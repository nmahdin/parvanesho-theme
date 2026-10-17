<?php
/**
 * Private challenge enrollment, bearer links and participant progress.
 *
 * @package Morvarid_Daron
 */
if (!defined('ABSPATH')) { exit; }

function md_challenge_register_private_types() {
    foreach (array(
        'md_challenge_member' => 'شرکت‌کننده چالش',
        'md_challenge_entry' => 'پاسخ چالش',
    ) as $type => $label) {
        if (!post_type_exists($type)) {
            register_post_type($type, array(
                'label' => $label,
                'public' => false,
                'publicly_queryable' => false,
                'exclude_from_search' => true,
                'show_ui' => false,
                'show_in_menu' => false,
                'show_in_rest' => false,
                'rewrite' => false,
                'query_var' => false,
                'supports' => array('title'),
                'capability_type' => 'post',
                'map_meta_cap' => true,
            ));
        }
    }
}
add_action('init', 'md_challenge_register_private_types', 7);

/**
 * Internal private CPT lookup. Public WP_Query deliberately excludes private
 * posts for signed-out visitors, even when explicitly requested. Authorise
 * the caller before accessing data returned by this helper.
 */
function md_challenge_private_posts_by_meta($type, $key, $value) {
    global $wpdb;
    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
         WHERE p.post_type = %s AND p.post_status = 'private'
         AND m.meta_key = %s AND m.meta_value = %s
         ORDER BY p.post_date DESC, p.ID DESC",
        $type, $key, (string) $value
    ));
    return array_values(array_filter(array_map('get_post', array_map('intval', $ids))));
}

function md_challenge_participants($challenge_id) {
    return md_challenge_private_posts_by_meta('md_challenge_member', '_md_challenge_id', absint($challenge_id));
}

function md_challenge_find_member_by_email($challenge_id, $email_hash) {
    global $wpdb;
    $id = $wpdb->get_var($wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} c ON c.post_id = p.ID
         INNER JOIN {$wpdb->postmeta} e ON e.post_id = p.ID
         WHERE p.post_type = 'md_challenge_member' AND p.post_status = 'private'
         AND c.meta_key = '_md_challenge_id' AND c.meta_value = %s
         AND e.meta_key = '_md_member_email_hash' AND e.meta_value = %s LIMIT 1",
        (string) absint($challenge_id), $email_hash
    ));
    return $id ? absint($id) : 0;
}

function md_challenge_limit_response($text, $max) {
    $text = sanitize_textarea_field($text);
    return function_exists('mb_substr') ? mb_substr($text, 0, $max) : substr($text, 0, $max * 2);
}

function md_challenge_member_link($member) {
    $member_id = is_object($member) ? $member->ID : absint($member);
    $challenge_id = absint(get_post_meta($member_id, '_md_challenge_id', true));
    $token = (string) get_post_meta($member_id, '_md_member_key', true);
    if (!$challenge_id || !$token || get_post_status($member_id) !== 'private' || get_post_meta($member_id, '_md_member_revoked', true)) {
        return '';
    }
    return add_query_arg(array('md_member' => $member_id, 'md_key' => $token), get_permalink($challenge_id));
}

function md_challenge_member_from_request($challenge_id) {
    $id = isset($_GET['md_member']) ? absint($_GET['md_member']) : 0;
    $token = isset($_GET['md_key']) && is_string($_GET['md_key']) ? sanitize_text_field(wp_unslash($_GET['md_key'])) : '';
    return md_challenge_check_member($challenge_id, $id, $token);
}

function md_challenge_check_member($challenge_id, $member_id, $token) {
    if (!$member_id || !preg_match('/^[a-f0-9]{64}$/D', $token)) {
        return null;
    }
    $member = get_post($member_id);
    if (!$member || $member->post_type !== 'md_challenge_member' || $member->post_status !== 'private') {
        return null;
    }
    if ((int) get_post_meta($member_id, '_md_challenge_id', true) !== (int) $challenge_id
        || (bool) get_post_meta($member_id, '_md_member_revoked', true)) {
        return null;
    }
    $expected = (string) get_post_meta($member_id, '_md_member_key', true);
    return $expected && hash_equals($expected, $token) ? $member : null;
}

