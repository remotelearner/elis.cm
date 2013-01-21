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

//define('DEBUG_SURVEY', 1);

require_once(dirname(__FILE__) .'/../../config.php');
require_once($CFG->dirroot .'/blocks/enrol_survey/forms.php');
require_once($CFG->dirroot .'/blocks/enrol_survey/lib.php');
require_once($CFG->dirroot .'/elis/program/lib/setup.php');
require_once($CFG->dirroot .'/elis/program/lib/lib.php');
require_once($CFG->dirroot .'/elis/program/lib/deprecatedlib.php'); // cm_get_crlmuserid()
require_once($CFG->dirroot .'/elis/program/lib/data/user.class.php');

global $COURSE, $DB, $ME, $OUTPUT, $PAGE, $USER, $block;

$instanceid = required_param('id', PARAM_INT);
$instance = $DB->get_record('block_instances', array('id' => $instanceid));
$block = block_instance('enrol_survey', $instance);

$mymoodle = optional_param('mymoodle', 0, PARAM_INT);
$courseid = optional_param('courseid', 1, PARAM_INT);
if (!$course = $DB->get_record('course', array('id'=>$courseid))) {
    print_error('invalidcourseid');
}

//make sure we're logged in one way or another
if (fnmatch($block->instance->pagetypepattern, 'course-view-') && !empty($COURSE->id)) {
    require_course_login($COURSE->id); // TBD
} else {
    require_login();
}

if ($COURSE->id == SITEID) {
    $context = get_context_instance(CONTEXT_SYSTEM);
} else {
    $context = get_context_instance(CONTEXT_COURSE, $COURSE->id);
}

require_capability('block/enrol_survey:take', $context);

if (cm_get_crlmuserid($USER->id) === false) { // ***TBD***
    print_error(get_string('noelisuser', 'block_enrol_survey'));
}

//set the page context to either the system or course context
$PAGE->set_context($context);

$moodle_user = get_complete_user_data('id', $USER->id);
$elis_user = new user(cm_get_crlmuserid($USER->id));
$elis_user->load();

$courseobj = new stdClass();
$courseobj->courseid = $course->id;
$courseobj->mymoodle = $mymoodle;
$survey_form = new survey_form($CFG->wwwroot .'/blocks/enrol_survey/survey.php?id='. $instanceid, $courseobj);

if ($survey_form->is_cancelled()) {
  if ($mymoodle == 1) {
    redirect($CFG->wwwroot .'/my');
  } else {
    redirect($CFG->wwwroot .'/course/view.php?id=' . $course->id);
  }
} else if ($formdata = $survey_form->get_data()) {
    $customfields = get_customfields();
    $profilefields = get_profilefields();

    $data = get_object_vars($formdata);

    if (defined('DEBUG_SURVEY')) {
        ob_start();
        var_dump($data);
        //echo "\n  elis_user: ";
        //var_dump($eu_obj);
        //echo "\n  moodle_user: ";
        //var_dump($moodle_user);
        $tmp = ob_get_contents();
        ob_end_clean();
        error_log("/blocks/enrol_survey/survey.php: data = {$tmp}");
    }

    // $eu_obj = $elis_user->to_object();
    foreach ($data as $key => $fd) {
        if (isset($fd) && $fd !== '') { // *MUST* handle checkboxes = '0'
            if (in_array($key, $profilefields)) {
                // NOTE: property_exists($eu_obj, $key) doesn't work if existing value is NULL
                try {
                    $elis_user->__set($key, $fd);
                } catch (Exception $e) {
                    // ignore invalid property exception!
                    error_log("/blocks/enrol_survey/survey.php: Warning - invalid property of crlm_user: '{$key}'");
                }
                if ($key == 'language') { // special case $USER->lang
                    $key = 'lang';
                }
                if (property_exists($moodle_user, $key)) {
                    $moodle_user->{$key} = $fd;
                    $USER->{$key} = $fd;
                }
            }
            if (in_array($key, $customfields)) {
                $id = $DB->get_field('user_info_field', 'id', array('shortname' => $key));

                if ($DB->record_exists('user_info_data', array('userid' => $USER->id, 'fieldid' => $id))) {
                    $DB->set_field('user_info_data', 'data', $fd, array('userid' => $USER->id, 'fieldid' => $id));
                } else {
                    $dataobj = new object();
                    $dataobj->userid = $USER->id;
                    $dataobj->fieldid = $id;
                    $dataobj->data = $fd;
                    $DB->insert_record('user_info_data', $dataobj);
                }
            }
        } else {
            $incomplete = true;
        }
    }

    $DB->update_record('user', $moodle_user); // *MUST* be called before save()
    $elis_user->save();

    $usernew = $DB->get_record('user', array('id' => $moodle_user->id));
    if (!empty($usernew)) {
        events_trigger('user_updated', $usernew);
    }

    if (!is_survey_taken($USER->id, $instanceid) && empty($incomplete)) {
        $dataobject = new object();
        $dataobject->blockinstanceid = $instanceid;
        $dataobject->userid = $USER->id;
        $DB->insert_record('block_enrol_survey_taken', $dataobject);
    }

    if (!empty($formdata->save_exit)) {
      if ($mymoodle == 1) {
        redirect($CFG->wwwroot .'/my');
      } else {
        redirect($CFG->wwwroot .'/course/view.php?id=' . $course->id);
      }
    }
}

$toform = array_merge((array)$moodle_user, (array)$elis_user->to_object());

$customdata = $DB->get_records('user_info_data', array('userid' => $USER->id));
if (!empty($customdata)) {
    foreach ($customdata as $cd) {
        $customfields = $DB->get_record('user_info_field', array('id' => $cd->fieldid));
        $toform[$customfields->shortname] = $cd->data;
    }
}

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

if (isset($block->config->title)) {
    $pagetitle = $block->config->title;
} else {
    $pagetitle = get_string('takepage', 'block_enrol_survey');
}

$PAGE->navbar->add($pagetitle);
$PAGE->blocks->add_regions(array('side-pre', 'side-post')); // TBV ?

echo $OUTPUT->header();
echo $OUTPUT->heading($pagetitle);
$survey_form->set_data($toform);
$survey_form->display();
echo $OUTPUT->footer();

