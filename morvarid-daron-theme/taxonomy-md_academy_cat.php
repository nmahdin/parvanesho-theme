<?php
/**
 * Academy category archive.
 *
 * @package Morvarid_Daron
 */

get_header();
?>
<main id="main" class="container md-academy-directory">
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
</main>
<?php
get_footer();
