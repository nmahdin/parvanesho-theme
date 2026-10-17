<?php
/**
 * Storytelling homepage sections inspired by the supplied booklet layout.
 * This is a structural design only; the theme's existing color tokens remain
 * authoritative. Nothing writes to an existing homepage or its content.
 *
 * @package Morvarid_Daron
 */
if (!defined('ABSPATH')) {
    exit;
}

function md_home_editorial_render($attributes, $content, $block) {
    $name = isset($block->name) ? $block->name : '';
    $title = trim((string) ($attributes['title'] ?? ''));
    $eyebrow = trim((string) ($attributes['eyebrow'] ?? ''));
    $text = trim((string) ($attributes['text'] ?? ''));
    $title_html = $title !== '' ? '<h2>' . esc_html($title) . '</h2>' : '';
    $eyebrow_html = $eyebrow !== '' ? '<span class="md-home-ref-kicker">' . esc_html($eyebrow) . '</span>' : '';

    if ($name === 'morvarid/welcome') {
        $quote = trim((string) ($attributes['quote'] ?? ''));
        if ($title === '' && $text === '' && $quote === '') {
            return '';
        }
        return '<section class="md-home-ref md-home-ref-welcome"><div class="container"><div class="md-home-ref-welcome-inner">'
            . '<span class="md-home-ref-mark" aria-hidden="true">✦</span>'
            . $eyebrow_html . $title_html
            . ($text !== '' ? '<div class="md-home-ref-lead">' . wpautop(esc_html($text)) . '</div>' : '')
            . ($quote !== '' ? '<blockquote class="md-home-ref-quote">' . wpautop(esc_html($quote)) . '</blockquote>' : '')
            . '</div></div></section>';
    }

    if ($name === 'morvarid/story') {
        $image_id = absint($attributes['imageId'] ?? 0);
        $image = $image_id && wp_attachment_is_image($image_id)
            ? wp_get_attachment_image($image_id, 'large', false, array(
                'loading' => 'lazy', 'decoding' => 'async',
                'class' => 'md-home-ref-story-image',
            )) : '';
        if ($title === '' && $text === '' && !$image) {
            return '';
        }
        $caption = trim((string) ($attributes['caption'] ?? ''));
        $story_media = $image ? '<figure class="md-home-ref-story-media">' . $image
            . ($caption !== '' ? '<figcaption>' . esc_html($caption) . '</figcaption>' : '')
            . '</figure>' : '';
        return '<section class="md-home-ref md-home-ref-story"><div class="container"><div class="md-home-ref-story-grid'
            . ($story_media ? ' has-image' : '') . '">'
            . '<div class="md-home-ref-story-copy">' . $eyebrow_html . $title_html
            . ($text !== '' ? '<div class="md-home-ref-prose">' . wpautop(esc_html($text)) . '</div>' : '')
            . '</div>' . $story_media . '</div></div></section>';
    }

    if ($name === 'morvarid/principles' || $name === 'morvarid/values') {
        $items = isset($attributes['items']) && is_array($attributes['items'])
            ? array_slice($attributes['items'], 0, 12) : array();
        $cards = '';
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $item_title = trim((string) ($item['title'] ?? ''));
            $item_text = trim((string) ($item['text'] ?? ''));
            $item_icon = trim((string) ($item['icon'] ?? ''));
            if ($item_title === '' && $item_text === '') {
                continue;
            }
            $icon = $item_icon !== '' ? '<span class="md-home-ref-icon" aria-hidden="true">'
                . esc_html($item_icon) . '</span>' : '';
            $cards .= '<article class="md-home-ref-item">' . $icon . '<div>'
                . ($item_title !== '' ? '<h3>' . esc_html($item_title) . '</h3>' : '')
                . ($item_text !== '' ? '<p>' . esc_html($item_text) . '</p>' : '')
                . '</div></article>';
        }
        if ($title === '' && $cards === '') {
            return '';
        }
        $is_values = $name === 'morvarid/values';
        return '<section class="md-home-ref ' . ($is_values ? 'md-home-ref-values' : 'md-home-ref-principles')
            . '"><div class="container"><div class="md-home-ref-panel">'
            . '<header class="md-home-ref-heading">' . $eyebrow_html . $title_html
            . ($text !== '' ? '<p>' . esc_html($text) . '</p>' : '') . '</header>'
            . '<div class="md-home-ref-items">' . $cards . '</div>'
            . '</div></div></section>';
    }

    return $content;
}

