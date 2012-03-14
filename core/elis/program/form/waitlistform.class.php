<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
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

require_once elispm::file('form/cmform.class.php'); // TBD
require_once elispm::file('form/selectionform.class.php');

class waitlistaddform extends cmform {

    public function definition() {
        global $DB;
        parent::definition();

        $mform = &$this->_form;

        if(!empty($this->_customdata['students'])) {
            $student_list = $this->_customdata['students'];

            $mform->addElement('header', 'waitlistaddform', get_string('waitinglistform_title', 'elis_program'));

            foreach($student_list as $student) {
                $mform->addElement('hidden', 'userid[' . $student->userid . ']', $student->userid);
                $mform->addElement('hidden', 'classid[' . $student->userid . ']', $student->classid);
                $mform->addElement('hidden', 'enrolmenttime[' . $student->userid . ']', $student->enrolmenttime);

                $enrol_options = array();
                $enrol_options[] = $mform->createElement('radio', 'enrol[' . $student->userid . ']', '', get_string('yes'), 1);
                $enrol_options[] = $mform->createElement('radio', 'enrol[' . $student->userid . ']', '', get_string('no'), 0);

                $context = get_context_instance(CONTEXT_SYSTEM);

                if(has_capability('elis/program:overrideclasslimit', $context)) {
                    $mform->addElement('hidden', 'grade[' . $student->userid . ']', $student->grade);
                    $mform->addElement('hidden', 'credits[' . $student->credits . ']', $student->credits);
                    $mform->addElement('hidden', 'locked[' . $student->locked . ']', $student->locked);

                    $enrol_options[] = $mform->createElement('radio', 'enrol[' . $student->userid . ']', '', get_string('over_enrol', 'elis_program'), 2);
                }

                $user = $DB->get_record(user::TABLE, array('id' => $student->userid));
                $user->name = fullname($user);
                $mform->addGroup($enrol_options, 'options[' . $student->userid . ']', get_string('add_to_waitinglist', 'elis_program', $user), array('&nbsp;&nbsp;&nbsp;'), false);
            }
        } else if(!empty($this->_customdata['student_ids'])) {
            $student_id = $this->_customdata['student_ids'];

            foreach($student_id as $id=>$student) {
                $mform->addElement('hidden', 'userid[' . $id . ']');
                $mform->addElement('hidden', 'classid[' . $id . ']');
                $mform->addElement('hidden', 'enrolmenttime[' . $id . ']');

                $enrol_options = array();
                $enrol_options[] = $mform->createElement('radio', 'enrol[' . $id . ']', '', get_string('yes'), 1);
                $enrol_options[] = $mform->createElement('radio', 'enrol[' . $id . ']', '', get_string('no'), 0);

                $context = get_context_instance(CONTEXT_SYSTEM);

                if(has_capability('elis/program:overrideclasslimit', $context)) {
                    $enrol_options[] = $mform->createElement('radio', 'enrol[' . $id . ']', '', get_string('over_enrol', 'elis_program'), 2);
                }

                $name = 'no name';
                $mform->addGroup($enrol_options, 'options[' . $id . ']', $name, '', false);
            }
        }

        $mform->addElement('hidden', 'id');

        $mform->addElement('submit', 'submitbutton', 'Save');
    }
}

class waitlisteditform extends selectionform {
    function definition() {
        global $CFG;

        $mform =& $this->_form;

        $mform->addElement('hidden', 'id');
        $actions = array('remove' => get_string('remove'),
                         'overenrol' => get_string('over_enrol', 'elis_program'));
        $mform->addElement('select', 'do', get_string('withselectedusers'), $actions);

        parent::definition();
    }
}

