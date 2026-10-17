<?php
if (!defined('ABSPATH')) exit;

if (!defined('MD_THEME_VERSION')) {
 define('MD_THEME_VERSION', wp_get_theme()->get('Version') ? wp_get_theme()->get('Version') : '1.0.0');
}
if (!defined('MD_THEME_DIR')) define('MD_THEME_DIR', get_template_directory());
if (!defined('MD_THEME_URI')) define('MD_THEME_URI', get_template_directory_uri());

require_once MD_THEME_DIR . '/inc/helpers.php';
require_once MD_THEME_DIR . '/inc/dates.php';
require_once MD_THEME_DIR . '/inc/media.php';
require_once MD_THEME_DIR . '/inc/forms.php';
require_once MD_THEME_DIR . '/inc/blocks.php';
require_once MD_THEME_DIR . '/inc/sidebars.php';
require_once MD_THEME_DIR . '/inc/social.php';
require_once MD_THEME_DIR . '/inc/post-types.php';
require_once MD_THEME_DIR . '/inc/academy.php';
require_once MD_THEME_DIR . '/inc/course-builder.php';
require_once MD_THEME_DIR . '/inc/homepage.php';
require_once MD_THEME_DIR . '/inc/challenges/helpers.php';
require_once MD_THEME_DIR . '/inc/challenges/ajax.php';
require_once MD_THEME_DIR . '/inc/challenges/admin.php';
require_once MD_THEME_DIR . '/inc/challenges/public.php';
require_once MD_THEME_DIR . '/inc/customizer.php';
function md_setup() {
 add_theme_support('title-tag'); add_theme_support('post-thumbnails'); add_theme_support('html5', ['search-form','comment-form','comment-list','gallery','caption','style','script']); add_theme_support('custom-logo', ['height'=>100,'width'=>260,'flex-height'=>true,'flex-width'=>true]); add_theme_support('responsive-embeds'); add_theme_support('editor-styles'); add_theme_support('align-wide'); add_theme_support('wp-block-styles'); add_theme_support('automatic-feed-links'); add_theme_support('customize-selective-refresh-widgets'); add_theme_support('starter-content');
 load_theme_textdomain('morvarid-daron', get_template_directory() . '/languages');
 register_nav_menus(['primary'=>__('منوی اصلی','morvarid-daron'),'footer'=>__('منوی پایین سایت','morvarid-daron')]);
 add_image_size('md-card',720,480,true);
}
add_action('after_setup_theme','md_setup');
function md_assets() {
 wp_enqueue_style('md-main', MD_THEME_URI . '/assets/css/main.css', [], MD_THEME_VERSION);
 wp_enqueue_style('md-academy', MD_THEME_URI . '/assets/css/academy.css', ['md-main'], MD_THEME_VERSION);
 wp_enqueue_style('md-challenge', MD_THEME_URI . '/assets/css/challenge.css', ['md-main'], MD_THEME_VERSION);
 wp_enqueue_style('md-sidebar', MD_THEME_URI . '/assets/css/sidebar.css', ['md-main'], MD_THEME_VERSION);
 wp_enqueue_script('md-main', MD_THEME_URI . '/assets/js/main.js', [], MD_THEME_VERSION, true);
 if (is_singular() || is_author() || is_tax() || is_attachment()) {
  wp_enqueue_style('md-comments', MD_THEME_URI . '/assets/css/comments.css', ['md-main'], MD_THEME_VERSION);
 }
}
add_action('wp_enqueue_scripts','md_assets');
function md_excerpt($length){return is_admin() ? $length : 18;}
add_filter('excerpt_length','md_excerpt',99);
function md_fallback_menu(){
 $blog = get_option('page_for_posts') ? get_permalink(get_option('page_for_posts')) : home_url('/');
 $academy = get_post_type_archive_link('md_course') ?: home_url('/#academy');
 $coaching = get_post_type_archive_link('md_service') ?: home_url('/#coaching');
 $challenges = get_post_type_archive_link('md_challenge') ?: home_url('/');
 echo '<ul class="nav-list"><li><a href="'.esc_url(home_url('/')).'">خانه</a></li><li><a href="'.esc_url($blog).'">مجله</a></li><li><a href="'.esc_url($coaching).'">کوچینگ</a></li><li><a href="'.esc_url($academy).'">آکادمی</a></li><li><a href="'.esc_url($challenges).'">چالش‌ها</a></li></ul>';
}
function md_card($post_id,$label='') { ?>
<article class="content-card">
<a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="card-cover" aria-label="<?php echo esc_attr(get_the_title($post_id)); ?>">
<?php if(has_post_thumbnail($post_id)){echo get_the_post_thumbnail($post_id,'md-card',['loading'=>'lazy']);}else{echo '<span class="image-fallback" aria-hidden="true">✦</span>';} ?>
</a>
<div class="card-body"><span class="eyebrow"><?php echo esc_html($label ?: 'مروارید درون'); ?></span><h3><a href="<?php echo esc_url(get_permalink($post_id)); ?>"><?php echo esc_html(get_the_title($post_id)); ?></a></h3><p><?php echo esc_html(wp_trim_words(get_post_field('post_excerpt',$post_id) ?: wp_strip_all_tags(get_post_field('post_content',$post_id)),19,'…')); ?></p><a class="text-link" href="<?php echo esc_url(get_permalink($post_id)); ?>">بیشتر بخوانید ←</a></div>
</article><?php }

