<?php
/**
 * Sidebar areas and the curated widget set.
 *
 * Every context-aware sidebar is fully managed through Appearance > Widgets.
 * Built-in card designs are ordinary configurable widgets (not fixed markup).
 *
 * @package Morvarid_Daron
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the sidebar areas.
 */
function md_register_sidebars() {
    $common = array(
        'before_widget' => '<section id="%1$s" class="md-widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h2 class="md-widget-title">',
        'after_title'   => '</h2>',
    );

    register_sidebar(
        array_merge(
            $common,
            array(
                'name'        => 'سایدبار مجله',
                'id'          => 'md-magazine-sidebar',
                'description' => 'ابزارک‌های صفحه نوشته، دسته‌ها و آرشیو مجله',
            )
        )
    );

    register_sidebar(
        array_merge(
            $common,
            array(
                'name'        => 'سایدبار آکادمی',
                'id'          => 'md-academy-sidebar',
                'description' => 'ابزارک‌های دوره‌ها، جلسه‌ها، پادکست‌ها و منابع آکادمی',
            )
        )
    );

    register_sidebar(
        array_merge(
            $common,
            array(
                'name'        => 'سایدبار چالش‌ها',
                'id'          => 'md-challenge-sidebar',
                'description' => 'ابزارک‌های صفحه چالش و روزهای آن',
            )
        )
    );
}
add_action('widgets_init', 'md_register_sidebars');

/**
 * Which sidebar belongs to the current screen.
 *
 * @return string magazine|academy|challenge
 */
function md_sidebar_context() {
    if (
        is_singular(array('md_course', 'md_lesson', 'md_podcast', 'md_resource')) ||
        is_post_type_archive(array('md_course', 'md_lesson', 'md_podcast', 'md_resource')) ||
        is_tax('md_academy_cat') || is_tax('md_resource_cat')
    ) {
        return 'academy';
    }

    if (is_singular('md_challenge') || is_post_type_archive('md_challenge') || is_tax('md_challenge_cat')) {
        return 'challenge';
    }

    return 'magazine';
}

/**
 * Render a sidebar with curated fallbacks.
 *
 * @param string $context Sidebar context.
 * @param string $layout  right|left|none.
 */
function md_render_sidebar($context = '', $layout = 'right') {
    if ('none' === $layout) {
        return;
    }

    $context = $context ? $context : md_sidebar_context();
    if (!in_array($context, array('magazine', 'academy', 'challenge'), true)) {
        $context = 'magazine';
    }
    $labels  = array(
        'magazine'  => 'سایدبار مجله',
        'academy'   => 'سایدبار آکادمی',
        'challenge' => 'سایدبار چالش‌ها',
    );
    $id      = 'md-' . $context . '-sidebar';

    // Respect an intentionally empty widget area: never restore fixed cards
    // during rendering. The one-time migration seeds the previous defaults.
    if (!is_active_sidebar($id)) {
        return;
    }
    echo '<aside class="md-magazine-sidebar md-sidebar md-sidebar-' . esc_attr($context) . '" aria-label="' . esc_attr($labels[$context]) . '">';
    dynamic_sidebar($id);
    echo '</aside>';
}

/**
 * Backwards compatible wrapper used by older templates.
 *
 * @param string $layout Layout.
 */
function md_magazine_sidebar($layout = 'right') {
    md_render_sidebar('magazine', $layout);
}

/**
 * Sidebar search form.
 *
 * @param string $post_type Post type to restrict to.
 * @param string $title     Widget title.
 */
function md_sidebar_search($post_type = '', $title = 'جستجو') {
    $id = wp_unique_id('md-sidebar-search-');
    ?>
    <section class="md-widget md-widget-search">
        <h2 class="md-widget-title"><?php echo esc_html($title); ?></h2>
        <form role="search" method="get" class="md-sidebar-search" action="<?php echo esc_url(home_url('/')); ?>">
            <label class="screen-reader-text" for="<?php echo esc_attr($id); ?>"><?php echo esc_html($title); ?></label>
            <input id="<?php echo esc_attr($id); ?>" type="search" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="عبارت مورد نظر…">
            <?php if ($post_type) : ?>
                <input type="hidden" name="post_type" value="<?php echo esc_attr($post_type); ?>">
            <?php endif; ?>
            <button type="submit" aria-label="جستجو">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="m16 16 4.5 4.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
        </form>
    </section>
    <?php
}

