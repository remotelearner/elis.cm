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
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once elis::lib('data/data_object_with_custom_fields.class.php');
require_once elispm::lib('data/clusterassignment.class.php');
require_once elispm::lib('data/student.class.php');
require_once elispm::lib('data/waitlist.class.php');
require_once elispm::lib('data/instructor.class.php');
require_once elispm::lib('data/curriculumstudent.class.php');
require_once elispm::lib('data/usertrack.class.php');
require_once $CFG->dirroot . '/user/filters/text.php';
require_once $CFG->dirroot . '/user/filters/date.php';
require_once $CFG->dirroot . '/user/filters/select.php';
require_once $CFG->dirroot . '/user/filters/simpleselect.php';
require_once $CFG->dirroot . '/user/filters/courserole.php';
require_once $CFG->dirroot . '/user/filters/globalrole.php';
require_once $CFG->dirroot . '/user/filters/profilefield.php';
require_once $CFG->dirroot . '/user/filters/yesno.php';
require_once $CFG->dirroot . '/user/filters/user_filter_forms.php';
require_once $CFG->dirroot . '/user/profile/lib.php';

class user extends data_object_with_custom_fields {
    const TABLE = 'crlm_user';

    var $verbose_name = 'user';

    static $associations = array(
        'classenrolments' => array(
            'class' => 'student',
            'foreignidfield' => 'userid'
        ),
        'waitlist' => array(
            'class' => 'waitlist',
            'foreignidfield' => 'userid'
        ),
        'classestaught' => array(
            'class' => 'instructor',
            'foreignidfield' => 'userid'
        ),
        'clusterassignments' => array(
            'class' => 'clusterassignment',
            'foreignidfield' => 'userid'
        ),
        'programassignments' => array(
            'class' => 'curriculumstudent',
            'foreignidfield' => 'userid'
        ),
        'trackassignments' => array(
            'class' => 'usertrack',
            'foreignidfield' => 'userid'
        ),
        'classgraded' => array(
            'class' => 'student_grade',
            'foreignidfield' => 'userid'
        )
    );

    /**
     * Moodle username
     * @var    char
     * @length 100
     */
    protected $_dbfield_username;

    /**
     * User password
     * @var    char
     * @length 32
     */
    protected $_dbfield_password;

    /**
     * User ID-number
     * @var    char
     * @length 255
     */
    protected $_dbfield_idnumber;

    protected $_dbfield_firstname;
    protected $_dbfield_lastname;
    protected $_dbfield_mi;
    protected $_dbfield_email;
    protected $_dbfield_email2;
    protected $_dbfield_address;
    protected $_dbfield_address2;
    protected $_dbfield_city;
    protected $_dbfield_state;
    protected $_dbfield_country;
    protected $_dbfield_phone;
    protected $_dbfield_phone2;
    protected $_dbfield_fax;
    protected $_dbfield_postalcode;
    protected $_dbfield_birthdate;
    protected $_dbfield_gender;
    protected $_dbfield_language;
    protected $_dbfield_transfercredits;
    protected $_dbfield_comments;
    protected $_dbfield_notes;
    protected $_dbfield_timecreated;
    protected $_dbfield_timeapproved;
    protected $_dbfield_timemodified;
    protected $_dbfield_inactive;

    static $delete_is_complex = true;

    protected function get_field_context_level() {
        return CONTEXT_ELIS_USER;
    }

    public function delete () {
        global $CFG;
        $muser = $this->get_moodleuser();

        if(empty($muser) || !is_primary_admin($muser->id)) {
            // delete associated data
            require_once elis::lib('data/data_filter.class.php');
            $filter = new field_filter('userid', $this->id);
            curriculumstudent::delete_records($filter, $this->_db);
            student::delete_records($filter, $this->_db);
            student_grade::delete_records($filter, $this->_db);
            waitlist::delete_records($filter, $this->_db);
            instructor::delete_records($filter, $this->_db);
            usertrack::delete_records($filter, $this->_db);
            clusterassignment::delete_records($filter, $this->_db);

            //delete association to Moodle user, if applicable
            require_once(elispm::lib('data/usermoodle.class.php'));
            $filter = new field_filter('cuserid', $this->id);
            usermoodle::delete_records($filter, $this->_db);

            // Delete Moodle user.
            if (!empty($muser)) {
                delete_user($muser);
            }

            parent::delete();

            $context = context_elis_user::instance($this->id);
            $context->delete();
        }
    }

    public static function find($filter=null, array $sort=array(), $limitfrom=0, $limitnum=0, moodle_database $db=null) {
        // if we're sorting by "name", sort by lastname, then firstname
        $newsort = array();
        foreach ($sort as $field => $dir) {
            if ($field == 'name') {
                $newsort['lastname'] = $dir;
                $newsort['firstname'] = $dir;
            } else {
                $newsort[$field] = $dir;
            }
        }

        return parent::find($filter, $newsort, $limitfrom, $limitnum, $db);
    }

    /**
     * @todo move out
     */
    public function set_from_data($data) {
        // Process non-direct elements:
        $this->set_date('birthdate', $data->birthyear, $data->birthmonth, $data->birthday);

        if (!empty($data->newpassword)) {
            $this->change_password($data->newpassword);
            $data->password = $this->password;
        }

        if(!empty($data->id_same_user)) {
            $data->username = $data->idnumber;
        }
        if (!empty($data->username)) {
            $data->username = textlib::strtolower($data->username);
        }
        $this->_load_data_from_record($data, true);
    }

    public function fullname() {
        $name = array();

        if (!empty($this->firstname)) {
            $name[] = $this->firstname;
        }

        if (!empty($this->mi)) {
            $name[] = $this->mi;
        }

        if (!empty($this->lastname)) {
            $name[] = $this->lastname;
        }

        return implode(' ', $name);
    }

    public function __toString() {
        return $this->fullname();
    }

    /**
     * Retrieves the Moodle user object associated to this PM user if applicable
     *
     * @param boolean $strict_match Whether we should use the association table rather
     *                              than just check idnumbers
     */
    public function get_moodleuser($strict_match = true) {
        require_once(elispm::lib('data/usermoodle.class.php'));

        if ($strict_match && isset($this->id)) {
            //check against the association table
            $sql = "SELECT mu.*
                    FROM
                    {user} mu
                    JOIN {".usermoodle::TABLE."} um
                      ON mu.id = um.muserid
                    JOIN {".user::TABLE."} cu
                      ON um.cuserid = cu.id
                    WHERE cu.id = ?
                      AND mu.deleted = 0";

            return $this->_db->get_record_sql($sql, array($this->id));
        } else {
            //note: we need this case because when syncing Moodle users to the PM system, the PM user is inserted
            //before the association record is
            return $this->_db->get_record('user', array('idnumber' => $this->idnumber, 'deleted' => 0));
        }
    }

    public function get_country() {
        $countries = get_string_manager()->get_list_of_countries();

        return isset($countries[$this->country]) ? $countries[$this->country] : '';
    }

    function set_date($field, $year, $month, $day) {
        if ($field == '') {
            return '';
        }
        if (empty($year) || empty($month) || empty($day)) {
            return '';
        }
        $this->$field = sprintf('%04d/%02d/%02d', $year, $month, $day);
    }

    /**
     * @todo move out
     */
    function get_add_form($form) {
        require_once elispm::file('/form/userform.class.php');

        return new addform($form);
    }

