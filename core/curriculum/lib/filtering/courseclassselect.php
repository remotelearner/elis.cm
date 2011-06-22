<?php //$Id$

require_once($CFG->dirroot.'/user/filters/lib.php');
require_once($CFG->dirroot.'/curriculum/lib/filtering/dependentselect.php');

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

        $choices_array = array('0'=>'Select a class');

        $contexts = get_contexts_by_capability_for_user('class', 'block/php_report:view', $USER->id);
        if($records = cmclass_get_listing('crsname', 'ASC', 0, 0, '', '', 0, false, $contexts)) {
            foreach($records as $record) {
                $choices_array[$record->id] = $record->idnumber;
            }
        }

        $options['choices'] = $choices_array;

        parent::generalized_filter_dependentselect($uniqueid, $alias, $name, $label, $advanced, $field, $options);
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        global $CFG;

        $full_fieldname = $this->get_full_fieldname();
        $sql = "{$full_fieldname} = {$data['value']}";

        return $sql;
    }

    /**
     * Override this method to return the main pulldown option
     * @return array List of options keyed on id
     */
    function get_main_options() {
        global $USER;

        $courses_array = array('0'=>'Select a course');

        // Fetch array of allowed classes
        $contexts = get_contexts_by_capability_for_user('class', 'block/php_report:view', $USER->id);
        if($records = cmclass_get_listing('crsname', 'ASC', 0, 0, '', '', 0, false, $contexts)) {
            $allowed_courses = array();
            foreach ($records as $record) {
                if (!in_array($record->courseid,$allowed_courses)) {
                    $allowed_courses[] = $record->courseid;
                }
            }
            sort($allowed_courses);

            // Fetch array of all courses
            $course_list = course_get_listing('name', 'ASC', 0, 0, '', '');
            foreach ($course_list as $course_obj) {
                // Only show courses that are associated with an allowed class
                if (in_array($course_obj->id,$allowed_courses)) {
                    $courses_array[$course_obj->id] = (strlen($course_obj->name) > 80) ? substr($course_obj->name,0,80) . '...' : $course_obj->name;
                }
            }
        }

        return $courses_array;
    }

}

