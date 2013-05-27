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

require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once($CFG->libdir .'/gradelib.php');

class elis_graded_users_iterator {

    /**
     * The couse whose users we are interested in
     */
    protected $course;

    /**
     * An array of grade items or null if only user data was requested
     */
    protected $grade_items;

    /**
     * The group ID we are interested in. 0 means all groups.
     */
    protected $groupid;

    /**
     * A recordset of graded users
     */
    protected $users_rs;

    /**
     * A recordset of user grades (grade_grade instances)
     */
    protected $grades_rs;

    /**
     * Array used when moving to next user while iterating through the grades recordset
     */
    protected $gradestack;

    /**
     * The first field of the users table by which the array of users will be sorted
     */
    protected $sortfield1;

    /**
     * Should sortfield1 be ASC or DESC
     */
    protected $sortorder1;

    /**
     * The second field of the users table by which the array of users will be sorted
     */
    protected $sortfield2;

    /**
     * Should sortfield2 be ASC or DESC
     */
    protected $sortorder2;

    /**
     * Should users whose enrolment has been suspended be ignored?
     */
    protected $onlyactive = false;

    /**
     * ELIS-7965: limit queries to only specified userids (array)
     */
    protected $req_users;

    /**
     * Constructor
     *
     * @param object $course A course object
     * @param array  $grade_items array of grade items, if not specified only user info returned
     * @param int    $groupid iterate only group users if present
     * @param string $sortfield1 The first field of the users table by which the array of users will be sorted
     * @param string $sortorder1 The order in which the first sorting field will be sorted (ASC or DESC)
     * @param string $sortfield2 The second field of the users table by which the array of users will be sorted
     * @param string $sortorder2 The order in which the second sorting field will be sorted (ASC or DESC)
     * @param mixed  $req_users  Optional array of desired userids - empty for all (default)
     */
    public function __construct($course, $grade_items = null, $groupid = 0,
                                $sortfield1 = 'lastname', $sortorder1 = 'ASC',
                                $sortfield2 = 'firstname', $sortorder2 = 'ASC',
                                $req_users = null) {
        $this->course      = $course;
        $this->grade_items = $grade_items;
        $this->groupid     = $groupid;
        $this->sortfield1  = $sortfield1;
        $this->sortorder1  = $sortorder1;
        $this->sortfield2  = $sortfield2;
        $this->sortorder2  = $sortorder2;

        $this->gradestack  = array();
        $this->req_users   = $req_users;
    }

    /**
     * Initialise the iterator
     *
     * @return boolean success
     */
    public function init() {
        global $CFG, $DB;

        $this->close();

        grade_regrade_final_grades($this->course->id);
        $course_item = grade_item::fetch_course_item($this->course->id);
        if ($course_item->needsupdate) {
            // can not calculate all final grades - sorry
            return false;
        }

        $coursecontext = get_context_instance(CONTEXT_COURSE, $this->course->id);
        $relatedcontexts = get_related_contexts_string($coursecontext);

        list($gradebookroles_sql, $params) =
            $DB->get_in_or_equal(explode(',', $CFG->gradebookroles), SQL_PARAMS_NAMED, 'grbr');
        list($enrolledsql, $enrolledparams) = get_enrolled_sql($coursecontext, '', 0, $this->onlyactive);

        $params = array_merge($params, $enrolledparams);

        if ($this->groupid) {
            $groupsql = "INNER JOIN {groups_members} gm ON gm.userid = u.id";
            $groupwheresql = "AND gm.groupid = :groupid";
            // $params contents: gradebookroles
            $params['groupid'] = $this->groupid;
        } else {
            $groupsql = "";
            $groupwheresql = "";
        }

        if (empty($this->sortfield1)) {
            // we must do some sorting even if not specified
            $ofields = ", u.id AS usrt";
            $order   = "usrt ASC";

        } else {
            $ofields = ", u.$this->sortfield1 AS usrt1";
            $order   = "usrt1 $this->sortorder1";
            if (!empty($this->sortfield2)) {
                $ofields .= ", u.$this->sortfield2 AS usrt2";
                $order   .= ", usrt2 $this->sortorder2";
            }
            if ($this->sortfield1 != 'id' and $this->sortfield2 != 'id') {
                // user order MUST be the same in both queries,
                // must include the only unique user->id if not already present
                $ofields .= ", u.id AS usrt";
                $order   .= ", usrt ASC";
            }
        }

        // ELIS-7965: requested specific userids (array)
        $req_users = '';
        if (!empty($this->req_users)) {
            $req_users = 'AND u.id IN ('. implode(',', $this->req_users) .')';
        }

        // $params contents: gradebookroles and groupid (for $groupwheresql)
        $users_sql = "SELECT u.* $ofields
                        FROM {user} u
                        JOIN ($enrolledsql) je ON je.id = u.id
                             $groupsql
                        JOIN (
                                  SELECT DISTINCT ra.userid
                                    FROM {role_assignments} ra
                                   WHERE ra.roleid $gradebookroles_sql
                                     AND ra.contextid $relatedcontexts
                             ) rainner ON rainner.userid = u.id
                         WHERE u.deleted = 0 {$req_users}
                             $groupwheresql
                    ORDER BY $order";
        $this->users_rs = $DB->get_recordset_sql($users_sql, $params);

        if (!empty($this->grade_items)) {
            $itemids = array_keys($this->grade_items);
            list($itemidsql, $grades_params) = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED, 'items');
            $params = array_merge($params, $grades_params);
            // $params contents: gradebookroles, enrolledparams, groupid (for $groupwheresql) and itemids

            $grades_sql = "SELECT g.* $ofields
                             FROM {grade_grades} g
                             JOIN {user} u ON g.userid = u.id
                             JOIN ($enrolledsql) je ON je.id = u.id
                                  $groupsql
                             JOIN (
                                      SELECT DISTINCT ra.userid
                                        FROM {role_assignments} ra
                                       WHERE ra.roleid $gradebookroles_sql
                                         AND ra.contextid $relatedcontexts
                                  ) rainner ON rainner.userid = u.id
                              WHERE u.deleted = 0 {$req_users}
                              AND g.itemid $itemidsql
                              $groupwheresql
                         ORDER BY $order, g.itemid ASC";
            $this->grades_rs = $DB->get_recordset_sql($grades_sql, $params);
        } else {
            $this->grades_rs = false;
        }

        return true;
    }

    /**
     * Returns information about the next user
     * @return mixed array of user info, all grades and feedback or null when no more users found
     */
    public function next_user() {
        if (!$this->users_rs) {
            return false; // no users present
        }

        if (!$this->users_rs->valid()) {
            if ($current = $this->_pop()) {
                // this is not good - user or grades updated between the two reads above :-(
            }

            return false; // no more users
        } else {
            $user = $this->users_rs->current();
            $this->users_rs->next();
        }

        // find grades of this user
        $grade_records = array();
        while (true) {
            if (!$current = $this->_pop()) {
                break; // no more grades
            }

            if (empty($current->userid)) {
                break;
            }

            if ($current->userid != $user->id) {
                // grade of the next user, we have all for this user
                $this->_push($current);
                break;
            }

            $grade_records[$current->itemid] = $current;
        }

        $grades = array();
        $feedbacks = array();

        if (!empty($this->grade_items)) {
            foreach ($this->grade_items as $grade_item) {
                if (!isset($feedbacks[$grade_item->id])) {
                    $feedbacks[$grade_item->id] = new stdClass();
                }
                if (array_key_exists($grade_item->id, $grade_records)) {
                    $feedbacks[$grade_item->id]->feedback       = $grade_records[$grade_item->id]->feedback;
                    $feedbacks[$grade_item->id]->feedbackformat = $grade_records[$grade_item->id]->feedbackformat;
                    unset($grade_records[$grade_item->id]->feedback);
                    unset($grade_records[$grade_item->id]->feedbackformat);
                    $grades[$grade_item->id] = new grade_grade($grade_records[$grade_item->id], false);
                } else {
                    $feedbacks[$grade_item->id]->feedback       = '';
                    $feedbacks[$grade_item->id]->feedbackformat = FORMAT_MOODLE;
                    $grades[$grade_item->id] =
                        new grade_grade(array('userid'=>$user->id, 'itemid'=>$grade_item->id), false);
                }
            }
        }

        $result = new stdClass();
        $result->user      = $user;
        $result->grades    = $grades;
        $result->feedbacks = $feedbacks;
        return $result;
    }

    /**
     * Close the iterator, do not forget to call this function
     */
    public function close() {
        if ($this->users_rs) {
            $this->users_rs->close();
            $this->users_rs = null;
        }
        if ($this->grades_rs) {
            $this->grades_rs->close();
            $this->grades_rs = null;
        }
        $this->gradestack = array();
    }

    /**
     * Should all enrolled users be exported or just those with an active enrolment?
     *
     * @param bool $onlyactive True to limit the export to users with an active enrolment
     */
    public function require_active_enrolment($onlyactive = true) {
        if (!empty($this->users_rs)) {
            debugging('Calling require_active_enrolment() has no effect unless you call init() again', DEBUG_DEVELOPER);
        }
        $this->onlyactive  = $onlyactive;
    }


    /**
     * Add a grade_grade instance to the grade stack
     *
     * @param grade_grade $grade Grade object
     *
     * @return void
     */
    private function _push($grade) {
        array_push($this->gradestack, $grade);
    }


    /**
     * Remove a grade_grade instance from the grade stack
     *
     * @return grade_grade current grade object
     */
    private function _pop() {
        global $DB;
        if (empty($this->gradestack)) {
            if (empty($this->grades_rs) || !$this->grades_rs->valid()) {
                return null; // no grades present
            }

            $current = $this->grades_rs->current();

            $this->grades_rs->next();

            return $current;
        } else {
            return array_pop($this->gradestack);
        }
    }
}

/**Callback function for ELIS Config/admin: Cluster Group Settings
 *
 * @param string $name  the fullname of the parameter that changed
 * @uses  $DB
 */
function cluster_groups_changed($name) {
    global $DB;
    $shortname = substr($name, strpos($name, 'elis_program_') + strlen('elis_program_'));
    // TBD: following didn't work?
    //$value = elis::$config->elis_program->$shortname;
    $value = $DB->get_field('config_plugins', 'value',
                            array('plugin' => 'elis_program',
                                  'name'   => $shortname));
    //error_log("/elis/program/lib/lib.php::cluster_groups_changed({$name}) {$shortname} = '{$value}'");
    if (!empty($value)) {
        $event = 'crlm_'. $shortname .'_enabled';
        error_log("Triggering event: $event");
        events_trigger($event, 0);
    }
}

