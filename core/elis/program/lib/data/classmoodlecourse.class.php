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
require_once elispm::lib('data/pmclass.class.php');
require_once elispm::lib('data/user.class.php');
require_once elispm::lib('data/coursetemplate.class.php');
require_once elispm::lib('data/student.class.php');
require_once elispm::lib('data/instructor.class.php');
require_once elispm::lib('moodlecourseurl.class.php');

define ('CLSMDLENROLAUTO', 0);          // Automatically assign roles in Moodle course.
define ('CLSMDLENROLCHOICE', 1);        // Allow user to choose at time of assignment.

class classmoodlecourse extends elis_data_object {
    const TABLE = 'crlm_class_moodle';

    static $associations = array(
        'pmclass' => array(
            'class' => 'pmclass',
            'idfield' => 'classid'
        )
    );

    static $validation_rules = array(
        array('validation_helper', 'is_unique_classid_moodlecourseid')
    );

    protected $_dbfield_classid;
    protected $_dbfield_moodlecourseid;
    //protected $_dbfield_siteconfig;
    protected $_dbfield_enroltype;
    protected $_dbfield_enrolplugin;
    protected $_dbfield_timemodified;
    protected $_dbfield_autocreated;

    var $siteconfig = ''; // TBD: not a _dbfield, leave empty?

	public static function delete_for_class($id) {
    	return $this->_db->delete_records(classmoodlecourse::TABLE, array('classid'=>$id));
    }

    /////////////////////////////////////////////////////////////////////
    //                                                                 //
    //  DATA FUNCTIONS:                                                //
    //                                                                 //
    /////////////////////////////////////////////////////////////////////

    /**
     * Enrol the instructors associated with the class into the attached Moodle
     * course.
     *
     * @param none
     * @return bool True on success, False otherwise.
     */
    function data_enrol_instructors() {
        if (empty($this->classid) || empty($this->moodlecourseid) ||
            (!empty($this->siteconfig) && !file_exists($this->siteconfig))) {

            return false;
        }

        $instructors = instructor::find(new field_filter('classid', $this->classid));
        if (elis::$config->elis_program->default_instructor_role && $instructors->valid()) {
            /// At this point we must switch over the other Moodle site's DB config, if needed
            if (!empty($this->siteconfig)) {
                // TBD: implement this in the future if needed in v2
                //$cfgbak = moodle_load_config($this->siteconfig);
            }

            /// This has to be put here in case we have a site config reload.
            $CFG    = $GLOBALS['CFG'];

            if (!$context = get_context_instance(CONTEXT_COURSE, $this->moodlecourseid)) {
                return false;
            }

            $plugin = enrol_get_plugin('elis');
            $enrol = $plugin->get_or_create_instance($this->_db->get_record('course', array('id'=>$this->moodlecourseid)));

            foreach ($instructors as $instructor) {
                /// Make sure that a Moodle account exists for this user already.
                $user = $instructor->users;
                if (!($muser = $user->get_moodleuser())) {
                    if (!$muserid = $user->synchronize_moodle_user(true, true)) {
                        throw new Exception(get_string('errorsynchronizeuser', self::LANG_FILE));
                    }
                } else {
                    $muserid = $muser->id;
                }

                if (!is_enrolled($context, $muserid)) {
                    $plugin->enrol_user($enrol, $muserid, elis::$config->elis_program->default_instructor_role, 0, 0);
                }
            }

            /// Reset $CFG object.
            if (!empty($this->siteconfig)) {
                // TBD: implement this in the future if needed in v2
                //moodle_load_config($cfgbak->dirroot . '/config.php');
            }
        }
        $instructors->close();

        return true;
    }

    /**
     * Enrol the students associated with the class into the attached Moodle
     * course.
     *
     * @param none
     * @return bool True on success, False otherwise.
     */
    function data_enrol_students() {
        if (empty($this->classid) || empty($this->moodlecourseid) ||
            (!empty($this->siteconfig) && !file_exists($this->siteconfig))) {

            return false;
        }

        $students = student::find(new field_filter('classid', $this->classid));
        if ($students->valid()) {
            /// At this point we must switch over the other Moodle site's DB config, if needed
            if (!empty($this->siteconfig)) {
                // TBD: implement this in the future if needed in v2
                //$cfgbak = moodle_load_config($this->siteconfig);
            }

            /// This has to be put here in case we have a site config reload.
            $CFG    = $GLOBALS['CFG'];

            if (!$context = get_context_instance(CONTEXT_COURSE, $this->moodlecourseid)) {
                return false;
            }

            $plugin = enrol_get_plugin('elis');
            $enrol = $plugin->get_or_create_instance($this->_db->get_record('course', array('id'=>$this->moodlecourseid)));

            foreach ($students as $student) {
                /// Make sure that a Moodle account exists for this user already.
                $user = $student->users;
                if (!($muser = $user->get_moodleuser())) {
                    if (!$muserid = $user->synchronize_moodle_user(true, true)) {
                        throw new Exception(get_string('errorsynchronizeuser', self::LANG_FILE));
                    }
                } else {
                    $muserid = $muser->id;
                }

                if (!is_enrolled($context, $muserid)) {
                    $plugin->enrol_user($enrol, $muserid, $enrol->roleid, $student->enrolmenttime, $student->endtime);
                }
            }

            /// Reset $CFG object.
            if (!empty($this->siteconfig)) {
                // TBD: implement this in the future if needed in v2
                //moodle_load_config($cfgbak->dirroot . '/config.php');
            }
        }
        $students->close();

        return true;
    }
}

