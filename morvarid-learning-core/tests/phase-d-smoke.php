<?php
/**
 * Pure-PHP Phase D smoke tests for block identities, duplicate prevention,
 * server-side question definitions and private learner reads.
 */
require __DIR__ . '/phase-b-smoke.php';
class MD_Learning_Schema {
    public static function table($name) { return 'wp_md_learning_' . $name; }
}
function sanitize_textarea_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function wp_is_post_revision($id) { return false; }
function wp_is_post_autosave($id) { return false; }
function parse_blocks($content) { return json_decode((string) $content, true) ?: array(); }
function serialize_blocks($blocks) { return json_encode($blocks, JSON_UNESCAPED_SLASHES); }
function get_post_field($field, $id) { return $GLOBALS['posts'][$id]->post_content; }
function wp_update_post($updates, $wp_error = false) {
    $GLOBALS['posts'][$updates['ID']]->post_content = $updates['post_content'];
    return $updates['ID'];
}
require_once dirname(__DIR__) . '/includes/class-md-learning-questions.php';

$originalBlock = str_repeat('a', 32);
$originalQuestion = str_repeat('b', 32);
$attrs = array(
    'blockId' => $originalBlock, 'mode' => 'save',
    'questions' => array(
        array('id' => $originalQuestion, 'text' => 'پرسش اول', 'required' => true),
        array('id' => str_repeat('c', 32), 'text' => 'پرسش دوم', 'required' => false),
    ),
);
$blocks = array(
    array('blockName' => 'core/paragraph', 'attrs' => array(), 'innerBlocks' => array()),
    array('blockName' => MD_Learning_Questions::BLOCK, 'attrs' => $attrs, 'innerBlocks' => array()),
    array('blockName' => MD_Learning_Questions::BLOCK, 'attrs' => $attrs, 'innerBlocks' => array()),
);
$GLOBALS['posts'][500] = (object) array('ID' => 500, 'post_type' => 'md_resource',
    'post_status' => 'publish', 'post_content' => json_encode($blocks, JSON_UNESCAPED_SLASHES));
MD_Learning_Questions::normalize_post(500, $GLOBALS['posts'][500], true);
$fixed = parse_blocks($GLOBALS['posts'][500]->post_content);
check($fixed[1]['attrs']['blockId'] === $originalBlock, 'original block ID not retained');
check($fixed[1]['attrs']['questions'][0]['id'] === $originalQuestion, 'original question ID not retained');
check($fixed[2]['attrs']['blockId'] !== $originalBlock, 'duplicate block ID not repaired');
check($fixed[2]['attrs']['questions'][0]['id'] !== $originalQuestion, 'duplicate question ID not repaired');
check(MD_Learning_Questions::valid_uuid($fixed[2]['attrs']['blockId']), 'new block ID not UUID32');
$firstStable = $GLOBALS['posts'][500]->post_content;
MD_Learning_Questions::normalize_post(500, $GLOBALS['posts'][500], true);
check($GLOBALS['posts'][500]->post_content === $firstStable, 'saving without changes reset identities');

$matched = MD_Learning_Questions::published_block(500, $originalBlock);
check($matched['mode'] === 'save', 'valid persisted block lookup failed');
check(MD_Learning_Questions::published_block(500, str_repeat('z', 32)) === null,
    'forged invalid block ID accepted');
check(MD_Learning_Questions::published_block(500, str_repeat('d', 32)) === null,
    'nonexistent block accepted');

$clean = MD_Learning_Questions::clean_questions(array(
    array('id' => $originalQuestion, 'text' => '<script>x</script> معنی پاسخ چیست؟',
        'hint' => '<b>اختیاری</b>', 'type' => 'short', 'required' => true),
    array('id' => 'not-an-id', 'text' => 'Invalid'),
));
check(count($clean) === 1 && $clean[0]['required'], 'sanitization or required type not preserved');
check(strpos($clean[0]['text'], '<script>') === false, 'unsafe question markup retained');
check($clean[0]['type'] === 'short', 'short answer configuration lost');

// A caller cannot read even their own response using another learner ID.
MD_Learning_Auth::$current = 1;
check(MD_Learning_Questions::responses(2, 500, $originalBlock) === array(),
    'cross-learner response query was allowed');
MD_Learning_Auth::$current = 0;
check(MD_Learning_Questions::responses(1, 500, $originalBlock) === array(),
    'signed-out response query was allowed');

echo "Phase D question identity and access smoke tests: PASS\n";
