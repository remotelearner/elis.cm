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
require_once elis::lib('data/data_object_with_custom_fields.class.php');
require_once elispm::lib('lib.php');
require_once elispm::lib('data/classmoodlecourse.class.php');
require_once elispm::lib('data/clustertrack.class.php');
require_once elispm::lib('data/course.class.php');
require_once elispm::lib('data/coursetemplate.class.php');
require_once elispm::lib('data/curriculum.class.php');
require_once elispm::lib('data/pmclass.class.php');
require_once elispm::lib('data/user.class.php');
require_once elispm::lib('data/usertrack.class.php');
require_once elispm::lib('deprecatedlib.php');

class track extends data_object_with_custom_fields {
    const TABLE = 'crlm_track';

    var $verbose_name = 'track';
    var $autocreate;

     /**
     * User ID-number
     * @var    char
     * @length 255
     */
    protected $_dbfield_curid;
    protected $_dbfield_idnumber;
    protected $_dbfield_name;
    protected $_dbfield_description;
    protected $_dbfield_startdate;
    protected $_dbfield_enddate;
    protected $_dbfield_defaulttrack;
    protected $_dbfield_timecreated;
    protected $_dbfield_timemodified;

    private $location;
    private $templateclass;

    static $associations = array(
        'clustertrack' => array(
            'class' => 'clustertrack',
            'foreignidfield' => 'trackid'
        ),
        'usertrack' => array(
            'class' => 'usertrack',
            'foreignidfield' => 'trackid'
        ),
        'trackassignment' => array(
            'class' => 'trackassignment',
            'foreignidfield' => 'trackid'
        ),
        'curriculum' => array(
            'class' => 'curriculum',
            'idfield' => 'curid'
        )
    );

    static $delete_is_complex = true;

    protected function get_field_context_level() {
        return CONTEXT_ELIS_TRACK;
    }

    public static function delete_for_curriculum($id) {

        //look up and delete associated tracks
        if ($tracks = track_get_listing('name', 'ASC', 0, 0, '', '', $id)) {
            foreach ($tracks as $track) {
                $record = new track($track->id);
                $record->delete();
            }
        }
    }

    /**
     * Creates and associates a class with a track for every course that
     * belongs to the track curriculum
     *
     * TODO: return some data
     */
    function track_auto_create() {
        // Had to load $this due to lazy-loading
        $this->load();
        if (empty($this->curid) or
            empty($this->id)) {
            cm_error('trackid and curid have not been properly initialized');
            return false;
        }

        $autoenrol = false;
        $usetemplate = false;

        // Pull up the curricula assignment record(s)
        //        $curcourse = curriculumcourse_get_list_by_curr($this->curid);
        $sql = 'SELECT ccc.*, cc.idnumber, cc.name ' .
            'FROM {' . curriculumcourse::TABLE . '} ccc ' .
            'INNER JOIN {' . course::TABLE . '} cc ON cc.id = ccc.courseid '.
            'WHERE ccc.curriculumid = ? ';
        $params = array($this->curid);

        $curcourse = $this->_db->get_recordset_sql($sql, $params);

        // For every course of the curricula determine which ones need -
        // to have their auto enrol flag set
        foreach ($curcourse as $recid => $curcourec) {

            //get a unique idnumber
            $idnumber = $this->idnumber;
            if (!empty($curcourec->idnumber)) {
                $idnumber = append_once($idnumber, $curcourec->idnumber .'-',
                                        array('prepend'   => true,
                                              'maxlength' => 95,
                                              'strict'    => true));
            }

            generate_unique_identifier(pmclass::TABLE,
                                      'idnumber',
                                       $idnumber,
                                       array('idnumber' => $idnumber),
                                      'pmclass', $classojb,
                                       array('courseid' => $curcourec->courseid,
                                             'idnumber' => $idnumber));

            // Course is required
            if ($curcourec->required) {
                $autoenrol = true;
            }

            //attempte to obtain the course template
            $cortemplate = coursetemplate::find(new field_filter('courseid', $curcourec->courseid));
            if ($cortemplate->valid()) {
                $cortemplate = $cortemplate->current();
            }

            // Course is using a Moodle template
            if (!empty($cortemplate->location)) {
                // Parse the course id from the template location
                $classname = $cortemplate->templateclass;
                $templateobj = new $classname();
                $templatecorid = $cortemplate->location;
                $usetemplate = true;
            }

            // Create class
            if (!($classid = $classojb->auto_create_class(array('courseid' => $curcourec->courseid)))) {
                cm_error(get_string('error_creating_class', 'elis_program', $curcourec->name));
                continue;
            }

            // attach course to moodle template
            if ($usetemplate) {
                moodle_attach_class($classid, 0, '', false, false, true);
            }

            $trackclassobj = new trackassignment(array('courseid' => $curcourec->courseid,
                                                       'trackid'  => $this->id,
                                                       'classid'  => $classojb->id));

            // Set auto-enrol flag
            if ($autoenrol) {
                $trackclassobj->autoenrol = 1;
            }

            // Assign class to track
            $trackclassobj->save();

            // Create and assign class to default system track
            // TODO: for now, use elis::$config->elis_program in place of $CURMAN->config
            if (!empty(elis::$config->elis_program->userdefinedtrack)) {
                $trkid = $this->create_default_track();

                $trackclassobj = new trackassignment(array('courseid' => $curcourec->courseid,
                                                           'trackid'  => $trkid,
                                                           'classid'  => $classojb->id));

                // Set auto-enrol flag
                if ($autoenrol) {
                    $trackclassobj->autoenrol = 1;
                }

                // Assign class to default system track
                $trackclassobj->save();

            }
            $usetemplate = false;
            $autoenrol = false;
        }
        unset($curcourse);
    }

