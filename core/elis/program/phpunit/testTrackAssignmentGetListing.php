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

require_once(dirname(__FILE__).'/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once('PHPUnit/Extensions/Database/DataSet/CsvDataSet.php');
require_once(elispm::lib('data/track.class.php'));

/**
 * Unit tests for the track_assignment_get_listing method defined in track.class.php
 */
class testTrackAssignmentGetListing extends elis_database_test {
    /**
     * Return the list of tables that should be overlayed.
     */
    static protected function get_overlay_tables() {
        require_once(elispm::lib('data/student.class.php'));
        require_once(elispm::lib('data/usertrack.class.php'));
        require_once(elispm::lib('data/user.class.php'));
        require_once(elispm::lib('data/pmclass.class.php'));
        require_once(elispm::lib('data/curriculumcourse.class.php'));

        return array(track::TABLE => 'elis_program',
                     trackassignment::TABLE => 'elis_program',
                     student::TABLE => 'elis_program',
                     usertrack::TABLE => 'elis_program',
                     user::TABLE => 'elis_program',
                     pmclass::TABLE => 'elis_program',
                     curriculumcourse::TABLE => 'elis_program',
                     'config_plugins' => 'moodle');
    }

    /**
     * Load all necessary data from CSV files
     */
    protected function load_csv_data() {
        global $CFG;
        require_once(elispm::lib('data/student.class.php'));
        require_once(elispm::lib('data/usertrack.class.php'));
        require_once(elispm::lib('data/user.class.php'));
        require_once(elispm::lib('data/pmclass.class.php'));
        require_once(elispm::lib('data/curriculumcourse.class.php'));

        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();

        //set up all of the PM associations needed to get the listing to work
        //have to use many test-specific files because ids won't match up otherwise
        $dataset->addTable(track::TABLE, elis::component_file('program', 'phpunit/track.csv'));
        $dataset->addTable(trackassignment::TABLE, elis::component_file('program', 'phpunit/trackassignment_trackassignment_listing.csv'));
        $dataset->addTable(student::TABLE, elis::component_file('program', 'phpunit/student_trackassignment_listing.csv'));
        $dataset->addTable(usertrack::TABLE, elis::component_file('program', 'phpunit/usertrack_trackassignment_listing.csv'));
        $dataset->addTable(user::TABLE, elis::component_file('program', 'phpunit/user_trackassignment_listing.csv'));
        $dataset->addTable(pmclass::TABLE, elis::component_file('program', 'phpunit/pmclass.csv'));
        $dataset->addTable(curriculumcourse::TABLE, elis::component_file('program', 'phpunit/curriculum_course_trackassignment_listing.csv'));

        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    /**
     * Validate that the enrolment count includes inactive users when the site
     * is configured to show inactive users
     */
    public function testUserCountIncludesInactiveWhenIncludingInactive() {
        require_once(elispm::lib('lib.php'));

        //set up all the data needed for the listing
        $this->load_csv_data();

        //enable showing of inactive users
        pm_set_config('legacy_show_inactive_users', 1);
        elis::$config = new elis_config();

        //obtain the lsting
        $listing = track_assignment_get_listing(1);

        //validate the number of rows
        $count = 0;
        foreach ($listing as $entity) {
            $count++;

            //validate the aggregated count in the first row
            $this->assertEquals(2, $entity->enrolments);
        }
        unset($listing);
        $this->assertEquals(1, $count);
    }

    /**
     * Validate that the enrolment count excludes inactive users when the site
     * is not configured to show inactive users
     */
    public function testUserCountExcludesInactiveWhenExcludingInactive() {
        require_once(elispm::lib('lib.php'));

        //set up all the data needed for the listing
        $this->load_csv_data();

        //disable showing of inactive users
        pm_set_config('legacy_show_inactive_users', 0);
        elis::$config = new elis_config();

        //obtain the listing
        $listing = track_assignment_get_listing(1);

        //validate the number of rows
        $count = 0;
        foreach ($listing as $entity) {
            $count++;

            //validate the aggregated count in the first row
            $this->assertEquals(1, $entity->enrolments);
        }
        unset($listing);
        $this->assertEquals(1, $count);
    }
}