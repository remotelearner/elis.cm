<?php
/**
* ELIS(TM): Enterprise Learning Intelligence Suite
* Copyright (C) 2008-2011 Remote-Learner.net Inc (http://www.remote-learner.net )
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program. If not, see <http://www.gnu.org/licenses/ >.
*
* @package    elis
* @subpackage program
* @author     Remote-Learner.net Inc
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
* @copyright (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
*
*/

require_once(dirname(__FILE__).'/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once('PHPUnit/Extensions/Database/DataSet/CsvDataSet.php');
require_once(elispm::lib('data/usertrack.class.php'));
require_once(elispm::file('enrol/userset/moodle_profile/userset_profile.class.php'));

class testTrackAssignmentGetAvailableUsers extends elis_database_test {
    /**
     * Return the list of tables that should be overlayed.
     */
    static protected function get_overlay_tables() {
        require_once(elispm::lib('data/clusterassignment.class.php'));
        require_once(elispm::lib('data/clustertrack.class.php'));
        require_once(elispm::lib('data/track.class.php'));
        require_once(elispm::lib('data/usertrack.class.php'));
        require_once(elispm::lib('data/user.class.php'));
        require_once(elispm::lib('data/usermoodle.class.php'));
        require_once(elispm::lib('data/userset.class.php'));

        return array(
            clusterassignment::TABLE => 'elis_program',
            clustertrack::TABLE => 'elis_program',
            curriculum::TABLE => 'elis_program',
            track::TABLE => 'elis_program',
            usertrack::TABLE => 'elis_program',
            user::TABLE => 'elis_program',
            usermoodle::TABLE => 'elis_program',
            userset::TABLE => 'elis_program',
            userset_profile::TABLE  => 'elis_program',
            'config' => 'moodle',
            'config_plugins' => 'moodle',
            'context' => 'moodle',
            'role' => 'moodle',
            'role_assignments' => 'moodle',
            'user' => 'moodle',
            'elis_field_data_char' => 'elis_core',
            'elis_field_data_int' => 'elis_core',
            'elis_field_data_num' => 'elis_core',
            'elis_field_data_text' => 'elis_core'
        );
    }

    /**
     * Return the list of tables that should be ignored for writes.
     */
    static protected function get_ignored_tables() {
        return array('cache_flags' => 'moodle');
    }

    /**
     * Load all necessary data from CSV files
     */
    protected function load_csv_data() {
        require_once(elispm::lib('data/track.class.php'));
        require_once(elispm::lib('data/usertrack.class.php'));
        require_once(elispm::lib('data/user.class.php'));

        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();

        $dataset->addTable(curriculum::TABLE, elis::component_file('program', 'phpunit/track_curriculum_available_users.csv'));
        $dataset->addTable(track::TABLE, elis::component_file('program', 'phpunit/track_trackassignment_available_users.csv'));
        $dataset->addTable(usertrack::TABLE, elis::component_file('program', 'phpunit/usertrack_trackassignment_available_users.csv'));
        $dataset->addTable(user::TABLE, elis::component_file('program', 'phpunit/user_trackassignment_available_users.csv'));

        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    /**
     * Create the system context record
     */
    protected function create_sys_context() {
        global $DB;

        $DB->execute("INSERT INTO {context}
                      SELECT *
                      FROM ".self::$origdb->get_prefix()."context
                      WHERE contextlevel = ?", array(CONTEXT_SYSTEM));
    }

    /**
     * Set up permissions that allow the current user to enrol users in tracks
     */
    protected function assign_track_enrol_permissions() {
        global $CFG, $USER, $DB;
        require_once(elispm::lib('data/user.class.php'));

        //set up a test role that allows users to enrol users in tracks
        $roleid = create_role('trackenrol', 'trackenrol', 'trackenrol');
        $syscontext = get_context_instance(CONTEXT_SYSTEM);
        assign_capability('elis/program:track_enrol', CAP_ALLOW, $roleid, $syscontext->id);

        //set up our current user
        $activeuser = new user(array('idnumber' => 'activeuser',
                                     'username' => 'activeuser',
                                     'firstname' => 'activeuser',
                                     'lastname' => 'activeuser',
                                     'email' => 'active@user.com',
                                     'country' => 'CA'));
        $activeuser->save();
        $USER = $DB->get_record('user', array('username' => 'activeuser'));

        //assign the role to the current user
        role_assign($roleid, $USER->id, $syscontext->id);
    }

    /**
     * Validate that including inactive users in the listing works
     */
    public function testAvailableUsersIncludesInactiveWhenIncludingInactive() {
        global $DB;
        $this->load_csv_data();
        $this->create_sys_context();
        set_config('siteguest', '');
        set_config('siteadmins', '');
        $this->assign_track_enrol_permissions();

        pm_set_config('legacy_show_inactive_users', 1);
        elis::$config = new elis_config();

        $users_recset = usertrack::get_available_users(1);
        $users = array();
        foreach ($users_recset as $key => $user) {
            $users[$key] = $user;
        }
        unset($users_recset);

        //note: this includes the user we are currently using for permissions reasons
        $this->assertEquals(4, count($users));

        //validate first user
        $this->assertArrayHasKey(101, $users);
        $user = $users[101];
        $this->assertEquals($user->username, 'unassignedinactive');

        //validate second user
        $this->assertArrayHasKey(102, $users);
        $user = $users[102];
        $this->assertEquals($user->username, 'unassignedactive');

        //validate third user
        $this->assertArrayHasKey(103, $users);
        $user = $users[103];
        $this->assertEquals($user->username, 'anotherunassignedactive');

        //validate count
        $count = usertrack::count_available_users(1);
        $this->assertEquals(4, $count);
    }

    /**
     * Validate that excluding inactive users in the listing works
     */
    public function testAvailableUsersExcludesInactiveWhenExcludingInactive() {
        $this->load_csv_data();
        $this->create_sys_context();
        $this->assign_track_enrol_permissions();
        set_config('siteguest', '');
        set_config('siteadmins', '');

        pm_set_config('legacy_show_inactive_users', 0);
        elis::$config = new elis_config();

        $users_recset = usertrack::get_available_users(1);
        $users = array();
        foreach ($users_recset as $key => $user) {
            $users[$key] = $user;
        }
        unset($users_recset);

        //note: this includes the user we are currently using for permissions reasons
        $this->assertEquals(3, count($users));

        //validate first user
        $this->assertArrayHasKey(102, $users);
        $user = $users[102];
        $this->assertEquals($user->username, 'unassignedactive');

        //validate second user
        $this->assertArrayHasKey(103, $users);
        $user = $users[103];
        $this->assertEquals($user->username, 'anotherunassignedactive');

        //validate count
        $count = usertrack::count_available_users(1);
        $this->assertEquals(3, $count);
    }

    /**
     * Data provider for testing sorting
     */
    public function sortProvider() {
        return array(array('idnumber', 'ASC', 'activeuser'),
                     array('idnumber', 'DESC', 'unassignedinactive'),
                     array('lastname', 'ASC', 'Active'),
                     array('lastname', 'DESC', 'Inactive'),
                     array('email', 'ASC', 'active@user.com'),
                     array('email', 'DESC', 'unassigned@inactive.com'));
    }

    /**
     * @param string $sort The column to sort by
     * @param string $dir The sort direction
     * @param string $firstfieldvalue The value that should be contained in the field
     *                                identified by the sort parameter in the first record
     *
     * @dataProvider sortProvider
     */
    public function testAvailableUsersSortsCorrectly($sort, $dir, $firstfieldvalue) {
        $this->load_csv_data();
        $this->create_sys_context();
        $this->assign_track_enrol_permissions();
        set_config('siteguest', '');
        set_config('siteadmins', '');

        pm_set_config('legacy_show_inactive_users', 1);
        elis::$config = new elis_config();

        $users_recset = usertrack::get_available_users(1, $sort, $dir);
        $users = array();
        foreach ($users_recset as $key => $user) {
            $users[$key] = $user;
        }
        unset($users_recset);

        //note: this includes the user we are currently using for permissions reasons
        $this->assertEquals(4, count($users));
        $firstuser = reset($users);

        //validate the appropriate field that we're sorting by
        $this->assertEquals($firstfieldvalue, $firstuser->$sort);
    }

    /**
     * Validate that the listing respects name searches
     */
    public function testAvailableUsersRespectsNameSearch() {
        $this->load_csv_data();
        $this->create_sys_context();
        $this->assign_track_enrol_permissions();
        set_config('siteguest', '');
        set_config('siteadmins', '');

        $users_recset = usertrack::get_available_users(1, 'lastname', 'ASC', 'AnotherUnassigned Active');
        $users = array();
        foreach ($users_recset as $key => $user) {
            $users[$key] = $user;
        }
        unset($users_recset);

        $this->assertEquals(1, count($users));

        //validate user
        $this->assertArrayHasKey(103, $users);
        $user = $users[103];
        $this->assertEquals($user->username, 'anotherunassignedactive');

        //validate count
        $count = usertrack::count_available_users(1, 'AnotherUnassigned Active');
        $this->assertEquals(1, $count);
    }

    /**
     * Validate that the listing respects searches for the first letter of a
     * user's fullname
     */
    public function testAvailableUsersRespectsAlpha() {
        $this->load_csv_data();
        $this->create_sys_context();
        $this->assign_track_enrol_permissions();
        set_config('siteguest', '');
        set_config('siteadmins', '');

        $users_recset = usertrack::get_available_users(1, 'lastname', 'ASC', '', 'A');
        $users = array();
        foreach ($users_recset as $key => $user) {
            $users[$key] = $user;
        }
        unset($users_recset);

        //note: this includes the user we are currently using for permissions reasons
        $this->assertEquals(2, count($users));

        //validate user
        $this->assertArrayHasKey(103, $users);
        $user = $users[103];
        $this->assertEquals($user->username, 'anotherunassignedactive');

        //validate count
        $count = usertrack::count_available_users(1, '', 'A');
        $this->assertEquals(2, $count);
    }

    /**
     * Validate that the listing respects paging
     */
    public function testAvailableUsersRespectsPaging() {
        $this->load_csv_data();
        $this->create_sys_context();
        $this->assign_track_enrol_permissions();
        set_config('siteguest', '');
        set_config('siteadmins', '');

        pm_set_config('legacy_show_inactive_users', 0);
        elis::$config = new elis_config();

        $users_recset = usertrack::get_available_users(1, 'lastname', 'ASC', '', '', 0, 1);
        $users = array();
        foreach ($users_recset as $key => $user) {
            $users[$key] = $user;
        }
        unset($users_recset);

        //validate that only one record is picked up when paging with page size 1
        $this->assertEquals(1, count($users));
    }

    /**
     * Validate that the listing respects the elis/program:track_enrol_userset_user
     * capability as long as the appropriate userset and track are associated to
     * one another and the target user is in the userset
     */
    public function testAvailableUsersRespectsIndirectUsersetPermissions() {
        global $DB, $UNITTEST, $USER;
        require_once(elispm::lib('data/user.class.php'));
        require_once(elispm::lib('data/userset.class.php'));
        require_once(elispm::lib('data/clusterassignment.class.php'));
        require_once(elispm::lib('data/clustertrack.class.php'));

        $this->load_csv_data();
        $this->create_sys_context();
        set_config('siteguest', '');
        set_config('siteadmins', '');

        $UNITTEST = new stdClass;
        $UNITTEST->running = true;
        accesslib_clear_all_caches_for_unit_testing();
        unset($UNITTEST->running);
        //create a test user to be in the userset
        $usersetmember = new user(array('idnumber' => 'usersetmember',
                                        'username' => 'usersetmember',
                                        'firstname' => 'usersetmember',
                                        'lastname' => 'usersetmember',
                                        'email' => 'userset@member.com',
                                        'country' => 'CA'));
        $usersetmember->save();

        //our test userset
        $userset = new userset(array('name' => 'userset'));
        $userset->save();

        //assign the test user to the test userset
        $clusterassignment = new clusterassignment(array('userid' => $usersetmember->id,
                                                         'clusterid' => $userset->id));
        $clusterassignment->save();

        //assign the userset to our track
        $clustertrack = new clustertrack(array('clusterid' => $userset->id,
                                               'trackid' => 1));
        $clustertrack->save();

        //set up a db record for the active user for permissions reasons
        //(i.e. so they are not treated as an admin)
        $activeuser = new user(array('idnumber' => 'activeuser',
                                     'username' => 'activeuser',
                                     'firstname' => 'activeuser',
                                     'lastname' => 'activeuser',
                                     'email' => 'active@user.com',
                                     'country' => 'CA'));
        $activeuser->save();

        //set up our test role
        $roleid = create_role('testrole', 'testrole', 'testrole');
        $syscontext = get_context_instance(CONTEXT_SYSTEM);
        assign_capability('elis/program:track_enrol_userset_user', CAP_ALLOW, $roleid, $syscontext->id);

        //perform the role necessary assignment
        $moodleuser = $DB->get_record('user', array('username' => 'activeuser'));
        // make sure all the contexts are created, so that we can find the children
        $contextclass = context_elis_helper::get_class_for_level(CONTEXT_ELIS_USERSET);
        $instance     = $contextclass::instance($userset->id);
        role_assign($roleid, $moodleuser->id, $instance->id);

        //assume the role of the user with the role assignment
        $USER = $moodleuser;

        $users_recset = usertrack::get_available_users(1);
        $users = array();
        foreach ($users_recset as $key => $user) {
            $users[$key] = $user;
        }
        unset($users_recset);

        $this->assertEquals(1, count($users));

        //validate user
        $this->assertArrayHasKey($usersetmember->id, $users);
        $user = $users[$usersetmember->id];
        $this->assertEquals($usersetmember->username, 'usersetmember');

        //validate count
        $count = usertrack::count_available_users(1);
        $this->assertEquals(1, $count);
    }
}
