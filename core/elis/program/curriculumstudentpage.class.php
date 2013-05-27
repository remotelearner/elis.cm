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

require_once(elispm::lib('data/curriculumcourse.class.php'));
require_once(elispm::lib('data/curriculumstudent.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/clusterassignment.class.php'));
require_once(elispm::lib('associationpage2.class.php'));
require_once(elispm::lib('deepsightpage.class.php'));
require_once(elispm::lib('contexts.php'));
require_once(elispm::file('userpage.class.php'));
require_once(elispm::file('curriculumpage.class.php'));
require_once(elispm::file('form/curriculumstudentform.class.php'));
require_once(elispm::lib('deepsightpage.class.php'));

/**
 * User -> Program associations.
 */
class studentcurriculumpage extends deepsightpage {
    public $parent_data_class = 'user';
    public $data_class = 'curriculumstudent';
    public $pagename = 'stucur';
    public $tab_page = 'userpage';
    public $default_tab = 'curriculumstudent';
    public $section = 'users';
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
     * Get the context for the current user.
     *
     * @return context_elis_user The context object.
     */
    protected function get_context() {
        if (!isset($this->context)) {
            $id = isset($this->params['id']) ? $this->params['id'] : required_param('id', PARAM_INT);
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
        $table = new deepsight_datatable_userprogram_assigned($DB, 'assigned', $endpoint, $uniqid, $userid);
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
        $table = new deepsight_datatable_userprogram_available($DB, 'unassigned', $endpoint, $uniqid, $userid);
        $table->set_userid($userid);
        return $table;
    }

    /**
     * Determine whether the current user can assign programs to users.
     * @return bool Whether the user has permissions or not.
     */
    public function can_do_add() {
        return true;
    }

    /**
     * Program assignment permission is handled at the action-object level
     * @return bool true
     */
    public function can_do_action_userprogramassign() {
        return true;
    }

    /**
     * Program unassignment permission is handled at the action-object level
     * @return bool true
     */
    public function can_do_action_userprogramunassign() {
        return true;
    }

}

/**
 * Defines the page to manage associations between programs and students
 */
class curriculumstudentpage extends deepsightpage {
    const LANG_FILE = 'elis_program';
    public $pagename = 'curstu';
    public $section = 'curr';
    public $tab_page = 'curriculumpage';
    public $data_class = 'curriculumstudent';
    public $parent_data_class = 'curriculum';
    public $default_tab = 'curriculumstudent';
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
     * Get the context of the current program.
     * @return context_elis_program The context object of the current program.
     */
    protected function get_context() {
        if (!isset($this->context)) {
            $id = required_param('id', PARAM_INT);
            $this->context = context_elis_program::instance($id);
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
        $programid = $this->required_param('id', PARAM_INT);
        $endpoint = qualified_me().'&action=deepsight_response&tabletype=assigned&id='.$programid;
        $table = new deepsight_datatable_programuser_assigned($DB, 'assigned', $endpoint, $uniqid);
        $table->set_programid($programid);
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
        $programid = $this->required_param('id', PARAM_INT);
        $endpoint = qualified_me().'&action=deepsight_response&tabletype=unassigned&id='.$programid;
        $table = new deepsight_datatable_programuser_available($DB, 'unassigned', $endpoint, $uniqid, $programid);
        $table->set_programid($programid);
        return $table;
    }

    /**
     * Program assignment permission is handled at the action-object level
     * @return bool true
     */
    public function can_do_action_programuserassign() {
        return true;
    }

    /**
     * Program unassignment permission is handled at the action-object level
     * @return bool true
     */
    public function can_do_action_programuserunassign() {
        return true;
    }

    /**
     * Whether the user has access to see the main page (assigned list)
     * @return bool Whether the user has access.
     */
    public function can_do_default() {
        $id = $this->required_param('id');
        $cpage = new curriculumpage();
        return $cpage->_has_capability('elis/program:program_view', $id);
    }

    /**
     * Determine whether the current user can enrol students into the class.
     * @return bool Whether the user can enrol users into the class or not.
     */
    public function can_do_add() {
        $id = $this->required_param('id');
        return curriculumpage::can_enrol_into_curriculum($id);
    }
}