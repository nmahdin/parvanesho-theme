<?php
/**
 * Private challenge enrollment, bearer links and participant progress.
 *
 * @package Morvarid_Daron
 */
if (!defined('ABSPATH')) { exit; }

function md_challenge_register_private_types() {
    foreach (array(
        'md_challenge_member' => 'شرکت‌کننده چالش',
        'md_challenge_entry' => 'پاسخ چالش',
    ) as $type => $label) {
        if (!post_type_exists($type)) {
            register_post_type($type, array(
                'label' => $label,
                'public' => false,
                'publicly_queryable' => false,
                'exclude_from_search' => true,
                'show_ui' => false,
                'show_in_menu' => false,
                'show_in_rest' => false,
                'rewrite' => false,
                'query_var' => false,
                'supports' => array('title'),
                'capability_type' => 'post',
                'map_meta_cap' => true,
            ));
        }
    }
}
add_action('init', 'md_challenge_register_private_types', 7);

/**
 * Internal private CPT lookup. Public WP_Query deliberately excludes private
 * posts for signed-out visitors, even when explicitly requested. Authorise
 * the caller before accessing data returned by this helper.
 */
function md_challenge_private_posts_by_meta($type, $key, $value) {
    global $wpdb;
    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
         WHERE p.post_type = %s AND p.post_status = 'private'
         AND m.meta_key = %s AND m.meta_value = %s
         ORDER BY p.post_date DESC, p.ID DESC",
        $type, $key, (string) $value
    ));
    return array_values(array_filter(array_map('get_post', array_map('intval', $ids))));
}

function md_challenge_participants($challenge_id) {
    return md_challenge_private_posts_by_meta('md_challenge_member', '_md_challenge_id', absint($challenge_id));
}

/**
 * Short 8-digit code login for a challenge.
 * No attempt rate limit is applied to this login, as configured by the owner.
 * The separate direct link uses a high-entropy bearer token.
 */
function md_challenge_code_hash($challenge_id, $code) {
    return hash_hmac('sha256', absint($challenge_id) . ':' . $code, wp_salt('auth'));
}

function md_challenge_find_member_by_code($challenge_id, $code, $include_revoked = false) {
    if (!preg_match('/^[0-9]{8}$/D', $code)) {
        return null;
    }
    global $wpdb;
    $hash = md_challenge_code_hash($challenge_id, $code);
    $id = $wpdb->get_var($wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} c ON c.post_id = p.ID
         INNER JOIN {$wpdb->postmeta} h ON h.post_id = p.ID
         WHERE p.post_type = 'md_challenge_member' AND p.post_status = 'private'
         AND c.meta_key = '_md_challenge_id' AND c.meta_value = %s
         AND h.meta_key = '_md_member_code_hash' AND h.meta_value = %s LIMIT 1",
        (string) absint($challenge_id), $hash
    ));
    if (!$id || (!$include_revoked && get_post_meta($id, '_md_member_revoked', true))) {
        return null;
    }
    return get_post($id);
}

function md_challenge_generate_member_code($challenge_id) {
    for ($attempt = 0; $attempt < 30; $attempt++) {
        $code = (string) random_int(10000000, 99999999);
        if (!md_challenge_find_member_by_code($challenge_id, $code, true)) {
            return $code;
        }
    }
    return '';
}

function md_challenge_limit_response($text, $max) {
    $text = sanitize_textarea_field($text);
    return function_exists('mb_substr') ? mb_substr($text, 0, $max) : substr($text, 0, $max * 2);
}

function md_challenge_member_link($member) {
    $member_id = is_object($member) ? $member->ID : absint($member);
    $challenge_id = absint(get_post_meta($member_id, '_md_challenge_id', true));
    $token = (string) get_post_meta($member_id, '_md_member_key', true);
    if (!$challenge_id || !$token || get_post_status($member_id) !== 'private' || get_post_meta($member_id, '_md_member_revoked', true)) {
        return '';
    }
    return add_query_arg(array('md_member' => $member_id, 'md_key' => $token), get_permalink($challenge_id));
}

