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
require_once CURMAN_DIRLOCATION . '/lib/course.class.php';
require_once CURMAN_DIRLOCATION . '/lib/curriculum.class.php';
require_once CURMAN_DIRLOCATION . '/lib/curriculumcourse.class.php';
require_once CURMAN_DIRLOCATION . '/lib/student.class.php';
require_once CURMAN_DIRLOCATION . '/lib/certificate.php';


define('CURASSTABLE', 			     'crlm_curriculum_assignment');
define('CURR_EXPIRE_ENROL_START',    1);
define('CURR_EXPIRE_ENROL_COMPLETE', 2);


class curriculumstudent extends datarecord {
/*
    var $id;           // INT - The data ID if in the database.
    var $userid;       // INT - The user ID.
    var $user;         // OBJECT - The user database object.
    var $curriculumid; // INT - The curriculum ID.
    var $curriculum;   // OBJECT - The curriculum database object.
    var $timecreated;  // INT - The time created (timestamp).
    var $timemodified; // INT - The time modified (timestamp).

    var $_dbloaded;         // BOOLEAN - True if loaded from database.
*/
    // STRING - Styles to use for edit form.
    var $_editstyle = '
.curriculumstudenteditform input,
.curriculumstudenteditform textarea,
.curriculumstudenteditform select {
    margin: 0;
    display: block;
}
';


    /**
     * Contructor.
     *
     * @param $curriculumstudentdata int/object/array The data id of a data record or data elements to load manually.
     *
     */
    function curriculumstudent($curriculumstudentdata = false) {
        parent::datarecord();

        $this->set_table(CURASSTABLE);
        $this->add_property('id', 'int');
        $this->add_property('userid', 'int');
        $this->add_property('curriculumid', 'int');
        $this->add_property('completed', 'int');
        $this->add_property('timecompleted', 'int');
        $this->add_property('timeexpired', 'int');
        $this->add_property('credits', 'float');
        $this->add_property('locked', 'int');
        $this->add_property('certificatecode', 'string');
        $this->add_property('timecreated', 'int');
        $this->add_property('timemodified', 'int');

        if (is_numeric($curriculumstudentdata)) {
            $this->data_load_record($curriculumstudentdata);
        } else if (is_array($curriculumstudentdata)) {
            $this->data_load_array($curriculumstudentdata);
        } else if (is_object($curriculumstudentdata)) {
            $this->data_load_array(get_object_vars($curriculumstudentdata));
        }

        if (!empty($this->userid)) {
            $this->user = new user($this->userid);
        }

        if (!empty($this->curriculumid)) {
            $this->curriculum = new curriculum($this->curriculumid);
        }
    }

	public static function delete_for_curriculum($id) {
		global $CURMAN;

		return $CURMAN->db->delete_records(CURASSTABLE, 'curriculumid', $id);
	}

	public static function delete_for_user($id) {
		global $CURMAN;

		return $CURMAN->db->delete_records(CURASSTABLE, 'userid', $id);
	}

/////////////////////////////////////////////////////////////////////
//                                                                 //
//  STANDARD FUNCTIONS:                                            //
//                                                                 //
/////////////////////////////////////////////////////////////////////

    /**
     * Perform all necessary tasks to add a student curriculum enrolment to the system.
     */
    function add() {
        global $CURMAN;

        // If curriculum expiration is set to be based off curriculum enrolment then we need to calculate the expiry
        // date right now (ELIS-1618).
        if (!empty($CURMAN->config->enable_curriculum_expiration) &&
            $CURMAN->config->curriculum_expiration_start == CURR_EXPIRE_ENROL_START &&
            get_field(CURTABLE, 'frequency', 'id', $this->curriculumid)) {

            // We need to load this record from the DB fresh so we don't accidentally overwrite legitimate
            // values with something empty when we update the record.
            $this->timecreated = time();
            $timeexpired = calculate_curriculum_expiry($this);
            if ($timeexpired > 0) {
                $this->timeexpired = $timeexpired;
            }
        }

        $this->certificatecode = null;

        $result = $this->data_insert_record();

        return $result;
    }

    /**
     * Perform all necessary tasks to update a student enrolment.
     *
     */
    function update() {
        return $this->data_update_record();
    }

