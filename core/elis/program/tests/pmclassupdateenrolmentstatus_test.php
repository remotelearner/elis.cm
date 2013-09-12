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
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));

/**
 * Class for testing the update_enrolment_status method belonging to the pmclass class
 * @group elis_program
 */
class pmclassupdateenrolmentstatus_testcase extends elis_database_test {

    /**
     * Load CSV data for use in this test class.
     * @param boolean $createnonrequiredlos set to true to create non-required learning objective records
     * @param $createrequiredlos set to true to create required learning objective records
     */
    public function load_csv_data($createnonrequiredlos = false, $createrequiredlos = false) {
        // NOTE: for now, can only use one of two parameters.

        $csvs = array(
            // Need PM course to create PM class.
            course::TABLE => elis::component_file('program', 'tests/fixtures/pmcoursewithgrade.csv'),
            // Need PM classes to create associations.
            pmclass::TABLE => elis::component_file('program', 'tests/fixtures/pmclass.csv'),
            user::TABLE => elis::component_file('program', 'tests/fixtures/pmuser.csv'),
            usermoodle::TABLE => elis::component_file('program', 'tests/fixtures/usermoodle.csv'),
            'user' => elis::component_file('program', 'tests/fixtures/mdluser.csv'),
        );

        if ($createnonrequiredlos) {
            // Want a non-required learning objective.
            $csvs[coursecompletion::TABLE] = elis::component_file('program', 'tests/fixtures/course_completion_nonrequired.csv');
        } else if ($createrequiredlos) {
            // Want a required learning objective.
            $csvs[coursecompletion::TABLE] = elis::component_file('program', 'tests/fixtures/course_completion_required.csv');
        }

        $dataset = $this->createCsvDataSet($csvs);
        $this->loadDataSet($dataset);
    }

    /**
     * Save a set of enrolments and LO grades to the database
     * @param array $enrolments Enrolment data to save
     * @param array $grades LO grade data to save
     */
    protected function save_enrolments($enrolments, $grades = array()) {
        // Enrolments.
        foreach ($enrolments as $enrolment) {
            $student = new student($enrolment);
            $sink = $this->redirectMessages();
            $student->save();
        }

        // LO grades.
        foreach ($grades as $grade) {
            $studentgrade = new student_grade($grade);
            $studentgrade->save();
        }
    }

    /**
     * Filter records out of an array where their userid value does not match the specified value.
     * @param array $enrolments An array of elements containing enrolment data
     * @param int $userid The userid we specifically want to deal with
     * @return array The filtered array
     */
    protected function filter_by_userid($enrolments, $userid) {
        $result = array();

        foreach ($enrolments as $enrolment) {
            if ($enrolment['userid'] == $userid) {
                // Correct userid, so add to result.
                $result[] = $enrolment;
            }
        }

        return $result;
    }

    /**
     * Validate that a set of enrolments exist in the provided state
     *
     * @param array $expectedenrolments The list of enrolments are are validating
     */
    protected function validate_expected_enrolments($expectedenrolments, $pmuserid = 0) {
        global $DB;

        // Validate count.
        $params = array();
        if ($pmuserid) {
            $params = array('userid' => $pmuserid);
        }
        $count = $DB->count_records(student::TABLE, $params);
        $this->assertEquals(count($expectedenrolments), $count);

        // Validate each enrolment individually.
        foreach ($expectedenrolments as $expectedenrolment) {
            $exists = $DB->record_exists(student::TABLE, $expectedenrolment);
            $this->assertTrue($exists);
        }
    }

