<?php
/**
 * Request form for the coaching services.
 *
 * The form is a Gutenberg block: its title, intro, submit label, success text
 * and every field can be edited from the block sidebar. Submissions are stored
 * as private "md_request" posts and emailed to the site administrator.
 *
 * @package Morvarid_Daron
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Field types supported by the form builder.
 *
 * @return array
 */
function md_request_field_types() {
    return array(
        'text'     => 'متن کوتاه',
        'email'    => 'ایمیل',
        'tel'      => 'شماره تماس',
        'number'   => 'عدد',
        'url'      => 'نشانی وب',
        'date'     => 'تاریخ',
        'select'   => 'انتخابی',
        'textarea' => 'متن بلند',
    );
}

/**
 * Default fields inserted with the block.
 *
 * @return array
 */
function md_request_default_fields() {
    return array(
        array(
            'label'       => 'نام و نام خانوادگی',
            'type'        => 'text',
            'required'    => true,
            'placeholder' => 'نام شما',
            'options'     => '',
        ),
        array(
            'label'       => 'شماره تماس',
            'type'        => 'tel',
            'required'    => true,
            'placeholder' => '۰۹۱۲…',
            'options'     => '',
        ),
        array(
            'label'       => 'ایمیل',
            'type'        => 'email',
            'required'    => false,
            'placeholder' => '',
            'options'     => '',
        ),
        array(
            'label'       => 'خدمت مورد نظر',
            'type'        => 'select',
            'required'    => false,
            'placeholder' => '',
            'options'     => "مشاوره فردی\nمشاوره خانواده\nهمراهی نوجوان",
        ),
        array(
            'label'       => 'توضیح درخواست',
            'type'        => 'textarea',
            'required'    => true,
            'placeholder' => 'کمی از موقعیت خود و زمان مناسب بنویسید…',
            'options'     => '',
        ),
    );
}

/**
 * Normalize one field definition coming from block attributes.
 *
 * @param array $field Raw field.
 * @return array
 */
function md_request_normalize_field($field) {
    $types = md_request_field_types();
    $type  = isset($field['type']) ? sanitize_key($field['type']) : 'text';
    if (!isset($types[$type])) {
        $type = 'text';
    }

    return array(
        'label'       => isset($field['label']) ? sanitize_text_field($field['label']) : '',
        'type'        => $type,
        'required'    => !empty($field['required']),
        'placeholder' => isset($field['placeholder']) ? sanitize_text_field($field['placeholder']) : '',
        'options'     => isset($field['options']) ? (string) $field['options'] : '',
    );
}

/**
 * Options of a select field.
 *
 * @param array $field Field definition.
 * @return array
 */
function md_request_field_options($field) {
    $options = array();
    if (empty($field['options'])) {
        return $options;
    }

    $lines = preg_split('/\r\n|\r|\n/', (string) $field['options']);
    foreach ($lines as $line) {
        $line = trim($line);
        if ('' !== $line) {
            $options[] = $line;
        }
    }

    return $options;
}

/**
 * Find the request form block inside post content.
 *
 * @param string $content Post content.
 * @return array|null Block attributes or null.
 */
function md_request_find_block($content) {
    if (!function_exists('parse_blocks') || '' === trim((string) $content)) {
        return null;
    }

    $blocks = parse_blocks($content);
    return md_request_find_block_recursive($blocks);
}

/**
 * Recursive helper for md_request_find_block().
 *
 * @param array $blocks Parsed blocks.
 * @return array|null
 */
function md_request_find_block_recursive($blocks) {
    foreach ($blocks as $block) {
        if (isset($block['blockName']) && 'morvarid/request-form' === $block['blockName']) {
            return isset($block['attrs']) && is_array($block['attrs']) ? $block['attrs'] : array();
        }
        if (!empty($block['innerBlocks'])) {
            $found = md_request_find_block_recursive($block['innerBlocks']);
            if (null !== $found) {
                return $found;
            }
        }
    }

    return null;
}

/**
 * Attributes of the form on a given page.
 *
 * @param int $page_id Page ID.
 * @return array
 */
function md_request_page_attributes($page_id) {
    $page_id = absint($page_id);
    $page    = $page_id ? get_post($page_id) : null;
    if (!$page) {
        return array();
    }

    $attributes = md_request_find_block($page->post_content);
    return is_array($attributes) ? $attributes : array();
}

/**
 * Read the configured service from the query string.
 *
 * @return int
 */
function md_request_query_service() {
    return isset($_GET['md_service']) ? absint($_GET['md_service']) : 0;
}

