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