    /**
     * Creates a default track
     * @return mixed id of new track or false if error
     */
    function create_default_track() {

        $time = time();
        $trackid = 0;

        $trackid = $this->get_default_track();

        if (false !== $trackid) {
            return $trackid;
        }

        $param = array('curid' => $this->curid,
                       'idnumber' => 'default.'.$time,
                       'name' => 'DT.CURID.'.$this->curid,
                       'description' => 'Default Track',
                       'defaulttrack' => 1,
                       'startdate' => $time,
                       'enddate' => $time,
                       'defaulttrack' => 1,
            );

        $newtrk = new track($param);

        if ($newtrk->save()) {
            return $newtrk->id;
        }

        return false;
    }

    /**
     * Returns the track id of the default track for a curriculum
     *
     * @return mixed $trackid Track id or false if an error occured
     */
    function get_default_track() {

        $trackid = $this->_db->get_field(track::TABLE, 'id', array('curid' => $this->curid,
                                                            'defaulttrack' => 1));
        return $trackid;
    }

    /**
     * Removes all associations with a track, this entails removing
     * user track, cluster track and class track associations
     * @param none
     * @return none
     */
    function delete() {
        // Cascade
        //clean make the delete cascade into association records
        $filter = new field_filter('trackid', $this->id);

        usertrack::delete_records($filter, $this->_db);
        clustertrack::delete_records($filter, $this->_db);
        trackassignment::delete_records($filter, $this->_db);

        parent::delete();

        //Delete this leve's context
        $context = context_elis_track::instance($this->id);
        $context->delete();
    }

    function __toString() {
        return $this->name . ' (' . $this->idnumber . ')';
    }

    function get_verbose_name() {
        return $this->verbose_name;
    }

    public function set_from_data($data) {
        $this->autocreate = !empty($data->autocreate) ? $data->autocreate : 0;

        $this->_load_data_from_record($data, true);
    }

    static $validation_rules = array(
        'validate_idnumber_not_empty',
        'validate_unique_idnumber'
    );

    function validate_idnumber_not_empty() {
        return validate_not_empty($this, 'idnumber');
    }

    function validate_unique_idnumber() {
        return validate_is_unique($this, array('idnumber'));
    }
    public function save() { //add
        parent::save(); //add

        if ($this->autocreate) {
            $this->track_auto_create();
        }

        $status = field_data::set_for_context_from_datarecord(CONTEXT_ELIS_TRACK, $this);

        return $status;
    }

    /*function update() {
        $result = parent::update();

        $result = $result && field_data::set_for_context_from_datarecord('track', $this);

        return $result;
    } */

    /*public function duplicate_check($record=null) {
        global $DB;

        if (empty($record)) {
            $record = $this;
        }

        /// Check for valid idnumber - it can't already exist in the user table.
        if ($DB->record_exists($this->table, 'idnumber', $record->idnumber)) {
            return true;
        }

        return false;
    }*/

    public static function get_by_idnumber($idnumber) {
        global $DB;

        $retval = $DB->get_record(track::TABLE, 'idnumber', $idnumber);

        if (!empty($retval)) {
            $retval = new track($retval->id);
        } else {
            $retval = null;
        }

        return $retval;
    }