    /**
     * Data provider that does not include any data for learning objectives
     *
     * @return array An array of runs, containing sub-arrays for parameters
     */
    public function dataprovider_nolearningobjectives() {
        // Array for storing our runs.
        $runs = array();

        // Records that we will be re-using.
        $sufficientgraderecord = array(
            'classid' => 100,
            'grade' => 100,
            'completestatusid' => STUSTATUS_NOTCOMPLETE,
            'locked' => 0
        );

        $sufficientgraderecordcompleted = array(
            'classid' => 100,
            'grade' => 100,
            'completestatusid' => STUSTATUS_PASSED,
            'locked' => 1
        );
        $insufficientgraderecord = array(
            'classid' => 101,
            'grade' => 0,
            'completestatusid' => STUSTATUS_NOTCOMPLETE,
            'locked' => 0
        );

        // Arrays specifying user ids.
        $firstuser = array('userid' => 103);
        $seconduser = array('userid' => 104);

        /*
         * run with just sufficient grades
         */

        // Each user has an enrolment record with a sufficient grade on class 100.
        $enrolments = array();
        $enrolments[] = array_merge($sufficientgraderecord, $firstuser);
        $enrolments[] = array_merge($sufficientgraderecord, $seconduser);

        // Each user has a matching expected passed and locked record.
        $expectedenrolments = array();
        $expectedenrolments[] = array_merge($sufficientgraderecordcompleted, $firstuser);
        $expectedenrolments[] = array_merge($sufficientgraderecordcompleted, $seconduser);
        $runs[] = array($enrolments, $expectedenrolments, array(100, 101));

        /*
         * run with just insufficient grades
         */

        // Each user has an enrolment record with an insufficient grade on class 101.
        $enrolments = array();
        $enrolments[] = array_merge($insufficientgraderecord, $firstuser);
        $enrolments[] = array_merge($insufficientgraderecord, $seconduser);

        // Each user has a matching expected in progress and unlocked record.
        $expectedenrolments = array();
        $expectedenrolments[] = array_merge($insufficientgraderecord, $firstuser);
        $expectedenrolments[] = array_merge($insufficientgraderecord, $seconduser);
        $runs[] = array($enrolments, $expectedenrolments, array(100, 101));

        /*
         * run with both sufficient and an insufficient grades
         */

        // Each user has one sufficient and one insufficient grade.
        $enrolments = array();
        $enrolments[] = array_merge($sufficientgraderecord, $firstuser);
        $enrolments[] = array_merge($sufficientgraderecord, $seconduser);
        $enrolments[] = array_merge($insufficientgraderecord, $firstuser);
        $enrolments[] = array_merge($insufficientgraderecord, $seconduser);

        // The matching expected output.
        $expectedenrolments = array();
        $expectedenrolments[] = array_merge($sufficientgraderecordcompleted, $firstuser);
        $expectedenrolments[] = array_merge($sufficientgraderecordcompleted, $seconduser);
        $expectedenrolments[] = array_merge($insufficientgraderecord, $firstuser);
        $expectedenrolments[] = array_merge($insufficientgraderecord, $seconduser);

        $runs[] = array($enrolments, $expectedenrolments, array(100, 101));

        // Return all data.
        return $runs;
    }

    /**
     * Validate that enrolments are updated appropriate when there are no LOs
     *
     * @param array $enrolments Enrolment records to create
     * @param array $expectedenrolments Records to validate
     * @param array $classids The ids of the classes we should run the method for
     * @dataProvider dataprovider_nolearningobjectives
     */
    public function test_enrolmentupdateswithnolearningobjectives($enrolments, $expectedenrolments, $classids) {
        global $DB;

        $this->load_csv_data();

        $this->save_enrolments($enrolments);

        foreach ($classids as $classid) {
            $class = new pmclass($classid);
            $sink = $this->redirectMessages();
            $class->update_enrolment_status();
        }

        $this->validate_expected_enrolments($expectedenrolments);
    }

    /**
     * Validate that enrolments are updated appropriate when there are no LOs
     *
     * @param array $enrolments Enrolment records to create
     * @param array $expectedenrolments Records to validate
     * @param array $classids The ids of the classes we should run the method for
     * @dataProvider dataprovider_nolearningobjectives
     */
    public function test_enrolmentupdateswithnolearningobjectivesforspecificuserid($enrolments, $expectedenrolments, $classids) {
        global $DB;

        $this->load_csv_data();
        $pmuserid = 103;
        $this->save_enrolments($enrolments);

        foreach ($classids as $classid) {
            $class = new pmclass($classid);
            $sink = $this->redirectMessages();
            $class->update_enrolment_status($pmuserid);
        }
        // Var_dump($expectedenrolments);.
        $expectedenrolments = $this->filter_by_userid($expectedenrolments, $pmuserid);
        // Var_dump($expectedenrolments);.
        $this->validate_expected_enrolments($expectedenrolments, $pmuserid);
    }

