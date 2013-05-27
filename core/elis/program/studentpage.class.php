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
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once elispm::lib('lib.php');
require_once elispm::lib('deprecatedlib.php'); // cm_get_param()
require_once elispm::lib('page.class.php');
require_once elispm::lib('associationpage.class.php');
//require_once elispm::lib('table.class.php');
require_once elispm::lib('data/pmclass.class.php');
require_once elispm::lib('data/student.class.php');
require_once elispm::lib('contexts.php'); // TBD

require_once elispm::file('pmclasspage.class.php');
require_once elispm::file('form/waitlistform.class.php');

class studentpage extends associationpage {
    const LANG_FILE = 'elis_program';

    var $data_class = 'student';
    var $pagename = 'stu';
    var $tab_page = 'pmclasspage'; // TBD: was cmclasspage
    var $default_tab = 'studentpage';

    //var $form_class = 'studentform';

    var $section = 'curr';

    var $parent_data_class = 'pmclass'; // TBD: was cmclass

    //var $tabs; // TBD: moved to associationpage

    function __construct(array $params = null) {
        $this->tabs = array( // TBD: 'currcourse_edit' -> 'edit'
            //array('tab_id' => 'view', 'page' => get_class($this), 'params' => array('action' => 'view'), 'name' => get_string('detail', self::LANG_FILE), 'showtab' => true),
            array('tab_id' => 'currcourse_edit', 'page' => get_class($this), 'params' => array('action' => 'currcourse_edit'), 'name' => get_string('edit', self::LANG_FILE), 'showtab' => true, 'showbutton' => true, 'image' => 'edit'),
            //array('tab_id' => 'edit', 'page' => get_class($this), 'params' => array('action' => 'edit'), 'name' => get_string('edit', self::LANG_FILE), 'showtab' => true, 'showbutton' => true, 'image' => 'edit'), // TBD: tab_id was 'edit' or 'bulkedit'???
           array('tab_id' => 'delete', 'page' => get_class($this), 'params' => array('action' => 'delete'), 'name' => get_string('delete', self::LANG_FILE), 'showbutton' => true, 'image' => 'delete'),
        );

        parent::__construct($params);
    }

    // Store checkbox data into session
    function do_checkbox_selection_session() {
        parent::checkbox_selection_session($this->pagename);
    }

    function do_bulk_checkbox_selection_session() {
        parent::bulk_checkbox_selection_session($this->pagename);
    }

    function do_bulk_checkbox_selection_reset() {
        parent::bulk_checkbox_selection_reset('bulkedit');
    }

    function do_bulk_checkbox_selection_deselectall() {
        parent::bulk_checkbox_selection_deselectall($this->pagename);
    }

    function do_bulk_apply_all() {
        parent::bulk_apply_all($this->pagename);
    }

    function _get_page_params() { // TBD
        return parent::_get_page_params();
    }

    function can_do_default() { // cm => pm
        $id = $this->required_param('id', PARAM_INT);
        $pmclasspage = new pmclasspage(array('id' => $id));
        return $pmclasspage->can_do();
    }

    function can_do_add() {
        $id    = $this->required_param('id');
        $users = pm_process_user_enrolment_data(); // ELIS-4089 -- JJF

        foreach($users as $uid => $user) {
            if(!student::can_manage_assoc($uid, $id)) {
                return false;
            }
        }

        return pmclasspage::can_enrol_into_class($id);
    }

    function can_do_savenew() {
        return $this->can_do_add();
    }

    function can_do_currcourse_edit() {
        return $this->can_do_edit();
    }

    function can_do_delete() {
        global $DB;
        $association_id = $this->required_param('association_id', PARAM_INT);
        $student = new student($association_id);
        if (empty(elis::$config->elis_program->force_unenrol_in_moodle)) {
            // check whether the user is enrolled in the Moodle course via any
            // plugin other than the elis plugin
            $mcourse = $student->pmclass->classmoodle;
            $muser = $student->users->get_moodleuser();
            if ($mcourse->valid() && $muser) {
                $mcourse = $mcourse->current()->moodlecourseid;
                if ($mcourse) {
                    $ctx = get_context_instance(CONTEXT_COURSE, $mcourse);
                    if ($DB->record_exists_select('role_assignments', "userid = ? AND contextid = ? AND component != 'enrol_elis'", array($muser->id, $ctx->id))) {
                        // user is assigned a role other than via the elis
                        // enrolment plugin
                        return false;
                    }
                }
            }
        }
        return student::can_manage_assoc($student->userid, $student->classid);
    }

    function can_do_confirm() {
        return $this->can_do_delete();
    }

    function can_do_edit() {
        $association_id = $this->optional_param('association_id', '', PARAM_INT);
        if (empty($association_id)) { // TBD
            error_log('studentpage.class.php::can_do_edit() - empty association_id! Returning: false');
            return false;
        }
        $student = new student($association_id);
        return student::can_manage_assoc($student->userid, $student->classid);
    }

    function can_do_update() {
        return $this->can_do_edit();
    }

    function can_do_view() {
        return $this->can_do_edit();
    }

    function can_do_bulkedit() {
        //todo: allow bulk editing for non-admins
        $id = $this->required_param('id');
        return pmclasspage::_has_capability('elis/program:track_enrol', $id);
    }

    function can_do_updatemultiple() {
        //todo: allow multi-update for non-admins
        $id = $this->required_param('id');
        return pmclasspage::_has_capability('elis/program:track_enrol', $id);
    }

