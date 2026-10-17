<?php
/**
 * Service archive.
 *
 * @package Morvarid_Daron
 */

get_header();
?>
<main id="main" class="container md-academy-directory md-service-archive">
    <header class="md-academy-intro">
        <span class="eyebrow">همراهی / مروارید درون</span>
        <h1><?php echo esc_html(md_academy_archive_title()); ?></h1>
        <p class="md-section-lead"><?php echo esc_html(md_academy_archive_intro()); ?></p>
    </header>

    <?php if (have_posts()) : ?>
        <div class="three-col card-grid">
            <?php
            while (have_posts()) :
                the_post();
                $service_id = get_the_ID();
                $icon       = get_post_meta($service_id, '_md_service_icon', true);
                $duration   = get_post_meta($service_id, '_md_service_duration', true);
                $price      = get_post_meta($service_id, '_md_service_price', true);
                $booking    = md_service_booking_url($service_id);
                $excerpt    = get_post_field('post_excerpt', $service_id);
                if (!$excerpt) {
                    $excerpt = get_post_field('post_content', $service_id);
                }
                ?>
                <article class="md-service-card">
                    <span class="md-service-symbol" aria-hidden="true"><?php echo esc_html($icon ? $icon : '✦'); ?></span>
                    <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                    <p><?php echo esc_html(md_trim_text($excerpt, 20)); ?></p>
                    <div class="md-service-meta">
                        <?php if ($duration) : ?><span><?php echo esc_html($duration); ?></span><?php endif; ?>
                        <?php if ($price) : ?><span><?php echo esc_html($price); ?></span><?php endif; ?>
                    </div>
                    <a class="btn btn-primary" href="<?php echo esc_url($booking); ?>">درخواست جلسه ←</a>
                </article>
            <?php endwhile; ?>
        </div>
        <div class="pagination">
            <?php the_posts_pagination(); ?>
        </div>
    <?php else : ?>
        <p class="empty-note">هنوز خدمتی در این بخش معرفی نشده است.</p>
    <?php endif; ?>
</main>
<?php
get_footer();