/**
 * Author card for single posts.
 */
function md_sidebar_author($title = 'نویسنده') {
    $author_id = (int) get_the_author_meta('ID');
    if (!$author_id) {
        return;
    }

    $description = get_the_author_meta('description', $author_id);
    ?>
    <section class="md-widget md-widget-author">
        <div class="md-author-card">
            <?php echo get_avatar($author_id, 76); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <span class="md-author-eyebrow"><?php echo esc_html($title); ?></span>
            <h2 class="md-author-name"><?php echo esc_html(get_the_author_meta('display_name', $author_id)); ?></h2>
            <?php if ($description) : ?>
                <p class="md-author-bio"><?php echo esc_html(md_trim_text($description, 26)); ?></p>
            <?php endif; ?>
            <a class="md-author-link" href="<?php echo esc_url(get_author_posts_url($author_id)); ?>">همه نوشته‌های این نویسنده ←</a>
        </div>
    </section>
    <?php
}

/**
 * Latest posts with thumbnails.
 */
function md_sidebar_popular($title = 'تازه‌ترین مطالب', $count = 5) {
    $posts = get_posts(
        array(
            'post_type'      => 'post',
            'numberposts'    => max(1, min(12, absint($count))),
            'post_status'    => 'publish',
            'ignore_sticky_posts' => true,
        )
    );

    if (!$posts) {
        return;
    }
    ?>
    <section class="md-widget md-widget-posts">
        <h2 class="md-widget-title"><?php echo esc_html($title); ?></h2>
        <ol class="md-sidebar-posts">
            <?php foreach ($posts as $index => $item) : ?>
                <li>
                    <a class="md-sidebar-post" href="<?php echo esc_url(get_permalink($item)); ?>">
                        <span class="md-sidebar-post-thumb">
                            <?php
                            if (has_post_thumbnail($item)) {
                                echo get_the_post_thumbnail($item, 'thumbnail', array('loading' => 'lazy'));
                            } else {
                                echo '<span class="md-sidebar-post-fallback" aria-hidden="true">✦</span>';
                            }
                            ?>
                        </span>
                        <span class="md-sidebar-post-body">
                            <strong><?php echo esc_html(get_the_title($item)); ?></strong>
                            <span class="md-sidebar-post-date"><?php echo esc_html(md_format_jalali(get_post_time('Y-m-d', false, $item), 'short')); ?></span>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>
    <?php
}

/**
 * Category list as pills.
 *
 * @param string $taxonomy Taxonomy.
 * @param string $title    Widget title.
 */
function md_sidebar_categories($taxonomy = 'category', $title = 'موضوعات') {
    $terms = get_terms(
        array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
            'number'     => 14,
        )
    );

    if (is_wp_error($terms) || !$terms) {
        return;
    }
    ?>
    <section class="md-widget md-widget-terms">
        <h2 class="md-widget-title"><?php echo esc_html($title); ?></h2>
        <div class="md-term-cloud">
            <?php foreach ($terms as $term) : ?>
                <a class="md-term-pill" href="<?php echo esc_url(get_term_link($term)); ?>">
                    <?php echo esc_html($term->name); ?>
                    <span><?php echo esc_html(md_fa_num($term->count)); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
}

/**
 * Promote the next challenge.
 */
