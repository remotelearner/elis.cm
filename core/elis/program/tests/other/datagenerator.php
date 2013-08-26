<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @package    elis_program
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

class elis_datagenerator {
    protected $db;

    public function __construct(moodle_database &$DB) {
        $this->db =& $DB;
    }
}

/**
 * Datagenerator for ELIS unit tests.
 */
class elis_program_datagenerator extends elis_datagenerator{

    /**
     * Create an ELIS program.
     * @param array $info An array of values to set.
     * @return object The resulting database record.
     */
    public function create_program(array $info = array()) {
        require_once(elispm::lib('data/curriculum.class.php'));

        $uniq = uniqid();
        $now = time();

        $defaults = array(
            'idnumber' => 'PGM_'.$uniq,
            'name' => 'Program '.$uniq,
            'description' => 'A test program created for phpunit testing. '.$uniq,
            'reqcredits' => '2.00',
            'iscustom' => '',
            'timecreated' => $now,
            'timemodified' => $now,
            'timetocomplete' => '',
            'frequency' => '',
            'priority' => ''
        );

        if (!empty($info)) {
            $info = array_intersect_key($info, $defaults);
        }
        $info = (object)array_merge($defaults, $info);

        $info->id = $this->db->insert_record(curriculum::TABLE, $info);

        return $info;
    }

    /**
     * Create an ELIS track.
     * @param array $info An array of values to set.
     * @return object The resulting database record.
     */
    public function create_track(array $info = array()) {
        require_once(elispm::lib('data/track.class.php'));

        $uniq = uniqid();
        $now = time();

        $defaults = array(
            'curid' => '0',
            'idnumber' => 'TRK_'.$uniq,
            'name' => 'Track '.$uniq,
            'description' => 'A test track created for phpunit testing. '.$uniq,
            'startdate' => '',
            'enddate' => '',
            'defaulttrack' => '',
            'timecreated' => $now,
            'timemodified' => $now
        );

        if (!empty($info)) {
            $info = array_intersect_key($info, $defaults);
        }
        $info = (object)array_merge($defaults, $info);

        $info->id = $this->db->insert_record(track::TABLE, $info);

        return $info;
    }

    /**
     * Create an ELIS course.
     * @param array $info An array of values to set.
     * @return object The resulting database record.
     */
    public function create_course(array $info=array()) {
        require_once(elispm::lib('data/course.class.php'));

        $uniq = uniqid();
        $now = time();

        $defaults = array(
            'name' => 'Course '.$uniq,
            'code' => '',
            'idnumber' => 'CRS_'.$uniq,
            'syllabus' => 'Test Course Syllabus',
            'documents' => '',
            'lengthdescription' => '',
            'length' => '',
            'credits' => '',
            'completion_grade' => '',
            'environmentid' => '',
            'cost' => '',
            'timecreated' => $now,
            'timemodified' => $now,
            'version' => '',
        );

        if (!empty($info)) {
            $info = array_intersect_key($info, $defaults);
        }
        $info = (object)array_merge($defaults, $info);

        $info->id = $this->db->insert_record(course::TABLE, $info);

        return $info;
    }

    /**
     * Create an ELIS class.
     * @param array $info An array of values to set.
     * @return object The resulting database record.
     */
    public function create_pmclass(array $info = array()) {
        require_once(elispm::lib('data/pmclass.class.php'));

        $uniq = uniqid();
        $now = time();

        $defaults = array(
            'idnumber' => 'CLS_'.$uniq,
            'courseid' => '',
            'startdate' => $now,
            'enddate' => ($now + 604800), // 7 days.
            'duration' => '',
            'starttimehour' => '',
            'starttimeminute' => '',
            'endtimehour' => '',
            'endtimeminute' => '',
            'maxstudents' => '',
            'environmentid' => '',
            'enrol_from_waitlist' => '',
            'moodlecourseid' => '',
            'track' => ''
        );

        if (!empty($info)) {
            $info = array_intersect_key($info, $defaults);
        }
        $info = (object)array_merge($defaults, $info);

        $info->id = $this->db->insert_record(pmclass::TABLE, $info);

        return (object)$info;
    }

    /**
     * Create an ELIS user.
     * @param array $info An array of values to set.
     * @return object The resulting database record.
     */
    public function create_user(array $info=array()) {
        require_once(elispm::lib('data/user.class.php'));

        $uniq = uniqid();
        $now = time();

        $defaults = array(
            'username' => 'testuser_'.$uniq,
            'password' => '12345',
            'idnumber' => 'testuser_'.$uniq,
            'firstname' => 'Test User',
            'lastname' => $uniq,
            'mi' => '',
            'email' => 'testuser_'.$uniq.'@example.com',
            'email2' => '',
            'address' => '',
            'address2' => '',
            'city' => 'Waterloo',
            'state' => 'ON',
            'country' => 'CA',
            'phone' => '',
            'phone2' => '',
            'fax' => '',
            'postalcode' => '',
            'birthdate' => '',
            'gender' => '',
            'language' => '',
            'transfercredits' => '',
            'comments' => '',
            'notes' => '',
            'timecreated' => $now,
            'timeapproved' => $now,
            'timemodified' => $now,
            'inactive' => '0',
        );

        if (!empty($info)) {
            $info = array_intersect_key($info, $defaults);
        }
        $info = (object)array_merge($defaults, $info);

        $info->id = $this->db->insert_record(user::TABLE, $info);

        return $info;
    }

