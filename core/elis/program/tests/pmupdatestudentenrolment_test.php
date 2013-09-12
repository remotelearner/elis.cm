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
require_once(elispm::lib('data/student.class.php'));

/**
 * Class for testing the pm_update_student_enrolment method and its ability to fail students whose class enrolment are in
 * progress and past the class end date.
 * @group elis_program
 */
class pmupdatestudentenrolment_testcase extends elis_database_test {

    /**
     * Load initial data from CSVs.
     */
    public function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            // Need PM course to create PM class.
            course::TABLE => elis::component_file('program', 'tests/fixtures/pmcourse.csv'),
            // Need PM user to create enrolment.
            user::TABLE => elis::component_file('program', 'tests/fixtures/pmusers.csv'),
            // Needed to prevent messaging errors.
            'user' => elis::component_file('program', 'tests/fixtures/mdlusers.csv'),
            // Need PM classes to create associations.
            pmclass::TABLE => elis::component_file('program', 'tests/fixtures/pmclass.csv'),
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * Data provider for testing validation condition on enrolment status.
     * @return array A list of enrolments with a variety of enrolment statuses
     */
    public function dataprovider_enrolmentstatus() {
        $associations = array(
                array(
                    'classid' => 100,
                    'userid' => 1,
                    'completestatusid' => STUSTATUS_PASSED,
                    'completetime' => 1000000000,
                    'endtime' => 1000000000
                ),
                array(
                    'classid' => 101,
                    'userid' => 1,
                    'completestatusid' => STUSTATUS_FAILED,
                    'completetime' => 1000000000,
                    'endtime' => 1000000000
                ),
                array(
                    'classid' => 100,
                    'userid' => 2,
                    'completestatusid' => STUSTATUS_FAILED,
                    'completetime' => 1000000000,
                    'endtime' => 1000000000
                ),
                array(
                    'classid' => 101,
                    'userid' => 2,
                    'completestatusid' => STUSTATUS_NOTCOMPLETE,
                    'completetime' => 1000000000,
                    'endtime' => 1000000000
                ),
                array(
                    'classid' => 100,
                    'userid' => 3,
                    'completestatusid' => STUSTATUS_NOTCOMPLETE,
                    'completetime' => 1000000000,
                    'endtime' => 1000000000
                ),
                array(
                    'classid' => 101,
                    'userid' => 3,
                    'completestatusid' => STUSTATUS_PASSED,
                    'completetime' => 1000000000,
                    'endtime' => 1000000000
                ),
        );

        return array(array($associations));
    }

    /**
     * Validate that the method respect enrolment status.
     *
     * @param array $associations A list of enrolments / associations to validate against.
     * @dataProvider dataprovider_enrolmentstatus
     */
    public function test_pmupdatestudentenrolmentrespectsenrolmentstatus($associations) {
        global $DB;

        // Prevent messaging emails from being sent.
        set_config('noemailever', true);

        // Necessary data.
        $this->load_csv_data();

        // Track the final data state for validation.
        $expectedassociations = array();

        foreach ($associations as $key => $association) {
            // Create student enrolment.
            $record = new student($association);
            $record->save();

            $expectedassociations[] = $association;

            if ($association['completestatusid'] == STUSTATUS_NOTCOMPLETE) {
                // Enrolment will lapse.
                $expectedassociations[$key]['completestatusid'] = STUSTATUS_FAILED;
                // Hard to reliably test timestamps.
                unset($expectedassociations[$key]['completetime']);
            }
        }

        // Fail expired students.
        $this->quiet_pm_update_student_enrolment();

        // Validate count.
        $this->assertEquals(count($expectedassociations), $DB->count_records(student::TABLE));

        // Validate records specifically.
        foreach ($expectedassociations as $i => $expectedassociation) {
            $exists = $DB->record_exists(student::TABLE, $expectedassociation);
            $this->assertTrue($exists, 'could not find: '.$i);
        }
    }

