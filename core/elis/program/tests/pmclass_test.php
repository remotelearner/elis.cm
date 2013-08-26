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

// Data objects.
require_once(elispm::lib('data/curriculumcourse.class.php'));
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/student.class.php'));
require_once(elispm::lib('data/user.class.php'));

// Libs.
require_once(elispm::file('tests/other/datagenerator.php'));

/**
 * Test pmclass data object functions.
 * @group elis_program
 */
class pmclass_testcase extends elis_database_test {

    /**
     * Test get_completion_counts function
     */
    public function test_get_completion_counts() {
        // Fixture.
        $dataset = $this->createCsvDataSet(array(
            student::TABLE => elis::component_file('program', 'tests/fixtures/student.csv'),
            user::TABLE => elis::component_file('program', 'tests/fixtures/pmuser.csv'),
        ));
        $this->loadDataSet($dataset);

        // Test.
        $pmclass = new pmclass;
        $pmclass->id = 100;
        $completioncounts = $pmclass->get_completion_counts();

        // Verify results.
        $this->assertInternalType('array', $completioncounts);
        $this->assertEquals(3, count($completioncounts));
        $this->assertArrayHasKey(STUSTATUS_NOTCOMPLETE, $completioncounts);
        $this->assertArrayHasKey(STUSTATUS_FAILED, $completioncounts);
        $this->assertArrayHasKey(STUSTATUS_PASSED, $completioncounts);
        $this->assertEquals(1, $completioncounts[STUSTATUS_NOTCOMPLETE]);
        $this->assertEquals(0, $completioncounts[STUSTATUS_FAILED]);
        $this->assertEquals(0, $completioncounts[STUSTATUS_PASSED]);
    }

    /**
     * Test format_course_listing function.
     */
    public function test_format_course_listing() {
        // Fixture.
        $dataset = $this->createCsvDataSet(array(
            curriculumcourse::TABLE => elis::component_file('program', 'tests/fixtures/curriculum_course.csv')
        ));
        $this->loadDataSet($dataset);

        // Test.
        $courses = array(1 => null, 100 => null);
        $pmclass = new pmclass;
        $listing = $pmclass->format_course_listing($courses);

        // Verify.
        $expected = array(
            1 => array(
                1 => 1,
                100 => 2
            )
        );

        $this->assertEquals($expected, $listing);
    }

    /**
     * Data provider for test method test_pmclass_auto_create_class() for ELIS-6854.
     * @return array Parameters for test method
     *               format: array(
     *                   array(pmclassdata),
     *                   array(autocreateparams),
     *                   expected auto_create_class() return: false => false, true => !false
     *                   newclass startdate (if applicable)
     *                   newclass enddate (if applicable)
     *               )
     */
    public function dataprovider_pmclass_auto_create() {
        return array(
                // 0: No courseid => acc returns false.
                array(
                        array(
                            'idnumber' => 'ci-elis-6854a',
                            'maxstudents' => 12
                        ),
                        array(
                        ),
                        false, // Accreturn.
                        null,
                        null
                ),
                // 1: No courseid => acc returns false.
                array(
                        array(
                            'idnumber' => 'ci-elis-6854b',
                            'maxstudents' => 12,
                            'startdate' => 1234567,
                            'enddate' => 2345678,
                        ),
                        array(
                        ),
                        false, // Accreturn.
                        null,
                        null
                ),
                // 2: No courseid => acc returns false.
                array(
                        array(
                            'idnumber' => 'ci-elis-6854c',
                            'maxstudents' => 12
                        ),
                        array(
                            'startdate' => 1234567,
                            'enddate' => 2345678,
                        ),
                        false, // Accreturn.
                        null,
                        null
                ),
                // 3: Courseid => acc returns id.
                array(
                        array(
                            'idnumber' => 'ci-elis-6854d',
                            'courseid' => 1,
                            'maxstudents' => 12
                        ),
                        array(
                        ),
                        true,
                        0,
                        0
                ),
                // 4: Courseid in extra params => acc returns id.
                array(
                        array(
                            'idnumber' => 'ci-elis-6854e',
                            'maxstudents' => 12
                        ),
                        array(
                            'courseid' => 1
                        ),
                        true,
                        0,
                        0
                ),
                // 5: Courseid => acc returns id.
                array(
                        array(
                            'idnumber' => 'ci-elis-6854f',
                            'courseid' => 1,
                            'maxstudents' => 12,
                            'startdate' => 1234567,
                            'enddate' => 2345678,
                        ),
                        array(
                        ),
                        true,
                        1234567,
                        2345678
                ),
                // 6: Courseid => acc returns id.
                array(
                        array(
                            'idnumber' => 'ci-elis-6854g',
                            'courseid' => 1,
                            'maxstudents' => 12
                        ),
                        array(
                            'startdate' => 1234567,
                            'enddate' => 2345678,
                        ),
                        true,
                        1234567,
                        2345678
                ),
        );
    }

    /**
     * Method to test pmclass:auto_create_class()
     * for ELIS-6854
     * @dataProvider dataprovider_pmclass_auto_create
     * @param array $pmclassdata Data to pass to pmclass contructor
     * @param array $autocreateparams Data to pass to pmclass::auto_create_class
     * @param bool $accreturn False if pmclass::auto_create_class should return false, true otherwise
     * @param int $startdate Expected new class startdate
     * @param int $enddate Expected new class enddate
     */
    public function test_pmclass_auto_create_class($pmclassdata, $autocreateparams, $accreturn, $startdate, $enddate) {
        global $DB;
        $datagenerator = new elis_program_datagenerator($DB);
        $course = $datagenerator->create_course();
        if (!empty($pmclassdata['courseid'])) {
            $pmclassdata['courseid'] = $course->id;
        }
        if (!empty($autocreateparams['courseid'])) {
            $autocreateparams['courseid'] = $course->id;
        }

        $pmclass = new pmclass((object)$pmclassdata);
        $realaccret = $pmclass->auto_create_class($autocreateparams);
        if ($accreturn) {
            $this->assertTrue(!empty($realaccret));
            $this->assertEquals($startdate, $pmclass->startdate);
            $this->assertEquals($enddate, $pmclass->enddate);
        } else {
            $this->assertFalse($realaccret);
        }
    }
}