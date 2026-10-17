<?php
/**
 * Jalali (Shamsi) date engine.
 *
 * - Converts Jalali input to the Gregorian Y-m-d values stored in post meta.
 * - Converts every front-end date (posts, comments, archives, widgets) to the
 *   Jalali calendar, with Persian digits, through the wp_date filter.
 * - Renders the reusable Jalali date field used by the challenge editor.
 *
 * @package Morvarid_Daron
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Persian weekday names, indexed like PHP's date('w') (Sunday = 0).
 *
 * @return array
 */
function md_weekday_names() {
    return array(
        0 => 'یکشنبه',
        1 => 'دوشنبه',
        2 => 'سه‌شنبه',
        3 => 'چهارشنبه',
        4 => 'پنجشنبه',
        5 => 'جمعه',
        6 => 'شنبه',
    );
}

/**
 * Short Persian weekday names.
 *
 * @return array
 */
function md_weekday_short_names() {
    return array(
        0 => 'یک',
        1 => 'دو',
        2 => 'سه',
        3 => 'چهار',
        4 => 'پنج',
        5 => 'جمعه',
        6 => 'شنبه',
    );
}

/**
 * Convert a Jalali date to Gregorian.
 *
 * @param int $jy Jalali year.
 * @param int $jm Jalali month.
 * @param int $jd Jalali day.
 * @return array [gy, gm, gd]
 */
function md_jalali_to_gregorian($jy, $jm, $jd) {
    $jy = (int) $jy + 1595;
    $jm = (int) $jm;
    $jd = (int) $jd;

    $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8) + intdiv(($jy % 33) + 3, 4) + $jd
        + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);

    $gy = 400 * intdiv($days, 146097);
    $days %= 146097;
    if ($days > 36524) {
        $gy += 100 * intdiv(--$days, 36524);
        $days %= 36524;
        if ($days >= 365) {
            $days++;
        }
    }
    $gy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
        $gy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }

    $gd = $days + 1;
    $leap = (0 === $gy % 4 && 0 !== $gy % 100) || 0 === $gy % 400;
    $month_days = array(0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);

    $gm = 0;
    while ($gm < 13 && $gd > $month_days[$gm]) {
        $gd -= $month_days[$gm];
        $gm++;
    }

    return array($gy, $gm, $gd);
}

/**
 * Number of days in a Jalali month.
 *
 * @param int $jy Jalali year.
 * @param int $jm Jalali month.
 * @return int
 */
function md_jalali_month_days($jy, $jm) {
    $jy = (int) $jy;
    $jm = (int) $jm;
    if ($jm < 1 || $jm > 12) {
        return 0;
    }
    if ($jm <= 6) {
        return 31;
    }
    if ($jm <= 11) {
        return 30;
    }

    // Esfand: compare the start of the next year with the start of this year.
    $this_year = md_jalali_to_gregorian($jy, 1, 1);
    $next_year = md_jalali_to_gregorian($jy + 1, 1, 1);
    $from  = gmmktime(0, 0, 0, $this_year[1], $this_year[2], $this_year[0]);
    $to    = gmmktime(0, 0, 0, $next_year[1], $next_year[2], $next_year[0]);
    $total = (int) round(($to - $from) / DAY_IN_SECONDS);

    return 366 === $total ? 30 : 29;
}

/**
 * Convert a Gregorian Y-m-d date to Jalali parts.
 *
 * @param string $date Gregorian date.
 * @return array|null [jy, jm, jd] or null.
 */
function md_ymd_to_jalali($date) {
    $date = md_sanitize_date($date);
    if ('' === $date) {
        return null;
    }
    list($gy, $gm, $gd) = array_map('intval', explode('-', $date));
    return md_gregorian_to_jalali($gy, $gm, $gd);
}

/**
 * Parse a Jalali date string into a Gregorian Y-m-d value.
 *
 * Accepts Persian/Arabic digits and "/", "-", "." or spaces as separators.
 * Returns an empty string when the value is not a valid Jalali date.
 *
 * @param string $value Raw input such as ۱۴۰۴/۰۷/۲۵.
 * @return string
 */
