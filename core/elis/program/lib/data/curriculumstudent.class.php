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
 * @subpackage curriculummanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/../../../../config.php');
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once elis::lib('data/data_object.class.php');
require_once elispm::lib('data/course.class.php');
require_once elispm::lib('data/curriculum.class.php');
require_once elispm::lib('data/curriculumcourse.class.php');
require_once elispm::lib('data/student.class.php');
require_once elispm::lib('certificate.php');
require_once elispm::lib('lib.php');

define('CURR_EXPIRE_ENROL_START',    1);
define('CURR_EXPIRE_ENROL_COMPLETE', 2);

class curriculumstudent extends elis_data_object {
    const TABLE = 'crlm_curriculum_assignment';

    var $verbose_name = 'curriculumstudent';
    var $curid;

    static $associations = array(
        'user' => array(
            'class' => 'user',
            'idfield' => 'userid'
        ),
        'curriculum' => array(
            'class' => 'curriculum',
            'idfield' => 'curriculumid'
        ),
    );

    protected $_dbfield_userid;
    protected $_dbfield_curriculumid;
    protected $_dbfield_completed;
    protected $_dbfield_timecompleted;
    protected $_dbfield_timeexpired;
    protected $_dbfield_credits;
    protected $_dbfield_locked;
    protected $_dbfield_certificatecode;
    protected $_dbfield_timecreated;
    protected $_dbfield_timemodified;

    // STRING - Styles to use for edit form.
    var $_editstyle = '
.curriculumstudenteditform input,
.curriculumstudenteditform textarea,
.curriculumstudenteditform select {
    margin: 0;
    display: block;
}
';

    /////////////////////////////////////////////////////////////////////
    //                                                                 //
    //  STANDARD FUNCTIONS:                                            //
    //                                                                 //
    /////////////////////////////////////////////////////////////////////

