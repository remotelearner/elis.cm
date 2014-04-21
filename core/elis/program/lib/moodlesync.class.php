<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2014 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @package    elis_program
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2014 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

require_once(__DIR__.'/data/course.class.php');
require_once(__DIR__.'/data/user.class.php');
require_once(__DIR__.'/data/usermoodle.class.php');
require_once(__DIR__.'/data/classmoodlecourse.class.php');
require_once(__DIR__.'/data/pmclass.class.php');
require_once(__DIR__.'/data/student.class.php');
require_once(__DIR__.'/../../../lib/grade/grade_category.php');
require_once(__DIR__.'/../../../lib/grade/grade_item.php');
require_once(__DIR__.'/../../../lib/grade/grade_grade.php');

/**
 * Handles Moodle - ELIS synchronization.
 */
class moodlesync {

    /** @var object Holds the next record to process when processing completion elements in get_elis_coursecompletion_grades */
    protected $completionelementlastrec = null;

    /** @var moodle_recordset Holds the completion element recordset for get_elis_coursecompletion_grades */
    protected $completionelementrecset = null;

    /**
     * Get syncable users.
     *
     * @param int $muserid (Optional) If a valid moodle userid, only get information for this user.
     * @return moodle_recordset|array An iterable of syncable user information.
     */
    public function get_syncable_users($muserid = 0) {
        global $DB, $CFG;

        // If we are filtering for a specific user, add the necessary SQL fragment.
        $userfilter = '';
        $userparams = array();
        if (!empty($muserid)) {
            $userfilter = ' AND u.id = :userid ';
            $userparams['userid'] = $muserid;
        }

        $gbr = explode(',', $CFG->gradebookroles);
        list($gbrsql, $gbrparams) = $DB->get_in_or_equal($gbr, SQL_PARAMS_NAMED);

        // Get all users (or specified user) that are enroled in any Moodle course that is linked to an ELIS class.
        $sql = "SELECT u.id AS muid,
                       u.username AS username,
                       cu.id AS cmid,
                       crs.id AS moodlecourseid,
                       cls.id AS pmclassid,
                       ecrs.id AS pmcourseid,
                       ecrs.completion_grade AS pmcoursecompletiongrade,
                       ecrs.credits AS pmcoursecredits,
                       stu.*
                  FROM {user} u
                  JOIN {role_assignments} ra ON u.id = ra.userid
                  JOIN {context} ctx ON ctx.id = ra.contextid
                  JOIN {".usermoodle::TABLE."} umdl ON umdl.muserid = u.id
                  JOIN {".user::TABLE."} cu ON cu.id = umdl.cuserid
                  JOIN {course} crs ON crs.id = ctx.instanceid
                  JOIN {".classmoodlecourse::TABLE."} cmc ON cmc.moodlecourseid = crs.id
                  JOIN {".pmclass::TABLE."} cls ON cls.id = cmc.classid
                  JOIN {".course::TABLE."} ecrs ON ecrs.id = cls.courseid
             LEFT JOIN {".student::TABLE."} stu ON stu.userid = cu.id AND stu.classid = cls.id
                 WHERE ra.roleid $gbrsql
                       AND ctx.contextlevel = ".CONTEXT_COURSE."
                       AND u.deleted = 0
                       {$userfilter}
              GROUP BY muid, pmclassid
              ORDER BY muid ASC, pmclassid ASC";
        $params = array_merge($gbrparams, $userparams);
        $users = $DB->get_recordset_sql($sql, $params);
        if (!empty($users) && $users->valid() === true) {
            return $users;
        } else {
            return array();
        }
    }

