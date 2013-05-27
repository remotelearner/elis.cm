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

/**
 * Tests datatable functions
 */
class deepsight_datatable_test extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

    /**
     * Return overlay tables.
     *
     * @return array An array of overlay tables.
     */
    protected static function get_overlay_tables() {
        return array(
            'context' => 'moodle',
            user::TABLE => 'elis_program',
            userset::TABLE => 'elis_program',
        );
    }

    /**
     * Dataprovider for test_get_userset_subsets.
     * @return array Array of test parameters.
     */
    public function dataprovider_get_userset_subsets() {
        return array(
                // Test userset with no children and includeparent off returns nothing.
                array(
                        3,
                        false,
                        array(),
                ),
                // Test userset with no children and includeparent on returns parent.
                array(
                        3,
                        true,
                        array(3),
                ),
                // Test userset with children and includeparent off returns children.
                array(
                        1,
                        false,
                        array(2, 4, 5, 6),
                ),
                // Test userset with children and includeparent on returns parent and descendants.
                array(
                        1,
                        true,
                        array(1, 2, 4, 5, 6),
                ),
        );
    }

    /**
     * Tests deepsight_datatable_usersetuser_base::get_userset_subsets
     * @dataProvider dataprovider_get_userset_subsets
     * @param int $parentuserset The ID of a userset to pass to the function as the parent userset ID.
     * @param bool $includeparent Whether to include the parent ID in the return array.
     * @param array $expectedresults The expected return value.
     */
    public function test_get_userset_subsets($parentuserset, $includeparent, $expectedresults) {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(user::TABLE, elispm::lib('deepsight/phpunit/csv_user.csv'));
        $dataset->addTable(userset::TABLE, elispm::lib('deepsight/phpunit/csv_usersetwithsubsets.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);

        accesslib_clear_all_caches(true);

        // Set up contexts.
        for ($i=1; $i<=6; $i++) {
            $ctx = context_elis_userset::instance($i);
        }

        accesslib_clear_all_caches(true);

        $actualresults = deepsight_datatable_standard::get_userset_subsets($parentuserset, $includeparent);
        $this->assertEquals($expectedresults, array_keys($actualresults));
    }

    /**
     * Test basic operations
     */
    public function test_basics() {
        global $DB;
        $name = 'datatable';
        $endpoint = 'test.php';

        $datatable = new deepsight_datatable_mock($DB, $name, $endpoint);

        // Assert correct assignment of basic attributes.
        $this->assertNotEmpty($datatable->DB);
        $this->assertEquals($name, $datatable->name);
        $this->assertEquals($endpoint, $datatable->endpoint);
        $this->assertNotEmpty($datatable->uniqid);

        // Assert correct population and cleaning of filters.
        $this->assertNotEmpty($datatable->available_filters);
        $this->assertInternalType('array', $datatable->available_filters);
        $this->assertEquals(2, count($datatable->available_filters));
        $this->assertArrayHasKey('testfilter1', $datatable->available_filters);
        $this->assertArrayHasKey('testfilter2', $datatable->available_filters);
        $this->assertTrue(($datatable->available_filters['testfilter1'] instanceof deepsight_filter));
        $this->assertTrue(($datatable->available_filters['testfilter2'] instanceof deepsight_filter));
        $this->assertFalse(in_array('invalid filter entry', $datatable->available_filters));

        // Assert correct assignment of initial filters and test columns.
        $this->assertEquals(array('testfilter1'), $datatable->initial_filters);
        $this->assertEquals(array('testcolumn' => 'Test Column'), $datatable->fixed_columns);

        // Assert correct population and cleaning of actions.
        $this->assertNotEmpty($datatable->actions);
        $this->assertInternalType('array', $datatable->actions);
        $this->assertEquals(1, count($datatable->actions));
        $this->assertArrayHasKey('testaction', $datatable->actions);
        $this->assertTrue(($datatable->actions['testaction'] instanceof deepsight_action));
        $this->assertFalse(in_array('invalid action entry', $datatable->actions));

        // Test getters.
        $this->assertEquals($name, $datatable->get_name());
        $this->assertEmpty($datatable->get_action('nonexistent'));
        $this->assertNotEmpty($datatable->get_action('testaction'));
        $this->assertTrue(($datatable->get_action('testaction') instanceof deepsight_action));
        $this->assertEmpty($datatable->get_filter('nonexistent'));
        $this->assertNotEmpty($datatable->get_filter('testfilter1'));
        $this->assertTrue(($datatable->get_filter('testfilter1') instanceof deepsight_filter));

        // Test options.
        $jsopts = $datatable->get_table_js_opts();
        $this->assertNotEmpty($jsopts);
        $this->assertInternalType('array', $jsopts);
        $this->assertArrayHasKey('dataurl', $jsopts);
        $this->assertArrayHasKey('uniqid', $jsopts);
        $this->assertArrayHasKey('initial_filters', $jsopts);
        $this->assertArrayHasKey('actions', $jsopts);
        $this->assertEquals($endpoint, $jsopts['dataurl']);
        $this->assertEquals($datatable->uniqid, $jsopts['uniqid']);
        $this->assertEquals(array('testfilter1'), $jsopts['initial_filters']);
        $this->assertNotEmpty($jsopts['actions']);
        $this->assertEquals(1, count($jsopts['actions']));

        // Test filterbar options.
        $filterbaropts = $datatable->get_filterbar_js_opts();
        $this->assertNotEmpty($filterbaropts);
        $this->assertInternalType('array', $filterbaropts);
        $this->assertEquals(2, count($filterbaropts));
        $this->assertEquals(array('testfilter1'), json_decode($filterbaropts[1]));
        $filterbarfilters = json_decode($filterbaropts[0], true);
        $this->assertNotEmpty($filterbarfilters);
        $this->assertInternalType('array', $filterbarfilters);
        $this->assertEquals(2, count($filterbarfilters));
        $this->assertArrayHasKey('testfilter1', $filterbarfilters);
        $this->assertNotEmpty($filterbarfilters['testfilter1']);
        $this->assertInternalType('array', $filterbarfilters['testfilter1']);
        $this->assertEquals(2, count($filterbarfilters['testfilter1']));
        $this->assertArrayHasKey('type', $filterbarfilters['testfilter1']);
        $this->assertArrayHasKey('opts', $filterbarfilters['testfilter1']);
    }

    /**
     * Test AJAX responses
     */
    public function test_responses() {
        global $DB, $SESSION;
        $name = 'datatable';
        $endpoint = 'test.php';

        $datatable = new deepsight_datatable_mock($DB, $name, $endpoint);

        // Test basic response.
        ob_start();
        $datatable->respond('mock');
        $contents = ob_get_contents();
        ob_end_clean();
        $this->assertEquals('Success', $contents);

        // Test nonexistent respond.
        ob_start();
        $datatable->respond('nonexistent');
        $contents = ob_get_contents();
        ob_end_clean();
        $expected = 'throw 1;{"result":"fail","msg":"Do not know how to respond to that request."}';
        $this->assertEquals($expected, $contents);

        // Test filter response.
        ob_start();
        $_POST['filtername'] = 'testfilter1';
        $datatable->respond('filter');
        $actual = ob_get_contents();
        ob_end_clean();
        $expected = 'success';
        $this->assertEquals($expected, $actual);

        // Test action response.
        ob_start();
        $_POST['actionname'] = 'testaction';
        $_POST['sesskey'] = sesskey();
        $_POST['elements'] = safe_json_encode(array());
        $datatable->respond('action');
        $actual = ob_get_contents();
        ob_end_clean();
        $expected = safe_json_encode(array('result' => 'success'));
        $this->assertEquals($expected, $actual);

        // Empty bulklist list.
        ob_start();
        $datatable->respond('bulklist_get');
        $actual = ob_get_contents();
        ob_end_clean();
        $actual = safe_json_decode($actual);
        $expected = array(
            'result' => 'success',
            'page_results_ids' => array(),
            'page_results_values' => array(),
            'total_results' => 0
        );
        $this->assertEquals($expected, $actual);

        // Test bulklist list with items.
        $bulklistparam = $datatable->get_bulklist_sess_param();
        $generatedids = array();
        for ($i=0; $i<2; $i++) {
            $user = new stdClass;
            $user->firstname = 'Test';
            $user->lastname = 'User '.$i;
            $id = $DB->insert_record('crlm_user', $user);
            $generatedids[] = $id;
            $SESSION->{$bulklistparam}[$id] = $id;
        }
        ob_start();
        $datatable->respond('bulklist_get');
        $actual = ob_get_contents();
        ob_end_clean();
        $actual = safe_json_decode($actual);
        $expected = array(
            'result' => 'success',
            'page_results_ids' => array_reverse($generatedids),
            'page_results_values' => array('Test User 1', 'Test User 0'),
            'total_results' => 2
        );
        $this->assertEquals($expected, $actual);

        // Test bulklist modify - removing.
        $_POST['modify'] = 'remove';
        $_POST['ids'] = array(2);
        ob_start();
        $datatable->respond('bulklist_modify');
        $actual = ob_get_contents();
        ob_end_clean();
        $actual = safe_json_decode($actual);
        $expected = array(
            'result' => 'success',
            'page_results_ids' => array(1),
            'page_results_values' => array('Test User 0'),
            'total_results' => 1
        );
        $this->assertEquals($expected, $actual);

        // Test bulklist modify - adding.
        $_POST['modify'] = 'add';
        $_POST['ids'] = array(2);
        ob_start();
        $datatable->respond('bulklist_modify');
        $actual = ob_get_contents();
        ob_end_clean();
        $actual = safe_json_decode($actual);
        $expected = array(
            'result' => 'success',
            'page_results_ids' => array(2, 1),
            'page_results_values' => array('Test User 1', 'Test User 0'),
            'total_results' => 2
        );
        $this->assertEquals($expected, $actual);

        // Test bulklist modify - deduplication.
        $_POST['modify'] = 'add';
        $_POST['ids'] = array(2);
        ob_start();
        $datatable->respond('bulklist_modify');
        $actual = ob_get_contents();
        ob_end_clean();
        $actual = safe_json_decode($actual);
        $expected = array(
            'result' => 'success',
            'page_results_ids' => array(2, 1),
            'page_results_values' => array('Test User 1', 'Test User 0'),
            'total_results' => 2
        );
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test results fetching.
     */
    public function test_results() {
        global $DB, $SESSION;

        // Insert test data.
        $cities = array('Springfield', 'Springfield', 'Springfield', 'Toronto', 'Toronto', 'Waterloo');
        foreach ($cities as $i => $city) {
            $user = new stdClass;
            $user->username = 'testuser'.$i;
            $user->idnumber = 'testuser'.$i;
            $user->city = $city;
            $DB->insert_record('crlm_user', $user);
        }

        $name = 'datatable';
        $endpoint = 'test.php';
        $datatable = new deepsight_datatable_mock($DB, $name, $endpoint);

        // Test sort sql.
        $sort = array('city' => 'desc');
        $sortsql = $datatable->get_sort_sql($sort);
        $expected = 'ORDER BY city DESC';
        $this->assertEquals($expected, $sortsql);

        // Test sort sql with invalid sort field.
        $sort = array('nonexistentfield' => 'desc');
        $sortsql = $datatable->get_sort_sql($sort);
        $expected = '';
        $this->assertEquals($expected, $sortsql);

        // Test filter sql without bulklist and one filter.
        $filters = array('testfilter1' => 'test');
        $filtersql = $datatable->get_filter_sql($filters);
        $expectedsql = 'WHERE name = ?';
        $expectedparams = array('test');
        $this->assertEquals($expectedsql, $filtersql[0]);
        $this->assertEquals($expectedparams, $filtersql[1]);

        // Test filter sql without bulklist and two filters.
        $filters = array('testfilter1' => 'test', 'testfilter2' => 'test2');
        $filtersql = $datatable->get_filter_sql($filters);
        $expectedsql = 'WHERE name = ? AND city = ?';
        $expectedparams = array('test', 'test2');
        $this->assertEquals($expectedsql, $filtersql[0]);
        $this->assertEquals($expectedparams, $filtersql[1]);

        // Test filter sql with bulklist.
        $bulklistparam = $datatable->get_bulklist_sess_param();
        $SESSION->{$bulklistparam} = array(1 => 1, 2 => 2);
        $filters = array('testfilter1' => 'test');
        $filtersql = $datatable->get_filter_sql($filters);
        $expectedsql = 'WHERE name = ? AND element.id NOT IN (1,2)';
        $expectedparams = array('test');
        $this->assertEquals($expectedsql, $filtersql[0]);
        $this->assertEquals($expectedparams, $filtersql[1]);

        // Test select fields with one filter.
        $filters = array('testfilter1' => 'test');
        $selectfields = $datatable->get_select_fields($filters);
        $expected = array('element.id AS element_id', 'testcolumn AS testcolumn', 'name AS name');
        $this->assertEquals($expected, $selectfields);

        // Test select fields with two filters.
        $filters = array('testfilter1' => 'test', 'testfilter2' => 'test2');
        $selectfields = $datatable->get_select_fields($filters);
        $expected = array('element.id AS element_id', 'testcolumn AS testcolumn', 'name AS name', 'city AS city');
        $this->assertEquals($expected, $selectfields);

        // Test get column labels with one filter.
        $filters = array('testfilter1' => 'test');
        $columnlabels = $datatable->get_column_labels($filters);
        $expected = array('testcolumn' => 'Test Column', 'name'=> 'Name');
        $this->assertEquals($expected, $columnlabels);

        // Test get column labels with two filters.
        $filters = array('testfilter1' => 'test', 'testfilter2' => 'test2');
        $columnlabels = $datatable->get_column_labels($filters);
        $expected = array('testcolumn' => 'Test Column', 'name'=> 'Name', 'city' => 'City');
        $this->assertEquals($expected, $columnlabels);
    }
}