    /**
     * Perform all actions to mark this student record complete.
     *
     * @param  mixed    $time     Student's curriculum completion time (ignored if equal to FALSE)
     * @param  mixed    $credits  The number of credits awarded (ignored if false)
     * @param  boolean  $locked   TRUE if the curriculum enrolment should be locked, otherwise false
     */
    function complete($time = false, $credits = false, $locked = false) {
        global $CFG;

        require_once elispm::lib('notifications.php');

        $this->completed = STUSTATUS_PASSED;

        if ($time !== false) {
            $this->timecompleted = $time;
        }
        if (($this->timecompleted <= 0) || !is_numeric($this->timecompleted)) {
            $this->timecompleted = time();
        }

        // Check for $this->curriculum and create it if it is not included
        if (!isset($this->curriculum) && isset($this->curriculumid)) {
            $this->curriculum = new curriculum($this->curriculumid);
            $this->curriculum->load();
        } else {
            $this->curriculum->load();
        }

        // Handle a curriculum with an expiry date defined (ELIS-1172):
        if (!empty(elis::$config->elis_program->enable_curriculum_expiration) && !empty($this->curriculum->frequency)) {

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

            $this->certificatecode = cm_certificate_get_code();
        }

        // Doesn't return true/false, so just assume it worked
        $this->save();

        // Send notifications

        /// Does the user receive a notification?
        $sendtouser = (!empty(elis::$config->elis_program->notify_curriculumcompleted_user)) ? true : false;
        $sendtorole = (!empty(elis::$config->elis_program->notify_curriculumcompleted_role)) ? true : false;
        $sendtosupervisor = (!empty(elis::$config->elis_program->notify_curriculumcompleted_supervisor)) ? true : false;

        /// If nobody receives a notification, we're done.
        if (!$sendtouser && !$sendtorole && !$sendtosupervisor) {
            return true;
        }

        $context = get_system_context();

        /// Make sure this is a valid user.
        $enroluser = new user($this->userid);
        // Due to lazy loading, we need to pre-load this object
        $enroluser->load();
        if (empty($enroluser->id)) {
            print_error('nouser', 'elis_program');
            return true;
        }

        $message = new notification();

        /// Set up the text of the message
        $text = empty(elis::$config->elis_program->notify_curriculumcompleted_message) ?
                    get_string('notifycurriculumcompletedmessagedef', 'elis_program') :
                    elis::$config->elis_program->notify_curriculumcompleted_message;
        $search = array('%%userenrolname%%', '%%programname%%');
        $pmuser = $this->_db->get_record(user::TABLE, array('id' => $this->userid));
        $user = new user($pmuser);
        // Get course info
        $program = $this->_db->get_record(curriculum::TABLE, array('id' => $this->curriculumid));

        $replace = array(fullname($pmuser), $program->name);
        $text = str_replace($search, $replace, $text);

        $eventlog = new Object();
        $eventlog->event = 'curriculum_completed';
        $eventlog->instance = $this->id;    /// Store the assignment id.
        if ($sendtouser) {
            //todo: figure out why a log object is passed in here
            $message->send_notification($text, $user, null, $eventlog);
        }

        $users = array();

        if ($sendtorole) {
            /// Get all users with the notify_classenrol capability.
            if ($roleusers = get_users_by_capability($context, 'elis/program:notify_programcomplete')) {
                $users = $users + $roleusers;
            }
        }

        if ($sendtosupervisor) {
            /// Get parent-context users.
            if ($supervisors = pm_get_users_by_capability('user', $this->userid, 'elis/program:notify_programcomplete')) {
                $users = $users + $supervisors;
            }
        }

        foreach ($users as $user) {
            $message->send_notification($text, $user, $enroluser);
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
        global $CFG, $DB;

        require_once elispm::lib('notifications.php');

        /// Does the user receive a notification?
        $sendtouser       = elis::$config->elis_program->notify_curriculumnotcompleted_user;
        $sendtorole       = elis::$config->elis_program->notify_curriculumnotcompleted_role;
        $sendtosupervisor = elis::$config->elis_program->notify_curriculumnotcompleted_supervisor;

        /// If nobody receives a notification, we're done.
        if (!$sendtouser && !$sendtorole && !$sendtosupervisor) {
            return true;
        }

        $context = get_system_context();

        // Send notifications
        $message = new notification();

        /// Set up the text of the message
        $text = empty(elis::$config->elis_program->notify_curriculumnotcompleted_message) ?
                get_string('notifycurriculumnotcompletedmessagedef', 'elis_program') :
                elis::$config->elis_program->notify_curriculumnotcompleted_message;
        $pmuser = $DB->get_record(user::TABLE, array('id' => $curstudent->userid));
        $user = new user($pmuser);
        // Get course info
        $program = $DB->get_record(curriculum::TABLE, array('id' => $curstudent->curriculumid));

        $search = array('%%userenrolname%%', '%%programname%%');
        $replace = array(fullname($pmuser), $program->name);
        $text = str_replace($search, $replace, $text);

        $eventlog = new Object();
        $eventlog->event = 'curriculum_notcompleted';
        $eventlog->instance = $curstudent->id;    /// Store the assignment id.
        $eventlog->fromuserid = $user->id;
        if ($sendtouser) {
            $message->send_notification($text, $user, null, $eventlog);
        }

        $users = array();

        if ($sendtorole) {
            /// Get all users with the notify_curriculumnotcomplete capability.
            if ($roleusers = get_users_by_capability($context, 'elis/program:notify_programnotcomplete')) {
                $users = $users + $roleusers;
            }
        }

        if ($sendtosupervisor) {
            /// Get parent-context users.
            if ($supervisors = pm_get_users_by_capability('user', $user->id, 'elis/program:notify_programnotcomplete')) {
                $users = $users + $supervisors;
            }
        }

        foreach ($users as $u) {
            $message->send_notification($text, $u, $user, $eventlog);
        }

        return true;
    }

    public static function get_completed_for_user($userid) {
        global $DB;

        $r = array();

        $rows = $DB->get_recordset_select(curriculumstudent::TABLE, "userid = $userid and completed != 0", null, '', 'id');
        foreach($rows as $row) {
            $r[] = new curriculumstudent($row->id);
        }
        unset($rows);

        return $r;
    }

    /**
     * Get a list of the curricula assigned to this student.
     *
     * @param int $userid The user id.
     * @param int $cnt    Optional return count
     */
    public static function get_curricula($userid = 0, &$cnt = null) {
        global $USER, $DB;

        if ($userid <= 0) {
            $userid = $USER->id;
        }

        if (empty($DB)) {
            return NULL;
        }

        $params = array($userid);

        $select  = 'SELECT curass.id, curass.curriculumid curid, curass.completed, curass.timecompleted, curass.credits, '.
                   'cur.idnumber, cur.name, cur.description, cur.reqcredits, COUNT(curcrs.id) as numcourses ';
        $tables  = 'FROM {'.curriculumstudent::TABLE.'} curass ';
        $join    = 'LEFT JOIN {'.curriculum::TABLE.'} cur '.
                   'ON cur.id = curass.curriculumid ';
        $join   .= 'LEFT JOIN {'.curriculumcourse::TABLE.'} curcrs '.
                   'ON curcrs.curriculumid = cur.id ';
        $where   = 'WHERE curass.userid = ? ';
        $group   = 'GROUP BY curass.id, curass.curriculumid, curass.completed, curass.timecompleted, curass.credits, '.
                   'cur.idnumber, cur.name, cur.description, cur.reqcredits ';
        $sort    = 'ORDER BY cur.priority ASC, cur.name, curcrs.position DESC ';

        $sql = $select.$tables.$join.$where.$group.$sort;
        $rs  = $DB->get_recordset_sql($sql, $params);
        if ($cnt !== null) {
            $cnt = $rs->valid()
                   ? $DB->count_records_sql("SELECT COUNT('x') ".
                              $tables.$join.$where, $params)
                   : 0;
        }
        return $rs;
    }

    /**
     * Get a list of the available students curriculum.
     *
     * @param string $search A search filter.
     * @return array An array of user records.
     */
    public static function curriculumstudent_get_students($curid = 0, $enroled = true) {
        global $DB;

        if(0 >= $curid) {
            $curid = $this->id;
        }

        if (empty($DB)) {
            return NULL;
        }

        $params = array();

        $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');

        $select   = 'SELECT curass.id, usr.id as usrid, curass.curriculumid as curid, '.
                     $FULLNAME.' as name, usr.idnumber, usr.country, usr.language, curass.timecreated, curass.userid ';

        $tables   = 'FROM {'.user::TABLE.'} usr ';
        $join     = 'LEFT JOIN {'.curriculumstudent::TABLE.'} curass ON curass.userid = usr.id ';

        $sort     = 'ORDER BY usr.idnumber ASC ';

        if($enroled) {
            $where = 'WHERE curass.curriculumid = ? ';
            $params[] = $curid;
        } else {
            $join .= 'LEFT JOIN {'.curriculumstudent::TABLE.'} curass2 ON curass2.userid = usr.id AND curass2.curriculumid = ? ';
            $where = 'WHERE curass2.curriculumid IS NULL ';
            $params[] = $curid;
        }

        $sql = $select.$tables.$join.$where.$sort;

        return $DB->get_records_sql($sql, $params);
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
        global $USER, $DB;

        // TODO: Ugly, this needs to be overhauled
        $cpage = new curriculumpage();

        if (!curriculumpage::can_enrol_into_curriculum($curid)) {
            //the users who satisfty this condition are a superset of those who can manage associations
            return false;
        } else if ($cpage->_has_capability('elis/program:program_enrol', $curid)) {
            //current user has the direct capability
            return true;
        }

        //get the context for the "indirect" capability
        $context = pm_context_set::for_user_with_capability('cluster', 'elis/program:program_enrol_userset_user', $USER->id);

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
        if($DB->record_exists_select(clusterassignment::TABLE, $select)) {
            return true;
        }

        return false;
    }

    /**
     * Updates all students' curriculum expiry times (only call this method if
     * curriculum expiry is enabled)
     */
    static function update_expiration_times() {
        global $DB;

        if ($rs = $DB->get_recordset(curriculumstudent::TABLE, null, '', 'id, userid, curriculumid')) {
            $timenow = time();

            foreach ($rs as $curass) {
                $update = new stdClass;
                $update->id           = $curass->id;
                $update->timeexpired  = calculate_curriculum_expiry(false, $curass->curriculumid, $curass->userid);
                $update->timemodified = $timenow;
                $DB->update_record(curriculumstudent::TABLE, $update);
            }

            $rs->close();
        }
    }

    static $validation_rules = array(
        array('validation_helper', 'is_unique_curriculumid_userid')
    );

    public function save() {
        $isnew = empty($this->id);
        $now = time();

        if ($isnew) {
            $this->timecreated = $now;
            if (!empty(elis::$config->elis_program->enable_curriculum_expiration) &&
                elis::$config->elis_program->curriculum_expiration_start == CURR_EXPIRE_ENROL_START &&
                $this->_db->get_field(curriculum::TABLE, 'frequency', array('id'=>$this->curriculumid))) {

                // We need to load this record from the DB fresh so we don't accidentally overwrite legitimate
                // values with something empty when we update the record.
                $this->timecreated = time();

                $timeexpired = calculate_curriculum_expiry($this);
                if ($timeexpired > 0) {
                    $this->timeexpired = $timeexpired;
                }
            }
        }
        $this->timemodified = $now;

        parent::save();
    }

   function get_verbose_name() {
        return $this->verbose_name;
    }
}


/// Non-class supporting functions. (These may be able to replaced by a generic container/listing class)


/**
 * Count the number of users
 */
function curriculumstudent_count_students($type = 'student', $namesearch = '', $alpha = '') {
    global $DB;

    $FULLNAME = $DB->sql_concat('usr.firstname', "' '", 'usr.lastname');

    $select  = 'SELECT COUNT(usr.id) ';
    $tables  = 'FROM {'.user::TABLE.'} usr ';

    $where   = array();
    $params  = array();

    if (!empty($namesearch)) {
        $namesearch = trim($namesearch);
        $name_like = $DB->sql_like($FULLNAME, '?', FALSE);
        $where[] = "($name_like)";
        $params[] = "%$namesearch%";
    }

    if ($alpha) {
        $name_like = $DB->sql_like($FULLNAME, '?', FALSE);
        $where[] = "($name_like)";
        $params[] = "$alpha%";
    }

    if (!empty($where)) {
        $where = 'WHERE '.implode(' AND ', $where).' ';
    } else {
        $where = '';
    }

    $sql = $select.$tables.$where;

    return $DB->count_records_sql($sql, $params);
}

/**
 * Determine if the given user has a curriculum assigned to them.
 *
 * @param int $uid The user ID.
 * @return bool True or False.
 */
function student_has_curriculum($uid) {
    global $DB;

    return $DB->record_exists(curriculumstudent::TABLE, array('userid'=>$uid));
}

/**
 * Calculate a curriculum expiration value for a specific user in a curriculum.
 *
 * NOTE: if you pass in the $curass parameter you do not need the second or third parameter and if you pass in
 *       an empty or NULL value for the first parameter, then the second and third parameters are expected to be
 *       passed instead.
 *
 * @param object $curass The curriculum assignment object data (as loaded by the curriculumstudent class constructor).
 * @param int 	 $curid  The curriculum DB record ID.
 * @param int    $userid The user DB record ID.
 * @return int The expiration value as a UNIX timestamp or 0 for no expiration or an error.
 */
function calculate_curriculum_expiry($curass, $curid = 0, $userid = 0) {
    global $DB;

    // If we are specifically looking for a curriculum and user ID, then pass verify the parameters.
    if (empty($curass)) {
        if (!$curriculum = new curriculum($curid)) {
            return 0;
        }

        if (!$curass = $DB->get_record(curriculumstudent::TABLE, array('userid'=>$userid, 'curriculumid'=>$curriculum->id))) {
            return 0;
        }

        $curass->curriculum = clone($curriculum);
    }

    // Make sure curriculum is loaded
    $curass->curriculum->load();

    if (!isset($curass->curriculum->frequency)) {
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

    if (!isset(elis::$config->elis_program->curriculum_expiration_start) ||
        elis::$config->elis_program->curriculum_expiration_start == CURR_EXPIRE_ENROL_COMPLETE) {

        if ($curass->timecompleted == 0) {
            // Fall back to basing the expiry date off the curriculum enrolment date.
            $timenow = $curass->timecreated;
        } else {
            // Base the expiry date off the curriculum completion date.
            $timenow = $curass->timecompleted;
        }
    } else if (elis::$config->elis_program->curriculum_expiration_start == CURR_EXPIRE_ENROL_START) {
        // Base the expiry date off the curriculum enrolment date.
        $timenow = $curass->timecreated;
    } else {
        // Just in case?
        $timenow = time();
    }

    // Get the time of expiry start plus the delta value for the actual expiration.
    return strtotime($strtimedelta, $timenow);
}

/**
 * Determine if the specified unique certificate code already exists
 *
 * @uses $DB
 * @param string $code The code to look for
 * @return boolean True if the code already exists, false otherwise.
 */
function curriculum_code_exists($code) {
    global $DB;

    if (empty($code)) {
        return true;
    }

    return $DB->record_exists(curriculumstudent::TABLE, array('certificatecode' => $code));
}
