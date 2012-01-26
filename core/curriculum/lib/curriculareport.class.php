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

require_once CURMAN_DIRLOCATION . '/lib/report.class.php';
require_once CURMAN_DIRLOCATION . '/lib/user.class.php';
require_once CURMAN_DIRLOCATION . '/lib/student.class.php';
require_once CURMAN_DIRLOCATION . '/lib/instructor.class.php';
require_once CURMAN_DIRLOCATION . '/lib/cmclass.class.php';
require_once CURMAN_DIRLOCATION . '/lib/curriculumcourse.class.php';
require_once CURMAN_DIRLOCATION . '/lib/curriculumstudent.class.php';
require_once CURMAN_DIRLOCATION . '/lib/attendance.class.php';
require_once CURMAN_DIRLOCATION . '/lib/cluster.class.php';


class curriculareport extends report {

    var $daywindow;
    var $valid_windows = array(
        90 => '90 days',
        60 => '60 days',
        30 => '30 days',
        15 => '15 days',
        7  => '7 days'
    );

    /**
     * Contructor.
     *
     * @param string $id An identifier for this table (optional).
     * @retrn none
     */
    function curriculareport($id = '') {
        parent::report($id);

        $this->type        = 'class';
        $this->title       = get_string('reportcurricula', 'block_curr_admin');
        $this->fileformats = array(
            'pdf'   => 'PDF',
            'csv'   => 'CSV',
            'excel' => 'Excel'
        );
    }


/////////////////////////////////////////////////////////////////////
//                                                                 //
//  DATA FUNCTIONS:                                                //
//                                                                 //
/////////////////////////////////////////////////////////////////////


    /**
     * Get the data to display for this table page.
     *
     * @param bool $download Flag to not include HTML for report download.
     * @return array An array of data records.
     */
    function get_data($download = false) {
        global $CURMAN, $USER, $CFG;

        $context = get_context_instance(CONTEXT_SYSTEM);

///     Get records for each user, where:
///         - they are in a curriculum, and
///         - the courses in that curriculum have a status of 2 (PASSED), and
///         - all required courses in that curriculum have a status of 2 (PASSED), and
///         - the total number of credits for the passed courses is equal to or greater than the number of credits required for the curriculum


        $LIKE     = $CURMAN->db->sql_compare();
        if ($CURMAN->db->_dbconnection->databaseType == 'postgres7') {
            $FULLNAME = 'usr.firstname || \' \' || COALESCE(usr.mi, \'\') || \' \' || usr.lastname';
        } else {
            $FULLNAME = 'CONCAT(usr.firstname,\' \',IFNULL(usr.mi, \'\'),\' \',usr.lastname)';
        }

        $cselect = 'SELECT COUNT(*) '; //, cc.reqcredits as reqcredits, SUM(cce.credits) AS numcredits ';
        $select  = "SELECT curass.id AS curasid,
                           cc.name AS curname,
                           clst.name as clustername,
                           usr.idnumber AS idnumber,
                           usr.id AS id,
                           $FULLNAME as student,
                           usr.transfercredits as transfercredits,
                           cc.reqcredits as reqcredits,
                           SUM(cce.credits) AS numcredits,
                           MAX(cce.completetime) AS completiondate ";
        $tables  = "FROM {$CFG->prefix}crlm_curriculum_assignment curass
                    INNER JOIN {$CFG->prefix}crlm_user usr ON curass.userid = usr.id
                    INNER JOIN {$CFG->prefix}crlm_curriculum cc ON curass.curriculumid = cc.id
                    INNER JOIN {$CFG->prefix}crlm_curriculum_course ccc ON ccc.curriculumid = cc.id
                    INNER JOIN {$CFG->prefix}crlm_class ccl ON ccl.courseid = ccc.courseid
                    INNER JOIN {$CFG->prefix}crlm_class_enrolment cce ON (cce.classid = ccl.id) AND (cce.userid = curass.userid)
                    LEFT JOIN ". $CURMAN->db->prefix_table(CLSTUSERTABLE) . " uclst ON uclst.userid = usr.id
                    LEFT JOIN ". $CURMAN->db->prefix_table(CLSTTABLE) . " clst ON clst.id = uclst.clusterid ";
        $where   = "";
        $groupby = "GROUP BY curass.id "; //HAVING numcredits > cc.reqcredits "; /// The "HAVING" clause limits the returns to completed CURRICULA only.

        if (!has_capability('block/curr_admin:viewreports', $context)) {
            if (has_capability('block/curr_admin:viewgroupreports', $context)) {
                $clstid = get_field(CLSTUSERTABLE, 'clusterid', 'userid', cm_get_crlmuserid($USER->id));
                $where .= "(uclst.clusterid = $clstid) ";
            }
        }

        if ($this->extrasql['where']) {
            $where .= (!empty($where) ? ' AND ' : '') . $this->extrasql['where'] . ' ';
        }

        if (!empty($where)) {
            $where = 'WHERE ' . $where . ' ';
        }

        if (isset($this->extrasql['groupby'])) {
            $groupby .= ' ' . $this->extrasql['groupby'] . ' ';
        }

        if (!empty($this->sort)) {
            $sort = 'ORDER BY ' . $this->sort . ' ' . $this->dir . ' ';
        } else {
            $sort = '';
        }

        if (!empty($this->perpage)) {
            if ($CURMAN->db->_dbconnection->databaseType == 'postgres7') {
                $limit = 'LIMIT ' . $this->perpage . ' OFFSET ' . $this->page * $this->perpage;
            } else {
                $limit = 'LIMIT ' . $this->page * $this->perpage . ', ' . $this->perpage;
            }
        } else {
            $limit = '';
        }

    /// Count the total number of results.
    /// Because we are using a "GROUP BY", to determine the actual number of rows returned, we need to COUNT the entire data query.
        $sql           = $cselect . ' FROM (' . $select . $tables . $where . $groupby . ') AS numrecs';
        $this->numrecs = $CURMAN->db->count_records_sql($sql);

    /// Get the current 'page' of results.
        $sql        = $select . $tables . $where . $groupby . $sort . $limit;
        $this->data = $CURMAN->db->get_records_sql($sql);

        $context = get_context_instance(CONTEXT_SYSTEM, SITEID);

        if (!empty($this->data)) {
            foreach ($this->data as $di => $datum) {
                $context = get_context_instance(CONTEXT_SYSTEM, SITEID);
                if (!$download) {
                    $datum->student = '<a href="index.php?s=rep&amp;section=rept&amp;type=' .
                                         'induser&amp;frompage=curricula&amp;user=' . $datum->id . '">' .
                                         $datum->student . '</a> ' .
                                      '&nbsp;&nbsp;&nbsp;&nbsp;' .
                                      '<a href="index.php?s=rep&amp;section=rept&amp;type=' .
                                         'transcript&amp;frompage=curricula&amp;user=' . $datum->id . '">' . get_string('transcript', 'block_curr_admin') . '</a>';
                    $datum->completiondate = ($datum->completiondate > 0) ? userdate($datum->completiondate, '%b %d, %Y') : '-';
                }

                $this->data[$di] = $datum;
            }
        }
    }


/////////////////////////////////////////////////////////////////////
//                                                                 //
//  DISPLAY FUNCTIONS:                                             //
//                                                                 //
/////////////////////////////////////////////////////////////////////


