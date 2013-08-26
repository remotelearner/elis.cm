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
 * An action to assign tracks to a user.
 */
class deepsight_action_usertrack_assign extends deepsight_action_confirm {
    public $label = 'Assign';
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
        $langelements->baseelement = strtolower(get_string('user', 'elis_program'));
        $langelements->actionelement = strtolower(get_string('track', 'elis_program'));
        $this->descsingle = (!empty($descsingle))
                ? $descsingle : get_string('ds_action_assign_confirm', 'elis_program', $langelements);

        $langelements = new stdClass;
        $langelements->baseelement = strtolower(get_string('user', 'elis_program'));
        $langelements->actionelement = strtolower(get_string('tracks', 'elis_program'));
        $this->descmultiple = (!empty($descmultiple))
                ? $descmultiple : get_string('ds_action_assign_confirm_multi', 'elis_program', $langelements);
    }

    /**
     * Assign the tracks to the user.
     * @param array $elements An array containing information on tracks to assign to the user.
     * @param bool $bulkaction Whether this is a bulk-action or not.
     * @return array An array to format as JSON and return to the Javascript.
     */
    protected function _respond_to_js(array $elements, $bulkaction) {
        global $DB;
        $userid = required_param('id', PARAM_INT);
        $user = new user($userid);

        foreach ($elements as $trackid => $label) {
            if ($this->can_assign($user->id, $trackid) === true) {
                usertrack::enrol($user->id, $trackid);
            }
        }

        return array('result' => 'success', 'msg'=>'Success');
    }

    /**
     * Determine whether the current user can assign the given track to the given user.
     * @param int $userid The ID of the user (the assignee).
     * @param int $trkid The ID of the track.
     * @return bool Whether the user can assign (true) or not (false)
     */
    protected function can_assign($userid, $trkid) {
        return usertrack::can_manage_assoc($userid, $trkid);
    }
}

/**
 * An action to unassign tracks from a user.
 */
class deepsight_action_usertrack_unassign extends deepsight_action_confirm {
    public $label = 'Unassign';
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
        $langelements->baseelement = strtolower(get_string('user', 'elis_program'));
        $langelements->actionelement = strtolower(get_string('track', 'elis_program'));
        $this->descsingle = (!empty($descsingle))
                ? $descsingle : get_string('ds_action_unassign_confirm', 'elis_program', $langelements);

        $langelements = new stdClass;
        $langelements->baseelement = strtolower(get_string('user', 'elis_program'));
        $langelements->actionelement = strtolower(get_string('tracks', 'elis_program'));
        $this->descmultiple = (!empty($descmultiple))
                ? $descmultiple : get_string('ds_action_unassign_confirm_multi', 'elis_program', $langelements);
    }

    /**
     * Unassign the tracks from the user.
     * @param array $elements An array containing information on tracks to unassign from the user.
     * @param bool $bulkaction Whether this is a bulk-action or not.
     * @return array An array to format as JSON and return to the Javascript.
     */
    protected function _respond_to_js(array $elements, $bulkaction) {
        global $DB;
        $userid = required_param('id', PARAM_INT);

        foreach ($elements as $trackid => $label) {
            if ($this->can_unassign($userid, $trackid) === true) {
                $assignrec = $DB->get_record(usertrack::TABLE, array('userid' => $userid, 'trackid' => $trackid));
                $usertrack = new usertrack($assignrec);
                $usertrack->delete();
            }
        }

        return array('result' => 'success', 'msg'=>'Success');
    }

    /**
     * Determine whether the current user can unassign the user from the track.
     * @param int $userid The ID of the user (the assignee).
     * @param int $trackid The ID of the track.
     * @return bool Whether the current can unassign (true) or not (false)
     */
    protected function can_unassign($userid, $trackid) {
        return usertrack::can_manage_assoc($userid, $trackid);
    }
}