function md_challenge_member_from_request($challenge_id) {
    $id = isset($_GET['md_member']) ? absint($_GET['md_member']) : 0;
    $token = isset($_GET['md_key']) && is_string($_GET['md_key']) ? sanitize_text_field(wp_unslash($_GET['md_key'])) : '';
    return md_challenge_check_member($challenge_id, $id, $token);
}

function md_challenge_check_member($challenge_id, $member_id, $token) {
    if (!$member_id || !preg_match('/^[a-f0-9]{64}$/D', $token)) {
        return null;
    }
    $member = get_post($member_id);
    if (!$member || $member->post_type !== 'md_challenge_member' || $member->post_status !== 'private') {
        return null;
    }
    if ((int) get_post_meta($member_id, '_md_challenge_id', true) !== (int) $challenge_id
        || (bool) get_post_meta($member_id, '_md_member_revoked', true)) {
        return null;
    }
    $expected = (string) get_post_meta($member_id, '_md_member_key', true);
    return $expected && hash_equals($expected, $token) ? $member : null;
}

function md_challenge_member_request_headers() {
    if (isset($_GET['md_member']) || isset($_GET['md_key'])) {
        nocache_headers();
        header('Referrer-Policy: no-referrer', true);
        header('X-Robots-Tag: noindex, nofollow, noarchive', true);
        add_filter('wp_robots', function ($robots) {
            $robots['noindex'] = true;
            $robots['nofollow'] = true;
            return $robots;
        });
    }
}
add_action('template_redirect', 'md_challenge_member_request_headers', 1);

function md_challenge_member_rate_limit($action) {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    $key = 'md_c_rate_' . md5($action . '|' . $ip . '|' . wp_salt('auth'));
    $count = (int) get_transient($key);
    if ($count >= 8) {
        wp_die('تعداد درخواست‌ها بیش از حد مجاز است. بعداً دوباره تلاش کنید.', '', array('response' => 429));
    }
    set_transient($key, $count + 1, HOUR_IN_SECONDS);
}

/** Registration and login via eight-digit code for either challenge mode. */
function md_challenge_registration_form($challenge_id) {
    if (function_exists('md_learning_current_challenge_member')) {
        MD_Learning_Challenges::render_join($challenge_id);
        return;
    }
    if (get_post_status($challenge_id) !== 'publish') {
        return;
    }
    ?>
    <section class="md-member-registration" id="md-challenge-enroll" aria-labelledby="md-enroll-heading">
        <h2 id="md-enroll-heading">عضویت در چالش</h2>
        <p>فقط نام خود را ثبت کنید. کد ۸ رقمی و لینک اختصاصی شما در داشبورد نمایش داده می‌شود. هر دو را برای ورودهای بعدی نگه دارید.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="md-member-registration-form">
            <input type="hidden" name="action" value="md_challenge_register_member">
            <input type="hidden" name="challenge_id" value="<?php echo esc_attr($challenge_id); ?>">
            <?php wp_nonce_field('md_challenge_register_' . $challenge_id); ?>
            <label>نام و نام خانوادگی <input type="text" name="member_name" maxlength="120" autocomplete="name" required></label>
            <label class="md-enroll-consent"><input type="checkbox" name="member_consent" value="1" required> با ذخیره نام و پاسخ‌های تمرین‌هایم در این چالش موافقم.</label>
            <div class="md-enroll-honeypot" aria-hidden="true"><label>وب‌سایت <input type="text" name="member_website" tabindex="-1" autocomplete="off"></label></div>
            <button type="submit" class="btn btn-primary">ثبت‌نام و دریافت کد</button>
        </form>
        <div class="md-member-code-login">
            <h3>قبلاً ثبت‌نام کرده‌ام</h3>
            <p>کد ۸ رقمی خود را وارد کنید تا وارد همان داشبورد قبلی شوید.</p>
            <?php if (isset($_GET['md_login_error']) && 'invalid' === sanitize_key(wp_unslash($_GET['md_login_error']))) : ?>
                <p class="md-code-login-error" role="alert">کد واردشده معتبر نیست یا غیرفعال شده است.</p>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="md-member-code-form">
                <input type="hidden" name="action" value="md_challenge_login_code">
                <input type="hidden" name="challenge_id" value="<?php echo esc_attr($challenge_id); ?>">
                <?php wp_nonce_field('md_challenge_login_' . $challenge_id); ?>
                <label>کد اختصاصی ۸ رقمی
                    <input type="text" name="member_code" inputmode="numeric" pattern="[0-9۰-۹٠-٩]{8}" maxlength="8" minlength="8" autocomplete="off" dir="ltr" required placeholder="۱۲۳۴۵۶۷۸">
                </label>
                <button type="submit" class="btn btn-primary">ورود به داشبورد من</button>
            </form>
        </div>
        <p class="md-enroll-privacy">ورود به ایمیل یا حساب وردپرس نیاز ندارد. کد و لینک شما خصوصی‌اند؛ در صورت گم‌شدن، مدیر چالش می‌تواند کدتان را به شما بدهد.</p>
    </section>
    <?php
}

