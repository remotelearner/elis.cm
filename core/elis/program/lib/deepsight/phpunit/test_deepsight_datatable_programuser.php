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

require_once(elispm::lib('deepsightpage.class.php'));
require_once(elispm::lib('selectionpage.class.php'));

require_once(elispm::lib('data/clusterassignment.class.php'));
require_once(elispm::lib('data/clustercurriculum.class.php'));
require_once(elispm::lib('data/curriculum.class.php'));
require_once(elispm::lib('data/curriculumstudent.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/userset.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));

/**
 * Mock programuser_assigned datatable class to expose protected methods and properties.
 */
class deepsight_datatable_programuser_assigned_mock extends deepsight_datatable_programuser_assigned {

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
 * Mock programuser_available datatable class to expose protected methods and properties.
 */
class deepsight_datatable_programuser_available_mock extends deepsight_datatable_programuser_available {

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
 * Tests programuser datatable functions.
 */
class deepsight_datatable_programuser_test extends deepsight_datatable_searchresults_test {

    /**
     * Return overlay tables.
     * @return array An array of overlay tables.
     */
    protected static function get_overlay_tables() {
        $overlay = array(
            clusterassignment::TABLE => 'elis_program',
            clustercurriculum::TABLE => 'elis_program',
            curriculum::TABLE => 'elis_program',
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
        $dataset->addTable(curriculum::TABLE, elispm::lib('deepsight/phpunit/csv_program.csv'));
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
     *
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
     *
     * @return array Array of test parameters.
     */
    public function dataprovider_assigned_shows_assigned_users() {
        return array(
                // Test table shows nothing when no associations present.
                array(
                        array(),
                        101,
                        array(),
                        0,
                ),
                // Test table shows nothing when no associations present for current program.
                array(
                        array(
                                array('curriculumid' => 6, 'userid' => 100),
                        ),
                        5,
                        array(),
                        0,
                ),
                // Test table shows existing associations.
                array(
                        array(
                                array('curriculumid' => 5, 'userid' => 101),
                        ),
                        5,
                        array(
                                $this->get_search_result_row('csv_user.csv', 101),
                        ),
                        1,
                ),
                // Test table shows existing associations.
                array(
                        array(
                                array('curriculumid' => 5, 'userid' => 100),
                                array('curriculumid' => 5, 'userid' => 101),
                        ),
                        5,
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
     *
     * @dataProvider dataprovider_assigned_shows_assigned_users
     * @param array $associations An array of arrays of parameters to construct curriculumstudent associations.
     * @param int $tableprogramid The program ID of the program we're managing.
     * @param array $expectedresults The expected page of results.
     * @param int $expectedtotal The expected number of total results.
     */
    public function test_assigned_shows_assigned_users($associations, $tableprogramid, $expectedresults, $expectedtotal) {
        global $DB;

        foreach ($associations as $association) {
            $curriculumstudent = new curriculumstudent($association);
            $curriculumstudent->save();
        }

        $table = new deepsight_datatable_programuser_assigned_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_programid($tableprogramid);

        $actualresults = $table->get_search_results(array(), array(), 0, 20);
        $this->assert_search_results($expectedresults, $expectedtotal, $actualresults);
    }

    /**
     * Dataprovider for test_available_program_enrol_perms.
     *
     * @return array Array of test parameters.
     */
    public function dataprovider_available_program_enrol_perms() {
        return array(
                // Test empty results when user has no permissions.
                array(
                        array(),
                        100,
                        array(),
                        0,
                ),
                // Test all users are returned when user has permission at the system context.
                array(
                        array('system' => 1),
                        100,
                        array(
                                $this->get_search_result_row('csv_user.csv', 100),
                                $this->get_search_result_row('csv_user.csv', 101),
                                $this->get_search_result_row_assigning_user(),
                        ),
                        3,
                ),
                // Test no users are returned when the user has permission at wrong program context.
                array(
                        array('program' => 6),
                        5,
                        array(),
                        0,
                ),
                // Test all users are returned when the user has permission at right program context.
                array(
                        array('program' => 5),
                        5,
                        array(
                                $this->get_search_result_row('csv_user.csv', 100),
                                $this->get_search_result_row('csv_user.csv', 101),
                                $this->get_search_result_row_assigning_user(),
                        ),
                        3,
                ),
        );
    }

    /**
     * Test available table shows correct search results based on elis/program:program_enrol perms.
     *
     * @dataProvider dataprovider_available_program_enrol_perms
     * @param array $permcontexts An array of context information for which to assign the elis/program:program_enrol permission.
     * @param int $tableprogramid The ID of the program to use for the table.
     * @param array $expectedresults An array of expected search results.
     * @param int $expectedtotal The expected total number of search results.
     */
    public function test_available_program_enrol_perms($permcontexts, $tableprogramid, $expectedresults, $expectedtotal) {
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
                case 'program':
                    $permcontext = context_elis_program::instance($id);
                    break;
            }
            $this->give_permission_for_context($USER->id, 'elis/program:program_enrol', $permcontext);
        }

        // Construct test table.
        $table = new deepsight_datatable_programuser_available_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_programid($tableprogramid);

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
                        100,
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
                                array('curriculumid' => 100, 'userid' => 101),
                        ),
                        100,
                        array(
                                $this->get_search_result_row('csv_user.csv', 100),
                                $this->get_search_result_row_assigning_user(),
                        ),
                        2,
                ),
                // Test multiple assignments.
                array(
                        array(
                                array('curriculumid' => 100, 'userid' => 100),
                                array('curriculumid' => 100, 'userid' => 101),
                        ),
                        100,
                        array(
                                $this->get_search_result_row_assigning_user(),
                        ),
                        1,
                ),
                // Test only assignments for the current program affect results.
                array(
                        array(
                                array('curriculumid' => 101, 'userid' => 100),
                                array('curriculumid' => 100, 'userid' => 101),
                        ),
                        100,
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
     * @param array $associations An array of arrays of parameters to construct curriculumstudent associations.
     * @param int $tableprogramid The ID of the program we're going to manage.
     * @param array $expectedresults The expected page of results.
     * @param int $expectedtotal The expected number of total results.
     */
    public function test_available_doesnt_show_assigned_users($associations, $tableprogramid, $expectedresults, $expectedtotal) {
        global $USER, $DB, $CFG;
        $userbackup = $USER;

        // Set up permissions.
        $USER = $this->setup_permissions_test();
        $this->give_permission_for_context($USER->id, 'elis/program:program_enrol', get_context_instance(CONTEXT_SYSTEM));

        foreach ($associations as $association) {
            $curriculumstudent = new curriculumstudent($association);
            $curriculumstudent->save();
        }

        // Construct test table.
        $table = new deepsight_datatable_programuser_available_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_programid($tableprogramid);

        // Perform test.
        $actualresults = $table->get_search_results(array(), array(), 0, 20);

        // Verify result.
        $this->assert_search_results($expectedresults, $expectedtotal, $actualresults);

        // Restore user.
        $USER = $userbackup;
    }

    /**
     * Dataprovider for test_available_permissions_program_enrol_userset_user.
     * @return array Array of test parameters.
     */
    public function dataprovider_available_permissions_program_enrol_userset_user() {
        return array(
                // 0: Test when no permissions or associations exist, no users are returned.
                array(
                        array(),
                        array(),
                        array(),
                        5,
                        array(),
                        0,
                ),
                // 1: Test when associations exist but permissions are not present, users are not returned.
                array(
                        array(),
                        array(
                                array('userid' => 101, 'clusterid' => 3),
                        ),
                        array(
                                array('clusterid' => 3, 'curriculumid' => 5),
                        ),
                        5,
                        array(),
                        0,
                ),
                // 2: Test when permissions exist but no associations exist, users are not returned.
                array(
                        array(3),
                        array(),
                        array(),
                        5,
                        array(),
                        0,
                ),
                // 3: Test when permissions exist, and users are assigned to userset, but program is not assigned, users are not
                // returned.
                array(
                        array(3),
                        array(
                                array('userid' => 101, 'clusterid' => 3),
                        ),
                        array(),
                        5,
                        array(),
                        0,
                ),
                // 4: Test when permissions exist, and program is assigned to userset, but users are not assigned, users are not
                // returned.
                array(
                        array(3),
                        array(),
                        array(
                                array('clusterid' => 3, 'curriculumid' => 5),
                        ),
                        5,
                        array(),
                        0,
                ),
                // 5: Test when permissions exist, and associations exist, users are returned.
                array(
                        array(3),
                        array(
                                array('userid' => 100, 'clusterid' => 3),
                        ),
                        array(
                                array('clusterid' => 3, 'curriculumid' => 5),
                        ),
                        5,
                        array(
                                $this->get_search_result_row('csv_user.csv', 100),
                        ),
                        1,
                ),
                // 6: Test when permissions exist, and associations exist for multiple, users are returned.
                array(
                        array(3),
                        array(
                                array('userid' => 100, 'clusterid' => 3),
                                array('userid' => 101, 'clusterid' => 3),
                        ),
                        array(
                                array('clusterid' => 3, 'curriculumid' => 6),
                        ),
                        6,
                        array(
                                $this->get_search_result_row('csv_user.csv', 100),
                                $this->get_search_result_row('csv_user.csv', 101),
                        ),
                        2,
                ),
                // 7: Test that user associations to other clusters do not appear.
                array(
                        array(3),
                        array(
                                array('userid' => 100, 'clusterid' => 3),
                                array('userid' => 101, 'clusterid' => 4),
                        ),
                        array(
                                array('clusterid' => 3, 'curriculumid' => 7),
                        ),
                        7,
                        array(
                                $this->get_search_result_row('csv_user.csv', 100),
                        ),
                        1,
                ),
                // 8: Test that program associations to other, non-permissioned, clusters do not appear.
                array(
                        array(3),
                        array(
                                array('userid' => 100, 'clusterid' => 3),
                                array('userid' => 101, 'clusterid' => 4),
                        ),
                        array(
                                array('clusterid' => 3, 'curriculumid' => 6),
                                array('clusterid' => 4, 'curriculumid' => 6),
                        ),
                        6,
                        array(
                                $this->get_search_result_row('csv_user.csv', 100),
                        ),
                        1,
                ),
                // 9: Test that permissions on multiple clusters return the users in all permissioned clusters.
                array(
                        array(3, 4),
                        array(
                                array('userid' => 100, 'clusterid' => 3),
                                array('userid' => 101, 'clusterid' => 4),
                        ),
                        array(
                                array('clusterid' => 3, 'curriculumid' => 6),
                                array('clusterid' => 4, 'curriculumid' => 6),
                        ),
                        6,
                        array(
                                $this->get_search_result_row('csv_user.csv', 100),
                                $this->get_search_result_row('csv_user.csv', 101),
                        ),
                        2,
                ),
        );
    }

    /**
     * Test available table obeys program_enrol_userset_users permission.
     *
     * Test available table shows only users that are in clusters where:
     *     - the assigner has the elis/program:program_enrol_userset_user permission
     *     - the current program is associated with the cluster.
     *
     * @dataProvider dataprovider_available_permissions_program_enrol_userset_user
     * @param array $usersetidsforperm An array of userset IDs to assign the elis/program:program_enrol_userset_user on.
     * @param array $clusterassignments An array of arrays of parameters to construct clusterassignments with.
     * @param array $clustercurriculums An array of arrays of parameters to construct clustercurriculums with.
     * @param int $tableprogramid The id of the program to manage associations for.
     * @param array $expectedresults The expected page of results.
     * @param int $expectedtotal The expected number of total results.
     */
    public function test_available_permissions_program_enrol_userset_user($usersetidsforperm, $clusterassignments,
                                                                          $clustercurriculums, $tableprogramid, $expectedresults,
                                                                          $expectedtotal) {
        global $USER, $DB, $CFG;

        $userbackup = $USER;

        // Import usersets.
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(userset::TABLE, elispm::lib('deepsight/phpunit/csv_userset.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);

        // Set up permissions.
        $USER = $this->setup_permissions_test();
        $capability = 'elis/program:program_enrol_userset_user';
        foreach ($usersetidsforperm as $usersetid) {
            $this->give_permission_for_context($USER->id, $capability, context_elis_userset::instance($usersetid));
        }

        // Create clusterassignments.
        foreach ($clusterassignments as $clusterassignment) {
            $clusterassignment = new clusterassignment($clusterassignment);
            $clusterassignment->save();
        }

        // Create clustercurriculums.
        foreach ($clustercurriculums as $clustercurriculum) {
            $clustercurriculum = new clustercurriculum($clustercurriculum);
            $clustercurriculum->save();
        }

        // Construct test table.
        $table = new deepsight_datatable_programuser_available_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_programid($tableprogramid);

        // Perform test.
        $actualresults = $table->get_search_results(array(), array(), 0, 20);

        // Verify.
        $this->assert_search_results($expectedresults, $expectedtotal, $actualresults);
    }
}