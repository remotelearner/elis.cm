<?php
/**
 * Contains definitions for notification events.
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

$handlers = array (
    'message_send' => array (
         'handlerfile'      => '/elis/program/lib/notifications.php',
         'handlerfunction'  => 'pm_notify_send_handler',
         'schedule'         => 'instant'
     ),
    'role_assigned' => array (
         'handlerfile'      => '/elis/program/lib/notifications.php',
         'handlerfunction'  => 'pm_notify_role_assign_handler',
         'schedule'         => 'instant'
     ),
    'role_unassigned' => array (
         'handlerfile'      => '/elis/program/lib/notifications.php',
         'handlerfunction'  => 'pm_notify_role_unassign_handler',
         'schedule'         => 'instant'
     ),
    'track_assigned' => array (
         'handlerfile'      => '/elis/program/lib/notifications.php',
         'handlerfunction'  => 'pm_notify_track_assign_handler',
         'schedule'         => 'instant'
     ),
    'class_completed' => array (
         'handlerfile'      => '/elis/program/lib/notifications.php',
         'handlerfunction'  => 'pm_notify_class_completed_handler',
         'schedule'         => 'instant'
     ),
    'class_notcompleted' => array (
         'handlerfile'      => '/elis/program/lib/data/student.class.php',
         'handlerfunction'  => array('student', 'class_notcompleted_handler'),
         'schedule'         => 'instant'
     ),
    'class_notstarted' => array (
         'handlerfile'      => '/elis/program/lib/data/student.class.php',
         'handlerfunction'  => array('student', 'class_notstarted_handler'),
         'schedule'         => 'instant'
     ),
    'course_recurrence' => array (
         'handlerfile'      => '/elis/program/lib/data/course.class.php',
         'handlerfunction'  => array('course', 'course_recurrence_handler'),
         'schedule'         => 'instant'
     ),
    'curriculum_completed' => array (
         'handlerfile'      => '/elis/program/lib/data/curriculumstudent.class.php',
         'handlerfunction'  => array('curriculumstudent', 'curriculum_completed_handler'),
         'schedule'         => 'instant'
     ),
    'curriculum_notcompleted' => array (
         'handlerfile'      => '/elis/program/lib/data/curriculumstudent.class.php',
         'handlerfunction'  => array('curriculumstudent', 'curriculum_notcompleted_handler'),
         'schedule'         => 'instant'
     ),
    'curriculum_recurrence' => array (
         'handlerfile'      => '/elis/program/lib/data/curriculum.class.php',
         'handlerfunction'  => array('curriculum', 'curriculum_recurrence_handler'),
         'schedule'         => 'instant'
     ),
    // triggered when a user is assigned to a cluster
    'cluster_assigned' => array (
         'handlerfile'      => '/elis/program/lib/data/userset.class.php',
         'handlerfunction'  => array('userset', 'cluster_assigned_handler'),
         'schedule'         => 'instant'
     ),
    // triggered when a user is deassigned from a cluster
    'cluster_deassigned' => array (
         'handlerfile'      => '/elis/program/lib/data/userset.class.php',
         'handlerfunction'  => array('userset', 'cluster_deassigned_handler'),
         'schedule'         => 'instant'
     ),

    'user_created' => array (
        'handlerfile'       => '/elis/program/lib/lib.php',
        'handlerfunction'   => 'pm_moodle_user_to_pm',
        'schedule'          => 'instant'
     ),

    'crlm_class_completed' => array (
        'handlerfile'       => '/elis/program/lib/lib.php',
        'handlerfunction'   => 'pm_course_complete',
        'schedule'          => 'instant'
     ),

     'crlm_instructor_assigned' => array (
        'handlerfile'       => '/elis/program/lib/notifications.php',
        'handlerfunction'   => 'pm_notify_instructor_assigned_handler',
        'schedule'          => 'instant'
     ),

     'crlm_instructor_unassigned' => array (
        'handlerfile'       => '/elis/program/lib/notifications.php',
        'handlerfunction'   => 'pm_notify_instructor_unassigned_handler',
        'schedule'          => 'instant'
     ),

     'user_deleted' => array (
         'handlerfile' => '/elis/program/lib/data/user.class.php',
         'handlerfunction' => array('user', 'user_deleted_handler'),
         'schedule' => 'instant'
     ),
);
