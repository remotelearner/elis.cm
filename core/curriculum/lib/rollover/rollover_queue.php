<?php
/**
 * View the rollover queue.
 *
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
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
 *
 */

    require_once('../config.php');
    require_once $CFG->dirroot . '/course/sharelib.php';


    require_login(0, FALSE);
    require_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM));

    $run     = optional_param('run', 0, PARAM_INT);
    $del     = optional_param('del', 0, PARAM_INT);
    $confirm = optional_param('confirm', '', PARAM_ALPHANUM);
    $auto    = optional_param('auto', '', PARAM_INT);

    $navlinks = array(array(
        'name' => 'View rollover queue',
        'link' => null,
        'type' => 'misc'
    ));

    $navigation = build_navigation($navlinks);

    print_header_simple(get_string('rollover_queue_title', 'block_curr_admin'), get_string('rollover_queue_heading', 'block_curr_admin'), $navigation, '');

    if (!empty($auto)) {
        $endtime = time() + ($auto * 60);

        $status = TRUE;
        while ($status && time() < $endtime) {
            if (!process_queue()) {
                break;
            }
        }

    } else if (!empty($run)) {
        process_queue($run);

    } else if (!empty($del)) {
        if (empty($confirm)) {
            $task = get_record('block_admin_rollover_queue', 'id', $del);
            $confirm = md5($del);

            $lyes = $CFG->wwwroot . '/course/rollover_queue.php?del=' . $del . '&amp;confirm='. $confirm;
            $lno  = $CFG->wwwroot . '/course/rollover_queue.php';

            $a = new stdClass;
            $a->template = get_field('course', 'fullname', 'id', $task->templateid);
            $a->crn      = get_field('course', 'fullname', 'id', $task->courseid);

            notice_yesno(get_string('queue_delete_confirm', 'rollover', $a), $lyes, $lno);
            print_footer();
            exit;
        }

        if (delete_records('block_admin_rollover_queue', 'id', $del)) {
            redirect($CFG->wwwroot . '/course/rollover_queue.php', '', 0);
        }
    }

    $sql = "SELECT q.id as id, c.fullname as course, c2.fullname as template,
                   q.timerequested as timerequested, u.username as user
            FROM {$CFG->prefix}block_admin_rollover_queue q,
                 {$CFG->prefix}course c, {$CFG->prefix}course c2,
                 {$CFG->prefix}user u
            WHERE q.courseid = c.id AND q.templateid = c2.id
              AND q.userid = u.id
            ORDER BY q.timerequested ASC";
    $tasks = get_records_sql($sql);
    $time = time();
    if (empty($tasks)) {
        print_box('No pending tasks.', 'informationbox');
    } else {
        $table = new stdClass();
        $table->head = array(
            get_string('course'),
            get_string('template', 'rollover'),
            get_string('requesting_user', 'rollover'),
            get_string('time'),
            '',
            ''
        );
        $table->data = array();

        foreach ($tasks as $task) {
            $table->data[] = array(
                $task->course,
                $task->template,
                $task->user,
                get_string('ago', 'message', format_time($time - $task->timerequested)),
                '<a href="' . $CFG->wwwroot . '/course/rollover_queue.php?run=' . $task->id . '">run</a>',
                '<a href="' . $CFG->wwwroot . '/course/rollover_queue.php?del=' . $task->id . '">del</a>'
            );
        }

        print_table($table);
    }

    print_footer();

?>
