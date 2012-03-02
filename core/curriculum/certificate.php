<?php

/**
 * Generates a PDF certificate corresponding to a particular curriculum assignment.
 */



require_once dirname(__FILE__) . '/config.php'; // Necessary because we're not accessed through index.php
require_once CURMAN_DIRLOCATION . '/lib/curriculumstudent.class.php';
require_once CURMAN_DIRLOCATION . '/lib/certificate.php';

// Retrieve curriculum assignment
$id = required_param('id', PARAM_INT);
$curass = new curriculumstudent($id);
$curuserid = cm_get_crlmuserid($USER->id);

if(!isset($curass->user) || !isset($curass->curriculum)) {
    echo("Invalid curriculum completion.");
}
else if($curuserid != $curass->userid) {
    echo("Your current user ID does not match the user ID for this curriculum completion.");
}
else if(0 == (int)($curass->timecompleted)) {
    echo("Error: curriculum not completed.");
}
else {
    $datecomplete = date("F j, Y", $curass->timecompleted);
    $dateexpired = '';

    if (!empty($CURMAN->config->enable_curriculum_expiration) && !empty($curass->timeexpired)) {
        $dateexpired  =  date("F j, Y", $curass->timeexpired);
    }

    $border_image = (isset($CURMAN->config->certificate_border_image)) ? $CURMAN->config->certificate_border_image : 'Fancy1-blue.jpg';
    $seal_image = (isset($CURMAN->config->certificate_seal_image)) ? $CURMAN->config->certificate_seal_image : 'none';
    $templates = (isset($CURMAN->config->certificate_template_file)) ? $CURMAN->config->certificate_template_file : 'default.php';


    certificate_output_completion($curass->user->to_string(), $curass->curriculum->to_string(),
                                  $curass->certificatecode, $datecomplete, $dateexpired,
                                  $curass->curriculum->frequency,
                                  $border_image,
                                  $seal_image,
                                  $templates);
}

?>