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

//
// Context level definitions for this block.
//
// The context levels are loaded into the database table when the block is
// installed or updated.  Whenever the context level definitions are updated,
// the module version number should be bumped up.
//
// The variable name for the capability definitions array follows the format
//   $<componenttype>_<component_name>_contextlevels

global $CFG;
require_once($CFG->dirroot.'/elis/program/accesslib.php');

/*
$contextlevels = array(
    'curriculum' => new context_level_elis_curriculum(),
    'track' => new context_level_elis_track(),
    'course' => new context_level_elis_course(),
    'class' => new context_level_elis_class(),
    'user' => new context_level_elis_user(),
    'cluster' => new context_level_elis_cluster(),
    );
*/
//
// Capability definitions for the this block.
//
// The capabilities are loaded into the database table when the block is
// installed or updated. Whenever the capability definitions are updated,
// the module version number should be bumped up.
//
// The system has four possible values for a capability:
// CAP_ALLOW, CAP_PREVENT, CAP_PROHIBIT, and inherit (not set).
//
//
// CAPABILITY NAMING CONVENTION
//
// It is important that capability names are unique. The naming convention
// for capabilities that are specific to modules and blocks is as follows:
//   [mod/block]/<component_name>:<capabilityname>
//
// component_name should be the same as the directory name of the mod or block.
//
// Core moodle capabilities are defined thus:
//    moodle/<capabilityclass>:<capabilityname>
//
// Examples: mod/forum:viewpost
//           block/recent_activity:view
//           moodle/site:deleteuser
//
// The variable name for the capability definitions array follows the format
//   $<componenttype>_<component_name>_capabilities
//
// For the core capabilities, the variable is $moodle_capabilities.


$capabilities = array(

    'elis/program:config' => array(

        'riskbitmask' => RISK_SPAM | RISK_PERSONAL | RISK_XSS | RISK_CONFIG | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:config',
        'archetypes' => array(
            'manager' => CAP_ALLOW
        )
    ),

// Master control switch, kind of (legacy):

    'elis/program:manage' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:managecurricula',
        'archetypes' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

// Programs:

    'elis/program:program_view' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:curriculum:view',
        'archetypes' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    'elis/program:program_create' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:curriculum:create',
        'archetypes' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    'elis/program:program_edit' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:curriculum:edit',
        'archetypes' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    'elis/program:program_delete' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:curriculum:delete',
        'archetypes' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    'elis/program:program_enrol' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:curriculum:enrol',
        'archetypes' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

// Tracks:

    'elis/program:track_view' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:track:view',
        'archetypes' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    'elis/program:track_create' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:track:create',
        'archetypes' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    'elis/program:track_edit' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:track:edit',
        'archetypes' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    'elis/program:track_delete' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:track:delete',
        'archetypes' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    'elis/program:track_enrol' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:track:enrol',
        'archetypes' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

// User sets:

    'elis/program:userset_view' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:cluster:view',
        'archetypes' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    'elis/program:userset_create' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:cluster:create',
        'archetypes' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    'elis/program:userset_edit' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:cluster:edit',
        'archetypes' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    'elis/program:userset_delete' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:cluster:delete',
        'archetypes' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    'elis/program:userset_enrol' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:cluster:enrol',
        'archetypes' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

// Courses:

    'elis/program:course_view' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:course:view',
        'archetypes' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    'elis/program:course_create' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:course:create',
        'archetypes' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    'elis/program:course_edit' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:course:edit',
        'archetypes' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    'elis/program:course_delete' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:course:delete',
        'archetypes' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

// Classes:

    'elis/program:class_view' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:class:view',
        'archetypes' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    'elis/program:class_create' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:class:create',
        'archetypes' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    'elis/program:class_edit' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:class:edit',
        'archetypes' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    'elis/program:class_delete' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:class:delete',
        'archetypes' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    'elis/program:class_enrol' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:class:enrol',
        'archetypes' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    'elis/program:assign_class_instructor' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

// Users:

    'elis/program:user_view' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:user:view',
        'archetypes' => array(
            'manager' => CAP_ALLOW
        )
    ),

    'elis/program:user_create' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:user:create',
        'archetypes' => array(
            'manager' => CAP_ALLOW
        )
    ),

    'elis/program:user_edit' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:user:edit',
        'archetypes' => array(
            'manager' => CAP_ALLOW
        )
    ),

    'elis/program:user_delete' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:user:delete',
        'archetypes' => array(
            'manager' => CAP_ALLOW
        )
    ),

