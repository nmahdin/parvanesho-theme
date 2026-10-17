<?php
/**
 * Enrollment regression after challenge redesign:
 * successful post/redirect, invalid/nonexistent form, guest redirect and
 * a rendering check that consent and submit are separate real controls.
 */
require __DIR__ . '/phase-b-smoke.php';
class RedirectCaptured extends RuntimeException {}
function wp_safe_redirect($url, $status=302) { throw new RedirectCaptured($url . '|HTTP' . $status); }
function add_query_arg($args, $value, $url = null) {
    if (is_array($args)) {
        $url = $value;
        $query = $args;
    } else {
        $query = array($args => $value);
    }
    return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);
}
function wp_verify_nonce($nonce, $action) { return $nonce === 'valid-' . $action ? 1 : false; }
function sanitize_text_field($v) { return trim(strip_tags($v)); }
function sanitize_key($v) { return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$v)); }
function wp_unslash($v) { return $v; }
function admin_url($p) { return '/wp-admin/' . $p; }
function wp_nonce_field($action) { echo '<input type="hidden" name="_wpnonce" value="valid-' . $action . '">'; }
class MD_Learning_Schema {
    public static function url($name) { return '/learning-' . $name . '/'; }
}
function capture_join($post, $user) {
    $_POST = $post;
    MD_Learning_Auth::$current = $user;
    try { MD_Learning_Challenges::join(); }
    catch (RedirectCaptured $r) { return $r->getMessage(); }
    throw new RuntimeException('join must end with server redirect');
}
$good = array(
    'challenge_id' => 100,
    '_wpnonce' => 'valid-md_learning_challenge_join_100',
    'md_join_consent' => '1',
);
$before = count($GLOBALS['wpdb']->rows);
$out = capture_join($good, 1);
check(strpos($out,'md_joined=1') !== false && strpos($out,'HTTP303') !== false, 'successful join redirect failed');
check(count($GLOBALS['wpdb']->rows) === $before, 'rejoining added a duplicate membership');
$out = capture_join($good, 0);
check(strpos($out,'md_join_error=login') !== false,'guest did not see login-needed result');
$bad = $good;
$bad['_wpnonce'] = 'bad';
$out = capture_join($bad, 1);
check(strpos($out,'md_join_error=expired') !== false,'expired nonce not clearly handled');
$bad = $good;
unset($bad['md_join_consent']);
$out = capture_join($bad, 1);
check(strpos($out,'md_join_error=consent') !== false,'missing consent not handled');
$bad = $good;
$bad['challenge_id'] = 999999;
$out = capture_join($bad, 1);
check(strpos($out,'md_join_error=expired') !== false,'forged invalid challenge action not denied');

MD_Learning_Auth::$current = 1;
ob_start();
MD_Learning_Challenges::render_join(100);
$already_member_form = ob_get_clean();
check($already_member_form === '', 'member should never see the join form again');
check(MD_Learning_Challenges::current_member(100) !== null, 'existing challenge membership not recognized');

MD_Learning_Auth::$current = 1;
ob_start();
$GLOBALS['posts'][102] = (object) array('ID'=>102,'post_type'=>'md_challenge','post_status'=>'publish','post_title'=>'چالش تازه');
MD_Learning_Challenges::render_join(102);
$html = ob_get_clean();
check(strpos($html, 'action="md_learning_challenge_join"') === false,
    'hidden action should be form input, not form attribute');
check(strpos($html, 'name="action" value="md_learning_challenge_join"') !== false,
    'central challenge action missing in form');
check(strpos($html,'name="md_join_consent"') !== false,'consent input missing');
check(strpos($html,'type="submit"') !== false,'real submit button missing');
check(strpos($html,'md-learning-challenge-join-form') !== false,'responsive join form class missing');
echo "Challenge join redirect and accessible form smoke tests: PASS\n";
