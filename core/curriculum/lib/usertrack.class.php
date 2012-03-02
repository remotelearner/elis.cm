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
require_once CURMAN_DIRLOCATION . '/lib/student.class.php';
require_once CURMAN_DIRLOCATION . '/lib/track.class.php';


define ('USRTRKTABLE', 'crlm_user_track');


class usertrack extends datarecord {
    /**
     * Constructor.
     *
     * @param int|object|array $data The data id of a data record or data
     * elements to load manually.
     *
     */
    function usertrack($data = false) {
        parent::datarecord();

        $this->set_table(USRTRKTABLE);
        $this->add_property('id', 'int');
        $this->add_property('userid', 'int', true);
        $this->add_property('trackid', 'int', true);

        if (is_numeric($data)) {
            $this->data_load_record($data);
        } else if (is_array($data)) {
            $this->data_load_array($data);
        } else if (is_object($data)) {
            $this->data_load_array(get_object_vars($data));
        }
    }

	public static function delete_for_user($id) {
		global $CURMAN;

		return $CURMAN->db->delete_records(USRTRKTABLE, 'userid', $id);
	}

	public static function delete_for_track($id) {
		global $CURMAN;

		return $CURMAN->db->delete_records(USRTRKTABLE, 'trackid', $id);
	}

    // defer loading of sub-data elements until requested
    function __get($name) {
        if ($name == 'user' && !empty($this->userid)) {
            $this->user = new user($this->userid);
            return $this->user;
        }
        if ($name == 'track' && !empty($this->trackid)) {
            $this->track = new track($this->trackid);
            return $this->track;
        }
        return null;
    }

    /**
     * Enrols a user in a track.
     *
     * @param int $userid The user id
     * @param int $trackid The track id
     */
    static function enrol($userid, $trackid) {
        global $CURMAN;

        // make sure we don't double-enrol
        if ($CURMAN->db->record_exists(USRTRKTABLE, 'userid', $userid,
                                       'trackid', $trackid)) {
            return false;
        }

        $record = new usertrack();
        $record->userid = $userid;
        $record->trackid = $trackid;
        $record->data_insert_record();

        $user = new user($userid);
        $track = new track($trackid);

        // add the student to the associated curriculum, if they're not already
        // enrolled
        if (!$CURMAN->db->record_exists(CURASSTABLE, 'userid', $userid,
                                        'curriculumid', $track->curid)) {
            $curstu = new curriculumstudent();
            $curstu->userid = $userid;
            $curstu->curriculumid = $track->curid;
            $curstu->completed = 0;
            $curstu->credits = 0;
            $curstu->locked = 0;
            $curstu->add();
        }

        events_trigger('track_assigned', $record);

        /**
         * Get autoenrollable classes in the track.  Classes are autoenrollable
         * if:
         * - the autoenrol flag is set
         * - it is the only class in that course slot for the track
         */
        $sql = 'SELECT classid, courseid '
            . 'FROM '.$CURMAN->db->prefix_table(TRACKCLASSTABLE).' '
            . 'WHERE trackid = \''.$trackid.'\' '
            // group the classes from the same course together
            . 'GROUP BY courseid '
            // only select the ones that are the only class for that course in
            // the given track, and if the autoenrol flag is set
            . 'HAVING COUNT(*) = 1 AND MAX(autoenrol) = 1';
        $classes = $CURMAN->db->get_records_sql($sql);
        if (!empty($classes)) {
            foreach ($classes as $class) {
                // enrol user in each autoenrolable class
                $stu_record = new object();
                $stu_record->userid = $userid;
                $stu_record->classid = $class->classid;
                $stu_record->enrolmenttime = time();

                $enrolment = new student($stu_record);
                // check prerequisites and enrolment limits
                $enrolment->add(array('prereq' => 1, 'waitlist' => 1), true);
            }
        }

        return true;
    }

    /**
     * Unenrols a user from a track.
     */
    function unenrol() {
        return $this->data_delete_record();
    }


    /**
     * Check for a duplicate record when doing an insert.
     *
     * @param object $record The record we want to insert.
     */
    function duplicate_check($record=null) {
        global $CURMAN;

        if(empty($record)) {
            $record = $this;
        }

        /// Check for an existing enrolment - it can't already exist.
        if ($CURMAN->db->record_exists(USRTRKTABLE, 'trackid', $record->trackid, 'userid', $record->userid)) {
            return true;
        }

        return false;
    }

    /// collection functions. (These may be able to replaced by a generic container/listing class)

    static function get_usertrack($userid, $trackid) {
        global $CURMAN;

        if(empty($CURMAN->db)) {
            return null;
        }

        $retval = $CURMAN->db->get_record(USRTRKTABLE, 'userid', $userid, 'trackid', $trackid);

        if(!empty($retval)) {
            $retval = new usertrack($retval->id);
        } else {
            $retval = null;
        }

        return $retval;
    }