    /**
     * Get completion element grades for a specific user.
     *
     * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
     * !!!!! THIS FUNCTION MUST BE CALLED WITH ASCENDING $muserid AND $pmclassid PARAMETERS. !!!!!
     * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
     *
     * This is a bit fragile but it a neat way to reduce memory usage. On the first run, a recordset will be created containing
     * completion element grades for all users (if $userpool is 0). When we call it again, it will loop through the recordset and
     * only assemble grades for the requested user. Using the recordset this way allows us minimal memory usage and database calls,
     * and is pretty neat!
     *
     * @param int $userpool Either 0 to fetch grades for all users, or a moodle userid to fetch for one user.
     * @param int $muserid The user we want to get grades for right now.
     * @param int $pmclassid The id of the ELIS class we want grades for.
     * @return array Array of student_grade information, indexed by coursecompletion id.
     */
    public function get_elis_coursecompletion_grades($userpool, $muserid, $pmclassid) {
        global $DB;

        if ($this->completionelementrecset === null) {
            $userfilter = '';
            $params = array();

            if (!empty($userpool)) {
                $userfilter = 'WHERE mu.id = :userid ';
                $params['userid'] = $userpool;
            }

            $sql = "SELECT grades.*, mu.id AS muid, grades.classid AS pmclassid
                      FROM {".student_grade::TABLE."} grades
                      JOIN {".user::TABLE."} cu ON grades.userid = cu.id
                      JOIN {".usermoodle::TABLE."} umdl ON umdl.cuserid = cu.id
                      JOIN {user} mu ON mu.id = umdl.muserid
                           {$userfilter}
                  ORDER BY muid ASC, pmclassid ASC";
            $this->completionelementrecset = $DB->get_recordset_sql($sql, $params);
        }

        // Get student's completion elements.
        $cmgrades = array();

        // NOTE: we use a do-while loop, since $last_rec might be set from the last run, so we need to check it before we load
        // from the database.

        // Need to track whether we're on the first record because of how recordsets work.
        $first = true;
        do {
            if (isset($this->completionelementlastrec->muid)) {

                $muiddiff = ($this->completionelementlastrec->muid > $muserid) ? true : false;
                $pmclassiddiff = ($this->completionelementlastrec->muid == $muserid && $this->completionelementlastrec->pmclassid > $pmclassid)
                        ? true : false;
                if ($muiddiff === true || $pmclassiddiff === true) {
                    // We've reached the end of this student's grades in this class.
                    // Property $this->completionelementlastrec will save this record for the next run).
                    break;
                }

                if ($this->completionelementlastrec->muid == $muserid && $this->completionelementlastrec->pmclassid = $pmclassid) {
                    $cmgrades[$this->completionelementlastrec->completionid] = $this->completionelementlastrec;
                }
            }

            if (!$first) {
                // Not using a cached record, so advance the recordset.
                $this->completionelementrecset->next();
            }

            // Obtain the next record.
            $this->completionelementlastrec = $this->completionelementrecset->current();
            // Signal that we are now within the current recordset.
            $first = false;
        } while ($this->completionelementrecset->valid());

        return $cmgrades;
    }

    /**
     * Returns the grade items associated with the courses.
     *
     * @param array $courseids Array of course ids.
     * @return array Array of grade_items for each course level grade item.
     */
    public static function fetch_course_items(array $courseids) {
        global $DB;

        $courseitems = array();

        if (!empty($courseids)) {
            list($courseidssql, $courseidsparams) = $DB->get_in_or_equal($courseids);
            $select = 'courseid '.$courseidssql.' AND itemtype = ?';
            $params = array_merge($courseidsparams, array('course'));
            $rs = $DB->get_recordset_select('grade_items', $select, $params);

            $courseitems = array();
            foreach ($rs as $data) {
                $instance = new grade_item();
                grade_object::set_properties($instance, $data);
                $courseitems[$instance->id] = $instance;
            }
            $rs->close();
        }

        // Determine if any courses were missing.
        $receivedids = array();
        foreach ($courseitems as $item) {
            $receivedids[] = $item->courseid;
        }
        $missingids = array_diff($courseids, $receivedids);

        // Create grade items for any courses that didn't have one.
        if (!empty($missingids)) {
            foreach ($missingids as $courseid) {
                // First get category - it creates the associated grade item.
                $coursecategory = grade_category::fetch_course_category($courseid);
                $gradeitem = $coursecategory->get_grade_item();
                $courseitems[$gradeitem->id] = $gradeitem;
            }
        }
        return $courseitems;
    }

