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
require_once(elis::lib('data/customfield.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/userset.class.php'));
require_once(elispm::lib('data/track.class.php'));

/**
 * Test curriculum custom fields.
 * @group elis_program
 */
class fakecontexthierarchy_testcase extends elis_database_test {

    /**
     * @var int The id of a test program.
     */
    protected $tprogramid;

    /**
     * @var int The id of a test track.
     */
    protected $ttrackid;

    /**
     * @var int The id of a test course.
     */
    protected $tcourseid;

    /**
     * @var int The id of a test class.
     */
    protected $tclassid;

    /**
     * @var int The id of a test elis user.
     */
    protected $tuserid;

    /**
     * @var int The id of a test userset.
     */
    protected $tusersetid;

    /**
     * @var int The id of a test moodle user.
     */
    protected $mdluserid;

    /**
     * Do setup before each test.
     */
    protected function setUp() {
        global $DB;

        parent::setUp();

        // Ensure that the editing teacher role has a specific capapbility enabled.
        $role = $DB->get_record('role', array('shortname' => 'editingteacher'));

        accesslib_clear_all_caches(true);

        $syscontext = context_system::instance();
        assign_capability('elis/program:userset_enrol_userset_user', CAP_ALLOW, $role->id, $syscontext, true);
        $syscontext->mark_dirty();

        // Initialise testing data.
        $this->initprogram();
        $this->inittrack($this->tprogramid);
        $this->initcourse();
        $this->initclass($this->tcourseid);
        $this->inituser();
        $this->inituserset();
    }

    /**
     * Initialize a new program object
     */
    protected function initprogram() {
        $data = array(
            'idnumber' => '__fcH__TESTID001__',
            'name'     => 'Test Program 1'
        );

        $newprogram = new curriculum($data);
        $newprogram->save();
        $this->tprogramid = $newprogram->id;
    }

    /**
     * Initialize a new track object
     * @param int $curid A curriculum record ID
     */
    protected function inittrack($curid) {
        $data = array(
            'curid'    => $curid,
            'idnumber' => '__fcH__TESTID001__',
            'name'     => 'Test Track 1'
        );

        $newtrack = new track($data);
        $newtrack->save();
        $this->ttrackid = $newtrack->id;
    }

    /**
     * Initialize a new course description object
     */
    protected function initcourse() {
        $data = array(
            'idnumber' => '__fcH__TESTID001__',
            'name'     => 'Test Course 1',
            'syllabus' => ''  // For some reason this field needs to be defined, or INSERT fails?!
        );

        $newcourse = new course($data);
        $newcourse->save();
        $this->tcourseid = $newcourse->id;
    }

    /**
     * Initialize a new class object
     * @param int $courseid A course record ID
     */
    protected function initclass($courseid) {
        $data = array(
            'idnumber' => '__fcH__TESTID001__',
            'courseid' => $courseid
        );

        $newclass = new pmclass($data);
        $newclass->save();
        $this->tclassid = $newclass->id;
    }

    /**
     * Initialize a new user description object
     */
    protected function inituser() {
        global $CFG, $DB, $USER;

        $data = array(
            'idnumber'  => '__fcH__TESTID001__',
            'username'  => '__fcH__testuser1__',
            'firstname' => 'Test',
            'lastname'  => 'User1',
            'email'     => 'testuser1@example.com',
            'country'   => 'us'
        );

        $newuser = new user($data);
        $newuser->save();
        $this->tuserid = $newuser->id;

        $usernew = new stdClass;
        $usernew->username    = '__fcH__testuser__';
        $usernew->idnumber    = '__fcH__testuser__';
        $usernew->firstname   = 'Test';
        $usernew->lastname    = 'User';
        $usernew->email       = 'testuser@example.com';
        $usernew->confirmed   = 1;
        $usernew->auth        = 'manual';
        $usernew->mnethostid  = $CFG->mnet_localhost_id;
        $usernew->confirmed   = 1;
        $usernew->timecreated = time();
        $usernew->password    = hash_internal_user_password('testpassword');

        $this->mdluserid = $DB->insert_record('user', $usernew);

        // Setup the global user to be this new test user we have created.
        $USER = $DB->get_record('user', array('id' => $this->mdluserid));
        $USER->access = get_user_accessdata($USER->id);
    }

    /**
     * Initialize a new user description object
     */
    protected function inituserset() {
        $data = array(
            'name' => 'Test User Set 1',
            'display' => 'We\'re just testing user set creation!'
        );

        $newuserset = new userset($data);
        $newuserset->save();
        $this->tusersetid = $newuserset->id;
    }

