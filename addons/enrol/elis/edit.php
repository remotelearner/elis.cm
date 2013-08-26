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

// based on /enrol/manual/edit.php

require('../../elis/program/lib/setup.php');
require(elis::plugin_file('enrol_elis', 'edit_form.php'));

$courseid = required_param('courseid', PARAM_INT);

$course = $DB->get_record('course', array('id'=>$courseid), '*', MUST_EXIST);
$context = get_context_instance(CONTEXT_COURSE, $course->id, MUST_EXIST);

require_login($course);
require_capability('enrol/elis:config', $context);

$PAGE->set_url('/enrol/elis/edit.php', array('courseid'=>$course->id));
$PAGE->set_pagelayout('admin');

$return = new moodle_url('/enrol/instances.php', array('id'=>$course->id));
if (!enrol_is_enabled('elis')) {
    redirect($return);
}

$plugin = enrol_get_plugin('elis');

if ($instances = $DB->get_records('enrol', array('courseid'=>$course->id, 'enrol'=>'elis'), 'id ASC')) {
    $instance = array_shift($instances);
    if ($instances) {
        // oh - we allow only one instance per course!!
        foreach ($instances as $del) {
            $plugin->delete_instance($del);
        }
    }
} else {
    require_capability('moodle/course:enrolconfig', $context);
    // no instance yet, we have to add new instance
    navigation_node::override_active_url(new moodle_url('/enrol/instances.php', array('id'=>$course->id)));
    $instance = new stdClass();
    $instance->id       = null;
    $instance->courseid = $course->id;
}

$mform = new enrol_elis_edit_form(NULL, array($instance, $plugin, $context));

if ($mform->is_cancelled()) {
    redirect($return);

} else if ($data = $mform->get_data()) {
    if ($instance->id) {
        $instance->{enrol_elis_plugin::ENROL_FROM_COURSE_CATALOG_DB} = $data->{enrol_elis_plugin::ENROL_FROM_COURSE_CATALOG_DB};
        $instance->roleid       = $data->roleid;
        $instance->timemodified = time();
        $DB->update_record('enrol', $instance);

    } else {
        $fields = array(
            enrol_elis_plugin::ENROL_FROM_COURSE_CATALOG_DB => $data->{enrol_elis_plugin::ENROL_FROM_COURSE_CATALOG_DB},
            'roleid'=>$data->roleid,
        );
        $plugin->add_instance($course, $fields);
    }

    redirect($return);
}

$PAGE->set_title(get_string('pluginname', 'enrol_elis'));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'enrol_elis'));
$mform->set_data($instance);
$mform->display();
echo $OUTPUT->footer();
