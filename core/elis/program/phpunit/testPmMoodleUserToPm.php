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
 * @package     elis
 * @subpackage  core
 * @author      Remote-Learner.net Inc
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright   (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

//env
require_once(dirname(__FILE__) . '/../../../elis/core/test_config.php');
global $CFG;

//libs
require_once($CFG->dirroot . '/elis/program/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once(elispm::lib('lib.php'));
require_once(elis::file('core/fields/moodle_profile/custom_fields.php'));

//elis objects
require_once(elis::lib('data/customfield.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));


class pmMoodleUserToPmTest extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');
    protected $langfile = '';

    protected static function get_overlay_tables() {
        return array(
            'context' => 'moodle',
            'user' => 'moodle',
            'user_info_category' => 'moodle',
            'user_info_field' => 'moodle',
            'user_info_data' => 'moodle',
            field_category::TABLE => 'elis_core',
            field_category_contextlevel::TABLE => 'elis_core',
            field::TABLE => 'elis_core',
            field_contextlevel::TABLE => 'elis_core',
            field_data_text::TABLE => 'elis_core',
            field_owner::TABLE => 'elis_core',
            user::TABLE => 'elis_program',
            usermoodle::TABLE => 'elis_program',
        );
    }

    protected function set_up_elis_field_category() {
        $data = new stdClass;
        $data->name = context_elis_helper::get_class_for_level(CONTEXT_ELIS_USER).' Test';
        $category = new field_category($data);
        $category->save();

        $categorycontext = new field_category_contextlevel();
        $categorycontext->categoryid   = $category->id;
        $categorycontext->contextlevel = CONTEXT_ELIS_USER;
        $categorycontext->save();

        return $category;
    }

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
        $field->id = $DB->insert_record('user_info_field',$field);
        return $field;
    }

    protected function set_up_elis_custom_field($mfield, field_category $cat) {

        $cat = $this->set_up_elis_field_category();

        $field = new field;
        $field->shortname = $mfield->shortname;
        $field->name = $mfield->name;
        $field->datatype = $mfield->datatype;
        field::ensure_field_exists_for_context_level($field, CONTEXT_ELIS_USER, $cat);

        $owner = new field_owner();
        $owner->fieldid = $field->id;
        $owner->plugin = 'moodle_profile';
        $owner->exclude = pm_moodle_profile::sync_to_moodle;
        $owner->save();

        return $field;
    }

    protected function set_up_custom_fields() {
        $shortname = 'testfield';
        $mfield = $this->set_up_moodle_custom_field($shortname);
        $cat = $this->set_up_elis_field_category();
        $efield = $this->set_up_elis_custom_field($mfield,$cat);
        return array('m' => $mfield, 'e' => $efield);
    }

    protected function set_up_muser($mfield) {
        global $DB;

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

        $profile_field_prop = 'profile_field_'.$mfield->shortname;
        $user->$profile_field_prop = 'onetwothree';

        $user->id = $DB->insert_record('user', $user);
        profile_save_data($user);
        return $user;
    }

    public function testPmMoodleUserToPm() {
        global $DB;

        $fields = $this->set_up_custom_fields();
        $mu = $this->set_up_muser($fields['m']);

        $result = pm_moodle_user_to_pm($mu);

        $this->assertTrue($result);

        //get elis user
        $cu = $DB->get_record('crlm_user',array('username' => $mu->username));
        $this->assertNotEmpty($cu);
        $this->assertEquals($mu->idnumber,$cu->idnumber);

        //get elis custom field data
        $sql = 'SELECT * FROM {elis_field_data_text} fdt JOIN {context} ctx ON ctx.id = fdt.contextid
            WHERE ctx.instanceid = ? AND ctx.contextlevel=? AND fdt.fieldid=?';
        $params = array($cu->id,CONTEXT_ELIS_USER,$fields['e']->id);
        $field_data = $DB->get_records_sql($sql,$params);
        $this->assertNotEmpty($field_data);

        //get usermoodle record
        $usermoodle = $DB->get_record('crlm_user_moodle',array('cuserid' => $cu->id, 'muserid'=> $mu->id, 'idnumber' => $cu->idnumber));
        $this->assertNotEmpty($usermoodle);


    }

}
