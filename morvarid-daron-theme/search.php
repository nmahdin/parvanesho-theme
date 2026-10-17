<?php
/**
 * Search results with the matching contextual sidebar.
 *
 * @package Morvarid_Daron
 */

get_header();

$md_query_type = get_query_var('post_type');
$md_query_type = is_array($md_query_type) ? reset($md_query_type) : $md_query_type;
if (in_array($md_query_type, array('md_course', 'md_lesson', 'md_podcast', 'md_resource'), true)) {
    $md_context = 'academy';
} elseif ('md_challenge' === $md_query_type) {
    $md_context = 'challenge';
} else {
    $md_context = 'magazine';
}
?>
<main id="main" class="container md-magazine-layout sidebar-right">
    <div class="md-magazine-main">
        <h1>نتایج جست‌وجو برای «<?php echo esc_html(get_search_query()); ?>»</h1>
        <?php get_search_form(); ?>
        <div class="three-col card-grid md-archive-cards">
            <?php
            if (have_posts()) :
                while (have_posts()) :
                    the_post();
                    md_card(get_the_ID());
                endwhile;
            else :
                ?>
                <p>موردی پیدا نشد.</p>
            <?php endif; ?>
        </div>
        <?php the_posts_pagination(); ?>
    </div>

    <?php md_render_sidebar($md_context, 'right'); ?>
</main>
<?php
get_footer();
