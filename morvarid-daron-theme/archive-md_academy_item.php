<?php get_header(); ?>
<main class="site-main md-academy-archive">
<header><h1><?php post_type_archive_title(); ?></h1></header>
<?php if(have_posts()): ?>
<div class="md-academy-grid">
<?php while(have_posts()): the_post(); ?>
<article <?php post_class('content-card'); ?>>
<a href="<?php the_permalink(); ?>"><?php if(has_post_thumbnail()) the_post_thumbnail('medium'); ?><h2><?php the_title(); ?></h2></a>
<p><?php echo esc_html(get_the_excerpt()); ?></p>
</article>
<?php endwhile; ?>
</div>
<?php the_posts_pagination(); ?>
<?php else: ?><p>هنوز مطلبی منتشر نشده است.</p><?php endif; ?>
</main>
<?php get_footer(); ?>
