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
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/instructor.class.php'));
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/course.class.php'));

require_once(elispm::file('tests/other/datagenerator.php'));

/**
 * Test instructor functions.
 * @group elis_program
 */
class instructor_testcase extends elis_database_test {

    /**
     * Load initial data from CSVs.
     */
    protected function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            'user' => elis::component_file('program', 'tests/fixtures/mdluser.csv'),
            course::TABLE => elis::component_file('program', 'tests/fixtures/pmcourse.csv'),
            pmclass::TABLE => elis::component_file('program', 'tests/fixtures/pmclass.csv'),
            user::TABLE => elis::component_file('program', 'tests/fixtures/pmuser.csv'),
            instructor::TABLE => elis::component_file('program', 'tests/fixtures/instructor.csv'),
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * Test validation of empty userid.
     * @expectedException data_object_validation_exception
     */
    public function test_instructorvalidation_preventsemptyuserid() {
        $this->load_csv_data();
        $instructor = new instructor(array('classid' => 100));
        $instructor->save();
    }

    /**
     * Test validation of empty classid.
     * @expectedException data_object_validation_exception
     */
    public function test_instructorvalidation_preventsemptyclassid() {
        $this->load_csv_data();
        $instructor = new instructor(array('userid' => 103));
        $instructor->save();
    }

    /**
     * Test validation of invalid userid.
     * @expectedException dml_missing_record_exception
     */
    public function test_instructorvalidation_preventsinvaliduserid() {
        $this->load_csv_data();
        $instructor = new instructor(array('userid' => 1, 'classid' => 100));
        $instructor->save();
    }

    /**
     * Test validation of invalid classid.
     * @expectedException dml_missing_record_exception
     */
    public function test_instructorvalidation_preventsinvalidclassid() {
        $this->load_csv_data();
        $instructor = new instructor(array('userid' => 103, 'classid' => 1));
        $instructor->save();
    }

    /**
     * Test validation of duplicates.
     * @expectedException data_object_validation_exception
     */
    public function test_instructor_validation_preventsduplicates() {
        $this->load_csv_data();
        $instructor = new instructor(array('userid' => 103, 'classid' => 100));
        $instructor->save();
    }

    /**
     * Test the insertion of a valid association record.
     */
    public function test_instructor_validation_allowsvalidrecord() {
        $this->load_csv_data();
        $instructor = new instructor(array('userid' => 103, 'classid' => 101));
        $instructor->save();
        $this->assertTrue(true);
    }

    /**
     * Test get_instructors function.
     */
    public function test_get_instructors() {
        global $DB;

        // Fixture.
        $datagenerator = new elis_program_datagenerator($DB);
        $user = $datagenerator->create_user();
        $datagenerator->assign_instructor_to_class($user->id, 1);

        // Test.
        $instructor = new instructor;
        $instructor->classid = 1;
        $instructors = $instructor->get_instructors();

        // Verify.
        $count = 0;
        foreach ($instructors as $instructoruser) {
            $this->assertEquals($user->id, $instructoruser->id);
            $count++;
        }
        $this->assertEquals(1, $count);
    }
}
