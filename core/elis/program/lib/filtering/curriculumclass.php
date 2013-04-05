<?php
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
 * @package    elis-programmanagement
 * @subpackage filtering
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

/**
 * curriculummatch.php - PHP Report filter
 *
 * Group of filters for matching curriculum fields
 *
 * Configuration options include:
 *  ['choices'] => array, defines included DB user fields [as key],
 *                 *optional* value (string) as form field label
 *                 language string key for options['langfile'] (below).
 * fields (keys) may include:
 * fullname, lastname, firstname, idnumber, email, city, country,
 * username. confirmed, crsrole, crscat, sysrole,
 * firstaccess, lastaccess, lastlogin, timemodified, auth
 * + any user_info_field.shortname custom profile fields
 * * filters displayed in the order of this array!
 *
 * ['advanced'] => array, DB user feilds which are advanced form elements.
 * ['notadvanced'] => array, DB user fields which are NOT advanced form elements
 * (use only one of keys 'advanced' or 'notadvanced')
 * ['langfile'] => string optional language file, default: 'filters'
 *   ['tables'] => optional array of tables as key, table alias as value
 * default values show below: array(
 *             'user' => 'u',
 *   'user_info_data' => 'uidata', // only required for extra profile fields
 * 'role_assignments' => 'ra' // only required for crsrole and sysrole
 * );
 *    ['extra'] => boolean : true includes all extra profile fields,
 *                 false (default) does not auto include them.
 *  ['heading'] => optional string (raw HTML) - NOT yet IMPLEMENTED
 *   ['footer'] => optional string (raw HTML) - NOT yet IMPLEMENTED
 *     ['help'] => optional array of arrays:
                   keys are user fields (above, or extra profile fields shortnames)
 *                 values are arrays to pass to setHelpButton($helpbuttonargs)
                   - not implemented in all sub-filters!
 *
 * (TBD: add layout options, columns/row designation ...)
 *
 * NOTES:
 * ======
 * 1. Since this is a compund filter it must be used a bit different then
 *    standard filters.
 *
 * 2. Call constructor method directly _NOT_ using:
 *        new generalized_filter_entry( ... ) - INCORRECT!
 *    Instead call:
 *        new generalized_filter_curriculumclass( ... ) - CORRECT!
 *
 * 3. Class constructor and get_filters() methods return an array!
 *    Therefore, do _NOT_ put return inside another array in your report's
 *    get_filters() method.
 *
 * E.g.
 * // CORRECT
 * class some_user_report extends table_report {
 *     ...
 *     function get_filters() {
 *         $userprofilefilters = new generalized_filter_curriculumclass( ... );
 *         return array_merge(
 *             $userprofilefilters->get_filters(),
 *             array(
 *                 new generalized_filter_entry( ... ),
 *                 new generalized_filter_entry( ... ) [, ...]
 *             )
 *         );
 *     }
 *     ...
 * }
 *
 * // INCORRECT
 * class some_user_report extends table_report {
 *     ...
 *     function get_filters() {
 *         return(
 *             array(
 *                 new generalized_filter_curriculumclass( ... ),
 *                 new generalized_filter_entry( ... ),
 *                 new generalized_filter_entry( ... ) [, ...]
               )
 *         );
 *     }
 *     ...
 * }
 *
 * // CORRECT: Report case with only the User Profile filters
 * class some_user_report extends table_report {
 *     ...
 *     function get_filters() {
 *         $userprofilefilters = new generalized_filter_curriculumclass( ... );
 *         return $userprofilefilters->get_filters();
 *     }
 *     ...
 * }
 *
 * @author Brent Boghosian <brent.boghosian@remote-learner.net>
 * @version $Id$
 * @package elis/program/lib/filtering
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 */

require_once($CFG->dirroot .'/user/filters/lib.php'); // TBD
require_once($CFG->dirroot .'/elis/core/lib/filtering/multifilter.php');
require_once($CFG->dirroot .'/elis/core/lib/filtering/exists_select.php');
require_once($CFG->dirroot .'/elis/core/lib/filtering/custom_field_text.php');
require_once($CFG->dirroot .'/elis/core/lib/filtering/custom_field_select.php');

/**
 * Generic userprofilematch filter class
 */
class generalized_filter_curriculumclass extends generalized_filter_multifilter  {

    /**
     * Class contants: Additional required sub-filter types
     */
    const filtertype_customtext   = 'custom_field_text';
    const filtertype_customselect = 'custom_field_select';
    const filtertype_existselect  = 'exists_select';

    /**
     * Class properties
     */
    protected $_uniqueid;
    protected $_label;
    protected $_heading;
    protected $_footer;

