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
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));

/**
 * Class for testing that the pm_migrate_moodle_users function correctly auto-assigns idnumbers and delegates to synchronization
 * functionality
 * @group elis_program
 */
class pmmigratemoodleusers_testcase extends elis_database_test {

    /**
     * Data provider for testing auto-assigning of idnumbers
     * @return array An array containing users with their required information
     */
    public function dataprovider_autoassignidnumber() {
        global $CFG;

        $users = array(
                array(
                    'username' => 'testuser1',
                    'deleted' => 0,
                    'confirmed' => 1,
                    'mnethostid' => $CFG->mnet_localhost_id
                ),
                array(
                    'username' => 'testuser2',
                    'deleted' => 0,
                    'confirmed' => 1,
                    'mnethostid' => $CFG->mnet_localhost_id
                ),
                array(
                    'username' => 'testuser3',
                    'deleted' => 0,
                    'confirmed' => 1,
                    'mnethostid' => $CFG->mnet_localhost_id
                )
        );
        return array(array($users));
    }

    /**
     * Validate that our method correctly auto-assigns idnumbers when the method parameter is set to true.
     *
     * @dataProvider dataprovider_autoassignidnumber
     * @param array An array containing users with their required information
     */
    public function test_autoassignidnumberviamethodparameter1($users) {
        global $DB;

        // Make sure the config is not enabling the functionality.
        set_config('auto_assign_user_idnumber', 0, 'elis_program');
        elis::$config = new elis_config();

        foreach ($users as $user) {
            // Set up the provided users.
            $DB->insert_record('user', (object)$user);
        }

        // Call the migration method.
        pm_migrate_moodle_users(true);

        // Count the number of users.
        $this->assertEquals(3, $DB->count_records_select('user', 'username = idnumber AND username LIKE "testuser%"'));

        // Validate that the usernames haven't been changed.
        foreach ($users as $user) {
            $exists = $DB->record_exists('user', array('username' => $user['username']));
            $this->assertTrue($exists);
        }
    }

    /**
     * Validate that our method correctly auto-assigns idnumber for a particular user when the method parameter is set to true.
     *
     * @dataProvider dataprovider_autoassignidnumber
     * @param array An array containing users with their required information
     */
    public function test_autoassignidnumberviamethodparameterwhenuseridprovided($users) {
        global $DB;

        // Make sure the config is not enabling the functionality.
        set_config('auto_assign_user_idnumber', 0, 'elis_program');
        elis::$config = new elis_config();

        foreach ($users as $i => $user) {
            // Set up the provided users.
            $users[$i]['id'] = $DB->insert_record('user', (object)$user);
        }

        // Call the migration method.
        pm_migrate_moodle_users(true, 0, $users[0]['id']);

        // We should have one user with an idnumber set to their username and that user should be the first one.
        $records = $DB->get_records_select('user', 'username = idnumber AND username LIKE "testuser%"');
        $this->assertEquals(1, count($records));

        $user = reset($records);
        $this->assertEquals('testuser1', $user->username);
        $this->assertEquals('testuser1', $user->idnumber);
    }

    /**
     * Validate that our method correctly auto-assigns idnumbers when the corresponding elis setting is set to true.
     *
     * @dataProvider dataprovider_autoassignidnumber
     * @param array An array containing users with their required information
     */
    public function test_autoassignidnumberviamethodpmsetting($users) {
        global $DB;

        // Enable functionality via the settong.
        set_config('auto_assign_user_idnumber', 1, 'elis_program');
        elis::$config = new elis_config();

        foreach ($users as $user) {
            // Set up the provided users.
            $DB->insert_record('user', (object)$user);
        }

        // Call the migration method, making sure we're not enabling via method parameter.
        pm_migrate_moodle_users();

        // Count the number of users.
        $this->assertEquals(3, $DB->count_records_select('user', 'username = idnumber AND username LIKE "testuser%"'));

        // Validate that the usernames haven't been changed.
        foreach ($users as $user) {
            $exists = $DB->record_exists('user', array('username' => $user['username']));
            $this->assertTrue($exists);
        }
    }

