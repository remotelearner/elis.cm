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
require_once(elispm::lib('data/usertrack.class.php'));
require_once(elispm::file('enrol/userset/moodle_profile/userset_profile.class.php'));
require_once(elispm::lib('data/clusterassignment.class.php'));
require_once(elispm::lib('data/clustertrack.class.php'));
require_once(elispm::lib('data/track.class.php'));
require_once(elispm::lib('data/usertrack.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));
require_once(elispm::lib('data/userset.class.php'));

/**
 * Test trackassignment get_available_users.
 * @group elis_program
 */
class trackassignmentgetavailableusers_testcase extends elis_database_test {

    /**
     * Load initial data from CSVs.
     */
    protected function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            curriculum::TABLE => elis::component_file('program', 'tests/fixtures/track_curriculum_available_users.csv'),
            track::TABLE => elis::component_file('program', 'tests/fixtures/track_trackassignment_available_users.csv'),
            usertrack::TABLE => elis::component_file('program', 'tests/fixtures/usertrack_trackassignment_available_users.csv'),
            user::TABLE => elis::component_file('program', 'tests/fixtures/user_trackassignment_available_users.csv'),
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * Set up permissions that allow the current user to enrol users in tracks
     */
    protected function assign_track_enrol_permissions() {
        global $CFG, $USER, $DB;
        require_once(elispm::lib('data/user.class.php'));

        // Set up a test role that allows users to enrol users in tracks.
        $roleid = create_role('trackenrol', 'trackenrol', 'trackenrol');
        $syscontext = get_context_instance(CONTEXT_SYSTEM);
        assign_capability('elis/program:track_enrol', CAP_ALLOW, $roleid, $syscontext->id);

        // Set up our current user.
        $activeuser = new user(array(
            'idnumber' => 'activeuser',
            'username' => 'activeuser',
            'firstname' => 'activeuser',
            'lastname' => 'activeuser',
            'email' => 'active@user.com',
            'country' => 'CA'
        ));
        $activeuser->save();
        $USER = $DB->get_record('user', array('username' => 'activeuser'));

        // Assign the role to the current user.
        role_assign($roleid, $USER->id, $syscontext->id);
    }

    /**
     * Validate that including inactive users in the listing works
     */
    public function test_availableusersincludesinactivewhenincludinginactive() {
        global $DB;
        $this->load_csv_data();
        set_config('siteguest', '');
        set_config('siteadmins', '');
        $this->assign_track_enrol_permissions();

        pm_set_config('legacy_show_inactive_users', 1);
        elis::$config = new elis_config();

        $usersrecset = usertrack::get_available_users(1);
        $users = array();
        foreach ($usersrecset as $key => $user) {
            $users[$key] = $user;
        }
        unset($usersrecset);

        // Note: this includes the user we are currently using for permissions reasons.
        $this->assertEquals(4, count($users));

        // Validate first user.
        $this->assertArrayHasKey(101, $users);
        $user = $users[101];
        $this->assertEquals($user->username, 'unassignedinactive');

        // Validate second user.
        $this->assertArrayHasKey(102, $users);
        $user = $users[102];
        $this->assertEquals($user->username, 'unassignedactive');

        // Validate third user.
        $this->assertArrayHasKey(103, $users);
        $user = $users[103];
        $this->assertEquals($user->username, 'anotherunassignedactive');

        // Validate count.
        $count = usertrack::count_available_users(1);
        $this->assertEquals(4, $count);
    }

    /**
     * Validate that excluding inactive users in the listing works
     */
    public function test_availableusersexcludesinactivewhenexcludinginactive() {
        $this->load_csv_data();
        $this->assign_track_enrol_permissions();
        set_config('siteguest', '');
        set_config('siteadmins', '');

        pm_set_config('legacy_show_inactive_users', 0);
        elis::$config = new elis_config();

        $usersrecset = usertrack::get_available_users(1);
        $users = array();
        foreach ($usersrecset as $key => $user) {
            $users[$key] = $user;
        }
        unset($usersrecset);

        // Note: this includes the user we are currently using for permissions reasons.
        $this->assertEquals(3, count($users));

        // Validate first user.
        $this->assertArrayHasKey(102, $users);
        $user = $users[102];
        $this->assertEquals($user->username, 'unassignedactive');

        // Validate second user.
        $this->assertArrayHasKey(103, $users);
        $user = $users[103];
        $this->assertEquals($user->username, 'anotherunassignedactive');

        // Validate count.
        $count = usertrack::count_available_users(1);
        $this->assertEquals(3, $count);
    }

    /**
     * Data provider for testing sorting
     */
    public function dataprovider_sort() {
        return array(
                array('idnumber', 'ASC', 'activeuser'),
                array('idnumber', 'DESC', 'unassignedinactive'),
                array('lastname', 'ASC', 'Active'),
                array('lastname', 'DESC', 'Inactive'),
                array('email', 'ASC', 'active@user.com'),
                array('email', 'DESC', 'unassigned@inactive.com')
        );
    }

    /**
     * Test get_available_users sorting.
     * @param string $sort The column to sort by
     * @param string $dir The sort direction
     * @param string $firstfieldvalue The value that should be contained in the field
     *                                identified by the sort parameter in the first record
     *
     * @dataProvider dataprovider_sort
     */
    public function test_availableuserssortscorrectly($sort, $dir, $firstfieldvalue) {
        $this->load_csv_data();
        $this->assign_track_enrol_permissions();
        set_config('siteguest', '');
        set_config('siteadmins', '');

        pm_set_config('legacy_show_inactive_users', 1);
        elis::$config = new elis_config();

        $usersrecset = usertrack::get_available_users(1, $sort, $dir);
        $users = array();
        foreach ($usersrecset as $key => $user) {
            $users[$key] = $user;
        }
        unset($usersrecset);

        // Note: this includes the user we are currently using for permissions reasons.
        $this->assertEquals(4, count($users));
        $firstuser = reset($users);

        // Validate the appropriate field that we're sorting by.
        $this->assertEquals($firstfieldvalue, $firstuser->$sort);
    }

    /**
     * Validate that the listing respects name searches
     */
    public function test_availableusersrespectsnamesearch() {
        $this->load_csv_data();
        $this->assign_track_enrol_permissions();
        set_config('siteguest', '');
        set_config('siteadmins', '');

        $usersrecset = usertrack::get_available_users(1, 'lastname', 'ASC', 'AnotherUnassigned Active');
        $users = array();
        foreach ($usersrecset as $key => $user) {
            $users[$key] = $user;
        }
        unset($usersrecset);

        $this->assertEquals(1, count($users));

        // Validate user.
        $this->assertArrayHasKey(103, $users);
        $user = $users[103];
        $this->assertEquals($user->username, 'anotherunassignedactive');

        // Validate count.
        $count = usertrack::count_available_users(1, 'AnotherUnassigned Active');
        $this->assertEquals(1, $count);
    }

    /**
     * Validate that the listing respects searches for the first letter of a
     * user's fullname
     */
    public function test_availableusersrespectsalpha() {
        $this->load_csv_data();
        $this->assign_track_enrol_permissions();
        set_config('siteguest', '');
        set_config('siteadmins', '');

        $usersrecset = usertrack::get_available_users(1, 'lastname', 'ASC', '', 'A');
        $users = array();
        foreach ($usersrecset as $key => $user) {
            $users[$key] = $user;
        }
        unset($usersrecset);

        // Note: this includes the user we are currently using for permissions reasons.
        $this->assertEquals(2, count($users));

        // Validate user.
        $this->assertArrayHasKey(103, $users);
        $user = $users[103];
        $this->assertEquals($user->username, 'anotherunassignedactive');

        // Validate count.
        $count = usertrack::count_available_users(1, '', 'A');
        $this->assertEquals(2, $count);
    }

    /**
     * Validate that the listing respects paging
     */
    public function test_availableusersrespectspaging() {
        $this->load_csv_data();
        $this->assign_track_enrol_permissions();
        set_config('siteguest', '');
        set_config('siteadmins', '');

        pm_set_config('legacy_show_inactive_users', 0);
        elis::$config = new elis_config();

        $usersrecset = usertrack::get_available_users(1, 'lastname', 'ASC', '', '', 0, 1);
        $users = array();
        foreach ($usersrecset as $key => $user) {
            $users[$key] = $user;
        }
        unset($usersrecset);

        // Validate that only one record is picked up when paging with page size 1.
        $this->assertEquals(1, count($users));
    }

    /**
     * Validate that the listing respects the elis/program:track_enrol_userset_user
     * capability as long as the appropriate userset and track are associated to
     * one another and the target user is in the userset
     */
    public function test_availableusersrespectsindirectusersetpermissions() {
        global $DB, $USER;

        $this->load_csv_data();
        set_config('siteguest', '');
        set_config('siteadmins', '');

        accesslib_clear_all_caches_for_unit_testing();

        // Create a test user to be in the userset.
        $usersetmember = new user(array(
            'idnumber' => 'usersetmember',
            'username' => 'usersetmember',
            'firstname' => 'usersetmember',
            'lastname' => 'usersetmember',
            'email' => 'userset@member.com',
            'country' => 'CA'
        ));
        $usersetmember->save();

        // Our test userset.
        $userset = new userset(array('name' => 'userset'));
        $userset->save();

        // Assign the test user to the test userset.
        $clusterassignment = new clusterassignment(array(
            'userid' => $usersetmember->id,
            'clusterid' => $userset->id
        ));
        $clusterassignment->save();

        // Assign the userset to our track.
        $clustertrack = new clustertrack(array('clusterid' => $userset->id, 'trackid' => 1));
        $clustertrack->save();

        // Set up a db record for the active user for permissions reasons.
        // (i.e. so they are not treated as an admin).
        $activeuser = new user(array(
            'idnumber' => 'activeuser',
            'username' => 'activeuser',
            'firstname' => 'activeuser',
            'lastname' => 'activeuser',
            'email' => 'active@user.com',
            'country' => 'CA'
        ));
        $activeuser->save();

        // Set up our test role.
        $roleid = create_role('testrole', 'testrole', 'testrole');
        $syscontext = get_context_instance(CONTEXT_SYSTEM);
        assign_capability('elis/program:track_enrol_userset_user', CAP_ALLOW, $roleid, $syscontext->id);

        // Perform the role necessary assignment.
        $moodleuser = $DB->get_record('user', array('username' => 'activeuser'));
        // Make sure all the contexts are created, so that we can find the children.
        $contextclass = context_elis_helper::get_class_for_level(CONTEXT_ELIS_USERSET);
        $instance     = $contextclass::instance($userset->id);
        role_assign($roleid, $moodleuser->id, $instance->id);

        // Assume the role of the user with the role assignment.
        $USER = $moodleuser;

        $usersrecset = usertrack::get_available_users(1);
        $users = array();
        foreach ($usersrecset as $key => $user) {
            $users[$key] = $user;
        }
        unset($usersrecset);

        $this->assertEquals(1, count($users));

        // Validate user.
        $this->assertArrayHasKey($usersetmember->id, $users);
        $user = $users[$usersetmember->id];
        $this->assertEquals($usersetmember->username, 'usersetmember');

        // Validate count.
        $count = usertrack::count_available_users(1);
        $this->assertEquals(1, $count);
    }
}
