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

    /**
     * Data provider for test method test_pmclass_can_enrol_from_waitlist()
     * @return array Parameters for test method
     *               format: array(
     *                   array(pmclassdata),
     *                   count_enroled,
     *                   expected can_enrol_from_waitlist()
     *               )
     */
    public function dataprovider_pmclass_can_enrol_from_waitlist() {
        return array(
            // Var enrol_from_waitlist not set, no maxstudents set - default values.
            'noenrolfromwaitlist_nomaxstudents' => array(
                    array(
                        'enrol_from_waitlist' => 0,
                        'maxstudents' => null,
                    ),
                    2,
                    false
            ),
            // Var enrol_from_waitlist not set, maxstudents set with low current enrollment.
            'noenrolfromwaitlist_maxstudentsset_lowenrolment' => array(
                    array(
                        'enrol_from_waitlist' => 0,
                        'maxstudents' => 2,
                    ),
                    1,
                    false
            ),
            // Var enrol_from_waitlist not set, maxstudents set with high enrollment.
            'noenrolfromwaitlist_maxstudentsset_highenrolment' => array(
                    array(
                        'enrol_from_waitlist' => 0,
                        'maxstudents' => 2,
                    ),
                    2,
                    false
            ),
            // Var enrol_from_waitlist set, maxstudents not set.
            'enrolfromwaitlist_maxstudentsnotset' => array(
                    array(
                        'enrol_from_waitlist' => 1,
                        'maxstudents' => 0
                    ),
                    1,
                    true
            ),
            // Var enrol_from_waitlist set, maxstudents set high.
            'enrolfromwaitlist_maxstudentssethigh' => array(
                    array(
                        'enrol_from_waitlist' => 1,
                        'maxstudents' => 2
                    ),
                    1,
                    true
            ),
            // Var enrol_from_waitlist set, maxstudents set low.
            'enrolfromwaitlist_maxstudentssetlow' => array(
                    array(
                        'enrol_from_waitlist' => 1,
                        'maxstudents' => 2,
                    ),
                    2,
                    false
            )
        );
    }

    /**
     * Method to test pmclass:can_enrol_from_waitlist()
     * @dataProvider dataprovider_pmclass_can_enrol_from_waitlist
     * @param array $pmclassdata Data to pass to pmclass contructor
     * @param int $countenrolled The number enrolled to return from the mocked count_enroled function.
     * @param boolean $expected The expected result.
     */
    public function test_pmclass_can_enrol_from_waitlist($pmclassdata, $countenrolled, $expected) {
        // Create a stub for the pm class with count_enroled ready to override.
        $pmclass = $this->getMockBuilder('pmclass')
                        ->setMethods(array('count_enroled'))
                        ->getMock();
        // Iterate through the instance field-value pairs and set them.
        foreach ($pmclassdata as $key => $value) {
            $pmclass->$key = $value;
        }
        // Create a mock method for the count_enroled function.
        $pmclass->expects($this->any())
                ->method('count_enroled')
                ->will($this->returnValue($countenrolled));
        // Run the test.
        $answer = $pmclass->can_enrol_from_waitlist();
        $this->assertEquals($expected, $answer);
    }

    /**
     * Data provider for test method test_pmclass_can_enrol_from_waitlist()
     * @return array Parameters for test method
     *               format: array(
     *                  classid
     *                  userid,
     *                  expected result
     *               )
     */
    public function dataprovider_check_user_prerequisite_status() {
        return array(
            // Class has prerequisites and specified user meets all of the prerequisites.
            'meetsallprerequistites' => array(200, 603, true),
            // Class has prerequisites and specified user does not meet all of the prerequisites.
            'doesnotmeetsallprerequistites' => array(200, 606, false),
            // Class is in a program but has no prerequisites.
            'noprogramprerequistites' => array(202, 605, true),
            // Class is not in a program and thus has no prerequisites.
            'notinprogram' => array(203, 605, true)
        );
    }

    /**
     * Test the check_user_prerequisite_status function.
     * @dataProvider dataprovider_check_user_prerequisite_status
     * @param int $classid The class instance to check against.
     * @param int $userid The number enrolled to return from the mocked count_enroled function.
     * @param boolean $expected The expected result.
     */
    public function test_check_user_prerequisite_status($classid, $userid, $expected) {
        // Load the data sets.
        $dataset = $this->createCsvDataSet(array(
            curriculum::TABLE => elispm::file('tests/fixtures/elisprogram_pgm.csv'),
            curriculumstudent::TABLE => elispm::file('tests/fixtures/elisprogram_pgm_assign.csv'),
            course::TABLE => elispm::file('tests/fixtures/elisprogram_crs.csv'),
            pmclass::TABLE => elispm::file('tests/fixtures/elisprogram_cls.csv'),
            curriculumcourse::TABLE =>elispm::file('tests/fixtures/elisprogram_pgm_crs.csv'),
            user::TABLE => elispm::file('tests/fixtures/elisprogram_usr.csv'),
            student::TABLE => elispm::file('tests/fixtures/elisprogram_cls_enrol.csv'),
            waitlist::TABLE => elispm::file('tests/fixtures/elisprogram_waitlist.csv'),
            courseprerequisite::TABLE => elispm::file('tests/fixtures/elisprogram_prereq.csv'),
        ));
        $this->loadDataSet($dataset);
        // Load the first Course Description's Class Instance.
        $classinstance = new pmclass($classid);
        $classinstance->load();

        // Call the function to test.
        $answer = $classinstance->check_user_prerequisite_status($userid);
        $this->assertEquals($expected, $answer);
    }

}