    /**
     * Validate that enrolments are updated appropriate when there are only
     * non-required LOs
     *
     * @param array $enrolments Enrolment records to create
     * @param array $expectedenrolments Records to validate
     * @param array $classids The ids of the classes we should run the method for
     * @dataProvider dataprovider_nolearningobjectives
     */
    public function test_enrolmentupdateswithnonrequiredlearningobjectives($enrolments, $expectedenrolments, $classids) {
        global $DB;

        $this->load_csv_data(true);

        $this->save_enrolments($enrolments);

        foreach ($classids as $classid) {
            $class = new pmclass($classid);
            $sink = $this->redirectMessages();
            $class->update_enrolment_status();
        }

        $this->validate_expected_enrolments($expectedenrolments);
    }

    /**
     * Validate that enrolments are updated appropriate when there are only
     * non-required LOs for a specific user
     *
     * @param array $enrolments Enrolment records to create
     * @param array $expectedenrolments Records to validate
     * @param array $classids The ids of the classes we should run the method for
     * @dataProvider dataprovider_nolearningobjectives
     */
    public function test_enrolmentupdateswithnonrequiredlearningobjectivesforspecificuserid($enrolments, $expectedenrolments,
                                                                                            $classids) {
        global $DB;

        $this->load_csv_data(true);
        $pmuserid = 103;
        $this->save_enrolments($enrolments);

        foreach ($classids as $classid) {
            $class = new pmclass($classid);
            $sink = $this->redirectMessages();
            $class->update_enrolment_status($pmuserid);
        }

        $expectedenrolments = $this->filter_by_userid($expectedenrolments, $pmuserid);
        $this->validate_expected_enrolments($expectedenrolments, $pmuserid);
    }

    /**
     * Data provided that includes information regarding learning objectives
     *
     * @return array An array of runs, containing sub-arrays for parameters
     */
    public function dataprovider_learningobjectives() {
        // Array for storing our runs.
        $runs = array();

        // Records that we will be re-using.
        $sufficientgraderecord = array(
            'classid' => 100,
            'grade' => 100,
            'completestatusid' => STUSTATUS_NOTCOMPLETE,
            'locked' => 0
        );
        $sufficientgraderecordcompleted = array(
            'classid' => 100,
            'grade' => 100,
            'completestatusid' => STUSTATUS_PASSED,
            'locked' => 1
        );
        $insufficientgraderecord = array(
            'classid' => 100,
            'grade' => 0,
            'completestatusid' => STUSTATUS_NOTCOMPLETE,
            'locked' => 0
        );

        $sufficientlograderecord = array(
            'completionid' => 1,
            'classid' => 100,
            'grade' => 100,
            'locked' => 0
        );
        $insufficientlograderecord = array(
            'completionid' => 1,
            'classid' => 100,
            'grade' => 0,
            'locked' => 0
        );

        // Arrays specifying user ids.
        $firstuser = array('userid' => 103);
        $seconduser = array('userid' => 104);

        /*
         * run with sufficient enrolment grade but insufficient required LO grade
         */

        // Each user has an enrolment record with a sufficient grade.
        $enrolments = array();
        $enrolments[] = array_merge($sufficientgraderecord, $firstuser);
        $enrolments[] = array_merge($sufficientgraderecord, $seconduser);

        // Each user has an LO grade record with an insufficient grade.
        $logrades = array();
        $logrades[] = array_merge($insufficientlograderecord, $firstuser);
        $logrades[] = array_merge($insufficientlograderecord, $seconduser);

        // Each user has a matching in progress and unlocked record.
        $expectedenrolments = array();
        $expectedenrolments[] = array_merge($sufficientgraderecord, $firstuser);
        $expectedenrolments[] = array_merge($sufficientgraderecord, $seconduser);
        $runs[] = array($enrolments, $logrades, $expectedenrolments, 100);

        /*
         * run with insufficient enrolment grade but sufficient required LO grade
         */

        // Each user has an enrolment record with an insufficient grade.
        $enrolments = array();
        $enrolments[] = array_merge($insufficientgraderecord, $firstuser);
        $enrolments[] = array_merge($insufficientgraderecord, $seconduser);

        // Each user has an LO grade record with a sufficient grade.
        $logrades = array();
        $logrades[] = array_merge($sufficientlograderecord, $firstuser);
        $logrades[] = array_merge($sufficientlograderecord, $seconduser);

        // Each user has a matching in progress and unlocked record.
        $expectedenrolments = array();
        $expectedenrolments[] = array_merge($insufficientgraderecord, $firstuser);
        $expectedenrolments[] = array_merge($insufficientgraderecord, $seconduser);

        $runs[] = array($enrolments, $logrades, $expectedenrolments, 100);

        /*
         * run with sufficient enrolment grade and sufficient required LO grade
         */

        // Each user has an enrolment record with a sufficient grade.
        $enrolments = array();
        $enrolments[] = array_merge($sufficientgraderecord, $firstuser);
        $enrolments[] = array_merge($sufficientgraderecord, $seconduser);

        // Each user has an LO grade record with a sufficient grade.
        $logrades = array();
        $logrades[] = array_merge($sufficientlograderecord, $firstuser);
        $logrades[] = array_merge($sufficientlograderecord, $seconduser);

        // Each user has a matching passed and locked record.
        $expectedenrolments = array();
        $expectedenrolments[] = array_merge($sufficientgraderecordcompleted, $firstuser);
        $expectedenrolments[] = array_merge($sufficientgraderecordcompleted, $seconduser);

        $runs[] = array($enrolments, $logrades, $expectedenrolments, 100);

        // Return all data.
        return $runs;
    }

