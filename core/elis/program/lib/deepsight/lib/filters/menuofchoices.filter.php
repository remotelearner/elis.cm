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
 * @copyright  (C) 2013 Remote Learner.net Inc http://www.remote-learner.net
 * @author     James McQuillan <james.mcquillan@remote-learner.net>
 *
 */

/**
 * A filter providing a menu of pre-populated choices.
 */
class deepsight_filter_menuofchoices extends deepsight_filter_standard {
    const TYPE = 'searchselect';
    protected $endpoint = '';
    protected $choices = array();

    /**
     * Constructor.
     *
     * @param moodle_database &$DB       The global moodle_database object.
     * @param string          $name      The name of the filter. Used when receiving data to determine where to send the data.
     * @param string          $label     The label that will be displayed on the filter button.
     * @param array           $fielddata An array of field information used by the filter. Formatted like [field]=>[label].
     *                                   Usually this is what field the filter will use to affect the datatable results, but refer
     *                                   to the individual filter for specifics.
     * @param string          $endpoint  The endpoint to make requests to, when searching for a choice.
     */
    public function __construct(moodle_database &$DB, $name, $label, array $fielddata = array(), $endpoint=null) {
        if (empty($endpoint)) {
            throw new Exception("You must specify an endpoint URL for menuofchoices filter '{$name}'");
        }
        $this->endpoint = (strpos($endpoint, '?') !== false) ? $endpoint.'&m=filter' : $endpoint.'?m=filter';
        parent::__construct($DB, $name, $label, $fielddata);
    }

    /**
     * Set the available choices.
     *
     * @param array $choices An array of choices, indexed by internal choice ID, with choice label as value.
     */
    public function set_choices(array $choices) {
        $this->choices = $choices;
    }

    /**
     * Gets the available choices.
     *
     * @return array The array of choices.
     */
    public function get_choices() {
        return $this->choices;
    }

    /**
     * Responds to a search request for a choice.
     *
     * @return string a JSON response string.
     */
    public function respond_to_js() {
        $requestval = required_param('val', PARAM_CLEAN);
        $ret = array();
        $requestval = strtolower($requestval);
        foreach ($this->choices as $choice) {
            if (strpos(strtolower($choice), $requestval) !== false) {
                $ret[] = array('id' => $choice, 'label' => $choice);
            }
        }
        return safe_json_encode($ret);
    }

    /**
     * Gets filter SQL based on the assigned fields, and chosen values.
     *
     * @param mixed $data The data from the filter send from the javascript.
     * @return array An array of filter SQL, and SQL parameters.
     */
    public function get_filter_sql($data) {
        if (empty($data) || !is_array($data)) {
            return array('', array());
        } else {
            $params = array();
            foreach ($data as $val) {
                if (!is_numeric($val) && !is_string($val)) {
                    return array('', array());
                }
                $params[] = $val;
            }
            reset($this->fields);
            $field = key($this->fields);
            return array(
                $field.' IN ('.implode(',', array_fill(0, count($data), '?')).')',
                $params
            );
        }
    }

    /**
     * Returns options for the javascript object.
     *
     * @return array An array of options.
     */
    public function get_js_opts() {
        $opts = array(
            'name' => $this->name,
            'label' => $this->label,
            'dataurl' => $this->endpoint,
            'initialchoices' => array(),
            'lang_search' => get_string('search', 'elis_program'),
            'lang_selected' => get_string('selected', 'elis_program'),
            'lang_all' => get_string('all', 'moodle'),
        );
        $i = 0;
        foreach ($this->choices as $val => $label) {
            $opts['initialchoices'][] = array('label' => $label, 'id' => $val);
            if ($i >= 3) {
                break;
            }
            $i++;
        }
        return $opts;
    }
}