    protected $languagefile = 'elis_program';


    // Array $fieldtofiltermap maps fields to filter type
    protected $fieldtofiltermap = array(
        'curriculum' => array(
            'id'                => generalized_filter_curriculumclass::filtertypetext,
            'idnumber'          => generalized_filter_curriculumclass::filtertypetext,
            'name'              => generalized_filter_curriculumclass::filtertypeselect,
            'description'       => generalized_filter_curriculumclass::filtertypetext,
            'reqcredits'        => generalized_filter_curriculumclass::filtertypetext,
            'iscustom'          => generalized_filter_curriculumclass::filtertypetristate,
            'timecreated'       => generalized_filter_curriculumclass::filtertypedate,
            'timemodified'      => generalized_filter_curriculumclass::filtertypedate,
            'timetocomplete'    => generalized_filter_curriculumclass::filtertypedate,
            'priority'          => generalized_filter_curriculumclass::filtertypetext,
        ),
        'course' => array(
            'id'                => generalized_filter_curriculumclass::filtertypetext,
            'name'              => generalized_filter_curriculumclass::filtertypeselect,
            'code'              => generalized_filter_curriculumclass::filtertypetext,
            'idnumber'          => generalized_filter_curriculumclass::filtertypetext,
            'syllabus'          => generalized_filter_curriculumclass::filtertypetext,
            'lengthdescription' => generalized_filter_curriculumclass::filtertypetext,
            'length'            => generalized_filter_curriculumclass::filtertypetext,
            'credits'           => generalized_filter_curriculumclass::filtertypetext,
            'completion_grade'  => generalized_filter_curriculumclass::filtertypetext,
            'environmentid'     => generalized_filter_curriculumclass::filtertypeselect,
            'cost'              => generalized_filter_curriculumclass::filtertypetext,
            'timecreated'       => generalized_filter_curriculumclass::filtertypedate,
            'timemodified'      => generalized_filter_curriculumclass::filtertypedate,
            'version'           => generalized_filter_curriculumclass::filtertypetext,
            'courseid'          => generalized_filter_curriculumclass::filtertypetext,
        ),
        'class' => array(
            'id'                => generalized_filter_curriculumclass::filtertypetext,
            'courseid'          => generalized_filter_curriculumclass::filtertypetext,
            'idnumber'          => generalized_filter_curriculumclass::filtertypeselect,
            'startdate'         => generalized_filter_curriculumclass::filtertypedate, //TBD
            'enddate'           => generalized_filter_curriculumclass::filtertypedate,
            'environmentid'     => generalized_filter_curriculumclass::filtertype_existselect,
            'completestatusid'  => generalized_filter_curriculumclass::filtertypeselect,
        ),
    );

    // Array $defaultlabels are default user profile field labels
    // - maybe overridden in $options array
    protected $labels = array(
        'curriculum' => array(
            'idnumber'          => 'fld_idnumber',
            'name'              => 'fld_curriculumname',
            'description'       => 'fld_description',
            'reqcredits'        => 'fld_reqcredits',
            'iscustom'          => 'fld_iscustom',
            'timecreated'       => 'fld_timecreated',
            'timemodified'      => 'fld_timemodified',
            'timetocomplete'    => 'fld_timetocomplete',
            'priority'          => 'fld_priority',
        ),
        'course' => array(
            'name'              => 'fld_coursename',
            'code'              => 'fld_code',
            'idnumber'          => 'fld_idnumber',
            'syllabus'          => 'fld_syllabus',
            'lengthdescription' => 'fld_lengthdescription',
            'length'            => 'fld_length',
            'credits'           => 'fld_credits',
            'completion_grade'  => 'fld_completion_grade',
            'environmentid'     => 'fld_environmentid',
            'cost'              => 'fld_cost',
            'timecreated'       => 'fld_timecreated',
            'timemodified'      => 'fld_timemodified',
            'version'           => 'fld_version',
        ),
        'class' => array(
            'idnumber'          => 'fld_idnumber',
            'startdate'         => 'fld_startdate',
            'enddate'           => 'fld_enddate',
            //'environmentid'     => 'fld_environmentid',
            // Environment changed to custom field in ELIS 2.x
        ),
    );

    protected $tables = array(
        'curriculum' => array(
            'crlm_curriculum' => 'cur',
            'crlm_curriculum_assignment' => 'cura',
        ),
        'course' => array(
            'crlm_course' => 'cou',
        ),
        'class' => array(
            'crlm_class' => 'cls',
         ),
    );

