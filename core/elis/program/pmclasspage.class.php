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

require_once elispm::lib('data/pmclass.class.php');
require_once elispm::lib('data/course.class.php');
require_once elispm::lib('data/classmoodlecourse.class.php');
require_once elispm::lib('data/coursetemplate.class.php');
require_once elispm::lib('data/track.class.php');
require_once elispm::lib('managementpage.class.php');
require_once elispm::lib('contexts.php');
require_once elispm::lib('moodlecourseurl.class.php');
require_once elispm::file('form/pmclassform.class.php');
require_once elispm::file('coursepage.class.php');
require_once elispm::file('instructorpage.class.php');
require_once elispm::file('studentpage.class.php');
require_once elispm::file('waitlistpage.class.php');
require_once elispm::file('rolepage.class.php');
require_once elispm::file('reportlinkspage.class.php');

class pmclasspage extends managementpage {
    var $data_class = 'pmclass';
    var $form_class = 'pmclassform';

    var $view_columns = array('idnumber');  // TODO: make crsname link to the view page for that course

    var $pagename = 'cls';
    var $section = 'curr';

    static $contexts = array();

    static function get_contexts($capability, $userid = 0) {
        if (!isset(pmclasspage::$contexts[$capability])) {
            global $USER;
            pmclasspage::$contexts[$capability] = get_contexts_by_capability_for_user('class', $capability, $userid ? $userid : $USER->id);
        }
        return pmclasspage::$contexts[$capability];
    }

