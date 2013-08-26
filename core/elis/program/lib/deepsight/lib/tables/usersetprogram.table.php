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

require_once(elispm::lib('data/clustercurriculum.class.php'));
require_once(elispm::file('plugins/userset_classification/usersetclassification.class.php'));

/**
 * A base class for managing userset - program associations. (one userset, multiple programs)
 */
class deepsight_datatable_usersetprogram_base extends deepsight_datatable_program {
    protected $usersetid;

    /**
     * Sets the current userset ID
     * @param int $usersetid The ID of the userset to use.
     */
    public function set_usersetid($usersetid) {
        $this->usersetid = (int)$usersetid;
    }

    /**
     * Gets an array of javascript files needed for operation.
     * @see deepsight_datatable::get_js_dependencies()
     */
    public function get_js_dependencies() {
        $deps = parent::get_js_dependencies();
        $deps[] = '/elis/program/lib/deepsight/js/actions/deepsight_action_confirm.js';
        $deps[] = '/elis/program/lib/deepsight/js/actions/deepsight_action_usersetprogram.js';
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
 * A datatable for listing currently assigned programs.
 */
class deepsight_datatable_usersetprogram_assigned extends deepsight_datatable_usersetprogram_base {

    /**
     * Gets an array of available filters.
     * @return array An array of deepsight_filter objects that will be available.
     */
    protected function get_filters() {
        $langautoenrol = get_string('usersetprogram_auto_enrol', 'elis_program');
        $filters = parent::get_filters();
        $fielddata = array('clstcur.autoenrol' => $langautoenrol);
        $autoenrol = new deepsight_filter_menuofchoices($this->DB, 'autoenrol', $langautoenrol, $fielddata, $this->endpoint);
        $autoenrol->set_choices(array(
            0 => get_string('no', 'moodle'),
            1 => get_string('yes', 'moodle'),
        ));
        $filters[] = $autoenrol;
        return $filters;
    }

    /**
     * Gets an array of initial filters.
     * @return array An array of deepsight_filter $name properties that will be present when the user first loads the page.
     */
    protected function get_initial_filters() {
        $initialfilters = parent::get_initial_filters();
        $initialfilters[] = 'autoenrol';
        return $initialfilters;
    }

    /**
     * Formats the autoenrol parameter.
     * @param array $row An array for a single result.
     * @return array The transformed result.
     */
    protected function results_row_transform(array $row) {
        $row = parent::results_row_transform($row);
        if (isset($row['clstcur_autoenrol'])) {
            // Save original autoenrol value for use by javascript, then convert value to language string.
            $row['autoenrol'] = $row['clstcur_autoenrol'];
            $row['clstcur_autoenrol'] = ($row['clstcur_autoenrol'] == 1) ? get_string('yes', 'moodle') : get_string('no', 'moodle');
        }
        return $row;
    }

    /**
     * Gets the edit and unassignment actions.
     * @return array An array of deepsight_action objects that will be available for each element.
     */
    public function get_actions() {
        $actions = parent::get_actions();
        $editaction = new deepsight_action_usersetprogram_edit($this->DB, 'usersetprogramedit');
        $editaction->endpoint = (strpos($this->endpoint, '?') !== false)
                ? $this->endpoint.'&m=action' : $this->endpoint.'?m=action';
        $unassignaction = new deepsight_action_usersetprogram_unassign($this->DB, 'usersetprogramunassign');
        $unassignaction->endpoint = (strpos($this->endpoint, '?') !== false)
                ? $this->endpoint.'&m=action' : $this->endpoint.'?m=action';
        array_unshift($actions, $editaction, $unassignaction);
        return $actions;
    }

    /**
     * Adds the assignment table.
     * @param array $filters An array of active filters to use to determne join sql.
     * @return string A SQL string containing any JOINs needed for the full query.
     */
    protected function get_join_sql(array $filters=array()) {
        $joinsql = parent::get_join_sql($filters);
        $joinsql[] = 'JOIN {'.clustercurriculum::TABLE.'} clstcur
                           ON clstcur.clusterid='.$this->usersetid.' AND clstcur.curriculumid = element.id';
        return $joinsql;
    }
}

/**
 * A datatable listing programs that are available to assign to the userset, and are not currently assigned.
 */
class deepsight_datatable_usersetprogram_available extends deepsight_datatable_usersetprogram_base {

    /**
     * Gets the assign action.
     * @return array An array of deepsight_action objects that will be available for each element.
     */
    public function get_actions() {
        $actions = parent::get_actions();
        $assignaction = new deepsight_action_usersetprogram_assign($this->DB, 'usersetprogramassign');
        $assignaction->endpoint = (strpos($this->endpoint, '?') !== false)
                ? $this->endpoint.'&m=action' : $this->endpoint.'?m=action';
        array_unshift($actions, $assignaction);
        return $actions;
    }

    /**
     * Adds the assignment table.
     * @param array $filters An array of active filters to use to determne join sql.
     * @return string A SQL string containing any JOINs needed for the full query.
     */
    protected function get_join_sql(array $filters=array()) {
        $joinsql = parent::get_join_sql($filters);
        $joinsql[] = 'LEFT JOIN {'.clustercurriculum::TABLE.'} clstcur
                                ON clstcur.clusterid='.$this->usersetid.' AND clstcur.curriculumid = element.id';
        return $joinsql;
    }

    /**
     * Gets filter sql for permissions.
     * @return array An array consisting of additional WHERE conditions, and parameters.
     */
    protected function get_filter_sql_permissions() {
        global $USER;
        $ctxlevel = 'curriculum';
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
        $additionalfilters[] = 'clstcur.id IS NULL';

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