    /**
     * Get an array of moodle courses we would create ELIS class enrolments for, if they did not exist.
     *
     * This returns a list of moodle course ids which are only linked to one ELIS class.
     *
     * @return array Array of moodle courses we would create ELIS class enrolments for, if they did not exist.
     */
    public function get_courses_to_create_enrolments() {
        global $DB;

        $sql = 'SELECT moodlecourseid, count(moodlecourseid) as numentries
                  FROM {'.classmoodlecourse::TABLE.'}
              GROUP BY moodlecourseid';
        $recs = $DB->get_recordset_sql($sql);
        $doenrol = array();
        foreach ($recs as $rec) {
            if ($rec->numentries == 1) {
                $doenrol[$rec->moodlecourseid] = $rec->moodlecourseid;
            }
        }
        return $doenrol;
    }

    /**
     * Create an enrolment record for an ELIS user into an ELIS class.
     *
     * @param int $pmuserid The ELIS userid.
     * @param int $muserid The Moodle userid.
     * @param int $mcourseid The Moodle courseid.
     * @param int $pmclassid The ELIS classid.
     * @param int $timenow (Optional) The time to use as a fallback enrolment time if the enrolment time cannot be determined.
     * @return stdClass The created student object.
     */
    public function create_enrolment_record($pmuserid, $muserid, $mcourseid, $pmclassid, $timenow = null) {
        global $DB;

        if (empty($timenow)) {
            $timenow = time();
        }

        $sturec = new stdClass;
        $sturec->classid = $pmclassid;
        $sturec->userid = $pmuserid;

        // Enrolment time will be the earliest found role assignment for this user.
        $enroltime = $timenow;
        $enrolments = $DB->get_recordset('enrol', array('courseid' => $mcourseid));
        foreach ($enrolments as $enrolment) {
            $etime = $DB->get_field('user_enrolments', 'timestart', array('enrolid' => $enrolment->id, 'userid'  => $muserid));
            if (!empty($etime) && $etime < $enroltime) {
                $enroltime = $etime;
            }
        }
        unset($enrolments);

        $sturec->enrolmenttime = $enroltime;
        $sturec->completetime = 0;
        $sturec->endtime = 0;
        $sturec->completestatusid = student::STUSTATUS_NOTCOMPLETE;
        $sturec->grade = 0;
        $sturec->credits = 0;
        $sturec->locked = 0;
        $sturec->id = $DB->insert_record(student::TABLE, $sturec);
        return $sturec;
    }

    /**
     * Get grade items and completion elements for elis and moodle courses that are linked together.
     * @return array An array of grade items (index 0) and elis completion elements (index 1).
     *               Each are arrays of grade item/course completions, sorted by moodle course id, and pm course id.
     */
    public function get_grade_and_completion_elements() {
        global $DB;
        $sql = 'SELECT cmp.id as cmpid,
                       cmp.courseid AS pmcourseid,
                       cmp.completion_grade cmpcompletiongrade,
                       cmc.moodlecourseid AS moodlecourseid,
                       gi.id AS giid,
                       gi.grademax AS gigrademax
                  FROM {'.coursecompletion::TABLE.'} cmp
                  JOIN {'.pmclass::TABLE.'} cls ON cls.courseid = cmp.courseid
                  JOIN {'.classmoodlecourse::TABLE.'} cmc ON cmc.classid = cls.id
             LEFT JOIN {course_modules} crsmod ON crsmod.idnumber = cmp.idnumber
             LEFT JOIN {grade_items} gi ON gi.courseid = cmc.moodlecourseid
                       AND (gi.idnumber = cmp.idnumber OR gi.idnumber = crsmod.id)';
        $data = $DB->get_recordset_sql($sql);
        $gis = array();
        $linkedcompelems = array();
        $compelems = array();
        foreach ($data as $rec) {
            if (!empty($rec->giid)) {
                $gis[$rec->moodlecourseid][$rec->giid] = (object)array(
                    'id' => $rec->giid,
                    'grademax' => $rec->gigrademax
                );
                $linkedcompelems[$rec->pmcourseid][$rec->giid] = (object)array(
                    'id' => $rec->cmpid,
                    'completion_grade' => $rec->cmpcompletiongrade
                );
            }

            $compelems[$rec->pmcourseid][$rec->cmpid] = (object)array(
                'id' => $rec->cmpid,
            );
        }

        return array($gis, $linkedcompelems, $compelems);
    }

