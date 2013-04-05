<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__).'/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once(elispm::lib('data/student.class.php'));

class gradeElementsSortTest extends elis_database_test {

    protected static function get_overlay_tables() {
        return array(user::TABLE => 'elis_program',
                     course::TABLE => 'elis_program',
                     pmclass::TABLE => 'elis_program',
                     student::TABLE => 'elis_program',
                     coursecompletion::TABLE => 'elis_program',
                     student_grade::TABLE => 'elis_program');
    }

    protected function load_csv_data() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(user::TABLE, elis::component_file('program', 'phpunit/pmuser.csv'));
        $dataset->addTable(course::TABLE, elis::component_file('program', 'phpunit/pmcompletioncourse.csv'));
        $dataset->addTable(pmclass::TABLE, elis::component_file('program', 'phpunit/pmclass.csv'));
        $dataset->addTable(student::TABLE, elis::component_file('program', 'phpunit/completionstudent.csv'));
        $dataset->addTable(coursecompletion::TABLE, elis::component_file('program', 'phpunit/course_completion.csv'));
        $dataset->addTable(student_grade::TABLE, elis::component_file('program', 'phpunit/class_graded.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    public function testGradeElementsSort() {
        global $CFG, $DB;

        $this->load_csv_data();

        $rec1 = new stdClass;
        $rec1->id = 1;
        $rec1->studentgradeid = 1;
        $rec1->idnumber = 'required';
        $rec1->grade = '0.00000';
        $rec1->locked = 0;
        $rec1->timegraded = 0;

        $rec2 = new stdClass;
        $rec2->id = 2;
        $rec2->studentgradeid = 2;
        $rec2->idnumber = 'notrequired';
        $rec2->grade = '80.00000';
        $rec2->locked = 0;
        $rec2->timegraded = 0;

        $dataset = array();
        $dataset[$rec1->id] =  $rec1;
        $dataset[$rec2->id] =  $rec2;

        // Test idnumber descending sorting
        $sort = student::retrieve_grade_elements(100, 100, 103, 'idnumber', 'DESC');
        $sortvalues = array();
        foreach ($sort as $val) {
            $sortvalues[] = $val;
        }
        unset($sort);
        $this->assertEquals(array_values($dataset), $sortvalues);

        // Test grade ascending sorting
        $sort = student::retrieve_grade_elements(100, 100, 103, 'grade', 'ASC');
        $sortvalues = array();
        foreach ($sort as $val) {
            $sortvalues[] = $val;
        }
        unset($sort);
        $this->assertEquals(array_values($dataset), $sortvalues);

        $dataset = array();
        // Swap
        $dataset[$rec2->id] =  $rec2;
        $dataset[$rec1->id] =  $rec1;

        // Test idnumber ascending sorting
        $sort = student::retrieve_grade_elements(100, 100, 103, 'idnumber', 'ASC');
        $sortvalues = array();
        foreach ($sort as $val) {
            $sortvalues[] = $val;
        }
        unset($sort);
        $this->assertEquals(array_values($dataset), $sortvalues);

        // Test grade descending sorting
        $sort = student::retrieve_grade_elements(100, 100, 103, 'grade', 'DESC');
        $sortvalues = array();
        foreach ($sort as $val) {
            $sortvalues[] = $val;
        }
        unset($sort);
        $this->assertEquals(array_values($dataset), $sortvalues);

        // Test empty array on no results
        $sort = student::retrieve_grade_elements(-1, 100, 103, 'grade', 'DESC');
        $sortvalues = array();
        foreach ($sort as $val) {
            $sortvalues[] = $val;
        }
        unset($sort);
        $this->assertEquals(array(), $sortvalues);
    }

}
