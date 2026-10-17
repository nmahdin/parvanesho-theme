<?php
/**
 * Attachment page — full media preview, caption and a link back to the parent.
 *
 * @package Morvarid_Daron
 */

get_header();

while (have_posts()) :
    the_post();

    $md_parent_id = (int) wp_get_post_parent_id(get_the_ID());
    $md_mime      = get_post_mime_type();
    $md_is_image  = $md_mime && 0 === strpos($md_mime, 'image/');
    ?>
    <main id="main" class="container reading-layout">
        <article <?php post_class('article-single attachment-single'); ?>>
            <?php if ($md_parent_id) : ?>
                <a class="text-link" href="<?php echo esc_url(get_permalink($md_parent_id)); ?>">← بازگشت به «<?php echo esc_html(get_the_title($md_parent_id)); ?>»</a>
            <?php else : ?>
                <a class="text-link" href="<?php echo esc_url(home_url('/')); ?>">← بازگشت به خانه</a>
            <?php endif; ?>

            <div class="eyebrow">رسانه</div>
            <h1><?php the_title(); ?></h1>

            <div class="attachment-media">
                <?php if ($md_is_image) : ?>
                    <a href="<?php echo esc_url(wp_get_attachment_url()); ?>" class="attachment-full-link">
                        <?php echo wp_get_attachment_image(get_the_ID(), 'large', false, array('class' => 'attachment-full')); ?>
                    </a>
                <?php elseif ($md_mime && 0 === strpos($md_mime, 'audio/')) : ?>
                    <?php echo md_audio_player(array('src' => wp_get_attachment_url(), 'title' => get_the_title(), 'subtitle' => 'فایل صوتی')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php elseif ($md_mime && 0 === strpos($md_mime, 'video/')) : ?>
                    <?php echo md_video_player(array('src' => wp_get_attachment_url(), 'title' => get_the_title())); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php else : ?>
                    <p><a class="btn btn-primary" href="<?php echo esc_url(wp_get_attachment_url()); ?>" download>دریافت فایل ←</a></p>
                <?php endif; ?>
            </div>

            <?php if (has_excerpt()) : ?>
                <p class="attachment-caption"><?php echo esc_html(get_the_excerpt()); ?></p>
            <?php endif; ?>

            <div class="entry-content"><?php the_content(); ?></div>
            <?php wp_link_pages(); ?>
            <?php comments_template(); ?>
        </article>
    </main>
    <?php
endwhile;

get_footer();