    /**
     * Get a list of the users assigned to this track.
     *
     * @uses $CURMAN
     * @param int $trackid The track id.
     */
    static function get_users($trackid = 0, $sort = '', $dir = 'ASC', $page = 0, $perpage = 0) {
        global $CURMAN;

        if (empty($CURMAN->db)) {
            return NULL;
        }

        if (empty($trackid) && !empty($this->trackid)) {
            $trackid = $this->trackid;
        }

        $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');
        $select  = 'SELECT usrtrk.id, usrtrk.userid, usr.idnumber, ' . $FULLNAME . ' AS name, usr.email ';
        $tables  = 'FROM ' . $CURMAN->db->prefix_table(USRTRKTABLE) . ' usrtrk ';
        $join    = 'LEFT JOIN ' . $CURMAN->db->prefix_table(USRTABLE) . ' usr '.
            'ON usr.id = usrtrk.userid ';
        $where   = 'WHERE usrtrk.trackid = '.$trackid.' ';
        //$group   = 'GROUP BY usrtrk.id ';

        if ($dir != 'ASC') {
            $dir = 'DESC';
        }
        if (empty($sort)) {
            $sort = 'name';
        }
        if ($sort == 'name') { // TBV: ELIS-2772 & above
           $sort = "ORDER BY usr.lastname {$dir}, usr.firstname {$dir} ";
        } else {
            $sort = 'ORDER BY '. $sort .' '. $dir .' ';
        }
        $limit = '';

        if (empty($CURMAN->config->legacy_show_inactive_users)) {
            $where .= ' AND usr.inactive = 0 ';
        }

        $sql = $select.$tables.$join.$where./*$group.*/$sort.$limit;
        return $CURMAN->db->get_records_sql($sql, $page * $perpage, $perpage);
    }


    /**
     * Get a list of the tracks assigned to this user.
     *
     * @uses $CURMAN
     * @param int $userid The cluster id.
     */
    static function get_tracks($userid = 0) {
        global $CURMAN;

        if (empty($CURMAN->db)) {
            return NULL;
        }

        if(empty($userid) && !empty($this->userid)) {
            $userid = $this->userid;
        }

        $select  = 'SELECT usrtrk.id, usrtrk.trackid, trk.idnumber, trk.name, trk.description, COUNT(trkcls.id) as numclasses ';
        $tables  = 'FROM ' . $CURMAN->db->prefix_table(USRTRKTABLE) . ' usrtrk ';
        $join    = 'LEFT JOIN ' . $CURMAN->db->prefix_table(TRACKTABLE) . ' trk '.
            'ON trk.id = usrtrk.trackid ';
        $join   .= 'LEFT JOIN ' . $CURMAN->db->prefix_table(TRACKCLASSTABLE) . ' trkcls '.
            'ON trkcls.trackid = trk.id ';
        $where   = 'WHERE usrtrk.userid = '.$userid.' ';
        $group   = 'GROUP BY usrtrk.id ';
        $sort    = 'ORDER BY trk.idnumber ASC ';
        $limit   = '';

        $sql = $select.$tables.$join.$where.$group.$sort.$limit;

        return $CURMAN->db->get_records_sql($sql);
    }
    

    /**
     * Determines whether the current user is allowed to create, edit, and delete associations
     * between a user and a track
     * 
     * @param    int      $userid    The id of the user being associated to the track
     * @param    int      $trackid   The id of the track we are associating the user to
     * 
     * @return   boolean             True if the current user has the required permissions, otherwise false
     */
    public static function can_manage_assoc($userid, $trackid) {
        global $USER;
        
        //get the context for the "indirect" capability
        $context = cm_context_set::for_user_with_capability('cluster', 'block/curr_admin:track:enrol_cluster_user', $USER->id);
            
        $allowed_clusters = array();
            
        if(!trackpage::can_enrol_into_track($trackid)) {
            //the users who satisfty this condition are a superset of those who can manage associations
            return false;
        } else if (trackpage::_has_capability('block/curr_admin:track:enrol', $trackid)) {
            //current user has the direct capability
            return true;
        }

        //get the clusters and check the context against them
        $clusters = clustertrack::get_clusters($trackid);
        $allowed_clusters = $context->get_allowed_instances($clusters, 'cluster', 'clusterid');

        //query to get users associated to at least one enabling cluster
        $cluster_select = '';
        if(empty($allowed_clusters)) {
            $cluster_select = '0=1';
        } else {
            $cluster_select = 'clusterid IN (' . implode(',', $allowed_clusters) . ')';
        }
        $select = "userid = {$userid} AND {$cluster_select}";

        //user just needs to be in one of the possible clusters
        if(record_exists_select(CLSTUSERTABLE, $select)) {
            return true;
        
        }
                                    
        return false;
    }
}

?>
