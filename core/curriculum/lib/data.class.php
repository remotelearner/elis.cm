<?php
/**
 * Contains the database functions used by the Curriculum Management System.
 * These should be able to be replaced by any database system. We use this so
 * that we can re-use this system outside of Moodle as well.  This class should
 * only be extended by a new class using the name 'dbtype'_database
 *
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
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
 * @subpackage curriculummanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

class database_base {

    // Private variables (properties)
    var $_dbconnection;     // Holds the database connection variable.

    /**
     * Constructor. Optionally can create a data connection.
     *
     * @param $dbhost string The name of the host server.
     * @param $dbname string The name of the database.
     * @param $dbuser string The login name for the database and server.
     * @param $dbpass string The login password for the database and server.
     * @param $dbpersist boolean Persistant connection or not (if supported by $dbtype).
     *
     */
    function database ($dbhost='', $dbname='', $dbuser='', $dbpass='', $dbpersist=false) {
        $this->dbconnect($dbhost, $dbname, $dbuser, $dbpass, $dbpersist);
    }

    /**
     * Connects a database object if arguments are valid.
     */
    function dbconnect ($dbhost='', $dbname='', $dbuser='', $dbpass='', $dbpersist=false) {
        echo get_string('method_extended', 'block_curr_admin');
    }


    function get_field($table, $return, $field1, $value1, $field2='', $value2='', $field3='', $value3='') {
        echo get_string('method_extended', 'block_curr_admin');
    }

    function get_field_select($table, $return, $select) {
        echo get_string('method_extended', 'block_curr_admin');
    }

    function get_field_sql($sql) {
        echo get_string('method_extended', 'block_curr_admin');;
    }

    function set_field($table, $newfield, $newvalue, $field1, $value1, $field2='', $value2='', $field3='', $value3='') {
        echo get_string('method_extended', 'block_curr_admin');;
    }

    function set_field_select($table, $newfield, $newvalue, $select, $localcall = false) {
        echo get_string('method_extended', 'block_curr_admin');;
    }

    function record_exists($table, $field1='', $value1='', $field2='', $value2='', $field3='', $value3='') {
        echo get_string('method_extended', 'block_curr_admin');;
    }

    function record_exists_select($table, $select='') {
        echo get_string('method_extended', 'block_curr_admin');;
    }

    function record_exists_sql($sql) {
        echo get_string('method_extended', 'block_curr_admin');;
    }

    function get_record($table, $field1, $value1, $field2='', $value2='', $field3='', $value3='', $fields='*') {
        echo get_string('method_extended', 'block_curr_admin');;
    }

    function get_record_select($table, $select='', $fields='*') {
        echo get_string('method_extended', 'block_curr_admin');;
    }

    function get_record_sql($sql, $expectmultiple=false, $nolimit=false) {
        echo get_string('method_extended', 'block_curr_admin');;
    }

    function count_records($table, $field1='', $value1='', $field2='', $value2='', $field3='', $value3='') {
        echo get_string('method_extended', 'block_curr_admin');;
    }

    function count_records_select($table, $select='', $countitem='COUNT(*)') {
        echo get_string('method_extended', 'block_curr_admin');;
    }

    function count_records_sql($sql) {
        echo get_string('method_extended', 'block_curr_admin');;
    }

    function get_records($table, $field='', $value='', $sort='', $fields='*', $limitfrom='', $limitnum='') {
        echo get_string('method_extended', 'block_curr_admin');;
    }

    function get_records_select($table, $select='', $sort='', $fields='*', $limitfrom='', $limitnum='') {
        echo get_string('method_extended', 'block_curr_admin');;
    }

    function get_records_sql($sql, $limitfrom='', $limitnum='') {
        echo get_string('method_extended', 'block_curr_admin');;
    }

    function get_records_list($table, $field='', $values='', $sort='', $fields='*', $limitfrom='', $limitnum='') {
        echo get_string('method_extended', 'block_curr_admin');;
    }

    function get_records_menu($table, $field='', $value='', $sort='', $fields='*', $limitfrom='', $limitnum='') {
        echo get_string('method_extended', 'block_curr_admin');;
    }

    function get_records_select_menu($table, $select='', $sort='', $fields='*', $limitfrom='', $limitnum='') {
        echo get_string('method_extended', 'block_curr_admin');;
    }

    function get_records_sql_menu($sql, $limitfrom='', $limitnum='') {
        echo get_string('method_extended', 'block_curr_admin');;
    }

    function insert_record($table, $dataobject, $returnid=true, $primarykey='id') {
        echo get_string('method_extended', 'block_curr_admin');;
    }

    function update_record($table, $dataobject) {
        echo get_string('method_extended', 'block_curr_admin');;
    }

    function delete_records($table, $field1='', $value1='', $field2='', $value2='', $field3='', $value3='') {
        echo get_string('method_extended', 'block_curr_admin');;
    }

    function delete_records_select($table, $select='') {
        echo get_string('method_extended', 'block_curr_admin');;
    }

    function execute_sql($command, $feedback=true) {
        echo get_string('method_extended', 'block_curr_admin');;
    }

    /**
     * Function to return the proper SQL string for comparisons for the database type used.
     */
    function sql_compare() {
        echo get_string('method_extended', 'block_curr_admin');;
    }

    /**
     * Function to clean text before going into database. This *must* be provided by the extended class.
     *
     * @param $text string The text to be cleaned.
     *
     */
    function clean_text($text) {
        echo get_string('method_extended', 'block_curr_admin');
    }

    /**
     * Function to clean HTML before going into database. This *must* be provided by the extended class.
     *
     * @param $text string The text to be cleaned.
     *
     */
    function clean_html($text) {
        echo get_string('method_extended', 'block_curr_admin');
    }

    /**
     * Function to clean integers before going into database. This *must* be provided by the extended class.
     *
     * @param $number int The number to be cleaned.
     *
     */
    function clean_int($number) {
        echo get_string('method_extended', 'block_curr_admin');
    }

    /**
     * Function to clean numbers before going into database. This *must* be provided by the extended class.
     *
     * @param $number float The number to be cleaned.
     *
     */
    function clean_num($number) {
        echo get_string('method_extended', 'block_curr_admin');
    }
}


