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
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));

/**
 * Class for testing the pmclass::check_for_moodle_courses method, which cleans up orphaned class-moodle course associations
 * @group elis_program
 */
class pmclasscheckformoodlecourses_testcase extends elis_database_test {

    /**
     * Load initial data from CSVs.
     */
    protected function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            'course' => elis::component_file('program', 'tests/fixtures/mdlcourses.csv'),
            course::TABLE => elis::component_file('program', 'tests/fixtures/pmcrs.csv'),
            pmclass::TABLE => elis::component_file('program', 'tests/fixtures/pmclass2.csv'),
            student::TABLE => elis::component_file('program', 'tests/fixtures/student.csv'),
            user::TABLE => elis::component_file('program', 'tests/fixtures/pmuser.csv'),
            usermoodle::TABLE => elis::component_file('program', 'tests/fixtures/usermoodle.csv'),
            'user' => elis::component_file('program', 'tests/fixtures/mdluser.csv'),
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * Data provider for this test class
     * @return array A list of associations, as well as information related to whether they should be cleaned up by the method we
     *               are testing
     */
    public function dataprovider_checkformoodlecourses() {
        $associations = array(
                array(
                    'classid' => 100,
                    'moodlecourseid' => 100,
                    'expect_deleted' => false
                ),
                array(
                    'classid' => 101,
                    'moodlecourseid' => 101,
                    'expect_deleted' => false
                ),
                array(
                    'classid' => 102,
                    'moodlecourseid' => 102,
                    'expect_deleted' => false
                ),
                array(
                    'classid' => 103,
                    'moodlecourseid' => 103,
                    'expect_deleted' => true
                ),
                array(
                    'classid' => 104,
                    'moodlecourseid' => 104,
                    'expect_deleted' => true
                ),
        );
        return array(array($associations));
    }

    /**
     * Validate that the check_for_moodle_courses method deletes the correct set of orphaned associations exactly.
     * @param array $associations The list of associations and information regarding whether they should be cleaned up or not.
     * @dataProvider dataprovider_checkformoodlecourses
     */
    public function test_checkformoodlecoursesdeletesorphanedrecords($associations) {
        global $DB;

        // Set up our classes.
        $this->load_csv_data();

        // Track which associations should remain.
        $remainingassociations = array();

        foreach ($associations as $association) {
            // Persist the record.
            $record = new classmoodlecourse($association);
            $record->save();

            if (!$association['expect_deleted']) {
                // It should persist after the method is called.
                $remainingassociations[] = $association;
            }
        }

        // Delete orphaned records.
        pmclass::check_for_moodle_courses();

        // Validate count.
        $this->assertEquals(count($remainingassociations), $DB->count_records(classmoodlecourse::TABLE));

        // Validate records specifically.
        foreach ($remainingassociations as $remainingassociation) {
            $params = array(
                'classid' => $remainingassociation['classid'],
                'moodlecourseid' => $remainingassociation['moodlecourseid']
            );
            $exists = $DB->record_exists(classmoodlecourse::TABLE, $params);
            $this->assertTrue($exists);
        }
    }

    /**
     * Validate that the check_for_moodle_courses method deletes the correct set of orphaned associations for a specific user.
     * @param array $associations The list of associations and information regarding whether they should be cleaned up or not.
     * @dataProvider dataprovider_checkformoodlecourses
     */
    public function test_checkformoodlecoursesrespectsuseridparameter($associations) {
        global $DB;

        // Set up our classes.
        $this->load_csv_data();

        $student = new student(array('userid' => 103, 'classid' => 103));

        $sink = $this->redirectMessages();
        $student->save();

        // Track which associations should remain.
        $remainingassociations = array();

        foreach ($associations as $association) {
            // Persist the record.
            $record = new classmoodlecourse($association);
            $record->save();

            // Test user is enrolled in class 103, so this one should be deleted.
            if ($association['classid'] != 103) {
                // It should persist after the method is called.
                $remainingassociations[] = $association;
            }
        }

        // Delete orphaned records.
        pmclass::check_for_moodle_courses(103);

        // Validate count.
        $this->assertEquals(count($remainingassociations), $DB->count_records(classmoodlecourse::TABLE));

        // Validate records specifically.
        foreach ($remainingassociations as $remainingassociation) {
            $params = array(
                'classid' => $remainingassociation['classid'],
                'moodlecourseid' => $remainingassociation['moodlecourseid']
            );
            $exists = $DB->record_exists(classmoodlecourse::TABLE, $params);
            $this->assertTrue($exists);
        }
    }
}