<?php
/**
 * Library functions for the automatic course content roll-over functionality.
 *
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

define('SHARE_RESTRICT_LISTING',   true);
define('SHARE_AUTOROLLOVER_DELTA', WEEKSECS);


/**
 * Determine if the given course was built using a template.
 *
 * @param int $cid The course ID.
 * @return bool Whether a template was used.
 */
    function course_using_template($cid) {
        if (course_has_content($cid)) {
            return true;
        }

        return record_exists('block_admin_course_template', 'courseid', $cid);
    }


/**
 * Determine if a course has content within it or not.
 *
 * Having content is denoted by containing a non-zero number of course
 * modules in a SCORM-format course and having more than one course module
 * for any other format of course.
 *
 * @param int $cid The course ID.
 * @return bool Whether the course has content or not.
 */
    function course_has_content($cid) {
        if (get_field('course', 'format', 'id', $cid) == 'scorm') {
            return (count_records('course_modules', 'course', $cid) > 0);
        }

        return (count_records('course_modules', 'course', $cid) > 1);
    }


/**
 * Get the CRN number from a course name.
 *
 * @param object $course The course database object (must at least contain the 'fullname' parameter).
 * @return int|bool The CRN value or, False on failure.
 */
    function get_crn_from_coursename($course) {
        if(!isset($course->fullname)) {
            return false;
        }

        preg_match('/.+-([0-9]{1,5})-.+/', $course->fullname, $matches);

        if (count($matches) != 2) {
            return false;
        }

        return $matches[1];
    }


/**
 * Get a list of the avaialble templates for a given course.
 *
 * @uses $CFG
 * @uses $USER
 * @param object $course The course database object.
 * @return array An array of course template information.
 */
    function course_get_avail_templates($course) {
        global $CFG, $USER;

        $return = array();
        $cats   = array();

    /// Fetch the courses that the user has direct access to.
        $fields     = array('id', 'category', 'fullname', 'shortname');
        $accessinfo = isset($USER->access) ? $USER->access : get_user_access_sitewide($USER->id);
        $courses    = get_user_courses_bycap($USER->id, 'moodle/course:update', $accessinfo, false,
                                             'c.sortorder ASC', $fields);

        $cids = array();

        if (!empty($courses)) {
            foreach ($courses as $crs) {
                $cids[] = $crs->id;
            }
        }

        $sql = "SELECT c.id, c.category, c.fullname, c.shortname, 1 as shared
                FROM {$CFG->prefix}course c
                INNER JOIN {$CFG->prefix}block_admin_share cs ON cs.courseid = c.id
                WHERE c.shortname = '{$course->shortname}'
                AND cs.userid != {$USER->id}";

        if (!empty($cids)) {
            $sql .= ' AND c.id NOT IN (' . implode(', ', $cids) . ')';
        }

        $sql .= ' ORDER BY c.sortorder ASC';

        if ($shares = get_records_sql($sql)) {
            array_merge($courses, $shares);
        }

        if (!empty($courses)) {
            foreach ($courses as $crs) {
                if (SHARE_RESTRICT_LISTING) {
                    if ($crs->shortname != $course->shortname || !course_using_template($crs->id) ||
                        $course->id == $crs->id) {

                        continue;
                    }
                } else {
                    if ($crs->shortname != $course->shortname || $course->id == $crs->id) {
                        continue;
                    }
                }

                if (!isset($cats[$crs->category])) {
                    $cats[$crs->category] = get_field('course_categories', 'name', 'id', $crs->category);
                }

                $crs->catname     = $cats[$crs->category];
                $crs->crn         = get_crn_from_coursename($crs);
                $crs->displayname = (isset($crs->shared) ?
                                     '<i>' . $crs->catname . ' ' . $crs->shortname . ' ' . ' CRN ' . $crs->crn . '</i>' :
                                     $crs->catname . ' ' . $crs->shortname . ' ' . ' CRN ' . $crs->crn);

                $return[] = $crs;
            }
        }

        if (!empty($return)) {
            usort($return, 'sort_template_courses');
        }

        return $return;
    }


