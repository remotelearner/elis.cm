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

if(!defined('NO_ROLE_ID')) {
    define('NO_ROLE_ID', 0);
}

require_once elispm::file('form/cmform.class.php');

class configcrsdefaultform extends cmform {

    function definition() {
        $mform =& $this->_form;

        $mform->addElement('header', 'crsdftdefault', get_string('defaultcrs', 'elis_program'));

        $mform->addElement('text', 'crsdftname', get_string('course_name', 'elis_program') . ':');
        $mform->setType('crsdftname', PARAM_TEXT);

        $mform->addElement('text', 'crsdftcode', get_string('course_code', 'elis_program') . ':');
        $mform->setType('crsdftcode', PARAM_TEXT);

        $mform->addElement('text', 'crsdftidnumber', get_string('course_idnumber', 'elis_program') . ':');
        $mform->setType('crsdftidnumber', PARAM_TEXT);

        $attributes = array('cols'=>40, 'rows'=>2);
        $mform->addElement('textarea', 'crsdftsyllabus', get_string('course_syllabus', 'elis_program') . ':', $attributes);
        $mform->setType('crsdftsyllabus', PARAM_CLEAN);

        $mform->addElement('text', 'crsdftlengthdescription', get_string('length_description', 'elis_program'));
        $mform->setType('crsdftlengthdescription', PARAM_TEXT);
        $mform->addRule('crsdftlengthdescription', null, 'maxlength', 100);

        $mform->addElement('text', 'crsdftlength', get_string('duration', 'elis_program') . ':');
        $mform->setType('crsdftlength', PARAM_INT);

        $mform->addElement('text', 'crsdftcredits', get_string('credits', 'elis_program') . ':');
        $mform->setType('crsdftcredits', PARAM_NUMBER);
        $mform->addRule('crsdftcredits', null, 'maxlength', 10);

        $grades = range(0,100,1);
        $mform->addElement('select', 'crsdftcompletion_grade', get_string('completion_grade', 'elis_program') . ':', $grades);

        $mform->addElement('text', 'crsdftcost', get_string('cost', 'elis_program') . ':');
        $mform->setType('crsdftcost', PARAM_TEXT);
        $mform->addRule('crsdftcost', null, 'maxlength', 10);

        $mform->addElement('text', 'crsdftversion', get_string('course_version', 'elis_program') . ':');
        $mform->setType('crsdftversion', PARAM_TEXT);
        $mform->addRule('crsdftversion', null, 'maxlength', 100);

        $this->add_action_buttons();
    }

    function set_data($default_values, $slashed=false) {

        $default_values = clone $default_values;
        parent::set_data($default_values, $slashed);
    }
}