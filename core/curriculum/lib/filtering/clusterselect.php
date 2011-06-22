<?php //$Id$

require_once($CFG->dirroot.'/user/filters/lib.php');
require_once($CFG->dirroot.'/curriculum/lib/filtering/equalityselect.php');
//needed for execution mode constants
require_once($CFG->dirroot.'/blocks/php_report/php_report_base.php');

/**
 * Generic filter based on a list of values.
 */
class generalized_filter_clusterselect extends generalized_filter_equalityselect {
    /**
     * options for the list values
     */
    var $_options;

    var $_field;

    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     * @param array $options select options
     */
    function generalized_filter_clusterselect($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {
        global $USER;

        $options['numeric'] = true;

        //expected by the parent class
        $options['choices'] = array();

        parent::generalized_filter_equalityselect($uniqueid, $alias, $name, $label, $advanced, $field, $options);
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     */
    function setupForm(&$mform) {
        global $USER;

        $choices_array = array();

        //figure out which capability to check
        if ($this->execution_mode == php_report::EXECUTION_MODE_SCHEDULED) {
            $capability = 'block/php_report:schedule';
        } else {
            $capability = 'block/php_report:view';
        }

        //obtain all course contexts where this user can view reports
        $contexts = get_contexts_by_capability_for_user('user', $capability, $USER->id);

        $context_array = array('contexts' => $contexts);

        if($records = cluster_get_listing('name', 'ASC', 0, 0, '', '', $context_array)) {
            foreach($records as $record) {
                if ($record->parent == 0)
                {
                    $choices_array[$record->id] = $record->name;
                    $child_array = $this->find_child_clusters($records, $record->id);
                    $choices_array = $this->merge_array_keep_keys($choices_array,$child_array);
                }
            }
        }

        //explicitly set the list of available options
        $this->_options = $choices_array;

        parent::setupForm($mform);
    }

    /**
     * Returns the child clusters of the given parent
     * @param array $records complete cluster objects array
     * @param int $parentid the parent cluster id
     * @param int $indent the parent cluster indentation level
     * @return array child clusters array
     */
    function find_child_clusters($records, $parentid, $indent=0) {
        $choices_array = array();
        $indent++;

        if ($indent < 1000) { // avoid infinite recursion
            foreach ($records as $record) {
                if ($record->parent == $parentid) {
                    // shorten really long cluster names
                    $name = (strlen($record->name) > 100)
                          ? substr($record->name,0,100) . '...'
                          : $record->name;
                    $choices_array[$record->id] = str_repeat('- ', $indent) . $name;

                    // recursively find child clusters
                    $child_array = $this->find_child_clusters($records, $record->id, $indent);
                    $choices_array = $this->merge_array_keep_keys($choices_array, $child_array);
                }
            }
        }

        return $choices_array;
    }

    /**
     * Returns the merged array of two given arrays without renumbering the key values
     * @param array $array1 first array
     * @param array $array2 second array
     * @return array the merged array
     */
    function merge_array_keep_keys($array1, $array2) {
        $merged_array = $array1;

        foreach ($array2 as $array2_key=>$array2_value) {
            $merged_array[$array2_key] = $array2_value;
        }

        return $merged_array;
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        global $CFG;

        $full_fieldname = $this->get_full_fieldname();

        if($data['operator'] == generalized_filter_equalityselect::$OPERATOR_IS_EQUAL_TO) {
            $operator = 'IN';
        } else if($data['operator'] == generalized_filter_equalityselect::$OPERATOR_NOT_EQUAL_TO) {
            $operator = 'NOT IN';
        } else {
            //error call
            print_error('invalidoperator', 'block_php_report');
        }


        return "{$full_fieldname} $operator
                (SELECT inner_usercluster.userid FROM
                 {$CFG->prefix}crlm_usercluster inner_usercluster
                 WHERE inner_usercluster.clusterid = {$data['value']})";
    }

}

