<?php
/**
 * Challenge data helpers.
 *
 * @package Morvarid_Daron
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get a challenge meta value.
 *
 * @param int    $challenge_id Challenge ID.
 * @param string $key          Meta key.
 * @param mixed  $default      Default.
 * @return mixed
 */
function md_challenge_meta($challenge_id, $key, $default = '') {
    $value = get_post_meta($challenge_id, $key, true);
    return ($value === '' || $value === null) ? $default : $value;
}

/**
 * Challenge start date.
 *
 * @param int $challenge_id Challenge ID.
 * @return string
 */
function md_challenge_start($challenge_id) {
    return md_sanitize_date(md_challenge_meta($challenge_id, '_md_start_date', ''));
}

/**
 * Challenge end date.
 *
 * @param int $challenge_id Challenge ID.
 * @return string
 */
function md_challenge_end($challenge_id) {
    return md_sanitize_date(md_challenge_meta($challenge_id, '_md_end_date', ''));
}

/**
 * Access mode of a challenge.
 *
 * @param int $challenge_id Challenge ID.
 * @return string "all" or "schedule".
 */
function md_challenge_access_mode($challenge_id) {
    $mode = md_challenge_meta($challenge_id, '_md_access_mode', 'schedule');
    return in_array($mode, array('all', 'schedule'), true) ? $mode : 'schedule';
}

/**
 * Whether locked day titles are visible.
 *
 * @param int $challenge_id Challenge ID.
 * @return bool
 */
function md_challenge_show_locked_title($challenge_id) {
    return (bool) md_challenge_meta($challenge_id, '_md_show_locked_title', 1);
}

/**
 * Calendar day count of a challenge.
 *
 * @param int $challenge_id Challenge ID.
 * @return int
 */
function md_challenge_calendar_days($challenge_id) {
    return md_calendar_days(md_challenge_start($challenge_id), md_challenge_end($challenge_id));
}

/**
 * Get challenge days in display order.
 *
 * @param int  $challenge_id         Challenge ID.
 * @param bool $include_unpublished  Include drafts for editors.
 * @return WP_Post[]
 */
function md_challenge_days($challenge_id, $include_unpublished = false) {
    $challenge_id = absint($challenge_id);
    if (!$challenge_id) {
        return array();
    }

    $status = $include_unpublished ? array('publish', 'draft', 'pending', 'private') : 'publish';

    return get_posts(array(
        'post_type'      => 'md_challenge_day',
        'posts_per_page' => -1,
        'post_status'    => $status,
        'meta_key'       => '_md_day_order',
        'orderby'        => array('meta_value_num' => 'ASC', 'ID' => 'ASC'),
        'meta_query'     => array(
            array(
                'key'     => '_md_challenge_id',
                'value'   => $challenge_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ),
        ),
        'no_found_rows'  => true,
        'ignore_sticky_posts' => true,
    ));
}

/**
 * Get a single day post.
 *
 * @param int $day_id Day ID.
 * @return WP_Post|null
 */
function md_challenge_day($day_id) {
    $day = get_post(absint($day_id));
    if (!$day || 'md_challenge_day' !== $day->post_type) {
        return null;
    }
    return $day;
}

/**
 * Day order (1-based).
 *
 * @param int $day_id Day ID.
 * @return int
 */
function md_challenge_day_order($day_id) {
    $order = absint(get_post_meta($day_id, '_md_day_order', true));
    return $order ? $order : 1;
}

/**
 * Content completion status, independent from the WordPress publish status.
 *
 * @param WP_Post $day Day post.
 * @return array{key:string,label:string}
 */
function md_challenge_day_content_status($day) {
    if (!$day instanceof WP_Post) {
        return array('key' => 'incomplete', 'label' => 'تکمیل‌نشده');
    }

    $title   = trim(wp_strip_all_tags($day->post_title));
    $content = trim(wp_strip_all_tags($day->post_content));

    if ('' === $title && '' === $content) {
        return array('key' => 'incomplete', 'label' => 'تکمیل‌نشده');
    }
    if ('' === $title || '' === $content) {
        return array('key' => 'in-progress', 'label' => 'در حال تکمیل');
    }
    return array('key' => 'ready', 'label' => 'آماده انتشار');
}

