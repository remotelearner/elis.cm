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
 * @package    elis
 * @subpackage programmanager
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
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
 * Mock useruserset_assigned datatable class to expose protected methods and properties.
 */
class deepsight_datatable_useruserset_assigned_mock extends deepsight_datatable_useruserset_assigned {

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
 * Mock useruserset_available datatable class to expose protected methods and properties.
 */
class deepsight_datatable_useruserset_available_mock extends deepsight_datatable_useruserset_available {

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
 * Tests useruserset datatable functions.
 */
class deepsight_datatable_useruserset_test extends deepsight_datatable_searchresults_test {
    protected $resultscsv = 'csv_usersetwithsubsets.csv';


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
     * @param array $element An array of raw data from the CSV.
     * @return array A single search result array.
     */
    protected function create_search_result_from_csvelement($element) {
        return array(
            'element_id' => $element['id'],
            'element_name' => $element['name'],
            'id' => $element['id'],
            'meta' => array(
                'label' => $element['name']
            )
        );
    }

    /**
     * Dataprovider for test_assigned_shows_assigned_usersets.
     * @return array Array of test parameters.
     */
    public function dataprovider_assigned_shows_assigned_usersets() {
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
                                array('clusterid' => 1, 'userid' => 101),
                        ),
                        100,
                        array(),
                        0
                ),
                // Test table shows existing associations.
                array(
                        array(
                                array('clusterid' => 1, 'userid' => 101),
                        ),
                        101,
                        array(
                                $this->get_search_result_row($this->resultscsv, 1)
                        ),
                        1
                ),
                // Test table shows existing associations.
                array(
                        array(
                                array('clusterid' => 1, 'userid' => 101),
                                array('clusterid' => 2, 'userid' => 101),
                        ),
                        101,
                        array(
                                $this->get_search_result_row($this->resultscsv, 1),
                                $this->get_search_result_row($this->resultscsv, 2),
                        ),
                        2
                )
        );
    }

    /**
     * Test assigned table shows assigned usersets.
     *
     * @dataProvider dataprovider_assigned_shows_assigned_usersets
     * @param array $associations An array of arrays of parameters to construct clusterassignment associations.
     * @param int $tableuserid The user ID of the user we're going to manage.
     * @param array $expectedresults The expected page of results.
     * @param int $expectedtotal The expected number of total results.
     */
    public function test_assigned_shows_assigned_usersets($associations, $tableuserid, $expectedresults, $expectedtotal) {
        global $DB;

        foreach ($associations as $association) {
            $clusterassignment = new clusterassignment($association);
            $clusterassignment->save();
        }

        $table = new deepsight_datatable_useruserset_assigned_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_userid($tableuserid);

        $actualresults = $table->get_search_results(array(), array(), 0, 20);
        $this->assert_search_results($expectedresults, $expectedtotal, $actualresults);
    }

    /**
     * Test available table can show all usersets.
     */
    public function test_available_can_show_all_usersets() {
        global $USER, $DB, $CFG;
        $userbackup = $USER;

        // Set up permissions.
        $USER = $this->setup_permissions_test();
        $this->give_permission_for_context($USER->id, 'elis/program:userset_enrol', get_context_instance(CONTEXT_SYSTEM));

        // Construct test table.
        $table = new deepsight_datatable_useruserset_available_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_userid(101);

        // Perform test.
        $actualresults = $table->get_search_results(array(), array(), 0, 20);

        // Verify result.
        $expectedresults = array(
                $this->get_search_result_row($this->resultscsv, 1),
                $this->get_search_result_row($this->resultscsv, 2),
                $this->get_search_result_row($this->resultscsv, 3),
                $this->get_search_result_row($this->resultscsv, 4),
                $this->get_search_result_row($this->resultscsv, 5),
                $this->get_search_result_row($this->resultscsv, 6),
        );
        $expectedtotal = 6;
        $this->assert_search_results($expectedresults, $expectedtotal, $actualresults);

        // Restore user.
        $USER = $userbackup;
    }

    /**
     * Dataprovider for test_available_doesnt_show_assigned_usersets.
     * @return array Array of test parameters.
     */
    public function dataprovider_available_doesnt_show_assigned_usersets() {
        return array(
                // Test the table shows all usersets when nothing is assigned.
                array(
                        array(),
                        101,
                        array(
                                $this->get_search_result_row($this->resultscsv, 1),
                                $this->get_search_result_row($this->resultscsv, 2),
                                $this->get_search_result_row($this->resultscsv, 3),
                                $this->get_search_result_row($this->resultscsv, 4),
                                $this->get_search_result_row($this->resultscsv, 5),
                                $this->get_search_result_row($this->resultscsv, 6),
                        ),
                        6
                ),
                // Test the table doesn't show assigned usersets.
                array(
                        array(
                                array('clusterid' => 2, 'userid' => 101),
                        ),
                        101,
                        array(
                                $this->get_search_result_row($this->resultscsv, 1),
                                $this->get_search_result_row($this->resultscsv, 3),
                                $this->get_search_result_row($this->resultscsv, 4),
                                $this->get_search_result_row($this->resultscsv, 5),
                                $this->get_search_result_row($this->resultscsv, 6),
                        ),
                        5
                ),
                // Test multiple assignments.
                array(
                        array(
                                array('clusterid' => 1, 'userid' => 101),
                                array('clusterid' => 3, 'userid' => 101),
                        ),
                        101,
                        array(
                                $this->get_search_result_row($this->resultscsv, 2),
                                $this->get_search_result_row($this->resultscsv, 4),
                                $this->get_search_result_row($this->resultscsv, 5),
                                $this->get_search_result_row($this->resultscsv, 6),
                        ),
                        4
                ),
                // Test only assignments for the current user affect results.
                array(
                        array(
                                array('clusterid' => 1, 'userid' => 102),
                                array('clusterid' => 2, 'userid' => 101),
                                array('clusterid' => 3, 'userid' => 101),
                        ),
                        101,
                        array(
                                $this->get_search_result_row($this->resultscsv, 1),
                                $this->get_search_result_row($this->resultscsv, 4),
                                $this->get_search_result_row($this->resultscsv, 5),
                                $this->get_search_result_row($this->resultscsv, 6),
                        ),
                        4
                ),
        );
    }

    /**
     * Test available table doesn't show assigned usersets.
     * @dataProvider dataprovider_available_doesnt_show_assigned_usersets
     * @param array $associations An array of arrays of parameters to construct clusterassignment associations.
     * @param int $tableuserid The user ID of the user we're going to manage.
     * @param array $expectedresults The expected page of results.
     * @param int $expectedtotal The expected number of total results.
     */
    public function test_available_doesnt_show_assigned_usersets($associations, $tableuserid, $expectedresults, $expectedtotal) {
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
        $table = new deepsight_datatable_useruserset_available_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_userid($tableuserid);

        // Perform test.
        $actualresults = $table->get_search_results(array(), array(), 0, 20);

        // Verify result.
        $this->assert_search_results($expectedresults, $expectedtotal, $actualresults);

        // Restore user.
        $USER = $userbackup;
    }

    /**
     * Dataprovider for test_available_permissions_userset_enrol.
     * @return array Array of test parameters.
     */
    public function dataprovider_available_permissions_userset_enrol() {
        return array(
                // Test no permissions shows no usersets.
                array(
                        array(),
                        101,
                        array(),
                        0
                ),
                // Test permission on one userset shows one userset.
                array(
                        array(3),
                        101,
                        array(
                                $this->get_search_result_row($this->resultscsv, 3)
                        ),
                        1
                ),
                // Test permission on multiple usersets shows multiple userset.
                array(
                        array(3, 4),
                        101,
                        array(
                                $this->get_search_result_row($this->resultscsv, 3),
                                $this->get_search_result_row($this->resultscsv, 4),
                        ),
                        2
                ),
                // Test permission on parent usersets shows descendant usersets.
                array(
                        array(1),
                        101,
                        array(
                                $this->get_search_result_row($this->resultscsv, 1),
                                $this->get_search_result_row($this->resultscsv, 2),
                                $this->get_search_result_row($this->resultscsv, 4),
                                $this->get_search_result_row($this->resultscsv, 5),
                                $this->get_search_result_row($this->resultscsv, 6),
                        ),
                        5
                ),
        );
    }

    /**
     * Test available table shows only usersets the user has permission to enrol into based on the
     * elis/program:userset_enrol permissions on the userset.
     *
     * @dataProvider dataprovider_available_permissions_userset_enrol
     * @param array $usersetstoallow An array of usersets IDs to assign the elis/program:userset_enrol permission at.
     * @param int $tableuserid The ID of the user we're managing.
     * @param array $expectedresults The expected page of results.
     * @param int $expectedtotal The expected number of total results.
     */
    public function test_available_permissions_userset_enrol($usersetstoallow, $tableuserid, $expectedresults, $expectedtotal) {
        global $USER, $DB, $CFG;

        $userbackup = $USER;

        // Set up permissions.
        $USER = $this->setup_permissions_test();

        // Set up contexts.
        for ($i = 1; $i <= 6; $i++) {
            $ctx = context_elis_userset::instance($i);
        }

        accesslib_clear_all_caches(true);

        // Set up associations.
        foreach ($usersetstoallow as $usersetid) {
            $this->give_permission_for_context($USER->id, 'elis/program:userset_enrol', context_elis_userset::instance($usersetid));
        }

        // Construct test table.
        $table = new deepsight_datatable_useruserset_available_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_userid($tableuserid);

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
                // 0: Test when no permissions or associations exist, no usersets are returned.
                array(
                        array(),
                        array(),
                        101,
                        array(),
                        0,
                ),
                // 1: Test when associations exist but permissions are not present, usersets are not returned.
                array(
                        array(),
                        array(
                                array('userid' => 101, 'clusterid' => 1),
                        ),
                        101,
                        array(),
                        0,
                ),
                // 2: Test when permissions exist but no associations exist, usersets are not returned.
                array(
                        array(1),
                        array(),
                        101,
                        array(),
                        0,
                ),
                // 3: Test when permissions exist on grandparent userset and user is assigned to grandparent userset, all children
                // and grandchildren usersets are returned.
                array(
                        array(1),
                        array(
                                array('userid' => 101, 'clusterid' => 1),
                        ),
                        101,
                        array(
                                $this->get_search_result_row($this->resultscsv, 2),
                                $this->get_search_result_row($this->resultscsv, 4),
                                $this->get_search_result_row($this->resultscsv, 5),
                                $this->get_search_result_row($this->resultscsv, 6),
                        ),
                        4,
                ),
                // 4: Test when user is assigned to parent userset (where grandparent exists), and assigner is assigned permission
                // on the grandparent userset, only children of the parent userset are returned.
                array(
                        array(1),
                        array(
                                array('userid' => 101, 'clusterid' => 2),
                        ),
                        101,
                        array(
                                $this->get_search_result_row($this->resultscsv, 4),
                        ),
                        1,
                ),
                // 5: Test when permission is assigned on the parent userset and user is assigned to parent userset, only child
                // usersets are returned.
                array(
                        array(2),
                        array(
                                array('userid' => 101, 'clusterid' => 2),
                        ),
                        101,
                        array(
                                $this->get_search_result_row($this->resultscsv, 4),
                        ),
                        1,
                ),
                // 6: Test when permission is assigned on the parent userset and user is assigned to grandparent userset, only
                // usersets from the parent and below are returned.
                array(
                        array(2),
                        array(
                                array('userid' => 101, 'clusterid' => 1),
                        ),
                        101,
                        array(
                            $this->get_search_result_row($this->resultscsv, 2),
                            $this->get_search_result_row($this->resultscsv, 4),
                        ),
                        2,
                ),
                // 7: Test permission on multiple usersets only returns usersets the user is associated with.
                array(
                        array(2, 5),
                        array(
                                array('userid' => 101, 'clusterid' => 2),
                        ),
                        101,
                        array(
                                $this->get_search_result_row($this->resultscsv, 4),
                        ),
                        1,
                ),
                // 8: Test permission on multiple usersets with associations to multiple usersets returns all available children.
                array(
                        array(2, 5),
                        array(
                                array('userid' => 101, 'clusterid' => 2),
                                array('userid' => 101, 'clusterid' => 5),
                        ),
                        101,
                        array(
                                $this->get_search_result_row($this->resultscsv, 4),
                                $this->get_search_result_row($this->resultscsv, 6),
                        ),
                        2,
                ),
        );
    }

    /**
     * Test available table shows only usersets the user has permission to enrol into based on
     * elis/program:userset_enrol_userset_user permission on a parent userset.
     *
     * @dataProvider dataprovider_available_permissions_userset_enrol_userset_user
     * @param array $usersetidsforperm An array of userset IDs to assign the elis/program:userset_enrol_userset_user on.
     * @param array $clusterassignments An array of arrays of parameters to construct clusterassignments with.
     * @param int $tableuserid The id of the user to manage associations for.
     * @param array $expectedresults The expected page of results.
     * @param int $expectedtotal The expected number of total results.
     */
    public function test_available_permissions_userset_enrol_userset_user($usersetidsforperm, $clusterassignments,
                                                                        $tableuserid, $expectedresults, $expectedtotal) {
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
        $table = new deepsight_datatable_useruserset_available_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_userid($tableuserid);

        // Perform test.
        $actualresults = $table->get_search_results(array(), array(), 0, 20);

        // Verify.
        $this->assert_search_results($expectedresults, $expectedtotal, $actualresults);
    }
}