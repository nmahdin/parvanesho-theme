<?php
/**
 * WordPress-admin reporting for challenge enrollment and answers.
 *
 * @package Morvarid_Daron
 */
if (!defined('ABSPATH')) { exit; }

function md_challenge_admin_participants_menu() {
    add_submenu_page(
        'edit.php?post_type=md_challenge',
        'شرکت‌کنندگان چالش',
        'شرکت‌کنندگان و پیشرفت',
        'edit_posts',
        'md-challenge-participants',
        'md_challenge_admin_participants_page'
    );
}
add_action('admin_menu', 'md_challenge_admin_participants_menu');

function md_challenge_admin_dashboard_url($challenge_id) {
    return add_query_arg(array(
        'post_type' => 'md_challenge',
        'page' => 'md-challenge-participants',
        'challenge_id' => absint($challenge_id),
    ), admin_url('edit.php'));
}

function md_challenge_admin_participants_page() {
    if (!current_user_can('edit_posts')) {
        wp_die('دسترسی مجاز نیست.', '', array('response' => 403));
    }
    $challenge_id = isset($_GET['challenge_id']) ? absint($_GET['challenge_id']) : 0;
    ?>
    <div class="wrap md-participants-admin" dir="rtl">
        <h1>داشبورد شرکت‌کنندگان چالش</h1>
        <?php if (!$challenge_id) : ?>
            <p>یک چالش را برای دیدن ثبت‌نام‌ها، لینک‌ها و گزارش تمرین‌های شرکت‌کنندگان انتخاب کنید.</p>
            <div class="md-admin-challenge-picker">
                <?php foreach (get_posts(array('post_type' => 'md_challenge', 'post_status' => array('publish', 'draft'), 'posts_per_page' => 100)) as $challenge) : ?>
                    <?php if (current_user_can('edit_post', $challenge->ID)) : ?>
                        <p><a class="button" href="<?php echo esc_url(md_challenge_admin_dashboard_url($challenge->ID)); ?>"><?php echo esc_html($challenge->post_title); ?> — مشاهده اعضا</a></p>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php elseif ('md_challenge' !== get_post_type($challenge_id) || !current_user_can('edit_post', $challenge_id)) : ?>
            <p>چالش پیدا نشد یا اجازه دسترسی ندارید.</p>
        <?php else :
            $members = md_challenge_participants($challenge_id);
            $days = md_challenge_days($challenge_id);
            $total = count($days);
            $selected_id = isset($_GET['member_id']) ? absint($_GET['member_id']) : 0;
            $selected = null;
            if ($selected_id) {
                foreach ($members as $candidate) {
                    if ($candidate->ID === $selected_id) {
                        $selected = $candidate;
                        break;
                    }
                }
            }
            ?>
            <p><a href="<?php echo esc_url(get_edit_post_link($challenge_id)); ?>">← ویرایش چالش</a> | <a href="<?php echo esc_url(get_permalink($challenge_id)); ?>" target="_blank" rel="noopener">مشاهده صفحه چالش</a></p>
            <div class="md-participant-admin-summary">
                <div><span>نام چالش</span><strong><?php echo esc_html(get_the_title($challenge_id)); ?></strong></div>
                <div><span>شرکت‌کنندگان</span><strong><?php echo esc_html(md_fa_num(count($members))); ?></strong></div>
                <div><span>روزهای منتشرشده</span><strong><?php echo esc_html(md_fa_num($total)); ?></strong></div>
                <div><span>چالش</span><strong><?php echo md_challenge_calendar_days($challenge_id) ? 'تاریخ‌دار' : 'بدون تاریخ'; ?></strong></div>
            </div>
            <section class="md-admin-create-member">
                <h2>ثبت شرکت‌کننده توسط مدیر</h2>
                <p>نام را وارد کنید تا کد ۸ رقمی و لینک اختصاصی ساخته شود. سپس کد یا لینک را خارج از سایت در اختیار شرکت‌کننده بگذارید.</p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="md_challenge_admin_create_member">
                    <input type="hidden" name="challenge_id" value="<?php echo esc_attr($challenge_id); ?>">
                    <?php wp_nonce_field('md_challenge_admin_create_member_' . $challenge_id); ?>
                    <label>نام شرکت‌کننده <input type="text" name="member_name" maxlength="120" required></label>
                    <button class="button button-primary" type="submit">ساخت کد و لینک جدید</button>
                </form>
            </section>
            <?php if (!$members) : ?><p class="notice notice-info inline">هنوز کسی در این چالش ثبت‌نام نکرده است.</p><?php endif; ?>
            <div class="md-participants-table-wrap">
                <table class="widefat striped md-participants-table">
                    <thead><tr><th>نام</th><th>کد ورود ۸ رقمی</th><th>ثبت‌نام</th><th>پیشرفت</th><th>آخرین فعالیت</th><th>لینک اختصاصی (محرمانه)</th><th>جزئیات</th></tr></thead>
                    <tbody>
                    <?php foreach ($members as $member) :
                        $entries = md_challenge_member_entries($member->ID);
                        $done = 0;
                        foreach ($days as $day) {
                            if (isset($entries[$day->ID]) && get_post_meta($entries[$day->ID]->ID, '_md_task_done', true)) {
                                $done++;
                            }
                        }
                        $link = md_challenge_member_link($member);
                        $last = get_post_meta($member->ID, '_md_member_last_activity', true);
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($member->post_title); ?></strong></td>
                            <td><strong class="md-member-admin-code" dir="ltr"><?php echo esc_html(get_post_meta($member->ID, '_md_member_code', true) ?: '—'); ?></strong></td>
                            <td><?php echo esc_html(md_format_jalali(substr($member->post_date, 0, 10), 'short')); ?></td>
                            <td><?php echo esc_html(md_fa_num($done) . ' / ' . md_fa_num($total)); ?></td>
                            <td><?php echo esc_html($last ? $last : '—'); ?></td>
                            <td><?php if ($link) : ?><input class="md-member-admin-url" type="text" dir="ltr" readonly onclick="this.select()" value="<?php echo esc_attr($link); ?>" aria-label="لینک خصوصی <?php echo esc_attr($member->post_title); ?>"><?php else : ?>غیرفعال<?php endif; ?></td>
                            <td><a class="button" href="<?php echo esc_url(add_query_arg('member_id', $member->ID, md_challenge_admin_dashboard_url($challenge_id)) . '#md-member-detail'); ?>">پاسخ‌ها</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($selected) :
                $entries = md_challenge_member_entries($selected->ID);
                ?>
                <section id="md-member-detail" class="md-admin-answer-detail">
                    <h2>گزارش <?php echo esc_html($selected->post_title); ?></h2>
                    <p>فقط مدیر مجاز چالش می‌تواند پاسخ‌ها را ببیند. برای جلوگیری از انتشار ناخواسته، پاسخ‌ها در صفحات عمومی نمایش داده نمی‌شوند.</p>
                    <?php foreach ($days as $day) :
                        $entry = isset($entries[$day->ID]) ? $entries[$day->ID] : null;
                        $answers = $entry ? get_post_meta($entry->ID, '_md_answers', true) : array();
                        if (!is_array($answers)) { $answers = array(); }
                        $notes = $entry ? get_post_meta($entry->ID, '_md_member_notes', true) : '';
                        $prompts = md_challenge_day_prompts($day);
                        ?>
                        <details class="md-admin-day-answer" <?php if ($entry) : ?>open<?php endif; ?>>
                            <summary>روز <?php echo esc_html(md_fa_num(md_challenge_day_order($day->ID))); ?> — <?php echo esc_html($day->post_title); ?> · <?php echo $entry ? (get_post_meta($entry->ID, '_md_task_done', true) ? 'تکمیل‌شده' : 'در دست انجام') : 'پاسخی ثبت نشده'; ?></summary>
                            <?php if ($entry) : ?>
                                <?php foreach ($prompts as $i => $prompt) : ?>
                                    <p><strong><?php echo esc_html($prompt); ?></strong></p>
                                    <p><?php echo nl2br(esc_html(isset($answers[$i]) ? $answers[$i] : '—')); ?></p>
                                <?php endforeach; ?>
                                <p><strong>گزارش تمرین / یادداشت:</strong></p>
                                <p><?php echo nl2br(esc_html($notes ? $notes : '—')); ?></p>
                                <p class="description">آخرین ثبت: <?php echo esc_html(get_post_meta($entry->ID, '_md_answer_updated', true)); ?></p>
                            <?php else : ?><p>هنوز تمرین یا پاسخی ثبت نشده است.</p><?php endif; ?>
                        </details>
                    <?php endforeach; ?>
                    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" onsubmit="return confirm('لینک این شرکت‌کننده غیرفعال شود؟');">
                        <input type="hidden" name="action" value="md_challenge_revoke_member">
                        <input type="hidden" name="challenge_id" value="<?php echo esc_attr($challenge_id); ?>">
                        <input type="hidden" name="member_id" value="<?php echo esc_attr($selected->ID); ?>">
                        <?php wp_nonce_field('md_challenge_revoke_member_' . $selected->ID); ?>
                        <?php if (!get_post_meta($selected->ID, '_md_member_revoked', true)) : ?><button type="submit" class="button">غیرفعال کردن لینک این شرکت‌کننده</button><?php else : ?><p>لینک شرکت‌کننده غیرفعال است.</p><?php endif; ?>
                    </form>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * The admin can manually create a participant and hand over the short code.
 * The public registration path never grants admin capabilities.
 */
function md_challenge_admin_create_member() {
    $challenge_id = isset($_POST['challenge_id']) ? absint($_POST['challenge_id']) : 0;
    if (!$challenge_id || get_post_type($challenge_id) !== 'md_challenge' || !current_user_can('edit_post', $challenge_id)) {
        wp_die('اجازه مدیریت این چالش را ندارید.', '', array('response' => 403));
    }
    check_admin_referer('md_challenge_admin_create_member_' . $challenge_id);
    $name = isset($_POST['member_name']) && is_string($_POST['member_name']) ? sanitize_text_field(wp_unslash($_POST['member_name'])) : '';
    if (!$name || strlen($name) > 360) {
        wp_die('نام شرکت‌کننده معتبر نیست.', '', array('response' => 400));
    }
    $code = md_challenge_generate_member_code($challenge_id);
    if (!$code) {
        wp_die('ساخت کد جدید ممکن نشد.', '', array('response' => 500));
    }
    $member_id = wp_insert_post(array('post_type' => 'md_challenge_member', 'post_status' => 'private', 'post_title' => $name), true);
    if (!$member_id || is_wp_error($member_id)) {
        wp_die('ثبت شرکت‌کننده انجام نشد.', '', array('response' => 500));
    }
    update_post_meta($member_id, '_md_challenge_id', $challenge_id);
    update_post_meta($member_id, '_md_member_code', $code);
    update_post_meta($member_id, '_md_member_code_hash', md_challenge_code_hash($challenge_id, $code));
    update_post_meta($member_id, '_md_member_key', bin2hex(random_bytes(32)));
    update_post_meta($member_id, '_md_member_registered_at', current_time('mysql'));
    wp_safe_redirect(add_query_arg('member_id', $member_id, md_challenge_admin_dashboard_url($challenge_id)) . '#md-member-detail');
    exit;
}
add_action('admin_post_md_challenge_admin_create_member', 'md_challenge_admin_create_member');

function md_challenge_revoke_member() {
    $challenge_id = isset($_POST['challenge_id']) ? absint($_POST['challenge_id']) : 0;
    $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
    if (!$challenge_id || !current_user_can('edit_post', $challenge_id)
        || 'md_challenge_member' !== get_post_type($member_id)
        || (int) get_post_meta($member_id, '_md_challenge_id', true) !== $challenge_id) {
        wp_die('دسترسی مجاز نیست.', '', array('response' => 403));
    }
    check_admin_referer('md_challenge_revoke_member_' . $member_id);
    update_post_meta($member_id, '_md_member_revoked', 1);
    wp_safe_redirect(add_query_arg('member_id', $member_id, md_challenge_admin_dashboard_url($challenge_id)) . '#md-member-detail');
    exit;
}
add_action('admin_post_md_challenge_revoke_member', 'md_challenge_revoke_member');

function md_challenge_admin_dashboard_assets($hook) {
    if (strpos((string) $hook, 'md-challenge-participants') === false) {
        return;
    }
    wp_enqueue_style('md-participants-admin', MD_THEME_URI . '/assets/css/challenge-members.css', array(), MD_THEME_VERSION);
}
add_action('admin_enqueue_scripts', 'md_challenge_admin_dashboard_assets');