/**
 * Get a list of templates available to a user in the 'other category'
 *
 * @param int $cid The course ID the user is looking for a template for.
 * @param int $uid The user ID to get "other" templates for.
 * @return array
 */
    function get_other_templates($cid, $uid) {
        global $CFG;

        $templates = array();


    /// If the user is an admin, select all available templates.
        if (has_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM, SITEID), $uid)) {
            $sql = "SELECT DISTINCT(c.shortname), c.id
                    FROM {$CFG->prefix}course c
                    WHERE c.id NOT IN (" . SITEID . ", {$cid})
                    ORDER BY c.shortname ASC";

    /// Otherwise, pick the courses the user has taught and any shared courses.
        } else {
            $shareids = array();

            if ($shares = get_records('block_admin_share', '', '', 'id,courseid')) {
                foreach ($shares as $share) {
                    if (!in_array($share->courseid, $shareids)) {
                        $shareids[] = $share->courseid;
                    }
                }
            }

            $sql = "SELECT DISTINCT(c.shortname), c.id
                    FROM {$CFG->prefix}course c
                    INNER JOIN {$CFG->prefix}context ct ON ct.instanceid = c.id
                    INNER JOIN {$CFG->prefix}role_assignments ra ON  ra.contextid = ct.id
                    INNER JOIN {$CFG->prefix}role_capabilities rc ON rc.roleid = ra.roleid
                    WHERE c.id NOT IN (" . SITEID . ", $cid)
                    AND (ct.contextlevel = " . CONTEXT_COURSE . "
                    AND ra.userid = $uid
                    AND rc.capability = 'moodle/course:update'
                    AND rc.permission = 1) ";

            if (!empty($shareids)) {
                 if (count($shareids) === 1) {
                    $sql .= 'OR c.id = ' . $shareids[0] . ' ';
                 } else {
                    $sql .= 'OR c.id IN (' . implode(',', $shareids) . ') ';
                 }
            }

            $sql .= 'ORDER BY c.shortname ASC';
        }

        if ($courses = get_records_sql($sql)) {
            foreach ($courses as $course) {
                $templates[$course->id] = $course->shortname;
            }
        }

        return $templates;
    }


/**
 * Get a list of all the CRNs that are associated with
 */
    function course_get_crns($cid) {
        $return = array();

        if (!$course = get_record('course', 'id', $cid, '', '', '', '', 'id, shortname')) {
            return $return;
        }

        if ($courses = get_records_select('course', 'shortname LIKE \'' . $course->shortname . '\'',
                                          'sortorder ASC', 'id, category, fullname, shortname')) {

            $cats = array();

            foreach ($courses as $crs) {
				if (SHARE_RESTRICT_LISTING) {
                    if (!course_using_template($crs->id)) {
                        continue;
                    }
                }

                if (!isset($cats[$crs->category])) {
                    $cats[$crs->category] = get_field('course_categories', 'name', 'id', $crs->category);
                }

                $crs->catname     = $cats[$crs->category];
                $crs->crn         = get_crn_from_coursename($crs);
                $crs->displayname = $crs->shortname . ' ' . $crs->catname . ' ' . ' CRN ' . $crs->crn;

                $return[] = $crs;
            }
        }

        if (!empty($return)) {
            usort($return, 'sort_crns');
        }

        return $return;
    }


/**
 * Sorting function for a list of courses (used with the
 * course_get_avail_templates() function).
 *
 * Note: this function is passed into usort().
 */
    function sort_template_courses($a, $b) {
        $cmp = strcmp($a->shortname, $b->shortname);

        if ($cmp < 0) {
            return -1;
        } else if ($cmp > 1) {
            return 1;
        }

        $aparts = explode(' ', $a->catname);
        $bparts = explode(' ', $b->catname);

        if (count($aparts) != 2 && count($bparts) != 2) {
            $cmp = strcmp($a->catname, $b->catname);

            if ($cmp < 0) {
                return -1;
            } else if ($cmp > 1) {
                return 1;
            }

        } else {
            if ($aparts[1] > $bparts[1]) {
                return 1;
            } else if ($aparts[1] < $bparts[1]) {
                return -1;
            }

            if ($aparts[0] == $bparts[0]) {
                return 0;
            }

            if ($aparts[0] == 'Spring' || $bparts[0] == 'Winter') {
                return -1;
            } else if ($aparts[0] == 'Winter' || $bparts[0] == 'Spring') {
                return 1;
            } else if ($aparts[0] == 'Summer') {
                switch($bparts[0]) {
                    case 'Spring':
                        return 1;
                        break;

                    case 'Fall':
                    case 'Winter':
                        return -1;
                        break;
                }
            } else if ($aparts[0] == 'Fall') {
                switch ($bparts[0]) {
                    case 'Spring':
                    case 'Summer':
                        return 1;
                        break;

                    case 'Winter':
                        return -1;
                        break;
                }
            }
        }

        if ($a->crn == $b->crn) {
            return 0;
        }

        return ($a->crn > $b->crn) ? -1 : 1;
    }


