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


require_once CURMAN_DIRLOCATION . '/lib/datarecord.class.php';
require_once CURMAN_DIRLOCATION . '/lib/user.class.php';
require_once CURMAN_DIRLOCATION . '/lib/curriculum.class.php';
require_once CURMAN_DIRLOCATION . '/lib/curriculumcourse.class.php';
require_once CURMAN_DIRLOCATION . '/lib/cluster.class.php';
require_once CURMAN_DIRLOCATION . '/lib/curriculumstudent.class.php';
require_once CURMAN_DIRLOCATION . '/lib/usertrack.class.php';
require_once CURMAN_DIRLOCATION . '/lib/track.class.php';


define ('CLSTCURTABLE', 'crlm_cluster_curriculum');
define ('CLSTTRKTABLE', 'crlm_cluster_track');

class clustercurriculum extends datarecord {
    /**
     * Constructor.
     *
     * @param int|object|array $data The data id of a data record or data
     * elements to load manually.
     *
     */
    function clustercurriculum($data = false) {
        parent::datarecord();

        $this->set_table(CLSTCURTABLE);
        $this->add_property('id', 'int');
        $this->add_property('clusterid', 'int');
        $this->add_property('curriculumid', 'int');
        $this->add_property('autoenrol', 'int');

        if (is_numeric($data)) {
            $this->data_load_record($data);
        } else if (is_array($data)) {
            $this->data_load_array($data);
        } else if (is_object($data)) {
            $this->data_load_array(get_object_vars($data));
        }
    }

	public static function delete_for_curriculum($id) {
		global $CURMAN;

		return $CURMAN->db->delete_records(CLSTCURTABLE, 'curriculumid', $id);
	}

    // defer loading of sub-data elements until requested
    function __get($name) {
        if ($name == 'cluster' && !empty($this->clusterid)) {
            $this->cluster = new cluster($this->clusterid);
            return $this->cluster;
        }
        if ($name == 'curriculum' && !empty($this->curriculumid)) {
            $this->curriculum = new curriculum($this->curriculumid);
            return $this->curriculum;
        }
        return null;
    }

    /**
     * Associates a cluster with a curriculum.
     */
    static function associate($cluster, $curriculum, $autoenrol=true) {
        global $CURMAN;

        $db = $CURMAN->db;

        // make sure we don't double-associate
        if ($db->record_exists(CLSTCURTABLE, 'clusterid', $cluster,
                               'curriculumid', $curriculum))
        {
            return;
        }

        $record = new clustercurriculum();
        $record->clusterid = $cluster;
        $record->autoenrol = !empty($autoenrol) ? 1 : 0;
        $record->curriculumid = $curriculum;
        $record->data_insert_record();

        /* Assign all users in the cluster with curriculum.  Don't assign users
         * if already assigned */
        /**
         * @todo we may need to change this if associating a user with a
         * curriculum does anything more complicated
         */

        //only insert users if we are auto-enrolling
        if(!empty($autoenrol)) {
            $timenow = time();
            $sql = 'INSERT INTO ' . $db->prefix_table(CURASSTABLE) . ' '
                . '(userid, curriculumid, timecreated, timemodified) '
                . 'SELECT DISTINCT u.id, ' . $curriculum . ', ' . $timenow . ', ' . $timenow. ' '
                . 'FROM ' . $db->prefix_table(CLSTUSERTABLE) . ' clu '
                . 'INNER JOIN ' . $db->prefix_table(USRTABLE) . ' u ON u.id = clu.userid '
                . 'LEFT OUTER JOIN ' . $db->prefix_table(CURASSTABLE) . ' ca ON ca.userid = u.id AND ca.curriculumid = \'' . $curriculum . '\' '
                . 'WHERE clu.clusterid = \'' . $cluster . '\' AND ca.curriculumid IS NULL';
            $db->execute_sql($sql,false);
        }

        events_trigger('crlm_cluster_curriculum_associated', $record);
    }

    /// collection fetching functions. (These may be able to replaced by a generic container/listing class)

