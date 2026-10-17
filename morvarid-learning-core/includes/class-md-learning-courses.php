<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Phase F: central learner course memberships and independently-owned
 * per-lesson progress, backed by the existing theme's course builder.
 *
 * Enrollment never grants a payment entitlement. Courses must explicitly opt
 * into open enrollment, otherwise only an administrator can enroll learners.
 * Lesson pages remain publicly readable as in the existing theme: membership
 * gates personal progress actions, not content delivery.
 */
final class MD_Learning_Courses {
    const OPEN_META = '_md_learning_open_enrollment';

    public static function init() {
        add_action('admin_post_md_learning_course_join', array(__CLASS__, 'join'));
        add_action('admin_post_nopriv_md_learning_course_join', array(__CLASS__, 'join'));
        add_action('admin_post_md_learning_lesson_progress', array(__CLASS__, 'change_progress'));
        add_action('admin_post_nopriv_md_learning_lesson_progress', array(__CLASS__, 'change_progress'));
        add_action('add_meta_boxes_md_course', array(__CLASS__, 'add_course_box'));
        add_action('save_post_md_course', array(__CLASS__, 'save_course_settings'), 20, 3);
        add_action('wp_enqueue_scripts', array(__CLASS__, 'assets'));
        add_action('template_redirect', array(__CLASS__, 'private_headers'), 4);
    }

    public static function table($type) {
        if (!in_array($type, array('enrollments', 'lesson_progress'), true)) {
            return '';
        }
        return MD_Learning_Schema::table($type);
    }

    public static function is_published_course($course_id) {
        return absint($course_id) > 0 && get_post_type($course_id) === 'md_course'
            && get_post_status($course_id) === 'publish';
    }

    public static function is_published_lesson($lesson_id, $course_id) {
        return self::is_published_course($course_id)
            && absint($lesson_id) > 0
            && get_post_type($lesson_id) === 'md_lesson'
            && get_post_status($lesson_id) === 'publish'
            && (int) get_post_meta($lesson_id, '_md_course_id', true) === absint($course_id);
    }

    public static function open_enrollment($course_id) {
        return self::is_published_course($course_id)
            && get_post_meta($course_id, self::OPEN_META, true) === '1';
    }

    public static function published_lessons($course_id) {
        if (!self::is_published_course($course_id)) {
            return array();
        }
        if (function_exists('md_get_course_lessons')) {
            $lessons = md_get_course_lessons($course_id, false);
        } else {
            $lessons = get_posts(array(
                'post_type' => 'md_lesson',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'orderby' => array('date' => 'ASC', 'ID' => 'ASC'),
                'meta_query' => array(array(
                    'key' => '_md_course_id', 'value' => absint($course_id),
                    'compare' => '=', 'type' => 'NUMERIC',
                )),
                'no_found_rows' => true,
            ));
        }
        return array_values(array_filter((array) $lessons, function ($lesson) use ($course_id) {
            return isset($lesson->ID) && self::is_published_lesson($lesson->ID, $course_id);
        }));
    }

    /** Lookup is intentionally learner/course-scoped, never by an untrusted ID. */
    public static function enrollment($learner_id, $course_id) {
        if (!$learner_id || !$course_id) {
            return null;
        }
        global $wpdb;
        $table = self::table('enrollments');
        return $wpdb->get_row($wpdb->prepare(
            "SELECT id, learner_id, course_id, status, enrolled_at
             FROM {$table} WHERE learner_id = %d AND course_id = %d LIMIT 1",
            absint($learner_id), absint($course_id)
        ));
    }

    public static function is_enrolled($learner_id, $course_id) {
        $row = self::enrollment($learner_id, $course_id);
        return $row && $row->status === 'active' && self::is_published_course($course_id);
    }

