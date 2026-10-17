<?php
/**
 * Single podcast episode.
 *
 * @package Morvarid_Daron
 */

get_header();

while (have_posts()) :
    the_post();

    $podcast_id = get_the_ID();
    $pairs      = md_academy_meta_pairs($podcast_id);
    ?>
    <main id="main" class="container reading-layout md-academy-single">
        <article <?php post_class('article-single'); ?>>
            <a class="text-link" href="<?php echo esc_url(get_post_type_archive_link('md_podcast')); ?>">← همه پادکست‌ها</a>
            <div class="eyebrow">آکادمی مروارید درون / پادکست</div>
            <h1><?php the_title(); ?></h1>

            <?php if ($pairs) : ?>
                <div class="md-academy-meta">
                    <?php foreach ($pairs as $pair) : ?>
                        <span><?php echo esc_html($pair['label']); ?>: <?php echo esc_html($pair['value']); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (has_post_thumbnail()) : ?>
                <?php the_post_thumbnail('large', array('class' => 'single-cover')); ?>
            <?php endif; ?>

            <?php echo md_academy_media($podcast_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

            <div class="entry-content">
                <?php the_content(); ?>
            </div>

            <div class="md-course-cta">
                <div>
                    <strong>شنیدن این قسمت را به دوستانتان پیشنهاد کنید</strong>
                    <span>پادکست‌های تازه در آرشیو آکادمی منتشر می‌شوند.</span>
                </div>
                <a class="btn btn-light" href="<?php echo esc_url(get_post_type_archive_link('md_podcast')); ?>">آرشیو پادکست‌ها</a>
            </div>
        </article>
    </main>
    <?php
endwhile;

get_footer();
