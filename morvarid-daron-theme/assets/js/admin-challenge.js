/**
 * Challenge admin panel.
 */
(function ($) {
    'use strict';

    var cfg = window.mdChallenge || {};
    var strings = cfg.i18n || {};
    var challengeId = parseInt(cfg.challengeId, 10) || 0;
    var currentDayId = 0;
    var editorReady = false;
    var dayDirty = false;
    var challengeDirty = false;

    var monthNames = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

    function faNum(value) {
        return String(value).replace(/\d/g, function (digit) {
            return '۰۱۲۳۴۵۶۷۸۹'.charAt(parseInt(digit, 10));
        });
    }

    function pad(value, length) {
        var output = String(value);
        while (output.length < length) {
            output = '0' + output;
        }
        return output;
    }

    function parseYmd(value) {
        var match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value || ''));
        if (!match) {
            return null;
        }
        return [parseInt(match[1], 10), parseInt(match[2], 10), parseInt(match[3], 10)];
    }

    function toJalali(gy, gm, gd) {
        var gDays = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        var gy2 = gm > 2 ? gy + 1 : gy;
        var days = 355666 + (365 * gy) + Math.trunc((gy2 + 3) / 4) - Math.trunc((gy2 + 99) / 100) + Math.trunc((gy2 + 399) / 400) + gd + gDays[gm - 1];
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
        return [jy, jm, jd];
    }

    function jalaliShort(value) {
        var parts = parseYmd(value);
        if (!parts) {
            return '—';
        }
        var j = toJalali(parts[0], parts[1], parts[2]);
        return faNum(pad(j[0], 4) + '/' + pad(j[1], 2) + '/' + pad(j[2], 2));
    }

    function jalaliLong(value) {
        var parts = parseYmd(value);
        if (!parts) {
            return '—';
        }
        var j = toJalali(parts[0], parts[1], parts[2]);
        return faNum(j[2]) + ' ' + monthNames[j[1] - 1] + ' ' + faNum(j[0]);
    }

    function calendarDays(start, end) {
        var s = parseYmd(start);
        var e = parseYmd(end);
        if (!s || !e) {
            return 0;
        }
        var startTime = Date.UTC(s[0], s[1] - 1, s[2]);
        var endTime = Date.UTC(e[0], e[1] - 1, e[2]);
        if (endTime < startTime) {
            return -1;
        }
        return Math.round((endTime - startTime) / 86400000) + 1;
    }

    function ajax(action, data) {
        return $.ajax({
            url: cfg.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: $.extend({
                action: action,
                nonce: cfg.nonce,
                challenge_id: challengeId
            }, data || {})
        });
    }

    function errorMessage(response) {
        return response && response.data && response.data.message ? response.data.message : strings.error;
    }

    function showError(message) {
        $('#md-modal-error').text(message || strings.error).prop('hidden', false);
    }

    function clearError() {
        $('#md-modal-error').text('').prop('hidden', true);
    }

    function showNotice(message, isError) {
        var $notice = $('#md-days-notice');
        $notice.text(message).toggleClass('is-error', !!isError).prop('hidden', false);
        window.clearTimeout(showNotice.timer);
        showNotice.timer = window.setTimeout(function () {
            $notice.prop('hidden', true);
        }, 5000);
    }

    function setBusy(busy) {
        $('#md-save-day').prop('disabled', !!busy);
        $('#md-modal-spinner').prop('hidden', !busy);
    }

    function updateCounts() {
        var start = $('#md_start_date').val();
        var end = $('#md_end_date').val();
        var days = calendarDays(start, end);
        var contentDays = $('#md-days-list tr').length;
        var warning = '';

        $('#md_start_date_jalali').text(start ? jalaliLong(start) : '—');
        $('#md_end_date_jalali').text(end ? jalaliLong(end) : '—');
        $('#md-calendar-days').text(days > 0 ? faNum(days) : '—');
        $('#md-manager-calendar-days').text(days > 0 ? faNum(days) : '—');
        $('#md-content-days').text(faNum(contentDays));
        $('#md-manager-content-days').text(faNum(contentDays));

        if (days === -1) {
            warning = strings.invalidDates;
        } else if (days === 0) {
            warning = strings.missingDates;
        } else if (days !== contentDays) {
            warning = String(strings.countMismatch).replace('%1$s', faNum(contentDays)).replace('%2$s', faNum(days));
        }

        $('#md-day-warning').text(warning).prop('hidden', !warning);
    }

    function renumberRows() {
        $('#md-days-list tr').each(function (index) {
            var $row = $(this);
            $row.attr('data-order', index + 1);
            $row.find('.md-day-number').text(faNum(index + 1));
        });
    }

    function toggleEmpty() {
        var hasRows = $('#md-days-list tr').length > 0;
        $('#md-days-empty').prop('hidden', hasRows);
        $('#md-days-table').toggle(hasRows);
    }

    function updateManagerCounts(stats) {
        if (!stats) {
            return;
        }
        $('#md-manager-content-days').text(faNum(stats.content_days || 0));
        $('#md-manager-calendar-days').text(stats.calendar_days ? faNum(stats.calendar_days) : '—');
        $('#md-content-days').text(faNum(stats.content_days || 0));
        $('#md-calendar-days').text(stats.calendar_days ? faNum(stats.calendar_days) : '—');
        updateCounts();
    }

    function destroyEditor() {
        if (window.wp && wp.editor && wp.editor.remove) {
            try {
                wp.editor.remove('md-day-content');
            } catch (e) {
                // The editor may not have been initialized yet.
            }
        }
        editorReady = false;
    }

    function initEditor(content) {
        destroyEditor();
        $('#md-day-content').val(content || '');
        editorReady = false;

        if (!window.wp || !wp.editor || !wp.editor.initialize) {
            return;
        }

        try {
            wp.editor.initialize('md-day-content', {
                tinymce: {
                    wpautop: true,
                    directionality: 'rtl',
                    toolbar1: 'formatselect,bold,italic,bullist,numlist,blockquote,alignright,aligncenter,alignleft,link,unlink',
                    toolbar2: 'strikethrough,hr,forecolor,pastetext,removeformat,charmap,outdent,indent,undo,redo'
                },
                quicktags: true,
                mediaButtons: true
            });
            editorReady = true;
        } catch (e) {
            editorReady = false;
        }
    }

    function getEditorContent() {
        if (editorReady && window.wp && wp.editor && wp.editor.getContent) {
            var content = wp.editor.getContent('md-day-content');
            if (typeof content === 'string') {
                return content;
            }
        }
        return $('#md-day-content').val() || '';
    }

    function qaRow(question, answer) {
        var $row = $('<div class="md-qa-row" />');
        var $head = $('<div class="md-qa-row-head" />');
        var $actions = $('<div class="md-qa-row-actions" />');

        $head.append($('<strong />').text('پرسش و پاسخ'));
        $actions.append($('<button type="button" class="button md-qa-up" />').text('↑').attr('aria-label', strings.qaUp || 'بالا'));
        $actions.append($('<button type="button" class="button md-qa-down" />').text('↓').attr('aria-label', strings.qaDown || 'پایین'));
        $actions.append($('<button type="button" class="button md-qa-remove" />').text('×').attr('aria-label', strings.qaRemove || 'حذف'));
        $head.append($actions);

        $row.append($head);
        $row.append($('<input type="text" class="md-qa-q" />').attr('placeholder', strings.qaQuestion || 'متن پرسش').val(question || ''));
        $row.append($('<textarea rows="2" class="md-qa-a" />').attr('placeholder', strings.qaAnswer || 'متن پاسخ').val(answer || ''));

        return $row;
    }

    function appendQa(question, answer) {
        $('#md-qa-list').append(qaRow(question, answer));
        dayDirty = true;
    }

    function collectQa() {
        var items = [];
        $('#md-qa-list .md-qa-row').each(function () {
            items.push({
                q: $(this).find('.md-qa-q').val() || '',
                a: $(this).find('.md-qa-a').val() || ''
            });
        });
        return items;
    }

    function setMedia(kind, id, name) {
        $('#md-day-' + kind + '-id').val(id || 0);
        $('#md-day-' + kind + '-name').text(name || '');
    }

    function clearMedia(kind) {
        setMedia(kind, 0, '');
        dayDirty = true;
    }

    function openMedia(kind) {
        if (!window.wp || !wp.media) {
            return;
        }
        var type = kind === 'file' ? '' : kind;
        var frame = wp.media({
            title: strings.chooseMedia || 'انتخاب فایل',
            button: { text: 'استفاده از این فایل' },
            multiple: false,
            library: type ? { type: type } : {}
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            setMedia(kind, attachment.id, attachment.filename || attachment.title || '');
            dayDirty = true;
        });

        frame.open();
    }

    function resetForm() {
        currentDayId = 0;
        $('#md-day-id').val(0);
        $('#md-day-number').val($('#md-days-list tr').length + 1);
        $('#md-day-title').val('');
        $('#md-day-summary').val('');
        $('#md-day-extra').val('');
        $('#md-day-tip-title').val('');
        $('#md-day-tip').val('');
        $('#md-day-exercise-title').val('');
        $('#md-day-exercise-desc').val('');
        $('#md-day-exercise-steps').val('');
        $('#md-day-audio-title').val('');
        $('#md-day-video-url').val('');
        $('#md-day-video-title').val('');
        $('#md-day-file-title').val('');
        $('#md-day-status').val('draft');
        $('#md-qa-list').empty();
        setMedia('audio', 0, '');
        setMedia('video', 0, '');
        setMedia('file', 0, '');
        clearError();
        dayDirty = false;
    }

    function fillForm(day) {
        currentDayId = parseInt(day.id, 10) || 0;
        $('#md-day-id').val(currentDayId);
        $('#md-day-number').val(parseInt(day.order, 10) || (currentDayId ? 1 : $('#md-days-list tr').length + 1));
        $('#md-day-title').val(day.title || '');
        $('#md-day-summary').val(day.summary || '');
        $('#md-day-extra').val(day.extra || '');
        $('#md-day-tip-title').val(day.tip_title || '');
        $('#md-day-tip').val(day.tip || '');
        $('#md-day-exercise-title').val(day.exercise_title || '');
        $('#md-day-exercise-desc').val(day.exercise_desc || '');
        $('#md-day-exercise-steps').val(day.exercise_steps || '');
        $('#md-day-audio-title').val(day.audio_title || '');
        $('#md-day-video-url').val(day.video_url || '');
        $('#md-day-video-title').val(day.video_title || '');
        $('#md-day-file-title').val(day.file_title || '');
        $('#md-day-status').val(day.status === 'publish' ? 'publish' : 'draft');
        setMedia('audio', day.audio_id || 0, day.audio_name || '');
        setMedia('video', day.video_id || 0, day.video_name || '');
        setMedia('file', day.file_id || 0, day.file_name || '');

        $('#md-qa-list').empty();
        if (day.qa && day.qa.length) {
            $.each(day.qa, function (index, item) {
                $('#md-qa-list').append(qaRow(item.q || '', item.a || ''));
            });
        }
        clearError();
        dayDirty = false;
    }

    function showModal(title) {
        $('#md-day-modal-title').text(title || strings.editDay);
        $('#md-day-modal').prop('hidden', false).attr('aria-hidden', 'false');
        $('body').addClass('md-modal-open');
    }

    function focusTitle() {
        window.setTimeout(function () {
            $('#md-day-title').trigger('focus');
        }, 60);
    }

    function openModal(dayId) {
        if (!challengeId) {
            window.alert(strings.noChallenge || 'ابتدا چالش را ذخیره کنید.');
            return;
        }

        destroyEditor();
        clearError();
        dayDirty = false;

        if (!dayId) {
            showModal(strings.addDay);
            resetForm();
            initEditor('');
            focusTitle();
            window.setTimeout(function () { dayDirty = false; }, 300);
            return;
        }

        showModal(strings.editDay);
        setBusy(true);
        ajax('md_challenge_get_day', { day_id: dayId }).done(function (response) {
            if ($('#md-day-modal').prop('hidden')) {
                return;
            }
            if (!response.success) {
                showError(errorMessage(response));
                return;
            }
            fillForm(response.data.day);
            initEditor(response.data.day.content || '');
            focusTitle();
            window.setTimeout(function () { dayDirty = false; }, 300);
        }).fail(function (xhr) {
            showError(errorMessage(xhr.responseJSON));
        }).always(function () {
            setBusy(false);
        });
    }

    function closeModal(force) {
        if (!force && dayDirty && !window.confirm(strings.confirmClose || 'تغییرات ذخیره نشده است.')) {
            return;
        }
        destroyEditor();
        $('#md-day-modal').prop('hidden', true).attr('aria-hidden', 'true');
        $('body').removeClass('md-modal-open');
        dayDirty = false;
        currentDayId = 0;
        setBusy(false);
    }

    function updateRow(response) {
        if (!response || !response.success) {
            showNotice(errorMessage(response), true);
            return false;
        }
        var $row = $(response.data.row_html);
        if (currentDayId) {
            $('#md-days-list tr[data-day-id="' + currentDayId + '"]').replaceWith($row);
        } else {
            $('#md-days-list').append($row);
        }
        renumberRows();
        updateCounts();
        toggleEmpty();
        if (response.data.stats) {
            updateManagerCounts(response.data.stats);
        }
        return true;
    }

    function saveDay() {
        var status = $('#md-day-status').val();
        var title = $.trim($('#md-day-title').val());
        var content = getEditorContent();
        var desired = parseInt($('#md-day-number').val(), 10);

        if (status === 'publish' && (!title || !$.trim($('<div>').html(content).text()))) {
            showError(strings.publishNeeds || 'برای انتشار روز، عنوان و متن اصلی الزامی است.');
            return;
        }

        if (isNaN(desired) || desired < 1) {
            showError(strings.invalidNumber || 'شماره روز باید عددی بزرگ‌تر از صفر باشد.');
            return;
        }

        clearError();
        setBusy(true);

        ajax('md_challenge_save_day', {
            day_id: currentDayId,
            title: title,
            summary: $('#md-day-summary').val(),
            content: content,
            extra: $('#md-day-extra').val(),
            tip_title: $('#md-day-tip-title').val(),
            tip: $('#md-day-tip').val(),
            exercise_title: $('#md-day-exercise-title').val(),
            exercise_desc: $('#md-day-exercise-desc').val(),
            exercise_steps: $('#md-day-exercise-steps').val(),
            audio_id: $('#md-day-audio-id').val() || 0,
            audio_title: $('#md-day-audio-title').val(),
            video_id: $('#md-day-video-id').val() || 0,
            video_url: $('#md-day-video-url').val(),
            video_title: $('#md-day-video-title').val(),
            file_id: $('#md-day-file-id').val() || 0,
            file_title: $('#md-day-file-title').val(),
            qa: JSON.stringify(collectQa()),
            status: status
        }).done(function (response) {
            if (updateRow(response)) {
                showNotice(strings.saved || 'روز ذخیره شد.', false);
                var savedId = parseInt(response.data.day_id, 10) || 0;
                closeModal(true);
                if (savedId) {
                    moveRowTo(savedId, desired);
                }
            }
        }).fail(function (xhr) {
            showError(errorMessage(xhr.responseJSON));
        }).always(function () {
            setBusy(false);
        });
    }

    function deleteDay(dayId, $row) {
        if (!window.confirm(strings.confirmDelete || 'این روز حذف شود؟')) {
            return;
        }
        ajax('md_challenge_delete_day', { day_id: dayId }).done(function (response) {
            if (!response.success) {
                showNotice(errorMessage(response), true);
                return;
            }
            $row.remove();
            renumberRows();
            updateCounts();
            toggleEmpty();
            if (response.data.stats) {
                updateManagerCounts(response.data.stats);
            }
            showNotice(strings.deleted || 'روز حذف شد.', false);
        }).fail(function (xhr) {
            showNotice(errorMessage(xhr.responseJSON), true);
        });
    }

    function duplicateDay(dayId, $row) {
        ajax('md_challenge_duplicate_day', { day_id: dayId }).done(function (response) {
            if (!response.success) {
                showNotice(errorMessage(response), true);
                return;
            }
            var $newRow = $(response.data.row_html);
            $newRow.insertAfter($row);
            renumberRows();
            updateCounts();
            toggleEmpty();
            if (response.data.stats) {
                updateManagerCounts(response.data.stats);
            }
            showNotice(strings.duplicated || 'روز کپی شد.', false);
        }).fail(function (xhr) {
            showNotice(errorMessage(xhr.responseJSON), true);
        });
    }

    function moveRowTo(dayId, position) {
        var $row = $('#md-days-list tr[data-day-id="' + dayId + '"]');
        if (!$row.length) {
            return;
        }
        var $rows = $('#md-days-list tr');
        var target = Math.max(1, Math.min(position, $rows.length));
        if (target === $rows.index($row) + 1) {
            return;
        }
        var $others = $rows.not($row);
        if (target - 1 >= $others.length) {
            $row.appendTo('#md-days-list');
        } else {
            $row.insertBefore($others.eq(target - 1));
        }
        persistOrder(true);
    }

    function persistOrder(silent) {
        var ids = [];
        $('#md-days-list tr').each(function () {
            ids.push($(this).data('day-id'));
        });
        if (!ids.length) {
            return;
        }
        ajax('md_challenge_reorder_days', { ids: ids }).done(function (response) {
            if (!response.success) {
                showNotice(errorMessage(response), true);
                return;
            }
            renumberRows();
            updateCounts();
            if (response.data.stats) {
                updateManagerCounts(response.data.stats);
            }
            if (!silent) {
                showNotice(strings.reordered || 'ترتیب ذخیره شد.', false);
            }
        }).fail(function (xhr) {
            showNotice(errorMessage(xhr.responseJSON), true);
        });
    }

    $(function () {
        if (!$('#md-challenge-days').length) {
            return;
        }

        updateCounts();
        toggleEmpty();

        if (!challengeId) {
            $('#md-add-day').prop('disabled', true);
        }

        if ($.fn.sortable) {
            $('#md-days-list').sortable({
                handle: '.md-drag-handle',
                axis: 'y',
                helper: 'clone',
                update: function () {
                    persistOrder();
                }
            });
        }

        $('#md-add-day').on('click', function () {
            openModal(0);
        });

        $('#md-days-list').on('click', '.md-edit-day', function () {
            openModal($(this).closest('tr').data('day-id'));
        });

        $('#md-days-list').on('click', '.md-duplicate-day', function () {
            var $row = $(this).closest('tr');
            duplicateDay($row.data('day-id'), $row);
        });

        $('#md-days-list').on('click', '.md-delete-day', function () {
            var $row = $(this).closest('tr');
            deleteDay($row.data('day-id'), $row);
        });

        $('#md-days-list').on('click', '.md-move-up', function () {
            var $row = $(this).closest('tr');
            var $previous = $row.prev('tr');
            if ($previous.length) {
                $row.insertBefore($previous);
                persistOrder();
            }
        });

        $('#md-days-list').on('click', '.md-move-down', function () {
            var $row = $(this).closest('tr');
            var $next = $row.next('tr');
            if ($next.length) {
                $row.insertAfter($next);
                persistOrder();
            }
        });

        $('#md-save-day').on('click', saveDay);

        $('.md-modal-close, [data-md-close]').on('click', function (event) {
            event.preventDefault();
            closeModal(false);
        });

        $('#md-add-qa').on('click', function () {
            appendQa('', '');
        });

        $('#md-qa-list').on('click', '.md-qa-remove', function () {
            $(this).closest('.md-qa-row').remove();
            dayDirty = true;
        });

        $('#md-qa-list').on('click', '.md-qa-up', function () {
            var $row = $(this).closest('.md-qa-row');
            var $previous = $row.prev('.md-qa-row');
            if ($previous.length) {
                $row.insertBefore($previous);
                dayDirty = true;
            }
        });

        $('#md-qa-list').on('click', '.md-qa-down', function () {
            var $row = $(this).closest('.md-qa-row');
            var $next = $row.next('.md-qa-row');
            if ($next.length) {
                $row.insertAfter($next);
                dayDirty = true;
            }
        });

        $('#md-day-modal').on('click', '.md-day-media-select', function () {
            openMedia($(this).data('media'));
        });

        $('#md-day-modal').on('click', '.md-day-media-remove', function () {
            clearMedia($(this).data('media'));
        });

        $('#md-day-modal').on('input change', ':input', function () {
            dayDirty = true;
        });

        $('#md_start_date, #md_end_date').on('input change', function () {
            challengeDirty = true;
            updateCounts();
        });

        $('#md_access_mode, #md_show_locked_title, #md_enrollment_url').on('input change', function () {
            challengeDirty = true;
        });

        $('#post').on('submit', function () {
            challengeDirty = false;
            dayDirty = false;
        });

        $(document).on('keydown', function (event) {
            if (event.key === 'Escape' && !$('#md-day-modal').prop('hidden')) {
                closeModal(false);
            }
        });

        $(window).on('beforeunload', function () {
            if (dayDirty || challengeDirty) {
                return strings.unsaved || 'تغییرات ذخیره نشده‌ای دارید.';
            }
        });
    });
})(jQuery);
