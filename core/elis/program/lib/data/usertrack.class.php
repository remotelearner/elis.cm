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

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/../../../../config.php');
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once elis::lib('data/data_object_with_custom_fields.class.php');
require_once elispm::lib('data/user.class.php');
require_once elispm::lib('data/student.class.php');
require_once elispm::lib('data/curriculumstudent.class.php');
require_once elispm::lib('data/track.class.php');


class usertrack extends elis_data_object {

    const TABLE = 'crlm_user_track';
    var $verbose_name = 'usertrack';

    /**
     * User ID-number
     * @var    char
     * @length 255
     */
    protected $_dbfield_id;
    protected $_dbfield_userid;
    protected $_dbfield_trackid;

    private $location;
    private $templateclass;

    static $associations = array(
        'track' => array(
            'class' => 'track',
            'idfield' => 'trackid'
        ),
        'user' => array(
            'class' => 'user',
            'idfield' => 'userid'
        ),
    );
    /**
     * Constructor.
     *
     * @param int|object|array $data The data id of a data record or data
     * elements to load manually.
     *
     */
    /*function usertrack($data = false) {
        parent::datarecord();

        $this->set_table(TABLE);
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
    }*/

    public static function delete_for_user($id) {
        global $DB;

        return $DB->delete_records(self::TABLE, array('userid'=> $id));
    }

    public static function delete_for_track($id) {
        global $DB;

        return $DB->delete_records(self::TABLE, array('trackid'=> $id));
    }


    /**
     * Enrols a user in a track.
     *
     * @param int $userid The user id
     * @param int $trackid The track id
     */
    public static function enrol($userid, $trackid) {
        global $DB;

        // make sure we don't double-enrol
        if ($DB->record_exists(self::TABLE, array('userid'=> $userid,
                                            'trackid'=> $trackid))) {
            return false;
        }

        $record = new usertrack();
        $record->userid = $userid;
        $record->trackid = $trackid;
        $record->save();

        $user = new user($userid);
        $track = new track($trackid);

        if (!$DB->record_exists(curriculumstudent::TABLE, array('userid'=> $userid,
                                                                   'curriculumid'=> $track->curid))) {
            $curstu = new curriculumstudent();
            $curstu->userid = $userid;
            $curstu->curriculumid = $track->curid;
            $curstu->completed = 0;
            $curstu->credits = 0;
            $curstu->locked = 0;
            $curstu->save();
        }

        events_trigger('track_assigned', $record);

        /**
         * Get autoenrollable classes in the track.  Classes are autoenrollable
         * if:
         * - the autoenrol flag is set
         * - it is the only class in that course slot for the track
         */
        $sql = 'SELECT classid, courseid '
            . 'FROM {'.trackassignment::TABLE.'} '
            . 'WHERE trackid = ? '
            // group the classes from the same course together
            . 'GROUP BY courseid '
            // only select the ones that are the only class for that course in
            // the given track, and if the autoenrol flag is set
            . 'HAVING COUNT(*) = 1 AND MAX(autoenrol) = 1';
        $params = array($trackid);
        $classes = $DB->get_recordset_sql($sql, $params);
        foreach ($classes as $class) {
            // ELIS-3460: check pre-requisites ...
            $curcrs = new curriculumcourse(array('courseid' => $class->courseid, 'curriculumid' => $track->curid));
            if (!$curcrs->prerequisites_satisfied($userid)) {
                //error_log("/elis/program/lib/data/usertrack.class.php::enrol({$userid}); pre-requisites NOT satisfied for course: {$class->courseid}, curriculum: {$track->curid}");
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
                $status = $enrolment->save();
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
                $status = true;
            } catch (Exception $e) {
                $param = array('message' => $e->getMessage());
                echo cm_error(get_string('record_not_created_reason', 'elis_program', $param));
            }
        }
        unset($classes);

        return true;
    }

    /**
     * Unenrols a user from a track.
     */
    function unenrol() {
        //return $this->data_delete_record();
        parent::delete();
    }

    static $validation_rules = array(
        'validate_userid_not_empty',
        'validate_trackid_not_empty',
        array('validation_helper', 'is_unique_userid_trackid')
    );

    function validate_userid_not_empty() {
        return validate_not_empty($this, 'userid');
    }

    function validate_trackid_not_empty() {
        return validate_not_empty($this, 'trackid');
    }

    function validate_unique_userid_trackid() {
        return validate_is_unique($this, array('userid','trackid'));
    }

    /// collection functions. (These may be able to replaced by a generic container/listing class)

