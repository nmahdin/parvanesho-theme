<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Phase B: central learner identity -> private challenge membership.
 * Challenge days and answer entries stay in the theme's existing post types.
 * Historical challenge bearer credentials are NOT accepted by this bridge.
 */
final class MD_Learning_Challenges {
    public static function init() {
        add_action('admin_post_md_learning_challenge_join', array(__CLASS__, 'join'));
        add_action('admin_post_nopriv_md_learning_challenge_join', array(__CLASS__, 'join'));
    }

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'md_learning_challenges';
    }

    private static function valid_challenge($id) {
        return $id && 'md_challenge' === get_post_type($id)
            && 'publish' === get_post_status($id);
    }

    public static function enabled() {
        return function_exists('md_challenge_member_entries')
            && function_exists('md_challenge_days')
            && post_type_exists('md_challenge_member');
    }

    public static function member_id($learner_id, $challenge_id) {
        if (!$learner_id || !$challenge_id) {
            return 0;
        }
        global $wpdb;
        $table = self::table();
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT member_id FROM {$table} WHERE learner_id = %d AND challenge_id = %d LIMIT 1",
            absint($learner_id), absint($challenge_id)
        ));
    }

    public static function current_member($challenge_id) {
        if (!self::enabled() || !self::valid_challenge($challenge_id)) {
            return null;
        }
        $id = MD_Learning_Auth::current_learner_id();
        $member_id = self::member_id($id, $challenge_id);
        if (!$member_id) {
            return null;
        }
        $member = get_post($member_id);
        if (!$member || 'md_challenge_member' !== $member->post_type
            || 'private' !== $member->post_status
            || (int) get_post_meta($member_id, '_md_challenge_id', true) !== (int) $challenge_id
            || get_post_meta($member_id, '_md_member_revoked', true)) {
            return null;
        }
        return $member;
    }

    /** Check answer ownership using the validated central session, not form ID. */
    public static function owns_member($challenge_id, $member_id) {
        $member = self::current_member($challenge_id);
        return $member && (int) $member->ID === absint($member_id);
    }

    public static function learner_id_for_member($member_id) {
        global $wpdb;
        $table = self::table();
        return $member_id ? (int) $wpdb->get_var($wpdb->prepare(
            "SELECT learner_id FROM {$table} WHERE member_id = %d LIMIT 1",
            absint($member_id)
        )) : 0;
    }

    public static function members_for_challenge($challenge_id) {
        if (!current_user_can('edit_post', absint($challenge_id))) {
            return array();
        }
        global $wpdb;
        $table = self::table();
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT member_id FROM {$table} WHERE challenge_id = %d ORDER BY id DESC",
            absint($challenge_id)
        ));
        return array_values(array_filter(array_map('get_post', array_map('absint', $ids))));
    }

    public static function enroll($challenge_id, $learner_id) {
        $challenge_id = absint($challenge_id);
        $learner_id = absint($learner_id);
        if (!self::enabled() || !self::valid_challenge($challenge_id)) {
            return new WP_Error('challenge_unavailable', 'این چالش برای عضویت در دسترس نیست.');
        }
        $learner = MD_Learning_Auth::get_learner($learner_id);
        if (!$learner || $learner->status !== 'active') {
            return new WP_Error('login_required', 'ابتدا وارد پروفایل یادگیرنده شوید.');
        }

        $existing = self::member_id($learner_id, $challenge_id);
        if ($existing) {
            $member = get_post($existing);
            return $member && $member->post_type === 'md_challenge_member'
                && $member->post_status === 'private'
                && (int) get_post_meta($member->ID, '_md_challenge_id', true) === $challenge_id
                && !get_post_meta($member->ID, '_md_member_revoked', true)
                ? $member : new WP_Error('membership_unavailable', 'عضویت این چالش در دسترس نیست.');
        }

        $member_id = wp_insert_post(array(
            'post_type' => 'md_challenge_member',
            'post_status' => 'private',
            'post_title' => $learner->display_name,
        ), true);
        if (is_wp_error($member_id) || !$member_id) {
            return new WP_Error('create_failed', 'ثبت عضویت ممکن نشد.');
        }

        update_post_meta($member_id, '_md_challenge_id', $challenge_id);
        update_post_meta($member_id, '_md_member_registered_at', current_time('mysql'));

        global $wpdb;
        $saved = $wpdb->insert(self::table(), array(
            'learner_id' => $learner_id,
            'challenge_id' => $challenge_id,
            'member_id' => $member_id,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ), array('%d','%d','%d','%s'));
        if (!$saved) {
            // The unique DB constraint resolves simultaneous join requests.
            wp_delete_post($member_id, true);
            $existing = self::member_id($learner_id, $challenge_id);
            if ($existing) {
                $member = get_post($existing);
                return $member && $member->post_type === 'md_challenge_member'
                    && $member->post_status === 'private'
                    && !get_post_meta($member->ID, '_md_member_revoked', true)
                    ? $member : new WP_Error('membership_unavailable', 'عضویت غیرفعال است.');
            }
            return new WP_Error('conflict', 'ذخیره عضویت انجام نشد. دوباره تلاش کنید.');
        }
        return get_post($member_id);
    }

    public static function join() {
        $challenge_id = isset($_POST['challenge_id']) ? absint($_POST['challenge_id']) : 0;
        check_admin_referer('md_learning_challenge_join_' . $challenge_id);
        $learner_id = MD_Learning_Auth::current_learner_id();
        if (!$learner_id) {
            wp_die('برای عضویت، ابتدا با کد اصلی خود وارد شوید.', '', array('response' => 403));
        }
        if (empty($_POST['md_join_consent'])) {
            wp_die('برای عضویت، رضایت به ذخیره پاسخ‌ها ضروری است.', '', array('response' => 400));
        }
        $member = self::enroll($challenge_id, $learner_id);
        if (is_wp_error($member)) {
            wp_die(esc_html($member->get_error_message()), '', array('response' => 409));
        }
        wp_safe_redirect(get_permalink($challenge_id), 303);
        exit;
    }

    public static function render_join($challenge_id) {
        if (!self::enabled() || !self::valid_challenge($challenge_id)) {
            return;
        }
        $learner_id = MD_Learning_Auth::current_learner_id();
        echo '<section class="md-member-registration md-learning-challenge-join" id="md-challenge-enroll" dir="rtl">';
        echo '<h2>عضویت در چالش</h2>';
        if (!$learner_id) {
            $redirect_login = add_query_arg('md_challenge', absint($challenge_id), MD_Learning_Schema::url('login'));
            $redirect_register = add_query_arg('md_challenge', absint($challenge_id), MD_Learning_Schema::url('register'));
            echo '<p>با یک کد ۸ رقمی اصلی وارد تمام چالش‌های خود شوید. نیازی به ثبت‌نام جداگانه در هر چالش نیست.</p>';
            echo '<p><a class="btn btn-primary" href="' . esc_url($redirect_login) . '">ورود با کد اصلی</a> ';
            echo '<a class="btn btn-light" href="' . esc_url($redirect_register) . '">ساخت پروفایل یادگیری</a></p>';
        } else {
            echo '<p>شما وارد پروفایل یکپارچه هستید؛ با تأیید، این چالش به داشبوردتان اضافه می‌شود.</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="md_learning_challenge_join">';
            echo '<input type="hidden" name="challenge_id" value="' . esc_attr($challenge_id) . '">';
            wp_nonce_field('md_learning_challenge_join_' . $challenge_id);
            echo '<label class="md-enroll-consent"><input type="checkbox" name="md_join_consent" value="1" required> با ثبت پیشرفت و پاسخ‌های این چالش در پروفایل خودم موافقم.</label>';
            echo '<button type="submit" class="btn btn-primary">پیوستن به چالش</button></form>';
        }
        echo '</section>';
    }

    /** Challenge tiles, accessible only for the owner of this central identity. */
    public static function dashboard_html($learner_id) {
        // The method is callable by third-party code, so enforce ownership
        // here as well as at the dashboard shortcode.
        if (!$learner_id || absint($learner_id) !== MD_Learning_Auth::current_learner_id()) {
            return '<p>برای دیدن چالش‌های خود وارد حساب شوید.</p>';
        }
        if (!self::enabled()) {
            return '<p>برای مشاهده چالش‌ها، قالب مروارید درون باید فعال باشد.</p>';
        }
        global $wpdb;
        $table = self::table();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT challenge_id, member_id FROM {$table}
             WHERE learner_id = %d ORDER BY id DESC", absint($learner_id)
        ));
        if (!$rows) {
            return '<p>هنوز در چالشی عضو نشده‌اید. <a href="' . esc_url(get_post_type_archive_link('md_challenge')) . '">مشاهده چالش‌ها</a></p>';
        }
        $html = '<div class="md-learning-challenge-grid">';
        foreach ($rows as $row) {
            $challenge = get_post(absint($row->challenge_id));
            $member = get_post(absint($row->member_id));
            if (!$challenge || $challenge->post_type !== 'md_challenge' || $challenge->post_status !== 'publish'
                || !$member || $member->post_type !== 'md_challenge_member'
                || $member->post_status !== 'private' || get_post_meta($member->ID, '_md_member_revoked', true)) {
                continue;
            }
            $days = md_challenge_days($challenge->ID);
            $entries = md_challenge_member_entries($member->ID);
            $done = 0;
            foreach ($days as $day) {
                if (isset($entries[$day->ID]) && get_post_meta($entries[$day->ID]->ID, '_md_task_done', true)) {
                    $done++;
                }
            }
            $total = count($days);
            $pct = $total ? (int) round($done * 100 / $total) : 0;
            $html .= '<article class="md-learning-challenge-card"><h3>' . esc_html($challenge->post_title) . '</h3>';
            $html .= '<p>' . esc_html($done . ' از ' . $total . ' روز تکمیل‌شده') . ' · ' . esc_html($pct) . '٪</p>';
            $html .= '<div class="md-learning-challenge-progress"><span style="width:' . esc_attr($pct) . '%"></span></div>';
            $html .= '<a href="' . esc_url(get_permalink($challenge->ID)) . '">ادامه چالش ←</a></article>';
        }
        return $html . '</div>';
    }
}
