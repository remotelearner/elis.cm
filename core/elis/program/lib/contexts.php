<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2011 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once elis::lib('data/data_filter.class.php');

function get_contexts_by_capability_for_user($contextlevel, $capability, $userid, $doanything=true) {
    return pm_context_set::for_user_with_capability($contextlevel, $capability, $userid, $doanything);
}

class pm_context_set {
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
     * The name of the context level used to create this set.
     */
    var $contextlevel;

    /**
     * Fetch the contexts where the user has a given capability.  This only works
     * with the CM context levels.
     *
     * Assumes that the user does not have "too many" role assignments.  Assumes
     * the user has no "prevents"/"prohibits" roles.
     */
    static function for_user_with_capability($contextlevel, $capability, $userid=null, $doanything=true) {
        global $USER, $DB;

        static $pm_context_parents = array('track' => array('curriculum'),
                                           'course' => array('curriculum'),
                                           'class' => array('course','track'),
                                           'user' => array('cluster'));

        if ($userid === null) {
            $userid = $USER->id;
        }

        $obj = new pm_context_set();

        $obj->contextlevel = $contextlevel;

        // if the user has the capability at the system level (or has the
        // manage master capability), we can stop here
        if (has_capability($capability, get_context_instance(CONTEXT_SYSTEM), $userid, $doanything) ||
            has_capability('elis/program:manage', get_context_instance(CONTEXT_SYSTEM), $userid, $doanything)) {
            $obj->contexts = array('system' => 1);
            return $obj;
        }

        $contexts = array($contextlevel => array());

        // find all contexts at the given context level where the user has a direct
        // role assignment

        $ctxlevel = context_elis_helper::get_level_from_name($contextlevel);
        $ctxclass = context_elis_helper::get_class_for_level($ctxlevel);

        $sql = "SELECT c.id, c.instanceid
                  FROM {role_assignments} ra
                  JOIN {context} c ON ra.contextid = c.id
                 WHERE ra.userid = $userid
                   AND c.contextlevel = ".$ctxlevel;

        $possiblecontexts = $DB->get_recordset_sql($sql);
        foreach ($possiblecontexts as $c) {
            $context = $ctxclass::instance($c->instanceid);

            if (has_capability($capability, $context, $userid, $doanything)) {
                $contexts[$contextlevel][] = $context->__get('instanceid');
            }
        }
        if (empty($contexts[$contextlevel])) {
            unset($contexts[$contextlevel]);
        }

        // look in the parent contexts
        if (isset($pm_context_parents[$contextlevel])) {
            foreach ($pm_context_parents[$contextlevel] as $parentlevel) {
                $parent = pm_context_set::for_user_with_capability($parentlevel, $capability, $userid, $doanything);
                $contexts = array_merge($contexts, $parent->contexts);
            }
        }

        $obj->contexts = $contexts;
        return $obj;
    }

    /**
     * Tests whether the context set is empty
     */
    function is_empty() {
        return empty($this->contexts);
    }

    /**
     * Returns a filter object that will limit the context instances to those
     * in the context set.
     *
     * @param string $idfieldname the field name that willcontain the context
     * instance IDs to test
     * @param string $contextlevel context level
     *
     * @return data_filter SQL WHERE clause
     */
    public function get_filter($idfieldname, $contextlevel=null) {
        if ($contextlevel === null) {
            $contextlevel = $this->contextlevel;
        }

        if (isset($this->contexts['system'])) {
            return new AND_filter(array()); // empty AND filter = TRUE
        }

        $where = call_user_func(array($this,"_filter_for_$contextlevel"), $idfieldname);
        if (empty($where)) {
            return new select_filter('FALSE');
        } else {
            return new OR_filter($where);
        }
    }

    function _filter_for_curriculum($idfieldname) {
        $where = array();
        if (isset($this->contexts['curriculum'])) {
            $where[] = new in_list_filter($idfieldname, $this->contexts['curriculum']);
        }
        return $where;
    }