    /**
     * Validate that enrolments are updated appropriately when there are required
     * LOs
     *
     * @param array $enrolments Enrolment records to create
     * @param array $logrades Learning objective grades to create
     * @param array $expectedenrolments Records to validate
     * @param int $classid The id of the class we should run the method for
     * @dataProvider dataprovider_learningobjectives
     */
    public function test_enrolmentupdateswithrequiredlearningobjectives($enrolments, $logrades, $expectedenrolments, $classid) {
        global $DB;

        $this->load_csv_data(false, true);

        $this->save_enrolments($enrolments, $logrades);

        $class = new pmclass($classid);
        $sink = $this->redirectMessages();
        $class->update_enrolment_status();

        $this->validate_expected_enrolments($expectedenrolments);
    }

    /**
     * Validate that enrolments are updated appropriately when there are required
     * LOs for a specific user
     *
     * @param array $enrolments Enrolment records to create
     * @param array $logrades Learning objective grades to create
     * @param array $expectedenrolments Records to validate
     * @param int $classid The id of the class we should run the method for
     * @dataProvider dataprovider_learningobjectives
     */
    public function test_enrolmentupdateswithrequiredlearningobjectivesforspecificuserid($enrolments, $logrades,
                                                                                         $expectedenrolments, $classid) {
        global $DB;

        $this->load_csv_data(false, true);
        $pmuserid = 103;
        $this->save_enrolments($enrolments, $logrades);

        $class = new pmclass($classid);
        $sink = $this->redirectMessages();
        $class->update_enrolment_status($pmuserid);

        $expectedenrolments = $this->filter_by_userid($expectedenrolments, $pmuserid);
        $this->validate_expected_enrolments($expectedenrolments, $pmuserid);
    }

