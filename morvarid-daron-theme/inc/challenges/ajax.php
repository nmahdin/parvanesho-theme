<?php
/**
 * Challenge AJAX endpoints and admin row rendering.
 *
 * @package Morvarid_Daron
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render one row in the challenge days table.
 *
 * @param WP_Post $day          Day post.
 * @param int     $challenge_id Challenge ID.
 * @return string
 */
function md_challenge_render_day_row($day, $challenge_id) {
    if (!$day instanceof WP_Post) {
        return '';
    }

    $order          = md_challenge_day_order($day->ID);
    $content_status = md_challenge_day_content_status($day);
    $publish_status = $day->post_status;
    $publish_label  = md_challenge_day_publish_label($day);
    $title          = trim($day->post_title);
    if ('' === $title) {
        $title = 'بدون عنوان';
    }

    $unlock_date = md_challenge_day_unlock_date($day, $challenge_id);
    $resources   = array();
    if (absint(get_post_meta($day->ID, '_md_day_audio_id', true))) {
        $resources[] = 'صوت';
    }
    if (absint(get_post_meta($day->ID, '_md_day_video_id', true)) || get_post_meta($day->ID, '_md_day_video_url', true)) {
        $resources[] = 'ویدیو';
    }
    if (absint(get_post_meta($day->ID, '_md_day_file_id', true))) {
        $resources[] = 'فایل';
    }
    if (trim((string) get_post_meta($day->ID, '_md_day_exercise_desc', true)) || trim((string) get_post_meta($day->ID, '_md_day_exercise_steps', true))) {
        $resources[] = 'تمرین';
    }

    ob_start();
    ?>
    <tr class="md-day-row" data-day-id="<?php echo esc_attr($day->ID); ?>" data-order="<?php echo esc_attr($order); ?>" data-content-state="<?php echo esc_attr($content_status['key']); ?>" data-publish-state="<?php echo esc_attr($publish_status); ?>">
        <td class="md-col-drag">
            <span class="md-drag-handle dashicons dashicons-menu" aria-hidden="true"></span>
        </td>
        <td class="md-col-number">
            <span class="md-day-number"><?php echo esc_html(md_fa_num($order)); ?></span>
            <?php if ($unlock_date) : ?>
                <small class="md-day-unlock-date" title="تاریخ بازشدن این روز"><?php echo esc_html(md_format_jalali($unlock_date)); ?></small>
            <?php endif; ?>
        </td>
        <td class="md-col-title">
            <strong class="md-day-row-title"><?php echo esc_html($title); ?></strong>
            <?php if ($resources) : ?>
                <small class="md-day-resources"><?php echo esc_html(implode(' · ', $resources)); ?></small>
            <?php endif; ?>
            <?php if ('' === trim(wp_strip_all_tags($day->post_content))) : ?>
                <span class="md-row-hint">متن اصلی خالی است</span>
            <?php endif; ?>
        </td>
        <td class="md-col-content">
            <span class="md-badge md-badge-content-<?php echo esc_attr($content_status['key']); ?>">
                <?php echo esc_html($content_status['label']); ?>
            </span>
        </td>
        <td class="md-col-publish">
            <span class="md-badge md-badge-<?php echo esc_attr($publish_status); ?>">
                <?php echo esc_html($publish_label); ?>
            </span>
        </td>
        <td class="md-col-actions">
            <a class="button" href="<?php echo esc_url(add_query_arg(array('post' => $challenge_id, 'action' => 'edit', 'md_edit_day' => $day->ID), admin_url('post.php')) . '#md-day-inline-form'); ?>">ویرایش مستقیم</a>
            <button type="button" class="button md-edit-day">ویرایش پیشرفته</button>
            <button type="button" class="button md-duplicate-day">کپی</button>
            <button type="button" class="button md-delete-day">حذف</button>
            <span class="md-order-buttons">
                <button type="button" class="button md-move-up" aria-label="جابجایی به بالا">↑</button>
                <button type="button" class="button md-move-down" aria-label="جابجایی به پایین">↓</button>
            </span>
        </td>
    </tr>
    <?php
    return ob_get_clean();
}

