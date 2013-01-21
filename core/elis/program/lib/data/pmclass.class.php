<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2011 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    elis
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/../../../../config.php');
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once elis::lib('data/data_object_with_custom_fields.class.php');
require_once elis::lib('data/customfield.class.php');
require_once elispm::lib('lib.php');
require_once elispm::lib('data/course.class.php');
require_once elispm::lib('data/coursetemplate.class.php');
require_once elispm::lib('data/classmoodlecourse.class.php');
require_once elispm::lib('data/curriculumcourse.class.php');
require_once elispm::lib('data/track.class.php');
require_once elispm::lib('data/student.class.php');
require_once elispm::lib('data/instructor.class.php');
require_once elispm::lib('managementpage.class.php');
require_once elispm::lib('contexts.php');
require_once elispm::file('form/pmclassform.class.php');

class pmclass extends data_object_with_custom_fields {
    const TABLE = 'crlm_class';

    var $verbose_name = 'class';
    var $autocreate;
    var $moodlecourseid;
    var $track;
    var $oldmax;
    var $unlink_attached_course;

    static $config_default_prefix = 'clsdft';

    static $associations = array(
        'course' => array(
            'class' => 'course',
            'idfield' => 'courseid'
        ),
        'student' => array(
            'class' => 'student',
            'foreignidfield' => 'classid'
        ),
        'classgraded' => array(
            'class' => 'student_grade',
            'foreignidfield' => 'classid'
        ),
        'classinstructor' => array(
            'class' => 'instructor',
            'foreignidfield' => 'classid'
        ),
        'classmoodle' => array(
            'class' => 'classmoodlecourse',
            'foreignidfield' => 'classid'
        ),
        'trackclass' => array(
            'class' => 'trackassignment',
            'foreignidfield' => 'classid'
        ),
    );

    private $form_url = null;  //moodle_url object

    protected $_dbfield_idnumber;
    protected $_dbfield_courseid;
    protected $_dbfield_startdate;
    protected $_dbfield_enddate;
    protected $_dbfield_duration;
    protected $_dbfield_starttimehour;
    protected $_dbfield_starttimeminute;
    protected $_dbfield_endtimehour;
    protected $_dbfield_endtimeminute;
    protected $_dbfield_maxstudents;
    protected $_dbfield_environmentid;
    protected $_dbfield_enrol_from_waitlist;

    static $delete_is_complex = true;

    protected function get_field_context_level() {
        return CONTEXT_ELIS_CLASS;
    }

    function get_start_time() {
        if ($this->starttimehour >= 25 || $this->starttimeminute >= 61) {
            return 0;
        }
        $starttime = ($this->starttimehour - get_user_timezone_offset()) * HOURSECS;
        $starttime += $this->starttimeminute * MINSECS;

        return $starttime;
    }

    function get_end_time() {
        if ($this->endtimehour >= 25 || $this->endtimeminute >= 61) {
            return 0;
        }
        $endtime = ($this->endtimehour - get_user_timezone_offset()) * HOURSECS;
        $endtime += $this->endtimeminute * MINSECS;

        return $endtime;
    }

    function get_moodle_course_id() {
        $mdlrec = $this->_db->get_record(classmoodlecourse::TABLE, array('classid'=>$this->id));
        return !empty($mdlrec) ? $mdlrec->moodlecourseid : 0;
        //$this->moodlesiteid = !empty($mdlrec->siteid) ? $mdlrec->siteid : 0;
    }

    public static function delete_for_course($id) {
        global $DB;
        return $DB->delete_records(pmclass::TABLE, array('courseid'=>$id));
    }

    /*
     * Returns an aggregate of enrolment completion statuses for this class.
     *
     * @see course::get_completion_counts()
     */
    public function get_completion_counts($clsid = null) {
        global $DB;

        if ($clsid === null) {
            if (empty($this->id)) {
                return array();
            }
            $clsid = $this->id;
        }

        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $inactive = 'AND usr.inactive = 0';
        } else {
            $inactive = '';
        }

        $sql = 'SELECT cce.completestatusid status, COUNT(cce.completestatusid) count
        FROM {'. student::TABLE .'} cce
        INNER JOIN {'. user::TABLE ."} usr ON cce.userid = usr.id
        WHERE cce.classid = ? {$inactive}
        GROUP BY cce.completestatusid";

        $ret = array(STUSTATUS_NOTCOMPLETE=>0, STUSTATUS_FAILED=>0, STUSTATUS_PASSED=>0);

        $rows = $DB->get_recordset_sql($sql, array($clsid));
        foreach ($rows as $row) {
            $ret[$row->status] = $row->count;
        }
        unset($rows);

        return $ret;
    }

