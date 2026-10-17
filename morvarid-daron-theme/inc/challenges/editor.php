<?php
/**
 * JavaScript-independent day management and calendar synchronisation.
 *
 * @package Morvarid_Daron
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fill missing calendar slots with editable draft posts. Never modify or
 * destroy existing lessons when dates or duration change.
 */
function md_challenge_ensure_calendar_days($challenge_id) {
    $total = md_challenge_calendar_days($challenge_id);
    if ($total < 1 || $total > 120) {
        return;
    }

    $existing = md_challenge_days($challenge_id, true);
    $taken = array();
    foreach ($existing as $day) {
        $taken[md_challenge_day_order($day->ID)] = true;
    }
    for ($i = 1; $i <= $total; $i++) {
        if (isset($taken[$i])) {
            continue;
        }
        $id = wp_insert_post(array(
            'post_type' => 'md_challenge_day',
            'post_status' => 'draft',
            'post_title' => 'روز ' . md_fa_num($i),
            'post_content' => '',
        ), true);
        if (is_wp_error($id) || !$id) {
            break;
        }
        update_post_meta($id, '_md_challenge_id', $challenge_id);
        update_post_meta($id, '_md_day_order', $i);
    }
}

function md_challenge_manual_day_editor($challenge_id) {
    if (!current_user_can('edit_post', $challenge_id)) {
        return;
    }
    $url = add_query_arg(array('post' => $challenge_id, 'action' => 'edit'), admin_url('post.php'));
    echo '<p class="md-day-fallback-notice"><strong>ویرایش قابل‌اعتماد بدون JavaScript:</strong> ';
    echo '<a href="' . esc_url($url . '#md-day-inline-form') . '">افزودن روز و ثبت تمرین و پرسش‌ها</a>. ';
    echo 'برای ویرایش یک روز موجود از لینک «ویرایش مستقیم» همان ردیف استفاده کنید.</p>';
}

/**
 * The standalone form renders in admin_footer OUTSIDE WordPress's #post form,
 * avoiding nested forms, inaccessible saves and dependency on a JS modal.
 */
function md_challenge_render_plain_day_form() {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || 'md_challenge' !== $screen->post_type || !in_array($screen->base, array('post'), true)) {
        return;
    }
    $challenge_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
    if (!$challenge_id || !current_user_can('edit_post', $challenge_id)) {
        return;
    }

    $edit_id = isset($_GET['md_edit_day']) ? absint($_GET['md_edit_day']) : 0;
    $day = $edit_id ? md_challenge_day($edit_id) : null;
    if ($day && (int) get_post_meta($day->ID, '_md_challenge_id', true) !== $challenge_id) {
        $day = null;
    }
    $save_message = isset($_GET['md_day_notice']) ? sanitize_key(wp_unslash($_GET['md_day_notice'])) : '';
    ?>
    <section id="md-day-inline-form" class="md-direct-day-panel" dir="rtl" aria-label="ثبت و ویرایش روز چالش">
        <h2><?php echo $day ? 'ویرایش مستقیم روز ' . esc_html(md_fa_num(md_challenge_day_order($day->ID))) : 'افزودن روز جدید'; ?></h2>
        <p>این فرم حتی اگر اسکریپت‌های پیشخوان اجرا نشوند کار می‌کند. برای چالش تاریخ‌دار، پیش‌نویس روزها با ذخیره چالش ساخته می‌شود.</p>
        <?php if ($save_message === 'saved') : ?><p class="notice notice-success inline">اطلاعات روز با موفقیت ذخیره شد.</p><?php endif; ?>
        <?php if ($save_message === 'invalid') : ?><p class="notice notice-error inline">برای انتشار روز، عنوان و متن اصلی را تکمیل کنید.</p><?php endif; ?>
        <?php if ($save_message === 'error') : ?><p class="notice notice-error inline">ذخیره روز انجام نشد. دوباره تلاش کنید.</p><?php endif; ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="md_challenge_direct_save_day">
            <input type="hidden" name="challenge_id" value="<?php echo esc_attr($challenge_id); ?>">
            <input type="hidden" name="day_id" value="<?php echo esc_attr($day ? $day->ID : 0); ?>">
            <?php wp_nonce_field('md_challenge_direct_save_day_' . $challenge_id); ?>
            <div class="md-direct-day-fields">
                <label>عنوان روز <input type="text" name="day_title" required value="<?php echo esc_attr($day ? $day->post_title : ''); ?>"></label>
                <label>خلاصه <textarea name="day_excerpt" rows="2"><?php echo esc_textarea($day ? $day->post_excerpt : ''); ?></textarea></label>
                <label class="md-wide">محتوای آموزشی روز <textarea name="day_content" rows="7"><?php echo esc_textarea($day ? $day->post_content : ''); ?></textarea></label>
                <label>عنوان تمرین <input type="text" name="exercise_title" value="<?php echo esc_attr($day ? get_post_meta($day->ID, '_md_day_exercise_title', true) : ''); ?>"></label>
                <label>شرح تمرین <textarea name="exercise_desc" rows="3"><?php echo esc_textarea($day ? get_post_meta($day->ID, '_md_day_exercise_desc', true) : ''); ?></textarea></label>
                <label class="md-wide">مراحل تمرین (هر مرحله در یک خط) <textarea name="exercise_steps" rows="3"><?php echo esc_textarea($day ? get_post_meta($day->ID, '_md_day_exercise_steps', true) : ''); ?></textarea></label>
                <label class="md-wide">پرسش‌های شرکت‌کننده (هر سؤال در یک خط) <textarea name="day_questions" rows="4"><?php echo esc_textarea($day ? get_post_meta($day->ID, '_md_day_questions', true) : ''); ?></textarea></label>
                <label>وضعیت
                    <select name="day_status">
                        <option value="draft" <?php selected($day ? $day->post_status : 'draft', 'draft'); ?>>پیش‌نویس</option>
                        <option value="publish" <?php selected($day ? $day->post_status : 'draft', 'publish'); ?>>منتشرشده</option>
                    </select>
                </label>
            </div>
            <button type="submit" class="button button-primary button-hero">ذخیره روز</button>
            <?php if ($day) : ?>
                <a class="button" href="<?php echo esc_url(add_query_arg(array('post' => $challenge_id, 'action' => 'edit'), admin_url('post.php')) . '#md-day-inline-form'); ?>">افزودن روز دیگر</a>
            <?php endif; ?>
        </form>
    </section>
    <?php
}
add_action('admin_footer', 'md_challenge_render_plain_day_form', 15);

