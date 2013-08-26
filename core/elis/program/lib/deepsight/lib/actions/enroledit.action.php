<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2013 Remote Learner.net Inc http://www.remote-learner.net
 * @author     James McQuillan <james.mcquillan@remote-learner.net>
 *
 */

require_once(elispm::lib('data/student.class.php'));
require_once(elispm::lib('data/course.class.php'));
require_once(elispm::lib('data/pmclass.class.php'));

/**
 * An action to edit a user's enrolment information.
 */
class deepsight_action_enroledit extends deepsight_action_standard {
    const TYPE = 'enroledit';
    public $label = 'Edit User';
    public $icon = 'elisicon-edit';

    /**
     * Sets the action's label from language string.
     */
    protected function postconstruct() {
        $this->label = get_string('ds_action_edit', 'elis_program');
    }

    /**
     * Responds to a javascript request.
     *
     * Processes incoming information and updates the relevant user's information, including learning objectives.
     * Also responds to a request for enrolment data, used when editing a single user.
     *
     * @param array $elements An array of elements to perform the action on. Although the values will differ, the indexes
     *                        will always be element IDs.
     * @param bool $bulkaction Whether this is a bulk-action or not.
     * @return array A response array, consisting of result and msg.
     */
    protected function _respond_to_js(array $elements, $bulkaction) {
        $classid = required_param('id', PARAM_INT);
        $mode = optional_param('mode', 'complete', PARAM_ALPHA);

        if ($mode == 'getinfo') {
            foreach ($elements as $userid => $label) {
                return array(
                    'result' => 'success',
                    'msg' => 'Success',
                    'enroldata' => $this->get_enrol_data($userid, $classid),
                );
            }
        } else {
            set_time_limit(0);

            // Enrolment data.
            $enroldata = required_param('enroldata', PARAM_CLEAN);
            $enroldata = $this->process_enrolment_data($classid, @json_decode($enroldata));
            if (empty($enroldata)) {
                throw new Exception('Did not receive valid enrolment data.');
            }

            // Learning objectives.
            $learningobjectives = optional_param('learnobjdata', '', PARAM_CLEAN);
            $learningobjectives = (!empty($learningobjectives))
                ? $this->process_learning_objectives_data($learningobjectives)
                : array();

            foreach ($elements as $userid => $label) {
                $this->do_update($userid, $classid, $enroldata, $learningobjectives);
            }

            $formattedenroldata = $this->format_enroldata_for_display($enroldata);
        }

        return array(
            'result' => 'success',
            'msg' => 'Success',
            'enroldata' => $enroldata,
            'displaydata' => $formattedenroldata
        );
    }

    /**
     * Formats enrolment data for display in the table post-edit.
     * @param array $enroldata The incoming enrolment data
     * @return array The formatted enrolment data.
     */
    protected function format_enroldata_for_display($enroldata) {

        // Locked 0,1 => no, yes.
        if (isset($enroldata['locked'])) {
            $enroldata['locked'] = ($enroldata['locked'] == 1) ? get_string('yes', 'moodle') : get_string('no', 'moodle');
        }

        // Format enrolment time.
        if (isset($enroldata['enrolmenttime'])) {
            $enroldata['enrolmenttime'] = ds_process_displaytime($enroldata['enrolmenttime']);
        }

        // Format completion time.
        if (isset($enroldata['completetime'])) {
            $statusiscomplete = (isset($enroldata['completestatusid']) && $enroldata['completestatusid'] != STUSTATUS_NOTCOMPLETE)
                    ? true : false;
            $enroldata['completetime'] = ($statusiscomplete === true) ? ds_process_displaytime($enroldata['completetime']) : '-';
        }

        // Completion status ints to labels.
        if (isset($enroldata['completestatusid'])) {
            $choices = array(
                STUSTATUS_NOTCOMPLETE => get_string('n_completed', 'elis_program'),
                STUSTATUS_PASSED => get_string('passed', 'elis_program'),
                STUSTATUS_FAILED => get_string('failed', 'elis_program'),
            );
            $enroldata['completestatusid'] = $choices[$enroldata['completestatusid']];
        }

        if (isset($enroldata['grade'])) {
            $enroldata['grade'] = pm_display_grade($enroldata['grade']);
        }

        if (isset($enroldata['credits'])) {
            $enroldata['credits'] = number_format($enroldata['credits'], 2);
        }

        return $enroldata;
    }

