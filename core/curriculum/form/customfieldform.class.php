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

global $CFG;
require_once($CFG->dirroot . '/lib/formslib.php');

class customfieldform extends moodleform {
    function definition() {
        $form =& $this->_form;

        $form->addElement('hidden', 'id');
        $form->setType('id', PARAM_INT);

        // common form elements (copied from /user/profile/definelib.php)
        $form->addElement('header', '_commonsettings', get_string('profilecommonsettings', 'admin'));
        $strrequired = get_string('required');

        $form->addElement('text', 'shortname', get_string('profileshortname', 'admin'), 'maxlength="100" size="25"');
        $form->addRule('shortname', $strrequired, 'required', null, 'client');
        $form->setType('shortname', PARAM_SAFEDIR);

        $form->addElement('text', 'name', get_string('profilename', 'admin'), 'size="50"');
        $form->addRule('name', $strrequired, 'required', null, 'client');
        $form->setType('name', PARAM_MULTILANG);

        $level = $this->_customdata->required_param('level', PARAM_ACTION);
        $ctxlvl = context_level_base::get_custom_context_level($level, 'block_curr_admin');
        $categories = field_category::get_for_context_level($ctxlvl);
        $choices = array();
        foreach ($categories as $category) {
            $choices[$category->id] = $category->name;
        }
        $form->addElement('select', 'categoryid', get_string('profilecategory', 'admin'), $choices);

        $form->addElement('htmleditor', 'description', get_string('profiledescription', 'admin'));
        $form->setHelpButton('description', array('text', get_string('helptext')));

        $choices = array(
            'text' => get_string('field_datatype_text', 'block_curr_admin'),
            'char' => get_string('field_datatype_char', 'block_curr_admin'),
            'int' => get_string('field_datatype_int', 'block_curr_admin'),
            'num' => get_string('field_datatype_num', 'block_curr_admin'),
            'bool' => get_string('field_datatype_bool', 'block_curr_admin'),
            );
        $form->addElement('select', 'datatype', get_string('field_datatype', 'block_curr_admin'), $choices);

        $form->addElement('advcheckbox', 'forceunique', get_string('profileforceunique', 'admin'));
        $form->setAdvanced('forceunique');

        $form->addElement('advcheckbox', 'multivalued', get_string('field_multivalued', 'block_curr_admin'));
        $form->setAdvanced('multivalued');

        $form->addElement('text', 'defaultdata', get_string('profiledefaultdata', 'admin'), 'size="50"');
        $form->setType('defaultdata', PARAM_MULTILANG);


        $plugins = get_list_of_plugins('curriculum/plugins');

        foreach ($plugins as $plugin) {
            if (is_readable(CURMAN_DIRLOCATION . '/plugins/' . $plugin . '/custom_fields.php')) {
                include_once(CURMAN_DIRLOCATION . '/plugins/' . $plugin . '/custom_fields.php');
                if (function_exists("{$plugin}_field_edit_form_definition")) {
                    call_user_func("{$plugin}_field_edit_form_definition", $this);
                }
            }
        }


        $this->add_action_buttons(true);
    }

    function validation($data, $files) {
        // copied from /user/profile/definelib.php
        global $USER;

        $err = array();

        /// Check the shortname was not truncated by cleaning
        if (empty($data['shortname'])) {
            $err['shortname'] = get_string('required');

        } else {
            /*
        /// Fetch field-record from DB
            $field = get_record(FIELDTABLE, 'shortname', $data['shortname']);
        /// Check the shortname is unique
            if ($field and $field->id != $data['id']) {
                $err['shortname'] = get_string('profileshortnamenotunique', 'admin');
            }
            */
        }

        $plugins = get_list_of_plugins('curriculum/plugins');

        foreach ($plugins as $plugin) {
            if (is_readable(CURMAN_DIRLOCATION . '/plugins/' . $plugin . '/custom_fields.php')) {
                include_once(CURMAN_DIRLOCATION . '/plugins/' . $plugin . '/custom_fields.php');
                if (function_exists("{$plugin}_field_edit_form_validation")) {
                    $err += call_user_func("{$plugin}_field_edit_form_validation", $this, $data, $files);
                }
            }
        }

        /// No further checks necessary as the form class will take care of it
        return $err;
    }
}

?>