    /////////////////////////////////////////////////////////////////////
    //                                                                 //
    //  FORM FUNCTIONS:                                                //
    //                                                                 //
    /////////////////////////////////////////////////////////////////////
    public function setUrl($url = null, $action = array()) {
        if(!($url instanceof moodle_url)) {
            $url = new moodle_url($url, $action);
        }

        $this->form_url = $url;
    }

//     public function create_edit_form($formid='', $rows=2, $cols=40) {
//         $configdata = array();
//         $configdata['id'] = $this->id;
//         $configdata['courseid'] = $this->courseid;
//         $configdata['display_12h'] = true;

//         $this->form = new pmclassform($this->form_url, $configdata);

//         $this->starttime = ($this->starttimehour + 5) * HOURSECS;
//         $this->starttime += $this->starttimeminute * MINSECS;

//         $this->endtime = ($this->endtimehour + 5) * HOURSECS;
//         $this->endtime += $this->endtimeminute * MINSECS;

//         $this->form->set_data($this);

//         return $this->form;
//     }

    public static function find($filter=null, array $sort=array(), $limitfrom=0, $limitnum=0, moodle_database $db=null) {
        return parent::find($filter, $sort, $limitfrom, $limitnum, $db);
    }

    public function set_from_data($data) {
        if (!empty($data->moodleCourses['autocreate'])) {
            $this->autocreate = $data->moodleCourses['autocreate'];
        } else {
            $this->autocreate = false;
        }

        if (isset($data->disablestart)) {
            $this->startdate = 0;
        }

        if (isset($data->disableend)) {
            $this->enddate = 0;
        }

        if (!empty($data->moodleCourses['moodlecourseid']) && !$this->autocreate) {
            $this->moodlecourseid = $data->moodleCourses['moodlecourseid'];
        } else if (!empty($data->courseSelected['unlink_attached_course']) && !empty($data->moodlecourseid)) {
            $this->unlink_attached_course = $data->courseSelected['unlink_attached_course'];
            $this->moodlecourseid = $data->moodlecourseid;
        } else {
            $this->moodlecourseid = 0;
        }

        if (isset($data->track)) {
            $this->track = $data->track;
        }

        $this->oldmax = $this->maxstudents;

        $this->_load_data_from_record($data, true);
    }

    /*
     * Perform all the necessary steps to delete all aspects of a class.
     *
     */
    function delete() {
        if (!empty($this->id)) {
            //clean make the delete cascade into association records
            $filter = new field_filter('classid', $this->id);
            instructor::delete_records($filter, $this->_db);
            student::delete_records($filter, $this->_db);
            trackassignment::delete_records($filter, $this->_db);
            classmoodlecourse::delete_records($filter, $this->_db);
            student_grade::delete_records($filter, $this->_db);
            waitlist::delete_records($filter, $this->_db);

            parent::delete();

            $context = context_elis_class::instance($this->id);
            $context->delete();
        }
    }

    function __toString() {
        $coursename = isset($this->course) ? $this->course->name : '';
        return $this->idnumber . ' ' . $coursename;
    }

    /**
     * Add param fields to the form object
     */
    public function to_object() {
        $obj = parent::to_object();

        $mdlcrsid = $this->get_moodle_course_id();
        if ($mdlcrsid != 0) {
            $obj->moodlecourseid = $mdlcrsid;
        }

        return $obj;
    }

    /**
     * Determine whether a class is currently (manually) enrollable.
     * Checks if the class is associated with a Moodle course.
     * Checks whether the Moodle course is enrollable.
     */
    public function is_enrollable() {
        global $CFG;
        $courseid = $this->get_moodle_course_id();
        // no associated Moodle course, so we're always enrollable
        if ($courseid == 0) {
            return true;
        }
        $course = $this->_db->get_record('course', array('id'=>$courseid));
        // check that this is a valid course to enrol into
        if (!$course || $course->id == SITEID) {
            return false;
        }

        // check that the elis plugin allows for enrolments from the course
        // catalog, or that some other plugin allows for manual enrolments
        $plugin = enrol_get_plugin('elis');
        $enrol = $plugin->get_or_create_instance($course);
        if (!$enrol->{enrol_elis_plugin::ENROL_FROM_COURSE_CATALOG_DB}) {
            // get course enrolment plugins, and see if any of them allow self-enrolment
            $enrols = enrol_get_plugins(true);
            $enrolinstances = enrol_get_instances($course->id, true);
            foreach($enrolinstances as $instance) {
                if (!isset($enrols[$instance->enrol])) {
                    continue;
                }
                $form = $enrols[$instance->enrol]->enrol_page_hook($instance);
                if ($form) {
                    // at least one plugin allows self-enrolment, so return
                    // true
                    return true;
                }
            }
            return false;
        }

        // if all the checks pass, then we're good
        return true;
    }

    /////////////////////////////////////////////////////////////////////
    //                                                                 //
    //  CUURICULUM FUNCTIONS:                                          //
    //                                                                 //
    /////////////////////////////////////////////////////////////////////

