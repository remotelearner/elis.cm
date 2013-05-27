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

/**
 * Base class to manage program - course associations.
 */
abstract class deepsight_action_programcourse_assignedit extends deepsight_action_standard {
    /**
     * The javascript class to use.
     */
    const TYPE = 'programcourse_assignedit';

    /**
     * @var string The label for the action.
     */
    public $label = 'Assign';

    /**
     * @var string The icon for the action.
     */
    public $icon = 'elisicon-assoc';

    /**
     * @var string Mode indicating to the javascript how to operate.
     */
    public $mode = 'assign';

    /**
     * Provide options to the javascript.
     * @return array An array of options.
     */
    public function get_js_opts() {
        global $CFG;
        $opts = parent::get_js_opts();
        $opts['condition'] = $this->condition;
        $opts['opts']['actionurl'] = $this->endpoint;
        $opts['opts']['mode'] = $this->mode;
        $opts['opts']['langbulkconfirm'] = get_string('ds_bulk_confirm', 'elis_program');
        $opts['opts']['langworking'] = get_string('ds_working', 'elis_program');
        $opts['opts']['langyes'] = get_string('yes', 'moodle');
        $opts['opts']['langno'] = get_string('no', 'moodle');
        $opts['opts']['langchanges'] = get_string('ds_changes', 'elis_program');
        $opts['opts']['langnochanges'] = get_string('ds_nochanges', 'elis_program');
        $opts['opts']['langreq'] = get_string('curriculumcourseform:required', 'elis_program');
        $opts['opts']['langnonreq'] = get_string('curriculumcourseform:notrequired', 'elis_program');
        $opts['opts']['langtimeperiodyear'] = get_string('time_period_year', 'elis_program');
        $opts['opts']['langtimeperiodmonth'] = get_string('time_period_month', 'elis_program');
        $opts['opts']['langtimeperiodweek'] = get_string('time_period_week', 'elis_program');
        $opts['opts']['langtimeperiodday'] = get_string('time_period_day', 'elis_program');
        $opts['opts']['langgeneralerror'] = get_string('ds_unknown_error', 'elis_program');
        $opts['opts']['langtitle'] = get_string('ds_assocdata', 'elis_program');
        $opts['opts']['langfrequency'] = get_string('curriculumcourseform:frequency', 'elis_program');
        $opts['opts']['langtimeperiod'] = get_string('curriculumcourseform:time_period', 'elis_program');
        $opts['opts']['langposition'] = get_string('curriculumcourseform:position', 'elis_program');
        return $opts;
    }

    /**
     * Process association data from the form.
     * @param string $assocdata JSON-formatted association data.
     * @param string $bulkaction Whether this is a bulk action or not.
     * @return array The formatted and cleaned association data.
     */
    protected function process_incoming_assoc_data($assocdata, $bulkaction) {
        $assocdata = @json_decode($assocdata, true);
        if (!is_array($assocdata)) {
            return array();
        }
        if ($bulkaction === true && $this->mode === 'edit') {
            $cleanedassoc = array();
        } else {
            $cleanedassoc = array(
                'required' => 0,
                'frequency' => 0,
                'timeperiod' => 'year',
                'position' => 0
            );
        }
        if (isset($assocdata['required']) && ($assocdata['required'] == 1 || $assocdata['required'] == 0)) {
            $cleanedassoc['required'] = $assocdata['required'];
        }
        if (isset($assocdata['frequency']) && is_numeric($assocdata['frequency'])) {
            $cleanedassoc['frequency'] = (int)$assocdata['frequency'];
        }
        if (isset($assocdata['timeperiod']) && in_array($assocdata['timeperiod'], array('year', 'month', 'week', 'day'), true)) {
            $cleanedassoc['timeperiod'] = $assocdata['timeperiod'];
        }
        if (isset($assocdata['position']) && is_numeric($assocdata['position'])) {
            $cleanedassoc['position'] = (int)$assocdata['position'];
        }
        return $cleanedassoc;
    }

    /**
     * Formats association data for display in the table post-edit.
     * @param array $assocdata The incoming association data
     * @return array The formatted association data.
     */
    protected function format_assocdata_for_display($assocdata) {
        if (isset($assocdata['required'])) {
            $assocdata['required'] = ($assocdata['required'] == 1) ? get_string('yes', 'moodle') : get_string('no', 'moodle');
        }
        if (isset($assocdata['timeperiod'])) {
            $timeperiodlang = array(
                'year' => get_string('time_period_year', 'elis_program'),
                'month' => get_string('time_period_month', 'elis_program'),
                'week' => get_string('time_period_week', 'elis_program'),
                'day' => get_string('time_period_day', 'elis_program')
            );
            $assocdata['timeperiod'] = $timeperiodlang[$assocdata['timeperiod']];
        }
        return $assocdata;
    }

