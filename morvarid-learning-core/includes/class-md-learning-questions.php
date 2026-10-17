<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Phase D: a moveable Gutenberg question block with reflective and private
 * answer modes. Question definitions live in post_content; learner answers
 * NEVER do. Existing content/media blocks remain freely composable.
 */
final class MD_Learning_Questions {
    const BLOCK = 'morvarid/learning-questions';
    const TYPES = array('md_resource', 'md_podcast', 'md_lesson');
    private static $normalizing = false;

    public static function init() {
        wp_register_script(
            'md-learning-question-editor',
            MD_LEARNING_CORE_URL . 'assets/questions-editor.js',
            array('wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor'),
            MD_LEARNING_CORE_VERSION,
            true
        );
        wp_register_style('md-learning-question-editor-style',
            MD_LEARNING_CORE_URL . 'assets/questions.css', array(), MD_LEARNING_CORE_VERSION);
        register_block_type(self::BLOCK, array(
            'api_version' => 2,
            'title' => 'پرسش‌های آموزشی مروارید',
            'description' => 'مجموعه پرسش‌های تأملی یا پاسخ‌های خصوصی قابل ذخیره',
            'category' => 'widgets',
            'icon' => 'editor-help',
            'editor_script' => 'md-learning-question-editor',
            'editor_style' => 'md-learning-question-editor-style',
            'attributes' => array(
                'blockId' => array('type' => 'string', 'default' => ''),
                'title' => array('type' => 'string', 'default' => 'مکثی برای خودشناسی'),
                'description' => array('type' => 'string', 'default' => ''),
                'mode' => array('type' => 'string', 'default' => 'reflection'),
                'appearance' => array('type' => 'string', 'default' => 'card'),
                'allowEdit' => array('type' => 'boolean', 'default' => true),
                'questions' => array('type' => 'array', 'default' => array()),
            ),
            'render_callback' => array(__CLASS__, 'render'),
            'supports' => array('html' => false, 'multiple' => true),
        ));
        add_action('wp_after_insert_post', array(__CLASS__, 'normalize_post'), 20, 3);
        add_action('admin_post_md_learning_save_questions', array(__CLASS__, 'save'));
        add_action('admin_post_nopriv_md_learning_save_questions', array(__CLASS__, 'save'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'assets'));
        add_action('template_redirect', array(__CLASS__, 'private_headers'), 3);
    }

    private static function is_supported($content_id, $published = false) {
        return in_array(get_post_type($content_id), self::TYPES, true)
            && (!$published || get_post_status($content_id) === 'publish');
    }

    public static function uuid() {
        return bin2hex(random_bytes(16));
    }

    public static function valid_uuid($id) {
        return is_string($id) && preg_match('/^[a-f0-9]{32}$/D', $id);
    }

    public static function clean_questions($questions) {
        if (!is_array($questions)) {
            return array();
        }
        $out = array();
        foreach ($questions as $question) {
            if (!is_array($question) || !isset($question['id'], $question['text'])
                || !self::valid_uuid($question['id'])) {
                continue;
            }
            $text = trim(sanitize_textarea_field($question['text']));
            if ($text === '') {
                continue;
            }
            $out[] = array(
                'id' => $question['id'],
                'text' => function_exists('mb_substr') ? mb_substr($text, 0, 1500) : substr($text, 0, 6000),
                'hint' => sanitize_textarea_field(isset($question['hint']) ? $question['hint'] : ''),
                'type' => isset($question['type']) && $question['type'] === 'short' ? 'short' : 'long',
                'required' => !empty($question['required']),
            );
        }
        return $out;
    }

