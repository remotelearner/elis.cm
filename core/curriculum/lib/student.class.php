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

require_once CURMAN_DIRLOCATION . '/lib/curriculumcourse.class.php';
require_once CURMAN_DIRLOCATION . '/lib/cmclass.class.php';
require_once CURMAN_DIRLOCATION . '/lib/instructor.class.php';
require_once CURMAN_DIRLOCATION . '/lib/user.class.php';
require_once CURMAN_DIRLOCATION . '/lib/attendance.class.php';
require_once CURMAN_DIRLOCATION . '/lib/course.class.php';
require_once CURMAN_DIRLOCATION . '/lib/waitlist.class.php';

define ('STUTABLE', 'crlm_class_enrolment');
define ('WAITTABLE', 'crlm_wait_list');
define ('GRDTABLE', 'crlm_class_graded');
//define ('CRSTABLE', 'crlm_course');

define ('STUSTATUS_NOTCOMPLETE', 0);
define ('STUSTATUS_FAILED',      1);
define ('STUSTATUS_PASSED',      2);

class student extends datarecord {
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

    // STRING - Styles to use for edit form.
    var $_editstyle = '
.attendanceeditform input,
.attendanceeditform textarea {
    margin: 0;
    display: block;
}
';


    /**
     * Contructor.
     *
     * @param $studentdata int/object/array The data id of a data record or data elements to load manually.
     * @param $classdata object Optional cmclass object to load into the structure.
     * @param $complelements array Optional array of completion elements associated with the class.
     *
     */
    function __construct($studentdata=false, $classdata=false, $compelements=false) {
        global $CURMAN;

        parent::datarecord();

        $this->set_table(STUTABLE);
        $this->add_property('id', 'int');
        $this->add_property('classid', 'int', true);
        $this->add_property('userid', 'int', true);
        $this->add_property('enrolmenttime', 'int');
        $this->add_property('completetime', 'int');
        $this->add_property('endtime', 'int');
        $this->add_property('completestatusid', 'int');
        $this->completestatusid = key(student::$completestatusid_values);
        $this->add_property('grade', 'float');
        $this->add_property('credits', 'float');
        $this->add_property('locked', 'int');

        if (is_numeric($studentdata)) {
            $this->data_load_record($studentdata);
        } else if (is_array($studentdata)) {
            $this->data_load_array($studentdata);
        } else if (is_object($studentdata)) {
            $this->data_load_array(get_object_vars($studentdata));
        }

        $this->load_cmclass($classdata, $compelements);

        if (!empty($this->userid)) {
            $this->user = new user($this->userid);
        } else {
            $this->user = new user();
        }
    }

    /**
     *  @param $classdata int/object Optional id, or cmclass object to load.
     *  @param $compelements array Optional array of completion elements
     *  associated with the class.  (Set to null to avoid loading completion elements.)
     *
     */
    function load_cmclass($classdata=false, $compelements=false) {
        global $CURMAN;

        if ($classdata !== false) {
            if (is_int($classdata) || is_numeric($classdata)) {
                $this->classid = $classdata;
                $this->cmclass = null;
            } else if (is_object($classdata) && (get_class($classdata) == 'cmclass')) {
                $this->classid = $classdata->id;
                $this->cmclass = $classdata;
            }
        }

        if (!empty($this->classid)) {

            if (empty($this->cmclass)) {
                $this->cmclass = new cmclass($this->classid);
            }

            /// Load up any completion and grade elements
            if (isset($this->cmclass->course) && $compelements !== null) {

                if ($compelements === false) {
                    $compelements = $this->cmclass->course->get_completion_elements();
                }
                $select ='classid = '.$this->classid.' AND userid = '.$this->userid;
                $grades = $CURMAN->db->get_records_select
                            (CLSGRTABLE, $select, '',
                             'completionid,id,classid,userid,grade,locked,timegraded,timemodified');
                $this->grades = array();

                if (!empty($compelements)) {
                    foreach ($compelements as $compelement) {
                        if (isset($grades[$compelement->id])) {
                            $this->grades[$compelement->id] = new student_grade($grades[$compelement->id]);
                        } else {
                            $this->grades[$compelement->id] = new student_grade();
                        }
                    }
                }
            }
        } else {
            $this->cmclass = new cmclass();
        }
    }

    /**
     * Perform all actions to mark this student record complete.
     *
     * @param   mixed  $status   The completion status (ignored if FALSE)
     * @param   mixed  $time     The completion time (ignored if FALSE)
     * @param   mixed  $grade    Grade in the class (ignored if FALSE)
     * @param   mixed  $credits  Number of credits awarded (ignored if FALSE)
     * @param   mixed  $locked   If TRUE, the assignment record becomes locked
     *
     * @return  boolean          TRUE is successful, otherwise FALSE
     */
    function complete($status = false, $time = false, $grade = false, $credits = false, $locked = false) {
        global $CFG, $CURMAN;
        require_once CURMAN_DIRLOCATION . '/lib/notifications.php';

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

        if ($this->update()) {
            /// Does the user receive a notification?
            $sendtouser       = $CURMAN->config->notify_classcompleted_user;
            $sendtorole       = $CURMAN->config->notify_classcompleted_role;
            $sendtosupervisor = $CURMAN->config->notify_classcompleted_supervisor;

            /// Make sure this is a valid user.
            $enroluser = new user($this->userid);
            if (empty($enroluser->id)) {
                print_error('nouser', 'block_curr_admin');
                return true;
            }

            $message = new notification();

            /// Set up the text of the message
            $text = empty($CURMAN->config->notify_classcompleted_message) ?
                        get_string('notifyclasscompletedmessagedef', 'block_curr_admin') :
                        $CURMAN->config->notify_classcompleted_message;
            $search = array('%%userenrolname%%', '%%classname%%');

            if (($clsmdl = $CURMAN->db->get_record(CLSMDLTABLE, 'classid', $this->cmclass->id)) &&
                ($course = get_record('course', 'id', $clsmdl->moodlecourseid))) {
                /// If its a Moodle class...
                $replace = array(fullname($this->user), $course->fullname);
                if (!($context = get_context_instance(CONTEXT_COURSE, $course->id))) {
                    print_error('invalidcontext');
                    return true;
                }
            } else {
                $replace = array(fullname($this->user), $this->cmclass->course->name);
                if (!($context = get_system_context())) {
                    print_error('invalidcontext');
                    return true;
                }
            }

            $text = str_replace($search, $replace, $text);

            if ($sendtouser) {
                $message->send_notification($text, $this->user);
            }

            $users = array();

            if ($sendtorole) {
                /// Get all users with the notify_classcompleted capability.
                if ($roleusers = get_users_by_capability($context, 'block/curr_admin:notify_classcomplete')) {
                    $users = $users + $roleusers;
                }
            }

            if ($sendtosupervisor) {
                /// Get parent-context users.
                if ($supervisors = cm_get_users_by_capability('user', $this->userid, 'block/curr_admin:notify_classcomplete')) {
                    $users = $users + $supervisors;
                }
            }

            foreach ($users as $user) {
                $message->send_notification($text, $user, $enroluser);
            }
        }

//        events_trigger('crlm_class_completed', $this);
    }

/////////////////////////////////////////////////////////////////////
//                                                                 //
//  STANDARD FUNCTIONS:                                            //
//                                                                 //
/////////////////////////////////////////////////////////////////////

    /**
     * Perform all necessary tasks to add a student enrolment to the system.
     *
     * @param array $checks what checks to perform before adding enrolling the
     * user.  e.g. array('prereq' => 1, 'waitlist' => 1) will check that
     * prerequisites are satisfied, and that the class is not full
     * @param boolean $notify whether or not notifications should be sent if a
     * check fails
     */
    function add($checks = array(), $notify = false) {
        global $CURMAN, $CFG, $USER;

        $status = true;

        if ($CURMAN->db->record_exists(STUTABLE, 'userid', $this->userid,
                                       'classid', $this->classid)) {
            // already enrolled -- pretend we succeeded
            return true;
        }

        // check that the student can be enrolled first
        if (!empty($checks['prereq'])) {
            // check prerequisites

            $cmclass = new cmclass($this->classid);
            // get all the curricula that the user is in
            $curricula = curriculumstudent::get_curricula($this->userid);
            foreach ($curricula as $curriculum) {
                $curcrs = new curriculumcourse();
                $curcrs->courseid = $cmclass->courseid;
                $curcrs->curriculumid = $curriculum->curid;
                if (!$curcrs->prerequisites_satisfied($this->userid)) {
                    // prerequisites not satisfied
                    if ($notify) {
                        $data = new stdClass;
                        $data->userid = $this->userid;
                        $data->classid = $this->classid;
                        //$data->trackid = $trackid;
                        events_trigger('crlm_prereq_unsatisfied', $data);
                    }

                    $status = new Object();
                    $status->message = get_string('unsatisfiedprereqs', 'block_curr_admin');
                    $status->code = 'unsatisfiedprereqs';
                    return $status;
                }
            }
        }

        if (!empty($checks['waitlist'])) {
            // check class enrolment limit
            $cmclass = new cmclass($this->classid);
            $limit = $cmclass->maxstudents;
            if (!empty($limit) && $limit <= student::count_enroled($this->classid)) {
                // class is full
                // put student on wait list
                $wait_list = new waitlist($this);
                $wait_list->timecreated = time();
                $wait_list->position = 0;
                $wait_list->add();

                if ($notify) {
                    $subject = get_string('user_waitlisted', 'block_curr_admin');

                    $a = new object();
                    $a->user = $this->user->idnumber;
                    $a->cmclass = $cmclass->idnumber;
                    $message = get_string('user_waitlisted_msg', 'block_curr_admin', $a);

                    $from = $user = get_admin();

                    notification::notify($message, $user, $from);
                    email_to_user($user, $from, $subject, $message);
                }

                $status = new Object();
                $status->message = get_string('user_waitlisted', 'block_curr_admin');
                $status->code = 'user_waitlisted';
                return $status;
            }
        }
        //set end time based on class duration
        $studentclass = new cmclass($this->classid);
        if (empty($this->endtime)) {
            if (isset($studentclass->duration) && $studentclass->duration) {
                $this->endtime = $this->enrolmenttime + $studentclass->duration;
            } else {
                // no class duration -> no end time
                $this->endtime = 0;
            }
        }

        $status = $this->data_insert_record(); // TBD: we should check this!

        /// Get the Moodle user ID or create a new account for this user.
        if (!($muserid = cm_get_moodleuserid($this->userid))) {
            $user = new user($this->userid);

            if (!$muserid = $user->synchronize_moodle_user(true, true)) {
                $status = new Object();
                $status->message = get_string('errorsynchronizeuser', 'block_curr_admin');
                $muserid = false;
            }
        }

        /// Enrol them into the Moodle class.
        if ($moodlecourseid = moodle_get_course($this->classid)) {
            if ($mcourse = get_record('course', 'id', $moodlecourseid)) {
                $enrol = $mcourse->enrol;
                if (!$enrol) {
                    $enrol = $CFG->enrol;
                }
                if ($CURMAN->config->restrict_to_elis_enrolment_plugin && $enrol != 'elis') {
                    $status = new Object();
                    $status->message = get_string('error_not_using_elis_enrolment', 'block_curr_admin');
                    return $status;
                }

                $timestart = $this->enrolmenttime;
                $timeend = $this->endtime;

                if ($role = get_default_course_role($mcourse)) {
                    $context = get_context_instance(CONTEXT_COURSE, $mcourse->id);

                    if (!empty($muserid)) {
                        if (!role_assign($role->id, $muserid, 0, $context->id, $timestart, $timeend, 0, 'manual')) {
                            $status = new Object();
                            $status->message = get_string('errorroleassign', 'block_curr_admin');
                        }
                    }
                }
            }
        } else if (!empty($muserid)) {
            $sturole = $CURMAN->config->enrolment_role_sync_student_role;
            // ELIS-2776: must still trigger events for notifications
            $ra = new stdClass();
            $ra->roleid       = !empty($sturole)
                                ? $sturole
                                : get_field('role', 'id', 'shortname', 'student');
            $ra->contextid    = context_level_base::get_custom_context_level('class', 'block_curr_admin'); // TBD
            $ra->userid       = $muserid;
            $ra->component    = ''; // TBD: 'enrol_elis'
            $ra->itemid       = $this->classid; // TBD
            $ra->timemodified = time();
            $ra->modifierid   = empty($USER->id) ? 0 : $USER->id;
            events_trigger('role_assigned', $ra);
        }

        return $status;
    }

