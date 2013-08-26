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

require_once(elispm::lib('data/instructor.class.php'));

/**
 * A base datatable object for class - instructor assignments.
 */
class deepsight_datatable_instructor_base extends deepsight_datatable_user {

    /**
     * @var int The ID of the class we're managing.
     */
    protected $classid;

    /**
     * Sets the current class ID
     * @param int $classid The ID of the class to manage.
     */
    public function set_classid($classid) {
        $this->classid = (int)$classid;
    }

    /**
     * Gets an array of javascript files needed for operation.
     * @see deepsight_datatable::get_js_dependencies()
     */
    public function get_js_dependencies() {
        $deps = parent::get_js_dependencies();
        $deps[] = '/elis/program/lib/deepsight/js/actions/deepsight_action_confirm.js';
        $deps[] = '/elis/program/lib/deepsight/js/actions/deepsight_action_instructor_assignedit.js';
        return $deps;
    }

    /**
     * Get an array of options to pass to the deepsight_datatable javascript object. Enables drag and drop, and multiselect
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
 * A datatable object for users assigned to the class as instructors.
 */
class deepsight_datatable_instructor_assigned extends deepsight_datatable_instructor_base {

    /**
     * Gets an array of available filters.
     * @return array An array of deepsight_filter objects that will be available.
     */
    protected function get_filters() {
        $filters = parent::get_filters();

        $langasstime = get_string('instructor_assignment', 'elis_program');
        $filters[] = new deepsight_filter_date($this->DB, 'assigntime', $langasstime, array('ins.assigntime' => $langasstime));

        $langcmptime = get_string('instructor_completion', 'elis_program');
        $filters[] = new deepsight_filter_date($this->DB, 'completetime', $langcmptime, array('ins.completetime' => $langcmptime));

        return $filters;
    }

    /**
     * Gets an array of initial filters.
     * @return array An array of deepsight_filter $name properties that will be present when the user first loads the page.
     */
    protected function get_initial_filters() {
        $initfilt = parent::get_initial_filters();
        $initfilt[] = 'assigntime';
        $initfilt[] = 'completetime';
        return $initfilt;
    }

    /**
     * Gets the unassignment action.
     * @return array An array of deepsight_action objects that will be available for each element.
     */
    public function get_actions() {
        $actions = parent::get_actions();
        $editaction = new deepsight_action_instructor_edit($this->DB, 'instructor_edit');
        $editaction->endpoint = (strpos($this->endpoint, '?') !== false)
                ? $this->endpoint.'&m=action' : $this->endpoint.'?m=action';
        $actions[] = $editaction;
        $unassignaction = new deepsight_action_instructor_unassign($this->DB, 'instructor_unassign');
        $unassignaction->endpoint = (strpos($this->endpoint, '?') !== false)
                ? $this->endpoint.'&m=action' : $this->endpoint.'?m=action';
        $actions[] = $unassignaction;
        return $actions;
    }

    /**
     * Formats times, adds separated assign and complete times to the array for use by js.
     *
     * @param array $row An array for a single result.
     * @return array The transformed result.
     */
    protected function results_row_transform(array $row) {
        foreach (array('assigntime', 'completetime') as $timeparam) {
            if (isset($row['ins_'.$timeparam])) {
                $row['assocdata_'.$timeparam] = json_encode(array(
                    'date' => date('j', $row['ins_'.$timeparam]),
                    'month' => date('n', $row['ins_'.$timeparam])-1,
                    'year'=> date('Y', $row['ins_'.$timeparam])
                ));
                $row['ins_'.$timeparam] = ds_process_displaytime($row['ins_'.$timeparam]);
            }
        }
        return $row;
    }

