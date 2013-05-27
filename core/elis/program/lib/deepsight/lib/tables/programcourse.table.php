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

require_once(elispm::lib('data/curriculumcourse.class.php'));

/**
 * A base class for managing program - courses associations. (one program, multiple courses)
 */
class deepsight_datatable_programcourse_base extends deepsight_datatable_course {

    /**
     * @var int The ID of the program being managed.
     */
    protected $programid;

    /**
     * Sets the current program ID
     * @param int $programid The ID of the program to use.
     */
    public function set_programid($programid) {
        $this->programid = (int)$programid;
    }

    /**
     * Gets an array of javascript files needed for operation.
     * @see deepsight_datatable::get_js_dependencies()
     */
    public function get_js_dependencies() {
        $deps = parent::get_js_dependencies();
        $deps[] = '/elis/program/lib/deepsight/js/actions/deepsight_action_confirm.js';
        $deps[] = '/elis/program/lib/deepsight/js/actions/deepsight_action_link.js';
        $deps[] = '/elis/program/lib/deepsight/js/actions/deepsight_action_programcourse.js';
        return $deps;
    }

    /**
     * Get an array of options to pass to the deepsight_datatable javascript object. Enables drag and drop, and multiselect.
     * @return array An array of options, ready to be passed to $this->get_init_js()
     */
    public function get_table_js_opts() {
        $opts = parent::get_table_js_opts();
        $opts['dragdrop'] = true;
        $opts['multiselect'] = true;
        return $opts;
    }
}

/**
 * A datatable for listing currently assigned courses.
 */
class deepsight_datatable_programcourse_assigned extends deepsight_datatable_programcourse_base {

    /**
     * Gets an array of available filters.
     * @return array An array of deepsight_filter objects that will be available.
     */
    protected function get_filters() {
        $filters = parent::get_filters();

        // Required.
        $langrequired = get_string('curriculumcourseform:required', 'elis_program');
        $fielddata = array('curcrs.required' => $langrequired);
        $required = new deepsight_filter_menuofchoices($this->DB, 'required', $langrequired, $fielddata, $this->endpoint);
        $required->set_choices(array(
            0 => get_string('no', 'moodle'),
            1 => get_string('yes', 'moodle'),
        ));
        $filters[] = $required;

        // Frequency.
        $langfrequency = get_string('curriculumcourseform:frequency', 'elis_program');
        $fielddata = array('curcrs.frequency' => $langfrequency);
        $filters[] = new deepsight_filter_searchselect($this->DB, 'frequency', $langfrequency, $fielddata, $this->endpoint,
                curriculumcourse::TABLE, 'frequency');

        // Timeperiod.
        $langtimeperiod = get_string('curriculumcourseform:time_period', 'elis_program');
        $fielddata = array('curcrs.timeperiod' => $langtimeperiod);
        $timeperiod = new deepsight_filter_menuofchoices($this->DB, 'timeperiod', $langtimeperiod, $fielddata, $this->endpoint);
        $timeperiod->set_choices(array(
            'year' => get_string('time_period_year', 'elis_program'),
            'month' => get_string('time_period_month', 'elis_program'),
            'week' => get_string('time_period_week', 'elis_program'),
            'day' => get_string('time_period_day', 'elis_program')
        ));
        $filters[] = $timeperiod;

        // Position.
        $langposition = get_string('curriculumcourseform:position', 'elis_program');
        $fielddata = array('curcrs.position' => $langposition);
        $filters[] = new deepsight_filter_searchselect($this->DB, 'position', $langposition, $fielddata, $this->endpoint,
                curriculumcourse::TABLE, 'position');

        return $filters;
    }

    /**
     * Gets an array of initial filters.
     * @return array An array of deepsight_filter $name properties that will be present when the user first loads the page.
     */
    protected function get_initial_filters() {
        $initialfilters = parent::get_initial_filters();
        $initialfilters[] = 'required';
        $initialfilters[] = 'frequency';
        $initialfilters[] = 'timeperiod';
        $initialfilters[] = 'position';
        return $initialfilters;
    }

    /**
     * Formats the required parameter.
     * @param array $row An array for a single result.
     * @return array The transformed result.
     */
    protected function results_row_transform(array $row) {
        $row = parent::results_row_transform($row);

        // Format required param.
        if (isset($row['curcrs_required'])) {
            // Save original required value for use by javascript, then convert value to language string.
            $row['required'] = $row['curcrs_required'];
            $row['curcrs_required'] = ($row['curcrs_required'] == 1) ? get_string('yes', 'moodle') : get_string('no', 'moodle');
        }

        if (isset($row['curcrs_timeperiod'])) {
            $timeperiodlang = array(
                'year' => get_string('time_period_year', 'elis_program'),
                'month' => get_string('time_period_month', 'elis_program'),
                'week' => get_string('time_period_week', 'elis_program'),
                'day' => get_string('time_period_day', 'elis_program')
            );
            if (isset($timeperiodlang[$row['curcrs_timeperiod']])) {
                $row['curcrs_timeperiod'] = $timeperiodlang[$row['curcrs_timeperiod']];
            }
        }
        return $row;
    }

