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
require_once(elispm::lib('data/curriculum.class.php'));
require_once(elispm::lib('data/course.class.php'));
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/userset.class.php'));
require_once(elispm::lib('data/track.class.php'));

/**
 * Test duplicate.
 * @group elis_program
 */
class duplicate_testcase extends elis_database_test {

    /**
     * Load initial data from CSVs.
     */
    protected function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            course::TABLE => elis::component_file('program', 'tests/fixtures/duplicatecourse.csv'),
            pmclass::TABLE => elis::component_file('program', 'tests/fixtures/duplicateclass.csv'),
            curriculum::TABLE => elis::component_file('program', 'tests/fixtures/duplicatecurriculum.csv'),
            curriculumcourse::TABLE => elis::component_file('program', 'tests/fixtures/duplicatecurriculum_course.csv'),
            track::TABLE => elis::component_file('program', 'tests/fixtures/duplicatetrack.csv'),
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * Test generate unique identifier function.
     */
    public function test_generateuniqueidentifier() {
        global $DB;

        $this->load_csv_data();

        // Test without passing an object.
        $idnumber = 'test - test';
        $newidnumber = generate_unique_identifier(pmclass::TABLE, 'idnumber', $idnumber, array('idnumber' => $idnumber));

        // We want to validate that the  unique idnumber is "test - test.3".
        $expectedvalue = 'test - test.3';
        $this->assertEquals($expectedvalue, $newidnumber);

        // Test with passing an object.
        $idnumber = 'test - test';
        $classobj = new stdClass();
        generate_unique_identifier(pmclass::TABLE, 'idnumber', $idnumber, array('idnumber' => $idnumber), 'pmclass', $classobj);

        // We want to validate that the  unique idnumber is "test - test.3".
        $expectedvalue = 'test - test.3';
        $this->assertEquals($expectedvalue, $classobj->idnumber);

        // Test that we also get a unique identifier with multiple values in the params array.
        $idnumber = 'test - test';
        $params = array('courseid' => '1', 'idnumber' => $idnumber);
        $newidnumber = generate_unique_identifier(pmclass::TABLE, 'idnumber', $idnumber, $params);

        // We want to validate that the  unique idnumber is "test - test.2".
        $expectedvalue = 'test - test.2';
        $this->assertEquals($expectedvalue, $newidnumber);

        // Test with passing an object and object parameters.
        $idnumber = 'test - test';
        $courseid = 1;
        $classobj = new stdClass();
        $params = array('courseid'=> $courseid, 'idnumber' => $idnumber);
        $classparams = array('idnumber' => $idnumber);
        generate_unique_identifier(pmclass::TABLE, 'idnumber', $idnumber, $params, 'pmclass', $classobj, $classparams);

        // We want to validate that the  unique idnumber is "test - test.2".
        $expectedvalue = 'test - test.2';
        $this->assertEquals($expectedvalue, $classobj->idnumber);
    }

    /**
     * Test validation of duplicate pm classes.
     */
    public function test_classvalidation_preventsduplicates() {
        global $DB;

        $this->load_csv_data();

        $class = new pmclass(array('courseid' => 1, 'idnumber' => 'test'));

        $userset = new stdClass();
        $userset->name = 'test';
        $options = array();
        $options['targetcluster'] = $userset;
        $options['tracks'] = 1;
        $options['classes'] = 1;
        $options['moodlecourses'] = 'copyalways';
        $options['classmap'] = array();

        $return = $class->duplicate($options);
        // Make sure that a we get a class returned.
        $this->assertTrue(is_array($return['classes']));

        $id = $return['classes'][''];

        $record = $DB->get_record('crlm_class', array('id' => $id));

        // We want to validate that the  unique idnumber is "test - test_3".
        $expectedvalue = 'test - test.3';
        $this->assertEquals($expectedvalue, $record->idnumber);
    }

    /**
     * Test validation of duplicate programs.
     */
    public function test_programvalidation_preventsduplicates() {
        global $DB;

        $this->load_csv_data();

        // Need program and userset.
        $userset = new stdClass();
        $userset->id = 1;
        $userset->name = 'test';

        $program = new curriculum(array('idnumber' => 'test', 'name' => 'test'));
        $options = array();
        $options['targetcluster'] = $userset;
        $options['moodlecourses'] = 'copyalways';
        $options['classmap'] = array();

        $return = $program->duplicate($options);

        // Make sure that a we get a program returned.
        $this->assertTrue(is_array($return['curricula']));

        $id = $return['curricula'][''];
        $record = $DB->get_record('crlm_curriculum', array('id' => $id));

        // We want to validate that the  unique idnumber is "test - test.3".
        $expectedvalue = 'test - test.3';
        $this->assertEquals($expectedvalue, $record->idnumber);
        // The name is also to be unique.
        $this->assertEquals($expectedvalue, $record->name);
    }

