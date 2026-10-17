<?php
/**
 * Single challenge template.
 *
 * @package Morvarid_Daron
 */

get_header();

while (have_posts()) :
    the_post();

    $challenge_id   = get_the_ID();
    $start          = md_challenge_start($challenge_id);
    $end            = md_challenge_end($challenge_id);
    $stats          = md_challenge_stats($challenge_id);
    $time_status    = md_challenge_time_status($challenge_id);
    $time_label     = md_challenge_time_label($challenge_id);
    $current_day    = md_challenge_current_day($challenge_id);
    $enroll         = md_challenge_enrollment_url($challenge_id);
    $category       = md_challenge_categories($challenge_id);
    $is_editor      = md_challenge_is_editor($challenge_id);
    $days           = md_challenge_days($challenge_id, $is_editor);
    $calendar_days  = md_challenge_calendar_days($challenge_id);
    $content_days   = count($days);
    ?>
    <main id="main" class="md-challenge-page">
        <section class="md-challenge-hero">
            <div class="container md-challenge-hero-grid">
                <div class="md-challenge-hero-copy">
                    <span class="eyebrow">چالش مروارید درون</span>
                    <h1><?php the_title(); ?></h1>

                    <div class="md-challenge-meta">
                        <?php if ($time_label) : ?>
                            <span class="md-challenge-status md-challenge-status-<?php echo esc_attr($time_status); ?>"><?php echo esc_html($time_label); ?></span>
                        <?php endif; ?>
                        <?php if ($category) : ?>
                            <span><?php echo esc_html($category); ?></span>
                        <?php endif; ?>
                        <?php if ($start && $end) : ?>
                            <span>از <?php echo esc_html(md_format_jalali($start)); ?> تا <?php echo esc_html(md_format_jalali($end)); ?></span>
                        <?php elseif ($start) : ?>
                            <span>شروع از <?php echo esc_html(md_format_jalali($start)); ?></span>
                        <?php endif; ?>
                        <span><?php echo esc_html(md_fa_num($content_days ? $content_days : $calendar_days)); ?> روز</span>
                        <?php if ($is_editor) : ?>
                            <span class="md-editor-badge">پیش‌نمایش مدیر</span>
                        <?php endif; ?>
                    </div>

                    <?php if (has_excerpt()) : ?>
                        <p class="md-challenge-summary"><?php echo esc_html(get_the_excerpt()); ?></p>
                    <?php endif; ?>

                    <?php if ($enroll) : ?>
                        <a class="btn btn-primary" href="<?php echo esc_url($enroll); ?>">شرکت در چالش ←</a>
                    <?php endif; ?>

                    <?php if ($stats['total'] && 'unknown' !== $time_status) : ?>
                        <div class="md-challenge-progress">
                            <div class="md-challenge-progress-head">
                                <?php if ('active' === $time_status && $current_day) : ?>
                                    <strong>روز <?php echo esc_html(md_fa_num($current_day)); ?> از <?php echo esc_html(md_fa_num($stats['total'])); ?></strong>
                                <?php elseif ('upcoming' === $time_status && $start) : ?>
                                    <strong>شروع در <?php echo esc_html(md_format_jalali($start)); ?></strong>
                                <?php elseif ('ended' === $time_status) : ?>
                                    <strong>این چالش به پایان رسیده است</strong>
                                <?php endif; ?>
                                <span><?php echo esc_html(md_fa_num($stats['percent'])); ?>٪ باز شده</span>
                            </div>
                            <div class="md-challenge-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr($stats['percent']); ?>">
                                <span style="width: <?php echo esc_attr($stats['percent']); ?>%"></span>
                            </div>
                            <?php if ($is_editor) : ?>
                                <p class="md-challenge-editor-note">
                                    روزهای آماده انتشار: <?php echo esc_html(md_fa_num($stats['ready'])); ?>
                                    · منتشرشده: <?php echo esc_html(md_fa_num($stats['published'])); ?>
                                    · روزهای تقویمی: <?php echo esc_html($calendar_days ? md_fa_num($calendar_days) : '—'); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="md-challenge-hero-media">
                    <?php if (has_post_thumbnail()) : ?>
                        <?php the_post_thumbnail('large', array('class' => 'md-challenge-cover')); ?>
                    <?php else : ?>
                        <div class="md-challenge-art" role="img" aria-label="چالش مروارید درون">
                            <span class="md-challenge-art-ring" aria-hidden="true">◌</span>
                            <span class="md-challenge-art-pearl" aria-hidden="true">●</span>
                            <span class="md-challenge-art-days"><?php echo esc_html(md_fa_num($content_days ? $content_days : $calendar_days)); ?> روز همراهی</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <div class="container md-challenge-layout">
            <div class="md-challenge-main">
                <?php
                $intro = apply_filters('the_content', get_the_content());
                if ('' !== trim(wp_strip_all_tags($intro))) :
                    ?>
                    <section class="md-challenge-intro entry-content">
                        <?php echo $intro; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </section>
                <?php endif; ?>

                <?php md_challenge_render_day_nav($days, $challenge_id); ?>

                <div class="md-challenge-days" id="md-days">
                    <?php if ($days) : ?>
                        <?php foreach ($days as $day) : ?>
                            <?php md_challenge_render_day($day, $challenge_id); ?>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <p class="empty-note">هنوز روزی برای این چالش تعریف نشده است. به‌زودی محتوای روزها اینجا منتشر می‌شود.</p>
                    <?php endif; ?>
                </div>
            </div>

            <?php md_render_sidebar('challenge', 'right'); ?>
        </div>

        <?php if ($enroll) : ?>
            <section class="md-challenge-final-cta">
                <div class="container">
                    <h2>همین حالا به چالش بپیوندید</h2>
                    <p>یک قدم کوچک برای ساختن عادت تازه کافی است.</p>
                    <a class="btn btn-primary" href="<?php echo esc_url($enroll); ?>">شرکت در چالش ←</a>
                </div>
            </section>
        <?php endif; ?>
    </main>
    <?php
endwhile;

get_footer();
