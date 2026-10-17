<?php
/**
 * Native academy, service and resource features.
 *
 * These replace the functions the theme previously expected from a companion
 * plugin. Everything is registered in the theme and works without plugins.
 *
 * @package Morvarid_Daron
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Post types managed by the academy module.
 *
 * @return array
 */
function md_academy_types() {
    return array('md_course', 'md_lesson', 'md_podcast');
}

/**
 * All content types that receive the shared academy meta box.
 *
 * @return array
 */
function md_academy_meta_post_types() {
    return array('md_course', 'md_lesson', 'md_podcast', 'md_resource', 'md_service');
}

/**
 * Field definitions for the academy meta boxes.
 *
 * @param string $post_type Post type.
 * @return array
 */
function md_academy_meta_fields($post_type) {
    $fields = array();

    if ('md_course' === $post_type) {
        $fields['_md_course_level'] = array(
            'label'   => 'سطح دوره',
            'type'    => 'select',
            'options' => array(
                'beginner'     => 'مقدماتی',
                'intermediate' => 'متوسط',
                'advanced'     => 'پیشرفته',
            ),
        );
        $fields['_md_course_duration'] = array('label' => 'مدت دوره', 'type' => 'text', 'help' => 'مثلاً ۶ هفته یا ۱۲ ساعت');
        $fields['_md_course_instructor'] = array('label' => 'مدرس', 'type' => 'text');
        $fields['_md_course_price'] = array('label' => 'هزینه', 'type' => 'text', 'help' => 'مثلاً رایگان یا ۲۵۰٬۰۰۰ تومان');
        $fields['_md_course_cta_url'] = array('label' => 'لینک ثبت‌نام', 'type' => 'url');
        $fields['_md_media_kind'] = array(
            'label'   => 'نوع رسانه معرفی',
            'type'    => 'select',
            'options' => array('video' => 'ویدیویی', 'audio' => 'صوتی', 'text' => 'متنی'),
        );
        $fields['_md_media_title'] = array('label' => 'عنوان رسانه', 'type' => 'text');
        $fields['_md_media_video_id'] = array('label' => 'ویدیوی معرفی', 'type' => 'media', 'media_type' => 'video');
        $fields['_md_media_audio_id'] = array('label' => 'فایل صوتی معرفی', 'type' => 'media', 'media_type' => 'audio');
        $fields['_md_media_url'] = array('label' => 'لینک ویدیوی خارجی', 'type' => 'url');
    }

    if ('md_lesson' === $post_type) {
        $fields['_md_course_id'] = array('label' => 'دوره والد', 'type' => 'post_select', 'post_type' => 'md_course');
        $fields['_md_lesson_order'] = array('label' => 'ترتیب جلسه', 'type' => 'number', 'help' => 'عدد کوچک‌تر، جلسه را بالاتر نشان می‌دهد.');
        $fields['_md_duration'] = array('label' => 'مدت جلسه', 'type' => 'text', 'help' => 'مثلاً ۱۸ دقیقه');
        $fields['_md_media_kind'] = array(
            'label'   => 'نوع محتوا',
            'type'    => 'select',
            'options' => array('video' => 'ویدیویی', 'audio' => 'صوتی', 'text' => 'متنی'),
        );
        $fields['_md_media_title'] = array('label' => 'عنوان رسانه', 'type' => 'text');
        $fields['_md_media_video_id'] = array('label' => 'ویدیو', 'type' => 'media', 'media_type' => 'video');
        $fields['_md_media_audio_id'] = array('label' => 'فایل صوتی', 'type' => 'media', 'media_type' => 'audio');
        $fields['_md_media_url'] = array('label' => 'لینک ویدیوی خارجی', 'type' => 'url', 'help' => 'برای آپارات، یوتیوب یا هر سرویس پشتیبانی‌شده.');
    }

    if ('md_podcast' === $post_type) {
        $fields['_md_episode_number'] = array('label' => 'شماره قسمت', 'type' => 'number');
        $fields['_md_duration'] = array('label' => 'مدت پادکست', 'type' => 'text', 'help' => 'مثلاً ۳۲ دقیقه');
        $fields['_md_media_kind'] = array(
            'label'   => 'نوع محتوا',
            'type'    => 'select',
            'options' => array('audio' => 'صوتی', 'video' => 'ویدیویی', 'text' => 'متنی'),
        );
        $fields['_md_media_title'] = array('label' => 'عنوان رسانه', 'type' => 'text');
        $fields['_md_media_audio_id'] = array('label' => 'فایل صوتی', 'type' => 'media', 'media_type' => 'audio');
        $fields['_md_media_video_id'] = array('label' => 'ویدیو', 'type' => 'media', 'media_type' => 'video');
        $fields['_md_media_url'] = array('label' => 'لینک خارجی', 'type' => 'url');
    }

    if ('md_resource' === $post_type) {
        $fields['_md_resource_kind'] = array(
            'label'   => 'نوع تمرین',
            'type'    => 'select',
            'options' => array(
                'reflection' => 'تأمل و خودشناسی',
                'practice'   => 'تمرین عملی',
                'download'   => 'کاربرگ دانلودی',
            ),
        );
        $fields['_md_resource_minutes'] = array('label' => 'زمان تقریبی (دقیقه)', 'type' => 'number');
        $fields['_md_resource_file_id'] = array('label' => 'فایل دانلودی', 'type' => 'media', 'media_type' => '');
        $fields['_md_resource_file_url'] = array('label' => 'یا لینک فایل', 'type' => 'url');
    }

    if ('md_service' === $post_type) {
        $fields['_md_service_icon'] = array('label' => 'نماد خدمت', 'type' => 'text', 'help' => 'یک نماد کوتاه مثل ✦ یا ❀');
        $fields['_md_service_duration'] = array('label' => 'مدت جلسه', 'type' => 'text');
        $fields['_md_service_price'] = array('label' => 'هزینه', 'type' => 'text');
        $fields['_md_service_booking_url'] = array('label' => 'لینک درخواست جلسه', 'type' => 'url');
    }

    return $fields;
}

