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

require_once(elispm::lib('data/clustercurriculum.class.php'));
require_once(elispm::lib('data/curriculum.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/userset.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));

/**
 * Mock programuserset_assigned datatable class to expose protected methods and properties.
 */
class deepsight_datatable_programuserset_assigned_mock extends deepsight_datatable_programuserset_assigned {

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
 * Mock programuserset_available datatable class to expose protected methods and properties.
 */
class deepsight_datatable_programuserset_available_mock extends deepsight_datatable_programuserset_available {

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
 * Tests programuserset datatable functions.
 */
class deepsight_datatable_programuserset_test extends deepsight_datatable_searchresults_test {
    public $resultscsv = 'csv_userset.csv';

    /**
     * Return overlay tables.
     * @return array An array of overlay tables.
     */
    protected static function get_overlay_tables() {
        $overlay = array(
            clustercurriculum::TABLE => 'elis_program',
            curriculum::TABLE => 'elis_program',
            userset::TABLE => 'elis_program',
            field::TABLE => 'elis_core'
        );
        return array_merge(parent::get_overlay_tables(), $overlay);
    }

    /**
     * Do any setup before tests that rely on data in the database - i.e. create users/courses/classes/etc or import csvs.
     */
    protected function set_up_tables() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(curriculum::TABLE, elispm::lib('deepsight/phpunit/csv_program.csv'));
        $dataset->addTable(userset::TABLE, elispm::lib('deepsight/phpunit/csv_userset.csv'));
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
            'id' => $element['id'],
            'meta' => array(
                'label' => $element['name']
            ),
            'autoenroldefault' => 0,
        );
    }

    /**
     * Dataprovider for test_assigned_shows_assigned_usersets.
     * @return array Array of test parameters.
     */
    public function dataprovider_assigned_shows_assigned_usersets() {
        return array(
                // 0: Test table shows nothing when no associations present.
                array(
                        array(),
                        2,
                        array(),
                        0,
                ),
                // 1: Test table shows nothing when no associations present for current program.
                array(
                        array(
                                array('curriculumid' => 5, 'clusterid' => 2),
                        ),
                        6,
                        array(),
                        0,
                ),
                // 2: Test table shows existing associations for the current program.
                array(
                        array(
                                array('curriculumid' => 5, 'clusterid' => 2),
                        ),
                        5,
                        array(
                                $this->get_search_result_row($this->resultscsv, 2),
                        ),
                        1,
                ),
                // 3: Test table shows multiple existing associations for the current program.
                array(
                        array(
                                array('curriculumid' => 5, 'clusterid' => 1),
                                array('curriculumid' => 5, 'clusterid' => 2),
                        ),
                        5,
                        array(
                                $this->get_search_result_row($this->resultscsv, 1),
                                $this->get_search_result_row($this->resultscsv, 2),
                        ),
                        2,
                ),
                // 4: Test table shows multiple existing associations for the current program and associations for other programs
                // don't appear.
                array(
                        array(
                                array('curriculumid' => 5, 'clusterid' => 1),
                                array('curriculumid' => 5, 'clusterid' => 2),
                                array('curriculumid' => 6, 'clusterid' => 3),
                                array('curriculumid' => 6, 'clusterid' => 4),
                        ),
                        6,
                        array(
                                $this->get_search_result_row($this->resultscsv, 3),
                                $this->get_search_result_row($this->resultscsv, 4),
                        ),
                        2,
                ),
        );
    }

    /**
     * Test assigned table shows assigned usersets.
     *
     * @dataProvider dataprovider_assigned_shows_assigned_usersets
     * @param array $associations An array of arrays of parameters to construct clustercurriculum associations.
     * @param int $tableprogramid The ID of the program we're going to manage.
     * @param array $expectedresults The expected page of results.
     * @param int $expectedtotal The expected number of total results.
     */
    public function test_assigned_shows_assigned_usersets($associations, $tableprogramid, $expectedresults, $expectedtotal) {
        global $DB;

        foreach ($associations as $association) {
            $clustercurriculum = new clustercurriculum($association);
            $clustercurriculum->save();
        }

        $table = new deepsight_datatable_programuserset_assigned_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_programid($tableprogramid);

        $actualresults = $table->get_search_results(array(), array(), 0, 20);
        foreach ($expectedresults as &$result) {
            unset($result['autoenroldefault']);
        }
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
        $this->give_permission_for_context($USER->id, 'elis/program:associate', get_context_instance(CONTEXT_SYSTEM));

        // Construct test table.
        $table = new deepsight_datatable_programuserset_available_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_programid(5);

        // Perform test.
        $actualresults = $table->get_search_results(array(), array(), 0, 20);

        // Verify result.
        $expectedresults = array(
                $this->get_search_result_row($this->resultscsv, 1),
                $this->get_search_result_row($this->resultscsv, 2),
                $this->get_search_result_row($this->resultscsv, 3),
                $this->get_search_result_row($this->resultscsv, 4),
                $this->get_search_result_row($this->resultscsv, 5),
        );
        $expectedtotal = 5;
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
                        100,
                        array(
                                $this->get_search_result_row($this->resultscsv, 1),
                                $this->get_search_result_row($this->resultscsv, 2),
                                $this->get_search_result_row($this->resultscsv, 3),
                                $this->get_search_result_row($this->resultscsv, 4),
                                $this->get_search_result_row($this->resultscsv, 5),
                        ),
                        5
                ),
                // Test the table doesn't show assigned usersets.
                array(
                        array(
                                array('curriculumid' => 6, 'clusterid' => 2),
                        ),
                        6,
                        array(
                                $this->get_search_result_row($this->resultscsv, 1),
                                $this->get_search_result_row($this->resultscsv, 3),
                                $this->get_search_result_row($this->resultscsv, 4),
                                $this->get_search_result_row($this->resultscsv, 5),
                        ),
                        4
                ),
                // Test multiple assignments.
                array(
                        array(
                                array('curriculumid' => 6, 'clusterid' => 3),
                                array('curriculumid' => 6, 'clusterid' => 4),
                        ),
                        6,
                        array(
                                $this->get_search_result_row($this->resultscsv, 1),
                                $this->get_search_result_row($this->resultscsv, 2),
                                $this->get_search_result_row($this->resultscsv, 5),
                        ),
                        3
                ),
                // Test only assignments for the current userset affect results.
                array(
                        array(
                                array('curriculumid' => 5, 'clusterid' => 1),
                                array('curriculumid' => 6, 'clusterid' => 2),
                                array('curriculumid' => 6, 'clusterid' => 3),
                        ),
                        6,
                        array(
                                $this->get_search_result_row($this->resultscsv, 1),
                                $this->get_search_result_row($this->resultscsv, 4),
                                $this->get_search_result_row($this->resultscsv, 5),
                        ),
                        3
                ),
        );
    }

    /**
     * Test available table doesn't show assigned usersets.
     * @dataProvider dataprovider_available_doesnt_show_assigned_usersets
     * @param array $associations An array of arrays of parameters to construct clustercurriculum associations.
     * @param int $tableprogramid The ID of the program we're going to manage.
     * @param array $expectedresults The expected page of results.
     * @param int $expectedtotal The expected number of total results.
     */
    public function test_available_doesnt_show_assigned_usersets($associations, $tableprogramid, $expectedresults, $expectedtotal) {
        global $USER, $DB, $CFG;
        $userbackup = $USER;

        // Set up permissions.
        $USER = $this->setup_permissions_test();
        $this->give_permission_for_context($USER->id, 'elis/program:associate', get_context_instance(CONTEXT_SYSTEM));

        foreach ($associations as $association) {
            $clustercurriculum = new clustercurriculum($association);
            $clustercurriculum->save();
        }

        // Construct test table.
        $table = new deepsight_datatable_programuserset_available_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_programid($tableprogramid);

        // Perform test.
        $actualresults = $table->get_search_results(array(), array(), 0, 20);

        // Verify result.
        $this->assert_search_results($expectedresults, $expectedtotal, $actualresults);

        // Restore user.
        $USER = $userbackup;
    }

    /**
     * Dataprovider for test_available_permissions_associate.
     * @return array Array of test parameters.
     */
    public function dataprovider_available_permissions_associate() {
        return array(
                // 0: Test no permissons results in no results.
                array(
                        array(),
                        1,
                        array(),
                        0,
                ),
                // 1: Test permissions on the system level results in all results.
                array(
                        array('system' => true),
                        5,
                        array(
                                $this->get_search_result_row($this->resultscsv, 1),
                                $this->get_search_result_row($this->resultscsv, 2),
                                $this->get_search_result_row($this->resultscsv, 3),
                                $this->get_search_result_row($this->resultscsv, 4),
                                $this->get_search_result_row($this->resultscsv, 5),
                        ),
                        5,
                ),
                // 2: Test permissions on one userset returns that userset.
                array(
                        array('userset' => array(1)),
                        6,
                        array(
                                $this->get_search_result_row($this->resultscsv, 1),
                        ),
                        1,
                ),
                // 3: Test permissions on multiple usersets returns those userset.
                array(
                        array('userset' => array(1, 3)),
                        6,
                        array(
                                $this->get_search_result_row($this->resultscsv, 1),
                                $this->get_search_result_row($this->resultscsv, 3),
                        ),
                        2,
                ),
        );
    }

    /**
     * Test available table only shows usersets that the assigner has the elis/program::associate permission on.
     * @dataProvider dataprovider_available_permissions_associate
     * @param array $contextstoassign An array of information specifying the contexts to assign the associate permission on.
     *                                This is formatted like array('system' => true, 'userset' => array(1, 2, 3))
     * @param int $tableprogramid The ID of the program we're going to manage.
     * @param array $expectedresults The expected page of results.
     * @param int $expectedtotal The expected number of total results.
     */
    public function test_available_permissions_associate($contextstoassign, $tableprogramid, $expectedresults, $expectedtotal) {
        global $USER, $DB, $CFG;
        $userbackup = $USER;

        // Set up permissions.
        $USER = $this->setup_permissions_test();

        // Set up capabilities.
        foreach ($contextstoassign as $contexttype => $ids) {
            if ($contexttype === 'system') {
                $this->give_permission_for_context($USER->id, 'elis/program:associate', get_context_instance(CONTEXT_SYSTEM));
            } else {
                foreach ($ids as $contextinstanceid) {
                    switch($contexttype) {
                        case 'userset':
                            $context = context_elis_userset::instance($contextinstanceid);
                            break;
                    }
                    $this->give_permission_for_context($USER->id, 'elis/program:associate', $context);
                }
            }
        }

        accesslib_clear_all_caches(true);

        // Construct test table.
        $table = new deepsight_datatable_programuserset_available_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_programid($tableprogramid);

        // Perform test.
        $actualresults = $table->get_search_results(array(), array(), 0, 20);

        // Verify result.
        $this->assert_search_results($expectedresults, $expectedtotal, $actualresults);

        // Restore user.
        $USER = $userbackup;
    }
}
