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

require_once(dirname(__FILE__).'/../../../../config.php');
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(elis::lib('data/data_object_with_custom_fields.class.php'));
require_once(elispm::lib('data/curriculum.class.php'));
require_once(elispm::lib('data/curriculumcourse.class.php'));
require_once(elispm::lib('data/curriculumstudent.class.php'));
require_once(elispm::lib('data/userset.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/usertrack.class.php'));
require_once(elispm::lib('data/track.class.php'));

class clustertrack extends elis_data_object {
    const TABLE = 'crlm_cluster_track';

    var $verbose_name = 'clustertrack';

    /**
     * User ID-number
     * @var    char
     * @length 255
     */
    protected $_dbfield_id;
    protected $_dbfield_clusterid;
    protected $_dbfield_trackid;
    protected $_dbfield_autoenrol;
    protected $_dbfield_autounenrol;
    protected $_dbfield_enrolmenttime;

    private $location;
    private $templateclass;

    static $associations = array(
        'track' => array(
            'class' => 'track',
            'idfield' => 'trackid'
        ),
        'userset' => array(
            'class' => 'userset',
            'idfield' => 'clusterid'
        )
    );

    static $validation_rules = array(
        array('validation_helper', 'is_unique_clusterid_trackid')
    );

    function get_verbose_name() {
        return $this->verbose_name;
    }

    function __toString() {
        // TO-DO: what should this return?
        return $this->id;
    }

    /**
     * Associates a cluster with a track.
     */
    public static function associate($cluster, $track, $autounenrol=true, $autoenrol=true) {
        global $DB;

        // make sure we don't double-associate
        if ($DB->record_exists(self::TABLE, array('clusterid' => $cluster,
                                                  'trackid'   => $track)))
        {
            return;
        }

        // ELIS-7582
        @set_time_limit(0);

        $record = new clustertrack();
        $record->clusterid = $cluster;
        $record->trackid = $track;
        $record->autoenrol = $autoenrol;
        $record->autounenrol = $autounenrol;
        $record->save();

        // Enrol all users in the cluster into track.
        $sql = 'SELECT uc.*
                FROM {' . clusterassignment::TABLE . '} as uc
                JOIN {' . user::TABLE . '} as u
                ON uc.userid = u.id
                WHERE uc.clusterid = ?
                ORDER BY u.lastname';

        $params = array($cluster);
        $users = $DB->get_recordset_sql($sql, $params);
        if (!empty($autoenrol)) {
            foreach ($users as $user) {
                usertrack::enrol($user->userid, $track);
            }
        }
        unset($users);

        events_trigger('pm_userset_track_associated', $record);

    }

    /**
     * Disassociates a cluster from a track.
     */
    public function delete() {

        if ($this->autounenrol) {
            // ELIS-7582
            @set_time_limit(0);

            // Unenrol all users in the cluster from the track (unless they are
            // in another cluster associated with the track and autoenrolled by
            // that cluster).  Only work on users that were autoenrolled in the
            // track by the cluster.

            // $filter selects all users enrolled in the track due to being in
            // a(nother) cluster associated with the track.  We will left-join
            // with it, and only select non-matching records.
            $params = array();
            $filter = 'SELECT u.userid '
                . 'FROM {' . clusterassignment::TABLE . '} u '
                . 'INNER JOIN {' . usertrack::TABLE . '} ut ON u.userid = ut.userid '
                . 'WHERE ut.trackid = :trackid AND u.autoenrol=\'1\'';
            $params['trackid'] = $this->trackid;

            $sql = 'SELECT usrtrk.id '
                . 'FROM {' . clusterassignment::TABLE . '} cu '
                . 'INNER JOIN {' . usertrack::TABLE . '} usrtrk ON cu.userid = usrtrk.userid AND usrtrk.trackid = \'' . $this->trackid . '\' '
                . 'LEFT OUTER JOIN (' . $filter . ') f ON f.userid = cu.userid '
                . 'WHERE cu.clusterid = :clusterid AND cu.autoenrol=\'1\' AND f.userid IS NULL';
            $params['clusterid'] = $this->clusterid;

            $usertracks = $this->_db->get_recordset_sql($sql, $params);
            foreach ($usertracks as $usertrack) {
                $ut = new usertrack($usertrack->id);
                $ut->unenrol();
            }
            unset($usertracks);
        }

        parent::delete();
    }

