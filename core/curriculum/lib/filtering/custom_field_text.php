<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2010 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * userprofiletext.php - PHP Report filter for extra user profile text fields
 *
 * Filter for matching user profile text fields
 *
 * Required options include: all text filter requirements PLUS
 *  ['tables'] => array, table names as keys => table alias as values
 *  ['fieldid'] => int, the user_info_field id of the extra user profile field
 *
 * @package    elis
 * @subpackage curriculummanagement
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2011 Remote Learner.net Inc http://www.remote-learner.net
 * @author     Tyler Bannister <tyler.bannister@remote-learner.net>
 */

require_once($CFG->dirroot.'/user/filters/lib.php');
require_once($CFG->dirroot.'/curriculum/lib/filtering/text.php');

/**
 * Generic filter for custom text fields.
 */
class generalized_filter_custom_field_text extends generalized_filter_text {

    /**
     * Data type for field, used to search the correct db table
     */
    protected $_fieldtypes = array(
        'bool' => 'int',
        'char' => 'char',
        'int'  => 'int',
        'num'  => 'num',
        'text' => 'text'
    );

    /**
     * Data type for field, used to search the correct db table
     */
    protected $_datatype;

    /**
     * User profile field id (int)
     */
    protected $_fieldid;

    /**
     * Wrapper SQL to get back desired context level
     */
    protected $_wrapper = '';

    /**
     * Inner field name for complex queries that use the wrapper setting
     */
    protected $_innerfield = 'c.instanceid';

    /**
     * A prefix to add to the subquery, usually involving IN or EXISTS
     */
    protected $_subqueryprefix = '';

    /**
     * Extra conditions to add to the filter query
     */
    public $_extraconditions = '';

    /**
     * This is the context level the current field lives at
     */
    protected $_contextlevel = 0;