    /**
     * Update enrolment status of users enroled in the current class, completing and locking
     * records where applicable based on class grade and required completion elements
     *
     * @param int $pmuserid  optional userid to update, default(0) updates all users
     */
    function update_enrolment_status($pmuserid = 0) {
        //information about which course this belongs to may not have been
        //loaded due to lazy-loading
        $this->load();

//        if (isset($this->course) && (get_class($this->course) == 'course')) {
        if (isset($this->courseid)) {
            $course = new course($this->courseid);
            $elements = $course->get_completion_elements();
        } else {
            $elements = false;
        }

        $timenow = time();

        if (!empty($elements) && $elements->valid() === true) {
            // for each student, find out how many required completion elements are
            // incomplete, and when the last completion element was graded
            $sql = 'SELECT s.*, grades.incomplete, grades.maxtime
                      FROM {'.student::TABLE.'} s
                      JOIN (SELECT s.userid, COUNT(CASE WHEN grades.id IS NULL AND cc.required = 1 THEN 1
                                                        ELSE NULL END) AS incomplete,
                                    MAX(timegraded) AS maxtime
                              FROM {'.student::TABLE.'} s
                              JOIN {'.coursecompletion::TABLE.'} cc
                                   ON cc.courseid = :courseid
                         LEFT JOIN {'.student_grade::TABLE.'} grades
                                   ON grades.userid = s.userid
                                      AND grades.completionid = cc.id
                                      AND grades.classid = :joinclassid
                                      AND grades.grade >= cc.completion_grade
                             WHERE s.classid = :innerclassid AND s.locked = 0
                          GROUP BY s.userid
                           ) grades ON grades.userid = s.userid
                     WHERE s.classid = :outerclassid AND s.locked = 0';

            $params = array('courseid'     => $this->courseid,
                            'joinclassid'  => $this->id,
                            'innerclassid' => $this->id,
                            'outerclassid' => $this->id);
            if ($pmuserid) {
                $sql .= ' AND s.userid = :userid';
                $params['userid'] = $pmuserid;
            }

            $rs = $this->_db->get_recordset_sql($sql, $params);
            foreach ($rs as $rec) {
                if ($rec->incomplete == 0 && $rec->grade > 0 &&
                    $rec->grade >= $this->course->completion_grade) {
                    $student = new student($rec);
                    $student->completestatusid = STUSTATUS_PASSED;
                    $student->completetime     = $rec->maxtime;
                    $student->credits          = $this->course->credits;
                    $student->locked           = 1;
                    $student->complete();
                }
            }
        } else {
            /// We have no completion elements so just make sure the user's grade is at least the
            /// minimum value required for the course.

            /// Get all unlocked enrolments
            $stufilters = array(new field_filter('classid', $this->id),
                                new field_filter('locked', 0));
            if ($pmuserid) {
                $stufilters[] = new field_filter('userid', $pmuserid);
            }
            $rs = student::find($stufilters);
            foreach ($rs as $rec) {
                if ($rec->grade > 0 && $rec->grade >= $this->course->completion_grade) {
                    $rec->completestatusid = STUSTATUS_PASSED;
                    $rec->completetime     = $timenow;
                    $rec->credits          = $this->course->credits;
                    $rec->locked           = 1;
                    $rec->complete();
                }
            }
        }
        unset($elements);
    }

    /**
     * Counts the number of classes assigned to the course
     *
     * @param int courseid Course id
     */
    function count_course_assignments($courseid) {
        $assignments = pmclass::count(new field_filter('courseid', $courseid), $this->_db);
        return $assignments;
    }

    /////////////////////////////////////////////////////////////////////
    //                                                                 //
    //  DATA FUNCTIONS:                                                //
    //                                                                 //
    /////////////////////////////////////////////////////////////////////


    public static function check_for_moodle_courses($pmuserid = 0) {
        global $DB;

        //crlm_class_moodle moodlecourseid
        $sql = 'SELECT cm.id
                FROM {'.classmoodlecourse::TABLE.'} cm
                LEFT JOIN {course} c ON cm.moodlecourseid = c.id
                WHERE c.id IS NULL';
        $params = array();
        if ($pmuserid) {
            $sql .= ' AND EXISTS (SELECT id FROM {'. student::TABLE .'} stu
                                   WHERE stu.classid = cm.classid
                                     AND stu.userid = ?)';
            $params[] = $pmuserid;
        }

        $broken_classes = $DB->get_recordset_sql($sql, $params);
        foreach ($broken_classes as $class) {
            $DB->delete_records(classmoodlecourse::TABLE, array('id' => $class->id));
        }
        unset($broken_classes);

