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

require_once(CURMAN_DIRLOCATION . '/lib/datarecord.class.php');
require_once(CURMAN_DIRLOCATION . '/lib/usercluster.class.php');
require_once(CURMAN_DIRLOCATION . '/lib/clusterassignment.class.php');
require_once CURMAN_DIRLOCATION . '/lib/clustercurriculum.class.php';
require_once CURMAN_DIRLOCATION . '/lib/customfield.class.php';
require_once CURMAN_DIRLOCATION . '/lib/contexts.php';

define ('CLSTTABLE', 'crlm_cluster');
define ('CLSTPROFTABLE', 'crlm_cluster_profile');

class cluster extends datarecord {
    /*
     var $id;            // INT - The data id if in the database.
     var $name;          // STRING - Textual name of the cluster.
     var $display;       // STRING - A description of the cluster.
     */

    var $verbose_name = 'cluster';

    /**
     * Constructor.
     *
     * @param $clusterdata int/object/array The data id of a data record or data elements to load manually.
     *
     */
    function cluster($clusterdata=false) {
        global $CURMAN;

        parent::datarecord();

        $this->set_table(CLSTTABLE);
        $this->add_property('id', 'int');
        $this->add_property('name', 'string');
        $this->add_property('display', 'string');
        $this->add_property('leader', 'int');
        $this->add_property('parent', 'int');
        $this->add_property('depth', 'int');

        if (is_numeric($clusterdata)) {
            $this->data_load_record($clusterdata);
        } else if (is_array($clusterdata)) {
            $this->data_load_array($clusterdata);
        } else if (is_object($clusterdata)) {
            $this->data_load_array(get_object_vars($clusterdata));
        }

        if (!empty($this->id)) {
            // custom fields
            $level = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');
            if ($level) {
                $fielddata = field_data::get_for_context(get_context_instance($level,$this->id));
                $fielddata = $fielddata ? $fielddata : array();
                foreach ($fielddata as $name => $value) {
                    $this->{"field_{$name}"} = $value;
                }
            }
        }

/*
 * profile_field1 -select box
 * profile_value1 -select box corresponding to profile_field1
 *
 * profile_field2 -select box
 * profile_value2 -select box corresponding to profile_field2
 */
        $prof_fields = $CURMAN->db->get_records(CLSTPROFTABLE, 'clusterid', $this->id, '', '*', 0, 2);

        if(!empty($prof_fields)) {
            foreach(range(1,2) as $i) {
                $profile = pos($prof_fields);

                if(!empty($profile)) {
                    $field = 'profile_field' . $i;
                    $value = 'profile_value' . $i;

                    $this->$field = $profile->fieldid;
                    $this->$value = $profile->value;
                }

                next($prof_fields);
            }
        }
    }

    public function set_from_data($data) {
        $fields = field::get_for_context_level('cluster', 'block_curr_admin');
        $fields = $fields ? $fields : array();
        foreach ($fields as $field) {
            $fieldname = "field_{$field->shortname}";
            if (isset($data->$fieldname)) {
                $this->$fieldname = $data->$fieldname;
            }
        }

        return parent::set_from_data($data);
    }