// Reports:

    'elis/program:viewownreports' => array(

        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:viewownreports',
        'archetypes' => array(
            'user' => CAP_ALLOW,
            //'student' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
     ),

// Files

    'elis/program:managefiles' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_XSS | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:managefiles',
        'archetypes' => array(
            //'teacher' => CAP_PREVENT,
            //'editingteacher' => CAP_PREVENT,
            //'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
     ),

// Notifications:

    'elis/program:notify_trackenrol' => array(

        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:notify_trackenrol',
        'archetypes' => array(
            //'teacher' => CAP_PREVENT,
            //'editingteacher' => CAP_PREVENT,
            //'coursecreator' => CAP_PREVENT,
            'manager' => CAP_ALLOW
        )
     ),

    'elis/program:notify_classenrol' => array(

        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'clonepermissionsfrom' => 'block/curr_admin:notify_classenrol',
        'archetypes' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
     ),

    'elis/program:notify_classcomplete' => array(

        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'clonepermissionsfrom' => 'block/curr_admin:notify_classcomplete',
        'archetypes' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
     ),

    'elis/program:notify_classnotstart' => array(

        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'clonepermissionsfrom' => 'block/curr_admin:notify_classnotstart',
        'archetypes' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
     ),

    'elis/program:notify_classnotcomplete' => array(

        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'clonepermissionsfrom' => 'block/curr_admin:notify_classnotcomplete',
        'archetypes' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
     ),

    'elis/program:notify_courserecurrence' => array(

        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:notify_courserecurrence',
        'archetypes' => array(
            //'teacher' => CAP_PREVENT,
            //'editingteacher' => CAP_PREVENT,
            //'coursecreator' => CAP_PREVENT,
            'manager' => CAP_ALLOW
        )
     ),

    'elis/program:notify_programrecurrence' => array(

        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:notify_curriculumrecurrence',
        'archetypes' => array(
            //'teacher' => CAP_PREVENT,
            //'editingteacher' => CAP_PREVENT,
            //'coursecreator' => CAP_PREVENT,
            'manager' => CAP_ALLOW
        )
     ),

    'elis/program:notify_programcomplete' => array(

        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:notify_curriculumcomplete',
        'archetypes' => array(
            //'teacher' => CAP_PREVENT,
            //'editingteacher' => CAP_PREVENT,
            //'coursecreator' => CAP_PREVENT,
            'manager' => CAP_ALLOW
        )
     ),

    'elis/program:notify_programnotcomplete' => array(

        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:notify_curriculumnotcomplete',
        'archetypes' => array(
            //'teacher' => CAP_PREVENT,
            //'editingteacher' => CAP_PREVENT,
            //'coursecreator' => CAP_PREVENT,
            'manager' => CAP_ALLOW
        )
     ),

    'elis/program:notify_programdue' => array(

        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:notify_curriculumdue',
        'archetypes' => array(
            //'teacher' => CAP_PREVENT,
            //'editingteacher' => CAP_PREVENT,
            //'coursecreator' => CAP_PREVENT,
            'manager' => CAP_ALLOW
        )
     ),

    'elis/program:notify_coursedue' => array(

        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:notify_coursedue',
        'archetypes' => array(
            //'teacher' => CAP_PREVENT,
            //'editingteacher' => CAP_PREVENT,
            //'coursecreator' => CAP_PREVENT,
            'manager' => CAP_ALLOW
        )
     ),

// Enrolment via clusters:

     'elis/program:program_enrol_userset_user' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:curriculum:enrol_cluster_user',
        'archetypes' => array(
            'manager' => CAP_ALLOW
        )
    ),

    'elis/program:track_enrol_userset_user' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:track:enrol_cluster_user',
        'archetypes' => array(
            'manager' => CAP_ALLOW
        )
    ),

    'elis/program:userset_enrol_userset_user' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:cluster:enrol_cluster_user',
        'archetypes' => array(
            'manager' => CAP_ALLOW
        )
    ),

    'elis/program:class_enrol_userset_user' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:class:enrol_cluster_user',
        'archetypes' => array(
            'manager' => CAP_ALLOW
        )
    ),

    'elis/program:assign_userset_user_class_instructor' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'manager' => CAP_ALLOW
        )
    ),

// Other:

    'elis/program:viewcoursecatalog' => array(

        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:viewcoursecatalog',
        'archetypes' => array(
            'user' => CAP_ALLOW,
            //'student' => CAP_ALLOW
        )
     ),

     'elis/program:overrideclasslimit' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:overrideclasslimit',
        'archetypes' => array(
            'manager' => CAP_ALLOW
        )
     ),

     'elis/program:userset_role_assign_userset_users' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:cluster:role_assign_cluster_users',
     ),

     'elis/program:associate' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/curr_admin:associate',
        'archetypes' => array(
            'manager' => CAP_ALLOW
        )
     )
);
