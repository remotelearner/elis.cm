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

/**
 * Class to put event handlers in a namespace.
 */
class enrolment_role_sync {
    /**
     * Handle a role assignment by creating an equivalent class enrolment or
     * instructor assignment (if applicable).
     */
    static function role_assigned($data) {
        //include dependencies
        require_once elispm::lib('data/student.class.php');
        require_once elispm::lib('data/instructor.class.php');

        $student_roleid = get_config('pmplugins_enrolment_role_sync', 'student_role');
        $instructor_roleid = get_config('pmplugins_enrolment_role_sync', 'instructor_role');

        if (!($context = get_context_instance_by_id($data->contextid))) {
            $context = get_context_instance($data->contextid, $data->itemid);
        }
        if (!empty($context) && $context->contextlevel == CONTEXT_ELIS_CLASS) {
            //assignment is on a PM class instance

            //need the PM userid to create an association
            $cmuserid = pm_get_crlmuserid($data->userid);

            if (!empty($student_roleid) && $data->roleid == $student_roleid) {
                // add enrolment record
                $student = new student();
                $student->userid = $cmuserid;
                $student->classid = $context->instanceid;
                $student->enrolmenttime = time();

                try {
                    $student->save();
                } catch (Exception $e) {
                    //validation failed
                }
            } else if (!empty($instructor_roleid) && $data->roleid == $instructor_roleid) {
                // add instructor record
                $instructor = new instructor();
                $instructor->userid = $cmuserid;
                $instructor->classid = $context->instanceid;
                $instructor->assigntime = time();

                try {
                    $instructor->save();
                } catch (Exception $e) {
                    //validation failed
                }
            }
        }
        return true;
    }

    /**
     * When a role is selected from the sync configuration, create class
     * enrolments for the specified role assignments.
     */
    static function student_sync_role_set() {
        global $DB;

        $roleid = get_config('pmplugins_enrolment_role_sync', 'student_role');
        if (empty($roleid)) {
            //not configured
            return;
        }

        //include dependencies
        require_once elispm::lib('data/student.class.php');

        // find all class role assignments
        $sql = "SELECT ra.id, cu.id AS userid, ctx.instanceid AS classid
                  FROM {role_assignments} ra
                  JOIN {user} mu ON ra.userid = mu.id
                  JOIN {".user::TABLE."} cu ON mu.idnumber = cu.idnumber
                  JOIN {context} ctx ON ctx.id = ra.contextid
                  LEFT JOIN {".student::TABLE."} stu
                    ON cu.id = stu.userid
                    AND ctx.instanceid = stu.classid
                 WHERE ctx.contextlevel = :contextlevel
                   AND ra.roleid = :roleid
                   AND stu.id IS NULL";
        $params = array('contextlevel' => CONTEXT_ELIS_CLASS,
                        'roleid' => $roleid);

        $studentswanted = $DB->get_recordset_sql($sql, $params);
        $studentswanted = $studentswanted ? $studentswanted : array();

        //iterate and add where needed
        foreach ($studentswanted as $student) {
            unset($student->id);
            $student = new student($student);
            $student->enrolmenttime = time();
            try {
                $student->save();
            } catch (Exception $e) {
                //validation failed
            }
        }
        return true;
    }

    /**
     * When a role is selected from the sync configuration, create class
     * enrolments for the specified role assignments.
     */
    static function instructor_sync_role_set() {
        global $DB;

        $roleid = get_config('pmplugins_enrolment_role_sync', 'instructor_role');
        if (empty($roleid)) {
            //not configured
            return;
        }

        //include dependencies
        require_once elispm::lib('data/instructor.class.php');

        // find all class role assignments
        $sql = "SELECT ra.id, cu.id AS userid, ctx.instanceid AS classid
                  FROM {role_assignments} ra
                  JOIN {user} mu ON ra.userid = mu.id
                  JOIN {".user::TABLE."} cu ON mu.idnumber = cu.idnumber
                  JOIN {context} ctx ON ctx.id = ra.contextid
                  LEFT JOIN {".instructor::TABLE."} inst
                    ON cu.id = inst.userid
                    AND ctx.instanceid = inst.classid
                 WHERE ctx.contextlevel = :contextlevel
                   AND ra.roleid = :roleid
                   AND inst.id IS NULL";
        $params = array('contextlevel' => CONTEXT_ELIS_CLASS,
                        'roleid' => $roleid);

        $instructorswanted = $DB->get_recordset_sql($sql, $params);
        $instructorswanted = $instructorswanted ? $instructorswanted : array();

        //iterate and add where needed
        foreach ($instructorswanted as $instructor) {
            unset($instructor->id);
            $instructor = new instructor($instructor);
            $instructor->assigntime = time();
            try {
                $instructor->save();
            } catch (Exception $e) {
                //validation failed
            }
        }
        return true;
    }
}

/**
 * Callback that performs the appropriate sync when the configured student
 * or instructor role changes
 *
 * @param string $name The full name of the setting that's changed
 */
function enrolment_role_sync_updatedcallback($name) {

    if ($name == 's_pmplugins_enrolment_role_sync_student_role') {
        //student role has changed
        enrolment_role_sync::student_sync_role_set();
    } else if ($name == 's_pmplugins_enrolment_role_sync_instructor_role') {
        //instructor role has changed
        enrolment_role_sync::instructor_sync_role_set();
    } else {
        //this should never happen
    }
}
