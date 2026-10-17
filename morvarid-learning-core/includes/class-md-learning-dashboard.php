<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Phase C: a real, private personal workspace, rendered from session-owned
 * data. No profile details or activity responses are published via REST.
 */
final class MD_Learning_Dashboard {
    const TABS = array('home', 'challenges', 'courses', 'activity', 'answers', 'profile', 'security');

    public static function selected_tab() {
        $tab = isset($_GET['md_tab']) && is_string($_GET['md_tab'])
            ? sanitize_key(wp_unslash($_GET['md_tab'])) : 'home';
        return in_array($tab, self::TABS, true) ? $tab : 'home';
    }

    private static function link($tab) {
        return esc_url(add_query_arg('md_tab', $tab, MD_Learning_Schema::url('dashboard')));
    }

    private static function action_url() {
        return esc_url(admin_url('admin-post.php'));
    }

    private static function escaped_date($utc) {
        if (!$utc) {
            return '—';
        }
        $timestamp = strtotime($utc . ' UTC');
        return $timestamp ? esc_html(wp_date('Y/m/d H:i', $timestamp)) : '—';
    }

    private static function receipt($data) {
        if (!$data || !isset($data['code'], $data['link'])) {
            return;
        }
        ?>
        <section class="md-learning-receipt" aria-labelledby="md-learning-receipt-heading">
            <h2 id="md-learning-receipt-heading">کد و لینک جدید شما آماده است</h2>
            <p><strong>این اطلاعات فقط یک‌بار نمایش داده می‌شوند.</strong> اکنون آن‌ها را در محل امن ذخیره کنید.</p>
            <label>کد ورود اصلی ۸ رقمی</label>
            <div class="md-learning-copy-row">
                <input type="text" readonly dir="ltr" aria-label="کد ورود" value="<?php echo esc_attr($data['code']); ?>">
                <button class="md-learning-copy-button" type="button" data-md-copy>کپی کد</button>
            </div>
            <label>لینک ورود اختصاصی</label>
            <div class="md-learning-copy-row">
                <input type="text" readonly dir="ltr" aria-label="لینک اختصاصی" value="<?php echo esc_attr($data['link']); ?>">
                <button class="md-learning-copy-button" type="button" data-md-copy>کپی لینک</button>
            </div>
            <p>هر فردی که این کد یا لینک را داشته باشد می‌تواند وارد حساب شما شود. آن‌ها را عمومی نکنید.</p>
        </section>
        <?php
    }

