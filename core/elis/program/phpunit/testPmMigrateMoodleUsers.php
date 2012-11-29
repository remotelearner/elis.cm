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
require_once(dirname(__FILE__).'/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once(elispm::lib('lib.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));

/**
 * Class for testing that the pm_migrate_moodle_users function correctly
 * auto-assigns idnumbers and delegates to synchronization functionality
 */
class pmMigrateMoodleUsersTest extends elis_database_test {
    /**
     * Return the list of tables that should be overlayed.
     *
     * @return array The mapping of overlay tables to components
     */
    static protected function get_overlay_tables() {
        return array('config_plugins' => 'moodle',
                     'user' => 'moodle',
                     'user_info_data' => 'moodle',
                     user::TABLE => 'elis_program',
                     usermoodle::TABLE => 'elis_program',
                     'elis_field_data_char' => 'elis_core',
                     'elis_field_data_int' => 'elis_core',
                     'elis_field_data_num' => 'elis_core',
                     'elis_field_data_text' => 'elis_core'
               );
    }

    /**
     * Return the list of tables that should be ignored for writes.
     *
     * @return array The mapping of ignored table to components
     */
    static protected function get_ignored_tables() {
        return array('context' => 'moodle');
    }

    /**
     * Data provider for testing auto-assigning of idnumbers
     *
     * @return array An array containing users with their required information
     */
    function autoAssignIdnumberProvider() {
        global $CFG;

        $users = array();
        $users[] = array('username' => 'testuser1',
                         'deleted' => 0,
                         'confirmed' => 1,
                         'mnethostid' => $CFG->mnet_localhost_id);
        $users[] = array('username' => 'testuser2',
                         'deleted' => 0,
                         'confirmed' => 1,
                         'mnethostid' => $CFG->mnet_localhost_id);
        $users[] = array('username' => 'testuser3',
                         'deleted' => 0,
                         'confirmed' => 1,
                         'mnethostid' => $CFG->mnet_localhost_id);
        return array(array($users));
    }

    /**
     * Validate that our method correctly auto-assigns idnumbers when the
     * method parameter is set to true
     *
     * @param array An array containing users with their required information
     * @dataProvider autoAssignIdnumberProvider
     */
    public function testAutoAssignIdnumberViaMethodParameter($users) {
        global $DB;

        //make sure the config is not enabling the functionality
        set_config('auto_assign_user_idnumber', 0, 'elis_program');
        elis::$config = new elis_config();

        foreach ($users as $user) {
            //set up the provided users
            $DB->insert_record('user', $user);
        }

        //call the migration method
        pm_migrate_moodle_users(true);

        //count the number of users
        $count_idnumbers_set = $DB->count_records_select('user', 'username = idnumber');
        $this->assertEquals(3, $count_idnumbers_set);

        //validate that the usernames haven't been changed
        foreach ($users as $user) {
            $exists = $DB->record_exists('user', array('username' => $user['username']));
            $this->assertTrue($exists);
        }
    }

    /**
     * Validate that our method correctly auto-assigns idnumber for a particular
     * user when the method parameter is set to true
     *
     * @param array An array containing users with their required information
     * @dataProvider autoAssignIdnumberProvider
     */
    public function testAutoAssignIdnumberViaMethodParameterWhenUseridProvided($users) {
        global $DB;

        //make sure the config is not enabling the functionality
        set_config('auto_assign_user_idnumber', 0, 'elis_program');
        elis::$config = new elis_config();

        foreach ($users as $user) {
            //set up the provided users
            $DB->insert_record('user', $user);
        }

        //call the migration method
        pm_migrate_moodle_users(true, 0, 1);

        //we should have one user with an idnumber set to their username and
        //that user should be the first one
        $records = $DB->get_records_select('user', 'username = idnumber');
        $this->assertEquals(1, count($records));

        $user = reset($records);
        $this->assertEquals('testuser1', $user->username);
        $this->assertEquals('testuser1', $user->idnumber);
    }

