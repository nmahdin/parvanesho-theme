/**
 * Jalali (Shamsi) date utilities and date picker.
 *
 * Exposes window.MDJalali and upgrades every [data-md-date] field rendered by
 * md_jalali_date_field(): the visible input accepts Jalali text while the
 * hidden input keeps the Gregorian Y-m-d value used by WordPress.
 */
(function () {
    'use strict';

    var MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    var WEEKDAYS = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
    var FA_DIGITS = '۰۱۲۳۴۵۶۷۸۹';
    var AR_DIGITS = '٠١٢٣٤٥٦٧٨٩';

    function fa(value) {
        return String(value).replace(/\d/g, function (digit) {
            return FA_DIGITS.charAt(parseInt(digit, 10));
        });
    }

    function en(value) {
        return String(value).replace(/[۰-۹٠-٩]/g, function (digit) {
            var index = FA_DIGITS.indexOf(digit);
            if (index === -1) {
                index = AR_DIGITS.indexOf(digit);
            }
            return index > -1 ? String(index) : digit;
        });
    }

    function pad(value, length) {
        var output = String(value);
        while (output.length < length) {
            output = '0' + output;
        }
        return output;
    }

    function toJalali(gy, gm, gd) {
        var gDaysInMonth = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        var gy2 = gm > 2 ? gy + 1 : gy;
        var days = 355666 + (365 * gy) + Math.trunc((gy2 + 3) / 4) - Math.trunc((gy2 + 99) / 100) +
            Math.trunc((gy2 + 399) / 400) + gd + gDaysInMonth[gm - 1];

        var jy = -1595 + (33 * Math.trunc(days / 12053));
        days %= 12053;
        jy += 4 * Math.trunc(days / 1461);
        days %= 1461;
        if (days > 365) {
            jy += Math.trunc((days - 1) / 365);
            days = (days - 1) % 365;
        }

        var jm;
        var jd;
        if (days < 186) {
            jm = 1 + Math.trunc(days / 31);
            jd = 1 + (days % 31);
        } else {
            jm = 7 + Math.trunc((days - 186) / 30);
            jd = 1 + ((days - 186) % 30);
        }
        return { jy: jy, jm: jm, jd: jd };
    }

    function toGregorian(jy, jm, jd) {
        jy = jy + 1595;
        var days = -355668 + (365 * jy) + (Math.trunc(jy / 33) * 8) + Math.trunc(((jy % 33) + 3) / 4) + jd +
            ((jm < 7) ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);

        var gy = 400 * Math.trunc(days / 146097);
        days %= 146097;
        if (days > 36524) {
            gy += 100 * Math.trunc(--days / 36524);
            days %= 36524;
            if (days >= 365) {
                days++;
            }
        }
        gy += 4 * Math.trunc(days / 1461);
        days %= 1461;
        if (days > 365) {
            gy += Math.trunc((days - 1) / 365);
            days = (days - 1) % 365;
        }

        var gd = days + 1;
        var leap = (gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0;
        var monthDays = [0, 31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        var gm = 0;
        while (gm < 13 && gd > monthDays[gm]) {
            gd -= monthDays[gm];
            gm++;
        }
        return { gy: gy, gm: gm, gd: gd };
    }

    function monthDays(jy, jm) {
        if (jm <= 6) {
            return 31;
        }
        if (jm <= 11) {
            return 30;
        }
        var start = toGregorian(jy, 1, 1);
        var next = toGregorian(jy + 1, 1, 1);
        var a = Date.UTC(start.gy, start.gm - 1, start.gd);
        var b = Date.UTC(next.gy, next.gm - 1, next.gd);
        return Math.round((b - a) / 86400000) === 366 ? 30 : 29;
    }

    function parseJalali(text) {
        var value = en(text).trim().replace(/[\s\/\.\-]+/g, '-');
        var match = /^(\d{4})-(\d{1,2})-(\d{1,2})$/.exec(value);
        if (!match) {
            return null;
        }
        var jy = parseInt(match[1], 10);
        var jm = parseInt(match[2], 10);
        var jd = parseInt(match[3], 10);
        if (jy < 1200 || jy > 1700 || jm < 1 || jm > 12 || jd < 1 || jd > monthDays(jy, jm)) {
            return null;
        }
        return { jy: jy, jm: jm, jd: jd };
    }

    function parseGregorian(text) {
        var match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(text || '').trim());
        if (!match) {
            return null;
        }
        return { gy: parseInt(match[1], 10), gm: parseInt(match[2], 10), gd: parseInt(match[3], 10) };
    }

    function jalaliText(parts) {
        return fa(pad(parts.jy, 4) + '/' + pad(parts.jm, 2) + '/' + pad(parts.jd, 2));
    }

    function gregorianText(parts) {
        return pad(parts.gy, 4) + '-' + pad(parts.gm, 2) + '-' + pad(parts.gd, 2);
    }

    function fromGregorian(text) {
        var value = parseGregorian(text);
        return value ? toJalali(value.gy, value.gm, value.gd) : null;
    }

    function toIso(parts) {
        return gregorianText(toGregorian(parts.jy, parts.jm, parts.jd));
    }

    function today() {
        var now = new Date();
        return toJalali(now.getFullYear(), now.getMonth() + 1, now.getDate());
    }

    function sameDay(a, b) {
        return !!a && !!b && a.jy === b.jy && a.jm === b.jm && a.jd === b.jd;
    }

    /* ------------------------------------------------------------------ UI */

    function fieldOf(element) {
        return element.closest ? element.closest('[data-md-date]') : null;
    }

    function ensureState(field) {
        if (!field._mdDate) {
            var hidden = field.querySelector('input[type="hidden"]');
            var selected = hidden && hidden.value ? fromGregorian(hidden.value) : null;
            var view = selected || today();
            field._mdDate = { view: { jy: view.jy, jm: view.jm }, selected: selected };
        }
        return field._mdDate;
    }

    function syncHint(field) {
        var hidden = field.querySelector('input[type="hidden"]');
        var hint = field.querySelector('[data-md-gregorian]');
        if (hint && hidden) {
            hint.textContent = hidden.value || '—';
        }
    }

    function notify(field) {
        var hidden = field.querySelector('input[type="hidden"]');
        if (hidden) {
            hidden.dispatchEvent(new Event('change', { bubbles: true }));
            hidden.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    function renderCalendar(field) {
        var popover = field.querySelector('.md-jalali-popover');
        if (!popover) {
            return;
        }
        var state = ensureState(field);
        var view = state.view;
        var total = monthDays(view.jy, view.jm);
        var first = toGregorian(view.jy, view.jm, 1);
        var offset = (new Date(Date.UTC(first.gy, first.gm - 1, first.gd)).getUTCDay() + 1) % 7;
        var now = today();

        var html = '<div class="md-jalali-cal">';
        html += '<div class="md-jalali-cal-head">';
        html += '<button type="button" class="md-jalali-nav" data-md-nav="prev" aria-label="ماه پیشین">‹</button>';
        html += '<strong>' + MONTHS[view.jm - 1] + ' ' + fa(view.jy) + '</strong>';
        html += '<button type="button" class="md-jalali-nav" data-md-nav="next" aria-label="ماه بعد">›</button>';
        html += '</div><div class="md-jalali-cal-week">';
        WEEKDAYS.forEach(function (day) {
            html += '<span>' + day + '</span>';
        });
        html += '</div><div class="md-jalali-cal-grid">';
        for (var i = 0; i < offset; i++) {
            html += '<span class="md-jalali-empty" aria-hidden="true"></span>';
        }
        for (var day = 1; day <= total; day++) {
            var candidate = { jy: view.jy, jm: view.jm, jd: day };
            var classes = 'md-jalali-day';
            if (sameDay(candidate, state.selected)) {
                classes += ' is-selected';
            }
            if (sameDay(candidate, now)) {
                classes += ' is-today';
            }
            html += '<button type="button" class="' + classes + '" data-md-day="' + day + '" aria-label="' +
                fa(day) + ' ' + MONTHS[view.jm - 1] + '">' + fa(day) + '</button>';
        }
        html += '</div><div class="md-jalali-cal-foot">';
        html += '<button type="button" class="button-link" data-md-today>امروز</button>';
        html += '<button type="button" class="button-link md-jalali-clear" data-md-clear>پاک کردن</button>';
        html += '</div></div>';

        popover.innerHTML = html;
    }

    function openPopover(field) {
        var popover = field.querySelector('.md-jalali-popover');
        var toggle = field.querySelector('.md-jalali-toggle');
        if (!popover) {
            return;
        }
        closeAll(field);
        renderCalendar(field);
        popover.hidden = false;
        field.classList.add('is-open');
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'true');
        }
    }

    function closeAll(except) {
        document.querySelectorAll('[data-md-date]').forEach(function (field) {
            if (field === except) {
                return;
            }
            var popover = field.querySelector('.md-jalali-popover');
            var toggle = field.querySelector('.md-jalali-toggle');
            if (popover) {
                popover.hidden = true;
            }
            field.classList.remove('is-open');
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function selectDate(field, parts) {
        if (!parts) {
            return;
        }
        var text = field.querySelector('.md-jalali-input');
        var hidden = field.querySelector('input[type="hidden"]');
        if (text) {
            text.value = jalaliText(parts);
            text.classList.remove('is-invalid');
        }
        if (hidden) {
            hidden.value = toIso(parts);
        }
        ensureState(field).selected = parts;
        syncHint(field);
        notify(field);
        closeAll();
    }

    function clearDate(field) {
        var text = field.querySelector('.md-jalali-input');
        var hidden = field.querySelector('input[type="hidden"]');
        if (text) {
            text.value = '';
            text.classList.remove('is-invalid');
        }
        if (hidden) {
            hidden.value = '';
        }
        ensureState(field).selected = null;
        syncHint(field);
        notify(field);
        closeAll();
    }

    function onTextInput(input) {
        var field = fieldOf(input);
        if (!field) {
            return;
        }
        var hidden = field.querySelector('input[type="hidden"]');
        var parsed = parseJalali(input.value);
        input.classList.toggle('is-invalid', input.value.trim() !== '' && !parsed);

        if (hidden) {
            hidden.value = parsed ? toIso(parsed) : '';
        }
        if (parsed) {
            var state = ensureState(field);
            state.selected = parsed;
            state.view = { jy: parsed.jy, jm: parsed.jm };
        }
        syncHint(field);
        notify(field);
    }

    document.addEventListener('click', function (event) {
        var target = event.target;

        var toggle = target.closest('.md-jalali-toggle');
        if (toggle) {
            event.preventDefault();
            var field = fieldOf(toggle);
            var popover = field ? field.querySelector('.md-jalali-popover') : null;
            if (popover && !popover.hidden) {
                closeAll();
            } else if (field) {
                openPopover(field);
                field.querySelector('.md-jalali-input').focus();
            }
            return;
        }

        var day = target.closest('.md-jalali-day');
        if (day) {
            event.preventDefault();
            var dayField = fieldOf(day);
            if (!dayField) {
                return;
            }
            var state = ensureState(dayField);
            selectDate(dayField, { jy: state.view.jy, jm: state.view.jm, jd: parseInt(day.dataset.mdDay, 10) });
            return;
        }

        var nav = target.closest('[data-md-nav]');
        if (nav) {
            event.preventDefault();
            var navField = fieldOf(nav);
            var state = ensureState(navField);
            if (nav.dataset.mdNav === 'prev') {
                state.view.jm--;
                if (state.view.jm < 1) {
                    state.view.jm = 12;
                    state.view.jy--;
                }
            } else {
                state.view.jm++;
                if (state.view.jm > 12) {
                    state.view.jm = 1;
                    state.view.jy++;
                }
            }
            renderCalendar(navField);
            return;
        }

        if (target.closest('[data-md-today]')) {
            event.preventDefault();
            var todayField = fieldOf(target);
            var now = today();
            var s = ensureState(todayField);
            s.view = { jy: now.jy, jm: now.jm };
            selectDate(todayField, now);
            return;
        }

        if (target.closest('[data-md-clear]')) {
            event.preventDefault();
            clearDate(fieldOf(target));
            return;
        }

        if (!target.closest('[data-md-date]')) {
            closeAll();
        }
    });

    document.addEventListener('input', function (event) {
        if (event.target.classList && event.target.classList.contains('md-jalali-input')) {
            onTextInput(event.target);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeAll();
        }
    });

    document.addEventListener('focusin', function (event) {
        var field = event.target.closest ? event.target.closest('[data-md-date]') : null;
        if (!field) {
            closeAll();
        }
    });

    function init(root) {
        (root || document).querySelectorAll('[data-md-date]').forEach(function (field) {
            if (field.dataset.mdInit === '1') {
                return;
            }
            field.dataset.mdInit = '1';
            ensureState(field);
            syncHint(field);
            var input = field.querySelector('.md-jalali-input');
            if (input && input.value && !parseJalali(input.value)) {
                input.classList.add('is-invalid');
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { init(document); });
    } else {
        init(document);
    }

    window.MDJalali = {
        toJalali: toJalali,
        toGregorian: toGregorian,
        monthDays: monthDays,
        parse: parseJalali,
        fromGregorian: fromGregorian,
        toIso: toIso,
        today: today,
        format: jalaliText,
        fa: fa,
        init: init
    };
})();