    /**
     * Main display function.
     *
     * Fetch and display (or download) the required data.
     *
     * @param string $sort     The column to sort results by.
     * @param string $dir      The direction to sort by.
     * @param int    $page     The page number to display results for.
     * @param int    $perpage  The number of results per page.
     * @param string $search   A string to search for.
     * @param string $download The format to download the report in.
     */
    function main($sort = '', $dir = '', $page = 0, $perpage = 20, $download = '') {

        $context = get_context_instance(CONTEXT_SYSTEM, SITEID);
        if (has_capability('block/curr_admin:viewreports', $context)) {
            // Okay
        } else if (has_capability('block/curr_admin:viewgroupreports', $context)) {
            // Verify userid
        } else {
            error("No access allowed.");
        }

        $this->daywindow = cm_get_param('daywindow', 90);

        if (!isset($this->valid_windows[$this->daywindow])) {
            $this->daywindow = key($this->valid_windows);
        }

        $this->add_column('idnumber', get_string('idnumber', 'block_curr_admin'), 'left', true);
        $this->add_column('student', get_string('student_name', 'block_curr_admin'), 'left', true);
        $this->add_column('curname', get_string('curriculum', 'block_curr_admin'), 'left', empty($this->curname) ? true: false);
        $this->add_column('clustername', get_string('proctor', 'block_curr_admin'), 'left', true);
        $this->add_column('reqcredits', get_string('required_credits', 'block_curr_admin'), 'left', true);
        $this->add_column('numcredits', get_string('completed_credits', 'block_curr_admin'), 'left', true);
        $this->add_column('transfercredits', get_string('transfercredits', 'block_curr_admin'), 'left', true);
        $this->add_column('completiondate', get_string('completiondate', 'block_curr_admin'), 'left', true);

        $this->set_default_sort('student', 'ASC');

        $this->sort    = !empty($sort) ? $sort : $this->defsort;
        $this->dir     = !empty($dir) ? $dir : $this->defdir;
        $this->page    = $page;
        $this->perpage = !empty($download) ? 9999 : $perpage;

        // create the user filter form
        $this->filter   = new curricula_filtering(null, $this->baseurl);
        $this->extrasql = $this->filter->get_sql_filter();

        $this->get_data(!empty($download));

        if (empty($download)) {
            $output = '';

        /// Nav bar information:
            $bc = '<div style="float:right;">'.$this->numrecs . get_string('users_found', 'block_curr_admin') . '</div>'.
                  '<span class="breadcrumb"><a href="index.php?s=rep&amp;section=rept">' . get_string('reports', 'block_curr_admin') . '</a> ' .
                  '&raquo; ' . $this->title . '</span>';
            $output .= cm_print_heading_block($bc, '', true);
            $output .= '<br />' . "\n";

            $output .= $this->print_download_menu();
            $output .= $this->print_header();

            if (!empty($this->data)) {
                $output .= $this->display();
            } else {
                $output .= '<h2>' . get_string('no_matching_users', 'block_curr_admin') . '</h2>';
            }

            $output .= $this->print_footer();

            echo $output;
        } else {
            $this->download($download);
        }
    }
}