class moodle_database extends database_base {
    /**
     * Constructor. Optionally can create a data connection.
     *
     * @param $dbhost string The name of the host server.
     * @param $dbname string The name of the database.
     * @param $dbuser string The login name for the database and server.
     * @param $dbpass string The login password for the database and server.
     * @param $dbpersist boolean Persistant connection or not (if supported by $dbtype).
     *
     */
    function moodle_database ($dbhost='', $dbname='', $dbuser='', $dbpass='', $dbpersist=false) {
        parent::database($dbhost, $dbname, $dbuser, $dbpass, $dbpersist);
    }

    /**
     * Connects a database object if arguments are valid.
     */
    function dbconnect () {
        /// Because we're in a class, we need to use the $GLOBALS directory.
        $this->_dbconnection = &$GLOBALS['db'];
    }

    function get_field($table, $return, $field1, $value1, $field2='', $value2='', $field3='', $value3='') {
        return get_field($table, $return, $field1, $value1, $field2, $value2, $field3, $value3);
    }

    function get_field_select($table, $return, $select) {
        return get_field_select($table, $return, $select);
    }

    function get_field_sql($sql) {
        return get_field_sql($sql);
    }

    function set_field($table, $newfield, $newvalue, $field1, $value1, $field2='', $value2='', $field3='', $value3='') {
        return set_field($table, $newfield, $newvalue, $field1, $value1, $field2, $value2, $field3, $value3);
    }

    function set_field_select($table, $newfield, $newvalue, $select, $localcall = false) {
        return set_field_select($table, $newfield, $newvalue, $select, $localcall);
    }

    function record_exists($table, $field1='', $value1='', $field2='', $value2='', $field3='', $value3='') {
        return record_exists($table, $field1, $value1, $field2, $value2, $field3, $value3);
    }