    /**
     * Perform all necessary tasks to remove a student enrolment from the system.
     */
    function delete() {
        /// Remove any grade records for this enrolment.
        $result = student_grade::delete_for_user_and_class($this->userid, $this->classid);

        /// Unenrol them from the Moodle class.
        if (!empty($this->classid) && !empty($this->userid) &&
            ($moodlecourseid = get_field('crlm_class_moodle', 'moodlecourseid', 'classid', $this->classid)) &&
            ($muserid = cm_get_moodleuserid($this->userid))) {

            $context = get_context_instance(CONTEXT_COURSE, $moodlecourseid);
            if ($context && $context->id) {
                role_unassign(0, $muserid, 0, $context->id);
            }
        }

        $result = $result && $this->data_delete_record();

        if($this->completestatusid == STUSTATUS_NOTCOMPLETE) {
            $cmclass = new cmclass($this->classid);

            if(empty($cmclass->maxstudents) || $cmclass->maxstudents > student::count_enroled($cmclass->id)) {
                $wlst = waitlist::get_next($this->classid);

                if(!empty($wlst)) {
                    $wlst->enrol();
                }
            }
        }

        return $result;
    }

    /**
     * Retrieves a user object given the users idnumber
     * @global <type> $CURMAN
     * @param <type> $idnumber
     * @return <type>
     */
    public static function get_userclass($userid, $classid) {
        global $CURMAN;
        $retval = null;

        $student = $CURMAN->db->get_record(STUTABLE, 'userid', $userid, 'classid', $classid);

        if(!empty($student)) {
            $retval = new student($student->id);
        }

        return $retval;
    }

    // Note: we rely on the caller to cascade these deletes to the student_grade
	// table.
    public static function delete_for_class($id) {
    	global $CURMAN;

    	return $CURMAN->db->delete_records(STUTABLE, 'classid', $id);
    }

	public static function delete_for_user($id) {
		global $CURMAN;

		return $CURMAN->db->delete_records(STUTABLE, 'userid', $id);
	}