    /**
     * Test program capability check.
     */
    public function test_programcapabilitycheck() {
        global $DB, $USER;

        $role = $DB->get_record('role', array('shortname' => 'editingteacher'));

        // Assign the test user the editing teacher role on a test program.
        $ctx = context_elis_program::instance($this->tprogramid);
        $this->assertGreaterThan(0, role_assign($role->id, $this->mdluserid, $ctx->id));
        load_role_access_by_context($role->id, $ctx, $USER->access); // We need to force the accesslib cache to refresh.

        // Validate the return value when looking at the 'curriculum' level.
        $contextscurriculum = new pm_context_set();
        $contextscurriculum->contexts = array(
            'curriculum' => array($this->tprogramid)
        );
        $contextscurriculum->contextlevel = 'curriculum';

        $perm = 'elis/program:userset_enrol_userset_user';
        $contexts = pm_context_set::for_user_with_capability('curriculum', $perm, $this->mdluserid, false);
        $this->assertEquals($contextscurriculum, $contexts);

        // Validate the return value when looking at the 'track' level.
        $contextstrack = new pm_context_set();
        $contextstrack->contexts = array(
            'curriculum' => array($this->tprogramid)
        );
        $contextstrack->contextlevel = 'track';

        $contexts = pm_context_set::for_user_with_capability('track', 'elis/program:userset_enrol_userset_user', $this->mdluserid);
        $this->assertEquals($contextstrack, $contexts);

        // Validate the return value when looking at the 'course' level.
        $contextscourse = new pm_context_set();
        $contextscourse->contexts = array(
            'curriculum' => array($this->tprogramid)
        );
        $contextscourse->contextlevel = 'course';

        $contexts = pm_context_set::for_user_with_capability('course', 'elis/program:userset_enrol_userset_user', $this->mdluserid);
        $this->assertEquals($contextscourse, $contexts);

        // Validate the return value when looking at the 'class' level.
        $contextsclass = new pm_context_set();
        $contextsclass->contexts = array(
            'curriculum' => array($this->tprogramid)
        );
        $contextsclass->contextlevel = 'class';

        $contexts = pm_context_set::for_user_with_capability('class', 'elis/program:userset_enrol_userset_user', $this->mdluserid);
        $this->assertEquals($contextsclass, $contexts);

        // Validate checking for users with the given capability on this context.
        $users = pm_get_users_by_capability('curriculum', $this->tprogramid, 'elis/program:userset_enrol_userset_user');
        $this->assertEquals($this->mdluserid, current($users)->id);
    }

    /**
     * Test track capability check.
     */
    public function test_trackcapabilitycheck() {
        global $DB, $USER;

        $role = $DB->get_record('role', array('shortname' => 'editingteacher'));

        // Assign the test user the editing teacher role on a test track.
        $ctx = context_elis_track::instance($this->ttrackid);
        $this->assertNotEmpty(role_assign($role->id, $this->mdluserid, $ctx->id));
        load_role_access_by_context($role->id, $ctx, $USER->access); // We need to force the accesslib cache to refresh.

        // Validate the return value when looking at the 'track' level.
        $contextstrack = new pm_context_set();
        $contextstrack->contexts = array(
            'track' => array($this->ttrackid)
        );
        $contextstrack->contextlevel = 'track';

        $contexts = pm_context_set::for_user_with_capability('track', 'elis/program:userset_enrol_userset_user', $this->mdluserid);
        $this->assertEquals($contextstrack, $contexts);

        // Validate the return value when looking at the 'class' level.
        $contextsclass = new pm_context_set();
        $contextsclass->contexts = array(
            'track' => array($this->ttrackid)
        );
        $contextsclass->contextlevel = 'class';

        $perm = 'elis/program:userset_enrol_userset_user';
        $contexts = pm_context_set::for_user_with_capability('class', $perm, $this->mdluserid);
        $this->assertEquals($contextsclass, $contexts);

        // Validate checking for users with the given capability on this context.
        $users = pm_get_users_by_capability('track', $this->ttrackid, 'elis/program:userset_enrol_userset_user');
        $this->assertEquals($this->mdluserid, current($users)->id);
    }

    /**
     * Test course capability check.
     */
    public function test_coursecapabilitycheck() {
        global $DB, $USER;

        $role = $DB->get_record('role', array('shortname' => 'editingteacher'));

        // Assign the test user the editing teacher role on a test curriculum.
        $ctx = context_elis_course::instance($this->tcourseid);
        $this->assertNotEmpty(role_assign($role->id, $this->mdluserid, $ctx->id));
        load_role_access_by_context($role->id, $ctx, $USER->access); // We need to force the accesslib cache to refresh.

        // Validate the return value when looking at the 'course' level.
        $contextscourse = new pm_context_set();
        $contextscourse->contexts = array(
            'course' => array($this->tcourseid)
        );
        $contextscourse->contextlevel = 'course';

        $contexts = pm_context_set::for_user_with_capability('course', 'elis/program:userset_enrol_userset_user', $this->mdluserid);
        $this->assertEquals($contextscourse, $contexts);

        // Validate the return value when looking at the 'class' level.
        $contextsclass = new pm_context_set();
        $contextsclass->contexts = array(
            'course' => array($this->tcourseid)
        );
        $contextsclass->contextlevel = 'class';

        $contexts = pm_context_set::for_user_with_capability('class', 'elis/program:userset_enrol_userset_user', $this->mdluserid);
        $this->assertEquals($contextsclass, $contexts);

        // Validate checking for users with the given capability on this context.
        $users = pm_get_users_by_capability('course', $this->tcourseid, 'elis/program:userset_enrol_userset_user');
        $this->assertEquals($this->mdluserid, current($users)->id);
    }

