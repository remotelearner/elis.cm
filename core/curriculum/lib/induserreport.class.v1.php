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


class induserreport extends report {

    var $usrid;
    var $hideins;

    /**
     * Contructor.
     *
     * @param string $id An identifier for this table (optional).
     * @retrn none
     */
    function induserreport($id = '') {
        parent::report($id);

        $this->usrid       = 0;
        $this->hideins     = false;
        $this->type        = 'induser';
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
        $select  = "SELECT crs.id as idx,
                           cls.idnumber as idnumber,
                           cls.id as classid,
                           crs.name as coursename,
                           crs.id as courseid,
                           stu.completetime as datecomplete,
                           stu.completestatusid as completestatus,
                           stu.grade as classgrade,
                           stu.userid as userid,
                           cur.name as curriculumname,
                           curcrs.frequency as frequency,
                           curcrs.timeperiod as timeperiod ";
        $tables  = "FROM " . $CURMAN->db->prefix_table(CURASSTABLE) . " curass
                    INNER JOIN " . $CURMAN->db->prefix_table(CURTABLE) . " cur ON cur.id = curass.curriculumid
                    INNER JOIN " . $CURMAN->db->prefix_table(CURCRSTABLE) . " curcrs ON curcrs.curriculumid = cur.id
                    INNER JOIN " . $CURMAN->db->prefix_table(CRSTABLE) . " crs ON crs.id = curcrs.courseid
                    LEFT  JOIN " . $CURMAN->db->prefix_table(CLSTABLE) . " cls ON cls.courseid = crs.id
                    INNER JOIN " . $CURMAN->db->prefix_table(STUTABLE) . " stu ON stu.classid = cls.id AND stu.userid = {$this->usrid}
                   ";

        $where   = "WHERE curass.userid = '{$this->usrid}' ";

    /// Count the total number of results.
        $sql           = $cselect . $tables . $where;
        $this->numrecs = $CURMAN->db->count_records_sql($sql);

    /// Get the current 'page' of results.
        $sql        = $select . $tables . $where;
        $this->data = $CURMAN->db->get_records_sql($sql);

    /// Add non-DB info to the records for display.
        if (!empty($this->data)) {
            $this->_maxexams = 0;
            $cids = '';
            foreach ($this->data as $di => $datum) {

                $cids .= (empty($cids) ? '(' :  ',') . $datum->courseid;

                if (!is_null($datum->classid) && !is_null($datum->userid)) {
                    /// Get any course element id's.
                    $select = 'SELECT cc.id, cc.courseid, cc.idnumber as elementname, ccg.id as ccgid,'.
                                'ccg.grade as grade,ccg.timegraded as timegraded '.
                              'FROM '.$CURMAN->db->prefix_table(CRSCOMPTABLE).' cc '.
                              'LEFT JOIN '.$CURMAN->db->prefix_table(CLSGRTABLE).' ccg '.
                              'ON ccg.classid = '.$datum->classid.' AND ccg.userid = '.$datum->userid.' '.
                              'AND ccg.completionid = cc.id '.
                              'WHERE cc.courseid = '.$datum->courseid.' '.
                              'ORDER BY id ASC';
                } else {
                    /// Get any course element id's.
                    $select = 'SELECT cc.id, cc.courseid, cc.idnumber as elementname '.
                              'FROM '.$CURMAN->db->prefix_table(CRSCOMPTABLE).' cc '.
                              'WHERE cc.courseid = '.$datum->courseid.' '.
                              'ORDER BY id ASC';
                }
                if (!($ccs = get_records_sql($select))) {
                    $ccs = array();
                }

                $this->_maxexams = MAX(count($ccs), $this->_maxexams);
                $i = 1;
                foreach ($ccs as $cc) {
                    $datum->{'ccid'.$i} = $cc->id;
                    $datum->{'ccelementname'.$i} = $cc->elementname;
                    $datum->{'ccgid'.$i} = isset($cc->ccgid) ? $cc->ccgid : '';
                    $datum->{'ccgrade'.$i} = isset($cc->grade) ? $cc->grade : '';
                    $datum->{'cctimegraded'.$i} =
                        isset($cc->timegraded) ?
                        (!empty($cc->timegraded) ? date('M j, Y', $cc->timegraded) : '-') : '';
                    $i++;
                }

                if (!empty($datum->datecomplete) && !empty($datum->frequency)) {
                    switch ($datum->timeperiod) {
                        case 'year':
                            $datum->dnextdue = cm_timedelta($datum->datecomplete, $datum->frequency);
                            break;

                        case 'month':
                            $datum->dnextdue = cm_timedelta($datum->datecomplete, 0, $datum->frequency);
                            break;

                        case 'week':
                            $datum->dnextdue = cm_timedelta($datum->datecomplete, 0, 0, $datum->frequency);
                            break;

                        case 'day':
                            $datum->dnextdue = cm_timedelta($datum->datecomplete, 0, 0, 0, $datum->frequency);
                            break;

                        default:
                            $datum->dnextdue = 0;
                            break;
                    }

                    $datum->nextdue = !empty($datum->dnextdue) ? date('M j, Y', $datum->dnextdue) : '-';

                } else {
                    $datum->nextdue = '-';
                }

                if (!empty($datum->datecomplete)) {
                    $datum->datecomplete = !empty($datum->datecomplete) ?
                                           date('M j, Y', $datum->datecomplete) : '-';
                } else {
                    $datum->datecomplete = '-';
                }

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
                            $datum->completestatus = '<span style="color: green;">' . get_string('complete', 'block_curr_admin') . '</span>';
                        } else {
                            $datum->completestatus = get_string('complete', 'block_curr_admin');
                        }
                        break;

                    case STUSTATUS_NOTCOMPLETE:
                    default:
                        $timenow = time();
                        if (!empty($datum->clsend) && ($datum->clsend > $timenow) &&
                            (($datum->clsend - $timenow) < (90 * 24 * 60 * 60))) {
                            /// If the course is due within 90 days, colour code it accordingly.
                                if (!$download) {
                                    $datum->completestatus = '<span style="color: red;">' . get_string('incomplete', 'block_curr_admin') . '</span>';
                                } else {
                                    $datum->completestatus = get_string('incomplete', 'block_curr_admin');
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
                                    $datum->completestatus = '<span style="color: red;">' . get_string('overdue', 'block_curr_admin') . '' . $daysover .
                                                             ($daysover > 1 ? get_string('duration_days', 'block_curr_admin') : get_string('duration_day', 'block_curr_admin')) . '</span>';
                                } else {
                                    $datum->completestatus = get_string('overdue', 'block_curr_admin') . $daysover .
                                                             ($daysover > 1 ? get_string('duration_days', 'block_curr_admin') : get_string('duration_day', 'block_curr_admin'));
                                }
                            }

                        } else {
                            if (!$download) {
                                $datum->completestatus = '<span style="color: red;">' . get_string('incomplete', 'block_curr_admin') . '</span>';
                            } else {
                                $datum->completestatus = get_string('incomplete', 'block_curr_admin');
                            }
                        }

                        break;
                }

