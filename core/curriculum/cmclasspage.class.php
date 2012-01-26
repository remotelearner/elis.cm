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
require_once (CURMAN_DIRLOCATION . '/lib/cmclass.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/course.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/environment.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/classmoodlecourse.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/coursetemplate.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/moodlecourseurl.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/track.class.php');

require_once (CURMAN_DIRLOCATION . '/form/cmclassform.class.php');
require_once (CURMAN_DIRLOCATION . '/studentpage.class.php');
require_once (CURMAN_DIRLOCATION . '/waitlistpage.class.php');
require_once (CURMAN_DIRLOCATION . '/instructorpage.class.php');
require_once (CURMAN_DIRLOCATION . '/taginstancepage.class.php');
require_once (CURMAN_DIRLOCATION . '/rolepage.class.php');
require_once (CURMAN_DIRLOCATION . '/reportlinkspage.class.php');

class cmclasspage extends managementpage {
    var $data_class = 'cmclass';
    var $form_class = 'cmclassform';

    var $view_columns = array('idnumber');  // TODO: make crsname link to the view page for that course

    var $pagename = 'cls';
    var $section = 'curr';

    static $contexts = array();

    static function get_contexts($capability, $userid = 0) {
        if (!isset(cmclasspage::$contexts[$capability])) {
            global $USER;
            cmclasspage::$contexts[$capability] = get_contexts_by_capability_for_user('class', $capability, $userid ? $userid : $USER->id);
        }
        return cmclasspage::$contexts[$capability];
    }

    /**
     * Check the cached capabilities for the current track.
     */
    static function check_cached($capability, $id) {
        global $CURMAN;
        if (isset(cmclasspage::$contexts[$capability])) {
            // we've already cached which contexts the user has delete
            // capabilities in
            $contexts = cmclasspage::$contexts[$capability];
            return $contexts->context_allowed($id, 'class');
        }
        return null;
    }

    /**
     * Determines whether the current user is allowed to enrol users into the provided class
     *
     * @param   int      $classid  The id of the class we are checking permissions on
     *
     * @return  boolean            Whether the user is allowed to enrol users into the class
     *
     */
    static function can_enrol_into_class($classid) {
        global $USER;

        //check the standard capability
        if(cmclasspage::_has_capability('block/curr_admin:class:enrol', $classid)
           || cmclasspage::_has_capability('block/curr_admin:class:enrol_cluster_user', $classid)) {
            return true;
        }

        //get the context for the "indirect" capability
        $context = cm_context_set::for_user_with_capability('cluster', 'block/curr_admin:class:enrol_cluster_user', $USER->id);

        //we first need to go through tracks to get to clusters
        $track_listing = new trackassignmentclass(array('classid' => $classid));
        $tracks = $track_listing->get_assigned_tracks();

        //iterate over the track ides, which are the keys of the array
        if(!empty($tracks)) {
            foreach(array_keys($tracks) as $track) {
                //get the clusters and check the context against them
                $clusters = clustertrack::get_clusters($track);

                if(!empty($clusters)) {
                    foreach($clusters as $cluster) {
                        if($context->context_allowed($cluster->clusterid, 'cluster')) {
                            return true;
                        }
                    }
                }

            }
        }

        return false;
    }

    /**
     * Check if the user has the given capability for the requested track
     */
    function _has_capability($capability, $id = null) {
        $id = $id ? $id : $this->required_param('id', PARAM_INT);
        // class contexts are different -- we rely on the cache because tracks
        // require special logic
        cmclasspage::get_contexts($capability);
        $cached = cmclasspage::check_cached($capability, $id);
        if ($cached !== null) {
            return $cached;
        }
        $context = get_context_instance(context_level_base::get_custom_context_level('class', 'block_curr_admin'), $id);
        return has_capability($capability, $context);
    }

    function __construct($params=false) {
        $this->tabs = array(
        array('tab_id' => 'view', 'page' => get_class($this), 'params' => array('action' => 'view'), 'name' => get_string('detail', 'block_curr_admin'), 'showtab' => true),
        array('tab_id' => 'edit', 'page' => get_class($this), 'params' => array('action' => 'edit'), 'name' => get_string('edit', 'block_curr_admin'), 'showtab' => true, 'showbutton' => true, 'image' => 'edit.gif'),

        array('tab_id' => 'studentpage', 'page' => 'studentpage', 'name' => get_string('enrolments', 'block_curr_admin'), 'showtab' => true, 'showbutton' => true, 'image' => 'user.gif'),
        array('tab_id' => 'waitlistpage', 'page' => 'waitlistpage', 'name' => get_string('waiting', 'block_curr_admin'), 'showtab' => true, 'showbutton' => true, 'image' => 'waiting.png'),
        array('tab_id' => 'instructorpage', 'page' => 'instructorpage', 'name' => get_string('instructors', 'block_curr_admin'), 'showtab' => true, 'showbutton' => true, 'image' => 'instructor.gif'),
        array('tab_id' => 'clstaginstancepage', 'page' => 'clstaginstancepage', 'name' => get_string('tags', 'block_curr_admin'), 'showtab' => true, 'showbutton' => true, 'image' => 'tag.gif'),
        array('tab_id' => 'class_rolepage', 'page' => 'class_rolepage', 'name' => get_string('roles', 'role'), 'showtab' => true, 'showbutton' => false, 'image' => 'tag.gif'),

        array('tab_id' => 'delete', 'page' => get_class($this), 'params' => array('action' => 'delete'), 'name' => get_string('delete_label', 'block_curr_admin'), 'showbutton' => true, 'image' => 'delete.gif'),
        array('tab_id' => 'class_reportlinkspage', 'page' => 'class_reportlinkspage', '', 'name' => get_string('classreportlinks', 'block_curr_admin'), 'showtab' => true, 'showbutton' => true, 'image' => 'report.gif')
        );

        parent::__construct($params);
    }

