<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @subpackage program
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__).'/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once(elispm::file('rolepage.class.php'));
require_once(elispm::file('enrol/userset/moodle_profile/userset_profile.class.php'));

/**
 * Class that behaves like the cluster role page but allows for unit tests to
 * call its "get_available_records" method
 */
class accessible_cluster_rolepage extends cluster_rolepage {
    /**
     * Obtains the set of users available for assignment to the appropriate
     * role
     *
     * @return array An array where the first elements is the array of users
     *               and the second is the user count
     */
    public function get_available_records($filter) {
        //delegate to the parent class
        return parent::get_available_records($filter);
    }
}

/**
 * Test class for testing special ELIS roles functionality
 */
class testRoles extends elis_database_test {
    /**
     * Return the list of tables that should be overlayed.
     *
     * @return array Mapping of tables to component names
     */
    static protected function get_overlay_tables() {
        global $CFG;
        require_once(elispm::lib('data/clusterassignment.class.php'));
        require_once(elispm::lib('data/user.class.php'));
        require_once(elispm::lib('data/usermoodle.class.php'));
        require_once(elispm::lib('data/userset.class.php'));

        return array(
            clusterassignment::TABLE => 'elis_program',
            user::TABLE => 'elis_program',
            usermoodle::TABLE => 'elis_program',
            userset::TABLE => 'elis_program',
            userset_profile::TABLE  => 'elis_program',
            'config_plugins' => 'moodle',
            'context' => 'moodle',
            'role' => 'moodle',
            'role_assignments' => 'moodle',
            'role_capabilities' => 'moodle',
            'user' => 'moodle',
            'elis_field_data_char' => 'elis_core',
            'elis_field_data_int' => 'elis_core',
            'elis_field_data_num' => 'elis_core',
            'elis_field_data_text' => 'elis_core'
        );
    }

    /**
     * Return the list of tables that should be ignored for writes.
     *
     * @return array Mapping of tables to component names
     */
    static protected function get_ignored_tables() {
        return array('cache_flags' => 'moodle');
    }

    /**
     * Validate that the cluster role page respects non-admin privileges when
     * obtaining the list of users
     */
    public function testClusterRolepageAvailableRecordsRespectUsersetPermissions() {
        global $CFG, $_GET, $USER, $DB;
        require_once(elispm::lib('data/clusterassignment.class.php'));
        require_once(elispm::lib('data/user.class.php'));
        require_once(elispm::lib('data/userset.class.php'));

        accesslib_clear_all_caches(true);

        //create our test userset
        $userset = new userset(array('name' => 'testuserset'));
        $userset->save();

        //the user who is assigned to the user set
        $assigned_user = new user(array('idnumber' => 'assigned',
                                        'username' => 'assigned',
                                        'firstname' => 'assigned',
                                        'lastname' => 'assigned',
                                        'email' => 'assigned@assigned.com',
                                        'country' => 'CA'));
        $assigned_user->save();

        //userset assignment
        $cluster_assignment = new clusterassignment(array('clusterid' => $userset->id,
                                                          'userid' => $assigned_user->id));
        $cluster_assignment->save();

        //user who is potentially assigning the userset member a new role
        //within the userset
        $assigning_user = new user(array('idnumber' => 'assigning',
                                         'username' => 'assigning',
                                         'firstname' => 'assigning',
                                         'lastname' => 'assigning',
                                         'email' => 'assigning@assigning.com',
                                         'country' => 'CA'));
        $assigning_user->save();

        //need the system context for role assignments
        $system_context = get_context_instance(CONTEXT_SYSTEM);

        //set up the role that allows a user to assign roles but only to userset
        //members
        $permissions_roleid = create_role('permissionsrole', 'permissionsrole', 'permissionsrole');
        //enable the appropriate capabilities
        assign_capability('moodle/role:assign', CAP_ALLOW, $permissions_roleid, $system_context->id);
        assign_capability('elis/program:userset_role_assign_userset_users', CAP_ALLOW, $permissions_roleid,
                          $system_context->id);

        //perform the role assignment
        $moodle_userid = $DB->get_field('user', 'id', array('username' => 'assigning'));
        role_assign($permissions_roleid, $moodle_userid, $system_context->id);

        //imitate the user assigned the role which allows for further role
        //assignments only on userset members
        $USER = $DB->get_record('user', array('id' => $moodle_userid));

        //test role for potential assignment to userset members
        $roleid = create_role('targetrole', 'targetrole', 'targetrole');

        //obtain the list of users available for assignment
        $page = new accessible_cluster_rolepage(array('id' => $userset->id));
        $_GET['role'] = $roleid;
        list($available_users, $count) = $page->get_available_records(new pm_user_filtering());

        //list should only contain the userset member
        $available_users_count = 0;
        foreach ($available_users as $available_user) {
            $available_users_count++;
            $this->assertEquals('assigned', $available_user->idnumber);
        }
        $this->assertEquals(1, $available_users_count);
    }

