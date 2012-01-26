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

require_once CURMAN_DIRLOCATION . '/lib/course.class.php';
require_once CURMAN_DIRLOCATION . '/lib/curriculum.class.php';
require_once CURMAN_DIRLOCATION . '/lib/curriculumcourse.class.php';
require_once CURMAN_DIRLOCATION . '/lib/cmclass.class.php';
require_once CURMAN_DIRLOCATION . '/lib/user.class.php';
require_once CURMAN_DIRLOCATION . '/lib/student.class.php';
require_once CURMAN_DIRLOCATION . '/lib/instructor.class.php';


class cmimport {

    var $id;            // INT - The data id if in the database.
    var $name;          // STRING - Textual name of the tag.
    var $description;   // STRING - A description of the tag.
    var $timecreated;   // INT - Timestamp.
    var $timemodified;  // INT - Timestamp.

    var $_dbloaded;     // BOOLEAN - True if loaded from database.

    /// Valid mimetypes for the uploaded CSV file.
    var $valid_mimetypes = array(
        'text/plain',
        'text/comma-separated-values',
        'text/csv',
        'text/x-csv',
        'application/csv'
    );

    // STRING - Styles to use for edit form.
/*
    var $_editstyle = '
.cmimportform input,
.cmimportform textarea {
    margin: 0;
    display: block;
}
';
*/
    var $_editstyle = '';


    /**
     * Contructor.
     *
     * @param $tagdata int/object/array The data id of a data record or data elements to load manually.
     *
     */
    function cmimport($tagdata=false) {
        $this->_dbloaded = false;

        $this->id = 0;
        $this->name = '';
        $this->description = '';
        $this->timecreated = 0;
        $this->timemodified = 0;

        if (is_numeric($tagdata)) {
            $this->data_load_record($tagdata);
        } else if (is_array($tagdata)) {
            $this->data_load_array($tagdata);
        } else if (is_object($tagdata)) {
            $this->data_load_array(get_object_vars($tagdata));
        }

    }


/////////////////////////////////////////////////////////////////////
//                                                                 //
//  FORM FUNCTIONS:                                                //
//                                                                 //
/////////////////////////////////////////////////////////////////////


    /**
     * Return the HTML to edit a specific tag.
     * This could be extended to allow for application specific editing, for example
     * a Moodle interface to its formslib.
     *
     * @param $formid string A suffix to put on all 'id' and index for all 'name' attributes.
     *                       This should be unique if being used more than once in a form.
     * @param $extraclass string Any extra class information to add to the output.
     *
     * @return string The form HTML, without the form.
     */
    function import_form_html($formid='', $extraclass='', $rows='2', $cols='40') {
        global $CFG;

        $index = !empty($formid) ? '['.$formid.']' : '';
        $formid_suffix = !empty($formid) ? '_'.$formid : '';

        $output = '';

        $output .= '<style>'.$this->_editstyle.'</style>';
        $output .= '<fieldset id="cmimportform'.$formid.'" class="cmimportform '.$extraclass.'">'."\n";
        $output .= '<legend>' . get_string('import_data_options', 'block_curr_admin');
        $output .= '<a href="index.php?s=stuimp&amp;section=curr&amp;action=help" title="Student Performance Data File Format">';
        $output .= '<img class="iconhelp" src="pix/help.gif" alt="Help with Student Performance Data File Format"/>';
        $output .= '</a>';
        $output .= '</legend>'."\n";
        $output .= '<label for="importfile'.$formid.'" id="limportfile'.$formid.'">Import File: ';
        $output .= '<input type="file" name="importfile'.$index.'" value="" id="importfile'.$formid.'" '.
                   'class="cmimportform '.$extraclass.'" />'."\n";
        $output .= '</label><br />' . "\n";
        $output .= '<label for="update' . $formid . '" id="lupdate' . $formid . '">Update existing records? ';
        $output .= '<input type="hidden" name="update" value="0" />';
        $output .= '<input type="checkbox" name="update' . $index . '" value="1" id="update' . $formid . '" ' .
                   'class="cmimportform ' . $extraclass . '" />' . "\n";
        $output .= '</label><br />' . "\n";
        $output .= '<label for="verbose' . $formid . '" id="lverbose' . $formid . '">Verbose output? ';
        $output .= '<input type="hidden" name="verbose" value="0" />';
        $output .= '<input type="checkbox" name="verbose' . $index . '" value="1" id="verbose' . $formid . '" ' .
                   'class="cmimportform ' . $extraclass . '" />' . "\n";
        $output .= '</label><br />' . "\n";
        $output .= '</fieldset>';

        return $output;
    }


/////////////////////////////////////////////////////////////////////
//                                                                 //
//  DATA FUNCTIONS:                                                //
//                                                                 //
/////////////////////////////////////////////////////////////////////