    /**
     * Validate that our method correctly auto-assigns idnumbers when the
     * corresponding elis setting is set to true
     *
     * @param array An array containing users with their required information
     * @dataProvider autoAssignIdnumberProvider
     */
    public function testAutoAssignIdnumberViaMethodPMSetting($users) {
        global $DB;

        //enable functionality via the settong
        set_config('auto_assign_user_idnumber', 1, 'elis_program');
        elis::$config = new elis_config();

        foreach ($users as $user) {
            //set up the provided users
            $DB->insert_record('user', $user);
        }

        //call the migration method, making sure we're not enabling via method parameter
        pm_migrate_moodle_users();

        //count the number of users
        $count_idnumbers_set = $DB->count_records_select('user', 'username = idnumber');
        $this->assertEquals(3, $count_idnumbers_set);

        //validate that the usernames haven't been changed
        foreach ($users as $user) {
            $exists = $DB->record_exists('user', array('username' => $user['username']));
            $this->assertTrue($exists);
        }
    }

    /**
     * Validate that our method correctly auto-assigns idnumber for a particular
     * user when the corresponding elis setting is set to true
     *
     * @param array An array containing users with their required information
     * @dataProvider autoAssignIdnumberProvider
     */
    public function testAutoAssignIdnumberViaPMSettingWhenUseridProvided($users) {
        global $DB;

        //enable functionality via the settong
        set_config('auto_assign_user_idnumber', 1, 'elis_program');
        elis::$config = new elis_config();

        foreach ($users as $user) {
            //set up the provided users
            $DB->insert_record('user', $user);
        }

        //call the migration method, making sure we're not enabling via method parameter
        pm_migrate_moodle_users(false, 0, 1);

        //we should have one user with an idnumber set to their username and
        //that user should be the first one
        $records = $DB->get_records_select('user', 'username = idnumber');
        $this->assertEquals(1, count($records));

        $user = reset($records);
        $this->assertEquals('testuser1', $user->username);
        $this->assertEquals('testuser1', $user->idnumber);
    }

    /**
     * Validate that our method does not auto-assign idnumbers when the parameter
     * and setting are disabled
     *
     * @param array An array containing users with their required information
     * @dataProvider autoAssignIdnumberProvider
     */
    public function testAutoAssignIdnumberDisabledWhenSettingAndParameterDisabled($users) {
        global $DB;

        //make sure the config is not enabling the functionality
        set_config('auto_assign_user_idnumber', 0, 'elis_program');
        elis::$config = new elis_config();

        foreach ($users as $user) {
            //set up the provided users
            $DB->insert_record('user', $user);
        }

        //call the migration method
        pm_migrate_moodle_users();

        //count the number of users
        $count_idnumbers_set = $DB->count_records_select('user', 'username = idnumber');
        $this->assertEquals(0, $count_idnumbers_set);
    }

    /**
     * Validate that our method does not auto-assign idnumber for a particular user
     * when the parameter and setting are disabled
     *
     * @param array An array containing users with their required information
     * @dataProvider autoAssignIdnumberProvider
     */
    public function testAutoAssignIdnumberDisabledWhenSettingAndParameterDisabledWhenUseridProvided($users) {
        global $DB;

        //NOTE: this test does not specifically depend on the userid parameter
        //but is a valuable sanity check

        //make sure the config is not enabling the functionality
        set_config('auto_assign_user_idnumber', 0, 'elis_program');
        elis::$config = new elis_config();

        foreach ($users as $user) {
            //set up the provided users
            $DB->insert_record('user', $user);
        }

        //call the migration method
        pm_migrate_moodle_users(false, 0, 1);

        //count the number of users
        $count_idnumbers_set = $DB->count_records_select('user', 'username = idnumber');
        $this->assertEquals(0, $count_idnumbers_set);
    }

