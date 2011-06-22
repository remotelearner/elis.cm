<?php
/**
 * Form used for editing / displaying default values for class records.
 *
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2009 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2008-2009 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

if(!defined('NO_ROLE_ID')) {
    define('NO_ROLE_ID', 0);
}

require_once(CURMAN_DIRLOCATION . '/form/cmform.class.php');

    class configcrsdefaultform extends cmform {

        function definition() {
            global $USER, $CFG, $COURSE, $CURMAN;

            $mform =& $this->_form;

            $mform->addElement('header', 'crsdftdefault', get_string('defaultcrs', 'block_curr_admin'));

            $mform->addElement('text', 'crsdftname', get_string('course_name', 'block_curr_admin') . ':');
            $mform->setType('crsdftname', PARAM_TEXT);

            $mform->addElement('text', 'crsdftcode', get_string('course_code', 'block_curr_admin') . ':');
            $mform->setType('crsdftcode', PARAM_TEXT);

            $mform->addElement('text', 'crsdftidnumber', get_string('course_idnumber', 'block_curr_admin') . ':');
            $mform->setType('crsdftidnumber', PARAM_TEXT);

            $attributes = array('cols'=>40, 'rows'=>2);
            $mform->addElement('textarea', 'crsdftsyllabus', get_string('course_syllabus', 'block_curr_admin') . ':', $attributes);
            $mform->setType('crsdftsyllabus', PARAM_CLEAN);

            $mform->addElement('text', 'crsdftlengthdescription', get_string('length_description', 'block_curr_admin'));
            $mform->setType('crsdftlengthdescription', PARAM_TEXT);
            $mform->addRule('crsdftlengthdescription', null, 'maxlength', 100);

            $mform->addElement('text', 'crsdftlength', get_string('duration', 'block_curr_admin') . ':');
            $mform->setType('crsdftlength', PARAM_INT);

            $mform->addElement('text', 'crsdftcredits', get_string('credits', 'block_curr_admin') . ':');
            $mform->setType('crsdftcredits', PARAM_TEXT);
            $mform->addRule('crsdftcredits', null, 'maxlength', 10);

            $grades = range(0,100,1);
            $mform->addElement('select', 'crsdftcompletion_grade', get_string('completion_grade', 'block_curr_admin') . ':', $grades);


            $environments = array('- ' . get_string('none', 'block_curr_admin') . ' -');
            $envs = environment_get_listing();

            if(empty($envs)) {
                $envs = array();
            }

            foreach($envs as $e){
                $environments[$e->id] = $e->name;
            }

            $mform->addElement('select', 'crsdftenvironmentid', get_string('environment', 'block_curr_admin'), $environments);

            $mform->addElement('text', 'crsdftcost', get_string('cost', 'block_curr_admin') . ':');
            $mform->setType('crsdftcost', PARAM_TEXT);
            $mform->addRule('crsdftcost', null, 'maxlength', 10);

            $mform->addElement('text', 'crsdftversion', get_string('course_version', 'block_curr_admin') . ':');
            $mform->setType('crsdftversion', PARAM_TEXT);
            $mform->addRule('crsdftversion', null, 'maxlength', 100);

            $this->add_action_buttons();
        }

        function set_data($default_values, $slashed=false) {

            $default_values = clone $default_values;
            parent::set_data($default_values, $slashed);
        }

    }
?>