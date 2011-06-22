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

/**
 * ---------------------------------------------------------------
 * This section consists of the event handlers used by this plugin
 * ---------------------------------------------------------------
 */

/**
 * Handler that gets called when a cm user gets assigned to a cluster
 *
 * @param  object   $cluster_assignment  The appropriate user-cluster association record
 * @return boolean                       Whether the association was successful
 *
 */
function cluster_groups_cluster_assigned_handler($cluster_assignment) {
    $attributes = array('usr.id' =>  $cluster_assignment->userid,
                        'clst.id' => $cluster_assignment->clusterid);

    $result = cluster_groups_update_groups($attributes);
    $result = $result && cluster_groups_update_user_site_course($cluster_assignment->userid, $cluster_assignment->clusterid);
    return $result;
}

/**
 * Handler that gets called when a cm class get associated with a Moodle course
 *
 * @param  classmoddlecourse  $class_association  The appropriate class-course association record
 * @return boolean                                Whether the association was successful
 *
 */
function cluster_groups_crlm_class_associated_handler($class_association) {
    $attributes = array('cls.id' => $class_association->classid,
                        'crs.id' => $class_association->moodlecourseid);

    return cluster_groups_update_groups($attributes);
}

/**
 * Handler that gets called when a class gets associated with a track
 *
 * @param  trackassignmentclass   $track_class_association  The appropriate track-class association record
 * @return boolean                                          Whether the association was successful
 */
function cluster_groups_crlm_curriculum_course_associated_handler($curriculum_course_association) {
    $attributes = array('cur.id' => $curriculum_course_association->curriculumid,
                        'cmcrs.id' => $curriculum_course_association->courseid);

    return cluster_groups_update_groups($attributes);
}

/**
 * Handler that gets called when a cluster gets associated with a track
 *
 * @param  object   $cluster_track_association  The appropriate cluster-track association record
 * @return boolean                              Whether the association was successful
 */
function cluster_groups_crlm_cluster_curriculum_associated_handler($cluster_curriculum_association) {
    $attributes = array('clst.id' => $cluster_curriculum_association->clusterid,
                        'cur.id' => $cluster_curriculum_association->curriculumid);

    return cluster_groups_update_groups($attributes);
}

/**
 * Handler that gets called when a cluster is updated
 *
 * @param  cluster   $cluster  The appropriate cluster record
 *
 * @return boolean              Whether the association was successful
 */
function cluster_groups_crlm_cluster_updated_handler($cluster) {
    $attributes = array('clst.id' => $cluster->id);

    $result = cluster_groups_update_groups($attributes);
    $result = $result && cluster_groups_update_site_course($cluster->id, true);
    $result = $result && cluster_groups_update_grouping_closure($cluster->id, true);
    return $result;
}

/**
 * Handler that gets called when group syncing is enabled
 *
 * @return boolean  Whether the association was successful
 */
function cluster_groups_crlm_cluster_groups_enabled_handler() {
    return cluster_groups_update_groups();
}

/**
 * Handler that gets called when site-course group syncing is enabled
 *
 * @return  boolean  Whether the association was successful
 */
function cluster_groups_crlm_site_course_cluster_groups_enabled_handler() {
    return cluster_groups_update_site_course(0, true);
}

/**
 * Handler that gets called when a cluster is created
 *
 * @return  boolean  Whether the association was successful
 */
function cluster_groups_crlm_cluster_created_handler($cluster) {
    $result = cluster_groups_update_site_course($cluster->id);
    $result = $result && cluster_groups_update_grouping_closure($cluster->id);
    return $result;
}

/**
 * Handler that gets called when a role assignment takes place
 */
function cluster_groups_role_assigned_handler($role_assignment) {

    //update non-site courses for that user
    $result = cluster_groups_update_groups(array('mdlusr.id' => $role_assignment->userid));
    //update site course for that user
    $result = $result && cluster_groups_update_site_course(0, true, $role_assignment->userid);

    return $result;
}

/**
 * Handler that gets called when cluster-based groupings are enabled
 */
function cluster_groups_crlm_cluster_groupings_enabled() {

    $result = true;

    //update the groups and groupings info
    if($recordset = get_recordset(CLSTTABLE)) {
        while($record = rs_fetch_next_record($recordset)) {
            $result = $result && cluster_groups_update_site_course($record->id);
        }
    }

    //update the parent-child relationships
    if($recordset = get_recordset(CLSTTABLE)) {
        while($record = rs_fetch_next_record($recordset)) {
            $result = $result && cluster_groups_update_grouping_closure($record->id);
        }
    }

    return true;
}

