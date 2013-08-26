<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General
 * Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    elis
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

/**
 * Generates a PDF certificate corresponding to a particular curriculum assignment.
 */
require_once(dirname(__FILE__).'/../../config.php');
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(elispm::lib('deprecatedlib.php')); // cm_get_crlmuserid()
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/course.class.php'));
require_once(elispm::lib('data/certificatesettings.class.php'));
require_once(elispm::lib('data/certificateissued.class.php'));
require_once(elispm::lib('data/student.class.php'));
require_once(elispm::lib('data/instructor.class.php'));

$ciid = required_param('id', PARAM_INT); // Issued certificate id
$csid = required_param('csid', PARAM_INT); // certificate setting id

global $USER;

$cmuserid = cm_get_crlmuserid($USER->id);

$student = new user($cmuserid);

$student->load();

if (empty($student->id)) {
    return get_string('studentnotfound', 'elis_program');
}

// Retrieve the certificate settings record
$certsettingrec = new certificatesettings($csid);
$certsettingrec->load();

// Check if the record exists or if the certificate is disabled
if (empty($certsettingrec->id) and !empty($certsettingrec->disable)) {
    // Passing hard coded error code to disallow administrators from changing them to
    // custom strings
    echo get_string('errorfindingcertsetting', 'elis_program', 'Error 11');
}

// Retrieve the certificate issued record
$certissuedrec = new certificateissued($ciid);
$certissuedrec->load();

// Check if the record exists or if the certificate is disabled
if (empty($certissuedrec->id) and !empty($certissuedrec->disable)) {
    // Passing hard coded error code to disallow administrators from changing them to
    // custom strings
    echo get_string('errorfindingcertissued', 'elis_program', 'Error 22');
}

// Set the border, seal and template filenames and other info
$borderimage  = $certsettingrec->cert_border;
$sealimage    = $certsettingrec->cert_seal;
$template     = $certsettingrec->cert_template;
$instructor   = '';
$params       = array();

// Retrieve additional metadata about the entity
$params = certificate_get_entity_metadata($certsettingrec, $certissuedrec, $student);

if (!empty($params)) {
    certificate_output_entity_completion($params, $borderimage, $sealimage, $template);
} else {
    echo get_string('errorfindingcertsetting', 'elis_program', 'Error 33');
}