    function do_add() { // TBD: must overload the parents since no studentform
        //error_log('studentpage.class.php::do_add()');
        $this->display('savenew');
    }

    function do_delete() { // action_confirm
        global $DB;
        $stuid = $this->required_param('association_id', PARAM_INT);
        $confirm = optional_param('confirm', null, PARAM_CLEAN);
        if ($confirm == null) {
            $this->display('delete');
            return;
        }

        require_sesskey();
        $stu = new student($stuid);
        $user = $DB->get_record(user::TABLE, array('id' => $stu->userid));
        $sparam = new stdClass;
        $sparam->name = fullname($user);
        $classid = $stu->classid;
        if (md5($stuid) != $confirm) {
            echo cm_error(get_string('invalidconfirm', self::LANG_FILE));
        } else {
            $stu->delete();
        }

        $studentpage = new studentpage();
        $target = $studentpage->get_new_page(array('action' => 'default', 'id' => $classid));
        redirect($target->url, get_string('studentunenrolled', self::LANG_FILE, $sparam));
    }

    function do_currcourse_edit() {
        $this->display('edit');
    }

    function display_bulkedit() { // action_bulkedit
        $clsid        = $this->required_param('id', PARAM_INT);
        $type         = $this->optional_param('stype', '', PARAM_ALPHANUM);
        $sort         = $this->optional_param('sort', 'name', PARAM_ALPHANUM);
        $dir          = $this->optional_param('dir', 'ASC', PARAM_ALPHANUM);
        $page         = $this->optional_param('page', 0, PARAM_INT);
        $perpage      = $this->optional_param('perpage', 30, PARAM_INT); // how many per page
        $namesearch   = trim($this->optional_param('search', '', PARAM_CLEAN));
        $alpha        = $this->optional_param('alpha', '', PARAM_ALPHA);

        // TBD: 'edit' or 'bulkedit' or ???; and array(params ???)
        // print_tabs now in parent::print_header()
        echo $this->get_view_form($clsid, $type, $sort, $dir, $page, $perpage, $namesearch, $alpha);
    }

    function display_savenew() { // action_savenew
        $clsid = $this->required_param('id', PARAM_INT);
        $users = pm_process_user_enrolment_data();  // ELIS-4089 -- JJF

        if (!empty($users)) {
            require_sesskey();
            $this->attempt_enrol($clsid, $users);
        } else {
            $this->display_add();
        }
    }

    private function build_student($uid, $clsid, $user) {
        $sturecord            = array();
        $sturecord['classid'] = $clsid;
        $sturecord['userid']  = $uid;

        $startyear  = $user['startyear'];
        $startmonth = $user['startmonth'];
        $startday   = $user['startday'];
        $sturecord['enrolmenttime'] = pm_timestamp(0, 0, 0, $startmonth, $startday, $startyear);

        $endyear  = $user['endyear'];
        $endmonth = $user['endmonth'];
        $endday   = $user['endday'];
        $sturecord['completetime'] = pm_timestamp(0, 0, 0, $endmonth, $endday, $endyear);

        $sturecord['completestatusid'] = $user['completestatusid'];
        $sturecord['grade']            = $user['grade'];
        $sturecord['credits']          = $user['credits'];
        $sturecord['locked']           = !empty($user['locked']) ? 1 : 0;

        return new student($sturecord);
    }

    private function attempt_enrol($classid, $users) {
        // Delete/reset checkbox selection for add action
        session_selection_deletion('add');

        foreach ($users as $uid => $user) {
            if (!empty($user['enrol'])) {
                $newstu = $this->build_student($uid, $classid, $user);
                $pmclass = new pmclass($classid);

                $newstu->validation_overrides[] = 'prerequisites';
                if ($newstu->completestatusid != STUSTATUS_NOTCOMPLETE) {
                    // user is set to completed, so don't worry about enrolment limit
                    $newstu->validation_overrides[] = 'enrolment_limit';
                }
                try {
                    $status = $newstu->save();
                } catch (pmclass_enrolment_limit_validation_exception $e) {
                    $waitlist[] = $newstu;
                    $status = true;
                } catch (Exception $e) {
                    $param = array('message' => $e->getMessage());
                    echo cm_error(get_string('record_not_created_reason',
                                             self::LANG_FILE, $param));
                }
            }
        }

        if(!empty($waitlist)) {
            $this->get_waitlistform($waitlist);
        } else {
            //redirect back to the main enrolment listing for this class
            $id = $this->required_param('id', PARAM_INT);
            $page = $this->get_new_page(array('id' => $id));
            redirect($page->url);
        }
    }