    /// collection functions. (These may be able to replaced by a generic container/listing class)

    /**
     * Get a list of the clusters assigned to this track.
     *
     * @uses            $CURMAN
     * @param  int      $trackid            The track id
     * @param  int      $parent_cluster_id  Cluster that must be the parent of track's clusters
     * @param  int      $startrec           The index of the record to start with
     * @param  int      $perpage            How many records to include
     * @param  array    $extrafilters       Additional filters to apply to the count
     * @param  array                        The appropriate cluster records
     */
    public static function get_clusters($trackid = 0, $parent_cluster_id = 0, $sort = 'name', $dir = 'ASC', $startrec = 0, $perpage = 0, $extrafilters = array()) {
        global $DB;

        if (empty($DB)) {
            return NULL;
        }

        //require plugin code if enabled
        $plugins = get_plugin_list('pmplugins');
        $display_priority_enabled = isset($plugins['userset_display_priority']);
        if($display_priority_enabled) {
            require_once(elis::plugin_file('pmplugins_userset_display_priority', 'lib.php'));
        }

        $select  = 'SELECT clsttrk.id, clsttrk.clusterid, clst.name, clst.display, clsttrk.autoenrol ';
        $tables  = 'FROM {' . self::TABLE . '} clsttrk ';
        $join    = 'LEFT JOIN {' . userset::TABLE . '} clst '.
                   'ON clst.id = clsttrk.clusterid ';

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
            userset_display_priority_append_sort_data('clst.id', $select, $join);
        }

        $params = array();
        $where   = 'WHERE clsttrk.trackid = :trackid ';
        $params['trackid'] = $trackid;
        if(!empty($parent_cluster_id)) {
            $where .= " AND clst.parent = :parent_cluster_id ";
            $params['parent_cluster_id'] = $parent_cluster_id;
        }

        if (!empty($extrafilters['contexts'])) {
            //apply a filter related to filtering on particular PM cluster contexts
     	    $filter_object = $extrafilters['contexts']->get_filter('id', 'cluster');
	        $filter_sql = $filter_object->get_sql(false, 'clst', SQL_PARAMS_NAMED);

    	    if (!empty($filter_sql)) {
                //user does not have access at the system context
        	    $where .= 'AND ('.$filter_sql['where'].') ';
            	$params = array_merge($params, $filter_sql['where_parameters']);
        	}
    	}


        $group   = 'GROUP BY clsttrk.id ';

        $sort_clause = 'ORDER BY ' . implode($sort_clauses, ', ') . ' ';

        $sql = $select.$tables.$join.$where.$group.$sort_clause;

