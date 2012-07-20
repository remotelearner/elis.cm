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
 * @subpackage enrol_survey
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__) .'/../../config.php');
require_once($CFG->dirroot .'/blocks/enrol_survey/forms.php');
require_once($CFG->dirroot .'/blocks/enrol_survey/lib.php');

global $COURSE, $DB, $ME, $OUTPUT, $PAGE, $block;

$mymoodle = optional_param('mymoodle', 0, PARAM_INT);
$courseid = optional_param('courseid', 1, PARAM_INT);
if (!$course = $DB->get_record('course', array('id'=>$courseid))) {
  print_error('invalidcourseid');
}

$instanceid        = required_param('id', PARAM_INT);
$add_profilefield  = optional_param('add_profilefield', '', PARAM_CLEAN);
$retake            = optional_param('retake', '', PARAM_CLEAN);
$update            = optional_param('update', '', PARAM_CLEAN);
$exit              = optional_param('exit', '', PARAM_CLEAN);
$force_user        = optional_param('force_user', false, PARAM_BOOL);
$custom_names      = optional_param('custom_name', 0, PARAM_CLEAN);
$profile_fields    = optional_param('profile_field', 0, PARAM_CLEAN);
$delete            = optional_param('delete', 0, PARAM_CLEAN);

$instance = $DB->get_record('block_instances', array('id' => $instanceid));
$block = block_instance('enrol_survey', $instance);

if (fnmatch($block->instance->pagetypepattern, 'course-view-') && !empty($COURSE->id)) {
    require_course_login($COURSE->id); // TBD
}

if ($COURSE->id == SITEID) {
    $context = get_context_instance(CONTEXT_SYSTEM);
} else {
    $context = get_context_instance(CONTEXT_COURSE, $COURSE->id);
}

if (isset($block->config) && is_object($block->config)) {
    $data = get_object_vars($block->config);
} else {
    $data = array();
}

$data['force_user'] = $force_user;
unset($data['none']);

if (!empty($profile_fields) && count($profile_fields) === count($custom_names)) {
    $tempdata = array_combine($profile_fields, $custom_names);
    $data = array_merge($data, $tempdata);
}

$data = (object)$data;

$dbupdate = false;
if (!empty($delete)) {
    $keys = array_keys($delete);
    foreach($keys as $todel) {
        unset($data->$todel);
    }
    $dbupdate = true;
} else if (!empty($update) && !empty($data)) {
    $dbupdate = true;
} else if (!empty($add_profilefield)) {
    $data->none = '';
    $dbupdate = true;
} else if (!empty($exit)) {
    $dbupdate = true;
} else if (!empty($retake)) {
    $DB->delete_records('block_enrol_survey_taken', array('blockinstanceid' => $instanceid));
}

if ($dbupdate) {
    $block->instance_config_save($data);

    // NOTE: instance_config_save() does NOT update $block->config only DBtable!
    // therefore we MUST reload the block data!
    $instance = $DB->get_record('block_instances', array('id' => $instanceid));
    $block = block_instance('enrol_survey', $instance);
}

if (!empty($exit)) {
    if ($mymoodle == 1) {
        redirect($CFG->wwwroot .'/my');
    } else {
        redirect($CFG->wwwroot .'/course/view.php?id=' . $course->id);
    }
}

require_capability('block/enrol_survey:edit', $context);

$courseobj = new stdClass();
$courseobj->courseid = $course->id;
$courseobj->mymoodle = $mymoodle;
$edit_form = new edit_survey_form($CFG->wwwroot .'/blocks/enrol_survey/edit_survey.php?id='. $instanceid, $courseobj);

$blockname = get_string('blockname', 'block_enrol_survey');
$PAGE->set_pagelayout('standard'); // TBV
$PAGE->set_pagetype('elis'); // TBV
$PAGE->set_context($context);
$PAGE->set_url($ME);
$PAGE->set_title($blockname);
$PAGE->set_heading($blockname);
$PAGE->set_cacheable(true);
$PAGE->set_button('&nbsp;');

// Make sure that the either the My Moodle or course shortname link is in the navbar if within that context
if ($mymoodle) {
    $PAGE->navbar->add(get_string('myhome'), new moodle_url('/my'));
} else if ($course->id != SITEID) {
    $PAGE->navbar->add($course->shortname, new moodle_url('/course/view.php?id='.$course->id));
}

$PAGE->navbar->add(get_string('editpage', 'block_enrol_survey'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('surveysettings', 'block_enrol_survey'));
$edit_form->display();
echo $OUTPUT->footer();

