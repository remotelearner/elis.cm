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


class transcriptreport extends report {

    var $usrid;
    var $hideins;

    /**
     * Contructor.
     *
     * @param string $id An identifier for this table (optional).
     * @retrn none
     */
    function transcriptreport($id = '') {
        parent::report($id);

        $this->usrid       = 0;
        $this->hideins     = false;
        $this->type        = 'induser';
        $this->title       = get_string('user_transcript', 'block_curr_admin');
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
                           stu.completestatusid as completed,
                           stu.grade as classgrade,
                           stu.userid as userid
                   ";

        $tables  = "FROM " . $CURMAN->db->prefix_table(STUTABLE) . " stu
                    INNER JOIN " . $CURMAN->db->prefix_table(CLSTABLE) . " cls ON cls.id = stu.classid
                    INNER JOIN " . $CURMAN->db->prefix_table(CRSTABLE) . " crs ON crs.id = cls.courseid
                   ";

        $where   = "WHERE stu.userid = '{$this->usrid}' AND stu.completestatusid != 0 ";
        $sort    = "ORDER BY datecomplete ASC ";

    /// Get the current 'page' of results.
        $sql         = $select . $tables . $where . $sort;
        $data2 = $CURMAN->db->get_records_sql($sql);

    /// Add non-DB info to the records for display.
        $this->_maxexams = 0;
        if (!empty($data2)) {
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
                                           cm_timestamp_to_date($datum->datecomplete) : '<span style="color: red;">' . get_string('incomplete', 'block_curr_admin') . '</span>';
                } else {
                    $datum->datecomplete = '<span style="color: red;">' . get_string('incomplete', 'block_curr_admin') . '</span>';
                }

                switch ($datum->completed) {
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
                                    $datum->completestatus = '<span style="color: red;">' . get_string('overdue', 'block_curr_admin') . ': ' . $daysover .
                                                             ($daysover > 1 ? get_string('duration_days', 'block_curr_admin') : get_string('duration_day', 'block_curr_admin')) . '</span>';
                                } else {
                                    $datum->completestatus = 'Overdue: ' . $daysover .
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

                $datum->classpercent = $datum->classgrade;
                if (empty($datum->classpercent)) {
                    $datum->classpercent = 'I';
                    $datum->gradepoints = 0;
                } else if ($datum->classpercent >= 90) {
                    $datum->classgrade = 'A';
                    $datum->gradepoints = 4;
                } else if ($datum->classpercent >= 80) {
                    $datum->classgrade = 'B';
                    $datum->gradepoints = 3;
                } else if ($datum->classpercent >= 70) {
                    $datum->classgrade = 'C';
                    $datum->gradepoints = 2;
                } else {
                    $datum->classgrade = 'I';
                    $datum->gradepoints = 0;
                }

                $data2[$di] = $datum;
            }
        }

