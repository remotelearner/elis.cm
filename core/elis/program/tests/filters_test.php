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

/**
 * NOTE: This file only tests all ELIS custom context calls made by ELIS reports. It should not be used to verify
 * other report operations or data accuracy.
 */

require_once(dirname(__FILE__).'/../../../elis/core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');

// Data objects.
require_once(elis::lib('data/customfield.class.php'));

// Filter libs.
require_once(elis::lib('filtering/multifilter.php'));
// require_once(elispm::lib('filtering/clustertree.php'));
require_once(elispm::lib('filtering/custom_field_multiselect_values.php'));

/**
 * A mock multifilter used for testing.
 */
class test_multifilter extends generalized_filter_multifilter {
    /**
     * @var array Sections used for testing.
     */
    protected $sections = array(
        'test_cur' => array('name' => 'curriculum'),
        'test_trk' => array('name' => 'track'),
        'test_crs' => array('name' => 'course'),
        'test_cls' => array('name' => 'class'),
        'test_usr' => array('name' => 'user'),
        'test_usrset' => array('name' => 'cluster'),
    );

    /**
     * A function solely for testing to give us access to $this->sections.
     * @return array The $this->sections array.
     */
    public function get_sections() {
        return $this->sections;
    }
}

/**
 * A mock moodleform used for testing.
 */
class test_moodleform_filterstest extends moodleform {

    /**
     * Empty definition used for testing.
     */
    public function definition() {

    }

    /**
     * Test-only method to give us access to _elements
     * @return array Form elements.
     */
    public function get_elements() {
        return $this->_form->_elements;
    }

    /**
     * Test method to return moodleform.
     * @return MoodleQuickForm The internal MoodleQuickForm object.
     */
    public function get_mform() {
        return $this->_form;
    }
}

/**
 * A mock generalized_filter_custom_field_multiselect_values used for testing.
 */
class test_generalized_filter_custom_field_multiselect_values extends generalized_filter_custom_field_multiselect_values {
    /**
     * Return a boolean to indicate whether or not this filter is displayed depending upon whether any custom fields are
     * found for this user.
     * @param string $fieldtype Type of custom field to check
     * @return bool True if the filter is to show
     */
    public function check_for_custom_fields($fieldtype) {
        return true;
    }
}

/**
 * Test filters.
 * @group elis_program
 */
class filters_testcase extends elis_database_test {

    /**
     * Load initial data from CSVs.
     */
    protected function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            field::TABLE => elis::component_file('program', 'tests/fixtures/course_custom_fields.csv'),
            field_contextlevel::TABLE => elis::component_file('program', 'tests/fixtures/course_custom_fields_contextlevels.csv'),
            field_owner::TABLE => elis::component_file('program', 'tests/fixtures/course_custom_fields_owner.csv'),
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * Test the multifilter.
     */
    public function test_multifilter() {
        $filter = new test_multifilter('test_multifilter', 'Test Multifilter', array());
        $filter->make_field_list(array());
        $sections = $filter->get_sections();
        foreach ($sections as $name => $info) {
            $this->assertNotEmpty($info['contextlevel']);
        }
    }

