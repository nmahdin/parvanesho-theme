<?php
if (!defined('ABSPATH')) {
    exit;
}

final class MD_Learning_Schema {
    const VERSION = '0.6.1';
    const VERSION_OPTION = 'md_learning_core_schema_version';
    const PAGE_OPTION = 'md_learning_core_pages';

    public static function table($name) {
        global $wpdb;
        $allowed = array('learners', 'links', 'sessions', 'challenges', 'responses', 'audit_events', 'enrollments', 'lesson_progress');
        if (!in_array($name, $allowed, true)) {
            return '';
        }
        return $wpdb->prefix . 'md_learning_' . $name;
    }

    public static function activate() {
        self::install_tables();
        self::create_pages();
        self::grant_admin_capability();
        MD_Learning_Auth::register_link_route();
        flush_rewrite_rules(false);
        update_option(self::VERSION_OPTION, self::VERSION, false);
    }

    public static function grant_admin_capability() {
        $role = get_role('administrator');
        if ($role && !$role->has_cap('md_view_learning_responses')) {
            $role->add_cap('md_view_learning_responses');
        }
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
        $challenges = self::table('challenges');
        $responses = self::table('responses');
        $audit = self::table('audit_events');
        $enrollments = self::table('enrollments');
        $lesson_progress = self::table('lesson_progress');

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
        // Independent identity-to-challenge membership map. Existing answers
        // continue using private md_challenge_entry posts, no migration needed.
        dbDelta("CREATE TABLE {$challenges} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            learner_id bigint(20) unsigned NOT NULL,
            challenge_id bigint(20) unsigned NOT NULL,
            member_id bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY learner_challenge (learner_id,challenge_id),
            UNIQUE KEY member_id (member_id),
            KEY challenge_id (challenge_id)
        ) {$charset};");

        // One independently versioned answer per learner and stable question ID.
        // Do not store private learner answers in public post_content/meta.
        dbDelta("CREATE TABLE {$responses} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            learner_id bigint(20) unsigned NOT NULL,
            content_id bigint(20) unsigned NOT NULL,
            block_id varchar(64) NOT NULL,
            question_id varchar(64) NOT NULL,
            question_text text NOT NULL,
            answer_text longtext NOT NULL,
            status varchar(12) NOT NULL DEFAULT 'draft',
            manager_visible tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            submitted_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY learner_content_question (learner_id,content_id,question_id),
            KEY learner_content (learner_id,content_id),
            KEY content_block (content_id,block_id),
            KEY status (status)
        ) {$charset};");

        // Audit metadata: never store answers, access codes, or link tokens.
        dbDelta("CREATE TABLE {$audit} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            actor_user_id bigint(20) unsigned NOT NULL,
            learner_id bigint(20) unsigned NOT NULL,
            response_id bigint(20) unsigned NOT NULL,
            event_type varchar(40) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY actor_time (actor_user_id,created_at),
            KEY response_time (response_id,created_at),
            KEY learner_id (learner_id)
        ) {$charset};");

        // Learner-owned course enrollment, independent of WP users and billing.
        dbDelta("CREATE TABLE {$enrollments} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            learner_id bigint(20) unsigned NOT NULL,
            course_id bigint(20) unsigned NOT NULL,
            status varchar(12) NOT NULL DEFAULT 'active',
            enrolled_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY learner_course (learner_id,course_id),
            KEY course_status (course_id,status),
            KEY learner_status (learner_id,status)
        ) {$charset};");

        // A lesson is tracked within its real parent course. Reassignment to
        // another course does not carry progress across automatically.
        dbDelta("CREATE TABLE {$lesson_progress} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            learner_id bigint(20) unsigned NOT NULL,
            course_id bigint(20) unsigned NOT NULL,
            lesson_id bigint(20) unsigned NOT NULL,
            status varchar(16) NOT NULL DEFAULT 'incomplete',
            completed_at datetime DEFAULT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY learner_course_lesson (learner_id,course_id,lesson_id),
            KEY learner_course (learner_id,course_id),
            KEY course_lesson (course_id,lesson_id)
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
        $slugs = array('register' => 'learning-register', 'login' => 'learning-login', 'dashboard' => 'learning-dashboard');
        $shortcodes = array('register' => '[md_learning_register]', 'login' => '[md_learning_login]', 'dashboard' => '[md_learning_dashboard]');
        if (!isset($slugs[$key])) $key = 'login';
        $pages = get_option(self::PAGE_OPTION, array());
        $page_id = is_array($pages) && isset($pages[$key]) ? absint($pages[$key]) : 0;
        // A previous page ID can now point to an unrelated WordPress page.
        // Never direct a challenge visitor to a page without the target form.
        if ($page_id && get_post_type($page_id) === 'page' && get_post_status($page_id) === 'publish'
            && strpos((string) get_post_field('post_content', $page_id), $shortcodes[$key]) !== false) {
            return get_permalink($page_id);
        }
        $correct = get_page_by_path($slugs[$key], OBJECT, 'page');
        if ($correct && $correct->post_status === 'publish'
            && strpos((string) $correct->post_content, $shortcodes[$key]) !== false) {
            return get_permalink($correct->ID);
        }
        return home_url('/' . $slugs[$key] . '/');
    }
}