    /**
     * Perform all necessary tasks to update a student enrolment.
     *
     */
    function update() {
        $retval = $this->data_update_record();
        events_trigger('crlm_class_completed', $this);

        return $retval;
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
     *
     * @return string The form HTML, without the form.
     */
    function edit_form_html($classid, $type = '', $sort = 'name', $dir = 'ASC', $page = 0,
                            $perpage = 0, $namesearch = '', $alpha = '') {
        global $CURMAN, $CFG;

        $classid = $this->classid;

        $output = '';
        ob_start();

        $table = new stdClass;

        if (empty($this->id)) {
            $columns = array(
                'enrol'            => get_string('enrol', 'block_curr_admin'),
                'idnumber'         => get_string('student_idnumber', 'block_curr_admin'),
                'name'             => get_string('student_name_1', 'block_curr_admin'),
                'enrolmenttime'    => get_string('enrolment_time', 'block_curr_admin'),
                'completetime'     => get_string('completion_time', 'block_curr_admin'),
                'completestatusid' => get_string('student_status', 'block_curr_admin'),
                'grade'            => get_string('student_grade', 'block_curr_admin'),
                'credits'          => get_string('student_credits', 'block_curr_admin'),
                'locked'           => get_string('student_locked', 'block_curr_admin')
            );

        } else {
            $columns = array(
                'idnumber'         => get_string('student_idnumber', 'block_curr_admin'),
                'name'             => get_string('student_name_1', 'block_curr_admin'),
                'enrolmenttime'    => get_string('enrolment_time', 'block_curr_admin'),
                'completetime'     => get_string('completion_time', 'block_curr_admin'),
                'completestatusid' => get_string('student_status', 'block_curr_admin'),
                'grade'            => get_string('student_grade', 'block_curr_admin'),
                'credits'          => get_string('student_credits', 'block_curr_admin'),
                'locked'           => get_string('student_locked', 'block_curr_admin')
            );
        }

        foreach ($columns as $column => $cdesc) {
            if ($sort != $column) {
                $columnicon = "";
                $columndir = "ASC";
            } else {
                $columndir = $dir == "ASC" ? "DESC":"ASC";
                $columnicon = $dir == "ASC" ? "down":"up";
                $columnicon = " <img src=\"$CFG->pixpath/t/$columnicon.gif\" alt=\"\" />";

            }

            if (($column == 'name') || ($column == 'description')) {
                $$column = "<a href=\"index.php?s=stu&amp;section=curr&amp;id=$classid&amp;class=$classid&amp;" .
                           "action=add&amp;sort=$column&amp;dir=$columndir&amp;stype=$type&amp;search=" .
                           urlencode(stripslashes($namesearch)) . "&amp;alpha=$alpha\">" .
                           $cdesc . "</a>$columnicon";
            } else {
                $$column = $cdesc;
            }

            $table->head[]  = $$column;
            $table->align[] = "left";
            $table->wrap[]  = true;
        }

        if (empty($this->id)) {
            $users     = $this->get_users_avail($sort, $dir, $page * $perpage, $perpage,
                                                $namesearch, $alpha);
            $usercount = $this->count_users_avail($namesearch, $alpha);

            $alphabet = explode(',', get_string('alphabet'));
            $strall   = get_string('all');


        /// Bar of first initials
            echo "<p style=\"text-align:center\">";
            echo get_string('tag_name', 'block_curr_admin')." : ";
            if ($alpha) {
                echo " <a href=\"index.php?s=stu&amp;section=curr&amp;action=add&amp;id=$classid&amp;class=$classid&amp;" .
                     "sort=name&amp;dir=ASC&amp;perpage=$perpage\">$strall</a> ";
            } else {
                echo " <b>$strall</b> ";
            }
            foreach ($alphabet as $letter) {
                if ($letter == $alpha) {
                    echo " <b>$letter</b> ";
                } else {
                    echo " <a href=\"index.php?s=stu&amp;section=curr&amp;action=add&amp;id=$classid&amp;class=$classid&amp;" .
                         "sort=name&amp;dir=ASC&amp;perpage=$perpage&amp;alpha=$letter\">$letter</a> ";
                }
            }
            echo "</p>";

            print_paging_bar($usercount, $page, $perpage,
                    "index.php?s=stu&amp;section=curr&amp;id=$classid&amp;class=$classid&amp;&amp;action=add&amp;" .
                    "sort=$sort&amp;dir=$dir&amp;perpage=$perpage&amp;alpha=$alpha&amp;stype=$type" .
                    "&amp;search=" . urlencode(stripslashes($namesearch)) . "&amp;");

            flush();

        } else {
            $user = $this->user;

            $user->name        = cm_fullname($user);
            $users[]           = $user;
            $usercount         = 0;
        }

        if (empty($this->id) && !$users) {
            $match = array();
            if ($namesearch !== '') {
               $match[] = s($namesearch);
            }
            if ($alpha) {
               $match[] = 'name'.": $alpha"."___";
            }
            $matchstring = implode(", ", $match);
            echo 'No users matching '.$matchstring;

            $table = NULL;

        } else {
            $stuobj = new student();

            $table->width = "100%";
            foreach ($users as $user) {
                $newarr = array();

                foreach ($columns as $column => $cdesc) {
                    switch ($column) {
                        case 'enrol':
                            $newarr[] = '<input type="checkbox" name="users[' . $user->id . '][enrol]" value="1" />'.
                                        '<input type="hidden" name="users[' . $user->id . '][idnumber]" '.
                                        'value="' . $user->idnumber . '" />';
                            break;

                        case 'name':
                        case 'idnumber':
                        case 'description';
                            $newarr[] = $user->$column;
                            break;

                        case 'enrolmenttime':
                            $newarr[] = cm_print_date_selector('users[' . $user->id . '][startday]',
                                                               'users[' . $user->id . '][startmonth]',
                                                               'users[' . $user->id . '][startyear]',
                                                               $this->enrolmenttime, true);
                            break;

                        case 'completetime':
                            $newarr[] = cm_print_date_selector('users[' . $user->id . '][endday]',
                                                               'users[' . $user->id . '][endmonth]',
                                                               'users[' . $user->id . '][endyear]',
                                                               $this->completetime, true);
                            break;

                        case 'completestatusid':
                            $choices = array();

                            foreach(student::$completestatusid_values as $key => $csidv) {
                                $choices[$key] = get_string($csidv, 'block_curr_admin');
                            }

                            $newarr[] = cm_choose_from_menu($choices,
                                                            'users[' . $user->id . '][completestatusid]',
                                                            $this->completestatusid, '', '', '', true);
                            break;

                        case 'grade':
                            $newarr[] = '<input type="text" name="users[' . $user->id . '][grade]" ' .
                                        'value="' . $this->grade . '" size="5" />';
                            break;

                        case 'credits':
                            $newarr[] = '<input type="text" name="users[' . $user->id . '][credits]" ' .
                                        'value="' . $this->credits . '" size="5" />';
                            break;

                        case 'locked':
                            $newarr[] = '<input type="checkbox" name="users[' . $user->id . '][locked]" ' .
                                        'value="1" '.($this->locked?'checked="checked"':'').'/>';
                            break;

                        default:
                            $newarr[] = '';
                            break;
                    }
                }

                $table->data[] = $newarr;
            }
        }

        if (empty($this->id)) {
            echo "<table class=\"searchbox\" style=\"margin-left:auto;margin-right:auto\" cellpadding=\"10\"><tr><td>";
            echo "<form action=\"index.php\" method=\"get\"><fieldset>";
            echo '<input type="hidden" name="s" value="stu" />';
            echo '<input type="hidden" name="section" value="curr" />';
            echo '<input type="hidden" name="action" value="add" />';
            echo '<input type="hidden" name="id" value="' . $classid . '" />';
            echo '<input type="hidden" name="sort" value="' . $sort . '" />';
            echo '<input type="hidden" name="dir" value="' . $dir . '" />';
            /*echo '<input type="radio" name="stype" value="student" ' .
                 (($type == 'student') ? ' checked' : '') . '/> Students ' .
                 '<input type="radio" name="stype" value="instructor" ' .
                 (($type == 'instructor') ? ' checked' : '') . '/> Instructors ' .
                 '<input type="radio" name="stype" vale="" ' . (($type == '') ? ' checked' : '') . '/> All ';*/
            echo "<input type=\"text\" name=\"search\" value=\"".s($namesearch, true)."\" size=\"20\" />";
            echo "<input type=\"submit\" value=\"" . get_string('search', 'block_curr_admin') . "\" />";
            if ($namesearch) {
                echo "<input type=\"button\" onclick=\"document.location='index.php?s=stu&amp;section=curr&amp;" .
                     "action=add&amp;id=$classid';\" value=\"" . get_string('show_all_users', 'block_curr_admin') . "\" />";
            }
            echo "</fieldset></form>";
            echo "</td></tr></table>";

            echo '<form method="post" action="index.php?s=stu&amp;section=curr&amp;id=' . $classid . '" >'."\n";
            echo '<input type="hidden" name="action" value="savenew" />'."\n";

        } else {
            echo '<form method="post" action="index.php?s=stu&amp;section=curr&amp;id=' . $classid . '" >'."\n";
            echo '<input type="hidden" name="action" value="update" />'."\n";
            echo '<input type="hidden" name="association_id" value="' . $this->id . '" />' . "\n";
            echo '<input type="hidden" name="id" value="' . $this->classid . '" />' . "\n";
            echo '<input type="hidden" name="userid" value="' . $this->userid . '" />' . "\n";
        }

        if (!empty($table)) {
            if(empty($this->id)) {
                require_js($CFG->wwwroot . '/curriculum/js/classform.js');
                echo '<span class="checkbox selectall">';

                echo '<input type="checkbox" onclick="class_enrol_set_all_selected()"
                             id="class_enrol_select_all" name="class_enrol_select_all"/>';
                echo '<label for="class_enrol_select_all">' . get_string('enrol_select_all', 'block_curr_admin') . '</label>';
                echo '</span>';
            }
            print_table($table);
        }


        if (isset($this->cmclass->course) && is_object($this->cmclass->course) &&
            (get_class($this->cmclass->course) == 'course') &&
            ($elements = $this->cmclass->course->get_completion_elements())) {

            $select = "classid = {$this->classid} AND userid = {$this->userid}";
            $grades = $CURMAN->db->get_records_select(CLSGRTABLE, $select, 'id', 'completionid,id,classid,userid,grade,locked,timegraded,timemodified');

            $table = new stdClass;

            $columns = array(
                'element'          => 'Grade Element',
                'grade'            => 'Grade',
                'locked'           => 'Locked',
                'timegraded'       => 'Date Graded'
            );

            foreach ($columns as $column => $cdesc) {
                if ($sort != $column) {
                    $columnicon = "";
                    $columndir = "ASC";
                } else {
                    $columndir = $dir == "ASC" ? "DESC":"ASC";
                    $columnicon = $dir == "ASC" ? "down":"up";
                    $columnicon = " <img src=\"$CFG->pixpath/t/$columnicon.gif\" alt=\"\" />";

                }

                if (($column == 'name') || ($column == 'description')) {
                    $$column = "<a href=\"index.php?s=stu&amp;section=curr&amp;id=$classid&amp;class=$classid&amp;" .
                               "action=add&amp;sort=$column&amp;dir=$columndir&amp;stype=$type&amp;search=" .
                               urlencode(stripslashes($namesearch)) . "&amp;alpha=$alpha\">" .
                               $cdesc . "</a>$columnicon";
                } else {
                    $$column = $cdesc;
                }

                $table->head[]  = $$column;
                $table->align[] = "left";
                $table->wrap[]  = true;
            }

            $table->width = "100%";

            foreach ($elements as $element) {
                $newarr = array();
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
                            $newarr[] = '<input type="hidden" name="'.$name.'" ' .
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
                            $newarr[] = cm_print_date_selector($name.'[startday]',
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
                            $newarr[] = '<input type="text" name="'.$name.'" ' .
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
                            $newarr[] = '<input type="checkbox" name="'.$name.'" ' .
                                        'value="1" '.($value?'checked="checked"':'').'/>';
                            break;

                        default:
                            $newarr[] = '';
                            break;
                    }
                }
                $table->data[] = $newarr;
            }

            if (!empty($table)) {
                echo '<br />';
                print_table($table);
                print_string('grade_update_warning', 'block_curr_admin');
            }
        }

        if (empty($this->id)) {
            echo '<br /><input type="submit" value="' . get_string('enrol_selected', 'block_curr_admin') . '">'."\n";
        } else {
            echo '<br /><input type="submit" value="' . get_string('update_enrolment', 'block_curr_admin') . '">'."\n";
        }
        echo '</form>'."\n";

        $output = ob_get_contents();
        ob_end_clean();

        return $output;
    }

