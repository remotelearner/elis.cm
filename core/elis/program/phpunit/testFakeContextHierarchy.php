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
require_once(elis::lib('data/customfield.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/userset.class.php'));
require_once(elispm::lib('data/track.class.php'));


class curriculumCustomFieldsTest extends elis_database_test {
    protected $backupGlobalsBlacklist = array('ACCESSLIB_PRIVATE', 'USER');

    private $tprogramid;
    private $ttrackid;
    private $tcourseid;
    private $classid;
    private $tuserid;
    private $tusersetid;
    private $mdluserid;

    protected static function get_overlay_tables() {
        return array(
            'cache_flags' => 'moodle',
            'context' => 'moodle',
            'context_temp' => 'moodle',
            'course' => 'moodle',
            'message' => 'moodle',
            'message_read' => 'moodle',
            'role_assignments' => 'moodle',
            'role_capabilities' => 'moodle',
            'user' => 'moodle',
            field::TABLE => 'elis_core',
            curriculum::TABLE => 'elis_program',
            track::TABLE => 'elis_program',
            course::TABLE => 'elis_program',
            coursetemplate::TABLE => 'elis_program',
            pmclass::TABLE => 'elis_program',
            userset::TABLE => 'elis_program',
            clusterassignment::TABLE => 'elis_program',
            'crlm_cluster_profile' => 'elis_program',
            user::TABLE => 'elis_program',
            usermoodle::TABLE => 'elis_program',
        );
    }

    protected function setUp() {
        global $DB, $ACCESSLIB_PRIVATE;

        parent::setUp();
        $DB = self::$origdb; // setUpContextsTable needs $DB to be the real
        // database for get_admin()
        $this->setUpContextsTable();
        $DB = self::$overlaydb;

        // Ensure that the editing teacher role has a specific capapbility enabled
        $role = $DB->get_record('role', array('shortname' => 'editingteacher'));

        accesslib_clear_all_caches(true);

        $syscontext = context_system::instance();
        assign_capability('elis/program:userset_enrol_userset_user', CAP_ALLOW, $role->id, $syscontext, true);
        $syscontext->mark_dirty();

        // Initialise testing data
        $this->initProgram();
        $this->initTrack($this->tprogramid);
        $this->initCourse();
        $this->initClass($this->tcourseid);
        $this->initUser();
        $this->initUserset();
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

        // Guest user
        if ($guest = self::$origdb->get_record('user', array('username' => 'guest', 'mnethostid' => $CFG->mnet_localhost_id))) {
            self::$overlaydb->import_record('user', $guest);
            $usercontext = self::$origdb->get_record('context', array('contextlevel' => CONTEXT_USER, 'instanceid' => $guest->id));
            self::$overlaydb->import_record('context', $usercontext);
        }

        // primary admin user
        $admin = get_admin();
        if ($admin) {
            self::$overlaydb->import_record('user', $admin);
            $CFG->siteadmins = $admin->id;
            $usercontext = self::$origdb->get_record('context', array('contextlevel' => CONTEXT_USER, 'instanceid' => $admin->id));
            self::$overlaydb->import_record('context', $usercontext);

            // copy admin user's ELIS user (if available)
            $elisuser = user::find(new field_filter('idnumber', $admin->idnumber), array(), 0, 0, self::$origdb);
            if ($elisuser->valid()) {
                $elisuser = $elisuser->current();
                self::$overlaydb->import_record(user::TABLE, $elisuser->to_object());
            }
        }
    }