    /**
     * Validate that the method respect enrolment status and user id, i.e. can run only for a specific user
     *
     * @param array $associations A list of enrolments / associations to validate against.
     * @dataProvider dataprovider_enrolmentstatus
     */
    public function test_pmupdatestudentenrolmentrespectsenrolmentstatusanduserid($associations) {
        global $DB;

        // Prevent messaging emails from being sent.
        set_config('noemailever', true);

        // Necessary data.
        $this->load_csv_data();

        // Track the final data state for validation.
        $expectedassociations = array();

        foreach ($associations as $key => $association) {
            // Create student enrolment.
            $record = new student($association);
            $record->save();

            $expectedassociations[] = $association;

            if ($association['completestatusid'] == STUSTATUS_NOTCOMPLETE && $association['userid'] == 2) {
                // Enrolment will lapse.
                $expectedassociations[$key]['completestatusid'] = STUSTATUS_FAILED;
                // Hard to reliably test timestamps.
                unset($expectedassociations[$key]['completetime']);
            }
        }

        // Fail specific expired student.
        $this->quiet_pm_update_student_enrolment(2);

        // Validate count.
        $this->assertEquals(count($expectedassociations), $DB->count_records(student::TABLE));

        // Validate records specifically.
        foreach ($expectedassociations as $expectedassociation) {
            $exists = $DB->record_exists(student::TABLE, $expectedassociation);
            $this->assertTrue($exists);
        }
    }

    /**
     * Data provider for testing validation condition on enrolment end times
     *
     * @return array A list of enrolments with a variety of enrolment end times
     */
    public function dataprovider_enrolmentendtime() {
        $associations = array(
                array(
                    'classid' => 100,
                    'userid' => 1,
                    'completestatusid' => STUSTATUS_NOTCOMPLETE,
                    'completetime' => 1000000000,
                    'endtime' => 0,
                ),
                array(
                    'classid' => 101,
                    'userid' => 1,
                    'completestatusid' => STUSTATUS_NOTCOMPLETE,
                    'completetime' => time() + 1000,
                    'endtime' => 0,
                ),
                array(
                    'classid' => 100,
                    'userid' => 2,
                    'completestatusid' => STUSTATUS_NOTCOMPLETE,
                    'completetime' => 1000000000,
                    'endtime' => time() + 1000,
                ),
                array(
                    'classid' => 101,
                    'userid' => 2,
                    'completestatusid' => STUSTATUS_NOTCOMPLETE,
                    'completetime' => 1000000000,
                    'endtime' => time() - 1000,
                ),
                array(
                    'classid' => 100,
                    'userid' => 3,
                    'completestatusid' => STUSTATUS_NOTCOMPLETE,
                    'completetime' => 1000000000,
                    'endtime' => time() - 1000,
                ),
                array(
                    'classid' => 101,
                    'userid' => 3,
                    'completestatusid' => STUSTATUS_NOTCOMPLETE,
                    'completetime' => 1000000000,
                    'endtime' => 1000000000,
                ),
        );

        return array(array($associations));
    }

    /**
     * Validate that the method respects end time.
     *
     * @param array $associations A list of enrolments / associations to validate against.
     * @dataProvider dataprovider_enrolmentendtime
     */
    public function test_pmupdatestudentenrolmentrespectsenrolmentendtime($associations) {
        global $DB;

        // Prevent messaging emails from being sent.
        set_config('noemailever', true);

        // Necessary data.
        $this->load_csv_data();

        // Track the final data state for validation.
        $expectedassociations = array();

        foreach ($associations as $key => $association) {
            // Create student enrolment.
            $record = new student($association);
            $record->save();

            $expectedassociations[] = $association;

            if ($association['endtime'] > 0 && $association['endtime'] < time()) {
                // Enrolment will lapse.
                $expectedassociations[$key]['completestatusid'] = STUSTATUS_FAILED;
                // Hard to reliably test timestamps.
                unset($expectedassociations[$key]['completetime']);
            }
        }

        // Fail expired students.
        $this->quiet_pm_update_student_enrolment();

        // Validate count.
        $this->assertEquals(count($expectedassociations), $DB->count_records(student::TABLE));

        // Validate records specifically.
        foreach ($expectedassociations as $expectedassociation) {
            $exists = $DB->record_exists(student::TABLE, $expectedassociation);
            $this->assertTrue($exists);
        }
    }