    function record_exists_select($table, $select='') {
        return record_exists_select($table, $select);
    }

    function record_exists_sql($sql) {
        return record_exists_sql($sql);
    }

    function get_record($table, $field1, $value1, $field2='', $value2='', $field3='', $value3='', $fields='*') {
        return get_record($table, $field1, $value1, $field2, $value2, $field3, $value3, $fields);
    }

    function get_record_select($table, $select='', $fields='*') {
        return get_record_select($table, $select, $fields);
    }

    function get_record_sql($sql, $expectmultiple=false, $nolimit=false) {
        return get_record_sql($sql, $expectmultiple, $nolimit);
    }

    function count_records($table, $field1='', $value1='', $field2='', $value2='', $field3='', $value3='') {
        return count_records($table, $field1, $value1, $field2, $value2, $field3, $value3);
    }

    function count_records_select($table, $select='', $countitem='COUNT(*)') {
        return count_records_select($table, $select, $countitem);
    }

    function count_records_sql($sql) {
        return count_records_sql($sql);
    }

    function get_records($table, $field='', $value='', $sort='', $fields='*', $limitfrom='', $limitnum='') {
        return get_records($table, $field, $value, $sort, $fields, $limitfrom, $limitnum);
    }

    function get_records_select($table, $select='', $sort='', $fields='*', $limitfrom='', $limitnum='') {
        return get_records_select($table, $select, $sort, $fields, $limitfrom, $limitnum);
    }

    function get_records_sql($sql, $limitfrom='', $limitnum='') {
        return get_records_sql($sql, $limitfrom, $limitnum);
    }

    function get_records_list($table, $field='', $values='', $sort='', $fields='*', $limitfrom='', $limitnum='') {
        return get_records_list($table, $field, $values, $sort, $fields, $limitfrom, $limitnum);
    }

    function get_records_menu($table, $field='', $value='', $sort='', $fields='*', $limitfrom='', $limitnum='') {
        return get_records_menu($table, $field, $value, $sort, $fields, $limitfrom, $limitnum);
    }

    function get_records_select_menu($table, $select='', $sort='', $fields='*', $limitfrom='', $limitnum='') {
        return get_records_select_menu($table, $select, $sort, $fields, $limitfrom, $limitnum);
    }

    function get_records_sql_menu($sql, $limitfrom='', $limitnum='') {
        return get_records_sql_menu($sql, $limitfrom, $limitnum);
    }

    /**
     * Function to insert a record and return the primary index.
     *
     */
    function insert_record($table, $dataobject, $returnid=true, $primarykey='id') {
        return insert_record($table, $dataobject, $returnid, $primarykey);
    }

    function update_record($table, $dataobject) {
        return update_record($table, $dataobject);
    }

    function delete_records($table, $field1='', $value1='', $field2='', $value2='', $field3='', $value3='') {
        return delete_records($table, $field1, $value1, $field2, $value2, $field3, $value3);
    }

    function delete_records_select($table, $select='') {
        return delete_records_select($table, $select);
    }

    function execute_sql($command, $feedback=true) {
        return execute_sql($command, $feedback);
    }

    /**
     * Function to return the proper SQL string for comparisons for the database type used.
     */
    function sql_compare() {
        return sql_ilike();
    }

    /**
     * Function to any 'massaging' of table names if necessary.
     */
    function prefix_table($table) {
        global $CFG;

        return ($CFG->prefix.$table);
    }

    /**
     * Function to clean text before going into database. Uses Moodle functions.
     *
     * @param $text string The text to be cleaned.
     *
     */
    function clean_text($text) {
        return clean_param( $text, PARAM_TEXT);
    }

    /**
     * Function to clean html before going into database. Uses Moodle functions.
     *
     * @param $text string The text to be cleaned.
     *
     */
    function clean_html($text) {
        return clean_param($text, PARAM_CLEAN);
    }

    /**
     * Function to clean integers before going into database. Uses Moodle functions.
     *
     * @param $number int The number to be cleaned.
     *
     */
    function clean_int($number) {
        return clean_param($number, PARAM_INT);
    }