/**
 * Prints the 'All A B C ...' alphabetical filter bar.
 *
 * @param object $moodle_url the moodle url object for the alpha/letter links
 * @param string $pname      the parameter name to be appended to the moodle_url
 *                           i.e. 'pname=alpha'
 * @param string $label      optional label - defaults to none
 */
function pmalphabox($moodle_url, $pname = 'alpha', $label = null) {
    $alpha    = optional_param($pname, null, PARAM_ALPHA);
    $alphabet = explode(',', get_string('alphabet', 'langconfig'));
    $strall   = get_string('all');

    echo html_writer::start_tag('div', array('style' => 'text-align:center'));
    if (!empty($label)) {
        echo $label, ' '; // TBD: html_writer::???
    }
    if ($alpha) {
        $url = clone($moodle_url); // TBD
        $url->remove_params($pname);
        echo html_writer::link($url, $strall);
    } else {
        echo html_writer::tag('b', $strall);
    }

    foreach ($alphabet as $letter) {
        if ($letter == $alpha) {
            echo ' ', html_writer::tag('b', $letter);
        } else {
            $url = clone($moodle_url); // TBD
            // Set current page to 0
            $url->params(array($pname => $letter, 'page' => 0));
            echo ' ', html_writer::link($url, $letter);
        }
    }

    echo html_writer::end_tag('div');
}

/**
 * Prints the text substring search interface.
 *
 * @param object|string $page_or_url the page object for the search form action
 *                                   or the url string.
 * @param string $searchname         the parameter name for the search tag
 *                                   i.e. 'searchname=search'
 * @param string $method             the form submit method: get(default)| post
 *                                   TBD: 'post' method flakey, doesn't always work!
 * @param string $showall            label for the 'Show All' link - optional
 *                                   defaults to get_string('showallitems' ...
 * @param string $extra              extra html for input fields displayed BEFORE search fields. i.e. student.class.php::edit_form_html()
 *                                   $extra defaults to none.
 * @uses $_GET
 * @uses $_POST
 * @uses $CFG
 * @todo convert echo HTML statements to use M2 html_writer, etc.
 * @todo support moodle_url as 1st parameter and not just string url.
 */
function pmsearchbox($page_or_url = null, $searchname = 'search', $method = 'get', $showall = null, $extra = '') {
    global $CFG;
    $search = trim(optional_param($searchname, '', PARAM_TEXT));

    $params = $_GET;
    unset($params['page']);      // TBD: Do we want to go back to the first page
    unset($params[$searchname]); // And clear the search ???
    if (isset($params['mode']) && $params['mode'] == 'bare') {
        unset($params['mode']);
    }
    if (empty($params)) {
        //error_log("pmsearchbox() _GET empty using _POST");
        $params = $_POST;
        unset($params['page']);      // TBD: Do we want to go back to the first page
        unset($params[$searchname]); // And clear the search ???
        if (isset($params['mode']) && $params['mode'] == 'bare') {
            unset($params['mode']);
        }
    }

    $target = is_object($page_or_url) ? $page_or_url->get_new_page($params)->url
                                      : get_pm_url($page_or_url, $params);
    if (method_exists($target, 'remove_params')) {
        $target->remove_params($searchname); // TBD: others too???
        $existingparams = $target->params();
        if (isset($existingparams['mode']) && $existingparams['mode'] == 'bare') {
            $target->remove_params('mode');
        }
    }
    $query_pos = strpos($target, '?');
    $action_url = ($query_pos !== false) ? substr($target, 0, $query_pos)
                                         : $target;
    echo '<table class="searchbox" style="margin-left:auto;margin-right:auto" cellpadding="10"><tr><td>'; // TBD: style ???
    echo "<form action=\"{$action_url}\" method=\"{$method}\">";
    echo '<fieldset class="invisiblefieldset">';
    // TBD: merge parameters from $target - if exists
    foreach($params as $key => $val) {
        echo "<input type=\"hidden\" name=\"{$key}\" value=\"{$val}\" />";
        if ($query_pos === false) {
            $target .= (strpos($target, '?') === false) ? '?' : '&';
            $target .= "{$key}={$val}"; // required for onclick, below
        }
    }
    if (!empty($extra)) {
        echo $extra;
    }
    echo "<input type=\"text\" name=\"{$searchname}\" value=\"" . s($search, true) . '" size="20" />';
    echo '<input type="submit" value="'.get_string('search').'" />';

    if ($search) {
        if (empty($showall)) {
            $showall = get_string('showallitems', 'elis_program');
        }
        echo "<input type=\"button\" onclick=\"document.location='{$target}';\" value=\"{$showall}\" />";
        //error_log("/elis/program/lib/lib.php::pmsearchbox() show_all_url = {$target}");
    }

    echo '</fieldset></form>';
    echo '</td></tr></table>';
}

/**
 * Prints the current 'alpha' and 'search' settings for no table entries
 *
 * @param string $alpha         the current alpha/letter match
 * @param string $namesearch    the current string search
 * @param string $matchlabel    optional get_string identifier for label prefix of match settings
 *                              default get_string('name_lower_case', 'elis_program')
 * @param string $nomatchlabel  optional get_string identifier for label prefix of no matches
 *                              default get_string('no_users_matching', 'elis_program')
 */
function pmshowmatches($alpha, $namesearch, $matchlabel = null, $nomatchlabel = null) {
    //error_log("pmshowmatches({$alpha}, {$namesearch}, {$matchlabel}, {$nomatchlabel})");
    if (empty($matchlabel)) {
        $matchlabel = 'name_lower_case';
    }
    if (empty($nomatchlabel)) {
        $nomatchlabel = 'no_item_matching';
    }
    $match = array();
    if ($namesearch !== '') {
        $match[] = '<b>'. s($namesearch) .'</b>';
    }
    if ($alpha) {
        $match[] = get_string($matchlabel, 'elis_program') .": <b>{$alpha}___</b>";
    }

    $matchstring = implode(", ", $match);
    $sparam = new stdClass;
    $sparam->match = $matchstring;
    echo get_string($nomatchlabel, 'elis_program', $sparam), '<br/>'; // TBD
}

/** Function to return pm page url with required params
 *
 * @param   string|moodle_url  $baseurl  the pages base url
 *                             defaults to: '/elis/program/index.php'
 * @param   array              $extras   extra parameters for url.
 * @return  moodle_url         the baseurl with required params
 */
function get_pm_url($baseurl = null, $extras = array()) {
    if (empty($baseurl)) {
        $baseurl = '/elis/program/index.php';
    }
    $options = array('s', 'id', 'action', 'section', 'alpha', 'search', 'perpage', 'class', 'association_id', 'mode', '_assign'); // TBD: add more parameters as required: page, [sort, dir] ???
    $params = array();
    foreach ($options as $option) {
        $val = optional_param($option, null, PARAM_CLEAN);
        if ($val != null) {
            $params[$option] = $val;
        }
    }
    foreach ($extras as $key => $val) {
        $params[$key] = $val;
    }
    return new moodle_url($baseurl, $params);
}

/**
 * New display function callback to allow HTML elements in table
 * see: /elis/core/lib/table.class.php
 */
function htmltab_display_function($column, $item) {
    return isset($item->{$column}) ? $item->{$column} : '';
}

/**
 * display function - originally a method in table.class.php
 * see ELIS_1.9x:/curriculum/lib/table.class.php
 */
function get_date_item_display($column, $item) {
    if (empty($item->$column)) {
        return '-';
    } else {
        $timestamp = $item->$column;
        return is_numeric($timestamp)
               ? userdate($timestamp, get_string('pm_date_format', 'elis_program'))
               : '';
    }
}

/**
 * display function - originally a method in table.class.php
 * see ELIS_1.9x:/curriculum/lib/table.class.php
 */
function get_yesno_item_display($column, $item) {
    return get_string($item->$column ? 'yes' : 'no');
}

/**
 *
 * Call Moodle's set_config with 3rd parm 'elis_program'
 *
 * @param string $name the key to set
 * @param string $value the value to set (without magic quotes)
 * @return n/a
 */
function pm_set_config($name, $value) {
    set_config($name,$value, 'elis_program');
}

/**
 * Synchronize Moodle enrolments over to the PM system based on associations of Moodle
 * courses to PM classes, as well as converting grade item grades to learning objective grades
 *
 * @param int $moodleuserid The id of the specific Moodle user to sync, or 0 for
 *                          all users
 */
