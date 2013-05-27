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
 * @package    elis
 * @subpackage programmanager
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2013 Remote Learner.net Inc http://www.remote-learner.net
 * @author     James McQuillan <james.mcquillan@remote-learner.net>
 *
 */

/**
 * A datatable implementation for a list of users currently enrolled in a class.
 */
class deepsight_datatable_enrolled extends deepsight_datatable_user {
    protected $classid;

    /**
     * Constructor.
     *
     * Performs the following functions:
     *     - Sets internal data.
     *     - Runs $this->populate();
     *
     * @see deepsight_datatable::__construct();
     * @uses deepsight_datatable_standard::populate()
     * @param moodle_database &$DB      The global moodle_database object.
     * @param string          $name     The name of the table - used in various places to tie together parts for the same table.
     * @param string          $endpoint The URL where all AJAX requests will be sent. This will be appended with an 'm' GET or
     *                                  POST variable for different request types.
     * @param string          $uniqid   A unique identifier for a datatable session.
     * @param int             $classid  The classid we're enrolling students into.
     */
    public function __construct(moodle_database &$DB, $name, $endpoint, $uniqid=null, $classid=null) {
        if (!is_numeric($classid)) {
            throw new Exception('Invalid class ID received when creating enrolments datatable.');
        }
        $this->classid = (int)$classid;
        parent::__construct($DB, $name, $endpoint, $uniqid);
    }

    /**
     * Gets the edit and unenrolment actions.
     *
     * @return array An array of deepsight_action objects that will be available for each element.
     */
    public function get_actions() {
        $actions = parent::get_actions();

        $editaction = new deepsight_action_enroledit($this->DB, 'edit');
        $editaction->endpoint = (strpos($this->endpoint, '?') !== false)
            ? $this->endpoint.'&m=action'
            : $this->endpoint.'?m=action';

        $unenrolaction = new deepsight_action_unenrol($this->DB, 'unenrol');
        $unenrolaction->endpoint = (strpos($this->endpoint, '?') !== false)
            ? $this->endpoint.'&m=action'
            : $this->endpoint.'?m=action';

        array_unshift($actions, $editaction, $unenrolaction);
        return $actions;
    }

    /**
     * Gets an array of available filters.
     *
     * @return array An array of deepsight_filter objects that will be available.
     */
    protected function get_filters() {
        $langenrolmenttime = get_string('enrolment_time', 'elis_program');
        $langcompletetime = get_string('completion_time', 'elis_program');
        $langgrade = get_string('student_grade', 'elis_program');
        $langcredits = get_string('student_credits', 'elis_program');
        $langcompletestatus = get_string('student_status', 'elis_program');

        $completestatus = new deepsight_filter_menuofchoices($this->DB, 'completestatus', $langcompletestatus,
                                                             array('enrol.completestatusid' => $langcompletestatus),
                                                             $this->endpoint);
        $choices = array(
            STUSTATUS_NOTCOMPLETE => 'Not Complete',
            STUSTATUS_PASSED => 'Passed',
            STUSTATUS_FAILED => 'Failed',
        );
        $completestatus->set_choices($choices);

        $filters = array(
            new deepsight_filter_date($this->DB, 'enrolmenttime', $langenrolmenttime,
                                      array('enrol.enrolmenttime' => $langenrolmenttime)),
            $completestatus,
            new deepsight_filter_date($this->DB, 'completetime', $langcompletetime,
                                      array('enrol.completetime' => $langcompletetime)),
            new deepsight_filter_textsearch($this->DB, 'grade', $langgrade, array('enrol.grade' => $langgrade)),
            new deepsight_filter_textsearch($this->DB, 'credits', $langcredits, array('enrol.credits' => $langcredits)),
        );

        $filters = array_merge(parent::get_filters(), $filters);
        return $filters;
    }

    /**
     * Gets an array of initial filters.
     *
     * @return array An array of deepsight_filter $name properties that will be present when the user first loads the page.
     */
    protected function get_initial_filters() {
        return array('idnumber', 'name', 'enrolmenttime', 'completestatus', 'completetime');
    }

