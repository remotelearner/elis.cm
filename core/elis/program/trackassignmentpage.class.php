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

require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/track.class.php'));
require_once(elispm::lib('page.class.php'));
require_once(elispm::lib('deepsightpage.class.php'));
require_once(elispm::lib('associationpage.class.php'));
require_once(elispm::file('trackpage.class.php'));
require_once(elispm::file('pmclasspage.class.php'));
require_once(elispm::file('form/trackassignmentform.class.php'));

/**
 * Deepsight assignment page for track - class associations.
 */
class trackassignmentpage extends deepsightpage {

    /**
     * @var string A unique name for the page.
     */
    public $pagename = 'trkcls';

    /**
     * @var string The section of the page.
     */
    public $section = 'curr';

    /**
     * @var string The page to get tabs from.
     */
    public $tab_page = 'trackpage';

    /**
     * @var string The main data class.
     */
    public $data_class = 'trackassignment';

    /**
     * @var string The page's parent.
     */
    public $parent_page;

    /**
     * @var string The page's context.
     */
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
     *
     * @param string $uniqid A unique ID to assign to the datatable object.
     * @return deepsight_datatable The datatable object.
     */
    protected function construct_assigned_table($uniqid = null) {
        global $DB;
        $trackid = $this->required_param('id', PARAM_INT);
        $endpoint = qualified_me().'&action=deepsight_response&tabletype=assigned&id='.$trackid;
        $table = new deepsight_datatable_trackclass_assigned($DB, 'assigned', $endpoint, $uniqid);
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
        $table = new deepsight_datatable_trackclass_available($DB, 'unassigned', $endpoint, $uniqid);
        $table->set_trackid($trackid);
        return $table;
    }

    /**
     * Assignment permission is handled at the action-object level.
     * @return bool true
     */
    public function can_do_action_trackclassassign() {
        return true;
    }

    /**
     * Edit permission is handled at the action-object level.
     * @return bool true
     */
    public function can_do_action_trackclassedit() {
        return true;
    }

    /**
     * Unassignment permission is handled at the action-object level.
     * @return bool true
     */
    public function can_do_action_trackclassunassign() {
        return true;
    }

    /**
     * Whether the user has access to see the main page (assigned list)
     * @return bool Whether the user has access.
     */
    public function can_do_default() {
        global $USER;
        $id = $this->required_param('id', PARAM_INT);
        $viewctx = pm_context_set::for_user_with_capability('track', 'elis/program:track_view', $USER->id);
        return ($viewctx->context_allowed($id, 'track') === true) ? true : false;
    }

    /**
     * Determine whether the current user can assign classes to the viewed track.
     * @return bool Whether the user can assign classes to this track.
     */
    public function can_do_add() {
        global $USER;
        $id = $this->required_param('id', PARAM_INT);
        $canview = $this->can_do_default();
        $associatectx = pm_context_set::for_user_with_capability('track', 'elis/program:associate', $USER->id);
        return ($canview === true && $associatectx->context_allowed($id, 'track') === true) ? true : false;
    }

    /**
     * Do autocreate classes action.
     */
    public function do_autocreate() {
        // TBD: display_autocreate() for error messages?
        $id = required_param('id', PARAM_INT);

        $track = new track($id);
        $track->track_auto_create();

        $tmppage = new trackassignmentpage(array('id' => $id));
        redirect($tmppage->url, get_string('track_success_autocreate', 'elis_program'));
    }

    /**
     * Display enrol all action.
     */
    public function display_enrolall() {
        // ELIS-3761: changed from do_enrolall()
        // since enrol_all_track_users_in_class() outputs message(s)!
        $id = required_param('id', PARAM_INT);
        $aid = required_param('association_id', PARAM_INT);

        $trackassignment = new trackassignment($aid);
        $trackassignment->enrol_all_track_users_in_class();

        $tmppage = new trackassignmentpage(array('id' => $id));
        redirect($tmppage->url, '', 15);
    }

    /**
     * Display the default action (assigned page)
     */
    public function display_default() {
        global $OUTPUT;
        $id = required_param('id', PARAM_INT);
        echo '<div align="center">';
        $tmppage = new trackassignmentpage(array('action'=>'autocreate', 'id'=>$id));
        $button = new single_button($tmppage->url, get_string('track_autocreate_button', 'elis_program'), 'get');
        echo $OUTPUT->render($button);
        echo '</div>';
        parent::display_default();
    }
}