// فونت وزیرمتن به‌صورت محلی در قالب میزبانی می‌شود؛ هیچ درخواستی به CDN گوگل ارسال نمی‌شود.
function md_typography_assets(){
 wp_enqueue_style('md-fonts', MD_THEME_URI . '/assets/css/fonts.css', array(), MD_THEME_VERSION);
}
add_action('wp_enqueue_scripts','md_typography_assets',5);
// پیش‌بارگذاری فونت متغیر برای جلوگیری از پرش متن (FOUT).
add_action('wp_head', function () {
 echo '<link rel="preload" href="' . esc_url(MD_THEME_URI . '/assets/fonts/Vazirmatn-Variable.woff2') . '" as="font" type="font/woff2" crossorigin>' . "\n";
}, 2);
function md_editor_style(){ add_editor_style(['assets/css/fonts.css', 'assets/css/editor.css']); }
add_action('after_setup_theme','md_editor_style');


// Magazine layout options (sidebars are registered in inc/sidebars.php).
add_action('customize_register', function ($wp_customize) {
 $wp_customize->add_section('md_magazine_settings',['title'=>'چیدمان مجله','priority'=>42]);
 foreach(['md_single_sidebar'=>'نمایش سایدبار در مقاله‌ها','md_archive_sidebar'=>'نمایش سایدبار در صفحه مجله'] as $id=>$label){
  $wp_customize->add_setting($id,['default'=>'right','sanitize_callback'=>function($v){return in_array($v,['right','left','none'],true)?$v:'right';}]);
  $wp_customize->add_control($id,['label'=>$label,'section'=>'md_magazine_settings','type'=>'select','choices'=>['right'=>'سمت راست','left'=>'سمت چپ','none'=>'بدون سایدبار']]);
 }
});

