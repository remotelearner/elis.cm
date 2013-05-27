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
 * @copyright  (C) 2013 Remote Learner.net Inc http://www.remote-learner.net
 * @author     James McQuillan <james.mcquillan@remote-learner.net>
 *
 */

require_once(dirname(__FILE__).'/../../../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once(dirname(__FILE__).'/lib.php');

require_once(elispm::lib('deepsightpage.class.php'));
require_once(elispm::lib('selectionpage.class.php'));

require_once(elispm::lib('data/clusterassignment.class.php'));
require_once(elispm::lib('data/clustertrack.class.php'));
require_once(elispm::lib('data/curriculum.class.php'));
require_once(elispm::lib('data/track.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/userset.class.php'));
require_once(elispm::lib('data/usertrack.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));

/**
 * Mock usertrack_assigned datatable class to expose protected methods and properties.
 */
class deepsight_datatable_usertrack_assigned_mock extends deepsight_datatable_usertrack_assigned {

    /**
     * Magic function to expose protected properties.
     * @param string $name The name of the property
     * @return string|int|bool The value of the property
     */
    public function __get($name) {
        return (isset($this->$name)) ? $this->$name : false;
    }

    /**
     * Magic function to expose protected properties.
     * @param string $name The name of the property
     * @return string|int|bool The value of the property
     */
    public function __isset($name) {
        return (isset($this->$name)) ? true : false;
    }

    /**
     * Expose protected methods.
     * @param string $name The name of the called method.
     * @param array $args Array of arguments.
     */
    public function __call($name, $args) {
        if (method_exists($this, $name)) {
            return call_user_func_array(array($this, $name), $args);
        }
    }

    /**
     * Expose protected properties.
     * @param string $name The name of the property.
     * @param mixed $val The name to set.
     */
    public function __set($name, $val) {
        $this->$name = $val;
    }
}

/**
 * Mock usertrack_available datatable class to expose protected methods and properties.
 */
class deepsight_datatable_usertrack_available_mock extends deepsight_datatable_usertrack_available {

    /**
     * Magic function to expose protected properties.
     * @param string $name The name of the property
     * @return string|int|bool The value of the property
     */
    public function __get($name) {
        return (isset($this->$name)) ? $this->$name : false;
    }

    /**
     * Magic function to expose protected properties.
     * @param string $name The name of the property
     * @return string|int|bool The value of the property
     */
    public function __isset($name) {
        return (isset($this->$name)) ? true : false;
    }

    /**
     * Expose protected methods.
     * @param string $name The name of the called method.
     * @param array $args Array of arguments.
     */
    public function __call($name, $args) {
        if (method_exists($this, $name)) {
            return call_user_func_array(array($this, $name), $args);
        }
    }

    /**
     * Expose protected properties.
     * @param string $name The name of the property.
     * @param mixed $val The name to set.
     */
    public function __set($name, $val) {
        $this->$name = $val;
    }
}

/**
 * Tests usertrack datatable functions.
 */
class deepsight_datatable_usertrack_test extends deepsight_datatable_searchresults_test {

    /**
     * Return overlay tables.
     * @return array An array of overlay tables.
     */
    protected static function get_overlay_tables() {
        $overlay = array(
            clusterassignment::TABLE => 'elis_program',
            clustertrack::TABLE => 'elis_program',
            curriculum::TABLE => 'elis_program',
            curriculumstudent::TABLE => 'elis_program',
            track::TABLE => 'elis_program',
            userset::TABLE => 'elis_program',
            usertrack::TABLE => 'elis_program',
        );
        return array_merge(parent::get_overlay_tables(), $overlay);
    }

    /**
     * Do any setup before tests that rely on data in the database - i.e. create users/courses/classes/etc or import csvs.
     */
    protected function set_up_tables() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(curriculum::TABLE, elispm::lib('deepsight/phpunit/csv_program.csv'));
        $dataset->addTable(track::TABLE, elispm::lib('deepsight/phpunit/csv_track.csv'));
        $dataset->addTable(user::TABLE, elispm::lib('deepsight/phpunit/csv_user.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    /**
     * Transform an element from a csv into a search result array.
     * @param array $element An array of raw data from the CSV.
     * @return array A single search result array.
     */
    protected function create_search_result_from_csvelement($element) {
        return array(
            'element_id' => $element['id'],
            'element_name' => $element['name'],
            'element_idnumber' => $element['idnumber'],
            'pgm_name' => 'Test Program '.$element['curid'],
            'id' => $element['id'],
            'meta' => array(
                'label' => $element['name']
            )
        );
    }

    /**
     * Dataprovider for test_assigned_shows_assigned_tracks.
     * @return array Array of test parameters.
     */
    public function dataprovider_assigned_shows_assigned_tracks() {
        return array(
                // Test table shows nothing when no associations present.
                array(
                        array(),
                        101,
                        array(),
                        0,
                ),
                // Test table shows nothing when no associations present for current user.
                array(
                        array(
                                array('trackid' => 100, 'userid' => 101),
                        ),
                        100,
                        array(),
                        0
                ),
                // Test table shows existing associations.
                array(
                        array(
                                array('trackid' => 100, 'userid' => 101),
                        ),
                        101,
                        array(
                                $this->get_search_result_row('csv_track.csv', 100)
                        ),
                        1
                ),
                // Test table shows existing associations.
                array(
                        array(
                                array('trackid' => 100, 'userid' => 101),
                                array('trackid' => 101, 'userid' => 101),
                        ),
                        101,
                        array(
                                $this->get_search_result_row('csv_track.csv', 100),
                                $this->get_search_result_row('csv_track.csv', 101)
                        ),
                        2
                )
        );
    }

    /**
     * Test assigned table shows assigned tracks.
     *
     * @dataProvider dataprovider_assigned_shows_assigned_tracks
     * @param array $associations An array of arrays of parameters to construct usertrack associations.
     * @param int $tableuserid The user ID of the user we're going to manage.
     * @param array $expectedresults The expected page of results.
     * @param int $expectedtotal The expected number of total results.
     */
    public function test_assigned_shows_assigned_tracks($associations, $tableuserid, $expectedresults, $expectedtotal) {
        global $DB;

        foreach ($associations as $association) {
            $usertrack = new usertrack($association);
            $usertrack->save();
        }

        $table = new deepsight_datatable_usertrack_assigned_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_userid($tableuserid);

        $actualresults = $table->get_search_results(array(), array(), 0, 20);
        $this->assert_search_results($expectedresults, $expectedtotal, $actualresults);
    }

    /**
     * Test available table can show all tracks.
     */
    public function test_available_can_show_all_tracks() {
        global $USER, $DB, $CFG;
        $userbackup = $USER;

        // Set up permissions.
        $USER = $this->setup_permissions_test();
        $this->give_permission_for_context($USER->id, 'elis/program:track_enrol', get_context_instance(CONTEXT_SYSTEM));

        // Construct test table.
        $table = new deepsight_datatable_usertrack_available_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_userid(101);

        // Perform test.
        $actualresults = $table->get_search_results(array(), array(), 0, 20);

        // Verify result.
        $expectedresults = array(
                $this->get_search_result_row('csv_track.csv', 100),
                $this->get_search_result_row('csv_track.csv', 101),
                $this->get_search_result_row('csv_track.csv', 102),
                $this->get_search_result_row('csv_track.csv', 103),
        );
        $expectedtotal = 4;
        $this->assert_search_results($expectedresults, $expectedtotal, $actualresults);

        // Restore user.
        $USER = $userbackup;
    }

    /**
     * Dataprovider for test_available_doesnt_show_assigned_tracks.
     * @return array Array of test parameters.
     */
    public function dataprovider_available_doesnt_show_assigned_tracks() {
        return array(
                // Test the table shows all tracks when nothing is assigned.
                array(
                        array(),
                        101,
                        array(
                                $this->get_search_result_row('csv_track.csv', 100),
                                $this->get_search_result_row('csv_track.csv', 101),
                                $this->get_search_result_row('csv_track.csv', 102),
                                $this->get_search_result_row('csv_track.csv', 103),
                        ),
                        4
                ),
                // Test the table doesn't show assigned tracks.
                array(
                        array(
                                array('trackid' => 102, 'userid' => 101),
                        ),
                        101,
                        array(
                                $this->get_search_result_row('csv_track.csv', 100),
                                $this->get_search_result_row('csv_track.csv', 101),
                                $this->get_search_result_row('csv_track.csv', 103),
                        ),
                        3
                ),
                // Test multiple assignments.
                array(
                        array(
                                array('trackid' => 102, 'userid' => 101),
                                array('trackid' => 103, 'userid' => 101),
                        ),
                        101,
                        array(
                                $this->get_search_result_row('csv_track.csv', 100),
                                $this->get_search_result_row('csv_track.csv', 101),
                        ),
                        2
                ),
                // Test only assignments for the current user affect results.
                array(
                        array(
                                array('trackid' => 101, 'userid' => 102),
                                array('trackid' => 102, 'userid' => 101),
                                array('trackid' => 103, 'userid' => 101),
                        ),
                        101,
                        array(
                                $this->get_search_result_row('csv_track.csv', 100),
                                $this->get_search_result_row('csv_track.csv', 101),
                        ),
                        2
                ),
        );
    }

    /**
     * Test available table doesn't show assigned tracks.
     * @dataProvider dataprovider_available_doesnt_show_assigned_tracks
     * @param array $associations An array of arrays of parameters to construct usertrack associations.
     * @param int $tableuserid The user ID of the user we're going to manage.
     * @param array $expectedresults The expected page of results.
     * @param int $expectedtotal The expected number of total results.
     */
    public function test_available_doesnt_show_assigned_tracks($associations, $tableuserid, $expectedresults, $expectedtotal) {
        global $USER, $DB, $CFG;
        $userbackup = $USER;

        // Set up permissions.
        $USER = $this->setup_permissions_test();
        $this->give_permission_for_context($USER->id, 'elis/program:track_enrol', get_context_instance(CONTEXT_SYSTEM));

        foreach ($associations as $association) {
            $usertrack = new usertrack($association);
            $usertrack->save();
        }

        // Construct test table.
        $table = new deepsight_datatable_usertrack_available_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_userid($tableuserid);

        // Perform test.
        $actualresults = $table->get_search_results(array(), array(), 0, 20);

        // Verify result.
        $this->assert_search_results($expectedresults, $expectedtotal, $actualresults);

        // Restore user.
        $USER = $userbackup;
    }

    /**
     * Dataprovider for test_available_permissions_track_enrol.
     * @return array Array of test parameters.
     */
    public function dataprovider_available_permissions_track_enrol() {
        return array(
                // Test no permissions shows no tracks.
                array(
                        array(),
                        101,
                        array(),
                        0
                ),
                // Test permission on one track shows one track.
                array(
                        array(100),
                        101,
                        array(
                                $this->get_search_result_row('csv_track.csv', 100)
                        ),
                        1
                ),
                // Test permission on multiple tracks shows multiple tracks.
                array(
                        array(100, 101, 102),
                        101,
                        array(
                                $this->get_search_result_row('csv_track.csv', 100),
                                $this->get_search_result_row('csv_track.csv', 101),
                                $this->get_search_result_row('csv_track.csv', 102)
                        ),
                        3
                ),
        );
    }

    /**
     * Test available table shows only tracks the user has permission to enrol into based on the
     * elis/program:track_enrol permissions on the track.
     *
     * @dataProvider dataprovider_available_permissions_track_enrol
     * @param array $trackstoallow An array of track IDs to assign the elis/program:track_enrol permission at.
     * @param int $tableuserid The ID of the user we're managing.
     * @param array $expectedresults The expected page of results.
     * @param int $expectedtotal The expected number of total results.
     */
    public function test_available_permissions_track_enrol($trackstoallow, $tableuserid, $expectedresults, $expectedtotal) {
        global $USER, $DB, $CFG;

        $userbackup = $USER;

        // Set up permissions.
        $USER = $this->setup_permissions_test();
        foreach ($trackstoallow as $trackid) {
            $this->give_permission_for_context($USER->id, 'elis/program:track_enrol', context_elis_track::instance($trackid));
        }

        // Construct test table.
        $table = new deepsight_datatable_usertrack_available_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_userid($tableuserid);

        // Perform test.
        $actualresults = $table->get_search_results(array(), array(), 0, 20);

        // Verify result.
        $this->assert_search_results($expectedresults, $expectedtotal, $actualresults);

        // Restore user.
        $USER = $userbackup;
    }

    /**
     * Dataprovider for test_available_permissions_track_enrol_userset_user.
     * @return array Array of test parameters.
     */
    public function dataprovider_available_permissions_track_enrol_userset_user() {
        return array(
                // 0: Test when no permissions or associations exist, no tracks are returned.
                array(
                        array(),
                        array(),
                        array(),
                        101,
                        array(),
                        0,
                ),
                // 1: Test when associations exist but permissions are not present, tracks are not returned.
                array(
                        array(),
                        array(
                                array('userid' => 101, 'clusterid' => 3),
                        ),
                        array(
                                array('clusterid' => 3, 'trackid' => 102),
                        ),
                        101,
                        array(),
                        0,
                ),
                // 2: Test when permissions exist but no associations exist, tracks are not returned.
                array(
                        array(3),
                        array(),
                        array(),
                        101,
                        array(),
                        0,
                ),
                // 3: Test when permissions exist, and user is assigned to userset, but track is not assigned, track is not
                // returned.
                array(
                        array(3),
                        array(
                                array('userid' => 101, 'clusterid' => 3),
                        ),
                        array(),
                        101,
                        array(),
                        0,
                ),
                // 4: Test when permissions exist, and track is assigned to userset, but user is not assigned, track is not
                // returned.
                array(
                        array(3),
                        array(),
                        array(
                                array('clusterid' => 3, 'trackid' => 102),
                        ),
                        101,
                        array(),
                        0,
                ),
                // 5: Test when permissions exist, and associations exist for other users but not current user, track is not
                // returned.
                array(
                        array(3),
                        array(
                                array('userid' => 100, 'clusterid' => 3),
                        ),
                        array(
                                array('clusterid' => 3, 'trackid' => 102),
                        ),
                        101,
                        array(),
                        0
                ),
                // 6: Test when permissions exist, and associations exist, track is returned.
                array(
                        array(3),
                        array(
                                array('userid' => 101, 'clusterid' => 3),
                        ),
                        array(
                                array('clusterid' => 3, 'trackid' => 102),
                        ),
                        101,
                        array(
                                $this->get_search_result_row('csv_track.csv', 102),
                        ),
                        1
                ),
                // 7: Test when permissions exist, and associations exist for multiple tracks, tracks are returned.
                array(
                        array(3),
                        array(
                                array('userid' => 101, 'clusterid' => 3),
                        ),
                        array(
                                array('clusterid' => 3, 'trackid' => 101),
                                array('clusterid' => 3, 'trackid' => 102),
                        ),
                        101,
                        array(
                                $this->get_search_result_row('csv_track.csv', 101),
                                $this->get_search_result_row('csv_track.csv', 102),
                        ),
                        2
                ),
                // 8: Test when permissions exist for one cluster, and associations exist for multiple clusters, only tracks
                // from permissioned clusters are returned.
                array(
                        array(3),
                        array(
                                array('userid' => 101, 'clusterid' => 3),
                                array('userid' => 101, 'clusterid' => 4),
                        ),
                        array(
                                array('clusterid' => 3, 'trackid' => 101),
                                array('clusterid' => 4, 'trackid' => 102),
                        ),
                        101,
                        array(
                                $this->get_search_result_row('csv_track.csv', 101),
                        ),
                        1
                ),
                // 9: Test when permissions exist, and associations exist for multiple clusters, correct tracks are returned.
                array(
                        array(3, 4),
                        array(
                                array('userid' => 101, 'clusterid' => 3),
                                array('userid' => 101, 'clusterid' => 4),
                        ),
                        array(
                                array('clusterid' => 3, 'trackid' => 102),
                                array('clusterid' => 4, 'trackid' => 101),
                        ),
                        101,
                        array(
                                $this->get_search_result_row('csv_track.csv', 101),
                                $this->get_search_result_row('csv_track.csv', 102),
                        ),
                        2
                ),
                // 10: Test when permissions exist, and associations exist for multiple tracks, correct tracks are returned.
                array(
                        array(3),
                        array(
                                array('userid' => 101, 'clusterid' => 3),
                        ),
                        array(
                                array('clusterid' => 3, 'trackid' => 102),
                                array('clusterid' => 3, 'trackid' => 101),
                        ),
                        101,
                        array(
                                $this->get_search_result_row('csv_track.csv', 101),
                                $this->get_search_result_row('csv_track.csv', 102),
                        ),
                        2
                ),
                // 11: Test when permissions exist, and associations exist for multiple clusters & tracks, correct tracks are
                // returned.
                array(
                        array(3, 4, 5),
                        array(
                                array('userid' => 101, 'clusterid' => 3),
                                array('userid' => 101, 'clusterid' => 4),
                                array('userid' => 101, 'clusterid' => 5),
                        ),
                        array(
                                array('clusterid' => 3, 'trackid' => 100),
                                array('clusterid' => 3, 'trackid' => 101),
                                array('clusterid' => 4, 'trackid' => 102),
                                array('clusterid' => 5, 'trackid' => 103),
                        ),
                        101,
                        array(
                                $this->get_search_result_row('csv_track.csv', 100),
                                $this->get_search_result_row('csv_track.csv', 101),
                                $this->get_search_result_row('csv_track.csv', 102),
                                $this->get_search_result_row('csv_track.csv', 103),
                        ),
                        4
                ),
                // 12: Test that one track assigned to multiple clusters doesn't cause conflicts.
                array(
                        array(3, 4),
                        array(
                                array('userid' => 101, 'clusterid' => 3),
                                array('userid' => 101, 'clusterid' => 4),
                                array('userid' => 101, 'clusterid' => 5),
                        ),
                        array(
                                array('clusterid' => 3, 'trackid' => 100),
                                array('clusterid' => 3, 'trackid' => 101),
                                array('clusterid' => 4, 'trackid' => 100),
                                array('clusterid' => 5, 'trackid' => 100),
                        ),
                        101,
                        array(
                                $this->get_search_result_row('csv_track.csv', 100),
                                $this->get_search_result_row('csv_track.csv', 101),
                        ),
                        2
                ),
        );
    }

    /**
     * Test available table shows only tracks the user has permission to enrol into based on
     * elis/program:track_enrol_userset_user permission on a parent userset.
     *
     * @dataProvider dataprovider_available_permissions_track_enrol_userset_user
     * @param array $usersetidsforperm An array of userset IDs to assign the elis/program:track_enrol_userset_user on.
     * @param array $clusterassignments An array of arrays of parameters to construct clusterassignments with.
     * @param array $clustertracks An array of arrays of parameters to construct clustertracks with.
     * @param int $tableuserid The id of the user to manage associations for.
     * @param array $expectedresults The expected page of results.
     * @param int $expectedtotal The expected number of total results.
     */
    public function test_available_permissions_track_enrol_userset_user($usersetidsforperm, $clusterassignments, $clustertracks,
                                                                        $tableuserid, $expectedresults, $expectedtotal) {
        global $USER, $DB, $CFG;

        $userbackup = $USER;

        // Import usersets.
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(userset::TABLE, elispm::lib('deepsight/phpunit/csv_userset.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);

        // Set up permissions.
        $USER = $this->setup_permissions_test();
        $capability = 'elis/program:track_enrol_userset_user';
        foreach ($usersetidsforperm as $usersetid) {
            $this->give_permission_for_context($USER->id, $capability, context_elis_userset::instance($usersetid));
        }

        // Create clusterassignments.
        foreach ($clusterassignments as $clusterassignment) {
            $clusterassignment = new clusterassignment($clusterassignment);
            $clusterassignment->save();
        }

        // Create clustertracks.
        foreach ($clustertracks as $clustertrack) {
            $clustertrack = new clustertrack($clustertrack);
            $clustertrack->save();
        }

        // Construct test table.
        $table = new deepsight_datatable_usertrack_available_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_userid($tableuserid);

        // Perform test.
        $actualresults = $table->get_search_results(array(), array(), 0, 20);

        // Verify.
        $this->assert_search_results($expectedresults, $expectedtotal, $actualresults);
    }
}