function pm_synchronize_moodle_class_grades($moodleuserid = 0) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/grade/lib.php');
    require_once(elispm::lib('data/classmoodlecourse.class.php'));

    if ($moodleclasses = moodle_get_classes()) {
        $timenow = time();

        //if we are filtering for a specific user, add the necessary SQL fragment
        $outerusercondition = '';
        $innerusercondition = '';
        $userparams = array();

        if ($moodleuserid != 0) {
            $outerusercondition = "AND u.id = :userid";
            $innerusercondition = "AND mu.id = :userid";
            $userparams['userid'] = $moodleuserid;
        }

        foreach ($moodleclasses as $class) {
            $pmclass = $class->pmclass;

            $context = get_context_instance(CONTEXT_COURSE, $class->moodlecourseid);
            $moodlecourse = $DB->get_record('course', array('id' => $class->moodlecourseid));

            // Get CM enrolment information (based on Moodle enrolments)
            // IMPORTANT: this record set must be sorted using the Moodle
            // user ID
            $relatedcontextsstring = get_related_contexts_string($context);
            $sql = "SELECT DISTINCT u.id AS muid, u.username, cu.id AS cmid, stu.*
                      FROM {user} u
                      JOIN {role_assignments} ra ON u.id = ra.userid
                 LEFT JOIN {".user::TABLE."} cu ON cu.idnumber = u.idnumber
                 LEFT JOIN {".student::TABLE."} stu on stu.userid = cu.id AND stu.classid = {$pmclass->id}
                     WHERE ra.roleid in ({$CFG->gradebookroles})
                       AND ra.contextid {$relatedcontextsstring}
                       {$outerusercondition}
                  ORDER BY muid ASC";

            $causers = $DB->get_recordset_sql($sql, $userparams);

            if (empty($causers)) {
                // nothing to see here, move on
                continue;
            }

            /// Get CM completion elements and related Moodle grade items
            $comp_elements = array();
            $gis = array();

            //this will store the initial valid state of the $elements recordset, since once we loop
            //through it the valid state turns false
            $compelems_valid = false;

            if (isset($pmclass->course) && (get_class($pmclass->course) == 'course')) {

                $elements = $pmclass->course->get_completion_elements();
                $compelems_valid = (!empty($elements) && $elements->valid() === true) ? true : false;
                foreach ($elements as $element) {
                    // In Moodle 2.4 the idnumber in grade_items *maybe* a foreign key index into course_modules
                    // so we must check for both possibilities
                    $params1 =  array(
                        'courseid' => $class->moodlecourseid,
                        'idnumber' => $element->idnumber
                    );
                    $idnumber = $DB->get_field('course_modules', 'id', array('idnumber' => $element->idnumber));
                    $params2 =  array(
                        'courseid' => $class->moodlecourseid,
                        'idnumber' => $idnumber
                    );
                    if ($gi = $DB->get_record('grade_items', $params1)) {
                        $gis[$gi->id] = $gi;
                        $comp_elements[$gi->id] = $element;
                    } else if ($idnumber && ($gi = $DB->get_record('grade_items', $params2))) {
                        $gis[$gi->id] = $gi;
                        $comp_elements[$gi->id] = $element;
                    }
                }
                unset($elements);
            }
            // add grade item for the overall course grade
            $coursegradeitem = grade_item::fetch_course_item($moodlecourse->id);
            $gis[$coursegradeitem->id] = $coursegradeitem;

            if ($coursegradeitem->grademax == 0) {
                // no maximum course grade, so we can't calculate the
                // student's grade
                continue;
            }

            if (!empty($compelems_valid) && $compelems_valid === true) {
                // get current completion element grades if we have any
                // IMPORTANT: this record set must be sorted using the Moodle
                // user ID

                //todo: use table constant
                $sql = "SELECT grades.*, mu.id AS muid
                          FROM {crlm_class_graded} grades
                    INNER JOIN {".user::TABLE."} cu ON grades.userid = cu.id
                    INNER JOIN {user} mu ON cu.idnumber = mu.idnumber
                         WHERE grades.classid = :classid
                         {$innerusercondition}
                      ORDER BY mu.id ASC";

                //apply the userid parameter for performance reasons
                $params = array_merge(array('classid' => $pmclass->id), $userparams);
                $allcompelemgrades = $DB->get_recordset_sql($sql, $params);
                $last_rec = null; // will be used to store the last completion
                                  // element that we fetched from the
                                  // previous iteration (which may belong
                                  // to the current user)
            }

            // get the Moodle course grades
            // IMPORTANT: this iterator must be sorted using the Moodle
            // user ID
            $gradedusers = new elis_graded_users_iterator($moodlecourse, $gis, 0, 'id', 'ASC', '', '', $moodleuserid ? array($moodleuserid) : null);
            $gradedusers->init();

            // only create a new enrolment record if there is only one CM
            // class attached to this Moodle course
            $doenrol = ($DB->count_records(classmoodlecourse::TABLE, array('moodlecourseid' => $class->moodlecourseid)) == 1);

            // main loop -- go through the student grades
            foreach ($causers as $sturec) {
                if (!$stugrades = $gradedusers->next_user()) {
                    break;
                }

                // skip user records that don't match up
                // (this works since both sets are sorted by Moodle user ID)
                // (in theory, we shouldn't need this, but just in case...)
                // Verify that we have stugrades before continuing...
                if (!is_object($stugrades)) {
                    break;
                }
                while (is_object($sturec) && is_object($stugrades) && ($sturec->muid < $stugrades->user->id)) {
                    $causers->next();
                    $sturec = $causers->valid() ? $causers->current() : NULL;
                }

                if (!is_object($sturec)) {
                    break;
                }
                while(is_object($stugrades) && is_object($gradedusers) && ($stugrades->user->id < $sturec->muid)) {
                    $stugrades = $gradedusers->next_user();
                }
                if (!is_object($stugrades)) {
                    break;
                }

                /// If the user doesn't exist in CM, skip it -- should we flag it?
                if (empty($sturec->cmid)) {
                    if ($moodleuserid == 0) {
                        //only show this if we are in the PM cron
                        mtrace("No user record for Moodle user id: {$sturec->muid}: {$sturec->username}<br />\n");
                    }
                    continue;
                }
                $cmuserid = $sturec->cmid;

                /// If no enrolment record in ELIS, then let's set one.
                if (empty($sturec->id)) {
                    if (!$doenrol) {
                        continue;
                    }
                    $sturec->classid = $class->classid;
                    $sturec->userid = $cmuserid;

                    /// Enrolment time will be the earliest found role assignment for this user.
                    $enroltime = $timenow;
                    $enrolments = $DB->get_recordset('enrol', array('courseid' => $class->moodlecourseid));
                    foreach ($enrolments as $enrolment) {
                        $etime = $DB->get_field('user_enrolments', 'timestart',
                                          array('enrolid' => $enrolment->id,
                                                'userid'  => $sturec->muid));
                        if (!empty($etime) && $etime < $enroltime) {
                            $enroltime = $etime;
                        }
                    }
                    unset($enrolments);
                    $sturec->enrolmenttime = $enroltime;
                    $sturec->completetime = 0;
                    $sturec->endtime = 0;
                    $sturec->completestatusid = STUSTATUS_NOTCOMPLETE;
                    $sturec->grade = 0;
                    $sturec->credits = 0;
                    $sturec->locked = 0;
                    $sturec->id = $DB->insert_record(STUTABLE, $sturec);
                }

                /// Handle the course grade
                if (isset($stugrades->grades[$coursegradeitem->id]->finalgrade)) {

                    /// Set the course grade if there is one and it's not locked.
                    $usergradeinfo = $stugrades->grades[$coursegradeitem->id];
                    if (!$sturec->locked && !is_null($usergradeinfo->finalgrade)) {
                        // clone of student record, to see if we actually change anything
                        $old_sturec = clone($sturec);

                        $grade = $usergradeinfo->finalgrade / $coursegradeitem->grademax * 100.0;
                        $sturec->grade = $grade;

                        /// Update completion status if all that is required is a course grade.
                        if (empty($compelems_valid)) {
                            if ($pmclass->course->completion_grade <= $sturec->grade) {
                                $sturec->completetime = $usergradeinfo->get_dategraded();
                                $sturec->completestatusid = STUSTATUS_PASSED;
                                $sturec->credits = floatval($pmclass->course->credits);
                            } else {
                                $sturec->completetime = 0;
                                $sturec->completestatusid = STUSTATUS_NOTCOMPLETE;
                                $sturec->credits = 0;
                            }
                        } else {
                            $sturec->completetime = 0;
                            $sturec->completestatusid = STUSTATUS_NOTCOMPLETE;
                            $sturec->credits = 0;
                        }

                        // only update if we actually changed anything
                        // (exception: if the completetime gets smaller,
                        // it's probably because $usergradeinfo->get_dategraded()
                        // returned an empty value, so ignore that change)
                        if ($old_sturec->grade != $sturec->grade
                            || $old_sturec->completetime < $sturec->completetime
                            || $old_sturec->completestatusid != $sturec->completestatusid
                            || $old_sturec->credits != $sturec->credits) {

                            if ($sturec->completestatusid == STUSTATUS_PASSED && empty($sturec->completetime)) {
                                // make sure we have a valid complete time, if we passed
                                $sturec->completetime = $timenow;
                            }

                            $DB->update_record(student::TABLE, $sturec);
                        }
                    }
                }

                /// Handle completion elements
                if (!empty($allcompelemgrades)) {
                    // get student's completion elements
                    $cmgrades = array();
                    // NOTE: we use a do-while loop, since $last_rec might
                    // be set from the last run, so we need to check it
                    // before we load from the database

                    //need to track whether we're on the first record because of how
                    //recordsets work
                    $first = true;

                    do {
                        if (isset($last_rec->muid)) {
                            if ($last_rec->muid > $sturec->muid) {
                                // we've reached the end of this student's
                                // grades ($last_rec will save this record
                                // for the next student's run)
                                break;
                            }
                            if ($last_rec->muid == $sturec->muid) {
                                $cmgrades[$last_rec->completionid] = $last_rec;
                            }
                        }

                        if (!$first) {
                            //not using a cached record, so advance the recordset
                            $allcompelemgrades->next();
                        }

                        //obtain the next record
                        $last_rec = $allcompelemgrades->current();
                        //signal that we are now within the current recordset
                        $first = false;
                    } while ($allcompelemgrades->valid());

                    foreach ($comp_elements as $gi_id => $element) {
                        if (!isset($stugrades->grades[$gi_id]->finalgrade)) {
                            continue;
                        }
                        // calculate Moodle grade as a percentage
                        $gradeitem = $stugrades->grades[$gi_id];
                        $maxgrade = $gis[$gi_id]->grademax;
                        /// Ignore mingrade for now... Don't really know what to do with it.
                        $gradepercent =  ($gradeitem->finalgrade >= $maxgrade) ? 100.0
                                      : (($gradeitem->finalgrade <= 0) ? 0.0
                                      :  ($gradeitem->finalgrade / $maxgrade * 100.0));

                        if (isset($cmgrades[$element->id])) {
                            // update existing completion element grade
                            $grade_element = $cmgrades[$element->id];
                            if (!$grade_element->locked
                                && ($gradeitem->get_dategraded() > $grade_element->timegraded)) {

                                // clone of record, to see if we actually change anything
                                $old_grade = clone($grade_element);

                                $grade_element->grade = $gradepercent;
                                $grade_element->timegraded = $gradeitem->get_dategraded();
                                /// If completed, lock it.
                                $grade_element->locked = ($grade_element->grade >= $element->completion_grade) ? 1 : 0;

                                // only update if we actually changed anything
                                if ($old_grade->grade != $grade_element->grade
                                    || $old_grade->timegraded != $grade_element->timegraded
                                    || $old_grade->grade != $grade_element->grade
                                    || $old_grade->locked != $grade_element->locked) {

                                    $grade_element->timemodified = $timenow;
                                    //todo: use class constant
                                    $DB->update_record('crlm_class_graded', $grade_element);
                                }
                            }
                        } else {
                            // no completion element grade exists: create a new one
                            $grade_element = new Object();
                            $grade_element->classid = $class->classid;
                            $grade_element->userid = $cmuserid;
                            $grade_element->completionid = $element->id;
                            $grade_element->grade = $gradepercent;
                            $grade_element->timegraded = $gradeitem->get_dategraded();
                            $grade_element->timemodified = $timenow;
                            /// If completed, lock it.
                            $grade_element->locked = ($grade_element->grade >= $element->completion_grade) ? 1 : 0;
                            //todo: use class constant
                            $DB->insert_record('crlm_class_graded', $grade_element);
                        }
                    }
                }
            }
            set_time_limit(600);
        }
    }
}

/**
 * Notifies that students have not passed their classes via the notifications where applicable,
 * setting enrolment status to failed where applicable
 *
 * @param int $pmuserid  optional userid to update, default(0) updates all users
 */