    function _filter_for_track($idfieldname) {
        $where = array();
        if (isset($this->contexts['track'])) {
            $where[] = new in_list_filter($idfieldname, $this->contexts['track']);
        }
        if (isset($this->contexts['curriculum'])) {
            // yuck
            $where[] = new join_filter($idfieldname, track::TABLE, 'id',
                                       new in_list_filter('curid', $this->contexts['curriculum']));
        }
        return $where;
    }

    function _filter_for_course($idfieldname) {
        $where = array();
        if (isset($this->contexts['course'])) {
            $where[] = new in_list_filter($idfieldname, $this->contexts['course']);
        }
        if (isset($this->contexts['curriculum'])) {
            $where[] = new join_filter($idfieldname, curriculumcourse::TABLE, 'courseid',
                                       new in_list_filter('curriculumid', $this->contexts['curriculum']),
                                       false, false);
        }
        return $where;
    }

    function _filter_for_class($idfieldname) {
        $where = array();
        if (isset($this->contexts['class'])) {
            $where[] = new in_list_filter($idfieldname, $this->contexts['class']);
        }
        if (isset($this->contexts['course']) || isset($this->contexts['curriculum'])) {
            // yuck
            $where[] = new join_filter($idfieldname, pmclass::TABLE, 'id',
                                       $this->get_filter('courseid', 'course'));
        }
        if (isset($this->contexts['track']) || isset($this->contexts['curriculum'])) {
            $where[] = new join_filter($idfieldname, trackassignment::TABLE, 'classid',
                                       $this->get_filter('trackid', 'track'),
                                       false, false);
        }
        return $where;
    }

    function _filter_for_cluster($idfieldname) {
        $where = array();
        if (isset($this->contexts['cluster'])) {
            $where[] = new in_list_filter($idfieldname, $this->contexts['cluster']);
            // cross fingers and hope that the user doesn't have too many clusters
            foreach ($this->contexts['cluster'] as $cluster) {
                $context = context_elis_userset::instance($cluster);
                $pattern = $context->path . '/%';
                $where[] = new join_filter($idfieldname, 'context', 'instanceid',
                                           new AND_filter(array(new field_filter('path', $pattern, field_filter::LIKE),
                                                                new field_filter('contextlevel', CONTEXT_ELIS_USERSET))));
            }
        }
        return $where;
    }

    function _filter_for_user($idfieldname) {
        $where = array();
        if (isset($this->contexts['user'])) {
            $where[] = new in_list_filter($idfieldname, $this->contexts['user']);
        }
        if (isset($this->contexts['cluster'])) {
            $where[] = new join_filter($idfieldname, clusterassignment::TABLE, 'userid',
                                       $this->get_filter('clusterid', 'cluster'),
                                       false, false);
        }
        return $where;
    }


    function context_allowed($id, $contextlevel=null) {
        if ($contextlevel === null) {
            $contextlevel = $this->contextlevel;
        }

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
        global $DB;
        if (isset($this->contexts['curriculum'])
            && $DB->record_exists_select('crlm_track',
                                               "id = $id AND curid IN (".implode(',',$this->contexts['curriculum']).')')) {
            return true;
        }
        return false;
    }

    function _course_allowed($id) {
        global $DB;
        if (isset($this->contexts['curriculum'])
            && $DB->record_exists_select('crlm_curriculum_course',
                                               "courseid = $id AND curriculumid IN (".implode(',',$this->contexts['curriculum']).')')) {
            return true;
        }
        return false;
    }

