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
 * @subpackage core
 * @author     Remote-Learner.net Inc
 * @author     James McQuillan <james.mcquillan@remote-learner.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2011 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__) . '/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot . '/elis/program/lib/setup.php');
require_once($CFG->dirroot . '/elis/program/accesslib.php');
require_once('PHPUnit/Extensions/Database/DataSet/ITableMetaData.php');
require_once('PHPUnit/Extensions/Database/DataSet/AbstractTableMetaData.php');
require_once('PHPUnit/Extensions/Database/DataSet/ITable.php');
require_once('PHPUnit/Extensions/Database/DataSet/AbstractTable.php');
require_once(elis::lib('testlib.php'));
require_once(elispm::lib('contexts.php'));
require_once(elispm::lib('deprecatedlib.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));
require_once(elis::lib('data/customfield.class.php'));
require_once(elis::file('core/fields/moodle_profile/custom_fields.php'));
require_once(elis::file('core/fields/manual/custom_fields.php'));

require_once($CFG->dirroot.'/blocks/php_report/php_report_base.php');
require_once(elispm::lib('filtering/autocomplete_eliswithcustomfields.php'));

class autocompleteElisWithCustomFieldsTest extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

    protected $parent_report = 'user_class_completion';
    protected $uniqid = 'filt-autoc';
    protected $langfile = 'rlreport_user_class_completion';
    protected $contextlevel = CONTEXT_ELIS_USER;

    protected static function get_overlay_tables() {
        return array(
            'config_plugins' => 'moodle',
            field::TABLE => 'elis_core',
            field_contextlevel::TABLE => 'elis_core',
            field_data_text::TABLE => 'elis_core',
            user::TABLE => 'elis_program',
            'context' => 'moodle',
            'user' => 'moodle',
            usermoodle::TABLE => 'elis_program',
            field_owner::TABLE => 'elis_core',
        );
    }

    protected function create_custom_field() {
        $data = new stdClass;
        $data->shortname = 'testfield';
        $data->name = 'Test Field';
        $data->categoryid = 1;
        $data->description = 'Test Field';
        $data->datatype = 'text';
        $data->forceunique = '0';
        $data->mform_showadvanced_last = 0;
        $data->multivalued = '0';
        $data->defaultdata = '';
        $data->manual_field_enabled = '1';
        $data->manual_field_edit_capability = '';
        $data->manual_field_view_capability = '';
        $data->manual_field_control = 'text';
        $data->manual_field_options_source = '';
        $data->manual_field_options = '';
        $data->manual_field_columns = 30;
        $data->manual_field_rows = 10;
        $data->manual_field_maxlength = 2048;

        $field = new field($data);
        $field->save();

        $fieldcontext = new field_contextlevel();
        $fieldcontext->fieldid      = $field->id;
        $fieldcontext->contextlevel = $this->contextlevel;
        $fieldcontext->save();
    }

    public function create_user() {
        $data = new stdClass;
        $data->idnumber = 'testuser12345678';
        $data->username = 'testuser1';
        $data->firstname = 'Test';
        $data->lastname = 'User';
        $data->email = 'test@example.com';
        $data->country = 'CA';
        $data->birthday = '';
        $data->birthmonth = '';
        $data->birthyear = '';
        $data->language = 'en';
        $data->inactive = '0';

        $fieldvar = 'field_testfield';
        $data->$fieldvar='test field data';

        $usr = new user();
        $usr->set_from_data($data);
        $usr->save();
        return $usr;
    }

    protected function set_filter_config() {
        $configdata = array(
            'instance' => array(
                'idnumber' => array(
                    'search' => 1,
                    'disp' => 1,
                ),
                'firstname' => array(
                    'search' => 1,
                    'disp' => 1,
                ),
                'lastname' => array (
                    'search' => 0,
                    'disp' => 1,
                ),
            ),
            'custom_field' => array(
                'testfield' => array(
                    'search' => 1,
                    'disp' => 1,
                    'restrict' => 1,
                ),
            ),
        );
        filt_autoc_set_config($this->parent_report,$this->uniqid,$configdata);
    }

    protected function get_filter_instance() {
        $this->create_custom_field();
        $this->set_filter_config();

        $alias = 'u';
        $label = 'Full Name';
        $advanced = false;
        $field = 'fullname';
        $options = array(
            'ui' => 'inline',
            'report' => $this->parent_report,
            'restriction_sql' => '',
            'label_template' => '[[firstname]] [[lastname]]',
            'selection_enabled' => true,
            'defaults' => array('id' => 2,' label' => 'Test User'),
            'configurable' => true,
            'contextlevel' => 1005,
            'instance_fields' => array(
                'idnumber' => get_string('filter_autocomplete_idnumber',$this->langfile),
                'firstname' => get_string('filter_autocomplete_firstname',$this->langfile),
                'lastname' => get_string('filter_autocomplete_lastname',$this->langfile)
            ),
            'custom_fields' => '*',
        );

        $filter = new generalized_filter_autocomplete_eliswithcustomfields(
                $this->uniqid,
                $alias,
                $this->uniqid,
                $label,
                $advanced,
                $field,
                $options
        );
        return $filter;
    }

    public function testProcessConfigData() {
        $filter = $this->get_filter_instance();

        $configdata = array(
            'instance' => array(
                'idnumber' => array(
                    'search' => '1',
                    'disp' => 0,
                ),
                'firstname' => array(
                    'search' => '0',
                    'disp' => 1,
                ),
                'lastname' => array (
                    'search' => 0,
                    'disp' => 0,
                ),
            ),
            'custom_field' => array(
                'testfield' => array(
                    'search' => '1',
                    'disp' => 0,
                    'restrict' => '1',
                ),
            ),
        );

        $configdata_processed = $filter->process_config_data($configdata);

        //validate basic structure
        $this->assertInternalType('array',$configdata_processed);
        $this->assertArrayHasKey('instance',$configdata_processed);
        $this->assertArrayHasKey('custom_field',$configdata_processed);

        //validate correct instance values
        $this->assertInternalType('array',$configdata_processed['instance']);
        $this->assertArrayHasKey('idnumber',$configdata_processed['instance']);
        $this->assertArrayHasKey('search',$configdata_processed['instance']['idnumber']);
        $this->assertArrayHasKey('disp',$configdata_processed['instance']['idnumber']);
        $this->assertEquals(1,$configdata_processed['instance']['idnumber']['search']);
        $this->assertEquals(1,$configdata_processed['instance']['idnumber']['disp']);
        $this->assertArrayHasKey('firstname',$configdata_processed['instance']);
        $this->assertArrayHasKey('search',$configdata_processed['instance']['firstname']);
        $this->assertArrayHasKey('disp',$configdata_processed['instance']['firstname']);
        $this->assertEquals(0,$configdata_processed['instance']['firstname']['search']);
        $this->assertEquals(1,$configdata_processed['instance']['firstname']['disp']);
        //lastname shoud've been removed since both options were set off
        $this->assertArrayNotHasKey('lastname',$configdata_processed['instance']);

        //validate correct custom field vals
        $this->assertInternalType('array',$configdata_processed['custom_field']);
        $this->assertArrayHasKey('testfield',$configdata_processed['custom_field']);
        $this->assertArrayHasKey('search',$configdata_processed['custom_field']['testfield']);
        $this->assertArrayHasKey('disp',$configdata_processed['custom_field']['testfield']);
        $this->assertArrayHasKey('restrict',$configdata_processed['custom_field']['testfield']);
        $this->assertEquals(1,$configdata_processed['custom_field']['testfield']['search']);
        $this->assertEquals(1,$configdata_processed['custom_field']['testfield']['disp']);
        $this->assertEquals(1,$configdata_processed['custom_field']['testfield']['restrict']);
    }

    public function testGetDisplayInstanceFields() {
        $filter = $this->get_filter_instance();
        $display_instance_fields = $filter->get_display_instance_fields();

        $this->assertInternalType('array',$display_instance_fields);

        $fields = array('idnumber','firstname','lastname');
        foreach ($fields as $field) {
            $this->assertArrayHasKey($field,$display_instance_fields);
            $str = get_string('filter_autocomplete_'.$field,$this->langfile);
            $this->assertEquals($str,$display_instance_fields[$field]);
        }
    }

    public function testGetSearchInstanceFields() {
        $filter = $this->get_filter_instance();
        $search_instance_fields = $filter->get_search_instance_fields();

        $this->assertInternalType('array',$search_instance_fields);

        $fields = array('idnumber','firstname','lastname');
        foreach ($fields as $field) {
            if ($field === 'lastname') {
                $this->assertArrayNotHasKey('lastname',$search_instance_fields);
            } else {
                $this->assertArrayHasKey($field,$search_instance_fields);
                $str = get_string('filter_autocomplete_'.$field,$this->langfile);
                $this->assertEquals($str,$search_instance_fields[$field]);
            }
        }
    }

    public function testGetDisplayCustomFields() {
        $filter = $this->get_filter_instance();
        $display_custom_fields = $filter->get_display_custom_fields();
        $this->assertInternalType('array',$display_custom_fields);
        $this->assertArrayHasKey('testfield',$display_custom_fields);
        $this->assertInternalType('array',$display_custom_fields['testfield']);
        $this->assertArrayHasKey('fieldid',$display_custom_fields['testfield']);
        $this->assertArrayHasKey('label',$display_custom_fields['testfield']);
        $this->assertArrayHasKey('shortname',$display_custom_fields['testfield']);
        $this->assertArrayHasKey('datatype',$display_custom_fields['testfield']);
        $this->assertEquals(1,$display_custom_fields['testfield']['fieldid']);
        $this->assertEquals('Test Field',$display_custom_fields['testfield']['label']);
        $this->assertEquals('testfield',$display_custom_fields['testfield']['shortname']);
        $this->assertEquals('text',$display_custom_fields['testfield']['datatype']);
    }


    public function testGetSearchCustomFields() {
        $filter = $this->get_filter_instance();
        $search_custom_fields = $filter->get_search_custom_fields();
        $this->assertInternalType('array',$search_custom_fields);
        $this->assertArrayHasKey('testfield',$search_custom_fields);
        $this->assertInternalType('array',$search_custom_fields['testfield']);
        $this->assertArrayHasKey('fieldid',$search_custom_fields['testfield']);
        $this->assertArrayHasKey('label',$search_custom_fields['testfield']);
        $this->assertArrayHasKey('shortname',$search_custom_fields['testfield']);
        $this->assertArrayHasKey('datatype',$search_custom_fields['testfield']);
        $this->assertEquals(1,$search_custom_fields['testfield']['fieldid']);
        $this->assertEquals('Test Field',$search_custom_fields['testfield']['label']);
        $this->assertEquals('testfield',$search_custom_fields['testfield']['shortname']);
        $this->assertEquals('text',$search_custom_fields['testfield']['datatype']);
    }

    public function testGetResultsHeaders() {
        $filter = $this->get_filter_instance();
        $results_headers = $filter->get_results_headers();

        $this->assertInternalType('array',$results_headers);

        $idnumber_label = get_string('filter_autocomplete_idnumber',$this->langfile);
        $this->assertContains($idnumber_label, $results_headers);

        $firstname_label = get_string('filter_autocomplete_firstname',$this->langfile);
        $this->assertContains($firstname_label, $results_headers);

        $lastname_label = get_string('filter_autocomplete_lastname',$this->langfile);
        $this->assertContains($lastname_label, $results_headers);

        $this->assertContains('Test Field', $results_headers);
    }

    public function testGetResultsFields() {
        $filter = $this->get_filter_instance();
        $results_fields = $filter->get_results_fields();

        $this->assertInternalType('array',$results_fields);

        $this->assertContains('idnumber', $results_fields);
        $this->assertContains('firstname', $results_fields);
        $this->assertContains('lastname', $results_fields);
        $this->assertContains('testfield', $results_fields);
    }

    public function testGetSearchResults() {
        $filter = $this->get_filter_instance();
        $this->create_user();
        $filter->parent_report_instance->access_capability = 'block/php_report:view';
        $filter->parent_report_instance->userid = 2;
        $search_results = $filter->get_search_results('test');

        $this->assertInternalType('array',$search_results);
        $this->assertArrayHasKey(1,$search_results);
        $this->assertInternalType('object',$search_results[1]);
        $this->assertObjectHasAttribute('idnumber',$search_results[1]);
        $this->assertEquals('testuser12345678',$search_results[1]->idnumber);
    }

}