/**
 * Publish status label of a day.
 *
 * @param WP_Post $day Day post.
 * @return string
 */
function md_challenge_day_publish_label($day) {
    if (!$day instanceof WP_Post) {
        return '';
    }
    $object = get_post_status_object($day->post_status);
    return $object ? $object->label : $day->post_status;
}

/**
 * Whether the current user can manage a challenge.
 *
 * @param int $challenge_id Challenge ID.
 * @return bool
 */
function md_challenge_is_editor($challenge_id) {
    return $challenge_id && current_user_can('edit_post', $challenge_id);
}

/**
 * Whether a day is unlocked for the public.
 *
 * Editors always see everything so they can preview their work.
 *
 * @param WP_Post $day          Day post.
 * @param int     $challenge_id Challenge ID. Optional.
 * @return bool
 */
function md_challenge_day_is_unlocked($day, $challenge_id = 0) {
    if (!$day instanceof WP_Post) {
        return false;
    }
    $challenge_id = $challenge_id ? $challenge_id : absint(get_post_meta($day->ID, '_md_challenge_id', true));
    if (md_challenge_is_editor($challenge_id)) {
        return true;
    }
    if ('publish' !== $day->post_status) {
        return false;
    }
    if ('all' === md_challenge_access_mode($challenge_id)) {
        return true;
    }

    $start = md_challenge_start($challenge_id);
    if (!$start) {
        return true;
    }

    $order  = md_challenge_day_order($day->ID);
    $unlock = md_date_add_days($start, $order - 1);
    return md_today() >= $unlock;
}

/**
 * Date a locked day opens.
 *
 * @param WP_Post $day          Day post.
 * @param int     $challenge_id Challenge ID. Optional.
 * @return string Y-m-d or empty.
 */
function md_challenge_day_unlock_date($day, $challenge_id = 0) {
    if (!$day instanceof WP_Post) {
        return '';
    }
    $challenge_id = $challenge_id ? $challenge_id : absint(get_post_meta($day->ID, '_md_challenge_id', true));
    if ('all' === md_challenge_access_mode($challenge_id)) {
        return '';
    }
    $start = md_challenge_start($challenge_id);
    if (!$start) {
        return '';
    }
    return md_date_add_days($start, md_challenge_day_order($day->ID) - 1);
}

/**
 * Challenge statistics.
 *
 * @param int $challenge_id Challenge ID.
 * @return array
 */
function md_challenge_stats($challenge_id) {
    $days      = md_challenge_days($challenge_id, md_challenge_is_editor($challenge_id));
    $total     = count($days);
    $published = 0;
    $ready     = 0;
    $unlocked  = 0;

    foreach ($days as $day) {
        if ('publish' === $day->post_status) {
            $published++;
        }
        $status = md_challenge_day_content_status($day);
        if ('ready' === $status['key']) {
            $ready++;
        }
        if (md_challenge_day_is_unlocked($day, $challenge_id)) {
            $unlocked++;
        }
    }

    return array(
        'total'     => $total,
        'published' => $published,
        'ready'     => $ready,
        'unlocked'  => $unlocked,
        'calendar'  => md_challenge_calendar_days($challenge_id),
        'percent'   => $total ? (int) round(($unlocked / $total) * 100) : 0,
    );
}

/**
 * Time status of a challenge.
 *
 * @param int $challenge_id Challenge ID.
 * @return string upcoming|active|ended|unknown
 */
function md_challenge_time_status($challenge_id) {
    $start = md_challenge_start($challenge_id);
    $end   = md_challenge_end($challenge_id);
    if (!$start || !$end) {
        return 'unknown';
    }
    $today = md_today();
    if ($today < $start) {
        return 'upcoming';
    }
    if ($today > $end) {
        return 'ended';
    }
    return 'active';
}

/**
 * Human label for the time status.
 *
 * @param int $challenge_id Challenge ID.
 * @return string
 */
function md_challenge_time_label($challenge_id) {
    $labels = array(
        'upcoming' => 'به‌زودی',
        'active'   => 'در حال اجرا',
        'ended'    => 'پایان یافته',
        'unknown'  => '',
    );
    return $labels[md_challenge_time_status($challenge_id)];
}

