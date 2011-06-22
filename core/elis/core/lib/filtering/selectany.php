<?php

require_once($CFG->dirroot.'/curriculum/lib/filtering/selectall.php');

/**
 * This filter is a child to the selectall filter
 * and was created to deal with the any/all situation
 * where LEFT JOINS in report queries lead to the
 * need to check the filter field for IS NOT NULL
 * Specifically for the cluster filter at this time
 */
class generalized_filter_selectany extends generalized_filter_selectall {

    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     * @param array $options select options
     */
    function generalized_filter_selectany($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {
        $new_options = array();
        if (!empty($options)) {
            foreach ($options as $id => $value) {
                $new_options[$id] = $value;
            }
        }

        if (empty($new_options['choices'])) {
            $new_options['choices'] = array(0 => get_string('report_filter_all', 'block_curr_admin'));
        } else {
            $new_options['choices'] = array(0 => get_string('report_filter_all', 'block_curr_admin')) + $options['choices'];
        }

        parent::__construct($uniqueid, $alias, $name, $label, $advanced, $field, $new_options);
    }


    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     */
    function setupForm(&$mform) {
        if (!empty($this->_anyvalue)) {
            $choices = array(''=>$this->_anyvalue) + $this->_options;
        } else {
            // Change display for anyvalue
            $choices = array(''=>get_string('report_filter_anyvalue', 'block_curr_admin')) + $this->_options;
        }
        $mform->addElement('select', $this->_uniqueid, $this->_label, $choices);
        $mform->setHelpButton($this->_uniqueid, array('simpleselect', $this->_label, 'filters'));
        if ($this->_advanced) {
            $mform->setAdvanced($this->_uniqueid);
        }
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        $full_fieldname = $this->get_full_fieldname();

        $value = $data['value'];

        if (is_numeric($value) &&  $value == 0) {
            return "{$full_fieldname} IS NOT NULL";
        }

        return parent::get_sql_filter($data);
    }
}

?>