/**
 * ------------------------------------------------------
 * Main processing functions called by the event handlers
 * ------------------------------------------------------
 */

/**
 * Adds groups and group assignments at the site-level either globally
 * or for a particular cluster
 *
 * @param  int  $clusterid  The particular cluster's id, or zero for all
 */
function cluster_groups_update_site_course($clusterid = 0, $add_members = false, $userid = 0) {
    global $CFG, $CURMAN;

    //make sure this functionality is even enabled
    if(!empty($CURMAN->config->site_course_cluster_groups)) {

        $select_parts = array();

        $cluster_select = '';
        if(!empty($clusterid)) {
            $select_parts[] = "(clst.id = $clusterid)";
        }

        if(!empty($userid)) {
            $select_parts[] = "(mdluser.id = {$userid})";
        }

        $select = empty($select_parts) ? '' : 'WHERE ' . implode('AND', $select_parts);

        $siteid = SITEID;

        //query to get clusters, groups, and possibly users
        $sql = "SELECT grp.id AS groupid, clst.id AS clusterid, clst.name AS clustername, mdluser.id AS userid FROM
                {$CURMAN->db->prefix_table(CLSTTABLE)} clst
                LEFT JOIN {$CURMAN->db->prefix_table('groups')} grp
                ON clst.name = grp.name
                AND grp.courseid = {$siteid}
                LEFT JOIN
                    ({$CURMAN->db->prefix_table(CLSTUSERTABLE)} usrclst
                     JOIN {$CURMAN->db->prefix_table(USRTABLE)} crlmuser
                     ON usrclst.userid = crlmuser.id
                     JOIN {$CURMAN->db->prefix_table('user')} mdluser
                     ON crlmuser.idnumber = mdluser.idnumber)
                ON clst.id = usrclst.clusterid
                $select
                ORDER BY clst.id";

        if($recordset = get_recordset_sql($sql)) {

            $last_clusterid = 0;
            $last_group_id = 0;

            while($record = rs_fetch_next_record($recordset)) {

                if($last_clusterid != $record->clusterid) {
                    $last_group_id = $record->groupid;
                }

                if(cluster_groups_cluster_allows_groups($record->clusterid)) {

                    //handle group record
                    if(empty($record->groupid) && (empty($last_clusterid) || $last_clusterid !== $record->clusterid)) {
                        //create group
                        $group = new stdClass;
                        $group->courseid = SITEID;
                        $group->name = addslashes($record->clustername);
                        $group->id = groups_create_group($group);
                        $last_group_id = $group->id;
                    }

                    //handle adding members
                    if($add_members && !empty($last_group_id) && !empty($record->userid)) {
                        cluster_groups_add_member($last_group_id, $record->userid);
                    }

                    //set up groupings
                    if(empty($last_clusterid) || $last_clusterid !== $record->clusterid) {
                        cluster_groups_grouping_helper($record->clusterid, $record->clustername);
                    }

                }

                $last_clusterid = $record->clusterid;
            }
        }

    }

    if(!empty($CFG->enablegroupings) && !empty($CURMAN->config->cluster_groupings)) {
        //query condition
        $select = '1 = 1';
        if(!empty($clusterid)) {
            $select = "id = $clusterid";
        }

        //go through all appropriate clusters
        if($recordset = get_recordset_select(CLSTTABLE, $select)) {
            while($record = rs_fetch_next_record($recordset)) {
                //set up groupings
                cluster_groups_grouping_helper($record->id, $record->name);
            }
        }
    }

    return true;
}

/**
 * Sets up a grouping based on a particular cluster
 *
 * @param  int     $clusterid  The id of the chosen cluster
 * @param  string  $name       The name of the cluster
 */
function cluster_groups_grouping_helper($clusterid, $name) {
    global $CFG, $CURMAN;

    if(!empty($CFG->enablegroupings) &&
       !empty($CURMAN->config->cluster_groupings) &&
       cluster_groups_grouping_allowed($clusterid)) {

        //determine if flagged as grouping
        $contextlevel = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');
        $contextinstance = get_context_instance($contextlevel, $clusterid);
        $data = field_data::get_for_context($contextinstance);

        //retrieve grouping
        $grouping = groups_get_grouping_by_name(SITEID, $name);

        //obtain the grouping record
        if(empty($grouping)) {
            $grouping = new stdClass;
            $grouping->courseid = SITEID;
            $grouping->name = addslashes($name);
            $grouping->id = groups_create_grouping($grouping);
        } else {
            $grouping = groups_get_grouping($grouping);
        }

        //obtain the child cluster ids
        $child_clusters = cluster_groups_get_child_clusters($clusterid);

        //add appropriate cluster-groups to the grouping
        foreach($child_clusters as $child_cluster) {
            if($cluster_record = get_record(CLSTTABLE, 'id', $child_cluster)) {
                if($child_cluster_group = groups_get_group_by_name(SITEID, $cluster_record->name)) {
                    if(cluster_groups_grouping_allowed($cluster_record->id)) {
                        groups_assign_grouping($grouping->id, $child_cluster_group);
                    }
                }

            }
        }
    }
}