    /**
     * Validate that our method correctly auto-assigns idnumber for a user when the corresponding elis setting is set to true.
     *
     * @dataProvider dataprovider_autoassignidnumber
     * @param array An array containing users with their required information
     */
    public function test_autoassignidnumberviapmsettingwhenuseridprovided($users) {
        global $DB;

        // Enable functionality via the settong.
        set_config('auto_assign_user_idnumber', 1, 'elis_program');
        elis::$config = new elis_config();

        foreach ($users as $i => $user) {
            // Set up the provided users.
            $users[$i]['id'] = $DB->insert_record('user', (object)$user);
        }

        // Call the migration method, making sure we're not enabling via method parameter.
        pm_migrate_moodle_users(false, 0, $users[0]['id']);

        // We should have one user with an idnumber set to their username and that user should be the first one.
        $records = $DB->get_records_select('user', 'username = idnumber AND username LIKE "testuser%"');
        $this->assertEquals(1, count($records));

        $user = reset($records);
        $this->assertEquals('testuser1', $user->username);
        $this->assertEquals('testuser1', $user->idnumber);
    }

    /**
     * Validate that our method does not auto-assign idnumbers when the parameter and setting are disabled.
     *
     * @dataProvider dataprovider_autoassignidnumber
     * @param array An array containing users with their required information
     */
    public function test_autoassignidnumberdisabledwhensettingandparameterdisabled($users) {
        global $DB;

        // Make sure the config is not enabling the functionality.
        set_config('auto_assign_user_idnumber', 0, 'elis_program');
        elis::$config = new elis_config();

        foreach ($users as $user) {
            // Set up the provided users.
            $DB->insert_record('user', (object)$user);
        }

        // Call the migration method.
        pm_migrate_moodle_users();

        // Count the number of users.
        $this->assertEquals(0, $DB->count_records_select('user', 'username = idnumber'));
    }

    /**
     * Validate that our method does not auto-assign idnumber for a particular user when the parameter and setting are disabled.
     *
     * @dataProvider dataprovider_autoassignidnumber
     * @param array An array containing users with their required information
     */
    public function test_autoassignidnumberdisabledwhensettingandparameterdisabledwhenuseridprovided($users) {
        global $DB;

        // NOTE: this test does not specifically depend on the userid parameter but is a valuable sanity check.

        // Make sure the config is not enabling the functionality.
        set_config('auto_assign_user_idnumber', 0, 'elis_program');
        elis::$config = new elis_config();

        foreach ($users as $i => $user) {
            // Set up the provided users.
            $users[$i]['id'] = $DB->insert_record('user', (object)$user);
        }

        // Call the migration method.
        pm_migrate_moodle_users(false, 0, $users[0]['id']);

        // Count the number of users.
        $this->assertEquals(0, $DB->count_records_select('user', 'username = idnumber'));
    }

    /**
     * Data provider for testing auto-assigning of idnumbers in relation to the guest user.
     * @return array An array containing users with their required information
     */
    public function dataprovider_guestuser() {
        global $CFG;

        $users = array(
                array(
                    'username' => 'testuser1',
                    'deleted' => 0,
                    'confirmed' => 1,
                    'mnethostid' => $CFG->mnet_localhost_id
                ),
                array(
                    'username' => 'testuser2',
                    'deleted' => 0,
                    'confirmed' => 1,
                    'mnethostid' => $CFG->mnet_localhost_id
                )
        );
        return array(array($users));
    }

