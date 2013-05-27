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

require_once(elispm::lib('data/clusterassignment.class.php'));
require_once(elispm::lib('data/clustertrack.class.php'));
require_once(elispm::lib('data/curriculumstudent.class.php'));
require_once(elispm::lib('data/instructor.class.php'));
require_once(elispm::lib('data/student.class.php'));
require_once(elispm::lib('data/track.class.php'));
require_once(elispm::lib('data/userset.class.php'));

/**
 * Mock instructor_assigned datatable class to expose protected methods and properties.
 */
class deepsight_datatable_instructor_assigned_mock extends deepsight_datatable_instructor_assigned {
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
 * Mock instructor_available datatable class to expose protected methods and properties.
 */
class deepsight_datatable_instructor_available_mock extends deepsight_datatable_instructor_available {
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
 * Tests courseprogram datatable functions.
 */
class deepsight_datatable_courseprogram_test extends deepsight_datatable_searchresults_test {

    /**
     * @var string The name of the CSV file results will come from.
     */
    public $resultscsv = 'csv_user.csv';

    /**
     * Return overlay tables.
     * @return array An array of overlay tables.
     */
    protected static function get_overlay_tables() {
        $overlay = array(
            clusterassignment::TABLE => 'elis_program',
            clustertrack::TABLE => 'elis_program',
            curriculumstudent::TABLE => 'elis_program',
            course::TABLE => 'elis_program',
            instructor::TABLE => 'elis_program',
            pmclass::TABLE => 'elis_program',
            student::TABLE => 'elis_program',
            track::TABLE => 'elis_program',
            trackassignment::TABLE => 'elis_program',
            user::TABLE => 'elis_program',
            userset::TABLE => 'elis_program',
        );
        return array_merge(parent::get_overlay_tables(), $overlay);
    }

    /**
     * Do any setup before tests that rely on data in the database - i.e. create users/courses/classes/etc or import csvs.
     */
    protected function set_up_tables() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(course::TABLE, elispm::lib('deepsight/phpunit/csv_course.csv'));
        $dataset->addTable(pmclass::TABLE, elispm::lib('deepsight/phpunit/csv_class.csv'));
        $dataset->addTable(track::TABLE, elispm::lib('deepsight/phpunit/csv_track.csv'));
        $dataset->addTable(user::TABLE, elispm::lib('deepsight/phpunit/csv_user.csv'));
        $dataset->addTable(userset::TABLE, elispm::lib('deepsight/phpunit/csv_userset.csv'));
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
                // 0: Test no assgnments shows no users.
                array(
                        array(),
                        5,
                        array(),
                ),
                // 1: Test assignment shows user.
                array(
                        array(
                                array('classid' => 5, 'userid' => 100),
                        ),
                        5,
                        array(
                                $this->get_search_result_row($this->resultscsv, 100),
                        ),
                ),
                // 2: Test multiple assignments.
                array(
                        array(
                                array('classid' => 5, 'userid' => 100),
                                array('classid' => 5, 'userid' => 101),
                        ),
                        5,
                        array(
                                $this->get_search_result_row($this->resultscsv, 100),
                                $this->get_search_result_row($this->resultscsv, 101),
                        ),
                ),
                // 3: Test assignment to other classes don't show up.
                array(
                        array(
                                array('classid' => 5, 'userid' => 100),
                                array('classid' => 6, 'userid' => 101),
                        ),
                        5,
                        array(
                                $this->get_search_result_row($this->resultscsv, 100),
                        ),
                ),
        );
    }