function pm_update_student_enrolment($pmuserid = 0) {
    global $DB;

    require_once(elispm::lib('data/student.class.php'));
    require_once(elispm::lib('notifications.php'));

    //look for all enrolments where status is incomplete / in progress and end time has passed
    $select = 'completestatusid = :status AND endtime > 0 AND endtime < :time';
    $params = array('status' => STUSTATUS_NOTCOMPLETE,
                    'time'   => time());
    if ($pmuserid) {
        $select .= ' AND userid = :userid';
        $params['userid'] = $pmuserid;
    }
    $students = $DB->get_recordset_select(student::TABLE, $select, $params);
    if (!empty($students)) {
        foreach ($students as $s) {
            //send message
            $a = $DB->get_field(pmclass::TABLE, 'idnumber', array('id' => $s->classid));
            $message = get_string('incomplete_course_message', 'elis_program', $a);
            $user = cm_get_moodleuser($s->userid);
            $from = get_admin();
            notification::notify($message, $user, $from);

            //set status to failed
            $s->completetime = 0;
            $s->completestatusid = STUSTATUS_FAILED;
            $DB->update_record(student::TABLE, $s);
        }
    }

    return true;
}

/**
 * Migrate any existing Moodle users to the Curriculum Management
 * system.
 */
function pm_migrate_moodle_users($setidnumber = false, $fromtime = 0, $mdluserid = 0) {
    global $CFG, $DB;

    require_once ($CFG->dirroot.'/elis/program/lib/setup.php');
    require_once(elispm::lib('data/user.class.php'));

    $timenow = time();
    $result  = true;

    // set time modified if not set, so we can keep track of "new" users
    $sql = 'UPDATE {user}
               SET timemodified = :timenow
             WHERE timemodified = 0';
    $params = array('timenow' => $timenow);
    if ($mdluserid) {
        $sql .= ' AND id = :userid';
        $params['userid'] = $mdluserid;
    }
    $result = $result && $DB->execute($sql, $params);

    if ($setidnumber || elis::$config->elis_program->auto_assign_user_idnumber) {
        // make sure we only set idnumbers if users' usernames don't point to
        // existing idnumbers
        $sql = "UPDATE {user}
                   SET idnumber = username
                 WHERE idnumber = ''
                   AND username != 'guest'
                   AND deleted = 0
                   AND confirmed = 1
                   AND mnethostid = :hostid
                   AND username NOT IN (SELECT idnumber
                                        FROM (SELECT idnumber
                                              FROM {user} inneru) innertable)";
        $params = array('hostid' => $CFG->mnet_localhost_id);
        if ($mdluserid) {
            $sql .= ' AND id = :userid';
            $params['userid'] = $mdluserid;
        }
        $result = $result && $DB->execute($sql, $params);
    }

    $select = "username != 'guest'
               AND deleted = 0
               AND confirmed = 1
               AND mnethostid = :hostid
               AND idnumber != ''
               AND timemodified >= :time
               AND NOT EXISTS (SELECT 'x'
                               FROM {".user::TABLE."} cu
                               WHERE cu.idnumber = {user}.idnumber)";
    $params = array('hostid' => $CFG->mnet_localhost_id,
                    'time'   => $fromtime);
    if ($mdluserid) {
        $select .= ' AND id = :userid';
        $params['userid'] = $mdluserid;
    }
    $rs = $DB->get_recordset_select('user', $select, $params);
    if ($rs && $rs->valid()) {
        require_once elis::plugin_file('usersetenrol_moodle_profile', 'lib.php');
        foreach ($rs as $user) {
            // FIXME: shouldn't depend on cluster functionality -- should
            // be more modular
            cluster_profile_update_handler($user);
        }
        $rs->close();
    }

    return $result;
}

/**
 * Migrate a single Moodle user to the Program Management system.  Will
 * only do this for users who have an idnumber set.
 */
function pm_moodle_user_to_pm($mu) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/lib/moodlelib.php');
    require_once(elis::lib('data/customfield.class.php'));
    require_once(elispm::lib('data/user.class.php'));
    require_once(elispm::lib('data/usermoodle.class.php'));
    require_once(elis::lib('data/data_filter.class.php'));
    require_once($CFG->dirroot . '/user/profile/lib.php');

    if (!isset($mu->id)) {
        return true;
    }

    // re-fetch, in case this is from a stale event
    $mu = $DB->get_record('user', array('id' => $mu->id));

    if (user_not_fully_set_up($mu)) {
        //prevent the sync if a bare-bones user record is being created
        //by create_user_record
        return true;
    }

    //not going to be concerned with city or password for now

    if (empty($mu->country)) {
        //this is necessary because PM requires this field
        return true;
    }

    if (empty($mu->idnumber) && elis::$config->elis_program->auto_assign_user_idnumber) {
        //make sure the current user's username does not match up with some other user's
        //idnumber (necessary since usernames and idnumbers aren't bound to one another)
        if (!$DB->record_exists('user', array('idnumber' => $mu->username))) {
            $mu->idnumber = $mu->username;
            $DB->update_record('user', $mu);
        }
    }

    // skip user if no ID number set
    if (empty($mu->idnumber)) {
        return true;
    }

    // track whether we're syncing an idnumber change over to the PM system
    $idnumber_updated = false;
    // track whether an associated Moodle user is linked to the current PM user
    $moodle_user_exists = false;

    // determine if the user is already noted as having been associated to a PM user
    // this will join to Moodle user and PM user table to ensure data correctness
    $filters = array();
    $filters[] = new join_filter('muserid', 'user', 'id');
    $filters[] = new join_filter('cuserid', user::TABLE, 'id');
    $filters[] = new field_filter('muserid', $mu->id);

    if ($um = usermoodle::find($filters)) {
        if ($um->valid()) {
            $um = $um->current();

            //signal that an associated user already exists
	        $moodle_user_exists = true;

	        // determine if the Moodle user idnumber was updated
	        if ($um->idnumber != $mu->idnumber) {
                //signal that the idnumber was synced over
	            $idnumber_updated = true;

	            // update the PM user with the new idnumber
	            $cmuser = new user();
	            $cmuser->id = $um->cuserid;
	            $cmuser->idnumber = $mu->idnumber;
	            $cmuser->save();

	            // update the association table with the new idnumber
	            $um->idnumber = $mu->idnumber;
	            $um->save();
	        }
        }
    }

    // find the linked PM user

    //filter for the basic condition on the Moodle user id
    $condition_filter = new field_filter('id', $mu->id);
    //filter for joining the association table
    $association_filter = new join_filter('muserid', 'user', 'id', $condition_filter);
    //outermost filter
    $filter = new join_filter('id', usermoodle::TABLE, 'cuserid', $association_filter);

    $cu = user::find($filter);
    if ($cu->valid()) {
        $cu = $cu->current();
    } else {
        // if a user with the same username but different idnumber exists,
        // we can't sync over because it will violate PM user uniqueness
        // constraints
        $cu = user::find(new field_filter('username', $mu->username));
        if ($cu->valid()) {
            return true;
        }

        // if no such PM user exists, create a new one
        $cu = new user();
        $cu->transfercredits = 0;
        $cu->timecreated = time();
    }

    // synchronize standard fields
    $cu->username = $mu->username;
    $cu->password = $mu->password;

    // only need to update the idnumber if it wasn't handled above
    if (!$idnumber_updated) {
        $cu->idnumber = $mu->idnumber;
    }

    $cu->firstname = $mu->firstname;
    $cu->lastname = $mu->lastname;
    $cu->email = $mu->email;
    $cu->address = $mu->address;
    $cu->city = $mu->city;
    $cu->country = $mu->country;
    if (!empty($mu->phone1)) {
        $cu->phone = $mu->phone1;
    }
    if (!empty($mu->phone2)) {
        $cu->phone2 = $mu->phone2;
    }
    if (!empty($mu->lang)) {
        $cu->language = $mu->lang;
    }
    $cu->timemodified = time();

    // synchronize custom profile fields
    profile_load_data($mu);
    $fields = field::get_for_context_level(CONTEXT_ELIS_USER);
    $fields = $fields ? $fields : array();
    require_once(elis::plugin_file('elisfields_moodle_profile', 'custom_fields.php'));
    foreach ($fields as $field) {
        $field = new field($field);
        if (!moodle_profile_can_sync($field->shortname)) {
            continue;
        }
        if (isset($field->owners['moodle_profile']) && isset($mu->{"profile_field_{$field->shortname}"})) {
            // check if should sync user profile field settings
            if ($field->owners['moodle_profile']->exclude == pm_moodle_profile::sync_from_moodle) {
                sync_profile_field_settings_from_moodle($field);
            }
            $fieldname = "field_{$field->shortname}";
            $cu->$fieldname = $mu->{"profile_field_{$field->shortname}"};
        }
    }

    //specifically tell the user save not to use the crlm_user_moodle for syncing
    //because the record hasn't been inserted yet (see below)
    try {
        $cu->save(false);
    } catch (Exception $ex) {
        if (in_cron()) {
            mtrace(get_string('record_not_created_reason', 'elis_program',
                        array('message' => $ex->getMessage() ." [{$mu->id}]")));
            return false;
        } else {
            throw new Exception($ex->getMessage());
        }
    }

    // if no user association record exists, create one
    if (!$moodle_user_exists) {
        $um = new usermoodle();
        $um->cuserid  = $cu->id;
        $um->muserid  = $mu->id;
        $um->idnumber = $mu->idnumber;
        $um->save();
    }

    return true;
}

/**
 * Get all of the data from Moodle and update the curriculum system.
 * This should do the following:
 *      - Get all Moodle courses connected with classes.
 *      - Get all users in each Moodle course.
 *      - Get grade records from the class's course and completion elements.
 *      - For each user:
 *          - Check if they have an enrolment record in CM, and add if not.
 *          - Update grade information in the enrollment and grade tables in CM.
 *
 * @param int $muserid  optional user to update, default(0) updates all users
 */
function pm_update_student_progress($muserid = 0) {
    global $CFG;

    require_once ($CFG->dirroot.'/grade/lib.php');
    require_once ($CFG->dirroot.'/grade/querylib.php');

    /// Get all grades in all relevant courses for all relevant users.
    require_once (elispm::lib('data/classmoodlecourse.class.php'));
    require_once (elispm::lib('data/student.class.php'));
    require_once (elispm::lib('data/pmclass.class.php'));
    require_once (elispm::lib('data/course.class.php'));

/// Start with the Moodle classes...
    if ($muserid == 0) {
        mtrace("Synchronizing Moodle class grades<br />\n");
    }
    pm_synchronize_moodle_class_grades($muserid);

    flush(); sleep(1);

/// Now we need to check all of the student and grade records again, since data may have come from sources
/// other than Moodle.
    if ($muserid == 0) {
        //running for all users
        mtrace("Updating all class grade completions.<br />\n");
        pm_update_enrolment_status();
    } else {
        //attempting to run for a particular user
        $pmuserid = pm_get_crlmuserid($muserid);

        if ($pmuserid != false) {
            //user has a matching PM user
            pm_update_enrolment_status($pmuserid);
        }
    }

    return true;
}

