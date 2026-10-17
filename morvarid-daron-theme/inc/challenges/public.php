<?php
/**
 * Public challenge rendering.
 *
 * @package Morvarid_Daron
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enrollment URL of a challenge.
 *
 * @param int $challenge_id Challenge ID.
 * @return string
 */
function md_challenge_enrollment_url($challenge_id) {
    return esc_url_raw(get_post_meta($challenge_id, '_md_enrollment_url', true));
}

/**
 * Render the media block of a day.
 *
 * @param WP_Post $day Day post.
 * @return string
 */
function md_challenge_day_media_html($day) {
    $audio_id    = absint(get_post_meta($day->ID, '_md_day_audio_id', true));
    $audio_url   = esc_url_raw(get_post_meta($day->ID, '_md_day_audio_url', true));
    $audio_title = sanitize_text_field(get_post_meta($day->ID, '_md_day_audio_title', true));
    $video_id    = absint(get_post_meta($day->ID, '_md_day_video_id', true));
    $video_url   = esc_url_raw(get_post_meta($day->ID, '_md_day_video_url', true));
    $video_title = sanitize_text_field(get_post_meta($day->ID, '_md_day_video_title', true));
    $file_id     = absint(get_post_meta($day->ID, '_md_day_file_id', true));
    $file_url    = esc_url_raw(get_post_meta($day->ID, '_md_day_file_url', true));
    $file_title  = sanitize_text_field(get_post_meta($day->ID, '_md_day_file_title', true));

    $sections = array();

    if ($audio_id || $audio_url) {
        $player = md_audio_player(array(
            'src'      => $audio_url ? $audio_url : $audio_id,
            'title'    => $audio_title ? $audio_title : get_the_title($day),
            'subtitle' => 'صوت روز',
        ));
        if ($player) {
            $sections[] = '<div class="md-day-media-item">' . $player . '</div>';
        }
    }

    if ($video_url) {
        $video_path = wp_parse_url($video_url, PHP_URL_PATH);
        $is_file = $video_path && preg_match('/\.(mp4|webm|ogv|mov)$/i', $video_path);
        $player = $is_file ? md_video_player(array('src' => $video_url, 'title' => $video_title)) : md_video_embed($video_url, $video_title);
        if ($player) {
            $sections[] = '<div class="md-day-media-item">' . $player . '</div>';
        }
    } elseif ($video_id) {
        $player = md_video_player(array(
            'src'   => $video_id,
            'title' => $video_title,
        ));
        if ($player) {
            $sections[] = '<div class="md-day-media-item">' . $player . '</div>';
        }
    }

    foreach (md_challenge_day_items($day->ID, 'files') as $file_item) {
        $attachment_id = absint($file_item['id'] ?? 0);
        $url = esc_url_raw($file_item['url'] ?? '');
        $file = $url ? $url : ($attachment_id ? wp_get_attachment_url($attachment_id) : '');
        if (!$file) {
            continue;
        }
        $title = sanitize_text_field($file_item['title'] ?? '');
        $sections[] = '<div class="md-day-media-item md-day-file">'
            . ($title ? '<h3>' . esc_html($title) . '</h3>' : '')
            . '<a class="btn btn-light" href="' . esc_url($file) . '" target="_blank" rel="noopener noreferrer">'
            . 'دریافت فایل <span aria-hidden="true">↓</span></a></div>';
    }

    if (!$sections) {
        return '';
    }

    return '<section class="md-day-media" aria-label="محتوای چندرسانه‌ای روز">' . implode('', $sections) . '</section>';
}

/**
 * Render the tip block of a day.
 *
 * @param WP_Post $day Day post.
 * @return string
 */
