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
require_once CURMAN_DIRLOCATION . '/lib/student.class.php';
require_once CURMAN_DIRLOCATION . '/lib/notifications.php';

define ('WAITLISTTABLE', 'crlm_wait_list');

class waitlist extends datarecord {
/*
    var $id;            // INT - The data id if in the database.
    var $classid;       // INT - The id of the class this relationship belongs to.
    var $cmclass;         // OBJECT - class object.
    var $userid;        // INT - The id of the user this relationship belongs to.
    var $user;          // OBJECT - User object.
    var $timecreated;   // INT - Timestamp.
    var $timemodified;  // INT - Timestamp.
    var $position;      // INT - User's position in the waiting list queue.

    var $_dbloaded;         // BOOLEAN - True if loaded from database.
*/

    public function __construct($waitlistdata) {
        parent::__construct();

        $this->set_table(WAITLISTTABLE);
        $this->add_property('id', 'int');
        $this->add_property('classid', 'int');
        $this->add_property('userid', 'int');
        $this->add_property('timecreated', 'int');
        $this->add_property('timemodifieid', 'int');
        $this->add_property('position', 'int');
        $this->add_property('enrolmenttime', 'int');

        if (is_numeric($waitlistdata)) {
            $this->data_load_record($waitlistdata);
        } else if (is_array($waitlistdata)) {
            $this->data_load_array($waitlistdata);
        } else if (is_object($waitlistdata)) {
            $this->data_load_array(get_object_vars($waitlistdata));
        }
    }

