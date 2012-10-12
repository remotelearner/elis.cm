<?php
/**
* ELIS(TM): Enterprise Learning Intelligence Suite
* Copyright (C) 2008-2011 Remote-Learner.net Inc (http://www.remote-learner.net )
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program. If not, see <http://www.gnu.org/licenses/ >.
*
* @package    elis
* @subpackage program
* @author     Remote-Learner.net Inc
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
* @copyright (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
*
*/

require_once(dirname(__FILE__) . '/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot . '/elis/program/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once('PHPUnit/Extensions/Database/DataSet/CsvDataSet.php');
require_once(elispm::lib('data/track.class.php'));

class testTrackAssignmentCountRecords extends elis_database_test {

    protected static function get_overlay_tables() {
        return array(
            curriculumcourse::TABLE => 'elis_program',
            track::TABLE => 'elis_program',
            trackassignment::TABLE => 'elis_program',
            pmclass::TABLE => 'elis_program'
        );
    }

    protected function load_csv_data() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();

        $dataset->addTable(curriculumcourse::TABLE, elis::component_file('program', 'phpunit/curriculum_course.csv'));
        $dataset->addTable(track::TABLE, elis::component_file('program', 'phpunit/track.csv'));
        $dataset->addTable(trackassignment::TABLE, elis::component_file('program', 'phpunit/track_assign_count_track_class.csv'));
        $dataset->addTable(pmclass::TABLE, elis::component_file('program', 'phpunit/track_assign_count_pmclass.csv'));

        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    // Test that the alphabetical search returns the correct record count
    // Two results should be returned in this case
    public function testCountAlphaSearch() {
        $this->load_csv_data();
        $result = track_assignment_count_records(1, null, "alpha");
        $this->assertEquals(2, (int)$result);
    }

    // Test that the name search returns the correct record count
    // No results should be returned in this case
    public function testCountNameSearchNotFound() {
        $this->load_csv_data();
        $result = track_assignment_count_records(1, "beta");
        $this->assertEquals(0, (int)$result);
    }

    // Test that the name search returns the correct record count
    // One result should be returned in this case
    public function testCountNameSearchFound() {
        $this->load_csv_data();
        $result = track_assignment_count_records(1, "alphaclass");
        $this->assertEquals(1, (int)$result);
    }

}