    /**
     * Sync the course grade from Moodle to ELIS.
     *
     * @param object $sturec The ELIS student record.
     * @param grade_item $coursegradeitem The Moodle course grade_item object.
     * @param grade_grade $coursegradegrade The Moodle user's grade data for the course grade item.
     * @param array $compelements Array of ELIS course completion elements.
     * @param int $completiongrade The completion grade for this course.
     * @param int $credits The number of credits for this course.
     * @param int $timenow The time to set the student complete time to if they are passed and don't have one set already.
     */
    public function sync_coursegrade($sturec, $coursegradeitem, $coursegradegrade, $compelements, $completiongrade, $credits, $timenow) {
        global $DB;

        if (isset($sturec->id) && !$sturec->locked && $coursegradegrade->finalgrade !== null) {
            // Clone of student record, to see if we actually change anything.
            $oldsturec = clone($sturec);

            $sturec->grade = $this->get_scaled_grade($coursegradegrade, $coursegradeitem->grademax);

            // Update completion status if all that is required is a course grade.
            if (empty($compelements) && $sturec->grade >= $completiongrade) {
                $sturec->completetime = $coursegradegrade->get_dategraded();
                $sturec->completestatusid = student::STUSTATUS_PASSED;
                $sturec->credits = floatval($credits);
            } else {
                $sturec->completetime = 0;
                $sturec->completestatusid = student::STUSTATUS_NOTCOMPLETE;
                $sturec->credits = 0;
            }

            // Only update if we actually changed anything.
            // (exception: if the completetime gets smaller, it's probably because $coursegradegrade->get_dategraded()
            // returned an empty value, so ignore that change).
            if ($oldsturec->grade != $sturec->grade
                    || $oldsturec->completetime < $sturec->completetime
                    || $oldsturec->completestatusid != $sturec->completestatusid
                    || $oldsturec->credits != $sturec->credits) {

                if ($sturec->completestatusid == student::STUSTATUS_PASSED && empty($sturec->completetime)) {
                    // Make sure we have a valid complete time, if we passed.
                    $sturec->completetime = $timenow;
                }

                $DB->update_record(student::TABLE, $sturec);
            }
        }
    }

    /**
     * Convert a grade_grade's finalgrade into a percent based on the associated grade_item's maxgrade.
     *
     * @param grade_grade $gradegrade The grade_grade object.
     * @param int $grademax The maximum grade for the grade_item.
     * @return float The resulting percent grade.
     */
    public function get_scaled_grade(grade_grade $gradegrade, $grademax) {
        // Ignore mingrade for now... Don't really know what to do with it.
        if ($gradegrade->finalgrade >= $grademax) {
            $gradepercent = 100;
        } else if ($gradegrade->finalgrade <= 0) {
            $gradepercent = 0;
        } else {
            $gradepercent = (($gradegrade->finalgrade / $grademax) * 100.0);
        }
        return $gradepercent;
    }