    /**
     * Initialize a new program object
     */
    private function initProgram() {
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
     *
     * @param integer $curid A curriculum record ID
     */
    private function initTrack($curid) {
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
    private function initCourse() {
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
     *
     * @param integer $courseid A course record ID
     */
    private function initClass($courseid) {
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
    private function initUser() {
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

        // Setup the global user to be this new test user we have created
        $USER = $DB->get_record('user', array('id' => $this->mdluserid));
        $USER->access = get_user_accessdata($USER->id);
    }

    /**
     * Initialize a new user description object
     */
    private function initUserset() {
        $data = array(
            'name'    => 'Test User Set 1',
            'display' => 'We\'re just testing user set creation!'
        );

        $newuserset = new userset($data);
        $newuserset->save();
        $this->tusersetid = $newuserset->id;
    }

    public function testProgramCapabilityCheck() {
        global $DB, $USER;

        $role = $DB->get_record('role', array('shortname' => 'editingteacher'));

        // Assign the test user the editing teacher role on a test program
        $ctx = context_elis_program::instance($this->tprogramid);
        $this->assertGreaterThan(0, role_assign($role->id, $this->mdluserid, $ctx->id));
        load_role_access_by_context($role->id, $ctx, $USER->access); // We need to force the accesslib cache to refresh

        // Validate the return value when looking at the 'curriculum' level
        $contexts_curriculum = new pm_context_set();
        $contexts_curriculum->contexts = array(
            'curriculum' => array($this->tprogramid)
        );
        $contexts_curriculum->contextlevel = 'curriculum';

        $contexts = pm_context_set::for_user_with_capability('curriculum', 'elis/program:userset_enrol_userset_user', $this->mdluserid, false);
        $this->assertEquals($contexts_curriculum, $contexts);

        // Validate the return value when looking at the 'track' level
        $contexts_track = new pm_context_set();
        $contexts_track->contexts = array(
            'curriculum' => array($this->tprogramid)
        );
        $contexts_track->contextlevel = 'track';

        $contexts = pm_context_set::for_user_with_capability('track', 'elis/program:userset_enrol_userset_user', $this->mdluserid);
        $this->assertEquals($contexts_track, $contexts);

        // Validate the return value when looking at the 'course' level
        $contexts_course = new pm_context_set();
        $contexts_course->contexts = array(
            'curriculum' => array($this->tprogramid)
        );
        $contexts_course->contextlevel = 'course';

        $contexts = pm_context_set::for_user_with_capability('course', 'elis/program:userset_enrol_userset_user', $this->mdluserid);
        $this->assertEquals($contexts_course, $contexts);

        // Validate the return value when looking at the 'class' level
        $contexts_class = new pm_context_set();
        $contexts_class->contexts = array(
            'curriculum' => array($this->tprogramid)
        );
        $contexts_class->contextlevel = 'class';

        $contexts   = pm_context_set::for_user_with_capability('class', 'elis/program:userset_enrol_userset_user', $this->mdluserid);
        $this->assertEquals($contexts_class, $contexts);

        // Validate checking for users with the given capability on this context
        $users = pm_get_users_by_capability('curriculum', $this->tprogramid, 'elis/program:userset_enrol_userset_user');
        $this->assertEquals($this->mdluserid, current($users)->id);
    }

    public function testTrackCapabilityCheck() {
        global $DB, $USER;

        $role = $DB->get_record('role', array('shortname' => 'editingteacher'));

        // Assign the test user the editing teacher role on a test track
        $ctx = context_elis_track::instance($this->ttrackid);
        $this->assertNotEmpty(role_assign($role->id, $this->mdluserid, $ctx->id));
        load_role_access_by_context($role->id, $ctx, $USER->access); // We need to force the accesslib cache to refresh

        // Validate the return value when looking at the 'track' level
        $contexts_track = new pm_context_set();
        $contexts_track->contexts = array(
            'track' => array($this->ttrackid)
        );
        $contexts_track->contextlevel = 'track';

        $contexts = pm_context_set::for_user_with_capability('track', 'elis/program:userset_enrol_userset_user', $this->mdluserid);
        $this->assertEquals($contexts_track, $contexts);

        // Validate the return value when looking at the 'class' level
        $contexts_class = new pm_context_set();
        $contexts_class->contexts = array(
            'track' => array($this->ttrackid)
        );
        $contexts_class->contextlevel = 'class';

        $contexts   = pm_context_set::for_user_with_capability('class', 'elis/program:userset_enrol_userset_user', $this->mdluserid);
        $this->assertEquals($contexts_class, $contexts);

        // Validate checking for users with the given capability on this context
        $users = pm_get_users_by_capability('track', $this->ttrackid, 'elis/program:userset_enrol_userset_user');
        $this->assertEquals($this->mdluserid, current($users)->id);
    }

    public function testCourseCapabilityCheck() {
        global $DB, $USER;

        $role = $DB->get_record('role', array('shortname' => 'editingteacher'));

        // Assign the test user the editing teacher role on a test curriculum
        $ctx = context_elis_course::instance($this->tcourseid);
        $this->assertNotEmpty(role_assign($role->id, $this->mdluserid, $ctx->id));
        load_role_access_by_context($role->id, $ctx, $USER->access); // We need to force the accesslib cache to refresh

        // Validate the return value when looking at the 'course' level
        $contexts_course = new pm_context_set();
        $contexts_course->contexts = array(
            'course' => array($this->tcourseid)
        );
        $contexts_course->contextlevel = 'course';

        $contexts = pm_context_set::for_user_with_capability('course', 'elis/program:userset_enrol_userset_user', $this->mdluserid);
        $this->assertEquals($contexts_course, $contexts);

        // Validate the return value when looking at the 'class' level
        $contexts_class = new pm_context_set();
        $contexts_class->contexts = array(
            'course' => array($this->tcourseid)
        );
        $contexts_class->contextlevel = 'class';

        $contexts = pm_context_set::for_user_with_capability('class', 'elis/program:userset_enrol_userset_user', $this->mdluserid);
        $this->assertEquals($contexts_class, $contexts);

        // Validate checking for users with the given capability on this context
        $users = pm_get_users_by_capability('course', $this->tcourseid, 'elis/program:userset_enrol_userset_user');
        $this->assertEquals($this->mdluserid, current($users)->id);
    }

    public function testClassCapabilityCheck() {
        global $DB, $USER;

        $role = $DB->get_record('role', array('shortname' => 'editingteacher'));

        // Assign the test user the editing teacher role on a test curriculum
        $ctx = context_elis_class::instance($this->tclassid);
        $this->assertNotEmpty(role_assign($role->id, $this->mdluserid, $ctx->id));
        load_role_access_by_context($role->id, $ctx, $USER->access); // We need to force the accesslib cache to refresh

        // Validate the return value when looking at the 'class' level
        $contexts_class = new pm_context_set();
        $contexts_class->contexts = array(
            'class' => array($this->tclassid)
        );
        $contexts_class->contextlevel = 'class';

        $contexts = pm_context_set::for_user_with_capability('class', 'elis/program:userset_enrol_userset_user', $this->mdluserid);
        $this->assertEquals($contexts_class, $contexts);

        // Validate checking for users with the given capability on this context
        $users = pm_get_users_by_capability('class', $this->tclassid, 'elis/program:userset_enrol_userset_user');
        $this->assertEquals($this->mdluserid, current($users)->id);
    }

    public function testUsersetCapabilityCheck() {
        global $DB, $USER;

        $role = $DB->get_record('role', array('shortname' => 'editingteacher'));

        // Assign the test user the editing teacher role on a test cluster
        $ctx = context_elis_userset::instance($this->tusersetid);
        $this->assertNotEmpty(role_assign($role->id, $this->mdluserid, $ctx->id));
        load_role_access_by_context($role->id, $ctx, $USER->access); // We need to force the accesslib cache to refresh

         // Validate the return value when looking at the 'cluster' level
        $contexts_cluster = new pm_context_set();
        $contexts_cluster->contexts = array(
            'cluster' => array($this->tusersetid)
        );
        $contexts_cluster->contextlevel = 'cluster';

        $contexts = pm_context_set::for_user_with_capability('cluster', 'elis/program:userset_enrol_userset_user', $this->mdluserid);
        $this->assertEquals($contexts_cluster, $contexts);

         // Validate the return value when looking at the 'user' level
        $contexts_user = new pm_context_set();
        $contexts_user->contexts = array(
            'cluster' => array($this->tusersetid)
        );
        $contexts_user->contextlevel = 'user';

        $contexts = pm_context_set::for_user_with_capability('user', 'elis/program:userset_enrol_userset_user', $this->mdluserid);
        $this->assertEquals($contexts_user, $contexts);

        // Validate checking for users with the given capability on this context
        $users = pm_get_users_by_capability('cluster', $this->tusersetid, 'elis/program:userset_enrol_userset_user');
        $this->assertEquals($this->mdluserid, current($users)->id);
    }

    public function testUserCapabilityCheck() {
        global $DB, $USER;

        $role = $DB->get_record('role', array('shortname' => 'editingteacher'));

        // Assign the test user the editing teacher role on a test cluster
        $ctx = context_elis_user::instance($this->tuserid);
        $this->assertNotEmpty(role_assign($role->id, $this->mdluserid, $ctx->id));
        load_role_access_by_context($role->id, $ctx, $USER->access); // We need to force the accesslib cache to refresh

         // Validate the return value when looking at the 'user' level
        $contexts_user = new pm_context_set();
        $contexts_user->contexts = array(
            'user' => array($this->tuserid)
        );
        $contexts_user->contextlevel = 'user';

        $contexts = pm_context_set::for_user_with_capability('user', 'elis/program:userset_enrol_userset_user', $this->mdluserid);
        $this->assertEquals($contexts_user, $contexts);

        // Validate checking for users with the given capability on this context
        $users = pm_get_users_by_capability('user', $this->tuserid, 'elis/program:userset_enrol_userset_user');
        $this->assertEquals($this->mdluserid, current($users)->id);
    }
}