    /**
     * Validate that counting the number of role assignments on a particular
     * cluster for a particular role respects special userset permissions
     */
    public function testClusterRolepageCountRoleUsersRespectsUsersetPermissions() {
        global $CFG, $_GET, $USER, $DB;
        require_once(elispm::lib('data/clusterassignment.class.php'));
        require_once(elispm::lib('data/user.class.php'));
        require_once(elispm::lib('data/userset.class.php'));

        accesslib_clear_all_caches(true);

        //create a user record so that Moodle and PM ids don't match by fluke
        set_config('auto_assign_user_idnumber', 0, 'elis_program');
        elis::$config = new elis_config();
        create_user_record('bogususer', 'Bogususer!0');

        //create our test userset
        $userset = new userset(array('name' => 'testuserset'));
        $userset->save();

        //the user who is assigned to the user set
        $assigned_user = new user(array('idnumber' => 'assigned',
                                        'username' => 'assigned',
                                        'firstname' => 'assigned',
                                        'lastname' => 'assigned',
                                        'email' => 'assigned@assigned.com',
                                        'country' => 'CA'));
        $assigned_user->save();

        //userset assignment
        $cluster_assignment = new clusterassignment(array('clusterid' => $userset->id,
                                                          'userid' => $assigned_user->id));
        $cluster_assignment->save();

        //user who is potentially assigning the userset member a new role
        //within the userset
        $assigning_user = new user(array('idnumber' => 'assigning',
                                         'username' => 'assigning',
                                         'firstname' => 'assigning',
                                         'lastname' => 'assigning',
                                         'email' => 'assigning@assigning.com',
                                         'country' => 'CA'));
        $assigning_user->save();

        //need the system context for role assignments
        $system_context = get_context_instance(CONTEXT_SYSTEM);

        //set up the role that allows a user to assign roles but only to userset
        //members
        $permissions_roleid = create_role('permissionsrole', 'permissionsrole', 'permissionsrole');
        //enable the appropriate capabilities
        assign_capability('moodle/role:assign', CAP_ALLOW, $permissions_roleid, $system_context->id);
        assign_capability('elis/program:userset_role_assign_userset_users', CAP_ALLOW, $permissions_roleid,
                          $system_context->id);

        //perform the role assignment
        $moodle_userid = $DB->get_field('user', 'id', array('username' => 'assigning'));
        role_assign($permissions_roleid, $moodle_userid, $system_context->id);

        //imitate the user assigned the role which allows for further role
        //assignments only on userset members
        $USER = $DB->get_record('user', array('id' => $moodle_userid));

        //test role for potential assignment to userset members
        $roleid = create_role('targetrole', 'targetrole', 'targetrole');

        //assign the both users to the userset role
        $contextclass = context_elis_helper::get_class_for_level(CONTEXT_ELIS_USERSET);
        $userset_context     = $contextclass::instance($userset->id);
        role_assign($roleid, $moodle_userid, $userset_context->id);
        $moodle_userid = $DB->get_field('user', 'id', array('username' => 'assigned'));
        role_assign($roleid, $moodle_userid, $userset_context->id);

        //obtain the count of assigned users
        $page = new cluster_rolepage(array('id' => $userset->id));
        $count = $page->count_role_users($roleid, $userset_context);

        //list should only contain the userset member
        $this->assertEquals(1, $count);
    }
}
