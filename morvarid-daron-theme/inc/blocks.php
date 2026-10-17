<?php
/**
 * Additional dynamic blocks for the Morvarid Daron design library.
 *
 * These blocks expose the academy, challenge and media features directly in
 * the block editor, so pages can be composed without shortcodes.
 *
 * @package Morvarid_Daron
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shared section heading markup.
 *
 * @param string $title    Heading.
 * @param string $subtitle Optional lead paragraph.
 * @return string
 */
function md_block_heading($title, $subtitle = '', $link_label = '', $link_url = '') {
    $title      = trim((string) $title);
    $subtitle   = trim((string) $subtitle);
    $link_label = trim((string) $link_label);
    $link_url   = trim((string) $link_url);

    if ('' === $title && '' === $subtitle && ('' === $link_label || '' === $link_url)) {
        return '';
    }

    $html  = '<div class="md-gb-section-head"><div class="md-gb-section-copy">';
    if ($title) {
        $html .= '<h2>' . esc_html($title) . '</h2>';
    }
    if ($subtitle) {
        $html .= '<p class="md-gb-section-lead">' . esc_html($subtitle) . '</p>';
    }
    $html .= '</div>';

    if ($link_label && $link_url) {
        $html .= '<a class="text-link md-gb-section-link" href="' . esc_url($link_url) . '">' . esc_html($link_label) . ' ←</a>';
    }

    $html .= '</div>';

    return $html;
}

/**
 * Background class of a section block.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function md_block_bg_class($attributes) {
    $allowed = array('cream' => 'md-gb-bg-cream', 'warm' => 'md-gb-bg-warm', 'mint' => 'md-gb-bg-mint');
    $bg      = isset($attributes['background']) ? sanitize_key($attributes['background']) : '';

    return isset($allowed[$bg]) ? ' ' . $allowed[$bg] : '';
}

/**
 * Wrap section content in the shared full-width band + container.
 *
 * @param array  $attributes Block attributes (for the background).
 * @param string $inner      Inner HTML.
 * @param string $extra      Extra classes.
 * @return string
 */
function md_block_section($attributes, $inner, $extra = '') {
    $classes = trim('md-gb-grid-section ' . $extra . md_block_bg_class($attributes));

    return '<section class="' . esc_attr($classes) . '"><div class="container">' . $inner . '</div></section>';
}

/**
 * Render a grid of posts using the theme card.
 *
 * @param array $args Query arguments.
 * @param int   $columns Columns.
 * @param string $label  Card eyebrow label.
 * @param string $empty  Empty message.
 * @return string
 */
function md_block_card_grid($args, $columns = 3, $label = 'مروارید درون', $empty = 'هنوز محتوایی منتشر نشده است.') {
    $columns = max(1, min(4, (int) $columns));
    $query   = new WP_Query(wp_parse_args($args, array('post_status' => 'publish')));

    ob_start();
    echo '<div class="md-gb-grid md-gb-cols-' . esc_attr((string) $columns) . '">';
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            md_card(get_the_ID(), $label);
        }
        wp_reset_postdata();
    } else {
        echo '<p class="md-gb-empty">' . esc_html($empty) . '</p>';
    }
    echo '</div>';

    return ob_get_clean();
}

/* ------------------------------------------------------------- course grid */

/**
 * Render the course grid block.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function md_block_course_grid($attributes) {
    $attributes = wp_parse_args(
        $attributes,
        array('title' => 'دوره‌های آکادمی', 'subtitle' => '', 'count' => 3, 'columns' => 3, 'category' => '', 'level' => '', 'linkLabel' => '', 'linkUrl' => '', 'background' => '')
    );

    $args = array(
        'post_type'      => 'md_course',
        'posts_per_page' => max(1, min(12, (int) $attributes['count'])),
        'ignore_sticky_posts' => true,
    );

    if ($attributes['category']) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'md_academy_cat',
                'field'    => 'slug',
                'terms'    => sanitize_title($attributes['category']),
            ),
        );
    }

    if ($attributes['level']) {
        $args['meta_query'] = array(
            array(
                'key'   => '_md_course_level',
                'value' => sanitize_key($attributes['level']),
            ),
        );
    }

    $link_url = esc_url($attributes['linkUrl']);
    if (!$link_url) {
        $link_url = (string) get_post_type_archive_link('md_course');
    }

    return md_block_section(
        $attributes,
        md_block_heading($attributes['title'], $attributes['subtitle'], $attributes['linkLabel'], $link_url)
        . md_block_card_grid($args, $attributes['columns'], 'آکادمی مروارید', 'هنوز دوره‌ای ثبت نشده است.')
    );
}

/* ---------------------------------------------------------- challenge grid */