/**
 * Sorting function for a list of associated course CRNs (used with the
 * course_get_crns() function).
 *
 * Note: this function is passed into usort().
 */
    function sort_crns($a, $b) {
        $cmp = strcmp($a->shortname, $b->shortname);

        if ($cmp < 0) {
            return -1;
        } else if ($cmp > 1) {
            return 1;
        }

        $aparts = explode(' ', $a->catname);
        $bparts = explode(' ', $b->catname);

        if (count($aparts) != 2 && count($bparts) != 2) {
            $cmp = strcmp($a->catname, $b->catname);

            if ($cmp < 0) {
                return -1;
            } else if ($cmp > 1) {
                return 1;
            }

        } else {
            if ($aparts[1] > $bparts[1]) {
                return 1;
            } else if ($aparts[1] < $bparts[1]) {
                return -1;
            }

            if ($aparts[0] == $bparts[0]) {
                return 0;
            }

            if ($aparts[0] == 'Spring' || $bparts[0] == 'Winter') {
                return -1;
            } else if ($aparts[0] == 'Winter' || $bparts[0] == 'Spring') {
                return 1;
            } else if ($aparts[0] == 'Summer') {
                switch($bparts[0]) {
                    case 'Spring':
                        return 1;
                        break;

                    case 'Fall':
                    case 'Winter':
                        return -1;
                        break;
                }
            } else if ($aparts[0] == 'Fall') {
                switch ($bparts[0]) {
                    case 'Spring':
                    case 'Summer':
                        return 1;
                        break;

                    case 'Winter':
                        return -1;
                        break;
                }
            }
        }

        if ($a->crn == $b->crn) {
            return 0;
        }

        return ($a->crn > $b->crn) ? -1 : 1;
    }


/**
 * @see /lib/moodlelib.php - remove_course_contents()
 * - $showfeedback is always false
 * - don't let local code delete its reference to the course
 * - don't delete course blocks
 * - don't delete groups, members, etc
 * - don't delete related records from: event, log, course_sections,
 *   course_modules, backup_courses, user_lastaccess, backup_log
 * - don't clean up metacourse stuff
 * - don't delete questions and question categories
 * - don't remove data from gradebook
 */
    function empty_course_contents($courseid) {
        global $CFG;
        require_once($CFG->libdir.'/questionlib.php');
        require_once($CFG->libdir.'/gradelib.php');

        if (! $course = get_record('course', 'id', $courseid)) {
            error('Course ID was incorrect (can\'t find it)');
        }

        $result = true;

    /// Delete every instance of every module
        if ($allmods = get_records('modules') ) {
            foreach ($allmods as $mod) {
                $modname = $mod->name;
                $modfile = $CFG->dirroot .'/mod/'. $modname .'/lib.php';
                $moddelete = $modname .'_delete_instance';       // Delete everything connected to an instance
                $moddeletecourse = $modname .'_delete_course';   // Delete other stray stuff (uncommon)
                $count=0;
                if (file_exists($modfile)) {
                    include_once($modfile);
                    if (function_exists($moddelete)) {
                        if ($instances = get_records($modname, 'course', $course->id)) {
                            foreach ($instances as $instance) {
                                if ($cm = get_coursemodule_from_instance($modname, $instance->id, $course->id)) {
                                    /// Delete activity context questions and question categories
                                    question_delete_activity($cm,  false);
                                }
                                if ($moddelete($instance->id)) {
                                    $count++;

                                } else {
                                    notify('Could not delete '. $modname .' instance '. $instance->id .' ('. format_string($instance->name) .')');
                                    $result = false;
                                }
                                if ($cm) {
                                    // delete cm and its context in correct order
                                    delete_records('course_modules', 'id', $cm->id);
                                    delete_context(CONTEXT_MODULE, $cm->id);
                                }
                            }
                        }
                    } else {
                        notify('Function '.$moddelete.'() doesn\'t exist!');
                        $result = false;
                    }

                    if (function_exists($moddeletecourse)) {
                        $moddeletecourse($course, false);
                    }
                }
            }
        } else {
            error('No modules are installed!');
        }

    /// Give local code a chance to delete its references to this course.
        require_once($CFG->libdir . '/locallib.php');
        notify_local_delete_course($courseid, false);

        return $result;
    }


