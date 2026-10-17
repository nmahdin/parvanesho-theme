<?php
/**
 * Resource / exercise archive.
 *
 * @package Morvarid_Daron
 */

get_header();
?>
<main id="main" class="md-exercise-archive container md-magazine-layout sidebar-right">
    <div class="md-magazine-main">
        <header class="md-exercise-hero">
            <span class="eyebrow">باشگاه رشد مروارید درون</span>
            <h1>یک تمرین کوچک، یک کشف تازه</h1>
            <p>تمرین مورد علاقه‌ات را انتخاب کن و قدم بعدی را بردار.</p>
        </header>

        <?php echo md_resource_categories_nav(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

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
                    md_resource_card(get_the_ID());
                endwhile;
                ?>
            <?php else : ?>
                <p class="md-exercise-empty">هنوز تمرینی منتشر نشده است.</p>
            <?php endif; ?>
        </div>

        <div class="md-exercise-pagination">
            <?php the_posts_pagination(); ?>
        </div>
    </div>

    <?php md_render_sidebar('academy', 'right'); ?>
</main>
<?php
get_footer();