    /** Prevent client-chosen learner identities: caller must opt into admin mode. */
    public static function enroll($learner_id, $course_id, $admin = false) {
        $learner_id = absint($learner_id);
        $course_id = absint($course_id);
        if (!self::is_published_course($course_id)) {
            return new WP_Error('unavailable', 'دوره در دسترس نیست.');
        }
        if (!$admin) {
            if ($learner_id !== MD_Learning_Auth::current_learner_id()
                || !self::open_enrollment($course_id)) {
                return new WP_Error('not_permitted', 'ثبت‌نام آزاد این دوره فعال نیست.');
            }
        } elseif (!current_user_can('manage_options')) {
            return new WP_Error('not_permitted', 'مجوز افزودن یادگیرنده را ندارید.');
        }

        $learner = MD_Learning_Auth::get_learner($learner_id);
        if (!$learner || $learner->status !== 'active') {
            return new WP_Error('invalid_learner', 'حساب یادگیرنده فعال نیست.');
        }
        $existing = self::enrollment($learner_id, $course_id);
        if ($existing) {
            return $existing->status === 'active'
                ? $existing
                : new WP_Error('revoked', 'این عضویت غیرفعال شده است؛ بازیابی آن با مدیر دوره است.');
        }
        global $wpdb;
        $table = self::table('enrollments');
        $now = gmdate('Y-m-d H:i:s');
        $created = $wpdb->insert($table, array(
            'learner_id' => $learner_id, 'course_id' => $course_id,
            'status' => 'active', 'enrolled_at' => $now, 'updated_at' => $now,
        ), array('%d','%d','%s','%s','%s'));
        if (!$created) {
            // A database unique index makes concurrent join requests idempotent.
            $existing = self::enrollment($learner_id, $course_id);
            return $existing && $existing->status === 'active'
                ? $existing : new WP_Error('failed', 'عضویت در دوره ذخیره نشد.');
        }
        return self::enrollment($learner_id, $course_id);
    }

    /** Admin-only assignment, suspension and reactivation, never deleting progress. */
    public static function set_membership_status($learner_id, $course_id, $status) {
        if (!current_user_can('manage_options') || !in_array($status, array('active', 'revoked'), true)) {
            return new WP_Error('forbidden', 'اجازه تغییر عضویت را ندارید.');
        }
        $learner_id = absint($learner_id);
        $course_id = absint($course_id);
        if (!self::is_published_course($course_id)) {
            return new WP_Error('unavailable', 'دوره منتشر نشده است.');
        }
        $current = self::enrollment($learner_id, $course_id);
        if (!$current) {
            return $status === 'active'
                ? self::enroll($learner_id, $course_id, true)
                : new WP_Error('missing', 'عضویتی برای غیرفعال‌کردن وجود ندارد.');
        }
        global $wpdb;
        $done = $wpdb->update(self::table('enrollments'), array(
            'status' => $status, 'updated_at' => gmdate('Y-m-d H:i:s'),
        ), array('learner_id' => $learner_id, 'course_id' => $course_id),
        array('%s','%s'), array('%d','%d'));
        if ($done === false) {
            return new WP_Error('failed', 'تغییر وضعیت عضویت انجام نشد.');
        }
        return self::enrollment($learner_id, $course_id);
    }