/**
 * Render the challenge grid block.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function md_block_challenge_grid($attributes) {
    $attributes = wp_parse_args(
        $attributes,
        array('title' => 'چالش‌های مروارید درون', 'subtitle' => '', 'count' => 3, 'status' => 'all', 'linkLabel' => '', 'linkUrl' => '', 'background' => '')
    );

    $args = array(
        'posts_per_page' => max(1, min(12, (int) $attributes['count'])),
    );

    $today = md_today();
    if ('upcoming' === $attributes['status']) {
        $args['meta_query'] = array(
            array('key' => '_md_start_date', 'value' => $today, 'compare' => '>', 'type' => 'DATE'),
        );
    } elseif ('active' === $attributes['status']) {
        $args['meta_query'] = array(
            'relation' => 'AND',
            array('key' => '_md_start_date', 'value' => $today, 'compare' => '<=', 'type' => 'DATE'),
            array('key' => '_md_end_date', 'value' => $today, 'compare' => '>=', 'type' => 'DATE'),
        );
    } elseif ('current' === $attributes['status']) {
        // Everything that has not finished yet, including future challenges.
        $args['meta_query'] = array(
            array('key' => '_md_end_date', 'value' => $today, 'compare' => '>=', 'type' => 'DATE'),
        );
    }

    $query = md_challenge_query($args);

    $link_url = esc_url($attributes['linkUrl']);
    if (!$link_url) {
        $link_url = (string) get_post_type_archive_link('md_challenge');
    }

    ob_start();
    echo '<div class="md-gb-grid md-gb-cols-3 md-challenge-grid">';
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            md_challenge_render_card(get_the_ID());
        }
        wp_reset_postdata();
    } else {
        echo '<p class="md-gb-empty">چالشی با این شرایط پیدا نشد.</p>';
    }
    echo '</div>';

    return md_block_section(
        $attributes,
        md_block_heading($attributes['title'], $attributes['subtitle'], $attributes['linkLabel'], $link_url) . ob_get_clean(),
        'md-challenge-grid-section'
    );
}

/* ---------------------------------------------------------- challenge days */

/**
 * Render the list of days for one challenge.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function md_block_challenge_days($attributes) {
    $attributes = wp_parse_args(
        $attributes,
        array('title' => 'روزهای چالش', 'subtitle' => '', 'challengeId' => 0, 'showSummary' => true)
    );

    $challenge_id = absint($attributes['challengeId']);

    if (!$challenge_id && is_singular('md_challenge')) {
        $challenge_id = get_the_ID();
    }
    if (!$challenge_id) {
        $latest = md_challenge_query(array('posts_per_page' => 1, 'orderby' => 'date', 'order' => 'DESC'));
        if ($latest->have_posts()) {
            $challenge_id = (int) $latest->posts[0]->ID;
        }
        wp_reset_postdata();
    }

    if (!$challenge_id) {
        return '<p class="md-gb-empty">هنوز چالشی منتشر نشده است.</p>';
    }

    $days = md_challenge_days($challenge_id, false);

    if (!$days) {
        return '<p class="md-gb-empty">برای این چالش هنوز روزی ثبت نشده است.</p>';
    }

    $permalink     = get_permalink($challenge_id);
    $show_titles   = md_challenge_show_locked_title($challenge_id);

    ob_start();
    echo '<section class="md-gb-grid-section container md-challenge-days-block">';
    echo md_block_heading(
        $attributes['title'] ? $attributes['title'] : get_the_title($challenge_id),
        $attributes['subtitle']
    ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

    echo '<ol class="md-day-list">';
    foreach ($days as $day) {
        $order    = md_challenge_day_order($day->ID);
        $unlocked = md_challenge_day_is_unlocked($day, $challenge_id);
        $summary  = get_post_field('post_excerpt', $day->ID);
        $date     = md_challenge_day_unlock_date($day, $challenge_id);
        $classes  = 'md-day-list-item' . ($unlocked ? ' is-open' : ' is-locked');

        echo '<li class="' . esc_attr($classes) . '">';
        echo '<span class="md-day-list-number">' . esc_html(md_fa_num($order)) . '</span>';
        echo '<div class="md-day-list-body">';
        if ($unlocked || $show_titles) {
            echo '<h3><a href="' . esc_url($permalink . '#day-' . $order) . '">' . esc_html(get_the_title($day)) . '</a></h3>';
        } else {
            echo '<h3 class="md-day-list-hidden">روز ' . esc_html(md_fa_num($order)) . '</h3>';
        }
        if ($attributes['showSummary'] && $summary && $unlocked) {
            echo '<p>' . esc_html(md_trim_text($summary, 22)) . '</p>';
        }
        if ($date) {
            echo '<span class="md-day-list-date">' . esc_html(md_format_jalali($date, 'long')) . '</span>';
        }
        echo '</div>';
        echo '<span class="md-day-list-state" aria-hidden="true">' . ($unlocked ? 'باز' : '🔒') . '</span>';
        echo '</li>';
    }
    echo '</ol></section>';

    return ob_get_clean();
}

/* -------------------------------------------------------------- audio/video */

