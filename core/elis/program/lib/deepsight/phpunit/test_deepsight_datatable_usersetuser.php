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

require_once(elispm::lib('data/curriculumstudent.class.php'));

/**
 * Mock usersetuser_assigned datatable class to expose protected methods and properties.
 */
class deepsight_datatable_usersetuser_assigned_mock extends deepsight_datatable_usersetuser_assigned {

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
     * @return string|int|bool|float|array|object The return value of the function.
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
 * Mock usersetuser_available datatable class to expose protected methods and properties.
 */
class deepsight_datatable_usersetuser_available_mock extends deepsight_datatable_usersetuser_available {

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
     * @return string|int|bool|float|array|object The return value of the function.
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
 * Tests usersetuser datatable functions.
 */
class deepsight_datatable_usersetuser_test extends deepsight_datatable_searchresults_test {

    /**
     * Return overlay tables.
     * @return array An array of overlay tables.
     */
    protected static function get_overlay_tables() {
        $overlay = array(
            clusterassignment::TABLE => 'elis_program',
            curriculumstudent::TABLE => 'elis_program',
            userset::TABLE => 'elis_program',
        );
        return array_merge(parent::get_overlay_tables(), $overlay);
    }

    /**
     * Do any setup before tests that rely on data in the database - i.e. create users/courses/classes/etc or import csvs.
     */
    protected function set_up_tables() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(user::TABLE, elispm::lib('deepsight/phpunit/csv_user.csv'));
        $dataset->addTable(userset::TABLE, elispm::lib('deepsight/phpunit/csv_usersetwithsubsets.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    /**
     * Transform an element from a csv into a search result array.
     * @return array A single search result array.
     */
    protected function create_search_result_from_csvelement($element) {
        return array(
            'element_id' => $element['id'],
            'element_idnumber' => $element['idnumber'],
            'element_firstname' => $element['firstname'],
            'element_lastname' => $element['lastname'],
            'id' => $element['id'],
            'meta' => array(
                'label' => $element['firstname'].' '.$element['lastname']
            )
        );
    }

    /**
     * Get search result array for the assigning user (created when testing with permissions.)
     * @param array $element An array of raw data from the CSV.
     * @return array A single search result array for the assigning user.
     */
    protected function get_search_result_row_assigning_user() {
        return array(
            'element_id' => 102,
            'element_idnumber' => 'assigninguser',
            'element_firstname' => 'assigninguser',
            'element_lastname' => 'assigninguser',
            'id' => 102,
            'meta' => array(
                'label' => 'assigninguser assigninguser'
            )
        );
    }

    /**
     * Dataprovider for test_assigned_shows_assigned_users.
     * @return array Array of test parameters.
     */
    public function dataprovider_assigned_shows_assigned_users() {
        return array(
                // Test table shows nothing when no associations present.
                array(
                        array(),
                        2,
                        array(),
                        0,
                ),
                // Test table shows nothing when no associations present for current cluster.
                array(
                        array(
                                array('clusterid' => 2, 'userid' => 100),
                        ),
                        3,
                        array(),
                        0,
                ),
                // Test table shows existing associations.
                array(
                        array(
                                array('clusterid' => 3, 'userid' => 101),
                        ),
                        3,
                        array(
                                $this->get_search_result_row('csv_user.csv', 101),
                        ),
                        1,
                ),
                // Test table shows existing associations.
                array(
                        array(
                                array('clusterid' => 3, 'userid' => 100),
                                array('clusterid' => 3, 'userid' => 101),
                        ),
                        3,
                        array(
                                $this->get_search_result_row('csv_user.csv', 100),
                                $this->get_search_result_row('csv_user.csv', 101),
                        ),
                        2,
                ),
        );
    }

    /**
     * Test assigned table shows assigned users.
     * @dataProvider dataprovider_assigned_shows_assigned_users
     * @param array $associations An array of arrays of parameters to construct clusterassignment associations.
     * @param int $tableusersetid The ID of the userset we're managing.
     * @param array $expectedresults The expected page of results.
     * @param int $expectedtotal The expected number of total results.
     */
    public function test_assigned_shows_assigned_users($associations, $tableusersetid, $expectedresults, $expectedtotal) {
        global $DB;

        foreach ($associations as $association) {
            $clusterassignment = new clusterassignment($association);
            $clusterassignment->save();
        }

        $table = new deepsight_datatable_usersetuser_assigned_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_usersetid($tableusersetid);

        $actualresults = $table->get_search_results(array(), array(), 0, 20);
        $this->assert_search_results($expectedresults, $expectedtotal, $actualresults);
    }

    /**
     * Dataprovider for test_available_userset_enrol_perms.
     * @return array Array of test parameters.
     */
    public function dataprovider_available_userset_enrol_perms() {
        return array(
                // Test empty results when user has no permissions.
                array(
                        array(),
                        1,
                        array(),
                        0,
                ),
                // Test all users are returned when user has permission at the system context.
                array(
                        array('system' => 1),
                        1,
                        array(
                                $this->get_search_result_row('csv_user.csv', 100),
                                $this->get_search_result_row('csv_user.csv', 101),
                                $this->get_search_result_row_assigning_user(),
                        ),
                        3,
                ),
                // Test no users are returned when the user has only on a child userset, but we're looking at the parent userset.
                array(
                        array('userset' => 2),
                        1,
                        array(),
                        0,
                ),
                // Test all users are returned when the user has permission at a parent userset.
                array(
                        array('userset' => 1),
                        2,
                        array(
                                $this->get_search_result_row('csv_user.csv', 100),
                                $this->get_search_result_row('csv_user.csv', 101),
                                $this->get_search_result_row_assigning_user(),
                        ),
                        3,
                ),
                // Test no users are returned when the user has permission at the wrong userset context.
                array(
                        array('userset' => 3),
                        1,
                        array(),
                        0,
                ),
                // Test all users are returned when the user has permission at the right userset context.
                array(
                        array('userset' => 3),
                        3,
                        array(
                                $this->get_search_result_row('csv_user.csv', 100),
                                $this->get_search_result_row('csv_user.csv', 101),
                                $this->get_search_result_row_assigning_user(),
                        ),
                        3,
                )
        );
    }

    /**
     * Test available table shows correct search results based on elis/program:userset_enrol perms.
     * @dataProvider dataprovider_available_userset_enrol_perms
     * @param array $permcontexts An array of context objects for which to assign the elis/program:userset_enrol permission.
     * @param int $tableusersetid The ID of the userset to use for the table.
     * @param array $expectedresults An array of expected search results.
     * @param int $expectedtotal The expected total number of search results.
     */
    public function test_available_userset_enrol_perms($permcontexts, $tableusersetid, $expectedresults, $expectedtotal) {
        global $USER, $DB, $CFG;
        $userbackup = $USER;

        $USER = $this->setup_permissions_test();

        // Set up permissions.
        foreach ($permcontexts as $level => $id) {
            $context = null;
            switch($level) {
                case 'system':
                    $permcontext = get_context_instance(CONTEXT_SYSTEM);
                    break;
                case 'userset':
                    $permcontext = context_elis_userset::instance($id);
                    break;
            }
            $this->give_permission_for_context($USER->id, 'elis/program:userset_enrol', $permcontext);
        }

        // Construct test table.
        $table = new deepsight_datatable_usersetuser_available_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_usersetid($tableusersetid);

        // Perform test.
        $actualresults = $table->get_search_results(array(), array(), 0, 20);

        // Verify result.
        $this->assert_search_results($expectedresults, $expectedtotal, $actualresults);

        // Restore user.
        $USER = $userbackup;
    }

    /**
     * Dataprovider for test_available_doesnt_show_assigned_users.
     *
     * @return array Array of test parameters.
     */
    public function dataprovider_available_doesnt_show_assigned_users() {
        return array(
                // Test table shows all users when nothing is assigned.
                array(
                        array(),
                        1,
                        array(
                                $this->get_search_result_row('csv_user.csv', 100),
                                $this->get_search_result_row('csv_user.csv', 101),
                                $this->get_search_result_row_assigning_user(),
                        ),
                        3,
                ),
                // Test table doesn't show assigned users.
                array(
                        array(
                                array('clusterid' => 1, 'userid' => 101),
                        ),
                        1,
                        array(
                                $this->get_search_result_row('csv_user.csv', 100),
                                $this->get_search_result_row_assigning_user(),
                        ),
                        2,
                ),
                // Test multiple assignments.
                array(
                        array(
                                array('clusterid' => 1, 'userid' => 100),
                                array('clusterid' => 1, 'userid' => 101),
                        ),
                        1,
                        array(
                                $this->get_search_result_row_assigning_user(),
                        ),
                        1,
                ),
                // Test only assignments for the current userset affect results.
                array(
                        array(
                                array('clusterid' => 3, 'userid' => 100),
                                array('clusterid' => 1, 'userid' => 101),
                        ),
                        1,
                        array(
                                $this->get_search_result_row('csv_user.csv', 100),
                                $this->get_search_result_row_assigning_user(),
                        ),
                        2,
                ),
        );
    }

    /**
     * Test available table doesn't show assigned users.
     * @dataProvider dataprovider_available_doesnt_show_assigned_users
     * @param array $associations An array of arrays of parameters to construct clusterassignment associations.
     * @param int $tableusersetid The ID of the userset user we're going to manage.
     * @param array $expectedresults The expected page of results.
     * @param int $expectedtotal The expected number of total results.
     */
    public function test_available_doesnt_show_assigned_users($associations, $tableusersetid, $expectedresults, $expectedtotal) {
        global $USER, $DB, $CFG;
        $userbackup = $USER;

        // Set up permissions.
        $USER = $this->setup_permissions_test();
        $this->give_permission_for_context($USER->id, 'elis/program:userset_enrol', get_context_instance(CONTEXT_SYSTEM));

        foreach ($associations as $association) {
            $clusterassignment = new clusterassignment($association);
            $clusterassignment->save();
        }

        // Construct test table.
        $table = new deepsight_datatable_usersetuser_available_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_usersetid($tableusersetid);

        // Perform test.
        $actualresults = $table->get_search_results(array(), array(), 0, 20);

        // Verify result.
        $this->assert_search_results($expectedresults, $expectedtotal, $actualresults);

        // Restore user.
        $USER = $userbackup;
    }

    /**
     * Dataprovider for test_available_permissions_userset_enrol_userset_user.
     * @return array Array of test parameters.
     */
    public function dataprovider_available_permissions_userset_enrol_userset_user() {
        return array(
                // 0: Test when no permissions or associations exist, no users are returned.
                array(
                        array(),
                        array(),
                        1,
                        array(),
                        0,
                ),
                // 1: Test when users are assigned but permissions are not present, users are not returned.
                array(
                        array(),
                        array(
                                array('userid' => 101, 'clusterid' => 1),
                        ),
                        2,
                        array(),
                        0,
                ),
                // 2: Test when permissions exist but no users are assigned, users are not returned.
                array(
                        array(1),
                        array(),
                        2,
                        array(),
                        0,
                ),
                // 3: Test when permissions exist on the parent and users are assigned to the parent, those users are returned
                // when looking at the child userset's available users.
                array(
                        array(1),
                        array(
                                array('userid' => 101, 'clusterid' => 1),
                        ),
                        2,
                        array(
                                $this->get_search_result_row('csv_user.csv', 101),
                        ),
                        1,
                ),
                // 4: Test when permissions exist on the parent and multiple users are assigned to the parent, multiple users are
                // returned when looking at the child userset's available users.
                array(
                        array(1),
                        array(
                                array('userid' => 100, 'clusterid' => 1),
                                array('userid' => 101, 'clusterid' => 1),
                        ),
                        2,
                        array(
                                $this->get_search_result_row('csv_user.csv', 100),
                                $this->get_search_result_row('csv_user.csv', 101),
                        ),
                        2,
                ),
                // 5: Test that user assignments to other clusters do not appear.
                array(
                        array(1),
                        array(
                                array('userid' => 100, 'clusterid' => 1),
                                array('userid' => 101, 'clusterid' => 3),
                        ),
                        2,
                        array(
                                $this->get_search_result_row('csv_user.csv', 100),
                        ),
                        1,
                ),
                // 6: Test that when looking at a sub-subset, and permissions are assigned at the grandparent userset, all users
                // from the grandparent and parent are returned.
                //
                array(
                        array(1),
                        array(
                                array('userid' => 100, 'clusterid' => 1),
                                array('userid' => 101, 'clusterid' => 2),
                        ),
                        4,
                        array(
                                $this->get_search_result_row('csv_user.csv', 100),
                                $this->get_search_result_row('csv_user.csv', 101),
                        ),
                        2,
                ),
                // 7: Test when looking at a sub-subset, and permissions are assigned at the parent userset, only users from the
                // parent userset and NOT the grandparent userset are returned.
                array(
                        array(2),
                        array(
                                array('userid' => 100, 'clusterid' => 1),
                                array('userid' => 101, 'clusterid' => 2),
                        ),
                        4,
                        array(
                                $this->get_search_result_row('csv_user.csv', 101),
                        ),
                        1,
                ),
        );
    }

    /**
     * Test available table obeys userset_enrol_userset_users permission.
     *
     * Test available table shows only users that are in usersets where:
     *     - the assigner has the elis/program:userset_enrol_userset_user permission
     *     - the current userset is associated with the userset.
     *
     * @dataProvider dataprovider_available_permissions_userset_enrol_userset_user
     * @param array $usersetidsforperm An array of userset IDs to assign the elis/program:userset_enrol_userset_user on.
     * @param array $clusterassignments An array of arrays of parameters to construct clusterassignments with.
     * @param int $tableusersetid The id of the userset to manage associations for.
     * @param array $expectedresults The expected page of results.
     * @param int $expectedtotal The expected number of total results.
     */
    public function test_available_permissions_userset_enrol_userset_user($usersetidsforperm, $clusterassignments,
                                                                          $tableusersetid, $expectedresults, $expectedtotal) {
        global $USER, $DB, $CFG;

        $userbackup = $USER;

        // Set up permissions.
        $USER = $this->setup_permissions_test();

        // Set up userset contexts.
        for ($i = 1; $i <= 6; $i++) {
            $ctx = context_elis_userset::instance($i);
        }

        accesslib_clear_all_caches(true);

        // Assign capabilities.
        $capability = 'elis/program:userset_enrol_userset_user';
        foreach ($usersetidsforperm as $usersetid) {
            $this->give_permission_for_context($USER->id, $capability, context_elis_userset::instance($usersetid));
        }

        // Create clusterassignments.
        foreach ($clusterassignments as $clusterassignment) {
            $clusterassignment = new clusterassignment($clusterassignment);
            $clusterassignment->save();
        }

        // Construct test table.
        $table = new deepsight_datatable_usersetuser_available_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_usersetid($tableusersetid);

        // Perform test.
        $actualresults = $table->get_search_results(array(), array(), 0, 20);

        // Verify.
        $this->assert_search_results($expectedresults, $expectedtotal, $actualresults);
    }
}