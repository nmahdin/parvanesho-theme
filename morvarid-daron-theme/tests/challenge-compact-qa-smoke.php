<?php
/**
 * Standalone front-end regression for numbered inline Q&A and multi-item
 * challenge tips/tasks. Native day details are checked from source shape.
 */
define('ABSPATH', __DIR__ . '/');
function add_action() {}
function add_shortcode() {}
function sanitize_textarea_field($v) { return trim(strip_tags((string) $v)); }
function sanitize_text_field($v) { return trim(strip_tags((string) $v)); }
function esc_html($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function wpautop($s) { return '<p>' . $s . '</p>'; }
function md_fa_num($v) { return strtr((string)$v, '0123456789','۰۱۲۳۴۵۶۷۸۹'); }
function md_challenge_day_qa($day_id) {
    return array(
        array('q' => 'سؤال اول بدون جواب', 'a' => ''),
        array('q' => 'سؤال دوم', 'a' => 'این پاسخ باید مستقیم نمایش داده شود'),
        array('q' => 'سؤال سوم', 'a' => '   '),
    );
}
function md_challenge_day_items($id, $kind) {
    if ($kind === 'tips') {
        return array(
            array('title' => 'نکته الف', 'text' => 'متن اول'),
            array('title' => 'نکته ب', 'text' => 'متن دوم')
        );
    }
    if ($kind === 'tasks') {
        return array(
            array('title' => 'کار الف', 'description' => 'شرح کار', 'steps' => 'گام اول'),
            array('title' => 'کار ب', 'description' => 'شرح دوم', 'steps' => 'گام دوم')
        );
    }
    return array();
}
require dirname(__DIR__) . '/inc/challenges/public.php';
function verify($truth, $reason) { if (!$truth) { throw new RuntimeException($reason); } }
$day = (object) array('ID' => 101);
$qa = md_challenge_day_qa_html($day);
verify(substr_count($qa, 'class="md-qa-item"') === 3, 'all questions must appear');
verify(strpos($qa,'پرسش ۱') !== false && strpos($qa,'پرسش ۲') !== false && strpos($qa,'پرسش ۳') !== false,
    'question numbering missing');
verify(substr_count($qa,'class="md-qa-answer"') === 1, 'blank answers created empty panels');
verify(strpos($qa,'این پاسخ باید مستقیم نمایش داده شود') !== false, 'provided answer hidden');
verify(strpos($qa,'<details') === false && strpos($qa,'<summary') === false,
    'question itself must not be an accordion');
$tips = md_challenge_day_tip_html($day);
$tasks = md_challenge_day_exercise_html($day);
verify(strpos($tips,'نکته ۱') !== false && strpos($tips,'نکته ۲') !== false,
    'tip numbering not sequential');
verify(strpos($tasks,'کار ۱') !== false && strpos($tasks,'کار ۲') !== false,
    'task numbering not sequential');
$public = file_get_contents(dirname(__DIR__) . '/inc/challenges/public.php');
$member = file_get_contents(dirname(__DIR__) . '/inc/challenges/members.php');
verify(strpos($public,'<details id="day-') !== false && strpos($member,'<details id="member-day-') !== false,
    'challenge days must use native collapsible details');
verify(strpos($member,'md_challenge_day_qa_html($day)') !== false,
    'participant day does not show authored Q&A');
echo "Compact challenge days and numbered inline questions: PASS\n";