    /**
     * On save assign IDs to new blocks and IDs duplicated through Gutenberg
     * copy/paste. Existing IDs remain stable on move or text-only edits.
     */
    public static function normalize_post($post_id, $post, $update) {
        if (self::$normalizing || !$post || !self::is_supported($post_id)
            || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)
            || !is_string($post->post_content) || strpos($post->post_content, 'morvarid/learning-questions') === false) {
            return;
        }
        $blocks = parse_blocks($post->post_content);
        $seen_blocks = array();
        $seen_questions = array();
        $changed = false;
        self::normalize_blocks($blocks, $seen_blocks, $seen_questions, $changed);
        if (!$changed) {
            return;
        }
        self::$normalizing = true;
        $result = wp_update_post(array(
            'ID' => $post_id,
            'post_content' => serialize_blocks($blocks),
        ), true);
        self::$normalizing = false;
        if (is_wp_error($result)) {
            error_log('Morvarid Learning Core: could not normalize question IDs on content ' . absint($post_id));
        }
    }

    private static function normalize_blocks(&$blocks, &$seen_blocks, &$seen_questions, &$changed) {
        foreach ($blocks as &$block) {
            if (isset($block['blockName']) && $block['blockName'] === self::BLOCK) {
                $attrs = isset($block['attrs']) && is_array($block['attrs']) ? $block['attrs'] : array();
                $bid = isset($attrs['blockId']) ? $attrs['blockId'] : '';
                $duplicate = !self::valid_uuid($bid) || isset($seen_blocks[$bid]);
                if ($duplicate) {
                    $attrs['blockId'] = self::uuid();
                    $changed = true;
                }
                $seen_blocks[$attrs['blockId']] = true;
                $questions = isset($attrs['questions']) && is_array($attrs['questions']) ? $attrs['questions'] : array();
                foreach ($questions as &$question) {
                    if (!is_array($question)) {
                        continue;
                    }
                    $qid = isset($question['id']) ? $question['id'] : '';
                    if ($duplicate || !self::valid_uuid($qid) || isset($seen_questions[$qid])) {
                        $question['id'] = self::uuid();
                        $changed = true;
                    }
                    $seen_questions[$question['id']] = true;
                }
                unset($question);
                $attrs['questions'] = $questions;
                $block['attrs'] = $attrs;
            }
            if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                self::normalize_blocks($block['innerBlocks'], $seen_blocks, $seen_questions, $changed);
            }
        }
        unset($block);
    }

    private static function walk_blocks($blocks, &$matches, $target_id) {
        foreach ($blocks as $block) {
            if (isset($block['blockName']) && $block['blockName'] === self::BLOCK
                && isset($block['attrs']['blockId']) && $block['attrs']['blockId'] === $target_id) {
                $matches[] = $block['attrs'];
            }
            if (!empty($block['innerBlocks'])) {
                self::walk_blocks($block['innerBlocks'], $matches, $target_id);
            }
        }
    }

    /** Find exactly one persisted block, never trust client-supplied questions. */
    public static function published_block($content_id, $block_id) {
        if (!self::is_supported($content_id, true) || !self::valid_uuid($block_id)) {
            return null;
        }
        $content = get_post_field('post_content', $content_id);
        $matches = array();
        self::walk_blocks(parse_blocks($content), $matches, $block_id);
        return count($matches) === 1 ? $matches[0] : null;
    }

    public static function responses($learner_id, $content_id, $block_id) {
        if (!$learner_id || $learner_id !== MD_Learning_Auth::current_learner_id()
            || !self::valid_uuid($block_id) || !self::is_supported($content_id, true)) {
            return array();
        }
        global $wpdb;
        $table = MD_Learning_Schema::table('responses');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT question_id, answer_text, status, updated_at FROM {$table}
             WHERE learner_id = %d AND content_id = %d AND block_id = %s",
            $learner_id, absint($content_id), $block_id
        ));
        $map = array();
        foreach ((array) $rows as $row) {
            $map[$row->question_id] = $row;
        }
        return $map;
    }

    public static function render($attributes, $content = '', $block = null) {
        $attrs = is_array($attributes) ? $attributes : array();
        $title = isset($attrs['title']) ? sanitize_text_field($attrs['title']) : '';
        $intro = isset($attrs['description']) ? sanitize_textarea_field($attrs['description']) : '';
        $mode = isset($attrs['mode']) && $attrs['mode'] === 'save' ? 'save' : 'reflection';
        $appearance = isset($attrs['appearance']) && $attrs['appearance'] === 'minimal' ? 'minimal' : 'card';
        $block_id = isset($attrs['blockId']) ? $attrs['blockId'] : '';
        $questions = self::clean_questions(isset($attrs['questions']) ? $attrs['questions'] : array());
        if (!$questions) {
            return is_admin() ? '<p class="md-learning-question-empty">از پنل بلوک، پرسش جدید اضافه کنید.</p>' : '';
        }
        $post_id = $block && isset($block->context['postId']) ? absint($block->context['postId']) : absint(get_the_ID());
        if (!self::is_supported($post_id)) {
            return '';
        }
        $learner_id = MD_Learning_Auth::current_learner_id();
        $interactive = $mode === 'save' && self::valid_uuid($block_id)
            && !is_admin() && self::is_supported($post_id, true);
        $answers = $interactive && $learner_id ? self::responses($learner_id, $post_id, $block_id) : array();
        $notice = isset($_GET['md_answer_saved']) && is_string($_GET['md_answer_saved'])
            ? sanitize_key(wp_unslash($_GET['md_answer_saved'])) : '';
        $notice_block = isset($_GET['md_question_block']) && is_string($_GET['md_question_block'])
            ? sanitize_key(wp_unslash($_GET['md_question_block'])) : '';
        ob_start();
        ?>
        <section class="md-learning-questions md-learning-questions--<?php echo esc_attr($appearance); ?>"
            id="md-learning-q-<?php echo esc_attr($block_id); ?>" dir="rtl">
            <?php if ($title) : ?><h2><?php echo esc_html($title); ?></h2><?php endif; ?>
            <?php if ($intro) : ?><p class="md-learning-questions-intro"><?php echo nl2br(esc_html($intro)); ?></p><?php endif; ?>
            <?php if ($mode === 'reflection') : ?>
                <ol class="md-learning-reflections">
                    <?php foreach ($questions as $q) : ?>
                        <li><strong><?php echo esc_html($q['text']); ?></strong>
                            <?php if ($q['hint']) : ?><p><?php echo nl2br(esc_html($q['hint'])); ?></p><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <p class="md-learning-question-note">این پرسش‌ها برای تأمل هستند؛ پاسخی در سایت ذخیره نمی‌شود.</p>
            <?php elseif (!$interactive) : ?>
                <p class="md-learning-question-note">برای فعال‌شدن ثبت پاسخ، ابتدا این آموزش را با شناسه‌های پرسش ذخیره و منتشر کنید.</p>
            <?php elseif (!$learner_id) : ?>
                <ol class="md-learning-reflections">
                    <?php foreach ($questions as $q) : ?><li><?php echo esc_html($q['text']); ?></li><?php endforeach; ?>
                </ol>
                <p class="md-learning-question-note">برای نوشتن و نگهداری پاسخ‌های خصوصی، وارد پروفایل یادگیری خود شوید.</p>
                <a class="md-learning-question-link" href="<?php echo esc_url(add_query_arg('md_resource', $post_id, MD_Learning_Schema::url('login'))); ?>">ورود با کد ۸ رقمی</a>
                <a class="md-learning-question-link" href="<?php echo esc_url(add_query_arg('md_resource', $post_id, MD_Learning_Schema::url('register'))); ?>">ساخت پروفایل</a>
            <?php else : ?>
                <?php if ($notice && $notice_block === $block_id) : ?>
                    <p class="md-learning-question-success" role="status"><?php echo $notice === 'final' ? 'پاسخ‌های شما ثبت نهایی شدند.' : 'پیش‌نویس پاسخ‌های شما ذخیره شد.'; ?></p>
                <?php endif; ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="md-learning-answer-form">
                    <input type="hidden" name="action" value="md_learning_save_questions">
                    <input type="hidden" name="md_content_id" value="<?php echo esc_attr($post_id); ?>">
                    <input type="hidden" name="md_block_id" value="<?php echo esc_attr($block_id); ?>">
                    <?php wp_nonce_field('md_learning_question_save_' . $post_id . '_' . $block_id); ?>
                    <?php
                    $allow_edit = !isset($attrs['allowEdit']) || (bool) $attrs['allowEdit'];
                    $all_final = true;
                    foreach ($questions as $q) :
                        $old = isset($answers[$q['id']]) ? $answers[$q['id']] : null;
                        $locked = $old && $old->status === 'final' && !$allow_edit;
                        if (!$old || $old->status !== 'final') { $all_final = false; }
                        ?>
                        <label class="md-learning-answer-item">
                            <span class="md-learning-question-text"><?php echo esc_html($q['text']); ?><?php if ($q['required']) : ?> <span aria-label="الزامی در ثبت نهایی">*</span><?php endif; ?></span>
                            <?php if ($q['hint']) : ?><span class="md-learning-question-hint"><?php echo nl2br(esc_html($q['hint'])); ?></span><?php endif; ?>
                            <?php if ($q['type'] === 'short') : ?>
                                <input type="text" name="md_answers[<?php echo esc_attr($q['id']); ?>]" maxlength="500"
                                    value="<?php echo esc_attr($old ? $old->answer_text : ''); ?>" <?php disabled($locked); ?> placeholder="پاسخ من">
                            <?php else : ?>
                                <textarea name="md_answers[<?php echo esc_attr($q['id']); ?>]" rows="4" maxlength="10000"
                                    <?php disabled($locked); ?> placeholder="پاسخ من"><?php echo esc_textarea($old ? $old->answer_text : ''); ?></textarea>
                            <?php endif; ?>
                            <?php if ($old) : ?><span class="md-learning-answer-state"><?php echo $old->status === 'final' ? 'ثبت نهایی' : 'پیش‌نویس'; ?></span><?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                    <?php if (!$all_final || $allow_edit) : ?>
                        <div class="md-learning-answer-actions">
                            <button type="submit" name="md_intent" value="draft" formnovalidate>ذخیره پیش‌نویس</button>
                            <button type="submit" name="md_intent" value="final">ثبت پاسخ‌ها</button>
                        </div>
                    <?php endif; ?>
                    <p class="md-learning-question-note">پاسخ‌ها فقط در حساب شما و برای مدیران مجاز سایت قابل دسترسی‌اند؛ عمومی نمایش داده نمی‌شوند.</p>
                </form>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    public static function save() {
        $learner_id = MD_Learning_Auth::current_learner_id();
        if (!$learner_id) {
            wp_die('برای ذخیره پاسخ ابتدا وارد شوید.', '', array('response' => 403));
        }
        $content_id = isset($_POST['md_content_id']) ? absint($_POST['md_content_id']) : 0;
        $block_id = isset($_POST['md_block_id']) && is_string($_POST['md_block_id'])
            ? sanitize_key(wp_unslash($_POST['md_block_id'])) : '';
        if (!self::valid_uuid($block_id)) {
            wp_die('شناسه بلوک معتبر نیست.', '', array('response' => 400));
        }
        check_admin_referer('md_learning_question_save_' . $content_id . '_' . $block_id);
        $block = self::published_block($content_id, $block_id);
        if (!$block || !isset($block['mode']) || $block['mode'] !== 'save') {
            wp_die('این پرسش برای ثبت پاسخ فعال نیست.', '', array('response' => 403));
        }
        $questions = self::clean_questions(isset($block['questions']) ? $block['questions'] : array());
        if (!$questions) {
            wp_die('پرسشی برای ثبت وجود ندارد.', '', array('response' => 400));
        }
        $intent = isset($_POST['md_intent']) && $_POST['md_intent'] === 'final' ? 'final' : 'draft';
        $raw = isset($_POST['md_answers']) && is_array($_POST['md_answers']) ? wp_unslash($_POST['md_answers']) : array();
        $allow_edit = !isset($block['allowEdit']) || (bool) $block['allowEdit'];
        global $wpdb;
        $table = MD_Learning_Schema::table('responses');
        $locked_questions = array();
        if (!$allow_edit) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT question_id FROM {$table}
                 WHERE learner_id = %d AND content_id = %d AND block_id = %s AND status = 'final'",
                $learner_id, $content_id, $block_id
            ));
            foreach ((array) $rows as $row) {
                $locked_questions[$row->question_id] = true;
            }
        }

        // Validate all editable fields before writes; already-final answers
        // remain immutable even when the teacher adds another question later.
        $validated = array();
        $ids = array();
        foreach ($questions as $q) {
            if (isset($ids[$q['id']])) {
                wp_die('شناسه‌های پرسش تکراری‌اند. آموزش را دوباره ذخیره کنید.', '', array('response' => 409));
            }
            $ids[$q['id']] = true;
            if (isset($locked_questions[$q['id']])) {
                continue;
            }
            $value = isset($raw[$q['id']] && is_string($raw[$q['id']])
                ? sanitize_textarea_field($raw[$q['id']]) : '';
            $max = $q['type'] === 'short' ? 500 : 10000;
            if ((function_exists('mb_strlen') ? mb_strlen($value) : strlen($value)) > $max) {
                wp_die('پاسخ از حداکثر اندازه مجاز طولانی‌تر است.', '', array('response' => 400));
            }
            if ($intent === 'final' && $q['required'] && trim($value) === '') {
                wp_die('برای ثبت نهایی، به تمام پرسش‌های الزامی پاسخ دهید.', '', array('response' => 400));
            }
            $validated[] = array('question' => $q, 'answer' => $value);
        }

        $now = gmdate('Y-m-d H:i:s');
        $wpdb->query('START TRANSACTION');
        foreach ($validated as $item) {
            $q = $item['question'];
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id, status, question_text FROM {$table}
                 WHERE learner_id = %d AND content_id = %d AND question_id = %s LIMIT 1",
                $learner_id, $content_id, $q['id']
            ));
            if ($existing && $existing->status === 'final' && !$allow_edit) {
                $wpdb->query('ROLLBACK');
                wp_die('این پاسخ قبلاً نهایی شده و ویرایش آن بسته است.', '', array('response' => 403));
            }
            $status = $existing && $existing->status === 'final' ? 'final' : $intent;
            $record = array(
                'answer_text' => $item['answer'],
                'status' => $status,
                'updated_at' => $now,
                'submitted_at' => $status === 'final' ? $now : null,
            );
            if ($existing) {
                $ok = $wpdb->update($table, $record,
                    array('id' => (int) $existing->id, 'learner_id' => $learner_id),
                    array('%s','%s','%s','%s'), array('%d','%d'));
                if ($ok === false) { $wpdb->query('ROLLBACK'); wp_die('ذخیره پاسخ انجام نشد.', '', array('response' => 500)); }
            } else {
                $ok = $wpdb->insert($table, array_merge($record, array(
                    'learner_id' => $learner_id, 'content_id' => $content_id,
                    'block_id' => $block_id, 'question_id' => $q['id'],
                    'question_text' => $q['text'], 'created_at' => $now,
                )), array('%s','%s','%s','%s','%d','%d','%s','%s','%s','%s'));
                if (!$ok) { $wpdb->query('ROLLBACK'); wp_die('ذخیره پاسخ انجام نشد.', '', array('response' => 500)); }
            }
        }
        $wpdb->query('COMMIT');
        $url = add_query_arg(array(
            'md_answer_saved' => $intent,
            'md_question_block' => $block_id,
        ), get_permalink($content_id));
        wp_safe_redirect($url . '#md-learning-q-' . $block_id, 303);
        exit;
    }

    public static function assets() {
        if (!is_singular(self::TYPES) || !has_block(self::BLOCK)) {
            return;
        }
        wp_enqueue_style('md-learning-questions',
            MD_LEARNING_CORE_URL . 'assets/questions.css', array(),
            MD_LEARNING_CORE_VERSION);
    }

    public static function private_headers() {
        if (!is_singular(self::TYPES) || !has_block(self::BLOCK)) {
            return;
        }
        // The block can render session-specific answers/CSRF tokens. Disable
        // full-page caching even if the current request is anonymous.
        nocache_headers();
        header('Cache-Control: private, no-store, max-age=0, must-revalidate', true);
        header('Referrer-Policy: no-referrer', true);
    }
}
