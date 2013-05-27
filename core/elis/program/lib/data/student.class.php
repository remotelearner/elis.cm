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
require_once elis::lib('data/data_object.class.php');
require_once elis::lib('table.class.php');
require_once elispm::lib('lib.php');
require_once elispm::lib('deprecatedlib.php');
require_once elispm::lib('data/classmoodlecourse.class.php');
require_once elispm::lib('data/course.class.php');
require_once elispm::lib('data/curriculumcourse.class.php');
require_once elispm::lib('data/instructor.class.php');
require_once elispm::lib('data/pmclass.class.php');
require_once elispm::lib('data/user.class.php');
require_once elispm::lib('data/waitlist.class.php');

define ('STUTABLE', 'crlm_class_enrolment');
define ('GRDTABLE', 'crlm_class_graded');

define ('STUSTATUS_NOTCOMPLETE', 0);
define ('STUSTATUS_FAILED',      1);
define ('STUSTATUS_PASSED',      2);

class student extends elis_data_object {
    const TABLE = STUTABLE;
    const LANG_FILE = 'elis_program';

    const STUSTATUS_NOTCOMPLETE = 0;
    const STUSTATUS_FAILED = 1;
    const STUSTATUS_PASSED = 2;

    var $verbose_name = 'student';

    static $associations = array(
        'users'   => array('class' => 'user',
                           'idfield' => 'userid'),
        'pmclass' => array('class' => 'pmclass',
                           'idfield' => 'classid')
    );

    static $validation_rules = array(
        array('validation_helper', 'not_empty_userid'),
        array('validation_helper', 'not_empty_classid'),
        'validate_associated_user_exists',
        'validate_associated_class_exists',
        array('validation_helper', 'is_unique_userid_classid'),
        'prerequisites' => 'validate_class_prerequisites',
        'enrolment_limit' => 'validate_class_enrolment_limit',
    );

    /**
     * Whether or not to enrol the student in the associated Moodle course (if
     * any).
     */
    public $no_moodle_enrol = false;

    /**
     * Validates that the associated user record exists
     */
    public function validate_associated_user_exists() {
        validate_associated_record_exists($this, 'users');
    }

    /**
     * Validates that the associated pmclass record exists
     */
    public function validate_associated_class_exists() {
        validate_associated_record_exists($this, 'pmclass');
    }

    public function validate_class_prerequisites() {
        // check prerequisites

        $pmclass = $this->pmclass;
        // get all the curricula that the user is in
        $curricula = $this->users->get_programassignments();
        foreach ($curricula as $curriculum) {
            $curcrs = new curriculumcourse();
            $curcrs->courseid = $pmclass->courseid;
            $curcrs->curriculumid = $curriculum->curriculumid;
            if (!$curcrs->prerequisites_satisfied($this->userid)) {
                // prerequisites not satisfied
                throw new unsatisfied_prerequisites_exception($this);

                /*
                $status = new Object();
                $status->message = get_string('unsatisfiedprereqs', self::LANG_FILE);
                $status->code = 'unsatisfiedprereqs';
                //error_log('student.class::add() - student missing prereqs!');
                return $status;
                */
            } else {
                return true;
            }
        }
    }

    /**
     * Check that the class enrolment limit is not reached.
     */
    public function validate_class_enrolment_limit() {
        // check class enrolment limit
        if (isset($this->id)) {
            // editing an existing enrolment -- don't need to check enrolment
            // limit
            return true;
        }

        $limit = $this->pmclass->maxstudents;
        if (!empty($limit) && $limit <= static::count_enroled($this->classid)) {
            // class is full
            throw new pmclass_enrolment_limit_validation_exception($this->pmclass);
        }
    }

/*
    var $id;                // INT - The data id if in the database.
    var $classid;           // INT - The class ID.
    var $cmclass;           // OBJECT - The class object
    var $userid;            // INT - The user ID.
    var $user;              // OBJECT - The user object.
    var $enrolmenttime;     // INT - The time assigned.
    var $completetime;      // INT - The time completed.
    var $completestatusid;  // INT - Status code for completion.
    var $grade;             // INT - Student grade.
    var $credits;           // INT - Credits awarded.
    var $locked;            // INT - Grade locked.

    var $_dbloaded;    // BOOLEAN - True if loaded from database.
*/
    static $completestatusid_values = array(
        STUSTATUS_NOTCOMPLETE => 'n_completed',
        STUSTATUS_FAILED      => 'failed',
        STUSTATUS_PASSED      => 'passed'
    );

    private $form_url = null;  //moodle_url object

    protected $_dbfield_classid;
    protected $_dbfield_userid;
    protected $_dbfield_enrolmenttime;
    protected $_dbfield_completetime;
    protected $_dbfield_endtime;
    protected $_dbfield_completestatusid;
    protected $_dbfield_grade;
    protected $_dbfield_credits;
    protected $_dbfield_locked;

    static $delete_is_complex = true;

    function is_available() { // TBD: Move to parent class or library with class as param?
        return $this->_db->get_manager()->table_exists(self::TABLE);
    }

    /**
     * Perform all actions to mark this student record complete.
     *
     * @param   mixed  $status   The completion status (ignored if FALSE)
     * @param   mixed  $time     The completion time (ignored if FALSE)
     * @param   mixed  $grade    Grade in the class (ignored if FALSE)
     * @param   mixed  $credits  Number of credits awarded (ignored if FALSE)
     * @param   mixed  $locked   If TRUE, the assignment record becomes locked
     * @uses    $CFG
     * @return  boolean          TRUE is successful, otherwise FALSE
     */
    function complete($status = false, $time = false, $grade = false, $credits = false, $locked = false) {
        global $CFG;
        require_once elispm::lib('notifications.php');

        /// Set any data passed in...
        if ($status !== false) {
            $this->completestatusid = $status;
        }

        if ($time !== false) {
            $this->completetime = $time;
        }

        if ($grade !== false) {
            $this->grade = $grade;
        }

        if ($credits !== false) {
            $this->credits = $credits;
        }

        if ($locked !== false) {
            $this->locked = $locked;
        }

        /// Check that the data makes sense...
        if (($this->completestatusid == STUSTATUS_NOTCOMPLETE) || !isset(student::$completestatusid_values[$this->completestatusid])) {
            $this->completestatusid = STUSTATUS_PASSED;
        }

        if (($this->completetime <= 0) || !is_numeric($this->completetime)) {
            $this->completetime = time();
        }

        try {
            $this->save();
        } catch (Exception $e) {
            // We can't continue if we couldn't save the enrolment record.
            return false;
        }

        /// Does the user receive a notification?
        $sendtouser       = elis::$config->elis_program->notify_classcompleted_user;
        $sendtorole       = elis::$config->elis_program->notify_classcompleted_role;
        $sendtosupervisor = elis::$config->elis_program->notify_classcompleted_supervisor;

        /// Make sure this is a valid user.
        $enroluser = new user($this->userid);
        // Due to lazy loading, we need to pre-load this object
        $enroluser->load();
        if (empty($enroluser->id)) {
            print_error('nouser', self::LANG_FILE);
            return true;
        }

        $message = new notification();

        /// Set up the text of the message
        $text = empty(elis::$config->elis_program->notify_classcompleted_message) ?
                get_string('notifyclasscompletedmessagedef', self::LANG_FILE) :
                elis::$config->elis_program->notify_classcompleted_message;
        $search = array('%%userenrolname%%', '%%classname%%');

        $pmuser = $this->_db->get_record(user::TABLE, array('id' => $this->userid));
        $user = new user($pmuser);
        if (($clsmdl = $this->_db->get_record(classmoodlecourse::TABLE,
                array('classid' => $this->classid))) &&
            ($course = $this->_db->get_record('course', array('id' => $clsmdl->moodlecourseid)))) {
            /// If its a Moodle class...
            $replace = array(fullname($pmuser), $course->fullname);
            if (!($context = get_context_instance(CONTEXT_COURSE, $course->id))) {
                print_error('invalidcontext');
                return true;
            }
        } else {
            $pmclass = new pmclass($this->classid);
            $replace = array(fullname($pmuser), $pmclass->course->name);
            if (!($context = get_system_context())) {
                print_error('invalidcontext');
                return true;
            }
        }

        $text = str_replace($search, $replace, $text);

        if ($sendtouser) {
            $message->send_notification($text, $user);
        }

        $users = array();

        if ($sendtorole) {
            /// Get all users with the notify_classcompleted capability.
            if ($roleusers = get_users_by_capability($context, 'elis/program:notify_classcomplete')) {
                $users = $users + $roleusers;
            }
        }

        if ($sendtosupervisor) {
            /// Get parent-context users.
            if ($supervisors = pm_get_users_by_capability('user', $this->userid, 'elis/program:notify_classcomplete')) {
                $users = $users + $supervisors;
            }
        }

        // Send notifications to any users who need to receive them.
        foreach ($users as $touser) {
            $message->send_notification($text, $touser, $user);
        }
    }

/////////////////////////////////////////////////////////////////////
//                                                                 //
//  STANDARD FUNCTIONS:                                            //
//                                                                 //
/////////////////////////////////////////////////////////////////////

    function save() {
        global $DB, $USER;

        try {
            validation_helper::is_unique_userid_classid($this);
        } catch (Exception $e) {
            // already enrolled -- pretend we succeeded
            //error_log('student.class::add() - student already enrolled!');
            return true;
        }

        //set end time based on class duration
        if (empty($this->id) && empty($this->endtime) && !empty($this->classid)) {
            $studentclass = $this->pmclass;
            if (!empty($studentclass->duration)) {
                $this->endtime = $this->enrolmenttime + $studentclass->duration;
            } else {
                // no class duration -> no end time
                $this->endtime = 0;
            }
        }
        parent::save();

        /// Enrol them into the Moodle class, if not already enrolled.
        if (empty($this->no_moodle_enrol) && ($moodlecourseid = moodle_get_course($this->classid))) {
            if ($mcourse = $this->_db->get_record('course', array('id' => $moodlecourseid))) {
                $plugin = enrol_get_plugin('elis');
                $enrol = $plugin->get_or_create_instance($mcourse);

                $user = $this->users;
                if (!($muser = $user->get_moodleuser())) {
                    if (!$muserid = $user->synchronize_moodle_user(true, true)) {
                        throw new Exception(get_string('errorsynchronizeuser', self::LANG_FILE));
                    }
                } else {
                    $muserid = $muser->id;
                }

                $context = get_context_instance(CONTEXT_COURSE, $moodlecourseid);
                if (!is_enrolled($context, $muserid)) {
                    $plugin->enrol_user($enrol, $muserid, $enrol->roleid,
                                        $this->enrolmenttime,
                                        $this->endtime ? $this->endtime : 0);
                }
            }
        } else {
            $sturole = get_config('pmplugins_enrolment_role_sync', 'student_role');
            // ELIS-3397: must still trigger events for notifications
            $sturole = get_config('pmplugins_enrolment_role_sync', 'student_role');
            $ra = new stdClass();
            $ra->roleid       = !empty($sturole)
                                ? $sturole
                                : $DB->get_field('role', 'id', array('shortname' => 'student'));
            $ra->contextid    = context_elis_class::instance($this->classid)->id;
            $ra->userid       = cm_get_moodleuserid($this->userid);
            $ra->component    = 'enrol_elis';
            $ra->timemodified = time();
            $ra->modifierid   = empty($USER->id) ? 0 : $USER->id;
            events_trigger('role_assigned', $ra);
        }

        // Fire the course complete event
        events_trigger('crlm_class_completed', $this);

        return;
    }

    /**
     * Perform all necessary tasks to remove a student enrolment from the system.
     */
    function delete() {
        /// Remove any grade records for this enrolment.
        $result = student_grade::delete_for_user_and_class($this->userid, $this->classid);

        /// Unenrol them from the Moodle class.
        if ($moodlecourseid = moodle_get_course($this->classid)) {
            if (($mcourse = $this->_db->get_record('course', array('id' => $moodlecourseid)))
                && ($muser = $this->users->get_moodleuser())) {

                $sql = 'SELECT enrol.*
                          FROM {user_enrolments} enrolments
                          JOIN {enrol} enrol ON enrol.id = enrolments.enrolid
                         WHERE enrol.courseid = ?
                           AND enrolments.userid = ?';
                $enrolments = $this->_db->get_recordset_sql($sql, array($moodlecourseid, $muser->id));
                foreach ($enrolments as $enrolment) {
                    $plugin = enrol_get_plugin($enrolment->enrol);
                    $plugin->unenrol_user($enrolment, $muser->id);
                }
                unset($enrolments);
            }
        }

        parent::delete();

        if ($this->completestatusid == STUSTATUS_NOTCOMPLETE) {
            $pmclass = $this->pmclass;
            if (empty($pmclass->maxstudents) || $pmclass->maxstudents > static::count_enroled($pmclass->id)) {
                $wlst = waitlist::get_next($this->classid);

                if (!empty($wlst)) {
                    $wlst->enrol();
                }
            }
        }

        return $result;
    }