function md_challenge_register_member() {
    if (function_exists('md_learning_current_challenge_member')) {
        wp_die('ثبت‌نام قدیمی چالش غیرفعال است؛ از پروفایل یکپارچه استفاده کنید.', '', array('response' => 410));
    }
    $challenge_id = isset($_POST['challenge_id']) ? absint($_POST['challenge_id']) : 0;
    if (!$challenge_id || get_post_type($challenge_id) !== 'md_challenge' || get_post_status($challenge_id) !== 'publish') {
        wp_die('چالش برای ثبت‌نام در دسترس نیست.', '', array('response' => 400));
    }
    check_admin_referer('md_challenge_register_' . $challenge_id);
    md_challenge_member_rate_limit('register');
    if (!empty($_POST['member_website'])) {
        wp_safe_redirect(get_permalink($challenge_id));
        exit;
    }
    if (empty($_POST['member_consent'])) {
        wp_die('برای ثبت‌نام، رضایت به ذخیره اطلاعات ضروری است.', '', array('response' => 400));
    }
    $name = isset($_POST['member_name']) && is_string($_POST['member_name']) ? sanitize_text_field(wp_unslash($_POST['member_name'])) : '';
    if (!$name || strlen($name) > 360) {
        wp_die('نام معتبر را وارد کنید.', '', array('response' => 400));
    }
    $code = md_challenge_generate_member_code($challenge_id);
    if (!$code) {
        wp_die('ساخت کد ورود ممکن نشد. دوباره تلاش کنید.', '', array('response' => 500));
    }
    $member_id = wp_insert_post(array('post_type' => 'md_challenge_member', 'post_status' => 'private', 'post_title' => $name), true);
    if (is_wp_error($member_id) || !$member_id) {
        wp_die('ثبت‌نام انجام نشد. دوباره تلاش کنید.', '', array('response' => 500));
    }
    update_post_meta($member_id, '_md_challenge_id', $challenge_id);
    update_post_meta($member_id, '_md_member_code', $code);
    update_post_meta($member_id, '_md_member_code_hash', md_challenge_code_hash($challenge_id, $code));
    update_post_meta($member_id, '_md_member_key', bin2hex(random_bytes(32)));
    update_post_meta($member_id, '_md_member_registered_at', current_time('mysql'));
    wp_safe_redirect(md_challenge_member_link($member_id));
    exit;
}
add_action('admin_post_md_challenge_register_member', 'md_challenge_register_member');
add_action('admin_post_nopriv_md_challenge_register_member', 'md_challenge_register_member');

