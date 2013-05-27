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

defined('MOODLE_INTERNAL') || die();

require_once(elispm::file('clustertrackpage.class.php'));
require_once(elispm::lib('deepsightpage.class.php'));
require_once(elispm::lib('managementpage.class.php'));
require_once(elispm::lib('data/clustercurriculum.class.php'));
require_once(elispm::lib('data/userset.class.php'));
require_once(elispm::lib('contexts.php'));
require_once(elispm::file('form/usersetform.class.php'));
require_once(elis::plugin_file('usersetenrol_manual', 'usersetassignmentpage.class.php'));

/**
 * Page to manage user subsets.
 */
class usersetsubusersetpage extends deepsightpage {
    /**
     * @var string A unique name for the page.
     */
    public $pagename = 'clstsub';

    /**
     * @var string The section of the page.
     */
    public $section = 'users';

    /**
     * @var string The page to get tabs from.
     */
    public $tab_page = 'usersetpage';

    /**
     * @var string The main data class.
     */
    public $data_class = 'userset';

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
        $table = new deepsight_datatable_usersetsubuserset_assigned($DB, 'assigned', $endpoint, $uniqid);
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
        $table = new deepsight_datatable_usersetsubuserset_available($DB, 'unassigned', $endpoint, $uniqid);
        $table->set_usersetid($usersetid);
        return $table;
    }

    /**
     * Whether the user can view subsets.
     * @return bool Whether the user has permission.
     */
    public function can_do_default() {
        $id = $this->required_param('id', PARAM_INT);
        $requiredperms = array('elis/program:userset_view');
        return $this->has_perms_for_element($requiredperms, 'cluster', $id);
    }

    /**
     * Whether the user can move existing usersets to a subset of the current userset.
     * @return bool Whether user has permission.
     */
    public function can_do_add() {
        $id = $this->required_param('id', PARAM_INT);
        $requiredperms = array('elis/program:userset_edit');
        return $this->has_perms_for_element($requiredperms, 'cluster', $id);
    }

    /**
     * Permission for this action is handled at the action-object level.
     * @return bool true
     */
    public function can_do_action_usersetsubuserset_makesubset() {
        return true;
    }

    /**
     * Display user subsets
     */
    public function display_default() {
        $this->print_add_button();
        parent::display_default();
    }

    /**
     * Prints the single-button form used to request the add action for a record type.
     */
    public function print_add_button() {
        global $OUTPUT;

        $id = required_param('id', PARAM_INT);
        $targetpage = new usersetpage(array('action' => 'add', 'parent' => $id));
        if (!$targetpage->can_do('add')) {
            return;
        }

        $addbutton = $OUTPUT->single_button($targetpage->url, get_string("add_{$this->data_class}", 'elis_program'), 'get');
        echo html_writer::tag('div', $addbutton, array('style' => 'text-align: center'));
    }
}

class usersetpage extends managementpage {
    var $data_class = 'userset';
    var $form_class = 'usersetform';
    var $pagename = 'clst';

    var $section = 'users';

    var $view_columns = array('name');

    static $contexts = array();

    static function get_contexts($capability) {
        if (!isset(self::$contexts[$capability])) {
            global $USER;
            self::$contexts[$capability] = get_contexts_by_capability_for_user('cluster', $capability, $USER->id);
        }
        return self::$contexts[$capability];
    }

    /**
     * Check the cached capabilities for the current cluster.
     */
    static function check_cached($capability, $id) {
        if (isset(self::$contexts[$capability])) {
            // we've already cached which contexts the user has
            // capabilities in
            $contexts = self::$contexts[$capability];
            return $contexts->context_allowed($id, 'cluster');
        }
        return null;
    }

    /**
     * Determines whether the current user is allowed to enrol users into the provided cluster
     *
     * @param   int      $clusterid  The id of the cluster we are checking permissions on
     *
     * @return  boolean              Whether the user is allowed to enrol users into the cluster
     *
     */
    static function can_enrol_into_cluster($clusterid) {
        global $USER;

        //check the standard capability
        $page = new usersetpage();
        if ($page->_has_capability('elis/program:userset_enrol', $clusterid)) {
            return true;
        }

      /* TBD: the folowing commented-out code was removed for ELIS-3846
        $cluster = new userset($clusterid);
        $cluster->load();  // ELIS-3848 Needed otherwise the 'parent' property is not set =(

        if (!empty($cluster->parent)) {
            //check to see if the current user has the secondary capability anywhere up the cluster tree
            $contexts = pm_context_set::for_user_with_capability('cluster', 'elis/program:userset_enrol_userset_user', $USER->id);
            return $contexts->context_allowed($clusterid, 'cluster');
        }
      */

        //check to see if the current user has the secondary capability anywhere up the cluster tree
        $contexts = pm_context_set::for_user_with_capability('cluster', 'elis/program:userset_enrol_userset_user', $USER->id);
        return $contexts->context_allowed($clusterid, 'cluster');
    }

