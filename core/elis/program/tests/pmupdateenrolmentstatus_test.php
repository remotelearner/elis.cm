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
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__).'/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');

// Libs.
require_once(elispm::lib('lib.php'));
require_once(elispm::lib('data/course.class.php'));

/**
 * Class for testing the pm_update_enrolment_status method and its ability to delegate to a class instance's
 * update_enrolment_status method for passing and locking PM classenrolments.
 *
 * TODO: Consider merging with testAutoClassCompletion.php
 *
 * @group elis_program
 */
class pmupdateenrolmentstatus_testcase extends elis_database_test {

    /**
     * Load initial data from CSVs.
     */
    public function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            // Need PM course to create PM class.
            course::TABLE => elis::component_file('program', 'tests/fixtures/pmcourse.csv'),
            // Include learning objectives just for completion sake.
            coursecompletion::TABLE => elis::component_file('program', 'tests/fixtures/course_completion.csv'),
            // Need PM user to create enrolment.
            user::TABLE => elis::component_file('program', 'tests/fixtures/pmusers.csv'),
            // Need PM classes to create associations.
            pmclass::TABLE => elis::component_file('program', 'tests/fixtures/pmclass.csv'),
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * Data provider for brief testing of the appropriate method
     *
     * @return array A list of two enrolments, one of which should be auto-passed, including the status indicating which should
     *               be passed, and a list of the associate learning objective grades
     */
    public function dataprovider_updatedelegation() {
        // Enrolments.
        $enrolments = array(
                array(
                    'classid' => 100,
                    'userid' => 1,
                    'grade' => 0,
                    'completestatusid' => STUSTATUS_NOTCOMPLETE,
                    'expect_pass' => false
                ),
                array(
                    'classid' => 100,
                    'userid' => 2,
                    'grade' => 100,
                    'completestatusid' => STUSTATUS_NOTCOMPLETE,
                    'expect_pass' => true
                ),
                array(
                    'classid' => 100,
                    'userid' => 3,
                    'grade' => 100,
                    'completestatusid' => STUSTATUS_NOTCOMPLETE,
                    'expect_pass' => true,
                )
        );

        // Learning objective grades.
        $classgraded = array(
                array(
                    'completionid' => 1,
                    'classid' => 100,
                    'userid' => 1,
                    'grade' => 0,
                ),
                array(
                    'completionid' => 1,
                    'classid' => 100,
                    'userid' => 2,
                    'grade' => 100,
                ),
                array(
                    'completionid' => 1,
                    'classid' => 100,
                    'userid' => 3,
                    'grade' => 100,
                ),
        );

        return array(array($enrolments, $classgraded));
    }

    /**
     * Validate that the method we are testing delegates appropriately to the helper method in the appropriate classes
     * NOTE: this unit test does not test all cases because that should be specifically tested for $pmclass->update_enrolment_status
     *
     * @param array $enrolments A list of class enrolment records we are processing
     * @param array $classgraded A list of learning objective grades we are processing
     * @dataProvider dataprovider_updatedelegation
     */
    public function test_pmupdateenrolmentstatusdelegatestoclassmethod($enrolments, $classgraded) {
        global $DB;

        // Necessary data.
        $this->load_csv_data();

        // Track the final data state for validation.
        $expectedenrolments = array();

        foreach ($enrolments as $key => $enrolment) {
            // Create student enrolment.
            $record = new student($enrolment);
            $record->save();

            $expectedenrolments[] = $enrolment;
            if ($enrolment['expect_pass']) {
                // Enrolment will be auto-passed.
                $expectedenrolments[$key]['completestatusid'] = STUSTATUS_PASSED;
            }
        }

        foreach ($classgraded as $lograde) {
            // Create learning objective grade.
            $record = new student_grade($lograde);
            $record->save();
        }

        // Pass the appropriate student.
        pm_update_enrolment_status();

        // Validate count.
        $this->assertEquals(count($expectedenrolments), $DB->count_records(student::TABLE));

        // Validate records specifically.
        foreach ($expectedenrolments as $expectedenrolment) {
            unset($expectedenrolment['expect_pass']);
            $exists = $DB->record_exists(student::TABLE, $expectedenrolment);
            $this->assertTrue($exists);
        }
    }

    /**
     * Validate that the pm_update_enrolment_status method respects its userid parameter, i.e. it can run only for a specific user.
     * NOTE: this unit test does not test all cases because that should be specifically tested for $pmclass->update_enrolment_status
     *
     * @param array $enrolments A list of class enrolment records we are processing
     * @param array $classgraded A list of learning objective grades we are processing
     * @dataProvider dataprovider_updatedelegation
     */
    public function test_pmupdateenrolmentstatusrespectsuseridparameter($enrolments, $classgraded) {
        global $DB;

        // Necessary data.
        $this->load_csv_data();

        foreach ($enrolments as $key => $enrolment) {
            // Create student enrolment.
            $record = new student($enrolment);
            $record->save();
        }

        foreach ($classgraded as $lograde) {
            // Create learning objective grade.
            $record = new student_grade($lograde);
            $record->save();
        }

        // Pass the appropriate student.
        pm_update_enrolment_status(2);

        // We should have one passed student in the PM class instance, and that student should be the second user.
        $enrolments = $DB->get_records(student::TABLE, array('completestatusid' => STUSTATUS_PASSED));
        $this->assertEquals(1, count($enrolments));

        $enrolment = reset($enrolments);
        $this->assertEquals(100, $enrolment->classid);
        $this->assertEquals(2, $enrolment->userid);
        $this->assertEquals(100, $enrolment->grade);
    }
}