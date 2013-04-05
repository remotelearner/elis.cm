<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for component 'elis_program', language 'en_us', branch 'ELIS_2.0.0'
 *
 * @package   elis_program
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['delete_cancelled'] = 'Delete canceled';
$string['edit_cancelled'] = 'Edit canceled';
$string['enrol'] = 'Enroll';
$string['enrole_sync_settings'] = 'Enrollment Role Sync Settings';
$string['enrolment'] = 'Enrollment';
$string['enrolments'] = 'Enrollments';
$string['enrolment_time'] = 'Enrollment Date';
$string['enrolstudents'] = 'Enroll Student';
$string['enrol_all_users_now'] = 'Enroll all users from this track now';
$string['enrol_confirmation'] = 'You will be placed on a waitlist for this course. Are you sure you would like to enroll in ({$a->coursename}){$a->classid}?';
$string['enrol_selected'] = 'Enroll Selected';
$string['error_not_using_elis_enrolment'] = 'The associated Moodle course is not using the ELIS enrollment plugin';
$string['error_n_overenrol'] = 'The over enroll capability is required for this';
$string['force_unenrol_in_moodle_help'] = 'If this setting is set, then ELIS will forcibly unenroll users from the associated Moodle course when they are unenrolled from the ELIS class instance, regardless of which enrollment plugin they used to enroll.

If this setting is unset, ELIS will only unenroll users who were originally enrolled via ELIS.';
$string['force_unenrol_in_moodle_setting'] = 'Force unenrollment in Moodle course';
$string['health_duplicate'] = 'Duplicate enrollment records';
$string['health_duplicatedesc'] = 'There were {$a} duplicate enrollments records in the ELIS enrollments table.';
$string['health_duplicatesoln'] = 'The duplicate enrollments need to be removed directly from the database. <b>DO NOT</b> try to remove them via the UI.<br/><br/> Recommended to escalate to development for solution.';
$string['moodleenrol_subj'] = 'Ready to enroll in {$a->idnumber}.';
$string['notice_usertrack_deleted'] = 'Unenrolled the user from track: {$a->trackid}';
$string['notifyclassenrolmessage'] = 'Message template for class instance enrollment';
$string['notifyclassnotstarteddays'] = 'Number of days after enrollment to send message';
$string['notifytrackenrolmessage'] = 'Message template for track enrollment';
$string['notify_classenrol'] = 'Receive class instance enrollment notifications';
$string['notify_trackenrol'] = 'Receive track enrollment notifications';
$string['over_enrol'] = 'Over Enroll';
$string['pmclassform:waitlistenrol'] = 'Auto enroll from waitlist';
$string['pmclassform:waitlistenrol_help'] = '<p>Check this box to automatically enroll students from the waitlist into the course description when an erolled student completes (passes or fails) the course description.</p>';
$string['pmclass_delete_warning'] = 'Warning!  Deleting this class instance will also delete all stored enrollment information for the class instance.';
$string['pmclass_delete_warning_continue'] = 'I understand all enrollments for the class instance will be deleted, continue ...';
$string['program:class_enrol'] = 'Manage class instance enrollments';
$string['program:class_enrol_userset_user'] = 'Manage user set\'s users\' class instance enrollments';
$string['program:notify_classenrol'] = 'Receive class enrollment notifications';
$string['program:notify_trackenrol'] = 'Receive track enrollment notifications';
$string['program:overrideclasslimit'] = 'Can over enroll a class';
$string['program:program_enrol'] = 'Manage program enrollments';
$string['program:program_enrol_userset_user'] = 'Manage user set\'s users\' program enrollments';
$string['program:track_enrol'] = 'Manage track enrollments';
$string['program:track_enrol_userset_user'] = 'Manage user set\'s users\' track enrollments';
$string['student_deleteconfirm'] = 'Are you sure you want to unenroll the student name: {$a->name} ?<br />NOTE: This will delete all records for this student in this class and will unenroll them from any connected Moodle class!';
$string['subplugintype_usersetenrol_plural'] = 'User set enrollment methods';
$string['trackassignmentform:track_autoenrol'] = 'Auto-enroll';
$string['trackassignmentform:track_autoenrol_help'] = '<p>Auto enroll into this track.</p>';
$string['trackassignmentform:track_autoenrol_long'] = 'Auto-enroll users into this class instance when they are added to this track';
$string['trackuserset_auto_enrol'] = 'Auto-enroll';
$string['track_auto_enrol'] = 'Auto-enroll';
$string['track_click_user_enrol_track'] = 'Click on a user to enroll him/her in the track.';
$string['unenrol'] = 'Unenroll';
$string['update_enrolment'] = 'Update Enrollment';
$string['usersetprogramform:autoenrol'] = 'Auto-enroll';
$string['usersetprogramform_auto_enrol'] = 'Auto-enroll users into this program when they are added to this user set';
$string['usersetprogram_auto_enrol'] = 'Auto-enroll';
$string['usersettrackform:autoenrol'] = 'Auto-enroll';
$string['usersettrack_autoenrol'] = 'Auto-enroll';
$string['usersettrack_auto_enrol'] = 'Auto-enroll users into this track when they are added to this user set';
$string['waitlistenrol'] = 'Auto enroll from waitlist';
