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
 * @package    elis
 * @subpackage program
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 */

if (!isset($_SERVER['HTTP_USER_AGENT'])) {
    define('CLI_SCRIPT', true);
}

require_once(dirname(__FILE__).'/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once(elispm::lib('lib.php'));
require_once(elispm::file('phpunit/datagenerator.php'));
require_once(elispm::lib('data/certificateissued.class.php'));

// The data class below is required because pm_issue_user_certificate()
// Uses a function that checks for duplicate certificate codes in the
// Curriculum assignment table.  When all of the code for certificates is
// removed from the curriculum student data class, the class below will
// not be required.
require_once(elispm::lib('data/curriculumstudent.class.php'));
require_once(elispm::lib('certificate.php'));

/**
 * PHPUnit test to retrieve a user's certificates
 */
class issue_user_certificate_test extends elis_database_test {
    /**
     * @var array Array of globals that will be backed-up/restored for each test.
     */
    protected $backupGlobalsBlacklist = array('DB');

    /**
     * Setup overlay tables array.
     */
    protected static function get_overlay_tables() {
        return array(
            certificateissued::TABLE => 'elis_program'
        );
    }

    /**
     * This function tests issuing a user a certificate by inserting the record in the crlm_certificate_issued table
     */
    public function test_issue_user_certificate() {
        $certsettingid = 1;
        $user = array();
        $user[0] = new stdClass();
        $user[0]->userid = 20;
        $user[0]->completetime = '1357851284';
        $certissued = new certificateissued();

        $result = pm_issue_user_certificate($certsettingid, $user, $certissued);

        $record = self::$overlaydb->get_record('crlm_certificate_issued', array('timeissued' => '1357851284'));

        $conditions = array('cm_userid' => 20, 'cert_setting_id' => 1, 'timeissued' => 1357851284);
        self::$overlaydb->delete_records('crlm_certificate_issued', $conditions);

        // Cannot test for the certificate code or time created, because both values are generated at runtime.
        $expected = array();
        $expected['cm_userid'] = 20;
        $expected['cert_setting_id'] = 1;
        $expected['timeissued'] = 1357851284;

        foreach ($expected as $key => $data) {
            $this->assertObjectHasAttribute($key, $record);
            $this->assertEquals($data, $record->$key);
        }

        $this->assertEquals(true, $result);
    }

    /**
     * This function tests issuing a user a certificate with an empty user
     */
    public function test_issue_user_certificate_empty_user_set_fail() {
        $certsettingid = 1;
        $user = array();

        $certissued = new certificateissued();

        $result = pm_issue_user_certificate($certsettingid, $user, $certissued);

        $record = self::$overlaydb->get_record('crlm_certificate_issued', array('timeissued' => '1357851284'));

        $this->assertEquals(false, $record);
        $this->assertEquals(true, $result);

    }

    /**
     * This function tests issuing a user a certificate with an empty certificate setting id
     */
    public function test_issue_user_certificate_empty_cert_setting_fail() {
        $certsettingid = 0;
        $user = array();
        $user[0] = new stdClass();
        $user[0]->userid = 20;
        $user[0]->completetime = '1357851284';
        $certissued = new certificateissued();

        $result = pm_issue_user_certificate($certsettingid, $user, $certissued);

        $record = self::$overlaydb->get_record('crlm_certificate_issued', array('timeissued' => '1357851284'));

        $this->assertEquals(false, $record);
        $this->assertEquals(false, $result);

    }

    /**
     * This function tests issuing a user a certificate with an illegit data class
     */
    public function test_issue_user_certificate_illegit_data_class_fail() {
        $certsettingid = 1;
        $user = array();
        $user[0] = new stdClass();
        $user[0]->userid = 20;
        $user[0]->completetime = '1357851284';
        $certissued = new stdClass();

        $result = pm_issue_user_certificate($certsettingid, $user, $certissued);

        $record = self::$overlaydb->get_record('crlm_certificate_issued', array('timeissued' => '1357851284'));

        $this->assertEquals(false, $record);
        $this->assertEquals(false, $result);
    }
}