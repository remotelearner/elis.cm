<?php //$Id$

require_once($CFG->dirroot.'/elis/core/lib/filtering/lib.php');

/**
 * Generic filter based on a list of values.
 */
class generalized_filter_dependentselect extends generalized_filter_type {

    /**
     * options for the list values
     */
    var $_options = array();

    var $_field;

    var $_default = null;

    var $_numeric = false;

    var $_report_path = '';

    var $_filename = 'childoptions.php';

    var $_isrequired = false;

    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     * @param array $options select options
     * @param mixed $default option
     */
    function generalized_filter_dependentselect($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {
        global $CFG;

        parent::generalized_filter_type($uniqueid, $alias, $name, $label, $advanced,
                                        !empty($options['help'])
                                        ? $options['help']
                                        : array('simpleselect', $label, 'filters'));
        $this->_field   = $field;

        $extrafields = array(
            '_options'     => 'choices',
            '_default'     => 'default',
            '_numeric'     => 'numeric',
            '_report_path' => 'report_path',
            '_isrequired'  => 'required',
            '_filename'    => 'filename',
        );

        foreach ($extrafields as $var => $extra) {
            if (array_key_exists($extra, $options)) {
                $this->$var = $options[$extra];
            }
        }
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     */
    function setupForm(&$mform) {
        global $CFG;

        require_js(array('yui_yahoo',
                         'yui_dom',
                         'yui_event',
                         'yui_connection',
                         'yui_json',
                         "{$CFG->wwwroot}/curriculum/js/dependentselect.js"),
                         true);

        $options_array = $this->get_main_options();

        $fullpath = $this->_report_path . $this->_filename;
        $parent   = $this->_uniqueid .'_parent';

        $js = "dependentselect_updateoptions('{$parent}','{$this->_uniqueid}','{$fullpath}');";
        $objs = array();
        $objs[] =& $mform->createElement('select', $this->_uniqueid.'_parent', null, $options_array,
                                         array('onChange'=>$js));
        $objs[] =& $mform->createElement('select', $this->_uniqueid, null, $this->_options);
        $grp =& $mform->addElement('group', $this->_uniqueid.'_grp', $this->_label, $objs, '<br/>', false);
        $grp->setHelpButton($this->_filterhelp);

        if (!is_null($this->_default)) {
            $mform->setDefault($this->_uniqueid, $this->_default);
        }
        if ($this->_advanced) {
            $mform->setAdvanced($this->_uniqueid.'_grp');
        }

        // Always refresh the child pulldown
        $mform->addElement('html','<script>'.$js.'</script>');

    }

    /**
     * Retrieves data from the form data
     * @param object $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    function check_data($formdata) {
        $field    = $this->_uniqueid;

        if (array_key_exists($field, $formdata)) {
            return array('value' => (string)$formdata->$field);
        }

        return false;
    }

    function get_report_parameters($data) {

        $return_value = array('value' => $data['value'],
                              'numeric' => $this->_numeric);

        return $return_value;
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        $full_fieldname = $this->get_full_fieldname();

        $value = addslashes($data['value']);
        if(empty($data['numeric'])) {
            $value = "'{$value}'";
        }

    }

    /**
     * Override this method to return the main pulldown option
     * @return array List of options keyed on id
     */
    function get_main_options() {
        return array('0' => 'Select...');
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    function get_label($data) {
        if (!empty($this->_options)) {
            foreach ($this->_options as $key => $value) {
                if ($key == $data['value']) {
                    return "{$this->_label}: {$value}";
                }
            }
            return "{$this->_label}: {$data['value']}";
        }
        return "{$this->_label}: ". get_string('off'); // TBD: 'none'
    }

    function  get_default_values($filter_data) {
        if (isset($this->_default)) {
            return array($this->_uniqueid => $this->_default);
        }
        return parent::get_default_values($filter_data);
    }
}

?>