    /**
     * Check if the user has the given capability for the requested cluster
     */
    function _has_capability($capability, $id = null) {
        $id = $id ? $id : $this->required_param('id', PARAM_INT);
        $cached = self::check_cached($capability, $id);
        if ($cached !== null) {
            return $cached;
        }
        $context = context_elis_userset::instance($id);
        return has_capability($capability, $context);
    }

    /**
     * Constructor
     * @param array $params An array of parameters for the page.
     */
    public function __construct(array $params=null) {
        $this->tabs = array(
                array(
                    'tab_id' => 'view',
                    'page' => 'usersetpage',
                    'params' => array('action' => 'view'),
                    'name' => get_string('detail', 'elis_program'),
                    'showtab' => true
                ),
                array(
                    'tab_id' => 'edit',
                    'page' => 'usersetpage',
                    'params' => array('action' => 'edit'),
                    'name' => get_string('edit', 'elis_program'),
                    'showtab' => true,
                    'showbutton' => true,
                    'image' => 'edit'
                ),
                array(
                    'tab_id' => 'usersetsubusersetpage',
                    'page' => 'usersetsubusersetpage',
                    'params' => array(),
                    'name' => get_string('usersubsets', 'elis_program'),
                    'showtab' => true,
                    'showbutton' => true,
                    'image' => 'cluster'
                ),
                array(
                    'tab_id' => 'clustertrackpage',
                    'page' => 'clustertrackpage',
                    'name' => get_string('tracks', 'elis_program'),
                    'showtab' => true,
                    'showbutton' => true,
                    'image' => 'track'
                ),
                array(
                    'tab_id' => 'clusteruserpage',
                    'page' => 'clusteruserpage',
                    'name' => get_string('users', 'elis_program'),
                    'showtab' => true,
                    'showbutton' => true,
                    'image' => 'user'
                ),
                array(
                    'tab_id' => 'clustercurriculumpage',
                    'page' => 'clustercurriculumpage',
                    'name' => get_string('curricula', 'elis_program'),
                    'showtab' => true,
                    'showbutton' => true,
                    'image' => 'curriculum'
                ),
                array(
                    'tab_id' => 'cluster_rolepage',
                    'page' => 'cluster_rolepage',
                    'name' => get_string('roles', 'role'),
                    'showtab' => true,
                    'showbutton' => false,
                    'image' => 'tag'
                ),
                array(
                    'tab_id' => 'delete',
                    'page' => 'usersetpage',
                    'params' => array('action' => 'delete'),
                    'name' => get_string('delete_label', 'elis_program'),
                    'showbutton' => true,
                    'image' => 'delete'
                ),
        );

        parent::__construct($params);
    }

    function can_do_view() {
        $id = $this->required_param('id', PARAM_INT);
        if ($this->_has_capability('elis/program:userset_view')) {
            return true;
        }

        /*
         * Start of cluster hierarchy extension
         */

        $viewable_clusters = userset::get_viewable_clusters();
        $contextset = pm_context_set::for_user_with_capability('cluster', 'elis/program:userset_view');
        return in_array($id, $viewable_clusters)
            || userset::exists(array(new usersubset_filter('id', new field_filter('id', $id)),
                                     $contextset->get_filter('id')))
            || userset::exists(array(new usersubset_filter('id', $contextset->get_filter('id')),
                                     new field_filter('id', $id)));

        /*
         * End of cluster hierarchy extension
         */
    }

    function can_do_edit() {
        return $this->_has_capability('elis/program:userset_edit');
    }

