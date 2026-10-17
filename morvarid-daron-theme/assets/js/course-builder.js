/**
 * Course builder — chapters and lessons inside the course editor.
 *
 * The Gutenberg sidebar panel lists chapters and lessons. Adding or editing a
 * lesson opens a modal over the editor; nothing navigates away from the course.
 */
(function ($) {
    'use strict';

    var cfg = window.mdCourseBuilder || {};
    var strings = cfg.i18n || {};
    var state = { chapters: [], lessons: [] };
    var loading = false;
    var editorReady = false;
    var currentLessonId = 0;
    var toastTimer = 0;

    function faNum(value) {
        return String(value).replace(/\d/g, function (digit) {
            return '۰۱۲۳۴۵۶۷۸۹'.charAt(parseInt(digit, 10));
        });
    }

    function esc(value) {
        return $('<div>').text(value === undefined || value === null ? '' : value).html();
    }

    function escAttr(value) {
        return esc(value).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function currentCourseId() {
        if (window.wp && wp.data && wp.data.select('core/editor')) {
            var id = parseInt(wp.data.select('core/editor').getCurrentPostId(), 10);
            if (id) {
                return id;
            }
        }
        return parseInt(cfg.courseId, 10) || 0;
    }

    function request(action, data) {
        return $.ajax({
            url: cfg.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: $.extend(
                {
                    action: action,
                    nonce: cfg.nonce,
                    course_id: currentCourseId()
                },
                data || {}
            )
        });
    }

    function toast(message, isError) {
        $('#md-cb-toast').remove();
        var $toast = $('<div id="md-cb-toast" role="status" />')
            .addClass(isError ? 'is-error' : '')
            .text(message || strings.saved || 'ذخیره شد.');
        $('body').append($toast);
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(function () {
            $toast.fadeOut(200, function () {
                $(this).remove();
            });
        }, 3200);
    }

    function errorMessage(response) {
        if (response && response.data && response.data.message) {
            return response.data.message;
        }
        return strings.error || 'خطایی رخ داد.';
    }

    function applyData(data) {
        if (!data) {
            return;
        }
        state.chapters = data.chapters || [];
        state.lessons = data.lessons || [];
        renderPanel();
    }

    function loadData() {
        var id = currentCourseId();
        var $root = $('#md-course-builder-panel');

        if (!$root.length || loading) {
            return;
        }
        if (!id) {
            $root.html('<p class="md-cb-help">برای مدیریت جلسه‌ها، دوره را یک بار ذخیره کنید.</p>');
            return;
        }

        loading = true;
        request('md_course_builder_get')
            .done(function (response) {
                if (response && response.success) {
                    applyData(response.data);
                } else {
                    $root.html('<p class="md-cb-help md-cb-error-text">' + esc(errorMessage(response)) + '</p>');
                }
            })
            .fail(function (xhr) {
                $root.html('<p class="md-cb-help md-cb-error-text">' + esc(errorMessage(xhr.responseJSON)) + '</p>');
            })
            .always(function () {
                loading = false;
            });
    }

    /* ------------------------------------------------------------ panel html */

    function lessonHtml(lesson, index, total) {
        var badge = lesson.status === 'publish'
            ? '<span class="md-cb-badge is-published">' + esc(strings.publish || 'منتشرشده') + '</span>'
            : '<span class="md-cb-badge">' + esc(strings.draft || 'پیش‌نویس') + '</span>';

        var meta = badge;
        if (lesson.duration) {
            meta += '<span class="md-cb-dot">·</span><span>' + esc(lesson.duration) + '</span>';
        }

        return '' +
            '<li class="md-cb-lesson" data-lesson-id="' + parseInt(lesson.id, 10) + '">' +
                '<div class="md-cb-lesson-body">' +
                    '<strong>' + esc(lesson.title || 'بدون عنوان') + '</strong>' +
                    '<span class="md-cb-lesson-meta">' + meta + '</span>' +
                '</div>' +
                '<div class="md-cb-lesson-actions">' +
                    '<button type="button" class="button-link" data-md-cb-edit-lesson title="' + esc(strings.edit) + '">' + esc(strings.edit) + '</button>' +
                    '<button type="button" class="button-link" data-md-cb-move-lesson="up" aria-label="بالا"' + (index === 0 ? ' disabled' : '') + '>↑</button>' +
                    '<button type="button" class="button-link" data-md-cb-move-lesson="down" aria-label="پایین"' + (index >= total - 1 ? ' disabled' : '') + '>↓</button>' +
                    '<button type="button" class="button-link md-cb-danger" data-md-cb-delete-lesson aria-label="حذف">×</button>' +
                '</div>' +
            '</li>';
    }

    function chapterHtml(chapter, index, total) {
        var lessons = state.lessons.filter(function (lesson) {
            return lesson.chapter === chapter;
        });

        var items = lessons.map(function (lesson, lessonIndex) {
            return lessonHtml(lesson, lessonIndex, lessons.length);
        }).join('');

        if (!lessons.length) {
            items = '<li class="md-cb-empty">' + esc(strings.noLessons || 'هنوز جلسه‌ای در این فصل نیست.') + '</li>';
        }

        return '' +
            '<section class="md-cb-chapter" data-chapter="' + escAttr(chapter) + '">' +
                '<header class="md-cb-chapter-head">' +
                    '<div class="md-cb-chapter-title">' +
                        '<span class="md-cb-chapter-number">' + esc(strings.chapter || 'فصل') + ' ' + faNum(index + 1) + '</span>' +
                        '<strong>' + esc(chapter) + '</strong>' +
                    '</div>' +
                    '<div class="md-cb-chapter-actions">' +
                        '<button type="button" class="button-link" data-md-cb-rename-chapter title="' + esc(strings.renameChapter) + '">✎</button>' +
                        '<button type="button" class="button-link" data-md-cb-chapter-move="up" aria-label="بالا"' + (index === 0 ? ' disabled' : '') + '>↑</button>' +
                        '<button type="button" class="button-link" data-md-cb-chapter-move="down" aria-label="پایین"' + (index >= total - 1 ? ' disabled' : '') + '>↓</button>' +
                        '<button type="button" class="button-link md-cb-danger" data-md-cb-delete-chapter aria-label="حذف فصل">×</button>' +
                    '</div>' +
                '</header>' +
                '<ul class="md-cb-lessons">' + items + '</ul>' +
                '<button type="button" class="button md-cb-add-lesson" data-md-cb-add-lesson data-chapter="' + escAttr(chapter) + '">+ ' + esc(strings.addLessonHere || 'افزودن جلسه به این فصل') + '</button>' +
            '</section>';
    }

    function panelHtml() {
        var html = '<div class="md-cb-panel">';
        html += '<p class="md-cb-help">فصل‌ها و جلسه‌ها را همین‌جا و بدون ترک صفحهٔ دوره مدیریت کنید. تغییرات فوراً ذخیره می‌شوند.</p>';

        if (!state.chapters.length) {
            html += '<p class="md-cb-empty-note">' + esc(strings.empty || 'هنوز فصلی ساخته نشده است.') + '</p>';
        }

        state.chapters.forEach(function (chapter, index) {
            html += chapterHtml(chapter, index, state.chapters.length);
        });

        var unassigned = state.lessons.filter(function (lesson) {
            return state.chapters.indexOf(lesson.chapter) === -1;
        });

        if (unassigned.length) {
            html += '<section class="md-cb-chapter md-cb-unassigned">' +
                '<header class="md-cb-chapter-head"><div class="md-cb-chapter-title"><strong>' + esc(strings.unassigned || 'جلسه‌های بدون فصل') + '</strong></div></header>' +
                '<ul class="md-cb-lessons">' +
                unassigned.map(function (lesson, index) {
                    return lessonHtml(lesson, index, unassigned.length);
                }).join('') +
                '</ul></section>';
        }

        html += '<div class="md-cb-chapter-add">' +
            '<input type="text" class="md-cb-new-chapter" placeholder="' + esc(strings.chapterName || 'نام فصل') + '">' +
            '<button type="button" class="button" data-md-cb-add-chapter>' + esc(strings.addChapter || 'افزودن فصل') + '</button>' +
            '</div>';
        html += '<button type="button" class="button button-primary md-cb-add-lesson" data-md-cb-add-lesson data-chapter="">+ ' + esc(strings.addLesson || 'افزودن جلسه') + '</button>';
        html += '</div>';

        return html;
    }

    function renderPanel() {
        var $root = $('#md-course-builder-panel');
        if (!$root.length) {
            return;
        }
        $root.html(panelHtml());
    }

    /* ---------------------------------------------------------- modal logic */

    function populateChapters(selected) {
        var $select = $('#md-lesson-chapter');
        var options = '<option value="">' + esc(strings.noChapter || 'بدون فصل') + '</option>';
        state.chapters.forEach(function (chapter) {
            options += '<option value="' + escAttr(chapter) + '">' + esc(chapter) + '</option>';
        });
        $select.html(options).val(selected || '');
    }

    function destroyEditor() {
        if (window.wp && wp.editor && wp.editor.remove) {
            try {
                wp.editor.remove('md-lesson-content');
            } catch (e) {
                // The editor may not be initialized yet.
            }
        }
        editorReady = false;
    }

    function initEditor(content) {
        destroyEditor();
        $('#md-lesson-content').val(content || '');

        if (!window.wp || !wp.editor || !wp.editor.initialize) {
            return;
        }

        try {
            wp.editor.initialize('md-lesson-content', {
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
            var content = wp.editor.getContent('md-lesson-content');
            if (typeof content === 'string') {
                return content;
            }
        }
        return $('#md-lesson-content').val() || '';
    }

    function setMedia(kind, id, name) {
        $('#md-lesson-' + kind).val(id || 0);
        $('#md-lesson-' + kind + '-name').text(name || '');
    }

    function resetForm() {
        currentLessonId = 0;
        $('#md-lesson-id').val(0);
        $('#md-lesson-title').val('');
        $('#md-lesson-duration').val('');
        $('#md-lesson-summary').val('');
        $('#md-lesson-media-kind').val('text');
        $('#md-lesson-media-title').val('');
        $('#md-lesson-media-url').val('');
        $('#md-lesson-status').val('draft');
        $('#md-lesson-error').text('').prop('hidden', true);
        setMedia('audio', 0, '');
        setMedia('video', 0, '');
    }

    function fillForm(lesson) {
        currentLessonId = parseInt(lesson.id, 10) || 0;
        $('#md-lesson-id').val(currentLessonId);
        $('#md-lesson-title').val(lesson.title || '');
        $('#md-lesson-duration').val(lesson.duration || '');
        $('#md-lesson-summary').val(lesson.summary || '');
        $('#md-lesson-media-kind').val(lesson.mediaKind || 'text');
        $('#md-lesson-media-title').val(lesson.mediaTitle || '');
        $('#md-lesson-media-url').val(lesson.mediaUrl || '');
        $('#md-lesson-status').val(lesson.status === 'publish' ? 'publish' : 'draft');
        setMedia('audio', lesson.audioId || 0, lesson.audioName || '');
        setMedia('video', lesson.videoId || 0, lesson.videoName || '');
        $('#md-lesson-error').text('').prop('hidden', true);
    }

    function openModal(lessonId, chapter) {
        if (!currentCourseId()) {
            window.alert('ابتدا دوره را ذخیره کنید.');
            return;
        }

        var content = '';
        var chapterValue = chapter || '';

        if (lessonId) {
            var lesson = null;
            state.lessons.forEach(function (item) {
                if (parseInt(item.id, 10) === parseInt(lessonId, 10)) {
                    lesson = item;
                }
            });
            if (!lesson) {
                return;
            }
            resetForm();
            fillForm(lesson);
            content = lesson.content || '';
            chapterValue = lesson.chapter || '';
        } else {
            resetForm();
        }

        populateChapters(chapterValue);

        $('#md-lesson-modal-title').text(lessonId ? (strings.editorTitle || 'ویرایش جلسه') : (strings.editorNew || 'جلسهٔ تازه'));
        $('#md-lesson-modal').prop('hidden', false).attr('aria-hidden', 'false');
        $('body').addClass('md-cb-modal-open');

        // The editor must be visible before TinyMCE measures its container.
        initEditor(content);

        window.setTimeout(function () {
            $('#md-lesson-title').trigger('focus');
        }, 80);
    }

    function closeModal() {
        $('#md-lesson-modal').prop('hidden', true).attr('aria-hidden', 'true');
        $('body').removeClass('md-cb-modal-open');
        destroyEditor();
    }

    function saveLesson() {
        var title = $.trim($('#md-lesson-title').val() || '');
        if (!title) {
            showModalError('عنوان جلسه را وارد کنید.');
            return;
        }

        var $button = $('#md-lesson-save');
        $button.prop('disabled', true);
        $('#md-lesson-spinner').prop('hidden', false);
        $('#md-lesson-error').prop('hidden', true);

        request('md_course_builder_save_lesson', {
            lesson_id: currentLessonId,
            title: title,
            chapter: $('#md-lesson-chapter').val() || '',
            duration: $('#md-lesson-duration').val() || '',
            summary: $('#md-lesson-summary').val() || '',
            content: getEditorContent(),
            media_kind: $('#md-lesson-media-kind').val() || '',
            media_title: $('#md-lesson-media-title').val() || '',
            media_url: $('#md-lesson-media-url').val() || '',
            audio_id: $('#md-lesson-audio').val() || 0,
            video_id: $('#md-lesson-video').val() || 0,
            status: $('#md-lesson-status').val() || 'draft'
        }).done(function (response) {
            if (response && response.success) {
                applyData(response.data);
                closeModal();
                toast(strings.saved || 'ذخیره شد.');
            } else {
                showModalError(errorMessage(response));
            }
        }).fail(function (xhr) {
            showModalError(errorMessage(xhr.responseJSON));
        }).always(function () {
            $button.prop('disabled', false);
            $('#md-lesson-spinner').prop('hidden', true);
        });
    }

    function showModalError(message) {
        $('#md-lesson-error').text(message || strings.error || 'خطایی رخ داد.').prop('hidden', false);
    }

    function deleteLesson(lessonId) {
        if (!window.confirm(strings.confirmDeleteLesson || 'این جلسه حذف شود؟')) {
            return;
        }
        request('md_course_builder_delete_lesson', { lesson_id: lessonId })
            .done(function (response) {
                if (response && response.success) {
                    applyData(response.data);
                    toast(strings.saved || 'ذخیره شد.');
                } else {
                    toast(errorMessage(response), true);
                }
            })
            .fail(function (xhr) {
                toast(errorMessage(xhr.responseJSON), true);
            });
    }

    function moveLesson(lessonId, direction) {
        request('md_course_builder_move_lesson', { lesson_id: lessonId, direction: direction })
            .done(function (response) {
                if (response && response.success) {
                    applyData(response.data);
                } else {
                    toast(errorMessage(response), true);
                }
            })
            .fail(function (xhr) {
                toast(errorMessage(xhr.responseJSON), true);
            });
    }

    function saveChapters(chapters) {
        request('md_course_builder_save_chapters', { chapters: chapters })
            .done(function (response) {
                if (response && response.success) {
                    applyData(response.data);
                    toast(strings.saved || 'ذخیره شد.');
                } else {
                    toast(errorMessage(response), true);
                }
            })
            .fail(function (xhr) {
                toast(errorMessage(xhr.responseJSON), true);
            });
    }

    function addChapter() {
        var name = $.trim($('.md-cb-new-chapter').val() || '');
        if (!name) {
            $('.md-cb-new-chapter').trigger('focus');
            return;
        }
        var chapters = state.chapters.slice();
        chapters.push(name);
        saveChapters(chapters);
    }

    function renameChapter(oldName) {
        var name = window.prompt(strings.chapterName || 'نام فصل', oldName);
        if (name === null) {
            return;
        }
        name = $.trim(name);
        if (!name || name === oldName) {
            return;
        }

        request('md_course_builder_rename_chapter', { old: oldName, new: name })
            .done(function (response) {
                if (response && response.success) {
                    applyData(response.data);
                    toast(strings.saved || 'ذخیره شد.');
                } else {
                    toast(errorMessage(response), true);
                }
            })
            .fail(function (xhr) {
                toast(errorMessage(xhr.responseJSON), true);
            });
    }

    function moveChapter(index, direction) {
        var target = direction === 'up' ? index - 1 : index + 1;
        if (target < 0 || target >= state.chapters.length) {
            return;
        }
        var chapters = state.chapters.slice();
        var temp = chapters[index];
        chapters[index] = chapters[target];
        chapters[target] = temp;
        saveChapters(chapters);
    }

    function deleteChapter(name) {
        if (!window.confirm(strings.confirmDeleteChapter || 'این فصل حذف شود؟')) {
            return;
        }
        var chapters = state.chapters.filter(function (chapter) {
            return chapter !== name;
        });
        saveChapters(chapters);
    }

    /* ---------------------------------------------------------- panel mount */

    function registerPanel() {
        if (!window.wp || !wp.plugins || !wp.element || !wp.data) {
            return false;
        }

        var el = wp.element.createElement;
        var registerPlugin = wp.plugins.registerPlugin;
        var PluginDocumentSettingPanel =
            (wp.editPost && wp.editPost.PluginDocumentSettingPanel) ||
            (wp.editor && wp.editor.PluginDocumentSettingPanel);
        var useSelect = wp.data.useSelect;

        if (!PluginDocumentSettingPanel || !useSelect) {
            return false;
        }

        function Panel() {
            var postType = useSelect(function (select) {
                return select('core/editor').getCurrentPostType();
            }, []);

            if ('md_course' !== postType) {
                return null;
            }

            return el(
                PluginDocumentSettingPanel,
                {
                    name: 'md-course-builder-slot',
                    title: 'ساخت دوره (فصل‌ها و جلسه‌ها)',
                    className: 'md-course-builder-slot',
                    initialOpen: true
                },
                el('div', {
                    id: 'md-course-builder-panel',
                    className: 'md-cb-panel-root',
                    dangerouslySetInnerHTML: { __html: '' }
                })
            );
        }

        registerPlugin('md-course-builder', { render: Panel });
        return true;
    }

    function legacyMount() {
        if ($('body').hasClass('block-editor-page')) {
            return;
        }
        var $side = $('#postbox-container-1 #side-sortables');
        if (!$side.length || $('#md-course-builder-panel').length) {
            return;
        }
        $side.prepend(
            '<div class="postbox md-cb-legacy">' +
                '<h2 class="hndle"><span>ساخت دوره (فصل‌ها و جلسه‌ها)</span></h2>' +
                '<div class="inside"><div id="md-course-builder-panel"></div></div>' +
            '</div>'
        );
    }

    function ensurePanel() {
        legacyMount();
        var $root = $('#md-course-builder-panel');
        if (!$root.length) {
            return;
        }
        if (!$root.children().length) {
            loadData();
        }
    }

    /* ------------------------------------------------------------- handlers */

    $(document).on('click', '#md-course-builder-panel [data-md-cb-add-lesson]', function (event) {
        event.preventDefault();
        openModal(0, $(this).data('chapter') || '');
    });

    $(document).on('click', '#md-course-builder-panel [data-md-cb-edit-lesson]', function (event) {
        event.preventDefault();
        openModal($(this).closest('.md-cb-lesson').data('lesson-id'));
    });

    $(document).on('click', '#md-course-builder-panel [data-md-cb-delete-lesson]', function (event) {
        event.preventDefault();
        deleteLesson($(this).closest('.md-cb-lesson').data('lesson-id'));
    });

    $(document).on('click', '#md-course-builder-panel [data-md-cb-move-lesson]', function (event) {
        event.preventDefault();
        moveLesson(
            $(this).closest('.md-cb-lesson').data('lesson-id'),
            $(this).data('md-cb-move-lesson')
        );
    });

    $(document).on('click', '#md-course-builder-panel [data-md-cb-add-chapter]', function (event) {
        event.preventDefault();
        addChapter();
    });

    $(document).on('keydown', '#md-course-builder-panel .md-cb-new-chapter', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            addChapter();
        }
    });

    $(document).on('click', '#md-course-builder-panel [data-md-cb-rename-chapter]', function (event) {
        event.preventDefault();
        renameChapter($(this).closest('.md-cb-chapter').data('chapter'));
    });

    $(document).on('click', '#md-course-builder-panel [data-md-cb-delete-chapter]', function (event) {
        event.preventDefault();
        deleteChapter($(this).closest('.md-cb-chapter').data('chapter'));
    });

    $(document).on('click', '#md-course-builder-panel [data-md-cb-chapter-move]', function (event) {
        event.preventDefault();
        var $chapter = $(this).closest('.md-cb-chapter');
        var index = state.chapters.indexOf($chapter.data('chapter'));
        if (index !== -1) {
            moveChapter(index, $(this).data('md-cb-chapter-move'));
        }
    });

    $(document).on('click', '#md-lesson-save', function (event) {
        event.preventDefault();
        saveLesson();
    });

    $(document).on('click', '.md-cb-modal-close, [data-md-cb-close]', function (event) {
        event.preventDefault();
        closeModal();
    });

    $(document).on('click', '[data-md-cb-media]', function (event) {
        event.preventDefault();
        if (!window.wp || !wp.media) {
            return;
        }
        var kind = $(this).data('md-cb-media');
        var type = $(this).data('type') || kind;
        var frame = wp.media({
            title: strings.chooseMedia || 'انتخاب فایل',
            button: { text: 'استفاده از این فایل' },
            multiple: false,
            library: type ? { type: type } : {}
        });
        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            setMedia(kind, attachment.id, attachment.filename || attachment.title || '');
        });
        frame.open();
    });

    $(document).on('click', '[data-md-cb-clear]', function (event) {
        event.preventDefault();
        setMedia($(this).data('md-cb-clear'), 0, '');
    });

    $(document).on('keydown', function (event) {
        if (event.key === 'Escape' && !$('#md-lesson-modal').prop('hidden')) {
            closeModal();
        }
    });

    $(function () {
        registerPanel();
        window.setInterval(ensurePanel, 700);
        ensurePanel();
    });
})(jQuery);
