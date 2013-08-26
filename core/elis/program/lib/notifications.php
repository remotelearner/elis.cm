<?php
/**
 * ELIS Notifications Class Definitions
 *
 * Contains all of the classes necessary to provide a notifications API for
 * the ELIS system.
 * This file includes functions from 2.0 that do not exist in 1.9. In particular:
 *  - functions from '/lib/messagelib.php'
 *
 * Notifications can be sent by instantiating an object and calling send_message, or
 * by using the static 'notify' function.
 *
 * Object Example:
 *      $message = new notification();
 *      $message->send_notification("This is a test", $USER);
 *
 * Static Example:
 *      notification::notify("This is a test", $USER);
 *
 * The send_handler function, while public, is intended to be used by the events
 * system only. It is registered in the '/block/cur_admin/events.php' file.
 *
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

global $CFG;
require_once ($CFG->dirroot.'/elis/program/lib/setup.php');
require_once elispm::lib('data/pmclass.class.php');
require_once elispm::lib('data/instructor.class.php');

class message {

    /// In 2.0, the message_send_handler in /lib/messagelib takes a structure with defined variables below.
    /// These are loaded into a generic object. They are defined here for better documentation, and should
    /// not have to be replaced when upgraded to 2.0.
    /// If a message class is created, and is the same as this, it can be removed here and the notification
    /// class below can extend it instead.
    // TODO: Not sure if we need to extend message_output_email or class_message_output

    public    $modulename;                // The name of the Moodle subsystem generating the event.
    public    $component;                 // The component in the subsystem.
    public    $name;                      // A descriptive name for this event.
    public    $userfrom;                  // The sending user object.
    public    $userto;                    // The receiving user object.
    public    $subject;                   // Subject for the message.
    public    $fullmessage;               // Full text of the message.
    public    $fullmessageformat;         // Format for the message (FORMAT_PLAIN, FORMAT_HTML).
    public    $fullmessagehtml;           // Full HTML of the message.
    public    $smallmessage;              // Brief version of the message.
/*
 * ---------------------------------------------------------------------------------------
 *
 * PUBLIC FUNCTIONS
 *
 */

    public function __construct() {

        $site = get_site();

        $this->modulename = '';
        $this->component = '';
        $this->name = '';
        $this->userfrom = '';
        $this->userto = '';
        $this->subject = '';
        $this->fullmessage = '';
        $this->fullmessageformat = FORMAT_HTML;
        $this->fullmessagehtml = '';
        $this->smallmessage = '';
    }

    public function __destruct() {
    }
/*
 * ---------------------------------------------------------------------------------------
 *
 * PRIVATE FUNCTIONS
 *
 */

/*
 * ---------------------------------------------------------------------------------------
 */
}

class notificationlog extends elis_data_object {
    const TABLE = 'crlm_notification_log';

    var $verbose_name = 'notificationlog';

    /**
     * Notification log ID-number
     * @var    int
     * @length 10
     */
    protected $_dbfield_id;
    /**
     * Notification log event string
     * @var    char
     * @length 166
     */
    protected $_dbfield_event;
    protected $_dbfield_instance;
    protected $_dbfield_userid;
    protected $_dbfield_data;
    protected $_dbfield_timecreated;

    private $location;
    private $templateclass;

    static $associations = array(
        'userid' => array(
            'class' => 'user',
            'idfield' => 'userid'
        )
    );
}

class notification extends message {
    /// In 2.0, the message_send_handler in /lib/messagelib takes a structure with defined variables below.
    /// These are loaded into a generic object. They are defined here for better documentation, and should
    /// not have to be replaced when upgraded to 2.0.
    /// If a message class is created, and is the same as this, it can be removed here and the notification
    /// class below can extend it instead.

    private $defname;
    private $defsubject;
/*
 * ---------------------------------------------------------------------------------------
 *
 * PUBLIC FUNCTIONS
 *
 */

    public function __construct() {

        parent::__construct();

        //todo: determine if the modulename property is still needed
        $this->modulename = 'curriculum';
        $this->component = 'elis_program';

        $this->defname    = "notify_pm";
        $site = get_site();
        $this->defsubject = "Message Event from {$site->fullname}";
    }

    public function __destruct() {
    }