/**
 * Determine if the given course is queued to be built using a template.
 *
 * @param int $cid The course ID.
 * @return bool Whether the course is queued to be built.
 */
    function course_is_queued($cid) {
        return record_exists('block_admin_rollover_queue', 'courseid', $cid);
    }


/**
 * Queue the course content rollover operation to perform later.
 *
 * @param int $from The course ID we are taking content from.
 * @param int $to   The course ID we are moving content to.
 * @return bool True on success, False otherwise.
 */
    function queue_content_rollover($from, $to) {
        global $CFG, $USER;

        require_once $CFG->libdir . '/dmllib.php';

        // remove any pending rollover tasks for the destination course
        delete_records('block_admin_rollover_queue', 'courseid', $to);

        $record = new stdClass;
        $record->courseid = $to;
        $record->templateid = $from;
        $record->userid = $USER->id;
        $record->timerequested = time();
        return insert_record('block_admin_rollover_queue', $record);
    }


/**
 * Fetch a record from the rollover queue and process it.
 *
 * @param int $id The ID of the request to process.
 * @return bool True on success, False otherwise.
 */
    function process_queue($id=FALSE) {
        global $CFG, $USER;
        require_once $CFG->dirroot . '/message/lib.php';

        begin_sql();

        if ($id) {
            $task = get_record('block_admin_rollover_queue', 'id', $id);
            if (!$task) {
                commit_sql();
                notify(get_string('queue_notfound', 'rollover'), 'notifyproblem errorbox');
                return FALSE;
            }
        } else {
            $task = get_record('block_admin_rollover_queue', '', '');
            if (!$task) {
                commit_sql();
                return FALSE;
            }
        }
        print_heading(get_string('run_queue_record', 'rollover', $task));
        flush();
        // make sure we don't have multiple entries for the course
        $status = delete_records('block_admin_course_template', 'courseid', $task->courseid);

        $status = $status && delete_records('block_admin_rollover_queue', 'id' ,$task->id);
        $status = $status && content_rollover($task->templateid, $task->courseid);

        $trec = new stdClass;
        $trec->courseid     = $task->courseid;
        $trec->templateid   = $task->templateid;
        $trec->timemodified = time();
        $status = $status && ($trec->id = insert_record('block_admin_course_template', $trec));

        if ($status) {
            commit_sql();
            $touser     = get_record('user', 'id', $task->userid);
            $coursename = get_field('course', 'fullname', 'id', $task->courseid);

            $a = new stdClass;
            $a->coursename = get_field('course', 'fullname', 'id', $task->courseid);
            $a->link       = '<a href="' . $CFG->wwwroot . '/course/view.php?id=' . $task->courseid . '">' .
                             $coursename . '</a>';

            print_heading(get_string('rollover_message', 'rollover', $a));

            message_post_message($USER, $touser, get_string('rollover_message', 'rollover', $a), FORMAT_HTML, 'direct');
            notify(get_string('success'), 'notifysuccess');
            return TRUE;
        } else {
            rollback_sql();
            notify(get_string('rollover_failure', 'rollover'), 'notifyproblem errorbox');
            return FALSE;
        }
    }


/**
 * Blank out a specific warning message in output buffer (generated by core moodle code) that scares users
 *
 * @param string $buffer The captured buffer output to act on
 * @return string $buffer The modified buffer output
 */
    function callback_for_upgrade_backup_db($buffer)
    {
        $warning_msg = 'WARNING!!!  The code you are using is OLDER than the version that made these databases!';
        return (str_replace($warning_msg, '', $buffer));
    }