    /**
     * Create an ELIS userset.
     * @param array $info An array of values to set.
     * @return object The resulting database record.
     */
    public function create_userset(array $info=array()) {
        require_once(elispm::lib('data/userset.class.php'));

        $uniq = uniqid();

        $defaults = array(
            'name' => 'Userset '.$uniq,
            'display' => '',
            'parent' => '0',
            'depth' => '1',
        );

        if (!empty($info)) {
            $info = array_intersect_key($info, $defaults);
        }
        $info = (object)array_merge($defaults, $info);

        $info->id = $this->db->insert_record(userset::TABLE, $info);

        return $info;
    }

    /**
     * Create an ELIS custom field category.
     * @param array $name The name of the category.
     */
    public function create_field_category($name = '') {
        require_once(elis::lib('data/customfield.class.php'));
        if (empty($name)) {
            $name = 'Field Category '.uniqid();
        }

        $info = new stdClass;
        $info->name = $name;
        $info->sortorder = 0;
        $info->id = $this->db->insert_record(field_category::TABLE, $info);
        return $info;
    }

    /**
     * Assign an ELIS user to an ELIS class
     * @param int $userid The ID of an ELIS user.
     * @param int $pmclassid The ID of an ELIS class instance.
     * @return object The resulting database record.
     */
    public function assign_user_to_class($userid, $pmclassid) {
        require_once(elispm::lib('data/student.class.php'));
        $info = new stdClass;
        $info->userid = $userid;
        $info->classid = $pmclassid;
        $info->id = $this->db->insert_record(student::TABLE, $info);
        return $info;
    }

    /**
     * Assign an ELIS user to an ELIS track.
     * @param int $userid The ID of an ELIS user.
     * @param int $trackid The ID of an ELIS track.
     * @return object The resulting database record.
     */
    public function assign_user_to_track($userid, $trackid) {
        require_once(elispm::lib('data/usertrack.class.php'));
        $info = new stdClass;
        $info->userid = $userid;
        $info->trackid = $trackid;
        $info->id = $this->db->insert_record(usertrack::TABLE, $info);
        return $info;
    }

    /**
     * Assign an ELIS user to an ELIS class as an instructor.
     * @param int $userid The ID of an ELIS user.
     * @param int $pmclassid The ID of an ELIS class instance.
     * @return object The resulting database record.
     */
    public function assign_instructor_to_class($userid, $pmclassid) {
        require_once(elispm::lib('data/instructor.class.php'));
        $info = new stdClass;
        $info->userid = $userid;
        $info->classid = $pmclassid;
        $info->id = $this->db->insert_record(instructor::TABLE, $info);
        return $info;
    }

    /**
     * Assign an ELIS class instance to an ELIS track.
     * @param int $pmclassid The ID of an ELIS class instance.
     * @param int $courseid The ID of an ELIS course description.
     * @param int $trackid The ID of an ELIS track.
     * @param bool $autoenrol Whether or not to set the autoenrol flag.
     * @return object The resulting database record.
     */
    public function assign_class_to_track($pmclassid, $courseid, $trackid, $autoenrol = false) {
        require_once(elispm::lib('data/track.class.php'));
        $info = new stdClass;
        $info->classid = $pmclassid;
        $info->courseid = $courseid;
        $info->trackid = $trackid;
        $info->autoenrol = (int)$autoenrol;
        $info->id = $this->db->insert_record(trackassignment::TABLE, $info);
        return $info;
    }

    /**
     * Assign an ELIS user to an ELIS program.
     * @param int $userid The ID of an ELIS user.
     * @param int $curriculumid The ID of an ELIS program.
     * @param array $extra An array of extra parameters to set.
     * @return object The resulting database record.
     */
    public function assign_user_to_program($userid, $curriculumid, $extra = array()) {
        require_once(elispm::lib('data/curriculumstudent.class.php'));
        $now = time();
        $defaults = array(
            'userid' => 1,
            'curriculumid' => 1,
            'completed' => 0,
            'timecompleted' => 0,
            'timeexpired' => 0,
            'credits' => 0,
            'locked' => 0,
            'certificatecode' => '',
            'timecreated' => $now,
            'timemodified' => $now,
        );
        $info = array_merge(array('userid' => $userid, 'curriculumid' => $curriculumid), $extra);
        $info = array_intersect_key($info, $defaults);
        $info = (object)array_merge($defaults, $info);
        $info->id = $this->db->insert_record(curriculumstudent::TABLE, $info);
        return $info;
    }

    /**
     * Assign an ELIS course description to an ELIS program.
     * @param int $courseid The ID of an ELIS course description.
     * @param int $curriculumid The ID of an ELIS program.
     * @param array $extra An array of extra parameters to set.
     * @return object The resulting database record.
     */
    public function assign_course_to_program($courseid, $curriculumid, $extra = array()) {
        require_once(elispm::lib('data/curriculumcourse.class.php'));
        $now = time();
        $defaults = array(
            'curriculumid' => 1,
            'courseid' => 1,
            'required' => 0,
            'frequency' => 0,
            'timeperiod' => 0,
            'position' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        );
        $info = array_merge(array('userid' => $courseid, 'curriculumid' => $curriculumid), $extra);
        $info = array_intersect_key($info, $defaults);
        $info = (object)array_merge($defaults, $info);
        $info->id = $this->db->insert_record(curriculumcourse::TABLE, $info);
        return $info;
    }
}