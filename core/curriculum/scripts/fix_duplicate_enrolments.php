<?php

/**
 * Fix ELIS track classes that have had their parent curriculum courses deleted.
 *
 * @version   $Id: fix_track_classes.php,v 1.0 2011/01/03 11:00:00 mvidberg Exp $
 * @package   codelibrary
 * @copyright 2011 Remote Learner - http://www.remote-learner.net/
 * @author    Marko Vidberg <marko.vidberg@remote-learner.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once dirname(dirname(__FILE__)).'/config.php';
require_once dirname(dirname(__FILE__)).'/lib/student.class.php';

if (isset($_SERVER['REMOTE_ADDR'])) {
    die('no web access');
}

if ($argc > 1) {
    die(print_usage());
}

$tablename = STUTABLE;
$tempname  = STUTABLE.'_temp';

$xmldbtable = new XMLDBTable($tempname);

if (table_exists($xmldbtable)) {
    if (!drop_table($xmldbtable)) {
        mtrace(' <<< Could not remove temporary table '.$tempname);
        exit;
    }
}

// Create a temporary table
if (execute_sql("CREATE TABLE {$CURMAN->db->prefix_table($tempname)} LIKE {$CURMAN->db->prefix_table($tablename)}", false)) {
    mtrace(' >>> Created temporary table '.$tempname);
} else {
    mtrace(' <<< Could not create temporary table '.$tempname);
    exit;
}

// Step 1. -- attempt to move unique values into the temporary table in a way that should leave some duplicates but
//            will remove the vast majority of the them
$sql = "INSERT INTO {$CURMAN->db->prefix_table($tempname)}
        SELECT id, classid, userid, enrolmenttime, MIN(completetime) AS completetime, endtime, completestatusid,
               grade, credits, locked
        FROM {$CURMAN->db->prefix_table($tablename)}
        GROUP BY classid, userid, completestatusid, grade, credits, locked";

if (!execute_sql($sql, false)) {
    mtrace(' <<< Could not move duplicate records from '.$tablename.' into '.$tempname);
} else {
    mtrace(' >>> Moved duplicate records from '.$tablename.' into '.$tempname);
}

// Step 2. -- detect if we still have any duplicates remaining
$sql = "SELECT COUNT(*) AS count, classid, userid, enrolmenttime, completetime, completestatusid, grade, credits, locked
        FROM {$CURMAN->db->prefix_table($tablename)}
        GROUP BY classid, userid
        ORDER BY count DESC, classid ASC, userid ASC";

if ($dupcount = get_record_sql($sql, true)) {
    if ($dupcount->count > 1) {
        mtrace(' <<< Duplicate records still exist in temporary table');
    } else {
        mtrace(' >>> No duplicate records exist in temporary table');
        exit;
    }
}

// Step 3. -- at this point duplicate data was found, so we will need to process each record in turn to find the first
//            legitimate record that should be kept
if ($rs = get_recordset_sql($sql)) {
    while ($dupe = rs_fetch_next_record($rs)) {
        if ($dupe->count <= 1) {
            continue;
        }

        $classid = $dupe->classid;
        $userid  = $dupe->userid;
        $goodid  = 0; // The ID of the record we will keep

        // Find the record marked "complete" or "failed" and locked with the earliest completion time
        $sql2 = "SELECT id, completestatusid, grade locked
                 FROM {$CURMAN->db->prefix_table($tempname)}
                 WHERE userid = $userid
                 AND classid = $classid
                 ORDER BY completetime ASC, completestatusid ASC, locked ASC";

        if ($rs2 = get_recordset_sql($sql2)) {
            while ($rec = rs_fetch_next_record($rs2)) {
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

            rs_close($rs2);

            // We need to make sure we have a record ID to keep, if we found no "complete" and locked
            // records, let's just keep the last record we saw
            if (empty($goodid)) {
                $goodid = $lastid;
            }

            $select = 'classid = '.$classid.' AND userid = '.$userid.' AND id != '.$goodid;
        }

        // If we have some records to clean up, let's do that now
        if (!empty($select)) {
            if (!delete_records_select($tempname, $select)) {
                mtrace(' <<< Could not clean up duplicate '.$tempname.' records for userid = '.$userid.', classid = '.$classid);
            } else {
                mtrace(' >>> Cleaned up duplicate '.$tempname.' records for userid = '.$userid.', classid = '.$classid);
            }
        }
    }

    rs_close($rs);
}

// Step 4. -- drop the table containing duplicate values and rename the temporary table to take it's place
if (!execute_sql("DROP TABLE {$CURMAN->db->prefix_table($tablename)}", false)) {
    mtrace(' <<< Could not execute query: "DROP TABLE '.$tablename.'"');
} else {
    mtrace(' >>> Executed query: "DROP TABLE '.$tablename.'"');
}

// Rename the temporary table to allow it to replace the real table
if (!execute_sql("ALTER TABLE {$CURMAN->db->prefix_table($tempname)} RENAME TO {$CURMAN->db->prefix_table($tablename)}", false)) {
    mtrace(' <<< Could not execute query: "ALTER TABLE '.$CURMAN->db->prefix_table($tempname).' RENAME TO '.
           $tablename.'"');
} else {
    mtrace(' >>> Executed query: "ALTER TABLE '.$CURMAN->db->prefix_table($tempname).' RENAME TO '.
           $CURMAN->db->prefix_table($tablename).'"');
}


exit;


function print_usage() {
    mtrace('Usage: ' . basename(__FILE__) . "\n");
}


?>