    /*
     * Function to notify required users about an event. Assumes most parameters have been
     * preset.
     *
     * @param char $message The text of the message.
     * @param object $userto A Moodle generic user object, or a PM user class object that the message is to.
     * @param object $userfrom A Moodle generic user object, or a PM user class object that the message is from.
     * @param object $logevent Information to log to 'crlm_notification_log' (can include fields userto, fromuserid,
     *                         instance, data, timecreated).
     *
     */
    public function send_notification($message='', $userto=null, $userfrom=null, $logevent=false) {
        global $DB, $USER;

        /// Handle parameters:
        if (!empty($userto)) {
            $this->userto = $userto;
        } else if (empty($this->userto)) {
            if (in_cron()) {
                mtrace(get_string('message_nodestinationuser', 'elis_program'));
            } else {
                print_error('message_nodestinationuser', 'elis_program');
            }
            return false;
        }

        if (!empty($userfrom)) {
            $this->userfrom = $userfrom;
        } else if (empty($this->userfrom)) {
            $this->userfrom = $USER;
        }

        if ($message != '') {
            $this->fullmessage = $message;
        }

        /// Check for the user object type. If a PM User was sent in, need to get the Moodle object.
        //todo: convert this code to use "is_a"
        $topmuserid = false;
        if (get_class($this->userto) == 'user') {
            $topmuserid = $this->userto->id;
//            if (!($this->userto = cm_get_moodleuser($this->userto->id))) {
            if (!($this->userto = $this->userto->get_moodleuser())) {
                if (in_cron()) {
                    mtrace(get_string('nomoodleuser', 'elis_program'));
                } else {
                    debugging(get_string('nomoodleuser', 'elis_program'));
                }
            }
        }
        if (empty($this->userto)) {
            // ELIS-3632: prevent DB errors downstream
            if (in_cron()) {
                mtrace(get_string('message_nodestinationuser', 'elis_program'));
            } else {
                print_error('message_nodestinationuser', 'elis_program');
            }
            return false;
        }

        if (get_class($this->userfrom) == 'user') {
//            if (!($this->userfrom = cm_get_moodleuser($this->userfrom->id))) {
            if (!($this->userfrom = $this->userfrom->get_moodleuser())) {
                if (in_cron()) {
                    mtrace(get_string('nomoodleuser', 'elis_program'));
                } else {
                    debugging(get_string('nomoodleuser', 'elis_program'));
                }
            }
        }
        if (empty($this->userfrom)) {
            // ELIS-3632: prevent DB errors downstream
            $this->userfrom = $USER; // TBD
        }

        /// Handle unset variables:
        $this->name    = ($this->name == '') ? $this->defname : $this->name;
        $this->subject = ($this->subject == '') ? $this->defsubject : $this->subject;

        //this call performs the core work involved in notifying users
        pm_notify_send_handler(clone($this));

    /// Insert a notification log if we have data for it.
        if ($logevent !== false) {
            if (!empty($logevent->event)) {
                $newlog = new Object();
                $newlog->event = $logevent->event;
                if (isset($logevent->userid)) {
                    $newlog->userid = $logevent->userid;
                } else if ($topmuserid === false){
                    $newlog->userid = pm_get_crlmuserid($this->userto->id);
                } else {
                    $newlog->userid = $topmuserid;
                }

                //if the log entry specifies which user triggered the event,
                //store that info
                //NOTE: Do not use $userfrom because that is the message sender
                //but not necessarily the user whose criteria triggered the event
                if (isset($logevent->fromuserid)) {
                    $newlog->fromuserid = $logevent->fromuserid;
                }

                if (isset($logevent->instance)) {
                    $newlog->instance = $logevent->instance;
                }
                if (isset($logevent->data)) {
                    $newlog->data = $logevent->data;
                }
                if (isset($logevent->timecreated)) {
                    $newlog->timecreated = $logevent->timecreated;
                } else {
                    $newlog->timecreated = time();
                }
                $DB->insert_record('crlm_notification_log', $newlog);
            }
        }
    }

    /*
     * ---------------------------------------------------------------------------------------
     * STATIC API FUNCTIONS:
     *
     * These functions can be used without instatiating an object.
     * Usage: message::[function_name([args])]
     *
     */

    /*
     * Function to notify required users about an event. Assumes most parameters have been
     * preset.
     *
     */
    public static function notify($text='', $userto=null, $userfrom=null) {

        $message = new notification();
        $message->send_notification($text, $userto, $userfrom);
    }