    /**
     * Data provider that includes records for testing the "timegraded" field
     *
     * @return array An array of runs, containing sub-arrays for parameters
     */
    public function dataprovider_learningobjectivetimegraded() {
        // Array for storing our runs.
        $runs = array();

        // Run with one enrolment having a time graded on and LO and one without.
        $enrolments = array();
        $enrolments[] = array(
            'userid' => 103,
            'classid' => 100,
            'grade' => 100,
            'completestatusid' => STUSTATUS_NOTCOMPLETE,
            'locked' => 0
        );
        $enrolments[] = array(
            'userid' => 104,
            'classid' => 100,
            'grade' => 100,
            'completestatusid' => STUSTATUS_NOTCOMPLETE,
            'locked' => 0
        );
        $logrades = array();
        $logrades[] = array(
            'userid' => 103,
            'completionid' => 1,
            'classid' => 100,
            'grade' => 100,
            'locked' => 0,
            'timegraded' => 1000000000
        );
        $logrades[] = array(
            'userid' => 103,
            'completionid' => 2,
            'classid' => 100,
            'grade' => 100,
            'locked' => 0,
            'timegraded' => 1
        );
        $expectedenrolments = array(
                array(
                    'userid' => 103,
                    'classid' => 100,
                    'grade' => 100,
                    'completestatusid' => STUSTATUS_PASSED,
                    'completetime' => 1000000000,
                    'locked' => 1
                ),
                array(
                    'userid' => 104,
                    'classid' => 100,
                    'grade' => 100,
                    'completestatusid' => STUSTATUS_PASSED,
                    'locked' => 1
                )
        );

        $runs[] = array($enrolments, $logrades, $expectedenrolments, 100);

        // Return all data.
        return $runs;
    }

    /**
     * Validate that our method respects the latest time graded on any linked LO
     *
     * @param array $enrolments Enrolment records to create
     * @param array $logrades Learning objective grades to create
     * @param array $expectedenrolments Records to validate
     * @param int $classid The id of the class we should run the method for
     * @dataProvider dataprovider_learningobjectivetimegraded
     */
    public function test_enrolmentupdaterespectslatestlotimegraded($enrolments, $logrades, $expectedenrolments, $classid) {
        global $DB;

        $this->load_csv_data(true);

        $this->save_enrolments($enrolments, $logrades);

        // Track our time boundaries.
        $class = new pmclass($classid);
        $mintime = time();
        $sink = $this->redirectMessages();
        $class->update_enrolment_status();
        $maxtime = time();

        $count = $DB->count_records(student::TABLE);

        $this->assertEquals(count($expectedenrolments), $count);

        foreach ($expectedenrolments as $expectedenrolment) {
            $exists = $DB->record_exists(student::TABLE, $expectedenrolment);

            $this->assertTrue($exists);

            if (!isset($expectedenrolment['completetime'])) {
                // Validate a time range.
                $record = $DB->get_record(student::TABLE, $expectedenrolment);
                $this->assertGreaterThanOrEqual($mintime, $record->completetime);
                $this->assertLessThanOrEqual($maxtime, $record->completetime);
            }
        }
    }

    /**
     * Validate that our method respects the latest time graded on any linked LO
     * for a specific user
     *
     * @param array $enrolments Enrolment records to create
     * @param array $logrades Learning objective grades to create
     * @param array $expectedenrolments Records to validate
     * @param int $classid The id of the class we should run the method for
     * @dataProvider dataprovider_learningobjectivetimegraded
     */
    public function test_enrolmentupdaterespectslatestlotimegradedforspecificuserid($enrolments, $logrades,
                                                                                    $expectedenrolments, $classid) {
        global $DB;

        $this->load_csv_data(true);
        $pmuserid = 103;
        $this->save_enrolments($enrolments, $logrades);

        // Track our time boundaries.
        $class = new pmclass($classid);
        $mintime = time();
        $sink = $this->redirectMessages();
        $class->update_enrolment_status($pmuserid);
        $maxtime = time();

        $expectedenrolments = $this->filter_by_userid($expectedenrolments, $pmuserid);

        $count = $DB->count_records(student::TABLE, array('userid' => $pmuserid));
        $this->assertEquals(count($expectedenrolments), $count);

        foreach ($expectedenrolments as $expectedenrolment) {
            $exists = $DB->record_exists(student::TABLE, $expectedenrolment);
            $this->assertTrue($exists);
            if (!isset($expectedenrolment['completetime'])) {
                // Validate a time range.
                $record = $DB->get_record(student::TABLE, $expectedenrolment);
                $this->assertGreaterThanOrEqual($mintime, $record->completetime);
                $this->assertLessThanOrEqual($maxtime, $record->completetime);
            }
        }
    }

