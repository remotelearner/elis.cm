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

require_once(elispm::lib('associationpage.class.php'));
require_once(elispm::lib('data/clustercurriculum.class.php'));
require_once(elispm::lib('data/clustertrack.class.php'));
require_once(elispm::file('usersetpage.class.php'));
require_once(elispm::file('trackpage.class.php'));
require_once(elispm::file('form/clustertrackform.class.php'));
require_once(elispm::file('form/clustertrackeditform.class.php'));

/**
 * Deepsight assignment page for userset - track associations.
 */
class clustertrackpage extends deepsightpage {
    public $pagename = 'clsttrk';
    public $section = 'users';
    public $tab_page = 'usersetpage';
    public $data_class = 'clustertrack';
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
     * Get the context of the current userset.
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
     * @param string $uniqid A unique ID to assign to the datatable object.
     * @return deepsight_datatable The datatable object.
     */
    protected function construct_assigned_table($uniqid = null) {
        global $DB;
        $usersetid = $this->required_param('id', PARAM_INT);
        $endpoint = qualified_me().'&action=deepsight_response&tabletype=assigned&id='.$usersetid;
        $table = new deepsight_datatable_usersettrack_assigned($DB, 'assigned', $endpoint, $uniqid);
        $table->set_usersetid($usersetid);
        return $table;
    }

    /**
     * Construct the unassigned datatable.
     * @param string $uniqid A unique ID to assign to the datatable object.
     * @return deepsight_datatable The datatable object.
     */
    protected function construct_unassigned_table($uniqid = null) {
        global $DB;
        $usersetid = $this->required_param('id', PARAM_INT);
        $endpoint = qualified_me().'&action=deepsight_response&tabletype=unassigned&id='.$usersetid;
        $table = new deepsight_datatable_usersettrack_available($DB, 'unassigned', $endpoint, $uniqid);
        $table->set_usersetid($usersetid);
        return $table;
    }

    /**
     * Assignment permission is handled at the action-object level.
     * @return bool true
     */
    public function can_do_action_usersettrackassign() {
        return true;
    }

    /**
     * Edit permission is handled at the action-object level.
     * @return bool true
     */
    public function can_do_action_usersettrackedit() {
        return true;
    }

    /**
     * Unassignment permission is handled at the action-object level.
     * @return bool true
     */
    public function can_do_action_usersettrackunassign() {
        return true;
    }

    /**
     * Whether the user has access to see the main page (assigned list)
     * @return bool Whether the user has access.
     */
    public function can_do_default() {
        global $USER;
        $id = $this->required_param('id', PARAM_INT);
        $requiredperms = array('elis/program:userset_view', 'elis/program:associate');
        foreach ($requiredperms as $perm) {
            $ctx = pm_context_set::for_user_with_capability('cluster', $perm, $USER->id);
            if ($ctx->context_allowed($id, 'cluster') !== true) {
                return false;
            }
        }
        return true;
    }

    /**
     * Determine whether the current user can assign tracks to the viewed userset.
     * @return bool Whether the user can assign tracks to this userset.
     */
    public function can_do_add() {
        return $this->can_do_default();
    }
}

/**
 * Deepsight assignment page for track - userset associations.
 */
class trackclusterpage extends deepsightpage {
    public $pagename = 'trkclst';
    public $section = 'curr';
    public $tab_page = 'trackpage';
    public $data_class = 'clustertrack';
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
     * @param string $uniqid A unique ID to assign to the datatable object.
     * @return deepsight_datatable The datatable object.
     */
    protected function construct_assigned_table($uniqid = null) {
        global $DB;
        $trackid = $this->required_param('id', PARAM_INT);
        $endpoint = qualified_me().'&action=deepsight_response&tabletype=assigned&id='.$trackid;
        $table = new deepsight_datatable_trackuserset_assigned($DB, 'assigned', $endpoint, $uniqid);
        $table->set_trackid($trackid);
        return $table;
    }

    /**
     * Construct the unassigned datatable.
     * @param string $uniqid A unique ID to assign to the datatable object.
     * @return deepsight_datatable The datatable object.
     */
    protected function construct_unassigned_table($uniqid = null) {
        global $DB;
        $trackid = $this->required_param('id', PARAM_INT);
        $endpoint = qualified_me().'&action=deepsight_response&tabletype=unassigned&id='.$trackid;
        $table = new deepsight_datatable_trackuserset_available($DB, 'unassigned', $endpoint, $uniqid);
        $table->set_trackid($trackid);
        return $table;
    }

    /**
     * Assignment permission is handled at the action-object level.
     * @return bool true
     */
    public function can_do_action_trackusersetassign() {
        return true;
    }

    /**
     * Edit permission is handled at the action-object level.
     * @return bool true
     */
    public function can_do_action_trackusersetedit() {
        return true;
    }


    /**
     * Unassignment permission is handled at the action-object level.
     * @return bool true
     */
    public function can_do_action_trackusersetunassign() {
        return true;
    }

    /**
     * Whether the user has access to see the main page (assigned list)
     * @return bool Whether the user has access.
     */
    public function can_do_default() {
        global $USER;
        $id = $this->required_param('id', PARAM_INT);
        $requiredperms = array('elis/program:track_view', 'elis/program:associate');
        foreach ($requiredperms as $perm) {
            $ctx = pm_context_set::for_user_with_capability('track', $perm, $USER->id);
            if ($ctx->context_allowed($id, 'track') !== true) {
                return false;
            }
        }
        return true;
    }

    /**
     * Determine whether the current user can assign usersets to the viewed track.
     * @return bool Whether the user can assign usersets to this track.
     */
    public function can_do_add() {
        return $this->can_do_default();
    }
}