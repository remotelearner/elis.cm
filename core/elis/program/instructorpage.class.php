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
 *
 */


defined('MOODLE_INTERNAL') or die();

require_once(elispm::lib('lib.php'));
require_once(elispm::lib('deprecatedlib.php'));
require_once(elispm::lib('deepsightpage.class.php'));
require_once(elispm::lib('associationpage.class.php'));
require_once(elispm::lib('data/instructor.class.php'));
require_once(elispm::file('pmclasspage.class.php'));

/**
 * A page to manage class instructors
 */
class instructorpage extends deepsightpage {
    /**
     * Language file used throughout the page.
     */
    const LANG_FILE = 'elis_program';

    /**
     * @var string A unique name for the page.
     */
    public $pagename = 'ins';

    /**
     * @var string The section of the page.
     */
    public $section = 'curr';

    /**
     * @var string The page to get tabs from.
     */
    public $tab_page = 'pmclasspage';

    /**
     * @var string The main data class.
     */
    public $data_class = 'instructor';

    /**
     * @var string The page's parent.
     */
    public $parent_page;

    /**
     * @var string The page's context.
     */
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
     * Get the context of the current class.
     * @return context_elis_class The current class context object.
     */
    protected function get_context() {
        if (!isset($this->context)) {
            $id = required_param('id', PARAM_INT);
            $this->context = context_elis_class::instance($id);
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
        $classid = $this->required_param('id', PARAM_INT);
        $endpoint = qualified_me().'&action=deepsight_response&tabletype=assigned&id='.$classid;
        $table = new deepsight_datatable_instructor_assigned($DB, 'assigned', $endpoint, $uniqid);
        $table->set_classid($classid);
        return $table;
    }

    /**
     * Construct the unassigned datatable.
     * @param string $uniqid A unique ID to assign to the datatable object.
     * @return deepsight_datatable The datatable object.
     */
    protected function construct_unassigned_table($uniqid = null) {
        global $DB;
        $classid = $this->required_param('id', PARAM_INT);
        $endpoint = qualified_me().'&action=deepsight_response&tabletype=unassigned&id='.$classid;
        $table = new deepsight_datatable_instructor_available($DB, 'unassigned', $endpoint, $uniqid);
        $table->set_classid($classid);
        return $table;
    }

    /**
     * Whether the user has access to see the main page (assigned list).
     * @return bool Whether the user has access.
     */
    public function can_do_default() {
        $id = $this->required_param('id', PARAM_INT);
        $pmclasspage = new pmclasspage(array('id' => $id));
        return $pmclasspage->can_do();
    }

    /**
     * Determine whether the current user can assign users as instructors.
     * @return bool Whether the user can assign users as instructors.
     */
    public function can_do_add() {
        $id = $this->required_param('id');
        return static::can_enrol_into_class($id);
    }

    /**
     * Assignment permissions are handled within the action object.
     * @return bool true
     */
    public function can_do_action_instructor_assign() {
        return true;
    }

    /**
     * Edit permissions are handled within the action object.
     * @return bool true
     */
    public function can_do_action_instructor_edit() {
        return true;
    }

    /**
     * Unassignment permissions are handled within the action object.
     * @return bool true
     */
    public function can_do_action_instructor_unassign() {
        return true;
    }

    /**
     * Determines whether the current user is allowed to enrol users into the provided class.
     * @param int $classid The id of the class we are checking permissions on
     * @return boolean Whether the user is allowed to enrol users into the class
     *
     */
    public static function can_enrol_into_class($classid) {
        global $USER;
        $directperm = 'elis/program:assign_class_instructor';
        $indirectperm = 'elis/program:assign_userset_user_class_instructor';

        // Check the standard capability.

        // TODO: Ugly, this needs to be overhauled.
        $cpage = new pmclasspage();
        if ($cpage->_has_capability($directperm, $classid) || $cpage->_has_capability($indirectperm, $classid)) {
            return true;
        }

        // Get the context for the "indirect" capability.
        $context = pm_context_set::for_user_with_capability('cluster', $indirectperm, $USER->id);

        // We first need to go through tracks to get to clusters.
        $tracklisting = new trackassignment(array('classid' => $classid));
        $tracks = $tracklisting->get_assigned_tracks();

        // Iterate over the track ides, which are the keys of the array.
        if (!empty($tracks)) {
            foreach (array_keys($tracks) as $track) {
                // Get the clusters and check the context against them.
                $clusters = clustertrack::get_clusters($track);
                if (!empty($clusters)) {
                    foreach ($clusters as $cluster) {
                        if ($context->context_allowed($cluster->clusterid, 'cluster')) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }
}