    static $validation_rules = array(
        array('validation_helper', 'not_empty_idnumber'),
        array('validation_helper', 'is_unique_idnumber'),
        array('validation_helper', 'not_empty_username'),
        array('validation_helper', 'is_unique_username'),
        array('validation_helper', 'not_empty_firstname'),
        array('validation_helper', 'not_empty_lastname'),
        array('validation_helper', 'not_empty_email'),
        array('validation_helper', 'not_empty_country'),
    );

    /**
     * Save the record to the database.  This method is used to both create a
     * new record, and to update an existing record.
     *
     * @param boolean $strict_match Whether we should use the association table rather
     *                              than just check idnumbers when comparing to Moodle users
     */
    public function save($strict_match = true) {
        $isnew = empty($this->id);

        $now = time();
        if ($isnew) {
            $this->timecreated = $now;
        } else {
            $this->timemodified = $now;
        }

        parent::save();

        /// Synchronize Moodle data with this data.
        $this->synchronize_moodle_user(true, $isnew, $strict_match);
    }

    function save_field_data() {
        static $loopdetect;

        if(!empty($loopdetect)) {
            return true;
        }

        field_data::set_for_context_from_datarecord('user', $this);

        $loopdetect = true;
        events_trigger('user_updated', $this->get_moodleuser());
        $loopdetect = false;

        return true;
    }

    /**
     * This function should change the password for the CM user.
     * It should treat it properly according to the text/HASH settings.
     *
     */
    function change_password($password) {
        $this->password = hash_internal_user_password($password);
    }

    /////////////////////////////////////////////////////////////////////
    //                                                                 //
    //  DATA FUNCTIONS:                                                //
    //                                                                 //
    /////////////////////////////////////////////////////////////////////

    /**
     * Function to synchronize the curriculum data with the Moodle data.
     *
     * @param boolean $tomoodle Optional direction to synchronize the data.
     * @param boolean $strict_match Whether we should use the association table rather
     *                               than just check idnumbers when comparing to Moodle users
     *
     */
    function synchronize_moodle_user($tomoodle = true, $createnew = false, $strict_match = true) {
        global $CFG;

        require_once(elispm::lib('data/usermoodle.class.php'));

        static $mu_loop_detect = array();

        // Create a new Moodle user record to update with.

        if (!($muser = $this->get_moodleuser($strict_match)) && !$createnew) {
            return false;
        }
        $muserid = $muser ? $muser->id : false;

        if ($tomoodle) {
            // map PM user fields to Moodle user fields
            $mdlfieldmap = array(
                'idnumber'  => 'idnumber',
                'username'  => 'username',
                'firstname' => 'firstname',
                'lastname'  => 'lastname',
                'email'     => 'email',
                'address'   => 'address',
                'city'      => 'city',
                'country'   => 'country',
                'language'  => 'lang'
            );

            // determine if the user is already noted as having been associated to a PM user
            if ($um = usermoodle::find(new field_filter('cuserid', $this->id))) {
                if ($um->valid()) {
                    $um = $um->current();

           	        // determine if the PM user idnumber was updated
                    if ($um->idnumber != $this->idnumber) {

                        // update the Moodle user with the new idnumber
                        $muser = new stdClass;
                        $muser->id = $um->muserid;
                        $muser->idnumber = $this->idnumber;
                        $this->_db->update_record('user', $muser);

                        // update the association table with the new idnumber
                        $um->idnumber = $this->idnumber;
                        $um->save();
                    }
                }
            }

            //try to update the idnumber of a matching Moodle user that
            //doesn't have an idnumber set yet
            $exists_params = array('username' => $this->username,
                                   'mnethostid' => $CFG->mnet_localhost_id);
            if ($moodle_user = $this->_db->get_record('user', $exists_params)) {
                if (empty($moodle_user->idnumber)) {
                    //potentially old data, so set the idnumber
                    $moodle_user->idnumber = $this->idnumber;
                    $this->_db->update_record('user', $moodle_user);
                    $muserid = $moodle_user->id;
                } else if ($this->idnumber != $moodle_user->idnumber) {
                    //the username points to a pre-existing Moodle user
                    //with a non-matching idnumber, so something horrible
                    //happened
                    return;
                }
            }

            if ($createnew && !$muserid) {
                /// Add a new user
                $record                 = new stdClass();
                foreach ($mdlfieldmap as $pmfield => $mdlfield) {
                    if (isset($this->$pmfield)) {
                        $record->$mdlfield = $this->$pmfield;
                    }
                }
                $record->password       = $this->password === null ? '' : $this->password;
                $record->confirmed      = 1;
                $record->mnethostid     = $CFG->mnet_localhost_id;
                $record->timemodified   = time();
                $record->id = $this->_db->insert_record('user', $record);
            } else if ($muserid) {
                /// Update an existing user
                $record                 = new stdClass();
                $record->id             = $muserid;
                foreach ($mdlfieldmap as $pmfield => $mdlfield) {
                    if (isset($this->$pmfield)) {
                        $record->$mdlfield = $this->$pmfield;
                    }
                }
                if (!empty($this->password)) {
                    $record->password = $this->password;
                }
                $record->timemodified   = time();
                $this->_db->update_record('user', $record);
            } else {
                return true;
            }

            // avoid update loops
            if (isset($mu_loop_detect[$this->id])) {
                return $record->id;
            }
            $mu_loop_detect[$this->id] = true;

            // synchronize profile fields
            $origrec = clone($record);
            profile_load_data($origrec);
            $fields = field::get_for_context_level(CONTEXT_ELIS_USER);
            $mfields = $this->_db->get_records('user_info_field', array(), '', 'shortname');
            $fields = $fields ? $fields : array();
            $changed = false;
            require_once elis::plugin_file('elisfields_moodle_profile','custom_fields.php');
            foreach ($fields as $field) {
                $field = new field($field);
                if (!moodle_profile_can_sync($field->shortname)) {
                    continue;
                }

                if (isset($field->owners['moodle_profile']) && $field->owners['moodle_profile']->exclude == pm_moodle_profile::sync_to_moodle && isset($mfields[$field->shortname])) {
                    $shortname = $field->shortname;
                    $fieldname = "field_{$shortname}";
                    $mfieldname = "profile_{$fieldname}";
                    $mfieldvalue = isset($origrec->$mfieldname) ? $origrec->$mfieldname : null;
                    if ($mfieldvalue != $this->$fieldname) {
                        $record->$mfieldname = $this->$fieldname;
                        $changed = true;
                        sync_profile_field_settings_to_moodle($field);
                    }
                }
            }
            profile_save_data($record);

            if ($muserid) {
                if ($changed) {
                    events_trigger('user_updated', $record);
                }
            } else {
                // if no user association record exists, create one
                $um = new usermoodle();
                $um->cuserid  = $this->id;
                $um->muserid  = $record->id;
                $um->idnumber = $this->idnumber;
                $um->save();

                events_trigger('user_created', $record);
            }

            unset($mu_loop_detect[$this->id]);
            return $record->id;
        }
    }

