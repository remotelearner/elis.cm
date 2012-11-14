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
require_once(elispm::lib('data/curriculum.class.php'));
require_once(elis::lib('data/customfield.class.php'));
require_once(elis::file('core/fields/moodle_profile/custom_fields.php'));
require_once(elispm::lib('data/usermoodle.class.php'));
require_once('PHPUnit/Extensions/Database/DataSet/CsvDataSet.php');
require_once($CFG->dirroot.'/admin/roles/lib.php');

class curriculumCustomFieldsTest extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

    protected static function get_overlay_tables() {
		return array(
            'cache_flags' => 'moodle',
            'context' => 'moodle',
            'context_temp' => 'moodle',
            'course' => 'moodle',
            'events_queue' => 'moodle',
            'events_queue_handlers' => 'moodle',
            'message' => 'moodle',
            'message_working' => 'moodle',
            'role' => 'moodle',
            'role_context_levels' => 'moodle',
            'role_assignments' => 'moodle',
            'role_capabilities' => 'moodle',
            'user' => 'moodle',
            'user_info_category' => 'moodle',
            'user_info_field' => 'moodle',
            'user_info_data' => 'moodle',
		    'user_preferences' => 'moodle',
            field_category::TABLE => 'elis_core',
            field_category_contextlevel::TABLE => 'elis_core',
            field::TABLE => 'elis_core',
            field_contextlevel::TABLE => 'elis_core',
            field_data_text::TABLE => 'elis_core',
            curriculum::TABLE => 'elis_program',
            track::TABLE => 'elis_program',
            course::TABLE => 'elis_program',
            coursetemplate::TABLE => 'elis_program',
            pmclass::TABLE => 'elis_program',
            user::TABLE => 'elis_program',
            usermoodle::TABLE => 'elis_program',
            field_owner::TABLE => 'elis_core',
            userset::TABLE => 'elis_program'
        );
    }

    protected function setUp() {
        global $DB;

        parent::setUp();

        $this->setUpRolesTables();
        $this->load_csv_data();

        $DB = self::$origdb; // setUpContextsTable needs $DB to be the real
        // database for get_admin()
        $this->setUpContextsTable();
        $DB = self::$overlaydb;
    }

    /**
     * Set up the contexts table with the minimum that we need.
     */
    private function setUpContextsTable() {
        global $CFG;

        $syscontext = self::$origdb->get_record('context', array('contextlevel' => CONTEXT_SYSTEM));
        self::$overlaydb->import_record('context', $syscontext);

        $site = self::$origdb->get_record('course', array('id' => SITEID));
        self::$overlaydb->import_record('course', $site);


        $sitecontext = self::$origdb->get_record('context', array('contextlevel' => CONTEXT_COURSE,
                                                                  'instanceid' => SITEID));
        self::$overlaydb->import_record('context', $sitecontext);

        // Guest user
        if ($guest = self::$origdb->get_record('user', array('username' => 'guest', 'mnethostid' => $CFG->mnet_localhost_id))) {
            self::$overlaydb->import_record('user', $guest);
        }

        // Primary admin user
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

    private function setUpRolesTables() {
        $roles = self::$origdb->get_records('role');
        foreach ($roles as $rolerec) {
            self::$overlaydb->import_record('role', $rolerec);
        }

        $roles_ctxs = self::$origdb->get_records('role_context_levels');
        foreach ($roles_ctxs as $role_ctx) {
            self::$overlaydb->import_record('role_context_levels', $role_ctx);
        }
    }

    protected function load_csv_data() {

        // load initial data from a CSV file
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable('user', elis::component_file('program', 'phpunit/mdluser.csv'));
        $dataset->addTable('user_info_category', elis::component_file('program', 'phpunit/user_info_category.csv'));
        $dataset->addTable('user_info_field', elis::component_file('program', 'phpunit/user_info_field.csv'));
        $dataset->addTable('user_info_data', elis::component_file('program', 'phpunit/user_info_data.csv'));
        $dataset->addTable(user::TABLE, elis::component_file('program', 'phpunit/pmuser.csv'));
        $dataset->addTable(usermoodle::TABLE, elis::component_file('program', 'phpunit/usermoodle.csv'));
        $dataset->addTable(field_category::TABLE, elis::component_file('program', 'phpunit/user_field_category.csv'));
        $dataset->addTable(field::TABLE, elis::component_file('program', 'phpunit/user_field.csv'));
        $dataset->addTable(field_owner::TABLE, elis::component_file('program', 'phpunit/user_field_owner.csv'));
        $dataset = new PHPUnit_Extensions_Database_DataSet_ReplacementDataSet($dataset);
        $dataset->addSubStrReplacement('\n', "\n");
        load_phpunit_data_set($dataset, true, self::$overlaydb);

        //load curriculum data
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(curriculum::TABLE, elis::component_file('program', 'phpunit/curriculum.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);

        //load track data
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(track::TABLE, elis::component_file('program', 'phpunit/track.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);

        //load course data
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(course::TABLE, elis::component_file('program', 'phpunit/pmcourse.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);

        //load class data
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(pmclass::TABLE, elis::component_file('program', 'phpunit/pmclass.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);

        //load userset data
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(userset::TABLE, elis::component_file('program', 'phpunit/userset.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    /**
     * ELIS-4745: Test for assigning a user a role on a program context
     */
    public function testAssignUserforProgramCTX() {
        global $DB;

        //get role to assign (we'll just take the first one returned)
        $roles_ctx = $DB->get_records('role_context_levels',array('contextlevel' => CONTEXT_ELIS_PROGRAM));
        foreach ($roles_ctx as $role_ctx) {
            $roleid = $role_ctx->roleid;
            break;
        }

        //get user to assign role
        $user  = new user(103);
        $muser = $user->get_moodleuser();

        //get specific context
        $cur = new curriculum(1);
        $context = context_elis_program::instance($cur->id);

        //assign role
        $this->assertGreaterThan(0, role_assign($roleid, $muser->id, $context->id));
    }

    /**
     * ELIS-4746: Test for assigning a user a role on a track context
     */
    public function testAssignUserforTrackCTX() {
        global $DB;

        //get role to assign (we'll just take the first one returned)
        $roles_ctx = $DB->get_records('role_context_levels',array('contextlevel' => CONTEXT_ELIS_TRACK));
        foreach ($roles_ctx as $role_ctx) {
            $roleid = $role_ctx->roleid;
            break;
        }

        //get user to assign role
        $user  = new user(103);
        $muser = $user->get_moodleuser();

        //get specific context
        $trk = new track(1);
        $context = context_elis_track::instance($trk->id);

        //assign role
        $this->assertGreaterThan(0, role_assign($roleid, $muser->id, $context->id));
    }

    /**
     * ELIS-4747: Test for assigning a user a role on a course context
     */
    public function testAssignUserforCourseCTX() {
        //get role to assign (we'll just take the first one returned)
        $roles_ctx = self::$overlaydb->get_records('role_context_levels',array('contextlevel' => CONTEXT_ELIS_COURSE));
        foreach ($roles_ctx as $role_ctx) {
            $roleid = $role_ctx->roleid;
            break;
        }

        //get user to assign role
        $user = new user(103);
        $muser = $user->get_moodleuser();

        //get specific context
        $crs = new course(100);
        $context = context_elis_course::instance($crs->id);

        //assign role
        $this->assertGreaterThan(0, role_assign($roleid, $muser->id, $context->id));
    }

    /**
     * ELIS-4748: Test for assigning a user a role on a course context
     */
    public function testAssignUserforClassCTX() {
        global $DB;

        //get role to assign (we'll just take the first one returned)
        $roles_ctx = $DB->get_records('role_context_levels',array('contextlevel' => CONTEXT_ELIS_CLASS));
        foreach ($roles_ctx as $role_ctx) {
            $roleid = $role_ctx->roleid;
            break;
        }

        //get user to assign role
        $user = new user(103);

        $muser = $user->get_moodleuser();

        //get specific context
        $cls = new pmclass(100);
        $context = context_elis_class::instance($cls->id);

        //assign role
        $this->assertGreaterThan(0, role_assign($roleid, $muser->id, $context->id));
    }

    /**
     * ELIS-4749: Test for assigning a user a role on a user context
     */
    public function testAssignUserforUserCTX() {
        global $DB;

        //get role to assign (we'll just take the first one returned)
        $roles_ctx = $DB->get_records('role_context_levels',array('contextlevel' => CONTEXT_ELIS_USER));
        foreach ($roles_ctx as $role_ctx) {
            $roleid = $role_ctx->roleid;
            break;
        }

        //get user to assign role
        $user  = new user(103);
        $muser = $user->get_moodleuser();

        //get specific context
        $context = context_elis_user::instance($user->id);

        //assign role
        $this->assertGreaterThan(0, role_assign($roleid, $muser->id, $context->id));
    }

    /**
     * ELIS-4749: Test for assigning a user a role on a user context
     */
    public function testAssignUserforUsersetCTX() {
        global $DB;

        //get role to assign (we'll just take the first one returned)
        $roles_ctx = $DB->get_records('role_context_levels',array('contextlevel' => CONTEXT_ELIS_USERSET));
        foreach ($roles_ctx as $role_ctx) {
            $roleid = $role_ctx->roleid;
            break;
        }

        //get user to assign role
        $user  = new user(103);
        $muser = $user->get_moodleuser();

        //get specific context
        $usrset = new userset(1);
        $context = context_elis_userset::instance($usrset->id);

        //assign role
        $this->assertGreaterThan(0, role_assign($roleid, $muser->id, $context->id));
    }

    /**
     * Test the role assignment interface to determine if it is properly finding our custom contexts
     */
    public function testRoleTableContexts() {
        $context = get_context_instance(CONTEXT_SYSTEM);
        $roleTable = new roleTable($context, 3);

        $allcontextlevels = array(
            CONTEXT_SYSTEM => get_string('coresystem'),
            CONTEXT_USER => get_string('user'),
            CONTEXT_COURSECAT => get_string('category'),
            CONTEXT_COURSE => get_string('course'),
            CONTEXT_MODULE => get_string('activitymodule'),
            CONTEXT_BLOCK => get_string('block'),
            1001 => get_string('curriculum', 'elis_program'),
            1002 => get_string('track', 'elis_program'),
            1003 => get_string('course', 'elis_program'),
            1004 => get_string('class', 'elis_program'),
            1005 => get_string('context_level_user', 'elis_program'),
            1006 => get_string('cluster', 'elis_program')
        );

        $this->assertEquals($allcontextlevels, $roleTable->get_all_context_levels());
    }

    /**
     * Test that the pm_ensure_role_assignable function works correctly
     */
    public function testPmEnsureRoleAssignable() {
        global $DB;

        // This test needs to have the role_context_levels table completely empty before beginning
        $DB->delete_records('role_context_levels');

        $context_levels = context_elis_helper::get_all_levels();

        $managerroleid      = $DB->get_field('role', 'id', array('shortname' => 'manager'));
        $programadminroleid = $DB->get_field('role', 'id', array('shortname' => 'curriculumadmin'));

        // Test that the function works with the 'manager' role
        $this->assertEquals($managerroleid, pm_ensure_role_assignable('manager'));

        foreach ($context_levels as $ctxlevel => $ctxclass) {
            $params = array('roleid' => $managerroleid, 'contextlevel' => $ctxlevel);
            $this->assertTrue($DB->record_exists('role_context_levels', $params));
        }

        // Test that the function works with the 'curriculumadmin' role
        $this->assertEquals($programadminroleid, pm_ensure_role_assignable('curriculumadmin'));

        foreach ($context_levels as $ctxlevel => $ctxclass) {
            $params = array('roleid' => $programadminroleid, 'contextlevel' => $ctxlevel);
            $this->assertTrue($DB->record_exists('role_context_levels', $params));
        }
    }

    public function testPmNotifyRoleAssignHandler() {
        global $DB;

        // Setup ELIS PM configuration for notification messages on enrolment
        elis::$config->elis_program->notify_classenrol_user             = 0;
        elis::$config->elis_program->notify_classenrol_role             = 1;
        elis::$config->elis_program->fitem_id_notify_classenrol_message = '%%userenrolname%% has been enrolled in the class %%classname%%.';

        // Add the correct capability to the system level role and assign that role to the admin user
        $admin = get_admin();

        $role    = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $syscontext = get_context_instance(CONTEXT_SYSTEM);

        $this->assertTrue(assign_capability('elis/program:notify_classenrol', CAP_ALLOW, $role->id, $syscontext->id));
        $syscontext->mark_dirty();
        $this->assertGreaterThan(0, role_assign($role->id, $admin->id, $syscontext->id));


        // Assign the test user a new role in the class context
        $testuser = $DB->get_record('user', array('id' => 100));
        $pmclass = new pmclass(100);

        $role    = $DB->get_record('role', array('shortname' => 'student'));
        $context = context_elis_class::instance($pmclass->id);
        $this->assertGreaterThan(0, role_assign($role->id, $testuser->id, $context->id));

        // Validate that the message was correctly sent
        $fullname = fullname($testuser);

        $select = 'useridfrom = :useridfrom AND useridto = :useridto AND '.
                  $DB->sql_compare_text('subject', 255).' = :subject AND '.
                  $DB->sql_compare_text('smallmessage', 255).' = :smallmessage';

        $params = array(
            'useridfrom'   => $testuser->id,
            'useridto'     => $admin->id,
            'subject'      => get_string('unreadnewmessage', 'message', $fullname),
            'smallmessage' => $fullname.' has been enrolled in the class instance '.$pmclass->idnumber.'.'
        );

        $this->assertTrue($DB->record_exists_select('message', $select, $params));
    }
}

class roleTable extends define_role_table_advanced {
    public function get_all_context_levels() {
        return $this->allcontextlevels;
    }
}