    function can_do_subcluster() {
        //obtain the contexts where editing is allowed for the subcluster
        $subclusterid = $this->required_param('subclusterid', PARAM_INT);
        $context = context_elis_userset::instance($subclusterid);

        //make sure editing is allowed on both clusters
        return $this->_has_capability('elis/program:userset_edit') && has_capability('elis/program:userset_edit', $context);
    }

    function can_do_delete() {
        return $this->_has_capability('elis/program:userset_delete');
    }

    function can_do_confirm() {
        return $this->can_do_delete();
    }

    function can_do_add() {
        $parent = ($this->optional_param('id', 0, PARAM_INT))
                ? $this->optional_param('id', 0, PARAM_INT)
                : $this->optional_param('parent', 0, PARAM_INT);

        if ($parent) {
            $context = context_elis_userset::instance($parent);
        } else {
            $context = get_context_instance(CONTEXT_SYSTEM);
        }

        return has_capability('elis/program:userset_create', $context);
    }

    /**
     * Dummy can_do method for viewing a curriculum report (needed for the
     * cluster tree parameter for reports)
     */
    function can_do_viewreport() {
        global $CFG, $DB;

        $id = $this->required_param('id', PARAM_INT);

        //needed for execution mode constants
        require_once($CFG->dirroot . '/blocks/php_report/php_report_base.php');

        //check if we're scheduling or viewing
        $execution_mode = $this->optional_param('execution_mode', php_report::EXECUTION_MODE_SCHEDULED, PARAM_INT);

        //check the correct capability
        $capability = ($execution_mode == php_report::EXECUTION_MODE_SCHEDULED) ? 'block/php_report:schedule' : 'block/php_report:view';
        if ($this->_has_capability($capability)) {
            return true;
        }

        /*
         * Start of cluster hierarchy extension
         */
        $viewable_clusters = userset::get_viewable_clusters($capability);

        //if the user has no additional access through parent clusters, then they can't view this cluster
        if (empty($viewable_clusters)) {
            return false;
        }

        $like_clause = $DB->sql_like('child_context.path', '?');
        $parent_path = $DB->sql_concat('parent_context.path', "'/%'");

        list($in_clause, $params) = $DB->get_in_or_equal($viewable_clusters);

        //determine if this cluster is the parent of some accessible child cluster
        $sql = "SELECT parent_context.instanceid
                FROM {context} parent_context
                JOIN {context} child_context
                  ON child_context.instanceid {$in_clause}
                  AND {$like_clause}
                  AND parent_context.contextlevel = ".CONTEXT_ELIS_USERSET."
                  AND child_context.contextlevel = ".CONTEXT_ELIS_USERSET."
                  AND parent_context.instanceid = {$id}";

        $params = array_merge($params, array($parent_path, $cluster_context_level, $cluster_context_level, $id));

        return $DB->record_exists_sql($sql, $params);

        /*
         * End of cluster hierarchy extension
         */
    }

    function can_do_default() {
        $parent = $this->optional_param('id', 0, PARAM_INT);
        if ($parent) {
            return $this->can_do_view();
        }
        $contexts = self::get_contexts('elis/program:userset_view');
        return !$contexts->is_empty();
    }

    function build_navbar_view($who = null, $id_param = 'id', $extra_params = array()) {
        // cluster name is already added by build_navbar_default, so don't
        // print it again
        return $this->build_navbar_default();
    }

    public function build_navbar_default($who = null, $addparent = true, $params = array()) {
        global $CFG, $DB;

        parent::build_navbar_default();

        // add cluster hierarchy if cluster defined
        $id = $this->optional_param('id', 0, PARAM_INT);
        if ($id) {
            $context = context_elis_userset::instance($id);
            $ancestorids = substr(str_replace('/',',',$context->path),3);
            $sql = "SELECT cluster.*
                    FROM {context} ctx
                    JOIN {" . userset::TABLE . "} cluster ON ctx.instanceid = cluster.id
                   WHERE ctx.id IN ($ancestorids) AND ctx.contextlevel = ".CONTEXT_ELIS_USERSET."
                   ORDER BY ctx.depth";
            $ancestors = $DB->get_recordset_sql($sql);
            foreach ($ancestors as $ancestor) {
                $url = $this->get_new_page(array('action' => 'view',
                                                 'id' => $ancestor->id), true)->url;
                $this->navbar->add($ancestor->name, $url);
            }
        }
    }