    /**
     * Validate that our method does not auto-assign an idnumber to the guest user but does so for other normal users.
     *
     * @dataProvider dataprovider_guestuser
     * @param array An array containing users with their required information
     */
    public function test_autoassignidnumberignoresguestuser($users) {
        global $DB;

        foreach ($users as $user) {
            // Set up the provided users.
            $DB->insert_record('user', (object)$user);
        }

        // Call the migration method.
        pm_migrate_moodle_users(true);

        // Count the number of users (should be everyone except the guest user).
        $this->assertEquals(2, $DB->count_records_select('user', 'username = idnumber AND username != "admin"'));

        // Validate that nobody has an idnumber of 'guest'.
        $exists = $DB->record_exists('user', array('idnumber' => 'guest'));
        $this->assertFalse($exists);
    }

    /**
     * Validate that our method does not auto-assign an idnumber to the guest user even when that user's userid is specifically
     * specified
     *
     * @dataProvider dataprovider_guestuser
     * @param array An array containing users with their required information
     */
    public function test_autoassignidnumberignoresguestwhenuseridprovided($users) {
        global $DB;

        foreach ($users as $i => $user) {
            // Set up the provided users.
            $users[$i]['id'] = $DB->insert_record('user', (object)$user);
        }

        // Call the migration method.
        pm_migrate_moodle_users(true, 0, 1);

        // Count the number of users.
        // (should be nobody since we specifically indicated to use the guest user and that user should not be migrated).
        $this->assertEquals(0, $DB->count_records_select('user', 'username = idnumber AND username != "admin"'));
    }

    /**
     * Data provider for testing auto-assigning of idnumbers in relation to the idnumber uniqueness / username - idnumber conflicts
     * @return array An array containing users with their required information
     */
    public function dataprovider_nonuniqueuser() {
        global $CFG;

        $users = array(
                array(
                    'username' => 'testuser1',
                    'idnumber' => 'testuser2',
                    'deleted' => 0,
                    'confirmed' => 1,
                    'mnethostid' => $CFG->mnet_localhost_id
                ),
                array(
                    'username' => 'testuser2',
                    'idnumber' => '',
                    'deleted' => 0,
                    'confirmed' => 1,
                    'mnethostid' => $CFG->mnet_localhost_id
                ),
                array(
                    'username' => 'testuser3',
                    'idnumber' => '',
                    'deleted' => 0,
                    'confirmed' => 1,
                    'mnethostid' => $CFG->mnet_localhost_id
                )
        );
        return array(array($users));
    }

    /**
     * Validate that a user's idnumber is not auto-assigned from their username if another user already has that value set as their
     * idnumber.
     *
     * @dataProvider dataprovider_nonuniqueuser
     * @param array $users An array containing users with their required information
     */
    public function test_autoassignidnumberignoresnonuniquepotentialidnumbers($users) {
        global $DB;

        foreach ($users as $user) {
            // Set up the provided users.
            $DB->insert_record('user', (object)$user);
        }

        // Call the migration method.
        pm_migrate_moodle_users(true);

        // Count the number of users (should only be the last user).
        $this->assertEquals(1, $DB->count_records_select('user', 'username = idnumber AND username LIKE "testuser%"'));

        // Validate that it is indeed the last user.
        $exists = $DB->record_exists('user', array('username' => 'testuser3', 'idnumber' => 'testuser3'));
        $this->assertTrue($exists);
    }

    /**
     * Validate that a particular user's idnumber is not auto-assigned from their username if another user already has that value
     * set as their idnumber.
     *
     * @dataProvider dataprovider_nonuniqueuser
     * @param array $users An array containing users with their required information
     */
    public function test_autoassignidnumberignoresnonuniquepotentialidnumberswhenuseridprovided($users) {
        global $DB;

        foreach ($users as $i => $user) {
            // Set up the provided users.
            $users[$i]['id'] = $DB->insert_record('user', (object)$user);
        }

        // Call the migration method.
        pm_migrate_moodle_users(true, 0, $users[1]['id']);

        // Count the number of users (should only be the last user).
        $this->assertEquals(0, $DB->count_records_select('user', 'username = idnumber AND username LIKE "testuser%"'));
    }