/**
 * Render the audio player block.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function md_block_audio($attributes) {
    $attributes = wp_parse_args(
        $attributes,
        array('title' => '', 'subtitle' => 'پادکست مروارید درون', 'mediaId' => 0, 'mediaUrl' => '')
    );

    $source = $attributes['mediaId'] ? absint($attributes['mediaId']) : esc_url_raw($attributes['mediaUrl']);

    if (!$source) {
        return '<p class="md-gb-empty">برای این بلوک یک فایل صوتی انتخاب کنید.</p>';
    }

    return md_audio_player(
        array(
            'src'      => $source,
            'title'    => $attributes['title'],
            'subtitle' => $attributes['subtitle'],
        )
    );
}

/**
 * Render the video player block.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function md_block_video($attributes) {
    $attributes = wp_parse_args(
        $attributes,
        array('title' => '', 'posterId' => 0, 'mediaId' => 0, 'mediaUrl' => '')
    );

    if ($attributes['mediaId']) {
        return md_video_player(
            array(
                'src'    => absint($attributes['mediaId']),
                'poster' => absint($attributes['posterId']),
                'title'  => $attributes['title'],
            )
        );
    }

    $url = esc_url_raw($attributes['mediaUrl']);
    if (!$url) {
        return '<p class="md-gb-empty">برای این بلوک یک ویدیو انتخاب یا لینک کنید.</p>';
    }

    $path = wp_parse_url($url, PHP_URL_PATH);
    if ($path && preg_match('/\.(mp4|webm|ogv|ogg|m4v)$/i', $path)) {
        return md_video_player(
            array(
                'src'    => $url,
                'poster' => absint($attributes['posterId']),
                'title'  => $attributes['title'],
            )
        );
    }

    return md_video_embed($url, $attributes['title']);
}

/* --------------------------------------------------------------- lesson list */

/**
 * Render the lessons of a course.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function md_block_lesson_list($attributes) {
    $attributes = wp_parse_args($attributes, array('title' => 'سرفصل‌های دوره', 'courseId' => 0));

    $course_id = absint($attributes['courseId']);
    if (!$course_id && is_singular('md_course')) {
        $course_id = get_the_ID();
    }
    if (!$course_id) {
        return '<p class="md-gb-empty">این بلوک را در صفحه یک دوره قرار دهید یا شناسه دوره را انتخاب کنید.</p>';
    }

    if (!function_exists('md_academy_lessons')) {
        return '';
    }

    $lessons = md_academy_lessons($course_id);
    if (!$lessons) {
        return '';
    }

    return '<section class="md-course-lessons-block container">'
        . md_block_heading($attributes['title'], '')
        . $lessons
        . '</section>';
}

/* ------------------------------------------------------------ resource grid */

/**
 * Render the resources grid.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function md_block_resource_grid($attributes) {
    $attributes = wp_parse_args(
        $attributes,
        array('title' => 'تمرین‌ها و منابع رایگان', 'subtitle' => '', 'count' => 3, 'kind' => '', 'linkLabel' => '', 'linkUrl' => '', 'background' => '')
    );

    $args = array(
        'post_type'      => 'md_resource',
        'posts_per_page' => max(1, min(12, (int) $attributes['count'])),
    );

    if ($attributes['kind']) {
        $args['meta_query'] = array(
            array('key' => '_md_resource_kind', 'value' => sanitize_key($attributes['kind'])),
        );
    }

    $link_url = esc_url($attributes['linkUrl']);
    if (!$link_url) {
        $link_url = (string) get_post_type_archive_link('md_resource');
    }

    return md_block_section(
        $attributes,
        md_block_heading($attributes['title'], $attributes['subtitle'], $attributes['linkLabel'], $link_url)
        . md_block_card_grid($args, 3, 'منابع رایگان', 'هنوز منبعی ثبت نشده است.')
    );
}

/* ------------------------------------------------------------- service grid */

