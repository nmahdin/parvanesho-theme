<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom learner authentication (independent of WordPress administrator users).
 *
 * Deliberately no code-login rate limiter or lockout, per site owner's choice.
 * Eight-digit codes therefore provide materially weaker protection than links.
 */
final class MD_Learning_Auth {
    const COOKIE = 'md_learning_session';
    const IDLE_SECONDS = 604800;    // 7 days.
    const MAX_SECONDS = 2592000;    // 30 days.
    private static $cached_id = null;

    public static function init() {
        add_action('admin_post_nopriv_md_learning_register', array(__CLASS__, 'register'));
        add_action('admin_post_md_learning_register', array(__CLASS__, 'register'));
        add_action('admin_post_nopriv_md_learning_login', array(__CLASS__, 'login'));
        add_action('admin_post_md_learning_login', array(__CLASS__, 'login'));
        add_action('admin_post_nopriv_md_learning_logout', array(__CLASS__, 'logout'));
        add_action('admin_post_md_learning_logout', array(__CLASS__, 'logout'));
        add_action('template_redirect', array(__CLASS__, 'exchange_link'), 0);
        add_filter('query_vars', array(__CLASS__, 'link_query_var'));
        // WordPress treats all logged-out visitors as user ID 0 when
        // generating nonces. Bind sensitive learner/challenge nonces to the
        // authenticated central session instead of the shared guest nonce.
        add_filter('nonce_user_logged_out', array(__CLASS__, 'nonce_learner_session'), 10, 2);
    }

