<?php
/**
 * Single service.
 *
 * @package Morvarid_Daron
 */

get_header();

while (have_posts()) :
    the_post();

    $service_id = get_the_ID();
    $icon       = get_post_meta($service_id, '_md_service_icon', true);
    $pairs      = md_academy_meta_pairs($service_id);
    $booking    = md_service_booking_url($service_id);
    ?>
    <main id="main" class="container reading-layout md-academy-single">
        <article <?php post_class('article-single'); ?>>
            <a class="text-link" href="<?php echo esc_url(get_post_type_archive_link('md_service')); ?>">← همه خدمات</a>
            <div class="eyebrow">کوچینگ / مروارید درون</div>
            <h1><?php the_title(); ?></h1>

            <?php if ($icon) : ?>
                <div class="md-service-symbol" aria-hidden="true" style="margin: 10px 0; font-size: 46px; color: #a77a5e;"><?php echo esc_html($icon); ?></div>
            <?php endif; ?>

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

            <div class="entry-content">
                <?php the_content(); ?>
            </div>

            <div class="md-course-cta">
                <div>
                    <strong>برای شروع یک گفت‌وگوی امن آماده‌اید؟</strong>
                    <span>درخواست جلسه را ثبت کنید تا زمان مناسب هماهنگ شود.</span>
                </div>
                <a class="btn btn-primary" href="<?php echo esc_url($booking); ?>">درخواست جلسه ←</a>
            </div>

            <?php
            $related = new WP_Query(array(
                'post_type'      => 'md_service',
                'posts_per_page' => 3,
                'post__not_in'   => array($service_id),
                'post_status'    => 'publish',
                'no_found_rows'  => true,
            ));
            if ($related->have_posts()) :
                ?>
                <section class="md-related-services" style="margin-top: 40px;">
                    <h2>خدمات دیگر</h2>
                    <div class="three-col card-grid">
                        <?php
                        while ($related->have_posts()) :
                            $related->the_post();
                            md_card(get_the_ID(), 'خدمات کوچینگ');
                        endwhile;
                        wp_reset_postdata();
                        ?>
                    </div>
                </section>
            <?php endif; ?>
        </article>
    </main>
    <?php
endwhile;

get_footer();
