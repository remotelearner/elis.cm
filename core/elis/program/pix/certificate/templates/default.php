<?php

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from certificate.php in /elis/program/certificate.php
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