/**
 * Register the meta box for academy content.
 *
 * @param string  $post_type Post type.
 * @param WP_Post $post      Post object.
 */
function md_academy_add_meta_box($post_type, $post) {
    if (!in_array($post_type, md_academy_meta_post_types(), true)) {
        return;
    }
    add_meta_box(
        'md_academy_details',
        'اطلاعات تکمیلی',
        'md_academy_render_meta_box',
        $post_type,
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'md_academy_add_meta_box', 10, 2);

/**
 * Render the academy meta box.
 *
 * @param WP_Post $post Post object.
 */
function md_academy_render_meta_box($post) {
    $fields = md_academy_meta_fields($post->post_type);
    if (!$fields) {
        return;
    }
    wp_nonce_field('md_academy_meta', 'md_academy_meta_nonce');
    echo '<div class="md-admin-fields">';
    foreach ($fields as $key => $field) {
        md_academy_render_field($key, $field, $post->ID);
    }
    echo '</div>';
}

/**
 * Render one academy field.
 *
 * @param string $key    Meta key.
 * @param array  $field  Field definition.
 * @param int    $post_id Post ID.
 */
function md_academy_render_field($key, $field, $post_id) {
    $value = get_post_meta($post_id, $key, true);
    $id    = 'md-field-' . sanitize_html_class($key);
    $type  = isset($field['type']) ? $field['type'] : 'text';

    echo '<div class="md-admin-field">';
    echo '<label for="' . esc_attr($id) . '">' . esc_html($field['label']) . '</label>';

    if ('select' === $type) {
        echo '<select id="' . esc_attr($id) . '" name="' . esc_attr($key) . '">';
        echo '<option value="">— انتخاب کنید —</option>';
        foreach ($field['options'] as $option_value => $option_label) {
            echo '<option value="' . esc_attr($option_value) . '" ' . selected($value, $option_value, false) . '>' . esc_html($option_label) . '</option>';
        }
        echo '</select>';
    } elseif ('post_select' === $type) {
        $target_type = $field['post_type'];
        $items = get_posts(array(
            'post_type'      => $target_type,
            'posts_per_page' => 200,
            'post_status'    => array('publish', 'draft', 'pending', 'private'),
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));
        echo '<select id="' . esc_attr($id) . '" name="' . esc_attr($key) . '">';
        echo '<option value="0">— انتخاب کنید —</option>';
        foreach ($items as $item) {
            echo '<option value="' . esc_attr($item->ID) . '" ' . selected((int) $value, $item->ID, false) . '>' . esc_html(get_the_title($item)) . '</option>';
        }
        echo '</select>';
    } elseif ('media' === $type) {
        $media_id   = absint($value);
        $media_name = md_attachment_name($media_id);
        $media_type = isset($field['media_type']) ? $field['media_type'] : '';
        echo '<div class="md-media-field">';
        echo '<input type="hidden" id="' . esc_attr($id) . '" name="' . esc_attr($key) . '" value="' . esc_attr($media_id) . '" data-media-id="' . esc_attr($media_id) . '">';
        echo '<button type="button" class="button md-media-select" data-target="' . esc_attr($id) . '" data-type="' . esc_attr($media_type) . '">انتخاب از رسانه</button> ';
        echo '<button type="button" class="button md-media-remove" data-target="' . esc_attr($id) . '">حذف</button>';
        echo '<span class="md-media-name" id="' . esc_attr($id) . '_name">' . esc_html($media_name) . '</span>';
        echo '</div>';
    } elseif ('number' === $type) {
        echo '<input type="number" min="0" step="1" id="' . esc_attr($id) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" class="small-text">';
    } else {
        $input_type = ('url' === $type) ? 'url' : 'text';
        echo '<input type="' . esc_attr($input_type) . '" id="' . esc_attr($id) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" class="regular-text">';
    }

    if (!empty($field['help'])) {
        echo '<p class="description">' . esc_html($field['help']) . '</p>';
    }
    echo '</div>';
}

/**
 * Save academy meta.
 *
 * @param int $post_id Post ID.
 */
function md_academy_save_meta($post_id) {
    if (!isset($_POST['md_academy_meta_nonce'])) {
        return;
    }
    $nonce = sanitize_text_field(wp_unslash($_POST['md_academy_meta_nonce']));
    if (!wp_verify_nonce($nonce, 'md_academy_meta')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $post_type = get_post_type($post_id);
    $fields    = md_academy_meta_fields($post_type);
    if (!$fields) {
        return;
    }

    foreach ($fields as $key => $field) {
        $raw = isset($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
        if (!is_scalar($raw)) {
            continue;
        }

        $type = isset($field['type']) ? $field['type'] : 'text';
        if ('number' === $type || 'media' === $type || 'post_select' === $type) {
            $value = absint($raw);
        } elseif ('url' === $type) {
            $value = esc_url_raw($raw);
        } elseif ('select' === $type) {
            $value = array_key_exists($raw, $field['options']) ? $raw : '';
        } else {
            $value = sanitize_text_field($raw);
        }

        if ($value === '' || $value === 0) {
            delete_post_meta($post_id, $key);
        } else {
            update_post_meta($post_id, $key, $value);
        }
    }
}
add_action('save_post', 'md_academy_save_meta');

/**
 * Enqueue the reusable media picker on academy and service screens.
 *
 * @param string $hook Current admin page.
 */
function md_academy_admin_assets($hook) {
    if (!in_array($hook, array('post.php', 'post-new.php'), true)) {
        return;
    }
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->post_type, md_academy_meta_post_types(), true)) {
        return;
    }
    wp_enqueue_media();
    wp_enqueue_style('md-admin-fields', MD_THEME_URI . '/assets/css/admin-fields.css', array(), MD_THEME_VERSION);
    wp_enqueue_script('md-admin-media', MD_THEME_URI . '/assets/js/admin-media.js', array('jquery'), MD_THEME_VERSION, true);
}
add_action('admin_enqueue_scripts', 'md_academy_admin_assets');

/**
 * Configured label for an academy post type.
 *
 * @param string $post_type Post type.
 * @return string
 */
function md_academy_type_label($post_type) {
    $labels = array(
        'md_course'  => 'دوره آموزشی',
        'md_lesson'  => 'جلسه آموزشی',
        'md_podcast' => 'پادکست',
        'md_resource'=> 'تمرین و منبع',
        'md_service' => 'خدمت کوچینگ',
    );
    return isset($labels[$post_type]) ? $labels[$post_type] : '';
}

/**
 * Current academy post type, including post type archives.
 *
 * @return string
 */
function md_academy_queried_type() {
    if (is_post_type_archive()) {
        $type = get_query_var('post_type');
        if (is_array($type)) {
            $type = reset($type);
        }
        if ($type) {
            return (string) $type;
        }
        $object = get_queried_object();
        if ($object && isset($object->name)) {
            return (string) $object->name;
        }
    }
    return (string) get_post_type();
}

/**
 * Archive title for the current academy screen.
 *
 * @return string
 */
function md_academy_archive_title() {
    if (is_tax('md_academy_cat')) {
        return single_term_title('', false);
    }
    $type = md_academy_queried_type();
    if ('md_lesson' === $type) {
        return 'جلسه‌های آکادمی';
    }
    if ('md_podcast' === $type) {
        return 'پادکست‌های مروارید درون';
    }
    if ('md_service' === $type) {
        return 'خدمات کوچینگ';
    }
    if ('md_resource' === $type) {
        return 'تمرین‌ها و منابع رایگان';
    }
    return md_theme_option('academy_title', 'آکادمی مروارید درون');
}

/**
 * Archive intro for the current academy screen.
 *
 * @return string
 */
function md_academy_archive_intro() {
    if (is_tax('md_academy_cat')) {
        $term = get_queried_object();
        if ($term && !empty($term->description)) {
            return $term->description;
        }
        return 'محتوای آکادمی با موضوع ' . single_term_title('', false);
    }
    $type = md_academy_queried_type();
    if ('md_lesson' === $type) {
        return 'جلسه‌های صوتی و ویدیویی برای یادگیری گام‌به‌گام.';
    }
    if ('md_podcast' === $type) {
        return 'گفت‌وگوها و شنیدنی‌هایی برای رشد آگاهانه.';
    }
    if ('md_service' === $type) {
        return 'مسیرهای همراهی و کوچینگ برای والدین و نوجوانان.';
    }
    if ('md_resource' === $type) {
        return 'تمرین‌ها، کاربرگ‌ها و ابزارهای کوچک برای قدم‌های واقعی.';
    }
    return md_theme_option('academy_intro', 'دوره‌ها، درس‌های متنی، ویدیوها و پادکست‌های آموزشی برای رشد آگاهانه.');
}

/**
 * Filter tabs and category pills for academy archives.
 */
if (!function_exists('md_academy_filters')) {
function md_academy_filters() {
    $types = array(
        'md_course'  => 'دوره‌ها',
        'md_lesson'  => 'جلسه‌ها',
        'md_podcast' => 'پادکست‌ها',
    );

    $current_type = md_academy_queried_type();
    if (is_tax('md_academy_cat')) {
        $current_type = 'md_course';
    }

    echo '<nav class="md-academy-tabs" aria-label="نوع محتوای آکادمی">';
    foreach ($types as $type => $label) {
        $url = get_post_type_archive_link($type);
        if (!$url) {
            continue;
        }
        $active = ($current_type === $type) ? ' is-active' : '';
        echo '<a class="md-academy-tab' . esc_attr($active) . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
    }
    echo '</nav>';

    $terms = get_terms(array(
        'taxonomy'   => 'md_academy_cat',
        'hide_empty' => true,
    ));
    if (is_wp_error($terms) || !$terms) {
        return;
    }

    $all_url = $current_type ? get_post_type_archive_link($current_type) : get_post_type_archive_link('md_course');
    echo '<div class="md-filter-bar md-academy-terms">';
    echo '<a class="md-academy-pill' . (is_tax('md_academy_cat') ? '' : ' is-active') . '" href="' . esc_url($all_url ? $all_url : home_url('/')) . '">همه موضوع‌ها</a>';
    foreach ($terms as $term) {
        $active = is_tax('md_academy_cat', $term->term_id) ? ' is-active' : '';
        $link   = get_term_link($term);
        if (is_wp_error($link)) {
            continue;
        }
        echo '<a class="md-academy-pill' . esc_attr($active) . '" href="' . esc_url($link) . '">' . esc_html($term->name) . '</a>';
    }
    echo '</div>';
}

}

/**
 * Render the main academy loop as cards.
 */
if (!function_exists('md_academy_directory')) {
function md_academy_directory() {
    if (!have_posts()) {
        echo '<p class="empty-note">هنوز محتوایی در این بخش منتشر نشده است.</p>';
        return;
    }
    echo '<div class="three-col card-grid md-academy-grid">';
    while (have_posts()) {
        the_post();
        md_academy_card(get_the_ID());
    }
    echo '</div>';
}

}

/**
 * Render one academy card.
 *
 * @param int|null $post_id Post ID.
 */
function md_academy_card($post_id = null) {
    $post_id = $post_id ? $post_id : get_the_ID();
    if (!$post_id) {
        return;
    }
    $type     = get_post_type($post_id);
    $label    = md_academy_type_label($type);
    $duration = get_post_meta($post_id, '_md_duration', true);
    $kind     = get_post_meta($post_id, '_md_media_kind', true);
    $kinds    = array('video' => 'ویدیویی', 'audio' => 'صوتی', 'text' => 'متنی');
    $symbols  = array('md_course' => '✦', 'md_lesson' => '▶', 'md_podcast' => '🎧', 'md_resource' => '✎', 'md_service' => '❀');
    $symbol   = isset($symbols[$type]) ? $symbols[$type] : '✦';

    $excerpt = get_post_field('post_excerpt', $post_id);
    if (!$excerpt) {
        $excerpt = get_post_field('post_content', $post_id);
    }
    ?>
    <article class="content-card md-academy-card">
        <a class="card-cover" href="<?php echo esc_url(get_permalink($post_id)); ?>" aria-label="<?php echo esc_attr(get_the_title($post_id)); ?>">
            <?php
            if (has_post_thumbnail($post_id)) {
                echo get_the_post_thumbnail($post_id, 'md-card', array('loading' => 'lazy'));
            } else {
                echo '<span class="image-fallback" aria-hidden="true">' . esc_html($symbol) . '</span>';
            }
            ?>
        </a>
        <div class="card-body">
            <span class="eyebrow"><?php echo esc_html($label); ?></span>
            <h3><a href="<?php echo esc_url(get_permalink($post_id)); ?>"><?php echo esc_html(get_the_title($post_id)); ?></a></h3>
            <p><?php echo esc_html(md_trim_text($excerpt, 18)); ?></p>
            <div class="md-card-footer">
                <span class="md-format-label">
                    <?php
                    $bits = array();
                    if ($duration) {
                        $bits[] = $duration;
                    }
                    if ($kind && isset($kinds[$kind])) {
                        $bits[] = $kinds[$kind];
                    }
                    echo esc_html(implode(' · ', $bits));
                    ?>
                </span>
                <a class="text-link" href="<?php echo esc_url(get_permalink($post_id)); ?>">مشاهده ←</a>
            </div>
        </div>
    </article>
    <?php
}

/**
 * Render the media player for a lesson, podcast or course.
 *
 * The plugin used to provide md_academy_media(). This is the native fallback.
 *
 * @param int|null $post_id Post ID.
 * @return string
 */
if (!function_exists('md_academy_media')) {
    function md_academy_media($post_id = null) {
        $post_id = $post_id ? $post_id : get_the_ID();
        if (!$post_id) {
            return '';
        }

        $kind = get_post_meta($post_id, '_md_media_kind', true);
        if (!$kind) {
            $kind = ('md_podcast' === get_post_type($post_id)) ? 'audio' : 'video';
        }

        $video_id  = absint(get_post_meta($post_id, '_md_media_video_id', true));
        $audio_id  = absint(get_post_meta($post_id, '_md_media_audio_id', true));
        $legacy_id = absint(get_post_meta($post_id, '_md_media_id', true));
        $external  = esc_url_raw(get_post_meta($post_id, '_md_media_url', true));
        $title     = sanitize_text_field(get_post_meta($post_id, '_md_media_title', true));

        $player = '';

        if ('audio' === $kind) {
            $audio_id = $audio_id ? $audio_id : $legacy_id;
            if ($audio_id) {
                $src = wp_get_attachment_url($audio_id);
                if ($src) {
                    $player = wp_audio_shortcode(array('src' => $src, 'preload' => 'metadata'));
                }
            }
            if (!$player && $external) {
                $player = wp_audio_shortcode(array('src' => $external, 'preload' => 'metadata'));
            }
        } elseif ('video' === $kind) {
            if ($external) {
                $embed = wp_oembed_get($external);
                if ($embed) {
                    $player = $embed;
                } else {
                    $player = '<p class="md-media-link"><a href="' . esc_url($external) . '" rel="nofollow noopener" target="_blank">تماشای ویدیو</a></p>';
                }
            }
            if (!$player) {
                $video_id = $video_id ? $video_id : $legacy_id;
                if ($video_id) {
                    $src = wp_get_attachment_url($video_id);
                    if ($src) {
                        $player = wp_video_shortcode(array('src' => $src, 'preload' => 'metadata'));
                    }
                }
            }
        }

        if (!$player) {
            return '';
        }

        $html = '<div class="md-featured-media md-learning-media">';
        if ($title) {
            $html .= '<h3>' . esc_html($title) . '</h3>';
        }
        $html .= $player . '</div>';
        return $html;
    }
}

/**
 * List the lessons that belong to a course.
 *
 * The plugin used to provide md_academy_lessons(). This is the native fallback.
 *
 * @param int|null $course_id Course ID.
 * @return string
 */
if (!function_exists('md_academy_lessons')) {
    function md_academy_lessons($course_id = null) {
        $course_id = $course_id ? $course_id : get_the_ID();
        if (!$course_id) {
            return '';
        }

        $can_edit = current_user_can('edit_post', $course_id);
        $lessons  = get_posts(array(
            'post_type'      => 'md_lesson',
            'posts_per_page' => -1,
            'post_status'    => $can_edit ? array('publish', 'draft', 'pending', 'private') : 'publish',
            'meta_key'       => '_md_lesson_order',
            'orderby'        => array('meta_value_num' => 'ASC', 'date' => 'ASC'),
            'meta_query'     => array(
                array(
                    'key'     => '_md_course_id',
                    'value'   => $course_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ),
            ),
            'no_found_rows'  => true,
        ));

        if (!$lessons) {
            return '<p class="empty-note">هنوز جلسه‌ای برای این دوره ثبت نشده است.</p>';
        }

        $html = '<ol class="md-lesson-list">';
        foreach ($lessons as $index => $lesson) {
            $duration = get_post_meta($lesson->ID, '_md_duration', true);
            $badge    = ('draft' !== $lesson->post_status) ? '' : ' <span class="md-inline-status">پیش‌نویس</span>';
            $html .= '<li><a href="' . esc_url(get_permalink($lesson)) . '">';
            $html .= '<span class="md-lesson-number">' . esc_html(md_fa_num($index + 1)) . '</span>';
            $html .= '<span class="md-lesson-title">' . esc_html(get_the_title($lesson)) . $badge . '</span>';
            if ($duration) {
                $html .= '<span class="md-lesson-meta">' . esc_html($duration) . '</span>';
            }
            $html .= '</a></li>';
        }
        $html .= '</ol>';
        return $html;
    }
}

/**
 * Get the ordered lessons of a course.
 *
 * @param int  $course_id Course ID.
 * @param bool $include_unpublished Include drafts for editors.
 * @return WP_Post[]
 */
function md_get_course_lessons($course_id, $include_unpublished = false) {
    $status = 'publish';
    if ($include_unpublished && current_user_can('edit_post', $course_id)) {
        $status = array('publish', 'draft', 'pending', 'private');
    }
    return get_posts(array(
        'post_type'      => 'md_lesson',
        'posts_per_page' => -1,
        'post_status'    => $status,
        'meta_key'       => '_md_lesson_order',
        'orderby'        => array('meta_value_num' => 'ASC', 'date' => 'ASC'),
        'meta_query'     => array(
            array(
                'key'     => '_md_course_id',
                'value'   => $course_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ),
        ),
        'no_found_rows'  => true,
    ));
}

/**
 * Previous/next lesson links inside the same course.
 *
 * @param int $lesson_id Lesson ID.
 * @return string
 */
function md_academy_lesson_navigation($lesson_id = null) {
    $lesson_id = $lesson_id ? $lesson_id : get_the_ID();
    $course_id = absint(get_post_meta($lesson_id, '_md_course_id', true));
    if (!$course_id) {
        return '';
    }
    $lessons = md_get_course_lessons($course_id, true);
    if (count($lessons) < 2) {
        return '';
    }
    $ids = wp_list_pluck($lessons, 'ID');
    $pos = array_search($lesson_id, $ids, true);
    if (false === $pos) {
        return '';
    }

    $prev = ($pos > 0) ? $lessons[$pos - 1] : null;
    $next = ($pos < count($lessons) - 1) ? $lessons[$pos + 1] : null;
    if (!$prev && !$next) {
        return '';
    }

    $html = '<nav class="md-lesson-nav" aria-label="جلسه‌های دوره">';
    if ($prev) {
        $html .= '<a class="md-lesson-nav-prev" href="' . esc_url(get_permalink($prev)) . '"><span>جلسه قبل</span><strong>' . esc_html(get_the_title($prev)) . '</strong></a>';
    }
    if ($next) {
        $html .= '<a class="md-lesson-nav-next" href="' . esc_url(get_permalink($next)) . '"><span>جلسه بعد</span><strong>' . esc_html(get_the_title($next)) . '</strong></a>';
    }
    $html .= '</nav>';
    return $html;
}

/**
 * Label/value pairs for the single template meta area.
 *
 * @param int|null $post_id Post ID.
 * @return array
 */
function md_academy_meta_pairs($post_id = null) {
    $post_id = $post_id ? $post_id : get_the_ID();
    $type    = get_post_type($post_id);
    $pairs   = array();

    if ('md_course' === $type) {
        $levels = array('beginner' => 'مقدماتی', 'intermediate' => 'متوسط', 'advanced' => 'پیشرفته');
        $level  = get_post_meta($post_id, '_md_course_level', true);
        if ($level && isset($levels[$level])) {
            $pairs[] = array('label' => 'سطح', 'value' => $levels[$level]);
        }
        $duration = get_post_meta($post_id, '_md_course_duration', true);
        if ($duration) {
            $pairs[] = array('label' => 'مدت', 'value' => $duration);
        }
        $instructor = get_post_meta($post_id, '_md_course_instructor', true);
        if ($instructor) {
            $pairs[] = array('label' => 'مدرس', 'value' => $instructor);
        }
        $price = get_post_meta($post_id, '_md_course_price', true);
        if ($price) {
            $pairs[] = array('label' => 'هزینه', 'value' => $price);
        }
    } elseif ('md_lesson' === $type || 'md_podcast' === $type) {
        if ('md_podcast' === $type) {
            $episode = absint(get_post_meta($post_id, '_md_episode_number', true));
            if ($episode) {
                $pairs[] = array('label' => 'قسمت', 'value' => md_fa_num($episode));
            }
        }
        $duration = get_post_meta($post_id, '_md_duration', true);
        if ($duration) {
            $pairs[] = array('label' => 'مدت', 'value' => $duration);
        }
        if ('md_lesson' === $type) {
            $course_id = absint(get_post_meta($post_id, '_md_course_id', true));
            if ($course_id) {
                $pairs[] = array('label' => 'دوره', 'value' => get_the_title($course_id), 'url' => get_permalink($course_id));
            }
        }
    } elseif ('md_resource' === $type) {
        $kind = get_post_meta($post_id, '_md_resource_kind', true);
        if ($kind) {
            $pairs[] = array('label' => 'نوع', 'value' => md_resource_kind_label($kind));
        }
        $minutes = absint(get_post_meta($post_id, '_md_resource_minutes', true));
        if ($minutes) {
            $pairs[] = array('label' => 'زمان', 'value' => md_fa_num($minutes) . ' دقیقه');
        }
    } elseif ('md_service' === $type) {
        $duration = get_post_meta($post_id, '_md_service_duration', true);
        if ($duration) {
            $pairs[] = array('label' => 'مدت', 'value' => $duration);
        }
        $price = get_post_meta($post_id, '_md_service_price', true);
        if ($price) {
            $pairs[] = array('label' => 'هزینه', 'value' => $price);
        }
    }

    return $pairs;
}

/**
 * Human label for a resource kind.
 *
 * @param string $kind Kind key.
 * @return string
 */
function md_resource_kind_label($kind) {
    $labels = array(
        'reflection' => 'تأمل و خودشناسی',
        'practice'   => 'تمرین عملی',
        'download'   => 'کاربرگ دانلودی',
    );
    return isset($labels[$kind]) ? $labels[$kind] : 'تأمل و خودشناسی';
}

/**
 * Download button for a resource.
 *
 * @param int|null $post_id Post ID.
 * @return string
 */
function md_resource_file_link($post_id = null) {
    $post_id = $post_id ? $post_id : get_the_ID();
    $file_id = absint(get_post_meta($post_id, '_md_resource_file_id', true));
    $file    = esc_url_raw(get_post_meta($post_id, '_md_resource_file_url', true));
    if ($file_id) {
        $file = wp_get_attachment_url($file_id);
    }
    if (!$file) {
        return '';
    }
    return '<a class="btn btn-primary md-download-button" href="' . esc_url($file) . '" download>دریافت فایل <span aria-hidden="true">↓</span></a>';
}

/**
 * Booking URL for a service.
 *
 * @param int|null $post_id Post ID.
 * @return string
 */
function md_service_booking_url($post_id = null) {
    $post_id = $post_id ? $post_id : get_the_ID();
    $url     = esc_url_raw(get_post_meta($post_id, '_md_service_booking_url', true));
    if (!$url) {
        $url = get_theme_mod('md_booking_url', home_url('/contact/'));
    }
    return $url;
}