/**
 * Data sent to the day editor modal.
 *
 * @param WP_Post $day Day post.
 * @return array
 */
function md_challenge_get_day_payload($day) {
    $qa = md_challenge_day_qa($day->ID);
    $qa = array_values(array_filter(array_map(function ($item) {
        if (!is_array($item)) {
            return null;
        }
        return array(
            'q' => isset($item['q']) ? (string) $item['q'] : '',
            'a' => isset($item['a']) ? (string) $item['a'] : '',
        );
    }, $qa)));

    return array(
        'id'              => $day->ID,
        'order'           => md_challenge_day_order($day->ID),
        'title'           => $day->post_title,
        'summary'         => $day->post_excerpt,
        'content'         => $day->post_content,
        'extra'           => (string) get_post_meta($day->ID, '_md_day_extra', true),
        'tip_title'       => (string) get_post_meta($day->ID, '_md_day_tip_title', true),
        'tip'             => (string) get_post_meta($day->ID, '_md_day_tip', true),
        'exercise_title'  => (string) get_post_meta($day->ID, '_md_day_exercise_title', true),
        'exercise_desc'   => (string) get_post_meta($day->ID, '_md_day_exercise_desc', true),
        'exercise_steps'  => (string) get_post_meta($day->ID, '_md_day_exercise_steps', true),
        'audio_id'        => absint(get_post_meta($day->ID, '_md_day_audio_id', true)),
        'audio_title'     => (string) get_post_meta($day->ID, '_md_day_audio_title', true),
        'audio_name'      => md_attachment_name(absint(get_post_meta($day->ID, '_md_day_audio_id', true))),
        'video_id'        => absint(get_post_meta($day->ID, '_md_day_video_id', true)),
        'video_url'       => esc_url_raw(get_post_meta($day->ID, '_md_day_video_url', true)),
        'video_title'     => (string) get_post_meta($day->ID, '_md_day_video_title', true),
        'video_name'      => md_attachment_name(absint(get_post_meta($day->ID, '_md_day_video_id', true))),
        'file_id'         => absint(get_post_meta($day->ID, '_md_day_file_id', true)),
        'file_title'      => (string) get_post_meta($day->ID, '_md_day_file_title', true),
        'file_name'       => md_attachment_name(absint(get_post_meta($day->ID, '_md_day_file_id', true))),
        'qa'              => $qa,
        'status'          => $day->post_status,
    );
}

/**
 * Validate the AJAX request and return the challenge ID.
 *
 * @return int
 */
function md_challenge_verify_ajax_challenge() {
    check_ajax_referer('md_challenge_admin', 'nonce');
    $challenge_id = isset($_POST['challenge_id']) ? absint($_POST['challenge_id']) : 0;
    if (!$challenge_id || 'md_challenge' !== get_post_type($challenge_id)) {
        wp_send_json_error(array('message' => 'چالش نامعتبر است.'), 400);
    }
    if (!current_user_can('edit_post', $challenge_id)) {
        wp_send_json_error(array('message' => 'شما اجازه مدیریت این چالش را ندارید.'), 403);
    }
    return $challenge_id;
}

/**
 * Validate that a day belongs to a challenge.
 *
 * @param int $day_id       Day ID.
 * @param int $challenge_id Challenge ID.
 * @return WP_Post
 */
function md_challenge_verify_ajax_day($day_id, $challenge_id) {
    $day = md_challenge_day($day_id);
    if (!$day || (int) get_post_meta($day->ID, '_md_challenge_id', true) !== (int) $challenge_id) {
        wp_send_json_error(array('message' => 'روز موردنظر به این چالش تعلق ندارد.'), 400);
    }
    return $day;
}

/**
 * Admin-facing counters.
 *
 * @param int $challenge_id Challenge ID.
 * @return array
 */
function md_challenge_admin_stats($challenge_id) {
    $start = md_challenge_start($challenge_id);
    $end   = md_challenge_end($challenge_id);
    return array(
        'content_days'  => count(md_challenge_days($challenge_id, true)),
        'calendar_days' => md_calendar_days($start, $end),
        'start'         => $start,
        'end'           => $end,
    );
}