    /** Human-friendly URL with 128 bits of cryptographic randomness. */
    public static function register_link_route() {
        add_rewrite_rule('^l/([A-Za-z0-9_-]{22})/?
        return hash_hmac('sha256', $purpose . ':' . (string) $secret, wp_salt('auth'));
    }

    public static function find_by_code($code) {
        $code = self::normalize_code($code);
        if (!preg_match('/^[0-9]{8}$/D', $code)) {
            return null;
        }
        global $wpdb;
        $table = MD_Learning_Schema::table('learners');
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE code_hash = %s AND status = 'active' LIMIT 1",
            self::hash_secret('code', $code)
        ));
        return $id ? self::get_learner(absint($id)) : null;
    }

    public static function normalize_code($code) {
        return strtr(trim((string) $code), array(
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        ));
    }

    public static function get_learner($id) {
        if (!$id) {
            return null;
        }
        global $wpdb;
        $table = MD_Learning_Schema::table('learners');
        return $wpdb->get_row($wpdb->prepare(
            "SELECT id, display_name, status, created_at, last_login_at FROM {$table} WHERE id = %d LIMIT 1",
            absint($id)
        ));
    }

    private static function generate_unused_code() {
        global $wpdb;
        $table = MD_Learning_Schema::table('learners');
        for ($i = 0; $i < 40; $i++) {
            $code = (string) random_int(10000000, 99999999);
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE code_hash = %s LIMIT 1",
                self::hash_secret('code', $code)
            ));
            if (!$exists) {
                return $code;
            }
        }
        return new WP_Error('code_unavailable', 'صدور کد ورود ممکن نشد. دوباره تلاش کنید.');
    }

    public static function create_learner($name) {
        $name = sanitize_text_field($name);
        if (!$name || strlen($name) > 600) {
            return new WP_Error('invalid_name', 'نام واردشده معتبر نیست.');
        }
        global $wpdb;
        $table = MD_Learning_Schema::table('learners');
        for ($i = 0; $i < 5; $i++) {
            $code = self::generate_unused_code();
            if (is_wp_error($code)) {
                return $code;
            }
            $now = gmdate('Y-m-d H:i:s');
            $created = $wpdb->insert($table, array(
                'display_name' => $name,
                'code_hash' => self::hash_secret('code', $code),
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ), array('%s','%s','%s','%s','%s'));
            // The UNIQUE code_hash index is the final collision safeguard.
            if (!$created) {
                continue;
            }
            $learner_id = (int) $wpdb->insert_id;
            $link = self::create_link($learner_id);
            if (is_wp_error($link)) {
                $wpdb->delete($table, array('id' => $learner_id), array('%d'));
                return $link;
            }
            return array('learner_id' => $learner_id, 'code' => $code, 'link' => $link);
        }
        return new WP_Error('create_failed', 'ساخت پروفایل ممکن نشد. دوباره تلاش کنید.');
    }

    public static function create_link($learner_id) {
        global $wpdb;
        $table = MD_Learning_Schema::table('links');
        $secret = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
        $result = $wpdb->insert($table, array(
            'learner_id' => absint($learner_id),
            'token_hash' => self::hash_secret('link', $secret),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ), array('%d','%s','%s'));
        if (!$result) {
            return new WP_Error('link_failed', 'ساخت لینک خصوصی ممکن نشد.');
        }
        return home_url('/l/' . rawurlencode($secret) . '/');
    }

    public static function rotate_credentials($learner_id) {
        $learner = self::get_learner($learner_id);
        if (!$learner) {
            return new WP_Error('missing_learner', 'پروفایل پیدا نشد.');
        }
        $code = self::generate_unused_code();
        if (is_wp_error($code)) {
            return $code;
        }
        global $wpdb;
        $learners = MD_Learning_Schema::table('learners');
        $links = MD_Learning_Schema::table('links');
        $sessions = MD_Learning_Schema::table('sessions');
        $wpdb->query('START TRANSACTION');
        $changed = $wpdb->update($learners, array(
            'code_hash' => self::hash_secret('code', $code),
            'status' => 'active',
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ), array('id' => $learner_id), array('%s','%s','%s'), array('%d'));
        if ($changed === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('rotate_failed', 'صدور کد جدید ممکن نشد.');
        }
        $wpdb->delete($links, array('learner_id' => $learner_id), array('%d'));
        $wpdb->delete($sessions, array('learner_id' => $learner_id), array('%d'));
        $link = self::create_link($learner_id);
        if (is_wp_error($link)) {
            $wpdb->query('ROLLBACK');
            return $link;
        }
        $wpdb->query('COMMIT');
        self::$cached_id = null;
        return array('learner_id' => (int) $learner_id, 'code' => $code, 'link' => $link);
    }

    public static function revoke_access($learner_id) {
        global $wpdb;
        $learners = MD_Learning_Schema::table('learners');
        $wpdb->query('START TRANSACTION');
        $updated = $wpdb->update($learners,
            array('status' => 'revoked', 'updated_at' => gmdate('Y-m-d H:i:s')),
            array('id' => absint($learner_id)), array('%s','%s'), array('%d'));
        if ($updated === false) {
            $wpdb->query('ROLLBACK');
            return false;
        }
        $wpdb->delete(MD_Learning_Schema::table('links'), array('learner_id' => $learner_id), array('%d'));
        $wpdb->delete(MD_Learning_Schema::table('sessions'), array('learner_id' => $learner_id), array('%d'));
        $wpdb->query('COMMIT');
        self::$cached_id = null;
        return true;
    }

    private static function https_allowed() {
        return is_ssl() || (function_exists('wp_get_environment_type') && wp_get_environment_type() === 'local');
    }

    public static function create_session($learner_id) {
        if (!self::https_allowed()) {
            return new WP_Error('https_required', 'برای ورود ایمن، سایت باید از HTTPS استفاده کند.');
        }
        $learner = self::get_learner($learner_id);
        if (!$learner || $learner->status !== 'active') {
            return new WP_Error('invalid_learner', 'این حساب فعال نیست.');
        }
        if (headers_sent()) {
            return new WP_Error('headers_sent', 'ایجاد نشست ممکن نشد. خروجی صفحه زودتر ارسال شده است.');
        }
        global $wpdb;
        $token = bin2hex(random_bytes(32));
        $now = gmdate('Y-m-d H:i:s');
        $stored = $wpdb->insert(MD_Learning_Schema::table('sessions'), array(
            'learner_id' => absint($learner_id),
            'token_hash' => self::hash_secret('session', $token),
            'created_at' => $now,
            'last_seen_at' => $now,
            'expires_at' => gmdate('Y-m-d H:i:s', time() + self::MAX_SECONDS),
        ), array('%d','%s','%s','%s','%s'));
        if (!$stored) {
            return new WP_Error('session_failed', 'ایجاد نشست ورود ممکن نشد.');
        }
        if (!setcookie(self::COOKIE, $token, array(
            'expires' => time() + self::MAX_SECONDS,
            'path' => '/',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ))) {
            $wpdb->delete(MD_Learning_Schema::table('sessions'), array('token_hash' => self::hash_secret('session', $token)), array('%s'));
            return new WP_Error('cookie_failed', 'مرورگر نشست را دریافت نکرد.');
        }
        $wpdb->update(MD_Learning_Schema::table('learners'),
            array('last_login_at' => $now), array('id' => $learner_id), array('%s'), array('%d'));
        self::$cached_id = (int) $learner_id;
        // Flash receipts are short-lived and rendered once, never placed in a URL.
        return self::hash_secret('session', $token);
    }

    public static function nonce_learner_session($guest_id, $action) {
        if (!is_string($action) || !preg_match(
            '/^(?:md_learning_(?:profile_update|rotate_self|revoke_session|signout_others|logout|challenge_join_[0-9]+)|md_challenge_entry_[0-9]+_[0-9]+|md_learning_question_save_[0-9]+_[a-f0-9]{32}|md_learning_course_join_[0-9]+|md_learning_lesson_progress_[0-9]+_[0-9]+)$/D',
            $action
        )) {
            return $guest_id;
        }
        $hash = self::current_session_hash();
        if (!$hash || !self::current_learner_id()) {
            return $guest_id;
        }
        // The pseudonymous WordPress nonce user value is unique per central
        // session, without exposing or storing the raw bearer token.
        return 'md-learner-' . $hash;
    }

    public static function current_session_hash() {
        $token = isset($_COOKIE[self::COOKIE]) && is_string($_COOKIE[self::COOKIE]) ? $_COOKIE[self::COOKIE] : '';
        return preg_match('/^[a-f0-9]{64}$/D', $token) ? self::hash_secret('session', $token) : '';
    }

    public static function current_learner_id() {
        if (self::$cached_id !== null) {
            return self::$cached_id;
        }
        self::$cached_id = 0;
        $hash = self::current_session_hash();
        if (!$hash) {
            return 0;
        }
        global $wpdb;
        $sessions = MD_Learning_Schema::table('sessions');
        $learners = MD_Learning_Schema::table('learners');
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT s.id, s.learner_id, s.last_seen_at FROM {$sessions} s
             INNER JOIN {$learners} l ON l.id = s.learner_id
             WHERE s.token_hash = %s AND s.expires_at > %s
             AND s.last_seen_at > %s AND l.status = 'active' LIMIT 1",
            $hash, gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s', time() - self::IDLE_SECONDS)
        ));
        if (!$row) {
            return 0;
        }
        self::$cached_id = (int) $row->learner_id;
        if (strtotime($row->last_seen_at . ' UTC') < time() - 600) {
            $wpdb->update($sessions, array('last_seen_at' => gmdate('Y-m-d H:i:s')),
                array('id' => $row->id), array('%s'), array('%d'));
        }
        return self::$cached_id;
    }

    public static function store_receipt($session_hash, $credentials) {
        if ($session_hash) {
            set_transient('md_learning_receipt_' . $session_hash, $credentials, 5 * MINUTE_IN_SECONDS);
        }
    }

    public static function take_receipt() {
        $hash = self::current_session_hash();
        if (!$hash || !self::current_learner_id()) {
            return null;
        }
        $key = 'md_learning_receipt_' . $hash;
        $value = get_transient($key);
        if ($value) {
            delete_transient($key);
        }
        return is_array($value) ? $value : null;
    }

    public static function requested_challenge() {
        $raw = isset($_POST['md_challenge']) ? $_POST['md_challenge'] : (isset($_GET['md_challenge']) ? $_GET['md_challenge'] : 0);
        $id = absint($raw);
        return $id && get_post_type($id) === 'md_challenge' && get_post_status($id) === 'publish' ? $id : 0;
    }

    public static function requested_resource() {
        $raw = isset($_POST['md_resource']) ? $_POST['md_resource']
            : (isset($_GET['md_resource']) ? $_GET['md_resource'] : 0);
        $id = absint($raw);
        return $id && in_array(get_post_type($id), MD_Learning_Questions::TYPES, true)
            && get_post_status($id) === 'publish' ? $id : 0;
    }

    public static function requested_course() {
        $raw = isset($_POST['md_course']) ? $_POST['md_course']
            : (isset($_GET['md_course']) ? $_GET['md_course'] : 0);
        $id = absint($raw);
        return $id && get_post_type($id) === 'md_course'
            && get_post_status($id) === 'publish' ? $id : 0;
    }

    public static function register() {
        $name = isset($_POST['md_name']) && is_string($_POST['md_name']) ? sanitize_text_field(wp_unslash($_POST['md_name'])) : '';
        check_admin_referer('md_learning_register');
        if (!empty($_POST['md_website'])) {
            wp_safe_redirect(MD_Learning_Schema::url('register'));
            exit;
        }
        if (!self::https_allowed()) {
            wp_die('برای ثبت‌نام و دریافت کد خصوصی، ابتدا HTTPS سایت را فعال کنید.', '', array('response' => 403));
        }
        if (empty($_POST['md_consent'])) {
            wp_die('برای ثبت‌نام، موافقت با ذخیره نام و پاسخ‌ها لازم است.', '', array('response' => 400));
        }
        $challenge_id = self::requested_challenge();
        $resource_id = self::requested_resource();
        $course_id = self::requested_course();
        if (self::current_learner_id()) {
            $dest = $challenge_id ? get_permalink($challenge_id)
                : ($resource_id ? get_permalink($resource_id)
                : ($course_id ? get_permalink($course_id) : MD_Learning_Schema::url('dashboard')));
            wp_safe_redirect($dest);
            exit;
        }
        $created = self::create_learner($name);
        if (is_wp_error($created)) {
            wp_die(esc_html($created->get_error_message()), '', array('response' => 400));
        }
        $session = self::create_session($created['learner_id']);
        if (is_wp_error($session)) {
            wp_die(esc_html($session->get_error_message()), '', array('response' => 500));
        }
        self::store_receipt($session, $created);
        $destination = MD_Learning_Schema::url('dashboard');
        if ($challenge_id) {
            $destination = add_query_arg('md_challenge', $challenge_id, $destination);
        } elseif ($resource_id) {
            $destination = add_query_arg('md_resource', $resource_id, $destination);
        } elseif ($course_id) {
            $destination = add_query_arg('md_course', $course_id, $destination);
        }
        wp_safe_redirect($destination);
        exit;
    }

    public static function login() {
        check_admin_referer('md_learning_login');
        $challenge_id = self::requested_challenge();
        $resource_id = self::requested_resource();
        $course_id = self::requested_course();
        if (!self::https_allowed()) {
            wp_die('برای ورود، ابتدا HTTPS سایت را فعال کنید.', '', array('response' => 403));
        }
        $raw = isset($_POST['md_code']) && is_string($_POST['md_code']) ? wp_unslash($_POST['md_code']) : '';
        $learner = self::find_by_code($raw);
        if (!$learner) {
            // Intentionally no attempt counter and no account lockout.
            $dest = add_query_arg('md_learning_error', 'invalid', MD_Learning_Schema::url('login'));
            if ($challenge_id) {
                $dest = add_query_arg('md_challenge', $challenge_id, $dest);
            } elseif ($resource_id) {
                $dest = add_query_arg('md_resource', $resource_id, $dest);
            } elseif ($course_id) {
                $dest = add_query_arg('md_course', $course_id, $dest);
            }
            wp_safe_redirect($dest);
            exit;
        }
        $session = self::create_session($learner->id);
        if (is_wp_error($session)) {
            wp_die(esc_html($session->get_error_message()), '', array('response' => 500));
        }
        wp_safe_redirect($challenge_id ? get_permalink($challenge_id)
            : ($resource_id ? get_permalink($resource_id)
            : ($course_id ? get_permalink($course_id) : MD_Learning_Schema::url('dashboard'))));
        exit;
    }

    public static function exchange_link() {
        $route = get_query_var('md_learning_link');
        if (!isset($_GET['md_learning_link']) && !$route) {
            return;
        }
        nocache_headers();
        header('Referrer-Policy: no-referrer', true);
        header('X-Robots-Tag: noindex, nofollow, noarchive', true);
        if (!self::https_allowed()) {
            wp_die('ورود با لینک خصوصی فقط روی HTTPS فعال است.', '', array('response' => 403));
        }
        $secret = isset($_GET['md_learning_link']) && is_string($_GET['md_learning_link'])
            ? sanitize_text_field(wp_unslash($_GET['md_learning_link']))
            : (is_string($route) ? $route : '');
        // Also honor previously issued 64-character links during rollout.
        if (!preg_match('/^(?:[A-Za-z0-9_-]{22}|[a-f0-9]{64})$/D', $secret)) {
            self::invalid_link_redirect();
        }
        global $wpdb;
        $links = MD_Learning_Schema::table('links');
        $learners = MD_Learning_Schema::table('learners');
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT t.learner_id, t.id FROM {$links} t
             INNER JOIN {$learners} l ON l.id = t.learner_id
             WHERE t.token_hash = %s AND l.status = 'active' LIMIT 1",
            self::hash_secret('link', $secret)
        ));
        if (!$row) {
            self::invalid_link_redirect();
        }
        $session = self::create_session($row->learner_id);
        if (is_wp_error($session)) {
            wp_die(esc_html($session->get_error_message()), '', array('response' => 500));
        }
        $wpdb->update($links, array('used_at' => gmdate('Y-m-d H:i:s')),
            array('id' => $row->id), array('%s'), array('%d'));
        wp_safe_redirect(MD_Learning_Schema::url('dashboard'), 303);
        exit;
    }

    private static function invalid_link_redirect() {
        wp_safe_redirect(add_query_arg('md_learning_error', 'invalid_link', MD_Learning_Schema::url('login')));
        exit;
    }

    /** Forget the current browser credentials without touching other sessions. */
    public static function forget_current_cookie() {
        self::$cached_id = 0;
        unset($_COOKIE[self::COOKIE]);
        setcookie(self::COOKIE, '', array(
            'expires' => time() - DAY_IN_SECONDS,
            'path' => '/',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ));
    }

    public static function logout() {
        check_admin_referer('md_learning_logout');
        $hash = self::current_session_hash();
        if ($hash) {
            global $wpdb;
            $wpdb->delete(MD_Learning_Schema::table('sessions'), array('token_hash' => $hash), array('%s'));
        }
        self::forget_current_cookie();
        wp_safe_redirect(MD_Learning_Schema::url('login'));
        exit;
    }
}
, 'index.php?md_learning_link=$matches[1]', 'top');
    }

    public static function link_query_var($vars) {
        $vars[] = 'md_learning_link';
        return $vars;
    }

    public static function hash_secret($purpose, $secret) {
        return hash_hmac('sha256', $purpose . ':' . (string) $secret, wp_salt('auth'));
    }

    public static function find_by_code($code) {
        $code = self::normalize_code($code);
        if (!preg_match('/^[0-9]{8}$/D', $code)) {
            return null;
        }
        global $wpdb;
        $table = MD_Learning_Schema::table('learners');
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE code_hash = %s AND status = 'active' LIMIT 1",
            self::hash_secret('code', $code)
        ));
        return $id ? self::get_learner(absint($id)) : null;
    }

    public static function normalize_code($code) {
        return strtr(trim((string) $code), array(
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        ));
    }

    public static function get_learner($id) {
        if (!$id) {
            return null;
        }
        global $wpdb;
        $table = MD_Learning_Schema::table('learners');
        return $wpdb->get_row($wpdb->prepare(
            "SELECT id, display_name, status, created_at, last_login_at FROM {$table} WHERE id = %d LIMIT 1",
            absint($id)
        ));
    }

    private static function generate_unused_code() {
        global $wpdb;
        $table = MD_Learning_Schema::table('learners');
        for ($i = 0; $i < 40; $i++) {
            $code = (string) random_int(10000000, 99999999);
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE code_hash = %s LIMIT 1",
                self::hash_secret('code', $code)
            ));
            if (!$exists) {
                return $code;
            }
        }
        return new WP_Error('code_unavailable', 'صدور کد ورود ممکن نشد. دوباره تلاش کنید.');
    }

    public static function create_learner($name) {
        $name = sanitize_text_field($name);
        if (!$name || strlen($name) > 600) {
            return new WP_Error('invalid_name', 'نام واردشده معتبر نیست.');
        }
        global $wpdb;
        $table = MD_Learning_Schema::table('learners');
        for ($i = 0; $i < 5; $i++) {
            $code = self::generate_unused_code();
            if (is_wp_error($code)) {
                return $code;
            }
            $now = gmdate('Y-m-d H:i:s');
            $created = $wpdb->insert($table, array(
                'display_name' => $name,
                'code_hash' => self::hash_secret('code', $code),
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ), array('%s','%s','%s','%s','%s'));
            // The UNIQUE code_hash index is the final collision safeguard.
            if (!$created) {
                continue;
            }
            $learner_id = (int) $wpdb->insert_id;
            $link = self::create_link($learner_id);
            if (is_wp_error($link)) {
                $wpdb->delete($table, array('id' => $learner_id), array('%d'));
                return $link;
            }
            return array('learner_id' => $learner_id, 'code' => $code, 'link' => $link);
        }
        return new WP_Error('create_failed', 'ساخت پروفایل ممکن نشد. دوباره تلاش کنید.');
    }

    public static function create_link($learner_id) {
        global $wpdb;
        $table = MD_Learning_Schema::table('links');
        $secret = bin2hex(random_bytes(32));
        $result = $wpdb->insert($table, array(
            'learner_id' => absint($learner_id),
            'token_hash' => self::hash_secret('link', $secret),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ), array('%d','%s','%s'));
        if (!$result) {
            return new WP_Error('link_failed', 'ساخت لینک خصوصی ممکن نشد.');
        }
        return add_query_arg('md_learning_link', rawurlencode($secret), home_url('/'));
    }

    public static function rotate_credentials($learner_id) {
        $learner = self::get_learner($learner_id);
        if (!$learner) {
            return new WP_Error('missing_learner', 'پروفایل پیدا نشد.');
        }
        $code = self::generate_unused_code();
        if (is_wp_error($code)) {
            return $code;
        }
        global $wpdb;
        $learners = MD_Learning_Schema::table('learners');
        $links = MD_Learning_Schema::table('links');
        $sessions = MD_Learning_Schema::table('sessions');
        $wpdb->query('START TRANSACTION');
        $changed = $wpdb->update($learners, array(
            'code_hash' => self::hash_secret('code', $code),
            'status' => 'active',
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ), array('id' => $learner_id), array('%s','%s','%s'), array('%d'));
        if ($changed === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('rotate_failed', 'صدور کد جدید ممکن نشد.');
        }
        $wpdb->delete($links, array('learner_id' => $learner_id), array('%d'));
        $wpdb->delete($sessions, array('learner_id' => $learner_id), array('%d'));
        $link = self::create_link($learner_id);
        if (is_wp_error($link)) {
            $wpdb->query('ROLLBACK');
            return $link;
        }
        $wpdb->query('COMMIT');
        self::$cached_id = null;
        return array('learner_id' => (int) $learner_id, 'code' => $code, 'link' => $link);
    }

    public static function revoke_access($learner_id) {
        global $wpdb;
        $learners = MD_Learning_Schema::table('learners');
        $wpdb->query('START TRANSACTION');
        $updated = $wpdb->update($learners,
            array('status' => 'revoked', 'updated_at' => gmdate('Y-m-d H:i:s')),
            array('id' => absint($learner_id)), array('%s','%s'), array('%d'));
        if ($updated === false) {
            $wpdb->query('ROLLBACK');
            return false;
        }
        $wpdb->delete(MD_Learning_Schema::table('links'), array('learner_id' => $learner_id), array('%d'));
        $wpdb->delete(MD_Learning_Schema::table('sessions'), array('learner_id' => $learner_id), array('%d'));
        $wpdb->query('COMMIT');
        self::$cached_id = null;
        return true;
    }

    private static function https_allowed() {
        return is_ssl() || (function_exists('wp_get_environment_type') && wp_get_environment_type() === 'local');
    }

    public static function create_session($learner_id) {
        if (!self::https_allowed()) {
            return new WP_Error('https_required', 'برای ورود ایمن، سایت باید از HTTPS استفاده کند.');
        }
        $learner = self::get_learner($learner_id);
        if (!$learner || $learner->status !== 'active') {
            return new WP_Error('invalid_learner', 'این حساب فعال نیست.');
        }
        if (headers_sent()) {
            return new WP_Error('headers_sent', 'ایجاد نشست ممکن نشد. خروجی صفحه زودتر ارسال شده است.');
        }
        global $wpdb;
        $token = bin2hex(random_bytes(32));
        $now = gmdate('Y-m-d H:i:s');
        $stored = $wpdb->insert(MD_Learning_Schema::table('sessions'), array(
            'learner_id' => absint($learner_id),
            'token_hash' => self::hash_secret('session', $token),
            'created_at' => $now,
            'last_seen_at' => $now,
            'expires_at' => gmdate('Y-m-d H:i:s', time() + self::MAX_SECONDS),
        ), array('%d','%s','%s','%s','%s'));
        if (!$stored) {
            return new WP_Error('session_failed', 'ایجاد نشست ورود ممکن نشد.');
        }
        if (!setcookie(self::COOKIE, $token, array(
            'expires' => time() + self::MAX_SECONDS,
            'path' => '/',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ))) {
            $wpdb->delete(MD_Learning_Schema::table('sessions'), array('token_hash' => self::hash_secret('session', $token)), array('%s'));
            return new WP_Error('cookie_failed', 'مرورگر نشست را دریافت نکرد.');
        }
        $wpdb->update(MD_Learning_Schema::table('learners'),
            array('last_login_at' => $now), array('id' => $learner_id), array('%s'), array('%d'));
        self::$cached_id = (int) $learner_id;
        // Flash receipts are short-lived and rendered once, never placed in a URL.
        return self::hash_secret('session', $token);
    }

    public static function nonce_learner_session($guest_id, $action) {
        if (!is_string($action) || !preg_match(
            '/^(?:md_learning_(?:profile_update|rotate_self|revoke_session|signout_others|logout|challenge_join_[0-9]+)|md_challenge_entry_[0-9]+_[0-9]+|md_learning_question_save_[0-9]+_[a-f0-9]{32}|md_learning_course_join_[0-9]+|md_learning_lesson_progress_[0-9]+_[0-9]+)$/D',
            $action
        )) {
            return $guest_id;
        }
        $hash = self::current_session_hash();
        if (!$hash || !self::current_learner_id()) {
            return $guest_id;
        }
        // The pseudonymous WordPress nonce user value is unique per central
        // session, without exposing or storing the raw bearer token.
        return 'md-learner-' . $hash;
    }

    public static function current_session_hash() {
        $token = isset($_COOKIE[self::COOKIE]) && is_string($_COOKIE[self::COOKIE]) ? $_COOKIE[self::COOKIE] : '';
        return preg_match('/^[a-f0-9]{64}$/D', $token) ? self::hash_secret('session', $token) : '';
    }

    public static function current_learner_id() {
        if (self::$cached_id !== null) {
            return self::$cached_id;
        }
        self::$cached_id = 0;
        $hash = self::current_session_hash();
        if (!$hash) {
            return 0;
        }
        global $wpdb;
        $sessions = MD_Learning_Schema::table('sessions');
        $learners = MD_Learning_Schema::table('learners');
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT s.id, s.learner_id, s.last_seen_at FROM {$sessions} s
             INNER JOIN {$learners} l ON l.id = s.learner_id
             WHERE s.token_hash = %s AND s.expires_at > %s
             AND s.last_seen_at > %s AND l.status = 'active' LIMIT 1",
            $hash, gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s', time() - self::IDLE_SECONDS)
        ));
        if (!$row) {
            return 0;
        }
        self::$cached_id = (int) $row->learner_id;
        if (strtotime($row->last_seen_at . ' UTC') < time() - 600) {
            $wpdb->update($sessions, array('last_seen_at' => gmdate('Y-m-d H:i:s')),
                array('id' => $row->id), array('%s'), array('%d'));
        }
        return self::$cached_id;
    }

    public static function store_receipt($session_hash, $credentials) {
        if ($session_hash) {
            set_transient('md_learning_receipt_' . $session_hash, $credentials, 5 * MINUTE_IN_SECONDS);
        }
    }

    public static function take_receipt() {
        $hash = self::current_session_hash();
        if (!$hash || !self::current_learner_id()) {
            return null;
        }
        $key = 'md_learning_receipt_' . $hash;
        $value = get_transient($key);
        if ($value) {
            delete_transient($key);
        }
        return is_array($value) ? $value : null;
    }

    public static function requested_challenge() {
        $raw = isset($_POST['md_challenge']) ? $_POST['md_challenge'] : (isset($_GET['md_challenge']) ? $_GET['md_challenge'] : 0);
        $id = absint($raw);
        return $id && get_post_type($id) === 'md_challenge' && get_post_status($id) === 'publish' ? $id : 0;
    }

    public static function requested_resource() {
        $raw = isset($_POST['md_resource']) ? $_POST['md_resource']
            : (isset($_GET['md_resource']) ? $_GET['md_resource'] : 0);
        $id = absint($raw);
        return $id && in_array(get_post_type($id), MD_Learning_Questions::TYPES, true)
            && get_post_status($id) === 'publish' ? $id : 0;
    }

    public static function requested_course() {
        $raw = isset($_POST['md_course']) ? $_POST['md_course']
            : (isset($_GET['md_course']) ? $_GET['md_course'] : 0);
        $id = absint($raw);
        return $id && get_post_type($id) === 'md_course'
            && get_post_status($id) === 'publish' ? $id : 0;
    }

    public static function register() {
        $name = isset($_POST['md_name']) && is_string($_POST['md_name']) ? sanitize_text_field(wp_unslash($_POST['md_name'])) : '';
        check_admin_referer('md_learning_register');
        if (!empty($_POST['md_website'])) {
            wp_safe_redirect(MD_Learning_Schema::url('register'));
            exit;
        }
        if (!self::https_allowed()) {
            wp_die('برای ثبت‌نام و دریافت کد خصوصی، ابتدا HTTPS سایت را فعال کنید.', '', array('response' => 403));
        }
        if (empty($_POST['md_consent'])) {
            wp_die('برای ثبت‌نام، موافقت با ذخیره نام و پاسخ‌ها لازم است.', '', array('response' => 400));
        }
        $challenge_id = self::requested_challenge();
        $resource_id = self::requested_resource();
        $course_id = self::requested_course();
        if (self::current_learner_id()) {
            $dest = $challenge_id ? get_permalink($challenge_id)
                : ($resource_id ? get_permalink($resource_id)
                : ($course_id ? get_permalink($course_id) : MD_Learning_Schema::url('dashboard')));
            wp_safe_redirect($dest);
            exit;
        }
        $created = self::create_learner($name);
        if (is_wp_error($created)) {
            wp_die(esc_html($created->get_error_message()), '', array('response' => 400));
        }
        $session = self::create_session($created['learner_id']);
        if (is_wp_error($session)) {
            wp_die(esc_html($session->get_error_message()), '', array('response' => 500));
        }
        self::store_receipt($session, $created);
        $destination = MD_Learning_Schema::url('dashboard');
        if ($challenge_id) {
            $destination = add_query_arg('md_challenge', $challenge_id, $destination);
        } elseif ($resource_id) {
            $destination = add_query_arg('md_resource', $resource_id, $destination);
        } elseif ($course_id) {
            $destination = add_query_arg('md_course', $course_id, $destination);
        }
        wp_safe_redirect($destination);
        exit;
    }

    public static function login() {
        check_admin_referer('md_learning_login');
        $challenge_id = self::requested_challenge();
        $resource_id = self::requested_resource();
        $course_id = self::requested_course();
        if (!self::https_allowed()) {
            wp_die('برای ورود، ابتدا HTTPS سایت را فعال کنید.', '', array('response' => 403));
        }
        $raw = isset($_POST['md_code']) && is_string($_POST['md_code']) ? wp_unslash($_POST['md_code']) : '';
        $learner = self::find_by_code($raw);
        if (!$learner) {
            // Intentionally no attempt counter and no account lockout.
            $dest = add_query_arg('md_learning_error', 'invalid', MD_Learning_Schema::url('login'));
            if ($challenge_id) {
                $dest = add_query_arg('md_challenge', $challenge_id, $dest);
            } elseif ($resource_id) {
                $dest = add_query_arg('md_resource', $resource_id, $dest);
            } elseif ($course_id) {
                $dest = add_query_arg('md_course', $course_id, $dest);
            }
            wp_safe_redirect($dest);
            exit;
        }
        $session = self::create_session($learner->id);
        if (is_wp_error($session)) {
            wp_die(esc_html($session->get_error_message()), '', array('response' => 500));
        }
        wp_safe_redirect($challenge_id ? get_permalink($challenge_id)
            : ($resource_id ? get_permalink($resource_id)
            : ($course_id ? get_permalink($course_id) : MD_Learning_Schema::url('dashboard'))));
        exit;
    }

    public static function exchange_link() {
        if (!isset($_GET['md_learning_link'])) {
            return;
        }
        nocache_headers();
        header('Referrer-Policy: no-referrer', true);
        header('X-Robots-Tag: noindex, nofollow, noarchive', true);
        if (!self::https_allowed()) {
            wp_die('ورود با لینک خصوصی فقط روی HTTPS فعال است.', '', array('response' => 403));
        }
        $secret = is_string($_GET['md_learning_link']) ? sanitize_text_field(wp_unslash($_GET['md_learning_link'])) : '';
        if (!preg_match('/^[a-f0-9]{64}$/D', $secret)) {
            self::invalid_link_redirect();
        }
        global $wpdb;
        $links = MD_Learning_Schema::table('links');
        $learners = MD_Learning_Schema::table('learners');
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT t.learner_id, t.id FROM {$links} t
             INNER JOIN {$learners} l ON l.id = t.learner_id
             WHERE t.token_hash = %s AND l.status = 'active' LIMIT 1",
            self::hash_secret('link', $secret)
        ));
        if (!$row) {
            self::invalid_link_redirect();
        }
        $session = self::create_session($row->learner_id);
        if (is_wp_error($session)) {
            wp_die(esc_html($session->get_error_message()), '', array('response' => 500));
        }
        $wpdb->update($links, array('used_at' => gmdate('Y-m-d H:i:s')),
            array('id' => $row->id), array('%s'), array('%d'));
        wp_safe_redirect(MD_Learning_Schema::url('dashboard'), 303);
        exit;
    }

    private static function invalid_link_redirect() {
        wp_safe_redirect(add_query_arg('md_learning_error', 'invalid_link', MD_Learning_Schema::url('login')));
        exit;
    }

    /** Forget the current browser credentials without touching other sessions. */
    public static function forget_current_cookie() {
        self::$cached_id = 0;
        unset($_COOKIE[self::COOKIE]);
        setcookie(self::COOKIE, '', array(
            'expires' => time() - DAY_IN_SECONDS,
            'path' => '/',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ));
    }

    public static function logout() {
        check_admin_referer('md_learning_logout');
        $hash = self::current_session_hash();
        if ($hash) {
            global $wpdb;
            $wpdb->delete(MD_Learning_Schema::table('sessions'), array('token_hash' => $hash), array('%s'));
        }
        self::forget_current_cookie();
        wp_safe_redirect(MD_Learning_Schema::url('login'));
        exit;
    }
}