/// Non-class supporting functions. (These may be able to replaced by a generic container/listing class)

/**
 * Load a config file from another local Moodle instance and set the database
 * values in the $CFG global and initializing the new ADODB database connection.
 * NOTE: nothing should be calling this function until (if) we need this functionality in v2
 *
 * @uses $CFG
 * @uses $db
 * @param string $file    The full system path to the config.php file.
 * @param bool   $justroot Just get and set the wwwroot value, don't actually reset the
 *                         global DB connection or load the alternate config options.
 * @return object bool True on sucess, false otherwise.
 */
function moodle_load_config($file, $justroot = false) {
    global $CFG, $db;

    $return = false;

    if (file_exists($file) && ($fp = fopen($file, 'r'))) {
        while ($line = fgets($fp)) {
            if ($line[0] != '$') {
                continue;
            }

            $regex = "/^\$CFG->[a-z]+|= '(.+|)';/";
            $parts = preg_split($regex, $line, -1, PREG_SPLIT_DELIM_CAPTURE);

            if (count($parts) < 2) {
                continue;
            }

            $arg = trim($parts[0]);
            $val = trim($parts[1]);

            if ($justroot) {
                if ($arg == '$CFG->wwwroot') {
                    return $val;
                }
            } else {
                if ($arg == '$CFG->dbtype') {
                    $CFG->dbtype = $val;
                } else if ($arg == '$CFG->dbhost') {
                    $CFG->dbhost = $val;
                } else if ($arg == '$CFG->dbname') {
                    $CFG->dbname = $val;
                } else if ($arg == '$CFG->dbuser') {
                    $CFG->dbuser = $val;
                } else if ($arg == '$CFG->dbpass') {
                    $CFG->dbpass = $val;
                } else if ($arg == '$CFG->dbpersist') {
                    $CFG->dbpersist = ($val == 'true') ? true : false;
                } else if ($arg == '$CFG->prefix') {
                    $CFG->prefix = $val;
                }
            }
        }

        require_once ($CFG->libdir . '/adodb/adodb.inc.php');

        /// Initialize the new DB connection.
        $db->Disconnect();
        unset($db);
        $db = &ADONewConnection($CFG->dbtype);

        if (!isset($CFG->dbpersist) or !empty($CFG->dbpersist)) {    // Use persistent connection (default)
            $dbconnected = $db->PConnect($CFG->dbhost,$CFG->dbuser,$CFG->dbpass,$CFG->dbname);
        } else {                                                     // Use single connection
            $dbconnected = $db->Connect($CFG->dbhost,$CFG->dbuser,$CFG->dbpass,$CFG->dbname);
        }

        /// Save the new global objects.
        if ($dbconnected) {
            $GLOBALS['CFG'] = $CFG;
            $GLOBALS['db']  = $db;

            $this->_db = database_factory(CURMAN_APPPLATFORM);
            $this->_db = database_factory('airtran');

            $return = true;
        }
    }

    return $return;
}

/**
 * Return the $CFG->wwwroot value for the specific Moodle site.
 *
 * @uses $CFG
 * @param string $siteconfig The full system path to the config.php file (optional).
 * @return string The $CFG->wwwroot value.
 */
function moodle_get_wwwroot($siteconfig = '') {
    global $CFG;

    if (empty($siteconfig)) {
        return $CFG->wwwroot;
    }

    // TBD: implement this in the future if needed in v2
    //return moodle_load_config($siteconfig, true);
    return $CFG->wwwroot;
}

/**
 * Return the 'fullname' for the specific Moodle site.
 *
 * @uses $CFG
 * @param string $siteconfig The full system path to the config.php file (optional).
 * @return string The 'fullname' value for the site course.
 */
function moodle_get_sitename($siteconfig = '') {
    global $CFG, $DB;

    $sitename = '';
    $cfgbak   = $CFG->dirroot . '/config.php';

    if (!empty($siteconfig)) {
        // TBD: implement this in the future if needed in v2
        //moodle_load_config($siteconfig);
    }

    $sitename = $DB->get_field('course', 'fullname', array('id' => SITEID));

    if (!empty($siteconig)) {
        // TBD: implement this in the future if needed in v2
        //moodle_load_config($cfgbak);
    }

    return $sitename;
}