/**
 * Render the request form block.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function md_block_request_form($attributes) {
    $attributes = wp_parse_args(
        $attributes,
        array(
            'title'          => 'درخواست جلسه',
            'intro'          => 'برای هماهنگی جلسه، فرم زیر را پر کنید؛ در اولین فرصت با شما تماس می‌گیریم.',
            'submitLabel'    => 'ارسال درخواست',
            'successMessage' => 'درخواست شما با موفقیت ثبت شد. به‌زودی با شما تماس می‌گیریم.',
            'fields'         => md_request_default_fields(),
        )
    );

    $fields = is_array($attributes['fields']) ? $attributes['fields'] : md_request_default_fields();
    $fields = array_map('md_request_normalize_field', $fields);
    $fields = array_values(array_filter($fields, function ($field) {
        return '' !== $field['label'];
    }));
    if (!$fields) {
        $fields = array_map('md_request_normalize_field', md_request_default_fields());
    }

    $status      = isset($_GET['md_request']) ? sanitize_key(wp_unslash($_GET['md_request'])) : '';
    $reason      = isset($_GET['md_reason']) ? sanitize_key(wp_unslash($_GET['md_reason'])) : '';
    $field_label = isset($_GET['md_field']) ? sanitize_text_field(wp_unslash($_GET['md_field'])) : '';
    $service_id  = md_request_query_service();
    $service     = $service_id ? get_the_title($service_id) : '';
    $page_id     = get_the_ID();
    $is_rest     = defined('REST_REQUEST') && REST_REQUEST;

    ob_start();
    ?>
    <section class="md-request-form" id="md-request-form">
        <header class="md-request-head">
            <span class="eyebrow">فرم ثبت درخواست</span>
            <h2><?php echo esc_html($attributes['title']); ?></h2>
            <?php if ($attributes['intro']) : ?>
                <p><?php echo esc_html($attributes['intro']); ?></p>
            <?php endif; ?>
            <?php if ($service) : ?>
                <p class="md-request-service">خدمت انتخاب‌شده: <strong><?php echo esc_html($service); ?></strong></p>
            <?php endif; ?>
        </header>

        <?php if ('success' === $status) : ?>
            <div class="md-form-notice md-form-notice-success" role="status">
                <strong>ثبت شد.</strong>
                <p><?php echo esc_html($attributes['successMessage']); ?></p>
            </div>
        <?php else : ?>
            <?php if ('error' === $status) : ?>
                <div class="md-form-notice md-form-notice-error" role="alert">
                    <strong>ارسال نشد.</strong>
                    <p>
                        <?php
                        if ('required' === $reason) {
                            echo esc_html($field_label ? 'لطفاً «' . $field_label . '» را کامل کنید.' : 'لطفاً همهٔ فیلدهای الزامی را کامل کنید.');
                        } elseif ('email' === $reason) {
                            echo 'نشانی ایمیل معتبر نیست.';
                        } elseif ('date' === $reason) {
                            echo esc_html($field_label ? 'تاریخ «' . $field_label . '» معتبر نیست؛ آن را مثل ۱۴۰۴/۰۷/۲۵ بنویسید.' : 'تاریخ شمسی را مثل ۱۴۰۴/۰۷/۲۵ بنویسید.');
                        } elseif ('expired' === $reason) {
                            echo 'زمان ارسال فرم به پایان رسیده است. صفحه را دوباره بارگذاری کنید.';
                        } else {
                            echo 'ارسال فرم ممکن نشد. دوباره تلاش کنید.';
                        }
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="md-form"<?php echo $is_rest ? ' onsubmit="return false;"' : ''; ?>>
                <input type="hidden" name="action" value="md_request_submit">
                <input type="hidden" name="md_request_page" value="<?php echo esc_attr($page_id); ?>">
                <input type="hidden" name="md_request_service" value="<?php echo esc_attr($service_id); ?>">
                <?php wp_nonce_field('md_request_submit', 'md_request_nonce'); ?>

                <div class="md-form-honeypot" aria-hidden="true">
                    <label for="md-website">این فیلد را خالی بگذارید</label>
                    <input type="text" id="md-website" name="md_website" tabindex="-1" autocomplete="off">
                </div>

                <div class="md-form-grid">
                    <?php foreach ($fields as $index => $field) : ?>
                        <?php
                        $id       = 'md-field-' . $index;
                        $wide     = in_array($field['type'], array('textarea'), true) ? ' md-form-field-wide' : '';
                        $required = $field['required'];
                        ?>
                        <div class="md-form-field md-form-field-<?php echo esc_attr($field['type']); ?><?php echo esc_attr($wide); ?>">
                            <label for="<?php echo esc_attr($id); ?>">
                                <?php echo esc_html($field['label']); ?>
                                <?php if ($required) : ?>
                                    <span class="md-form-required" aria-hidden="true">*</span>
                                <?php endif; ?>
                            </label>

                            <?php if ('textarea' === $field['type']) : ?>
                                <textarea id="<?php echo esc_attr($id); ?>" name="md_field[<?php echo esc_attr($index); ?>]" rows="5" placeholder="<?php echo esc_attr($field['placeholder']); ?>" <?php echo $required ? 'required' : ''; ?>></textarea>
                            <?php elseif ('select' === $field['type']) : ?>
                                <?php $options = md_request_field_options($field); ?>
                                <select id="<?php echo esc_attr($id); ?>" name="md_field[<?php echo esc_attr($index); ?>]" <?php echo $required ? 'required' : ''; ?>>
                                    <option value="">— انتخاب کنید —</option>
                                    <?php foreach ($options as $option) : ?>
                                        <option value="<?php echo esc_attr($option); ?>"<?php echo ($service && $option === $service) ? ' selected' : ''; ?>><?php echo esc_html($option); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ('date' === $field['type']) : ?>
                                <input
                                    type="text"
                                    id="<?php echo esc_attr($id); ?>"
                                    name="md_field[<?php echo esc_attr($index); ?>]"
                                    class="md-form-date"
                                    placeholder="<?php echo esc_attr($field['placeholder'] ? $field['placeholder'] : '۱۴۰۴/۰۷/۲۵'); ?>"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    <?php echo $required ? 'required' : ''; ?>
                                >
                                <p class="md-form-hint">تاریخ شمسی؛ مثل ۱۴۰۴/۰۷/۲۵</p>
                            <?php else : ?>
                                <input
                                    type="<?php echo esc_attr($field['type']); ?>"
                                    id="<?php echo esc_attr($id); ?>"
                                    name="md_field[<?php echo esc_attr($index); ?>]"
                                    placeholder="<?php echo esc_attr($field['placeholder']); ?>"
                                    <?php echo $required ? 'required' : ''; ?>
                                >
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="md-form-actions">
                    <button type="submit" class="btn btn-primary md-form-submit"><?php echo esc_html($attributes['submitLabel']); ?></button>
                    <p class="md-form-privacy">اطلاعات شما فقط برای هماهنگی جلسه استفاده می‌شود.</p>
                </div>
            </form>
        <?php endif; ?>
    </section>
    <?php
    return ob_get_clean();
}

/**
 * Sanitize one submitted value according to the field type.
 *
 * @param mixed $value Raw value.
 * @param array $field Field definition.
 * @return string
 */
