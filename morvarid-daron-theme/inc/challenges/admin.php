<?php
/**
 * Challenge admin screens.
 *
 * @package Morvarid_Daron
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the challenge meta boxes.
 *
 * @param string  $post_type Post type.
 * @param WP_Post $post      Post object.
 */
function md_challenge_add_meta_boxes($post_type, $post) {
    if ('md_challenge' !== $post_type) {
        return;
    }
    // Section order matches the requirements document:
    // editor (title/category/excerpt/thumbnail) → days → publish & access.
    add_meta_box(
        'md_challenge_days',
        'روزهای چالش',
        'md_challenge_days_box',
        'md_challenge',
        'normal',
        'high'
    );
    add_meta_box(
        'md_challenge_details',
        'انتشار و دسترسی',
        'md_challenge_details_box',
        'md_challenge',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'md_challenge_add_meta_boxes', 10, 2);

/**
 * Details meta box.
 *
 * @param WP_Post $post Challenge post.
 */
function md_challenge_details_box($post) {
    $start        = md_challenge_start($post->ID);
    $end          = md_challenge_end($post->ID);
    $mode         = md_challenge_access_mode($post->ID);
    $show_titles  = md_challenge_show_locked_title($post->ID);
    $enroll       = esc_url_raw(get_post_meta($post->ID, '_md_enrollment_url', true));
    $calendar     = md_calendar_days($start, $end);
    $content_days = count(md_challenge_days($post->ID, true));
    $mismatch     = ($calendar > 0 && $content_days !== $calendar);

    wp_nonce_field('md_challenge_details', 'md_challenge_details_nonce');
    ?>
    <div class="md-challenge-details">
        <div class="md-challenge-dates">
            <div class="md-admin-field">
                <label for="md_start_date_jalali">تاریخ شروع <span class="md-field-hint">(شمسی)</span></label>
                <?php echo md_jalali_date_field(array('name' => 'md_start_date', 'value' => $start, 'required' => false)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
            <div class="md-admin-field">
                <label for="md_end_date_jalali">تاریخ پایان <span class="md-field-hint">(شمسی)</span></label>
                <?php echo md_jalali_date_field(array('name' => 'md_end_date', 'value' => $end, 'required' => false)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        </div>

        <div class="md-challenge-counts" aria-live="polite">
            <span>روزهای تقویمی: <strong id="md-calendar-days"><?php echo esc_html($calendar ? md_fa_num($calendar) : '—'); ?></strong></span>
            <span>روزهای محتوایی: <strong id="md-content-days"><?php echo esc_html(md_fa_num($content_days)); ?></strong></span>
        </div>
        <p class="md-warning" id="md-day-warning" <?php echo $mismatch ? '' : 'hidden'; ?>>
            <?php
            if ($mismatch) {
                printf(
                    'تعداد روزهای محتوایی (%1$s) با تعداد روزهای تقویمی (%2$s) برابر نیست. پیش از انتشار، این تفاوت را بررسی کنید.',
                    esc_html(md_fa_num($content_days)),
                    esc_html(md_fa_num($calendar))
                );
            }
            ?>
        </p>

        <div class="md-challenge-options">
            <div class="md-admin-field">
                <label for="md_access_mode">دسترسی مخاطبان</label>
                <select id="md_access_mode" name="md_access_mode">
                    <option value="schedule" <?php selected($mode, 'schedule'); ?>>زمان‌بندی روزانه (هر روز بر اساس تاریخ شروع باز می‌شود)</option>
                    <option value="all" <?php selected($mode, 'all'); ?>>نمایش همه روزها پس از انتشار چالش</option>
                </select>
            </div>
            <div class="md-admin-field md-checkbox-field">
                <label>
                    <input type="checkbox" id="md_show_locked_title" name="md_show_locked_title" value="1" <?php checked($show_titles); ?>>
                    عنوان روزهای قفل‌شده نمایش داده شود
                </label>
            </div>
            <div class="md-admin-field">
                <label for="md_enrollment_url">لینک ثبت‌نام یا درخواست شرکت</label>
                <input type="url" id="md_enrollment_url" name="md_enrollment_url" class="regular-text" value="<?php echo esc_attr($enroll); ?>" placeholder="<?php echo esc_attr(home_url('/contact/')); ?>">
            </div>
        </div>
        <p class="description">تاریخ‌ها اختیاری‌اند. با ثبت هر دو تاریخ، روزهای چالش به‌صورت خودکار هنگام ذخیره ایجاد می‌شوند. بدون تاریخ، روزها را دستی تعریف کنید. تغییر یا کوتاه‌کردن بازه هیچ روز موجودی را حذف نمی‌کند.</p>
    </div>
    <?php
}

/**
 * Days meta box.
 *
 * @param WP_Post $post Challenge post.
 */
function md_challenge_days_box($post) {
    if ('auto-draft' === $post->post_status) {
        ?>
        <div class="md-days-manager md-days-manager-new" id="md-challenge-days" data-challenge-id="0">
            <div class="md-days-toolbar">
                <a class="button button-primary" href="#md-day-inline-form">افزودن روز جدید</a>
                <span class="md-days-summary">پیش‌نویس چالش به‌صورت خودکار ساخته می‌شود.</span>
            </div>
            <div class="md-days-notice" id="md-days-notice" role="status" hidden></div>
            <p class="md-days-save-first">
                برای افزودن روز به چالش بدون تاریخ، ابتدا چالش را به‌صورت پیش‌نویس ذخیره کنید. سپس فرم ثبت روز در همین صفحه ظاهر می‌شود.
            </p>
        </div>
        <?php
        return;
    }

    $days = md_challenge_days($post->ID, true);
    md_challenge_render_days_manager($post->ID, $days);
}

/**
 * Render the days manager UI.
 *
 * @param int      $challenge_id Challenge ID.
 * @param WP_Post[] $days        Day posts.
 */
function md_challenge_render_days_manager($challenge_id, $days) {
    $content_days = count($days);
    $calendar     = md_challenge_calendar_days($challenge_id);
    $ready_days = 0;
    $published_days = 0;
    foreach ($days as $day) {
        if ('ready' === md_challenge_day_content_status($day)['key']) {
            $ready_days++;
        }
        if ('publish' === $day->post_status) {
            $published_days++;
        }
    }
    $ready_percent = $content_days ? (int) round(100 * $ready_days / $content_days) : 0;
    ?>
    <div class="md-days-manager" id="md-challenge-days" data-challenge-id="<?php echo esc_attr($challenge_id); ?>" data-content-days="<?php echo esc_attr($content_days); ?>">
        <div class="md-days-toolbar">
            <a class="button button-primary" href="#md-day-inline-form">افزودن روز جدید</a>
            <span class="md-days-summary">
                <span>روزهای محتوایی: <strong id="md-manager-content-days"><?php echo esc_html(md_fa_num($content_days)); ?></strong></span>
                <span>روزهای تقویمی: <strong id="md-manager-calendar-days"><?php echo esc_html($calendar ? md_fa_num($calendar) : '—'); ?></strong></span>
            </span>
        </div>

        <div class="md-days-notice" id="md-days-notice" role="status" hidden></div>

        <section class="md-days-overview" aria-label="وضعیت تولید محتوای چالش">
            <div class="md-days-kpis" id="md-days-kpis" aria-live="polite">
                <div class="md-days-kpi"><span>کل روزهای ساخته‌شده</span><strong id="md-kpi-total"><?php echo esc_html(md_fa_num($content_days)); ?></strong></div>
                <div class="md-days-kpi"><span>محتوای آماده</span><strong id="md-kpi-ready"><?php echo esc_html(md_fa_num($ready_days)); ?></strong></div>
                <div class="md-days-kpi"><span>روزهای منتشرشده</span><strong id="md-kpi-published"><?php echo esc_html(md_fa_num($published_days)); ?></strong></div>
                <div class="md-days-kpi"><span>نیازمند تکمیل</span><strong id="md-kpi-incomplete"><?php echo esc_html(md_fa_num($content_days - $ready_days)); ?></strong></div>
            </div>
            <div class="md-days-progress-label"><span>پیشرفت آماده‌سازی محتوا</span><strong id="md-days-progress-text"><?php echo esc_html(md_fa_num($ready_percent)); ?>٪</strong></div>
            <div class="md-days-progress" id="md-days-progress" role="progressbar" aria-label="درصد روزهای آماده" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr($ready_percent); ?>">
                <span id="md-days-progress-fill" style="width:<?php echo esc_attr($ready_percent); ?>%"></span>
            </div>
        </section>

        <div class="md-days-controls">
            <label class="md-days-filter-label" for="md-day-search">
                <span>جست‌وجوی روزها</span>
                <input type="search" id="md-day-search" class="regular-text" placeholder="شماره یا عنوان روز">
            </label>
            <label class="md-days-filter-label" for="md-day-status-filter">
                <span>نمایش روزها</span>
                <select id="md-day-status-filter">
                    <option value="all">همه روزها</option>
                    <option value="ready">محتوای آماده</option>
                    <option value="incomplete">نیازمند تکمیل</option>
                    <option value="published">منتشرشده</option>
                    <option value="unpublished">منتشرنشده</option>
                </select>
            </label>
            <button type="button" class="button md-generate-days" id="md-generate-days" <?php disabled($calendar < 1); ?>>
                ساخت پیش‌نویس روزهای باقی‌مانده
            </button>
        </div>
        <p class="md-days-control-note">روزهای ساخته‌شده حذف یا بازنویسی نمی‌شوند؛ دکمه ساخت گروهی فقط جای خالی تا تاریخ پایان را با پیش‌نویس پُر می‌کند. تاریخ‌ها را ابتدا ذخیره کنید.</p>

        <table class="widefat striped md-days-table" id="md-days-table">
            <thead>
                <tr>
                    <th class="md-col-drag" scope="col"><span class="screen-reader-text">جابجایی</span></th>
                    <th class="md-col-number" scope="col">روز</th>
                    <th class="md-col-title" scope="col">عنوان روز</th>
                    <th class="md-col-content" scope="col">وضعیت محتوا</th>
                    <th class="md-col-publish" scope="col">انتشار</th>
                    <th class="md-col-actions" scope="col">عملیات</th>
                </tr>
            </thead>
            <tbody id="md-days-list">
                <?php
                foreach ($days as $day) {
                    echo md_challenge_render_day_row($day, $challenge_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
                ?>
            </tbody>
        </table>

        <p class="md-days-empty" id="md-days-empty" <?php echo $days ? 'hidden' : ''; ?>>هنوز روزی برای این چالش اضافه نشده است. با دکمه «افزودن روز جدید» شروع کنید.</p>
        <p class="md-days-empty" id="md-days-no-match" hidden>روزی با این جست‌وجو یا وضعیت پیدا نشد. فیلترها را تغییر دهید.</p>
        <p class="description">برای تغییر ترتیب، ردیف‌ها را با دستگیره بکشید یا از دکمه‌های ↑ و ↓ استفاده کنید. تغییر ترتیب به‌صورت خودکار ذخیره می‌شود.</p>
        <?php md_challenge_manual_day_editor($challenge_id); ?>
    </div>

    <?php
}

/**
 * Print the day editor once per screen, outside the meta box.
 *
 * Keeping the modal in admin_footer means it survives meta box re-renders and
 * is never clipped by a collapsed or re-rendered postbox.
 */
function md_challenge_print_day_modal() {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || 'md_challenge' !== $screen->post_type) {
        return;
    }
    md_challenge_render_day_modal();
}
add_action('admin_footer', 'md_challenge_print_day_modal');

/**
 * Render the day editor modal.
 */
function md_challenge_render_day_modal() {
    ?>
    <div id="md-day-modal" class="md-modal" hidden aria-hidden="true">
        <div class="md-modal-overlay" data-md-close="1"></div>
        <div class="md-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="md-day-modal-title">
            <div class="md-modal-header">
                <h2 id="md-day-modal-title">ویرایش روز</h2>
                <button type="button" class="button-link md-modal-close" aria-label="بستن">&times;</button>
            </div>
            <div class="md-modal-body">
                <div class="md-modal-error" id="md-modal-error" role="alert" hidden></div>
                <input type="hidden" id="md-day-id" value="0">

                <div class="md-admin-field md-field-number">
                    <label for="md-day-number">شماره روز <span class="md-required">*</span></label>
                    <input type="number" id="md-day-number" class="small-text" min="1" step="1" value="1">
                    <p class="description">جایگاه این روز در چالش. با تغییر شماره، ترتیب روزها خودکار به‌روزرسانی می‌شود.</p>
                </div>

                <div class="md-admin-field md-field-full">
                    <label for="md-day-title">عنوان روز <span class="md-required">*</span></label>
                    <input type="text" id="md-day-title" class="widefat">
                </div>
                <div class="md-admin-field md-field-full">
                    <label for="md-day-summary">خلاصه کوتاه</label>
                    <textarea id="md-day-summary" rows="2" class="widefat"></textarea>
                </div>
                <div class="md-admin-field md-field-full">
                    <label for="md-day-content">متن اصلی <span class="md-required">*</span></label>
                    <textarea id="md-day-content" rows="14" class="widefat"></textarea>
                    <p class="description">عنوان و متن اصلی، معیار «آماده انتشار» بودن روز هستند. برای انتشار روز هر دو الزامی است.</p>
                </div>
                <div class="md-admin-field md-field-full">
                    <label for="md-day-extra">توضیحات تکمیلی</label>
                    <textarea id="md-day-extra" rows="3" class="widefat"></textarea>
                </div>

                <fieldset class="md-fieldset">
                    <legend>نکته روز</legend>
                    <div class="md-admin-field">
                        <label for="md-day-tip-title">عنوان نکته (اختیاری)</label>
                        <input type="text" id="md-day-tip-title" class="widefat">
                    </div>
                    <div class="md-admin-field md-field-full">
                        <label for="md-day-tip">متن نکته</label>
                        <textarea id="md-day-tip" rows="3" class="widefat"></textarea>
                    </div>
                </fieldset>

                <fieldset class="md-fieldset">
                    <legend>تمرین عملی</legend>
                    <div class="md-admin-field">
                        <label for="md-day-exercise-title">عنوان تمرین</label>
                        <input type="text" id="md-day-exercise-title" class="widefat">
                    </div>
                    <div class="md-admin-field md-field-full">
                        <label for="md-day-exercise-desc">دستورالعمل و توضیحات</label>
                        <textarea id="md-day-exercise-desc" rows="3" class="widefat"></textarea>
                    </div>
                    <div class="md-admin-field md-field-full">
                        <label for="md-day-exercise-steps">مراحل انجام</label>
                        <textarea id="md-day-exercise-steps" rows="4" class="widefat" placeholder="هر مرحله را در یک خط بنویسید."></textarea>
                    </div>
                </fieldset>

                <fieldset class="md-fieldset">
                    <legend>محتوای چندرسانه‌ای</legend>
                    <div class="md-media-grid">
                        <div class="md-media-block">
                            <label>فایل صوتی</label>
                            <div class="md-media-field">
                                <input type="hidden" id="md-day-audio-id" value="0">
                                <button type="button" class="button md-day-media-select" data-media="audio" data-type="audio">انتخاب صوت</button>
                                <button type="button" class="button md-day-media-remove" data-media="audio">حذف</button>
                                <span class="md-media-name" id="md-day-audio-name"></span>
                            </div>
                            <input type="text" id="md-day-audio-title" class="widefat" placeholder="عنوان یا توضیح صوت (اختیاری)">
                        </div>
                        <div class="md-media-block">
                            <label>ویدیو</label>
                            <div class="md-media-field">
                                <input type="hidden" id="md-day-video-id" value="0">
                                <button type="button" class="button md-day-media-select" data-media="video" data-type="video">انتخاب ویدیو</button>
                                <button type="button" class="button md-day-media-remove" data-media="video">حذف</button>
                                <span class="md-media-name" id="md-day-video-name"></span>
                            </div>
                            <input type="url" id="md-day-video-url" class="widefat" placeholder="یا لینک ویدیوی خارجی (آپارات، یوتیوب و…)">
                            <input type="text" id="md-day-video-title" class="widefat" placeholder="عنوان یا توضیح ویدیو (اختیاری)">
                        </div>
                        <div class="md-media-block">
                            <label>فایل پیوست</label>
                            <div class="md-media-field">
                                <input type="hidden" id="md-day-file-id" value="0">
                                <button type="button" class="button md-day-media-select" data-media="file" data-type="">انتخاب فایل</button>
                                <button type="button" class="button md-day-media-remove" data-media="file">حذف</button>
                                <span class="md-media-name" id="md-day-file-name"></span>
                            </div>
                            <input type="text" id="md-day-file-title" class="widefat" placeholder="عنوان یا توضیح فایل (اختیاری)">
                        </div>
                    </div>
                </fieldset>

                <fieldset class="md-fieldset">
                    <legend>پرسش و پاسخ</legend>
                    <div id="md-qa-list" class="md-qa-list"></div>
                    <button type="button" class="button" id="md-add-qa">افزودن پرسش</button>
                    <p class="description">پرسش‌ها فقط برای نمایش هستند؛ کاربران امکان ارسال پرسش یا پاسخ از این بخش را ندارند.</p>
                </fieldset>

                <div class="md-admin-field">
                    <label for="md-day-status">وضعیت انتشار روز</label>
                    <select id="md-day-status">
                        <option value="draft">پیش‌نویس</option>
                        <option value="publish">منتشرشده</option>
                    </select>
                </div>
            </div>
            <div class="md-modal-footer">
                <button type="button" class="button button-primary" id="md-save-day">ذخیره روز</button>
                <button type="button" class="button md-modal-close">انصراف</button>
                <span class="md-modal-spinner" id="md-modal-spinner" hidden>در حال ذخیره…</span>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Read a date posted by the challenge editor.
 *
 * The Jalali field is authoritative; the hidden Gregorian value is only a
 * fallback for requests where JavaScript did not run.
 *
 * @param string $key Meta key without the leading underscore (md_start_date…).
 * @return string Gregorian Y-m-d or an empty string.
 */
function md_challenge_posted_date($key) {
    $jalali = isset($_POST[$key . '_jalali']) && is_scalar($_POST[$key . '_jalali']) ? wp_unslash($_POST[$key . '_jalali']) : '';
    $date   = md_parse_jalali($jalali);

    if (!$date) {
        $fallback = isset($_POST[$key]) && is_scalar($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
        $date     = md_sanitize_date($fallback);
    }

    return $date;
}

/**
 * Save challenge details.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 */
function md_challenge_save_details($post_id, $post) {
    if (!isset($_POST['md_challenge_details_nonce'])) {
        return;
    }
    $nonce = sanitize_text_field(wp_unslash($_POST['md_challenge_details_nonce']));
    if (!wp_verify_nonce($nonce, 'md_challenge_details')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $start = md_challenge_posted_date('md_start_date');
    $end   = md_challenge_posted_date('md_end_date');

    if ($start) {
        update_post_meta($post_id, '_md_start_date', $start);
    } else {
        delete_post_meta($post_id, '_md_start_date');
    }
    if ($end) {
        update_post_meta($post_id, '_md_end_date', $end);
    } else {
        delete_post_meta($post_id, '_md_end_date');
    }

    $mode = isset($_POST['md_access_mode']) ? sanitize_key(wp_unslash($_POST['md_access_mode'])) : 'schedule';
    if (!in_array($mode, array('all', 'schedule'), true)) {
        $mode = 'schedule';
    }
    if (!$start || !$end) {
        $mode = 'all';
    }
    update_post_meta($post_id, '_md_access_mode', $mode);
    update_post_meta($post_id, '_md_show_locked_title', empty($_POST['md_show_locked_title']) ? 0 : 1);

    $enroll = isset($_POST['md_enrollment_url']) ? esc_url_raw(wp_unslash($_POST['md_enrollment_url'])) : '';
    if ($enroll) {
        update_post_meta($post_id, '_md_enrollment_url', $enroll);
    } else {
        delete_post_meta($post_id, '_md_enrollment_url');
    }

    update_post_meta($post_id, '_md_calendar_days', md_calendar_days($start, $end));
    // Server-side generation: no JavaScript, AJAX request or extra click needed.
    if ($start && $end && $end >= $start) {
        md_challenge_ensure_calendar_days($post_id);
    }
    md_challenge_sync_counts($post_id);
}
add_action('save_post_md_challenge', 'md_challenge_save_details', 10, 2);

/**
 * Prevent publishing a challenge with invalid dates or an empty title.
 *
 * The filter runs before the post is written, so the admin is returned to the
 * edit screen with a clear notice and the challenge stays a draft.
 *
 * @param array $data    Post data.
 * @param array $postarr Raw post array.
 * @return array
 */
function md_challenge_validate_publish($data, $postarr) {
    if (empty($data['post_type']) || 'md_challenge' !== $data['post_type']) {
        return $data;
    }
    if (empty($data['post_status']) || 'publish' !== $data['post_status']) {
        return $data;
    }
    if (empty($_POST['md_challenge_details_nonce'])) {
        return $data;
    }
    $nonce = sanitize_text_field(wp_unslash($_POST['md_challenge_details_nonce']));
    if (!wp_verify_nonce($nonce, 'md_challenge_details')) {
        return $data;
    }

    $post_id = !empty($postarr['ID']) ? absint($postarr['ID']) : 0;
    if ($post_id) {
        if (!current_user_can('edit_post', $post_id)) {
            return $data;
        }
    } elseif (!current_user_can('publish_posts')) {
        return $data;
    }

    $start = md_challenge_posted_date('md_start_date');
    $end   = md_challenge_posted_date('md_end_date');
    $mode  = isset($_POST['md_access_mode']) ? sanitize_key(wp_unslash($_POST['md_access_mode'])) : 'schedule';

    $errors = array();
    if ('' === trim((string) $data['post_title'])) {
        $errors[] = 'عنوان چالش نمی‌تواند خالی باشد.';
    }
    if (($start && !$end) || (!$start && $end)) {
        $errors[] = 'یا هر دو تاریخ را وارد کنید یا هر دو را خالی بگذارید.';
    } elseif ($start && $end && $end < $start) {
        $errors[] = 'تاریخ پایان نمی‌تواند پیش از تاریخ شروع باشد.';
    } elseif ($start && $end && md_calendar_days($start, $end) > 120) {
        $errors[] = 'بازه چالش تاریخ‌دار حداکثر ۱۲۰ روز است.';
    }

    if ($errors) {
        $data['post_status'] = 'draft';
        $notices = array();
        foreach ($errors as $error) {
            $notices[] = array('type' => 'error', 'message' => $error);
        }
        $notices[] = array('type' => 'warning', 'message' => 'چالش به‌صورت پیش‌نویس ذخیره شد تا داده‌های ناقص منتشر نشوند.');
        set_transient('md_challenge_notice_' . get_current_user_id(), $notices, 120);
    }

    return $data;
}
add_filter('wp_insert_post_data', 'md_challenge_validate_publish', 10, 2);

/**
 * Display validation notices after a challenge save.
 */
function md_challenge_admin_notices() {
    $key     = 'md_challenge_notice_' . get_current_user_id();
    $notices = get_transient($key);
    if (!$notices || !is_array($notices)) {
        return;
    }
    delete_transient($key);
    foreach ($notices as $notice) {
        $type = in_array($notice['type'], array('error', 'warning', 'success', 'info'), true) ? $notice['type'] : 'info';
        echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($notice['message']) . '</p></div>';
    }
}
add_action('admin_notices', 'md_challenge_admin_notices');

/**
 * Challenge list table columns.
 *
 * @param array $columns Existing columns.
 * @return array
 */
function md_challenge_columns($columns) {
    $new = array();
    foreach ($columns as $key => $label) {
        if ('author' === $key) {
            continue;
        }
        if (0 === strpos($key, 'taxonomy-')) {
            continue;
        }
        $new[$key] = $label;
        if ('title' === $key) {
            $new['md_category'] = 'دسته‌بندی';
            $new['md_start']    = 'تاریخ شروع';
            $new['md_end']      = 'تاریخ پایان';
            $new['md_days']     = 'روزها';
            $new['md_publish']  = 'وضعیت';
        }
    }
    return $new;
}
add_filter('manage_md_challenge_posts_columns', 'md_challenge_columns');

/**
 * Render custom challenge columns.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 */
function md_challenge_custom_column($column, $post_id) {
    if ('md_category' === $column) {
        $terms = get_the_term_list($post_id, 'md_challenge_cat', '', '، ');
        echo $terms && !is_wp_error($terms) ? wp_kses_post($terms) : '—';
        return;
    }

    if ('md_start' === $column || 'md_end' === $column) {
        $date = ('md_start' === $column) ? md_challenge_start($post_id) : md_challenge_end($post_id);
        if (!$date) {
            echo '—';
            return;
        }
        echo '<span title="' . esc_attr($date) . '">' . esc_html(md_format_jalali($date)) . '</span>';
        return;
    }

    if ('md_days' === $column) {
        $content = count(md_challenge_days($post_id, true));
        $calendar = md_challenge_calendar_days($post_id);
        $class = ($calendar > 0 && $content !== $calendar) ? ' md-days-mismatch' : '';
        echo '<span class="' . esc_attr(trim($class)) . '">' . esc_html(md_fa_num($content)) . ' / ' . esc_html($calendar ? md_fa_num($calendar) : '—') . '</span>';
        return;
    }

    if ('md_publish' === $column) {
        $status = get_post_status($post_id);
        $object = get_post_status_object($status);
        $label  = $object ? $object->label : $status;
        echo '<span class="md-badge md-badge-' . esc_attr($status) . '">' . esc_html($label) . '</span>';
    }
}
add_action('manage_md_challenge_posts_custom_column', 'md_challenge_custom_column', 10, 2);

/**
 * Make the start date column sortable.
 *
 * @param array $columns Sortable columns.
 * @return array
 */
function md_challenge_sortable_columns($columns) {
    $columns['md_start'] = 'md_start';
    return $columns;
}
add_filter('manage_edit-md_challenge_sortable_columns', 'md_challenge_sortable_columns');

/**
 * Sort the challenge list by start date.
 *
 * @param WP_Query $query Query.
 */
function md_challenge_sort_by_start($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }
    if ('md_challenge' !== $query->get('post_type')) {
        return;
    }
    if ('md_start' === $query->get('orderby')) {
        $query->set('meta_key', '_md_start_date');
        $query->set('orderby', 'meta_value');
    }
}
add_action('pre_get_posts', 'md_challenge_sort_by_start');

/**
 * Category filter above the challenge list.
 *
 * @param string $post_type Current post type.
 */
function md_challenge_filter_dropdown($post_type) {
    if ('md_challenge' !== $post_type) {
        return;
    }
    $selected = isset($_GET['md_challenge_cat']) ? sanitize_title(wp_unslash($_GET['md_challenge_cat'])) : '';
    wp_dropdown_categories(array(
        'show_option_all' => 'همه دسته‌بندی‌ها',
        'taxonomy'        => 'md_challenge_cat',
        'name'            => 'md_challenge_cat',
        'orderby'         => 'name',
        'selected'        => $selected,
        'hierarchical'    => true,
        'depth'           => 3,
        'show_count'      => false,
        'hide_empty'      => false,
        'value_field'     => 'slug',
    ));
}
add_action('restrict_manage_posts', 'md_challenge_filter_dropdown');

/**
 * Add a "manage days" row action.
 *
 * @param array   $actions Row actions.
 * @param WP_Post $post    Post.
 * @return array
 */
function md_challenge_row_actions($actions, $post) {
    if ('md_challenge' === $post->post_type) {
        $url = get_edit_post_link($post->ID, 'raw');
        if ($url) {
            $actions['md_days'] = '<a href="' . esc_url($url . '#md-challenge-days') . '">مدیریت روزها</a>';
        }
    }
    return $actions;
}
add_filter('post_row_actions', 'md_challenge_row_actions', 10, 2);

/**
 * Enqueue challenge admin assets.
 *
 * @param string $hook Current admin page.
 */
function md_challenge_admin_assets($hook) {
    if (!in_array($hook, array('post.php', 'post-new.php', 'edit.php'), true)) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || 'md_challenge' !== $screen->post_type) {
        return;
    }

    if ('edit.php' === $hook) {
        wp_enqueue_style('md-admin-fields', MD_THEME_URI . '/assets/css/admin-fields.css', array(), MD_THEME_VERSION);
        wp_enqueue_style('md-admin-challenge', MD_THEME_URI . '/assets/css/admin-challenge.css', array('md-admin-fields'), MD_THEME_VERSION);
        return;
    }

    $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;

    wp_enqueue_media();
    wp_enqueue_editor();
    wp_enqueue_style('md-admin-fields', MD_THEME_URI . '/assets/css/admin-fields.css', array(), MD_THEME_VERSION);
    wp_enqueue_style('md-admin-challenge', MD_THEME_URI . '/assets/css/admin-challenge.css', array('md-admin-fields'), MD_THEME_VERSION);
    wp_enqueue_script(
        'md-jalali',
        MD_THEME_URI . '/assets/js/jalali.js',
        array(),
        MD_THEME_VERSION,
        true
    );
    // Do not depend on "wp-media": this is a JS namespace, not a core
    // script handle. An unregistered dependency prevents WordPress from
    // printing admin-challenge.js at all, making "Add new day" inert.
    // wp_enqueue_media() and wp_enqueue_editor() above load their APIs.
    wp_enqueue_script(
        'md-admin-challenge',
        MD_THEME_URI . '/assets/js/admin-challenge.js',
        array('jquery', 'jquery-ui-sortable', 'md-jalali'),
        MD_THEME_VERSION,
        true
    );

    wp_localize_script('md-admin-challenge', 'mdChallenge', array(
        'ajaxUrl'      => admin_url('admin-ajax.php'),
        'nonce'        => wp_create_nonce('md_challenge_admin'),
        'challengeId'  => $post_id,
        'autoOpenDay'  => (bool) ($post_id && isset($_GET['md_add_day']) && '1' === $_GET['md_add_day']),
        'contentDays'  => count(md_challenge_days($post_id, true)),
        'calendarDays' => md_challenge_calendar_days($post_id),
        'i18n'         => array(
            'addDay'         => 'افزودن روز جدید',
            'editDay'        => 'ویرایش روز',
            'saving'         => 'در حال ذخیره…',
            'saved'          => 'روز با موفقیت ذخیره شد.',
            'deleted'        => 'روز حذف شد.',
            'duplicated'     => 'روز کپی شد.',
            'reordered'      => 'ترتیب روزها ذخیره شد.',
            'error'          => 'خطایی رخ داد. دوباره تلاش کنید.',
            'confirmDelete'  => 'این روز و تمام محتوای آن حذف شود؟ این عمل قابل بازگشت نیست.',
            'confirmClose'   => 'تغییرات ذخیره‌نشده از بین می‌رود. ادامه می‌دهید؟',
            'unsaved'        => 'تغییرات ذخیره‌نشده‌ای دارید.',
            'invalidDates'   => 'تاریخ پایان نمی‌تواند پیش از تاریخ شروع باشد.',
            'missingDates'   => 'برای محاسبه روزهای تقویمی، تاریخ شروع و پایان را وارد کنید.',
            'countMismatch'  => 'تعداد روزهای محتوایی (%1$s) با روزهای تقویمی (%2$s) برابر نیست.',
            'chooseMedia'    => 'انتخاب فایل',
            'removeMedia'    => 'حذف',
            'publishNeeds'   => 'برای انتشار روز، عنوان و متن اصلی الزامی است.',
            'invalidNumber'  => 'شماره روز باید عددی بزرگ‌تر از صفر باشد.',
            'noChallenge'    => 'ابتدا چالش را ذخیره کنید.',
            'qaQuestion'     => 'متن پرسش',
            'qaAnswer'       => 'متن پاسخ',
            'qaRemove'       => 'حذف',
            'qaUp'           => 'بالا',
            'qaDown'         => 'پایین',
        ),
    ));
}
add_action('admin_enqueue_scripts', 'md_challenge_admin_assets');

/**
 * Challenge screens rely on classic meta boxes and their own modal editor.
 * Gutenberg re-renders meta boxes, which used to detach their event handlers.
 *
 * @param bool   $use       Whether to use the block editor.
 * @param string $post_type Post type.
 * @return bool
 */
function md_challenge_classic_editor($use, $post_type) {
    if (in_array($post_type, array('md_challenge', 'md_challenge_day'), true)) {
        return false;
    }
    return $use;
}
add_filter('use_block_editor_for_post_type', 'md_challenge_classic_editor', 10, 2);
