<?php //$id: Exp $
/*
*  ELIS(TM): Enterprise Learning Intelligence Suite
*
*  Copyright (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
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
*  @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
*/
    require_once(CURMAN_DIRLOCATION . '/form/cmform.class.php');

    class cmCourseForm extends cmform {
        public function definition() {
            if (isset($this->_customdata['obj']->id)) {
                $id = $this->_customdata['obj']->id;
            }

            global $CFG, $CURMAN;

            require_js($CFG->wwwroot . '/curriculum/js/courseform.js');

            $this->set_data($this->_customdata['obj']);

            $mform =& $this->_form;

            $mform->addElement('hidden', 'id');

            $mform->addElement('text', 'name', get_string('course_name', 'block_curr_admin') . ':');
            $mform->setType('name', PARAM_TEXT);
            $mform->addRule('name', get_string('required_field', 'block_curr_admin', get_string('course_name', 'block_curr_admin')), 'required', null, 'client');
            $mform->addRule('name', null, 'maxlength', 255);
            $mform->setHelpButton('name', array('courseform/name', get_string('course_name', 'block_curr_admin'), 'block_curr_admin'));

            $mform->addElement('text', 'code', get_string('course_code', 'block_curr_admin') . ':');
            $mform->setType('code', PARAM_TEXT);
            $mform->addRule('code', null, 'maxlength', 100);
            $mform->setHelpButton('code', array('courseform/code', get_string('course_code', 'block_curr_admin'), 'block_curr_admin'));

            $mform->addElement('text', 'idnumber', get_string('course_idnumber', 'block_curr_admin') . ':');
            $mform->setType('idnumber', PARAM_TEXT);
            $mform->addRule('idnumber', get_string('required_field', 'block_curr_admin', get_string('course_idnumber', 'block_curr_admin')), 'required', null, 'client');
            $mform->addRule('idnumber', null, 'maxlength', 100);
            $mform->setHelpButton('idnumber', array('courseform/idnumber', get_string('course_idnumber', 'block_curr_admin'), 'block_curr_admin'));

            $attributes = array('cols'=>40, 'rows'=>2);
            $mform->addElement('textarea', 'syllabus', get_string('course_syllabus', 'block_curr_admin') . ':', $attributes);
            $mform->setType('syllabus', PARAM_CLEAN);
            $mform->setHelpButton('syllabus', array('courseform/description', get_string('course_syllabus', 'block_curr_admin'), 'block_curr_admin'));

            $mform->addElement('text', 'lengthdescription', get_string('length_description', 'block_curr_admin'));
            $mform->setType('lengthdescription', PARAM_TEXT);
            $mform->addRule('lengthdescription', null, 'maxlength', 100);
            $mform->setHelpButton('lengthdescription', array('courseform/lengthdescription', get_string('length_description', 'block_curr_admin'), 'block_curr_admin'));

            $mform->addElement('text', 'length', get_string('duration', 'block_curr_admin') . ':');
            $mform->setType('length', PARAM_INT);
            $mform->setHelpButton('length', array('courseform/duration', get_string('duration', 'block_curr_admin'), 'block_curr_admin'));

            $mform->addElement('text', 'credits', get_string('credits', 'block_curr_admin') . ':');
            $mform->setType('credits', PARAM_TEXT);
            $mform->addRule('credits', null, 'maxlength', 10);
            $mform->setHelpButton('credits', array('courseform/credits', get_string('credits', 'block_curr_admin'), 'block_curr_admin'));

            $grades = range(0,100,1);
            $mform->addElement('select', 'completion_grade', get_string('completion_grade', 'block_curr_admin') . ':', $grades);
            $mform->setHelpButton('completion_grade', array('courseform/completion_grade', get_string('completion_grade', 'block_curr_admin'), 'block_curr_admin'));

            $environments = array('- ' . get_string('none', 'block_curr_admin') . ' -');
            $envs = environment_get_listing();

            if(empty($envs)) {
                $envs = array();
            }

            foreach($envs as $e){
                $environments[$e->id] = $e->name;
            }

            $mform->addElement('select', 'environmentid', get_string('environment', 'block_curr_admin'), $environments);
            $mform->setHelpButton('environmentid', array('courseform/environmentid', get_string('environment', 'block_curr_admin'), 'block_curr_admin'));

            $mform->addElement('text', 'cost', get_string('cost', 'block_curr_admin') . ':');
            $mform->setType('cost', PARAM_TEXT);
            $mform->addRule('cost', null, 'maxlength', 10);
            $mform->setHelpButton('cost', array('courseform/cost', get_string('cost', 'block_curr_admin'), 'block_curr_admin'));

            $mform->addElement('text', 'version', get_string('course_version', 'block_curr_admin') . ':');
            $mform->setType('version', PARAM_TEXT);
            $mform->addRule('version', null, 'maxlength', 100);
            $mform->setHelpButton('version', array('courseform/version', get_string('course_version', 'block_curr_admin'), 'block_curr_admin'));

            // Print form items for course template browsing

            $mform->addElement('html', '<br />');
            $mform->addElement('hidden', 'templateclass', 'moodlecourseurl', array('id'=>'id_templateclass'));

            if (optional_param('action', '', PARAM_CLEAN) == 'view' && !empty($this->_customdata['obj']->locationlabel)) {
                $mform->addElement('static', 'locationlabellink', get_string('coursetemplate', 'block_curr_admin').':',  "<a href=\"{$CFG->wwwroot}/course/view.php?id={$this->_customdata['obj']->locationid}\">{$this->_customdata['obj']->locationlabel}</a>");
                $mform->setHelpButton('locationlabellink', array('courseform/coursetemplate', get_string('coursetemplate', 'block_curr_admin'), 'block_curr_admin'));
            } else {
                $mform->addElement('text', 'locationlabel', get_string('coursetemplate', 'block_curr_admin').':', array('readonly'=>'readonly'));
                $mform->setType('locationlabel', PARAM_TEXT);
                $mform->setHelpButton('locationlabel', array('courseform/coursetemplate', get_string('coursetemplate', 'block_curr_admin'), 'block_curr_admin'));
            }

            if(empty($id)) {
                $mform->addElement('hidden', 'location', '', array('id'=>'id_location'));
                $mform->addElement('hidden', 'temptype', '', array('id'=>'tempid'));
            } else {
                global $CURMAN;

                $template = new coursetemplate($id);

                $mform->addElement('hidden', 'location', $template->location, array('id'=>'id_location'));

                $mform->addElement('hidden', 'tempid', $template->id, array('id'=>'tempid'));
            }

            $templateButtons = array();
            $templateButtons[] =& $mform->createElement('button', 'submit1', get_string('browse', 'block_curr_admin'), array('onClick'=>'openNewWindow();'));
            $templateButtons[] =& $mform->createElement('button', 'submit1', get_string('clear', 'block_curr_admin'), array('onClick'=>'cleartext();'));
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

                $strcur = get_string("curricula", "block_curr_admin");

                // Set an explicit width if the select box will have no elements.
                $attributes = empty($values) ? array('style' => 'width: 200px;') : array();

                $multiSelect =& $mform->addElement('select', 'curriculum', $strcur . ':', $values, $attributes);
                $multiSelect->setMultiple(true);
                $mform->setHelpButton('curriculum', array('courseform/curriculum', get_string('curriculum', 'block_curr_admin'), 'block_curr_admin'));

                $mform->addElement('submit', 'makecurcourse', get_string('makecurcourse', 'block_curr_admin'));
            }

            // custom fields
            $fields = field::get_for_context_level('course');
            $fields = $fields ? $fields : array();

            $lastcat = null;
            $context = isset($this->_customdata['obj']) && isset($this->_customdata['obj']->id)
                ? get_context_instance(context_level_base::get_custom_context_level('course', 'block_curr_admin'), $this->_customdata['obj']->id)
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

        function freeze() {
            $this->_form->removeElement('templateButtons');

            // Add completion status information
            $counts = $this->_customdata['obj']->get_completion_counts();

            $counttext = "Passed: {$counts[2]}, Failed: {$counts[1]}, In Progress: {$counts[0]}";

            $this->_form->addElement('static', 'test', get_string('completion_status', 'block_curr_admin'), $counttext);

            parent::freeze();
        }

        function validation($data, $files) {
            global $CURMAN;
            $errors = parent::validation($data, $files);

            if ($CURMAN->db->record_exists_select(CRSTABLE, "idnumber = '{$data['idnumber']}'".($data['id'] ? " AND id != {$data['id']}" : ''))) {
                $errors['idnumber'] = get_string('idnumber_already_used', 'block_curr_admin');
            }

            return $errors;
        }
    }

    class completionform extends moodleform{
        public function definition() {
            $elem = $this->_customdata['elem'];

            $this->set_data($this->_customdata['elem']);
            $this->set_data($this->_customdata);

            $mform =& $this->_form;

            $mform->addElement('hidden', 'id');
            $mform->addElement('hidden', 'elemid');

            $mform->addElement('text', 'idnumber', get_string('course_idnumber', 'block_curr_admin') . ':');
            $mform->setType('idnumber', PARAM_TEXT);
            $mform->addRule('idnumber', null, 'maxlength', 100);
            $mform->addRule('idnumber', null, 'required', null, 'client');
            $mform->setHelpButton('idnumber', array('completionform/idnumber', get_string('course_idnumber', 'block_curr_admin'), 'block_curr_admin'));

            $mform->addElement('text', 'name', get_string('course_name', 'block_curr_admin'));
            $mform->setType('name', PARAM_TEXT);
            $mform->addRule('name', null, 'maxlength', 255);
            $mform->setHelpButton('name', array('completionform/name', get_string('course_name', 'block_curr_admin'), 'block_curr_admin'));

            $attributes = array('rows'=>2, 'cols'=>40);
            $mform->addElement('textarea', 'description', get_string('course_syllabus', 'block_curr_admin') . ':', $attributes);
            $mform->setType('description', PARAM_CLEAN);
            $mform->setHelpButton('description', array('completionform/description', get_string('course_syllabus', 'block_curr_admin'), 'block_curr_admin'));

            $grades = range(0,100,1);
            $mform->addElement('select', 'completion_grade', get_string('completion_grade', 'block_curr_admin') . ':', $grades);
            $mform->setHelpButton('completion_grade', array('completionform/completion_grade', get_string('completion_grade', 'block_curr_admin'), 'block_curr_admin'));

            $mform->addElement('checkbox', 'required', get_string('required', 'block_curr_admin') . ':');
            $mform->setHelpButton('required', array('completionform/required', get_string('required', 'block_curr_admin'), 'block_curr_admin'));

            $this->add_action_buttons();
        }
    }
?>