/**
 * Update enrolment status of users enroled in all classes, completing and locking
 * records where applicable based on class grade and required completion elements
 *
 * @param int $pmuserid  optional userid to update, default(0) updates all users
 */
function pm_update_enrolment_status($pmuserid = 0) {
    global $DB;

    require_once(elispm::lib('data/pmclass.class.php'));
    require_once(elispm::lib('data/student.class.php'));

/// Need to separate this out so that the enrolments by class are checked for completion.
/// ... for each class and then for each enrolment...
/// Goal is to minimize database reads, so we can't just instantiate a student object, as
/// each one will go and get the same things for one class. So, we probably need a class-level
/// function that then manages the student objects. Once this is in place, add completion notice
/// to the code.

    /// Get all classes with unlocked enrolments.
    $sql = 'SELECT cce.classid as classid, COUNT(cce.userid) as numusers
            FROM {'.student::TABLE.'} cce
            INNER JOIN {'.pmclass::TABLE.'} cls ON cls.id = cce.classid
            WHERE cce.locked = 0';
    $params = array();
    if ($pmuserid) {
        $sql .= ' AND cce.userid = ?';
        $params = array($pmuserid);
    }
    $sql .= '
            GROUP BY cce.classid
            ORDER BY cce.classid ASC';
    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs as $rec) {
        $pmclass = new pmclass($rec->classid);
        $pmclass->update_enrolment_status($pmuserid);
        //todo: investigate as to whether ten minutes is too long for one class
        set_time_limit(600);
    }
}

/**
 * Get Curriculum user id for a given Moodle user id.
 *
 */
function pm_get_crlmuserid($userid) {
    global $DB;
    require_once(elispm::lib('data/user.class.php'));

    $select = 'SELECT cu.id ';
    $from   = 'FROM {user} mu ';
    $join   = 'INNER JOIN {'.user::TABLE.'} cu ON cu.idnumber = mu.idnumber ';
    $where  = 'WHERE mu.id = :userid';
    $params  = array('userid'=>$userid);
    return $DB->get_field_sql($select.$from.$join.$where, $params);
}

/**
 * Function to determine if running in cron
 *
 */
function in_cron() {
    global $SCRIPT;
    return(strpos(strrev($SCRIPT), 'php.norc') === 0);
}

/**
 * Call all cron jobs needed for the ELIS system.
 *
 */
function pm_cron() {
    $status = true;

    $status = pm_migrate_moodle_users(false, time() - (7*24*60*60)) && $status;
    $status = pm_update_student_progress() && $status;
    $status = pm_check_for_nags() && $status;
    $status = pm_update_student_enrolment() && $status;

    return $status;
}

/**
 * Update all PM information for the provided user
 *
 * @param int $mdluserid the id of the Moodle user we want to migrate
 * @return boolean true on success, otherwise false
 */
function pm_update_user_information($mdluserid) {
    $status = true;

    //create the PM user if necessary, regardless of time modified
    $status = pm_migrate_moodle_users(false, 0, $mdluserid) && $status;
    //sync enrolments and pass ones with sufficient grades and passed LOs
    $status = pm_update_student_progress($mdluserid) && $status;

    $pmuserid = pm_get_crlmuserid($mdluserid);

    if ($pmuserid != false) {
        //delete orphaned class - Moodle course associations the user is enrolled in
        $status = pmclass::check_for_moodle_courses($pmuserid) && $status;
        //fail users who took to long to complete classes
        $status = pm_update_student_enrolment($pmuserid) && $status;
    }

    return $status;
}

/**
 * Check for nags...
 *
 */
function pm_check_for_nags() {
    $status = true;

    mtrace("Checking notifications<br />\n");
    $status = pmclass::check_for_nags() && $status;
    $status = pmclass::check_for_moodle_courses() && $status;
    $status = course::check_for_nags() && $status;
    $status = curriculum::check_for_nags() && $status;

    return $status;
}

/*
 * Check for autoenrol after course completion
 */
function pm_course_complete($enrolment) {
    track::check_autoenrol_after_course_completion($enrolment);
    waitlist::check_autoenrol_after_course_completion($enrolment);

    return true;
}

/**
 * Function from old [1.9x] usermanagement.class.php
 * used by: bulkuserpage.class.php
 */
function usermanagement_get_users($sort = 'name', $dir = 'ASC', $startrec = 0,
                                  $perpage = 0, $extrasql = array(), $contexts = null) {
    global $DB;
    require_once(elispm::lib('data/user.class.php'));

    $FULLNAME = $DB->sql_concat('firstname', "' '", 'lastname');
    $select   = 'SELECT id, idnumber, country, language, timecreated, '.
               $FULLNAME . ' as name ';
    //do not use a user table alias because user-based filters operate on the user table directly
    $tables   = 'FROM {'. user::TABLE .'} ';
    $where    = array();
    $params   = array();

    if (!empty($extrasql) && $extrasql[0]) {
        $where[] = $extrasql[0];
        if ($extrasql[1]) {
            $params = $extrasql[1];
        }
    }

    if ($contexts !== null) { // TBV
        $user_obj = $contexts->get_filter('id', 'user');
        $filter_array = $user_obj->get_sql(false, NULL, SQL_PARAMS_NAMED);
        if (isset($filter_array['where'])) {
            $where[] = '('. $filter_array['where'] .')';
            $params = array_merge($params, $filter_array['where_parameters']);
        }
    }

    if (!empty($where)) {
        $s_where = 'WHERE '. implode(' AND ', $where) .' ';
    } else {
        $s_where = '';
    }

    if ($sort) { // ***TBD***
        if ($sort == 'name') {
            $sort = "ORDER BY lastname {$dir}, firstname {$dir} ";
        } else {
            $sort = "ORDER BY {$sort} {$dir} ";
        }
    }

    $sql = $select.$tables.$s_where.$sort;
    return $DB->get_records_sql($sql, $params, $startrec, $perpage);
}

/**
 * Count the number of users
 * Function from old [1.9x] usermanagement.class.php
 * used by: bulkuserpage.class.php
 */
function usermanagement_count_users($extrasql = array(), $contexts = null) {
    global $DB;
    require_once(elispm::lib('data/user.class.php'));

    $select  = 'SELECT COUNT(id) ';
    //do not use a user table alias because user-based filters operate on the user table directly
    $tables  = 'FROM {'. user::TABLE .'} ';
    $join    = '';
    $on      = '';
    $where   = array();
    $params  = array();

    if (!empty($extrasql) && $extrasql[0]) {
        $where[] = $extrasql[0];
        if ($extrasql[1]) {
            $params = $extrasql[1];
        }
    }

    if ($contexts !== null) { // TBV
        $user_obj = $contexts->get_filter('id', 'user');
        $filter_array = $user_obj->get_sql(false, NULL, SQL_PARAMS_NAMED);
        if (isset($filter_array['where'])) {
            $where[] = '('. $filter_array['where'] .')';
            $params = array_merge($params, $filter_array['where_parameters']);
        }
    }

    if (!empty($where)) {
        $s_where = 'WHERE '. implode(' AND ', $where) .' ';
    } else {
        $s_where = '';
    }

    $sql = $select.$tables.$join.$on.$s_where;
    return $DB->count_records_sql($sql, $params);
}

/**
 * Get users recordset
 * Function from old [1.9x] usermanagement.class.php
 * used by: individual_user_report.class.php
 * @uses    $CFG
 * @uses    $DB
 */
function usermanagement_get_users_recordset($sort = 'name', $dir = 'ASC',
                                            $startrec = 0, $perpage = 0,
                                            $extrasql = '', $contexts = null) {
    global $CFG, $DB;
    require_once($CFG->dirroot .'/elis/program/lib/data/user.class.php');

    $FULLNAME = $DB->sql_concat('firstname', "' '", 'lastname');
    $select = 'SELECT id, idnumber as idnumber, country, language, timecreated, '.
               $FULLNAME .' as name ';
    $tables = 'FROM {'. user::TABLE .'} ';
    $where = array();
    $params = array();

    if (!empty($extrasql) && $extrasql[0]) {
        $where[] = $extrasql[0];
        if ($extrasql[1]) {
            $params = $extrasql[1];
        }
    }

    if ($contexts !== null) { // TBV
        $user_obj = $contexts->get_filter('id', 'user');
        $filter_array = $user_obj->get_sql(false, NULL, SQL_PARAMS_NAMED);
        if (isset($filter_array['where'])) {
            $where[] = '('. $filter_array['where'] .')';
            $params = array_merge($params, $filter_array['where_parameters']);
        }
    }

    if (!empty($where)) {
        $where = 'WHERE '. implode(' AND ', $where).' ';
    } else {
        $where = '';
    }

    if ($sort) {
        if ($sort == 'name') {
            $sort = "ORDER BY lastname {$dir}, firstname {$dir} ";
        } else {
            $sort = "ORDER BY {$sort} {$dir} ";
        }
    }

    $sql = $select.$tables.$where.$sort;
    return $DB->get_recordset_sql($sql, $params, $startrec, $perpage);
}

/**
 * Output a message during plugin upgrade or install
 */
function install_msg($msg) {
    $msg .= "\n";
    if (!CLI_SCRIPT) {
        $msg = nl2br($msg);
    }
    echo $msg;
}

/**
 * Migrate tags and tag instances to custom fields and custom field data
 * (run as a one-off during the elis program upgrade)
 *
 * If there are one or more entities (curricula, courses, classes) with tags
 * assigned to them, a new category and custom field is created, specific to the
 * appropriate context level. Then, that custom field is populated for each entity
 * that has one or more tags assigned (custom field is a multi-select, where the options
 * are all the different tags on the site).
 *
 * If one or more tag instances have custom data defined, a custom field is set up for
 * each such tag instance, and data is associated to the particular entities using this sparate
 * custom field.
 */