    /*
     * ---------------------------------------------------------------------------------------
     * STATIC EVENT HANDLERS:
     *
     * These functions are intended to be loaded as event handlers with a function string
     * like "notification::send_handler". Unfortunately, pre-PHP 5.2.3, the PHP function
     * 'call_user_func' can't take 'static' functions, and therefore this will not work. They
     * are re-created outside of the class as stand-alone functions as well. These need to be
     * used to work with versions before 5.2.3.
     * When the system is updated to work with Moodle 2.0, we can move the handlers below back
     * into this class as static functions.
     */

    public static function send_handler($eventdata){
        return pm_notify_send_handler($eventdata);
    }

    public static function role_assign_handler($eventdata){
        return pm_notify_role_assign_handler($eventdata);
    }
/*
 * ---------------------------------------------------------------------------------------
 *
 * PRIVATE FUNCTIONS
 *
 */

/*
 * ---------------------------------------------------------------------------------------
 */
}

/*
 * ---------------------------------------------------------------------------------------
 * NOTIFICATION EVENT HANDLERS:
 *
 * These functions are intended to be loaded as event handlers with a function string
 * like "notification::send_handler". Unfortunately, pre-PHP 5.2.3, the PHP function
 * 'call_user_func' can't take 'static' functions, and therefore this will not work. They
 * are re-created outside of the class as stand-alone functions as well. These need to be
 * used to work with versions before 5.2.3.
 * When the system is updated to work with Moodle 2.0, we can move the handlers below back
 * into the class above as static functions.
 */

/**
 * Triggered when a message provider wants to send a message.
 * This functions checks the user's processor configuration to send the given type of message,
 * then tries to send it.
 * Note: This method is sometimes called directly to save on performance.
 * @param object $eventdata information about the message (origin, destination, type, content)
 * @return boolean success
 */
function pm_notify_send_handler($eventdata){
    global $CFG, $SITE;

    require_once($CFG->dirroot.'/message/lib.php');

    //the following setup work is very similar to that of message_post_message, but does not make assumptions
    //about the component or message details

    //using string manager directly so that strings in the message will be in the message recipients language rather than the senders
    $fullname = fullname($eventdata->userfrom);
    $eventdata->subject = get_string_manager()->get_string('unreadnewmessage', 'message', $fullname, $eventdata->userto->lang);

    //make sure the event is in the correct format
    $message = $eventdata->fullmessage;
    if ($eventdata->fullmessageformat == FORMAT_HTML) {
        $eventdata->fullmessage      = '';
        $eventdata->fullmessagehtml  = $message;
    } else {
        $eventdata->fullmessage      = $message;
        $eventdata->fullmessagehtml  = '';
    }

    //store the message unfiltered. Clean up on output.
    $eventdata->smallmessage = nl2br($message);

    //add the email tag line
    $s = new stdClass();
    $s->sitename = $SITE->shortname;
    $s->url = $CFG->wwwroot.'/message/index.php?user='.$eventdata->userto->id.'&id='.$eventdata->userfrom->id;

    $emailtagline = get_string_manager()->get_string('emailtagline', 'message', $s, $eventdata->userto->lang);
    if (!empty($eventdata->fullmessage)) {
        $eventdata->fullmessage .= "\n\n---------------------------------------------------------------------\n".$emailtagline;
    }
    if (!empty($eventdata->fullmessagehtml)) {
        $eventdata->fullmessagehtml .= "<br /><br />---------------------------------------------------------------------<br />".$emailtagline;
    }

    //send the message
    return message_send($eventdata);
}

/**
 *
 * Takes a role assignment event from Moodle and assigns class instructors
 * in curriculum admin appropriately, based on "course manager" roles
 *
 * @param  stdClass  $eventdata  The appropriate role_assignments record
 *
 */
