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
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/../../../../../config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(elispm::lib('data/userset.class.php'));

function cluster_manual_delete_for_cluster($id) {
    // Do nothing
    return true;
}

function cluster_manual_edit_form($form, $mform, $clusterid) {
    // nothing needs to be done
}

function userset_manual_update($cluster) {
    // nothing needs to be done
}

function cluster_manual_assign_user($clusterid, $userid, $autoenrol = true, $leader = false) {
    global $CFG;

    if (!is_numeric($clusterid) || !is_numeric($userid) || ($clusterid <= 0) || ($userid <= 0)) {
        return false;
    }

    if (clusterassignment::exists(array(new field_filter('userid', $userid),
                                        new field_filter('clusterid', $clusterid),
                                        new field_filter('plugin', 'manual')))) {
        return true;
    }

    $record = new clusterassignment();
    $record->clusterid = $clusterid;
    $record->userid = $userid;
    $record->plugin = 'manual';
    $record->autoenrol = $autoenrol;
    $record->leader = $leader;
    $record->save();

    return true;
}

function cluster_manual_deassign_user($clusterid, $userid) {
    global $CURMAN, $CFG;

    if (!is_numeric($clusterid) || !is_numeric($userid) || ($clusterid <= 0) || ($userid <= 0)) {
        return false;
    }

    clusterassignment::delete_records(array(new field_filter('userid', $userid),
                                            new field_filter('clusterid', $clusterid),
                                            new field_filter('plugin', 'manual')));

    return true;
}
