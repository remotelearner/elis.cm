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
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/student.class.php'));
require_once(elispm::lib('data/waitlist.class.php'));
require_once(elispm::file('coursecatalogpage.class.php'));
require_once(elispm::lib('data/course.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));
require_once(elispm::lib('data/coursetemplate.class.php'));
require_once(elispm::file('studentpage.class.php'));

/**
 * Mock course catalog page that doesn't do any displaying of info
 */
class coursecatalogpage_nodisplay extends coursecatalogpage {
    /**
     * Display the page.
     */
    public function display($action = null) {
        // Ignore any displays - only testing back-end.
    }
}

/**
 * Test waitlist functions.
 * @group elis_program
 */
class waitlist_testcase extends elis_database_test {

    /**
     * Load initial data from CSVs.
     */
    protected function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            pmclass::TABLE => elis::component_file('program', 'tests/fixtures/pmclass.csv'),
            waitlist::TABLE => elis::component_file('program', 'tests/fixtures/waitlist.csv'),
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * Load in some data for a course and a class at the bare minimum
     */
    protected function load_csv_data_course_class() {
        $dataset = $this->createCsvDataSet(array(
            course::TABLE => elis::component_file('program', 'tests/fixtures/pmcourse.csv'),
            pmclass::TABLE => elis::component_file('program', 'tests/fixtures/pmclass.csv'),
        ));
        $this->loadDataSet($dataset);
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

        // Validate that a related database record exists.
        $exists = $DB->record_exists(waitlist::TABLE, array('classid' => $classid, 'userid' => $userid, 'position' => 1));
        $this->assertTrue($exists);

        // Obtain the record.
        $record = $DB->get_record(waitlist::TABLE, array('classid' => $classid, 'userid' => $userid, 'position' => 1));

        // Timestamp validation.
        $this->assertGreaterThanOrEqual($mintime, $record->timecreated);
        $this->assertLessThanOrEqual($maxtime, $record->timecreated);
        $this->assertEquals($record->timecreated, $record->timemodified);
    }

    /**
     * Test validation of duplicates
     *
     * Note: no exception thrown from waitlist.class.php for dup.
     */
    public function test_waitlistvalidationpreventsduplicates() {
        global $DB;
        $this->load_csv_data();

        $waitlist = new waitlist(array('classid' => 100, 'userid' => 1, 'position' => 1));

        $waitlist->save();
        $waitlistentries = $DB->get_records(waitlist::TABLE, array('classid' => 100, 'userid' => 1));
        $this->assertEquals(count($waitlistentries), 1);
    }

    /**
     * Validate that the course catalog page saves a waitlist record with
     * correct data
     */
    public function test_coursecatalogpagesaveswaitlistrecord() {
        global $CFG, $_POST, $USER, $DB;

        // Prevent emails from being sent.
        set_config('noemailever', true);

        // Create our course, class and user.
        $this->load_csv_data_course_class();

        $user = new user(array(
            'idnumber' => 'user',
            'username' => 'user',
            'firstname' => 'user',
            'lastname' => 'user',
            'email' => 'user@user.com',
            'country' => 'CA'
        ));
        $user->save();

        // Fake out the page by assuming the role of the test user.
        $USER = $DB->get_record('user', array('username' => 'user'));

        // Fake out formslib to convince it that the form was submitted.
        $_POST['id'] = 100;
        $_POST['submitbutton'] = 'submitbutton';
        $_POST['_qf__enrolconfirmform'] = '1';
        $_POST['sesskey'] = sesskey();

        // Instantiate a version of the course catalogue page that does not display anything to the UI.
        $page = new coursecatalogpage_nodisplay();

        // Use the page to enrol the test user in the waitlist, recording our time range.
        $mintime = time();
        $sink = $this->redirectMessages();
        $page->do_savewaitlist();
        $maxtime = time();

        // Validate state of the waitlist db record.
        $this->assert_waitlist_record_valid(100, $user->id, $mintime, $maxtime);
    }

    /**
     * Test the autoenrol after course completion function.
     */
    public function test_check_autoenrol_after_course_completion() {
        $dataset = $this->createCsvDataSet(array(
            course::TABLE => elis::component_file('program', 'tests/fixtures/pmcourse.csv'),
            pmclass::TABLE => elis::component_file('program', 'tests/fixtures/pmclass.csv'),
            user::TABLE => elis::component_file('program', 'tests/fixtures/pmuser.csv'),
            student::TABLE => elis::component_file('program', 'tests/fixtures/student.csv'),
            waitlist::TABLE => elis::component_file('program', 'tests/fixtures/waitlist2.csv'),
        ));
        $this->loadDataSet($dataset);

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