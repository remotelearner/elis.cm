<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2010 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2008-2010 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once (CURMAN_DIRLOCATION . '/lib/cluster.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/managementpage.class.php');
require_once (CURMAN_DIRLOCATION . '/clustertrackpage.class.php');
require_once (CURMAN_DIRLOCATION . '/clustercurriculumpage.class.php');
require_once (CURMAN_DIRLOCATION . '/cluster/manual/assignpage.class.php');
require_once (CURMAN_DIRLOCATION . '/form/clusterform.class.php');
require_once (CURMAN_DIRLOCATION . '/rolepage.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/contexts.php');

class clusterpage extends managementpage {
    var $data_class = 'cluster';
    var $form_class = 'clusterform';
    var $pagename = 'clst';

    var $section = 'users';

    var $view_columns = array('name');

    static $contexts = array();

    static function get_contexts($capability) {
        if (!isset(clusterpage::$contexts[$capability])) {
            global $USER;
            clusterpage::$contexts[$capability] = get_contexts_by_capability_for_user('cluster', $capability, $USER->id);
        }
        return clusterpage::$contexts[$capability];
    }

    /**
     * Check the cached capabilities for the current cluster.
     */
    static function check_cached($capability, $id) {
        if (isset(clusterpage::$contexts[$capability])) {
            // we've already cached which contexts the user has
            // capabilities in
            $contexts = clusterpage::$contexts[$capability];
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
        if(clusterpage::_has_capability('block/curr_admin:cluster:enrol', $clusterid)) {
            return true;
        }

        $cluster = new cluster($clusterid);
        if(!empty($cluster->parent)) {
            //check to see if the current user has the secondary capability anywhere up the cluster tree
            $contexts = cm_context_set::for_user_with_capability('cluster', 'block/curr_admin:cluster:enrol_cluster_user', $USER->id);
            return $contexts->context_allowed($clusterid, 'cluster');
        }

        return false;
    }

    /**
     * Check if the user has the given capability for the requested cluster
     */
    function _has_capability($capability, $id = null) {
        $id = $id ? $id : $this->required_param('id', PARAM_INT);
        $cached = clusterpage::check_cached($capability, $id);
        if ($cached !== null) {
            return $cached;
        }
        $context = get_context_instance(context_level_base::get_custom_context_level('cluster', 'block_curr_admin'), $id);
        return has_capability($capability, $context);
    }

    public function __construct($params=false) {
        $this->tabs = array(
        array('tab_id' => 'view', 'page' => 'clusterpage', 'params' => array('action' => 'view'), 'name' => get_string('detail','block_curr_admin'), 'showtab' => true),
        array('tab_id' => 'edit', 'page' => 'clusterpage', 'params' => array('action' => 'edit'), 'name' => get_string('edit','block_curr_admin'), 'showtab' => true, 'showbutton' => true, 'image' => 'edit.gif'),
        array('tab_id' => 'subclusters', 'page' => 'clusterpage', 'params' => array(), 'name' => get_string('subclusters','block_curr_admin'), 'showtab' => true),

        array('tab_id' => 'clustertrackpage', 'page' => 'clustertrackpage', 'name' => get_string('tracks','block_curr_admin'), 'showtab' => true, 'showbutton' => true, 'image' => 'track.gif'),
        array('tab_id' => 'clusteruserpage', 'page' => 'clusteruserpage', 'name' => get_string('users','block_curr_admin'), 'showtab' => true, 'showbutton' => true, 'image' => 'user.gif'),
        array('tab_id' => 'clustercurriculumpage', 'page' => 'clustercurriculumpage', 'name' => get_string('curricula','block_curr_admin'), 'showtab' => true, 'showbutton' => true, 'image' => 'curriculum.gif'),
        array('tab_id' => 'cluster_rolepage', 'page' => 'cluster_rolepage', 'name' => get_string('roles', 'role'), 'showtab' => true, 'showbutton' => false, 'image' => 'tag.gif'),

        array('tab_id' => 'delete', 'page' => 'clusterpage', 'params' => array('action' => 'delete'), 'name' => get_string('delete_label','block_curr_admin') , 'showbutton' => true, 'image' => 'delete.gif'),
        );

        parent::__construct($params);
    }

    function can_do_view() {
        global $USER, $CURMAN;

        $id = $this->required_param('id', PARAM_INT);
        if ($this->_has_capability('block/curr_admin:cluster:view')) {
            return true;
        }

         /*
         * Start of cluster hierarchy extension
         */
        $viewable_clusters = cluster::get_viewable_clusters();

        $cluster_context_level = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');

        $like = sql_ilike();
        $parent_path = sql_concat('parent_context.path', "'/%'");

        //if the user has no additional access through parent clusters, then they can't view this cluster
        if (empty($viewable_clusters)) {
            return false;
        }

        $cluster_filter = implode(',', $viewable_clusters);

        //determine if this cluster is the parent of some accessible child cluster
        $sql = "SELECT parent_context.instanceid
                FROM {$CURMAN->db->prefix_table('context')} parent_context
                JOIN {$CURMAN->db->prefix_table('context')} child_context
                  ON child_context.path {$like} {$parent_path}
                  AND parent_context.contextlevel = {$cluster_context_level}
                  AND child_context.contextlevel = {$cluster_context_level}
                  AND child_context.instanceid IN ({$cluster_filter})
                  AND parent_context.instanceid = {$id}";

        return record_exists_sql($sql);

        /*
         * End of cluster hierarchy extension
         */
    }

    function can_do_edit() {
        return $this->_has_capability('block/curr_admin:cluster:edit');
    }

    function can_do_subcluster() {
        return $this->_has_capability('block/curr_admin:cluster:edit');
    }

    function can_do_delete() {
        return $this->_has_capability('block/curr_admin:cluster:delete');
    }

    function can_do_confirm() {
        return $this->can_do_delete();
    }

    function can_do_add() {
        $parent = ($this->optional_param('id', 0, PARAM_INT))
                ? $this->optional_param('id', 0, PARAM_INT)
                : $this->optional_param('parent', 0, PARAM_INT);

        if ($parent) {
            $level = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');
            $context = get_context_instance($level,$parent);
        } else {
            $context = get_context_instance(CONTEXT_SYSTEM);
        }

        return has_capability('block/curr_admin:cluster:create', $context);
    }

    /**
     * Dummy can_do method for viewing a curriculum report (needed for the
     * cluster tree parameter for reports)
     */
    function can_do_viewreport() {
        global $CFG;

        //needed for execution mode constants
        require_once($CFG->dirroot . '/blocks/php_report/php_report_base.php');

        //check if we're scheduling or viewing
        $execution_mode = $this->optional_param('execution_mode', php_report::EXECUTION_MODE_SCHEDULED, PARAM_INT);

        //check the correct capability
        if ($execution_mode == php_report::EXECUTION_MODE_SCHEDULED) {
            return $this->_has_capability('block/php_report:schedule');
        } else {
            return $this->_has_capability('block/php_report:view');
        }
    }

    function can_do_default() {
        $parent = $this->optional_param('id', 0, PARAM_INT);
        if ($parent) {
            return $this->can_do_view();
        }
        $contexts = clusterpage::get_contexts('block/curr_admin:cluster:view');
        return !$contexts->is_empty();
    }

    public function get_navigation_default() {
        global $CFG, $CURMAN;

        $parent = $this->optional_param('id', 0, PARAM_INT);
        $navigation = parent::get_navigation_default();
        $level = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');
        if ($parent) {
            $context = get_context_instance($level, $parent);
            $ancestorids = substr(str_replace('/',',',$context->path),1);
            $sql = "SELECT cluster.*
                    FROM {$CFG->prefix}context ctx
                    JOIN {$CURMAN->db->prefix_table(CLSTTABLE)} cluster ON ctx.instanceid = cluster.id
                   WHERE ctx.id IN ($ancestorids) AND ctx.contextlevel=$level
                   ORDER BY ctx.depth";
            $ancestors = $CURMAN->db->get_records_sql($sql);
            $ancestors = $ancestors ? $ancestors : array();
            $target = $this->get_new_page(array('action' => 'view'));
            foreach ($ancestors as $ancestor) {
                $target->params['id'] = $ancestor->id;
                $navigation[] = array('name' => htmlspecialchars($ancestor->name),
                                      'link' => $target->get_url());
            }
        }

        return $navigation;
    }

    public function get_navigation_view() {
        return $this->get_navigation_default();
    }

    function action_default() {
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
            'name' => get_string('cluster_name','block_curr_admin'),
            'display' => get_string('cluster_description','block_curr_admin'),
        );

        $extrafilters = array('contexts' => clusterpage::get_contexts('block/curr_admin:cluster:view'),
                              'parent' => $parent,
                              'classification' => $classification);
        $items = cluster_get_listing($sort, $dir, $page*$perpage, $perpage, $namesearch, $alpha, $extrafilters);
        $numitems = cluster_count_records($namesearch, $alpha, $extrafilters);

        clusterpage::get_contexts('block/curr_admin:cluster:edit');
        clusterpage::get_contexts('block/curr_admin:cluster:delete');

        $this->print_list_view($items, $numitems, $columns, $filter=null, $alphaflag=true, $searchflag=true);

        if ($this->optional_param('id', 0, PARAM_INT)) {
            echo '<div align="center">';
            echo get_string('cluster_subcluster_prompt','block_curr_admin') . ': ';
            $non_parent_clusters = cluster_get_possible_sub_clusters($this->optional_param('id', 0, PARAM_INT));
            $url = $this->get_new_page(array('action'=>'subcluster','id'=>$this->optional_param('id', 0, PARAM_INT)))->get_url() . '&amp;subclusterid=';
            popup_form($url, $non_parent_clusters, 'assignsubcluster', '', 'Choose...');
            echo '</div>';
        }
    }

