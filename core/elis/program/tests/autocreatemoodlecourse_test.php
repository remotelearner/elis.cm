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
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/classmoodlecourse.class.php'));

/**
 * Test auto-creating moodle courses.
 * @group elis_program
 */
class autocreatemoodlecourse_testcase extends elis_database_test {

    /**
     * This method is called before the first test of this test class is run.
     */
    public function setUp() {
        global $DB, $USER;
        parent::setUp();

        // Create data we need for many test cases.
        set_config('defaultenrol', 1, 'enrol_guest');
        set_config('status', ENROL_INSTANCE_DISABLED, 'enrol_guest');
        set_config('enrol_plugins_enabled', 'manual,guest');

        // Load initial data from CSVs.
        $dataset = $this->createCsvDataSet(array(
            'course' => elis::component_file('program', 'tests/fixtures/autocreatemoodlecourse_course.csv'),
            pmclass::TABLE => elis::component_file('program', 'tests/fixtures/autocreatemoodlecourse_class.csv'),
            course::TABLE => elis::component_file('program', 'tests/fixtures/autocreatemoodlecourse_coursedescription.csv'),
            coursetemplate::TABLE => elis::component_file('program', 'tests/fixtures/autocreatemoodlecourse_coursetemplate.csv'),
        ));
        $this->loadDataSet($dataset);

        // Set current user as admin.
        $this->setAdminUser();
    }

    /**
     * Test validation that class duplicate with autocreate creates and links to a moodle course
     */
    public function test_autocreatemoodlecourse_createsandlinksmoodlecourse() {
        global $DB;

        $class = new pmclass(1);

        $classmoodle = new classmoodlecourse(array('moodlecourseid' => 2, 'classid' => 1));
        $classmoodle->save();

        $userset = new stdClass();
        $userset->name = 'test';
        $options = array();
        $options['targetcluster'] = $userset;
        $options['classes'] = 1;
        $options['moodlecourses'] = 'copyalways';
        $options['classmap'] = array();

        $return = $class->duplicate($options);

        // Make sure that a we get a class returned.
        $this->assertTrue(is_array($return['classes']));

        // Get the new returned id.
        $id = $return['classes'][1];

        $recordexists = $DB->record_exists('crlm_class_moodle', array('classid' => $id));

        // We want to validate that a link to the new moodle course was created.
        $this->assertTrue($recordexists);

        // Get the new course id.
        $record = $DB->get_record('crlm_class_moodle', array('classid' => $id));
        $courseexists = $DB->record_exists('course', array('id' => $record->moodlecourseid));

        // We want to validate that new moodle course was created.
        $this->assertTrue($recordexists);
    }

    /**
     * Test validation that moodle_attach_class will attach a Moodle course if autocreate is true
     */
    public function test_autocreatemoodlecourse_attachesmoodlecourse() {
        global $DB;

        $clsid = 1;
        $mdlid = 2;
        $autocreate = true;

        $result = moodle_attach_class($clsid, $mdlid, '', false, false, $autocreate);

        // Make sure that moodle_attach_class returns true.
        $this->assertTrue($result);

        $recordexists = $DB->record_exists('crlm_class_moodle', array('classid' => $clsid));

        // We want to validate that a link to the new moodle course was created.
        $this->assertTrue($recordexists);

        // Get the new course id.
        $record = $DB->get_record('crlm_class_moodle', array('classid' => $clsid));
        $courseexists = $DB->record_exists('course', array('id' => $record->moodlecourseid));

        // We want to validate that new moodle course was created.
        $this->assertTrue($recordexists);
    }
}