function pm_migrate_tags() {
    global $DB;

    require_once(elis::lib('data/customfield.class.php'));

    //set up our contextlevel mapping
    $contextlevels = array('cur' => 'curriculum',
                           'crs' => 'course',
                           'cls' => 'class');

    //set up ELIS table mapping
    $tables = array('cur' => 'curriculum',
                           'crs' => 'course',
                           'cls' => 'pmclass');

    //lookup on all tags
    $tag_lookup = $DB->get_records('crlm_tag', null, '', 'id, name');
    foreach ($tag_lookup as $id => $tag) {
        $tag_lookup[$id] = $tag->name;
    }

    //go through each contextlevel and look for tags
    foreach ($contextlevels as $instancetype => $contextname) {

        //calculate the context level integer
        $contextlevel = context_elis_helper::get_level_from_name($contextname);
            $contextclass = context_elis_helper::get_class_for_level($contextlevel);

        //make sure one or more tags are used at the current context level
        if ($DB->record_exists('crlm_tag_instance', array('instancetype' => $instancetype))) {

            //used to reference the category name
            $category = new field_category(array('name' => get_string('misc_category', 'elis_program')));

            //make sure our field for storing tags is created
            $field = new field(array('shortname'   => "_19upgrade_{$contextname}_tags",
                                     'name'        => get_string('tags', 'elis_program'),
                                     'datatype'    => 'char',
                                     'multivalued' => 1));
            $field = field::ensure_field_exists_for_context_level($field, $contextlevel, $category);

            //determine tag options
            $options = array();
            if ($records = $DB->get_recordset('crlm_tag', null, 'name', 'DISTINCT name')) {
                foreach ($records as $record) {
                    $options[] = $record->name;
                }
            }
            $options = implode("\n", $options);

            //set up our field owner
            field_owner::ensure_field_owner_exists($field, 'manual', array('control'         => 'menu',
                                                                           'options'         => $options,
                                                                           'edit_capability' => '',
                                                                           'view_capability' => ''));

            //clean up any tags with invalid instancids
            $sql = "DELETE FROM {crlm_tag_instance}
                    WHERE NOT EXISTS (
                        SELECT 'x' FROM {".$tables[$instancetype]::TABLE."} ct
                        WHERE ct.id = {crlm_tag_instance}.instanceid)
                    AND {crlm_tag_instance}.instancetype = '$instancetype'";
            $success = $DB->execute($sql);

            //set up data for all relevant entries
            $sql = "SELECT instanceid, GROUP_CONCAT(tagid) AS tagids, data
                    FROM {crlm_tag_instance}
                    WHERE instancetype = ?
                    GROUP BY instanceid";
            if ($success && $records = $DB->get_recordset_sql($sql, array($instancetype))) {
                foreach ($records as $record) {
                    $tagids = explode(',', $record->tagids);
                    foreach ($tagids as $k => $v) {
                        $tagids[$k] = $tag_lookup[$v];
                    }

                    $context      = $contextclass::instance($record->instanceid);

                    field_data::set_for_context_and_field($context, $field, $tagids);
                }
            }

            //find all tags that have associated custom data and create a separate
            //custom field for each one
            $sql = "SELECT DISTINCT tagid
                    FROM {crlm_tag_instance}
                    WHERE instancetype = ?
                    AND data != ''";
            if ($records = $DB->get_recordset_sql($sql, array($instancetype))) {
                foreach ($records as $record) {
                    $tagname = $tag_lookup[$record->tagid];
                    $field = new field(array('shortname' => "_19upgrade_{$contextname}_tag_data_{$tagname}",
                                             'name'      => get_string('tag_custom_data', 'elis_program', $tagname),
                                             'datatype'  => 'char'));
                    $field = field::ensure_field_exists_for_context_level($field, $contextlevel, $category);

                    field_owner::ensure_field_owner_exists($field, 'manual', array('control'         => 'text',
                                                                                   'edit_capability' => '',
                                                                                   'view_capability' => ''));
                }
            }

            //set the data on all entities of the corresponding type for each tag
            //custom data entity that is set
            $sql = "SELECT instanceid, tagid, data
                    FROM {crlm_tag_instance}
                    WHERE instancetype = ?
                    AND data != ''";
            if ($records = $DB->get_recordset_sql($sql, array($instancetype))) {
                foreach ($records as $record) {
                    $tagname = $tag_lookup[$record->tagid];
                    if ($field = $DB->get_record(field::TABLE, array('shortname' => "_19upgrade_{$contextname}_tag_data_{$tagname}"))) {
                        $field = new field($field->id);

                        $contextlevel = context_elis_helper::get_level_from_name($contextname);
                        $contextclass = context_elis_helper::get_class_for_level($contextlevel);
                        $context     = $contextclass::instance($record->instanceid);

                        field_data::set_for_context_and_field($context, $field, $record->data);
                    }
                }
            }
        }
    }
}

/**
 * Update environments and environment assignments to custom fields and
 * custom field data (run as a one-off during the elis program upgrade)
 *
 * If there are one or more entities (courses, classes) with environments
 * assigned to them, a new category and custom field is created, specific to the
 * appropriate context level. Then, that custom field is populated for each entity
 * that has and environment assigned (custom field is a single-select, where the options
 * are all the different environments on the site).
 */
function pm_migrate_environments() {
    global $DB;

    require_once(elis::lib('data/customfield.class.php'));
    require_once(elispm::lib('data/course.class.php'));
    require_once(elispm::lib('data/pmclass.class.php'));

    //set up our contextlevel mapping
    $contextlevels = array(course::TABLE  => 'course',
                           pmclass::TABLE => 'class');

    //lookup on all tags
    $environment_lookup = $DB->get_records('crlm_environment', null, '', 'id, name');
    foreach ($environment_lookup as $id => $environment) {
        $environment_lookup[$id] = $environment->name;
    }

    //go through each contextlevel and look for tags
    foreach ($contextlevels as $instancetable => $contextname) {

        //calculate the context level integer
        $contextlevel = context_elis_helper::get_level_from_name($contextname);

        //make sure one or more environments are used at the current context level
        $select = 'environmentid != 0';

        if ($DB->record_exists_select($instancetable, $select)) {

            //used to reference the category name
            $category = new field_category(array('name' => get_string('misc_category', 'elis_program')));

            //make sure our field for storing environments is created
            $field = new field(array('shortname'   => "_19upgrade_{$contextname}_environment",
                                     'name'        => get_string('environment', 'elis_program'),
                                     'datatype'    => 'char'));
            $field = field::ensure_field_exists_for_context_level($field, $contextlevel, $category);

            //determine environment options
            $options = array();
            if ($records = $DB->get_recordset('crlm_environment', null, 'name', 'DISTINCT name')) {
                foreach ($records as $record) {
                    $options[] = $record->name;
                }
            }
            $options = implode("\n", $options);

            //set up our field owner
            field_owner::ensure_field_owner_exists($field, 'manual', array('control'         => 'menu',
                                                                           'options'         => $options,
                                                                           'edit_capability' => '',
                                                                           'view_capability' => ''));

            //set up data for all relevant entries
            $sql = "SELECT id, environmentid
                    FROM {{$instancetable}}
                    WHERE environmentid != 0";
            if ($records = $DB->get_recordset_sql($sql)) {
                foreach ($records as $record) {
                    $contextlevel = context_elis_helper::get_level_from_name($contextname);
                    $contextclass = context_elis_helper::get_class_for_level($contextlevel);
                    $context     = $contextclass::instance($record->id);

                    $environmentid = $environment_lookup[$record->environmentid];
                    field_data::set_for_context_and_field($context, $field, $environmentid);
                }
            }
        }
    }
}

/**
 * Ensures that a role is assignable to all the PM context levels
 *
 * @param $role mixed - either the role shortname OR a role id
 * @return the roleid on success, false otherwise.
 * @uses  $DB
 */
function pm_ensure_role_assignable($role) {
    global $DB;
    if (!is_numeric($role)) {
        if (!($roleid = $DB->get_field('role', 'id', array('shortname' => $role)))
            && !($roleid = create_role(get_string($role .'name', 'elis_program'), $role,
                                       get_string($role .'description', 'elis_program'),
                                       get_string($role .'archetype', 'elis_program')))) {

            mtrace("\n pm_ensure_role_assignable(): Error creating role '{$role}'\n");
        }
    } else {
        $roleid = $role;
    }
    if ($roleid) {
        $rcl = new stdClass;
        $rcl->roleid = $roleid;

        foreach (context_elis_helper::get_all_levels() as $ctxlevel => $ctxclass) {
            $rcl->contextlevel = $ctxlevel;
            if (!$DB->record_exists('role_context_levels', array('roleid' => $roleid, 'contextlevel' => $ctxlevel))) {
                $DB->insert_record('role_context_levels', $rcl);
            }
        }
    }
    return $roleid;
}

/**
 * Fixes duplicate data relating to class enrolments (specifically duplicate class_graded records)
 * @return boolean true on success, otherwise false
 */
function pm_fix_duplicate_class_enrolments() {
    global $CFG, $DB;

    require_once($CFG->dirroot.'/lib/ddllib.php');

    $dbman = $DB->get_manager();

    // Delete duplicate class completion element grades
    $xmldbtable = new xmldb_table('crlm_class_graded_temp');

    if ($dbman->table_exists($xmldbtable)) {
        $dbman->drop_table($xmldbtable);
    }

    $result = true;

    // Create a temporary table
    $result = $result && $DB->execute("CREATE TABLE {$CFG->prefix}crlm_class_graded_temp LIKE {$CFG->prefix}crlm_class_graded");

    // Store the unique values in the temporary table
    $sql = "INSERT INTO {$CFG->prefix}crlm_class_graded_temp
            SELECT MAX(id) AS id, classid, userid, completionid, grade, locked, timegraded, timemodified
            FROM {$CFG->prefix}crlm_class_graded
            GROUP BY classid, userid, completionid, locked";

    $result = $result && $DB->execute($sql);

    // Detect if there are still duplicates in the temporary table
    $sql = "SELECT COUNT(*) AS count, classid, userid, completionid, grade, locked, timegraded, timemodified
            FROM {$CFG->prefix}crlm_class_graded_temp
            GROUP BY classid, userid, completionid
            ORDER BY count DESC, classid ASC, userid ASC, completionid ASC";

    if ($dupcount = $DB->get_record_sql($sql, null, IGNORE_MULTIPLE)) {
        if ($dupcount->count > 1) {
            if ($rs = $DB->get_recordset_sql($sql)) {
                foreach ($rs as $dupe) {
                    if ($dupe->count <= 1) {
                        continue;
                    }

                    $classid = $dupe->classid;
                    $userid  = $dupe->userid;
                    $goodid  = 0; // The ID of the record we will keep

                    // Look for the earliest locked grade record for this user and keep that (if any are locked)
                    $sql2 = "SELECT id, grade, locked, timegraded
                             FROM {crlm_class_graded}
                             WHERE classid = $classid
                             AND userid = $userid
                             ORDER BY timegraded ASC";

                    if (($rs2 = $DB->get_recordset_sql($sql2)) && $rs2->valid()) {
                        foreach ($rs2 as $rec) {
                            // Store the last record ID just in case we need it for cleanup
                            $lastid = $rec->id;

                            // Don't bother looking at remaining records if we have found a record to keep
                            if (!empty($goodid)) {
                                continue;
                            }

                            if ($rec->locked = 1) {
                                $goodid = $rec->id;
                            }
                        }

                        $rs2->close();

                        // We need to make sure we have a record ID to keep, if we found no "complete" and locked
                        // records, let's just keep the last record we saw
                        if (empty($goodid)) {
                            $goodid = $lastid;
                        }

                        $select = 'classid = ? AND userid = ? AND id != ?';
                        $params = array($classid, $userid, $goodid);
                    }

                    if (!empty($select)) {
                        $result = $result && $DB->delete_records_select('crlm_class_graded_temp', $select, $params);
                    }
                }
            }
        }
    }

    // Drop the real table
    $result = $result && $DB->execute("DROP TABLE {$CFG->prefix}crlm_class_graded");

    // Replace the real table with the temporary table that now only contains unique values.
    $result = $result && $DB->execute("ALTER TABLE {$CFG->prefix}crlm_class_graded_temp RENAME TO {$CFG->prefix}crlm_class_graded");

    return $result;
}