    /**
     * Actually process the uploaded CSV organization file upon successful upload.
     *
     * NOTE: A lot o code here is borrowed / modified from Moodle.
     *
     * @see Moodle:/admin/uploaduser.php
     *
     * @uses $CURMAN
     * @param array $fieldata A PHP upload file array (i.e. from the $_FILES superglobal).
     * @param bool  $update   Flag for updating existing records.
     * @param bool  $verbose  Flag for verbose output.
     * @return string Output for display.
     */
    function process_input_data($filedata, $update = false, $verbose = false) {
        global $CURMAN;

        $output = '';

    /// Don't check for a valid mime/type as this is causing errors for the client.
/*
        if (!in_array($filedata['type'], $this->valid_mimetypes)) {
            return 'The file format uploaded was incorrect';
        }
*/
        if ($filedata['size'] === 0) {
            return get_string('uploaded_empty_file', 'block_curr_admin');
        }

        /**
         * Large files are likely to take their time and memory. Let PHP know
         * that we'll take longer, and that the process should be recycled soon
         * to free up memory.
         */
        @set_time_limit(0);
        @cm_raise_memory_limit('192M');
        if (function_exists('apache_child_terminate')) {
            @apache_child_terminate();
        }

        $csv_encode     = '/\&\#44/';
        $csv_delimiter  = "\,";
        $csv_delimiter2 = ",";

        $data = '';
        $file = @fopen($filedata['tmp_name'], 'rb');
        if ($file) {
            while (!feof($file)) {
                $data .= fread($file, 1024);
            }
            fclose($file);
        }

        if (empty($data)) {
            return get_string('no_data_file', 'block_curr_admin');
        }

        /**
         * Removes the BOM from unicode string - see http://unicode.org/faq/utf_bom.html
         *
         * Borrowed from Moodle code - /lib/textlib.class.php
         */
        $bom = "\xef\xbb\xbf";
        if (strpos($data, $bom) === 0) {
            $data = substr($data, strlen($bom));
        }

        /**
         * Fix Mac/DOS newlines
         *
         * Borrowed from Moodle code - /admin/uploaduser.php
         */
        $data = preg_replace('!\r\n?!', "\n", $data);
        $fp   = fopen($filedata['tmp_name'], 'w');
        fwrite($fp, $data);
        fclose($fp);
        $fp = fopen($filedata['tmp_name'], 'r');

        /**
         * The required and optional fields we're looking for in the CSV file.
         */
        $required = array(
            'studentid'  => 1,
            'class'      => 1,
            'trainernum' => 1,
            'startdate'  => 1,
            'enddate'    => 1
        );

        $optional = array(
            'firstname'  => 1,
            'lastname'   => 1,
            'curriculum' => 1,
            'status'     => 1,
            'completed'  => 1,
            'grade'      => 1,
            'frequency'  => 1,
            'timeperiod' => 1
        );

        $colpos = array();
        $header = split($csv_delimiter, fgets($fp,1024));

    // Check for valid field names
        foreach ($header as $i => $h) {
            $h = trim($h); $header[$i] = $h;      // remove whitespace
            $h = ereg_replace('^\"|\"$', '', $h); // strip encapsulating quotes

            $header[$i] = $h;

            if (isset($required[$h])) {
                $required[$h] = 0;
                $colpos[$i] = $h;
            } else if (isset($optional[$h])) {
                $colpos[$i] = $h;
            }
        }

    /// Check for required fields
        foreach ($required as $key => $value) {
            if ($value) {  //required field missing
                return get_string('missing_required_field', 'block_curr_admin', $key);
            }
        }

        $linenum = 2; // since header is line 1

        $stusnew     = 0;
        $stuserror   = 0;
        $stusupdated = 0;

        $timenow = time();

        while (!feof ($fp)) {
            //Note: commas within a field should be encoded as &#44 (for comma separated csv files)
            //Note: semicolon within a field should be encoded as &#59 (for semicolon separated csv files)
            $line = split($csv_delimiter, fgets($fp,1024));

            foreach ($line as $key => $value) {
                if (isset($colpos[$key])) {
                /// decode encoded commas and strip enapsulating quotes
                    $record[$colpos[$key]] = preg_replace($csv_encode,$csv_delimiter2,trim($value));
                    $record[$colpos[$key]] = ereg_replace('^\"|\"$', '', $record[$colpos[$key]]);
                }
            }

        /// Got organization data
            if ($record[$header[0]]) {
                $done = false;

                $users = $CURMAN->db->get_records(USRTABLE, 'idnumber', $record['studentid']);
                $user  = NULL;

                /// Don't worry about the actual type. Just worry about the idnumber.
                if (!empty($users)) {
                    $user = current($users);
                }

            /// Only proceed if this student and instructor users actually exists.
                if (!empty($user->id)) {
                    $crsidnumber = $record['class'];
                    $dateparts = explode('/', $record['startdate']);
                    $startdate = mktime(0, 0, 0, $dateparts[1], $dateparts[0], $dateparts[2]);
                    $dateparts = explode('/', $record['enddate']);
                    $enddate = mktime(0, 0, 0, $dateparts[1], $dateparts[0], $dateparts[2]);

                /// Check if the class as specified exists...
                    $clsidnumber = $record['class'];
                    if (!($class = $CURMAN->db->get_record(CLSTABLE, 'idnumber', $clsidnumber))) {
                        $clsidnumber = $record['class'] . '-' . $record['trainernum'];

                    /// Need to check for old classes that didn't have dates, and remove them.
                        if ($class = $CURMAN->db->get_record(CLSTABLE, 'idnumber', $clsidnumber)) {
                            $class = new cmclass($class);
                            $class->delete();
                        }

                    /// If the class doesn't exist, we have to create it first.
                        $datepart = date('Ymd', $startdate);
                        $clsidnumber = $record['class'] . '-' . $record['trainernum'] . '-' . $datepart;
                        $class = $CURMAN->db->get_record(CLSTABLE, 'idnumber', $clsidnumber);
                    }

                    if (empty($class->id) || ($update && !empty($class->id))) {
                        if ($course = $CURMAN->db->get_record(CRSTABLE, 'idnumber', $crsidnumber)) {

                        /// Do we need to add / update curriculum info for this course???
                            if (isset($record['curriculum'])) {
                                if ($cur = $CURMAN->db->get_record(CURTABLE, 'idnumber', $record['curriculum'])) {
                                    $curcrs = $CURMAN->db->get_record(CURCRSTABLE, 'curriculumid', $cur->id,
                                                                      'courseid', $course->id);

                                    if ((!$update && empty($curcrs->id)) || ($update && !empty($curcrs->id))) {
                                        $cmcrec = array(
                                            'curriculumid' => $cur->id,
                                            'courseid'     => $course->id
                                        );

                                        if (!empty($record['frequency'])) {
                                            $cmcrec['frequency'] = $record['frequency'];
                                        }

                                        if (!empty($record['timeperiod'])) {
                                            $cmcrec['timeperiod'] = $record['timeperiod'];
                                        }

                                        if (empty($curcrs->id)) {
                                            $curcrs = new curriculum($cmcrec);
                                        } else {
                                            $curcrs = new curriculum($curcrs->id);
                                            foreach ($cmcrec as $key => $val) {
                                                $curcrs->$key = $val;
                                            }
                                        }

                                        $a = new object();
                                        $a->courseid = $course->idnumber;
                                        $a->coursename = $course->name;
                                        $a->curid = $cur->idnumber;
                                        
                                        if ($update && !empty($curcrs->id)) {
                                            if ($curcrs->data_update_record() && $verbose) {
                                                $output .= get_string('updated_curriculum_course_info', 'block_curr_admin');
                                            }
                                        } else {
                                            if ($curcrs->data_insert_record() && $verbose) {
                                                $output .= get_string('added_curriculum_course_info', 'block_curr_admin');
                                            }
                                        }
                                    }
                                }
                            }

                            $clsrec = array(
                                'courseid'  => $course->id,
                                'idnumber'  => $clsidnumber,
                                'startdate' => $startdate,
                                'enddate'   => $enddate
                            );

                            if (empty($class->id)) {
                                $class = new cmclass($clsrec);
                            } else {
                                $class = new cmclass($class->id);
                                foreach ($clsrec as $key => $val) {
                                    $class->$key = $val;
                                }
                            }

                            if ($update && !empty($class->id)) {
                                if ($class->data_update_record() && $verbose) {
                                    $output .= get_string('updated_class_info', 'block_curr_admin') . $class->idnumber .
                                               '<br /><br />' . "\n";
                                }
                            } else {
                                if ($class->data_insert_record() && $verbose) {
                                    $output .= get_string('added_class_info', 'block_curr_admin') . $class->idnumber .
                                               '<br /><br />' . "\n";
                                }
                            }

                            if (empty($class->id) && $verbose) {
                                $output .= get_string('error_class_not_created', 'block_curr_admin') . $class->idnumber .
                                           '<br /><br />' . "\n";
                            }
                        } else if ($verbose) {
                            $output .= get_string('error_course_not_found', 'block_curr_admin') . $crsidnumber .
                                       '<br /><br />' . "\n";
                        }
                    }

                /// Only proceed if we have an actual class here...
                    if (!empty($class->id)) {
                        $instructors = $CURMAN->db->get_records(USRTABLE, 'idnumber', $record['trainernum']);
                        $instructor  = NULL;

                        /// Don't worry about the actual type. Just worry about the idnumber.
                        if (!empty($instructors)) {
                            $instructor = current($instructors);
                        }

                        if (!empty($instructor->id) &&
                            !$CURMAN->db->record_exists(INSTABLE, 'classid', $class->id,
                                                        'userid', $instructor->id)) {

                            $insrec = array(
                                'classid'       => $class->id,
                                'userid'        => $instructor->id,
                            );

                            $newins = new instructor($insrec);
                            if ($newins->data_insert_record() && $verbose) {
                                $output .= get_string('added_instructor_class', 'block_curr_admin', cm_fullname($instructor)) . $class->idnumber . '<br /><br />' . "\n";
                            }
                        }

                        $student = $CURMAN->db->get_record(STUTABLE, 'classid', $class->id,
                                                           'userid', $user->id);

                        $a = new object();
                        $a->name = cm_fullname($user);
                        $a->id = $class->idnumber;
                        
                        if ((!$update && empty($student->id)) || ($update && !empty($student->id))) {
                            $sturec = array(
                                'classid'       => $class->id,
                                'userid'        => $user->id,
                            );

                            if (isset($record['status'])) {
                                $sturec['completestatusid'] = intval($record['status']);
                            }

                            if (!isset($record['completed'])) {
                                $sturec['completetime'] = $enddate;
                            } else {
                                $d = explode('/', $record['completed']);
                                if (count($d) == 3) {
                                    $day       = $d[0];
                                    $month     = $d[1];
                                    $year      = $d[2];
                                    $timestamp = mktime(0, 0, 0, $month, $day, $year);

                                    $sturec['completetime'] = $timestamp;
                                }
                            }

                            if (isset($record['grade'])) {
                                $sturec['grade'] = intval($record['grade']);
                            }

                            if (empty($student->id)) {
                                $student = new student($sturec);
                            } else {
                                $student = new student($student->id);
                                foreach ($sturec as $key => $val) {
                                    $student->$key = $val;
                                }
                            }

                            if ($update && !empty($student->id)) {
                                if ($student->data_update_record() && $verbose) {
                                    $output .= get_string('update_enrolment_info', 'block_curr_admin', $a) . '<br /><br />' . "\n";
                                }
                            } else {
                                if ($student->data_insert_record() && $verbose) {
                                    $output .= get_string('add_enrolment_info', 'block_curr_admin', $a) . '<br /><br />' . "\n";
                                }
                            }
                        } else {
                            $student = NULL;
                            if ($verbose) {
                                $output .= get_string('existing_enrolment_info', 'block_curr_admin') . '<br /><br />' . "\n";
                            }
                        }

                        if (!empty($student->id)) {
                            $done = true;
                        }
                    }
                } else {
                    $output .= get_string('error_studentid_not_found', 'block_curr_admin') . $record['studentid'] .
                               '<br /><br />' . "\n";
                }

                if ($update && $done) {
                    $stusupdated++;
                } else if (!$update && $done) {
                    $stusnew++;
                } else {
                    $stuserror++;
                }
            }
        }

        if (!empty($output)) {
            $output .= '<br /><br />';
        }
        if (!$stusnew && !$stusupdated && !$stuserror) {
            $output .= get_string('nothing_done', 'block_curr_admin');
        }
        if ($stusnew > 0) {
            $output .= get_string('added_new_students', 'block_curr_admin', $stusnew);
        }
        if ($stusupdated > 0) {
            $output .= get_string('updated_existing_students', 'block_curr_admin', $stusupdated);
        }
        if ($stuserror > 0) {
            $output .= get_string('error_not_processed', 'block_curr_admin', $stuserror);
        }

        return $output;
    }
}

?>