/**
 * Update a meta value or remove it when it is empty.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @param mixed  $value   Value.
 */
function md_challenge_update_meta($post_id, $key, $value) {
    if ('' === $value || null === $value || 0 === $value || '0' === $value) {
        delete_post_meta($post_id, $key);
    } else {
        update_post_meta($post_id, $key, $value);
    }
}

/**
 * Load a day for the modal.
 */
function md_challenge_ajax_get_day() {
    $challenge_id = md_challenge_verify_ajax_challenge();
    $day_id       = isset($_POST['day_id']) ? absint($_POST['day_id']) : 0;
    $day          = md_challenge_verify_ajax_day($day_id, $challenge_id);

    wp_send_json_success(array(
        'day' => md_challenge_get_day_payload($day),
    ));
}
add_action('wp_ajax_md_challenge_get_day', 'md_challenge_ajax_get_day');

/**
 * Save a day.
 */
function md_challenge_ajax_save_day() {
    $challenge_id = md_challenge_verify_ajax_challenge();
    $day_id       = isset($_POST['day_id']) ? absint($_POST['day_id']) : 0;

    $title   = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
    $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
    $summary = isset($_POST['summary']) ? sanitize_textarea_field(wp_unslash($_POST['summary'])) : '';
    $extra   = isset($_POST['extra']) ? sanitize_textarea_field(wp_unslash($_POST['extra'])) : '';

    $tip_title = isset($_POST['tip_title']) ? sanitize_text_field(wp_unslash($_POST['tip_title'])) : '';
    $tip       = isset($_POST['tip']) ? sanitize_textarea_field(wp_unslash($_POST['tip'])) : '';

    $exercise_title = isset($_POST['exercise_title']) ? sanitize_text_field(wp_unslash($_POST['exercise_title'])) : '';
    $exercise_desc  = isset($_POST['exercise_desc']) ? sanitize_textarea_field(wp_unslash($_POST['exercise_desc'])) : '';
    $exercise_steps = isset($_POST['exercise_steps']) ? sanitize_textarea_field(wp_unslash($_POST['exercise_steps'])) : '';

    $audio_id    = isset($_POST['audio_id']) ? absint($_POST['audio_id']) : 0;
    $audio_title = isset($_POST['audio_title']) ? sanitize_text_field(wp_unslash($_POST['audio_title'])) : '';
    $video_id    = isset($_POST['video_id']) ? absint($_POST['video_id']) : 0;
    $video_url   = isset($_POST['video_url']) ? esc_url_raw(wp_unslash($_POST['video_url'])) : '';
    $video_title = isset($_POST['video_title']) ? sanitize_text_field(wp_unslash($_POST['video_title'])) : '';
    $file_id     = isset($_POST['file_id']) ? absint($_POST['file_id']) : 0;
    $file_title  = isset($_POST['file_title']) ? sanitize_text_field(wp_unslash($_POST['file_title'])) : '';

    $status = (isset($_POST['status']) && 'publish' === $_POST['status']) ? 'publish' : 'draft';

    if ('publish' === $status && !current_user_can('publish_posts')) {
        wp_send_json_error(array('message' => 'شما اجازه انتشار روز را ندارید.'), 403);
    }
    if ('publish' === $status && ('' === $title || '' === trim(wp_strip_all_tags($content)))) {
        wp_send_json_error(array('message' => 'برای انتشار روز، عنوان و متن اصلی الزامی است.'), 400);
    }

    $qa_raw = isset($_POST['qa']) ? wp_unslash($_POST['qa']) : '[]';
    if (is_array($qa_raw)) {
        $qa = $qa_raw;
    } else {
        $qa = json_decode((string) $qa_raw, true);
    }
    if (!is_array($qa)) {
        $qa = array();
    }
    $clean_qa = array();
    foreach ($qa as $item) {
        if (!is_array($item)) {
            continue;
        }
        $question = isset($item['q']) ? sanitize_textarea_field($item['q']) : '';
        $answer   = isset($item['a']) ? sanitize_textarea_field($item['a']) : '';
        if ('' === trim($question) && '' === trim($answer)) {
            continue;
        }
        $clean_qa[] = array('q' => $question, 'a' => $answer);
    }

    if ($day_id) {
        $day = md_challenge_verify_ajax_day($day_id, $challenge_id);
        if (!current_user_can('edit_post', $day_id)) {
            wp_send_json_error(array('message' => 'شما اجازه ویرایش این روز را ندارید.'), 403);
        }
        $order = md_challenge_day_order($day_id);
        $result = wp_update_post(array(
            'ID'           => $day_id,
            'post_title'   => $title,
            'post_content' => $content,
            'post_excerpt' => $summary,
            'post_status'  => $status,
        ), true);
    } else {
        $order = md_challenge_next_day_order($challenge_id);
        $result = wp_insert_post(array(
            'post_type'    => 'md_challenge_day',
            'post_title'   => $title,
            'post_content' => $content,
            'post_excerpt' => $summary,
            'post_status'  => $status,
        ), true);
        if (!is_wp_error($result)) {
            update_post_meta($result, '_md_challenge_id', $challenge_id);
            update_post_meta($result, '_md_day_order', $order);
        }
    }

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()), 500);
    }

    $saved_id = (int) $result;

    md_challenge_update_meta($saved_id, '_md_day_extra', $extra);
    md_challenge_update_meta($saved_id, '_md_day_tip_title', $tip_title);
    md_challenge_update_meta($saved_id, '_md_day_tip', $tip);
    md_challenge_update_meta($saved_id, '_md_day_exercise_title', $exercise_title);
    md_challenge_update_meta($saved_id, '_md_day_exercise_desc', $exercise_desc);
    md_challenge_update_meta($saved_id, '_md_day_exercise_steps', $exercise_steps);
    md_challenge_update_meta($saved_id, '_md_day_audio_id', $audio_id);
    md_challenge_update_meta($saved_id, '_md_day_audio_title', $audio_title);
    md_challenge_update_meta($saved_id, '_md_day_video_id', $video_id);
    md_challenge_update_meta($saved_id, '_md_day_video_url', $video_url);
    md_challenge_update_meta($saved_id, '_md_day_video_title', $video_title);
    md_challenge_update_meta($saved_id, '_md_day_file_id', $file_id);
    md_challenge_update_meta($saved_id, '_md_day_file_title', $file_title);

    if ($clean_qa) {
        update_post_meta($saved_id, '_md_day_qa', wp_json_encode($clean_qa, JSON_UNESCAPED_UNICODE));
    } else {
        delete_post_meta($saved_id, '_md_day_qa');
    }

    md_challenge_sync_counts($challenge_id);
    $saved = get_post($saved_id);

    wp_send_json_success(array(
        'row_html' => md_challenge_render_day_row($saved, $challenge_id),
        'day_id'   => $saved_id,
        'order'    => md_challenge_day_order($saved_id),
        'stats'    => md_challenge_admin_stats($challenge_id),
    ));
}
add_action('wp_ajax_md_challenge_save_day', 'md_challenge_ajax_save_day');

