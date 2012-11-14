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
require_once(elispm::lib('lib.php'));

/**
 * Class for testing functionality related to various settings that are core to
 * how the PM system works
 */
class programSettingsTest extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

    /**
     * Return the list of tables that should be overlayed.
     *
     * @return array The mapping to tables to their component
     */
    protected static function get_overlay_tables() {
        return array(
            'config_plugins' => 'moodle',
            'config' => 'moodle',
            'context' => 'moodle',
            'log' => 'moodle',
            'role' => 'moodle',
            'role_capabilities' => 'moodle',
            'role_assignments' => 'moodle',
            'user' => 'moodle'
        );
    }

    /**
     * Return the list of tables that should be ignored for writes.
     */
    static protected function get_ignored_tables() {
        return array('cache_flags' => 'moodle');
    }

    /**
     * Validate that the setting controls whether the redirect from My Moodle
     */
    public function testMyoodleRedirectReturnsFalseWhenSettingDisabled() {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot.'/user/lib.php');

        //our test user
        $user = new stdClass;
        $user->username = "testuser";
        $userid = user_create_user($user);
        $USER = $DB->get_record('user', array('id' => $userid));

        //disable the setting
        pm_set_config('mymoodle_redirect', 0);
        elis::$config = new elis_config();

        //validation
        $result = pm_mymoodle_redirect();
        $this->assertFalse($result);
    }

    /**
     * Validate that redirection from My Moodle does not happen for admins
     */
    public function testMymoodleRedirectReturnsFalseForAdmin() {
        global $CFG, $USER, $DB;
        require_once($CFG->dirroot.'/user/lib.php');

        //make sure we're not a guest
        set_config('siteguest', '');

        //set up the system context record to satisfy accesslib
        $DB->execute("INSERT INTO {context}
                      SELECT * FROM
                      ".self::$origdb->get_prefix()."context
                      WHERE contextlevel = ?", array(CONTEXT_SYSTEM));

        //obtain the system context
        $syscontext = get_context_instance(CONTEXT_SYSTEM);

        //set up the current user global
        $user = new stdClass;
        $user->username = "testuser";
        $userid = user_create_user($user);
        $USER = $DB->get_record('user', array('id' => $userid));

        //enable functionaltiy
        pm_set_config('mymoodle_redirect', 1);
        elis::$config = new elis_config();

        //give the admin sufficient permissions
        $roleid = create_role('adminrole', 'adminrole', 'adminrole');
        assign_capability('moodle/site:config', CAP_ALLOW, $roleid, $syscontext->id);
        role_assign($roleid, $USER->id, $syscontext->id);

        //validate that redirection does not happen for admins
        $result = pm_mymoodle_redirect();

        //clear out cached permissions data so we don't affect other tests
        accesslib_clear_all_caches(true);

        $this->assertFalse($result);
    }

    /**
     * Validate that redirection from My Moodle does not happen while editing
     */
    public function testMymoodleRedirectReturnsFalseDuringEdit() {
        //enable functionality
        pm_set_config('mymoodle_redirect', 1);
        elis::$config = new elis_config();

        //validation
        $result = pm_mymoodle_redirect(true);
        $this->assertFalse($result);
    }

    /**
     * Validate that a non-logged-in user is not redirected from My Moodle
     */
    public function testMymoodleRedirectReturnsFalseWhenNotLoggedIn() {
        //set up our user
        global $USER;
        $userid = $USER->id;
        unset($USER->id);

        //enable functionality
        pm_set_config('mymoodle_redirect', 1);
        elis::$config = new elis_config();

        //validation
        $result = pm_mymoodle_redirect();
        //reset state of global
        $USER->id = $userid;
        $this->assertFalse($result);
    }

    /**
     * Validate that a non-existent user is not redirect from My Moodle
     */
    public function testMymoodleRedirectReturnsFalseWhenUserNotInDatabase() {
        //enable functionality
        pm_set_config('mymoodle_redirect', 1);
        elis::$config = new elis_config();

        //validation ($USER global already has an id attribute)
        $result = pm_mymoodle_redirect();
        $this->assertFalse($result);
    }

    /**
     * Validate that My Moodle redirection works when it's supposed to
     */
    public function testMyoodleRedirectReturnsTrueWhenSettingEnabled() {
        global $CFG, $USER, $DB;
        require_once($CFG->dirroot.'/user/lib.php');

        //set up our test user
        $user = new stdClass;
        $user->username = "testuser";
        $userid = user_create_user($user);
        $USER = $DB->get_record('user', array('id' => $userid));

        //enable functionality
        pm_set_config('mymoodle_redirect', 1);
        elis::$config = new elis_config();

        //validation
        $result = pm_mymoodle_redirect();
        $this->assertTrue($result);
    }
}