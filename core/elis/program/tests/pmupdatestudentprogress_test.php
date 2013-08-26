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
require_once(elispm::lib('lib.php'));
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));

/**
 * Class for testing the pm_update_student_progress method and making sure it delegates to the appropriate methods.
 * @group elis_program
 */
class pmupdatestudentprogress_testcase extends elis_database_test {

    /**
     * Set up all data needed for testing.
     * @param array $userids List of moodle user ids to test with.
     * @param array $itemgrades List of grade item grades to test with.
     */
    public function fixture_moodleenrol($userids, $itemgrades) {
        global $DB;

        // Import CSV data.
        $dataset = $this->createCsvDataSet(array(
            // Need PM course to create PM class.
            course::TABLE => elis::component_file('program', 'tests/fixtures/pmcourse.csv'),
            // Need PM classes to create associations.
            pmclass::TABLE => elis::component_file('program', 'tests/fixtures/pmclass.csv'),
            // Set up associated users.
            'user' => elis::component_file('program', 'tests/fixtures/mdluser.csv'),
            user::TABLE => elis::component_file('program', 'tests/fixtures/pmuser.csv'),
            usermoodle::TABLE => elis::component_file('program', 'tests/fixtures/user_moodle.csv'),
            // Set up learning objectives.
            coursecompletion::TABLE => elis::component_file('program', 'tests/fixtures/course_completion.csv'),
        ));
        $this->loadDataSet($dataset);

        // Create course.
        $course = $this->getDataGenerator()->create_course();

        // Link with ELIS class.
        $DB->insert_record(classmoodlecourse::TABLE, (object)array('classid' => 100, 'moodlecourseid' => $course->id));

        // Create grade items.
        $items = array(
                array(
                    'courseid' => $course->id,
                    'idnumber' => 'required',
                    'itemtype' => 'manual',
                ),
                array(
                    'courseid' => $course->id,
                    'idnumber' => 'notrequired',
                    'itemtype' => 'manual',
                ),
                array(
                    'courseid' => $course->id,
                    'idnumber' => 'course',
                    'itemtype' => 'course',
                ),
        );
        foreach ($items as $item) {
            $DB->insert_record('grade_items', (object)$item);
        }

        // Set up our test role.
        $roleid = create_role('gradedrole', 'gradedrole', 'gradedrole');
        set_config('gradebookroles', $roleid);

        // Create all of our test enrolments.
        foreach ($userids as $userid) {
            $this->getDataGenerator()->enrol_user($userid, $course->id, $roleid);
        }

        // Assign item grades.
        foreach ($itemgrades as $itemgrade) {
            $DB->insert_record('grade_grades', (object)$itemgrade);
        }
    }

    /**
     * Data provider for validating basic user enrolment synchronization
     * @return array An array containing the list of role assignments to create and Moodle grade_item grades to assign
     */
    public function dataprovider_updatestudentprogress() {

        // Role assignments.
        $userids = array(100, 101);

        // Grade item grades.
        $itemgrades = array(
                // Corresponds to required learning objective.
                array(
                    'itemid' => 1,
                    'userid' => 100,
                    'finalgrade' => 100,
                ),
                // Course grade.
                array(
                    'itemid' => 3,
                    'userid' => 100,
                    'finalgrade' => 100,
                ),
                // Corresponds to required learning objective.
                array(
                    'itemid' => 1,
                    'userid' => 101,
                    'finalgrade' => 100,
                ),
                // Course grade.
                array(
                    'itemid' => 3,
                    'userid' => 101,
                    'finalgrade' => 100,
                ),
        );

        return(array(array($userids, $itemgrades)));
    }

    /**
     * Validate that pm_update_student_progress syncs all necessary enrolment information and "completes" a student.
     * @param array $userids List of moodle user ids to test with.
     * @param array $itemgrades List of grade item grades to test with.
     * @dataProvider dataprovider_updatestudentprogress
     */
    public function test_pmupdatestudentprogressdelegatetoenrolmentsyncandupdate($userids, $itemgrades) {
        global $DB;

        // Set up data.
        $this->fixture_moodleenrol($userids, $itemgrades);

        // Perform the sync.
        pm_update_student_progress();

        // We should have two passed students in the PM class instance.
        $numcompletedenrolments = $DB->count_records(student::TABLE, array('completestatusid' => STUSTATUS_PASSED, 'grade' => 100));
        $this->assertEquals(2, $numcompletedenrolments);

        // We should have two completed learning objectives.
        $countcompletedelements = $DB->count_records(student_grade::TABLE, array('locked' => 1, 'grade' => 100));
        $this->assertEquals(2, $countcompletedelements);
    }

    /**
     * Validate that the pm_update_student_progress method respects its userid parameter, i.e. can run only for a specific user.
     * @param array $userids List of moodle user ids to test with.
     * @param array $itemgrades List of grade item grades to test with.
     * @dataProvider dataprovider_updatestudentprogress
     */
    public function test_pmupdatestudentprogressrespectsuseridparameter($userids, $itemgrades) {
        global $DB;

        // Set up data.
        $this->fixture_moodleenrol($userids, $itemgrades);

        // Perform the sync for the first user.
        pm_update_student_progress(100);

        // We should have one passed student in the PM class instance and that student should be the first user.
        $completedenrolments = $DB->get_records(student::TABLE, array('completestatusid' => STUSTATUS_PASSED, 'userid' => 103));
        $this->assertEquals(1, count($completedenrolments));

        $enrolment = reset($completedenrolments);
        $this->assertEquals(103, $enrolment->userid);
        $this->assertEquals(100, $enrolment->grade);

        // We should have one passed learning objective for the first user.
        $completedelements = $DB->get_records(student_grade::TABLE, array('locked' => 1, 'userid' => 103));
        $this->assertEquals(1, count($completedelements));

        $element = reset($completedelements);
        $this->assertEquals(103, $element->userid);
        $this->assertEquals(100, $element->grade);
    }
}