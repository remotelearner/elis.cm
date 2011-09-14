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
 * @subpackage curriculummanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2011 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once '../config.php';
require_once '../lib/cluster.class.php';

if (isset($_SERVER['REMOTE_ADDR'])) {
    require_login();

    $context = get_context_instance(CONTEXT_SYSTEM);
    require_capability('moodle/site:doanything', $context);
    echo '<pre>';
}

$result = true;

// Delete duplicate class completion element grades
$xmldbtable = new XMLDBTable('user_info_data_temp');

if (table_exists($xmldbtable)) {
    drop_table($xmldbtable);
}

echo "creating temporary table\n";

// Create a temporary table
$result = $result && execute_sql("CREATE TABLE {$CFG->prefix}user_info_data_temp LIKE {$CFG->prefix}user_info_data");

echo "copying unique records\n";

// Store the unique values in the temporary table
$sql = "INSERT INTO {$CFG->prefix}user_info_data_temp
        SELECT id, userid, fieldid, data
          FROM {$CFG->prefix}user_info_data d
         WHERE id = (SELECT MAX(id)
                       FROM {$CFG->prefix}user_info_data d2
                      WHERE d2.userid = d.userid AND d2.fieldid = d.fieldid)";
$result = $result && execute_sql($sql);

echo "moving temp table to real table\n";
// Drop the real table
$result = $result && execute_sql("DROP TABLE {$CFG->prefix}user_info_data");

// Replace the real table with the temporary table that now only contains unique values.
$result = $result && execute_sql("ALTER TABLE {$CFG->prefix}user_info_data_temp RENAME TO {$CFG->prefix}user_info_data");

echo "done.\n";

if (isset($_SERVER['REMOTE_ADDR'])) {
    echo '</pre>';
}