/** Code-only login: deliberately no attempt counter or lockout. */
function md_challenge_login_code() {
    if (function_exists('md_learning_current_challenge_member')) {
        wp_die('ورود با کد مخصوص چالش جای خود را به ورود با کد مرکزی داده است.', '', array('response' => 410));
    }
    $challenge_id = isset($_POST['challenge_id']) ? absint($_POST['challenge_id']) : 0;
    if (!$challenge_id || get_post_type($challenge_id) !== 'md_challenge' || get_post_status($challenge_id) !== 'publish') {
        wp_die('چالش موجود نیست.', '', array('response' => 400));
    }
    check_admin_referer('md_challenge_login_' . $challenge_id);
    $raw = isset($_POST['member_code']) && is_string($_POST['member_code']) ? wp_unslash($_POST['member_code']) : '';
    $code = md_normalize_digits(trim($raw));
    $member = md_challenge_find_member_by_code($challenge_id, $code);
    if (!$member) {
        wp_safe_redirect(add_query_arg('md_login_error', 'invalid', get_permalink($challenge_id)) . '#md-challenge-enroll');
        exit;
    }
    wp_safe_redirect(md_challenge_member_link($member));
    exit;
}
add_action('admin_post_md_challenge_login_code', 'md_challenge_login_code');
add_action('admin_post_nopriv_md_challenge_login_code', 'md_challenge_login_code');

/** One saved record per participant/day; submissions stay private. */
function md_challenge_member_entries($member_id) {
    $rows = md_challenge_private_posts_by_meta('md_challenge_entry', '_md_member_id', absint($member_id));
    $map = array();
    foreach ($rows as $row) {
        $day_id = absint(get_post_meta($row->ID, '_md_day_id', true));
        if ($day_id) {
            $map[$day_id] = $row;
        }
    }
    return $map;
}

function md_challenge_day_prompts($day) {
    $text = (string) get_post_meta($day->ID, '_md_day_questions', true);
    $lines = preg_split('/\r\n|\r|\n/', $text);
    return array_slice(array_values(array_filter(array_map('trim', $lines), 'strlen')), 0, 12);
}

function md_challenge_member_can_open_day($day, $challenge_id, $entries, $published_days) {
    if (!md_challenge_day_is_unlocked($day, $challenge_id)) {
        return false;
    }
    // Undated challenges progress one completed step at a time.
    if (md_challenge_calendar_days($challenge_id) === 0 && !md_challenge_start($challenge_id)) {
        foreach ($published_days as $item) {
            if ($item->ID === $day->ID) {
                return true;
            }
            if (empty($entries[$item->ID]) || !get_post_meta($entries[$item->ID]->ID, '_md_task_done', true)) {
                return false;
            }
        }
    }
    return true;
}

