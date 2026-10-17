<?php
get_header();
?>
<main id="main" class="container reading-layout md-standard-page">
<?php while (have_posts()) : the_post();
    $body = get_the_content();
    $has_learning_shell = has_shortcode($body, 'md_learning_register')
        || has_shortcode($body, 'md_learning_login')
        || has_shortcode($body, 'md_learning_dashboard');
    $rendered = apply_filters('the_content', $body);
    $visible = trim(wp_strip_all_tags($rendered));
?>
    <article <?php post_class($has_learning_shell ? 'md-page-learning' : 'md-page-regular'); ?>>
        <?php if (!$has_learning_shell) : ?>
            <h1 class="md-page-heading"><?php the_title(); ?></h1>
        <?php endif; ?>
        <?php if ($visible !== '') : ?>
            <div class="entry-content"><?php echo $rendered; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
        <?php endif; ?>
    </article>
    <?php if (comments_open() || get_comments_number()) comments_template(); ?>
<?php endwhile; ?>
</main>
<?php get_footer(); ?>
