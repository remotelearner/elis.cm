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

defined('MOODLE_INTERNAL') || die();

require_once elispm::file('form/cmform.class.php');

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

        $fields = field::get_for_context_level('track');

        foreach ($fields as $rec) {
            $field = new field($rec);
            if(strcmp($field->datatype,"num") == 0) {
                $fieldname = "field_$field->shortname";
                if(isset($this->_customdata['obj']->$fieldname)) {
                    $formatnum = $field->format_number($this->_customdata['obj']->$fieldname);
                    $this->_customdata['obj']->$fieldname = $formatnum;
                }
            }
        }

        $this->set_data($this->_customdata['obj']);

        $mform = &$this->_form;

        $mform->addElement('hidden', 'id');

        $curs = array();
        if (!empty($USER->id)) {
            // TBD: and/or capability 'elis/program:track_edit|view' ?
            // This is necessary for creating a new track but will prevent a parent programs from appearing
            // when the user has track edit permissions but not track creation permission -- ELIS-5954
            $contexts = get_contexts_by_capability_for_user('curriculum', 'elis/program:track_create', $USER->id);
            $curs = curriculum_get_listing('name', 'ASC', 0, 0, '', '', $contexts);
        }
        if (empty($this->_customdata['obj']->id)) {
            $curid_options = array();
            if (!empty($curs)) {
                foreach ($curs as $cur) {
                    $curid_options[$cur->id] = '(' . $cur->idnumber . ') ' . $cur->name;
                }
            }

            $mform->addElement('select', 'curid', get_string('curriculum', 'elis_program') . ':', $curid_options);
            $mform->addRule('curid', get_string('required'), 'required', NULL, 'client');
            $mform->addHelpButton('curid','trackform:curriculum_curid', 'elis_program');
        } else { // Track editing, do not allow the user to change curriculum
            // Make sure that the parent program for this track is always included otherwise the display is messed up
            // and hitting the form Cancel button causes a DB error -- ELIS-5954
            $track = new track($this->_customdata['obj']->id);

            $curs = curriculum_get_listing('name', 'ASC', 0, 0, $track->curriculum->name);

            $mform->addElement('static', 'curidstatic', get_string('curriculum', 'elis_program') . ':', $curs[$this->_customdata['obj']->curid]->name);
            $mform->addHelpButton('curidstatic', 'trackform:curriculum_curidstatic', 'elis_program');

            $mform->addElement('hidden', 'curid');
        }

        $mform->addElement('text', 'idnumber', get_string('track_idnumber', 'elis_program') . ':');
        $mform->setType('idnumber', PARAM_TEXT);
        $mform->addRule('idnumber', get_string('required'), 'required', NULL, 'client');
        $mform->addRule('idnumber', null, 'maxlength', 100);
        $mform->addHelpButton('idnumber', 'trackform:track_idnumber', 'elis_program');

        $mform->addElement('text', 'name', get_string('track_name', 'elis_program') . ':');
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'maxlength', 255);
        $mform->addRule('name', get_string('required'), 'required', NULL, 'client');
        $mform->addHelpButton('name', 'trackform:track_name', 'elis_program');

        $mform->addElement('textarea', 'description', get_string('track_description', 'elis_program') . ':');
        $mform->setType('description', PARAM_CLEAN);
        $mform->addHelpButton('description', 'trackform:track_description', 'elis_program');

        $mform->addElement('date_selector', 'startdate', get_string('track_startdate', 'elis_program') . ':', array('optional'=>true));
        $mform->addElement('date_selector', 'enddate', get_string('track_enddate', 'elis_program') . ':', array('optional'=>true));
        $mform->addHelpButton('startdate', 'trackform:track_startdate', 'elis_program');

        if (!empty($this->_customdata['obj']->id)) {
            $trackassignobj = new trackassignment(array('trackid' =>$this->_customdata['obj']->id));
        }

        // Only show auto-create checkbox if the track does not have any classes assigned
        if (!isset($trackassignobj) || 0 == $trackassignobj->count_assigned_classes_from_track()) {
            $mform->addElement('checkbox', 'autocreate', get_string('track_autocreate', 'elis_program') . ':');
            $mform->addHelpButton('autocreate', 'trackform:track_autocreate', 'elis_program');
        }

        // custom fields
        $this->add_custom_fields('track', 'elis/program:track_edit', 'elis/program:track_view', 'curriculum');

        $this->add_action_buttons();
    }

    function check_unique($table, $field, $value, $id) {
        global $DB;
        return !$DB->record_exists_select($table, "$field = ? AND id <> ?", array($value, $id));
    }

    /**
     *  make sure the start time is before the end time and the start date is before the end date for the class
     * @param array $data
     * @param mixed $files
     * @return array
     */
    function validation($data, $files) {
        $errors = parent::validation($data, $files);


        if (!empty($data['idnumber'])) {
            if (!$this->check_unique(track::TABLE, 'idnumber', $data['idnumber'], $data['id'])) {
                $errors['idnumber'] = get_string('badidnumber', 'elis_program');
            }
        }

        if(!empty($data['startdate']) && !empty($data['enddate']) && !empty($data['disablestart']) && !empty($data['disableend'])) {
            if($data['startdate'] > $data['enddate']) {
                $errors['startdate'] = get_string('error_date_range', 'elis_program');
            }
        }

        $errors += parent::validate_custom_fields($data, 'track');

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