    /**
     * Determine whether the current user can manage the program - course association.
     * @param int $programid The ID of the program.
     * @param int $courseid The ID of the course.
     * @return bool Whether the current can manage (true) or not (false)
     */
    protected function can_manage_assoc($programid, $courseid) {
        global $USER;
        $perm = 'elis/program:associate';
        $programassocctx = pm_context_set::for_user_with_capability('curriculum', $perm, $USER->id);
        $programassociateallowed = ($programassocctx->context_allowed($programid, 'curriculum') === true) ? true : false;
        $courseassocctx = pm_context_set::for_user_with_capability('course', $perm, $USER->id);
        $courseassociateallowed = ($courseassocctx->context_allowed($courseid, 'course') === true) ? true : false;
        return ($programassociateallowed === true && $courseassociateallowed === true) ? true : false;
    }
}

/**
 * Action to assign courses to a program.
 */
class deepsight_action_programcourse_assign extends deepsight_action_programcourse_assignedit {
    /**
     * @var string The label for the action.
     */
    public $label = 'Assign';

    /**
     * @var string The icon for the action.
     */
    public $icon = 'elisicon-assoc';

    /**
     * @var string Mode indicating to the javascript how to operate.
     */
    public $mode = 'assign';

    /**
     * Constructor.
     * @param moodle_database $DB The active database connection.
     * @param string $name The unique name of the action to use.
     */
    public function __construct(moodle_database &$DB, $name) {
        parent::__construct($DB, $name);
        $this->label = ucwords(get_string('assign', 'elis_program'));
    }

    /**
     * Edit program - course associations.
     * @param array $elements An array of course information to edit.
     * @param bool $bulkaction Whether this is a bulk-action or not.
     * @return array An array to format as JSON and return to the Javascript.
     */
    protected function _respond_to_js(array $elements, $bulkaction) {
        global $DB;
        $programid = required_param('id', PARAM_INT);
        $assocdata = required_param('assocdata', PARAM_CLEAN);
        $assocdata = $this->process_incoming_assoc_data($assocdata, $bulkaction);
        if (!is_array($assocdata)) {
            throw new Exception('Did not receive valid enrolment data.');
        }
        if (!empty($assocdata)) {
            foreach ($elements as $courseid => $label) {
                if ($this->can_manage_assoc($programid, $courseid) === true) {
                    $curriculumcourse = new curriculumcourse(array('curriculumid' => $programid, 'courseid' => $courseid));
                    $fields = array('required', 'frequency', 'timeperiod', 'position');
                    foreach ($fields as $field) {
                        if (isset($assocdata[$field])) {
                            $curriculumcourse->$field = $assocdata[$field];
                        }
                    }
                    $curriculumcourse->save();
                }
            }
        }
        $formatteddata = $this->format_assocdata_for_display($assocdata);
        return array(
            'result' => 'success',
            'msg' => 'Success',
            'displaydata' => $formatteddata,
            'saveddata' => $assocdata
        );
    }
}

/**
 * Edit the program - course assignment.
 */
class deepsight_action_programcourse_edit extends deepsight_action_programcourse_assignedit {
    /**
     * @var string The label for the action.
     */
    public $label = 'Edit';

    /**
     * @var string The icon for the action.
     */
    public $icon = 'elisicon-edit';

    /**
     * @var string Mode indicating to the javascript how to operate.
     */
    public $mode = 'edit';

    /**
     * Constructor.
     * @param moodle_database $DB The active database connection.
     * @param string $name The unique name of the action to use.
     */
    public function __construct(moodle_database &$DB, $name) {
        parent::__construct($DB, $name);
        $this->label = ucwords(get_string('edit', 'elis_program'));
    }

    /**
     * Edit program - course associations.
     * @param array $elements An array of course information to edit.
     * @param bool $bulkaction Whether this is a bulk-action or not.
     * @return array An array to format as JSON and return to the Javascript.
     */
    protected function _respond_to_js(array $elements, $bulkaction) {
        global $DB;
        $programid = required_param('id', PARAM_INT);
        $assocdata = required_param('assocdata', PARAM_CLEAN);
        $assocdata = $this->process_incoming_assoc_data($assocdata, $bulkaction);
        if (!is_array($assocdata)) {
            throw new Exception('Did not receive valid enrolment data.');
        }
        if (!empty($assocdata)) {
            foreach ($elements as $courseid => $label) {
                if ($this->can_manage_assoc($programid, $courseid) === true) {
                    $assoc = $DB->get_record(curriculumcourse::TABLE, array('curriculumid' => $programid, 'courseid' => $courseid));
                    if (!empty($assoc)) {
                        $curriculumcourse = new curriculumcourse($assoc);
                        $fields = array('required', 'frequency', 'timeperiod', 'position');
                        foreach ($fields as $field) {
                            if (isset($assocdata[$field])) {
                                $curriculumcourse->$field = $assocdata[$field];
                            }
                        }
                        $curriculumcourse->save();
                    }
                }
            }
        }
        $formatteddata = $this->format_assocdata_for_display($assocdata);
        return array(
            'result' => 'success',
            'msg' => 'Success',
            'displaydata' => $formatteddata,
            'saveddata' => $assocdata
        );
    }
}