    /**
     * Retrieves a list of classes the specified user is currently enrolled in under the specified curriculum.
     * @param $userid ID of the user
     * @param $curid ID of the curriculum
     * @uses $DB
     * @return unknown_type
     */
    static function get_current_classes_in_curriculum($userid, $curid) {
        global $DB;
        $sql = 'SELECT DISTINCT CONCAT(curcrs.id, ".", cls.id),
                       curcrs.*, crs.name AS coursename, cls.id AS classid
                  FROM {'.curriculumcourse::TABLE.'} curcrs
                  JOIN {'.course::TABLE.'} crs ON curcrs.courseid = crs.id
                       -- Next two are to limit to currently enrolled courses
                  JOIN {'.pmclass::TABLE.'} cls ON cls.courseid = crs.id
                  JOIN {'.student::TABLE.'} clsenrol ON cls.id = clsenrol.classid
                 WHERE curcrs.curriculumid = ?
                   AND clsenrol.userid = ?
                   AND clsenrol.completestatusid = ?
              ORDER BY curcrs.position';

        return $DB->get_recordset_sql($sql,
                        array($curid, $userid, student::STUSTATUS_NOTCOMPLETE));
    }

    /**
     * Retrieves a list of classes the specified user is currently enrolled in that don't fall under a
     * curriculum the user is assigned to.
     * @param $userid ID of the user
     * @param $curid  ID of the curriculum
     * @param $cnt    Optional return count
     * @uses $DB
     * @return unknown_type
     */
    static function get_non_curriculum_classes($userid, &$cnt = null) {
        global $DB;
        $select = 'SELECT curcrs.*, crs.name AS coursename, crs.id AS courseid, cls.id AS classid';
        $sql = '  FROM {'.student::TABLE.'} clsenrol
                  JOIN {'.pmclass::TABLE.'} cls ON cls.id = clsenrol.classid
                  JOIN {'.course::TABLE.'} crs ON crs.id = cls.courseid
             LEFT JOIN (SELECT curcrs.courseid
                          FROM {'.curriculumcourse::TABLE.'} curcrs
                          JOIN {'.curriculumstudent::TABLE.'} curass ON curass.curriculumid = curcrs.curriculumid AND curass.userid = ?) curcrs
                       ON curcrs.courseid = crs.id
                 WHERE clsenrol.userid = ? AND curcrs.courseid IS NULL';

        $params = array($userid, $userid);
        $rs = $DB->get_recordset_sql($select . $sql, $params);
        if ($cnt !== null) {
            $cnt = $rs->valid() ? $DB->count_records_sql("SELECT COUNT('x') {$sql}", $params)
                                : 0;
        }
        return $rs;
    }

    /**
     * Retrieves a list of courses that:
     * - Belong to the specified curriculum.
     * - The user is not currently enrolled in.
     * @param $userid ID of the user to retrieve the courses for.
     * @param $curid ID of the curriculum to retrieve the courses for.
     * @uses $DB
     * @return unknown_type
     */
    static function get_user_course_curriculum($userid, $curid) {
        global $DB;
        $sql = 'SELECT DISTINCT curcrs.*, crs.name AS coursename, cls.count as classcount, prereq.count as prereqcount, enrol.completestatusid as completionid, waitlist.courseid as waiting
                  FROM {'.curriculumcourse::TABLE.'} curcrs
                  JOIN {'.course::TABLE.'} crs ON curcrs.courseid = crs.id
                       -- limit to non-enrolled courses
                  LEFT JOIN (SELECT cls.courseid, clsenrol.completestatusid FROM {'.pmclass::TABLE.'} cls
                          JOIN {'.student::TABLE.'} clsenrol ON cls.id = clsenrol.classid AND clsenrol.userid = :userida) enrol
                       ON enrol.courseid = crs.id
                       -- limit to courses where user is not on waitlist
             LEFT JOIN (SELECT cls.courseid
                          FROM {'.pmclass::TABLE.'} cls
                          JOIN {'.waitlist::TABLE.'} watlst ON cls.id = watlst.classid AND watlst.userid = :useridb) waitlist
                       ON waitlist.courseid = crs.id
                       -- count the number of classes for each course
             LEFT JOIN (SELECT cls.courseid, COUNT(*) as count
                          FROM {'.pmclass::TABLE.'} cls
                               -- enddate is beginning of day
                         WHERE (cls.enddate > (:currtimea - 24*60*60)) OR NOT cls.enddate
                      GROUP BY cls.courseid) cls
                       ON cls.courseid = crs.id
                       -- count the number of unsatisfied prerequisities
             LEFT JOIN (SELECT prereq.curriculumcourseid, COUNT(*) as count
                          FROM {'.courseprerequisite::TABLE.'} prereq
                          JOIN {'.course::TABLE.'} crs ON prereq.courseid = crs.id
                     LEFT JOIN (SELECT cls.courseid
                                  FROM {'.pmclass::TABLE.'} cls
                                  JOIN {'.student::TABLE.'} enrol ON enrol.classid = cls.id
                                 WHERE enrol.completestatusid = '.student::STUSTATUS_PASSED.' AND enrol.userid = :useridc
                                   AND (cls.enddate > :currtimeb OR NOT cls.enddate)) cls
                               ON cls.courseid = crs.id
                         WHERE cls.courseid IS NULL
                      GROUP BY prereq.curriculumcourseid) prereq
                       ON prereq.curriculumcourseid = curcrs.id
                 WHERE curcrs.curriculumid = :curid
              ORDER BY curcrs.position';

        $time_now = time();
        $params = array(
            'userida'   => $userid,
            'useridb'   => $userid,
            'useridc'   => $userid,
            'currtimea' => $time_now,
            'currtimeb' => $time_now,
            'curid'     => $curid,
        );
        return $DB->get_recordset_sql($sql, $params);
    }

    /**
     * Retrieves a list of classes the user instructs.
     * @param $userid ID of the user
     * @param $cnt    Optional return count
     * @uses $DB
     * @return unknown_type
     */
    static function get_instructed_classes($userid, &$cnt = null) {
        global $DB;
        $select = 'SELECT cls.*, crs.name AS coursename';
        $sql = '  FROM {'.pmclass::TABLE.'} cls
                  JOIN {'.course::TABLE.'} crs ON cls.courseid = crs.id
                  JOIN {'.instructor::TABLE.'} clsinstr ON cls.id = clsinstr.classid
                 WHERE clsinstr.userid = ?
              GROUP BY cls.id ';
        $rs = $DB->get_recordset_sql($select . $sql, array($userid));
        if ($cnt !== null) {
            $cnt = $rs->valid() ? $DB->count_records_sql("SELECT COUNT('x') {$sql}", array($userid))
                                : 0;
        }
        return $rs;
    }

