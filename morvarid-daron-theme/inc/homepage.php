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
 * @param string $name  Full block name, e.g. morvarid/hero.
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
 * Give the blog a home when the front page becomes static.
 *
 * Without a posts page, «همه مطالب» would point back at the static homepage.
 * The page is only created when the site already has published posts and no
 * page named «مجله» or «بلاگ» exists, so nothing is ever overwritten.
 *
 * @return int Page ID, or 0 when not needed.
 */
function md_ensure_posts_page() {
    if (absint(get_option('page_for_posts'))) {
        return 0;
    }

    foreach (array('magazine', 'blog') as $slug) {
        if (get_page_by_path($slug) instanceof WP_Post) {
            return 0;
        }
    }

    $same_title = get_posts(
        array(
            'post_type'      => 'page',
            'post_status'    => 'any',
            'title'          => 'مجله',
            'posts_per_page' => 1,
            'fields'         => 'ids',
        )
    );
    if ($same_title) {
        return 0;
    }

    $page_id = wp_insert_post(
        array(
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => 'مجله',
            'post_name'    => 'magazine',
            'post_content' => '',
        ),
        true
    );

    if (is_wp_error($page_id) || !$page_id) {
        return 0;
    }

    update_option('page_for_posts', (int) $page_id);

    return (int) $page_id;
}

/**
 * Version of the automatic homepage setup.
 *
 * Bump it when the setup has to run once more on sites that already went
 * through an older version (the option stores the last version that ran).
 *
 * @return int
 */
function md_home_setup_version() {
    return 2;
}

/**
 * Whether a page already carries the theme homepage blocks.
 *
 * @param int $page_id Page ID.
 * @return bool
 */
function md_home_page_has_blocks($page_id) {
    $content = (string) get_post_field('post_content', $page_id);
    return false !== strpos($content, '<!-- wp:morvarid/');
}

/**
 * Whether content is effectively empty.
 *
 * A page saved in Gutenberg can contain empty block scaffolding while showing
 * nothing, so comments and tags are ignored before the check.
 *
 * @param string $content Post content.
 * @return bool
 */
function md_home_content_is_blank($content) {
    $content = preg_replace('/<!--.*?-->/s', '', (string) $content);
    return '' === trim(wp_strip_all_tags($content));
}

/**
 * Remember that the homepage setup is complete.
 */
function md_home_mark_done() {
    update_option('md_home_page_created', md_home_setup_version());
}

/**
 * Guarantee a block-driven homepage.
 *
 * - A front page that already contains theme blocks is left alone.
 * - An empty front page (including an empty Gutenberg canvas) receives the
 *   default layout, so the existing URL keeps working.
 * - Otherwise a «خانه» page with the full layout is created and used as the
 *   front page; an older front page with real content stays untouched.
 *
 * @return int Page ID, or 0 on failure.
 */
function md_create_home_page() {
    // A static front page needs a separate posts page to keep the magazine alive.
    if ('page' !== get_option('show_on_front')) {
        md_ensure_posts_page();
    }

    $front_id = md_home_page_id();

    if ($front_id && md_home_page_has_blocks($front_id)) {
        md_home_mark_done();
        return $front_id;
    }

    if ($front_id && md_home_content_is_blank(get_post_field('post_content', $front_id))) {
        wp_update_post(
            array(
                'ID'           => $front_id,
                'post_content' => md_home_default_content(),
            )
        );
        md_home_mark_done();
        set_transient('md_home_page_notice', 1, 60);
        return $front_id;
    }

    $page         = get_page_by_path('home');
    $is_blog_page = $page instanceof WP_Post && (int) get_option('page_for_posts') === (int) $page->ID;

    if ($page instanceof WP_Post && 'publish' === $page->post_status && !$is_blog_page) {
        $page_id = (int) $page->ID;
        if (!md_home_page_has_blocks($page_id) && md_home_content_is_blank($page->post_content)) {
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
    md_home_mark_done();
    set_transient('md_home_page_notice', 1, 60);

    return (int) $page_id;
}
add_action(
    'after_switch_theme',
    function () {
        // Post types and taxonomies are registered on init; wait for their
        // archive links before building the default page.
        add_action('init', 'md_create_home_page', 99);
    }
);

/**
 * One-time setup for sites that already had the theme active.
 *
 * Without it an existing installation would keep the hard-coded homepage and
 * the owner would have nothing to edit.
 */
function md_maybe_create_home_page() {
    if ((int) get_option('md_home_page_created') >= md_home_setup_version()) {
        return;
    }
    if (!current_user_can('manage_options')) {
        return;
    }

    md_create_home_page();
}
add_action('admin_init', 'md_maybe_create_home_page');

/**
 * Point the owner at the freshly created homepage once.
 */
function md_home_page_notice() {
    if (!get_transient('md_home_page_notice') || !current_user_can('edit_pages')) {
        return;
    }

    delete_transient('md_home_page_notice');

    $page_id = md_home_page_id();
    if (!$page_id) {
        return;
    }

    echo '<div class="notice notice-success is-dismissible"><p><strong>صفحه اصلی بلوکی آماده است.</strong> همهٔ بخش‌های آن بلوک گوتنبرگ‌اند. <a href="'
        . esc_url((string) get_edit_post_link($page_id)) . '">ویرایش صفحه اصلی</a> — عنوان، متن، پیوند، تعداد و پس‌زمینهٔ هر بخش را از همان برگه تغییر دهید. '
        . 'اگر پیش‌تر برگهٔ دیگری صفحه اصلی بود، حذف نشده و در «برگه‌ها» باقی است. <a href="'
        . esc_url(admin_url('options-reading.php')) . '">تنظیمات خواندن</a></p></div>';
}
add_action('admin_notices', 'md_home_page_notice');

/**
 * Manual "build the homepage" action used from the theme settings page.
 */
function md_handle_build_home() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('دسترسی کافی ندارید.', 'morvarid-daron'));
    }

    check_admin_referer('md_build_home');

    $page_id = md_create_home_page();
    $target  = $page_id
        ? admin_url('post.php?post=' . (int) $page_id . '&action=edit')
        : admin_url('themes.php');

    wp_safe_redirect($target);
    exit;
}
add_action('admin_post_md_build_home', 'md_handle_build_home');