/**
 * Refactored code from SBCC
 * Actually perform the course content rollover operation.
 * Rollover will restore into a new blank course
 *
 * @param int $from The course ID we are taking content from.
 * @return bool True on success, False otherwise.
 */
    function content_rollover($from, $startdate=0) {
        global $CFG;

        require_once $CFG->dirroot . '/backup/lib.php';
        require_once $CFG->dirroot . '/backup/backuplib.php';
        require_once $CFG->libdir . '/blocklib.php';
        require_once $CFG->libdir . '/adminlib.php';
        require_once $CFG->libdir . '/xmlize.php';
        require_once $CFG->dirroot . '/course/lib.php';
        require_once $CFG->dirroot . '/backup/restorelib.php';
        require_once $CFG->dirroot . '/backup//bb/restore_bb.php';
        require_once $CFG->libdir . '/wiki_to_markdown.php';


    /// Make sure the destination course has the same "format" and structure as the template.
        $coursefrom   = get_record('course', 'id', $from);

    /// Proceed with the content rollover...

    /// Check necessary functions exists.
        backup_required_functions();

    /// Adjust some php variables to the execution of this script
        @ini_set('max_execution_time', '3000');
        if (empty($CFG->extramemorylimit)) {
            raise_memory_limit('128M');
        } else {
            raise_memory_limit($CFG->extramemorylimit);
        }

    /// Check backup_version.
        ob_start("callback_for_upgrade_backup_db");
        upgrade_backup_db('curriculum/index.php?s=cur&section=curr');
        ob_end_flush();

        $prefs = array(
            'backup_metacourse'   => 0,
            'backup_users'        => 2,
            'backup_logs'         => 0,
            'backup_user_files'   => 0,
            'backup_course_files' => 1,
            'backup_site_files'   => 1,
            'backup_messages'     => 0
        );

        $errorstr = '';

        if (($filename = rollover_backup_course_silently($from, $prefs, $errorstr)) === false) {
            error($errorstr);
            return false;
        }

        flush();

    /// Handle the import.
        $errorstr = '';

        $prefs = array(
            'restore_metacourse'   => 0,
            'restore_logs'         => 0,
            'restore_site_files'   => 1,
            'restore_course_files' => 1,
            'restore_messages'     => 0,
            'restore_startdate'    => $startdate
        );

        $newcourseid = false;
        if (!$newcourseid = rollover_import_backup_file_silently($filename, 0, false, false, $prefs)) {
            error('Error importing course data');
            return false;
        }

        flush();

    /// Delete the backup file that was created during this process.
        fulldelete($filename);

        return $newcourseid;
    }


    /**
     * This function will restore an entire backup.zip into the specified course
     * using standard moodle backup/restore functions, but silently.
     *
     * @see /backup/lib.php
     * @param string $pathtofile the absolute path to the backup file.
     * @param int $destinationcourse the course id to restore to.
     * @param boolean $emptyfirst whether to delete all coursedata first.
     * @param boolean $userdata whether to include any userdata that may be in the backup file.
     * @param array $preferences optional, 0 will be used.  Can contain:
     *   metacourse
     *   logs
     *   course_files
     *   messages
     */
    function rollover_import_backup_file_silently($backup_unique_code,$destinationcourse,$emptyfirst=false,$userdata=false, $preferences=array()) {
        global $CFG,$SESSION,$USER; // is there such a thing on cron? I guess so..
        global $restore; // ick
        if (empty($USER)) {
            $USER = get_admin();
            $USER->admin = 1; // not sure why, but this doesn't get set
        }

        if (!defined('RESTORE_SILENTLY')) {
            define('RESTORE_SILENTLY', true); // don't output all the stuff to us.
        }

        $debuginfo    = 'import_backup_file_silently: ';
        $cleanupafter = false;
        $errorstr     = ''; // passed by reference to restore_precheck to get errors from.

        // first check we have a valid file.
        /* Skip the file checking stuff, because we're not using a zip file
        if (!file_exists($pathtofile) || !is_readable($pathtofile)) {
            mtrace($debuginfo.'File '.$pathtofile.' either didn\'t exist or wasn\'t readable');
            return false;
        }

        // now make sure it's a zip file
        require_once($CFG->dirroot.'/lib/filelib.php');
        $filename = substr($pathtofile,strrpos($pathtofile,'/')+1);
        $mimetype = mimeinfo("type", $filename);
        if ($mimetype != 'application/zip') {
            mtrace($debuginfo.'File '.$pathtofile.' was of wrong mimetype ('.$mimetype.')' );
            return false;
        }

        // restore_precheck wants this within dataroot, so lets put it there if it's not already..
        if (strstr($pathtofile,$CFG->dataroot) === false) {
            // first try and actually move it..
            if (!check_dir_exists($CFG->dataroot.'/temp/backup/',true)) {
                mtrace($debuginfo.'File '.$pathtofile.' outside of dataroot and couldn\'t move it! ');
                return false;
            }
            if (!copy($pathtofile,$CFG->dataroot.'/temp/backup/'.$filename)) {
                mtrace($debuginfo.'File '.$pathtofile.' outside of dataroot and couldn\'t move it! ');
                return false;
            } else {
                $pathtofile = 'temp/backup/'.$filename;
                $cleanupafter = true;
            }
        } else {
            // it is within dataroot, so take it off the path for restore_precheck.
            $pathtofile = substr($pathtofile,strlen($CFG->dataroot.'/'));
        }
        */

        if (!backup_required_functions()) {
            mtrace($debuginfo.'Required function check failed (see backup_required_functions)');
            return false;
        }

        @ini_set('max_execution_time', '3000');
        if (empty($CFG->extramemorylimit)) {
            raise_memory_limit('128M');
        } else {
            raise_memory_limit($CFG->extramemorylimit);
        }

        /*if (!$backup_unique_code = restore_precheck($destinationcourse,$pathtofile,$errorstr,true)) {*/
        //if (!$backup_unique_code = restore_precheck(/*NOT NEEDED*/0,$pathtofile,$errorstr,true)) {
        //    mtrace($debuginfo.'Failed restore_precheck (error was '.$errorstr.')');
        //    return false;
        //}

        // RL: the following few lines are normally handled by restore_precheck
        $xml_file  = $CFG->dataroot."/temp/backup/".$backup_unique_code."/moodle.xml";
        //Reading info from file
        $info = restore_read_xml_info ($xml_file);
        //Reading course_header from file
        $course_header = restore_read_xml_course_header ($xml_file);

        if(!is_object($course_header)){
            // ensure we fail if there is no course header
            $course_header = false;
        }
        $SESSION->info = $info;
        $SESSION->course_header = $course_header;

        $SESSION->restore = new StdClass;

        // add on some extra stuff we need...
        $SESSION->restore->metacourse   = $restore->metacourse = (isset($preferences['restore_metacourse']) ? $preferences['restore_metacourse'] : 0);
        $SESSION->restore->users        = $restore->users = $userdata;
        $SESSION->restore->logs         = $restore->logs = (isset($preferences['restore_logs']) ? $preferences['restore_logs'] : 0);
        $SESSION->restore->user_files   = $restore->user_files = $userdata;
        $SESSION->restore->messages     = $restore->messages = (isset($preferences['restore_messages']) ? $preferences['restore_messages'] : 0);
        //$SESSION->restore->restoreto    = 0; // Make sure we delete content and add everything from the source course.
        $SESSION->restore->restoreto    = RESTORETO_NEW_COURSE;
        $SESSION->restore->course_id    = $restore->course_id = $destinationcourse;
        $SESSION->restore->deleting     = $emptyfirst;
        $SESSION->restore->restore_course_files = $restore->course_files = (isset($preferences['restore_course_files']) ? $preferences['restore_course_files'] : 0);
        $SESSION->restore->restore_site_files = $restore->restore_site_files = (isset($preferences['restore_site_files']) ? $preferences['restore_site_files'] : 0);
        $SESSION->restore->backup_version = $SESSION->info->backup_backup_version;

        // If a start date was specified, determine the difference between the start date of the template course
        // and the one specified for use in module dates.
        if (!empty($preferences['restore_startdate'])) {
            $SESSION->restore->course_startdateoffset = $preferences['restore_startdate'] - $SESSION->course_header->course_startdate;
        } else {
            $SESSION->restore->course_startdateoffset = 0;
        }

        // Set restore groups to 0
        $SESSION->restore->groups                   = $restore->groups = RESTORE_GROUPS_NONE;
        // Set restore cateogry to 0, restorelib.php will look in the backup xml file
        $SESSION->restore->restore_restorecatto     = $restore->restore_restorecatto = 0;
        $SESSION->restore->blogs                    = $restore->blogs = 0;

        restore_setup_for_check($SESSION->restore,$backup_unique_code);

        // maybe we need users (defaults to 2 in restore_setup_for_check)

/*        if (!empty($userdata)) {
            $SESSION->restore->users = 1;
        }
*/
        // we also need modules...
        if ($allmods = get_records('modules')) {
            foreach ($allmods as $mod) {
                $modname = $mod->name;
                //Now check that we have that module info in the backup file
                if (isset($SESSION->info->mods[$modname]) && $SESSION->info->mods[$modname]->backup == "true") {
                    $SESSION->restore->mods[$modname]->restore = true;
                    $SESSION->restore->mods[$modname]->userinfo = $userdata;
                }
                else {
                    // avoid warnings
                    $SESSION->restore->mods[$modname]->restore = false;
                    $SESSION->restore->mods[$modname]->userinfo = false;
                }
            }
        }
        $restore = clone($SESSION->restore);

        if (!restore_execute($restore,$SESSION->info,$SESSION->course_header,$errorstr)) {
            mtrace($debuginfo.'Failed restore_execute (error was '.$errorstr.')');
            return false;
        }

        rebuild_course_cache($SESSION->restore->course_id);

        return $SESSION->restore->course_id;
    }