    /**
     * Retrieves a user object given the users idnumber
     * @param <type> $idnumber
     * @uses $DB
     * @return <type>
     */
    public static function get_userclass($userid, $classid) {
        global $DB;
        $retval = null;

        $student = $DB->get_record(student::TABLE, array('userid' => $userid, 'classid' => $classid));
        if (!empty($student)) {
            $retval = new student($student->id);
        }
        return $retval;
    }

    // Note: we rely on the caller to cascade these deletes to the student_grade
    // table.
    public static function delete_for_class($id) {
        global $DB;
        return $DB->delete_records(student::TABLE, array('classid' => $id));
    }

    public static function delete_for_user($id) {
        global $DB;
        return $DB->delete_records(student::TABLE, array('userid' => $id));
    }

    /**
     * Perform all necessary tasks to update a student enrolment.
     *
     */
    function update() {
        parent::save(); // no return val
        events_trigger('crlm_class_completed', $this);
        return true;    // TBD
    }

/////////////////////////////////////////////////////////////////////
//                                                                 //
//  FORM FUNCTIONS:                                                //
//                                                                 //
/////////////////////////////////////////////////////////////////////

    protected function get_base_url($withquerystring = true) {
        if ($withquerystring) {
            return get_pm_url();
        } else {
            return get_pm_url()->out_omit_querystring();
        }
    }

    function edit_student_html($stuid, $type = '', $sort = 'name', $dir = 'ASC', $page = 0,
                               $perpage = 30, $namesearch = '', $alpha = '') {
        $this->id = $stuid;
        //error_log("student.class.php::edit_student_html({$stuid}, {$type}, ... ); this->classid = {$this->classid}");
        return $this->edit_form_html($this->id /* ->classid */, $type, $sort, $dir, $page,
                                     $perpage, $namesearch, $alpha);
    }

    function edit_classid_html($classid, $type = '', $sort = 'name', $dir = 'ASC', $page = 0,
                               $perpage = 30, $namesearch = '', $alpha = '') {

        //error_log("student.class.php::edit_classid_html({$classid}, {$type}, ... ) - setting this->classid ({$this->classid}) = classid ({$classid})");
        $this->classid = $classid; // TBD ???
        return $this->edit_form_html($classid, $type, $sort, $dir, $page,
                                     $perpage, $namesearch, $alpha);
    }

   /**
    * Get a listing of grade elements for a particular course
    *
    * @param int $courseid The course id
    * @param int $classid The class id
    * @param int $userid The user id
    * @param string $sort The sorting field name
    * @param string $dir The direction of sorting
    * @return recordset The grade elements
    */
    public static function retrieve_grade_elements($courseid = 0, $classid, $userid, $sort, $dir) {
        global $DB;

        if (empty($DB)) {
            return NULL;
        }

        $select  = 'SELECT cc.id, sg.id AS studentgradeid, cc.idnumber, sg.grade, sg.locked, sg.timegraded ';
        $tables  = 'FROM {' . coursecompletion::TABLE . '} cc ';
        $join    = 'LEFT JOIN {' . student_grade::TABLE . '} sg '.
                    'ON sg.classid = ? AND sg.userid = ? AND cc.id = sg.completionid ';
        $where   = 'WHERE cc.courseid = ? ';
        $sort    = "ORDER BY {$sort} {$dir} ";
        $params  = array($classid, $userid, $courseid);

        $sql = $select.$tables.$join.$where.$sort;

        return $DB->get_recordset_sql($sql, $params);
    }

    /**
     * Return the HTML to edit a specific student.
     * This could be extended to allow for application specific editing,
     * for example a Moodle interface to its formslib.
     *
     * @uses $CFG
     * @uses $OUTPUT
     * @uses $PAGE
     * @return string The form HTML, without the form.
     */
    function edit_form_html($classid, $type = '', $sort = 'idnumber', $dir = 'ASC', $page = 0,
                            $perpage = 30, $namesearch = '', $alpha = '') {
                            // ^^^ set non-zero default for $perpage
        global $CFG, $OUTPUT, $PAGE, $SESSION;
        $output = '';
        ob_start();

        $newarr = array();
        if (empty($this->id)) {
            $columns = array(
                'enrol'            => array('header' => get_string('enrol', self::LANG_FILE),
                                            'display_function' => 'htmltab_display_function',
                                            'sortable' => false),
                'idnumber'         => array('header' => get_string('student_idnumber', self::LANG_FILE),
                                            'display_function' => 'htmltab_display_function'),
                'name'             => array('header' => get_string('student_name_1', self::LANG_FILE),
                                            'display_function' => 'htmltab_display_function'),
                'enrolmenttime'    => array('header' => get_string('enrolment_time', self::LANG_FILE),
                                            'display_function' => 'htmltab_display_function',
                                            'sortable' => false),
                'completetime'     => array('header' => get_string('completion_time', self::LANG_FILE),
                                            'display_function' => 'htmltab_display_function',
                                            'sortable' => false),
                'completestatusid' => array('header' => get_string('student_status', self::LANG_FILE),
                                            'display_function' => 'htmltab_display_function',
                                            'sortable' => false),
                'grade'            => array('header' => get_string('student_grade', self::LANG_FILE),
                                            'display_function' => 'htmltab_display_function',
                                            'sortable' => false),
                'credits'          => array('header' => get_string('student_credits', self::LANG_FILE),
                                            'display_function' => 'htmltab_display_function',
                                            'sortable' => false),
                'locked'           => array('header' => get_string('student_locked', self::LANG_FILE),
                                            'display_function' => 'htmltab_display_function',
                                            'sortable' => false),
            );
        } else {
            $columns = array(
                'idnumber'         => array('header' => get_string('student_idnumber', self::LANG_FILE),
                                            'display_function' => 'htmltab_display_function',
                                            'sortable' => false),
                'name'             => array('header' => get_string('student_name_1', self::LANG_FILE),
                                            'display_function' => 'htmltab_display_function',
                                            'sortable' => false),
                'enrolmenttime'    => array('header' => get_string('enrolment_time', self::LANG_FILE),
                                            'display_function' => 'htmltab_display_function',
                                            'sortable' => false),
                'completetime'     => array('header' => get_string('completion_time', self::LANG_FILE),
                                            'display_function' => 'htmltab_display_function',
                                            'sortable' => false),
                'completestatusid' => array('header' => get_string('student_status', self::LANG_FILE),
                                            'display_function' => 'htmltab_display_function',
                                            'sortable' => false),
                'grade'            => array('header' => get_string('student_grade', self::LANG_FILE),
                                            'display_function' => 'htmltab_display_function',
                                            'sortable' => false),
                'credits'          => array('header' => get_string('student_credits', self::LANG_FILE),
                                            'display_function' => 'htmltab_display_function',
                                            'sortable' => false),
                'locked'           => array('header' => get_string('student_locked', self::LANG_FILE),
                                            'display_function' => 'htmltab_display_function',
                                            'sortable' => false),
            );
        }

        // ELIS-6468
        if ($dir !== 'DESC') {
            $dir = 'ASC';
        }
        if (isset($columns[$sort])) {
            $columns[$sort]['sortable'] = $dir;
        } else {
            $sort = 'name';
            $columns[$sort]['sortable'] = $dir;
        }

        $users = array();
        if (empty($this->id)) {
            $users = $this->get_users_avail($sort, $dir, $page * $perpage, $perpage, $namesearch, $alpha);
            $usercount = $this->count_users_avail($namesearch, $alpha); // TBD

            pmalphabox(new moodle_url('/elis/program/index.php', // TBD
                               array('s' => 'stu', 'section' => 'curr',
                                     'action' => 'add', 'id' => $classid,
                                     'search' => $namesearch, 'sort' => $sort,
                                     'dir' => $dir, 'perpage' => $perpage)),
                       'alpha', get_string('tag_name', self::LANG_FILE) .':');

            $pagingbar = new paging_bar($usercount, $page, $perpage,
                    "index.php?s=stu&amp;section=curr&amp;id=$classid&amp;class=$classid&amp;&amp;action=add&amp;" .
                    "sort=$sort&amp;dir=$dir&amp;perpage=$perpage&amp;alpha=$alpha&amp;stype=$type" .
                    "&amp;search=" . urlencode($namesearch)); // TBD: .'&amp;'
            echo $OUTPUT->render($pagingbar);
            flush();

            pmsearchbox(null, 'search', 'get', get_string('show_all_users', self::LANG_FILE)); // TBD: moved from below

        } else {
            $user       = $this->_db->get_record(user::TABLE, array('id' => $this->userid));
            $user->name = fullname($user);
            $users[]    = $user;
            $usercount  = 0;
        }

        $has_users = ((is_array($users) && !empty($users)) || ($users instanceof Iterator && $users->valid() === true)) ? true : false;

        if (empty($this->id) && $has_users === false) {
            pmshowmatches($alpha, $namesearch);
            $table = NULL;
        } else {

            $stuobj = new student();

            foreach ($users as $user) {
                $locked = $this->locked;
                $credits = $this->credits;
                $grade = $this->grade;
                $status = $this->completestatusid;
                $enrolmenttime =  $this->enrolmenttime;
                $completetime = $this->completetime;

                $selection = json_decode(retrieve_session_selection($user->id, 'add'));

                if ($selection) {
                    $locked = $selection->locked;
                    $credits = $selection->credits;
                    $grade = $selection->grade;
                    $status = $selection->status;
                    $enrolmenttime = pm_timestamp(0, 0, 0, $selection->enrolment_date->month, $selection->enrolment_date->day, $selection->enrolment_date->year);
                    $completetime = pm_timestamp(0, 0, 0, $selection->completion_date->month, $selection->completion_date->day, $selection->completion_date->year);
                }
                $tabobj = new stdClass;
                foreach ($columns as $column => $cdesc) {
                    switch ($column) {
                        case 'enrol':
                            $tabobj->{$column} = '<input type="checkbox" id="checkbox'. $user->id .'"
                            name="users[' . $user->id . '][enrol]" value="1" onClick="select_item(' . $user->id .')" '.($selection?'checked="checked"':''). '/>'.
                                        '<input type="hidden" name="users[' . $user->id . '][idnumber]" '.
                                        'value="' . $user->idnumber . '" />';
                            break;

                        case 'name':
                        case 'idnumber':
                        case 'description';
                            $tabobj->{$column} = isset($user->{$column}) ? $user->{$column} : '';
                            break;

                        case 'enrolmenttime':
                            $tabobj->{$column} = cm_print_date_selector('users[' . $user->id . '][startday]',
                                                               'users[' . $user->id . '][startmonth]',
                                                               'users[' . $user->id . '][startyear]',
                                                               $enrolmenttime, true);

                            break;

                        case 'completetime':
                            $tabobj->{$column} = cm_print_date_selector('users[' . $user->id . '][endday]',
                                                               'users[' . $user->id . '][endmonth]',
                                                               'users[' . $user->id . '][endyear]',
                                                               $completetime, true);
                            break;

                        case 'completestatusid':
                            $choices = array();

                            foreach(student::$completestatusid_values as $key => $csidv) {
                                $choices[$key] = get_string($csidv, self::LANG_FILE); // TBD
                            }
                            $tabobj->{$column} = cm_choose_from_menu($choices,
                                                            'users[' . $user->id . '][completestatusid]',
                                                            $status, '', '', '', true);
                            break;

                        case 'grade':
                            $tabobj->{$column} = '<input type="text" id="grade' .$user->id . '" name="users[' . $user->id . '][grade]" ' .
                                        'value="' . $grade . '" size="5" />';
                            break;

                        case 'credits':
                            $tabobj->{$column} = '<input type="text" id="credits' .$user->id . '" name="users[' . $user->id . '][credits]" ' .
                                        'value="' . $credits . '" size="5" />';
                            break;

                        case 'locked':
                            $tabobj->{$column} = '<input type="checkbox" id="locked' .$user->id . '" name="users[' . $user->id . '][locked]" ' .
                                        'value="1" '.($locked?'checked="checked"':'').'/>';
                            break;

                        default:
                            $tabobj->{$column} = '';
                            break;
                    }
                }
                $newarr[] = $tabobj;
                //$table->data[] = $newarr;
            }
            // TBD: student_table() ???
            $table = new display_table($newarr, $columns, $this->get_base_url(), 'sort', 'dir', array('id' => 'selectiontbl'));
        }
        unset($users);

        print_checkbox_selection($classid, 'stu', 'add');

        if (empty($this->id)) {
            echo '<form method="post" action="index.php?s=stu&amp;section=curr&amp;id=' . $classid . '" >'."\n";
            echo '<input type="hidden" name="action" value="savenew" />'."\n";
            echo '<input type="hidden" name="sesskey" value="'.sesskey().'"/>';
        } else {
            echo '<form method="post" action="index.php?s=stu&amp;section=curr&amp;id=' . $classid . '" >'."\n";
            echo '<input type="hidden" name="action" value="update" />'."\n";
            echo '<input type="hidden" name="association_id" value="' . $this->id . '" />' . "\n";
            echo '<input type="hidden" name="id" value="' . $this->classid . '" />' . "\n";
            echo '<input type="hidden" name="userid" value="' . $this->userid . '" />' . "\n";
            echo '<input type="hidden" name="sesskey" value="'.sesskey().'"/>';
        }

        if (!empty($newarr)) { // TBD: $newarr or $table
            if(empty($this->id)) {
                $PAGE->requires->js('/elis/program/js/classform.js');
                echo '<input type="button" onclick="checkbox_select(true,\'[enrol]\')" value="'.get_string('selectall').'" /> ';
                echo '<input type="button" onclick="checkbox_select(false,\'[enrol]\')" value="'.get_string('deselectall').'" /> ';
            }
            echo $table->get_html();
        }

        if (isset($this->id)) {

            $columns = array(
                    'idnumber'    => array('header' => get_string('grade_element', self::LANG_FILE),
                                          'display_function' => 'htmltab_display_function'),
                    'grade'      => array('header' => get_string('grade', self::LANG_FILE),
                                          'display_function' => 'htmltab_display_function'),
                    'locked'     => array('header' => get_string('student_locked', self::LANG_FILE),
                                          'display_function' => 'htmltab_display_function'),
                    'timegraded' => array('header' => get_string('date_graded', self::LANG_FILE),
                                          'display_function' => 'htmltab_display_function')
            );

            if ($dir !== 'DESC') {
                $dir = 'ASC';
            }
            if (isset($columns[$sort])) {
                $columns[$sort]['sortable'] = $dir;
            } else {
                $sort = 'idnumber';
                $columns[$sort]['sortable'] = $dir;
            }

            $elements = self::retrieve_grade_elements($this->pmclass->course->get_course_id(), $this->classid, $this->userid, $sort, $dir);

            if (!empty($elements) && $elements->valid() === true) {
                //$table->width = "100%"; // TBD

                $newarr = array();
                foreach ($elements as $element) {
                    $tabobj = new stdClass;
                    foreach ($element as $column => $cdesc) {
                        switch ($column) {
                            case 'idnumber':
                                if (isset($element->studentgradeid)) {
                                    $name = 'element['.$element->studentgradeid.']';
                                    $value = $element->id;
                                } else {
                                    $name = 'newelement['.$element->id.']';
                                    $value = $element->id;
                                }
                                $tabobj->{$column} = '<input type="hidden" name="'.$name.'" ' .
                                            'value="' . $value . '" />'.s($element->idnumber);
                                break;

                            case 'timegraded':
                                if (isset($element->studentgradeid)) {
                                    $name = 'timegraded['.$element->studentgradeid.']';
                                    $value = $element->timegraded;
                                } else {
                                    $name = 'newtimegraded['.$element->id.']';
                                    $value = 0;
                                }
                                $tabobj->{$column} = cm_print_date_selector($name.'[startday]',
                                                                   $name.'[startmonth]',
                                                                   $name.'[startyear]',
                                                                   $value, true);
                                break;

                            case 'grade':
                                if (isset($element->studentgradeid)) {
                                    $name = 'grade['.$element->studentgradeid.']';
                                    $value = $element->grade;
                                } else {
                                    $name = 'newgrade['.$element->id.']';
                                    $value = 0;
                                }
                                $tabobj->{$column} = '<input type="text" name="'.$name.'" ' .
                                            'value="' . $value . '" size="5" />';
                                break;

                            case 'locked':
                                if (isset($element->studentgradeid)) {
                                    $name = 'locked['.$element->studentgradeid.']';
                                    $value = $element->locked;
                                } else {
                                    $name = 'newlocked['.$element->id.']';
                                    $value = 0;
                                }
                                $tabobj->{$column} = '<input type="checkbox" name="'.$name.'" ' .
                                            'value="1" '.($value?'checked="checked"':'').'/>';
                                break;

                            default:
                                $tabobj->{$column} = '';
                                break;
                        }
                    }
                    $newarr[] = $tabobj;
                    //$table->data[] = $newarr;
                }
                // TBD: student_table() ???
                $table = new display_table($newarr, $columns, $this->get_base_url());
                if (!empty($newarr)) { // TBD: $table or $newarr?
                    echo '<br />';
                    echo $table->get_html();
                    print_string('grade_update_warning', self::LANG_FILE);
                }
            }
            unset($elements);
        }

        if (empty($this->id)) {
            echo '<br /><input type="submit" value="' . get_string('enrol_selected', self::LANG_FILE) . '">'."\n";
        } else {
            echo '<br /><input type="submit" value="' . get_string('update_enrolment', self::LANG_FILE) . '">'."\n";
        }
        echo '</form>'."\n";

        $output = ob_get_contents();
        ob_end_clean();

        return $output;
    }

