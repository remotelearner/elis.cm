<?php // $Id: fix_track_classes.php,v 1.0 2011/01/03 11:00:00 mvidberg Exp $

/**
 * Fix ELIS track classes that have had their parent curriculum courses deleted.
 *
 * @version   $Id: fix_track_classes.php,v 1.0 2011/01/03 11:00:00 mvidberg Exp $
 * @package   codelibrary
 * @copyright 2011 Remote Learner - http://www.remote-learner.net/
 * @author    Marko Vidberg <marko.vidberg@remote-learner.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

    require_once '../config.php';
    require_once '../lib/track.class.php';

    if (isset($_SERVER['REMOTE_ADDR'])) {
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
            FROM {$CURMAN->db->prefix_table('crlm_track_class')} trkcls
            JOIN {$CURMAN->db->prefix_table('crlm_track')} trk ON trk.id = trkcls.trackid";
    $classes = $CURMAN->db->get_records_sql($sql);

    if (is_array($classes)) {
        foreach ($classes as $trackClassId=>$trackClassObj) {
            $select = "curriculumid = {$trackClassObj->curid} AND courseid = {$trackClassObj->courseid}";
            $cnt = $CURMAN->db->count_records_select(CURCRSTABLE, $select);
            if ($cnt < 1) {
                $sql = "DELETE FROM {$CURMAN->db->prefix_table('crlm_track_class')} WHERE id={$trackClassObj->id} LIMIT 1";
                $feedback = "";
                execute_sql($sql, $feedback);
                $track_classes_fixed_cnt++;
            }
        }
    }

    if ($track_classes_fixed_cnt > 0) {
        mtrace("{$track_classes_fixed_cnt} classes removed from tracks.{$br}");
    } else {
        mtrace("No unassociated track classes found!{$br}");
    }

    if ($br != '') {
        echo '<p><a href="'.$CFG->wwwroot.'/curriculum/index.php?s=health">Go back to health check page</a></p>';
    }

    exit;

    function print_usage() {
        mtrace('Usage: ' . basename(__FILE__) . "\n");
    }

?>
