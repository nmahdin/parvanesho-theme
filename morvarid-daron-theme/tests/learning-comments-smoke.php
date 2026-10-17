<?php
/** No-email comment identity security smoke tests. */
define('ABSPATH', __DIR__ . '/');
$GLOBALS['filters'] = array();
$GLOBALS['actions'] = array();
$GLOBALS['comment_meta'] = array();
$GLOBALS['learner_id'] = 17;
$GLOBALS['wp_user_logged'] = false;
function add_filter($name,$fn,$priority=10,$accepted_args=1){$GLOBALS['filters'][$name][]=$fn;}
function add_action($name,$fn,$priority=10,$accepted_args=1){$GLOBALS['actions'][$name][]=$fn;}
function is_user_logged_in(){return $GLOBALS['wp_user_logged'];}
function sanitize_text_field($v){return trim(strip_tags($v));}
function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function absint($v){return abs((int)$v);}
function add_comment_meta($id,$key,$value,$unique){$GLOBALS['comment_meta'][$id][$key]=$value;}
function wp_die($msg,$title='',$args=array()){throw new RuntimeException($msg);}
class MD_Learning_Auth {
    public static function current_learner_id(){return $GLOBALS['learner_id'];}
    public static function get_learner($id){return $id===17?(object) array('id'=>17,'display_name'=>'کاربر آزمون','status'=>'active'):null;}
}
require dirname(__DIR__) . '/inc/comments-learning.php';
function check_case($condition,$message){if(!$condition)throw new RuntimeException($message);}
$learner=md_comment_learner();
check_case($learner && $learner->id===17,'learner session was not recognized');
check_case(md_comment_authenticated(),'authenticated learner blocked');
check_case($GLOBALS['filters']['pre_option_require_name_email'][0](false)==='0','email requirement not disabled for learner');
check_case($GLOBALS['filters']['pre_option_comment_registration'][0](false)==='0','WordPress-only login restriction not bypassed for learner');
$defaults=$GLOBALS['filters']['comment_form_defaults'][0](array());
check_case($defaults['fields']===array(),'learner identity inputs not removed');
$bad=array('comment_author'=>'Fake Name','comment_author_email'=>'impersonated@example.com','comment_author_url'=>'https://example.org');
$clean=$GLOBALS['filters']['preprocess_comment'][0]($bad);
check_case($clean['comment_author']==='کاربر آزمون' && $clean['comment_author_email']==='' && $clean['comment_author_url']==='' && $clean['comment_approved']===0,'comment impersonation was not blocked');
$GLOBALS['actions']['comment_post'][0](99);
check_case($GLOBALS['comment_meta'][99]['_md_learner_id']===17,'comment not attributed to authenticated learner');
$GLOBALS['learner_id']=0;
check_case(!md_comment_authenticated(),'anonymous guest was allowed');
try{$GLOBALS['actions']['pre_comment_on_post'][0](44);throw new RuntimeException('anonymous comment bypassed authentication');}
catch(RuntimeException $e){check_case(strpos($e->getMessage(),'وارد')!==false,'unexpected guest denial');}
$GLOBALS['wp_user_logged']=true;
check_case(md_comment_authenticated(),'real WordPress user blocked');
echo "Learner comments without email, anti-impersonation and guest rejection: PASS\n";