    /**
     * Check whether a student should be auto-enrolled in more class.
     *
     * This function gets called when a student completes a class, and checks
     * whether that class was a prerequisite for other courses in a track,
     * and whether the student should now be enrolled in the other classes.
     *
     * Strategy:
     * - find tracks that the student is in, and get their curricula
     * - find the course that the class is associated with
     * - see if the course is a prerequisite in any of the curricula
     * - if yes, see if there are any autoenrollable classes in the tracks that
     *   the student can be autoenrolled in
     */
    public static function check_autoenrol_after_course_completion($enrolment) {
        global $DB;

        $userid = $enrolment->userid;
        $courseid = $enrolment->pmclass->courseid;

        // this query will give us all the classes that had course $courseid as
        // a prerequisite, that are autoenrollable in tracks that user $userid
        // is in
        $sql = "SELECT trkcls.*, trk.curid, cls.courseid
                  FROM {". courseprerequisite::TABLE ."} prereq
            INNER JOIN {". curriculumcourse::TABLE ."} curcrs
                       ON curcrs.id = prereq.curriculumcourseid
            INNER JOIN {". pmclass::TABLE ."} cls
                       ON curcrs.courseid = cls.courseid
            INNER JOIN {". trackassignment::TABLE. "} trkcls
                       ON trkcls.classid = cls.id AND trkcls.autoenrol = 1
            INNER JOIN {". track::TABLE. "} trk
                       ON trk.id = trkcls.trackid AND trk.curid = curcrs.curriculumid
            INNER JOIN {". usertrack::TABLE. "} usrtrk
                       ON usrtrk.trackid = trk.id
                 WHERE prereq.courseid = ? AND usrtrk.userid = ?";
        $params = array($courseid, $userid);
        $recs = $DB->get_recordset_sql($sql, $params);

        // now we just need to loop through them, and enrol them
        foreach ($recs as $trkcls) {
            $curcrs = new curriculumcourse();
            $curcrs->courseid = $trkcls->courseid;
            $curcrs->curriculumid = $trkcls->curid;
            if ($curcrs->prerequisites_satisfied($userid)) {
                // no unsatisfied prereqs -- enrol the student
                $now = time();
                $newenrolment = new student();
                $newenrolment->userid = $userid;
                $newenrolment->classid = $trkcls->classid;
                $newenrolment->enrolmenttime = $now;
                // catch enrolment limits
                try {
                    $status = $newenrolment->save();
                } catch (pmclass_enrolment_limit_validation_exception $e) {
                    // autoenrol into waitlist
                    $wait_record = new object();
                    $wait_record->userid = $userid;
                    $wait_record->classid = $trkcls->classid;
                    $wait_record->enrolmenttime = $now;
                    $wait_record->timecreated = $now;
                    $wait_record->position = 0;
                    $wait_list = new waitlist($wait_record);
                    $wait_list->save();
                    $status = true;
                } catch (Exception $e) {
                    $param = array('message' => $e->getMessage());
                    echo cm_error(get_string('record_not_created_reason',
                                             self::LANG_FILE, $param));
                }
            }
        }
        unset($recs);

        return true;
    }

    /**
     * Clone a track
     * @param array $options options for cloning.  Valid options are:
     * - 'targetcurriculum': the curriculum id to associate the clones with
     *   (default: same as original track)
     * - 'classmap': a mapping of class IDs to use from the original track to
     *   the cloned track.  If a class from the original track is not mapped, a
     *   new class will be created
     * - 'moodlecourse': whether or not to clone Moodle courses (if they were
     *   autocreated).  Values can be (default: "copyalways"):
     *   - "copyalways": always copy course
     *   - "copyautocreated": only copy autocreated courses
     *   - "autocreatenew": autocreate new courses from course template
     *   - "link": link to existing course
     * @return array array of array of object IDs created.  Key in outer array
     * is type of object (plural).  Key in inner array is original object ID,
     * value is new object ID.  Outer array also has an entry called 'errors',
     * which is an array of any errors encountered when duplicating the
     * object.
     */
    function duplicate(array $options=array()) {

        $objs = array('errors' => array());
        if (isset($options['targetcluster'])) {
            $userset = $options['targetcluster'];
            if (!is_object($userset) || !is_a($userset, 'userset')) {
                $options['targetcluster'] = $userset = new userset($userset);
            }
        }

        // Due to lazy loading, we need to pre-load this object
        $this->load();

        // clone main track object
        $clone = new track($this);
        unset($clone->id);

        if (isset($options['targetcurriculum'])) {
            $clone->curid = $options['targetcurriculum'];
        }

        $idnumber = $clone->idnumber;
        $name = $clone->name;
        if (isset($userset)) {
            $to_append = ' - '. $userset->name;
            // if cluster specified, append cluster's name to course
            $idnumber = append_once($idnumber, $to_append, array('maxlength' => 95));
            $name = append_once($name, $to_append, array('maxlength' => 250));
        }

        //get a unique idnumber
        $clone->idnumber = generate_unique_identifier(track::TABLE, 'idnumber', $idnumber, array('idnumber' => $idnumber));

        if ($clone->idnumber != $idnumber) {
            //get the suffix appended and add it to the name
            $parts = explode('.', $clone->idnumber);
            $suffix = end($parts);
            $clone->name = $name.'.'.$suffix;
        } else {
            $clone->name = $name;
        }
        $clone->autocreate = false; // avoid warnings
        $clone->save();
        $objs['tracks'] = array($this->id => $clone->id);

        // associate with target cluster (if any)
        if (isset($userset)) {
            clustertrack::associate($userset->id, $clone->id);
        }

        // copy classes
        $clstrks = track_assignment_get_listing($this->id);
        if ($clstrks->valid() === true) {
            $objs['classes'] = array();
            if (!isset($options['classmap'])) {
                $options['classmap'] = array();
            }
            foreach ($clstrks as $clstrkdata) {
                $newclstrk = new trackassignment($clstrkdata);
                $newclstrk->trackid = $clone->id;
                unset($newclstrk->id);
                if (isset($options['classmap'][$clstrkdata->clsid])) {
                    // use existing duplicate class
                    $class = new pmclass($options['classmap'][$clstrkdata->clsid]);
                } else {
                    // no existing duplicate -> duplicate class
                    $class = new pmclass($clstrkdata->clsid);
                    $rv = $class->duplicate($options);
                    if (isset($rv['errors']) && !empty($rv['errors'])) {
                        $objs['errors'] = array_merge($objs['errors'], $rv['errors']);
                    }
                    if (isset($rv['classes'])) {
                        $objs['classes'] = $objs['classes'] + $rv['classes'];
                    }
                }
                $newclstrk->classid = $class->id;
                $newclstrk->courseid = $class->courseid;
                $newclstrk->save();
            }
        }
        unset($clstrks);
        return $objs;
    }
}

