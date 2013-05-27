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
 * An action to assign usersets to a track and set the autoenrol flag.
 */
class deepsight_action_trackuserset_assign extends deepsight_action_standard {
    const TYPE = 'usersettrack_assignedit';
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
        $this->label = ucwords(get_string('assign', 'elis_program'));

        $langelements = new stdClass;
        $langelements->baseelement = strtolower(get_string('track', 'elis_program'));
        $langelements->actionelement = strtolower(get_string('cluster', 'elis_program'));
        $this->descsingle = (!empty($descsingle))
                ? $descsingle : get_string('ds_action_assign_confirm', 'elis_program', $langelements);

        $langelements = new stdClass;
        $langelements->baseelement = strtolower(get_string('track', 'elis_program'));
        $langelements->actionelement = strtolower(get_string('clusters', 'elis_program'));
        $this->descmultiple = (!empty($descmultiple))
                ? $descmultiple : get_string('ds_action_assign_confirm_multi', 'elis_program', $langelements);
    }

    /**
     * Provide options to the javascript.
     *
     * @return array An array of options.
     */
    public function get_js_opts() {
        global $CFG;

        $opts = parent::get_js_opts();
        $opts['condition'] = $this->condition;
        $opts['opts']['actionurl'] = $this->endpoint;
        $opts['opts']['autoenroldefault'] = '';
        $opts['opts']['desc_single'] = $this->descsingle;
        $opts['opts']['desc_multiple'] = $this->descmultiple;
        $opts['opts']['mode'] = 'assign';
        $opts['opts']['lang_bulk_confirm'] = get_string('ds_bulk_confirm', 'elis_program');
        $opts['opts']['lang_working'] = get_string('ds_working', 'elis_program');
        $opts['opts']['langautoenrol'] = get_string('usersettrack_auto_enrol', 'elis_program');
        $opts['opts']['langyes'] = get_string('yes', 'moodle');
        $opts['opts']['langno'] = get_string('no', 'moodle');
        return $opts;
    }

    /**
     * Assign usersets to the track.
     * @param array $elements An array of userset information to assign to the track.
     * @param bool $bulkaction Whether this is a bulk-action or not.
     * @return array An array to format as JSON and return to the Javascript.
     */
    protected function _respond_to_js(array $elements, $bulkaction) {
        global $DB;
        $trackid = required_param('id', PARAM_INT);
        $autoenrol = optional_param('autoenrol', 0, PARAM_INT);
        $autounenrol = optional_param('autounenrol', 1, PARAM_INT);
        foreach ($elements as $usersetid => $label) {
            if ($this->can_assign($trackid, $usersetid) === true) {
                clustertrack::associate($usersetid, $trackid, $autounenrol, $autoenrol);
            }
        }
        return array('result' => 'success', 'msg' => 'Success');
    }

    /**
     * Determine whether the current user can assign the userset to the track.
     * @param int $trackid The ID of the track.
     * @param int $usersetid The ID of the userset.
     * @return bool Whether the current can assign (true) or not (false)
     */
    protected function can_assign($trackid, $usersetid) {
        global $USER;
        $perm = 'elis/program:associate';
        $trkassocctx = pm_context_set::for_user_with_capability('track', $perm, $USER->id);
        $trackassociateallowed = ($trkassocctx->context_allowed($trackid, 'track') === true) ? true : false;
        $clstassocctx = pm_context_set::for_user_with_capability('cluster', $perm, $USER->id);
        $usersetassociateallowed = ($clstassocctx->context_allowed($usersetid, 'cluster') === true) ? true : false;
        return ($trackassociateallowed === true && $usersetassociateallowed === true) ? true : false;
    }
}

/**
 * An action to edit the autoenrol flag on a clustertrack assignment.
 */
class deepsight_action_trackuserset_edit extends deepsight_action_standard {
    const TYPE = 'usersettrack_assignedit';
    public $label = '';
    public $icon = 'elisicon-edit';

    /**
     * Sets the action's label from language string.
     */
    protected function postconstruct() {
        $this->label = get_string('ds_action_edit', 'elis_program');
    }

    /**
     * Provide options to the javascript.
     * @return array An array of options.
     */
    public function get_js_opts() {
        global $CFG;
        $opts = parent::get_js_opts();
        $opts['condition'] = $this->condition;
        $opts['opts']['actionurl'] = $this->endpoint;
        $opts['opts']['desc_single'] = '';
        $opts['opts']['desc_multiple'] = '';
        $opts['opts']['mode'] = 'edit';
        $opts['opts']['lang_bulk_confirm'] = get_string('ds_bulk_confirm', 'elis_program');
        $opts['opts']['lang_working'] = get_string('ds_working', 'elis_program');
        $opts['opts']['langautoenrol'] = get_string('usersettrack_auto_enrol', 'elis_program');
        $opts['opts']['langyes'] = get_string('yes', 'moodle');
        $opts['opts']['langno'] = get_string('no', 'moodle');
        return $opts;
    }