    /**
     * Return the HTML to for a view page that also allows editing.
     *
     * @uses $CFG
     * @uses $OUTPUT
     * @uses $PAGE
     * @return string The form HTML, without the form.
     */
    function view_form_html($classid, $type = '', $sort = 'name', $dir = 'ASC', $page = 0,
                            $perpage = 0, $namesearch = '', $alpha = '') {
        global $CFG, $OUTPUT, $PAGE, $SESSION;

        $pageid = optional_param('id', 1, PARAM_INT);
        $pagetype = optional_param('s', '', PARAM_ALPHA);
        $target = optional_param('action', '', PARAM_ALPHA);
        $pagename = $pagetype . $pageid . $target;

        $output = '';
        ob_start();

        $can_unenrol = pmclasspage::can_enrol_into_class($classid);

        if (empty($this->id)) {
            $columns = array(
                'select' => array(
                    'header' => get_string('select'),
                    'sortable' => false,
                    'display_function' => 'htmltab_display_function'
                ),
                'unenrol'          => array('header' => get_string('unenrol', self::LANG_FILE),
                                            'sortable' => false,
                                            'display_function' => 'htmltab_display_function'),
                'idnumber'         => array('header' => get_string('student_idnumber', self::LANG_FILE),
                                            'display_function' => 'htmltab_display_function'),
                'name'             => array('header' => get_string('student_name_1', self::LANG_FILE),
                                            'display_function' => 'htmltab_display_function'),
//                'description'      => 'Description',
                'enrolmenttime'    => array('header' => get_string('enrolment_time', self::LANG_FILE),
                                            'sortable' => false,
                                            'display_function' => 'htmltab_display_function'),
                'completetime'     => array('header' => get_string('completion_time', self::LANG_FILE),
                                            'sortable' => false,
                                            'display_function' => 'htmltab_display_function'),
                'completestatusid' => array('header' => get_string('student_status', self::LANG_FILE),
                                            'sortable' => false,
                                            'display_function' => 'htmltab_display_function'),
                'grade'            => array('header' => get_string('student_grade', self::LANG_FILE),
                                            'sortable' => false,
                                            'display_function' => 'htmltab_display_function'),
                'credits'          => array('header' => get_string('student_credits', self::LANG_FILE),
                                            'sortable' => false,
                                            'display_function' => 'htmltab_display_function'),
                'locked'           => array('header' => get_string('student_locked', self::LANG_FILE),
                                            'sortable' => false,
                                            'display_function' => 'htmltab_display_function'),
                'edited' => array(
                    'header' => get_string('student_edited', self::LANG_FILE),
                    'sortable' => false,
                    'display_function' => 'htmltab_display_function'
                )
            );

            if (!$can_unenrol) {
                unset($columns['unenrol']);
            }
        } else {
            $columns = array(
                'idnumber'         => array('header' => get_string('student_idnumber', self::LANG_FILE),
                                            'display_function' => 'htmltab_display_function'),
                'name'             => array('header' => get_string('student_name_1', self::LANG_FILE),
                                            'display_function' => 'htmltab_display_function'),
//                'description'      => 'Description',
                'enrolmenttime'    => array('header' => get_string('enrolment_time', self::LANG_FILE),
                                            'sortable' => false,
                                            'display_function' => 'htmltab_display_function'),
                'completetime'     => array('header' => get_string('completion_time', self::LANG_FILE),
                                            'sortable' => false,
                                            'display_function' => 'htmltab_display_function'),
                'completestatusid' => array('header' => get_string('student_status', self::LANG_FILE),
                                            'sortable' => false,
                                            'display_function' => 'htmltab_display_function'),
                'grade'            => array('header' => get_string('student_grade', self::LANG_FILE),
                                            'sortable' => false,
                                            'display_function' => 'htmltab_display_function'),
                'credits'          => array('header' => get_string('student_credits', self::LANG_FILE),
                                            'sortable' => false,
                                            'display_function' => 'htmltab_display_function'),
                'locked'           => array('header' => get_string('student_locked', self::LANG_FILE),
                                            'sortable' => false,
                                            'display_function' => 'htmltab_display_function')
            );
        }

        if ($dir !== 'DESC') {
            $dir = 'ASC';
        }
        if (isset($columns[$sort])) {
            $columns[$sort]['sortable'] = $dir;
        } else {
            $sort = 'name';
            $columns[$sort]['sortable'] = $dir;
        }

        $users = array();
        if (empty($this->id)) {
            $do_select_all = optional_param('do_select_all', '0', PARAM_CLEAN);
            if (!empty($do_select_all) && $do_select_all === '1') {

                // save all users as selected in session (user clicked "select all on all pages" button)
                $users = $this->get_users_enrolled($type, $sort, $dir, 0, 0, '', '');
                foreach ($users as $userid => $user) {
                    $SESSION->associationpage[$pagename][$userid] = new stdClass;
                    $SESSION->associationpage[$pagename][$userid]->id = $userid;
                    $SESSION->associationpage[$pagename][$userid]->selected = true;
                    $SESSION->associationpage[$pagename][$userid]->associd = $user->association_id;
                }

                echo get_string('success', self::LANG_FILE);
                die();

            } else {

                $users = $this->get_users_enrolled($type, $sort, $dir, $page * $perpage, $perpage, $namesearch, $alpha);
                $usercount = $this->count_users_enrolled($type, $namesearch, $alpha);

            }

            pmalphabox(new moodle_url('/elis/program/index.php',
                               array('s' => 'stu', 'section' => 'curr',
                                     'action' => 'bulkedit', 'id' => $classid,
                                     'class' => $classid, 'perpage' => $perpage,
                                     'search' => $namesearch, 'sort' => $sort,
                                     'dir' => $dir)),
                       'alpha', get_string('lastname', self::LANG_FILE) .':');

            $pagingbar = new paging_bar($usercount, $page, $perpage,
                    "index.php?s=stu&amp;section=curr&amp;id=$classid&amp;class=$classid&amp;&amp;action=bulkedit&amp;" .
                    "sort=$sort&amp;dir=$dir&amp;perpage=$perpage&amp;alpha=$alpha&amp;stype=$type" .
                    "&amp;search=" . urlencode($namesearch)); // TBD: .'&amp;'
            echo $OUTPUT->render($pagingbar);
            flush();

            pmsearchbox(null, 'search', 'get', get_string('show_all_users', self::LANG_FILE)); // TBD: moved from below

        } else {
            $user       = $this->_db->get_record(user::TABLE, array('id' => $this->userid));
            $user->name = fullname($user);
            $users[]    = $user;
            $usercount  = 0;
        }

        $has_users = ((is_array($users) && !empty($users)) || ($users instanceof Iterator && $users->valid() === true)) ? true : false;

        if (empty($this->id) && $has_users === false) {
            pmshowmatches($alpha, $namesearch);
            $table = null;
        } else {
            $stuobj = new student();
            $newarr = array();
            // $table->width = "100%"; // TBD
            $pmclass = new pmclass($classid);
            if (empty(elis::$config->elis_program->force_unenrol_in_moodle)) {
                $mcourse = $pmclass->get_moodle_course_id();
                $ctx = $mcourse ? get_context_instance(CONTEXT_COURSE, $mcourse) : 0;
            }

            foreach ($users as $user) {
                $selected = false;
                $locked = $user->locked;
                $credits = $user->credits;
                $grade = $user->grade;
                $status = $user->completestatusid;
                $enrolmenttime =  $user->enrolmenttime;
                $completetime = $user->completetime;
                $unenrol = false;
                $changed = false;

                $selection = retrieve_session_selection_bulkedit($user->id, 'bulkedit');

                if ($selection) {
                    if (isset($selection->selected) && $selection->selected === true) {
                        $selected = $selection->selected;
                    }
                    if (isset($selection->unenrol) && $selection->unenrol === true) {
                        $unenrol = $selection->unenrol;
                    }
                    if (isset($selection->locked) && $selection->locked === true) {
                        $locked = $selection->locked;
                    }
                    if (isset($selection->credits)) {
                        $credits = $selection->credits;
                    }
                    if (isset($selection->grade)) {
                        $grade = $selection->grade;
                    }
                    if (isset($selection->status)) {
                        $status = $selection->status;
                    }
                    if (isset($selection->enrolment_date)) {
                        $enrolmenttime = pm_timestamp(0, 0, 0, $selection->enrolment_date->month, $selection->enrolment_date->day, $selection->enrolment_date->year);
                    }
                    if (isset($selection->completion_date)) {
                        $completetime = pm_timestamp(0, 0, 0, $selection->completion_date->month, $selection->completion_date->day, $selection->completion_date->year);
                    }
                    $changed = true;
                }
                $tabobj = new stdClass;
                foreach ($columns as $column => $cdesc) {
                    switch ($column) {
                        case 'select':
                            $tabobj->{$column} = '<input type="checkbox" onclick="select_item(' . $user->id .')" name="users[' . $user->id . '][selected]" '.
                                                 'value="1" id="selected' . $user->id .'" '.(($selected) ? 'checked="checked" ' : ' ').
                                                 'onchange="proxy_select(' . $user->id . ')"/>';
                            break;

                        case 'unenrol':
                            if (!empty($mcourse)) {
                                $userobj = new user($user);
                                $muser = $userobj->get_moodleuser();
                                if (!empty($muser)) {
                                    $role_assignment_exists = $this->_db->record_exists_select('role_assignments',
                                        "userid = ? AND contextid = ? AND component != 'enrol_elis'", array($muser->id, $ctx->id));
                                    if ($role_assignment_exists) {
                                        // user is assigned a role other than via the elis
                                        // enrolment plugin
                                        $tabobj->{$column} = '';
                                        break;
                                    }
                                }
                            }
                            $tabobj->{$column} = '<input type="checkbox" id="unenrol'.$user->id.'" name="users[' . $user->id . '][unenrol]" '.
                                                 'value="1" onchange="proxy_select('.$user->id.')" '.(($unenrol) ? 'checked="checked" ' : ' ').'/>';
                            break;

                        case 'name':
                        case 'idnumber':
                        case 'description';
                            $tabobj->{$column} = $user->{$column};
                            break;

                        case 'enrolmenttime':
                            $tabobj->{$column} = cm_print_date_selector('users[' . $user->id . '][startday]',
                                                     'users[' . $user->id . '][startmonth]',
                                                     'users[' . $user->id . '][startyear]',
                                                     $enrolmenttime, true, 'proxy_select('.$user->id.')');
                            break;

                        case 'completetime':
                            $tabobj->{$column} = cm_print_date_selector('users[' . $user->id . '][endday]',
                                                     'users[' . $user->id . '][endmonth]',
                                                     'users[' . $user->id . '][endyear]',
                                                     $completetime, true, 'proxy_select('.$user->id.')');
                            break;

                        case 'completestatusid':
                            $choices = array();
                            foreach (student::$completestatusid_values as $key => $csidv) {
                                $choices[$key] = get_string($csidv, self::LANG_FILE);
                            }
                            $tabobj->{$column} = cm_choose_from_menu($choices, 'users['.$user->id.'][completestatusid]',
                                                                     $status, '', 'proxy_select('.$user->id.')', '', true);
                            break;

                        case 'grade':
                            $tabobj->{$column} = '<input type="text" id="grade'.$user->id.'" id="locked'.$user->id.'" '.
                                                 'name="users['.$user->id.'][grade]" value="'.$grade.'" '.
                                                 'size="5" onchange="proxy_select('.$user->id.')" />';
                            break;

                        case 'credits':
                            $tabobj->{$column} = '<input type="text" id="credits'.$user->id.'" name="users['.$user->id.'][credits]" '.
                                                 'value="'.$credits.'" size="5" onchange="proxy_select('.$user->id.')" />';
                            break;

                        case 'locked':
                            $tabobj->{$column} = '<input type="checkbox" id="locked'.$user->id.'" name="users['.$user->id.'][locked]" value="1" '.
                                                 ($locked ? 'checked="checked" ' : ' ').'onchange="proxy_select('.$user->id.')" />'.
                                                 '<input type="hidden" name="users['.$user->id.'][idnumber]" value="'.$user->idnumber.'" />'.
                                                 '<input type="hidden" id="associationid'.$user->id.'" name="users['.$user->id.'][association_id]" '.
                                                 'value="'.$user->association_id.'" />';
                            break;

                        case 'edited':
                            $tabobj->{$column} = '<input type="checkbox" name="users['.$user->id.'][changed]" id="changed'.$user->id.'" '.
                                                 ($changed ? 'checked="checked" ' : ' ').'/>';
                            break;

                        default:
                            $tabobj->{$column} = '';
                            break;
                    }
                }
                $newarr[] = $tabobj;
                // $table->data[] = $newarr;
            }
            // TBD: student_table() ???
            $table = new display_table($newarr, $columns, $this->get_base_url(), 'sort', 'dir', array('id' => 'selectiontbl', 'width' => '100%'));
        }
        unset($users);

        $ids_for_checkbox_selection = (!empty($SESSION->associationpage[$pagename]) && is_array($SESSION->associationpage[$pagename]))
            ? array_keys($SESSION->associationpage[$pagename]) : array();

        print_ids_for_checkbox_selection($ids_for_checkbox_selection, $classid, 'stu', 'bulkedit');

        if (empty($this->id)) {
            echo '<form method="post" action="index.php?s=stu&amp;section=curr&amp;id=' . $classid . '" >'."\n";
            echo '<input type="hidden" name="action" value="updatemultiple_confirm" />'."<br />\n";
            echo '<input type="hidden" name="sesskey" value="'.sesskey().'"/>';
            echo $this->get_bulk_edit_ui();
        } else {
            echo '<form method="post" action="index.php?s=stu&amp;section=curr&amp;id=' . $classid . '" >'."\n";
            echo '<input type="hidden" name="action" value="updatemultiple" />'."\n";
            echo '<input type="hidden" name="sesskey" value="'.sesskey().'"/>';
            echo '<input type="hidden" name="association_id" value="' . $this->id . '" />' . "\n";
            echo '<input type="hidden" name="id" value="' . $this->classid . '" />' . "\n";
            echo '<input type="hidden" name="userid" value="' . $this->userid . '" />' . "\n";
        }

        if (!empty($newarr)) { // TBD: $newarr or $table?
            if (empty($this->id)) {
                $PAGE->requires->js('/elis/program/js/classform_bulkedit.js');
                $numselected_allpages = (!empty($SESSION->associationpage[$pagename]) ? count($SESSION->associationpage[$pagename]) : 0);
                $str_numchanged_allpages = get_string('numchanged_allpages', 'elis_program',
                                                       array('num' => '<span id="numselected_allpages">'.$numselected_allpages.'</span>'));
                echo '<div style="display:inline-block;width:100%">';
                echo '<span style="float:right;font-weight:bold">'.$str_numchanged_allpages.'</span>';
                echo '<input type="button" onclick="checkbox_select(true,\'[selected]\',\'selected\')" value="'.get_string('selectallonpage', self::LANG_FILE).'" /> ';
                echo '<input type="button" onclick="do_select_all();" value="'.get_string('selectallonallpages', self::LANG_FILE).'" /> ';
                echo '<input type="button" onclick="checkbox_select(false,\'[selected]\',\'selected\')" value="'.get_string('deselectallonpage', self::LANG_FILE).'" /> ';
                echo '<input type="button" onclick="do_deselect_all();" value="'.get_string('deselectallonallpages', self::LANG_FILE).'" /> ';
                echo '</div>';
            }
            echo $table->get_html();
        }

        if (isset($this->id)) {
            $elements = $this->pmclass->course->get_completion_elements();
            if (!empty($elements) && $elements->valid() === true) {

                $select = 'classid = ? AND userid = ? ';
                $grades = $this->_db->get_records_select(student_grade::TABLE, $select, array($this->classid, $this->userid), 'id', 'completionid,id,classid,userid,grade,locked,timegraded,timemodified');

                $columns = array(
                    'element'          => array('header' => get_string('grade_element', self::LANG_FILE),
                                                'display_function' => 'htmltab_display_function'),
                    'grade'            => array('header' => get_string('grade', self::LANG_FILE),
                                                'display_function' => 'htmltab_display_function'),
                    'locked'           => array('header' => get_string('student_locked', self::LANG_FILE),
                                                'display_function' => 'htmltab_display_function'),
                    'timegraded'       => array('header' => get_string('date_graded', self::LANG_FILE),
                                                'display_function' => 'htmltab_display_function')
                );

                if ($dir !== 'DESC') {
                    $dir = 'ASC';
                }
                if (isset($columns[$sort])) {
                    $columns[$sort]['sortable'] = $dir;
                } else {
                    $sort = 'element'; // TBD
                    $columns[$sort]['sortable'] = $dir;
                }
                // $table->width = "100%"; // TBD

                $newarr = array();
                foreach ($elements as $element) {
                    $tabobj = new stdClass;
                    foreach ($columns as $column => $cdesc) {
                        switch ($column) {
                            case 'element':
                                if (isset($grades[$element->id])) {
                                    $name = 'element['.$grades[$element->id]->id.']';
                                    $value = $element->id;
                                } else {
                                    $name = 'newelement['.$element->id.']';
                                    $value = $element->id;
                                }
                                $tabobj->{$column} = '<input type="hidden" name="'.$name.'" ' .
                                            'value="' . $value . '" />'.s($element->idnumber);
                                break;

                            case 'timegraded':
                                if (isset($grades[$element->id])) {
                                    $name = 'timegraded['.$grades[$element->id]->id.']';
                                    $value = $grades[$element->id]->timegraded;
                                } else {
                                    $name = 'newtimegraded['.$element->id.']';
                                    $value = 0;
                                }
                                $tabobj->{$column} = cm_print_date_selector($name.'[startday]',
                                                                   $name.'[startmonth]',
                                                                   $name.'[startyear]',
                                                                   $value, true);
                                break;

                            case 'grade':
                                if (isset($grades[$element->id])) {
                                    $name = 'grade['.$grades[$element->id]->id.']';
                                    $value = $grades[$element->id]->grade;
                                } else {
                                    $name = 'newgrade['.$element->id.']';
                                    $value = 0;
                                }
                                $tabobj->{$column} = '<input type="text" name="'.$name.'" ' .
                                            'value="' . $value . '" size="5" />';
                                break;

                            case 'locked':
                                if (isset($grades[$element->id])) {
                                    $name = 'locked['.$grades[$element->id]->id.']';
                                    $value = $grades[$element->id]->locked;
                                } else {
                                    $name = 'newlocked['.$element->id.']';
                                    $value = 0;
                                }
                                $tabobj->{$column} = '<input type="checkbox" name="'.$name.'" ' .
                                            'value="1" '.($value?'checked="checked"':'').'/>';
                                break;

                            default:
                                $tabobj->{$column} = '';
                                break;
                        }
                    }
                    $newarr[] = $tabobj;
                    // $table->data[] = $newarr;
                }
                // TBD: student_table() ???
                $table = new display_table($newarr, $columns, $this->get_base_url(), 'sort', 'dir', array('id' => 'wowwww'));
                if (!empty($table)) { // TBD: $newarr or $table?
                    echo '<br />';
                    echo $table->get_html();
                }
            }
            unset($elements);
        }

        if ($has_users === true) {
            echo '<br /><input type="submit" value="' . get_string('save_enrolment_changes', self::LANG_FILE) . '">'."\n";
        }

        $cancel_js = "document.location='index.php?s=stu&amp;section=curr&amp;action=default&amp;id=$classid&amp;".
                     "sort=$sort&amp;dir=$dir&amp;perpage=$perpage&amp;alpha=$alpha&amp;search=".urlencode($namesearch)."';";
        echo '<input type="button" onclick="'.$cancel_js.'" value="'.get_string('cancel').'" />';
        echo '<input type="button" onclick="datapersist_do_reset()" value="'.get_string('reset').'" />';
        echo '</form>'."\n";

        $output = ob_get_contents();
        ob_end_clean();

        return $output;
    }

