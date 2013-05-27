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


define('CM_CERTIFICATE_CODE_LENGTH', 15);


/**
 * Outputs a certificate for some sort of completion element
 *
 * @param string $person_fullname      The full name of the certificate recipient
 * @param string $entity_name          The name of the entity that is compelted
 * @param string $certificatecode      The unique certificate code
 * @param string $date_string          Date /time the certification was achieved
 * @param string $expirydate           A string representing the time that the certificate expires (optional).
 * @param string $curriculum_frequency The curriculum frequency
 * @param string $border               A custom border image to use
 * @param string $seal                 A custom seal image to use
 * @param string $template             A custom template to use
 */
function certificate_output_completion($person_fullname, $entity_name, $certificatecode = '', $date_string, $expirydate = '',
                                       $curriculum_frequency = '', $border = '', $seal = '', $template = '') {

    global $CFG;

    //use the TCPDF library
    require_once($CFG->libdir.'/pdflib.php');

//     error_log("/elis/program/lib/certificate.php::certificate_output_completion('{$person_fullname}', '{$entity_name}',
//               '{$certificatecode}', '{$date_string}', '{$expirydate}', '{$curriculum_frequency}', '{$border}', '{$seal}', '{$template}')");

    //global settings
    $borders = 0;
    $font = 'FreeSerif';
    $large_font_size = 30;
    $small_font_size = 16;

    //create pdf
    $pdf = new pdf('L', 'in', 'Letter');

    // Prevent the pdf from printing black bars.
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetAutoPageBreak(false);
    $pdf->SetMargins(0, 0, 0, false);

    $pdf->AddPage();

    // Draw the border.
    $pagewidth = $pdf->getPageWidth();
    $pageheight = $pdf->getPageHeight();
    cm_certificate_check_data_path('borders');
    if (!empty($border)) {
        if (file_exists($CFG->dirroot.'/elis/program/pix/certificate/borders/'.$border)) {
            $pdf->Image($CFG->dirroot.'/elis/program/pix/certificate/borders/'.$border, 0, 0, $pagewidth, $pageheight);
        } else if (file_exists($CFG->dataroot.'/elis/program/pix/certificate/borders/'.$border)) {
            $pdf->Image($CFG->dataroot.'/elis/program/pix/certificate/borders/'.$border, 0, 0, $pagewidth, $pageheight);
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

    // Include the certificate template
    cm_certificate_check_data_path('templates');

    if (file_exists($CFG->dirroot.'/elis/program/pix/certificate/templates/'.$template)) {
        include($CFG->dirroot.'/elis/program/pix/certificate/templates/'.$template);
    } else if (file_exists($CFG->dataroot.'/elis/program/pix/certificate/templates/'.$template)) {
        include($CFG->dataroot.'/elis/program/pix/certificate/templates/'.$template);
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

/**
 * Get the availavble certificate templates from the filesystem
 *
 * @param none
 * @return array An array of
 */
function cm_certificate_get_templates() {
    global $CFG;

    // Add default templates
    $templateoptions = array();

    $my_path = $CFG->dirroot.'/elis/program/pix/certificate/templates';

    if (file_exists($my_path) && is_dir($my_path) && $handle = opendir($my_path)) {
        while (false !== ($file = readdir($handle))) {
            if (strpos($file, '.php',1)) {
                $templateoptions[$file] = basename($file, '.php');
            }
        }
        closedir($handle);
    }

    // Add custom images
    cm_certificate_check_data_path('templates');
    $my_path = $CFG->dataroot.'/elis/program/pix/certificate/templates';
    if (file_exists($my_path) && is_dir($my_path) && $handle = opendir($my_path)) {
        while (false !== ($file = readdir($handle))) {
            if (strpos($file, '.php',1) ) {
                $templateoptions[$file] = basename($file, '.php');
            }
        }
        closedir($handle);
    }

    // Sort templates
    ksort($templateoptions);

    return $templateoptions;
}

/**
 * This function returns a random string of numbers and characters.
 * The standard length of the string is CM_CERTIFICATE_CODE_LENGTH
 * characters.  Pass a parameter to append more characters to the
 * standard CM_CERTIFICATE_CODE_LENGTH characters
 *
 * @param int $append - The number of characters to append to the standard
 * length of CM_CERTIFICATE_CODE_LENGTH
 */
function cm_certificate_generate_code($append = 0) {
    $size = CM_CERTIFICATE_CODE_LENGTH + intval($append);
    $code = random_string($size);

    return $code;
}

/**
 * This function sends a message to the development team indicating that
 * the maximum number of attempts to generate a random string has been
 * exhausted
 *
 * @uses $CDG
 * @uses $DB
 */
function cm_certificate_email_random_number_fail($tableobj = null) {
    global $CFG, $DB;

    if (empty($tableobj)) {
        return false;
    }

    require_once($CFG->dirroot.'/message/lib.php');

    //construct the message
    $a = new stdClass;
    $a->sitename = $DB->get_field('course', 'fullname', array('id' => SITEID));
    $a->url      = $CFG->wwwroot;

    $message_text  = get_string('certificate_code_fail', 'elis_proram', $a) . "\n\n";
    $message_text .= get_string('certificate_code_fail_text', 'elis_proram') . "\n";
    $message_text .= get_string('certificate_code_fail_text_data', 'elis_proram', $tableobj) . "\n";

    $message_html = nl2br($message_text);

    //send message to rladmin user if possible
    if ($rladmin_user = $DB->get_record('user', array('username' => 'rladmin', 'mnethostid' => $CFG->mnet_localhost_id))) {
        $result = message_post_message($rladmin_user, $rladmin_user, $message_html, FORMAT_HTML, 'direct');

        if ($result === false) {
            return $result;
        }
    }

    //email to specified address
    $user_obj = new stdClass;
    $user_obj->email      = 'development@remote-learner.net';
    $user_obj->mailformat = FORMAT_HTML;

    email_to_user($user_obj, get_admin(), get_string('certificate_code_fail', 'elis_proram', $a), $message_text, $message_html);

    //output to screen if possible
    if (!empty($output_to_screen)) {
        echo $message_html;
    }

    return true;
}


/**
 * Make multiple attempts to get a unique certificate code.
 *
 * @param none
 * @return string A unique certificate code.
 */
function cm_certificate_get_code() {
    $counter     = 0;
    $attempts    = 10;
    $maximumchar = 15;
    $addchar     = 0;

    // This loop will try to generate a unique string 11 times.  On the 11th attempt
    // if string is still not unique then it will add to the length of the string
    // If the length of the string exceed the maximum length set by $maximumchar
    // then stop the loop and return an error
    do {
        $code   = cm_certificate_generate_code($addchar);
        $exists = curriculum_code_exists($code);

        if (!$exists) {
            return $code;
        }

        // If the counter is equal to the number of attempts
        if ($counter == $attempts) {
            // Set counter back to zero and add a character to the string
            $counter = 0;
            $addchar++;
        }

        // increment counter otherwise this is an infinite loop
        $counter++;
    } while($maximumchar >= $addchar);

    // Check if the length has exceeded the maximum length
    if ($maximumchar < $addchar) {
        if (!cm_certificate_email_random_number_fail($this)) {
            $message = get_string('certificate_email_fail', 'elis_program');
            $OUTPUT->notification($message);
        }

        print_error('certificate_code_error', 'elis_program');
    }
}