function md_parse_jalali($value) {
    $value = trim(md_normalize_digits((string) $value));
    if ('' === $value) {
        return '';
    }

    $value = preg_replace('/[\s\/\.\-]+/', '-', $value);
    if (!preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $matches)) {
        return '';
    }

    $jy = (int) $matches[1];
    $jm = (int) $matches[2];
    $jd = (int) $matches[3];

    if ($jy < 1200 || $jy > 1700 || $jm < 1 || $jm > 12) {
        return '';
    }
    if ($jd < 1 || $jd > md_jalali_month_days($jy, $jm)) {
        return '';
    }

    list($gy, $gm, $gd) = md_jalali_to_gregorian($jy, $jm, $jd);
    if (!checkdate($gm, $gd, $gy)) {
        return '';
    }

    return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
}

/**
 * Format a Jalali date with Persian digits.
 *
 * @param string $date Gregorian Y-m-d date.
 * @param string $style "long", "short" or "numeric".
 * @return string
 */
function md_jalali_string($date, $style = 'long') {
    $parts = md_ymd_to_jalali($date);
    if (!$parts) {
        return '';
    }
    list($jy, $jm, $jd) = $parts;

    if ('numeric' === $style) {
        return md_fa_num(sprintf('%04d/%02d/%02d', $jy, $jm, $jd));
    }
    if ('short' === $style) {
        $months = md_jalali_months();
        return md_fa_num($jd) . ' ' . $months[$jm];
    }

    $months = md_jalali_months();
    return md_fa_num($jd) . ' ' . $months[$jm] . ' ' . md_fa_num($jy);
}

/**
 * Return the Jalali parts of the current moment.
 *
 * @param string|null $timezone Timezone name.
 * @return array [jy, jm, jd]
 */
function md_jalali_today($timezone = null) {
    $now = new DateTimeImmutable('now', $timezone ? new DateTimeZone($timezone) : wp_timezone());
    return md_gregorian_to_jalali((int) $now->format('Y'), (int) $now->format('n'), (int) $now->format('j'));
}

/**
 * Format a timestamp using a PHP date format but Jalali year/month/day.
 *
 * Supports the usual display tokens (Y y F M m n d j l D H G h g i s a A U)
 * and the backslash escape. Unknown tokens are kept untouched.
 *
 * @param int           $timestamp Unix timestamp.
 * @param string        $format    PHP date format.
 * @param DateTimeZone|null $timezone Timezone.
 * @return string
 */
function md_format_timestamp_jalali($timestamp, $format = '', $timezone = null) {
    $timestamp = (int) $timestamp;
    $format    = (string) $format;

    if ('' === $format) {
        $format = 'j F Y';
    }

    $timezone = $timezone instanceof DateTimeZone ? $timezone : wp_timezone();
    $date     = (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone);

    list($jy, $jm, $jd) = md_gregorian_to_jalali(
        (int) $date->format('Y'),
        (int) $date->format('n'),
        (int) $date->format('j')
    );

    $months   = md_jalali_months();
    $weekdays = md_weekday_names();
    $short    = md_weekday_short_names();
    $weekday  = (int) $date->format('w');

    $tokens = array(
        'Y' => sprintf('%04d', $jy),
        'y' => substr(sprintf('%04d', $jy), -2),
        'm' => sprintf('%02d', $jm),
        'n' => (string) $jm,
        'F' => $months[$jm],
        'M' => $months[$jm],
        'd' => sprintf('%02d', $jd),
        'j' => (string) $jd,
        'l' => $weekdays[$weekday],
        'D' => $short[$weekday],
        'H' => $date->format('H'),
        'G' => $date->format('G'),
        'h' => $date->format('h'),
        'g' => $date->format('g'),
        'i' => $date->format('i'),
        's' => $date->format('s'),
        'a' => 'ق.ظ',
        'A' => 'ب.ظ',
        'U' => $date->format('U'),
        'S' => '',
        'T' => $date->format('T'),
        'e' => $date->format('e'),
    );
    // Time zone name in Persian is not needed; avoid leaking English abbreviations.
    $tokens['T'] = '';
    $tokens['e'] = '';

    $output = preg_replace_callback(
        '/\\\\(.)|([a-zA-Z])/',
        function ($matches) use ($tokens) {
            if (!empty($matches[1])) {
                return $matches[1];
            }
            $token = $matches[2];
            return array_key_exists($token, $tokens) ? $tokens[$token] : $token;
        },
        $format
    );

    return md_fa_num($output);
}

/**
 * Whether a date format is meant for machines (feeds, ISO, REST) and must not
 * be converted to Jalali.
 *
 * @param string $format PHP date format.
 * @return bool
 */
