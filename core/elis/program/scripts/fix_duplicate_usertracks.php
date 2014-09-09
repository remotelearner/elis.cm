<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2014 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @package    elis_program
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2014 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

if (!isset($_SERVER['REMOTE_ADDR'])) {
    define('CLI_SCRIPT', true);
}

require_once(dirname(__FILE__).'/../lib/setup.php');
require_once(elispm::lib('lib.php'));

global $CFG, $FULLME, $OUTPUT, $PAGE;

if (isset($_SERVER['REMOTE_ADDR'])) {
    $context = get_context_instance(CONTEXT_SYSTEM);
    require_capability('moodle/site:config', $context);
    $PAGE->set_context($context);
    $PAGE->set_pagelayout('standard');
    $PAGE->set_url($FULLME);
    $PAGE->set_title(get_string('health_dupusertrack', 'elis_program'));
    echo $OUTPUT->header();
}

mtrace('Removing duplicate user track records ...');
pm_fix_duplicate_usertrack_records();
mtrace('Done.');

if (isset($_SERVER['REMOTE_ADDR'])) {
    $url = $CFG->wwwroot.'/elis/program/index.php?s=health';
    $link = html_writer::link($url, 'Go back to health check page');
    echo html_writer::tag('p', $link);
    echo $OUTPUT->footer();
}
