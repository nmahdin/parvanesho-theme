<?php
/**
 * In-editor course builder: chapters and lessons.
 *
 * Everything is edited from the course screen. The Gutenberg sidebar panel
 * lists chapters and lessons, and the lesson modal edits the title, chapter,
 * content and media without leaving the course editor.
 *
 * @package Morvarid_Daron
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Chapters of a course, in display order.
 *
 * @param int $course_id Course ID.
 * @return string[]
 */
function md_course_chapters($course_id) {
    $course_id = absint($course_id);
    if (!$course_id) {
        return array();
    }

    $stored = get_post_meta($course_id, '_md_course_chapters', true);
    if (!is_array($stored)) {
        return array();
    }

    $chapters = array();
    foreach ($stored as $chapter) {
        if (!is_scalar($chapter)) {
            continue;
        }
        $chapter = sanitize_text_field($chapter);
        if ('' !== $chapter && !in_array($chapter, $chapters, true)) {
            $chapters[] = $chapter;
        }
    }

    return $chapters;
}

/**
 * Save the chapter list.
 *
 * @param int      $course_id Course ID.
 * @param string[] $chapters  Chapter titles.
 */
function md_course_set_chapters($course_id, $chapters) {
    $course_id = absint($course_id);
    if (!$course_id) {
        return;
    }

    $clean = array();
    foreach ((array) $chapters as $chapter) {
        if (!is_scalar($chapter)) {
            continue;
        }
        $chapter = sanitize_text_field($chapter);
        if ('' !== $chapter && !in_array($chapter, $clean, true)) {
            $clean[] = $chapter;
        }
    }
    $clean = array_slice($clean, 0, 60);

    update_post_meta($course_id, '_md_course_chapters', $clean);
}

/**
 * Chapter of one lesson.
 *
 * @param int $lesson_id Lesson ID.
 * @return string
 */
function md_lesson_chapter($lesson_id) {
    $chapter = get_post_meta($lesson_id, '_md_lesson_chapter', true);
    return is_scalar($chapter) ? sanitize_text_field($chapter) : '';
}

/**
 * Sort lessons by chapter order, then by their own order.
 *
 * @param WP_Post[] $lessons  Lessons.
 * @param string[]  $chapters Chapter list.
 * @return WP_Post[]
 */
function md_course_sort_lessons($lessons, $chapters) {
    $positions = array_flip(array_values($chapters));

    usort($lessons, function ($a, $b) use ($positions) {
        $chapter_a = md_lesson_chapter($a->ID);
        $chapter_b = md_lesson_chapter($b->ID);

        $pos_a = isset($positions[$chapter_a]) ? $positions[$chapter_a] : PHP_INT_MAX;
        $pos_b = isset($positions[$chapter_b]) ? $positions[$chapter_b] : PHP_INT_MAX;

        if ($pos_a !== $pos_b) {
            return $pos_a <=> $pos_b;
        }

        // 0 means "never ordered"; those lessons go last, not first.
        $order_a = (int) get_post_meta($a->ID, '_md_lesson_order', true);
        $order_b = (int) get_post_meta($b->ID, '_md_lesson_order', true);
        $order_a = $order_a ? $order_a : PHP_INT_MAX;
        $order_b = $order_b ? $order_b : PHP_INT_MAX;
        if ($order_a !== $order_b) {
            return $order_a <=> $order_b;
        }

        return strcmp($a->post_date, $b->post_date);
    });

    return $lessons;
}

/**
 * Give legacy lessons an order value.
 *
 * md_course_lessons() joins on _md_lesson_order, so a lesson created before
 * the builder existed would otherwise stay invisible.
 *
 * @param int $course_id Course ID.
 */
function md_course_ensure_lesson_order($course_id) {
    $course_id = absint($course_id);
    if (!$course_id) {
        return;
    }

    $ids = get_posts(
        array(
            'post_type'      => 'md_lesson',
            'post_status'    => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => array(
                array(
                    'key'     => '_md_course_id',
                    'value'   => $course_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ),
            ),
        )
    );

    foreach ($ids as $lesson_id) {
        if ('' === get_post_meta($lesson_id, '_md_lesson_order', true)) {
            update_post_meta($lesson_id, '_md_lesson_order', 9999);
        }
    }
}

