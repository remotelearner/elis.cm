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
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once elis::lib('data/data_object.class.php');
require_once elispm::lib('data/userset.class.php');

class clusterassignment extends elis_data_object {
	/*
	 var $id;            // INT - The data id if in the database.
	 var $name;          // STRING - Textual name of the cluster.
	 var $display;       // STRING - A description of the cluster.
	 */

    const TABLE = 'crlm_cluster_assignments';

    /**
     * User ID-number
     * @var    char
     * @length 255
     */
    protected $_dbfield_id;
    protected $_dbfield_clusterid;
    protected $_dbfield_userid;
    protected $_dbfield_plugin;
    protected $_dbfield_autoenrol;
    protected $_dbfield_leader;

    private $location;
    private $templateclass;

    static $associations = array(
        'user' => array(
            'class' => 'user',
            'idfield' => 'userid'
        ),
        'cluster' => array(
            'class' => 'userset',
            'idfield' => 'clusterid'
        )
    );

    static $validation_rules = array(
        array('validation_helper', 'is_unique_clusterid_userid_plugin')
    );

    public function delete() {
        $this->load(); // The object must be loaded before sending through to any event handlers -- ELIS-6567
        $status = parent::delete();
        events_trigger('cluster_deassigned', $this->to_object());
        return $status;
	}

	public static function delete_for_user($id) {
    	global $DB;

    	$status = $DB->delete_records(self::TABLE, array('userid'=> $id));

    	return $status;
    }

	public static function delete_for_cluster($id) {
    	global $DB;

    	$status = $DB->delete_records(self::TABLE, array('clusterid'=> $id));

    	return $status;
    }

    /**
     * Save the record to the database.  This method is used to both create a
     * new record, and to update an existing record.
     */
    public function save() {
        $trigger = false;

        if (!isset($this->id)) {
            $trigger = true;
        }

        parent::save();

        if ($trigger) {
            $usass = new stdClass;
            $usass->userid = $this->userid;
            $usass->clusterid = $this->clusterid;

            events_trigger('cluster_assigned', $usass);
        }
    }

    /**
     * Updates resulting enrolments that are auto-created after users are
     * assigned to user sets (specifically user-track assignments, user-program
     * assignments, and class enrolments in a track's default class)
     *
     * Note: This is essentially equivalent to cluster_assigned_handler but
     * runs a fixed number of queries for scalability reasons
     *
     * @param  int  $userid     A specific PM user id to filter on for
     *                          consideration, or all users if zero
     * @param  int  $clusterid  A specific cluster / user set id to filter
     *                          on for consideration, or all users if zero
     */
    static function update_enrolments($userid = 0, $clusterid = 0) {
        global $DB;
        require_once(elispm::lib('data/usermoodle.class.php'));
        // error_log("/elis/program/lib/data/clusterassignment.class.php::update_enrolments({$userid}, {$clusterid})");

        // ELIS-7582
        @set_time_limit(0);

        // convert provided parameters to SQL conditions
        $extraconditions = array();
        $extraparams = array();

        if (!empty($userid)) {
            $users = array($userid);
            $extraconditions[] = 'u.id = ?';
            $extraparams[] = $userid;
        } else {
            $users = clusterassignment::find(new field_filter('clusterid', $clusterid));
        }

        if (!empty($clusterid)) {
            $extraconditions[] = 'clu.clusterid = ?';
            $extraparams[] = $clusterid;
        }

        $extrawhere = '';
        if (!empty($extraconditions)) {
            $extrawhere = ' AND '. implode(' AND ', $extraconditions);
        }

        //use the current time as the time created and modified for curriculum
        //assignments
        $timenow = time();

        //assign to curricula based on user-cluster and cluster-curriculum
        //associations
        $sql = "INSERT INTO {".curriculumstudent::TABLE."}
                    (userid, curriculumid, timecreated, timemodified)
                SELECT DISTINCT u.id, clucur.curriculumid, {$timenow}, {$timenow}
                  FROM {".clusterassignment::TABLE."} clu
                  JOIN {".user::TABLE."} u ON u.id = clu.userid
                  JOIN {".clustercurriculum::TABLE."} clucur
                    ON clucur.clusterid = clu.clusterid
             LEFT JOIN {".curriculumstudent::TABLE."} ca
                    ON ca.userid = u.id
                   AND ca.curriculumid = clucur.curriculumid
                 WHERE ca.curriculumid IS NULL
                   AND clucur.autoenrol = 1
                   {$extrawhere}";
        $DB->execute($sql, $extraparams);

        //assign to curricula based on user-cluster and cluster-track
        //associations (assigning a user to a track auto-assigns them to
        //the track's curriculum, track assignment happens below)
        $sql = "INSERT INTO {".curriculumstudent::TABLE."}
                    (userid, curriculumid, timecreated, timemodified)
                SELECT DISTINCT u.id, trk.curid, {$timenow}, {$timenow}
                  FROM {".clusterassignment::TABLE."} clu
                  JOIN {".user::TABLE."} u
                    ON u.id = clu.userid
                  JOIN {".clustertrack::TABLE."} clutrk
                    ON clutrk.clusterid = clu.clusterid
                  JOIN {".track::TABLE."} trk
                    ON clutrk.trackid = trk.id
             LEFT JOIN {".curriculumstudent::TABLE."} ca
                    ON ca.userid = u.id
                   AND ca.curriculumid = trk.curid
                 WHERE ca.curriculumid IS NULL
                   AND clutrk.autoenrol = 1
                   {$extrawhere}";
        $DB->execute($sql, $extraparams);

        //this represents the tracks that users will be assigned to
        //based on user-cluster and cluster-track associations
        //(actual assignment happens below)
        $exists = "EXISTS (SELECT DISTINCT u.id, clutrk.trackid
                             FROM {".clusterassignment::TABLE."} clu
                             JOIN {".user::TABLE."} u
                               ON u.id = clu.userid
                             JOIN {".clustertrack::TABLE."} clutrk
                               ON clutrk.clusterid = clu.clusterid
                        LEFT JOIN {".usertrack::TABLE."} ta
                               ON ta.userid = u.id
                              AND ta.trackid = clutrk.trackid
                            WHERE ta.trackid IS NULL
                              AND clutrk.autoenrol = 1
                              AND outerta.trackid = clutrk.trackid
	                      {$extrawhere})";