function pm_assign_instructor_from_mdl($eventdata) {

    global $CFG, $DB;

    //make sure we have course manager roles defined
    if(empty($CFG->coursecontact)) {
        return;
    }

    //retrieve the appropriate roles
    $valid_instructor_roles = explode(',', $CFG->coursecontact);

    //make sure the assigned role is one of the ones we care about
    if(!in_array($eventdata->roleid, $valid_instructor_roles)) {
        return;
    }

    //get the id of the appropriate curriculum user
    if(!$instructorid = pm_get_crlmuserid($eventdata->userid)) {
        return;
    }

    //get the curriculum user object
    $instructor = new user($instructorid);

    //get the role assignment context
    if(!$context = $DB->get_record('context', array('id'=> $eventdata->contextid))) {
        return;
    }

    //make sure we're using a course context
    if($context->contextlevel != CONTEXT_COURSE) {
        return;
    }

    //make sure the Moodle course is not tied to other curriculum administartion classes
    if($DB->count_records(classmoodlecourse::TABLE, array('moodlecourseid'=> $context->instanceid)) != 1) {
        return true;
    }

    //make sure the course is tied to at least one class
    if(!$crlm_class = $DB->get_record(classmoodlecourse::TABLE, array('moodlecourseid'=> $context->instanceid))) {
        return;
    }

    //add user as instructor for the appropriate class

    if(!$DB->record_exists(instructor::TABLE, array('classid'=> $crlm_class->classid,
                                                    'userid'=> $instructorid))) {
        $ins_record = new instructor(array('classid' => $crlm_class->classid,
                                           'userid' => $instructorid,
                                           'assigntime' => $eventdata->timestart,
                                           'completetime' => $eventdata->timeend));
        $ins_record->save();
    }

}

/**
 *
 * Takes a role assignment event from Moodle and assigns class enrolment
 * in curriculum admin appropriately, based on "gradebook" roles
 *
 * @param  stdClass  $eventdata  The appropriate role_assignments record
 *
 */
function pm_assign_student_from_mdl($eventdata) {
    global $CFG, $DB;

    // First check if this is a standard Moodle context
    $context = context::instance_by_id($eventdata->contextid, IGNORE_MISSING);

    // If not, try checking to see if this is a custom ELIS context
    if (!$context) {
        $context = context_elis::instance_by_id($eventdata->contextid, IGNORE_MISSING);
    }

    /// We get all context assigns, so check that this is a class. If not, we're done.
    if (!$context) {
        if (in_cron()) {
            mtrace(get_string('invalidcontext'));
        } else {
            print_error('invalidcontext');
        }
        return true;
    } else if ($context->contextlevel != CONTEXT_COURSE) {
        return true;
    }

    $pmuserid = pm_get_crlmuserid($eventdata->userid);
    if (!$pmuserid) {
        return;
    }

    $gradebookroles = explode(',',$CFG->gradebookroles);
    if (!in_array($eventdata->roleid, $gradebookroles)) {
        return;
    }

    $timenow = time();

    /// synchronize enrolment to ELIS class (if applicable)
    require_once elispm::lib('data/classmoodlecourse.class.php');
    require_once elispm::lib('data/student.class.php');
    $classes = $DB->get_records(classmoodlecourse::TABLE, array('moodlecourseid'=> $context->instanceid));
    if (is_array($classes) && (count($classes) == 1)) { // only if course is associated with one class
        $class = current($classes);
        if (!$DB->get_record(student::TABLE, array('classid' => $class->classid,
                                                   'userid'  => $pmuserid))) {
            $sturec = new Object();
            $sturec->classid = $class->classid;
            $sturec->userid = $pmuserid;
            /// determine enrolment time (ELIS-2972)
            $enroltime = $timenow;
            $enrolments = $DB->get_recordset('enrol', array('courseid' => $class->moodlecourseid));
            foreach ($enrolments as $enrolment) {
                $etime = $DB->get_field('user_enrolments', 'timestart',
                                  array('enrolid' => $enrolment->id,
                                        'userid'  => $eventdata->userid));
                if (!empty($etime) && $etime < $enroltime) {
                    $enroltime = $etime;
                }
            }
            unset($enrolments);
            $sturec->enrolmenttime = $enroltime;
            $sturec->completetime = 0;
            $sturec->completestatusid = STUSTATUS_NOTCOMPLETE;
            $sturec->grade = 0;
            $sturec->credits = 0;
            $sturec->locked = 0;
            $sturec->id = $DB->insert_record(student::TABLE, $sturec);
        }
    }
}

/**
 * Triggered when a role assignment takes place.
 * This function should use the PM configured values to send messages to appropriate users when a role assignment
 * takes place. Users will be ones configured for the context, which can include the user that is assigned and users
 * assigned to configured roles for that context. The message template used should be the one configured as well.
 *
 * @param object $eventdata the role assignment record
 * @return boolean success
 *
 */
