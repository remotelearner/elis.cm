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


class nonqualreport extends report {

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
    function nonqualreport($id = '') {
        parent::report($id);

        $this->type        = 'nonqual';
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
        global $CURMAN;

        $LIKE     = $CURMAN->db->sql_compare();
        $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');

        $cselect = 'SELECT COUNT(stu.id) ';
        $select  = "SELECT DISTINCT(stu.id),
                           usr.idnumber as idnumber,
                           $FULLNAME as crewmember,
                           usr.local as location,
                           crs.id as crsid,
                           cur.id as curid,
                           cls.id as clsid,
                           crs.name as classname,
                           stu.completestatusid as completestatus,
                           stu.completetime as datecomplete,
                           stu.completestatusid as completestatus,
                           curcrs.frequency as frequency,
                           curcrs.timeperiod as timeperiod ";
        $tables  = "FROM " . $CURMAN->db->prefix_table(USRTABLE) . " usr
                    INNER JOIN " . $CURMAN->db->prefix_table(STUTABLE) . " stu ON stu.userid = usr.id
                    INNER JOIN " . $CURMAN->db->prefix_table(CLSTABLE) . " cls ON cls.id = stu.classid
                    INNER JOIN " . $CURMAN->db->prefix_table(CRSTABLE) . " crs ON crs.id = cls.courseid
                    LEFT JOIN " . $CURMAN->db->prefix_table(CURCRSTABLE) . " curcrs ON curcrs.courseid = crs.id
                    LEFT JOIN " . $CURMAN->db->prefix_table(CURTABLE) . " cur ON cur.id = curcrs.curriculumid
                    LEFT JOIN " . $CURMAN->db->prefix_table(CURASSTABLE) . " curass ON curass.curriculumid = cur.id ";
        $where   = 'curass.userid = stu.userid ';

        if (!empty($this->search)) {
            $search = trim($this->search);
            $where .= (!empty($where) ? ' AND ' : '') . "(($FULLNAME $LIKE '%{$this->search}%')" .
                      " OR (usr.idnumber $LIKE '%{$this->search}%')" .
                      (isset($this->headers['location']) ? " OR (usr.local $LIKE '%{$this->search}%')" : '') .
                      ") ";
        }

        if ($this->alpha) {
            $where .= (!empty($where) ? ' AND ' : '') . "($FULLNAME $LIKE '{$this->alpha}%') ";
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

    /// Add non-DB info to the records for display.
        if (!empty($this->data)) {
            $timenow = time();

            foreach ($this->data as $di => $datum) {
                if (!empty($datum->datecomplete) && !empty($datum->frequency)) {
                    switch ($datum->timeperiod) {
                        case 'year':
                            $datum->dueby = cm_timedelta($datum->datecomplete, $datum->frequency);
                            break;

                        case 'month':
                            $datum->dueby = cm_timedelta($datum->datecomplete, 0, $datum->frequency);
                            break;

                        case 'week':
                            $datum->dueby = cm_timedelta($datum->datecomplete, 0, 0, $datum->frequency);
                            break;

                        case 'day':
                            $datum->dueby = cm_timedelta($datum->datecomplete, 0, 0, 0, $datum->frequency);
                            break;

                        default:
                            $datum->dueby = 0;
                            break;
                    }

                /// Check if the user has a renewal coming up for this course within the specified
                /// window of days.
                    if (empty($datum->dueby) ||
                        (($datum->dueby - $timenow) > $this->daywindow * 24 * 60 * 60)) {
                        unset($this->data[$di]);
                        continue;

                    } else {

                    /// The user DOES have a renewal coming up, see if they have completed another
                    /// class in the same course at a later date.
                        $sql = "SELECT COUNT(stu.id)
                                FROM " . $CURMAN->db->prefix_table(STUTABLE) . " stu
                                INNER JOIN " . $CURMAN->db->prefix_table(CLSTABLE) . " cls ON cls.id = stu.classid
                                WHERE cls.courseid = '{$datum->crsid}'
                                AND cls.startdate > '{$datum->datecomplete}'
                                AND cls.enddate = '0' ";

                        if (!$CURMAN->db->count_records_sql($sql)) {
                            $datum->nextdue = floor(($datum->dueby - $timenow) / (24 * 60 * 60));
                        } else {
                            unset($this->data[$di]);
                            continue;
                        }

                        $datum->dueby = date('M j, Y', $datum->dueby);
                    }

                } else {
                    unset($this->data[$di]);
                    continue;
                }

            /// This should NEVER be empty.... *glare*
                if ($datum->nextdue == 1) {
                    $datum->nextdue = $datum->strnextdue . ' day';
                } else {
                    $datum->strnextdue = $datum->nextdue . ' days';
                }

                if ($datum->nextdue > 0) {
                    $datum->status = 'Qualified';
                } else {
                    $datum->status = 'Non-Qualified';
                }

            /// Format class starting and ending dates.
                if (!empty($datum->completetime)) {
                    $datum->completetime = date('M j, Y', $datum->completetime);
                } else {
                    $datum->completetime = '-';
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
    function main($sort = '', $dir = '', $page = 0, $perpage = 20, $search = '',
                  $alpha = '', $download = '') {

        $this->daywindow = cm_get_param('daywindow', 90);

        if (!isset($this->valid_windows[$this->daywindow])) {
            $this->daywindow = key($this->valid_windows);
        }

        $this->set_title('Non-Qualification report for ' . $this->daywindow . ' day window');

        if (empty($download)) {
            $output = '';

            $bc = '<span class="breadcrumb"><a href="index.php?s=rep&amp;section=rept">Reports</a> ' .
                  '&raquo; ' . $this->title . '</span>';

            $output .= cm_print_heading_block($bc, '', true);
            $output .= '<br />' . "\n";
        }

        $this->add_column('idnumber', 'ID', 'left', true);
        $this->add_column('student', 'Student', 'left', true);
        $this->add_column('classname', 'Class', 'left', true);
        $this->add_column('strnextdue', 'Next Due', 'left', false);

        $this->set_default_sort('crewmember', 'ASC');

        $this->sort    = !empty($sort) ? $sort : $this->defsort;
        $this->dir     = !empty($dir) ? $dir : $this->defdir;
        $this->page    = 0;
        $this->perpage = 9999;
        $this->search  = $search;
        $this->alpha   = $alpha;

        $this->get_data(!empty($download));

        if (empty($download)) {
            $this->baseurl .= '&amp;daywindow=' . $this->daywindow;

            $output .= $this->print_download_menu();
            $output .= '<br /><fieldset>' . "\n";
            $output .= '<form action="index.php" method="post">';

            $output .= '<input type="hidden" name="s" value="rep" />';
            $output .= '<input type="hidden" name="section" value="rept" />';
            $output .= '<input type="hidden" name="type" value="nonqual" />';
            $output .= '<input type="hidden" name="sort" value="' . $this->sort . '" />';
            $output .= '<input type="hidden" name="dir" value="' . $this->dir . '" />';
            $output .= 'Display report for users who will be non-qualifieid within ';
            $output .= cm_choose_from_menu($this->valid_windows, 'daywindow', $this->daywindow,
                                           '', '', '', true);
            $output .= '<input type="submit" value="' . get_string('display', 'block_curr_admin') . '" />';
            $output .= '</form>';
            $output .= '</fieldset><br />';

            if (!empty($this->data)) {
                $output .= $this->display();
            } else {
                $output .= '<h2>' . get_string('hide_nonqualified_users', 'block_curr_admin') . ' ' .
                           $this->valid_windows[$this->daywindow] . '</h2>';
            }

            echo $output;
        } else {
            $this->download($download);
        }
    }


    /**
     * Get the data necessary for sending out a notification e-mail about NQ users.
     *
     */
    function get_emaildata($daywindow = 30) {
        $this->daywindow = isset($this->valid_windows[$daywindow]) ? $daywindow : 30;

        $this->set_default_sort('crewmember', 'ASC');

        $this->sort    = $this->defsort;
        $this->dir     = $this->defdir;
        $this->page    = 0;
        $this->perpage = 9999;
        $this->search  = '';
        $this->alpha   = '';

        $this->get_data();

        return $this->data;
    }
}

?>