    /**
     * Function to clean numbers before going into database. Uses Moodle functions.
     *
     * @param $number float The number to be cleaned.
     *
     */
    function clean_num($number) {
        return clean_param($number, PARAM_NUMBER);
    }
}

class wr_database extends database_base {
    /**
     * Constructor. Optionally can create a data connection.
     *
     * @param $dbhost string The name of the host server.
     * @param $dbname string The name of the database.
     * @param $dbuser string The login name for the database and server.
     * @param $dbpass string The login password for the database and server.
     * @param $dbpersist boolean Persistant connection or not (if supported by $dbtype).
     *
     */
    function wr_database ($dbhost='', $dbname='', $dbuser='', $dbpass='', $dbpersist=false) {
        parent::database($dbhost, $dbname, $dbuser, $dbpass, $dbpersist);
    }

    /**
     * Connects a database object if arguments are valid.
     */
    function dbconnect () {
        global $CFG;

        $this->oldmoodleprefix = $CFG->prefix.'';    // Remember it.  The '' is to prevent PHP5 reference.. see bug 3223

        if ($CFG->dbtype != 'postgres7') {
            $CFG->prefix = $CFG->dbname.'.'.$CFG->prefix;
        }
        $this->newmoodleprefix = $CFG->prefix.'';    // Remember it.  The '' is to prevent PHP5 reference.. see bug 3223
        $this->prefix = CURMAN_UDB_DBNAME.'.';

        $adb = &ADONewConnection($CFG->dbtype);
        $dbconnected = $adb->Connect(CURMAN_UDB_DBHOST, CURMAN_UDB_DBUSER, CURMAN_UDB_DBPASS,
                                     CURMAN_UDB_DBNAME);

        if (!$dbconnected) {
            return false;
        } else {
        /// Forcing ASSOC mode for ADOdb (some DBs default to FETCH_BOTH)
            $adb->SetFetchMode(ADODB_FETCH_ASSOC);
        }

        $this->_dbconnection = $adb;
    }

    function get_field($table, $return, $field1, $value1, $field2='', $value2='', $field3='', $value3='') {
        global $CFG;

        $CFG->prefix = $this->prefix;
        $records = get_field($table, $return, $field1, $value1, $field2, $value2, $field3, $value3);
        $CFG->prefix = $this->newmoodleprefix;
        return $records;
    }

    function get_field_select($table, $return, $select) {
        global $CFG;

        $CFG->prefix = $this->prefix;
        $records = get_field_select($table, $return, $select);
        $CFG->prefix = $this->newmoodleprefix;
        return $records;
    }

    function get_field_sql($sql) {
        return get_field_sql($sql);
    }

    function set_field($table, $newfield, $newvalue, $field1, $value1, $field2='', $value2='', $field3='', $value3='') {
        global $CFG;

        $CFG->prefix = $this->prefix;
        $records = set_field($table, $newfield, $newvalue, $field1, $value1, $field2, $value2, $field3, $value3);
        $CFG->prefix = $this->newmoodleprefix;
        return $records;
    }

    function set_field_select($table, $newfield, $newvalue, $select, $localcall = false) {
        global $CFG;

        $CFG->prefix = $this->prefix;
        $records = set_field_select($table, $newfield, $newvalue, $select, $localcall);
        $CFG->prefix = $this->newmoodleprefix;
        return $records;
    }

    function record_exists($table, $field1='', $value1='', $field2='', $value2='', $field3='', $value3='') {
        global $CFG;

        $CFG->prefix = $this->prefix;
        $records = record_exists($table, $field1, $value1, $field2, $value2, $field3, $value3);
        $CFG->prefix = $this->newmoodleprefix;
        return $records;
    }

    function record_exists_select($table, $select='') {
        global $CFG;

        $CFG->prefix = $this->prefix;
        $records = record_exists_select($table, $select);
        $CFG->prefix = $this->newmoodleprefix;
        return $records;
    }

    function record_exists_sql($sql) {
        return record_exists_sql($sql);
    }

