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

require_once(elispm::lib('data/clusterassignment.class.php'));

/**
 * An action to assign a user to a program.
 */
class deepsight_action_programuser_assign extends deepsight_action_confirm {
    public $label = 'Assign User';
    public $icon = 'elisicon-assoc';

    /**
     * Constructor.
     * @param moodle_database $DB The active database connection.
     * @param string $name The unique name of the action to use.
     * @param string $descsingle The description when the confirmation is for a single element.
     * @param string $descmultiple The description when the confirmation is for the bulk list.
     */
    public function __construct(moodle_database &$DB, $name, $descsingle='', $descmultiple='') {
        parent::__construct($DB, $name);

        $langelements = new stdClass;
        $langelements->baseelement = strtolower(get_string('curriculum', 'elis_program'));
        $langelements->actionelement = strtolower(get_string('user', 'elis_program'));
        $this->descsingle = (!empty($descsingle))
                ? $descsingle : get_string('ds_action_assign_confirm', 'elis_program', $langelements);

        $langelements = new stdClass;
        $langelements->baseelement = strtolower(get_string('curriculum', 'elis_program'));
        $langelements->actionelement = strtolower(get_string('users', 'elis_program'));
        $this->descmultiple = (!empty($descmultiple))
                ? $descmultiple : get_string('ds_action_assign_confirm_multi', 'elis_program', $langelements);
    }

    /**
     * Assign the user to the program.
     * @param array $elements An array of elements to perform the action on.
     * @param bool $bulkaction Whether this is a bulk-action or not.
     * @return array An array to format as JSON and return to the Javascript.
     */
    protected function _respond_to_js(array $elements, $bulkaction) {
        global $DB;
        $pgmid = required_param('id', PARAM_INT);

        // Permissions.
        if (curriculumpage::can_enrol_into_curriculum($pgmid) !== true) {
            return array('result' => 'fail', 'msg' => get_string('not_permitted', 'elis_program'));
        }

        foreach ($elements as $userid => $label) {
            if ($this->can_assign($pgmid, $userid) === true) {
                $stucur = new curriculumstudent(array('userid' => $userid, 'curriculumid' => $pgmid));
                $stucur->save();
            }
        }

        return array('result' => 'success', 'msg'=>'Success');
    }

    /**
     * Determine whether the current user can assign the user to the program.
     * @param int $programid The ID of the program.
     * @param int $userid The ID of the user (the assignee).
     * @return bool Whether the current user has permission.
     */
    protected function can_assign($programid, $userid) {
        return curriculumstudent::can_manage_assoc($userid, $programid);
    }
}

/**
 * An action to unassign a user from a program.
 */
class deepsight_action_programuser_unassign extends deepsight_action_confirm {
    public $label = 'Unassign User';
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
        $langelements->actionelement = strtolower(get_string('user', 'elis_program'));
        $this->descsingle = (!empty($descsingle))
                ? $descsingle : get_string('ds_action_unassign_confirm', 'elis_program', $langelements);

        $langelements = new stdClass;
        $langelements->baseelement = strtolower(get_string('curriculum', 'elis_program'));
        $langelements->actionelement = strtolower(get_string('users', 'elis_program'));
        $this->descmultiple = (!empty($descmultiple))
                ? $descmultiple : get_string('ds_action_unassign_confirm_multi', 'elis_program', $langelements);
    }

    /**
     * Unassign the user from the program.
     * @param array $elements An array of elements to perform the action on.
     * @param bool $bulkaction Whether this is a bulk-action or not.
     * @return array An array to format as JSON and return to the Javascript.
     */
    protected function _respond_to_js(array $elements, $bulkaction) {
        global $DB;
        $pgmid = required_param('id', PARAM_INT);

        // Permissions.
        $cpage = new curriculumpage();
        if ($cpage->_has_capability('elis/program:program_view', $pgmid) !== true) {
            return array('result' => 'fail', 'msg' => get_string('not_permitted', 'elis_program'));
        }

        foreach ($elements as $userid => $label) {
            if ($this->can_unassign($pgmid, $userid) === true) {
                $assignrec = $DB->get_record(curriculumstudent::TABLE, array('userid' => $userid, 'curriculumid' => $pgmid));
                if (!empty($assignrec)) {
                    $curstu = new curriculumstudent($assignrec);
                    $curstu->delete();
                }
            }
        }

        return array('result' => 'success', 'msg'=>'Success');
    }

    /**
     * Determine whether the current user can unassign the user from the program.
     * @param int $programid The ID of the program.
     * @param int $userid The ID of the user (the assignee).
     * @return bool Whether the current user has permission.
     */
    protected function can_unassign($programid, $userid) {
        return curriculumstudent::can_manage_assoc($userid, $programid);
    }
}