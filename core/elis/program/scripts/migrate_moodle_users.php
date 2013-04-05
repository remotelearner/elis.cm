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
 * @subpackage programmanagement-scripts
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */
if (!isset($_SERVER['REMOTE_ADDR'])) {
    define('CLI_SCRIPT', true);
}

require_once(dirname(__FILE__) .'/../lib/setup.php');
require_once elispm::lib('lib.php');

global $CFG, $FULLME, $OUTPUT, $PAGE;

if (isset($_SERVER['REMOTE_ADDR'])) {
    $context = get_context_instance(CONTEXT_SYSTEM);
    require_capability('moodle/site:config', $context); // WAS :doanything
    $PAGE->set_context($context);
    $PAGE->set_pagelayout('standard');
    $PAGE->set_url($FULLME);
    $PAGE->set_title(get_string('health_user_sync', 'elis_program'));
    echo $OUTPUT->header();
}

mtrace('Migrating Moodle Users to ELIS ...');
pm_migrate_moodle_users();
mtrace('Done.');

if (isset($_SERVER['REMOTE_ADDR'])) {
    echo '<p><a href="'. $CFG->wwwroot .'/elis/program/index.php?s=health">Go back to health check page</a></p>';
    echo $OUTPUT->footer();
}
