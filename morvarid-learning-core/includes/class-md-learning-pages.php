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
        // Challenge pages now render user-specific content based on a central
        // session; no shared full-page cache may store these responses.
        if (is_singular('md_challenge')) {
            nocache_headers();
            header('Cache-Control: private, no-store, max-age=0, must-revalidate', true);
            if (MD_Learning_Auth::current_learner_id()) {
                header('Referrer-Policy: no-referrer', true);
            }
        }
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
        $resource_id = MD_Learning_Auth::requested_resource();
        if (MD_Learning_Auth::current_learner_id()) {
            $dest = $challenge_id ? get_permalink($challenge_id) : ($resource_id ? get_permalink($resource_id) : MD_Learning_Schema::url('dashboard'));
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
            <?php if ($resource_id) : ?><input type="hidden" name="md_resource" value="<?php echo esc_attr($resource_id); ?>"><?php endif; ?>
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
        <p class="md-learning-note">حساب دارید؟ <a href="<?php echo esc_url($challenge_id ? add_query_arg('md_challenge', $challenge_id, MD_Learning_Schema::url('login')) : ($resource_id ? add_query_arg('md_resource', $resource_id, MD_Learning_Schema::url('login')) : MD_Learning_Schema::url('login'))); ?>">با کد قبلی وارد شوید</a>.</p>
        <p class="md-learning-note">نام، راه بازیابی حساب نیست. کد و لینک را نزد خود نگه دارید؛ اگر هر دو را گم کنید، باید از مدیر کمک بگیرید.</p>
        <?php echo self::shell_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php
        return ob_get_clean();
    }

    public static function login_shortcode() {
        $challenge_id = MD_Learning_Auth::requested_challenge();
        $resource_id = MD_Learning_Auth::requested_resource();
        if (MD_Learning_Auth::current_learner_id()) {
            $dest = $challenge_id ? get_permalink($challenge_id) : ($resource_id ? get_permalink($resource_id) : MD_Learning_Schema::url('dashboard'));
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
            <?php if ($resource_id) : ?><input type="hidden" name="md_resource" value="<?php echo esc_attr($resource_id); ?>"><?php endif; ?>
            <?php wp_nonce_field('md_learning_login'); ?>
            <label>کد ورود ۸ رقمی
                <input type="text" name="md_code" inputmode="numeric" autocomplete="off" minlength="8" maxlength="8"
                    pattern="[0-9۰-۹٠-٩]{8}" dir="ltr" class="md-learning-code-input"
                    placeholder="۱۲۳۴۵۶۷۸" required>
            </label>
            <button class="md-learning-button" type="submit">ورود به داشبورد من</button>
        </form>
        <p class="md-learning-note">لینک اختصاصی خود را دارید؟ کافی است آن را باز کنید؛ نیازی به واردکردن کد نیست.</p>
        <p class="md-learning-note">هنوز عضو نشده‌اید؟ <a href="<?php echo esc_url($challenge_id ? add_query_arg('md_challenge', $challenge_id, MD_Learning_Schema::url('register')) : ($resource_id ? add_query_arg('md_resource', $resource_id, MD_Learning_Schema::url('register')) : MD_Learning_Schema::url('register'))); ?>">ایجاد پروفایل جدید</a>.</p>
        <?php echo self::shell_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php
        return ob_get_clean();
    }

    public static function dashboard_shortcode() {
        return MD_Learning_Dashboard::render();
    }
}