/**
 * Re-number lessons so chapter order and lesson order stay in sync.
 *
 * @param int $course_id Course ID.
 */
function md_course_reorder_lessons($course_id) {
    md_course_ensure_lesson_order($course_id);

    $chapters = md_course_chapters($course_id);
    $lessons  = md_course_lessons($course_id);

    if (!$lessons) {
        return;
    }

    $lessons = md_course_sort_lessons($lessons, $chapters);
    foreach ($lessons as $index => $lesson) {
        update_post_meta($lesson->ID, '_md_lesson_order', $index + 1);
    }
}

/**
 * Lesson data used by the builder.
 *
 * @param WP_Post $lesson Lesson post.
 * @return array
 */
function md_course_builder_lesson_payload($lesson) {
    $audio_id = absint(get_post_meta($lesson->ID, '_md_media_audio_id', true));
    $video_id = absint(get_post_meta($lesson->ID, '_md_media_video_id', true));

    return array(
        'id'          => $lesson->ID,
        'title'       => get_the_title($lesson),
        'chapter'     => md_lesson_chapter($lesson->ID),
        'order'       => (int) get_post_meta($lesson->ID, '_md_lesson_order', true),
        'status'      => $lesson->post_status,
        'summary'     => $lesson->post_excerpt,
        'content'     => $lesson->post_content,
        'duration'    => (string) get_post_meta($lesson->ID, '_md_duration', true),
        'mediaKind'   => (string) get_post_meta($lesson->ID, '_md_media_kind', true),
        'mediaTitle'  => (string) get_post_meta($lesson->ID, '_md_media_title', true),
        'mediaUrl'    => (string) get_post_meta($lesson->ID, '_md_media_url', true),
        'audioId'     => $audio_id,
        'audioName'   => md_attachment_name($audio_id),
        'videoId'     => $video_id,
        'videoName'   => md_attachment_name($video_id),
    );
}

/**
 * Whole builder state for a course.
 *
 * @param int $course_id Course ID.
 * @return array
 */
function md_course_builder_data($course_id) {
    md_course_ensure_lesson_order($course_id);

    $chapters = md_course_chapters($course_id);
    $lessons  = md_course_sort_lessons(md_course_lessons($course_id), $chapters);

    $items = array();
    foreach ($lessons as $lesson) {
        $items[] = md_course_builder_lesson_payload($lesson);
    }

    return array(
        'courseId' => $course_id,
        'chapters' => $chapters,
        'lessons'  => $items,
        'editUrl'  => get_edit_post_link($course_id, 'raw'),
    );
}

/**
 * Validate the builder AJAX request.
 *
 * @return int Course ID.
 */
function md_course_builder_verify() {
    check_ajax_referer('md_course_builder', 'nonce');

    $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
    if (!$course_id || 'md_course' !== get_post_type($course_id)) {
        wp_send_json_error(array('message' => 'دورهٔ نامعتبر است.'), 400);
    }
    if (!current_user_can('edit_post', $course_id)) {
        wp_send_json_error(array('message' => 'اجازهٔ ویرایش این دوره را ندارید.'), 403);
    }

    return $course_id;
}

/**
 * Response helper.
 *
 * @param int $course_id Course ID.
 */
function md_course_builder_send_data($course_id) {
    wp_send_json_success(md_course_builder_data($course_id));
}

/* ------------------------------------------------------------------- ajax */

/**
 * Return the current chapters and lessons.
 */
function md_course_builder_ajax_get() {
    $course_id = md_course_builder_verify();
    md_course_builder_send_data($course_id);
}
add_action('wp_ajax_md_course_builder_get', 'md_course_builder_ajax_get');

/**
 * Create or update a lesson.
 */