function md_challenge_direct_save_day() {
    $challenge_id = isset($_POST['challenge_id']) ? absint($_POST['challenge_id']) : 0;
    if (!$challenge_id || 'md_challenge' !== get_post_type($challenge_id) || !current_user_can('edit_post', $challenge_id)) {
        wp_die('دسترسی به این چالش مجاز نیست.', '', array('response' => 403));
    }
    check_admin_referer('md_challenge_direct_save_day_' . $challenge_id);
    $day_id = isset($_POST['day_id']) ? absint($_POST['day_id']) : 0;
    $day = $day_id ? md_challenge_day($day_id) : null;
    if ($day_id && (!$day || (int) get_post_meta($day_id, '_md_challenge_id', true) !== $challenge_id || !current_user_can('edit_post', $day_id))) {
        wp_die('روز نامعتبر است.', '', array('response' => 403));
    }

    $title = isset($_POST['day_title']) ? sanitize_text_field(wp_unslash($_POST['day_title'])) : '';
    $content = isset($_POST['day_content']) ? wp_kses_post(wp_unslash($_POST['day_content'])) : '';
    $excerpt = isset($_POST['day_excerpt']) ? sanitize_textarea_field(wp_unslash($_POST['day_excerpt'])) : '';
    $status = (isset($_POST['day_status']) && 'publish' === $_POST['day_status'] && current_user_can('publish_posts')) ? 'publish' : 'draft';
    $notice = 'saved';
    if ('publish' === $status && (!$title || !trim(wp_strip_all_tags($content)))) {
        $notice = 'invalid';
    } else {
        $fields = array('post_title' => $title, 'post_content' => $content, 'post_excerpt' => $excerpt, 'post_status' => $status);
        $id = $day ? wp_update_post(array_merge($fields, array('ID' => $day_id)), true) :
            wp_insert_post(array_merge($fields, array('post_type' => 'md_challenge_day')), true);
        if (is_wp_error($id) || !$id) {
            $notice = 'error';
        } else {
            if (!$day) {
                update_post_meta($id, '_md_challenge_id', $challenge_id);
                update_post_meta($id, '_md_day_order', md_challenge_next_day_order($challenge_id));
            }
            $keys = array(
                'exercise_title' => '_md_day_exercise_title',
                'exercise_desc' => '_md_day_exercise_desc',
                'exercise_steps' => '_md_day_exercise_steps',
                'day_questions' => '_md_day_questions',
            );
            foreach ($keys as $input => $meta) {
                $value = isset($_POST[$input]) ? sanitize_textarea_field(wp_unslash($_POST[$input])) : '';
                update_post_meta($id, $meta, $value);
            }
            md_challenge_sync_counts($challenge_id);
        }
    }
    $url = add_query_arg(array(
        'post' => $challenge_id,
        'action' => 'edit',
        'md_day_notice' => $notice,
    ), admin_url('post.php'));
    wp_safe_redirect($url . '#md-day-inline-form');
    exit;
}
add_action('admin_post_md_challenge_direct_save_day', 'md_challenge_direct_save_day');
