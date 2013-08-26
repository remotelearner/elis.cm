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
 * @package    elis_program
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}

// Check if the $params exists and if it is an array
$initialized = false;

if (isset($params) && is_array($params)) {
    $initialized = true;
}

$pdf->Ln(1.25);
$pdf->SetFont($font, '', $largefontsize);
$pdf->Cell(0, 1, get_string('certificate_title', 'elis_program'), $borders, 1, 'C');

$pdf->Ln(0.25);

$pdf->SetFont($font, '', $smallfontsize);
$pdf->Cell(0, 0.5, get_string('certificate_certify', 'elis_program'), $borders, 1, 'C');

// Person's name
$studentname = '';
if ($initialized && array_key_exists('student_name', $params)) {
    $studentname = $params['student_name'];
}

$pdf->SetFont($font, '', $largefontsize);
$pdf->Cell(0, 1, $studentname, $borders, 1, 'C');

$pdf->SetFont($font, '', $smallfontsize);
$pdf->Cell(0, 0.5, get_string('certificate_has_completed', 'elis_program'), $borders, 1, 'C');

// Entity's name
$entityname = '';
if ($initialized && array_key_exists('course_name', $params)) {
    $entityname = $params['course_name'];
}

$pdf->SetFont($font, '', $largefontsize);
$pdf->Cell(0, 1, $entityname, $borders, 1, 'C');

// Time issued
$timeissued = '';
if ($initialized && array_key_exists('cert_timeissued', $params)) {
    $timeissued = userdate($params['cert_timeissued']);
}

$pdf->SetFont($font, '', $smallfontsize);
$pdf->Cell(0, 0.5, get_string('certificate_date', 'elis_program', $timeissued), $borders, 1, 'C');