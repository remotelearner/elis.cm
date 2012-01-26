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
 * @subpackage curriculummanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

global $CFG;
require_once($CFG->dirroot.'/lib/formslib.php');

class fieldfrommoodle extends moodleform {
    function definition() {
        $mform =& $this->_form;

        /// Add some extra hidden fields
        $mform->addElement('hidden', 's', 'field');
        $mform->setType('s', PARAM_ACTION);
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'action', 'frommoodle');
        $mform->setType('action', PARAM_ACTION);

        $categories =  field_category::get_all();
        $choices = array();
        foreach ($categories as $category) {
            $choices[$category->id] = $category->name;
        }
        $mform->addElement('select', 'categoryid', get_string('profilecategory', 'admin'), $choices);

        $choices = array();
        $choices[field::no_sync] = get_string('field_no_sync', 'block_curr_admin');
        $choices[field::sync_to_moodle] = get_string('field_sync_to_moodle', 'block_curr_admin');
        $choices[field::sync_from_moodle] = get_string('field_sync_from_moodle', 'block_curr_admin');
        $mform->addElement('select', 'syncwithmoodle', get_string('field_syncwithmoodle', 'block_curr_admin'), $choices);

        $this->add_action_buttons(true);
    }
}

?>