    /**
     * Perform all actions to mark this student record complete.
     *
     * @param  mixed    $time     Student's curriculum completion time (ignored if equal to FALSE)
     * @param  mixed    $credits  The number of credits awarded (ignored if false)
     * @param  boolean  $locked   TRUE if the curriculum enrolment should be locked, otherwise false
     */
    function complete($time = false, $credits = false, $locked = false) {
        global $CFG, $CURMAN;
        require_once CURMAN_DIRLOCATION . '/lib/notifications.php';

        $this->completed = STUSTATUS_PASSED;

        if ($time !== false) {
            $this->timecompleted = $time;
        }
        if (($this->timecompleted <= 0) || !is_numeric($this->timecompleted)) {
            $this->timecompleted = time();
        }

        // Handle a curriculum with an expiry date defined (ELIS-1172):
        if (!empty($CURMAN->config->enable_curriculum_expiration) && !empty($this->curriculum->frequency)) {
            $this->timeexpired = calculate_curriculum_expiry($this);
        }

        if ($credits !== false) {
            $this->credits = $credits;
        }

        if ($locked !== false) {
            $this->locked = $locked ? 1 : 0;
        }

        // Get the certificate code.  This batch of code tries to ensure
        // that the random string is unique trying
        if (empty($this->certificatecode)) {

            $this->certificatecode = null;
            $counter        = 0;
            $attempts       = 10;
            $maximumchar    = 15;
            $addchar        = 0;

            // This loop will try to generate a unique string 11 times.  On the 11th attempt
            // if string is still not unique then it will add to the length of the string
            // If the length of the string exceed the maximum length set by $maximumchar
            // then stop the loop and return an error
            do {
                $code = cm_certificate_generate_code($addchar);
                $exists = curriculum_code_exists($code);

                if (!$exists) {
                    $this->certificatecode = $code;
                    break;
                }

                // If the counter is equal to the number of attempts
                if ($counter == $attempts) {
                    // Set counter back to zero and add a character to the string
                    $counter = 0;
                    $addchar++;
                }

                // increment counter otherwise this is an infinite loop
                $counter++;
            } while($maximumchar >= $addchar);

            // Check if the length has exceeded the maximum length
            if ($maximumchar < $addchar) {

                if (!cm_certificate_email_random_number_fail($this)) {

                    $message = get_string('certificate_email_fail', 'block_curr_admin');
                    notify($message);
                }

                print_error('certificate_code_error', 'block_curr_admin');
            }

        }

        if ($this->update()) {
            /// Does the user receive a notification?
            $sendtouser       = $CURMAN->config->notify_curriculumcompleted_user;
            $sendtorole       = $CURMAN->config->notify_curriculumcompleted_role;
            $sendtosupervisor = $CURMAN->config->notify_curriculumcompleted_supervisor;

            /// If nobody receives a notification, we're done.
            if (!$sendtouser && !$sendtorole && !$sendtosupervisor) {
                return true;
            }

            $context = get_system_context();

            /// Make sure this is a valid user.
            $enroluser = new user($this->userid);
            if (empty($enroluser->id)) {
                print_error('nouser', 'block_curr_admin');
                return true;
            }

            $message = new notification();

            /// Set up the text of the message
            $text = empty($CURMAN->config->notify_curriculumcompleted_message) ?
                        get_string('notifycurriculumcompletedmessagedef', 'block_curr_admin') :
                        $CURMAN->config->notify_curriculumcompleted_message;
            $search = array('%%userenrolname%%', '%%curriculumname%%');
            $replace = array(fullname($this->user), $this->curriculum->name);
            $text = str_replace($search, $replace, $text);

            $eventlog = new Object();
            $eventlog->event = 'curriculum_completed';
            $eventlog->instance = $this->id;    /// Store the assignment id.
            if ($sendtouser) {
            	//todo: figure out why a log object is passed in here
                $message->send_notification($text, $this->user, null, $eventlog);
            }

            $users = array();

            if ($sendtorole) {
                /// Get all users with the notify_classenrol capability.
                if ($roleusers = get_users_by_capability($context, 'block/curr_admin:notify_curriculumcomplete')) {
                    $users = $users + $roleusers;
                }
            }

            if ($sendtosupervisor) {
                /// Get parent-context users.
                if ($supervisors = cm_get_users_by_capability('user', $this->userid, 'block/curr_admin:notify_curriculumcomplete')) {
                    $users = $users + $supervisors;
                }
            }

            foreach ($users as $user) {
                $message->send_notification($text, $user, $enroluser);
            }

        }
    }

/////////////////////////////////////////////////////////////////////
//                                                                 //
//  FORM FUNCTIONS:                                                //
//                                                                 //
/////////////////////////////////////////////////////////////////////


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
     * Function to handle curriculum completed events.
     *
     * @param   curriculumstudent  $student  The curriculum-student entry to mark as completed
     *
     * @return  boolean                      TRUE is successful, otherwise FALSE
     */

