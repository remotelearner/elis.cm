<?php
/**
 * Contains definitions for notification events.
 *
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

$handlers = array (
    'message_send' => array (
         'handlerfile'      => '/curriculum/lib/notifications.php',
         'handlerfunction'  => 'cm_notify_send_handler',
         'schedule'         => 'instant'
     ),
    'role_assigned' => array (
         'handlerfile'      => '/curriculum/lib/notifications.php',
         'handlerfunction'  => 'cm_notify_role_assign_handler',
         'schedule'         => 'instant'
     ),
    'role_unassigned' => array (
         'handlerfile'      => '/curriculum/lib/notifications.php',
         'handlerfunction'  => 'cm_notify_role_unassign_handler',
         'schedule'         => 'instant'
     ),
    'track_assigned' => array (
         'handlerfile'      => '/curriculum/lib/notifications.php',
         'handlerfunction'  => 'cm_notify_track_assign_handler',
         'schedule'         => 'instant'
     ),
    'class_completed' => array (
         'handlerfile'      => '/curriculum/lib/notifications.php',
         'handlerfunction'  => 'cm_notify_class_completed_handler',
         'schedule'         => 'instant'
     ),
    'class_notcompleted' => array (
         'handlerfile'      => '/curriculum/lib/student.class.php',
         'handlerfunction'  => array('student', 'class_notcompleted_handler'),
         'schedule'         => 'instant'
     ),
    'class_notstarted' => array (
         'handlerfile'      => '/curriculum/lib/student.class.php',
         'handlerfunction'  => array('student', 'class_notstarted_handler'),
         'schedule'         => 'instant'
     ),
    'course_recurrence' => array (
         'handlerfile'      => '/curriculum/lib/course.class.php',
         'handlerfunction'  => array('course', 'course_recurrence_handler'),
         'schedule'         => 'instant'
     ),
    'curriculum_completed' => array (
         'handlerfile'      => '/curriculum/lib/curriculumstudent.class.php',
         'handlerfunction'  => array('curriculumstudent', 'curriculum_completed_handler'),
         'schedule'         => 'instant'
     ),
    'curriculum_notcompleted' => array (
         'handlerfile'      => '/curriculum/lib/curriculumstudent.class.php',
         'handlerfunction'  => array('curriculumstudent', 'curriculum_notcompleted_handler'),
         'schedule'         => 'instant'
     ),
    'curriculum_recurrence' => array (
         'handlerfile'      => '/curriculum/lib/curriculum.class.php',
         'handlerfunction'  => array('curriculum', 'curriculum_recurrence_handler'),
         'schedule'         => 'instant'
     ),
    // triggered when a user is assigned to a cluster
    'cluster_assigned' => array (
         'handlerfile'      => '/curriculum/lib/cluster.class.php',
         'handlerfunction'  => array('cluster', 'cluster_assigned_handler'),
         'schedule'         => 'instant'
     ),
    // triggered when a user is deassigned from a cluster
    'cluster_deassigned' => array (
         'handlerfile'      => '/curriculum/lib/cluster.class.php',
         'handlerfunction'  => array('cluster', 'cluster_deassigned_handler'),
         'schedule'         => 'instant'
     ),

    'user_created' => array (
        'handlerfile'       => '/curriculum/lib/lib.php',
        'handlerfunction'   => 'cm_moodle_user_to_cm',
        'schedule'          => 'instant'
     ),

    'crlm_class_completed' => array (
        'handlerfile'       => '/curriculum/lib/lib.php',
        'handlerfunction'   => 'cm_course_complete',
        'schedule'          => 'instant'
     ),

     'crlm_instructor_assigned' => array (
        'handlerfile'       => '/curriculum/lib/notifications.php',
        'handlerfunction'   => 'cm_notify_instructor_assigned_handler',
        'schedule'          => 'instant'
     ),

     'crlm_instructor_unassigned' => array (
        'handlerfile'       => '/curriculum/lib/notifications.php',
        'handlerfunction'   => 'cm_notify_instructor_unassigned_handler',
        'schedule'          => 'instant'
     ),
);
?>