    /**
     * Data provider for migrating users from Moodle to the PM system.
     * @return array An array containing users with their required information
     */
    public function dataprovider_usermigration() {
        global $CFG;

        $users = array(
            array(
                'username' => 'migrateuser1',
                'firstname' => 'migrateuser1',
                'lastname' => 'migrateuser1',
                'email' => 'migrateuser@1.com',
                'country' => 'CA',
                'idnumber' => 'migrateuser1',
                'deleted' => 0,
                'confirmed' => 1,
                'mnethostid' => $CFG->mnet_localhost_id
            ),
            array(
                'username' => 'migrateuser2',
                'firstname' => 'migrateuser2',
                'lastname' => 'migrateuser2',
                'email' => 'migrateuser@2.com',
                'country' => 'CA',
                'idnumber' => 'migrateuser2',
                'deleted' => 0,
                'confirmed' => 1,
                'mnethostid' => $CFG->mnet_localhost_id
            ),
            array(
                'username' => 'migrateuser3',
                'firstname' => 'migrateuser3',
                'lastname' => 'migrateuser3',
                'email' => 'migrateuser@3.com',
                'country' => 'CA',
                'idnumber' => 'migrateuser3',
                'deleted' => 0,
                'confirmed' => 1,
                'mnethostid' => $CFG->mnet_localhost_id
            )
        );
        return array(array($users));
    }

    /**
     * Validate that users are correctly migrated and that the migration work is not duplicated for already-migrated users.
     *
     * @dataProvider dataprovider_usermigration
     * @param array $users An array containing users with their required information
     */
    public function test_usersaremigratedonlyonce($users) {

        // NOTE: not all scenarios are being tested - only preventing duplicates and delegating to cluster_profile_update_handler.

        global $DB;

        foreach ($users as $user) {
            // Set up the provided users.
            $DB->insert_record('user', (object)$user);
        }

        // Query to obtain our data set for testing.
        $sql = "SELECT crlmu.id, crlmu.username, crlmu.idnumber
                  FROM {".user::TABLE."} crlmu
                  JOIN {".usermoodle::TABLE."} um ON crlmu.id = um.cuserid
                  JOIN {user} mdlu ON um.muserid = mdlu.id
              ORDER BY mdlu.username";

        // Run loop twice to make sure the migrate function doesn't do anything on its second run for the same data set.
        for ($i = 0; $i < 2; $i++) {
            // Call the migration method.
            pm_migrate_moodle_users(true);

            // Validate record count.
            $records = $DB->get_records_sql($sql);
            $this->assertEquals(3, count($records));

            // Validate usernames and idnumbers.
            $this->assertEquals('migrateuser1', $records[1]->username);
            $this->assertEquals('migrateuser1', $records[1]->idnumber);
            $this->assertEquals('migrateuser2', $records[2]->username);
            $this->assertEquals('migrateuser2', $records[2]->idnumber);
            $this->assertEquals('migrateuser3', $records[3]->username);
            $this->assertEquals('migrateuser3', $records[3]->idnumber);
        }
    }

    /**
     * Validate that a user is correctly migrated and that the migration work is not duplicated for the alread-migrated user.
     *
     * @dataProvider dataprovider_usermigration
     * @param array $users An array containing users with their required information
     */
    public function test_usersaremigratedonlyoncewhenuseridprovided($users) {
        // NOTE: not all scenarios are being tested - only preventing duplicates and delegating to cluster_profile_update_handler.

        global $DB;

        foreach ($users as $i => $user) {
            // Set up the provided users.
            $users[$i]['id'] = $DB->insert_record('user', (object)$user);
        }

        // Query to obtain our data set for testing.
        $sql = "SELECT crlmu.id, crlmu.username, crlmu.idnumber
                  FROM {".user::TABLE."} crlmu
                  JOIN {".usermoodle::TABLE."} um ON crlmu.id = um.cuserid
                  JOIN {user} mdlu ON um.muserid = mdlu.id
              ORDER BY mdlu.username";

        // Run loop twice to make sure the migrate function doesn't do anything on its second run for the same data set.
        for ($i = 0; $i < 2; $i++) {
            // Call the migration method.
            pm_migrate_moodle_users(true, 0, $users[0]['id']);

            // Validate record count.
            $records = $DB->get_records_sql($sql);
            $this->assertEquals(1, count($records));

            // Validate usernames and idnumbers.
            $user = reset($records);
            $this->assertEquals('migrateuser1', $user->username);
            $this->assertEquals('migrateuser1', $user->idnumber);
        }
    }