function pm_notify_role_assign_handler($eventdata){
    global $CFG, $DB, $USER;

    pm_assign_instructor_from_mdl($eventdata);
    pm_assign_student_from_mdl($eventdata);

    /// Does the user receive a notification?
    $sendtouser = !empty(elis::$config->elis_program->notify_classenrol_user) ?
                      elis::$config->elis_program->notify_classenrol_user : 0;
    $sendtorole = !empty(elis::$config->elis_program->notify_classenrol_role) ?
                      elis::$config->elis_program->notify_classenrol_role : 0;
    $sendtosupervisor = !empty(elis::$config->elis_program->notify_classenrol_supervisor) ?
                      elis::$config->elis_program->notify_classenrol_supervisor : 0;

    /// If nobody receives a notification, we're done.
    if (!$sendtouser && !$sendtorole && !$sendtosupervisor) {
        return true;
    }

    // First check if this is a standard Moodle context
    $context = context::instance_by_id($eventdata->contextid, IGNORE_MISSING);

    // If not, try checking to see if this is a custom ELIS context
    if (!$context) {
        $context = context_elis::instance_by_id($eventdata->contextid, IGNORE_MISSING);
    }

    /// We get all context assigns, so check that this is a class. If not, we're done.
//     if (!$context = context::instance_by_id($eventdata->contextid)) {
    if (!$context) {
        if (in_cron()) {
            mtrace(getstring('invalidcontext'));
        } else {
            print_error('invalidcontext');
        }

        return true;
    } else if ($context->contextlevel == CONTEXT_SYSTEM) {
        // TBD: ^above was != CONTEXT_COURSE
        return true;
    }

    /// Make sure this is a valid user.
    if (!($enroluser = $DB->get_record('user', array('id'=> $eventdata->userid)))) {
        if (in_cron()) {
           mtrace(get_string('nomoodleuser', 'elis_program'));
        } else {
           debugging(get_string('nomoodleuser', 'elis_program'));
        }
        return true;
    }

    $course = null;
    /// Get the course record from the context id.
    if ($context->contextlevel == CONTEXT_COURSE &&
        !($course = $DB->get_record('course', array('id'=> $context->instanceid)))) {
        if (in_cron()) {
           mtrace(getstring('invalidcourse'));
        } else {
           print_error('invalidcourse');
        }
        return true;
    } else {
        if (empty($course) && $context->contextlevel != CONTEXT_ELIS_CLASS) { // TBD
             //error_log("/elis/program/lib/notifications.php::pm_notify_role_assign_handler(); eventdata->contextid != CONTEXT_ELIS_CLASS");
            return true;
        }
        $name = !empty($course) ? $course->fullname
                                : $DB->get_field(pmclass::TABLE, 'idnumber',
                                          array('id' => $context->instanceid));
        if (empty($name)) {
            return true;
        }
    }

    $message = new notification();

    /// Set up the text of the message
    $text = empty(elis::$config->elis_program->notify_classenrol_message) ?
                  get_string('notifyclassenrolmessagedef', 'elis_program') :
                  elis::$config->elis_program->notify_classenrol_message;
    $search = array('%%userenrolname%%', '%%classname%%');
    $replace = array(fullname($enroluser), $name);
    $text = str_replace($search, $replace, $text);

    if ($sendtouser) {
        $message->send_notification($text, $enroluser);
    }

    $users = array();

    if ($sendtorole) {
        // Get all users with the notify_classenrol capability.
        if ($roleusers = get_users_by_capability($context, 'elis/program:notify_classenrol')) {
            $users = $users + $roleusers;
        }
        if ($roleusers = get_users_by_capability(get_system_context(), 'elis/program:notify_classenrol')) {
            $users = $users + $roleusers;
        }
    }

    if ($sendtosupervisor) {
        $pmuserid = pm_get_crlmuserid($eventdata->userid);
        // Get all users with the notify_classenrol capability.
        if ($supervisors = pm_get_users_by_capability('user', $pmuserid, 'elis/program:notify_classenrol')) {
            $users = $users + $supervisors;
        }
    }

    foreach ($users as $user) {
        //error_log("/elis/program/lib/notifications.php::pm_notify_role_assign_handler(eventdata); Sending notification to user[{$user->id}]: {$user->email}");
        $message->send_notification($text, $user, $enroluser);
    }

    /// If you don't return true, the message queue will clog and no more will be sent.
    return true;
}

