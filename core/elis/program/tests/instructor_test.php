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

// Data objects.
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/instructor.class.php'));
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/course.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));

require_once(elispm::file('tests/other/datagenerator.php'));


/**
 * Test instructor functions.
 * @group elis_program
 */
class instructor_testcase extends elis_database_test {

    /**
     * Load initial data from CSVs.
     */
    protected function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            'user' => elis::component_file('program', 'tests/fixtures/mdluser.csv'),
            'course' => elis::component_file('program', 'tests/fixtures/mdlcourse.csv'),
            usermoodle::TABLE => elis::component_file('program', 'tests/fixtures/user_moodle.csv'),
            classmoodlecourse::TABLE => elis::component_file('program', 'tests/fixtures/class_moodle_course.csv'),
            course::TABLE => elis::component_file('program', 'tests/fixtures/pmcourse.csv'),
            pmclass::TABLE => elis::component_file('program', 'tests/fixtures/pmclass.csv'),
            user::TABLE => elis::component_file('program', 'tests/fixtures/pmuser.csv'),
            instructor::TABLE => elis::component_file('program', 'tests/fixtures/instructor.csv'),
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * Set the instructor role in the ELIS config
     */
    protected function set_config_instructor_role() {
        global $DB;
        $role = $DB->get_record('role', array('shortname' => 'editingteacher'));
        set_config('default_instructor_role', $role->id, 'elis_program');
    }

    /**
     * Test validation of empty userid.
     * @expectedException data_object_validation_exception
     */
    public function test_instructorvalidation_preventsemptyuserid() {
        $this->load_csv_data();
        $instructor = new instructor(array('classid' => 100));
        $instructor->save();
    }

    /**
     * Test validation of empty classid.
     * @expectedException data_object_validation_exception
     */
    public function test_instructorvalidation_preventsemptyclassid() {
        $this->load_csv_data();
        $instructor = new instructor(array('userid' => 103));
        $instructor->save();
    }

    /**
     * Test validation of invalid userid.
     * @expectedException dml_missing_record_exception
     */
    public function test_instructorvalidation_preventsinvaliduserid() {
        $this->load_csv_data();
        $instructor = new instructor(array('userid' => 1, 'classid' => 100));
        $instructor->save();
    }

    /**
     * Test validation of invalid classid.
     * @expectedException dml_missing_record_exception
     */
    public function test_instructorvalidation_preventsinvalidclassid() {
        $this->load_csv_data();
        $instructor = new instructor(array('userid' => 103, 'classid' => 1));
        $instructor->save();
    }

    /**
     * Test validation of duplicates.
     * @expectedException data_object_validation_exception
     */
    public function test_instructor_validation_preventsduplicates() {
        $this->load_csv_data();
        $instructor = new instructor(array('userid' => 103, 'classid' => 100));
        $instructor->save();
    }

    /**
     * Test the insertion of a valid association record.
     */
    public function test_instructor_validation_allowsvalidrecord() {
        $this->load_csv_data();
        $this->set_config_instructor_role();

        $instructor = new instructor(array('userid' => 103, 'classid' => 101));
        $instructor->save();
        $this->assertTrue(true);
    }

    /**
     * Test get_instructors function.
     */
    public function test_get_instructors() {
        global $DB;

        // Fixture.
        $datagenerator = new elis_program_datagenerator($DB);
        $user = $datagenerator->create_user();
        $datagenerator->assign_instructor_to_class($user->id, 1);

        // Test.
        $instructor = new instructor;
        $instructor->classid = 1;
        $instructors = $instructor->get_instructors();

        // Verify.
        $count = 0;
        foreach ($instructors as $instructoruser) {
            $this->assertEquals($user->id, $instructoruser->id);
            $count++;
        }
        $this->assertEquals(1, $count);
    }

    /**
     * Test instructors are enrolled into the course
     */
    public function test_instructors_enrolled() {
        global $DB;

        $this->resetAfterTest(true);
        $this->load_csv_data();
        $this->set_config_instructor_role();

        $instructor = new instructor(array('userid' => 104, 'classid' => 100));
        $instructor->save();

        $context = context_course::instance(100);
        $user = new stdClass();
        $user->id = 101;
        $result = is_enrolled($context, $user, '', true);
        $this->assertTrue($result);

        // Check that the user has a correct role in the course
        $course = new stdClass();
        $course->id = $context->instanceid;

        $plugin = enrol_get_plugin('elis');
        $enrolinstance = $plugin->get_or_create_instance($course);

        $roleid = get_config('elis_program', 'default_instructor_role');

        $params = array(
                'roleid' => $roleid,
                'contextid' => $context->id,
                'userid' => $user->id,
                'component' => 'enrol_elis',
                'itemid' => $enrolinstance->id
        );

        $record = $DB->get_records('role_assignments', $params);

        $this->assertNotEmpty($record);
    }