/**
 * Delete a day.
 */
function md_challenge_ajax_delete_day() {
    $challenge_id = md_challenge_verify_ajax_challenge();
    $day_id       = isset($_POST['day_id']) ? absint($_POST['day_id']) : 0;
    $day          = md_challenge_verify_ajax_day($day_id, $challenge_id);

    if (!current_user_can('delete_post', $day->ID)) {
        wp_send_json_error(array('message' => 'شما اجازه حذف این روز را ندارید.'), 403);
    }

    wp_delete_post($day->ID, true);
    md_challenge_normalize_day_orders($challenge_id);

    wp_send_json_success(array(
        'day_id' => $day_id,
        'stats'  => md_challenge_admin_stats($challenge_id),
    ));
}
add_action('wp_ajax_md_challenge_delete_day', 'md_challenge_ajax_delete_day');

/**
 * Duplicate a day right after the source row.
 */
function md_challenge_ajax_duplicate_day() {
    $challenge_id = md_challenge_verify_ajax_challenge();
    $day_id       = isset($_POST['day_id']) ? absint($_POST['day_id']) : 0;
    $source       = md_challenge_verify_ajax_day($day_id, $challenge_id);

    $source_order = md_challenge_day_order($source->ID);
    md_challenge_shift_day_orders($challenge_id, $source_order + 1, 1);

    $new_id = wp_insert_post(array(
        'post_type'    => 'md_challenge_day',
        'post_title'   => $source->post_title . ' (کپی)',
        'post_content' => $source->post_content,
        'post_excerpt' => $source->post_excerpt,
        'post_status'  => 'draft',
    ), true);

    if (is_wp_error($new_id)) {
        md_challenge_normalize_day_orders($challenge_id);
        wp_send_json_error(array('message' => $new_id->get_error_message()), 500);
    }

    $meta = get_post_meta($source->ID);
    foreach ($meta as $key => $values) {
        if (in_array($key, array('_edit_lock', '_edit_last', '_md_challenge_id', '_md_day_order'), true)) {
            continue;
        }
        if (isset($values[0])) {
            update_post_meta($new_id, $key, maybe_unserialize($values[0]));
        }
    }

    update_post_meta($new_id, '_md_challenge_id', $challenge_id);
    update_post_meta($new_id, '_md_day_order', $source_order + 1);
    md_challenge_sync_counts($challenge_id);

    $new_day = get_post($new_id);
    wp_send_json_success(array(
        'row_html' => md_challenge_render_day_row($new_day, $challenge_id),
        'day_id'   => $new_id,
        'stats'    => md_challenge_admin_stats($challenge_id),
    ));
}
add_action('wp_ajax_md_challenge_duplicate_day', 'md_challenge_ajax_duplicate_day');