/**
 * Fixes idnumbers for Moodle users to remove duplicates
 * @return boolean true on success, otherwise false
 */
function pm_fix_duplicate_moodle_users() {
    global $CFG, $DB;

    require_once($CFG->dirroot.'/lib/ddllib.php');
    require_once($CFG->dirroot.'/elis/program/lib/setup.php');
    require_once(elispm::lib('notifications.php'));
    require_once(elispm::lib('data/user.class.php'));

    $dbman = $DB->get_manager();

    // Delete duplicate class completion element grades
    $xmldbtable = new xmldb_table('user_idnumber_temp');

    if ($dbman->table_exists($xmldbtable)) {
        $dbman->drop_table($xmldbtable);
    }

    $result = true;

    // Create temporary table for storing qualifying idnumbers
    $table = new xmldb_table('user_idnumber_temp');
    $table->add_field('idnumber', XMLDB_TYPE_CHAR, '255', NULL, XMLDB_NOTNULL);
    $dbman->create_table($table);

    $sql = "INSERT INTO {user_idnumber_temp}
            SELECT idnumber
            FROM {user}
            GROUP BY idnumber
              HAVING idnumber != ''
              AND COUNT(*) > 1";

    $result = $result && $DB->execute($sql);

    $admin = get_admin();

    // Look up the list of duplicate idnumbers
    if ($rs = $DB->get_recordset('user_idnumber_temp')) {
        foreach ($rs as $record) {

            // Store whether we're currently on the first user record, whose idnumber
            // will not change
            $first = true;

            // Store the userids and usernames of the appropriate users
            $userids = array();
            $usernames = array();

            // Store whether there was some failure generating an idnumber
            $idnumber_generation_failure = false;

            // By default, obtain the least recently modified record
            $sort_condition = 'timemodified';

            $sql = "SELECT mu.id
                    FROM {user} mu
                    JOIN {".user::TABLE."} pu
                      ON mu.idnumber = pu.idnumber
                      AND mu.username = pu.username
                      AND mu.idnumber = ?";
            $params = array($record->idnumber);

            if ($correct_record = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE)) {
                // Found corresponding user with username and idnumber matching, so
                // prioritize it
                $sort_condition = 'id = '.$correct_record->id.' DESC';
            }

            if ($rs2 = $DB->get_recordset('user', array('idnumber' => $record->idnumber), $sort_condition)) {
                foreach ($rs2 as $record2) {

                    // Store information about the current user
                    $userids[] = $record2->id;
                    $usernames[] = $record2->username;

                    // Store whether some idnumber generation attempt was successful
                    $generated = false;

                    if (!$first) {
                        // Use this flag to determine if a unique random string was generated

                        // Attempt to generate a unique random idnumber
                        for ($i = 0; $i < 10; $i++) {
                            $record2->idnumber = random_string(15);
                            if (!$DB->record_exists('user', array('idnumber' => $record2->idnumber)) &&
                                !$DB->record_exists(user::TABLE, array('idnumber' => $record2->idnumber))) {
                                $DB->update_record('user', $record2);
                                $generated = true;
                                break;
                            }
                        }

                    }

                    if (!$first && !$generated) {
                        //this is where we would ideally send a failure message

                        $idnumber_generation_failure = true;
                    }

                    $first = false;
                }
            }

            //this is where we would ideally send a success message but it's current
            //not possible because this is called during the upgrade before the messages
            //setup happens
        }
    }

    // Drop the temp table
    $result = $result && $DB->execute("DROP TABLE {user_idnumber_temp}");

    return $result;
}

/**
 * Fixes idnumbers for Program Management users to remove duplicates
 * @return boolean true on success, otherwise false
 */
function pm_fix_duplicate_pm_users() {
    global $CFG, $DB;

    require_once($CFG->dirroot.'/lib/ddllib.php');
    require_once($CFG->dirroot.'/elis/program/lib/setup.php');
    require_once(elispm::lib('notifications.php'));
    require_once(elispm::lib('data/user.class.php'));
    require_once(elispm::file('userpage.class.php'));

    $dbman = $DB->get_manager();

    // Delete duplicate class completion element grades
    $xmldbtable = new xmldb_table('crlm_user_idnumber_temp');

    if ($dbman->table_exists($xmldbtable)) {
        $dbman->drop_table($xmldbtable);
    }

    $result = true;

    // Create temporary table for storing qualifying idnumbers
    $table = new xmldb_table('crlm_user_idnumber_temp');
    $table->add_field('idnumber', XMLDB_TYPE_CHAR, '255', NULL, XMLDB_NOTNULL);
    $dbman->create_table($table);

    $sql = "INSERT INTO {crlm_user_idnumber_temp}
            SELECT idnumber
            FROM {".user::TABLE."}
            GROUP BY idnumber
              HAVING idnumber != ''
              AND COUNT(*) > 1";

    $result = $result && $DB->execute($sql);

    $admin = get_admin();

    // Look up the list of duplicate idnumbers
    if ($rs = $DB->get_recordset('crlm_user_idnumber_temp')) {
        foreach ($rs as $record) {

            // Store whether we're currently on the first user record, whose idnumber
            // will not change
            $first = true;

            // Store the userids and usernames of the appropriate users
            $userids = array();
            $usernames = array();

            // Store whether there was some failure generating an idnumber
            $idnumber_generation_failure = false;

            // By default, obtain the least recently modified record
            $sort_condition = 'timemodified';

            $sql = "SELECT pu.id
                    FROM {user} mu
                    JOIN {".user::TABLE."} pu
                      ON mu.idnumber = pu.idnumber
                      AND mu.username = pu.username
                      AND mu.idnumber = ?";
            $params = array($record->idnumber);

            if ($correct_record = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE)) {
                // Found corresponding user with username and idnumber matching, so
                // prioritize it
                $sort_condition = 'id = '.$correct_record->id.' DESC';
            }

            if ($rs2 = $DB->get_recordset(user::TABLE, array('idnumber' => $record->idnumber), $sort_condition)) {
                foreach ($rs2 as $record2) {

                    // Store information about the current user
                    $userids[] = $record2->id;
                    $usernames[] = $record2->username;

                    // Store whether some idnumber generation attempt was successful
                    $generated = false;

                    if (!$first) {
                        // Use this flag to determine if a unique random string was generated

                        // Attempt to generate a unique random idnumber
                        for ($i = 0; $i < 10; $i++) {
                            $record2->idnumber = random_string(15);
                            if (!$DB->record_exists('user', array('idnumber' => $record2->idnumber)) &&
                                !$DB->record_exists(user::TABLE, array('idnumber' => $record2->idnumber))) {
                                $DB->update_record(user::TABLE, $record2);
                                $generated = true;
                                break;
                            }
                        }

                    }

                    if (!$first && !$generated) {
                        //this is where we would ideally send a failure message

                        $idnumber_generation_failure = true;
                    }

                    $first = false;
                }
            }

            //this is where we would ideally send a success message but it's current
            //not possible because this is called during the upgrade before the messages
            //setup happens
        }
    }

    // Drop the temp table
    $result = $result && $DB->execute("DROP TABLE {crlm_user_idnumber_temp}");

    return $result;
}

/**
 * Migrates certificate border & seal image files from ELIS 1.9x to 2.x
 * @return boolean true on success, otherwise false
 */
function pm_migrate_certificate_files() {
    global $CFG;
    $result = true;
    // Migrate directories: olddir => newdir
    $dirs = array(
        '1/curriculum/pix/certificate/borders'  => 'elis/program/pix/certificate/borders',
        '1/curriculum/pix/certificate/seals'    => 'elis/program/pix/certificate/seals',
        'curriculum/pix/certificates/templates' => 'elis/program/pix/certificates/templates'
    );
    foreach ($dirs as $olddir => $newdir) {
        $oldpath = $CFG->dataroot .'/'. $olddir;
        $newpath = $CFG->dataroot .'/'. $newdir;
        if (is_dir($oldpath) && ($dh = opendir($oldpath))) {
            while (($file = readdir($dh)) !== false) {
                if (is_file($oldpath .'/'. $file)) {
                    if (!is_dir($newpath) && !mkdir($newpath, 0777, true)) {
                        install_msg("\n pm_migrate_certificate_files(): Failed creating certificate directory: {$newpath}");
                    } else if (!copy($oldpath .'/'. $file, $newpath .'/'. $file)) {
                        install_msg("\n pm_migrate_certificate_files(): Failed copying certificate file: {$oldpath}/{$file} to {$newpath}/{$file}");
                    }
                }
            }
            closedir($dh);
        }
    }
    return $result;
}

/**
 * As of Moodle 2.2 optional_param() calls optional_param_array() if the input data is an array but that new function
 * can only handle single-dimensional arrays, not 2-dimensional arrays like the ones used for data submission on the
 * ELIS PM class student enrolment / instructor assignment pages.
 *
 * This function basically abstracts out that process and does it manually.
 *
 * Expected data input:
 *
 *     array(
 *         [userid] => array(array of properties from editing table)
 *     )
 *
 * @param none
 * @return array An array of sanitised user data
 */
function pm_process_user_enrolment_data() {
    $users = array();

    // ELIS-4089 -- Moodle 2.2 can only handle single-dimensional arrays via optional_param =(
    if (isset($_POST['users'] )&& ($userdata = $_POST['users']) && is_array($userdata)) {
        foreach ($userdata as $i => $userdatum) {
            if (is_array($userdatum)) {
                foreach ($userdatum as $key => $val) {
                    $users[$i][$key] = clean_param($val, PARAM_CLEAN);
                }
            }
        }
    }

    return $users;
}

/**
 * Given a float grade value, return a representation of the number meant for UI display
 *
 * An integer value will be returned without any decimals included and a true floating point value
 * will be reduced to only displaying two decimal digits _with_ rounding.
 *
 * @param float $grade The floating point grade value
 * @return string The grade value formatted for display
 */