function md_challenge_member_request_headers() {
    if (isset($_GET['md_member']) || isset($_GET['md_key'])) {
        nocache_headers();
        header('Referrer-Policy: no-referrer', true);
        header('X-Robots-Tag: noindex, nofollow, noarchive', true);
        add_filter('wp_robots', function ($robots) {
            $robots['noindex'] = true;
            $robots['nofollow'] = true;
            return $robots;
        });
    }
}
add_action('template_redirect', 'md_challenge_member_request_headers', 1);

function md_challenge_member_rate_limit($action) {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    $key = 'md_c_rate_' . md5($action . '|' . $ip . '|' . wp_salt('auth'));
    $count = (int) get_transient($key);
    if ($count >= 8) {
        wp_die('تعداد درخواست‌ها بیش از حد مجاز است. بعداً دوباره تلاش کنید.', '', array('response' => 429));
    }
    set_transient($key, $count + 1, HOUR_IN_SECONDS);
}

function md_challenge_registration_form($challenge_id) {
    if (get_post_status($challenge_id) !== 'publish') {
        return;
    }
    ?>
    <section class="md-member-registration" id="md-challenge-enroll" aria-labelledby="md-enroll-heading">
        <h2 id="md-enroll-heading">ثبت‌نام و شروع چالش</h2>
        <p>با وارد کردن نام و ایمیل، لینک خصوصی مسیر خود را دریافت می‌کنید. پاسخ‌ها و تمرین‌های شما برای بازدیدکنندگان دیگر نمایش داده نمی‌شوند.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="md-member-registration-form">
            <input type="hidden" name="action" value="md_challenge_register_member">
            <input type="hidden" name="challenge_id" value="<?php echo esc_attr($challenge_id); ?>">
            <?php wp_nonce_field('md_challenge_register_' . $challenge_id); ?>
            <label>نام و نام خانوادگی <input type="text" name="member_name" maxlength="120" autocomplete="name" required></label>
            <label>ایمیل <input type="email" name="member_email" autocomplete="email" maxlength="190" required></label>
            <label class="md-enroll-consent"><input type="checkbox" name="member_consent" value="1" required> با ذخیره نام، ایمیل و پاسخ‌هایم برای اجرای این چالش موافقم.</label>
            <div class="md-enroll-honeypot" aria-hidden="true"><label>وب‌سایت <input type="text" name="member_website" tabindex="-1" autocomplete="off"></label></div>
            <button type="submit" class="btn btn-primary">دریافت لینک اختصاصی من</button>
        </form>
        <p class="md-enroll-privacy">لینک اختصاصی مانند رمز عبور است؛ آن را فقط نزد خود نگه دارید. در ثبت‌نام مجدد با همان ایمیل، لینک موجود فقط به همان نشانی ایمیل ارسال می‌شود.</p>
    </section>
    <?php
}

