<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2014 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2013 onwards Remote-Learner.net Inc (http://www.remote-learner.net)
 * @author     James McQuillan <james.mcquillan@remote-learner.net>
 *
 */

require_once(elispm::lib('data/track.class.php'));
require_once(elispm::lib('data/curriculum.class.php'));

/**
 * A datatable implementation for lists of classes.
 */
class deepsight_datatable_class extends deepsight_datatable_standard {

    /**
     * @var string The main table results are pulled from. This forms that FROM clause.
     */
    protected $main_table = 'crlm_class';

    /**
     * Gets an array of available filters.
     *
     * @return array An array of deepsight_filter objects that will be available.
     */
    protected function get_filters() {
        $langidnumber = get_string('class_idnumber', 'elis_program');
        $langcoursename = get_string('class_course', 'elis_program');
        $langstartdate = get_string('class_startdate', 'elis_program');
        $langenddate = get_string('class_enddate', 'elis_program');

        $filters = array(
            new deepsight_filter_textsearch($this->DB, 'idnumber', $langidnumber, array('element.idnumber' => $langidnumber)),
            new deepsight_filter_searchselect($this->DB, 'course_name', $langcoursename, array('crs.name' => $langcoursename),
                                              $this->endpoint, course::TABLE, 'name'),
            new deepsight_filter_date($this->DB, 'startdate', $langstartdate, array('element.startdate' => $langstartdate)),
            new deepsight_filter_date($this->DB, 'enddate', $langenddate, array('element.enddate' => $langenddate)),
        );

        $customfieldfilters = $this->get_custom_field_info(CONTEXT_ELIS_TRACK);
        return array_merge($filters, $customfieldfilters);
    }

    /**
     * Gets an array of initial filters.
     *
     * @return array An array of deepsight_filter $name properties that will be present when the user first loads the page.
     */
    protected function get_initial_filters() {
        return array('idnumber', 'course_name');
    }

    /**
     * Get an array of columns that will always be present.
     *
     * @return array An array of fixed columns formatted like [table-aliased field name (i.e. element.id)]=>[column label]
     */
    protected function get_fixed_columns() {
        return array(
            'element.idnumber' => get_string('class_idnumber', 'elis_program'),
            'crs.name' => get_string('course_name', 'elis_program')
        );
    }

    /**
     * Gets an array of actions that can be used on the elements of the datatable.
     *
     * @return array An array of deepsight_action objects that will be available for each element.
     */
    public function get_actions() {
        return array();
    }

    /**
     * Gets a page of elements from the bulklist for display.
     *
     * @param array $ids An array of IDs to get information for.
     * @return array An array of information for the requested IDs. Contains labels indexed by IDs.
     */
    protected function bulklist_get_info_for_ids(array $ids = array()) {
        if (empty($ids)) {
            return array();
        }

        list($where, $params) = $this->DB->get_in_or_equal($ids);
        $sql = 'SELECT id, idnumber FROM {'.$this->main_table.'} WHERE id '.$where;
        $results = $this->DB->get_recordset_sql($sql, $params);
        $pageresults = array_flip($ids);
        foreach ($results as $result) {
            $pageresults[$result->id] = $result->idnumber;
        }

        return $pageresults;
    }

    /**
     * Formats the timecreated parameter, if present, and adds a link to view the user's ELIS profile around the idnumber parameter.
     *
     * @param array $row An array for a single result.
     * @return array The transformed result.
     */
    protected function results_row_transform(array $row) {
        if (isset($row['element_startdate'])) {
            $row['element_startdate'] = ds_process_displaytime($row['element_startdate']);
        }
        if (isset($row['element_enddate'])) {
            $row['element_enddate'] = ds_process_displaytime($row['element_enddate']);
        }
        return parent::results_row_transform($row);
    }

    /**
     * Adds the inactive users check to the filter SQL.
     *
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array An array consisting of the SQL WHERE clause, and the parameters for the SQL.
     */
    protected function get_filter_sql(array $filters) {
        list($filtersql, $filterparams) = parent::get_filter_sql($filters);
        return array($filtersql, $filterparams);
    }

    /**
     * Adds the custom field tables, if necessary, to the JOIN sql.
     *
     * @param array $filters An array of active filters to use to determne join sql.
     * @return array An array of JOIN sql fragments.
     */
    protected function get_join_sql(array $filters=array()) {
        $joinsql = parent::get_join_sql($filters);
        $joinsql[] = 'JOIN {'.course::TABLE.'} crs ON crs.id = element.courseid';
        $activecustomfields = array_intersect_key($this->custom_fields, $filters);
        if (!empty($activecustomfields)) {
            $joinsql[] = 'JOIN {context} ctx ON ctx.instanceid = element.id AND ctx.contextlevel = '.CONTEXT_ELIS_CLASS;
            $joinsql = array_merge($joinsql, $this->get_custom_field_joins($activecustomfields));
        }
        return $joinsql;
    }

    /**
     * Gets search results for the datatable.
     *
     * @param array $filters The filter array received from js. It is an array consisting of filtername=>data, and can be passed
     *                       directly to $this->get_filter_sql() to generate the required WHERE sql.
     * @param array $sort An array of field=>direction to specify sorting for the results.
     * @param int $limitfrom The position in the dataset from which to start returning results.
     * @param int $limitnum The amount of results to return.
     * @return array An array with the first value being a page of results, and the second value being the total number of results
     */
    protected function get_search_results(array $filters, array $sort = array(), $limitfrom=null, $limitnum=null) {

        // Select fields.
        $selectfields = $this->get_select_fields($filters);

        // Joins.
        $joinsql = implode(' ', $this->get_join_sql($filters));

        // Filtering.
        list($filtersql, $filterparams) = $this->get_filter_sql($filters);

        // Grouping.
        $groupby = $this->get_groupby_sql($filters);
        $groupby = (!empty($groupby)) ? ' GROUP BY '.implode(', ', $groupby).' ' : '';

        // Sorting.
        $sortsql = $this->get_sort_sql($sort);

        if (!is_int($limitfrom)) {
            $limitfrom = 0;
        }
        if (empty($limitnum) || !is_int($limitnum) || $limitnum > static::RESULTSPERPAGE) {
            $limitnum = static::RESULTSPERPAGE;
        }

        // Get the number of results in the full dataset.
        $query = 'SELECT count(1) as count FROM {'.$this->main_table.'} element '.$joinsql.' '.$filtersql.' '.$groupby;
        $results = $this->DB->get_record_sql($query, $filterparams);
        $totalresults = (int)$results->count;

        // Generate and execute query for a single page of results.
        $query = 'SELECT '.implode(', ', $selectfields).' FROM {'.$this->main_table.'} element '.$joinsql.' '.$filtersql.' ';
        $query .= $groupby.' '.$sortsql;
        $results = $this->DB->get_recordset_sql($query, $filterparams, $limitfrom, $limitnum);

        // Process results.
        $pageresults = array();
        foreach ($results as $i => $result) {
            $result = (array)$result;
            $pageresult = $result;
            $pageresult['id'] = $result['element_id'];
            $pageresult['meta'] = array(
                'label' => $result['element_idnumber'],
            );
            $pageresults[] = $this->results_row_transform($pageresult);
        }
        return array($pageresults, $totalresults);
    }
}
