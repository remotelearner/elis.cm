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

require_once(dirname(__FILE__).'/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');

// Libs.
require_once(elispm::lib('data/classmoodlecourse.class.php'));
require_once(elispm::lib('data/course.class.php'));
require_once(elispm::lib('data/coursetemplate.class.php'));
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/student.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));
require_once(elispm::lib('data/waitlist.class.php'));
require_once($CFG->dirroot.'/lib/phpunit/classes/util.php');
require_once(elispm::file('tests/other/datagenerator.php'));

/**
 * Test student data object.
 * @group elis_program
 */
class student_testcase extends elis_database_test {

    /**
     * Load initial data from CSVs.
     */
    protected function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            'user' => elis::component_file('program', 'tests/fixtures/mdluser.csv'),
            course::TABLE => elis::component_file('program', 'tests/fixtures/pmcourse.csv'),
            pmclass::TABLE => elis::component_file('program', 'tests/fixtures/pmclass.csv'),
            user::TABLE => elis::component_file('program', 'tests/fixtures/pmuser.csv'),
            student::TABLE => elis::component_file('program', 'tests/fixtures/student.csv'),
            waitlist::TABLE => elis::component_file('program', 'tests/fixtures/waitlist2.csv'),
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * Load initial data from CSVs for moodle enrolments.
     */
    protected function load_csv_data_moodlenrol() {
        $dataset = $this->createCsvDataSet(array(
            'course' => elis::component_file('program', 'tests/fixtures/mdlcourse.csv'),
            'enrol' => elis::component_file('program', 'tests/fixtures/enrol.csv'),
            'user' => elis::component_file('program', 'tests/fixtures/mdluser.csv'),
            'user_enrolments' => elis::component_file('program', 'tests/fixtures/user_enrolments.csv'),
            classmoodlecourse::TABLE => elis::component_file('program', 'tests/fixtures/class_moodle_course.csv'),
            pmclass::TABLE => elis::component_file('program', 'tests/fixtures/pmclass.csv'),
            student::TABLE => elis::component_file('program', 'tests/fixtures/student.csv'),
            user::TABLE => elis::component_file('program', 'tests/fixtures/pmuser.csv'),
            usermoodle::TABLE => elis::component_file('program', 'tests/fixtures/usermoodle.csv'),
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * Test validation of empty userid
     *
     * @expectedException data_object_validation_exception
     */
    public function teststudentvalidationpreventsemptyuserid() {
        $this->load_csv_data();

        $student = new student(array('classid' => 100));

        $student->save();
    }

    /**
     * Test validation of empty classid
     *
     * @expectedException data_object_validation_exception
     */
    public function teststudentvalidationpreventsemptyclassid() {
        $this->load_csv_data();

        $student = new student(array('userid' => 103));

        $student->save();
    }

    /**
     * Test validation of invalid userid
     *
     * @expectedException dml_missing_record_exception
     */
    public function teststudentvalidationpreventsinvaliduserid() {
        $this->load_csv_data();

        $student = new student(array('userid' => 1, 'classid' => 100));

        $student->save();
    }

    /**
     * Test validation of invalid classid
     *
     * @expectedException dml_missing_record_exception
     */
    public function teststudentvalidationpreventsinvalidclassid() {
        $this->load_csv_data();

        $student = new student(array('userid' => 103, 'classid' => 1));

        $student->save();
    }

    /**
     * Test validation of duplicates
     *
     * Note: no exception thrown from student.class.php for dup.
     */
    public function teststudentvalidationpreventsduplicates() {
        global $DB;
        $this->load_csv_data();

        $student = new student(array('userid' => 103, 'classid' => 100));

        $student->save();
        $stus = $DB->get_records(student::TABLE, array('userid' => 103, 'classid' => 100));
        $this->assertEquals(count($stus), 1);
    }

    /**
     * Test the insertion of a valid association record
     */
    public function teststudentvalidationallowsvalidrecord() {
        $this->load_csv_data();

        $student = new student(array('userid' => 103, 'classid' => 101));

        $student->save();

        $this->assertTrue(true);
    }

    /**
     * Test deleting.
     */
    public function test_delete() {
        global $DB;

        $this->load_csv_data_moodlenrol();

        // Verify enrolment exists.
        $enrol = $DB->get_record_sql(
                'SELECT enrol.*
                  FROM {user_enrolments} enrolments
                  JOIN {enrol} enrol ON enrol.id = enrolments.enrolid
                 WHERE enrol.courseid = ?
                   AND enrolments.userid = ?',
                array(100, 100)
        );
        $this->assertNotEmpty($enrol);

        // Delete the student record.
        $student = new student;
        $student->userid = 103;
        $student->classid = 100;
        $student->delete();

        // Verify enrolment deleted.
        $enrol = $DB->get_record_sql(
                'SELECT enrol.*
                  FROM {user_enrolments} enrolments
                  JOIN {enrol} enrol ON enrol.id = enrolments.enrolid
                 WHERE enrol.courseid = ?
                   AND enrolments.userid = ?',
                array(100, 100)
        );

        $this->assertEmpty($enrol);
    }

    public function test_delete_enrols_waitlist() {
        global $DB;
        $this->load_csv_data();

        $student = new student(array('userid' => 104, 'classid' => 100));
        $student->load();
        $student->save();

        $class = new pmclass(100);
        $class->load();
        $class->maxstudents = 1;
        $class->save();

        try {
            $student->delete();
        } catch (Exception $e) {
            $this->assertEquals(get_string('message_nodestinationuser', 'elis_program'), $e->getMessage());
        }
    }

    public function test_get_students() {
        // Fixture.
        $dataset = $this->createCsvDataSet(array(
            user::TABLE => elis::component_file('program', 'tests/fixtures/pmuser.csv'),
            student::TABLE => elis::component_file('program', 'tests/fixtures/student.csv'),
        ));
        $this->loadDataSet($dataset);

        // Test.
        $student = new student;
        $student->classid = 100;
        $students = $student->get_students();
        $this->assertNotEmpty($students);

        // Verify.
        $found = false;
        foreach ($students as $userrec) {
            if ($userrec->id === '103') {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function test_get_waiting() {
        // Fixture.
        $dataset = $this->createCsvDataSet(array(
            user::TABLE => elis::component_file('program', 'tests/fixtures/pmuser.csv'),
            waitlist::TABLE => elis::component_file('program', 'tests/fixtures/waitlist2.csv'),
        ));
        $this->loadDataSet($dataset);

        // Test.
        $student = new student;
        $student->classid = 100;
        $usersonwaitlist = $student->get_waiting();
        $this->assertNotEmpty($usersonwaitlist);

        // Verify.
        $found = false;
        foreach ($usersonwaitlist as $userrec) {
            if ($userrec->id === '103') {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    /**
     * Validate the $student->validate_class_enrolment_limit function.
     */
    public function test_validate_class_enrolment_limit() {
        $this->load_csv_data();
        $student = new student(array('userid' => 104, 'classid' => 101));
        $student->load();
        $student->save();

        try {
            $result = $student->validate_class_enrolment_limit();
            $this->assertTrue($result);
        } catch (Exception $e) {
            // Should not reach here.
            $this->assertFalse(true);
        }

        $class = new pmclass(101);
        $class->load();
        $class->maxstudents = 1;
        $class->save();

        $student = new student(array('userid' => 103, 'classid' => 101));

        try {
            $result = $student->validate_class_enrolment_limit();
        } catch (Exception $e) {
            $this->assertTrue($e instanceof pmclass_enrolment_limit_validation_exception);
        }
    }
}