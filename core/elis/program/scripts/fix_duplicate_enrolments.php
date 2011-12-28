<?php // $Id: fix_track_classes.php,v 1.0 2011/01/03 11:00:00 mvidberg Exp $
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
 * @copyright  (C) 2008-2011 Remote Learner.net Inc http://www.remote-learner.net
 *
 */
if (!isset($_SERVER['REMOTE_ADDR'])) {
    define('CLI_SCRIPT', true);
}

require_once(dirname(__FILE__) .'/../lib/setup.php');
require_once elispm::lib('data/student.class.php');

global $DB;

if (isset($_SERVER['REMOTE_ADDR'])) {
    die('no web access');
}

if ($argc > 1) {
    die(print_usage());
}

$tablename  = student::TABLE;
$tempname   = $tablename .'_temp';
$xmldbtable = new XMLDBTable($tempname);
if ($DB->get_manager()->table_exists($xmldbtable)) {
    if (!$DB->execute("TRUNCATE TABLE {{$tempname}}")) {
        mtrace(' <<< Could not empty temporary table '. $tempname);
        exit;
    }
}

// Create a temporary table
if ($DB->execute("CREATE TABLE {{$tempname}} LIKE {{$tablename}}")) {
    mtrace(' >>> Created temporary table '. $tempname);
} else {
    mtrace(' <<< Could not create temporary table '. $tempname);
    exit;
}

// Step 1. -- attempt to move unique values into the temporary table in a way that should leave some duplicates but
//            will remove the vast majority of the them
$sql = "INSERT INTO {{$tempname}}
        SELECT id, classid, userid, enrolmenttime, MIN(completetime) AS completetime, completestatusid,
               grade, credits, locked
        FROM {{$tablename}}
        GROUP BY classid, userid, completestatusid, grade, credits, locked";

if (!$DB->execute($sql)) {
    mtrace(' <<< Could not move duplicate records from '. $tablename .' into '. $tempname);
} else {
    mtrace(' >>> Moved duplicate records from '. $tablename .' into '. $tempname);
}

// Step 2. -- detect if we still have any duplicates remaining
$sql = "SELECT COUNT(*) AS count, classid, userid, enrolmenttime, completetime, completestatusid, grade, credits, locked
          FROM {{$tablename}}
      GROUP BY classid, userid
      ORDER BY count DESC, classid ASC, userid ASC";

if ($dupcount = $DB->get_records_sql($sql)) {
    if ($dupcount->count > 1) {
        mtrace(' <<< Duplicate records still exist in temporary table');
    } else {
        mtrace(' >>> No duplicate records exist in temporary table');
        exit;
    }
}

// Step 3. -- at this point duplicate data was found, so we will need to process each record in turn to find the first
//            legitimate record that should be kept
if ($rs = $DB->get_recordset_sql($sql)) {
    foreach ($rs as $dupe) {
        if ($dupe->count <= 1) {
            continue;
        }
        $classid = $dupe->classid;
        $userid  = $dupe->userid;
        $goodid  = 0; // The ID of the record we will keep

        // Find the record marked "complete" or "failed" and locked with the earliest completion time
        $sql2 = "SELECT id, completestatusid, grade locked
                   FROM {{$tempname}}
                  WHERE userid = $userid
                    AND classid = $classid
               ORDER BY completetime ASC, completestatusid ASC, locked ASC";

        if ($rs2 = $DB->get_recordset_sql($sql2)) {
            foreach ($rs2 as $rec) {
                // Store the last record ID just in case we need it for cleanup
                $lastid = $rec->id;

                // Don't bother looking at remaining records if we have found a record to keep
                if (!empty($goodid)) {
                    continue;
                }

                if ($rec->completestatusid != 0 && $rec->locked = 1) {
                    $goodid = $rec->id;
                }
            }
            $rs2->close();

            // We need to make sure we have a record ID to keep, if we found no "complete" and locked
            // records, let's just keep the last record we saw
            if (empty($goodid)) {
                $goodid = $lastid;
            }

            $select = 'classid = '. $classid .' AND userid = '. $userid .' AND id != '. $goodid;
        }

        // If we have some records to clean up, let's do that now
        if (!empty($select)) {
            if (!$DB->delete_records_select($tempname, $select)) {
                mtrace(' <<< Could not clean up duplicate '. $tempname .' records for userid = '. $userid .', classid = '. $classid);
            } else {
                mtrace(' >>> Cleaned up duplicate '. $tempname .' records for userid = '. $userid .', classid = '. $classid);
            }
        }
    }
    $rs->close();
}

// Step 4. -- drop the table containing duplicate values and rename the temporary table to take it's place
if (!$DB->execute("DROP TABLE {{$tablename}}")) {
    mtrace(' <<< Could not execute query: "DROP TABLE '. $tablename .'"');
} else {
    mtrace(' >>> Executed query: "DROP TABLE '. $tablename .'"');
}

// Rename the temporary table to allow it to replace the real table
if (!$DB->execute("ALTER TABLE {{$tempname}} RENAME TO {{$tablename}}")) {
    mtrace(' <<< Could not execute query: "ALTER TABLE {'. $tempname .'} RENAME TO {'. $tablename .'}"');
} else {
    mtrace(' >>> Executed query: "ALTER TABLE {'. $tempname .'} RENAME TO {'.
           $tablename .'}"');
}

exit;

function print_usage() {
    mtrace('Usage: '. basename(__FILE__) ."\n");
}

