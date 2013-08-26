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
 * A base datatable object for user - track assignments.
 */
class deepsight_datatable_usertrack_base extends deepsight_datatable_track {
    protected $userid;

    /**
     * Sets the current user ID
     * @param int $userid The ID of the user to use.
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
 * A datatable object for tracks assigned to this user.
 */
class deepsight_datatable_usertrack_assigned extends deepsight_datatable_usertrack_base {

    /**
     * Gets the unassignment action.
     * @return array An array of deepsight_action objects that will be available for each element.
     */
    public function get_actions() {
        $actions = parent::get_actions();
        $unassignaction = new deepsight_action_usertrack_unassign($this->DB, 'usertrackunassign');
        $unassignaction->endpoint = (strpos($this->endpoint, '?') !== false)
                ? $this->endpoint.'&m=action' : $this->endpoint.'?m=action';
        array_unshift($actions, $unassignaction);
        return $actions;
    }

    /**
     * Adds the user - track assignment table.
     * @param array $filters An array of active filters to use to determne join sql.
     * @return string A SQL string containing any JOINs needed for the full query.
     */
    protected function get_join_sql(array $filters=array()) {
        $joinsql = parent::get_join_sql($filters);
        $joinsql[] = 'JOIN {'.usertrack::TABLE.'} trkass ON trkass.userid = '.$this->userid.' AND trkass.trackid = element.id';
        return $joinsql;
    }
}

/**
 * A datatable for tracks not yet assigned to this user.
 */
class deepsight_datatable_usertrack_available extends deepsight_datatable_usertrack_base {

    /**
     * Gets the assignment action.
     * @return array An array of deepsight_action objects that will be available for each element.
     */
    public function get_actions() {
        $actions = parent::get_actions();
        $assignaction = new deepsight_action_usertrack_assign($this->DB, 'usertrackassign');
        $assignaction->endpoint = (strpos($this->endpoint, '?') !== false)
                ? $this->endpoint.'&m=action' : $this->endpoint.'?m=action';
        array_unshift($actions, $assignaction);
        return $actions;
    }

    /**
     * Adds the user - track assignent table.
     * @param array $filters An array of active filters to use to determne join sql.
     * @return string A SQL string containing any JOINs needed for the full query.
     */
    protected function get_join_sql(array $filters=array()) {
        $joinsql = parent::get_join_sql($filters);
        $joinsql[] = 'LEFT JOIN {'.usertrack::TABLE.'} trkass ON trkass.userid = '.$this->userid.' AND trkass.trackid = element.id';
        return $joinsql;
    }

    /**
     * Gets filter sql for permissions.
     * @return array An array consisting of additional WHERE conditions, and parameters.
     */
    protected function get_filter_sql_permissions() {
        global $USER, $DB;

        $elementtype = 'track';
        $elementidsfromclusterids = 'SELECT trackid FROM {'.clustertrack::TABLE.'} WHERE clusterid {clusterids}';
        return $this->get_filter_sql_permissions_userelement_available($elementtype, $elementidsfromclusterids);
    }

    /**
     * Removes assigned tracks and handles display permissions.
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array An array consisting of the SQL WHERE clause, and the parameters for the SQL.
     */
    protected function get_filter_sql(array $filters) {
        global $USER;

        list($filtersql, $filterparams) = parent::get_filter_sql($filters);

        $additionalfilters = array();

        // Limit to users not currently assigned.
        $additionalfilters[] = 'trkass.id IS NULL';

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