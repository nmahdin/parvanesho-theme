<?php
/**
 * Pure PHP Phase B behavioral smoke tests with a tiny WordPress-function stub.
 * This does not replace a live WordPress/MySQL integration test.
 */
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/');
class WP_Error {
    private $message;
    public function __construct($code, $message) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error($v) { return $v instanceof WP_Error; }
function absint($v) { return abs((int) $v); }
function post_type_exists($v) { return $v === 'md_challenge_member'; }
function get_post_type($id) { return isset($GLOBALS['posts'][$id]) ? $GLOBALS['posts'][$id]->post_type : ''; }
function get_post_status($id) { return isset($GLOBALS['posts'][$id]) ? $GLOBALS['posts'][$id]->post_status : ''; }
function get_post($id) { return isset($GLOBALS['posts'][$id]) ? $GLOBALS['posts'][$id] : null; }
function get_post_meta($id, $key) { return isset($GLOBALS['meta'][$id][$key]) ? $GLOBALS['meta'][$id][$key] : ''; }
function update_post_meta($id, $key, $value) { $GLOBALS['meta'][$id][$key] = $value; return true; }
function current_time($v) { return '2026-10-17 10:00:00'; }
function wp_insert_post($post, $wp_error = false) {
    $id = ++$GLOBALS['next_post'];
    $GLOBALS['posts'][$id] = (object) array_merge($post, array('ID' => $id));
    return $id;
}
function wp_delete_post($id, $force = false) { unset($GLOBALS['posts'][$id], $GLOBALS['meta'][$id]); }
function get_permalink($id) { return '/challenges/' . $id . '/'; }
function esc_url($v) { return $v; }
function esc_html($v) { return htmlspecialchars((string) $v, ENT_QUOTES); }
function esc_attr($v) { return htmlspecialchars((string) $v, ENT_QUOTES); }
function get_post_type_archive_link($type) { return '/challenges/'; }
function md_challenge_member_entries($member_id) { return isset($GLOBALS['entries'][$member_id]) ? $GLOBALS['entries'][$member_id] : array(); }
function md_challenge_days($challenge_id) { return isset($GLOBALS['days'][$challenge_id]) ? $GLOBALS['days'][$challenge_id] : array(); }
class MD_Learning_Auth {
    public static $current = 0;
    public static function current_learner_id() { return self::$current; }
    public static function get_learner($id) {
        return isset($GLOBALS['learners'][$id]) ? $GLOBALS['learners'][$id] : null;
    }
}
class DummyDb {
    public $prefix = 'wp_';
    public $rows = array();
    public function prepare($sql, ...$args) {
        return array('sql' => $sql, 'params' => $args);
    }
    public function get_var($query) {
        $p = $query['params'];
        foreach ($this->rows as $row) {
            if (strpos($query['sql'], 'SELECT member_id') !== false
                && $row->learner_id == $p[0] && $row->challenge_id == $p[1]) {
                return $row->member_id;
            }
            if (strpos($query['sql'], 'SELECT learner_id') !== false && $row->member_id == $p[0]) {
                return $row->learner_id;
            }
        }
        return null;
    }
    public function insert($table, $data, $formats = null) {
        foreach ($this->rows as $row) {
            if ($row->member_id == $data['member_id']
                || ($row->learner_id == $data['learner_id'] && $row->challenge_id == $data['challenge_id'])) {
                return false;
            }
        }
        $this->rows[] = (object) $data;
        return 1;
    }
    public function get_results($query) {
        $p = $query['params'];
        $results = array();
        foreach ($this->rows as $row) {
            if (strpos($query['sql'], 'WHERE learner_id') !== false && $row->learner_id == $p[0]
                || strpos($query['sql'], 'WHERE challenge_id') !== false && $row->challenge_id == $p[0]) {
                $results[] = $row;
            }
        }
        return $results;
    }
    public function get_col($query) {
        return array_map(function ($r) { return $r->member_id; }, $this->get_results($query));
    }
}
function check($ok, $message) {
    if (!$ok) { throw new RuntimeException($message); }
}

$GLOBALS['wpdb'] = new DummyDb();
$GLOBALS['posts'] = array(
    100 => (object) array('ID' => 100, 'post_type' => 'md_challenge', 'post_status' => 'publish', 'post_title' => 'چالش الف'),
    101 => (object) array('ID' => 101, 'post_type' => 'md_challenge', 'post_status' => 'publish', 'post_title' => 'چالش ب'),
);
$GLOBALS['meta'] = array();
$GLOBALS['next_post'] = 1000;
$GLOBALS['learners'] = array(
    1 => (object) array('id' => 1, 'display_name' => 'یادگیرنده اول', 'status' => 'active'),
    2 => (object) array('id' => 2, 'display_name' => 'یادگیرنده دوم', 'status' => 'active'),
);
$GLOBALS['days'] = array(
    100 => array((object) array('ID' => 200), (object) array('ID' => 201)),
    101 => array((object) array('ID' => 202)),
);
$GLOBALS['entries'] = array();

require_once dirname(__DIR__) . '/includes/class-md-learning-challenges.php';

// One central identity can join multiple challenges without duplicate records.
MD_Learning_Auth::$current = 1;
$m1 = MD_Learning_Challenges::enroll(100, 1);
check(!is_wp_error($m1), 'first join failed');
check(MD_Learning_Challenges::member_id(1, 100) === $m1->ID, 'membership not mapped');
check(MD_Learning_Challenges::owns_member(100, $m1->ID), 'owner rejected');
$again = MD_Learning_Challenges::enroll(100, 1);
check($again->ID === $m1->ID, 'rejoin created another membership');
check(count($wpdb->rows) === 1, 'rejoin duplicated row');
$m2 = MD_Learning_Challenges::enroll(101, 1);
check(!is_wp_error($m2) && count($wpdb->rows) === 2, 'second challenge failed');

// One learner cannot read a different learner's member even if given its ID.
MD_Learning_Auth::$current = 2;
check(!MD_Learning_Challenges::owns_member(100, $m1->ID), 'cross-learner member accepted');
$m3 = MD_Learning_Challenges::enroll(100, 2);
check(!is_wp_error($m3) && $m3->ID !== $m1->ID, 'other learner mapped incorrectly');
check(MD_Learning_Challenges::owns_member(100, $m3->ID), 'second owner denied');
check(!MD_Learning_Challenges::owns_member(100, $m1->ID), 'cross-learner member leaked');

// Revoking a challenge membership does not revoke the central account.
update_post_meta($m3->ID, '_md_member_revoked', 1);
check(MD_Learning_Challenges::current_member(100) === null, 'revocation ineffective');
check(is_wp_error(MD_Learning_Challenges::enroll(100, 2)), 'revoked challenge rejoined');
check(MD_Learning_Auth::get_learner(2)->status === 'active', 'revocation touched central account');
unset($GLOBALS['meta'][$m3->ID]['_md_member_revoked']);
check(MD_Learning_Challenges::current_member(100)->ID === $m3->ID, 'reactivation failed');

MD_Learning_Auth::$current = 1;
update_post_meta(300, '_md_task_done', 1);
$GLOBALS['entries'][$m1->ID] = array(200 => (object) array('ID' => 300));
$html = MD_Learning_Challenges::dashboard_html(1);
check(strpos($html, '50٪') !== false, 'completion percentage missing');
check(strpos($html, 'چالش الف') !== false && strpos($html, 'چالش ب') !== false, 'challenge tiles missing');
check(count(MD_Learning_Challenges::members_for_challenge(100)) === 2, 'admin query not scoped');
echo "Phase B smoke tests: PASS\n";
