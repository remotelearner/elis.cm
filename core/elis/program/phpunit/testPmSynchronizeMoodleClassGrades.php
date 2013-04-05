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
require_once(dirname(__FILE__).'/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once(elispm::lib('data/course.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));
require_once(elispm::lib('data/student.class.php'));
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('lib.php'));
require_once($CFG->dirroot.'/lib/grade/grade_item.php');
require_once($CFG->dirroot.'/lib/enrollib.php');

/**
 * Unit testing for the pm_synchronize_moodle_class_grades method
 */
class pmSynchronizeMoodleClassGradesTest extends elis_database_test {
    /**
     * Return the list of tables that should be overlayed.
     *
     * @return array The mapping of overlay tables to components
     */
    static protected function get_overlay_tables() {
        return array(
            'config' => 'moodle',
            'context' => 'moodle',
            'course' => 'moodle',
            'course_categories' => 'moodle',
            'enrol' => 'moodle',
            //prevent events magic from happening
            'events_handlers' => 'moodle',
            'grade_categories' => 'moodle',
            'grade_grades' => 'moodle',
            'grade_items' => 'moodle',
            'role' => 'moodle',
            'role_assignments' => 'moodle',
            'user' => 'moodle',
            'user_enrolments' => 'moodle',
            field::TABLE => 'elis_core',
            classmoodlecourse::TABLE => 'elis_program',
            course::TABLE => 'elis_program',
            coursecompletion::TABLE => 'elis_program',
            pmclass::TABLE => 'elis_program',
            student::TABLE => 'elis_program',
            student_grade::TABLE => 'elis_program',
            user::TABLE => 'elis_program',
            usermoodle::TABLE => 'elis_program'
        );
    }

    /**
     * Return the list of tables that should be ignored for writes.
     */
    static protected function get_ignored_tables() {
        return array(
            'cache_flags' => 'moodle',
            'grade_categories_history' => 'moodle',
            'grade_grades_history' => 'moodle',
            'grade_items_history' => 'moodle',
            coursetemplate::TABLE => 'moodle'
        );
    }

    /**
     * Load base data from CSV file
     *
     * @param boolean $link_class If true, link the PM class to the Moodle course
     */
    private function load_csv_data($link_class = true) {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        //need PM course to create PM class
        $dataset->addTable(course::TABLE, elis::component_file('program', 'phpunit/pmcourse.csv'));
        //need PM classes to create associations
        $dataset->addTable(pmclass::TABLE, elis::component_file('program', 'phpunit/pmclass.csv'));
        //need a Moodle course to sync users from
        $dataset->addTable('course', elis::component_file('program', 'phpunit/mdlcoursenonsite.csv'));
        //need both the Moodle and the PM user
        $dataset->addTable('user', elis::component_file('program', 'phpunit/mdluser.csv'));
        $dataset->addTable(user::TABLE, elis::component_file('program', 'phpunit/pmuser.csv'));
        $dataset->addTable(usermoodle::TABLE, elis::component_file('program', 'phpunit/usermoodle.csv'));
        //set up a role to assign
        $dataset->addTable('role', elis::component_file('program', 'phpunit/role.csv'));

        if ($link_class) {
            //link the course and the class
            $dataset->addTable(classmoodlecourse::TABLE, elis::component_file('program', 'phpunit/class_moodle_course_nonsite.csv'));
        }

        load_phpunit_data_set($dataset, true, self::$overlaydb);

        //make our role a "student" role
        set_config('gradebookroles', 1);
    }

    /**
     * Set up our main Moodle course to be enrollable
     */
    private function make_course_enrollable() {
        set_config('enrol_plugins_enabled', 'manual');

        $enrol = enrol_get_plugin('manual');
        $course = new stdClass;
        $course->id = 2;
        $enrol->add_instance($course);
    }

