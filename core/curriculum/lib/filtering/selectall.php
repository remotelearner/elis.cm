<?php

require_once($CFG->dirroot.'/curriculum/lib/filtering/simpleselect.php');

/**
 * Generic filter based on a list of values, which also has a "select all" option.
 */
class generalized_filter_selectall extends generalized_filter_simpleselect {
    
    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     * @param array $options select options
     */
    function generalized_filter_selectall($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {        
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
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        if ($data['value'] === 0) {
            return 'TRUE';
        }
        
        return parent::get_sql_filter($data);
    }
}

?>