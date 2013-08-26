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


if (!defined('CLI_SCRIPT')) define('CLI_SCRIPT', true);

require_once(dirname(__FILE__) .'/../lib/setup.php');
require_once elispm::lib('data/student.class.php');

cleanup(); // Perform the cleanup now


function cleanup() {
    global $DB;

    $dbman = $DB->get_manager();

    $tablename  = student::TABLE;
    $tempname   = $tablename .'_temp';

    $table = new xmldb_table($tempname);
    if ($dbman->table_exists($table)) {
        if (!$dbman->drop_table($table)) {
            mtrace(' <<< Could not remove temporary table: '.$tempname);
            exit;
        }
    }

    // Create a temporary table by reading in the XMLDB file that defines the student enrolment table
    $xmldb_file = new xmldb_file(elispm::file('db/install.xml'));
    if (!$xmldb_file->fileExists() or !$xmldb_file->loadXMLStructure()) {
        continue;
    }
    $structure = $xmldb_file->getStructure();
    $tables = $structure->getTables();

    foreach ($tables as $table) {
        if ($table->getName() == $tablename) {
            $xml_temptable = $table;
            $xml_temptable->setName($tempname);
            $xml_temptable->setPrevious(null);
            $xml_temptable->setNext(null);

            $tempstructure = new xmldb_structure('temp');
            $tempstructure->addTable($xml_temptable);

            try {
                $dbman->install_from_xmldb_structure($tempstructure);
            } catch (ddl_change_structure_exception $e) {
                mtrace(' <<< Could not create temporary table: '.$tempname);
                exit;
            }

            mtrace(' >>> Created temporary table: '.$tempname);
        }
    }

    $xml_table = new xmldb_table($tablename);

    // Step 1. -- attempt to move unique values into the temporary table in a way that should leave some duplicates but
    //            will remove the vast majority of the them
        $sql = "INSERT INTO {{$tempname}}
                SELECT id, classid, userid, enrolmenttime, MIN(completetime) AS completetime, endtime, completestatusid,
                       grade, credits, locked
                FROM {{$tablename}}
                GROUP BY classid, userid, completestatusid, grade, credits, locked";

    try {
        $DB->execute($sql);
    } catch (dml_exception $e) {
        mtrace(' <<< Could not move duplicate records from: '.$tablename.' into: '.$tempname);
        exit;
    }

    mtrace(' >>> Moved duplicate records from: '.$tablename .' into: '.$tempname);


    // Step 2. -- detect if we still have any duplicates remaining
    $sql = "SELECT id, COUNT(*) AS count, classid, userid, enrolmenttime, completetime, completestatusid, grade, credits, locked
            FROM {{$tempname}}
            GROUP BY classid, userid
            ORDER BY count DESC, classid ASC, userid ASC";

    if ($dupcount = $DB->get_records_sql($sql, array(), 0, 1)) {
        $dupe = current($dupcount);

        if ($dupe->count > 1) {
            mtrace(' <<< Duplicate records still exist in temporary table');
        } else {
            mtrace(' >>> No duplicate records exist in temporary table');
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

                $select = 'classid = '.$classid.' AND userid = '.$userid.' AND id != '.$goodid;
            }

            // If we have some records to clean up, let's do that now
            if (!empty($select)) {
                $status = true;

                try {
                    $DB->delete_records_select($tempname, $select);
                } catch (dml_exception $e) {
                    mtrace(' <<< Could not clean up duplicate '.$tempname.' records for userid = '.$userid .', classid = '.$classid);
                    $status = false;
                }

                if ($status) {
                    mtrace(' >>> Cleaned up duplicate '.$tempname.' records for userid = '.$userid.', classid = '.$classid);
                }
            }
        }

        $rs->close();
    }

    // Step 4. -- drop the table containing duplicate values and rename the temporary table to take it's place
    try {
        $dbman->drop_table($xml_table);
    } catch (ddl_change_structure_exception $e) {
        mtrace(' <<< Could not drop table: '.$tablename);
        exit;
    }

    mtrace(' >>> Successfully dropped table: '.$tablename);

    // Rename the temporary table to allow it to replace the real table
    try {
        $dbman->rename_table($xml_temptable, $tablename);
    } catch (ddl_change_structure_exception $e) {
        mtrace(' <<< Could not rename table: '.$tempname.' to: '.$tablename);
        exit;
    }

    mtrace(' >>> Successfully renamed table: '. $tempname .' to: '.$tablename);
}

function print_usage() {
    mtrace('Usage: '. basename(__FILE__) ."\n");
}

