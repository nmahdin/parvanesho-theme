<?php
/**
 * Branded audio and video players.
 *
 * Native <audio>/<video> elements are kept for playback, but the controls are
 * replaced by the theme's own markup so the player matches the site design.
 *
 * @package Morvarid_Daron
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Unique id for a player instance.
 *
 * @return string
 */
function md_player_uid() {
    static $counter = 0;
    ++$counter;
    return 'md-player-' . $counter;
}

/**
 * Resolve a media source from an attachment id or a URL.
 *
 * @param int|string $source Attachment id or URL.
 * @return string
 */
function md_media_src($source) {
    if (is_numeric($source)) {
        $url = wp_get_attachment_url(absint($source));
        return $url ? $url : '';
    }
    return esc_url_raw((string) $source);
}

/**
 * Resolve a poster image URL.
 *
 * @param int|string $source Attachment id or URL.
 * @param string     $size   Image size.
 * @return string
 */
function md_media_poster($source, $size = 'large') {
    if (is_numeric($source)) {
        $url = wp_get_attachment_image_url(absint($source), $size);
        return $url ? $url : '';
    }
    return esc_url_raw((string) $source);
}

/**
 * SVG icon used inside the players.
 *
 * @param string $name Icon name.
 * @return string
 */
function md_player_icon($name) {
    $icons = array(
        'play'       => '<path d="M8 5.5v13l11-6.5z"/>',
        'pause'      => '<rect x="7" y="5" width="3.6" height="14" rx="1.4"/><rect x="13.4" y="5" width="3.6" height="14" rx="1.4"/>',
        'volume'     => '<path d="M4 9.5h3.5L12 5.5v13L7.5 14.5H4z"/><path d="M15.5 8.6a5 5 0 0 1 0 6.8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M18 6.2a8.5 8.5 0 0 1 0 11.6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
        'muted'      => '<path d="M4 9.5h3.5L12 5.5v13L7.5 14.5H4z"/><path d="m16 9.5 5 5m0-5-5 5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>',
        'fullscreen' => '<path d="M4 9V4h5M15 4h5v5M20 15v5h-5M9 20H4v-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'download'   => '<path d="M12 4v11m0 0 4.2-4.2M12 15l-4.2-4.2M5 19h14" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>',
        'headphone'  => '<path d="M4 13a8 8 0 0 1 16 0" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/><rect x="3" y="13" width="4.5" height="7" rx="2"/><rect x="16.5" y="13" width="4.5" height="7" rx="2"/>',
    );

    if (!isset($icons[$name])) {
        return '';
    }

    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $icons[$name] . '</svg>';
}

/**
 * Render the theme audio player.
 *
 * @param array $args {
 *     @type string $src      Audio URL.
 *     @type string $title    Track title.
 *     @type string $subtitle Small label above the title.
 *     @type string $download Whether to show a download link.
 *     @type string $class    Extra CSS classes.
 * }
 * @return string
 */
