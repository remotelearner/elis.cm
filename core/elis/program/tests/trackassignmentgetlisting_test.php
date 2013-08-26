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
require_once(elispm::lib('data/student.class.php'));
require_once(elispm::lib('data/usertrack.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/curriculumcourse.class.php'));

/**
 * Unit tests for the track_assignment_get_listing method defined in track.class.php
 * @group elis_program
 */
class trackassignmentgetlisting_testcase extends elis_database_test {

    /**
     * Load initial data from CSVs.
     */
    protected function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            track::TABLE => elis::component_file('program', 'tests/fixtures/track.csv'),
            trackassignment::TABLE => elis::component_file('program', 'tests/fixtures/trackassignment_trackassignment_listing.csv'),
            student::TABLE => elis::component_file('program', 'tests/fixtures/student_trackassignment_listing.csv'),
            usertrack::TABLE => elis::component_file('program', 'tests/fixtures/usertrack_trackassignment_listing.csv'),
            user::TABLE => elis::component_file('program', 'tests/fixtures/user_trackassignment_listing.csv'),
            pmclass::TABLE => elis::component_file('program', 'tests/fixtures/pmclass.csv'),
            curriculumcourse::TABLE => elis::component_file('program', 'tests/fixtures/curriculum_course_trackassignment_listing.csv'),
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * Validate that the enrolment count includes inactive users when the site is configured to show inactive users
     */
    public function test_usercountincludesinactivewhenincludinginactive() {
        require_once(elispm::lib('lib.php'));

        // Set up all the data needed for the listing.
        $this->load_csv_data();

        // Enable showing of inactive users.
        pm_set_config('legacy_show_inactive_users', 1);
        elis::$config = new elis_config();

        // Obtain the lsting.
        $listing = track_assignment_get_listing(1);

        // Validate the number of rows.
        $count = 0;
        foreach ($listing as $entity) {
            $count++;

            // Validate the aggregated count in the first row.
            $this->assertEquals(2, $entity->enrolments);
        }
        unset($listing);
        $this->assertEquals(1, $count);
    }

    /**
     * Validate that the enrolment count excludes inactive users when the site is not configured to show inactive users
     */
    public function test_usercountexcludesinactivewhenexcludinginactive() {
        require_once(elispm::lib('lib.php'));

        // Set up all the data needed for the listing.
        $this->load_csv_data();

        // Disable showing of inactive users.
        pm_set_config('legacy_show_inactive_users', 0);
        elis::$config = new elis_config();

        // Obtain the listing.
        $listing = track_assignment_get_listing(1);

        // Validate the number of rows.
        $count = 0;
        foreach ($listing as $entity) {
            $count++;

            // Validate the aggregated count in the first row.
            $this->assertEquals(1, $entity->enrolments);
        }
        unset($listing);
        $this->assertEquals(1, $count);
    }
}