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
 * A base class for managing user - program associations. (one user, multiple programs)
 */
class deepsight_datatable_userprogram_base extends deepsight_datatable_program {
    protected $userid;

    /**
     * Sets the current program ID
     * @param int $programid The ID of the program to use.
     */
    public function set_userid($userid) {
        $this->userid = (int)$userid;
    }

    /**
     * Gets an array of javascript files needed for operation.
     * @see deepsight_datatable::get_js_dependencies()
     */
    public function get_js_dependencies() {
        $deps = parent::get_js_dependencies();
        $deps[] = '/elis/program/lib/deepsight/js/actions/deepsight_action_confirm.js';
        return $deps;
    }

    /**
     * Get an array of options to pass to the deepsight_datatable javascript object.
     * Enables drag and drop, and multiselect.
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
 * A datatable for listing currently assigned users.
 */
class deepsight_datatable_userprogram_assigned extends deepsight_datatable_userprogram_base {

    /**
     * Gets an array of available filters.
     * @return array An array of deepsight_filter objects that will be available.
     */
    protected function get_filters() {
        $langcreditsrecv = get_string('credits_rec', 'elis_program');
        $langdatecompleted = get_string('date_completed', 'elis_program');

        $filters = parent::get_filters();

        $fielddata = array('currass.credits' => $langcreditsrecv);
        $filters[] = new deepsight_filter_textsearch($this->DB, 'creditsrecv', $langcreditsrecv, $fielddata);

        $fielddata = array('currass.timecompleted' => $langdatecompleted);
        $filters[] = new deepsight_filter_date($this->DB, 'datecompleted', $langdatecompleted, $fielddata);

        return $filters;
    }

    /**
     * Gets an array of fields to include in the search SQL's SELECT clause.
     * @param array $filters An Array of active filters to use to determine the needed select fields.
     * @return array An array of fields for the SELECT clause.
     */
    protected function get_select_fields(array $filters) {
        $selectfields = parent::get_select_fields($filters);

        // Add completed so we can set date completed to "-" if the program has not been completed.
        $selectfields[] = 'currass.completed AS currass_completed';

        // Add query for number of course descriptions.
        $sql = 'SELECT count(1) FROM {'.curriculumcourse::TABLE.'} curcrs WHERE curcrs.curriculumid = element.id';
        $selectfields[] = '('.$sql.') AS numcourses';

        return $selectfields;
    }

    /**
     * Gets an array of column labels to send back to the javascript.
     * Pulls information from $this->fixed_columns, and each filter's get_column_labels() function.
     * @param array $filters An array of active filters to determine which columns to return.
     * @return array An column labels formatted like [field alias (i.e. element_id)]=>[label]
     */
    protected function get_column_labels(array $filters) {
        $labels = parent::get_column_labels($filters);
        $labels['numcourses'] = get_string('num_courses', 'elis_program');
        return $labels;
    }

    /**
     * Formats the timecreated parameter, if present, and adds a link to view the user's ELIS profile around the idnumber parameter.
     * @param array $row An array for a single result.
     * @return array The transformed result.
     */
    protected function results_row_transform(array $row) {
        $row = parent::results_row_transform($row);
        if (isset($row['currass_timecompleted'])) {
            $row['currass_timecompleted'] = ds_process_displaytime($row['currass_timecompleted']);
        }
        return $row;
    }

    /**
     * Gets an array of initial filters.
     * @return array An array of deepsight_filter $name properties that will be present when the user first loads the page.
     */
    protected function get_initial_filters() {
        $initialfilters = parent::get_initial_filters();
        $initialfilters[] = 'datecompleted';
        $initialfilters[] = 'creditsrecv';
        return $initialfilters;
    }

    /**
     * Gets the unassignment action.
     * @return array An array of deepsight_action objects that will be available for each element.
     */
    public function get_actions() {
        $actions = parent::get_actions();
        $unassignaction = new deepsight_action_userprogram_unassign($this->DB, 'userprogramunassign');
        $unassignaction->endpoint = (strpos($this->endpoint, '?') !== false)
                ? $this->endpoint.'&m=action' : $this->endpoint.'?m=action';
        array_unshift($actions, $unassignaction);
        return $actions;
    }

    /**
     * Adds the assignment table for this program.
     * @param array $filters An array of active filters to use to determne join sql.
     * @return string A SQL string containing any JOINs needed for the full query.
     */
    protected function get_join_sql(array $filters=array()) {
        $joinsql = parent::get_join_sql($filters);
        $joinsql[] = 'JOIN {'.curriculumstudent::TABLE.'} currass
                           ON currass.userid='.$this->userid.' AND currass.curriculumid = element.id';
        return $joinsql;
    }
}

/**
 * A datatable listing users that are available to assign to the program, and are not currently assigned.
 */
class deepsight_datatable_userprogram_available extends deepsight_datatable_userprogram_base {

    /**
     * Gets the program assignment action.
     * @return array An array of deepsight_action objects that will be available for each element.
     */
    public function get_actions() {
        $actions = parent::get_actions();
        $assignaction = new deepsight_action_userprogram_assign($this->DB, 'userprogramassign');
        $assignaction->endpoint = (strpos($this->endpoint, '?') !== false)
                ? $this->endpoint.'&m=action' : $this->endpoint.'?m=action';
        array_unshift($actions, $assignaction);
        return $actions;
    }

    /**
     * Adds the assignment table for user/program associations.
     * @param array $filters An array of active filters to use to determne join sql.
     * @return string A SQL string containing any JOINs needed for the full query.
     */
    protected function get_join_sql(array $filters=array()) {
        $joinsql = parent::get_join_sql($filters);
        $joinsql[] = 'LEFT JOIN {'.curriculumstudent::TABLE.'} currass
                           ON currass.userid='.$this->userid.' AND currass.curriculumid = element.id';
        return $joinsql;
    }

    /**
     * Gets filter sql for permissions.
     * @return array An array consisting of additional WHERE conditions, and parameters.
     */
    protected function get_filter_sql_permissions() {
        global $USER, $DB;

        $elementtype = 'program';
        $elementidsfromclusterids = 'SELECT curriculumid FROM {'.clustercurriculum::TABLE.'} WHERE clusterid {clusterids}';
        return $this->get_filter_sql_permissions_userelement_available($elementtype, $elementidsfromclusterids);
    }

    /**
     * Removes assigned users, and limits results according to permissions.
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array An array consisting of the SQL WHERE clause, and the parameters for the SQL.
     */
    protected function get_filter_sql(array $filters) {
        global $USER;

        list($filtersql, $filterparams) = parent::get_filter_sql($filters);

        $additionalfilters = array();

        // Remove assigned programs.
        $additionalfilters[] = 'currass.id IS NULL';

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