    /**
     * Edit clustertrack information.
     * @param array $elements An array of track information to assign to the userset.
     * @param bool $bulkaction Whether this is a bulk-action or not.
     * @return array An array to format as JSON and return to the Javascript.
     */
    protected function _respond_to_js(array $elements, $bulkaction) {
        global $DB;
        $trackid = required_param('id', PARAM_INT);
        $autoenrol = required_param('autoenrol', PARAM_INT);
        foreach ($elements as $usersetid => $label) {
            if ($this->can_edit($trackid, $usersetid) === true) {
                $association = $DB->get_record(clustertrack::TABLE, array('clusterid' => $usersetid, 'trackid' => $trackid));
                if (!empty($association)) {
                    clustertrack::update_autoenrol($association->id, $autoenrol);
                }
            }
        }
        return array('result' => 'success', 'msg' => 'Success');
    }

    /**
     * Determine whether the current user can edit the clustertrack association.
     * @param int $trackid The ID of the track.
     * @param int $usersetid The ID of the userset.
     * @return bool Whether the current can edit (true) or not (false)
     */
    protected function can_edit($trackid, $usersetid) {
        global $USER;
        $perm = 'elis/program:associate';
        $trkassocctx = pm_context_set::for_user_with_capability('track', $perm, $USER->id);
        $trackassociateallowed = ($trkassocctx->context_allowed($trackid, 'track') === true) ? true : false;
        $clstassocctx = pm_context_set::for_user_with_capability('cluster', $perm, $USER->id);
        $usersetassociateallowed = ($clstassocctx->context_allowed($usersetid, 'cluster') === true) ? true : false;
        return ($trackassociateallowed === true && $usersetassociateallowed === true) ? true : false;
    }
}

/**
 * An action to unassign usersets from a track.
 */
class deepsight_action_trackuserset_unassign extends deepsight_action_confirm {
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
        $langelements->baseelement = strtolower(get_string('track', 'elis_program'));
        $langelements->actionelement = strtolower(get_string('cluster', 'elis_program'));
        $this->descsingle = (!empty($descsingle))
                ? $descsingle : get_string('ds_action_unassign_confirm', 'elis_program', $langelements);

        $langelements = new stdClass;
        $langelements->baseelement = strtolower(get_string('track', 'elis_program'));
        $langelements->actionelement = strtolower(get_string('clusters', 'elis_program'));
        $this->descmultiple = (!empty($descmultiple))
                ? $descmultiple : get_string('ds_action_unassign_confirm_multi', 'elis_program', $langelements);
    }

    /**
     * Unassign the usersets from the track.
     * @param array $elements An array of userset information to unassign from the track.
     * @param bool $bulkaction Whether this is a bulk-action or not.
     * @return array An array to format as JSON and return to the Javascript.
     */
    protected function _respond_to_js(array $elements, $bulkaction) {
        global $DB;
        $trackid = required_param('id', PARAM_INT);
        foreach ($elements as $usersetid => $label) {
            if ($this->can_unassign($trackid, $usersetid) === true) {
                $assignrec = $DB->get_record(clustertrack::TABLE, array('clusterid' => $usersetid, 'trackid' => $trackid));
                $usertrack = new clustertrack($assignrec);
                $usertrack->delete();
            }
        }
        return array('result' => 'success', 'msg'=>'Success');
    }

    /**
     * Determine whether the current user can unassign the userset from the track.
     * @param int $trackid The ID of the track.
     * @param int $usersetid The ID of the userset.
     * @return bool Whether the current can unassign (true) or not (false)
     */
    protected function can_unassign($trackid, $usersetid) {
        global $USER;
        $perm = 'elis/program:associate';
        $trkassocctx = pm_context_set::for_user_with_capability('track', $perm, $USER->id);
        $trackassociateallowed = ($trkassocctx->context_allowed($trackid, 'track') === true) ? true : false;
        $clstassocctx = pm_context_set::for_user_with_capability('cluster', $perm, $USER->id);
        $usersetassociateallowed = ($clstassocctx->context_allowed($usersetid, 'cluster') === true) ? true : false;
        return ($trackassociateallowed === true && $usersetassociateallowed === true) ? true : false;
    }
}