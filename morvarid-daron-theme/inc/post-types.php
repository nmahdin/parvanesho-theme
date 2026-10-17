<?php
/**
 * Native content types and taxonomies.
 *
 * The theme used to rely on an external plugin for the academy content types.
 * They are registered here so the theme works on its own.
 *
 * @package Morvarid_Daron
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the theme content types.
 */
function md_register_content_types() {
    $common = array(
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_rest'       => true,
        'capability_type'    => 'post',
        'map_meta_cap'       => true,
        'hierarchical'       => false,
        'supports'           => array('title', 'editor', 'excerpt', 'thumbnail', 'revisions'),
    );

    if (!post_type_exists('md_course')) {
        register_post_type('md_course', array_merge($common, array(
            'labels'       => md_post_type_labels('دوره', 'دوره‌ها', 'آکادمی'),
            'menu_icon'    => 'dashicons-welcome-learn-more',
            'show_in_menu' => 'edit.php?post_type=md_academy_item',
            'has_archive'  => 'academy/courses',
            'rewrite'      => array('slug' => 'academy/courses', 'with_front' => false),
            'taxonomies'   => array('md_academy_cat'),
        )));
    }

    if (!post_type_exists('md_lesson')) {
        register_post_type('md_lesson', array_merge($common, array(
            'labels'       => md_post_type_labels('جلسه', 'جلسه‌ها', 'جلسه‌ها'),
            'menu_icon'    => 'dashicons-video-alt3',
            'show_in_menu' => false,
            'has_archive'  => 'academy/lessons',
            'rewrite'      => array('slug' => 'academy/lessons', 'with_front' => false),
            'taxonomies'   => array('md_academy_cat'),
        )));
    }

    if (!post_type_exists('md_podcast')) {
        register_post_type('md_podcast', array_merge($common, array(
            'labels'       => md_post_type_labels('پادکست', 'پادکست‌ها', 'پادکست‌ها'),
            'menu_icon'    => 'dashicons-microphone',
            'show_in_menu' => false,
            'has_archive'  => 'academy/podcasts',
            'rewrite'      => array('slug' => 'academy/podcasts', 'with_front' => false),
            'taxonomies'   => array('md_academy_cat'),
        )));
    }

    if (!post_type_exists('md_resource')) {
        register_post_type('md_resource', array_merge($common, array(
            'labels'       => md_post_type_labels('تمرین یا منبع', 'تمرین‌ها و منابع', 'منابع'),
            'menu_icon'    => 'dashicons-download',
            'show_in_menu' => 'edit.php?post_type=md_course',
            'has_archive'  => 'resources',
            'rewrite'      => array('slug' => 'resources', 'with_front' => false),
            'taxonomies'   => array('md_resource_cat'),
        )));
    }

    if (!post_type_exists('md_service')) {
        register_post_type('md_service', array_merge($common, array(
            'labels'       => md_post_type_labels('خدمت', 'خدمات', 'کوچینگ'),
            'menu_icon'    => 'dashicons-heart',
            'menu_position'=> 26,
            'has_archive'  => 'services',
            'rewrite'      => array('slug' => 'services', 'with_front' => false),
            'taxonomies'   => array('md_service_cat'),
        )));
    }

    if (!post_type_exists('md_challenge')) {
        register_post_type('md_challenge', array(
            'labels'             => md_post_type_labels('چالش', 'چالش‌ها', 'چالش‌ها'),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => true,
            'menu_icon'          => 'dashicons-flag',
            'menu_position'      => 6,
            'capability_type'    => 'post',
            'map_meta_cap'       => true,
            'hierarchical'       => false,
            'has_archive'        => 'challenges',
            'rewrite'            => array('slug' => 'challenges', 'with_front' => false),
            'supports'           => array('title', 'editor', 'excerpt', 'thumbnail', 'revisions'),
            'taxonomies'         => array('md_challenge_cat'),
        ));
    }

    /*
     * Coaching requests are stored privately. The form is a Gutenberg block on
     * the «فرم ثبت درخواست» page, so no new request can be added manually.
     */
    if (!post_type_exists('md_request')) {
        register_post_type('md_request', array(
            'labels'             => md_post_type_labels('درخواست', 'درخواست‌ها', 'درخواست‌ها'),
            'public'             => false,
            'publicly_queryable' => false,
            'exclude_from_search'=> true,
            'show_ui'            => true,
            'show_in_menu'       => 'edit.php?post_type=md_service',
            'show_in_rest'       => false,
            'menu_icon'          => 'dashicons-email-alt',
            'capability_type'    => 'post',
            'map_meta_cap'       => true,
            'hierarchical'       => false,
            'supports'           => array('title'),
            'capabilities'       => array('create_posts' => 'do_not_allow'),
            'rewrite'            => false,
            'query_var'          => false,
        ));
    }

    /*
     * Challenge days are deliberately hidden from the admin menu. They are
     * managed from the parent challenge screen, exactly as the requirements
     * document asks for.
     */
    if (!post_type_exists('md_challenge_day')) {
        register_post_type('md_challenge_day', array(
            'labels'              => md_post_type_labels('روز چالش', 'روزهای چالش', 'روزهای چالش'),
            'public'              => false,
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'show_ui'             => false,
            'show_in_menu'        => false,
            'show_in_rest'        => false,
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'hierarchical'        => false,
            'supports'            => array('title', 'editor', 'revisions'),
            'rewrite'             => false,
            'query_var'           => false,
        ));
    }
}
add_action('init', 'md_register_content_types', 5);

