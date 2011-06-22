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

class report {

    var $id;          // INT - Table identifier.
    var $title;       // STRING - The title for this report.
    var $type;        // STRING - The type of this report.
    var $table;       // OBJECT - The table object.
    var $columns;     // ARRAY - An array of strings.
    var $headers;     // ARRAY - An array of strings.
    var $align;       // ARRAY - An array of strings.
    var $sortable;    // ARRAY - An array of bools.
    var $wrap;        // ARRAY - An array of bools.
    var $defsort;     // STRING - A column to sort by default.
    var $defdir;      // STRING - The direction to sort the default column by.
    var $data;        // ARRAY - An array of table data.
    var $numrecs;     // INT - The total number of results found.
    var $baseurl;     // STRING - The base URL pointing to this report.
    var $pagerl;      // STRING - The paging URL for this report.
    var $sort;        // STRING - The column to sort by.
    var $dir;         // STRING - The direction of sorting.
    var $page;        // INT - The page number being displayed.
    var $perpage;     // INT - The number of rows per page.
    var $fileformats; // ARRAY - An array of strings for valid file formats.

    /**
     * Contructor.
     *
     * @param string $id An identifier for this table (optional).
     * @retrn none
     */
    function report($id = '') {
        $this->id          = $id;
        $this->table       = new stdClass;
        $this->columns     = array();
        $this->headers     = array();
        $this->align       = array();
        $this->sortable    = array();
        $this->wrap        = array();
        $this->defsort     = '';
        $this->defdir      = '';
        $this->data        = array();
        $this->numrecs     = 0;
        $this->baseurl     = '';
        $this->sort        = '';
        $this->dir         = '';
        $this->page        = 0;
        $this->perpage     = 0;
        $this->fileformats = array();
    }


/////////////////////////////////////////////////////////////////////
//                                                                 //
//  DATA FUNCTIONS:                                                //
//                                                                 //
/////////////////////////////////////////////////////////////////////


    /**
     * Set the array of report columns.
     *
     * @param string $id       The column ID.
     * @param string $name     The textual name displayed for the column header.
     * @param string $align    Column alignment ('left', 'center' or 'right').
     * @param bool   $sortable Whether the column is sortable or not.
     * @param bool   $wrap     If set to true the column will not automatically wrap.
     * @return bool True on success, False otherwise.
     */
    function add_column($id, $name, $align = 'left', $sortable = false, $wrap = false) {
        if ($align != 'left' || $align != 'center' || $align != 'right') {
            $align = 'left';
        }

        $this->headers[$id]  = $name;
        $this->align[$id]    = $align;
        $this->sortable[$id] = $sortable;
        $this->wrap[$id]     = $wrap;
    }


    /**
     * Set the title of this report (only really used in a PDF download
     */
    function set_title($title) {
        $this->title = $title;
    }


    /**
     * Set a column to default sorting.
     *
     * @param string $column The column ID.
     * @param string $dir    The sort direction (ASC, DESC).
     */
    function set_default_sort($column, $dir = 'ASC') {
        if (!isset($this->headers[$column]) || !$this->sortable[$column]) {
            return false;
        }

        if ($dir != 'ASC' || $dir != 'DESC') {
            $dir = 'ASC';
        }

        $this->defsort = $column;
        $this->defdir  = $dir;

        return true;
    }


    /**
     * Define the base URL for this report.
     *
     * @param string $url The base URL.
     * @return none
     */
    function set_baseurl($url) {
        $this->baseurl = $url;
    }


    /**
     * Define the paging URL for this report.
     *
     * @param string $url The paging URL.
     * @return none
     */
    function set_pageurl($url) {
        $this->pageurl = $url;
    }


    /**
     * Get the data to display for this table page.
     *
     * @TODO: This function must be extended in a subclass.
     */
    function get_data() {
    /// This function must be extended to load data into the table.
    }


/////////////////////////////////////////////////////////////////////
//                                                                 //
//  DISPLAY FUNCTIONS:                                             //
//                                                                 //
/////////////////////////////////////////////////////////////////////


