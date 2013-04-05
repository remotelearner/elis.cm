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
 * @subpackage programmanager
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__).'/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(elis::lib('testlib.php'));

/**
 * Test retrieval of users as used by the bulk user page
 */
class usermanagementGetsUsersTest extends elis_database_test {
    /**
     * Return the list of tables that should be overlayed.
     *
     * @return array The mapping of table names to components
     */
    static protected function get_overlay_tables() {
        require_once(elispm::lib('data/clusterassignment.class.php'));
        require_once(elispm::lib('data/user.class.php'));
        require_once(elispm::lib('data/usermoodle.class.php'));
        require_once(elispm::lib('data/userset.class.php'));

        return array(
            'elis_field' => 'elis_core',
            clusterassignment::TABLE => 'elis_program',
            'crlm_class_enrolment'   => 'elis_program',
            'crlm_curriculum_assignment' => 'elis_program',
            user::TABLE => 'elis_program',
            usermoodle::TABLE => 'elis_program',
            usertrack::TABLE  => 'elis_program',
            userset::TABLE => 'elis_program',
            'config' => 'moodle',
            'context' => 'moodle',
            'course' => 'moodle',
            'enrol' => 'moodle',
            'role' => 'moodle',
            'role_assignments' => 'moodle',
            'role_capabilities' => 'moodle',
            'user' => 'moodle',
            'user_enrolments' => 'moodle'
        );
    }

    /**
     * Return the list of tables that should be ignored for writes.
     *
     * @return array The mapping of table names to componenets
     */
    static protected function get_ignored_tables() {
        return array('cache_flags' => 'moodle',
                     'message'     => 'moodle');
    }