function md_audio_player($args = array()) {
    $args = wp_parse_args(
        $args,
        array(
            'src'      => '',
            'title'    => '',
            'subtitle' => 'پادکست مروارید درون',
            'download' => '',
            'class'    => '',
        )
    );

    $src = md_media_src($args['src']);
    if (!$src) {
        return '';
    }

    $id       = md_player_uid();
    $download = '' !== $args['download'] ? $args['download'] : $src;
    $classes  = trim('md-player md-audio-player ' . $args['class']);

    ob_start();
    ?>
    <figure class="<?php echo esc_attr($classes); ?>" id="<?php echo esc_attr($id); ?>" data-md-player="audio">
        <audio preload="metadata" src="<?php echo esc_url($src); ?>"></audio>

        <div class="md-audio-inner">
            <button type="button" class="md-audio-toggle" data-md-action="toggle" aria-label="پخش">
                <svg class="md-audio-ring" viewBox="0 0 100 100" aria-hidden="true" focusable="false">
                    <circle class="md-audio-ring-bg" cx="50" cy="50" r="45"></circle>
                    <circle class="md-audio-ring-fill" cx="50" cy="50" r="45"></circle>
                </svg>
                <span class="md-audio-icon md-icon-play"><?php echo md_player_icon('play'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <span class="md-audio-icon md-icon-pause"><?php echo md_player_icon('pause'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            </button>

            <div class="md-audio-body">
                <div class="md-audio-head">
                    <?php if ($args['subtitle']) : ?>
                        <span class="md-audio-sub"><?php echo esc_html($args['subtitle']); ?></span>
                    <?php endif; ?>
                    <?php if ($args['title']) : ?>
                        <span class="md-audio-title"><?php echo esc_html($args['title']); ?></span>
                    <?php endif; ?>
                </div>

                <div class="md-seek" data-md-action="seek" role="slider" tabindex="0"
                     aria-label="جایگاه پخش" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                    <span class="md-seek-track"><span class="md-seek-fill"></span></span>
                    <span class="md-seek-knob"></span>
                </div>

                <div class="md-audio-footer">
                    <span class="md-player-time" data-md-time>۰:۰۰ / ۰:۰۰</span>
                    <div class="md-player-actions">
                        <button type="button" class="md-player-btn md-rate-btn" data-md-action="rate" aria-label="سرعت پخش">۱×</button>
                        <button type="button" class="md-player-btn" data-md-action="mute" aria-label="بی‌صدا">
                            <span class="md-icon-volume"><?php echo md_player_icon('volume'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                            <span class="md-icon-muted"><?php echo md_player_icon('muted'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        </button>
                        <a class="md-player-btn md-download-btn" href="<?php echo esc_url($download); ?>" download aria-label="دریافت فایل">
                            <?php echo md_player_icon('download'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </figure>
    <?php
    return ob_get_clean();
}

/**
 * Render the theme video player for a local file.
 *
 * @param array $args {
 *     @type string $src    Video URL.
 *     @type string $poster Poster URL.
 *     @type string $title  Video title.
 *     @type string $class  Extra CSS classes.
 * }
 * @return string
 */
function md_video_player($args = array()) {
    $args = wp_parse_args(
        $args,
        array(
            'src'    => '',
            'poster' => '',
            'title'  => '',
            'class'  => '',
        )
    );

    $src = md_media_src($args['src']);
    if (!$src) {
        return '';
    }

    $id      = md_player_uid();
    $poster  = md_media_poster($args['poster']);
    $classes = trim('md-player md-video-player ' . $args['class']);

    ob_start();
    ?>
    <figure class="<?php echo esc_attr($classes); ?>" id="<?php echo esc_attr($id); ?>" data-md-player="video">
        <div class="md-video-stage">
            <video preload="metadata" playsinline <?php echo $poster ? 'poster="' . esc_url($poster) . '"' : ''; ?> src="<?php echo esc_url($src); ?>"></video>

            <?php if ($args['title']) : ?>
                <span class="md-video-badge"><?php echo esc_html($args['title']); ?></span>
            <?php endif; ?>

            <button type="button" class="md-video-bigplay" data-md-action="toggle" aria-label="پخش ویدیو">
                <span class="md-video-pearl"><?php echo md_player_icon('play'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            </button>

            <div class="md-video-bar" data-md-controls>
                <button type="button" class="md-player-btn" data-md-action="toggle" aria-label="پخش">
                    <span class="md-icon-play"><?php echo md_player_icon('play'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <span class="md-icon-pause"><?php echo md_player_icon('pause'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                </button>

                <span class="md-player-time" data-md-time>۰:۰۰ / ۰:۰۰</span>

                <div class="md-seek" data-md-action="seek" role="slider" tabindex="0"
                     aria-label="جایگاه پخش" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                    <span class="md-seek-track"><span class="md-seek-fill"></span></span>
                    <span class="md-seek-knob"></span>
                </div>

                <button type="button" class="md-player-btn" data-md-action="mute" aria-label="بی‌صدا">
                    <span class="md-icon-volume"><?php echo md_player_icon('volume'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <span class="md-icon-muted"><?php echo md_player_icon('muted'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                </button>
                <button type="button" class="md-player-btn" data-md-action="fullscreen" aria-label="تمام‌صفحه">
                    <?php echo md_player_icon('fullscreen'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </button>
            </div>
        </div>
    </figure>
    <?php
    return ob_get_clean();
}

/**
 * Render a video from an external provider (Aparat, YouTube, Vimeo…).
 *
 * @param string $url   Video URL.
 * @param string $title Optional title.
 * @return string
 */
function md_video_embed($url, $title = '') {
    $url = esc_url_raw($url);
    if (!$url) {
        return '';
    }

    $embed = wp_oembed_get($url, array('width' => 960));
    if (!$embed) {
        return '<p class="md-media-link"><a class="md-button" href="' . esc_url($url) . '" rel="nofollow noopener" target="_blank">تماشای ویدیو</a></p>';
    }

    $html  = '<figure class="md-player md-video-embed">';
    $html .= '<div class="md-video-embed-frame">' . $embed . '</div>';
    if ($title) {
        $html .= '<figcaption>' . esc_html($title) . '</figcaption>';
    }
    $html .= '</figure>';

    return $html;
}

/**
 * [md_audio src="..." title="..."]
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function md_audio_shortcode($atts) {
    $atts = shortcode_atts(
        array('src' => '', 'id' => '', 'title' => '', 'subtitle' => 'پادکست مروارید درون'),
        $atts,
        'md_audio'
    );

    return md_audio_player(
        array(
            'src'      => $atts['id'] ? absint($atts['id']) : $atts['src'],
            'title'    => $atts['title'],
            'subtitle' => $atts['subtitle'],
        )
    );
}
add_shortcode('md_audio', 'md_audio_shortcode');

/**
 * [md_video src="..." poster="..." title="..."]
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function md_video_shortcode($atts) {
    $atts = shortcode_atts(
        array('src' => '', 'id' => '', 'poster' => '', 'title' => ''),
        $atts,
        'md_video'
    );

    return md_video_player(
        array(
            'src'    => $atts['id'] ? absint($atts['id']) : $atts['src'],
            'poster' => $atts['poster'],
            'title'  => $atts['title'],
        )
    );
}
add_shortcode('md_video', 'md_video_shortcode');

/**
 * Enqueue the player assets on the front end.
 */
function md_player_assets() {
    wp_enqueue_style('md-player', MD_THEME_URI . '/assets/css/player.css', array('md-main'), MD_THEME_VERSION);
    wp_enqueue_script('md-player', MD_THEME_URI . '/assets/js/player.js', array(), MD_THEME_VERSION, true);
}
add_action('wp_enqueue_scripts', 'md_player_assets', 20);

/* --------------------------------------------------------- post audio/video */

/**
 * Meta box: attach an audio file and a video to a regular article.
 */
function md_post_add_media_box() {
    add_meta_box(
        'md_post_media',
        'صدا و ویدیوی مقاله',
        'md_post_render_media_box',
        'post',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'md_post_add_media_box');

/**
 * Render the article media meta box.
 *
 * @param WP_Post $post Post object.
 */
function md_post_render_media_box($post) {
    wp_nonce_field('md_post_media', 'md_post_media_nonce');

    $audio     = absint(get_post_meta($post->ID, '_md_post_audio_id', true));
    $video     = absint(get_post_meta($post->ID, '_md_post_video_id', true));
    $video_url = esc_url_raw(get_post_meta($post->ID, '_md_post_video_url', true));
    $title     = sanitize_text_field(get_post_meta($post->ID, '_md_post_media_title', true));
    ?>
    <div class="md-admin-fields md-post-media-fields">
        <p class="description">فایل صوتی یا ویدیوی این مقاله را انتخاب کنید. پخش‌کنندهٔ اختصاصی قالب زیر عنوان مقاله نمایش داده می‌شود.</p>

        <div class="md-admin-field">
            <label for="md-post-audio">فایل صوتی مقاله</label>
            <div class="md-media-field">
                <input type="hidden" id="md-post-audio" name="md_post_audio_id" value="<?php echo esc_attr($audio); ?>" data-media-id="<?php echo esc_attr($audio); ?>">
                <button type="button" class="button md-media-select" data-target="md-post-audio" data-type="audio">انتخاب از رسانه</button>
                <button type="button" class="button md-media-remove" data-target="md-post-audio">حذف</button>
                <span class="md-media-name" id="md-post-audio_name"><?php echo esc_html(md_attachment_name($audio)); ?></span>
            </div>
        </div>

        <div class="md-admin-field">
            <label for="md-post-video">فایل ویدیوی مقاله</label>
            <div class="md-media-field">
                <input type="hidden" id="md-post-video" name="md_post_video_id" value="<?php echo esc_attr($video); ?>" data-media-id="<?php echo esc_attr($video); ?>">
                <button type="button" class="button md-media-select" data-target="md-post-video" data-type="video">انتخاب از رسانه</button>
                <button type="button" class="button md-media-remove" data-target="md-post-video">حذف</button>
                <span class="md-media-name" id="md-post-video_name"><?php echo esc_html(md_attachment_name($video)); ?></span>
            </div>
        </div>

        <div class="md-admin-field">
            <label for="md-post-video-url">یا لینک ویدیوی خارجی</label>
            <input type="url" id="md-post-video-url" name="md_post_video_url" class="regular-text" dir="ltr" placeholder="https://" value="<?php echo esc_attr($video_url); ?>">
            <p class="description">برای آپارات، یوتیوب یا هر سرویس پشتیبانی‌شده.</p>
        </div>

        <div class="md-admin-field">
            <label for="md-post-media-title">عنوان رسانه</label>
            <input type="text" id="md-post-media-title" name="md_post_media_title" class="regular-text" value="<?php echo esc_attr($title); ?>">
        </div>
    </div>
    <?php
}

/**
 * Save the article media fields.
 *
 * @param int $post_id Post ID.
 */
function md_post_save_media($post_id) {
    if (!isset($_POST['md_post_media_nonce'])) {
        return;
    }
    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['md_post_media_nonce'])), 'md_post_media')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $map = array(
        '_md_post_audio_id'  => isset($_POST['md_post_audio_id']) ? absint($_POST['md_post_audio_id']) : 0,
        '_md_post_video_id'  => isset($_POST['md_post_video_id']) ? absint($_POST['md_post_video_id']) : 0,
        '_md_post_video_url' => isset($_POST['md_post_video_url']) ? esc_url_raw(wp_unslash($_POST['md_post_video_url'])) : '',
        '_md_post_media_title' => isset($_POST['md_post_media_title']) ? sanitize_text_field(wp_unslash($_POST['md_post_media_title'])) : '',
    );

    foreach ($map as $key => $value) {
        if ('' === $value || 0 === $value || '0' === $value) {
            delete_post_meta($post_id, $key);
        } else {
            update_post_meta($post_id, $key, $value);
        }
    }
}
add_action('save_post_post', 'md_post_save_media');

/**
 * Media picker on the article editor.
 *
 * @param string $hook Current admin page.
 */
function md_post_media_admin_assets($hook) {
    if (!in_array($hook, array('post.php', 'post-new.php'), true)) {
        return;
    }
    $screen = get_current_screen();
    if (!$screen || 'post' !== $screen->post_type) {
        return;
    }
    wp_enqueue_media();
    wp_enqueue_style('md-admin-fields', MD_THEME_URI . '/assets/css/admin-fields.css', array(), MD_THEME_VERSION);
    wp_enqueue_script('md-admin-media', MD_THEME_URI . '/assets/js/admin-media.js', array('jquery'), MD_THEME_VERSION, true);
}
add_action('admin_enqueue_scripts', 'md_post_media_admin_assets');

/**
 * Player markup for the current article.
 *
 * @param int|null $post_id Post ID.
 * @return string
 */
function md_post_media_html($post_id = null) {
    $post_id = $post_id ? $post_id : get_the_ID();
    if (!$post_id) {
        return '';
    }

    $audio     = absint(get_post_meta($post_id, '_md_post_audio_id', true));
    $video     = absint(get_post_meta($post_id, '_md_post_video_id', true));
    $video_url = esc_url_raw(get_post_meta($post_id, '_md_post_video_url', true));
    $title     = sanitize_text_field(get_post_meta($post_id, '_md_post_media_title', true));

    $players = '';

    if ($audio) {
        $players .= md_audio_player(
            array(
                'src'      => $audio,
                'title'    => $title ? $title : get_the_title($post_id),
                'subtitle' => 'نسخهٔ صوتی این مقاله',
            )
        );
    }

    if ($video_url) {
        $players .= md_video_embed($video_url, $title);
    } elseif ($video) {
        $players .= md_video_player(
            array(
                'src'   => $video,
                'title' => $title,
            )
        );
    }

    if (!$players) {
        return '';
    }

    return '<div class="md-post-media">' . $players . '</div>';
}
