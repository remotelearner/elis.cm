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
require_once(elispm::lib('data/clusterassignment.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));
require_once(elispm::lib('data/userset.class.php'));

/**
 * Test retrieval of users as used by the bulk user page.
 * @group elis_program
 */
class usermanagementgetsusers_testcase extends elis_database_test {

    /**
     * Load clusters from CSV file.
     */
    protected function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            userset::TABLE => elis::component_file('program', 'tests/fixtures/userset.csv')
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * Set up some base user data
     */
    protected function set_up_users() {
        global $DB, $USER;

        // Set up a cluster administrator as a PM user.
        $clusteradmin = new user(array(
            'idnumber' => 'clusteradmin',
            'username' => 'clusteradmin',
            'firstname' => 'Cluster',
            'lastname' => 'Admin',
            'email' => 'cluster@admin.com',
            'country' => 'CA'
        ));
        $clusteradmin->save();
        $USER = $DB->get_record('user', array('username' => 'clusteradmin'));

        // Set up a user set member as a PM user.
        $clusteruser = new user(array(
            'idnumber' => 'clusteruser',
            'username' => 'clusteruser',
            'firstname' => 'Cluster',
            'lastname' => 'User',
            'email' => 'cluster@user.com',
            'country' => 'CA'
        ));
        $clusteruser->save();

        // Set up our test role.
        $syscontext = get_context_instance(CONTEXT_SYSTEM);
        $roleid = create_role('clusteradmin', 'clusteradmin', 'clusteradmin');
        assign_capability('elis/program:user_edit', CAP_ALLOW, $roleid, $syscontext->id);
        // Assign the userset administrator an appropriate role on the userset.
        $instance     = context_elis_userset::instance(1);
        role_assign($roleid, $USER->id, $instance->id);

        // Assign the user to the user set.
        $clusterassignment = new clusterassignment(array('clusterid' => 1, 'userid' => $clusteruser->id));
        $clusterassignment->save();
    }

    /**
     * Test the basic functionality of the methods for fetching and counting
     * users in relation to userset permissions
     */
    public function test_usermanagementgetsusersrespectsusersetpermissions() {
        global $USER, $DB;

        require_once(elispm::lib('lib.php'));

        // Make sure we don't hit corner-cases with permissions.
        set_config('siteguest', '');
        set_config('siteadmins', '');

        // Prevent accesslib caching.
        accesslib_clear_all_caches(true);

        // Data setup.
        $this->load_csv_data();
        $this->set_up_users();

        // The context set our user set administrator has access to.
        $contextset = pm_context_set::for_user_with_capability('cluster', 'elis/program:user_edit', $USER->id);

        // Validate count.
        $count = usermanagement_count_users(array(), $contextset);
        $this->assertEquals(1, $count);

        // Validate record.
        $users = usermanagement_get_users('name', 'ASC', 0, 0, array(), $contextset);
        $this->assertEquals(1, count($users));
        $user = reset($users);
        $this->assertEquals('clusteruser', $user->idnumber);
    }

    /**
     * Test the basic functionality of the methods for fetching users as a
     * recordset in relation to userset permissions
     */
    public function test_usermanagementgetsusersrecordsetrespectsusersetpermissions() {
        global $USER, $DB;

        require_once(elispm::lib('lib.php'));

        // Make sure we don't hit corner-cases with permissions.
        set_config('siteguest', '');
        set_config('siteadmins', '');

        // Prevent accesslib caching.
        accesslib_clear_all_caches(true);

        // Data setup.
        $this->load_csv_data();
        $this->set_up_users();

        // The context set our user set administrator has access to.
        $contextset = pm_context_set::for_user_with_capability('cluster', 'elis/program:user_edit', $USER->id);

        // Validate record.
        $users = usermanagement_get_users_recordset('name', 'ASC', 0, 0, array(), $contextset);
        $this->assertTrue($users->valid());
        $user = $users->current();
        $this->assertEquals('clusteruser', $user->idnumber);
        $this->assertNull($users->next());
    }

    /**
     * Test the basic functionality of the methods for fetching and counting
     * users when applying userset permissions and an appropriate SQL filter
     */
    public function test_usermanagementgetsusersrespectsfilters() {
        global $USER, $DB;

        require_once(elispm::lib('data/clusterassignment.class.php'));
        require_once(elispm::lib('data/user.class.php'));
        require_once(elispm::lib('lib.php'));

        // Make sure we don't hit corner-cases with permissions.
        set_config('siteguest', '');
        set_config('siteadmins', '');

        // Prevent accesslib caching.
        accesslib_clear_all_caches(true);

        // Data setup.
        $this->load_csv_data();
        $this->set_up_users();

        // Assign a second user to the user set.
        $secondclusteruser = new user(array(
            'idnumber' => 'secondclusteruser',
            'username' => 'secondclusteruser',
            'firstname' => 'Secondcluster',
            'lastname' => 'User',
            'email' => 'secpmdcluster@user.com',
            'country' => 'CA'
        ));
        $secondclusteruser->save();
        $clusterassignment = new clusterassignment(array('clusterid' => 1, 'userid' => $secondclusteruser->id));
        $clusterassignment->save();

        // The context set our user set administrator has access to.
        $contextset = pm_context_set::for_user_with_capability('cluster', 'elis/program:user_edit', $USER->id);

        // Add a filter to filter down to only our first test user.
        $extrasql = array('username = :testusername', array('testusername' => 'clusteruser'));

        // Validate count.
        $count = usermanagement_count_users($extrasql, $contextset);
        $this->assertEquals(1, $count);

        // Validate record.
        $users = usermanagement_get_users('name', 'ASC', 0, 0, $extrasql, $contextset);
        $this->assertEquals(1, count($users));
        $user = reset($users);
        $this->assertEquals('clusteruser', $user->idnumber);
    }

    /**
     * Test the basic functionality of the methods for fetching users as a
     * recordset when applying userset permissions and an appropriate SQL filter
     */
    public function test_usermanagementgetsusersrecordsetrespectsfilters() {
        global $USER, $DB;

        require_once(elispm::lib('data/clusterassignment.class.php'));
        require_once(elispm::lib('data/user.class.php'));
        require_once(elispm::lib('lib.php'));

        // Make sure we don't hit corner-cases with permissions.
        set_config('siteguest', '');
        set_config('siteadmins', '');

        // Prevent accesslib caching.
        accesslib_clear_all_caches(true);

        // Data setup.
        $this->load_csv_data();
        $this->set_up_users();

        // Assign a second user to the user set.
        $secondclusteruser = new user(array(
            'idnumber' => 'secondclusteruser',
            'username' => 'secondclusteruser',
            'firstname' => 'Secondcluster',
            'lastname' => 'User',
            'email' => 'secpmdcluster@user.com',
            'country' => 'CA'
        ));
        $secondclusteruser->save();
        $clusterassignment = new clusterassignment(array('clusterid' => 1, 'userid' => $secondclusteruser->id));
        $clusterassignment->save();

        // The context set our user set administrator has access to.
        $contextset = pm_context_set::for_user_with_capability('cluster', 'elis/program:user_edit', $USER->id);

        // Add a filter to filter down to only our first test user.
        $extrasql = array('username = :testusername', array('testusername' => 'clusteruser'));

        // Validate record.
        $users = usermanagement_get_users_recordset('name', 'ASC', 0, 0, $extrasql, $contextset);
        $this->assertTrue($users->valid());
        $user = $users->current();
        $this->assertEquals('clusteruser', $user->idnumber);
        $this->assertNull($users->next());
    }
}