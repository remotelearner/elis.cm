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

require_once(dirname(__FILE__) .'/../../config.php');

global $DB, $ME, $OUTPUT, $PAGE;

$id = required_param('id', PARAM_INT);

$context = get_context_instance(CONTEXT_SYSTEM);

require_capability('block/course_request:request', $context);

$blockname = get_string('blockname', 'block_course_request');
$header = get_string('notice', 'block_course_request');

$PAGE->set_pagelayout('standard'); // TBV
$PAGE->set_pagetype('elis'); // TBV
$PAGE->set_context($context);
$PAGE->set_url($ME);
$PAGE->set_title($blockname); // TBV
$PAGE->set_heading($header); // TBV
$PAGE->set_cacheable(true);
$PAGE->set_button('&nbsp;');

//$navlinks = array();
//$navlinks[] = array('name' => $blockname, 'link' => "{$CFG->wwwroot}/elis/program/index.php?action=requests&s=crp", 'type' => 'misc');
//$navlinks[] = array('name' => $header, 'type' => 'misc');
// TBD
// $PAGE->navbar->add()

echo $OUTPUT->heading($header);

$request = $DB->get_record('block_course_request', array('id' => $id));
if (!empty($request)) {
    echo $OUTPUT->box($request->statusnote);
}