    /**
     * Sets options and language strings for the javascript object.
     *
     * @see deepsight_action::get_js_opts();
     */
    public function get_js_opts() {
        $opts = parent::get_js_opts();
        $opts['condition'] = $this->condition;
        $opts['opts']['actionurl'] = $this->endpoint;
        $opts['opts']['mode'] = 'edit';
        $opts['opts']['lang_enrolment_date'] = get_string('enrolment_time', 'elis_program');
        $opts['opts']['lang_completion_status'] = get_string('student_status', 'elis_program');
        $opts['opts']['lang_completion_notcomplete'] = get_string('n_completed', 'elis_program');
        $opts['opts']['lang_completion_passed'] = get_string('passed', 'elis_program');
        $opts['opts']['lang_completion_failed'] = get_string('failed', 'elis_program');
        $opts['opts']['lang_completion_on'] = get_string('ds_completion_on', 'elis_program');
        $opts['opts']['lang_grade'] = get_string('student_grade', 'elis_program');
        $opts['opts']['lang_credits'] = get_string('student_credits', 'elis_program');
        $opts['opts']['lang_lock'] = get_string('student_lock', 'elis_program');
        $opts['opts']['lang_locked'] = get_string('student_locked', 'elis_program');
        $opts['opts']['lang_time_graded'] = get_string('date_graded', 'elis_program');
        $opts['opts']['lang_enroldata'] = get_string('enrolment_data', 'elis_program');
        $opts['opts']['lang_learning_objectives'] = get_string('completion_elements', 'elis_program');
        $opts['opts']['lang_bulk_confirm'] = get_string('ds_bulk_confirm', 'elis_program');
        $opts['opts']['lang_enrolled'] = get_string('enroled', 'elis_program');
        $opts['opts']['lang_months'] = array(
            0 => get_string('month_jan_short', 'elis_program'),
            1 => get_string('month_feb_short', 'elis_program'),
            2 => get_string('month_mar_short', 'elis_program'),
            3 => get_string('month_apr_short', 'elis_program'),
            4 => get_string('month_may_short', 'elis_program'),
            5 => get_string('month_jun_short', 'elis_program'),
            6 => get_string('month_jul_short', 'elis_program'),
            7 => get_string('month_aug_short', 'elis_program'),
            8 => get_string('month_sep_short', 'elis_program'),
            9 => get_string('month_oct_short', 'elis_program'),
            10 => get_string('month_nov_short', 'elis_program'),
            11 => get_string('month_dec_short', 'elis_program')
        );
        $opts['opts']['lang_waitlist_headers'] = get_string('ds_waitlist_headers', 'elis_program');
        $opts['opts']['lang_waitlist_overenrol'] = get_string('over_enrol', 'elis_program');
        $opts['opts']['lang_waitlist_add'] = get_string('ds_add_to_waitlist', 'elis_program');
        $opts['opts']['lang_waitlist_skip'] = get_string('skip_enrolment', 'elis_program');
        $opts['opts']['lang_general_error'] = get_string('ds_unknown_error', 'elis_program');
        $opts['opts']['lang_all_users'] = get_string('ds_allusers', 'elis_program');
        return $opts;
    }