    /**
     * Test validation of duplicate course descriptions.
     */
    public function test_coursedescriptionvalidation_preventsduplicates() {
        global $DB;

        $this->load_csv_data();

        // Need course and userset.
        $userset = new stdClass();
        $userset->id = 1;
        $userset->name = 'test';

        $course = new course(array('idnumber' => 'test', 'name' => 'test', 'syllabus' => 1));
        $options = array();
        $options['targetcluster'] = $userset;
        $options['targetcurriculum'] = 5;
        $options['moodlecourses'] = 'copyalways';
        $options['courses'] = 1;

        $return = $course->duplicate($options);

        // Make sure that a we get a program returned.
        $this->assertTrue(is_array($return['courses']));

        $id = $return['courses'][''];
        $record = $DB->get_record('crlm_course', array('id' => $id));

        // We want to validate that the  unique idnumber is "test - test.3".
        $expectedvalue = 'test - test.3';
        $this->assertEquals($expectedvalue, $record->idnumber);
        // The name is also to be unique.
        $this->assertEquals($expectedvalue, $record->name);
    }

    /**
     * Test validation of duplicate tracks.
     */
    public function test_trackvalidation_preventsduplicates() {
        global $DB;

        $this->load_csv_data();

        // Need track and userset.
        $userset = new stdClass();
        $userset->id = 1;
        $userset->name = 'test';

        $track = new track(array('curid' => 4, 'name' => 'test', 'idnumber' => 'test'));
        $options = array();
        $options['targetcluster'] = $userset;
        $options['targetcurriculum'] = 4;
        $options['moodlecourses'] = 'copyalways';
        $options['tracks'] = 1;
        $options['classmap'] = array();

        $return = $track->duplicate($options);

        // Make sure that a we get a program returned.
        $this->assertTrue(is_array($return['tracks']));

        $id = $return['tracks'][''];
        $record = $DB->get_record('crlm_track', array('id'=>$id));

        // We want to validate that the  unique idnumber is "test - test.3".
        $expectedvalue = 'test - test.3';
        $this->assertEquals($expectedvalue, $record->idnumber);
        // The name is also to be unique.
        $this->assertEquals($expectedvalue, $record->name);
    }

    /**
     * Test validation of duplicate auto created tracks.
     */
    public function test_trackautocreatevalidation_preventsduplicates() {
        global $DB;

        $this->load_csv_data();

        // Need track and userset.
        $userset = new stdClass();
        $userset->id = 1;
        $userset->name = 'test';

        // Set values required for auto create.
        $track = new track(array('id' => 1, 'curid' => 1, 'name' => 'test', 'idnumber' => 'test'));
        // Testing track auto create.
        $track->track_auto_create();

        // Nothing is returned, so get most recent class created.
        $records = $DB->get_records('crlm_class', null, "id DESC");
        foreach ($records as $record) {
            $return = $record;
            break;
        }
        $expectedvalue = 'test-test.2';

        // We want to validate that the  unique idnumber is "test-test.2".
        $this->assertEquals($expectedvalue, $return->idnumber);
    }

    /**
     * Test to ensure that the auto-generated class ID number values do not overflow the maximum length of the
     * crlm_class.idnumber field
     */
    public function test_trackautocreatevalidation_doesnotoverflowidnumberfield() {
        global $DB;

        $this->load_csv_data();

        // Need track and userset.
        $userset = new stdClass();
        $userset->id = 1;
        $userset->name = 'test';

        // Set values required for auto create.
        $track = new track(5);
        $track->load();

        // Testing track auto create.
        $track->track_auto_create();

        // Nothing is returned, so get most recent class created.
        $records = $DB->get_records('crlm_class', null, 'id DESC');
        foreach ($records as $record) {
            $return = $record;
            break;
        }

        // We want to validate that the  unique idnumber is "test-test.2".
        $expectedvalue = substr('test-'.$track->idnumber, 0, 95);
        $this->assertEquals($expectedvalue, $return->idnumber);
    }

    /**
     * Test to ensure that the auto-generated class ID number values do not overflow the maximum length of the
     * crlm_class.idnumber field when multiple copies of the same class are created which require an incrementing iterator
     * to be appended to the idnumber value are used.
     */
    public function test_trackautocreatevalidation_doesnotoverflowidnumberfieldwithiterators() {
        global $DB;

        $this->load_csv_data();

        // Need track and userset.
        $userset = new stdClass();
        $userset->id = 1;
        $userset->name = 'test';

        // Set values required for auto create.
        $track = new track(5);
        $track->load();

        // Testing track auto create.
        $track->track_auto_create();

        // Force duplicate classes to be created which should have a unique iterator added to the idnumber field and
        // still be within the allowable field size.
        $track->track_auto_create();
        $track->track_auto_create();

        // Get most recent class records created.
        $records = $DB->get_records('crlm_class', array(), "id DESC", 'id, idnumber', 0, 3);

        // We want to test in the order they were created.
        $records = array_reverse($records);

        $expectedvalue = substr('test-'.$track->idnumber, 0, 95);
        $iterator = 0;

        foreach ($records as $record) {
            $this->assertEquals($expectedvalue.($iterator > 0 ? '.'.$iterator : ''), $record->idnumber);
            $iterator++;
        }
    }
}