    /**
     * Return the HTML to for a view page that also allows editing.
     *
     * @return string The form HTML, without the form.
     */
    function view_form_html($classid, $type = '', $sort = 'name', $dir = 'ASC', $page = 0,
                            $perpage = 0, $namesearch = '', $alpha = '') {
        global $CURMAN, $CFG;

        $output = '';
        ob_start();

        $table = new stdClass;

        $can_unenrol = cmclasspage::can_enrol_into_class($classid);

        if (empty($this->id)) {
            $columns = array(
                'unenrol'          => get_string('unenrol','block_curr_admin'),
                'idnumber'         => get_string('student_idnumber','block_curr_admin'),
                'name'             => get_string('student_name_1','block_curr_admin'),
//                'description'      => 'Description',
                'enrolmenttime'    => get_string('enrolment_time','block_curr_admin'),
                'completetime'     => get_string('completion_time','block_curr_admin'),
                'completestatusid' => get_string('student_status','block_curr_admin'),
                'grade'            => get_string('student_grade','block_curr_admin'),
                'credits'          => get_string('student_credits','block_curr_admin'),
                'locked'           => get_string('student_locked','block_curr_admin')
            );

            if (!$can_unenrol) {
                unset($columns['unenrol']);
            }
        } else {
            $columns = array(
                'idnumber'         => get_string('student_idnumber','block_curr_admin'),
                'name'             => get_string('student_name_1','block_curr_admin'),
//                'description'      => 'Description',
                'enrolmenttime'    => get_string('enrolment_time','block_curr_admin'),
                'completetime'     => get_string('completion_time','block_curr_admin'),
                'completestatusid' => get_string('student_status','block_curr_admin'),
                'grade'            => get_string('student_grade','block_curr_admin'),
                'credits'          => get_string('student_credits','block_curr_admin'),
                'locked'           => get_string('student_locked','block_curr_admin')
            );
        }

        foreach ($columns as $column => $cdesc) {
            if ($sort != $column) {
                $columnicon = "";
                $columndir = "ASC";
            } else {
                $columndir = $dir == "ASC" ? "DESC":"ASC";
                $columnicon = $dir == "ASC" ? "down":"up";
                $columnicon = " <img src=\"$CFG->pixpath/t/$columnicon.gif\" alt=\"\" />";
            }

            if (($column != 'unenrol')) {
                $$column = "<a href=\"index.php?s=stu&amp;section=curr&amp;id=$classid&amp;class=$classid&amp;" .
                           "action=bulkedit&amp;sort=$column&amp;dir=$columndir&amp;stype=$type&amp;search=" .
                           urlencode(stripslashes($namesearch)) . "&amp;alpha=$alpha\">" .
                           $cdesc . "</a>$columnicon";
            } else {
                $$column = $cdesc;
            }

            $table->head[]  = $$column;
            $table->align[] = "left";
            $table->wrap[]  = true;
        }

        if (empty($this->id)) {
            $users     = $this->get_users_enrolled($type, $sort, $dir, $page * $perpage, $perpage,
                                                $namesearch, $alpha);
            $usercount = $this->count_users_enrolled($type, $namesearch, $alpha);

            $alphabet = explode(',', get_string('alphabet'));
            $strall   = get_string('all');


        /// Bar of first initials
            echo "<p style=\"text-align:center\">";
            echo "Last Name : ";
            if ($alpha) {
                echo " <a href=\"index.php?s=stu&amp;section=curr&amp;action=bulkedit&amp;id=$classid&amp;class=$classid&amp;" .
                     "sort=name&amp;dir=ASC&amp;perpage=$perpage\">$strall</a> ";
            } else {
                echo " <b>$strall</b> ";
            }
            foreach ($alphabet as $letter) {
                if ($letter == $alpha) {
                    echo " <b>$letter</b> ";
                } else {
                    echo " <a href=\"index.php?s=stu&amp;section=curr&amp;action=bulkedit&amp;id=$classid&amp;class=$classid&amp;" .
                         "action=bulkedit&amp;sort=name&amp;dir=ASC&amp;perpage=$perpage&amp;alpha=$letter\">$letter</a> ";
                }
            }
            echo "</p>";

            print_paging_bar($usercount, $page, $perpage,
                    "index.php?s=stu&amp;section=curr&amp;id=$classid&amp;class=$classid&amp;&amp;action=bulkedit&amp;" .
                    "sort=$sort&amp;dir=$dir&amp;perpage=$perpage&amp;alpha=$alpha&amp;stype=$type" .
                    "&amp;search=" . urlencode(stripslashes($namesearch)) . "&amp;");

            flush();

        } else {
            $user = $this->user;

            $user->name        = cm_fullname($user);
            $users[]           = $user;
            $usercount         = 0;
        }

        if (empty($this->id) && !$users) {
            $match = array();
            if ($namesearch !== '') {
               $match[] = s($namesearch);
            }
            if ($alpha) {
               $match[] = "name: {$alpha}___";
            }
            $matchstring = implode(", ", $match);
            echo 'No users matching '.$matchstring;

            $table = NULL;

        } else {
            $stuobj = new student();

            $table->width = "100%";
            foreach ($users as $user) {
                $newarr = array();

                foreach ($columns as $column => $cdesc) {
                    switch ($column) {
                        case 'unenrol':
                            $newarr[] = '<input type="checkbox" name="users[' . $user->id . '][unenrol]" value="1" />';
                            break;

                        case 'name':
                        case 'idnumber':
                        case 'description';
                            $newarr[] = $user->$column;
                            break;

                        case 'enrolmenttime':
                            $newarr[] = cm_print_date_selector('users[' . $user->id . '][startday]',
                                                               'users[' . $user->id . '][startmonth]',
                                                               'users[' . $user->id . '][startyear]',
                                                               $user->enrolmenttime, true);
                            break;

                        case 'completetime':
                            $newarr[] = cm_print_date_selector('users[' . $user->id . '][endday]',
                                                               'users[' . $user->id . '][endmonth]',
                                                               'users[' . $user->id . '][endyear]',
                                                               $user->completetime, true);
                            break;

                        case 'completestatusid':
                            $choices = array();

                            foreach(student::$completestatusid_values as $key => $csidv) {
                                $choices[$key] = get_string($csidv, 'block_curr_admin');
                            }

                            $newarr[] = cm_choose_from_menu($choices,
                                                            'users[' . $user->id . '][completestatusid]',
                                                            $user->completestatusid, '', '', '', true);
                            break;

                        case 'grade':
                            $newarr[] = '<input type="text" name="users[' . $user->id . '][grade]" ' .
                                        'value="' . $user->grade . '" size="5" />';
                            break;

                        case 'credits':
                            $newarr[] = '<input type="text" name="users[' . $user->id . '][credits]" ' .
                                        'value="' . $user->credits . '" size="5" />';
                            break;

                        case 'locked':
                            $newarr[] = '<input type="checkbox" name="users[' . $user->id . '][locked]" ' .
                                        'value="1" '.($user->locked?'checked="checked"':'').'/>'.
                                        '<input type="hidden" name="users[' . $user->id . '][idnumber]" '.
                                        'value="' . $user->idnumber . '" />' .
                                        '<input type="hidden" name="users[' . $user->id . '][association_id]" '.
                                        'value="' . $user->association_id . '" />';
                            break;

                        default:
                            $newarr[] = '';
                            break;
                    }
                }

                $table->data[] = $newarr;
            }
        }

        if (empty($this->id)) {
            echo "<table class=\"searchbox\" style=\"margin-left:auto;margin-right:auto\" cellpadding=\"10\"><tr><td>";
            echo "<form action=\"index.php\" method=\"get\"><fieldset>";
            echo '<input type="hidden" name="s" value="stu" />';
            echo '<input type="hidden" name="section" value="curr" />';
            echo '<input type="hidden" name="action" value="bulkedit" />';
            echo '<input type="hidden" name="id" value="' . $classid . '" />';
            echo '<input type="hidden" name="sort" value="' . $sort . '" />';
            echo '<input type="hidden" name="dir" value="' . $dir . '" />';
            /*echo '<input type="radio" name="stype" value="student" ' .
                 (($type == 'student') ? ' checked' : '') . '/> Students ' .
                 '<input type="radio" name="stype" value="instructor" ' .
                 (($type == 'instructor') ? ' checked' : '') . '/> Instructors ' .
                 '<input type="radio" name="stype" vale="" ' . (($type == '') ? ' checked' : '') . '/> All ';*/
            echo "<input type=\"text\" name=\"search\" value=\"".s($namesearch, true)."\" size=\"20\" />";
            echo "<input type=\"submit\" value=\"" . get_string('search', 'block_curr_admin') . "\" />";
            if ($namesearch) {
                echo "<input type=\"button\" onclick=\"document.location='index.php?s=stu&amp;section=curr&amp;" .
                     "action=bulkedit&amp;id=$classid';\" value=\"" . get_string('show_all_users', 'block_curr_admin') . "\" />";
            }
            echo "</fieldset></form>";
            echo "</td></tr></table>";

            echo '<form method="post" action="index.php?s=stu&amp;section=curr&amp;id=' . $classid . '" >'."\n";
            echo '<input type="hidden" name="action" value="updatemultiple" />'."\n";

        } else {
            echo '<form method="post" action="index.php?s=stu&amp;section=curr&amp;id=' . $classid . '" >'."\n";
            echo '<input type="hidden" name="action" value="updatemultiple" />'."\n";
            echo '<input type="hidden" name="association_id" value="' . $this->id . '" />' . "\n";
            echo '<input type="hidden" name="id" value="' . $this->classid . '" />' . "\n";
            echo '<input type="hidden" name="userid" value="' . $this->userid . '" />' . "\n";
        }

        if (!empty($table)) {
            if(empty($this->id)) {
                require_js($CFG->wwwroot . '/curriculum/js/classform.js');
                echo '<span class="checkbox selectall">';

                echo '<input type="checkbox" onclick="class_bulkedit_set_all_selected()"
                             id="class_bulkedit_select_all" name="class_bulkedit_select_all"/>';
                echo '<label for="class_bulkedit_select_all">' . get_string('bulkedit_select_all', 'block_curr_admin') . '</label>';
                echo '</span>';
            }

            print_table($table);
        }


        if (isset($this->cmclass->course) && is_object($this->cmclass->course) &&
            (get_class($this->cmclass->course) == 'course') &&
            ($elements = $this->cmclass->course->get_completion_elements())) {

            $select = "classid = {$this->classid} AND userid = {$this->userid}";
            $grades = $CURMAN->db->get_records_select(CLSGRTABLE, $select, 'id', 'completionid,id,classid,userid,grade,locked,timegraded,timemodified');

            $table = new stdClass;

            $columns = array(
                'element'          => 'Grade Element',
                'grade'            => 'Grade',
                'locked'           => 'Locked',
                'timegraded'       => 'Date Graded'
            );

            foreach ($columns as $column => $cdesc) {
                if ($sort != $column) {
                    $columnicon = "";
                    $columndir = "ASC";
                } else {
                    $columndir = $dir == "ASC" ? "DESC":"ASC";
                    $columnicon = $dir == "ASC" ? "down":"up";
                    $columnicon = " <img src=\"$CFG->pixpath/t/$columnicon.gif\" alt=\"\" />";

                }

                if (($column == 'name') || ($column == 'description')) {
                    $$column = "<a href=\"index.php?s=stu&amp;section=curr&amp;id=$classid&amp;class=$classid&amp;" .
                               "action=default&amp;sort=$column&amp;dir=$columndir&amp;stype=$type&amp;search=" .
                               urlencode(stripslashes($namesearch)) . "&amp;alpha=$alpha\">" .
                               $cdesc . "</a>$columnicon";
                } else {
                    $$column = $cdesc;
                }

                $table->head[]  = $$column;
                $table->align[] = "left";
                $table->wrap[]  = true;
            }

            $table->width = "100%";

            foreach ($elements as $element) {
                $newarr = array();
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
                            $newarr[] = '<input type="hidden" name="'.$name.'" ' .
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
                            $newarr[] = cm_print_date_selector($name.'[startday]',
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
                            $newarr[] = '<input type="text" name="'.$name.'" ' .
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
                            $newarr[] = '<input type="checkbox" name="'.$name.'" ' .
                                        'value="1" '.($value?'checked="checked"':'').'/>';
                            break;

                        default:
                            $newarr[] = '';
                            break;
                    }
                }
                $table->data[] = $newarr;
            }

            if (!empty($table)) {
                echo '<br />';
                print_table($table);
            }
        }