    function can_do_view() {
        global $USER;
        $id = $this->required_param('id', PARAM_INT);
        return $this->_has_capability('block/curr_admin:class:view')
            || instructor::user_is_instructor_of_class(cm_get_crlmuserid($USER->id), $id);
    }

    function can_do_edit() {
        global $USER;
        $id = $this->required_param('id', PARAM_INT);
        return $this->_has_capability('block/curr_admin:class:edit')
            || instructor::user_is_instructor_of_class(cm_get_crlmuserid($USER->id), $id);
    }

    function can_do_delete() {
        return $this->_has_capability('block/curr_admin:class:delete');
    }

    function can_do_confirm() {
        return $this->can_do_delete();
    }

    function can_do_add() {
        global $USER;
        if (!empty($USER->id)) {
            $contexts = get_contexts_by_capability_for_user('course',
                            'block/curr_admin:class:create', $USER->id);
            return(!$contexts->is_empty());
        }
        return false;
    }

    function can_do_default() {
        $contexts = cmclasspage::get_contexts('block/curr_admin:class:view');
        return !$contexts->is_empty();
    }

    /**
     * Constructs navigational breadcrumbs
     */
    function get_navigation_default() {
        global $CFG, $CURMAN;

        //get the parent courseid if possible
        $parent = $this->get_cm_id();

        $action = $this->optional_param('action', '', PARAM_CLEAN);
        $cancel = $this->optional_param('cancel', '', PARAM_CLEAN);

        $navigation = parent::get_navigation_default();

        if (empty($parent) || ((!empty($action) && $action!='default') && empty($cancel))) {
            //viewing the class page directly
            return $navigation;
        }

        $coursepage = new coursepage(array('id' => $parent));
        $course_navigation = $coursepage->get_navigation_view();

        //combine course and class navigation
        return array_merge($course_navigation, $navigation);
    }

    /* override parent class, because formslib is picky
     */
    function action_savenew() {
        $target = $this->get_new_page(array('action' => 'savenew'));

        $form = new $this->form_class($target->get_moodle_url());

        if ($form->is_cancelled()) {
            $this->action_default();
            return;
        }

        // track is populated using AJAX-y magic, which confuses formslib, so
        // just fetch the submitted data
        $data = $form->get_submitted_data();
        $tracks = array();

        if (!empty($data->track)) {
            // sanitize, since we're bypassing formslib's validation
            foreach($data->track as $key => $track) {
                $tracks[$key] =  clean_param($track, PARAM_INT);
            }
        }

        $data = $form->get_data();

        if($data) {
            $data->track = $tracks;
            $obj = new $this->data_class();
            $obj->set_from_data($data);
            $obj->add();
            $target = $this->get_new_page(array('action' => 'view', 'id' => $obj->id));
            redirect($target->get_url(), ucwords($obj->get_verbose_name())  . ' ' . $obj->to_string() . ' saved.');
        } else {
            // Validation must have failed, redisplay form
            $form->display();
        }
    }

    function action_default() {
        // Get parameters
        $sort             = optional_param('sort', 'crsname', PARAM_ALPHA);
        $dir              = optional_param('dir', 'ASC', PARAM_ALPHA);

        $page             = optional_param('page', 0, PARAM_INT);
        $perpage          = optional_param('perpage', 30, PARAM_INT);        // how many per page

        $namesearch       = trim(optional_param('search', '', PARAM_TEXT));
        $alpha            = optional_param('alpha', '', PARAM_ALPHA);

        $id               = $this->get_cm_id();

        //this parameter signifies a required relationship between a class and a track
        //through a cluster
        $parent_clusterid = $this->optional_param('parent_clusterid', 0, PARAM_INT);

        // Define columns
        $columns = array(
            'crsname'      => get_string('class_course', 'block_curr_admin'),
            'idnumber'     => get_string('class_idnumber', 'block_curr_admin'),
            'moodlecourse' => get_string('class_moodle_course', 'block_curr_admin'),
            'startdate'    => get_string('class_startdate', 'block_curr_admin'),
            'enddate'      => get_string('class_enddate', 'block_curr_admin'),
            'starttime'    => get_string('class_starttime', 'block_curr_admin'),
            'endtime'      => get_string('class_endtime', 'block_curr_admin'),
            'maxstudents'  => get_string('class_maxstudents', 'block_curr_admin'),
            'envname'      => get_string('environment', 'block_curr_admin'),
        );

        $items    = cmclass_get_listing($sort, $dir, $page*$perpage, $perpage, $namesearch, $alpha, $id, false, cmclasspage::get_contexts('block/curr_admin:class:view'), $parent_clusterid);
        $numitems = cmclass_count_records($namesearch, $alpha, $id, false, cmclasspage::get_contexts('block/curr_admin:class:view'), $parent_clusterid);

        cmclasspage::get_contexts('block/curr_admin:class:edit');
        cmclasspage::get_contexts('block/curr_admin:class:delete');

        if (!empty($id)) {
            $coursepage = new coursepage(array('id' => $id));
            $coursepage->print_tabs('cmclasspage', array('id' => $id));
        }

        $this->print_list_view($items, $numitems, $columns, $filter=null, $alphaflag=true, $searchflag=true);
    }

