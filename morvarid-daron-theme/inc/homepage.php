<?php
/**
 * Editable homepage.
 *
 * The theme ships a «خانه» page whose blocks are the whole front page. Every
 * element of the homepage is therefore a block: the editor opens the page,
 * edits the hero, the section titles, the links, the counts and the colors,
 * and the site updates without touching code.
 *
 * @package Morvarid_Daron
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ID of the page used as the front page, when it is still published.
 *
 * @return int
 */
function md_home_page_id() {
    if ('page' !== get_option('show_on_front')) {
        return 0;
    }

    $page_id = absint(get_option('page_on_front'));
    if (!$page_id) {
        return 0;
    }

    $status = get_post_status($page_id);
    if ('publish' !== $status) {
        return 0;
    }

    return $page_id;
}

/**
 * Admin edit link of the homepage, or an empty string.
 *
 * @return string
 */
function md_home_page_edit_url() {
    $page_id = md_home_page_id();
    return $page_id ? (string) get_edit_post_link($page_id) : '';
}

/**
 * Serialize one dynamic block into post content.
 *
 * @param string $name  Block name without the morvarid/ prefix.
 * @param array  $attrs Block attributes.
 * @return string
 */
function md_home_block($name, $attrs = array()) {
    $json = $attrs ? ' ' . wp_json_encode($attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    return '<!-- wp:' . $name . $json . ' /-->';
}

/**
 * Default homepage layout.
 *
 * It mirrors the original curated homepage, but every section is now a block
 * that can be edited, removed or duplicated in the editor.
 *
 * @return string
 */
function md_home_default_content() {
    $blog_url = get_option('page_for_posts')
        ? get_permalink((int) get_option('page_for_posts'))
        : home_url('/');

    $services_url = get_post_type_archive_link('md_service');
    $services_url = $services_url ? $services_url : home_url('/services/');

    $courses_url = get_post_type_archive_link('md_course');
    $courses_url = $courses_url ? $courses_url : home_url('/academy/courses/');

    $blocks = array(
        md_home_block(
            'morvarid/hero',
            array(
                'eyebrow'        => '✧ رشد، آگاهی، ارتباط',
                'title'          => 'جایی برای کشف خود، فهم نوجوان و ساختن رابطه‌ای عمیق‌تر',
                'subtitle'       => 'فضایی برای آگاهی، رشد و همراهی والدین و نوجوانان در مسیر گفت‌وگو و رابطه‌ای عمیق‌تر.',
                'buttonLabel'    => 'من والد هستم',
                'buttonUrl'      => '/category/parents/',
                'secondaryLabel' => 'من نوجوان هستم',
                'secondaryUrl'   => '/category/teens/',
                'artQuote'       => 'هر گفت‌وگوی خوب، آغاز یک کشف تازه است.',
            )
        ),
        md_home_block(
            'morvarid/features',
            array(
                'items' => array(
                    array(
                        'icon'  => '▤',
                        'title' => 'مجله رشد',
                        'text'  => 'مقاله، داستان و راهکارهای کاربردی',
                        'url'   => $blog_url,
                    ),
                    array(
                        'icon'  => '❀',
                        'title' => 'کوچینگ فردی',
                        'text'  => 'همراهی در مسیر شناخت و تغییر',
                        'url'   => $services_url,
                    ),
                    array(
                        'icon'  => '✦',
                        'title' => 'آکادمی آموزشی',
                        'text'  => 'دوره و کارگاه برای رشد آگاهانه',
                        'url'   => $courses_url,
                    ),
                ),
            )
        ),
        md_home_block(
            'morvarid/content-grid',
            array(
                'title'     => 'مجله مروارید درون',
                'subtitle'  => 'خواندنی‌هایی برای شناخت، درک بیشتر و رابطه‌ای عمیق‌تر',
                'postType'  => 'post',
                'count'     => 3,
                'columns'   => 3,
                'linkLabel' => 'همه مطالب',
            )
        ),
        md_home_block(
            'morvarid/service-grid',
            array(
                'title'      => 'کوچینگ مروارید درون',
                'subtitle'   => 'فضایی امن برای گفت‌وگو، روشن‌شدن انتخاب‌ها و حرکت به سمت اهداف',
                'count'      => 3,
                'linkLabel'  => 'همه خدمات',
                'background' => 'warm',
            )
        ),
        md_home_block(
            'morvarid/course-grid',
            array(
                'title'     => 'آکادمی مروارید درون',
                'subtitle'  => 'کارگاه‌ها، دوره‌ها و وبینارهای آموزشی',
                'count'     => 3,
                'columns'   => 3,
                'linkLabel' => 'همه دوره‌ها',
            )
        ),
        md_home_block(
            'morvarid/challenge-grid',
            array(
                'title'      => 'چالش‌های مروارید درون',
                'subtitle'   => 'چالش‌های چندروزه برای ساختن عادت‌های تازه و قدم‌های کوچک و پیوسته',
                'count'      => 3,
                'status'     => 'current',
                'linkLabel'  => 'همه چالش‌ها',
                'background' => 'warm',
            )
        ),
        md_home_block(
            'morvarid/resource-grid',
            array(
                'title'      => 'تمرین‌ها و منابع رایگان',
                'subtitle'   => 'ابزارهایی کوچک برای قدم‌های واقعی',
                'count'      => 3,
                'linkLabel'  => 'همه منابع',
                'background' => 'mint',
            )
        ),
        md_home_block(
            'morvarid/media-grid',
            array(
                'title'      => 'کتابخانه ویدیو و پادکست',
                'subtitle'   => 'محتوای چندرسانه‌ای و جلسه‌های آموزشی در مسیر یادگیری',
                'count'      => 3,
                'linkLabel'  => 'همه پادکست‌ها',
                'background' => 'cream',
            )
        ),
        md_home_block(
            'morvarid/newsletter',
            array(
                'eyebrow'     => 'همراه مسیر رشد',
                'title'       => 'عضو خانواده مروارید درون شوید',
                'text'        => 'از تازه‌ترین مطالب، کارگاه‌ها و تمرین‌های رایگان باخبر شوید.',
                'buttonLabel' => 'عضویت در خبرنامه',
                'buttonUrl'   => get_theme_mod('md_newsletter_url', '/newsletter/'),
            )
        ),
    );

    /**
     * Filter the blocks stored in the newly created homepage.
     *
     * @param string $content Serialized blocks.
     */
    return apply_filters('md_home_default_content', implode("\n\n", $blocks));
}

/**
 * Create the «خانه» page and make it the static front page.
 *
 * An existing static front page is never replaced: the editor's setup always
 * wins. The page is created once and can then be edited like any other page.
 *
 * @return int Page ID, or 0 on failure.
 */
function md_create_home_page() {
    $existing = md_home_page_id();
    if ($existing) {
        // A front page that is still empty gets the default layout once.
        if ('' === trim((string) get_post_field('post_content', $existing))) {
            wp_update_post(
                array(
                    'ID'           => $existing,
                    'post_content' => md_home_default_content(),
                )
            );
        }
        update_option('md_home_page_created', 1);
        return $existing;
    }

    $page = get_page_by_path('home');
    $is_blog_page = $page instanceof WP_Post && (int) get_option('page_for_posts') === (int) $page->ID;
    if ($page instanceof WP_Post && 'trash' !== $page->post_status && !$is_blog_page) {
        $page_id = (int) $page->ID;
        if ('' === trim((string) $page->post_content)) {
            wp_update_post(
                array(
                    'ID'           => $page_id,
                    'post_content' => md_home_default_content(),
                )
            );
        }
    } else {
        $page_id = wp_insert_post(
            array(
                'post_type'    => 'page',
                'post_status'  => 'publish',
                'post_title'   => 'خانه',
                'post_name'    => 'home',
                'post_content' => md_home_default_content(),
            ),
            true
        );
    }

    if (is_wp_error($page_id) || !$page_id) {
        return 0;
    }

    update_option('show_on_front', 'page');
    update_option('page_on_front', (int) $page_id);
    update_option('md_home_page_created', 1);

    return (int) $page_id;
}
add_action('after_switch_theme', 'md_create_home_page');

/**
 * One-time setup for sites that already had the theme active.
 *
 * Without it an existing installation would keep the hard-coded homepage and
 * the owner would have nothing to edit.
 */
function md_maybe_create_home_page() {
    if (get_option('md_home_page_created')) {
        return;
    }
    if (!current_user_can('manage_options')) {
        return;
    }

    md_create_home_page();
}
add_action('admin_init', 'md_maybe_create_home_page');