    private static function progress_rows($learner_id, $course_id) {
        global $wpdb;
        $table = self::table('lesson_progress');
        $rows = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT lesson_id, status, completed_at FROM {$table}
             WHERE learner_id = %d AND course_id = %d",
            absint($learner_id), absint($course_id)
        ));
        $map = array();
        foreach ($rows as $row) {
            $map[(int) $row->lesson_id] = $row;
        }
        return $map;
    }

    /**
     * Summary denominator counts only currently published lessons assigned
     * to this published course, not draft/removed/reassigned lesson records.
     */
    public static function progress($learner_id, $course_id) {
        if (!$learner_id || !self::is_enrolled($learner_id, $course_id)) {
            return null;
        }
        $lessons = self::published_lessons($course_id);
        $rows = self::progress_rows($learner_id, $course_id);
        $completed = 0;
        $items = array();
        foreach ($lessons as $lesson) {
            $done = isset($rows[$lesson->ID]) && $rows[$lesson->ID]->status === 'completed';
            if ($done) {
                $completed++;
            }
            $items[] = array(
                'id' => (int) $lesson->ID,
                'title' => get_the_title($lesson->ID),
                'completed' => $done,
            );
        }
        $total = count($lessons);
        return array(
            'course_id' => absint($course_id), 'total' => $total, 'completed' => $completed,
            'percent' => $total ? (int) round($completed * 100 / $total) : 0,
            'lessons' => $items,
        );
    }

    public static function my_courses($learner_id) {
        if (!$learner_id || $learner_id !== MD_Learning_Auth::current_learner_id()) {
            return array();
        }
        global $wpdb;
        $table = self::table('enrollments');
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT course_id FROM {$table} WHERE learner_id = %d
             AND status = 'active' ORDER BY id DESC LIMIT 100", $learner_id
        ));
        $result = array();
        foreach ((array) $ids as $course_id) {
            if (!self::is_published_course($course_id)) {
                continue;
            }
            $data = self::progress($learner_id, $course_id);
            if ($data) {
                $data['title'] = get_the_title($course_id);
                $result[] = $data;
            }
        }
        return $result;
    }

    /** Explicit action, never infer completion from opening a lesson page. */
    public static function set_progress($learner_id, $course_id, $lesson_id, $completed) {
        $learner_id = absint($learner_id);
        $course_id = absint($course_id);
        $lesson_id = absint($lesson_id);
        if (!$learner_id || $learner_id !== MD_Learning_Auth::current_learner_id()) {
            return new WP_Error('forbidden', 'این حساب اجازه ثبت پیشرفت را ندارد.');
        }
        if (!self::is_published_lesson($lesson_id, $course_id)
            || !self::is_enrolled($learner_id, $course_id)) {
            return new WP_Error('forbidden', 'برای این درس عضویت فعال در دوره ندارید.');
        }
        global $wpdb;
        $table = self::table('lesson_progress');
        $now = gmdate('Y-m-d H:i:s');
        $status = $completed ? 'completed' : 'incomplete';
        $sql = "INSERT INTO {$table}
            (learner_id, course_id, lesson_id, status, completed_at, updated_at)
            VALUES (%d,%d,%d,%s,%s,%s)
            ON DUPLICATE KEY UPDATE
              status = VALUES(status),
              completed_at = VALUES(completed_at),
              updated_at = VALUES(updated_at)";
        // Keep completed_at nullable for incomplete records.
        $completed_at = $completed ? $now : null;
        $updated = $wpdb->query($wpdb->prepare($sql,
            $learner_id, $course_id, $lesson_id, $status, $completed_at, $now));
        return $updated === false ? new WP_Error('failed', 'وضعیت این درس ذخیره نشد.') : true;
    }

    public static function join() {
        $course_id = isset($_POST['md_course_id']) ? absint($_POST['md_course_id']) : 0;
        check_admin_referer('md_learning_course_join_' . $course_id);
        $learner_id = MD_Learning_Auth::current_learner_id();
        if (!$learner_id) {
            wp_die('برای ثبت‌نام در دوره ابتدا وارد شوید.', '', array('response' => 403));
        }
        if (empty($_POST['md_course_consent'])) {
            wp_die('برای عضویت، ذخیره پیشرفت را تأیید کنید.', '', array('response' => 400));
        }
        $result = self::enroll($learner_id, $course_id);
        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()), '', array('response' => 403));
        }
        wp_safe_redirect(get_permalink($course_id) . '#md-learning-course', 303);
        exit;
    }

    public static function change_progress() {
        $learner_id = MD_Learning_Auth::current_learner_id();
        if (!$learner_id) {
            wp_die('برای ثبت پیشرفت ابتدا وارد شوید.', '', array('response' => 403));
        }
        $course_id = isset($_POST['md_course_id']) ? absint($_POST['md_course_id']) : 0;
        $lesson_id = isset($_POST['md_lesson_id']) ? absint($_POST['md_lesson_id']) : 0;
        check_admin_referer('md_learning_lesson_progress_' . $course_id . '_' . $lesson_id);
        $intent = isset($_POST['md_intent']) && $_POST['md_intent'] === 'complete';
        $result = self::set_progress($learner_id, $course_id, $lesson_id, $intent);
        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()), '', array('response' => 403));
        }
        wp_safe_redirect(get_permalink($lesson_id) . '#md-learning-lesson-progress', 303);
        exit;
    }

    private static function course_login_url($course_id, $register = false) {
        return add_query_arg('md_course', absint($course_id),
            MD_Learning_Schema::url($register ? 'register' : 'login'));
    }

    public static function course_ui($course_id) {
        $course_id = absint($course_id);
        if (!self::is_published_course($course_id)) {
            return '';
        }
        $learner = MD_Learning_Auth::current_learner_id();
        $open = self::open_enrollment($course_id);
        $joined = $learner && self::is_enrolled($learner, $course_id);
        ob_start();
        ?>
        <section class="md-learning-course-box" id="md-learning-course" dir="rtl">
            <h2>مسیر یادگیری این دوره</h2>
            <?php if ($joined) :
                $progress = self::progress($learner, $course_id); ?>
                <p>این دوره در داشبورد شما ثبت شده است.</p>
                <div class="md-learning-course-meter" role="progressbar" aria-valuemin="0"
                    aria-valuemax="100" aria-valuenow="<?php echo esc_attr($progress['percent']); ?>"
                    aria-label="پیشرفت دوره"><span style="width:<?php echo esc_attr($progress['percent']); ?>%"></span></div>
                <p><?php echo esc_html(number_format_i18n($progress['completed']) . ' از ' .
                    number_format_i18n($progress['total']) . ' درس تکمیل‌شده · ' .
                    number_format_i18n($progress['percent']) . '٪'); ?></p>
                <ol class="md-learning-course-lessons">
                    <?php foreach ($progress['lessons'] as $lesson) : ?>
                        <li>
                            <span class="dashicons <?php echo $lesson['completed'] ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>" aria-hidden="true"></span>
                            <a href="<?php echo esc_url(get_permalink($lesson['id'])); ?>"><?php echo esc_html($lesson['title']); ?></a>
                            <span><?php echo $lesson['completed'] ? 'انجام‌شده' : 'در انتظار'; ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <a href="<?php echo esc_url(add_query_arg('md_tab', 'courses', MD_Learning_Schema::url('dashboard'))); ?>">همه دوره‌های من ←</a>
            <?php elseif ($open && !$learner) : ?>
                <p>ثبت‌نام این دوره باز است. برای ثبت پیشرفت، یک‌بار با کد مرکزی وارد شوید.</p>
                <p><a class="md-learning-button" href="<?php echo esc_url(self::course_login_url($course_id)); ?>">ورود به پروفایل</a>
                    <a class="md-learning-course-secondary" href="<?php echo esc_url(self::course_login_url($course_id, true)); ?>">ساخت پروفایل</a></p>
            <?php elseif ($open) : ?>
                <p>با تأیید عضویت، این دوره به داشبورد شما اضافه می‌شود. تکمیل درس‌ها را خودتان ثبت می‌کنید.</p>
                <?php self::join_form($course_id); ?>
            <?php else : ?>
                <p>عضویت آزاد این دوره فعال نیست. برای ثبت‌نام از مسیر معرفی دوره استفاده کنید؛ مدیر می‌تواند عضویت شما را تأیید کند.</p>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function join_form($course_id) {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="md-learning-course-join-form">
            <input type="hidden" name="action" value="md_learning_course_join">
            <input type="hidden" name="md_course_id" value="<?php echo esc_attr($course_id); ?>">
            <?php wp_nonce_field('md_learning_course_join_' . $course_id); ?>
            <label><input type="checkbox" name="md_course_consent" value="1" required>
                با ذخیره عضویت و پیشرفت درس‌ها در حساب یادگیری خود موافقم.</label>
            <button class="md-learning-button" type="submit">عضویت در دوره</button>
        </form>
        <?php
    }

    public static function lesson_ui($lesson_id) {
        $lesson_id = absint($lesson_id);
        $course_id = absint(get_post_meta($lesson_id, '_md_course_id', true));
        if (!self::is_published_lesson($lesson_id, $course_id)) {
            return '';
        }
        $learner = MD_Learning_Auth::current_learner_id();
        $joined = $learner && self::is_enrolled($learner, $course_id);
        ob_start();
        ?>
        <section class="md-learning-lesson-box" id="md-learning-lesson-progress" dir="rtl">
            <h2>پیشرفت درس من</h2>
            <?php if ($joined) :
                $progress = self::progress($learner, $course_id);
                $completed = false;
                foreach ($progress['lessons'] as $row) {
                    if ($row['id'] === $lesson_id) {
                        $completed = $row['completed'];
                        break;
                    }
                }
                ?>
                <p><?php echo esc_html(get_the_title($course_id)); ?> ·
                    <?php echo esc_html(number_format_i18n($progress['completed']) . ' از ' .
                        number_format_i18n($progress['total']) . ' درس تکمیل‌شده'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="md_learning_lesson_progress">
                    <input type="hidden" name="md_course_id" value="<?php echo esc_attr($course_id); ?>">
                    <input type="hidden" name="md_lesson_id" value="<?php echo esc_attr($lesson_id); ?>">
                    <?php wp_nonce_field('md_learning_lesson_progress_' . $course_id . '_' . $lesson_id); ?>
                    <button type="submit" class="md-learning-button" name="md_intent"
                        value="<?php echo $completed ? 'incomplete' : 'complete'; ?>"><?php echo $completed ? 'علامت‌گذاری به‌عنوان انجام‌نشده' : 'این درس را انجام دادم'; ?></button>
                </form>
                <a href="<?php echo esc_url(get_permalink($course_id)); ?>">مشاهده مسیر کامل دوره ←</a>
            <?php else : ?>
                <p>برای ثبت انجام‌شدن این درس، ابتدا عضو دوره شوید.</p>
                <a class="md-learning-course-secondary" href="<?php echo esc_url(get_permalink($course_id)); ?>">رفتن به معرفی دوره ←</a>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    public static function add_course_box() {
        if (!current_user_can('manage_options')) {
            return;
        }
        add_meta_box('md_learning_enrollment', 'عضویت و پیشرفت یادگیرندگان',
            array(__CLASS__, 'settings_box'), 'md_course', 'side', 'default');
    }

    public static function settings_box($post) {
        wp_nonce_field('md_learning_course_settings_' . $post->ID, 'md_learning_course_nonce');
        ?>
        <p><label><input type="checkbox" name="md_learning_open_enrollment" value="1"
            <?php checked(get_post_meta($post->ID, self::OPEN_META, true), '1'); ?>>
            اجازه عضویت آزاد با پروفایل مرکزی</label></p>
        <p class="description">پیش‌فرض خاموش است. فقط دوره‌هایی را فعال کنید که نیاز به تأیید دستی یا پرداخت ندارند. این تنظیم هیچ پرداختی را بررسی نمی‌کند.</p>
        <?php if (get_post_status($post->ID) === 'publish') : ?>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=md-learning-course-members&md_course=' . $post->ID)); ?>">مدیریت عضویت‌های این دوره ←</a></p>
        <?php endif; ?>
        <?php
    }

    public static function save_course_settings($post_id, $post, $update) {
        if (!current_user_can('manage_options')
            || !isset($_POST['md_learning_course_nonce'])
            || !is_string($_POST['md_learning_course_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['md_learning_course_nonce'])),
                'md_learning_course_settings_' . $post_id)
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || wp_is_post_revision($post_id)) {
            return;
        }
        update_post_meta($post_id, self::OPEN_META,
            !empty($_POST['md_learning_open_enrollment']) ? '1' : '0');
    }

    public static function assets() {
        if (!is_singular(array('md_course', 'md_lesson'))) {
            return;
        }
        wp_enqueue_style('md-learning-courses',
            MD_LEARNING_CORE_URL . 'assets/courses.css', array(), MD_LEARNING_CORE_VERSION);
    }

    public static function private_headers() {
        if (!is_singular(array('md_course', 'md_lesson'))) {
            return;
        }
        nocache_headers();
        header('Cache-Control: private, no-store, max-age=0, must-revalidate', true);
        header('Referrer-Policy: no-referrer', true);
    }
}
