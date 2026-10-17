<?php
/**
 * Configurable theme cards for all three standard WordPress widget areas.
 *
 * All cards can be reordered, configured or deleted at Appearance > Widgets.
 * Never replace a user's widget selections with hardcoded sidebar markup.
 *
 * @package Morvarid_Daron
 */
if (!defined('ABSPATH')) {
    exit;
}

class MD_Sidebar_Card_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('md_sidebar_card', 'مروارید | کارت سایدبار', array(
            'classname' => 'md-sidebar-card-widget',
            'description' => 'جستجو، معرفی، مطالب، دسته‌ها، چالش، دوره، جلسه و کارت خانواده؛ کاملاً قابل ویرایش در ابزارک‌ها',
            'customize_selective_refresh' => true,
        ));
    }

    public static function types() {
        return array(
            'search' => 'جستجو',
            'author' => 'معرفی نویسندهٔ مقاله',
            'posts' => 'تازه‌ترین مطالب مجله',
            'categories' => 'دسته‌بندی‌ها',
            'challenge' => 'معرفی چالش آینده',
            'progress' => 'پیشرفت چالش جاری',
            'courses' => 'دوره‌های آکادمی',
            'lessons' => 'جلسه‌ها و پادکست‌ها',
            'academy_categories' => 'دسته‌بندی مرتبط آکادمی',
            'challenge_categories' => 'دسته‌های چالش',
            'family' => 'عضو خانواده مروارید درون',
        );
    }

    public static function defaults($type) {
        $titles = array(
            'search' => 'جستجو', 'author' => 'نویسنده', 'posts' => 'تازه‌ترین مطالب',
            'categories' => 'موضوعات مجله', 'challenge' => 'چالش پیش‌رو',
            'progress' => 'پیشرفت چالش', 'courses' => 'دوره‌های آکادمی',
            'lessons' => 'تازه‌ترین جلسه‌ها', 'academy_categories' => '',
            'challenge_categories' => 'دسته‌های چالش',
            'family' => 'عضو خانواده مروارید درون شوید',
        );
        return array(
            'type' => $type, 'title' => isset($titles[$type]) ? $titles[$type] : '',
            'post_type' => 'post', 'taxonomy' => 'category', 'count' => 5,
            'body' => 'از تازه‌ترین مطالب، کارگاه‌ها و تمرین‌های رایگان باخبر شوید.',
            'button_text' => 'عضویت در خبرنامه ←', 'button_url' => '',
            'show_socials' => 1, 'network_urls' => null,
            'placeholder' => 'عبارت مورد نظر…',
        );
    }

    public function widget($args, $instance) {
        $type = isset($instance['type']) ? $instance['type'] : 'search';
        if (!isset(self::types()[$type])) {
            return;
        }
        $defaults = self::defaults($type);
        $i = wp_parse_args((array) $instance, $defaults);
        $title = apply_filters('widget_title', (string) $i['title'], $i, $this->id_base);
        $count = max(1, min(12, absint($i['count'])));
        // Reuse the theme's escaped card renderers, then move their existing
        // section markup into WordPress's widget wrapper (correct widget ID
        // and classes, no nested card padding).
        ob_start();
        switch ($type) {
            case 'search':
                $allowed = array('', 'post', 'md_course', 'md_lesson', 'md_podcast', 'md_resource', 'md_challenge');
                $post_type = in_array($i['post_type'], $allowed, true) ? $i['post_type'] : '';
                md_sidebar_search($post_type, $title, $i['placeholder']);
                break;
            case 'author':
                if (is_singular('post')) {
                    md_sidebar_author($title);
                }
                break;
            case 'posts':
                md_sidebar_popular($title, $count);
                break;
            case 'categories':
                $taxonomies = array('category', 'md_academy_cat', 'md_resource_cat', 'md_challenge_cat');
                md_sidebar_categories(in_array($i['taxonomy'], $taxonomies, true) ? $i['taxonomy'] : 'category', $title, $count);
                break;
            case 'challenge':
                md_sidebar_challenge_promo($title);
                break;
            case 'progress':
                md_sidebar_challenge_progress($title);
                break;
            case 'courses':
                md_sidebar_academy_promo($title, $count);
                break;
            case 'lessons':
                md_sidebar_lesson_promo($title, $count);
                break;
            case 'academy_categories':
                $is_resource = is_singular('md_resource') || is_post_type_archive('md_resource') || is_tax('md_resource_cat');
                md_sidebar_categories($is_resource ? 'md_resource_cat' : 'md_academy_cat',
                    $title !== '' ? $title : ($is_resource ? 'دسته‌بندی منابع' : 'دسته‌بندی دوره‌ها'));
                break;
            case 'challenge_categories':
                md_sidebar_categories('md_challenge_cat', $title);
                break;
            case 'family':
                md_sidebar_newsletter(array(
                    'title' => $title, 'body' => $i['body'],
                    'button_text' => $i['button_text'],
                    'button_url' => $i['button_url'],
                    'show_socials' => $i['show_socials'],
                    'network_urls' => $i['network_urls'],
                ));
                break;
        }
        $markup = trim(ob_get_clean());
        if ($markup === '') {
            // The Appearance > Widgets editor runs through REST, outside any
            // queried post: context-only cards would otherwise preview blank.
            if (defined('REST_REQUEST') && REST_REQUEST &&
                current_user_can('edit_theme_options')) {
                $preview_title = $title !== '' ? $title : (self::types()[$type] ?? 'کارت سایدبار');
                $markup = '<section class="md-widget md-widget-' . esc_attr($type) . '">'
                    . '<h2 class="md-widget-title">' . esc_html($preview_title) . '</h2>'
                    . '<p class="md-widget-preview-note">پیش‌نمایش کارت در صفحه مرتبط با محتوای سایت نمایش داده می‌شود.</p></section>';
            } else {
                return;
            }
        }
        $modifier = '';
        if (preg_match('/^<section class="md-widget (md-widget-[a-z0-9_-]+)">/i', $markup, $match)) {
            $modifier = $match[1];
            $markup = substr($markup, strlen($match[0]));
            $markup = preg_replace('/<\\/section>\\s*$/', '', $markup, 1);
        }
        $before = isset($args['before_widget']) ? $args['before_widget'] : '<section class="md-widget">';
        if ($modifier) {
            $before = preg_replace('/class="/', 'class="' . esc_attr($modifier) . ' ', $before, 1);
        }
        echo $before . $markup . (isset($args['after_widget']) ? $args['after_widget'] : '</section>');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- existing theme renderers escape data
    }

    public function update($new, $old) {
        $type = isset($new['type']) && isset(self::types()[$new['type']])
            ? $new['type'] : (isset($old['type']) ? $old['type'] : 'search');
        $prev = wp_parse_args((array) $old, self::defaults($type));
        $v = $prev;
        $v['type'] = $type;
        $v['title'] = sanitize_text_field(wp_unslash($new['title'] ?? ''));
        $v['body'] = sanitize_textarea_field(wp_unslash($new['body'] ?? ''));
        $v['button_text'] = sanitize_text_field(wp_unslash($new['button_text'] ?? ''));
        $v['button_url'] = esc_url_raw(wp_unslash($new['button_url'] ?? ''), array('https', 'http'));
        $v['placeholder'] = sanitize_text_field(wp_unslash($new['placeholder'] ?? 'عبارت مورد نظر…'));
        $v['network_urls'] = array();
        foreach (md_social_networks() as $key => $_network) {
            $v['network_urls'][$key] = esc_url_raw(
                wp_unslash($new['network_urls'][$key] ?? ''), array('https', 'http')
            );
        }
        $v['show_socials'] = !empty($new['show_socials']) ? 1 : 0;
        $v['count'] = max(1, min(12, absint($new['count'] ?? 5)));
        $allowed = array('', 'post', 'md_course', 'md_lesson', 'md_podcast', 'md_resource', 'md_challenge');
        $v['post_type'] = in_array($new['post_type'] ?? '', $allowed, true) ? ($new['post_type'] ?? '') : 'post';
        $taxonomies = array('category', 'md_academy_cat', 'md_resource_cat', 'md_challenge_cat');
        $v['taxonomy'] = in_array($new['taxonomy'] ?? '', $taxonomies, true) ? ($new['taxonomy'] ?? 'category') : 'category';
        return $v;
    }

    private function form_select($key, $label, $options, $current) {
        echo '<p><label for="' . esc_attr($this->get_field_id($key)) . '">' . esc_html($label) . '</label>';
        echo '<select class="widefat" id="' . esc_attr($this->get_field_id($key)) .
            '" name="' . esc_attr($this->get_field_name($key)) . '">';
        foreach ($options as $value => $name) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($current, (string) $value, false) .
                '>' . esc_html($name) . '</option>';
        }
        echo '</select></p>';
    }

    private function form_input($key, $label, $value, $type = 'text') {
        echo '<p><label for="' . esc_attr($this->get_field_id($key)) . '">' . esc_html($label) . '</label>';
        echo '<input class="widefat" type="' . esc_attr($type) . '" id="' .
            esc_attr($this->get_field_id($key)) . '" name="' .
            esc_attr($this->get_field_name($key)) . '" value="' . esc_attr($value) . '"' .
            ($type === 'number' ? ' min="1" max="12"' : '') . '></p>';
    }

    public function form($instance) {
        $type = isset($instance['type']) && isset(self::types()[$instance['type']]) ? $instance['type'] : 'search';
        $i = wp_parse_args((array) $instance, self::defaults($type));
        $this->form_select('type', 'نوع کارت', self::types(), $type);
        $this->form_input('title', 'عنوان کارت (برای بعضی کارت‌ها اختیاری)', $i['title']);
        $this->form_input('placeholder', 'متن داخل فیلد جستجو', $i['placeholder']);
        $this->form_select('post_type', 'محدوده جستجو', array(
            ''=>'کل سایت', 'post'=>'مجله', 'md_course'=>'دوره‌ها', 'md_lesson'=>'درس‌ها',
            'md_podcast'=>'پادکست‌ها', 'md_resource'=>'منابع آموزشی', 'md_challenge'=>'چالش‌ها',
        ), $i['post_type']);
        $this->form_select('taxonomy', 'دسته‌بندی برای کارت موضوعات', array(
            'category'=>'موضوعات مجله', 'md_academy_cat'=>'دسته‌های آکادمی',
            'md_resource_cat'=>'دسته‌های منابع', 'md_challenge_cat'=>'دسته‌های چالش',
        ), $i['taxonomy']);
        $this->form_input('count', 'تعداد مطالب / دوره‌ها / جلسه‌ها (۱ تا ۱۲)', $i['count'], 'number');
        echo '<p><label for="' . esc_attr($this->get_field_id('body')) . '">متن کارت خانواده</label><textarea class="widefat" rows="3" id="' .
            esc_attr($this->get_field_id('body')) . '" name="' .
            esc_attr($this->get_field_name('body')) . '">' . esc_textarea($i['body']) . '</textarea></p>';
        $this->form_input('button_text', 'متن دکمه خانواده', $i['button_text']);
        $this->form_input('button_url', 'لینک دکمه خانواده (خالی = لینک تنظیمات قالب)', $i['button_url'], 'url');
        echo '<p><label><input type="checkbox" name="' . esc_attr($this->get_field_name('show_socials')) .
            '" value="1" ' . checked(!empty($i['show_socials']), true, false) .
            '> نمایش کانال‌ها در کارت خانواده</label></p>';
        echo '<p><strong>لینک شبکه‌های اجتماعی همین کارت</strong></p>';
        echo '<p class="description">هر آدرس را از داخل ابزارک تغییر دهید. خالی‌گذاشتن لینک، آن شبکه را فقط در همین باکس پنهان می‌کند.</p>';
        foreach (md_social_networks() as $key => $network) {
            $links = is_array($i['network_urls']) ? $i['network_urls'] : null;
            $url = $links === null ? md_social_url($key) : ($links[$key] ?? '');
            echo '<p><label for="' . esc_attr($this->get_field_id('network_' . $key)) . '">'
                . esc_html('آدرس ' . $network['label']) . '</label><input type="url" class="widefat" id="'
                . esc_attr($this->get_field_id('network_' . $key)) . '" name="'
                . esc_attr($this->get_field_name('network_urls')) . '[' . esc_attr($key)
                . ']" value="' . esc_attr($url) . '" placeholder="https://"></p>';
        }
        echo '<p class="description">ترتیب، افزودن و حذف کارت‌ها را از بخش ابزارک‌ها تغییر دهید. فقط فیلدهای نوع کارت انتخاب‌شده در نمای سایت استفاده می‌شوند.</p>';
    }
}

