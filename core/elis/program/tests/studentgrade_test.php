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
require_once(elispm::lib('data/student.class.php'));

/**
 * Test student grade related functions.
 * Since class is defined within student.class.php testDataObjectsFieldsAndAssociations.php will not auto test this class
 * @group elis_program
 */
class studentgrade_testcase extends elis_database_test {

    /**
     * Load initial data from CSVs.
     */
    protected function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            student_grade::TABLE => elis::component_file('program', 'tests/fixtures/studentgrade.csv')
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * Test that data class has correct DB fields.
     */
    public function teststudentgradehascorrectdbfields() {
        $testobj = new student_grade(false, null, array(), false, array());
        $this->assertTrue($testobj->_test_dbfields(), 'Error(s) with class $_dbfield_ properties.');
    }

    /**
     * Test that data class has correct associations
     */
    public function teststudentgradehascorrectassociations() {
        $testobj = new student_grade(false, null, array(), false, array());
        $this->assertTrue($testobj->_test_associations(), 'Error(s) with class associations.');
    }

    /**
     * Test that a record can be created in the database.
     */
    public function teststudentgradecancreaterecord() {
        // Create a record.
        $src = new student_grade(false, null, array(), false, array());
        $src->classid = 1;
        $src->userid = 2;
        $src->completionid = 3;
        $src->grade = 88;
        $src->locked = 0;
        $src->save();

        // Read it back.
        $retr = new student_grade($src->id, null, array(), false, array());
        foreach ($src as $key => $value) {
            if (strpos($key, elis_data_object::FIELD_PREFIX) !== false) {
                $key = substr($key, strlen(elis_data_object::FIELD_PREFIX));
                $this->assertEquals($src->{$key}, $retr->{$key});
            }
        }
    }

    /**
     * Test that a record can be modified.
     */
    public function teststudentgradecanupdaterecord() {
        $this->load_csv_data();

        // Read a record.
        $src = new student_grade(1, null, array(), false, array());
        $src->grade = 70;
        $src->save();

        // Read it back.
        $retr = new student_grade(3, null, array(), false, array());
        foreach ($src as $key => $value) {
            if (strpos($key, elis_data_object::FIELD_PREFIX) !== false) {
                $key = substr($key, strlen(elis_data_object::FIELD_PREFIX));
                $this->assertEquals($src->{$key}, $retr->{$key});
            }
        }
    }
}