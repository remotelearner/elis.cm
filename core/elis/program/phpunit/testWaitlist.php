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
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/student.class.php'));
require_once(elispm::lib('data/waitlist.class.php'));
require_once(elispm::file('coursecatalogpage.class.php'));

/**
 * Mock course catalog page that doesn't do any displaying of info
 */
class coursecatalogpage_nodisplay extends coursecatalogpage {
    /**
     * Display the page.
     */
    public function display($action=null) {
        //ignore any displays - only testing back-end
    }
}

/**
 * Test waitlist functions.
 */
class waitlistTest extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

    /**
     * Get list of overlay tables
     * @return array Array of overlay tables.
     */
    protected static function get_overlay_tables() {
        require_once(elispm::lib('data/course.class.php'));
        require_once(elispm::lib('data/user.class.php'));
        require_once(elispm::lib('data/usermoodle.class.php'));

        return array(
            'config' => 'moodle',
            'message_read' => 'moodle',
            'user' => 'moodle',
            'user_info_data' => 'moodle',
            course::TABLE => 'elis_program',
            pmclass::TABLE => 'elis_program',
            student::TABLE => 'elis_program',
            user::TABLE => 'elis_program',
            usermoodle::TABLE => 'elis_program',
            waitlist::TABLE => 'elis_program',
            'elis_field_data_char' => 'elis_core',
            'elis_field_data_int' => 'elis_core',
            'elis_field_data_num' => 'elis_core',
            'elis_field_data_text' => 'elis_core'
        );
    }

    /**
     * Return the list of tables that should be ignored for writes.
     *
     * @return array The mapping of tables to components
     */
    static protected function get_ignored_tables() {
        require_once(elispm::lib('data/coursetemplate.class.php'));

        return array(coursetemplate::TABLE => 'elis_program',
                     'context' => 'moodle',
                     'message' => 'moodle',
                     'user_preferences' => 'moodle');
    }

    protected function load_csv_data() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(pmclass::TABLE, elis::component_file('program', 'phpunit/pmclass.csv'));
        $dataset->addTable(waitlist::TABLE, elis::component_file('program', 'phpunit/waitlist.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    /**
     * Load in some data for a course and a class at the bare minimum
     */
    protected function load_csv_data_course_class() {
        require_once(elispm::lib('data/course.class.php'));

        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(course::TABLE, elis::component_file('program', 'phpunit/pmcourse.csv'));
        $dataset->addTable(pmclass::TABLE, elis::component_file('program', 'phpunit/pmclass.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    /**
     * Validate that a waitlist record has a particular user associated to a
     * particular class at the top of the wait list, and created and modified times
     * are equal and within the provided range
     *
     * @param int $classid The database record id of the PM class
     * @param int $userid The database record id of the PM user
     * @param int $mintime The minimum time allowed for time created and modified
     * @param int $maxtime The maximum time allowed for time created and modified
     */
    protected function assert_waitlist_record_valid($classid, $userid, $mintime, $maxtime) {
        global $DB;

        //validate that a related database record exists
        $exists = $DB->record_exists(waitlist::TABLE, array('classid' => $classid,
                                                            'userid' => $userid,
                                                            'position' => 1));
        $this->assertTrue($exists);

        //obtain the record
        $record = $DB->get_record(waitlist::TABLE, array('classid' => $classid,
                                                         'userid' => $userid,
                                                         'position' => 1));
        //timestamp validation
        $this->assertGreaterThanOrEqual($mintime, $record->timecreated);
        $this->assertLessThanOrEqual($maxtime, $record->timecreated);
        $this->assertEquals($record->timecreated, $record->timemodified);
    }

    /**
     * Test validation of duplicates
     *
     * Note: no exception thrown from waitlist.class.php for dup.
     */
    public function testWaitlistValidationPreventsDuplicates() {
        global $DB;

        $this->load_csv_data();

        $waitlist = new waitlist(array('classid' => 100,
                                       'userid' => 1,
                                       'position' => 1));

        $waitlist->save();
        $waitlistentries = $DB->get_records(waitlist::TABLE, array('classid' => 100, 'userid' => 1));
        $this->assertEquals(count($waitlistentries), 1);
    }

    /**
     * Validate that the course catalog page saves a waitlist record with
     * correct data
     */
    public function testCourseCatalogPageSavesWaitlistRecord() {
        global $CFG, $_POST, $USER, $DB;
        require_once(elispm::lib('data/user.class.php'));

        //prevent emails from being sent
        set_config('noemailever', true);

        //create our course, class and user
        $this->load_csv_data_course_class();

        $user = new user(array('idnumber' => 'user',
                               'username' => 'user',
                               'firstname' => 'user',
                               'lastname' => 'user',
                               'email' => 'user@user.com',
                               'country' => 'CA'));
        $user->save();

        //fake out the page by assuming the role of the test user
        $USER = $DB->get_record('user', array('username' => 'user'));

        //fake out formslib to convince it that the form was submitted
        $_POST['id'] = 100;
        $_POST['submitbutton'] = 'submitbutton';
        $_POST['_qf__enrolconfirmform'] = '1';
        $_POST['sesskey'] = sesskey();

        //instantiate a version of the course catalogue page that does not
        //display anything to the UI
        $page = new coursecatalogpage_nodisplay();

        //use the page to enrol the test user in the waitlist, recording
        //our time range
        $mintime = time();
        $page->do_savewaitlist();
        $maxtime = time();

        //validate state of the waitlist db record
        $this->assert_waitlist_record_valid(100, $user->id, $mintime, $maxtime);
    }

    /**
     * Validate that the student page saves a waitlist record with correct data
     */
    public function testStudentPageSavesWaitlistRecord() {
        global $CFG, $_POST, $DB, $_GET;
        require_once(elispm::lib('data/user.class.php'));
        require_once(elispm::file('studentpage.class.php'));

        //prevent emails from being sent
        set_config('noemailever', true);

        //create our course, class and user
        $this->load_csv_data_course_class();

        $user = new user(array('idnumber' => 'user',
                               'username' => 'user',
                               'firstname' => 'user',
                               'lastname' => 'user',
                               'email' => 'user@user.com',
                               'country' => 'CA'));
        $user->save();

        //fake out the page
        $_GET['userid'] = $user->id;

        //fake out formslib
        $_POST['userid'] = array($user->id => $user->id);
        $_POST['enrol'] = array($user->id => 1);
        $_POST['classid'] = array($user->id => 100);
        $_POST['enrolmenttime'] = array($user->id => time());
        $_POST['submitbutton'] = 'submitbutton';
        $_POST['_qf__waitlistaddform'] = '1';
        $_POST['sesskey'] = sesskey();

        //use the page to enrol the test user in the waitlist, recording
        //our time range
        $page = new studentpage();
        $mintime = time();
        $page->do_waitlistconfirm(false);
        $maxtime = time();

        //validate state of the waitlist db record
        $this->assert_waitlist_record_valid(100, $user->id, $mintime, $maxtime);
    }

    /**
     * Test the autoenrol after course completion function.
     */
    public function test_check_autoenrol_after_course_completion() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(course::TABLE, elis::component_file('program', 'phpunit/pmcourse.csv'));
        $dataset->addTable(pmclass::TABLE, elis::component_file('program', 'phpunit/pmclass.csv'));
        $dataset->addTable(user::TABLE, elis::component_file('program', 'phpunit/pmuser.csv'));
        $dataset->addTable(student::TABLE, elis::component_file('program', 'phpunit/student.csv'));
        $dataset->addTable(waitlist::TABLE, elis::component_file('program', 'phpunit/waitlist2.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);

        $class = new pmclass(100);
        $class->load();
        $class->maxstudents = 2;
        $class->enrol_from_waitlist = 1;
        $class->save();

        $student = new student(array('userid' => 103, 'classid' => 100));
        $student->completestatusid = STUSTATUS_PASSED;
        $student->save();

        $return = waitlist::check_autoenrol_after_course_completion($student);
        $this->assertTrue($return);
    }
}