                $this->data[$di] = $datum;
            }
            $cids .= (!empty($cids) ? ')' :  '');
        }

        if (!empty($cids)) {
            $select  = "SELECT crs.id as courseid,
                               crs.name as coursename,
                               cur.name as curriculumname,
                               curcrs.frequency as frequency,
                               curcrs.timeperiod as timeperiod ";
            $tables  = "FROM " . $CURMAN->db->prefix_table(CURASSTABLE) . " curass
                        INNER JOIN " . $CURMAN->db->prefix_table(CURTABLE) . " cur ON cur.id = curass.curriculumid
                        INNER JOIN " . $CURMAN->db->prefix_table(CURCRSTABLE) . " curcrs ON curcrs.curriculumid = cur.id
                        INNER JOIN " . $CURMAN->db->prefix_table(CRSTABLE) . " crs ON crs.id = curcrs.courseid
                       ";

            $where   = "WHERE curass.userid = '{$this->usrid}' AND crs.id NOT IN $cids ";

        /// Get the current 'page' of results.
            $sql        = $select . $tables . $where;
            $this->data2 = $CURMAN->db->get_records_sql($sql);

        }
//        $select  = "SELECT crs.id as courseid,
//                           crs.name as coursename,
//                           cls.idnumber as idnumber,
//                           cls.id as classid,
//                           stu.completetime as datecomplete,
//                           stu.completestatusid as completestatus,
//                           stu.grade as classgrade,
//                           stu.userid as userid
//                   ";
//        $tables  = "FROM " . $CURMAN->db->prefix_table(STUTABLE) . " stu
//                    INNER JOIN " . $CURMAN->db->prefix_table(CLSTABLE) . " cls ON cls.id = stu.classid
//                    INNER JOIN " . $CURMAN->db->prefix_table(CRSTABLE) . " crs ON crs.id = cls.courseid
//                   ";
//
//        $where   = "WHERE stu.userid = '{$this->usrid}' ";
//        if (!empty($cids)) {
//            $where   .= "AND crs.id NOT IN $cids ";
//        }
//
//    /// Get the current 'page' of results.
//        $sql        = $select . $tables . $where;
//        $this->data3 = $CURMAN->db->get_records_sql($sql);
//print_object($sql);
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

        $this->usrid    = cm_get_param('user', 0);