    /**
     * Returns the HTML for the Bulk Apply Inputs Box
     * @return string the HTML for the Bulk Apply Inputs Box
     */
    public function get_bulk_edit_ui() {

        // generate choices for the completion status menu
        $statuschoices = array();
        foreach (student::$completestatusid_values as $key => $csidv) {
            $statuschoices[$key] = get_string($csidv, self::LANG_FILE);
        }

        $blktpl_table = new html_table;
        $blktpl_table->head = array(
            'enable' => get_string('blktpl_enable', self::LANG_FILE),
            'label' => get_string('blktpl_field', self::LANG_FILE),
            'value' => get_string('blktpl_value', self::LANG_FILE),
        );
        $blktpl_table->data = array(
            array(
                '<input type="checkbox" id="blktpl_enrolmenttime_checked">',
                get_string('enrolment_time', self::LANG_FILE),
                cm_print_date_selector('blktpl_enrolmenttime_d', 'blktpl_enrolmenttime_m', 'blktpl_enrolmenttime_y', 0, true)
            ),
            array(
                '<input type="checkbox" id="blktpl_completetime_checked">',
                get_string('completion_time', self::LANG_FILE),
                cm_print_date_selector('blktpl_completetime_d', 'blktpl_completetime_m', 'blktpl_completetime_y', 0, true)
            ),
            array(
                '<input type="checkbox" id="blktpl_status_checked">',
                get_string('student_status', self::LANG_FILE),
                cm_choose_from_menu($statuschoices, 'blktpl_status', '', '', '', '', true)
            ),
            array(
                '<input type="checkbox" id="blktpl_grade_checked">',
                get_string('student_grade', self::LANG_FILE),
                '<input type="text" id="blktpl_grade" name="blktpl_grade" size="5"/>'
            ),
            array(
                '<input type="checkbox" id="blktpl_credits_checked">',
                get_string('student_credits', self::LANG_FILE),
                '<input type="text" id="blktpl_credits" name="blktpl_credits" size="5"/>'
            ),
            array(
                '<input type="checkbox" id="blktpl_locked_checked">',
                get_string('student_locked', self::LANG_FILE),
                '<input type="checkbox" id="blktpl_locked" name="blktpl_locked"/>'
            ),
            array(
                '',
                '',
                '<input type="button" onclick="do_bulk_value_apply();return false;" value="'.get_string('blktpl_applytousers_button', self::LANG_FILE).'"/>'
            ),
        );

        return html_writer::table($blktpl_table);
    }

