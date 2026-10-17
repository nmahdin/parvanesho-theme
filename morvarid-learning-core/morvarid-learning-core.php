<?php
/**
 * Plugin Name: Morvarid Learning Core
 * Plugin URI: https://morvarid-daron.ir/
 * Description: Independent, email-free learner identity, eight-digit access codes, private access links and secure sessions for Morvarid Daron Academy.
 * Version: 0.4.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Text Domain: morvarid-learning
 */
if (!defined('ABSPATH')) {
    exit;
}

define('MD_LEARNING_CORE_VERSION', '0.4.0');
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

register_activation_hook(__FILE__, array('MD_Learning_Schema', 'activate'));
add_action('admin_init', array('MD_Learning_Schema', 'maybe_upgrade'));

add_action('init', array('MD_Learning_Auth', 'init'));
add_action('init', array('MD_Learning_Pages', 'init'));
add_action('init', array('MD_Learning_Profile', 'init'));
add_action('init', array('MD_Learning_Questions', 'init'), 25);
add_action('init', array('MD_Learning_Challenges', 'init'));
add_action('init', array('MD_Learning_Admin', 'init'));

add_action('wp_enqueue_scripts', function () {
    if (is_page(MD_Learning_Schema::page_ids())) {
        wp_enqueue_style('dashicons');
        wp_enqueue_style('md-learning-core', MD_LEARNING_CORE_URL . 'assets/learning.css', array('dashicons'), MD_LEARNING_CORE_VERSION);
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
