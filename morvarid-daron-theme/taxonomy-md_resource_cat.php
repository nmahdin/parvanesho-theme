<?php
/**
 * Resource category archive.
 *
 * Resources have their own taxonomy, separate from the courses and the rest of
 * the academy, so their categories never mix.
 *
 * @package Morvarid_Daron
 */

get_header();

$md_term = get_queried_object();
?>
<main id="main" class="md-exercise-archive container md-magazine-layout sidebar-right">
    <div class="md-magazine-main">
        <header class="md-exercise-hero">
            <span class="eyebrow">دسته‌بندی منابع / مروارید درون</span>
            <h1><?php echo esc_html(single_term_title('', false)); ?></h1>
            <p>
                <?php
                if ($md_term instanceof WP_Term && $md_term->description) {
                    echo esc_html($md_term->description);
                } else {
                    echo esc_html('تمرین‌ها و منابع دستهٔ «' . single_term_title('', false) . '» را ببینید.');
                }
                ?>
            </p>
            <a class="text-link" href="<?php echo esc_url(get_post_type_archive_link('md_resource')); ?>">← همه منابع</a>
        </header>

        <?php echo md_resource_categories_nav(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

        <div class="md-exercise-grid" id="md-exercise-results">
            <?php if (have_posts()) : ?>
                <?php
                while (have_posts()) :
                    the_post();
                    md_resource_card(get_the_ID());
                endwhile;
                ?>
            <?php else : ?>
                <p class="md-exercise-empty">در این دسته هنوز منبعی منتشر نشده است.</p>
            <?php endif; ?>
        </div>

        <div class="md-exercise-pagination">
            <?php the_posts_pagination(); ?>
        </div>
    </div>

    <?php md_render_sidebar('academy', 'right'); ?>
</main>
<?php
get_footer();