    public function __toString() { // to_string()
        return $this->user->idnumber . ' in ' . $this->pmclass->idnumber; // TBD
    }

/////////////////////////////////////////////////////////////////////
//                                                                 //
//  DATA FUNCTIONS:                                                //
//                                                                 //
/////////////////////////////////////////////////////////////////////


    /**
     * Check for a duplicate record when doing an insert.
     *
     * @param object $record The record we want to insert.
     */
    function duplicate_check($record=null) {

        if(empty($record)) {
            $record = $this;
        }

        /// Check for an existing enrolment - it can't already exist.
        if ($this->_db->record_exists(student::TABLE, array('classid' => $record->classid, 'userid' => $record->userid))) {
            return true;
        }

        return false;
    }

    /**
     * Get a list of the existing students for the supplied (or current)
     * class. Regardless of status either passed failed or not completed.
     *
     * @paam int $cid A class ID (optional).
     * @return recordset A recordset of user records.
     */
    function get_students($cid = 0) {

        if (!$cid) {
            if (empty($this->classid)) {
                return array();
            }
            $cid = $this->classid;
        }

        $uids = array();

        $students = $this->_db->get_recordset(student::TABLE, array('classid' => $cid));
        foreach ($students as $student) {
            $uids[] = $student->userid;
        }
        unset($students);

        if (!empty($uids)) {
            $sql = 'SELECT id, idnumber, username, firstname, lastname
                    FROM {'. user::TABLE .'}
                    WHERE id IN ( '. implode(', ', $uids). ' )
                    ORDER BY lastname ASC, firstname ASC';

            return $this->_db->get_recordset_sql($sql);
        }
        return array();
    }

    /**
     * get the students on the waiting list for the supplied (or current) class
     * @param INT $cid the class id
     * @return recordset students on the waiting list
     */
    public function get_waiting($cid = 0) {

        if (!$cid) {
            if (empty($this->classid)) {
                return array();
            }
            $cid = $this->classid;
        }

        $uids = array();

        $students = $this->_db->get_recordset(waitlist::TABLE, array('classid' => $cid));
        foreach ($students as $student) {
            $uids[] = $student->userid;
        }
        unset($students);

        if (!empty($uids)) {
            $sql = 'SELECT id, idnumber, username, firstname, lastname
                    FROM {'. user::TABLE .'}
                    WHERE id IN ( '. implode(', ', $uids) .' )
                    ORDER BY lastname ASC, firstname ASC';

            return $this->_db->get_recordset_sql($sql);
        }
        return array();
    }

    /**
     * Gets classes a user is on the waitlist for in a curriculum
     * @global moodle_database $DB
     * @param int $userid User ID
     * @param int $curid Curriculum ID
     * @return recordset Recordset of waitlist, class, and course information
     */
    static public function get_waitlist_in_curriculum($userid, $curid) {
        global $DB;
        $select  = 'SELECT wat.id wlid, wat.position, cls.idnumber clsid, crs.name, cls.* ';
        $tables = 'FROM {'. curriculumcourse::TABLE .'} curcrs ';
        $join   = 'JOIN {'. course::TABLE .'} crs ON curcrs.courseid = crs.id ';
        $join  .= 'JOIN {'. pmclass::TABLE .'} cls ON cls.courseid = crs.id ';
        $join  .= 'JOIN {'. waitlist::TABLE .'} wat ON wat.classid = cls.id ';
        $where  = 'WHERE curcrs.curriculumid = ? ';
        $where .= 'AND wat.userid = ? ';
        $sort = 'ORDER BY curcrs.position';

        $sql = $select.$tables.$join.$where.$sort;
        return $DB->get_recordset_sql($sql, array($curid, $userid));
    }

    /**
     * gets a list of classes that the given (or current) student is a part of
     * filters are applied to classid_number
     * @param int $cuserid
     * @param str $sort
     * @param str $dir
     * @param int $startrec
     * @param int $perpage
     * @param str $namesearch
     * @param str $alpha
     * @return array
     */
    public function get_waitlist($cuserid=0, $sort='name', $dir='ASC', $startrec=0, $perpage=0, $namesearch='',
                                            $alpha='') {

        if (!$cuserid) {
            if (empty($this->userid)) {
                return array();
            }
            $cuserid = $this->userid;
        }

        $params = array();
        $CRSNAME_LIKE = $this->_db->sql_like('crs.name', ':crs_like', FALSE);
        $CRSNAME_STARTSWITH = $this->_db->sql_like('crs.name', ':crs_startswith', FALSE);
        $CLSID_LIKE = $this->_db->sql_like('cls.idnumber', ':clsid', FALSE);

        $select  = 'SELECT wat.id wlid, wat.position, cls.idnumber clsid, crs.name, cls.*';
        $tables  = 'FROM {'. waitlist::TABLE .'} wat ';
        $join    = 'JOIN {'. pmclass::TABLE .'} cls ON wat.classid = cls.id ';
        $join   .= 'JOIN {'. course::TABLE .'} crs ON cls.courseid = crs.id ';
        $where   = 'wat.userid = :userid ';
        $params['userid'] = $cuserid;

        if (!empty($namesearch)) {
            $namesearch = trim($namesearch);
            $where .= (!empty($where) ? ' AND ' : ' ') .'('. $CRSNAME_LIKE .') OR ('. $CLSID_LIKE .') ';
            $params['crs_like'] = "%{$namesearch}%";
            $params['clsid_like'] = "%{$namesearch}%";
        }

        if ($alpha) {
            $where .= (!empty($where) ? ' AND ' : ' ') . '('. $CRSNAME_STARTSWITH .') ';
            $params['crs_startswith'] = "{$alpha}%";
        }

        if (!empty($where)) {
            $where = 'WHERE '.$where.' ';
        }

        if ($sort) {
            if ($sort === 'name') {
                $sort = 'crs.name';
            }
            $sort = 'ORDER BY '.$sort .' '. $dir.' ';
        }

        $sql = $select.$tables.$join.$where.$sort;
        return $this->_db->get_records_sql($sql, $params, $startrec, $perpage);
    }

    /**
     * Gets a student listing with specific sort and other filters from this
     * class (or supplide) includes students that have passed failed or not
     * completed
     *
     * @param int $classid The class ID.
     * @param string $sort Field to sort on.
     * @param string $dir Direction of sort.
     * @param int $startrec Record number to start at.
     * @param int $perpage Number of records per page.
     * @param string $namesearch Search string for student name.
     * @param string $alpha Start initial of student name filter.
     * @return object array Returned records.
     */
    function get_listing($classid=0, $sort='name', $dir='ASC', $startrec=0, $perpage=0, $namesearch='',
                                 $alpha='') {
        if (!$classid) {
            if (empty($this->classid)) {
                return 0;
            }
            $classid = $this->classid;
        }

        $params = array();
        $FULLNAME = $this->_db->sql_concat('usr.firstname', "' '", 'usr.lastname');
        $FULLNAME_LIKE = $this->_db->sql_like($FULLNAME, ':name_like', FALSE);
        $IDNUMBER_LIKE = $this->_db->sql_like('usr.idnumber', ':id_like', FALSE);
        $LASTNAME_STARTSWITH = $this->_db->sql_like('usr.lastname', ':lastname_startswith', FALSE);

        $select  = 'SELECT stu.* ';
        $select .= ', ' . $FULLNAME . ' as name, usr.idnumber ';
    //    $select .= ', ' . $FULLNAME . ' as name, usr.type as description ';
        $tables  = 'FROM {'. student::TABLE .'} stu ';
        $join    = 'JOIN {'. user::TABLE .'} usr ';
        $on      = 'ON stu.userid = usr.id ';
        $where   = 'stu.classid = :clsid ';
        $params['clsid'] = $classid;

        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $where .= ' AND usr.inactive = 0 ';
        }

        if (!empty($namesearch)) {
            $namesearch = trim($namesearch);
            $where .= ' AND (('. $FULLNAME_LIKE .') OR ('. $IDNUMBER_LIKE .')) ';
            $params['name_like'] = "%{$namesearch}%";
            $params['id_like']   = "%{$namesearch}%";
        }

        if ($alpha) {
            $where .= ' AND ('. $LASTNAME_STARTSWITH .') ';
            $params['lastname_startswith'] = "{$alpha}%";
        }

        $where = 'WHERE '. $where .' ';

        if ($sort) {
            if ($sort == 'name') { // TBV: ELIS-2772
                //$sort = $FULLNAME;
                $sort = "ORDER BY usr.lastname {$dir}, usr.firstname {$dir} ";
            } else {
                $sort = 'ORDER BY '. $sort .' '. $dir .' ';
            }
        }

