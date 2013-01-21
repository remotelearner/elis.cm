<?php

/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @subpackage core
 * @author     Remote-Learner.net Inc
 * @author     James McQuillan <james.mcquillan@remote-learner.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

class elis_datagen {
    protected $DB;

    public function __construct(moodle_database &$DB) {
        $this->DB =& $DB;
    }
}

class elis_program_datagen extends elis_datagen{

    public function create_program(array $info=array()) {
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
            $info = array_intersect_key($info,$defaults);
        }
        $info = array_merge($defaults,$info);
        return (object)$info;
    }

    public function create_track(array $info=array()) {
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
            $info = array_intersect_key($info,$defaults);
        }
        $info = array_merge($defaults,$info);
        return (object)$info;
    }

    public function create_course(array $info=array()) {
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
            $info = array_intersect_key($info,$defaults);
        }
        $info = array_merge($defaults,$info);
        return (object)$info;
    }

    public function create_pmclass(array $info=array()) {
        $uniq = uniqid();
        $now = time();

        $defaults = array(
            'idnumber' => 'CLS_'.$uniq,
            'courseid' => '',
            'startdate' => $now,
            'enddate' => ($now+604800), //7 days
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
            $info = array_intersect_key($info,$defaults);
        }
        $info = array_merge($defaults,$info);
        return (object)$info;
    }

    public function create_user(array $info=array()) {
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
            $info = array_intersect_key($info,$defaults);
        }
        $info = array_merge($defaults,$info);
        return (object)$info;
    }

    public function create_userset(array $info=array()) {
        $uniq = uniqid();

        $defaults = array(
            'name' => 'Userset '.$uniq,
            'display' => '',
            'parent' => '0',
            'depth' => '1',
        );

        if (!empty($info)) {
            $info = array_intersect_key($info,$defaults);
        }
        $info = array_merge($defaults,$info);
        return (object)$info;
    }

    public function assign_user_to_class(array $info=array()) {
        $defaults = array(
            'classid' => 1,
            'userid' => 1,
        );

        if (!empty($info)) {
            $info = array_intersect_key($info,$defaults);
        }
        $info = array_merge($defaults,$info);
        return (object)$info;
    }

    public function assign_user_to_track(array $info=array()) {
        $defaults = array(
            'trackid' => 1,
            'userid' => 1,
        );

        if (!empty($info)) {
            $info = array_intersect_key($info,$defaults);
        }
        $info = array_merge($defaults,$info);
        return (object)$info;
    }

    public function assign_instructor_to_class(array $info=array()) {
        $defaults = array(
            'classid' => 1,
            'userid' => 1,
        );

        if (!empty($info)) {
            $info = array_intersect_key($info,$defaults);
        }
        $info = array_merge($defaults,$info);
        return (object)$info;
    }

    public function assign_class_to_track(array $info=array()) {
        $defaults = array(
            'courseid' => 1,
            'classid' => 1,
            'trackid' => 1,
            'autoenrol' => 0
        );

        if (!empty($info)) {
            $info = array_intersect_key($info,$defaults);
        }
        $info = array_merge($defaults,$info);
        return (object)$info;
    }

    public function assign_user_to_curriculum(array $info=array()) {
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

        if (!empty($info)) {
            $info = array_intersect_key($info,$defaults);
        }
        $info = array_merge($defaults,$info);
        return (object)$info;
    }

    public function assign_course_to_curriculum(array $info=array()) {
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

        if (!empty($info)) {
            $info = array_intersect_key($info,$defaults);
        }
        $info = array_merge($defaults,$info);
        return (object)$info;
    }
}

class elis_program_datagen_unit extends elis_program_datagen {

    public function create_program(array $info=array()) {
        require_once(elispm::lib('data/curriculum.class.php'));
        $info = parent::create_program($info);
        $info->id = $this->DB->insert_record(curriculum::TABLE,$info);
        return $info;
    }

    public function create_track(array $info=array()) {
        require_once(elispm::lib('data/track.class.php'));
        $info = parent::create_track($info);
        $info->id = $this->DB->insert_record(track::TABLE,$info);
        return $info;
    }