    function get_record($table, $field1, $value1, $field2='', $value2='', $field3='', $value3='', $fields='*') {
        global $CFG;

        $CFG->prefix = $this->prefix;
        $records = get_record($table, $field1, $value1, $field2, $value2, $field3, $value3, $fields);
        $CFG->prefix = $this->newmoodleprefix;
        return $records;
    }

    function get_record_select($table, $select='', $fields='*') {
        global $CFG;

        $CFG->prefix = $this->prefix;
        $records = get_record_select($table, $select, $fields);
        $CFG->prefix = $this->newmoodleprefix;
        return $records;
    }

    function get_record_sql($sql, $expectmultiple=false, $nolimit=false) {
        return get_record_sql($sql, $expectmultiple, $nolimit);
    }

    function count_records($table, $field1='', $value1='', $field2='', $value2='', $field3='', $value3='') {
        global $CFG;

        $CFG->prefix = $this->prefix;
        $records = count_records($table, $field1, $value1, $field2, $value2, $field3, $value3);
        $CFG->prefix = $this->newmoodleprefix;
        return $records;
    }

    function count_records_select($table, $select='', $countitem='COUNT(*)') {
        global $CFG;

        $CFG->prefix = $this->prefix;
        $records = count_records_select($table, $select, $countitem);
        $CFG->prefix = $this->newmoodleprefix;
        return $records;
    }

    function count_records_sql($sql) {
        return count_records_sql($sql);
    }

    function get_records($table, $field='', $value='', $sort='', $fields='*', $limitfrom='', $limitnum='') {
        global $CFG;

        $CFG->prefix = $this->prefix;
        $records = get_records($table, $field, $value, $sort, $fields, $limitfrom, $limitnum);
        $CFG->prefix = $this->newmoodleprefix;
        return $records;
    }

    function get_records_select($table, $select='', $sort='', $fields='*', $limitfrom='', $limitnum='') {
        global $CFG;

        $CFG->prefix = $this->prefix;
        $records = get_records_select($table, $select, $sort, $fields, $limitfrom, $limitnum);
        $CFG->prefix = $this->newmoodleprefix;
        return $records;
    }

    function get_records_sql($sql, $limitfrom='', $limitnum='') {
        return get_records_sql($sql, $limitfrom, $limitnum);
    }

    function get_records_list($table, $field='', $values='', $sort='', $fields='*', $limitfrom='', $limitnum='') {
        global $CFG;

        $CFG->prefix = $this->prefix;
        $records = get_records_list($table, $field, $values, $sort, $fields, $limitfrom, $limitnum);
        $CFG->prefix = $this->newmoodleprefix;
        return $records;
    }

    function get_records_menu($table, $field='', $value='', $sort='', $fields='*', $limitfrom='', $limitnum='') {
        global $CFG;

        $CFG->prefix = $this->prefix;
        $records = get_records_menu($table, $field, $value, $sort, $fields, $limitfrom, $limitnum);
        $CFG->prefix = $this->newmoodleprefix;
        return $records;
    }

    function get_records_select_menu($table, $select='', $sort='', $fields='*', $limitfrom='', $limitnum='') {
        global $CFG;

        $CFG->prefix = $this->prefix;
        $records = get_records_select_menu($table, $select, $sort, $fields, $limitfrom, $limitnum);
        $CFG->prefix = $this->newmoodleprefix;
        return $records;
    }

    function get_records_sql_menu($sql, $limitfrom='', $limitnum='') {
        return get_records_sql_menu($sql, $limitfrom, $limitnum);
    }

    /**
     * Function to insert a record and return the primary index.
     *
     */
    function insert_record($table, $dataobject, $returnid=true, $primarykey='id') {
        global $CFG;

        $CFG->prefix = $this->prefix;
        $records = insert_record($table, $dataobject, $returnid, $primarykey);
        $CFG->prefix = $this->newmoodleprefix;
        return $records;
    }

    function update_record($table, $dataobject) {
        global $CFG;

        $CFG->prefix = $this->prefix;
        $records = update_record($table, $dataobject);
        $CFG->prefix = $this->newmoodleprefix;
        return $records;
    }

