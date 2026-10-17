<?php
/**
 * Author archive — biography plus the author's magazine posts.
 *
 * @package Morvarid_Daron
 */

get_header();

$md_author_id   = (int) get_query_var('author');
$md_author      = get_userdata($md_author_id);
$md_layout      = get_theme_mod('md_archive_sidebar', 'right');
$md_author_desc = $md_author ? get_the_author_meta('description', $md_author_id) : '';
?>
<main id="main" class="container md-magazine-layout <?php echo esc_attr('none' === $md_layout ? 'without-sidebar' : 'sidebar-' . $md_layout); ?>">
    <div class="md-magazine-main">
        <header class="md-archive-head md-author-head">
            <?php if ($md_author) : ?>
                <div class="md-author-avatar"><?php echo get_avatar($md_author_id, 84); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <?php endif; ?>
            <div>
                <span class="eyebrow">نویسنده</span>
                <h1><?php echo esc_html(get_the_archive_title()); ?></h1>
                <?php if ($md_author_desc) : ?>
                    <p class="md-archive-intro"><?php echo esc_html($md_author_desc); ?></p>
                <?php endif; ?>
                <?php if ($md_author && $md_author->user_url) : ?>
                    <p class="md-author-link"><a class="text-link" href="<?php echo esc_url($md_author->user_url); ?>" rel="nofollow">وب‌سایت نویسنده ←</a></p>
                <?php endif; ?>
            </div>
        </header>

        <div class="md-archive-cards card-grid">
            <?php if (have_posts()) : ?>
                <?php while (have_posts()) : the_post(); ?>
                    <?php md_card(get_the_ID(), 'مجله'); ?>
                <?php endwhile; ?>
            <?php else : ?>
                <p class="empty-note">این نویسنده هنوز نوشته‌ای منتشر نکرده است.</p>
            <?php endif; ?>
        </div>

        <div class="pagination"><?php the_posts_pagination(); ?></div>
    </div>
    <?php md_render_sidebar('magazine', $md_layout); ?>
</main>
<?php get_footer(); ?>
