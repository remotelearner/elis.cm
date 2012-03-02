<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
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
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once CURMAN_DIRLOCATION . '/lib/datarecord.class.php';
require_once CURMAN_DIRLOCATION . '/lib/environment.class.php';
require_once CURMAN_DIRLOCATION . '/lib/curriculum.class.php';
require_once CURMAN_DIRLOCATION . '/lib/curriculumcourse.class.php';
require_once CURMAN_DIRLOCATION . '/lib/customfield.class.php';

define ('CRSTABLE', 'crlm_course');
define ('CRSCOMPTABLE', 'crlm_course_completion');


class course extends datarecord {
    static $config_default_prefix = 'crsdft';
/*
    var $id;            // INT - The data id if in the database.
    var $name;          // STRING - Textual name of the course.
    var $code;          // STRING - Small readable code for course.
    var $idnumber;      // STRING - Unique ID shared among systems.
    var $syllabus;      // STRING - Full description of course and contents.
    var $documents;     // STRING - URL of course documents location.
    var $lengthdescription; // STRING - Expected time to complete in plain language.
    var $length;        // INT - Expected time to completion in seconds.
    var $credits;       // STRING - Credits awarded upon completion - does not have to be numeric.
    var $completion_grade;  // INT - Required grade for pass completion.
    var $environmentid; // INT - Intended environment for this course.
    var $environment;   // OBJECT - Actual environment object associated with id.
    var $cost;          // STRING - Cost of course in currency.
    var $timecreated;   // INT - Timestamp.
    var $timemodified;  // INT - Timestamp.
    var $version;       // INT - The most recent version number (can be used to keep multiple versions).

    var $_dbloaded;     // BOOLEAN - True if loaded from database.
*/

    /**
     * Contructor.
     *
     * @param $coursedata int/object/array The data id of a data record or data elements to load manually.
     *
     */
    function course($coursedata = false) {
        parent::datarecord();

        $this->set_table(CRSTABLE);
        $this->add_property('id', 'int');
        $this->add_property('name', 'string', true);
        $this->add_property('code', 'string');
        $this->add_property('idnumber', 'string', true);
        $this->add_property('syllabus', 'string');
        $this->add_property('documents', 'string');
        $this->add_property('lengthdescription', 'string');
        $this->add_property('length', 'int');
        $this->add_property('credits', 'string');
        $this->add_property('completion_grade', 'int');
        $this->add_property('environmentid', 'int');
        $this->add_property('cost', 'string');
        $this->add_property('timecreated', 'int');
        $this->add_property('timemodified', 'int');
        $this->add_property('version', 'string');

        if (is_numeric($coursedata)) {
            $this->data_load_record($coursedata);
        } else if (is_array($coursedata)) {
            $this->data_load_array($coursedata);
        } else if (is_object($coursedata)) {
            $this->data_load_array(get_object_vars($coursedata));
        }

        if (!empty($this->environmentid)) {
            $this->environment = new environment($this->environmentid);
        }

        // FIXME: this should be done every time the template is updated, i.e. through getter/setter methods
        global $CURMAN;
        if (!empty($this->id)) {
            $template = new coursetemplate($this->id);
            $course = $CURMAN->db->get_record('course', 'id', $template->location);

            if (!empty($course)) {
                $this->locationlabel = $course->fullname . ' ' . $course->shortname;
                $this->locationid = $template->location;
            }

            // custom fields
            $level = context_level_base::get_custom_context_level('course', 'block_curr_admin');
            if ($level) {
                $fielddata = field_data::get_for_context(get_context_instance($level,$this->id));
                $fielddata = $fielddata ? $fielddata : array();
                foreach ($fielddata as $name => $value) {
                    $this->{"field_{$name}"} = $value;
                }
            }
        }
    }

