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

require_once(CURMAN_DIRLOCATION . '/form/cmform.class.php');

/**
 * edit/add track form
 *
 * @copyright 12-Jun-2009 Olav Jordan <olav.jordan@remote-learner.ca>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class trackform extends cmform {
    /**
     * items in the form
     *
     * @uses $USER
     */
    public function definition() {
        global $USER;
        $this->set_data($this->_customdata['obj']);

        $mform = &$this->_form;

        $mform->addElement('hidden', 'id');

        $curs = array();
        if (!empty($USER->id)) {
            // TBD: and/or capability 'block/curr_admin:curriculum:edit|view' ?
            $contexts = get_contexts_by_capability_for_user('curriculum',
                            'block/curr_admin:track:create', $USER->id);
            $curs = curriculum_get_listing('name', 'ASC', 0, 0, '', '',
                        $contexts);
        }
        if (empty($this->_customdata['obj']->id)) {
            $curid_options = array();
            if (!empty($curs)) {
                foreach ($curs as $cur) {
                    $curid_options[$cur->id] = '(' . $cur->idnumber . ') ' . $cur->name;
                }
            }

            $mform->addElement('select', 'curid', get_string('curriculum', 'block_curr_admin') . ':', $curid_options);
            $mform->addRule('curid', get_string('required'), 'required', NULL, 'client');
            $mform->setHelpButton('curid', array('trackform/curriculum', get_string('curriculum', 'block_curr_admin'), 'block_curr_admin'));
        } else { // Track editing, do not allow the user to change curriculum
            $mform->addElement('static', 'curidstatic', get_string('curriculum', 'block_curr_admin') . ':', $curs[$this->_customdata['obj']->curid]->name);
            $mform->setHelpButton('curidstatic', array('trackform/curriculum', get_string('curriculum', 'block_curr_admin'), 'block_curr_admin'));

            $mform->addElement('hidden', 'curid');
        }

        $mform->addElement('text', 'idnumber', get_string('track_idnumber', 'block_curr_admin') . ':');
        $mform->setType('idnumber', PARAM_TEXT);
        $mform->addRule('idnumber', get_string('required'), 'required', NULL, 'client');
        $mform->addRule('idnumber', null, 'maxlength', 100);
        $mform->setHelpButton('idnumber', array('trackform/idnumber', get_string('track_idnumber', 'block_curr_admin'), 'block_curr_admin'));

        $mform->addElement('text', 'name', get_string('track_name', 'block_curr_admin') . ':');
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'maxlength', 255);
        $mform->addRule('name', get_string('required'), 'required', NULL, 'client');
        $mform->setHelpButton('name', array('trackform/name', get_string('track_name', 'block_curr_admin'), 'block_curr_admin'));

        $mform->addElement('textarea', 'description', get_string('track_description', 'block_curr_admin') . ':');
        $mform->setType('description', PARAM_CLEAN);
        $mform->setHelpButton('description', array('trackform/description', get_string('track_description', 'block_curr_admin'), 'block_curr_admin'));

        $mform->addElement('date_selector', 'startdate', get_string('track_startdate', 'block_curr_admin') . ':', array('optional'=>true));
        $mform->addElement('date_selector', 'enddate', get_string('track_enddate', 'block_curr_admin') . ':', array('optional'=>true));
        $mform->setHelpButton('startdate', array('trackform/startdate', get_string('startdate', 'block_curr_admin'), 'block_curr_admin'));

        if (!empty($this->_customdata['obj']->id)) {
            $trackassignobj = new trackassignmentclass(array('trackid' =>$this->_customdata['obj']->id));
        }

        // Only show auto-create checkbox if the track does not have any classes assigned
        if (!isset($trackassignobj) or 0 == $trackassignobj->count_assigned_classes_from_track()) {
            $mform->addElement('checkbox', 'autocreate', get_string('track_autocreate', 'block_curr_admin') . ':');
            $mform->setHelpButton('autocreate', array('trackform/autocreate', get_string('track_autocreate', 'block_curr_admin'), 'block_curr_admin'));
        }

        // custom fields
        $fields = field::get_for_context_level('track');
        $fields = $fields ? $fields : array();

        $lastcat = null;
        $context = isset($this->_customdata['obj']) && isset($this->_customdata['obj']->id)
            ? get_context_instance(context_level_base::get_custom_context_level('track', 'block_curr_admin'), $this->_customdata['obj']->id)
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

    /**
     *  make sure the start time is before the end time and the start date is before the end date for the class
     * @param array $data
     * @param mixed $files
     * @return array
     */
    function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if(!empty($data['startdate']) && !empty($data['enddate']) && !empty($data['disablestart']) && !empty($data['disableend'])) {
            if($data['startdate'] > $data['enddate']) {
                $errors['startdate'] = get_string('error_date_range', 'block_curr_admin');
            }
        }

        return $errors;
    }

    function get_data(){
        $data = parent::get_data();

        if (!empty($data)) {
            $mform =& $this->_form;

            if(!empty($mform->_submitValues['disablestart'])) {
                $data->startdate = 0;
            }

            if(!empty($mform->_submitValues['disableend'])) {
                $data->enddate = 0;
            }
        }

        return $data;
    }

    function freeze() {
        if (isset($this->_form->_elementIndex['autocreate'])) {
            $this->_form->removeElement('autocreate');
        }
        parent::freeze();
    }
}
?>