    /**
     * Generic handler for the confirm action.  Deletes the record identified by 'id'.
     */
    function action_confirm() {
        global $CFG;

        $id = required_param('id', PARAM_INT);
        $confirm = required_param('confirm', PARAM_ALPHANUM);   //md5 confirmation hash
        $deletesubs = optional_param('deletesubs', 0, PARAM_INT);
        $obj = $this->get_new_data_object($id);
        $target_page = $this->get_new_page();
        $form = new clusterdeleteform($target_page->get_moodle_url(),array('obj' => $obj));

        if (md5($id) != $confirm) {
            redirect($target_page->get_url(), 'Invalid confirmation code!');
        } else if ($form->is_cancelled()) {
            redirect($target_page->get_url(), get_string('delete_cancelled','block_curr_admin'));
        } else if (!$obj->delete($deletesubs)){
            // FIXME:
            redirect($target_page->get_url(), ucwords($obj->get_verbose_name())  . ' ' . $obj->to_string() . ' ' . strtolower(get_string('not_deleted','block_curr_admin')));
        } else {
            redirect($target_page->get_url(), ucwords($obj->get_verbose_name())  . ' ' . $obj->to_string() . ' ' . strtolower(get_string('deleted','block_curr_admin')));
        }
    }

    /**
     * Handler for the confirm action.  Assigns a child cluster to specified cluster.
     */
    function action_subcluster() {
        global $CFG;

        $id = $this->required_param('id',PARAM_INT);
        $target_page = $this->get_new_page(array('id'=>$id));
        $sub_cluster_id = $this->required_param('subclusterid',PARAM_INT);

        $cluster = new cluster($sub_cluster_id);
        $cluster->parent = $id;
        $cluster->update();

        redirect($target_page->get_url(), get_string('cluster_assigned','block_curr_admin'));
    }