    /**
     * Perform the necessary actions required to "delete" a cluster from the system.
     *
     * @uses CURMAN
     * @uses CFG
     * @param none
     * @return bool True on success, False otherwise.
     */
    function delete($deletesubs=0) {
        global $CURMAN, $CFG;
        require_once CURMAN_DIRLOCATION . '/cluster/profile/lib.php';

        $result = true;
        $delete_ids = array();
        $promote_ids = array();
        $cluster_context_level = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');

        if ($deletesubs > 0) {
        /// Figure out all the sub-cluster ids and whether to delete or promote them
            $LIKE = $CURMAN->db->sql_compare();
            $cluster_context_instance = get_context_instance($cluster_context_level, $this->id);
            $instance_id = $cluster_context_instance->id;
            $instance_path = $cluster_context_instance->path;
            $sql = "SELECT instanceid FROM {$CFG->prefix}context
                    WHERE path {$LIKE} '{$instance_path}/%' ORDER BY instanceid DESC";
            $clusters = get_records_sql($sql);
            foreach ($clusters as $cluster) {
                if ($deletesubs == 1) {
                    // This sub-cluster will be deleted
                    $delete_ids[] = $cluster->instanceid;
                } else {
                    // This sub-cluster will be promoted
                    $promote_ids[] = $cluster->instanceid;
                }
            }
        }

        $delete_ids[] = $this->id; // The specified cluster always gets deleted

        foreach ($delete_ids as $delete_id) {
            // Cascade to regular datarecords
            $result = $result && clustercurriculum::delete_for_cluster($delete_id); // in clustercurriculum
            $result = $result && clustertrack::delete_for_cluster($delete_id); // in clustercurriculum
            $result = $result && clusterassignment::delete_for_cluster($delete_id);
            $result = $result && usercluster::delete_for_cluster($delete_id);
            $result = $result && delete_context($cluster_context_level,$delete_id);

            // Cascade to all plugins
            $plugins = $this->get_plugins();
            foreach ($plugins as $plugin) {
                require_once CURMAN_DIRLOCATION . '/cluster/' . $plugin . '/lib.php';
                $result = $result && call_user_func('cluster_' . $plugin . '_delete_for_cluster', $delete_id);
            }

            $result = $result && datarecord::data_delete_record($delete_id);  // this record
        }

        if (count($promote_ids) > 0) {
            foreach ($promote_ids as $promote_id) {
                $cluster_data = get_record(CLSTTABLE,'id',$promote_id);

                $lower_depth = $cluster_data->depth - 1;
                $select = "id='{$cluster_data->parent}'";
                $parent_cnt = $CURMAN->db->count_records_select(CLSTTABLE, $select);

                $newclusterdata = new stdClass;
                $newclusterdata->id = $promote_id;
                if ($parent_cnt < 1) {
                /// Parent not found so this cluster will be top-level
                    $newclusterdata->parent = 0;
                    $newclusterdata->depth = 1;
                } else {
                /// A child cluster found so lets lower the depth
                    $newclusterdata->depth = $lower_depth;
                }
                $result = update_record(CLSTTABLE, $newclusterdata);

                $cluster_context_level = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');
                $sql = "UPDATE {$CFG->prefix}context
                        SET depth=0, path=NULL
                        WHERE contextlevel='{$cluster_context_level}' AND instanceid='{$promote_id}'";
                $feedback = "";
                execute_sql($sql, $feedback);
            }
            build_context_path(); // Re-build the context table for all sub-clusters
        }

        return $result;
    }

    function add() {
        // figure out the right depth for the cluster
        if (!isset($this->depth) || !$this->depth) {
            if ($this->parent == 0) {
                $this->depth = 1;
            } else {
                $parent = new cluster($this->parent);
                $this->depth = $parent->depth + 1;
            }
        }

        $result = parent::add();

        $plugins = cluster::get_plugins();
        foreach ($plugins as $plugin) {
            require_once CURMAN_DIRLOCATION . '/cluster/' . $plugin . '/lib.php';
            call_user_func('cluster_' . $plugin . '_update', $this);
        }

        $result = $result && field_data::set_for_context_from_datarecord('cluster', $this);

        //signal that the cluster was created
        events_trigger('crlm_cluster_created', $this);

        return $result;
    }

    function update() {
        global $CFG;
        global $CURMAN;

        $old = new cluster($this->id);
        $parent_obj = new cluster($this->parent);
        $this->depth = empty($parent_obj->depth) ? 1 : ($parent_obj->depth + 1);

        $result = parent::update();

        if ($this->parent != $old->parent) {
            $cluster_context_level = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');
            $cluster_context_instance = get_context_instance($cluster_context_level, $this->id);

            // find all subclusters and adjust their depth
            $delta_depth = $this->depth - $old->depth;
            $sql = "UPDATE {$CURMAN->db->prefix_table(CLSTTABLE)}
                       SET depth = depth + {$delta_depth}
                     WHERE id IN (SELECT instanceid
                                    FROM {$CURMAN->db->prefix_table('context')}
                                   WHERE contextlevel = {$cluster_context_level}
                                     AND path LIKE '{$cluster_context_instance->path}/%')";
            execute_sql($sql, false);

            // Blank out the depth and path for associated records and child records in context table
            $sql = "UPDATE {$CFG->prefix}context
                       SET depth=0, path=NULL
                     WHERE id={$cluster_context_instance->id} OR path LIKE '{$cluster_context_instance->path}/%'";
            execute_sql($sql, false);

            // Rebuild any blanked out records in context table
            build_context_path();
        }

        $plugins = cluster::get_plugins();
        foreach ($plugins as $plugin) {
            require_once CURMAN_DIRLOCATION . '/cluster/' . $plugin . '/lib.php';
            call_user_func('cluster_' . $plugin . '_update', $this);
        }

        $result = $result && field_data::set_for_context_from_datarecord('cluster', $this);

        events_trigger('crlm_cluster_updated', $this);

        return $result;
    }

