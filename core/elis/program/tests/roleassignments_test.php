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
require_once(elispm::lib('data/curriculum.class.php'));
require_once(elis::lib('data/customfield.class.php'));
require_once(elis::file('core/fields/moodle_profile/custom_fields.php'));
require_once(elispm::lib('data/usermoodle.class.php'));
require_once($CFG->dirroot.'/admin/roles/lib.php');

/**
 * Test role assignments
 * @group elis_program
 */
class roleassignments_testcase extends elis_database_test {

    /**
     * Load CSV data before every test.
     */
    protected function setUp() {
        parent::setUp();
        $this->load_csv_data();
    }

    /**
     * Load iniital data from CSVs.
     */
    protected function load_csv_data() {

        $dataset = $this->createCsvDataSet(array(
            'user' => elis::component_file('program', 'tests/fixtures/mdluser.csv'),
            'user_info_field' => elis::component_file('program', 'tests/fixtures/user_info_field.csv'),
            'user_info_data' => elis::component_file('program', 'tests/fixtures/user_info_data.csv'),
            user::TABLE => elis::component_file('program', 'tests/fixtures/pmuser.csv'),
            usermoodle::TABLE => elis::component_file('program', 'tests/fixtures/usermoodle.csv'),
            field::TABLE => elis::component_file('program', 'tests/fixtures/user_field.csv'),
            field_owner::TABLE => elis::component_file('program', 'tests/fixtures/user_field_owner.csv'),
            curriculum::TABLE => elis::component_file('program', 'tests/fixtures/curriculum.csv'),
            track::TABLE => elis::component_file('program', 'tests/fixtures/track.csv'),
            course::TABLE => elis::component_file('program', 'tests/fixtures/pmcourse.csv'),
            pmclass::TABLE => elis::component_file('program', 'tests/fixtures/pmclass.csv'),
            userset::TABLE => elis::component_file('program', 'tests/fixtures/userset.csv')
        ));
        $dataset = new PHPUnit_Extensions_Database_DataSet_ReplacementDataSet($dataset);
        $dataset->addSubStrReplacement('\n', "\n");
        $this->loadDataSet($dataset);
    }

    /**
     * ELIS-4745: Test for assigning a user a role on a program context
     */
    public function test_assignuserforprogramctx() {
        global $DB;

        // Get role to assign (we'll just take the first one returned).
        $rolesctx = $DB->get_records('role_context_levels', array('contextlevel' => CONTEXT_ELIS_PROGRAM));
        foreach ($rolesctx as $rolectx) {
            $roleid = $rolectx->roleid;
            break;
        }

        // Get user to assign role.
        $user  = new user(103);
        $muser = $user->get_moodleuser();

        // Get specific context.
        $cur = new curriculum(1);
        $context = context_elis_program::instance($cur->id);

        // Assign role.
        $this->assertGreaterThan(0, role_assign($roleid, $muser->id, $context->id));
    }

    /**
     * ELIS-4746: Test for assigning a user a role on a track context
     */
    public function test_assignuserfortrackctx() {
        global $DB;

        // Get role to assign (we'll just take the first one returned).
        $rolesctx = $DB->get_records('role_context_levels', array('contextlevel' => CONTEXT_ELIS_TRACK));
        foreach ($rolesctx as $rolectx) {
            $roleid = $rolectx->roleid;
            break;
        }

        // Get user to assign role.
        $user  = new user(103);
        $muser = $user->get_moodleuser();

        // Get specific context.
        $trk = new track(1);
        $context = context_elis_track::instance($trk->id);

        // Assign role.
        $this->assertGreaterThan(0, role_assign($roleid, $muser->id, $context->id));
    }

    /**
     * ELIS-4747: Test for assigning a user a role on a course context
     */
    public function test_assignuserforcoursectx() {
        global $DB;
        // Get role to assign (we'll just take the first one returned).
        $rolesctx = $DB->get_records('role_context_levels', array('contextlevel' => CONTEXT_ELIS_COURSE));
        foreach ($rolesctx as $rolectx) {
            $roleid = $rolectx->roleid;
            break;
        }

        // Get user to assign role.
        $user = new user(103);
        $muser = $user->get_moodleuser();

        // Get specific context.
        $crs = new course(100);
        $context = context_elis_course::instance($crs->id);

        // Assign role.
        $this->assertGreaterThan(0, role_assign($roleid, $muser->id, $context->id));
    }

    /**
     * ELIS-4748: Test for assigning a user a role on a course context
     */
    public function test_assignuserforclassctx() {
        global $DB;

        // Get role to assign (we'll just take the first one returned).
        $rolesctx = $DB->get_records('role_context_levels', array('contextlevel' => CONTEXT_ELIS_CLASS));
        foreach ($rolesctx as $rolectx) {
            $roleid = $rolectx->roleid;
            break;
        }

        // Get user to assign role.
        $user = new user(103);

        $muser = $user->get_moodleuser();

        // Get specific context.
        $cls = new pmclass(100);
        $context = context_elis_class::instance($cls->id);

        // Assign role.
        $this->assertGreaterThan(0, role_assign($roleid, $muser->id, $context->id));
    }

