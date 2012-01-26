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

require_once(CURMAN_DIRLOCATION . '/lib/datarecord.class.php');

define ('CLSTUSERTABLE', 'crlm_usercluster');

class usercluster extends datarecord {
	/*
	 var $id;            // INT - The data id if in the database.
	 var $name;          // STRING - Textual name of the cluster.
	 var $display;       // STRING - A description of the cluster.
	 */

	/**
	 * Constructor.
	 *
	 * @param $clusterdata int/object/array The data id of a data record or data elements to load manually.
	 *
	 */
	function usercluster($data=false) {
		parent::datarecord();

		$this->set_table(CLSTUSERTABLE);
		$this->add_property('id', 'int');
		$this->add_property('userid', 'int');
		$this->add_property('clusterid', 'int');
		$this->add_property('autoenrol', 'int');

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
    	
    	return $CURMAN->db->delete_records(CLSTUSERTABLE, 'userid', $id);
    }
    
	public static function delete_for_cluster($id) {
    	global $CURMAN;
    	
    	return $CURMAN->db->delete_records(CLSTUSERTABLE, 'clusterid', $id);
    }
    
    /**
     * Determines whether the current user is allowed to create, edit, and delete associations
     * between a user and a cluster
     * 
     * @param    int      $userid    The id of the user being associated to the cluster
     * @param    int      $clustid   The id of the cluster we are associating the user to
     * 
     * @return   boolean             True if the current user has the required permissions, otherwise false
     */
    public static function can_manage_assoc($userid, $clustid) {
        global $USER;
            
        $allowed_clusters = array();
                
        if(!clusterpage::can_enrol_into_cluster($clustid)) {
            //the users who satisfty this condition are a superset of those who can manage associations
            return false;
        } else if (clusterpage::_has_capability('block/curr_admin:cluster:enrol', $clustid)) {
            //current user has the direct capability
            return true;
        }
            
        $allowed_clusters = cluster::get_allowed_clusters($clustid);

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
