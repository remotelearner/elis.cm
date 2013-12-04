<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
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
require_once elispm::file('form/cmform.class.php');

class customfieldform extends cmform {
    var $defaultdata_menu = null;

    /**
     * Custom field form definition method
     *
     * @uses $CFG
     * @uses $DB
     * @uses $PAGE
     */
    function definition() {
        global $CFG, $DB, $PAGE;
        $attrs = array();

        $form =& $this->_form;
        $form->addElement('hidden', 'id');
        $form->setType('id', PARAM_INT);

        // Include required yui javascript
        $PAGE->requires->yui_module('moodle-elis_core-customfieldsform', 'M.elis_core.init_customfieldsform', array(get_string('profiledefaultdata', 'admin')), null, true);

        $fid = $this->_customdata['id'];
        $from = $this->_customdata['from'];

        // common form elements (copied from /user/profile/definelib.php)
        $form->addElement('header', '_commonsettings', get_string('profilecommonsettings', 'admin'));
        $strrequired = get_string('required');
        $form->addElement('text', 'shortname', get_string('profileshortname', 'admin'), array('maxlength'=>'100', 'size'=>'25'));
        $form->setType('shortname', PARAM_SAFEDIR);

        $form->addElement('text', 'name', get_string('profilename', 'admin'), array('size'=>'50'));
        $form->addRule('name', $strrequired, 'required', null, 'client');
        $form->setType('name', PARAM_MULTILANG);

        $level = $this->_customdata['level'];
        $ctxlvl = context_elis_helper::get_level_from_name($level);
        $categories = field_category::get_for_context_level($ctxlvl);
        $choices = array();
        foreach ($categories as $category) {
            $choices[$category->id] = $category->name;
        }
        $form->addElement('select', 'categoryid', get_string('profilecategory', 'admin'), $choices);

        $form->addElement('htmleditor', 'description', get_string('profiledescription', 'admin'));
        $form->setType('description', PARAM_CLEAN);
        //$form->addHelpButton('description', 'helptext');

        $choices = array(
            'text' => get_string('field_datatype_text', 'elis_program'),
            'char' => get_string('field_datatype_char', 'elis_program'),
            'int' => get_string('field_datatype_int', 'elis_program'),
            'num' => get_string('field_datatype_num', 'elis_program'),
            'bool' => get_string('field_datatype_bool', 'elis_program'),
            'datetime' => get_string('field_datatype_datetime', 'elis_program'),
            );
        $form->addElement('select', 'datatype', get_string('field_datatype', 'elis_program'), $choices);

        $form->addElement('advcheckbox', 'forceunique', get_string('profileforceunique', 'admin'));
        $form->setAdvanced('forceunique');

        $form->addElement('advcheckbox', 'multivalued', get_string('field_multivalued', 'elis_program'), '', array('group' => false));
        $form->setAdvanced('multivalued');
        $form->disabledIf('multivalued', 'datatype', 'eq', 'datetime');

        // ELIS-4592: default needs to use custom field type control
        // for checkbox OR datetime which requires javascript to update this
        // when control type is changed!
        $form->addElement('html', '<fieldset class="clearfix" id="datatype_text">');
        $form->addElement('text', 'defaultdata_text', get_string('profiledefaultdata', 'admin'), array('size'=>'50'));
        $form->setType('defaultdata_text', PARAM_MULTILANG); // TBD???

        $form->addElement('html', '</fieldset>');

        $form->addElement('html', '<fieldset class="accesshide" id="datatype_checkbox">');
        $form->addElement('advcheckbox', 'defaultdata_checkbox', get_string('profiledefaultdata', 'admin'));
        $form->addElement('html', '</fieldset>');

        $form->addElement('html', '<fieldset class="accesshide" id="datatype_menu">');
        $menu_options = array();
        if ($from == 'moodle') {
            $moptions = $DB->get_field('user_info_field', 'param1',
                                array('id' => $fid));
            $menu_options = explode("\n", $moptions);
        } else if ($fid) {
            $fparams = $DB->get_field(field_owner::TABLE, 'params',
                               array('fieldid' => $fid, 'plugin' => 'manual'));
            $foptions = unserialize($fparams);
            $menu_options = !empty($foptions['options'])
                            ? explode("\n", $foptions['options'])
                            : array();
        }
        if (!empty($menu_options)) {
           array_walk($menu_options, array($this, 'trim_crlf'));
           $menu_options = array_combine($menu_options, $menu_options);
        }
        if (($this->defaultdata_menu = $form->createElement('select', 'defaultdata_menu', get_string('profiledefaultdata', 'admin'), $menu_options, array('multiple' => 'multiple')))) {
            $form->addElement($this->defaultdata_menu);
        }
        //$form->setType('defaultdata_menu', PARAM_TEXT);
        $form->addElement('html', '</fieldset>');

        $form->addElement('hidden', 'defaultdata_radio'); // *REQUIRED* place-hoolder!!!
        $form->setType('defaultdata_radio', PARAM_TEXT);
        $form->addElement('html', '<fieldset class="accesshide" id="datatype_radio">');
        $form->addElement('html', '</fieldset>');

        // Loop thru all possible sources for menu options
        require_once elis::plugin_file('elisfields_manual','sources.php');
        $basedir = elis::plugin_file('elisfields_manual','sources');
        $dirhandle = opendir($basedir);
        while (false !== ($file = readdir($dirhandle))) {
            if (filetype($basedir .'/'. $file) === 'dir') {
                continue;
            }
            if (substr($file,-4) !== '.php') {
                continue;
            }
            require_once($basedir.'/'.$file);
            $file = substr($file, 0, -4);
            $classname = "manual_options_{$file}";
            $plugin = new $classname();
            if ($plugin->is_applicable($level)) {
                $poptions = $plugin->get_options(array()); // TBD

                $form->addElement('html', '<fieldset class="accesshide" id="datatype_menu_'. $file .'">');
                $form->addElement('select', "defaultdata_menu_{$file}",
                        get_string('profiledefaultdata', 'admin'),
                        $poptions);
                $form->addElement('html', '</fieldset>');

                $form->addElement('html', '<fieldset class="accesshide" id="datatype_radio_'. $file .'">');
                $radios = array();
                foreach ($poptions as $poption) {
                    $radios[] = &$form->createElement('radio', "defaultdata_radio_{$file}_", $poption, $poption, $poption);
                }
                $form->addGroup($radios, "defaultdata_radio_{$file}",
                                get_string('profiledefaultdata', 'admin'),
                                array('<br/>'), false);
                $form->addElement('html', '</fieldset>');
            }
        }

        $form->addElement('html', '<fieldset class="accesshide" id="datatype_datetime">');

        $startyear = $stopyear = $inctime = false;
        if ($from == 'moodle') {
            $startyear = $DB->get_field('user_info_field', 'param1',
                                array('id' => $fid));
            $stopyear = $DB->get_field('user_info_field', 'param2',
                               array('id' => $fid));
            $inctime = $DB->get_field('user_info_field', 'param3',
                               array('id' => $fid));
        } else if ($fid) {
            $fparams = $DB->get_field(field_owner::TABLE, 'params',
                               array('fieldid' => $fid, 'plugin' => 'manual'));
            $foptions = unserialize($fparams);
            $startyear = !empty($foptions['startyear']) ? $foptions['startyear'] : false;
            $stopyear = !empty($foptions['stopyear']) ? $foptions['stopyear'] : false;
            $inctime = !empty($foptions['inctime']);
        }
        if (empty($startyear)) {
            $startyear = 1970;
        }
        if (empty($stopyear)) {
           $stopyear = 2038;
        }
        if ($startyear < 1902 || $startyear > 2038) {
            $startyear = 1970;
        }
        if ($stopyear < 1902 || $stopyear > 2038) {
            $stopyear = 2038;
        }
        $form->addElement('date_time_selector',
                 'defaultdata_datetime', get_string('profiledefaultdata', 'admin'),
                 array('startyear' => $startyear,
                       'stopyear' => $stopyear,
                       'timezone' => 99, 'optional' => false)); // TBD!?!
        $form->addElement('html', '</fieldset>');

        $plugins = get_list_of_plugins('elis/core/fields');

        foreach ($plugins as $plugin) {
            if (is_readable(elis::plugin_file("elisfields_{$plugin}",'custom_fields.php'))) {
                include_once(elis::plugin_file("elisfields_{$plugin}",'custom_fields.php'));
                if (function_exists("{$plugin}_field_edit_form_definition")) {
                    call_user_func("{$plugin}_field_edit_form_definition", $form, $attrs);
                }
            }
        }

        $this->add_action_buttons(true);
    }

