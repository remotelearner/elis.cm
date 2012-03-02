<?php
require('config.php');

global $CFG;

/*
 * This ONE TIME script assumes that a Moodle course is linked to one ELIS
 * class AND there is one ELIS class assigned to a ELIS curriculum no more or
 * less.  If the above criteria are satisfied then there will be a steady
 * calmness
 *
 */


$sql = "SELECT currass.id AS Curr_ass_id, currass.curriculumid AS Curr_id, ci.certdate AS Moodle_cert_date, ci.code AS Moodle_cert_code, ci.userid AS Moodle_user_id, currass.completed ".
       "FROM {$CFG->prefix}certificate_issues ci ".
       "INNER JOIN {$CFG->prefix}certificate c ON ci.certificateid = c.id ".
       "INNER JOIN {$CFG->prefix}crlm_class_moodle clsm ON clsm.moodlecourseid = c.course ".
       "INNER JOIN {$CFG->prefix}crlm_class cls ON clsm.classid = cls.id ".
       "INNER JOIN {$CFG->prefix}crlm_course crs ON cls.courseid = crs.id ".
       "INNER JOIN {$CFG->prefix}crlm_curriculum_course currcrs ON crs.id = currcrs.courseid ".
       "INNER JOIN {$CFG->prefix}user u ON u.id = ci.userid ".
       "INNER JOIN {$CFG->prefix}crlm_user cmu ON u.idnumber = cmu.idnumber ".
       "INNER JOIN {$CFG->prefix}crlm_curriculum_assignment currass ON cmu.id = currass.userid ".
       "WHERE currass.curriculumid = currcrs.curriculumid AND currass.completed = 2";

$records = get_records_sql($sql);

if (empty($records)) {
    echo 'Records found where Moodle certificates courses are linked with ELIS classes, where the ELIS class is linked to a ELIS curriculum and the curriculum_assignment completed record is equal to 2';
}

foreach ($records as $key => $data) {
    $updaterec = new stdClass();
    $updaterec->id = $data->Curr_ass_id;
    $updaterec->certificatecode = $data->Moodle_cert_code;
    $updaterec->timecompleted = $data->Moodle_cert_date;

    $expiry = strtotime('3 years', $data->Moodle_cert_date);

    $updaterec->timeexpired = $expiry;

    $status = update_record('crlm_curriculum_assignment', $updaterec);

    if ($status) {
        echo 'Updated curriculum_assignment record id {$data->Curr_ass_id} with the following values: '.
             "certificate code - {$data->Moodle_cert_code}, timecompleted {$data->Moodle_cert_date}, timeexpired - {$expiry}<br /><br />";
    } else {
        echo 'Error updating curriculum_assignment record id {$data->Curr_ass_id} with the following values: '.
             "certificate code - {$data->Moodle_cert_code}, timecompleted {$data->Moodle_cert_date}, timeexpired - {$expiry}<br /><br />";

    }
}

//echo $sql;

?>