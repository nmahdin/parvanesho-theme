<?php
/**
 * Single course.
 *
 * @package Morvarid_Daron
 */

get_header();

while (have_posts()) :
    the_post();

    $course_id = get_the_ID();
    $pairs     = md_academy_meta_pairs($course_id);
    $price     = get_post_meta($course_id, '_md_course_price', true);
    $cta       = esc_url_raw(get_post_meta($course_id, '_md_course_cta_url', true));
    if (!$cta) {
        $cta = md_booking_url();
    }
    $lessons   = md_get_course_lessons($course_id, current_user_can('edit_post', $course_id));
    ?>
    <main id="main" class="container md-magazine-layout sidebar-right md-academy-single">
        <div class="md-magazine-main">
        <article <?php post_class('md-course-single'); ?>>
            <header class="md-course-header-card md-article-header-card">
                <a class="md-article-back" href="<?php echo esc_url(get_post_type_archive_link('md_course')); ?>">← همه دوره‌ها</a>
                <div class="eyebrow">آکادمی مروارید درون · دوره آموزشی</div>
                <h1><?php the_title(); ?></h1>
                <?php if ($pairs) : ?>
                    <div class="md-academy-meta md-course-header-meta">
                        <?php foreach ($pairs as $pair) : ?>
                            <span><?php echo esc_html($pair['label']); ?>:
                                <?php if (!empty($pair['url'])) : ?>
                                    <a href="<?php echo esc_url($pair['url']); ?>"><?php echo esc_html($pair['value']); ?></a>
                                <?php else : ?>
                                    <?php echo esc_html($pair['value']); ?>
                                <?php endif; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (has_excerpt()) : ?><p class="md-course-summary"><?php echo esc_html(get_the_excerpt()); ?></p><?php endif; ?>
            </header>
            <div class="md-course-content-card">
            <?php if (has_post_thumbnail()) : ?>
                <?php the_post_thumbnail('large', array('class' => 'single-cover')); ?>
            <?php endif; ?>

            <?php echo md_academy_media($course_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

            <div class="entry-content">
                <?php the_content(); ?>
            </div>

            <?php if (class_exists('MD_Learning_Courses')) : ?>
                <?php echo MD_Learning_Courses::course_ui($course_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>
            <?php if (!class_exists('MD_Learning_Courses')
                || (!MD_Learning_Courses::open_enrollment($course_id)
                    && !MD_Learning_Courses::is_enrolled(MD_Learning_Auth::current_learner_id(), $course_id))) : ?>
            <div class="md-course-cta">
                <div>
                    <strong><?php echo esc_html($price ? 'هزینه دوره: ' . $price : 'برای شرکت در این دوره آماده‌اید؟'); ?></strong>
                    <span><?php echo esc_html(count($lessons) ? md_fa_num(count($lessons)) . ' جلسه در این دوره ثبت شده است.' : 'جزئیات ثبت‌نام را از طریق فرم درخواست دریافت کنید.'); ?></span>
                </div>
                <a class="btn btn-primary" href="<?php echo esc_url($cta); ?>">درخواست ثبت‌نام ←</a>
            </div>
            <?php endif; ?>

            </div>
            <section class="md-course-lessons md-course-lessons-card">
                <h2>جلسات این دوره</h2>
                <?php echo md_academy_lessons($course_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </section>
        </article>
        </div>
        <?php md_render_sidebar('academy', 'right'); ?>
    </main>
    <?php
endwhile;

get_footer();
