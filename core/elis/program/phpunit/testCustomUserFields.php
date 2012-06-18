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

require_once(dirname(__FILE__) . '/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot . '/elis/program/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once(elis::lib('data/customfield.class.php'));
require_once(elis::file('core/fields/moodle_profile/custom_fields.php'));
require_once('PHPUnit/Extensions/Database/DataSet/CsvDataSet.php');
require_once(elispm::lib('deprecatedlib.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));
require_once($CFG->dirroot . '/user/profile/lib.php');

class userCustomFieldsTest extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

    protected static function get_overlay_tables() {
        return array(
            'context' => 'moodle',
            'course' => 'moodle',
            'events_queue' => 'moodle',
            'events_queue_handlers' => 'moodle',
            'user' => 'moodle',
            'user_info_category' => 'moodle',
            'user_info_field' => 'moodle',
            'user_info_data' => 'moodle',
            field::TABLE => 'elis_core',
            field_owner::TABLE => 'elis_core',
            field_category::TABLE => 'elis_core',
            field_data_int::TABLE => 'elis_core',
            field_data_num::TABLE => 'elis_core',
            field_data_char::TABLE => 'elis_core',
            field_data_text::TABLE => 'elis_core',
            field_contextlevel::TABLE => 'elis_core',
            field_category_contextlevel::TABLE => 'elis_core',
            user::TABLE => 'elis_program',
            usermoodle::TABLE => 'elis_program',
        );
    }

    protected function setUp() {
        global $DB;
        parent::setUp();
        $DB = self::$origdb; // setUpContextsTable needs $DB to be the real
                             // database for get_admin()
        $this->setUpContextsTable();
        $DB = self::$overlaydb;
    }


    /**
     * Set up the contexts table with the minimum that we need.
     */
    private function setUpContextsTable() {
        global $CFG;
        // system context
        $syscontext = self::$origdb->get_record('context', array('contextlevel' => CONTEXT_SYSTEM));
        self::$overlaydb->import_record('context', $syscontext);

        // site (front page) course
        $site = self::$origdb->get_record('course', array('id' => SITEID));
        self::$overlaydb->import_record('course', $site);
        $sitecontext = self::$origdb->get_record('context', array('contextlevel' => CONTEXT_COURSE,
                                                                  'instanceid' => SITEID));
        self::$overlaydb->import_record('context', $sitecontext);

        // primary admin user
        $admin = get_admin();
        if ($admin) {
            self::$overlaydb->import_record('user', $admin);
            $CFG->siteadmins = $admin->id;
            $usercontext = self::$origdb->get_record('context', array('contextlevel' => CONTEXT_USER,
                                                                      'instanceid' => $admin->id));
            self::$overlaydb->import_record('context', $usercontext);

            // copy admin user's ELIS user (if available)
            $elisuser = user::find(new field_filter('idnumber', $admin->idnumber), array(), 0, 0, self::$origdb);
            if ($elisuser->valid()) {
                $elisuser = $elisuser->current();
                self::$overlaydb->import_record(user::TABLE, $elisuser->to_object());
            }
        }
    }

    protected function load_csv_data() {
        // load initial data from a CSV file
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable('user', elis::component_file('program', 'phpunit/mdluser.csv'));
        $dataset->addTable('user_info_category', elis::component_file('program', 'phpunit/user_info_category.csv'));
        $dataset->addTable('user_info_field', elis::component_file('program', 'phpunit/user_info_field.csv'));
        $dataset->addTable('user_info_data', elis::component_file('program', 'phpunit/user_info_data.csv'));
        $dataset->addTable(user::TABLE, elis::component_file('program', 'phpunit/pmuser.csv'));
        $dataset->addTable(usermoodle::TABLE, elis::component_file('program', 'phpunit/usermoodle.csv'));
        $dataset->addTable(field_category::TABLE, elis::component_file('program', 'phpunit/user_field_category.csv'));
        $dataset->addTable(field::TABLE, elis::component_file('program', 'phpunit/user_field.csv'));
        $dataset->addTable(field_owner::TABLE, elis::component_file('program', 'phpunit/user_field_owner.csv'));
        $dataset = new PHPUnit_Extensions_Database_DataSet_ReplacementDataSet($dataset);
        $dataset->addSubStrReplacement('\n', "\n");
        load_phpunit_data_set($dataset, true, self::$overlaydb);

        // load field data next (we need the user context ID and context level)
        $usercontext = context_elis_user::instance(103);
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(field_contextlevel::TABLE, elis::component_file('program', 'phpunit/user_field_contextlevel.csv'));
        $dataset->addTable(field_category_contextlevel::TABLE, elis::component_file('program', 'phpunit/user_field_category_contextlevel.csv'));
        $dataset->addTable(field_data_int::TABLE, elis::component_file('program', 'phpunit/user_field_data_int.csv'));
        //we don't have any num field data
        //$dataset->addTable(field_data_num::TABLE, elis::component_file('program', 'phpunit/user_field_data_num.csv'));
        $dataset->addTable(field_data_char::TABLE, elis::component_file('program', 'phpunit/user_field_data_char.csv'));
        $dataset->addTable(field_data_text::TABLE, elis::component_file('program', 'phpunit/user_field_data_text.csv'));
        $dataset = new PHPUnit_Extensions_Database_DataSet_ReplacementDataSet($dataset);
        $dataset->addFullReplacement('##USERCTXID##', $usercontext->id);
        $dataset->addFullReplacement('##USERCTXLVL##', CONTEXT_ELIS_USER);
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    /**
     * Test sync-ing an ELIS User Profile field
     * to a DELETED Moodle User Profile field
     */
    public function testSyncPMUserFieldToDeletedMoodleProfileField() {
        global $CFG, $DB;
        require_once($CFG->dirroot .'/user/profile/definelib.php');
        //PHPUnit_Framework_Error_Notice::$enabled = true;
        //PHPUnit_Framework_Error_Warning::$enabled = true;
        $this->load_csv_data();

        // Set PM Custom User field(s) to Sync to Moodle
        $ctxlvl = CONTEXT_ELIS_USER;
        $fields = field::get_for_context_level($ctxlvl);
        foreach ($fields as $field) {
            $fieldobj = new field($field);
            if (!isset($fieldobj->owners['moodle_profile'])) {
                $fieldobj->owners['moodle_profile'] = new stdClass;
            }
            $owner = new field_owner($fieldobj->owners['moodle_profile']);
            $owner->exclude = pm_moodle_profile::sync_from_moodle;
            $owner->save();
            $fieldobj->save();
        }

        // read a record
        $src = new user(103, null, array(), false, array(), self::$overlaydb);
        $src->reset_custom_field_list();
        // modify the data
        $src->firstname = 'Testuser';
        $src->lastname = 'One';
        $src->field_sometext = 'boo';
        $src->field_sometextfrompm = 'bla';
        $src->save();

        // Delete some custom Moodle Profile field(s)
        // to cause old error (pre ELIS-4499)
        $fields = field::get_for_context_level($ctxlvl);
        foreach ($fields as $field) {
            $fieldobj = new field($field);
            //echo "Get Moodle field for PM field '{$fieldobj->shortname}'\n";
            if ($moodle_field = $DB->get_record('user_info_field',
                                         array('shortname' => $fieldobj->shortname)
                                )) {
                //echo "Deleting Moodle Profile field ID = {$moodle_field->id}\n";
                profile_delete_field($moodle_field->id);
            }
        }

        // Run the library sync - throws errors not exceptions :(
        $CFG->mnet_localhost_id = 1; // ???
        $mu = cm_get_moodleuser(103);
        //print_object($mu);
        try {
            $result = pm_moodle_user_to_pm($mu);
            $this->assertTrue($result);
        } catch (Exception $ex) {
            $this->assertTrue(false, $ex->message);
        }
    }
}

