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
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2011 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once elispm::file('form/cmform.class.php');

class customfieldform extends cmform {
    function definition() {
        global $CFG;

        $form =& $this->_form;

        $form->addElement('hidden', 'id');
        $form->setType('id', PARAM_INT);

        // common form elements (copied from /user/profile/definelib.php)
        $form->addElement('header', '_commonsettings', get_string('profilecommonsettings', 'admin'));
        $strrequired = get_string('required');

        $form->addElement('text', 'shortname', get_string('profileshortname', 'admin'), array('maxlength'=>'100', 'size'=>'25'));
        $form->addRule('shortname', $strrequired, 'required', null, 'client');
        $form->setType('shortname', PARAM_SAFEDIR);

        $form->addElement('text', 'name', get_string('profilename', 'admin'), array('size'=>'50'));
        $form->addRule('name', $strrequired, 'required', null, 'client');
        $form->setType('name', PARAM_MULTILANG);

        $level = $this->_customdata->required_param('level', PARAM_ACTION);
        $ctxlvl = context_level_base::get_custom_context_level($level, 'elis_program');
        $categories = field_category::get_for_context_level($ctxlvl);
        $choices = array();
        foreach ($categories as $category) {
            $choices[$category->id] = $category->name;
        }
        $form->addElement('select', 'categoryid', get_string('profilecategory', 'admin'), $choices);

        $form->addElement('htmleditor', 'description', get_string('profiledescription', 'admin'));
        //$form->addHelpButton('description', 'helptext');

        $choices = array(
            'text' => get_string('field_datatype_text', 'elis_program'),
            'char' => get_string('field_datatype_char', 'elis_program'),
            'int' => get_string('field_datatype_int', 'elis_program'),
            'num' => get_string('field_datatype_num', 'elis_program'),
            'bool' => get_string('field_datatype_bool', 'elis_program'),
            );
        $form->addElement('select', 'datatype', get_string('field_datatype', 'elis_program'), $choices);

        $form->addElement('advcheckbox', 'forceunique', get_string('profileforceunique', 'admin'));
        $form->setAdvanced('forceunique');

        $form->addElement('advcheckbox', 'multivalued', get_string('field_multivalued', 'elis_program'));
        $form->setAdvanced('multivalued');

        $form->addElement('text', 'defaultdata', get_string('profiledefaultdata', 'admin'), array('size'=>'50'));
        $form->setType('defaultdata', PARAM_MULTILANG);

        $plugins = get_list_of_plugins('elis/core/fields');

        foreach ($plugins as $plugin) {
            if (is_readable(elis::plugin_file("elisfields_{$plugin}",'custom_fields.php'))) {
                include_once(elis::plugin_file("elisfields_{$plugin}",'custom_fields.php'));
                if (function_exists("{$plugin}_field_edit_form_definition")) {
                    call_user_func("{$plugin}_field_edit_form_definition", $form);
                }
            }
        }

        $this->add_action_buttons(true);
    }

    function validation($data, $files) {
        // copied from /user/profile/definelib.php
        global $CFG, $USER, $DB;

        $err = array();

        /// Check the shortname was not truncated by cleaning
        if (empty($data['shortname'])) {
            $err['shortname'] = get_string('required');
        } else {
            /*
            /// Fetch field-record from DB
            $field = $DB->get_record(field::TABLE, array('shortname'=>$data['shortname']));
            /// Check the shortname is unique
            if ($field and $field->id != $data['id']) {
                $err['shortname'] = get_string('profileshortnamenotunique', 'admin');
            }
            */
        }

        $plugins = get_list_of_plugins('elis/core/fields');

        foreach ($plugins as $plugin) {
            if (is_readable(elis::plugin_file("elisfields_{$plugin}",'custom_fields.php'))) {
                include_once(elis::plugin_file("elisfields_{$plugin}",'custom_fields.php'));
                if (function_exists("{$plugin}_field_edit_form_validation")) {
                    $err += call_user_func("{$plugin}_field_edit_form_validation", $this, $data, $files);
                }
            }
        }

        /// No further checks necessary as the form class will take care of it
        return $err;
    }

    /**
     * Accessor for the inner quickform (needed since _form is now protected)
     *
     * @return  object  The inner quickform
     */
    function get_quickform() {
        return $this->_form;
    }
}
