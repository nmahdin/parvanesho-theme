<?php
/**
 * Calendar day preparation. Day authoring now happens entirely in the advanced modal.
 *
 * @package Morvarid_Daron
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fill missing calendar slots with editable draft posts. Never modify or
 * destroy existing lessons when dates or duration change.
 */
function md_challenge_ensure_calendar_days($challenge_id) {
    $total = md_challenge_calendar_days($challenge_id);
    if ($total < 1 || $total > 120) {
        return;
    }

    $existing = md_challenge_days($challenge_id, true);
    $taken = array();
    foreach ($existing as $day) {
        $taken[md_challenge_day_order($day->ID)] = true;
    }
    for ($i = 1; $i <= $total; $i++) {
        if (isset($taken[$i])) {
            continue;
        }
        $id = wp_insert_post(array(
            'post_type' => 'md_challenge_day',
            'post_status' => 'draft',
            'post_title' => 'روز ' . md_fa_num($i),
            'post_content' => '',
        ), true);
        if (is_wp_error($id) || !$id) {
            break;
        }
        update_post_meta($id, '_md_challenge_id', $challenge_id);
        update_post_meta($id, '_md_day_order', $i);
    }
}

