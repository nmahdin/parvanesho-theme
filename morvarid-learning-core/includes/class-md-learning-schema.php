<?php
if (!defined('ABSPATH')) {
    exit;
}

final class MD_Learning_Schema {
    const VERSION = '0.1.0';
    const VERSION_OPTION = 'md_learning_core_schema_version';
    const PAGE_OPTION = 'md_learning_core_pages';

    public static function table($name) {
        global $wpdb;
        $allowed = array('learners', 'links', 'sessions');
        if (!in_array($name, $allowed, true)) {
            return '';
        }
        return $wpdb->prefix . 'md_learning_' . $name;
    }

    public static function activate() {
        self::install_tables();
        self::create_pages();
        update_option(self::VERSION_OPTION, self::VERSION, false);
    }

    public static function maybe_upgrade() {
        if (!current_user_can('manage_options') || get_option(self::VERSION_OPTION) === self::VERSION) {
            return;
        }
        self::activate();
    }

    public static function install_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $learners = self::table('learners');
        $links = self::table('links');
        $sessions = self::table('sessions');

        dbDelta("CREATE TABLE {$learners} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            display_name varchar(190) NOT NULL,
            code_hash char(64) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            last_login_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code_hash (code_hash),
            KEY status (status)
        ) {$charset};");

        dbDelta("CREATE TABLE {$links} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            learner_id bigint(20) unsigned NOT NULL,
            token_hash char(64) NOT NULL,
            created_at datetime NOT NULL,
            used_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY token_hash (token_hash),
            KEY learner_id (learner_id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$sessions} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            learner_id bigint(20) unsigned NOT NULL,
            token_hash char(64) NOT NULL,
            created_at datetime NOT NULL,
            last_seen_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY token_hash (token_hash),
            KEY learner_id (learner_id),
            KEY expires_at (expires_at)
        ) {$charset};");
    }

    public static function create_pages() {
        $definitions = array(
            'register' => array('ثبت‌نام یادگیرنده', 'learning-register', '[md_learning_register]'),
            'login' => array('ورود به آکادمی', 'learning-login', '[md_learning_login]'),
            'dashboard' => array('داشبورد یادگیری من', 'learning-dashboard', '[md_learning_dashboard]'),
        );
        $stored = get_option(self::PAGE_OPTION, array());
        if (!is_array($stored)) {
            $stored = array();
        }

        foreach ($definitions as $key => $data) {
            $id = isset($stored[$key]) ? absint($stored[$key]) : 0;
            if ($id && get_post_status($id) && get_post_type($id) === 'page') {
                continue;
            }
            $existing = get_page_by_path($data[1], OBJECT, 'page');
            if ($existing) {
                // Never overwrite an unrelated page just to install shortcodes.
                if (strpos($existing->post_content, $data[2]) !== false) {
                    $stored[$key] = (int) $existing->ID;
                }
                continue;
            }
            $id = wp_insert_post(array(
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => $data[0],
                'post_name' => $data[1],
                'post_content' => $data[2],
                'comment_status' => 'closed',
            ), true);
            if (!is_wp_error($id) && $id) {
                $stored[$key] = (int) $id;
            }
        }
        update_option(self::PAGE_OPTION, $stored, false);
    }

    public static function page_ids() {
        $pages = get_option(self::PAGE_OPTION, array());
        return array_values(array_filter(array_map('absint', is_array($pages) ? $pages : array())));
    }

    public static function url($key) {
        $pages = get_option(self::PAGE_OPTION, array());
        $page_id = is_array($pages) && isset($pages[$key]) ? absint($pages[$key]) : 0;
        if ($page_id && get_post_status($page_id) === 'publish') {
            return get_permalink($page_id);
        }
        $slugs = array('register' => 'learning-register', 'login' => 'learning-login', 'dashboard' => 'learning-dashboard');
        return home_url('/' . (isset($slugs[$key]) ? $slugs[$key] : 'learning-login') . '/');
    }
}