    /** Central learner's challenge progress; no arbitrary learner ID allowed. */
    public static function challenge_data($learner_id) {
        if (!$learner_id || $learner_id !== MD_Learning_Auth::current_learner_id()
            || !class_exists('MD_Learning_Challenges') || !MD_Learning_Challenges::enabled()) {
            return array('items' => array(), 'activity' => array(), 'active' => 0, 'completed' => 0, 'total_days' => 0);
        }
        global $wpdb;
        $table = MD_Learning_Challenges::table();
        $rows = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT challenge_id, member_id FROM {$table} WHERE learner_id = %d ORDER BY id DESC LIMIT 100",
            $learner_id
        ));
        $items = array();
        $activity = array();
        $completed = 0;
        $total_days = 0;
        foreach ($rows as $row) {
            $challenge = get_post(absint($row->challenge_id));
            $member = get_post(absint($row->member_id));
            if (!$challenge || $challenge->post_type !== 'md_challenge' || $challenge->post_status !== 'publish'
                || !$member || $member->post_type !== 'md_challenge_member' || $member->post_status !== 'private'
                || (int) get_post_meta($member->ID, '_md_challenge_id', true) !== (int) $challenge->ID
                || get_post_meta($member->ID, '_md_member_revoked', true)) {
                continue;
            }
            $days = md_challenge_days($challenge->ID);
            $entries = md_challenge_member_entries($member->ID);
            $done = 0;
            foreach ($days as $day) {
                $entry = isset($entries[$day->ID]) ? $entries[$day->ID] : null;
                if (!$entry) {
                    continue;
                }
                $is_done = (bool) get_post_meta($entry->ID, '_md_task_done', true);
                if ($is_done) {
                    $done++;
                }
                $last = (string) get_post_meta($entry->ID, '_md_answer_updated', true);
                if ($last !== '') {
                    $activity[] = array(
                        'challenge_id' => $challenge->ID,
                        'challenge_title' => $challenge->post_title,
                        'day_id' => $day->ID,
                        'day_title' => $day->post_title,
                        'date' => $last,
                        'done' => $is_done,
                    );
                }
            }
            $total = count($days);
            $items[] = array(
                'id' => $challenge->ID,
                'title' => $challenge->post_title,
                'done' => $done,
                'total' => $total,
                'percent' => $total ? (int) round($done * 100 / $total) : 0,
                'last_activity' => (string) get_post_meta($member->ID, '_md_member_last_activity', true),
            );
            $completed += $done;
            $total_days += $total;
        }
        usort($activity, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });
        return array(
            'items' => $items,
            'activity' => array_slice($activity, 0, 25),
            'active' => count($items),
            'completed' => $completed,
            'total_days' => $total_days,
        );
    }

    private static function notice() {
        $notice = isset($_GET['md_notice']) && is_string($_GET['md_notice'])
            ? sanitize_key(wp_unslash($_GET['md_notice'])) : '';
        $messages = array(
            'name_updated' => 'نام نمایشی شما ذخیره شد.',
            'credentials_rotated' => 'کد و لینک قبلی باطل شدند و اطلاعات ورود جدید صادر شد.',
            'session_revoked' => 'نشست انتخاب‌شده پایان یافت.',
            'other_sessions_ended' => 'از همه دستگاه‌های دیگر خارج شدید.',
        );
        if (isset($messages[$notice])) {
            echo '<p class="md-learning-message" role="status">' . esc_html($messages[$notice]) . '</p>';
        }
    }

    private static function navigation($selected) {
        $tabs = array(
            'home' => array('خانه من', 'dashicons-admin-home'),
            'challenges' => array('چالش‌های من', 'dashicons-flag'),
            'courses' => array('دوره‌های من', 'dashicons-welcome-learn-more'),
            'activity' => array('فعالیت‌ها', 'dashicons-chart-line'),
            'answers' => array('پاسخ‌های آموزشی من', 'dashicons-edit-page'),
            'profile' => array('پروفایل', 'dashicons-id'),
            'security' => array('تنظیمات ورود', 'dashicons-lock'),
        );
        echo '<nav class="md-learning-nav" aria-label="بخش‌های داشبورد شخصی">';
        foreach ($tabs as $key => $data) {
            echo '<a class="' . ($selected === $key ? 'is-current' : '') . '" href="' . self::link($key) . '"';
            if ($selected === $key) {
                echo ' aria-current="page"';
            }
            echo '><span class="dashicons ' . esc_attr($data[1]) . '" aria-hidden="true"></span><span>' . esc_html($data[0]) . '</span></a>';
        }
        echo '</nav>';
    }

    private static function cards($data, $answers, $courses) {
        ?>
        <div class="md-learning-kpis">
            <div><span class="dashicons dashicons-flag" aria-hidden="true"></span>
                <strong><?php echo esc_html(number_format_i18n($data['active'])); ?></strong><span>چالش‌های من</span></div>
            <div><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                <strong><?php echo esc_html(number_format_i18n($data['completed'])); ?></strong><span>روزهای انجام‌شده</span></div>
            <div><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                <strong><?php echo esc_html(number_format_i18n($data['total_days'])); ?></strong><span>روزهای منتشرشده</span></div>
            <div><span class="dashicons dashicons-edit-page" aria-hidden="true"></span>
                <strong><?php echo esc_html(number_format_i18n(count($answers))); ?></strong><span>پاسخ‌های آموزشی من</span></div>
            <div><span class="dashicons dashicons-welcome-learn-more" aria-hidden="true"></span>
                <strong><?php echo esc_html(number_format_i18n(count($courses))); ?></strong><span>دوره‌های من</span></div>
        </div>
        <?php
    }

    private static function challenge_cards($data) {
        if (empty($data['items'])) {
            echo '<div class="md-learning-empty"><span class="dashicons dashicons-flag" aria-hidden="true"></span>';
            echo '<p>هنوز در چالشی عضو نشده‌اید. از فهرست چالش‌ها مسیر دلخواهتان را انتخاب کنید.</p>';
            if (post_type_exists('md_challenge')) {
                echo '<a class="md-learning-button" href="' . esc_url(get_post_type_archive_link('md_challenge')) . '">مشاهده چالش‌ها</a>';
            }
            echo '</div>';
            return;
        }
        echo '<div class="md-learning-challenge-grid">';
        foreach ($data['items'] as $item) {
            echo '<article class="md-learning-challenge-card"><h3>' . esc_html($item['title']) . '</h3>';
            echo '<p>' . esc_html(number_format_i18n($item['done']) . ' از ' . number_format_i18n($item['total']) . ' روز انجام‌شده') . '</p>';
            echo '<div class="md-learning-challenge-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr($item['percent']) . '" aria-label="درصد پیشرفت"><span style="width:' . esc_attr($item['percent']) . '%"></span></div>';
            echo '<p>' . esc_html(number_format_i18n($item['percent'])) . '٪ پیشرفت</p>';
            echo '<a href="' . esc_url(get_permalink($item['id'])) . '">ادامه چالش ←</a></article>';
        }
        echo '</div>';
    }

    private static function course_cards($courses) {
        if (!$courses) {
            echo '<div class="md-learning-empty"><p>هنوز در دوره‌ای عضو نشده‌اید.</p>';
            if (post_type_exists('md_course')) {
                echo '<a class="md-learning-button" href="' . esc_url(get_post_type_archive_link('md_course')) . '">مشاهده دوره‌ها</a>';
            }
            echo '</div>';
            return;
        }
        echo '<div class="md-learning-challenge-grid md-learning-courses-grid">';
        foreach ($courses as $course) {
            echo '<article class="md-learning-challenge-card">';
            echo '<h3>' . esc_html($course['title']) . '</h3>';
            echo '<div class="md-learning-challenge-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr($course['percent']) . '" aria-label="درصد پیشرفت دوره"><span style="width:' . esc_attr($course['percent']) . '%"></span></div>';
            echo '<p>' . esc_html(number_format_i18n($course['completed']) . ' از ' . number_format_i18n($course['total']) . ' درس تکمیل‌شده · ' . number_format_i18n($course['percent']) . '٪') . '</p>';
            $next = null;
            foreach ($course['lessons'] as $lesson) {
                if (!$lesson['completed']) {
                    $next = $lesson;
                    break;
                }
            }
            if ($next) {
                echo '<p>درس بعدی: <a href="' . esc_url(get_permalink($next['id'])) . '">' . esc_html($next['title']) . '</a></p>';
            } elseif ($course['total'] > 0) {
                echo '<p>تمام درس‌های منتشرشده را تکمیل کرده‌اید.</p>';
            }
            echo '<a href="' . esc_url(get_permalink($course['course_id'])) . '">مشاهده دوره ←</a></article>';
        }
        echo '</div>';
    }

    private static function activities($data, $limit = 10) {
        if (!$data['activity']) {
            echo '<div class="md-learning-empty"><p>هنوز پاسخی در چالش‌های شما ذخیره نشده است.</p></div>';
            return;
        }
        echo '<ol class="md-learning-activity-list">';
        foreach (array_slice($data['activity'], 0, $limit) as $activity) {
            $link = get_permalink($activity['challenge_id']) . '#member-day-' . absint($activity['day_id']);
            echo '<li><span class="dashicons ' . ($activity['done'] ? 'dashicons-yes-alt' : 'dashicons-edit') . '" aria-hidden="true"></span>';
            echo '<div><a href="' . esc_url($link) . '">' . esc_html($activity['day_title']) . '</a>';
            echo '<p>' . esc_html($activity['challenge_title']) . ' · ' . ($activity['done'] ? 'انجام‌شده' : 'پاسخ ذخیره‌شده') . '</p></div>';
            // Challenge data is in the site's local timezone. Never assume UTC.
            echo '<time>' . esc_html($activity['date']) . '</time></li>';
        }
        echo '</ol>';
    }

    private static function answer_history($answers, $limit = 50) {
        if (!$answers) {
            echo '<div class="md-learning-empty"><p>هنوز پاسخی برای فایل‌های آموزشی ذخیره نکرده‌اید.</p></div>';
            return;
        }
        echo '<div class="md-learning-answer-history">';
        foreach (array_slice($answers, 0, $limit) as $a) {
            $url = get_permalink(absint($a->content_id)) . '#md-learning-q-' . $a->block_id;
            echo '<article class="md-learning-answer-card">';
            echo '<div class="md-learning-answer-heading"><a href="' . esc_url($url) . '">' . esc_html(get_the_title(absint($a->content_id))) . '</a>';
            echo '<span class="md-learning-answer-badge">' . ($a->status === 'final' ? 'ثبت نهایی' : 'پیش‌نویس') . '</span></div>';
            echo '<strong>' . esc_html($a->question_text) . '</strong>';
            echo '<p>' . nl2br(esc_html($a->answer_text)) . '</p>';
            echo '<small>آخرین ویرایش: ' . self::escaped_date($a->updated_at) . '</small>';
            echo '</article>';
        }
        echo '</div>';
    }

    private static function profile($learner) {
        ?>
        <h2>اطلاعات پروفایل من</h2>
        <form class="md-learning-profile-logout" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="md_learning_logout">
            <?php wp_nonce_field('md_learning_logout'); ?>
            <button type="submit" class="md-learning-button md-learning-logout">خروج از حساب</button>
        </form>
        <p class="md-learning-muted">این نام برای نمایش پروفایل شما استفاده می‌شود. ویرایش آن، اطلاعات ورودتان را تغییر نمی‌دهد.</p>
        <div class="md-learning-profile-meta">
            <span>تاریخ عضویت</span><strong><?php echo self::escaped_date($learner->created_at); ?></strong>
            <span>وضعیت</span><strong>فعال</strong>
        </div>
        <form class="md-learning-settings-form" method="post" action="<?php echo self::action_url(); ?>">
            <input type="hidden" name="action" value="md_learning_profile_update">
            <?php wp_nonce_field('md_learning_profile_update'); ?>
            <label>نام نمایشی
                <input type="text" name="md_name" maxlength="120" value="<?php echo esc_attr($learner->display_name); ?>" required autocomplete="name">
            </label>
            <button class="md-learning-button" type="submit">ذخیره نام</button>
        </form>
        <p class="md-learning-muted">برای عضویت در چالش‌ها نیازی به واردکردن دوباره نام ندارید.</p>
        <?php
    }

    private static function security() {
        $sessions = MD_Learning_Profile::sessions_for_current();
        $current_hash = MD_Learning_Auth::current_session_hash();
        ?>
        <h2>امنیت و تنظیمات ورود</h2>
        <p class="md-learning-muted">کد ۸ رقمی و لینک خصوصی مانند رمز عبورند. در صورت نگرانی از افشای آن‌ها می‌توانید هر دو را مجدداً صادر کنید.</p>
        <section class="md-learning-settings-panel">
            <h3>صدور دوباره کد و لینک</h3>
            <p>با این کار کد و تمام لینک‌های قبلی باطل می‌شوند و همه نشست‌ها پایان می‌یابند. برای همین مرورگر، یک نشست تازه ایجاد می‌شود و کد جدید فقط یک‌بار نمایش داده خواهد شد.</p>
            <form method="post" action="<?php echo self::action_url(); ?>" class="md-learning-settings-form">
                <input type="hidden" name="action" value="md_learning_rotate_self">
                <?php wp_nonce_field('md_learning_rotate_self'); ?>
                <label class="md-learning-check"><input type="checkbox" name="md_confirm_rotate" value="1" required>
                    <span>می‌دانم کد و لینک‌های قبلی دیگر کار نخواهند کرد.</span></label>
                <button class="md-learning-button" type="submit">ساخت کد و لینک تازه</button>
            </form>
        </section>
        <section class="md-learning-settings-panel">
            <h3>دستگاه‌ها و نشست‌های من</h3>
            <p>نشست‌ها پس از ۷ روز عدم فعالیت یا حداکثر ۳۰ روز منقضی می‌شوند. فقط زمان‌های نشست نمایش داده می‌شوند؛ شناسه‌های محرمانه هرگز در صفحه قرار نمی‌گیرند.</p>
            <?php if (!$sessions) : ?><p>نشست فعالی پیدا نشد.</p><?php endif; ?>
            <div class="md-learning-sessions">
            <?php foreach ($sessions as $session) :
                $is_current = $current_hash && hash_equals((string) $session->token_hash, $current_hash);
                ?>
                <div class="md-learning-session">
                    <div><strong><?php echo $is_current ? 'این مرورگر · نشست فعلی' : 'نشست دیگر'; ?></strong>
                        <p>شروع: <?php echo self::escaped_date($session->created_at); ?> · آخرین فعالیت: <?php echo self::escaped_date($session->last_seen_at); ?></p></div>
                    <form method="post" action="<?php echo self::action_url(); ?>">
                        <input type="hidden" name="action" value="md_learning_revoke_session">
                        <input type="hidden" name="md_session_id" value="<?php echo esc_attr($session->id); ?>">
                        <?php wp_nonce_field('md_learning_revoke_session'); ?>
                        <button type="submit" class="md-learning-text-button"><?php echo $is_current ? 'خروج از این مرورگر' : 'پایان نشست'; ?></button>
                    </form>
                </div>
            <?php endforeach; ?>
            </div>
            <form method="post" action="<?php echo self::action_url(); ?>" class="md-learning-signout-others">
                <input type="hidden" name="action" value="md_learning_signout_others">
                <?php wp_nonce_field('md_learning_signout_others'); ?>
                <button type="submit" class="md-learning-button md-learning-logout">خروج از تمام دستگاه‌های دیگر</button>
            </form>
        </section>
        <p class="md-learning-muted">ورود با کد ۸ رقمی پس از چند تلاش ناموفق به‌صورت موقت محدود می‌شود. لینک خصوصی طولانی‌تر است و استفاده از آن برای داده‌های حساس ترجیح دارد.</p>
        <?php
    }

    public static function render() {
        $id = MD_Learning_Auth::current_learner_id();
        if (!$id) {
            return '<section class="md-learning-app" dir="rtl"><div class="md-learning-shell"><h1>داشبورد شخصی</h1><p>برای مشاهده اطلاعات خود وارد شوید.</p><a class="md-learning-button" href="'
                . esc_url(MD_Learning_Schema::url('login')) . '">ورود با کد</a></div></section>';
        }
        $learner = MD_Learning_Auth::get_learner($id);
        if (!$learner || $learner->status !== 'active') {
            return '<p>پروفایل شما در دسترس نیست.</p>';
        }
        $tab = self::selected_tab();
        $receipt = MD_Learning_Auth::take_receipt();
        $data = self::challenge_data($id);
        $answers = class_exists('MD_Learning_Questions')
            ? MD_Learning_Questions::personal_answers($id, 100) : array();
        $courses = class_exists('MD_Learning_Courses')
            ? MD_Learning_Courses::my_courses($id) : array();
        ob_start();
        ?>
        <section class="md-learning-app md-learning-workspace" dir="rtl">
            <div class="md-learning-shell">
                <header class="md-learning-workspace-hero">
                    <div><span class="md-learning-eyebrow">مروارید درون · فضای شخصی یادگیری</span>
                        <h1>سلام، <?php echo esc_html($learner->display_name); ?> <span aria-hidden="true">✦</span></h1>
                        <p>اینجا مسیرهای یادگیری و فعالیت‌های شما در یک فضای خصوصی کنار هم هستند.</p>
                    </div>
                </header>
                <?php self::receipt($receipt); ?>
                <?php self::notice(); ?>
                <?php $challenge = MD_Learning_Auth::requested_challenge(); ?>
                <?php if ($challenge) : ?>
                    <section class="md-learning-challenge-next">
                        <h2>ادامه مسیر چالش</h2>
                        <p>حساب شما آماده است. می‌توانید با همین پروفایل در چالش موردنظر عضو شوید.</p>
                        <a class="md-learning-button" href="<?php echo esc_url(get_permalink($challenge)); ?>">رفتن به چالش ←</a>
                    </section>
                <?php endif; ?>
                <?php $resource_id = MD_Learning_Auth::requested_resource(); ?>
                <?php if ($resource_id) : ?>
                    <section class="md-learning-challenge-next">
                        <h2>بازگشت به محتوای آموزشی</h2>
                        <p>پروفایل شما آماده است. اکنون می‌توانید پاسخ پرسش‌های آموزشی را در حساب خود ذخیره کنید.</p>
                        <a class="md-learning-button" href="<?php echo esc_url(get_permalink($resource_id)); ?>">ادامه آموزش ←</a>
                    </section>
                <?php endif; ?>
                <?php $requested_course = MD_Learning_Auth::requested_course(); ?>
                <?php if ($requested_course) : ?>
                    <section class="md-learning-challenge-next">
                        <h2>ادامه مسیر دوره آموزشی</h2>
                        <p>پروفایل شما آماده است؛ اکنون می‌توانید شرایط عضویت دوره را مشاهده کنید.</p>
                        <a class="md-learning-button" href="<?php echo esc_url(get_permalink($requested_course)); ?>">بازگشت به دوره ←</a>
                    </section>
                <?php endif; ?>
                <div class="md-learning-workspace-layout">
                    <div class="md-learning-workspace-main">
                        <?php self::navigation($tab); ?>
                        <section class="md-learning-workspace-content" aria-live="polite">
                        <?php if ($tab === 'home') : ?>
                            <div class="md-learning-section-head"><h2>نمای کلی یادگیری من</h2><p>تصویری از فعالیت‌های ثبت‌شده و قدم‌های بعدی شما</p></div>
                            <?php self::cards($data, $answers, $courses); ?>
                            <div class="md-learning-section-head"><h2>ادامه چالش‌ها</h2><a href="<?php echo self::link('challenges'); ?>">همه چالش‌های من ←</a></div>
                            <?php self::challenge_cards($data); ?>
                            <div class="md-learning-section-head"><h2>دوره‌های من</h2><a href="<?php echo self::link('courses'); ?>">همه دوره‌های من ←</a></div>
                            <?php self::course_cards(array_slice($courses, 0, 3)); ?>
                            <div class="md-learning-section-head"><h2>آخرین فعالیت‌ها</h2><a href="<?php echo self::link('activity'); ?>">مشاهده همه ←</a></div>
                            <?php self::activities($data, 5); ?>
                            <div class="md-learning-section-head"><h2>پاسخ‌های آموزشی اخیر</h2><a href="<?php echo self::link('answers'); ?>">همه پاسخ‌ها ←</a></div>
                            <?php self::answer_history($answers, 3); ?>
                        <?php elseif ($tab === 'challenges') : ?>
                            <div class="md-learning-section-head"><h2>چالش‌های من</h2><p>پیشرفت هر چالش مستقل محاسبه می‌شود.</p></div>
                            <?php self::challenge_cards($data); ?>
                        <?php elseif ($tab === 'courses') : ?>
                            <div class="md-learning-section-head"><h2>دوره‌های من</h2><p>عضویت‌ها و میزان تکمیل درس‌ها با حساب مرکزی شما ثبت شده‌اند.</p></div>
                            <?php self::course_cards($courses); ?>
                        <?php elseif ($tab === 'activity') : ?>
                            <div class="md-learning-section-head"><h2>تاریخچه فعالیت‌های من</h2><p>آخرین پاسخ‌های ذخیره‌شده و مراحل انجام‌شدهٔ چالش‌ها</p></div>
                            <?php self::activities($data, 25); ?>
                        <?php elseif ($tab === 'answers') : ?>
                            <div class="md-learning-section-head"><h2>پاسخ‌های آموزشی من</h2><p>پیش‌نویس‌ها و پاسخ‌های نهایی؛ فقط برای خودتان</p></div>
                            <?php self::answer_history($answers, 100); ?>
                        <?php elseif ($tab === 'profile') : ?>
                            <?php self::profile($learner); ?>
                        <?php elseif ($tab === 'security') : ?>
                            <?php self::security(); ?>
                        <?php endif; ?>
                        </section>
                    </div>
                </div>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }
}
