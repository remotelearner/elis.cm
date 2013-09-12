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

// Libs.
require_once(elispm::lib('data/student.class.php'));
require_once(elispm::lib('data/curriculumstudent.class.php'));
require_once(elispm::lib('data/certificatesettings.class.php'));
require_once(elispm::lib('data/certificateissued.class.php'));
require_once(elispm::lib('data/instructor.class.php'));
require_once(elispm::lib('data/course.class.php'));
require_once(elispm::lib('certificate.php'));
require_once(elispm::file('tests/other/datagenerator.php'));

/**
 * PHPUnit test to retrieve a user's certificates
 * @group elis_program
 */
class certificate_get_entity_metadata_testcase extends elis_database_test {

    /**
     * Load PHPUnit test data.
     */
    protected function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            student::TABLE => elis::component_file('program', 'tests/fixtures/class_enrolment.csv'),
            pmclass::TABLE => elis::component_file('program', 'tests/fixtures/class.csv'),
            user::TABLE => elis::component_file('program', 'tests/fixtures/pmuser.csv'),
            certificatesettings::TABLE => elis::component_file('program', 'tests/fixtures/certificate_settings.csv'),
            certificateissued::TABLE => elis::component_file('program', 'tests/fixtures/certificate_issued.csv'),
            instructor::TABLE => elis::component_file('program', 'tests/fixtures/instructor.csv'),
            course::TABLE => elis::component_file('program', 'tests/fixtures/pmcourse.csv'),
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * This function will setup a fake certificatesettings object
     * @return object An stdClass object mimicking the properties of a certificatesettings object
     */
    protected function setup_dummy_certsettings_object() {
        $certsetting = new stdClass();
        $certsetting->id = 1;
        $certsetting->entity_id = 1;
        $certsetting->entity_course = 'fake course';
        $certsetting->cert_border = 'fake border';
        $certsetting->cert_seal = 'fake seal';
        $certsetting->cert_template = 'fake template';
        $certsetting->timecreated = 0;
        $certsetting->timemodified = 0;
        return $certsetting;
    }

    /**
     * This function will setup a fake certificateissued object
     * @return object An stdClass object mimicking the properties of a certificateissued object
     */
    protected function setup_dummy_certissued_object() {
        $certissued = new stdClass();
        $certissued->id = 1;
        $certissued->cm_userid = 1;
        $certissued->cert_setting_id = 1;
        $certissued->cert_code = 'fake code';
        $certissued->timeissued = 0;
        $certissued->timecreated = 0;
        return $certissued;
    }

    /**
     * This function will setup a fake user object
     * @return object An stdClass object mimicking some properties of a user object
     */
    protected function setup_dummy_user_object() {
        $user = new stdClass();
        $user->id = 1;
        $user->firstname = 'fake first name';
        $user->lastname = 'fake last name';
        $user->idnumber = 'fake idnumber';

        return $user;
    }

    /**
     * Data provider using invalid arguments
     * @return array An array of objects
     */
    public function incorrect_object_types_provider() {
        return array(
                array(false, false, false),
                array(1, 1, 1),
                array('one', 'two', 'three'),
                array(new stdClass(), new stdClass(), new stdClass()),
                array(new certificatesettings(), new stdClass(), new stdClass()),
                array(new certificatesettings(), new certificateissued(), new stdClass()),
                array(new certificatesettings(), new stdClass(), new user()),
                array(new stdClass(), new certificateissued(), new stdClass()),
                array(new stdClass(), new certificateissued(), new user()),
                array(new certificatesettings(), new certificateissued(), new stdClass()),
                array(new certificatesettings(), new stdClass(), new user()),
                array(new stdClass(), new certificateissued(), new user()),
        );
    }

    /**
     * Test retrieving metadata passing incorrect class instances
     * @param bool|int|string|object $certsetting Incorrect objects
     * @param bool|int|string|object $certissued Incorrect objects
     * @param bool|int|string|object $student Incorrect objects
     * @dataProvider incorrect_object_types_provider
     */
    public function test_retireve_metadata_for_entity_incorrect_objects($certsetting, $certissued, $student) {
        $result = certificate_get_entity_metadata($certsetting, $certissued, $student);

        $this->assertEquals(false, $result);
    }