function md_challenge_register_member() {
    $challenge_id = isset($_POST['challenge_id']) ? absint($_POST['challenge_id']) : 0;
    if (!$challenge_id || get_post_type($challenge_id) !== 'md_challenge' || get_post_status($challenge_id) !== 'publish') {
        wp_die('چالش برای ثبت‌نام در دسترس نیست.', '', array('response' => 400));
    }
    check_admin_referer('md_challenge_register_' . $challenge_id);
    md_challenge_member_rate_limit('register');
    if (!empty($_POST['member_website'])) {
        wp_safe_redirect(get_permalink($challenge_id));
        exit;
    }
    if (empty($_POST['member_consent'])) {
        wp_die('برای ثبت‌نام، رضایت به ذخیره اطلاعات ضروری است.', '', array('response' => 400));
    }
    $name = isset($_POST['member_name']) ? sanitize_text_field(wp_unslash($_POST['member_name'])) : '';
    $email = isset($_POST['member_email']) ? sanitize_email(wp_unslash($_POST['member_email'])) : '';
    if (!$name || strlen($name) > 360 || !is_email($email)) {
        wp_die('نام و ایمیل معتبر را وارد کنید.', '', array('response' => 400));
    }

    $email_hash = hash_hmac('sha256', strtolower($email), wp_salt('auth'));
    $existing_id = md_challenge_find_member_by_email($challenge_id, $email_hash);
    if ($existing_id) {
        $old_link = md_challenge_member_link($existing_id);
        if ($old_link && !get_post_meta($existing_id, '_md_member_revoked', true)) {
            wp_mail($email, 'لینک اختصاصی چالش ' . get_the_title($challenge_id), "لینک خصوصی ورود شما:\n" . $old_link . "\nاین لینک را محرمانه نگه دارید.");
        }
        // Never reveal an existing person's private link to an unverified caller.
        wp_safe_redirect(add_query_arg('md_enroll_result', 'check-email', get_permalink($challenge_id)) . '#md-challenge-enroll');
        exit;
    }

    $member_id = wp_insert_post(array('post_type' => 'md_challenge_member', 'post_status' => 'private', 'post_title' => $name), true);
    if (is_wp_error($member_id) || !$member_id) {
        wp_die('ثبت‌نام انجام نشد. دوباره تلاش کنید.', '', array('response' => 500));
    }
    $key = bin2hex(random_bytes(32));
    update_post_meta($member_id, '_md_challenge_id', $challenge_id);
    update_post_meta($member_id, '_md_member_email', $email);
    update_post_meta($member_id, '_md_member_email_hash', $email_hash);
    update_post_meta($member_id, '_md_member_key', $key);
    update_post_meta($member_id, '_md_member_registered_at', current_time('mysql'));

    $link = md_challenge_member_link($member_id);
    wp_mail($email, 'لینک اختصاصی چالش ' . get_the_title($challenge_id), "ثبت‌نام شما انجام شد.\nلینک اختصاصی چالش:\n" . $link . "\nلطفاً لینک را محرمانه نگه دارید.");
    wp_safe_redirect($link);
    exit;
}
add_action('admin_post_md_challenge_register_member', 'md_challenge_register_member');
add_action('admin_post_nopriv_md_challenge_register_member', 'md_challenge_register_member');

/** One saved record per participant/day; submissions stay private. */
function md_challenge_member_entries($member_id) {
    $rows = md_challenge_private_posts_by_meta('md_challenge_entry', '_md_member_id', absint($member_id));
    $map = array();
    foreach ($rows as $row) {
        $day_id = absint(get_post_meta($row->ID, '_md_day_id', true));
        if ($day_id) {
            $map[$day_id] = $row;
        }
    }
    return $map;
}

function md_challenge_day_prompts($day) {
    $text = (string) get_post_meta($day->ID, '_md_day_questions', true);
    $lines = preg_split('/\r\n|\r|\n/', $text);
    return array_slice(array_values(array_filter(array_map('trim', $lines), 'strlen')), 0, 12);
}

function md_challenge_member_can_open_day($day, $challenge_id, $entries, $published_days) {
    if (!md_challenge_day_is_unlocked($day, $challenge_id)) {
        return false;
    }
    // Undated challenges progress one completed step at a time.
    if (md_challenge_calendar_days($challenge_id) === 0) {
        foreach ($published_days as $item) {
            if ($item->ID === $day->ID) {
                return true;
            }
            if (empty($entries[$item->ID]) || !get_post_meta($entries[$item->ID]->ID, '_md_task_done', true)) {
                return false;
            }
        }
    }
    return true;
}