    /**
     * Obtain the appropriate information regarding program courses as needed
     * by the dashboard
     *
     * @param boolean $tab_sensitive true if we need to be concerned about whether
     *                               we are on the current or archived tab, otherwise
     *                               false
     * @param boolean $show_archived specifies whether we're showing archived or non-
     *                               archived courses (ignored if tab_sensitive is false)
     * @param boolean $showcompleted specifies whether we're showing passed and failed
     *                               courses in addition to ones in progress
     * @param mixed $programid a specific program id to look for data related to, or
     *                         NULL for all
     * @return array a list of values, where the first entry is the user's course list,
     *               the second is a mapping of programs to a course listing, the third
     *               is a list of classes handled, and the fourth is the number of programs
     *               handled, the fifth is a mapping of program ids to number of compelted
     *               courses, the sixth is a mapping of program ids to total number of courses
     */
    function get_dashboard_program_data($tab_sensitive, $show_archived, $showcompleted = false,
                                        $programid = NULL) {
        global $CFG, $DB;

        $archive_var     = '_elis_program_archive';
        $classids = array();
        //track mapping of programs to course / class listings
        $curriculas = array();
        $totalcurricula = 0;

        //map program ids to appropriate counts
        $completecoursesmap = array();
        $totalcoursesmap = array();

        $params = array($this->id);

        //set up a condition for when handling a specific program
        $program_condition = '';
        if ($programid !== NULL) {
            $program_condition = 'AND cur.id = ?';
            $params[] = $programid;
        }

        $sql = 'SELECT curstu.id, curstu.curriculumid as curid, cur.name as name
                  FROM {'. curriculumstudent::TABLE .'} curstu
                  JOIN {'. curriculum::TABLE .'} cur
                    ON cur.id = curstu.curriculumid
                 WHERE curstu.userid = ?
                 '.$program_condition.'
              ORDER BY cur.priority ASC, cur.name ASC';

        //mapping of completion status to display string
        $status_mapping = array(STUSTATUS_PASSED => get_string('passed', 'elis_program'),
                                STUSTATUS_FAILED => get_string('failed', 'elis_program'),
                                STUSTATUS_NOTCOMPLETE => get_string('n_completed', 'elis_program'));

        if ($usercurs = $DB->get_records_sql($sql, $params)) {
            //^pre-ELIS-3615 WAS: if ($usercurs = curriculumstudent::get_curricula($this->id)) {
            foreach ($usercurs as $usercur) {
                // Check if this curricula is set as archived and whether we want to display it
                $crlm_context = context_elis_program::instance($usercur->curid);
                $data_array = field_data::get_for_context_and_field($crlm_context, $archive_var);
                $crlm_archived = 0;
                if (!empty($data_array) && is_object($data_array->rs) && !empty($data_array->rs)) {
                    $crlm_archived = !empty($data_array->rs->current()->data) ? 1 : 0;
                }

                //being insensitive to which tab we're on gets us this listing "for free"
                if (!$tab_sensitive || $show_archived == $crlm_archived) {
                    $totalcurricula++;
                    $curriculas[$usercur->curid]['id'] = $usercur->curid;
                    $curriculas[$usercur->curid]['name'] = $usercur->name;
                    $data = array();

                    //count our totals per-program
                    //note that "course" is really one enrolment, so this should
                    //count each enrolment, plus one for each unenrolled program course
                    $totalcourses = 0;
                    $completecourses = 0;

                    $courses = curriculumcourse_get_listing($usercur->curid, 'curcrs.position, crs.name', 'ASC');
                    foreach ($courses as $course) {
                        $course_obj = new course($course->courseid);
                        $coursedesc = $course_obj->syllabus;

                        $cdata = student_get_class_from_course($course->courseid, $this->id);
                        if ($cdata->valid() === true) {
                            foreach($cdata as $classdata) {
                                //count each enrolment as one "course"
                                $totalcourses++;

                                if (!in_array($classdata->id, $classids)) {
                                    $classids[] = $classdata->id;
                                }

                                if ($classdata->completestatusid == STUSTATUS_PASSED ||
                                    $classdata->completestatusid == STUSTATUS_FAILED) {
                                    //count completed enrolments
                                    $completecourses++;
                                }

                                if (!$showcompleted && ($classdata->completestatusid == STUSTATUS_PASSED ||
                                                        $classdata->completestatusid == STUSTATUS_FAILED)) {
                                    //not showing completed courses, so skip this course
                                    continue;
                                }

                                if ($mdlcrs = moodle_get_course($classdata->id)) {
                                    $coursename = '<a href="' . $CFG->wwwroot . '/course/view.php?id=' .
                                        $mdlcrs . '">' . $course->coursename . '</a>';
                                } else {
                                    $coursename = $course->coursename;
                                }

                                $data[] = array(
                                    $coursename,
                                    $classdata->idnumber,
                                    $coursedesc,
                                    pm_display_grade($classdata->grade),
                                    $status_mapping[$classdata->completestatusid],
                                    $classdata->completestatusid == STUSTATUS_PASSED && !empty($classdata->completetime) ?
                                        userdate($classdata->completetime, get_string('pm_date_format', 'elis_program')) : get_string('na','elis_program')
                                    );
                            }
                        } else {
                            //count this unenrolled course toward the total
                            $totalcourses++;

                            $data[] = array(
                                $course->coursename,
                                get_string('dashboard_na', 'elis_program'),
                                $coursedesc,
                                0,
                                get_string('not_enrolled', 'elis_program'),
                                get_string('na','elis_program')
                            );
                        }
                        unset($cdata);
                    }
                    unset($courses);

                    $curriculas[$usercur->curid]['data'] = $data;
                    //associate this program id with the appropriate counts
                    $completecoursesmap[$usercur->curid] = $completecourses;
                    $totalcoursesmap[$usercur->curid] = $totalcourses;

                } else {

                    // Keep note of the classid's regardless if set archived or not for later use in determining non-curricula courses
                    $courses = curriculumcourse_get_listing($usercur->curid, 'curcrs.position, crs.name', 'ASC');
                    foreach ($courses as $course) {
                        $cdata = student_get_class_from_course($course->courseid, $this->id);
                        foreach ($cdata as $classdata) {
                            if (!in_array($classdata->id, $classids)) {
                                $classids[] = $classdata->id;
                            }
                        }
                        unset($cdata);
                    }
                    unset($courses);

                }
            }
        }

        return array($usercurs, $curriculas, $classids, $totalcurricula, $completecoursesmap, $totalcoursesmap);
    }

    /**
     * Convert a listing of a particular program's courses to a data table
     *
     * @param array $curricula A listing of table entries for a particular program
     * @return object the table that can be used to display this information
     */
    function get_dashboard_program_table($curricula) {
        $table = new html_table();
        $table->head = array(
            get_string('course', 'elis_program'),
            get_string('class', 'elis_program'),
            get_string('description', 'elis_program'),
            get_string('score', 'elis_program'),
            get_string('student_status', 'elis_program'),
            get_string('date', 'elis_program')
        );
        $table->data = $curricula['data'];

        return $table;
    }