function md_request_sanitize_value($value, $field) {
    $value = is_scalar($value) ? wp_unslash($value) : '';

    switch ($field['type']) {
        case 'email':
            return sanitize_email($value);
        case 'url':
            return esc_url_raw($value);
        case 'textarea':
            return sanitize_textarea_field($value);
        case 'number':
            return preg_replace('/[^0-9۰-۹٠-٩]/u', '', $value);
        case 'date':
            $value = trim(md_normalize_digits($value));
            $value = preg_replace('/[\s\.\-]+/', '/', $value);
            $value = preg_replace('#/+#', '/', $value);
            if ('' === $value || !md_parse_jalali($value)) {
                return '';
            }
            return md_fa_num($value);
        case 'select':
            $value   = sanitize_text_field($value);
            $options = md_request_field_options($field);
            return in_array($value, $options, true) ? $value : '';
        default:
            return sanitize_text_field($value);
    }
}

/**
 * Redirect back to the form with a status flag.
 *
 * @param string $url    Base URL.
 * @param string $status success|error.
 * @param array  $args   Extra query args.
 */
function md_request_redirect($url, $status, $args = array()) {
    $args = array_merge(array('md_request' => $status), $args);
    wp_safe_redirect(add_query_arg($args, $url) . '#md-request-form');
    exit;
}

/**
 * Handle the submitted request form.
 */