function md_course_builder_ajax_save_lesson() {
    $course_id = md_course_builder_verify();

    $lesson_id = isset($_POST['lesson_id']) ? absint($_POST['lesson_id']) : 0;
    $title     = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
    if ('' === $title) {
        wp_send_json_error(array('message' => 'عنوان جلسه را وارد کنید.'), 400);
    }

    if ($lesson_id) {
        $lesson = get_post($lesson_id);
        if (!$lesson || 'md_lesson' !== $lesson->post_type || (int) get_post_meta($lesson_id, '_md_course_id', true) !== $course_id) {
            wp_send_json_error(array('message' => 'این جلسه به دورهٔ جاری تعلق ندارد.'), 400);
        }
    }

    $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
    $summary = isset($_POST['summary']) ? sanitize_textarea_field(wp_unslash($_POST['summary'])) : '';
    $chapter = isset($_POST['chapter']) ? sanitize_text_field(wp_unslash($_POST['chapter'])) : '';
    $status  = (isset($_POST['status']) && 'publish' === $_POST['status']) ? 'publish' : 'draft';

    if ('publish' === $status && !current_user_can('publish_posts')) {
        wp_send_json_error(array('message' => 'اجازهٔ انتشار جلسه را ندارید.'), 403);
    }

    $postarr = array(
        'post_type'    => 'md_lesson',
        'post_title'   => $title,
        'post_content' => $content,
        'post_excerpt' => $summary,
        'post_status'  => $status,
    );

    if ($lesson_id) {
        $postarr['ID'] = $lesson_id;
        $saved         = wp_update_post($postarr, true);
    } else {
        $saved = wp_insert_post($postarr, true);
    }

    if (is_wp_error($saved) || !$saved) {
        wp_send_json_error(array('message' => 'ذخیرهٔ جلسه ممکن نشد.'), 500);
    }

    $lesson_id = (int) $saved;

    update_post_meta($lesson_id, '_md_course_id', $course_id);

    if ('' !== $chapter) {
        update_post_meta($lesson_id, '_md_lesson_chapter', $chapter);
        $chapters = md_course_chapters($course_id);
        if (!in_array($chapter, $chapters, true)) {
            $chapters[] = $chapter;
            md_course_set_chapters($course_id, $chapters);
        }
    } else {
        delete_post_meta($lesson_id, '_md_lesson_chapter');
    }

    $meta = array(
        '_md_duration'        => isset($_POST['duration']) ? sanitize_text_field(wp_unslash($_POST['duration'])) : '',
        '_md_media_title'     => isset($_POST['media_title']) ? sanitize_text_field(wp_unslash($_POST['media_title'])) : '',
        '_md_media_url'       => isset($_POST['media_url']) ? esc_url_raw(wp_unslash($_POST['media_url'])) : '',
    );

    $kind = isset($_POST['media_kind']) ? sanitize_key($_POST['media_kind']) : '';
    $meta['_md_media_kind'] = in_array($kind, array('video', 'audio', 'text'), true) ? $kind : '';

    $meta['_md_media_audio_id'] = isset($_POST['audio_id']) ? absint($_POST['audio_id']) : 0;
    $meta['_md_media_video_id'] = isset($_POST['video_id']) ? absint($_POST['video_id']) : 0;

    foreach ($meta as $key => $value) {
        if ('' === $value || 0 === $value || '0' === $value) {
            delete_post_meta($lesson_id, $key);
        } else {
            update_post_meta($lesson_id, $key, $value);
        }
    }

    if (!get_post_meta($lesson_id, '_md_lesson_order', true)) {
        update_post_meta($lesson_id, '_md_lesson_order', 9999);
    }

    // An auto-draft course would otherwise be cleaned up with its lessons.
    if ('auto-draft' === get_post_status($course_id)) {
        wp_update_post(array('ID' => $course_id, 'post_status' => 'draft'));
    }

    md_course_reorder_lessons($course_id);
    md_course_builder_send_data($course_id);
}
add_action('wp_ajax_md_course_builder_save_lesson', 'md_course_builder_ajax_save_lesson');

/**
 * Delete a lesson.
 */
