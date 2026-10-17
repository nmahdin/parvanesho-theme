<?php
/**
 * Phase E standalone smoke tests.
 * Checks role isolation, defensive filtering, parameterized reporting,
 * answer-detail permission, and private audit metadata.
 * Does not replace a staging WordPress/MySQL integration test.
 */
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');

class AccessDenied extends RuntimeException {}
$GLOBALS['can_view_responses'] = false;
$GLOBALS['rendered_dies'] = array();
function current_user_can($cap) {
    return $cap === 'md_view_learning_responses' && $GLOBALS['can_view_responses'];
}
function wp_die($message, $title = '', $options = array()) {
    $GLOBALS['rendered_dies'][] = array('text' => $message, 'status' => $options['response'] ?? 0);
    throw new AccessDenied((string) $message);
}
function absint($v) { return abs((int) $v); }
function wp_unslash($s) { return $s; }
function sanitize_key($s) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $s)); }
function sanitize_text_field($s) { return trim(strip_tags((string) $s)); }
function get_current_user_id() { return 51; }
function admin_url($path = '') { return '/wp-admin/' . $path; }
function add_query_arg($args, $url = '') { return $url . '?' . http_build_query($args); }
class MD_Learning_Schema {
    public static function table($name) { return 'wp_md_learning_' . $name; }
}
class ReportDb {
    public $posts = 'wp_posts';
    public $prepared = array();
    public $queries = array();
    public $inserts = array();
    public $fail_insert = false;
    public function esc_like($s) {
        return addcslashes($s, '_%\\');
    }
    public function prepare($sql, ...$values) {
        if (count($values) === 1 && is_array($values[0])) {
            $values = $values[0];
        }
        $this->prepared[] = array('sql' => $sql, 'values' => $values);
        return array('sql' => $sql, 'values' => $values);
    }
    public function get_row($query, $output = null) {
        $this->queries[] = $query;
        $sql = is_array($query) ? $query['sql'] : $query;
        if (strpos($sql, 'COUNT(r.id) AS total') !== false) {
            return array('total' => 37, 'drafts' => 14, 'finals' => 23, 'learners' => 6, 'content_items' => 3);
        }
        if (strpos($sql, 'r.answer_text') !== false) {
            return (object) array('id' => 9, 'learner_id' => 3, 'content_id' => 20, 'question_text' => 'پرسش خصوصی', 'answer_text' => 'پاسخ خصوصی');
        }
        return null;
    }
    public function get_results($query) {
        $this->queries[] = $query;
        return array((object) array('id' => 9, 'learner_id' => 3, 'content_id' => 20, 'status' => 'draft', 'question_text' => 'سؤال', 'display_name' => 'فرد آزمایشی'));
    }
    public function insert($table, $values, $format = null) {
        if ($this->fail_insert) { return false; }
        $this->inserts[] = array('table' => $table, 'values' => $values);
        return 1;
    }
}
$GLOBALS['wpdb'] = new ReportDb();
require dirname(__DIR__) . '/includes/class-md-learning-response-admin.php';

function verify($yes, $message) {
    if (!$yes) { throw new RuntimeException($message); }
}
$filters = MD_Learning_Response_Admin::filters(array(
    'md_status' => 'DELETE;',
    'md_learner' => '3',
    'md_content' => '20',
    'md_query' => '<b>آموزش</b> %_',
    'md_from' => '2026-02-30',
    'md_to' => '2026-10-17',
));
verify($filters['md_status'] === '', 'invalid status must be ignored');
verify($filters['md_learner'] === 3 && $filters['md_content'] === 20, 'numeric filters lost');
verify($filters['md_from'] === '' && $filters['md_to'] === '2026-10-17', 'calendar date filters wrong');
verify(strpos($filters['md_query'], '<') === false, 'markup not stripped from search text');

