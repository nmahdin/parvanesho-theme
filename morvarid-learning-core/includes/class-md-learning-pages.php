<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Front-end login, registration and Phase-A personal dashboard. */
final class MD_Learning_Pages {
    public static function init() {
        add_shortcode('md_learning_register', array(__CLASS__, 'register_shortcode'));
        add_shortcode('md_learning_login', array(__CLASS__, 'login_shortcode'));
        add_shortcode('md_learning_dashboard', array(__CLASS__, 'dashboard_shortcode'));
        add_action('template_redirect', array(__CLASS__, 'private_headers'), 2);
    }

    public static function private_headers() {
        if (is_page(MD_Learning_Schema::page_ids())
            || isset($_GET['md_learning_link'])
            || isset($_GET['md_learning_error'])) {
            nocache_headers();
            header('Cache-Control: private, no-store, max-age=0, must-revalidate', true);
            header('Referrer-Policy: no-referrer', true);
            header('X-Robots-Tag: noindex, nofollow, noarchive', true);
            add_filter('wp_robots', function ($robots) {
                $robots['noindex'] = true;
                $robots['nofollow'] = true;
                return $robots;
            });
        }
    }

    private static function shell_start($title, $subtitle = '') {
        $html = '<section class="md-learning-app" dir="rtl"><div class="md-learning-shell">';
        $html .= '<div class="md-learning-heading"><span>مروارید درون · یادگیری</span><h1>' . esc_html($title) . '</h1>';
        if ($subtitle) {
            $html .= '<p>' . esc_html($subtitle) . '</p>';
        }
        return $html . '</div>';
    }

    private static function shell_end() {
        return '</div></section>';
    }