        return $DB->get_records_sql($sql, $params, $startrec, $perpage);
    }

    /**
     * Calculates the number of clusters associated to the provided track
     *
     * @param   int    $trackid            The track to check associations for
     * @param   int    $parent_cluster_id  Cluster that must be the parent of track's clusters
     * @param   array  $extrafilters       Additional filters to apply to the count
     * @return                             The number of associated records
     */
    public static function count_clusters($trackid = 0, $parent_cluster_id = 0, $extrafilters = array()) {
        global $DB;

        if (empty($DB)) {
            return 0;
        }

        $params = array();
        $select  = 'SELECT COUNT(*) ';
        $tables  = 'FROM {' . self::TABLE . '} clsttrk ';
        $join    = 'LEFT JOIN {' . userset::TABLE . '} clst '.
                   'ON clst.id = clsttrk.clusterid ';
        $where   = 'WHERE clsttrk.trackid = :trackid ';
        $params['trackid'] = $trackid;
        if(!empty($parent_cluster_id)) {
            $where .= " AND clst.parent = :parent_cluster_id ";
            $params['parent_cluster_id'] = $parent_cluster_id;
        }

        if (!empty($extrafilters['contexts'])) {
            //apply a filter related to filtering on particular PM cluster contexts
     	    $filter_object = $extrafilters['contexts']->get_filter('id', 'cluster');
	        $filter_sql = $filter_object->get_sql(false, 'clst', SQL_PARAMS_NAMED);

    	    if (!empty($filter_sql)) {
                //user does not have access at the system context
        	    $where .= 'AND ('.$filter_sql['where'].') ';
            	$params = array_merge($params, $filter_sql['where_parameters']);
        	}
    	}

        $sort    = 'ORDER BY clst.name ASC ';

        $sql = $select.$tables.$join.$where.$sort;

        return $DB->count_records_sql($sql, $params);
    }


    /**
     * Get a list of the tracks assigned to this cluster.
     *
     * @uses $CURMAN
     * @param int $clusterid The cluster id.
     */
    public static function get_tracks($clusterid = 0, $sort, $dir) {
        global $DB;

        if (empty($DB)) {
            return NULL;
        }

        $select  = 'SELECT clsttrk.id, clsttrk.trackid, trk.idnumber, trk.name, trk.description, trk.startdate, trk.enddate, clsttrk.autoenrol ';
        $tables  = 'FROM {' . self::TABLE . '} clsttrk ';
        $join    = 'LEFT JOIN {' . track::TABLE . '} trk '.
          'ON trk.id = clsttrk.trackid ';
        $where   = 'WHERE clsttrk.clusterid = ? ';
        $sort    = "ORDER BY {$sort} {$dir} ";
        $params = array($clusterid);

        $sql = $select.$tables.$join.$where.$sort;

        return $DB->get_records_sql($sql, $params);
    }

    public static function delete_for_cluster($id) {
        global $DB;

        return $DB->delete_records(self::TABLE, array('clusterid'=> $id));
    }

    public static function delete_for_track($id) {
        global $DB;

        return $DB->delete_records(self::TABLE, array('trackid'=> $id));
    }

    /**
     * Updates the autoenrol flag for a particular cluster-track association
     *
     * @param   int     $association_id  The id of the appropriate association record
     * @param   int     $autoenrol       The new autoenrol value
     *
     * @return  object                   The updated record
     */
    public static function update_autoenrol($association_id, $autoenrol) {
        global $DB;

        $old_autoenrol = $DB->get_field(self::TABLE, 'autoenrol', array('id' => $association_id));

        // update the flag on the association record
        $update_record = new stdClass;
        $update_record->id = $association_id;
        $update_record->autoenrol = $autoenrol;
        $result = $DB->update_record(self::TABLE, $update_record);

        if (!empty($autoenrol) && empty($old_autoenrol) && ($cluster = $DB->get_field(self::TABLE, 'clusterid',
            array('id' => $association_id))) && ($track = $DB->get_field(self::TABLE, 'trackid', array('id' => $association_id)))) {
            // ELIS-7582
            @set_time_limit(0);

            // Enrol all users in the cluster into track.
            $sql = 'SELECT uc.*
                    FROM {' . clusterassignment::TABLE . '} as uc
                    JOIN {' . user::TABLE . '} as u
                    ON uc.userid = u.id
                    WHERE uc.clusterid = ?
                    ORDER BY u.lastname';
            $params = array($cluster);

            $users = $DB->get_recordset_sql($sql, $params);
            foreach ($users as $user) {
                usertrack::enrol($user->userid, $track);
            }
            unset($users);
        }

        return $result;
    }

    public function set_from_data($data) {
        $this->_load_data_from_record($data, true);
    }
}