    public function create_course(array $info=array()) {
        require_once(elispm::lib('data/course.class.php'));
        $info = parent::create_course($info);
        $info->id = $this->DB->insert_record(course::TABLE,$info);
        return $info;
    }

    public function create_pmclass(array $info=array()) {
        require_once(elispm::lib('data/pmclass.class.php'));
        $info = parent::create_pmclass($info);
        $info->id = $this->DB->insert_record(pmclass::TABLE,$info);
        return $info;
    }

    public function create_user(array $info=array()) {
        require_once(elispm::lib('data/user.class.php'));
        $info = parent::create_user($info);
        $info->id = $this->DB->insert_record(user::TABLE,$info);
        return $info;
    }

    public function create_userset(array $info=array()) {
        require_once(elispm::lib('data/userset.class.php'));
        $info = parent::create_userset($info);
        $info->id = $this->DB->insert_record(userset::TABLE,$info);
        return $info;
    }

    public function assign_user_to_class($userid, $pmclassid) {
        require_once(elispm::lib('data/student.class.php'));
        $info = parent::assign_user_to_class(array(
            'classid' => $pmclassid,
            'userid' => $userid,
        ));
        $info->id = $this->DB->insert_record(student::TABLE,$info);
        return $info;
    }

    public function assign_user_to_track($userid, $trackid) {
        require_once(elispm::lib('data/usertrack.class.php'));
        $info = parent::assign_user_to_track(array(
            'trackid' => $trackid,
            'userid' => $userid,
        ));
        $info->id = $this->DB->insert_record(usertrack::TABLE,$info);
        return $info;
    }

    public function assign_instructor_to_class($userid, $pmclassid) {
        require_once(elispm::lib('data/instructor.class.php'));
        $info = parent::assign_instructor_to_class(array(
            'classid' => $pmclassid,
            'userid' => $userid,
        ));
        $info->id = $this->DB->insert_record(instructor::TABLE,$info);
        return $info;
    }

    public function assign_class_to_track($pmclassid, $courseid, $trackid, $autoenrol = false) {
        require_once(elispm::lib('data/track.class.php'));
        $info = parent::assign_class_to_track(array(
            'classid' => $pmclassid,
            'courseid' => $courseid,
            'trackid' => $trackid,
            'autoenrol' => (int)$autoenrol
        ));
        $info->id = $this->DB->insert_record(trackassignment::TABLE,$info);
        return $info;
    }

    public function assign_user_to_curriculum($userid,$curriculumid,$extra=array()) {
        require_once(elispm::lib('data/curriculumstudent.class.php'));
        $data = array_merge(
                array('userid'=>$userid, 'curriculumid'=>$curriculumid),
                $extra
        );
        $info = parent::assign_user_to_curriculum($data);
        $info->id = $this->DB->insert_record(curriculumstudent::TABLE,$info);
        return $info;
    }

    public function assign_course_to_curriculum($courseid,$curriculumid,$extra=array()) {
        require_once(elispm::lib('data/curriculumcourse.class.php'));
        $data = array_merge(
                array('userid'=>$courseid, 'curriculumid'=>$curriculumid),
                $extra
        );
        $info = parent::assign_course_to_curriculum($data);
        $info->id = $this->DB->insert_record(curriculumcourse::TABLE,$info);
        return $info;
    }
}

class elis_program_datagen_integration extends elis_program_datagen {
    protected $save = true;

    public function turn_off_saving() {
        $this->save = false;
    }

    public function turn_on_saving() {
        $this->save = true;
    }

    public function create_program(array $info=array()) {
        require_once(elispm::lib('data/curriculum.class.php'));
        $info = parent::create_program($info);
        $program = new curriculum($info);

        if ($this->save === true) {
            $program->save();
            $program_ctx = context_elis_program::instance($program->id);
        }

        return $program;
    }

    public function create_track(array $info=array()) {
        require_once(elispm::lib('data/track.class.php'));
        $info = parent::create_track($info);

        //if we don't have the track assigned to a program, create a program and assign the track to it
        if (empty($info->curid) && $this->save === true) {
            $program = $this->create_program();
            $info->curid = $program->id;
        }

        $track = new track($info);

        if ($this->save === true) {
            $track->save();
            $track_ctx = context_elis_track::instance($track->id);
        }

        return $track;
    }

