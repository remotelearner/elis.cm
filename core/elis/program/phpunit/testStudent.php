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

require_once(elispm::lib('data/classmoodlecourse.class.php'));
require_once(elispm::lib('data/course.class.php'));
require_once(elispm::lib('data/coursetemplate.class.php'));
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/student.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));
require_once(elispm::lib('data/waitlist.class.php'));

require_once($CFG->dirroot.'/lib/phpunit/classes/util.php');
require_once($CFG->dirroot.'/elis/program/phpunit/datagenerator.php');

class studentTest extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

    protected static function get_overlay_tables() {
        return array(
            'cache_flags' => 'moodle',
            'context' => 'moodle',
            'course' => 'moodle',
            'enrol' => 'moodle',
            'forum' => 'mod_forum',
            'forum_read' => 'mod_forum',
            'forum_subscriptions' => 'mod_forum',
            'forum_track_prefs' => 'mod_forum',
            'groups' => 'moodle',
            'groups_members' => 'moodle',
            'message' => 'moodle',
            'message_working' => 'moodle',
            'user' => 'moodle',
            'user_enrolments' => 'moodle',
            'user_info_data' => 'moodle',
            'user_lastaccess' => 'moodle',
            classmoodlecourse::TABLE => 'elis_program',
            course::TABLE => 'elis_program',
            'elis_field_data_char' => 'elis_core',
            'elis_field_data_int' => 'elis_core',
            'elis_field_data_num' => 'elis_core',
            'elis_field_data_text' => 'elis_core',
            GRDTABLE => 'elis_program',
            pmclass::TABLE => 'elis_program',
            student::TABLE => 'elis_program',
            student_grade::TABLE => 'elis_program',
            user::TABLE => 'elis_program',
            usermoodle::TABLE => 'elis_program',
            waitlist::TABLE => 'elis_program',
        );
    }

    protected function setUp() {
        global $DB;
        parent::setUp();
        $DB = self::$origdb; // setUpContextsTable needs $DB to be the real
                             // database for get_admin()
        $this->setUpContextsTable();
        $DB = self::$overlaydb;
    }

    protected function tearDown() {
        global $DB;
        $DB = self::$origdb;
    }

    /**
     * Set up the contexts table with the minimum that we need.
     */
    private function setUpContextsTable() {
        global $CFG;
        // system context
        $syscontext = self::$origdb->get_record('context', array('contextlevel' => CONTEXT_SYSTEM));
        self::$overlaydb->import_record('context', $syscontext);

        // site (front page) course
        $site = self::$origdb->get_record('course', array('id' => SITEID));
        self::$overlaydb->import_record('course', $site);
        $sitecontext = self::$origdb->get_record('context', array('contextlevel' => CONTEXT_COURSE,
                                                                  'instanceid' => SITEID));
        self::$overlaydb->import_record('context', $sitecontext);

        // primary admin user
        $admin = get_admin();
        if ($admin) {
            self::$overlaydb->import_record('user', $admin);
            $CFG->siteadmins = $admin->id;
            $usercontext = self::$origdb->get_record('context', array('contextlevel' => CONTEXT_USER,
                                                                      'instanceid' => $admin->id));
            self::$overlaydb->import_record('context', $usercontext);

            // copy admin user's ELIS user (if available)
            $elisuser = user::find(new field_filter('idnumber', $admin->idnumber), array(), 0, 0, self::$origdb);
            if ($elisuser->valid()) {
                $elisuser = $elisuser->current();
                self::$overlaydb->import_record(user::TABLE, $elisuser->to_object());
            }
        }
    }

    protected function load_csv_data() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable('user', elis::component_file('program', 'phpunit/mdluser.csv'));
        $dataset->addTable(course::TABLE, elis::component_file('program', 'phpunit/pmcourse.csv'));
        $dataset->addTable(pmclass::TABLE, elis::component_file('program', 'phpunit/pmclass.csv'));
        $dataset->addTable(user::TABLE, elis::component_file('program', 'phpunit/pmuser.csv'));
        $dataset->addTable(student::TABLE, elis::component_file('program', 'phpunit/student.csv'));
        $dataset->addTable(waitlist::TABLE, elis::component_file('program', 'phpunit/waitlist2.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    protected function load_csv_data_moodlenrol() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable('course', elis::component_file('program', 'phpunit/mdlcourse.csv'));
        $dataset->addTable('enrol', elis::component_file('program', 'phpunit/enrol.csv'));
        $dataset->addTable('user', elis::component_file('program', 'phpunit/mdluser.csv'));
        $dataset->addTable('user_enrolments', elis::component_file('program', 'phpunit/user_enrolments.csv'));
        $dataset->addTable(classmoodlecourse::TABLE, elis::component_file('program', 'phpunit/class_moodle_course.csv'));
        $dataset->addTable(pmclass::TABLE, elis::component_file('program', 'phpunit/pmclass.csv'));
        $dataset->addTable(student::TABLE, elis::component_file('program', 'phpunit/student.csv'));
        $dataset->addTable(user::TABLE, elis::component_file('program', 'phpunit/pmuser.csv'));
        $dataset->addTable(usermoodle::TABLE, elis::component_file('program', 'phpunit/usermoodle.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    /**
     * Test validation of empty userid
     *
     * @expectedException data_object_validation_exception
     */
    public function testStudentValidationPreventsEmptyUserid() {
        $this->load_csv_data();

        $student = new student(array('classid' => 100));

        $student->save();
    }

    /**
     * Test validation of empty classid
     *
     * @expectedException data_object_validation_exception
     */
    public function testStudentValidationPreventsEmptyClassid() {
        $this->load_csv_data();

        $student = new student(array('userid' => 103));

        $student->save();
    }

    /**
     * Test validation of invalid userid
     *
     * @expectedException dml_missing_record_exception
     */
    public function testStudentValidationPreventsInvalidUserid() {
        $this->load_csv_data();

        $student = new student(array('userid' => 1,
                                     'classid' => 100));

        $student->save();
    }

    /**
     * Test validation of invalid classid
     *
     * @expectedException dml_missing_record_exception
     */
    public function testStudentValidationPreventsInvalidClassid() {
        $this->load_csv_data();

        $student = new student(array('userid' => 103,
                                     'classid' => 1));

        $student->save();
    }

    /**
     * Test validation of duplicates
     *
     * Note: no exception thrown from student.class.php for dup.
     */
    public function testStudentValidationPreventsDuplicates() {
        global $DB;
        $this->load_csv_data();

        $student = new student(array('userid' => 103,
                                     'classid' => 100));

        $student->save();
        $stus = $DB->get_records(student::TABLE, array('userid' => 103, 'classid' => 100));
        $this->assertEquals(count($stus), 1);
    }

    /**
     * Test the insertion of a valid association record
     */
    public function testStudentValidationAllowsValidRecord() {
        $this->load_csv_data();

        $student = new student(array('userid' => 103,
                                     'classid' => 101));

        $student->save();

        $this->assertTrue(true);
    }

    public function test_delete() {
        global $DB;

        $this->load_csv_data_moodlenrol();

        //verify enrolment exists
        $enrol = $DB->get_record_sql(
                'SELECT enrol.*
                  FROM {user_enrolments} enrolments
                  JOIN {enrol} enrol ON enrol.id = enrolments.enrolid
                 WHERE enrol.courseid = ?
                   AND enrolments.userid = ?',
                array(1,100)
        );
        $this->assertNotEmpty($enrol);

        //delete the student record
        $student = new student;
        $student->userid = 103;
        $student->classid = 100;
        $student->delete();

        //verify enrolment deleted
        $enrol = $DB->get_record_sql(
                'SELECT enrol.*
                  FROM {user_enrolments} enrolments
                  JOIN {enrol} enrol ON enrol.id = enrolments.enrolid
                 WHERE enrol.courseid = ?
                   AND enrolments.userid = ?',
                array(1,100)
        );
        $this->assertEmpty($enrol);
    }

    public function test_delete_enrols_waitlist() {
        global $DB;
        $this->load_csv_data();

        $student = new student(array('userid' => 104, 'classid' => 100));
        $student->load();
        $student->save();

        $class = new pmclass(100);
        $class->load();
        $class->maxstudents = 1;
        $class->save();

        try {
            $student->delete();
        } catch (Exception $e) {
            $this->assertEquals(get_string('message_nodestinationuser', 'elis_program'), $e->getMessage());
        }
    }

    public function test_get_students() {
        //fixture
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(user::TABLE, elis::component_file('program', 'phpunit/pmuser.csv'));
        $dataset->addTable(student::TABLE, elis::component_file('program', 'phpunit/student.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);

        //test
        $student = new student;
        $student->classid = 100;
        $students = $student->get_students();
        $this->assertNotEmpty($students);

        //verify
        $found = false;
        foreach ($students as $userrec) {
            if ($userrec->id === '103') {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function test_get_waiting() {
        //fixture
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(user::TABLE, elis::component_file('program', 'phpunit/pmuser.csv'));
        $dataset->addTable(waitlist::TABLE, elis::component_file('program', 'phpunit/waitlist2.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);

        //test
        $student = new student;
        $student->classid = 100;
        $users_on_waitlist = $student->get_waiting();
        $this->assertNotEmpty($users_on_waitlist);

        //verify
        $found = false;
        foreach ($users_on_waitlist as $userrec) {
            if ($userrec->id === '103') {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    /**
     * Validate the $student->validate_class_enrolment_limit function.
     */
    public function test_validate_class_enrolment_limit() {
        $this->load_csv_data();
        $student = new student(array('userid' => 104, 'classid' => 101));
        $student->load();
        $student->save();

        try {
            $result = $student->validate_class_enrolment_limit();
            $this->assertTrue($result);
        } catch (Exception $e) {
            // Should not reach here.
            $this->assertFalse(true);
        }

        $class = new pmclass(101);
        $class->load();
        $class->maxstudents = 1;
        $class->save();

        $student = new student(array('userid' => 103, 'classid' => 101));

        try {
            $result = $student->validate_class_enrolment_limit();
        } catch (Exception $e) {
            $this->assertTrue($e instanceof pmclass_enrolment_limit_validation_exception);
        }
    }
}
