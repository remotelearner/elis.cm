<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
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
 * @package    elis_program
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once(elispm::file('form/cmform.class.php'));

class certificateform extends cmform {
    /**
     * This function defines the formslib elements
     * @return void
     */
    public function definition() {
        global $CFG, $PAGE, $DB;

        $locationlabel = '';

        $mform =& $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'rec_id');
        $mform->setType('rec_id', PARAM_INT);
        $mform->addElement('hidden', 'entity_id');
        $mform->setType('entity_id', PARAM_INT);
        $mform->addElement('hidden', 'entity_type');
        $mform->setType('entity_type', PARAM_TEXT);

        if (!empty($this->_customdata['nosettingfound'])) {
            $mform->addElement('header', 'nosettingfound', get_string('nosettingfound', 'elis_program'));
        }

        // border drop down selection
        $name = get_string('cert_border_setting', 'elis_program');
        $borders = function_exists('cm_certificate_get_borders')
                   ? cm_certificate_get_borders()
                   : array('Fancy1-blue.jpg'  => 'Fancy1-blue',
                           'Fancy1-green.jpg' => 'Fancy1-green',
                           'Fancy2-black.jpg' => 'Fancy2-black',
                           'Fancy2-brown.jpg' => 'Fancy2-brown',
                           ''                 => 'None'); // for testing

        $mform->addElement('select', 'cert_border', $name, $borders);
        $mform->addHelpButton('cert_border', 'cert_border_setting', 'elis_program');

        // Seal drop down selection
        $name = get_string('cert_seal_setting', 'elis_program');
        $seals = function_exists('cm_certificate_get_seals')
                 ? cm_certificate_get_seals()
                 : array('Fancy.png' => 'Fancy', 'Logo.png' => 'Logo',
                         'Plain.png' => 'Plain', 'Quality.png' => 'Quality',
                         'Teamwork.png' => 'Teamwork', '' => 'None'); // for testing

        $mform->addElement('select', 'cert_seal', $name, $seals);
        $mform->addHelpButton('cert_seal', 'cert_seal_setting', 'elis_program');

        // Template drop down selection
        $name = get_string('certificate_template_file', 'elis_program');
        $templates = cm_certificate_get_templates();

        $mform->addElement('select', 'cert_template', $name, $templates);
        $mform->setDefault('cert_template', 'crsentitydefault.php');
        $mform->addHelpButton('cert_template', 'certificate_template_file', 'elis_program');

        $name = get_string('cert_enable', 'elis_program');
        $desc = '&nbsp;'.get_string('cert_enable_desc', 'elis_program');
        $mform->addElement('advcheckbox', 'disable', $name, $desc, array('group' => 1), array(1, 0));
        $mform->setDefault('disable', 1);

        $submitlabel  = get_string('savechanges');

        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', $submitlabel);
        $buttonarray[] = &$mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
    }
}