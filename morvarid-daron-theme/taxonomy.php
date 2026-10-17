<?php
/**
 * Generic taxonomy archive.
 *
 * Specific taxonomies (md_academy_cat) have their own templates; anything else
 * — tags, categories, custom taxonomies — lands here and reuses the archive
 * layout, with the term description shown above the cards.
 *
 * @package Morvarid_Daron
 */

get_header();

$md_term  = get_queried_object();
$md_layout = get_theme_mod('md_archive_sidebar', 'right');
$md_is_magazine = is_category() || is_tag();
?>
<main id="main" class="container md-magazine-layout <?php echo esc_attr('none' === $md_layout ? 'without-sidebar' : 'sidebar-' . $md_layout); ?>">
    <div class="md-magazine-main">
        <header class="md-archive-head">
            <span class="eyebrow">آرشیو</span>
            <h1><?php echo esc_html(get_the_archive_title()); ?></h1>
            <?php if ($md_term instanceof WP_Term && $md_term->description) : ?>
                <div class="md-archive-intro"><?php echo wp_kses_post(wpautop($md_term->description)); ?></div>
            <?php endif; ?>
        </header>

        <div class="md-archive-cards card-grid">
            <?php if (have_posts()) : ?>
                <?php while (have_posts()) : the_post(); ?>
                    <?php md_card(get_the_ID()); ?>
                <?php endwhile; ?>
            <?php else : ?>
                <p class="empty-note">هنوز محتوایی در این بخش منتشر نشده است.</p>
            <?php endif; ?>
        </div>

        <div class="pagination"><?php the_posts_pagination(); ?></div>
    </div>
    <?php md_render_sidebar('', $md_layout); ?>
</main>
<?php get_footer(); ?>