    public function get_page_title_default() {
        if (($id = $this->optional_param('id', '', PARAM_INT))) {
            return parent::get_page_title_view();
        } else {
            return parent::get_page_title_default();
        }
    }

    public function get_navigation_view() {
        return $this->get_navigation_default();
    }

    function display_default() {
        global $OUTPUT;

        // Get parameters
        $sort         = optional_param('sort', 'name', PARAM_ALPHA);
        $dir          = optional_param('dir', 'ASC', PARAM_ALPHA);

        $page         = optional_param('page', 0, PARAM_INT);
        $perpage      = optional_param('perpage', 30, PARAM_INT);        // how many per page

        $namesearch   = trim(optional_param('search', '', PARAM_TEXT));
        $alpha        = optional_param('alpha', '', PARAM_ALPHA);

        $parent = $this->optional_param('id', 0, PARAM_INT);
        $classification = $this->optional_param('classification', NULL, PARAM_SAFEDIR);

        if ($parent) {
            $this->print_tabs('subclusters', array('id' => $parent));
        }

        // Define columns
        $columns = array(
            'name' => array('header' => get_string('userset_name','elis_program')),
            'display' => array('header' => get_string('userset_description','elis_program')),
        );

        // set sorting
        if ($dir !== 'DESC') {
            $dir = 'ASC';
        }
        if (isset($columns[$sort])) {
            $columns[$sort]['sortable'] = $dir;
        } else {
            $sort = 'defaultsortcolumn';
            $columns[$sort]['sortable'] = $dir;
        }

        $extrafilters = array('contexts' => self::get_contexts('elis/program:userset_view'),
                              'parent' => $parent,
                              'classification' => $classification);
        $items = cluster_get_listing($sort, $dir, $page*$perpage, $perpage, $namesearch, $alpha, $extrafilters);
        $numitems = cluster_count_records($namesearch, $alpha, $extrafilters);

        self::get_contexts('elis/program:userset_edit');
        self::get_contexts('elis/program:userset_delete');

        $this->print_list_view($items, $numitems, $columns, $filter=null, $alphaflag=true, $searchflag=true);

        if ($this->optional_param('id', 0, PARAM_INT)) {
            //get the non-parent clusters that are accessible based on the edit capability
            $contexts = usersetpage::get_contexts('elis/program:userset_edit');
            $non_parent_clusters = cluster_get_possible_sub_clusters($this->optional_param('id', 0, PARAM_INT), $contexts);

            //display the dropdown if there are one or more available clusters
            if (count($non_parent_clusters) > 0) {
                echo html_writer::start_tag('div', array('align' => 'center'));
                echo get_string('cluster_subcluster_prompt','elis_program') . ': ';
                $url = $this->get_new_page(array('action'=>'subcluster','id'=>$this->optional_param('id', 0, PARAM_INT)))->url . '&amp;subclusterid=';
                echo $OUTPUT->single_select($url, 'subclusterid', $non_parent_clusters);
                echo html_writer::end_tag('div');
            }
        }
    }

    /**
     * Handler for the delete action.  Deletes the record identified by the
     * 'id' parameter, if the confirm parameter is set.
     *
     * Modified from the default handler to pass in whether or not subclusters
     * should be deleted or promoted.
     */
    public function do_delete() {
        global $CFG;

        if (!optional_param('confirm', 0, PARAM_INT)) {
            return $this->display('delete');
        }

        $deletesubs = optional_param('deletesubs', 0, PARAM_INT);

        require_sesskey();

        $id = required_param('id', PARAM_INT);

        //handling of cancel case
        $view_params = array('id'     => $id,
                             'action' => 'view');
        $target_page = $this->get_new_page($view_params);
        $form = new usersetdeleteform();

        if ($form->is_cancelled()) {
            //cancelled, so redirect back to view page
            redirect($target_page->url, get_string('delete_cancelled', 'elis_program'));
        }

        $obj = $this->get_new_data_object($id);
        $obj->load(); // force load, so that the confirmation notice has something to display
        $obj->deletesubs = $deletesubs;
        $obj->delete();

        $returnurl = optional_param('return_url', null, PARAM_URL);
        if ($returnurl === null) {
            $target_page = $this->get_new_page(array(), true);
            $returnurl = $target_page->url;
        } else {
            $returnurl = $CFG->wwwroot.$returnurl;
        }

        redirect($returnurl, get_string('notice_'.get_class($obj).'_deleted', 'elis_program', $obj->to_object()));
    }

