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

require_once('../../../lib/setup.php');

require_once(elis::plugin_file('usersetenrol_moodle_profile', 'lib.php'));

$id = required_param('field', PARAM_INT);
$elementName = required_param('elementName', PARAM_TEXT);
$value = optional_param('value', null, PARAM_TEXT);

require_login(0, false);

$prof_field = new stdClass;
$prof_field->fieldid = $id;

//output the innerHTML of the surrounding DIV tag
echo get_moodle_profile_field_options($prof_field, $elementName, $value);