/**
 * User filtering wrapper class.
 */
class curricula_filtering extends cm_user_filtering {
    /**
     * Contructor
     * @param array array of visible user fields
     * @param string base url used for submission/return, null if the same of current page
     * @param array extra page parameters
     */
    function curricula_filtering($fieldnames=null, $baseurl=null, $extraparams=null) {
        if (empty($fieldnames)) {
            $fieldnames = array('realname'=>0, 'lastname'=>1, 'firstname'=>1, 'email'=>0, 'city'=>1, 'country'=>1,
                                'username'=>0, 'gender' => 1, 'language' => 1,
                                'clusterid' => 1, 'curriculumid' => 0, 'completed' => 1, 'completiondate' => 0);
        }
        parent::cm_user_filtering($fieldnames, $baseurl, $extraparams);
    }

    /**
     * Creates known user filter if present
     * @param string $fieldname
     * @param boolean $advanced
     * @return object filter
     */
    function get_field($fieldname, $advanced) {
        global $USER;

        if (($return = parent::get_field($fieldname, $advanced)) !== null) {
            return $return;
        }
        switch ($fieldname) {
            case 'completed':
                return new curicula_filter_completed('completed', get_string('completed', 'block_curr_admin'), $advanced, 'completed');

            case 'completiondate':
                return new curicula_filter_completiondate('completiondate', get_string('completiondate', 'block_curr_admin'), $advanced, 'cce.completetime');

            default:
                return null;
        }
    }
    /**
     * Returns sql where statement based on active user filters
     * @param string $extra sql
     * @return string
     */
    function get_sql_filter($extra='') {
        global $SESSION;

        if (isset($SESSION->user_filtering['completed'])) {
            $fcompleted = $SESSION->user_filtering['completed'];
            unset($SESSION->user_filtering['completed']);
        }

        $return = array();
        $return['where'] = parent::get_sql_filter($extra);
        if (isset($fcompleted)) {
            $field = $this->_fields['completed'];
            foreach($fcompleted as $i=>$data) {
                $return['groupby'] = $field->get_sql_filter($data);
            }
            $SESSION->user_filtering['completed'] = $fcompleted;
        }

        return $return;
    }
}

class curicula_filter_completed extends user_filter_yesno {
    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     */
    function curicula_filter_completed($name, $label, $advanced, $field) {
        parent::user_filter_yesno($name, $label, $advanced, $field);
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        $value = addslashes($data['value']);

        $res = '';
        if ($value == '1') {
            $res = "HAVING numcredits >= cc.reqcredits";
        } else if ($value == '0') {
            $res = "HAVING numcredits < cc.reqcredits";
        }

        return $res;
    }
}

class curicula_filter_completiondate extends user_filter_date {
    var $_field;

    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     */
    function curicula_filter_completiondate($name, $label, $advanced, $field) {
        parent::user_filter_date($name, $label, $advanced, $field);
    }


    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {

        $res = parent::get_sql_filter($data);
        $res .= " AND (cce.completestatusid = ".STUSTATUS_PASSED.")";
        return $res;
    }
}
?>