    function delete_records($table, $field1='', $value1='', $field2='', $value2='', $field3='', $value3='') {
        global $CFG;

        $CFG->prefix = $this->prefix;
        $records = delete_records($table, $field1, $value1, $field2, $value2, $field3, $value3);
        $CFG->prefix = $this->newmoodleprefix;
        return $records;
    }

    function delete_records_select($table, $select='') {
        global $CFG;

        $CFG->prefix = $this->prefix;
        $records = delete_records_select($table, $select);
        $CFG->prefix = $this->newmoodleprefix;
        return $records;
    }

    function execute_sql($command, $feedback=true) {
        return execute_sql($command, $feedback);
    }

    /**
     * Function to return the proper SQL string for comparisons for the database type used.
     */
    function sql_compare() {
        return sql_ilike();
    }

    /**
     * Function to any 'massaging' of table names if necessary.
     */
    function prefix_table($table) {
        return ($this->prefix.$table);
    }

    /**
     * Function to clean text before going into database. Uses Moodle functions.
     *
     * @param $text string The text to be cleaned.
     *
     */
    function clean_text($text) {
        return clean_param( $text, PARAM_TEXT);
    }

    /**
     * Function to clean html before going into database. Uses Moodle functions.
     *
     * @param $text string The text to be cleaned.
     *
     */
    function clean_html($text) {
        return clean_param($text, PARAM_CLEAN);
    }

    /**
     * Function to clean integers before going into database. Uses Moodle functions.
     *
     * @param $number int The number to be cleaned.
     *
     */
    function clean_int($number) {
        return clean_param($number, PARAM_INT);
    }

    /**
     * Function to clean numbers before going into database. Uses Moodle functions.
     *
     * @param $number float The number to be cleaned.
     *
     */
    function clean_num($number) {
        return clean_param($number, PARAM_NUMBER);
    }
}
/**
 * This function constructs an appropriate database object based on the passed arguments.
 *
 * @param $dbtype string The name of the database type. A class must exist for this type.
 * @param $dbhost string The name of the host server.
 * @param $dbname string The name of the database.
 * @param $dbuser string The login name for the database and server.
 * @param $dbpass string The login password for the database and server.
 * @param $dbpersist boolean Persistant connection or not (if supported by $dbtype).
 *
 * @return object The specific database type extended from database class.
 *
 */
function database_factory($dbtype, $dbhost='', $dbname='', $dbuser='', $dbpass='', $dbpersist=false) {

    if (empty($dbtype)) {
        return false;
    }

    $dbclass = $dbtype.'_database';
    /// If the class isn't defined yet, see if it exists.
    if (!class_exists($dbclass)) {
        $dbclassfile = CURMAN_DIRLOCATION.'/database/'.$dbtype.'_data.class.php';
        if (!file_exists($dbclassfile)) {
            return false;
        }
        require_once($dbclassfile);
        if (!class_exists($dbclass)) {
            return false;
        }

    }

    return new $dbclass($dbhost, $dbname, $dbuser, $dbpass, $dbpersist);
}

/**
 * A collection of data objects (based on an ADODB recordset)
 */
class recordset_iterator implements Iterator {
    public function __construct($recordset) {
        $this->rs = $recordset;
        if (!empty($recordset)) {
            $this->firstfield = $recordset->FetchField(0);
        }
    }

    public function current() {
        return empty($this->rs) ? null : (rs_EOF($this->rs) ? false : rs_fetch_record($this->rs));
    }

    public function key() {
        if (empty($this->rs)) {
            return null;
        }
        $rec = $this->current();
        return $rec->{$this->firstfield->name};
    }

    public function next() {
        if (!empty($this->rs)) {
            rs_next_record($this->rs);
        }
    }

    public function rewind() {
        // no seeking, sorry - let's ignore it ;-)
        return;
    }

    public function valid() {
        return !empty($this->rs) && !rs_EOF($this->rs);
    }
}
?>