    public function to_string() {
        return $this->name;
    }

    static function cluster_assigned_handler($eventdata) {
        global $CURMAN, $CFG;

        // assign user to the curricula associated with the cluster
        /**
        * @todo we may need to change this if associating a user with a
        * curriculum does anything more complicated
        */
        require_once CURMAN_DIRLOCATION . '/lib/user.class.php';
        require_once CURMAN_DIRLOCATION . '/lib/clustercurriculum.class.php';
        require_once CURMAN_DIRLOCATION . '/lib/curriculumstudent.class.php';
        require_once CURMAN_DIRLOCATION . '/lib/usertrack.class.php';

        $db = $CURMAN->db;
        $timenow = time();
        $sql = 'INSERT INTO ' . $db->prefix_table(CURASSTABLE) . ' '
        . '(userid, curriculumid, timecreated, timemodified) '
        . 'SELECT DISTINCT u.id, clucur.curriculumid, ' . $timenow . ', ' . $timenow . ' '
        . 'FROM ' . $db->prefix_table(CLSTUSERTABLE) . ' clu '
        . 'INNER JOIN ' . $db->prefix_table(USRTABLE) . ' u ON u.id = clu.userid '
        . 'INNER JOIN ' . $db->prefix_table(CLSTCURTABLE) . ' clucur ON clucur.clusterid = clu.clusterid '
        . 'LEFT OUTER JOIN ' . $db->prefix_table(CURASSTABLE) . ' ca ON ca.userid = u.id AND ca.curriculumid = clucur.curriculumid '
        . 'WHERE clu.clusterid = \'' . $eventdata->clusterid . '\' AND u.id = \'' . $eventdata->userid . '\' AND ca.curriculumid IS NULL '
        . 'AND clucur.autoenrol = 1';
        $db->execute_sql($sql,false);

        // enrol user in associated tracks if autoenrol flag is set
        if ($eventdata->autoenrol) {
            $tracks = clustertrack::get_tracks($eventdata->clusterid);
            if ($tracks) {
                foreach ($tracks as $track) {
                    //make sure the cluster-track association is set up for autoenrol
                    if(record_exists(CLSTTRKTABLE, 'clusterid', $eventdata->clusterid, 'trackid', $track->trackid, 'autoenrol', 1)) {
                        usertrack::enrol($eventdata->userid, $track->trackid);
                    }
                }
            }
        }

        return true;
    }

    static function cluster_deassigned_handler($eventdata) {
        return true;
    }

    static function get_plugins() {
        return get_list_of_plugins('curriculum/cluster');
    }

