<?php
/**
 * WordPress-independent check that sensitive nonces are bound to the
 * actual learner session rather than a universal WordPress guest user.
 */
define('ABSPATH', __DIR__ . '/');
function wp_salt($scheme = 'auth') { return 'test-only-wordpress-auth-salt'; }
class MD_Learning_Schema {
    public static function table($type) { return 'wp_md_learning_' . $type; }
}
class MemoryAuthDb {
    public function prepare($sql, ...$values) { return array('sql' => $sql, 'args' => $values); }
    public function get_row($query) {
        return (object) array(
            'id' => 10, 'learner_id' => 101,
            'last_seen_at' => gmdate('Y-m-d H:i:s')
        );
    }
    public function update($table, $data, $where, $format, $where_format) { return 1; }
}
$GLOBALS['wpdb'] = new MemoryAuthDb();
require_once dirname(__DIR__) . '/includes/class-md-learning-auth.php';
function check_nonce($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}
$_COOKIE[MD_Learning_Auth::COOKIE] = str_repeat('a', 64);
$one = MD_Learning_Auth::nonce_learner_session(0, 'md_learning_profile_update');
check_nonce(is_string($one) && strpos($one, 'md-learner-') === 0, 'profile nonce not learner-scoped');
check_nonce($one === MD_Learning_Auth::nonce_learner_session(0, 'md_learning_signout_others'), 'same session must use same pseudonymous ID');
check_nonce($one === MD_Learning_Auth::nonce_learner_session(0, 'md_learning_challenge_join_125'), 'challenge join not scoped');
check_nonce($one === MD_Learning_Auth::nonce_learner_session(0, 'md_challenge_entry_200_300'), 'answer save not scoped');
check_nonce(MD_Learning_Auth::nonce_learner_session(0, 'md_learning_register') === 0, 'public registration nonce changed');
check_nonce(MD_Learning_Auth::nonce_learner_session(0, 'other_wordpress_form') === 0, 'unrelated WordPress nonce changed');
check_nonce(MD_Learning_Auth::nonce_learner_session(998, 'md_learning_profile_update') !== 0, 'test setup invalid');
$_COOKIE[MD_Learning_Auth::COOKIE] = str_repeat('b', 64);
$two = MD_Learning_Auth::nonce_learner_session(0, 'md_learning_profile_update');
check_nonce($two !== $one, 'different session reused a nonce identity');
unset($_COOKIE[MD_Learning_Auth::COOKIE]);
check_nonce(MD_Learning_Auth::nonce_learner_session(0, 'md_learning_profile_update') === 0, 'guest without session got a learner nonce');
echo "Phase C session-bound nonce smoke tests: PASS\n";
