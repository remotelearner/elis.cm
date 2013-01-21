<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
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

require_once(dirname(__FILE__).'/../../../../config.php');
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once elispm::lib('data/curriculumcourse.class.php');
require_once elispm::lib('data/track.class.php');

$courseid = required_param('courseid', PARAM_INT);

require_login(0, false);

$tracks = track_get_listing();
$curcrsassign = curriculumcourse_get_list_by_course($courseid);
$allowed_tracks = array();

// Obtain all tracks belonging to tracks that the course description is
// associated to
foreach ($curcrsassign as $recid => $curcrsrec) {
    foreach ($tracks as $trackid => $trackrec) {
        if ($trackrec->curid == $curcrsrec->curriculumid) {
            $allowed_track = new stdClass;
            $allowed_track->name = $trackrec->name;
            $allowed_track->id = $trackid;
            $allowed_tracks[] = $allowed_track;
        }

    }
}
unset($curcrsassign);

// JSON encode our data so that we can handle it without using innerHtml
echo json_encode($allowed_tracks);
