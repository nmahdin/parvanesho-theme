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
require_once MD_THEME_DIR . '/inc/comments-learning.php';
require_once MD_THEME_DIR . '/inc/blocks.php';
require_once MD_THEME_DIR . '/inc/resource-blocks.php';
require_once MD_THEME_DIR . '/inc/sidebars.php';
require_once MD_THEME_DIR . '/inc/social.php';
require_once MD_THEME_DIR . '/inc/post-types.php';
require_once MD_THEME_DIR . '/inc/academy.php';
require_once MD_THEME_DIR . '/inc/unified-academy.php';
require_once MD_THEME_DIR . '/inc/course-builder.php';
require_once MD_THEME_DIR . '/inc/homepage.php';
require_once MD_THEME_DIR . '/inc/challenges/helpers.php';
require_once MD_THEME_DIR . '/inc/challenges/editor.php';
require_once MD_THEME_DIR . '/inc/challenges/ajax.php';
require_once MD_THEME_DIR . '/inc/challenges/admin.php';
require_once MD_THEME_DIR . '/inc/challenges/public.php';
require_once MD_THEME_DIR . '/inc/challenges/members.php';
require_once MD_THEME_DIR . '/inc/challenges/dashboard.php';
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
 $challenges = get_post_type_archive_link('md_challenge') ?: home_url('/');
 echo '<ul class="nav-list"><li><a href="'.esc_url(home_url('/')).'">خانه</a></li><li><a href="'.esc_url($blog).'">مجله</a></li><li><a href="'.esc_url($academy).'">آکادمی</a></li><li><a href="'.esc_url($challenges).'">چالش‌ها</a></li></ul>';
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
function md_editor_style(){ add_editor_style(['assets/css/fonts.css','assets/css/main.css','assets/css/academy.css','assets/css/challenge.css','assets/css/sidebar.css','assets/css/gutenberg-blocks.css','assets/css/forms.css','assets/css/player.css','assets/css/editor.css']); }
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
 $allowed=['post','md_course','md_resource','md_lesson','md_podcast'];
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
 foreach(['post'=>['مجله مروارید درون','مقاله‌های تازه'], 'md_course'=>['آکادمی مروارید درون','دوره‌ها و کارگاه‌ها'], 'md_resource'=>['منابع رایگان','تمرین‌ها و فایل‌های آموزشی'], 'md_podcast'=>['پادکست‌ها','شنیدنی‌های تازه']] as $type=>$labels){
  $short='[morvarid_grid type="'.$type.'" count="3" title="'.$labels[0].'" subtitle="'.$labels[1].'"]';
  register_block_pattern('morvarid/grid-'.$type,['title'=>'مروارید | '.$labels[0],'categories'=>['morvarid'],'content'=>'<!-- wp:shortcode -->'.$short.'<!-- /wp:shortcode -->']);
 }
 $sections=['post','md_course','md_resource','md_podcast'];
 $content=$hero;
 foreach($sections as $type){$titles=['post'=>'مجله مروارید درون','md_course'=>'آکادمی آموزشی','md_resource'=>'تمرین‌ها و منابع رایگان','md_podcast'=>'پادکست‌ها'];$content.='<!-- wp:shortcode -->[morvarid_grid type="'.$type.'" count="3" title="'.$titles[$type].'"]<!-- /wp:shortcode -->';}
 register_block_pattern('morvarid/full-home',['title'=>'مروارید | صفحه اصلی کامل و قابل تنظیم','description'=>'سربرگ و فهرست‌های پویای مجله، آکادمی، منابع و پادکست‌ها','categories'=>['morvarid'],'content'=>$content]);
});


