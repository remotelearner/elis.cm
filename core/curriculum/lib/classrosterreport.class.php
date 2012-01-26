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


class classrosterreport extends report {

    var $clsid;
    var $hideins;
    var $hidestu;
    var $hidesyl;

    /**
     * Contructor.
     *
     * @param string $id An identifier for this table (optional).
     * @retrn none
     */
    function classrosterreport($id = '') {
        parent::report($id);

        $this->clsid       = 0;
        $this->hideins     = false;
        $this->hidestu     = false;
        $this->hidesyl     = false;
        $this->type        = 'classroster';
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
        global $CURMAN, $CFG;

        /// Get any course element id's.
        $courseid = get_field('crlm_class', 'courseid', 'id', $this->clsid);
        $cselect = 'SELECT COUNT(DISTINCT stu.id) ';
        $select = 'SELECT id,courseid '.
                  'FROM '.$CURMAN->db->prefix_table(CRSCOMPTABLE).' cc '.
                  'WHERE cc.courseid = '.$courseid.' '.
                  'ORDER BY id ASC';
        if (!($ccs = get_records_sql($select))) {
            $ccs = array();
        }

        $LIKE     = $CURMAN->db->sql_compare();
        $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');

        $cselect = 'SELECT COUNT(stu.id) ';
        $select  = "SELECT DISTINCT(stu.id),
                           stu.classid as classid,
                           stu.userid as userid,
                           usr.idnumber as idnumber,
                           $FULLNAME as fullname,
                           stu.completestatusid as completestatus,
                           stu.grade,
                           stu.credits,
                           stu.completetime as completetime ";
        $from    = "FROM {$CURMAN->db->prefix_table(STUTABLE)} stu ";
        $join    = "INNER JOIN {$CURMAN->db->prefix_table(USRTABLE)} usr ON stu.userid = usr.id ";

        $tables  = $from.$join;
        $where   = 'stu.classid = \'' . $this->clsid . '\' ';

        if ($this->extrasql) {
            $where .= (!empty($where) ? ' AND ' : '') . $this->extrasql . ' ';
            if (strpos($this->extrasql, 'clusterid') !== false) {
                $join .= "LEFT JOIN  {$CURMAN->db->prefix_table(CLSTUSERTABLE)} uclst ON uclst.userid = usr.id";
            }
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

    /// include the course completions in the report
        $i = 1;
        foreach ($ccs as $cc) {
            $join .= 'LEFT JOIN '.$CURMAN->db->prefix_table(CLSGRTABLE).' ccg'.$i.' '.
                     'ON ccg'.$i.'.classid = stu.classid AND ccg'.$i.'.userid = stu.userid '.
                     'AND ccg'.$i.'.completionid = '.$cc->id.' ';
            $select .= ', ccg'.$i.'.grade as grade'.$i.', ccg'.$i.'.timegraded as timegraded'.$i.' ';
            $i++;
        }
        $this->num_grade_fields = $i-1;
        $tables  = $from.$join;

    /// Get the current 'page' of results.
        $sql        = $select . $tables . $where . $sort . $limit;
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
                        $datum->completetime = 0;
                        if (!empty($datum->clsend) && ($datum->clsend > $timenow) &&
                            (($datum->clsend - $timenow) < (90 * 24 * 60 * 60))) {
                            /// If the course is due within 90 days, colour code it accordingly.
                                if (!$download) {
                                    $datum->completestatus = '<span style="color: yellow;">' . get_string('current','block_curr_admin') . '</span>';
                                } else {
                                    $datum->completestatus = get_string('current','block_curr_admin');
                                }

                        } else if (!empty($datum->clsend) && ($datum->clsend < $timenow)) {
                        /// If the course is overdue, then display by how many days.
                            $timedelta = $timenow - $datum->clsend;
                            $timeday   = 24 * 60 * 60; // Number of seconds in a day.

                            if ($timedelta < $timeday) {
                                if (!$download) {
                                    $datum->completestatus = '<span style="colur: red;">' . get_string('overdue_zero', 'block_curr_admin'). '</span>';
                                } else {
                                    $datum->completestatus = get_string('overdue_zero', 'block_curr_admin');
                                }
                            } else {
                                $daysover = floor($timedelta / $timeday);

                                if (!$download) {
                                    $datum->completestatus = '<span style="color: red;">' . get_string('overdue', 'block_curr_admin') . $daysover .
                                                             ($daysover > 1 ? get_string('duration_days', 'block_curr_admin') : get_string('duration_day', 'block_curr_admin')) . '</span>';
                                } else {
                                    $datum->completestatus = get_string('overdue', 'block_curr_admin') . $daysover .
                                                             ($daysover > 1 ? get_string('duration_days', 'block_curr_admin') : get_string('duration_day', 'block_curr_admin'));
                                }
                            }

                        } else {
                            if (!$download) {
                                $datum->completestatus = '<span style="color: green;">' . get_string('current','block_curr_admin') . '</span>';
                            } else {
                                $datum->completestatus = get_string('current','block_curr_admin');
                            }
                        }

                        break;
                }

            /// Get any existing frequency and timeperiod values for this class.
//                $sql = "SELECT curcrs.frequency, curcrs.timeperiod
//                        FROM " . $CURMAN->db->prefix_table(CLSTABLE) . " cls
//                        INNER JOIN " . $CURMAN->db->prefix_table(CRSTABLE) . " crs ON crs.id = cls.courseid
//                        INNER JOIN " . $CURMAN->db->prefix_table(CURCRSTABLE) . " curcrs ON curcrs.courseid = crs.id
//                        INNER JOIN " . $CURMAN->db->prefix_table(CURASSTABLE) . " curass ON curass.curriculumid = curcrs.curriculumid
//                        WHERE cls.id = '{$datum->classid}'
//                        AND curass.userid = '$datum->userid'";
//
//                if ($rec = $CURMAN->db->get_record_sql($sql)) {
//                    $datum->frequency  = $rec->frequency;
//                    $datum->timeperiod = $rec->timeperiod;
//                } else {
//                    $datum->frequency  = 0;
//                    $datum->timeperiod = 0;
//                }

                for ($i = 1; $i <= $this->num_grade_fields; $i++) {
                    if (!empty($datum->{'timegraded'.$i})) {
                        $datum->{'timegraded'.$i} = date('M j, Y', $datum->{'timegraded'.$i});
                    } else {
                        $datum->{'timegraded'.$i} = '-';
                    }
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
    function main($sort = '', $dir = '', $page = 0, $perpage = 20, $download = '') {

        $this->clsid   = cm_get_param('id', 0);
        $this->hideins = cm_get_param('hideins', false);
        $this->hidestu = cm_get_param('hidestu', false);
        $this->hidesyl = cm_get_param('hidesyl', false);
        $class         = new cmclass($this->clsid);

        $this->set_title('Class Roster Report for ' . $class->course->name . ' ' . $class->idnumber);
        $this->set_default_sort('fullname', 'ASC');
        $this->baseurl .= '&id=' . $this->clsid . '&hideins=' . $this->hideins .
                          '&hidestu=' . $this->hidestu . '&hidesyl=' . $this->hidesyl;

        $this->sort    = !empty($sort) ? $sort : $this->defsort;
        $this->dir     = !empty($dir) ? $dir : $this->defdir;
        $this->page    = $page;
        $this->perpage = !empty($download) ? 9999 : $perpage;

        // create the user filter form
        $this->filter   = new cm_user_filtering(null, $this->baseurl);
        $this->extrasql = $this->filter->get_sql_filter();

        $this->get_data(!empty($download));

        $this->add_column('idnumber', 'ID', 'left', true);

        if (!$this->hidestu) {
            $this->add_column('fullname', 'Student', 'left', true);
        } else if ($sort == 'fullname') {
                $sort = 'idnumber';
        }

        for ($i = 1; $i <= $this->num_grade_fields; $i++) {
            $this->add_column('timegraded'.$i, 'Completed '.$i, 'left');
            $this->add_column('grade'.$i, 'Grade '.$i, 'left');
        }

//        $this->add_column('completestatus', 'Completion Status', 'left');
        $this->add_column('completetime', 'Completion Time', 'left', true);
        $this->add_column('grade', 'Completion Grade', 'left', true);
        $this->add_column('credits', 'Credits Awarded', 'left');

        if (empty($download)) {
            $output = '';
            $bc = '<div style="float:right;">'.$this->numrecs.' users found.</div>'.
                  '<span class="breadcrumb"><a href="index.php?s=rep&amp;section=rept">Reports</a> ' .
                  '&raquo; ' . $this->title . '</span>';

            $output .= cm_print_heading_block($bc, '', true);
            $output .= '<br />' . "\n";

            $output .= $this->print_download_menu();
            $output .= $this->print_header();

            $output .= '<br /><fieldset>' . "\n";
            $output .= '<legend>Class Information</legend>';
            $output .= '<form action="index.php" method="post">';
            $output .= '<input type="hidden" name="s" value="rep" />';
            $output .= '<input type="hidden" name="type" value="classroster" />';
            $output .= '<input type="hidden" name="sort" value="' . $this->sort . '" />';
            $output .= '<input type="hidden" name="dir" value="' . $this->dir . '" />';
            $output .= '<input type="hidden" name="id" value="' . $this->clsid . '" />';
            $output .= 'Hide student name <input type="checkbox" name="hidestu" value="1" ' .
                       (!empty($this->hidestu) ? ' checked' : '') . ' /> ';
            $output .= 'Hide syllabus <input type="checkbox" name="hidesyl" value="1" ' .
                       (!empty($this->hidesyl) ? ' checked' : '') . ' /> ';
            $output .= '<input type="submit" value="Update Display" />';
            $output .= '</form>';
            $output .= '<table width="100%"><tr>';
            $output .= '<tr><td colspan="2" rowspan="1">&nbsp;</td></tr>';
            $output .= '<td align="left" width="50%"><b>Course name</b>: <i>' .
                       $class->course->name . '</i></td>';
            $output .= '<td align="left" width="50%"><b>Class code</b>: ' . '<i>' .
                       $class->idnumber . '</i></td></tr>';

            $startdate = !empty($class->startdate) ? date('M j, Y', $class->startdate) : '-';
            $enddate   = !empty($class->enddate) ? date('M j, Y', $class->enddate) : '-';

            $output .= '<tr><td align="left"><b>Start Date</b>: <i>' . $startdate . '</i></td>';
            $output .= '<td align="left"><b>End Date</b>: <i>' . $enddate . '</i></td></tr>';

//            $ins = new instructor();
//
//            if ($instructors = $ins->get_instructors($this->clsid)) {
//                $output .= '<tr><td colspan="2" rowspan="1">&nbsp;</td></tr>';
//                $output .= '<tr><th align="left" colspan="2">Instructors</th></tr>';
//
//                foreach ($instructors as $instructor) {
//                    if ($this->hideins) {
//                        $output .= '<tr><td align="left" colspan="2"><b>ID</b>: <i>' .
//                                   $instructor->idnumber . '</i></td></tr>';
//                    } else {
//                        $output .= '<tr><td><b>Instructor</b>: <i>' . cm_fullname($instructor) .
//                                   '</i></td>';
//                        $output .= '<td><b>ID</b>: <i>' . $instructor->idnumber .
//                                   '</i></td></tr>';
//                    }
//                }
//            }

            if (!$this->hidesyl && !empty($class->course->syllabus)) {
                $output .= '<tr><td colspan="2" rowspan="1">&nbsp;</td></tr>';
                $output .= '<tr><td colspan="2">';
                $output .= '<fieldset>';
                $output .= '<legend>Syllabus</legend>';
                $output .= '<i>' . $class->course->syllabus . '</i>';
                $output .= '</fieldset></td></tr>';
            }

            $output .= '</table></fieldset><br />';
            $output .= $this->display();

            echo $output;
        } else {
            $this->download($download);
        }
    }
}

?>