    public static function register_shortcode() {
        $challenge_id = MD_Learning_Auth::requested_challenge();
        if (MD_Learning_Auth::current_learner_id()) {
            $dest = $challenge_id ? get_permalink($challenge_id) : MD_Learning_Schema::url('dashboard');
            return self::shell_start('پروفایل شما آماده است')
                . '<p>پیش‌تر وارد حساب یادگیری خود شده‌اید.</p>'
                . '<a class="md-learning-button" href="' . esc_url($dest) . '">ادامه مسیر یادگیری</a>'
                . self::shell_end();
        }
        ob_start();
        ?>
        <?php echo self::shell_start('ساخت پروفایل یادگیرنده', 'بدون ایمیل و شماره همراه. یک نام وارد کنید و کد خصوصی خود را دریافت کنید.'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <form class="md-learning-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="md_learning_register">
            <?php if ($challenge_id) : ?><input type="hidden" name="md_challenge" value="<?php echo esc_attr($challenge_id); ?>"><?php endif; ?>
            <?php wp_nonce_field('md_learning_register'); ?>
            <label>نام و نام خانوادگی
                <input type="text" name="md_name" maxlength="120" autocomplete="name" required placeholder="نام شما">
            </label>
            <label class="md-learning-check">
                <input type="checkbox" name="md_consent" value="1" required>
                <span>با ذخیره نام، وضعیت یادگیری و پاسخ‌های آموزشی‌ام موافقم. می‌دانم کد و لینک خصوصی مانند رمز ورود هستند.</span>
            </label>
            <div class="md-learning-trap" aria-hidden="true">
                <label>وب‌سایت <input type="text" name="md_website" tabindex="-1" autocomplete="off"></label>
            </div>
            <button class="md-learning-button" type="submit">ثبت‌نام و دریافت کد ۸ رقمی</button>
        </form>
        <p class="md-learning-note">حساب دارید؟ <a href="<?php echo esc_url(MD_Learning_Schema::url('login')); ?>">با کد قبلی وارد شوید</a>.</p>
        <p class="md-learning-note">نام، راه بازیابی حساب نیست. کد و لینک را نزد خود نگه دارید؛ اگر هر دو را گم کنید، باید از مدیر کمک بگیرید.</p>
        <?php echo self::shell_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php
        return ob_get_clean();
    }

    public static function login_shortcode() {
        $challenge_id = MD_Learning_Auth::requested_challenge();
        if (MD_Learning_Auth::current_learner_id()) {
            $dest = $challenge_id ? get_permalink($challenge_id) : MD_Learning_Schema::url('dashboard');
            return self::shell_start('شما وارد شده‌اید')
                . '<p>می‌توانید مسیر یادگیری خود را ادامه دهید.</p>'
                . '<a class="md-learning-button" href="' . esc_url($dest) . '">ادامه مسیر یادگیری</a>'
                . self::shell_end();
        }
        $error = isset($_GET['md_learning_error']) && is_string($_GET['md_learning_error'])
            ? sanitize_key(wp_unslash($_GET['md_learning_error'])) : '';
        ob_start();
        ?>
        <?php echo self::shell_start('ورود به داشبورد یادگیری', 'همان کد ۸ رقمی اصلی را وارد کنید. برای ورود نیازی به ایمیل نیست.'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php if ($error === 'invalid') : ?>
            <p class="md-learning-error" role="alert">کد واردشده معتبر نیست یا حساب غیرفعال است.</p>
        <?php elseif ($error === 'invalid_link') : ?>
            <p class="md-learning-error" role="alert">لینک خصوصی معتبر نیست یا باطل شده است. از کد اصلی استفاده کنید.</p>
        <?php endif; ?>
        <form class="md-learning-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="md_learning_login">
            <?php if ($challenge_id) : ?><input type="hidden" name="md_challenge" value="<?php echo esc_attr($challenge_id); ?>"><?php endif; ?>
            <?php wp_nonce_field('md_learning_login'); ?>
            <label>کد ورود ۸ رقمی
                <input type="text" name="md_code" inputmode="numeric" autocomplete="off" minlength="8" maxlength="8"
                    pattern="[0-9۰-۹٠-٩]{8}" dir="ltr" class="md-learning-code-input"
                    placeholder="۱۲۳۴۵۶۷۸" required>
            </label>
            <button class="md-learning-button" type="submit">ورود به داشبورد من</button>
        </form>
        <p class="md-learning-note">لینک اختصاصی خود را دارید؟ کافی است آن را باز کنید؛ نیازی به واردکردن کد نیست.</p>
        <p class="md-learning-note">هنوز عضو نشده‌اید؟ <a href="<?php echo esc_url(MD_Learning_Schema::url('register')); ?>">ایجاد پروفایل جدید</a>.</p>
        <?php echo self::shell_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php
        return ob_get_clean();
    }

    public static function dashboard_shortcode() {
        $id = MD_Learning_Auth::current_learner_id();
        if (!$id) {
            return self::shell_start('ورود برای مشاهده داشبورد')
                . '<p>برای مشاهده اطلاعات شخصی، ابتدا با کد یا لینک اختصاصی خود وارد شوید.</p>'
                . '<a class="md-learning-button" href="' . esc_url(MD_Learning_Schema::url('login')) . '">ورود با کد</a>'
                . self::shell_end();
        }
        $learner = MD_Learning_Auth::get_learner($id);
        if (!$learner) {
            return '<p>پروفایل شما در دسترس نیست.</p>';
        }
        $receipt = MD_Learning_Auth::take_receipt();
        ob_start();
        ?>
        <?php echo self::shell_start('سلام، ' . $learner->display_name, 'پروفایل یکپارچه شما برای آکادمی، چالش‌ها و دوره‌ها آماده است.'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php if ($receipt) : ?>
            <section class="md-learning-receipt" aria-label="اطلاعات ورود صادرشده">
                <h2>کد و لینک اختصاصی شما ساخته شد</h2>
                <p><strong>این اطلاعات فقط همین یک‌بار نمایش داده می‌شوند.</strong> آن‌ها را اکنون در جای امنی ذخیره کنید.</p>
                <label>کد ۸ رقمی ورود
                    <input type="text" dir="ltr" readonly onclick="this.select()" value="<?php echo esc_attr($receipt['code']); ?>">
                </label>
                <label>لینک مستقیم خصوصی
                    <input type="text" dir="ltr" readonly onclick="this.select()" value="<?php echo esc_attr($receipt['link']); ?>">
                </label>
                <p>لینک مستقیم به‌اندازه رمز عبور محرمانه است. با ارسال آن، دیگران نیز می‌توانند وارد حساب شما شوند.</p>
            </section>
        <?php endif; ?>
        <?php $requested_challenge = MD_Learning_Auth::requested_challenge(); ?>
        <?php if ($requested_challenge) : ?>
            <div class="md-learning-challenge-next">
                <h2>ادامهٔ ثبت‌نام در چالش</h2>
                <p>پروفایل شما آماده است. اکنون می‌توانید با همین حساب وارد چالش شوید.</p>
                <a class="md-learning-button" href="<?php echo esc_url(get_permalink($requested_challenge)); ?>">رفتن به چالش ←</a>
            </div>
        <?php endif; ?>
        <div class="md-learning-status">
            <div><span>شناسه یادگیرنده</span><strong><?php echo esc_html('L-' . $learner->id); ?></strong></div>
            <div><span>وضعیت حساب</span><strong>فعال</strong></div>
            <div><span>تاریخ عضویت</span><strong><?php echo esc_html(wp_date('Y/m/d', strtotime($learner->created_at . ' UTC'))); ?></strong></div>
        </div>
        <div class="md-learning-overview">
            <div><span class="dashicons dashicons-welcome-learn-more" aria-hidden="true"></span><h3>فایل‌های آموزشی</h3><p>پاسخ‌های آموزشی در فاز D به این پروفایل متصل می‌شوند.</p></div>
            <div><span class="dashicons dashicons-flag" aria-hidden="true"></span><h3>چالش‌های من</h3><p>عضویت‌ها و درصد پیشرفت در پایین این صفحه نمایش داده می‌شوند.</p></div>
            <div><span class="dashicons dashicons-book" aria-hidden="true"></span><h3>دوره‌ها</h3><p>دوره‌ها و پیشرفت درس‌ها در فاز F اضافه می‌شوند.</p></div>
        </div>
        <section class="md-learning-my-challenges">
            <h2>چالش‌های من</h2>
            <?php if (class_exists('MD_Learning_Challenges')) : ?>
                <?php echo MD_Learning_Challenges::dashboard_html($id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php else : ?>
                <p>ماژول چالش‌ها در دسترس نیست.</p>
            <?php endif; ?>
        </section>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="md-learning-logout-form">
            <input type="hidden" name="action" value="md_learning_logout">
            <?php wp_nonce_field('md_learning_logout'); ?>
            <button type="submit" class="md-learning-button md-learning-logout">خروج از حساب</button>
        </form>
        <p class="md-learning-note">اگر کد یا لینک خود را گم کردید، مدیر می‌تواند اعتبار جدیدی صادر کند؛ پاسخ‌ها و شناسهٔ یادگیرنده حفظ خواهند شد.</p>
        <?php echo self::shell_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php
        return ob_get_clean();
    }
}