    public static function curriculum_completed_handler($student) {
        return $student->complete();
    }

    /**
     * Function to handle curriculum not completed events.
     *
     */

    public static function curriculum_notcompleted_handler($curstudent) {
        global $CFG, $CURMAN;
        require_once($CFG->dirroot.'/curriculum/lib/notifications.php');

        /// Does the user receive a notification?
        $sendtouser       = $CURMAN->config->notify_curriculumnotcompleted_user;
        $sendtorole       = $CURMAN->config->notify_curriculumnotcompleted_role;
        $sendtosupervisor = $CURMAN->config->notify_curriculumnotcompleted_supervisor;

        /// If nobody receives a notification, we're done.
        if (!$sendtouser && !$sendtorole && !$sendtosupervisor) {
            return true;
        }

        $context = get_system_context();

        $message = new notification();

        /// Set up the text of the message
        $text = empty($CURMAN->config->notify_curriculumnotcompleted_message) ?
                    get_string('notifycurriculumnotcompletedmessagedef', 'block_curr_admin') :
                    $CURMAN->config->notify_curriculumnotcompleted_message;
        $search = array('%%userenrolname%%', '%%curriculumname%%');
        $replace = array(fullname($curstudent->user), $curstudent->curriculum->name);
        $text = str_replace($search, $replace, $text);

        $eventlog = new Object();
        $eventlog->event = 'curriculum_notcompleted';
        $eventlog->instance = $curstudent->id;    /// Store the assignment id.
        $eventlog->fromuserid = $curstudent->userid;
        if ($sendtouser) {
            $message->send_notification($text, $curstudent->user, null, $eventlog);
        }

        $users = array();

        if ($sendtorole) {
            /// Get all users with the notify_curriculumnotcomplete capability.
            if ($roleusers = get_users_by_capability($context, 'block/curr_admin:notify_curriculumnotcomplete')) {
                $users = $users + $roleusers;
            }
        }

        if ($sendtosupervisor) {
            /// Get parent-context users.
            if ($supervisors = cm_get_users_by_capability('user', $this->userid, 'block/curr_admin:notify_curriculumnotcomplete')) {
                $users = $users + $supervisors;
            }
        }

        foreach ($users as $user) {
            $message->send_notification($text, $user, $enroluser, $eventlog);
        }

        return true;
    }

    public static function get_completed_for_user($userid) {
        global $CURMAN;

        $rows = $CURMAN->db->get_records_select(CURASSTABLE, "userid = $userid and completed != 0", '', 'id');
        $rows = ($rows == false ? array() : $rows);

        $r = array();

        foreach($rows as $row) {
            $r[] = new curriculumstudent($row->id);
        }

        return $r;
    }

    /**
     * Get a list of the curricula assigned to this student.
     *
     * @uses $CURMAN
     * @param int $userud The user id.
     */
    public static function get_curricula($userid = 0) {
        global $CURMAN, $USER;

        if ($userid <= 0) {
            $userid = $USER->id;
        }

        if (empty($CURMAN->db)) {
            return NULL;
        }

        $LIKE     = $CURMAN->db->sql_compare();

        $select  = 'SELECT curass.id, curass.curriculumid curid, curass.completed, curass.timecompleted, curass.credits, '.
                   'cur.idnumber, cur.name, cur.description, cur.reqcredits, COUNT(curcrs.id) as numcourses ';
        $tables  = 'FROM ' . $CURMAN->db->prefix_table(CURASSTABLE) . ' curass ';
        $join    = 'LEFT JOIN ' . $CURMAN->db->prefix_table(CURTABLE) . ' cur '.
                   'ON cur.id = curass.curriculumid ';
        $join   .= 'LEFT JOIN ' . $CURMAN->db->prefix_table(CURCRSTABLE) . ' curcrs '.
                   'ON curcrs.curriculumid = cur.id ';
        $where   = 'WHERE curass.userid = '.$userid.' ';
        $group   = 'GROUP BY curass.id, curass.curriculumid, curass.completed, curass.timecompleted, curass.credits, ' .
                   'cur.idnumber, cur.name, cur.description, cur.reqcredits ';
        $sort    = 'ORDER BY cur.priority ASC, cur.name, curcrs.position DESC ';
        $limit   = '';

        $sql = $select.$tables.$join.$where.$group.$sort.$limit;

        return $CURMAN->db->get_records_sql($sql);
    }

