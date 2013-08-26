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
require_once(elispm::file('rolepage.class.php'));
require_once(elispm::file('enrol/userset/moodle_profile/userset_profile.class.php'));
require_once(elispm::lib('data/clusterassignment.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));
require_once(elispm::lib('data/userset.class.php'));

/**
 * Class that behaves like the cluster role page but allows for unit tests to call its "get_available_records" method.
 */
class accessible_cluster_rolepage extends cluster_rolepage {

    /**
     * Obtains the set of users available for assignment to the appropriate role.
     * @return array An array where the first elements is the array of users and the second is the user count.
     */
    public function get_available_records($filter) {
        // Delegate to the parent class.
        return parent::get_available_records($filter);
    }
}

/**
 * Test class for testing special ELIS roles functionality.
 * @group elis_program
 */
class roles_testcase extends elis_database_test {

    /**
     * Validate that the cluster role page respects non-admin privileges when obtaining the list of users.
     */
    public function test_clusterrolepageavailablerecordsrespectusersetpermissions() {
        global $CFG, $USER, $DB;
        require_once(elispm::lib('data/clusterassignment.class.php'));
        require_once(elispm::lib('data/user.class.php'));
        require_once(elispm::lib('data/userset.class.php'));

        accesslib_clear_all_caches(true);

        // Create our test userset.
        $userset = new userset(array('name' => 'testuserset'));
        $userset->save();

        // The user who is assigned to the user set.
        $assigneduser = new user(array(
            'idnumber' => 'assigned',
            'username' => 'assigned',
            'firstname' => 'assigned',
            'lastname' => 'assigned',
            'email' => 'assigned@assigned.com',
            'country' => 'CA'
        ));
        $assigneduser->save();

        // Userset assignment.
        $clusterassignment = new clusterassignment(array('clusterid' => $userset->id, 'userid' => $assigneduser->id));
        $clusterassignment->save();

        // User who is potentially assigning the userset member a new role within the userset.
        $assigninguser = new user(array(
            'idnumber' => 'assigning',
            'username' => 'assigning',
            'firstname' => 'assigning',
            'lastname' => 'assigning',
            'email' => 'assigning@assigning.com',
            'country' => 'CA'
        ));
        $assigninguser->save();

        // Need the system context for role assignments.
        $systemcontext = get_context_instance(CONTEXT_SYSTEM);

        // Set up the role that allows a user to assign roles but only to userset members.
        $permissionsroleid = create_role('permissionsrole', 'permissionsrole', 'permissionsrole');
        // Enable the appropriate capabilities.
        assign_capability('moodle/role:assign', CAP_ALLOW, $permissionsroleid, $systemcontext->id);
        assign_capability('elis/program:userset_role_assign_userset_users', CAP_ALLOW, $permissionsroleid, $systemcontext->id);

        // Perform the role assignment.
        $moodleuserid = $DB->get_field('user', 'id', array('username' => 'assigning'));
        role_assign($permissionsroleid, $moodleuserid, $systemcontext->id);

        // Imitate the user assigned the role which allows for further role assignments only on userset members.
        $USER = $DB->get_record('user', array('id' => $moodleuserid));

        // Test role for potential assignment to userset members.
        $roleid = create_role('targetrole', 'targetrole', 'targetrole');

        // Obtain the list of users available for assignment.
        $page = new accessible_cluster_rolepage(array('id' => $userset->id));
        $_GET['role'] = $roleid;
        list($availableusers, $count) = $page->get_available_records(new pm_user_filtering());

        // List should only contain the userset member.
        $availableuserscount = 0;
        foreach ($availableusers as $availableuser) {
            $availableuserscount++;
            $this->assertEquals('assigned', $availableuser->idnumber);
        }
        $this->assertEquals(1, $availableuserscount);
    }

    /**
     * Validate that counting the number of role assignments on a particular
     * cluster for a particular role respects special userset permissions
     */
    public function test_clusterrolepagecountroleusersrespectsusersetpermissions() {
        global $CFG, $USER, $DB;
        require_once(elispm::lib('data/clusterassignment.class.php'));
        require_once(elispm::lib('data/user.class.php'));
        require_once(elispm::lib('data/userset.class.php'));

        accesslib_clear_all_caches(true);

        // Create a user record so that Moodle and PM ids don't match by fluke.
        set_config('auto_assign_user_idnumber', 0, 'elis_program');
        elis::$config = new elis_config();
        create_user_record('bogususer', 'Bogususer!0');

        // Create our test userset.
        $userset = new userset(array('name' => 'testuserset'));
        $userset->save();

        // The user who is assigned to the user set.
        $assigneduser = new user(array(
            'idnumber' => 'assigned',
            'username' => 'assigned',
            'firstname' => 'assigned',
            'lastname' => 'assigned',
            'email' => 'assigned@assigned.com',
            'country' => 'CA'
        ));
        $assigneduser->save();

        // Userset assignment.
        $clusterassignment = new clusterassignment(array('clusterid' => $userset->id, 'userid' => $assigneduser->id));
        $clusterassignment->save();

        // User who is potentially assigning the userset member a new role within the userset.
        $assigninguser = new user(array(
            'idnumber' => 'assigning',
            'username' => 'assigning',
            'firstname' => 'assigning',
            'lastname' => 'assigning',
            'email' => 'assigning@assigning.com',
            'country' => 'CA'
        ));
        $assigninguser->save();

        // Need the system context for role assignments.
        $systemcontext = get_context_instance(CONTEXT_SYSTEM);

        // Set up the role that allows a user to assign roles but only to userset members.
        $permissionsroleid = create_role('permissionsrole', 'permissionsrole', 'permissionsrole');
        // Enable the appropriate capabilities.
        assign_capability('moodle/role:assign', CAP_ALLOW, $permissionsroleid, $systemcontext->id);
        assign_capability('elis/program:userset_role_assign_userset_users', CAP_ALLOW, $permissionsroleid,
                          $systemcontext->id);

        // Perform the role assignment.
        $moodleuserid = $DB->get_field('user', 'id', array('username' => 'assigning'));
        role_assign($permissionsroleid, $moodleuserid, $systemcontext->id);

        // Imitate the user assigned the role which allows for further role assignments only on userset members.
        $USER = $DB->get_record('user', array('id' => $moodleuserid));

        // Test role for potential assignment to userset members.
        $roleid = create_role('targetrole', 'targetrole', 'targetrole');

        // Assign the both users to the userset role.
        $contextclass = context_elis_helper::get_class_for_level(CONTEXT_ELIS_USERSET);
        $usersetcontext = $contextclass::instance($userset->id);
        role_assign($roleid, $moodleuserid, $usersetcontext->id);
        $moodleuserid = $DB->get_field('user', 'id', array('username' => 'assigned'));
        role_assign($roleid, $moodleuserid, $usersetcontext->id);

        // Obtain the count of assigned users.
        $page = new cluster_rolepage(array('id' => $userset->id));
        $count = $page->count_role_users($roleid, $usersetcontext);

        // List should only contain the userset member.
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
            'name'      => '', // Intentionally blank!
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

        // Create specified roles.
        foreach ($testroles as $testrole) {
            $roleid = create_role($testrole['name'], $testrole['shortname'], 'Default role description');
            // Assign role capabilities.
            foreach ($testrole['caps'] as $cap => $perm) {
                assign_capability($cap, $perm, $roleid, 1, true);
            }
            // Create assignable contexts array & assign.
            $contexts = array();
            foreach ($testrole['contexts'] as $contextlevel) {
                $contexts[$contextlevel] = $contextlevel;
            }
            set_role_contextlevels($roleid, $contexts);
            // Save roleid for later conversion.
            $id2shortname[$roleid] = $testrole['shortname'];
        }

        // Call test function.
        $results = array();
        pm_get_select_roles_for_contexts($results, $passedcontexts);

        // Convert roleid to roleshortname to validate.
        foreach ($results as $id => $name) {
            if (isset($id2shortname[$id])) {
                $results[$id2shortname[$id]] = $name;
            }
            unset($results[$id]);
        }
        $this->assertEquals($expectedresults, $results);
    }
}