    /**
     * Prints a deletion confirmation form.
     * @param $obj record whose deletion is being confirmed
     */
    function print_delete_form($obj) {
        global $CURMAN;

        if ($CURMAN->db->record_exists('crlm_cluster', 'parent', $obj->id)) {
            // cluster has sub-clusters
            $a = new stdClass;
            $a->name = $obj->to_string();
            $a->subclusters = $CURMAN->db->count_records('crlm_cluster', 'parent', $obj->id);
            $context = get_context_instance(context_level_base::get_custom_context_level('cluster', 'block_curr_admin'), $obj->id);
            $like = $CURMAN->db->sql_compare();
            $a->descendants = $CURMAN->db->count_records_select('context',"path $like '{$context->path}/%'") - $a->subclusters;
            print_string($a->descendants ? 'confirm_delete_with_subclusters_and_descendants' : 'confirm_delete_with_subclusters', 'block_curr_admin', $a);
            require_once CURMAN_DIRLOCATION . '/form/clusterform.class.php';
            $target = $this->get_new_page(array('action' => 'confirm'));
            $form = new clusterdeleteform($target->get_moodle_url(), array('obj' => $obj, 'a' => $a));
            $form->display();
        } else {
            parent::print_delete_form($obj);
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

    function get_default_object_for_add() {
        $parent = $this->optional_param('parent', 0, PARAM_INT);
        if ($parent) {
            $obj = new stdClass;
            $obj->parent = $parent;
            if ($parent) {
                require_once (CURMAN_DIRLOCATION.'/plugins/cluster_classification/clusterclassification.class.php');
                require_once (CURMAN_DIRLOCATION.'/plugins/cluster_classification/lib.php');
                if ($classification = clusterclassification::get_for_cluster($parent)) {
                    $fieldname = 'field_'.CLUSTER_CLASSIFICATION_FIELD;
                    if ($classification->param_child_classification) {
                        $obj->$fieldname = $classification->param_child_classification;
                    } else {
                        $obj->$fieldname = $classification->shortname;
                    }

                    //default groups and groupings settings
                    if ($classification->param_autoenrol_groups) {
                        $obj->field_cluster_group = $classification->param_autoenrol_groups;
                    }
                    if ($classification->param_autoenrol_groupings) {
                        $obj->field_cluster_groupings = $classification->param_autoenrol_groupings;
                    }
                }
            }
            return $obj;
        } else {
            return NULL;
        }
    }

    /**
     * Prints the '1 2 3 ...' paging bar for when a query set is split across multiple pages.
     * @param $numitems total number of items in the query set
     */
    function print_paging_bar($numitems) {
        // TODO: take a queryset as an argument rather than the number of items
        $sort         = optional_param('sort', '', PARAM_ALPHA);
        $dir          = optional_param('dir', '', PARAM_ALPHA);
        $locsearch    = trim(optional_param('locsearch', '', PARAM_TEXT));
        $namesearch   = trim(optional_param('search', '', PARAM_TEXT));
        $alpha        = optional_param('alpha', '', PARAM_ALPHA);

        $page         = optional_param('page', 0, PARAM_INT);
        $perpage      = optional_param('perpage', 30, PARAM_INT);

        $params = array(
			'search' => stripslashes($namesearch),
			'locsearch' => stripslashes($locsearch),
			'alpha' => $alpha,
			'perpage' => $perpage,
			/*'namesearch' => $namesearch*/
        );

        //add the parent cluster id as a parameter
        //(0 will signal no that there is no parent cluster)
        $params['id'] = $this->optional_param('id', 0, PARAM_INT);

        if (!empty($sort)) {
            $params['sort'] = $sort;
        }
        if (!empty($sort)) {
            $params['dir'] = $dir;
        }

        $target = $this->get_new_page($params);

        print_paging_bar($numitems, $page, $perpage, $target->get_url() . '&amp;');
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
        if(!empty($CURMAN->config->default_cluster_role_id) && record_exists('role', 'id', $CURMAN->config->default_cluster_role_id)) {

            //get the context instance for capability checking
            $context_level = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');
            $context_instance = get_context_instance($context_level, $cm_entity->id);

            //assign the appropriate role if the user does not have the edit capability
            if(!has_capability('block/curr_admin:cluster:edit', $context_instance)) {
                role_assign($CURMAN->config->default_cluster_role_id, $USER->id, 0, $context_instance->id);
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
            if ($parts[0] == 'cluster') {
                return $parts[0] . '-' . $parts[1];
            }
        }

        return NULL;
    }
}
?>
