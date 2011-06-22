<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2010 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @subpackage curriculummanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2010 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once CURMAN_DIRLOCATION . '/lib/datarecord.class.php';
require_once CURMAN_DIRLOCATION . '/lib/course.class.php';
require_once CURMAN_DIRLOCATION . '/lib/environment.class.php';
require_once CURMAN_DIRLOCATION . '/lib/instructor.class.php';
require_once CURMAN_DIRLOCATION . '/lib/student.class.php';
require_once CURMAN_DIRLOCATION . '/lib/attendance.class.php';
require_once CURMAN_DIRLOCATION . '/lib/classmoodlecourse.class.php';
require_once CURMAN_DIRLOCATION . '/form/cmclassform.class.php';
require_once CURMAN_DIRLOCATION . '/lib/coursetemplate.class.php';
require_once CURMAN_DIRLOCATION . '/lib/track.class.php';
require_once CURMAN_DIRLOCATION . '/lib/curriculumcourse.class.php';
require_once CURMAN_DIRLOCATION . '/lib/taginstance.class.php';
require_once CURMAN_DIRLOCATION . '/lib/track.class.php';
require_once CURMAN_DIRLOCATION . '/lib/customfield.class.php';

define ('CLSTABLE', 'crlm_class');
define ('CLSMOODLETABLE', 'crlm_class_moodle');
define ('CLSGRTABLE', 'crlm_class_graded');  // TODO: this is a dup, remove it.
define ('CLSTRACKCLS', 'crlm_track_class');

class cmclass extends datarecord {

    var $verbose_name = 'class';
    static $config_default_prefix = 'clsdft';

/*
    var $id;            // INT - The data id if in the database.
    var $idnumber;      // STRING - A unique ID number for this class.
    var $courseid;      // INT - The course this class is based off.
    var $course;        // OBJECT - The actual course object associated with the ID.
    var $startdate;     // INT - Time the class begings - timestamp.
    var $enddate;       // INT - Time the class ends - timestamp.
    var $duration;      // INT -
    var $maxstudents;   // INT - The maximum number of students this class can hold.
    var $environmentid; // INT - Intended environment for this course.
    var $environment;   // OBJECT - Actual environment object associated with ID.

    var $_dbloaded;     // BOOLEAN - True if loaded from database.
*/

    private $form_url = null;  //moodle_url object

    /**
     * Contructor.
     *
     * @param $cmclassdata int/object/array The data id of a data record or data elements to load manually.
     *
     */
    public function cmclass($cmclassdata=false) {
        global $CURMAN;

        parent::datarecord();

        $this->set_table(CLSTABLE);
        $this->add_property('id', 'int');
        $this->add_property('idnumber', 'string', true);
        $this->add_property('courseid', 'int', true);
        $this->add_property('startdate', 'int');
        $this->add_property('enddate', 'int');
        $this->add_property('duration', 'int');
        $this->add_property('starttimehour', 'int');
        $this->add_property('starttimeminute', 'int');
        $this->add_property('endtimehour', 'int');
        $this->add_property('endtimeminute', 'int');
        $this->add_property('maxstudents', 'int');
        $this->add_property('environmentid', 'int');
        $this->add_property('enrol_from_waitlist', 'int');

        if (is_numeric($cmclassdata)) {
            $this->data_load_record($cmclassdata);
        } else if (is_array($cmclassdata)) {
            $this->data_load_array($cmclassdata);
        } else if (is_object($cmclassdata)) {
            $this->data_load_array(get_object_vars($cmclassdata));
        }

        if (!empty($this->id)) {
            // custom fields
            $level = context_level_base::get_custom_context_level('class', 'block_curr_admin');
            if ($level) {
                $fielddata = field_data::get_for_context(get_context_instance($level,$this->id));
                $fielddata = $fielddata ? $fielddata : array();
                foreach ($fielddata as $name => $value) {
                    $this->{"field_{$name}"} = $value;
                }
            }
        }

        if (!empty($this->courseid)) {
            $this->course = new course($this->courseid);
        }

        if (!empty($this->environmentid)) {
            $this->environment = new environment($this->environmentid);
        }

        $this->moodlecourseid = $this->get_moodle_course_id();
    }

    function get_start_time() {
        $starttime = ($this->starttimehour - get_user_timezone_offset()) * HOURSECS;
        $starttime += $this->starttimeminute * MINSECS;

        return $starttime;
    }

    function get_end_time() {
        $endtime = ($this->endtimehour - get_user_timezone_offset()) * HOURSECS;
        $endtime += $this->endtimeminute * MINSECS;

        return $endtime;
    }

    function get_moodle_course_id() {
        global $CURMAN;

        $mdlrec = $CURMAN->db->get_record(CLSMDLTABLE, 'classid', $this->id);
        return !empty($mdlrec) ? $mdlrec->moodlecourseid : 0;
        //$this->moodlesiteid = !empty($mdlrec->siteid) ? $mdlrec->siteid : 0;
    }

