<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2010 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2008-2010 Remote Learner.net Inc http://www.remote-learner.net
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


class enrolreport extends report {

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
    function enrolreport($id = '') {
        parent::report($id);

        $this->type        = 'class';
        $this->title       = 'Enrolment Report';
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
        global $CURMAN, $CFG, $USER;

    /// Don't include users with the 'groupleader' role at the site level.
        $context = get_context_instance(CONTEXT_SYSTEM, SITEID);
        $procid = get_field('role', 'id', 'shortname', 'groupleader');

        $LIKE     = $CURMAN->db->sql_compare();
        if ($CURMAN->db->_dbconnection->databaseType == 'postgres7') {
            $FULLNAME = 'usr.firstname || \' \' || COALESCE(usr.mi, \'\') || \' \' || usr.lastname';
        } else {
            $FULLNAME = 'CONCAT(usr.firstname,\' \',IFNULL(usr.mi, \'\'),\' \',usr.lastname)';
        }


        $cselect = 'SELECT COUNT(DISTINCT usr.id) ';
        $select  = "SELECT usr.id as id,
                           usr.idnumber as idnumber,
                           usr.email as email,
                           MAX(usr.timecreated) as timecreated,
                           usr.birthdate as birthdate,
                           usr.gender as gender,
                           usr.country as country,
                           clst.name as clustername,
                           MAX(clsgrd.timegraded) as timegraded,
                           $FULLNAME as student,
                           curass.curriculumid as curriculumid,
                           cras1.curriculumid as cras1id, cras2.curriculumid as cras2id, cras3.curriculumid as cras3id
                   ";
        $tables  = "FROM " . $CURMAN->db->prefix_table(USRTABLE) . " usr
                    LEFT JOIN {$CFG->prefix}user mu ON mu.idnumber = usr.idnumber
                    LEFT JOIN {$CFG->prefix}role_assignments ra ON (ra.roleid = $procid) AND ra.contextid = {$context->id} AND ra.userid = mu.id
                    LEFT JOIN " . $CURMAN->db->prefix_table(CLSGRTABLE) . " clsgrd ON clsgrd.userid = usr.id
                    LEFT JOIN " . $CURMAN->db->prefix_table(CLSTUSERTABLE) . " uclst ON uclst.userid = usr.id
                    LEFT JOIN " . $CURMAN->db->prefix_table(CLSTTABLE) . " clst ON clst.id = uclst.clusterid
                    LEFT JOIN " . $CURMAN->db->prefix_table(CURASSTABLE) . " curass ON curass.userid = usr.id
                    LEFT JOIN " . $CURMAN->db->prefix_table(CURASSTABLE) . " cras1 ON cras1.userid = usr.id
                    LEFT JOIN " . $CURMAN->db->prefix_table(CURASSTABLE) . " cras2 ON cras2.userid = usr.id AND (cras2.id != cras1.id)
                    LEFT JOIN " . $CURMAN->db->prefix_table(CURASSTABLE) . " cras3 ON cras3.userid = usr.id AND (cras3.id != cras1.id) AND (cras3.id != cras2.id)
                   ";

        $timenow = time();
        $yearago = $timenow - (365 * 24 * 60 * 60);
        $yearagostr = date('Y/m/d', $yearago);

        $where   = "(ra.id IS NULL) ";
        $group   = "GROUP BY usr.id ";

        if (!has_capability('block/curr_admin:viewreports', $context)) {
            if (has_capability('block/curr_admin:viewgroupreports', $context)) {
                $clstid = get_field(CLSTUSERTABLE, 'clusterid', 'userid', cm_get_crlmuserid($USER->id));
                $where .= "AND (uclst.clusterid = $clstid) ";
            }
        }

        if ($this->extrasql) {
            $where .= (!empty($where) ? ' AND ' : '') . $this->extrasql . ' ';
        }

        if (!empty($where)) {
            $where = 'WHERE ' . $where . ' ';
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
        $sql           = $cselect . $tables . $where;
        $this->numrecs = $CURMAN->db->count_records_sql($sql);

    /// Get the current 'page' of results.
        $sql        = $select . $tables . $where . $group . $sort . $limit;
        $this->data = $CURMAN->db->get_records_sql($sql);

        $context = get_context_instance(CONTEXT_SYSTEM, SITEID);

        if (!empty($this->data)) {
            $curricula = get_records(CURTABLE);
            $countries = cm_get_list_of_countries();
            foreach ($this->data as $di => $datum) {
                $datum->currentclassid = 0;
                $datum->currentclass   = '';
                $datum->lastclassid    = 0;
                $datum->lastclass      = '';

                $context = get_context_instance(CONTEXT_SYSTEM, SITEID);

                $timenow = time();

                if (!$download) {
//                  if (has_capability('block/curr_admin:viewlocationusers', $context)) {
                    $datum->student = '<a href="index.php?s=rep&amp;section=rept&amp;type=' .
                                         'induser&amp;frompage=enrol&amp;user=' . $datum->id . '">' .
                                         $datum->student . '</a>';
//                  }
                }

                if ($datum->timecreated > 0) {
                    $datum->origenroldate = cm_timestamp_to_date($datum->timecreated);
                } else {
                    $datum->origenroldate = get_string('unknown', 'block_curr_admin');
                }
                $datum->birthdate = cm_timestring_to_date($datum->birthdate);
                $datum->timegraded = ($datum->timegraded > 0) ? cm_timestamp_to_date($datum->timegraded) : '';

                switch ($datum->gender) {
                    case 'M':
                    case 'm':
                        $datum->gender = get_string('male', 'block_curr_admin');
                    break;

                    case 'F':
                    case 'f':
                        $datum->gender = get_string('female', 'block_curr_admin');
                    break;

                    default:
                        $datum->gender = get_string('unknown', 'block_curr_admin');
                    break;

                }

                $datum->curricula = '';
                if (!empty($datum->cras1id)) {
                    $datum->curricula .= $curricula[$datum->cras1id]->name;
                }
                if (!empty($datum->cras2id)) {
                    $datum->curricula .= !empty($datum->curricula)?',':''.$curricula[$datum->cras2id]->name;
                }
                if (!empty($datum->cras3id)) {
                    $datum->curricula .= !empty($datum->curricula)?',':''.$curricula[$datum->cras3id]->name;
                }

                if (!empty($datum->country) && isset($countries[$datum->country])) {
                    $datum->country = $countries[$datum->country];
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
     * @param string $alpha    An initial to filter results by.
     * @param string $download The format to download the report in.
     */
    function main($sort = '', $dir = '', $page = 0, $perpage = 20, $download = '', $frompage = '') {

        $this->daywindow = cm_get_param('daywindow', 90);

        if (!isset($this->valid_windows[$this->daywindow])) {
            $this->daywindow = key($this->valid_windows);
        }

        $this->add_column('idnumber', get_string('student_id', 'block_curr_admin'), 'left', true);
        $this->add_column('student', get_string('student_name', 'block_curr_admin'), 'left', true);
        $this->add_column('email', get_string('student_email', 'block_curr_admin'), 'left', true);
        $this->add_column('origenroldate', get_string('curenroldate', 'block_curr_admin'), 'left', true);
        $this->add_column('birthdate', get_string('userbirthdate', 'block_curr_admin'), 'left', true);
        $this->add_column('gender', get_string('usergender', 'block_curr_admin'), 'left', true);
        $this->add_column('timegraded', get_string('last_activity', 'block_curr_admin'), 'left', true);
        $this->add_column('clustername', get_string('cluster', 'block_curr_admin'), 'left', true);
        $this->add_column('curricula', get_string('curriculum', 'block_curr_admin'), 'left', false);
        $this->add_column('country', get_string('country', 'block_curr_admin'), 'left', true);

        $this->set_default_sort('student', 'ASC');

        $this->sort    = !empty($sort) ? $sort : $this->defsort;
        $this->dir     = !empty($dir) ? $dir : $this->defdir;
        $this->page    = $page;
        $this->perpage = !empty($download) ? 9999 : $perpage;

        // create the user filter form
        $this->filter   = new enrollment_filtering(null, $this->baseurl);
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
class enrollment_filtering extends cm_user_filtering {
    /**
     * Contructor
     * @param array array of visible user fields
     * @param string base url used for submission/return, null if the same of current page
     * @param array extra page parameters
     */
    function enrollment_filtering($fieldnames=null, $baseurl=null, $extraparams=null) {
        if (empty($fieldnames)) {
            $fieldnames = array('realname'=>0, 'lastname'=>1, 'firstname'=>1, 'email'=>0, 'city'=>1, 'country'=>1,
                                'username'=>0, 'gender' => 1, 'language' => 1,
                                'clusterid' => 1, 'curriculumid' => 1, 'curenroldate' => 0);
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
            case 'curenroldate':
                return new enrollment_filter_startdate('curenroldate', get_string('curenroldate', 'block_curr_admin'), $advanced, 'curenroldate');

            default:
                return null;
        }
    }
}

class enrollment_filter_startdate extends user_filter_date {
    var $_field;

    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     */
    function enrollment_filter_startdate($name, $label, $advanced, $field) {
        parent::user_filter_date($name, $label, $advanced, $field);
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        $after     = $data['after'];
        $before    = $data['before'];

        if (empty($after) && empty($before)) {
            return '';
        }

        $res = '';
        $timecreated = '';
        $timegraded = '';
        $origenroldate = '';
        $enroldate = '';

        if (!empty($after)) {
            $afterstr  = date('Y/m/d', $after);
            $timecreated   .= "(usr.timecreated IS NOT NULL) AND (origenroldate = '') AND (usr.timecreated >= $after)";
            $timegraded    .= "(timegraded IS NOT NULL) AND (timegraded >= $after)";
            $origenroldate .= "(origenroldate != '') AND (origenroldate >= '$afterstr')";
            $enroldate     .= "(enroldate != '') AND (enroldate >= '$afterstr')";
        }

        if (!empty($before)) {
            $beforestr = date('Y/m/d', $before);
            $timecreated   .= (empty($timecreated) ? "(usr.timecreated IS NOT NULL) AND (origenroldate = '') AND " : " AND ") .
                              "(usr.timecreated <= $before)";
            $timegraded    .= (empty($timegraded) ? "(timegraded IS NOT NULL) AND " : " AND ") .
                              "(timegraded <= $before)";
            $origenroldate .= (empty($origenroldate) ? "(origenroldate != '') AND " : " AND ") .
                              "(origenroldate <= '$beforestr')";
            $enroldate     .= (empty($enroldate) ? "(enroldate != '') AND " : " AND ") .
                              "(enroldate <= '$beforestr')";
        }

        $res .= "(($timecreated) OR ($timegraded) OR ($origenroldate) OR ($enroldate))";
        return $res;
    }
}
?>