/**
 * @see /course/lib.php - create_course
 * - empty course contents, and clear backups
 * - doesn't create new course records
 * - doesn't change sort order
 * - doesn't update restricted modules
 */
    function rebuild_course($cid) {
        global $CFG;

        require_once($CFG->dirroot.'/backup/lib.php');
        require_once($CFG->libdir.'/pagelib.php');

        empty_course_contents($cid);
        delete_dir_contents($CFG->dataroot . '/' . $cid, 'backupdata');

        $course = get_record('course', 'id', $cid);

        // Setup the blocks
        $page = page_create_object(PAGE_COURSE_VIEW, $cid);
        blocks_repopulate_page($page); // Return value not checked because you can always edit later
    }


/**
     * Function to backup an entire course silently and create a zipfile.
     *
     * @param int $courseid the id of the course
     * @param array $prefs see {@link backup_generate_preferences_artificially}
     */
    function rollover_backup_course_silently($courseid, $prefs, &$errorstring) {
        global $CFG, $preferences; // global preferences here because something else wants it :(
        if (!defined('BACKUP_SILENTLY')) {
            define('BACKUP_SILENTLY', 1);
        }
        if (!$course = get_record('course', 'id', $courseid)) {
            debugging("Couldn't find course with id $courseid in backup_course_silently");
            return false;
        }
        $preferences = rollover_backup_generate_preferences_artificially($course, $prefs);
        $preferences->elis_skip_zip = true;
        if (backup_execute($preferences, $errorstring)) {
            return $preferences->backup_unique_code;
        }
        else {
            return false;
        }
    }


    /**
     * Function to generate the $preferences variable that
     * backup uses.  This will back up all modules and instances in a course.
     *
     * @param object $course course object
     * @param array $prefs can contain:
            backup_metacourse
            backup_users
            backup_logs
            backup_user_files
            backup_course_files
            backup_site_files
            backup_messages
     * and if not provided, they will not be included.
     */
    function rollover_backup_generate_preferences_artificially($course, $prefs) {
        global $CFG;
        $preferences = new StdClass;
        $preferences->backup_unique_code = time();
        $preferences->backup_name = backup_get_zipfile_name($course, $preferences->backup_unique_code);
        $count = 0;

        if ($allmods = get_records("modules") ) {
            foreach ($allmods as $mod) {
                $modname = $mod->name;
                $modfile = "$CFG->dirroot/mod/$modname/backuplib.php";
                $modbackup = $modname."_backup_mods";
                $modbackupone = $modname."_backup_one_mod";
                $modcheckbackup = $modname."_check_backup_mods";
                if (!file_exists($modfile)) {
                    continue;
                }
                include_once($modfile);
                if (!function_exists($modbackup) || !function_exists($modcheckbackup)) {
                    continue;
                }
                $var = "exists_".$modname;
                $preferences->$var = true;
                $count++;
                // check that there are instances and we can back them up individually
                if (!count_records('course_modules','course',$course->id,'module',$mod->id) || !function_exists($modbackupone)) {
                    continue;
                }
                $var = 'exists_one_'.$modname;
                $preferences->$var = true;
                $varname = $modname.'_instances';
                $preferences->$varname = get_all_instances_in_course($modname, $course, NULL, true);
                $instancestopass = array();
                $countinstances = 0;
                foreach ($preferences->$varname as $instance) {
                    $preferences->mods[$modname]->instances[$instance->id]->name = $instance->name;
                    $var = 'backup_'.$modname.'_instance_'.$instance->id;
                    $preferences->$var = true;
                    $preferences->mods[$modname]->instances[$instance->id]->backup = true;
                    $var = 'backup_user_info_'.$modname.'_instance_'.$instance->id;
                    $preferences->$var = false;
                    $preferences->mods[$modname]->instances[$instance->id]->userinfo = false;
                    $var = 'backup_'.$modname.'_instances';
                    $preferences->$var = 1; // we need this later to determine what to display in modcheckbackup.

                    $var1 = 'backup_'.$modname.'_instance_'.$instance->id;
                    $var2 = 'backup_user_info_'.$modname.'_instance_'.$instance->id;
                    if (!empty($preferences->$var1)) {
                        $obj = new StdClass;
                        $obj->name = $instance->name;
                        $obj->userdata = $preferences->$var2;
                        $obj->id = $instance->id;
                        $instancestopass[$instance->id]= $obj;
                        $countinstances++;
                    }
                }

                $modcheckbackup($course->id,$preferences->$varname,$preferences->backup_unique_code,$instancestopass);

                //Check data
                //Check module info
                $preferences->mods[$modname]->name = $modname;

                $var = "backup_".$modname;
                $preferences->$var = true;
                $preferences->mods[$modname]->backup = true;

                //Check include user info
                $var = "backup_user_info_".$modname;
                $preferences->$var = false;
                $preferences->mods[$modname]->userinfo = false;

            }
        }

        //Check other parameters
        $preferences->backup_metacourse = (isset($prefs['backup_metacourse']) ? $prefs['backup_metacourse'] : 0);
        $preferences->backup_users = (isset($prefs['backup_users']) ? $prefs['backup_users'] : 0);
        $preferences->backup_logs = (isset($prefs['backup_logs']) ? $prefs['backup_logs'] : 0);
        $preferences->backup_user_files = (isset($prefs['backup_user_files']) ? $prefs['backup_user_files'] : 0);
        $preferences->backup_course_files = (isset($prefs['backup_course_files']) ? $prefs['backup_course_files'] : 0);
        $preferences->backup_site_files = (isset($prefs['backup_site_files']) ? $prefs['backup_site_files'] : 0);
        $preferences->backup_messages = (isset($prefs['backup_messages']) ? $prefs['backup_messages'] : 0);
        $preferences->backup_gradebook_history = (isset($prefs['backup_gradebook_history']) ? $prefs['backup_gradebook_history'] : 0);
        $preferences->backup_blogs = (isset($prefs['backup_blogs']) ? $prefs['backup_blogs'] : 0);
        $preferences->backup_course = $course->id;
        backup_add_static_preferences($preferences);
        return $preferences;
    }


