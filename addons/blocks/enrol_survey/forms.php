<?php

/**
 * This is a one-line short description of the file                    (1)
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.                        (2)
 *
 * @package
 *    1.  If it's part of Moodle core libraries, use moodlecore
 *    2. If it's part of a plugin, use the path of the plugin with hyphens: eg mod-forum or grade-report-visual
 *    3. Unit test code should be placed in the same package as the code being tested.
 * @copyright 15-Mar-2010 Olav Jordan <olav.jordan@remote-learner.ca>                                          
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later 
 */

require_once($CFG->dirroot . '/lib/formslib.php');
require_once('lib.php');
/**
 * short description of forms.php
 *
 * [long description of forms.php]
 *
 * @copyright 15-Mar-2010 Olav Jordan <olav.jordan@remote-learner.ca>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_survey_form {
    private $action_url;

    /**
     * set the form's action url on creation
     * 
     * @param string $url the form's action url
     */
    public function __construct($url) {
        $this->action_url = $url;
    }

    /**
     * items in the form
     */
    public function display() {
        global $CFG;
        
        $fields = get_fields();
        $questions = get_questions();
        $checked = get_forceuser()?'checked': '';

        print '<form class="mform" method="post" action="' . $this->action_url . '">';

        print '<fieldset class="hidden">';
        if(!empty($questions)) {
            print '<table cellpadding="2">';
            print '<tr align="right">';
            print '<th><span style="margin-right:10px;">' . get_string('name_on_form', 'block_enrol_survey') . '</span></th>';
            print '<th><span style="margin-right:10px;">' . get_string('existing_profile_fileds', 'block_enrol_survey') . '</span></th>';
            print '<th><span style="margin-right:10px;">' . get_string('action_fields', 'block_enrol_survey') . '</span></th>';
            print '</tr>';

            foreach($questions as $key=>$value) {
                print '<tr>';

                print '<td><input type="text" name="custom_name[]" value="' . $value . '" /></td>';

                print '<td><select name="profile_field[]" />';

                print '<option value="none">none</option>';

                foreach($fields as $f) {
                    if(strcmp($f,$key) === 0) {
                        print '<option value="' . $f . '" selected="true">' . $f . '</option>';
                        $selected = true;
                    } else {
                        print '<option value="' . $f . '">' . $f . '</option>';
                    }
                }
                
                print "</select></td>";

                print '<td><input type="submit" name="delete['. $key . ']" value="' . get_string('delete', 'block_enrol_survey') . '" /></td>';
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
        $questions = get_questions();
        $profile_fields = get_profilefields();
        $custom_fields = get_customfields();

        $mform =& $this->_form;

        unset($questions['none']);

        foreach($questions as $k=>$q) {
            $extra = null;
            
            if(in_array($k, $profile_fields)) {
                if(strcmp($k, 'country') === 0) {
                    $type = 'select';
                    $extra = get_list_of_countries();
                } else {
                    $type = 'text';
                }
            } else if(in_array($k, $custom_fields)) {
                $field = get_record('user_info_field', 'shortname', $k);

                if(strcmp($field->datatype, 'menu') === 0){
                    $type = 'select';
                    $extra = explode("\n", $field->param1);
                    $extra = array_combine($extra, $extra);
                } else if(strcmp($field->datatype, 'text') === 0) {
                    $type = 'text';
                } else if(strcmp($field->datatype, 'checkbox') === 0) {
                    $type = 'checkbox';
                } else if(strcmp($field->datatype, 'textarea') === 0) {
                    $type = 'textarea';
                }
            }

            if(!empty($type)) {
                $mform->addElement($type, $k, $q, $extra);
            }
        }

        $group = array();
        $group[] =& $mform->createElement('submit', 'save_exit', get_string('save_exit', 'block_enrol_survey'));
        $group[] =& $mform->createElement('submit', 'update', get_string('update', 'block_enrol_survey'));
        $group[] =& $mform->createElement('cancel');
        $mform->addGroup($group, 'form_buttons', '', array(''), false);
    }
}
?>
