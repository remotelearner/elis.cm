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

/**
 * Generate a JSON data set containing all the classes belonging to the specified course
 */

require_once('../../../../config.php'); // Moodle
require_once('../../../config.php');    // Curriculum

require_once($CFG->dirroot.'/curriculum/lib/contexts.php');
require_once($CFG->dirroot.'/curriculum/lib/course.class.php');

if (!isloggedin() || isguestuser()) {
    mtrace("ERROR: must be logged in!");
    exit;
}

$ids      = array();
if (array_key_exists('id', $_REQUEST)) {
    $dirtyids = $_REQUEST['id'];
    if (is_array($dirtyids)) {
        foreach ($dirtyids as $dirty) {
            $ids[] = clean_param($dirty, PARAM_INT);
        }
    } else {
        $ids[] = clean_param($dirtyids, PARAM_INT);
    }
} else {
    $ids[] = 0;
}


// Must have blank value as the default here (instead of zero) or it breaks the gas guage report
$choices_array = array(array('', get_string('anyvalue', 'filters')));
if (sizeof($ids) > 0) {
    $contexts = get_contexts_by_capability_for_user('course', 'block/php_report:view', $USER->id);
    foreach ($ids as $id) {
        $records = false;

        if ($id > 0) {
            $records = curriculumcourse_get_listing($id, 'crs.name');
            $idfield   = 'courseid';
            $namefield = 'coursename';
        } else if ($id == 0) {
            $records = course_get_listing();
            $idfield   = 'id';
            $namefield = 'name';
        }

        if (is_array($records)) {
            foreach ($records as $record) {
                $choices_array[] = array($record->id, $record->$namefield);
            }
        }
    }
}

echo json_encode($choices_array);