    /*
     * Remove specified environment from all courses.
     *
     * @param $envid int Environment id.
     * @return bool Status of operation.
     */
    public static function remove_environment($envid) {
    	global $CURMAN;

    	$sql = 'UPDATE ' . $CURMAN->db->prefix_table(CRSTABLE) . ' SET environmentid=0 where environmentid=' . $envid;
    	return $CURMAN->db->execute_sql($sql, "");
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

    public function create_edit_form($formid='', $rows=2, $cols=40) {
        global $CURMAN;

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
            $template = new coursetemplate($this->id);
            $course = $CURMAN->db->get_record('course', 'id', $template->location);

            if (!empty($course)) {
                $this->locationlabel = $course->fullname . ' ' . $course->shortname;
                $this->locationid = $template->location;
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
            $template = new coursetemplate($this->id);
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
        global $CURMAN;

        if ($elemid != 0) {
            $elem = $CURMAN->db->get_record(CRSCOMPTABLE, 'id', $elemid);
        } else {
            $elem = new Object();
            $elem->idnumber = '';
            $elem->name = '';
            $elem->description = '';
            $elem->completion_grade = 0;
            $elem->required = 1;
        }

        $config_data = array();

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
     * Check for a duplicate record when doing an insert.
     *
     * @param object $record The record we want to insert.
     */
    function duplicate_check($record=null) {
        global $CURMAN;

        if(empty($record)) {
            $record = $this;
        }

        /// Check for valid course id - it can't already exist.
        if ($CURMAN->db->record_exists(CRSTABLE, 'idnumber', $record->idnumber)) {
            return true;
        }

        return false;
    }

    /**
     * Get a list of the course completion elements for the current course.
     *
     * @uses $CURMAN
     * @param none
     * @return array The list of course IDs.
     */
    function get_completion_elements() {
        global $CURMAN;

        if (!$this->id) {
            return false;
        }

        return $CURMAN->db->get_records(CRSCOMPTABLE, 'courseid', $this->id);
    }

    /*
     * Returns an aggregate of enrolment completion statuses for all classes created from this course.
     */
    public function get_completion_counts() {
        global $CURMAN;

        $sql = "SELECT cce.completestatusid status, COUNT(cce.completestatusid) count
        FROM {$CURMAN->db->prefix_table(STUTABLE)} cce
        JOIN {$CURMAN->db->prefix_table(USRTABLE)} usr ON cce.userid = usr.id
        INNER JOIN {$CURMAN->db->prefix_table(CLSTABLE)} cc ON cc.id = cce.classid
        INNER JOIN {$CURMAN->db->prefix_table(CRSTABLE)} cco ON cco.id = cc.courseid
        WHERE cco.id = {$this->id} ";

        if (empty($CURMAN->config->legacy_show_inactive_users)) {
            $sql .= 'AND usr.inactive = 0 ';
        }

        $sql .= 'GROUP BY cce.completestatusid';

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

    /**
     * Save an element.
     *
     * @uses $CURMAN
     * @param none
     * @return array The list of course IDs.
     */
    function save_completion_element($elemrecord) {
        global $CURMAN;

        if (!$this->id) {
            return false;
        }

        $elemrecord->courseid = $this->id;
        if (empty($elemrecord->id)) {
            return $CURMAN->db->insert_record(CRSCOMPTABLE, $elemrecord);
        } else {
            return $CURMAN->db->update_record(CRSCOMPTABLE, $elemrecord);
        }
    }

    /**
     * Delete an element.
     *
     * @uses $CURMAN
     * @param none
     * @return array The list of course IDs.
     */
    function delete_completion_element($elemid) {
        global $CURMAN;

        if (!$this->id || !$this->_dbloaded) {
            return false;
        }

        return $CURMAN->db->delete_records(CRSCOMPTABLE, 'id', $elemid);
    }

    /**
     * Retrieve the curricula that are affiliated with this course
     *
     * @uses $CURMAN
     * @param none
     * @return array The list of curricula IDs.
     */
    function get_assigned_curricula() {
      global $CURMAN;
      $assigned = array();

      if (!$this->id) {
          return false;
      }

      $result = $CURMAN->db->get_records(CURCRSTABLE, 'courseid', $this->id);

      if ($result) {
          foreach ($result as $data) {
            $assigned[$data->curriculumid] = $data->id;
          }
      }

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
              $status = $newcurcrs->data_insert_record();
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

        foreach($currassigned as $currid => $rowid) {
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
            return record_exists(CRSTABLE, 'id', $this->id);
        } else {
            return record_exists(CRSTABLE, 'id', $id);
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
     * Check for any course nags that need to be handled.
     *
     */
    function check_for_nags() {
        global $CFG, $CURMAN;

        $sendtouser =       $CURMAN->config->notify_courserecurrence_user;
        $sendtorole =       $CURMAN->config->notify_courserecurrence_role;
        $sendtosupervisor = $CURMAN->config->notify_courserecurrence_supervisor;

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
        $select = "SELECT cce.id, ccc.frequency, ccc.timeperiod, ".
                  "cc.name as coursename, " .
                  "c.name as curriculumname, " .
                  "cu.id as userid, cu.idnumber as useridnumber, cu.firstname as firstname, cu.lastname as lastname, " .
                  "cce.id as enrolmentid, cce.completetime as completetime, " .
                  "u.id as muserid ";
        $from   = "FROM {$CFG->prefix}crlm_curriculum_course ccc ";
        $join   = "INNER JOIN {$CFG->prefix}" . CRSTABLE . " cc ON cc.id = ccc.courseid " .
                  "INNER JOIN {$CFG->prefix}crlm_curriculum c ON c.id = ccc.curriculumid " .
                  "INNER JOIN {$CFG->prefix}crlm_curriculum_assignment cca ON cca.curriculumid = c.id " .
                  "INNER JOIN {$CFG->prefix}crlm_user cu ON cu.id = cca.userid " .
                  "INNER JOIN {$CFG->prefix}crlm_class ccl ON ccl.courseid = cc.id " .
                  "INNER JOIN {$CFG->prefix}crlm_class_enrolment cce ON cce.classid = ccl.id " .
                  "LEFT JOIN {$CFG->prefix}user u ON u.idnumber = cu.idnumber " .
                  "LEFT JOIN {$CFG->prefix}crlm_notification_log cnl ON cnl.fromuserid = cu.id AND cnl.instance = cce.id AND cnl.event = 'course_recurrence' ";
        $where  = "WHERE (cce.completestatusid != ".STUSTATUS_NOTCOMPLETE.") AND (ccc.frequency > 0) ".
                  "AND ((cce.completetime + " .
            /// This construct is to select the number of seconds to add to determine the delta frequency based on the timeperiod
                  "(CASE ccc.timeperiod WHEN 'year' THEN (ccc.frequency * {$year})
                                        WHEN 'month' THEN (ccc.frequency * {$month})
                                        WHEN 'week' THEN (ccc.frequency * {$week})
                                        WHEN 'day' THEN (ccc.frequency * {$day})
                                        ELSE 0 END)" .
            ///
                  ") < {$timenow}) AND (cnl.id IS NULL) ";
        $order  = "ORDER BY cce.id ASC ";
        $sql    = $select . $from . $join . $where . $order;

        $usertempl = new user(); // used just for its properties.

        $rs = get_recordset_sql($sql);
        if ($rs) {
            while ($rec = rs_fetch_next_record($rs)) {
                /// Load the student...
                $userdata = array();
                foreach ($usertempl->properties as $prop => $type) {
                    if (isset($rec->$prop)) {
                        $userdata[$prop] = $rec->$prop;
                    }
                }
                /// Do this AFTER copying properties to prevent accidentially stomping on the user id
                $userdata['id'] = $rec->userid;
                $user = new user($userdata);
                /// Add the moodleuserid to the user record so we can use it in the event handler.
                $user->moodleuserid = $rec->muserid;
                $user->coursename = $rec->coursename;
                $user->enrolmentid = $rec->enrolmentid;

                mtrace("Triggering course_recurrence event.\n");
                events_trigger('course_recurrence', $user);
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
        global $CFG, $CURMAN;
        require_once($CFG->dirroot.'/curriculum/lib/notifications.php');

        /// Does the user receive a notification?
        $sendtouser       = $CURMAN->config->notify_courserecurrence_user;
        $sendtorole       = $CURMAN->config->notify_courserecurrence_role;
        $sendtosupervisor = $CURMAN->config->notify_courserecurrence_supervisor;

        /// If nobody receives a notification, we're done.
        if (!$sendtouser && !$sendtorole && !$sendtosupervisor) {
            return true;
        }

        $context = get_system_context();

        /// Make sure this is a valid user.
        $enroluser = new user($user->id);
        if (empty($enroluser->id)) {
            print_error('nouser', 'block_curr_admin');
            return true;
        }
        
        $message = new notification();

        /// Set up the text of the message
        $text = empty($CURMAN->config->notify_courserecurrence_message) ?
                    get_string('notifycourserecurrencemessagedef', 'block_curr_admin') :
                    $CURMAN->config->notify_courserecurrence_message;
        $search = array('%%userenrolname%%', '%%coursename%%');
        $replace = array(fullname($user), $user->coursename);
        $text = str_replace($search, $replace, $text);

        $eventlog = new Object();
        $eventlog->event = 'course_recurrence';
        $eventlog->instance = $user->enrolmentid;
        $eventlog->fromuserid = $user->id;
        if ($sendtouser) {
            $message->send_notification($text, $user, null, $eventlog);
        }

        $users = array();

        if ($sendtorole) {
            /// Get all users with the notify_courserecurrence capability.
            if ($roleusers = get_users_by_capability($context, 'block/curr_admin:notify_courserecurrence')) {
                $users = $users + $roleusers;
            }
        }

        if ($sendtosupervisor) {
            /// Get parent-context users.
            if ($supervisors = cm_get_users_by_capability('user', $user->id, 'block/curr_admin:notify_courserecurrence')) {
                $users = $users + $supervisors;
            }
        }

        foreach ($users as $u) {
            $message->send_notification($text, $u, $enroluser, $eventlog);
        }

        return true;
    }

	public function delete() {
        $level = context_level_base::get_custom_context_level('course', 'block_curr_admin');
		$return = curriculumcourse::delete_for_course($this->id);
		$return = $return && cmclass::delete_for_course($this->id);
		$return = $return && taginstance::delete_for_course($this->id);
        $return = $return && coursetemplate::delete_for_course($this->id);
        $return = $return && delete_context($level,$this->id);

    	return $return && $this->data_delete_record();
    }

    public function set_from_data($data) {
        if (isset($data->curriculum)) {
            $this->curriculum = $data->curriculum;
        }

        if (isset($data->location)) {
            $this->location = $data->location;
            $this->templateclass = $data->templateclass;
        }

        $fields = field::get_for_context_level('course', 'block_curr_admin');
        $fields = $fields ? $fields : array();
        foreach ($fields as $field) {
            $fieldname = "field_{$field->shortname}";
            if (isset($data->$fieldname)) {
                $this->$fieldname = $data->$fieldname;
            }
        }

        return parent::set_from_data($data);
    }

    public function add() {
        $result = parent::add();

        if(isset($this->curriculum)) {
            $this->add_course_to_curricula($this->curriculum);
        }

        // Add moodle course template
        if (isset($this->location)) {
            $template = new coursetemplate($this->id);
            $template->location           = $this->location;
            $template->templateclass      = $this->templateclass;
            $template->courseid           = $this->id;

            $template->data_update_record(true);
        } else {
            coursetemplate::delete_for_course($this->id);
        }

        $result = $result && field_data::set_for_context_from_datarecord('course', $this);

        return $result;
    }

    public function update() {
        $result = parent::update();

        if(isset($this->curriculum)) {
            $this->add_course_to_curricula($this->curriculum);
        }

        // Add moodle course template
        if (isset($this->location)) {
            $template = new coursetemplate($this->id);
            $template->location           = $this->location;
            $template->templateclass      = $this->templateclass;
            $template->courseid           = $this->id;

            $template->data_update_record(true);
        } else {
            coursetemplate::delete_for_course($this->id);
        }

        $result = $result && field_data::set_for_context_from_datarecord('course', $this);

        return $result;
    }

    public function to_string() {
    	return $this->name;
    }

    static public function get_by_idnumber($idnumber) {
        global $CURMAN;
        $retval = null;

        $course = $CURMAN->db->get_record(CRSTABLE, 'idnumber', $idnumber);


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
    function duplicate($options) {
        global $CURMAN;
        require_once CURMAN_DIRLOCATION . '/lib/cmclass.class.php';
        require_once CURMAN_DIRLOCATION . '/lib/coursetemplate.class.php';
        $objs = array('errors' => array());
        if (isset($options['targetcluster'])) {
            $cluster = $options['targetcluster'];
            if (!is_object($cluster) || !is_a($cluster, 'cluster')) {
                $options['targetcluster'] = $cluster = new cluster($cluster);
            }
        }

        // clone main course object
        $clone = new course($this);
        unset($clone->id);
        if (isset($cluster)) {
            // if cluster specified, append cluster's name to course
            $clone->name = $clone->name . ' - ' . $cluster->name;
            $clone->idnumber = $clone->idnumber . ' - ' . $cluster->name;
        }
        $clone = new course(addslashes_recursive($clone));
        if (!$clone->add()) {
            $objs['errors'][] = get_string('failclustcpycurrcrs', 'block_curr_admin', $this);
            return $objs;
        }

        $objs['courses'] = array($this->id => $clone->id);
        $options['targetcourse'] = $clone->id;

        // copy completion elements
        $compelems = $this->get_completion_elements();
        if (!empty($compelems)) {
            foreach ($compelems as $compelem) {
                $compelem = addslashes_recursive($compelem);
                unset($compelem->id);
                $clone->save_completion_element($compelem);
            }
        }

        // copy template
        $template = $CURMAN->db->get_record(CTTABLE, 'courseid', $this->id);
        $template = new coursetemplate($template);
        unset($template->id);
        $template->courseid = $clone->id;
        $template->add();

        // FIXME: copy tags

        // copy the classes
        if (!empty($options['classes'])) {
            $classes = cmclass_get_record_by_courseid($this->id);
            if (!empty($classes)) {
                $objs['classes'] = array();
                foreach ($classes as $class) {
                    $class = new cmclass($class);
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
    global $CURMAN;

    $LIKE = $CURMAN->db->sql_compare();

    $select = 'SELECT crs.*, env.name as envname, env.description as envdescription, (SELECT COUNT(*) FROM ' . $CURMAN->db->prefix_table(CURCRSTABLE) . ' WHERE courseid = crs.id ) as curricula ';
    $tables = 'FROM '.$CURMAN->db->prefix_table(CRSTABLE).' crs ';
    $join   = 'LEFT JOIN '.$CURMAN->db->prefix_table(ENVTABLE).' env ';
    $on     = 'ON env.id = crs.environmentid ';

    $where = array();
    if (!empty($namesearch)) {
        $namesearch = trim($namesearch);
        $where[] = "((crs.name $LIKE '%$namesearch%') OR (crs.idnumber $LIKE '%$namesearch%')) ";
    }

    if ($alpha) {
        $where[] = (!empty($where) ? ' AND ' : '') . "(crs.name $LIKE '$alpha%') ";
    }

    if ($contexts !== null) {
        $where[] = $contexts->sql_filter_for_context_level('crs.id', 'course');
    }

    if (!empty($where)) {
        $where = 'WHERE '.implode(' AND ',$where).' ';
    } else {
        $where = '';
    }

    if ($sort) {
        $sort = 'ORDER BY '.$sort .' '. $dir.' ';
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

    $sql = $select.$tables.$join.$on.$where.$sort.$limit;

    return $CURMAN->db->get_records_sql($sql);
}


function course_count_records($namesearch = '', $alpha = '', $contexts = null) {
    global $CURMAN;

    $where = array();

    $LIKE = $CURMAN->db->sql_compare();

    if (!empty($namesearch)) {
        $where[] = "((name $LIKE '%$namesearch%') OR (idnumber $LIKE '%$namesearch%'))";
    }

    if ($alpha) {
        $where[] = "(name $LIKE '$alpha%')";
    }

    if ($contexts !== null) {
        $where[] = $contexts->sql_filter_for_context_level('id', 'course');
    }

    $where = implode(' AND ', $where);

    return $CURMAN->db->count_records_select(CRSTABLE, $where);
}

?>