// Dynamic posts display, inserted through the standard WordPress Shortcode block.
add_shortcode('morvarid_grid',function($atts){
 $a=shortcode_atts(['type'=>'post','count'=>'3','title'=>'','subtitle'=>'','category'=>''], $atts,'morvarid_grid');
 $allowed=['post','md_service','md_course','md_resource','md_lesson','md_podcast'];
 $type=in_array($a['type'],$allowed,true)?$a['type']:'post';
 $count=max(1,min(12,absint($a['count'])));
 $args=['post_type'=>$type,'posts_per_page'=>$count,'post_status'=>'publish','ignore_sticky_posts'=>true];
 if($type==='post' && $a['category'])$args['category_name']=sanitize_title($a['category']);
 $q=new WP_Query($args);ob_start();
 echo '<section class="section md-home-grid-section"><div class="container">';
 if($a['title']||$a['subtitle'])echo '<div class="section-head"><div>'.($a['title']?'<h2>'.esc_html($a['title']).'</h2>':'').($a['subtitle']?'<p>'.esc_html($a['subtitle']).'</p>':'').'</div></div>';
 echo '<div class="three-col card-grid">';
 if($q->have_posts()){while($q->have_posts()){$q->the_post();md_card(get_the_ID(),$type==='post'?'مجله':'مروارید درون');}wp_reset_postdata();}
 else echo '<p class="empty-note">پس از انتشار محتوا از پیشخوان، مطالب این بخش به صورت خودکار نمایش داده می‌شوند.</p>';
 echo '</div></div></section>';return ob_get_clean();
});
// Ready-to-insert block patterns. Editors can reorder, remove and insert any Gutenberg block.
add_action('init',function(){
 if(!function_exists('register_block_pattern'))return;
 register_block_pattern_category('morvarid',['label'=>'مروارید درون']);
 $hero='<!-- wp:group {"align":"full","className":"md-block-hero","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull md-block-hero"><!-- wp:paragraph {"className":"eyebrow"} --><p class="eyebrow">رشد، آگاهی، ارتباط</p><!-- /wp:paragraph --><!-- wp:heading {"level":1} --><h1 class="wp-block-heading">جایی برای کشف خود، فهم نوجوان و ساختن رابطه‌ای عمیق‌تر</h1><!-- /wp:heading --><!-- wp:paragraph --><p>فضایی برای آگاهی، رشد و همراهی والدین و نوجوانان.</p><!-- /wp:paragraph --><!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/category/parents/">من والد هستم</a></div><!-- /wp:button --><!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/category/teens/">من نوجوان هستم</a></div><!-- /wp:button --></div><!-- /wp:buttons --></div><!-- /wp:group -->';
 register_block_pattern('morvarid/hero',['title'=>'مروارید | سربرگ قابل ویرایش','categories'=>['morvarid'],'content'=>$hero]);
 foreach(['post'=>['مجله مروارید درون','مقاله‌های تازه'], 'md_service'=>['خدمات کوچینگ','همراهی برای رشد'], 'md_course'=>['آکادمی مروارید درون','دوره‌ها و کارگاه‌ها'], 'md_resource'=>['منابع رایگان','تمرین‌ها و فایل‌های آموزشی'], 'md_podcast'=>['پادکست‌ها','شنیدنی‌های تازه']] as $type=>$labels){
  $short='[morvarid_grid type="'.$type.'" count="3" title="'.$labels[0].'" subtitle="'.$labels[1].'"]';
  register_block_pattern('morvarid/grid-'.$type,['title'=>'مروارید | '.$labels[0],'categories'=>['morvarid'],'content'=>'<!-- wp:shortcode -->'.$short.'<!-- /wp:shortcode -->']);
 }
 $sections=['post','md_service','md_course','md_resource','md_podcast'];
 $content=$hero;
 foreach($sections as $type){$titles=['post'=>'مجله مروارید درون','md_service'=>'کوچینگ','md_course'=>'آکادمی آموزشی','md_resource'=>'تمرین‌ها و منابع رایگان','md_podcast'=>'پادکست‌ها'];$content.='<!-- wp:shortcode -->[morvarid_grid type="'.$type.'" count="3" title="'.$titles[$type].'"]<!-- /wp:shortcode -->';}
 register_block_pattern('morvarid/full-home',['title'=>'مروارید | صفحه اصلی کامل و قابل تنظیم','description'=>'سربرگ و فهرست‌های پویای مجله، کوچینگ، آکادمی، منابع و پادکست‌ها','categories'=>['morvarid'],'content'=>$content]);
});