    /**
     * Test class capability check.
     */
    public function test_classcapabilitycheck() {
        global $DB, $USER;

        $role = $DB->get_record('role', array('shortname' => 'editingteacher'));

        // Assign the test user the editing teacher role on a test curriculum.
        $ctx = context_elis_class::instance($this->tclassid);
        $this->assertNotEmpty(role_assign($role->id, $this->mdluserid, $ctx->id));
        load_role_access_by_context($role->id, $ctx, $USER->access); // We need to force the accesslib cache to refresh.

        // Validate the return value when looking at the 'class' level.
        $contextsclass = new pm_context_set();
        $contextsclass->contexts = array(
            'class' => array($this->tclassid)
        );
        $contextsclass->contextlevel = 'class';

        $contexts = pm_context_set::for_user_with_capability('class', 'elis/program:userset_enrol_userset_user', $this->mdluserid);
        $this->assertEquals($contextsclass, $contexts);

        // Validate checking for users with the given capability on this context.
        $users = pm_get_users_by_capability('class', $this->tclassid, 'elis/program:userset_enrol_userset_user');
        $this->assertEquals($this->mdluserid, current($users)->id);
    }

    /**
     * Test userset capability check.
     */
    public function test_usersetcapabilitycheck() {
        global $DB, $USER;

        $role = $DB->get_record('role', array('shortname' => 'editingteacher'));

        // Assign the test user the editing teacher role on a test cluster.
        $ctx = context_elis_userset::instance($this->tusersetid);
        $this->assertNotEmpty(role_assign($role->id, $this->mdluserid, $ctx->id));
        load_role_access_by_context($role->id, $ctx, $USER->access); // We need to force the accesslib cache to refresh.

         // Validate the return value when looking at the 'cluster' level.
        $contextscluster = new pm_context_set();
        $contextscluster->contexts = array(
            'cluster' => array($this->tusersetid)
        );
        $contextscluster->contextlevel = 'cluster';

        $perm = 'elis/program:userset_enrol_userset_user';
        $contexts = pm_context_set::for_user_with_capability('cluster', $perm, $this->mdluserid);
        $this->assertEquals($contextscluster, $contexts);

         // Validate the return value when looking at the 'user' level.
        $contextsuser = new pm_context_set();
        $contextsuser->contexts = array(
            'cluster' => array($this->tusersetid)
        );
        $contextsuser->contextlevel = 'user';

        $contexts = pm_context_set::for_user_with_capability('user', 'elis/program:userset_enrol_userset_user', $this->mdluserid);
        $this->assertEquals($contextsuser, $contexts);

        // Validate checking for users with the given capability on this context.
        $users = pm_get_users_by_capability('cluster', $this->tusersetid, 'elis/program:userset_enrol_userset_user');
        $this->assertEquals($this->mdluserid, current($users)->id);
    }

    /**
     * Test user capability check.
     */
    public function test_usercapabilitycheck() {
        global $DB, $USER;

        $role = $DB->get_record('role', array('shortname' => 'editingteacher'));

        // Assign the test user the editing teacher role on a test cluster.
        $ctx = context_elis_user::instance($this->tuserid);
        $this->assertNotEmpty(role_assign($role->id, $this->mdluserid, $ctx->id));
        load_role_access_by_context($role->id, $ctx, $USER->access); // We need to force the accesslib cache to refresh.

         // Validate the return value when looking at the 'user' level.
        $contextsuser = new pm_context_set();
        $contextsuser->contexts = array(
            'user' => array($this->tuserid)
        );
        $contextsuser->contextlevel = 'user';

        $contexts = pm_context_set::for_user_with_capability('user', 'elis/program:userset_enrol_userset_user', $this->mdluserid);
        $this->assertEquals($contextsuser, $contexts);

        // Validate checking for users with the given capability on this context.
        $users = pm_get_users_by_capability('user', $this->tuserid, 'elis/program:userset_enrol_userset_user');
        $this->assertEquals($this->mdluserid, current($users)->id);
    }
}