/**
 * Updates site-course cluster-based groups for a particular user and cluster
 *
 * @param   int      $userid     The id of the appropriate CM user
 * @param   int      $clusterid  The id of the appropriate cluster
 * @return  boolean              Returns true to satisfy event handling
 */
function cluster_groups_update_user_site_course($userid, $clusterid) {
    global $CURMAN;

    //make sure this site-course group functionality is even enabled
    if($CURMAN->config->site_course_cluster_groups) {

        //make sure group functionality is enabled for this cluster
        if(cluster_groups_cluster_allows_groups($clusterid)) {

            //obtain the cluster
            if($cluster_record = get_record(CLSTTABLE, 'id', $clusterid)) {

                //retrieves the appropraite user
                $crlm_user = new user($userid);
                if($mdl_user = get_record('user', 'idnumber', addslashes($crlm_user->idnumber))) {

                    //obtain the group
                    $siteid = SITEID;
                    $sql = "SELECT grp.*
                            FROM {$CURMAN->db->prefix_table('groups')} grp
                            WHERE name = '" . addslashes($cluster_record->name) . "'
                            AND courseid = {$siteid}";
                    if(!$group = get_record_sql($sql, true)) {
                         //create the group here
                        $group = new stdClass;
                        $group->courseid = SITEID;
                        $group->name = addslashes($cluster_record->name);
                        $group->id = groups_create_group($group);
                    }

                    //add current user to group
                    cluster_groups_add_member($group->id, $mdl_user->id);

                    //make sure groupings are set up
                    cluster_groups_grouping_helper($cluster_record->id, $cluster_record->name);
                }
            }
        }
    }

    return true;
}

/**
 * Updated Moodle groups based on clusters when a part of the following chain is updated:
 *
 * Moodle User - CM User - Cluster - Track - Class - Moodle Course
 *
 * @param   array    $attributes  Conditions to apply to the SQL query
 * @return  boolean               Returns true to appease events_trigger
 *
 */
function cluster_groups_update_groups($attributes = array()) {
    global $CURMAN;

    //nothing to do if global setting is off
    if(!empty($CURMAN->config->cluster_groups)) {

        //whenever we're given a cluster id, see if we can eliminate
        //any processed in the case where the cluster does not allow
        //synching to a group
        $clusterid = 0;
        if(!empty($attributes['clst.id'])) {
            $clusterid = $attributes['clst.id'];
        }

        //proceed if no cluster is specified or one that allows group
        //synching is specified
        if($clusterid === 0 || cluster_groups_cluster_allows_groups($clusterid)) {

            $condition = '';
            if(!empty($attributes)) {
                foreach($attributes as $key => $value) {
                    if(empty($condition)) {
                        $condition = "WHERE $key = $value";
                    } else {
                        $condition .= " AND $key = $value";
                    }
                }
            }

            //this query handles the bulk of the work
            $sql = "SELECT crs.id AS courseid,
                           clst.name AS clustername,
                           mdlusr.id AS userid,
                           clst.id AS clusterid
                    FROM {$CURMAN->db->prefix_table(CLSTABLE)} cls
                    JOIN {$CURMAN->db->prefix_table(CLSMOODLETABLE)} clsmdl
                    ON cls.id = clsmdl.classid
                    JOIN {$CURMAN->db->prefix_table('course')} crs
                    ON clsmdl.moodlecourseid = crs.id
                    JOIN {$CURMAN->db->prefix_table(CRSTABLE)} cmcrs
                    ON cmcrs.id = cls.courseid
                    JOIN {$CURMAN->db->prefix_table(CURCRSTABLE)} curcrs
                    ON curcrs.courseid = cmcrs.id
                    JOIN {$CURMAN->db->prefix_table(CURTABLE)} cur
                    ON curcrs.curriculumid = cur.id
                    JOIN {$CURMAN->db->prefix_table(CLSTCURTABLE)} clstcur
                    ON clstcur.curriculumid = curcrs.curriculumid
                    JOIN {$CURMAN->db->prefix_table(CLSTTABLE)} clst
                    ON clstcur.clusterid = clst.id
                    JOIN {$CURMAN->db->prefix_table(CLSTUSERTABLE)} usrclst
                    ON clst.id = usrclst.clusterid
                    JOIN {$CURMAN->db->prefix_table(USRTABLE)} usr
                    ON usrclst.userid = usr.id
                    JOIN {$CURMAN->db->prefix_table('user')} mdlusr
                    ON usr.idnumber = mdlusr.idnumber
                    {$condition}
                    ORDER BY clst.id";

            $records = get_recordset_sql($sql);

            if($records) {

                //used to track changes in clusters
                $last_cluster_id = 0;
                $last_group_id = 0;

                while($record = rs_fetch_next_record($records)) {

                    //make sure the cluster allows synching to groups
                    if(cluster_groups_cluster_allows_groups($record->clusterid)) {

                        //if first record cluster is different from last, create / retrieve group
                        if($last_cluster_id === 0 || $last_cluster_id !== $record->clusterid) {

                            //determine if group already exists
                            if(record_exists('groups', 'name', addslashes($record->clustername), 'courseid', $record->courseid)) {
                                $sql = "SELECT *
                                        FROM {$CURMAN->db->prefix_table('groups')} grp
                                        WHERE name = '" . addslashes($record->clustername) . "'
                                        AND courseid = {$record->courseid}";
                                $group = get_record_sql($sql, true);
                            } else {
                                $group = new stdClass;
                                $group->courseid = $record->courseid;
                                $group->name = addslashes($record->clustername);
                                $group->id = groups_create_group($group);
                            }

                            $last_cluster_id = $record->clusterid;
                            $last_group_id = $group->id;
                        }

                        //add user to group
                        cluster_groups_add_member($last_group_id, $record->userid);
                    }

                }

            }

        }
    }

    return true;
}

