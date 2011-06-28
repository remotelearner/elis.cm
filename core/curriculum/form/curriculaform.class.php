<?php
/**
 *  ELIS(TM): Enterprise Learning Intelligence Suite
 *
 *  Copyright (C) 2008-2010 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *  @package    elis
 *  @subpackage curriculummanagement
 *  @author     Remote-Learner.net Inc
 *  @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 *  @copyright  (C) 2008-2010 Remote Learner.net Inc http://www.remote-learner.net
 */

require_once(CURMAN_DIRLOCATION . '/form/cmform.class.php');
require_once(CURMAN_DIRLOCATION . '/lib/lib.php');


/**
 * the form element for curriculum
 *
 * @author Olav Jordan
 */
class cmCurriculaForm extends cmform {
    /**
     * defines items in the form
     */
    public function definition() {
        global $CURMAN;

        $configData = array('title');

        if($this->_customdata['obj']) {
            // FIXME: This is probably not be the right place for set_data.  Move it.
            $this->set_data($this->_customdata['obj']);
        }

        $mform =& $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->addElement('hidden', 'courseid');

        $mform->addElement('text', 'idnumber', get_string('curriculum_idnumber', 'block_curr_admin') . ':');
        $mform->setType('idnumber', PARAM_TEXT);
        $mform->addRule('idnumber', null, 'required', null, 'client');
        $mform->addRule('idnumber', null, 'maxlength', 100);
        $mform->setHelpButton('idnumber', array('curriculaform/idnumber', get_string('curriculum_idnumber', 'block_curr_admin'), 'block_curr_admin'));

        $mform->addElement('text', 'name', get_string('curriculum_name', 'block_curr_admin') . ':');
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', null, 'maxlength', 64);
        $mform->setHelpButton('name', array('curriculaform/name', get_string('curriculum_name', 'block_curr_admin'), 'block_curr_admin'));

        $attributes = array('rows'=>'2', 'cols'=>'40');
        $mform->addElement('textarea', 'description', get_string('curriculum_description', 'block_curr_admin') . ':', $attributes);
        $mform->setType('description', PARAM_CLEAN);
        $mform->setHelpButton('description', array('curriculaform/description', get_string('curriculum_description', 'block_curr_admin'), 'block_curr_admin'));

        $mform->addElement('text', 'reqcredits', get_string('required_credits', 'block_curr_admin') . ':');
        $mform->setType('reqcredits', PARAM_NUMBER);
        $mform->addRule('reqcredits', null, 'maxlength', 10);
        $mform->setHelpButton('reqcredits', array('curriculaform/reqcredits', get_string('required_credits', 'block_curr_admin'), 'block_curr_admin'));

        $choices = range(0, 10);
        $mform->addElement('select', 'priority', get_string('priority', 'block_curr_admin') . ':', $choices);
        $mform->setHelpButton('priority', array('curriculaform/priority', get_string('priority', 'block_curr_admin'), 'block_curr_admin'));

        //because moodle forms will not allow headers within headers
        $mform->addElement('header', 'editform', get_string('time_settings', 'block_curr_admin'));

        // Time to complete
        $mform->addElement('text', 'timetocomplete', get_string('time_to_complete', 'block_curr_admin') . ':');
        $mform->setType('timetocomplete', PARAM_TEXT);
        $mform->addRule('timetocomplete', null, 'maxlength', 64);
        $mform->setHelpButton('timetocomplete', array('curriculaform/timetocomplete', get_string('time_to_complete', 'block_curr_admin'), 'block_curr_admin'));

        //$mform->addElement('html', '<small>' . get_string('tips_time_to_complete', 'block_curr_admin') . '</small><br /><br />');

        // Frequency (only display if curriculum expiration is currently enabled).
        if (!empty($CURMAN->config->enable_curriculum_expiration)) {
            $mform->addElement('text', 'frequency', get_string('expiration', 'block_curr_admin') . ':');
            $mform->setType('frequency', PARAM_TEXT);
            $mform->addRule('frequency', null, 'maxlength', 64);
            $mform->setHelpButton('frequency', array('curriculaform/frequency', get_string('expiration', 'block_curr_admin'), 'block_curr_admin'));
        } else {
            $mform->addElement('hidden', 'frequency');
        }

        //$mform->addElement('html', '<small>' . get_string('tips_time_to_redo', 'block_curr_admin') . '</small><br /><br />');

        $mform->addElement('static', '', '', '<small>'.get_string('tips_time_format', 'block_curr_admin').'</small>');

        // custom fields
        $fields = field::get_for_context_level('curriculum');
        $fields = $fields ? $fields : array();

        $lastcat = null;
        $context = isset($this->_customdata['obj']) && isset($this->_customdata['obj']->id)
            ? get_context_instance(context_level_base::get_custom_context_level('curriculum', 'block_curr_admin'), $this->_customdata['obj']->id)
            : get_context_instance(CONTEXT_SYSTEM);
        require_once CURMAN_DIRLOCATION.'/plugins/manual/custom_fields.php';
        foreach ($fields as $rec) {
            $field = new field($rec);
            if (!isset($field->owners['manual'])) {
                continue;
            }
            if ($lastcat != $rec->categoryid) {
                $lastcat = $rec->categoryid;
                $mform->addElement('header', "category_{$lastcat}", htmlspecialchars($rec->categoryname));
            }
            manual_field_add_form_element($this, $context, $field);
        }

        $this->add_action_buttons();
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if(!empty($data['timetocomplete'])) {
            $datedelta = new datedelta($data['timetocomplete']);

            if(!$datedelta->getDateString()) {
                $errors['timetocomplete'] = get_string('error_not_timeformat', 'block_curr_admin');
            }
        }

        if(!empty($data['frequency'])) {
            $datedelta = new datedelta($data['frequency']);

            if(!$datedelta->getDateString()) {
                $errors['frequency'] = get_string('error_not_durrationformat', 'block_curr_admin');
            }
        }

        return $errors;
    }

    /**
     * Overridden to specially handle timetocomplete and frequency fields.
     */
    function get_data($slashed=false) {
        $data = parent::get_data($slashed);

        if(!empty($data)) {
            $datedelta = new datedelta($data->timetocomplete);
            $data->timetocomplete = $datedelta->getDateString();

            $datedelta = new datedelta($data->frequency);
            $data->frequency = $datedelta->getDateString();
        }

        return $data;
    }
}
?>