/**
 *
 * Triggered when a role unassignment takes place.
 * @param $eventdata
 * @return unknown_type
 */
function pm_notify_role_unassign_handler($eventdata){
    global $CFG, $DB;

    //make sure we have course manager roles defined
    if(empty($CFG->coursecontact)) {
        return true;
    }

    //retrieve the list of role ids we want to sync to curriculum admin
    $valid_instructor_roles = explode(',', $CFG->coursecontact);

    //make sure we actually care about the current role
    if(!in_array($eventdata->roleid, $valid_instructor_roles)) {
        return true;
    }

    //prevent removal from curriculum admin if the user still has an appropriate role in Moodle
    foreach($valid_instructor_roles as $valid_instructor_role) {
        if(user_has_role_assignment($eventdata->userid, $eventdata->roleid, $eventdata->contextid)) {
            return true;
        }
    }

    //retrieve the course context
    if(!$course_context = $DB->get_record('context',
                                          array('contextlevel'=> CONTEXT_COURSE,
                                                'id'=> $eventdata->contextid))) {
        return true;
    }

    //if the course is not tied to any curriculum admin classes, then we are done
    $associated_classes = $DB->get_recordset(classmoodlecourse::TABLE, array('moodlecourseid'=> $course_context->instanceid));
    if($associated_classes->valid() !== true) {
        return true;
    }

    //retrieve the curriculum admin user's id
    if(!$crlm_userid = pm_get_crlmuserid($eventdata->userid)) {
        return true;
    }

    //clear out instructor assignments in all associated classes
    foreach($associated_classes as $associated_class) {
        if($instructor_record = $DB->get_record(instructor::TABLE, array('classid'=> $associated_class->classid,
                                                                         'userid'=>  $crlm_userid))) {
            $delete_record = new instructor($instructor_record->id);
            $delete_record->delete();
        }
    }
    unset($associated_classes);

    return true;
}

/**
 * Triggered when a track assignment takes place.
 * This function should use the CM configured values to send messages to appropriate users when a track assignment
 * takes place. Users will be ones configured for the context, which can include the user that is assigned and users
 * assigned to configured roles for that context. The message template used should be the one configured as well.
 *
 * @param object $eventdata the track assignment record
 * @return boolean success
 *
 */
function pm_notify_track_assign_handler($eventdata){
    global $CFG, $DB, $USER;

    /// Does the user receive a notification?
    $sendtouser       = isset(elis::$config->elis_program->notify_trackenrol_user) ? elis::$config->elis_program->notify_trackenrol_user : '';
    $sendtorole       = isset(elis::$config->elis_program->notify_trackenrol_role) ? elis::$config->elis_program->notify_trackenrol_role : '';
    $sendtosupervisor = isset(elis::$config->elis_program->notify_trackenrol_supervisor) ? elis::$config->elis_program->notify_trackenrol_supervisor : '';

    /// If nobody receives a notification, we're done.
    if (!$sendtouser && !$sendtorole && !$sendtosupervisor) {
        return true;
    }

    /// We get all context assigns, so check that this is a class. If not, we're done.
    $context = get_system_context();

    /// Make sure this is a valid user.
    $enroluser = new user($eventdata->userid);
    // Due to lazy loading, we need to pre-load this object
    $enroluser->load();
    if (empty($enroluser->id)) {
        if (in_cron()) {
            mtrace(getstring('nouser','elis_program'));
        } else {
            print_error('nouser','elis_program');
        }
        return true;
    }

    /// Get the track record from the track id.
    if (!($track = $DB->get_record('crlm_track', array('id'=> $eventdata->trackid)))) {
        if (in_cron()) {
            mtrace(get_string('notrack', 'elis_program'));
        } else {
            print_error('notrack', 'elis_program');
        }
        return true;
    }

    $message = new notification();

    /// Set up the text of the message
    $text = empty(elis::$config->elis_program->notify_trackenrol_message) ?
                  get_string('notifytrackenrolmessagedef', 'elis_program') :
                  elis::$config->elis_program->notify_trackenrol_message;
    $search = array('%%userenrolname%%', '%%trackname%%');
    $replace = array(fullname($enroluser->to_object()), $track->name);
    $text = str_replace($search, $replace, $text);

    if ($sendtouser) {
        $message->send_notification($text, $enroluser);
    }

    $users = array();

    if ($sendtorole) {
        /// Get all users with the notify_trackenrol capability.
        if ($roleusers = get_users_by_capability($context, 'elis/program:notify_trackenrol')) {
            $users = $users + $roleusers;
        }
    }

    if ($sendtosupervisor) {
        /// Get parent-context users.
        if ($supervisors = pm_get_users_by_capability('user', $eventdata->userid, 'elis/program:notify_trackenrol')) {
            $users = $users + $supervisors;
        }
    }

    foreach ($users as $user) {
        $message->send_notification($text, $user, $enroluser);
    }

    /// If you don't return true, the message queue will clog and no more will be sent.
    return true;
}

