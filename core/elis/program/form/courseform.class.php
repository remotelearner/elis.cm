<?php
/**
 * Form used for editing / displaying a course
 *
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

require_once elispm::file('form/cmform.class.php');

class cmCourseForm extends cmform {
    public function definition() {
        global $CFG, $PAGE, $DB;

        $locationlabel = '';

        if (isset($this->_customdata['obj']->id)) {
            $id = $this->_customdata['obj']->id;

            // TO-DO: this should probably be moved to a different location
            $template = coursetemplate::find(new field_filter('courseid', $id));
            if ($template->valid()) {
                $template = $template->current();
                $course = $DB->get_record('course', array('id'=>$template->location));
                if (!empty($course)) {
                    $locationlabel = $course->fullname . ' ' . $course->shortname;
                }
            } else {
                // use a blank template
                $template = new coursetemplate();
            }
        }

        $PAGE->requires->js('/elis/program/js/courseform.js');

        $this->set_data($this->_customdata['obj']);

        $mform =& $this->_form;

        $mform->addElement('hidden', 'id');

        $mform->addElement('text', 'name', get_string('course_name', 'elis_program') . ':');
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required_field', 'elis_program', get_string('course_name', 'elis_program')), 'required', null, 'client');
        $mform->addRule('name', null, 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'courseform:course_name', 'elis_program');

        $mform->addElement('text', 'code', get_string('course_code', 'elis_program') . ':');
        $mform->setType('code', PARAM_TEXT);
        $mform->addRule('code', null, 'maxlength', 100, 'client');
        $mform->addHelpButton('code', 'courseform:course_code', 'elis_program');

        $mform->addElement('text', 'idnumber', get_string('course_idnumber', 'elis_program') . ':');
        $mform->setType('idnumber', PARAM_TEXT);
        $mform->addRule('idnumber', get_string('required_field', 'elis_program', get_string('course_idnumber', 'elis_program')), 'required', null, 'client');
        $mform->addRule('idnumber', null, 'maxlength', 100, 'client');
        $mform->addHelpButton('idnumber', 'courseform:course_idnumber', 'elis_program');

        $attributes = array('cols'=>40, 'rows'=>2);
        $mform->addElement('textarea', 'syllabus', get_string('course_syllabus', 'elis_program') . ':', $attributes);
        $mform->setType('syllabus', PARAM_CLEAN);
        $mform->addHelpButton('syllabus', 'courseform:course_syllabus', 'elis_program');

        $mform->addElement('text', 'lengthdescription', get_string('length_description', 'elis_program'));
        $mform->setType('lengthdescription', PARAM_TEXT);
        $mform->addRule('lengthdescription', null, 'maxlength', 100, 'client');
        $mform->addHelpButton('lengthdescription', 'courseform:length_description', 'elis_program');

        $mform->addElement('text', 'length', get_string('duration', 'elis_program') . ':');
        $mform->setType('length', PARAM_INT);
        $mform->addHelpButton('length', 'courseform:duration', 'elis_program');

        $mform->addElement('text', 'credits', get_string('credits', 'elis_program') . ':');
        $mform->setType('credits', PARAM_TEXT);
        $mform->addRule('credits', null, 'maxlength', 10, 'client');
        $mform->addHelpButton('credits', 'courseform:credits', 'elis_program');

        $grades = range(0,100,1);
        $mform->addElement('select', 'completion_grade', get_string('completion_grade', 'elis_program') . ':', $grades);
        $mform->addHelpButton('completion_grade', 'courseform:completion_grade', 'elis_program');

        $mform->addElement('text', 'cost', get_string('cost', 'elis_program') . ':');
        $mform->setType('cost', PARAM_TEXT);
        $mform->addRule('cost', null, 'maxlength', 10, 'client');
        $mform->addHelpButton('cost', 'courseform:cost', 'elis_program');

        $mform->addElement('text', 'version', get_string('course_version', 'elis_program') . ':');
        $mform->setType('version', PARAM_TEXT);
        $mform->addRule('version', null, 'maxlength', 100, 'client');
        $mform->addHelpButton('version', 'courseform:course_version', 'elis_program');

        // Print form items for course template browsing

        $mform->addElement('html', '<br />');
        $mform->addElement('hidden', 'templateclass', 'moodlecourseurl', array('id' => 'id_templateclass'));
        if (empty($locationlabel) || optional_param('action', '', PARAM_CLEAN) != 'view') {
            $mform->addElement('text', 'locationlabel', get_string('coursetemplate', 'elis_program'), array('readonly' => 'readonly', 'value' => $locationlabel));
            $mform->setType('locationlabel', PARAM_TEXT);
            $mform->addHelpButton('locationlabel', 'courseform:coursetemplate', 'elis_program');
        } else {
            $mform->addElement('static', 'locationlabellink', get_string('coursetemplate', 'elis_program').':',  "<a href=\"{$CFG->wwwroot}/course/view.php?id={$course->id}\">{$locationlabel}</a>");
            $mform->addHelpButton('locationlabellink', 'courseform:coursetemplate', 'elis_program');
        }

        if (empty($id)) {
            $mform->addElement('hidden', 'location', '', array('id'=>'id_location'));
            $mform->addElement('hidden', 'temptype', '', array('id'=>'tempid'));
        } else {
            $mform->addElement('hidden', 'location', $template->location, array('id'=>'id_location'));
            $mform->addElement('hidden', 'tempid', $template->id, array('id'=>'tempid'));
        }

        $templateButtons = array();
        $templateButtons[] =& $mform->createElement('button', 'submit1', get_string('browse', 'elis_program'), array('onClick'=>'openNewWindow();'));
        $templateButtons[] =& $mform->createElement('button', 'submit1', get_string('clear', 'elis_program'), array('onClick'=>'cleartext();'));
        $mform->addGroup($templateButtons, 'templateButtons', '', '', false);

        // Multi select box for choosing curricula (only when creating a course)
        if(!isset($this->_customdata['obj'])) {
            $mform->addElement('html', '<br />');

            $cur_listings = curriculum_get_listing();
            $cur_listings = $cur_listings ? $cur_listings : array();

            $values = array();
            foreach($cur_listings as $key=>$val){
                $values[$key] = $val->name;
            }

            $strcur = get_string("curricula", "elis_program");

            // Set an explicit width if the select box will have no elements.
            $attributes = empty($values) ? array('style' => 'width: 200px;') : array();

            $multiSelect =& $mform->addElement('select', 'curriculum', $strcur . ':', $values, $attributes);
            $multiSelect->setMultiple(true);
            $mform->addHelpButton('curriculum', 'courseform:curriculum', 'elis_program');

            $mform->addElement('submit', 'makecurcourse', get_string('makecurcourse', 'elis_program'));
        }

        // custom fields
        $this->add_custom_fields('course', 'elis/program:course_edit',
                                 'elis/program:course_view');

        $this->add_action_buttons();

    }

    function freeze() {
        $this->_form->removeElement('templateButtons');

        // Add completion status information
        $obj = new course($this->_customdata['obj']);
        $counts = $obj->get_completion_counts();

        $counttext = "Passed: {$counts[2]}, Failed: {$counts[1]}, In Progress: {$counts[0]}";

        $this->_form->addElement('static', 'test', get_string('completion_status', 'elis_program'), $counttext);

        parent::freeze();
    }

    function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);
        $params = array($data['idnumber']);
        $sql = 'idnumber = ?';
        if ($data['id']) {
            $sql .= ' AND id != ?';
            $params[] = $data['id'];
        }
        if ($DB->record_exists_select(course::TABLE, $sql, $params)) {
            $errors['idnumber'] = get_string('idnumber_already_used', 'elis_program');
        }

        $errors += parent::validate_custom_fields($data, 'course');

        return $errors;
    }
}

/**
 * Completion form class.
 *
 * @package    elis
 * @copyright  2013 Remote-Learner (GPL, BJB)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completionform extends cmform {

    /**
     * Completion form definition method
     */
    public function definition() {
        require_once(elis::lib('form/gradebookidnumber.php'));

        $elem = $this->_customdata['elem'];
        $course = $this->_customdata['course'];

        $this->set_data($this->_customdata['elem']);
        $this->set_data($this->_customdata);

        $mform =& $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->addElement('hidden', 'elemid');

        $options = array(
            'lockcourse' => 1,
            'nocoursestring' => get_string('selecttemplate', 'elis_program'),
        );
        if ($course->coursetemplate->valid()) {
            $template = $course->coursetemplate->current();
            $options['courseid'] = $template->location;
        }
        $mform->addElement(elis_gradebook_idnumber_selector::NAME, 'idnumber', get_string('course_idnumber', 'elis_program') . ':', $options);
        $mform->setType('idnumber', PARAM_TEXT);
        $mform->addRule('idnumber', null, 'maxlength', 100, 'client');
        $mform->addRule('idnumber', null, 'required', null, 'client');
        $mform->addHelpButton('idnumber', 'completionform:course_idnumber', 'elis_program');

        $mform->addElement('text', 'name', get_string('course_name', 'elis_program'));
        $mform->setType('name', PARAM_CLEAN);
        $mform->addRule('name', null, 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'completionform:course_name', 'elis_program');

        $attributes = array('rows'=>2, 'cols'=>40);
        $mform->addElement('textarea', 'description', get_string('course_syllabus', 'elis_program') . ':', $attributes);
        $mform->setType('description', PARAM_CLEAN);
        $mform->addHelpButton('description', 'completionform:course_syllabus', 'elis_program');

        $grades = range(0,100,1);
        $mform->addElement('select', 'completion_grade', get_string('completion_grade', 'elis_program') . ':', $grades);
        $mform->addHelpButton('completion_grade', 'completionform:completion_grade', 'elis_program');

        $mform->addElement('checkbox', 'required', get_string('required', 'elis_program') . ':');
        $mform->addHelpButton('required', 'completionform:required', 'elis_program');

        $this->add_action_buttons();
    }

    /**
     * Completion form validation method
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @uses  $DB
     * @return array associative array of error messages, indexed by form element
     */
    function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);
        $course = $this->_customdata['course'];
        $params = array($course->id, $data['idnumber']);
        $sql = 'courseid = ? AND idnumber = ?';
        if (!empty($data['elemid'])) {
            $sql .= ' AND id != ?';
            $params[] = $data['elemid'];
        }
        if ($DB->record_exists_select(coursecompletion::TABLE, $sql, $params)) {
            $errors['idnumber'] = get_string('idnumber_already_used', 'elis_program');
        }

        return $errors;
    }
}
