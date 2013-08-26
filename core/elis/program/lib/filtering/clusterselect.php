<?php //$Id$
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2011 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot .'/user/filters/lib.php');
require_once($CFG->dirroot .'/elis/core/lib/filtering/equalityselect.php');

//needed for execution mode constants
require_once($CFG->dirroot .'/blocks/php_report/php_report_base.php');

// required for cluster_get_listings()
require_once($CFG->dirroot .'/elis/program/lib/data/userset.class.php');
require_once($CFG->dirroot .'/elis/program/lib/data/clusterassignment.class.php');

/**
 * Generic filter based on a list of values.
 */
class generalized_filter_clusterselect extends generalized_filter_equalityselect {
    /**
     * options for the list values
     */
    var $_options;

    var $_field;

    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     * @param array $options select options
     */
    function generalized_filter_clusterselect($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {
        global $USER;

        //figure out which capability to check
        if ($this->execution_mode == php_report::EXECUTION_MODE_SCHEDULED) {
            $capability = 'block/php_report:schedule';
        } else {
            $capability = 'block/php_report:view';
        }

        //obtain all cluster contexts where this user can view reports
        $contexts = get_contexts_by_capability_for_user('cluster', $capability, $USER->id);

        //set up cluster listing
        if ($records = $this->cluster_dropdown_get_listing($contexts)) {
            foreach ($records as $record) {
                if (empty($choices_array[$record->id])) {
                    //if (count($choices_array) > 1) {
                    //    $choices_array[-$record->id] = $cluster_group_separator;
                    //}
                    $ancestors = $record->depth - 1;
                    // shorten really long cluster names
                    $clstname = (strlen($record->name) > 100)
                            ? substr($record->name, 0, 100) .'...'
                            : $record->name;
                    $choices_array[$record->id] = $ancestors
                            ? str_repeat('- ', $ancestors) . $clstname
                            : $clstname;
                    //merge in child clusters
                    $child_array = $this->find_child_clusters($records, $record->id, $ancestors);
                    $choices_array = $this->merge_array_keep_keys($choices_array, $child_array);
                }
            }
        }

        //explicitly set the list of available options
        $this->_options = $choices_array;

        //expected by the parent class
        $options['choices'] = $choices_array;
        $options['numeric'] = true;

        parent::generalized_filter_equalityselect($uniqueid, $alias, $name, $label, $advanced, $field, $options);
    }

    /**
     * Gets the cluster listing for the drop-down menu
     * @param mixed  $contexts
     * @return array cluster listing
     */
    function cluster_dropdown_get_listing($contexts = null) {
        global $DB;

        //ob_start();
        //var_dump($contexts);
        //$tmp = ob_get_contents();
        //ob_end_clean();
        $sql = 'SELECT * FROM {'. userset::TABLE .'}';
        $params = array();

        if ($contexts) {
            $filter_obj = $contexts->get_filter('id', 'cluster');
            $filter_sql = $filter_obj->get_sql();
            if (isset($filter_sql['where'])) {
                $sql .= " WHERE {$filter_sql['where']}";
                $params = $filter_sql['where_parameters'];
            }
        }

        $sql .= ' ORDER BY depth ASC, name ASC';
        //error_log("cluster_dropdown_get_listing(); SQL => {$sql}; contextset = {$tmp}");
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Returns the child clusters of the given parent
     * @param array $records complete cluster objects array
     * @param int $parentid the parent cluster id
     * @param int $indent the parent cluster indentation level
     * @return array child clusters array
     */
    function find_child_clusters($records, $parentid, $indent = 0) {
        $choices_array = array();
        $indent++;

        if ($indent < 1000) { // avoid infinite recursion
            foreach ($records as $record) {
                if ($record->parent == $parentid) {
                    // shorten really long cluster names
                    $name = (strlen($record->name) > 100)
                          ? substr($record->name,0,100) . '...'
                          : $record->name;
                    $choices_array[$record->id] = str_repeat('- ', $indent) . $name;

                    // recursively find child clusters
                    $child_array = $this->find_child_clusters($records, $record->id, $indent);
                    $choices_array = $this->merge_array_keep_keys($choices_array, $child_array);
                }
            }
        }

        return $choices_array;
    }

    /**
     * Returns the merged array of two given arrays without renumbering the key values
     * @param array $array1 first array
     * @param array $array2 second array
     * @return array the merged array
     */
    function merge_array_keep_keys($array1, $array2) {
        $merged_array = $array1;

        foreach ($array2 as $array2_key => $array2_value) {
            $merged_array[$array2_key] = $array2_value;
        }

        return $merged_array;
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        static $counter = 0;
        $param_name = 'ex_clusterselect'. $counter++;

        $full_fieldname = $this->get_full_fieldname();
        if (empty($full_fieldname)) {
            return null;
        }

        if ($data['operator'] == generalized_filter_equalityselect::$OPERATOR_IS_EQUAL_TO) {
            $operator = 'IN';
        } else if ($data['operator'] == generalized_filter_equalityselect::$OPERATOR_NOT_EQUAL_TO) {
            $operator = 'NOT IN';
        } else {
            //error call
            print_error('invalidoperator', 'elis_core');
        }

        return array("{$full_fieldname} $operator
                     (SELECT inner_usercluster.userid FROM
                     {". clusterassignment::TABLE ."} inner_usercluster
                     WHERE inner_usercluster.clusterid = :{$param_name})",
                     array($param_name => $data['value']));
    }

}