        return true;
    }

    /////////////////////////////////////////////////////////////////////
    //                                                                 //
    //  STATIC FUNCTIONS:                                              //
    //                                                                 //
    /////////////////////////////////////////////////////////////////////

    public static function get_default() {
        $default_values = array();
        $prefix = self::$config_default_prefix;
        $prefixlen = strlen($prefix);

        foreach (elis::$config->elis_program as $key => $data) {
          if (false !== strpos($key, $prefix)) {
              $index = substr($key, $prefixlen);
              $default_values[$index] = $data;
          }
        }

        return $default_values;
    }

    /**
     * Check for any class nags that need to be handled.
     * Run through all classes to find enrolments where work hasn't started, and enrolments where completion
     * has not been met in the defined timeframe.
     */
    public static function check_for_nags() {
        global $CFG;
        $result = true;

        $sendtouser       = elis::$config->elis_program->notify_classnotstarted_user;
        $sendtorole       = elis::$config->elis_program->notify_classnotstarted_role;
        $sendtosupervisor = elis::$config->elis_program->notify_classnotstarted_supervisor;
        if ($sendtouser || $sendtorole || $sendtosupervisor) {
            $result = self::check_for_nags_notstarted() && $result;
        }

        $sendtouser       = elis::$config->elis_program->notify_classnotcompleted_user;
        $sendtorole       = elis::$config->elis_program->notify_classnotcompleted_role;
        $sendtosupervisor = elis::$config->elis_program->notify_classnotcompleted_supervisor;
        if ($sendtouser || $sendtorole || $sendtosupervisor) {
            $result = self::check_for_nags_notcompleted() && $result;
        }

        return $result;
    }

    public static function check_for_nags_notstarted() {
        global $DB;

        require_once elispm::lib ('notifications.php');

        /// Unstarted classes:
        /// A class is unstarted if
        ///     - it's connected to a Moodle course and it has not been accessed by the user...
        ///    (- it's connected to a Moodle course and no activities have been accessed...) not sure about this
        ///     - it's connected to a Moodle course and no graded activities have been graded...
        ///     - no completion elements have been completed (graded).

        /// Get all enrolments that haven't started.
        /// LEFT JOIN Moodle course and Moodle user info, since they may not have records.
        /// LEFT JOIN notification log where there isn't a notification record for the course and user and 'class_notstarted'.

        $timenow = time();
        $timedelta = elis::$config->elis_program->notify_classnotstarted_days * 60*60*24;

        // If the student is enrolled prior to this time, then they have been
        // enrolled for at least [notify_classnotstarted_days] days
        $startdate = $timenow - $timedelta;

        $select = 'SELECT ccl.*,
                          ccm.moodlecourseid as mcourseid,
                          cce.id as studentid, cce.classid, cce.userid,
                          cce.enrolmenttime, cce.completetime, cce.completestatusid, cce.grade, cce.credits, cce.locked,
                          u.id as muserid ';
        $from   = 'FROM {'.pmclass::TABLE.'} ccl ';
        $join   = 'INNER JOIN {'.student::TABLE.'} cce ON cce.classid = ccl.id
                    LEFT JOIN {'.classmoodlecourse::TABLE.'} ccm ON ccm.classid = ccl.id
                   INNER JOIN {'.user::TABLE.'} cu ON cu.id = cce.userid
                    LEFT JOIN {user} u ON u.idnumber = cu.idnumber
                    LEFT JOIN {user_lastaccess} ul ON ul.userid = u.id AND ul.courseid = ccm.moodlecourseid
                    LEFT JOIN {'.notificationlog::TABLE.'} cnl ON cnl.fromuserid = cu.id AND cnl.instance = ccl.id AND cnl.event = \'class_notstarted\' ';
        $where  = 'WHERE cce.completestatusid = '.STUSTATUS_NOTCOMPLETE.'
                     AND cnl.id IS NULL
                     AND ul.id IS NULL
                     AND cce.enrolmenttime < :startdate ';
        $order  = 'ORDER BY ccl.id ASC ';
        $sql    = $select . $from . $join . $where . $order;
        $params = array('startdate'=> $startdate);

        $classid = 0;
//        $classtempl = new pmclass(); // used just for its properties.
//        $studenttempl = new student(); // used just for its properties.

        $rs = $DB->get_recordset_sql($sql, $params);

        foreach ($rs as $rec) {
            if ($classid != $rec->id) {
                /// Load a new class
                $classid = $rec->id;
//                $classdata = array();
//                foreach ($classtempl->properties as $prop => $type) {
//                    $classdata[$prop] = $rec->$prop;
//                }
//                $pmclass = new pmclass($rec);

                // Move to event handlers
//                $elements = $pmclass->course->get_completion_elements();

                /// Is there a Moodle class?
                $moodlecourseid = (empty($rec->mcourseid)) ? false : $rec->mcourseid;
            }

            /// Load the student...
//            $studentdata = array();
//            foreach ($studenttempl->properties as $prop => $type) {
//                $studentdata[$prop] = $rec->$prop;
//            }
//            $student = new student($studentdata, $pmclass, $elements);
//            $student = new student($rec);
            /// Add the moodlecourseid to the student record so we can use it in the event handler.
//            $student->moodlecourseid = $moodlecourseid;
            $rec->moodlecourseid = $moodlecourseid;

            $moodleuserid = (empty($rec->muserid)) ? false : $rec->muserid;

            mtrace("Triggering class_notstarted event.\n");
//            events_trigger('class_notstarted', $student);
            events_trigger('class_notstarted', $rec);
        }
        $rs->close();
        return true;
    }

    public static function check_for_nags_notcompleted() {
        global $DB;

        /// Incomplete classes:
        /// A class is incomplete if
        ///     - The enrollment record is not marked as complete.

        /// Get all enrolments that haven't started.
        /// LEFT JOIN notification log where there isn't a notification record for the course and user and 'class_notstarted'.

        $timenow = time();
        $timedelta = elis::$config->elis_program->notify_classnotcompleted_days * 24*60*60;

        // If the completion time is prior to this time, then it will complete
        // within [notify_classnotcompleted_days] days
        $enddate = $timenow + $timedelta;

        $select = 'SELECT ccl.*,
                          ccm.moodlecourseid as mcourseid,
                          cce.id as studentid, cce.classid, cce.userid, cce.enrolmenttime,
                          cce.completetime, cce.completestatusid, cce.grade, cce.credits, cce.locked,
                          u.id as muserid ';
        $from   = 'FROM {'.pmclass::TABLE.'} ccl ';
        $join   = 'INNER JOIN {'.student::TABLE.'} cce ON cce.classid = ccl.id
                    LEFT JOIN {'.classmoodlecourse::TABLE.'} ccm ON ccm.classid = ccl.id
                   INNER JOIN {'.user::TABLE.'} cu ON cu.id = cce.userid
                    LEFT JOIN {user} u ON u.idnumber = cu.idnumber
                    LEFT JOIN {'.notificationlog::TABLE.'} cnl ON cnl.fromuserid = cu.id AND cnl.instance = ccl.id AND cnl.event = \'class_notcompleted\' ';
        $where  = 'WHERE cce.completestatusid = '.STUSTATUS_NOTCOMPLETE.'
                     AND cnl.id IS NULL
                     AND ccl.enddate <= :enddate ';
        $order  = 'ORDER BY ccl.id ASC ';
        $sql    = $select . $from . $join . $where . $order;
        $params = array('enddate'=>$enddate);

        $classid = 0;
//        $classtempl = new pmclass(); // used just for its properties.
//        $studenttempl = new student(); // used just for its properties.

        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $rec) {
            if ($classid != $rec->id) {
                /// Load a new class
                $classid = $rec->id;
//                $classdata = array();
//                foreach ($classtempl->properties as $prop => $type) {
//                    $classdata[$prop] = $rec->$prop;
//                }
//                $pmclass = new pmclass($classdata);


//                $elements = $pmclass->course->get_completion_elements();

                /// Is there a Moodle class?
                $moodlecourseid = (empty($rec->mcourseid)) ? false : $rec->mcourseid;
            }

            /// If the class doesn't have an end date, skip it.
            if (empty($rec->enddate)) {
                continue;
            }

            /// Load the student...
//            $studentdata = array();
//            foreach ($studenttempl->properties as $prop => $type) {
//                $studentdata[$prop] = $rec->$prop;
//            }
//            $student = new student($studentdata, $pmclass, $elements);
            /// Add the moodlecourseid to the student record so we can use it in the event handler.
            $rec->moodlecourseid = $moodlecourseid;

            $moodleuserid = (empty($rec->muserid)) ? false : $rec->muserid;

            mtrace("Triggering class_notcompleted event.\n");
            events_trigger('class_notcompleted', $rec);
        }
        $rs->close();
        return true;
    }

    /**
     * returns a class object given the class idnumber
     *
     * @param string $idnumber class idnumber
     * @return object pmclass corresponding to the idnumber or null
     */
    public static function get_by_idnumber($idnumber) {
        global $DB;
        $retval = $DB->get_record(pmclass::TABLE, array('idnumber'=>$idnumber));

        if(!empty($retval)) {
            $retval = new pmclass($retval->id);
        } else {
            $retval = null;
        }

        return $retval;
    }

    /**
     *
     * @param <type> $crss
     * @return <type>
     */
    function format_course_listing($crss) {
        $curcourselist = array();

        foreach ($crss as $crsid => $crs) {
            $curcourse = curriculumcourse_get_list_by_course($crsid);
            foreach ($curcourse as $rowid => $obj) {
                $curcourselist[$obj->curriculumid][$obj->courseid] = $obj->id;
            }
            unset($curcourse);
        }
        return $curcourselist;
    }

    /**
     * Auto create a class requiring a course id for minimum info
     *
     * @param array param array with key - crlm_class property ('courseid' minimum)
     * value - property value
     *
     * @return mixed course id if created, false if error encountered
     */
    function auto_create_class($params = array()) {
        if (empty($this->courseid) &&
            (empty($params) || !array_key_exists('courseid', $params))) {
            return false;
        }

        // ELIS-3429: Initialize start/end-time-minute/hour fields to out_of-range
        $this->starttimeminute = 61;
        $this->starttimehour   = 25;
        $this->endtimeminute   = 61;
        $this->endtimehour     = 25;

        if (!empty($params)) {
            foreach ($params as $key => $data) {
                $this->$key = $data;
            }
        }

        $defaults = $this->get_default();
        if (!empty($defaults)) {
            foreach ($defaults as $key => $data) {
                if (!isset($this->$key)) {
                    try {
                        $this->$key = $data;
                    } catch (data_object_exception $ex) {
                        // ELIS-3989: just log - TBV
                        error_log("/elis/program/lib/data/pmclass.class.php::auto_create_class() - data_object_exception setting property from defaults: $key = $data");
                    }
                }
            }
        }

        // Check for course id one more time
        if (!empty($this->courseid) or 0 !== intval($this->courseid)) {
            $this->startdate = !isset($this->startdate) ? time() : $this->startdate;
            $this->enddate = !isset($this->enddate) ? time() : $this->enddate;

            $this->save();
        }

        return $this->id;
    }

    /**
     * Returns an array of cluster ids that are associated to the supplied class through tracks and
     * the current user has access to enrol users into
     *
     * @param   int        $clsid  The class whose association ids we care about
     * @return  int array          The array of accessible cluster ids
     */
    public static function get_allowed_clusters($clsid) {
        global $USER;

        $context = pm_context_set::for_user_with_capability('cluster', 'elis/program:class_enrol_userset_user', $USER->id);

        $allowed_clusters = array();

        // TODO: Ugly, this needs to be overhauled
        $cpage = new pmclasspage();

        if ($cpage->_has_capability('elis/program:class_enrol_userset_user', $clsid)) {
            require_once elispm::lib('data/clusterassignment.class.php');
            $cmuserid = pm_get_crlmuserid($USER->id);
            $userclusters = clusterassignment::find(new field_filter('userid', $cmuserid));
            foreach ($userclusters as $usercluster) {
                $allowed_clusters[] = $usercluster->clusterid;
            }
        }

        //we first need to go through tracks to get to clusters
        $track_listing = new trackassignment(array('classid' => $clsid));
        $tracks = $track_listing->get_assigned_tracks();

        //iterate over the track ides, which are the keys of the array
        if(!empty($tracks)) {
            foreach(array_keys($tracks) as $track) {
                //get the clusters and check the context against them
                $clusters = clustertrack::get_clusters($track);
                $allowed_track_clusters = $context->get_allowed_instances($clusters, 'cluster', 'clusterid');

                //append all clusters that are allowed by the available clusters contexts
                foreach($allowed_track_clusters as $allowed_track_cluster) {
                    $allowed_clusters[] = $allowed_track_cluster;
                }
            }
        }

        return $allowed_clusters;
    }

    /**
     * Clone a class
     * @param array $options options for cloning.  Valid options are:
     * - 'moodlecourses': whether or not to clone Moodle courses (if they were
     *   autocreated).  Values can be (default: "copyalways"):
     *   - "copyalways": always copy course
     *   - "copyautocreated": only copy autocreated courses
     *   - "autocreatenew": autocreate new courses from course template
     *   - "link": link to existing course
     * - 'targetcourse': the course id to associate the clones with (default:
     *   same as original class)
     * @return array array of array of object IDs created.  Key in outer array
     * is type of object (plural).  Key in inner array is original object ID,
     * value is new object ID.  Outer array also has an entry called 'errors',
     * which is an array of any errors encountered when duplicating the
     * object.
     */
    function duplicate(array $options=array()) {
        //needed by the rollover lib
        global $CFG;
        require_once(elis::lib('rollover/lib.php'));

        $objs = array('errors' => array());
        if (isset($options['targetcluster'])) {
            $userset = $options['targetcluster'];
            if (!is_object($userset) || !is_a($userset, 'userset')) {
                $options['targetcluster'] = $userset = new userset($userset);
            }
        }

        // Due to lazy loading, we need to pre-load this object
        $this->load();

        // clone main class object
        $clone = new pmclass($this);
        unset($clone->id);

        if (isset($options['targetcourse'])) {
            $clone->courseid = $options['targetcourse'];
        }
        $idnumber = $clone->idnumber;
        if (isset($userset)) {
            $idnumber = append_once($idnumber, ' - '. $userset->name,
                                    array('maxlength' => 95));
        }

        //get a unique idnumber
        $clone->idnumber = generate_unique_identifier(pmclass::TABLE, 'idnumber', $idnumber, array('idnumber' => $idnumber));

        $clone->autocreate = false; // avoid warnings
        $clone->save();

        $objs['classes'] = array($this->id => $clone->id);

        $cmc = $this->_db->get_record(classmoodlecourse::TABLE, array('classid'=>$this->id));
        if ($cmc) {
            if ($cmc->autocreated == -1) {
                $cmc->autocreated = elis::$config->elis_program->autocreated_unknown_is_yes;
            }
            if (empty($options['moodlecourses']) || $options['moodlecourses'] == 'copyalways'
                || ($options['moodlecourses'] == 'copyautocreated' && $cmc->autocreated)) {
                // create a new Moodle course based on the current class's Moodle course
                $moodlecourseid   = course_rollover($cmc->moodlecourseid);
                //check that the course has rolled over successfully
                if (!$moodlecourseid) {
                    return false;
                }
                // Rename the fullname, shortname and idnumber of the restored course
                $restore = new stdClass;
                $restore->id = $moodlecourseid;
                // ELIS-2941: Don't prepend course name if already present ...
                if (strpos($clone->idnumber, $clone->course->name) !== 0) {
                    $restore->fullname = $clone->course->name .'_'. $clone->idnumber;
                } else {
                    $restore->fullname = $clone->idnumber;
                }
                $restore->shortname = $clone->idnumber;
                $this->_db->update_record('course', $restore);
                moodle_attach_class($clone->id, $moodlecourseid);
            } elseif ($options['moodlecourses'] == 'link' ||
                      ($options['moodlecourses'] == 'copyautocreated' && !$cmc->autocreated)) {
                // link to the current class's Moodle course
                moodle_attach_class($clone->id, $cmc->moodlecourseid);
            } else {
                // $options['moodlecourses'] == 'autocreatenew'
                // create a new course based on the course template
                moodle_attach_class($clone->id, 0, '', false, false, true);
            }
        }

        return $objs;
    }

    static $validation_rules = array(
        'validate_courseid_not_empty',
        'validate_idnumber_not_empty',
        'validate_unique_idnumber'
    );

    function validate_courseid_not_empty() {
        return validate_not_empty($this, 'courseid');
    }

    function validate_idnumber_not_empty() {
        return validate_not_empty($this, 'idnumber');
    }

    function validate_unique_idnumber() {
        return validate_is_unique($this, array('idnumber'));
    }

    public function save() {
        $isnew = empty($this->id);

        parent::save();

        if (isset($this->track) && is_array($this->track)) {
            $param['classid'] = $this->id;
            $param['courseid'] = $this->courseid;
            foreach ($this->track as $t) {
                if (trackassignment::exists(array(new field_filter('classid', $this->id),
                                                  new field_filter('trackid', $t)))) {
                    continue;
                }
                $param['trackid'] = $t;
                $trackassignobj = new trackassignment($param);
                $trackassignobj->save();
            }
        }

        if (isset($this->unlink_attached_course) && isset($this->moodlecourseid)) {
            // process unlink moodle course id request
            $return = moodle_detach_class($this->id, $this->moodlecourseid);
            $this->moodlecourseid = 0;
        }

        if ($this->moodlecourseid || $this->autocreate) {
            moodle_attach_class($this->id, $this->moodlecourseid, '', true, true, $this->autocreate);
        }

        if (!$isnew) {
            if (!empty($this->oldmax) &&
                (!$this->maxstudents || $this->oldmax < $this->maxstudents) &&
                ($waiting = waitlist::count_records($this->id)) > 0) {
                $start = student_count_records($this->id);
                $max = $this->maxstudents ? $this->maxstudents
                                          : ($start + $waiting + 1);
                //error_log("pmclass.class.php::save() oldmax = {$this->oldmax}, start = {$start}, newmax = {$this->maxstudents}, waiting = {$waiting} ... max = {$max}");
                for ($i = $start; $i < $max; $i++) {
                    $next_student = waitlist::get_next($this->id);
                    if (empty($next_student)) {
                        break;
                    }
                    $next_student->enrol();
                }
            }
        }

        field_data::set_for_context_from_datarecord(CONTEXT_ELIS_CLASS, $this);
    }

}

