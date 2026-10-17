<?php
/**
 * UX release gate: shorter private links must retain >=128 bits of entropy,
 * remain hashed at rest and be captured by the WordPress rewrite rule.
 */
define('ABSPATH', __DIR__ . '/');
class MD_Learning_Schema {
    public static function table($name) { return 'wp_md_learning_' . $name; }
}
class LinkTestDb {
    public $inserted = array();
    public function insert($table, $values, $formats = null) {
        $this->inserted[] = array('table'=>$table, 'values'=>$values);
        return 1;
    }
}
$GLOBALS['wpdb'] = new LinkTestDb();
$GLOBALS['rewrite'] = array();
function wp_salt($type) { return 'unit-test-secret'; }
function absint($v) { return abs((int) $v); }
function home_url($path = '/') { return 'https://example.test' . $path; }
function add_rewrite_rule($regex, $query, $priority) {
    $GLOBALS['rewrite'] = compact('regex','query','priority');
}
require dirname(__DIR__) . '/includes/class-md-learning-auth.php';
function check($truth, $why) { if (!$truth) { throw new RuntimeException($why); } }

$first = MD_Learning_Auth::create_link(23);
$second = MD_Learning_Auth::create_link(23);
check($first !== $second, 'private links must differ for same learner');
check(strpos($first, 'https://example.test/l/') === 0, 'short link path missing');
check(strpos($first, '?') === false, 'short link still contains query token');
$path = parse_url($first, PHP_URL_PATH);
check((bool)preg_match('~^/l/([A-Za-z0-9_-]{22})/$~', $path, $match),
    'short token not URL-safe base64 with 22 characters');
$raw = base64_decode(strtr($match[1], '-_', '+/') . '==', true);
check($raw !== false && strlen($raw) === 16, 'short link has less than 128 bits of randomness');
check($wpdb->inserted[0]['values']['token_hash'] === MD_Learning_Auth::hash_secret('link', $match[1]),
    'link token not hashed in database');
check(!isset($wpdb->inserted[0]['values']['token']), 'raw token persisted');
MD_Learning_Auth::register_link_route();
check($GLOBALS['rewrite']['query'] === 'index.php?md_learning_link=$matches[1]',
    'WordPress rewrite route invalid');
check(in_array('md_learning_link', MD_Learning_Auth::link_query_var(array()), true),
    'link query variable not registered');
echo "Short private links and HMAC smoke tests: PASS\n";
