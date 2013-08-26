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
 * Action to assign programs to a course.
 */
class deepsight_action_courseprogram_assign extends deepsight_action_programcourse_assignedit {
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
     * Edit course - program associations.
     * @param array $elements An array of program information to edit.
     * @param bool $bulkaction Whether this is a bulk-action or not.
     * @return array An array to format as JSON and return to the Javascript.
     */
    protected function _respond_to_js(array $elements, $bulkaction) {
        global $DB;
        $courseid = required_param('id', PARAM_INT);
        $assocdata = required_param('assocdata', PARAM_CLEAN);
        $assocdata = $this->process_incoming_assoc_data($assocdata, $bulkaction);
        if (!is_array($assocdata)) {
            throw new Exception('Did not receive valid enrolment data.');
        }
        if (!empty($assocdata)) {
            foreach ($elements as $programid => $label) {
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
 * Edit the course - program assignment.
 */
class deepsight_action_courseprogram_edit extends deepsight_action_programcourse_assignedit {
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
        $courseid = required_param('id', PARAM_INT);
        $assocdata = required_param('assocdata', PARAM_CLEAN);
        $assocdata = $this->process_incoming_assoc_data($assocdata, $bulkaction);
        if (!is_array($assocdata)) {
            throw new Exception('Did not receive valid enrolment data.');
        }
        if (!empty($assocdata)) {
            foreach ($elements as $programid => $label) {
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
 * An action to unassign programs from a course.
 */
class deepsight_action_courseprogram_unassign extends deepsight_action_confirm {
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
        $langelements->baseelement = strtolower(get_string('course', 'elis_program'));
        $langelements->actionelement = strtolower(get_string('curriculum', 'elis_program'));
        $this->descsingle = (!empty($descsingle))
                ? $descsingle : get_string('ds_action_unassign_confirm', 'elis_program', $langelements);

        $langelements = new stdClass;
        $langelements->baseelement = strtolower(get_string('course', 'elis_program'));
        $langelements->actionelement = strtolower(get_string('curricula', 'elis_program'));
        $this->descmultiple = (!empty($descmultiple))
                ? $descmultiple : get_string('ds_action_unassign_confirm_multi', 'elis_program', $langelements);
    }

    /**
     * Unassign the programs from the course.
     * @param array $elements An array of program information to unassign from the course.
     * @param bool $bulkaction Whether this is a bulk-action or not.
     * @return array An array to format as JSON and return to the Javascript.
     */
    protected function _respond_to_js(array $elements, $bulkaction) {
        global $DB;
        $courseid = required_param('id', PARAM_INT);
        foreach ($elements as $programid => $label) {
            if ($this->can_unassign($courseid, $programid) === true) {
                $assignrec = $DB->get_record(curriculumcourse::TABLE, array('curriculumid' => $programid, 'courseid' => $courseid));
                $curriculumcourse = new curriculumcourse($assignrec);
                $curriculumcourse->delete();
            }
        }
        return array('result' => 'success', 'msg'=>'Success');
    }

    /**
     * Determine whether the current user can unassign the program from the course.
     * @param int $courseid The ID of the course.
     * @param int $programid The ID of the program.
     * @return bool Whether the current can unassign (true) or not (false)
     */
    protected function can_unassign($courseid, $programid) {
        global $USER;
        $perm = 'elis/program:associate';
        $programassocctx = pm_context_set::for_user_with_capability('curriculum', $perm, $USER->id);
        $programassociateallowed = ($programassocctx->context_allowed($programid, 'curriculum') === true) ? true : false;
        $courseassocctx = pm_context_set::for_user_with_capability('course', $perm, $USER->id);
        $courseassociateallowed = ($courseassocctx->context_allowed($courseid, 'course') === true) ? true : false;
        return ($programassociateallowed === true && $courseassociateallowed === true) ? true : false;
    }
}