    /**
     * Data provider for migrating users from Moodle to the PM system.
     * @return array An array containing users with their required information
     */
    public function dataprovider_timemodified() {
        global $CFG;

        $users = array(
                // Zero value will be reassigned to the current time.
                array(
                    'username' => 'timemodifieduser1',
                    'firstname' => 'timemodifieduser1',
                    'lastname' => 'timemodifieduser1',
                    'email' => 'timemodified@user1.com',
                    'country' => 'CA',
                    'idnumber' => 'timemodifieduser1',
                    'deleted' => 0,
                    'confirmed' => 1,
                    'timemodified' => 0,
                    'mnethostid' => $CFG->mnet_localhost_id
                ),
                // Value far in the past.
                array(
                    'username' => 'timemodifieduser2',
                    'firstname' => 'timemodifieduser2',
                    'lastname' => 'timemodifieduser2',
                    'email' => 'timemodified@user2.com',
                    'country' => 'CA',
                    'idnumber' => 'timemodifieduser2',
                    'deleted' => 0,
                    'confirmed' => 1,
                    'timemodified' => 1000000000,
                    'mnethostid' => $CFG->mnet_localhost_id
                ),
                // Our boundary condition.
                array(
                    'username' => 'timemodifieduser3',
                    'firstname' => 'timemodifieduser3',
                    'lastname' => 'timemodifieduser3',
                    'email' => 'timemodified@user3.com',
                    'country' => 'CA',
                    'idnumber' => 'timemodifieduser3',
                    'deleted' => 0,
                    'confirmed' => 1,
                    'timemodified' => 1300000000,
                    'mnethostid' => $CFG->mnet_localhost_id
                ),
                // In the future.
                array(
                    'username' => 'timemodifieduser4',
                    'firstname' => 'timemodifieduser4',
                    'lastname' => 'timemodifieduser4',
                    'email' => 'timemodified@user4.com',
                    'country' => 'CA',
                    'idnumber' => 'timemodifieduser4',
                    'deleted' => 0,
                    'confirmed' => 1,
                    'timemodified' => 2000000000,
                    'mnethostid' => $CFG->mnet_localhost_id
                ),
        );
        return array(array($users));
    }

    /**
     * Validate that the migration functionality respects the appropriate time parameter, and only migrates users who have been
     * modified since that time (or at that exact time)
     *
     * @dataProvider dataprovider_timemodified
     * @param array $users An array containing users with their required information
     */
    public function test_usermigrationrespectstimemodified($users) {
        global $DB;

        foreach ($users as $user) {
            // Set up the provided users.
            $DB->insert_record('user', (object)$user);
        }

        // Earliest possible time for zero to be reassigned to.
        $earliesttime = time();

        // Call the migration method, passing the boundary time.
        pm_migrate_moodle_users(true, 1300000000);

        // Earliest possible time for zero to be reassigned to.
        $latesttime = time();

        // Query to obtain our data set for testing.
        $sql = "SELECT crlmu.id, crlmu.username, crlmu.idnumber, crlmu.timemodified
                  FROM {".user::TABLE."} crlmu
                  JOIN {".usermoodle::TABLE."} um ON crlmu.id = um.cuserid
                  JOIN {user} mdlu ON um.muserid = mdlu.id
              ORDER BY mdlu.username";

        // Validate record count.
        $records = $DB->get_records_sql($sql);
        $this->assertEquals(3, count($records));

        // Validate that everyone but the second user has been included.
        $this->assertEquals('timemodifieduser1', $records[1]->username);
        $this->assertEquals('timemodifieduser1', $records[1]->idnumber);
        $this->assertEquals('timemodifieduser3', $records[2]->username);
        $this->assertEquals('timemodifieduser3', $records[2]->idnumber);
        $this->assertEquals('timemodifieduser4', $records[3]->username);
        $this->assertEquals('timemodifieduser4', $records[3]->idnumber);

        // Validate that the time for the first user was auto-assigned.
        $moodleuser = $DB->get_record('user', array('username' => 'timemodifieduser1'));
        $this->assertGreaterThanOrEqual($earliesttime, $moodleuser->timemodified);
        $this->assertLessThanOrEqual($latesttime, $moodleuser->timemodified);
    }