    /**
     * Gets enrolment data for a single user.
     *
     * Includes all enrolment data, as well as learning objective information.
     *
     * @param int $userid The userid to get information for.
     * @param int $classid The classid to get enrolment data for.
     * @return array An array of enrolment and learning objective data.
     */
    protected function get_enrol_data($userid, $classid) {
        global $DB;

        $completestatusjskeys = array(
            STUSTATUS_NOTCOMPLETE => 'notcomplete',
            STUSTATUS_PASSED => 'passed',
            STUSTATUS_FAILED => 'failed'
        );

        if (student::can_manage_assoc($userid, $classid) !== true) {
            throw new Exception(get_string('not_permitted', 'elis_program'));
        }

        $enroldata = array();

        // Enrolment data.
        $enrolrec = $DB->get_record(student::TABLE, array('classid' => $classid, 'userid' => $userid));
        if (!empty($enrolrec)) {
            $enroldata = array(
                'association_id' => $enrolrec->id,
                'enroltime' => array(
                    'date' => date('j', $enrolrec->enrolmenttime),
                    'month' => date('n', $enrolrec->enrolmenttime),
                    'year'=> date('Y', $enrolrec->enrolmenttime)
                ),
                'completestatus' => $completestatusjskeys[$enrolrec->completestatusid],
                'completetime' => array(
                    'date' => date('j', $enrolrec->completetime),
                    'month' => date('n', $enrolrec->completetime),
                    'year' => date('Y', $enrolrec->completetime)
                ),
                'grade' => $enrolrec->grade,
                'credits' => $enrolrec->credits,
                'locked' => $enrolrec->locked,
                'learningobjectives' => array()
            );
        }

        // Learning objective information.
        $objectivesql = 'SELECT comp.id as objectiveid,
                                grade.id as gradeid,
                                comp.idnumber,
                                grade.grade,
                                grade.locked,
                                grade.timegraded
                           FROM {'.coursecompletion::TABLE.'} comp
                           JOIN {'.pmclass::TABLE.'} pmclass
                                ON pmclass.courseid = comp.courseid
                      LEFT JOIN {'.student_grade::TABLE.'} grade
                                ON grade.completionid = comp.id AND grade.classid = ? AND grade.userid = ?
                          WHERE pmclass.id = ?
                       ORDER BY comp.id ASC';
        $objectiveparams = array($classid, $userid, $classid);
        $objectivedata = $DB->get_records_sql($objectivesql, $objectiveparams);
        foreach ($objectivedata as $objective) {
            $timegraded = (!empty($objective->timegraded)) ? $objective->timegraded : time();
            $objectiveentry = array(
                'objectiveid' => $objective->objectiveid,
                'idnumber' => $objective->idnumber,
                'grade' => (!empty($objective->grade)) ? $objective->grade : 0,
                'locked' => (!empty($objective->locked)) ? $objective->locked : 0,
                'date_graded' => array(
                    'date' => date('j', $timegraded),
                    'month' => date('n', $timegraded),
                    'year' => date('Y', $timegraded)
                )
            );

            if (!empty($objective->gradeid)) {
                $objectiveentry['gradeid'] = $objective->gradeid;
            }

            $enroldata['learningobjectives'][] = $objectiveentry;
        }

        return $enroldata;
    }

    /**
     * Processes incoming enrolment data, sent from javascript.
     *
     * @param int $classid The class ID we're editing enrolment data for.
     * @param array $rawenroldata The array of raw enrolment data to process.
     * @return array An array of processed enrolment data, ready to be used to construct a student object.
     */
    protected function process_enrolment_data($classid, array $rawenroldata) {
        if (empty($rawenroldata) || !is_array($rawenroldata)) {
            return array();
        }

        $enroldata = array();

        // Rawenroldata comes in as an array of objects with name and value properties, convert it into an accessible array of
        // name => value.
        foreach ($rawenroldata as $param) {
            $enroldata[$param->name] = $param->value;
        }

        $sturecord = array();

        // Enrolment time AND completion time - same validation w/ predictable naming scheme, use array/loop to do both with once
        // chunk of code.
        $times = array('enrolmenttime' => 'start', 'completetime' => 'end');
        foreach ($times as $stuparam => $inputprefix) {
            $sturecord[$stuparam] = ds_process_js_date_data($enroldata, $inputprefix);
        }

        // Completion status.
        $validcompletionstatus = array(
            'notcomplete' => STUSTATUS_NOTCOMPLETE,
            'passed' => STUSTATUS_PASSED,
            'failed' => STUSTATUS_FAILED
        );
        $sturecord['completestatusid'] = (isset($enroldata['completestatusid'])
                                          && isset($validcompletionstatus[$enroldata['completestatusid']]))
            ? $validcompletionstatus[$enroldata['completestatusid']]
            : STUSTATUS_NOTCOMPLETE;

        // Additional info.
        $sturecord['grade'] = (!empty($enroldata['grade'])) ? $enroldata['grade'] : 0;
        $sturecord['credits'] = (!empty($enroldata['credits'])) ? $enroldata['credits'] : 0;
        $sturecord['locked'] = (!empty($enroldata['locked'])) ? 1 : 0;
        if (isset($enroldata['bulkedit']) && $enroldata['bulkedit'] == 1) {
            foreach ($sturecord as $attr => $val) {
                if (!isset($enroldata[$attr.'_enabled'])) {
                    unset($sturecord[$attr]);
                }
            }
        }

        // Basic info.
        $sturecord['classid'] = $classid;

        if (isset($enroldata['association_id']) && is_numeric($enroldata['association_id'])) {
            $sturecord['id'] = $enroldata['association_id'];
        }

        return $sturecord;
    }