    public static function get_usertrack($userid, $trackid) {
        global $DB;

        if(empty($DB)) {
            return null;
        }

        $retval = $DB->get_record(TABLE, array('userid'=> $userid, 'trackid'=> $trackid));

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
    public static function get_users($trackid = 0, $sort = '', $dir = 'ASC', $page = 0, $perpage = 0) {
        global $DB;

        if (empty($DB)) {
            return NULL;
        }

        if (empty($trackid) && !empty($this->trackid)) {
            $trackid = $this->trackid;
        }

        $FULLNAME = $DB->sql_concat('usr.firstname', "' '", 'usr.lastname');
        $select  = 'SELECT usrtrk.id, usrtrk.userid, usr.idnumber, ' . $FULLNAME . ' AS name, usr.email ';
        $tables  = 'FROM {' . self::TABLE . '} usrtrk ';
        $join    = 'JOIN {' . user::TABLE . '} usr '.
            'ON usr.id = usrtrk.userid ';
        $where   = 'WHERE usrtrk.trackid = ? ';
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

        $params = array($trackid);

        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $where .= ' AND usr.inactive = 0 ';
        }

        $sql = $select.$tables.$join.$where./*$group.*/$sort;
        return $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
    }

    /**
     * Get a list of the tracks assigned to this user.
     *
     * @uses $CURMAN
     * @param int $userid The cluster id.
     */
    public static function get_tracks($userid = 0, $sort, $dir) {
        global $DB;

        if (empty($DB)) {
            return NULL;
        }

        if(empty($userid) && !empty($this->userid)) {
            $userid = $this->userid;
        }

        $select  = 'SELECT usrtrk.id, usrtrk.trackid, trk.idnumber, trk.name, trk.description, COUNT(trkcls.id) as numclasses ';
        $tables  = 'FROM {' . self::TABLE . '} usrtrk ';
        $join    = 'LEFT JOIN {' . track::TABLE . '} trk '.
            'ON trk.id = usrtrk.trackid ';
        $join   .= 'LEFT JOIN {' . trackassignment::TABLE . '} trkcls '.
            'ON trkcls.trackid = trk.id ';
        $where   = 'WHERE usrtrk.userid = ? ';
        $group   = 'GROUP BY usrtrk.id ';
        $sort    = "ORDER BY {$sort} {$dir} ";

        $params = array($userid);

        $sql = $select.$tables.$join.$where.$group.$sort;

        return $DB->get_records_sql($sql, $params);
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
        $context = pm_context_set::for_user_with_capability('cluster', 'elis/program:track_enrol_userset_user', $USER->id);

        $allowed_clusters = array();

        // TODO: Ugly, this needs to be overhauled
        $tpage = new trackpage();

        if (!trackpage::can_enrol_into_track($trackid)) {
            //the users who satisfty this condition are a superset of those who can manage associations
            return false;
        } else if ($tpage->_has_capability('elis/program:track_enrol', $trackid)) {
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
        $select = "userid = ? AND ?";
        $params = array($userid, $cluster_select);


        //user just needs to be in one of the possible clusters
        // TODO: clusteruser needs to be ported to ELIS 2 as clusterassignment
//        if(record_exists_select(clusteruser::TABLE, $select, $params)) {
//            return true;
//        }

        return false;
    }

    /**
     * Obtain the list of users who can be assigned to the provided track
     *
     * @param int $trackid The record id of the track we are currently assigning to
     * @param string $sort The column to sort users by
     * @param string $dir The user sorting direction
     * @param string $namesearch A substring of users' fullnames to search by
     * @param string $alpha The first letter of users' fullnames to search by
     * @param int $page The page we are on (0-indexed)
     * @param int $perpage Number of users to display per page
     *
     * @return recordset The list of users on the current page
     */
    public static function get_available_users($trackid, $sort = 'lastname', $dir = 'ASC', $namesearch = '',
                                               $alpha = '', $page = 0, $perpage = 30) {
        global $CFG, $DB, $USER;

        require_once(elispm::file('trackpage.class.php'));
        require_once(elispm::lib('data/clusterassignment.class.php'));

        // find all users not enrolled in the track
        $FULLNAME = $DB->sql_concat('usr.firstname', "' '", 'usr.lastname');

        $select = 'SELECT usr.*, ' . $FULLNAME . ' AS name, usr.lastname AS lastname ';
        $sql = 'FROM {' . user::TABLE . '} usr '
            . 'LEFT OUTER JOIN {' . usertrack::TABLE . '} ut ON ut.userid = usr.id AND ut.trackid = :trackid '
            . 'WHERE ut.userid IS NULL ';
        $params = array('trackid' => $trackid);

        if ($namesearch != '') {
            $NAMELIKE = $DB->sql_like($FULLNAME, ':namesearch', false);

            $namesearch = trim($namesearch);
            $sql .= 'AND '.$NAMELIKE.' ';
            $params['namesearch'] = "%{$namesearch}%";
        }

        if ($alpha != '') {
            //todo: determine if this should actually be using last name?
            $ALPHA_LIKE = $DB->sql_like($FULLNAME, ':lastname', false);

            $sql .= 'AND '.$ALPHA_LIKE.' ';
            $params['lastname'] = "{$alpha}%";
        }

        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $sql .= 'AND usr.inactive = 0 ';
        }

        // TODO: Ugly, this needs to be overhauled
        $tpage = new trackpage();

        if (!$tpage->_has_capability('elis/program:track_enrol', $trackid)) {
            //perform SQL filtering for the more "conditional" capability
            //get the context for the "indirect" capability
            $context = pm_context_set::for_user_with_capability('cluster', 'elis/program:track_enrol_userset_user', $USER->id);

            //get the clusters and check the context against them
            $clusters = clustertrack::get_clusters($trackid);
            $allowed_clusters = $context->get_allowed_instances($clusters, 'cluster', 'clusterid');

            if (empty($allowed_clusters)) {
                $sql .= 'AND 0=1 ';
            } else {
                $cluster_filter = implode(',', $allowed_clusters);
                $sql .= "AND usr.id IN (
                           SELECT userid FROM {" . clusterassignment::TABLE . "}
                           WHERE clusterid IN (:clusterfilter)) ";
                $params['clusterfilter'] = $cluster_filter;
            }
        }

