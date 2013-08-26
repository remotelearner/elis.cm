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
 * A switch filter to change whether enrolled users, non-enrolled users, or all users are displayed on an enrolments table.
 */
class deepsight_filter_enrolmentstatus extends deepsight_filter_switch {
    const TYPE = 'switch';

    protected $choices = array(
        'notenrolled' => '',
        'enrolled' => '',
        'all' => ''
    );

    protected $default = '';

    /**
     * Sets the available choices - not enrolled, enrolled, or all.
     */
    protected function postconstruct() {
        $this->choices['notenrolled'] = get_string('ds_notenrolled', 'elis_program');
        $this->choices['enrolled'] = get_string('ds_enrolled', 'elis_program');
        $this->choices['all'] = get_string('ds_allusers', 'elis_program');
    }

    /**
     * Set the default choice.
     *
     * @param string $default The default choice (corresponds to an index of $this->choices).
     */
    public function set_default($default) {
        $this->default = $default;
    }

    /**
     * Set the class ID we're using to filter.
     *
     * @param int $classid The class ID to set.
     */
    public function set_classid($classid) {
        if (is_int($classid)) {
            $this->classid = $classid;
        }
    }

    /**
     * Get the set class ID.
     *
     * @return int The current class ID.
     */
    public function get_classid() {
        return $this->classid;
    }

    /**
     * Get SQL to show only users that fit into the currently selected option.
     *
     * Will force an enrolment to be present, force an enrolment to not be preset, or return empty SQL.
     *
     * @param mixed $data The data from the filter send from the javascript.
     * @return array An array consisting of filter sql as index 0, and an array of parameters as index 1
     */
    public function get_filter_sql($data) {
        if (empty($this->classid)) {
            throw new Exception('No classid set for enrolmentstatus filter.');
        }

        $data = (!empty($data) && !empty($data[0])) ? $data[0] : $this->default;

        if (isset($data) && $data === 'notenrolled') {
            return array(
                '(SELECT id FROM {crlm_class_enrolment} WHERE classid = ? AND userid=element.id) IS NULL',
                array($this->classid)
            );
        } else if (isset($data) && $data === 'enrolled') {
            return array(
                '(SELECT id FROM {crlm_class_enrolment} WHERE classid = ? AND userid=element.id) IS NOT NULL',
                array($this->classid)
            );
        } else {
            return array('', array());
        }
    }

    /**
     * Get the enrolment ID, if available.
     *
     * @return array An array consisting of the one select field.
     */
    public function get_select_fields() {
        return array('enrol.id AS enrol_id');
    }
}