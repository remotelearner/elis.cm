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
require_once(elispm::lib('data/curriculumstudent.class.php'));
require_once(elispm::lib('data/curriculum.class.php'));
require_once(elispm::lib('data/user.class.php'));

/**
 * Test curriculumstudent data object.
 * @group elis_program
 */
class curriculumstudent_testcase extends elis_database_test {

    /**
     * Load initial data from CSVs.
     */
    protected function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            curriculumstudent::TABLE => elis::component_file('program', 'tests/fixtures/curriculum_student.csv')
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * Test validation of duplicates.
     * @expectedException data_object_validation_exception
     */
    public function test_curriculumstudent_validationpreventsduplicates() {
        $this->load_csv_data();
        $curriculumstudent = new curriculumstudent(array('curriculumid' => 1, 'userid' => 1));
        $curriculumstudent->save();
    }

    /**
     * Test complete function.
     */
    public function test_complete() {
        $dataset = $this->createCsvDataSet(array(
            user::TABLE => elis::component_file('program', 'tests/fixtures/pmuser.csv'),
            curriculum::TABLE => elis::component_file('program', 'tests/fixtures/curriculum.csv'),
            curriculumstudent::TABLE => elis::component_file('program', 'tests/fixtures/curriculum_student.csv'),
        ));
        $this->loadDataSet($dataset);

        $cs = new curriculumstudent(2);
        $cs->load();
        $cs->complete(time(), 5);

        // Verify.
        $completed = curriculumstudent::get_completed_for_user(103);
        $count = 0;
        foreach ($completed as $cstu) {
            $this->assertTrue(($cstu instanceof curriculumstudent));
            $this->assertEquals(103, $cstu->userid);
            $count++;
        }
        $this->assertEquals(1, $count);
    }
}