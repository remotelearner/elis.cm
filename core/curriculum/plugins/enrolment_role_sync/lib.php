<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2010 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2008-2010 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once CURMAN_DIRLOCATION.'/lib/lib.php';

/**
 * Class to put event handlers in a namespace.
 */
class enrolment_role_sync {
    /**
     * Handle a role assignment by creating an equivalent class enrolment or
     * instructor assignment (if applicable).
     */
    static function role_assigned($data) {
        require_once CURMAN_DIRLOCATION.'/lib/student.class.php';
        require_once CURMAN_DIRLOCATION.'/lib/instructor.class.php';
        global $CURMAN;
        $context = get_context_instance_by_id($data->contextid);
        if ($context->contextlevel == context_level_base::get_custom_context_level('class', 'block_curr_admin')) {
            $cmuserid = cm_get_crlmuserid($data->userid);
            if ($data->roleid == $CURMAN->config->enrolment_role_sync_student_role) {
                // add enrolment record
                $student = new student();
                $student->userid = $cmuserid;
                $student->classid = $context->instanceid;
                // NOTE: student::add checks if the student is already
                // enrolled, so we don't have to check here
                $student->add();
            }
            if ($data->roleid == $CURMAN->config->enrolment_role_sync_instructor_role) {
                // add instructor record
                if (!instructor::user_is_instructor_of_class($cmuserid, $context->instanceid)) {
                    $instructor = new instructor();
                    $instructor->userid = $cmuserid;
                    $instructor->classid = $context->instanceid;
                    $instructor->add();
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
        require_once CURMAN_DIRLOCATION.'/lib/student.class.php';
        global $CURMAN;
        $contextlevel = context_level_base::get_custom_context_level('class', 'block_curr_admin');
        // find all class role assignments
        $sql = "SELECT ra.id, cu.id AS userid, ctx.instanceid AS classid
                  FROM {$CURMAN->db->prefix_table('role_assignments')} ra
                  JOIN {$CURMAN->db->prefix_table('user')} mu ON ra.userid = mu.id
                  JOIN {$CURMAN->db->prefix_table('crlm_user')} cu ON mu.idnumber = cu.idnumber
                  JOIN {$CURMAN->db->prefix_table('context')} ctx ON ctx.id = ra.contextid
                 WHERE ctx.contextlevel = $contextlevel
                   AND ra.roleid = {$CURMAN->config->enrolment_role_sync_student_role}";
        $studentswanted = $CURMAN->db->get_records_sql($sql);
        $studentswanted = $studentswanted ? $studentswanted : array();
        foreach ($studentswanted as $student) {
            unset($student->id);
            $student = new student($student);
            $student->add();
        }
        return true;
    }

    /**
     * When a role is selected from the sync configuration, create class
     * enrolments for the specified role assignments.
     */
    static function instructor_sync_role_set() {
        require_once CURMAN_DIRLOCATION.'/lib/instructor.class.php';
        global $CURMAN;
        $contextlevel = context_level_base::get_custom_context_level('class', 'block_curr_admin');
        // find all class role assignments
        $sql = "SELECT ra.id, cu.id AS userid, ctx.instanceid AS classid
                  FROM {$CURMAN->db->prefix_table('role_assignments')} ra
                  JOIN {$CURMAN->db->prefix_table('user')} mu ON ra.userid = mu.id
                  JOIN {$CURMAN->db->prefix_table('crlm_user')} cu ON mu.idnumber = cu.idnumber
                  JOIN {$CURMAN->db->prefix_table('context')} ctx ON ctx.id = ra.contextid
                 WHERE ctx.contextlevel = $contextlevel
                   AND ra.roleid = {$CURMAN->config->enrolment_role_sync_instructor_role}";
        $instructorswanted = $CURMAN->db->get_records_sql($sql);
        $instructorswanted = $instructorswanted ? $instructorswanted : array();
        foreach ($instructorswanted as $instructor) {
            unset($instructor->id);
            if (!instructor::user_is_instructor_of_class($instructor->userid, $instructor->classid)) {
                $instructor = new instructor($instructor);
                $instructor->add();
            }
        }
        return true;
    }
}

?>
