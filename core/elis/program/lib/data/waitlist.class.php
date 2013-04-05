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
require_once elispm::lib('data/pmclass.class.php');
require_once elispm::lib('data/student.class.php');
require_once elispm::lib('data/user.class.php');
require_once elispm::lib('notifications.php');

define ('WAITLISTTABLE', 'crlm_wait_list');

class waitlist extends elis_data_object {
    const LANG_FILE = 'elis_program'; // TBD
    const TABLE = WAITLISTTABLE;

    var $verbose_name = 'waitlist';

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

    private $form_url = null;  //moodle_url object

    protected $_dbfield_classid;
    protected $_dbfield_userid;
    protected $_dbfield_timecreated;
    protected $_dbfield_timemodified;
    protected $_dbfield_position;

    static $associations = array(
        'user'   => array('class' => 'user',
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
    );

    /**
     * Validates that the associated user record exists
     */
    public function validate_associated_user_exists() {
        validate_associated_record_exists($this, 'user');
    }

    /**
     * Validates that the associated pmclass record exists
     */
    public function validate_associated_class_exists() {
        validate_associated_record_exists($this, 'pmclass');
    }

/* ***** disabled constructor *****
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
***** */

    /**
     * Gets students for a waitlist
     * 
     * @param int $clsid
     * @param string $sort
     * @param string $dir
     * @param int $startrec
     * @param int $perpage
     * @param string $namesearch
     * @param string $alpha
     * @uses $DB
     * @return recordset
     */
    public static function get_students($clsid = 0, $sort = 'timecreated', $dir = 'ASC',
                                        $startrec = 0, $perpage = 0, $namesearch = '',
                                        $alpha = '') {
        global $DB;
        // TBD: this method should be replaced by association w/ filter

        $params = array();
        $FULLNAME = $DB->sql_concat('usr.firstname', "' '", 'usr.lastname');
        $FULLNAME_LIKE = $DB->sql_like($FULLNAME, ':search_fullname', FALSE);
        $LASTNAME_LIKE = $DB->sql_like('usr.lastname', ':search_lastname', FALSE);

        $select   = 'SELECT watlst.id, usr.id as uid, '. $FULLNAME .' as name, usr.idnumber, usr.country, usr.language, watlst.timecreated ';

        $tables  = 'FROM {'. waitlist::TABLE .'} watlst ';
        $join    = 'JOIN {'. user::TABLE .'} usr ';
        $on      = 'ON watlst.userid = usr.id ';
        $where   = 'watlst.classid = :clsid ';
        $params['clsid'] = $clsid;

        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $where .= ' AND usr.inactive = 0 ';
        }

        if (!empty($namesearch)) {
            $namesearch = trim($namesearch);
            $where     .= (!empty($where) ? ' AND ' : ' ') . $FULLNAME_LIKE;
            $params['search_fullname'] = "%{$namesearch}%";
        }

        if ($alpha) {
            $where .= (!empty($where) ? ' AND ' : ' ') . $LASTNAME_LIKE;
            $params['search_lastname'] = "{$alpha}%";
        }

        if (!empty($where)) {
            $where = ' WHERE '.$where.' ';
        }

        if ($sort) {
            $sort = ' ORDER BY '.$sort .' '. $dir.' ';
        }

        $sql = $select.$tables.$join.$on.$where.$sort;
        return $DB->get_recordset_sql($sql, $params, $startrec, $perpage);
    }

    public static function check_autoenrol_after_course_completion($enrolment) {
        if ($enrolment->completestatusid != STUSTATUS_NOTCOMPLETE) {
            $pmclass = new pmclass($enrolment->classid);
            $pmclass->load();

            if ((empty($pmclass->maxstudents) || $pmclass->maxstudents > student::count_enroled($pmclass->id)) && !empty($pmclass->enrol_from_waitlist)) {
                $wlst = waitlist::get_next($enrolment->classid);
                if (!empty($wlst)) {
                    $crsid = $pmclass->course->id;
                    foreach ($pmclass->course->curriculumcourse as $curcrs) {
                        if ($curcrs->courseid == $crsid && $curcrs->prerequisites_satisfied($wlst->userid)) {
                            $wlst->enrol();
                        }
                    }
                }
            }
        }

        return true;
    }

    /**
     *
     * @param int $clsid
     * @param string $namesearch
     * @param char $alpha
     * @return array
     */
    public static function count_records($clsid, $namesearch = '', $alpha = '') {
        global $DB;
        // TBD: this method should be replaced by association w/ filter
        if (empty($clsid)) { // TBD
            return array();
        }

        $select = '';
        $params = array();
        $FULLNAME = $DB->sql_concat('usr.firstname', "' '", 'usr.lastname');
        $FULLNAME_LIKE = $DB->sql_like($FULLNAME, ':search_fullname', FALSE);
        $LASTNAME_LIKE = $DB->sql_like('usr.lastname', ':search_lastname', FALSE);

        $select = 'SELECT COUNT(watlist.id) ';
        $tables = 'FROM {'. waitlist::TABLE .'} watlist ';
        $join   = 'INNER JOIN {'. user::TABLE .'} usr ';
        $on     = 'ON watlist.userid = usr.id ';
        $where = 'watlist.classid = :clsid ';
        $params['clsid'] = $clsid;

        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $where .= ' AND usr.inactive = 0 ';
        }