function md_sidebar_challenge_promo($title = 'چالش پیش‌رو') {
    $today = md_today();

    $query = md_challenge_query(
        array(
            'posts_per_page' => 1,
            'meta_key'       => '_md_end_date',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'meta_query'     => array(
                array(
                    'key'     => '_md_end_date',
                    'value'   => $today,
                    'compare' => '>=',
                    'type'    => 'DATE',
                ),
            ),
        )
    );

    if (!$query->have_posts()) {
        return;
    }

    $query->the_post();
    $challenge_id = get_the_ID();
    $start        = md_challenge_start($challenge_id);
    $end          = md_challenge_end($challenge_id);
    $days         = md_challenge_calendar_days($challenge_id);
    $label        = md_challenge_time_label($challenge_id);
    ?>
    <section class="md-widget md-widget-challenge">
        <h2 class="md-widget-title"><?php echo esc_html($title); ?></h2>
        <a class="md-challenge-promo" href="<?php echo esc_url(get_permalink($challenge_id)); ?>">
            <span class="md-challenge-promo-glow" aria-hidden="true"></span>
            <?php if ($label) : ?>
                <span class="md-challenge-promo-badge"><?php echo esc_html($label); ?></span>
            <?php endif; ?>
            <strong class="md-challenge-promo-title"><?php echo esc_html(get_the_title($challenge_id)); ?></strong>
            <span class="md-challenge-promo-meta">
                <?php
                if ($start && $end) {
                    echo esc_html(md_format_jalali($start, 'short') . ' تا ' . md_format_jalali($end, 'short'));
                }
                if ($days) {
                    echo ' · ' . esc_html(md_fa_num($days)) . ' روز';
                }
                ?>
            </span>
            <span class="md-challenge-promo-cta">مشاهده چالش ←</span>
        </a>
    </section>
    <?php
    wp_reset_postdata();
}

/**
 * Progress card for the challenge being viewed.
 */
function md_sidebar_challenge_progress($title = 'پیشرفت چالش') {
    $challenge_id = is_singular('md_challenge') ? get_the_ID() : 0;

    if (!$challenge_id) {
        md_sidebar_challenge_promo();
        return;
    }

    $days   = md_challenge_days($challenge_id, false);
    $total  = count($days);
    $open   = 0;
    foreach ($days as $day) {
        if (md_challenge_day_is_unlocked($day, $challenge_id)) {
            ++$open;
        }
    }
    $percent = $total ? (int) round(($open / $total) * 100) : 0;
    $start   = md_challenge_start($challenge_id);
    $end     = md_challenge_end($challenge_id);
    ?>
    <section class="md-widget md-widget-progress">
        <h2 class="md-widget-title"><?php echo esc_html($title); ?></h2>
        <div class="md-progress-head">
            <span class="md-progress-count"><?php echo esc_html(md_fa_num($open) . ' از ' . md_fa_num($total)); ?></span>
            <span class="md-progress-caption">روز باز شده</span>
        </div>
        <div class="md-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr($percent); ?>">
            <span class="md-progress-bar" style="width:<?php echo esc_attr($percent); ?>%"></span>
        </div>
        <div class="md-progress-percent"><?php echo esc_html(md_fa_num($percent) . '٪'); ?></div>
        <?php if ($start && $end) : ?>
            <p class="md-progress-dates"><?php echo esc_html(md_format_jalali($start) . ' — ' . md_format_jalali($end)); ?></p>
        <?php endif; ?>
        <a class="md-progress-link" href="<?php echo esc_url(get_permalink($challenge_id)); ?>#md-days">فهرست روزها ←</a>
    </section>
    <?php
}

/**
 * Latest courses.
 */
function md_sidebar_academy_promo($title = 'دوره‌های آکادمی', $count = 4) {
    $courses = get_posts(
        array(
            'post_type'   => 'md_course',
            'numberposts' => max(1, min(12, absint($count))),
            'post_status' => 'publish',
        )
    );

    if (!$courses) {
        return;
    }
    ?>
    <section class="md-widget md-widget-courses">
        <h2 class="md-widget-title"><?php echo esc_html($title); ?></h2>
        <ul class="md-sidebar-courses">
            <?php foreach ($courses as $course) : ?>
                <?php
                $level  = get_post_meta($course->ID, '_md_course_level', true);
                $labels = array('beginner' => 'مقدماتی', 'intermediate' => 'متوسط', 'advanced' => 'پیشرفته');
                ?>
                <li>
                    <a href="<?php echo esc_url(get_permalink($course)); ?>">
                        <span class="md-sidebar-course-thumb">
                            <?php
                            if (has_post_thumbnail($course)) {
                                echo get_the_post_thumbnail($course, 'thumbnail', array('loading' => 'lazy'));
                            } else {
                                echo '<span aria-hidden="true">✦</span>';
                            }
                            ?>
                        </span>
                        <span class="md-sidebar-course-body">
                            <strong><?php echo esc_html(get_the_title($course)); ?></strong>
                            <?php if ($level && isset($labels[$level])) : ?>
                                <span><?php echo esc_html($labels[$level]); ?></span>
                            <?php endif; ?>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <a class="md-widget-more" href="<?php echo esc_url(get_post_type_archive_link('md_course')); ?>">همه دوره‌ها ←</a>
    </section>
    <?php
}