    /**
     * Test assigned table shows assigned users.
     * @dataProvider dataprovider_assigned_shows_assigned_users
     * @param array $associations An array of arrays of parameters to construct instructor associations.
     * @param int $tableclassid The ID of the class we're managing.
     * @param array $expectedresults The expected page of results.
     */
    public function test_assigned_shows_assigned_users($associations, $tableclassid, $expectedresults) {
        global $DB;

        foreach ($associations as $association) {
            $association = new instructor($association);
            $association->save();
        }

        $table = new deepsight_datatable_instructor_assigned_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_classid($tableclassid);

        $actualresults = $table->get_search_results(array(), array(), 0, 20);
        $this->assert_search_results($expectedresults, count($expectedresults), $actualresults);
    }

    /**
     * Dataprovider for test_available_doesnt_show_assigned_instructors.
     * @return array Array of test parameters.
     */
    public function dataprovider_available_doesnt_show_assigned_instructors() {
        return array(
                // 0: Test no assgnments shows all users.
                array(
                        array(),
                        5,
                        array(
                                $this->get_search_result_row($this->resultscsv, 100),
                                $this->get_search_result_row($this->resultscsv, 101),
                                $this->get_search_result_row_assigning_user(),
                        ),
                ),
                // 1: Test doesn't show existing instructor.
                array(
                        array(
                                array('classid' => 5, 'userid' => 100),
                        ),
                        5,
                        array(
                                $this->get_search_result_row($this->resultscsv, 101),
                                $this->get_search_result_row_assigning_user(),
                        ),
                ),
                // 2: Test multiple existing instructors.
                array(
                        array(
                                array('classid' => 5, 'userid' => 100),
                                array('classid' => 5, 'userid' => 101),
                        ),
                        5,
                        array(
                                $this->get_search_result_row_assigning_user(),
                        ),
                ),
                // 3: Test instructors from other classes show up.
                array(
                        array(
                                array('classid' => 5, 'userid' => 100),
                                array('classid' => 6, 'userid' => 101),
                        ),
                        5,
                        array(
                                $this->get_search_result_row($this->resultscsv, 101),
                                $this->get_search_result_row_assigning_user(),
                        ),
                ),
        );
    }

    /**
     * Test table doesn't show assigned instructors
     * @dataProvider dataprovider_available_doesnt_show_assigned_instructors
     * @param array $associations An array of arrays of parameters to construct instructor associations.
     * @param int $tableclassid The ID of the class we're managing.
     * @param array $expectedresults The expected page of results.
     */
    public function test_available_doesnt_show_assigned_instructors($associations, $tableclassid, $expectedresults) {
        global $USER, $DB, $CFG;
        $userbackup = $USER;

        // Set up permissions.
        $USER = $this->setup_permissions_test();
        $this->give_permission_for_context($USER->id, 'elis/program:assign_class_instructor', get_context_instance(CONTEXT_SYSTEM));

        foreach ($associations as $association) {
            $association = new instructor($association);
            $association->save();
        }

        $table = new deepsight_datatable_instructor_available_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_classid($tableclassid);

        $actualresults = $table->get_search_results(array(), array(), 0, 20);
        $this->assert_search_results($expectedresults, count($expectedresults), $actualresults);

        // Restore user.
        $USER = $userbackup;
    }

    /**
     * Dataprovider for test_available_doesnt_show_assigned_students.
     * @return array Array of test parameters.
     */
    public function dataprovider_available_doesnt_show_assigned_students() {
        return array(
                // 0: Test no assgnments shows all users.
                array(
                        array(),
                        5,
                        array(
                                $this->get_search_result_row($this->resultscsv, 100),
                                $this->get_search_result_row($this->resultscsv, 101),
                                $this->get_search_result_row_assigning_user(),
                        ),
                ),
                // 1: Test doesn't show existing instructor.
                array(
                        array(
                                array('classid' => 5, 'userid' => 100),
                        ),
                        5,
                        array(
                                $this->get_search_result_row($this->resultscsv, 101),
                                $this->get_search_result_row_assigning_user(),
                        ),
                ),
                // 2: Test multiple existing instructors.
                array(
                        array(
                                array('classid' => 5, 'userid' => 100),
                                array('classid' => 5, 'userid' => 101),
                        ),
                        5,
                        array(
                                $this->get_search_result_row_assigning_user(),
                        ),
                ),
                // 3: Test instructors from other classes show up.
                array(
                        array(
                                array('classid' => 5, 'userid' => 100),
                                array('classid' => 6, 'userid' => 101),
                        ),
                        5,
                        array(
                                $this->get_search_result_row($this->resultscsv, 101),
                                $this->get_search_result_row_assigning_user(),
                        ),
                ),
        );
    }

