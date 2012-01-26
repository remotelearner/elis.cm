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

require_once($CFG->dirroot.'/user/filters/lib.php');
require_once($CFG->dirroot.'/curriculum/config.php');
require_once(CURMAN_DIRLOCATION . '/lib/filtering/custom_field_multiselect.php');

/**
 * Child class to the custom_field_multiselect filter. Returns names - specified for this report - for the supplied ids
 */
class generalized_filter_custom_field_multiselect_values extends generalized_filter_custom_field_multiselect {
    /**
     * field id and name lists
     */
    var $_fieldidlist;
    var $_fieldnamelist;
    var $_reportname;
    var $_options;

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
        parent::generalized_filter_custom_field_multiselect($uniqueid, $alias, $name, $label, $advanced, $field, $options);

        // Initialize class variables
        $this->_fieldidlist = $fieldidlist;
        $this->_reportname = (isset($options['reportname'])) ? $options['reportname'] : '';

        if (!isset($this->_fieldnamelist)) {
            $this->_fieldnamelist = array();
        }
        if (isset($options['help'])) {
            $this->_filterhelp = $options['help'];
        } else {
            $this->filterhelp = null;
        }
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     */
    function setupForm(&$mform) {
        global $CFG, $COURSE, $SESSION;

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

        // need to reset fieldidlist and fieldnamelist if field is empty
        if (!empty($this->_fieldidlist)) {
            $this->get_names();
        }

        //needed for AJAX calls
        require_js(array('yui_yahoo',
                         'yui_dom',
                         'yui_event',
                         'yui_connection',
                         "{$CFG->wwwroot}/curriculum/js/customfields.js",
                         "{$CFG->wwwroot}/curriculum/js/associate.class.js"),true);

        $label = $this->get_label(array('value'=> $this->_label));

        $mform->addElement('static',$this->_uniqueid.'_label','<br clear=left>'.$label);
        if (!empty($this->_filterhelp)) {
            $mform->setHelpButton($this->_uniqueid.'_label', $this->_filterhelp);
        }
        $mform->addElement('html','<br clear=left>');
        // Create a table for the dynamic table to be located
        $mform->addElement('html','<div class=fieldtable'.$this->_block_instance.' id=fieldtable'.$this->_block_instance.'></div>');

        $path = $CFG->wwwroot.'/blocks/php_report/instances/' . $this->_reportname . '/';

        // Add hidden fields that javascript can update
        $mform->addElement('hidden','field'.$this->_block_instance,null,'id=id_field'.$this->_block_instance);
        $mform->addElement('hidden','fieldidlist'.$this->_block_instance,null,'id=fieldidlist'.$this->_block_instance);
        $mform->addElement('hidden','fieldnamelist'.$this->_block_instance,null,'id=fieldnamelist'.$this->_block_instance);

        // Add a listener so the dynamic table gets preloaded on config params
        $mform->addElement('html', '<script type="text/javascript">
                                   YAHOO.util.Event.addListener(window, "load",
                                   customfields_initializeTable(\''.
                                   $this->_block_instance.'\',\'init\',\''.
                                   $path.'\',\''.
                                   base64_encode(serialize($this->_fieldidlist)).'\',\''.
                                   base64_encode(serialize($this->_fieldnamelist)).'\',\''.
                                   $this_scheduled.'\'));
                                    </script>');

        // Add onreset action to form to allow reset of field id and name list
        $mform->_attributes['onreset'] = 'customfields_initializeTable(\''.$this->_block_instance.'\',\'init\',\''.$path.'\');';

        // Create popuplink with current list of custom fields included in custom multi filter list
        $mform->addElement('html','<div align="center"><br>
                            <a href="javascript:void(0);" onclick="customfields_updateFieldLists(\''.$this->_block_instance.'\', \'add\', \''.$CFG->wwwroot.'/blocks/php_report/instances/' . $this->_reportname . '/coursefieldpopup.php\');">'.
                            get_string('add_field','rlreport_'.$this->_reportname).'</a></div>');
    }

    /*
     * Update field id and name lists
     * @param   pointer to array    $fieldidlist    pointer to array of all the custom field ids included in the filter
     * @return  none
     */
    function get_names() {
        global $CFG;

        require_once "{$CFG->dirroot}/curriculum/lib/customfield.class.php";

        //Use this object's field id and name list
        $fieldidlist = $this->_fieldidlist;
        $fieldnamelist = $this->_fieldnamelist;

        // Get custom course field names
        $context = context_level_base::get_custom_context_level('course', 'block_curr_admin');
        $fields = field::get_for_context_level($context);
        $fields = $fields ? $fields : array();

        //Unserialize fieldidlist to check against field list
        if (isset($fieldidlist)) {
            $fieldidlist = unserialize(base64_decode($fieldidlist));
        }

        foreach ($fieldidlist as $fieldid) {
            $fieldnamelist[] = $fields[$fieldid]->categoryname.' - '.$fields[$fieldid]->name;
        }

        //Reset this object's field id and name list with updated values
        $this->_fieldnamelist = $fieldnamelist;
        $this->_fieldidlist = $fieldidlist;
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
        $a->value    = ': ';
        $a->operator = '';

        return get_string('selectlabel', 'filters', $a);
    }

    /**
     * Return a boolean to indicate whether or not this filter is displayed
     * depending upon whether any custom fields are found for this user
     * @param string       $field_type  type of custom field to check
     * @return boolean  true if the filter is to show
     */
    function check_for_custom_fields($field_type) {

        // Get custom course fields by context level
        $context = context_level_base::get_custom_context_level($field_type, 'block_curr_admin');
        $fields = field::get_for_context_level($context);
        $fields = $fields ? $fields : array();
        $testfields = array();
        foreach ($fields as $field) {
            //make sure the current user can access this field in at least one
            //course context
            $owners = field_owner::get_for_field($field);
            if (!block_php_report_field_accessible($owners)) {
                continue;
            }

            return true;
        }

        return false;
    }
}

