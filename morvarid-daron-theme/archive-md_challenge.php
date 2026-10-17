<?php
/**
 * Challenge archive.
 *
 * @package Morvarid_Daron
 */

get_header();
?>
<main id="main" class="container md-magazine-layout sidebar-right md-challenge-archive">
    <div class="md-magazine-main">
        <header class="md-challenge-archive-head">
            <span class="eyebrow">همراهی گام‌به‌گام / مروارید درون</span>
            <h1><?php echo esc_html(md_theme_option('challenges_title', 'چالش‌های مروارید درون')); ?></h1>
            <p class="md-section-lead"><?php echo esc_html(md_theme_option('challenges_intro', 'چالش‌های چندروزه برای ساختن عادت‌های تازه، شناخت الگوها و قدم‌های کوچک و پیوسته.')); ?></p>
        </header>

        <?php if (have_posts()) : ?>
            <div class="three-col card-grid md-challenge-grid">
                <?php
                while (have_posts()) :
                    the_post();
                    md_challenge_render_card(get_the_ID());
                endwhile;
                ?>
            </div>
            <div class="pagination">
                <?php the_posts_pagination(); ?>
            </div>
        <?php else : ?>
            <p class="empty-note">هنوز چالشی منتشر نشده است. به‌زودی چالش‌های تازه اینجا نمایش داده می‌شوند.</p>
        <?php endif; ?>
    </div>

    <?php md_render_sidebar('challenge', 'right'); ?>
</main>
<?php
get_footer();