    protected $sections = array(
        'curriculum' => array('name' => 'curriculum'),
        'course'     => array('name' => 'course'),
        'class'      => array('name' => 'class')
    );

    // This is the field to be returned by the custom profile fields.
    protected $_innerfield = array(
        'curriculum' => 'cca.userid',
        'course'     => 'cls.id',
        'class'      => 'cls.id',
    );

    // This is the field to compare the custom profile field against.
    protected $_outerfield = array(
        'curriculum' => 'u.id',
        'course'     => 'cls.id',
        'class'      => 'cls.id',
    );


    /**
     * Constructor
     *
     * @param string $uniqueid Unique prefix for filters
     * @param string $label    Filter label
     * @param array  $options  Filter options (see above)
     * @return array The sub-filters
     * @uses $PAGE
     */
    function generalized_filter_curriculumclass($uniqueid, $label, $options = array()) {
        global $PAGE;

        parent::__construct($uniqueid, $label, $options);

        $this->_fields = array();

        $PAGE->requires->js('/elis/core/js/dependentselect.js');

        if (empty($options['help'])) {
            $options['help'] = array();
        }

        // Get table aliases
        if (empty($options['tables'])) {
            $options['tables'] = array();
        }

        $allfields = array();

        foreach ($this->labels as $group => $labels) {
            foreach ($labels as $key => $val) {
                $this->record_short_field_name($group .'-'. $key);
            }
        }

        // Check for & assign table aliases
        foreach ($this->tables as $group => $tables) {
            if (!array_key_exists($group, $options['tables'])) {
                continue;
            }

            foreach ($tables as $key => $val) {
                if (!empty($options['tables'][$group][$key])) {
                    // use defaults table aliases if not specified
                    $this->tables[$group][$key] = $options['tables'][$group][$key];
                }
            }
        }

        foreach ($this->sections as $group => $section) {
            $ctxtlvl = context_elis_helper::get_level_from_name($section['name']);

            $this->sections[$group]['contextlevel'] = $ctxtlvl;

            // Add custom fields to array
            $extrafields = field::get_for_context_level($ctxtlvl);
            $this->get_custom_fields($group, $extrafields);
        }

        // Force $options['choices'] to be an associative array
        foreach ($options['choices'] as $key => $choices) {
            if (!$this->is_assoc_array($choices)) {
                $options['choices'][$key] = array_fill_keys($choices, '');
            }
        }

        foreach ($options['choices'] as $group => $choices) {
            $allfields[$group] = array();
            foreach ($choices as $name => $alias) {
                $label = $name;
                if (!empty($alias) &&
                    get_string_manager()->string_exists($alias, $this->languagefile)) {
                    $label = get_string($alias, $this->languagefile);
                } else if (array_key_exists($name, $this->defaultlabels[$group])
                           && get_string_manager()->string_exists(
                                  $this->defaultlabels[$group][$name],
                                  $this->languagefile)) {
                    $label = get_string($this->defaultlabels[$group][$name], $this->languagefile);
                } else {
                    foreach ($this->sections as $section) {
                        if (array_key_exists($name, $section['custom'])) {
                            $label = $section['custom'][$name];
                        }
                    }
                }
                $allfields[$group][$name] = $label;
            }

            if (!empty($options['extra']) && !empty($this->sections[$group]['custom'])) {
                $allfields[$group] = array_merge($allfields[$group], $this->sections[$group]['custom']);
            }
        }

        foreach ($allfields as $group => $fields) {
            $this->_filters[$group] = array();

            foreach ($fields as $name => $label) {
                // must setup select choices for specific fields
                $myoptions = $this->make_filter_options($group, $name, $options['help'], $options['tables']);

                if (!is_array($myoptions)) {
                    continue;
                }

                $filterid = $this->_uniqueid . $group .'-'. substr($name, 0, MAX_FILTER_SUFFIX_LEN);
                $ftype = (string)$this->fieldtofiltermap[$group][$name];
                $advanced = (!empty($options['advanced'][$group]) &&
                             in_array($name, $options['advanced'][$group]))
                            || (!empty($options['notadvanced'][$group]) &&
                                !in_array($name, $options['notadvanced'][$group]));

                $this->_filters[$group][$name] =
                    new generalized_filter_entry($filterid, $myoptions['talias'],
                        $myoptions['dbfield'], $label, $advanced, $ftype, $myoptions);
            }
        }
    }

