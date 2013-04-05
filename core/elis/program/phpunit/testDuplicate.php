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
 * @subpackage programmanager
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__) . '/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot . '/elis/program/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once('PHPUnit/Extensions/Database/DataSet/CsvDataSet.php');
require_once(elispm::lib('data/curriculum.class.php'));
require_once(elispm::lib('data/course.class.php'));
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/userset.class.php'));
require_once(elispm::lib('data/track.class.php'));

class duplicateTest extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

    /**
     * Return the list of tables that should be ignored for writes.
     */
    static protected function get_ignored_tables() {
        return array('crlm_coursetemplate' => 'elis_program',
                     'crlm_user_track' => 'elis_program'
               );
    }

    protected static function get_overlay_tables() {
        return array(
            'course' => 'moodle',
            'context' => 'moodle',
            'elis_field_categories' => 'elis_core',
            'elis_field_category_contexts' => 'elis_core',
            'elis_field' => 'elis_core',
            'elis_field_contextlevels' => 'elis_core',
            'elis_field_owner' => 'elis_core',
            'elis_field_data_text' => 'elis_core',
            pmclass::TABLE => 'elis_program',
            course::TABLE => 'elis_program',
            curriculum::TABLE => 'elis_program',
            track::TABLE => 'elis_program',
            trackassignment::TABLE => 'elis_program',
            userset::TABLE => 'elis_program',
            usertrack::TABLE => 'elis_program',
            clustertrack::TABLE => 'elis_program',
            clustercurriculum::TABLE => 'elis_program',
            curriculum::TABLE => 'elis_program',
            curriculumcourse::TABLE => 'elis_program'
        );
    }

    protected function load_csv_data() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(course::TABLE, elis::component_file('program', 'phpunit/duplicatecourse.csv'));
        $dataset->addTable(pmclass::TABLE, elis::component_file('program', 'phpunit/duplicateclass.csv'));
        $dataset->addTable(curriculum::TABLE, elis::component_file('program', 'phpunit/duplicatecurriculum.csv'));
        $dataset->addTable(curriculumcourse::TABLE, elis::component_file('program', 'phpunit/duplicatecurriculum_course.csv'));
        $dataset->addTable(track::TABLE, elis::component_file('program', 'phpunit/duplicatetrack.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    /**
     * Set up the course and context records needed for many of the
     * unit tests
     */
    private function init_contexts_and_site_course() {
        global $DB;

        $prefix = self::$origdb->get_prefix();
        $DB->execute("INSERT INTO {context}
                      SELECT * FROM
                      {$prefix}context
                      WHERE contextlevel = ?", array(CONTEXT_SYSTEM));
        $DB->execute("INSERT INTO {context}
                      SELECT * FROM
                      {$prefix}context
                      WHERE contextlevel = ? and instanceid = ?", array(CONTEXT_COURSE, SITEID));
        //set up the site course record
        if ($record = self::$origdb->get_record('course', array('id' => SITEID))) {
            unset($record->id);
            $DB->insert_record('course', $record);
        }

        build_context_path();
    }

    /**
     * Test generate unique identifier function
     */
    public function testGenerateUniqueIdentifier() {
        global $DB;

        $this->load_csv_data();

        //test without passing an object
        $idnumber = "test - test";
        $newidnumber = generate_unique_identifier(pmclass::TABLE, 'idnumber', $idnumber, array('idnumber' => $idnumber));
        $expectedvalue = "test - test.3";

        //we want to validate that the  unique idnumber is "test - test.3"
        $this->assertEquals($expectedvalue, $newidnumber);

        //test with passing an object
        $idnumber = "test - test";
        $classobj = new stdClass();
        generate_unique_identifier(pmclass::TABLE, 'idnumber', $idnumber, array('idnumber' => $idnumber), 'pmclass', $classobj);

        $expectedvalue = "test - test.3";

        //we want to validate that the  unique idnumber is "test - test.3"
        $this->assertEquals($expectedvalue, $classobj->idnumber);

        //test that we also get a unique identifier with multiple values in the params array
        $idnumber = "test - test";
        $newidnumber = generate_unique_identifier(pmclass::TABLE, 'idnumber', $idnumber, array('courseid'=>'1',
                                                                                               'idnumber' => $idnumber));

        $expectedvalue = "test - test.2";

        //we want to validate that the  unique idnumber is "test - test.2"
        $this->assertEquals($expectedvalue, $newidnumber);

        //test with passing an object and object parameters
        $idnumber = "test - test";
        $courseid = 1;
        $classobj = new stdClass();

        generate_unique_identifier(pmclass::TABLE, 'idnumber', $idnumber, array('courseid'=> $courseid, 'idnumber' => $idnumber),
                                  'pmclass', $classobj, array('idnumber' => $idnumber));

        $expectedvalue = "test - test.2";

        //we want to validate that the  unique idnumber is "test - test.3"
        $this->assertEquals($expectedvalue, $classobj->idnumber);
    }

    /**
     * Test validation of duplicate pm classes
     */
    public function testClassValidationPreventsDuplicates() {
        global $DB;

        $this->load_csv_data();

        $class = new pmclass(array('courseid' => 1,
                                   'idnumber' => 'test'));

        $userset = new stdClass();
        $userset->name = 'test';
        $options = array();
        $options['targetcluster'] = $userset;
        $options['tracks'] = 1;
        $options['classes'] = 1;
        $options['moodlecourses'] = 'copyalways';
        $options['classmap'] = array();

        $return = $class->duplicate($options);
        //make sure that a we get a class returned
        $this->assertTrue(is_array($return['classes']));

        $id = $return['classes'][''];

        $record = $DB->get_record('crlm_class', array('id'=>$id));
        $expectedvalue = "test - test.3";

       //we want to validate that the  unique idnumber is "test - test_3"
       $this->assertEquals($expectedvalue, $record->idnumber);
    }

    /**
     * Test validation of duplicate programs
     */
    public function testProgramValidationPreventsDuplicates() {
        global $DB;

        $this->load_csv_data();

        //set up context records
        $this->init_contexts_and_site_course();

        //need program and userset
        $userset = new stdClass();
        $userset->id = 1;
        $userset->name = 'test';

        $program = new curriculum(array('idnumber' => 'test', 'name' => 'test'));
        $options = array();
        $options['targetcluster'] = $userset;
        $options['moodlecourses'] = 'copyalways';
        $options['classmap'] = array();

        $return = $program->duplicate($options);
        //make sure that a we get a program returned
        $this->assertTrue(is_array($return['curricula']));

        $id = $return['curricula'][''];
        $record = $DB->get_record('crlm_curriculum', array('id'=>$id));
        $expectedvalue = "test - test.3";

       //we want to validate that the  unique idnumber is "test - test.3"
       $this->assertEquals($expectedvalue, $record->idnumber);
       //the name is also to be unique
       $this->assertEquals($expectedvalue, $record->name);
    }

    /**
     * Test validation of duplicate course descriptions
     */
    public function testCourseDescriptionValidationPreventsDuplicates() {
        global $DB;

        $this->load_csv_data();

        //need course and userset
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

        //make sure that a we get a program returned
        $this->assertTrue(is_array($return['courses']));

        $id = $return['courses'][''];
        $record = $DB->get_record('crlm_course', array('id'=>$id));
        $expectedvalue = "test - test.3";

       //we want to validate that the  unique idnumber is "test - test.3"
       $this->assertEquals($expectedvalue, $record->idnumber);
       //the name is also to be unique
       $this->assertEquals($expectedvalue, $record->name);
    }

    /**
     * Test validation of duplicate tracks
     */
    public function testTrackValidationPreventsDuplicates() {
        global $DB;

        $this->load_csv_data();

        //need track and userset
        $userset = new stdClass();
        $userset->id = 1;
        $userset->name = 'test';

        $track = new track(array('curid' => 5, 'name' => 'test', 'idnumber' => 'test'));
        $options = array();
        $options['targetcluster'] = $userset;
        $options['targetcurriculum'] = 5;
        $options['moodlecourses'] = 'copyalways';
        $options['tracks'] = 1;
        $options['classmap'] = array();

        $return = $track->duplicate($options);

        //make sure that a we get a program returned
        $this->assertTrue(is_array($return['tracks']));

        $id = $return['tracks'][''];
        $record = $DB->get_record('crlm_track', array('id'=>$id));
        $expectedvalue = "test - test.3";

        //we want to validate that the  unique idnumber is "test - test.3"
        $this->assertEquals($expectedvalue, $record->idnumber);
        //the name is also to be unique
        $this->assertEquals($expectedvalue, $record->name);
    }

    /**
     * Test validation of duplicate auto created tracks
     */
    public function testTrackAutoCreateValidationPreventsDuplicates() {
        global $DB;

        $this->load_csv_data();

        //need track and userset
        $userset = new stdClass();
        $userset->id = 1;
        $userset->name = 'test';

        //set values required for auto create
        $track = new track(array('id' => 1, 'curid' => 1, 'name' => 'test', 'idnumber' => 'test'));
        //testing track auto create
        $track->track_auto_create();

        //nothing is returned, so get most recent class created
        $records = $DB->get_records('crlm_class', null, "id DESC");
        foreach($records as $record) {
            $return = $record;
            break;
        }
        $expectedvalue = "test-test.2";

       //we want to validate that the  unique idnumber is "test-test.2"
       $this->assertEquals($expectedvalue, $return->idnumber);
    }

    /**
     * Test to ensure that the auto-generated class ID number values do not overflow the maximum length of the
     * crlm_class.idnumber field
     */
    public function testTrackAutoCreateValidationDoesNotOverflowIdNumberField() {
        global $DB;

        $this->load_csv_data();

        //need track and userset
        $userset = new stdClass();
        $userset->id = 1;
        $userset->name = 'test';

        //set values required for auto create
        $track = new track(5);
        $track->load();

        //testing track auto create
        $track->track_auto_create();

        //nothing is returned, so get most recent class created
        $records = $DB->get_records('crlm_class', null, "id DESC");
        foreach($records as $record) {
            $return = $record;
            break;
        }
        $expectedvalue = substr('test-'.$track->idnumber, 0, 95);

       //we want to validate that the  unique idnumber is "test-test.2"
       $this->assertEquals($expectedvalue, $return->idnumber);
    }

    /**
     * Test to ensure that the auto-generated class ID number values do not overflow the maximum length of the
     * crlm_class.idnumber field when multiple copies of the same class are created which require an incrementing iterator
     * to be appended to the idnumber value are used.
     */
    public function testTrackAutoCreateValidationDoesNotOverflowIdNumberFieldWithIterators() {
        global $DB;

        $this->load_csv_data();

        //need track and userset
        $userset = new stdClass();
        $userset->id = 1;
        $userset->name = 'test';

        //set values required for auto create
        $track = new track(5);
        $track->load();

        //testing track auto create
        $track->track_auto_create();

        // Force duplicate classes to be created which should have a unique iterator added to the idnumber field and
        // still be within the allowable field size
        $track->track_auto_create();
        $track->track_auto_create();

        // Get most recent class records created
        $records = $DB->get_records('crlm_class', array(), "id DESC", 'id, idnumber', 0, 3);

        // We want to test in the order they were created
        $records = array_reverse($records);

        $expectedvalue = substr('test-'.$track->idnumber, 0, 95);
        $iterator      = 0;

        foreach($records as $record) {
            $this->assertEquals($expectedvalue.($iterator > 0 ? '.'.$iterator : ''), $record->idnumber);
            $iterator++;
        }
    }
}