    /**
     * Get an array of columns that will always be present.
     *
     * @return array An array of fixed columns formatted like [table-aliased field name (i.e. element.id)]=>[column label]
     */
    protected function get_fixed_columns() {
        return array(
            'element.idnumber' => get_string('student_idnumber', 'elis_program'),
            'element.firstname' => get_string('firstname', 'moodle'),
            'element.lastname' => get_string('lastname', 'elis_program'),
            'enrol.enrolmenttime' => get_string('enrolment_time', 'elis_program'),
            'enrol.completetime' => get_string('completion_time', 'elis_program'),
            'enrol.completestatusid' => get_string('student_status', 'elis_program'),
            'enrol.grade' => get_string('student_grade', 'elis_program'),
            'enrol.credits' => get_string('student_credits', 'elis_program'),
            'enrol.locked' => get_string('student_locked', 'elis_program'),
        );
    }

    /**
     * Get an array of options to pass to the deepsight_datatable javascript object.
     *
     * Enables drag and drop and multiselect.
     *
     * @return array An array of options, ready to be passed to $this->get_init_js()
     */
    public function get_table_js_opts() {
        $opts = parent::get_table_js_opts();
        $opts['dragdrop'] = true;
        $opts['multiselect'] = true;

        return $opts;
    }

    /**
     * Gets an array of javascript files needed for operation.
     *
     * @see deepsight_datatable::get_js_dependencies()
     */
    public function get_js_dependencies() {
        $deps = parent::get_js_dependencies();
        $deps[] = '/elis/program/lib/deepsight/js/actions/deepsight_action_confirm.js';
        $deps[] = '/elis/program/lib/deepsight/js/actions/deepsight_action_enroledit.js';
        return $deps;
    }

    /**
     * Formats various attributes for human consumption.
     *
     * Changes the locked int to yes/no, formated enrolment and completion times into date strings, converts completion status
     * to human-readable label.
     *
     * @param array $row An array for a single result.
     * @return array The transformed result.
     */
    protected function results_row_transform(array $row) {
        $row = parent::results_row_transform($row);

        // Locked 0,1 => no, yes.
        if (isset($row['enrol_locked'])) {
            $row['enrol_locked'] = ($row['enrol_locked'] == 1) ? get_string('yes', 'moodle') : get_string('no', 'moodle');
        }

        // Format enrolment time.
        if (isset($row['enrol_enrolmenttime'])) {
            $row['enrol_enrolmenttime'] = ds_process_displaytime($row['enrol_enrolmenttime']);
        }

        // Format completion time.
        if (isset($row['enrol_completetime'])) {
            $statusiscomplete = (isset($row['enrol_completestatusid']) && $row['enrol_completestatusid'] != STUSTATUS_NOTCOMPLETE)
                    ? true : false;
            $row['enrol_completetime'] = ($statusiscomplete === true) ? ds_process_displaytime($row['enrol_completetime']) : '';
        }

        // Completion status ints to labels.
        if (isset($row['enrol_completestatusid'])) {
            $choices = array(
                STUSTATUS_NOTCOMPLETE => get_string('n_completed', 'elis_program'),
                STUSTATUS_PASSED => get_string('passed', 'elis_program'),
                STUSTATUS_FAILED => get_string('failed', 'elis_program'),
            );
            $row['enrol_completestatusid'] = $choices[$row['enrol_completestatusid']];
        }

        if (isset($row['enrol_grade'])) {
            $row['enrol_grade'] = pm_display_grade($row['enrol_grade']);
        }

        return $row;
    }

    /**
     * Adds the enrolment table for this class, and the custom field tables, if necessary, to the JOIN sql.
     *
     * @param array $filters An array of active filters to use to determne join sql.
     * @return array An array of JOIN sql fragments.
     */
    protected function get_join_sql(array $filters=array()) {
        $joinsql = parent::get_join_sql($filters);
        $joinsql[] = 'JOIN {crlm_class_enrolment} enrol ON enrol.classid='.$this->classid.' AND enrol.userid = element.id';
        return $joinsql;
    }
}