    /**
     * Test table doesn't show assigned students
     * @dataProvider dataprovider_available_doesnt_show_assigned_students
     * @param array $associations An array of arrays of parameters to construct student associations.
     * @param int $tableclassid The ID of the class we're managing.
     * @param array $expectedresults The expected page of results.
     */
    public function test_available_doesnt_show_assigned_students($associations, $tableclassid, $expectedresults) {
        global $USER, $DB, $CFG;
        $userbackup = $USER;

        // Set up permissions.
        $USER = $this->setup_permissions_test();
        $this->give_permission_for_context($USER->id, 'elis/program:assign_class_instructor', get_context_instance(CONTEXT_SYSTEM));

        foreach ($associations as $association) {
            $association = new student($association);
            $association->save();
        }

        $table = new deepsight_datatable_instructor_available_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_classid($tableclassid);

        $actualresults = $table->get_search_results(array(), array(), 0, 20);
        $this->assert_search_results($expectedresults, count($expectedresults), $actualresults);

        // Restore user.
        $USER = $userbackup;
    }

    /**
     * Dataprovider for test_available_doesnt_show_students_instructors.
     * @return array Array of test parameters.
     */
    public function dataprovider_available_doesnt_show_students_instructors() {
        return array(
                // 0: Test no assgnments shows all users.
                array(
                        array(),
                        array(),
                        5,
                        array(
                                $this->get_search_result_row($this->resultscsv, 100),
                                $this->get_search_result_row($this->resultscsv, 101),
                                $this->get_search_result_row_assigning_user(),
                        ),
                ),
                // 1: Test doesn't show instructors or students.
                array(
                        array(
                                array('classid' => 5, 'userid' => 100),
                        ),
                        array(
                                array('classid' => 5, 'userid' => 101),
                        ),
                        5,
                        array(
                                $this->get_search_result_row_assigning_user(),
                        ),
                ),
        );
    }

    /**
     * Test table doesn't show assigned students or instructors
     * @dataProvider dataprovider_available_doesnt_show_students_instructors
     * @param array $associations An array of arrays of parameters to construct student associations.
     * @param int $tableclassid The ID of the class we're managing.
     * @param array $expectedresults The expected page of results.
     */
    public function test_available_doesnt_show_students_instructors($students, $instructors, $tableclassid, $expectedresults) {
        global $USER, $DB, $CFG;
        $userbackup = $USER;

        // Set up permissions.
        $USER = $this->setup_permissions_test();
        $this->give_permission_for_context($USER->id, 'elis/program:assign_class_instructor', get_context_instance(CONTEXT_SYSTEM));

        // Create associations.
        foreach ($students as $student) {
            $student = new student($student);
            $student->save();
        }
        foreach ($instructors as $instructor) {
            $instructor = new instructor($instructor);
            $instructor->save();
        }

        $table = new deepsight_datatable_instructor_available_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_classid($tableclassid);

        $actualresults = $table->get_search_results(array(), array(), 0, 20);
        $this->assert_search_results($expectedresults, count($expectedresults), $actualresults);

        // Restore user.
        $USER = $userbackup;
    }

