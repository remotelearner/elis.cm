<?php //$Id$
/**
 * userprofiletext.php - PHP Report filter for extra user profile text fields
 *
 * Filter for matching user profile text fields
 *
 * Required options include: all text filter requirements PLUS
 *  ['tables'] => array, table names as keys => table alias as values
 *  ['fieldid'] => int, the user_info_field id of the extra user profile field
 *
 * @author Brent Boghosian <brent.boghosian@remote-learner.net>
 * @version $Id$
 * @package curriculum/lib/filtering
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright (C) 2008-2011 Remote Learner.net Inc http://www.remote-learner.net
 */

require_once($CFG->dirroot.'/user/filters/lib.php');
require_once($CFG->dirroot.'/curriculum/lib/filtering/text.php');

/**
 * Generic filter for user profile text fields.
 */
class generalized_filter_userprofiletext extends generalized_filter_text {

    /**
     * Array of tables: table as key => table alias as value
     */
    var $_tables;

    /**
     * User profile field id (int)
     */
    var $_fieldid;

    /**
     * Constructor
     * @param string $alias aliacs for the table being filtered on
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     */
    function generalized_filter_userprofiletext($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {
        parent::generalized_filter_text($uniqueid, $alias, $name, $label, $advanced, $field, $options);
        $this->_tables = $options['tables'];
        $this->_fieldid = $options['fieldid'];
        //print_object($this);
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

        $sql = "{$this->_tables['user']}.id IN
                   (SELECT userid FROM {$CFG->prefix}user_info_data
                    WHERE fieldid = {$this->_fieldid} AND {$full_fieldname} ";

        switch($data['operator']) {
            case generalized_filter_text::$OPERATOR_CONTAINS:
                //contains
                $sql .= "LIKE '%{$value}%'";
                break;
            case generalized_filter_text::$OPERATOR_DOES_NOT_CONTAIN:
                //does not contain
                $sql .= "NOT LIKE '%{$value}%'";
                break;
            case generalized_filter_text::$OPERATOR_IS_EQUAL_TO:
                //equals
                $sql .= "= '{$value}'";
                break;
            case generalized_filter_text::$OPERATOR_STARTS_WITH:
                //starts with
                $sql .= "LIKE '{$value}%'";
                break;
            case generalized_filter_text::$OPERATOR_ENDS_WITH:
                //ends with
                $sql .= "LIKE '%{$value}'";
                break;
            case generalized_filter_text::$OPERATOR_IS_EMPTY:
                $sql .= "= ''";
                break;
            default:
                //error call
                print_error('invalidoperator', 'block_php_report');
        }

        $sql .= " )";
        //error_log("userprofiletext.php::get_filter_sql() => {$sql}");
        return $sql;
    }

}

?>
