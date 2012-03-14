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
 * @subpackage enrol_survey
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot .'/lib/formslib.php');
require_once($CFG->dirroot .'/blocks/enrol_survey/lib.php');

class edit_survey_form {
    private $action_url;
    private $courseobj;

    /**
     * set the form's action url on creation
     * 
     * @param string $url the form's action url
     */
    public function __construct($url, $courseobj) {
        $this->action_url = $url;
        $this->courseobj = $courseobj;
    }

    /**
     * items in the form
     */
    public function display() {
        global $CFG;
        
        $fields = get_fields();
        $questions = get_questions();
        $checked = get_forceuser() ? 'checked' : '';

        print '<form class="mform" method="post" action="' . $this->action_url . '">';

        print '<fieldset class="hidden">';
        if (!empty($questions)) {
            print '<table cellpadding="2">';
            print '<tr align="right">';
            print '<th><span style="margin-right:10px;">' . get_string('name_on_form', 'block_enrol_survey') . '</span></th>';
            print '<th><span style="margin-right:10px;">' . get_string('existing_profile_fileds', 'block_enrol_survey') . '</span></th>';
            print '<th><span style="margin-right:10px;">' . get_string('delete', 'block_enrol_survey') . '</span></th>';
            print '</tr>';

            foreach ($questions as $key => $value) {
                print '<tr>';

                print '<td><input type="text" name="custom_name[]" value="' . $value . '" /></td>';

                print '<td><select name="profile_field[]" />';

                print '<option value="none">none</option>';

                foreach ($fields as $f) {
                    if (strcmp($f,$key) === 0) {
                        print '<option value="' . $f . '" selected="true">' . $f . '</option>';
                        $selected = true;
                    } else {
                        print '<option value="' . $f . '">' . $f . '</option>';
                    }
                }
                
                print "</select></td>";

                print '<td><input type="checkbox" name="delete['. $key . ']" value="delete['. $key . ']" /></td>';
                print '</tr>';
            }

            print '</table>';
        }

        print '<p>';
        print '<div><label>' . get_string('force_user', 'block_enrol_survey') . ': </label><input type="checkbox" name="force_user" ' . $checked . ' /></div>';
        print '</p>';

        print '<div style="margin-top:5px"><input type="submit" name="add_profilefield" value="' . get_string('profile_field', 'block_enrol_survey') . '" /></div>';
        print '<div style="margin-top:5px"><input type="submit" name="retake" value="' . get_string('retake', 'block_enrol_survey') . '" /></div>';

        print '<div style="margin-top:10px">';
        print '<input type="submit" name="update" value="' . get_string('update', 'block_enrol_survey') . '" />';
        print '<input type="submit" name="exit" value="' . get_string('exit', 'block_enrol_survey') . '" />';
        print '<input type="hidden" name="courseid" value="' . $this->courseobj->courseid . '" />';
        print '<input type="hidden" name="mymoodle" value="' . $this->courseobj->mymoodle . '" />';
        print '</div>';

        print '</fieldset>';

        print '</form>';
    }
}

class survey_form extends moodleform {
    /**
     * items in the form
     */
    public function definition() {
        global $DB;
        $questions      = get_questions();
        $profile_fields = get_profilefields();
        $custom_fields  = get_customfields();

        $mform =& $this->_form;

        unset($questions['none']);

        foreach ($questions as $k => $q) {
            $extra = null;

            if (in_array($k, $profile_fields)) {
                if (strcmp($k, 'country') === 0) {
                    $type = 'select';
                    $extra = get_string_manager()->get_list_of_countries();
                } else if (strcmp($k, 'language') === 0) {
                    $type = 'select';
                    $extra = get_string_manager()->get_list_of_languages();
                } else if (strcmp($k, 'inactive') === 0) {
                    $type = 'advcheckbox';
                } else {
                    $type = 'text';
                }
            } else if (in_array($k, $custom_fields)) {
                $field = $DB->get_record('user_info_field', array('shortname' => $k));
                if (strcmp($field->datatype, 'menu') === 0) {
                    $type = 'select';
                    $extra = explode("\n", $field->param1);
                    $extra = array_combine($extra, $extra);
                } else if (strcmp($field->datatype, 'text') === 0) {
                    $type = 'text';
                } else if (strcmp($field->datatype, 'checkbox') === 0) {
                    $type = 'advcheckbox';
                } else if (strcmp($field->datatype, 'textarea') === 0) {
                    $type = 'textarea';
                }
            }

            if (!empty($type)) {
                $mform->addElement($type, $k, $q, $extra);
            }
        }

        $group = array();
        $group[] =& $mform->createElement('submit', 'save_exit', get_string('save_exit', 'block_enrol_survey'));
        $group[] =& $mform->createElement('submit', 'update', get_string('update', 'block_enrol_survey'));
        $group[] =& $mform->createElement('cancel');
        $mform->addElement('hidden', 'courseid', $this->_customdata->courseid);
        $mform->addElement('hidden', 'mymoodle', $this->_customdata->mymoodle);
        $mform->addGroup($group, 'form_buttons', '', array('&nbsp;'), false);
    }
}