    /**
     * Obtain the appropriate information regarding program courses as needed
     * by the dashboard
     *
     * @param array $classids a list of class ids we want to specifically
     *                        ignore in this listing (i.e. classes already
     *                        accounted for within programs)
     * @param boolean $showcompleted specifies whether we're showing passed and failed
     *                               courses in addition to ones in progress
     * @return array an array where the first element is a recordset containing the course listing,
     *               or false if none found, the second is the number of completed courses, the
     *               third is the total number of courses
     */
    function get_dashboard_nonprogram_data($classids, $showcompleted = false) {
        global $DB;

        $status_condition = '';
        //parameters needed by the main query
        $params = array($this->id);
        //parameters needed by the count query
        $count_params = array($this->id);

        if (!$showcompleted) {
            list($status_condition, $status_params) = $DB->get_in_or_equal(array(STUSTATUS_NOTCOMPLETE));
            $params = array_merge($params, $status_params);
            $status_condition = 'AND stu.completestatusid '.$status_condition;
        }

        if (!empty($classids)) {
            //querying for specific classes

            //fragment used in both queries
            $query_body = 'FROM {'.student::TABLE.'} stu
                           INNER JOIN {'.pmclass::TABLE.'} cls ON cls.id = stu.classid
                           INNER JOIN {'.course::TABLE.'} crs ON crs.id = cls.courseid
                           WHERE userid = ?
                           AND classid '. (count($classids) == 1 ? '!= '. current($classids) :
                           'NOT IN ('. implode(', ', $classids) .')');

            //query for fetching data
            $sql = "SELECT stu.id, stu.classid, crs.name as coursename, cls.idnumber,
                           stu.completetime, stu.grade, stu.completestatusid
                    {$query_body}
                    {$status_condition}
                    ORDER BY crs.name ASC, stu.completetime ASC";

            //query for counting
            $count_sql = "SELECT COUNT(*) AS totalcourses,
                          SUM(CASE stu.completestatusid
                                WHEN ".STUSTATUS_NOTCOMPLETE." THEN 0
                                ELSE 1
                              END) AS completecourses
                          {$query_body}";
        } else {
            //querying for all non-program info

            //fragment used in both queries
            $query_body = 'FROM {'.student::TABLE.'} stu
                           INNER JOIN {'.pmclass::TABLE.'} cls ON cls.id = stu.classid
                           INNER JOIN {'.course::TABLE.'} crs ON crs.id = cls.courseid
                           WHERE userid = ?
                           AND NOT EXISTS (
                             SELECT *
                             FROM {'.curriculumstudent::TABLE.'} ca
                             JOIN {'.curriculumcourse::TABLE.'} cc
                               ON ca.curriculumid = cc.curriculumid
                             WHERE ca.userid = stu.userid
                               AND crs.id = cc.courseid
                           )';

            //query for fetching data
            $sql = "SELECT stu.id, stu.classid, crs.name as coursename, cls.idnumber,
                           stu.completetime, stu.grade, stu.completestatusid
                    {$query_body}
                    {$status_condition}
                    ORDER BY crs.name ASC, stu.completetime ASC";

            //query for counting
            $count_sql = "SELECT COUNT(*) AS totalcourses,
                          SUM(CASE stu.completestatusid
                                WHEN ".STUSTATUS_NOTCOMPLETE." THEN 0
                                ELSE 1
                              END) AS completecourses
                          {$query_body}";
        }

        $classes = $DB->get_recordset_sql($sql, $params);
        //obtain completed and total counts
        $counts_record = $DB->get_record_sql($count_sql, $count_params);

        if (!$classes->valid()) {
            //return data in necessary structure
            return array(false, $counts_record->completecourses, $counts_record->totalcourses);
        }

