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
            'role_context_levels' => 'moodle',
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

    /**
     * Data provider for test_pm_get_select_roles()
     * data format:
     * array( array( // array of required roles to create
     *     array( // single role 'object'
     *         'shortname' => 'role_short_name',
     *         'name'      => 'role_name',
     *         'contexts'  => array(contextlevels), // assignable context levels
     *         'caps'      => array('elis/program:config' => CAP_ALLOW, ...) // CAPS
     *     ), ... ),
     *     array(passedcontextlevels), // contextlevel array to pass to pm_get_select_roles_for_contexts() as 2nd param
     *     array(expectedrolesarray) // expected associative array: roleshortname => rolename
     * )
     *
     */
    public function pm_get_select_roles_data_provider() {
        $systemrole = array(
            'shortname' => 'SystemRole',
            'name'      => '', // intentionally blank!
            'contexts'  => array(CONTEXT_SYSTEM),
            'caps'      => array('elis/program:config' => CAP_ALLOW)
        );
        $mdlcrsrole = array(
            'shortname' => 'MoodleCourseRole',
            'name'      => 'Moodle Course Role',
            'contexts'  => array(CONTEXT_COURSE),
            'caps'      => array('moodle/course:update' => CAP_ALLOW)
        );
        $cirole = array(
            'shortname' => 'elisclassrole',
            'name'      => 'ELIS Class Instance Role',
            'contexts'  => array(CONTEXT_ELIS_CLASS),
            'caps'      => array('elis/program:class_edit' => CAP_ALLOW)
        );
        $usrole = array(
            'shortname' => 'elisusrole',
            'name'      => 'ELIS Userset Role',
            'contexts'  => array(CONTEXT_ELIS_USERSET),
            'caps'      => array('elis/program:userset_view' => CAP_ALLOW)
        );
        $prgrole = array(
            'shortname' => 'elisprgrole',
            'name'      => 'ELIS Program Role',
            'contexts'  => array(CONTEXT_ELIS_PROGRAM),
            'caps'      => array('elis/program:program_view' => CAP_ALLOW)
        );
        $trkrole = array(
            'shortname' => 'elistrkrole',
            'name'      => 'ELIS Track Role',
            'contexts'  => array(CONTEXT_ELIS_TRACK),
            'caps'      => array('elis/program:track_view' => CAP_ALLOW)
        );
        $multirole = array(
            'shortname' => 'multirole',
            'name'      => 'Multi-Role',
            'contexts'  => array(CONTEXT_SYSTEM, CONTEXT_ELIS_COURSE, CONTEXT_ELIS_USERSET, CONTEXT_ELIS_TRACK),
            'caps'      => array('elis/program:user_view' => CAP_ALLOW)
        );
        return array(
            array(
                array($systemrole), array(CONTEXT_SYSTEM), array('SystemRole' => 'SystemRole')
            ),
            array(
                array($systemrole), array(CONTEXT_ELIS_PROGRAM), array()
            ),
            array(
                array($mdlcrsrole), array(CONTEXT_COURSE), array('MoodleCourseRole' => 'Moodle Course Role')
            ),
            array(
                array($systemrole, $mdlcrsrole), array(CONTEXT_SYSTEM, CONTEXT_COURSE),
                array(
                    'SystemRole' => 'SystemRole',
                    'MoodleCourseRole' => 'Moodle Course Role'
                )
            ),
            array(
                array($cirole, $mdlcrsrole), array(CONTEXT_ELIS_CLASS), array('elisclassrole' => 'ELIS Class Instance Role')
            ),
            array(
                array($usrole, $mdlcrsrole), array(CONTEXT_ELIS_USERSET), array('elisusrole' => 'ELIS Userset Role')
            ),
            array(
                array($prgrole, $mdlcrsrole), array(CONTEXT_ELIS_PROGRAM), array('elisprgrole' => 'ELIS Program Role')
            ),
            array(
                array($trkrole, $mdlcrsrole), array(CONTEXT_ELIS_TRACK), array('elistrkrole' => 'ELIS Track Role')
            ),
            array(
                array($multirole, $mdlcrsrole), array(CONTEXT_ELIS_COURSE), array('multirole' => 'Multi-Role')
            ),
            array(
                array($multirole, $mdlcrsrole), array(CONTEXT_ELIS_PROGRAM), array()
            ),
            array(
                array($multirole, $mdlcrsrole), array(CONTEXT_COURSE), array('MoodleCourseRole' => 'Moodle Course Role')
            ),
            array(
                array($multirole, $mdlcrsrole), array(CONTEXT_ELIS_USERSET), array('multirole' => 'Multi-Role')
            ),
            array(
                array($multirole, $mdlcrsrole), array(CONTEXT_SYSTEM), array('multirole' => 'Multi-Role')
            ),
            array(
                array($multirole, $systemrole), array(CONTEXT_SYSTEM), array('multirole' => 'Multi-Role', 'SystemRole' => 'SystemRole')
            )
        );
    }

    /**
     * Method to test function /elis/program/lib/lib.php::pm_get_select_roles_for_contexts()
     * part of ELIS-8341
     * @param array $testroles array of role 'objects' to create
     * @param array $passedcontexts  array of contexts to pass to function under  test
     * @param array $expectedresults associative array of selectable roles: roleshortname => rolename
     * @uses $DB
     * @dataProvider pm_get_select_roles_data_provider
     */
    public function test_pm_get_select_roles($testroles, $passedcontexts, $expectedresults) {
        global $DB;
        $id2shortname = array();

        // Create specified roles
        foreach ($testroles as $testrole) {
            $roleid = create_role($testrole['name'], $testrole['shortname'], 'Default role description');
            // Assign role capabilities
            foreach ($testrole['caps'] as $cap => $perm) {
                assign_capability($cap, $perm, $roleid, 1, true);
            }
            // Create assignable contexts array & assign
            $contexts = array();
            foreach ($testrole['contexts'] as $contextlevel) {
                $contexts[$contextlevel] = $contextlevel;
            }
            set_role_contextlevels($roleid, $contexts);
            // save roleid for later conversion
            $id2shortname[$roleid] = $testrole['shortname'];
        }

        // call test function
        $results = array();
        pm_get_select_roles_for_contexts($results, $passedcontexts);

        // Convert roleid to roleshortname to validate
        foreach ($results as $id => $name) {
            $results[$id2shortname[$id]] = $name;
            unset($results[$id]);
        }
        $this->assertEquals($expectedresults, $results);
    }
}
