<?php
/**
 * Default configuration options
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
 * @subpackage program
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

$defaults = array(
    'userdefinedtrack' => 0,
    'time_format_12h' => 0,
    'auto_assign_user_idnumber' => 1,
    'restrict_to_elis_enrolment_plugin' => 0,
    'force_unenrol_in_moodle' => 0,
    'userset_groups' => 0,
    'site_course_userset_groups' => 0,
    'userset_groupings' => 0,
    'default_instructor_role' => 0,

    // Course catalog
    'catalog_collapse_count' => 4,
    'disablecoursecatalog' => 0,

    // Certificates
    'disablecertificates' => 1,

    // notifications
    'notify_classenrol_user' => 0,
    'notify_classenrol_role' => 0,
    'notify_classenrol_supervisor' => 0,
    //'notify_classenrol_message' => get_string('notifyclassenrolmessagedef', 'elis_program'),

    'notify_classcompleted_user' => 0,
    'notify_classcompleted_role' => 0,
    'notify_classcompleted_supervisor' => 0,
    //'notify_classcompleted_message' => get_string('notifyclasscompletedmessagedef', 'elis_program'),

    'notify_classnotstarted_user' => 0,
    'notify_classnotstarted_role' => 0,
    'notify_classnotstarted_supervisor' => 0,
    //'notify_classnotstarted_message' => get_string('notifyclassnotstartedmessagedef', 'elis_program'),
    'notify_classnotstarted_days' => 10,

    'notify_classnotcompleted_user' => 0,
    'notify_classnotcompleted_role' => 0,
    'notify_classnotcompleted_supervisor' => 0,
    //'notify_classnotcompleted_message' => get_string('notifyclassnotcompletedmessagedef', 'elis_program'),
    'notify_classnotcompleted_days' => 10,

    'notify_curriculumnotcompleted_user' => 0,
    'notify_curriculumnotcompleted_role' => 0,
    'notify_curriculumnotcompleted_supervisor' => 0,
    //'notify_curriculumnotcompleted_message' => get_string('notifycurriculumnotcompletedmessagedef', 'elis_program'),
    'notify_classnotstarted_days' => 10,

    'notify_trackenrol_user' => 0,
    'notify_trackenrol_role' => 0,
    'notify_trackenrol_supervisor' => 0,
    //'notify_ttrackenrol_message' => get_string('notifytrackenrolmessagedef', 'elis_program'),

    'notify_courserecurrence_user' => 0,
    'notify_courserecurrence_role' => 0,
    'notify_courserecurrence_supervisor' => 0,
    //'notify_courserecurrence_message' => get_string('notifycourserecurrencemessagedef', 'elis_program'),
    'notify_courserecurrence_days' => 10,

    'notify_curriculumrecurrence_user' => 0,
    'notify_curriculumrecurrence_role' => 0,
    'notify_curriculumrecurrence_supervisor' => 0,
    //'notify_curriculumrecurrence_message' => get_string('notifycurriculumrecurrencemessagedef', 'elis_program'),
    'notify_curriculumrecurrence_days' => 10,

    //number of icons of each type to display at each level in the curr admin block
    'num_block_icons' => 5,
    'display_clusters_at_top_level' => 1,
    'display_curricula_at_top_level' => 0,

    //default roles
    'default_cluster_role_id' => 0,
    'default_curriculum_role_id' => 0,
    'default_course_role_id' => 0,
    'default_class_role_id' => 0,
    'default_track_role_id' => 0,

    'autocreated_unknown_is_yes' => 1,

    //legacy settings
    'legacy_show_inactive_users' => 0,
);