function md_challenge_save_member_entry() {
    $challenge_id = isset($_POST['challenge_id']) ? absint($_POST['challenge_id']) : 0;
    $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
    $day_id = isset($_POST['day_id']) ? absint($_POST['day_id']) : 0;
    $token = isset($_POST['md_key']) && is_string($_POST['md_key']) ? sanitize_text_field(wp_unslash($_POST['md_key'])) : '';
    $member = md_challenge_check_member($challenge_id, $member_id, $token);
    if (!$member || get_post_status($challenge_id) !== 'publish') {
        wp_die('لینک خصوصی نامعتبر یا غیرفعال است.', '', array('response' => 403));
    }
    check_admin_referer('md_challenge_entry_' . $member_id . '_' . $day_id);
    md_challenge_member_rate_limit('entry-' . $member_id);
    $day = md_challenge_day($day_id);
    $days = md_challenge_days($challenge_id);
    $ids = array_map('intval', wp_list_pluck($days, 'ID'));
    $entries = md_challenge_member_entries($member_id);
    if (!$day || !in_array($day_id, $ids, true) || !md_challenge_member_can_open_day($day, $challenge_id, $entries, $days)) {
        wp_die('این روز هنوز برای شما فعال نیست.', '', array('response' => 403));
    }
    $prompts = md_challenge_day_prompts($day);
    $submitted = isset($_POST['answers']) && is_array($_POST['answers']) ? wp_unslash($_POST['answers']) : array();
    $answers = array();
    foreach ($prompts as $index => $prompt) {
        $answers[] = isset($submitted[$index]) && is_string($submitted[$index]) ? md_challenge_limit_response($submitted[$index], 4000) : '';
    }
    $notes = isset($_POST['member_notes']) && is_string($_POST['member_notes']) ? md_challenge_limit_response(wp_unslash($_POST['member_notes']), 5000) : '';
    $completed = !empty($_POST['task_done']) ? 1 : 0;
    $entry = isset($entries[$day_id]) ? $entries[$day_id] : null;
    if (!$entry) {
        $entry_id = wp_insert_post(array('post_type' => 'md_challenge_entry', 'post_status' => 'private',
            'post_title' => 'پاسخ روز ' . $day_id . ' - ' . $member_id), true);
        if (is_wp_error($entry_id) || !$entry_id) {
            wp_die('ذخیره پاسخ ممکن نشد.', '', array('response' => 500));
        }
        update_post_meta($entry_id, '_md_member_id', $member_id);
        update_post_meta($entry_id, '_md_day_id', $day_id);
        update_post_meta($entry_id, '_md_challenge_id', $challenge_id);
    } else {
        $entry_id = $entry->ID;
    }
    update_post_meta($entry_id, '_md_answers', $answers);
    update_post_meta($entry_id, '_md_member_notes', $notes);
    update_post_meta($entry_id, '_md_task_done', $completed);
    update_post_meta($entry_id, '_md_answer_updated', current_time('mysql'));
    update_post_meta($member_id, '_md_member_last_activity', current_time('mysql'));
    wp_safe_redirect(add_query_arg('md_saved', $day_id, md_challenge_member_link($member)) . '#member-day-' . $day_id);
    exit;
}
add_action('admin_post_md_challenge_save_member_entry', 'md_challenge_save_member_entry');
add_action('admin_post_nopriv_md_challenge_save_member_entry', 'md_challenge_save_member_entry');