    /**
     * Handler for the confirm action.  Assigns a child cluster to specified cluster.
     */
    function do_subcluster() {
        global $CFG;

        $id = $this->required_param('id',PARAM_INT);
        $target_page = $this->get_new_page(array('id'=>$id), true);
        $sub_cluster_id = $this->required_param('subclusterid',PARAM_INT);

        $cluster = new userset($sub_cluster_id);
        $cluster->parent = $id;
        $cluster->save();

        redirect($target_page->url, get_string('cluster_assigned','elis_program'));
    }

    /**
     * Prints a deletion confirmation form.
     * @param $obj record whose deletion is being confirmed
     */
    function print_delete_form($obj) {
        global $DB;
        if (($count = userset::count(new field_filter('parent', $obj->id)))) {
            // cluster has sub-clusters, so ask the user if they want to
            // promote or delete the sub-clusters
            $a = new stdClass;
            $a->name = $obj;
            $a->subclusters = $count;
            $context = context_elis_userset::instance($obj->id);
            $like = $DB->sql_like('path', '?');
            $a->descendants = $DB->count_records_select('context',$DB->sql_like('path', '?'), array("{$context->path}/%")) - $a->subclusters;
            print_string($a->descendants ? 'confirm_delete_with_usersubsets_and_descendants' : 'confirm_delete_with_usersubsets', 'elis_program', array('name'=>$obj->name, 'subclusters'=>$count));
            $target = $this->get_new_page(array('action' => 'delete', 'confirm'=>'1'));
            $form = new usersetdeleteform($target->url, array('obj' => $obj, 'a' => $a));
            $form->display();
        } else {
            parent::print_delete_form($obj);
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

        $options = array('action' => 'add');
        $parent = $this->optional_param('id', 0, PARAM_INT);
        if ($parent) {
            $options['parent'] = $parent;
        }
        $target_page = $this->get_new_page($options, true);
        $url = $target_page->url;

        echo html_writer::tag('div', $OUTPUT->single_button($url, get_string("add_{$this->data_class}",'elis_program'), 'get'), array('style' => 'text-align: center'));
    }

    function get_default_object_for_add() {
        $parent = $this->optional_param('parent', 0, PARAM_INT);
        if ($parent) {
            $obj = new stdClass;
            $obj->parent = $parent;
            if ($parent) {
                require_once(elis::plugin_file('pmplugins_userset_classification','usersetclassification.class.php'));
                require_once(elis::plugin_file('pmplugins_userset_classification','lib.php'));
                if ($classification = usersetclassification::get_for_cluster($parent)) {
                    $fieldname = 'field_'.USERSET_CLASSIFICATION_FIELD;
                    if ($classification->param_child_classification) {
                        $obj->$fieldname = $classification->param_child_classification;
                    } else {
                        $obj->$fieldname = $classification->shortname;
                    }

                    //default groups and groupings settings
                    if ($classification->param_autoenrol_groups) {
                        $obj->field_userset_group = $classification->param_autoenrol_groups;
                    }
                    if ($classification->param_autoenrol_groupings) {
                        $obj->field_userset_groupings = $classification->param_autoenrol_groupings;
                    }
                }
            }
            return $obj;
        } else {
            return NULL;
        }
    }

    /**
     * Hook that gets called after a CM entity is added through this page
     * (Note: this function should only use the id field from the supplied cm entity
     *  as the rest of the data is not guaranteed to be there)
     *
     * @param  object  $cm_entity  The CM entity added
     */
    function after_cm_entity_add($cm_entity) {
        global $USER, $DB;

        //make sure a valid role is set
        if(!empty(elis::$config->elis_program->default_cluster_role_id)
           && $DB->record_exists('role', array('id' => elis::$config->elis_program->default_cluster_role_id))) {

            //get the context instance for capability checking
            $context_instance = context_elis_userset::instance($cm_entity->id);

            //assign the appropriate role if the user does not have the edit capability
            if (!has_capability('elis/program:userset_edit', $context_instance)) {
                role_assign(elis::$config->elis_program->default_cluster_role_id, $USER->id, $context_instance->id);
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
            if ($parts[0] == 'userset') {
                return $parts[0] . '-' . $parts[1];
            }
        }

        return NULL;
    }
}