function md_course_builder_ajax_delete_lesson() {
    $course_id = md_course_builder_verify();
    $lesson_id = isset($_POST['lesson_id']) ? absint($_POST['lesson_id']) : 0;
    $lesson    = $lesson_id ? get_post($lesson_id) : null;

    if (!$lesson || 'md_lesson' !== $lesson->post_type || (int) get_post_meta($lesson_id, '_md_course_id', true) !== $course_id) {
        wp_send_json_error(array('message' => 'جلسه پیدا نشد.'), 400);
    }

    wp_delete_post($lesson_id, true);
    md_course_reorder_lessons($course_id);
    md_course_builder_send_data($course_id);
}
add_action('wp_ajax_md_course_builder_delete_lesson', 'md_course_builder_ajax_delete_lesson');

/**
 * Move a lesson up or down inside its chapter.
 */
function md_course_builder_ajax_move_lesson() {
    $course_id = md_course_builder_verify();
    $lesson_id = isset($_POST['lesson_id']) ? absint($_POST['lesson_id']) : 0;
    $direction = (isset($_POST['direction']) && 'up' === $_POST['direction']) ? 'up' : 'down';

    $lessons = md_course_lessons($course_id);
    $chapter = md_lesson_chapter($lesson_id);
    $siblings = array_values(array_filter($lessons, function ($lesson) use ($chapter) {
        return md_lesson_chapter($lesson->ID) === $chapter;
    }));

    $index = false;
    foreach ($siblings as $key => $lesson) {
        if ((int) $lesson->ID === $lesson_id) {
            $index = $key;
            break;
        }
    }

    if (false === $index) {
        wp_send_json_error(array('message' => 'جلسه پیدا نشد.'), 400);
    }

    $target = 'up' === $direction ? $index - 1 : $index + 1;
    if ($target < 0 || $target >= count($siblings)) {
        md_course_builder_send_data($course_id);
    }

    $other = $siblings[$target];
    $mine  = $siblings[$index];

    $my_order    = (int) get_post_meta($mine->ID, '_md_lesson_order', true);
    $other_order = (int) get_post_meta($other->ID, '_md_lesson_order', true);

    update_post_meta($mine->ID, '_md_lesson_order', $other_order);
    update_post_meta($other->ID, '_md_lesson_order', $my_order);

    md_course_reorder_lessons($course_id);
    md_course_builder_send_data($course_id);
}
add_action('wp_ajax_md_course_builder_move_lesson', 'md_course_builder_ajax_move_lesson');

/**
 * Save the whole chapter list (order, additions, deletions).
 */
function md_course_builder_ajax_save_chapters() {
    $course_id = md_course_builder_verify();
    $chapters  = isset($_POST['chapters']) ? (array) wp_unslash($_POST['chapters']) : array();

    md_course_set_chapters($course_id, $chapters);
    $saved = md_course_chapters($course_id);

    // Lessons that pointed at a removed chapter become unassigned.
    $lessons = md_course_lessons($course_id);
    foreach ($lessons as $lesson) {
        $chapter = md_lesson_chapter($lesson->ID);
        if ('' !== $chapter && !in_array($chapter, $saved, true)) {
            delete_post_meta($lesson->ID, '_md_lesson_chapter');
        }
    }

    md_course_reorder_lessons($course_id);
    md_course_builder_send_data($course_id);
}
add_action('wp_ajax_md_course_builder_save_chapters', 'md_course_builder_ajax_save_chapters');

/**
 * Rename a chapter and move its lessons with it.
 */
function md_course_builder_ajax_rename_chapter() {
    $course_id = md_course_builder_verify();
    $old       = isset($_POST['old']) ? sanitize_text_field(wp_unslash($_POST['old'])) : '';
    $new       = isset($_POST['new']) ? sanitize_text_field(wp_unslash($_POST['new'])) : '';

    if ('' === $old || '' === $new) {
        wp_send_json_error(array('message' => 'عنوان فصل را وارد کنید.'), 400);
    }

    $chapters = md_course_chapters($course_id);
    $found    = array_search($old, $chapters, true);
    if (false === $found) {
        wp_send_json_error(array('message' => 'فصل پیدا نشد.'), 400);
    }

    if ($new !== $old) {
        $existing = array_search($new, $chapters, true);
        if (false !== $existing) {
            // Merging into an existing chapter: drop the old entry.
            unset($chapters[$found]);
        } else {
            $chapters[$found] = $new;
        }

        $lessons = md_course_lessons($course_id);
        foreach ($lessons as $lesson) {
            if (md_lesson_chapter($lesson->ID) === $old) {
                update_post_meta($lesson->ID, '_md_lesson_chapter', $new);
            }
        }
    }

    md_course_set_chapters($course_id, array_values($chapters));
    md_course_reorder_lessons($course_id);
    md_course_builder_send_data($course_id);
}
add_action('wp_ajax_md_course_builder_rename_chapter', 'md_course_builder_ajax_rename_chapter');

