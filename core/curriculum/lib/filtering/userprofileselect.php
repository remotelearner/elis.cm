<?php //$Id$
/**
 * userprofileselect.php - PHP Report filter for extra user profile menu fields
 *
 * Filter for matching user profile menu fields
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
require_once($CFG->dirroot.'/curriculum/lib/filtering/simpleselect.php');

/**
 * Generic filter based on a list of values.
 */
class generalized_filter_userprofileselect extends generalized_filter_simpleselect {

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
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     * @param array $options select options
     */
    function generalized_filter_userprofileselect($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {
       
        parent::generalized_filter_simpleselect($uniqueid, $alias, $name, $label, $advanced, $field, $options);
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

        $value = $data['value'];
        $value = addslashes($value);

        $sql = "{$this->_tables['user']}.id IN
                   (SELECT userid FROM {$CFG->prefix}user_info_data
                    WHERE fieldid = {$this->_fieldid} AND {$full_fieldname} = "
                    .(empty($this->_numeric) ? "'{$value}'" : "{$value}").')';

        //error_log("userprofileselect.php::get_filter_sql() => {$sql}");
        return $sql;
    }

}

?>
