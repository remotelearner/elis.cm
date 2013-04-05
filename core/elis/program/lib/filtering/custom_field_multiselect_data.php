<?php
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
//require_once($CFG->dirroot .'/elis/program/lib/filtering/custom_field_multiselect.php');

/**
 * Generic filter based on a list of values.
 */
class generalized_filter_custom_field_multiselect_data {
    /**
     * options for the list values
     */
    var $_block_instance;
    var $_fieldidlist;
    var $_fieldnamelist;

    /**
     * Constructor
     * @param string $block_instance the block id
     * @param pointer to array $fieldidlist array of selected custom field ids
     * @param pointer to array $fieldnamelist array of selected of custom field names
     * @return none
     */
    function generalized_filter_custom_field_multiselect_data($block_instance,$fieldidlist,$fieldnamelist) {
        $this->_block_instance = $block_instance;
        $this->_fieldidlist = $fieldidlist;
        $this->_fieldnamelist = $fieldnamelist;
    }

    /*
     * Update field id and name lists
     * @param   string  $action     update action
     * @param   int     $fieldid    id of custom field to be acted upon
     * @param   string  $fieldname  name of custom field to be acted upon
     * @param   pointer to array    $fieldidlist    pointer to array of all the custom field ids included in the filter
     * @param   pointer to array    $fieldname list pointer to array of all the custom field names included in the filter
     */
    function update_field_list($action, $fieldid, $fieldname, $fieldidlist, $fieldnamelist, $scheduled) {
        global $SESSION;

        switch ($action) {
            case 'add':
                $fieldidlist[] = $fieldid;
                $fieldnamelist[] = $fieldname;
                break;
            case 'remove':
                // Get field index of array element - used for moveup and move down
                $fieldindex = array_search($fieldid,$fieldidlist);
                unset($fieldidlist[$fieldindex]);
                unset($fieldnamelist[$fieldindex]);
                break;
            case 'up':
                // Get field index of array element - used for moveup and move down
                $fieldindex = array_search($fieldid,$fieldidlist);
                $fieldidlist = $this->move_up($fieldidlist,$fieldindex);
                $fieldnamelist = $this->move_up($fieldnamelist,$fieldindex);
                break;
            case 'down':
                // Get field index of array element - used for moveup and move down
                $fieldindex = array_search($fieldid,$fieldidlist);
                $fieldidlist = $this->move_down($fieldidlist,$fieldindex);
                $fieldnamelist = $this->move_down($fieldnamelist,$fieldindex);
                break;
            case 'init':
                // Pull filter params if report is not being scheduled
                if (!$scheduled) {
                    $user_preferences = php_report_filtering_get_user_preferences($this->_block_instance);
                    $report_index = 'php_report_'.$this->_block_instance.'/field'.$this->_block_instance;
                }
                if ($scheduled) {
                    // accept current fieldidlist if in schedule mode
                    $fieldidlist = $fieldidlist;
                    $fieldnamelist =  $fieldnamelist;
                } else if (!empty($report_index) && isset($user_preferences[$report_index])) {
                    $fieldidlist = unserialize(base64_decode($user_preferences[$report_index]));
                    $fieldnamelist = array();
                } else {
                    $fieldidlist = array();
                    $fieldnamelist = array();
                }

                break;
        }

        // Reset this class's fieldidlist and fieldnamelist to recalculated lists
        $this->_fieldidlist = $fieldidlist;
        $this->_fieldnamelist = $fieldnamelist;
    }

    /*
     * Move array element up
     * @param array $input containing element to be moved
     * @param int   $index index of element to be moved
     *
     * @return array $new_array new, reordered array
     */
    function move_up($input, $index) {
        $new_array = $input;

        if ((count($new_array) > $index) && ($index > 0)) {
            array_splice($new_array, $index - 1, 0, $input[$index]);
            array_splice($new_array, $index + 1, 1);
        }

        return $new_array;
    }

    /*
     * Move array element down
     * @param array $input containing element to be moved
     * @param int   $index index of element to be moved
     *
     * @return array $new_array new, reordered array
     */
    function move_down($input,$index) {
        $new_array = $input;

        if (count($new_array) > $index) {
            array_splice($new_array, $index + 2, 0, $input[$index]);
            array_splice($new_array, $index, 1);
        }

        return $new_array;
    }

    /* Get report class name
     * @param object $obj   report object
     * @return string $reportname name of report
     */
    function get_report_name($obj) {
        $classname = get_class($obj);
        $reportname = substr($classname, 0, strlen($classname) - strlen('_report'));

        return $reportname;
    }
}