    /**
     * Data provider for testing auto-assigning of idnumbers in relation to the
     * guest user
     *
     * @return array An array containing users with their required information
     */
    function guestUserProvider() {
        global $CFG;

        $users = array();
        $users[] = array('username' => 'guest',
                         'deleted' => 0,
                         'confirmed' => 1,
                         'mnethostid' => $CFG->mnet_localhost_id);
        $users[] = array('username' => 'testuser1',
                         'deleted' => 0,
                         'confirmed' => 1,
                         'mnethostid' => $CFG->mnet_localhost_id);
        $users[] = array('username' => 'testuser2',
                         'deleted' => 0,
                         'confirmed' => 1,
                         'mnethostid' => $CFG->mnet_localhost_id);
        return array(array($users));  
    }

    /**
     * Validate that our method does not auto-assign an idnumber to the guest
     * user but does so for other normal users
     *
     * @param array An array containing users with their required information
     * @dataProvider guestUserProvider
     */
    public function testAutoAssignIdnumberIgnoresGuestUser($users) {
        global $DB;

        foreach ($users as $user) {
            //set up the provided users
            $DB->insert_record('user', $user);
        }

        //call the migration method
        pm_migrate_moodle_users(true);

        //count the number of users (should be everyone except the guest user)
        $count_idnumbers_set = $DB->count_records_select('user', 'username = idnumber');
        $this->assertEquals(2, $count_idnumbers_set);

        //validate that nobody has an idnumber of 'guest'
        $exists = $DB->record_exists('user', array('idnumber' => 'guest'));
        $this->assertFalse($exists);
    }

    /**
     * Validate that our method does not auto-assign an idnumber to the guest
     * user even when that user's userid is specifically specified
     *
     * @param array An array containing users with their required information
     * @dataProvider guestUserProvider
     */
    public function testAutoAssignIdnumberIgnoresGuestWhenUseridProvided($users) {
        global $DB;

        foreach ($users as $user) {
            //set up the provided users
            $DB->insert_record('user', $user);
        }

        //call the migration method
        pm_migrate_moodle_users(true, 0, 1);

        //count the number of users (should be nobody since we specifically indicated
        //to use the guest user and that user should not be migrated)
        $count_idnumbers_set = $DB->count_records_select('user', 'username = idnumber');
        $this->assertEquals(0, $count_idnumbers_set);
    }

    /**
     * Data provider for testing auto-assigning of idnumbers in relation to the
     * idnumber uniqueness / username - idnumber conflicts
     *
     * @return array An array containing users with their required information
     */
    function nonuniqueUserProvider() {
        global $CFG;

        $users = array();
        $users[] = array('username' => 'testuser1',
                         'idnumber' => 'testuser2',
                         'deleted' => 0,
                         'confirmed' => 1,
                         'mnethostid' => $CFG->mnet_localhost_id);
        $users[] = array('username' => 'testuser2',
                         'idnumber' => '',
                         'deleted' => 0,
                         'confirmed' => 1,
                         'mnethostid' => $CFG->mnet_localhost_id);
        $users[] = array('username' => 'testuser3',
                         'idnumber' => '',
                         'deleted' => 0,
                         'confirmed' => 1,
                         'mnethostid' => $CFG->mnet_localhost_id);
        return array(array($users));
    }

    /**
     * Validate that a user's idnumber is not auto-assigned from their username if
     * another user already has that value set as their idnumber
     *
     * @param array $users An array containing users with their required information
     * @dataProvider nonuniqueUserProvider
     */
    public function testAutoAssignIdnumberIgnoresNonuniquePotentialIdnumbers($users) {
        global $DB;

        foreach ($users as $user) {
            //set up the provided users
            $DB->insert_record('user', $user);
        }

        //call the migration method
        pm_migrate_moodle_users(true);

        //count the number of users (should only be the last user)
        $count_idnumbers_set = $DB->count_records_select('user', 'username = idnumber');
        $this->assertEquals(1, $count_idnumbers_set);

        //validate that it is indeed the last user
        $exists = $DB->record_exists('user', array('username' => 'testuser3',
                                                   'idnumber' => 'testuser3'));
        $this->assertTrue($exists);
    }