    /// Organize the data into udergraduate and graduate courses
        $this->rawdata = array();
        if (!empty($data2)) {
            foreach ($data2 as $di2 => $datum2) {
                if ($datum2->courseidnumber[0] == 'M') {
                    $coursecat = 'grad';
                    $name = get_string('grad_courses', 'block_curr_admin');
                } else {
                    $coursecat = 'undergrad';
                    $name = get_string('undergrad_courses', 'block_curr_admin');
                }
                if (!isset($this->rawdata[$coursecat])) {
                    $this->rawdata[$coursecat] = new Object();
                    $this->rawdata[$coursecat]->curriculumname = $name;
                    $this->rawdata[$coursecat]->numcredits = 0;
                    $this->rawdata[$coursecat]->gpa = 0;
                }
                if ((int)$datum2->completed > 0) {
                    $this->rawdata[$coursecat]->numcredits += $datum2->credits;
                    $this->rawdata[$coursecat]->gpa += ($datum2->gradepoints * $datum2->credits);
                }
                $this->rawdata[$coursecat]->data[] = clone($datum2);
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
        global $CFG;

        $this->usrid    = cm_get_param('user', 0);
//        $this->hideins  = cm_get_param('hideins', false);
        $this->user     = new user($this->usrid);
        $this->baseurl .= '&amp;user=' . $this->user->id . '&amp;hideins=' . $this->hideins;
        $this->set_title(get_string('individual_report', 'block_curr_admin') . cm_fullname($this->user));

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

        $this->add_column('datecomplete', get_string('completed_label', 'block_curr_admin'), 'left', false);
        $this->add_column('courseidnumber', get_string('course_id', 'block_curr_admin'), 'left', false);
        $this->add_column('coursename', get_string('coursename', 'block_curr_admin'), 'left', false);

        for ($i=1; $i<=$this->_maxexams; $i++) {
            $this->add_column('ccgrade'.$i, get_string('exam', 'block_curr_admin').$i, 'left', false);
        }

        $this->add_column('classgrade', get_string('grade', 'block_curr_admin'), 'left', false);
        $this->add_column('credits', get_string('credits', 'block_curr_admin'), 'left', false);

        $this->set_default_sort('datecomplete', 'ASC');

        $this->sort    = !empty($sort) ? $sort : $this->defsort;
        $this->dir     = !empty($dir) ? $dir : $this->defdir;
        $this->page    = 0;
        $this->perpage = 9999;

        if (empty($download)) {
            $tlink = $CFG->wwwroot.'/curriculum/index.php';
            $toptions = array('s' => 'rep', 'section' => 'rept', 'type' => 'induser', 'user' => $this->usrid);
            $tlabel = get_string('userreport', 'block_curr_admin');
            $output .= '<div class="trans-button">' . print_single_button($tlink, $toptions, $tlabel, NULL, NULL, true) . '</div>';
            if (!empty($this->rawdata)) {
                $output .= $this->print_download_menu();
            }
            $output .= '<br />';

            $output .= '<fieldset>' . "\n";
            $output .= '<legend>' . get_string('user_information', 'block_curr_admin') . '</legend>';

            switch ($this->user->gender) {
                case 'M':
                case 'm':
                    $gender = get_string('male', 'block_curr_admin');
                break;

                case 'F':
                case 'f':
                    $gender = get_string('female', 'block_curr_admin');
                break;

                default:
                    $gender = get_string('unknown', 'block_curr_admin');
                break;

            }

            $output .= '<div class="trans-userarea">';
            $output .= '<div class="trans-leftside">';
            $output .= cm_fullname($this->user) . "<br />\n";
            $output .= $this->user->address . "<br />\n";
            if (!empty($this->user->address2)) {
                $output .= $this->user->address2 . "<br />\n";
            }
            $output .= $this->user->city;
            if (!empty($this->user->city)) {
                $output .= ', ' . $this->user->state;
            }
            $output .= ' ' . s($this->user->postalcode) . ' ' . cm_get_country($this->user->country) . "<br />\n";

            $output .= '</div>';

            $output .= '<div class="trans-rightside">';

            $output .= '<div class="trans-innerleft">Sex:</div>';
            $output .= '<div class="trans-innerright">'.$gender.'</div>';

            $output .= '<div class="trans-innerleft">Born:</div>';
            $bday = cm_timestring_to_date($this->user->birthdate);
            $output .= '<div class="trans-innerright">'.$bday.'</div>';

            $output .= '<div class="trans-innerleft">Registration Date:</div>';
            if (!empty($this->user->origenroldate)) {
                $enroldate = cm_timestring_to_date($this->user->origenroldate);
            } else if (!empty($this->user->timecreated)) {
                $enroldate = cm_timestamp_to_date($this->user->timecreated);
            } else {
                $enroldate = get_string('unknown', 'block_curr_admin');
            }
            $output .= '<div class="trans-innerright">'.$enroldate.'</div>';

            $output .= '<div class="trans-innerleft">Identification No:</div>';
            $output .= '<div class="trans-innerright">'.$this->user->idnumber.'</div>';

            $output .= '</div>';

            $output .= '</div>';

            $output .= '</fieldset><br />';

            $summary = '';
            if (!empty($this->rawdata)) {
                foreach ($this->rawdata as $curid => $curlist) {
                    $output .= '<strong>'.$curlist->curriculumname.' - ' . get_string('enrolled_classes', 'block_curr_admin') . '</strong>';
                    $this->data = $curlist->data;
                    $output .= $this->display();
                    unset($this->table);

                    if ($curlist->numcredits > 0) {
                        $gpa = sprintf('%1.2f', ((float)$curlist->gpa / (float)$curlist->numcredits));
                    } else {
                        $gpa = '0.0';
                    }
                    $summary .= "<div style=\"float:left; width: 30%;\">' . get_string('total_credits', 'block_curr_admin', $curlist->curriculumname) . '</div>
                                 <div style=\"float:left; width: 20%;\">{$curlist->numcredits}</div>
                                 <div style=\"float:left; width: 30%;\">{$curlist->curriculumname}' . get_string('gpa', 'block_curr_admin') . ': </div>
                                 <div style=\"float:left; width: 20%;\">$gpa</div><br />";
                    $output  .= '<br /><br />';
                }

            } else {
                $output .= '<h2>' . get_string('no_classes_completed', 'block_curr_admin') . '</h2>';
            }

            $output .= $summary;
            $output .= "<br />" . get_string('transfercredits', 'block_curr_admin') . ": {$this->user->transfercredits}<br />";
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

        $filename = !empty($this->title) ? $this->title : get_string('download_report', 'block_curr_admin');

        switch ($format) {
            case 'csv':
                $filename .= '.csv';

                header("Content-Transfer-Encoding: ascii");
                header("Content-Disposition: attachment; filename=$filename");
                header("Content-Type: text/comma-separated-values");

                $row = array();

                $row[] = get_string('curriculum', 'block_curr_admin');
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

                $myxls->write($rownum, $colnum++, get_string('curriculum', 'block_curr_admin'), $formatbc);
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

                $this->newpdf = new FPDF('P', 'in', 'letter');
                $marginx = 0.75;
                $marginy = 28.35/$this->newpdf->k;
                $marginy = 0.75;
                $this->newpdf->setMargins($marginx, $marginy);
                $this->newpdf->SetFont('Arial', '', 9);
                $this->newpdf->AddPage();
                $this->newpdf->SetFillColor(225, 225, 225);

                if(file_exists("$CFG->dirroot/curriculum/pix/transcript.jpg")) {
                    $this->newpdf->Image( "$CFG->dirroot/curriculum/pix/transcript.jpg", 0, 0, 8.5, 11.0, 'jpg');
                }

                $this->newpdf->SetFont('Arial', 'I', 7);

                $this->newpdf->SetXY($marginx,2.62);
                $full = 8.5-(2.0*$marginx);
                $half = $full/2.0;
                $qrtr = $half/2.0;

                $this->newpdf->Cell($full, 0.2, get_string('transmessage', 'block_curr_admin'), 0, 0, 'C', 0);
                $this->newpdf->Ln(0.15);
                $this->newpdf->Ln(0.15);

                $this->newpdf->SetFont('Arial', '', 8);

            /// Set the left, middle and right columns.
                $leftcol = array();
                $middcol = array();
                $rghtcol = array();

                $leftcol[] = cm_fullname($this->user);
                switch ($this->user->gender) {
                    case 'M':
                    case 'm':
                        $gender = get_string('male', 'block_curr_admin');
                    break;

                    case 'F':
                    case 'f':
                        $gender = get_string('female', 'block_curr_admin');
                    break;

                    default:
                        $gender = get_string('unknown', 'block_curr_admin');
                    break;

                }
                $middcol[] = get_string('sex', 'block_curr_admin');
                $rghtcol[] = $gender;

                $bday = cm_timestring_to_date($this->user->birthdate);
                $leftcol[] = $this->user->address;
                $middcol[] = get_string('born', 'block_curr_admin');
                $rghtcol[] = $bday;

                if (!empty($this->user->origenroldate)) {
                    $enroldate = cm_timestring_to_date($this->user->origenroldate);
                } else if (!empty($this->user->timecreated)) {
                    $enroldate = cm_timestamp_to_date($this->user->timecreated);
                } else {
                    $enroldate = get_string('unknown', 'block_curr_admin');
                }
                if (!empty($this->user->address2)) {
                    $leftcol[] = $this->user->address2;
                }
                $middcol[] = get_string('registrationdate', 'block_curr_admin');
                $rghtcol[] = $enroldate;

                $text  = !empty($this->user->city) ? s($this->user->city) : '';
                $text .= (!empty($this->user->state) ? ((!empty($text) ? ', ' : '') . $this->user->state) : '');
                $text .= (!empty($this->user->postalcode) ? ((!empty($text) ? ' ' : '') . s($this->user->postalcode)) : '');
                $text .= (!empty($this->user->country) ? ((!empty($text) ? ' ' : '') . cm_get_country($this->user->country)) : '');
                $leftcol[] = $text;
                $middcol[] = get_string('nuident', 'block_curr_admin');
                $rghtcol[] = $this->user->idnumber;


                $entrycredstr = get_string('entrycred', 'block_curr_admin').': '.$this->user->entrycred;
                if ($this->newpdf->GetStringWidth($entrycredstr) > $half) {
                    if ($lines = $this->split_lines($entrycredstr, $half)) {
                        foreach ($lines as $line) {
                            $leftcol[] = $line;
                        }
                    }
                } else {
                    $leftcol[] = $entrycredstr;
                }
                $middcol[] = get_string('degree', 'block_curr_admin').':';
                $rghtcol[] = $this->user->degree;

                $leftcol[] = get_string('awards', 'block_curr_admin').': '.$this->user->awards;

                foreach ($leftcol as $idx => $lefttxt) {
                    if (isset($middcol[$idx])) {
                        $this->newpdf->Cell($half, 0.2, $lefttxt, 0, 0, 'L', 0);
                        $this->newpdf->Cell($qrtr, 0.2, $middcol[$idx], 0, 0, 'L', 0);
                        $this->newpdf->Cell($qrtr, 0.2, $rghtcol[$idx], 0, 0, 'L', 0);
                    } else {
                        $this->newpdf->Cell($full, 0.2, $lefttxt, 0, 0, 'L', 0);
                    }
                    $this->newpdf->Ln(0.15);
                }

                $twidth  = 0;
                $heights = array();
                $widths  = array();
                $hmap    = array();
                $rownum  = 0;

                $this->newpdf->SetFont('Arial', '', 7);

            /// PASS 1 - Calculate sizes.
                foreach ($this->headers as $id => $header) {
                    $widths[$id] = $this->newpdf->GetStringWidth($header) + 0.2;
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
                                $width = $this->newpdf->GetStringWidth($datum->$id) + 0.2;

                                if ($width > $widths[$id]) {
                                    $lines = ceil($width / $widths[$id]);
                                    $lines = 1;
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

                $curx = 0.2;
                $cury = $this->newpdf->GetY() + 0.1;
                $endx = 8.3;
                $endy = $cury;
                $this->newpdf->Line($marginx, $cury, 8.5-$marginx, $endy);
                $this->newpdf->Ln(0.2);

                /// Readjust the left margin according to the total width...
                $marginx = (8.5 - $twidth) / 2.0;
                $this->newpdf->setMargins($marginx, $marginy);
//                $this->newpdf->SetX($marginx);

                $this->newpdf->Cell(8.3, 0.2, get_string('transmessage1', 'block_curr_admin'), 0, 0, 'L', 0);
                $this->newpdf->Ln(0.15);
                $this->newpdf->Cell(8.3, 0.2, get_string('transmessage2', 'block_curr_admin'), 0, 0, 'L', 0);
                $this->newpdf->Ln(0.15);
                $this->newpdf->Ln(0.15);

                $leftsummary = array();
                $rightsummary = array();
                foreach ($this->rawdata as $curid => $curlist) {
                    foreach ($this->headers as $id => $header) {
                        $text = str_replace(' ', "\n", $header);
                        $this->newpdf->Cell($widths[$id], 0.2, "$text", 1, 0, 'C', 1);
                    }

                    $this->newpdf->Ln();

                    $row = 0;

                    foreach ($curlist->data as $datum) {
                        if (is_array($datum) && (strtolower($datum[0]) == 'hr')) {
                            $curx = $this->newpdf->GetX();
                            $cury = $this->newpdf->GetY() + 0.1;
                            $endx = 0;
                            $endy = $cury;

                            foreach ($widths as $width) {
                                $endx += $width;
                            }

                            $this->newpdf->Line($curx, $cury, $endx, $endy);

                            $this->newpdf->SetX($curx + 0.1);

                        } else {
                            foreach ($this->headers as $id => $header) {
                                $text = '';

                                if (isset($datum->$id)) {
                                    $text = $datum->$id;
                                }

                                $this->newpdf->Cell($widths[$id], $heights[$row], $text, 0, 0, 'L', 0);
                            }
                        }

                        $this->newpdf->Ln();
                        $row++;
                    }
                    $curx = $marginx;
                    $cury = $this->newpdf->GetY() + 0.1;
                    $endx = 8.5-$marginx;
                    $endy = $cury;
                    $this->newpdf->Line($curx, $cury, $endx, $endy);
                    $this->newpdf->Ln(0.2);

                    $this->newpdf->Write(0.2, "\n");

                    if ($curlist->numcredits > 0) {
                        $gpa = sprintf('%1.2f', ((float)$curlist->gpa / (float)$curlist->numcredits));
                    } else {
                        $gpa = '0.0';
                    }
                    $leftsummary[]  = get_string('total_credits', 'block_curr_admin', $curlist->curriculumname) . ": {$curlist->numcredits}";
                    $rightsummary[] = "{$curlist->curriculumname} Grade Point Average: $gpa";
                }

                foreach ($leftsummary as $idx => $lsummary) {
                    $this->newpdf->Cell(4.25, 0.2, $lsummary, 0, 0, 'L', 0);
                    $this->newpdf->Cell(4.25, 0.2, $rightsummary[$idx], 0, 0, 'L', 0);
                    $this->newpdf->Ln(0.15);
                }
                $this->newpdf->Ln(0.15);

                $this->newpdf->Cell(4.25, 0.2, get_string('transfercredits', 'block_curr_admin') . ": {$this->user->transfercredits}", 0, 0, 'L', 0);
                $this->newpdf->Ln(0.75);

            /// Signature line:
                $curx = $this->newpdf->GetX();
                $cury = $this->newpdf->GetY() + 0.1;
                $endx = 0;
                $endy = $cury;

                foreach ($widths as $width) {
                    $endx += $width;
                }

                $this->newpdf->Line($marginx, $cury, 8.5-$marginx, $endy);
                $this->newpdf->Ln(0.15);
                $half = (8.5-(2*$marginx))/2.0;
                $this->newpdf->Cell($half, 0.2, get_string('registrar', 'block_curr_admin'), 0, 0, 'L', 0);
                $this->newpdf->Cell($half, 0.2, get_string('date', 'block_curr_admin'), 0, 0, 'L', 0);


                $this->newpdf->Output($filename, 'I');

                break;

            default:
                return $output;
                break;
        }
    }

    function split_lines($string, $size) {
        $newstr = $string;
        $remstr = '';

        while (($this->newpdf->GetStringWidth($newstr) + 0.2) > $size) {
            if (($pos = strrpos($newstr, ' ')) !== false) {
                $newstr  = substr($newstr, 0, $pos);
            } else {
                break;
            }
        }
        $remstr .= substr($string, $pos);

        $retstr = array($newstr, trim($remstr));
        return $retstr;
    }
}

?>
