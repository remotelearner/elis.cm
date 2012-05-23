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
 * @subpackage blocks-course_request
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2011 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

global $DB;

$options = array('0' => get_string('none'));
$roles = $DB->get_records('role', null, 'sortorder', 'id, name');
$roles = $roles ? $roles : array();
foreach ($roles as $role) {
    $options[$role->id] = $role->name;
}

$settings->add(new admin_setting_configselect('block_course_request_course_role', get_string('course_role', 'block_course_request'),
                   get_string('configcourse_role', 'block_course_request'), '0', $options));

$settings->add(new admin_setting_configselect('block_course_request_class_role', get_string('class_role', 'block_course_request'),
                   get_string('configclass_role', 'block_course_request'), '0', $options));

//checkbox for enabling the creation of Moodle courses from templates
$settings->add(new admin_setting_configcheckbox('block_course_request_use_template_by_default', get_string('use_template_by_default', 'block_course_request'),
                   get_string('configuse_template_by_default', 'block_course_request'), 0));

$settings->add(new admin_setting_configcheckbox('block_course_request_use_course_fields', get_string('use_course_fields', 'block_course_request'),
               get_string('configuse_course_fields', 'block_course_request'), 0));

$settings->add(new admin_setting_configcheckbox('block_course_request_use_class_fields', get_string('use_class_fields', 'block_course_request'),
               get_string('configuse_class_fields', 'block_course_request'), 1));

$settings->add(new admin_setting_configcheckbox('block_course_request_create_class_with_course', get_string('create_class_with_course', 'block_course_request'),
               get_string('configcreate_class_with_course', 'block_course_request'), 1));