    /**
     * Test retrieving metadata passing incorrect entity type name
     */
    public function test_retireve_metadata_for_entity_wrong_entity_type() {
        $this->load_csv_data();

        $student     = new user();
        $certissued  = new certificateissued();
        $certsetting = new certificatesettings(1);
        $certsetting->load();
        $certsetting->entity_type = '';
        $result = certificate_get_entity_metadata($certsetting, $certissued, $student);

        $this->assertEquals(false, $result);
    }

    /**
     * Test retrieving metadata passing missing entity type property
     */
    public function test_retireve_metadata_for_entity_missing_entity_type() {
        $this->load_csv_data();

        $student     = new user();
        $certissued  = new certificateissued();
        $certsetting = new certificatesettings(1);
        $certsetting->load();
        unset($certsetting->entity_type);

        $result = certificate_get_entity_metadata($certsetting, $certissued, $student);

        $this->assertEquals(false, $result);
    }

    /**
     * Test retrieving metadata passing correct entity type name
     */
    public function test_retireve_metadata_for_entity_correct_entity_type() {
        $this->load_csv_data();

        $student     = new user(104);
        $certsetting = new certificatesettings(6);
        $certissued  = new certificateissued(9);
        $certissued->load();
        $student->load();
        $certsetting->load();

        $result = certificate_get_entity_metadata($certsetting, $certissued, $student);

        $this->assertNotEquals(false, $result);
    }

    /**
     * Test retrieving course metadata
     */
    public function test_retrieve_metadata_for_course_entity() {
        $this->load_csv_data();

        $student     = new user(104);
        $certsetting = new certificatesettings(6);
        $certissued  = new certificateissued(9);
        $certissued->load();
        $student->load();
        $certsetting->load();

        $result = certificate_get_course_entity_metadata($certsetting, $certissued, $student);

        $expected = array(
            'student_name' => 'User Test2',
            'course_name' => 'Test Course',
            'class_idnumber' => 'Test_Class_Instance_1',
            'class_enrol_time' => 1358315400,
            'class_startdate' => 0,
            'class_enddate' => 0,
            'class_grade' => '10.00000',
            'cert_timeissued' => 1358363100,
            'cert_code' => '339Fjap8j6oPKnw',
            'class_instructor_name' => 'User Test1'
        );

        $this->assertEquals($expected, $result);
    }

    /**
     * Data provider using invalid arguments
     * @return array An array of objects
     */
    public function incorrect_object_properties_provider() {
        $certsetting = $this->setup_dummy_certsettings_object();
        $certissued  = $this->setup_dummy_certissued_object();
        $student     = $this->setup_dummy_user_object();

        $data = array();

        // Incorrect student id.
        $student->id = 999;
        $certsetting->entity_id = 100;
        $certissued->timeissued = 1358363100;
        $data[] = array(clone($certsetting), clone($certissued), clone($student));

        // Incorrect certsetting entity id.
        $student->id = 104;
        $certsetting->entity_id = 999;
        $certissued->timeissued = 1358363100;
        $data[] = array(clone($certsetting), clone($certissued), clone($student));

        // Incorrect certissued timeissued.
        $student->id = 104;
        $certsetting->entity_id = 100;
        $certissued->timeissued = 999;
        $data[] = array(clone($certsetting), clone($certissued), clone($student));

        // Missing student id property.
        unset($student->id);
        $certsetting->entity_id = 100;
        $certissued->timeissued = 1358363100;
        $data[] = array(clone($certsetting), clone($certissued), clone($student));

        // Missing certsetting entity_id property.
        $student->id = 104;
        unset($certsetting->entity_id);
        $certissued->timeissued = 1358363100;
        $data[] = array(clone($certsetting), clone($certissued), clone($student));

        // Missing certsetting entity_id property.
        $student->id = 104;
        $certsetting->entity_id = 100;
        unset($certissued->timeissued);
        $data[] = array(clone($certsetting), clone($certissued), clone($student));

        return $data;
    }

    /**
     * Test retrieving course metadata with incorrect object properties
     * @param object $certsetting Certificate settings mock object
     * @param object $certissued Certificate issued mock object
     * @param object $student User mock object
     * @dataProvider incorrect_object_properties_provider
     */
    public function test_retrieve_metadata_for_course_entity_incorrect_object_properties($certsetting, $certissued, $student) {
        $this->load_csv_data();

        $result = certificate_get_course_entity_metadata($certsetting, $certissued, $student);
        phpunit_util::reset_debugging();
        $this->assertEquals(false, $result);
    }
}