/** ------ trackassignment class ------ **/
class trackassignment extends elis_data_object {

    var $verbose_name = 'trackassignment';

    const TABLE = 'crlm_track_class';

    /**
     * User ID-number
     * @var    char
     * @length 255
     */
    protected $_dbfield_id;
    protected $_dbfield_trackid;
    protected $_dbfield_classid;
    protected $_dbfield_courseid;
    protected $_dbfield_autoenrol;
    protected $_dbfield_timecreated;
    protected $_dbfield_timemodified;

    private $location;
    private $templateclass;

    static $associations = array(
        'track' => array(
            'class' => 'track',
            'idfield' => 'trackid'
        ),
        'pmclass' => array(
            'class' => 'pmclass',
            'idfield' => 'courseid'
        ),
        'course' => array(
            'class' => 'course',
            'foreignkey' => 'courseid'
        ),
    );

    static $validation_rules = array(
        array('validation_helper', 'is_unique_trackid_classid')
    );

    public static function delete_for_class($id) {
        global $DB;

        return $DB->delete_records(trackassignment::TABLE, array('classid' => $id));
    }

    public static function delete_for_track($id) {
        global $DB;

        return $DB->delete_records(trackassignment::TABLE, array('trackid' => $id));
    }


    function count_assigned_classes_from_track() {

        //return $this->_db->count_records(trackassignment::TABLE, array('trackid' => $this->trackid));
        $trackassignments = trackassignment::count(new field_filter('trackid', $this->trackid), $this->_db);
        return $trackassignments;

    }

    /**
     * Retrieve records of tracks that have been assigned to
     * the class id
     *
     * @return mixed Returns an array key - track id, value - id of record
     * in crlm_track_class table
     */
    function get_assigned_tracks() {

        $assigned   = array();

        $result = $this->_db->get_recordset(trackassignment::TABLE, array('classid' => $this->classid));
        foreach ($result as $data) {
            $assigned[$data->trackid] = $data->id;
        }
        unset($result);

        return $assigned;
    }

    /**
     * Returns true if class is assigned to a track
     *
     * @return mixed true if record exits, otherwise fale
     */
    function is_class_assigned_to_track() {

        // check if assignment already exists
        return $this->_db->record_exists(trackassignment::TABLE, array('classid' => $this->classid,
                                                                'trackid' => $this->trackid));
    }

    public function set_from_data($data) {
        $this->_load_data_from_record($data, true);
    }

