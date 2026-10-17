<?php
/**
 * Plugin Name: Morvarid Learning Core
 * Plugin URI: https://morvarid-daron.ir/
 * Description: Independent, email-free learner identity, eight-digit access codes, private access links and secure sessions for Morvarid Daron Academy.
 * Version: 0.7.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Text Domain: morvarid-learning
 */
if (!defined('ABSPATH')) {
    exit;
}

define('MD_LEARNING_CORE_VERSION', '0.7.0');
define('MD_LEARNING_CORE_FILE', __FILE__);
define('MD_LEARNING_CORE_DIR', plugin_dir_path(__FILE__));
define('MD_LEARNING_CORE_URL', plugin_dir_url(__FILE__));

require_once MD_LEARNING_CORE_DIR . 'includes/class-md-learning-schema.php';
require_once MD_LEARNING_CORE_DIR . 'includes/class-md-learning-auth.php';
require_once MD_LEARNING_CORE_DIR . 'includes/class-md-learning-pages.php';
require_once MD_LEARNING_CORE_DIR . 'includes/class-md-learning-profile.php';
require_once MD_LEARNING_CORE_DIR . 'includes/class-md-learning-dashboard.php';
require_once MD_LEARNING_CORE_DIR . 'includes/class-md-learning-challenges.php';
require_once MD_LEARNING_CORE_DIR . 'includes/class-md-learning-questions.php';
require_once MD_LEARNING_CORE_DIR . 'includes/class-md-learning-admin.php';
require_once MD_LEARNING_CORE_DIR . 'includes/class-md-learning-response-admin.php';
require_once MD_LEARNING_CORE_DIR . 'includes/class-md-learning-courses.php';
require_once MD_LEARNING_CORE_DIR . 'includes/class-md-learning-course-admin.php';

register_activation_hook(__FILE__, array('MD_Learning_Schema', 'activate'));
add_action('admin_init', array('MD_Learning_Schema', 'maybe_upgrade'));

add_action('init', array('MD_Learning_Auth', 'register_link_route'), 9);
add_action('init', array('MD_Learning_Auth', 'init'));
add_action('init', array('MD_Learning_Pages', 'init'));
add_action('init', array('MD_Learning_Profile', 'init'));
add_action('init', array('MD_Learning_Questions', 'init'), 25);
add_action('init', array('MD_Learning_Challenges', 'init'));
add_action('init', array('MD_Learning_Admin', 'init'));
add_action('init', array('MD_Learning_Response_Admin', 'init'));
add_action('init', array('MD_Learning_Courses', 'init'));
add_action('init', array('MD_Learning_Course_Admin', 'init'));

add_action('wp_enqueue_scripts', function () {
    if (is_page(MD_Learning_Schema::page_ids())) {
        wp_enqueue_style('dashicons');
        wp_enqueue_style('md-learning-core', MD_LEARNING_CORE_URL . 'assets/learning.css', array('dashicons'), MD_LEARNING_CORE_VERSION);
    }
    if (is_page(MD_Learning_Schema::page_ids()) || is_singular('md_challenge')) {
        wp_enqueue_style('md-learning-core-forms', MD_LEARNING_CORE_URL . 'assets/learning.css', array(), MD_LEARNING_CORE_VERSION);
        wp_enqueue_script('md-learning-copy', MD_LEARNING_CORE_URL . 'assets/copy.js', array(), MD_LEARNING_CORE_VERSION, true);
    }
});

/**
 * Bridge API for challenge, course and educational block integrations.
 * Returns 0 for signed-out visitors. Never accept a client-supplied learner ID
 * as a substitute for this server-side identity check.
 */
function md_learning_current_learner_id() {
    return MD_Learning_Auth::current_learner_id();
}

function md_learning_is_authenticated() {
    return md_learning_current_learner_id() > 0;
}

/** Phase B bridge: returns the current learner's challenge member post or null. */
function md_learning_current_challenge_member($challenge_id) {
    return MD_Learning_Challenges::current_member(absint($challenge_id));
}

/**
 * Independent learner sessions are not WordPress logged-in cookies.
 * Never allow public/shared page caches to serve anonymous HTML to a learner.
 */
add_action('plugins_loaded', function () {
    if (!empty($_COOKIE[MD_Learning_Auth::COOKIE])) {
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        if (!defined('DONOTCACHEOBJECT')) define('DONOTCACHEOBJECT', true);
    }
}, 0);
add_action('template_redirect', function () {
    if (!empty($_COOKIE[MD_Learning_Auth::COOKIE])) {
        nocache_headers();
        header('Cache-Control: private, no-store, no-cache, max-age=0, must-revalidate', true);
        header('Vary: Cookie', false);
    }
}, 0);

/* Repair account routes once after upgrade when old page IDs or slugs are stale. */
add_action('admin_init', function () {
    if (!current_user_can('manage_options') || get_option('md_learning_account_routes_069')) return;
    MD_Learning_Schema::create_pages();
    update_option('md_learning_account_routes_069', 1, false);
}, 12);
