<?php
/**
 * Unified academy content editor. Legacy post types remain registered to
 * preserve existing URLs and course-builder relationships.
 */
if (!defined('ABSPATH')) exit;

add_action('init', function () {
    register_post_type('md_academy_item', array(
        'labels' => array(
            'name'=>'مطالب آموزشی', 'singular_name'=>'مطلب آموزشی',
            'menu_name'=>'آکادمی', 'all_items'=>'همه مطالب آموزشی',
            'add_new'=>'افزودن مطلب', 'add_new_item'=>'افزودن مطلب آموزشی',
            'edit_item'=>'ویرایش مطلب آموزشی',
        ),
        'public'=>true, 'show_in_rest'=>true, 'show_ui'=>true,
        'show_in_menu'=>true, 'menu_icon'=>'dashicons-welcome-learn-more',
        'menu_position'=>25, 'has_archive'=>'academy',
        'rewrite'=>array('slug'=>'academy/item','with_front'=>false),
        'supports'=>array('title','editor','thumbnail','excerpt','revisions'),
        'taxonomies'=>array('md_academy_cat'),
    ));
    register_taxonomy_for_object_type('md_academy_cat', 'md_academy_item');
}, 15);

add_action('add_meta_boxes_md_academy_item', function () {
    add_meta_box('md_academy_item_kind','نوع مطلب و تنظیمات رسانه','md_academy_item_metabox','md_academy_item','normal','high');
});
function md_academy_item_metabox($post) {
    wp_nonce_field('md_academy_item_save','md_academy_item_nonce');
    $kind = get_post_meta($post->ID,'_md_academy_item_kind',true) ?: 'article';
    $media = get_post_meta($post->ID,'_md_academy_item_media',true) ?: 'video';
    $url = get_post_meta($post->ID,'_md_academy_item_url',true);
    echo '<p><label for="md-academy-kind"><strong>قالب محتوای آموزشی</strong></label></p>';
    echo '<select id="md-academy-kind" name="md_academy_item_kind">';
    foreach(array('article'=>'مقاله','media'=>'ویدیو / پادکست','mixed'=>'ترکیبی') as $k=>$label)
        echo '<option value="'.esc_attr($k).'" '.selected($kind,$k,false).'>'.esc_html($label).'</option>';
    echo '</select>';
    echo '<div id="md-academy-media-fields">';
    echo '<p><label for="md-academy-media">نوع رسانه</label></p><select id="md-academy-media" name="md_academy_item_media">';
    foreach(array('video'=>'ویدیو','audio'=>'پادکست') as $k=>$label)
        echo '<option value="'.esc_attr($k).'" '.selected($media,$k,false).'>'.esc_html($label).'</option>';
    echo '</select>';
    echo '<p><label for="md-academy-url">آدرس فایل یا رسانه (از کتابخانه وردپرس نیز می‌توانید نشانی فایل را کپی کنید)</label></p>';
    echo '<input class="widefat" id="md-academy-url" type="url" name="md_academy_item_url" value="'.esc_attr($url).'" placeholder="https://">';
    echo '</div>';
    echo '<p class="description">متن، تصاویر و بلوک‌های محتوایی را در ویرایشگر اصلی همین صفحه درج و مرتب کنید. قالب ترکیبی، متن و رسانه را با هم نمایش می‌دهد.</p>';
    echo '<script>document.addEventListener("DOMContentLoaded",function(){var k=document.getElementById("md-academy-kind"),f=document.getElementById("md-academy-media-fields");function sync(){f.style.display=k.value==="article"?"none":"";}k.addEventListener("change",sync);sync();});</script>';
}
add_action('save_post_md_academy_item', function($id){
    if(!isset($_POST['md_academy_item_nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['md_academy_item_nonce'])),'md_academy_item_save') ||
        (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post',$id)) return;
    $kind=sanitize_key(wp_unslash($_POST['md_academy_item_kind'] ?? 'article'));
    $media=sanitize_key(wp_unslash($_POST['md_academy_item_media'] ?? 'video'));
    update_post_meta($id,'_md_academy_item_kind',in_array($kind,array('article','media','mixed'),true)?$kind:'article');
    update_post_meta($id,'_md_academy_item_media',in_array($media,array('audio','video'),true)?$media:'video');
    update_post_meta($id,'_md_academy_item_url',esc_url_raw(wp_unslash($_POST['md_academy_item_url'] ?? '')));
});

add_action('admin_menu',function(){
    // Preserve legacy content and course builder, but expose only courses in
    // their former menu. New content lives under the unified academy menu.
    remove_submenu_page('edit.php?post_type=md_course','edit.php?post_type=md_lesson');
    remove_submenu_page('edit.php?post_type=md_course','edit.php?post_type=md_podcast');
    remove_submenu_page('edit.php?post_type=md_course','edit.php?post_type=md_resource');
    remove_submenu_page('edit.php?post_type=md_course','edit-tags.php?taxonomy=md_resource_cat&post_type=md_resource');
    add_submenu_page('edit.php?post_type=md_academy_item','دسته‌بندی‌ها','دسته‌بندی‌ها','manage_categories','edit-tags.php?taxonomy=md_academy_cat&post_type=md_academy_item');
},100);

add_filter('manage_md_academy_item_posts_columns',function($cols){
    $cols['md_kind']='نوع مطلب';
    return $cols;
});
add_action('manage_md_academy_item_posts_custom_column',function($col,$id){
    if($col==='md_kind'){
        $types=array('article'=>'مقاله','media'=>'ویدیو / پادکست','mixed'=>'ترکیبی');
        $kind=get_post_meta($id,'_md_academy_item_kind',true);
        echo esc_html($types[$kind]??'مقاله');
    }
},10,2);
