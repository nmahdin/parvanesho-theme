<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Standalone administrator UI. Never sends codes or links through email. */
final class MD_Learning_Admin {
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_post_md_learning_admin_create', array(__CLASS__, 'create'));
        add_action('admin_post_md_learning_admin_rotate', array(__CLASS__, 'rotate'));
        add_action('admin_post_md_learning_admin_revoke', array(__CLASS__, 'revoke'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
    }

    public static function menu() {
        add_menu_page(
            'یادگیرندگان مروارید درون',
            'یادگیرندگان',
            'manage_options',
            'md-learning-learners',
            array(__CLASS__, 'render'),
            'dashicons-id-alt',
            27
        );
    }

    public static function assets($hook) {
        if ($hook !== 'toplevel_page_md-learning-learners') {
            return;
        }
        wp_enqueue_style('md-learning-core-admin',
            MD_LEARNING_CORE_URL . 'assets/learning.css', array(), MD_LEARNING_CORE_VERSION);
    }

    private static function url() {
        return admin_url('admin.php?page=md-learning-learners');
    }

    private static function require_admin() {
        if (!current_user_can('manage_options')) {
            wp_die('شما اجازه مدیریت یادگیرندگان را ندارید.', '', array('response' => 403));
        }
    }

    private static function receipt($credentials) {
        set_transient('md_learning_admin_receipt_' . get_current_user_id(),
            $credentials, 5 * MINUTE_IN_SECONDS);
    }

    public static function create() {
        self::require_admin();
        check_admin_referer('md_learning_admin_create');
        $name = isset($_POST['md_name']) && is_string($_POST['md_name'])
            ? sanitize_text_field(wp_unslash($_POST['md_name'])) : '';
        $credentials = MD_Learning_Auth::create_learner($name);
        if (is_wp_error($credentials)) {
            wp_die(esc_html($credentials->get_error_message()), '', array('response' => 400));
        }
        self::receipt($credentials);
        wp_safe_redirect(self::url());
        exit;
    }

    private static function validated_learner($action) {
        self::require_admin();
        $id = isset($_POST['md_learner_id']) ? absint($_POST['md_learner_id']) : 0;
        if (!$id || !MD_Learning_Auth::get_learner($id)) {
            wp_die('پروفایل پیدا نشد.', '', array('response' => 404));
        }
        check_admin_referer('md_learning_admin_' . $action . '_' . $id);
        return $id;
    }

    public static function rotate() {
        $id = self::validated_learner('rotate');
        $credentials = MD_Learning_Auth::rotate_credentials($id);
        if (is_wp_error($credentials)) {
            wp_die(esc_html($credentials->get_error_message()), '', array('response' => 500));
        }
        self::receipt($credentials);
        wp_safe_redirect(self::url());
        exit;
    }

    public static function revoke() {
        $id = self::validated_learner('revoke');
        if (!MD_Learning_Auth::revoke_access($id)) {
            wp_die('غیرفعال‌سازی حساب ممکن نشد.', '', array('response' => 500));
        }
        wp_safe_redirect(add_query_arg('md_notice', 'revoked', self::url()));
        exit;
    }

    public static function render() {
        self::require_admin();
        global $wpdb;
        $table = MD_Learning_Schema::table('learners');
        $page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $per_page = 25;
        $offset = ($page - 1) * $per_page;
        $search = isset($_GET['s']) && is_string($_GET['s'])
            ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $where = '';
        $params = array();
        if ($search !== '') {
            $where = ' WHERE display_name LIKE %s';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }
        $count_sql = "SELECT COUNT(*) FROM {$table}{$where}";
        $count = (int) $wpdb->get_var($params ? $wpdb->prepare($count_sql, $params) : $count_sql);
        $list_sql = "SELECT id, display_name, status, created_at, last_login_at FROM {$table}{$where} ORDER BY id DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        $learners = $wpdb->get_results($wpdb->prepare($list_sql, $params));
        $receipt_key = 'md_learning_admin_receipt_' . get_current_user_id();
        $receipt = get_transient($receipt_key);
        if ($receipt) {
            delete_transient($receipt_key);
        }
        ?>
        <div class="wrap md-learning-admin" dir="rtl">
            <h1>یادگیرندگان · هویت مرکزی</h1>
            <p>مدیریت مستقل از چالش‌ها و دوره‌ها؛ بدون ایمیل و بدون حساب کاربری وردپرس.</p>
            <p class="md-learning-admin-page-links">
                <a class="button" href="<?php echo esc_url(MD_Learning_Schema::url('register')); ?>" target="_blank" rel="noopener">صفحه ثبت‌نام</a>
                <a class="button" href="<?php echo esc_url(MD_Learning_Schema::url('login')); ?>" target="_blank" rel="noopener">صفحه ورود</a>
                <a class="button" href="<?php echo esc_url(MD_Learning_Schema::url('dashboard')); ?>" target="_blank" rel="noopener">داشبورد یادگیرنده</a>
            </p>

            <?php if ($receipt && is_array($receipt)) : ?>
                <div class="notice notice-success md-learning-admin-receipt">
                    <h2>کد و لینک اختصاصی صادر شد</h2>
                    <p><strong>این مقادیر فقط یک‌بار نمایش داده می‌شوند.</strong> آن‌ها را به‌صورت خصوصی در اختیار یادگیرنده قرار دهید.</p>
                    <p><label>شناسه: <strong><?php echo esc_html('L-' . absint($receipt['learner_id'])); ?></strong></label></p>
                    <p><label>کد ۸ رقمی:
                        <input dir="ltr" type="text" readonly onclick="this.select()" value="<?php echo esc_attr($receipt['code']); ?>"></label></p>
                    <p><label>لینک خصوصی:
                        <input class="large-text" dir="ltr" type="text" readonly onclick="this.select()" value="<?php echo esc_attr($receipt['link']); ?>"></label></p>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['md_notice']) && 'revoked' === sanitize_key(wp_unslash($_GET['md_notice']))) : ?>
                <div class="notice notice-success"><p>کد، لینک‌ها و نشست‌های این یادگیرنده غیرفعال شدند.</p></div>
            <?php endif; ?>