    /**
     * Get a list of the available students curriculum.
     *
     * @uses $CURMAN
     * @param string $search A search filter.
     * @return array An array of user records.
     */
    public static function curriculumstudent_get_students($curid = 0, $enroled = true) {
        global $CURMAN;

        if(0 >= $curid) {
            $curid = $CURMAN->id;
        }

        if (empty($CURMAN->db)) {
            return NULL;
        }

        $LIKE     = $CURMAN->db->sql_compare();
        $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');

        $select   = 'SELECT curass.id, usr.id as usrid, curass.curriculumid as curid, ' .
                     $FULLNAME . ' as name, usr.idnumber, usr.country, usr.language, curass.timecreated, curass.userid ';

        $tables   = 'FROM ' . $CURMAN->db->prefix_table(USRTABLE) . ' usr ';
        $join     = 'LEFT JOIN ' . $CURMAN->db->prefix_table(CURASSTABLE) . ' curass ON curass.userid = usr.id ';

        $sort     = 'ORDER BY usr.idnumber ASC ';
        $limit    = '';

        if($enroled) {
            $where = 'WHERE curass.curriculumid = ' . $curid . ' ';
        } else {
            $join .= 'LEFT JOIN ' . $CURMAN->db->prefix_table(CURASSTABLE) . ' curass2 ON curass2.userid = usr.id AND curass2.curriculumid = ' . $curid . ' ';
            $where = 'WHERE curass2.curriculumid IS NULL ';
        }

        $sql = $select.$tables.$join.$where.$sort.$limit;

        return $CURMAN->db->get_records_sql($sql);
    }

