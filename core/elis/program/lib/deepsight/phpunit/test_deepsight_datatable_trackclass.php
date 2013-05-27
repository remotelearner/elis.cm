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
require_once(elispm::lib('data/track.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));

/**
 * Mock trackclass_assigned datatable class to expose protected methods and properties.
 */
class deepsight_datatable_trackclass_assigned_mock extends deepsight_datatable_trackclass_assigned {

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
 * Mock trackclass_available datatable class to expose protected methods and properties.
 */
class deepsight_datatable_trackclass_available_mock extends deepsight_datatable_trackclass_available {

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
 * Tests trackclass datatable functions.
 */
class deepsight_datatable_trackclass_test extends deepsight_datatable_searchresults_test {
    public $resultscsv = 'csv_class.csv';

    /**
     * Return overlay tables.
     * @return array An array of overlay tables.
     */
    protected static function get_overlay_tables() {
        $overlay = array(
            course::TABLE => 'elis_program',
            curriculum::TABLE => 'elis_program',
            curriculumcourse::TABLE => 'elis_program',
            pmclass::TABLE => 'elis_program',
            trackassignment::TABLE => 'elis_program',
            track::TABLE => 'elis_program',
            field::TABLE => 'elis_core',
            'message' => 'moodle'
        );
        return array_merge(parent::get_overlay_tables(), $overlay);
    }

    /**
     * Do any setup before tests that rely on data in the database - i.e. create users/courses/classes/etc or import csvs.
     */
    protected function set_up_tables() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(pmclass::TABLE, elispm::lib('deepsight/phpunit/csv_class.csv'));
        $dataset->addTable(course::TABLE, elispm::lib('deepsight/phpunit/csv_course.csv'));
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
            'element_idnumber' => $element['idnumber'],
            'crs_name' => 'Test Course'.$element['courseid'],
            'id' => $element['id'],
            'meta' => array(
                'label' => $element['idnumber']
            )
        );
    }

    /**
     * Dataprovider for test_assigned_shows_assigned_classes.
     * @return array Array of test parameters.
     */
    public function dataprovider_assigned_shows_assigned_classes() {
        return array(
                // 0: Test table shows nothing when no associations present.
                array(
                        array(),
                        101,
                        array(),
                        0,
                ),
                // 1: Test table shows nothing when no associations present for current track.
                array(
                        array(
                                array('trackid' => 100, 'classid' => 5),
                        ),
                        101,
                        array(),
                        0,
                ),
                // 2: Test table shows existing associations for the current track.
                array(
                        array(
                                array('trackid' => 101, 'classid' => 5),
                        ),
                        101,
                        array(
                                $this->get_search_result_row($this->resultscsv, 5),
                        ),
                        1,
                ),
                // 3: Test table shows multiple existing associations for the current program.
                array(
                        array(
                                array('trackid' => 101, 'classid' => 5),
                                array('trackid' => 101, 'classid' => 6),
                        ),
                        101,
                        array(
                                $this->get_search_result_row($this->resultscsv, 5),
                                $this->get_search_result_row($this->resultscsv, 6),
                        ),
                        2,
                ),
                // 4: Test table shows multiple existing associations for the current program and associations for other programs
                // don't appear.
                array(
                        array(
                                array('trackid' => 100, 'classid' => 5),
                                array('trackid' => 100, 'classid' => 6),
                                array('trackid' => 101, 'classid' => 7),
                                array('trackid' => 101, 'classid' => 8),
                        ),
                        101,
                        array(
                                $this->get_search_result_row($this->resultscsv, 7),
                                $this->get_search_result_row($this->resultscsv, 8),
                        ),
                        2,
                ),
        );
    }

    /**
     * Test assigned table shows assigned classes.
     *
     * @dataProvider dataprovider_assigned_shows_assigned_classes
     * @param array $associations An array of arrays of parameters to construct trackassignment associations.
     * @param int $tabletrackid The ID of the track we're going to manage.
     * @param array $expectedresults The expected page of results.
     * @param int $expectedtotal The expected number of total results.
     */
    public function test_assigned_shows_assigned_classes($associations, $tabletrackid, $expectedresults, $expectedtotal) {
        global $DB;

        foreach ($associations as $association) {
            $trackassignment = new trackassignment($association);
            $trackassignment->save();
        }

        $table = new deepsight_datatable_trackclass_assigned_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_trackid($tabletrackid);

        $actualresults = $table->get_search_results(array(), array(), 0, 20);
        $this->assert_search_results($expectedresults, $expectedtotal, $actualresults);
    }

    /**
     * Dataprovider for test_available_shows_correct_classes.
     * @return array Array of test parameters.
     */
    public function dataprovider_available_shows_correct_classes() {
        return array(
                // 0: Test no associations produces no results.
                array(
                        array(),
                        101,
                        array(),
                ),
                // 1: Test that correct classes are returned when programcourse association exists.
                array(
                        array(
                                array('curriculumid' => 5, 'courseid' => 100),
                        ),
                        101,
                        array(
                                $this->get_search_result_row($this->resultscsv, 5),
                                $this->get_search_result_row($this->resultscsv, 6),
                        ),
                ),
                // 2: Test that correct classes are returned when muliple programcourse associations exist.
                array(
                        array(
                                array('curriculumid' => 5, 'courseid' => 100),
                                array('curriculumid' => 5, 'courseid' => 101),
                        ),
                        101,
                        array(
                                $this->get_search_result_row($this->resultscsv, 5),
                                $this->get_search_result_row($this->resultscsv, 6),
                                $this->get_search_result_row($this->resultscsv, 7),
                        ),
                ),
                // 3: Test that programcourse associations for programs other than those associated with current track don't appear.
                array(
                        array(
                                array('curriculumid' => 5, 'courseid' => 100),
                                array('curriculumid' => 6, 'courseid' => 101),
                        ),
                        101,
                        array(
                                $this->get_search_result_row($this->resultscsv, 5),
                                $this->get_search_result_row($this->resultscsv, 6),
                        ),
                ),
        );
    }

    /**
     * Test available list only shows classes that are part of courses that are part of programs that this track is part of
     * @dataProvider dataprovider_available_shows_correct_classes
     * @param array $programcourse An array of programcourse associations to create.
     * @param int $tabletrackid The ID of the track we want to manage.
     * @param array $expectedresults An array of expected results.
     */
    public function test_available_shows_correct_classes(array $programcourse, $tabletrackid, $expectedresults) {
        global $USER, $DB, $CFG;
        $userbackup = $USER;

        // Set up permissions.
        $USER = $this->setup_permissions_test();

        // We're not interested in permissions for this test, so give associate permission globally.
        $this->give_permission_for_context($USER->id, 'elis/program:associate', get_context_instance(CONTEXT_SYSTEM));

        foreach ($programcourse as $association) {
            $association = new curriculumcourse($association);
            $association->save();
        }

        // Construct test table.
        $table = new deepsight_datatable_trackclass_available_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_trackid($tabletrackid);

        // Perform test.
        $actualresults = $table->get_search_results(array(), array(), 0, 20);
        $this->assert_search_results($expectedresults, count($expectedresults), $actualresults);

        // Restore user.
        $USER = $userbackup;
    }

    // Test doesn't show assigned classes
    // Test permissions.

    /**
     * Dataprovider for test_available_doesnt_show_assigned_classes.
     * @return array Array of test parameters.
     */
    public function dataprovider_available_doesnt_show_assigned_classes() {
        return array(
                // Test the table shows all classes when nothing is assigned.
                array(
                        array(),
                        100,
                        array(
                                $this->get_search_result_row($this->resultscsv, 5),
                                $this->get_search_result_row($this->resultscsv, 6),
                                $this->get_search_result_row($this->resultscsv, 7),
                                $this->get_search_result_row($this->resultscsv, 8),
                        ),
                        4
                ),
                // Test the table doesn't show assigned classes.
                array(
                        array(
                                array('trackid' => 102, 'classid' => 6),
                        ),
                        102,
                        array(
                                $this->get_search_result_row($this->resultscsv, 5),
                                $this->get_search_result_row($this->resultscsv, 7),
                                $this->get_search_result_row($this->resultscsv, 8),
                        ),
                        3
                ),
                // Test multiple assignments.
                array(
                        array(
                                array('trackid' => 102, 'classid' => 6),
                                array('trackid' => 102, 'classid' => 8),
                        ),
                        102,
                        array(
                                $this->get_search_result_row($this->resultscsv, 5),
                                $this->get_search_result_row($this->resultscsv, 7),
                        ),
                        2
                ),
                // Test only assignments for the current track affect results.
                array(
                        array(
                                array('trackid' => 101, 'classid' => 6),
                                array('trackid' => 102, 'classid' => 5),
                                array('trackid' => 102, 'classid' => 8),
                        ),
                        102,
                        array(
                                $this->get_search_result_row($this->resultscsv, 6),
                                $this->get_search_result_row($this->resultscsv, 7),
                        ),
                        2
                ),
        );
    }

    /**
     * Test available table doesn't show assigned classes.
     * @dataProvider dataprovider_available_doesnt_show_assigned_classes
     * @param array $associations An array of arrays of parameters to construct trackassignment associations.
     * @param int $tabletrackid The ID of the track we're going to manage.
     * @param array $expectedresults The expected page of results.
     * @param int $expectedtotal The expected number of total results.
     */
    public function test_available_doesnt_show_assigned_classes($associations, $tabletrackid, $expectedresults, $expectedtotal) {
        global $USER, $DB, $CFG;
        $userbackup = $USER;

        // Set up permissions.
        $USER = $this->setup_permissions_test();
        $this->give_permission_for_context($USER->id, 'elis/program:associate', get_context_instance(CONTEXT_SYSTEM));

        // We're not interested in the class > course > program > track requirement for this test, so assign all courses to all
        // programs.
        foreach (array(5, 6, 7) as $programid) {
            foreach (array(100, 101, 102) as $courseid) {
                $association = new curriculumcourse(array('curriculumid' => $programid, 'courseid' => $courseid));
                $association->save();
            }
        }

        // Create trackassignments.
        foreach ($associations as $association) {
            $trackassignment = new trackassignment($association);
            $trackassignment->save();
        }

        // Construct test table.
        $table = new deepsight_datatable_trackclass_available_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_trackid($tabletrackid);

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
                        101,
                        array(
                                $this->get_search_result_row($this->resultscsv, 5),
                                $this->get_search_result_row($this->resultscsv, 6),
                                $this->get_search_result_row($this->resultscsv, 7),
                                $this->get_search_result_row($this->resultscsv, 8),
                        ),
                        4,
                ),
                // 2: Test permissions on one track returns that track.
                array(
                        array('class' => array(6)),
                        102,
                        array(
                                $this->get_search_result_row($this->resultscsv, 6),
                        ),
                        1,
                ),
                // 3: Test permissions on multiple tracks returns those tracks.
                array(
                        array('class' => array(5, 6)),
                        102,
                        array(
                                $this->get_search_result_row($this->resultscsv, 5),
                                $this->get_search_result_row($this->resultscsv, 6),
                        ),
                        2,
                ),
        );
    }

    /**
     * Test available table only shows classes that the assigner has the elis/program::associate permission on.
     * @dataProvider dataprovider_available_permissions_associate
     * @param array $contextstoassign An array of information specifying the contexts to assign the associate permission on.
     *                                This is formatted like array('system' => true, 'class' => array(1, 2, 3))
     * @param int $tabletrackid The ID of the track we're going to manage.
     * @param array $expectedresults The expected page of results.
     * @param int $expectedtotal The expected number of total results.
     */
    public function test_available_permissions_associate($contextstoassign, $tabletrackid, $expectedresults, $expectedtotal) {
        global $USER, $DB, $CFG;
        $userbackup = $USER;

        // Set up permissions.
        $USER = $this->setup_permissions_test();

        // We're not interested in the class > course > program > track requirement for this test, so assign all courses to all
        // programs.
        foreach (array(5, 6, 7) as $programid) {
            foreach (array(100, 101, 102) as $courseid) {
                $association = new curriculumcourse(array('curriculumid' => $programid, 'courseid' => $courseid));
                $association->save();
            }
        }

        // Set up capabilities.
        foreach ($contextstoassign as $contexttype => $ids) {
            if ($contexttype === 'system') {
                $this->give_permission_for_context($USER->id, 'elis/program:associate', get_context_instance(CONTEXT_SYSTEM));
            } else {
                foreach ($ids as $contextinstanceid) {
                    switch($contexttype) {
                        case 'class':
                            $context = context_elis_class::instance($contextinstanceid);
                            break;
                    }
                    $this->give_permission_for_context($USER->id, 'elis/program:associate', $context);
                }
            }
        }

        accesslib_clear_all_caches(true);

        // Construct test table.
        $table = new deepsight_datatable_trackclass_available_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_trackid($tabletrackid);

        // Perform test.
        $actualresults = $table->get_search_results(array(), array(), 0, 20);

        // Verify result.
        $this->assert_search_results($expectedresults, $expectedtotal, $actualresults);

        // Restore user.
        $USER = $userbackup;
    }
}