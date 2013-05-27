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
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') or die();

require_once(elispm::lib('lib.php'));
require_once(elispm::lib('deepsightpage.class.php'));
require_once(elispm::lib('associationpage.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/userset.class.php'));
require_once(elispm::lib('data/clusterassignment.class.php'));
require_once(elispm::file('userpage.class.php'));
require_once(elispm::file('usersetpage.class.php'));
require_once(elis::plugin_file('usersetenrol_manual', 'lib.php'));
require_once(elis::plugin_file('usersetenrol_manual', 'usersetassignment_form.php'));
require_once(elis::plugin_file('usersetenrol_manual', 'selectpage.class.php'));

/**
 * A base user-userset association implementation for code shared between userclusterpage and clusteruserpage.
 */
abstract class userclusterbase extends deepsightpage {
    public $section = 'users';
    public $data_class = 'clusterassignment';
    public $parent_page;
    public $context;

    /**
     * Determine whether the current can manage the association between a given user and userset.
     * @param int $userid The ID of a user.
     * @param int $clustid The ID of a userset.
     * @return bool Success status.
     */
    public static function can_manage_assoc($userid, $usersetid) {
        global $USER;

        $allowedusersets = array();

        // TODO: Ugly, this needs to be overhauled.
        $upage = new usersetpage();

        if (!usersetpage::can_enrol_into_cluster($usersetid)) {
            // The users who satisfty this condition are a superset of those who can manage associations.
            return false;
        } else if ($upage->_has_capability('elis/program:userset_enrol', $usersetid)) {
            // Current user has the direct capability.
            return true;
        }

        $allowedusersets = userset::get_allowed_clusters($usersetid);

        $filter = array(new field_filter('userid', $userid));

        // Query to get users associated to at least one enabling userset.
        if (empty($allowedusersets)) {
            $filter[] = new select_filter('FALSE');
        } else {
            $filter[] = new in_list_filter('clusterid', $allowedusersets);
        }

        // User just needs to be in one of the possible usersets.
        if (clusterassignment::exists($filter)) {
            return true;
        }

        return false;
    }
}

/**
 * A deepsight page for managing user - userset associations (one user, multiple usersets)
 */
class userclusterpage extends userclusterbase {
    public $pagename = 'usrclst';
    public $tab_page = 'userpage';

    /**
     * Constructor
     * @param array $params An array of parameters for the page.
     */
    public function __construct(array $params = null) {
        $this->context = parent::_get_page_context();
        parent::__construct($params);
    }

    /**
     * Get the context of the current user.
     *
     * @return context_elis_user The current user context object.
     */
    protected function get_context() {
        if (!isset($this->context)) {
            $id = required_param('id', PARAM_INT);
            $this->context = context_elis_user::instance($id);
        }
        return $this->context;
    }

    /**
     * Construct the assigned datatable.
     *
     * @param string $uniqid A unique ID to assign to the datatable object.
     * @return deepsight_datatable The datatable object.
     */
    protected function construct_assigned_table($uniqid = null) {
        global $DB;
        $userid = $this->required_param('id', PARAM_INT);
        $endpoint = qualified_me().'&action=deepsight_response&tabletype=assigned&id='.$userid;
        $table = new deepsight_datatable_useruserset_assigned($DB, 'assigned', $endpoint, $uniqid);
        $table->set_userid($userid);
        return $table;
    }

    /**
     * Construct the unassigned datatable.
     *
     * @param string $uniqid A unique ID to assign to the datatable object.
     * @return deepsight_datatable The datatable object.
     */
    protected function construct_unassigned_table($uniqid = null) {
        global $DB;
        $userid = $this->required_param('id', PARAM_INT);
        $endpoint = qualified_me().'&action=deepsight_response&tabletype=unassigned&id='.$userid;
        $table = new deepsight_datatable_useruserset_available($DB, 'unassigned', $endpoint, $uniqid);
        $table->set_userid($userid);
        return $table;
    }