        //return data in necessary structure
        return array($classes, $counts_record->completecourses, $counts_record->totalcourses);
    }

    /**
     * Convert a listing of non-program courses to a data table
     *
     * @param array $classes A listing of table entries for non-program courses
     * @return object the table that can be used to display this information
     */
    function get_dashboard_nonprogram_table($classes) {
        global $CFG;

        $status_mapping = array(STUSTATUS_PASSED => get_string('passed', 'elis_program'),
                                STUSTATUS_FAILED => get_string('failed', 'elis_program'),
                                STUSTATUS_NOTCOMPLETE => get_string('n_completed', 'elis_program'));

        $table = new html_table();
        $table->head = array(
            get_string('course', 'elis_program'),
            get_string('class', 'elis_program'),
            get_string('score', 'elis_program'),
            get_string('student_status', 'elis_program'),
            get_string('date', 'elis_program')
        );

        $table->data = array();

        if ($classes != false) {
            //have one or more classes

            foreach ($classes as $class) {
                if ($mdlcrs = moodle_get_course($class->classid)) {
                    $coursename = '<a href="' . $CFG->wwwroot . '/course/view.php?id=' .
                                  $mdlcrs . '">' . $class->coursename . '</a>';
                } else {
                    $coursename = $class->coursename;
                }

                $table->data[] = array(
                    $coursename,
                    $class->idnumber,
                    pm_display_grade($class->grade),
                    $status_mapping[$class->completestatusid],
                    $class->completestatusid == STUSTATUS_PASSED && !empty($class->completetime) ?
                        userdate($class->completetime, get_string('pm_date_format', 'elis_program')) : get_string('na','elis_program')
                );
            }
        }

        return $table;
    }

    /**
     * Obtains the HTML needed to show the summary / counts for a particular program
     * or for non-program courses
     *
     * @param int $completecourses the number of courses completed
     * @param int $totalcourses the total number of courses
     * @param mixed $programid the id of the program, or false for non-program courses
     */
    function get_dashboard_program_summary($completecourses, $totalcourses, $programid) {
        global $OUTPUT;

        //data for lang string
        $a = new stdClass;
        $a->completecourses = $completecourses;
        $a->totalcourses = $totalcourses;

        //determine whether we are allow students to view completed courses
        //(value default to enabled)
        $allow_show_completed = !isset(elis::$config->elis_program->display_completed_courses) ||
                                !empty(elis::$config->elis_program->display_completed_courses);

        $output = '';

        if (!$allow_show_completed) {
            if ($completecourses == $totalcourses) {
                //special message because no courses are displayed and we can't show them
                if ($programid == false) {
                    //nonprogram case
                    $output = get_string('dashboard_summary_nonprogram_hidden', 'elis_program');
                } else {
                    //program case
                    $output = get_string('dashboard_summary_program_hidden', 'elis_program');
                }
            }
        } else if ($completecourses > 0) {
            //at least one course is hidden by default

            if ($programid == false) {
                //nonprogram cases
                if ($completecourses == $totalcourses) {
                    //all courses hidden
                    $output = get_string('dashboard_summary_nonprogram_all', 'elis_program', $a);
                } else {
                    //some courses hidden
                    $output = get_string('dashboard_summary_nonprogram', 'elis_program', $a);
                }
            } else {
                //program case
                if ($completecourses == $totalcourses) {
                    //all courses hidden
                    $output = get_string('dashboard_summary_program_all', 'elis_program', $a);
                } else {
                    //some courses hidden
                    $output = get_string('dashboard_summary_program', 'elis_program', $a);
                }
            }
        }

        if ($output != '') {

            //only add the link if we can actually toggle
            if ($allow_show_completed) {
                //add some spacing
                $br_tag = html_writer::empty_tag('br');
                $output .= $br_tag.$br_tag;

                //static part of the "show all" text
                $output .= get_string('dashboard_show_all', 'elis_program');

                //the link
                $show_all_text = get_string('dashboard_show_all_link', 'elis_program');
                $parameter = $programid == false ? 'false' : $programid;
                $attributes = array('href' => '#',
                                    'onclick' => 'toggleCompletedCoursesViaLink('.$parameter.');return false;');
                $output .= html_writer::tag('a', $show_all_text, $attributes);
            }

            //display info in a nice box
            return $OUTPUT->box($output);
        }

        //nothing to show
        return '';
    }

    /**
     * Get the user dashboard report view.
     *
     * @uses $CFG
     * @uses $OUTPUT
     * @param none
     * @return string The HTML for the dashboard report.
     * @todo move out of this class
     */
    function get_dashboard() {
        global $CFG, $OUTPUT, $DB, $PAGE;

        require_once elispm::lib('data/curriculumstudent.class.php');

        $PAGE->requires->js('/elis/program/js/util.js');
        $PAGE->requires->js('/elis/program/js/dashboard.js');

        $content         = '';
        $archive_var     = '_elis_program_archive';

        if (optional_param('tab','',PARAM_CLEAN) == 'archivedlp') {
            $tab = 'archivedlp';
            $show_archived = 1;
        } else {
            $tab = 'currentlp';
            $show_archived = 0;
        }

        //obtain all of our core program-specific data
        list($usercurs, $curriculas, $classids, $totalcurricula, $completecoursesmap, $totalcoursesmap) =
             $this->get_dashboard_program_data(true, $show_archived);

        // Show different css for IE below version 8
        if (check_browser_version('MSIE',7.0) && !check_browser_version('MSIE',8.0)) {
            // IEs that are lower than version 8 do not get the float because it messes up the tabs at the top of the page for some reason
            $float_style = 'text-align:right;';
        } else {
            // Sane browsers get the float tag
            $float_style = 'text-align:right; float:right;';
        }

        // Tab header
        $field_exists = field::get_for_context_level_with_name('curriculum', $archive_var);
        if (!empty($field_exists)) {
            $tabrow = array();
            $tabrow[] = new tabobject('currentlp', $CFG->wwwroot.'/elis/program/index.php?tab=currentlp',
                                      get_string('tab_current_learning_plans','elis_program'));
            $tabrow[] = new tabobject('archivedlp', $CFG->wwwroot.'/elis/program/index.php?tab=archivedlp',
                                      get_string('tab_archived_learning_plans','elis_program'));
            $tabrows = array($tabrow);
            print_tabs($tabrows, $tab);
        }

        $content .= $OUTPUT->heading(get_string('learningplanwelcome', 'elis_program', fullname($this)));

        if ($totalcurricula === 0) { // ELIS-3615 was: if ($totalcourses === 0)
            $blank_lang = ($tab == 'archivedlp') ? 'noarchivedplan' : 'nolearningplan';
            $content .= '<br /><center>' . get_string($blank_lang, 'elis_program') . '</center>';
        }

        // Load the user preferences for hide/show button states
        if ($collapsed = get_user_preferences('crlm_learningplan_collapsed_curricula')) {
            $collapsed_array = explode(',',$collapsed);
        } else {
            $collapsed = '';
            $collapsed_array = array();
        }

        //use a div to track which programs have completed elements displayed
        $content .= '<input type="hidden" name="displayedcompleted" id="displayedcompleted" value="">';
        $content .= '<input type="hidden" name="collapsed" id="collapsed" value="' . $collapsed . '">';

        //determine whether we are allow students to view completed courses
        //(value default to enabled)
        $allow_show_completed = !isset(elis::$config->elis_program->display_completed_courses) ||
                                !empty(elis::$config->elis_program->display_completed_courses);

        if (!empty($usercurs)) {
            foreach ($usercurs as $usercur) {
                if (!isset($curriculas[$usercur->curid])) {
                    continue;
                }
                $curricula = $curriculas[$usercur->curid];
                //convert our data to an output table
                $table = $this->get_dashboard_program_table($curricula);

                $curricula_name = empty(elis::$config->elis_program->disablecoursecatalog)
                                ? ('<a href="index.php?s=crscat&section=curr&showcurid=' . $curricula['id'] . '">' . $curricula['name'] . '</a>')
                                : $curricula['name'];

                $header_curr_name = get_string('learningplanname', 'elis_program', $curricula_name);

                //only show toggle if enabled via PM config
                if ($allow_show_completed) {
                    //do not display toggle button if program is hidden
                    $displayed = in_array($curricula['id'], $collapsed_array) ? 'false' : 'true';
                    //grey out toggle button if there are not hidden courses
                    $enabled = $completecoursesmap[$curricula['id']] > 0 ? 'true' : 'false';

                    //javascript code for toggling display of completed courses
                    $jscode = 'toggleCompletedInit("curriculum'.$curricula['id'].'script", '
                            . '"curriculum'.$curricula['id'].'completedbutton", "'
                            . get_string('showcompletedcourses', 'elis_program').'", "'
                            . get_string('hidecompletedcourses', 'elis_program').'", "'
                            . get_string('showcompletedcourses', 'elis_program').'", "curriculum-'.$curricula['id'].'", '
                            . $displayed.', '.$enabled.');';
                    $PAGE->requires->js_init_code($jscode, true);
                }

                if (in_array($curricula['id'],$collapsed_array)) {
                    $button_label = get_string('showcourses','elis_program');
                    $extra_class = ' hide';
                } else {
                    $button_label = get_string('hidecourses','elis_program');
                    $extra_class = '';
                }

                $jscode = 'toggleVisibleInitWithState("curriculum'.$curricula['id'].'script", '
                        . '"curriculum'.$curricula['id'].'button", "'
                        . $button_label.'", "'.get_string('hidecourses','elis_program').'", "'
                        . get_string('showcourses','elis_program').'", "curriculum-'.$curricula['id'].'");';
                $PAGE->requires->js_init_code($jscode, true);

                $heading = '<div class="clearfix"></div>'
                         . '<div style="'.$float_style.'"><div id="curriculum'.$curricula['id'].'script"></div></div>'
                         . $header_curr_name;

                $content .= '<div class="dashboard_curricula_block">';
                $content .= $OUTPUT->heading($heading);
                $content .= '<div id="curriculum-' . $curricula['id'] . '" class="yui-skin-sam ' . $extra_class . '">';

                if (empty($curricula['data']) && $totalcoursesmap[$usercur->curid] == 0) {
                    //nothing in the table, and no completed courses are being hidden
                    $content .= $OUTPUT->box(get_string('nocoursedescassoc','elis_program'));
                } else {
                    if (!empty($table->data)) {
                        //we are showing some data
                        $content .= html_writer::table($table);
                    }

                    //display the summary text
                    $completecourses = $completecoursesmap[$usercur->curid];
                    $totalcourses = $totalcoursesmap[$usercur->curid];
                    $content .= $this->get_dashboard_program_summary($completecourses, $totalcourses, $usercur->curid);
                }
                $content .= '</div>';
                $content .= '</div>';
            }
        }

        /// Completed non-curricula course data
        if ($tab != 'archivedlp') {
            //obtain all of our core non-program course data
            list($classes, $completecourses, $totalcourses) = $this->get_dashboard_nonprogram_data($classids);

            //if ($classes != false) {
            if ($totalcourses > 0) {
                //convert this data to a display table
                $table = $this->get_dashboard_nonprogram_table($classes);

                $header_curr_name = get_string('noncurriculacourses', 'elis_program');

                //only show toggle if enabled via PM config
                if ($allow_show_completed) {
                    //do not display toggle button if non-program courses are hidden
                    $displayed = in_array('na', $collapsed_array) ? 'false' : 'true';
                    //grey out toggle button if no non-program courses are hidden
                    $enabled = $completecourses > 0 ? 'true' : 'false';

                    //javascript code for toggling display of completed courses
                    $js = 'toggleCompletedInit("noncurriculascript", "noncurriculacompletedbutton", "'
                           .get_string('showcompletedcourses', 'elis_program').'", "'
                           .get_string('hidecompletedcourses', 'elis_program').'", "'
                           .get_string('showcompletedcourses', 'elis_program').'", "curriculum-na", '
                           .$displayed.', '.$enabled.');';
                    $PAGE->requires->js_init_code($js, true);
                }

                if (in_array('na',$collapsed_array)) {
                    $button_label = get_string('showcourses','elis_program');
                    $extra_class = ' hide';
                } else {
                    $button_label = get_string('hidecourses','elis_program');
                    $extra_class = '';
                }

                $jscode = 'toggleVisibleInitWithState("noncurriculascript", "noncurriculabutton", "'
                                 . $button_label . '", "' . get_string('hidecourses','elis_program') . '", "'
                                 . get_string('showcourses','elis_program') . '", "curriculum-na");';
                $PAGE->requires->js_init_code($jscode, true);

                $heading = '<div class="clearfix"></div>'
                         . '<div style="'.$float_style.'"><div id="noncurriculascript"></div></div>'
                         . $header_curr_name;

                $content .= '<div class="dashboard_curricula_block">';
                $content .= $OUTPUT->heading($heading);
                $content .= '<div id="curriculum-na" class="yui-skin-sam ' . $extra_class . '">';

                if (count($table->data) > 0) {
                    //we are showing some data
                    $content .= html_writer::table($table);
                }

                $content .= $this->get_dashboard_program_summary($completecourses, $totalcourses, false);
                $content .= '</div>';
                $content .= '</div>';
            }
        }

        return $content;
    }

    /**
     * Function to handle Moodle user deletion events
     *
     * @param object $user  The Moodle user that was deleted
     * @return boolean true is successful, otherwise FALSE
     */
    static function user_deleted_handler($user) {
        global $DB;

        require_once(elis::lib('data/data_filter.class.php'));
        require_once(elispm::lib('data/usermoodle.class.php'));

        usermoodle::delete_records(new field_filter('muserid', $user->id), $DB);
    }
}