/**
 * Persist the new order of days.
 */
function md_challenge_ajax_reorder_days() {
    $challenge_id = md_challenge_verify_ajax_challenge();
    $ids          = isset($_POST['ids']) ? (array) wp_unslash($_POST['ids']) : array();
    $ids          = array_values(array_filter(array_map('absint', $ids)));

    if (!$ids) {
        wp_send_json_error(array('message' => 'ترتیبی برای ذخیره ارسال نشده است.'), 400);
    }

    $existing = md_challenge_days($challenge_id, true);
    $existing_ids = array_map('intval', wp_list_pluck($existing, 'ID'));
    sort($existing_ids);
    $sorted_ids = array_map('intval', $ids);
    sort($sorted_ids);
    if ($existing_ids !== $sorted_ids) {
        wp_send_json_error(array('message' => 'فهرست روزها با چالش هم‌خوانی ندارد. صفحه را دوباره بارگذاری کنید.'), 400);
    }

    foreach ($ids as $index => $id) {
        update_post_meta($id, '_md_day_order', $index + 1);
    }
    md_challenge_sync_counts($challenge_id);

    wp_send_json_success(array(
        'stats' => md_challenge_admin_stats($challenge_id),
    ));
}
add_action('wp_ajax_md_challenge_reorder_days', 'md_challenge_ajax_reorder_days');

/**
 * Create missing day placeholders in one request without altering existing days.
 *
 * The saved challenge dates are authoritative; the editor must save date
 * changes before invoking this action. Up to 120 calendar days are supported
 * to avoid unexpectedly expensive requests on large date ranges.
 */