// Dedicated theme settings page. Settings use WordPress Settings API.
add_action('admin_menu',function(){add_theme_page('تنظیمات مروارید درون','تنظیمات مروارید','edit_theme_options','md-theme-options','md_theme_options_page');});
add_action('admin_init',function(){
 register_setting('md_theme_options','md_theme_options',['type'=>'array','sanitize_callback'=>'md_sanitize_theme_options','default'=>[]]);
 add_settings_section('md_general','نمایش و چیدمان','__return_false','md-theme-options');
 foreach(['magazine_title'=>'عنوان مجله','academy_title'=>'عنوان آکادمی','academy_intro'=>'توضیحات آکادمی','challenges_title'=>'عنوان چالش‌ها','challenges_intro'=>'توضیحات چالش‌ها','magazine_intro'=>'توضیحات مجله','primary_color'=>'رنگ اصلی (HEX)','items_per_page'=>'تعداد کارت در هر صفحه'] as $key=>$label){
 add_settings_field($key,$label,function($args){$o=get_option('md_theme_options',[]);$k=$args['key'];$v=$o[$k]??'';echo '<input class="regular-text" type="'.($k==='items_per_page'?'number':'text').'" name="md_theme_options['.esc_attr($k).']" value="'.esc_attr($v).'" '.($k==='items_per_page'?'min="3" max="24"':'').'/>';},'md-theme-options','md_general',['key'=>$key]);
 }
 add_settings_section('md_social','شبکه‌های اجتماعی (کانال بله، تلگرام و اینستاگرام)','__return_false','md-theme-options');
 foreach(['telegram_url'=>'لینک کانال تلگرام','bale_url'=>'لینک کانال بله','instagram_url'=>'لینک پیج اینستاگرام'] as $key=>$label){
 add_settings_field($key,$label,function($args){$o=get_option('md_theme_options',[]);$k=$args['key'];$v=$o[$k]??'';echo '<input class="regular-text" type="url" dir="ltr" placeholder="https://" name="md_theme_options['.esc_attr($k).']" value="'.esc_attr($v).'"/>';},'md-theme-options','md_social',['key'=>$key]);
 }
});
function md_sanitize_theme_options($input){$out=[];foreach(['magazine_title','academy_title','academy_intro','challenges_title','challenges_intro','magazine_intro'] as $k)$out[$k]=sanitize_text_field($input[$k]??'');$out['primary_color']=sanitize_hex_color($input['primary_color']??'')?:'#326d62';$out['items_per_page']=max(3,min(24,absint($input['items_per_page']??9)));foreach(['telegram_url','bale_url','instagram_url'] as $k)$out[$k]=esc_url_raw($input[$k]??'');return $out;}
function md_theme_option($key,$default=''){ $o=get_option('md_theme_options',[]);return isset($o[$key])&&$o[$key]!==''?$o[$key]:$default;}
function md_theme_options_page(){if(!current_user_can('edit_theme_options'))return;$home_link=md_home_page_edit_url()?'<a href="'.esc_url(md_home_page_edit_url()).'">ویرایش برگهٔ خانه</a>':'';echo '<div class="wrap"><h1>تنظیمات قالب مروارید درون</h1><p>مدیریت رنگ و عناوین مجله و آکادمی و لینک کانال‌های بله، تلگرام و اینستاگرام. مدیریت سایدبار از نمایش ← ابزارک‌ها است.</p><p>صفحه اصلی یک برگهٔ معمولی است و همهٔ بخش‌هایش با بلوک‌های گوتنبرگ ویرایش می‌شود. '.$home_link.'</p><form method="post" action="options.php">';settings_fields('md_theme_options');do_settings_sections('md-theme-options');submit_button('ذخیره تنظیمات');echo '</form></div>';}
add_action('wp_enqueue_scripts',function(){ $c=md_theme_option('primary_color','#326d62');wp_add_inline_style('md-main',':root{--md-accent:'.esc_attr($c).';} .md-search-button,.md-academy-pill.is-active{background:var(--md-accent)}');},20);
function md_pretty_search($only_posts=true){$action=home_url('/');$id=wp_unique_id('md-search-');return '<form role="search" method="get" class="md-pretty-search" action="'.esc_url($action).'"><label class="screen-reader-text" for="'.esc_attr($id).'">جستجو در مجله</label><span class="md-search-icon" aria-hidden="true">⌕</span><input id="'.esc_attr($id).'" type="search" name="s" placeholder="در مجله جستجو کنید…" value="'.esc_attr(get_search_query()).'" />'.($only_posts?'<input type="hidden" name="post_type" value="post">':'').'<button class="md-search-button" type="submit">جستجو ←</button></form>';}
add_filter('get_search_form',function($form){if(is_home()||is_singular('post')||is_category()||is_tag())return md_pretty_search();return $form;});
// Make searchable archive of academy content across format types.
add_action('pre_get_posts',function($q){if(is_admin()||!$q->is_main_query())return; if($q->is_post_type_archive('md_course')||$q->is_post_type_archive('md_lesson')||$q->is_post_type_archive('md_podcast'))$q->set('posts_per_page',max(3,min(24,(int)md_theme_option('items_per_page',9))));if($q->is_home())$q->set('posts_per_page',max(3,min(24,(int)md_theme_option('items_per_page',9))));});