    /**
     * Test the clustertree.
     */
    public function test_clustertree() {
        global $CFG;

        if (!file_exists($CFG->dirroot.'/blocks/php_report/php_report_base.php')) {
            $this->markTestSkipped('Test requires block_php_report code');
        }

        require_once(elispm::lib('filtering/clustertree.php'));

        $testclusterids = array();
        for ($i = 0; $i < 5; $i++) {
            $userset = new userset;
            $userset->name = 'test_userset_'.$i;
            $userset->save();
            $testclusterids[] = $userset->id;
        }

        $uniqueid = 'test_clustertree';
        $alias = 'test_clustertree';
        $name = 'test_clustertree';
        $label = 'test_clustertree';
        $advanced = false;
        $field = null;
        $filter = new generalized_filter_clustertree($uniqueid, $alias, $name, $label, $advanced, $field);

        // Test context call in get_sql_filter.
        $data = array(
            'clusterids' => array(),
            'clrunexpanded_ids' => array(),
            'unexpanded_ids' => array()
        );
        $sqlfilter = $filter->get_sql_filter($data);
        $this->assertNotEmpty($sqlfilter);

        // Test empty get_label.
        $data = array();
        $label = $filter->get_label($data);
        $expected = 'test_clustertree: N/A<br/>';
        $this->assertEquals($expected, $label);

        // Test get_label with clusterids.
        $data = array(
            'clusterids' => $testclusterids
        );
        $label = $filter->get_label($data);
        $clusternames = array();
        for ($i = 0; $i < 5; $i++) {
            $clusternames[] = 'test_userset_'.$i;
        }
        $expected = $name.':<br/>'.implode(', ', $clusternames).'<br/>';
        $this->assertEquals($expected, $label);

        // Test get_label with unexpanded_ids.
        $data = array(
            'unexpanded_ids' => $testclusterids
        );
        $label = $filter->get_label($data);
        $clusternames = array();
        for ($i = 0; $i < 5; $i++) {
            $clusternames[] = 'test_userset_'.$i.' (and all children)';
        }
        $expected = $name.':<br/>'.implode(', ', $clusternames).'<br/>';
        $this->assertEquals($expected, $label);

        // Create a subuserset for the next two tests.
        $subuserset = new userset;
        $subuserset->name = 'test_subuserset_1';
        $subuserset->parent = $testclusterids[0];
        $subuserset->save();
        $testclusterids[] = $subuserset->id;

        // Test get_label with recursively unselected clusters with 'clusterids' param.
        // Tests that when clrunexpanded_ids contains an ID of a userset with child usersets, the child usersets also
        // do not show up.
        $data = array(
            'clusterids' => $testclusterids,
            'clrunexpanded_ids' => array($testclusterids[0])
        );
        $label = $filter->get_label($data);
        $clusternames = array();
        for ($i = 1; $i <= 4; $i++) {
            $clusternames[] = 'test_userset_'.$i;
        }
        $expected = $name.':<br/>'.implode(', ', $clusternames).'<br/>';
        $this->assertEquals($expected, $label);

        // Test get_label with recursively unselected clusters with 'unexpanded_ids'.
        // Tests that when a subusetset is listed in 'unexpanded_ids', but it's parent is listed in clrunexpanded_ids,
        // the subuserset does not show up.
        $unexpandedids = $testclusterids;
        unset($unexpandedids[0]);
        $data = array(
            'unexpanded_ids' => $unexpandedids,
            'clrunexpanded_ids' => array($testclusterids[0])
        );
        $label = $filter->get_label($data);
        $clusternames = array();
        for ($i = 1; $i <= 4; $i++) {
            $clusternames[] = 'test_userset_'.$i.' (and all children)';
        }
        $expected = $name.':<br/>'.implode(', ', $clusternames).'<br/>';
        $this->assertEquals($expected, $label);

    }

    /**
     * Test generalized_filter_custom_field_multiselect_values.
     */
    public function test_customfieldmultiselectvalues() {
        global $CFG;

        if (!file_exists($CFG->dirroot.'/blocks/php_report/php_report_base.php')) {
            $this->markTestSkipped('Test requires block_php_report code');
        }

        $this->load_csv_data();
        $uniqid = 'test_CustomFieldMultiselectValues';
        $alias = 'test_CustomFieldMultiselectValues';
        $name = 'test_CustomFieldMultiselectValues';
        $label = 'test_CustomFieldMultiselectValues';
        $adv = false;
        $field = null;
        $opts = array('block_instance' => '');
        $filter = new test_generalized_filter_custom_field_multiselect_values($uniqid, $alias, $name, $label, $adv, $field, $opts);

        $frm = new test_moodleform_filterstest();
        $mform = $frm->get_mform();

        // Test context in setupForm.
        $filter->setupForm($mform);
        $elements = $frm->get_elements();
        $this->assertNotEmpty($elements);
        $multiselectfound = false;
        foreach ($elements as $ele) {
            if (is_a($ele, 'elis_custom_field_multiselect')) {
                $this->assertNotEmpty($ele->_options['contextlevel']);
                $multiselectfound = true;
            }
        }
        $this->assertNotEmpty($multiselectfound);

        // Test context in get_label.
        $filter->get_label(array('value' => '100'));

        // Text context in check_for_custom_fields().
        $filter = new generalized_filter_custom_field_multiselect_values($uniqid, $alias, $name, $label, $adv, $field, $opts);
        $filter->check_for_custom_fields('course');
    }
}