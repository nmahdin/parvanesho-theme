<?php
/**
 * Standalone Phase F course ownership, enrollment and progress smoke tests.
 * Runs without WordPress/MySQL; a staging DB test is still required.
 */
require __DIR__ . '/phase-b-smoke.php';

class CourseDb extends DummyDb {
    public $enrollments = array();
    public $progress_rows = array();
    public $next_id = 1;
    public $last_insert_sql = '';
    public $last_insert_args = array();

    public function prepare($sql, ...$args) {
        // wpdb::prepare accepts a flat list OR a single array of parameters.
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }
        return array('sql' => $sql, 'params' => $args);
    }
    public function get_row($q) {
        $sql = $q['sql'];
        $params = $q['params'];
        if (strpos($sql, 'FROM wp_md_learning_enrollments') !== false) {
            foreach ($this->enrollments as $e) {
                if ($e->learner_id === (int)$params[0] && $e->course_id === (int)$params[1]) {
                    return $e;
                }
            }
        }
        return null;
    }
    public function get_col($query) {
        $sql = $query['sql'];
        $learner = (int) $query['params'][0];
        if (strpos($sql, 'FROM wp_md_learning_enrollments') !== false) {
            $out = array();
            foreach ($this->enrollments as $row) {
                if ($row->learner_id === $learner && $row->status === 'active') {
                    $out[] = $row->course_id;
                }
            }
            return $out;
        }
        return array();
    }
    public function get_results($query) {
        $sql = $query['sql'];
        if (strpos($sql, 'FROM wp_md_learning_lesson_progress') !== false) {
            $params = $query['params'];
            return array_values(array_filter($this->progress_rows, function($p) use ($params) {
                return $p->learner_id === (int)$params[0] && $p->course_id === (int)$params[1];
            }));
        }
        return array();
    }
    public function insert($table, $values, $formats = null) {
        if (strpos($table, 'enrollments') !== false) {
            foreach ($this->enrollments as $row) {
                if ($row->learner_id === $values['learner_id'] && $row->course_id === $values['course_id']) {
                    return false;
                }
            }
            $values['id'] = $this->next_id++;
            $this->enrollments[] = (object) $values;
            return 1;
        }
        return parent::insert($table, $values, $formats);
    }
    public function update($table, $data, $where, $formats = null, $where_formats = null) {
        if (strpos($table, 'enrollments') !== false) {
            foreach ($this->enrollments as $e) {
                if ($e->learner_id === $where['learner_id'] && $e->course_id === $where['course_id']) {
                    foreach ($data as $key => $value) {
                        $e->$key = $value;
                    }
                    return 1;
                }
            }
            return false;
        }
        return parent::update($table, $data, $where, $formats, $where_formats);
    }
    public function query($query) {
        $sql = $query['sql'];
        $params = $query['params'];
        if (strpos($sql, 'INSERT INTO wp_md_learning_lesson_progress') === false) {
            return 1;
        }
        $this->last_insert_sql = $sql;
        $this->last_insert_args = $params;
        $learner = (int) $params[0];
        $course = (int) $params[1];
        $lesson = (int) $params[2];
        $status = $params[3];
        $date = $status === 'completed' ? $params[4] : null;
        foreach ($this->progress_rows as $item) {
            if ($item->learner_id === $learner && $item->course_id === $course && $item->lesson_id === $lesson) {
                $item->status = $status;
                $item->completed_at = $date;
                return 2;
            }
        }
        $this->progress_rows[] = (object) array(
            'learner_id'=>$learner, 'course_id'=>$course, 'lesson_id'=>$lesson,
            'status'=>$status, 'completed_at'=>$date,
        );
        return 1;
    }
}
class MD_Learning_Schema {
    public static function table($type) { return 'wp_md_learning_' . $type; }
}
function get_posts($args) {
    $results = array();
    foreach ($GLOBALS['posts'] as $p) {
        if ($p->post_type !== $args['post_type'] || $p->post_status !== $args['post_status']) {
            continue;
        }
        $course = (int) $args['meta_query'][0]['value'];
        if ((int) get_post_meta($p->ID, '_md_course_id', true) === $course) {
            $results[] = $p;
        }
    }
    usort($results, function($a,$b) { return $a->ID <=> $b->ID; });
    return $results;
}
function get_the_title($id) {
    return isset($GLOBALS['posts'][$id]) ? $GLOBALS['posts'][$id]->post_title : '';
}
require_once dirname(__DIR__) . '/includes/class-md-learning-courses.php';