    /**
     * Update cluster assignments after a plugin has (de)assigned users.
     *
     * @param int clusterid only update cluster assignments for this cluster
     * @param int userid only update cluster assignments for this user
     */
    static function cluster_update_assignments($clusterid=null, $userid=null) {
        global $CURMAN;

        // these bits of the query can be reused
        $select  = 'SELECT a.id, a.clusterid, a.userid, MAX(a.autoenrol) AS autoenrol, MAX(a.leader) AS leader ';
        $where   = 'WHERE b.clusterid IS NULL';
        $groupby = ' GROUP BY a.clusterid, a.userid, a.id';
        if (!empty($clusterid)) {
            $where .= ' AND a.clusterid=' . $clusterid;
        }
        if (!empty($userid)) {
            $where .= ' AND a.userid=' . $userid;
        }

        // users that have been assigned by a plugin to the cluster, but are not
        // really assigned yet -- add the assignment
        $from = 'FROM ' . $CURMAN->db->prefix_table(CLSTASSTABLE) . ' a '
        . 'LEFT OUTER JOIN ' . $CURMAN->db->prefix_table(CLSTUSERTABLE) . ' b ON a.clusterid = b.clusterid AND a.userid = b.userid ';
        $newusers = $CURMAN->db->get_records_sql($select.$from.$where.$groupby);
        if (!empty($newusers)) {
            foreach ($newusers as $user) {
                cluster_assign_to_user($user->clusterid, $user->userid, $user->autoenrol, $user->leader);
            }
        }

        // users that are assigned to the cluster, but no plugin has assigned them
        // (any more) -- remove the assignment
        $from = 'FROM ' . $CURMAN->db->prefix_table(CLSTUSERTABLE) . ' a '
        . 'LEFT OUTER JOIN ' . $CURMAN->db->prefix_table(CLSTASSTABLE) . ' b ON a.clusterid = b.clusterid AND a.userid = b.userid ';
        $oldusers = $CURMAN->db->get_records_sql($select.$from.$where.$groupby);
        if (!empty($oldusers)) {
            foreach ($oldusers as $user) {
                cluster_deassign_user($user->clusterid, $user->userid);
            }
        }
    }

    /**
     * Returns an array of cluster ids that are children of the supplied cluster and
     * the current user has access to enrol users into
     *
     * @param   int        $clusterid  The cluster whose children we care about
     * @return  int array              The array of accessible cluster ids
     */
    public static function get_allowed_clusters($clusterid) {
        global $USER, $CURMAN;

        $context = cm_context_set::for_user_with_capability('cluster', 'block/curr_admin:cluster:enrol_cluster_user', $USER->id);

        $allowed_clusters = array();

        //get the clusters and check the context against them
        $cluster_context_level = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');
        $cluster_context_instance = get_context_instance($cluster_context_level, $clusterid);

        $path = sql_concat('ctxt.path', "'/%'");
        $like = sql_ilike();

        //query to get sub-cluster contexts
        $cluster_permissions_sql = "SELECT clst.* FROM
                                    {$CURMAN->db->prefix_table(CLSTTABLE)} clst
                                    JOIN {$CURMAN->db->prefix_table('context')} ctxt
                                    ON clst.id = ctxt.instanceid
                                    AND ctxt.contextlevel = {$cluster_context_level}
                                    AND '{$cluster_context_instance->path}' {$like} {$path}";


        if($records = get_records_sql($cluster_permissions_sql)) {
            //filter the records based on what contexts have the cluster:enrol_cluster_user capability
            $allowed_clusters = $context->get_allowed_instances($records, 'cluster', 'id');
        }

        return $allowed_clusters;
    }

    /**
     * Determines whether the current user should be able to view any of the existing clusters
     *
     * @return  boolean  True if access is permitted, otherwise false
     */
    public static function all_clusters_viewable() {
        global $USER;

        //retrieve the context at which the current user has the sufficient capability
        $viewable_contexts = get_contexts_by_capability_for_user('cluster', 'block/curr_admin:cluster:view', $USER->id);
        $editable_contexts = get_contexts_by_capability_for_user('cluster', 'block/curr_admin:cluster:edit', $USER->id);

        //allow global access if either capability set allows access at the system context
        if (!empty($viewable_contexts->contexts['system'])) {
            return true;
        }
        if (!empty($editable_contexts->contexts['system'])) {
            return true;
        }

        return false;
    }

    /**
     * Determines the list of clusters the current user has the permissions to view
     *
     * @return  int array  The ids of the applicable clusters
     */
    public static function get_viewable_clusters($capabilities = null) {
        global $USER;

        if ($capabilities === null ) {
            $capabilities = array('block/curr_admin:cluster:view', 'block/curr_admin:cluster:edit');
        }
        if (!is_array($capabilities)) {
            $capabilities = array($capabilities);
        }

        $clusters = array();
        //retrieve the context at which the current user has the sufficient capability
        foreach ($capabilities as $capability) {
            $contexts = get_contexts_by_capability_for_user('cluster', $capability, $USER->id);
            //convert context sets to cluster ids
            $clusters[] = empty($contexts->contexts['cluster']) ? array() : $contexts->contexts['cluster'];
        }

        //merge the sets to get our final result
        $result = array_unique(call_user_func_array('array_merge', $clusters));

        return $result;
    }
}

