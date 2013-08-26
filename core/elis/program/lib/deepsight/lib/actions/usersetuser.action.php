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

require_once(elis::plugin_file('usersetenrol_manual', 'lib.php'));

/**
 * An action to assign users to a userset.
 */
class deepsight_action_usersetuser_assign extends deepsight_action_confirm {
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
        $langelements->baseelement = strtolower(get_string('cluster', 'elis_program'));
        $langelements->actionelement = strtolower(get_string('user', 'elis_program'));
        $this->descsingle = (!empty($descsingle))
                ? $descsingle : get_string('ds_action_assign_confirm', 'elis_program', $langelements);

        $langelements = new stdClass;
        $langelements->baseelement = strtolower(get_string('cluster', 'elis_program'));
        $langelements->actionelement = strtolower(get_string('users', 'elis_program'));
        $this->descmultiple = (!empty($descmultiple))
                ? $descmultiple : get_string('ds_action_assign_confirm_multi', 'elis_program', $langelements);
    }

    /**
     * Assign the users to the userset.
     *
     * @param array $elements An array of user information to assign to the userset.
     * @param bool $bulkaction Whether this is a bulk-action or not.
     * @return array An array to format as JSON and return to the Javascript.
     */
    protected function _respond_to_js(array $elements, $bulkaction) {
        global $DB;
        $usersetid = required_param('id', PARAM_INT);
        $userset = new userset($usersetid);

        // Permissions.
        if (usersetpage::can_enrol_into_cluster($userset->id) !== true) {
            return array('result' => 'fail', 'msg' => get_string('not_permitted', 'elis_program'));
        }

        foreach ($elements as $userid => $label) {
            if ($this->can_assign($userset->id, $userid) === true) {
                cluster_manual_assign_user($userset->id, $userid);
            }
        }

        return array('result' => 'success', 'msg'=>'Success');
    }

    /**
     * Determine whether the current user can assign the given user to the given userset.
     *
     * @param int $usersetid The ID of the userset.
     * @param int $userid The ID of the user.
     * @return bool Whether the user can assign (true) or not (false)
     */
    protected function can_assign($usersetid, $userid) {
        return clusteruserpage::can_manage_assoc($userid, $usersetid);
    }
}

/**
 * An action to unassign a user from a userset.
 */
class deepsight_action_usersetuser_unassign extends deepsight_action_confirm {
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
        $langelements->baseelement = strtolower(get_string('cluster', 'elis_program'));
        $langelements->actionelement = strtolower(get_string('user', 'elis_program'));
        $this->descsingle = (!empty($descsingle))
                ? $descsingle : get_string('ds_action_unassign_confirm', 'elis_program', $langelements);

        $langelements = new stdClass;
        $langelements->baseelement = strtolower(get_string('cluster', 'elis_program'));
        $langelements->actionelement = strtolower(get_string('users', 'elis_program'));
        $this->descmultiple = (!empty($descmultiple))
                ? $descmultiple : get_string('ds_action_unassign_confirm_multi', 'elis_program', $langelements);
    }

    /**
     * Unassign the user from the userset.
     *
     * @param array $elements An array of elements to perform the action on.
     * @param bool $bulkaction Whether this is a bulk-action or not.
     * @return array An array to format as JSON and return to the Javascript.
     */
    protected function _respond_to_js(array $elements, $bulkaction) {
        global $DB;
        $usersetid = required_param('id', PARAM_INT);

        // Permissions.
        $uspage = new usersetpage();
        if ($uspage->_has_capability('elis/program:userset_view', $usersetid) !== true) {
            return array('result' => 'fail', 'msg' => get_string('not_permitted', 'elis_program'));
        }

        foreach ($elements as $userid => $label) {
            if ($this->can_unassign($usersetid, $userid)) {
                $assignrec = $DB->get_record(clusterassignment::TABLE, array('userid' => $userid, 'clusterid' => $usersetid));
                if (!empty($assignrec) && $assignrec->plugin === 'manual') {
                    $curstu = new clusterassignment($assignrec);
                    $curstu->delete();
                }
            }
        }

        return array('result' => 'success', 'msg'=>'Success');
    }

    /**
     * Determine whether the current user can unassign the user from the userset.
     *
     * @param int $usersetid The ID of the userset.
     * @param int $userid The ID of the user (the assignee).
     * @return bool Whether the current can unassign (true) or not (false)
     */
    protected function can_unassign($usersetid, $userid) {
        return clusteruserpage::can_manage_assoc($userid, $usersetid);
    }
}