        $sql = $select.$tables.$join.$on.$where.$sort;
        return $this->_db->get_records_sql($sql, $params, $startrec, $perpage);
    }

    /**
     * counts the number of students enroled in the supplied (or current) class
     * That have not yet completed the class.
     *
     * @param INT $classid class id number
     * @param STR $namesearch name of the users being searched for
     * @param STR $alpha starting letter of the user being searched for
     * @return INT
     */
    public static function count_enroled($classid = 0, $namesearch = '', $alpha = '') {
        global $DB; // NOTE: method called statically from pmclassform.class.php::validation().

        if (empty($classid)) {
            return 0;
        }

        $params = array();
        $fullname = $DB->sql_concat('usr.firstname', "' '", 'usr.lastname');
        $fullnamelike = $DB->sql_like($fullname, ':name_like', false);
        $lastnamestartswith = $DB->sql_like('usr.lastname', ':lastname_startswith', false);

        $select  = 'SELECT COUNT(stu.id) ';
        $tables  = 'FROM {'.static::TABLE.'} stu ';
        $join    = 'JOIN {'.user::TABLE.'} usr ';
        $on      = 'ON stu.userid = usr.id ';
        $where   = 'stu.completestatusid = '.STUSTATUS_NOTCOMPLETE.' AND stu.classid = :clsid ';
        $params['clsid']= $classid;

        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $where .= ' AND usr.inactive = 0 ';
        }

        if (!empty($namesearch)) {
            $namesearch = trim($namesearch);
            $where .= ' AND ('.$fullnamelike.') ';
            $params['name_like'] = "%{$namesearch}%";
        }

        if ($alpha) {
            $where .= ' AND ('.$lastnamestartswith.') ';
            $params['lastname_startswith'] = "{$alpha}%";
        }

        $where = 'WHERE '.$where.' ';

        $sql = $select.$tables.$join.$on.$where;
        return $DB->count_records_sql($sql, $params);
    }

    /**
     * Count the number of students for this class.
     *
     * @param int    $classid     The class ID.
     * @param string $namesearch  name of the users being searched for
     * @param string $alpha       starting letter of the user being searched for
     */
    public function count_records($classid = 0, $namesearch = '', $alpha = '') {

        if (!$classid) {
            if (empty($this->classid)) {
                return 0;
            }
            $classid = $this->classid;
        }

        $params = array();
        $FULLNAME = $this->_db->sql_concat('usr.firstname', "' '", 'usr.lastname');
        $FULLNAME_LIKE = $this->_db->sql_like($FULLNAME, ':name_like', FALSE);
        $LASTNAME_STARTSWITH = $this->_db->sql_like('usr.lastname', ':lastname_startswith', FALSE);

        $select  = 'SELECT COUNT(stu.id) ';
        $tables  = 'FROM {'. student::TABLE .'} stu ';
        $join    = 'JOIN {'. user::TABLE .'} usr ';
        $on      = 'ON stu.userid = usr.id ';
        $where   = array('stu.classid = :clsid ');
        $params['clsid'] = $classid;

        if (!empty($namesearch)) {
            $namesearch = trim($namesearch);
            $where[] = '('.$FULLNAME_LIKE.')';
            $params['name_like'] = "%{$namesearch}%";
        }

        if ($alpha) {
            $where[] = '('.$LASTNAME_STARTSWITH.')';
            $params['lastname_startswith'] = "{$alpha}%";
        }

        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $where[] = 'usr.inactive = 0';
        }

        $where = 'WHERE ' . implode(' AND ', $where);

        $sql = $select . $tables . $join . $on . $where;
        return $this->_db->count_records_sql($sql, $params);
    }

    /**
     * Get a list of the available students not already attached to this course.
     *
     * TBD: add remaining params
     * @param string $namesearch  name of the users being searched for
     * @param string $alpha       starting letter of the user being searched for
     * @return recordset A recordset of user records.
     */
    function get_users_avail($sort = 'name', $dir = 'ASC', $startrec = 0,
                             $perpage = 0, $namesearch = '', $alpha = '') {
        if (empty($this->_db)) {
            return NULL;
        }

        $params = array();
        $FULLNAME = $this->_db->sql_concat('usr.firstname', "' '", 'usr.lastname');
        $FULLNAME_LIKE = $this->_db->sql_like($FULLNAME, ':name_like', FALSE);
        $IDNUMBER_LIKE = $this->_db->sql_like('usr.idnumber', ':id_like', FALSE);
        $LASTNAME_STARTSWITH = $this->_db->sql_like('usr.lastname', ':lastname_startswith', FALSE);

//        $select  = 'SELECT usr.id, usr.idnumber, ' . $FULLNAME . ' as name, usr.type as description, ' .
        $select  = 'SELECT usr.id, usr.idnumber, ' . $FULLNAME . ' as name, ' .
                   'stu.classid, stu.userid, stu.enrolmenttime, stu.completetime, ' .
                   'stu.completestatusid, stu.grade ';
        $tables  = 'FROM {'. user::TABLE .'} usr ';
        $join    = 'LEFT JOIN {'. student::TABLE .'} stu ';
        $on      = 'ON stu.userid = usr.id AND stu.classid = :clsid ';
        $where   = 'stu.id IS NULL';
        $params['clsid'] = $this->classid;

        if (!empty($namesearch)) {
            $namesearch = trim($namesearch);
            $where .= ' AND (('. $FULLNAME_LIKE .') OR ('. $IDNUMBER_LIKE .')) ';
            $params['name_like'] = "%{$namesearch}%";
            $params['id_like']   = "%{$namesearch}%";
        }

        if ($alpha) {
            $where .= ' AND ('. $LASTNAME_STARTSWITH .') ';
            $params['lastname_startswith'] = "{$alpha}%";
        }

        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $where .= ' AND usr.inactive = 0 ';
        }

        $uids = array();

        if ($users = $this->get_students()) {
            foreach ($users as $user) {
                $uids[] = $user->id;
            }
        }
        unset($users);

        if ($users = $this->get_waiting()) {
            foreach ($users as $user) {
                $uids[] = $user->id;
            }
        }
        unset($users);

        $ins = new instructor();
        if ($users = $ins->get_instructors($this->classid)) {
            foreach ($users as $user) {
                $uids[] = $user->id;
            }
        }
        unset($users);

        if (!empty($uids)) {
            $where .= ' AND usr.id NOT IN ( '. implode(', ', $uids) .' ) ';
        }

        $where = 'WHERE '. $where .' ';

        // *** TBD ***

        // TODO: Ugly, this needs to be overhauled
        $cpage = new pmclasspage();

        if (!$cpage->_has_capability('elis/program:class_enrol', $this->classid)) {
            //perform SQL filtering for the more "conditional" capability

            $allowed_clusters = pmclass::get_allowed_clusters($this->classid);

            if (empty($allowed_clusters)) {
                $where .= 'AND 0=1 ';
            } else {
                $cluster_filter = implode(',', $allowed_clusters);
                // *** TBD ***
                $where .= 'AND usr.id IN (
                             SELECT userid FROM {'. clusterassignment::TABLE ."}
                             WHERE clusterid IN ({$cluster_filter})) ";
            }
        }

        if ($sort) {
            if ($sort == 'name') { // TBV: ELIS-2772
                //$sort = $FULLNAME;
                $sort = "ORDER BY usr.lastname {$dir}, usr.firstname {$dir} ";
            } else {
                $sort = 'ORDER BY '. $sort .' '. $dir .' ';
            }
        }

        $sql = $select.$tables.$join.$on.$where.$sort;
        return $this->_db->get_recordset_sql($sql, $params, $startrec, $perpage);
    }

    /**
     * Count the available students not already attached to this course.
     *
     * @param string $namesearch  name of the users being searched for
     * @param string $alpha       starting letter of the user being searched for
     * @return  int  count of users.
     */
    function count_users_avail($namesearch = '', $alpha = '') {
        $params = array();
        $FULLNAME = $this->_db->sql_concat('usr.firstname', "' '", 'usr.lastname');
        $FULLNAME_LIKE = $this->_db->sql_like($FULLNAME, ':name_like', FALSE);
        $IDNUMBER_LIKE = $this->_db->sql_like('usr.idnumber', ':id_like', FALSE);
        $LASTNAME_STARTSWITH = $this->_db->sql_like('usr.lastname', ':lastname_startswith', FALSE);

        $select  = 'SELECT COUNT(usr.id) ';
        $tables  = 'FROM {'. user::TABLE .'} usr ';
        $join    = 'LEFT JOIN {'. student::TABLE .'} stu ';
        $on      = 'ON stu.userid = usr.id AND stu.classid = :clsid ';
        $where   = 'stu.id IS NULL';
        $params['clsid'] = $this->classid;

        if (!empty($namesearch)) {
            $namesearch = trim($namesearch);
            $where .= ' AND (('. $FULLNAME_LIKE .') OR ('. $IDNUMBER_LIKE .')) ';
            $params['name_like'] = "%{$namesearch}%";
            $params['id_like']   = "%{$namesearch}%";
        }

        if ($alpha) {
            $where .= ' AND ('. $LASTNAME_STARTSWITH .') ';
            $params['lastname_startswith'] = "{$alpha}%";
        }

        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $where .= ' AND usr.inactive = 0 ';
        }

        $uids = array();
        if ($users = $this->get_students()) {
            foreach ($users as $user) {
                $uids[] = $user->id;
            }
        }
        unset($users);

        if ($users = $this->get_waiting()) {
            foreach ($users as $user) {
                $uids[] = $user->id;
            }
        }
        unset($users);

        $ins = new instructor();
        if ($users = $ins->get_instructors($this->classid)) {
            foreach ($users as $user) {
                $uids[] = $user->id;
            }
        }
        unset($users);

        if (!empty($uids)) {
            $where .= ' AND usr.id NOT IN ( '. implode(', ', $uids) .' ) ';
        }
        $where = 'WHERE '. $where .' ';

        // *** TBD ***

        // TODO: Ugly, this needs to be overhauled
        $cpage = new pmclasspage();

        if (!$cpage->_has_capability('elis/program:class_enrol', $this->classid)) {
            //perform SQL filtering for the more "conditional" capability

            $allowed_clusters = pmclass::get_allowed_clusters($this->classid);

            if (empty($allowed_clusters)) {
                $where .= 'AND 0=1';
            } else {
                $cluster_filter = implode(',', $allowed_clusters);
                // *** TBD ***
                $where .= 'AND usr.id IN (
                             SELECT userid FROM {'. clusterassignment::TABLE ."}
                             WHERE clusterid IN ({$cluster_filter}))";
            }
        }

        $sql = $select.$tables.$join.$on.$where;
        return $this->_db->count_records_sql($sql, $params);
    }

    /**
     * Get a list of the students already attached to this course.
     *
     * TBD - add remaining params
     * @param string $namesearch  name of the users being searched for
     * @param string $alpha       starting letter of the user being searched for
     * @uses  object $CFG         TBD: $CFG->curr_configteams
     * @return recordset A recordset of user records.
     */
    function get_users_enrolled($type = '', $sort = 'name', $dir = 'ASC', $startrec = 0,
                             $perpage = 0, $namesearch = '', $alpha = '') {
        global $CFG;

        if (empty($this->_db)) {
            return NULL;
        }

        $params = array();
        $FULLNAME = $this->_db->sql_concat('usr.firstname', "' '", 'usr.lastname');
        $FULLNAME_LIKE = $this->_db->sql_like($FULLNAME, ':name_like', FALSE);
        $IDNUMBER_LIKE = $this->_db->sql_like('usr.idnumber', ':id_like', FALSE);
        $LASTNAME_STARTSWITH = $this->_db->sql_like('usr.lastname', ':lastname_startswith', FALSE);

//        $select  = 'SELECT usr.id, usr.idnumber, ' . $FULLNAME . ' as name, usr.type as description, ' .
        $select  = 'SELECT usr.id, usr.idnumber, ' . $FULLNAME . ' as name, ' .
                   'stu.classid, stu.userid, usr.idnumber AS user_idnumber, stu.enrolmenttime, stu.completetime, ' .
                   'stu.completestatusid, stu.grade, stu.id as association_id, stu.credits, stu.locked ';
        $tables  = 'FROM {'. user::TABLE .'} usr ';
        $join    = 'LEFT JOIN {'. student::TABLE .'} stu ';
        $on      = 'ON stu.userid = usr.id ';

        /// If limiting returns to specific teams, set that up now.
        if (!empty($CFG->curr_configteams)) { // ***** TBD *****
            $where = 'usr.team IN ('.$CFG->curr_configteams.') ';
        } else {
            $where = '';
        }

        if (!empty($namesearch)) {
            $namesearch = trim($namesearch);
            $where .= (!empty($where) ? ' AND ' : ' ') .'(('. $FULLNAME_LIKE .') OR ('. $IDNUMBER_LIKE .')) ';
            $params['name_like'] = "%{$namesearch}%";
            $params['id_like']   = "%{$namesearch}%";
        }

        if ($alpha) {
            $where .= (!empty($where) ? ' AND ' : ' ') .'('. $LASTNAME_STARTSWITH .') ';
            $params['lastname_startswith'] = "{$alpha}%";
        }

        $where .= (!empty($where) ? ' AND ' : '') . 'classid = :clsid ';
        $params['clsid'] = $this->classid;

        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $where .= ' AND usr.inactive = 0';
        }
        $where = "WHERE $where ";

        if ($sort) {
            if ($sort === 'name') {
                $sort = $FULLNAME;
            }
            $sort = 'ORDER BY '.$sort .' '. $dir.' ';
        }

        $sql = $select.$tables.$join.$on.$where.$sort;
        return $this->_db->get_recordset_sql($sql, $params, $startrec, $perpage);
    }

    /**
     * Count of the students already attached to this course.
     *
     * TBD - add remaining param: type
     * @param string $namesearch  name of the users being searched for
     * @param string $alpha       starting letter of the user being searched for
     * @uses  object $CFG    TBD: $CFG->curr_configteams
     * @return array An array of user records.
     */
    function count_users_enrolled($type = '', $namesearch = '', $alpha = '') {
        global $CFG;

        $params = array();
        $FULLNAME = $this->_db->sql_concat('usr.firstname', "' '", 'usr.lastname');
        $FULLNAME_LIKE = $this->_db->sql_like($FULLNAME, ':name_like', FALSE);
        $IDNUMBER_LIKE = $this->_db->sql_like('usr.idnumber', ':id_like', FALSE);
        $LASTNAME_STARTSWITH = $this->_db->sql_like('usr.lastname', ':lastname_startswith', FALSE);

        $select  = 'SELECT COUNT(usr.id) ';
        $tables  = 'FROM {'. user::TABLE .'} usr ';
        $join    = 'LEFT JOIN {'. student::TABLE .'} stu ';
        $on      = 'ON stu.userid = usr.id ';

        /// If limiting returns to specific teams, set that up now.
        if (!empty($CFG->curr_configteams)) { // *** TBD ***
            $where = 'usr.team IN ('.$CFG->curr_configteams.') ';
        } else {
            $where = '';
        }

        if (!empty($namesearch)) {
            $namesearch = trim($namesearch);
            $where .= (!empty($where) ? ' AND ' : ' ') .'(('. $FULLNAME_LIKE .') OR ('. $IDNUMBER_LIKE .')) ';
            $params['name_like'] = "%{$namesearch}%";
            $params['id_like']   = "%{$namesearch}%";
        }

        if ($alpha) {
            $where .= (!empty($where) ? ' AND ' : ' ') .'('. $LASTNAME_STARTSWITH .') ';
            $params['lastname_startswith'] = "{$alpha}%";
        }

//        switch ($type) {
//            case 'student':
//                $where .= (!empty($where) ? ' AND ' : '') . 'usr.type = \'Student\' ';
//                break;
//
//            case 'instructor':
//                $where .= (!empty($where) ? ' AND ' : '') . 'usr.type = \'Instructor\' ';
//                break;
//
//            case '':
//                $where .= (!empty($where) ? ' AND ' : '') . '(usr.type = \'Student\' OR usr.type = \'Instructor\') ';
//                break;
//        }

        $where .= (!empty($where) ? ' AND ' : '') . 'classid = :clsid ';
        $params['clsid'] = $this->classid;
        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $where .= 'AND usr.inactive = 0';
        }
        $where = "WHERE $where ";

        $sql = $select.$tables.$join.$on.$where;
        return $this->_db->count_records_sql($sql, $params);
    }

    public function set_from_data($data) {
        $this->_load_data_from_record($data, true);
    }

