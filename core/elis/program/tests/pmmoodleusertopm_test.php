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

require_once(dirname(__FILE__).'/../../../elis/core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');

// Libs.
require_once(elispm::lib('lib.php'));
require_once(elis::file('core/fields/moodle_profile/custom_fields.php'));

// Data objects.
require_once(elis::lib('data/customfield.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));

/**
 * Test the pm_moodle_user_to_pm function.
 * @group elis_program
 */
class pm_moodle_user_to_pm_testcase extends elis_database_test {

    /**
     * Set up an ELIS custom field category.
     * @return field_category The created field category.
     */
    protected function set_up_elis_field_category() {
        $data = new stdClass;
        $data->name = context_elis_helper::get_class_for_level(CONTEXT_ELIS_USER).' Test';
        $category = new field_category($data);
        $category->save();

        $categorycontext = new field_category_contextlevel();
        $categorycontext->categoryid = $category->id;
        $categorycontext->contextlevel = CONTEXT_ELIS_USER;
        $categorycontext->save();

        return $category;
    }

    /**
     * Set up a moodle custom field.
     * @return object The created Moodle custom field database object.
     */
    protected function set_up_moodle_custom_field($shortname) {
        global $DB;

        $field = new stdClass;
        $field->shortname = $shortname;
        $field->name = 'Test Field';
        $field->datatype = 'text';
        $field->description = '';
        $field->descriptionformat = '1';
        $field->categoryid = '1';
        $field->sortorder = '1';
        $field->required = '0';
        $field->locked = '0';
        $field->visible = '2';
        $field->forceunique = '0';
        $field->signup = '0';
        $field->defaultdata = '';
        $field->defaultdataformat = '0';
        $field->param1 = '30';
        $field->param2 = '2048';
        $field->param3 = '0';
        $field->param4 = '';
        $field->param5 = '';
        $field->id = $DB->insert_record('user_info_field', $field);
        return $field;
    }

    /**
     * Set up an ELIS custom field from a Moodle custom field.
     * @param object $mfield The moodle custom field object to use as reference.
     * @param field_category $cat The ELIS custom field category to put the field in.
     * @return field The created ELIS custom field.
     */
    protected function set_up_elis_custom_field($mfield, field_category $cat) {

        if (empty($cat)) {
            $cat = $this->set_up_elis_field_category();
        }

        $user = new user;
        $user->reset_custom_field_list();

        $field = new field;
        $field->shortname = $mfield->shortname;
        $field->name = $mfield->name;
        $field->datatype = $mfield->datatype;
        field::ensure_field_exists_for_context_level($field, CONTEXT_ELIS_USER, $cat);

        $owner = new field_owner();
        $owner->fieldid = $field->id;
        $owner->plugin = 'manual';
        $owner->params = serialize(array(
            'required' => false,
            'edit_capability' => '',
            'view_capability' => '',
            'control' => 'text',
            'columns' => 30,
            'rows' => 10,
            'maxlength' => 2048,
            'startyear' => '1970',
            'stopyear' => '2038',
            'inctime' => '0'
        ));
        $owner->save();

        $owner = new field_owner();
        $owner->fieldid = $field->id;
        $owner->plugin = 'moodle_profile';
        $owner->exclude = pm_moodle_profile::sync_to_moodle;
        $owner->save();

        return $field;
    }

    /**
     * Set up a moodle custom field, an ELIS custom field category, and an ELIS custom field.
     * @return array An array of a moodle field under index 'm' and an elis field under index 'e'.
     */
    protected function set_up_custom_fields() {
        $shortname = 'testfield';
        $mfield = $this->set_up_moodle_custom_field($shortname);
        $cat = $this->set_up_elis_field_category();
        $efield = $this->set_up_elis_custom_field($mfield, $cat);
        return array('m' => $mfield, 'e' => $efield);
    }

    /**
     * Set up a moodle user.
     * @param object $mfield The moodle custom field to use.
     * @return object The created moodle user database object.
     */
    protected function set_up_muser($mfield) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/admin/tool/uploaduser/locallib.php');

        $user = new stdClass;
        $user->auth = 'manual';
        $user->confirmed = 1;
        $user->mnethostid = 1;
        $user->username = 'test_user';
        $user->password = md5('12345');
        $user->idnumber = 'test_user';
        $user->firstname = 'Test';
        $user->lastname = 'User';
        $user->email = 'test@example.com';
        $user->country = 'CA';

        $profilefieldprop = 'profile_field_'.$mfield->shortname;
        $user->$profilefieldprop = 'onetwothree';

        $user->id = $DB->insert_record('user', $user);
        $user = uu_pre_process_custom_profile_data($user);
        profile_save_data($user);
        return $user;
    }

    /**
     * Test pm_moodle_user_to_pm function.
     */
    public function test_pm_moodle_user_to_pm() {
        global $DB;

        $fields = $this->set_up_custom_fields();
        $mu = $this->set_up_muser($fields['m']);

        $result = pm_moodle_user_to_pm($mu);

        $this->assertTrue($result);

        // Get ELIS user.
        $cu = $DB->get_record('crlm_user', array('username' => $mu->username));
        $this->assertNotEmpty($cu);
        $this->assertEquals($mu->idnumber, $cu->idnumber);

        // Get ELIS custom field data.
        $sql = 'SELECT *
                  FROM {elis_field_data_text} fdt
                  JOIN {context} ctx ON ctx.id = fdt.contextid
                 WHERE ctx.instanceid = ? AND ctx.contextlevel = ? AND fdt.fieldid = ?';
        $params = array($cu->id, CONTEXT_ELIS_USER, $fields['e']->id);
        $fielddata = $DB->get_records_sql($sql, $params);
        $this->assertNotEmpty($fielddata);

        // Get usermoodle record.
        $params = array('cuserid' => $cu->id, 'muserid' => $mu->id, 'idnumber' => $cu->idnumber);
        $usermoodle = $DB->get_record('crlm_user_moodle', $params);
        $this->assertNotEmpty($usermoodle);
    }
}
