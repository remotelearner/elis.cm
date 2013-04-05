<?php
/**
 * Contains definitions for notification events.
 *
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
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
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

$handlers = array(
    'cluster_assigned' => array (
        'handlerfile'     => '/elis/program/plugins/userset_groups/lib.php',
        'handlerfunction' => 'userset_groups_userset_assigned_handler',
        'schedule'        => 'instant'
    ),
    'pm_classinstance_associated' => array (
        'handlerfile'     => '/elis/program/plugins/userset_groups/lib.php',
        'handlerfunction' => 'userset_groups_pm_classinstance_associated_handler',
        'schedule'        => 'instant'
    ),
    /*'pm_program_coursedescription_associated' => array (
        'handlerfile'     => '/elis/program/plugins/userset_groups/lib.php',
        'handlerfunction' => 'userset_groups_pm_program_coursedescription_associated_handler',
        'schedule'        => 'instant'
    ),
    'pm_userset_program_associated' => array (
        'handlerfile'     => '/elis/program/plugins/userset_groups/lib.php',
        'handlerfunction' => 'userset_groups_pm_userset_program_associated_handler',
        'schedule'        => 'instant'
    ),*/
    'pm_track_class_associated' => array (
        'handlerfile'     => '/elis/program/plugins/userset_groups/lib.php',
        'handlerfunction' => 'userset_groups_pm_track_class_associated_handler',
        'schedule'        => 'instant'
    ),
    'pm_userset_track_associated' => array (
        'handlerfile'     => '/elis/program/plugins/userset_groups/lib.php',
        'handlerfunction' => 'userset_groups_pm_userset_track_associated_handler',
        'schedule'        => 'instant'
    ),
    'pm_userset_updated' => array (
        'handlerfile'     => '/elis/program/plugins/userset_groups/lib.php',
        'handlerfunction' => 'userset_groups_pm_userset_updated_handler',
        'schedule'        => 'instant'
    ),
     'pm_userset_created'  => array (
         'handlerfile'     => '/elis/program/plugins/userset_groups/lib.php',
         'handlerfunction' => 'userset_groups_pm_userset_created_handler',
         'schedule'        => 'instant'
     ),
     'role_assigned'       => array (
         'handlerfile'     => '/elis/program/plugins/userset_groups/lib.php',
         'handlerfunction' => 'userset_groups_role_assigned_handler',
         'schedule'        => 'instant'
     ),
);