function md_challenge_day_tip_html($day) {
    $items = md_challenge_day_items($day->ID, 'tips');
    if (!$items) {
        return '';
    }
    $html = '<section class="md-day-tips" aria-label="نکته‌های این روز">';
    $index = 0;
    foreach ($items as $item) {
        $title = sanitize_text_field($item['title'] ?? '');
        $body = sanitize_textarea_field($item['text'] ?? '');
        if ($title === '' && $body === '') {
            continue;
        }
        $index++;
        $html .= '<aside class="md-day-tip">';
        $html .= '<span class="md-day-label"><span aria-hidden="true">💡</span> نکته</span>';
        if ($title !== '') {
            $html .= '<h3>' . esc_html($title) . '</h3>';
        }
        if ($body !== '') {
            $html .= wpautop(esc_html($body));
        }
        $html .= '</aside>';
    }
    return $html . '</section>';
}

/** Render every independent practical task for this challenge day. */
function md_challenge_day_exercise_html($day, $member_state = null) {
    $items = md_challenge_day_items($day->ID, 'tasks');
    if (!$items) {
        return '';
    }
    $html = '<section class="md-day-tasks" aria-label="کارها و تمرین‌های این روز">';
    $index = 0;
    foreach ($items as $item) {
        $title = sanitize_text_field($item['title'] ?? '');
        $desc = sanitize_textarea_field($item['description'] ?? '');
        $steps = sanitize_textarea_field($item['steps'] ?? '');
        if ($title === '' && $desc === '' && $steps === '') {
            continue;
        }
        $index++;
        $html .= '<section class="md-day-exercise"><span class="md-day-label">کار ' . esc_html(md_fa_num($index)) . '</span>';
        if (is_array($member_state)) {
            $task_key = $index - 1;
            $done = !empty($member_state['tasks'][$task_key]);
            $form_id = (string) ($member_state['form_id'] ?? '');
            $html .= '<label class="md-member-task-toggle"><input type="checkbox" name="md_checked_tasks[]" value="' . esc_attr($task_key) . '" form="' . esc_attr($form_id) . '" ' . checked($done, true, false) . '> <span>این کار را انجام دادم</span></label>';
        }
        if ($title !== '') {
            $html .= '<h3>' . esc_html($title) . '</h3>';
        }
        if ($desc !== '') {
            $html .= wpautop(esc_html($desc));
        }
        if ($steps !== '') {
            $lines = preg_split('/\\r\\n|\\r|\\n/', $steps);
            $lines = array_filter(array_map('trim', $lines));
            if ($lines) {
                $html .= '<ol class="md-exercise-steps">';
                foreach ($lines as $line) {
                    $html .= '<li>' . esc_html($line) . '</li>';
                }
                $html .= '</ol>';
            }
        }
        $html .= '</section>';
    }
    return $html . '</section>';
}

/**
 * Render the Q&A block of a day.
 *
 * @param WP_Post $day Day post.
 * @return string
 */
function md_challenge_day_qa_html($day) {
    $items = md_challenge_day_qa($day->ID);
    if (!$items) {
        return '';
    }

    $html = '<section class="md-day-qa"><h3>پرسش و پاسخ</h3>';
    $number = 0;
    foreach ($items as $item) {
        $question = isset($item['q']) ? sanitize_textarea_field($item['q']) : '';
        $answer   = isset($item['a']) ? sanitize_textarea_field($item['a']) : '';
        if ('' === trim($question)) {
            continue;
        }
        ++$number;
        $html .= '<div class="md-qa-item">';
        $html .= '<div class="md-qa-question"><span class="md-qa-number">پرسش '
            . esc_html(md_fa_num($number)) . '</span><strong>' . esc_html($question) . '</strong></div>';
        if ('' !== trim($answer)) {
            $html .= '<div class="md-qa-answer">' . wpautop(esc_html($answer)) . '</div>';
        }
        $html .= '</div>';
    }
    $html .= '</section>';
    return $html;
}

/**
 * Render one challenge day.
 *
 * @param WP_Post $day          Day post.
 * @param int     $challenge_id Challenge ID.
 */