add_action('widgets_init', function () {
    register_widget('MD_Sidebar_Card_Widget');
}, 20);

/**
 * One-time non-destructive migration of the old hardcoded card arrangement.
 * Once set, removing every widget stays intentional on all future requests.
 */
function md_seed_default_sidebar_widgets() {
    if (!is_admin() || !current_user_can('edit_theme_options')
        || get_option('md_sidebar_widgets_seeded_v3')) {
        return;
    }
    $layouts = array(
        'md-magazine-sidebar' => array('author', 'search', 'posts', 'categories', 'challenge', 'family'),
        'md-academy-sidebar' => array('search', 'courses', 'lessons', 'academy_categories', 'family'),
        'md-challenge-sidebar' => array('progress', 'search', 'challenge', 'challenge_categories', 'family'),
    );
    $config = wp_get_sidebars_widgets();
    if (!is_array($config)) {
        return;
    }
    $instances = get_option('widget_md_sidebar_card', array());
    if (!is_array($instances)) {
        $instances = array();
    }
    $max = 0;
    foreach (array_keys($instances) as $key) {
        if (is_numeric($key)) {
            $max = max($max, (int) $key);
        }
    }
    foreach ($layouts as $area => $types) {
        $existing = isset($config[$area]) && is_array($config[$area]) ? $config[$area] : array();
        // Keep every existing widget and its order; convert the old
        // always-visible family footer card to ONE removable real widget.
        $seed_types = $existing ? array('family') : $types;
        foreach ($seed_types as $type) {
            if ($type === 'family') {
                $found = false;
                foreach ($existing as $widget_id) {
                    if (preg_match('/^md_sidebar_card-([0-9]+)$/', (string) $widget_id, $match) &&
                        isset($instances[(int) $match[1]]['type']) &&
                        $instances[(int) $match[1]]['type'] === 'family') {
                        $found = true;
                        break;
                    }
                }
                if ($found) {
                    continue;
                }
            }
            $max++;
            $instances[$max] = MD_Sidebar_Card_Widget::defaults($type);
            if ($type === 'search') {
                $instances[$max]['post_type'] = $area === 'md-challenge-sidebar'
                    ? 'md_challenge' : ($area === 'md-academy-sidebar' ? 'md_course' : 'post');
                $instances[$max]['title'] = $area === 'md-challenge-sidebar'
                    ? 'جستجو در چالش‌ها' : ($area === 'md-academy-sidebar' ? 'جستجو در آکادمی' : 'جستجو در مجله');
            }
            $existing[] = 'md_sidebar_card-' . $max;
        }
        $config[$area] = $existing;
    }
    $instances['_multiwidget'] = 1;
    update_option('widget_md_sidebar_card', $instances);
    wp_set_sidebars_widgets($config);
    update_option('md_sidebar_widgets_seeded_v3', '1', false);
}
add_action('admin_init', 'md_seed_default_sidebar_widgets', 20);
