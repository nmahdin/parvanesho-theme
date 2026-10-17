<!doctype html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main">رفتن به محتوای اصلی</a>
<?php
$header_title = md_theme_option('header_title', 'مروارید درون');
$header_tagline = md_theme_option('header_tagline', 'رشد آگاهانه، رابطه‌ای عمیق‌تر');
$booking_label = md_theme_option('header_booking_label', 'رزرو جلسه');
$booking_url = md_theme_option('header_booking_url', md_booking_url());
$show_booking = md_theme_option('header_booking_show', '1') === '1';
$show_profile = md_theme_option('header_profile_show', '1') === '1';
$profile_url = '';
$profile_label = 'ورود / پروفایل';
if (function_exists('md_learning_current_learner_id') && class_exists('MD_Learning_Schema')) {
    $logged_in = md_learning_current_learner_id() > 0;
    $profile_url = MD_Learning_Schema::url($logged_in ? 'dashboard' : 'login');
    $profile_label = $logged_in ? 'پروفایل من' : 'ورود / پروفایل';
}
?>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php echo esc_attr($header_title); ?>">
            <?php $logo_id = absint(md_theme_option('header_logo_id', get_theme_mod('custom_logo'))); ?>
            <?php if ($logo_id && wp_get_attachment_image($logo_id, 'medium', false, array('class'=>'brand-logo'))) : ?>
                <?php echo wp_get_attachment_image($logo_id, 'medium', false, array('class'=>'brand-logo')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php else : ?>
                <span class="brand-icon" aria-hidden="true">◈</span>
            <?php endif; ?>
            <span class="brand-copy"><strong><?php echo esc_html($header_title); ?></strong>
                <?php if ($header_tagline !== '') : ?><small><?php echo esc_html($header_tagline); ?></small><?php endif; ?>
            </span>
        </a>
        <nav id="site-navigation" class="site-navigation" aria-label="فهرست اصلی">
            <?php wp_nav_menu(array('theme_location'=>'primary','menu_class'=>'nav-list','container'=>false,'fallback_cb'=>'md_fallback_menu')); ?>
        </nav>
        <div class="md-header-actions">
            <?php if ($show_booking && $booking_url) : ?>
                <a class="btn btn-primary header-cta" href="<?php echo esc_url($booking_url); ?>"><?php echo esc_html($booking_label); ?> ↖</a>
            <?php endif; ?>
            <?php if ($show_profile && $profile_url) : ?>
                <a class="md-header-profile" href="<?php echo esc_url($profile_url); ?>" aria-label="<?php echo esc_attr($profile_label); ?>">
                    <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="8" r="4"/><path d="M4.5 21v-2.5a7.5 7.5 0 0 1 15 0V21"/>
                    </svg>
                    <span><?php echo esc_html($profile_label); ?></span>
                </a>
            <?php endif; ?>
            <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-navigation" aria-label="باز کردن فهرست">☰</button>
        </div>
    </div>
</header>