        if (!empty($users)) {
            echo '<br /><input type="submit" value="' . get_string('save_enrolment_changes', 'block_curr_admin') . '">'."\n";
        }

        echo "<input type=\"button\" onclick=\"document.location='index.php?s=stu&amp;section=curr&amp;" .
                     "action=default&amp;id=$classid&amp;sort=$sort&amp;dir=$dir&amp;perpage=$perpage&amp;alpha=$alpha&amp;search=" . urlencode(stripslashes($namesearch)) . "';\" value=\"Cancel\" />";

        echo '</form>'."\n";

        $output = ob_get_contents();
        ob_end_clean();

        return $output;
    }


    function attendance_form_html($formid='', $extraclass='', $rows='2', $cols='40') {
        $index = !empty($formid) ? '['.$formid.']' : '';
        $formid_suffix = !empty($formid) ? '_'.$formid : '';

        $output = '';

//        if (!$atn = cm_get_attendance($this->classid, $this->userid)) {
        if (!$atn = get_attendance($this->classid, $this->userid)) {
            $atn = new attendance();
        }

        $output .= '<style>'.$this->_editstyle.'</style>';
        $output .= '<fieldset id="cmclasseditform'.$formid.'" class="cmclasseditform '.$extraclass.'">'."\n";
        $output .= '<legend>' . get_string('edit_student_attendance', 'block_curr_admin') . '</legend>'."\n";

        $output .= '<label for="timestart'.$formid.'" id="ltimestart'.$formid.'">Start Date:<br />';
        $output .= cm_print_date_selector('startday', 'startmonth', 'startyear', $atn->timestart, true);
        $output .= '</label><br /><br />';

        $output .= '<label for="timeend'.$formid.'" id="ltimeend'.$formid.'">End Date:<br />';
        $output .= cm_print_date_selector('endday', 'endmonth', 'endyear', $atn->timeend, true);
        $output .= '</label><br /><br />';

        $output .= '<label for="note'.$formid.'" id="lnote'.$formid.'">Note:<br />';
        $output .= '<textarea name="note'.$index.'" cols="'.$cols.'" rows="'.$rows.'" '.
                   'id="note'.$formid.'" class="attendanceeditform '.$extraclass.'">'.$atn->note.
                   '</textarea>'."\n";
        $output .= '</label>';

        $output .= '<input type="hidden" name="id' . $index . '" value="' . $this->id . '" />'."\n";
        $output .= '<input type="hidden" name="class" value="' . $this->classid . '" />';
        $output .= '<input type="hidden" name="userid" value="' . $this->userid . '" />';
        $output .= '<input type="hidden" name="atnid' . $index . '" value="' . $atn->id . '" />' . "\n";
        $output .= '</fieldset>';

        return $output;
    }

    public function to_string() {
        return $this->user->idnumber . ' in ' . $this->cmclass->idnumber;
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

        /// Check for an existing enrolment - it can't already exist.
        if ($CURMAN->db->record_exists(STUTABLE, 'classid', $record->classid, 'userid', $record->userid)) {
            return true;
        }

        return false;
    }

    /**
     * Get a list of the existing students for the supplied (or current)
     * class. Regardless of status either passed failed or not completed.
     *
     * @uses $CURMAN
     * @paam int $cid A class ID (optional).
     * @return array An array of user records.
     */
    function get_students($cid = 0) {
        global $CURMAN;

        if (!$cid) {
            if (empty($this->classid)) {
                return array();
            }

            $cid = $this->classid;
        }

        $uids = array();

        if ($students = $CURMAN->db->get_records(STUTABLE, 'classid', $cid)) {
            foreach ($students as $student) {
                $uids[] = $student->userid;
            }
        }

        if (!empty($uids)) {
            $sql = "SELECT id, idnumber, username, firstname, lastname
                    FROM " . $CURMAN->db->prefix_table(USRTABLE) . "
                    WHERE id IN ( " . implode(', ', $uids) . " )
                    ORDER BY lastname ASC, firstname ASC";

            return $CURMAN->db->get_records_sql($sql);
        }

        return array();
    }

    /**
     * get the students on the waiting list for the supplied (or current) class
     * @param INT $cid the class id
     */
    public function get_waiting($cid = 0) {
        global $CURMAN;

        if (!$cid) {
            if (empty($this->classid)) {
                return array();
            }

            $cid = $this->classid;
        }

        $uids = array();

        if ($students = $CURMAN->db->get_records(WAITTABLE, 'classid', $cid)) {
            foreach ($students as $student) {
                $uids[] = $student->userid;
            }
        }

        if (!empty($uids)) {
            $sql = "SELECT id, idnumber, username, firstname, lastname
                    FROM " . $CURMAN->db->prefix_table(USRTABLE) . "
                    WHERE id IN ( " . implode(', ', $uids) . " )
                    ORDER BY lastname ASC, firstname ASC";

            return $CURMAN->db->get_records_sql($sql);
        }

        return array();
    }

    static public function get_waitlist_in_curriculum($userid, $curid) {
        global $CURMAN;
        $select  = 'SELECT wat.id wlid, wat.position, cls.idnumber clsid, crs.name, cls.* ';
        $tables = 'FROM ' . $CURMAN->db->prefix_table(CURCRSTABLE) . ' curcrs ';
        $join   = 'JOIN ' . $CURMAN->db->prefix_table(CRSTABLE) . ' crs ON curcrs.courseid = crs.id ';
        $join  .= 'JOIN ' . $CURMAN->db->prefix_table(CLSTABLE) . ' cls ON cls.courseid = crs.id ';
        $join  .= 'JOIN ' . $CURMAN->db->prefix_table(WAITTABLE) . ' wat ON wat.classid = cls.id ';
        $where  = 'WHERE curcrs.curriculumid = \'' . $curid . '\' ';
        $where .= 'AND wat.userid = \'' . $userid . '\' ';
        $sort = 'ORDER BY curcrs.position';

        $sql = $select.$tables.$join.$where.$sort;

        return $CURMAN->db->get_records_sql($sql);
    }

    /**
     * gets a list of classes that the given (or current) student is a part of
     * filters are applied to classid_number
     * @global object $CURMAN
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

        global $CURMAN;

        if(!$cuserid) {
            if(empty($this->userid)) {
                return array();
            }

            $cuserid = $this->userid;
        }

        $LIKE = $CURMAN->db->sql_compare();

        $select  = 'SELECT wat.id wlid, wat.position, cls.idnumber clsid, crs.name, cls.*';
        $tables  = 'FROM ' . $CURMAN->db->prefix_table(WAITTABLE) . ' wat ';
        $join    = 'JOIN ' . $CURMAN->db->prefix_table(CLSTABLE) . ' cls ON wat.classid = cls.id ';
        $join   .= 'JOIN ' . $CURMAN->db->prefix_table(CRSTABLE) . ' crs ON cls.courseid = crs.id ';
        $where   = 'wat.userid = ' . $cuserid . ' ';

        if (!empty($namesearch)) {
            $namesearch = trim($namesearch);
            $where .= (!empty($where) ? ' AND ' : '') . "((crs.name $LIKE '%$namesearch%') OR " .
                      "(cls.idnumber $LIKE '%$namesearch%')) ";
        }

        if ($alpha) {
            $where .= (!empty($where) ? ' AND ' : '') . "(crs.name $LIKE '$alpha%') ";
        }

        if (!empty($where)) {
            $where = 'WHERE '.$where.' ';
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


        $sql = $select.$tables.$join.$where.$sort.$limit;

        return $CURMAN->db->get_records_sql($sql);
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
        global $CURMAN;

        if (!$classid) {
            if (empty($this->classid)) {
                return 0;
            }
            $classid = $this->classid;
        }

        $LIKE     = $CURMAN->db->sql_compare();
        $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');

        $select  = 'SELECT stu.* ';
        $select .= ', ' . $FULLNAME . ' as name, usr.idnumber ';
    //    $select .= ', ' . $FULLNAME . ' as name, usr.type as description ';
        $tables  = 'FROM ' . $CURMAN->db->prefix_table(STUTABLE) . ' stu ';
        $join    = 'JOIN ' . $CURMAN->db->prefix_table(USRTABLE) . ' usr ';
        $on      = 'ON stu.userid = usr.id ';
        $where   = 'stu.classid = \'' . $classid . '\'';

        if (empty($CURMAN->config->legacy_show_inactive_users)) {
            $where .= ' AND usr.inactive = 0';
        }

        if (!empty($namesearch)) {
            $namesearch = trim($namesearch);
            $where .= " AND (($FULLNAME $LIKE '%$namesearch%') OR " .
                      "(usr.idnumber $LIKE '%$namesearch%')) ";
        }

        if ($alpha) {
            $where .= " AND (usr.lastname $LIKE '$alpha%') ";
        }

        $where = 'WHERE '. $where .' ';

        if ($sort) {
            if ($sort == 'name') { // TBV: ELIS-2772
                $sort = "ORDER BY usr.lastname {$dir}, usr.firstname {$dir} ";
            } else {
                $sort = 'ORDER BY '.$sort .' '. $dir.' ';
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


        $sql = $select.$tables.$join.$on.$where.$sort.$limit;

        return $CURMAN->db->get_records_sql($sql);
    }

    /**
     * counts the number of students enroled in the supplied (or current) class
     * That have not yet completed the class.
     *
     * @uses $CURMAN
     * @param INT $classid class id number
     * @param STR $namesearch name of the users being searched for
     * @param STR $alpha starting letter of the user being searched for
     * @return INT
     */
    public function count_enroled($classid = 0, $namesearch = '', $alpha = '') {
        global $CURMAN;

        if (!$classid) {
            if (empty($this->classid)) {
                return 0;
            }
            $classid = $this->classid;
        }

        $LIKE     = $CURMAN->db->sql_compare();
        $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');

        $select  = 'SELECT COUNT(stu.id) ';
        $tables  = 'FROM ' . $CURMAN->db->prefix_table(STUTABLE) . ' stu ';
        $join    = 'JOIN ' . $CURMAN->db->prefix_table(USRTABLE) . ' usr ';
        $on      = 'ON stu.userid = usr.id ';
        $where   = 'stu.completestatusid = ' . STUSTATUS_NOTCOMPLETE . ' AND stu.classid = \'' . $classid . '\'';

        if (empty($CURMAN->config->legacy_show_inactive_users)) {
            $where .= ' AND usr.inactive = 0';
        }

        if (!empty($namesearch)) {
            $namesearch = trim($namesearch);
            $where .= " AND ($FULLNAME $LIKE '%$namesearch%') ";
        }

        if ($alpha) {
            $where .= " AND (usr.lastname $LIKE '$alpha%') ";
        }

        $where = 'WHERE '. $where .' ';

        $sql = $select . $tables . $join . $on . $where;
        return $CURMAN->db->count_records_sql($sql);
    }

    /**
     * Count the number of students for this class.
     *
     * @uses $CURMAN
     * @param int $classid The class ID.
     */
    public function count_records($classid = 0, $namesearch = '', $alpha = '') {
        global $CURMAN;

        if (!$classid) {
            if (empty($this->classid)) {
                return 0;
            }
            $classid = $this->classid;
        }

        $LIKE     = $CURMAN->db->sql_compare();
        $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');

        $select  = 'SELECT COUNT(stu.id) ';
        $tables  = 'FROM ' . $CURMAN->db->prefix_table(STUTABLE) . ' stu ';
        $join    = 'JOIN ' . $CURMAN->db->prefix_table(USRTABLE) . ' usr ';
        $on      = 'ON stu.userid = usr.id ';
        $where   = array('stu.classid = \'' . $classid . '\'');

        if (!empty($namesearch)) {
            $namesearch = trim($namesearch);
            $where[] = "($FULLNAME $LIKE '%$namesearch%')";
        }

        if ($alpha) {
            $where[] = "(usr.lastname $LIKE '$alpha%')";
        }
        if (empty($CURMAN->config->legacy_show_inactive_users)) {
            $where[] = 'usr.inactive = 0';
        }

        $where = 'WHERE ' . implode(' AND ', $where);

        $sql = $select . $tables . $join . $on . $where;
        return $CURMAN->db->count_records_sql($sql);
    }

    /**
     * Get a list of the available students not already attached to this course.
     *
     * @uses $CURMAN
     * @param string $search A search filter.
     * @return array An array of user records.
     */
    function get_users_avail($sort = 'name', $dir = 'ASC', $startrec = 0,
                             $perpage = 0, $namesearch = '', $alpha = '') {
        global $CFG, $CURMAN, $USER;

        if (empty($CURMAN->db)) {
            return NULL;
        }

        $LIKE     = $CURMAN->db->sql_compare();
        $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');

//        $select  = 'SELECT usr.id, usr.idnumber, ' . $FULLNAME . ' as name, usr.type as description, ' .
        $select  = 'SELECT usr.id, usr.idnumber, ' . $FULLNAME . ' as name, ' .
                   'stu.classid, stu.userid, stu.enrolmenttime, stu.completetime, ' .
                   'stu.completestatusid, stu.grade ';
        $tables  = 'FROM ' . $CURMAN->db->prefix_table(USRTABLE) . ' usr ';
        $join    = 'LEFT JOIN ' . $CURMAN->db->prefix_table(STUTABLE) . ' stu ';
        $on      = "ON stu.userid = usr.id AND stu.classid = $this->classid ";
        $where   = 'stu.id IS NULL';

        if (!empty($namesearch)) {
            $namesearch = trim($namesearch);
            $where     .= " AND (($FULLNAME $LIKE '%$namesearch%') OR " .
                          "(usr.idnumber $LIKE '%$namesearch%')) ";
        }

        if ($alpha) {
            $where .= " AND ($FULLNAME $LIKE '$alpha%') ";
        }

        if (empty($CURMAN->config->legacy_show_inactive_users)) {
            $where .= ' AND usr.inactive = 0 ';
        }

        $uids = array();
        if ($users = $this->get_students()) {
            foreach ($users as $user) {
                $uids[] = $user->id;
            }
        }

        if ($users = $this->get_waiting()) {
            foreach ($users as $user) {
                $uids[] = $user->id;
            }
        }

        $ins = new instructor();
        if ($users = $ins->get_instructors()) {
            foreach ($users as $user) {
                $uids[] = $user->id;
            }
        }

        if (!empty($uids)) {
            $where .= ' AND usr.id NOT IN ( '. implode(', ', $uids) .' ) ';
        }

        $where = 'WHERE '. $where .' ';

        if (!cmclasspage::_has_capability('block/curr_admin:class:enrol', $this->classid)) {
            //perform SQL filtering for the more "conditional" capability

            $allowed_clusters = cmclass::get_allowed_clusters($this->classid);

            if (empty($allowed_clusters)) {
                $where .= 'AND 0=1';
            } else {
                $cluster_filter = implode(',', $allowed_clusters);
                $where .= "AND usr.id IN (
                             SELECT userid FROM " . $CURMAN->db->prefix_table(CLSTUSERTABLE) . "
                             WHERE clusterid IN ({$cluster_filter}))";
            }
        }

        if ($sort) {
            if ($sort == 'name') { // TBV: ELIS-2772
                $sort = "ORDER BY usr.lastname {$dir}, usr.firstname {$dir} ";
            } else {
                $sort = 'ORDER BY '.$sort .' '. $dir.' ';
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

        $sql = $select.$tables.$join.$on.$where.$sort.$limit;

        return $CURMAN->db->get_records_sql($sql);
    }


    function count_users_avail($namesearch = '', $alpha = '') {
        global $CFG, $CURMAN, $USER;

        $LIKE     = $CURMAN->db->sql_compare();
        $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');

        $select  = 'SELECT COUNT(usr.id) ';
        $tables  = 'FROM ' . $CURMAN->db->prefix_table(USRTABLE) . ' usr ';
        $join    = 'LEFT JOIN ' . $CURMAN->db->prefix_table(STUTABLE) . ' stu ';
        $on      = "ON stu.userid = usr.id AND stu.classid = $this->classid ";
        $where   = 'stu.id IS NULL';

        if (!empty($namesearch)) {
            $namesearch = trim($namesearch);
            $where     .= (!empty($where) ? ' AND ' : '') . "($FULLNAME $LIKE '%$namesearch%') OR " .
                          "(usr.idnumber $LIKE '%$namesearch%') ";
        }

        if ($alpha) {
            $where .= (!empty($where) ? ' AND ' : '') . "($FULLNAME $LIKE '$alpha%') ";
        }

        $uids = array();
        if ($users = $this->get_students()) {
            foreach ($users as $user) {
                $uids[] = $user->id;
            }
        }

        if($users = $this->get_waiting()) {
            foreach ($users as $user) {
                $uids[] = $user->id;
            }
        }

        $ins = new instructor();
        if ($users = $ins->get_instructors()) {
            foreach ($users as $user) {
                $uids[] = $user->id;
            }
        }

        if (!empty($uids)) {
            $where .= (!empty($where) ? ' AND ' : '') . 'usr.id NOT IN ( ' .
                      implode(', ', $uids) . ' ) ';
        }

        if (!empty($where)) {
            $where = 'WHERE '.$where.' ';
        }

        if(!cmclasspage::_has_capability('block/curr_admin:class:enrol', $this->classid)) {
            //perform SQL filtering for the more "conditional" capability

            $allowed_clusters = cmclass::get_allowed_clusters($this->classid);

            if(empty($allowed_clusters)) {
                $where .= 'AND 0=1';
            } else {
                $cluster_filter = implode(',', $allowed_clusters);
                $where .= "AND usr.id IN (
                             SELECT userid FROM " . $CURMAN->db->prefix_table(CLSTUSERTABLE) . "
                             WHERE clusterid IN ({$cluster_filter}))";
            }
        }

        $sql = $select.$tables.$join.$on.$where;

        return $CURMAN->db->count_records_sql($sql);
    }

    /**
     * Get a list of the students already attached to this course.
     *
     * @uses $CURMAN
     * @param string $search A search filter.
     * @return array An array of user records.
     */
    function get_users_enrolled($type = '', $sort = 'name', $dir = 'ASC', $startrec = 0,
                             $perpage = 0, $namesearch = '', $alpha = '') {
        global $CFG, $CURMAN;

        if (empty($CURMAN->db)) {
            return NULL;
        }

        $LIKE     = $CURMAN->db->sql_compare();
        $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');

//        $select  = 'SELECT usr.id, usr.idnumber, ' . $FULLNAME . ' as name, usr.type as description, ' .
        $select  = 'SELECT usr.id, usr.idnumber, ' . $FULLNAME . ' as name, ' .
                   'stu.classid, stu.userid, usr.idnumber AS user_idnumber, stu.enrolmenttime, stu.completetime, ' .
                   'stu.completestatusid, stu.grade, stu.id as association_id, stu.credits, stu.locked ';
        $tables  = 'FROM ' . $CURMAN->db->prefix_table(USRTABLE) . ' usr ';
        $join    = 'LEFT JOIN ' . $CURMAN->db->prefix_table(STUTABLE) . ' stu ';
        $on      = 'ON stu.userid = usr.id ';

        /// If limiting returns to specific teams, set that up now.
        if (!empty($CFG->curr_configteams)) {
            $where = 'usr.team IN ('.$CFG->curr_configteams.') ';
        } else {
            $where = '';
        }

        if (!empty($namesearch)) {
            $namesearch = trim($namesearch);
            $where     .= (!empty($where) ? ' AND ' : '') . "(($FULLNAME $LIKE '%$namesearch%') OR " .
                          "(usr.idnumber $LIKE '%$namesearch%')) ";
        }

        if ($alpha) {
            $where .= (!empty($where) ? ' AND ' : '') . "(usr.lastname $LIKE '$alpha%') ";
        }

        $where .= (!empty($where) ? ' AND ' : '') . "classid=$this->classid ";

        $where = "WHERE $where ";

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


    function count_users_enrolled($type = '', $namesearch = '', $alpha = '') {
        global $CFG, $CURMAN;

        $LIKE     = $CURMAN->db->sql_compare();
        $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');

        $select  = 'SELECT COUNT(usr.id) ';
        $tables  = 'FROM ' . $CURMAN->db->prefix_table(USRTABLE) . ' usr ';
        $join    = 'LEFT JOIN ' . $CURMAN->db->prefix_table(STUTABLE) . ' stu ';
        $on      = 'ON stu.userid = usr.id ';

        /// If limiting returns to specific teams, set that up now.
        if (!empty($CFG->curr_configteams)) {
            $where = 'usr.team IN ('.$CFG->curr_configteams.') ';
        } else {
            $where = '';
        }

        if (!empty($namesearch)) {
            $namesearch = trim($namesearch);
            $where     .= (!empty($where) ? ' AND ' : '') . "($FULLNAME $LIKE '%$namesearch%') OR " .
                          "(usr.idnumber $LIKE '%$namesearch%') ";
        }

        if ($alpha) {
            $where .= (!empty($where) ? ' AND ' : '') . "($FULLNAME $LIKE '$alpha%') ";
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

        $where .= (!empty($where) ? ' AND ' : '') . "classid=$this->classid ";

        $where = "WHERE $where ";

        $sql = $select.$tables.$join.$on.$where;

        return $CURMAN->db->count_records_sql($sql);
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
     *
     * @return  boolean            TRUE is successful, otherwise FALSE
     */

    public static function class_notstarted_handler($student) {
        global $CFG, $CURMAN;
        require_once($CFG->dirroot.'/curriculum/lib/notifications.php');

        /// Does the user receive a notification?
        $sendtouser       = $CURMAN->config->notify_classnotstarted_user;
        $sendtorole       = $CURMAN->config->notify_classnotstarted_role;
        $sendtosupervisor = $CURMAN->config->notify_classnotstarted_supervisor;

        /// If nobody receives a notification, we're done.
        if (!$sendtouser && !$sendtorole && !$sendtosupervisor) {
            return true;
        }

        if (!empty($student->moodlecourseid)) {
            if (!($context = get_context_instance(CONTEXT_COURSE, $student->moodlecourseid))) {
                debugging(get_string('invalidcontext'));
                return true;
            }
        } else {
            $context = get_system_context();
        }

        $message = new notification();

        /// Set up the text of the message
        $text = empty($CURMAN->config->notify_classnotstarted_message) ?
                    get_string('notifyclassnotstartedmessagedef', 'block_curr_admin') :
                    $CURMAN->config->notify_classnotstarted_message;
        $search = array('%%userenrolname%%', '%%classname%%', '%%coursename%%');
        $replace = array(fullname($student->user), $student->cmclass->idnumber,
                         $student->cmclass->course->name);
        $text = str_replace($search, $replace, $text);

        $eventlog = new Object();
        $eventlog->event = 'class_notstarted';
        $eventlog->instance = $student->classid;
        $eventlog->fromuserid = $student->userid;
        if ($sendtouser) {
            $message->send_notification($text, $student->user, null, $eventlog);
        }

        $users = array();

        if ($sendtorole) {
            /// Get all users with the notify_classnotstart capability.
            if ($roleusers = get_users_by_capability($context, 'block/curr_admin:notify_classnotstart')) {
                $users = $users + $roleusers;
            }
        }

        if ($sendtosupervisor) {
            /// Get parent-context users.
            if ($supervisors = cm_get_users_by_capability('user', $student->userid, 'block/curr_admin:notify_classnotstart')) {
                $users = $users + $supervisors;
            }
        }

        $userfrom = new user($student->userid);

        foreach ($users as $user) {
            $message->send_notification($text, $user, $userfrom, $eventlog);
        }

        return true;
    }

    /**
     * Function to handle class not completed events.
     *
     * @param   student  $student  The class enrolment / student object who is "not completed"
     *
     * @return  boolean            TRUE is successful, otherwise FALSE
     */

    public static function class_notcompleted_handler($student) {
        global $CFG, $CURMAN;
        require_once($CFG->dirroot.'/curriculum/lib/notifications.php');

        /// Does the user receive a notification?
        $sendtouser = $CURMAN->config->notify_classnotcompleted_user;
        $sendtorole = $CURMAN->config->notify_classnotcompleted_role;
        $sendtosupervisor = $CURMAN->config->notify_classnotcompleted_supervisor;

        /// If nobody receives a notification, we're done.
        if (!$sendtouser && !$sendtorole && !$sendtosupervisor) {
            return true;
        }

        if (!empty($student->moodlecourseid)) {
            if (!($context = get_context_instance(CONTEXT_COURSE, $student->moodlecourseid))) {
                debugging(get_string('invalidcontext'));
                return true;
            }
        } else {
            $context = get_system_context();
        }

        /// Make sure this is a valid user.
        $enroluser = new user($student->userid);
        if (empty($enroluser->id)) {
            print_error('nouser', 'block_curr_admin');
            return true;
        }

        $message = new notification();

        /// Set up the text of the message
        $text = empty($CURMAN->config->notify_classnotcompleted_message) ?
                    get_string('notifyclassnotcompletedmessagedef', 'block_curr_admin') :
                    $CURMAN->config->notify_classnotcompleted_message;
        $search = array('%%userenrolname%%', '%%classname%%', '%%coursename%%');
        $replace = array(fullname($student->user), $student->cmclass->idnumber,
                         $student->cmclass->course->name);
        $text = str_replace($search, $replace, $text);

        $eventlog = new Object();
        $eventlog->event = 'class_notcompleted';
        $eventlog->instance = $student->classid;
        $eventlog->fromuserid = $student->userid;
        if ($sendtouser) {
            $message->send_notification($text, $student->user, null, $eventlog);
        }

        $users = array();

        if ($sendtorole) {
            /// Get all users with the notify_classnotcomplete capability.
            if ($roleusers = get_users_by_capability($context, 'block/curr_admin:notify_classnotcomplete')) {
                $users = $users + $roleusers;
            }
        }

        if ($sendtosupervisor) {
            /// Get parent-context users.
            if ($supervisors = cm_get_users_by_capability('user', $student->userid, 'block/curr_admin:notify_classnotcomplete')) {
                $users = $users + $supervisors;
            }
        }

        foreach ($users as $user) {
            $message->send_notification($text, $user, $enroluser, $eventlog);
        }

        return true;
    }

    /**
     * Determines whether the current user is allowed to create, edit, and delete associations
     * between a user and a class
     *
     * @param    int      $userid    The id of the user being associated to the class
     * @param    int      $classid   The id of the class we are associating the user to
     *
     * @return   boolean             True if the current user has the required permissions, otherwise false
     */
    public static function can_manage_assoc($userid, $classid) {
        global $USER;

        if(!cmclasspage::can_enrol_into_class($classid)) {
            //the users who satisfty this condition are a superset of those who can manage associations
            return false;
        } else if (cmclasspage::_has_capability('block/curr_admin:track:enrol', $classid)) {
            //current user has the direct capability
            return true;
        }

        //get the context for the "indirect" capability
        $context = cm_context_set::for_user_with_capability('cluster', 'block/curr_admin:class:enrol_cluster_user', $USER->id);

        $allowed_clusters = array();

        $allowed_clusters = cmclass::get_allowed_clusters($classid);

        //query to get users associated to at least one enabling cluster
        $cluster_select = '';
        if(empty($allowed_clusters)) {
            $cluster_select = '0=1';
        } else {
            $cluster_select = 'clusterid IN (' . implode(',', $allowed_clusters) . ')';
        }
        $select = "userid = {$userid} AND {$cluster_select}";

        //user just needs to be in one of the possible clusters
        if(record_exists_select(CLSTUSERTABLE, $select)) {
            return true;
        }

        return false;
    }
}


class student_grade extends datarecord {

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

    /**
     * Contructor.
     *
     * @param $studentdata int/object/array The data id of a data record or data elements to load manually.
     *
     */
    function student_grade($sgradedata=false) {
        $this->set_table(GRDTABLE);
        $this->add_property('id', 'int');
        $this->add_property('classid', 'int');
        $this->add_property('userid', 'int');
        $this->add_property('completionid', 'int');
        $this->add_property('grade', 'float');
        $this->add_property('locked', 'int');
        $this->add_property('timegraded', 'int');
        $this->add_property('timemodified', 'int');

        $this->completestatusid_values = array(
            STUSTATUS_NOTCOMPLETE => 'Not Completed',
            STUSTATUS_FAILED      => 'Failed',
            STUSTATUS_PASSED      => 'Passed'
        );

        $this->_editstyle = '
.attendanceeditform input,
.attendanceeditform textarea {
    margin: 0;
    display: block;
}
        ';

        if (is_numeric($sgradedata)) {
            $this->data_load_record($sgradedata);
        } else if (is_array($sgradedata)) {
            $this->data_load_array($sgradedata);
        } else if (is_object($sgradedata)) {
            $this->data_load_array(get_object_vars($sgradedata));
        }
    }


/////////////////////////////////////////////////////////////////////
//                                                                 //
//  STANDARD FUNCTIONS:                                                //
//                                                                 //
/////////////////////////////////////////////////////////////////////

    public static function delete_for_class($id) {
    	global $CURMAN;

    	return $CURMAN->db->delete_records(GRDTABLE, 'classid', $id);
    }

	public static function delete_for_user($id) {
		global $CURMAN;

		return $CURMAN->db->delete_records(GRDTABLE, 'userid', $id);
	}

	public static function delete_for_user_and_class($userid, $classid) {
    	global $CURMAN;

    	return $CURMAN->db->delete_records(GRDTABLE, 'userid', $userid, 'classid', $classid);
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
     *
     * @return string The form HTML, without the form.
     */
    function edit_form_html($classid, $type = '', $sort = 'name', $dir = 'ASC', $page = 0,
                            $perpage = 0, $namesearch = '', $alpha = '') {
        global $CFG;

        $output = '';
        ob_start();

        $table = new stdClass;

        $columns = array(
            'grade'            => 'Grade',
            'locked'           => 'Locked',
            'timegraded'       => 'Date Graded'
        );

        foreach ($columns as $column => $cdesc) {
            if ($sort != $column) {
                $columnicon = "";
                $columndir = "ASC";
            } else {
                $columndir = $dir == "ASC" ? "DESC":"ASC";
                $columnicon = $dir == "ASC" ? "down":"up";
                $columnicon = " <img src=\"$CFG->pixpath/t/$columnicon.gif\" alt=\"\" />";

            }

            if (($column == 'name') || ($column == 'description')) {
                $$column = "<a href=\"index.php?s=stu&amp;section=curr&amp;class=$classid&amp;" .
                           "action=add&amp;sort=$column&amp;dir=$columndir&amp;stype=$type&amp;search=" .
                           urlencode(stripslashes($namesearch)) . "&amp;alpha=$alpha\">" .
                           $cdesc . "</a>$columnicon";
            } else {
                $$column = $cdesc;
            }

            $table->head[]  = $$column;
            $table->align[] = "left";
            $table->wrap[]  = true;
        }

        $table->width = "100%";
        $newarr = array();

        foreach ($columns as $column => $cdesc) {
            switch ($column) {
                case 'timegraded':
                    $newarr[] = cm_print_date_selector('users[' . $user->id . '][startday]',
                                                       'users[' . $user->id . '][startmonth]',
                                                       'users[' . $user->id . '][startyear]',
                                                       $this->timegraded, true);
                    break;

                case 'grade':
                    $newarr[] = '<input type="text" name="users[' . $user->id . '][grade]" ' .
                                'value="' . $this->grade . '" size="5" />';
                    break;

                case 'locked':
                    $newarr[] = '<input type="checkbox" name="users[' . $user->id . '][locked]" ' .
                                'value="1" '.($this->locked?'checked="checked"':'').'/>';
                    break;

                default:
                    $newarr[] = '';
                    break;
            }

            $table->data[] = $newarr;
        }

        if (empty($this->id)) {
            echo "<table class=\"searchbox\" style=\"margin-left:auto;margin-right:auto\" cellpadding=\"10\"><tr><td>";
            echo "<form action=\"index.php\" method=\"get\"><fieldset>";
            echo '<input type="hidden" name="s" value="stu" />';
            echo '<input type="hidden" name="section" value="curr" />';
            echo '<input type="hidden" name="action" value="add" />';
            echo '<input type="hidden" name="class" value="' . $classid . '" />';
            echo '<input type="hidden" name="sort" value="' . $sort . '" />';
            echo '<input type="hidden" name="dir" value="' . $dir . '" />';
            echo '<input type="radio" name="stype" value="student" ' .
                 (($type == 'student') ? ' checked' : '') . '/> Students ' .
                 '<input type="radio" name="stype" value="instructor" ' .
                 (($type == 'instructor') ? ' checked' : '') . '/> Instructors ' .
                 '<input type="radio" name="stype" vale="" ' . (($type == '') ? ' checked' : '') . '/> All ';
            echo "<input type=\"text\" name=\"search\" value=\"".s($namesearch, true)."\" size=\"20\" />";
            echo "<input type=\"" . get_string('search', 'block_curr_admin') . "\" value=\"Search\" />";
            if ($namesearch) {
                echo "<input type=\"button\" onclick=\"document.location='index.php?s=stu&amp;section=curr&amp;" .
                     "action=add&amp;id=$classid';\" value=\"Show All Users\" />";
            }
            echo "</fieldset></form>";
            echo "</td></tr></table>";

            echo '<form method="post" action="index.php?s=stu&amp;section=curr&amp;class=' . $classid . '" >'."\n";
            echo '<input type="hidden" name="action" value="savenew" />'."\n";

        } else {
            echo '<form method="post" action="index.php?s=stu&amp;section=curr&amp;class=' . $classid . '" >'."\n";
            echo '<input type="hidden" name="action" value="update" />'."\n";
            echo '<input type="hidden" name="association_id" value="' . $this->id . '" />' . "\n";
            echo '<input type="hidden" name="id" value="' . $this->classid . '" />' . "\n";
            echo '<input type="hidden" name="userid" value="' . $this->userid . '" />' . "\n";
        }

        if (!empty($table)) {
            print_table($table);
        }

        if (empty($this->id)) {
            echo '<br /><input type="submit" value="' . get_string('add_grade', 'block_curr_admin') . '">'."\n";
        } else {
            echo '<br /><input type="submit" value="' . get_string('update_grade', 'block_curr_admin') . '">'."\n";
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
        global $CURMAN;

        if(empty($record)) {
            $record = $this;
        }

        if ($CURMAN->db->record_exists(CLSGRTABLE, 'classid', $record->classid, 'userid', $record->userid, 'completionid', $record->completionid)) {
            return true;
        }

        return false;
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
 * @return object array Returned records.
 */

function student_get_listing($classid, $sort='name', $dir='ASC', $startrec=0, $perpage=0, $namesearch='',
                             $alpha='') {
    global $CURMAN;

    $LIKE     = $CURMAN->db->sql_compare();
    $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');

    $select  = 'SELECT stu.* ';
    $select .= ', ' . $FULLNAME . ' as name, usr.idnumber ';
//    $select .= ', ' . $FULLNAME . ' as name, usr.type as description ';
    $tables  = 'FROM ' . $CURMAN->db->prefix_table(STUTABLE) . ' stu ';
    $join    = 'LEFT JOIN ' . $CURMAN->db->prefix_table(USRTABLE) . ' usr ';
    $on      = 'ON stu.userid = usr.id ';
    $where   = 'stu.classid = \'' . $classid . '\'';

    if (!empty($namesearch)) {
        $namesearch = trim($namesearch);
        $where .= " AND (($FULLNAME $LIKE '%$namesearch%') OR " .
                  "(usr.idnumber $LIKE '%$namesearch%')) ";
    }

    if ($alpha) {
        $where .= " AND (usr.lastname $LIKE '$alpha%') ";
    }

    if (empty($CURMAN->config->legacy_show_inactive_users)) {
        $where .= ' AND usr.inactive = 0 ';
    }

    $where = 'WHERE '. $where .' ';

    if ($sort) {
        if ($sort == 'name') { // TBV: ELIS-2772
            $sort = "ORDER BY usr.lastname {$dir}, usr.firstname {$dir} ";
        } else {
            $sort = 'ORDER BY '.$sort .' '. $dir.' ';
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


    $sql = $select.$tables.$join.$on.$where.$sort.$limit;

    return $CURMAN->db->get_records_sql($sql);
}


/**
 * Count the number of students for this class.
 *
 * @uses $CURMAN
 * @param int $classid The class ID.
 */
function student_count_records($classid, $namesearch = '', $alpha = '') {
    global $CURMAN;

    $LIKE     = $CURMAN->db->sql_compare();
    $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');

    $select  = 'SELECT COUNT(stu.id) ';
    $tables  = 'FROM ' . $CURMAN->db->prefix_table(STUTABLE) . ' stu ';
    $join    = 'LEFT JOIN ' . $CURMAN->db->prefix_table(USRTABLE) . ' usr ';
    $on      = 'ON stu.userid = usr.id ';
    $where   = 'stu.classid = \'' . $classid . '\'';

    if (!empty($namesearch)) {
        $namesearch = trim($namesearch);
        $where .= " AND ($FULLNAME $LIKE '%$namesearch%') ";
    }

    if ($alpha) {
        $where .= " AND (usr.lastname $LIKE '$alpha%') ";
    }

    if (empty($CURMAN->config->legacy_show_inactive_users)) {
        $where .= ' AND usr.inactive = 0 ';
    }

    $where = 'WHERE '. $where .' ';

    $sql = $select . $tables . $join . $on . $where;
    return $CURMAN->db->count_records_sql($sql);
}


/**
 * Get a full list of the classes that a student is enrolled in.
 *
 * @uses $CURMAN
 * @param int $userid The user ID to get classes for.
 * @param int $curid  Optional curriculum ID to limit classes to.
 * @return array An array of class and student enrolment data.
 */
function student_get_student_classes($userid, $curid = 0) {
    global $CURMAN;

    if (empty($curid)) {
        $sql = "SELECT cls.*, stu.enrolmenttime, stu.completetime, stu.completestatusid
                FROM " . $CURMAN->db->prefix_table(STUTABLE) . " stu
                INNER JOIN " . $CURMAN->db->prefix_table(CLSTABLE) . " cls ON stu.classid = cls.id
                WHERE stu.userid = $userid";
    } else {
        $sql = "SELECT cls.*, stu.enrolmenttime, stu.completetime, stu.completestatusid
                FROM " . $CURMAN->db->prefix_table(STUTABLE) . " stu
                INNER JOIN " . $CURMAN->db->prefix_table(CLSTABLE) . " cls ON stu.classid = cls.id
                INNER JOIN " . $CURMAN->db->prefix_table(CURCRSTABLE) . " curcrs ON cls.courseid = curcrs.courseid
                WHERE stu.userid = $userid
                AND curcrs.curriculumid = $curid";
    }

    return $CURMAN->db->get_records_sql($sql);
}


/**
 * Attempt to get the class information about a class that a student is enrolled
 * in for a specific course in the system.
 *
 * @uses $CURMAN
 * @param int $crsid The course ID
 * @return
 */
function student_get_class_from_course($crsid, $userid) {
    global $CURMAN;

    $sql = "SELECT cls.*, stu.enrolmenttime, stu.completetime, stu.completestatusid, stu.grade
            FROM " . $CURMAN->db->prefix_table(STUTABLE) . " stu
            INNER JOIN " . $CURMAN->db->prefix_table(CLSTABLE) . " cls ON stu.classid = cls.id
            WHERE stu.userid = $userid
            AND cls.courseid = $crsid";

    return $CURMAN->db->get_records_sql($sql);
}

?>
