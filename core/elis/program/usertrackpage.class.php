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

require_once(elispm::lib('data/track.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/usertrack.class.php'));
require_once(elispm::lib('data/userset.class.php'));
require_once(elispm::lib('deepsightpage.class.php'));
require_once(elispm::file('trackassignmentpage.class.php'));
require_once(elispm::file('trackpage.class.php'));
require_once(elispm::file('userpage.class.php'));

/**
 * Deepsight assignment page for user - track associations.
 */
class usertrackpage extends deepsightpage {
    public $pagename = 'usrtrk';
    public $section = 'users';
    public $tab_page = 'userpage';
    public $data_class = 'usertrack';
    public $parent_page;
    public $context;

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
        $table = new deepsight_datatable_usertrack_assigned($DB, 'assigned', $endpoint, $uniqid);
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
        $table = new deepsight_datatable_usertrack_available($DB, 'unassigned', $endpoint, $uniqid);
        $table->set_userid($userid);
        return $table;
    }

    /**
     * Track assignment permission is handled at the action-object level.
     *
     * @return true
     */
    public function can_do_action_usertrackassign() {
        return true;
    }

    /**
     * Track unassignment permission is handled at the action-object level.
     *
     * @return true
     */
    public function can_do_action_usertrackunassign() {
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
     * Determine whether the current user can assign tracks to the viewed user.
     *
     * @return bool Whether the user can assign tracks to this user.
     */
    public function can_do_add() {
        global $USER;
        $id = $this->required_param('id', PARAM_INT);
        $canview = $this->can_do_default();
        $associatectx = pm_context_set::for_user_with_capability('user', 'elis/program:associate', $USER->id);
        return ($canview === true && $associatectx->context_allowed($id, 'user') === true) ? true : false;
    }
}

/**
 * Deepsight assignment page for track - user associations.
 */
class trackuserpage extends deepsightpage {
    public $pagename = 'trkusr';
    public $section = 'curr';
    public $tab_page = 'trackpage';
    public $data_class = 'usertrack';
    public $parent_page;
    public $context;

    /**
     * Constructor.
     * @param array $params An array of parameters for the page.
     */
    public function __construct(array $params = null) {
        $this->context = parent::_get_page_context();
        parent::__construct($params);
    }

    /**
     * Get the context of the current track.
     *
     * @return context_elis_track The current track context object.
     */
    protected function get_context() {
        if (!isset($this->context)) {
            $id = required_param('id', PARAM_INT);
            $this->context = context_elis_track::instance($id);
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
        $trackid = $this->required_param('id', PARAM_INT);
        $endpoint = qualified_me().'&action=deepsight_response&tabletype=assigned&id='.$trackid;
        $table = new deepsight_datatable_trackuser_assigned($DB, 'assigned', $endpoint, $uniqid);
        $table->set_trackid($trackid);
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
        $trackid = $this->required_param('id', PARAM_INT);
        $endpoint = qualified_me().'&action=deepsight_response&tabletype=unassigned&id='.$trackid;
        $table = new deepsight_datatable_trackuser_available($DB, 'unassigned', $endpoint, $uniqid, $trackid);
        $table->set_trackid($trackid);
        return $table;
    }

    /**
     * Track assignment permission is handled at the action-object level.
     *
     * @return bool true
     */
    public function can_do_action_trackuserassign() {
        return true;
    }

    /**
     * Track unassignment permission is handled at the action-object level.
     *
     * @return bool true
     */
    public function can_do_action_trackuserunassign() {
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
        $userviewctx = pm_context_set::for_user_with_capability('track', 'elis/program:track_view', $USER->id);
        return ($userviewctx->context_allowed($id, 'track') === true) ? true : false;
    }

    /**
     * Determine whether the current user can assign users to this track.
     *
     * @return bool Whether the user can assign users to this track or not.
     */
    public function can_do_add() {
        $id = $this->required_param('id', PARAM_INT);
        return trackpage::can_enrol_into_track($id);
    }
}
