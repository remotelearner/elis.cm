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
require_once(elispm::lib('data/course.class.php'));

/**
 * Class for testing the pm_update_enrolment_status method and its ability to
 * delegate to a class instance's update_enrolment_status method for passing
 * and locking PM classenrolments
 *
 * TODO: Consider merging with testAutoClassCompletion.php
 */
class pmUpdateEnrolmentStatusTest extends elis_database_test {
    /**
     * Return the list of tables that should be overlayed.
     *
     * @return array The mapping of overlay tables to components
     */
    static protected function get_overlay_tables() {
        return array(course::TABLE => 'elis_program',
                     coursecompletion::TABLE => 'elis_program',
                     pmclass::TABLE => 'elis_program',
                     student::TABLE => 'elis_program',
                     student_grade::TABLE => 'elis_program',
                     user::TABLE => 'elis_program');
    }

    /**
     * Return the list of tables that should be ignored for writes.
     *
     * @return array The mapping of ignored tables to components
     */
    static protected function get_ignored_tables() {
        return array('context' => 'moodle');
    }

    /**
     * Load CSV data from file
     */
    function load_csv_data() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        //need PM course to create PM class
        $dataset->addTable(course::TABLE, elis::component_file('program', 'phpunit/pmcourse.csv'));
        //include learning objectives just for completion sake
        $dataset->addTable(coursecompletion::TABLE, elis::component_file('program', 'phpunit/course_completion.csv'));
        //need PM user to create enrolment
        $dataset->addTable(user::TABLE, elis::component_file('program', 'phpunit/pmusers.csv'));
        //need PM classes to create associations
        $dataset->addTable(pmclass::TABLE, elis::component_file('program', 'phpunit/pmclass.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    /**
     * Data provider for brief testing of the appropriate method
     *
     * @return array A list of two enrolments, one of which should be auto-passed,
     *               including the status indicating which should be passed, and a
     *               list of the associate learning objective grades
     */
    function updateDelegationProvider() {
        //enrolments
        $enrolments = array();
        $enrolments[] = array('classid' => 100,
                              'userid' => 1,
                              'grade' => 0,
                              'completestatusid' => STUSTATUS_NOTCOMPLETE,
                              'expect_pass' => false);
        $enrolments[] = array('classid' => 100,
                              'userid' => 2,
                              'grade' => 100,
                              'completestatusid' => STUSTATUS_NOTCOMPLETE,
                              'expect_pass' => true);
        $enrolments[] = array('classid' => 100,
                              'userid' => 3,
                              'grade' => 100,
                              'completestatusid' => STUSTATUS_NOTCOMPLETE,
                              'expect_pass' => true);
        //learning objective grades
        $classgraded = array();
        $classgraded[] = array('completionid' => 1,
                               'classid' => 100,
                               'userid' => 1,
                               'grade' => 0);
        $classgraded[] = array('completionid' => 1,
                               'classid' => 100,
                               'userid' => 2,
                               'grade' => 100);
        $classgraded[] = array('completionid' => 1,
                               'classid' => 100,
                               'userid' => 3,
                               'grade' => 100);
        //return both pieces of data
        return array(array($enrolments, $classgraded));
    }

    /**
     * Validate that the method we are testing delegates appropriately to the helper
     * method in the appropriate classes
     *
     * @param array $enrolments A list of class enrolment records we are processing
     * @param array $classgraded A list of learning objective grades we are processing
     * @dataProvider updateDelegationProvider
     */
    public function testPmUpdateEnrolmentStatusDelegatesToClassMethod($enrolments, $classgraded) {
        global $DB;

        //NOTE: this unit test does not test all cases because that should be specifically
        //tested for $pmclass->update_enrolment_status

        //necessary data
        $this->load_csv_data();

        //track the final data state for validation
        $expected_enrolments = array();

        foreach ($enrolments as $key => $enrolment) {
            //create student enrolment
            $record = new student($enrolment);
            $record->save();

            $expected_enrolments[] = $enrolment;
            if ($enrolment['expect_pass']) {
                //enrolment will be auto-passed
                $expected_enrolments[$key]['completestatusid'] = STUSTATUS_PASSED;
            }
        }

        foreach ($classgraded as $lograde) {
            //create learning objective grade
            $record = new student_grade($lograde);
            $record->save();
        }

        //pass the appropriate student
        pm_update_enrolment_status();

        //validate count
        $this->assertEquals(count($expected_enrolments), $DB->count_records(student::TABLE));

        //validate records specifically
        foreach ($expected_enrolments as $expected_enrolment) {
            unset($expected_enrolment['expect_pass']);
            $exists = $DB->record_exists(student::TABLE, $expected_enrolment);
            $this->assertTrue($exists);
        }
    }

    /**
     * Validate that the pm_update_enrolment_status method respects its userid
     * parameter, i.e. it can run only for a specific user
     *
     * @param array $enrolments A list of class enrolment records we are processing
     * @param array $classgraded A list of learning objective grades we are processing
     * @dataProvider updateDelegationProvider
     */
    public function testPmUpdateEnrolmentStatusRespectsUseridParameter($enrolments, $classgraded) {
        global $DB;

        //NOTE: this unit test does not test all cases because that should be specifically
        //tested for $pmclass->update_enrolment_status

        //necessary data
        $this->load_csv_data();

        foreach ($enrolments as $key => $enrolment) {
            //create student enrolment
            $record = new student($enrolment);
            $record->save();
        }

        foreach ($classgraded as $lograde) {
            //create learning objective grade
            $record = new student_grade($lograde);
            $record->save();
        }

        //pass the appropriate student
        pm_update_enrolment_status(2);

        //we should have one passed student in the PM class instance,
        //and that student should be the second user
        $enrolments = $DB->get_records(student::TABLE, array('completestatusid' => STUSTATUS_PASSED));
        $this->assertEquals(1, count($enrolments));

        $enrolment = reset($enrolments);
        $this->assertEquals(100, $enrolment->classid);
        $this->assertEquals(2, $enrolment->userid);
        $this->assertEquals(100, $enrolment->grade);
    }
}