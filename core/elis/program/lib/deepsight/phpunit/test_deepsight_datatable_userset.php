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
 * Mock userset datatable class exposing protected methods and properties
 */
class deepsight_datatable_userset_mock extends deepsight_datatable_userset {

    /**
     * Magic function to expose protected properties
     * @param string $name The name of the property
     * @return string|int|bool The value of the property
     */
    public function __get($name) {
        return (isset($this->$name)) ? $this->$name : false;
    }

    /**
     * Magic function to expose protected properties
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
 * Tests the base userset datatable class.
 */
class deepsight_datatable_userset_test extends deepsight_datatable_standard_implementation_test {
    protected $backupGlobalsBlacklist = array('DB');

    /**
     * Return overlay tables.
     *
     * @return array An array of overlay tables.
     */
    protected static function get_overlay_tables() {
        return array(
            'crlm_cluster' => 'elis_program'
        );
    }

    /**
     * Construct the datatable we're testing.
     *
     * @return deepsight_datatable The deepsight_datatable object we're testing.
     */
    protected function get_test_table() {
        global $DB;
        return new deepsight_datatable_userset_mock($DB, 'test', '', 'testuniqid');
    }

    /**
     * Do any setup before tests that rely on data in the database - i.e. create users/courses/classes/etc or import csvs.
     */
    protected function set_up_tables() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(userset::TABLE, elispm::lib('deepsight/phpunit/csv_userset.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    /**
     * Dataprovider for test_bulklist_get_display.
     *
     * @return array The array of argument arrays.
     */
    public function dataprovider_bulklist_get_display() {
        return array(
            array(array(1, 2), array(2 => 'Test Userset 2', 1 => 'Test Userset 1'), 2)
        );
    }

    /**
     * Dataprovider for test_get_search_results()
     *
     * @return array The array of argument arrays.
     */
    public function dataprovider_get_search_results() {
        $usersets = array(
            1 => 'Test Userset 1',
            2 => 'Test Userset 2',
            3 => 'Test Userset 3',
            4 => 'Test Userset 4',
            5 => 'Test Userset 5'
        );

        $usersetresults = array();
        foreach ($usersets as $id => $name) {
            $usersetresults[$id] = array(
                'element_id' => $id,
                'element_name' => $name,
                'id' => $id,
                'meta' => array(
                    'label' => $name
                )
            );
        }

        return array(
            // Test Default.
            array(
                array(),
                array('element.name' => 'ASC'),
                0,
                20,
                array($usersetresults[1], $usersetresults[2], $usersetresults[3], $usersetresults[4], $usersetresults[5]),
                5
            ),
            // Test Sorting.
            array(
                array(),
                array('element.name' => 'DESC'),
                0,
                20,
                array($usersetresults[5], $usersetresults[4], $usersetresults[3], $usersetresults[2], $usersetresults[1]),
                5
            ),
            // Test Basic Searching.
            array(
                array('name' => array('Test Userset 1')),
                array('element.name' => 'DESC'),
                0,
                20,
                array($usersetresults[1]),
                1
            ),
            // Test limited page results.
            array(
                array(),
                array('element.name' => 'ASC'),
                0,
                2,
                array($usersetresults[1], $usersetresults[2]),
                5
            ),
        );
    }
}