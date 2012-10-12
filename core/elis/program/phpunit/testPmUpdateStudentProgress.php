<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2011 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */
require_once(dirname(__FILE__).'/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once(elispm::lib('lib.php'));
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));

/**
 * Class for testing the pm_update_student_progress method and
 * making sure it delegates to the appropriate methods
 */
class pmUpdateStudentProgressTest extends elis_database_test {
    /**
     * Return the list of tables that should be overlayed.
     *
     * @return array The mapping of overlay tables to components
     */
    static protected function get_overlay_tables() {
        return array('config' => 'moodle',
                     'course' => 'moodle',
                     'grade_categories' => 'moodle',
                     'grade_grades' => 'moodle',
                     'grade_items' => 'moodle',
                     'role' => 'moodle',
                     'role_assignments' => 'moodle',
                     'user' => 'moodle',
                     classmoodlecourse::TABLE => 'elis_program',
                     course::TABLE => 'elis_program',
                     coursecompletion::TABLE => 'elis_program',
                     pmclass::TABLE => 'elis_program',
                     student::TABLE => 'elis_program',
                     student_grade::TABLE => 'elis_program',
                     user::TABLE => 'elis_program',
                     usermoodle::TABLE => 'elis_program');
    }

    /**
     * Return the list of tables that should be ignored for writes.
     *
     * @return array The mapping of ignored tables to components
     */
    static protected function get_ignored_tables() {
        return array('cache_flags' => 'moodle',
                     'grade_categories_history' => 'moodle',
                     'grade_items_history' => 'moodle');
    }

    /**
     * Load CSV data from file
     */
    function load_csv_data() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        //need PM course to create PM class
        $dataset->addTable(course::TABLE, elis::component_file('program', 'phpunit/pmcourse.csv'));
        //need PM classes to create associations
        $dataset->addTable(pmclass::TABLE, elis::component_file('program', 'phpunit/pmclass.csv'));
        //need a Moodle course for synchronization
        $dataset->addTable('course', elis::component_file('program', 'phpunit/mdlcourse.csv'));
        //need to associate the PM class with the course for sync
        $dataset->addTable(classmoodlecourse::TABLE, elis::component_file('program', 'phpunit/class_moodle_course.csv'));
        //set up associated users
        $dataset->addTable('user', elis::component_file('program', 'phpunit/mdluser.csv'));
        $dataset->addTable(user::TABLE, elis::component_file('program', 'phpunit/pmuser.csv'));
        $dataset->addTable(usermoodle::TABLE, elis::component_file('program', 'phpunit/user_moodle.csv'));
        //set up learning objectives
        $dataset->addTable(coursecompletion::TABLE, elis::component_file('program', 'phpunit/course_completion.csv'));
        //set up grade_items
        $dataset->addTable('grade_items', elis::component_file('program', 'phpunit/grade_items.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    /**
     * Data provider for validating basic user enrolment synchronization
     *
     * @return array An array containing the list of role assignments to create
     *               and Moodle grade_item grades to assign
     */
    function updateStudentProgressProvider() {
        //role assignments
        $role_assignments = array();
        $role_assignments[] = array('roleid' => 1,
                                    'userid' => 100,
                                    'contextid' => 1);
        $role_assignments[] = array('roleid' => 1,
                                    'userid' => 101,
                                    'contextid' => 1);
        //grade item grades
        $item_grades = array();
        //corresponds to required learning objective
        $item_grades[] = array('itemid' => 1,
                               'userid' => 100,
                               'finalgrade' => 100);
        //course grade
        $item_grades[] = array('itemid' => 3,
                               'userid' => 100,
                               'finalgrade' => 100);
        //corresponds to required learning objective
        $item_grades[] = array('itemid' => 1,
                               'userid' => 101,
                               'finalgrade' => 100);
        //course grade
        $item_grades[] = array('itemid' => 3,
                               'userid' => 101,
                               'finalgrade' => 100);

        return(array(array($role_assignments, $item_grades)));
    }

    /**
     * Validate that pm_update_student_progress syncs all necessary enrolment
     * information and "completes" a student
     *
     * @param array $role_assignments List of role assignment records to test with
     * @param array $item_grades List of grade item grades to test with
     * @dataProvider updateStudentProgressProvider
     */
    function testPmUpdateStudentProgressDelegateToEnrolmentSyncAndUpdate($role_assignments, $item_grades) {
        global $DB;

        //necessary data
        $this->load_csv_data();

        //make sure the context is set up
        context_course::instance(1);

        //set up our test role
        create_role('gradedrole', 'gradedrole', 'gradedrole');
        set_config('gradebookroles', '1');

        //create all of our test role assignments
        foreach ($role_assignments as $role_assignment) {
            role_assign($role_assignment['roleid'], $role_assignment['userid'], $role_assignment['contextid']);
        }

        //assign item grades
        foreach ($item_grades as $item_grade) {
            $DB->insert_record('grade_grades', $item_grade);
        }

        //perform the sync
        pm_update_student_progress();

        //we should have two passed students in the PM class instance
        $count_completed_enrolments = $DB->count_records(student::TABLE, array('completestatusid' => STUSTATUS_PASSED,
                                                                               'grade' => 100));
        $this->assertEquals(2, $count_completed_enrolments);

        //we should have two completed learning objectives
        $count_completed_elements = $DB->count_records(student_grade::TABLE, array('locked' => 1,
                                                                                   'grade' => 100));
        $this->assertEquals(2, $count_completed_elements);
    }

    /**
     * Validate that the pm_update_student_progress method respects its userid
     * parameter, i.e. can run only for a specific user
     *
     * @param array $role_assignments List of role assignment records to test with
     * @param array $item_grades List of grade item grades to test with
     * @dataProvider updateStudentProgressProvider
     */
    function testPmUpdateStudentProgressRespectsUseridParameter($role_assignments, $item_grades) {
        global $DB;

        //necessary data
        $this->load_csv_data();

        //make sure the context is set up
        context_course::instance(1);

        //set up our test role
        create_role('gradedrole', 'gradedrole', 'gradedrole');
        set_config('gradebookroles', '1');

        //create all of our test role assignments
        foreach ($role_assignments as $role_assignment) {
            role_assign($role_assignment['roleid'], $role_assignment['userid'], $role_assignment['contextid']);
        }

        //assign item grades
        foreach ($item_grades as $item_grade) {
            $DB->insert_record('grade_grades', $item_grade);
        }

        //perform the sync for the first user
        pm_update_student_progress(100);

        //we should have one passed student in the PM class instance,
        //and that student should be the first user
        $completed_enrolments = $DB->get_records(student::TABLE,
                                     array('completestatusid' => STUSTATUS_PASSED,
                                           'userid' => 103));
        $this->assertEquals(1, count($completed_enrolments));

        $enrolment = reset($completed_enrolments);
        $this->assertEquals(103, $enrolment->userid);
        $this->assertEquals(100, $enrolment->grade);

        //we should have one passed learning objective for the first user
        $completed_elements = $DB->get_records(student_grade::TABLE,
                                       array('locked' => 1, 'userid' => 103));
        $this->assertEquals(1, count($completed_elements));

        $element = reset($completed_elements);
        $this->assertEquals(103, $element->userid);
        $this->assertEquals(100, $element->grade);
    }
}