    /**
     * Constructor
     * @param string $alias     Alias for the table being filtered on
     * @param string $name      The name of the filter instance
     * @param string $label     The label of the filter instance
     * @param boolean $advanced Advanced form element flag
     * @param string $field     User table filed name
     */
    function generalized_filter_custom_field_text($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {
        parent::generalized_filter_text($uniqueid, $alias, $name, $label, $advanced, $field, $options);

        if (! array_key_exists('datatype', $options)) {
            print_error('missing_datatype', 'elis_core');
        }
        if (! array_key_exists($options['datatype'], $this->_fieldtypes)) {
            print_error('unknown_datatype', 'elis_core');
        }

        if (array_key_exists('wrapper', $options)) {
            $this->_wrapper = $options['wrapper'];
        }
        if (array_key_exists('innerfield', $options)) {
            $this->_innerfield = $options['innerfield'];
        }

        $this->_datatype = $options['datatype'];
        $this->_fieldid  = $options['fieldid'];

        //set up a "prefix" for the subquery, typically involving IN or EXISTS
        if (!empty($options['subqueryprefix'])) {
            //manually specified via constructor
            $this->_subqueryprefix = $options['subqueryprefix'];
        } else {
            //default to "fieldname IN ..."
            $full_fieldname = $this->get_full_fieldname();
            $this->_subqueryprefix = "{$full_fieldname} IN";
        }

        //allow for specification of extra conditions to impose on the IN/ EXISTS subquery
        $this->_extraconditions = '';
        if (!empty($options['extraconditions'])) {
            $this->_extraconditions = $options['extraconditions'];
        }

        if (!empty($options['contextlevel'])) {
            $this->_contextlevel = $options['contextlevel'];
        }
    }

    /**
     * Returns the condition to be used with SQL where
     * @uses $CFG
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        global $CFG;

        $full_fieldname = $this->get_full_fieldname();
        if (empty($full_fieldname)) {
            return null;
        }

        $value = addslashes($data['value']);

        //the data table where we can find the data we're filtering on
        $data_table = FIELDDATATABLE .'_'. $this->_fieldtypes[$this->_datatype];

        $operation = '';

        $using_default = false;

        switch($data['operator']) {
            case generalized_filter_text::$OPERATOR_CONTAINS:
                //contains
                $operation = "LIKE '%{$value}%'";

                //determine if the default value matches the necessary criteria
                $select = "fieldid = {$this->_fieldid}
                           AND contextid IS NULL
                           AND data {$operation}";
                $using_default = record_exists_select($data_table, $select);

                break;
            case generalized_filter_text::$OPERATOR_DOES_NOT_CONTAIN:
                //does not contain
                $operation = "NOT LIKE '%{$value}%'";

                //determine if a default value is specified
                $some_default_exists_select = "fieldid = {$this->_fieldid}
                                               AND contextid IS NULL";
                $some_default_exists = record_exists_select($data_table, $some_default_exists_select);

                //determine if the default value matches the necessary criteria
                $default_exists_select =  "fieldid = {$this->_fieldid}
                                           AND contextid IS NULL
                                           AND data {$operation}";
                $default_exists = record_exists_select($data_table, $default_exists_select);

                //no default value or one matching the necessary criteria means null records are ok
                $using_default = !$some_default_exists || $default_exists;

                break;
            case generalized_filter_text::$OPERATOR_IS_EQUAL_TO:
                //equals
                $operation = "= '{$value}'";

                //determine if the default value matches the necessary criteria
                $select = "fieldid = {$this->_fieldid}
                           AND contextid IS NULL
                           AND data {$operation}";
                $using_default = record_exists_select($data_table, $select);

                break;
            case generalized_filter_text::$OPERATOR_STARTS_WITH:
                //starts with
                $operation = "LIKE '{$value}%'";

                //determine if the default value matches the necessary criteria
                $select = "fieldid = {$this->_fieldid}
                           AND contextid IS NULL
                           AND data {$operation}";
                $using_default = record_exists_select($data_table, $select);

                break;
            case generalized_filter_text::$OPERATOR_ENDS_WITH:
                //ends with
                $operation = "LIKE '%{$value}'";

                //determine if the default value matches the necessary criteria
                $select = "fieldid = {$this->_fieldid}
                           AND contextid IS NULL
                           AND data {$operation}";
                $using_default = record_exists_select($data_table, $select);

                break;
            case generalized_filter_text::$OPERATOR_IS_EMPTY:
                //is empty
                $operation = "= ''";

                //determine if a default value exists
                $some_default_exists_select = "fieldid = {$this->_fieldid}
                                               AND contextid IS NULL";
                $some_default_exists = record_exists_select($data_table, $some_default_exists_select);

                //determine if the default value matches the necessary criteria
                $default_exists_select =  "fieldid = {$this->_fieldid}
                                           AND contextid IS NULL
                                           AND data {$operation}";
                $default_exists = record_exists_select($data_table, $default_exists_select);

                //no default value or one matching the necessary criteria means null records are ok
                $using_default = !$some_default_exists || $default_exists;

                break;
            default:
                //error call
                print_error('invalidoperator', 'block_php_report');
        }

        if ($using_default) {
            //the provided value matches the criteria specified by the field default
            $sql = "$this->_subqueryprefix
                    (SELECT {$this->_innerfield}
                     FROM {$CFG->prefix}context c
                     LEFT JOIN {$CFG->prefix}{$data_table} d
                       ON c.id = d.contextid
                       AND d.fieldid = {$this->_fieldid}
                     {$this->_wrapper}
                     WHERE (d.data {$operation} OR
                            d.data IS NULL)
                       AND c.contextlevel = {$this->_contextlevel}
                     {$this->_extraconditions})";
        } else {
            //default criteria not met, so require data to be in right format
            $sql = "$this->_subqueryprefix
                    (SELECT {$this->_innerfield}
                     FROM {$CFG->prefix}context c
                     JOIN {$CFG->prefix}{$data_table} d
                       ON c.id = d.contextid
                       AND d.fieldid = {$this->_fieldid}
                     {$this->_wrapper}
                     WHERE d.data {$operation}
                       AND c.contextlevel = {$this->_contextlevel}
                     {$this->_extraconditions})";
        }

        return $sql;
    }

}

?>