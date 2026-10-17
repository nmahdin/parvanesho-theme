<!doctype html><html <?php language_attributes(); ?> dir="rtl"><head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(); ?>><?php wp_body_open(); ?><a class="skip-link" href="#main">رفتن به محتوای اصلی</a>
<header class="site-header"><div class="container header-inner"><a class="brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="مروارید درون">
<?php if(has_custom_logo()){ $logo_id=get_theme_mod('custom_logo');echo wp_get_attachment_image($logo_id,'medium',false,['class'=>'brand-logo']); }else{echo '<span class="brand-icon" aria-hidden="true">◈</span><span><strong>مروارید درون</strong><small>رشد آگاهانه، رابطه‌ای عمیق‌تر</small></span>';} ?></a>
<button class="menu-toggle" aria-expanded="false" aria-controls="site-navigation" aria-label="باز کردن فهرست">☰</button>
<nav id="site-navigation" class="site-navigation" aria-label="فهرست اصلی"><?php wp_nav_menu(['theme_location'=>'primary','menu_class'=>'nav-list','container'=>false,'fallback_cb'=>'md_fallback_menu']); ?></nav>
<a class="btn btn-primary header-cta" href="<?php echo esc_url(get_theme_mod('md_booking_url','/contact/')); ?>">رزرو جلسه ↖</a></div></header>