    /**
     * Gets the edit and unassignment actions.
     * @return array An array of deepsight_action objects that will be available for each element.
     */
    public function get_actions() {
        $actions = parent::get_actions();

        // Edit action.
        $edit = new deepsight_action_programcourse_edit($this->DB, 'programcourse_edit');
        $edit->endpoint = (strpos($this->endpoint, '?') !== false)
                ? $this->endpoint.'&m=action' : $this->endpoint.'?m=action';
        $actions[] = $edit;

        // Prerequisite link.
        $langprereq = get_string('prerequisites', 'elis_program');
        $actions[] = new deepsight_action_programcourse_prereqlink($this->DB, 'programcourse_prereqedit', $langprereq);

        // Corequisite link.
        $langcoreq = get_string('corequisites', 'elis_program');
        $actions[] = new deepsight_action_programcourse_coreqlink($this->DB, 'programcourse_coreqedit', $langcoreq);

        // Unassign action.
        $unassign = new deepsight_action_programcourse_unassign($this->DB, 'programcourse_unassign');
        $unassign->endpoint = (strpos($this->endpoint, '?') !== false)
                ? $this->endpoint.'&m=action' : $this->endpoint.'?m=action';
        $actions[] = $unassign;

        return $actions;
    }

    /**
     * Gets an array of fields to include in the search SQL's SELECT clause.
     *
     * @param array $filters An Array of active filters to use to determine the needed select fields.
     * @return array An array of fields for the SELECT clause.
     */
    protected function get_select_fields(array $filters) {
        $fields = parent::get_select_fields($filters);
        $fields[] = 'curcrs.id AS curcrs_id';
        $fields[] = 'curcrs.curriculumid AS curcrs_curriculumid';
        $fields[] = 'curcrs.required AS assocdata_required';
        $fields[] = 'curcrs.frequency AS assocdata_frequency';
        $fields[] = 'curcrs.timeperiod AS assocdata_timeperiod';
        $fields[] = 'curcrs.position AS assocdata_position';
        return $fields;
    }

    /**
     * Adds the assignment table.
     * @param array $filters An array of active filters to use to determne join sql.
     * @return string A SQL string containing any JOINs needed for the full query.
     */
    protected function get_join_sql(array $filters=array()) {
        $joinsql = parent::get_join_sql($filters);
        $joinsql[] = 'JOIN {'.curriculumcourse::TABLE.'} curcrs
                           ON curcrs.curriculumid='.$this->programid.' AND curcrs.courseid = element.id';
        return $joinsql;
    }
}

/**
 * A datatable listing courses that are available to assign to the program, and are not currently assigned.
 */
class deepsight_datatable_programcourse_available extends deepsight_datatable_programcourse_base {

    /**
     * Gets the assign action.
     * @return array An array of deepsight_action objects that will be available for each element.
     */
    public function get_actions() {
        $actions = parent::get_actions();

        // Assign action.
        $assign = new deepsight_action_programcourse_assign($this->DB, 'programcourse_assign');
        $assign->endpoint = (strpos($this->endpoint, '?') !== false)
                ? $this->endpoint.'&m=action' : $this->endpoint.'?m=action';
        $actions[] = $assign;

        return $actions;
    }

    /**
     * Adds the assignment table.
     * @param array $filters An array of active filters to use to determne join sql.
     * @return string A SQL string containing any JOINs needed for the full query.
     */
    protected function get_join_sql(array $filters=array()) {
        $joinsql = parent::get_join_sql($filters);
        $joinsql[] = 'LEFT JOIN {'.curriculumcourse::TABLE.'} curcrs
                                ON curcrs.curriculumid='.$this->programid.' AND curcrs.courseid = element.id';
        return $joinsql;
    }

    /**
     * Gets filter sql for permissions.
     * @return array An array consisting of additional WHERE conditions, and parameters.
     */
    protected function get_filter_sql_permissions() {
        global $USER;
        $ctxlevel = 'course';
        $perm = 'elis/program:associate';
        $additionalfilters = array();
        $additionalparams = array();
        $associatectxs = pm_context_set::for_user_with_capability($ctxlevel, $perm, $USER->id);
        $associatectxsfilerobject = $associatectxs->get_filter('id', $ctxlevel);
        $associatefilter = $associatectxsfilerobject->get_sql(false, 'element', SQL_PARAMS_QM);
        if (isset($associatefilter['where'])) {
            $additionalfilters[] = $associatefilter['where'];
            $additionalparams = array_merge($additionalparams, $associatefilter['where_parameters']);
        }
        return array($additionalfilters, $additionalparams);
    }

    /**
     * Removes assigned programs, and limits results according to permissions.
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array An array consisting of the SQL WHERE clause, and the parameters for the SQL.
     */
    protected function get_filter_sql(array $filters) {
        global $USER;

        list($filtersql, $filterparams) = parent::get_filter_sql($filters);

        $additionalfilters = array();

        // Remove assigned users.
        $additionalfilters[] = 'curcrs.id IS NULL';

        // Permissions.
        list($permadditionalfilters, $permadditionalparams) = $this->get_filter_sql_permissions();
        $additionalfilters = array_merge($additionalfilters, $permadditionalfilters);
        $filterparams = array_merge($filterparams, $permadditionalparams);

        // Add our additional filters.
        if (!empty($additionalfilters)) {
            $filtersql = (!empty($filtersql))
                    ? $filtersql.' AND '.implode(' AND ', $additionalfilters)
                    : 'WHERE '.implode(' AND ', $additionalfilters);
        }

        return array($filtersql, $filterparams);
    }
}