    /**
     * Make Custom Filter Options
     *
     * This function handles filters that require custom values (languages, countries, etc).
     *
     * @param array  $options The options array (so far)
     * @param string $group   The name of the group the option belongs to
     * @param string $name    The name of the option
     * @param array  $tables  An array of table names for lookups.
     * @return array The options array with customized settings
     * @uses $CFG
     * @uses $DB
     */
    function make_filter_options_custom($options, $group, $name) {
        global $CFG, $DB;

        switch ($group) {
            case 'curriculum':
                switch ($name) {
                    case 'name':
                        $options['choices'] = array();
                        $records = $DB->get_recordset('crlm_curriculum', null, 'name', 'id, name');
                        foreach ($records as $record) {
                            $options['choices'][$record->id] = $record->name;
                        }
                        unset($records);

                        $id    = $this->_uniqueid . $group .'-name';
                        $child = $this->_uniqueid .'course-name';
                        $path  = $CFG->wwwroot .'/elis/program/lib/filtering/helpers/courses.php';
                        $options['numeric'] = 1;
                        $options['talias'] = $this->tables[$group]['crlm_curriculum'];
                        $options['dbfield'] = 'id';
                        $options['onchange'] = "dependentselect_updateoptions('$id','$child','$path')";
                        $options['multiple'] = 'multiple';
                        break;

                    default:
                        break;
                }
                //curriculum customfield filter condition, linking users to to curricula based on
                //curriculum assignments and curriculum-course associations
                $options['wrapper'] = ' INNER JOIN {crlm_curriculum_assignment} cca
                                                ON c.instanceid = cca.curriculumid';

                //use EXISTS clause because we might need to connect based on several conditions
                $options['subqueryprefix'] = 'EXISTS';

                //just link based on user id (override with additional condition on
                //on curriculum id elsewhere if needed)
                $options['extraconditions'] = 'AND u.id = cca.userid';

                //tell the filter we're operating on the curriculum context level
                $options['contextlevel'] = CONTEXT_ELIS_PROGRAM;
                break;

            case 'course':
                switch ($name) {
                    case 'name':
                        $options['choices'] = array();
                        $records = $DB->get_recordset('crlm_course', null, 'name', 'id, name');
                        foreach ($records as $record) {
                            $options['choices'][$record->id] = $record->name;
                        }
                        unset($records);

                        $id    = $this->_uniqueid . $group .'-name';
                        $child = $this->_uniqueid .'class-idnumber';
                        $path  = $CFG->wwwroot .'/elis/program/lib/filtering/helpers/classes.php';
                        $options['numeric'] = 1;
                        $options['talias'] = $this->tables['class']['crlm_class'];
                        $options['dbfield'] = 'courseid';
                        $options['onchange'] = "dependentselect_updateoptions('$id','$child','$path')";
                        $options['multiple'] = 'multiple';
                        break;

                    default:
                        break;
                }
                $options['wrapper'] = ' INNER JOIN {crlm_class} cls
                                                ON c.instanceid = cls.courseid';
                break;

            case 'class':
                $options['wrapper'] = ' INNER JOIN {crlm_class} cls
                                                ON c.instanceid = cls.id';
                switch ($name) {
                    case 'idnumber':
                        $options['choices'] = array();
                        $records = $DB->get_recordset('crlm_class', null, 'idnumber', 'id, idnumber');
                        foreach ($records as $record) {
                            $options['choices'][$record->id] = $record->idnumber;
                        }
                        unset($records);

                        $options['numeric']  = 1;
                        $options['talias']   = $this->tables[$group]['crlm_class'];
                        $options['dbfield']  = 'id';
                        $options['multiple'] = 'multiple';
                        break;

                    case 'environmentid':
                        $options['choices'] = array();
                        $records = $DB->get_recordset('crlm_environment', null, 'name', 'id, name');
                        foreach ($records as $record) {
                            $options['choices'][$record->id] = $record->name;
                        }
                        unset($records);

                        $options['numeric']    = 1;
                        $options['talias']     = $this->tables[$group]['crlm_class'];
                        $options['table']      = 'crlm_class';
                        $options['dbfield']    = 'environmentid';
                        $options['multiple']   = 'multiple';
                        $options['wrapper']    = '';
                        $options['outerfield'] = 'u.id';
                        break;

                    case 'startdate': // TBD: nofilterdate -> date
                        $options['talias']   = '';
                        $options['dbfield']  = '';
                        break;

                     default:
                        break;
                }
                $pos = strpos($name, 'customfield-');
                if ($pos !== false) {
                    //tell the filter we're operating on the class context level
                    $options['contextlevel'] = CONTEXT_ELIS_CLASS;
                }
                break;

            default:
                break;
        }

        $options['innerfield'] = $this->_innerfield[$group];
        $options['wrapper']   .= $this->_wrapper[$group];

        return $options;
    }
}

