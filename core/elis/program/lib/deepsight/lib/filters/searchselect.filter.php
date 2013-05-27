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
 * A filter providing a menu of choices based on the values in the database for the set fielddata.
 */
class deepsight_filter_searchselect extends deepsight_filter_standard {
    const TYPE = 'searchselect';
    protected $endpoint = '';
    protected $choices = array();

    /**
     * Constructor.
     *
     * @param moodle_database &$DB The global moodle_database object.
     * @param string $name The name of the filter. Used when receiving data to determine where to send the data.
     * @param string $label The label that will be displayed on the filter button.
     * @param array $fielddata An array of field information used by the filter. Formatted like [field]=>[label].
     *                         Usually this is what field the filter will use to affect the datatable results, but refer
     *                         to the individual filter for specifics.
     * @param string $endpoint The endpoint to make requests to, when searching for a choice.
     * @param string $choicestable The table to use for available choices.
     * @param string $choicesfield The the field to use for available choices.
     */
    public function __construct(moodle_database &$DB, $name, $label, array $fielddata = array(), $endpoint=null, $choicestable='', $choicesfield='') {
        if (empty($endpoint)) {
            throw new Exception("You must specify an endpoint URL for searchselect filter '{$name}'");
        }
        $this->endpoint = (strpos($endpoint, '?') !== false) ? $endpoint.'&m=filter' : $endpoint.'?m=filter';

        if (empty($choicestable) || !is_string($choicestable)) {
            throw new Exception('You must specify a non-empty string for the choices table parameter.');
        }
        $this->choicestable = $choicestable;

        if (empty($choicesfield) || !is_string($choicesfield)) {
            throw new Exception('You must specify a non-empty string for the choices field parameter');
        }
        $this->choicesfield = $choicesfield;

        parent::__construct($DB, $name, $label, $fielddata);
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
     * Sets internal options based on the different occurences in the table.
     */
    protected function postconstruct() {
        $records = $this->DB->get_recordset_sql('
            SELECT DISTINCT id, '.$this->choicesfield.', count(1) as count
              FROM {'.$this->choicestable.'}
             WHERE '.$this->choicesfield.' != ""
          GROUP BY '.$this->choicesfield.'
          ORDER BY count DESC
             LIMIT 0,5');
        $choicesfield = $this->choicesfield;
        foreach ($records as $record) {
            $choice = trim($record->$choicesfield);
            if (!empty($choice)) {
                $this->choices[$record->$choicesfield] = $record->$choicesfield;
            }
        }
    }

    /**
     * Responds to a search request for a choice.
     *
     * @return string a JSON response string.
     */
    public function respond_to_js() {
        $requestval = required_param('val', PARAM_CLEAN);
        $ret = array();
        $sql = 'SELECT DISTINCT '.$this->choicesfield.'
                  FROM {'.$this->choicestable.'}
                 WHERE '.$this->choicesfield.' LIKE ?';
        $vals = $this->DB->get_recordset_sql($sql, array('%'.$requestval.'%'));
        $choicesfield = $this->choicesfield;
        foreach ($vals as $val) {
            $ret[] = array('id' => $val->$choicesfield, 'label' => ucwords($val->$choicesfield));
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
        foreach ($this->choices as $val => $label) {
            $opts['initialchoices'][] = array('label' => $label, 'id' => $val);
        }
        return $opts;
    }
}