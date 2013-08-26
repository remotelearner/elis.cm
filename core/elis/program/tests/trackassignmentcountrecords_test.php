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
require_once(elispm::lib('data/track.class.php'));

/**
 * Test track assignment count records
 * @group elis_program
 */
class trackassignmentcountrecords_testcase extends elis_database_test {

    /**
     * Load initial data from CSVs.
     */
    protected function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            curriculumcourse::TABLE => elis::component_file('program', 'tests/fixtures/curriculum_course.csv'),
            track::TABLE => elis::component_file('program', 'tests/fixtures/track.csv'),
            trackassignment::TABLE => elis::component_file('program', 'tests/fixtures/track_assign_count_track_class.csv'),
            pmclass::TABLE => elis::component_file('program', 'tests/fixtures/track_assign_count_pmclass.csv'),
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * Test that the alphabetical search returns the correct record count
     * Two results should be returned in this case
     */
    public function test_countalphasearch() {
        $this->load_csv_data();
        $result = track_assignment_count_records(1, null, "alpha");
        $this->assertEquals(2, (int)$result);
    }

    /**
     * Test that the name search returns the correct record count
     * No results should be returned in this case
     */
    public function test_countnamesearchnotfound() {
        $this->load_csv_data();
        $result = track_assignment_count_records(1, "beta");
        $this->assertEquals(0, (int)$result);
    }

    /**
     * Test that the name search returns the correct record count
     * One result should be returned in this case
     */
    public function test_countnamesearchfound() {
        $this->load_csv_data();
        $result = track_assignment_count_records(1, "alphaclass");
        $this->assertEquals(1, (int)$result);
    }
}