/// Non-class supporting functions. (These may be able to replaced by a generic container/listing class)

/**
 * Gets a cluster listing with specific sort and other filters.
 *
 * @param string $sort Field to sort on.
 * @param string $dir Direction of sort.
 * @param int $startrec Record number to start at.
 * @param int $perpage Number of records per page.
 * @param string $namesearch Search string for cluster name.
 * @param string $descsearch Search string for cluster description.
 * @param string $alpha Start initial of cluster name filter.
 * @param int $userid User who you are assigning clusters to
 * @return object array Returned records.
 */

function cluster_get_listing($sort='name', $dir='ASC', $startrec=0, $perpage=0, $namesearch='',
                             $alpha='', $extrafilters = array(), $userid=0) {
    global $USER, $CURMAN;

    //require plugin code if enabled
    $display_priority_enabled = in_array('cluster_display_priority', get_list_of_plugins('curriculum/plugins'));
    if($display_priority_enabled) {
        require_once(CURMAN_DIRLOCATION . '/plugins/cluster_display_priority/lib.php');
    }

    $LIKE = $CURMAN->db->sql_compare();

    $select = 'SELECT clst.* ';
    $tables = "FROM {$CURMAN->db->prefix_table(CLSTTABLE)} clst ";
    $join = '';

    $where_conditions = array();
    if (!empty($namesearch)) {
        $namesearch = trim($namesearch);
        $where_conditions[] = "(name $LIKE '%$namesearch%') ";
    }

    if ($alpha) {
        $where_conditions[] = "(name $LIKE '$alpha%') ";
    }

    if (!empty($extrafilters['contexts'])) {
        /*
         * Start of cluster hierarchy extension
         */

        $sql_condition = '0=1';

        if (cluster::all_clusters_viewable()) {
            //user has capability at system level so allow access to any cluster
            $sql_condition = '0=0';
        } else {
            //user does not have capability at system level, so filter

            $viewable_clusters = cluster::get_viewable_clusters();

            if (empty($viewable_clusters)) {
                //user has no access to any clusters, so do not allow additional access
                $sql_condition = '0=1';
            } else {
                //user has additional access to some set of clusters, so "enable" this access
                $cluster_context_level = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');

                //use the context path to find parent clusters
                $like = sql_ilike();
                $parent_path = sql_concat('parent_context.path', "'/%'");
                $cluster_filter = implode(',', $viewable_clusters);

                $sql_condition = "id IN (
                                      SELECT parent_context.instanceid
                                      FROM {$CURMAN->db->prefix_table('context')} parent_context
                                      JOIN {$CURMAN->db->prefix_table('context')} child_context
                                        ON child_context.path {$like} {$parent_path}
                                        AND parent_context.contextlevel = {$cluster_context_level}
                                        AND child_context.contextlevel = {$cluster_context_level}
                                        AND child_context.instanceid IN ({$cluster_filter})
                                  )";
            }
        }

        /*
         * End of cluster hierarchy extension
         */

        $context_filter = $extrafilters['contexts']->sql_filter_for_context_level('id', 'cluster');

        //extend the basic context filter by potentially enabling access to parent clusters
        $where_conditions[] = "($context_filter OR $sql_condition)";
    }

    if (isset($extrafilters['parent'])) {
        $where_conditions[] = "parent={$extrafilters['parent']}";
    }

    if (isset($extrafilters['classification'])) {
        require_once (CURMAN_DIRLOCATION . '/plugins/cluster_classification/lib.php');
        $contextlevel = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');
        $field = new field(field::get_for_context_level_with_name($contextlevel, CLUSTER_CLASSIFICATION_FIELD));
        $where_conditions[] = "id IN (SELECT ctx.instanceid
                                        FROM {$CURMAN->db->prefix_table('context')} ctx
                                        JOIN (SELECT ctx.id AS contextid, IFNULL(fdata.data, fdefault.data) AS data
                                                FROM {$CURMAN->db->prefix_table('context')} ctx
                                            LEFT JOIN {$CURMAN->db->prefix_table($field->data_table())} fdata ON fdata.contextid = ctx.id AND fdata.fieldid = {$field->id}
                                            LEFT JOIN {$CURMAN->db->prefix_table($field->data_table())} fdefault ON fdefault.contextid IS NULL AND fdefault.fieldid = {$field->id}) fdata ON fdata.data = '{$extrafilters['classification']}' AND fdata.contextid = ctx.id
                                        WHERE ctx.contextlevel = $contextlevel)";
    }

    if(!empty($userid)) {
        //get the context for the "indirect" capability
        $context = cm_context_set::for_user_with_capability('cluster', 'block/curr_admin:cluster:enrol_cluster_user', $USER->id);

        $clusters = cluster_get_user_clusters($userid);
        $allowed_clusters = $context->get_allowed_instances($clusters, 'cluster', 'clusterid');

        $curriculum_context = cm_context_set::for_user_with_capability('cluster', 'block/curr_admin:cluster:enrol', $USER->id);
        $curriculum_filter = $curriculum_context->sql_filter_for_context_level('id', 'cluster');

        if(empty($allowed_clusters)) {
            $where_conditions[] = $curriculum_filter;
        } else {
            $allowed_clusters_list = implode(',', $allowed_clusters);

            $cluster_context_level = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');
            $path = sql_concat('parentctxt.path', "'/%'");

            $like = sql_ilike();

            //this allows both the indirect capability and the direct curriculum filter to work
            $where_conditions[] = "(
                                     (
                                     id IN (
                                       SELECT childctxt.instanceid
                                       FROM
                                         {$CURMAN->db->prefix_table(CLSTTABLE)} clst
                                         JOIN {$CURMAN->db->prefix_table('context')} parentctxt
                                           ON clst.id = parentctxt.instanceid
                                           AND parentctxt.contextlevel = {$cluster_context_level}
                                         JOIN {$CURMAN->db->prefix_table('context')} childctxt
                                           ON childctxt.path {$like} {$path}
                                           AND childctxt.contextlevel = {$cluster_context_level}
                                       )
                                     )
                                     OR
                                     (
                                     {$curriculum_filter}
                                     )
                                   )";
        }

    }

    //handle empty sort case
    if(empty($sort)) {
        $sort = 'name';
        $dir = 'ASC';
    }

    //get the fields we are sorting
    $sort_fields = explode(',', $sort);

    //convert the fields into clauses
    $sort_clauses = array();
    foreach($sort_fields as $key => $value) {
        $new_value = trim($value);
        if($display_priority_enabled && $new_value == 'priority') {
            $sort_clauses[$key] = $new_value . ' DESC';
        } else {
            $sort_clauses[$key] = $new_value . ' ' . $dir;
        }
    }

    //determine if we are handling the priority field for ordering
    if($display_priority_enabled && in_array('priority', $sort_fields)) {
        cluster_display_priority_append_sort_data('clst.id', $select, $join);
    }

    $where = '';
    if(!empty($where_conditions)) {
        $where = 'WHERE ' . implode(' AND ', $where_conditions) . ' ';
    }

    $sort_clause = 'ORDER BY ' . implode($sort_clauses, ', ') . ' ';

    //paging
    if (!empty($perpage)) {
        if ($CURMAN->db->_dbconnection->databaseType == 'postgres7') {
            $limit = 'LIMIT ' . $perpage . ' OFFSET ' . $startrec;
        } else {
            $limit = 'LIMIT '.$startrec.', '.$perpage;
        }
    } else {
        $limit = '';
    }

    $sql = $select.$tables.$join.$where.$sort_clause.$limit;

    return $CURMAN->db->get_records_sql($sql);
}