            <section class="md-learning-admin-create">
                <h2>افزودن یادگیرنده</h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="md_learning_admin_create">
                    <?php wp_nonce_field('md_learning_admin_create'); ?>
                    <label for="md-learning-admin-name">نام و نام خانوادگی</label>
                    <input id="md-learning-admin-name" type="text" maxlength="120" name="md_name" required>
                    <button class="button button-primary" type="submit">صدور کد و لینک</button>
                </form>
            </section>

            <form method="get" class="md-learning-admin-search">
                <input type="hidden" name="page" value="md-learning-learners">
                <label for="md-learning-search">جست‌وجوی نام یادگیرنده</label>
                <input id="md-learning-search" type="search" name="s" value="<?php echo esc_attr($search); ?>">
                <button class="button" type="submit">جست‌وجو</button>
            </form>
            <p>تعداد پروفایل‌ها: <strong><?php echo esc_html(number_format_i18n($count)); ?></strong></p>
            <div class="md-learning-admin-table-wrap">
                <table class="widefat striped md-learning-admin-table">
                    <thead><tr><th>شناسه</th><th>نام یادگیرنده</th><th>وضعیت</th><th>ایجاد</th><th>آخرین ورود</th><th>عملیات</th></tr></thead>
                    <tbody>
                    <?php if (!$learners) : ?>
                        <tr><td colspan="6">یادگیرنده‌ای پیدا نشد.</td></tr>
                    <?php endif; ?>
                    <?php foreach ((array) $learners as $learner) : ?>
                        <tr>
                            <td><?php echo esc_html('L-' . $learner->id); ?></td>
                            <td><strong><?php echo esc_html($learner->display_name); ?></strong></td>
                            <td><?php echo $learner->status === 'active' ? 'فعال' : 'غیرفعال'; ?></td>
                            <td><?php echo esc_html($learner->created_at); ?> UTC</td>
                            <td><?php echo $learner->last_login_at ? esc_html($learner->last_login_at) . ' UTC' : '—'; ?></td>
                            <td>
                                <form class="md-learning-admin-inline" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                    onsubmit="return confirm('کد و لینک قبلی باطل و اطلاعات ورود جدید صادر شود؟');">
                                    <input type="hidden" name="action" value="md_learning_admin_rotate">
                                    <input type="hidden" name="md_learner_id" value="<?php echo esc_attr($learner->id); ?>">
                                    <?php wp_nonce_field('md_learning_admin_rotate_' . $learner->id); ?>
                                    <button class="button button-secondary" type="submit">صدور مجدد کد و لینک</button>
                                </form>
                                <?php if ($learner->status === 'active') : ?>
                                    <form class="md-learning-admin-inline" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                        onsubmit="return confirm('همه دسترسی‌های این یادگیرنده غیرفعال شوند؟');">
                                        <input type="hidden" name="action" value="md_learning_admin_revoke">
                                        <input type="hidden" name="md_learner_id" value="<?php echo esc_attr($learner->id); ?>">
                                        <?php wp_nonce_field('md_learning_admin_revoke_' . $learner->id); ?>
                                        <button class="button" type="submit">غیرفعال‌سازی</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php
            $pages = max(1, (int) ceil($count / $per_page));
            if ($pages > 1) :
                echo '<div class="tablenav"><div class="tablenav-pages">';
                echo wp_kses_post(paginate_links(array(
                    'base' => add_query_arg(array('page' => 'md-learning-learners', 's' => $search, 'paged' => '%#%'), admin_url('admin.php')),
                    'format' => '',
                    'total' => $pages,
                    'current' => $page,
                )));
                echo '</div></div>';
            endif;
            ?>
            <p class="description">کدهای ورود اصلی فقط به‌شکل هش ذخیره می‌شوند. برای تحویل دوباره کد، «صدور مجدد» را بزنید؛ کد قبلی و نشست‌های فعال باطل می‌شوند.</p>
        </div>
        <?php
    }
}