    /**
     *
     * @global <type> $CURMAN
     * @param <type> $clsid
     * @param <type> $sort
     * @param <type> $dir
     * @param <type> $startrec
     * @param <type> $perpage
     * @param <type> $namesearch
     * @param <type> $alpha
     * @return <type>
     */
    public static function get_students($clsid = 0, $sort = 'timecreated', $dir = 'ASC',
                                        $startrec = 0, $perpage = 0, $namesearch = '',
                                        $alpha = '') {

        global $CURMAN;

        if (empty($CURMAN->db)) {
            return array();
        }

        $LIKE     = $CURMAN->db->sql_compare();
        $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');

        $select   = 'SELECT watlst.id, usr.id as uid, ' . $FULLNAME . ' as name, usr.idnumber, usr.country, usr.language, watlst.timecreated ';

        $tables  = 'FROM ' . $CURMAN->db->prefix_table(WAITLISTTABLE) . ' watlst ';
        $join    = 'JOIN ' . $CURMAN->db->prefix_table(USRTABLE) . ' usr ';
        $on      = 'ON watlst.userid = usr.id ';
        $where   = 'watlst.classid = ' . $clsid . ' ';

        if (!empty($namesearch)) {
            $namesearch = trim($namesearch);
            $where     .= (!empty($where) ? ' AND ' : '') . "($FULLNAME $LIKE '%$namesearch%') ";
        }

        if ($alpha) {
            $where .= (!empty($where) ? ' AND ' : '') . "(usr.lastname $LIKE '$alpha%') ";
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

        $sql = $select.$tables.$join.$on.$where.$sort.$limit;

        return $CURMAN->db->get_records_sql($sql);
    }

    public function check_autoenrol_after_course_completion($enrolment) {
        if($enrolment->completestatusid != STUSTATUS_NOTCOMPLETE) {
            $cmclass = new cmclass($enrolment->classid);

            if((empty($cmclass->maxstudents) || $cmclass->maxstudents > student::count_enroled($cmclass->id)) && !empty($cmclass->enrol_from_waitlist)) {
                $wlst = waitlist::get_next($enrolment->classid);

                if(!empty($wlst)) {
                    $wlst->enrol();
                }
            }
        }

        return true;
    }
    
    /**
     *
     * @global object $CURMAN
     * @param int $clsid
     * @param string $namesearch
     * @param char $alpha
     * @return array
     */
    public function count_records($clsid, $namesearch = '', $alpha = '') {
        global $CURMAN;

        if(empty($clsid)) {
            if(!empty($this->classid)) {
                $clsid = $this->classid;
            } else {
                return array();
            }
        }

        $select = '';

        $LIKE = $CURMAN->db->sql_compare();

        $select = 'SELECT COUNT(watlist.id) ';
        $tables = 'FROM ' . $CURMAN->db->prefix_table(WAITLISTTABLE) . ' watlist ';
        $join   = 'INNER JOIN ' . $CURMAN->db->prefix_table(USRTABLE) . ' usr ';
        $on     = 'ON watlist.userid = usr.id ';
        $where = 'watlist.classid = \'' . $clsid . '\'';

        $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');

        if (!empty($namesearch)) {
            $namesearch = trim($namesearch);
            $where .= (!empty($where) ? ' AND ' : '') . "($FULLNAME $LIKE  '%$namesearch%') ";
        }

        if ($alpha) {
            $where .= (!empty($where) ? ' AND ' : '') . "(usr.lastname $LIKE '$alpha%') ";
        }

        if (!empty($where)) {
            $where = 'WHERE '.$where.' ';
        }

        $sql = $select . $tables . $join . $on . $where;

        return $CURMAN->db->count_records_sql($sql);
    }

    /**
     *
     * @global object $CFG
     * @global object $CURMAN 
     */
    public function enrol() {
        global $CFG, $CURMAN;
        $this->data_delete_record();

        $class = new cmclass($this->classid);
        $courseid = $class->get_moodle_course_id();

        // enrol directly in the course
        $student = new student($this);
        $student->enrolmenttime = max(time(), $class->startdate);
        $student->add();

        if ($courseid) {
            $course = $CURMAN->db->get_record('course', 'id', $this->id);
            // the elis plugin is treated specially
            if ($course->enrol != 'elis') {
                // send the user to the Moodle enrolment page
                $a = new stdClass;
                $a->crs = $course;
                $a->class = $class;
                $a->wwwroot = $CFG->wwwroot;
                $subject = get_string('moodleenrol_subj', 'block_curr_admin', $a);
                $message = get_string('moodleenrol', 'block_curr_admin', $a);
            }
        }

        if (!isset($message)) {
            $a = $class->idnumber;

            $subject = get_string('nowenroled', 'block_curr_admin', $a);
            $message = get_string('nowenroled', 'block_curr_admin', $a);
        }

        $user = cm_get_moodleuser($this->userid);
        $from = get_admin();

        notification::notify($message, $user, $from);
        email_to_user($user, $from, $subject, $message);
    }

    /**
     *
     * @global <type> $CURMAN
     */
    public function add() {
        global $CURMAN;
        
        if(empty($this->position)) {
            //SELECT MIN(userid) FROM eli_crlm_wait_list WHERE 1
            $sql = 'SELECT ' . sql_max('position') . ' as max 
                    FROM ' . $CURMAN->db->prefix_table(WATLSTTABLE) . ' as wl
                    WHERE wl.classid = ' . $this->classid;
            
            $max_record = get_record_sql($sql);
            $max = $max_record->max;
            
            $this->position = $max + 1;
        }

        $subject = get_string('waitlist', 'block_curr_admin');
        $cmclass = new cmclass($this->classid);
        $message = get_string('added_to_waitlist_message', 'block_curr_admin', $cmclass->idnumber);

        $user = cm_get_moodleuser($this->userid);
        $from = get_admin();

        notification::notify($message, $user, $from);
        email_to_user($user, $from, $subject, $message);

        parent::add();
    }

    public static function get_next($clsid) {
        global $CURMAN;
        
        $select = 'SELECT * ';
        $from   = 'FROM ' . $CURMAN->db->prefix_table(WATLSTTABLE) . ' wlst ';
        $where  = 'WHERE wlst.classid="' . $clsid . '" ';
        $order  = 'ORDER BY wlst.position ASC LIMIT 0,1';

        $sql = $select . $from . $where . $order;

        $nextStudent = $CURMAN->db->get_records_sql($sql);

        if(!empty($nextStudent)) {
            $nextStudent = current($nextStudent);
            $nextStudent = new waitlist($nextStudent);
        }

        return $nextStudent;
    }

    public static function delete_for_user($id) {
    	global $CURMAN;

    	$status = $CURMAN->db->delete_records(WAITLISTTABLE, 'userid', $id);

    	return $status;
    }

    public static function delete_for_class($id) {
    	global $CURMAN;

    	$status = $CURMAN->db->delete_records(WAITLISTTABLE, 'classid', $id);

    	return $status;
    }
}

?>