    /**
     * Validate that a particular user's idnumber is not auto-assigned from their
     * username if another user already has that value set as their idnumber
     *
     * @param array $users An array containing users with their required information
     * @dataProvider nonuniqueUserProvider
     */
    public function testAutoAssignIdnumberIgnoresNonuniquePotentialIdnumbersWhenUseridProvided($users) {
        global $DB;

        foreach ($users as $user) {
            //set up the provided users
            $DB->insert_record('user', $user);
        }

        //call the migration method
        pm_migrate_moodle_users(true, 0, 2);

        //count the number of users (should only be the last user)
        $count_idnumbers_set = $DB->count_records_select('user', 'username = idnumber');
        $this->assertEquals(0, $count_idnumbers_set);
    }

    /**
     * Data provider for migrating users from Moodle to the PM system
     *
     * @return array An array containing users with their required information
     */
    function userMigrationProvider() {
        global $CFG;

        $users = array();
        $users[] = array('username' => 'migrateuser1',
                         'firstname' => 'migrateuser1',
                         'lastname' => 'migrateuser1',
                         'email' => 'migrateuser@1.com',
                         'country' => 'CA',
                         'idnumber' => 'migrateuser1',
                         'deleted' => 0,
                         'confirmed' => 1,
                         'mnethostid' => $CFG->mnet_localhost_id);
        $users[] = array('username' => 'migrateuser2',
                         'firstname' => 'migrateuser2',
                         'lastname' => 'migrateuser2',
                         'email' => 'migrateuser@2.com',
                         'country' => 'CA',
                         'idnumber' => 'migrateuser2',
                         'deleted' => 0,
                         'confirmed' => 1,
                         'mnethostid' => $CFG->mnet_localhost_id);
        $users[] = array('username' => 'migrateuser3',
                         'firstname' => 'migrateuser3',
                         'lastname' => 'migrateuser3',
                         'email' => 'migrateuser@3.com',
                         'country' => 'CA',
                         'idnumber' => 'migrateuser3',
                         'deleted' => 0,
                         'confirmed' => 1,
                         'mnethostid' => $CFG->mnet_localhost_id);
        return array(array($users));
    }

    /**
     * Validate that users are correctly migrated and that the migration work
     * is not duplicated for already-migrated users
     *
     * @param array $users An array containing users with their required information
     * @dataProvider userMigrationProvider   
     */
    public function testUsersAreMigratedOnlyOnce($users) {
        //NOTE: not all scenarios are being tested - just concerned with preventing
        //duplicates and delegating to cluster_profile_update_handler

        global $DB;

        foreach ($users as $user) {
            //set up the provided users
            $DB->insert_record('user', $user);
        }

        //query to obtain our data set for testing
        $sql = "SELECT crlmu.id, crlmu.username, crlmu.idnumber
                FROM {".user::TABLE."} crlmu
                JOIN {".usermoodle::TABLE."} um
                  ON crlmu.id = um.cuserid
                JOIN {user} mdlu
                  ON um.muserid = mdlu.id
                ORDER BY mdlu.username";

        //run loop twice to make sure the migrate function doesn't do anything
        //on its second run for the same data set
        for ($i = 0; $i < 2; $i++) {
            //call the migration method
            pm_migrate_moodle_users(true);

            //validate record count
            $records = $DB->get_records_sql($sql);
            $this->assertEquals(3, count($records));

            //validate usernames and idnumbers
            $this->assertEquals('migrateuser1', $records[1]->username);
            $this->assertEquals('migrateuser1', $records[1]->idnumber);
            $this->assertEquals('migrateuser2', $records[2]->username);
            $this->assertEquals('migrateuser2', $records[2]->idnumber);
            $this->assertEquals('migrateuser3', $records[3]->username);
            $this->assertEquals('migrateuser3', $records[3]->idnumber);
        }
    }

