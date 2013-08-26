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
require_once(elispm::lib('data/coursetemplate.class.php'));
require_once(elispm::lib('data/curriculum.class.php'));
require_once(elispm::lib('data/track.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));
require_once(elispm::lib('data/usertrack.class.php'));
require_once(elispm::file('tests/other/datagenerator.php'));

/**
 * Test track assignment
 * Since class is defined within track.class.php testDataObjectsFieldsAndAssociations.php will not auto test this class
 * @group elis_program
 */
class trackassignment_testcase extends elis_database_test {

    /**
     * Load initial data from CSVs.
     */
    protected function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            trackassignment::TABLE => elis::component_file('program', 'tests/fixtures/trackassignment.csv'),
            track::TABLE => elis::component_file('program', 'tests/fixtures/track.csv'),
            curriculumcourse::TABLE => elis::component_file('program', 'tests/fixtures/curriculum_course.csv'),
        ));
        $this->loadDataSet($dataset);

    }

    /**
     * Test that data class has correct DB fields
     */
    public function test_trackassignmenthascorrectdbfields() {
        $testobj = new trackassignment(false, null, array(), false, array());
        $this->assertTrue($testobj->_test_dbfields(), 'Error(s) with class $_dbfield_ properties.');
    }

    /**
     * Test that data class has correct associations
     */
    public function test_trackassignmenthascorrectassociations() {
        $testobj = new trackassignment(false, null, array(), false, array());
        $this->assertTrue($testobj->_test_associations(), 'Error(s) with class associations.');
    }

    /**
     * Test that a record can be created in the database.
     */
    public function test_trackassignmentcancreaterecord() {
        $this->load_csv_data();
        $timenow = time();

        // Create a record.
        $src = new trackassignment(false, null, array(), false, array());
        $src->trackid = 1;
        $src->classid = 1;
        $src->courseid = 1;
        $src->autoenrol = true;
        $src->timecreated = $timenow;
        $src->timemodified = $timenow;

        $src->save();

        // Read it back.
        $retr = new trackassignment($src->id, null, array(), false, array());
        foreach ($src as $key => $value) {
            if (strpos($key, elis_data_object::FIELD_PREFIX) !== false) {
                $key = substr($key, strlen(elis_data_object::FIELD_PREFIX));
                $this->assertEquals($src->{$key}, $retr->{$key});
            }
        }
    }

    /**
     * Test that a record can be modified.
     */
    public function test_trackassignmentcanupdaterecord() {
        $this->load_csv_data();
        $timenow = time();

        // Read a record.
        $src = new trackassignment(3, null, array(), false, array());
        $src->trackid = 1;
        $src->classid = 103;
        $src->courseid = 1;
        $src->autoenrol = true;
        $src->timemodified = $timenow;
        $src->save();

        // Read it back.
        $retr = new trackassignment(3, null, array(), false, array());
        foreach ($src as $key => $value) {
            if (strpos($key, elis_data_object::FIELD_PREFIX) !== false) {
                $key = substr($key, strlen(elis_data_object::FIELD_PREFIX));
                $this->assertEquals($src->{$key}, $retr->{$key});
            }
        }
    }

    /**
     * Test validation of duplicates
     *
     * Note: no exception thrown from trackassignment.class.php for dup.
     */
    public function test_trackassignmentvalidationpreventsduplicates() {
        global $DB;
        $this->load_csv_data();

        $trackassignment = new trackassignment(array('trackid' => 1, 'classid' => 100, 'courseid' => 99999999));

        $trackassignment->save();
        $trackassignments = $DB->get_records(trackassignment::TABLE, array('trackid' => 1, 'classid' => 100));
        $this->assertEquals(count($trackassignments), 1);
    }

    /**
     * Test get_assigned_tracks function
     */
    public function test_get_assigned_tracks() {
        // Fixture.
        $dataset = $this->createCsvDataset(array(
            trackassignment::TABLE => elis::component_file('program', 'tests/fixtures/trackassignment.csv')
        ));
        $this->loadDataSet($dataset);

        // Test.
        $trackassignment = new trackassignment;
        $trackassignment->classid = 100;
        $assignedtracks = $trackassignment->get_assigned_tracks();

        // Verify we got a non-empty array.
        $this->assertNotEmpty($assignedtracks);
        $this->assertInternalType('array', $assignedtracks);

        // Verify contents.
        $count = 0;
        foreach ($assignedtracks as $trackid => $trackassignmentid) {
            $this->assertEquals(1, $trackid);
            $this->assertEquals(1, $trackassignmentid);
            $count++;
        }
        $this->assertEquals(1, $count);
    }

    /**
     * Test enrol_all_track_users_in_class function.
     */
    public function test_enrol_all_track_users_in_class() {
        // Fixture.
        $dataset = $this->createCsvDataset(array(
            course::TABLE => elis::component_file('program', 'tests/fixtures/pmcourse.csv'),
            pmclass::TABLE => elis::component_file('program', 'tests/fixtures/pmclass.csv'),
            trackassignment::TABLE => elis::component_file('program', 'tests/fixtures/trackassignment.csv'),
            usertrack::TABLE => elis::component_file('program', 'tests/fixtures/user_track.csv'),
            user::TABLE => elis::component_file('program', 'tests/fixtures/user2.csv'),
        ));
        $this->loadDataSet($dataset);

        // Test.
        $trackassignment = new trackassignment;
        $trackassignment->classid = 100;
        $trackassignment->trackid = 1;
        ob_start();
        $trackassignment->enrol_all_track_users_in_class();
        $output = ob_get_contents();
        ob_end_clean();

        $this->assertEquals(get_string('n_users_enrolled', 'elis_program', 1), $output);
    }
}