        /**
         * Get autoenrollable classes in the track.  Classes are autoenrollable
         * if:
         * - the autoenrol flag is set
         * - it is the only class in that course slot for the track
         */
        // group the classes from the same course together
        // only select the ones that are the only class for that course in
        // the given track, and if the autoenrol flag is set
        $sql = "SELECT outerta.classid, outerta.courseid, trk.curid
                  FROM {". trackassignment::TABLE ."} outerta
                  JOIN {". track::TABLE ."} trk ON trk.id = outerta.trackid
                 WHERE {$exists}
              GROUP BY courseid
                HAVING COUNT(*) = 1 AND MAX(autoenrol) = 1";

         //go through and assign user(s) to the autoenollable classes
         $classes = $DB->get_records_sql($sql, $extraparams);
         if (!empty($classes)) {
            foreach ($users as $user) {
                $userid = is_object($user) ? $user->userid: $user;
                foreach ($classes as $class) {
                    // check pre-requisites
                    $curcrs = new curriculumcourse(
                                      array('courseid' => $class->courseid,
                                            'curriculumid' => $class->curid));
                    if (!$curcrs->prerequisites_satisfied($userid)) {
                        continue;
                    }
                    $now = time();
                    // enrol user in each autoenrolable class
                    $stu_record = new object();
                    $stu_record->userid = $userid;
                    $stu_record->classid = $class->classid;
                    $stu_record->enrolmenttime = $now;
                    $enrolment = new student($stu_record);

                    // catch enrolment limits
                    try {
                        $enrolment->save();
                    } catch (pmclass_enrolment_limit_validation_exception $e) {
                        // autoenrol into waitlist
                        $wait_record = new object();
                        $wait_record->userid = $userid;
                        $wait_record->classid = $class->classid;
                        $wait_record->enrolmenttime = $now;
                        $wait_record->timecreated = $now;
                        $wait_record->position = 0;
                        $wait_list = new waitlist($wait_record);
                        $wait_list->save();
                    } catch (Exception $e) {
                        $param = array('message' => $e->getMessage());
                        if (in_cron()) {
                            mtrace(get_string('record_not_created_reason',
                                                     'elis_program', $param));
                        } else {
                            echo cm_error(get_string('record_not_created_reason',
                                                     'elis_program', $param));
                        }
                    }
                }
            }
        }

        //assign to tracks based on user-cluster and cluster-track
        //associations
        $sql = "INSERT INTO {".usertrack::TABLE."}
                       (userid, trackid)
                SELECT DISTINCT u.id, clutrk.trackid
                  FROM {".clusterassignment::TABLE."} clu
                  JOIN {".user::TABLE."} u
                    ON u.id = clu.userid
                  JOIN {".clustertrack::TABLE."} clutrk
                    ON clutrk.clusterid = clu.clusterid
             LEFT JOIN {".usertrack::TABLE."} ta
                    ON ta.userid = u.id
                   AND ta.trackid = clutrk.trackid
                 WHERE ta.trackid IS NULL
                   AND clutrk.autoenrol = 1
                   {$extrawhere}";
        $DB->execute($sql, $extraparams);

        //update site-level "cluster groups"
        //TODO: make sure all "cluster groups" scenarios are handled here, and look at
        //performance in more detal
        if (!empty($userid) && file_exists(elispm::file('plugins/userset_groups/lib.php'))) {
            require_once(elispm::file('plugins/userset_groups/lib.php'));

            //need the Moodle user id
            $mdluserid = $DB->get_field(usermoodle::TABLE, 'muserid', array('cuserid' => $userid));

            if ($mdluserid) {
                //find all assignments for this user
                $assignments = $DB->get_recordset(clusterassignment::TABLE, array('userid' => $userid));

                foreach ($assignments as $assignment) {
                    //update site-level group assignments
                    userset_groups_update_site_course($assignment->clusterid, true, $mdluserid);
                }
            }

            //update course-level group assignment
            userset_groups_update_groups(array('mdlusr.cuserid' =>  $userid));
        }
    }
}