    /**
     * Test instructors are unenrolled from the course
     */
    public function test_instructors_unenrolled() {
        global $DB;

        $this->resetAfterTest(true);
        $this->load_csv_data();
        $this->set_config_instructor_role();

        $instructor = new instructor(array('userid' => 104, 'classid' => 100));
        $instructor->save();
        $instructor->delete();

        $context = context_course::instance(100);
        $user = new stdClass();
        $user->id = 101;
        $result = is_enrolled($context, $user, '', true);
        $this->assertFalse($result);

        // Check that the user has a correct role in the course
        $course = new stdClass();
        $course->id = $context->instanceid;

        $plugin = enrol_get_plugin('elis');
        $enrolinstance = $plugin->get_or_create_instance($course);

        $roleid = get_config('elis_program', 'default_instructor_role');

        $params = array(
                'roleid' => $roleid,
                'contextid' => $context->id,
                'userid' => $user->id,
                'component' => 'enrol_elis',
                'itemid' => $enrolinstance->id
        );

        $record = $DB->get_records('role_assignments', $params);

        $this->assertEmpty($record);
    }

    /**
     * Test ignoring user who does not have any one of the course contact roles in the Moodle course.
     * In this test the editing teacher is a course contact role and the non-editing teacher is not a course contact role.
     */
    public function test_ignore_user_with_no_coursecontact_role() {
        global $DB;

        $this->resetAfterTest(true);
        $this->load_csv_data();
        $this->set_config_instructor_role();

        // Enroll and assign user teacher role in course
        $instructor = new instructor(array('userid' => 104, 'classid' => 100));
        $instructor->save();

        // Get the configured teache role id
        $teacherroleid = get_config('elis_program', 'default_instructor_role');

        // Define user and course objects
        $user = new stdClass();
        $user->id = 101;

        $context = context_course::instance(100);
        $course = new stdClass();
        $course->id = $context->instanceid;

        // Manually assign another non-editing teacher role to prevent the user from being automatically unenrolled due to having no role in a course
        $role = $DB->get_record('role', array('shortname' => 'teacher'));
        role_assign($role->id, $user->id, $context->id);

        // Create an instance of the elis enrolment
        $plugin = enrol_get_plugin('elis');
        $enrolinstance = $plugin->get_or_create_instance($course);

        // Unassign the teacher role from the user
        role_unassign($teacherroleid, $user->id, $context->id, 'enrol_elis', $enrolinstance->id);

        // Initiate the delete method
        $instructor->delete();

        $result = is_enrolled($context, $user, '', true);

        $this->assertTrue($result);
    }

    /**
     * Test removing a user having one of the course contact roles.
     */
    public function test_unenrol_user_with_coursecontact_role() {
        global $DB, $CFG;

        $this->resetAfterTest(true);
        $this->load_csv_data();
        $this->set_config_instructor_role();

        // Enroll and assign user teacher role in course
        $instructor = new instructor(array('userid' => 104, 'classid' => 100));
        $instructor->save();

        // Define user and course objects
        $user = new stdClass();
        $user->id = 101;

        $context = context_course::instance(100);

        // Manually assign another non-editing teacher role to prevent the user from being automatically unenrolled due to having no role in a course
        $role = $DB->get_record('role', array('shortname' => 'teacher'));
        role_assign($role->id, $user->id, $context->id);

        // Add role as a course contact
        $CFG->coursecontact .= ','.$role->id;

        // Create an instance of the elis enrolment
        $course = new stdClass();
        $course->id = $context->instanceid;
        $plugin = enrol_get_plugin('elis');
        $enrolinstance = $plugin->get_or_create_instance($course);

        // Get the configured teache role id
        $teacherroleid = get_config('elis_program', 'default_instructor_role');

        // Unassign the teacher role from the user
        role_unassign($teacherroleid, $user->id, $context->id, 'enrol_elis', $enrolinstance->id);

        // Initiate the delete method
        $instructor->delete();

        $result = is_enrolled($context, $user, '', true);

        $this->assertFalse($result);
    }

    /**
     * Test ignoring user who was manually enrolled and has the instructor role assigned.
     */
    public function test_ignore_instructor_user_not_enrolled_by_elis() {
        global $DB;

        $this->resetAfterTest(true);
        $this->load_csv_data();
        $this->set_config_instructor_role();
        $enrolinstance = null;

        // Define user and course objects
        $user = new stdClass();
        $user->id = 101;

        $context = context_course::instance(100);
        $course = new stdClass();
        $course->id = $context->instanceid;

        // Enrol user manually and give them the instructor role
        $role = $DB->get_record('role', array('shortname' => 'teacher'));
        $plugin = enrol_get_plugin('manual');
        $plugin->add_instance($course);

        $this->getDataGenerator()->enrol_user($user->id, $course->id, $role->id, 'manual');

        // Call instructor delete method
        $instructor = new instructor(array('userid' => 104, 'classid' => 100));
        $instructor->delete();

        $result = is_enrolled($context, $user, '', true);

        $this->assertTrue($result);
    }
}