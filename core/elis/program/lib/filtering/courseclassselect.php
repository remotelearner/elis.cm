<?php //$Id$
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @package    elis-program
 * @subpackage filtering
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot .'/user/filters/lib.php');
require_once($CFG->dirroot .'/elis/core/lib/filtering/dependentselect.php');

/**
 * Generic filter based on a list of values.
 */
class generalized_filter_courseclassselect extends generalized_filter_dependentselect {
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
    function generalized_filter_courseclassselect($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {
        global $USER;

        $options['numeric'] = true;
        $options['default'] = '0';

        $choices_array = array('0' => get_string('selectaclass', 'elis_program'));

        $contexts = get_contexts_by_capability_for_user('class', 'block/php_report:view', $USER->id);
        $records = pmclass_get_listing('idnumber', 'ASC', 0, 0, '', '', 0, false, $contexts);
        foreach ($records as $record) {
            $choices_array[$record->id] = $record->idnumber;
        }
        unset($records);

        $options['choices'] = $choices_array;

        parent::generalized_filter_dependentselect($uniqueid, $alias, $name, $label, $advanced, $field, $options);
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return array      the filtering condition with optional parameters
     *                    or null if the filter is disabled
     */
    function get_sql_filter($data) {
        static $counter = 0;
        $param_name = 'ex_courseclassselect'. $counter++;
        $full_fieldname = $this->get_full_fieldname();
        if (empty($full_fieldname)) {
            return null;
        }
        return array("{$full_fieldname} = :{$param_name}",
                     array($param_name => $data['value']));
    }

    /**
     * Override this method to return the main pulldown option
     * @return array List of options keyed on id
     */
    function get_main_options() {
        global $USER;

        $courses_array = array('0' => get_string('selectacourse', 'elis_program'));

        // Fetch array of allowed classes
        $contexts = get_contexts_by_capability_for_user('class', 'block/php_report:view', $USER->id);
        $records = pmclass_get_listing('crsname', 'ASC', 0, 0, '', '', 0, false, $contexts);
        if ($records->valid() === true) {
            $allowed_courses = array();
            foreach ($records as $record) {
                if (!in_array($record->courseid, $allowed_courses)) {
                    $allowed_courses[] = $record->courseid;
                }
            }
            sort($allowed_courses);

            // Fetch array of all courses
            $course_list = course_get_listing('name', 'ASC', 0, 0, '', '');
            foreach ($course_list as $course_obj) {
                // Only show courses that are associated with an allowed class
                if (in_array($course_obj->id, $allowed_courses)) {
                    $courses_array[$course_obj->id] = (strlen($course_obj->name) > 80) ? substr($course_obj->name, 0, 80) .'...' : $course_obj->name;
                }
            }
        }
        unset($records);

        return $courses_array;
    }

}