/**
 * Latest lessons.
 */
function md_sidebar_lesson_promo($title = 'تازه‌ترین جلسه‌ها', $count = 4) {
    $lessons = get_posts(
        array(
            'post_type'   => array('md_lesson', 'md_podcast'),
            'numberposts' => max(1, min(12, absint($count))),
            'post_status' => 'publish',
        )
    );

    if (!$lessons) {
        return;
    }
    ?>
    <section class="md-widget md-widget-lessons">
        <h2 class="md-widget-title"><?php echo esc_html($title); ?></h2>
        <ul class="md-sidebar-lessons">
            <?php foreach ($lessons as $lesson) : ?>
                <?php $kind = get_post_meta($lesson->ID, '_md_media_kind', true); ?>
                <li>
                    <a href="<?php echo esc_url(get_permalink($lesson)); ?>">
                        <span class="md-sidebar-lesson-icon" aria-hidden="true"><?php echo 'audio' === $kind ? '🎧' : '▶'; ?></span>
                        <span class="md-sidebar-lesson-body">
                            <strong><?php echo esc_html(get_the_title($lesson)); ?></strong>
                            <span><?php echo esc_html('md_podcast' === $lesson->post_type ? 'پادکست' : 'جلسه آموزشی'); ?></span>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php
}

/**
 * Academy taxonomy list.
 */
function md_sidebar_academy_categories() {
    $is_resource = is_singular('md_resource') || is_post_type_archive('md_resource') || is_tax('md_resource_cat');
    if ($is_resource) {
        md_sidebar_categories('md_resource_cat', 'دسته‌بندی منابع');
        return;
    }

    md_sidebar_categories('md_academy_cat', 'دسته‌بندی دوره‌ها');
}

/**
 * Challenge category list.
 */
function md_sidebar_challenge_categories() {
    md_sidebar_categories('md_challenge_cat', 'دسته‌های چالش');
}

/**
 * Theme newsletter call to action.
 */
function md_sidebar_newsletter($settings = array()) {
    $url = !empty($settings['button_url']) ? $settings['button_url'] : get_theme_mod('md_newsletter_url', '/newsletter/');
    $title = isset($settings['title']) ? $settings['title'] : 'عضو خانواده مروارید درون شوید';
    $body = isset($settings['body']) ? $settings['body'] : 'از تازه‌ترین مطالب، کارگاه‌ها و تمرین‌های رایگان باخبر شوید.';
    $label = isset($settings['button_text']) ? $settings['button_text'] : 'عضویت در خبرنامه ←';
    $social = !isset($settings['show_socials']) || !empty($settings['show_socials']);
    ?>
    <section class="md-widget md-widget-newsletter">
        <span class="md-newsletter-eyebrow">همراه مسیر رشد</span>
        <h2 class="md-widget-title"><?php echo esc_html($title); ?></h2>
        <?php if (trim($body) !== '') : ?><p><?php echo esc_html($body); ?></p><?php endif; ?>
        <?php if ($url && $label) : ?><a class="md-newsletter-cta" href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a><?php endif; ?>
        <?php if ($social && function_exists('md_social_links')) : ?>
            <?php echo md_social_links(array('class' => 'md-social-stack md-social-family')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php endif; ?>
    </section>
    <?php
}

// Editable widget cards replace the previous unchangeable fallback cards.
require_once __DIR__ . '/sidebar-widgets.php';