    /**
     * Get a list of the clusters assigned to this curriculum.
     *
     * @uses           $CURMAN
     * @param   int    $curriculumid     The cluster id
     * @param   int    $parentclusterid  If non-zero, a required direct-parent cluster
     * @param   int    $startrecord      The index of the record to start with
     * @param   int    $perpage          The number of records to include
     * @return  array                    The appropriate cluster records
     */
    static function get_clusters($curriculumid = 0, $parentclusterid = 0, $sort = 'name', $dir = 'ASC', $startrec = 0, $perpage = 0) {
        global $CURMAN;

        if (empty($CURMAN->db)) {
            return NULL;
        }

        //require plugin code if enabled
        $display_priority_enabled = in_array('cluster_display_priority', get_list_of_plugins('curriculum/plugins'));
        if($display_priority_enabled) {
            require_once(CURMAN_DIRLOCATION . '/plugins/cluster_display_priority/lib.php');
        }

        $select  = 'SELECT clstcur.id, clstcur.clusterid, clst.name, clst.display, clstcur.autoenrol ';
        $tables  = 'FROM ' . $CURMAN->db->prefix_table(CLSTCURTABLE) . ' clstcur ';
        $join    = 'LEFT JOIN ' . $CURMAN->db->prefix_table(CLSTTABLE) . ' clst '.
                   'ON clst.id = clstcur.clusterid ';

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

        $where   = 'WHERE clstcur.curriculumid = '.$curriculumid.' ';

        //apply the parent-cluster condition if applicable
        if(!empty($parentclusterid)) {
            $where .= " AND clst.parent = {$parentclusterid} ";
        }

        $group   = 'GROUP BY clstcur.id ';

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

        $sql = $select.$tables.$join.$where.$group.$sort_clause.$limit;

        return $CURMAN->db->get_records_sql($sql);
    }

    /**
     * Determine the number of clusters assigned to this curriculum
     *
     * @uses           $CURMAN
     * @param   int    $curriculumid     The cluster id
     * @param   int    $parentclusterid  If non-zero, a required direct-parent cluster
     * @return  int                      The number of appropriate records
     */
    function count_clusters($curriculumid = 0, $parentclusterid = 0) {
        global $CURMAN;

        if (empty($CURMAN->db)) {
            return NULL;
        }

        $select  = 'SELECT COUNT(*) ';
        $tables  = 'FROM ' . $CURMAN->db->prefix_table(CLSTCURTABLE) . ' clstcur ';
        $join    = 'LEFT JOIN ' . $CURMAN->db->prefix_table(CLSTTABLE) . ' clst '.
                   'ON clst.id = clstcur.clusterid ';
        $where   = 'WHERE clstcur.curriculumid = '.$curriculumid.' ';

        if(!empty($parentclusterid)) {
            $where .= " AND clst.parent = {$parentclusterid} ";
        }

        $sort    = 'ORDER BY clst.name ASC ';
        $limit = '';

        $sql = $select.$tables.$join.$where.$sort.$limit;

        return $CURMAN->db->count_records_sql($sql);


    }


    /**
     * Get a list of the curricula assigned to this cluster.
     *
     * @uses             $CURMAN
     * @param   int      $clusterid  The cluster id.
     * @return  array                The associated curriculum records
     */
    static function get_curricula($clusterid = 0, $startrec = 0, $perpage = 0, $sort = 'cur.name ASC') {
        global $CURMAN;

        if (empty($CURMAN->db)) {
            return NULL;
        }

        $select  = 'SELECT clstcur.id, clstcur.curriculumid, cur.idnumber, cur.name, cur.description, cur.reqcredits, COUNT(curcrs.id) as numcourses, clstcur.autoenrol ';
        $tables  = 'FROM ' . $CURMAN->db->prefix_table(CLSTCURTABLE) . ' clstcur ';
        $join    = 'LEFT JOIN ' . $CURMAN->db->prefix_table(CURTABLE) . ' cur '.
                   'ON cur.id = clstcur.curriculumid ';
        $join   .= 'LEFT JOIN ' . $CURMAN->db->prefix_table(CURCRSTABLE) . ' curcrs '.
                   'ON curcrs.curriculumid = cur.id ';
        $where   = 'WHERE clstcur.clusterid = '.$clusterid.' ';
        $group   = 'GROUP BY clstcur.id ';
        $sort    = "ORDER BY $sort ";
        if (!empty($perpage)) {
            if ($CURMAN->db->_dbconnection->databaseType == 'postgres7') {
                $limit = 'LIMIT ' . $perpage . ' OFFSET ' . $startrec;
            } else {
                $limit = 'LIMIT '.$startrec.', '.$perpage;
            }
        } else {
            $limit = '';
        }

        $sql = $select.$tables.$join.$where.$group.$sort.$limit;

        return $CURMAN->db->get_records_sql($sql);
    }