    /**
     * Validate that a particular user is correctly migrated and that the migration
     * work is not duplicated for the alread-migrated user
     *
     * @param array $users An array containing users with their required information
     * @dataProvider userMigrationProvider
     */
    public function testUsersAreMigratedOnlyOnceWhenUseridProvided($users) {
        //NOTE: not all scenarios are being tested - just concerned with preventing
        //duplicates and delegating to cluster_profile_update_handler

        global $DB;

        foreach ($users as $user) {
            //set up the provided users
            $DB->insert_record('user', $user);
        }

        //query to obtain our data set for testing
        $sql = "SELECT crlmu.id, crlmu.username, crlmu.idnumber
                FROM {".user::TABLE."} crlmu
                JOIN {".usermoodle::TABLE."} um
                  ON crlmu.id = um.cuserid
                JOIN {user} mdlu
                  ON um.muserid = mdlu.id
                ORDER BY mdlu.username";

        //run loop twice to make sure the migrate function doesn't do anything
        //on its second run for the same data set
        for ($i = 0; $i < 2; $i++) {
            //call the migration method
            pm_migrate_moodle_users(true, 0, 1);

            //validate record count
            $records = $DB->get_records_sql($sql);
            $this->assertEquals(1, count($records));

            //validate usernames and idnumbers
            $user = reset($records);
            $this->assertEquals('migrateuser1', $user->username);
            $this->assertEquals('migrateuser1', $user->idnumber);
        }
    }

    /**
     * Data provider for migrating users from Moodle to the PM system
     *
     * @return array An array containing users with their required information
     */
    function timeModifiedProvider() {
        global $CFG;

        $users = array();
        //zero value will be reassigned to the current time
        $users[] = array('username' => 'timemodifieduser1',
                         'firstname' => 'timemodifieduser1',
                         'lastname' => 'timemodifieduser1',
                         'email' => 'timemodified@user1.com',
                         'country' => 'CA',
                         'idnumber' => 'timemodifieduser1',
                         'deleted' => 0,
                         'confirmed' => 1,
                         'timemodified' => 0,
                         'mnethostid' => $CFG->mnet_localhost_id);
        //value far in the past
        $users[] = array('username' => 'timemodifieduser2',
                         'firstname' => 'timemodifieduser2',
                         'lastname' => 'timemodifieduser2',
                         'email' => 'timemodified@user2.com',
                         'country' => 'CA',
                         'idnumber' => 'timemodifieduser2',
                         'deleted' => 0,
                         'confirmed' => 1,
                         'timemodified' => 1000000000,
                         'mnethostid' => $CFG->mnet_localhost_id);
        //our boundary condition
        $users[] = array('username' => 'timemodifieduser3',
                         'firstname' => 'timemodifieduser3',
                         'lastname' => 'timemodifieduser3',
                         'email' => 'timemodified@user3.com',
                         'country' => 'CA',
                         'idnumber' => 'timemodifieduser3',
                         'deleted' => 0,
                         'confirmed' => 1,
                         'timemodified' => 1300000000,
                         'mnethostid' => $CFG->mnet_localhost_id);
        //in the future
        $users[] = array('username' => 'timemodifieduser4',
                         'firstname' => 'timemodifieduser4',
                         'lastname' => 'timemodifieduser4',
                         'email' => 'timemodified@user4.com',
                         'country' => 'CA',
                         'idnumber' => 'timemodifieduser4',
                         'deleted' => 0,
                         'confirmed' => 1,
                         'timemodified' => 2000000000,
                         'mnethostid' => $CFG->mnet_localhost_id);
        return array(array($users));
    }