function md_challenge_save_member_entry() {
    $challenge_id = isset($_POST['challenge_id']) ? absint($_POST['challenge_id']) : 0;
    $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
    $day_id = isset($_POST['day_id']) ? absint($_POST['day_id']) : 0;
    if (function_exists('md_learning_current_challenge_member')) {
        // Never trust member_id from the form; compare against server session.
        $member = md_learning_current_challenge_member($challenge_id);
        if (!$member || (int) $member->ID !== $member_id) {
            wp_die('این پاسخ متعلق به پروفایل فعلی شما نیست.', '', array('response' => 403));
        }
    } else {
        $token = isset($_POST['md_key']) && is_string($_POST['md_key']) ? sanitize_text_field(wp_unslash($_POST['md_key'])) : '';
        $member = md_challenge_check_member($challenge_id, $member_id, $token);
    }
    if (!$member || get_post_status($challenge_id) !== 'publish') {
        wp_die('لینک خصوصی نامعتبر یا غیرفعال است.', '', array('response' => 403));
    }
    check_admin_referer('md_challenge_entry_' . $member_id . '_' . $day_id);
    // Saved responses are authenticated by the private key and nonce. Do not
    // lock out a participant who is actively completing multiple lessons.
    $day = md_challenge_day($day_id);
    $days = md_challenge_days($challenge_id);
    $ids = array_map('intval', wp_list_pluck($days, 'ID'));
    $entries = md_challenge_member_entries($member_id);
    if (!$day || !in_array($day_id, $ids, true) || !md_challenge_member_can_open_day($day, $challenge_id, $entries, $days)) {
        wp_die('این روز هنوز برای شما فعال نیست.', '', array('response' => 403));
    }
    $prompts = md_challenge_day_prompts($day);
    $submitted = isset($_POST['answers']) && is_array($_POST['answers']) ? wp_unslash($_POST['answers']) : array();
    $answers = array();
    foreach ($prompts as $index => $prompt) {
        $answers[] = isset($submitted[$index]) && is_string($submitted[$index]) ? md_challenge_limit_response($submitted[$index], 4000) : '';
    }
    $notes = isset($_POST['member_notes']) && is_string($_POST['member_notes']) ? md_challenge_limit_response(wp_unslash($_POST['member_notes']), 5000) : '';
    $completed = !empty($_POST['task_done']) ? 1 : 0;
    $checked_tasks = array();
    $valid_tasks = array_values(array_filter(md_challenge_day_items($day_id, 'tasks'), function ($item) {
        return trim((string) ($item['title'] ?? '') . (string) ($item['description'] ?? '') . (string) ($item['steps'] ?? '')) !== '';
    }));
    $selected = isset($_POST['md_checked_tasks']) && is_array($_POST['md_checked_tasks']) ? $_POST['md_checked_tasks'] : array();
    foreach ($selected as $key) {
        if (is_scalar($key) && ctype_digit((string) $key) && (int) $key < count($valid_tasks)) {
            $checked_tasks[(int) $key] = 1;
        }
    }
    $entry = isset($entries[$day_id]) ? $entries[$day_id] : null;
    if (!$entry) {
        $entry_id = wp_insert_post(array('post_type' => 'md_challenge_entry', 'post_status' => 'private',
            'post_title' => 'پاسخ روز ' . $day_id . ' - ' . $member_id), true);
        if (is_wp_error($entry_id) || !$entry_id) {
            wp_die('ذخیره پاسخ ممکن نشد.', '', array('response' => 500));
        }
        update_post_meta($entry_id, '_md_member_id', $member_id);
        update_post_meta($entry_id, '_md_day_id', $day_id);
        update_post_meta($entry_id, '_md_challenge_id', $challenge_id);
    } else {
        $entry_id = $entry->ID;
    }
    update_post_meta($entry_id, '_md_answers', $answers);
    update_post_meta($entry_id, '_md_member_notes', $notes);
    update_post_meta($entry_id, '_md_task_done', $completed);
    update_post_meta($entry_id, '_md_tasks_checked', $checked_tasks);
    update_post_meta($entry_id, '_md_answer_updated', current_time('mysql'));
    update_post_meta($member_id, '_md_member_last_activity', current_time('mysql'));
    $return_url = function_exists('md_learning_current_challenge_member')
        ? get_permalink($challenge_id) : md_challenge_member_link($member);
    wp_safe_redirect(add_query_arg('md_saved', $day_id, $return_url) . '#member-day-' . $day_id);
    exit;
}
add_action('admin_post_md_challenge_save_member_entry', 'md_challenge_save_member_entry');
add_action('admin_post_nopriv_md_challenge_save_member_entry', 'md_challenge_save_member_entry');