/**
 * Render the coaching services grid.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function md_block_service_grid($attributes) {
    $attributes = wp_parse_args(
        $attributes,
        array('title' => 'خدمات کوچینگ', 'subtitle' => '', 'count' => 3, 'linkLabel' => '', 'linkUrl' => '', 'background' => '')
    );

    $args = array(
        'post_type'      => 'md_service',
        'posts_per_page' => max(1, min(12, (int) $attributes['count'])),
    );

    $link_url = esc_url($attributes['linkUrl']);
    if (!$link_url) {
        $link_url = (string) get_post_type_archive_link('md_service');
    }

    return md_block_section(
        $attributes,
        md_block_heading($attributes['title'], $attributes['subtitle'], $attributes['linkLabel'], $link_url)
        . md_block_card_grid($args, 3, 'کوچینگ مروارید', 'هنوز خدمتی ثبت نشده است.')
    );
}

/* ---------------------------------------------------------------- features */

/**
 * Render the three (or more) highlight cards.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function md_block_features($attributes) {
    $attributes = wp_parse_args(
        $attributes,
        array('items' => array(), 'background' => '')
    );

    $items = is_array($attributes['items']) ? $attributes['items'] : array();
    $cards = '';

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $icon  = isset($item['icon']) ? sanitize_text_field($item['icon']) : '';
        $title = isset($item['title']) ? sanitize_text_field($item['title']) : '';
        $text  = isset($item['text']) ? sanitize_text_field($item['text']) : '';
        $url   = isset($item['url']) ? esc_url_raw($item['url']) : '';

        if ('' === $title && '' === $text) {
            continue;
        }

        $inner = ($icon ? '<span class="pillar-icon" aria-hidden="true">' . esc_html($icon) . '</span>' : '')
            . '<div>'
            . ($title ? '<h2>' . esc_html($title) . '</h2>' : '')
            . ($text ? '<p>' . esc_html($text) . '</p>' : '')
            . '</div>';

        $cards .= $url
            ? '<a class="pillar" href="' . esc_url($url) . '">' . $inner . '</a>'
            : '<div class="pillar">' . $inner . '</div>';
    }

    if ('' === $cards) {
        return '';
    }

    return md_block_section(
        $attributes,
        '<div class="three-col md-gb-features">' . $cards . '</div>',
        'md-gb-pillars-section'
    );
}

/* --------------------------------------------------------------- media grid */

/**
 * Render the latest lessons and podcasts together.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function md_block_media_grid($attributes) {
    $attributes = wp_parse_args(
        $attributes,
        array(
            'title'      => 'کتابخانه ویدیو و پادکست',
            'subtitle'   => '',
            'count'      => 3,
            'linkLabel'  => '',
            'linkUrl'    => '',
            'background' => '',
        )
    );

    $query = new WP_Query(
        array(
            'post_type'           => array('md_lesson', 'md_podcast'),
            'post_status'         => 'publish',
            'posts_per_page'      => max(1, min(12, (int) $attributes['count'])),
            'ignore_sticky_posts' => true,
        )
    );

    $link_url = esc_url($attributes['linkUrl']);
    if (!$link_url) {
        $link_url = (string) get_post_type_archive_link('md_podcast');
    }

    ob_start();
    echo '<div class="md-gb-grid md-gb-cols-3">';
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $type  = get_post_type();
            $kind  = get_post_meta(get_the_ID(), '_md_media_kind', true);
            $label = ('md_podcast' === $type) ? '🎧 پادکست' : ('audio' === $kind ? '🎧 جلسه صوتی' : '▶ جلسه ویدیویی');
            md_card(get_the_ID(), $label);
        }
        wp_reset_postdata();
    } else {
        echo '<p class="md-gb-empty">جلسه‌ها و پادکست‌های تازه‌ای که منتشر می‌کنید اینجا نمایش داده می‌شوند.</p>';
    }
    echo '</div>';

    return md_block_section(
        $attributes,
        md_block_heading($attributes['title'], $attributes['subtitle'], $attributes['linkLabel'], $link_url) . ob_get_clean(),
        'md-gb-media-section'
    );
}

/* --------------------------------------------------------------- newsletter */

