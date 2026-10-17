<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Administrative assignment only; self-enrollment policy lives on each course. */
final class MD_Learning_Course_Admin {
    const MENU = 'md-learning-course-members';
    const PER_PAGE = 25;

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'), 21);
        add_action('admin_post_md_learning_course_admin_membership', array(__CLASS__, 'change_membership'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
    }

    public static function menu() {
        add_submenu_page('md-learning-learners', 'عضویت یادگیرندگان در دوره‌ها',
            'عضویت دوره‌ها', 'manage_options', self::MENU, array(__CLASS__, 'render'));
    }

    private static function require_admin() {
        if (!current_user_can('manage_options')) {
            wp_die('فقط مدیرکل می‌تواند عضویت دوره‌ها را تغییر دهد.', '', array('response' => 403));
        }
    }

    private static function base_url($args = array()) {
        return add_query_arg(array_merge(array('page' => self::MENU), $args), admin_url('admin.php'));
    }

    public static function change_membership() {
        self::require_admin();
        $learner_id = isset($_POST['md_learner']) ? absint($_POST['md_learner']) : 0;
        $course_id = isset($_POST['md_course']) ? absint($_POST['md_course']) : 0;
        $status = isset($_POST['md_status']) && is_string($_POST['md_status'])
            ? sanitize_key(wp_unslash($_POST['md_status'])) : '';
        check_admin_referer('md_learning_course_admin_' . $learner_id . '_' . $course_id);
        if (!$learner_id || !$course_id || !in_array($status, array('active', 'revoked'), true)) {
            wp_die('شناسه یا وضعیت عضویت معتبر نیست.', '', array('response' => 400));
        }
        $result = MD_Learning_Courses::set_membership_status($learner_id, $course_id, $status);
        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()), '', array('response' => 400));
        }
        wp_safe_redirect(self::base_url(array(
            'md_course' => $course_id, 'md_notice' => $status === 'active' ? 'active' : 'revoked',
        )), 303);
        exit;
    }

    public static function assets($hook) {
        if ($hook !== 'learners_page_' . self::MENU && $hook !== 'md-learning-learners_page_' . self::MENU) {
            return;
        }
        wp_enqueue_style('md-learning-core-admin',
            MD_LEARNING_CORE_URL . 'assets/learning.css', array(), MD_LEARNING_CORE_VERSION);
    }

    public static function render() {
        self::require_admin();
        nocache_headers();
        global $wpdb;
        $enrollments = MD_Learning_Courses::table('enrollments');
        $learners = MD_Learning_Schema::table('learners');
        $course_id = isset($_GET['md_course']) ? absint($_GET['md_course']) : 0;
        $page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $where = $course_id ? ' WHERE e.course_id = %d' : '';
        $count_sql = "SELECT COUNT(*) FROM {$enrollments} e{$where}";
        $count = (int) $wpdb->get_var($course_id ? $wpdb->prepare($count_sql, $course_id) : $count_sql);
        $offset = ($page - 1) * self::PER_PAGE;
        $sql = "SELECT e.id, e.learner_id, e.course_id, e.status, e.enrolled_at,
                l.display_name, l.status AS learner_status
                FROM {$enrollments} e
                LEFT JOIN {$learners} l ON l.id = e.learner_id
                {$where} ORDER BY e.id DESC LIMIT %d OFFSET %d";
        $args = $course_id ? array($course_id, self::PER_PAGE, $offset) : array(self::PER_PAGE, $offset);
        $rows = $wpdb->get_results($wpdb->prepare($sql, $args));
        $notice = isset($_GET['md_notice']) && is_string($_GET['md_notice'])
            ? sanitize_key(wp_unslash($_GET['md_notice'])) : '';
        ?>
        <div class="wrap md-learning-admin" dir="rtl">
            <h1>عضویت یادگیرندگان در دوره‌ها</h1>
            <p>برای دوره‌های نیازمند تأیید یا پرداخت، عضویت را فقط پس از انجام فرایند بیرون از این سامانه اضافه کنید. این بخش هیچ تراکنشی را بررسی نمی‌کند.</p>
            <?php if (in_array($notice, array('active', 'revoked'), true)) : ?>
                <div class="notice notice-success"><p><?php echo $notice === 'active' ? 'عضویت فعال شد.' : 'عضویت غیرفعال شد؛ پیشرفت قبلی حفظ شده است.'; ?></p></div>
            <?php endif; ?>
            <div class="md-learning-admin-create">
                <h2>ثبت یا تغییر عضویت</h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="md_learning_course_admin_membership">
                    <label>شناسه یادگیرنده (شماره بعد از L-)
                        <input type="number" name="md_learner" id="md-learning-course-learner" min="1" required>
                    </label>
                    <label>شناسه دوره منتشرشده
                        <input type="number" name="md_course" id="md-learning-course-id" min="1"
                            value="<?php echo $course_id ? esc_attr($course_id) : ''; ?>" required>
                    </label>
                    <label>عملیات
                        <select name="md_status">
                            <option value="active">فعال‌کردن / افزودن</option>
                            <option value="revoked">غیرفعال‌کردن</option>
                        </select>
                    </label>
                    <button class="button button-primary" type="submit">ذخیره عضویت</button>
                    <p class="description">کد امنیتی هنگام ارسال فرم برای همین شناسه‌های انتخابی کنترل می‌شود.</p>
                </form>
            </div>
            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="md-learning-admin-search">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::MENU); ?>">
                <label>فیلتر دوره (شناسه) <input type="number" name="md_course" min="1" value="<?php echo $course_id ? esc_attr($course_id) : ''; ?>"></label>
                <button class="button" type="submit">نمایش</button>
                <a href="<?php echo esc_url(self::base_url()); ?>">همه عضویت‌ها</a>
            </form>
            <p>تعداد عضویت‌های این فهرست: <strong><?php echo esc_html(number_format_i18n($count)); ?></strong></p>
            <div class="md-learning-admin-table-wrap">
                <table class="widefat striped md-learning-admin-table">
                    <thead><tr><th>یادگیرنده</th><th>دوره</th><th>وضعیت</th><th>پیشرفت درس‌ها</th><th>تاریخ عضویت (UTC)</th><th>عملیات</th></tr></thead>
                    <tbody>
                    <?php if (!$rows) : ?><tr><td colspan="6">عضویتی یافت نشد.</td></tr><?php endif; ?>
                    <?php foreach ((array) $rows as $row) :
                        $progress = $row->status === 'active'
                            ? MD_Learning_Courses::progress($row->learner_id, $row->course_id) : null;
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($row->display_name ?: 'یادگیرنده حذف‌شده'); ?></strong>
                                <small><?php echo esc_html('L-' . $row->learner_id); ?></small></td>
                            <td><?php echo esc_html(get_the_title($row->course_id)); ?>
                                <small><?php echo esc_html('#' . $row->course_id); ?></small></td>
                            <td><?php echo $row->status === 'active' ? 'فعال' : 'غیرفعال'; ?></td>
                            <td><?php echo $progress ? esc_html($progress['completed'] . ' / ' . $progress['total'] . ' · ' . $progress['percent'] . '٪') : '—'; ?></td>
                            <td><?php echo esc_html($row->enrolled_at); ?></td>
                            <td>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <input type="hidden" name="action" value="md_learning_course_admin_membership">
                                    <input type="hidden" name="md_learner" value="<?php echo esc_attr($row->learner_id); ?>">
                                    <input type="hidden" name="md_course" value="<?php echo esc_attr($row->course_id); ?>">
                                    <input type="hidden" name="md_status" value="<?php echo $row->status === 'active' ? 'revoked' : 'active'; ?>">
                                    <?php wp_nonce_field('md_learning_course_admin_' . $row->learner_id . '_' . $row->course_id); ?>
                                    <button class="button button-small" type="submit"><?php echo $row->status === 'active' ? 'غیرفعال‌کردن' : 'فعال‌سازی دوباره'; ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($count > self::PER_PAGE) :
                echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post(paginate_links(array(
                    'base' => self::base_url(array('md_course' => $course_id ?: null, 'paged' => '%#%')),
                    'current' => $page,
                    'total' => (int) ceil($count / self::PER_PAGE),
                ))) . '</div></div>';
            endif; ?>
        </div>
        <?php
    }
}