    /**
     * Display the table with data.
     */
    function display() {
        global $CFG;

        $output = '';

        if (empty($this->data)) {
            return $output;
        }

        foreach ($this->headers as $column => $header) {
            $id = $column;

            if ($this->sortable[$id]) {
                if ($this->sort != $column) {
                    $columnicon = "";
                    $columndir = "ASC";
                } else {
                    $columndir  = $this->dir == "ASC" ? "DESC":"ASC";
                    $columnicon = $this->dir == "ASC" ? "down":"up";
                    $columnicon = " <img src=\"$CFG->pixpath/t/$columnicon.gif\" alt=\"\" />";
                }
                $args = '&amp;sort=' . $id . '&amp;dir=' . $columndir;
                $$column = '<a href="' . $this->baseurl . $args . '">' . $header . '</a>' . $columnicon;
            } else {
                $$column = $header;
            }

            $this->table->head[]  = $$column;
            $this->table->align[] = $this->align[$id];
            $this->table->wrap[]  = $this->wrap[$id];
        }

        foreach ($this->data as $datum) {
            $row = array();

            if (is_array($datum) && (strtolower($datum[0]) == 'hr')) {
                $row = 'hr';
            } else {
                foreach ($this->headers as $id => $header) {
                    if (isset($datum->$id)) {
                        $row[$id] = $datum->$id;
                    } else {
                        $row[$id] = '';
                    }
                }
            }

            $this->table->data[] = $row;
        }

        $this->table->width = '100%';

        $output .= print_table($this->table, true);

        return $output;
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

        if (empty($this->data)) {
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

                foreach ($this->headers as $header) {
                    $row[] = $this->csv_escape_string(strip_tags($header));
                }

                echo implode(',', $row) . "\n";

                foreach ($this->data as $datum) {
                    if (!is_object($datum)) {
                        continue;
                    }

                    $row = array();

                    foreach ($this->headers as $id => $header) {
                        if (isset($datum->$id)) {
                            $row[] = $this->csv_escape_string($datum->$id);
                        } else {
                            $row[] = '""';
                        }
                    }

                    echo implode(',', $row) . "\n";
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

                foreach ($this->headers as $header) {
                    $myxls->write($rownum, $colnum++, $header, $formatbc);
                }

                foreach ($this->data as $datum) {
                    if (!is_object($datum)) {
                        continue;
                    }

                    $rownum++;
                    $colnum = 0;

                    foreach ($this->headers as $id => $header) {
                        if (isset($datum->$id)) {
                            $myxls->write($rownum, $colnum++, $datum->$id, $format);
                        } else {
                            $myxls->write($rownum, $colnum++, '', $format);
                        }
                    }
                }

                $workbook->close();

                break;

            case 'pdf':
                require_once($CFG->libdir . '/fpdf/fpdf.php');

                $filename .= '.pdf';

                $newpdf = new FPDF('L', 'in', 'letter');
                $marginx = 0.75;
                $marginy = 0.75;
                $newpdf->setMargins($marginx, $marginy);
                $newpdf->SetFont('Arial', '', 9);
                $newpdf->AddPage();
                $newpdf->SetFont('Arial', '', 16);
                $newpdf->MultiCell(0, 0.2, $this->title, 0, 'C');
                $newpdf->Ln(0.2);
                $newpdf->SetFont('Arial', '', 8);
                $newpdf->SetFillColor(225, 225, 225);

                $heights = array();
                $widths  = array();
                $hmap    = array();
                $rownum  = 0;

            /// PASS 1 - Calculate sizes.
                foreach ($this->headers as $id => $header) {
                    $widths[$id] = $newpdf->GetStringWidth($header) + 0.2;
                }

                $row = 0;

                foreach ($this->data as $datum) {
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

                /// Calculate the width of the table...
                $twidth = 0;
                foreach ($widths as $width) {
                    $twidth += $width;
                }

                /// Readjust the left margin according to the total width...
                $marginx = (11.0 - $twidth) / 2.0;
                $newpdf->setMargins($marginx, $marginy);

                foreach ($this->headers as $id => $header) {
                    $text = str_replace(' ', "\n", $header);
                    $newpdf->Cell($widths[$id], 0.2, "$text", 1, 0, 'C', 1);
                }

                $newpdf->Ln();

                $row = 0;

                foreach ($this->data as $datum) {
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

                $newpdf->Output($filename, 'I');

                break;

            default:
                return $output;
                break;
        }
    }


    /**
     * Makes a string safe for CSV output.
     *
     * Replaces unsafe characters with whitespace and escapes
     * double-quotes within a column value.
     *
     * @param string $input The input string.
     * @return string A CSV export 'safe' string.
     */
    function csv_escape_string($input) {
        $input = ereg_replace("[\r\n\t]", ' ', $input);
        $input = ereg_replace('"', '""', $input);
        $input = '"' . $input . '"';

        return $input;
    }


    /**
     * Print the download menu.
     *
     * @param none
     * @return string HTML output for display.
     */
    function print_download_menu() {
        $output = '';

        if (!empty($this->fileformats)) {
            $output .= '<form action="reportdownload.php" method="post">' . "\n";

        /// Print out the necessary hidden form vars.
            $parts = explode('?', $this->baseurl);
            if (count($parts) == 2 && strlen($parts[1])) {
                $args = explode('&', str_replace('&amp;', '&', $parts[1]));

                if (count($args) === 0) {
                    $args = explode('&amp;', $parts[1]);
                }

                if (!empty($args)) {
                    foreach ($args as $arg) {
                        $vals = explode('=', $arg);

                        if (!empty($vals[1])) {
                            $output .= '<input type="hidden" name="' . $vals[0] .
                                       '" value="' . urldecode($vals[1]) . '" />';
                        }
                    }
                }
            }

            $output .= cm_choose_from_menu($this->fileformats, 'download', '', 'choose', '', '0', true);
            $output .= '<input type="submit" value="' . get_string('download_report', 'block_curr_admin') . '" />' . "\n";
            $output .= '</form>' . "\n";
        }

        return $output;
    }


    /**
     * Print the paging headers for the table.
     *
     * @param none
     * @return string HTML output for display.
     */
    function print_header() {
        $output = '';

        $args = '';

        $output .= print_paging_bar($this->numrecs, $this->page, $this->perpage,
                                    "{$this->baseurl}&amp;sort={$this->sort}&amp;dir={$this->dir}&amp;" .
                                    "perpage={$this->perpage}" . $args . "&amp;", 'page', false, true);

        if (isset($this->filter)) {
            $output .= $this->filter->display_add(true);
            $output .= $this->filter->display_active(true);
        }

        return $output;
    }


    /**
     * Print the paging footer for the table.
     *
     * @param none
     * @return string HTML output for display.
     */
    function print_footer() {

        $args = '';
        $output = '';

        $output .= print_paging_bar($this->numrecs, $this->page, $this->perpage,
                                    "{$this->baseurl}&amp;sort={$this->sort}&amp;dir={$this->dir}&amp;" .
                                    "perpage={$this->perpage}" . $args . "&amp;", 'page', false, true);

        return $output;
    }


    /**
     * Main display function.
     *
     * @TODO: This function must be extended in a subclass (using the same interface parameters).
     */
    function main($sort = '', $dir = '', $page = 0, $perpage = 0, $download = '') {
    /// To be exteded.
    }
}

?>
