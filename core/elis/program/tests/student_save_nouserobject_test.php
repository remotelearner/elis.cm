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
 * @copyright  (C) 2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__).'/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');

// Data objects.
require_once(elispm::lib('data/classmoodlecourse.class.php'));
require_once(elispm::lib('data/course.class.php'));
require_once(elispm::lib('data/coursetemplate.class.php'));
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/student.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));

class student_save_nouserobject_test extends elis_database_test {

    /**
     * Test student save works when $USER object not set
     */
    public function test_student_save_nouserobject() {
        global $DB, $USER;

        // Create Moodle course category.
        $crscat = create_course_category((object)array(
            'name'     => 'Test Course category',
            'idnumber' => 'MCC-1'
        ));

        // Create Moodle course.
        $crsdata = array(
            'category'  => $crscat->id,
            'fullname'  => 'MC-TEST-ELIS-8484',
            'shortname' => 'MC-TEST-ELIS-8484',
            'idnumber'  => 'MC-TEST-ELIS-8484'
        );

        $mdlcrs = new stdClass;
        $mdlcrs->id = $DB->insert_record('course', (object)$crsdata);

        $cddata = array(
            'name'     => 'CD-ELIS-8484',
            'code'     => 'CD-ELIS-8484',
            'idnumber' => 'CD-ELIS-8484',
            'syllabus' => 'syllabus'
        );
        $cd = new course($cddata);
        $cd->save();

        $ci = new pmclass(array(
            'idnumber'       => 'CI-ELIS-8484',
            'courseid'       => $cd->id,
            'moodlecourseid' => $mdlcrs->id,
            'autocreate'     => 0
        ));
        $ci->save();

        $testuser = new user(array(
            'idnumber'  => 'testuserelis8484',
            'username'  => 'testuserelis8484',
            'firstname' => 'Test',
            'lastname'  => 'User-ELIS8484',
            'email'     => 'tu8484@noreply.com',
            'city'      => 'Waterloo',
            'country'   => 'CA',
        ));
        $testuser->save();

        $USER = null;
        $sturec = new stdClass;
        $sturec->userid = $testuser->id;
        $sturec->classid = $ci->id;
        $sturec->grade = 0;
        $sturec->enrolmenttime = time();
        $student = new student($sturec);
        $student->save();

        $this->assertFalse(empty($student));
        if (!empty($student)) {
            $this->assertFalse(empty($student->id));
        }
    }
}