    /**
     * Sync moodle non-course grade_items to ELIS coursecompletion elements.
     *
     * @param object $causer The current user information being processed (w/ associated info). (@see get_syncable_users)
     * @param array $gis Array of moodle grade_item information. (@see get_grade_and_completion_elements)
     * @param array $compelements Array of ELIS coursecompletion information. (@see get_grade_and_completion_elements)
     * @param array $moodlegrades Array of moodle grade_grade objects, indexed by associated grade_item id.
     * @param array $cmgrades Array of ELIS student_grade information.
     * @param int $timenow The current time.
     */
    public function sync_completionelements($causer, $gis, $compelements, $moodlegrades, $cmgrades, $timenow) {
        global $DB;

        foreach ($compelements as $giid => $coursecompletion) {
            if (!isset($moodlegrades[$giid]->finalgrade) || !isset($gis[$giid])) {
                continue;
            }
            // Calculate Moodle grade as a percentage.
            $gradeitemgrade = $moodlegrades[$giid];
            $gradepercent = $this->get_scaled_grade($gradeitemgrade, $gis[$giid]->grademax);

            if (isset($cmgrades[$coursecompletion->id])) {
                // Update existing completion element grade.
                $studentgrade = $cmgrades[$coursecompletion->id];
                if (!$studentgrade->locked && ($gradeitemgrade->get_dategraded() > $studentgrade->timegraded)) {

                    // Clone of record, to see if we actually change anything.
                    $oldgrade = clone($studentgrade);

                    $studentgrade->grade = $gradepercent;
                    $studentgrade->timegraded = $gradeitemgrade->get_dategraded();
                    // If completed, lock it.
                    $studentgrade->locked = ($studentgrade->grade >= $coursecompletion->completion_grade) ? 1 : 0;

                    // Only update if we actually changed anything.
                    if ($oldgrade->grade != $studentgrade->grade
                            || $oldgrade->timegraded != $studentgrade->timegraded
                            || $oldgrade->grade != $studentgrade->grade
                            || $oldgrade->locked != $studentgrade->locked) {

                        $studentgrade->timemodified = $timenow;
                        $DB->update_record(student_grade::TABLE, $studentgrade);
                    }
                }
            } else {
                // No completion element grade exists: create a new one.
                $studentgrade = new stdClass;
                $studentgrade->classid = $causer->pmclassid;
                $studentgrade->userid = $causer->cmid;
                $studentgrade->completionid = $coursecompletion->id;
                $studentgrade->grade = $gradepercent;
                $studentgrade->timegraded = $gradeitemgrade->get_dategraded();
                $studentgrade->timemodified = $timenow;
                // If completed, lock it.
                $studentgrade->locked = ($studentgrade->grade >= $coursecompletion->completion_grade) ? 1 : 0;
                $DB->insert_record(student_grade::TABLE, $studentgrade);
            }
        }
    }

    /**
     * Get grade_grade objects for a user in moodle course.
     *
     * @param int $muserid The moodle user ID.
     * @param int $moodlecourseid The moodle course ID.
     * @param array $gis Array of grade_items to get grade data for.
     * @return array Array of grade_grade objects, indexed by associated grade_item id.
     */
    public function get_moodlegrades($muserid, $moodlecourseid, $gis) {
        global $DB, $CFG;

        if (empty($gis)) {
            return array();
        }

        $graderecords = array();
        $params = array();

        list($gisql, $giparams) = $DB->get_in_or_equal(array_keys($gis), SQL_PARAMS_NAMED, 'items');

        $gradessql = 'SELECT *
                        FROM {grade_grades} grade
                       WHERE userid = :muserid AND itemid '.$gisql.'
                    ORDER BY itemid ASC';
        $params = array_merge(array('muserid' => $muserid), $giparams);
        $graderecordstmp = $DB->get_recordset_sql($gradessql, $params);
        $graderecords = array();
        foreach ($graderecordstmp as $i => $record) {
            $graderecords[$record->itemid] = $record;
        }
        unset($graderecordstmp);

        $grades = array();
        foreach ($gis as $gradeitem) {
            if (isset($graderecords[$gradeitem->id])) {
                $grades[$gradeitem->id] = new grade_grade($graderecords[$gradeitem->id], false);
            } else {
                $grades[$gradeitem->id] = new grade_grade(array('userid' => $muserid, 'itemid' => $gradeitem->id), false);
            }
        }
        return $grades;
    }

