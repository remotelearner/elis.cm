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
require_once elispm::lib('data/curriculum.class.php');
require_once elispm::lib('data/curriculumcourse.class.php');
require_once elispm::lib('data/pmclass.class.php');

class course extends data_object_with_custom_fields {
    const TABLE = 'crlm_course';

    static $config_default_prefix = 'crsdft';

    static $associations = array(
        'pmclass' => array(
            'class' => 'pmclass',
            'foreignidfield' => 'courseid'
        ),
        'coursetemplate' => array(
            'class' => 'coursetemplate',
            'foreignidfield' => 'courseid'
        ),
        'coursecompletion' => array(
            'class' => 'coursecompletion',
            'foreignidfield' => 'courseid'
        ),
        'coursecorequisite' => array(
            'class' => 'coursecorequisite',
            'foreignidfield' => 'courseid'
        ),
        'courseprerequisite' => array(
            'class' => 'courseprerequisite',
            'foreignidfield' => 'courseid'
        ),
        'curriculumcourse' => array(
            'class' => 'curriculumcourse',
            'foreignidfield' => 'courseid'
        ),
        'trackclass' => array(
            'class' => 'trackassignment',
            'foreignidfield' => 'courseid'
        ),
    );

    protected $_dbfield_name;
    protected $_dbfield_code;
    protected $_dbfield_idnumber;
    protected $_dbfield_syllabus;
    protected $_dbfield_documents;
    protected $_dbfield_lengthdescription;
    protected $_dbfield_length;
    protected $_dbfield_credits;
    protected $_dbfield_completion_grade;
    protected $_dbfield_environmentid;
    protected $_dbfield_cost;
    protected $_dbfield_timecreated;
    protected $_dbfield_timemodified;
    protected $_dbfield_version;

    private $location;
    private $templateclass;
    private $form_url = null;

    var $courseid;
    var $_dbloaded = true;

    static $delete_is_complex = true;

