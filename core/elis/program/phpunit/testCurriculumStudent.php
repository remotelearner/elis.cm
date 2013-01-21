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
 * @subpackage programmanager
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__) . '/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot . '/elis/program/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once('PHPUnit/Extensions/Database/DataSet/CsvDataSet.php');
require_once(elispm::lib('data/curriculumstudent.class.php'));
require_once(elispm::lib('data/curriculum.class.php'));
require_once(elispm::lib('data/user.class.php'));

class curriculumstudentTest extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

    protected static function get_overlay_tables() {
        return array(
            curriculumstudent::TABLE => 'elis_program',
            curriculum::TABLE => 'elis_program',
            user::TABLE => 'elis_program',
        );
    }

    protected function load_csv_data() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(curriculumstudent::TABLE, elis::component_file('program', 'phpunit/curriculum_student.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    /**
     * Test validation of duplicates
     *
     * @expectedException data_object_validation_exception
     */
    public function testCurriculumStudentValidationPreventsDuplicates() {
        $this->load_csv_data();

        $curriculumstudent = new curriculumstudent(array('curriculumid' => 1,
                                                         'userid' => 1));

        $curriculumstudent->save();
    }

    public function testComplete() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(user::TABLE, elis::component_file('program', 'phpunit/pmuser.csv'));
        $dataset->addTable(curriculum::TABLE, elis::component_file('program', 'phpunit/curriculum.csv'));
        $dataset->addTable(curriculumstudent::TABLE, elis::component_file('program', 'phpunit/curriculum_student.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);

        $cs = new curriculumstudent(2);
        $cs->load();

        $cs->complete(time(),5);

        //verify
        $completed = curriculumstudent::get_completed_for_user(103);
        $count = 0;
        foreach ($completed as $cstu) {
            $this->assertTrue(($cstu instanceof curriculumstudent));
            $this->assertEquals(103,$cstu->userid);
            $count++;
        }
        $this->assertEquals(1,$count);
    }
}