/**
 * "Show inactive users" filter type.
 */
class pm_show_inactive_filter extends user_filter_type {
    /**
     * options for the list values
     */
    var $_options;

    var $_field;

    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     * @param array $options select options
     */
    function pm_show_inactive_filter($name, $label, $advanced, $field, $options) {
        parent::user_filter_type($name, $label, $advanced);
        $this->_field   = $field;
        $this->_options = $options;
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     */
    function setupForm(&$mform) {
        $mform->addElement('select', $this->_name, $this->_label, $this->_options);

        // TODO: add help
        //$mform->setHelpButton($this->_name, array('simpleselect', $this->_label, 'filters'));

        if ($this->_advanced) {
            $mform->setAdvanced($this->_name);
        }
    }

    /**
     * Retrieves data from the form data
     * @param object $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    function check_data($formdata) {
        $field = $this->_name;

        if (array_key_exists($field, $formdata)) {
            if ($formdata->$field != 0) {
                return array('value' => (string)$formdata->$field);
            }
        }

        return false;
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        $retval = $this->_field . ' = 0';
        $value = $data['value'];

        switch($value) {
        case '1':
            $retval = '1=1';

            break;
        case '2':
            $retval = $this->_field . ' = 1';

            break;
        }

        return array($retval,array());
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    function get_label($data) {
        $retval = '';

        if(!empty($data['value'])) {
            if($data['value'] == 1) {
                $retval = get_string('all');
            } else if($data['value'] == 2) {
                $retval = get_string('inactive', 'elis_program');
            }
        }

        return $retval;
    }
}

class pm_custom_field_filter extends user_filter_type {
    /**
     * options for the list values
     */
    var $_field;

    function pm_custom_field_filter($name, $label, $advanced, $field) {
        parent::user_filter_type($name, $label, $advanced);
        $this->_field   = $field;
    }

    function setupForm(&$mform) {
        $fieldname = "field_{$this->_field->shortname}";

        if (isset($this->_field->owners['manual'])) {
            $manual = new field_owner($this->_field->owners['manual']);
            if (isset($manual->param_control)) {
                $control = $manual->param_control;
            }
        }
        if (!isset($control)) {
            $control = 'text';
        }
        require_once elis::plugin_file('elisfields_manual', "field_controls/{$control}.php");
        $mform->setAdvanced($fieldname); // ELIS-3894: moved up
        call_user_func("{$control}_control_display", $mform, $mform, null, $this->_field, true);

    }

    function check_data($formdata) {
        $field = "field_{$this->_field->shortname}";

        if (!empty($formdata->$field)) {
            return array('value' => (string)$formdata->$field);
        }

        return false;
    }

    function get_sql_filter($data) {
        global $DB;

        static $counter = 0;
        $name = 'ex_elisfield'.$counter++;
        $sql = 'EXISTS (SELECT * FROM {'. $this->_field->data_table() ."} data
                        JOIN {context} ctx ON ctx.id = data.contextid
                        WHERE ctx.instanceid = {crlm_user}.id
                          AND ctx.contextlevel = ".CONTEXT_ELIS_USER."
                          AND data.fieldid = {$this->_field->id}
                          AND ". $DB->sql_like('data.data', ":{$name}", false) .')';
        $params = array($name => "%{$DB->sql_like_escape($data['value'])}%");

        return array($sql, $params);
    }

    function get_label($data) {
        $retval = '';

        if (!empty($data['value'])) {
            $a = new stdClass;
            $a->label = $this->_field->name;
            $a->value = "\"{$data['value']}\"";
            $a->operator = get_string('contains', 'filters');

            return get_string('textlabel', 'filters', $a);
        }

        return $retval;
    }
}

/**
 * Checks a text filter against several fields
 */
class pm_user_filter_text_OR extends user_filter_text {
    var $_fields;

    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $alias an alias to use for the form elements
     * @param array $fields an array of user table field names
     */
    function pm_user_filter_text_OR($name, $label, $advanced, $alias, $fields) {
        parent::user_filter_text($name, $label, $advanced, $alias);
        $this->_fields = $fields;
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @uses $DB
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        global  $DB;
        static $counter = 0;

        $operator = $data['operator'];
        $value    = addslashes($data['value']);
        $params   = array();
        $conditions = array();
        $combine_op = ' OR ';

        foreach ($this->_fields as $field) {
            $param = 'pmufto'. $counter++;
            switch($operator) {
                case 0: // contains
                    $conditions[] = $DB->sql_like($field, ":{$param}", FALSE);
                    $params[$param] = "%{$value}%";
                    break;
                case 1: // does not contain
                    $conditions[] = $DB->sql_like($field, ":{$param}", FALSE, true, true);
                    $params[$param] = "%{$value}%";
                    $combine_op = ' AND ';
                    break;
                case 2: // equal to
                    $conditions[] = $DB->sql_like($field, ":{$param}", FALSE);
                    $params[$param] = $value;
                    break;
                case 3: // starts with
                    $conditions[] = $DB->sql_like($field, ":{$param}", FALSE);
                    $params[$param] = "{$value}%";
                    break;
                case 4: // ends with
                    $conditions[] = $DB->sql_like($field, ":{$param}", FALSE);
                    $params[$param] = "%{$value}";
                    break;
                case 5: // empty
                    $conditions[] = "{$field} = ''";
                    break;
            }
        }
        $sql = '('. implode($combine_op, $conditions) .')';
        return array($sql, $params);
    }
}

/**
 * Class that filters users based on an operation and a userset id
 */
class pm_user_userset_filter extends user_filter_select {
    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        static $counter = 0;
        $name = 'pmuuf'.$counter++;

        $operator = $data['operator'];
        $value    = addslashes($data['value']);
        //reference to the CM user id field
        $field    = $this->_field;

        //determine the necessary operation
        $sql_operator = '';
        switch($operator) {
            case 1: // equal to
                $sql_operator = "IN";
                break;
            case 2: // not equal to
                $sql_operator = "NOT IN";
                break;
            default:
                return '';
        }

        //make sure the main query's user id field belongs to /
        //does not belong to the set of users in the appropriate cluster
        $sql = "$field $sql_operator (
                  SELECT userid
                  FROM {".clusterassignment::TABLE."}
                  WHERE clusterid = :$name
                )";
        $params = array($name => $data['value']);
        return array($sql, $params);
    }
}