    /**
     * Determines the number of curricula assigned to the provided cluster
     *
     * @uses             $CURMAN
     * @param   int      $clusterid  The id of the cluster to check associations from
     * @return  int                  The number of associated curricula
     */
    static function count_curricula($clusterid = 0) {
        global $CURMAN;

        if (empty($CURMAN->db)) {
            return 0;
        }

        $select  = 'SELECT COUNT(*) ';
        $tables  = 'FROM ' . $CURMAN->db->prefix_table(CLSTCURTABLE) . ' clstcur ';
        $join    = 'LEFT JOIN ' . $CURMAN->db->prefix_table(CURTABLE) . ' cur '.
                   'ON cur.id = clstcur.curriculumid ';
        $where   = 'WHERE clstcur.clusterid = '.$clusterid.' ';
        $sort    = 'ORDER BY cur.idnumber ASC ';
        $groupby = 'GROUP BY cur.idnumber ';

        $sql = $select . $tables . $join . $where . $groupby . $sort;

        return $CURMAN->db->count_records_sql($sql);
    }

	public static function delete_for_cluster($id) {
    	global $CURMAN;

    	return $CURMAN->db->delete_records(CLSTCURTABLE, 'clusterid', $id);
	}

    /**
     * Updates the autoenrol flag for a particular cluster-curriculum association
     *
     * @param   int     $association_id  The id of the appropriate association record
     * @param   int     $autoenrol       The new autoenrol value
     *
     * @return  object                   The updated record
     */
	public static function update_autoenrol($association_id, $autoenrol) {
	    global $CURMAN;

        $db = $CURMAN->db;

        $old_autoenrol = get_field(CLSTCURTABLE, 'autoenrol', 'id', $association_id);

        //update the flag on the association record
	    $update_record = new stdClass;
	    $update_record->id = $association_id;
	    $update_record->autoenrol = $autoenrol;
	    $result = update_record(CLSTCURTABLE, $update_record);

	    if(!empty($autoenrol) and
	       empty($old_autoenrol) and
	       $curriculum = get_field(CLSTCURTABLE, 'curriculumid', 'id', $association_id) and
	       $cluster = get_field(CLSTCURTABLE, 'clusterid', 'id', $association_id)) {
            $timenow = time();
            $sql = 'INSERT INTO ' . $db->prefix_table(CURASSTABLE) . ' '
                . '(userid, curriculumid, timecreated, timemodified) '
                . 'SELECT DISTINCT u.id, ' . $curriculum . ', ' . $timenow . ', ' . $timenow. ' '
                . 'FROM ' . $db->prefix_table(CLSTUSERTABLE) . ' clu '
                . 'INNER JOIN ' . $db->prefix_table(USRTABLE) . ' u ON u.id = clu.userid '
                . 'LEFT OUTER JOIN ' . $db->prefix_table(CURASSTABLE) . ' ca ON ca.userid = u.id AND ca.curriculumid = \'' . $curriculum . '\' '
                . 'WHERE clu.clusterid = \'' . $cluster . '\' AND ca.curriculumid IS NULL';
            $db->execute_sql($sql,false);
	    }

	    return $result;
	}
}


class clustertrack extends datarecord {
    /**
     * Constructor.
     *
     * @param int|object|array $data The data id of a data record or data
     * elements to load manually.
     *
     */
    function clustertrack($data = false) {
        parent::datarecord();

        $this->set_table(CLSTTRKTABLE);
        $this->add_property('id', 'int');
        $this->add_property('clusterid', 'int');
        $this->add_property('trackid', 'int');
        $this->add_property('autoenrol', 'int');
        $this->add_property('autounenrol', 'int');

        if (is_numeric($data)) {
            $this->data_load_record($data);
        } else if (is_array($data)) {
            $this->data_load_array($data);
        } else if (is_object($data)) {
            $this->data_load_array(get_object_vars($data));
        }
    }

    // defer loading of sub-data elements until requested
    function __get($name) {
        if ($name == 'cluster' && !empty($this->clusterid)) {
            $this->cluster = new cluster($this->clusterid);
            return $this->cluster;
        }
        if ($name == 'track' && !empty($this->trackid)) {
            $this->track = new track($this->trackid);
            return $this->track;
        }
        return null;
    }

