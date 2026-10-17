<?php
/**
 * Single lesson.
 *
 * @package Morvarid_Daron
 */

get_header();

while (have_posts()) :
    the_post();

    $lesson_id = get_the_ID();
    $course_id = absint(get_post_meta($lesson_id, '_md_course_id', true));
    $pairs     = md_academy_meta_pairs($lesson_id);
    ?>
    <main id="main" class="container md-magazine-layout sidebar-right md-academy-single">
        <div class="md-magazine-main">
        <article <?php post_class('article-single'); ?>>
            <?php if ($course_id) : ?>
                <a class="text-link" href="<?php echo esc_url(get_permalink($course_id)); ?>">← بازگشت به دوره: <?php echo esc_html(get_the_title($course_id)); ?></a>
            <?php else : ?>
                <a class="text-link" href="<?php echo esc_url(get_post_type_archive_link('md_lesson')); ?>">← همه جلسه‌ها</a>
            <?php endif; ?>

            <div class="eyebrow">آکادمی مروارید درون / جلسه آموزشی</div>
            <h1><?php the_title(); ?></h1>

            <?php if ($pairs) : ?>
                <div class="md-academy-meta">
                    <?php foreach ($pairs as $pair) : ?>
                        <span>
                            <?php echo esc_html($pair['label']); ?>:
                            <?php if (!empty($pair['url'])) : ?>
                                <a href="<?php echo esc_url($pair['url']); ?>"><?php echo esc_html($pair['value']); ?></a>
                            <?php else : ?>
                                <?php echo esc_html($pair['value']); ?>
                            <?php endif; ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (has_post_thumbnail()) : ?>
                <?php the_post_thumbnail('large', array('class' => 'single-cover')); ?>
            <?php endif; ?>

            <?php echo md_academy_media($lesson_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

            <div class="entry-content">
                <?php the_content(); ?>
            </div>

            <?php echo md_academy_lesson_navigation($lesson_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </article>
        </div>
        <?php md_render_sidebar('academy', 'right'); ?>
    </main>
    <?php
endwhile;

get_footer();
