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
 * This file runs the Results Engine Manual processing.  It is meant to be run via AJAX from a
 * popup spawned by the results engine page for courses or classes.  It returns a simple success or
 * failure message that will be processed and displayed on the popup.
 */
require_once(dirname(__FILE__) .'/lib/setup.php');
require_once(dirname(__FILE__) .'/lib/resultsengine.php');

$id = required_param('id', PARAM_INT);

if (! isloggedin()) {
    print_string('loggedinnot');
    exit;
}

$context = get_context_instance_by_id($id);

if ((! $context) || (($context->contextlevel != CONTEXT_ELIS_CLASS) && ($context->contextlevel != CONTEXT_ELIS_COURSE))) {
    print_string('results_unknown_classcourse', RESULTS_ENGINE_LANG_FILE);
    exit;
}

// Set page context.  Needed for custom field synchronization with Moodle users.
$PAGE->set_context($context);

$capability = 'elis/program:course_edit';

if ($context->contextlevel == $classlevel) {
    $capability = 'elis/program:class_edit';
}

if (! has_capability($capability, $context)) {
    print_string('not_permitted', RESULTS_ENGINE_LANG_FILE);
    exit;
}

if (results_engine_manual($context)) {
    print_string('results_manual_success', RESULTS_ENGINE_LANG_FILE);
} else {
    print_string('results_manual_failure', RESULTS_ENGINE_LANG_FILE);
}