function md_request_handle_submit() {
    $page_id  = isset($_POST['md_request_page']) ? absint($_POST['md_request_page']) : 0;
    $url      = $page_id ? get_permalink($page_id) : home_url('/');
    $redirect = $url ? $url : home_url('/');

    if (!isset($_POST['md_request_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['md_request_nonce'])), 'md_request_submit')) {
        md_request_redirect($redirect, 'error', array('md_reason' => 'expired'));
    }

    // Honeypot: bots fill hidden fields, humans never see them.
    if (!empty($_POST['md_website'])) {
        md_request_redirect($redirect, 'success');
    }

    $attributes = md_request_page_attributes($page_id);
    $fields     = isset($attributes['fields']) && is_array($attributes['fields']) ? $attributes['fields'] : md_request_default_fields();
    $fields     = array_map('md_request_normalize_field', $fields);
    $fields     = array_values(array_filter($fields, function ($field) {
        return '' !== $field['label'];
    }));
    if (!$fields) {
        $fields = array_map('md_request_normalize_field', md_request_default_fields());
    }

    $values  = isset($_POST['md_field']) && is_array($_POST['md_field']) ? wp_unslash($_POST['md_field']) : array();
    $entries = array();

    foreach ($fields as $index => $field) {
        $raw   = isset($values[$index]) ? $values[$index] : '';
        $value = md_request_sanitize_value($raw, $field);

        if ($field['required'] && '' === $value) {
            md_request_redirect($redirect, 'error', array('md_reason' => 'required', 'md_field' => $field['label']));
        }
        if ('email' === $field['type'] && '' !== $value && !is_email($value)) {
            md_request_redirect($redirect, 'error', array('md_reason' => 'email'));
        }
        if ('date' === $field['type'] && '' === $value && is_scalar($raw) && '' !== trim((string) $raw)) {
            md_request_redirect($redirect, 'error', array('md_reason' => 'date', 'md_field' => $field['label']));
        }

        $entries[] = array(
            'label' => $field['label'],
            'type'  => $field['type'],
            'value' => $value,
        );
    }

    $service_id = isset($_POST['md_request_service']) ? absint($_POST['md_request_service']) : 0;
    $service    = $service_id ? get_the_title($service_id) : '';

    $name = '';
    $email = '';
    $phone = '';
    foreach ($entries as $entry) {
        if ('' === $entry['value']) {
            continue;
        }
        if ('' === $name && in_array($entry['type'], array('text'), true)) {
            $name = $entry['value'];
        }
        if ('' === $email && 'email' === $entry['type']) {
            $email = $entry['value'];
        }
        if ('' === $phone && 'tel' === $entry['type']) {
            $phone = $entry['value'];
        }
    }

    $title = 'درخواست — ' . ($name ? $name : ($service ? $service : 'تازه'));
    $request_id = wp_insert_post(
        array(
            'post_type'   => 'md_request',
            'post_status' => 'private',
            'post_title'  => $title,
        ),
        true
    );

    if (is_wp_error($request_id) || !$request_id) {
        md_request_redirect($redirect, 'error');
    }

    update_post_meta($request_id, '_md_request_entries', $entries);
    update_post_meta($request_id, '_md_request_page', $page_id);
    if ($service_id) {
        update_post_meta($request_id, '_md_request_service', $service_id);
    }
    if ($email) {
        update_post_meta($request_id, '_md_request_email', $email);
    }
    if ($phone) {
        update_post_meta($request_id, '_md_request_phone', $phone);
    }

    $lines = array('درخواست تازه از ' . $title, '');
    if ($service) {
        $lines[] = 'خدمت: ' . $service;
    }
    foreach ($entries as $entry) {
        if ('' !== $entry['value']) {
            $lines[] = $entry['label'] . ': ' . $entry['value'];
        }
    }
    $lines[] = '';
    $lines[] = 'مدیریت درخواست‌ها: ' . admin_url('edit.php?post_type=md_request');

    wp_mail(
        get_option('admin_email'),
        'درخواست تازه — ' . get_bloginfo('name'),
        implode("\n", $lines)
    );

    md_request_redirect($redirect, 'success');
}
add_action('admin_post_md_request_submit', 'md_request_handle_submit');
add_action('admin_post_nopriv_md_request_submit', 'md_request_handle_submit');

/* --------------------------------------------------------------- admin side */

/**
 * Detail box for a stored request.
 *
 * @param WP_Post $post Request post.
 */
function md_request_render_details($post) {
    $entries = get_post_meta($post->ID, '_md_request_entries', true);
    $service = absint(get_post_meta($post->ID, '_md_request_service', true));
    $page    = absint(get_post_meta($post->ID, '_md_request_page', true));
    $email   = get_post_meta($post->ID, '_md_request_email', true);
    $phone   = get_post_meta($post->ID, '_md_request_phone', true);

    echo '<table class="widefat striped md-request-details">';
    if ($service) {
        echo '<tr><th>خدمت</th><td><a href="' . esc_url(get_edit_post_link($service)) . '">' . esc_html(get_the_title($service)) . '</a></td></tr>';
    }
    if ($page) {
        echo '<tr><th>فرم</th><td><a href="' . esc_url(get_permalink($page)) . '">' . esc_html(get_the_title($page)) . '</a></td></tr>';
    }
    if ($email) {
        echo '<tr><th>ایمیل</th><td dir="ltr"><a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a></td></tr>';
    }
    if ($phone) {
        echo '<tr><th>تماس</th><td dir="ltr">' . esc_html($phone) . '</td></tr>';
    }
    if (is_array($entries)) {
        foreach ($entries as $entry) {
            if (!isset($entry['label']) || '' === trim((string) $entry['value'])) {
                continue;
            }
            echo '<tr><th>' . esc_html($entry['label']) . '</th><td>' . nl2br(esc_html($entry['value'])) . '</td></tr>';
        }
    }
    echo '</table>';
}

/**
 * Register the request detail box.
 */
function md_request_add_details_box() {
    add_meta_box('md_request_details', 'جزئیات درخواست', 'md_request_render_details', 'md_request', 'normal', 'high');
}
add_action('add_meta_boxes', 'md_request_add_details_box');

/**
 * Columns for the requests list.
 *
 * @param array $columns Columns.
 * @return array
 */
function md_request_columns($columns) {
    return array(
        'cb'         => isset($columns['cb']) ? $columns['cb'] : '',
        'title'      => 'درخواست‌دهنده',
        'md_service' => 'خدمت',
        'md_contact' => 'تماس',
        'md_email'   => 'ایمیل',
        'date'       => 'تاریخ',
    );
}
add_filter('manage_md_request_posts_columns', 'md_request_columns');

/**
 * Render request list columns.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 */
function md_request_column_content($column, $post_id) {
    if ('md_service' === $column) {
        $service = absint(get_post_meta($post_id, '_md_request_service', true));
        echo $service ? esc_html(get_the_title($service)) : '—';
    } elseif ('md_contact' === $column) {
        echo esc_html(get_post_meta($post_id, '_md_request_phone', true) ?: '—');
    } elseif ('md_email' === $column) {
        $email = get_post_meta($post_id, '_md_request_email', true);
        echo $email ? '<a href="mailto:' . esc_attr($email) . '" dir="ltr">' . esc_html($email) . '</a>' : '—';
    }
}
add_action('manage_md_request_posts_custom_column', 'md_request_column_content', 10, 2);

/* ------------------------------------------------------------ request page */

/**
 * ID of the page that holds the request form.
 *
 * @return int
 */
function md_request_page_id() {
    return absint(get_option('md_request_page_id'));
}

/**
 * Whether a page can still host the form.
 *
 * A trashed page keeps its ID in the option, so status alone is not enough.
 *
 * @param int $page_id Page ID.
 * @return bool
 */
function md_request_page_is_live($page_id) {
    $page_id = absint($page_id);
    if (!$page_id) {
        return false;
    }

    $status = get_post_status($page_id);
    return $status && 'trash' !== $status;
}

/**
 * URL of the request form page.
 *
 * @return string
 */
function md_request_page_url() {
    $page_id = md_request_page_id();
    if (md_request_page_is_live($page_id)) {
        return get_permalink($page_id);
    }

    return home_url('/request/');
}

/**
 * Default "book a session" URL used by the header, the homepage and courses.
 *
 * The customizer value still wins, so existing sites keep their custom link.
 *
 * @return string
 */
function md_booking_url() {
    $custom = get_theme_mod('md_booking_url', '');
    if ($custom) {
        return $custom;
    }

    return md_request_page_url();
}

/**
 * Create the request page once so the service buttons always have a target.
 */
function md_create_request_page() {
    $existing = md_request_page_id();
    if (md_request_page_is_live($existing)) {
        return;
    }

    // Adopt a page an editor already made, but never reuse a trashed one.
    $page = get_page_by_path('request');
    if ($page instanceof WP_Post && md_request_page_is_live($page->ID)) {
        update_option('md_request_page_id', $page->ID);
        return;
    }

    $page_id = wp_insert_post(
        array(
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => 'فرم ثبت درخواست',
            'post_name'    => 'request',
            'post_content' => '<!-- wp:morvarid/request-form /-->',
        ),
        true
    );

    if (!is_wp_error($page_id) && $page_id) {
        update_option('md_request_page_id', $page_id);
    }
}
add_action('after_switch_theme', 'md_create_request_page');

/**
 * Make sure the page exists on older installations too.
 */
function md_maybe_create_request_page() {
    if (md_request_page_is_live(md_request_page_id())) {
        return;
    }
    if (!current_user_can('manage_options')) {
        return;
    }
    md_create_request_page();
}
add_action('admin_init', 'md_maybe_create_request_page');