    /**
     * Assign a class to a track, this function also creates
     * and assigns the class to the curriculum default track
     *
     * @return TODO: add meaningful return value
     */
    function save() { //add()

        if (empty($this->courseid)) {
            $this->courseid = $this->_db->get_field(pmclass::TABLE, 'courseid', array('id' => $this->classid));
        }

        if ((empty($this->trackid) or
             empty($this->classid) or
             empty($this->courseid)) and
            empty(elis::$config->elis_program->userdefinedtrack)) {
            cm_error('trackid and classid have not been properly initialized');
            return false;
        } else if ((empty($this->courseid) or
                   empty($this->classid)) and
                  elis::$config->elis_program->userdefinedtrack) {
            cm_error('courseid has not been properly initialized');
        }

        if (!isset($this->id) && $this->is_class_assigned_to_track()) {
            //trying to re-add an existing association
            return;
        }

        // Determine whether class is required
        $curcrsobj = new curriculumcourse(
            array('curriculumid' => $this->track->curid,
                  'courseid'     => $this->courseid)); // TBV: was $this->classid

        // insert assignment record
        parent::save(); //updated for ELIS2 from $this->data_insert_record()

        if ($this->autoenrol && $this->is_autoenrollable()) {
            // autoenrol all users in the track
            // ELIS-7582
            @set_time_limit(0);

            $users = usertrack::get_users($this->trackid);
            foreach ($users as $user) {
                // ELIS-3460: Must check pre-requisites ...
                if (!$curcrsobj->prerequisites_satisfied($user->userid)) {
                    //error_log("/elis/program/lib/data/track.class.php:trackassignment::save(); pre-requisites NOT satisfied for course: {$this->courseid}, curriculum: {$this->track->curid}");
                    continue;
                }
                $now = time();
                $stu_record = new object();
                $stu_record->userid = $user->userid;
                $stu_record->user_idnumber = $user->idnumber;
                $stu_record->classid = $this->classid;
                $stu_record->enrolmenttime = $now;

                $enrolment = new student($stu_record);
                // check enrolment limits
                try {
                    $enrolment->save();
                } catch (pmclass_enrolment_limit_validation_exception $e) {
                    // autoenrol into waitlist
                    $wait_record = new object();
                    $wait_record->userid = $user->userid;
                    $wait_record->classid = $this->classid;
                    $wait_record->enrolmenttime = $now;
                    $wait_record->timecreated = $now;
                    $wait_record->position = 0;
                    $wait_list = new waitlist($wait_record);
                    $wait_list->save();
                } catch (Exception $e) {
                    $param = array('message' => $e->getMessage());
                    echo cm_error(get_string('record_not_created_reason',
                                             'elis_program', $param));
                }
            }
        }

        events_trigger('pm_track_class_associated', $this);
    }

    /**
     * Determines whether a class can be autoenrolled.   To be autoenrollable,
     * the class:
     * - must have the autoenrol flag set
     * - must be the only class in the track for its course
     */
    function is_autoenrollable() {

        if (!$this->autoenrol) {
            return false;
        }
        if (empty($this->courseid)) {
            $this->courseid = $this->_db->get_field(pmclass::TABLE, 'courseid', array('id' => $this->classid));
        }
        $select = "trackid = ? AND courseid = ? AND classid != ?";
        $params = array($this->trackid, $this->courseid, $this->classid);
        return !$this->_db->record_exists_select(trackassignment::TABLE, $select, $params);
    }

    //TODO: document function and return something meaningful
    function assign_class_to_default_track() {
        $trackid = $this->track->create_default_track();
        if (false !== $trackid) {
            $this->trackid = $trackid;
            $this->save();
        }
    }

    /**
     * Returns whether a class has already been assigned to the curriculum -
     * default track
     *
     * @return boolean true if record exists, otherwise false
     */
    function is_class_assigned_to_default_track() {

        // Get the curriculum id
        // check if default track exists
        $exists = $this->_db->record_exists(track::TABLE, 'curid', $this->track->curid,
                                             array('defaulttrack' => 1));

        if (!$exists) {
            return false;
        }

        // Retrieve track id
        $trackid = $this->_db->get_field(track::TABLE, 'id', array('curid' => $this->track->curid,
                                                            'defaulttrack' => 1));
        if (false === $trackid) {
            cm_error('Error #1001: selecting field from crlm_track table');
        }

        // Check if class is assigned to default track
        return $this->_db->record_exists(trackassignment::TABLE, array('classid' => $this->classid,
                                                           'trackid' => $trackid));
    }

    function enrol_all_track_users_in_class() {

        // find all users who are not enrolled in the class
        // TODO: validate this...
        $sql = "NOT EXISTS (SELECT 'x'
                                FROM {".student::TABLE. "} s
                                WHERE s.classid = ? AND s.userid = {".usertrack::TABLE ."}.userid)
                  AND trackid = ?";
        $params = array($this->classid,$this->trackid);
        $users = $this->_db->get_recordset_select(usertrack::TABLE, $sql, $params, 'userid');

        if ($users->valid() === true) {
            // ELIS-7582
            @set_time_limit(0);
            $timenow = time();
            $count = 0;
            $waitlisted = 0;
            $prereq = 0;
            foreach ($users as $user) {
                // enrol user in track
                $enrol = new student();
                $enrol->classid = $this->classid;
                $enrol->userid = $user->userid;
                $enrol->enrolmenttime = $timenow;
                try {
                    $enrol->save();
                    $count++;
                } catch (unsatisfied_prerequisites_exception $ex) {
                    $prereq++;

                } catch (pmclass_enrolment_limit_validation_exception $ex) {
                    // autoenrol into waitlist
                    $wait_record = new stdClass;
                    $wait_record->userid = $user->userid;
                    $wait_record->classid = $this->classid;
                    //$wait_record->enrolmenttime = $timenow;
                    $wait_record->timecreated = $timenow;
                    $wait_record->position = 0;
                    $wait_list = new waitlist($wait_record);
                    $wait_list->save();
                    $waitlisted++;
                }
            }

            print_string('n_users_enrolled', 'elis_program', $count);
            if ($waitlisted) {
                print_string('n_users_waitlisted', 'elis_program', $waitlisted);
            }
            if ($prereq) {
                print_string('n_users_unsatisfied_prereq', 'elis_program', $prereq);
            }
        } else {
            print_string('all_users_already_enrolled', 'elis_program');
        }
        unset($users);
    }

}
/// Non-class supporting functions. (These may be able to replaced by a generic container/listing class)


