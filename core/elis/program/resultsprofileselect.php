<?php
/**
 * General class for displaying pages in the curriculum management system.
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
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 */

/**
 * This file is meant to return a snippet of html code that can be used via AJAX to display
 * a value select box for the currently selected user profile field on the user profile field
 * section of the results engine setup page.
 */

require_once(dirname(__FILE__) .'/lib/setup.php');
require_once(dirname(__FILE__) .'/lib/resultsengine.php');

$id   = required_param('id', PARAM_INT);
$name = required_param('name', PARAM_ALPHANUMEXT);

if (! isloggedin()) {
    print_string('loggedinnot');
    exit;
}

$criteria = array('fieldid' => $id, 'plugin' => 'manual');
$params = $DB->get_field(field_owner::TABLE, 'params', $criteria);
$config = unserialize($params);

if ($config['control'] == 'menu') {
    $choices = explode("\n", $config['options']);
    $options = array_combine($choices, $choices);
    asort($options);
    $field = html_writer::select($options, $name);
} else {
    $params = array('name' => $name);
    $field = html_writer::empty_tag('input', $params);
}

print('<div class="fitem"><div class="fitemtitle">&nbsp;</div><div class="felement fselect">'
    . $field .'</div></div>');
