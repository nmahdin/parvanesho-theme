<?php
if (!defined('ABSPATH')) exit;
add_action('customize_register',function($wp_customize){
 $wp_customize->add_section('md_home',['title'=>'مروارید درون | صفحه اصلی','priority'=>35]);
 $fields=[
 'hero_title'=>['عنوان اصلی','جایی برای کشف خود، فهم نوجوان و ساختن رابطه‌ای عمیق‌تر'],
 'hero_text'=>['متن معرفی','فضایی برای آگاهی، رشد و همراهی والدین و نوجوانان در مسیر گفت‌وگو و رابطه‌ای عمیق‌تر.'],
 'parent_url'=>['آدرس مسیر والدین','/category/parents/'],
 'teen_url'=>['آدرس مسیر نوجوانان','/category/teens/'],
 'booking_url'=>['آدرس درخواست جلسه','/contact/'],
 'newsletter_url'=>['آدرس فرم خبرنامه','/newsletter/'],
 'contact_email'=>['ایمیل تماس',''],
 'contact_phone'=>['شماره تماس',''],
 'instagram_url'=>['لینک اینستاگرام',''],
 'telegram_url'=>['لینک تلگرام',''],
 'bale_url'=>['لینک کانال بله',''],
 'eitaa_url'=>['لینک کانال ایتا',''],
 ];
 foreach($fields as $id=>$args){
  $url=in_array($id,['parent_url','teen_url','booking_url','newsletter_url','instagram_url','telegram_url','bale_url','eitaa_url'],true);
  $wp_customize->add_setting('md_'.$id,['default'=>$args[1],'sanitize_callback'=>$url?'esc_url_raw':'sanitize_text_field','transport'=>'refresh']);
  $wp_customize->add_control('md_'.$id,['label'=>$args[0],'section'=>'md_home','type'=>$id==='hero_text'?'textarea':'text']);
 }
 $wp_customize->add_setting('md_hero_image',['sanitize_callback'=>'absint']);
 $wp_customize->add_control(new WP_Customize_Media_Control($wp_customize,'md_hero_image',['label'=>'تصویر اصلی (ترجیحاً افقی)','section'=>'md_home','mime_type'=>'image']));
});
