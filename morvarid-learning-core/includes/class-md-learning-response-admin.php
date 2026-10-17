<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Phase E — private, read-only educational answer reporting.
 *
 * Access is deliberately separate from edit_posts or challenge mentoring.
 * Only an explicit 'md_view_learning_responses' capability grants access.
 * No public endpoints, inline answer editing, mass export or destructive actions.
 */
final class MD_Learning_Response_Admin {
    const CAPABILITY = 'md_view_learning_responses';
    const MENU_SLUG = 'md-learning-answers';
    const PAGE_SIZE = 25;
    private static $hook = '';

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'), 20);
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
        add_action('admin_init', array(__CLASS__, 'private_headers'));
    }

    public static function menu() {
        if (!current_user_can(self::CAPABILITY)) {
            return;
        }
        if (current_user_can('manage_options')) {
            self::$hook = add_submenu_page(
                'md-learning-learners',
                'پاسخ‌های آموزشی یادگیرندگان',
                'پاسخ‌های آموزشی',
                self::CAPABILITY,
                self::MENU_SLUG,
                array(__CLASS__, 'render')
            );
        } else {
            // An explicitly authorized mentor has the viewing capability,
            // but not access to the administrators-only Learners parent menu.
            self::$hook = add_menu_page(
                'پاسخ‌های آموزشی یادگیرندگان',
                'پاسخ‌های آموزشی',
                self::CAPABILITY,
                self::MENU_SLUG,
                array(__CLASS__, 'render'),
                'dashicons-visibility',
                28
            );
        }
    }

    public static function assets($hook) {
        if (!$hook || $hook !== self::$hook || !current_user_can(self::CAPABILITY)) {
            return;
        }
        wp_enqueue_style('md-learning-response-admin',
            MD_LEARNING_CORE_URL . 'assets/answer-admin.css', array(),
            MD_LEARNING_CORE_VERSION);
    }

    public static function private_headers() {
        if (!isset($_GET['page']) || !is_string($_GET['page'])
            || sanitize_key(wp_unslash($_GET['page'])) !== self::MENU_SLUG) {
            return;
        }
        if (!current_user_can(self::CAPABILITY)) {
            return;
        }
        nocache_headers();
        header('Cache-Control: private, no-store, max-age=0, must-revalidate', true);
        header('Referrer-Policy: no-referrer', true);
        header('X-Robots-Tag: noindex, nofollow, noarchive', true);
    }

    public static function allowed() {
        return current_user_can(self::CAPABILITY);
    }

    private static function require_permission() {
        if (!self::allowed()) {
            wp_die('اجازه مشاهده پاسخ‌های خصوصی یادگیرندگان را ندارید.', '', array('response' => 403));
        }
    }

    public static function page_url($filters = array()) {
        return add_query_arg(array_merge(array('page' => self::MENU_SLUG), $filters), admin_url('admin.php'));
    }

    public static function clean_date($value) {
        $date = is_string($value) ? trim($value) : '';
        if (!preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $date, $m)
            || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return '';
        }
        return $date;
    }

    public static function filters($input) {
        $read = function ($key) use ($input) {
            return isset($input[$key]) && is_string($input[$key])
                ? wp_unslash($input[$key]) : '';
        };
        $status = sanitize_key($read('md_status'));
        return array(
            'md_status' => in_array($status, array('draft', 'final'), true) ? $status : '',
            'md_learner' => absint($read('md_learner')),
            'md_content' => absint($read('md_content')),
            'md_query' => substr(sanitize_text_field($read('md_query')), 0, 100),
            'md_from' => self::clean_date($read('md_from')),
            'md_to' => self::clean_date($read('md_to')),
        );
    }

    /**
     * All comparisons are parameterized, apart from trusted table names and
     * fixed SQL fragments. Search only metadata, NEVER private answer text.
     */
    public static function where($filters) {
        global $wpdb;
        // Never disclose even response metadata from a block whose author
        // disabled administrator viewing in the Gutenberg editor.
        $clauses = array('r.manager_visible = 1');
        $values = array();
        if (!empty($filters['md_status'])) {
            $clauses[] = 'r.status = %s';
            $values[] = $filters['md_status'];
        }
        if (!empty($filters['md_learner'])) {
            $clauses[] = 'r.learner_id = %d';
            $values[] = (int) $filters['md_learner'];
        }
        if (!empty($filters['md_content'])) {
            $clauses[] = 'r.content_id = %d';
            $values[] = (int) $filters['md_content'];
        }
        if (!empty($filters['md_from'])) {
            $clauses[] = 'r.updated_at >= %s';
            $values[] = $filters['md_from'] . ' 00:00:00';
        }
        if (!empty($filters['md_to'])) {
            $clauses[] = 'r.updated_at < %s';
            $date = new DateTimeImmutable($filters['md_to'], new DateTimeZone('UTC'));
            $values[] = $date->modify('+1 day')->format('Y-m-d 00:00:00');
        }
        if (!empty($filters['md_query'])) {
            $clauses[] = '(l.display_name LIKE %s OR p.post_title LIKE %s OR r.question_text LIKE %s)';
            $term = '%' . $wpdb->esc_like($filters['md_query']) . '%';
            $values[] = $term;
            $values[] = $term;
            $values[] = $term;
        }
        return array('sql' => $clauses ? ' WHERE ' . implode(' AND ', $clauses) : '', 'values' => $values);
    }

    private static function from_sql() {
        global $wpdb;
        $responses = MD_Learning_Schema::table('responses');
        $learners = MD_Learning_Schema::table('learners');
        return " FROM {$responses} r
            LEFT JOIN {$learners} l ON l.id = r.learner_id
            LEFT JOIN {$wpdb->posts} p ON p.ID = r.content_id";
    }

    private static function sql($template, $values) {
        global $wpdb;
        return $values ? $wpdb->prepare($template, $values) : $template;
    }

    public static function report($filters, $page = 1) {
        self::require_permission();
        global $wpdb;
        $where = self::where($filters);
        $from = self::from_sql();
        $summary = $wpdb->get_row(self::sql(
            "SELECT COUNT(r.id) AS total,
              SUM(CASE WHEN r.status = 'draft' THEN 1 ELSE 0 END) AS drafts,
              SUM(CASE WHEN r.status = 'final' THEN 1 ELSE 0 END) AS finals,
              COUNT(DISTINCT r.learner_id) AS learners,
              COUNT(DISTINCT r.content_id) AS content_items
              {$from}{$where['sql']}", $where['values']
        ), ARRAY_A);
        $total = (int) ($summary && isset($summary['total']) ? $summary['total'] : 0);
        $page = max(1, min(100000, absint($page)));
        $offset = ($page - 1) * self::PAGE_SIZE;
        $values = array_merge($where['values'], array(self::PAGE_SIZE, $offset));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT r.id, r.learner_id, r.content_id, r.block_id, r.question_text,
              r.status, r.updated_at, r.submitted_at,
              l.display_name, p.post_title AS content_title, p.post_status AS content_status
              {$from}{$where['sql']}
              ORDER BY r.updated_at DESC, r.id DESC LIMIT %d OFFSET %d",
            $values
        ));
        return array(
            'summary' => array(
                'total' => $total,
                'drafts' => (int) ($summary && isset($summary['drafts']) ? $summary['drafts'] : 0),
                'finals' => (int) ($summary && isset($summary['finals']) ? $summary['finals'] : 0),
                'learners' => (int) ($summary && isset($summary['learners']) ? $summary['learners'] : 0),
                'content_items' => (int) ($summary && isset($summary['content_items']) ? $summary['content_items'] : 0),
            ),
            'rows' => (array) $rows,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / self::PAGE_SIZE)),
        );
    }

    /** Lookup is always capability-checked and scoped to exactly one answer. */
    public static function detail($answer_id) {
        self::require_permission();
        $answer_id = absint($answer_id);
        if (!$answer_id) {
            return null;
        }
        global $wpdb;
        $from = self::from_sql();
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT r.id, r.learner_id, r.content_id, r.block_id, r.question_id,
                r.question_text, r.answer_text, r.status, r.created_at,
                r.updated_at, r.submitted_at,
                l.display_name, l.status AS learner_status,
                p.post_title AS content_title, p.post_status AS content_status
                {$from} WHERE r.id = %d AND r.manager_visible = 1 LIMIT 1", $answer_id
        ));
        return $row ?: null;
    }

    /** Audit *metadata only* when an administrator opens an answer. */
    private static function record_view($row) {
        if (!self::allowed()) {
            return false;
        }
        global $wpdb;
        $written = $wpdb->insert(MD_Learning_Schema::table('audit_events'), array(
            'actor_user_id' => get_current_user_id(),
            'learner_id' => absint($row->learner_id),
            'response_id' => absint($row->id),
            'event_type' => 'answer_viewed',
            'created_at' => gmdate('Y-m-d H:i:s'),
        ), array('%d', '%d', '%d', '%s', '%s'));
        return $written === 1;
    }

    private static function visible_time($date) {
        if (!$date) {
            return '—';
        }
        $timestamp = strtotime($date . ' UTC');
        return $timestamp ? wp_date('Y/m/d H:i', $timestamp) : '—';
    }

    private static function filter_params($filters) {
        $out = array();
        foreach ($filters as $key => $value) {
            if ($value !== '' && $value !== 0) {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    private static function status_label($status) {
        return $status === 'final' ? 'ثبت نهایی' : 'پیش‌نویس';
    }

    private static function search_form($filters) {
        ?>
        <form class="md-learning-report-filters" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
            <input type="hidden" name="page" value="<?php echo esc_attr(self::MENU_SLUG); ?>">
            <label>جست‌وجوی نام، عنوان آموزش یا متن پرسش
                <input name="md_query" type="search" maxlength="100"
                    value="<?php echo esc_attr($filters['md_query']); ?>" placeholder="عبارت موردنظر">
            </label>
            <label>وضعیت
                <select name="md_status">
                    <option value="">همه وضعیت‌ها</option>
                    <option value="draft" <?php selected($filters['md_status'], 'draft'); ?>>پیش‌نویس</option>
                    <option value="final" <?php selected($filters['md_status'], 'final'); ?>>ثبت نهایی</option>
                </select>
            </label>
            <label>شناسه یادگیرنده
                <input name="md_learner" type="number" min="1" step="1"
                    value="<?php echo $filters['md_learner'] ? esc_attr($filters['md_learner']) : ''; ?>" placeholder="مثلاً ۱۲">
            </label>
            <label>شناسه آموزش
                <input name="md_content" type="number" min="1" step="1"
                    value="<?php echo $filters['md_content'] ? esc_attr($filters['md_content']) : ''; ?>" placeholder="شناسه نوشته">
            </label>
            <label>از تاریخ (میلادی)
                <input name="md_from" type="date" value="<?php echo esc_attr($filters['md_from']); ?>">
            </label>
            <label>تا تاریخ (میلادی)
                <input name="md_to" type="date" value="<?php echo esc_attr($filters['md_to']); ?>">
            </label>
            <div class="md-learning-report-filter-actions">
                <button type="submit" class="button button-primary">اعمال فیلترها</button>
                <a class="button" href="<?php echo esc_url(self::page_url()); ?>">پاک‌کردن فیلترها</a>
            </div>
        </form>
        <?php
    }

    private static function list_view($filters) {
        $page = isset($_GET['paged']) && is_string($_GET['paged'])
            ? max(1, absint($_GET['paged'])) : 1;
        $report = self::report($filters, $page);
        $summary = $report['summary'];
        ?>
        <h1>پاسخ‌های آموزشی</h1>
        <p class="description">پاسخ‌های خصوصی فقط برای افراد دارای مجوز مستقلِ مشاهده پاسخ‌ها نمایش داده می‌شوند.</p>
        <div class="md-learning-report-stats">
            <div><strong><?php echo esc_html(number_format_i18n($summary['total'])); ?></strong><span>کل پاسخ‌ها</span></div>
            <div><strong><?php echo esc_html(number_format_i18n($summary['drafts'])); ?></strong><span>پیش‌نویس</span></div>
            <div><strong><?php echo esc_html(number_format_i18n($summary['finals'])); ?></strong><span>ثبت نهایی</span></div>
            <div><strong><?php echo esc_html(number_format_i18n($summary['learners'])); ?></strong><span>یادگیرنده پاسخ‌دهنده</span></div>
            <div><strong><?php echo esc_html(number_format_i18n($summary['content_items'])); ?></strong><span>آموزش دارای پاسخ</span></div>
        </div>
        <?php self::search_form($filters); ?>
        <div class="md-learning-report-table-wrap">
            <table class="widefat striped md-learning-report-table">
                <thead><tr><th>یادگیرنده</th><th>آموزش</th><th>پرسش</th><th>وضعیت</th><th>آخرین ویرایش</th><th>جزئیات</th></tr></thead>
                <tbody>
                    <?php if (!$report['rows']) : ?>
                        <tr><td colspan="6">پاسخی با این فیلترها پیدا نشد.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($report['rows'] as $row) : ?>
                        <tr>
                            <td><strong><a href="<?php echo esc_url(self::page_url(array_merge(self::filter_params($filters), array('md_learner' => absint($row->learner_id))))); ?>"><?php echo esc_html($row->display_name ?: 'یادگیرنده حذف‌شده'); ?></a></strong>
                                <small><?php echo esc_html('L-' . $row->learner_id); ?></small></td>
                            <td><strong><a href="<?php echo esc_url(self::page_url(array_merge(self::filter_params($filters), array('md_content' => absint($row->content_id))))); ?>"><?php echo esc_html($row->content_title ?: 'آموزش حذف‌شده'); ?></a></strong>
                                <small><?php echo esc_html('#' . $row->content_id); ?></small></td>
                            <td class="md-learning-report-question"><?php echo esc_html(wp_trim_words($row->question_text, 16, '…')); ?></td>
                            <td><span class="md-learning-report-state <?php echo esc_attr($row->status === 'final' ? 'is-final' : 'is-draft'); ?>"><?php echo esc_html(self::status_label($row->status)); ?></span></td>
                            <td><?php echo esc_html(self::visible_time($row->updated_at)); ?></td>
                            <td><a class="button button-small" href="<?php echo esc_url(self::page_url(array_merge(self::filter_params($filters), array('md_answer' => (int) $row->id)))); ?>">مشاهده پاسخ</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        if ($report['pages'] > 1) {
            $base = self::page_url(array_merge(self::filter_params($filters), array('paged' => '%#%')));
            echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post(paginate_links(array(
                'base' => $base,
                'format' => '',
                'current' => $report['page'],
                'total' => $report['pages'],
                'prev_text' => 'قبلی',
                'next_text' => 'بعدی',
            ))) . '</div></div>';
        }
        ?>
        <p class="description">این آمار براساس تعداد پاسخ‌های پرسش‌هاست، نه تعداد کامل‌کنندگان کل صفحه یا دوره. تاریخ‌های فیلتر میلادی و زمان‌های ذخیره‌شده UTC هستند.</p>
        <?php
    }

    private static function detail_view($id, $filters) {
        $answer = self::detail($id);
        $back = self::page_url(self::filter_params($filters));
        if (!$answer) {
            status_header(404);
            echo '<div class="notice notice-error"><p>پاسخ موردنظر پیدا نشد.</p></div>';
            echo '<p><a class="button" href="' . esc_url($back) . '">بازگشت به فهرست</a></p>';
            return;
        }
        // Fail closed if the audit table is unavailable: private plaintext
        // cannot be viewed without an attributable access log.
        if (!self::record_view($answer)) {
            wp_die('ثبت گزارش دسترسی ممکن نشد؛ مشاهده پاسخ خصوصی به‌صورت موقت غیرفعال است.', '', array('response' => 503));
        }
        ?>
        <p><a href="<?php echo esc_url($back); ?>">← بازگشت به فهرست پاسخ‌ها</a></p>
        <h1>جزئیات پاسخ آموزشی</h1>
        <div class="md-learning-report-detail">
            <dl>
                <dt>یادگیرنده</dt><dd><?php echo esc_html(($answer->display_name ?: 'یادگیرنده حذف‌شده') . ' · L-' . $answer->learner_id); ?></dd>
                <dt>آموزش</dt><dd><?php echo esc_html($answer->content_title ?: 'آموزش حذف‌شده'); ?>
                    <?php if ($answer->content_status === 'publish') : ?>
                        <a href="<?php echo esc_url(get_permalink($answer->content_id)); ?>" target="_blank" rel="noopener noreferrer">مشاهده آموزش</a>
                    <?php endif; ?></dd>
                <dt>وضعیت پاسخ</dt><dd><?php echo esc_html(self::status_label($answer->status)); ?></dd>
                <dt>ایجاد</dt><dd><?php echo esc_html(self::visible_time($answer->created_at)); ?></dd>
                <dt>آخرین ویرایش</dt><dd><?php echo esc_html(self::visible_time($answer->updated_at)); ?></dd>
                <dt>ثبت نهایی</dt><dd><?php echo esc_html(self::visible_time($answer->submitted_at)); ?></dd>
            </dl>
            <section>
                <h2>متن پرسش هنگام ثبت پاسخ</h2>
                <div class="md-learning-report-content"><?php echo nl2br(esc_html($answer->question_text)); ?></div>
            </section>
            <section>
                <h2>پاسخ خصوصی یادگیرنده</h2>
                <div class="md-learning-report-content md-learning-report-answer"><?php echo $answer->answer_text === '' ? '<em>پاسخی وارد نشده است.</em>' : nl2br(esc_html($answer->answer_text)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            </section>
        </div>
        <p class="description">مشاهده این پاسخ به‌صورت رویداد مدیریتی ثبت شد. ویرایش یا حذف پاسخ از این صفحه ممکن نیست.</p>
        <?php
    }

    public static function render() {
        self::require_permission();
        $filters = self::filters($_GET);
        $id = isset($_GET['md_answer']) && is_string($_GET['md_answer']) ? absint($_GET['md_answer']) : 0;
        ?>
        <div class="wrap md-learning-report" dir="rtl">
            <?php if ($id) : self::detail_view($id, $filters); else : self::list_view($filters); endif; ?>
        </div>
        <?php
    }
}