// Dedicated theme settings page. Settings use WordPress Settings API.
add_action('admin_menu',function(){add_theme_page('تنظیمات مروارید درون','تنظیمات مروارید','edit_theme_options','md-theme-options','md_theme_options_page');});
add_action('admin_init',function(){
 register_setting('md_theme_options','md_theme_options',['type'=>'array','sanitize_callback'=>'md_sanitize_theme_options','default'=>[]]);
 add_settings_section('md_general','نمایش و چیدمان','__return_false','md-theme-options');
 foreach(['magazine_title'=>'عنوان مجله','academy_title'=>'عنوان آکادمی','academy_intro'=>'توضیحات آکادمی','challenges_title'=>'عنوان چالش‌ها','challenges_intro'=>'توضیحات چالش‌ها','magazine_intro'=>'توضیحات مجله','primary_color'=>'رنگ اصلی (HEX)','items_per_page'=>'تعداد کارت در هر صفحه'] as $key=>$label){
 add_settings_field($key,$label,function($args){$o=get_option('md_theme_options',[]);$k=$args['key'];$v=$o[$k]??'';echo '<input class="regular-text" type="'.($k==='items_per_page'?'number':'text').'" name="md_theme_options['.esc_attr($k).']" value="'.esc_attr($v).'" '.($k==='items_per_page'?'min="3" max="24"':'').'/>';},'md-theme-options','md_general',['key'=>$key]);
 }
 add_settings_section('md_social','شبکه‌های اجتماعی (بله، ایتا، تلگرام و اینستاگرام)','__return_false','md-theme-options');
 foreach(['telegram_url'=>'لینک کانال تلگرام','bale_url'=>'لینک کانال بله','eitaa_url'=>'لینک کانال ایتا','instagram_url'=>'لینک پیج اینستاگرام'] as $key=>$label){
 add_settings_field($key,$label,function($args){$o=get_option('md_theme_options',[]);$k=$args['key'];$v=$o[$k]??'';echo '<input class="regular-text" type="url" dir="ltr" placeholder="https://" name="md_theme_options['.esc_attr($k).']" value="'.esc_attr($v).'"/>';},'md-theme-options','md_social',['key'=>$key]);
 }
});
function md_sanitize_theme_options($input){
 $input=is_array($input)?$input:[];
 $out=[];
 foreach(['magazine_title','academy_title','academy_intro','challenges_title','challenges_intro','magazine_intro','header_title','header_tagline','header_booking_label'] as $k){
   $out[$k]=sanitize_text_field($input[$k]??'');
 }
 $out['primary_color']=sanitize_hex_color($input['primary_color']??'')?:'#326d62';
 $out['items_per_page']=max(3,min(24,absint($input['items_per_page']??9)));
 foreach(['telegram_url','bale_url','eitaa_url','instagram_url','header_booking_url'] as $k){
   $out[$k]=esc_url_raw($input[$k]??'');
 }
 foreach(['header_booking_show','header_profile_show'] as $k){
   $out[$k]=isset($input[$k])&&$input[$k]==='0'?'0':'1';
 }
 $out['header_logo_id']=absint($input['header_logo_id']??0);
 $out['home_person_image_id']=absint($input['home_person_image_id']??0);
 return $out;
}
// Homepage portrait can be uploaded without editing the Gutenberg page.
add_action('admin_init',function(){
 add_settings_section('md_home_photo_options','تصویر معرفی صفحه اصلی',function(){
   echo '<p>عکس شخص را از کتابخانهٔ رسانه انتخاب کنید. جایگزین تصویر تزئینی قبلی خواهد شد. اگر عکس انتخاب نشود، صفحه بدون تصویر تزئینی نمایش داده می‌شود.</p>';
 },'md-theme-options');
 add_settings_field('home_person_image_id','عکس شخص در سربرگ صفحه اصلی',function(){
   $opts=get_option('md_theme_options',[]);
   $id=absint($opts['home_person_image_id']??0);
   $src=$id?wp_get_attachment_image_url($id,'medium'):'';
   echo '<input type="hidden" id="md-home-person-id" name="md_theme_options[home_person_image_id]" value="'.esc_attr($id).'">';
   echo '<img id="md-home-person-preview" src="'.esc_url($src).'" alt="پیش‌نمایش عکس شخص" style="display:'.($src?'block':'none').';width:150px;height:150px;object-fit:cover;object-position:center top;border-radius:18px;margin:0 0 12px">';
   echo '<button type="button" class="button" id="md-home-person-choose">انتخاب یا بارگذاری عکس</button> ';
   echo '<button type="button" class="button" id="md-home-person-clear">حذف عکس</button>';
   echo '<p class="description">در صورت انتخاب تصویر مستقل در بلوک «بخش اصلی / هیرو»، تصویر همان بلوک اولویت دارد.</p>';
 },'md-theme-options','md_home_photo_options');
});
// All visible header controls live inside Appearance > Theme Settings.
add_action('admin_init',function(){
 add_settings_section('md_header_options','تنظیمات هدر و دسترسی سریع','__return_false','md-theme-options');
 $fields=[
  'header_title'=>['عنوان برند','text','مروارید درون'],
  'header_tagline'=>['زیرعنوان برند','text','رشد آگاهانه، رابطه‌ای عمیق‌تر'],
  'header_booking_label'=>['متن دکمه رزرو','text','رزرو جلسه'],
  'header_booking_url'=>['آدرس دکمه رزرو','url',md_booking_url()],
  'header_booking_show'=>['نمایش دکمه رزرو','select','1'],
  'header_profile_show'=>['نمایش دکمه پروفایل (موبایل و دسکتاپ)','select','1'],
  'header_logo_id'=>['لوگوی هدر','number','0'],
 ];
 foreach($fields as $key=>$field){
   add_settings_field($key,$field[0],function($args){
     $o=get_option('md_theme_options',[]);
     $key=$args['key'];$type=$args['type'];
     $value=isset($o[$key])?$o[$key]:$args['default'];
     if($key==='header_logo_id'){
       $src=absint($value)?wp_get_attachment_image_url(absint($value),'thumbnail'):'';
       echo '<input type="hidden" id="md-header-logo-id" name="md_theme_options[header_logo_id]" value="'.esc_attr(absint($value)).'">';
       echo '<img id="md-header-logo-preview" src="'.esc_url($src).'" alt="پیش‌نمایش لوگوی هدر" style="max-width:160px;max-height:75px;object-fit:contain;display:'.($src?'block':'none').';margin:0 0 12px">';
       echo '<button type="button" class="button" id="md-header-logo-choose">انتخاب از رسانه‌ها</button> ';
       echo '<button type="button" class="button" id="md-header-logo-clear">حذف لوگوی انتخابی</button>';
       echo '<p class="description">بدون انتخاب جدید، لوگوی کنونی سایت حفظ می‌شود.</p>';
     }elseif($type==='select'){
       echo '<select name="md_theme_options['.esc_attr($key).']">';
       echo '<option value="1" '.selected($value,'1',false).'>نمایش داده شود</option>';
       echo '<option value="0" '.selected($value,'0',false).'>پنهان شود</option>';
       echo '</select>';
     }else{
       echo '<input class="regular-text" type="'.esc_attr($type).'" name="md_theme_options['.esc_attr($key).']" value="'.esc_attr($value).'"'.($type==='number'?' min="0" step="1"':'').' />';
       if($key==='header_logo_id')echo '<p class="description">شناسه تصویر از رسانه‌ها؛ اگر خالی باشد لوگوی پیش‌فرض قالب استفاده می‌شود.</p>';
     }
   },'md-theme-options','md_header_options',['key'=>$key,'type'=>$field[1],'default'=>$field[2]]);
 }
});
add_action('admin_enqueue_scripts',function($hook){
 if($hook!=='appearance_page_md-theme-options')return;
 wp_enqueue_media();
 wp_enqueue_script('md-header-settings',MD_THEME_URI.'/assets/js/header-settings.js',['jquery','media-views'],MD_THEME_VERSION,true);
});
function md_theme_option($key,$default=''){ $o=get_option('md_theme_options',[]);return isset($o[$key])&&$o[$key]!==''?$o[$key]:$default;}
function md_theme_options_page(){if(!current_user_can('edit_theme_options'))return;$home_link=md_home_page_edit_url()?'<a href="'.esc_url(md_home_page_edit_url()).'">ویرایش برگهٔ خانه</a>':'';$needs_home=!md_home_page_id()||!md_home_page_has_blocks(md_home_page_id());$build_link=$needs_home?' <a class="button" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=md_build_home'),'md_build_home')).'">ساخت صفحه اصلی بلوکی</a>':'';$registered=class_exists('WP_Block_Type_Registry')?WP_Block_Type_Registry::get_instance()->get_all_registered():[];$theme_blocks=0;foreach($registered as $block_name=>$block_type){if(0===strpos($block_name,'morvarid/'))$theme_blocks++;}$script_ok=file_exists(MD_THEME_DIR.'/assets/js/blocks.js');$status='<p class="description">وضعیت سمت سرور: بلوک‌های ثبت‌شدهٔ قالب: <strong>'.absint($theme_blocks).'</strong> — وردپرس: '.esc_html(get_bloginfo('version')).' — فایل اسکریپت ویرایشگر: '.($script_ok?'موجود':'<strong>یافت نشد</strong>').' — نسخهٔ قالب: '.esc_html(MD_THEME_VERSION).'</p>';echo '<div class="wrap"><h1>تنظیمات قالب مروارید درون</h1><p>مدیریت رنگ و عناوین مجله و آکادمی و لینک کانال‌های بله، ایتا، تلگرام و اینستاگرام. مدیریت سایدبار از نمایش ← ابزارک‌ها است.</p><p>صفحه اصلی یک برگهٔ معمولی است و همهٔ بخش‌هایش با بلوک‌های گوتنبرگ ویرایش می‌شود. '.$home_link.$build_link.'</p>'.$status.'<form method="post" action="options.php">';settings_fields('md_theme_options');do_settings_sections('md-theme-options');submit_button('ذخیره تنظیمات');echo '</form></div>';}
add_action('wp_enqueue_scripts',function(){ $c=md_theme_option('primary_color','#326d62');wp_add_inline_style('md-main',':root{--md-accent:'.esc_attr($c).';} .md-search-button,.md-academy-pill.is-active{background:var(--md-accent)}');},20);
function md_pretty_search($only_posts=true){$action=home_url('/');$id=wp_unique_id('md-search-');return '<form role="search" method="get" class="md-pretty-search" action="'.esc_url($action).'"><label class="screen-reader-text" for="'.esc_attr($id).'">جستجو در مجله</label><span class="md-search-icon" aria-hidden="true">⌕</span><input id="'.esc_attr($id).'" type="search" name="s" placeholder="در مجله جستجو کنید…" value="'.esc_attr(get_search_query()).'" />'.($only_posts?'<input type="hidden" name="post_type" value="post">':'').'<button class="md-search-button" type="submit">جستجو ←</button></form>';}
add_filter('get_search_form',function($form){if(is_home()||is_singular('post')||is_category()||is_tag())return md_pretty_search();return $form;});
// Make searchable archive of academy content across format types.
add_action('pre_get_posts',function($q){if(is_admin()||!$q->is_main_query())return; if($q->is_post_type_archive('md_course')||$q->is_post_type_archive('md_lesson')||$q->is_post_type_archive('md_podcast'))$q->set('posts_per_page',max(3,min(24,(int)md_theme_option('items_per_page',9))));if($q->is_home())$q->set('posts_per_page',max(3,min(24,(int)md_theme_option('items_per_page',9))));});