    /**
     * Associates a cluster with a track.
     */
    static function associate($cluster, $track, $autounenrol=true, $autoenrol=true) {
        global $CURMAN;

        $db = $CURMAN->db;

        // make sure we don't double-associate
        if ($db->record_exists(CLSTTRKTABLE, 'clusterid', $cluster,
                               'trackid', $track))
        {
            return;
        }

        $record = new clustertrack();
        $record->clusterid = $cluster;
        $record->trackid = $track;
        $record->autoenrol = $autoenrol;
        $record->autounenrol = $autounenrol;
        $record->data_insert_record();

        // Enrol all users in the cluster into track.
        $sql = 'SELECT uc.*
                FROM ' . $CURMAN->db->prefix_table(CLSTASSTABLE) . ' as uc
                JOIN ' . $CURMAN->db->prefix_table(USRTABLE) . ' as u
                ON uc.userid = u.id
                WHERE uc.clusterid = '.$cluster.' AND uc.autoenrol = 1
                ORDER BY u.lastname';

        $users = $db->get_records_sql($sql);

//        $users = $db->get_records(CLSTUSERTABLE, 'clusterid', $cluster);
        if ($users && !empty($autoenrol)) {
            foreach ($users as $user) {
                usertrack::enrol($user->userid, $track);
            }
        }

        events_trigger('crlm_cluster_track_associated', $record);

    }

    /**
     * Disassociates a cluster from a track.
     */
    public function delete() {
        global $CURMAN;
        $return = $this->data_delete_record();

        if ($return && $this->autounenrol) {
            $db = $CURMAN->db;
            // Unenrol all users in the cluster from the track (unless they are
            // in another cluster associated with the track and autoenrolled by
            // that cluster).  Only work on users that were autoenrolled in the
            // track by the cluster.

            // $filter selects all users enrolled in the track due to being in
            // a(nother) cluster associated with the track.  We will left-join
            // with it, and only select non-matching records.
            $filter = 'SELECT u.userid '
                . 'FROM ' . $db->prefix_table(CLSTUSERTABLE) . ' u '
                . 'INNER JOIN ' . $db->prefix_table(USRTRKTABLE) . ' ut ON u.userid = ut.userid '
                . 'WHERE ut.trackid = \'' . $this->trackid . '\' AND u.autoenrol=\'1\'';

            $sql = 'SELECT usrtrk.id '
                . 'FROM ' . $db->prefix_table(CLSTUSERTABLE) . ' cu '
                . 'INNER JOIN ' . $db->prefix_table(USRTRKTABLE) . ' usrtrk ON cu.userid = usrtrk.userid AND usrtrk.trackid = \'' . $this->trackid . '\' '
                . 'LEFT OUTER JOIN (' . $filter . ') f ON f.userid = cu.userid '
                . 'WHERE cu.clusterid = \'' . $this->clusterid . '\' AND cu.autoenrol=\'1\' AND f.userid IS NULL';

            $usertracks = $db->get_records_sql($sql);

            if ($usertracks) {
                foreach ($usertracks as $usertrack) {
                    $ut = new usertrack($usertrack->id);
                    $ut->unenrol();
                }
            }
        }

        return $return;
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
     * @param  array                        The appropriate cluster records
     */
    static function get_clusters($trackid = 0, $parent_cluster_id = 0, $sort = 'name', $dir = 'ASC', $startrec = 0, $perpage = 0) {
        global $CURMAN;

        if (empty($CURMAN->db)) {
            return NULL;
        }

        //require plugin code if enabled
        $display_priority_enabled = in_array('cluster_display_priority', get_list_of_plugins('curriculum/plugins'));
        if($display_priority_enabled) {
            require_once(CURMAN_DIRLOCATION . '/plugins/cluster_display_priority/lib.php');
        }

        $select  = 'SELECT clsttrk.id, clsttrk.clusterid, clst.name, clst.display, clsttrk.autoenrol ';
        $tables  = 'FROM ' . $CURMAN->db->prefix_table(CLSTTRKTABLE) . ' clsttrk ';
        $join    = 'LEFT JOIN ' . $CURMAN->db->prefix_table(CLSTTABLE) . ' clst '.
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
            cluster_display_priority_append_sort_data('clst.id', $select, $join);
        }

        $where   = 'WHERE clsttrk.trackid = '.$trackid.' ';
        if(!empty($parent_cluster_id)) {
            $where .= " AND clst.parent = {$parent_cluster_id} ";
        }
        $group   = 'GROUP BY clsttrk.id ';

        $sort_clause = 'ORDER BY ' . implode($sort_clauses, ', ') . ' ';

        $limit = '';
        if (!empty($perpage)) {
            if ($CURMAN->db->_dbconnection->databaseType == 'postgres7') {
                $limit = 'LIMIT ' . $perpage . ' OFFSET ' . $startrec;
            } else {
                $limit = 'LIMIT '.$startrec.', '.$perpage;
            }
        } else {
            $limit = '';
        }

        $sql = $select.$tables.$join.$where.$group.$sort_clause.$limit;

        return $CURMAN->db->get_records_sql($sql);
    }