    /**
     * ELIS-4749: Test for assigning a user a role on a user context
     */
    public function test_assignuserforuserctx() {
        global $DB;

        // Get role to assign (we'll just take the first one returned).
        $rolesctx = $DB->get_records('role_context_levels', array('contextlevel' => CONTEXT_ELIS_USER));
        foreach ($rolesctx as $rolectx) {
            $roleid = $rolectx->roleid;
            break;
        }

        // Get user to assign role.
        $user  = new user(103);
        $muser = $user->get_moodleuser();

        // Get specific context.
        $context = context_elis_user::instance($user->id);

        // Assign role.
        $this->assertGreaterThan(0, role_assign($roleid, $muser->id, $context->id));
    }

    /**
     * ELIS-4749: Test for assigning a user a role on a user context
     */
    public function test_assignuserforusersetctx() {
        global $DB;

        // Get role to assign (we'll just take the first one returned).
        $rolesctx = $DB->get_records('role_context_levels', array('contextlevel' => CONTEXT_ELIS_USERSET));
        foreach ($rolesctx as $rolectx) {
            $roleid = $rolectx->roleid;
            break;
        }

        // Get user to assign role.
        $user  = new user(103);
        $muser = $user->get_moodleuser();

        // Get specific context.
        $usrset = new userset(1);
        $context = context_elis_userset::instance($usrset->id);

        // Assign role.
        $this->assertGreaterThan(0, role_assign($roleid, $muser->id, $context->id));
    }

    /**
     * Test the role assignment interface to determine if it is properly finding our custom contexts
     */
    public function test_roletablecontexts() {
        $context = get_context_instance(CONTEXT_SYSTEM);
        $roletable = new roleTable($context, 3);

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

        $this->assertEquals($allcontextlevels, $roletable->get_all_context_levels());
    }

    /**
     * Test that the pm_ensure_role_assignable function works correctly
     */
    public function test_pmensureroleassignable() {
        global $DB;

        // This test needs to have the role_context_levels table completely empty before beginning.
        $DB->delete_records('role_context_levels');

        $contextlevels = context_elis_helper::get_all_levels();

        $managerroleid      = $DB->get_field('role', 'id', array('shortname' => 'manager'));
        $programadminroleid = $DB->get_field('role', 'id', array('shortname' => 'curriculumadmin'));

        // Test that the function works with the 'manager' role.
        $this->assertEquals($managerroleid, pm_ensure_role_assignable('manager'));

        foreach ($contextlevels as $ctxlevel => $ctxclass) {
            $params = array('roleid' => $managerroleid, 'contextlevel' => $ctxlevel);
            $this->assertTrue($DB->record_exists('role_context_levels', $params));
        }

        // Test that the function works with the 'curriculumadmin' role.
        $this->assertEquals($programadminroleid, pm_ensure_role_assignable('curriculumadmin'));

        foreach ($contextlevels as $ctxlevel => $ctxclass) {
            $params = array('roleid' => $programadminroleid, 'contextlevel' => $ctxlevel);
            $this->assertTrue($DB->record_exists('role_context_levels', $params));
        }
    }

    /**
     * Test pmnotifyroleassignhandler
     */
    public function test_pmnotifyroleassignhandler() {
        global $DB;

        // Setup ELIS PM configuration for notification messages on enrolment.
        elis::$config->elis_program->notify_classenrol_user             = 0;
        elis::$config->elis_program->notify_classenrol_role             = 1;
        elis::$config->elis_program->fitem_id_notify_classenrol_message = '%%userenrolname%% has been enrolled in the class %%classname%%.';

        // Add the correct capability to the system level role and assign that role to the admin user.
        $admin = get_admin();

        $role = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $syscontext = get_context_instance(CONTEXT_SYSTEM);

        $this->assertTrue(assign_capability('elis/program:notify_classenrol', CAP_ALLOW, $role->id, $syscontext->id));
        $syscontext->mark_dirty();
        $this->assertGreaterThan(0, role_assign($role->id, $admin->id, $syscontext->id));

        // Assign the test user a new role in the class context.
        $testuser = $DB->get_record('user', array('id' => 100));
        $pmclass = new pmclass(100);

        $role    = $DB->get_record('role', array('shortname' => 'student'));
        $context = context_elis_class::instance($pmclass->id);
        $sink = $this->redirectMessages();
        $roleassignresult = role_assign($role->id, $testuser->id, $context->id);
        $this->assertGreaterThan(0, $roleassignresult);

        // Validate that the message was correctly sent.
        $messages = $sink->get_messages();
        $this->assertNotEmpty($messages);
        $fullname = fullname($testuser);
        $expected = array(
            'useridfrom' => $testuser->id,
            'useridto' => $admin->id,
            'subject' => get_string('unreadnewmessage', 'message', $fullname),
            'smallmessage' => $fullname.' has been enrolled in the class instance '.$pmclass->idnumber.'.',
        );
        foreach ($expected as $k => $v) {
            $this->assertEquals($v, $messages[0]->$k);
        }
    }
}

/**
 * Test role table.
 */
class roleTable extends define_role_table_advanced {
    /**
     * Get all context levels.
     * return array All context levels.
     */
    public function get_all_context_levels() {
        return $this->allcontextlevels;
    }
}