/**
 * Render the newsletter call to action.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function md_block_newsletter($attributes) {
    $attributes = wp_parse_args(
        $attributes,
        array(
            'eyebrow'     => 'همراه مسیر رشد',
            'title'       => 'عضو خانواده مروارید درون شوید',
            'text'        => '',
            'buttonLabel' => 'عضویت در خبرنامه',
            'buttonUrl'   => '',
        )
    );

    $url = esc_url($attributes['buttonUrl']);

    return '<section class="newsletter section md-gb-newsletter"><div class="container newsletter-inner"><div>'
        . ($attributes['eyebrow'] ? '<span class="eyebrow">' . esc_html($attributes['eyebrow']) . '</span>' : '')
        . '<h2>' . esc_html($attributes['title']) . '</h2>'
        . ($attributes['text'] ? '<p>' . esc_html($attributes['text']) . '</p>' : '')
        . '</div>'
        . ($url ? '<a class="btn btn-primary" href="' . $url . '">' . esc_html($attributes['buttonLabel']) . ' ←</a>' : '')
        . '</div></section>';
}

/* -------------------------------------------------------------- registration */

/**
 * Register the extra blocks and their editor script.
 */
function md_register_extra_blocks() {
    $script = 'md-blocks-editor';

    wp_register_script(
        $script,
        MD_THEME_URI . '/assets/js/blocks.js',
        array('wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-media'),
        MD_THEME_VERSION,
        true
    );

    $blocks = array(
        'course-grid'    => array(
            'render'     => 'md_block_course_grid',
            'attributes' => array(
                'title'     => array('type' => 'string', 'default' => 'دوره‌های آکادمی'),
                'subtitle'  => array('type' => 'string', 'default' => ''),
                'count'     => array('type' => 'number', 'default' => 3),
                'columns'   => array('type' => 'number', 'default' => 3),
                'category'  => array('type' => 'string', 'default' => ''),
                'level'     => array('type' => 'string', 'default' => ''),
                'linkLabel'  => array('type' => 'string', 'default' => ''),
                'linkUrl'    => array('type' => 'string', 'default' => ''),
                'background' => array('type' => 'string', 'default' => ''),
            ),
        ),
        'challenge-grid' => array(
            'render'     => 'md_block_challenge_grid',
            'attributes' => array(
                'title'    => array('type' => 'string', 'default' => 'چالش‌های مروارید درون'),
                'subtitle' => array('type' => 'string', 'default' => ''),
                'count'    => array('type' => 'number', 'default' => 3),
                'status'   => array('type' => 'string', 'default' => 'all'),
                'linkLabel'  => array('type' => 'string', 'default' => ''),
                'linkUrl'    => array('type' => 'string', 'default' => ''),
                'background' => array('type' => 'string', 'default' => ''),
            ),
        ),
        'challenge-days' => array(
            'render'     => 'md_block_challenge_days',
            'attributes' => array(
                'title'       => array('type' => 'string', 'default' => 'روزهای چالش'),
                'subtitle'    => array('type' => 'string', 'default' => ''),
                'challengeId' => array('type' => 'number', 'default' => 0),
                'showSummary' => array('type' => 'boolean', 'default' => true),
            ),
        ),
        'audio-player'   => array(
            'render'     => 'md_block_audio',
            'attributes' => array(
                'title'    => array('type' => 'string', 'default' => ''),
                'subtitle' => array('type' => 'string', 'default' => 'پادکست مروارید درون'),
                'mediaId'  => array('type' => 'number', 'default' => 0),
                'mediaUrl' => array('type' => 'string', 'default' => ''),
            ),
        ),
        'video-player'   => array(
            'render'     => 'md_block_video',
            'attributes' => array(
                'title'    => array('type' => 'string', 'default' => ''),
                'posterId' => array('type' => 'number', 'default' => 0),
                'mediaId'  => array('type' => 'number', 'default' => 0),
                'mediaUrl' => array('type' => 'string', 'default' => ''),
            ),
        ),
        'lesson-list'    => array(
            'render'     => 'md_block_lesson_list',
            'attributes' => array(
                'title'    => array('type' => 'string', 'default' => 'سرفصل‌های دوره'),
                'courseId' => array('type' => 'number', 'default' => 0),
            ),
        ),
        'resource-grid'  => array(
            'render'     => 'md_block_resource_grid',
            'attributes' => array(
                'title'    => array('type' => 'string', 'default' => 'تمرین‌ها و منابع رایگان'),
                'subtitle' => array('type' => 'string', 'default' => ''),
                'count'    => array('type' => 'number', 'default' => 3),
                'kind'     => array('type' => 'string', 'default' => ''),
                'linkLabel'  => array('type' => 'string', 'default' => ''),
                'linkUrl'    => array('type' => 'string', 'default' => ''),
                'background' => array('type' => 'string', 'default' => ''),
            ),
        ),
        'service-grid'   => array(
            'render'     => 'md_block_service_grid',
            'attributes' => array(
                'title'    => array('type' => 'string', 'default' => 'خدمات کوچینگ'),
                'subtitle' => array('type' => 'string', 'default' => ''),
                'count'    => array('type' => 'number', 'default' => 3),
                'linkLabel'  => array('type' => 'string', 'default' => ''),
                'linkUrl'    => array('type' => 'string', 'default' => ''),
                'background' => array('type' => 'string', 'default' => ''),
            ),
        ),
        'features'       => array(
            'render'     => 'md_block_features',
            'attributes' => array(
                'items' => array(
                    'type'    => 'array',
                    'default' => array(
                        array('icon' => '▤', 'title' => 'مجله رشد', 'text' => 'مقاله، داستان و راهکارهای کاربردی', 'url' => ''),
                        array('icon' => '❀', 'title' => 'کوچینگ فردی', 'text' => 'همراهی در مسیر شناخت و تغییر', 'url' => ''),
                        array('icon' => '✦', 'title' => 'آکادمی آموزشی', 'text' => 'دوره و کارگاه برای رشد آگاهانه', 'url' => ''),
                    ),
                ),
                'background' => array('type' => 'string', 'default' => ''),
            ),
        ),
        'media-grid'     => array(
            'render'     => 'md_block_media_grid',
            'attributes' => array(
                'title'      => array('type' => 'string', 'default' => 'کتابخانه ویدیو و پادکست'),
                'subtitle'   => array('type' => 'string', 'default' => 'محتوای چندرسانه‌ای و جلسه‌های آموزشی'),
                'count'      => array('type' => 'number', 'default' => 3),
                'linkLabel'  => array('type' => 'string', 'default' => 'همه پادکست‌ها'),
                'linkUrl'    => array('type' => 'string', 'default' => ''),
                'background' => array('type' => 'string', 'default' => ''),
            ),
        ),
        'newsletter'     => array(
            'render'     => 'md_block_newsletter',
            'attributes' => array(
                'eyebrow'     => array('type' => 'string', 'default' => 'همراه مسیر رشد'),
                'title'       => array('type' => 'string', 'default' => 'عضو خانواده مروارید درون شوید'),
                'text'        => array('type' => 'string', 'default' => ''),
                'buttonLabel' => array('type' => 'string', 'default' => 'عضویت در خبرنامه'),
                'buttonUrl'   => array('type' => 'string', 'default' => ''),
            ),
        ),
        'request-form'   => array(
            'render'     => 'md_block_request_form',
            'attributes' => array(
                'title'          => array('type' => 'string', 'default' => 'درخواست جلسه'),
                'intro'          => array('type' => 'string', 'default' => 'برای هماهنگی جلسه، فرم زیر را پر کنید؛ در اولین فرصت با شما تماس می‌گیریم.'),
                'submitLabel'    => array('type' => 'string', 'default' => 'ارسال درخواست'),
                'successMessage' => array('type' => 'string', 'default' => 'درخواست شما با موفقیت ثبت شد. به‌زودی با شما تماس می‌گیریم.'),
                'fields'         => array(
                    'type'    => 'array',
                    'default' => md_request_default_fields(),
                ),
            ),
        ),
    );

    foreach ($blocks as $slug => $config) {
        register_block_type(
            'morvarid/' . $slug,
            array(
                'api_version'          => 2,
                'editor_script_handles' => array($script),
                'attributes'      => $config['attributes'],
                'render_callback' => $config['render'],
                'supports'        => array(
                    'html'  => false,
                    'align' => array('wide', 'full'),
                    'anchor' => true,
                ),
            )
        );
    }
}
add_action('init', 'md_register_extra_blocks', 20);