/**
 * Return the top-level course category ID
 *
 * @uses $CFG
 * @param string $siteconfig The full system path to the config.php file (optional).
 * @return int The category ID.
 */
function moodle_get_topcatid($siteconfig = '') {
    global $CFG, $DB;

    $catid  = 0;
    $cfgbak = $CFG->dirroot . '/config.php';

    if (!empty($siteconfig)) {
        // TBD: implement this in the future if needed in v2
        //moodle_load_config($siteconfig);
    }

    $catid = $DB->get_field('course_categories', 'id', array('parent' => '0'));

    if (!empty($siteconig)) {
        // TBD: implement this in the future if needed in v2
        //moodle_load_config($cfgbak);
    }

    return $catid;
}


/**
 * Attach a class record from this system to an existing Moodle course.
 *
 * @param int    $clsid           The class ID.
 * @param int    $mdlid           The Moodle course ID.
 * @param string $siteconfig      The full system path to a Moodle congif.php file (defaults to local).
 * @param bool   $enrolinstructor Flag for enroling instructors into the Moodle course (optional).
 * @param bool   $enrolstudent    Flag for enroling students into the Moodle course (optional).
 * @return bool True on success, False otherwise.
 */
function moodle_attach_class($clsid, $mdlid, $siteconfig = '', $enrolinstructor = false,
                             $enrolstudent = false, $autocreate = false) {
    //$CFG global is needed by the rollover lib
    global $DB, $CFG;

    $result = true;
    $moodlecourseid = $mdlid;

    /// Look for an existing link for this class.
    if (!$clsmdl = $DB->get_record(classmoodlecourse::TABLE, array('classid'=>$clsid))) {
        /// Make sure the specified Moodle site config file exists.
        if (!empty($siteconfig) && !file_exists($siteconfig)) {
            return false;
        }

        if ($autocreate) {
            // auto create is checked, create connect to moodle course
            $cls        = new pmclass($clsid);
            //attempt to obtain the course template
            $template = coursetemplate::find(new field_filter('courseid', $cls->courseid));
            if ($template->valid()) {
                $template = $template->current();
            }

            // no template defined, so do nothing
            if (empty($template->id) || empty($template->location)) {
                print_error('notemplate', 'elis_program');
            }
            $classname  = $template->templateclass;

            $obj        = new $classname();
            $courseid   = $template->location;

            //perform the rollover
            require_once(elis::lib('rollover/lib.php'));
            $moodlecourseid   = course_rollover($courseid);
            //check that the course has rolled over successfully
            if (!$moodlecourseid) {
                return false;
            }

            //set the Moodle course name as expected
            $restoredcourse = new stdClass;
            $restoredcourse->id = $moodlecourseid;
            // ELIS-2941: Don't prepend course name if already present @ start
            if (strpos($cls->idnumber, $cls->course->name) !== 0) {
                $restoredcourse->fullname = $cls->course->name .'_'. $cls->idnumber;
            } else {
                $restoredcourse->fullname = $cls->idnumber;
            }
            $restoredcourse->shortname = $cls->idnumber;
            $DB->update_record('course', $restoredcourse);
        }

        $newrec = array(
            'classid'        => $clsid,
            'moodlecourseid' => $moodlecourseid,
            'siteconfig'     => $siteconfig,
            'autocreated'    => $autocreate ? 1 : 0,
        );

        $clsmdl = new classmoodlecourse($newrec);
        $clsmdl->save();

    } else {
        $clsmdl = new classmoodlecourse($clsmdl->id);
    }

    if ($enrolinstructor) {
        $clsmdl->data_enrol_instructors();
    }

    if ($enrolstudent) {
        $clsmdl->data_enrol_students();
    }

    events_trigger('pm_classinstance_associated', $clsmdl);

    return true;
}
/**
 * Detach the Moodle course from this class record.
 *
 * @param int    $clsid           The class ID.
 * @param int    $mdlid           The Moodle course ID.
 * @return bool True on success, False otherwise.
 */
function moodle_detach_class($clsid, $mdlid) {
    global $DB;

    /// Delete the crlm_class_moodle record
    $select = 'classid = ? AND moodlecourseid = ?';
    $params = array($clsid, $mdlid);
    return $DB->delete_records_select('crlm_class_moodle', $select, $params);
}

function moodle_get_classes() {
    return classmoodlecourse::find(array(new join_filter('moodlecourseid', 'course', 'id'),
                                         new join_filter('classid', pmclass::TABLE, 'id'),
                                         new field_filter('id', null, field_filter::NEQ)));
}

/**
 * Get the Moodle course ID for a specific class ID.
 *
 * @param int $clsid The class ID to try and get a Moodle course ID for.
 * @return int|bool The Moodle course ID or false on error.
 */
function moodle_get_course($clsid) {
    global $DB;

    return $DB->get_field(classmoodlecourse::TABLE, 'moodlecourseid', array('classid' => $clsid));
}
