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
 * A filter providing selection of a single value.
 */
class deepsight_filter_switch extends deepsight_filter_standard {
    const TYPE = 'switch';
    protected $choices = array();

    /**
     * Gets filter SQL based on the assigned fields, and chosen values.
     *
     * @param mixed $data The data from the filter send from the javascript.
     * @return array An array of filter SQL, and SQL parameters.
     */
    public function get_filter_sql($data) {
        if (empty($data) || !is_array($data) || !isset($data[0]) || (!is_numeric($data[0]) && !is_string($data[0]))) {
            return array('', array());
        } else {
            reset($this->fields);
            $field = key($this->fields);
            return array($field.' = ?', array($data[0]));
        }
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
     * Returns options for the javascript object.
     *
     * @return array An array of options.
     */
    public function get_js_opts() {
        $opts = array(
            'name' => $this->name,
            'label' => $this->label,
            'choices' => array()
        );
        foreach ($this->choices as $val => $label) {
            $opts['choices'][] = array('label' => $label, 'value' => $val);
        }
        return $opts;
    }
}