/**
 * Gets a track listing with specific sort and other filters.
 *
 * @param   string          $sort          Field to sort on
 * @param   string          $dir           Direction of sort
 * @param   int             $startrec      Record number to start at
 * @param   int             $perpage       Number of records per page
 * @param   string          $namesearch    Search string for curriculum name
 * @param   string          $alpha         Start initial of curriculum name filter
 * @param   int             $curriculumid  Necessary associated curriculum
 * @param   int             $clusterid     Necessary associated cluster
 * @param   pm_context_set  $contexts      Contexts to provide permissions filtering, of null if none
 * @param   int             $userid        The id of the user we are assigning to tracks
 *
 * @return  object array                   Returned records
 */
function track_get_listing($sort='name', $dir='ASC', $startrec=0, $perpage=0, $namesearch='',
                           $alpha='', $curriculumid = 0, $parentclusterid = 0, $contexts = null, $userid = 0) {
    global $USER, $DB;

    $params = array();
    $NAMESEARCH_LIKE = $DB->sql_like('trk.name', ':search_namesearch', FALSE);
    $ALPHA_LIKE = $DB->sql_like('trk.name', ':search_alpha', FALSE);

    $select = 'SELECT trk.*, cur.name AS parcur, (SELECT COUNT(*) ' .
              'FROM {' . trackassignment::TABLE . '} '.
              "WHERE trackid = trk.id ) as class ";
    $tables = 'FROM {' . track::TABLE . '} trk '.
              'JOIN {' .curriculum::TABLE . '} cur ON trk.curid = cur.id ';
    $join   = '';
    $on     = '';

    $where = array('trk.defaulttrack = 0');

    if (!empty($namesearch)) {
        $namesearch = trim($namesearch);
        $where[] = $NAMESEARCH_LIKE;
        $params['search_namesearch'] = "%{$namesearch}%";
    }

    if ($alpha) {
        //$where[] = "(trk.name $LIKE '$alpha%')";
        $where[] = $ALPHA_LIKE;
        $params['search_alpha'] = "{$alpha}%";
    }

    if ($curriculumid) {
        $where[] = "(trk.curid = :curid)";
        $params['curid'] = $curriculumid;
    }

    if ($parentclusterid) {
        $where[] = "(trk.id IN (SELECT trackid FROM {".clustertrack::TABLE."}
                            WHERE clusterid = :parentclusterid))";
        $params['parentclusterid'] = $parentclusterid;
    }

    if ($contexts !== null) {
        $filter_object = $contexts->get_filter('id', 'track');
        $filter_sql = $filter_object->get_sql(false, 'trk', SQL_PARAMS_NAMED);
        if (isset($filter_sql['where'])) {
            $where[] = $filter_sql['where'];
            $params += $filter_sql['where_parameters'];
        }
    }

    if (!empty($userid)) {
        //get the context for the "indirect" capability
        $context = pm_context_set::for_user_with_capability('cluster', 'elis/program:track_enrol_userset_user', $USER->id);

        $allowed_clusters = array();

        $clusters = cluster_get_user_clusters($userid);
        $allowed_clusters = $context->get_allowed_instances($clusters, 'cluster', 'clusterid');

        $curriculum_context = pm_context_set::for_user_with_capability('cluster', 'elis/program:track_enrol', $USER->id);
        $curriculum_filter_object = $curriculum_context->get_filter('id', 'track');
        $curriculum_filter = $curriculum_filter_object->get_sql(false, 'trk');

        if (isset($curriculum_filter['where'])) {
            if (count($allowed_clusters)!=0) {
                $where[] = $curriculum_filter['where'];
                $params += $curriculum_filter['where_parameters'];
            } else {
                //this allows both the indirect capability and the direct track filter to work

                $allowed_clusters_list = implode(',', $allowed_clusters);
                $where[] = "(
                              trk.id IN (
                                SELECT clsttrk.trackid
                                FROM {". clustertrack::TABLE. "} clsttrk
                                WHERE clsttrk.clusterid IN (:allowed_clusters)
                              )
                              OR
                              {$curriculum_filter['where']}
                            )";
               $params['allowed_clusters'] = $allowed_clusters_list;
            }
        }
    }

    if (!empty($where)) {
        $where = 'WHERE '.implode(' AND ',$where).' ';
    } else {
        $where = '';
    }

    if ($sort) {
        $sort = 'ORDER BY '.$sort .' '. $dir.' ';
    }

    $sql = $select.$tables.$join.$on.$where.$sort;

    return $DB->get_records_sql($sql, $params, $startrec, $perpage);
}