function md_challenge_ajax_generate_days() {
    $challenge_id = md_challenge_verify_ajax_challenge();
    $target       = md_challenge_calendar_days($challenge_id);

    if ($target < 1 || $target > 120) {
        wp_send_json_error(array('message' => 'ابتدا تاریخ شروع و پایان معتبر (حداکثر ۱۲۰ روز) را ذخیره کنید.'), 400);
    }
    $existing = md_challenge_days($challenge_id, true);
    $count    = count($existing);
    if ($count >= $target) {
        wp_send_json_success(array(
            'created'   => 0,
            'rows_html' => '',
            'stats'     => md_challenge_admin_stats($challenge_id),
            'message'   => 'تمام روزهای تقویمی قبلاً ساخته شده‌اند.',
        ));
    }

    $html    = '';
    $created = 0;
    $failed  = false;
    for ($order = $count + 1; $order <= $target; $order++) {
        $day_id = wp_insert_post(array(
            'post_type'    => 'md_challenge_day',
            'post_title'   => 'روز ' . md_fa_num($order),
            'post_content' => '',
            'post_status'  => 'draft',
        ), true);

        if (is_wp_error($day_id) || !$day_id) {
            $failed = true;
            break;
        }

        update_post_meta($day_id, '_md_challenge_id', $challenge_id);
        update_post_meta($day_id, '_md_day_order', $order);
        $html .= md_challenge_render_day_row(get_post($day_id), $challenge_id);
        $created++;
    }

    md_challenge_sync_counts($challenge_id);
    if (!$created && $failed) {
        wp_send_json_error(array('message' => 'ساخت روزها ناموفق بود. دوباره تلاش کنید.'), 500);
    }

    wp_send_json_success(array(
        'created'   => $created,
        'rows_html' => $html,
        'stats'     => md_challenge_admin_stats($challenge_id),
        'message'   => $failed ? 'بخشی از روزها ساخته شدند؛ برای ادامه دوباره تلاش کنید.' : 'پیش‌نویس روزهای باقی‌مانده ساخته شد.',
    ));
}
add_action('wp_ajax_md_challenge_generate_days', 'md_challenge_ajax_generate_days');


/**
 * Create a draft challenge from the "new challenge" screen.
 *
 * The day manager needs a real post ID, so when the editor clicks
 * «افزودن روز جدید» before saving, the current form values are posted here,
 * a draft is created and the browser is redirected to its edit screen with
 * the day modal already open. Nothing the editor typed is lost.
 */
function md_challenge_ajax_create_draft() {
    check_ajax_referer('md_challenge_admin', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => 'شما اجازه ساخت چالش را ندارید.'), 403);
    }

    $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
    if ('' === trim($title)) {
        $title = 'چالش تازه';
    }

    $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
    $excerpt = isset($_POST['excerpt']) ? sanitize_textarea_field(wp_unslash($_POST['excerpt'])) : '';

    $post_id = wp_insert_post(
        array(
            'post_type'    => 'md_challenge',
            'post_status'  => 'draft',
            'post_title'   => $title,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
        ),
        true
    );

    if (is_wp_error($post_id) || !$post_id) {
        wp_send_json_error(array('message' => 'ساخت پیش‌نویس چالش ممکن نشد. دوباره تلاش کنید.'), 500);
    }

    $start = md_challenge_posted_date('md_start_date');
    $end   = md_challenge_posted_date('md_end_date');

    if ($start) {
        update_post_meta($post_id, '_md_start_date', $start);
    }
    if ($end) {
        update_post_meta($post_id, '_md_end_date', $end);
    }

    $mode = isset($_POST['md_access_mode']) ? sanitize_key(wp_unslash($_POST['md_access_mode'])) : 'schedule';
    if (!in_array($mode, array('all', 'schedule'), true)) {
        $mode = 'schedule';
    }
    update_post_meta($post_id, '_md_access_mode', $mode);
    update_post_meta($post_id, '_md_show_locked_title', empty($_POST['md_show_locked_title']) ? 0 : 1);

    $enroll = isset($_POST['md_enrollment_url']) ? esc_url_raw(wp_unslash($_POST['md_enrollment_url'])) : '';
    if ($enroll) {
        update_post_meta($post_id, '_md_enrollment_url', $enroll);
    }

    update_post_meta($post_id, '_md_calendar_days', md_calendar_days($start, $end));
    md_challenge_sync_counts($post_id);

    wp_send_json_success(
        array(
            'id'      => $post_id,
            'editUrl' => get_edit_post_link($post_id, 'raw'),
        )
    );
}
add_action('wp_ajax_md_challenge_create_draft', 'md_challenge_ajax_create_draft');
