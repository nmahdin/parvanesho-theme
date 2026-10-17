<?php
/**
 * Pure PHP Phase C smoke tests for privacy-safe workspace reads.
 * No WordPress/MySQL installation is simulated beyond in-memory stubs.
 */
require __DIR__ . '/phase-b-smoke.php';
require_once dirname(__DIR__) . '/includes/class-md-learning-dashboard.php';

function sanitize_key($v) { return preg_replace('/[^a-z0-9_-]/', '', strtolower($v)); }
function wp_unslash($v) { return $v; }
function number_format_i18n($n) { return number_format((float) $n, 0, '.', ','); }
function wp_update_post($args) {
    $id = absint($args['ID']);
    if (!isset($GLOBALS['posts'][$id])) { return 0; }
    $GLOBALS['posts'][$id]->post_title = (string) $args['post_title'];
    return $id;
}

MD_Learning_Auth::$current = 1;
$GLOBALS['meta'][300]['_md_answer_updated'] = '2026-10-17 13:45:00';
$GLOBALS['meta'][300]['_md_member_notes'] = 'یک پاسخ شخصی';
$GLOBALS['meta'][300]['_md_answers'] = array('پاسخ محرمانه');
$_GET['md_tab'] = 'profile';
check(MD_Learning_Dashboard::selected_tab() === 'profile', 'profile tab not selectable');
$_GET['md_tab'] = 'security';
check(MD_Learning_Dashboard::selected_tab() === 'security', 'security tab not selectable');
$_GET['md_tab'] = 'arbitrary_malicious_name';
check(MD_Learning_Dashboard::selected_tab() === 'home', 'unknown tab must fall back to home');

$data = MD_Learning_Dashboard::challenge_data(1);
check($data['active'] === 2, 'correct learner challenge count missing');
check($data['completed'] === 1 && $data['total_days'] === 3, 'challenge completion stats incorrect');
check(count($data['activity']) === 1, 'recent activity not shown');
check($data['activity'][0]['day_id'] === 200, 'activity points to wrong day');
check(!isset($data['activity'][0]['notes']) && !isset($data['activity'][0]['answers']),
    'dashboard summaries must not include answer text');

$data = MD_Learning_Dashboard::challenge_data(2);
check($data['active'] === 0 && !$data['activity'], 'arbitrary learner ID leaked activity');
MD_Learning_Auth::$current = 2;
$data = MD_Learning_Dashboard::challenge_data(2);
check($data['active'] === 1 && $data['completed'] === 0, 'other learner activity contaminated');
MD_Learning_Auth::$current = 0;
$data = MD_Learning_Dashboard::challenge_data(1);
check($data['active'] === 0, 'anonymous access to learning summary');
// A profile change may rename only the current learner's mapped memberships.
MD_Learning_Auth::$current = 1;
MD_Learning_Challenges::sync_member_titles(1, 'نام به‌روز شده');
check($GLOBALS['posts'][$m1->ID]->post_title === 'نام به‌روز شده', 'own member title not synchronized');
check($GLOBALS['posts'][$m2->ID]->post_title === 'نام به‌روز شده', 'second challenge title not synchronized');
check($GLOBALS['posts'][$m3->ID]->post_title !== 'نام به‌روز شده', 'another learner name changed');
MD_Learning_Auth::$current = 2;
MD_Learning_Challenges::sync_member_titles(1, 'نام غیرمجاز');
check($GLOBALS['posts'][$m1->ID]->post_title === 'نام به‌روز شده', 'cross-learner title manipulation');
echo "Phase C privacy and workspace smoke tests: PASS\n";
