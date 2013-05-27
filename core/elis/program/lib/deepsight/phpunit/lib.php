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

require(dirname(__FILE__).'/../lib/lib.php');
require_once(elispm::lib('deepsightpage.class.php'));
require_once(elispm::lib('selectionpage.class.php'));
require_once(elispm::lib('data/clusterassignment.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/userset.class.php'));
require_once(elispm::lib('data/usertrack.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));

/**
 * Base unit test class providing useful functions to test the search results of a datatable.
 */
abstract class deepsight_datatable_searchresults_test extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

    /**
     * Do any setup before tests that rely on data in the database - i.e. create users/courses/classes/etc or import csvs.
     */
    abstract protected function set_up_tables();

    /**
     * Transform an element from a csv into a search result array.
     * @return array A single search result array.
     */
    abstract protected function create_search_result_from_csvelement($element);

    /**
     * Return overlay tables.
     * @return array An array of overlay tables.
     */
    protected static function get_overlay_tables() {
        return array(
            'cache_flags' => 'moodle',
            'config' => 'moodle',
            'context' => 'moodle',
            'role' => 'moodle',
            'role_assignments' => 'moodle',
            'role_capabilities' => 'moodle',
            'user' => 'moodle',
            user::TABLE => 'elis_program',
            usermoodle::TABLE => 'elis_program',
            usertrack::TABLE => 'elis_program',
        );
    }

    /**
     * Perform setup before every test.
     */
    protected function setUp() {
        parent::setUp();
        $this->set_up_tables();
    }

    /**
     * Perform all assertions to verify search results.
     * @param array $expectedresults The array of expected results.
     * @param int $expectedtotal The expect total number of results.
     * @param array $actualresults The direct return value of a $table->get_search_results() call.
     */
    protected function assert_search_results($expectedresults, $expectedtotal, $actualresults) {
        // Assert general structure of return val.
        $this->assertInternalType('array', $actualresults);
        $this->assertEquals(array(0, 1), array_keys($actualresults));
        $this->assertInternalType('array', $actualresults[0]);
        $this->assertInternalType('int', $actualresults[1]);

        // Assert search results.
        $this->assertEquals($expectedresults, $actualresults[0]);

        // Assert total count.
        $this->assertEquals($expectedtotal, $actualresults[1]);
    }

    /**
     * Give a user a permission at a context.
     * @param int $userid The ID of the user to give the permission to.
     * @param string $permission The permission to assign.
     * @param object $context The context object to assign the permission at.
     */
    protected function give_permission_for_context($userid, $permission, $context) {
        $roleid = create_role('userrole'.uniqid(), 'userrole'.uniqid(), 'userrole'.uniqid());
        assign_capability($permission, CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $userid, $context->id);
    }

    /**
     * Perform necessary functions to start a test involving roles and permissions.
     * @return object A user that can be used as $USER for testing permissions.
     */
    protected function setup_permissions_test() {
        global $DB, $USER;

        accesslib_clear_all_caches(true);

        set_config('siteguest', '');
        set_config('siteadmins', '');

        // Insert base contexts.
        $sql = "INSERT INTO {context} SELECT * FROM ".self::$origdb->get_prefix()."context WHERE contextlevel = ?";
        $params = array(CONTEXT_SYSTEM);
        $DB->execute($sql, $params);
        $syscontext = get_context_instance(CONTEXT_SYSTEM);

        // Create moodle user.
        $assigninguserdata = array(
            'idnumber' => 'assigninguser',
            'username' => 'assigninguser',
            'firstname' => 'assigninguser',
            'lastname' => 'assigninguser',
            'email' => 'assigninguser@testuserdomain.com',
            'country' => 'CA'
        );
        $assigninguser = new user($assigninguserdata);
        $assigninguser->save();

        return $DB->get_record('user', array('username' => 'assigninguser'));
    }

    /**
     * The the search result array for a given element id.
     * This is useful for generating expected search results.
     * @param string $csv The CSV file to pull information from.
     * @param int $elementid The ID of the element to get the information for.
     * @return array The expected search result array for the given id.
     */
    protected function get_search_result_row($csv, $elementid) {
        static $results = array();

        if (empty($results[$csv])) {
            // Parse the csv to get information and create element arrays, indexed by element id.
            $csvdata = file_get_contents(dirname(__FILE__).'/'.$csv);
            $csvdata = explode("\n", $csvdata);
            $keys = explode(',', $csvdata[0]);
            $lines = count($csvdata);
            $csvelements = array();
            for ($i=1; $i<$lines; $i++) {
                $curele = explode(',', $csvdata[$i]);
                $csvelements[$curele[0]] = array_combine($keys, $curele);
            }
            unset($csvdata, $keys);

            // Create search result arrays, indexed by element id.
            $results[$csv] = array();
            foreach ($csvelements as $id => $element) {
                $results[$csv][$id] = $this->create_search_result_from_csvelement($element);
            }
        }

        return (isset($results[$csv][$elementid])) ? $results[$csv][$elementid] : array();
    }
}

/**
 * Test an implementation of the deepsight_datatable_standard class.
 */
abstract class deepsight_datatable_standard_implementation_test extends elis_database_test {

    /**
     * Do any setup before tests that rely on data in the database - i.e. create users/courses/classes/etc or import csvs.
     */
    abstract protected function set_up_tables();

    /**
     * Construct the datatable we're testing.
     *
     * @return deepsight_datatable The deepsight_datatable object we're testing.
     */
    abstract protected function get_test_table();

    /**
     * Dataprovider for test_bulklist_get_display.
     * Should return an array of argument arrays for the test_bulklist_get_display function.
     * See test_bulklist_get_display for the parameters.
     *
     * @return array The array of argument arrays.
     */
    abstract public function dataprovider_bulklist_get_display();

    /**
     * Dataprovider for test_get_search_results.
     * Should return an array of argument arrays for the get_search_results function.
     * See test_get_search_results for the parameters.
     *
     * @return array The array of argument arrays.
     */
    abstract public function dataprovider_get_search_results();

    /**
     * Test get_filters
     *
     * Test function return is an array and contans only deepsight_filter objects
     */
    public function test_get_filters() {
        global $DB;

        // Test call.
        $table = $this->get_test_table();
        $filters = $table->get_filters();

        // Verify.
        $this->assertInternalType('array', $filters);
        if (!empty($filters)) {
            foreach ($filters as $filter) {
                $this->assertTrue($filter instanceof deepsight_filter);
            }
        }
    }

    /**
     * Test get_initial_filters.
     *
     * Test that the return is an array containing name properties of filters from get_filters().
     */
    public function test_get_initial_filters() {
        // Test call.
        $table = $this->get_test_table();
        $initialfilters = $table->get_initial_filters();

        // Verify.
        $this->assertInternalType('array', $initialfilters);
        if (!empty($initialfilters)) {
            foreach ($initialfilters as $name) {
                $this->assertTrue(isset($table->available_filters[$name]));
            }
        }
    }

    /**
     * Test get_fixed_columns()
     */
    public function test_get_fixed_columns() {
        // Test call.
        $table = $this->get_test_table();
        $fixedcolumns = $table->get_fixed_columns();

        // Verify.
        $this->assertInternalType('array', $fixedcolumns);
        $this->assertNotEmpty($fixedcolumns);
    }

    /**
     * Test get_actions()
     *
     * Test function return is an array and contans only deepsight_filter objects
     */
    public function test_get_actions() {
        global $DB;

        // Setup.
        $table = $this->get_test_table();

        // Test Call.
        $actions = $table->get_actions();

        // Verify.
        $this->assertInternalType('array', $actions);
        if (!empty($actions)) {
            foreach ($actions as $action) {
                $this->assertTrue($action instanceof deepsight_action);
            }
        }
    }

    /**
     * Test bulklist_get_display.
     *
     * @dataProvider dataprovider_bulklist_get_display
     * @param array $bulklistids Array of ids to pass to bulklist_get_display.
     * @param array $expectedpageresults Array of expected page results.
     * @param int $expectedtotalresults Expected number of total results.
     */
    public function test_bulklist_get_display($bulklistids, $expectedpageresults, $expectedtotalresults) {
        global $SESSION;

        // Set up.
        $this->set_up_tables();
        $table = $this->get_test_table();
        $sessparam = $table->get_bulklist_sess_param();
        $SESSION->$sessparam = $bulklistids;

        // Test call.
        list($pageresults, $numtotal) = $table->bulklist_get_display();

        // Assert page results.
        $this->assertInternalType('array', $pageresults);
        $this->assertEquals($expectedpageresults, $pageresults);

        // Assert total count.
        $this->assertInternalType('int', $numtotal);
        $this->assertEquals($expectedtotalresults, $numtotal);

        $SESSION->$sessparam = array();
    }

    /**
     * Test getting search results.
     *
     * @dataProvider dataprovider_get_search_results
     * @param array $filters An array of filter data.
     * @param array $sort An array of field=>direction to specify sorting for the results.
     * @param int $limitfrom The position in the dataset from which to start returning results.
     * @param int $limitnum The amount of results to return.
     * @param array $expectedresults The expected search results.
     * @param int $expectedtotal The expected total dataset size.
     */
    public function test_get_search_results(array $filters, array $sort, $limitfrom, $limitnum, $expectedresults, $expectedtotal) {
        // Setup.
        $this->set_up_tables();
        $table = $this->get_test_table();

        // Test call.
        $actualresults = $table->get_search_results($filters, $sort, $limitfrom, $limitnum);

        // Assert general structure of return val.
        $this->assertInternalType('array', $actualresults);
        $this->assertEquals(array(0, 1), array_keys($actualresults));
        $this->assertInternalType('array', $actualresults[0]);
        $this->assertInternalType('int', $actualresults[1]);

        // Assert search results.
        $this->assertEquals($expectedresults, $actualresults[0]);

        // Assert total count.
        $this->assertEquals($expectedtotal, $actualresults[1]);
    }
}

/**
 * Mock filter object
 */
class deepsight_filter_mock extends deepsight_filter_standard {
    /**
     * Provide part of a WHERE clause to the datatable to affect the results.
     *
     * The aggregate of all filter's get_filter_sql() sql will be joined together with AND and used to filter to entire dataset.
     *
     * @param mixed $data The data from the filter send from the javascript.
     * @return array An array consisting of filter sql as index 0, and an array of parameters as index 1
     */
    public function get_filter_sql($data) {
        reset($this->fields);
        $field = key($this->fields);
        return array($field.' = ?', array($data));
    }

    /**
     * Magic function to expose protected properties.
     *
     * @param string $name The name of the property
     * @return string|int|bool The value of the property
     */
    public function __get($name) {
        return (isset($this->$name)) ? $this->$name : false;
    }

    /**
     * Magic function to expose protected properties.
     *
     * @param string $name The name of the property
     * @return string|int|bool The value of the property
     */
    public function __isset($name) {
        return (isset($this->$name)) ? true : false;
    }

    /**
     * Function that is run by the datatable when it receives a request aimed at this filter.
     *
     * @return string Response.
     */
    public function respond_to_js() {
        return 'success';
    }
}

/**
 * Mock action object
 */
class deepsight_action_mock extends deepsight_action_standard {
    /**
     * Perform action-specific tasks.
     *
     * Doesn't actually do anything here.
     *
     * @param array $elements    An array of elements to perform the action on. Although the values will differ, the indexes
     *                           will always be element IDs.
     * @param bool  $bulk_action Whether this is a bulk-action or not.
     * @return array An array to format as JSON and return to the Javascript.
     */
    protected function _respond_to_js(array $elements, $bulk_action) {
        return array('result' => 'success');
    }
}

/**
 * Mock datatable object
 */
class deepsight_datatable_mock extends deepsight_datatable_standard {

    /**
     * Gets an array of available filters.
     *
     * Adds some mock filters to the table.
     *
     * @return array An array of deepsight_filter objects that will be available.
     */
    protected function get_filters() {
        return array(
            new deepsight_filter_mock($this->DB, 'testfilter1', 'Test Filter', array('name' => 'Name')),
            new deepsight_filter_mock($this->DB, 'testfilter2', 'Test Filter 2', array('city' => 'City')),
            'invalid filter entry'
        );
    }

    /**
     * Respond to a "mock" request.
     */
    protected function respond_mock() {
        echo 'Success';
    }

    /**
     * Gets an array of initial filters.
     *
     * @return array An array of deepsight_filter $name properties that will be present when the user first loads the page.
     */
    protected function get_initial_filters() {
        return array('testfilter1');
    }

    /**
     * Get an array of columns that will always be present.
     *
     * @return array An array of fixed columns formatted like [table-aliased field name (i.e. element.id)]=>[column label]
     */
    protected function get_fixed_columns() {
        return array('testcolumn' => 'Test Column');
    }

    /**
     * Gets an array of actions that can be used on the elements of the datatable.
     *
     * @return array An array of deepsight_action objects that will be available for each element.
     */
    public function get_actions() {
        return array(
            new deepsight_action_mock($this->DB, 'testaction'),
            'invalid action entry'
        );
    }

    /**
     * Gets a page of elements from the bulklist for display.
     *
     * @param array $ids An array of IDs to get information for.
     * @return array An array of information for the requested IDs. Contains labels indexed by IDs.
     */
    protected function bulklist_get_info_for_ids(array $ids = array()) {
        if (empty($ids)) {
            return array();
        }

        $sql = 'SELECT id, firstname, lastname FROM {crlm_user} WHERE id IN ('.implode(', ', array_fill(0, count($ids), '?')).')';
        $results = $this->DB->get_recordset_sql($sql, $ids);
        $pageresults = array_flip($ids);
        foreach ($results as $result) {
            $pageresults[$result->id] = fullname($result);
        }

        return $pageresults;
    }

    /**
     * Gets search results for the datatable.
     *
     * @param array   $filters    The filter array received from js. It is an array consisting of filtername=>data, and can be
     *                            passed directly to $this->get_filter_sql() to generate the required WHERE sql.
     * @param array   $sort       An array of field=>direction to specify sorting for the results.
     * @param int $limit_from The position in the dataset from which to start returning results.
     * @param int $limit_num  The amount of results to return.
     * @return array An array with the first value being a page of results, and the second value being the total number of results
     */
    public function get_search_results(array $filters, array $sort = array(), $limitfrom=null, $limitnum=null) {
        $selectfields = $this->get_select_fields($filters);
        $joinsql = implode(' ', $this->get_join_sql($filters));
        list($filtersql, $filterparams) = $this->get_filter_sql($filters);
        $sortsql = $this->get_sort_sql($sort);

        // Get the number of results in the full dataset.
        $query = 'SELECT count(1) as count FROM {crlm_user} element '.$joinsql.' '.$filtersql;
        $results = $this->DB->get_record_sql($query, $filterparams);
        $totalresults = $results->count;

        // Generate and execute query for a single page of results.
        $query = 'SELECT '.implode(', ', $selectfields).' FROM {crlm_user} element '.$joinsql.' '.$filtersql.' '.$sortsql;
        $results = $this->DB->get_recordset_sql($query, $filterparams, 0, 20);

        return array($results, $totalresults);
    }

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