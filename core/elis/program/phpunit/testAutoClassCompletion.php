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
require_once(elispm::lib('data/course.class.php'));

/**
 * Class for testing that automatic class completion, based on grade and
 * learning objectives, works
 */
class autoClassCompletionTest extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

    protected static function get_overlay_tables() {
        return array(user::TABLE => 'elis_program',
                     course::TABLE => 'elis_program',
                     pmclass::TABLE => 'elis_program',
                     student::TABLE => 'elis_program',
                     coursecompletion::TABLE => 'elis_program',
                     student_grade::TABLE => 'elis_program');
    }

    protected static function get_ignored_tables() {
        return array('context' => 'moodle');
    }

    protected function load_csv_data() {
        // load initial data from a CSV file
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(user::TABLE, elis::component_file('program', 'phpunit/pmuser.csv'));
        $dataset->addTable(course::TABLE, elis::component_file('program', 'phpunit/pmcompletioncourse.csv'));
        $dataset->addTable(pmclass::TABLE, elis::component_file('program', 'phpunit/pmclass.csv'));
        $dataset->addTable(student::TABLE, elis::component_file('program', 'phpunit/completionstudent.csv'));
        $dataset->addTable(coursecompletion::TABLE, elis::component_file('program', 'phpunit/course_completion.csv'));
        $dataset->addTable(student_grade::TABLE, elis::component_file('program', 'phpunit/class_graded.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    /**
     * Validates that the "completion" method is sensitive to whether
     * completion elements are required
     */
    public function testAutoClassCompletionRespectsRequiredStatus() {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/elis/program/lib/setup.php');
        require_once(elispm::lib('lib.php'));

        //load our data
        $this->load_csv_data();

        //attempt to update status before the required learning objective is
        //satisfied
        pm_update_enrolment_status();

        //validate that the enrolment is still in progress
        $sturecord = new student(100);
        $this->assertEquals(STUSTATUS_NOTCOMPLETE, $sturecord->completestatusid);
        $this->assertEquals(0, $sturecord->locked);

        //satisfy the required learning objective
        $graderecord = new student_grade(1);
        $graderecord->grade = 80;
        $graderecord->save();

        //attempt to update status now that the required learning objective is
        //satisfied
        pm_update_enrolment_status();

        //validate that the enrolment is passed
        $sturecord = new student(100);
        $this->assertEquals(STUSTATUS_PASSED, $sturecord->completestatusid);
        $this->assertEquals(1, $sturecord->locked);
    }

    public function testEnrolmentWithInvalidClassID() {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/elis/program/lib/setup.php');
        require_once(elispm::lib('lib.php'));

        $this->load_csv_data();

        $enrolment = new stdClass;
        $enrolment->classid          = 1000; // Invalid class ID
        $enrolment->userid           = 103;
        $enrolment->enrolmenttime    = time();
        $enrolment->completetime     = 0;
        $enrolment->endtime          = 0;
        $enrolment->completestatusid = 0;
        $enrolment->grade            = 0;
        $enrolment->credits          = 0.0;
        $enrolment->locked           = 0;

        // Directly insert the record to bypass 'student' class validation on the classid
        $this->assertGreaterThan(0, $DB->insert_record(student::TABLE, $enrolment));

        //attempt to update status before the required learning objective is
        //satisfied

        // ELIS-4955 -- This should ignore the bad data and proceed without error
        pm_update_enrolment_status();

        //validate that the enrolment is still in progress
        $sturecord = new student(100);
        $this->assertEquals(STUSTATUS_NOTCOMPLETE, $sturecord->completestatusid);
        $this->assertEquals(0, $sturecord->locked);

        //satisfy the required learning objective
        $graderecord = new student_grade(1);
        $graderecord->grade = 80;
        $graderecord->save();

        //attempt to update status now that the required learning objective is
        //satisfied
        pm_update_enrolment_status();

        //validate that the enrolment is passed
        $sturecord = new student(100);
        $this->assertEquals(STUSTATUS_PASSED, $sturecord->completestatusid);
        $this->assertEquals(1, $sturecord->locked);
    }

}