/**
 * Class that filters users based on an operation and a program id
 */
class pm_user_program_filter extends user_filter_select {
    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        static $counter = 0;
        $name = 'pmupf'.$counter++;

        $operator = $data['operator'];
        $value    = addslashes($data['value']);
        //reference to the CM user id field
        $field    = $this->_field;

        //determine the necessary operation
        $sql_operator = '';
        switch($operator) {
            case 1: // equal to
                $sql_operator = "IN";
                break;
            case 2: // not equal to
                $sql_operator = "NOT IN";
                break;
            default:
                return '';
        }

        //make sure the main query's user id field belongs to /
        //does not belong to the set of users in the appropriate curriculum
        $sql = "$field $sql_operator (
                  SELECT userid
                  FROM {".curriculumstudent::TABLE."}
                  WHERE curriculumid = :$name
                )";
        $params = array($name => $data['value']);
        return array($sql, $params);
    }
}

/**
 * User filtering wrapper class.
 */
class pm_user_filtering extends user_filtering {
    /**
     * Contructor
     * @param array array of visible user fields
     * @param string base url used for submission/return, null if the same of current page
     * @param array extra page parameters
     */
    function pm_user_filtering($fieldnames=null, $baseurl=null, $extraparams=null) {
        if (empty($fieldnames)) {
            $fieldnames = array(
                'realname' => 0,
                'lastname' => 1,
                'firstname' => 1,
                'idnumber' => 1,
                'email' => 0,
                'city' => 1,
                'country' => 1,
                'username' => 0,
                'language' => 1,
                'clusterid' => 1,
                'curriculumid' => 1,
            	'inactive' => 1,
                );

            $fields = field::get_for_context_level(CONTEXT_ELIS_USER);
            $fields = $fields ? $fields : array();
            foreach ($fields as $field) {
                $fieldnames["field_{$field->shortname}"] = 1;
            }
        }

        parent::user_filtering($fieldnames, $baseurl, $extraparams);
    }

    /**
     * Creates known user filter if present
     *
     * @uses $USER
     * @param string $fieldname
     * @param boolean $advanced
     * @return object filter
     */
    function get_field($fieldname, $advanced) {
        global $USER, $DB;

        $IFNULL = "COALESCE(mi, '')";

        $FULLNAME = $DB->sql_concat_join("' '", array('firstname', $IFNULL, 'lastname'));
        $FIRSTLASTNAME = $DB->sql_concat('firstname', "' '", 'lastname');

        switch ($fieldname) {
        case 'username':    return new user_filter_text('username', get_string('username'), $advanced, 'username');
        case 'realname':    return new pm_user_filter_text_OR('realname', get_string('fullname'),
                                           $advanced, 'fullname',
                                           array($FULLNAME, $FIRSTLASTNAME));
        case 'lastname':    return new user_filter_text('lastname', get_string('lastname'), $advanced, 'lastname');
        case 'firstname':   return new user_filter_text('firstname', get_string('firstname'), $advanced, 'firstname');
        case 'idnumber':    return new user_filter_text('idnumber', get_string('idnumber'), $advanced, 'idnumber');
        case 'email':       return new user_filter_text('email', get_string('email'), $advanced, 'email');

        case 'city':        return new user_filter_text('city', get_string('city'), $advanced, 'city');
        case 'country':     return new user_filter_select('country', get_string('country'), $advanced, 'country', get_string_manager()->get_list_of_countries(), $USER->country);
        case 'timecreated': return new user_filter_date('timecreated', get_string('timecreated'), $advanced, 'timecreated');

        case 'language':
            return new user_filter_select('language', get_string('preferredlanguage'), $advanced, 'language', get_string_manager()->get_list_of_translations(true));

        case 'clusterid':
            $clusters = userset_get_menu();
            //need to reference the user table directly to allow use of filters in DB calls that do not
            //require the full SQL query with table aliases
            return new pm_user_userset_filter('clusterid', get_string('usercluster', 'elis_program'), $advanced, '{'.user::TABLE.'}.id', $clusters);

        case 'curriculumid':
            $choices = program_get_menu();
            //need to reference the user table directly to allow use of filters in DB calls that do not
            //require the full SQL query with table aliases
            return new pm_user_program_filter('curriculumid', get_string('usercurricula', 'elis_program'), $advanced, '{'.user::TABLE.'}.id', $choices);

        case 'inactive':
            $inactive_options = array(get_string('o_active', 'elis_program'), get_string('all'), get_string('o_inactive', 'elis_program'));
            return new pm_show_inactive_filter('inactive', get_string('showinactive', 'elis_program'), $advanced, 'inactive', $inactive_options);


        default:
            if (strncmp($fieldname, 'field_', 6) === 0) {
                $f = substr($fieldname, 6);
                if ($rec = field::get_for_context_level_with_name(CONTEXT_ELIS_USER, $f)) {
                    return new pm_custom_field_filter($fieldname, $rec->shortname, $advanced, $rec);
                }
            }
            return null;
        }
    }

    /**
     * Print the add filter form.
     */
    function display_add($return = false) {
        if ($return) {
            return $this->_addform->_form->toHtml();
        } else {
            $this->_addform->display();
        }
    }

    /**
     * Print the active filter form.
     */
    function display_active($return = false) {
        if ($return) {
            return $this->_activeform->_form->toHtml();
        } else {
            $this->_activeform->display();
        }
    }

    /**
     * Returns sql where statement based on active user filters.  Overridden to provide proper
     * 'show inactive' default condition.
     *
     * @param string $extra sql
     * @return string
     */
    function get_sql_filter($extra='', array $params = NULL) {
        global $SESSION;

        $newextra = '';

        // Include default SQL if inactive filter has not been included in list
        if (empty($SESSION->user_filtering) || !isset($SESSION->user_filtering['inactive']) || !$SESSION->user_filtering['inactive']) {
            $newextra = ($extra ? $extra . ' AND ' : '') . 'inactive=0';
        }

        return parent::get_sql_filter($newextra);
    }
}

/// Non-class supporting functions. (These may be able to replaced by a generic container/listing class)


/**
 * Gets a instructor listing with specific sort and other filters.
 *
 * @param string $sort Field to sort on.
 * @param string $dir Direction of sort.
 * @param int $startrec Record number to start at.
 * @param int $perpage Number of records per page.
 * @param string $namesearch Search string for instructor name.
 * @param string $alpha Start initial of instructor name filter.
 * @return object array Returned records.
 */

function user_get_listing($sort='name', $dir='ASC', $startrec=0, $perpage=0, $namesearch='',
                          $alpha='') {
    global $DB;

    $FULLNAME = $DB->sql_concat_join("' '", array('firstname', 'lastname'));

    $filters = array();

    if (!empty($namesearch)) {
        $namesearch = trim($namesearch);
        $filters[] = new field_filter($FULLNAME, "%{$DB->sql_like_escape($namesearch)}%", field_filter::LIKE);
    }

    if ($alpha) {
        $filters[] = new field_filter($FULLNAME, "{$DB->sql_like_escape($alpha)}%", field_filter::LIKE);
    }

    if ($sort) {
        $sort = array($sort,$dir);
    } else {
        $sort = array();
    }

    return user::find(new AND_filter($filters), $sort, $startrec, $perpage);
}


function user_count_records() {
    return data_record::count();
}