/* ------------------------------------------------------------------- admin */

/**
 * Load the builder assets on the course screen.
 *
 * @param string $hook Current admin page.
 */
function md_course_builder_admin_assets($hook) {
    if (!in_array($hook, array('post.php', 'post-new.php'), true)) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || 'md_course' !== $screen->post_type) {
        return;
    }

    $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;

    wp_enqueue_media();
    wp_enqueue_editor();
    wp_enqueue_style('md-admin-fields', MD_THEME_URI . '/assets/css/admin-fields.css', array(), MD_THEME_VERSION);
    wp_enqueue_style('md-course-builder', MD_THEME_URI . '/assets/css/course-builder.css', array('md-admin-fields'), MD_THEME_VERSION);
    wp_enqueue_script(
        'md-course-builder',
        MD_THEME_URI . '/assets/js/course-builder.js',
        array('jquery', 'wp-editor', 'wp-media', 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n'),
        MD_THEME_VERSION,
        true
    );

    wp_localize_script(
        'md-course-builder',
        'mdCourseBuilder',
        array(
            'ajaxUrl'  => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('md_course_builder'),
            'courseId' => $post_id,
            'i18n'     => array(
                'chapter'        => 'فصل',
                'noChapter'      => 'بدون فصل',
                'edit'           => 'ویرایش',
                'delete'         => 'حذف',
                'addLesson'      => 'افزودن جلسه',
                'addLessonHere'  => 'افزودن جلسه به این فصل',
                'addChapter'     => 'افزودن فصل',
                'renameChapter'  => 'تغییر نام فصل',
                'deleteChapter'  => 'حذف فصل',
                'chapterName'    => 'نام فصل',
                'confirmDeleteLesson'  => 'این جلسه و محتوای آن برای همیشه حذف شود؟',
                'confirmDeleteChapter' => 'این فصل حذف شود؟ جلسه‌های آن به «بدون فصل» منتقل می‌شوند.',
                'saved'          => 'ذخیره شد.',
                'error'          => 'خطایی رخ داد. دوباره تلاش کنید.',
                'empty'          => 'هنوز فصلی ساخته نشده است. با «افزودن فصل» شروع کنید.',
                'noLessons'      => 'هنوز جلسه‌ای در این فصل نیست.',
                'loading'        => 'در حال بارگذاری…',
                'editorTitle'    => 'ویرایش جلسه',
                'editorNew'      => 'جلسهٔ تازه',
                'publish'        => 'منتشرشده',
                'draft'          => 'پیش‌نویس',
                'unassigned'     => 'جلسه‌های بدون فصل',
                'chooseMedia'    => 'انتخاب فایل',
                'save'           => 'ذخیرهٔ جلسه',
                'saving'         => 'در حال ذخیره…',
            ),
        )
    );
}
add_action('admin_enqueue_scripts', 'md_course_builder_admin_assets');

/**
 * Print the lesson modal on course screens.
 */