    /**
     * Dataprovider for test_available_table_obeys_perms.
     * @return array Array of test parameters.
     */
    public function dataprovider_available_table_obeys_perms() {
        return array(
                // 0: Test no associations returns no results.
                array(
                        array(),
                        array(),
                        array(),
                        array(),
                        5,
                        array(),
                ),
                // 1: Test elis/program:assign_class_instructor at system level returns everything.
                array(
                        array(
                            'elis/program:assign_class_instructor' => array('system' => true)
                        ),
                        array(),
                        array(),
                        array(),
                        5,
                        array(
                                $this->get_search_result_row($this->resultscsv, 100),
                                $this->get_search_result_row($this->resultscsv, 101),
                                $this->get_search_result_row_assigning_user(),
                        ),
                ),
                // 2: Test elis/program:assign_class_instructor at the class level returns everything.
                array(
                        array(
                            'elis/program:assign_class_instructor' => array('class' => array(5))
                        ),
                        array(),
                        array(),
                        array(),
                        5,
                        array(
                                $this->get_search_result_row($this->resultscsv, 100),
                                $this->get_search_result_row($this->resultscsv, 101),
                                $this->get_search_result_row_assigning_user(),
                        ),
                ),
                // 3: Test elis/program:assign_class_instructor at the wrong class level returns nothing.
                array(
                        array(
                            'elis/program:assign_class_instructor' => array('class' => array(6))
                        ),
                        array(),
                        array(),
                        array(),
                        5,
                        array(),
                ),
                // 4: Test elis/program:assign_userset_user_class_instructor with no association chain returns nothing.
                array(
                        array(
                            'elis/program:assign_userset_user_class_instructor' => array('userset' => array(2))
                        ),
                        array(),
                        array(),
                        array(),
                        5,
                        array(),
                ),
                // 5: Test elis/program:assign_userset_user_class_instructor with only class-track returns nothing.
                array(
                        array(
                            'elis/program:assign_userset_user_class_instructor' => array('userset' => array(2))
                        ),
                        array(
                                array('classid' => 5, 'trackid' => 101)
                        ),
                        array(),
                        array(),
                        5,
                        array(),
                ),
                // 6: Test elis/program:assign_userset_user_class_instructor with class-track and track-cluster returns nothing.
                array(
                        array(
                            'elis/program:assign_userset_user_class_instructor' => array('userset' => array(2))
                        ),
                        array(
                                array('classid' => 5, 'trackid' => 101),
                        ),
                        array(
                                array('trackid' => 101, 'clusterid' => 2),
                        ),
                        array(),
                        5,
                        array(),
                ),
                // 7: Test elis/program:assign_userset_user_class_instructor with correct assoc chain returns correct user.
                array(
                        array(
                            'elis/program:assign_userset_user_class_instructor' => array('userset' => array(2))
                        ),
                        array(
                                array('classid' => 5, 'trackid' => 101),
                        ),
                        array(
                                array('trackid' => 101, 'clusterid' => 2),
                        ),
                        array(
                                array('clusterid' => 2, 'userid' => 100)
                        ),
                        5,
                        array(
                                $this->get_search_result_row($this->resultscsv, 100),
                        ),
                ),
                // 8: Test elis/program:assign_userset_user_class_instructor with correct assoc chain returns correct users.
                array(
                        array(
                            'elis/program:assign_userset_user_class_instructor' => array('userset' => array(2))
                        ),
                        array(
                                array('classid' => 5, 'trackid' => 101),
                        ),
                        array(
                                array('trackid' => 101, 'clusterid' => 2),
                        ),
                        array(
                                array('clusterid' => 2, 'userid' => 100),
                                array('clusterid' => 2, 'userid' => 101),
                        ),
                        5,
                        array(
                                $this->get_search_result_row($this->resultscsv, 100),
                                $this->get_search_result_row($this->resultscsv, 101),
                        ),
                ),
                // 9: Test elis/program:assign_userset_user_class_instructor with correct assoc chain returns correct users.
                array(
                        array(
                            'elis/program:assign_userset_user_class_instructor' => array('userset' => array(2))
                        ),
                        array(
                                array('classid' => 5, 'trackid' => 101),
                        ),
                        array(
                                array('trackid' => 101, 'clusterid' => 2),
                                array('trackid' => 101, 'clusterid' => 3),
                        ),
                        array(
                                array('clusterid' => 2, 'userid' => 100),
                                array('clusterid' => 3, 'userid' => 101),
                        ),
                        5,
                        array(
                                $this->get_search_result_row($this->resultscsv, 100),
                        ),
                ),
                // 10: Test elis/program:assign_userset_user_class_instructor with correct assoc chain returns correct users.
                array(
                        array(
                            'elis/program:assign_userset_user_class_instructor' => array('userset' => array(2, 3))
                        ),
                        array(
                                array('classid' => 5, 'trackid' => 101),
                        ),
                        array(
                                array('trackid' => 101, 'clusterid' => 2),
                                array('trackid' => 101, 'clusterid' => 3),
                        ),
                        array(
                                array('clusterid' => 2, 'userid' => 100),
                                array('clusterid' => 3, 'userid' => 101),
                        ),
                        5,
                        array(
                                $this->get_search_result_row($this->resultscsv, 100),
                                $this->get_search_result_row($this->resultscsv, 101),
                        ),
                ),
                // 11: Test elis/program:assign_userset_user_class_instructor with correct assoc chain returns correct users.
                array(
                        array(
                            'elis/program:assign_userset_user_class_instructor' => array('userset' => array(2, 3))
                        ),
                        array(
                                array('classid' => 5, 'trackid' => 101),
                                array('classid' => 5, 'trackid' => 102),
                        ),
                        array(
                                array('trackid' => 101, 'clusterid' => 2),
                                array('trackid' => 102, 'clusterid' => 3),
                        ),
                        array(
                                array('clusterid' => 2, 'userid' => 100),
                                array('clusterid' => 3, 'userid' => 101),
                        ),
                        5,
                        array(
                                $this->get_search_result_row($this->resultscsv, 100),
                                $this->get_search_result_row($this->resultscsv, 101),
                        ),
                ),
        );
    }