// Morvarid Gutenberg design library: the same category is shared with the content plugin.
add_filter('block_categories_all', function($categories) {
 if (!is_array($categories)) return $categories;
 foreach ($categories as $category) { if (is_array($category) && ($category['slug'] ?? '') === 'morvarid-theme') return $categories; }
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
  if (!$image_id) $image_id = absint(md_theme_option('home_person_image_id',0));
  $media = '';
  if ($image_id && wp_attachment_is_image($image_id)) {
   $media = '<div class="md-gb-hero-media md-home-portrait">'.wp_get_attachment_image($image_id,'large',false,['loading'=>'eager','decoding'=>'async']).'</div>';
  }
  $quote = trim((string)($attributes['quote'] ?? ''));
  $quote_html = $quote!=='' ? '<blockquote class="md-home-hero-quote">'.esc_html($quote).'</blockquote>' : '';
  $copy = '<div class="md-gb-hero-copy"><span class="md-gb-eyebrow">'.$eyebrow.'</span><'.$heading.'>'.$title.'</'.$heading.'><p>'.$subtitle.'</p>'.$quote_html.($buttons?'<div class="md-gb-hero-actions">'.$buttons.'</div>':'').'</div>';
  return '<section class="md-gb-hero'.($media?' md-gb-hero-split':'').'"><div class="container">'.$copy.$media.'</div></section>';
 }
 if ($name === 'morvarid/welcome') {
  $body = trim((string)($attributes['body'] ?? ''));
  return '<section class="md-home-welcome"><div class="container md-home-narrow">'
    .'<span class="md-home-welcome-symbol" aria-hidden="true">✦</span>'
    .'<h2>'.$title.'</h2>'
    .($body!==''?'<p>'.nl2br(esc_html($body)).'</p>':'')
    .'</div></section>';
 }
 if ($name === 'morvarid/story') {
  $body = trim((string)($attributes['body'] ?? ''));
  $image = absint($attributes['imageId'] ?? 0);
  if (!$image && !empty($attributes['useHomePhoto'])) {
    $image = absint(md_theme_option('home_person_image_id',0));
  }
  $image_html = $image && wp_attachment_is_image($image)
    ? '<figure class="md-home-story-photo">'.wp_get_attachment_image($image,'large',false,array('loading'=>'lazy','decoding'=>'async')).'</figure>'
    : '';
  return '<section class="md-home-story-section"><div class="container"><div class="md-home-story'.($image_html?' md-home-story-split':'').'">'
    .'<div class="md-home-story-copy"><h2>'.$title.'</h2>'
    .($body!==''?'<p>'.nl2br(esc_html($body)).'</p>':'')
    .'</div>'.$image_html.'</div></div></section>';
 }
 if ($name === 'morvarid/values') {
  $items = isset($attributes['items']) && is_array($attributes['items']) ? array_slice($attributes['items'],0,8) : array();
  $subtitle = trim((string)($attributes['subtitle'] ?? ''));
  $out = '<section class="md-home-values-section"><div class="container"><div class="md-home-values-panel">'
    .'<div class="md-home-values-heading"><h2>'.$title.'</h2>'
    .($subtitle!==''?'<p>'.esc_html($subtitle).'</p>':'').'</div><div class="md-home-values-list">';
  $count=0;
  foreach($items as $item) {
   if (!is_array($item)) continue;
   $item_title=trim((string)($item['title']??''));
   $item_body=trim((string)($item['text']??''));
   if ($item_title==='' && $item_body==='') continue;
   ++$count;
   $out.='<article class="md-home-value"><span class="md-home-value-symbol" aria-hidden="true">'.esc_html((string)$count).'</span>'
     .'<div>'.($item_title!==''?'<h3>'.esc_html($item_title).'</h3>':'')
     .($item_body!==''?'<p>'.esc_html($item_body).'</p>':'').'</div></article>';
  }
  return $out.'</div></div></div></section>';
 }
 if ($name === 'morvarid/content-grid') {
  $allowed=['post','md_course','md_lesson','md_podcast','md_resource'];
  if (($attributes['postType'] ?? '') === 'md_service') return '';
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
  $labels=['post'=>'مجله','md_course'=>'دوره آموزشی','md_lesson'=>'جلسه','md_podcast'=>'پادکست','md_resource'=>'منبع رایگان'];
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
// Editor script dependencies, minus anything a plugin or an old WordPress build
// has not registered; a missing dependency would otherwise stop the whole file
// from ever loading and hide every theme block.
function md_editor_script_deps() {
 $deps=['wp-blocks','wp-element','wp-components','wp-block-editor','wp-server-side-render','wp-media'];
 if(!function_exists('wp_script_is'))return $deps;
 return array_values(array_filter($deps,function($dep){return wp_script_is($dep,'registered')||wp_script_is($dep,'enqueued');}));
}
add_action('init', function() {
 $script='md-design-editor';
 wp_register_script($script,get_template_directory_uri().'/assets/js/gutenberg-blocks.js',md_editor_script_deps(),MD_THEME_VERSION,true);
 $blocks=[
  'hero'=>['eyebrow'=>'مروارید درون','title'=>'جایی برای کشف خود','subtitle'=>'رشد، آگاهی و ارتباط عمیق‌تر','buttonLabel'=>'شروع کنید','buttonUrl'=>'','secondaryLabel'=>'','secondaryUrl'=>'','imageId'=>0,'artQuote'=>'','quote'=>''],
  'welcome'=>['title'=>'به مروارید درون خوش آمدید','body'=>'فضایی برای مکث، گفت‌وگو و کشف مسیرهای تازه.'],
  'story'=>['title'=>'اینجا چگونه همراهت هستیم؟','body'=>'با شنیدن، پرسیدن و همراهی؛ بدون قضاوت و نسخه‌های آماده.','imageId'=>0,'useHomePhoto'=>false],
  'values'=>['title'=>'ارزش‌های مسیر ما','subtitle'=>'پایه‌های گفت‌وگو و یادگیری','items'=>[
    ['title'=>'شنیدن و احترام','text'=>'فرصتی برای شنیدن نگاه‌های متفاوت.'],
    ['title'=>'کشف توانمندی','text'=>'دیدن ظرفیت‌هایی که شاید کمتر به آن‌ها توجه شده است.'],
    ['title'=>'انتخاب آگاهانه','text'=>'پیدا کردن گام‌های عملی متناسب با زندگی خود.'],
  ]],
  'content-grid'=>['title'=>'تازه‌ترین مطالب','subtitle'=>'','postType'=>'post','count'=>3,'columns'=>3,'categorySlug'=>'','linkLabel'=>'','linkUrl'=>'','background'=>''],
  'action'=>['title'=>'برای گفت‌وگو آماده‌ای؟','subtitle'=>'با یک قدم کوچک شروع کن','buttonLabel'=>'رزرو جلسه','buttonUrl'=>''],
  'download'=>['title'=>'کاربرگ آموزشی','description'=>'فایل کمکی برای یادگیری','fileUrl'=>'','buttonLabel'=>'دریافت فایل'],
  'accordion'=>['title'=>'پرسش‌های متداول','items'=>[['q'=>'پرسش اول','a'=>'پاسخ پرسش اول']],'openFirst'=>false,'body'=>''],
 ];
 foreach($blocks as $slug=>$fields){$attributes=[];foreach($fields as $field=>$default)$attributes[$field]=['type'=>(is_int($default)?'number':(is_bool($default)?'boolean':(is_array($default)?'array':'string'))),'default'=>$default];
 register_block_type('morvarid/'.$slug,['api_version'=>2,'editor_script_handles'=>[$script],'attributes'=>$attributes,'render_callback'=>'md_design_block_render']);}
});
add_action('enqueue_block_assets', function(){wp_enqueue_style('md-gutenberg-shared',get_template_directory_uri().'/assets/css/gutenberg-blocks.css',[], MD_THEME_VERSION);wp_enqueue_style('md-forms',get_template_directory_uri().'/assets/css/forms.css',['md-gutenberg-shared'], MD_THEME_VERSION);});
// Safety net: the block scripts are declared as editor_script handles too, but
// enqueueing them here guarantees the inserter sees every theme block even if a
// caching or security plugin strips the extra editor handles.
add_action('enqueue_block_editor_assets', function(){foreach(['md-design-editor','md-blocks-editor'] as $handle){if(wp_script_is($handle,'registered'))wp_enqueue_script($handle);}});
// Second net: enqueue the same handles on the post editor screens, before the
// block editor boots, so a stripped editor_script handle can never hide blocks.
add_action('admin_enqueue_scripts', function(){
 if(!function_exists('get_current_screen'))return;
 $screen=get_current_screen();
 if(!$screen||!in_array($screen->base,['post','post-new'],true))return;
 $md_deps=md_editor_script_deps();
 $md_scripts=['md-design-editor'=>'gutenberg-blocks.js','md-blocks-editor'=>'blocks.js'];
 foreach($md_scripts as $md_handle=>$md_file){
  if(wp_script_is($md_handle,'registered')){wp_enqueue_script($md_handle);}
  else{wp_enqueue_script($md_handle.'-direct',MD_THEME_URI.'/assets/js/'.$md_file,$md_deps,MD_THEME_VERSION,true);}
 }
});
// Last resort: print the same two files inline on editor screens. Some hosts,
// CDNs and security plugins block or replace theme assets inside wp-admin; the
// JS guards prevent double registration when the enqueued copy does load.
add_action('admin_print_footer_scripts', function(){
 if(!function_exists('get_current_screen'))return;
 $screen=get_current_screen();
 if(!$screen||!in_array($screen->base,['post','post-new'],true))return;
 if(!use_block_editor_for_post_type($screen->post_type))return;
 if(!apply_filters('md_inline_editor_blocks',true))return;
 foreach(['gutenberg-blocks.js','blocks.js'] as $md_file){
  $md_path=MD_THEME_DIR.'/assets/js/'.$md_file;
  if(!is_readable($md_path))continue;
  $md_js=file_get_contents($md_path);
  if(!$md_js)continue;
  $md_js=str_ireplace('</script','<\/script',$md_js);
  echo '<script id="md-inline-'.esc_attr(str_replace('.','-',$md_file)).'">'."\n".$md_js."\n".'</script>'."\n";
 }
},99);
// Collect early JavaScript errors on editor screens so the diagnostic panel can
// show them without the owner opening the browser console.
add_action('admin_head', function(){
 if(!function_exists('get_current_screen'))return;
 $screen=get_current_screen();
 if(!$screen||!in_array($screen->base,['post','post-new'],true))return;
 if(!use_block_editor_for_post_type($screen->post_type))return;
 echo '<script>window.__mdErrors=[];window.addEventListener("error",function(e){try{window.__mdErrors.push((e.filename||"").split("/").pop()+":"+(e.lineno||0)+" — "+(e.message||""));}catch(x){}},true);window.addEventListener("unhandledrejection",function(e){try{window.__mdErrors.push("promise: "+((e.reason&&e.reason.message)||e.reason));}catch(x){}},true);</script>'."\n";
},1);
// Diagnostic panel: if the theme blocks are still missing from the editor a few
// seconds after load, show a small Persian box with the browser-side state and
// the real HTTP status of both bundles. Invisible on a healthy install.
add_action('admin_print_footer_scripts', function(){
 if(!function_exists('get_current_screen'))return;
 $screen=get_current_screen();
 if(!$screen||!in_array($screen->base,['post','post-new'],true))return;
 if(!use_block_editor_for_post_type($screen->post_type))return;
 if(!apply_filters('md_editor_block_diagnostics',true))return;
 $md_data=wp_json_encode([
  'probe'=>'morvarid/hero',
  'files'=>[
   ['name'=>'gutenberg-blocks.js','url'=>MD_THEME_URI.'/assets/js/gutenberg-blocks.js'],
   ['name'=>'blocks.js','url'=>MD_THEME_URI.'/assets/js/blocks.js'],
  ],
 ]);
 $md_diag=<<<'JS'
(function(){
 var D = /*DATA*/;
 function esc(v){return String(v==null?'':v).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
 function say(html){
  var box=document.getElementById('md-block-diagnosis');
  if(!box){box=document.createElement('div');box.id='md-block-diagnosis';box.setAttribute('dir','rtl');
   box.style.cssText='position:fixed;inset-inline-start:16px;bottom:16px;max-width:460px;max-height:70vh;overflow:auto;z-index:100000;background:#fffdfa;border:1px solid #c0392b;border-radius:10px;box-shadow:0 12px 30px rgba(0,0,0,.18);padding:14px 16px;font:13px/1.9 Tahoma,sans-serif;color:#233b35';
   document.body.appendChild(box);}
  box.innerHTML=html;
 }
 function row(html){return '<div style="margin:2px 0">'+html+'</div>';}
 function tags(file){
  var ext=document.querySelectorAll('script[src*="'+file+'"]').length;
  var inl=document.getElementById('md-inline-'+file.replace(/\./g,'-'))?1:0;
  return ext+' / '+inl;
 }
 function perf(url){
  try{
   var list=performance.getEntriesByType('resource').filter(function(e){return e.name.indexOf(url)>-1;});
   if(!list.length)return 'بارگذاری نشده';
   var e=list[list.length-1];
   return (e.responseStatus?('HTTP '+e.responseStatus):('بارگذاری شد ('+Math.round(e.duration)+'ms)'));
  }catch(x){return 'نامشخص';}
 }
 function check(){
  var wp=window.wp||{};
  var types=(wp.blocks&&typeof wp.blocks.getBlockTypes==='function')?wp.blocks.getBlockTypes():[];
  var mine=types.filter(function(b){return b&&b.name&&b.name.indexOf('morvarid/')===0;}).length;
  if(wp.blocks&&typeof wp.blocks.getBlockType==='function'&&wp.blocks.getBlockType(D.probe)){
   if(window.console)console.log('[MD] theme blocks registered:',mine);return;
  }
  if(window.console)console.warn('[MD] theme blocks missing');
  var head='<strong style="color:#c0392b">بلوک‌های قالب مروارید درون در این صفحه ثبت نشده‌اند.</strong>'
   +'<div style="margin:4px 0 8px;font-size:12px;color:#60716a">این کادر را اسکرین‌شات بگیرید و برای پشتیبانی بفرستید.</div>';
  var state=row('wp: <b>'+(window.wp?'هست':'نیست')+'</b> · wp.blocks: <b>'+(wp.blocks?'هست':'نیست')+'</b> · wp.blockEditor: <b>'+(wp.blockEditor?'هست':'نیست')+'</b> · wp.components: <b>'+(wp.components?'هست':'نیست')+'</b> · serverSideRender: <b>'+(wp.serverSideRender?'هست':'نیست')+'</b>');
  var reg=row('بلوک‌های ثبت‌شده از قالب: <b>'+mine+'</b> از ۱۷');
  var t1=row('<span style="font-family:monospace;direction:ltr;display:inline-block">blocks.js</span> — تگ اسکریپت (خارجی/درون‌خطی): <b>'+tags('blocks.js')+'</b> — '+perf('blocks.js'));
  var t2=row('<span style="font-family:monospace;direction:ltr;display:inline-block">gutenberg-blocks.js</span> — تگ اسکریپت (خارجی/درون‌خطی): <b>'+tags('gutenberg-blocks.js')+'</b> — '+perf('gutenberg-blocks.js'));
  var errs=(window.__mdErrors||[]).slice(0,4);
  var errBox=errs.length?row('<b style="color:#c0392b">خطاهای جاوااسکریپت:</b><div style="font-family:monospace;direction:ltr;text-align:left;font-size:11px">'+errs.map(esc).join('<br>')+'</div>'):row('<span style="color:#60716a">خطای جاوااسکریپتی ثبت نشده است.</span>');
  say(head+'<div style="background:#f6efe6;border-radius:8px;padding:8px 10px;margin-bottom:8px">'+state+reg+t1+t2+'</div>'+errBox
   +'<div style="margin-top:8px;font-size:12px;color:#60716a">اگر این کادر را نمی‌بینید، یعنی افزونه‌ای اسکریپت‌های داخل پیشخوان را حذف می‌کند.</div>');
 }
 if(document.readyState==='complete'){setTimeout(check,1500);}
 else{window.addEventListener('load',function(){setTimeout(check,1500);});}
})();
JS;
JS;
 echo '<script id="md-block-diagnosis-script">'."\n".str_replace('/*DATA*/',$md_data,$md_diag)."\n".'</script>'."\n";
},120);
// If a plugin (Classic Editor, page-builder switchers…) turned the block editor
// off for pages or posts, say so instead of leaving the owner with empty screens.
add_action('admin_notices', function(){
 if(!function_exists('get_current_screen'))return;
 $screen=get_current_screen();
 if(!$screen||!in_array($screen->base,['post','post-new'],true))return;
 if(!in_array($screen->post_type,['page','post'],true))return;
 if(use_block_editor_for_post_type($screen->post_type))return;
 echo '<div class="notice notice-warning"><p><strong>مروارید درون:</strong> ویرایشگر بلوک برای این نوع محتوا غیرفعال است؛ بنابراین بلوک‌های گوتنبرگ دیده نمی‌شوند. اگر افزونهٔ «Classic Editor» یا مشابه آن را فعال کرده‌اید، غیرفعالش کنید.</p></div>';
});


// Do not expose legacy coaching service menu links; keep stored posts intact.
add_filter('wp_nav_menu_objects',function($items){
 return array_values(array_filter($items,function($item){
  if (($item->object ?? '') === 'md_service' || ($item->object ?? '') === 'md_service_cat') return false;
  $path=wp_parse_url((string)($item->url ?? ''),PHP_URL_PATH);
  return !is_string($path) || !preg_match('~/(?:services)(?:/|$)~i',$path);
 }));
},20);

// Site-matched styles for the Appearance > Widgets block editor and Customizer.
// The normal post editor gets the same sheets via add_editor_style().
add_action('enqueue_block_assets',function(){
 if (!is_admin()) return;
 wp_enqueue_style('md-widget-preview-site',MD_THEME_URI.'/assets/css/sidebar.css',array(),MD_THEME_VERSION);
},20);
add_action('admin_enqueue_scripts',function($hook){
 if(!in_array($hook,array('widgets.php','customize.php'),true))return;
 wp_enqueue_style('md-widget-editor-base',MD_THEME_URI.'/assets/css/main.css',array(),MD_THEME_VERSION);
 wp_enqueue_style('md-widget-editor-cards',MD_THEME_URI.'/assets/css/sidebar.css',array('md-widget-editor-base'),MD_THEME_VERSION);
 wp_enqueue_style('md-widget-editor-fonts',MD_THEME_URI.'/assets/css/fonts.css',array(),MD_THEME_VERSION);
});