    /*
     * foreach student to enrol
     *      set up the student object
     *      enrol the student
     */
    function do_update() { //action_update
        global $DB;
        $stuid = $this->required_param('association_id', PARAM_INT);
        $clsid = $this->required_param('id', PARAM_INT);
        require_sesskey();
        $users = pm_process_user_enrolment_data();
        //error_log("studentpage::do_update() stuid = {$stuid} clsid = {$clsid} ...");
        $uid   = key($users);
        $user  = current($users);

        $sturecord            = array();
        $sturecord['id']      = $stuid;
        $sturecord['classid'] = $clsid;
        $sturecord['userid']  = $uid;

        $startyear  = $user['startyear'];
        $startmonth = $user['startmonth'];
        $startday   = $user['startday'];
        $sturecord['enrolmenttime'] = pm_timestamp(0, 0, 0, $startmonth, $startday, $startyear);

        $endyear  = $user['endyear'];
        $endmonth = $user['endmonth'];
        $endday   = $user['endday'];
        $sturecord['completetime'] = pm_timestamp(0, 0, 0, $endmonth, $endday, $endyear);

        $sturecord['completestatusid'] = $user['completestatusid'];
        $sturecord['grade']            = $user['grade'];
        $sturecord['credits']          = $user['credits'];
        $sturecord['locked']           = !empty($user['locked']) ? 1 : 0;
        $stu                           = new student($sturecord);

        if ($stu->completestatusid == STUSTATUS_PASSED &&
            $DB->get_field(student::TABLE, 'completestatusid', array('id' => $stuid)) != STUSTATUS_PASSED) {
            $stu->complete();
        } else {
            $status = $stu->save();
        }

        /// Check for grade records...
        $element = cm_get_param('element', array());
        $newelement = cm_get_param('newelement', array());
        $timegraded = $this->optional_param_array('timegraded', array(), PARAM_CLEAN);
        $newtimegraded = $this->optional_param_array('newtimegraded', array(), PARAM_CLEAN);
        $completionid = cm_get_param('completionid', array());
        $newcompletionid = cm_get_param('newcompletionid', array());
        $grade = cm_get_param('grade', array());
        $newgrade = cm_get_param('newgrade', array());
        $locked = cm_get_param('locked', array());
        $newlocked = cm_get_param('newlocked', array());

        foreach ($element as $gradeid => $element) {
            $graderec = array();
            $graderec['id'] = $gradeid;
            $graderec['userid'] = $uid;
            $graderec['classid'] = $clsid;
            $graderec['completionid'] = $element;
            $graderec['timegraded'] = pm_timestamp(0, 0, 0, $timegraded[$gradeid]['startmonth'], $timegraded[$gradeid]['startday'], $timegraded[$gradeid]['startyear']);
            $graderec['grade'] = $grade[$gradeid];
            $graderec['locked'] = isset($locked[$gradeid]) ? $locked[$gradeid] : '0';

            $sgrade = new student_grade($graderec);
            $sgrade->save(); // update()
        }

        foreach ($newelement as $elementid => $element) {
            $graderec = array();
            $graderec['userid'] = $uid;
            $graderec['classid'] = $clsid;
            $graderec['completionid'] = $element;
            $graderec['timegraded'] = pm_timestamp(0, 0, 0, $newtimegraded[$elementid]['startmonth'], $newtimegraded[$elementid]['startday'], $newtimegraded[$elementid]['startyear']);
            $graderec['grade'] = $newgrade[$elementid];
            $graderec['locked'] = isset($newlocked[$elementid]) ? $newlocked[$elementid] : '0';

            $sgrade = new student_grade($graderec);
            $sgrade->save();
        }

        $studentpage = new studentpage();
        $target = $studentpage->get_new_page(array('action' => 'default', 'id' => $clsid));
        redirect($target->url);
    }

    /**
        * This converts a user record saved in the session from other pages into a record that looks like it was submitted by the form
        * @param type $sess_user
        */
    function sessuser2formuser($sess_user) {
        $formuser = array();

        if (isset($sess_user->enrolment_date->day) && isset($sess_user->enrolment_date->month) && isset($sess_user->enrolment_date->year)) {
            $formuser['startday'] = $sess_user->enrolment_date->day;
            $formuser['startmonth'] = $sess_user->enrolment_date->month;
            $formuser['startyear'] = $sess_user->enrolment_date->year;
        }

        if (isset($sess_user->completion_date->day) && isset($sess_user->completion_date->month) && isset($sess_user->completion_date->year)) {
            $formuser['endday'] = $sess_user->completion_date->day;
            $formuser['endmonth'] = $sess_user->completion_date->month;
            $formuser['endyear'] = $sess_user->completion_date->year;
        }

        //other single fields to check. array in the form sess_user_property => form_user_property
        $fields = array(
            'status'=>'completestatusid',
            'grade' => 'grade',
            'credits' => 'credits',
            'locked' => 'locked',
            'unenrol' => 'unenrol',
            'associd' => 'association_id',
        );

        foreach ($fields as $sess_prop => $form_prop) {
            if (isset($sess_user->$sess_prop)) {
                $formuser[$form_prop] = $sess_user->$sess_prop;
            }
        }

        if (empty($formuser['unenrol'])) {
            unset($formuser['unenrol']);
        }

        return $formuser;
    }