function md_challenge_render_day($day, $challenge_id, $member_state = null) {
    if (!$day instanceof WP_Post) {
        return;
    }

    $order       = md_challenge_day_order($day->ID);
    $unlocked    = is_array($member_state) ? !empty($member_state['opened']) : md_challenge_day_is_unlocked($day, $challenge_id);
    $show_title  = md_challenge_show_locked_title($challenge_id) && $day->post_status === 'publish';
    $is_editor   = md_challenge_is_editor($challenge_id);
    $title       = $day->post_title ? $day->post_title : 'روز ' . md_fa_num($order);
    $summary     = sanitize_textarea_field($day->post_excerpt);
    $unlock_date = md_challenge_day_unlock_date($day, $challenge_id);

    $day_dom_id = is_array($member_state) ? 'member-day-' . $day->ID : 'day-' . $order;
    $expanded = is_array($member_state) && isset($_GET['md_saved']) && absint($_GET['md_saved']) === (int) $day->ID;
    echo '<details id="' . esc_attr($day_dom_id) . '" class="md-day md-day-accordion' . ($unlocked ? '' : ' md-day-locked') . '"' . ($expanded ? ' open' : '') . '>';

    echo '<summary class="md-day-header">';
    echo '<span class="md-day-number">روز ' . esc_html(md_fa_num($order)) . '</span>';

    if ($unlocked || $show_title) {
        echo '<h2>' . esc_html($title) . '</h2>';
    } else {
        echo '<h2>روز ' . esc_html(md_fa_num($order)) . '</h2>';
    }

    if (is_array($member_state)) {
        echo '<span class="md-day-preview-badge md-member-progress-badge">' . (!empty($member_state['done']) ? 'انجام‌شده ✓' : ($unlocked ? 'آماده انجام' : '')) . '</span>';
    } elseif ($is_editor) {
        echo '<span class="md-day-preview-badge">پیش‌نمایش مدیر</span>';
    }
    if (!$unlocked) {
        echo '<span class="md-day-lock-badge"><span aria-hidden="true">🔒</span> قفل</span>';
    }
    echo '<span class="md-day-chevron" aria-hidden="true"></span></summary><div class="md-day-body">';

    if (!$unlocked) {
        echo '<div class="md-day-locked-note">';
        if ($unlock_date) {
            echo '<p>این روز در <strong>' . esc_html(md_format_jalali($unlock_date)) . '</strong> باز می‌شود.</p>';
        } elseif ('publish' !== $day->post_status) {
            echo '<p>این روز هنوز منتشر نشده است.</p>';
        } else {
            echo '<p>محتوای این روز هنوز در دسترس نیست.</p>';
        }
        echo '</div></div></details>';
        return;
    }

    if ($summary) {
        echo '<p class="md-day-summary">' . esc_html($summary) . '</p>';
    }

    global $post;
    $original_post = $post;
    $post = $day; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
    setup_postdata($day);
    $content = apply_filters('the_content', $day->post_content);
    wp_reset_postdata();
    $post = $original_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

    if ('' !== trim($day->post_content)) {
        echo '<div class="md-day-content entry-content">' . $content . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    } elseif ($is_editor) {
        echo '<p class="md-empty-day">محتوای این روز هنوز تکمیل نشده است.</p>';
    }

    $extra = sanitize_textarea_field(get_post_meta($day->ID, '_md_day_extra', true));
    if ($extra) {
        echo '<div class="md-day-extra">' . wpautop(esc_html($extra)) . '</div>';
    }

    echo md_challenge_day_tip_html($day); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo md_challenge_day_exercise_html($day, $member_state); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo md_challenge_day_media_html($day); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo md_challenge_day_qa_html($day); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    if (is_array($member_state) && function_exists('md_challenge_member_render_day_form')) {
        md_challenge_member_render_day_form($day, $challenge_id, $member_state);
    }

    echo '</div></details>';
}

