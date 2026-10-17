<?php
get_header();
$layout = get_theme_mod('md_single_sidebar', 'right');
?>
<main id="main" class="container md-magazine-layout <?php echo esc_attr($layout === 'none' ? 'without-sidebar' : 'sidebar-' . $layout); ?>">
    <div class="md-magazine-main">
        <?php while (have_posts()) : the_post(); ?>
            <article <?php post_class('article-single md-article-redesign'); ?>>
                <header class="md-article-header-card">
                    <a class="md-article-back" href="<?php echo esc_url(get_option('page_for_posts') ? get_permalink(get_option('page_for_posts')) : home_url('/')); ?>">← بازگشت به مجله</a>
                    <div class="eyebrow">مجله مروارید درون</div>
                    <h1><?php the_title(); ?></h1>
                    <div class="md-article-details">
                        <span class="md-article-detail"><span aria-hidden="true">◷</span> <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date()); ?></time></span>
                        <?php $author_id = get_the_author_meta('ID'); if ($author_id) : ?>
                            <span class="md-article-detail"><span aria-hidden="true">✎</span> <?php echo esc_html(get_the_author()); ?></span>
                        <?php endif; ?>
                        <?php $terms = get_the_category(); if ($terms) : ?>
                            <span class="md-article-detail md-article-categories"><span aria-hidden="true">⌁</span>
                                <?php foreach ($terms as $index => $term) : ?>
                                    <?php if ($index) echo '، '; ?><a href="<?php echo esc_url(get_category_link($term->term_id)); ?>"><?php echo esc_html($term->name); ?></a>
                                <?php endforeach; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </header>
                <div class="md-article-content-card">
                    <?php echo md_post_media_html(get_the_ID()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php if (has_post_thumbnail()) : the_post_thumbnail('large', array('class' => 'single-cover')); endif; ?>
                    <div class="entry-content"><?php the_content(); ?></div>
                    <?php wp_link_pages(); ?>
                </div>
            </article>
            <?php comments_template(); ?>
        <?php endwhile; ?>
    </div>
    <?php md_render_sidebar('magazine', $layout); ?>
</main>
<?php get_footer(); ?>
