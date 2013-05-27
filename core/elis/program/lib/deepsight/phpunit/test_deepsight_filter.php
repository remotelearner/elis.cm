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
 * Test various filter operations.
 */
class deepsight_filter_test extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

    /**
     * Return overlay tables.
     *
     * @return array An array of overlay tables.
     */
    protected static function get_overlay_tables() {
        return array(
            'crlm_user' => 'elis_program'
        );
    }

    /**
     * Dataprovider for filter parameters
     *
     * @return array Function parameters.
     */
    public function filter_dataprovider() {
        return array(
            array('testname', 'testlabel', array('element.id' => 'Element ID')),
            array('testname', 'testlabel', array('element.id' => 'Element ID', 'element.name' => 'Element Name')),
            array('testname', 'testlabel', array('id' => 'Element ID')),
            array('testname', '', array('id' => '')),
        );
    }

    /**
     * Test filter construction.
     *
     * @dataProvider filter_dataprovider
     */
    public function test_construct($name, $label, $fielddata) {
        global $DB;

        $fieldaliases = array();
        foreach ($fielddata as $field => $label) {
            $fieldaliases[$field] = str_replace('.', '_', $field);
        }

        $filter = new deepsight_filter_mock($DB, $name, $label, $fielddata);

        $this->assertEquals($name, $filter->name);
        $this->assertEquals($label, $filter->label);
        $this->assertEquals($fielddata, $filter->fields);
        $this->assertEquals($fieldaliases, $filter->field_aliases);
    }

    /**
     * Test get_column_labels() method.
     *
     * @dataProvider filter_dataprovider
     */
    public function test_get_column_labels($name, $label, $fielddata) {
        global $DB;

        $filter = new deepsight_filter_mock($DB, $name, $label, $fielddata);
        $columnlabels = $filter->get_column_labels();

        $expectedlabels = array();
        foreach ($fielddata as $field => $label) {
            $expectedlabels[str_replace('.', '_', $field)] = $label;
        }

        $this->assertInternalType('array', $columnlabels);
        $this->assertEquals($expectedlabels, $columnlabels);
    }

    /**
     * Test get_select_fields() method.
     *
     * @dataProvider filter_dataprovider
     */
    public function test_get_select_fields($name, $label, $fielddata) {
        global $DB;

        $filter = new deepsight_filter_mock($DB, $name, $label, $fielddata);
        $selectfields = $filter->get_select_fields();

        $expectedselect = array();
        foreach ($fielddata as $field => $label) {
            $expectedselect[] = $field.' AS '.str_replace('.', '_', $field);
        }

        $this->assertInternalType('array', $selectfields);
        $this->assertEquals($expectedselect, $selectfields);
    }

    /**
     * Test get_js_opts() method.
     *
     * @dataProvider filter_dataprovider
     */
    public function test_get_js_opts($name, $label, $fielddata) {
        global $DB;

        $filter = new deepsight_filter_mock($DB, $name, $label, $fielddata);
        $jsopts = $filter->get_js_opts();

        $this->assertInternalType('array', $jsopts);
        $this->assertArrayHasKey('name', $jsopts);
        $this->assertArrayHasKey('label', $jsopts);
        $this->assertEquals($name, $jsopts['name']);
        $this->assertEquals($label, $jsopts['label']);
    }

    /**
     * Test get_name() method.
     * @dataProvider filter_dataprovider
     */
    public function test_get_name($name, $label, $fielddata) {
        global $DB;

        $filter = new deepsight_filter_mock($DB, $name, $label, $fielddata);
        $this->assertEquals($name, $filter->get_name());
    }

    /**
     * Dataprovider for the date filter.
     *
     * @return array Function parameters
     */
    public function datefilter_dataprovider() {
        $tests = array(
            array(
                array('date' => 'Date'),
                array(array('month' => 0, 'date' => 1, 'year' => 1970)),
                array('date >= 0 AND date <= 86399', array())
            ),
            array(
                array('element.date' => 'Date'),
                array(array('month' => 0, 'date' => 1, 'year' => 1970)),
                array('element.date >= 0 AND element.date <= 86399', array())
            ),
            array(
                array('element.date' => 'Date'),
                array(array('month' => 0, 'date' => 2, 'year' => 1970)),
                array('element.date >= 86400 AND element.date <= 172799', array())
            ),
            array(
                array('element.date' => 'Date'),
                array(),
                array('', array())
            ),
        );

        $invaliddatavals = array(null, true, false, array(), 'test', 0, 10, -1, array('month'), array('date'), array('year'),
                                    array('month', 'date'), array('month', 'year'), array('date', 'year'));
        foreach ($invaliddatavals as $val) {
            $tests[] = array(
                array('date' => 'Date'),
                array($val),
                array('', array())
            );
        }

        $invalidmonthvals = array(null, true, false, array(), -1, 13, 'test');
        foreach ($invalidmonthvals as $val) {
            $tests[] = array(
                array('date' => 'Date'),
                array(array('month' => $val, 'date' => 1, 'year' => 1987)),
                array('', array())
            );
        }

        $invaliddatevals = array(null, true, false, array(), -1, 0, 32, 'test');
        foreach ($invaliddatevals as $val) {
            $tests[] = array(
                array('date' => 'Date'),
                array(array('month' => 2, 'date' => $val, 'year' => 1987)),
                array('', array())
            );
        }

        $invalidyearvals = array(null, true, false, array(), -1, 0, 12, 9999, 'test');
        foreach ($invalidyearvals as $val) {
            $tests[] = array(
                array('date' => 'Date'),
                array(array('month' => 2, 'date' => 2, 'year' => $val)),
                array('', array())
            );
        }

        return $tests;
    }

    /**
     * Get get_filter_sql for the date filter.
     *
     * @dataProvider datefilter_dataprovider
     */
    public function test_filter_date_filtersql($fielddata, $filterdata, $expectedfiltersql) {
        global $DB;

        date_default_timezone_set('UTC');

        $filter = new deepsight_filter_date($DB, 'date', 'Date', $fielddata);
        $filtersql = $filter->get_filter_sql($filterdata);

        $this->assertInternalType('array', $filtersql);
        $this->assertEquals(2, count($filtersql));
        $this->assertInternalType('string', $filtersql[0]);
        $this->assertInternalType('array', $filtersql[1]);
        $this->assertEquals($expectedfiltersql, $filtersql);
    }

    /**
     * Test the classid construction for the enrolmentstatus filter.
     */
    public function test_filter_enrolmentstatus() {
        global $DB;
        $filter = new deepsight_filter_enrolmentstatus($DB, 'enrolled', 'Enrolment Status');
        $filter->set_classid(1);

        $this->assertEquals(1, $filter->get_classid());
    }

    /**
     * Dataprovider for the enrolmentstatus filter.
     *
     * @return array Array of function parameters.
     */
    public function enrolmentstatusfilter_dataprovider() {
        $tests = array();

        $invalidvals = array(null, true, false, '', array(), 'test', 'enrolled', 1, 0, -1);
        foreach ($invalidvals as $val) {
            $tests[] = array($val, array('', array()));
        }

        $tests[] = array(
                array('enrolled'),
                array(
                        '(SELECT id FROM {crlm_class_enrolment} WHERE classid = ? AND userid=element.id) IS NOT NULL',
                        array(1)
                )
        );
        $tests[] = array(
                array('notenrolled'),
                array(
                        '(SELECT id FROM {crlm_class_enrolment} WHERE classid = ? AND userid=element.id) IS NULL',
                        array(1)
                )
        );
        return $tests;
    }

    /**
     * Test the get_filter_sql() method of the enrolmentstatus filter.
     *
     * @dataProvider enrolmentstatusfilter_dataprovider
     */
    public function test_filter_enrolmentstatus_filtersql($filterdata, $expectedfiltersql) {
        global $DB;
        $filter = new deepsight_filter_enrolmentstatus($DB, 'enrolled', 'Enrolment Status');
        $filter->set_classid(1);
        $filtersql = $filter->get_filter_sql($filterdata);

        $this->assertInternalType('array', $filtersql);
        $this->assertEquals(2, count($filtersql));
        $this->assertInternalType('string', $filtersql[0]);
        $this->assertInternalType('array', $filtersql[1]);
        $this->assertEquals($expectedfiltersql, $filtersql);
    }

    /**
     * Test various methods of the menuofchoices filter.
     */
    public function test_filter_menuofchoices() {
        global $DB;

        // Assert we get an exception if we don't specify an endpoint.
        try {
            $filter = new deepsight_filter_menuofchoices($DB, 'menu', 'Menu Of Choices', array());
            $this->assertTrue(false);
        } catch (Exception $e) {
            $this->assertTrue(true);
        }

        $name = 'menu';
        $label = 'Menu Of Choices';
        $dataurl = 'test.php';
        $filter = new deepsight_filter_menuofchoices($DB, $name, $label, array(), $dataurl);

        // Test setting/getting choices.
        $choices = array('A', 'B', 'C', 'D');
        $filter->set_choices($choices);
        $this->assertEquals($choices, $filter->get_choices());

        $jsopts = $filter->get_js_opts();
        $this->assertInternalType('array', $jsopts);
        $this->assertArrayHasKey('name', $jsopts);
        $this->assertArrayHasKey('label', $jsopts);
        $this->assertArrayHasKey('dataurl', $jsopts);
        $this->assertArrayHasKey('initialchoices', $jsopts);
        $this->assertEquals($name, $jsopts['name']);
        $this->assertEquals($label, $jsopts['label']);
        $this->assertEquals($dataurl.'?m=filter', $jsopts['dataurl']);

        $expectedchoices = array();
        for ($i = 0; $i < 4; $i++) {
            $expectedchoices[] = array('label' => $choices[$i], 'id' => $i);
        }
        $this->assertEquals($expectedchoices, $jsopts['initialchoices']);
    }

    /**
     * Dataprovider for the test of the menu of choices search function.
     *
     * @return array Array of parameters.
     */
    public function menuofchoicesfilter_respond_dataprovider() {
        $tests = array();

        $searchandresults = array(
            'Apple' => array('Apple'),
            'Ap' => array('Apple'),
            'A' => array('Apple', 'Banana', 'Carrot'),
            'E' => array('Apple', 'Deli'),
            'AnAn' => array('Banana'),
            'rr' => array('Carrot'),
            'zq' => array()
        );

        foreach ($searchandresults as $search => $results) {
            $formattedresults = array();
            foreach ($results as $result) {
                $formattedresults[] = array('id' => $result, 'label' => $result);
            }
            $tests[] = array($search, $formattedresults);
        }
        return $tests;
    }

    /**
     * Tests the menu of choices search function.
     *
     * @dataProvider menuofchoicesfilter_respond_dataprovider
     */
    public function test_filter_menuofchoices_respond($filterdata, $expectedresponse) {
        global $DB;
        $expectedresponse = safe_json_encode($expectedresponse);

        $name = 'menu';
        $label = 'Menu Of Choices';
        $dataurl = 'test.php';
        $filter = new deepsight_filter_menuofchoices($DB, $name, $label, array(), $dataurl);

        // Test setting/getting choices.
        $choices = array('Apple', 'Banana', 'Carrot', 'Deli');
        $filter->set_choices($choices);

        $_POST['val'] = $filterdata;
        $response = $filter->respond_to_js();

        $this->assertEquals($expectedresponse, $response);
    }

    /**
     * Dataprovider for test_filter_menuofchoices_get_filter_sql()
     *
     * @return array Array of method parameters.
     */
    public function menuofchoicesfilter_get_filter_sql_dataprovider() {
        $tests = array();
        $invalidvals = array(
                null,
                true,
                false,
                array(),
                0,
                10,
                -1,
                'test',
                array(null),
                array(false),
                array(true),
                array(array()),
                array('test', false)
        );
        foreach ($invalidvals as $val) {
            $tests[] = array($val, array('', array()));
        }

        $validvals = array(array('Apple'), array('Apple', 'Banana'), array('A', 'B', 'C'), array(0, 1, 2, 3));
        foreach ($validvals as $val) {
            $tests[] = array($val, array('choice IN ('.implode(',', array_fill(0, count($val), '?')).')', $val));
        }

        return $tests;
    }

    /**
     * Test the get_filter_sql() function of the menuofchoices filter.
     *
     * @dataProvider menuofchoicesfilter_get_filter_sql_dataprovider
     */
    public function test_filter_menuofchoices_get_filter_sql($filterdata, $expectedresponse) {
        global $DB;
        $name = 'menu';
        $label = 'Menu Of Choices';
        $dataurl = 'test.php';
        $filter = new deepsight_filter_menuofchoices($DB, $name, $label, array('choice' => 'Choice'), $dataurl);

        $filtersql = $filter->get_filter_sql($filterdata);

        $this->assertEquals($expectedresponse, $filtersql);
    }

    /**
     * Test various basic methods of the searchselect filter.
     */
    public function test_filter_searchselect() {
        global $DB;

        // Insert test data.
        $cities = array('Springfield', 'Springfield', 'Springfield', 'Toronto', 'Toronto', 'Waterloo');
        foreach ($cities as $i => $city) {
            $user = new stdClass;
            $user->username = 'testuser'.$i;
            $user->idnumber = 'testuser'.$i;
            $user->city = $city;
            $DB->insert_record('crlm_user', $user);
        }

        // Expected choices, in order.
        $expectedchoices = array(
            array('label' => 'Springfield', 'id' => 'Springfield'),
            array('label' => 'Toronto', 'id' => 'Toronto'),
            array('label' => 'Waterloo', 'id' => 'Waterloo'),
        );
        $expectedchoicesinternal = array('Springfield' => 'Springfield', 'Toronto' => 'Toronto', 'Waterloo' => 'Waterloo');

        $name = 'searchselect';
        $label = 'Search Select';
        $endpoint = 'test.php';
        $fielddata = array('city' => 'City');

        // Assert we get an exception if we don't specify an endpoint.
        try {
            $filter = new deepsight_filter_searchselect($DB, $name, $label, $fielddata);
            $this->assertTrue(false);
        } catch (Exception $e) {
            $this->assertTrue(true);
        }

        // Assert we get an exception if we don't specify a choices table.
        try {
            $filter = new deepsight_filter_searchselect($DB, $name, $label, $fielddata, $endpoint);
            $this->assertTrue(false);
        } catch (Exception $e) {
            $this->assertTrue(true);
        }

        // Assert we get an exception if we don't specify a choices field.
        try {
            $filter = new deepsight_filter_searchselect($DB, $name, $label, $fielddata, $endpoint, 'crlm_user');
            $this->assertTrue(false);
        } catch (Exception $e) {
            $this->assertTrue(true);
        }

        $filter = new deepsight_filter_searchselect($DB, $name, $label, $fielddata, $endpoint, 'crlm_user', 'city');

        $jsopts = $filter->get_js_opts();
        $this->assertInternalType('array', $jsopts);
        $this->assertArrayHasKey('name', $jsopts);
        $this->assertArrayHasKey('label', $jsopts);
        $this->assertArrayHasKey('dataurl', $jsopts);
        $this->assertArrayHasKey('initialchoices', $jsopts);
        $this->assertEquals($name, $jsopts['name']);
        $this->assertEquals($label, $jsopts['label']);
        $this->assertEquals($endpoint.'?m=filter', $jsopts['dataurl']);
        $this->assertEquals($expectedchoices, $jsopts['initialchoices']);
        $this->assertEquals($expectedchoicesinternal, $filter->get_choices());
    }

    /**
     * Dataprovider for testing the searchselect filter's search function.
     *
     * @return array Array of parameters.
     */
    public function searchselectfilter_respond_dataprovider() {
        $tests = array();

        $searchandresults = array(
            'Springfield' => array('Springfield'),
            'Spring' => array('Springfield'),
            'Toronto' => array('Toronto'),
            'Tor' => array('Toronto'),
            'Waterloo' => array('Waterloo'),
            'Wat' => array('Waterloo'),
            'n' => array('Springfield', 'Toronto'),
            'o' => array('Toronto', 'Waterloo'),
            'r' => array('Springfield', 'Toronto', 'Waterloo'),
            'zq' => array()
        );

        foreach ($searchandresults as $search => $results) {
            $formattedresults = array();
            foreach ($results as $result) {
                $formattedresults[] = array('id' => $result, 'label' => $result);
            }
            $tests[] = array($search, $formattedresults);
        }
        return $tests;
    }

    /**
     * Test the searchselect filter's search function.
     *
     * @dataProvider searchselectfilter_respond_dataprovider
     */
    public function test_filter_searchselect_respond($filterdata, $expectedresponse) {
        global $DB;
        $expectedresponse = safe_json_encode($expectedresponse);

        // Insert test data.
        $cities = array('Springfield', 'Springfield', 'Springfield', 'Toronto', 'Toronto', 'Waterloo');
        foreach ($cities as $i => $city) {
            $user = new stdClass;
            $user->username = 'testuser'.$i;
            $user->idnumber = 'testuser'.$i;
            $user->city = $city;
            $DB->insert_record('crlm_user', $user);
        }

        $name = 'searchselect';
        $label = 'Search Select';
        $endpoint = 'test.php';
        $fielddata = array('city' => 'City');
        $filter = new deepsight_filter_searchselect($DB, $name, $label, $fielddata, $endpoint, 'crlm_user', 'city');

        $_POST['val'] = $filterdata;
        $response = $filter->respond_to_js();

        $this->assertEquals($expectedresponse, $response);
    }

    /**
     * Dataprovider for testing the searchselect filter's get_filter_sql function.
     *
     * @return array Array of parameters.
     */
    public function searchselectfilter_get_filter_sql_dataprovider() {
        $tests = array();
        $invalidvals = array(
                null,
                true,
                false,
                array(),
                0,
                10,
                -1,
                'test',
                array(null),
                array(false),
                array(true),
                array(array()),
                array('test', false)
        );
        foreach ($invalidvals as $val) {
            $tests[] = array($val, array('', array()));
        }

        $validvals = array(
                array('Waterloo'),
                array('Springfield', 'Waterloo'),
                array('Toronto', 'Springfield', 'Waterloo'),
                array(0, 1, 2, 3)
        );
        foreach ($validvals as $val) {
            $tests[] = array($val, array('city IN ('.implode(',', array_fill(0, count($val), '?')).')', $val));
        }

        return $tests;
    }

    /**
     * Test the searchselect filter's get_filter_sql function.
     *
     * @dataProvider searchselectfilter_get_filter_sql_dataprovider
     */
    public function test_filter_searchselect_get_filter_sql($filterdata, $expectedresponse) {
        global $DB;

        $name = 'searchselect';
        $label = 'Search Select';
        $endpoint = 'test.php';
        $fielddata = array('city' => 'City');
        $filter = new deepsight_filter_searchselect($DB, $name, $label, $fielddata, $endpoint, 'crlm_user', 'city');

        $filtersql = $filter->get_filter_sql($filterdata);

        $this->assertEquals($expectedresponse, $filtersql);
    }

    /**
     * Test the switch function.
     */
    public function test_filter_switch() {
        global $DB;
        $name = 'switch';
        $label = 'Switch';
        $fielddata = array('gender' => 'Gender');
        $choicesinternal = array('M' => 'Male', 'F' => 'Female');

        $expectedoptschoices = array();
        foreach ($choicesinternal as $id => $choice) {
            $expectedoptschoices[] = array('label' => $choice, 'value' => $id);
        }

        $filter = new deepsight_filter_switch($DB, $name, $label, $fielddata);
        $filter->set_choices($choicesinternal);

        $jsopts = $filter->get_js_opts();
        $this->assertInternalType('array', $jsopts);
        $this->assertArrayHasKey('name', $jsopts);
        $this->assertArrayHasKey('label', $jsopts);
        $this->assertArrayHasKey('choices', $jsopts);
        $this->assertEquals($name, $jsopts['name']);
        $this->assertEquals($label, $jsopts['label']);
        $this->assertEquals($expectedoptschoices, $jsopts['choices']);
        $this->assertEquals($choicesinternal, $filter->get_choices());

        // Test get_filter_sql.
        $invalidvals = array(null, true, false, array(), 0, 10, -1, 'test', array(null), array(false), array(true), array(array()));
        $invalidresponse = array('', array());
        foreach ($invalidvals as $val) {
            $filtersql = $filter->get_filter_sql($val);
            $this->assertEquals($invalidresponse, $filtersql);
        }

        $validvals = array(array('M'), array('F'), array('Test'), array(1), array(10), array(0));
        foreach ($validvals as $val) {
            $filtersql = $filter->get_filter_sql($val);
            $expected = array('gender = ?', array($val[0]));
            $this->assertEquals($expected, $filtersql);
        }
    }

    /**
     * Dataprovider for the textsearch filter's get_filter_sql test.
     *
     * @return array Array of parameters
     */
    public function textsearchfilter_get_filter_sql_dataprovider() {
        $tests = array();

        $fieldsets = array(
            array(
                'field1' => 'Field One'
            ),
            array(
                'field1' => 'Field One',
                'field2' => 'Field Two',
            ),
            array(
                'field1' => 'Field One',
                'field2' => 'Field Two',
                'field3' => 'Field Three',
            ),
            array(
                'field1' => 'Field One',
                'field2' => 'Field Two',
                'field3' => 'Field Three',
                'field4' => 'Field Four',
            ),
        );

        $invalidvals = array(null, true, false, array(), 0, 10, -1, 'test', array(null), array(false), array(true), array(array()));
        $validvals = array('one', 'one two', 'one two three', 'one two three four');
        foreach ($fieldsets as $fieldset) {

            foreach ($invalidvals as $val) {
                $tests[] = array($val, $fieldset, array('', array()));
            }

            foreach ($validvals as $search) {
                $words = explode(' ', $search);
                $sql = array();
                $params = array();
                foreach ($words as $word) {
                    $wordsql = array();
                    foreach ($fieldset as $field => $label) {
                        $wordsql[] = $field.' LIKE ?';
                        $params[] = '%'.$word.'%';
                    }
                    $sql[] = '('.implode(' OR ', $wordsql).')';
                }
                $response = array('('.implode(' AND ', $sql).')', $params);

                $tests[] = array(array($search), $fieldset, $response);
            }
        }

        return $tests;
    }

    /**
     * Test the textsearch filter's get_filter_sql method.
     *
     * @dataProvider textsearchfilter_get_filter_sql_dataprovider
     */
    public function test_filter_textsearch_get_filter_sql($filterdata, $fielddata, $expectedresponse) {
        global $DB;
        $name = 'textsearch';
        $label = 'Text Search';
        $filter = new deepsight_filter_textsearch($DB, $name, $label, $fielddata);
        $filtersql = $filter->get_filter_sql($filterdata);
        $this->assertEquals($expectedresponse, $filtersql);
    }
}