/** Participant-only form inside the standard challenge day layout. */
function md_challenge_member_render_day_form($day, $challenge_id, $state) {
    $entry = $state['entry'] ?? null;
    $answers = $entry ? get_post_meta($entry->ID, '_md_answers', true) : array();
    if (!is_array($answers)) $answers = array();
    $notes = $entry ? (string) get_post_meta($entry->ID, '_md_member_notes', true) : '';
    $prompts = md_challenge_day_prompts($day);
    ?>
    <form id="<?php echo esc_attr($state['form_id']); ?>" class="md-member-day-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <input type="hidden" name="action" value="md_challenge_save_member_entry">
        <input type="hidden" name="challenge_id" value="<?php echo esc_attr($challenge_id); ?>">
        <input type="hidden" name="member_id" value="<?php echo esc_attr($state['member_id']); ?>">
        <?php if (empty($state['central'])) : ?>
            <input type="hidden" name="md_key" value="<?php echo esc_attr($state['token']); ?>">
        <?php endif; ?>
        <input type="hidden" name="day_id" value="<?php echo esc_attr($day->ID); ?>">
        <?php wp_nonce_field('md_challenge_entry_' . $state['member_id'] . '_' . $day->ID); ?>
        <?php if ($prompts) : ?><h3>پاسخ‌های من به پرسش‌های این روز</h3><?php endif; ?>
        <?php foreach ($prompts as $i => $prompt) : ?>
            <label class="md-member-answer-field"><strong><span class="md-member-question-index">پرسش <?php echo esc_html(md_fa_num($i + 1)); ?></span> <?php echo esc_html($prompt); ?></strong>
                <textarea name="answers[<?php echo esc_attr($i); ?>]" rows="3" maxlength="4000"><?php echo esc_textarea(isset($answers[$i]) ? $answers[$i] : ''); ?></textarea>
            </label>
        <?php endforeach; ?>
        <label><strong>یادداشت و گزارش فعالیت من</strong><textarea name="member_notes" rows="4" maxlength="5000"><?php echo esc_textarea($notes); ?></textarea></label>
        <label class="md-member-check"><input type="checkbox" name="task_done" value="1" <?php checked(!empty($state['done'])); ?>> این روز را تکمیل کرده‌ام.</label>
        <button type="submit" class="btn btn-primary">ذخیره پاسخ‌ها و فعالیت‌ها</button>
        <?php if (isset($_GET['md_saved']) && absint($_GET['md_saved']) === (int) $day->ID) : ?>
            <span class="md-member-saved" role="status">پاسخ‌ها و کارهای شما ذخیره شد.</span>
        <?php endif; ?>
    </form>
    <?php
}

