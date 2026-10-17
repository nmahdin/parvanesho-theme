<?php
/**
 * Passwordless learner identity for native WordPress comments.
 * Never trust a submitted author name or an email field as identification.
 *
 * @package Morvarid_Daron
 */
if (!defined('ABSPATH')) { exit; }

function md_comment_learner() {
    if (!class_exists('MD_Learning_Auth')) return null;
    $id = MD_Learning_Auth::current_learner_id();
    if (!$id) return null;
    $learner = MD_Learning_Auth::get_learner($id);
    return $learner && $learner->status === 'active' ? $learner : null;
}
function md_comment_authenticated() {
    return is_user_logged_in() || (bool) md_comment_learner();
}
/** WordPress settings must not require an email for a learner identity. */
add_filter('pre_option_require_name_email', function ($pre) {
    return md_comment_learner() ? '0' : $pre;
});
add_filter('pre_option_comment_registration', function ($pre) {
    return md_comment_learner() ? '0' : $pre;
});
add_filter('comment_form_defaults', function ($defaults) {
    $learner = md_comment_learner();
    if ($learner && !is_user_logged_in()) {
        $defaults['fields'] = array();
        $defaults['logged_in_as'] = '<p class="md-comment-identity">ارسال دیدگاه با پروفایل: <strong>' . esc_html($learner->display_name) . '</strong></p>';
        $defaults['comment_notes_before'] = '<p class="md-comment-notes">برای ثبت دیدگاه ایمیل لازم نیست. دیدگاه پس از تأیید مدیر نمایش داده می‌شود.</p>';
    }
    return $defaults;
});
add_action('pre_comment_on_post', function ($post_id) {
    if (!md_comment_authenticated()) {
        wp_die('برای ارسال دیدگاه ابتدا وارد حساب یادگیری یا حساب وردپرس شوید.', '', array('response' => 403));
    }
});
add_filter('preprocess_comment', function ($comment) {
    $learner = md_comment_learner();
    if (!$learner || is_user_logged_in()) return $comment;
    // Use the session identity only; do not accept impersonated POST fields.
    $comment['user_ID'] = 0;
    $comment['comment_author'] = sanitize_text_field($learner->display_name);
    $comment['comment_author_email'] = '';
    $comment['comment_author_url'] = '';
    $comment['comment_approved'] = 0;
    return $comment;
}, 20);
add_action('comment_post', function ($comment_id) {
    $learner = md_comment_learner();
    if ($learner && !is_user_logged_in()) {
        add_comment_meta($comment_id, '_md_learner_id', absint($learner->id), true);
    }
});