    /**
     * Validate that a certain number of student records exist
     *
     * @param int $num The expected number of student records
     */
    private function assert_num_students($num) {
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
    private function assert_student_exists($classid, $userid, $grade = NULL, $completestatusid = NULL, $completetime = NULL,
                                           $credits = NULL, $locked = NULL) {
        global $DB;

        //required fields
        $params = array('classid' => $classid,
                        'userid' => $userid);

        //optional fields
        if ($grade !== NULL) {
            $params['grade'] = $grade;
        }
        if ($completestatusid !== NULL) {
            $params['completestatusid'] = $completestatusid;
        }
        if ($completetime !== NULL) {
            $params['completetime'] = $completetime;
        }
        if ($credits !== NULL) {
            $params['credits'] = $credits;
        }
        if ($locked !== NULL) {
            $params['locked'] = $locked;
        }

        //validate existence
        $exists = $DB->record_exists(student::TABLE, $params);
        $this->assertTrue($exists);
    }

    /**
     * Validate that a certain number of student grade records exist
     *
     * @param int $num The expected number of student grade records
     */
    private function assert_num_student_grades($num) {
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
    private function assert_student_grade_exists($classid, $userid, $completionid, $grade = NULL, $locked = NULL,
                                                 $timegraded = NULL) {
        global $DB;

        //required fields
        $params = array('classid' => $classid,
                        'userid' => $userid,
                        'completionid' => $completionid);
        //optional fields
        if ($grade !== NULL) {
            $params['grade'] = $grade;
        }
        if ($locked !== NULL) {
            $params['locked'] = $locked;
        }
        if ($timegraded !== NULL) {
            $params['timegraded'] = $timegraded;
        }

        //validate existence
        $exists = $DB->record_exists(student_grade::TABLE, $params);
        $this->assertTrue($exists);
    }

    /**
     * Create a Moodle grade item for our test course
     *
     * @param string $idnumber The grade item's idnumber
     * @param int $grademax The max grade (100 if not specified);
     */
    private function create_grade_item($idnumber = 'manualitem', $grademax = NULL) {
        //required fields
        $data = array('courseid' => 2,
                      'itemtype' => 'manual',
                      'idnumber' => $idnumber,
                      'needsupdate' => false,
                      'locked' => true);
        //optional fields
        if ($grademax !== NULL) {
            $data['grademax'] = $grademax;
        }

        //save the record
        $grade_item = new grade_item($data);
        $grade_item->insert();
        return $grade_item->id;
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
    private function create_grade_grade($itemid, $userid, $finalgrade, $rawgrademax = 100, $timemodified = NULL) {
        $grade_grade = new grade_grade(array('itemid' => $itemid,
                                             'userid' => $userid,
                                             'finalgrade' => $finalgrade,
                                             'rawgrademax' => $rawgrademax,
                                             'timemodified' => $timemodified));
        $grade_grade->insert();
    }

    /**
     * Create a PM course completion record
     *
     * @param string $idnumber The idnumber of the course completion / LO
     * @param int $completion_grade The required completion grad
     * @return int The db record id of the element
     */
    private function create_course_completion($idnumber = 'manualitem', $completion_grade = NULL) {
        //required fields
        $data = array('courseid' => 100,
                      'idnumber' => $idnumber);
        //optional fields
        if ($completion_grade !== NULL) {
            $data['completion_grade'] = $completion_grade;
        }

        //save
        $coursecompletion = new coursecompletion($data);
        $coursecompletion->save();

        //return id
        return $coursecompletion->id;
    }

    /**
     * Validate that the sync depends on course-class associations
     */
    public function testNoSyncHappensWhenClassNotAsociated() {
        $this->load_csv_data(false);

        //set up enrolment
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        //call and validate
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(0);

        //associate class with course
        $classmoodlecourse = new classmoodlecourse(array('classid' => 100,
                                                         'moodlecourseid' => 2));
        $classmoodlecourse->save();

        //call and validate
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
    }

    /**
     * Validate that the sync depends on course-class associations when running
     * for a specific user
     */
    public function testNoSyncHappensWhenClassNotAsociatedForSpecificUserid() {
        $this->load_csv_data(false);

        //set up enrolments
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        //call and validate
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(0);

        //associate class with course
        $classmoodlecourse = new classmoodlecourse(array('classid' => 100,
                                                         'moodlecourseid' => 2));
        $classmoodlecourse->save();

        //call and validate
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
    }

    /**
     * Validate that the sync ignores orphaned classes
     */
    public function testNoSyncHappensWhenClassAssociatedToDeletedMoodleCourse() {
        global $DB;

        $this->load_csv_data();

        //set up enrolment
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        //orphan the class
        $DB->delete_records('course');

        //call and validate
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(0);

        //re-associate
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable('course', elis::component_file('program', 'phpunit/mdlcoursenonsite.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);

        //call and validate
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
    }

    /**
     * Validate that the sync ignores orphaned classes when running for a specific
     * user
     */
    public function testNoSyncHappensWhenClassAssociatedToDeleteMoodleCourseForSpecificUserid() {
        global $DB;

        $this->load_csv_data();

        //set up enrolments
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        //orphan the class
        $DB->delete_records('course');

        //call and validate
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(0);

        //re-associate
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable('course', elis::component_file('program', 'phpunit/mdlcoursenonsite.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);

        //call and validate
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
    }

    /**
     * Validate that the sync has fix for extra slashes in idnumbers
     */
    public function testMethodSupportsGradeItemIdnumbersWithSlashes() {
        global $DB;

        $this->load_csv_data();

        //set up enrolment
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        //set up grade item and completion item
        $itemid = $this->create_grade_item("\'withslashes\'");
        $this->create_grade_grade($itemid, 100, 75);
        $completionid = $this->create_course_completion("'withslashes'");

        //call and validate
        pm_synchronize_moodle_class_grades();
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, $completionid, 75);
    }

    /**
     * Validate that the sync has fix for extra slashes in idnumbers when running
     * for a specific user
     */
    public function testMethodSupportsGradeItemIdnumbersWithSlashesForSpecificUserid() {
        global $DB;

        $this->load_csv_data();

        //set up enrolments
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        //set up grade item and completion item
        $itemid = $this->create_grade_item("\'withslashes\'");
        $this->create_grade_grade($itemid, 100, 75);
        $this->create_grade_grade($itemid, 101, 75);
        $completionid = $this->create_course_completion("'withslashes'");

        //call and validate
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, $completionid, 75);
    }

    /**
     * Validate that the sync depends on Moodle enrolments
     */
    public function testNoSyncHappensWhenNoUsersEnrolled() {
        global $DB;

        $this->load_csv_data();

        //call and validate with no enrolments
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(0);

        //set up enrolment
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        //call and validate when enrolment exists
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
    }

    /**
     * Validate that the sync depends on Moodle enrolments when running for a
     * specific user
     */
    public function testNoSyncHappensWhenNoUsersEnrolledForSpecificUserid() {
        global $DB;

        $this->load_csv_data();

        //call and validate with no enrolments
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(0);

        //set up enrolments
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        //call and validate when enrolment exists
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
    }

    /**
     * Validate that the sync ignores courses with no max grade
     */
    public function testNoSyncHappensWhenCourseHasNoMaxGrade() {
        global $DB;

        $this->load_csv_data();

        //set up enrolment
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        //create course grade item with no max grade
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->grademax = 0;
        $coursegradeitem->update();

        //call and validate
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(0);

        //set valid max grade
        $coursegradeitem->grademax = 100;
        $coursegradeitem->update();

        //call and validate
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
    }

    /**
     * Validate that the sync ignores courses with no max grade when running
     * for a specific user
     */
    public function testNoSyncHappensWhenCourseHasNoMaxGradeForSpecificUserid() {
        global $DB;

        $this->load_csv_data();

        //set up enrolments
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        //create course grade item with no max grade
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->grademax = 0;
        $coursegradeitem->update();

        //call and validate
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(0);

        //set valid max grade
        $coursegradeitem->grademax = 100;
        $coursegradeitem->update();

        //call and validate
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
    }

    /**
     * Validate that the sync prevents grades from syncing into two classes
     */
    public function testNoSyncHappensWhenCourseLinkedToTwoClasses() {
        global $DB;

        $this->load_csv_data();

        //set up enrolment
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        //create second association
        $classmoodlecourse = new classmoodlecourse(array('classid' => 101,
                                                         'moodlecourseid' => 2));
        $classmoodlecourse->save();

        //call and validate
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(0);

        //remove second association
        $DB->delete_records(classmoodlecourse::TABLE, array('classid' => 101,
                                                            'moodlecourseid' => 2));

        //call and validate
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
    }

    /**
     * Validate that the sync prevents grades from syncing into two classes when
     * running for a specific user
     */
    public function testNoSyncHappensWhenCourseLinkedToTwoClassesForSpecificUserid() {
        global $DB;

        $this->load_csv_data();

        //set up enrolments
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        //create second association
        $classmoodlecourse = new classmoodlecourse(array('classid' => 101,
                                                         'moodlecourseid' => 2));
        $classmoodlecourse->save();

        //call and validate
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(0);

        //remove second association
        $DB->delete_records(classmoodlecourse::TABLE, array('classid' => 101,
                                                            'moodlecourseid' => 2));

        //call and validate
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
    }

    /**
     * Validate that recordsets iterate correctly and skip non-matching records
     * when dealing with multiple users
     */
    public function testMethodSkipsNonMatchingRecords() {
        global $DB;

        $this->load_csv_data();

        //set up enrolments
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        //set up grade items and PM completion info
        $this->create_course_completion();
        $itemid = $this->create_grade_item();
        $this->create_grade_grade($itemid, 100, 75, 100, 2);
        $this->create_grade_grade($itemid, 101, 75, 100, 2);

        //call and validate
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(2);
        $this->assert_student_exists(100, 103);
        $this->assert_student_exists(100, 104);

        $this->assert_num_student_grades(2);
        $this->assert_student_grade_exists(100, 103, 1);
        $this->assert_student_grade_exists(100, 104, 1);
    }

    //NOTE: it doesn't really make sense to have a version of the above test case
    //for a single user

    /**
     * Validate that enrolments are successfully created
     */
    public function testMethodCreatesEnrolment() {
        global $DB;

        $this->load_csv_data();

        //set up enrolment
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        //call and validate
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);

        //validate end time since no other unit test does this for creates
        $this->assert_student_exists(100, 103);
        $enrolment = $DB->get_record(student::TABLE, array('classid' => 100,
                                                           'userid' => 103));
        $this->assertEquals(0, $enrolment->endtime);
    }

    /**
     * Validate that enrolments are successfully created when running for a
     * specific user
     */
    public function testMethodCreatesEnrolmentForSpecificUserid() {
        global $DB;

        $this->load_csv_data();

        //set up enrolments
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        //call and validate
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);

        //validate end time since no other unit test does this for creates
        $this->assert_student_exists(100, 103);
        $enrolment = $DB->get_record(student::TABLE, array('classid' => 100,
                                                           'userid' => 103));
        $this->assertEquals(0, $enrolment->endtime);
    }

    /**
     * Validate that enrolments are successfully updated
     */
    public function testMethodUpdatesEnrolment() {
        global $DB;

        $this->load_csv_data();

        //set up enrolment
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        //set up grade
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->needsupdate = false;
        $coursegradeitem->locked = true;
        $coursegradeitem->update();
        $this->create_grade_grade(1, 100, 75);

        //set up PM enrolments
        $student = new student(array('userid' => 103,
                                     'classid' => 100,
                                     'grade' => 25));
        $student->save();

        //validate setup
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, 25);

        //call and validate
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
        $count = $DB->count_records(student::TABLE, array('grade' => 75));
        $this->assertEquals(1, $count);
        $this->assert_student_exists(100, 103, 75);

        //validate end time since no other unit test does this for updates
        $enrolment = $DB->get_record(student::TABLE, array('classid' => 100,
                                                           'userid' => 103));
        $this->assertEquals(0, $enrolment->endtime);
    }

    /**
     * Validate that enrolments are successfully updated for a specific user
     */
    public function testMethodUpdatesEnrolmentForSpecificUserid() {
        global $DB;

        $this->load_csv_data();

        //set up enrolments
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        //set up grade
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->needsupdate = false;
        $coursegradeitem->locked = true;
        $coursegradeitem->update();
        $this->create_grade_grade(1, 100, 75);
        $this->create_grade_grade(1, 101, 75);

        //set up PM enrolments
        $student = new student(array('userid' => 103,
                                     'classid' => 100,
                                     'grade' => 25));
        $student->save();
        $student = new student(array('userid' => 104,
                                     'classid' => 100,
                                     'grade' => 25));
        $student->save();

        //validate setup
        $this->assert_num_students(2);
        $this->assert_student_exists(100, 103, 25);

        //call and validate
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(2);
        $count = $DB->count_records(student::TABLE, array('grade' => 75));
        $this->assertEquals(1, $count);
        $this->assert_student_exists(100, 103, 75);

        //validate end time since no other unit test does this for updates
        $enrolment = $DB->get_record(student::TABLE, array('classid' => 100,
                                                           'userid' => 103));
        $this->assertEquals(0, $enrolment->endtime);
    }

    /**
     * Validate that PM enrolment end times are set from moodle
     */
    public function testMethodSetsEnrolmentTimeFromMoodleEnrolment() {
        global $DB;

        $this->load_csv_data();

        //set up enrolment
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1, 999999);

        //call and validate
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
        $exists = $DB->record_exists(student::TABLE, array('enrolmenttime' => 999999));
        $this->assertTrue($exists);
    }

    /**
     * Validate that PM enrolment end times are set from moodle when run for a
     * specific user
     */
    public function testMethodSetsEnrolmentTimeFromMoodleEnrolmentForSpecificUserid() {
        global $DB;

        $this->load_csv_data();

        //set up enrolments
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1, 999999);
        enrol_try_internal_enrol(2, 101, 1, 999999);

        //call and validate
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
        $exists = $DB->record_exists(student::TABLE, array('enrolmenttime' => 999999));
        $this->assertTrue($exists);
    }

