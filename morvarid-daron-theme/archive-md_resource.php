<?php
/**
 * Resource / exercise archive.
 *
 * @package Morvarid_Daron
 */

get_header();
?>
<main class="md-exercise-archive container" id="main">
    <header class="md-exercise-hero">
        <span class="eyebrow">باشگاه رشد مروارید درون</span>
        <h1>یک تمرین کوچک، یک کشف تازه</h1>
        <p>تمرین مورد علاقه‌ات را انتخاب کن و قدم بعدی را بردار.</p>
    </header>

    <div class="md-exercise-filters" role="group" aria-label="فیلتر تمرین‌ها">
        <button type="button" class="active" data-md-filter="all" aria-pressed="true">همه تمرین‌ها</button>
        <button type="button" data-md-filter="reflection" aria-pressed="false">تأمل و خودشناسی</button>
        <button type="button" data-md-filter="practice" aria-pressed="false">تمرین عملی</button>
        <button type="button" data-md-filter="download" aria-pressed="false">کاربرگ دانلودی</button>
    </div>

    <div class="md-exercise-grid" id="md-exercise-results">
        <?php if (have_posts()) : ?>
            <?php
            while (have_posts()) :
                the_post();
                $id      = get_the_ID();
                $kind    = get_post_meta($id, '_md_resource_kind', true);
                $kind    = $kind ? $kind : 'reflection';
                $minutes = absint(get_post_meta($id, '_md_resource_minutes', true));
                $minutes = $minutes ? $minutes : 5;
                ?>
                <article class="md-exercise-card" data-kind="<?php echo esc_attr($kind); ?>">
                    <div class="md-exercise-top">
                        <span class="md-exercise-symbol" aria-hidden="true"><?php echo 'download' === $kind ? '↓' : ('practice' === $kind ? '✓' : '✦'); ?></span>
                        <span class="md-exercise-time">حدود <?php echo esc_html(md_fa_num($minutes)); ?> دقیقه</span>
                    </div>
                    <span class="md-exercise-type"><?php echo esc_html(md_resource_kind_label($kind)); ?></span>
                    <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <p><?php echo esc_html(md_trim_text(get_the_excerpt() ? get_the_excerpt() : get_the_content(), 20)); ?></p>
                    <a class="md-exercise-start" href="<?php the_permalink(); ?>">شروع این تمرین <span aria-hidden="true">←</span></a>
                </article>
            <?php endwhile; ?>
        <?php else : ?>
            <p class="md-exercise-empty">هنوز تمرینی منتشر نشده است.</p>
        <?php endif; ?>
    </div>

    <div class="md-exercise-pagination">
        <?php the_posts_pagination(); ?>
    </div>
</main>
<?php
get_footer();