    /**
     * Data provider that includes enrolments for several classes
     *
     * @return array An array of runs, containing sub-arrays for parameters
     */
    public function dataprovider_differentclasses() {
        // Array for storing our runs.
        $runs = array();

        // Run with one enrolment that could be completed for one class, and on that could be completed for another.
        $enrolments = array();
        $enrolments[] = array(
            'userid' => 103,
            'classid' => 100,
            'grade' => 100,
            'completestatusid' => STUSTATUS_NOTCOMPLETE,
            'locked' => 0
        );
        $enrolments[] = array(
            'userid' => 104,
            'classid' => 100,
            'grade' => 100,
            'completestatusid' => STUSTATUS_NOTCOMPLETE,
            'locked' => 0
        );
        $enrolments[] = array(
            'userid' => 103,
            'classid' => 101,
            'grade' => 100,
            'completestatusid' => STUSTATUS_NOTCOMPLETE,
            'locked' => 0
        );
        $enrolments[] = array(
            'userid' => 104,
            'classid' => 101,
            'grade' => 100,
            'completestatusid' => STUSTATUS_NOTCOMPLETE,
            'locked' => 0
        );
        $expectedenrolments[] = array(
            'userid' => 103,
            'classid' => 100,
            'grade' => 100,
            'completestatusid' => STUSTATUS_PASSED,
            'locked' => 1
        );
        $expectedenrolments[] = array(
            'userid' => 104,
            'classid' => 100,
            'grade' => 100,
            'completestatusid' => STUSTATUS_PASSED,
            'locked' => 1
        );
        $expectedenrolments[] = $enrolments[2];
        $expectedenrolments[] = $enrolments[3];
        $runs[] = array($enrolments, $expectedenrolments, 100);
        // Return all data.
        return $runs;
    }

    /**
     * Validate that the method respects the class instance it is called on
     *
     * @param array $enrolments Enrolment records to create
     * @param array $expectedenrolments Records to validate
     * @param int $classid The id of the class we should run the method for
     * @dataProvider dataprovider_differentclasses
     */
    public function test_enrolmentupdaterespectsclassid($enrolments, $expectedenrolments, $classid) {
        global $DB;

        $this->load_csv_data();

        $this->save_enrolments($enrolments);

        $class = new pmclass($classid);
        $sink = $this->redirectMessages();
        $class->update_enrolment_status();

        $this->validate_expected_enrolments($expectedenrolments);
    }

    /**
     * Validate that the method respects that class instance is it called on
     * as well as the specific userid parameter
     *
     * @param array $enrolments Enrolment records to create
     * @param array $expectedenrolments Records to validate
     * @param int $classid The id of the class we should run the method for
     * @dataProvider dataprovider_differentclasses
     */
    public function test_enrolmentupdaterespectsclassidforspecificuserid($enrolments, $expectedenrolments, $classid) {
        global $DB;

        $this->load_csv_data();
        $pmuserid = 103;
        $this->save_enrolments($enrolments);

        $class = new pmclass($classid);
        $sink = $this->redirectMessages();
        $class->update_enrolment_status($pmuserid);

        $expectedenrolments = $this->filter_by_userid($expectedenrolments, $pmuserid);
        $this->validate_expected_enrolments($expectedenrolments, $pmuserid);
    }