/// Non-class supporting functions. (These may be able to replaced by a generic container/listing class)


/**
 * Gets a course listing with specific sort and other filters.
 *
 * @param   string          $sort        Field to sort on
 * @param   string          $dir         Direction of sort
 * @param   int             $startrec    Record number to start at
 * @param   int             $perpage     Number of records per page
 * @param   string          $namesearch  Search string for course name
 * @param   string          $alpha       Start initial of course name filter
 * @param   int             $id          Corresponding courseid, or zero for any
 * @param   boolean         $onlyopen    If true, only consider classes whose end date has not been passed
 * @param   pm_context_set  $contexts    Contexts to provide permissions filtering, of null if none
 * @param   int             $clusterid   Id of a cluster that the class must be assigned to via a track
 * @return  recordset                    Returned records
 */
function pmclass_get_listing($sort = 'crsname', $dir = 'ASC', $startrec = 0,
                             $perpage = 0, $namesearch = '', $alpha = '', $id = 0, $onlyopen=false,
                             $contexts=null, $clusterid = 0, $extrafilters = array()) {
    global $USER, $DB;

    $select = 'SELECT cls.*, cls.starttimehour as starttimehour, cls.starttimeminute as starttimeminute, ' .
              'cls.endtimehour as endtimehour, cls.endtimeminute as endtimeminute, crs.name as crsname, ' .
              'clsmoodle.moodlecourseid as moodlecourseid, mcrs.fullname as moodlecourse ';
    $tables = 'FROM {'.pmclass::TABLE.'} cls ';
    $join   = 'JOIN {'.course::TABLE.'} crs
               ON crs.id = cls.courseid
               LEFT JOIN {'.classmoodlecourse::TABLE.'} clsmoodle
               ON clsmoodle.classid = cls.id
               LEFT JOIN {course} mcrs
               ON clsmoodle.moodlecourseid = mcrs.id
              ';

    //class associated to a particular cluster via a track
    if(!empty($clusterid)) {
        $join .= 'JOIN {'.trackassignment::TABLE.'} clstrk
                  ON clstrk.classid = cls.id
                  JOIN {'.clustertrack::TABLE.'} clsttrk
                  ON clsttrk.trackid = clstrk.trackid
                  AND clsttrk.clusterid = '.$clusterid.'
                 ';
    }

    //assert that classes returned were requested by the current user using the course / class
    //request block and approved
    if (!empty($extrafilters['show_my_approved_classes'])) {
        $join .= 'JOIN {block_course_request} request
                  ON cls.id = request.classid
                  AND request.userid = '.$USER->id.'
                 ';
    }

    $where = array();
    $params = array();

    if (!empty($namesearch)) {
        $namesearch = trim($namesearch);

        $crslike = $DB->sql_like('crs.name', '?', FALSE);
        $clslike = $DB->sql_like('cls.idnumber', '?', FALSE);

        $where[] = "(($crslike) OR ($clslike))";
        $params = array_merge($params, array("%$namesearch%", "%$namesearch%"));
    }

    if ($alpha) {
        $crslike = $DB->sql_like('cls.idnumber', '?', FALSE);
        $where[] = "($crslike)";
        $params[] = "$alpha%";
    }

    if ($id) {
        $where[] = is_array($id) ? '(crs.id IN ('. implode(', ', $id) .'))'
                                 : "(crs.id = $id)";
    }

    if ($onlyopen) {
        $curtime = time();
        $where[] = "(cls.enddate > $curtime OR NOT cls.enddate)";
    }

    if ($contexts !== null) {
        $filter_object = $contexts->get_filter('id', 'class');
        $filter_sql = $filter_object->get_sql(false, 'cls');
        if (isset($filter_sql['where'])) {
            $where[] = $filter_sql['where'];
            $params += $filter_sql['where_parameters'];
        }
    }

    if (!empty($where)) {
        $where = 'WHERE '.implode(' AND ',$where).' ';
    } else {
        $where = '';
    }

    if ($sort) {
        if ($sort == 'starttime') {
            $sort = "ORDER BY starttimehour $dir, starttimeminute $dir ";
        } elseif ($sort == 'endtime') {
            $sort = "ORDER BY endtimehour $dir, endtimeminute $dir ";
        } else {
            $sort = "ORDER BY $sort $dir ";
        }
    }

    $sql = $select.$tables.$join.$where.$sort;

    return $DB->get_recordset_sql($sql, $params, $startrec, $perpage);
}

