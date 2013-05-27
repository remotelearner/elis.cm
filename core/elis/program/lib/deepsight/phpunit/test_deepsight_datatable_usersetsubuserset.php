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

require_once(elispm::lib('data/userset.class.php'));

/**
 * Mock usersetsubuserset_assigned datatable class to expose protected methods and properties.
 */
class deepsight_datatable_usersetsubuserset_assigned_mock extends deepsight_datatable_usersetsubuserset_assigned {
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
 * Mock usersetsubuserset_available datatable class to expose protected methods and properties.
 */
class deepsight_datatable_usersetsubuserset_available_mock extends deepsight_datatable_usersetsubuserset_available {
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
 * Tests usersetsubuserset datatable functions.
 */
class deepsight_datatable_usersetsubuserset_test extends deepsight_datatable_searchresults_test {
    public $resultscsv = 'csv_usersetwithsubsets.csv';

    /**
     * Return overlay tables.
     * @return array An array of overlay tables.
     */
    protected static function get_overlay_tables() {
        $overlay = array(
            field::TABLE => 'elis_core',
            userset::TABLE => 'elis_program',
        );
        return array_merge(parent::get_overlay_tables(), $overlay);
    }

    /**
     * Do any setup before tests that rely on data in the database - i.e. create users/courses/classes/etc or import csvs.
     * Structure for csv_usersetwithsubsets.csv
     *   1      3
     * +-+-+
     * 2   5
     * |   |
     * 4   6
     */
    protected function set_up_tables() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
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
            ),
        );
    }

    /**
     * Dataprovider for test_assigned_shows_correct_subsets.
     * @return array Array of test parameters.
     */
    public function dataprovider_assigned_shows_correct_subsets() {
        return array(
                // 0: Test table shows no results when there are no subsets.
                array(
                        array('system' => true),
                        3,
                        array(),
                ),
                // 1: Test table shows correct subsets.
                array(
                        array('system' => true),
                        1,
                        array(
                                $this->get_search_result_row($this->resultscsv, 2),
                                $this->get_search_result_row($this->resultscsv, 5),
                        ),
                ),
                // 2: Test table shows correct subsets.
                array(
                        array('system' => true),
                        2,
                        array(
                                $this->get_search_result_row($this->resultscsv, 4),
                        ),
                ),
                // 3: Test table shows correct subsets.
                array(
                        array('system' => true),
                        5,
                        array(
                                $this->get_search_result_row($this->resultscsv, 6),
                        ),
                ),
                // 4: Test table shows only subsets user has elis/program:userset_view permission on.
                array(
                        array('userset' => array(2, 3)),
                        1,
                        array(
                                $this->get_search_result_row($this->resultscsv, 2),
                        ),
                ),
        );
    }

    /**
     * Performs the following tests for the assigned list
     *   - Shows nothing when no usersets with parent exist.
     *   - Shows correct subsets.
     *   - Shows only subsets user has elis/program:userset_view permission on.
     *
     * @dataProvider dataprovider_assigned_shows_correct_subsets
     * @param array $permissions An array of information specifying the contexts to assign the associate permission on.
     *                           This is formatted like array('system' => true, 'userset' => array(1, 2, 3))
     * @param int $tableusersetid The ID of the userset we're going to manage.
     * @param array $expectedresults The expected page of results.
     */
    public function test_assigned_shows_correct_subsets($permissions, $tableusersetid, $expectedresults) {
        global $USER, $DB, $CFG;
        $userbackup = $USER;

        // Set up permissions.
        $USER = $this->setup_permissions_test();

        // Set up permissions.
        $perm = 'elis/program:userset_view';
        foreach ($permissions as $contexttype => $ids) {
            if ($contexttype === 'system') {
                $this->give_permission_for_context($USER->id, $perm, get_context_instance(CONTEXT_SYSTEM));
            } else {
                foreach ($ids as $contextinstanceid) {
                    switch($contexttype) {
                        case 'userset':
                            $context = context_elis_userset::instance($contextinstanceid);
                            break;
                    }
                    $this->give_permission_for_context($USER->id, $perm, $context);
                }
            }
        }

        accesslib_clear_all_caches(true);

        // Construct test table.
        $table = new deepsight_datatable_usersetsubuserset_assigned_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_usersetid($tableusersetid);

        // Perform test.
        $actualresults = $table->get_search_results(array(), array(), 0, 20);

        // Verify result.
        $this->assert_search_results($expectedresults, count($expectedresults), $actualresults);

        // Restore user.
        $USER = $userbackup;
    }

    /**
     * Dataprovider for test_available_shows_correct_usersets.
     * @return array Array of test parameters.
     */
    public function dataprovider_available_shows_correct_usersets() {
        return array(
                // 0: Doesn't show child subsets, Shows grandchild and beyond subsets, Doesn't show current userset.
                array(
                        array('system' => true),
                        1,
                        array(
                            $this->get_search_result_row($this->resultscsv, 3),
                            $this->get_search_result_row($this->resultscsv, 4),
                            $this->get_search_result_row($this->resultscsv, 6),
                        ),
                ),
                // 1: Doesn't show ancestor usersets, Doesn't show current userset.
                array(
                        array('system' => true),
                        2,
                        array(
                            $this->get_search_result_row($this->resultscsv, 3),
                            $this->get_search_result_row($this->resultscsv, 5),
                            $this->get_search_result_row($this->resultscsv, 6),
                        ),
                ),
                // 2: Shows sibling + sibling decendants.
                array(
                        array('system' => true),
                        3,
                        array(
                            $this->get_search_result_row($this->resultscsv, 1),
                            $this->get_search_result_row($this->resultscsv, 2),
                            $this->get_search_result_row($this->resultscsv, 4),
                            $this->get_search_result_row($this->resultscsv, 5),
                            $this->get_search_result_row($this->resultscsv, 6),
                        ),
                ),
                // 3: Only shows usersets which the current user has elis/program:userset_edit permissions on.
                array(
                        array('userset' => array(2, 6)),
                        3,
                        array(
                            $this->get_search_result_row($this->resultscsv, 2),
                            $this->get_search_result_row($this->resultscsv, 4),
                            $this->get_search_result_row($this->resultscsv, 6),
                        ),
                ),
        );
    }

    /**
     * Performs the following tests for the available list
     *   - Doesn't show child subsets.
     *   - DOES show grandchild and beyond subsets.
     *   - Doesn't show ancestor usersets.
     *   - Doesn't show current userset.
     *   - Only shows usersets which the current user has elis/program:userset_edit permissions on.
     *
     * @dataProvider dataprovider_available_shows_correct_usersets
     * @param array $permissions An array of information specifying the contexts to assign the associate permission on.
     *                           This is formatted like array('system' => true, 'userset' => array(1, 2, 3))
     * @param int $tableusersetid The ID of the userset we're going to manage.
     * @param array $expectedresults The expected page of results.
     */
    public function test_available_shows_correct_usersets($permissions, $tableusersetid, $expectedresults) {
        global $USER, $DB, $CFG;
        $userbackup = $USER;
        $_GET['id'] = $tableusersetid;
        // Set up permissions.
        $USER = $this->setup_permissions_test();

        // Set up contexts.
        for ($i = 1; $i <= 6; $i++) {
            $ctx = context_elis_userset::instance($i);
        }

        // Set up permissions.
        $perm = 'elis/program:userset_edit';
        foreach ($permissions as $contexttype => $ids) {
            if ($contexttype === 'system') {
                $this->give_permission_for_context($USER->id, $perm, get_context_instance(CONTEXT_SYSTEM));
            } else {
                foreach ($ids as $contextinstanceid) {
                    switch($contexttype) {
                        case 'userset':
                            $context = context_elis_userset::instance($contextinstanceid);
                            break;
                    }
                    $this->give_permission_for_context($USER->id, $perm, $context);
                }
            }
        }

        accesslib_clear_all_caches(true);

        // Construct test table.
        $table = new deepsight_datatable_usersetsubuserset_available_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_usersetid($tableusersetid);

        // Perform test.
        $actualresults = $table->get_search_results(array(), array(), 0, 20);

        // Verify result.
        $this->assert_search_results($expectedresults, count($expectedresults), $actualresults);

        // Restore user.
        $USER = $userbackup;
    }
}