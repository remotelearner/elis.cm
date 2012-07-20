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
 * @copyright  (C) 2008-2010 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once dirname(__FILE__) . '/lib/setup.php';
require_once elispm::lib('resultsengine.php');

$id = required_param('id', PARAM_INT);

require_login();

$context = get_context_instance_by_id($id);

if ((! $context) || (($context->contextlevel != CONTEXT_ELIS_CLASS) && ($context->contextlevel != CONTEXT_ELIS_COURSE))) {
    print_string('results_unknown_classcourse', RESULTS_ENGINE_LANG_FILE);
    exit;
}

$capability = 'elis/program:course_edit';
$table      = 'crlm_course';
$fields     = 'id, name as idnumber';

if ($context->contextlevel == CONTEXT_ELIS_CLASS) {
    $capability = 'elis/program:class_edit';
    $table      = 'crlm_class';
    $fields     = 'id, idnumber';
}

if (! has_capability($capability, $context)) {
    print_string('results_not_permitted', RESULTS_ENGINE_LANG_FILE);
    exit;
}

$object = $DB->get_record($table, array('id' => $context->instanceid), $fields);
$source = $CFG->wwwroot .'/elis/program/resultsmanualprocess.php';

$PAGE->requires->string_for_js('results_done', RESULTS_ENGINE_LANG_FILE);
$PAGE->requires->yui_module('yui-min', 'M.results_engine.process', array($source, $id));
$PAGE->requires->js('/elis/program/js/results_engine/manual.js');
$PAGE->set_context($context);
$PAGE->set_url($_SERVER['PHP_SELF']);
$PAGE->set_pagelayout('popup');

print($OUTPUT->header());
print_string('results_processing_manual', RESULTS_ENGINE_LANG_FILE, $object);
print('<div id="results"></div>');
print($OUTPUT->footer());