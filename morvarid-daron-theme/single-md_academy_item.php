<?php get_header(); ?>
<main class="site-main md-unified-academy">
<?php while(have_posts()): the_post();
$kind=get_post_meta(get_the_ID(),'_md_academy_item_kind',true) ?: 'article';
$media=get_post_meta(get_the_ID(),'_md_academy_item_media',true) ?: 'video';
$url=get_post_meta(get_the_ID(),'_md_academy_item_url',true);
?>
<article <?php post_class('md-academy-entry'); ?>>
<header class="md-academy-entry-header"><h1><?php the_title(); ?></h1></header>
<?php if (has_post_thumbnail()) the_post_thumbnail('large'); ?>
<?php if (in_array($kind,array('media','mixed'),true) && $url): ?>
<div class="md-academy-player">
<?php
if ($media==='audio') echo wp_audio_shortcode(array('src'=>$url));
else echo wp_video_shortcode(array('src'=>$url));
?>
</div>
<?php endif; ?>
<div class="entry-content"><?php the_content(); ?></div>
</article>
<?php comments_template(); endwhile; ?>
</main>
<?php get_footer(); ?>
