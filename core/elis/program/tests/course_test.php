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
require_once(elispm::lib('data/course.class.php'));
require_once(elispm::lib('data/curriculumcourse.class.php'));
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/user.class.php'));

require_once(elispm::file('tests/other/datagenerator.php'));

/**
 * Test the course data object.
 * @group elis_program
 */
class course_testcase extends elis_database_test {

    /**
     * Load initial data from CSVs
     */
    protected function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            course::TABLE => elis::component_file('program', 'tests/fixtures/pmcourse.csv')
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * Test course search.
     */
    public function test_coursesearch() {
        $this->load_csv_data();

        $namesearch = 'Test';
        $alpha = 'T';
        $courses = course_get_listing('crs.name', 'ASC', 0, 20, $namesearch, $alpha);
        $this->assertInternalType('array', $courses);
        $this->assertArrayHasKey(100, $courses);
        $this->assertInternalType('object', $courses[100]);

        $expected = array(
            'id' => 100,
            'name' => 'Test Course',
            'code' => '__test_course_code__',
            'idnumber' => '__test__course__',
            'syllabus' => 'Syllabus',
            'documents' => 'Documents',
            'lengthdescription' => 'Length Description',
            'length' => '5',
            'credits' => '1',
            'environmentid' => '1',
            'cost' => '100',
            'version' => '1',
        );

        foreach ($expected as $key => $val) {
            $this->assertObjectHasAttribute($key, $courses[100]);
            $this->assertEquals($val, $courses[100]->$key);
        }
    }

    /**
     * Test get_completion_counts function.
     */
    public function test_get_completion_counts() {
        global $DB;

        // Fixture.
        $elisgen = new elis_program_datagenerator($DB);
        $pmcourse = $elisgen->create_course();
        $class = $elisgen->create_pmclass(array('courseid' => $pmcourse->id));
        $class2 = $elisgen->create_pmclass(array('courseid' => $pmcourse->id));
        $user = $elisgen->create_user();
        $user2 = $elisgen->create_user();
        $elisgen->assign_user_to_class($user->id, $class->id);
        $elisgen->assign_user_to_class($user2->id, $class2->id);

        $course = new course;
        $course->id = $pmcourse->id;
        $completioncounts = $course->get_completion_counts();

        // Verify results.
        $this->assertInternalType('array', $completioncounts);
        $this->assertEquals(3, count($completioncounts));
        $this->assertArrayHasKey(STUSTATUS_NOTCOMPLETE, $completioncounts);
        $this->assertArrayHasKey(STUSTATUS_FAILED, $completioncounts);
        $this->assertArrayHasKey(STUSTATUS_PASSED, $completioncounts);
        $this->assertEquals(2, $completioncounts[STUSTATUS_NOTCOMPLETE]);
        $this->assertEquals(0, $completioncounts[STUSTATUS_FAILED]);
        $this->assertEquals(0, $completioncounts[STUSTATUS_PASSED]);
    }

    /**
     * Test get_assigned_curricula function.
     */
    public function test_get_assigned_curricula() {
        // Fixture.
        $dataset = $this->createCsvDataSet(array(
            curriculumcourse::TABLE => elis::component_file('program', 'tests/fixtures/curriculum_course.csv')
        ));
        $this->loadDataSet($dataset);

        // Test.
        $course = new course;
        $course->id = 100;
        $curriculumcourse = $course->get_assigned_curricula();

        // Verify.
        $this->assertNotEmpty($curriculumcourse);
        $this->assertInternalType('array', $curriculumcourse);
        $count = 0;
        foreach ($curriculumcourse as $curriculumid => $curriculumcourseid) {
            $this->assertEquals(1, $curriculumid);
            $this->assertEquals(2, $curriculumcourseid);
            $count++;
        }
        $this->assertEquals(1, $count);
    }
}
