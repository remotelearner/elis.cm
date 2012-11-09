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

require_once($CFG->dirroot.'/user/filters/lib.php');

/**
 * Generalized Filter Display Table
 *
 * This function outputs a table with rows of checkboxes in it to modify the display of a report
 * in ways other than actually filtering the sql.  A prime example is to add additional output
 * columns to the display.
 *
 * @author Tyler Bannister <tyler.bannister@remote-learner.net>
 * @copyright  (C) 2008-2011 Remote Learner.net Inc http://www.remote-learner.net
 */
class generalized_filter_display_table extends generalized_filter_type {
    protected $options;
    protected $languagefile = 'elis_core';
    protected $help         = 'display_table';


    /**
     * Constructor
     *
     * @param string $uniqueid A unique id
     * @param string $alias    An alias
     * @param string $name     A name
     * @param string $label    A label
     * @param bool   $advanced Whether it should be hidden when advanced items are hidden
     * @param object $help     The help object
     * @param array  $options  An array of options
     */
    function generalized_filter_display_table($uniqueid, $alias, $name, $label, $advanced,
                                              $help = null, $options = array()) {
        $this->options = $options;

        if (array_key_exists('lang', $options)) {
            $this->languagefile = $options['lang'];
        }

        if (array_key_exists('help', $options)) {
            $this->help = $options['help'];
        }

        parent::generalized_filter_type($uniqueid, $alias, $name, $label, $advanced, $help);
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    function get_label($data) {
        $value = $data['value'];
        $list = array();

        foreach ($this->options['fields'] as $group => $fields) {
            if (is_array($fields)) {
                foreach ($fields as $field => $data) {
                    if ($field == 'custom') {
                        $elements = $this->get_custom_fields($data);
                    } else {
                        $elements = array($field => get_string($data, $this->languagefile));
                    }
                    foreach ($elements as $name => $label) {
                        $list[] .= $label .' = '. $value[$name];
                    }
                }
            }
        }

        $a = new object();
        $a->label    = $this->_label;
        $a->value    = implode(",<br />\n", $list);
        $a->operator = get_string('isequalto','filters');

        return get_string('selectlabel', 'filters', $a);
    }

    /**
     * Returns the condition to be used with SQL where
     *
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        return null;
    }

    /**
     * Add controls for custom fields
     *
     * @param array $customfields An array with key = table and value = field(s) wanted
     * @return array A list of custom fields
     */
    function get_custom_fields($customfields) {
        $list = array();

        if (is_array($customfields)) {

            foreach ($customfields as $type => $fields) {
                $ctxlvl = context_elis_helper::get_level_from_name($type);
                $ctxtfields = field::get_for_context_level($ctxlvl);
                $ctxtfields = $ctxtfields ? $ctxtfields : array();

                if ((! is_array($fields)) && ($fields == 'all')) {
                    foreach ($ctxtfields as $id => $field) {
                        // ELIS-5862: skip password fields!
                        $fieldobj = new field($field->id);
                        if (!$fieldobj || !isset($fieldobj->owners['manual'])
                            || !($manual = new field_owner($fieldobj->owners['manual'])) ||
                            $manual->param_control == 'password') {
                            continue;
                        }
                        $list['custom_'. $id] = $field->name;
                    }
                    return $list;
                } else if (! is_array($fields)) {
                    $fields = array($fields);
                }

                foreach ($ctxtfields as $id => $field) {
                    foreach ($fields as $wanted) {
                        if ($field->shortname == $wanted) {
                            $list['custom_'. $id] = $field->name;
                        }
                    }
                }
            }
        }
        return $list;
    }

    /**
     * Adds controls specific to this filter in the form.
     *
     * @param object $mform a MoodleForm object to setup
     */
    function setupForm(&$mform) {
        global $OUTPUT;

        $html = array();
        $html[] = '<div class="fitem">';
        $html[] = '<div class="fitemtitle">';
        $html[] = $this->_label;
        $htnl[] = $OUTPUT->help_icon($this->help, $this->languagefile);
        $html[] = '</div>';
        $html[] = '<div class="felement">';
        $html[] = '<table class="display_table">';
        $html[] = '<tr>';
        $html[] = '<th>'. get_string($this->options['title'], $this->languagefile) .'</th>';
        $html[] = '<th>'. get_string('display', $this->languagefile) .'</th>';
        $html[] = '</tr>';
        $mform->addElement('html', implode("\n", $html));

        foreach ($this->options['fields'] as $group => $fields) {
            $grouphead = '<tr><td class="section_heading" colspan="2">'
                       . get_string($group, $this->languagefile) .'</td></tr>';
            $mform->addElement('html', $grouphead);

            if (is_array($fields)) {

                foreach ($fields as $field => $data) {
                    if ($field == 'custom') {
                        $elements = $this->get_custom_fields($data);
                    } else {
                        $elements = array($field => get_string($data, $this->languagefile));
                    }

                    foreach ($elements as $name => $label) {
                        $mform->addElement('html', '<tr><td></td><td>'. $label .'</td><td>');
                        $mform->addElement('advcheckbox', $this->_uniqueid .'['. $name .']');
                        $mform->addElement('html', '</td></tr>');
                    }
                }
            }
        }

        $mform->addElement('html', '</table></div></div>');
    }

    /**
     * Placeholder function
     *
     * @param array $data Report parameters?
     */
    function get_report_parameters($data) {
        //obsolete
    }

    /**
     * Retrieves data from the form data
     *
     * @param object $formdata Data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    function check_data($formdata) {
        $key = $this->_uniqueid;

        if (isset($formdata->$key)) {
            return array('value' => $formdata->$key);
        }

        return false;
    }

    /**
     * Takes a set of submitted values and retuns this filter's default values
     * for them in the same structure (used to reset the filtering form)
     */
    function get_default_values($filter_data) {
        //our data map of field shortnames to values
        $default_values = array();

        //set all fields to the default checkbox value of zero
        foreach ($filter_data as $key => $value) {
            $default_values[$key] = '0';
        }

        //return our data mapping
        return $default_values;
    }
}
