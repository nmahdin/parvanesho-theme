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
    // Phase B: central session is the ONLY participant credential when the
    // learning plugin is active. Old challenge-only codes/URLs are obsolete.
    if (function_exists('md_learning_current_challenge_member')) {
        $member = md_learning_current_challenge_member($challenge_id);
        if ($member) {
            md_challenge_render_member_dashboard($challenge_id, $member);
            continue;
        }
    } elseif (isset($_GET['md_member']) || isset($_GET['md_key'])) {
        // Compatibility fallback when the learning plugin is not installed.
        $member = md_challenge_member_from_request($challenge_id);
        if (!$member) {
            status_header(403);
            echo '<main class="container md-member-access-error" dir="rtl"><h1>لینک اختصاصی معتبر نیست</h1><p>این لینک اشتباه یا غیرفعال شده است.</p></main>';
        } else {
            md_challenge_render_member_dashboard($challenge_id, $member);
        }
        continue;
    }
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

                    <div class="md-challenge-auth-actions">
                        <?php if (function_exists('md_learning_current_challenge_member') && class_exists('MD_Learning_Auth') && class_exists('MD_Learning_Schema')) : ?>
                            <?php if (MD_Learning_Auth::current_learner_id()) : ?>
                                <a class="btn btn-primary md-challenge-join-button" href="#md-challenge-enroll">عضویت در چالش</a>
                            <?php else : ?>
                                <a class="btn btn-primary md-challenge-join-button" href="<?php echo esc_url(add_query_arg('md_challenge', $challenge_id, MD_Learning_Schema::url('login'))); ?>">ورود و عضویت</a>
                                <a class="btn btn-light md-challenge-join-button" href="<?php echo esc_url(add_query_arg('md_challenge', $challenge_id, MD_Learning_Schema::url('register'))); ?>">ساخت حساب و عضویت در چالش</a>
                            <?php endif; ?>
                        <?php else : ?>
                            <a class="btn btn-primary md-challenge-join-button" href="#md-challenge-enroll">عضویت در چالش</a>
                        <?php endif; ?>
                    </div>
                    <?php if ($enroll && !function_exists('md_learning_current_challenge_member')) : ?>
                        <a class="text-link" href="<?php echo esc_url($enroll); ?>">لینک تکمیلی ثبت‌نام ←</a>
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

                <?php md_challenge_registration_form($challenge_id); ?>

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

        <?php if ($enroll && !function_exists('md_learning_current_challenge_member')) : ?>
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
