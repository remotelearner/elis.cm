<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
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
 * @subpackage curriculummanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/user/filters/lib.php');
require_once(elis::lib('form/custom_field_multiselect.php'));

/**
 * Child class to the custom_field_multiselect filter. Returns names - specified for this report - for the supplied ids
 */
class generalized_filter_custom_field_multiselect_values extends generalized_filter_type {
    /**
     * field id and name lists
     */
    var $_fieldidlist;
    var $_reportname;
    var $_options;

    var $_field;

    var $_numeric;

    var $_block_instance;

    /**
     * List of custom field shortnames to exclude from selection
     */
    var $_field_exceptions;

    /**
     * Constructor
     * @param string $uniqueid the name of the column
     * @param string $alias an alias for the column name
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table field name
     * @param array $options select options <= in this case, this contains the list of selected custom fields
     */
    function generalized_filter_custom_field_multiselect_values($uniqueid, $alias, $name, $label, $advanced, $field, $options = array(),$fieldidlist=array(),$fieldnamelist=array(),$action='') {
        parent::generalized_filter_type($uniqueid, $alias, $name, $label, $advanced);

        // Initialize class variables
        $this->_field = $field;
        $this->_block_instance = $options['block_instance'];
        $this->_field_exceptions = !empty($options['field_exceptions'])
                                   ? $options['field_exceptions'] : array();
        $this->_fieldidlist = $fieldidlist;
        $this->_reportname = (isset($options['reportname'])) ? $options['reportname'] : '';

        if (isset($options['help'])) {
            $this->_filterhelp = $options['help'];
        } else {
            $this->_filterhelp = null;
        }
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     */
    function setupForm(&$mform) {
        // Check permissions and don't display filter if there are no course fields to display for this user
        if (!$this->check_for_custom_fields('course')) {
            return false;
        }

        $this_scheduled = false;
        // Grab the workflow default values for this element if in the report scheduling interface
        if ($this->execution_mode == php_report::EXECUTION_MODE_SCHEDULED) {
            if (isset($mform->_defaultValues['fieldidlist'.$this->_reportname])) {
                $this->_fieldidlist = $mform->_defaultValues['fieldidlist'.$this->_reportname];
            } else {
                $this->_fieldidlist = '';
            }
            $this_scheduled = true;
        }

        $options = array(
            'contextlevel' => CONTEXT_ELIS_COURSE,
            'fieldfilter' => array(&$this, 'field_accessible')
        );
        $mform->addElement(elis_custom_field_multiselect::NAME, $this->_uniqueid, $this->_label, $options);
        if (!empty($this->_filterhelp)) {
            $mform->addHelpButton($this->_uniqueid, $this->_filterhelp[0], $this->_filterhelp[2]);
        }
    }

    /**
     * Retrieves data from the form data
     * @param object $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    function check_data($formdata) {
        $field = $this->_uniqueid;
        $fieldids = 'fieldidlist'.$this->_block_instance;
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
        // Modified to give acceptable label for scheduling
        //$value = $data['value'];

        $a = new object();
        $a->label    = $this->_label;
        if (empty($data['value'])) {
            $a->value    = ': none selected';
        } else {
            $selectedfields = explode(',', $data['value']);
            $fields = field::get_for_context_level(CONTEXT_ELIS_COURSE)->to_array();
            $a->value = ': ' . implode(', ', array_map(function($id) use ($fields) { return $fields[$id]->name;}, $selectedfields));
        }
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

    /**
     * Return a boolean to indicate whether or not this filter is displayed
     * depending upon whether any custom fields are found for this user
     * @param string       $field_type  type of custom field to check
     * @return boolean  true if the filter is to show
     */
    function check_for_custom_fields($field_type) {

        // Get custom course fields by context level
        $context = context_elis_helper::get_level_from_name($field_type);
        $fields = field::get_for_context_level($context);
        $fields = $fields ? $fields : array();
        $testfields = array();
        foreach ($fields as $field) {
            //make sure the current user can access this field in at least one
            //course context
            if (!$this->field_accessible($field)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * Specifies the capability we need to check for a particular custom field to be
     * checked for course-level custom field access in a report
     *
     * @param   array           $owners                   shortname-indexed collection of all field owners
     * @param   string          $default_view_capability  capability to use if default setting was selected
     *                                                    on the manual field owner
     *
     * @return  string|boolean                            returns the capability we need to check, or false
     *                                                    on failure
     */
    public static function field_capability($owners, $default_view_capability = 'elis/program:course_view') {

        if (isset($owners['manual'])) {
            //the manual owner contains the permissions info
            $manual_owner = $owners['manual'];
            $params = unserialize($manual_owner->params);

            if (isset($params['view_capability'])) {
                //found the view capability settings
                $view_capability = $params['view_capability'];
                if ($view_capability === '') {
                    //default flag, so use the specified default
                    $view_capability = $default_view_capability;
                }

                return $view_capability;
            }
        }

        //data error
        return false;
    }

    /**
     * Specifies whether a course-level custom field is accessible to the
     * current user in at least once course context
     *
     * @param   array    $owners  shortname-indexed collection of all field owners
     *
     * @return  boolean           true if accessible, otherwise false
     */
    public function field_accessible($field) {
        global $CFG, $USER;
        $owners = $field->owners;

        require_once($CFG->dirroot .'/elis/program/lib/contexts.php');

        if (!in_array($field->shortname, $this->_field_exceptions) &&
            ($view_capability = self::field_capability($owners))) {
            //make sure the user has the view capability in some course
            $contexts = get_contexts_by_capability_for_user('course', $view_capability, $USER->id);
            return !$contexts->is_empty();
        } else {
            //data error
            return false;
        }
    }

    /**
     * Takes a set of submitted values and retuns this filter's default values
     * for them in the same structure (used to reset the filtering form)
     */
    function get_default_values($filter_data) {
        return array();
    }

    function reset_js() {
        return 'cf_reset();';
    }
}
