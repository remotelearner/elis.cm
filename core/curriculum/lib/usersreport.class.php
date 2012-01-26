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
require_once CURMAN_DIRLOCATION . '/lib/jasperlib.php';


class usersreport extends report {

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
    function usersreport($id = '') {
        parent::report($id);

        $this->type        = 'class';
        $this->title       = get_string('reportusers', 'block_curr_admin');
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

        $LIKE     = $CURMAN->db->sql_compare();
        $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');

        $cselect = 'SELECT COUNT(DISTINCT usr.id) ';
        $select  = "SELECT usr.id,
                           usr.idnumber as idnumber,
                           clst.display as clustername,
                           usr.country as country,
                           curass.id as curassid,
                           curass.curriculumid as curid,
                           cur.name as curname,
                           $FULLNAME as student ";
        $tables  = "FROM " . $CURMAN->db->prefix_table(USRTABLE) . " usr
                    LEFT JOIN " . $CURMAN->db->prefix_table(CURASSTABLE) . " curass ON curass.userid = usr.id
                    LEFT JOIN " . $CURMAN->db->prefix_table(CURTABLE) . " cur ON cur.id = curass.curriculumid
                    LEFT JOIN ". $CURMAN->db->prefix_table(CLSTUSERTABLE) . " uclst ON uclst.userid = usr.id
                    LEFT JOIN ". $CURMAN->db->prefix_table(CLSTTABLE) . " clst ON clst.id = uclst.clusterid ";
        $where   = '';

        if (!has_capability('block/curr_admin:viewreports', $context)) {
            if (has_capability('block/curr_admin:viewgroupreports', $context)) {
                $clstid = get_field(CLSTUSERTABLE, 'clusterid', 'userid', cm_get_crlmuserid($USER->id));
                $where .= "(uclst.clusterid = $clstid) ";
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
        $sql        = $select . $tables . $where . $sort . $limit;
        $this->data = $CURMAN->db->get_records_sql($sql);

        $context = get_context_instance(CONTEXT_SYSTEM, SITEID);

        if (!empty($this->data)) {
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
                                             'induser&amp;user=' . $datum->id . '">' .
                                             $datum->student . '</a> ' .
                                          '&nbsp;&nbsp;&nbsp;&nbsp;' .
                                          '<a href="' . $CFG->wwwroot . '/auth/mnet/jump.php?hostid=' . jasper_mnet_hostid() .
                                            '&wantsurl=' . rawurlencode(jasper_report_link(jasper_common_reports_folder() . '/induser', array('userid' => $datum->id))) . '" target="RL_ELIS_reports">JasperServer</a>' .
                                          '&nbsp;&nbsp;&nbsp;&nbsp;' .
                                          '<a href="index.php?s=rep&amp;section=rept&amp;type=' .
                                             'transcript&amp;user=' . $datum->id . '">Transcript</a>';
//                  }

//                    if (!empty($datum->currentclass) &&
//                        has_capability('block/curr_admin:viewlocationusers', $context)) {
                    if (!empty($datum->currentclass)) {
                        $datum->currentclass = '<a href="index.php?s=rep&amp;section=rept&amp;type=' .
                                               'classroster&amp;class=' . $datum->currentclassid . '">' .
                                               $datum->currentclass . '</a>';
                    }
//                    if (!empty($datum->lastclass) &&
//                        has_capability('block/curr_admin:viewlocationusers', $context)) {
                    if (!empty($datum->lastclass)) {
                        $datum->lastclass = '<a href="index.php?s=rep&amp;section=rept&amp;type=' .
                                            'classroster&amp;class=' . $datum->lastclassid . '">' .
                                            $datum->lastclass . '</a>';
                    }
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

        $this->add_column('idnumber', get_string('student_id', 'block_curr_admin'), 'left', true);
        $this->add_column('student', get_string('student_name', 'block_curr_admin'), 'left', true);
        $this->add_column('curname', get_string('curriculum', 'block_curr_admin'), 'left', empty($this->curname) ? true: false);
        $this->add_column('clustername', get_string('cluster', 'block_curr_admin'), 'left', true);
        $this->add_column('country', get_string('country', 'block_curr_admin'), 'left', true);

        $this->set_default_sort('student', 'ASC');

        $this->sort    = !empty($sort) ? $sort : $this->defsort;
        $this->dir     = !empty($dir) ? $dir : $this->defdir;
        $this->page    = $page;
        $this->perpage = !empty($download) ? 9999 : $perpage;

        // create the user filter form
        $this->filter   = new cm_user_filtering(null, $this->baseurl);
        $this->extrasql = $this->filter->get_sql_filter();

        $this->get_data(!empty($download));

        if (empty($download)) {
            $output = '';

        /// Nav bar information:
            $bc = '<div style="float:right;">'.$this->numrecs.' ' . get_string('users_found', 'block_curr_admin') . '</div>'.
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

?>
