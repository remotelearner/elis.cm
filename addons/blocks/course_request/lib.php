<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @subpackage blocks-course_request
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

// Component parameter for role_assign() calls in requestpage and approvepage
// Component for ELIS Course Descriptions (CD)
define('ECR_CD_ROLE_COMPONENT', ''); // TBD: 'enrol_elis'
// Component for ELIS Class Instances (CI)
define('ECR_CI_ROLE_COMPONENT', ''); // TBD: 'enrol_elis'
// Component for Moodle Course (MC)
define('ECR_MC_ROLE_COMPONENT', 'enrol_elis'); // TBD

/**
 * Determines whether the current user is allowed to make course / class
 * requests: they must have the necessary capability at the site level,
 * in some course, or some curriculum involved in a course-curriculum association
 *
 * @uses    $CFG
 * @uses    $DB
 * @uses    $USER
 * @return  boolean  True if allowed, otherwise false
 */
function block_course_request_can_do_request() {
    global $CFG, $DB, $USER;

    require_once($CFG->dirroot .'/elis/program/lib/data/course.class.php');
 
    $context = get_context_instance(CONTEXT_SYSTEM);

    //handle system context in case no courses are set up
    if (has_capability('block/course_request:request', $context)) {
        return true;
    }

    if ($course_contexts = get_contexts_by_capability_for_user('course', 'block/course_request:request', $USER->id)) {
        $course_filter = $course_contexts->get_filter('id', 'course');
        $filter_sql = $course_filter->get_sql(false, 'course'); // *TBV*
        $params = array();
        $where = '';
        if (isset($filter_sql['where'])) {
            $where = 'WHERE '. $filter_sql['where'];
            $params = $filter_sql['where_parameters'];
        }

        //this will handle both the course and curriculum cases
        $course_sql = 'SELECT * FROM {'. course::TABLE . "} course $where";
                   
        if ($DB->record_exists_sql($course_sql, $params)) {
            return true;
        }
    }
    //access denied
    return false;
}

/**
 * Determines an approval message to send to the course / class requester
 *
 * @param  object  $request     The course / class request, with any necessary additional
 *                              information about the course / class requested
 * @param  string  $statusnote  A descriptive note added by the administrator
 * @uses   $CFG
 */
function block_course_request_get_approval_message($request, $statusnote = null) {
    global $CFG;

    $notice = '';

    if (empty($request->courseid) && empty($CFG->block_course_request_create_class_with_course)) {
        //just course
        $a = new stdClass;
        $a->link = $CFG->wwwroot .'/elis/program/index.php?action=view&id='.$request->newcourseid.'&s=crs';;
        $a->coursename = $request->title;
        $notice = get_string('notification_courserequestapproved', 'block_course_request', $a);
    } else if (!empty($request->courseid)) {
        //just class
        $a = new stdClass;
        $a->link  = $CFG->wwwroot .'/elis/program/index.php?action=view&id='.$request->classid.'&s=cls';
        $a->coursename = $request->title;
        $a->classidnumber = $request->classidnumber;
        $notice = get_string('notification_classrequestapproved', 'block_course_request', $a);
    } else {
        //course and class
        $a = new stdClass;
        $a->link  = $CFG->wwwroot .'/elis/program/index.php?action=view&id='.$request->classid.'&s=cls';
        $a->coursename = $request->title;
        $a->classidnumber = $request->classidnumber;
        $notice = get_string('notification_courseandclassrequestapproved', 'block_course_request', $a);
    }

    if (!empty($statusnote)) {
        $a = new stdClass;
        $a->statusnote = $statusnote;
        $notice .= get_string('notification_statusnote', 'block_course_request', $a);
    }

    return $notice;
}

/**
 * Determines a denial message to send to the course / class requester
 *
 * @param  object  $request     The course / class request, with any necessary additional
 *                              information about the course / class requested
 * @param  string  $statusnote  A descriptive note added by the administrator
 * @uses   $CFG
 */
function block_course_request_get_denial_message($request, $statusnote = null) {
    global $CFG;

    $notice = '';
    $a = new stdClass;
    $a->link = $CFG->wwwroot .'/elis/program/index.php?action=requests&s=crp';
    $a->coursename = $request->title; 

    if (empty($request->courseid) && empty($CFG->block_course_request_create_class_with_course)) {
        //just course
        $notice = get_string('notification_courserequestdenied', 'block_course_request', $a);
    } else if (!empty($request->courseid)) {
        //just class
        $notice = get_string('notification_classrequestdenied', 'block_course_request', $a);
    } else {
        //course and class
        $notice = get_string('notification_courseandclassrequestdenied', 'block_course_request', $a);
    }

    if (!empty($statusnote)) {
        $a = new stdClass;
        $a->statusnote = $statusnote;
        $notice .= get_string('notification_statusnote', 'block_course_request', $a);
    }

    return $notice;
}