    /**
     * Determines whether the current user is allowed to create, edit, and delete associations
     * between a user and a curriculum
     *
     * @param    int      $userid  The id of the user being associated to the curricula
     * @param    int      $curid   The id of the curricula we are associating the user to
     *
     * @return   boolean           True if the current user has the required permissions, otherwise false
     */
    public static function can_manage_assoc($userid, $curid) {
        global $USER;

        if(!curriculumpage::can_enrol_into_curriculum($curid)) {
            //the users who satisfty this condition are a superset of those who can manage associations
            return false;
        } else if (curriculumpage::_has_capability('block/curr_admin:curriculum:enrol', $curid)) {
            //current user has the direct capability
            return true;
        }

        //get the context for the "indirect" capability
        $context = cm_context_set::for_user_with_capability('cluster', 'block/curr_admin:curriculum:enrol_cluster_user', $USER->id);

        $allowed_clusters = array();

        //get the clusters and check the context against them
        $clusters = clustercurriculum::get_clusters($curid);
        $allowed_clusters = $context->get_allowed_instances($clusters, 'cluster', 'id');

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


/// Non-class supporting functions. (These may be able to replaced by a generic container/listing class)


/**
 * Count the number of users
 */
function curriculumstudent_count_students($type = 'student', $namesearch = '', $alpha = '') {
    global $CURMAN;

    $LIKE     = $CURMAN->db->sql_compare();
    $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');

    $select  = 'SELECT COUNT(usr.id) ';
    $tables  = 'FROM ' . $CURMAN->db->prefix_table(USRTABLE) . ' usr ';
    $join    = '';
    $on      = '';
    $where   = '';

    if (!empty($namesearch)) {
        $namesearch = trim($namesearch);
        $where     .= (!empty($where) ? ' AND ' : '') . "($FULLNAME $LIKE '%$namesearch%') ";
    }

    if ($alpha) {
        $where .= (!empty($where) ? ' AND ' : '') . "($FULLNAME $LIKE '$alpha%') ";
    }

//    switch ($type) {
//        case 'student':
//            $where .= (!empty($where) ? ' AND ' : '') . 'usr.type = \'Student\' ';
//            break;
//
//        case 'instructor':
//            $where .= (!empty($where) ? ' AND ' : '') . 'usr.type = \'Instructor\' ';
//            break;
//
//        case '':
//            $where .= (!empty($where) ? ' AND ' : '') . '(usr.type = \'Student\' OR usr.type = \'Instructor\') ';
//            break;
//    }

    if (!empty($where)) {
        $where = 'WHERE '.$where.' ';
    }

    $sql = $select.$tables.$join.$on.$where;

    return $CURMAN->db->count_records_sql($sql);
}


/**
 * Determine if the given user has a curriculum assigned to them.
 *
 * @uses $CURMAN
 * @param int $uid The user ID.
 * @return bool True or False.
 */
function student_has_curriculum($uid) {
    global $CURMAN;

    return $CURMAN->db->record_exists(CURASSTABLE, 'userid', $uid);
}


/**
 * Calculate a curriculum expiration value for a specific user in a curriculum.
 *
 * NOTE: if you pass in the $curass parameter you do not need the second or third parameter and if you pass in
 *       an empty or NULL value for the first parameter, then the second and third parameters are expected to be
 *       passed instead.
 *
 * @uses $CURMAN
 * @param object $curass The curriculum assignment object data (as loaded by the curriculumstudent class constructor).
 * @param int 	 $curid  The curriculum DB record ID.
 * @param int    $userid The user DB record ID.
 * @return int The expiration value as a UNIX timestamp or 0 for no expiration or an error.
 */
function calculate_curriculum_expiry($curass, $curid = 0, $userid = 0) {
    global $CURMAN;

    // If we are specifically looking for a curriculum and user ID, then pass verify the parameters.
    if (empty($curass)) {
        if (!$curriculum = new curriculum($curid)) {
            return 0;
        }

        if (!$curass = get_record(CURASSTABLE, 'userid', $userid, 'curriculumid', $curriculum->id)) {
            return 0;
        }

        $curass->curriculum = clone($curriculum);
    }

    if (empty($curass->curriculum->frequency)) {
        return 0;
    }

    $strtimedelta = '';

    // Calculate the actual time difference from the completion time and the frequency value
    preg_match_all('/[0-9]+[h,d,w,m,y]/', strtolower($curass->curriculum->frequency), $matches);

    if (!empty($matches[0])) {
        $strtimedelta = '+';

        foreach ($matches[0] as $match) {
            switch ($match[strlen($match) - 1]) {
                case 'h':
                    if ($match[0] > 1) {
                        $strtimedelta .= str_replace('h', ' hours', $match) . ' ';
                    } else {
                        $strtimedelta .= str_replace('h', ' hour', $match) . ' ';
                    }
                    break;

                case 'd':
                    if ($match[0] > 1) {
                        $strtimedelta .= str_replace('d', ' days', $match) . ' ';
                    } else {
                        $strtimedelta .= str_replace('d', ' day', $match) . ' ';
                    }
                    break;

                case 'w':
                    if ($match[0] > 1) {
                        $strtimedelta .= str_replace('w', ' weeks', $match) . ' ';
                    } else {
                        $strtimedelta .= str_replace('w', ' week', $match) . ' ';
                    }
                    break;

                case 'm':
                    if ($match[0] > 1) {
                        $strtimedelta .= str_replace('m', ' months', $match) . ' ';
                    } else {
                        $strtimedelta .= str_replace('m', ' month', $match) . ' ';
                    }
                    break;

                case 'y':
                    if ($match[0] > 1) {
                        $strtimedelta .= str_replace('y', ' years', $match) . ' ';
                    } else {
                        $strtimedelta .= str_replace('y', ' year', $match) . ' ';
                    }
                    break;

                default:
                    break;
            }
        }
    }

    if (empty($strtimedelta)) {
        return 0;
    }

    if (!isset($CURMAN->config->curriculum_expiration_start) ||
        $CURMAN->config->curriculum_expiration_start == CURR_EXPIRE_ENROL_COMPLETE) {

        // Base the expiry date off the curriculum completion date.
        if ($curass->timecompleted == 0) {
            return 0;
        }
        $timenow = $curass->timecompleted;
    } else if ($CURMAN->config->curriculum_expiration_start == CURR_EXPIRE_ENROL_START) {
        // Base the expiry date off the curriculum enrolment date.
        $timenow = $curass->timecreated;
    } else {
        // Just in case?
        $timenow = time();
    }

    // Get the time of expiry start plus the delta value for the actual expiration.
    return strtotime($strtimedelta, $timenow);
}

function curriculum_code_exists($code) {
    global $CURMAN;

    if (empty($code)) {
        return true;
    }

    $exists = record_exists(CURASSTABLE, 'certificatecode', $code);

    return $exists;

}

?>