    /**
     * Validate default for PM enrolment end time
     */
    public function testMethodSetsDefaultEnrolmentTime() {
        global $DB;

        $this->load_csv_data();

        //set up enrolment
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        //set start time to an empty value
        $DB->execute("UPDATE {user_enrolments}
                      SET timestart = 0");

        //run and collect timing info
        $mintime = time();
        pm_synchronize_moodle_class_grades();
        $maxtime = time();

        //validate
        $this->assert_num_students(1);
        $enrolment = $DB->get_record(student::TABLE, array('id' => 1));
        $this->assertGreaterThanOrEqual($mintime, $enrolment->enrolmenttime);
        $this->assertLessThanOrEqual($maxtime, $enrolment->enrolmenttime);
    }

    /**
     * Validate default for PM enrolment end time when run for a specific user
     */
    public function testMethodSetsDefaultEnrolmentTimeForSpecificUserid() {
        global $DB;

        $this->load_csv_data();

        //set up enrolments
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        //set start time to an empty value
        $DB->execute("UPDATE {user_enrolments}
                      SET timestart = 0");

        //run and collect timing info
        $mintime = time();
        pm_synchronize_moodle_class_grades(100);
        $maxtime = time();

        //validate
        $this->assert_num_students(1);
        $enrolment = $DB->get_record(student::TABLE, array('id' => 1));
        $this->assertGreaterThanOrEqual($mintime, $enrolment->enrolmenttime);
        $this->assertLessThanOrEqual($maxtime, $enrolment->enrolmenttime);
    }

    /**
     * Data provider for scaling grades
     *
     * @return array An array containing current grade, max grade, and percentage
     *               in each position
     */
    function enrolmentGradeScaleProvider() {
        return array(array(100, 100, 100),
                     array(50, 100, 50),
                     array(1, 5, 20),
                     array(1, 2, 50));
    }

    /**
     * Validate that enrolment grade scaling works correctly
     *
     * @param int $finalgrade The user's grade in Moodle
     * @param int $grademax The maximum grade in Moodle
     * @param int $pmgrade The expected PM grade (percentage)
     * @dataProvider enrolmentGradeScaleProvider
     */
    public function testMethodScalesMoodleCourseGrade($finalgrade, $grademax, $pmgrade) {
        global $DB;

        $this->load_csv_data();

        //set up enrolment
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        //set up grade information
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->grademax = $grademax;
        $coursegradeitem->needsupdate = false;
        $coursegradeitem->locked = true;
        $coursegradeitem->update();

        $coursegradegrade = new grade_grade(array('itemid' => 1,
                                                  'userid' => 100,
                                                  'rawgrademax' => $grademax,
                                                  'finalgrade' => $finalgrade));
        $coursegradegrade->insert();

        //call and validate
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, $pmgrade);
    }

    /**
     * Validate that enrolment grade scaling works correctly when running for a
     * specific user
     *
     * @param int $finalgrade The user's grade in Moodle
     * @param int $grademax The maximum grade in Moodle
     * @param int $pmgrade The expected PM grade (percentage)
     * @dataProvider enrolmentGradeScaleProvider
     */
    public function testMethodScalesMoodleCourseGradeForSpecificUserid($finalgrade, $grademax, $pmgrade) {
        global $DB;

        $this->load_csv_data();

        //set up enrolments
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        //set up grade information
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->grademax = $grademax;
        $coursegradeitem->needsupdate = false;
        $coursegradeitem->locked = true;
        $coursegradeitem->update();

        $coursegradegrade = new grade_grade(array('itemid' => 1,
                                                  'userid' => 100,
                                                  'rawgrademax' => $grademax,
                                                  'finalgrade' => $finalgrade));
        $coursegradegrade->insert();
        $coursegradegrade = new grade_grade(array('itemid' => 1,
                                                  'userid' => 101,
                                                  'rawgrademax' => $grademax,
                                                  'finalgrade' => $finalgrade));
        $coursegradegrade->insert();

        //call and validate
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, $pmgrade);
    }

