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


class reclocreport extends report {

    var $loc;

    /**
     * Contructor.
     *
     * @param string $id An identifier for this table (optional).
     * @retrn none
     */
    function reclocreport($id = '') {
        parent::report($id);

        $this->loc         = '';
        $this->usrid       = 0;
        $this->type        = 'recloc';
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
        $cselect  = 'SELECT COUNT(stu.id) ';
        $select   = "SELECT DISTINCT(stu.id),
                            usr.idnumber as idnumber,
                             $FULLNAME as username,
                            crs.name as classname,
                            stu.completetime as datecomplete,
                            stu.completestatusid as completestatus,
                            curcrs.frequency as frequency,
                            curcrs.timeperiod as timeperiod ";
        $tables   = "FROM " . $CURMAN->db->prefix_table(STUTABLE) . " stu
                     INNER JOIN " . $CURMAN->db->prefix_table(USRTABLE) . " usr ON usr.id = stu.userid
                     INNER JOIN " . $CURMAN->db->prefix_table(CLSTABLE) . " cls ON cls.id = stu.classid
                     INNER JOIN " . $CURMAN->db->prefix_table(CRSTABLE) . " crs ON crs.id = cls.courseid
                     LEFT JOIN " . $CURMAN->db->prefix_table(CURCRSTABLE) . " curcrs ON curcrs.courseid = crs.id
                     LEFT JOIN " . $CURMAN->db->prefix_table(CURTABLE) . " cur ON cur.id = curcrs.curriculumid
                     LEFT JOIN " . $CURMAN->db->prefix_table(CURASSTABLE) . " curass ON curass.curriculumid = cur.id ";
        $where    = "usr.local = '{$this->loc}' ";
/*
        $where    = "usr.local = '{$this->loc}' AND
                     stu.completestatusid != '" . STUSTATUS_NOTCOMPLETE . "' ";
*/

        if (!empty($this->search)) {
            $search = trim($this->search);
            $where .= (!empty($where) ? ' AND ' : '') . "(($FULLNAME $LIKE '%{$this->search}%') " .
                      " OR (usr.idnumber $LIKE '%{$this->search}%')) ";
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
            foreach ($this->data as $di => $datum) {
                $timenow = time();
                if (!empty($datum->frequency)) {
                    switch ($datum->timeperiod) {
                        case 'year':
                            //$datum->dnextdue = $timenow + 365 * 24 * 60 * 60 * $datum->frequency;
                            $datum->dnextdue = cm_timedelta($timenow, $datum->frequency);
                            break;

                        case 'month':
                            //$datum->dnextdue = $timenow + 30 * 24 * 60 * 60 * $datum->frequency;
                            $datum->dnextdue = cm_timedelta($timenow, 0, $datum->frequency);
                            break;

                        case 'week':
                            //$datum->dnextdue = $timenow + 24 * 60 * 60 * $datum->frequency;
                            $datum->dnextdue = cm_timedelta($timenow, 0, 0, $datum->frequency);
                            break;

                        case 'day':
                            //$datum->dnextdue = $timenow + 60 * 60 * $datum->frequency;
                            $datum->dnextdue = cm_timedelta($timenow, 0, 0, 0, $datum->frequency);
                            break;

                        default:
                            $datum->dnextdue = 0;
                            break;
                    }

                    $datum->nextdue = !empty($datum->dnextdue) ? date('M j, Y', $datum->dnextdue) : '-';

                } else {
                    $datum->nextdue = '-';
                }

            /// Remove any users here who do not have a course coming up due in 30 days.
                if (empty($datum->dnextdue) || (($datum->dnextdue - $timenow) > 30 * 24 * 60 * 60)) {
                    unset($this->data[$di]);
                    continue;
                }


                if (!empty($datum->datecomplete)) {
                    $datum->datecomplete = !empty($datum->datecomplete) ?
                                           date('M j, Y', $datum->datecomplete) : '-';
                } else {
                    $datum->datecomplete = '-';
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
     * @uses $CURMAN
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

        global $CURMAN;

        $this->loc      = cm_get_param('loc', '');
        $this->baseurl .= '&amp;loc=' . $this->loc;
        $this->set_title('Recurrent Course Report for ' . $this->loc);

        if (empty($download)) {
            $output = '';

            $bc = '<span class="breadcrumb"><a href="index.php?s=usr&amp;section=users">' . get_string('user_management', 'block_curr_admin') . '</a> ' .
                  '&raquo; ' . $this->title . '</span>';

            $output .= cm_print_heading_block($bc, '', true);
            $output .= '<br />' . "\n";
        }

        $this->add_column('idnumber', get_string('class_id', 'block_curr_admin'), 'left', true);
        $this->add_column('username', get_string('student_name', 'block_curr_admin'), 'left', true);
        $this->add_column('classname', get_string('class_name', 'block_curr_admin'), 'left', true);
        $this->add_column('datecomplete', get_string('completed_label', 'block_curr_admin'), 'left', true);
        $this->add_column('nextdue', get_string('next_due', 'block_curr_admin'), 'left', true);

        $this->set_default_sort('username', 'ASC');

        $this->sort    = !empty($sort) ? $sort : $this->defsort;
        $this->dir     = !empty($dir) ? $dir : $this->defdir;
        $this->page    = 0;
        $this->perpage = 9999;
        $this->search  = $search;
        $this->alpha   = $alpha;

        $this->get_data(!empty($download));

        if (empty($download)) {
            if (!empty($this->data)) {
                $output .= $this->print_download_menu() . '<br />';
            }

            $usercount = $CURMAN->db->count_records(USRTABLE, 'local', $this->loc);

            $output .= '<fieldset>' . "\n";
            $output .= '<legend>' . get_string('station_location_info', 'block_curr_admin') . '</legend>';
            $output .= '<b>' . get_string('location', 'block_curr_admin') . ':</b> ' . $this->loc . '<br />';
            $output .= '<b>' . get_string('total_users_at_location', 'block_curr_admin') . ':</b> ' . $usercount . '<br />';
            $output .= '</fieldset><br />';

            if (!empty($this->data)) {
                $output .= $this->display();
            } else {
                $output .= '<h2>' . get_string('no_users_with_upcoming_renew', 'block_curr_admin') . '</h2>';
            }

            echo $output;
        } else {
            $this->download($download);
        }
    }
}

?>