    /**
     * Set up the course and context records needed for many of the
     * unit tests
     */
    private function init_contexts_and_site_course() {
        global $DB, $USER;

        $prefix = self::$origdb->get_prefix();
        $DB->execute("INSERT INTO {context}
                      SELECT * FROM
                      {$prefix}context
                      WHERE contextlevel = ?", array(CONTEXT_SYSTEM));
        $DB->execute("INSERT INTO {context}
                      SELECT * FROM
                      {$prefix}context
                      WHERE contextlevel = ? and instanceid = ?", array(CONTEXT_COURSE, SITEID));
        //set up the site course record
        if ($record = self::$origdb->get_record('course', array('id' => SITEID))) {
            unset($record->id);
            $DB->insert_record('course', $record);
        }

        build_context_path();
    }

    /**
     * Load clusters from CSV file
     */
    protected function load_csv_data() {
        require_once(elispm::lib('data/userset.class.php'));

        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(userset::TABLE, elis::component_file('program', 'phpunit/userset.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    /**
     * Set up some base user data
     */
    protected function set_up_users() {
        global $DB, $USER;

        require_once(elispm::lib('data/clusterassignment.class.php'));
        require_once(elispm::lib('data/user.class.php'));

        //set up a cluster administrator as a PM user
        $clusteradmin = new user(array('idnumber' => 'clusteradmin',
                                       'username' => 'clusteradmin',
                                       'firstname' => 'Cluster',
                                       'lastname' => 'Admin',
                                       'email' => 'cluster@admin.com',
                                       'country' => 'CA'));
        $clusteradmin->save();
        $USER = $DB->get_record('user', array('username' => 'clusteradmin'));

        //set up a user set member as a PM user
        $clusteruser = new user(array('idnumber' => 'clusteruser',
                                      'username' => 'clusteruser',
                                      'firstname' => 'Cluster',
                                      'lastname' => 'User',
                                      'email' => 'cluster@user.com',
                                      'country' => 'CA'));
        $clusteruser->save();

        // Copy the site-level course record from the real DB
        //$sitecourse = self::$origdb->get_record('course', array('id' => SITEID));
        //self::$overlaydb->import_record('course', $sitecourse);
        //var_dump($sitecourse);die();

        //set up our test role
        $syscontext = get_context_instance(CONTEXT_SYSTEM);
        $roleid = create_role('clusteradmin', 'clusteradmin', 'clusteradmin');
        assign_capability('elis/program:user_edit', CAP_ALLOW, $roleid, $syscontext->id);
        //assign the userset administrator an appropriate role on the userset
        $instance     = context_elis_userset::instance(1);
        role_assign($roleid, $USER->id, $instance->id);

        //assign the user to the user set
        $clusterassignment = new clusterassignment(array('clusterid' => 1,
                                                         'userid' => $clusteruser->id));
        $clusterassignment->save();
    }

    /**
     * Test the basic functionality of the methods for fetching and counting
     * users in relation to userset permissions
     */
    public function testUsermanagementGetsUsersRespectsUsersetPermissions() {
        global $USER, $DB;

        require_once(elispm::lib('lib.php'));

        //make sure we don't hit corner-cases with permissions
        set_config('siteguest', '');
        set_config('siteadmins', '');

        //prevent accesslib caching
        accesslib_clear_all_caches(true);

        //data setup
        $this->load_csv_data();
        $this->init_contexts_and_site_course();
        $this->set_up_users();

        //the context set our user set administrator has access to
        $context_set = pm_context_set::for_user_with_capability('cluster', 'elis/program:user_edit', $USER->id);

        //validate count
        $count = usermanagement_count_users(array(), $context_set);
        $this->assertEquals(1, $count);

        //validate record
        $users = usermanagement_get_users('name', 'ASC', 0, 0, array(), $context_set);
        $this->assertEquals(1, count($users));
        $user = reset($users);
        $this->assertEquals('clusteruser', $user->idnumber);
    }

    /**
     * Test the basic functionality of the methods for fetching users as a
     * recordset in relation to userset permissions
     */
    public function testUsermanagementGetsUsersRecordsetRespectsUsersetPermissions() {
        global $USER, $DB;

        require_once(elispm::lib('lib.php'));

        //make sure we don't hit corner-cases with permissions
        set_config('siteguest', '');
        set_config('siteadmins', '');

        //prevent accesslib caching
        accesslib_clear_all_caches(true);

        //data setup
        $this->load_csv_data();
        $this->init_contexts_and_site_course();
        $this->set_up_users();

        //the context set our user set administrator has access to
        $context_set = pm_context_set::for_user_with_capability('cluster', 'elis/program:user_edit', $USER->id);

        //validate record
        $users = usermanagement_get_users_recordset('name', 'ASC', 0, 0, array(), $context_set);
        $this->assertTrue($users->valid());
        $user = $users->current();
        $this->assertEquals('clusteruser', $user->idnumber);
        $this->assertNull($users->next());
    }

    /**
     * Test the basic functionality of the methods for fetching and counting
     * users when applying userset permissions and an appropriate SQL filter
     */
    public function testUsermanagementGetsUsersRespectsFilters() {
        global $USER, $DB;

        require_once(elispm::lib('data/clusterassignment.class.php'));
        require_once(elispm::lib('data/user.class.php'));
        require_once(elispm::lib('lib.php'));

        //make sure we don't hit corner-cases with permissions
        set_config('siteguest', '');
        set_config('siteadmins', '');

        //prevent accesslib caching
        accesslib_clear_all_caches(true);

        //data setup
        $this->load_csv_data();
        $this->init_contexts_and_site_course();
        $this->set_up_users();

        //assign a second user to the user set
        $secondclusteruser = new user(array('idnumber' => 'secondclusteruser',
                                            'username' => 'secondclusteruser',
                                            'firstname' => 'Secondcluster',
                                            'lastname' => 'User',
                                            'email' => 'secpmdcluster@user.com',
                                            'country' => 'CA'));
        $secondclusteruser->save();
        $clusterassignment = new clusterassignment(array('clusterid' => 1,
                                                         'userid' => $secondclusteruser->id));
        $clusterassignment->save();

        //the context set our user set administrator has access to
        $context_set = pm_context_set::for_user_with_capability('cluster', 'elis/program:user_edit', $USER->id);

        //add a filter to filter down to only our first test user
        $extrasql = array('username = :testusername', array('testusername' => 'clusteruser'));

        //validate count
        $count = usermanagement_count_users($extrasql, $context_set);
        $this->assertEquals(1, $count);

        //validate record
        $users = usermanagement_get_users('name', 'ASC', 0, 0, $extrasql, $context_set);
        $this->assertEquals(1, count($users));
        $user = reset($users);
        $this->assertEquals('clusteruser', $user->idnumber);
    }

    /**
     * Test the basic functionality of the methods for fetching users as a
     * recordset when applying userset permissions and an appropriate SQL filter
     */
    public function testUsermanagementGetsUsersRecordsetRespectsFilters() {
        global $USER, $DB;

        require_once(elispm::lib('data/clusterassignment.class.php'));
        require_once(elispm::lib('data/user.class.php'));
        require_once(elispm::lib('lib.php'));

        //make sure we don't hit corner-cases with permissions
        set_config('siteguest', '');
        set_config('siteadmins', '');

        //prevent accesslib caching
        accesslib_clear_all_caches(true);

        //data setup
        $this->load_csv_data();
        $this->init_contexts_and_site_course();
        $this->set_up_users();

        //assign a second user to the user set
        $secondclusteruser = new user(array('idnumber' => 'secondclusteruser',
                                            'username' => 'secondclusteruser',
                                            'firstname' => 'Secondcluster',
                                            'lastname' => 'User',
                                            'email' => 'secpmdcluster@user.com',
                                            'country' => 'CA'));
        $secondclusteruser->save();
        $clusterassignment = new clusterassignment(array('clusterid' => 1,
                                                         'userid' => $secondclusteruser->id));
        $clusterassignment->save();

        //the context set our user set administrator has access to
        $context_set = pm_context_set::for_user_with_capability('cluster', 'elis/program:user_edit', $USER->id);

        //add a filter to filter down to only our first test user
        $extrasql = array('username = :testusername', array('testusername' => 'clusteruser'));

        //validate record
        $users = usermanagement_get_users_recordset('name', 'ASC', 0, 0, $extrasql, $context_set);
        $this->assertTrue($users->valid());
        $user = $users->current();
        $this->assertEquals('clusteruser', $user->idnumber);
        $this->assertNull($users->next());
    }
}