add_action('init', function () {
    $handle = 'md-home-editorial-editor';
    wp_register_script($handle, MD_THEME_URI . '/assets/js/home-editorial.js', array(
        'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render',
    ), MD_THEME_VERSION, true);
    $common = array(
        'eyebrow' => array('type' => 'string', 'default' => ''),
        'title' => array('type' => 'string', 'default' => ''),
        'text' => array('type' => 'string', 'default' => ''),
    );
    $types = array(
        'welcome' => array_merge($common, array('quote' => array('type' => 'string', 'default' => ''))),
        'story' => array_merge($common, array(
            'imageId' => array('type' => 'number', 'default' => 0),
            'caption' => array('type' => 'string', 'default' => ''),
        )),
        'principles' => array_merge($common, array('items' => array('type' => 'array', 'default' => array()))),
        'values' => array_merge($common, array('items' => array('type' => 'array', 'default' => array()))),
    );
    foreach ($types as $slug => $attributes) {
        register_block_type('morvarid/' . $slug, array(
            'api_version' => 2,
            'editor_script' => $handle,
            'attributes' => $attributes,
            'render_callback' => 'md_home_editorial_render',
            'supports' => array('html' => false, 'anchor' => true),
        ));
    }
}, 11);

/** Insertion pattern, NOT an automatic overwrite of published homepage. */
add_action('init', function () {
    if (!function_exists('register_block_pattern') || !function_exists('md_home_block')) {
        return;
    }
    register_block_pattern('morvarid/home-storytelling', array(
        'title' => 'صفحه اصلی — روایت، رویکرد و ارزش‌ها',
        'description' => 'چیدمان محتوایی شبیه کتابچه: خوش‌آمد، داستان، کارت‌های اصول و ارزش‌ها؛ با رنگ‌های فعلی مروارید.',
        'categories' => array('featured'),
        'content' => implode("\n", array(
            md_home_block('morvarid/welcome', array(
                'eyebrow' => 'خوش‌آمدید',
                'title' => 'اینجا فرصتی برای مکث و کشف است',
                'text' => 'مروارید درون فضایی برای یادگیری، تجربه‌کردن و گفت‌وگوی آگاهانه است.',
                'quote' => 'نه نصیحت، نه قضاوت؛ فقط یک سؤال تازه برای کشف.',
            )),
            md_home_block('morvarid/story', array(
                'eyebrow' => 'داستان مروارید درون',
                'title' => 'چرا این مسیر را آغاز کردیم؟',
                'text' => 'هر انسان داستانی دارد؛ گاهی شنیده‌شدن و یک پرسش تازه می‌تواند آغاز نگاه متفاوتی باشد. اینجا می‌خواهیم برای شنیدن، کشف و انتخاب آگاهانه جا باز کنیم.',
            )),
            md_home_block('morvarid/principles', array(
                'title' => 'رویکرد ما به رشد و یادگیری',
                'items' => array(
                    array('icon' => '✦', 'title' => 'شنیدن پیش از پاسخ‌دادن', 'text' => 'برای فهمیدن مکث می‌کنیم.'),
                    array('icon' => '◈', 'title' => 'کشف به‌جای نسخه‌پیچی', 'text' => 'با پرسش، امکان نگاه تازه را باز می‌کنیم.'),
                    array('icon' => '✧', 'title' => 'قدم‌های کوچک و واقعی', 'text' => 'آگاهی را به تجربه و اقدام پیوند می‌دهیم.'),
                ),
            )),
            md_home_block('morvarid/values', array(
                'title' => 'ارزش‌هایی که مسیر را می‌سازند',
                'items' => array(
                    array('icon' => '✧', 'title' => 'احترام', 'text' => 'هر فرد جهان و تجربهٔ منحصربه‌فردی دارد.'),
                    array('icon' => '◈', 'title' => 'امنیت در گفت‌وگو', 'text' => 'می‌کوشیم فضا برای بیان آزادانه فراهم باشد.'),
                    array('icon' => '✦', 'title' => 'کنجکاوی و یادگیری', 'text' => 'برای دیدن امکان‌های تازه سؤال می‌پرسیم.'),
                ),
            )),
        )),
    ));
}, 25);