    function action_delete() {
        $id = required_param('id', PARAM_INT);
        $force = optional_param('force', 0, PARAM_INT);

        if(count_records(STUTABLE, 'classid', $id) && $force != 1) {
            $target = $this->get_new_page(array('action' => 'delete', 'id' => $id, 'force' => 1));
            notify(get_string('cmclass_delete_warning', 'block_curr_admin'), 'errorbox');
            echo '<center><a href="' . $target->get_url() . '">'. get_string('cmclass_delete_warning_continue', 'block_curr_admin') . '</a></center>';
        }
        else {
            parent::action_delete();
        }
    }

    /**
     * Prints the single-button form used to request the add action for a record type.
     */
    function print_add_button() {
        if (!$this->can_do('add')) {
            return;
        }

        $obj = $this->get_new_data_object();

        echo '<div align="center">';
        $options = array('s' => $this->pagename, 'action' => 'add');
        $parent = $this->optional_param('id', 0, PARAM_INT);
        if ($parent) {
            $options['parent'] = $parent;
        }
        // FIXME: change to language string
        echo print_single_button('index.php', $options, get_string('add','block_curr_admin').' ' . get_string($obj->get_verbose_name(),'block_curr_admin'), 'get', '_self', true, get_string('add','block_curr_admin').' ' . get_string($obj->get_verbose_name(),'block_curr_admin'));
        echo '</div>';
    }

    /**
     * Converts add button data to data for our add form
     *
     * @return  stdClass  Form data, or null if none
     */
    function get_default_object_for_add() {
        // get site-wide default values
        global $CURMAN;

        $obj = (object) cmclass::get_default();

        $parent = $this->optional_param('parent', 0, PARAM_INT);
        if ($parent) {
            $obj->courseid = $parent;
        }
        return $obj;
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
        if(!empty($CURMAN->config->default_class_role_id) && record_exists('role', 'id', $CURMAN->config->default_class_role_id)) {

            //get the context instance for capability checking
            $context_level = context_level_base::get_custom_context_level('class', 'block_curr_admin');
            $context_instance = get_context_instance($context_level, $cm_entity->id);

            //assign the appropriate role if the user does not have the edit capability
            if(!has_capability('block/curr_admin:class:edit', $context_instance)) {
                role_assign($CURMAN->config->default_class_role_id, $USER->id, 0, $context_instance->id);
            }
        }
    }

    /**
     * Returns the cm id corresponding to this page's entity, taking into account
     * weirdness from cancel actions
     *
     * @return  int  The appropriate id, or zero if none available
     */
    function get_cm_id() {
        $id  = $this->optional_param('courseid', 0, PARAM_INT);
        if(empty($id)) {
            //weirdness from cancel actions
            $id = $this->optional_param('id', 0, PARAM_INT);
        }

        return $id;
    }

    /**
     * Creates a new table object with specified $items and $columns.
     * @param array $items
     * @param array $columns
     */
    function create_table_object($items, $columns) {

        $parent_clusterid = $this->optional_param('parent_clusterid', 0, PARAM_INT);

        $extra_params = array();
        if(!empty($parent_clusterid)) {
            $extra_params['parent_clusterid'] = $parent_clusterid;
        }

        $page_object = $this->get_new_page($extra_params);

        return new management_page_table($items, $columns, $page_object);
    }
    
    /**
     * Determines the name of the context class that represents this page's cm entity
     * 
     * @return  string  The name of the context class that represents this page's cm entity
     * 
     * @todo            Do something less complex to determine the appropriate class
     *                  (requires page class redesign)            
     */
    function get_page_context() {
        $action = $this->optional_param('action', '', PARAM_ACTION);
        $id = $this->optional_param('id', 0, PARAM_INT);
        
        if ($action == 'default' && $id > 0) {
            //need a special case because there isn't a proper page for listing
            //classes that belong to a course
            
            //@todo  Create a proper page that lists classes that belong to a course
            return 'course';
        } else {
            return 'cmclass';
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
            if ($parts[0] == 'cmclass') {
                return $parts[0] . '-' . $parts[1];
            }
        }

        return NULL;
    }
}
?>