    /**
     * Check the cached capabilities for the current track.
     */
    static function check_cached($capability, $id) {
        if (isset(pmclasspage::$contexts[$capability])) {
            // we've already cached which contexts the user has delete
            // capabilities in
            $contexts = pmclasspage::$contexts[$capability];
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

        // TODO: Ugly, this needs to be overhauled
        $cpage = new pmclasspage();

        if($cpage->_has_capability('elis/program:class_enrol', $classid)
           || $cpage->_has_capability('elis/program:class_enrol_userset_user', $classid)) {
            return true;
        }

        //get the context for the "indirect" capability
        $context = pm_context_set::for_user_with_capability('cluster', 'elis/program:class_enrol_userset_user', $USER->id);

        //we first need to go through tracks to get to clusters
        $track_listing = new trackassignment(array('classid' => $classid));
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
    public function _has_capability($capability, $id = null) {
        if (empty($id)) {
            $id = (isset($this) && method_exists($this, 'required_param'))
                  ? $this->required_param('id', PARAM_INT)
                  : required_param('id', PARAM_INT);
        }
        // class contexts are different -- we rely on the cache because tracks
        // require special logic
        pmclasspage::get_contexts($capability);
        $cached = pmclasspage::check_cached($capability, $id);
        if ($cached !== null) {
            return $cached;
        }
        $context = context_elis_class::instance($id);
        return has_capability($capability, $context);
    }

    public function _get_page_params() {
        return parent::_get_page_params();
    }

    public function __construct(array $params=null) {
        global $DB, $CFG;

        $reports_installed = $DB->record_exists('block', array('name' => 'php_report'));
        if ($reports_installed && file_exists($CFG->dirroot .'/blocks/php_report/php_report_base.php')) {
            require_once($CFG->dirroot .'/blocks/php_report/php_report_base.php');
        }

        $this->tabs = array(
        array('tab_id' => 'view', 'page' => get_class($this), 'params' => array('action' => 'view'), 'name' => get_string('detail', 'elis_program'), 'showtab' => true),
        array('tab_id' => 'edit', 'page' => get_class($this), 'params' => array('action' => 'edit'), 'name' => get_string('edit', 'elis_program'), 'showtab' => true, 'showbutton' => true, 'image' => 'edit'),
        array('tab_id' => 'studentpage', 'page' => 'studentpage', 'name' => get_string('enrolments', 'elis_program'), 'showtab' => true, 'showbutton' => true, 'image' => 'user'),
        array('tab_id' => 'waitlistpage', 'page' => 'waitlistpage', 'name' => get_string('waiting', 'elis_program'), 'showtab' => true, 'showbutton' => true, 'image' => 'waiting'),
        array('tab_id' => 'instructorpage', 'page' => 'instructorpage', 'name' => get_string('instructors', 'elis_program'), 'showtab' => true, 'showbutton' => true, 'image' => 'instructor'),
        array('tab_id' => 'class_rolepage', 'page' => 'class_rolepage', 'name' => get_string('roles', 'role'), 'showtab' => true, 'showbutton' => false, 'image' => 'tag'),
        array('tab_id' => 'class_enginepage', 'page' => 'class_enginepage', 'name' => get_string('results_engine', 'elis_program'), 'showtab' => true, 'showbutton' => true, 'image' => 'calculator'),
        array('tab_id' => 'delete', 'page' => get_class($this), 'params' => array('action' => 'delete'), 'name' => get_string('delete_label', 'elis_program'), 'showbutton' => true, 'image' => 'delete')
        );

        if ($reports_installed) {
            $this->tabs[] = array('tab_id' => 'class_reportlinkspage', 'page' => 'class_reportlinkspage', '', 'name' => get_string('classreportlinks', 'elis_program'),
                                  'showtab' => true, 'showbutton' => true, 'image' => 'report');
        }

        parent::__construct($params);
    }

    function can_do_view() {
        global $USER;
        $id = $this->required_param('id', PARAM_INT);
        return $this->_has_capability('elis/program:class_view')
            || instructor::user_is_instructor_of_class(pm_get_crlmuserid($USER->id), $id);
    }

    function can_do_edit() {
        global $USER;
        $id = $this->required_param('id', PARAM_INT);
        return $this->_has_capability('elis/program:class_edit')
            || instructor::user_is_instructor_of_class(pm_get_crlmuserid($USER->id), $id);
    }

    function can_do_delete() {
        return $this->_has_capability('elis/program:class_delete');
    }

    function can_do_add() {
        global $USER;
        if (!empty($USER->id)) {
            $contexts = get_contexts_by_capability_for_user('course',
                            'elis/program:class_create', $USER->id);
            return(!$contexts->is_empty());
        }
        return false;
    }

    function can_do_default() {
        $contexts = pmclasspage::get_contexts('elis/program:class_view');
        return !$contexts->is_empty();
    }

    /**
     * Constructs navigational breadcrumbs
     */
    function build_navbar_default($who = null, $addparent = true, $params = array()) {
        //get the parent courseid if possible
        $action = $this->optional_param('action', '', PARAM_CLEAN);
        $cancel = $this->optional_param('cancel', '', PARAM_CLEAN);
        $parent = $this->get_cm_id(!empty($cancel)); // TBD: false | empty($action)
        $params = array();
        $lp = true;
        if (!empty($parent) && (empty($action) || $action == 'default' ||
                                !empty($cancel))) {
            //NOT viewing the class page directly
            $params['id'] = $parent;
            $coursepage = new coursepage($params);
            $coursepage->build_navbar_view($this, 'courseid');
            $lp = false;
        }
        parent::build_navbar_default($this, $lp, $params);
    }

   /* *** TBD ***
    function build_navbar_view($who = null) {
        $crsid = $this->get_cm_id(false);
        parent::build_navbar_view($who, 'id', $crsid ? array('courseid' => $crsid): array());
    }
   */

    /**
     * override parent class, because formslib is picky
     */
    function display_savenew() {
        $target = $this->get_new_page(array('action' => 'savenew'));

        $form = new $this->form_class($target->url);

        if ($form->is_cancelled()) {
            $this->display_default();
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
            redirect($target->url, ucwords($obj->get_verbose_name())  . ' ' . $obj->__toString() . ' saved.');
        } else {
            // Validation must have failed, redisplay form
            $form->display();
        }
    }

    function display_default() {
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
            'crsname'      => array('header' => get_string('class_course', 'elis_program')),
            'idnumber'     => array('header' => get_string('class_idnumber', 'elis_program')),
            'moodlecourse' => array('header' => get_string('class_moodle_course', 'elis_program')),
            'startdate'    => array('header' => get_string('class_startdate', 'elis_program')),
            'enddate'      => array('header' => get_string('class_enddate', 'elis_program')),
            'starttime'    => array('header' => get_string('class_starttime', 'elis_program')),
            'endtime'      => array('header' => get_string('class_endtime', 'elis_program')),
            'maxstudents'  => array('header' => get_string('class_maxstudents', 'elis_program')),
        );

        //set the column data needed to maintain and display current sort
        if ($dir !== 'DESC') {
            $dir = 'ASC';
        }
        if (isset($columns[$sort])) {
            $columns[$sort]['sortable'] = $dir;
        }

        $items    = pmclass_get_listing($sort, $dir, $page*$perpage, $perpage, $namesearch, $alpha, $id, false, pmclasspage::get_contexts('elis/program:class_view'), $parent_clusterid);
        $numitems = pmclass_count_records($namesearch, $alpha, $id, false, pmclasspage::get_contexts('elis/program:class_view'), $parent_clusterid);

        pmclasspage::get_contexts('elis/program:class_edit');
        pmclasspage::get_contexts('elis/program:class_delete');

        if (!empty($id)) {
            $coursepage = new coursepage(array('id' => $id));
            $coursepage->print_tabs('pmclasspage', array('id' => $id));
        }

        $this->print_list_view($items, $numitems, $columns, $filter=null, $alphaflag=true, $searchflag=true);
        unset($items);
    }

    function do_delete() {
        global $DB;

        $id = required_param('id', PARAM_INT);
        $force = optional_param('force', 0, PARAM_INT);
        $confirm = optional_param('confirm', 0, PARAM_INT);
        $needconfirm = optional_param('needconfirm', 0, PARAM_INT);

        if($DB->count_records(student::TABLE, array('classid'=>$id)) && $force != 1 && $confirm != 1) {
            $this->display('delete');
        } else {
            parent::do_delete();
        }
    }

    function display_delete() {
        $id = required_param('id', PARAM_INT);
        $needconfirm = optional_param('needconfirm', 0, PARAM_INT);

        if ($needconfirm != 1) {
            $target = $this->get_new_page(array('action' => 'delete', 'id' => $id, 'force' => 1, 'needconfirm' => 1));
            notify(get_string('pmclass_delete_warning', 'elis_program'), 'errorbox');
            echo '<center><a href="'.$target->url.'">'.get_string('pmclass_delete_warning_continue', 'elis_program').'</a></center>';
        } else {
            $obj = $this->get_new_data_object($id);
            $this->print_delete_form($obj);
        }
    }

    /**
     * Prints the single-button form used to request the add action for a record type.
     */
    function print_add_button() {
        global $OUTPUT;

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

        $button = new single_button(new moodle_url('index.php', $options), get_string('add_class','elis_program'), 'get', array('disabled'=>false, 'title'=>get_string('add_class','elis_program'), 'id'=>''));
        echo $OUTPUT->render($button);
        echo '</div>';
    }

    /**
     * Converts add button data to data for our add form
     *
     * @return  stdClass  Form data, or null if none
     */
    function get_default_object_for_add() {
        $obj = (object) pmclass::get_default();

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
     * @uses $DB
     * @uses $USER
     */
    function after_cm_entity_add($cm_entity) {
        global $DB, $USER;

        //make sure a valid role is set
        if(!empty(elis::$config->elis_program->default_class_role_id) && $DB->record_exists('role', array('id' => elis::$config->elis_program->default_class_role_id))) {

            //get the context instance for capability checking
            $context_instance = context_elis_class::instance($cm_entity->id);

            //assign the appropriate role if the user does not have the edit capability
            if (!has_capability('elis/program:class_edit', $context_instance)) {
                role_assign(elis::$config->elis_program->default_class_role_id, $USER->id, $context_instance->id);
            }
        }
    }

    /**
     * Returns the cm id corresponding to this page's entity, taking into account
     * weirdness from cancel actions
     *
     * @return  int  The appropriate id, or zero if none available
     */
    function get_cm_id($check_crs = true) {
        $id = $this->optional_param('courseid', 0, PARAM_INT);
        if ($check_crs && empty($id)) {
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
        $crsid = $this->get_cm_id();
        $parent_clusterid = $this->optional_param('parent_clusterid', 0, PARAM_INT);
        $extra_params = array();
        if ($crsid) {
            $extra_params['id'] = $crsid;
        }
        if (!empty($parent_clusterid)) {
            $extra_params['parent_clusterid'] = $parent_clusterid;
        }

        $page_object = $this->get_new_page($extra_params);
        return new management_page_table($items, $columns, $page_object, $extra_params);
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
            return 'pmclass';
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
            if ($parts[0] == 'pmclass') {
                return $parts[0] . '-' . $parts[1];
            }
        }

        return NULL;
    }
}
