<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2011 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Outputs a certificate for some sort of completion element
 *
 * @param  string  $person_fullname  The full name of the certificate recipient
 * @param  string  $entity_name      The name of the entity that is compelted
 * @param  string  $date_string      Date /time the certification was achieved
 * @param  string  $expirydate       A string representing the time that the certificate expires (optional).
 */
function certificate_output_completion($person_fullname, $entity_name, $date_string, $expirydate = '', $border = '', $seal = '') {
    global $CFG;

    //use the TCPDF library
    require_once($CFG->libdir.'/pdflib.php');

    //error_log("/elis/program/lib/certificate.php::certificate_output_completion('{$person_fullname}', '{$entity_name}', '{$date_string}', '{$expirydate}', {$border}, {$seal})");

    //global settings
    $borders = 0;
    $font = 'FreeSerif';
    $large_font_size = 30;
    $small_font_size = 16;

    //create pdf
    $pdf = new pdf('L', 'in', 'Letter');

    //prevent the pdf from printing black bars
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    //add main (only) page; this next call sometimes adds an empty first page!
    if (empty($border) || $border == 'none') {
        $pdf->AddPage();
    }

    //draw the border
    cm_certificate_check_data_path('borders');
    if (!empty($border)) {
        if (file_exists($CFG->dirroot .'/elis/program/pix/certificate/borders/'. $border)) {
            $pdf->Image($CFG->dirroot .'/elis/program/pix/certificate/borders/'. $border, 0, 0, 10.25, 7.75);
        } else if (file_exists($CFG->dataroot .'/elis/program/pix/certificate/borders/'. $border)) {
            $pdf->Image($CFG->dataroot .'/elis/program/pix/certificate/borders/'. $border, 0, 0, 10.25, 7.75);
        }
    }

    //draw the seal
    cm_certificate_check_data_path('seals');
    if (!empty($seal)) {
        if (file_exists($CFG->dirroot .'/elis/program/pix/certificate/seals/'. $seal)) {
            $pdf->Image($CFG->dirroot .'/elis/program/pix/certificate/seals/'. $seal, 8.0, 5.8);
        } else if (file_exists($CFG->dataroot .'/elis/program/pix/certificate/seals/' . $seal)) {
            $pdf->Image($CFG->dataroot .'/elis/program/pix/certificate/seals/'. $seal, 8.0, 5.8);
        }
    }

    //add the header
    $pdf->Ln(1.25);
    $pdf->SetFont($font, '', $large_font_size);
    $pdf->Cell(0, 1, get_string('certificate_title', 'elis_program'), $borders, 1, 'C');

    $pdf->Ln(0.25);

    $pdf->SetFont($font, '', $small_font_size);
    $pdf->Cell(0, 0.5, get_string('certificate_certify', 'elis_program'), $borders, 1, 'C');

    //person's name
    $pdf->SetFont($font, '', $large_font_size);
    $pdf->Cell(0, 1, $person_fullname, $borders, 1, 'C');

    $pdf->SetFont($font, '', $small_font_size);
    $pdf->Cell(0, 0.5, get_string('certificate_has_completed', 'elis_program'), $borders, 1, 'C');

    //entity's name
    $pdf->SetFont($font, '', $large_font_size);
    $pdf->Cell(0, 1, $entity_name, $borders, 1, 'C');

    //time issued
    $pdf->SetFont($font, '', $small_font_size);
    $pdf->Cell(0, 0.5, get_string('certificate_date', 'elis_program', $date_string), $borders, 1, 'C');

    // Expiry date (if applicable)
    if (!empty($expirydate)) {
        $pdf->SetFont($font, '', 11);
        $pdf->Cell(0, 0.5, get_string('certificate_expires', 'elis_program'), $borders, 1, 'C');
        $pdf->Cell(0, 0.05, $expirydate, $borders, 1, 'C');
    }

    $pdf->Output();
}

function cm_certificate_get_borders() {
    global $CFG;

    // Add default images
    $my_path = "{$CFG->dirroot}/elis/program/pix/certificate/borders";
    $borderstyleoptions = array();
    if (file_exists($my_path) && $handle = opendir($my_path)) {
        while (false !== ($file = readdir($handle))) {
            if (strpos($file, '.png',1)||strpos($file, '.jpg',1) ) {
                $i = strpos($file, '.');
                if ($i > 1) {
                    $borderstyleoptions[$file] = substr($file, 0, $i);
                }
            }
        }
        closedir($handle);
    }

    // Add custom images
    cm_certificate_check_data_path('borders');
    $my_path = "{$CFG->dataroot}/elis/program/pix/certificate/borders";
    if (file_exists($my_path) && $handle = opendir($my_path)) {
        while (false !== ($file = readdir($handle))) {
            if (strpos($file, '.png',1)||strpos($file, '.jpg',1) ) {
                $i = strpos($file, '.');
                if ($i > 1) {
                    $borderstyleoptions[$file] = substr($file, 0, $i);
                }
            }
        }
        closedir($handle);
    }

    // Sort borders
    ksort($borderstyleoptions);

    // Add no border option
    $borderstyleoptions['none'] = get_string('none');

    return $borderstyleoptions;
}

function cm_certificate_get_seals() {
    global $CFG;

    // Add default images
    $my_path = "{$CFG->dirroot}/elis/program/pix/certificate/seals";
    $sealoptions = array();
    if (file_exists($my_path) && $handle = opendir($my_path)) {
        while (false !== ($file = readdir($handle))) {
            if (strpos($file, '.png',1)||strpos($file, '.jpg',1) ) {
                $i = strpos($file, '.');
                if ($i > 1) {
                    $sealoptions[$file] = substr($file, 0, $i);
                }
            }
        }
        closedir($handle);
    }

    // Add custom images
    cm_certificate_check_data_path('seals');
    $my_path = "{$CFG->dataroot}/elis/program/pix/certificate/seals";
    if (file_exists($my_path) && $handle = opendir($my_path)) {
        while (false !== ($file = readdir($handle))) {
            if (strpos($file, '.png',1)||strpos($file, '.jpg',1) ) {
                $i = strpos($file, '.');
                if ($i > 1) {
                    $sealoptions[$file] = substr($file, 0, $i);
                }
            }
        }
        closedir($handle);
    }

    // Sort seals
    ksort($sealoptions);

    // Add no seal option
    $sealoptions['none'] = get_string('none');

    return $sealoptions;
}

function cm_certificate_check_data_path($imagetype) {
    global $CFG;

    $path_array = array('elis', 'program', 'pix', 'certificate', $imagetype);
    $full_path = $CFG->dataroot;
    foreach ($path_array as $path) {
        $full_path .= '/' . $path;
        if (!file_exists($full_path)) {
            mkdir($full_path);
        }
    }
}