    /**
     * Data provider that helps validate credit values in enrolments
     *
     * @return array An array of runs, containing sub-arrays for parameters
     */
    public function dataprovider_credits() {
        // Array for storing our runs.
        $runs = array();

        // Create one run with one passable enrolment relating to a PM course with a learning objective, and on passable enrolment
        // relating to a lo-less course.
        $enrolments = array();
        $enrolments[] = array(
            'userid' => 103,
            'classid' => 100,
            'grade' => 100,
            'completestatusid' => STUSTATUS_NOTCOMPLETE,
            'locked' => 0,
            'credits' => 0
        );
        $enrolments[] = array(
            'userid' => 104,
            'classid' => 100,
            'grade' => 100,
            'completestatusid' => STUSTATUS_NOTCOMPLETE,
            'locked' => 0,
            'credits' => 0
        );
        $enrolments[] = array(
            'userid' => 103,
            'classid' => 102,
            'grade' => 100,
            'completestatusid' => STUSTATUS_NOTCOMPLETE,
            'locked' => 0,
            'credits' => 0
        );
        $enrolments[] = array(
            'userid' => 104,
            'classid' => 102,
            'grade' => 100,
            'completestatusid' => STUSTATUS_NOTCOMPLETE,
            'locked' => 0,
            'credits' => 0
        );
        $expectedenrolments = array();
        $expectedenrolments[] = array(
            'userid' => 103,
            'classid' => 100,
            'grade' => 100,
            'completestatusid' => STUSTATUS_PASSED,
            'locked' => 1,
            'credits' => 1
        );
        $expectedenrolments[] = array(
            'userid' => 104,
            'classid' => 100,
            'grade' => 100,
            'completestatusid' => STUSTATUS_PASSED,
            'locked' => 1,
            'credits' => 1
        );
        $expectedenrolments[] = array(
            'userid' => 103,
            'classid' => 102,
            'grade' => 100,
            'completestatusid' => STUSTATUS_PASSED,
            'locked' => 1,
            'credits' => 1
        );
        $expectedenrolments[] = array(
            'userid' => 104,
            'classid' => 102,
            'grade' => 100,
            'completestatusid' => STUSTATUS_PASSED,
            'locked' => 1,
            'credits' => 1
        );
        $runs[] = array($enrolments, $expectedenrolments, array(100, 102));

        // Return all data.
        return $runs;
    }

    /**
     * Validate that credits are correctly transferred from course to enrolment
     *
     * @param array $enrolments Enrolment records to create
     * @param array $expectedenrolments Records to validate
     * @param array $classids The ids of the classes we should run the method for
     * @dataProvider dataprovider_credits
     */
    public function test_enrolmentupdatesetscredits($enrolments, $expectedenrolments, $classids) {
        global $DB;

        $this->load_csv_data(true);

        // Set up a second course and class.
        $course = new course(array(
            'name' => 'secondcourse',
            'idnumber' => 'secondcourse',
            'syllabus' => '',
            'credits' => 1
        ));
        $course->save();

        $pmclass = new pmclass(array('courseid' => $course->id, 'idnumber' => 'secondclass'));
        $pmclass->save();

        $this->save_enrolments($enrolments);

        foreach ($classids as $classid) {
            $pmclass = new pmclass($classid);
            $sink = $this->redirectMessages();
            $pmclass->update_enrolment_status();
        }

        $this->validate_expected_enrolments($expectedenrolments);
    }

    /**
     * Validate that credits are correctly transferred from course to enrolment
     * for a specific user
     *
     * @param array $enrolments Enrolment records to create
     * @param array $expectedenrolments Records to validate
     * @param array $classids The ids of the classes we should run the method for
     * @dataProvider dataprovider_credits
     */
    public function test_enrolmentupdatesetscreditsforspecificuserid($enrolments, $expectedenrolments, $classids) {
        global $DB;

        $this->load_csv_data(true);

        // Set up a second course and class.
        $course = new course(array(
            'name' => 'secondcourse',
            'idnumber' => 'secondcourse',
            'syllabus' => '',
            'credits' => 1
        ));
        $course->save();

        $pmclass = new pmclass(array('courseid' => $course->id, 'idnumber' => 'secondclass'));
        $pmclass->save();

        $this->save_enrolments($enrolments);
        $pmuserid = 103;
        foreach ($classids as $classid) {
            $pmclass = new pmclass($classid);
            $sink = $this->redirectMessages();
            $pmclass->update_enrolment_status($pmuserid);
        }

        $expectedenrolments = $this->filter_by_userid($expectedenrolments, $pmuserid);
        $this->validate_expected_enrolments($expectedenrolments, $pmuserid);
    }
}
