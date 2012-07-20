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
require_once(dirname(__FILE__).'/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once(elispm::lib('data/pmclass.class.php'));

/**
 * Class for testing the pmclass::check_for_moodle_courses method, which
 * cleans up orphaned class-moodle course associations
 */
class pmclassCheckForMoodleCoursesTest extends elis_database_test {
    /**
     * Return the list of tables that should be overlayed.
     *
     * @return array The mapping of overlay tables to components
     */
    static protected function get_overlay_tables() {
        return array('course' => 'moodle',
                     classmoodlecourse::TABLE => 'elis_program',
                     course::TABLE            => 'elis_program',
                     pmclass::TABLE           => 'elis_program',
                     student::TABLE           => 'elis_program',
                     user::TABLE              => 'elis_program'
               );
    }

    static protected function get_ignored_tables() {
        return array('context' => 'moodle'
               );
    }

    /**
     * Load CSV data from file
     */
    protected function load_csv_data() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        //need Moodle courses for testing
        $dataset->addTable('course', elis::component_file('program', 'phpunit/mdlcourses.csv'));
        $dataset->addTable(course::TABLE, elis::component_file('program', 'phpunit/pmcrs.csv'));
        $dataset->addTable(pmclass::TABLE, elis::component_file('program', 'phpunit/pmclass2.csv'));
        $dataset->addTable(student::TABLE, elis::component_file('program', 'phpunit/student.csv'));
        $dataset->addTable(user::TABLE, elis::component_file('program', 'phpunit/user.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    /**
     * Data provider for this test class
     *
     * @return array A list of associations, as well as information related to
     *               whether they should be cleaned up by the method we are testing
     */
    function checkForMoodleCoursesProvider() {
        $associations = array();
        $associations[] = array('classid' => 100,
                                'moodlecourseid' => 1,
                                'expect_deleted' => false);
        $associations[] = array('classid' => 101,
                                'moodlecourseid' => 2,
                                'expect_deleted' => false);
        $associations[] = array('classid' => 102,
                                'moodlecourseid' => 3,
                                'expect_deleted' => false);
        $associations[] = array('classid' => 103,
                                'moodlecourseid' => 4,
                                'expect_deleted' => true);
        $associations[] = array('classid' => 104,
                                'moodlecourseid' => 5,
                                'expect_deleted' => true);
        return array(array($associations));
    }

    /**
     * Validate that the check_for_moodle_courses method deletes the
     * correct set of orphaned associations exactly
     *
     * @param array $associations The list of associations, as well as information
     *                            regarding whether they should be cleaned up or not 
     * @dataProvider checkForMoodleCoursesProvider
     */
    public function testCheckForMoodleCoursesDeletesOrphanedRecords($associations) {
        global $DB;

        //set up our classes
        $this->load_csv_data();

        //track which associations should remain
        $remaining_associations = array();

        foreach ($associations as $association) {
            //persist the record
            $record = new classmoodlecourse($association);
            $record->save();

            if (!$association['expect_deleted']) {
                //it should persist after the method is called
                $remaining_associations[] = $association;
            }
        }

        //delete orphaned records
        pmclass::check_for_moodle_courses();

        //validate count
        $this->assertEquals(count($remaining_associations), $DB->count_records(classmoodlecourse::TABLE));

        //validate records specifically
        foreach ($remaining_associations as $remaining_association) {
            $params = array('classid' => $remaining_association['classid'],
                            'moodlecourseid' => $remaining_association['moodlecourseid']);
            $exists = $DB->record_exists(classmoodlecourse::TABLE, $params);
            $this->assertTrue($exists);
        }
    }

    /**
     * Validate that the check_for_moodle_courses method deletes the
     * correct set of orphaned associations exactly for a specific user
     *
     * @param array $associations The list of associations, as well as information
     *                            regarding whether they should be cleaned up or not 
     * @dataProvider checkForMoodleCoursesProvider
     */
    public function testCheckForMoodleCoursesRespectsUseridParameter($associations) {
        global $DB;

        //set up our classes
        $this->load_csv_data();

        $student = new student(array('userid' => 103, 'classid' => 103));
        $student->save();

        //track which associations should remain
        $remaining_associations = array();

        foreach ($associations as $association) {
            //persist the record
            $record = new classmoodlecourse($association);
            $record->save();

            //test user is enrolled in class 103, so this one should be deleted
            if ($association['classid'] != 103) {
                //it should persist after the method is called
                $remaining_associations[] = $association;
            }
        }

        //delete orphaned records
        pmclass::check_for_moodle_courses(103);

        //validate count
        $this->assertEquals(count($remaining_associations), $DB->count_records(classmoodlecourse::TABLE));

        //validate records specifically
        foreach ($remaining_associations as $remaining_association) {
            $params = array('classid' => $remaining_association['classid'],
                            'moodlecourseid' => $remaining_association['moodlecourseid']);
            $exists = $DB->record_exists(classmoodlecourse::TABLE, $params);
            $this->assertTrue($exists);
        }
    }
}