    /**
     * Validate that when no learning objectives are present, enrolments can be marked as passed
     * when they have a sufficient grade
     */
    public function testMethodMarkEnrolmentPassedWhenGradeSufficientAndNoRequiredLearningObjectives() {
        global $DB;

        $this->load_csv_data();

        //set up enrolment
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        //set up PM course completion criteria
        $pmcourse = new course(array('id' => 100,
                                     'completion_grade' => 50));
        $pmcourse->save();

        //set up course grade item info in Moodle
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->grademax = 100;
        $coursegradeitem->needsupdate = false;
        $coursegradeitem->locked = true;
        $coursegradeitem->update();

        //set up PM class enrolment with sufficient grade
        $coursegradegrade = new grade_grade(array('itemid' => 1,
                                                  'userid' => 100,
                                                  'finalgrade' => 100));
        $coursegradegrade->insert();

        //call and validate
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, NULL, STUSTATUS_PASSED);
    }

    /**
     * Validate that when no learning objectives are present, enrolments can be marked as passed
     * when they have a sufficient grade when run for a specific user
     */
    public function testMethodMarkEnrolmentPassedWhenGradeSufficientAndNoRequiredLearningObjectivesForSpecificUserid() {
        global $DB;

        $this->load_csv_data();

        //set up enrolments
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        //set up PM course completion criteria
        $pmcourse = new course(array('id' => 100,
                                     'completion_grade' => 50));
        $pmcourse->save();

        //set up course grade item info in Moodle
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->grademax = 100;
        $coursegradeitem->needsupdate = false;
        $coursegradeitem->locked = true;
        $coursegradeitem->update();

        //set up PM class enrolment with sufficient grade
        $coursegradegrade = new grade_grade(array('itemid' => 1,
                                                  'userid' => 100,
                                                  'finalgrade' => 100));
        $coursegradegrade->insert();
        $coursegradegrade = new grade_grade(array('itemid' => 1,
                                                  'userid' => 101,
                                                  'finalgrade' => 100));
        $coursegradegrade->insert();

        //call and validate
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, NULL, STUSTATUS_PASSED);
    }

    /**
     * Validate that enrolment completion times are properly maintained
     */
    public function testMethodSetsCompletionTimeFromMoodleGradeItem() {
        global $DB;

        $this->load_csv_data();

        //set up enrolment
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        //set up required PM course grade
        $pmcourse = new course(array('id' => 100,
                                     'completion_grade' => 50));
        $pmcourse->save();

        //set up Moodle course grade item info
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->needsupdate = false;
        $coursegradeitem->locked = true;
        $coursegradeitem->update();

        //assign the user a grade in Moodle
        $coursegradegrade = new grade_grade(array('itemid' => 1,
                                                  'userid' => 100,
                                                  'finalgrade' => 100,
                                                  'timemodified' => 12345));
        $coursegradegrade->insert();

        //call and validate
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, NULL, NULL, 12345);
    }

    /**
     * Validate that enrolment completion times are properly maintained when run
     * for a specific user
     */
    public function testMethodSetsCompletionTimeFromMoodleGradeItemForSpecificUserid() {
        global $DB;

        $this->load_csv_data();

        //set up enrolments
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        //set up required PM course grade
        $pmcourse = new course(array('id' => 100,
                                     'completion_grade' => 50));
        $pmcourse->save();

        //set up Moodle course grade item info
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->needsupdate = false;
        $coursegradeitem->locked = true;
        $coursegradeitem->update();

        //assign the user a grade in Moodle
        $coursegradegrade = new grade_grade(array('itemid' => 1,
                                                  'userid' => 100,
                                                  'finalgrade' => 100,
                                                  'timemodified' => 12345));
        $coursegradegrade->insert();
        $coursegradegrade = new grade_grade(array('itemid' => 1,
                                                  'userid' => 101,
                                                  'finalgrade' => 100,
                                                  'timemodified' => 12345));
        $coursegradegrade->insert();

        //call and validate
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, NULL, NULL, 12345);
    }

    /**
     * Validate that default enrolment completion times are used
     */
    public function testMethodSetsDefaultCompletionTime() {
        global $DB;

        $this->load_csv_data();

        //set up enrolment
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        //set up PM course completion grade
        $pmcourse = new course(array('id' => 100,
                                     'completion_grade' => 50));
        $pmcourse->save();

        //set up Moodle course grade item
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->needsupdate = false;
        $coursegradeitem->locked = true;
        $coursegradeitem->update();

        //assign the user a Moodle coruse grade
        $coursegradegrade = new grade_grade(array('itemid' => 1,
                                                  'userid' => 100,
                                                  'finalgrade' => 40,
                                                  'timemodified' => 12345));
        $coursegradegrade->insert();

        //run and validate when no learning objectives exist
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, NULL, NULL, 0);

        //reset state and create learning objective
        $DB->delete_records(student::TABLE);
        $this->create_course_completion();

        //run and validation when a learning objective exists
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, NULL, NULL, 0);
    }

    /**
     * Validate that default enrolment completion times are used when run for a
     * specific user
     */
    public function testMethodSetsDefaultCompletionTimeForSpecificUserid() {
        global $DB;

        $this->load_csv_data();

        //set up enrolments
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        //set up PM course completion grade
        $pmcourse = new course(array('id' => 100,
                                     'completion_grade' => 50));
        $pmcourse->save();

        //set up Moodle course grade item
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->needsupdate = false;
        $coursegradeitem->locked = true;
        $coursegradeitem->update();

        //assign the user a Moodle coruse grade
        $coursegradegrade = new grade_grade(array('itemid' => 1,
                                                  'userid' => 100,
                                                  'finalgrade' => 40,
                                                  'timemodified' => 12345));
        $coursegradegrade->insert();
        $coursegradegrade = new grade_grade(array('itemid' => 1,
                                                  'userid' => 101,
                                                  'finalgrade' => 40,
                                                  'timemodified' => 12345));
        $coursegradegrade->insert();

        //run and validate when no learning objectives exist
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, NULL, NULL, 0);

        //reset state and create learning objective
        $DB->delete_records(student::TABLE);
        $this->create_course_completion();

        //run and validation when a learning objective exists
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, NULL, NULL, 0);
    }

    /**
     * Validate that enrolments are only updated when a key field changes
     */
    public function testMethodOnlyUpdatesEnrolmentIfKeyFieldChanged() {
        global $DB;

        $this->load_csv_data();

        //set up enrolment
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        //set up course grade item
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->needsupdate = false;
        $coursegradeitem->locked = true;
        $coursegradeitem->update();

        //assign a course grade
        $coursegradegrade = new grade_grade(array('itemid' => 1,
                                                  'userid' => 100,
                                                  'finalgrade' => 40,
                                                  'timemodified' => 1));
        $coursegradegrade->insert();

        //set a completion grade
        $pmcourse = new course(array('id' => 100,
                                     'completion_grade' => 50,
                                     'credits' => 1));
        $pmcourse->save();

        //validate initial state
        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, 40, STUSTATUS_NOTCOMPLETE, 0, 0);

        //only a bogus db field is updated
        $DB->execute("UPDATE {grade_grades}
                      SET information = 'updated'");

        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, 40, STUSTATUS_NOTCOMPLETE, 0, 0);

        //update grade
        $DB->execute("UPDATE {grade_grades}
                      SET finalgrade = 45");

        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, 45, STUSTATUS_NOTCOMPLETE, 0, 0);

        //update completestatusid
        $DB->execute("UPDATE {".course::TABLE."}
                      SET completion_grade = 45");

        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, 45, STUSTATUS_PASSED, 1, 1);

        //update completetime
        $DB->execute("UPDATE {grade_grades}
                      SET timemodified = 12345");

        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, 45, STUSTATUS_PASSED, 12345, 1);

        //update credits
        $DB->execute("UPDATE {".course::TABLE."}
                      SET credits = 2");

        pm_synchronize_moodle_class_grades();
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, 45, STUSTATUS_PASSED, 12345, 2);
    }

    /**
     * Validate that enrolments are only updated when a key field changes when
     * run for a specific user
     */
    public function testMethodOnlyUpdatesEnrolmentIfKeyFieldChangedForSpecificUserid() {
        global $DB;

        $this->load_csv_data();

        //set up enrolments
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        //set up course grade item
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->needsupdate = false;
        $coursegradeitem->locked = true;
        $coursegradeitem->update();

        //assign a course grade
        $coursegradegrade = new grade_grade(array('itemid' => 1,
                                                  'userid' => 100,
                                                  'finalgrade' => 40,
                                                  'timemodified' => 1));
        $coursegradegrade->insert();
        $coursegradegrade = new grade_grade(array('itemid' => 1,
                                                  'userid' => 101,
                                                  'finalgrade' => 40,
                                                  'timemodified' => 1));
        $coursegradegrade->insert();

        //set a completion grade
        $pmcourse = new course(array('id' => 100,
                                     'completion_grade' => 50,
                                     'credits' => 1));
        $pmcourse->save();

        //validate initial state
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, 40, STUSTATUS_NOTCOMPLETE, 0, 0);

        //only a bogus db field is updated
        $DB->execute("UPDATE {grade_grades}
                      SET information = 'updated'");

        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, 40, STUSTATUS_NOTCOMPLETE, 0, 0);

        //update grade
        $DB->execute("UPDATE {grade_grades}
                      SET finalgrade = 45");

        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, 45, STUSTATUS_NOTCOMPLETE, 0, 0);

        //update completestatusid
        $DB->execute("UPDATE {".course::TABLE."}
                      SET completion_grade = 45");

        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, 45, STUSTATUS_PASSED, 1, 1);

        //update completetime
        $DB->execute("UPDATE {grade_grades}
                      SET timemodified = 12345");

        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, 45, STUSTATUS_PASSED, 12345, 1);

        //update credits
        $DB->execute("UPDATE {".course::TABLE."}
                      SET credits = 2");

        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_students(1);
        $this->assert_student_exists(100, 103, 45, STUSTATUS_PASSED, 12345, 2);
    }

    /**
     * Validate that the method respects the locked status
     */
    public function testMethodOnlyUpdatesUnlockedEnrolments() {
        global $DB;

        $this->load_csv_data();

        //set up enrolment
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        //set required PM course grade
        $pmcourse = new course(array('id' => 100,
                                     'completion_grade' => 50));
        $pmcourse->save();

        //set up course grade item
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->grademax = 100;
        $coursegradeitem->needsupdate = false;
        $coursegradeitem->locked = true;
        $coursegradeitem->update();

        //assign a student grade
        $coursegradegrade = new grade_grade(array('itemid' => 1,
                                                  'userid' => 100,
                                                  'finalgrade' => 100));
        $coursegradegrade->insert();

        //enrol the student
        $student = new student(array('userid' => 103,
                                     'classid' => 100,
                                     'grade' => 0,
                                     'completestatusid' => STUSTATUS_NOTCOMPLETE,
                                     'locked' => 1));
        $student->save();

        //call and validate that locked record is not changed
        pm_synchronize_moodle_class_grades();
        $this->assert_student_exists(100, 103, 0, STUSTATUS_NOTCOMPLETE, NULL, NULL, 1);
        $DB->execute("UPDATE {".student::TABLE."}
                      SET locked = 0");

        //call and validate that unlocked record is changed
        pm_synchronize_moodle_class_grades();

        //validate count
        $count = $DB->count_records(student::TABLE, array('completestatusid' => STUSTATUS_PASSED));
        $this->assertEquals(1, $count);

        //NOTE: this method does not lock enrolments
        $this->assert_student_exists(100, 103, 100, STUSTATUS_PASSED, NULL, NULL, 0);
    }

    /**
     * Validate that the method respects the locked status when run for a
     * specific user
     */
    public function testMethodOnlyUpdatesUnlockedEnrolmentsForSpecificUserid() {
        global $DB;

        $this->load_csv_data();

        //set up enrolments
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        //set required PM course grade
        $pmcourse = new course(array('id' => 100,
                                     'completion_grade' => 50));
        $pmcourse->save();

        //set up course grade item
        $coursegradeitem = grade_item::fetch_course_item(2);
        $coursegradeitem->grademax = 100;
        $coursegradeitem->needsupdate = false;
        $coursegradeitem->locked = true;
        $coursegradeitem->update();

        //assign student grades
        $coursegradegrade = new grade_grade(array('itemid' => 1,
                                                  'userid' => 100,
                                                  'finalgrade' => 100));
        $coursegradegrade->insert();
        $coursegradegrade = new grade_grade(array('itemid' => 1,
                                                  'userid' => 101,
                                                  'finalgrade' => 100));
        $coursegradegrade->insert();

        //enrol the student
        $student = new student(array('userid' => 103,
                                     'classid' => 100,
                                     'grade' => 0,
                                     'completestatusid' => STUSTATUS_NOTCOMPLETE,
                                     'locked' => 1));
        $student->save();

        //call and validate that locked record is not changed
        pm_synchronize_moodle_class_grades(100);
        $this->assert_student_exists(100, 103, 0, STUSTATUS_NOTCOMPLETE, NULL, NULL, 1);
        $DB->execute("UPDATE {".student::TABLE."}
                      SET locked = 0");

        //call and validate that unlocked record is changed
        pm_synchronize_moodle_class_grades(100);

        //validate count
        $count = $DB->count_records(student::TABLE, array('completestatusid' => STUSTATUS_PASSED));
        $this->assertEquals(1, $count);

        //NOTE: this method does not lock enrolments
        $this->assert_student_exists(100, 103, 100, STUSTATUS_PASSED, NULL, NULL, 0);
    }

    /**
     * Data provider for Learning Objective grade scaling
     *
     * @return array An array where each element contains the grade, max grade,
     *               and expected PM grade
     */
    function loGradeScaleProvider() {
        return array(array(100, 100, 100),
                     array(50, 100, 50),
                     array(1, 5, 20),
                     array(1, 2, 50));
    }

    /**
     * Validate that LO grades are scaled correctly from Moodle grade item grades
     *
     * @param int $finalgrade The assigned grade
     * @param int $grademax The maximum grade
     * @param int $pmgrade The expected PM grade
     * @dataProvider loGradeScaleProvider
     */
    public function testMethodScalesMoodleGradeItemGrade($finalgrade, $grademax, $pmgrade) {
        global $DB;

        $this->load_csv_data();

        //set up enrolment
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        //set up the LO and related Moodle structure
        $this->create_course_completion();
        $itemid = $this->create_grade_item('manualitem', $grademax);
        $this->create_grade_grade($itemid, 100, $finalgrade, $grademax);

        //run and validate
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
     * @dataProvider loGradeScaleProvider
     */
    public function testMethodScalesMoodleGradeItemGradeForSpecificUserid($finalgrade, $grademax, $pmgrade) {
        global $DB;

        $this->load_csv_data();

        //set up enrolments
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        //set up the LO and related Moodle structure
        $this->create_course_completion();
        $itemid = $this->create_grade_item('manualitem', $grademax);
        $this->create_grade_grade($itemid, 100, $finalgrade, $grademax);
        $this->create_grade_grade($itemid, 101, $finalgrade, $grademax);

        //run and validate
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, $pmgrade);
    }

    /**
     * Validate that LO grades are graded from Moodle grade item grades during create
     */
    public function testMethodCreatesLearningObjectiveGrade() {
        global $DB;

        $this->load_csv_data();

        //set up enrolment
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        //set up LO and Moodle grade
        $itemid = $this->create_grade_item();
        $this->create_grade_grade($itemid, 100, 75);
        $this->create_course_completion();

        //run
        $mintime = time();
        pm_synchronize_moodle_class_grades();
        $maxtime = time();

        //validate
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1);

        //validate time modified since we don't validate it anywhere else for creates
        $lograde = $DB->get_record(student_grade::TABLE, array('classid' => 100,
                                                               'userid' => 103,
                                                               'completionid' => 1));
        $this->assertGreaterThanOrEqual($mintime, $lograde->timemodified);
        $this->assertLessThanOrEqual($maxtime, $lograde->timemodified);
    }

    /**
     * Validate that LO grades are graded from Moodle grade item grades during create
     * when run for a specific user
     */
    public function testMethodCreatesLearningObjectiveGradeForSpecificUserid() {
        global $DB;

        $this->load_csv_data();

        //set up enrolments
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        //set up LO and Moodle grade
        $itemid = $this->create_grade_item();
        $this->create_grade_grade($itemid, 100, 75);
        $this->create_grade_grade($itemid, 101, 75);
        $this->create_course_completion();

        //run
        $mintime = time();
        pm_synchronize_moodle_class_grades(100);
        $maxtime = time();

        //validate
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1);

        //validate time modified since we don't validate it anywhere else for creates
        $lograde = $DB->get_record(student_grade::TABLE, array('classid' => 100,
                                                               'userid' => 103,
                                                               'completionid' => 1));
        $this->assertGreaterThanOrEqual($mintime, $lograde->timemodified);
        $this->assertLessThanOrEqual($maxtime, $lograde->timemodified);
    }

    /**
     * Validate that LO grades are graded from Moodle grade item grades during update
     */
    public function testMethodUpdatesLearningObjectiveGrade() {
        global $DB;

        $this->load_csv_data();

        //set up enrolment
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        //set up LO and Moodle grade
        $itemid = $this->create_grade_item();
        $this->create_grade_grade($itemid, 100, 75, 100, 1);
        $this->create_course_completion();

        //create LO grade
        $student_grade = new student_grade(array('userid' => 103,
                                                 'classid' => 100,
                                                 'completionid' => 1,
                                                 'grade' => 75,
                                                 'locked' => 0,
                                                 'timegraded' => 1));
        $student_grade->save();

        //validate setup
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 75);

        //update Moodle grade
        $DB->execute("UPDATE {grade_grades}
                      SET finalgrade = 80,
                      timemodified = 2");

        //run and validate
        pm_synchronize_moodle_class_grades();
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 80);
    }

    /**
     * Validate that LO grades are graded from Moodle grade item grades during update
     * when run for a specific user
     */
    public function testMethodUpdatesLearningObjectiveGradeForSpecificUserid() {
        global $DB;

        $this->load_csv_data();

        //set up enrolments
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        //set up LO and Moodle grade
        $itemid = $this->create_grade_item();
        $this->create_grade_grade($itemid, 100, 75, 100, 1);
        $this->create_grade_grade($itemid, 101, 75, 100, 1);
        $this->create_course_completion();

        //create LO grade
        $student_grade = new student_grade(array('userid' => 103,
                                                 'classid' => 100,
                                                 'completionid' => 1,
                                                 'grade' => 75,
                                                 'locked' => 0,
                                                 'timegraded' => 1));
        $student_grade->save();
        $student_grade = new student_grade(array('userid' => 104,
                                                 'classid' => 100,
                                                 'completionid' => 1,
                                                 'grade' => 75,
                                                 'locked' => 0,
                                                 'timegraded' => 1));
        $student_grade->save();

        //validate setup
        $this->assert_num_student_grades(2);
        $this->assert_student_grade_exists(100, 103, 1, 75);

        //update Moodle grade
        $DB->execute("UPDATE {grade_grades}
                      SET finalgrade = 80,
                      timemodified = 2");

        //run and validate
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_student_grades(2);
        $count = $DB->count_records(student::TABLE, array('grade' => 80));
        $this->assertEquals(1, $count);
        $this->assert_student_grade_exists(100, 103, 1, 80);
    }

    /**
     * Validate that LO grades are only updated if some key field changes
     */
    public function testMethodOnlyUpdatesLearningObjectiveGradeIfKeyFieldChanged() {
        global $DB;

        $this->load_csv_data();

        //set up enrolment
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        //set up LO and Moodle grade
        $itemid = $this->create_grade_item();
        $this->create_grade_grade($itemid, 100, 40, 100, 1);
        $this->create_course_completion('manualitem', 50);

        //validate setup
        pm_synchronize_moodle_class_grades();
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 40, 0, 1);

        //only a bogus db field is updated
        $DB->execute("UPDATE {grade_grades}
                      SET information = 'updated'");

        pm_synchronize_moodle_class_grades();
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 40, 0, 1);

        //update grade
        $DB->execute("UPDATE {grade_grades}
                      SET finalgrade = 45,
                      timemodified = 2");

        pm_synchronize_moodle_class_grades();
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 45, 0, 2);

        //update timegraded
        $DB->execute("UPDATE {grade_grades}
                      SET timemodified = 12345");

        pm_synchronize_moodle_class_grades();
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 45, 0, 12345);

        //update locked
        $DB->execute("UPDATE {".coursecompletion::TABLE."}
                      SET completion_grade = 45");
        $DB->execute("UPDATE {grade_grades}
                      SET timemodified = 123456");

        pm_synchronize_moodle_class_grades();
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 45, 1, 123456);
    }

    /**
     * Validate that LO grades are only updated if some key field changes when
     * run for a specific user
     */
    public function testMethodOnlyUpdatesLearningObjectiveGradeIfKeyFieldChangedForSpecificUserid() {
        global $DB;

        $this->load_csv_data();

        //set up enrolments
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        //set up LO and Moodle grade
        $itemid = $this->create_grade_item();
        $this->create_grade_grade($itemid, 100, 40, 100, 1);
        $this->create_grade_grade($itemid, 101, 40, 100, 1);
        $this->create_course_completion('manualitem', 50);

        //validate setup
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 40, 0, 1);

        //only a bogus db field is updated
        $DB->execute("UPDATE {grade_grades}
                      SET information = 'updated'");

        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 40, 0, 1);

        //update grade
        $DB->execute("UPDATE {grade_grades}
                      SET finalgrade = 45,
                      timemodified = 2");

        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 45, 0, 2);

        //update timegraded
        $DB->execute("UPDATE {grade_grades}
                      SET timemodified = 12345");

        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 45, 0, 12345);

        //update locked
        $DB->execute("UPDATE {".coursecompletion::TABLE."}
                      SET completion_grade = 45");
        $DB->execute("UPDATE {grade_grades}
                      SET timemodified = 123456");

        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 45, 1, 123456);
    }

    /**
     * Validate that updating LO grades respects the locked flag
     */
    public function testMethodOnlyUpdatesUnlockedLearningObjectiveGrades() {
        global $DB;

        //set up enrolment
        $this->load_csv_data();
        $this->make_course_enrollable();

        //create LO and Moodle grade
        $itemid = $this->create_grade_item();
        $this->create_grade_grade($itemid, 100, 75, 100, 2);
        $this->create_course_completion('manualitem', 50);

        //assign a PM grade
        $student_grade = new student_grade(array('userid' => 103,
                                                 'classid' => 100,
                                                 'completionid' => 1,
                                                 'grade' => 50,
                                                 'locked' => 1,
                                                 'timegraded' => 1));
        $student_grade->save();

        //validate setup with element locked
        enrol_try_internal_enrol(2, 100, 1);
        pm_synchronize_moodle_class_grades();
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 50, 1, 1);

        //validate update with element unlocked
        $DB->execute("UPDATE {".student_grade::TABLE."}
                      SET locked = 0");

        //run and validate
        pm_synchronize_moodle_class_grades();
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 75, 1);
    }

    /**
     * Validate that updating LO grades respects the locked flag when run for a
     * specific user
     */
    public function testMethodOnlyUpdatesUnlockedLearningObjectiveGradesForSpecificUserid() {
        global $DB;

        //set up enrolment
        $this->load_csv_data();
        $this->make_course_enrollable();

        //create LO and Moodle grade
        $itemid = $this->create_grade_item();
        $this->create_grade_grade($itemid, 100, 75, 100, 2);
        $this->create_grade_grade($itemid, 101, 75, 100, 2);
        $this->create_course_completion('manualitem', 50);

        //assign a PM grade
        $student_grade = new student_grade(array('userid' => 103,
                                                 'classid' => 100,
                                                 'completionid' => 1,
                                                 'grade' => 50,
                                                 'locked' => 1,
                                                 'timegraded' => 1));
        $student_grade->save();
        $student_grade = new student_grade(array('userid' => 104,
                                                 'classid' => 100,
                                                 'completionid' => 1,
                                                 'grade' => 50,
                                                 'locked' => 1,
                                                 'timegraded' => 1));
        $student_grade->save();

        //validate setup with element locked
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_student_grades(2);
        $this->assert_student_grade_exists(100, 103, 1, 50, 1, 1);
        $this->assert_student_grade_exists(100, 104, 1, 50, 1, 1);

        //validate update with element unlocked
        $DB->execute("UPDATE {".student_grade::TABLE."}
                      SET locked = 0");

        //run and validate
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_student_grades(2);
        $this->assert_student_grade_exists(100, 103, 1, 75, 1);
        $this->assert_student_grade_exists(100, 104, 1, 50, 0);
    }

    /**
     * Validate that LO grades are locked when they are created and grade
     * is sufficient
     */
    public function testMethodLocksLearningObjectiveGradesDuringCreate() {
        global $DB;

        $this->load_csv_data();

        //create enrolment
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        //create LO and Moodle grade
        $itemid = $this->create_grade_item();
        $this->create_grade_grade($itemid, 100, 75, 100, 1);
        $this->create_course_completion('manualitem', 50);

        //run and validate
        pm_synchronize_moodle_class_grades();
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 75, 1);
    }

    /**
     * Validate that LO grades are locked when they are created and grade
     * is sufficient when run for a specific user
     */
    public function testMethodLocksLearningObjectiveGradesDuringCreateForSpecificUserid() {
        global $DB;

        $this->load_csv_data();

        //create enrolment
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        //create LO and Moodle grade
        $itemid = $this->create_grade_item();
        $this->create_grade_grade($itemid, 100, 75, 100, 1);
        $this->create_grade_grade($itemid, 101, 75, 100, 1);
        $this->create_course_completion('manualitem', 50);

        //run and validate
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_student_grades(1);
        $this->assert_student_grade_exists(100, 103, 1, 75, 1);
    }

    /**
     * Validate that LO grades are locked when they are updated and grade
     * is sufficient
     */
    public function testMethodLocksLearningObjectiveGradesDuringUpdate() {
        global $DB;

        $this->load_csv_data();

        //create enrolment
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);

        //create LO and Moodle grade
        $itemid = $this->create_grade_item();
        $this->create_grade_grade($itemid, 100, 75, 100, 1);
        $this->create_course_completion('manualitem', 50);

        //enrol in PM class
        $student_grade = new student_grade(array('userid' => 103,
                                                 'classid' => 100,
                                                 'completionid' => 1,
                                                 'grade' => 75,
                                                 'locked' => 0,
                                                 'timegraded' => 1));
        $student_grade->save();

        //validate setup
        $this->assert_num_student_grades(1);
        $count = $DB->count_records(student_grade::TABLE, array('locked' => 1));
        $this->assertEquals(0, $count);
        $this->assert_student_grade_exists(100, 103, 1, NULL, 0);

        //update Moodle info
        $DB->execute("UPDATE {grade_grades}
                      SET finalgrade = 80,
                      timemodified = 2");

        //run and validate
        pm_synchronize_moodle_class_grades();
        $this->assert_num_student_grades(1);
        $count = $DB->count_records(student_grade::TABLE, array('locked' => 1));
        $this->assertEquals(1, $count);
        $this->assert_student_grade_exists(100, 103, 1, NULL, 1);
    }

    /**
     * Validate that LO grades are locked when they are updated and grade
     * is sufficient when run for a specific user
     */
    public function testMethodLocksLearningObjectiveGradesDuringUpdateForSpecificUserid() {
        global $DB;

        $this->load_csv_data();

        //create enrolment
        $this->make_course_enrollable();
        enrol_try_internal_enrol(2, 100, 1);
        enrol_try_internal_enrol(2, 101, 1);

        //create LO and Moodle grade
        $itemid = $this->create_grade_item();
        $this->create_grade_grade($itemid, 100, 75, 100, 1);
        $this->create_grade_grade($itemid, 101, 75, 100, 1);
        $this->create_course_completion('manualitem', 50);

        //enrol in PM class
        $student_grade = new student_grade(array('userid' => 103,
                                                 'classid' => 100,
                                                 'completionid' => 1,
                                                 'grade' => 75,
                                                 'locked' => 0,
                                                 'timegraded' => 1));
        $student_grade->save();
        $student_grade = new student_grade(array('userid' => 104,
                                                 'classid' => 100,
                                                 'completionid' => 1,
                                                 'grade' => 75,
                                                 'locked' => 0,
                                                 'timegraded' => 1));
        $student_grade->save();

        //validate setup
        $this->assert_num_student_grades(2);
        $count = $DB->count_records(student_grade::TABLE, array('locked' => 1));
        $this->assertEquals(0, $count);
        $this->assert_student_grade_exists(100, 103, 1, NULL, 0);

        //update Moodle info
        $DB->execute("UPDATE {grade_grades}
                      SET finalgrade = 80,
                      timemodified = 2");

        //run and validate
        pm_synchronize_moodle_class_grades(100);
        $this->assert_num_student_grades(2);
        $count = $DB->count_records(student_grade::TABLE, array('locked' => 1));
        $this->assertEquals(1, $count);
        $this->assert_student_grade_exists(100, 103, 1, NULL, 1);
    }

    /**
     * Validate that even with duplicate enrolment records, the grade synchronisation still runs correctly and can synchronise
     * data for unique enrolments.
     */
    public function testGradeSyncWithDuplicateClassEnrolmentRecords() {
        global $DB;

        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();

        $dataset->addTable('context', elis::component_file('program', 'phpunit/gsync_context.csv'));
        $dataset->addTable('course', elis::component_file('program', 'phpunit/gsync_mdl_course.csv'));
        $dataset->addTable('grade_grades', elis::component_file('program', 'phpunit/gsync_grade_grades.csv'));
        $dataset->addTable('grade_items', elis::component_file('program', 'phpunit/gsync_grade_items.csv'));
        $dataset->addTable('role', elis::component_file('program', 'phpunit/role.csv'));
        $dataset->addTable('role_assignments', elis::component_file('program', 'phpunit/gsync_role_assignments.csv'));
        $dataset->addTable('user', elis::component_file('program', 'phpunit/gsync_mdl_user.csv'));
        $dataset->addTable('user_enrolments', elis::component_file('program', 'phpunit/gsync_user_enrolments.csv'));
        $dataset->addTable('enrol', elis::component_file('program', 'phpunit/gsync_enrol.csv'));

        $dataset->addTable(pmclass::TABLE, elis::component_file('program', 'phpunit/gsync_class.csv'));
        $dataset->addTable(student::TABLE, elis::component_file('program', 'phpunit/gsync_class_enrolment.csv'));
        $dataset->addTable(classmoodlecourse::TABLE, elis::component_file('program', 'phpunit/gsync_class_moodle.csv'));
        $dataset->addTable(course::TABLE, elis::component_file('program', 'phpunit/gsync_course.csv'));
        $dataset->addTable(user::TABLE, elis::component_file('program', 'phpunit/gsync_user.csv'));
        $dataset->addTable(usermoodle::TABLE, elis::component_file('program', 'phpunit/gsync_user_moodle.csv'));

        load_phpunit_data_set($dataset, true, self::$overlaydb);

        // We need to reset the context cache
        accesslib_clear_all_caches(true);

        // Make our role a "student" role
        set_config('gradebookroles', 1);

        // Force synchronisation of grade data from Moodle to ELIS
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
}
