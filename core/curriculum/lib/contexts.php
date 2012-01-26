<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 * Manipulate CM context levels.
 *
 * The CM context level hierarchy looks like this:
 * System
 * +- Curriculum --.
 * |  `- Track --. |
 * +- Course <---+-'
 * |  `- Class <-'
 * +- Cluster -.
 * `- User   <-'
 *
 * (ASCII arrows represent "fake" hierarchies)
 *
 * Clusters can also be nested
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

function get_contexts_by_capability_for_user($contextlevel, $capability, $userid, $doanything=true) {
    return cm_context_set::for_user_with_capability($contextlevel, $capability, $userid, $doanything);
}

class cm_context_set {
    /**
     * An array of the contexts (both at the requested context level, and in
     * parent context levels) where the user is assigned the given
     * capabilities.  Clusters are considered a parent context of users.  The
     * return type is an array.  The array has keys set to the name of the
     * context level, and the value is an array of instance IDs where the user
     * has the given capability.
     */
    var $contexts;

    /**
     * Fetch the contexts where the user has a given capability.  This only works
     * with the CM context levels.
     *
     * Assumes that the user does not have "too many" role assignments.  Assumes
     * the user has no "prevents"/"prohibits" roles.
     */
    static function for_user_with_capability($contextlevel, $capability, $userid, $doanything=true) {
        global $CURMAN;

        static $cm_context_parents = array('track' => array('curriculum'),
                                           'course' => array('curriculum'),
                                           'class' => array('course','track'),
                                           'user' => array('cluster'));

        $obj = new cm_context_set();

        // if the user has the capability at the system level (or has the
        // managecurricula master capability), we can stop here
        if (has_capability($capability, get_context_instance(CONTEXT_SYSTEM), $userid, $doanything) ||
            has_capability('block/curr_admin:managecurricula', get_context_instance(CONTEXT_SYSTEM), $userid, $doanything)) {
            $obj->contexts = array('system' => 1);
            return $obj;
        }

        $contexts = array($contextlevel => array());

        $timenow = time();
        // find all contexts at the given context level where the user has a direct
        // role assignment
        $contextlevelnum = context_level_base::get_custom_context_level($contextlevel, 'block_curr_admin');
        $sql = "SELECT c.*
              FROM {$CURMAN->db->prefix_table('role_assignments')} ra
              JOIN {$CURMAN->db->prefix_table('context')} c ON ra.contextid = c.id
             WHERE ra.userid = $userid
               AND (ra.timeend = 0 OR ra.timeend >= $timenow)
               AND c.contextlevel = $contextlevelnum";
        $possiblecontexts = $CURMAN->db->get_records_sql($sql);
        $possiblecontexts = $possiblecontexts ? $possiblecontexts : array();
        foreach ($possiblecontexts as $c) {
            if (has_capability($capability, $c, $userid, $doanything)) {
                $contexts[$contextlevel][] = $c->instanceid;
            }
        }
        if (empty($contexts[$contextlevel])) {
            unset($contexts[$contextlevel]);
        }

        // look in the parent contexts
        if (isset($cm_context_parents[$contextlevel])) {
            foreach ($cm_context_parents[$contextlevel] as $parentlevel) {
                $parent = cm_context_set::for_user_with_capability($parentlevel, $capability, $userid, $doanything);
                $contexts = array_merge($contexts, $parent->contexts);
            }
        }

        $obj->contexts = $contexts;
        return $obj;
    }

    function is_empty() {
        return empty($this->contexts);
    }

    /**
     * Returns an SQL WHERE clause that will limit the curricula to those specified
     * in the $context array.
     * @param string $idfieldname the field name that willcontain the curriculum
     * IDs to test
     * @return string SQL WHERE clause
     */
    function sql_filter_for_context_level($idfieldname, $contextlevel) {
        if (isset($this->contexts['system'])) {
            return 'TRUE';
        }

        $where = call_user_func(array($this,"_sql_filter_for_$contextlevel"), $idfieldname);
        if (empty($where)) {
            return 'FALSE';
        } else {
            return '('.implode(' OR ',$where).')';
        }
    }

    function _sql_filter_for_curriculum($idfieldname) {
        $where = array();
        if (isset($this->contexts['curriculum'])) {
            $where[] = "($idfieldname IN (".implode(',',$this->contexts['curriculum']).'))';
        }
        return $where;
    }

    function _sql_filter_for_track($idfieldname) {
        global $CURMAN;
        $where = array();
        if (isset($this->contexts['track'])) {
            $where[] = "($idfieldname IN (".implode(',',$this->contexts['track']).'))';
        }
        if (isset($this->contexts['curriculum'])) {
            // yuck
            $where[] = "($idfieldname IN (SELECT id FROM {$CURMAN->db->prefix_table('crlm_track')}
                                       WHERE curid IN (".implode(',',$this->contexts['curriculum']).')))';
        }
        return $where;
    }

    function _sql_filter_for_course($idfieldname) {
        global $CURMAN;
        $where = array();
        if (isset($this->contexts['course'])) {
            $where[] = "($idfieldname IN (".implode(',',$this->contexts['course']).'))';
        }
        if (isset($this->contexts['curriculum'])) {
            $where[] = "($idfieldname IN (SELECT courseid
                                        FROM {$CURMAN->db->prefix_table('crlm_curriculum_course')}
                                       WHERE curriculumid IN (".implode(',',$this->contexts['curriculum']).')))';
        }
        return $where;
    }

    function _sql_filter_for_class($idfieldname) {
        global $CURMAN;
        $where = array();
        if (isset($this->contexts['class'])) {
            $where[] = "($idfieldname IN (".implode(',',$this->contexts['class']).'))';
        }
        if (isset($this->contexts['course'])) {
            // yuck
            $where[] = "($idfieldname IN (SELECT id FROM {$CURMAN->db->prefix_table('crlm_class')}
                                       WHERE courseid IN (".implode(',',$this->contexts['course']).')))';
        }
        if (isset($this->contexts['track'])) {
            $where[] = "($idfieldname IN (SELECT classid
                                        FROM {$CURMAN->db->prefix_table('crlm_track_class')}
                                       WHERE trackid IN (".implode(',',$this->contexts['track']).')))';
        }
        if (isset($this->contexts['curriculum'])) {
            // return classes that belong to tracks in the curriculum, or
            // classes that belong to courses in the curriculum
            $where[] = "($idfieldname IN (SELECT trkcls.classid
                                        FROM {$CURMAN->db->prefix_table('crlm_track_class')} trkcls
                                        JOIN {$CURMAN->db->prefix_table('crlm_track')} trk ON trk.id = trkcls.trackid
                                       WHERE trk.curid IN (".implode(',',$this->contexts['curriculum']).')))';

            $where[] = "($idfieldname IN (SELECT cls.id
                                        FROM {$CURMAN->db->prefix_table('crlm_curriculum_course')} cls
                                        JOIN {$CURMAN->db->prefix_table('crlm_curriculum_course')} curcrs ON curcrs.courseid = cls.courseid
                                       WHERE curcrs.curriculumid IN (".implode(',',$this->contexts['curriculum']).')))';
        }
        return $where;
    }

    function _sql_filter_for_cluster($idfieldname) {
        global $CURMAN;
        $where = array();
        if (isset($this->contexts['cluster'])) {
            $where[] = "($idfieldname IN (".implode(',',$this->contexts['cluster']).'))';
            $ctxlvl = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');
            // cross fingers and hope that the user doesn't have too many clusters
            foreach ($this->contexts['cluster'] as $cluster) {
                $context = get_context_instance($ctxlvl, $cluster);
                $pattern = $context->path . '/%';
                $where[] = "($idfieldname IN (SELECT ctx.instanceid
                                                FROM {$CURMAN->db->prefix_table('context')} ctx
                                               WHERE ctx.path LIKE '{$pattern}'
                                                 AND ctx.contextlevel = {$ctxlvl}))";
            }
        }
        return $where;
    }

    function _sql_filter_for_user($idfieldname) {
        global $CURMAN;
        $where = array();
        if (isset($this->contexts['user'])) {
            $where[] = "($idfieldname IN (".implode(',',$this->contexts['user']).'))';
        }
        if (isset($this->contexts['cluster'])) {
            $where[] = "($idfieldname IN (SELECT userid
                                        FROM {$CURMAN->db->prefix_table('crlm_usercluster')}
                                       WHERE clusterid IN (".implode(',',$this->contexts['cluster']).')))';
            $ctxlvl = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');
            // cross fingers and hope that the user doesn't have too many clusters
            foreach ($this->contexts['cluster'] as $cluster) {
                $context = get_context_instance($ctxlvl, $cluster);
                $pattern = $context->path . '/%';
                $where[] = "($idfieldname IN (SELECT userid
                                                FROM {$CURMAN->db->prefix_table('crlm_usercluster')}
                                               WHERE clusterid IN (SELECT ctx.instanceid
                                                                     FROM {$CURMAN->db->prefix_table('context')} ctx
                                                                    WHERE ctx.path LIKE '{$pattern}'
                                                                      AND ctx.contextlevel = {$ctxlvl})))";
            }
        }
        return $where;
    }


    function context_allowed($id, $contextlevel) {
        if (isset($this->contexts['system'])) {
            return true;
        }
        if (isset($this->contexts[$contextlevel]) && in_array($id, $this->contexts[$contextlevel])) {
            return true;
        }
        if (method_exists($this, "_{$contextlevel}_allowed")) {
            $rv = call_user_func(array($this,"_{$contextlevel}_allowed"), $id);
            if ($rv) {
                $this->contexts[$contextlevel][] = $id;
            }
            return $rv;
        }
        return false;
    }

    function _track_allowed($id) {
        global $CURMAN;
        if (isset($this->contexts['curriculum'])
            && $CURMAN->db->record_exists_select('crlm_track',
                                                 "id = $id AND curid IN (".implode(',',$this->contexts['curriculum']).')')) {
            return true;
        }
        return false;
    }

    function _course_allowed($id) {
        global $CURMAN;
        if (isset($this->contexts['curriculum'])
            && $CURMAN->db->record_exists_select('crlm_curriculum_course',
                                                 "courseid = $id AND curriculumid IN (".implode(',',$this->contexts['curriculum']).')')) {
            return true;
        }
        return false;
    }

    function _class_allowed($id) {
        global $CURMAN;
        if (isset($this->contexts['course'])
            && $CURMAN->db->record_exists_select('crlm_class',
                                                 "id = $id AND courseid IN (".implode(',',$this->contexts['course']).')')) {
            return true;
        }
        if (isset($this->contexts['track'])
            && $CURMAN->db->record_exists_select('crlm_track_class',
                                                 "classid = $id AND trackid IN (".implode(',',$this->contexts['track']).')')) {
            return true;
        }
        if (isset($this->contexts['curriculum']))  {
            $sql = "SELECT 'x'
                      FROM {$CURMAN->db->prefix_table('crlm_track_class')} trkcls
                      JOIN {$CURMAN->db->prefix_table('crlm_track')} trk ON trk.id = trkcls.trackid
                     WHERE trkcls.classid = $id AND trk.curid IN (".implode(',',$this->contexts['curriculum']).")
              UNION SELECT 'x'
                      FROM {$CURMAN->db->prefix_table('crlm_curriculum_course')} curcrs
                      JOIN {$CURMAN->db->prefix_table('crlm_class')} cls ON curcrs.courseid = cls.courseid
                     WHERE cls.id = $id AND curcrs.curriculumid IN (".implode(',',$this->contexts['curriculum']).')';
            if ($CURMAN->db->record_exists_sql($sql)) {
                return true;
            }
        }
        return false;
    }

    function _cluster_allowed($id) {
        global $CURMAN;
        if (isset($this->contexts['cluster'])) {
            $ctxlvl = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');
            $context = get_context_instance($ctxlvl, $id);
            $ancestorids = substr(str_replace('/',',',$context->path),1);
            $sql = "SELECT COUNT(*)
                      FROM {$CURMAN->db->prefix_table('context')} ctx
                     WHERE id IN ($ancestorids)
                       AND instanceid IN (".implode(',',$this->contexts['cluster']).")
                       AND contextlevel = $ctxlvl";
            return $CURMAN->db->count_records_sql($sql) != 0;
        }
        return false;
    }

    function _user_allowed($id) {
        global $CURMAN;
        if (isset($this->contexts['cluster'])
            && $CURMAN->db->record_exists_select('crlm_usercluster',
                                                 "userid = $id AND clusterid IN (".implode(',',$this->contexts['cluster']).')')) {
            return true;
        }
        if (isset($this->contexts['cluster'])) {
            $sql = "SELECT COUNT(*)
                      FROM {$CURMAN->db->prefix_table('crlm_usercluster')}
                     WHERE userid = $id
                       AND ".$this->sql_filter_for_context_level('clusterid', 'cluster');
            return $CURMAN->db->count_records_sql($sql) != 0;
        }
        return false;
    }
    
    /**
     * Filters a list of objects based on whether they are accessible to the current user
     * 
     * @param   object array  $instances     Array of objects representing context instances
     * @param   string        $contextlevel  String shortname of the context level we are checking permissions at
     * @param   string        $field         The field on each of the instances that represents the context instance id
     * 
     * @return  object array                 Array containing those of the supplied instances that are allowed
     *                                       based on the capability provided to this context object upon construction
     */
    function get_allowed_instances($instances, $contextlevel, $field) {
        $allowed_instances = array();
        
        if(!empty($instances)) {
            //go through and filter out those that are not allowed
            foreach($instances as $instance) {
                if($this->context_allowed($instance->{$field}, $contextlevel)) {
                    $allowed_instances[] = $instance->{$field};
                }
            }
        }
        
        return $allowed_instances;
    }
}

/**
 * Who has a capability in a CM context?  Uses accesslib's
 * get_users_by_capability, but augments the result with the CM system's fake
 * hierarchy.
 */
function cm_get_users_by_capability($contextlevel, $instance_id, $capability) {
    static $cm_context_extra_parents = array('course' => array('curriculum'),
                                             'class' => array('track'),
                                             'user' => array('cluster'));
    $contextlevelnum = context_level_base::get_custom_context_level($contextlevel, 'block_curr_admin');
    $context = get_context_instance($contextlevelnum, $instance_id);
    $users = get_users_by_capability($context, $capability);
    $users = $users ? $users : array();

    // look in the parent contexts
    if (isset($cm_context_extra_parents[$contextlevel])) {
        foreach ($cm_context_extra_parents[$contextlevel] as $parentlevel) {
            $parents = call_user_func("_cmctx_get_{$parentlevel}_containing_{$contextlevel}", $instance_id);
            foreach ($parents as $parentid => $parent) {
                $newusers = cm_get_users_by_capability($parentlevel, $parentid, $capability);
                if ($newusers) {
                    $users = $users + $newusers;
                }
            }
        }
    }
    return $users;
}

function _cmctx_get_curriculum_containing_course($instance_id) {
    require_once(CURMAN_DIRLOCATION . '/lib/curriculumcourse.class.php');
    global $CURMAN;
    $result = $CURMAN->db->get_records(CURCRSTABLE, 'courseid', $instance_id, '', 'curriculumid');
    if ($result) {
        return $result;
    } else {
        return array();
    }
}

function _cmctx_get_track_containing_class($instance_id) {
    require_once(CURMAN_DIRLOCATION . '/lib/track.class.php');
    global $CURMAN;
    $result = $CURMAN->db->get_records(TRACKCLASSTABLE, 'classid', $instance_id, '', 'trackid');
    if ($result) {
        return $result;
    } else {
        return array();
    }
}

function _cmctx_get_cluster_containing_user($instance_id) {
    require_once(CURMAN_DIRLOCATION . '/lib/usercluster.class.php');
    global $CURMAN;
    $result = $CURMAN->db->get_records(CLSTUSERTABLE, 'userid', $instance_id, '', 'clusterid');
    if ($result) {
        return $result;
    } else {
        return array();
    }
}

?>