    /**
     * Validate that the migration functionality works for a particular user when a valid time parameter is provided.
     *
     * @dataProvider dataprovider_timemodified
     * @param array $users An array containing users with their required information
     */
    public function test_usermigrationsucceedswithvalidtimemodifiedanduseridprovided($users) {
        global $DB;

        foreach ($users as $i => $user) {
            // Set up the provided users.
            $users[$i]['id'] = $DB->insert_record('user', (object)$user);
        }

        // Earliest possible time for zero to be reassigned to.
        $earliesttime = time();

        // Call the migration method, passing the boundary time.
        pm_migrate_moodle_users(true, 1300000000, $users[0]['id']);

        // Earliest possible time for zero to be reassigned to.
        $latesttime = time();

        // Query to obtain our data set for testing.
        $sql = "SELECT crlmu.id, crlmu.username, crlmu.idnumber, crlmu.timemodified
                  FROM {".user::TABLE."} crlmu
                  JOIN {".usermoodle::TABLE."} um ON crlmu.id = um.cuserid
                  JOIN {user} mdlu ON um.muserid = mdlu.id
              ORDER BY mdlu.username";

        // Validate record count.
        $records = $DB->get_records_sql($sql);
        $this->assertEquals(1, count($records));

        // Validate that everyone but the second user has been included.
        $user = reset($records);
        $this->assertEquals('timemodifieduser1', $user->username);
        $this->assertEquals('timemodifieduser1', $user->idnumber);

        // Validate that the time for the first user was auto-assigned.
        $moodleuser = $DB->get_record('user', array('username' => 'timemodifieduser1'));
        $this->assertGreaterThanOrEqual($earliesttime, $moodleuser->timemodified);
        $this->assertLessThanOrEqual($latesttime, $moodleuser->timemodified);
    }

    /**
     * Validate that the migration functionality ignores a particular user if the time parameter would normally exclude them.
     *
     * @dataProvider dataprovider_timemodified
     * @param array $users An array containing users with their required information
     */
    public function test_usermigrationexcludesinvalidtimemodifiedwhenuseridprovided($users) {
        global $DB;

        foreach ($users as $i => $user) {
            // Set up the provided users.
            $users[$i]['id'] = $DB->insert_record('user', (object)$user);
        }

        // Earliest possible time for zero to be reassigned to.
        $earliesttime = time();

        // Call the migration method, passing the boundary time.
        pm_migrate_moodle_users(true, 1300000000, $users[1]['id']);

        // Earliest possible time for zero to be reassigned to.
        $latesttime = time();

        // Query to obtain our count.
        $sql = "SELECT COUNT(*)
                  FROM {".user::TABLE."} crlmu
                  JOIN {".usermoodle::TABLE."} um ON crlmu.id = um.cuserid
                  JOIN {user} mdlu ON um.muserid = mdlu.id
              ORDER BY mdlu.username";

        // Validate record count.
        $count = $DB->count_records_sql($sql);
        $this->assertEquals(0, $count);
    }
}