/**
 * Get the default template for a given course.
 *
 * @param int $cid The course ID.
 * @return object|bool The course template database object to use or, False.
 */
    function get_default_template($cid) {
    /// Find a template for the course (if any exists)
        $crn = get_crn_from_coursename($cid);

        if (!$cat = get_record('course_categories', 'id', $course->category)) {
            return false;
        }

        $parts = explode(' ', $cat->name);

        if (count($parts) != 2) {
            return false;
        }

    /// Make sure that the second part of the category name is just a four-digit integer year value.
        if (!preg_match('/^[0-9]{4}$/', $parts[1])) {
            return  false;
        }

    /// Attempt to find a valid template for this CRN by going back through old terms.
        $template = false;

        for ($i = 0; $i < 10; $i++) {  // Only go through up to 10 looking for a template...
            if ($template) {
                continue;
            }

            switch ($parts[0]) {
                case 'Spring':
                case 'Summer':
                case 'Fall':
                case 'Winter':
                    $catname = $parts[0] . ' ' . ($parts[1] - 1);
                    break;

                default:
                    return false;
            }

            if (!$pcat = get_record('course_categories', 'name', $catname)) {
                return false;
            }

            $template = get_record_select('course', "fullname LIKE '%-$crn-%\ AND category = {$pcat->id}");
        }

    /// Fetch the most recent course (that has already started) and that has the same CRN
        if (!$template) {
            return false;
        }

        return $template;
    }

?>