function md_course_builder_print_modal() {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    // Only the course editing screen needs the modal (not the courses list).
    if (!$screen || 'post' !== $screen->base || 'md_course' !== $screen->post_type) {
        return;
    }
    ?>
    <div id="md-lesson-modal" class="md-cb-modal" hidden aria-hidden="true">
        <div class="md-cb-modal-overlay" data-md-cb-close="1"></div>
        <div class="md-cb-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="md-lesson-modal-title">
            <header class="md-cb-modal-header">
                <h2 id="md-lesson-modal-title">ویرایش جلسه</h2>
                <button type="button" class="button-link md-cb-modal-close" aria-label="بستن">&times;</button>
            </header>

            <div class="md-cb-modal-body">
                <div class="md-cb-error" id="md-lesson-error" role="alert" hidden></div>

                <input type="hidden" id="md-lesson-id" value="0">

                <div class="md-cb-grid">
                    <div class="md-cb-field md-cb-field-wide">
                        <label for="md-lesson-title">عنوان جلسه <span class="md-cb-required">*</span></label>
                        <input type="text" id="md-lesson-title" class="large-text" required>
                    </div>

                    <div class="md-cb-field">
                        <label for="md-lesson-chapter">فصل</label>
                        <select id="md-lesson-chapter">
                            <option value="">بدون فصل</option>
                        </select>
                    </div>

                    <div class="md-cb-field">
                        <label for="md-lesson-duration">مدت جلسه</label>
                        <input type="text" id="md-lesson-duration" placeholder="مثلاً ۱۸ دقیقه">
                    </div>

                    <div class="md-cb-field md-cb-field-wide">
                        <label for="md-lesson-summary">خلاصه</label>
                        <textarea id="md-lesson-summary" rows="2" class="large-text"></textarea>
                    </div>

                    <div class="md-cb-field md-cb-field-wide">
                        <label for="md-lesson-content">متن جلسه</label>
                        <?php wp_editor('', 'md-lesson-content', array('textarea_rows' => 10, 'media_buttons' => true, 'teeny' => false)); ?>
                    </div>

                    <div class="md-cb-field">
                        <label for="md-lesson-media-kind">نوع محتوا</label>
                        <select id="md-lesson-media-kind">
                            <option value="text">متنی</option>
                            <option value="video">ویدیویی</option>
                            <option value="audio">صوتی</option>
                        </select>
                    </div>

                    <div class="md-cb-field">
                        <label for="md-lesson-media-title">عنوان رسانه</label>
                        <input type="text" id="md-lesson-media-title">
                    </div>

                    <div class="md-cb-field">
                        <label for="md-lesson-audio">فایل صوتی</label>
                        <div class="md-cb-media">
                            <input type="hidden" id="md-lesson-audio" value="0">
                            <button type="button" class="button" data-md-cb-media="audio" data-type="audio">انتخاب فایل صوتی</button>
                            <button type="button" class="button-link" data-md-cb-clear="audio">حذف</button>
                            <span id="md-lesson-audio-name" class="md-cb-media-name"></span>
                        </div>
                    </div>

                    <div class="md-cb-field">
                        <label for="md-lesson-video">فایل ویدیو</label>
                        <div class="md-cb-media">
                            <input type="hidden" id="md-lesson-video" value="0">
                            <button type="button" class="button" data-md-cb-media="video" data-type="video">انتخاب فایل ویدیو</button>
                            <button type="button" class="button-link" data-md-cb-clear="video">حذف</button>
                            <span id="md-lesson-video-name" class="md-cb-media-name"></span>
                        </div>
                    </div>

                    <div class="md-cb-field">
                        <label for="md-lesson-media-url">یا لینک ویدیوی خارجی</label>
                        <input type="url" id="md-lesson-media-url" dir="ltr" placeholder="https://">
                    </div>

                    <div class="md-cb-field">
                        <label for="md-lesson-status">وضعیت</label>
                        <select id="md-lesson-status">
                            <option value="draft">پیش‌نویس</option>
                            <option value="publish">منتشرشده</option>
                        </select>
                    </div>
                </div>
            </div>

            <footer class="md-cb-modal-footer">
                <button type="button" class="button button-primary" id="md-lesson-save">ذخیرهٔ جلسه</button>
                <button type="button" class="button md-cb-modal-close">انصراف</button>
                <span class="md-cb-spinner" id="md-lesson-spinner" hidden>در حال ذخیره…</span>
            </footer>
        </div>
    </div>
    <?php
}
add_action('admin_footer', 'md_course_builder_print_modal');