/**
 * Updates all parent cluster's groupings with the existence of a group for this cluster
 *
 * @param    int      $clusterid         The cluster to check the parents for
 * @param    boolean  $include_children  If true, make child cluster-groups trickle up the tree
 * @return   boolean                     Returns true to satisfy event handlers
 */
function cluster_groups_update_grouping_closure($clusterid, $include_children = false) {
    global $CFG, $CURMAN;

    if(empty($CFG->enablegroupings) || empty($CURMAN->config->cluster_groupings) || !cluster_groups_grouping_allowed($clusterid)) {
        return true;
    }

    $cluster = new cluster($clusterid);

    //get the group id for this cluster
    if($groupid = groups_get_group_by_name(SITEID, $cluster->name)) {

        //go through the chain of parent clusters
        while(!empty($cluster->parent)) {
            $cluster = new cluster($cluster->parent);

            //add this to grouping if applicable
            $grouping = groups_get_grouping_by_name(SITEID, $cluster->name);
            if($grouping = groups_get_grouping($grouping)) {
                groups_assign_grouping($grouping->id, $groupid);

                //recurse into children if possible
                if($include_children) {
                
                    //get all child clusters
                    $child_cluster_ids = cluster_groups_get_child_clusters($cluster->id);
                    
                    foreach($child_cluster_ids as $child_cluster_id) {
                    
                        //children only
                        if($child_cluster_id != $cluster->id) {
                        
                            $child_cluster = new cluster($child_cluster_id);
                            
                            //make sure the group exists
                            if($child_groupid = groups_get_group_by_name(SITEID, $child_cluster->name) and
                               cluster_groups_cluster_allows_groups($child_cluster->id)) {
                                groups_assign_grouping($grouping->id, $child_groupid);
                            }
                        }
                    }
                }

            }
        }

    }

    return true;
}

/**
 * ----------------
 * Helper functions
 * ----------------
 */

/**
 * Determines whether a cluster allows groups without taking the global setting into account
 * Note: does not take global setting into account
 *
 * @param   int      $clusterid  The id of the cluster in question
 * @return  boolean              Whether the cluster allows groups or not
 */
function cluster_groups_cluster_allows_groups($clusterid) {
    global $CURMAN;

    //retrieve the config field
    if($fieldid = get_field(FIELDTABLE, 'id', 'shortname', 'cluster_group')) {

        //get the cluster context level
        $context = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');

        //retrieve the cluster context instance
        $context_instance = get_context_instance($context, $clusterid);

        //construct the specific field
        $field = new field($fieldid);

        //retrieve the appropriate field's data for this cluster based on the context instance
        if($field_data = field_data::get_for_context_and_field($context_instance, $field)) {

            //this should really only return one record, so return true of any have non-empty data
            foreach($field_data as $field_datum) {
                if(!empty($field_datum->data)) {
                    return true;
                }
            }
        }
    }

    return false;
}

