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

global $CFG;
require_once(elispm::file('userpage.class.php'));

/**
 * A datatable implementation for lists of users.
 */
class deepsight_datatable_user extends deepsight_datatable_standard {

    /**
     * @var string The main table results are pulled from. This forms that FROM clause.
     */
    protected $main_table = 'crlm_user';

    /**
     * Gets an array of available filters.
     *
     * @return array An array of deepsight_filter objects that will be available.
     */
    protected function get_filters() {
        $langidnumber = get_string('student_idnumber', 'elis_program');
        $langusername = get_string('username', 'moodle');
        $langname = get_string('name', 'moodle');
        $langfirstname = get_string('firstname', 'moodle');
        $langlastname = get_string('lastname', 'elis_program');
        $langcity = get_string('city', 'moodle');
        $langpostalcode = get_string('postalcode', 'elis_program');
        $langstate = get_string('state', 'moodle');
        $langcountry = get_string('country', 'moodle');
        $langemail = get_string('student_email', 'elis_program');
        $langaddress = get_string('address', 'moodle');
        $langphone = get_string('phone', 'moodle');
        $langtimecreated = get_string('timecreated', 'elis_program');

        $filters = array(
            new deepsight_filter_textsearch($this->DB, 'idnumber', $langidnumber, array('element.idnumber' => $langidnumber)),
            new deepsight_filter_textsearch($this->DB, 'username', $langusername, array('element.username' => $langusername)),
            new deepsight_filter_textsearch($this->DB, 'name', $langname,
                                            array('element.firstname' => $langfirstname, 'element.lastname' => $langlastname)),
            new deepsight_filter_searchselect($this->DB, 'city', $langcity, array('element.city' => $langcity), $this->endpoint,
                                              user::TABLE, 'city'),
            new deepsight_filter_searchselect($this->DB, 'postalcode', $langpostalcode,
                                              array('element.postalcode' => $langpostalcode), $this->endpoint, user::TABLE,
                                              'postalcode'),
            new deepsight_filter_textsearch($this->DB, 'address', $langaddress, array('element.address' => $langaddress)),
            new deepsight_filter_textsearch($this->DB, 'phone', $langphone, array('element.phone' => $langphone)),
            new deepsight_filter_textsearch($this->DB, 'email', $langemail, array('element.email' => $langemail)),
            new deepsight_filter_searchselect($this->DB, 'state', $langstate,
                                              array('element.state' => $langstate), $this->endpoint, user::TABLE, 'state'),
            new deepsight_filter_searchselect($this->DB, 'country', $langcountry,
                                              array('element.country' => $langcountry), $this->endpoint, user::TABLE, 'country'),
            new deepsight_filter_date($this->DB, 'timecreated', $langtimecreated,
                                      array('element.timecreated' => $langtimecreated)),
        );

        $customfieldfilters = $this->get_custom_field_info(CONTEXT_ELIS_USER);

        return array_merge($filters, $customfieldfilters);
    }

    /**
     * Gets an array of initial filters.
     *
     * @return array An array of deepsight_filter $name properties that will be present when the user first loads the page.
     */
    protected function get_initial_filters() {
        return array('idnumber', 'name');
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
            'element.lastname' => get_string('lastname', 'elis_program')
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
        $sql = 'SELECT id, firstname, lastname FROM {'.$this->main_table.'} WHERE id '.$where;
        $results = $this->DB->get_recordset_sql($sql, $params);
        $pageresults = array_flip($ids);
        foreach ($results as $result) {
            $pageresults[$result->id] = fullname($result);
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
        if (isset($row['element_timecreated'])) {
            $row['element_timecreated'] = ds_process_displaytime($row['element_timecreated']);
        }

        // Add link to view profile for idnumber column.
        if (isset($row['element_idnumber'])) {
            $usermanagementpage = new userpage();
            $classid = optional_param('id', null, PARAM_INT);
            if ($classid !== null && $usermanagementpage->can_do_view()) {
                $target = $usermanagementpage->get_new_page(array('action' => 'view', 'id' => $row['element_id']));
                $idnumber = $row['element_idnumber'];
                $row['element_idnumber'] = '<a href="'.$target->url.'" alt="ELIS profile" title="ELIS profile">'.$idnumber.'</a>';
            }
        }

        return $row;
    }

    /**
     * Adds the inactive users check to the filter SQL.
     *
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array An array consisting of the SQL WHERE clause, and the parameters for the SQL.
     */
    protected function get_filter_sql(array $filters) {
        list($filtersql, $filterparams) = parent::get_filter_sql($filters);

        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $activefilter = 'element.inactive = 0 ';
            $filtersql = (!empty($filtersql))
                ? $filtersql.' AND '.$activefilter
                : 'WHERE '.$activefilter;
        }

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
        $activecustomfields = array_intersect_key($this->custom_fields, $filters);
        if (!empty($activecustomfields)) {
            $joinsql[] = 'JOIN {context} ctx ON ctx.instanceid = element.id AND ctx.contextlevel='.CONTEXT_ELIS_USER;
            foreach ($activecustomfields as $fieldname => $field) {
                $customfieldjoin = 'LEFT JOIN {elis_field_data_'.$field->datatype.'} '.$fieldname.' ON ';
                $customfieldjoin .= $fieldname.'.contextid = ctx.id AND '.$fieldname.'.fieldid='.$field->id;
                $joinsql[] = $customfieldjoin;
            }
        }
        return $joinsql;
    }

    /**
     * Gets search results for the datatable.
     *
     * @param array $filters    The filter array received from js. It is an array consisting of filtername=>data, and can be
     *                          passed directly to $this->get_filter_sql() to generate the required WHERE sql.
     * @param array $sort       An array of field=>direction to specify sorting for the results.
     * @param int   $limit_from The position in the dataset from which to start returning results.
     * @param int   $limit_num  The amount of results to return.
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
                'label' => $result['element_firstname'].' '.$result['element_lastname'],
            );
            $pageresults[] = $this->results_row_transform($pageresult);
        }
        return array($pageresults, $totalresults);
    }
}