    /**
     * Process incoming learning objective information.
     *
     * @param string $rawlearnobjdata The raw learning objective information sent from javascript, in JSON format.
     * @return array An array of processed learning objective information. Will contain multuple arrays, indexed by the
     *               learning objective ID, each ready to construct a student_grade object.
     */
    protected function process_learning_objectives_data($rawlearnobjdata) {
        $learnobjdata = array();

        $rawlearnobjdata = @json_decode($rawlearnobjdata, true);

        if (empty($rawlearnobjdata) || !is_array($rawlearnobjdata)) {
            return array();
        }

        foreach ($rawlearnobjdata as $i => $input) {
            if (!isset($input['name'], $input['value'])) {
                continue;
            }

            $nameparts = explode('_', $input['name'], 2);
            if (empty($nameparts) || !is_numeric($nameparts[0]) || count($nameparts) < 2) {
                continue;
            }

            if ($input['value'] == 'on') {
                $input['value'] = 1;
            }

            $learnobjdata[$nameparts[0]][$nameparts[1]] = $input['value'];
        }

        foreach ($learnobjdata as $learnobjid => &$data) {
            if (!isset($data['locked']) || $data['locked'] !== 1) {
                $data['locked'] = 0;
            }

            if (!isset($data['grade']) || !is_numeric($data['grade'])) {
                $data['grade'] = 0;
            }

            $data['timegraded'] = ds_process_js_date_data($data);
        }

        return $learnobjdata;
    }

    /**
     * Perform an update for a single user/class pair.
     *
     * @param int $userid The user ID we're updating.
     * @param int $classid The class ID we're updating information for.
     * @param array $enroldata The updated enrolment data.
     * @param array $learningobjectives The updated learning objective data.
     */
    protected function do_update($userid, $classid, array $enroldata, array $learningobjectives) {
        global $DB;
        if (student::can_manage_assoc($userid, $classid) !== true) {
            throw new Exception('Unauthorized');
        }

        if (!isset($enroldata['id'])) {
            $associationid = $DB->get_field(student::TABLE, 'id', array('classid' => $classid, 'userid' => $userid));
            if (empty($associationid)) {
                return false;
            } else {
                $enroldata['id'] = $associationid;
            }
        }

        $enroldata['userid'] = $userid;
        $stu = new student($enroldata);

        if ($stu->completestatusid == STUSTATUS_PASSED
            && $DB->get_field(student::TABLE, 'completestatusid', array('id' => $stu->id)) != STUSTATUS_PASSED) {
            $stu->complete();
        } else {
            $status = $stu->save();
        }

        foreach ($learningobjectives as $id => $data) {
            $graderec = array('userid' => $userid, 'classid' => $classid, 'completionid' => $id);
            $existingrec = $DB->get_record(student_grade::TABLE, $graderec);
            if (!empty($existingrec)) {
                $graderec = (array)$existingrec;
            }

            $graderec['timegraded'] = $data['timegraded'];
            $graderec['grade'] = $data['grade'];
            $graderec['locked'] = $data['locked'];

            $sgrade = new student_grade($graderec);
            $sgrade->save();
        }
    }
}