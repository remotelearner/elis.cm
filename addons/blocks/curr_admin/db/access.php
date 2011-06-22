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

//
// Context level definitions for this block.
//
// The context levels are loaded into the database table when the block is
// installed or updated.  Whenever the context level definitions are updated,
// the module version number should be bumped up.
//
// The variable name for the capability definitions array follows the format
//   $<componenttype>_<component_name>_contextlevels

require_once($CFG->dirroot.'/blocks/curr_admin/accesslib.php');

$block_curr_admin_contextlevels = array(
    'curriculum' => new context_level_elis_curriculum(),
    'track' => new context_level_elis_track(),
    'course' => new context_level_elis_course(),
    'class' => new context_level_elis_class(),
    'user' => new context_level_elis_user(),
    'cluster' => new context_level_elis_cluster(),
    );

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


$block_curr_admin_capabilities = array(

    'block/curr_admin:config' => array(

        'riskbitmask' => RISK_SPAM | RISK_PERSONAL | RISK_XSS | RISK_CONFIG | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            'admin' => CAP_ALLOW
        )
    ),

// Master control switch:

    'block/curr_admin:managecurricula' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

// Tags:

    'block/curr_admin:tag:view' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    'block/curr_admin:tag:create' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    'block/curr_admin:tag:edit' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    'block/curr_admin:tag:delete' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

// Environments:

    'block/curr_admin:environment:view' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    'block/curr_admin:environment:create' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    'block/curr_admin:environment:edit' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    'block/curr_admin:environment:delete' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

// Curricula:

    'block/curr_admin:curriculum:view' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    'block/curr_admin:curriculum:create' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    'block/curr_admin:curriculum:edit' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    'block/curr_admin:curriculum:delete' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    'block/curr_admin:curriculum:enrol' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

// Tracks:

    'block/curr_admin:track:view' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    'block/curr_admin:track:create' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    'block/curr_admin:track:edit' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    'block/curr_admin:track:delete' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    'block/curr_admin:track:enrol' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

// Clusters:

    'block/curr_admin:cluster:view' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    'block/curr_admin:cluster:create' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    'block/curr_admin:cluster:edit' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    'block/curr_admin:cluster:delete' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    'block/curr_admin:cluster:enrol' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

// Courses:

    'block/curr_admin:course:view' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    'block/curr_admin:course:create' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    'block/curr_admin:course:edit' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    'block/curr_admin:course:delete' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

// Classes:

    'block/curr_admin:class:view' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    'block/curr_admin:class:create' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    'block/curr_admin:class:edit' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    'block/curr_admin:class:delete' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    'block/curr_admin:class:enrol' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

// Users:

    'block/curr_admin:user:view' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            'admin' => CAP_ALLOW
        )
    ),

    'block/curr_admin:user:create' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            'admin' => CAP_ALLOW
        )
    ),

    'block/curr_admin:user:edit' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            'admin' => CAP_ALLOW
        )
    ),

    'block/curr_admin:user:delete' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            'admin' => CAP_ALLOW
        )
    ),

// Reports:

    'block/curr_admin:viewreports' => array(

        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
     ),

    'block/curr_admin:viewgroupreports' => array(

        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            'admin' => CAP_ALLOW
        )
     ),

    'block/curr_admin:viewownreports' => array(

        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            'user' => CAP_ALLOW,
            //'student' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
     ),

// Files

    'block/curr_admin:managefiles' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_XSS | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_PREVENT,
            //'editingteacher' => CAP_PREVENT,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
     ),

// Notifications:

    'block/curr_admin:notify_trackenrol' => array(

        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_PREVENT,
            //'editingteacher' => CAP_PREVENT,
            //'coursecreator' => CAP_PREVENT,
            'admin' => CAP_ALLOW
        )
     ),

    'block/curr_admin:notify_classenrol' => array(

        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
     ),

    'block/curr_admin:notify_classcomplete' => array(

        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
     ),

    'block/curr_admin:notify_classnotstart' => array(

        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
     ),

    'block/curr_admin:notify_classnotcomplete' => array(

        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'legacy' => array(
            //'teacher' => CAP_ALLOW,
            //'editingteacher' => CAP_ALLOW,
            //'coursecreator' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
     ),

    'block/curr_admin:notify_courserecurrence' => array(

        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_PREVENT,
            //'editingteacher' => CAP_PREVENT,
            //'coursecreator' => CAP_PREVENT,
            'admin' => CAP_ALLOW
        )
     ),

    'block/curr_admin:notify_curriculumrecurrence' => array(

        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_PREVENT,
            //'editingteacher' => CAP_PREVENT,
            //'coursecreator' => CAP_PREVENT,
            'admin' => CAP_ALLOW
        )
     ),

    'block/curr_admin:notify_curriculumcomplete' => array(

        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_PREVENT,
            //'editingteacher' => CAP_PREVENT,
            //'coursecreator' => CAP_PREVENT,
            'admin' => CAP_ALLOW
        )
     ),

    'block/curr_admin:notify_curriculumnotcomplete' => array(

        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_PREVENT,
            //'editingteacher' => CAP_PREVENT,
            //'coursecreator' => CAP_PREVENT,
            'admin' => CAP_ALLOW
        )
     ),

    'block/curr_admin:notify_curriculumdue' => array(

        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_PREVENT,
            //'editingteacher' => CAP_PREVENT,
            //'coursecreator' => CAP_PREVENT,
            'admin' => CAP_ALLOW
        )
     ),

    'block/curr_admin:notify_coursedue' => array(

        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            //'teacher' => CAP_PREVENT,
            //'editingteacher' => CAP_PREVENT,
            //'coursecreator' => CAP_PREVENT,
            'admin' => CAP_ALLOW
        )
     ),

// Enrolment via clusters:

     'block/curr_admin:curriculum:enrol_cluster_user' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            'admin' => CAP_ALLOW
        )
    ),

    'block/curr_admin:track:enrol_cluster_user' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            'admin' => CAP_ALLOW
        )
    ),

    'block/curr_admin:cluster:enrol_cluster_user' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            'admin' => CAP_ALLOW
        )
    ),

    'block/curr_admin:class:enrol_cluster_user' => array(

        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            'admin' => CAP_ALLOW
        )
    ),

// Other:

    'block/curr_admin:viewcoursecatalog' => array(

        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            'user' => CAP_ALLOW,
            //'student' => CAP_ALLOW
        )
     ),

     'block/curr_admin:overrideclasslimit' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
                'admin' => CAP_ALLOW
        )
     ),

     'block/curr_admin:cluster:role_assign_cluster_users' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
     ),

     'block/curr_admin:associate' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            'admin' => CAP_ALLOW
        )
     )
);


?>
