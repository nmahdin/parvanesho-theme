<?php
/**
 * Phase E teacher privacy switch integration: Gutenberg editor updates must
 * change existing private rows' manager visibility without deleting answers.
 */
require __DIR__ . '/phase-d-smoke.php';

class PrivacySyncDb extends DummyDb {
    public $updates = array();
    public function update($table, $data, $where, $formats = null, $where_formats = null) {
        $this->updates[] = compact('table', 'data', 'where');
        return 1;
    }
}
$db = new PrivacySyncDb();
$GLOBALS['wpdb'] = $db;

$blocks = parse_blocks($GLOBALS['posts'][500]->post_content);
$blocks[1]['attrs']['managerVisible'] = false;
$GLOBALS['posts'][500]->post_content = serialize_blocks($blocks);
MD_Learning_Questions::normalize_post(500, $GLOBALS['posts'][500], true);
check(count($db->updates) === 2, 'expected manager settings for both question blocks');
check($db->updates[0]['data']['manager_visible'] === 0, 'hiding a block did not hide stored answers');
check($db->updates[0]['where']['block_id'] === $originalBlock, 'visibility changed wrong block');
check($db->updates[1]['data']['manager_visible'] === 1, 'unrelated block unexpectedly hidden');

$db->updates = array();
$blocks[1]['attrs']['managerVisible'] = true;
$GLOBALS['posts'][500]->post_content = serialize_blocks($blocks);
MD_Learning_Questions::normalize_post(500, $GLOBALS['posts'][500], true);
check($db->updates[0]['data']['manager_visible'] === 1, 're-enabled admin visibility not reflected');

echo "Phase E per-block manager visibility synchronization: PASS\n";
