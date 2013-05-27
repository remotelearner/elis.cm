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

/**
 * An action to enrol a user.
 */
class deepsight_action_enrol extends deepsight_action_standard {
    const TYPE = 'enroledit';
    public $label = 'Enrol User';
    public $icon = 'elisicon-assoc';
    public $endpoint = '';

    /**
     * Sets the action's label from language string.
     */
    protected function postconstruct() {
        $this->label = get_string('ds_action_enrol', 'elis_program');
    }

    /**
     * Gets the currently assigned endpoint.
     * @return string The currently assigned endpoint.
     */
    public function get_endpoint() {
        return $this->endpoint;
    }

    /**
     * Sets the endpoint.
     * @param string $endpoint The endpoint to assign.
     */
    public function set_endpoint($endpoint) {
        $this->endpoint = $endpoint;
    }

    /**
     * Gets options for the javascript object.
     * @return array An options array.
     */
    public function get_js_opts() {
        $opts = parent::get_js_opts();
        $opts['condition'] = $this->condition;
        $opts['completefunc'] = 'function(e, data) {
            if (typeof(data.opts.datatable.filters.enrolled) == \'undefined\') {
                var anim = \'remove\';
            } else if (data.opts.datatable.filters.enrolled == \'\') {
                var anim = \'remove\';
            } else if (data.opts.datatable.filters.enrolled == \'notenrolled\') {
                var anim = \'remove\';
            } else {
                var anim = \'disable\';
            }

            if (anim == \'remove\') {
                data.opts.parent.addClass(\'confirmed\').delay(1000).fadeOut(250, function() {
                    data.opts.datatable.removefromtable(\'assigned\', data.opts.parent.data(\'id\'));
                });
            } else if (anim == \'disable\') {
                data.opts.parent.addClass(\'confirmed\');
                setTimeout(function() {
                    data.opts.parent.removeClass(\'confirmed\', 250).addClass(\'disabled\', 250)
                        .find(\'.actions\').html(data.opts.lang_enrolled);
                },1000);
            }
        }';
        $opts['opts']['actionurl'] = $this->endpoint;
        $opts['opts']['mode'] = 'enrol';
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
     * Responds to the javascript request to complete the action.
     *
     * Can handle initial enrolment attempts, as well as waitlist/over-enrol attempts.
     *
     * @param array $elements An array of elements to perform the action on. Although the values will differ, the indexes
     *                        will always be element IDs.
     * @param bool $bulkaction Whether this is a bulk-action or not.
     * @return array A response array, consisting of result and msg.
     */
    protected function _respond_to_js(array $elements, $bulkaction) {
        $result = array();

        if (empty($elements) || !is_array($elements)) {
            throw new Exception('Did not receive any valid user ids.');
        }

        // Class ID to enrol into.
        $classid = required_param('id', PARAM_INT);

        $waitlistconfirm = optional_param('waitlistconfirm', 0, PARAM_INT);

        if (empty($waitlistconfirm)) {
            $enroldata = required_param('enroldata', PARAM_CLEAN);
            $result = $this->attempt_enrolment($elements, $classid, $enroldata, $bulkaction);
        } else {
            $useractions = required_param('actions', PARAM_CLEAN);
            $enroldata = required_param('enroldata', PARAM_CLEAN);
            $result = $this->waitlistconfirm($elements, $classid, $useractions, $enroldata);
        }
        return $result;
    }

    /**
     * Handle a request to resolve the enrolment limit for a class.
     *
     * Over-enrols, adds to waitlist, or skips enrolment based on user selection.
     *
     * @param array $elements An array of elements to perform the action on.
     * @param int $classid The ID of the class we're enrolling into.
     * @param string $rawuseractions The JSON string containing the actions we want to perform. This will be an array, indexed by
     *                               element ID, with values being "waitlist" for add to waitlist, "overenrol" for overenrol, or
     *                               anything else being skip enrolment. If we are performing a bulk enrolment, a "bulk_enrol" key
     *                               will be present, which will take precendence.
     * @param string $enroldata A JSON string containing enrolment data for the users we want to overenrol.
     * @return array An array consisting of 'result' and 'num_affected', indicating success, and the number of users either enroled,
     *               or added to waitlist, respectively.
     */
    protected function waitlistconfirm($elements, $classid, $rawuseractions, $enroldata) {
        set_time_limit(0);

        // Unpack and process incoming desired user actions.
        // $rawuseractions comes from jQuery's serializeArray function, which gives us an array of arrays, each containing a "name"
        // and "value" member. after processing here, we will get an array indexed by user ID, with the value being the desired
        // waitlist action (waitlist, overenrol, skip).
        if (is_string($rawuseractions)) {
            $rawuseractions = @json_decode($rawuseractions, true);
        }
        if (empty($rawuseractions) || !is_array($rawuseractions)) {
            $rawuseractions = array();
        }
        $useractions = array();
        foreach ($rawuseractions as $param) {
            if (is_numeric($param['name']) || $param['name'] == 'bulk_action') {
                $useractions[$param['name']] = $param['value'];
            }
        }
        if (empty($useractions)) {
            throw new Exception('Did not receive any valid user ids.');
        }

        // Original enrolment data.
        $enroldata = $this->process_enrolment_data($classid, @json_decode($enroldata));
        if (empty($enroldata)) {
            throw new Exception('Did not receive valid enrolment data.');
        }

        $now = time();
        $numaffected = 0;
        foreach ($elements as $userid) {

            // Skip invalid userids or users which we dont have permission to modify.
            if (!is_numeric($userid) || !student::can_manage_assoc($userid, $classid)) {
                continue;
            }

            // Get action.
            if (isset($useractions['bulk_action'])) {
                $action = $useractions['bulk_action'];
            } else if (isset($useractions[$userid])) {
                $action = $useractions[$userid];
            } else {
                continue;
            }

            // Perform actions.
            try {
                if ($action === 'waitlist') {
                    $waitrecord = new object();
                    $waitrecord->userid = $userid;
                    $waitrecord->classid = $classid;
                    $waitrecord->timecreated = $now;
                    $waitrecord->timemodified = $now;
                    $waitrecord->position = 0;
                    $waitlist = new waitlist($waitrecord);
                    $status = $waitlist->save();
                    $numaffected++;
                } else if ($action === 'overenrol') {
                    $sturecord = $enroldata;
                    $sturecord['userid'] = $userid;
                    $newstu = new student($sturecord);
                    $newstu->validation_overrides[] = 'prerequisites';
                    $newstu->validation_overrides[] = 'enrolment_limit';
                    $status = $newstu->save();
                    $numaffected++;
                }
            } catch (Exception $e) {
                $param = array('message' => $e->getMessage());
                throw new Exception(get_string('record_not_created_reason', 'elis_program', $param));
            }

        }
        return array('result' => 'success', 'num_affected'=>$numaffected);
    }

    /**
     * Attempt initial enrolment.
     *
     * This performs an initial attempt at enroling the selected users. This has not yet taken into account the enrolment limit
     * or permissions.
     *
     * @param array $elements An array of elements to perform the action on.
     * @param int $classid The ID of the class we're enrolling into.
     * @param string $enroldata A JSON string containing enrolment data for the users we want to overenrol.
     * @param bool $bulkaction Whether this attempt is a bulk action or not.
     * @return array An array consisting of "result", and optionally "users" and "total", explained below:
     *                   result: Will be "success" if all users were enrolled successfully, or "waitlist", if we have users that
     *                           need to be waitlisted.
     *                   users:  If some users need enrolment limit resolution, this will be present.
     *                           This will either contain an array of arrays like array('userid' => $userid, 'name' => $label),
     *                           or the string 'bulklist', if we're performing a bulk action.
     *                   total:  If we're performing a bulk action, and some users need enrolment limit resolution, this will be
     *                           included, indicating the number of users needed resolution.
     */
    protected function attempt_enrolment($elements, $classid, $enroldata, $bulkaction) {
        set_time_limit(0);

        // Enrolment data.
        $enroldata = $this->process_enrolment_data($classid, @json_decode($enroldata));
        if (empty($enroldata)) {
            throw new Exception('Did not receive valid enrolment data.');
        }

        // Attempt enrolment.
        $waitlist = array();
        foreach ($elements as $userid => $label) {

            // Skip invalid userids or users which we dont have permission to modify.
            if (!is_numeric($userid) || !student::can_manage_assoc($userid, $classid)) {
                continue;
            }

            // Build student.
            $sturecord = $enroldata;
            $sturecord['userid'] = $userid;
            $newstu = new student($sturecord);
            $newstu->validation_overrides[] = 'prerequisites';
            if ($newstu->completestatusid != STUSTATUS_NOTCOMPLETE) {
                // User is set to completed, so don't worry about enrolment limit.
                $newstu->validation_overrides[] = 'enrolment_limit';
            }

            // Attempt enrolment.
            try {
                $newstu->save();
                unset($elements[$userid]);
                $this->datatable->bulklist_modify(array(), array($userid));
            } catch (pmclass_enrolment_limit_validation_exception $e) {
                $waitlist[] = array('userid' => $userid, 'name' => $label);
            } catch (Exception $e) {
                $param = array('message' => $e->getMessage());
                throw new Exception(get_string('record_not_created_reason', 'elis_program', $param));
            }
        }

        if ($bulkaction === true) {
            if (!empty($waitlist)) {
                list($bulklistdisplay, $totalusers) = $this->datatable->bulklist_get_display(1);
                return array(
                    'result' => 'waitlist',
                    'users' => 'bulklist',
                    'total' => $totalusers
                );
            } else {
                return array('result' => 'success');
            }
        } else {
            return (!empty($waitlist))
                ? array('result' => 'waitlist', 'users' => $waitlist)
                : array('result' => 'success');
        }
    }

    /**
     * Process incoming enrolment data into an array ready to construct a student object.
     *
     * @param int $classid The class ID we're enroling into.
     * @param array $rawenroldata The raw enrolment data received from javascript.
     * @return array An array ready to construct a student object.
     */
    protected function process_enrolment_data($classid, $rawenroldata) {
        if (empty($rawenroldata) || !is_array($rawenroldata)) {
            return array();
        }

        // Rawenroldata comes in as an array of objects with name and value properties, convert it into an accessible array of
        // name => value.
        $enroldata = array();
        foreach ($rawenroldata as $param) {
            $enroldata[$param->name] = $param->value;
        }

        // Basic info.
        $sturecord = array('classid' => $classid);

        // Enrolment time AND completion time - same validation w/ predictable naming scheme, use array/loop to do both with once
        // chunk of code.
        $times = array('enrolmenttime' => 'start', 'completetime' => 'end');
        foreach ($times as $stuparam => $inputprefix) {
            $sturecord[$stuparam] = ds_process_js_date_data($enroldata, $inputprefix);
        }

        // Completion status.
        $validstatus = array(
            'notcomplete' => STUSTATUS_NOTCOMPLETE,
            'passed' => STUSTATUS_PASSED,
            'failed' => STUSTATUS_FAILED
        );
        $status = (isset($enroldata['completestatusid']) && isset($validstatus[$enroldata['completestatusid']]))
            ? $validstatus[$enroldata['completestatusid']]
            : STUSTATUS_NOTCOMPLETE;
        $sturecord['completestatusid'] = $status;

        // Additional info.
        $sturecord['grade'] = (!empty($enroldata['grade'])) ? $enroldata['grade'] : 0;
        $sturecord['credits'] = (!empty($enroldata['credits'])) ? $enroldata['credits'] : 0;
        $sturecord['locked'] = (!empty($enroldata['locked'])) ? 1 : 0;

        return $sturecord;
    }
}
