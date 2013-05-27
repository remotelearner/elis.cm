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

$capabilities = array(
    'block/course_request:request' => array(
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy'       => array(
            'manager'       => CAP_ALLOW,
            'coursecreator' => CAP_ALLOW,
            'teacher'       => CAP_ALLOW
        )
    ),

    'block/course_request:config' => array(
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy'       => array(
            'manager'       => CAP_ALLOW,
            'coursecreator' => CAP_ALLOW,
        )
    ),

    'block/course_request:approve' => array(
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy'       => array(
            'manager'       => CAP_ALLOW,
            'coursecreator' => CAP_ALLOW,
        )
    ),

    'block/course_request:addinstance' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => array(
            'manager' => CAP_ALLOW
        ),
    ),
);