function md_challenge_render_member_dashboard($challenge_id, $member) {
    $days = md_challenge_days($challenge_id);
    $entries = md_challenge_member_entries($member->ID);
    $completed = 0;
    foreach ($days as $day) {
        if (isset($entries[$day->ID]) && get_post_meta($entries[$day->ID]->ID, '_md_task_done', true)) {
            $completed++;
        }
    }
    $total = count($days);
    $percent = $total ? (int) round($completed * 100 / $total) : 0;
    $token = (string) get_post_meta($member->ID, '_md_member_key', true);
    ?>
    <main class="md-member-page container" dir="rtl">
        <nav class="md-member-breadcrumb"><a href="<?php echo esc_url(get_permalink($challenge_id)); ?>">معرفی چالش</a> / داشبورد شخصی</nav>
        <header class="md-member-hero">
            <span class="eyebrow">مسیر اختصاصی من</span>
            <h1><?php echo esc_html($member->post_title); ?>، به چالش «<?php echo esc_html(get_the_title($challenge_id)); ?>» خوش آمدید</h1>
            <p>پاسخ‌های این صفحه خصوصی هستند. این لینک را برای دیگران ارسال نکنید.</p>
            <div class="md-member-progress"><strong><?php echo esc_html(md_fa_num($completed)); ?> از <?php echo esc_html(md_fa_num($total)); ?> روز تکمیل‌شده</strong><span><?php echo esc_html(md_fa_num($percent)); ?>٪</span></div>
            <div class="md-member-progress-track"><span style="width:<?php echo esc_attr($percent); ?>%"></span></div>
            <label class="md-member-link-label">لینک خصوصی شما
                <input type="text" readonly onclick="this.select()" value="<?php echo esc_attr(md_challenge_member_link($member)); ?>">
            </label>
        </header>
        <section class="md-member-days">
            <h2>گام‌های من</h2>
            <?php if (!$days) : ?><p>هنوز محتوای روزهای این چالش منتشر نشده است.</p><?php endif; ?>
            <?php foreach ($days as $day) :
                $opened = md_challenge_member_can_open_day($day, $challenge_id, $entries, $days);
                $entry = isset($entries[$day->ID]) ? $entries[$day->ID] : null;
                $is_done = $entry && get_post_meta($entry->ID, '_md_task_done', true);
                $answers = $entry ? get_post_meta($entry->ID, '_md_answers', true) : array();
                if (!is_array($answers)) { $answers = array(); }
                $notes = $entry ? (string) get_post_meta($entry->ID, '_md_member_notes', true) : '';
                $prompts = md_challenge_day_prompts($day);
                ?>
                <article id="member-day-<?php echo esc_attr($day->ID); ?>" class="md-member-day <?php echo $is_done ? 'is-done' : ''; ?>">
                    <div class="md-member-day-head">
                        <span>روز <?php echo esc_html(md_fa_num(md_challenge_day_order($day->ID))); ?></span>
                        <h3><?php echo esc_html($opened ? $day->post_title : (md_challenge_show_locked_title($challenge_id) ? $day->post_title : 'روز قفل‌شده')); ?></h3>
                        <strong><?php echo $is_done ? 'انجام‌شده ✓' : ($opened ? 'آماده انجام' : 'قفل'); ?></strong>
                    </div>
                    <?php if (!$opened) : ?>
                        <p>این مرحله هنوز برای شما در دسترس نیست. پس از تکمیل مرحله پیشین یا رسیدن زمان انتشار باز می‌شود.</p>
                    <?php else : ?>
                    <div class="md-member-day-content entry-content"><?php echo apply_filters('the_content', $day->post_content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    <?php echo md_challenge_day_tip_html($day); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php echo md_challenge_day_exercise_html($day); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php echo md_challenge_day_media_html($day); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <form class="md-member-day-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                        <input type="hidden" name="action" value="md_challenge_save_member_entry">
                        <input type="hidden" name="challenge_id" value="<?php echo esc_attr($challenge_id); ?>">
                        <input type="hidden" name="member_id" value="<?php echo esc_attr($member->ID); ?>">
                        <input type="hidden" name="md_key" value="<?php echo esc_attr($token); ?>">
                        <input type="hidden" name="day_id" value="<?php echo esc_attr($day->ID); ?>">
                        <?php wp_nonce_field('md_challenge_entry_' . $member->ID . '_' . $day->ID); ?>
                        <?php foreach ($prompts as $i => $prompt) : ?>
                            <label><strong><?php echo esc_html($prompt); ?></strong>
                                <textarea name="answers[<?php echo esc_attr($i); ?>]" rows="3" maxlength="4000"><?php echo esc_textarea(isset($answers[$i]) ? $answers[$i] : ''); ?></textarea>
                            </label>
                        <?php endforeach; ?>
                        <label><strong>یادداشت و گزارش تمرین من</strong>
                            <textarea name="member_notes" rows="4" maxlength="5000"><?php echo esc_textarea($notes); ?></textarea>
                        </label>
                        <label class="md-member-check"><input type="checkbox" name="task_done" value="1" <?php checked($is_done); ?>> این مرحله را انجام داده‌ام.</label>
                        <button type="submit" class="btn btn-primary">ذخیره پاسخ و پیشرفت</button>
                        <?php if (isset($_GET['md_saved']) && absint($_GET['md_saved']) === $day->ID) : ?>
                            <span class="md-member-saved" role="status">پاسخ شما ذخیره شد.</span>
                        <?php endif; ?>
                    </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
    </main>
    <?php
}