/////////////////////////////////////////////////////////////////////
//                                                                 //
//  STATIC FUNCTIONS:                                              //
//    These functions can be used without instatiating an object.  //
//    Usage: student::[function_name([args])]                      //
//                                                                 //
/////////////////////////////////////////////////////////////////////

    /*
     * ---------------------------------------------------------------------------------------
     * EVENT HANDLER FUNCTIONS:
     *
     * These functions handle specific student events.
     *
     */

    /**
     * Function to handle class not started events.
     *
     * @param   student  $student  The class enrolment object
     * @uses    $CFG
     * @uses    $DB
     * @return  boolean            TRUE is successful, otherwise FALSE
     */

    public static function class_notstarted_handler($student) {
        global $CFG, $DB;

        require_once elispm::lib('notifications.php');

        /// Does the user receive a notification?
        $sendtouser       = elis::$config->elis_program->notify_classnotstarted_user;
        $sendtorole       = elis::$config->elis_program->notify_classnotstarted_role;
        $sendtosupervisor = elis::$config->elis_program->notify_classnotstarted_supervisor;

        /// If nobody receives a notification, we're done.
        if (!$sendtouser && !$sendtorole && !$sendtosupervisor) {
            return true;
        }

        if (!empty($student->moodlecourseid)) {
            if (!($context = get_context_instance(CONTEXT_COURSE, $student->moodlecourseid))) {
                if (in_cron()) {
                    mtrace(get_string('invalidcontext'));
                } else {
                    debugging(get_string('invalidcontext'));
                }
                return true;
            }
        } else {
            $context = get_system_context();
        }

        $message = new notification();

        /// Set up the text of the message
        $text = empty(elis::$config->elis_program->notify_classnotstarted_message) ?
                    get_string('notifyclassnotstartedmessagedef', self::LANG_FILE) :
                    elis::$config->elis_program->notify_classnotstarted_message;
        $search = array('%%userenrolname%%', '%%classname%%', '%%coursename%%');
        $pmuser = $DB->get_record(user::TABLE, array('id' => $student->userid));
        $user = new user($pmuser);
        // Get course info
        $pmcourse = $DB->get_record(course::TABLE, array('id' => $student->courseid));
        $pmclass = $DB->get_record(pmclass::TABLE, array('id' => $student->classid));

        //$replace = array(fullname($pmuser), $student->pmclass->course->name);
        $replace = array(fullname($pmuser), $pmclass->idnumber, $pmcourse->name);
        $text = str_replace($search, $replace, $text);

        $eventlog = new Object();
        $eventlog->event = 'class_notstarted';
        $eventlog->instance = $student->classid;
        $eventlog->fromuserid = $user->id;

        if ($sendtouser) {
            $message->send_notification($text, $user, null, $eventlog);
        }

        $users = array();

        if ($sendtorole) {
            /// Get all users with the notify_classnotstart capability.
            if ($roleusers = get_users_by_capability($context, 'elis/program:notify_classnotstart')) {
                $users = $users + $roleusers;
            }
        }

        if ($sendtosupervisor) {
            /// Get parent-context users.
            if ($supervisors = pm_get_users_by_capability('user', $student->userid, 'elis/program:notify_classnotstart')) {
                $users = $users + $supervisors;
            }
        }

        // Send notifications to any users who need to receive them.
        foreach ($users as $touser) {
            $message->send_notification($text, $touser, $user, $eventlog);
        }

        return true;
    }

    /**
     * Function to handle class not completed events.
     *
     * @param   student  $student  The class enrolment / student object who is "not completed"
     * @uses    $CFG
     * @uses    $DB
     * @return  boolean            TRUE is successful, otherwise FALSE
     */

    public static function class_notcompleted_handler($student) {
        global $CFG, $DB;
        require_once elispm::lib('notifications.php');

        /// Does the user receive a notification?
        $sendtouser = elis::$config->elis_program->notify_classnotcompleted_user;
        $sendtorole = elis::$config->elis_program->notify_classnotcompleted_role;
        $sendtosupervisor = elis::$config->elis_program->notify_classnotcompleted_supervisor;

        /// If nobody receives a notification, we're done.
        if (!$sendtouser && !$sendtorole && !$sendtosupervisor) {
            return true;
        }

        if (!empty($student->moodlecourseid)) {
            if (!($context = get_context_instance(CONTEXT_COURSE, $student->moodlecourseid))) {
                if (in_cron()) {
                    mtrace(get_string('invalidcontext'));
                } else {
                    debugging(get_string('invalidcontext'));
                }
                return true;
            }
        } else {
            $context = get_system_context();
        }

        $message = new notification();

        /// Set up the text of the message
        $text = empty(elis::$config->elis_program->notify_classnotcompleted_message) ?
                    get_string('notifyclassnotcompletedmessagedef', self::LANG_FILE) :
                    elis::$config->elis_program->notify_classnotcompleted_message;
        $search = array('%%userenrolname%%', '%%classname%%', '%%coursename%%');
        $pmuser = $DB->get_record(user::TABLE, array('id' => $student->userid));
        $user = new user($pmuser);
        // Get course info
        $pmcourse = $DB->get_record(course::TABLE, array('id' => $student->courseid));
        $pmclass = $DB->get_record(pmclass::TABLE, array('id' => $student->classid));

        $replace = array(fullname($pmuser), $pmclass->idnumber, $pmcourse->name);
        $text = str_replace($search, $replace, $text);

        $eventlog = new Object();
        $eventlog->event = 'class_notcompleted';
        $eventlog->instance = $student->classid;
        $eventlog->fromuserid = $user->id;
        if ($sendtouser) {
            $message->send_notification($text, $user, null, $eventlog);
        }

        $users = array();

        if ($sendtorole) {
            /// Get all users with the notify_classnotcomplete capability.
            if ($roleusers = get_users_by_capability($context, 'elis/program:notify_classnotcomplete')) {
                $users = $users + $roleusers;
            }
        }

        if ($sendtosupervisor) {
            /// Get parent-context users.
            if ($supervisors = pm_get_users_by_capability('user', $user->id, 'elis/program:notify_classnotcomplete')) {
                $users = $users + $supervisors;
            }
        }

        // Send notifications to any users who need to receive them.
        foreach ($users as $touser) {
            $message->send_notification($text, $touser, $user, $eventlog);
        }

        return true;
    }

    /**
     * Determines whether the current user is allowed to create, edit, and delete associations
     * between a user and a class
     *
     * @param    int      $userid    The id of the user being associated to the class
     * @param    int      $classid   The id of the class we are associating the user to
     * @uses     $DB
     * @uses     $USER;
     * @return   boolean             True if the current user has the required permissions, otherwise false
     */
    public static function can_manage_assoc($userid, $classid) {
        global $DB, $USER;

        // TODO: Ugly, this needs to be overhauled
        $cpage = new pmclasspage();

        if (!pmclasspage::can_enrol_into_class($classid)) {
            //the users who satisfty this condition are a superset of those who can manage associations
            return false;
        } else if ($cpage->_has_capability('elis/program:class_enrol', $classid)) {
            //current user has the direct capability
            return true;
        }

        //get the context for the "indirect" capability
        $context = pm_context_set::for_user_with_capability('cluster', 'elis/program:class_enrol_userset_user', $USER->id);

        $allowed_clusters = array();
        $allowed_clusters = pmclass::get_allowed_clusters($classid);

        //query to get users associated to at least one enabling cluster
        $cluster_select = '';
        if(empty($allowed_clusters)) {
            $cluster_select = '0=1';
        } else {
            $cluster_select = 'clusterid IN (' . implode(',', $allowed_clusters) . ')';
        }
        $select = "userid = ? AND {$cluster_select}";

        //user just needs to be in one of the possible clusters
        if($DB->record_exists_select(clusterassignment::TABLE, $select, array($userid))) {
            return true;
        }

        return false;
    }
}

class unsatisfied_prerequisites_exception extends Exception {
    public function __construct(student $stu) {
        parent::__construct("{$stu->users->fullname()} ({$stu->users->idnumber}) has one or more unsatisfied prerequisites for course description {$stu->pmclass->course->idnumber}");
    }
}

class pmclass_enrolment_limit_validation_exception extends Exception {
    public function __construct(pmclass $pmclass) {
        parent::__construct("Enrolment limit of {$pmclass->maxstudents} exceeded for class instance {$pmclass->course->idnumber}:{$pmclass->idnumber}");
    }
}

class student_grade extends elis_data_object {
    const TABLE = GRDTABLE;
    const LANG_FILE = 'elis_program';

    var $verbose_name = 'student_grade'; // TBD

    static $associations = array(
        'users'   => array('class' => 'user',
                           'idfield' => 'userid'),
        'pmclass' => array('class' => 'pmclass',
                           'idfield' => 'classid')
    );

/*
    var $id;                // INT - The data id if in the database.
    var $classid;           // INT - The class ID.
    var $userid;            // INT - The user ID.
    var $completionid;      // INT - Status code for completion.
    var $grade;             // INT - Student grade.
    var $locked;            // INT - Grade locked.
    var $timegraded;        // INT - The time graded.
    var $timemodified;      // INT - The time changed.

    var $_dbloaded;    // BOOLEAN - True if loaded from database.
*/

    private $form_url = null;  //moodle_url object

    protected $_dbfield_classid;
    protected $_dbfield_userid;
    protected $_dbfield_completionid;
    protected $_dbfield_grade;
    protected $_dbfield_locked;
    protected $_dbfield_timegraded;
    protected $_dbfield_timemodified;

/////////////////////////////////////////////////////////////////////
//                                                                 //
//  STANDARD FUNCTIONS:                                            //
//                                                                 //
/////////////////////////////////////////////////////////////////////

