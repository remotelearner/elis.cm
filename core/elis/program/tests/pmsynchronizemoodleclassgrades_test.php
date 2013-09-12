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
require_once(elispm::lib('data/course.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));
require_once(elispm::lib('data/student.class.php'));
require_once(elispm::lib('data/pmclass.class.php'));

// Libs.
require_once(elispm::lib('lib.php'));
require_once($CFG->dirroot.'/lib/grade/grade_item.php');
require_once($CFG->dirroot.'/lib/enrollib.php');

/**
 * Unit testing for the pm_synchronize_moodle_class_grades method
 * @group elis_program
 */
class pmsynchronizemoodleclassgrades_testcase extends elis_database_test {

    /**
     * Perform setup before tests.
     */
    protected function setUp() {
        global $DB;
        parent::setUp();

        // Prevent events magic from happening.
        $DB->delete_records('events_handlers');
    }

    /**
     * Load base data from CSV file
     *
     * @param boolean $linkclass If true, link the PM class to the Moodle course
     */
    protected function load_csv_data($linkclass = true) {
        $csvs = array(
            // Need PM course to create PM class.
            course::TABLE => elis::component_file('program', 'tests/fixtures/pmcourse.csv'),
            // Need PM classes to create associations.
            pmclass::TABLE => elis::component_file('program', 'tests/fixtures/pmclass.csv'),
            // Need a Moodle course to sync users from.
            'course' => elis::component_file('program', 'tests/fixtures/mdlcoursenonsite.csv'),
            // Need both the Moodle and the PM user.
            'user' => elis::component_file('program', 'tests/fixtures/mdluser.csv'),
            user::TABLE => elis::component_file('program', 'tests/fixtures/pmuser.csv'),
            usermoodle::TABLE => elis::component_file('program', 'tests/fixtures/usermoodle.csv'),
        );

        if ($linkclass) {
            // Link the course and the class.
            $csvs[classmoodlecourse::TABLE] = elis::component_file('program', 'tests/fixtures/class_moodle_course_nonsite.csv');
        }

        $dataset = $this->createCsvDataSet($csvs);
        $this->loadDataSet($dataset);

        // Make our role a "student" role.
        set_config('gradebookroles', 1);
    }

    /**
     * Set up our main Moodle course to be enrollable
     */
    protected function make_course_enrollable() {
        set_config('enrol_plugins_enabled', 'manual');
        $enrol = enrol_get_plugin('manual');
        $course = new stdClass;
        $course->id = 2;
        $enrol->add_instance($course);
    }

    /**
     * Validate that a certain number of student records exist
     * @param int $num The expected number of student records
     */
    protected function assert_num_students($num) {
        global $DB;
        $count = $DB->count_records(student::TABLE);
        $this->assertEquals($num, $count);
    }

    /**
     * Validate that a certain student record exists
     *
     * @param int $classid The id of the appropriate class
     * @param int $userid  The id of the appropriate PM user
     * @param int $grade The class grade
     * @param int $completestatusid The class completion status
     * @param int $completetime The class completion time
     * @param int $credits Number of credits achieved
     * @param int $locked Whether the record is locked
     */
    protected function assert_student_exists($classid, $userid, $grade = null, $completestatusid = null, $completetime = null,
                                             $credits = null, $locked = null) {
        global $DB;

        // Required fields.
        $params = array('classid' => $classid, 'userid' => $userid);

        // Optional fields.
        if ($grade !== null) {
            $params['grade'] = $grade;
        }
        if ($completestatusid !== null) {
            $params['completestatusid'] = $completestatusid;
        }
        if ($completetime !== null) {
            $params['completetime'] = $completetime;
        }
        if ($credits !== null) {
            $params['credits'] = $credits;
        }
        if ($locked !== null) {
            $params['locked'] = $locked;
        }

        // Validate existence.
        $exists = $DB->record_exists(student::TABLE, $params);
        $this->assertTrue($exists);
    }

    /**
     * Validate that a certain number of student grade records exist
     *
     * @param int $num The expected number of student grade records
     */
    protected function assert_num_student_grades($num) {
        global $DB;

        $count = $DB->count_records(student_grade::TABLE);
        $this->assertEquals($num, $count);
    }

    /**
     * Validate that a certain student grade record exists
     *
     * @param int $classid The id of the appropriate class
     * @param int $userid The id of the appropriate PM user
     * @param int $completionid The id of the appropriate LO
     * @param int $grade The LO grade
     * @param int $locked Whether the LO grade is locked
     * @param int $timegraded The graded time
     */
    protected function assert_student_grade_exists($classid, $userid, $completionid, $grade = null, $locked = null,
                                                   $timegraded = null) {
        global $DB;

        // Required fields.
        $params = array('classid' => $classid, 'userid' => $userid, 'completionid' => $completionid);
        // Optional fields.
        if ($grade !== null) {
            $params['grade'] = $grade;
        }
        if ($locked !== null) {
            $params['locked'] = $locked;
        }
        if ($timegraded !== null) {
            $params['timegraded'] = $timegraded;
        }

        // Validate existence.
        $exists = $DB->record_exists(student_grade::TABLE, $params);
        $this->assertTrue($exists);
    }

    /**
     * Create a Moodle grade item for our test course
     *
     * @param string $idnumber The grade item's idnumber
     * @param int $grademax The max grade (100 if not specified);
     */
    protected function create_grade_item($idnumber = 'manualitem', $grademax = null) {
        // Required fields.
        $data = array(
            'courseid' => 2,
            'itemtype' => 'manual',
            'idnumber' => $idnumber,
            'needsupdate' => false,
            'locked' => true
        );
        // Optional fields.
        if ($grademax !== null) {
            $data['grademax'] = $grademax;
        }

        // Save the record.
        $gradeitem = new grade_item($data);
        $gradeitem->insert();
        return $gradeitem->id;
    }

    /**
     * Create a Moodle student grade
     *
     * @param int $itemid The grade item id
     * @param int $userid The Moodle user id
     * @param int $finalgrade The student's final grade
     * @param int $rawgrademax The maximum grade
     * @param int $timemodified The graded time
     */
    protected function create_grade_grade($itemid, $userid, $finalgrade, $rawgrademax = 100, $timemodified = null) {
        $gradegrade = new grade_grade(array(
            'itemid' => $itemid,
            'userid' => $userid,
            'finalgrade' => $finalgrade,
            'rawgrademax' => $rawgrademax,
            'timemodified' => $timemodified
        ));
        $gradegrade->insert();
    }

    /**
     * Create a PM course completion record
     *
     * @param string $idnumber The idnumber of the course completion / LO
     * @param int $completiongrade The required completion grad
     * @return int The db record id of the element
     */
    protected function create_course_completion($idnumber = 'manualitem', $completiongrade = null) {
        // Required fields.
        $data = array('courseid' => 100, 'idnumber' => $idnumber);

        // Optional fields.
        if ($completiongrade !== null) {
            $data['completion_grade'] = $completiongrade;
        }

        // Save.
        $coursecompletion = new coursecompletion($data);
        $coursecompletion->save();

        // Return id.
        return $coursecompletion->id;
    }

    /**
     * Validate that the sync depends on course-class associations
     */
    public function test_nosynchappenswhenclassnotassociated() {
        $this->load_csv_data(false);

        // Set up enrolment.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        // Call and validate.
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(0);

        // Associate class with course.
        $classmoodlecourse = new classmoodlecourse(array('classid' => 100, 'moodlecourseid' => 2));
        $classmoodlecourse->save();

        // Call and validate.
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
    }

    /**
     * Validate that the sync depends on course-class associations when running for a specific user
     */
    public function test_nosynchappenswhenclassnotasociatedforspecificuserid() {
        $this->load_csv_data(false);

        // Set up enrolments.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        // Call and validate.
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(0);

        // Associate class with course.
        $classmoodlecourse = new classmoodlecourse(array('classid' => 100, 'moodlecourseid' => 2));
        $classmoodlecourse->save();

        // Call and validate.
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
    }

    /**
     * Validate that the sync ignores orphaned classes
     */
    public function test_nosynchappenswhenclassassociatedtodeletedmoodlecourse() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolment.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        // Orphan the class.
        $DB->delete_records('course');

        // Call and validate.
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(0);

        // Re-associate.
        $dataset = $this->createCsvDataSet(array(
            'course' => elis::component_file('program', 'tests/fixtures/mdlcoursenonsite.csv')
        ));
        $this->loadDataSet($dataset);

        // Call and validate.
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
    }

    /**
     * Validate that the sync ignores orphaned classes when running for a specific user
     */
    public function test_nosynchappenswhenclassassociatedtodeletemoodlecourseforspecificuserid() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolments.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        // Orphan the class.
        $DB->delete_records('course');

        // Call and validate.
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(0);

        // Re-associate.
        $dataset = $this->createCsvDataSet(array(
            'course' => elis::component_file('program', 'tests/fixtures/mdlcoursenonsite.csv')
        ));
        $this->loadDataSet($dataset);

        // Call and validate.
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
    }

    /**
     * Validate that the sync has fix for extra slashes in idnumbers
     */
    public function test_methodsupportsgradeitemidnumberswithslashes() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolment.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        // Set up grade item and completion item.
        $itemid = $this->create_grade_item("\'withslashes\'");
        $this->create_grade_grade($itemid, 100, 75);
        $completionid = $this->create_course_completion("\'withslashes\'");

        // Call and validate.
        pm_synchronize_moodle_class_grades();
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, $completionid, 75);
    }

    /**
     * Validate that the sync has fix for extra slashes in idnumbers when running for a specific user
     */
    public function test_methodsupportsgradeitemidnumberswithslashesforspecificuserid() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolments.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        // Set up grade item and completion item.
        $itemid = $this->create_grade_item("\'withslashes\'");
        $this->create_grade_grade($itemid, 100, 75);
        $this->create_grade_grade($itemid, 101, 75);
        $completionid = $this->create_course_completion("\'withslashes\'");

        // Call and validate.
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, $completionid, 75);
    }

    /**
     * Validate that the sync depends on Moodle enrolments
     */
    public function test_nosynchappenswhennousersenrolled() {
        global $DB;

        $this->load_csv_data();

        // Call and validate with no enrolments.
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(0);

        // Set up enrolment.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        // Call and validate when enrolment exists.
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
    }

    /**
     * Validate that the sync depends on Moodle enrolments when running for a specific user
     */
    public function test_nosynchappenswhennousersenrolledforspecificuserid() {
        global $DB;

        $this->load_csv_data();

        // Call and validate with no enrolments.
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(0);

        // Set up enrolments.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        // Call and validate when enrolment exists.
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
    }

    /**
     * Validate that the sync ignores courses with no max grade
     */
    public function test_nosynchappenswhencoursehasnomaxgrade() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolment.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        // Create course grade item with no max grade.
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->grademax = 0;
        $coursegradeitem->update();

        // Call and validate.
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(0);

        // Set valid max grade.
        $coursegradeitem->grademax = 100;
        $coursegradeitem->update();

        // Call and validate.
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
    }

    /**
     * Validate that the sync ignores courses with no max grade when running for a specific user
     */
    public function test_nosynchappenswhencoursehasnomaxgradeforspecificuserid() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolments.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        // Create course grade item with no max grade.
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->grademax = 0;
        $coursegradeitem->update();

        // Call and validate.
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(0);

        // Set valid max grade.
        $coursegradeitem->grademax = 100;
        $coursegradeitem->update();

        // Call and validate.
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
    }

    /**
     * Validate that the sync prevents grades from syncing into two classes
     */
    public function test_nosynchappenswhencourselinkedtotwoclasses() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolment.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        // Create second association.
        $classmoodlecourse = new classmoodlecourse(array('classid' => 101, 'moodlecourseid' => 2));
        $classmoodlecourse->save();

        // Call and validate.
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(0);

        // Remove second association.
        $DB->delete_records(classmoodlecourse::TABLE, array('classid' => 101, 'moodlecourseid' => 2));

        // Call and validate.
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
    }

    /**
     * Validate that the sync prevents grades from syncing into two classes when running for a specific user
     */
    public function test_nosynchappenswhencourselinkedtotwoclassesforspecificuserid() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolments.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        // Create second association.
        $classmoodlecourse = new classmoodlecourse(array('classid' => 101, 'moodlecourseid' => 2));
        $classmoodlecourse->save();

        // Call and validate.
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(0);

        // Remove second association.
        $DB->delete_records(classmoodlecourse::TABLE, array('classid' => 101, 'moodlecourseid' => 2));

        // Call and validate.
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
    }

    /**
     * Validate that recordsets iterate correctly and skip non-matching records when dealing with multiple users
     * NOTE: it doesn't really make sense to have a version of this test case for a single user
     */
    public function test_methodskipsnonmatchingrecords() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolments.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        // Set up grade items and PM completion info.
        $this->create_course_completion();
        $itemid = $this->create_grade_item();
        $this->create_grade_grade($itemid, 100, 75, 100, 2);
        $this->create_grade_grade($itemid, 101, 75, 100, 2);

        // Call and validate.
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(2);
        $this->assert_student_exists(100, 103);
        $this->assert_student_exists(100, 104);

        $this->assert_num_student_grades(2);
        $this->assert_student_grade_exists(100, 103, 1);
        $this->assert_student_grade_exists(100, 104, 1);
    }

    /**
     * Validate that enrolments are successfully created
     */
    public function test_methodcreatesenrolment() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolment.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        // Call and validate.
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);

        // Validate end time since no other unit test does this for creates.
        $this->assert_student_exists(100, 103);
        $enrolment = $DB->get_record(student::TABLE, array('classid' => 100, 'userid' => 103));
        $this->assertEquals(0, $enrolment->endtime);
    }

    /**
     * Validate that enrolments are successfully created when running for a specific user
     */
    public function test_methodcreatesenrolmentforspecificuserid() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolments.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        // Call and validate.
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);

        // Validate end time since no other unit test does this for creates.
        $this->assert_student_exists(100, 103);
        $enrolment = $DB->get_record(student::TABLE, array('classid' => 100, 'userid' => 103));
        $this->assertEquals(0, $enrolment->endtime);
    }

    /**
     * Validate that enrolments are successfully updated
     */
    public function test_methodupdatesenrolment() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolment.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        // Set up grade.
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->needsupdate = false;
        $coursegradeitem->locked = true;
        $coursegradeitem->update();
        $this->create_grade_grade(1, 100, 75);

        // Set up PM enrolments.
        $student = new student(array('userid' => 103, 'classid' => 100, 'grade' => 25));
        $student->save();

        // Validate setup.
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, 25);

        // Call and validate.
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
        $count = $DB->count_records(student::TABLE, array('grade' => 75));
        $this->assertEquals(1, $count);
        $this->assert_student_exists(100, 103, 75);

        // Validate end time since no other unit test does this for updates.
        $enrolment = $DB->get_record(student::TABLE, array('classid' => 100, 'userid' => 103));
        $this->assertEquals(0, $enrolment->endtime);
    }

    /**
     * Validate that enrolments are successfully updated for a specific user
     */
    public function test_methodupdatesenrolmentforspecificuserid() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolments.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        // Set up grade.
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->needsupdate = false;
        $coursegradeitem->locked = true;
        $coursegradeitem->update();
        $this->create_grade_grade(1, 100, 75);
        $this->create_grade_grade(1, 101, 75);

        // Set up PM enrolments.
        $student = new student(array('userid' => 103, 'classid' => 100, 'grade' => 25));
        $student->save();
        $student = new student(array('userid' => 104, 'classid' => 100, 'grade' => 25));
        $student->save();

        // Validate setup.
        $this->assert_num_students(2);
        $this->assert_student_exists(100, 103, 25);

        // Call and validate.
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(2);
        $count = $DB->count_records(student::TABLE, array('grade' => 75));
        $this->assertEquals(1, $count);
        $this->assert_student_exists(100, 103, 75);

        // Validate end time since no other unit test does this for updates.
        $enrolment = $DB->get_record(student::TABLE, array('classid' => 100, 'userid' => 103));
        $this->assertEquals(0, $enrolment->endtime);
    }

    /**
     * Validate that PM enrolment end times are set from moodle
     */
    public function test_methodsetsenrolmenttimefrommoodleenrolment() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolment.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1, 999999);

        // Call and validate.
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
        $exists = $DB->record_exists(student::TABLE, array('enrolmenttime' => 999999));
        $this->assertTrue($exists);
    }

    /**
     * Validate that PM enrolment end times are set from moodle when run for a specific user
     */
    public function test_methodsetsenrolmenttimefrommoodleenrolmentforspecificuserid() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolments.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1, 999999);
        enrol_try_internal_enrol(2, 101, 1, 999999);

        // Call and validate.
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
        $exists = $DB->record_exists(student::TABLE, array('enrolmenttime' => 999999));
        $this->assertTrue($exists);
    }

    /**
     * Validate default for PM enrolment end time
     */
    public function test_methodsetsdefaultenrolmenttime() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolment.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        // Set start time to an empty value.
        $DB->execute("UPDATE {user_enrolments} SET timestart = 0");

        // Run and collect timing info.
        $mintime = time();
        pm_synchronize_moodle_class_grades();
        $maxtime = time();

        // Validate.
        $this->assert_num_students(1);
        $enrolment = $DB->get_record(student::TABLE, array('id' => 1));
        $this->assertGreaterThanOrEqual($mintime, $enrolment->enrolmenttime);
        $this->assertLessThanOrEqual($maxtime, $enrolment->enrolmenttime);
    }

    /**
     * Validate default for PM enrolment end time when run for a specific user
     */
    public function test_methodsetsdefaultenrolmenttimeforspecificuserid() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolments.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        // Set start time to an empty value.
        $DB->execute("UPDATE {user_enrolments} SET timestart = 0");

        // Run and collect timing info.
        $mintime = time();
        pm_synchronize_moodle_class_grades(100);
        $maxtime = time();

        // Validate.
        $this->assert_num_students(1);
        $enrolment = $DB->get_record(student::TABLE, array('id' => 1));
        $this->assertGreaterThanOrEqual($mintime, $enrolment->enrolmenttime);
        $this->assertLessThanOrEqual($maxtime, $enrolment->enrolmenttime);
    }

    /**
     * Data provider for scaling grades
     * @return array An array containing current grade, max grade, and percentage in each position
     */
    public function dataprovider_enrolmentgradescale() {
        return array(
                array(100, 100, 100),
                array(50, 100, 50),
                array(1, 5, 20),
                array(1, 2, 50)
        );
    }

    /**
     * Validate that enrolment grade scaling works correctly
     *
     * @param int $finalgrade The user's grade in Moodle
     * @param int $grademax The maximum grade in Moodle
     * @param int $pmgrade The expected PM grade (percentage)
     * @dataProvider dataprovider_enrolmentgradescale
     */
    public function test_methodscalesmoodlecoursegrade($finalgrade, $grademax, $pmgrade) {
        global $DB;

        $this->load_csv_data();

        // Set up enrolment.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        // Set up grade information.
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->grademax = $grademax;
        $coursegradeitem->needsupdate = false;
        $coursegradeitem->locked = true;
        $coursegradeitem->update();

        $coursegradegrade = new grade_grade(array(
            'itemid' => 1,
            'userid' => 100,
            'rawgrademax' => $grademax,
            'finalgrade' => $finalgrade
        ));
        $coursegradegrade->insert();

        // Call and validate.
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, $pmgrade);
    }

    /**
     * Validate that enrolment grade scaling works correctly when running for a specific user
     *
     * @param int $finalgrade The user's grade in Moodle
     * @param int $grademax The maximum grade in Moodle
     * @param int $pmgrade The expected PM grade (percentage)
     * @dataProvider dataprovider_enrolmentgradescale
     */
    public function test_methodscalesmoodlecoursegradeforspecificuserid($finalgrade, $grademax, $pmgrade) {
        global $DB;

        $this->load_csv_data();

        // Set up enrolments.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        // Set up grade information.
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->grademax = $grademax;
        $coursegradeitem->needsupdate = false;
        $coursegradeitem->locked = true;
        $coursegradeitem->update();

        $coursegradegrade = new grade_grade(array(
            'itemid' => 1,
            'userid' => 100,
            'rawgrademax' => $grademax,
            'finalgrade' => $finalgrade
        ));
        $coursegradegrade->insert();
        $coursegradegrade = new grade_grade(array(
            'itemid' => 1,
            'userid' => 101,
            'rawgrademax' => $grademax,
            'finalgrade' => $finalgrade
        ));
        $coursegradegrade->insert();

        // Call and validate.
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, $pmgrade);
    }

    /**
     * Validate that when no learning objectives are present, enrolments can be marked as passed when they have a sufficient grade.
     */
    public function test_methodmarkenrolmentpassedwhengradesufficientandnorequiredlearningobjectives() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolment.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        // Set up PM course completion criteria.
        $pmcourse = new course(array('id' => 100, 'completion_grade' => 50));
        $pmcourse->save();

        // Set up course grade item info in Moodle.
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->grademax = 100;
        $coursegradeitem->needsupdate = false;
        $coursegradeitem->locked = true;
        $coursegradeitem->update();

        // Set up PM class enrolment with sufficient grade.
        $coursegradegrade = new grade_grade(array('itemid' => 1, 'userid' => 100, 'finalgrade' => 100));
        $coursegradegrade->insert();

        // Call and validate.
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, null, STUSTATUS_PASSED);
    }

    /**
     * Validate that when no learning objectives are present, enrolments can be marked as passed when they have a sufficient
     * grade when run for a specific user.
     */
    public function test_methodmarkenrolmentpassedwhengradesufficientandnorequiredlearningobjectivesforspecificuserid() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolments.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        // Set up PM course completion criteria.
        $pmcourse = new course(array('id' => 100, 'completion_grade' => 50));
        $pmcourse->save();

        // Set up course grade item info in Moodle.
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->grademax = 100;
        $coursegradeitem->needsupdate = false;
        $coursegradeitem->locked = true;
        $coursegradeitem->update();

        // Set up PM class enrolment with sufficient grade.
        $coursegradegrade = new grade_grade(array('itemid' => 1, 'userid' => 100, 'finalgrade' => 100));
        $coursegradegrade->insert();
        $coursegradegrade = new grade_grade(array('itemid' => 1, 'userid' => 101, 'finalgrade' => 100));
        $coursegradegrade->insert();

        // Call and validate.
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, null, STUSTATUS_PASSED);
    }

    /**
     * Validate that enrolment completion times are properly maintained.
     */
    public function test_methodsetscompletiontimefrommoodlegradeitem() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolment.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        // Set up required PM course grade.
        $pmcourse = new course(array('id' => 100, 'completion_grade' => 50));
        $pmcourse->save();

        // Set up Moodle course grade item info.
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->needsupdate = false;
        $coursegradeitem->locked = true;
        $coursegradeitem->update();

        // Assign the user a grade in Moodle.
        $coursegradegrade = new grade_grade(array('itemid' => 1, 'userid' => 100, 'finalgrade' => 100, 'timemodified' => 12345));
        $coursegradegrade->insert();

        // Call and validate.
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, null, null, 12345);
    }

    /**
     * Validate that enrolment completion times are properly maintained when run for a specific user.
     */
    public function test_methodsetscompletiontimefrommoodlegradeitemforspecificuserid() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolments.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        // Set up required PM course grade.
        $pmcourse = new course(array('id' => 100, 'completion_grade' => 50));
        $pmcourse->save();

        // Set up Moodle course grade item info.
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->needsupdate = false;
        $coursegradeitem->locked = true;
        $coursegradeitem->update();

        // Assign the user a grade in Moodle.
        $coursegradegrade = new grade_grade(array('itemid' => 1, 'userid' => 100, 'finalgrade' => 100, 'timemodified' => 12345));
        $coursegradegrade->insert();
        $coursegradegrade = new grade_grade(array('itemid' => 1, 'userid' => 101, 'finalgrade' => 100, 'timemodified' => 12345));
        $coursegradegrade->insert();

        // Call and validate.
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, null, null, 12345);
    }

    /**
     * Validate that default enrolment completion times are used
     */
    public function test_methodsetsdefaultcompletiontime() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolment.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        // Set up PM course completion grade.
        $pmcourse = new course(array('id' => 100, 'completion_grade' => 50));
        $pmcourse->save();

        // Set up Moodle course grade item.
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->needsupdate = false;
        $coursegradeitem->locked = true;
        $coursegradeitem->update();

        // Assign the user a Moodle coruse grade.
        $coursegradegrade = new grade_grade(array('itemid' => 1, 'userid' => 100, 'finalgrade' => 40, 'timemodified' => 12345));
        $coursegradegrade->insert();

        // Run and validate when no learning objectives exist.
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, null, null, 0);

        // Reset state and create learning objective.
        $DB->delete_records(student::TABLE);
        $this->create_course_completion();

        // Run and validation when a learning objective exists.
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, null, null, 0);
    }

    /**
     * Validate that default enrolment completion times are used when run for a
     * specific user
     */
    public function test_methodsetsdefaultcompletiontimeforspecificuserid() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolments.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        // Set up PM course completion grade.
        $pmcourse = new course(array('id' => 100, 'completion_grade' => 50));
        $pmcourse->save();

        // Set up Moodle course grade item.
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->needsupdate = false;
        $coursegradeitem->locked = true;
        $coursegradeitem->update();

        // Assign the user a Moodle coruse grade.
        $coursegradegrade = new grade_grade(array('itemid' => 1, 'userid' => 100, 'finalgrade' => 40, 'timemodified' => 12345));
        $coursegradegrade->insert();
        $coursegradegrade = new grade_grade(array('itemid' => 1, 'userid' => 101, 'finalgrade' => 40, 'timemodified' => 12345));
        $coursegradegrade->insert();

        // Run and validate when no learning objectives exist.
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, null, null, 0);

        // Reset state and create learning objective.
        $DB->delete_records(student::TABLE);
        $this->create_course_completion();

        // Run and validation when a learning objective exists.
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, null, null, 0);
    }

    /**
     * Validate that enrolments are only updated when a key field changes
     */
    public function test_methodonlyupdatesenrolmentifkeyfieldchanged() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolment.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        // Set up course grade item.
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->needsupdate = false;
        $coursegradeitem->locked = true;
        $coursegradeitem->update();

        // Assign a course grade.
        $coursegradegrade = new grade_grade(array('itemid' => 1, 'userid' => 100, 'finalgrade' => 40, 'timemodified' => 1));
        $coursegradegrade->insert();

        // Set a completion grade.
        $pmcourse = new course(array('id' => 100, 'completion_grade' => 50, 'credits' => 1));
        $pmcourse->save();

        // Validate initial state.
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, 40, STUSTATUS_NOTCOMPLETE, 0, 0);

        // Only a bogus db field is updated.
        $DB->execute("UPDATE {grade_grades} SET information = 'updated'");

        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, 40, STUSTATUS_NOTCOMPLETE, 0, 0);

        // Update grade.
        $DB->execute("UPDATE {grade_grades} SET finalgrade = 45");

        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, 45, STUSTATUS_NOTCOMPLETE, 0, 0);

        // Update completestatusid.
        $DB->execute("UPDATE {".course::TABLE."} SET completion_grade = 45");

        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, 45, STUSTATUS_PASSED, 1, 1);

        // Update completetime.
        $DB->execute("UPDATE {grade_grades} SET timemodified = 12345");

        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, 45, STUSTATUS_PASSED, 12345, 1);

        // Update credits.
        $DB->execute("UPDATE {".course::TABLE."} SET credits = 2");

        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, 45, STUSTATUS_PASSED, 12345, 2);
    }

    /**
     * Validate that enrolments are only updated when a key field changes when
     * run for a specific user
     */
    public function test_methodonlyupdatesenrolmentifkeyfieldchangedforspecificuserid() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolments.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        // Set up course grade item.
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->needsupdate = false;
        $coursegradeitem->locked = true;
        $coursegradeitem->update();

        // Assign a course grade.
        $coursegradegrade = new grade_grade(array('itemid' => 1, 'userid' => 100, 'finalgrade' => 40, 'timemodified' => 1));
        $coursegradegrade->insert();
        $coursegradegrade = new grade_grade(array('itemid' => 1, 'userid' => 101, 'finalgrade' => 40, 'timemodified' => 1));
        $coursegradegrade->insert();

        // Set a completion grade.
        $pmcourse = new course(array('id' => 100, 'completion_grade' => 50, 'credits' => 1));
        $pmcourse->save();

        // Validate initial state.
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, 40, STUSTATUS_NOTCOMPLETE, 0, 0);

        // Only a bogus db field is updated.
        $DB->execute("UPDATE {grade_grades} SET information = 'updated'");

        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, 40, STUSTATUS_NOTCOMPLETE, 0, 0);

        // Update grade.
        $DB->execute("UPDATE {grade_grades} SET finalgrade = 45");

        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, 45, STUSTATUS_NOTCOMPLETE, 0, 0);

        // Update completestatusid.
        $DB->execute("UPDATE {".course::TABLE."} SET completion_grade = 45");

        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, 45, STUSTATUS_PASSED, 1, 1);

        // Update completetime.
        $DB->execute("UPDATE {grade_grades} SET timemodified = 12345");

        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, 45, STUSTATUS_PASSED, 12345, 1);

        // Update credits.
        $DB->execute("UPDATE {".course::TABLE."} SET credits = 2");

        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, 45, STUSTATUS_PASSED, 12345, 2);
    }

    /**
     * Validate that the method respects the locked status
     */
    public function test_methodonlyupdatesunlockedenrolments() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolment.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        // Set required PM course grade.
        $pmcourse = new course(array('id' => 100, 'completion_grade' => 50));
        $pmcourse->save();

        // Set up course grade item.
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->grademax = 100;
        $coursegradeitem->needsupdate = false;
        $coursegradeitem->locked = true;
        $coursegradeitem->update();

        // Assign a student grade.
        $coursegradegrade = new grade_grade(array('itemid' => 1, 'userid' => 100, 'finalgrade' => 100));
        $coursegradegrade->insert();

        // Enrol the student.
        $student = new student(array(
            'userid' => 103,
            'classid' => 100,
            'grade' => 0,
            'completestatusid' => STUSTATUS_NOTCOMPLETE,
            'locked' => 1
        ));
        $student->save();

        // Call and validate that locked record is not changed.
        pm_synchronize_moodle_class_grades();
        $this->assert_student_exists(100, 103, 0, STUSTATUS_NOTCOMPLETE, null, null, 1);
        $DB->execute("UPDATE {".student::TABLE."} SET locked = 0");

        // Call and validate that unlocked record is changed.
        pm_synchronize_moodle_class_grades();

        // Validate count.
        $count = $DB->count_records(student::TABLE, array('completestatusid' => STUSTATUS_PASSED));
        $this->assertEquals(1, $count);

        // NOTE: this method does not lock enrolments.
        $this->assert_student_exists(100, 103, 100, STUSTATUS_PASSED, null, null, 0);
    }

    /**
     * Validate that the method respects the locked status when run for a
     * specific user
     */
    public function test_methodonlyupdatesunlockedenrolmentsforspecificuserid() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolments.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        // Set required PM course grade.
        $pmcourse = new course(array('id' => 100, 'completion_grade' => 50));
        $pmcourse->save();

        // Set up course grade item.
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->grademax = 100;
        $coursegradeitem->needsupdate = false;
        $coursegradeitem->locked = true;
        $coursegradeitem->update();

        // Assign student grades.
        $coursegradegrade = new grade_grade(array('itemid' => 1, 'userid' => 100, 'finalgrade' => 100));
        $coursegradegrade->insert();
        $coursegradegrade = new grade_grade(array('itemid' => 1, 'userid' => 101, 'finalgrade' => 100));
        $coursegradegrade->insert();

        // Enrol the student.
        $student = new student;
        $student->userid = 103;
        $student->classid = 100;
        $student->grade = 0;
        $student->completestatusid = STUSTATUS_NOTCOMPLETE;
        $student->locked = 1;
        $student->save();

        // Call and validate that locked record is not changed.
        pm_synchronize_moodle_class_grades(100);
        $this->assert_student_exists(100, 103, 0, STUSTATUS_NOTCOMPLETE, null, null, 1);
        $DB->execute("UPDATE {".student::TABLE."} SET locked = 0");

        // Call and validate that unlocked record is changed.
        pm_synchronize_moodle_class_grades(100);

        // Validate count.
        $count = $DB->count_records(student::TABLE, array('completestatusid' => STUSTATUS_PASSED));
        $this->assertEquals(1, $count);

        // NOTE: this method does not lock enrolments.
        $this->assert_student_exists(100, 103, 100, STUSTATUS_PASSED, null, null, 0);
    }

    /**
     * Data provider for Learning Objective grade scaling.
     * @return array An array where each element contains the grade, max grade, and expected PM grade
     */
    public function dataprovider_logradescale() {
        return array(
                array(100, 100, 100),
                array(50, 100, 50),
                array(1, 5, 20),
                array(1, 2, 50)
        );
    }

    /**
     * Validate that LO grades are scaled correctly from Moodle grade item grades
     *
     * @param int $finalgrade The assigned grade
     * @param int $grademax The maximum grade
     * @param int $pmgrade The expected PM grade
     * @dataProvider dataprovider_logradescale
     */
    public function test_methodscalesmoodlegradeitemgrade($finalgrade, $grademax, $pmgrade) {
        global $DB;

        $this->load_csv_data();

        // Set up enrolment.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        // Set up the LO and related Moodle structure.
        $this->create_course_completion();
        $itemid = $this->create_grade_item('manualitem', $grademax);
        $this->create_grade_grade($itemid, 100, $finalgrade, $grademax);

        // Run and validate.
        pm_synchronize_moodle_class_grades();
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, $pmgrade);
    }

    /**
     * Validate that LO grades are scaled correctly from Moodle grade item grades
     * when run for a specific user
     *
     * @param int $finalgrade The assigned grade
     * @param int $grademax The maximum grade
     * @param int $pmgrade The expected PM grade
     * @dataProvider dataprovider_logradescale
     */
    public function test_methodscalesmoodlegradeitemgradeforspecificuserid($finalgrade, $grademax, $pmgrade) {
        global $DB;

        $this->load_csv_data();

        // Set up enrolments.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        // Set up the LO and related Moodle structure.
        $this->create_course_completion();
        $itemid = $this->create_grade_item('manualitem', $grademax);
        $this->create_grade_grade($itemid, 100, $finalgrade, $grademax);
        $this->create_grade_grade($itemid, 101, $finalgrade, $grademax);

        // Run and validate.
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, $pmgrade);
    }

    /**
     * Validate that LO grades are graded from Moodle grade item grades during create
     */
    public function test_methodcreateslearningobjectivegrade() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolment.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        // Set up LO and Moodle grade.
        $itemid = $this->create_grade_item();
        $this->create_grade_grade($itemid, 100, 75);
        $this->create_course_completion();

        // Run.
        $mintime = time();
        pm_synchronize_moodle_class_grades();
        $maxtime = time();

        // Validate.
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1);

        // Validate time modified since we don't validate it anywhere else for creates.
        $lograde = $DB->get_record(student_grade::TABLE, array('classid' => 100, 'userid' => 103, 'completionid' => 1));
        $this->assertGreaterThanOrEqual($mintime, $lograde->timemodified);
        $this->assertLessThanOrEqual($maxtime, $lograde->timemodified);
    }

    /**
     * Validate that LO grades are graded from Moodle grade item grades during create
     * when run for a specific user
     */
    public function test_methodcreateslearningobjectivegradeforspecificuserid() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolments.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        // Set up LO and Moodle grade.
        $itemid = $this->create_grade_item();
        $this->create_grade_grade($itemid, 100, 75);
        $this->create_grade_grade($itemid, 101, 75);
        $this->create_course_completion();

        // Run.
        $mintime = time();
        pm_synchronize_moodle_class_grades(100);
        $maxtime = time();

        // Validate.
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1);

        // Validate time modified since we don't validate it anywhere else for creates.
        $lograde = $DB->get_record(student_grade::TABLE, array('classid' => 100, 'userid' => 103, 'completionid' => 1));
        $this->assertGreaterThanOrEqual($mintime, $lograde->timemodified);
        $this->assertLessThanOrEqual($maxtime, $lograde->timemodified);
    }

    /**
     * Validate that LO grades are graded from Moodle grade item grades during update
     */
    public function test_methodupdateslearningobjectivegrade() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolment.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        // Set up LO and Moodle grade.
        $itemid = $this->create_grade_item();
        $this->create_grade_grade($itemid, 100, 75, 100, 1);
        $this->create_course_completion();

        // Create LO grade.
        $studentgrade = new student_grade(array(
            'userid' => 103,
            'classid' => 100,
            'completionid' => 1,
            'grade' => 75,
            'locked' => 0,
            'timegraded' => 1
        ));
        $studentgrade->save();

        // Validate setup.
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 75);

        // Update Moodle grade.
        $DB->execute("UPDATE {grade_grades} SET finalgrade = 80, timemodified = 2");

        // Run and validate.
        pm_synchronize_moodle_class_grades();
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 80);
    }

    /**
     * Validate that LO grades are graded from Moodle grade item grades during update
     * when run for a specific user
     */
    public function test_methodupdateslearningobjectivegradeforspecificuserid() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolments.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        // Set up LO and Moodle grade.
        $itemid = $this->create_grade_item();
        $this->create_grade_grade($itemid, 100, 75, 100, 1);
        $this->create_grade_grade($itemid, 101, 75, 100, 1);
        $this->create_course_completion();

        // Create LO grade.
        $studentgrade = new student_grade(array(
            'userid' => 103,
            'classid' => 100,
            'completionid' => 1,
            'grade' => 75,
            'locked' => 0,
            'timegraded' => 1
        ));
        $studentgrade->save();
        $studentgrade = new student_grade(array(
            'userid' => 104,
            'classid' => 100,
            'completionid' => 1,
            'grade' => 75,
            'locked' => 0,
            'timegraded' => 1
        ));
        $studentgrade->save();

        // Validate setup.
        $this->assert_num_student_grades(2);
        $this->assert_student_grade_exists(100, 103, 1, 75);

        // Update Moodle grade.
        $DB->execute("UPDATE {grade_grades} SET finalgrade = 80, timemodified = 2");

        // Run and validate.
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_student_grades(2);
        $count = $DB->count_records(student::TABLE, array('grade' => 80));
        $this->assertEquals(1, $count);
        $this->assert_student_grade_exists(100, 103, 1, 80);
    }

    /**
     * Validate that LO grades are only updated if some key field changes
     */
    public function test_methodonlyupdateslearningobjectivegradeifkeyfieldchanged() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolment.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        // Set up LO and Moodle grade.
        $itemid = $this->create_grade_item();
        $this->create_grade_grade($itemid, 100, 40, 100, 1);
        $this->create_course_completion('manualitem', 50);

        // Validate setup.
        pm_synchronize_moodle_class_grades();
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 40, 0, 1);

        // Only a bogus db field is updated.
        $DB->execute("UPDATE {grade_grades} SET information = 'updated'");

        pm_synchronize_moodle_class_grades();
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 40, 0, 1);

        // Update grade.
        $DB->execute("UPDATE {grade_grades} SET finalgrade = 45, timemodified = 2");

        pm_synchronize_moodle_class_grades();
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 45, 0, 2);

        // Update timegraded.
        $DB->execute("UPDATE {grade_grades} SET timemodified = 12345");

        pm_synchronize_moodle_class_grades();
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 45, 0, 12345);

        // Update locked.
        $DB->execute("UPDATE {".coursecompletion::TABLE."} SET completion_grade = 45");
        $DB->execute("UPDATE {grade_grades} SET timemodified = 123456");

        pm_synchronize_moodle_class_grades();
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 45, 1, 123456);
    }

    /**
     * Validate that LO grades are only updated if some key field changes when
     * run for a specific user
     */
    public function test_methodonlyupdateslearningobjectivegradeifkeyfieldchangedforspecificuserid() {
        global $DB;

        $this->load_csv_data();

        // Set up enrolments.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        // Set up LO and Moodle grade.
        $itemid = $this->create_grade_item();
        $this->create_grade_grade($itemid, 100, 40, 100, 1);
        $this->create_grade_grade($itemid, 101, 40, 100, 1);
        $this->create_course_completion('manualitem', 50);

        // Validate setup.
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 40, 0, 1);

        // Only a bogus db field is updated.
        $DB->execute("UPDATE {grade_grades} SET information = 'updated'");

        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 40, 0, 1);

        // Update grade.
        $DB->execute("UPDATE {grade_grades} SET finalgrade = 45, timemodified = 2");

        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 45, 0, 2);

        // Update timegraded.
        $DB->execute("UPDATE {grade_grades} SET timemodified = 12345");

        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 45, 0, 12345);

        // Update locked.
        $DB->execute("UPDATE {".coursecompletion::TABLE."} SET completion_grade = 45");
        $DB->execute("UPDATE {grade_grades} SET timemodified = 123456");

        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 45, 1, 123456);
    }

    /**
     * Validate that updating LO grades respects the locked flag
     */
    public function test_methodonlyupdatesunlockedlearningobjectivegrades() {
        global $DB;

        // Set up enrolment.
        $this->load_csv_data();
        $this->make_course_enrollable();

        // Create LO and Moodle grade.
        $itemid = $this->create_grade_item();
        $this->create_grade_grade($itemid, 100, 75, 100, 2);
        $this->create_course_completion('manualitem', 50);

        // Assign a PM grade.
        $studentgrade = new student_grade(array(
            'userid' => 103,
            'classid' => 100,
            'completionid' => 1,
            'grade' => 50,
            'locked' => 1,
            'timegraded' => 1
        ));
        $studentgrade->save();

        // Validate setup with element locked.
        enrol_try_internal_enrol(2, 100, 1);
        pm_synchronize_moodle_class_grades();
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 50, 1, 1);

        // Validate update with element unlocked.
        $DB->execute("UPDATE {".student_grade::TABLE."} SET locked = 0");

        // Run and validate.
        pm_synchronize_moodle_class_grades();
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 75, 1);
    }

    /**
     * Validate that updating LO grades respects the locked flag when run for a
     * specific user
     */
    public function test_methodonlyupdatesunlockedlearningobjectivegradesforspecificuserid() {
        global $DB;

        // Set up enrolment.
        $this->load_csv_data();
        $this->make_course_enrollable();

        // Create LO and Moodle grade.
        $itemid = $this->create_grade_item();
        $this->create_grade_grade($itemid, 100, 75, 100, 2);
        $this->create_grade_grade($itemid, 101, 75, 100, 2);
        $this->create_course_completion('manualitem', 50);

        // Assign a PM grade.
        $studentgrade = new student_grade(array(
            'userid' => 103,
            'classid' => 100,
            'completionid' => 1,
            'grade' => 50,
            'locked' => 1,
            'timegraded' => 1
        ));
        $studentgrade->save();
        $studentgrade = new student_grade(array(
            'userid' => 104,
            'classid' => 100,
            'completionid' => 1,
            'grade' => 50,
            'locked' => 1,
            'timegraded' => 1
        ));
        $studentgrade->save();

        // Validate setup with element locked.
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_student_grades(2);
        $this->assert_student_grade_exists(100, 103, 1, 50, 1, 1);
        $this->assert_student_grade_exists(100, 104, 1, 50, 1, 1);

        // Validate update with element unlocked.
        $DB->execute("UPDATE {".student_grade::TABLE."} SET locked = 0");

        // Run and validate.
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_student_grades(2);
        $this->assert_student_grade_exists(100, 103, 1, 75, 1);
        $this->assert_student_grade_exists(100, 104, 1, 50, 0);
    }

    /**
     * Validate that LO grades are locked when they are created and grade
     * is sufficient
     */
    public function test_methodlockslearningobjectivegradesduringcreate() {
        global $DB;

        $this->load_csv_data();

        // Create enrolment.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        // Create LO and Moodle grade.
        $itemid = $this->create_grade_item();
        $this->create_grade_grade($itemid, 100, 75, 100, 1);
        $this->create_course_completion('manualitem', 50);

        // Run and validate.
        pm_synchronize_moodle_class_grades();
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 75, 1);
    }

    /**
     * Validate that LO grades are locked when they are created and grade
     * is sufficient when run for a specific user
     */
    public function test_methodlockslearningobjectivegradesduringcreateforspecificuserid() {
        global $DB;

        $this->load_csv_data();

        // Create enrolment.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        // Create LO and Moodle grade.
        $itemid = $this->create_grade_item();
        $this->create_grade_grade($itemid, 100, 75, 100, 1);
        $this->create_grade_grade($itemid, 101, 75, 100, 1);
        $this->create_course_completion('manualitem', 50);

        // Run and validate.
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 75, 1);
    }

    /**
     * Validate that LO grades are locked when they are updated and grade
     * is sufficient
     */
    public function test_methodlockslearningobjectivegradesduringupdate() {
        global $DB;

        $this->load_csv_data();

        // Create enrolment.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        // Create LO and Moodle grade.
        $itemid = $this->create_grade_item();
        $this->create_grade_grade($itemid, 100, 75, 100, 1);
        $this->create_course_completion('manualitem', 50);

        // Enrol in PM class.
        $studentgrade = new student_grade(array(
            'userid' => 103,
            'classid' => 100,
            'completionid' => 1,
            'grade' => 75,
            'locked' => 0,
            'timegraded' => 1
        ));
        $studentgrade->save();

        // Validate setup.
        $this->assert_num_student_grades(1);
        $count = $DB->count_records(student_grade::TABLE, array('locked' => 1));
        $this->assertEquals(0, $count);
        $this->assert_student_grade_exists(100, 103, 1, null, 0);

        // Update Moodle info.
        $DB->execute("UPDATE {grade_grades} SET finalgrade = 80, timemodified = 2");

        // Run and validate.
        pm_synchronize_moodle_class_grades();
        $this->assert_num_student_grades(1);
        $count = $DB->count_records(student_grade::TABLE, array('locked' => 1));
        $this->assertEquals(1, $count);
        $this->assert_student_grade_exists(100, 103, 1, null, 1);
    }

    /**
     * Validate that LO grades are locked when they are updated and grade
     * is sufficient when run for a specific user
     */
    public function test_methodlockslearningobjectivegradesduringupdateforspecificuserid() {
        global $DB;

        $this->load_csv_data();

        // Create enrolment.
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        // Create LO and Moodle grade.
        $itemid = $this->create_grade_item();
        $this->create_grade_grade($itemid, 100, 75, 100, 1);
        $this->create_grade_grade($itemid, 101, 75, 100, 1);
        $this->create_course_completion('manualitem', 50);

        // Enrol in PM class.
        $studentgrade = new student_grade(array(
            'userid' => 103,
            'classid' => 100,
            'completionid' => 1,
            'grade' => 75,
            'locked' => 0,
            'timegraded' => 1
        ));
        $studentgrade->save();
        $studentgrade = new student_grade(array(
            'userid' => 104,
            'classid' => 100,
            'completionid' => 1,
            'grade' => 75,
            'locked' => 0,
            'timegraded' => 1
        ));
        $studentgrade->save();

        // Validate setup.
        $this->assert_num_student_grades(2);
        $count = $DB->count_records(student_grade::TABLE, array('locked' => 1));
        $this->assertEquals(0, $count);
        $this->assert_student_grade_exists(100, 103, 1, null, 0);

        // Update Moodle info.
        $DB->execute("UPDATE {grade_grades} SET finalgrade = 80, timemodified = 2");

        // Run and validate.
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_student_grades(2);
        $count = $DB->count_records(student_grade::TABLE, array('locked' => 1));
        $this->assertEquals(1, $count);
        $this->assert_student_grade_exists(100, 103, 1, null, 1);
    }

    /**
     * Validate that even with duplicate enrolment records, the grade synchronisation still runs correctly and can synchronise
     * data for unique enrolments.
     */
    public function test_gradesyncwithduplicateclassenrolmentrecords() {
        global $DB;

        $dataset = $this->createCsvDataSet(array(
            'context' => elis::component_file('program', 'tests/fixtures/gsync_context.csv'),
            'course' => elis::component_file('program', 'tests/fixtures/gsync_mdl_course.csv'),
            'grade_grades' => elis::component_file('program', 'tests/fixtures/gsync_grade_grades.csv'),
            'grade_items' => elis::component_file('program', 'tests/fixtures/gsync_grade_items.csv'),
            'role_assignments' => elis::component_file('program', 'tests/fixtures/gsync_role_assignments.csv'),
            'user' => elis::component_file('program', 'tests/fixtures/gsync_mdl_user.csv'),
            'user_enrolments' => elis::component_file('program', 'tests/fixtures/gsync_user_enrolments.csv'),
            'enrol' => elis::component_file('program', 'tests/fixtures/gsync_enrol.csv'),
            pmclass::TABLE => elis::component_file('program', 'tests/fixtures/gsync_class.csv'),
            student::TABLE => elis::component_file('program', 'tests/fixtures/gsync_class_enrolment.csv'),
            classmoodlecourse::TABLE => elis::component_file('program', 'tests/fixtures/gsync_class_moodle.csv'),
            course::TABLE => elis::component_file('program', 'tests/fixtures/gsync_course.csv'),
            user::TABLE => elis::component_file('program', 'tests/fixtures/gsync_user.csv'),
            usermoodle::TABLE => elis::component_file('program', 'tests/fixtures/gsync_user_moodle.csv'),
        ));
        $this->loadDataSet($dataset);

        // We need to reset the context cache.
        accesslib_clear_all_caches(true);

        // Make our role a "student" role.
        set_config('gradebookroles', 1);

        // Force synchronisation of grade data from Moodle to ELIS.
        pm_synchronize_moodle_class_grades();

        $params = array(
            'classid' => 1,
            'userid'  => 120,
            'grade'   => 75.00000
        );
        $this->assertTrue($DB->record_exists(student::TABLE, $params));

        $params['userid']           = 100;
        $params['grade']            = 77.0000;
        $params['completestatusid'] = STUSTATUS_PASSED;
        $this->assertTrue($DB->record_exists(student::TABLE, $params));

        $params['userid'] = 130;
        $params['grade']  = 82.00000;
        $this->assertTrue($DB->record_exists(student::TABLE, $params));

        $params['userid'] = 110;
        $params['grade']  = 88.00000;
        $this->assertTrue($DB->record_exists(student::TABLE, $params));

        $params['userid']           = 110;
        $params['completestatusid'] = STUSTATUS_NOTCOMPLETE;
        $params['grade']            = 0.00000;
        $this->assertTrue($DB->record_exists(student::TABLE, $params));
    }

    /**
     * Test the grade synchronisation when there are duplicate course_module.idnumber values present.
     */
    public function test_sync_with_duplicate_course_module_idnumbers() {
        global $CFG, $DB;

        $this->load_csv_data();

        $olddebug        = null;
        $olddebugdisplay = null;

        // Developer debugging must be enabled and displayed for this test to work.
        if ($CFG->debug < DEBUG_DEVELOPER) {
            $olddebug = $CFG->debug;
            $CFG->debug = DEBUG_DEVELOPER;
        }
        if ($CFG->debugdisplay == false) {
            $olddebugdisplay = false;
            $CFG->debugdisplay = true;
        }

        // Set up grade item and completion item.
        $itemid = $this->create_grade_item('duplicateidnumber');
        $this->create_grade_grade($itemid, 100, 75);
        $completionid = $this->create_course_completion('duplicateidnumber');

        // Insert a couple duplicate course_module 'idnumber' balues but for different course ID values.
        $cmobj = new stdClass;
        $cmobj->course   = 1000;
        $cmobj->module   = 20;
        $cmobj->instance = 100;
        $cmobj->section  = 1;
        $cmobj->idnumber = 'duplicateidnumber';
        $DB->insert_record('course_modules', $cmobj);

        $cmobj->course = 2000;
        $DB->insert_record('course_modules', $cmobj);

        // Using an output buffer here because the following function will throw a debugging error if more than one record is found.
        ob_start();
        pm_synchronize_moodle_class_grades();
        $buffer = ob_get_contents();
        ob_end_clean();

        $this->assertEquals('', $buffer);

        // Restore old values if we modified them in this test.
        if ($olddebug != null) {
            $CFG->debug = $olddebug;
        }
        if ($olddebugdisplay != null) {
            $CFG->debugdisplay = $olddebugdisplay;
        }
    }
}