    function _class_allowed($id) {
        global $DB;
        if (isset($this->contexts['course'])
            && $DB->record_exists_select('crlm_class',
                                                 "id = $id AND courseid IN (".implode(',',$this->contexts['course']).')')) {
            return true;
        }
        if (isset($this->contexts['track'])
            && $DB->record_exists_select('crlm_track_class',
                                                 "classid = $id AND trackid IN (".implode(',',$this->contexts['track']).')')) {
            return true;
        }
        if (isset($this->contexts['curriculum']))  {
            $sql = "SELECT 'x'
                      FROM {crlm_track_class} trkcls
                      JOIN {crlm_track} trk ON trk.id = trkcls.trackid
                     WHERE trkcls.classid = $id AND trk.curid IN (".implode(',',$this->contexts['curriculum']).")
              UNION SELECT 'x'
                      FROM {crlm_curriculum_course} curcrs
                      JOIN {crlm_class} cls ON curcrs.courseid = cls.courseid
                     WHERE cls.id = $id AND curcrs.curriculumid IN (".implode(',',$this->contexts['curriculum']).')';
            if ($DB->record_exists_sql($sql)) {
                return true;
            }
        }
        return false;
    }

    function _cluster_allowed($id) {
        global $DB;
        if (isset($this->contexts['cluster'])) {
            $context = context_elis_userset::instance($id);
            $ancestorids = substr(str_replace('/',',',$context->path),1);
            $select = "id IN ($ancestorids)
                   AND instanceid IN (".implode(',',$this->contexts['cluster']).")
                   AND contextlevel = ".CONTEXT_ELIS_USERSET;
            return $DB->record_exists_select('context', $select);
        }
        return false;
    }

    function _user_allowed($id) {
        global $DB;
        if (isset($this->contexts['cluster'])
            && clusterassignment::exists(array(new field_filter('userid', $id),
                                               new in_list_filter('clusterid', $this->contexts['cluster'])))) {
            return true;
        }
        if (isset($this->contexts['cluster'])) {
            $filter = $this->get_filter('clusterid', 'cluster');
            return clusterassignment::count(array(new field_filter('userid', $id),
                                                  $filter));
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
function pm_get_users_by_capability($contextlevel, $instance_id, $capability) {
    static $pm_context_extra_parents = array('course' => array('curriculum'),
                                             'class' => array('track'),
                                             'user' => array('cluster'));

    $ctxclass = context_elis_helper::get_class_for_level(context_elis_helper::get_level_from_name($contextlevel));

    $context = $ctxclass::instance($instance_id);
    $users = get_users_by_capability($context, $capability);
    $users = $users ? $users : array();

    // look in the parent contexts
    if (isset($pm_context_extra_parents[$contextlevel])) {
        foreach ($pm_context_extra_parents[$contextlevel] as $parentlevel) {
            $parents = call_user_func("_cmctx_get_{$parentlevel}_containing_{$contextlevel}", $instance_id);
            foreach ($parents as $parentid => $parent) {
                $newusers = pm_get_users_by_capability($parentlevel, $parentid, $capability);
                if ($newusers) {
                    $users = $users + $newusers;
                }
            }
            unset($parents);
        }
    }
    return $users;
}

function _cmctx_get_curriculum_containing_course($instance_id) {
    global $DB;
    require_once elispm::lib('data/curriculumcourse.class.php');
    $result =$DB->get_recordset(curriculumcourse::TABLE, array('courseid' => $instance_id), '', 'curriculumid');
    return ($result->valid() === true) ? $result : array();
}

function _cmctx_get_track_containing_class($instance_id) {
    global $DB;
    require_once elispm::lib('data/track.class.php');
    $result = $DB->get_recordset(trackassignment::TABLE, array('classid' => $instance_id), '', 'trackid');
    return ($result->valid() === true) ? $result : array();
}

function _cmctx_get_cluster_containing_user($instance_id) {
    global $DB;
    require_once elispm::lib('data/clusterassignment.class.php');
    $result = $DB->get_recordset(clusterassignment::TABLE, array('userid' => $instance_id), '', 'DISTINCT clusterid');
    return ($result->valid() === true) ? $result : array();
}