function cluster_count_records($namesearch = '', $alpha = '', $extrafilters = array()) {
    global $CURMAN;

    $select = array();

    $LIKE = $CURMAN->db->sql_compare();

    if (!empty($namesearch)) {
        $namesearch = trim($namesearch);
        $select[] = "(name $LIKE '%$namesearch%')";
    }

    if ($alpha) {
        $select[] = "(name $LIKE '$alpha%')";
    }

    if (!empty($extrafilters['contexts'])) {
        /*
         * Start of cluster hierarchy extension
         */
        $sql_condition = '0=1';

        if (cluster::all_clusters_viewable()) {
            //user has capability at system level so allow access to any cluster
            $sql_condition = '0=0';
        } else {
            //user does not have capability at system level, so filter

            $viewable_clusters = cluster::get_viewable_clusters();

            if (empty($viewable_clusters)) {
                //user has no access to any clusters, so do not allow additional access
                $sql_condition = '0=1';
            } else {
                //user has additional access to some set of clusters, so "enable" this access
                $cluster_context_level = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');

                //use the context path to find parent clusters
                $like = sql_ilike();
                $parent_path = sql_concat('parent_context.path', "'/%'");
                $cluster_filter = implode(',', $viewable_clusters);

                $sql_condition = "id IN (
                                      SELECT parent_context.instanceid
                                      FROM {$CURMAN->db->prefix_table('context')} parent_context
                                      JOIN {$CURMAN->db->prefix_table('context')} child_context
                                        ON child_context.path {$like} {$parent_path}
                                        AND parent_context.contextlevel = {$cluster_context_level}
                                        AND child_context.contextlevel = {$cluster_context_level}
                                        AND child_context.instanceid IN ({$cluster_filter})
                                  )";
            }
        }

        /*
         * End of cluster hierarchy extension
         */

        $context_filter = $extrafilters['contexts']->sql_filter_for_context_level('id', 'cluster');

        //extend the basic context filter by potentially enabling access to parent clusters
        $select[] = "($context_filter OR $sql_condition)";
    }

    if (isset($extrafilters['parent'])) {
        $select[] = "parent={$extrafilters['parent']}";
    }

    if (isset($extrafilters['classification'])) {
        require_once (CURMAN_DIRLOCATION . '/plugins/cluster_classification/lib.php');
        $contextlevel = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');
        $field = new field(field::get_for_context_level_with_name($contextlevel, CLUSTER_CLASSIFICATION_FIELD));
        $select[] = "id IN (SELECT ctx.instanceid
                              FROM {$CURMAN->db->prefix_table('context')} ctx
                              JOIN (SELECT ctx.id AS contextid, IFNULL(fdata.data, fdefault.data) AS data
                                      FROM {$CURMAN->db->prefix_table('context')} ctx
                                 LEFT JOIN {$CURMAN->db->prefix_table($field->data_table())} fdata ON fdata.contextid = ctx.id AND fdata.fieldid = {$field->id}
                                 LEFT JOIN {$CURMAN->db->prefix_table($field->data_table())} fdefault ON fdefault.contextid IS NULL AND fdefault.fieldid = {$field->id}) fdata ON fdata.data = '{$extrafilters['classification']}' AND fdata.contextid = ctx.id
                             WHERE ctx.contextlevel = $contextlevel)";
    }

    $select = implode(' AND ', $select);

    return $CURMAN->db->count_records_select(CLSTTABLE, $select);
}