function pm_display_grade($grade) {
    $val = false;
    if (is_float($grade)) {
        // passed value is definitely as float so just round it
        $val = round($grade, 2, PHP_ROUND_HALF_UP);
    } else if (preg_match('/([0-9]+)(\.[0-9]+)/', $grade, $matches) && count($matches) == 3) {
        // passed value is a numeric string with decimals, round if decimals not all zero
        $val = ($matches[2] == 0) ? $matches[1] : round(floatval($matches[0]), 2, PHP_ROUND_HALF_UP);
    }
    // if we did any rounding of the passed grade then return that
    return (($val !== false) ? sprintf('%0.2f', $val) : (string)$grade);
}

/**
 * Determines whether, on the "My Moodle" page, we should instead redirect to
 * the Program Management Daskboard
 *
 * @param boolean $editing True if we are currently editing the page, otherwise
 *                         false
 * @return boolean True if we should redirect, otherwise false
 */
function pm_mymoodle_redirect($editing = false) {
    global $USER, $DB;

    if ($editing) {
        //editing, so do not redirect
        return false;
    }

    if (!isloggedin()) {
        //the page typically handles this but worth sanity checking
        return false;
    }

    if (!$DB->record_exists('user', array('id' => $USER->id))) {
        //require_login handles this but worth sanity checking
        return false;
    }

    if (has_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM))) {
        //don't force admins to redirect
        return false;
    }

    //check the setting
    return (!empty(elis::$config->elis_program->mymoodle_redirect) &&
            elis::$config->elis_program->mymoodle_redirect == 1);
}

// Retrieve the selection record from a session
function retrieve_session_selection_bulkedit($id, $action) {
    global $SESSION;

    $pageid = optional_param('id', 1, PARAM_INT);
    $page = optional_param('s', '', PARAM_ALPHA);
    $target = optional_param('target', '', PARAM_ALPHA);

    if (empty($target)) {
        $target = $action;
    }

    $pagename = $page . $pageid . $target;

    if (isset($SESSION->associationpage[$pagename][$id])) {
        return $SESSION->associationpage[$pagename][$id];
    } else {
        return false;
    }

    return false;
}

/**
 * Prints inputs required for bulk edit checkbox persistence.
 * @param  array  $ids     An array of IDs to note as checked.
 * @param  int    $classid The ID of the class the IDs belong to.
 * @param  string $page    The page type they're checked on. (ex. stu)
 * @param  string $target  The page section they're checked on. (ex. bulkedit)
 */
function print_ids_for_checkbox_selection($ids, $classid, $page, $target) {
    $baseurl = get_pm_url()->out_omit_querystring().'?&id='.$classid.'&s='.$page.'&target='.$target;
    echo '<input type="hidden" id="baseurl" value="'.$baseurl.'" /> ';
    echo '<input type="hidden" id="selfurl" value="'.qualified_me().'" /> ';
    $result  = implode(',', $ids);
    echo '<input type="hidden" id="persist_ids_this_page" value="'.$result.'" /> ';
}

/**
 * Function to append suffix to string, but, only once
 * - if already present doesn't re-append
 *
 * @param string $str     The string to append to
 * @param string $suffix  The string to append
 * @param array  $options associate array of options, including:
 *                        'maxlength' => int - maximum length of returned str
 *                        'prepend'   => bool, false appends, true prepends $suffix
 *                        'casesensitive' => bool, caseinsensitive by default
 *                        'strict' => bool, true if $suffix must end (begin for prepend)
 * @return string         The appended string
 */
function append_once($str, $suffix, $options = array()) {
    $has_suffix = empty($options['casesensitive']) ? stripos($str, $suffix)
                                                   : strpos($str, $suffix);
    $prepend = !empty($options['prepend']);
    $strict = !empty($options['strict']);
    $maxlen = !empty($options['maxlength'])
              ? ($options['maxlength'] - strlen($suffix))
              : 0;
    if ($prepend) {
        if ($has_suffix === FALSE || ($strict && $has_suffix !== 0)) {
            if ($maxlen) {
                $str = substr($str, 0, $maxlen);
            }
            return $suffix . $str;
        }
    } else if ($has_suffix === FALSE ||
              ($strict && $has_suffix != (strlen($str) - strlen($suffix)))) {
        if ($maxlen) {
            $str = substr($str, 0, $maxlen);
        }
        return $str . $suffix;
    }

    // $suffix already in $str
    return $str;
}

// Retrieve the selection record from a session
function retrieve_session_selection($id, $action) {
    global $SESSION;

    $pageid = optional_param('id', 1, PARAM_INT);
    $page = optional_param('s', '', PARAM_ALPHA);
    $target = optional_param('target', '', PARAM_ALPHA);

    if (empty($target)) {
        $target = $action;
    }

    $pagename = $page . $pageid . $target;

    if (isset($SESSION->associationpage[$pagename])) {
        foreach ($SESSION->associationpage[$pagename] as $selectedcheckbox) {
            $record = json_decode($selectedcheckbox);
            if($record->id == $id) {
                return $selectedcheckbox;
            }
        }
    }

    return false;
}

// Prints the checkbox selection
function print_checkbox_selection($classid, $page, $target) {
    global $SESSION;

    $pagename = $page.$classid.$target;
    $baseurl = get_pm_url()->out_omit_querystring() . '?&id='.$classid.'&s='.$page.'&target=' . $target;
    echo '<input type="hidden" id="baseurl" value="' . $baseurl .'" /> ';

    if(isset($SESSION->associationpage[$pagename])) {
        $selectedcheckboxes = $SESSION->associationpage[$pagename];
        if (is_array($selectedcheckboxes)) {
            $selection = array();
            foreach ($selectedcheckboxes as $selectedcheckbox) {
                $record = json_decode($selectedcheckbox);
                $selection[] = $record->id;
            }
            $result  = implode(',', $selection);
            echo '<input type="hidden" id="selected_checkboxes" value="' . $result .'" /> ';
        }
    }
}

function session_selection_deletion($target) {
    global $SESSION;
    $pageid = optional_param('id', 1, PARAM_INT);
    $page = optional_param('s', '', PARAM_ALPHA);

    $pagename = $page.$pageid.$target;

    if (isset($SESSION->associationpage[$pagename])) {
        unset($SESSION->associationpage[$pagename]);
    }
}

/**
 * Function to move any custom fields with an invalid category
 * into a category called Miscellaneous
 *
 */
function pm_fix_orphaned_fields() {
    global $DB;

    $misc_cat = get_string('misc_category','elis_program');
    //set up context array
    $context_array = context_elis_helper::get_all_levels();
    foreach ($context_array as $contextlevel=>$contextname) {

        //find all fields with non existant category assignments
        $sql = "SELECT field.id
                  FROM {".field::TABLE."} field
                  JOIN {".field_contextlevel::TABLE."} ctx ON ctx.fieldid = field.id AND ctx.contextlevel = ?
                  WHERE NOT EXISTS (
                    SELECT 'x' FROM {".field_category::TABLE."} category
                    WHERE category.id = field.categoryid)";
        $params = array($contextlevel);
        $rs = $DB->get_recordset_sql($sql, $params);

        //if any are found - then check if miscellaneous category exists - if not, create it
        foreach ($rs as $field) {
            $sql = "SELECT category.id
                    FROM {".field_category::TABLE."} category
                    JOIN {".field_category_contextlevel::TABLE."} categorycontext
                      ON categorycontext.categoryid = category.id
                    WHERE categorycontext.contextlevel = ?
                      AND category.name = ?";
            $params = array($contextlevel,$misc_cat);

            $categoryid = $DB->get_field_sql($sql, $params);

            //create a miscellaneous category if it doesn't already exist
            if (!$categoryid) {
                // create an empty category
                $category = new field_category(array('name'=>$misc_cat));
                $category->save();
                $categorycontext = new field_category_contextlevel();
                $categorycontext->categoryid = $category->id;
                $categorycontext->contextlevel = $contextlevel;
                $categorycontext->save();
                $categoryid = $category->id;
            }
            $field = new field($field->id);

            // set the field category to the Miscellaneous category
            $field->categoryid = $categoryid;
            $field->save();
        }
        $rs->close();
    }
}

/**
 * Function to convert time in user's timezone to GMT
 * Note: Moodle function usertime() doesn't include DST offset
 * @param int $usertime timestamp in user's timezone
 * @param float|int|string $timezone optional timezone to use, defaults to user's
 * @return int  timestamp in GMT
 */
function pm_gmt_from_usertime($usertime, $timezone = 99) {
    $tz = get_user_timezone_offset($timezone);
    if (abs($tz) > 13) {
        return $usertime;
    }
    $usertime -= (int)($tz * HOURSECS);
    if ($timezone == 99 || !is_numeric($timezone)) {
        $usertime -= dst_offset_on($usertime, $timezone);
    }
    return $usertime;
}

/**
 * Given date/time components return the equivalent GMT timestamp for specified
 * date/time in user's timezone.
 *
 * @param int $hour            the hour in specified or user's timezone (0-23)
 * @param int $minute          the minute in specified or user's timezone (0-59)
 * @param int $second          the second in specified or user's timezone (0-59)
 * @param int $month           the month in specified or user's timezone (1-12)
 * @param int $day             the day in specified or user's timezone (0-31)
 * @param int $year            the year in specified or user's timezone
 * @param int|string $timezone the timezone the specified time is relative to.
 * @return int the GMT timestamp in specified or user's timezone
 */
function pm_timestamp($hour = null, $minute = null, $second = null, $month = null, $day = null, $year = null, $timezone = 99) {
    if ($hour === null) {
        $hour = gmdate('H');
    }
    if ($minute === null) {
        $minute = gmdate('i');
    }
    if ($second === null) {
        $second = gmdate('s');
    }
    if ($month === null) {
        $month = gmdate('n');
    }
    if ($day === null) {
        $day = gmdate('j');
    }
    if ($year === null) {
        $year = gmdate('Y');
    }
    return make_timestamp($year, $month, $day, $hour, $minute, $second, $timezone);
}

/**
 * Function for selecting roles in PM admin settings
 *
 * @param  array $roles  Output role array of roleids mapped to role names
 * @params array $contextlevels Optional assignable context-levels, i.e. array(CONTEXT_COURSE), leave null for all (default)
 * @uses $DB
 */
function pm_get_select_roles_for_contexts(&$roles, array $contextlevels = null) {
    global $DB;
    $sql = 'SELECT r.* FROM {role} r';
    if (!empty($contextlevels)) {
        $sql .= ' JOIN {role_context_levels} rcl ON r.id = rcl.roleid WHERE rcl.contextlevel IN ('.implode(',', $contextlevels).')';
    }
    $rolers = $DB->get_recordset_sql($sql);
    foreach ($rolers AS $id => $role) {
        $roles[$id] = strip_tags(format_string(!empty($role->name) ? $role->name : $role->shortname, true));
    }
    unset($rolers);
}

