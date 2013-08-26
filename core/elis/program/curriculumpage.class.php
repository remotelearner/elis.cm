<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2011 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @package    elis
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once elispm::lib('data/curriculum.class.php');
require_once elispm::lib('managementpage.class.php');
require_once elispm::lib('contexts.php');
require_once elispm::lib('datedelta.class.php');
require_once elispm::file('form/curriculumform.class.php');
require_once elispm::file('curriculumcoursepage.class.php');
require_once elispm::file('curriculumstudentpage.class.php');
require_once elispm::file('clustercurriculumpage.class.php');
require_once elispm::file('rolepage.class.php');

/// The main management page.
class curriculumpage extends managementpage {
    var $pagename = 'cur';
    var $section = 'curr';

    var $data_class = 'curriculum';
    var $form_class = 'cmCurriculaForm';

    var $view_columns = array('name', 'description');

    static $contexts = array();

    static function get_contexts($capability) {
        if (!isset(curriculumpage::$contexts[$capability])) {
            global $USER;
            curriculumpage::$contexts[$capability] = get_contexts_by_capability_for_user('curriculum', $capability, $USER->id);
        }
        return curriculumpage::$contexts[$capability];
    }

    /**
     * Check the cached capabilities for the current user.
     */
    static function check_cached($capability, $id) {
        if (isset(curriculumpage::$contexts[$capability])) {
            // we've already cached which contexts the user has delete
            // capabilities in
            $contexts = curriculumpage::$contexts[$capability];
            return $contexts->context_allowed($id, 'curriculum');
        }
        return null;
    }

