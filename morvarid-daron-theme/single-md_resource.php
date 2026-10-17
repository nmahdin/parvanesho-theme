<?php
/**
 * Single resource / exercise.
 *
 * @package Morvarid_Daron
 */

get_header();

while (have_posts()) :
    the_post();

    $resource_id = get_the_ID();
    $pairs       = md_academy_meta_pairs($resource_id);
    $download    = md_resource_file_link($resource_id);
    ?>
    <main id="main" class="container reading-layout md-academy-single">
        <article <?php post_class('article-single'); ?>>
            <a class="text-link" href="<?php echo esc_url(get_post_type_archive_link('md_resource')); ?>">← همه تمرین‌ها و منابع</a>
            <div class="eyebrow">باشگاه رشد / تمرین و منبع</div>
            <h1><?php the_title(); ?></h1>

            <?php if ($pairs) : ?>
                <div class="md-academy-meta">
                    <?php foreach ($pairs as $pair) : ?>
                        <span><?php echo esc_html($pair['label']); ?>: <?php echo esc_html($pair['value']); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($download) : ?>
                <p><?php echo $download; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
            <?php endif; ?>

            <?php if (has_post_thumbnail()) : ?>
                <?php the_post_thumbnail('large', array('class' => 'single-cover')); ?>
            <?php endif; ?>

            <div class="entry-content">
                <?php the_content(); ?>
            </div>

            <div class="md-resource-note">
                <strong>یک قدم کوچک کافی است.</strong>
                این تمرین را در فضایی آرام انجام دهید و در صورت نیاز، پاسخ‌هایتان را یادداشت کنید.
            </div>

            <?php if ($download) : ?>
                <p class="text-link-wrap"><?php echo $download; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
            <?php endif; ?>
        </article>
    </main>
    <?php
endwhile;

get_footer();
