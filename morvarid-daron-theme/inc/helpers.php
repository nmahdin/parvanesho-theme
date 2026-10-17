<?php
/**
 * Shared helpers: Persian digits, Jalali dates and small formatting utilities.
 *
 * @package Morvarid_Daron
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Convert Persian/Arabic digits to Latin digits.
 *
 * @param string $value Input value.
 * @return string
 */
function md_normalize_digits($value) {
    $value = (string) $value;
    return strtr($value, array(
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ));
}

/**
 * Convert Latin digits to Persian digits.
 *
 * @param string|int $value Input value.
 * @return string
 */
function md_fa_num($value) {
    return strtr((string) $value, array(
        '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
        '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
    ));
}

/**
 * Sanitize a Gregorian date and return it as Y-m-d, or an empty string.
 *
 * @param string $value Raw date.
 * @return string
 */
function md_sanitize_date($value) {
    $value = trim(md_normalize_digits($value));
    if ($value === '') {
        return '';
    }
    $value = str_replace(array('/', '.'), '-', $value);
    if (!preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $matches)) {
        return '';
    }
    $year  = (int) $matches[1];
    $month = (int) $matches[2];
    $day   = (int) $matches[3];
    if ($year < 1900 || $year > 2100 || !checkdate($month, $day, $year)) {
        return '';
    }
    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

/**
 * Parse a Y-m-d date into a UTC DateTimeImmutable.
 *
 * @param string $value Date.
 * @return DateTimeImmutable|false
 */
function md_date_to_object($value) {
    $value = md_sanitize_date($value);
    if ($value === '') {
        return false;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
    return $date instanceof DateTimeImmutable ? $date : false;
}

/**
 * Number of calendar days between two dates, inclusive.
 *
 * @param string $start Start date Y-m-d.
 * @param string $end   End date Y-m-d.
 * @return int Zero when either date is invalid.
 */
function md_calendar_days($start, $end) {
    $from = md_date_to_object($start);
    $to   = md_date_to_object($end);
    if (!$from || !$to || $to < $from) {
        return 0;
    }
    return (int) $from->diff($to)->days + 1;
}

/**
 * Add days to a Y-m-d date.
 *
 * @param string $date Date.
 * @param int    $days Number of days.
 * @return string
 */
function md_date_add_days($date, $days) {
    $object = md_date_to_object($date);
    if (!$object) {
        return '';
    }
    $days = (int) $days;
    return $object->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
}

/**
 * Today in the site timezone.
 *
 * @return string
 */
function md_today() {
    return current_time('Y-m-d');
}

/**
 * Convert a Gregorian date to Jalali.
 *
 * Based on the well-known jalaali algorithm.
 *
 * @param int $gy Gregorian year.
 * @param int $gm Gregorian month.
 * @param int $gd Gregorian day.
 * @return array [year, month, day]
 */
function md_gregorian_to_jalali($gy, $gm, $gd) {
    $gy = (int) $gy;
    $gm = (int) $gm;
    $gd = (int) $gd;

    $g_days_in_month = array(0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334);
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;

    $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100)
        + intdiv($gy2 + 399, 400) + $gd + $g_days_in_month[$gm - 1];

    $jy = -1595 + (33 * intdiv($days, 12053));
    $days %= 12053;

    $jy += 4 * intdiv($days, 1461);
    $days %= 1461;

    if ($days > 365) {
        $jy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }

    if ($days < 186) {
        $jm = 1 + intdiv($days, 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + intdiv($days - 186, 30);
        $jd = 1 + (($days - 186) % 30);
    }

    return array($jy, $jm, $jd);
}

/**
 * Persian month names.
 *
 * @return array
 */
function md_jalali_months() {
    return array(
        1  => 'فروردین',
        2  => 'اردیبهشت',
        3  => 'خرداد',
        4  => 'تیر',
        5  => 'مرداد',
        6  => 'شهریور',
        7  => 'مهر',
        8  => 'آبان',
        9  => 'آذر',
        10 => 'دی',
        11 => 'بهمن',
        12 => 'اسفند',
    );
}

/**
 * Format a Gregorian Y-m-d date as a Jalali date.
 *
 * @param string $date Gregorian date.
 * @param string $style "long" or "short".
 * @return string Empty string when the date is invalid.
 */
function md_format_jalali($date, $style = 'long') {
    $object = md_date_to_object($date);
    if (!$object) {
        return '';
    }

    list($jy, $jm, $jd) = md_gregorian_to_jalali(
        (int) $object->format('Y'),
        (int) $object->format('n'),
        (int) $object->format('j')
    );

    if ('short' === $style) {
        return md_fa_num(sprintf('%04d/%02d/%02d', $jy, $jm, $jd));
    }

    $months = md_jalali_months();
    return md_fa_num($jd) . ' ' . $months[$jm] . ' ' . md_fa_num($jy);
}

/**
 * Human readable attachment file name.
 *
 * @param int $attachment_id Attachment ID.
 * @return string
 */
function md_attachment_name($attachment_id) {
    $attachment_id = absint($attachment_id);
    if (!$attachment_id) {
        return '';
    }
    $file = get_attached_file($attachment_id);
    if ($file) {
        return wp_basename($file);
    }
    return get_the_title($attachment_id);
}

/**
 * Trim text to a given number of words without breaking UTF-8.
 *
 * @param string $text  Text.
 * @param int    $count Words.
 * @return string
 */
function md_trim_text($text, $count = 18) {
    $text = wp_strip_all_tags((string) $text);
    return wp_trim_words($text, max(1, (int) $count), '…');
}