    protected function get_field_context_level() {
        return CONTEXT_ELIS_COURSE;
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

    public function seturl($url = null, $action = array()) {
        if(!($url instanceof moodle_url)) {
            $url = new moodle_url($url, $action);
        }

        $this->form_url = $url;
    }

    public function create_edit_form($formid='', $rows=2, $cols=40) {
        $configdata = array();
        $configdata['formid'] = $formid;
        $configdata['rows'] = $rows;
        $configdata['cols'] = $cols;
        $configdata['curassignment'] = $this->get_assigned_curricula();
        $configdata['id'] = $this->id;

        if($this->course_exists()) {
            $crsForm = new cmCourseEditForm(null, $configdata);
        } else {
            $crsForm = new cmCourseAddForm(null, $configdata);
        }

        if (!empty($this->id)) {
            $template = $this->coursetemplate->current();
            $course = $this->_db->get_record('course', array('id'=>$template->location));

            if (!empty($course)) {
                $this->locationlabel = $course->fullname . ' ' . $course->shortname;
            }
        }

        $crsForm->set_data($this);

        return $crsForm;
    }


    /**
     * Return the HTML to edit a specific course.
     * This could be extended to allow for application specific editing, for example
     * a Moodle interface to its formslib.
     *
     * @param $formid string A suffix to put on all 'id' and index for all 'name' attributes.
     *                       This should be unique if being used more than once in a form.
     * @param $extraclass string Any extra class information to add to the output.
     *
     * @return string The form HTML, without the form.
     */
    public function edit_form_html($formid='', $rows='2', $cols='40') {
        $output = '';

        if (!empty($this->id)) {
            $template = $this->coursetemplate->current();
            $output .= $this->add_js_function($template->location);
        } else {
            $output .= $this->add_js_function();
        }

        $crsForm = $this->create_edit_form($formid, $rows, $cols);

        $crsForm->focus();

        ob_start();
        $crsForm->display();
        $output .= ob_get_contents();
        ob_end_clean();

        return $output;
    }

    public function create_completion_form($elemid=0, $formid='', $extraclass='', $rows='2', $cols='40') {
        if ($elemid != 0) {
            $elem = $this->_db->get_record(coursecompletion::TABLE, array('id'=>$elemid));
        } else {
            $elem = new Object();
            $elem->idnumber = '';
            $elem->name = '';
            $elem->description = '';
            $elem->completion_grade = 0;
            $elem->required = 1;
        }

        $config_data = array();

        $config_data['course'] = $this;
        $config_data['elem'] = $elem;
        $config_data['elemid'] = $elemid;
        $config_data['formid'] = $formid;
        $config_data['rows'] = $rows;
        $config_data['cols'] = $cols;
        $config_data['id'] = $this->id;

        return new completionform($this->form_url, $config_data);
    }

    /**
     * Return the HTML to edit a course's completion elements.
     * This could be extended to allow for application specific editing, for example
     * a Moodle interface to its formslib.
     *
     * @param $formid string A suffix to put on all 'id' and index for all 'name' attributes.
     *                       This should be unique if being used more than once in a form.
     * @param $extraclass string Any extra class information to add to the output.
     *
     * @return string The form HTML, without the form.
     */
    function edit_completion_form_html($elemid=0, $formid='', $extraclass='', $rows='2', $cols='40') {
        $completionForm = $this->create_completion_form($elemid);

        ob_start();
        $completionForm->display();
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
     * Get a list of the course completion elements for the current course.
     *
     * @param none
     * @return recordset The list of course IDs.
     */
    function get_completion_elements() {
        if (!$this->id) {
            return array();
        }

        return $this->_db->get_recordset(coursecompletion::TABLE, array('courseid'=>$this->id));
    }

    /*
     * @return int The Course id
     */
    function get_course_id() {
        return $this->id;
    }

    /*
     * Returns an aggregate of enrolment completion statuses for all classes created from this course.
     */
    public function get_completion_counts() {
        $sql = 'SELECT cce.completestatusid status, COUNT(cce.completestatusid) count
                FROM {'. student::TABLE .'} cce
                JOIN {'. user::TABLE .'} usr ON cce.userid = usr.id
                INNER JOIN {'. pmclass::TABLE .'} cc ON cc.id = cce.classid
                INNER JOIN {'. course::TABLE .'} cco ON cco.id = cc.courseid
                WHERE cco.id = ? ';

        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $sql .= 'AND usr.inactive = 0 ';
        }
        $sql .= 'GROUP BY cce.completestatusid';

        $ret = array(STUSTATUS_NOTCOMPLETE=>0, STUSTATUS_FAILED=>0, STUSTATUS_PASSED=>0);

        $rows = $this->_db->get_recordset_sql($sql, array($this->id));
        foreach($rows as $row) {
            // We add the counts to the existing array, which should be as good as an assignment
            // because we never have duplicate statuses.  Of course, stranger things have happened.

            $ret[$row->status] += $row->count;
        }
        unset($rows);

        return $ret;
    }

    /**
     * Save an element.
     *
     * @param none
     * @return array The list of course IDs.
     */
    function save_completion_element($elemrecord) {
        if (!$this->id || !$this->_dbloaded) {
            return false;
        }

        $elemrecord->courseid = $this->id;
        if (empty($elemrecord->id)) {
            return $this->_db->insert_record(coursecompletion::TABLE, $elemrecord);
        } else {
            return $this->_db->update_record(coursecompletion::TABLE, $elemrecord);
        }
    }

    /**
     * Delete an element.
     *
     * @param none
     * @return array The list of course IDs.
     */
    function delete_completion_element($elemid) {
        if (!$this->id || !$this->_dbloaded) {
            return false;
        }

        return $this->_db->delete_records(coursecompletion::TABLE, array('id'=>$elemid));
    }

    /**
     * Retrieve the curricula that are affiliated with this course
     *
     * @param none
     * @return array The list of curricula IDs.
     */
    function get_assigned_curricula() {
        $assigned = array();

        if (!$this->id) {
            return false;
        }

        $result = $this->_db->get_recordset(curriculumcourse::TABLE, array('courseid'=>$this->id));
        foreach ($result as $data) {
            $assigned[$data->curriculumid] = $data->id;
        }
        unset($result);

        return $assigned;
    }

    /**
     * Add a course to a curricula
     *
     * @param array $curriculums array value is the curriculum id
     * @return nothing
     * TODO: need to add some error checking
     */
    function add_course_to_curricula($curriculums = array()) {
        $curcourse = new curriculumcourse();

        // Add course to curricula (one or more)
        $curcrsrecord = array();
        $curcrsrecord['id']           = 0;
        $curcrsrecord['courseid']     = $this->id;
        $curcrsrecord['required']     = 0;
        $curcrsrecord['frequency']    = 0;
        $curcrsrecord['timeperiod']   = key($curcourse->timeperiod_values);
        $curcrsrecord['position']     = 0;

        if (is_array($curriculums)) {
            foreach ($curriculums as $curr) {
              $curcrsrecord['curriculumid'] = $curr;
              $newcurcrs = new curriculumcourse($curcrsrecord);
              $status = $newcurcrs->save();
              if ($status !== true) {
                  if (!empty($status->message)) {
                      //$output .= cm_error('Record not created. Reason: '.$status->message);
                  } else {
                      //echo cm_error('Record not created.');
                  }
              } else {
                  //echo 'New record created.';
              }
            }
        }
    }

    /**
     * Remove course curriculum assignments
     */
    function remove_course_curricula() {
        $currassigned = $this->get_assigned_curricula();

        foreach ($currassigned as $currid => $rowid) {
                // Remove
                $curcrs = new curriculumcourse($rowid);
                $curcrs->data_delete_record();
        }
    }

    function add_js_function($id = 0) {
        $id = empty($id) ? 0 : $id;

        return '<script language=javascript>
                    function openNewWindow() {
                        var clsTemplate = document.getElementById("id_templateclass");
                        var classname = clsTemplate.value;
                        var x = window.open(\'coursetemplatepage.php?class=\' + classname + \'&selected=\' + '.$id.', \'newWindow\', \'height=500,width=500,resizable,scrollbars\');
                    }

                    function cleartext() {
                        var crslabel = document.getElementById("id_locationlabel");
                        crslabel.value = "";

                        var location = document.getElementById("id_location");
                        location.value = "";
                    }
                </script>';
    }

    function course_exists($id=null) {
        if(empty($id)){
            return $this->_db->record_exists(course::TABLE, array('id'=>$this->id));
        } else {
            return $this->_db->record_exists(course::TABLE, array('id'=>$id));
        }
    }

    /////////////////////////////////////////////////////////////////////
    //                                                                 //
    //  STATIC FUNCTIONS:                                              //
    //    These functions can be used without instatiating an object.  //
    //    Usage: student::[function_name([args])]                      //
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
     * Check for any course nags that need to be handled.
     *
     */
    public static function check_for_nags() {
        global $CFG, $DB;

        $sendtouser =       elis::$config->elis_program->notify_courserecurrence_user;
        $sendtorole =       elis::$config->elis_program->notify_courserecurrence_role;
        $sendtosupervisor = elis::$config->elis_program->notify_courserecurrence_supervisor;

        /// If nobody receives a notification, we're done.
        if (!$sendtouser && !$sendtorole && !$sendtosupervisor) {
            return true;
        }

        /// Course Recurrence:
        /// A course needs to recur if,
        ///     - The user has previously taken a class of this course,
        ///     - The frequency time has passed from when they last completed a class of this course

        /// currenttime > completetime + (frequency * timeperiod_in_seconds)

        mtrace("Checking course notifications<br />\n");

        /// Get all curriculum courses with a frequency greater than zero.
        /// LEFT JOIN Moodle course and Moodle user info, since they may not have records.
        /// LEFT JOIN notification log where there isn't a notification record for the course and user and 'class_notstarted'.
        $day   = 60 * 60 * 24;
        $week  = $day * 7;
        $month = $day * 30;
        $year  = $day * 365;
        $timenow = time();

        /// Enrolment id will be the one that won't repeat, so it will be the unique index.
        $select = 'SELECT cce.id, ccc.frequency, ccc.timeperiod, ' .
                  'cc.name as coursename, ' .
                  'c.name as curriculumname, ' .
                  'cu.id as userid, cu.idnumber as useridnumber, cu.firstname as firstname, cu.lastname as lastname, ' .
                  'cce.id as enrolmentid, cce.completetime as completetime, ' .
                  'u.id as muserid ';
        $from   = 'FROM {'.curriculumcourse::TABLE.'} ccc ';
        $join   = 'INNER JOIN {'.course::TABLE.'} cc ON cc.id = ccc.courseid ' .
                  'INNER JOIN {'.curriculum::TABLE.'} c ON c.id = ccc.curriculumid ' .
                  'INNER JOIN {'.curriculumstudent::TABLE.'} cca ON cca.curriculumid = c.id ' .
                  'INNER JOIN {'.user::TABLE.'} cu ON cu.id = cca.userid ' .
                  'INNER JOIN {'.pmclass::TABLE.'} ccl ON ccl.courseid = cc.id ' .
                  'INNER JOIN {'.student::TABLE.'} cce ON cce.classid = ccl.id ' .
                  'LEFT JOIN {user} u ON u.idnumber = cu.idnumber ' .
                  'LEFT JOIN {'.notificationlog::TABLE.'} cnl ON cnl.fromuserid = cu.id AND cnl.instance = cce.id AND cnl.event = \'course_recurrence\' ';
        $where  = 'WHERE (cce.completestatusid != '.STUSTATUS_NOTCOMPLETE.') AND (ccc.frequency > 0) '.
                  'AND ((cce.completetime + ' .
            /// This construct is to select the number of seconds to add to determine the delta frequency based on the timeperiod
                  '(CASE ccc.timeperiod WHEN \'year\' THEN (ccc.frequency * ?)
                                        WHEN \'month\' THEN (ccc.frequency * ?)
                                        WHEN \'week\' THEN (ccc.frequency * ?)
                                        WHEN \'day\' THEN (ccc.frequency * ?)
                                        ELSE 0 END)' .
            ///
                  ') < ?) AND (cnl.id IS NULL) ';
        $order  = 'ORDER BY cce.id ASC ';
        $sql    = $select . $from . $join . $where . $order;

        $usertempl = new user(); // used just for its properties.

        $rs = $DB->get_recordset_sql($sql, array($year,$month,$week,$day,$timenow));
        if ($rs) {
            foreach ($rs as $rec) {
                mtrace("Triggering course_recurrence event.\n");
                events_trigger('course_recurrence', $rec);
            }
        }
        return true;
    }

    /*
     * ---------------------------------------------------------------------------------------
     * EVENT HANDLER FUNCTIONS:
     *
     * These functions handle specific student events.
     *
     */

    /**
     * Function to handle course recurrence events.
     *
     * @param   user      $user  CM user object representing the user in the course
     *
     * @return  boolean          TRUE is successful, otherwise FALSE
     */

    public static function course_recurrence_handler($user) {
        global $DB;

        require_once elispm::lib('notifications.php');

        /// Does the user receive a notification?
        $sendtouser       = elis::$config->elis_program->notify_courserecurrence_user;
        $sendtorole       = elis::$config->elis_program->notify_courserecurrence_role;
        $sendtosupervisor = elis::$config->elis_program->notify_courserecurrence_supervisor;

        /// If nobody receives a notification, we're done.
        if (!$sendtouser && !$sendtorole && !$sendtosupervisor) {
            return true;
        }

        $context = get_system_context();

        /// Make sure this is a valid user.
        $enroluser = new user($user->userid);
        // Due to lazy loading, we need to pre-load this object
        $enroluser->load();
        if (empty($enroluser->id)) {
            if (in_cron()) {
                mtrace(get_string('nouser', 'elis_program'));
            } else {
                print_error('nouser', 'elis_program');
            }
            return true;
        }

        /// Set up the text of the message
        $message = new notification();

        $text = empty(elis::$config->elis_program->notify_courserecurrence_message) ?
                    get_string('notifycourserecurrencemessagedef', 'elis_program') :
                    elis::$config->elis_program->notify_courserecurrence_message;
        $pmuser = $DB->get_record(user::TABLE, array('id' => $user->userid));
        $student = new user($pmuser);

        $search = array('%%userenrolname%%', '%%coursename%%');
        $replace = array(fullname($user), $user->coursename);
        $text = str_replace($search, $replace, $text);

        $eventlog = new Object();
        $eventlog->event = 'course_recurrence';
        $eventlog->instance = $user->enrolmentid;
        $eventlog->fromuserid = $student->id;
        if ($sendtouser) {
            $message->send_notification($text, $student, null, $eventlog);
        }

        $users = array();

        if ($sendtorole) {
            /// Get all users with the notify_courserecurrence capability.
            if ($roleusers = get_users_by_capability($context, 'elis/program:notify_courserecurrence')) {
                $users = $users + $roleusers;
            }
        }

        if ($sendtosupervisor) {
            /// Get parent-context users.
            if ($supervisors = pm_get_users_by_capability('user', $student->id, 'elis/program:notify_courserecurrence')) {
                $users = $users + $supervisors;
            }
        }

        foreach ($users as $u) {
            $message->send_notification($text, $u, $enroluser, $eventlog);
        }

        return true;
    }

	public function delete() {

        //delete associated classes
        $filter = new field_filter('courseid', $this->id);
        pmclass::delete_records($filter, $this->_db);

        //clean up associated records
        curriculumcourse::delete_records($filter, $this->_db);
        coursetemplate::delete_records($filter, $this->_db);

        parent::delete();

        $context = context_elis_course::instance($this->id);
        $context->delete();
    }

    public static function find($filter=null, array $sort=array(), $limitfrom=0, $limitnum=0, moodle_database $db=null) {
        return parent::find($filter, $sort, $limitfrom, $limitnum, $db);
    }

    public function set_from_data($data) {
        if (isset($data->curriculum)) {
            $this->curriculum = $data->curriculum;
        }

        if (isset($data->location)) {
            $this->location = $data->location;
            $this->templateclass = $data->templateclass;
        }

        $this->_load_data_from_record($data, true);
    }

    static $validation_rules = array(
        'validate_name_not_empty',
        'validate_idnumber_not_empty',
        'validate_unique_idnumber'
    );

    function validate_name_not_empty() {
        return validate_not_empty($this, 'name');
    }

    function validate_idnumber_not_empty() {
        return validate_not_empty($this, 'idnumber');
    }

    function validate_unique_idnumber() {
        return validate_is_unique($this, array('idnumber'));
    }

    public function save() {
        parent::save();

        if(isset($this->curriculum)) {
            $this->add_course_to_curricula($this->curriculum);
        }

        // Add moodle course template
        if (isset($this->location)) {
            $template = $this->coursetemplate->current();
            $template->location           = $this->location;
            $template->templateclass      = $this->templateclass;
            $template->courseid           = $this->id;

            $template->save();
        } else {
            coursetemplate::delete_records(new field_filter('courseid', $this->id));
        }

        field_data::set_for_context_from_datarecord(CONTEXT_ELIS_COURSE, $this);
    }

    public function __toString() {
    	return $this->name;
    }

    static public function get_by_idnumber($idnumber) {
        global $DB;

        $retval = null;

        $course = $DB->get_record(course::TABLE, array('idnumber'=>$idnumber));

        if(!empty($course)) {
            $retval = new course($course->id);
        }

        return $retval;
    }

    /**
     * Clone a course.
     * @param array $options options for cloning.  Valid options are:
     * - 'classes': whether or not to clone classes (default: false)
     * - 'moodlecourses': whether or not to clone Moodle courses (if they were
     *   autocreated).  Values can be (default: "copyalways"):
     *   - "copyalways": always copy course
     *   - "copyautocreated": only copy autocreated courses
     *   - "autocreatenew": autocreate new courses from course template
     *   - "link": link to existing course
     * - 'targetcluster': the cluster id or cluster object (if any) to
     *   associate the clones with (default: none)
     * @return array array of array of object IDs created.  Key in outer array
     * is type of object (plural).  Key in inner array is original object ID,
     * value is new object ID.  Outer array also has an entry called 'errors',
     * which is an array of any errors encountered when duplicating the
     * object.
     */
    function duplicate(array $options) {
        require_once elispm::lib('data/pmclass.class.php');
        require_once elispm::lib('data/coursetemplate.class.php');

        $objs = array('errors' => array());
        if (isset($options['targetcluster'])) {
            $userset = $options['targetcluster'];
            if (!is_object($userset) || !is_a($userset, 'userset')) {
                $options['targetcluster'] = $userset = new userset($userset);
            }
        }

        // Due to lazy loading, we need to pre-load this object
        $this->load();

        // clone main course object
        $clone = new course($this);
        unset($clone->id);

        $idnumber = $clone->idnumber;
        $name = $clone->name;
        if (isset($userset)) {
            $to_append = ' - '. $userset->name;
            // if cluster specified, append cluster's name to course
            $idnumber = append_once($idnumber, $to_append,
                                    array('maxlength' => 95));
            $name = append_once($name, $to_append, array('maxlength' => 250));
        }

        //get a unique idnumber
        $clone->idnumber = generate_unique_identifier(course::TABLE, 'idnumber', $idnumber, array('idnumber' => $idnumber));

        if ($clone->idnumber != $idnumber) {
            //get the suffix appended and add it to the name
            $parts = explode('.', $clone->idnumber);
            $suffix = end($parts);
            $clone->name = $name.'.'.$suffix;
        } else {
            $clone->name = $name;
        }
        $clone->save();

        $objs['courses'] = array($this->id => $clone->id);
        $options['targetcourse'] = $clone->id;

        // copy completion elements
        $compelems = $this->get_completion_elements();
        foreach ($compelems as $compelem) {
            unset($compelem->id);
            $clone->save_completion_element($compelem);
        }
        unset($compelems);


        // copy template
        $template = $this->_db->get_record(coursetemplate::TABLE, array('courseid'=>$this->id));
        $template = new coursetemplate($template);
        unset($template->id);
        $template->courseid = $clone->id;
        $template->save();

        // copy the classes
        if (!empty($options['classes'])) {
            $classes = pmclass_get_record_by_courseid($this->id);
            if (!empty($classes)) {
                $objs['classes'] = array();
                foreach ($classes as $class) {
                    $class = new pmclass($class);
                    $rv = $class->duplicate($options);
                    if (isset($rv['errors']) && !empty($rv['errors'])) {
                        $objs['errors'] = array_merge($objs['errors'], $rv['errors']);
                    }
                    if (isset($rv['classes'])) {
                        $objs['classes'] = $objs['classes'] + $rv['classes'];
                    }
                }
            }
        }
        return $objs;
    }
}

/// Non-class supporting functions. (These may be able to replaced by a generic container/listing class)

/**
 * Gets a course listing with specific sort and other filters.
 *
 * @param string $sort Field to sort on.
 * @param string $dir Direction of sort.
 * @param int $startrec Record number to start at.
 * @param int $perpage Number of records per page.
 * @param string $namesearch Search string for course name.
 * @param string $descsearch Search string for course description.
 * @param string $alpha Start initial of course name filter.
 * @return object array Returned records.
 */

function course_get_listing($sort='crs.name', $dir='ASC', $startrec=0, $perpage=0, $namesearch='', $alpha='', $contexts=null) {
    global $DB;

    $select = 'SELECT crs.*, (SELECT COUNT(*) FROM {'.curriculumcourse::TABLE.'} WHERE courseid = crs.id ) as curricula ';
    $tables = 'FROM {'.course::TABLE.'} crs ';

    $where  = array();
    $params = array();

    if (!empty($namesearch)) {
        $namesearch = trim($namesearch);

        $name_like = $DB->sql_like('crs.name', '?', FALSE);
        $idnumber_like = $DB->sql_like('crs.idnumber', '?', FALSE);

        $where[] = "(($name_like) OR ($idnumber_like))";
        $params = array_merge($params, array("%$namesearch%", "%$namesearch%"));
    }

    if ($alpha) {
        $name_like = $DB->sql_like('crs.name', '?', FALSE);
        $where[] = '('.$name_like.')';
        $params[] = "$alpha%";
    }

    if ($contexts !== null) {
        $filter_object = $contexts->get_filter('id', 'course');
        $filter_sql = $filter_object->get_sql(false, 'crs');
        if (isset($filter_sql['where'])) {
            $where[] = $filter_sql['where'];
            $params = array_merge($params, $filter_sql['where_parameters']);
        }
    }

    if (!empty($where)) {
        $where = 'WHERE '.implode(' AND ',$where).' ';
    } else {
        $where = '';
    }

    if ($sort) {
        $sort = 'ORDER BY '.$sort .' '. $dir.' ';
    }

    $sql = $select.$tables.$where.$sort;

    return $DB->get_records_sql($sql, $params, $startrec, $perpage);
}

function course_count_records($namesearch = '', $alpha = '', $contexts = null) {
    global $DB;

    $where = array();
    $params = array();

    if (!empty($namesearch)) {
        $name_like     = $DB->sql_like('name', '?', FALSE);
        $idnumber_like = $DB->sql_like('idnumber', '?', FALSE);

        $where[] = "(($name_like) OR ($idnumber_like))";
        $params = array_merge($params, array("%$namesearch%", "%$namesearch%"));
    }

    if ($alpha) {
        $name_like = $DB->sql_like('name', '?', FALSE);
        $where[] = "($name_like)";
        $params[] = "$alpha%";
    }

    if ($contexts !== null) {
        $filter_object = $contexts->get_filter('id', 'course');
        $filter_sql = $filter_object->get_sql();
        if (isset($filter_sql['where'])) {
            $where[] = $filter_sql['where'];
            $params += $filter_sql['where_parameters'];
        }
    }

    $where = implode(' AND ', $where);

    return $DB->count_records_select(course::TABLE, $where, $params);
}

class coursecompletion extends elis_data_object {
    const TABLE = 'crlm_course_completion';

    protected $_dbfield_courseid;
    protected $_dbfield_idnumber;
    protected $_dbfield_name;
    protected $_dbfield_description;
    protected $_dbfield_completion_grade;
    protected $_dbfield_required;

    static $associations = array(
        'course' => array(
            'class' => 'course',
            'idfield' => 'courseid'
        ),
    );
}
