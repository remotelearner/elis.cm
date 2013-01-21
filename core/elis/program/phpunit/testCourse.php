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
 * @subpackage program
 * @author     Remote-Learner.net Inc
 * @author     James McQuillan <james.mcquillan@remote-learner.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

if (!isset($_SERVER['HTTP_USER_AGENT'])) {
    define('CLI_SCRIPT', true);
}
require_once(dirname(__FILE__) . '/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot . '/elis/program/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once('PHPUnit/Extensions/Database/DataSet/CsvDataSet.php');

require_once(elispm::lib('data/course.class.php'));
require_once(elispm::lib('data/curriculumcourse.class.php'));
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/user.class.php'));

require_once(elispm::file('phpunit/datagenerator.php'));

/** Since class is defined within course.class.php
 *  testDataObjectsFieldsAndAssociations.php will not auto test this class
 */
class coursecompletionTest extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

    protected static function get_overlay_tables() {
        return array(
            'context' => 'moodle',
            'course' => 'moodle',
            course::TABLE => 'elis_program',
            curriculumcourse::TABLE => 'elis_program',
            pmclass::TABLE => 'elis_program',
            student::TABLE => 'elis_program',
            user::TABLE => 'elis_program',
        );
    }

    protected function load_csv_data() {
        // load initial data from a CSV file
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(course::TABLE, elis::component_file('program', 'phpunit/pmcourse.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    public function testCourseSearch() {
        $this->load_csv_data();

        $namesearch = 'Test';
        $alpha = 'T';
        $courses = course_get_listing('crs.name', 'ASC', 0, 20, $namesearch, $alpha);
        $this->assertInternalType('array',$courses);
        $this->assertArrayHasKey(100,$courses);
        $this->assertInternalType('object',$courses[100]);

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
            $this->assertObjectHasAttribute($key,$courses[100]);
            $this->assertEquals($val,$courses[100]->$key);
        }
    }

    public function test_get_completion_counts() {
        global $DB;

        //fixture
        $elis_gen = new elis_program_datagen_unit($DB);
        $pmcourse = $elis_gen->create_course();
        $class = $elis_gen->create_pmclass(array('courseid' => $pmcourse->id));
        $class2 = $elis_gen->create_pmclass(array('courseid' => $pmcourse->id));
        $user = $elis_gen->create_user();
        $user2 = $elis_gen->create_user();
        $elis_gen->assign_user_to_class($user->id,$class->id);
        $elis_gen->assign_user_to_class($user2->id,$class2->id);

        $course = new course;
        $course->id = $pmcourse->id;
        $completion_counts = $course->get_completion_counts();

        //verify results
        $this->assertInternalType('array',$completion_counts);
        $this->assertEquals(3,sizeof($completion_counts));
        $this->assertArrayHasKey(STUSTATUS_NOTCOMPLETE,$completion_counts);
        $this->assertArrayHasKey(STUSTATUS_FAILED,$completion_counts);
        $this->assertArrayHasKey(STUSTATUS_PASSED,$completion_counts);
        $this->assertEquals(2,$completion_counts[STUSTATUS_NOTCOMPLETE]);
        $this->assertEquals(0,$completion_counts[STUSTATUS_FAILED]);
        $this->assertEquals(0,$completion_counts[STUSTATUS_PASSED]);
    }

    public function test_get_assigned_curricula() {
        //fixture
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(curriculumcourse::TABLE, elis::component_file('program', 'phpunit/curriculum_course.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);

        //test
        $course = new course;
        $course->id = 100;
        $curriculumcourse = $course->get_assigned_curricula();

        //verify
        $this->assertNotEmpty($curriculumcourse);
        $this->assertInternalType('array',$curriculumcourse);
        $count = 0;
        foreach ($curriculumcourse as $curriculumid => $curriculumcourseid) {
            $this->assertEquals(1,$curriculumid);
            $this->assertEquals(2,$curriculumcourseid);
            $count++;
        }
        $this->assertEquals(1,$count);
    }
}