function md_challenge_render_member_dashboard($challenge_id, $member) {
    $days = md_challenge_days($challenge_id);
    $entries = md_challenge_member_entries($member->ID);
    $completed = 0;
    foreach ($days as $day) {
        if (isset($entries[$day->ID]) && get_post_meta($entries[$day->ID]->ID, '_md_task_done', true)) ++$completed;
    }
    $total = count($days);
    $percent = $total ? (int) round($completed * 100 / $total) : 0;
    $central = function_exists('md_learning_current_challenge_member');
    $token = $central ? '' : (string) get_post_meta($member->ID, '_md_member_key', true);
    $intro = apply_filters('the_content', get_post_field('post_content', $challenge_id));
    $receipt = $central && class_exists('MD_Learning_Auth') ? MD_Learning_Auth::take_receipt() : null;
    ?>
    <main id="main" class="md-challenge-page md-member-page" dir="rtl">
        <section class="md-challenge-hero">
            <div class="container md-challenge-hero-grid">
                <div class="md-challenge-hero-copy">
                    <span class="eyebrow">چالش مروارید درون · مسیر اختصاصی من</span>
                    <h1><?php echo esc_html(get_the_title($challenge_id)); ?></h1>
                    <div class="md-challenge-meta">
                        <span>عضو چالش: <?php echo esc_html($member->post_title); ?></span>
                        <span><?php echo esc_html(md_fa_num($total)); ?> روز</span>
                        <span><?php echo esc_html(md_fa_num($completed)); ?> روز انجام‌شده</span>
                    </div>
                    <?php if (has_excerpt($challenge_id)) : ?>
                        <p class="md-challenge-summary"><?php echo esc_html(get_the_excerpt($challenge_id)); ?></p>
                    <?php endif; ?>
                    <div class="md-challenge-progress">
                        <div class="md-challenge-progress-head"><strong>پیشرفت من</strong><span><?php echo esc_html(md_fa_num($percent)); ?>٪</span></div>
                        <div class="md-challenge-progress-bar" role="progressbar" aria-valuenow="<?php echo esc_attr($percent); ?>" aria-valuemin="0" aria-valuemax="100">
                            <span style="width:<?php echo esc_attr($percent); ?>%"></span>
                        </div>
                    </div>
                    <?php if ($central) : ?>
                        <a class="btn btn-light" href="<?php echo esc_url(MD_Learning_Schema::url('dashboard')); ?>">داشبورد یادگیری من ←</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <div class="container md-challenge-layout">
            <div class="md-challenge-main">
                <?php if ($receipt && isset($receipt['code'], $receipt['link'])) : ?>
                    <section class="md-learning-receipt md-challenge-first-receipt" aria-label="اطلاعات ورود خصوصی">
                        <h2>کد و لینک ورود شما آماده است</h2>
                        <p><strong>این اطلاعات فقط یک بار نمایش داده می‌شود.</strong> آن‌ها را در جای امن نگه دارید.</p>
                        <label>کد ۸ رقمی ورود <input readonly type="text" dir="ltr" onclick="this.select()" value="<?php echo esc_attr($receipt['code']); ?>"></label>
                        <label>لینک اختصاصی ورود <input readonly type="text" dir="ltr" onclick="this.select()" value="<?php echo esc_attr($receipt['link']); ?>"></label>
                        <p>این اطلاعات محرمانه‌اند و نباید با دیگران به اشتراک گذاشته شوند.</p>
                    </section>
                <?php endif; ?>
                <?php if (isset($_GET['md_joined']) && $_GET['md_joined'] === '1' && $central) : ?>
                    <p class="md-enroll-message" role="status">عضویت شما با موفقیت ثبت شد.</p>
                <?php endif; ?>
                <?php if (trim(wp_strip_all_tags($intro)) !== '') : ?>
                    <section class="md-challenge-intro entry-content"><?php echo $intro; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></section>
                <?php endif; ?>
                <div class="md-challenge-days md-member-days" id="md-member-days">
                    <?php if (!$days) : ?><p class="empty-note">محتوای روزهای این چالش هنوز منتشر نشده است.</p><?php endif; ?>
                    <?php foreach ($days as $day) :
                        $opened = md_challenge_member_can_open_day($day, $challenge_id, $entries, $days);
                        $entry = isset($entries[$day->ID]) ? $entries[$day->ID] : null;
                        $tasks = $entry ? get_post_meta($entry->ID, '_md_tasks_checked', true) : array();
                        md_challenge_render_day($day, $challenge_id, array(
                            'opened' => $opened,
                            'done' => $entry && get_post_meta($entry->ID, '_md_task_done', true),
                            'tasks' => is_array($tasks) ? $tasks : array(),
                            'form_id' => 'md-member-entry-' . $day->ID,
                            'member_id' => $member->ID,
                            'central' => $central,
                            'token' => $token,
                            'entry' => $entry,
                        ));
                    endforeach; ?>
                </div>
            </div>
            <?php md_render_sidebar('challenge', 'right'); ?>
        </div>
    </main>
    <?php
}

/** Load participant styling only on public challenge screens. */
function md_challenge_member_public_assets() {
    if (is_singular('md_challenge')) {
        wp_enqueue_style('md-challenge-members', MD_THEME_URI . '/assets/css/challenge-members.css', array('md-challenge'), MD_THEME_VERSION);
    }
}
add_action('wp_enqueue_scripts', 'md_challenge_member_public_assets', 20);