/**
 * Calculates the number of records in a listing as created by track_get_listing
 *
 * @param   string          $namesearch    Search string for curriculum name
 * @param   string          $alpha         Start initial of curriculum name filter
 * @param   int             $curriculumid  Necessary associated curriculum
 * @param   int             $clusterid     Necessary associated cluster
 * @param   pm_context_set  $contexts      Contexts to provide permissions filtering, of null if none
 * @return  int                            The number of records
 */
function track_count_records($namesearch = '', $alpha = '', $curriculumid = 0, $parentclusterid = 0, $contexts = null) {
    global $DB;

    //$LIKE = $this->_db->sql_compare();
    $params = array();
    $NAMESEARCH_LIKE = $DB->sql_like('name', ':search_namesearch', FALSE);
    $ALPHA_LIKE = $DB->sql_like('name', ':search_alpha', FALSE);

    $where = array('defaulttrack = 0');

    if (!empty($namesearch)) {
        //$where[] = "name $LIKE '%$namesearch%'";
        $where[] = $NAMESEARCH_LIKE;
        $params['search_namesearch'] = "%{$namesearch}%";
    }

    if ($alpha) {
        //$where[] = "(name $LIKE '$alpha%')";
        $where[] = $ALPHA_LIKE;
        $params['search_alpha'] = "{$alpha}%";
    }

    if ($curriculumid) {
        //$where[] = "(curid = $curriculumid)";
        $where[] = "(curid = :curriculumid)";
        $params['curriculumid'] = $curriculumid;
    }

    if ($parentclusterid) {
        $where[] = "(id IN (SELECT trackid FROM {".clustertrack::TABLE."}
                            WHERE clusterid = :parentclusterid))";
        $params['parentclusterid'] = $parentclusterid;
    }

    if ($contexts !== null) {
        /* TODO: not working yet...
        $filter_object = $contexts->filter_for_context_level('id', 'track');
        $where[] = $filter_object->get_sql();
        */
        $filter_object = $contexts->get_filter('id', 'track');
        $filter_sql = $filter_object->get_sql(false, null, SQL_PARAMS_NAMED);
        if (isset($filter_sql['where'])) {
            $where[] = $filter_sql['where'];
            $params += $filter_sql['where_parameters'];
        }
    }

    $where = implode(' AND ', $where);

    return $DB->count_records_select(track::TABLE, $where, $params);
}

/**
 * Retrieve a list of tracks based on a curriculum id
 * excluding default tracks
 *
 * @param int curid curriculum id
 *
 * @return mixed array of crlm_track objects or false if
 * nothing was found
 */
function track_get_list_from_curr($curid) {
    global $DB;

    $tracks = $DB->get_records(track::TABLE, array('curid' => $curid));

    if (is_array($tracks)) {
        foreach ($tracks as $key => $track) {
            if (1  == $track->defaulttrack) {
                unset($tracks[$key]);
            }
        }
    }
    return $tracks;
}

/**
 * Gets a track assignment listing with specific sort and other filters.
 *
 * @param int $trackid track id
 * @param string $sort Field to sort on.
 * @param string $dir Direction of sort.
 * @param int $startrec Record number to start at.
 * @param int $perpage Number of records per page.
 * @param string $namesearch Search string for curriculum name.
 * @param string $alpha Start initial of curriculum name filter.
 * @param array $extrafilters Additional filters to apply to the count
 * @return recordset Returned records.
 */
