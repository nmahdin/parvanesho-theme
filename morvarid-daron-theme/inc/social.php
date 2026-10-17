<?php
/**
 * Social channels: Bale, Telegram and Instagram.
 *
 * The theme deliberately ships no external icon font. Every icon is an inline
 * SVG so the footer and the sidebar widget work offline.
 *
 * @package Morvarid_Daron
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Networks supported by the theme.
 *
 * @return array
 */
function md_social_networks() {
    return array(
        'telegram'  => array(
            'label' => 'تلگرام',
            'hint'  => 'کانال تلگرام',
        ),
        'bale'      => array(
            'label' => 'بله',
            'hint'  => 'کانال بله',
        ),
        'instagram' => array(
            'label' => 'اینستاگرام',
            'hint'  => 'پیج اینستاگرام',
        ),
    );
}

/**
 * Resolve the URL of one network.
 *
 * The theme settings page is the primary source. The customizer value is kept
 * as a fallback for sites configured before the settings page existed.
 *
 * @param string $network Network key.
 * @return string
 */
function md_social_url($network) {
    $networks = md_social_networks();
    if (!isset($networks[$network])) {
        return '';
    }

    $url = md_theme_option($network . '_url', '');
    if (!$url) {
        $url = get_theme_mod('md_' . $network . '_url', '');
    }

    return $url ? esc_url_raw($url) : '';
}

/**
 * Configured networks with their URLs.
 *
 * @return array
 */
function md_social_profiles() {
    $profiles = array();
    foreach (md_social_networks() as $key => $network) {
        $url = md_social_url($key);
        if (!$url) {
            continue;
        }
        $profiles[$key] = array(
            'label' => $network['label'],
            'url'   => $url,
        );
    }

    return $profiles;
}

/**
 * Inline SVG icon for a network.
 *
 * @param string $network Network key.
 * @return string
 */
function md_social_icon($network) {
    switch ($network) {
        case 'telegram':
            return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M21.6 3.3a1.3 1.3 0 0 0-1.4-.2L2.9 10.4c-1 .4-1 1.8.1 2.1l4.2 1.3 1.6 5.1c.3.9 1.4 1.1 2 .4l2.2-2.3 4.4 3.2c.7.5 1.7.1 1.9-.8l3.1-14.8c.1-.5-.1-1-.4-1.3ZM9.5 13.6l8.1-6.4-6.6 7.2-.2 3.1-1.3-3.9Z"/></svg>';
        case 'instagram':
            return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3.4" y="3.4" width="17.2" height="17.2" rx="5" fill="none" stroke="currentColor" stroke-width="1.9"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="1.9"/><circle cx="17.4" cy="6.7" r="1.2" fill="currentColor"/></svg>';
        case 'bale':
        default:
            return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round" d="M4.2 4.8h15.6a1.8 1.8 0 0 1 1.8 1.8v8.7a1.8 1.8 0 0 1-1.8 1.8H11l-4.6 3.1v-3.1H4.2a1.8 1.8 0 0 1-1.8-1.8V6.6a1.8 1.8 0 0 1 1.8-1.8Z"/><circle cx="8" cy="11" r="1.1" fill="currentColor"/><circle cx="12" cy="11" r="1.1" fill="currentColor"/><circle cx="16" cy="11" r="1.1" fill="currentColor"/></svg>';
    }
}

/**
 * Render the social links.
 *
 * @param array $args class, show_labels.
 * @return string
 */
function md_social_links($args = array()) {
    $args = wp_parse_args(
        $args,
        array(
            'class'       => '',
            'show_labels' => true,
        )
    );

    $profiles = md_social_profiles();
    if (!$profiles) {
        return '';
    }

    $html = '<nav class="md-social ' . esc_attr($args['class']) . '" aria-label="شبکه‌های اجتماعی">';
    foreach ($profiles as $key => $profile) {
        $html .= '<a class="md-social-link md-social-' . esc_attr($key) . '" href="' . esc_url($profile['url']) . '" target="_blank" rel="noopener noreferrer">';
        $html .= md_social_icon($key);
        if ($args['show_labels']) {
            $html .= '<span>' . esc_html($profile['label']) . '</span>';
        }
        $html .= '</a>';
    }
    $html .= '</nav>';

    return $html;
}

/**
 * Sidebar card with the social channels.
 */
function md_sidebar_social() {
    $profiles = md_social_profiles();
    if (!$profiles) {
        return;
    }
    ?>
    <section class="md-widget md-widget-social">
        <h2 class="md-widget-title">ما را دنبال کنید</h2>
        <p class="md-widget-social-lead">تازه‌ترین تمرین‌ها، جلسه‌ها و خبر کارگاه‌ها را در کانال‌های مروارید درون ببینید.</p>
        <?php echo md_social_links(array('class' => 'md-social-stack')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </section>
    <?php
}
