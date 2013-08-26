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

// Data classes.
require_once(elispm::lib('data/course.class.php'));

/**
 * Class for testing that automatic class completion, based on grade and
 * learning objectives, works
 * @group elis_program
 */
class autoclasscompletion_testcase extends elis_database_test {

    /**
     * Load initial data from a CSV files.
     */
    protected function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            user::TABLE => elis::component_file('program', 'tests/fixtures/pmuser.csv'),
            course::TABLE => elis::component_file('program', 'tests/fixtures/pmcompletioncourse.csv'),
            pmclass::TABLE => elis::component_file('program', 'tests/fixtures/pmclass.csv'),
            student::TABLE => elis::component_file('program', 'tests/fixtures/completionstudent.csv'),
            coursecompletion::TABLE => elis::component_file('program', 'tests/fixtures/course_completion.csv'),
            student_grade::TABLE => elis::component_file('program', 'tests/fixtures/class_graded.csv'),
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * Validates that the "completion" method is sensitive to whether completion elements are required.
     */
    public function test_autoclasscompletion_respectsrequiredstatus() {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/elis/program/lib/setup.php');
        require_once(elispm::lib('lib.php'));

        // Load our data.
        $this->load_csv_data();

        // Attempt to update status before the required learning objective is satisfied.
        pm_update_enrolment_status();

        // Validate that the enrolment is still in progress.
        $sturecord = new student(100);
        $this->assertEquals(STUSTATUS_NOTCOMPLETE, $sturecord->completestatusid);
        $this->assertEquals(0, $sturecord->locked);

        // Satisfy the required learning objective.
        $graderecord = new student_grade(1);
        $graderecord->grade = 80;
        $graderecord->save();

        // Attempt to update status now that the required learning objective is satisfied.
        pm_update_enrolment_status();

        // Validate that the enrolment is passed.
        $sturecord = new student(100);
        $this->assertEquals(STUSTATUS_PASSED, $sturecord->completestatusid);
        $this->assertEquals(1, $sturecord->locked);
    }

    /**
     * Test enrolment functions using an invalid class ID.
     */
    public function test_enrolment_with_invalid_classid() {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/elis/program/lib/setup.php');
        require_once(elispm::lib('lib.php'));

        $this->load_csv_data();

        $enrolment = new stdClass;
        $enrolment->classid = 1000; // Invalid class ID.
        $enrolment->userid = 103;
        $enrolment->enrolmenttime = time();
        $enrolment->completetime = 0;
        $enrolment->endtime = 0;
        $enrolment->completestatusid = 0;
        $enrolment->grade = 0;
        $enrolment->credits = 0.0;
        $enrolment->locked = 0;

        // Directly insert the record to bypass 'student' class validation on the classid.
        $this->assertGreaterThan(0, $DB->insert_record(student::TABLE, $enrolment));

        // Attempt to update status before the required learning objective is satisfied.

        // ELIS-4955 -- This should ignore the bad data and proceed without error.
        pm_update_enrolment_status();

        // Validate that the enrolment is still in progress.
        $sturecord = new student(100);
        $this->assertEquals(STUSTATUS_NOTCOMPLETE, $sturecord->completestatusid);
        $this->assertEquals(0, $sturecord->locked);

        // Satisfy the required learning objective.
        $graderecord = new student_grade(1);
        $graderecord->grade = 80;
        $graderecord->save();

        // Attempt to update status now that the required learning objective is satisfied.
        pm_update_enrolment_status();

        // Validate that the enrolment is passed.
        $sturecord = new student(100);
        $this->assertEquals(STUSTATUS_PASSED, $sturecord->completestatusid);
        $this->assertEquals(1, $sturecord->locked);
    }
}