/**
 * Current day number while the challenge is running.
 *
 * @param int $challenge_id Challenge ID.
 * @return int Zero when not running.
 */
function md_challenge_current_day($challenge_id) {
    if ('active' !== md_challenge_time_status($challenge_id)) {
        return 0;
    }
    $start = md_date_to_object(md_challenge_start($challenge_id));
    $today = md_date_to_object(md_today());
    if (!$start || !$today) {
        return 0;
    }
    return (int) $start->diff($today)->days + 1;
}

/**
 * Next free day order.
 *
 * @param int $challenge_id Challenge ID.
 * @return int
 */
function md_challenge_next_day_order($challenge_id) {
    $days = md_challenge_days($challenge_id, true);
    if (!$days) {
        return 1;
    }
    $max = 0;
    foreach ($days as $day) {
        $order = md_challenge_day_order($day->ID);
        if ($order > $max) {
            $max = $order;
        }
    }
    return $max + 1;
}

/**
 * Renumber days from 1 to N and refresh stored counts.
 *
 * @param int $challenge_id Challenge ID.
 */
function md_challenge_normalize_day_orders($challenge_id) {
    $days = md_challenge_days($challenge_id, true);
    foreach ($days as $index => $day) {
        update_post_meta($day->ID, '_md_day_order', $index + 1);
    }
    md_challenge_sync_counts($challenge_id);
}

/**
 * Shift day orders at or after a given order.
 *
 * @param int $challenge_id Challenge ID.
 * @param int $from_order   First order to shift.
 * @param int $amount       Amount to add.
 */
function md_challenge_shift_day_orders($challenge_id, $from_order, $amount = 1) {
    $days = md_challenge_days($challenge_id, true);
    foreach ($days as $day) {
        $order = md_challenge_day_order($day->ID);
        if ($order >= $from_order) {
            update_post_meta($day->ID, '_md_day_order', $order + $amount);
        }
    }
}

/**
 * Sync stored day counters on the challenge.
 *
 * @param int $challenge_id Challenge ID.
 */
function md_challenge_sync_counts($challenge_id) {
    $challenge_id = absint($challenge_id);
    if (!$challenge_id) {
        return;
    }
    update_post_meta($challenge_id, '_md_content_days', count(md_challenge_days($challenge_id, true)));
    update_post_meta($challenge_id, '_md_calendar_days', md_challenge_calendar_days($challenge_id));
}

/**
 * Q&A items of a day.
 *
 * @param int $day_id Day ID.
 * @return array
 */
function md_challenge_day_qa($day_id) {
    $raw = get_post_meta($day_id, '_md_day_qa', true);
    if (is_array($raw)) {
        return $raw;
    }
    if (!is_string($raw) || '' === $raw) {
        return array();
    }
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        return $decoded;
    }
    $unserialized = maybe_unserialize($raw);
    return is_array($unserialized) ? $unserialized : array();
}

/**
 * Challenge categories as HTML.
 *
 * @param int    $challenge_id Challenge ID.
 * @param string $separator    Separator.
 * @return string
 */
function md_challenge_categories($challenge_id, $separator = '، ') {
    $terms = get_the_terms($challenge_id, 'md_challenge_cat');
    if (!$terms || is_wp_error($terms)) {
        return '';
    }
    $names = wp_list_pluck($terms, 'name');
    return implode($separator, $names);
}

/**
 * Query challenges.
 *
 * @param array $args Query arguments.
 * @return WP_Query
 */
function md_challenge_query($args = array()) {
    $defaults = array(
        'post_type'      => 'md_challenge',
        'post_status'    => 'publish',
        'posts_per_page' => 3,
        'meta_key'       => '_md_start_date',
        'orderby'        => 'meta_value',
        'order'          => 'DESC',
        'no_found_rows'  => true,
    );
    return new WP_Query(wp_parse_args($args, $defaults));
}

/**
 * Label for the publish status of a challenge.
 *
 * @param int $challenge_id Challenge ID.
 * @return string
 */
function md_challenge_publish_label($challenge_id) {
    $status = get_post_status($challenge_id);
    $object = get_post_status_object($status);
    return $object ? $object->label : $status;
}