    function save() {
        // ELIS-3722 -- We need to prevent duplicate records when adding new student LO grade records
        if ($this->_dbfield_id !== parent::$_unset || ($this->_dbfield_id == parent::$_unset && !$this->duplicate_check())) {
            parent::save();
        } else {
            //debugging('student_grade::save() - LO grade already saved!', DEBUG_DEVELOPER);
        }

    }

    public static function delete_for_class($id) {
        global $DB;
        return $DB->delete_records(student_grade::TABLE, array('classid' => $id));
    }

    public static function delete_for_user($id) {
        global $DB;
        return $DB->delete_records(student_grade::TABLE, array('userid' => $id));
    }

    public static function delete_for_user_and_class($userid, $classid) {
        global $DB;
        return $DB->delete_records(student_grade::TABLE, array('userid' => $userid, 'classid' => $classid));
    }

/////////////////////////////////////////////////////////////////////
//                                                                 //
//  FORM FUNCTIONS:                                                //
//                                                                 //
/////////////////////////////////////////////////////////////////////

    /**
     * Return the HTML to edit a specific student.
     * This could be extended to allow for application specific editing, for example
     * a Moodle interface to its formslib.
     *
     * @param $formid string A suffix to put on all 'id' and index for all 'name' attributes.
     *                       This should be unique if being used more than once in a form.
     * @param $extraclass string Any extra class information to add to the output.
     * @uses $CFG
     * @uses $OUTPUT
     * @return string The form HTML, without the form.
     */
    function edit_form_html($classid, $type = '', $sort = 'name', $dir = 'ASC', $page = 0,
                            $perpage = 0, $namesearch = '', $alpha = '') {
        global $CFG, $OUTPUT;

        $output = '';
        ob_start();

        $columns = array( // TBD
            'grade'      => array('header' => get_string('grade', self::LANG_FILE),
                                  'display_function' => 'htmltab_display_function'),
            'locked'     => array('header' => get_string('student_locked', self::LANG_FILE),
                                  'display_function' => 'htmltab_display_function'),
            'timegraded' => array('header' => get_string('date_graded', self::LANG_FILE),
                                  'display_function' => 'htmltab_display_function')
        );

        if ($dir !== 'DESC') {
            $dir = 'ASC';
        }
        if (isset($columns[$sort])) {
            $columns[$sort]['sortable'] = $dir;
        } else {
            $sort = 'grade'; // TBD
            $columns[$sort]['sortable'] = $dir;
        }
        //$table->width = "100%"; // TBD

        $newarr = array();
        $tabobj = new stdClass;
        foreach ($columns as $column => $cdesc) {
            switch ($column) {
                case 'timegraded':
                    $tabobj->{$column} = cm_print_date_selector('users[' . $user->id . '][startday]',
                                             'users[' . $user->id . '][startmonth]',
                                             'users[' . $user->id . '][startyear]',
                                             $this->timegraded, true);
                    break;

                case 'grade':
                    $tabobj->{$column} = '<input type="text" name="users[' . $user->id . '][grade]" ' .
                                'value="' . $this->grade . '" size="5" />';
                    break;

                case 'locked':
                    $tabobj->{$column} = '<input type="checkbox" name="users[' . $user->id . '][locked]" ' .
                                'value="1" '.($this->locked?'checked="checked"':'').'/>';
                    break;

                default:
                    $tabobj->{$column} = '';
                    break;
            }
            //$table->data[] = $newarr;
        }
        $newarr[] = $tabobj;
        // TBD: student_table() ???
        $table = new display_table($newarr, $columns, $this->get_base_url());

        if (empty($this->id)) {
            // TBD: move up and add pmalphabox() and pmshowmatches() ???
            pmsearchbox(null, 'search', 'get', get_string('show_all_users', self::LANG_FILE),
               '<input type="radio" name="stype" value="student" '.
                 (($type == 'student') ? ' checked' : '') .'/> '. get_string('students', self::LANG_FILE) .
               ' <input type="radio" name="stype" value="instructor" '.
                 (($type == 'instructor') ? ' checked' : '') .'/> '. get_string('instructors', self::LANG_FILE) .
               ' <input type="radio" name="stype" vale="" ' . (($type == '') ? ' checked' : '') . '/> '. get_string('all') .' ');

            echo '<form method="post" action="index.php?s=stu&amp;section=curr&amp;class=' . $classid . '" >'."\n";
            echo '<input type="hidden" name="action" value="savenew" />'."\n";
        } else {
            echo '<form method="post" action="index.php?s=stu&amp;section=curr&amp;class=' . $classid . '" >'."\n";
            echo '<input type="hidden" name="action" value="update" />'."\n";
            echo '<input type="hidden" name="association_id" value="' . $this->id . '" />' . "\n";
            echo '<input type="hidden" name="id" value="' . $this->classid . '" />' . "\n";
            echo '<input type="hidden" name="userid" value="' . $this->userid . '" />' . "\n";
        }

        if (!empty($newarr)) { // TBD: $newarr or $table?
            echo $table->get_html();
        }

        if (empty($this->id)) {
            echo '<br /><input type="submit" value="' . get_string('add_grade', self::LANG_FILE) . '">'."\n";
        } else {
            echo '<br /><input type="submit" value="' . get_string('update_grade', self::LANG_FILE) . '">'."\n";
        }
        echo '</form>'."\n";

        $output = ob_get_contents();
        ob_end_clean();

        return $output;
    }

/////////////////////////////////////////////////////////////////////
//                                                                 //
//  DATA FUNCTIONS:                                                //
//                                                                 //
/////////////////////////////////////////////////////////////////////

    /**
     * Check for a duplicate record when doing an insert.
     *
     * @param object $record The record we want to insert.
     */
    function duplicate_check($record=null) {
        if(empty($record)) {
            $record = $this;
        }

        $params = array(
            'classid'      => $record->classid,
            'userid'       => $record->userid,
            'completionid' => $record->completionid
        );

        if ($this->_db->record_exists(student_grade::TABLE, $params)) {
            return true;
        }
        return false;
    }

    public function set_from_data($data) {
        $this->_load_data_from_record($data, true);
    }

}

/// Non-class supporting functions. (These may be able to replaced by a generic container/listing class)

/**
 * Gets a student listing with specific sort and other filters.
 *
 * @param int $classid The class ID.
 * @param string $sort Field to sort on.
 * @param string $dir Direction of sort.
 * @param int $startrec Record number to start at.
 * @param int $perpage Number of records per page.
 * @param string $namesearch Search string for student name.
 * @param string $alpha Start initial of student name filter.
 * @uses $DB
 * @return recordset Returned records.
 */
function student_get_listing($classid, $sort='name', $dir='ASC', $startrec=0, $perpage=0, $namesearch='',
                             $alpha='') {
    global $DB;
    $params = array();
    $FULLNAME = $DB->sql_concat('usr.firstname', "' '", 'usr.lastname');
    $FULLNAME_LIKE = $DB->sql_like($FULLNAME, ':name_like', FALSE); // 'name' breaks
    $IDNUMBER_LIKE = $DB->sql_like('usr.idnumber', ':id_like', FALSE);
    $LASTNAME_STARTSWITH = $DB->sql_like('usr.lastname', ':lastname_startswith', FALSE);

    $select  = 'SELECT stu.* ';
    $select .= ', ' . $FULLNAME . ' as name, usr.idnumber ';
//    $select .= ', ' . $FULLNAME . ' as name, usr.type as description ';
    $tables  = 'FROM {'. student::TABLE .'} stu ';
    $join    = 'LEFT JOIN {'. user::TABLE .'} usr ';
    $on      = 'ON stu.userid = usr.id ';
    $where   = 'stu.classid = :clsid ';
    $params['clsid'] = $classid;

    if (!empty($namesearch)) {
        $namesearch = trim($namesearch);
        $where .= ' AND (('. $FULLNAME_LIKE .') OR ('. $IDNUMBER_LIKE .')) ';
        $params['name_like'] = "%{$namesearch}%";
        $params['id_like']   = "%{$namesearch}%";
    }

    if ($alpha) {
        $where .= ' AND ('. $LASTNAME_STARTSWITH .') ';
        $params['lastname_startswith'] = "{$alpha}%";
    }

    if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
        $where .= ' AND usr.inactive = 0 ';
    }

    $where = 'WHERE '. $where .' ';

    if ($sort) {
        if ($sort == 'name') { // TBV: ELIS-2772
            //$sort = $FULLNAME;
            $sort = "ORDER BY usr.lastname {$dir}, usr.firstname {$dir} ";
        } else {
            $sort = 'ORDER BY '. $sort .' '. $dir .' ';
        }
    }

    $sql = $select.$tables.$join.$on.$where.$sort;
    return $DB->get_recordset_sql($sql, $params, $startrec, $perpage);
}

/**
 * Count the number of students for this class.
 *
 * @uses $DB
 * @param int $classid The class ID.
 */
function student_count_records($classid, $namesearch = '', $alpha = '') {
    global $DB;
    $params = array();
    $FULLNAME = $DB->sql_concat('usr.firstname', "' '", 'usr.lastname');
    $FULLNAME_LIKE = $DB->sql_like($FULLNAME, ':name_like', FALSE);
    $LASTNAME_STARTSWITH = $DB->sql_like('usr.lastname', ':lastname_startswith', FALSE);

    $select  = 'SELECT COUNT(stu.id) ';
    $tables  = 'FROM {'. student::TABLE .'} stu ';
    $join    = 'LEFT JOIN {'. user::TABLE .'} usr ';
    $on      = 'ON stu.userid = usr.id ';
    $where   = 'stu.classid = :clsid ';
    $params['clsid'] =  $classid;

    if (!empty($namesearch)) {
        $namesearch = trim($namesearch);
        $where .= ' AND ('. $FULLNAME_LIKE .') ';
        $params['name_like'] = "%{$namesearch}%";
    }

    if ($alpha) {
        $where .= ' AND ('. $LASTNAME_STARTSWITH .') ';
        $params['lastname_startswith'] = "{$alpha}%";
    }

    if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
        $where .= ' AND usr.inactive = 0 ';
    }

    $where = "WHERE $where ";

    $sql = $select . $tables . $join . $on . $where;
    return $DB->count_records_sql($sql, $params);
}

/**
 * Get a full list of the classes that a student is enrolled in.
 *
 * @param int $userid The user ID to get classes for.
 * @param int $curid  Optional curriculum ID to limit classes to.
 * @uses $DB
 * @return array An array of class and student enrolment data.
 * TBD: double-check tables!!!
 */
function student_get_student_classes($userid, $curid = 0) {
    global $DB;

    $params = array();
    if (empty($curid)) {
        $sql = 'SELECT cls.*, stu.enrolmenttime, stu.completetime, stu.completestatusid
                FROM {'. student::TABLE .'} stu
                INNER JOIN {'. pmclass::TABLE .'} cls ON stu.classid = cls.id
                WHERE stu.userid = ? ';
                $params[] = $userid;
    } else {
        $sql = 'SELECT cls.*, stu.enrolmenttime, stu.completetime, stu.completestatusid
                FROM {'. student::TABLE .'} stu
                INNER JOIN {'. pmclass::TABLE .'} cls ON stu.classid = cls.id
                INNER JOIN {'. course::TABLE .'} curcrs ON cls.courseid = curcrs.courseid
                WHERE stu.userid = ?
                AND curcrs.curriculumid = ? ';
                $params[] = $userid;
                $params[] = $curid;
    }
    return $DB->get_records_sql($sql, $params);
}

/**
 * Attempt to get the class information about a class that a student is enrolled
 * in for a specific course in the system.
 *
 * @param int $crsid The course ID
 * @param int $userid The PM user id whose enrolments we are considering
 * @param string $sort The field to sort on
 * @param string $dir The sort direction
 * @uses $DB
 * @return recordset
 */
function student_get_class_from_course($crsid, $userid, $sort = 'cls.idnumber', $dir = 'ASC') {
    global $DB;

    $sql = 'SELECT cls.*, stu.enrolmenttime, stu.completetime, stu.completestatusid, stu.grade
            FROM {'. student::TABLE .'} stu
            INNER JOIN {'. pmclass::TABLE .'} cls ON stu.classid = cls.id
            WHERE stu.userid = ?
            AND cls.courseid = ?
            ORDER BY '.$sort.' '.$dir;
    return $DB->get_recordset_sql($sql, array($userid, $crsid));
}
