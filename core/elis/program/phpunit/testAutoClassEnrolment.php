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
 * @subpackage programmanager
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__).'/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));

class testAutoClassEnrolment extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

    /**
     * Return the list of tables that should be overlayed.
     */
	protected static function get_overlay_tables() {
		return array(pmclass::TABLE => 'elis_program',
				     classmoodlecourse::TABLE => 'elis_program',
				     usermoodle::TABLE => 'elis_program',
		             user::TABLE => 'elis_program',
		             student::TABLE => 'elis_program',
		             'course' => 'moodle',
		             'user' => 'moodle',
		             'role' => 'moodle',
		             'role_assignments' => 'moodle',
		             'context' => 'moodle',
		             'config' => 'moodle',
		             'grade_items' => 'moodle',
		             'grade_categories' => 'moodle',);
	}

    /**
     * Return the list of tables that should be ignored for writes.
     */
    static protected function get_ignored_tables() {
        return array('grade_categories_history' => 'moodle',
                     'grade_items_history' => 'moodle');
    }

    protected function load_csv_data() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();

        //class and course
        $dataset->addTable(pmclass::TABLE, elis::component_file('program', 'phpunit/pmclass.csv'));
        $dataset->addTable(classmoodlecourse::TABLE, elis::component_file('program', 'phpunit/class_moodle_course.csv'));
        $dataset->addTable('course', elis::component_file('program', 'phpunit/mdlcourse.csv'));

        //user
        $dataset->addTable('user', elis::component_file('program', 'phpunit/mdluser.csv'));
        $dataset->addTable(usermoodle::TABLE, elis::component_file('program', 'phpunit/usermoodle.csv'));
        $dataset->addTable(user::TABLE, elis::component_file('program', 'phpunit/pmuser.csv'));

        //role
        $dataset->addTable('role', elis::component_file('program', 'phpunit/role.csv'));
        $dataset->addTable('role_assignments', elis::component_file('program', 'phpunit/role_assignments.csv'));

        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    /**
     * Validate that the sync from course role assignment to class instance
     * enrolment works
     */
	public function testEnrolledCourseUserSyncsToClass() {
	    global $CFG, $DB;
	    require_once($CFG->dirroot.'/elis/program/lib/setup.php');
	    require_once(elispm::lib('lib.php'));

	    //set up import data
	    $this->load_csv_data();
	    set_config('gradebookroles', '1');

	    //attempt the sync
	    pm_synchronize_moodle_class_grades();

	    //make sure the student record was created
	    $student = student::find();
	    $this->assertTrue($student->valid());

	    //make sure the student has the right class id
	    $student = $student->current();
	    $this->assertEquals(100, $student->classid);
	}
}