function track_assignment_get_listing($trackid = 0, $sort='cls.idnumber', $dir='ASC', $startrec=0, $perpage=0, $namesearch='',
                                      $alpha='', $extrafilters = array()) {
    global $DB;

    $params = array();
    $NAMESEARCH_LIKE = $DB->sql_like('cls.idnumber', ':search_namesearch', FALSE);
    $ALPHA_LIKE = $DB->sql_like('cls.idnumber', ':search_alpha', FALSE);

    $select = 'SELECT trkassign.*, cls.idnumber as clsname, cls.id as clsid, enr.enrolments, curcrs.required ';
    $tables = ' FROM {' . trackassignment::TABLE . '} trkassign ';
    $join   = " JOIN {" . track::TABLE ."} trk ON trkassign.trackid = trk.id
                JOIN {". pmclass::TABLE ."} cls ON trkassign.classid = cls.id
                JOIN {". curriculumcourse::TABLE . "} curcrs ON curcrs.curriculumid = trk.curid AND curcrs.courseid = cls.courseid ";

    //calculate an appropriate condition if we need to filter out inactive users
    $inactive_condition = '';
    if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
        $inactive_condition = ' AND u.inactive = 0';
    }

    // get number of users from track who are enrolled
    $join  .= "LEFT JOIN (SELECT s.classid, COUNT(s.userid) AS enrolments
                            FROM {". student::TABLE ."} s
                            JOIN {". usertrack::TABLE ."} t USING(userid)
                            JOIN {".user::TABLE."} u
                              ON t.userid = u.id
                              {$inactive_condition}
                           WHERE t.trackid = :trackid
                        GROUP BY s.classid) enr ON enr.classid = cls.id ";
    $params['trackid'] = $trackid;

    //apply the track filter to the outermost query if applicable
    if ($trackid == 0) {
        $where = ' TRUE';
    } else {
        $where = ' trkassign.trackid = :assign_trackid';
        $params['assign_trackid'] = $trackid;
    }

    if (!empty($namesearch)) {
        $namesearch = trim($namesearch);
        $where .= (!empty($where) ? ' AND ' : '') . $NAMESEARCH_LIKE;
        $params['search_namesearch'] = '%'.$namesearch.'%';
    }

    if ($alpha) {
        $where .= (!empty($where) ? ' AND ' : '') . $ALPHA_LIKE;
        $params['search_alpha'] = $alpha.'%';
    }

    if (!empty($extrafilters['contexts'])) {
        //apply a filter related to filtering on particular PM class contexts
        $filter_object = $extrafilters['contexts']->get_filter('id', 'class');
        $filter_sql = $filter_object->get_sql(false, 'cls', SQL_PARAMS_NAMED);

        if (!empty($filter_sql)) {
            //user does not have access at the system context
            $where .= 'AND '.$filter_sql['where'];
            $params = array_merge($params, $filter_sql['where_parameters']);
        }
    }

    if (!empty($where)) {
        $where = 'WHERE '.$where.' ';
    }

    if ($sort) {
        $sort = 'ORDER BY '.$sort .' '. $dir.' ';
    }

    $sql = $select.$tables.$join.$where.$sort;
    return $DB->get_recordset_sql($sql, $params, $startrec, $perpage);
}

/**
 * Gets the number of items in the track assignment listing
 *
 * @param   int     $trackid       The id of the track to obtain the listing for
 * @param   string  $namesearch    Search string for curriculum name
 * @param   string  $alpha         Start initial of curriculum name filter
 * @param   array   $extrafilters  Additional filters to apply to the count
 * @return  int                    The number of appropriate records
 */
function track_assignment_count_records($trackid, $namesearch = '', $alpha = '', $extrafilters = array()) {
    global $DB;

    //$LIKE = $this->_db->sql_compare();
    $params = array();
    $NAMESEARCH_LIKE = $DB->sql_like('cls.idnumber', ':search_namesearch', FALSE);
    $ALPHA_LIKE = $DB->sql_like('cls.idnumber', ':search_alpha', FALSE);

    $select = 'SELECT COUNT(*) ';
    $tables = ' FROM {' . trackassignment::TABLE . '} trkassign ';
    $join   = ' JOIN {' . track::TABLE. '} trk ON trkassign.trackid = trk.id
                JOIN {' . pmclass::TABLE. '} cls ON trkassign.classid = cls.id
                JOIN {' . curriculumcourse::TABLE. '} curcrs ON curcrs.curriculumid = trk.curid AND curcrs.courseid = cls.courseid ';
    // get number of users from track who are enrolled
    $join  .= 'LEFT JOIN (SELECT s.classid, COUNT(s.userid) AS enrolments
                            FROM {' . student::TABLE. '} s
                            JOIN {' . usertrack::TABLE. '} t USING(userid)
                           WHERE t.trackid = :trackid
                        GROUP BY s.classid) enr ON enr.classid = cls.id ';
    $params['trackid'] = $trackid;

    $where = ' trkassign.trackid = :assign_trackid';
    $params['assign_trackid'] = $trackid;

    if (!empty($namesearch)) {
        $namesearch = trim($namesearch);
        $where .= (!empty($where) ? ' AND ' : '') . $NAMESEARCH_LIKE;
        $params['search_namesearch'] = "%{$namesearch}%";
    }

    if ($alpha) {
        $where .= (!empty($where) ? ' AND ' : '') . $ALPHA_LIKE;
        $params['search_alpha'] = "{$alpha}%";
    }

    if (!empty($extrafilters['contexts'])) {
        //apply a filter related to filtering on particular PM class contexts
        $filter_object = $extrafilters['contexts']->get_filter('id', 'class');
        $filter_sql = $filter_object->get_sql(false, 'cls', SQL_PARAMS_NAMED);

        if (!empty($filter_sql)) {
            //user does not have access at the system context
            $where .= 'AND '.$filter_sql['where'];
            $params = array_merge($params, $filter_sql['where_parameters']);
        }
    }

    if (!empty($where)) {
        $where = 'WHERE '.$where.' ';
    }

    $sql = $select.$tables.$join.$where;

    return $DB->count_records_sql($sql, $params);
}