/**
 * Determines if a paritcular cluster is set up for groupings
 *
 * @param   int      $clusterid  The id of the cluster in question
 * @return  boolean              True if this grouping is allowed, otherwise false
 */
function cluster_groups_grouping_allowed($clusterid) {
    global $CFG, $CURMAN;

    if(empty($CFG->enablegroupings) || empty($CURMAN->config->cluster_groupings)) {
        return false;
    }

    //retrieve the config field
    if($fieldid = get_field(FIELDTABLE, 'id', 'shortname', 'cluster_groupings')) {

        //get the cluster context level
        $context = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');

        //retrieve the cluster context instance
        $context_instance = get_context_instance($context, $clusterid);

        //construct the specific field
        $field = new field($fieldid);

        //retrieve the appropriate field's data for this cluster based on the context instance
        if($field_data = field_data::get_for_context_and_field($context_instance, $field)) {

            //this should really only return one record, so return true of any have non-empty data
            foreach($field_data as $field_datum) {
                if(!empty($field_datum->data)) {
                    return true;
                }
            }
        }
    }

    return false;
}

/**
 * Adds a user to a group if appropriate
 * Note: does not check permissions
 *
 * @param  int  $groupid  The id of the appropriate group
 * @param  int  $userid   The id of the user to add
 */
function cluster_groups_add_member($groupid, $userid) {
    if($group_record = get_record('groups', 'id', $groupid)) {

        //this works even for the site-level "course"
        $context = get_context_instance(CONTEXT_COURSE, $group_record->courseid);
        $filter = get_related_contexts_string($context);

        //if the user doesn't have an appropriate role, a group assignment
        //will not work, so avoid assigning in that case
        $select = "userid = {$userid} and contextid {$filter}";
        if(!record_exists_select('role_assignments', $select)) {
            return;
        }

        groups_add_member($groupid, $userid);
    }
}

/**
 * Calculate a list of cluster ids that are equal to or children of the provided cluster
 *
 * @param   int        $clusterid  The cluster id to start at
 * @return  int array              The list of cluster id
 */
function cluster_groups_get_child_clusters($clusterid) {
    $result = array($clusterid);

    if($child_clusters = get_records(CLSTTABLE, 'parent', $clusterid)) {
        foreach($child_clusters as $child_cluster) {
            $child_result = cluster_groups_get_child_clusters($child_cluster->id);
            $result = array_merge($result, $child_result);
        }
    }

    return $result;
}

/**
 * ----------------
 * Install function
 * ----------------
 */

/**
 * Sets up the fields necessary for enabling cluster groups
 *
 * @return  boolean  Returns true to indicate success
 */
function cluster_groups_install() {

    //retrieve the cluster context
    $cluster_context = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');

    //set up the cluster group category
    $group_category = new field_category();
    $group_category->name = get_string('cluster_group_category', 'crlm_cluster_groups');

    //set up the field that allows users to turn the groupings on
    $group_field = new field();
    $group_field->shortname = 'cluster_group';
    $group_field->name = get_string('cluster_group', 'crlm_cluster_groups');
    $group_field->datatype = 'bool';

    //set up the field and category
    $group_field = field::ensure_field_exists_for_context_level($group_field, $cluster_context, $group_category);

    //set up the field owner
    $owner_options = array('required' => 0,
                           'edit_capability' => '',
                           'view_capability' => '',
                           'control' => 'checkbox',
                           'columns' => 30,
                           'rows' => 10,
                           'maxlength' => 2048,
                           'help_file' => 'crlm_cluster_groups/cluster_groups',
                          );
    field_owner::ensure_field_owner_exists($group_field, 'manual', $owner_options);

    //retrieve the cluster context
    $context = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');

    $field = new field();
    $field->shortname = 'cluster_groupings';
    $field->name = get_string('autoenrol_groupings', 'crlm_cluster_classification');
    $field->datatype = 'bool';
    $field = field::ensure_field_exists_for_context_level($field, $context, $group_category);

    $owner_options = array('required' => 0,
                           'edit_capability' => '',
                           'view_capability' => '',
                           'control' => 'checkbox',
                           'columns' => 30,
                           'rows' => 10,
                           'maxlength' => 2048,
                           'help_file' => 'crlm_cluster_groups/cluster_groupings');
    field_owner::ensure_field_owner_exists($field, 'manual', $owner_options);

    return true;
}

?>