/**
 * Specifies a mapping of cluster ids to names for display purposes
 *
 * @param  string  $orderby  Sort order and direction, if sorting is desired
 */
function cluster_get_cluster_names($orderby = 'name ASC') {
    global $CURMAN;

    $select = 'SELECT c.id, c.name ';
    $from   = 'FROM '.$CURMAN->db->prefix_table(CLSTTABLE).' c ';
    $join   = '';
    $where  = '';
    if (!empty($orderby)) {
        $order = 'ORDER BY '.$orderby.' ';
    } else {
        $order = '';
    }

    return $CURMAN->db->get_records_sql_menu($select.$from.$join.$where.$order);
}

function cluster_get_user_clusters($userid) {
    return get_records(CLSTUSERTABLE, 'userid', $userid);
}

function cluster_assign_to_user($clusterid, $userid, $autoenrol=true, $leader=false) {
    if (!is_numeric($clusterid) || !is_numeric($userid) || ($clusterid <= 0) || ($userid <= 0)) {
        return false;
    }

    if (record_exists(CLSTUSERTABLE, 'userid', $userid, 'clusterid', $clusterid)) {
        return true;
    }

    $cluster = new Object();
    $cluster->userid = $userid;
    $cluster->clusterid = $clusterid;
    $cluster->autoenrol = $autoenrol;
    $cluster->leader = $leader;
    $return = insert_record(CLSTUSERTABLE, $cluster);

    if ($return) {
        events_trigger('cluster_assigned', $cluster);
    }

    return $return;
}