/**
 * Register taxonomies used by the content types.
 */
function md_register_content_taxonomies() {
    $common = array(
        'public'            => true,
        'show_ui'           => true,
        'show_in_rest'      => true,
        'hierarchical'      => true,
        'show_admin_column' => true,
        'query_var'         => true,
    );

    if (!taxonomy_exists('md_academy_cat')) {
        register_taxonomy('md_academy_cat', array('md_course', 'md_lesson', 'md_podcast'), array_merge($common, array(
            'labels'       => md_taxonomy_labels('دسته دوره و آکادمی', 'دسته‌های دوره و آکادمی'),
            'rewrite'      => array('slug' => 'academy/category', 'with_front' => false),
            'show_in_menu' => 'edit.php?post_type=md_course',
        )));
    }

    if (!taxonomy_exists('md_resource_cat')) {
        register_taxonomy('md_resource_cat', array('md_resource'), array_merge($common, array(
            'labels'       => md_taxonomy_labels('دسته منبع', 'دسته‌های منابع'),
            'rewrite'      => array('slug' => 'resources/category', 'with_front' => false),
            'show_in_menu' => 'edit.php?post_type=md_course',
        )));
    }

    if (!taxonomy_exists('md_service_cat')) {
        register_taxonomy('md_service_cat', array('md_service'), array_merge($common, array(
            'labels'       => md_taxonomy_labels('دسته خدمت', 'دسته‌های خدمات'),
            'rewrite'      => array('slug' => 'services/category', 'with_front' => false),
            'show_in_menu' => 'edit.php?post_type=md_service',
        )));
    }

    if (!taxonomy_exists('md_challenge_cat')) {
        register_taxonomy('md_challenge_cat', array('md_challenge'), array_merge($common, array(
            'labels'       => md_taxonomy_labels('دسته چالش', 'دسته‌بندی چالش‌ها'),
            'rewrite'      => array('slug' => 'challenges/category', 'with_front' => false),
            'show_in_menu' => 'edit.php?post_type=md_challenge',
        )));
    }
}
add_action('init', 'md_register_content_taxonomies', 4);

/**
 * Build a standard labels array for a post type.
 *
 * @param string $singular Singular label.
 * @param string $plural   Plural label.
 * @param string $menu     Menu label.
 * @return array
 */
function md_post_type_labels($singular, $plural, $menu) {
    return array(
        'name'               => $plural,
        'singular_name'      => $singular,
        'menu_name'          => $menu,
        'all_items'          => 'همه ' . $plural,
        'add_new'            => 'افزودن جدید',
        'add_new_item'       => 'افزودن ' . $singular . ' جدید',
        'edit_item'          => 'ویرایش ' . $singular,
        'new_item'           => $singular . ' جدید',
        'view_item'          => 'مشاهده ' . $singular,
        'view_items'         => 'مشاهده ' . $plural,
        'search_items'       => 'جست‌وجوی ' . $plural,
        'not_found'          => $singular . 'ی پیدا نشد',
        'not_found_in_trash' => $singular . 'ی در زباله‌دان پیدا نشد',
        'archives'           => 'آرشیو ' . $plural,
        'featured_image'     => 'تصویر شاخص',
        'set_featured_image' => 'تعیین تصویر شاخص',
        'remove_featured_image' => 'حذف تصویر شاخص',
    );
}

/**
 * Build a standard labels array for a taxonomy.
 *
 * @param string $singular Singular label.
 * @param string $plural   Plural label.
 * @return array
 */
function md_taxonomy_labels($singular, $plural) {
    return array(
        'name'          => $plural,
        'singular_name' => $singular,
        'search_items'  => 'جست‌وجوی ' . $plural,
        'all_items'     => 'همه ' . $plural,
        'parent_item'   => $singular . ' والد',
        'edit_item'     => 'ویرایش ' . $singular,
        'update_item'   => 'به‌روزرسانی ' . $singular,
        'add_new_item'  => 'افزودن ' . $singular . ' جدید',
        'new_item_name' => 'نام ' . $singular . ' جدید',
        'menu_name'     => $plural,
    );
}

/**
 * Flush rewrite rules once when the theme is activated so the new permalinks
 * work immediately.
 */
function md_flush_rewrite_rules() {
    md_register_content_taxonomies();
    md_register_content_types();
    flush_rewrite_rules();
}
add_action('after_switch_theme', 'md_flush_rewrite_rules');