    /**
     * Assignment permission is handled at the action-object level.
     *
     * @return bool true
     */
    public function can_do_action_userusersetassign() {
        return true;
    }

    /**
     * Unassignment permission is handled at the action-object level.
     *
     * @return bool true
     */
    public function can_do_action_userusersetunassign() {
        return true;
    }

    /**
     * Whether the user has access to see the main page (assigned list)
     *
     * @return bool Whether the user has access.
     */
    public function can_do_default() {
        global $USER;
        $id = $this->required_param('id', PARAM_INT);
        $userviewctx = pm_context_set::for_user_with_capability('user', 'elis/program:user_view', $USER->id);
        return ($userviewctx->context_allowed($id, 'user') === true) ? true : false;
    }

    /**
     * Determine whether the current user can assign usersets to this user.
     *
     * @return bool Whether the user can assign.
     */
    public function can_do_add() {
        return true;
    }
}

/**
 * A deepsight page for managing userset - user associations (one userset, multiple users)
 */
class clusteruserpage extends userclusterbase {
    public $pagename = 'clstusr';
    public $tab_page = 'usersetpage';

    /**
     * Constructor
     * @param array $params An array of parameters for the page.
     */
    public function __construct(array $params = null) {
        $this->context = parent::_get_page_context();
        parent::__construct($params);
    }

    /**
     * Get the context of the current userset.
     *
     * @return context_elis_userset The current userset context object.
     */
    protected function get_context() {
        if (!isset($this->context)) {
            $id = required_param('id', PARAM_INT);
            $this->context = context_elis_userset::instance($id);
        }
        return $this->context;
    }

    /**
     * Construct the assigned datatable.
     *
     * @param string $uniqid A unique ID to assign to the datatable object.
     * @return deepsight_datatable The datatable object.
     */
    protected function construct_assigned_table($uniqid = null) {
        global $DB;
        $usersetid = $this->required_param('id', PARAM_INT);
        $endpoint = qualified_me().'&action=deepsight_response&tabletype=assigned&id='.$usersetid;
        $table = new deepsight_datatable_usersetuser_assigned($DB, 'assigned', $endpoint, $uniqid);
        $table->set_usersetid($usersetid);
        return $table;
    }

    /**
     * Construct the unassigned datatable.
     *
     * @param string $uniqid A unique ID to assign to the datatable object.
     * @return deepsight_datatable The datatable object.
     */
    protected function construct_unassigned_table($uniqid = null) {
        global $DB;
        $usersetid = $this->required_param('id', PARAM_INT);
        $endpoint = qualified_me().'&action=deepsight_response&tabletype=unassigned&id='.$usersetid;
        $table = new deepsight_datatable_usersetuser_available($DB, 'unassigned', $endpoint, $uniqid);
        $table->set_usersetid($usersetid);
        return $table;
    }

    /**
     * Userset assignment permission is handled at the action-object level.
     *
     * @return bool true
     */
    public function can_do_action_usersetuserassign() {
        return true;
    }

    /**
     * Userset unassignment permission is handled at the action-object level.
     *
     * @return bool true
     */
    public function can_do_action_usersetuserunassign() {
        return true;
    }

    /**
     * Whether the user has access to see the main page (assigned list)
     *
     * @return bool Whether the user has access.
     */
    public function can_do_default() {
        global $USER;
        $id = $this->required_param('id', PARAM_INT);
        $usersetviewctx = pm_context_set::for_user_with_capability('cluster', 'elis/program:userset_view', $USER->id);
        return ($usersetviewctx->context_allowed($id, 'cluster') === true) ? true : false;
    }

    /**
     * Determine whether the current user can assign users to this userset.
     *
     * @return bool Whether the user can assign users to this userset or not.
     */
    public function can_do_add() {
        $id = $this->required_param('id', PARAM_INT);
        return usersetpage::can_enrol_into_cluster($id);
    }
}