    /**
     * Validate that the migration functionality respects the appropriate time
     * parameter, and only migrates users who have been modified since that time
     * (or at that exact time)
     *
     * @param array $users An array containing users with their required information
     * @dataProvider timeModifiedProvider
     */
    public function testUserMigrationRespectsTimemodified($users) {
        global $DB;

        foreach ($users as $user) {
            //set up the provided users
            $DB->insert_record('user', $user);
        }

        //earliest possible time for zero to be reassigned to
        $earliest_time = time();

        //call the migration method, passing the boundary time
        pm_migrate_moodle_users(true, 1300000000);

        //earliest possible time for zero to be reassigned to
        $latest_time = time();

        //query to obtain our data set for testing
        $sql = "SELECT crlmu.id, crlmu.username, crlmu.idnumber, crlmu.timemodified
                FROM {".user::TABLE."} crlmu
                JOIN {".usermoodle::TABLE."} um
                  ON crlmu.id = um.cuserid
                JOIN {user} mdlu
                  ON um.muserid = mdlu.id
                ORDER BY mdlu.username";

        //validate record count
        $records = $DB->get_records_sql($sql);
        $this->assertEquals(3, count($records));

        //validate that everyone but the second user has been included
        $this->assertEquals('timemodifieduser1', $records[1]->username);
        $this->assertEquals('timemodifieduser1', $records[1]->idnumber);
        $this->assertEquals('timemodifieduser3', $records[2]->username);
        $this->assertEquals('timemodifieduser3', $records[2]->idnumber);
        $this->assertEquals('timemodifieduser4', $records[3]->username);
        $this->assertEquals('timemodifieduser4', $records[3]->idnumber);

        //validate that the time for the first user was auto-assigned
        $moodle_user = $DB->get_record('user', array('username' => 'timemodifieduser1'));
        $this->assertGreaterThanOrEqual($earliest_time, $moodle_user->timemodified);
        $this->assertLessThanOrEqual($latest_time, $moodle_user->timemodified);
    }

    /**
     * Validate that the migration functionality works for a particular user when
     * a valid time parameter is provided
     *
     * @param array $users An array containing users with their required information
     * @dataProvider timeModifiedProvider 
     */
    public function testUserMigrationSucceedsWithValidTimemodifiedAndUseridProvided($users) {
        global $DB;

        foreach ($users as $user) {
            //set up the provided users
            $DB->insert_record('user', $user);
        }

        //earliest possible time for zero to be reassigned to
        $earliest_time = time();

        //call the migration method, passing the boundary time
        pm_migrate_moodle_users(true, 1300000000, 1);

        //earliest possible time for zero to be reassigned to
        $latest_time = time();

        //query to obtain our data set for testing
        $sql = "SELECT crlmu.id, crlmu.username, crlmu.idnumber, crlmu.timemodified
                FROM {".user::TABLE."} crlmu
                JOIN {".usermoodle::TABLE."} um
                  ON crlmu.id = um.cuserid
                JOIN {user} mdlu
                  ON um.muserid = mdlu.id
                ORDER BY mdlu.username";

        //validate record count
        $records = $DB->get_records_sql($sql);
        $this->assertEquals(1, count($records));

        //validate that everyone but the second user has been included
        $user = reset($records);
        $this->assertEquals('timemodifieduser1', $user->username);
        $this->assertEquals('timemodifieduser1', $user->idnumber);

        //validate that the time for the first user was auto-assigned
        $moodle_user = $DB->get_record('user', array('username' => 'timemodifieduser1'));
        $this->assertGreaterThanOrEqual($earliest_time, $moodle_user->timemodified);
        $this->assertLessThanOrEqual($latest_time, $moodle_user->timemodified);
    }

    /**
     * Validate that the migration functionality ignores a particular user if
     * the time parameter would normally exclude them
     *
     * @param array $users An array containing users with their required information
     * @dataProvider timeModifiedProvider
     */
    public function testUserMigrationExcludesInvalidTimemodifiedWhenUseridProvided($users) {
        global $DB;

        foreach ($users as $user) {
            //set up the provided users
            $DB->insert_record('user', $user);
        }

        //earliest possible time for zero to be reassigned to
        $earliest_time = time();

        //call the migration method, passing the boundary time
        pm_migrate_moodle_users(true, 1300000000, 2);

        //earliest possible time for zero to be reassigned to
        $latest_time = time();

        //query to obtain our count
        $sql = "SELECT COUNT(*)
                FROM {".user::TABLE."} crlmu
                JOIN {".usermoodle::TABLE."} um
                  ON crlmu.id = um.cuserid
                JOIN {user} mdlu
                  ON um.muserid = mdlu.id
                ORDER BY mdlu.username";

        //validate record count
        $count = $DB->count_records_sql($sql);
        $this->assertEquals(0, $count);
    }
}
