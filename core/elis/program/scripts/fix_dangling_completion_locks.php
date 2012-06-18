<?php
/**
 * Find and lock all unlocked completion elements scores and student class grades which should be locked
 * but cannot be updated because the Moodle referenced grade values do not exist anymore.
 *
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
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

define('CLI_SCRIPT', true);

require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(elispm::lib('data/student.class.php'));

if (isset($_SERVER['REMOTE_ADDR'])) {
    die('no web access');
}

// Check for unlocked dangling completion element grades and lock them
$dangling_total = 0;
$dangling_fixed = 0;

mtrace(' >>> '.get_string('health_dangling_check', 'elis_program'));

// Cleanup unlocked, passed completion scores which are not associated with a valid Moodle grade item
$sql = "SELECT ccg.id, ccg.locked, ccg.timemodified
        FROM {".student_grade::TABLE."} ccg
        INNER JOIN {".coursecompletion::TABLE."} ccc ON ccc.id = ccg.completionid
        INNER JOIN {".classmoodlecourse::TABLE."} ccm ON ccm.classid = ccg.classid
        INNER JOIN {course} c ON c.id = ccm.moodlecourseid
        LEFT JOIN {grade_items} gi ON (gi.idnumber = ccc.idnumber AND gi.courseid = c.id)
        WHERE ccg.locked = 0
        AND ccc.idnumber != ''
        AND ccg.grade >= ccc.completion_grade
        AND gi.id IS NULL";

if ($rs = $DB->get_recordset_sql($sql)) {

    $timenow = time();

    foreach ($rs as $completion) {
        $dangling_total++;

        $completion->locked = 1;

        // Make sure a timemodified value is set, just in case
        if ($completion->timemodified == 0) {
            $completion->timemodified = $timenow;
        }

        if ($DB->update_record(student_grade::TABLE, $completion)) {
            $dangling_fixed++;
        }
    }

    unset($completion);
    $rs->close();
}

/*
// Clean up passed completion scores which are associated with a valid Moodle grade item but are not locked
// XXX - NOTE: this is not currently being done as it may be that these values were manually unlocked on purpose
// XXX - NOTE: this is from 1.9 so if / when using this query, update query to 2.x standard
$sql = "SELECT ccg.id, ccg.timemodified
        FROM {$CURMAN->db->prefix_table(USRTABLE)} cu
        INNER JOIN {$CURMAN->db->prefix_table(STUTABLE)} cce ON cce.userid = cu.id
        INNER JOIN {$CURMAN->db->prefix_table(GRDTABLE)} ccg ON (ccg.userid = cce.userid AND ccg.classid = cce.classid)
        INNER JOIN {$CURMAN->db->prefix_table(CRSCOMPTABLE)} ccc ON ccc.id = ccg.completionid
        INNER JOIN {$CURMAN->db->prefix_table(CLSMOODLETABLE)} ccm ON ccm.classid = ccg.classid
        INNER JOIN {$CFG->prefix}user u ON u.idnumber = cu.idnumber
        INNER JOIN {$CFG->prefix}course c ON c.id = ccm.moodlecourseid
        INNER JOIN {$CFG->prefix}grade_items gi ON (gi.courseid = c.id AND gi.idnumber = ccc.idnumber)
        INNER JOIN {$CFG->prefix}grade_grades gg ON (gg.itemid = gi.id AND gg.userid = u.id)
        WHERE ccg.locked = 0
        AND ccg.grade >= ccc.completion_grade
        AND gg.finalgrade >= ccc.completion_grade
        AND ccc.idnumber != ''
        AND gi.itemtype != 'course'
        AND ccg.timemodified > gg.timemodified";

$ce_check = array();

if ($rs = get_recordset_sql($sql)) {
    while ($completion = rs_fetch_next_record($rs)) {
        $dangling_total++;

        $completion->locked = 1;

        // Make sure a timemodified value is set, just in case
        if ($completion->timemodified == 0) {
            $completion->timemodified = $timenow;
        }

        if (update_record(GRDTABLE, $completion)) {
            $dangling_fixed++;
        }
    }

    rs_close($rs);
}
*/
$a = new stdClass;
$a->fixed = $dangling_fixed;
$a->total = $dangling_total;

mtrace(' >>> '.get_string('health_dangling_fixed_counts', 'elis_program', $a));

if ($dangling_fixed > 0) {
    // Make a note that class grades are being recalculated
    mtrace(' >>> '.get_string('health_dangling_recalculate', 'elis_program'), '');
    pm_update_enrolment_status();
    mtrace(get_string('done', 'elis_program').'!');
}
