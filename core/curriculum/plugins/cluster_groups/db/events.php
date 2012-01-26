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

$handlers = array(
    'cluster_assigned' => array (
        'handlerfile'     => '/curriculum/plugins/cluster_groups/lib.php',
        'handlerfunction' => 'cluster_groups_cluster_assigned_handler',
        'schedule'        => 'instant'
    ),
    'crlm_class_associated' => array (
        'handlerfile'     => '/curriculum/plugins/cluster_groups/lib.php',
        'handlerfunction' => 'cluster_groups_crlm_class_associated_handler',
        'schedule'        => 'instant'
    ),
    'crlm_curriculum_course_associated' => array (
        'handlerfile'     => '/curriculum/plugins/cluster_groups/lib.php',
        'handlerfunction' => 'cluster_groups_crlm_curriculum_course_associated_handler',
        'schedule'        => 'instant'
    ),
    'crlm_cluster_curriculum_associated' => array (
        'handlerfile'     => '/curriculum/plugins/cluster_groups/lib.php',
        'handlerfunction' => 'cluster_groups_crlm_cluster_curriculum_associated_handler',
        'schedule'        => 'instant'
    ),
    'crlm_cluster_updated' => array (
        'handlerfile'      => '/curriculum/plugins/cluster_groups/lib.php',
        'handlerfunction'  => 'cluster_groups_crlm_cluster_updated_handler',
        'schedule'         => 'instant'
    ),
    'crlm_cluster_groups_enabled' => array (
        'handlerfile'             => '/curriculum/plugins/cluster_groups/lib.php',
        'handlerfunction'         => 'cluster_groups_crlm_cluster_groups_enabled_handler',
        'schedule'                => 'instant'
     ),
     'crlm_site_course_cluster_groups_enabled' => array (
         'handlerfile'                         => '/curriculum/plugins/cluster_groups/lib.php',
         'handlerfunction'                     => 'cluster_groups_crlm_site_course_cluster_groups_enabled_handler',
         'schedule'                            => 'instant'
     ),
     'crlm_cluster_created' => array (
         'handlerfile'      => '/curriculum/plugins/cluster_groups/lib.php',
         'handlerfunction'  => 'cluster_groups_crlm_cluster_created_handler',
         'schedule'         => 'instant'
     ),
     'role_assigned'       => array (
         'handlerfile'     => '/curriculum/plugins/cluster_groups/lib.php',
         'handlerfunction' => 'cluster_groups_role_assigned_handler',
         'schedule'        => 'instant'
     ),
     'crlm_cluster_groupings_enabled' => array(
         'handlerfile'     => '/curriculum/plugins/cluster_groups/lib.php',
         'handlerfunction' => 'cluster_groups_crlm_cluster_groupings_enabled',
         'schedule'        => 'instant'
     ),
);

?>
