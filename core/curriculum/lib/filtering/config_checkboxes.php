<?php //$Id$
/**
 * checkboxes.php - PHP Report filter
 *
 * Group of checkboxes (TBD: add layout options, columns/row designation ...)
 * for selecting choices for DB field using SQL 'IN' statement (= if only one)
 * options include:
 *   ['choices'] = array(key = 'DB field value', value = 'display string' ...)
 *   ['defaults'] = array('DB field value', ... ) - used when no options selected (optional)
 *   ['checked'] = array('DB field value', ...) - initially checked on form (optional)
 *   ['heading'] = string - the checkbox group heading (optional, raw html)
 *   ['footer'] = string - the checkbox group footer (optional, raw html)
 *   ['numeric'] = boolean - true if DB field is numeric,
 *                           false (the default) -> string
 *
 * @author Brent Boghosian <brent.boghosian@remote-learner.net>
 * @version $Id$
 * @package curriculum/lib/filtering
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 */

require_once($CFG->dirroot.'/user/filters/lib.php');
require_once(CURMAN_DIRLOCATION . '/lib/filtering/checkboxes.php');

/**
 * Generic checkbox filter based on a list of values ...
 */
class generalized_filter_config_checkboxes extends generalized_filter_checkboxes {
    /**
     * Array - options for the checkboxes:
     * 'choices' => array
     *     keys are checkbox 'values' numbers or strings
     *     values are checkbox label strings
     * 'checked' => array of 'keys' only (from 'choices') to be checked
     * 'defaults' => array
     *     'keys' only (from 'choices') to be used if NO choices are checked!
     * 'heading' => string [optional] - checkbox group heading
     * 'footer' => string [optional] - checkbox group footer
     * 'numeric' => boolean - true if 'keys' should be numerically compared
     *                        optional, defaults to false (not numeric)
     */
    var $_options;

    var $_field;

    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table field name
     * @param array $options mixed array of checkbox options - see above
     */
    function generalized_filter_config_checkboxes($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {
        parent::generalized_filter_checkboxes($uniqueid, $alias, $name, $label, $advanced, $field, $options);
        $this->_field   = $field;
        $this->_options = $options;
    }

    /**
     * Retrieves data from the form data
     * @param object $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    function check_data($formdata) {
        $field = $this->_uniqueid;
        $retval = null;
        // check for checkboxes checked ...
        foreach ($this->_options['choices'] as $key => $value) {
            if (!empty($formdata->{$field.'_'.$key})) {
                if (!empty($retval)) {
                    $retval .= ', ';
                }
                $retval .= empty($this->_options['numeric'])
                           ? "'". $key ."'" : $key;
            }
        }

        if (empty($retval) && !empty($this->_options['defaults'])) {
            // if none checked use default
            foreach ($this->_options['defaults'] as $default) {
                if (!empty($retval)) {
                    $retval .= ', ';
                }
                $retval .= empty($this->_options['numeric'])
                           ? "'". $default."'" : $default;
            }
        }

        return empty($retval) ? false : array('value' => (string)$retval);
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        // No processing
        return null;
    }
}

?>
