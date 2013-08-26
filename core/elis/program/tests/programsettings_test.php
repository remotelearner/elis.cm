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

/**
 * Class for testing functionality related to various settings that are core to how the PM system works
 * @group elis_program
 */
class programsettings_testcase extends elis_database_test {

    /**
     * Validate that the setting controls whether the redirect from My Moodle
     */
    public function test_myoodleredirectreturnsfalsewhensettingdisabled() {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot.'/user/lib.php');

        // Our test user.
        $user = new stdClass;
        $user->username = "testuser";
        $userid = user_create_user($user);
        $USER = $DB->get_record('user', array('id' => $userid));

        // Disable the setting.
        pm_set_config('mymoodle_redirect', 0);
        elis::$config = new elis_config();

        // Validation.
        $result = pm_mymoodle_redirect();
        $this->assertFalse($result);
    }

    /**
     * Validate that redirection from My Moodle does not happen for admins
     */
    public function test_mymoodleredirectreturnsfalseforadmin() {
        global $CFG, $USER, $DB;
        require_once($CFG->dirroot.'/user/lib.php');

        // Make sure we're not a guest.
        set_config('siteguest', '');

        // Obtain the system context.
        $syscontext = get_context_instance(CONTEXT_SYSTEM);

        // Set up the current user global.
        $user = new stdClass;
        $user->username = "testuser";
        $userid = user_create_user($user);
        $USER = $DB->get_record('user', array('id' => $userid));

        // Enable functionaltiy.
        pm_set_config('mymoodle_redirect', 1);
        elis::$config = new elis_config();

        // Give the admin sufficient permissions.
        $roleid = create_role('adminrole', 'adminrole', 'adminrole');
        assign_capability('moodle/site:config', CAP_ALLOW, $roleid, $syscontext->id);
        role_assign($roleid, $USER->id, $syscontext->id);

        // Validate that redirection does not happen for admins.
        $result = pm_mymoodle_redirect();

        // Clear out cached permissions data so we don't affect other tests.
        accesslib_clear_all_caches(true);

        $this->assertFalse($result);
    }

    /**
     * Validate that redirection from My Moodle does not happen while editing
     */
    public function test_mymoodleredirectreturnsfalseduringedit() {
        // Enable functionality.
        pm_set_config('mymoodle_redirect', 1);
        elis::$config = new elis_config();

        // Validation.
        $result = pm_mymoodle_redirect(true);
        $this->assertFalse($result);
    }

    /**
     * Validate that a non-logged-in user is not redirected from My Moodle
     */
    public function test_mymoodleredirectreturnsfalsewhennotloggedin() {
        // Set up our user.
        global $USER;
        $userid = $USER->id;
        unset($USER->id);

        // Enable functionality.
        pm_set_config('mymoodle_redirect', 1);
        elis::$config = new elis_config();

        // Validation.
        $result = pm_mymoodle_redirect();
        // Reset state of global.
        $USER->id = $userid;
        $this->assertFalse($result);
    }

    /**
     * Validate that a non-existent user is not redirect from My Moodle
     */
    public function test_mymoodleredirectreturnsfalsewhenusernotindatabase() {
        // Enable functionality.
        pm_set_config('mymoodle_redirect', 1);
        elis::$config = new elis_config();

        // Validation ($USER global already has an id attribute).
        $result = pm_mymoodle_redirect();
        $this->assertFalse($result);
    }

    /**
     * Validate that My Moodle redirection works when it's supposed to
     */
    public function test_myoodleredirectreturnstruewhensettingenabled() {
        global $CFG, $USER, $DB;
        require_once($CFG->dirroot.'/user/lib.php');

        // Set up our test user.
        $user = new stdClass;
        $user->username = "testuser";
        $userid = user_create_user($user);
        $USER = $DB->get_record('user', array('id' => $userid));

        // Enable functionality.
        pm_set_config('mymoodle_redirect', 1);
        elis::$config = new elis_config();

        // Validation.
        $result = pm_mymoodle_redirect();
        $this->assertTrue($result);
    }
}