        if ($sort) {
            $sql .= 'ORDER BY '.$sort.' '.$dir.' ';
        }

        return $DB->get_recordset_sql($select.$sql, $params, $page * $perpage, $perpage);
    }

    /**
     * Obtain the count of users who can be assigned to the provided track
     *
     * @param int $trackid The record id of the track we are currently assigning to
     * @param string $namesearch A substring of users' fullnames to search by
     * @param string $alpha The first letter of users' fullnames to search by
     *
     * @return array The total count of appropriate users
     */
    public static function count_available_users($trackid, $namesearch = '', $alpha = '') {
        global $CFG, $DB, $USER;

        require_once(elispm::file('trackpage.class.php'));
        require_once(elispm::lib('data/clusterassignment.class.php'));

        $FULLNAME = $DB->sql_concat('usr.firstname', "' '", 'usr.lastname');

        $select = 'SELECT COUNT(*) ';
        $sql = 'FROM {' . user::TABLE . '} usr '
            . 'LEFT OUTER JOIN {' . usertrack::TABLE . '} ut ON ut.userid = usr.id AND ut.trackid = :trackid '
            . 'WHERE ut.userid IS NULL ';
        $params = array('trackid' => $trackid);

        if ($namesearch != '') {
            $NAMELIKE = $DB->sql_like($FULLNAME, ':namesearch', false);

            $namesearch = trim($namesearch);
            $sql .= 'AND '.$NAMELIKE.' ';
            $params['namesearch'] = "%{$namesearch}%";
        }

        if ($alpha != '') {
            //todo: determine if this should actually be using last name?
            $ALPHA_LIKE = $DB->sql_like($FULLNAME, ':lastname', false);

            $sql .= 'AND '.$ALPHA_LIKE.' ';
            $params['lastname'] = "{$alpha}%";
        }

        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $sql .= 'AND usr.inactive = 0 ';
        }

        // TODO: Ugly, this needs to be overhauled
        $tpage = new trackpage();

        if (!$tpage->_has_capability('elis/program:track_enrol', $trackid)) {
            //perform SQL filtering for the more "conditional" capability
            //get the context for the "indirect" capability
            $context = pm_context_set::for_user_with_capability('cluster', 'elis/program:track_enrol_userset_user', $USER->id);

            //get the clusters and check the context against them
            $clusters = clustertrack::get_clusters($trackid);
            $allowed_clusters = $context->get_allowed_instances($clusters, 'cluster', 'clusterid');

            if (empty($allowed_clusters)) {
                $sql .= 'AND 0=1 ';
            } else {
                $cluster_filter = implode(',', $allowed_clusters);
                $sql .= "AND usr.id IN (
                           SELECT userid FROM {" . clusterassignment::TABLE . "}
                           WHERE clusterid IN (:clusterfilter)) ";
                $params['clusterfilter'] = $cluster_filter;
            }
        }

        return $DB->count_records_sql($select.$sql, $params);
    }
}
