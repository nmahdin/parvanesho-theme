<?php
/**
 * Academy archive (courses, lessons and podcasts share this layout).
 *
 * @package Morvarid_Daron
 */

get_header();
?>
<main id="main" class="container md-magazine-layout sidebar-right md-academy-directory">
    <div class="md-magazine-main">
        <header class="md-academy-intro">
            <span class="eyebrow">یادگیری / مروارید درون</span>
            <h1><?php echo esc_html(md_academy_archive_title()); ?></h1>
            <p class="md-section-lead"><?php echo esc_html(md_academy_archive_intro()); ?></p>
        </header>

        <?php md_academy_filters(); ?>

        <?php md_academy_directory(); ?>

        <div class="pagination">
            <?php the_posts_pagination(); ?>
        </div>
    </div>

    <?php md_render_sidebar('academy', 'right'); ?>
</main>
<?php
get_footer();