    public function create_course(array $info=array()) {
        require_once(elispm::lib('data/course.class.php'));
        $info = parent::create_course($info);
        $course = new course($info);

        if ($this->save === true) {
            $course->save();
            $course_ctx = context_elis_course::instance($course->id);
        }

        return $course;
    }

    public function create_pmclass(array $info=array()) {
        require_once(elispm::lib('data/pmclass.class.php'));
        $info = parent::create_pmclass($info);

        //if class isn't indicated to be linked to a course, create a test course
        if (empty($info->courseid) && $this->save === true) {
            $course = static::create_course();
            $info->courseid = $course->id;
        }

        $pmclass = new pmclass($info);

        if (!empty($info->moodlecourseid)) {
            $pmclass->moodlecourseid = $info->moodlecourseid;
        }

        if (!empty($info->track)) {
            $pmclass->track = $info->track;
        }

        if ($this->save === true) {
            $pmclass->save();
            $pmclass_ctx = context_elis_class::instance($pmclass->id);
        }

        return $pmclass;
    }

    public function create_user(array $info=array()) {
        require_once(elispm::lib('data/user.class.php'));
        $info = parent::create_user($info);
        $user = new user($info);

        if ($this->save === true) {
            $user->save();
            $user_ctx = context_elis_user::instance($user->id);
        }

        return $user;
    }

    public function create_userset(array $info=array()) {
        require_once(elispm::lib('data/userset.class.php'));
        $info = parent::create_userset($info);
        $userset = new userset($info);

        if ($this->save === true) {
            $userset->save();
            $userset_ctx = context_elis_userset::instance($userset->id);
        }

        return $userset;
    }

    public function assign_user_to_class(user $user, pmclass $pmclass) {
        require_once(elispm::lib('data/student.class.php'));
        $info = parent::assign_user_to_class(array(
            'classid' => $pmclass->id,
            'userid' => $user->id,
        ));
        $student = new student($info);
        if ($this->save === true) {
            $student->save();
        }
        return $student;
    }

    public function assign_user_to_track(user $user, track $track) {
        require_once(elispm::lib('data/usertrack.class.php'));
        $info = parent::assign_user_to_track(array(
            'trackid' => $track->id,
            'userid' => $user->id,
        ));
        $usertrack = new usertrack($info);
        if ($this->save === true) {
            $usertrack->save();
        }
        return $usertrack;
    }

    public function assign_instructor_to_class(user $user, pmclass $pmclass) {
        require_once(elispm::lib('data/instructor.class.php'));
        $info = parent::assign_instructor_to_class(array(
            'classid' => $pmclass->id,
            'userid' => $user->id,
        ));
        $instructor = new instructor($info);
        if ($this->save === true) {
            $instructor->save();
        }
        return $instructor;
    }

    public function assign_class_to_track(pmclass $pmclass, track $track, $autoenrol = false) {
        require_once(elispm::lib('data/track.class.php'));
        $info = parent::assign_class_to_track(array(
            'classid' => $pmclass->id,
            'courseid' => $pmclass->courseid,
            'trackid' => $track->id,
            'autoenrol' => (int)$autoenrol
        ));
        $trackassignment = new trackassignment($info);
        if ($this->save === true) {
            $trackassignment->save();
        }
        return $trackassignment;
    }

    public function assign_user_to_curriculum(user $user, curriculum $curriculum) {
        require_once(elispm::lib('data/curriculumstudent.class.php'));
        $curriculumstudent = new curriculumstudent;
        $curriculumstudent->userid = $user->id;
        $curriculumstudent->curriculumid = $curriculum->id;
        if ($this->save === true) {
            $curriculumstudent->save();
        }
        return $curriculumstudent;
    }

    public function assign_course_to_curriculum(course $course, curriculum $curriculum) {
        require_once(elispm::lib('data/curriculumcourse.class.php'));
        $currcourse = new curriculumcourse;
        $currcourse->curriculumid = $curriculum->id;
        $currcourse->courseid = $course->id;
        if ($this->save === true) {
            $currcourse->save();
        }
        return $currcourse;
    }
}