    /**
     * Determines whether the current user is allowed to enrol users into the provided curriculum
     *
     * @param   int      $curriculumid  The id of the curriculum we are checking permissions on
     *
     * @return  boolean                 Whether the user is allowed to enrol users into the curriculum
     *
     */
    static function can_enrol_into_curriculum($curriculumid) {
        global $USER;

        //check the standard capability
        // TODO: Ugly, this needs to be overhauled
        $cpage = new curriculumpage();
        if($cpage->_has_capability('elis/program:program_enrol', $curriculumid)) {
            return true;
        }

        //get the context for the "indirect" capability
        $context = pm_context_set::for_user_with_capability('cluster', 'elis/program:program_enrol_userset_user', $USER->id);

        //get the clusters and check the context against them
        $clusters = clustercurriculum::get_clusters($curriculumid);
        if(!empty($clusters)) {
            foreach($clusters as $cluster) {
                if($context->context_allowed($cluster->clusterid, 'cluster')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if the user has the given capability for the requested curriculum
     */
    function _has_capability($capability, $id = null) {
        if (empty($id)) {
            $id = (isset($this) && method_exists($this, 'required_param'))
                  ? $this->required_param('id', PARAM_INT)
                  : required_param('id', PARAM_INT);
        }
        $cached = curriculumpage::check_cached($capability, $id);
        if ($cached !== null) {
            return $cached;
        }
        $context = context_elis_program::instance($id);
        return has_capability($capability, $context);
    }

    public function _get_page_params() {
        return parent::_get_page_params();
    }

    function __construct(array $params=null) {
        parent::__construct($params);

        $id = $this->optional_param('id', 0, PARAM_INT);
        $track_params = ($id) ? array('parent' => $id) : array();
        $this->tabs = array(
        array('tab_id' => 'view', 'page' => get_class($this), 'params' => array('action' => 'view'), 'name' => get_string('detail', 'elis_program'), 'showtab' => true),
        array('tab_id' => 'edit', 'page' => get_class($this), 'params' => array('action' => 'edit'), 'name' => get_string('edit', 'elis_program'), 'showtab' => true, 'showbutton' => true, 'image' => 'edit'),
        array('tab_id' => 'curriculumstudentpage', 'page' => 'curriculumstudentpage', 'name' => get_string('users', 'elis_program'), 'showtab' => true, 'showbutton' => true, 'image' => 'user'),
        array('tab_id' => 'curriculumclusterpage', 'page' => 'curriculumclusterpage', 'name' => get_string('clusters', 'elis_program'), 'showtab' => true, 'showbutton' => true, 'image' => 'cluster'),
        array('tab_id' => 'curriculumcoursepage', 'page' => 'curriculumcoursepage', 'name' => get_string('courses', 'elis_program') , 'showtab' => true, 'showbutton' => true, 'image' => 'course'),
        //allow users to view the tracks associated with this curriculum
        array('tab_id' => 'trackpage', 'page' => 'trackpage', 'params' => $track_params, 'name' => get_string('tracks', 'elis_program'), 'showtab' => true, 'showbutton' => true, 'image' => 'track'),
        array('tab_id' => 'curriculum_rolepage', 'page' => 'curriculum_rolepage', 'name' => get_string('roles', 'role'), 'showtab' => true, 'showbutton' => false, 'image' => 'tag'),
        array('tab_id' => 'delete', 'page' => get_class($this), 'params' => array('action' => 'delete'), 'name' => get_string('delete', 'elis_program'), 'showbutton' => true, 'image' => 'delete'),
        );

    }

    function can_do_view() {
        return $this->_has_capability('elis/program:program_view');
    }

    function can_do_edit() {
        return $this->_has_capability('elis/program:program_edit');
    }

    function can_do_delete() {
        return $this->_has_capability('elis/program:program_delete');
    }

    function can_do_confirm() {
        return $this->can_do_delete();
    }

    function can_do_add() {
        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('elis/program:program_create', $context);
    }

    function can_do_default() {
        $contexts = curriculumpage::get_contexts('elis/program:program_view');
        return !$contexts->is_empty();
    }

    function display_default() {
        // Get parameters
        $sort         = optional_param('sort', 'name', PARAM_ALPHA);
        $dir          = optional_param('dir', 'ASC', PARAM_ALPHA);

        $page         = optional_param('page', 0, PARAM_INT);
        $perpage      = optional_param('perpage', 30, PARAM_INT);        // how many per page

        $namesearch   = trim(optional_param('search', '', PARAM_TEXT));
        $alpha        = optional_param('alpha', '', PARAM_ALPHA);

        // Define columns
        $columns = array(
            'name'        => array('header' => get_string('curriculum_name', 'elis_program')),
            'description' => array('header' => get_string('description', 'elis_program')),
            'reqcredits'  => array('header' => get_string('curriculum_reqcredits', 'elis_program')),
            'courses'     => array('header' => get_string('courses', 'elis_program')),
            'priority'    => array('header' => get_string('priority', 'elis_program'))
        );

        if($dir !== 'DESC') {
            $dir = 'ASC';
        }
        if(isset($columns[$sort])) {
            $columns[$sort]['sortable'] = $dir;
        }

        // Get list of users
        $items    = curriculum_get_listing($sort, $dir, $page*$perpage, $perpage, $namesearch, $alpha, curriculumpage::get_contexts('elis/program:program_view'));
        $numitems = curriculum_count_records($namesearch, $alpha, curriculumpage::get_contexts('elis/program:program_view'));

        curriculumpage::get_contexts('elis/program:program_edit');
        curriculumpage::get_contexts('elis/program:program_delete');

        $this->print_list_view($items, $numitems, $columns, $filter=null, $alphaflag=true, $searchflag=true);
    }

    /**
     * Hook that gets called after a CM entity is added through this page
     * (Note: this function should only use the id field from the supplied cm entity
     *  as the rest of the data is not guaranteed to be there)
     *
     * @param  object  $cm_entity  The CM entity added
     * @uses $DB
     * @uses $USER
     */
    function after_cm_entity_add($cm_entity) {
        global $DB, $USER;

        //make sure a valid role is set
        if(!empty(elis::$config->elis_program->default_curriculum_role_id) && $DB->record_exists('role', array('id' => elis::$config->elis_program->default_curriculum_role_id))) {

            //get the context instance for capability checking
            $context_instance = context_elis_program::instance($cm_entity->id);

            //assign the appropriate role if the user does not have the edit capability
            if (!has_capability('elis/program:program_edit', $context_instance)) {
                role_assign(elis::$config->elis_program->default_curriculum_role_id, $USER->id, $context_instance->id);
            }
        }
    }

    /**
     * Specifies a unique shortname for the entity represented by
     * a page of this type, transforming the supplied value if necessary
     *
     * @param   string       $parent_path  Path of all parent elements, or the empty string
     *                                     if none
     * @param   string       $name         Initial name provided for the element
     *
     * @return  string|NULL                A valid name to identify the item with, or NULL if
     *                                     not applicable
     */
    static function get_entity_name($parent_path, $name) {
        $parts = explode('_', $name);

        //try to find the entity type and id, and combine them
        if (count($parts) == 2) {
            if ($parts[0] == 'curriculum') {
                return $parts[0] . '-' . $parts[1];
            }
        }

        return NULL;
    }
}

class curriculumforcoursepage extends curriculumpage {
    var $data = null;
    var $pagename = 'cfc';

    function can_do_default() {
        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('elis/program:program_create', $context);
        // FIXME: check permissions on the desired course too
    }

    /**
     * Prints the form for adding a new record.
     *
     * @param object $data Data to set on the form
     */
    function print_add_form($data) {
        $target = $this->get_new_page(array('action' => 'savenew'));

        $form = new $this->form_class($target->url);

        if (!empty($data)) {
            $data->idnumber .= ' -P';
            $data->name .= '-Program';
            $form->set_data($data);
        }

        $form->display();
    }

    function display_default() {
        //obtain a non-zero courseid if we are hitting this page for the first time
        //otherwise form data will submit back into itself
        $courseid = $this->required_param('id', PARAM_INT);
        $data = null;

        if ($courseid != 0) {
            //fetch the course record
	        $course = new course($courseid);
	        $course->load();

	        //set up the form data
	        $data = new stdClass;
	        $data->idnumber = $course->idnumber;
	        $data->name = $course->name;
	        //used to link the program to the course description
	        $data->courseid = $courseid;
        }

        $this->print_add_form($data);
    }

    function display_savenew() {
        $courseid = $this->optional_param('cfccourseid', 0, PARAM_INT);

        $target = $this->get_new_page(array('action' => 'savenew', 'cfccourseid' => $courseid));

        $form = new $this->form_class($target->url);

        if ($form->is_cancelled()) {
            //go back to course and list programs
            $target = new coursecurriculumpage(array('id'     => $courseid,
                                         'action' => 'default',
                                         's' => 'crscurr'));
            redirect($target->url);
            return;
        }

        $data = $form->get_data();

        if($data) {
            $obj = new $this->data_class();
            $obj->set_from_data($data);
            $obj->save();

            $course = new course($data->courseid);
            $course->add_course_to_curricula(array($obj->id));

            $page = new coursecurriculumpage();
            $params = array('action' => 'default', 'id' => $data->courseid);

            $target = $page->get_new_page($params);

            redirect($target->url, ucwords($obj->get_verbose_name())  . ' ' . $obj->__toString() . ' saved.');
        } else {
            // Validation must have failed, redisplay form
            $form->display();
        }
    }

}
