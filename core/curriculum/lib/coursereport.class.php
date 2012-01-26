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


class coursereport extends report {

    /**
     * Contructor.
     *
     * @param string $id An identifier for this table (optional).
     * @retrn none
     */
    function coursereport($id = '') {
        parent::report($id);

        $this->type        = 'course';
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

        $LIKE      = $CURMAN->db->sql_compare();
        $SFULLNAME = sql_concat('susr.firstname', "' '", 'susr.lastname');

        $cselect = 'SELECT COUNT(stu.id) ';
        $select  = 'SELECT DISTINCT(stu.id), stu.classid as classid, stu.userid as userid, '.
                   'crs.name as coursename, susr.idnumber as student, susr.firstname, '.
                   'stu.completestatusid as completestatus, '.
                   'cls.enddate as clsend, crs.syllabus as syllabus ';
        $tables  = 'FROM ' . $CURMAN->db->prefix_table(USRTABLE) . ' susr '.
                   'INNER JOIN ' . $CURMAN->db->prefix_table(STUTABLE) . ' stu ON stu.userid = susr.id '.
                   'INNER JOIN ' . $CURMAN->db->prefix_table(CLSTABLE) . ' cls ON cls.id = stu.classid '.
                   'LEFT JOIN ' . $CURMAN->db->prefix_table(CRSTABLE) . ' crs ON crs.id = cls.courseid ';
        $where   = '';

        if (!empty($this->searchu)) {
            $search = trim($this->searchu);
            $where .= (!empty($where) ? ' AND ' : '') . " (susr.idnumber $LIKE '%$search%') ";
        }

        if (!empty($this->searchc)) {
            $search = trim($this->searchc);
            $where .= (!empty($where) ? ' AND ' : '') . "(crs.name $LIKE '%$search%') ";
        }

        if (!empty($this->searchl)) {
            $search = trim($this->searchl);
            $where .= (!empty($where) ? ' AND ' : '') . "(susr.local $LIKE '%$search%') ";
        }

        if ($this->alpha) {
            $where .= (!empty($where) ? ' AND ' : '') . "(crs.name $LIKE '{$this->alpha}%') ";
        }

        if (!empty($where)) {
            $where = 'WHERE ' . $where . ' ';
        }

        if (!empty($this->sort)) {
            $sort = 'ORDER BY ' . $this->sort . ' ' . $this->dir . ' ';
        }

//        if (!empty($this->perpage)) {
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
        $sql = $select . $tables . $where . $sort . $limit;
//print_object($sql); die;
        $this->data = $CURMAN->db->get_records_sql($sql);

    /// Add non-DB info to the records for display.
        if (!empty($this->data)) {
            foreach ($this->data as $di => $datum) {
                switch ($datum->completestatus) {
                    case STUSTATUS_FAILED:
                        if (!$download) {
                            $datum->completestatus = '<span style="color: red;">' . get_string('failed', 'block_curr_admin') . '</span>';
                        } else {
                            $datum->completestatus = get_string('failed', 'block_curr_admin');
                        }
                        break;

                    case STUSTATUS_PASSED:
                        if (!$download) {
                            $datum->completestatus = '<span style="color: green;">' . get_string('passed', 'block_curr_admin') . '</span>';
                        } else {
                            $datum->completestatus = get_string('passed', 'block_curr_admin');
                        }
                        break;

                    case STUSTATUS_NOTCOMPLETE:
                    default:
                        $timenow = time();
                        if (!empty($datum->clsend) && ($datum->clsend > $timenow) &&
                            (($datum->clsend - $timenow) < (90 * 24 * 60 * 60))) {
                            /// If the course is due within 90 days, colour code it accordingly.
                                if (!$download) {
                                    $datum->completestatus = '<span style="color: yellow;">' . get_string('current', 'block_curr_admin') . '</span>';
                                } else {
                                    $datum->completestatus = get_string('current', 'block_curr_admin');
                                }

                        } else if (!empty($datum->clsend) && ($datum->clsend < $timenow)) {
                        /// If the course is overdue, then display by how many days.
                            $timedelta = $timenow - $datum->clsend;
                            $timeday   = 24 * 60 * 60; // Number of seconds in a day.

                            if ($timedelta < $timeday) {
                                if (!$download) {
                                    $datum->completestatus = '<span style="colur: red;">' . get_string('overdue_zero', 'block_curr_admin') . '</span>';
                                } else {
                                    $datum->completestatus = get_string('overdue_zero', 'block_curr_admin');
                                }
                            } else {
                                $daysover = floor($timedelta / $timeday);

                                if (!$download) {
                                    $datum->completestatus = '<span style="color: red;">' . get_string('overdue', 'block_curr_admin') . $daysover .
                                                             ($daysover > 1 ? get_string('duration_days', 'block_curr_admin') : get_string('duration_day', 'block_curr_admin')) . '</span>';
                                } else {
                                    $datum->completestatus = 'Overdue: ' . $daysover .
                                                             ($daysover > 1 ? get_string('duration_days', 'block_curr_admin') : get_string('duration_day', 'block_curr_admin'));
                                }
                            }

                        } else {
                            if (!$download) {
                                $datum->completestatus = '<span style="color: green;">' . get_string('current', 'block_curr_admin') . '</span>';
                            } else {
                                $datum->completestatus = get_string('current', 'block_curr_admin');
                            }
                        }

                        break;
                }

            /// Get any existing frequency and timeperiod values for this class.
                $sql = "SELECT curcrs.frequency, curcrs.timeperiod
                        FROM " . $CURMAN->db->prefix_table(CLSTABLE) . " cls
                        INNER JOIN " . $CURMAN->db->prefix_table(CRSTABLE) . " crs ON crs.id = cls.courseid
                        INNER JOIN " . $CURMAN->db->prefix_table(CURCRSTABLE) . " curcrs ON curcrs.courseid = crs.id
                        INNER JOIN " . $CURMAN->db->prefix_table(CURASSTABLE) . " curass ON curass.curriculumid = curcrs.curriculumid
                        WHERE cls.id = '{$datum->classid}'
                        AND curass.userid = '$datum->userid'";

                if ($rec = $CURMAN->db->get_record_sql($sql)) {
                    $datum->frequency  = $rec->frequency;
                    $datum->timeperiod = $rec->timeperiod;
                } else {
                    $datum->frequency  = 0;
                    $datum->timeperiod = 0;
                }

            /// Get recurrant training info.
                if ($attendance = cm_get_attendance($datum->classid, $datum->userid)) {
                    $rtstr = '';
                    if (!empty($attendance->timeend)) {
                        $rtstr .= 'Last taken: ' . date('M j, Y', $attendance->timeend);

                        if (!empty($datum->frequency)) {
                            $timenext = 0;

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
                            }

                            if (!empty($timenext)) {
                                $timenext += $attendance->timeend;

                                if (!$download) {
                                    $rtstr .= '<br /><br />'. get_string('next_due', 'block_curr_admin') . date('M j, Y', $timenext);
                                } else {
                                    $rtstr .= "\n\n" . get_string('next_due', 'block_curr_admin') . date('M j, Y', $timenext);
                                }
                            }
                        }
                    }

                    $datum->recurrenttraining = $rtstr;
                } else {
                    $datum->recurrenttraining = '';
                }

            /// Format class starting and ending dates.
                if (!empty($datum->clsstart)) {
                    $datum->clsstart = date('M j, Y', $datum->clsstart);
                } else {
                    $datum->clsstart = '-';
                }

                if (!empty($datum->clsend)) {
                    $datum->clsend = date('M j, Y', $datum->clsend);
                } else {
                    $datum->clsend = '-';
                }

                $datum->syllabus = '<img src="pix/instructor.gif" alt="info" title="' . $datum->syllabus . '" />';

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

        $this->set_title(get_string('coursereport', 'block_curr_admin'));

        if (empty($download)) {
            $output = '';

            $bc = '<span class="breadcrumb"><a href="index.php?s=rep&amp;section=rept">' . get_string('reports', 'block_curr_admin') . '</a> ' .
                  '&raquo; ' . $this->title . '</span>';

            $output .= cm_print_heading_block($bc, '', true);
            $output .= '<br />' . "\n";
        }

        $this->add_column('student', get_string('student', 'block_curr_admin'), 'left', true);
        $this->add_column('coursename', get_string('course', 'block_curr_admin'), 'left', true);
        $this->add_column('completestatus', get_string('completion_status', 'block_curr_admin'), 'left');
        $this->add_column('clsend', get_string('class_end', 'block_curr_admin'), 'left', true, true);
        $this->add_column('recurrenttraining', get_string('recruitment_training_info', 'block_curr_admin'), 'left', false, true);

//        if (empty($download)) {
//            $this->add_column('syllabus', 'Syllabus', 'left');
//        }

        $this->set_default_sort('coursename', 'ASC');

        $this->sort    = (!empty($sort) ? $sort : $this->defsort);
        $this->dir     = (!empty($dir) ? $dir : $this->defdir);
        $this->page    = $page;
        $this->perpage = empty($download) ? $perpage : 0;

        /// Check if we need to build a search...
        $this->searchu = trim(cm_get_param('searchu', ''));
        $this->searchc = trim(cm_get_param('searchc', ''));
        $this->searchl = trim(cm_get_param('searchl', ''));
        $this->search = '';

        $this->alpha   = $alpha;

        $this->get_data(!empty($download));

        if (empty($download)) {
            $searchstring = !empty($this->searchu) ? '&amp;searchu='.urlencode(stripslashes($this->searchu)) : '';
            $searchstring .= !empty($this->searchc) ? '&amp;searchc='.urlencode(stripslashes($this->searchc)) : '';
            $searchstring .= !empty($this->searchl) ? '&amp;searchl='.urlencode(stripslashes($this->searchl)) : '';
            $this->set_baseurl($this->baseurl.$searchstring);
            $output .= $this->print_download_menu();
            $output .= $this->print_header();
            $output .= $this->display();
            $output .= $this->print_footer();

            echo $output;
        } else {
            $this->download($download);
        }
    }

    /**
     * Print the initial, paging, and search headers for the table.
     *
     * @param none
     * @return string HTML output for display.
     */
    function print_header() {
        $output = '';

        $alphabet = explode(',', get_string('alphabet'));
        $strall   = get_string('all');

    /// Bar of first initials
        $output .= "<p style=\"text-align:center\">";
        $output .= 'Name'." : ";
        if ($this->alpha) {
            $output .= " <a href=\"{$this->baseurl}&amp;sort=name&amp;dir=ASC&amp;perpage=" .
                       "{$this->perpage}\">$strall</a> ";
        } else {
            $output .= " <b>$strall</b> ";
        }
        foreach ($alphabet as $letter) {
            if ($letter == $this->alpha) {
                $output .= " <b>$letter</b> ";
            } else {
                $output .= " <a href=\"{$this->baseurl}&amp;sort=name&amp;dir=ASC&amp;perpage=" .
                           "{$this->perpage}&amp;alpha=$letter\">$letter</a> ";
            }
        }
        $output .= "</p>";

        $searchstring = !empty($this->searchu) ? '&amp;searchu='.urlencode(stripslashes($this->searchu)) : '';
        $searchstring .= !empty($this->searchc) ? '&amp;searchc='.urlencode(stripslashes($this->searchc)) : '';
        $searchstring .= !empty($this->searchl) ? '&amp;searchl='.urlencode(stripslashes($this->searchl)) : '';
        $output .= print_paging_bar($this->numrecs, $this->page, $this->perpage,
                                    "{$this->baseurl}&amp;sort={$this->sort}&amp;dir={$this->dir}&amp;" .
                                    "perpage={$this->perpage}&amp;alpha={$this->alpha}$searchstring&amp;",
                                    'page', false, true);

        $output .= '<table class="searchbox" style="margin-left:auto;margin-right:auto" cellpadding="10"><tr><td>';
        $output .= '<form action="index.php" method="get"><fieldset class="invisiblefieldset">';

    /// Print out the necessary hidden form vars.
        $parts = explode('?', $this->baseurl);
        if (count($parts) == 2 && strlen($parts[1])) {
            $args = explode('&amp;', $parts[1]);

            if (count($args) === 0) {
                $args = exploe('&amp;', $parts[1]);
            }

            if (!empty($args)) {
                foreach ($args as $arg) {
                    $vals = explode('=', $arg);

                    if (!empty($vals[1]) && $vals[1] != 'search') {
                        $output .= '<input type="hidden" name="' . $vals[0] .
                                   '" value="' . $vals[1] . '" />';
                    }
                }
            }
        }

        $output .= '<span style="border: 1px solid #777777; font-size: 80%; padding: 0 5px 0 5px;">' . get_string('search_users_for', 'block_curr_admin') . '</span>';
        $output .= ' <input type="text" name="searchu" value="' . s($this->searchu, true) . '" size="20" /><br />';
//        $output .= '<input type="submit" value="Search Users" /><br />';
        $output .= '<span style="border: 1px solid #777777; font-size: 80%; padding: 0 5px 0 5px;">Search Courses For</span>';
        $output .= ' <input type="text" name="searchc" value="' . s($this->searchc, true) . '" size="20" /><br />';
//        $output .= '<input type="submit" value="Search Courses" /><br />';
        $output .= '<span style="border: 1px solid #777777; font-size: 80%; padding: 0 5px 0 5px;">Search Locations For</span>';
        $output .= ' <input type="text" name="searchl" value="' . s($this->searchl, true) . '" size="20" /><br />';
        $output .= '<input type="submit" value="' . get_string('search', 'block_curr_admin') . '" /> ';
        if (!empty($this->searchu) || !empty($this->searchc) || !empty($this->searchl)) {
            $output .= '<input type="button" onclick="document.location=\'' . $this->baseurl .
                 '&amp;sort=' . $this->sort . '&amp;dir=' . $this->dir . '&amp;perpage=' .
                 $this->perpage . '\'"value="Show all" />';
        }
        $output .= '</fieldset></form>';
        $output .= '</td></tr></table>';

        return $output;
    }
}

?>