    /**
     * Synchronize users from Moodle to ELIS.
     *
     * @param int $requestedmuserid A moodle userid to sync. If 0, syncs all available.
     */
    public function synchronize_moodle_class_grades($requestedmuserid = 0) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/grade/lib.php');
        require_once(elispm::lib('data/classmoodlecourse.class.php'));

        set_time_limit(0);
        $timenow = time();

        $causers = $this->get_syncable_users($requestedmuserid);
        if (empty($causers)) {
            return false;
        }

        // Get moodle course ids.
        $moodlecourseidsorig = $DB->get_recordset(classmoodlecourse::TABLE);
        $moodlecourseids = array();
        foreach ($moodlecourseidsorig as $i => $rec) {
            $moodlecourseids[] = $rec->moodlecourseid;
        }
        unset($moodlecourseidsorig);

        // Regrade each course.
        foreach ($moodlecourseids as $moodlecourseid) {
            grade_regrade_final_grades($moodlecourseid);
        }

        // Get course grade items and index by courseid.
        $coursegradeitemsorig = static::fetch_course_items($moodlecourseids);
        $coursegradeitems = array();
        foreach ($coursegradeitemsorig as $i => $item) {
            $coursegradeitems[$item->courseid] = $item;
            unset($coursegradeitemsorig[$i]);
        }

        $doenrol = $this->get_courses_to_create_enrolments();

        list($allgis, $alllinkedcompelements, $allcompelements) = $this->get_grade_and_completion_elements();

        foreach ($causers as $causer) {
            $gis = (isset($allgis[$causer->moodlecourseid])) ? $allgis[$causer->moodlecourseid] : array();
            $linkedcompelements = (isset($alllinkedcompelements[$causer->pmcourseid])) ? $alllinkedcompelements[$causer->pmcourseid] : array();
            $compelements = (isset($allcompelements[$causer->pmcourseid])) ? $allcompelements[$causer->pmcourseid] : array();

            $coursegradeitem = $coursegradeitems[$causer->moodlecourseid];
            $gis[$coursegradeitem->id] = $coursegradeitem;

            if ($coursegradeitem->grademax == 0) {
                // No maximum course grade, so we can't calculate the student's grade.
                continue;
            }

            // If no enrolment record in ELIS, let's set one.
            if (empty($causer->id)) {
                if (!isset($doenrol[$causer->moodlecourseid])) {
                    continue;
                }
                $sturec = $this->create_enrolment_record($causer->cmid, $causer->muid, $causer->moodlecourseid, $causer->pmclassid, $timenow);
                $sturec = (array)$sturec;

                // Merge the new student record with $causer.
                foreach ($sturec as $k => $v) {
                    $causer->$k = $v;
                }
            }

            $moodlegrades = $this->get_moodlegrades($causer->muid, $causer->moodlecourseid, $gis);

            // Handle the course grade.
            if (isset($moodlegrades[$coursegradeitem->id])) {
                $this->sync_coursegrade($causer, $coursegradeitem, $moodlegrades[$coursegradeitem->id], $compelements,
                        $causer->pmcoursecompletiongrade, $causer->pmcoursecredits, $timenow);
            }

            // Handle completion elements.
            $cmgrades = $this->get_elis_coursecompletion_grades($requestedmuserid, $causer->muid, $causer->pmclassid);
            $this->sync_completionelements($causer, $gis, $linkedcompelements, $moodlegrades, $cmgrades, $timenow);
        }

        if ($this->completionelementrecset !== null) {
            $this->completionelementrecset->close();
        }
    }
}