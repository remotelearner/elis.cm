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
        $this->title       = 'User Report';
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
        if ($CURMAN->db->_dbconnection->databaseType == 'postgres7') {
            $FULLNAME = 'usr.firstname || \' \' || COALESCE(usr.mi, \'\') || \' \' || usr.lastname';
        } else {
            $FULLNAME = 'CONCAT(usr.firstname,\' \',IFNULL(usr.mi, \'\'),\' \',usr.lastname)';
        }

        $select  = "SELECT cls.id as classid,
                           cls.idnumber as classidnumber,
                           crs.id as courseid,
                           crs.idnumber as courseidnumber,
                           crs.name as coursename,
                           crs.credits as credits,
                           stu.completetime as datecomplete,
                           stu.completestatusid as completestatus,
                           stu.grade as classgrade,
                           stu.userid as userid
                   ";

        $tables  = "FROM " . $CURMAN->db->prefix_table(STUTABLE) . " stu
                    INNER JOIN " . $CURMAN->db->prefix_table(CLSTABLE) . " cls ON cls.id = stu.classid
                    INNER JOIN " . $CURMAN->db->prefix_table(CRSTABLE) . " crs ON crs.id = cls.courseid
                   ";

        $where   = "WHERE stu.userid = '{$this->usrid}' ";

    /// Get the current 'page' of results.
        $sql   = $select . $tables . $where;
        $data2 = $CURMAN->db->get_records_sql($sql);

        $select = "SELECT curcrs.courseid,
                          cur.id as curriculumid,
                          cur.name as curriculumname,
                          crs.idnumber as courseidnumber,
                          crs.credits as credits,
                          crs.name as coursename
                  ";
        $tables = "FROM " . $CURMAN->db->prefix_table(CURCRSTABLE) . " curcrs
                   INNER JOIN " . $CURMAN->db->prefix_table(CRSTABLE) . " crs ON crs.id = curcrs.courseid
                   INNER JOIN " . $CURMAN->db->prefix_table(CURTABLE) . " cur ON cur.id = curcrs.curriculumid
                   INNER JOIN " . $CURMAN->db->prefix_table(CURASSTABLE) . " curass ON curass.curriculumid = cur.id
                  ";
        $where   = "WHERE curass.userid = '{$this->usrid}' ";
        $sort    = "ORDER BY curriculumname ASC, position ASC ";

        $sql     = $select . $tables . $where . $sort;
        $data3   = $CURMAN->db->get_records_sql($sql);

    /// Add non-DB info to the records for display.
        if (!empty($data2)) {
            $this->_maxexams = 0;
            foreach ($data2 as $di => $datum) {

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
                        $datum->datecomplete = '-';
                        $datum->credits = '';
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
                                    $datum->completestatus = '<span style="color: red;">' . get_string('overdue', 'block_curr_admin') . $daysover .
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

                $data2[$di] = $datum;
            }
        }

    /// Organize the data into curricula, and non-curricula.
    /// This will allow for a user who is in more than one class of the same course, and courses that
    /// are in more than one curricula.
        $this->rawdata = array();
        if (!empty($data3)) {
            foreach ($data3 as $di => $datum) {
                $found = false;
                if (!isset($this->rawdata[$datum->curriculumid])) {
                    $this->rawdata[$datum->curriculumid] = new Object();
                    $this->rawdata[$datum->curriculumid]->curriculumname = $datum->curriculumname;
                    $this->rawdata[$datum->curriculumid]->data = array();
                }
                if (!empty($data2)) {
                    foreach ($data2 as $di2 => $datum2) {
                        if ($datum2->courseid == $di) {
                            $this->rawdata[$datum->curriculumid]->data[] = clone($datum2);
                            $data2[$di2]->incur = true;
                            $found = true;
                        }
                    }
                }

                if (!$found) {
                    $rec = new Object();
                    $rec->classid = '';
                    $rec->classidnumber = '-none-';
                    $rec->courseid = $di;
                    $rec->courseidnumber = $datum->courseidnumber;
                    $rec->coursename = $datum->coursename;
                    $rec->completetime = '';
                    $rec->completestatusid = '';
                    $rec->classgrade = '';
                    $rec->credits = $datum->credits;
                    $rec->userid = '';
                    $this->rawdata[$datum->curriculumid]->data[] = clone($rec);
                }
            }
        }

        if (!empty($data2)) {
            foreach ($data2 as $di2 => $datum2) {
                if (!isset($datum2->incur)) {
                    if (!isset($this->rawdata[0])) {
                        $this->rawdata[0] = new Object();
                        $this->rawdata[0]->curriculumname = 'Non-curriculum courses';
                        $this->rawdata[0]->data = array();
                    }
                    $this->rawdata[0]->data[] = clone($datum2);
                }
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

        global $CFG, $USER;

        $context = get_context_instance(CONTEXT_SYSTEM, SITEID);
        $canaccessum = false;
        if (has_capability('block/curr_admin:viewreports', $context)) {
            $this->usrid = cm_get_param('user', 0);
            $canaccessum = true;
        } else if (has_capability('block/curr_admin:viewgroupreports', $context)) {
            // Verify userid
            $this->usrid = cm_get_param('user', 0);
            if (!cm_can_access_userreport($this->usrid)) {
                error("No access allowed.");
            }
            $canaccessum = false;
        } else if (has_capability('block/curr_admin:viewownreports', $context)) {
            // Make sure only this user.
            if (!($this->usrid = cm_get_crlmuserid($USER->id))) {
                error("No account found.");
            }
        } else {
            error("No access allowed.");
        }

        $user           = new user($this->usrid);
        $this->baseurl .= '&amp;user=' . $user->id . '&amp;hideins=' . $this->hideins;
        $this->set_title('Individual User Report for ' . cm_fullname($user));

        if (empty($download)) {
            $output = '';

            if ($frompage == '') {
                $frompage = 'users';
            }
            $pagename = get_string("report{$frompage}", 'block_curr_admin');


            $bc = '<span class="breadcrumb"><a href="index.php?s=rep&amp;section=rept&amp;type='.$frompage.'">'.$pagename.'</a> &raquo; ' .
                  $this->title . '</span>';

            $output .= cm_print_heading_block($bc, '', true);
            $output .= '<br />' . "\n";
        }

        $this->get_data(!empty($download));

        $this->add_column('courseidnumber', 'Course ID', 'left', false);
        $this->add_column('coursename', 'Course Name', 'left', false);
        $this->add_column('classidnumber', 'Class ID', 'left', false);

        if (!empty($this->_maxexams)) {
            for ($i=1; $i<=$this->_maxexams; $i++) {
                $this->add_column('ccgrade'.$i, 'Exam '.$i, 'left', false);
                $this->add_column('cctimegraded'.$i, 'Date '.$i, 'left', false);
            }
        }

        $this->add_column('classgrade', 'Class Grade', 'left', false);
        $this->add_column('credits', 'Credits', 'left', false);
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

        if (empty($download)) {

            $tlink = $CFG->wwwroot.'/curriculum/index.php';
            $toptions = array('s' => 'rep', 'section' => 'rept', 'type' => 'transcript', 'user' => $this->usrid);
            $tlabel = get_string('transcript', 'block_curr_admin');
            $output .= '<div class="trans-button">' . print_single_button($tlink, $toptions, $tlabel, NULL, NULL, true) . '</div>';
            if (!empty($this->rawdata)) {
                $output .= $this->print_download_menu();
            }
            $output .= '<br />';

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
            if (empty($user->origenroldate)) {
                if (empty($user->timecreated)) {
                    $origdate = get_string('unknown', 'block_curr_admin');
                } else {
                    $origdate = cm_timestamp_to_date($user->timecreated);
                }
            } else if (!($origdate = cm_timestring_to_date($user->origenroldate))) {
                $origdate = get_string('unknown', 'block_curr_admin');
            }
            $output .= '<legend>' . get_string('user_information', 'block_curr_admin') . '</legend>';

            $output .= '<div style="float: left; width: 50%;"><b>' . get_string('user_id', 'block_curr_admin') . '</b> ';
            if ($canaccessum) {
                $output .= '<a href="'.$CFG->wwwroot.'/curriculum/index.php?s=usr&userid='.$user->id.'&action=view">'.
                           $user->idnumber . '</a></div>';
            } else {
                $output .= $user->idnumber . '</div>';
            }
            $output .= '<div style="float: left; width: 50%;"><b>' . get_string('student_email', 'block_curr_admin') . ':</b> ' . $user->email . '</div><br />';
            $output .= '<div style="float: left; width: 50%;"><b>' . get_string('firstname', 'block_curr_admin') . ':</b> ' . $user->firstname . '</div>';
            $output .= '<div style="float: left; width: 50%;"><b>' . get_string('original_reg_date', 'block_curr_admin') . ':</b> ' . $origdate . '</div><br />';
            $output .= '<div style="float: left; width: 35%;"><b>' . get_string('lastname', 'block_curr_admin') . ':</b> ' . $user->lastname . '</div><br />';
            $output .= '</fieldset><br />';

            if (!empty($this->rawdata)) {
                foreach ($this->rawdata as $curid => $curlist) {
                    $output .= '<strong>'.$curlist->curriculumname.' - ' . get_string('enrolled_classes', 'block_curr_admin') . '</strong>';
                    $this->data = $curlist->data;
                    $output .= $this->display();
                    unset($this->table);
                    $output .= '<br /><br />';
                }
            } else {
                $output .= '<h2>' . get_string('no_classes_completed', 'block_curr_admin') . '</h2>';
            }

            $output .= $this->print_footer();

            echo $output;

        } else {
            $this->download($download);
        }
    }

    /**
     * Get the data needed for a downloadable version of the report (all data,
     * no paging necessary) and format it accordingly for the download file
     * type.
     *
     * NOTE: It is expected that the valid format types will be overridden in
     * an extended report class as the array is empty by default.
     *
     * @param string $format A valid format type.
     */
    function download($format) {
        global $CFG;

        $output = '';

        if (empty($this->rawdata)) {
            return $output;
        }

        $filename = !empty($this->title) ? $this->title : 'report_download';

        switch ($format) {
            case 'csv':
                $filename .= '.csv';

                header("Content-Transfer-Encoding: ascii");
                header("Content-Disposition: attachment; filename=$filename");
                header("Content-Type: text/comma-separated-values");

                $row = array();

                $row[] = 'Curriculum';
                foreach ($this->headers as $header) {
                    $row[] = $this->csv_escape_string(strip_tags($header));
                }

                echo implode(',', $row) . "\n";

            if (!empty($this->rawdata)) {
                foreach ($this->rawdata as $curid => $curlist) {
                    $output .= '<strong>'.$curlist->curriculumname.' - ' . get_string('enrolled_classes', 'block_curr_admin') . '</strong>';
                    $this->data = $curlist->data;
                    $output .= $this->display();
                    unset($this->table);
                    $output .= '<br /><br />';
                }
            } else {
                $output .= '<h2>' . get_string('no_classes_completed', 'block_curr_admin') . '</h2>';
            }
                foreach ($this->rawdata as $curid => $curlist) {
                    $first = $curlist->curriculumname;
                    foreach ($curlist->data as $datum) {
                        if (!is_object($datum)) {
                            continue;
                        }

                        $row = array();
                        $row[] = $this->csv_escape_string($first);

                        foreach ($this->headers as $id => $header) {
                            if (isset($datum->$id)) {
                                $row[] = $this->csv_escape_string($datum->$id);
                            } else {
                                $row[] = '""';
                            }
                        }

                        echo implode(',', $row) . "\n";
                    }
                }

                break;

            case 'excel':
                require_once($CFG->libdir . '/excellib.class.php');

                $filename .= '.xls';

            /// Creating a workbook
                $workbook = new MoodleExcelWorkbook('-');

            /// Sending HTTP headers
                $workbook->send($filename);

            /// Creating the first worksheet
                $sheettitle  = get_string('studentprogress', 'reportstudentprogress');
                $myxls      =& $workbook->add_worksheet($sheettitle);

            /// Format types
                $format =& $workbook->add_format();
                $format->set_bold(0);
                $formatbc =& $workbook->add_format();
                $formatbc->set_bold(1);
                $formatbc->set_align('center');
                $formatb =& $workbook->add_format();
                $formatb->set_bold(1);
                $formaty =& $workbook->add_format();
                $formaty->set_bg_color('yellow');
                $formatc =& $workbook->add_format();
                $formatc->set_align('center');
                $formatr =& $workbook->add_format();
                $formatr->set_bold(1);
                $formatr->set_color('red');
                $formatr->set_align('center');
                $formatg =& $workbook->add_format();
                $formatg->set_bold(1);
                $formatg->set_color('green');
                $formatg->set_align('center');

                $rownum = 0;
                $colnum = 0;

                $myxls->write($rownum, $colnum++, 'Curriculum', $formatbc);
                foreach ($this->headers as $header) {
                    $myxls->write($rownum, $colnum++, $header, $formatbc);
                }

                foreach ($this->rawdata as $curid => $curlist) {
                    $first = $curlist->curriculumname;
                    foreach ($curlist->data as $datum) {
                        if (!is_object($datum)) {
                            continue;
                        }

                        $rownum++;
                        $colnum = 0;

                        $myxls->write($rownum, $colnum++, $first, $format);
                        foreach ($this->headers as $id => $header) {
                            if (isset($datum->$id)) {
                                $myxls->write($rownum, $colnum++, $datum->$id, $format);
                            } else {
                                $myxls->write($rownum, $colnum++, '', $format);
                            }
                        }
                    }
                }

                $workbook->close();

                break;

            case 'pdf':
                require_once($CFG->libdir . '/fpdf/fpdf.php');

                $filename .= '.pdf';

                $newpdf = new FPDF('L', 'in', 'legal');
                $marginx = 0.75;
                $marginy = 0.75;
                $newpdf->setMargins($marginx,$marginy);
                $newpdf->SetFont('Arial', '', 9);
                $newpdf->AddPage();
                $newpdf->SetFont('Arial', '', 16);
                $newpdf->MultiCell(0, 0.2, $this->title, 0, 'C');
                $newpdf->Ln(0.2);
                $newpdf->SetFont('Arial', '', 8);
                $newpdf->SetFillColor(225, 225, 225);

                $twidth  = 0;
                $heights = array();
                $widths  = array();
                $hmap    = array();
                $rownum  = 0;

            /// PASS 1 - Calculate sizes.
                foreach ($this->headers as $id => $header) {
                    $widths[$id] = $newpdf->GetStringWidth($header) + 0.2;
                    $twidth     += $widths[$id];
                }

                $row = 0;

                foreach ($this->rawdata as $curid => $curlist) {
                    foreach ($curlist->data as $datum) {
                        if (!isset($heights[$row])) {
                            $heights[$row] = 0;
                        }

                        foreach ($this->headers as $id => $header) {
                            if (isset($datum->$id)) {
                                $width = $newpdf->GetStringWidth($datum->$id) + 0.2;

                                if ($width > $widths[$id]) {
                                    $lines = ceil($width / $widths[$id]);
                                    $widths[$id] = $width;
                                } else {
                                    $lines = 1;
                                }

                                $height = $lines * 0.2;

                                if ($height > $heights[$row]) {
                                    $heights[$row] = $height;
                                }
                            }
                        }

                        $row++;
                    }
                }


                /// Calculate the width of the table...
                $twidth = 0;
                foreach ($widths as $width) {
                    $twidth += $width;
                }

                /// Readjust the left margin according to the total width...
                $marginx = (14.0 - $twidth) / 2.0;
                $newpdf->setMargins($marginx, $marginy);

                foreach ($this->rawdata as $curid => $curlist) {
                    $newpdf->Write(0.2, $curlist->curriculumname.' - Enrolled Classes'."\n");
                    foreach ($this->headers as $id => $header) {
                        $text = str_replace(' ', "\n", $header);
                        $newpdf->Cell($widths[$id], 0.2, "$text", 1, 0, 'C', 1);
                    }

                    $newpdf->Ln();

                    $row = 0;

                    foreach ($curlist->data as $datum) {
                        if (is_array($datum) && (strtolower($datum[0]) == 'hr')) {
                            $curx = $newpdf->GetX();
                            $cury = $newpdf->GetY() + 0.1;
                            $endx = 0;
                            $endy = $cury;

                            foreach ($widths as $width) {
                                $endx += $width;
                            }

                            $newpdf->Line($curx, $cury, $endx, $endy);

                            $newpdf->SetX($curx + 0.1);

                        } else {
                            foreach ($this->headers as $id => $header) {
                                $text = '';

                                if (isset($datum->$id)) {
                                    $text = $datum->$id;
                                }

                                $newpdf->Cell($widths[$id], $heights[$row], $text, 0, 0, 'C', 0);
                            }
                        }

                        $newpdf->Ln();
                        $row++;
                    }
                    $newpdf->Write(0.2, "\n");
                }

                $newpdf->Output($filename, 'I');

                break;

            default:
                return $output;
                break;
        }
    }
}

?>