function cluster_deassign_user($clusterid, $userid) {
    if (!is_numeric($clusterid) || !is_numeric($userid) || ($clusterid <= 0) || ($userid <= 0)) {
        return false;
    }

    $return = delete_records(CLSTUSERTABLE, 'clusterid', $clusterid, 'userid', $userid);

    if ($return) {
        $cluster = new Object();
        $cluster->userid = $userid;
        $cluster->clusterid = $clusterid;
        events_trigger('cluster_deassigned', $cluster);
    }

    return $return;
}

function cluster_deassign_all_user($userid) {
    if (!is_numeric($userid) || ($userid <= 0)) {
        return false;
    }

    return delete_records(CLSTUSERTABLE, 'userid', $userid);
}

/**
 * Gets cluster IDs and names of all non child clusters of target cluster
 *
 * @param int $target_cluster_id Target cluster id
 * @param object $contexts Cluster contexts for filtering, if applicable
 * @return array Returned results with key as cluster id and value as cluster name
 */
function cluster_get_non_child_clusters($target_cluster_id, $contexts = null) {
    global $CURMAN;
    $return = array(0=>get_string('cluster_top_level','block_curr_admin'));

    $cluster_context_level = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');

    if (!empty($target_cluster_id)) {
        $cluster_context_instance = get_context_instance($cluster_context_level, $target_cluster_id);
        $target_cluster_path = $cluster_context_instance->path;
    } else {
        // provide a dummy id and path that won't match anything
        $target_cluster_id = 0;
        $target_cluster_path = 'NaN';
    }

    $sql = "SELECT clst.id, clst.name
              FROM {$CURMAN->db->prefix_table(CLSTTABLE)} clst
              JOIN {$CURMAN->db->prefix_table('context')} ctx ON ctx.instanceid = clst.id
                   AND ctx.contextlevel = {$cluster_context_level}
             WHERE ctx.path NOT LIKE '{$target_cluster_path}/%'
                   AND ctx.instanceid != {$target_cluster_id}";

    if ($contexts !== null) {
        //append context condition
        $sql .= ' AND '.$contexts->sql_filter_for_context_level('clst.id', 'cluster');
    }

    $clusters = $CURMAN->db->get_records_sql($sql);
    $clusters = empty($clusters) ? array() : $clusters;

    foreach ($clusters as $cluster) {
        $return[$cluster->id] = $cluster->name;
    }

    return $return;
}

/**
 * Gets cluster IDs and names of all clusters that could be made into
 * subclusters of the target cluster.  Excludes:
 * - clusters that are an ancestor of the target cluster
 * - clusters that are already direct subclusters of the target cluster
 *
 * @param int $target_cluster_id Target cluster id
 * @return array Returned results with key as cluster id and value as cluster name
 * @param object $contexts Cluster contexts for filtering, if applicable
 */
function cluster_get_possible_sub_clusters($target_cluster_id, $contexts = null) {
    global $CURMAN;
    $return = array();

    if ($target_cluster_id == '') {
        return $return;
    }

    $cluster_context_level = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');
    $cluster_context_instance = get_context_instance($cluster_context_level, $target_cluster_id);
    // get parent contexts as a comma-separated list of context IDs
    $parent_contexts = strtr(substr($cluster_context_instance->path,1), '/', ',');

    $sql = "SELECT clst.id, clst.name
              FROM {$CURMAN->db->prefix_table(CLSTTABLE)} clst
              JOIN {$CURMAN->db->prefix_table('context')} ctx ON ctx.instanceid = clst.id
                   AND ctx.contextlevel = {$cluster_context_level}
             WHERE ctx.id NOT IN ({$parent_contexts})
                   AND clst.parent != {$target_cluster_id}";

    if ($contexts !== null) {
        //append context condition
        $sql .= ' AND '.$contexts->sql_filter_for_context_level('clst.id', 'cluster');
    }

    $clusters = $CURMAN->db->get_records_sql($sql);
    $clusters = empty($clusters) ? array() : $clusters;

    foreach ($clusters as $cluster) {
        $return[$cluster->id] = $cluster->name;
    }

    return $return;
}

?>
