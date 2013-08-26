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
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot .'/user/filters/lib.php');

/**
 * Generic filter based on a list of values.
 */
class generalized_filter_custom_field_multiselect extends generalized_filter_type {
    /**
     * options for the list values
     */
    var $_options;

    var $_field;

    var $_numeric;

    var $_block_instance;
    /**
     * Constructor
     * @param string $uniqueid the name of the column
     * @param string $alias an alias for the column name
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     * @param array $options select options <= in this case, this contains the list of selected custom fields
     * @return none
     */
    function generalized_filter_custom_field_multiselect($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {
        parent::generalized_filter_type($uniqueid, $alias, $name, $label, $advanced);
        global $SESSION;

        // Set up required class variables
        $this->_field = $field;
        $this->_fieldidlist = base64_encode(serialize($options['fieldids']));
        $this->_block_instance = $options['block_instance'];
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     */
    function setupForm(&$mform) {
        // This function is setup in the child class, custom_field_multiselect_values
        // as it is very specific to each report
    }

    /**
     * Retrieves data from the form data
     * @param object $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    function check_data($formdata) {
        $field = $this->_uniqueid;
        $fieldids = 'fieldidlist'.$this->_block_instance;
        $fieldnames = 'fieldnamelist'.$this->_block_instance;
        if (array_key_exists($field, $formdata) and $formdata->$field !== '') {
            return array('value'=>(string)$formdata->$field);
        }

        return false;
    }

    function get_report_parameters($data) {
        return array('value' => $data['value'],
                     'numeric' => $this->_numeric);
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    function get_label($data) {

        $a = new object();
        $a->label    = $this->_label;
        $a->value    = ': ';
        $a->operator = '';

        return get_string('selectlabel', 'filters', $a);
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        // No processing here
        return null;
    }
}