/**
 * Day navigation for the single challenge page.
 *
 * @param WP_Post[] $days         Days.
 * @param int       $challenge_id Challenge ID.
 */
function md_challenge_render_day_nav($days, $challenge_id) {
    if (!$days) {
        return;
    }
    ?>
    <nav class="md-day-nav" aria-label="فهرست روزهای چالش">
        <span class="md-day-nav-title">روزهای چالش</span>
        <ol>
            <?php foreach ($days as $day) : ?>
                <?php
                $order    = md_challenge_day_order($day->ID);
                $unlocked = md_challenge_day_is_unlocked($day, $challenge_id);
                $visible_title = $unlocked || md_challenge_show_locked_title($challenge_id);
                $day_title = ($visible_title && $day->post_title) ? $day->post_title : 'روز ' . md_fa_num($order);
                $is_current = $unlocked && md_challenge_current_day($challenge_id) === $order;
                ?>
                <li class="<?php echo esc_attr(($unlocked ? 'is-open' : 'is-locked') . ($is_current ? ' is-current' : '')); ?>">
                    <a href="#day-<?php echo esc_attr($order); ?>" <?php echo $is_current ? 'aria-current="step"' : ''; ?>>
                        <span class="md-day-nav-number"><?php echo esc_html(md_fa_num($order)); ?></span>
                        <span class="md-day-nav-label"><?php echo esc_html($day_title); ?></span>
                        <?php if (!$unlocked) : ?><span class="md-day-nav-lock" aria-label="قفل">🔒</span><?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ol>
    </nav>
    <?php
}

/**
 * Render a challenge card.
 *
 * @param int|null $post_id Challenge ID.
 */
function md_challenge_render_card($post_id = null) {
    $post_id = $post_id ? $post_id : get_the_ID();
    if (!$post_id) {
        return;
    }

    $start      = md_challenge_start($post_id);
    $end        = md_challenge_end($post_id);
    $days       = md_challenge_days($post_id, false);
    $stats      = md_challenge_stats($post_id);
    $time       = md_challenge_time_status($post_id);
    $time_label = md_challenge_time_label($post_id);
    $category   = md_challenge_categories($post_id);

    $excerpt = get_post_field('post_excerpt', $post_id);
    if (!$excerpt) {
        $excerpt = get_post_field('post_content', $post_id);
    }
    ?>
    <article class="md-challenge-card md-challenge-card-<?php echo esc_attr($time); ?>">
        <a class="md-challenge-card-cover" href="<?php echo esc_url(get_permalink($post_id)); ?>" aria-label="<?php echo esc_attr(get_the_title($post_id)); ?>">
            <?php
            if (has_post_thumbnail($post_id)) {
                echo get_the_post_thumbnail($post_id, 'md-card', array('loading' => 'lazy'));
            } else {
                echo '<span class="md-challenge-card-fallback" aria-hidden="true">✦</span>';
            }
            ?>
        </a>
        <div class="md-challenge-card-body">
            <?php if ($time_label) : ?>
                <span class="md-challenge-status md-challenge-status-<?php echo esc_attr($time); ?>"><?php echo esc_html($time_label); ?></span>
            <?php endif; ?>
            <?php if ($category) : ?>
                <span class="md-challenge-category"><?php echo esc_html($category); ?></span>
            <?php endif; ?>
            <h3><a href="<?php echo esc_url(get_permalink($post_id)); ?>"><?php echo esc_html(get_the_title($post_id)); ?></a></h3>
            <p><?php echo esc_html(md_trim_text($excerpt, 18)); ?></p>
            <div class="md-challenge-card-meta">
                <span><?php echo esc_html($start ? md_format_jalali($start, 'short') : 'تاریخ اعلام نشده'); ?></span>
                <span><?php echo esc_html(count($days) ? md_fa_num(count($days)) . ' روز' : md_fa_num($stats['calendar']) . ' روز'); ?></span>
            </div>
            <a class="text-link" href="<?php echo esc_url(get_permalink($post_id)); ?>">مشاهده چالش ←</a>
        </div>
    </article>
    <?php
}

