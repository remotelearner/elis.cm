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

global $CFG, $DB, $FULLME, $OUTPUT, $PAGE;

if (isset($_SERVER['REMOTE_ADDR'])) {
    require_login();
    $context = get_context_instance(CONTEXT_SYSTEM);
    require_capability('moodle/site:config', $context); // WAS :doanything
    $PAGE->set_context($context);
    $PAGE->set_pagelayout('standard');
    $PAGE->set_url($FULLME);
    $PAGE->set_title(get_string('health_dupmoodleprofile', 'elis_program'));
    echo $OUTPUT->header();
    echo '<pre>';
}

$result = true;
$dbman = $DB->get_manager();

// Delete duplicate class completion element grades
$xmldbtable = new xmldb_table('user_info_data_temp');

if ($dbman->table_exists($xmldbtable)) {
    $dbman->drop_table($xmldbtable);
}

echo "Creating temporary table\n";

// Create a temporary table
$result = $result && $DB->execute('CREATE TABLE {user_info_data_temp} LIKE {user_info_data}');

echo "Copying unique records\n";

// Store the unique values in the temporary table
$sql = 'INSERT INTO {user_info_data_temp}
        SELECT id, userid, fieldid, data
          FROM {user_info_data} d
         WHERE id = (SELECT MAX(id)
                       FROM {user_info_data} d2
                      WHERE d2.userid = d.userid AND d2.fieldid = d.fieldid)';
$result = $result && $DB->execute($sql);

echo "Moving temp table to real table\n";
// Drop the real table
$result = $result && $DB->execute('DROP TABLE {user_info_data}');

// Replace the real table with the temporary table that now only contains unique values.
$result = $result && $DB->execute('ALTER TABLE {user_info_data_temp} RENAME TO {user_info_data}');

echo "Done.\n";

if (isset($_SERVER['REMOTE_ADDR'])) {
    echo '</pre>';
    echo '<p><a href="'. $CFG->wwwroot .'/elis/program/index.php?s=health">Go back to health check page</a></p>';
    echo $OUTPUT->footer();
}
