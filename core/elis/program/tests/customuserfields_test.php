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

// ELIS libs.
require_once(elis::lib('data/customfield.class.php'));
require_once(elis::file('core/fields/moodle_profile/custom_fields.php'));
require_once(elispm::lib('deprecatedlib.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));
require_once($CFG->dirroot.'/user/profile/lib.php');

/**
 * Test user custom fields.
 * @group elis_program
 */
class usercustomfields_testcase extends elis_database_test {

    /**
     * Load initial data from CSVs.
     */
    protected function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            'user' => elis::component_file('program', 'tests/fixtures/mdluser.csv'),
            'user_info_field' => elis::component_file('program', 'tests/fixtures/user_info_field.csv'),
            'user_info_data' => elis::component_file('program', 'tests/fixtures/user_info_data.csv'),
            user::TABLE => elis::component_file('program', 'tests/fixtures/pmuser.csv'),
            usermoodle::TABLE => elis::component_file('program', 'tests/fixtures/usermoodle.csv'),
            field::TABLE => elis::component_file('program', 'tests/fixtures/user_field.csv'),
            field_owner::TABLE => elis::component_file('program', 'tests/fixtures/user_field_owner.csv'),
        ));
        $dataset = new PHPUnit_Extensions_Database_DataSet_ReplacementDataSet($dataset);
        $dataset->addSubStrReplacement('\n', "\n");
        $this->loadDataSet($dataset);

        // Load field data next (we need the user context ID and context level).
        $usercontext = context_elis_user::instance(103);

        $dataset = $this->createCsvDataSet(array(
            field_contextlevel::TABLE => elis::component_file('program', 'tests/fixtures/user_field_contextlevel.csv'),
            field_category_contextlevel::TABLE => elis::component_file('program', 'tests/fixtures/user_field_category_contextlevel.csv'),
            field_data_int::TABLE => elis::component_file('program', 'tests/fixtures/user_field_data_int.csv'),
            field_data_char::TABLE => elis::component_file('program', 'tests/fixtures/user_field_data_char.csv'),
            field_data_text::TABLE => elis::component_file('program', 'tests/fixtures/user_field_data_text.csv'),
        ));
        $dataset = new PHPUnit_Extensions_Database_DataSet_ReplacementDataSet($dataset);
        $dataset->addFullReplacement('##USERCTXID##', $usercontext->id);
        $dataset->addFullReplacement('##USERCTXLVL##', CONTEXT_ELIS_USER);
        $this->loadDataSet($dataset);
    }

    /**
     * Test sync-ing an ELIS User Profile field to a DELETED Moodle User Profile field
     */
    public function test_syncpmuserfieldtodeletedmoodleprofilefield() {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/user/profile/definelib.php');

        $this->load_csv_data();

        // Set PM Custom User field(s) to Sync to Moodle.
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

        // Read a record.
        $src = new user(103, null, array(), false, array());
        $src->reset_custom_field_list();
        // Modify the data.
        $src->firstname = 'Testuser';
        $src->lastname = 'One';
        $src->field_sometext = 'boo';
        $src->field_sometextfrompm = 'bla';
        $src->save();

        // Delete some custom Moodle Profile field(s) to cause old error (pre ELIS-4499).
        $fields = field::get_for_context_level($ctxlvl);
        foreach ($fields as $field) {
            $fieldobj = new field($field);
            if ($moodlefield = $DB->get_record('user_info_field', array('shortname' => $fieldobj->shortname))) {
                profile_delete_field($moodlefield->id);
            }
        }

        // Run the library sync - throws errors not exceptions :(.
        $CFG->mnet_localhost_id = 1; // ???
        $mu = cm_get_moodleuser(103);
        try {
            $result = pm_moodle_user_to_pm($mu);
            $this->assertTrue($result);
        } catch (Exception $ex) {
            $this->assertTrue(false, $ex->message);
        }
    }
}