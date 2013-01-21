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
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */
if (!isset($_SERVER['REMOTE_ADDR'])) {
    define('CLI_SCRIPT', true);
}

require_once(dirname(__FILE__) .'/../lib/setup.php');
require_once(elispm::lib('data/curriculumcourse.class.php'));
require_once(elispm::lib('data/track.class.php'));

global $DB, $FULLME, $OUTPUT, $PAGE;

if (isset($_SERVER['REMOTE_ADDR'])) {
    $PAGE->set_context(get_context_instance(CONTEXT_SYSTEM));
    $PAGE->set_pagelayout('standard');
    $PAGE->set_url($FULLME);
    $PAGE->set_title(get_string('health_trackcheck', 'elis_program'));
    echo $OUTPUT->header();
    $br = '<br/>';
} else {
    $br = '';
    if ($argc > 1) {
        die(print_usage());
    }
}

mtrace("Begin track class fixes...{$br}");
$track_classes_fixed_cnt = 0;

$sql = "SELECT trkcls.id, trkcls.trackid, trkcls.courseid, trkcls.classid, trk.curid
         FROM {crlm_track_class} trkcls
         JOIN {crlm_track} trk ON trk.id = trkcls.trackid";
$classes = $DB->get_recordset_sql($sql);
foreach ($classes as $trackClassId=>$trackClassObj) {
    $select = "curriculumid = {$trackClassObj->curid} AND courseid = {$trackClassObj->courseid}";
    $cnt = $DB->count_records_select(curriculumcourse::TABLE, $select);
    if ($cnt < 1) {
        $sql = "DELETE FROM {crlm_track_class}
                      WHERE id = {$trackClassObj->id} LIMIT 1";
        $DB->execute($sql);
        $track_classes_fixed_cnt++;
    }
}
unset($classes);

if ($track_classes_fixed_cnt > 0) {
    mtrace("{$track_classes_fixed_cnt} classes removed from tracks.{$br}");
} else {
    mtrace("No unassociated track classes found!{$br}");
}

if ($br != '') {
    echo '<p><a href="'. $CFG->wwwroot .'/elis/program/index.php?s=health">Go back to health check page</a></p>';
    echo $OUTPUT->footer();
}

exit;

function print_usage() {
    mtrace('Usage: '. basename(__FILE__) ."\n");
}