//        $this->hideins  = cm_get_param('hideins', false);
        $user           = new user($this->usrid);
        $this->baseurl .= '&amp;user=' . $user->id . '&amp;hideins=' . $this->hideins;
        $this->set_title('Individual User Report for ' . cm_fullname($user));

        if (empty($download)) {
            $output = '';

            $bc = '<span class="breadcrumb"><a href="index.php?s=usr&amp;section=users&amp;search=' .
                  urlencode(cm_fullname($user)) . '">User Management</a> &raquo; ' .
                  $this->title . '</span>';

            $output .= cm_print_heading_block($bc, '', true);
            $output .= '<br />' . "\n";
        }

        $this->get_data(!empty($download));

        $this->add_column('idnumber', 'ID', 'left', false);
        $this->add_column('coursename', 'Name', 'left', false);

        for ($i=1; $i<=$this->_maxexams; $i++) {
            $this->add_column('ccgrade'.$i, 'Exam '.$i, 'left', false);
            $this->add_column('cctimegraded'.$i, 'Date '.$i, 'left', false);
        }

        $this->add_column('classgrade', 'Class Grade', 'left', false);
        $this->add_column('datecomplete', 'Completed', 'left', false);
        $this->add_column('completestatus', 'Status', 'left', false);
//        $this->add_column('nextdue', 'Next Due', 'left', true);
//        $this->add_column('insid', 'Instructor ID', 'left', true);
/*
        if (!$this->hideins) {
            $this->add_column('insname', 'Instructor Name', 'left', true);
        }
*/
        $this->set_default_sort('coursename', 'ASC');

        $this->sort    = !empty($sort) ? $sort : $this->defsort;
        $this->dir     = !empty($dir) ? $dir : $this->defdir;
        $this->page    = 0;
        $this->perpage = 9999;
        $this->search  = $search;
        $this->alpha   = $alpha;

        if (empty($download)) {
            if (!empty($this->data)) {
                $output .= $this->print_download_menu() . '<br />';
            }

            $output .= '<fieldset>' . "\n";
/*
            $output .= '<form action="index.php" method="post">';
            $output .= '<input type="hidden" name="s" value="rep" />';
            $output .= '<input type="hidden" name="section" value="rept" />';
            $output .= '<input type="hidden" name="type" value="induser" />';
            $output .= '<input type="hidden" name="sort" value="' . $this->sort . '" />';
            $output .= '<input type="hidden" name="dir" value="' . $this->dir . '" />';
            $output .= '<input type="hidden" name="user" value="' . $this->usrid . '" />';
            $output .= 'Hide instructor name <input type="checkbox" name="hideins" value="1" ' .
                       (!empty($this->hideins) ? ' checked' : '') . ' /> ';
            $output .= '<input type="submit" value="Update Display" />';
            $output .= '</form><br />';
*/
            $output .= '<legend>' . get_string('user_information', 'block_curr_admin') . '</legend>';
            $output .= '<b>' . get_string('user_id', 'block_curr_admin') . ':</b> ' . $user->idnumber . '<br />';
            $output .= '<b>' . get_string('firstname', 'block_curr_admin') . ':</b> ' . $user->firstname . '<br />';
            $output .= '<b>' . get_string('lastname', 'block_curr_admin') . ':</b> ' . $user->lastname . '<br />';
            $output .= '</fieldset><br />';

            if (!empty($this->data)) {
                $datum = reset($this->data);
                $output .= '<strong>'.$datum->curriculumname.' - ' . get_string('enrolled_classes', 'block_curr_admin') . '</strong>';
                $output .= $this->display();
            } else {
                $output .= '<h2>' . get_string('no_classes_completed', 'block_curr_admin') . '</h2>';
            }

            if (isset($this->data2)) {
                unset($this->headers);
                unset($this->align);
                unset($this->sortable);
                unset($this->wrap);

                $output .= '<br />' . get_string('courses_not_in', 'block_curr_admin') . ': ';
                foreach ($this->data2 as $datum) {
                    $output .= s($datum->coursename).', ';
                }
            }

            echo $output;

        } else {
            $this->download($download);
        }
    }
}

?>