/**
 * Shortcode: [morvarid_challenges].
 *
 * @param array $atts Attributes.
 * @return string
 */
function md_challenge_shortcode($atts) {
    $atts = shortcode_atts(array(
        'count'    => 3,
        'title'    => 'چالش‌های مروارید درون',
        'subtitle' => '',
        'status'   => 'all',
        'category' => '',
    ), $atts, 'morvarid_challenges');

    $args = array(
        'posts_per_page' => max(1, min(12, absint($atts['count']))),
        'orderby'        => 'meta_value',
        'order'          => 'DESC',
    );

    if ('upcoming' === $atts['status']) {
        $args['order'] = 'ASC';
        $args['meta_query'][] = array(
            'key'     => '_md_start_date',
            'value'   => md_today(),
            'compare' => '>',
            'type'    => 'DATE',
        );
    } elseif ('active' === $atts['status']) {
        $args['meta_query'][] = array('key' => '_md_start_date', 'value' => md_today(), 'compare' => '<=', 'type' => 'DATE');
        $args['meta_query'][] = array('key' => '_md_end_date', 'value' => md_today(), 'compare' => '>=', 'type' => 'DATE');
    }

    if ($atts['category']) {
        $args['tax_query'][] = array(
            'taxonomy' => 'md_challenge_cat',
            'field'    => 'slug',
            'terms'    => sanitize_title($atts['category']),
        );
    }

    $query = md_challenge_query($args);
    if (!$query->have_posts()) {
        return '';
    }

    ob_start();
    ?>
    <section class="section md-challenge-grid-section">
        <div class="container">
            <?php if ($atts['title'] || $atts['subtitle']) : ?>
                <div class="section-head">
                    <div>
                        <?php if ($atts['title']) : ?><h2><?php echo esc_html($atts['title']); ?></h2><?php endif; ?>
                        <?php if ($atts['subtitle']) : ?><p><?php echo esc_html($atts['subtitle']); ?></p><?php endif; ?>
                    </div>
                    <?php $archive = get_post_type_archive_link('md_challenge'); ?>
                    <?php if ($archive) : ?><a class="text-link" href="<?php echo esc_url($archive); ?>">همه چالش‌ها ←</a><?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="three-col card-grid md-challenge-grid">
                <?php
                while ($query->have_posts()) {
                    $query->the_post();
                    md_challenge_render_card(get_the_ID());
                }
                ?>
            </div>
        </div>
    </section>
    <?php
    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('morvarid_challenges', 'md_challenge_shortcode');

/**
 * Enqueue public challenge styles.
 */
function md_challenge_public_assets() {
    $is_challenge = is_singular('md_challenge')
        || is_post_type_archive('md_challenge')
        || is_tax('md_challenge_cat');

    if (!$is_challenge) {
        return;
    }

    wp_enqueue_style(
        'md-challenge',
        MD_THEME_URI . '/assets/css/challenge.css',
        array('md-main'),
        MD_THEME_VERSION
    );
}
add_action('wp_enqueue_scripts', 'md_challenge_public_assets', 15);

/**
 * Register a block pattern for the challenge grid.
 */
function md_challenge_register_pattern() {
    if (!function_exists('register_block_pattern')) {
        return;
    }
    register_block_pattern('morvarid/challenge-grid', array(
        'title'       => 'مروارید | چالش‌های چندروزه',
        'description' => 'نمایش خودکار چالش‌های منتشرشده با استفاده از شورت‌کد قالب.',
        'categories'  => array('morvarid'),
        'content'     => '<!-- wp:shortcode -->[morvarid_challenges count="3" title="چالش‌های مروارید درون" subtitle="قدم‌به‌قدم، همراه با هم" status="all"]<!-- /wp:shortcode -->',
    ));
}
add_action('init', 'md_challenge_register_pattern', 20);
