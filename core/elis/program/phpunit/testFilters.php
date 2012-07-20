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


/**
 * NOTE: This file only tests all ELIS custom context calls made by ELIS reports. It should not be used to verify
 * other report operations or data accuracy.
 */


require_once(dirname(__FILE__) . '/../../../elis/core/test_config.php');
global $CFG;
require_once($CFG->dirroot . '/elis/program/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once(elis::lib('data/customfield.class.php'));
require_once('PHPUnit/Extensions/Database/DataSet/CsvDataSet.php');

require_once(elis::lib('filtering/multifilter.php'));
require_once(elispm::lib('filtering/clustertree.php'));
require_once(elispm::lib('filtering/custom_field_multiselect_values.php'));
ini_set('error_reporting',1);
ini_set('display_errors',1);

class test_multifilter extends generalized_filter_multifilter {
    protected $sections = array(
        'test_cur' => array('name' => 'curriculum'),
        'test_trk' => array('name' => 'track'),
        'test_crs' => array('name' => 'course'),
        'test_cls' => array('name' => 'class'),
        'test_usr' => array('name' => 'user'),
        'test_usrset' => array('name' => 'cluster'),
    );

    //a function solely for testing to give us access to $this->sections
    public function get_sections() {
        return $this->sections;
    }
}

class test_moodleform extends moodleform {
    public function definition() {

    }

    //test-only method to give us access to _elements
    public function get_elements() {
        return $this->_form->_elements;
    }

    public function get_mform() {
        return $this->_form;
    }
}

class test_generalized_filter_custom_field_multiselect_values extends generalized_filter_custom_field_multiselect_values {

    function check_for_custom_fields($field_type) {
        return true;
    }
}

class filtersTest extends elis_database_test {
    protected static function get_overlay_tables() {
		return array(
            field::TABLE => 'elis_core',
            field_contextlevel::TABLE => 'elis_core',
            field_owner::TABLE => 'elis_core',
        );
    }

    protected function load_csv_data() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(field::TABLE, elis::component_file('program', 'phpunit/course_custom_fields.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);

        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(field_contextlevel::TABLE, elis::component_file('program', 'phpunit/course_custom_fields_contextlevels.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);

        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(field_owner::TABLE, elis::component_file('program', 'phpunit/course_custom_fields_owner.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    public function testMultifilter() {
        $filter = new test_multifilter('test_multifilter','Test Multifilter',array());
        $filter->make_field_list(array());
        $sections = $filter->get_sections();

        foreach ($sections as $name => $info) {
            $this->assertNotEmpty($info['contextlevel']);
        }
    }

    public function testClusterTree() {
        $uniqueid = 'test_clustertree';
        $alias = 'test_clustertree';
        $name = 'test_clustertree';
        $label = 'test_clustertree';
        $advanced = false;
        $field = null;
        $filter = new generalized_filter_clustertree($uniqueid, $alias, $name, $label, $advanced, $field);

        //test context call in get_sql_filter
        $data = array(
            'clusterids' => array(),
            'clrunexpanded_ids' => array(),
            'unexpanded_ids' => array()
        );
        $sql_filter = $filter->get_sql_filter($data);
        $this->assertNotEmpty($sql_filter);

        //test context call in get_label
        $data = array();
        $label = $filter->get_label($data);
        $this->assertNotEmpty($label);
    }

    public function testCustomFieldMultiselectValues() {
        $this->load_csv_data();
        $uniqueid = 'test_CustomFieldMultiselectValues';
        $alias = 'test_CustomFieldMultiselectValues';
        $name = 'test_CustomFieldMultiselectValues';
        $label = 'test_CustomFieldMultiselectValues';
        $advanced = false;
        $field = null;
        $opts = array(
            'block_instance' => '');
        $filter = new test_generalized_filter_custom_field_multiselect_values($uniqueid, $alias, $name, $label, $advanced, $field, $opts);

        $frm = new test_moodleform();
        $mform = $frm->get_mform();

        //test context in setupForm
        $filter->setupForm($mform);
        $elements = $frm->get_elements();
        $this->assertNotEmpty($elements);
        $multiselect_found = false;
        foreach($elements as $ele) {
            if (is_a($ele,'elis_custom_field_multiselect')) {
                $this->assertNotEmpty($ele->_options['contextlevel']);
                $multiselect_found = true;
            }
        }
        $this->assertNotEmpty($multiselect_found);

        //test context in get_label;
        $filter->get_label(array('value'=>'100'));

        //text context in check_for_custom_fields()
        $filter = new generalized_filter_custom_field_multiselect_values($uniqueid, $alias, $name, $label, $advanced, $field, $opts);
        $filter->check_for_custom_fields('course');
    }
}