$queries_before = count($wpdb->queries);
try {
    MD_Learning_Response_Admin::report($filters);
    throw new RuntimeException('Unauthorized reporting succeeded');
} catch (AccessDenied $e) {}
try {
    MD_Learning_Response_Admin::detail(9);
    throw new RuntimeException('Unauthorized detail lookup succeeded');
} catch (AccessDenied $e) {}
verify(count($wpdb->queries) === $queries_before, 'unauthorized user triggered SQL query');
verify(count($GLOBALS['rendered_dies']) === 2 && $GLOBALS['rendered_dies'][0]['status'] === 403,
    'missing explicit unauthorized response');

$GLOBALS['can_view_responses'] = true;
$filters['md_status'] = 'draft';
$report = MD_Learning_Response_Admin::report($filters, 2);
verify($report['summary']['total'] === 37 && $report['summary']['drafts'] === 14, 'summary mismatch');
verify($report['pages'] === 2 && $report['page'] === 2, 'pagination mismatch');
verify(count($report['rows']) === 1, 'report list did not return rows');

$summary_prepared = $wpdb->prepared[count($wpdb->prepared) - 2];
$list_prepared = $wpdb->prepared[count($wpdb->prepared) - 1];
verify(strpos($summary_prepared['sql'], 'r.manager_visible = 1') !== false, 'hidden answers counted in report');
verify(strpos($summary_prepared['sql'], 'r.status = %s') !== false, 'status not parameterized');
verify(strpos($summary_prepared['sql'], 'r.learner_id = %d') !== false, 'learner not parameterized');
verify(strpos($summary_prepared['sql'], 'r.content_id = %d') !== false, 'content not parameterized');
verify(strpos($summary_prepared['sql'], 'r.updated_at < %s') !== false, 'end date is not exclusive');
verify(strpos($summary_prepared['sql'], 'answer_text LIKE') === false, 'private answer text included in search');
verify(strpos($list_prepared['sql'], 'r.answer_text') === false, 'plaintext answer sent to admin list');
verify(!in_array($filters['md_query'], array($summary_prepared['sql'], $list_prepared['sql']), true),
    'user search text interpolated as SQL');
verify($list_prepared['values'][count($list_prepared['values']) - 1] === 25,
    'second-page SQL offset wrong');

$detail = MD_Learning_Response_Admin::detail(9);
$detail_sql = $wpdb->prepared[count($wpdb->prepared) - 1]['sql'];
verify(strpos($detail_sql, 'r.manager_visible = 1') !== false, 'hidden answer accessible through direct ID');
verify($detail && $detail->answer_text === 'پاسخ خصوصی', 'authorized detail missing answer');

$method = new ReflectionMethod(MD_Learning_Response_Admin::class, 'record_view');
$method->setAccessible(true);
$method->invoke(null, $detail);
$logged = $wpdb->inserts[0];
verify($logged['table'] === 'wp_md_learning_audit_events', 'audit inserted into wrong table');
verify($logged['values']['actor_user_id'] === 51 && $logged['values']['response_id'] === 9,
    'audit actor or response ID wrong');
verify(!isset($logged['values']['answer_text']), 'private answer leaked into audit event');

$GLOBALS['can_view_responses'] = false;
$method->invoke(null, $detail);
verify(count($wpdb->inserts) === 1, 'unauthorized view unexpectedly wrote an audit record');

// Private answers must fail closed if access logging is unavailable.
$GLOBALS['can_view_responses'] = true;
$wpdb->fail_insert = true;
$_GET = array('page' => 'md-learning-answers', 'md_answer' => '9');
$denied = false;
ob_start();
try {
    MD_Learning_Response_Admin::render();
} catch (AccessDenied $e) {
    $denied = true;
}
ob_end_clean();
verify($denied && end($GLOBALS['rendered_dies'])['status'] === 503,
    'private detail rendered without a successful access audit');


echo "Phase E reporting authorization, filters, pagination and auditing: PASS\n";