        if (!empty($namesearch)) {
            $namesearch = trim($namesearch);
            $where .= (!empty($where) ? ' AND ' : ' ') . $FULLNAME_LIKE;
            $params['search_fullname'] = "%{$namesearch}%";
        }

        if ($alpha) {
            $where .= (!empty($where) ? ' AND ' : ' ') . $LASTNAME_LIKE;
            $params['search_lastname'] = "{$alpha}%";
        }

        if (!empty($where)) {
            $where = ' WHERE '.$where.' ';
        }

        $sql = $select . $tables . $join . $on . $where;
        return $DB->count_records_sql($sql, $params);
    }

    /**
     *
     * @global object $CFG
     * @uses $CFG
     * @uses $OUTPUT
     */
    public function enrol() {
        global $CFG;

        $class = new pmclass($this->classid);

        // enrol directly in the course
        $student                = new student(); // TBD: new student($this); didn't work!!!
        $student->userid        = $this->userid;
        $student->classid       = $this->classid;
        $student->enrolmenttime = max(time(), $class->startdate);
        // Disable validation rules for prerequisites and enrolment_limits
        $student->validation_overrides[] = 'prerequisites';
        $student->validation_overrides[] = 'enrolment_limit';

        $courseid = $class->get_moodle_course_id();
        if ($courseid) {
            $course = $this->_db->get_record('course', array('id' => $courseid));

            // check that the elis plugin allows for enrolments from the course
            // catalog -- if not, see if there are other plugins that allow
            // self-enrolment.
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
                        // at least one plugin allows self-enrolment -- send
                        // the user to the course enrolment page, and prevent
                        // automatic enrolment
                        $a = new stdClass;
                        $a->id = $course->id;
                        $a->idnumber = $class->idnumber;
                        $a->wwwroot = $CFG->wwwroot;
                        $subject = get_string('moodleenrol_subj', self::LANG_FILE, $a);
                        $message = get_string('moodleenrol', self::LANG_FILE, $a);

                        $student->no_moodle_enrol = true;
                        break;
                    }
                }
            }
        }

        $student->save();

        if (!isset($message)) {
            $a = new stdClass;
            $a->idnum = $class->idnumber;
            $subject = get_string('nowenroled', self::LANG_FILE, $a);
            $message = get_string('nowenroled', self::LANG_FILE, $a);
        }

        $cuser = new user($this->userid);
        $user = $cuser->get_moodleuser();
        $from = get_admin();

        notification::notify($message, $user, $from);
        //email_to_user($user, $from, $subject, $message);

        $this->delete();
    }

    /**
     *
     */
    public function save() {
        $new = false;
        try {
            validation_helper::is_unique_userid_classid($this);
        } catch (Exception $e) {
            // already on waitlist
            return true;
        }

        if (!isset($this->id)) {
            $new = true;
            if (empty($this->position)) {
                $max = $this->_db->get_field(waitlist::TABLE, 'MAX(position)', array('classid' => $this->classid));
                $this->position = $max + 1;
            }
        }

        parent::save();

        if ($new) {
            $subject = get_string('waitlist', self::LANG_FILE);
            $pmclass = new pmclass($this->classid);
            $sparam = new stdClass;
            $sparam->idnumber = $pmclass->idnumber;
            $message = get_string('added_to_waitlist_message', self::LANG_FILE, $sparam);

            $cuser = new user($this->userid);
            $user = $cuser->get_moodleuser();
            $from = get_admin();

            notification::notify($message, $user, $from);
            //email_to_user($user, $from, $subject, $message); // *TBD*
        }
    }

    public static function get_next($clsid) {
        global $DB;
        $select = 'SELECT * ';
        $from   = 'FROM {'. waitlist::TABLE .'} wlst ';
        $where  = 'WHERE wlst.classid = ? ';
        $order  = 'ORDER BY wlst.position ASC LIMIT 0,1';
        $sql = $select . $from . $where . $order;
        $nextStudent = $DB->get_records_sql($sql, array($clsid));

        if (!empty($nextStudent)) {
            $nextStudent = current($nextStudent);
            $nextStudent = new waitlist($nextStudent);
        }

        return $nextStudent;
    }

    public static function delete_for_user($id) {
        global $DB;
        $status = $DB->delete_records(waitlist::TABLE, array('userid' => $id));
    	return $status;
    }

    public static function delete_for_class($id) {
        global $DB;
        $status = $DB->delete_records(waitlist::TABLE, array('classid' => $id));
    	return $status;
    }

    public function set_from_data($data) {
        $this->_load_data_from_record($data, true);
    }
}

