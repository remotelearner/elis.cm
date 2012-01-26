<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
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
 * @subpackage curriculummanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once (CURMAN_DIRLOCATION . '/lib/managementpage.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/curriculum.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/datedelta.class.php');
require_once (CURMAN_DIRLOCATION . '/form/curriculaform.class.php');
require_once (CURMAN_DIRLOCATION . '/clustercurriculumpage.class.php');
require_once (CURMAN_DIRLOCATION . '/curriculumcoursepage.class.php');
require_once (CURMAN_DIRLOCATION . '/taginstancepage.class.php');
require_once (CURMAN_DIRLOCATION . '/rolepage.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/contexts.php');


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
        if(curriculumpage::_has_capability('block/curr_admin:curriculum:enrol', $curriculumid)) {
            return true;
        }

        //get the context for the "indirect" capability
        $context = cm_context_set::for_user_with_capability('cluster', 'block/curr_admin:curriculum:enrol_cluster_user', $USER->id);

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
        $id = $id ? $id : $this->required_param('id', PARAM_INT);
        $cached = curriculumpage::check_cached($capability, $id);
        if ($cached !== null) {
            return $cached;
        }
        $context = get_context_instance(context_level_base::get_custom_context_level('curriculum', 'block_curr_admin'), $id);
        return has_capability($capability, $context);
    }

    function __construct($params=false) {
        $this->tabs = array(
        array('tab_id' => 'view', 'page' => get_class($this), 'params' => array('action' => 'view'), 'name' => get_string('detail', 'block_curr_admin'), 'showtab' => true),
        array('tab_id' => 'edit', 'page' => get_class($this), 'params' => array('action' => 'edit'), 'name' => get_string('edit', 'block_curr_admin'), 'showtab' => true, 'showbutton' => true, 'image' => 'edit.gif'),
//TODO: reenable showtab and show button once it is in place
        array('tab_id' => 'curriculumstudentpage', 'page' => 'curriculumstudentpage', 'name' => get_string('users', 'block_curr_admin'), 'showtab' => true, 'showbutton' => true, 'image' => 'user.gif'),
        array('tab_id' => 'curriculumclusterpage', 'page' => 'curriculumclusterpage', 'name' => get_string('clusters', 'block_curr_admin'), 'showtab' => true, 'showbutton' => true, 'image' => 'cluster.gif'),
        array('tab_id' => 'curriculumcoursepage', 'page' => 'curriculumcoursepage', 'name' => get_string('courses', 'block_curr_admin') , 'showtab' => true, 'showbutton' => true, 'image' => 'course.gif'),
        //allow users to view the tracks associated with this curriculum
        array('tab_id' => 'trackpage', 'page' => 'trackpage', 'name' => get_string('tracks', 'block_curr_admin'), 'showtab' => true, 'showbutton' => true, 'image' => 'track.gif'),
        array('tab_id' => 'curtaginstancepage', 'page' => 'curtaginstancepage', 'name' => get_string('tags', 'block_curr_admin'), 'showtab' => true, 'showbutton' => true, 'image' => 'tag.gif'),
        array('tab_id' => 'curriculum_rolepage', 'page' => 'curriculum_rolepage', 'name' => get_string('roles', 'role'), 'showtab' => true, 'showbutton' => false, 'image' => 'tag.gif'),

        array('tab_id' => 'delete', 'page' => get_class($this), 'params' => array('action' => 'delete'), 'name' => get_string('delete', 'block_curr_admin'), 'showbutton' => true, 'image' => 'delete.gif'),
        );

        parent::__construct($params);
    }

    function can_do_view() {
        return $this->_has_capability('block/curr_admin:curriculum:view');
    }

    function can_do_edit() {
        return $this->_has_capability('block/curr_admin:curriculum:edit');
    }

    function can_do_delete() {
        return $this->_has_capability('block/curr_admin:curriculum:delete');
    }

    function can_do_confirm() {
        return $this->can_do_delete();
    }

    function can_do_add() {
        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('block/curr_admin:curriculum:create', $context);
    }

    function can_do_default() {
        $contexts = curriculumpage::get_contexts('block/curr_admin:curriculum:view');
        return !$contexts->is_empty();
    }

    function action_default() {
        // Get parameters
        $sort         = optional_param('sort', 'name', PARAM_ALPHA);
        $dir          = optional_param('dir', 'ASC', PARAM_ALPHA);

        $page         = optional_param('page', 0, PARAM_INT);
        $perpage      = optional_param('perpage', 30, PARAM_INT);        // how many per page

        $namesearch   = trim(cm_get_param('search', ''));
        $alpha        = cm_get_param('alpha', '');

        // Define columns
        $columns = array(
            'name'        => get_string('curriculum_name', 'block_curr_admin'),
            'description' => get_string('curriculum_shortdescription', 'block_curr_admin'),
            'reqcredits'  => get_string('curriculum_reqcredits', 'block_curr_admin'),
            'courses'     => get_string('courses', 'block_curr_admin'),
            'priority'    => get_string('priority', 'block_curr_admin')
        );

        // Get list of users
        $items    = curriculum_get_listing($sort, $dir, $page*$perpage, $perpage, $namesearch, $alpha, curriculumpage::get_contexts('block/curr_admin:curriculum:view'));
        $numitems = curriculum_count_records($namesearch, $alpha, curriculumpage::get_contexts('block/curr_admin:curriculum:view'));

        curriculumpage::get_contexts('block/curr_admin:curriculum:edit');
        curriculumpage::get_contexts('block/curr_admin:curriculum:delete');

        $this->print_list_view($items, $numitems, $columns, $filter=null, $alphaflag=true, $searchflag=true);
    }

    /**
     * Hook that gets called after a CM entity is added through this page
     * (Note: this function should only use the id field from the supplied cm entity
     *  as the rest of the data is not guaranteed to be there)
     *
     * @param  object  $cm_entity  The CM entity added
     */
    function after_cm_entity_add($cm_entity) {
        global $USER, $CURMAN;

        //make sure a valid role is set
        if(!empty($CURMAN->config->default_curriculum_role_id) && record_exists('role', 'id', $CURMAN->config->default_curriculum_role_id)) {

            //get the context instance for capability checking
            $context_level = context_level_base::get_custom_context_level('curriculum', 'block_curr_admin');
            $context_instance = get_context_instance($context_level, $cm_entity->id);

            //assign the appropriate role if the user does not have the edit capability
            if(!has_capability('block/curr_admin:curriculum:edit', $context_instance)) {
                role_assign($CURMAN->config->default_curriculum_role_id, $USER->id, 0, $context_instance->id);
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
        return has_capability('block/curr_admin:curriculum:create', $context);
        // FIXME: check permissions on the desired course too
    }

    /**
     * Prints the form for adding a new record.
     */
    function print_add_form() {
        $target = $this->get_new_page(array('action' => 'savenew'));

        $form = new $this->form_class($target->get_moodle_url());

        if(!empty($this->data)) {
            $data = $this->data;
            $data->idnumber .= ' -C';
            $data->name .= '-Curriculum';
            $form->set_data($data);
        }

        $form->display();
    }

    function action_default() {
        $courseid = $this->required_param('id', PARAM_INT);
        $data = new course($courseid);
        $data->courseid = $data->id;
        unset($data->id);
        $this->data = $data;

        $this->print_add_form();
    }

    function action_savenew() {
        $target = $this->get_new_page(array('action' => 'add'));

        $form = new $this->form_class($target->get_moodle_url());

        if ($form->is_cancelled()) {
            $this->action_default();
            return;
        }

        $data = $form->get_data();

        if($data) {
            $obj = new $this->data_class();
            $obj->set_from_data($data);
            $obj->add();

            $course = new course($data->courseid);
            $course->add_course_to_curricula(array($obj->id));

            $coursepage = new coursepage();

            $target = $coursepage->get_new_page(array('action' => 'view', 'id' => $course->id));
            redirect($target->get_url(), ucwords($obj->get_verbose_name())  . ' ' . $obj->to_string() . ' saved.');
        } else {
            // Validation must have failed, redisplay form
            $form->display();
        }
    }
}

?>