/*
 * Takes an instructor assignment event and propagates and appropriate
 * course role assignment back to moodle based on the configured default role
 *
 * @param  stdClass  $eventdata  The appropriate crlm_class_instructor record
 * @uses   $CFG
 * @uses   $DB
 */
function pm_notify_instructor_assigned_handler($eventdata) {
    global $CFG, $DB;

    // ELIS-4684: RLIP can pass overriding role in $eventdata
    if (empty($eventdata->roleshortname) || !($roleid = $DB->get_field('role', 'id', array('shortname' => $eventdata->roleshortname)))) {
        $roleid = elis::$config->elis_program->default_instructor_role;
    }

    // make sure we actually have an instructor role specified
    if (empty($roleid)) {
        return true;
    }

    // get our curriculum admin class
    if (!$pmclass = new pmclass($eventdata->classid)) {
        return true;
    }

    // make sure our class is tied to a Moodle course
    $moodlecourseid = $pmclass->get_moodle_course_id();
    if (empty($moodlecourseid)) {
        return true;
    }

    // retrieve the Moodle course's context
    if (!$course_context = get_context_instance(CONTEXT_COURSE, $moodlecourseid)) {
        return true;
    }

    // retrieve the appropriate Moodle user based on the event's curriculum admin user
    if (!$instructor = cm_get_moodleuser($eventdata->userid)) {
        return true;
    }

    // make sure the Moodle user does not already have a Non-Editing Teacher, Teacher or Admin role in the course
    if (has_capability('moodle/course:viewhiddenactivities', $course_context, $instructor->id)) {
        return true;
    }

    // assign the Moodle user to the instructor role
    role_assign($roleid, $instructor->id, $course_context->id);
    return true;
}

/**
 *
 * Takes an instructor unassignment and deletes the appropriate
 * Moodle course role assignments
 *
 * @param  stdClass  $eventdata  The appropriate crlm_class_instructor record
 */
function pm_notify_instructor_unassigned_handler($eventdata) {

    global $CFG, $DB;

    //make sure users in some roles are identified as course managers
    if(empty($CFG->coursecontact)) {
        return true;
    }

    //create the curriculum administration class
    try {
        if(!$pmclass = new pmclass($eventdata->classid)) {
            return true;
        }
    } catch (dml_missing_record_exception $e) {
        //record does not exists, so no need to sync
        return true;
    }

    //ensure that the class is tied to a Moodle course
    $moodlecourseid = $pmclass->get_moodle_course_id();

    if(empty($moodlecourseid)) {
        return true;
    }

    //retrieve the context for the Moodle course
    if(!$course_context = get_context_instance(CONTEXT_COURSE, $moodlecourseid)) {
        return true;
    }

    //make sure the Moodle course is not tied to other curriculum administration classes
    if($DB->count_records(classmoodlecourse::TABLE, array('moodlecourseid'=> $moodlecourseid)) != 1) {
        return true;
    }

    //retrieve the Moodle user
    if(!$instructor = cm_get_moodleuser($eventdata->userid)) {
        return true;
    }

    //go through all applicable roles to see if we can remove them from the Moodle side of things
    $roleids = explode(',', $CFG->coursecontact);

    foreach($roleids as $roleid) {
        //unassign the role if found
        if(user_has_role_assignment($instructor->id, $roleid, $course_context->id)) {
            role_unassign($roleid, $instructor->id, $course_context->id);
        }
    }
    return true;
}