/**
 * Calculates the number of records in a listing as created by pmclass_get_listing
 *
 * @param   string          $namesearch  Search string for course name
 * @param   string          $alpha       Start initial of course name filter
 * @param   int             $id          Corresponding courseid, or zero for any
 * @param   boolean         $onlyopen    If true, only consider classes whose end date has not been passed
 * @param   pm_context_set  $contexts    Contexts to provide permissions filtering, of null if none
 * @param   int             $clusterid   Id of a cluster that the class must be assigned to via a track
 * @return  int                          The number of records
 */
function pmclass_count_records($namesearch = '', $alpha = '', $id = 0, $onlyopen = false, $contexts = null, $clusterid = 0) {
    global $DB;

    $select = 'SELECT COUNT(cls.id) ';
    $tables = 'FROM {'.pmclass::TABLE.'} cls ';
    $join   = 'LEFT JOIN {'.course::TABLE.'} crs ' .
              'ON crs.id = cls.courseid ';

    //class associated to a particular cluster via a track
    if(!empty($clusterid)) {
        $join .= 'JOIN {'.trackassignment::TABLE.'} clstrk
                  ON clstrk.classid = cls.id
                  JOIN {'.clustertrack::TABLE.'} clsttrk
                  ON clsttrk.trackid = clstrk.trackid
                  AND clsttrk.clusterid = '.$clusterid.' ';
    }

    $where  = array();
    $params = array();

    if (!empty($namesearch)) {
        $crslike = $DB->sql_like('crs.name', '?', FALSE);
        $clslike = $DB->sql_like('cls.idnumber', '?', FALSE);

        $where[] = "(($crslike) OR ($clslike))";
        $params = array_merge($params, array("%$namesearch%", "%$namesearch%"));
    }

    if ($alpha) {
        $crslike = $DB->sql_like('cls.idnumber', '?', FALSE);
        $where[] = "($crslike)";
        $params[] = "$alpha%";
    }

    if ($id) {
        $where[] = "(crs.id = $id)";
    }

    if ($onlyopen) {
        $curtime = time();
        $where[] = "(cls.enddate > $curtime OR NOT cls.enddate)";
    }

    if ($contexts !== null) {
        $filter_object = $contexts->get_filter('id', 'class');
        $filter_sql = $filter_object->get_sql(false, 'cls');
        if (isset($filter_sql['where'])) {
            $where[] = $filter_sql['where'];
            $params += $filter_sql['where_parameters'];
        }
    }

    if (!empty($where)) {
        $where = 'WHERE '.implode(' AND ',$where).' ';
    } else {
        $where = '';
    }

    $sql = $select . $tables . $join . $where;

    return $DB->count_records_sql($sql, $params);
}

function pmclass_get_record_by_courseid($courseid) {
    return pmclass::find(new field_filter('courseid', $courseid));
}
