<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Phase C: self-service learner profile and account security.
 * Every operation derives ownership exclusively from the central session.
 */
final class MD_Learning_Profile {
    public static function init() {
        $actions = array(
            'md_learning_profile_update' => 'update_name',
            'md_learning_rotate_self' => 'rotate_self',
            'md_learning_revoke_session' => 'revoke_session',
            'md_learning_signout_others' => 'signout_others',
        );
        foreach ($actions as $action => $method) {
            add_action('admin_post_' . $action, array(__CLASS__, $method));
            add_action('admin_post_nopriv_' . $action, array(__CLASS__, 'unauthenticated'));
        }
    }

    public static function unauthenticated() {
        wp_safe_redirect(MD_Learning_Schema::url('login'), 303);
        exit;
    }

    private static function authorize($nonce_action) {
        $id = MD_Learning_Auth::current_learner_id();
        if (!$id) {
            self::unauthenticated();
        }
        check_admin_referer($nonce_action);
        return $id;
    }

    public static function dashboard_url($tab, $notice = '') {
        $url = add_query_arg('md_tab', $tab, MD_Learning_Schema::url('dashboard'));
        return $notice ? add_query_arg('md_notice', $notice, $url) : $url;
    }

    private static function fail($message, $status = 400) {
        wp_die(esc_html($message), '', array('response' => $status));
    }

    public static function update_name() {
        $id = self::authorize('md_learning_profile_update');
        $name = isset($_POST['md_name']) && is_string($_POST['md_name'])
            ? trim(sanitize_text_field(wp_unslash($_POST['md_name']))) : '';
        if (!$name || (function_exists('mb_strlen') ? mb_strlen($name) > 120 : strlen($name) > 360)) {
            self::fail('نام باید بین ۱ تا ۱۲۰ نویسه باشد.');
        }
        global $wpdb;
        $updated = $wpdb->update(
            MD_Learning_Schema::table('learners'),
            array('display_name' => $name, 'updated_at' => gmdate('Y-m-d H:i:s')),
            array('id' => $id, 'status' => 'active'),
            array('%s','%s'), array('%d','%s')
        );
        if ($updated === false) {
            self::fail('ویرایش نام انجام نشد. دوباره تلاش کنید.', 500);
        }
        wp_safe_redirect(self::dashboard_url('profile', 'name_updated'), 303);
        exit;
    }

    /** An account owner can rotate their own credentials without email. */
    public static function rotate_self() {
        $id = self::authorize('md_learning_rotate_self');
        if (empty($_POST['md_confirm_rotate'])) {
            self::fail('برای صدور مجدد اطلاعات ورود، ابتدا تأیید کنید.');
        }
        $credentials = MD_Learning_Auth::rotate_credentials($id);
        if (is_wp_error($credentials)) {
            self::fail($credentials->get_error_message(), 500);
        }
        // Credential rotation invalidates every previous session. Create
        // a fresh session before displaying the one-time code/link receipt.
        $session = MD_Learning_Auth::create_session($id);
        if (is_wp_error($session)) {
            // The credentials remain valid and an administrator can reissue.
            self::fail('کد و لینک تغییر کردند اما ورود مجدد برقرار نشد. برای بازیابی از مدیر سایت کمک بگیرید.', 500);
        }
        MD_Learning_Auth::store_receipt($session, $credentials);
        wp_safe_redirect(self::dashboard_url('security', 'credentials_rotated'), 303);
        exit;
    }

    /**
     * List only active session metadata; never return bearer tokens or
     * hashes to the browser.
     */
    public static function sessions_for_current() {
        $id = MD_Learning_Auth::current_learner_id();
        if (!$id) {
            return array();
        }
        global $wpdb;
        $table = MD_Learning_Schema::table('sessions');
        $now = gmdate('Y-m-d H:i:s');
        $idle_cutoff = gmdate('Y-m-d H:i:s', time() - MD_Learning_Auth::IDLE_SECONDS);
        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT id, token_hash, created_at, last_seen_at, expires_at
             FROM {$table}
             WHERE learner_id = %d AND expires_at > %s AND last_seen_at > %s
             ORDER BY last_seen_at DESC LIMIT 50",
            $id, $now, $idle_cutoff
        ));
    }

    /** Revoke only a session belonging to this learner. */
    public static function revoke_session() {
        $id = self::authorize('md_learning_revoke_session');
        $session_id = isset($_POST['md_session_id']) ? absint($_POST['md_session_id']) : 0;
        if (!$session_id) {
            self::fail('نشست انتخاب‌شده معتبر نیست.');
        }
        global $wpdb;
        $table = MD_Learning_Schema::table('sessions');
        $hash = $wpdb->get_var($wpdb->prepare(
            "SELECT token_hash FROM {$table} WHERE id = %d AND learner_id = %d LIMIT 1",
            $session_id, $id
        ));
        if (!$hash) {
            self::fail('نشست یافت نشد یا متعلق به حساب شما نیست.', 403);
        }
        $current = hash_equals((string) $hash, (string) MD_Learning_Auth::current_session_hash());
        $deleted = $wpdb->delete($table, array('id' => $session_id, 'learner_id' => $id), array('%d','%d'));
        if ($deleted !== 1) {
            self::fail('پایان‌دادن به نشست انجام نشد.', 500);
        }
        if ($current) {
            MD_Learning_Auth::forget_current_cookie();
            wp_safe_redirect(MD_Learning_Schema::url('login'), 303);
        } else {
            wp_safe_redirect(self::dashboard_url('security', 'session_revoked'), 303);
        }
        exit;
    }

    /** End all other sessions, preserving this browser. */
    public static function signout_others() {
        $id = self::authorize('md_learning_signout_others');
        $hash = MD_Learning_Auth::current_session_hash();
        if (!$hash) {
            self::unauthenticated();
        }
        global $wpdb;
        $table = MD_Learning_Schema::table('sessions');
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE learner_id = %d AND token_hash <> %s",
            $id, $hash
        ));
        if ($deleted === false) {
            self::fail('خروج از دستگاه‌های دیگر ممکن نشد.', 500);
        }
        wp_safe_redirect(self::dashboard_url('security', 'other_sessions_ended'), 303);
        exit;
    }
}
