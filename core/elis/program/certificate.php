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

/**
 * Generates a PDF certificate corresponding to a particular curriculum assignment.
 */

require_once(dirname(__FILE__) .'/../../config.php');
require_once($CFG->dirroot .'/elis/program/lib/setup.php');
require_once(elispm::lib('data/curriculumstudent.class.php'));
require_once(elispm::lib('certificate.php'));
require_once(elispm::lib('deprecatedlib.php')); // cm_get_crlmuserid()

// Retrieve curriculum assignment
$id = required_param('id', PARAM_INT);


$curass = new curriculumstudent($id);

// TBD: following required to get user name to display on certificate!
$curass->load();
if (!empty($curass->user)) {
    $curass->user->load();
}

$curuserid = cm_get_crlmuserid($USER->id);

if (!isset($curass->user) || !isset($curass->curriculum)) {
    print_string('invalid_curriculum_completion', 'elis_program');
} else if ($curuserid != $curass->userid) {
    print_string('curriculum_userid_mismatch', 'elis_program');
} else if (0 == (int)($curass->timecompleted)) {
    print_string('error_curriculum_incomplete', 'elis_program');
} else {
    $datecomplete = date("F j, Y", $curass->timecompleted);

    $dateexpired = '';
    if (!empty(elis::$config->elis_program->enable_curriculum_expiration) && !empty($curass->timeexpired)) {
        $dateexpired  =  date("F j, Y", $curass->timeexpired);
    }

    $border_image = (isset(elis::$config->elis_program->certificate_border_image))
                    ? elis::$config->elis_program->certificate_border_image
                    : 'Fancy1-blue.jpg';

    $seal_image = (isset(elis::$config->elis_program->certificate_seal_image))
                  ? elis::$config->elis_program->certificate_seal_image
                  : 'none';

    $templates = (isset(elis::$config->elis_program->certificate_template_file)) ?
                  elis::$config->elis_program->certificate_template_file : 'default.php';

    certificate_output_completion($curass->user->__toString(), $curass->curriculum->__toString(), $curass->certificatecode,
                                  $datecomplete, $dateexpired, $curass->curriculum->frequency, $border_image, $seal_image, $templates);
}