    function trim_crlf(&$item, $key) {
        $item = trim($item, "\r\n");
    }

    /**
     * Definition after data method to modify form based on form data
     *
     * @uses $DB
     * @uses $PAGE
     */
    function definition_after_data() {
        global $DB, $PAGE;
        parent::definition_after_data();
        $mform = &$this->_form;

        // Disable shortname if user context and Moodle shortname exists
        $level = $this->_customdata['level'];
        $shortname = $mform->getElementValue('shortname');
        $requireshortname = true;
        if ($level == 'user') { // user custom field from Moodle
            if (!empty($shortname) && $DB->record_exists('user_info_field', array('shortname' => $shortname))) {
                $mform->freeze('shortname');
                // ELIS-8329: 2.4 freeze seems to remove element from data!
                $mform->setDefault('shortname', $shortname);
                $requireshortname = false;
            }
        }
        if ($requireshortname) {
            $mform->addRule('shortname', get_string('required'), 'required', null, 'client');
        }

        // Check for specific plugin definition_after_data functions
        $plugins = get_list_of_plugins('elis/core/fields');
        foreach ($plugins as $plugin) {
            if (is_readable(elis::plugin_file("elisfields_{$plugin}",'custom_fields.php'))) {
                include_once(elis::plugin_file("elisfields_{$plugin}",'custom_fields.php'));
                if (function_exists("{$plugin}_field_edit_form_definition_after_data")) {
                    call_user_func("{$plugin}_field_edit_form_definition_after_data", $mform, $level, $shortname);
                }
            }
        }

        $td = $mform->getElementValue('defaultdata_menu');
        if (!isset($td)) {
            return;
        }
        if (!is_array($td)) {
            $td = array($td);
        }
        array_walk($td, array($this, 'trim_crlf'));
        $dt = $mform->getElementValue('datatype');
        if (!empty($dt) && is_array($dt)) {
            // If it's an array just get first value!
            foreach ($dt as $val) {
                $dt = $val;
                break;
            }
        }
        $mform->addElement('html', '<script type="text/javascript">
        function setmenudefaults() {
            var myselected = ['.
            (($dt == 'char' || $dt == 'text')
             ? '"'. implode('", "', $td) .'"'
             : implode(', ', $td)). '];
            var defaultdata_menu = document.getElementById("id_defaultdata_menu");
            var inputtags = document.getElementsByTagName("input");
            for (var i = 0; i < myselected.length; ++i) {
                var j;
                for (j = 0; j < defaultdata_menu.options.length; ++j) {
                    //alert("checking: "+ myselected[i] +" == "+ parseFloat(defaultdata_menu.options[j].value));
                    if ((typeof(myselected[i]) == "string" &&
                        myselected[i] == defaultdata_menu.options[j].value)
                        || (typeof(myselected[i]) == "number" &&
                        myselected[i] == parseFloat(defaultdata_menu.options[j].value))) {
                        //alert("menu default match");
                        defaultdata_menu.options[j].selected = "selected";
                    }
                }
                for (j = 0; j < inputtags.length; ++j) {
                    //alert("checking for radios with value = "+ myselected[i] + "; current: input = "+ inputtags[j].type + ", value = "+ inputtags[j].value + " type == radio ? "+ (inputtags[j].type == "radio") + "; value == ? "+ (inputtags[j].value == myselected[i]));
                    if (inputtags[j].type == "radio" &&
                        inputtags[j].value == myselected[i]) {
                        //alert("radio default match");
                        inputtags[j].checked = "checked";
                        break;
                    }
                }
            }
        }
        YUI().use("yui2-base", "yui2-yahoo", function(Y) {
            if (Y.YUI2.env.ua.ie <= 8) {
                window.setTimeout(setmenudefaults, 2000); // ugly for IE8
            } else if (window.attachEvent) {
                window.attachEvent("onload", setmenudefaults);
            } else if (window.addEventListener) {
                window.addEventListener("DOMContentLoaded", setmenudefaults, false);
            } else {
                window.setTimeout(setmenudefaults, 2000); // rare fallback
            }
        });
        </script>');

        // $PAGE->requires->js_init_call() didn't work in IE8 w/ domready=true
    }

    function validation($data, $files) {
        // copied from /user/profile/definelib.php
        global $CFG, $USER, $DB;

        $err = array();
        $fid = $this->_customdata['id'];

        //ob_start();
        //var_dump($this->defaultdata_menu);
        //$tmp = ob_get_contents();
        //ob_end_clean();
        //error_log("customfieldform::validation(); defaultdata_menu = {$tmp}");

        if ($this->defaultdata_menu && $data['manual_field_control'] == 'menu'
            && empty($data['manual_field_options_source'])) {
            $menu_options = explode("\n", $data['manual_field_options']);
            array_walk($menu_options, array($this, 'trim_crlf'));
            $select_options = array();
            foreach ($menu_options as $menu_option) {
                // ELIS-8066: Disallow blank/empty menu options
                if (empty($menu_option)) {
                    $err['manual_field_options'] = get_string('no_blank_menuoption', 'elis_program');
                }
                $select_options[] = array('text' => $menu_option,
                                          'attr' => array('value' => $menu_option));
            }
            //ob_start();
            //var_dump($this->defaultdata_menu->_options);
            //$tmp = ob_get_contents();
            //ob_end_clean();
            //error_log("customfieldform::validation(); defaultdata_menu->_options = {$tmp}");
            $this->defaultdata_menu->_options = $select_options;
        }

        /// Check the shortname was not truncated by cleaning
        if (empty($data['shortname'])) {
            $err['shortname'] = get_string('required');
        } else {
            // Check for duplicate shortnames
            $level = $this->_customdata['level'];
            $contextlevel = context_elis_helper::get_level_from_name($level);
            if (!$contextlevel) {
                print_error('invalid_context_level', 'elis_program');
            }

            $editsql = '';
            // We are in edit mode
            if (!empty($fid)) {
                $editsql = "AND ef.id != {$fid}";
            }

            $sql = "SELECT ef.id
                    FROM {".field::TABLE."} ef
                    INNER JOIN {".field_contextlevel::TABLE."} cl ON ef.id = cl.fieldid
                    WHERE ef.shortname = ?
                    AND cl.contextlevel = ?
                    {$editsql}";

            $params =  array($data['shortname'], $contextlevel);

            if ($DB->record_exists_sql($sql, $params)) {
                 $err['shortname'] = get_string('profileshortnamenotunique', 'admin');
            }
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
