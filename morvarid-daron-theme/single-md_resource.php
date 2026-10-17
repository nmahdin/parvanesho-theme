<?php
/**
 * Unified resource reading layout with separate heading and content sections.
 * @package Morvarid_Daron
 */
get_header();
?>
<main id="main" class="container md-magazine-layout sidebar-right md-resource-single-layout">
    <div class="md-magazine-main">
        <?php while (have_posts()) : the_post();
            $resource_id = get_the_ID();
            $pairs = md_academy_meta_pairs($resource_id);
            $download = md_resource_file_link($resource_id);
        ?>
            <article <?php post_class('md-resource-single'); ?>>
                <header class="md-article-header-card md-resource-header">
                    <a class="md-article-back" href="<?php echo esc_url(get_post_type_archive_link('md_resource')); ?>">← همه منابع آموزشی</a>
                    <div class="eyebrow">آکادمی مروارید درون · منابع آموزشی</div>
                    <h1><?php the_title(); ?></h1>
                    <div class="md-resource-header-meta">
                        <span><?php echo esc_html(get_the_date()); ?></span>
                        <?php foreach ($pairs as $pair) : ?>
                            <span><?php echo esc_html($pair['label'] . ': ' . $pair['value']); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php if (has_excerpt()) : ?><p class="md-resource-description"><?php echo esc_html(get_the_excerpt()); ?></p><?php endif; ?>
                </header>
                <div class="md-resource-reading-card">
                    <?php if (has_post_thumbnail()) : ?>
                        <?php the_post_thumbnail('large', array('class'=>'single-cover')); ?>
                    <?php endif; ?>
                    <div class="entry-content md-resource-content"><?php the_content(); ?></div>
                    <?php if ($download) : ?>
                        <div class="md-resource-download"><?php echo $download; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    <?php endif; ?>
                </div>
            </article>
            <?php if (comments_open() || get_comments_number()) comments_template(); ?>
        <?php endwhile; ?>
    </div>
    <?php md_render_sidebar('academy', 'right'); ?>
</main>
<?php get_footer(); ?>
