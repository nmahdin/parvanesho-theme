<?php
/**
 * Front page.
 *
 * When a static front page with content exists, its blocks are the homepage:
 * every element (hero, sections, cards, newsletter) is edited in Gutenberg on
 * that page. Otherwise the built-in fallback layout keeps the site complete.
 *
 * @package Morvarid_Daron
 */

get_header();

$md_home_id      = function_exists('md_home_page_id') ? md_home_page_id() : 0;
$md_home_content = $md_home_id ? (string) get_post_field('post_content', $md_home_id) : '';
$md_home_blank   = '' === trim(wp_strip_all_tags((string) preg_replace('/<!--.*?-->/s', '', $md_home_content)));

if ($md_home_id && !$md_home_blank) {
    $md_home_query = new WP_Query(
        array(
            'page_id'        => $md_home_id,
            'post_type'      => 'page',
            'posts_per_page' => 1,
        )
    );

    echo '<main id="main" class="md-flex-home">';
    while ($md_home_query->have_posts()) {
        $md_home_query->the_post();
        the_content();
    }
    echo '</main>';
    wp_reset_postdata();
} else {
    get_template_part('template-parts/home', 'fallback');
}

get_footer();