    /**
     * Calculates the number of clusters associated to the provided track
     *
     * @param   int  $trackid            The track to check associations for
     * @param   int  $parent_cluster_id  Cluster that must be the parent of track's clusters
     * @return                           The number of associated records
     */
    static function count_clusters($trackid = 0, $parent_cluster_id = 0) {
        global $CURMAN;

        if (empty($CURMAN->db)) {
            return 0;
        }

        $select  = 'SELECT COUNT(*) ';
        $tables  = 'FROM ' . $CURMAN->db->prefix_table(CLSTTRKTABLE) . ' clsttrk ';
        $join    = 'LEFT JOIN ' . $CURMAN->db->prefix_table(CLSTTABLE) . ' clst '.
                   'ON clst.id = clsttrk.clusterid ';
        $where   = 'WHERE clsttrk.trackid = '.$trackid.' ';
        if(!empty($parent_cluster_id)) {
            $where .= " AND clst.parent = {$parent_cluster_id} ";
        }
        $sort    = 'ORDER BY clst.name ASC ';

        $sql = $select.$tables.$join.$where.$sort;

        return $CURMAN->db->count_records_sql($sql);
    }


    /**
     * Get a list of the tracks assigned to this cluster.
     *
     * @uses $CURMAN
     * @param int $clusterid The cluster id.
     */
    static function get_tracks($clusterid = 0) {
        global $CURMAN;

        if (empty($CURMAN->db)) {
            return NULL;
        }

        $select  = 'SELECT clsttrk.id, clsttrk.trackid, trk.idnumber, trk.name, trk.description, trk.startdate, trk.enddate, clsttrk.autoenrol ';
        $tables  = 'FROM ' . $CURMAN->db->prefix_table(CLSTTRKTABLE) . ' clsttrk ';
        $join    = 'LEFT JOIN ' . $CURMAN->db->prefix_table(TRACKTABLE) . ' trk '.
          'ON trk.id = clsttrk.trackid ';
        $where   = 'WHERE clsttrk.clusterid = '.$clusterid.' ';
        $sort    = 'ORDER BY trk.idnumber ASC ';
        $limit = '';

        $sql = $select.$tables.$join.$where.$sort.$limit;

        return $CURMAN->db->get_records_sql($sql);
    }

	public static function delete_for_cluster($id) {
    	global $CURMAN;

    	return $CURMAN->db->delete_records(CLSTTRKTABLE, 'clusterid', $id);
	}

	public static function delete_for_track($id) {
    	global $CURMAN;

    	return $CURMAN->db->delete_records(CLSTTRKTABLE, 'trackid', $id);
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
	    global $CURMAN;

	    $db = $CURMAN->db;

	    $old_autoenrol = get_field(CLSTTRKTABLE, 'autoenrol', 'id', $association_id);

        //update the flag on the association record
	    $update_record = new stdClass;
	    $update_record->id = $association_id;
	    $update_record->autoenrol = $autoenrol;
	    $result = update_record(CLSTTRKTABLE, $update_record);

        if(!empty($autoenrol) and
           empty($old_autoenrol) and
           $cluster = get_field(CLSTTRKTABLE, 'clusterid', 'id', $association_id) and
           $track = get_field(CLSTTRKTABLE, 'trackid', 'id', $association_id)) {
            //Enrol all users in the cluster into track.
            $sql = 'SELECT uc.*
                    FROM ' . $CURMAN->db->prefix_table(CLSTASSTABLE) . ' as uc
                    JOIN ' . $CURMAN->db->prefix_table(USRTABLE) . ' as u
                    ON uc.userid = u.id
                    WHERE uc.clusterid = '.$cluster.' AND uc.autoenrol = 1
                    ORDER BY u.lastname';

            $users = $db->get_records_sql($sql);

            if ($users) {
                foreach ($users as $user) {
                    usertrack::enrol($user->userid, $track);
                }
            }
        }

        return $result;
	}
}

?>
