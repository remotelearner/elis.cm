<?php 
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2010 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2008-2010 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(CURMAN_DIRLOCATION . '/form/cmform.class.php');

/**
 * the confirmation form for when a student is tries to enrol into a full class
 *
 * form is given after a full class is chosen and before the action_savenew in the
 * coursecatalogpage under action_confirmsave
 *
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrolconfirmform extends cmform {
    /**
     * items in the form
     */
    public function definition() {
        parent::definition();
        
        $mform = &$this->_form;

        $data = $this->_customdata[0];

        $mform->addElement('hidden', 'id');

        $limit_group[] = $mform->addElement('static', 'lbl_classlimit', get_string('class_limit', 'block_curr_admin') . ':', $data->limit);

        $mform->addElement('static', 'lbl_enroled', get_string('enroled', 'block_curr_admin') . ': ', $data->enroled);
        $mform->addElement('static', 'lbl_num_waitlist', get_string('num_waitlist', 'block_curr_admin') . ': ', $data->waitlisted);
        
        $mform->addElement('static', 'lbl_enrol_confirmation', '', get_string('enrol_confirmation', 'block_curr_admin', $data->a));

        $this->add_action_buttons(true, get_string('enrol', 'block_curr_admin'));
    }
}
?>