    function display_updatemultiple_confirm() {
        global $SESSION, $CFG, $DB;
        require_sesskey();

        $clsid = $this->required_param('id', PARAM_INT);
        $pageid = optional_param('id', 1, PARAM_INT);
        $page = optional_param('s', '', PARAM_ALPHA);

        $date_format = '%d %B %Y';

        // language strings - potential for big loops in this page - this saves the overhead of a function call for minimum mem usage
        $lang_enroldate = get_string('enrolment_time', 'elis_program');
        $lang_completedate = get_string('completion_time', 'elis_program');
        $lang_status = get_string('student_status', 'elis_program');
        $lang_grade = get_string('student_grade', 'elis_program');
        $lang_credits = get_string('student_credits', 'elis_program');
        $lang_locked = get_string('student_locked', 'elis_program');
        $lang_unlocked = get_string('student_unlocked', 'elis_program');
        $lang_unenrol = get_string('unenrol','elis_program');
        $lang_changed = get_string('bulkedit_changedto','elis_program');
        $lang_displayusers = get_string('bulkedit_displayusers','elis_program');
        $lang_user = get_string('user','elis_program');
        $lang_users = get_string('users','elis_program');
        $statuschoices = array();
        foreach(student::$completestatusid_values as $key => $csidv) {
            $statuschoices[$key] = get_string($csidv, self::LANG_FILE);
        }

        echo '<h2>',get_string('confirm_bulk_enrol_edit','elis_program'),'</h2>';
        /*
        echo '<table class="generaltable">';
        echo '<thead><tr>'
            .'<th class="header c0">'.get_string('user','elis_program').'</th>'
            .'<th class="header c1">'.get_string('confirm_bulk_enrol_changes','elis_program').'</th>'
            .'</tr></thead>';
         */

        $pagename = $page.$pageid.'bulkedit';
        if (!empty($SESSION->associationpage[$pagename])) {
            //adds users saved from other pages.
            if (!empty($SESSION->associationpage[$pagename]) && is_array($SESSION->associationpage[$pagename])) {
                $sess_userids = array_keys($SESSION->associationpage[$pagename]);

                $changes_by_change = array();
                $fullname_lookup = array();

                while(!empty($sess_userids)) {

                    $userids_this_pass = array_splice($sess_userids,0,100);

                    $sql = "SELECT u.id, u.firstname, u.lastname, s.enrolmenttime, s.completetime, s.completestatusid, "
                                    ."s.grade, s.credits, s.locked "
                        ."FROM {$CFG->prefix}crlm_user u "
                        ."JOIN {$CFG->prefix}crlm_class_enrolment s ON s.userid = u.id "
                        .'WHERE s.classid=? AND u.id IN ('.implode(',',$userids_this_pass).') '
                        .'ORDER BY u.lastname, u.firstname';
                    $users = $DB->get_records_sql($sql,array($clsid));

                    if (!empty($users) && is_array($users)) {
                        foreach ($users as $userid => $user) {

                            $changes = array();
                            $unenrol = false;

                            //show differences
                            if (!empty($SESSION->associationpage[$pagename][$userid])) {

                                $sess_user =& $SESSION->associationpage[$pagename][$userid];

                                if (isset($sess_user->enrolment_date->day) && isset($sess_user->enrolment_date->month)
                                        && isset($sess_user->enrolment_date->year)) {

                                    $newmonth = $sess_user->enrolment_date->month;
                                    $newday = $sess_user->enrolment_date->day;
                                    $newyear = $sess_user->enrolment_date->year;

                                    $oldmonth = userdate($user->enrolmenttime, '%m');
                                    $oldday = userdate($user->enrolmenttime, '%d');
                                    $oldyear = userdate($user->enrolmenttime, '%Y');

                                    if (($newmonth != $oldmonth) || ($newday != $oldday) || ($newyear != $oldyear)) {
                                        $oldenroldate = userdate($user->enrolmenttime, $date_format);
                                        $newenroldate = userdate(pm_timestamp(0, 0, 0, $newmonth, $newday, $newyear), $date_format);

                                        $changes[] = array($lang_enroldate, $oldenroldate, $newenroldate);
                                        $changes_by_change[$lang_enroldate][$newenroldate][] = $userid;
                                    }

                                }

                                //since the completion date has no meaning if the user has not completed the class
                                //we only compare the completion date if the user's status is not "not complete" or will not
                                //be "not complete"
                                if ($user->completestatusid != STUSTATUS_NOTCOMPLETE
                                        || (isset($sess_user->status) && $sess_user->status != STUSTATUS_NOTCOMPLETE)) {

                                    if (isset($sess_user->completion_date->day) && isset($sess_user->completion_date->month)
                                            && isset($sess_user->completion_date->year)) {

                                        $newmonth = $sess_user->completion_date->month;
                                        $newday = $sess_user->completion_date->day;
                                        $newyear = $sess_user->completion_date->year;

                                        $oldmonth = userdate($user->completetime, '%m');
                                        $oldday = userdate($user->completetime, '%d');
                                        $oldyear = userdate($user->completetime, '%Y');

                                        if (($newmonth != $oldmonth) || ($newday != $oldday) || ($newyear != $oldyear)) {

                                            if (!empty($user->completetime)) {
                                                $oldcompletetime = userdate($user->completetime, $date_format);
                                            } else {
                                                $oldcompletetime = get_string('confirm_bulk_enrol_none', 'elis_program');
                                            }

                                            $newcompletetime = userdate(pm_timestamp(0, 0, 0, $newmonth, $newday, $newyear), $date_format);

                                            $changes[] = array($lang_completedate, $oldcompletetime, $newcompletetime);
                                            $changes_by_change[$lang_completedate][$newcompletetime][] = $userid;
                                        }
                                    }

                                }

                                if (isset($sess_user->status) && $sess_user->status != $user->completestatusid) {
                                    $changes[] = array($lang_status, $user->completestatusid, $sess_user->status);
                                    $changes_by_change[$lang_status][$statuschoices[$sess_user->status]][] = $userid;
                                }

                                if (isset($sess_user->grade) && $sess_user->grade != $user->grade) {
                                    $changes[] = array($lang_grade, $user->grade, $sess_user->grade);
                                    $changes_by_change[$lang_grade][$sess_user->grade][] = $userid;
                                }

                                if (isset($sess_user->credits) && $sess_user->credits != $user->credits) {
                                    $changes[] = array($lang_credits, $user->credits, $sess_user->credits);
                                    $changes_by_change[$lang_credits][$sess_user->credits][] = $userid;
                                }

                                if (isset($sess_user->locked) && $sess_user->locked != $user->locked) {
                                    $changes[] = array($lang_locked, $user->locked, $sess_user->locked);

                                    $locked_str = ($sess_user->locked == 1) ? $lang_locked : $lang_unlocked;
                                    $changes_by_change[$lang_locked][$locked_str][] = $userid;

                                }

                                if (isset($sess_user->unenrol) && $sess_user->unenrol == 1) {
                                    $changes_by_change[$lang_unenrol][] = $userid;
                                    $unenrol = true;
                                }
                            }

                            /*
                            if (!empty($changes) || $unenrol === true) {
                                echo '<tr>';
                                echo '<td>'.fullname($user),' (#'.$user->id.')</td>';
                                echo '<td>';
                                echo '<ul>';
                                foreach ($changes as $change) {
                                    echo '<li>'.$change[0].': '.$change[1].' >> '.$change[2].'</li>';
                                }
                                if ($unenrol === true) {
                                    echo '<li style="color:#f00">'.get_string('unenrol','elis_program').'</li>';
                                }
                                echo '</ul>';
                                echo '</td></tr>';
                            }
                            */
                            unset($changes,$unenrol); //just for memory purposes

                            if (!isset($fullname_lookup[$userid])) {
                                $fullname_lookup[$userid] = fullname($user);
                            }
                        }
                    }

                    unset($users, $userids_this_pass); //just for memory purposes

                } //end while loop
            }
        }

        //echo '</table>';
        echo '<script>
                function toggle(id){
                    var e = document.getElementById(id);
                    e.style.display = ((e.style.display == \'none\') ? \'block\' : \'none\');
                }
              </script>
              <style>
              #changereview h3 {margin:10px 0 5px 0}
              #changereview ul.main {margin:7px 0 25px 2em}
              #changereview ul.main li {margin:0 0 4px 0}
              #changereview ul.sub {margin:5px 0 15px 2em;display:none}
              #changereview span.blk_val {text-decoration:underline;font-weight:bold;}
              #changereview span.blk_user {font-style:italic;font-weight:bold;}
              </style>';
        echo '<div id="changereview">';
        foreach ($changes_by_change as $change_cat => $change_vals) {
            echo '<h3>',$change_cat,'</h3>';
            if (!empty($change_vals)) {
                echo '<ul class="main">';
                foreach ($change_vals as $val => $userids) {
                    if (!is_array($userids)) {
                        //error_log("studentpage.class.php::userids = {$userids}");
                        $userids = array($userids);
                    }
                    $num_users = sizeof($userids);
                    $fullnames = array_intersect_key($fullname_lookup,array_flip($userids));
                    asort($fullnames);

                    if ($num_users > 5) {
                        $details_id = str_replace('.','',uniqid(true));
                        echo '<li><a class="blk_user" href="javascript:toggle(\''.$details_id.'\');" title="',$lang_displayusers,'">',
                                $num_users,' ',
                                (($num_users === 1) ? $lang_user : $lang_users),
                                '</a> ',$lang_changed,' <span class="blk_val">',
                                ($change_cat == $lang_unenrol)
                                ? get_string('unenrolled', 'elis_program') : $val,
                                '</span></li>';

                        echo '<ul id="'.$details_id.'" class="sub">';
                        echo '<li>'.implode('</li><li>',$fullnames).'</li>';
                        echo '</ul>';
                    } elseif ($num_users > 0) {
                        echo '<li>';
                        echo '<span class="blk_user">',implode('</span>, <span class="blk_user">',$fullnames),'</span> ';
                        echo $lang_changed.' <span class="blk_val">',
                             ($change_cat == $lang_unenrol)
                             ? get_string('unenrolled', 'elis_program') : $val,
                             '</span></li>';
                    }
                    unset($fullnames);
                }
                echo '</ul>';
            }
        }
        echo '</div>';
        echo '<form method="post" action="index.php?s=stu&amp;section=curr&amp;id=' . $clsid . '" >'."\n";
        echo '<input type="hidden" name="action" value="updatemultiple" />'."<br />\n";
        echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />'."<br />\n";
        echo '<input type="submit" value="'.get_string('save_enrolment_changes', self::LANG_FILE).'">'."<br />\n";
        echo '</form>'."<br />\n";
    }

    /**
     *
     */
    function do_updatemultiple() { // action_updatemultiple
        global $DB, $SESSION;
        $clsid = $this->required_param('id', PARAM_INT);
        $users = pm_process_user_enrolment_data();  // ELIS-4089 -- JJF
        $pageid = optional_param('id', 1, PARAM_INT);
        $page = optional_param('s', '', PARAM_ALPHA);
        require_sesskey();

        $pagename = $page.$pageid.'bulkedit';
        if (!empty($SESSION->associationpage[$pagename])) {
            //adds users saved from other pages.
            if (!empty($SESSION->associationpage[$pagename]) && is_array($SESSION->associationpage[$pagename])) {
                foreach ($SESSION->associationpage[$pagename] as $userid => $sess_user) {
                    if (!isset($users[$userid])) {
                        if (!empty($sess_user->associd)) {
                            $users[$userid] = $this->sessuser2formuser($sess_user);
                        }
                    }
                }
            }
        }

        session_selection_deletion('bulkedit');

        foreach($users as $uid => $user) {

            if (!isset($user['association_id'])) {
                continue;
            }

            $sturecord            = array();
            $sturecord['id']      = $user['association_id'];
            $sturecord['classid'] = $clsid;
            $sturecord['userid']  = $uid;

            if (!empty($user['startyear']) && !empty($user['startmonth']) && !empty($user['startday'])) {
                $startyear  = $user['startyear'];
                $startmonth = $user['startmonth'];
                $startday   = $user['startday'];
                $sturecord['enrolmenttime'] = pm_timestamp(0, 0, 0, $startmonth, $startday, $startyear);
            }

            if (!empty($user['endyear']) && !empty($user['endmonth']) && !empty($user['endday'])) {
                $endyear  = $user['endyear'];
                $endmonth = $user['endmonth'];
                $endday   = $user['endday'];
                $sturecord['completetime'] = pm_timestamp(0, 0, 0, $endmonth, $endday, $endyear);
            }

            if (isset($user['completestatusid'])) {
                $sturecord['completestatusid'] = $user['completestatusid'];
            }

            if (isset($user['grade'])) {
                $sturecord['grade'] = $user['grade'];
            }

            if (isset($user['credits'])) {
                $sturecord['credits'] = $user['credits'];
            }

            if (isset($user['locked'])) {
                $sturecord['locked'] = !empty($user['locked']) ? 1 : 0;
            }

            $stu = new student($sturecord);

            if ($stu->completestatusid == STUSTATUS_PASSED &&
                $DB->get_field(student::TABLE, 'completestatusid', array('id' => $stu->id)) != STUSTATUS_PASSED) {
                $stu->complete();
            } else {
                $status = $stu->update();
                if ($status !== true) {
                    echo cm_error(get_string('record_not_updated', self::LANG_FILE, array('message'=>$status)));
                }
            }

            // Now once we've done all this, delete the student if we've been asked to
            if (isset($user['unenrol']) && pmclasspage::can_enrol_into_class($clsid)) {
                $stu_delete = new student($sturecord); // TBD: param was $user['association_id']
                $status = $stu_delete->delete();
                if(!$status) {
                    $user = $DB->get_record(user::TABLE, array('id' => $stu->userid));
                    $sparam = new stdClass;
                    $sparam->name = fullname($user);
                    echo cm_error(get_string('studentnotunenrolled', self::LANG_FILE, $sparam));
                }
            }
        }

        $studentpage = new studentpage();
        $target = $studentpage->get_new_page(array('action' => 'default', 'id' => $clsid));
        redirect($target->url);
    }

    /**
     * Confirm saving a user to the wait list
     *
     * @param boolean $redirect Redirect to next destination page if true
     *                          (needed to prevent redirects in unit testing)
     */
    public function do_waitlistconfirm($redirect = true) { // action_waitlistconfirm
        $id = $this->required_param_array('userid', PARAM_INT);

        $form_url = new moodle_url(null, array('s'       => $this->pagename,
                                               'section' => $this->section,
                                               'action'  => 'waitlistconfirm'));
        $waitlistform = new waitlistaddform($form_url, array('student_ids'=>$id));
        if($data = $waitlistform->get_data()) {
            $now = time();

            foreach($data->userid as $uid) {
                if(isset($data->enrol[$uid]) &&
                    isset($data->classid[$uid]) &&
                    isset($data->enrolmenttime[$uid])) {

                    if($data->enrol[$uid] == 1) {
                        $wait_record = new object();
                        $wait_record->userid = $uid;
                        $wait_record->classid = $data->classid[$uid];
                        //todo: remove? (not an actual db field)
                        $wait_record->enrolmenttime = $data->enrolmenttime[$uid];
                        $wait_record->timecreated = $now;
                        $wait_record->timemodified = $now;
                        $wait_record->position = 0;

                        $wait_list = new waitlist($wait_record);
                        $wait_list->save();
                    } else if($data->enrol[$uid] == 2) {
                        $user = new user($uid);
                        $student_data= array();
                        $student_data['classid'] = $data->classid[$uid];
                        $student_data['userid'] = $uid;
                        $student_data['enrolmenttime'] = $data->enrolmenttime[$uid];
                        $student_data['timecreated'] = $now;
                        $student_data['completestatusid'] = STUSTATUS_NOTCOMPLETE;

                        $newstu = new student($student_data);
                        $newstu->validation_overrides[] = 'prerequisites';
                        $newstu->validation_overrides[] = 'enrolment_limit';
                        try {
                            $status = $newstu->save();
                            echo cm_error(get_string('record_not_created',
                                                     self::LANG_FILE));
                        } catch (Exception $e) {
                            $param = array('message' => $e->getMessage());
                            echo cm_error(get_string('record_not_created_reason', self::LANG_FILE, $param));
                        }
                    }
                }
            }
        }

        if ($redirect) {
            //todo: remove?
            if (is_array($id)) {
                $target_id = array_shift($id);
            } else {
                $target_id = $id;
            }

            $target = $this->get_new_page(array('action' => 'default', 'id' => $this->required_param('id', PARAM_INT)));
            redirect($target->url);
        }
    }

    /**
     *
     * @global <type> $CFG
     * @uses $CFG
     * @uses $OUTPUT
     */
    function display_default() { // action_default (and above)
        global $CFG, $OUTPUT;

        $clsid        = $this->required_param('id', PARAM_INT);
        $sort         = $this->optional_param('sort', 'name', PARAM_ALPHANUM);
        $dir          = $this->optional_param('dir', 'ASC', PARAM_ALPHA);
        $page         = $this->optional_param('page', 0, PARAM_INT);
        $perpage      = $this->optional_param('perpage', 30, PARAM_INT); // how many per page
        $namesearch   = trim($this->optional_param('search', '', PARAM_TEXT));
        $alpha        = $this->optional_param('alpha', '', PARAM_ALPHA);

        $cls = new pmclass($clsid);

        // TBD: see student.class.php
        $columns = array(
            'idnumber'         => array('header' => get_string('student_idnumber', self::LANG_FILE)),
            'name'             => array('header' => get_string('student_name_1', self::LANG_FILE)),
            'enrolmenttime'    => array('header' => get_string('enrolment_time', self::LANG_FILE)),
            'completetime'     => array('header' => get_string('completion_time', self::LANG_FILE)),
            'completestatusid' => array('header' => get_string('student_status', self::LANG_FILE)),
            'grade'            => array('header' => get_string('student_grade', self::LANG_FILE)),
            'credits'          => array('header' => get_string('student_credits', self::LANG_FILE)),
            'locked'           => array('header' => get_string('student_locked', self::LANG_FILE)),
            'buttons'          => array('header' => '', 'sortable' => false),
            );

        // TBD
        if ($dir !== 'DESC') {
            $dir = 'ASC';
        }
        if (isset($columns[$sort])) {
            $columns[$sort]['sortable'] = $dir;
        } else {
            $sort = 'name';
            $columns[$sort]['sortable'] = $dir;
        }

        $stus    = student_get_listing($clsid, $sort, $dir, $page*$perpage, $perpage, $namesearch, $alpha);
        $numstus = student_count_records($clsid, $namesearch, $alpha);

        $this->print_num_items($clsid, $cls->maxstudents);

        $this->print_alpha();
        $this->print_search();

        if (!$numstus) {
            pmshowmatches($alpha, $namesearch, null, 'no_student_matching');
        }

        //note: this does actually use the third parameter right now because
        //we have customized this function - consider using standard version
        //here an in the instructor page?
        $this->print_list_view($stus, $columns, 'users'); // TBD: students ?

        $pagingbar = new paging_bar($numstus, $page, $perpage,
                         "index.php?s=stu&amp;section=curr&amp;id=$clsid&amp;sort=$sort&amp;" .
                         "dir=$dir&amp;perpage=$perpage&amp;alpha=$alpha&amp;search="
                         . urlencode($namesearch)); // .'&amp;'
        echo $OUTPUT->render($pagingbar);

        echo "<form>";
        // TODO: pass in query parameters
        if ($this->can_do('bulkedit')) {
            echo "<input type=\"button\" onclick=\"document.location='index.php?s=stu&amp;section=curr&amp;".
                "action=bulkedit&amp;id=$clsid&amp;sort=$sort&amp;dir=$dir&amp;perpage=$perpage&amp;alpha={$alpha}&amp;search=".
                urlencode($namesearch)."';\" value=\"".get_string('bulkedit', 'elis_program')."\" />";
        }
        if ($this->can_do('add')) {
            echo "<input type=\"button\" onclick=\"document.location='index.php?s=stu&amp;section=curr&amp;" .
                "action=add&amp;id=$clsid';\" value=\"" . get_string('enrolstudents', self::LANG_FILE) . "\" />";
        }
        echo "</form>";
    }

    /**
     * Prints out the page that displays a list of records returned from a query.
     * @param $items array of records to print
     * @param $columns associative array of column id => column heading text
     */
    function print_list_view($items, $columns) { // TBD
        global $CFG;

        $id = $this->required_param('id', PARAM_INT);

        if (empty($items)) {
            //do not output a notice that no elements are found because this is handled by pmshowmatched
            return;
        }

        $table = $this->create_table_object($items, $columns);
        echo $table->get_html();
    }

    public function create_table_object($items, $columns /*, $formatters */) {
        return new student_table($items, $columns, $this);
    }

    public function get_waitlistform($students) {
        $target = $this->get_new_page(array('action' => 'waitlistconfirm'), true);
        $student = current($students);
        $data = $student->pmclass;
        $waitlistform = new waitlistaddform($target->url,
                                array('obj' => $data, 'students' => $students));
        $waitlistform->display();
    }

    function get_view_form($clsid, $type, $sort, $dir, $page, $perpage, $namesearch, $alpha) {
        $output = '';

        $newstu = new student();
        $newstu->classid = $clsid;

        $output .= $newstu->view_form_html($clsid, $type, $sort, $dir, $page, $perpage, $namesearch, $alpha);

        return $output;
    }

    function print_add_form($cmclass) {
        $type         = $this->optional_param('stype', '', PARAM_ALPHA);
        $sort         = $this->optional_param('sort', 'name', PARAM_ALPHANUM);
        $dir          = $this->optional_param('dir', 'ASC', PARAM_ALPHA);
        $page         = $this->optional_param('page', 0, PARAM_INT);
        $perpage      = $this->optional_param('perpage', 30, PARAM_INT); // how many per page
        $namesearch   = trim($this->optional_param('search', '', PARAM_TEXT));
        $alpha        = $this->optional_param('alpha', '', PARAM_ALPHA);

        $newstu          = new student();
        $newstu->classid = $cmclass->id;
        echo $newstu->edit_classid_html($cmclass->id, $type, $sort, $dir, $page, $perpage, $namesearch, $alpha);
    }

    function print_edit_form($stu, $cls, $sort, $dir) {
        $stu->classid = $cls->id; // TBD
        echo $stu->edit_student_html($stu->id, '', $sort, $dir);
    }

    /**
     * Returns the delete student form.
     *
     * @param int $id The ID of the student.
     * @return string HTML for the form.
     *
     */
    function print_delete_form($stu) {
        global $DB;
        $url        = 'index.php'; // TBD: '/elis/program/index.php'
        $a          = new stdClass;
        $user       = $DB->get_record(user::TABLE, array('id' => $stu->userid));
        $a->name    = fullname($user);
        $message    = get_string('student_deleteconfirm', self::LANG_FILE, $a);
        $optionsyes = array('s' => 'stu', 'section' => 'curr', 'id' => $stu->classid,
                            'action' => 'delete', 'association_id' => $stu->id, 'confirm' => md5($stu->id)); // TBD - was: 'action' => 'confirm'
        $optionsno  = array('s' => 'stu', 'section' => 'curr', 'id' => $stu->classid);

        echo cm_delete_form($url, $message, $optionsyes, $optionsno);
    }

    /**
     * override print_num_items to display the max number of students allowed in this class
     *
     * @param int $numitems max number of students
     */
    function print_num_items($classid, $max) {
        $pmclass = new pmclass($classid);
        $students = pmclass::get_completion_counts($classid);

        if(!empty($students[STUSTATUS_FAILED])) {
            echo '<div style="float:right;">' . get_string('num_students_failed', self::LANG_FILE) . ': ' . $students[STUSTATUS_FAILED] . '</div><br />';
        }

        if(!empty($students[STUSTATUS_PASSED])) {
            echo '<div style="float:right;">' . get_string('num_students_passed', self::LANG_FILE) . ': ' . $students[STUSTATUS_PASSED] . '</div><br />';
        }

        if(!empty($students[STUSTATUS_NOTCOMPLETE])) {
            echo '<div style="float:right;">' . get_string('num_students_not_complete', self::LANG_FILE) . ': ' . $students[STUSTATUS_NOTCOMPLETE] . '</div><br />';
        }

        if(!empty($max)) {
            echo '<div style="float:right;">' . get_string('num_max_students',
                 self::LANG_FILE) . ': ' . $max . '</div><br />';
        }
    }
}

class student_table extends association_page_table {
    const LANG_FILE = 'elis_program';

    function get_item_display_enrolmenttime($column, $item) {
        return get_date_item_display($column, $item);
    }

    function get_item_display_completetime($column, $item) {
        if ($item->completestatusid == STUSTATUS_NOTCOMPLETE) {
            return '-';
        } else {
            return get_date_item_display($column, $item);
        }
    }

    function get_item_display_completestatusid($column, $id) {
        $val = $id->completestatusid; // $id->$column
        $status = is_numeric($val) ? student::$completestatusid_values[$val] : $val;
        //error_log("student_table::get_item_display_completestatusid() id->completestatusid = {$val}, '{$status}'");
        return get_string($status, self::LANG_FILE);
    }

    function get_item_display_locked($column, $id) {
        return get_yesno_item_display($column, $id);
    }

    function is_column_wrapped_idnumber() {
        return true;
    }

    function is_column_wrapped_name() {
        return true;
    }

    function get_item_display_idnumber($column, $item) {
        global $CFG, $USER;

        $usermanagementpage = new userpage();
        if ($usermanagementpage->can_do_view()) {
            $target = $usermanagementpage->get_new_page(array('action' => 'view', 'id' => $item->userid));
            $link = $target->url;
            $elisprofilestr = get_string('elis_profile', 'elis_program');
            $elis_link_begin = '<a href="'.$link.'" alt="'.$elisprofilestr.'" title="'.$elisprofilestr.'">';
            $elis_link_end = '</a>';
        } else {
            $elis_link_begin = '';
            $elis_link_end = '';
        }

        return $elis_link_begin.$item->idnumber.$elis_link_end;
    }

    function get_item_display_name($column, $item) {
        global $CFG, $OUTPUT, $USER;

        $mdluid = cm_get_moodleuserid($item->userid);
        if (!empty($mdluid) && has_capability('moodle/user:viewdetails', get_context_instance(CONTEXT_USER, $USER->id))) {
            $moodleprofilestr = get_string('moodle_profile', 'elis_program');
            $moodle_link_begin = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$mdluid.'" alt="'.$moodleprofilestr.'" title="'.$moodleprofilestr.'">';
            $moodle_link_end = ' <img src="'.$OUTPUT->pix_url('i/moodle_host').'" alt="'.$moodleprofilestr.'" title="'.$moodleprofilestr.'" /></a>';
        } else {
            $moodle_link_begin = '';
            $moodle_link_end = '';
        }

        return $moodle_link_begin.$item->name.$moodle_link_end;
    }

}

