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

// Data objects.
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));
require_once(elis::lib('data/customfield.class.php'));

// ELIS libs.
require_once($CFG->dirroot.'/elis/program/accesslib.php');
require_once(elispm::lib('contexts.php'));
require_once(elispm::lib('deprecatedlib.php'));
require_once(elis::file('core/fields/moodle_profile/custom_fields.php'));
require_once(elis::file('core/fields/manual/custom_fields.php'));
require_once(elispm::lib('filtering/autocomplete_eliswithcustomfields.php'));

/**
 * Test autocomplete_eliswithcustomfields functions.
 * @group elis_program
 */
class autocompleteeliswithcustomfields_testcase extends elis_database_test {

    /**
     * @var string The report to use for the filter
     */
    protected $parent_report = 'user_class_completion';

    /**
     * @var string The unique id of the filter.
     */
    protected $uniqid = 'filt-autoc';

    /**
     * @var string The language file to use for comparing messages.
     */
    protected $langfile = 'rlreport_user_class_completion';

    /**
     * @var int The context level to use when searching.
     */
    protected $contextlevel = CONTEXT_ELIS_USER;

    /**
     * Create a custom field.
     */
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

    /**
     * Create a user.
     * @return user The created user.
     */
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

    /**
     * Set the config options for the filter.
     */
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
        filt_autoc_set_config($this->parent_report, $this->uniqid, $configdata);
    }

    /**
     * Get an instance of the filter.
     * @return generalized_filter_autocomplete_eliswithcustomfields The filter instance.
     */
    protected function get_filter_instance() {
        global $CFG;

        if (!file_exists($CFG->dirroot.'/blocks/php_report/php_report_base.php')) {
            $this->markTestSkipped('Test requires block_php_report code');
        }

        require_once($CFG->dirroot.'/blocks/php_report/php_report_base.php');

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
            'defaults' => array('id' => 2, ' label' => 'Test User'),
            'configurable' => true,
            'contextlevel' => 1005,
            'instance_fields' => array(
                'idnumber' => get_string('filter_autocomplete_idnumber', $this->langfile),
                'firstname' => get_string('filter_autocomplete_firstname', $this->langfile),
                'lastname' => get_string('filter_autocomplete_lastname', $this->langfile)
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

    /**
     * Test processing configuration data.
     */
    public function test_processconfigdata() {
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

        $configdataprocessed = $filter->process_config_data($configdata);

        // Validate basic structure.
        $this->assertInternalType('array', $configdataprocessed);
        $this->assertArrayHasKey('instance', $configdataprocessed);
        $this->assertArrayHasKey('custom_field', $configdataprocessed);

        // Validate correct instance values.
        $this->assertInternalType('array', $configdataprocessed['instance']);
        $this->assertArrayHasKey('idnumber', $configdataprocessed['instance']);
        $this->assertArrayHasKey('search', $configdataprocessed['instance']['idnumber']);
        $this->assertArrayHasKey('disp', $configdataprocessed['instance']['idnumber']);
        $this->assertEquals(1, $configdataprocessed['instance']['idnumber']['search']);
        $this->assertEquals(1, $configdataprocessed['instance']['idnumber']['disp']);
        $this->assertArrayHasKey('firstname', $configdataprocessed['instance']);
        $this->assertArrayHasKey('search', $configdataprocessed['instance']['firstname']);
        $this->assertArrayHasKey('disp', $configdataprocessed['instance']['firstname']);
        $this->assertEquals(0, $configdataprocessed['instance']['firstname']['search']);
        $this->assertEquals(1, $configdataprocessed['instance']['firstname']['disp']);
        // Lastname shoud've been removed since both options were set off.
        $this->assertArrayNotHasKey('lastname', $configdataprocessed['instance']);

        // Validate correct custom field vals.
        $this->assertInternalType('array', $configdataprocessed['custom_field']);
        $this->assertArrayHasKey('testfield', $configdataprocessed['custom_field']);
        $this->assertArrayHasKey('search', $configdataprocessed['custom_field']['testfield']);
        $this->assertArrayHasKey('disp', $configdataprocessed['custom_field']['testfield']);
        $this->assertArrayHasKey('restrict', $configdataprocessed['custom_field']['testfield']);
        $this->assertEquals(1, $configdataprocessed['custom_field']['testfield']['search']);
        $this->assertEquals(1, $configdataprocessed['custom_field']['testfield']['disp']);
        $this->assertEquals(1, $configdataprocessed['custom_field']['testfield']['restrict']);
    }

    /**
     * Test the get_display_instance_fields function.
     */
    public function test_get_display_instance_fields() {
        $filter = $this->get_filter_instance();
        $displayinstancefields = $filter->get_display_instance_fields();

        $this->assertInternalType('array', $displayinstancefields);

        $fields = array('idnumber', 'firstname', 'lastname');
        foreach ($fields as $field) {
            $this->assertArrayHasKey($field, $displayinstancefields);
            $str = get_string('filter_autocomplete_'.$field, $this->langfile);
            $this->assertEquals($str, $displayinstancefields[$field]);
        }
    }

    /**
     * Test the get_search_instance_fields function.
     */
    public function test_get_search_instance_fields() {
        $filter = $this->get_filter_instance();
        $searchinstancefields = $filter->get_search_instance_fields();

        $this->assertInternalType('array', $searchinstancefields);

        $fields = array('idnumber', 'firstname', 'lastname');
        foreach ($fields as $field) {
            if ($field === 'lastname') {
                $this->assertArrayNotHasKey('lastname', $searchinstancefields);
            } else {
                $this->assertArrayHasKey($field, $searchinstancefields);
                $str = get_string('filter_autocomplete_'.$field, $this->langfile);
                $this->assertEquals($str, $searchinstancefields[$field]);
            }
        }
    }

    /**
     * Test the get_display_custom_fields function.
     */
    public function test_get_display_custom_fields() {
        $filter = $this->get_filter_instance();
        $displaycustomfields = $filter->get_display_custom_fields();
        $this->assertInternalType('array', $displaycustomfields);
        $this->assertArrayHasKey('testfield', $displaycustomfields);
        $this->assertInternalType('array', $displaycustomfields['testfield']);
        $this->assertArrayHasKey('fieldid', $displaycustomfields['testfield']);
        $this->assertArrayHasKey('label', $displaycustomfields['testfield']);
        $this->assertArrayHasKey('shortname', $displaycustomfields['testfield']);
        $this->assertArrayHasKey('datatype', $displaycustomfields['testfield']);
        $this->assertEquals(10, $displaycustomfields['testfield']['fieldid']);
        $this->assertEquals('Test Field', $displaycustomfields['testfield']['label']);
        $this->assertEquals('testfield', $displaycustomfields['testfield']['shortname']);
        $this->assertEquals('text', $displaycustomfields['testfield']['datatype']);
    }

    /**
     * Test the get_search_custom_fields function.
     */
    public function test_get_search_custom_fields() {
        $filter = $this->get_filter_instance();
        $searchcustomfields = $filter->get_search_custom_fields();
        $this->assertInternalType('array', $searchcustomfields);
        $this->assertArrayHasKey('testfield', $searchcustomfields);
        $this->assertInternalType('array', $searchcustomfields['testfield']);
        $this->assertArrayHasKey('fieldid', $searchcustomfields['testfield']);
        $this->assertArrayHasKey('label', $searchcustomfields['testfield']);
        $this->assertArrayHasKey('shortname', $searchcustomfields['testfield']);
        $this->assertArrayHasKey('datatype', $searchcustomfields['testfield']);
        $this->assertEquals(10, $searchcustomfields['testfield']['fieldid']);
        $this->assertEquals('Test Field', $searchcustomfields['testfield']['label']);
        $this->assertEquals('testfield', $searchcustomfields['testfield']['shortname']);
        $this->assertEquals('text', $searchcustomfields['testfield']['datatype']);
    }

    /**
     * Test the get_results_headers function.
     */
    public function test_get_results_headers() {
        $filter = $this->get_filter_instance();
        $resultsheaders = $filter->get_results_headers();

        $this->assertInternalType('array', $resultsheaders);

        $idnumberlabel = get_string('filter_autocomplete_idnumber', $this->langfile);
        $this->assertContains($idnumberlabel, $resultsheaders);

        $firstnamelabel = get_string('filter_autocomplete_firstname', $this->langfile);
        $this->assertContains($firstnamelabel, $resultsheaders);

        $lastnamelabel = get_string('filter_autocomplete_lastname', $this->langfile);
        $this->assertContains($lastnamelabel, $resultsheaders);

        $this->assertContains('Test Field', $resultsheaders);
    }

    /**
     * Test the get_results_headers function.
     */
    public function test_get_results_fields() {
        $filter = $this->get_filter_instance();
        $resultsfields = $filter->get_results_fields();

        $this->assertInternalType('array', $resultsfields);

        $this->assertContains('idnumber', $resultsfields);
        $this->assertContains('firstname', $resultsfields);
        $this->assertContains('lastname', $resultsfields);
        $this->assertContains('testfield', $resultsfields);
    }

    /**
     * Test the get_search_results function.
     */
    public function test_get_search_results() {
        global $USER;
        $this->setAdminUser();

        $filter = $this->get_filter_instance();
        $pmuser = $this->create_user();
        if ($muser = $pmuser->get_moodleuser()) {
            $USER = $muser;
        }
        $filter->parent_report_instance->access_capability = 'block/php_report:view';
        $filter->parent_report_instance->userid = 2;
        $searchresults = $filter->get_search_results('test');

        $this->assertInternalType('array', $searchresults);
        $this->assertArrayHasKey(1, $searchresults);
        $this->assertInternalType('object', $searchresults[1]);
        $this->assertObjectHasAttribute('idnumber', $searchresults[1]);
        $this->assertEquals('testuser12345678', $searchresults[1]->idnumber);
    }
}