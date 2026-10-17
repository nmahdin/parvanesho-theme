<?php
/**
 * Smoke test for fully editable / non-destructive magazine, academy and
 * challenge widget areas. Uses WordPress facades, without modifying a site.
 */
define('ABSPATH', __DIR__ . '/');
$test_hooks = array();
$test_sidebar_regs = array();
$test_settings = array();
$test_rendered_sidebars = array();
$test_sidebar_layouts = array(
    'md-academy-sidebar' => array('block-2'),
    'inactive_widgets' => array('text-8'),
);
class WP_Widget {
    public $id_base;
    public function __construct($id, $label, $opts = array()) { $this->id_base = $id; }
}
function add_action($event, $handler, $priority = 10) {
    $GLOBALS['test_hooks'][$event][] = $handler;
}
function register_sidebar($opts) { $GLOBALS['test_sidebar_regs'][$opts['id']] = $opts; }
function register_widget($widget) { $GLOBALS['test_registered_widget'] = $widget; }
function is_admin() { return true; }
function current_user_can($cap) { return $cap === 'edit_theme_options'; }
function wp_parse_args($args, $defaults) { return array_merge($defaults, (array) $args); }
function wp_get_sidebars_widgets() { return $GLOBALS['test_sidebar_layouts']; }
function wp_set_sidebars_widgets($layouts) { $GLOBALS['test_sidebar_layouts'] = $layouts; }
function get_option($key, $fallback = false) {
    return array_key_exists($key, $GLOBALS['test_settings']) ? $GLOBALS['test_settings'][$key] : $fallback;
}
function update_option($key, $value, $autoload = null) {
    $GLOBALS['test_settings'][$key] = $value;
    return true;
}
function is_admin() { return true; }
function current_user_can($capability) { return $capability === 'edit_theme_options'; }
function is_active_sidebar($id) { return !empty($GLOBALS['test_sidebar_layouts'][$id]); }
function dynamic_sidebar($id) {
    $GLOBALS['test_rendered_sidebars'][] = $id;
    echo '<div data-dynamic="' . $id . '"></div>';
}
function wp_unique_id($prefix) { return $prefix . 'example'; }
function home_url($path = '/') { return 'https://example.test' . $path; }
function get_search_query() { return ''; }
function get_theme_mod($id, $fallback = '') { return $fallback; }
function is_singular($types = '') { return false; }
function is_post_type_archive($types = '') { return false; }
function is_tax($name = '') { return false; }
function esc_attr($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function esc_html($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function esc_url($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function esc_url_raw($v, $schemes = null) { return trim((string) $v); }
function esc_textarea($v) { return esc_html($v); }
function sanitize_textarea_field($v) { return trim(strip_tags((string) $v)); }
function sanitize_text_field($v) { return trim(strip_tags((string) $v)); }
function wp_unslash($v) { return $v; }
function absint($n) { return abs((int) $n); }
function selected($v, $expected, $echo = true) { return (string)$v === (string)$expected ? 'selected' : ''; }
function checked($v, $expected, $echo = true) { return (bool)$v === (bool)$expected ? 'checked' : ''; }
function apply_filters($tag, $value) { return $value; }
function md_social_networks() { return array(
    'telegram' => array('label'=>'تلگرام'), 'bale'=>array('label'=>'بله'),
    'eitaa'=>array('label'=>'ایتا'), 'instagram'=>array('label'=>'اینستاگرام'),
); }
function md_social_url($network) { return 'https://example.test/' . $network; }
function md_social_links($opts) { return '<nav class="md-social-family">social buttons</nav>'; }
function checkit($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
require dirname(__DIR__) . '/inc/sidebars.php';
md_register_sidebars();
checkit(count($GLOBALS['test_sidebar_regs']) === 3, 'all three widget areas must register');
foreach (array('md-magazine-sidebar','md-academy-sidebar','md-challenge-sidebar') as $id) {
    checkit(isset($GLOBALS['test_sidebar_regs'][$id]), 'missing registered sidebar ' . $id);
}
md_seed_default_sidebar_widgets();
$layout = $GLOBALS['test_sidebar_layouts'];
$instances = $GLOBALS['test_settings']['widget_md_sidebar_card'];
checkit(count($layout['md-magazine-sidebar']) === 6, 'magazine defaults are not individual widgets');
checkit(count($layout['md-challenge-sidebar']) === 5, 'challenge defaults missing');
checkit(count($layout['md-academy-sidebar']) === 1, 'existing academy widgets should stay untouched');
checkit($layout['md-academy-sidebar'][0] === 'block-2', 'existing Gutenberg widget must stay first');
checkit($layout['inactive_widgets'] === array('text-8'), 'inactive user widget was altered');
foreach (array('md-magazine-sidebar','md-challenge-sidebar') as $id) {
    $last = end($layout[$id]);
    preg_match('/^md_sidebar_card-([0-9]+)$/', $last, $match);
    checkit(isset($match[1]) && $instances[(int)$match[1]]['type'] === 'family',
        'family card not represented as editable widget in ' . $id);
}
$layout_before = serialize($layout);
md_seed_default_sidebar_widgets();
checkit(serialize($GLOBALS['test_sidebar_layouts']) === $layout_before, 'defaults reseeded');
$GLOBALS['test_sidebar_layouts']['md-challenge-sidebar'] = array();
md_seed_default_sidebar_widgets();
checkit($GLOBALS['test_sidebar_layouts']['md-challenge-sidebar'] === array(),
    'intentional widget removal was undone');
ob_start();
md_render_sidebar('academy');
$markup = ob_get_clean();
checkit(strpos($markup,'data-dynamic="md-academy-sidebar"') !== false, 'sidebar not rendered dynamically');
checkit(strpos($markup,'عضو خانواده') === false, 'uneditable hardcoded family card duplicated');
$widget = new MD_Sidebar_Card_Widget();
ob_start();
$widget->widget(array(), array('type'=>'family','title'=>'باشگاه دوستان',
    'body'=>'متن شخصی‌سازی‌شده','button_text'=>'عضویت','button_url'=>'https://example.test/join',
    'show_socials'=>0));
$card = ob_get_clean();
checkit(strpos($card,'باشگاه دوستان') !== false && strpos($card,'متن شخصی‌سازی‌شده') !== false,
    'family text cannot be edited');
checkit(strpos($card,'md-social-family') === false, 'unchecked social switch ignored');
$updated = $widget->update(array('type'=>'search','title'=>'جست‌وجوی منابع',
    'post_type'=>'md_resource','taxonomy'=>'category','count'=>99,'show_socials'=>0), array());
checkit($updated['post_type'] === 'md_resource' && $updated['count'] === 12,
    'search content type or list count settings failed');
$css = file_get_contents(dirname(__DIR__) . '/assets/css/sidebar.css');
checkit((bool)preg_match('/padding:\\s*0\\s*!important/', $css) &&
    (bool)preg_match('/justify-content:\\s*center/', $css),
    'magnifier must be centered without inherited button padding');
echo "Dynamic widget-only sidebar defaults, customization, and search centering: PASS\n";
