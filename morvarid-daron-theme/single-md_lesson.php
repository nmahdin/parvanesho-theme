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
        <article <?php post_class('md-lesson-single'); ?>>
            <header class="md-lesson-header">
                <nav class="md-lesson-breadcrumb" aria-label="مسیر جلسه">
                    <?php if ($course_id) : ?>
                        <a href="<?php echo esc_url(get_permalink($course_id)); ?>">← <?php echo esc_html(get_the_title($course_id)); ?></a>
                    <?php else : ?>
                        <a href="<?php echo esc_url(get_post_type_archive_link('md_lesson')); ?>">← همه جلسه‌ها</a>
                    <?php endif; ?>
                </nav>
                <span class="md-lesson-kicker">جلسه آموزشی · آکادمی مروارید درون</span>
                <h1><?php the_title(); ?></h1>
                <?php if ($pairs) : ?>
                    <div class="md-academy-meta md-lesson-header-meta">
                        <?php foreach ($pairs as $pair) : ?>
                            <span><?php echo esc_html($pair['label']); ?>: <?php if (!empty($pair['url'])) : ?><a href="<?php echo esc_url($pair['url']); ?>"><?php echo esc_html($pair['value']); ?></a><?php else : ?><?php echo esc_html($pair['value']); ?><?php endif; ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </header>
            <div class="md-lesson-media-card">
                <div class="md-lesson-section-label"><span aria-hidden="true">▶</span> محتوای صوتی و تصویری جلسه</div>
            <?php if (has_post_thumbnail()) : ?>
                <?php the_post_thumbnail('large', array('class' => 'single-cover')); ?>
            <?php endif; ?>

            <?php echo md_academy_media($lesson_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
            <div class="md-lesson-reading-card">
                <h2 class="md-lesson-reading-title">محتوای جلسه</h2>
                <div class="entry-content">
                <?php the_content(); ?>
                </div>
            </div>

            <section class="md-lesson-actions-card">
            <?php if (class_exists('MD_Learning_Courses')) : ?>
                <?php echo MD_Learning_Courses::lesson_ui($lesson_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>

            <?php echo md_academy_lesson_navigation($lesson_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </section>
        </article>
        </div>
        <?php md_render_sidebar('academy', 'right'); ?>
    </main>
    <?php
endwhile;

get_footer();