// Morvarid Gutenberg design library: the same category is shared with the content plugin.
add_filter('block_categories_all', function($categories) {
 foreach ($categories as $category) { if (($category['slug'] ?? '') === 'morvarid-theme') return $categories; }
 array_unshift($categories, ['slug'=>'morvarid-theme','title'=>'بلوک‌های قالب مروارید','icon'=>null]);
 return $categories;
}, 10, 1);
function md_design_block_render($attributes, $content, $block) {
 $name = $block->name;
 $defaults=['morvarid/hero'=>'جایی برای کشف خود','morvarid/content-grid'=>'تازه‌ترین مطالب','morvarid/action'=>'برای گفت‌وگو آماده‌ای؟','morvarid/download'=>'کاربرگ آموزشی','morvarid/accordion'=>'پرسش‌های متداول'];
 $title = esc_html(trim((string)($attributes['title'] ?? '')) ?: ($defaults[$name] ?? ''));
 if ($name === 'morvarid/hero') {
  $subtitle=esc_html(trim((string)($attributes['subtitle'] ?? '')) ?: 'رشد، آگاهی و ارتباط عمیق‌تر');
  $eyebrow=esc_html(trim((string)($attributes['eyebrow'] ?? '')) ?: 'مروارید درون');
  $label=esc_html(trim((string)($attributes['buttonLabel'] ?? '')) ?: 'شروع کنید');
  $link=esc_url($attributes['buttonUrl'] ?? '');
  $label2=esc_html(trim((string)($attributes['secondaryLabel'] ?? '')));
  $link2=esc_url($attributes['secondaryUrl'] ?? '');
  $buttons = $link ? '<a class="md-gb-button" href="'.$link.'">'.$label.'</a>' : '';
  if ($label2 && $link2) {
   $buttons .= '<a class="md-gb-button md-gb-button-light" href="'.$link2.'">'.$label2.'</a>';
  }
  $heading = is_front_page() ? 'h1' : 'h2';
  $image_id = absint($attributes['imageId'] ?? 0);
  $art_quote = trim((string)($attributes['artQuote'] ?? ''));
  $media = '';
  if ($image_id) {
   $media = '<div class="md-gb-hero-media">'.wp_get_attachment_image($image_id,'large',false,['loading'=>'eager']).'</div>';
  } elseif ($art_quote) {
   $media = '<div class="md-gb-hero-media"><div class="hero-art" role="img" aria-label="'.esc_attr($art_quote).'"><span class="art-ring">◌</span><span class="art-pearl">●</span><div class="art-quote">'.esc_html($art_quote).'</div></div></div>';
  }
  $copy = '<div class="md-gb-hero-copy"><span class="md-gb-eyebrow">'.$eyebrow.'</span><'.$heading.'>'.$title.'</'.$heading.'><p>'.$subtitle.'</p>'.($buttons?'<div class="md-gb-hero-actions">'.$buttons.'</div>':'').'</div>';
  return '<section class="md-gb-hero'.($media?' md-gb-hero-split':'').'"><div class="container">'.$copy.$media.'</div></section>';
 }
 if ($name === 'morvarid/content-grid') {
  $allowed=['post','md_course','md_lesson','md_podcast','md_service','md_resource'];
  $type=in_array(($attributes['postType'] ?? 'post'),$allowed,true)?$attributes['postType']:'post';
  $count=max(1,min(12,absint($attributes['count'] ?? 3)));
  $columns=max(1,min(4,absint($attributes['columns'] ?? 3)));
  $args=['post_type'=>$type,'post_status'=>'publish','posts_per_page'=>$count,'ignore_sticky_posts'=>true];
  if ($type==='post' && !empty($attributes['categorySlug'])) $args['category_name']=sanitize_title($attributes['categorySlug']);
  $subtitle=esc_html(trim((string)($attributes['subtitle'] ?? '')));
  $link_label=trim((string)($attributes['linkLabel'] ?? ''));
  $link_url=esc_url($attributes['linkUrl'] ?? '');
  if (!$link_url) {
   if ('post'===$type && get_option('page_for_posts')) {
    $link_url=esc_url(get_permalink((int)get_option('page_for_posts')));
   } elseif ('post'!==$type) {
    $link_url=esc_url((string)get_post_type_archive_link($type));
   }
  }
  $bg=in_array(($attributes['background'] ?? ''),['cream','warm','mint'],true)?' md-gb-bg-'.$attributes['background']:'';
  $head='<div class="md-gb-section-head"><div class="md-gb-section-copy">'.($title?'<h2>'.$title.'</h2>':'').($subtitle?'<p class="md-gb-section-lead">'.$subtitle.'</p>':'').'</div>';
  if ($link_label && $link_url) {
   $head.='<a class="text-link md-gb-section-link" href="'.$link_url.'">'.esc_html($link_label).' ←</a>';
  }
  $head.='</div>';
  $q=new WP_Query($args);ob_start();
  echo '<section class="md-gb-grid-section'.$bg.'"><div class="container">'.$head.'<div class="md-gb-grid md-gb-cols-'.esc_attr((string)$columns).'">';
  $labels=['post'=>'مجله','md_course'=>'دوره آموزشی','md_lesson'=>'جلسه','md_podcast'=>'پادکست','md_service'=>'کوچینگ','md_resource'=>'منبع رایگان'];
  $card_label=$labels[$type] ?? 'مروارید درون';
  if ($q->have_posts()) { while ($q->have_posts()) {$q->the_post();md_card(get_the_ID(),$card_label);}wp_reset_postdata(); }
  else echo '<p class="md-gb-empty">هنوز محتوای منتشرشده‌ای وجود ندارد.</p>';
  echo '</div></div></section>';return ob_get_clean();
 }
 if ($name === 'morvarid/action') {
  $subtitle=esc_html(trim((string)($attributes['subtitle'] ?? '')) ?: 'با یک قدم کوچک شروع کن');$label=esc_html(trim((string)($attributes['buttonLabel'] ?? '')) ?: 'رزرو جلسه');$url=esc_url($attributes['buttonUrl'] ?? '');
  return '<section class="md-gb-action"><div><h2>'.$title.'</h2><p>'.$subtitle.'</p></div>'.($url?'<a class="md-gb-button" href="'.$url.'">'.$label.'</a>':'').'</section>';
 }
 if ($name === 'morvarid/download') {
  $description=esc_html(trim((string)($attributes['description'] ?? '')) ?: 'فایل کمکی برای یادگیری');$url=esc_url($attributes['fileUrl'] ?? '');$label=esc_html(trim((string)($attributes['buttonLabel'] ?? '')) ?: 'دریافت فایل');
  $button=$url?'<a class="md-gb-button" href="'.$url.'" download>'.$label.'</a>':'<span class="md-gb-button md-gb-button-disabled" aria-disabled="true">'.$label.'</span>';
  return '<section class="md-gb-download"><span class="md-download-icon" aria-hidden="true">↓</span><div class="md-download-info"><h3>'.$title.'</h3><p>'.$description.'</p>'.(!$url?'<p class="md-download-no-file">هنوز فایلی برای دانلود انتخاب نشده است.</p>':'').'</div>'.$button.'</section>';
 }
 if ($name === 'morvarid/accordion') {
  $items=$attributes['items'] ?? [];if(!is_array($items))$items=[];if(!$items && !empty($attributes['body']))$items=[['q'=>$title,'a'=>$attributes['body']]];
  $out='<section class="md-faq-group"><h2>'.$title.'</h2>';foreach($items as $i=>$item){$question=trim((string)($item['q']??''));$answer=trim((string)($item['a']??''));if(!$question && !$answer)continue;$out.='<details class="md-gb-accordion"'.($i===0&&!empty($attributes['openFirst'])?' open':'').'><summary>'.esc_html($question ?: 'پرسش').'</summary><div class="md-gb-accordion-body">'.wpautop(esc_html($answer)).'</div></details>';}return $out.'</section>';
 }
 return $content;
}
add_action('init', function() {
 $script='md-design-editor';
 wp_register_script($script,get_template_directory_uri().'/assets/js/gutenberg-blocks.js',['wp-blocks','wp-element','wp-components','wp-block-editor','wp-server-side-render','wp-media'],MD_THEME_VERSION,true);
 $blocks=[
  'hero'=>['eyebrow'=>'مروارید درون','title'=>'جایی برای کشف خود','subtitle'=>'رشد، آگاهی و ارتباط عمیق‌تر','buttonLabel'=>'شروع کنید','buttonUrl'=>'','secondaryLabel'=>'','secondaryUrl'=>'','imageId'=>0,'artQuote'=>''],
  'content-grid'=>['title'=>'تازه‌ترین مطالب','subtitle'=>'','postType'=>'post','count'=>3,'columns'=>3,'categorySlug'=>'','linkLabel'=>'','linkUrl'=>'','background'=>''],
  'action'=>['title'=>'برای گفت‌وگو آماده‌ای؟','subtitle'=>'با یک قدم کوچک شروع کن','buttonLabel'=>'رزرو جلسه','buttonUrl'=>''],
  'download'=>['title'=>'کاربرگ آموزشی','description'=>'فایل کمکی برای یادگیری','fileUrl'=>'','buttonLabel'=>'دریافت فایل'],
  'accordion'=>['title'=>'پرسش‌های متداول','items'=>[['q'=>'پرسش اول','a'=>'پاسخ پرسش اول']],'openFirst'=>false,'body'=>''],
 ];
 foreach($blocks as $slug=>$fields){$attributes=[];foreach($fields as $field=>$default)$attributes[$field]=['type'=>(is_int($default)?'number':(is_bool($default)?'boolean':(is_array($default)?'array':'string'))),'default'=>$default];
 register_block_type('morvarid/'.$slug,['api_version'=>2,'editor_script'=>$script,'attributes'=>$attributes,'render_callback'=>'md_design_block_render']);}
});
add_action('enqueue_block_assets', function(){wp_enqueue_style('md-gutenberg-shared',get_template_directory_uri().'/assets/css/gutenberg-blocks.css',[], MD_THEME_VERSION);wp_enqueue_style('md-forms',get_template_directory_uri().'/assets/css/forms.css',['md-gutenberg-shared'], MD_THEME_VERSION);});
