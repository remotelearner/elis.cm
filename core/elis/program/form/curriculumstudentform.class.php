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

require_once elispm::file('form/selectionform.class.php');

class assigncurriculumform extends selectionform {
    function get_submit_button_name() {
        return ucfirst(get_string('assign','elis_program'));
    }

    function definition() {
        $mform =& $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->addElement('hidden', '_assign', 'assign');
        $mform->addElement('hidden', 's', 'stucur');
        $mform->addElement('hidden', '_selection');
        $this->add_action_buttons(false, $this->get_submit_button_name());
    }
}

class unassigncurriculumform extends selectionform {
    function get_submit_button_name() {
        return ucfirst(get_string('unassign','elis_program'));
    }

    function definition() {
        $mform =& $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->addElement('hidden', '_assign', 'unassign');
        $mform->addElement('hidden', 's', 'stucur');
        $mform->addElement('hidden', '_selection');
        $this->add_action_buttons(false, $this->get_submit_button_name());
    }
}

class assignstudentform extends selectionform {
    function get_submit_button_name() {
        return ucfirst(get_string('assign','elis_program'));
    }

    function definition() {
        $mform =& $this->_form;

        $id = required_param('id', PARAM_INT);

        if (curriculumpage::can_enrol_into_curriculum($id)) {
            $mform->addElement('hidden', 'id');
            $mform->addElement('hidden', '_assign', 'assign');
            $mform->addElement('hidden', 's', 'curstu');
            $mform->addElement('hidden', '_selection');
            $this->add_action_buttons(false, $this->get_submit_button_name());
        }
    }
}

class unassignstudentform extends selectionform {
    function get_submit_button_name() {
        return ucfirst(get_string('unassign','elis_program'));
    }

    function definition() {
        $mform =& $this->_form;

        $id = required_param('id', PARAM_INT);

        if (curriculumpage::can_enrol_into_curriculum($id)) {
            $mform->addElement('hidden', 'id');
            $mform->addElement('hidden', '_assign', 'unassign');
            $mform->addElement('hidden', 's', 'curstu');
            $mform->addElement('hidden', '_selection');
            $this->add_action_buttons(false, $this->get_submit_button_name());
        }
    }
}