/**
 * Provide a link to manage prerequisites.
 */
class deepsight_action_programcourse_prereqlink extends deepsight_action_link {
    /**
     * @var string The label for the action.
     */
    public $label = 'Enrol User';

    /**
     * @var string The icon for the action.
     */
    public $icon = 'elisicon-prereq';

    /**
     * @var string The link target (without query string)
     */
    public $baseurl = '/elis/program/index.php';

    /**
     * @var array Query parameters for the link target
     */
    public $params = array(
        'id' => '{curcrs_curriculumid}',
        's' => 'currcrs',
        'action' => 'prereqedit',
        'association_id' => '{curcrs_id}'
    );
}

/**
 * Provide a link to manage corequisites.
 */
class deepsight_action_programcourse_coreqlink extends deepsight_action_link {
    /**
     * @var string The label for the action.
     */
    public $label = 'Enrol User';

    /**
     * @var string The icon for the action.
     */
    public $icon = 'elisicon-coreq';

    /**
     * @var string The link target (without query string)
     */
    public $baseurl = '/elis/program/index.php';

    /**
     * @var array Query parameters for the link target
     */
    public $params = array(
        'id' => '{curcrs_curriculumid}',
        's' => 'currcrs',
        'action' => 'coreqedit',
        'association_id' => '{curcrs_id}'
    );
}

/**
 * An action to unassign courses from a program.
 */
class deepsight_action_programcourse_unassign extends deepsight_action_confirm {
    /**
     * @var string The label for the action.
     */
    public $label = 'Unassign';

    /**
     * @var string The icon for the action.
     */
    public $icon = 'elisicon-unassoc';

    /**
     * Constructor.
     * @param moodle_database $DB The active database connection.
     * @param string $name The unique name of the action to use.
     * @param string $descsingle The description when the confirmation is for a single element.
     * @param string $descmultiple The description when the confirmation is for the bulk list.
     */
    public function __construct(moodle_database &$DB, $name, $descsingle='', $descmultiple='') {
        parent::__construct($DB, $name);
        $this->label = ucwords(get_string('unassign', 'elis_program'));

        $langelements = new stdClass;
        $langelements->baseelement = strtolower(get_string('curriculum', 'elis_program'));
        $langelements->actionelement = strtolower(get_string('course', 'elis_program'));
        $this->descsingle = (!empty($descsingle))
                ? $descsingle : get_string('ds_action_unassign_confirm', 'elis_program', $langelements);

        $langelements = new stdClass;
        $langelements->baseelement = strtolower(get_string('curriculum', 'elis_program'));
        $langelements->actionelement = strtolower(get_string('courses', 'elis_program'));
        $this->descmultiple = (!empty($descmultiple))
                ? $descmultiple : get_string('ds_action_unassign_confirm_multi', 'elis_program', $langelements);
    }

    /**
     * Unassign the courses from the program.
     * @param array $elements An array of course information to unassign from the program.
     * @param bool $bulkaction Whether this is a bulk-action or not.
     * @return array An array to format as JSON and return to the Javascript.
     */
    protected function _respond_to_js(array $elements, $bulkaction) {
        global $DB;
        $programid = required_param('id', PARAM_INT);
        foreach ($elements as $courseid => $label) {
            if ($this->can_unassign($programid, $courseid) === true) {
                $assignrec = $DB->get_record(curriculumcourse::TABLE, array('curriculumid' => $programid, 'courseid' => $courseid));
                $curriculumcourse = new curriculumcourse($assignrec);
                $curriculumcourse->delete();
            }
        }
        return array('result' => 'success', 'msg'=>'Success');
    }

    /**
     * Determine whether the current user can unassign the course from the program.
     * @param int $programid The ID of the program.
     * @param int $courseid The ID of the course.
     * @return bool Whether the current can unassign (true) or not (false)
     */
    protected function can_unassign($programid, $courseid) {
        global $USER;
        $perm = 'elis/program:associate';
        $programassocctx = pm_context_set::for_user_with_capability('curriculum', $perm, $USER->id);
        $programassociateallowed = ($programassocctx->context_allowed($programid, 'curriculum') === true) ? true : false;
        $courseassocctx = pm_context_set::for_user_with_capability('course', $perm, $USER->id);
        $courseassociateallowed = ($courseassocctx->context_allowed($courseid, 'course') === true) ? true : false;
        return ($programassociateallowed === true && $courseassociateallowed === true) ? true : false;
    }
}