    /**
     * Test perms.
     * @dataProvider dataprovider_available_table_obeys_perms
     * @param array $associations An array of arrays of parameters to construct student associations.
     * @param int $tableclassid The ID of the class we're managing.
     * @param array $expectedresults The expected page of results.
     */
    public function test_available_table_obeys_perms($perms, $classtracks, $trackclusters, $userclusters, $tableclassid,
                                                     $expectedresults) {
        global $USER, $DB, $CFG;
        $userbackup = $USER;

        // Set up permissions.
        $USER = $this->setup_permissions_test();

        // Set up permissions.
        foreach ($perms as $perm => $contexts) {
            foreach ($contexts as $level => $ids) {
                if ($level === 'system') {
                    $this->give_permission_for_context($USER->id, $perm, get_context_instance(CONTEXT_SYSTEM));
                } else {
                    foreach ($ids as $id) {
                        $ctxclass = 'context_elis_'.$level;
                        $ctx = $ctxclass::instance($id);
                        $this->give_permission_for_context($USER->id, $perm, $ctx);
                    }
                }
            }
        }

        pmclasspage::$contexts = array();
        accesslib_clear_all_caches(true);

        // Create associations.
        $assocarrays = array(
            'classtracks' => 'trackassignment',
            'trackclusters' => 'clustertrack',
            'userclusters' => 'clusterassignment'
        );
        foreach ($assocarrays as $arrayname => $assocclass) {
            foreach ($$arrayname as $association) {
                $association = new $assocclass($association);
                $association->save();
            }
        }

        $table = new deepsight_datatable_instructor_available_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_classid($tableclassid);

        $actualresults = $table->get_search_results(array(), array(), 0, 20);
        $this->assert_search_results($expectedresults, count($expectedresults), $actualresults);

        // Restore user.
        $USER = $userbackup;
    }
}