$GLOBALS['wpdb'] = new CourseDb();
$GLOBALS['posts'][300] = (object) array('ID'=>300,'post_type'=>'md_course','post_status'=>'publish','post_title'=>'دوره آزاد');
$GLOBALS['posts'][301] = (object) array('ID'=>301,'post_type'=>'md_course','post_status'=>'publish','post_title'=>'دوره نیازمند تأیید');
$GLOBALS['posts'][302] = (object) array('ID'=>302,'post_type'=>'md_course','post_status'=>'draft','post_title'=>'دوره پیش نویس');
$GLOBALS['posts'][400] = (object) array('ID'=>400,'post_type'=>'md_lesson','post_status'=>'publish','post_title'=>'درس اول');
$GLOBALS['posts'][401] = (object) array('ID'=>401,'post_type'=>'md_lesson','post_status'=>'publish','post_title'=>'درس دوم');
$GLOBALS['posts'][402] = (object) array('ID'=>402,'post_type'=>'md_lesson','post_status'=>'draft','post_title'=>'درس پیش نویس');
$GLOBALS['posts'][403] = (object) array('ID'=>403,'post_type'=>'md_lesson','post_status'=>'publish','post_title'=>'درس دوره دیگر');
$GLOBALS['meta'][300]['_md_learning_open_enrollment'] = '1';
$GLOBALS['meta'][301]['_md_learning_open_enrollment'] = '0';
$GLOBALS['meta'][400]['_md_course_id'] = 300;
$GLOBALS['meta'][401]['_md_course_id'] = 300;
$GLOBALS['meta'][402]['_md_course_id'] = 300;
$GLOBALS['meta'][403]['_md_course_id'] = 301;

MD_Learning_Auth::$current = 1;
check(MD_Learning_Courses::open_enrollment(300), 'opted-in course not open');
check(!MD_Learning_Courses::open_enrollment(301), 'closed course unexpectedly open');
check(!MD_Learning_Courses::open_enrollment(302), 'draft course unexpectedly open');
check(is_wp_error(MD_Learning_Courses::enroll(1,301)), 'learner bypassed closed course');
check(is_wp_error(MD_Learning_Courses::enroll(2,300)), 'learner enrolled another learner');

$a=MD_Learning_Courses::enroll(1,300);
check(!is_wp_error($a) && $a->status==='active','free course enrollment failed');
$b=MD_Learning_Courses::enroll(1,300);
check(!is_wp_error($b) && $b->id===$a->id, 'duplicate self enrollment not idempotent');
check(count($wpdb->enrollments)===1, 'duplicate membership row');

$p=MD_Learning_Courses::progress(1,300);
check($p['total']===2 && $p['completed']===0, 'unpublished lesson counted');
check(is_wp_error(MD_Learning_Courses::set_progress(1,300,403,true)), 'cross-course lesson marked complete');
check(is_wp_error(MD_Learning_Courses::set_progress(1,300,402,true)), 'draft lesson marked complete');
check(is_wp_error(MD_Learning_Courses::set_progress(2,300,400,true)), 'cross-learner progress accepted');

check(MD_Learning_Courses::set_progress(1,300,400,true)===true, 'own lesson completion failed');
check(MD_Learning_Courses::set_progress(1,300,400,true)===true, 'repeating completion failed');
$p=MD_Learning_Courses::progress(1,300);
check($p['completed']===1 && $p['percent']===50, 'percentage incorrect');
check(MD_Learning_Courses::set_progress(1,300,400,false)===true, 'reverse completion failed');
check(strpos($wpdb->last_insert_sql, 'NULL') !== false && count($wpdb->last_insert_args)===5,
    'reverse progress does not persist real SQL NULL');
check(MD_Learning_Courses::progress(1,300)['percent']===0, 'reverse completion did not update count');
check(count(MD_Learning_Courses::my_courses(1))===1,'dashboard course list missing');

$GLOBALS['can_edit'] = true; // WP administrator.
$admin=MD_Learning_Courses::set_membership_status(2,301,'active');
check(!is_wp_error($admin), 'admin cannot enroll in non-open course');
check(count($wpdb->enrollments)===2, 'admin membership not persisted');
$admin=MD_Learning_Courses::set_membership_status(1,300,'revoked');
check(!is_wp_error($admin), 'revocation failed');
check(is_wp_error(MD_Learning_Courses::set_progress(1,300,400,true)),
    'revoked membership still writes progress');
check(MD_Learning_Courses::progress(1,300)===null, 'revoked membership still exposes progress');
$admin=MD_Learning_Courses::set_membership_status(1,300,'active');
check(!is_wp_error($admin), 'reactivation failed');
check(MD_Learning_Courses::progress(1,300)['total']===2,'reactivation lost progress records');
$GLOBALS['can_edit']=false;

MD_Learning_Auth::$current=2;
check(MD_Learning_Courses::progress(1,300)===null,'cross-learner progress read allowed');
check(count(MD_Learning_Courses::my_courses(1))===0, 'cross-learner course listing allowed');
check(count(MD_Learning_Courses::my_courses(2))===1,'second learner missing own admin assignment');
check(is_wp_error(MD_Learning_Courses::set_membership_status(1,300,'revoked')),
    'ordinary learner exercised administrator revoke');

echo "Phase F central course enrollment, lesson security and progress: PASS\n";