    /**
     * Adds the assignment table for instructors.
     * @param array $filters An array of active filters to use to determne join sql.
     * @return string A SQL string containing any JOINs needed for the full query.
     */
    protected function get_join_sql(array $filters=array()) {
        $joinsql = parent::get_join_sql($filters);
        $joinsql[] = 'JOIN {'.instructor::TABLE.'} ins ON ins.classid = '.$this->classid.' AND ins.userid = element.id';
        return $joinsql;
    }
}

/**
 * A datatable for users available to be assigned as instructors.
 */
class deepsight_datatable_instructor_available extends deepsight_datatable_instructor_base {

    /**
     * Gets the assignment action.
     * @return array An array of deepsight_action objects that will be available for each element.
     */
    public function get_actions() {
        $actions = parent::get_actions();
        $assignaction = new deepsight_action_instructor_assign($this->DB, 'instructor_assign');
        $assignaction->endpoint = (strpos($this->endpoint, '?') !== false)
                ? $this->endpoint.'&m=action' : $this->endpoint.'?m=action';
        $actions[] = $assignaction;
        return $actions;
    }

    /**
     * Adds the assignment table for instructors.
     * @param array $filters An array of active filters to use to determne join sql.
     * @return string A SQL string containing any JOINs needed for the full query.
     */
    protected function get_join_sql(array $filters=array()) {
        $joinsql = parent::get_join_sql($filters);
        $joinsql[] = 'LEFT JOIN {'.instructor::TABLE.'} ins ON ins.classid = '.$this->classid.' AND ins.userid = element.id';
        $joinsql[] = 'LEFT JOIN {'.student::TABLE.'} stu ON stu.classid = '.$this->classid.' AND stu.userid = element.id';
        return $joinsql;
    }

    /**
     * Gets filter sql for permissions.
     * @return array An array consisting of additional WHERE conditions, and parameters.
     */
    protected function get_filter_sql_permissions() {
        global $DB;
        $additionalfilters = array();
        $additionalfiltersparams = array();

        // If appropriate limit selection to users belonging to clusters for which the user can manage instructor assignments.

        // TODO: Ugly, this needs to be overhauled.
        $cpage = new pmclasspage();
        if (!$cpage->_has_capability('elis/program:assign_class_instructor', $this->classid)) {
            // Perform SQL filtering for the more "conditional" capability.
            $allowedclusters = instructor::get_allowed_clusters($this->classid);
            if (empty($allowedclusters)) {
                $additionalfilters[] = 'FALSE';
            } else {
                list($usersetinoreq, $usersetinoreqparams) = $DB->get_in_or_equal($allowedclusters);
                $clusterfilter = 'SELECT userid FROM {'.clusterassignment::TABLE.'} WHERE clusterid '.$usersetinoreq;
                $additionalfilters[] = 'element.id IN ('.$clusterfilter.')';
                $additionalfiltersparams = array_merge($additionalfiltersparams, $usersetinoreqparams);
            }
        }

        return array($additionalfilters, $additionalfiltersparams);
    }

    /**
     * Removes assigned instructors, students, and applies permissions.
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array An array consisting of the SQL WHERE clause, and the parameters for the SQL.
     */
    protected function get_filter_sql(array $filters) {
        global $USER;

        list($filtersql, $filterparams) = parent::get_filter_sql($filters);

        $additionalfilters = array();

        // Limit to users not currently assigned.
        $additionalfilters[] = 'ins.id IS NULL';
        $additionalfilters[] = 'stu.id IS NULL';

        // Add permissions.
        list($permadditionalfilters, $permadditionalparams) = $this->get_filter_sql_permissions();
        $additionalfilters = array_merge($additionalfilters, $permadditionalfilters);
        $filterparams = array_merge($filterparams, $permadditionalparams);

        // Add our additional filters.
        if (!empty($additionalfilters)) {
            $filtersql = (!empty($filtersql))
                    ? $filtersql.' AND '.implode(' AND ', $additionalfilters) : 'WHERE '.implode(' AND ', $additionalfilters);
        }

        return array($filtersql, $filterparams);
    }
}