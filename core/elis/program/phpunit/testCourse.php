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

require_once(dirname(__FILE__) . '/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot . '/elis/program/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once('PHPUnit/Extensions/Database/DataSet/CsvDataSet.php');
require_once(elispm::lib('data/course.class.php'));

/** Since class is defined within course.class.php
 *  testDataObjectsFieldsAndAssociations.php will not auto test this class
 */
class coursecompletionTest extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

    protected static function get_overlay_tables() {
        return array(
            'context' => 'moodle',
            'course' => 'moodle',
            course::TABLE => 'elis_program'
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

}