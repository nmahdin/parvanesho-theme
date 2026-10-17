<?php
/**
 * Phase D draft/final persistence smoke tests with an isolated in-memory
 * WordPress/WPDB facade. No production data or real WordPress required.
 */
require __DIR__ . '/phase-d-smoke.php';

class AnswerRedirect extends RuntimeException {}
class AnswerDenied extends RuntimeException {}
function wp_unslash($value) { return $value; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value)); }
function check_admin_referer($action) {
    if ($action !== $GLOBALS['required_nonce']) {
        throw new AnswerDenied('wrong nonce');
    }
}
function wp_die($message, $title = '', $args = array()) { throw new AnswerDenied((string) $message); }
function add_query_arg($a, $b = '') { return $b . '?' . http_build_query($a); }
function wp_safe_redirect($url, $status = 302) { throw new AnswerRedirect($url); }

class AnswerDb extends DummyDb {
    public $answers = array();
    public $id_counter = 0;
    public function get_row($query) {
        $args = $query['params'];
        foreach ($this->answers as $row) {
            if ($row->learner_id === $args[0] && $row->content_id === $args[1]
                && $row->question_id === $args[2]) {
                return (object) array('id' => $row->id, 'status' => $row->status, 'question_text' => $row->question_text);
            }
        }
        return null;
    }
    public function get_results($query) {
        if (strpos($query['sql'], 'SELECT question_id, answer_text') !== false) {
            $p = $query['params'];
            return array_values(array_filter($this->answers, function ($r) use ($p) {
                return $r->learner_id === $p[0] && $r->content_id === $p[1] && $r->block_id === $p[2];
            }));
        }
        if (strpos($query['sql'], "status = 'final'") !== false) {
            $p = $query['params'];
            return array_map(function ($r) { return (object) array('question_id' => $r->question_id); },
                array_values(array_filter($this->answers, function ($r) use ($p) {
                    return $r->learner_id === $p[0] && $r->content_id === $p[1]
                        && $r->block_id === $p[2] && $r->status === 'final';
                })));
        }
        return array();
    }
    public function insert($table, $record, $format = null) {
        foreach ($this->answers as $row) {
            if ($row->learner_id === $record['learner_id']
                && $row->content_id === $record['content_id']
                && $row->question_id === $record['question_id']) {
                return false;
            }
        }
        $record['id'] = ++$this->id_counter;
        $this->answers[] = (object) $record;
        return 1;
    }
    public function update($table, $record, $where, $format = null, $where_format = null) {
        foreach ($this->answers as $row) {
            if ($row->id === $where['id'] && $row->learner_id === $where['learner_id']) {
                foreach ($record as $k => $value) { $row->$k = $value; }
                return 1;
            }
        }
        return false;
    }
    public function query($query) { return 1; }
}

$GLOBALS['wpdb'] = new AnswerDb();
MD_Learning_Auth::$current = 1;
$GLOBALS['required_nonce'] = 'md_learning_question_save_500_' . $originalBlock;
function submit_answers($answers, $intent, $bid = null) {
    global $originalBlock;
    $_POST = array(
        'md_content_id' => 500,
        'md_block_id' => $bid ?: $originalBlock,
        'md_intent' => $intent,
        'md_answers' => $answers,
    );
    try {
        MD_Learning_Questions::save();
    } catch (AnswerRedirect $e) {
        return 'saved';
    } catch (AnswerDenied $e) {
        return 'denied';
    }
    throw new RuntimeException('save did not redirect or reject');
}
$optionalId = str_repeat('c', 32);
check(submit_answers(array($originalQuestion => '', $optionalId => 'پیش‌نویس دوم'), 'draft') === 'saved',
    'draft with empty required response rejected');
check(count($wpdb->answers) === 2 && $wpdb->answers[0]->status === 'draft', 'draft storage failed');
check(submit_answers(array($originalQuestion => '', $optionalId => 'متن کامل'), 'final') === 'denied',
    'required final answer not validated');
check($wpdb->answers[0]->status === 'draft', 'failed final validation changed existing draft');
check(submit_answers(array($originalQuestion => 'پاسخ قطعی', $optionalId => 'پاسخ دوم'), 'final') === 'saved',
    'final submission failed');
check($wpdb->answers[0]->status === 'final' && $wpdb->answers[0]->answer_text === 'پاسخ قطعی',
    'final answer not persisted');

MD_Learning_Auth::$current = 2;
check(MD_Learning_Questions::responses(2, 500, $originalBlock) === array(),
    'another learner read stored response');
check(submit_answers(array($originalQuestion => 'پاسخ دومین فرد'), 'draft') === 'saved',
    'second learner cannot save own independent draft');
check(count($wpdb->answers) === 4, 'answers not isolated by learner');

MD_Learning_Auth::$current = 1;
$blocks = parse_blocks($GLOBALS['posts'][500]->post_content);
$blocks[1]['attrs']['allowEdit'] = false;
$newId = str_repeat('d', 32);
$blocks[1]['attrs']['questions'][] = array('id' => $newId, 'text' => 'پرسش تازه', 'required' => true);
$GLOBALS['posts'][500]->post_content = serialize_blocks($blocks);
check(submit_answers(array($newId => 'جواب جدید'), 'final') === 'saved',
    'new question cannot be answered when previous finals are locked');
check($wpdb->answers[0]->answer_text === 'پاسخ قطعی', 'locked previous final answer overwritten');
check(submit_answers(array($originalQuestion => 'تلاش برای تغییر', $newId => 'جواب جدید'), 'final') === 'saved',
    'locked fields should be ignored while new questions remain editable');
check($wpdb->answers[0]->answer_text === 'پاسخ قطعی', 'locked final answer modified');

MD_Learning_Auth::$current = 0;
check(submit_answers(array($newId => 'تزریق ناشناس'), 'final') === 'denied',
    'signed-out user saved a response');
MD_Learning_Auth::$current = 1;
check(submit_answers(array($newId => 'تزریق', $originalQuestion => 'تزریق'), 'final', str_repeat('f', 32)) === 'denied',
    'forged block UUID saved answers');
echo "Phase D draft/final lifecycle and ownership smoke tests: PASS\n";