function md_is_machine_date_format($format) {
    $format = (string) $format;
    $fixed  = array(
        'c',
        'r',
        'U',
        'Y-m-d',
        'Ymd',
        'Y-m-d H:i:s',
        'Y-m-d\TH:i:s',
        'Y-m-d\TH:i:sP',
        'D, d M Y H:i:s',
        'D, d M Y H:i:s O',
    );

    if (in_array($format, $fixed, true)) {
        return true;
    }

    // Formats that combine numeric date and time tokens are storage formats.
    return (bool) preg_match('/^[YymdHisTO:\-\.\/\\\\ ]+$/', $format) && (bool) preg_match('/[HisT]/', $format);
}

/**
 * Filter wp_date so every human readable date on the front end is Jalali.
 *
 * @param string        $date      Formatted date.
 * @param string        $format    PHP date format.
 * @param int           $timestamp Unix timestamp.
 * @param DateTimeZone  $timezone  Timezone.
 * @return string
 */
function md_filter_wp_date_jalali($date, $format, $timestamp, $timezone) {
    if (is_admin() || is_feed() || wp_doing_ajax() || wp_doing_cron()) {
        return $date;
    }
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return $date;
    }
    if (md_is_machine_date_format($format)) {
        return $date;
    }

    // The site's default date format may be an English preset such as
    // "F j, Y". Use the natural Persian order for those instead.
    $default_format = get_option('date_format');
    if ('' === trim((string) $format) || $format === $default_format) {
        $format = 'j F Y';
    }

    return md_format_timestamp_jalali($timestamp, $format, $timezone);
}
add_filter('wp_date', 'md_filter_wp_date_jalali', 10, 4);

/**
 * Jalali month/date archive title.
 *
 * @param string $title Archive title.
 * @return string
 */
function md_filter_archive_title_jalali($title) {
    if (is_admin() || !is_date()) {
        return $title;
    }

    $timezone = wp_timezone();
    $timestamp = (int) get_the_time('U');

    if (is_year()) {
        return 'سال ' . md_format_timestamp_jalali($timestamp, 'Y', $timezone);
    }
    if (is_month()) {
        return 'ماه ' . md_format_timestamp_jalali($timestamp, 'F Y', $timezone);
    }
    if (is_day()) {
        return md_format_timestamp_jalali($timestamp, 'j F Y', $timezone);
    }

    return $title;
}
add_filter('get_the_archive_title', 'md_filter_archive_title_jalali', 20);

/**
 * Render a Jalali date field for the admin.
 *
 * The visible input accepts Jalali text; the hidden input stores Gregorian
 * Y-m-d so all existing queries and comparisons stay untouched.
 *
 * @param array $args Field arguments.
 * @return string
 */
function md_jalali_date_field($args) {
    $args = wp_parse_args(
        $args,
        array(
            'name'     => 'md_date',
            'id'       => '',
            'value'    => '',
            'required' => false,
            'hint'     => '',
        )
    );

    $id    = $args['id'] ? $args['id'] : $args['name'];
    $value = md_sanitize_date($args['value']);
    $parts = $value ? md_ymd_to_jalali($value) : null;
    $text  = $parts ? md_fa_num(sprintf('%04d/%02d/%02d', $parts[0], $parts[1], $parts[2])) : '';

    ob_start();
    ?>
    <div class="md-date-field" data-md-date>
        <div class="md-date-field-row">
            <input
                type="text"
                class="md-jalali-input"
                id="<?php echo esc_attr($id); ?>_jalali"
                name="<?php echo esc_attr($args['name']); ?>_jalali"
                value="<?php echo esc_attr($text); ?>"
                placeholder="۱۴۰۴/۰۷/۲۵"
                inputmode="numeric"
                autocomplete="off"
                <?php echo $args['required'] ? 'required' : ''; ?>
            >
            <button type="button" class="button md-jalali-toggle" aria-expanded="false">
                <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                تقویم
            </button>
        </div>
        <input type="hidden" name="<?php echo esc_attr($args['name']); ?>" id="<?php echo esc_attr($id); ?>" value="<?php echo esc_attr($value); ?>">
        <p class="description md-jalali-hint">
            معادل میلادی: <span data-md-gregorian><?php echo $value ? esc_html($value) : '—'; ?></span>
            <?php if ($args['hint']) : ?>
                <br><?php echo esc_html($args['hint']); ?>
            <?php endif; ?>
        </p>
        <div class="md-jalali-popover" hidden></div>
    </div>
    <?php
    return ob_get_clean();
}