    /**
     * Validate that the method respects end time and user id, i.e. can run only for a specific user.
     *
     * @param array $associations A list of enrolments / associations to validate against.
     * @dataProvider dataprovider_enrolmentendtime
     */
    public function test_pmupdatestudentenrolmentrespectsenrolmentendtimeanduserid($associations) {
        global $DB;

        // Prevent messaging emails from being sent.
        set_config('noemailever', true);

        // Necessary data.
        $this->load_csv_data();

        // Track the final data state for validation.
        $expectedassociations = array();

        foreach ($associations as $key => $association) {
            // Create student enrolment.
            $record = new student($association);
            $record->save();

            $expectedassociations[] = $association;

            if ($association['endtime'] > 0 && $association['endtime'] < time() && $association['userid'] == 2) {
                // Enrolment will lapse.
                $expectedassociations[$key]['completestatusid'] = STUSTATUS_FAILED;
                // Hard to reliably test timestamps.
                unset($expectedassociations[$key]['completetime']);
            }
        }

        // Fail specific expired student.
        $this->quiet_pm_update_student_enrolment(2);

        // Validate count.
        $this->assertEquals(count($expectedassociations), $DB->count_records(student::TABLE));

        // Validate records specifically.
        foreach ($expectedassociations as $expectedassociation) {
            $exists = $DB->record_exists(student::TABLE, $expectedassociation);
            $this->assertTrue($exists);
        }
    }

    /**
     * Data provider for testing successful execution of the method.
     * @return array A list of enrolments satisfying the necessary criteria.
     */
    public function dataprovider_enrolmentfail() {
        $associations = array();

        for ($i = 1; $i <= 3; $i++) {
            $associations[] = array(
                'classid' => 100,
                'userid' => $i,
                'completestatusid' => STUSTATUS_NOTCOMPLETE,
                'completetime' => 1000000000,
                'endtime' => 1000000000
            );
        }

        return array(array($associations));
    }

    /**
     * Validate the "succss" case of the method, i.e. failing students.
     *
     * @param array $associations A list of enrolments / associations to validate against.
     * @dataProvider dataprovider_enrolmentfail
     */
    public function test_pmupdatestudentenrolmentfailsstudents($associations) {
        global $DB;

        // Prevent messaging emails from being sent.
        set_config('noemailever', true);

        // Necessary data.
        $this->load_csv_data();

        // Track the final data state for validation.
        $expectedassociations = array();

        foreach ($associations as $key => $association) {
            // Create student enrolment.
            $record = new student($association);
            $record->save();

            // All enrolment will be set to failed.
            $expectedassociations[] = $association;
            $expectedassociations[$key]['completestatusid'] = STUSTATUS_FAILED;
            // Hard to reliably test timestamps.
            unset($expectedassociations[$key]['completetime']);
        }

        // Fail expired students.
        $this->quiet_pm_update_student_enrolment();

        // Validate count.
        $this->assertEquals(count($expectedassociations), $DB->count_records(student::TABLE));

        // Validate data specifically.
        foreach ($expectedassociations as $expectedassociation) {
            $exists = $DB->record_exists(student::TABLE, $expectedassociation);
            $this->assertTrue($exists);
        }
    }

    /**
     * Validate the "succss" case of the method, i.e. failing students for a specific user id, i.e. can only for a specific user.
     *
     * @param array $associations A list of enrolments / associations to validate against.
     * @dataProvider dataprovider_enrolmentfail
     */
    public function test_pmupdatestudentenrolmentfailsstudentwithspecificuserid($associations) {
        global $DB;

        // Prevent messaging emails from being sent.
        set_config('noemailever', true);

        // Necessary data.
        $this->load_csv_data();

        // Track the final data state for validation.
        $expectedassociations = array();

        foreach ($associations as $key => $association) {
            // Create student enrolment.
            $record = new student($association);
            $record->save();

            if ($association['userid'] == 1) {
                // Specific student will be set to failed.
                $expectedassociations[] = $association;
                $expectedassociations[$key]['completestatusid'] = STUSTATUS_FAILED;
                // Hard to reliably test timestamps.
                unset($expectedassociations[$key]['completetime']);
            }
        }

        // Fail specific expired students.
        $this->quiet_pm_update_student_enrolment(1);

        // Validate count.
        $this->assertEquals(count($expectedassociations), $DB->count_records(student::TABLE, array('userid' => 1)));

        // Validate data specifically.
        foreach ($expectedassociations as $expectedassociation) {
            $exists = $DB->record_exists(student::TABLE, $expectedassociation);
            $this->assertTrue($exists);
        }
    }

    /**
     * Performs pm_update_student_enrolment without producing $CFG->noemailever notices in unit test output.
     * @param int $pmuserid  optional userid to update, default(0) updates all users
     */
    protected function quiet_pm_update_student_enrolment($pmuserid = 0) {
        $sink = $this->redirectMessages();
        pm_update_student_enrolment($pmuserid);
    }
}
