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
 * A datatable implementation for a list of available users to enrol in a given class.
 */
class deepsight_datatable_enrolments extends deepsight_datatable_user {
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
     * @param int         $classid  The classid we're enrolling students into.
     */
    public function __construct(moodle_database &$DB, $name, $endpoint, $uniqid=null, $classid=null) {
        if (!is_numeric($classid)) {
            throw new Exception('Invalid class ID received when creating enrolments datatable.');
        }
        $this->classid = (int)$classid;
        parent::__construct($DB, $name, $endpoint, $uniqid);
    }

    /**
     * Gets the enrolment action.
     *
     * Includes condition to only show for non-enrolled users. (The table can show both enrolled and not enrolled)
     *
     * @return array An array of deepsight_action objects that will be available for each element.
     */
    public function get_actions() {
        $actions = parent::get_actions();

        $enrolaction = new deepsight_action_enrol($this->DB, 'enrol');
        $enrolaction->endpoint = (strpos($this->endpoint, '?') !== false)
            ? $this->endpoint.'&m=action'
            : $this->endpoint.'?m=action';
        $enrolaction->condition = 'function(rowdata) { return (rowdata.meta.enrolled == true) ? false : true; }';

        array_unshift($actions, $enrolaction);

        return $actions;
    }

    /**
     * Gets an array of javascript files needed for operation.
     *
     * @see deepsight_datatable::get_js_dependencies()
     */
    public function get_js_dependencies() {
        $deps = parent::get_js_dependencies();
        $deps[] = '/elis/program/lib/deepsight/js/actions/deepsight_action_enroledit.js';
        return $deps;
    }

    /**
     * Gets an array of available filters.
     *
     * @return array An array of deepsight_filter objects that will be available.
     */
    protected function get_filters() {
        $langshowing = get_string('ds_showing', 'elis_program').':';

        $enrolmentfilter = new deepsight_filter_enrolmentstatus($this->DB, 'enrolled', $langshowing);
        $enrolmentfilter->set_classid($this->classid);
        $enrolmentfilter->set_default('notenrolled');

        $filters = parent::get_filters();
        array_unshift($filters, $enrolmentfilter);

        return $filters;
    }

    /**
     * Gets an array of initial filters.
     *
     * @return array An array of deepsight_filter $name properties that will be present when the user first loads the page.
     */
    protected function get_initial_filters() {
        $initialfilters = parent::get_initial_filters();
        array_unshift($initialfilters, 'enrolled');
        return $initialfilters;
    }

    /**
     * Get an array of options to pass to the deepsight_datatable javascript object.
     *
     * Enables drag and drop, multiselect, and a rowfilter function to show enrolled users as disabled, if necessary.
     *
     * @return array An array of options, ready to be passed to $this->get_init_js()
     */
    public function get_table_js_opts() {
        $opts = parent::get_table_js_opts();
        $opts['dragdrop'] = true;
        $opts['multiselect'] = true;
        $langenroled = get_string('enroled', 'elis_program');
        $opts['rowfilter'] = 'function(row, rowdata) {
                                if (rowdata.meta.enrolled == true) {
                                    row.addClass(\'disabled\').find(\'td.actions\').html(\''.$langenroled.'\');
                                }
                                return row;
                            }';
        return $opts;
    }

    /**
     * Performs the parent transformations, and sets ['meta']['enrolled'], for use by the rowfilter function.
     *
     * @param array $row An array for a single result.
     * @return array The transformed result.
     */
    protected function results_row_transform(array $row) {
        $row = parent::results_row_transform($row);
        $row['meta']['enrolled'] = (!empty($row['enrol_id'])) ? true : false;
        return $row;
    }

    /**
     * Removes instructors and waitlisted users, and adds permission limits, if applicable.
     *
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array An array consisting of the SQL WHERE clause, and the parameters for the SQL.
     */
    protected function get_filter_sql(array $filters) {
        list($filtersql, $filterparams) = parent::get_filter_sql($filters);

        // Remove instructors and waitlisted users.
        $wheresql = 'WHERE classid = '.$this->classid.' AND userid=element.id) IS NULL';
        $instructorfilter = '(SELECT id FROM {crlm_class_instructor} '.$wheresql;
        $waitlistfilter = '(SELECT id FROM {crlm_wait_list} '.$wheresql;

        $filtersql = (!empty($filtersql))
            ? $filtersql.' AND '.$instructorfilter.' AND '.$waitlistfilter
            : 'WHERE '.$instructorfilter.' AND '.$waitlistfilter;

        // Permissions limits.
        $cpage = new pmclasspage();
        if (!$cpage->_has_capability('elis/program:class_enrol', $this->classid)) {
            // Perform SQL filtering for the more "conditional" capability.
            $allowedclusters = pmclass::get_allowed_clusters($this->classid);
            if (empty($allowedclusters)) {
                $permissionssql .= '0=1 ';
            } else {
                $clusterfilter = implode(',', $allowedclusters);
                // TBD.
                $permissionssqlselect = "SELECT userid FROM {".clusterassignment::TABLE."} WHERE clusterid IN ({$clusterfilter})";
                $permissionssql .= 'element.id IN ('.$permissionssqlselect.') ';
            }
        }

        if (!empty($permissionssql)) {
            $filtersql = (!empty($filtersql))
                ? $filtersql.' AND '.$permissionssql
                : 'WHERE '.$permissionssql;
        }

        return array($filtersql, $filterparams);
    }

    /**
     * Adds the enrolment table for this class, and the custom field tables, if necessary, to the JOIN sql.
     *
     * @param array $filters An array of active filters to use to determne join sql.
     * @return array An array of JOIN sql fragments.
     */
    protected function get_join_sql(array $filters=array()) {
        $joinsql = parent::get_join_sql($filters);
        $joinsql[] = 'LEFT JOIN {crlm_class_enrolment} enrol ON enrol.classid='.$this->classid.' AND enrol.userid = element.id';
        return $joinsql;
    }

    /**
     * Adds all elements returned from a search with a given set of filters to the bulklist.
     *
     * This is usually used when using the "add all search results" button when performing bulk actions.
     *
     * @param array $filters The filter array received from js. It is an array consisting of filtername=>data, and can be passed
     *                       directly to $this->get_filter_sql() to generate the required WHERE sql.
     * @return bool true/Success.
     */
    protected function bulklist_add_by_filters(array $filters) {
        $filters['enrolled'] = array('notenrolled');
        return parent::bulklist_add_by_filters($filters);
    }
}