    public static function delete_for_course($id) {
        global $CURMAN;

        return $CURMAN->db->delete_records(CLSTABLE, 'courseid', $id);
    }

    /*
     * Returns an aggregate of enrolment completion statuses for this class.
     *
     * @see course::get_completion_counts()
     */
    public function get_completion_counts() {
        global $CURMAN;

        $sql = "SELECT cce.completestatusid status, COUNT(cce.completestatusid) count
        FROM {$CURMAN->db->prefix_table(STUTABLE)} cce
        INNER JOIN {$CURMAN->db->prefix_table(CLSTABLE)} cc ON cc.id = cce.classid
        WHERE cc.id = {$this->id}
        GROUP BY cce.completestatusid";

        $rows = $CURMAN->db->get_records_sql($sql);

        $ret = array(STUSTATUS_NOTCOMPLETE=>0, STUSTATUS_FAILED=>0, STUSTATUS_PASSED=>0);

        if (empty($rows)) {
            return $ret;
        }

        foreach($rows as $row) {
            // We add the counts to the existing array, which should be as good as an assignment
            // because we never have duplicate statuses.  Of course, stranger things have happened.

            $ret[$row->status] += $row->count;
        }

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

//    public function create_edit_form($formid='', $rows=2, $cols=40) {
//        $configdata = array();
//        $configdata['id'] = $this->id;
//        $configdata['courseid'] = $this->courseid;
//        $configdata['display_12h'] = true;
//
//        $this->form = new cmclassform($this->form_url, $configdata);
//
//        $this->starttime = ($this->starttimehour + 5) * HOURSECS;
//        $this->starttime += $this->starttimeminute * MINSECS;
//
//        $this->endtime = ($this->endtimehour + 5) * HOURSECS;
//        $this->endtime += $this->endtimeminute * MINSECS;
//
//        $this->form->set_data($this);
//
//        return $this->form;
//    }

    public function set_from_data($data) {
        if(!empty($data->moodleCourses['autocreate'])) {
            $this->autocreate = $data->moodleCourses['autocreate'];
        } else {
            $this->autocreate = false;
        }

        if(isset($data->disablestart)) {
            $this->startdate = 0;
        }

        if(isset($data->disableend)) {
            $this->enddate = 0;
        }

        if (!empty($data->moodleCourses['moodlecourseid']) && !$this->autocreate) {
            $this->moodlecourseid = $data->moodleCourses['moodlecourseid'];
        } else {
            $this->moodlecourseid = 0;
        }

        if (isset($data->track)) {
            $this->track = $data->track;
        }


        $this->oldmax = $this->maxstudents;

        $fields = field::get_for_context_level('class', 'block_curr_admin');
        $fields = $fields ? $fields : array();
        foreach ($fields as $field) {
            $fieldname = "field_{$field->shortname}";
            if (isset($data->$fieldname)) {
                $this->$fieldname = $data->$fieldname;
            }
        }

        parent::set_from_data($data);
    }

    public function add() {
        $status = parent::add();

        if ($this->moodlecourseid || $this->autocreate) {
            moodle_attach_class($this->id, $this->moodlecourseid, '', true, true, $this->autocreate);
        }

        $status = $status && field_data::set_for_context_from_datarecord('class', $this);

        return $status;
    }

    public function update() {
        $status = parent::update();

        if ($this->moodlecourseid || $this->autocreate) {
            moodle_attach_class($this->id, $this->moodlecourseid, '', true, true, $this->autocreate);
        }


        if(!empty($this->oldmax) && $this->oldmax < $this->maxstudents && waitlist::count_records($this->id) > 0) {
            for($i = $this->oldmax; $i < $this->maxstudents; $i++) {
                $next_student = waitlist::get_next($this->id);

                if(!empty($next_student)) {
                    $next_student->enrol();
                } else {
                    break;
                }
            }
        }

        $status = $status && field_data::set_for_context_from_datarecord('class', $this);

        return $status;
    }

/*
 * Perform all the necessary steps to delete all aspects of a class.
 *
 */
    function delete() {
        $status = true;
        if (!empty($this->id)) {
            instructor::delete_for_class($this->id);
            student::delete_for_class($this->id);
            trackassignmentclass::delete_for_class($this->id);
            classmoodlecourse::delete_for_class($this->id);
            student_grade::delete_for_class($this->id);
            attendance::delete_for_class($this->id);
            taginstance::delete_for_class($this->id);
            waitlist::delete_for_class($this->id);
            classmoodlecourse::delete_for_class($this->id);

            $level = context_level_base::get_custom_context_level('class', 'block_curr_admin');
            $result = delete_context($level,$this->id);
            
            $status = $this->data_delete_record();
        }

        return $status;
    }

    function to_string() {
        $coursename = isset($this->course) ? $this->course->name : '';
        return $this->idnumber . ' ' . $coursename;
    }

    /*
     * Remove specified environment from all courses.
     *
     * @param $envid int Environment id.
     * @return bool Status of operation.
     */
    public static function remove_environment($envid) {
    	global $CURMAN;

    	$sql = 'UPDATE ' . $CURMAN->db->prefix_table(CLSTABLE) . ' SET environmentid=0 where environmentid=' . $envid;
    	return $CURMAN->db->execute_sql($sql, "");
    }

    /**
     * Determine whether a class is currently (manually) enrollable.
     * Checks if the class is associated with a Moodle course.
     * Checks whether the Moodle course is enrollable.
     * The logic is mostly copied/based on /course/enrol.php
     */
    public function is_enrollable() {
        global $CURMAN, $CFG;
        $courseid = $this->get_moodle_course_id();
        // no associated Moodle course, so we're always enrollable
        if ($courseid == 0) {
            return true;
        }
        $course = $CURMAN->db->get_record('course', 'id', $courseid);
        // check that this is a valid course to enrol into
        if (!$course || $course->metacourse || $course->id == SITEID) {
            return false;
        }
        // check that the course is enrollable, and that we are within the
        // enrolment dates
        if (!$course->enrollable ||
            ($course->enrollable == 2 && $course->enrolstartdate > 0 && $course->enrolstartdate > time()) ||
            ($course->enrollable == 2 && $course->enrolenddate > 0 && $course->enrolenddate <= time())
            ) {
            return false;
        }
        // check if the course is using the ELIS enrolment plugin
        $enrol = $course->enrol;
        if (!$enrol) {
            $enrol = $CFG->enrol;
        }
        if ($CURMAN->config->restrict_to_elis_enrolment_plugin && $enrol != 'elis') {
            return false;
        }
        // check that the enrolment plugin allows manual enrolment
        require_once("$CFG->dirroot/enrol/enrol.class.php");
        $enrol = enrolment_factory::factory($course->enrol);
        if (!method_exists($enrol, 'print_entry')) {
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
     * Update grades for this class
     *
     * @param array The class grades
     */
    function update_all_class_grades($classgrades = array()) {
        global $CURMAN;

        if (isset($this->course) && (get_class($this->course) == 'course')) {
            $elements = $this->course->get_completion_elements();
        } else {
            $elements = false;
        }

        $timenow = time();

        if (!empty($elements)) {
            // for each student, find out how many required completion elements are
            // incomplete, and when the last completion element was graded
            $sql = "SELECT s.*, grades.incomplete, grades.maxtime
                      FROM {$CURMAN->db->prefix_table(STUTABLE)} s
                      JOIN (SELECT s.userid, COUNT(CASE WHEN grades.id IS NULL AND cc.required = 1 THEN 1
                                                        ELSE NULL END) AS incomplete,
                                    MAX(timegraded) AS maxtime
                              FROM {$CURMAN->db->prefix_table(STUTABLE)} s
                              JOIN {$CURMAN->db->prefix_table(CRSCOMPTABLE)} cc
                                   ON cc.courseid = {$this->courseid}
                         LEFT JOIN {$CURMAN->db->prefix_table(CLSGRTABLE)} grades
                                   ON grades.userid = s.userid
                                      AND grades.completionid = cc.id
                                      AND grades.classid = {$this->id}
                                      AND grades.grade >= cc.completion_grade
                             WHERE s.classid = {$this->id} AND s.locked = 0
                          GROUP BY s.userid
                           ) grades ON grades.userid = s.userid
                     WHERE s.classid = {$this->id} AND s.locked = 0";
            $rs = get_recordset_sql($sql);
            if ($rs) {
                while ($rec = rs_fetch_next_record($rs)) {
                    if ($rec->incomplete == 0 && $rec->grade > 0 &&
                        $rec->grade >= $this->course->completion_grade) {
                        $student = new student($rec, $this, null);
                        $student->completestatusid = STUSTATUS_PASSED;
                        $student->completetime     = $rec->maxtime;
                        $student->credits          = $this->course->credits;
                        $student->locked           = 1;
                        $student->complete();
                    }
                }
            }
        } else {
            /// We have no completion elements so just make sure the user's grade is at least the
            /// minimum value required for the course.

            /// Get all unlocked enrolments
            $select  = "classid = {$this->id} AND locked = 0";
            $rs = get_recordset_select(STUTABLE, $select, 'userid');
            if ($rs) {
                while ($rec = rs_fetch_next_record($rs)) {
                    if ($rec->grade > 0 && $rec->grade >= $this->course->completion_grade) {
                        $student = new student($rec, $this, null);
                        $student->completestatusid = STUSTATUS_PASSED;
                        $student->completetime     = $timenow;
                        $student->credits          = $this->course->credits;
                        $student->locked           = 1;
                        $student->complete();
                    }
                }
            }
        }
    }

    /**
     * Counts the number of classes assigned to the course
     *
     * @param int courseid Course id
     * @param int curriculumid Curriculum id
     */
    function count_course_assignments($courseid) {
        global $CURMAN;

        $assignments = $CURMAN->db->count_records(CLSTABLE, 'courseid', $courseid);
        return $assignments;
    }

/////////////////////////////////////////////////////////////////////
//                                                                 //
//  DATA FUNCTIONS:                                                //
//                                                                 //
/////////////////////////////////////////////////////////////////////


    /**
     * Data function to attach a Moodle course with this class object.
     *
     * @param int  $cid             The Moodle course ID.
     * @param bool $enrolinstructor Flag for enroling instructors into the Moodle course (optional).
     * @param bool $enrolstudent    Flag for enroling students into the Moodle course (optional).
     */
    function data_attach_moodle_course($cid, $enrolinstructor = false, $enrolstudent = false) {

    }

    /**
     * Check for a duplicate record when doing an insert.
     *
     * @param boolean $record true if a duplicate is found false otherwise
     * note: output is expected and treated as boolean please ensure return values are boolean
     */
    function duplicate_check($record=null) {
        global $CURMAN;

        if(empty($record)) {
            $record = $this;
        }

        /// Check for valid idnumber - it can't already exist in the user table.
        if ($CURMAN->db->record_exists($this->table, 'idnumber', $record->idnumber)) {
            return true;
        }

        return false;
    }

    public static function check_for_moodle_courses() {
        global $CURMAN;

        //crlm_class_moodle moodlecourseid
        $sql = 'SELECT cm.id
                FROM ' . $CURMAN->db->prefix_table(CLSMOODLETABLE) . ' AS cm
                LEFT JOIN ' . $CURMAN->db->prefix_table('course') . ' AS c ON cm.moodlecourseid = c.id
                WHERE c.id IS NULL';

        $broken_classes = $CURMAN->db->get_records_sql($sql);

        if(!empty($broken_classes)) {
            foreach($broken_classes as $class) {
                $CURMAN->db->delete_records(CLSMOODLETABLE, 'id', $class->id);
            }
        }

        return true;
    }

/////////////////////////////////////////////////////////////////////
//                                                                 //
//  STATIC FUNCTIONS:                                              //
//                                                                 //
/////////////////////////////////////////////////////////////////////

    public static function get_default() {
        global $CURMAN;

        $default_values = array();
        $prefix = self::$config_default_prefix;
        $prefixlen = strlen($prefix);

        foreach ($CURMAN->config as $key => $data) {

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
        global $CFG, $CURMAN;
        $result = true;

        $sendtouser = $CURMAN->config->notify_classnotstarted_user;
        $sendtorole = $CURMAN->config->notify_classnotstarted_role;
        if ($sendtouser || $sendtorole) {
            $result = self::check_for_nags_notstarted() && $result;
        }

        $sendtouser = $CURMAN->config->notify_classnotcompleted_user;
        $sendtorole = $CURMAN->config->notify_classnotcompleted_role;
        if ($sendtouser || $sendtorole) {
            $result = self::check_for_nags_notcompleted() && $result;
        }

        return $result;
    }

    public static function check_for_nags_notstarted() {
        global $CFG, $CURMAN;

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
        $timedelta = $CURMAN->config->notify_classnotstarted_days * 60*60*24;
        // If the student is enrolled prior to this time, then they have been
        // enrolled for at least [notify_classnotstarted_days] days
        $startdate = $timenow - $timedelta;

        $select = "SELECT ccl.*,
                          ccm.moodlecourseid as mcourseid,
                          cce.id as studentid, cce.classid, cce.userid,
                          cce.enrolmenttime, cce.completetime, cce.completestatusid, cce.grade, cce.credits, cce.locked,
                          u.id as muserid ";
        $from   = "FROM {$CFG->prefix}crlm_class ccl ";
        $join   = "INNER JOIN {$CFG->prefix}crlm_class_enrolment cce ON cce.classid = ccl.id
                    LEFT JOIN {$CFG->prefix}crlm_class_moodle ccm ON ccm.classid = ccl.id
                   INNER JOIN {$CFG->prefix}crlm_user cu ON cu.id = cce.userid
                    LEFT JOIN {$CFG->prefix}user u ON u.idnumber = cu.idnumber
                    LEFT JOIN {$CFG->prefix}user_lastaccess ul ON ul.userid = u.id AND ul.courseid = ccm.moodlecourseid
                    LEFT JOIN {$CFG->prefix}crlm_notification_log cnl ON cnl.userid = cu.id AND cnl.instance = ccl.id AND cnl.event = 'class_notstarted' ";
        $where  = "WHERE cce.completestatusid = ".STUSTATUS_NOTCOMPLETE."
                     AND cnl.id IS NULL
                     AND ul.id IS NULL
                     AND cce.enrolmenttime < $startdate ";
        $order  = "ORDER BY ccl.id ASC ";
        $sql    = $select . $from . $join . $where . $order;

        $classid = 0;
        $classtempl = new cmclass(); // used just for its properties.
        $studenttempl = new student(); // used just for its properties.

        $rs = get_recordset_sql($sql);
        if ($rs) {
            while ($rec = rs_fetch_next_record($rs)) {
                if ($classid != $rec->id) {
                /// Load a new class
                    $classid = $rec->id;
                    $classdata = array();
                    foreach ($classtempl->properties as $prop => $type) {
                        $classdata[$prop] = $rec->$prop;
                    }
                    $cmclass = new cmclass($classdata);

                    $elements = $cmclass->course->get_completion_elements();

                /// Is there a Moodle class?
                    $moodlecourseid = (empty($rec->mcourseid)) ? false : $rec->mcourseid;
                }

                /// Load the student...
                $studentdata = array();
                foreach ($studenttempl->properties as $prop => $type) {
                    $studentdata[$prop] = $rec->$prop;
                }
                $student = new student($studentdata, $cmclass, $elements);
                /// Add the moodlecourseid to the student record so we can use it in the event handler.
                $student->moodlecourseid = $moodlecourseid;

                $moodleuserid = (empty($rec->muserid)) ? false : $rec->muserid;

                mtrace("Triggering class_notstarted event.\n");
                events_trigger('class_notstarted', $student);
            }
            rs_close($rs);
        }
        return true;
    }

    public static function check_for_nags_notcompleted() {
        global $CFG, $CURMAN;

        /// Incomplete classes:
        /// A class is incomplete if
        ///     - The enrollment record is not marked as complete.

    /// Get all enrolments that haven't started.
    /// LEFT JOIN notification log where there isn't a notification record for the course and user and 'class_notstarted'.

        $timenow = time();
        $timedelta = $CURMAN->config->notify_classnotcompleted_days * 24*60*60;
        // If the completion time is prior to this time, then it will complete
        // within [notify_classnotcompleted_days] days
        $enddate = $timenow + $timedelta;

        $select = "SELECT ccl.*,
                          ccm.moodlecourseid as mcourseid,
                          cce.id as studentid, cce.classid, cce.userid, cce.enrolmenttime,
                          cce.completetime, cce.completestatusid, cce.grade, cce.credits, cce.locked,
                          u.id as muserid ";
        $from   = "FROM {$CFG->prefix}crlm_class ccl ";
        $join   = "INNER JOIN {$CFG->prefix}crlm_class_enrolment cce ON cce.classid = ccl.id
                    LEFT JOIN {$CFG->prefix}crlm_class_moodle ccm ON ccm.classid = ccl.id
                   INNER JOIN {$CFG->prefix}crlm_user cu ON cu.id = cce.userid
                    LEFT JOIN {$CFG->prefix}user u ON u.idnumber = cu.idnumber
                    LEFT JOIN {$CFG->prefix}crlm_notification_log cnl ON cnl.userid = cu.id AND cnl.instance = ccl.id AND cnl.event = 'class_notcompleted' ";
        $where  = "WHERE cce.completestatusid = ".STUSTATUS_NOTCOMPLETE."
                     AND cnl.id IS NULL
                     AND ccl.enddate <= $enddate ";
        $order  = "ORDER BY ccl.id ASC ";
        $sql    = $select . $from . $join . $where . $order;

        $classid = 0;
        $classtempl = new cmclass(); // used just for its properties.
        $studenttempl = new student(); // used just for its properties.

        $rs = get_recordset_sql($sql);
        if ($rs) {
            while ($rec = rs_fetch_next_record($rs)) {
                if ($classid != $rec->id) {
                /// Load a new class
                    $classid = $rec->id;
                    $classdata = array();
                    foreach ($classtempl->properties as $prop => $type) {
                        $classdata[$prop] = $rec->$prop;
                    }
                    $cmclass = new cmclass($classdata);


                    $elements = $cmclass->course->get_completion_elements();

                /// Is there a Moodle class?
                    $moodlecourseid = (empty($rec->mcourseid)) ? false : $rec->mcourseid;
                }

                /// If the class doesn't have an end date, skip it.
                if (empty($cmclass->enddate)) {
                    continue;
                }

                /// Load the student...
                $studentdata = array();
                foreach ($studenttempl->properties as $prop => $type) {
                    $studentdata[$prop] = $rec->$prop;
                }
                $student = new student($studentdata, $cmclass, $elements);
                /// Add the moodlecourseid to the student record so we can use it in the event handler.
                $student->moodlecourseid = $moodlecourseid;

                $moodleuserid = (empty($rec->muserid)) ? false : $rec->muserid;

                mtrace("Triggering class_notcompleted event.\n");
                events_trigger('class_notcompleted', $student);
            }
            rs_close($rs);
        }
        return true;
    }

    /**
     * returns a class object given the class idnumber
     *
     * @global object $CURMAN
     * @param string $idnumber class idnumber
     * @return object cmclass corresponding to the idnumber or null
     */
    public static function get_by_idnumber($idnumber) {
        global $CURMAN;

        $retval = $CURMAN->db->get_record(CLSTABLE, 'idnumber', $idnumber);

        if(!empty($retval)) {
            $retval = new cmclass($retval->id);
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
            if (is_array($curcourse)) {
                foreach ($curcourse as $rowid => $obj) {
                    $curcourselist[$obj->curriculumid][$obj->courseid] = $obj->id;
                }
            }
        }
        return $curcourselist;
    }

    /**
     * Creates the javascript used to update the track selection box
     * so that only the tracks that belong to the curricula of the
     * selected course will show up.
     *
     * NOTE - This is not working as it should be right now
     */
    function add_edit_form_js_tracks($list) {
        $tracks = array();
        $case = '';
        $output = 'var trklist = document.getElementById("track");
                   var courselist = document.getElementById("courseid");
                   var selected = courselist.selectedIndex;

                   /* Clear list */
                   trklist.length = 0;'."\n";

        foreach ($list as $curid => $courselist) {

            $tracks = track_get_list_from_curr($curid);

//print_object($curlist);
            if (is_array($tracks)) {

                foreach ($courselist as $courseid => $temp) {
                    $case .= 'case "'.$courseid.'":'."\n";
                }

                foreach ($tracks as $trackid => $track) {
                    $case .= "    var y = document.createElement('option');"."\n".
                                  "y.text= '".$track->name."';"."\n".
                                  "y.value='".$track->id."';"."\n".
                                  "trklist.add(y,null);"."\n";
                }

                $case .= '        break;'."\n";
            }

        }
        //courselist.options[selected].value
        $output .= 'switch (courselist.options[selected].value) {'."\n".
                   $case."\n".
                   '}';

        return '<script language=javascript >
                    function updateTrackList() {'.
                        $output.
                    '}
                </script>';
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
        if (empty($this->courseid) and
            (empty($params) or
            !array_key_exists('courseid', $params))) {
            return false;
        }

        if (!empty($params)) {
            foreach($params as $key => $data) {
                $this->$key = $data;
            }
        }

        $defaults = $this->get_default();
        if (!empty($defaults)) {
            foreach($defaults as $key => $data) {
                if (!isset($this->$key)) {
                    $this->$key = $data;
                }
            }
        }

        // Check for course id one more time
        if (!empty($this->courseid) or 0 !== intval($this->courseid)) {
            $this->startdate = !isset($this->startdate) ? time() : $this->startdate;
            $this->enddate = !isset($this->enddate) ? time() : $this->enddate;

            if (!$this->data_insert_record()) {
                return false; // Something went wrong
            }
        }

        return $this->id;
    }

    /**
     * update records with fields that aren't handled by the parent class
     * tracks because they are multi select and require relations be made in a separate table
     * moodlecourseid because they require relations be made in a separate table
     * @global object $CURMAN
     * @param bool $createnew
     */
    public function data_update_record($createnew = false) {
        global $CURMAN;

        $status = parent::data_update_record($createnew);

        if (isset($this->track) && is_array($this->track)) {
            $param['classid'] = $this->id;
            $param['courseid'] = $this->courseid;

            foreach ($this->track as $t) {
                $param['trackid'] = $t;
                $trackassignobj = new trackassignmentclass($param);
                $trackassignobj->add();
            }
        }

        if(!empty($this->moodlecourseid)) {
            moodle_attach_class($this->id, $this->moodlecourseid);
        }

        return $status;
    }

    /**
     * Data function to insert a database record with the object contents.
     *
     * @param $record object If present, uses the contents of it rather than the object.
     * @return boolean Status of the operation.
     * @uses  $CURMAN global.
     */
    function data_insert_record($record = false) {
        $status = parent::data_insert_record($record);

        if (isset($this->track)) {
            $param['classid'] = $this->id;
            $param['courseid'] = $this->courseid;

            foreach ($this->track as $t) {
                $param['trackid'] = $t;
                $trackassignobj = new trackassignmentclass($param);
                $trackassignobj->add();
            }
        }

        if(!empty($this->moodlecourseid)) {
            moodle_attach_class($this->id, $this->moodlecourseid);
        }
        return $status;
    }

    /**
     * loads the data into this object specifically moodlecourseid since it is in a group form element
     * @param array $data
     */
    public function data_load_array($data) {
        parent::data_load_array($data);

        if(!empty($data['moodleCourses']['moodlecourseid'])){
            $this->moodlecourseid = $data['moodleCourses']['moodlecourseid'];
        }
    }

    public function count_students_by_section($clsid = 0){
        global $CURMAN;

        if(!$clsid) {
            if(empty($this->id)) {
                return array();
            }

            $clsid = $this->id;
        }

        $select     = 'SELECT stu.completestatusid, COUNT(stu.id) as c ';
        $from       = 'FROM ' . $CURMAN->db->prefix_table(STUTABLE) . ' stu ';
        $where      = 'WHERE stu.classid = ' . $clsid . ' ';
        $groupby    = 'GROUP BY stu.completestatusid ';

        $sql = $select . $from . $where . $groupby;

        return $CURMAN->db->get_records_sql($sql);
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
        
        $context = cm_context_set::for_user_with_capability('cluster', 'block/curr_admin:class:enrol_cluster_user', $USER->id); 

        $allowed_clusters = array();

        if (cmclasspage::_has_capability('block/curr_admin:class:enrol_cluster_user', $clsid)) {
            global $CURMAN;
            require_once CURMAN_DIRLOCATION.'/lib/usercluster.class.php';
            $cmuserid = cm_get_crlmuserid($USER->id);
            $userclusters = $CURMAN->db->get_records(CLSTUSERTABLE, 'userid', $cmuserid);
            foreach ($userclusters as $usercluster) {
                $allowed_clusters[] = $usercluster->clusterid;
            }
        }
            
        //we first need to go through tracks to get to clusters
        $track_listing = new trackassignmentclass(array('classid' => $clsid));
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
    function duplicate($options=array()) {
        global $CURMAN;
        $objs = array('errors' => array());
        if (isset($options['targetcluster'])) {
            $cluster = $options['targetcluster'];
            if (!is_object($cluster) || !is_a($cluster, 'cluster')) {
                $options['targetcluster'] = $cluster = new cluster($cluster);
            }
        }

        // clone main class object
        $clone = new cmclass($this);
        unset($clone->id);

        if (isset($options['targetcourse'])) {
            $clone->courseid = $options['targetcourse'];
        }
        if (isset($cluster)) {
            // if cluster specified, append cluster's name to class
            $clone->idnumber = $clone->idnumber . ' - ' . $cluster->name;
        }
        $clone = new cmclass(addslashes_recursive($clone));
        $clone->autocreate = false; // avoid warnings
        if (!$clone->add()) {
            $objs['errors'][] = get_string('failclustcpycls', 'block_curr_admin', $this);
            return $objs;
        }
        $objs['classes'] = array($this->id => $clone->id);

        $cmc = $CURMAN->db->get_record(CLSMDLTABLE, 'classid', $this->id);
        if ($cmc) {
            if ($cmc->autocreated == -1) {
                $cmc->autocreated = $CURMAN->config->autocreated_unknown_is_yes;
            }
            if (empty($options['moodlecourses']) || $options['moodlecourses'] == 'copyalways'
                || ($options['moodlecourses'] == 'copyautocreated' && $cmc->autocreated)) {
                // create a new Moodle course based on the current class's Moodle course
                $moodlecourseid   = content_rollover($cmc->moodlecourseid, $clone->startdate);
                // Rename the fullname, shortname and idnumber of the restored course
                $restore->id = $moodlecourseid;
                $restore->fullname = addslashes($clone->course->name . '_' . $clone->idnumber);
                $restore->shortname = addslashes($clone->idnumber);
                $CURMAN->db->update_record('course', $restore);
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

        // FIXME: copy tags

        return $objs;
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
 * @param   cm_context_set  $contexts    Contexts to provide permissions filtering, of null if none
 * @param   int             $clusterid   Id of a cluster that the class must be assigned to via a track
 * @return  object array                 Returned records
 */
function cmclass_get_listing($sort = 'crsname', $dir = 'ASC', $startrec = 0,
                             $perpage = 0, $namesearch = '', $alpha = '', $id = 0, $onlyopen=false,
                             $contexts=null, $clusterid = 0, $extrafilters = array()) {
    global $CURMAN, $USER;

    $LIKE = $CURMAN->db->sql_compare();

    $select = 'SELECT cls.*, cls.starttimehour as starttimehour, cls.starttimeminute as starttimeminute, ' .
              'cls.endtimehour as endtimehour, cls.endtimeminute as endtimeminute, crs.name as crsname, env.name as envname, ' .
              'env.description as envdescription, clsmoodle.moodlecourseid as moodlecourseid ';
    $tables = 'FROM '.$CURMAN->db->prefix_table(CLSTABLE).' cls ';
    $join   = 'JOIN ' . $CURMAN->db->prefix_table(CRSTABLE) . ' crs ' .
              'ON crs.id = cls.courseid ' .
              'LEFT JOIN ' . $CURMAN->db->prefix_table(ENVTABLE) . ' env ' .
              'ON env.id = cls.environmentid ' .
              'LEFT JOIN ' . $CURMAN->db->prefix_table(CLSMOODLETABLE) . ' clsmoodle ' .
              'ON clsmoodle.classid = cls.id ';

    //class associated to a particular cluster via a track
    if(!empty($clusterid)) {
        $join .= "JOIN {$CURMAN->db->prefix_table(CLSTRACKCLS)} clstrk
                  ON clstrk.classid = cls.id
                  JOIN {$CURMAN->db->prefix_table(CLSTTRKTABLE)} clsttrk
                  ON clsttrk.trackid = clstrk.trackid
                  AND clsttrk.clusterid = {$clusterid} ";
    }
    
    //assert that classes returned were requested by the current user using the course / class
    //request block and approved
    if (!empty($extrafilters['show_my_approved_classes'])) {
        $join .= "JOIN {$CURMAN->db->prefix_table('block_course_request')} request
                  ON cls.id = request.classid
                  AND request.userid = {$USER->id} ";
    }

    $where = array();

    if (!empty($namesearch)) {
        $namesearch = trim($namesearch);
        $where[] = "((crs.name $LIKE '%$namesearch%') OR (cls.idnumber $LIKE '%$namesearch%')) ";
    }

    if ($alpha) {
        $where[] = "(crs.name $LIKE '$alpha%')";
    }

    if ($id) {
        $where[] = "(crs.id = $id)";
    }

    if ($onlyopen) {
        $curtime = time();
        $where[] = "(cls.enddate > $curtime OR NOT cls.enddate)";
    }

    if ($contexts !== null) {
        $where[] = $contexts->sql_filter_for_context_level('cls.id', 'class');
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

    if (!empty($perpage)) {
        if ($CURMAN->db->_dbconnection->databaseType == 'postgres7') {
            $limit = 'LIMIT ' . $perpage . ' OFFSET ' . $startrec;
        } else {
            $limit = 'LIMIT '.$startrec.', '.$perpage;
        }
    } else {
        $limit = '';
    }

    $sql = $select.$tables.$join.$where.$sort.$limit;

    return $CURMAN->db->get_records_sql($sql);
}

/**
 * Calculates the number of records in a listing as created by cmclass_get_listing
 *
 * @param   string          $namesearch  Search string for course name
 * @param   string          $alpha       Start initial of course name filter
 * @param   int             $id          Corresponding courseid, or zero for any
 * @param   boolean         $onlyopen    If true, only consider classes whose end date has not been passed
 * @param   cm_context_set  $contexts    Contexts to provide permissions filtering, of null if none
 * @param   int             $clusterid   Id of a cluster that the class must be assigned to via a track
 * @return  int                          The number of records
 */
function cmclass_count_records($namesearch = '', $alpha = '', $id = 0, $onlyopen = false, $contexts = null, $clusterid = 0) {
    global $CURMAN;

    $select = 'SELECT COUNT(cls.id) ';
    $tables = 'FROM '.$CURMAN->db->prefix_table(CLSTABLE).' cls ';
    $join   = 'LEFT JOIN ' . $CURMAN->db->prefix_table(CRSTABLE) . ' crs ' .
              'ON crs.id = cls.courseid ';

    //class associated to a particular cluster via a track
    if(!empty($clusterid)) {
        $join .= "JOIN {$CURMAN->db->prefix_table(CLSTRACKCLS)} clstrk
                  ON clstrk.classid = cls.id
                  JOIN {$CURMAN->db->prefix_table(CLSTTRKTABLE)} clsttrk
                  ON clsttrk.trackid = clstrk.trackid
                  AND clsttrk.clusterid = {$clusterid} ";
    }

    $where  = array();

    $LIKE = $CURMAN->db->sql_compare();

    if (!empty($namesearch)) {
        $where[] = "((crs.name $LIKE '%$namesearch%') OR (cls.idnumber $LIKE '%$namesearch%'))";
    }

    if ($alpha) {
        $where[] = "(crs.name $LIKE '$alpha%')";
    }

    if ($id) {
        $where[] = "(crs.id = $id)";
    }

    if ($onlyopen) {
        $curtime = time();
        $where[] = "(cls.enddate > $curtime OR NOT cls.enddate)";
    }

    if ($contexts !== null) {
        $where[] = $contexts->sql_filter_for_context_level('cls.id', 'class');
    }

    if (!empty($where)) {
        $where = 'WHERE '.implode(' AND ',$where).' ';
    } else {
        $where = '';
    }

    $sql = $select . $tables . $join . $where;
    return $CURMAN->db->count_records_sql($sql);
}

function cmclass_get_record_by_courseid($courseid) {
    global $CURMAN;

    $records = $CURMAN->db->get_records(CLSTABLE, 'courseid', $courseid);
    return $records;
}
?>
