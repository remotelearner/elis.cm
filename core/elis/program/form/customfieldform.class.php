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
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
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

        $form->addElement('html', '<script type="text/javascript">
            function switchDefaultData() {
                var elem;
                var elemid;
                var fcontrol = document.getElementById("id_manual_field_control");
                var dttext = document.getElementById("datatype_text");
                var dtcheckbox = document.getElementById("datatype_checkbox");
                var dtradio = document.getElementById("datatype_radio");
                var dtdatetime = document.getElementById("datatype_datetime");
                elemid = "datatype_" + fcontrol.options[fcontrol.selectedIndex].value;
                //alert("switchDefaultData(): elemid = " + elemid);
                if (!(elem = document.getElementById(elemid))) {
                    elemid = "datatype_text";
                    elem = dttext;
                }
                if (elemid == "datatype_checkbox") {
                    dtradio.className = "accesshide custom_field_default_fieldset";
                    dtcheckbox.className = "accesshide custom_field_default_fieldset";
                    dttext.className = "accesshide custom_field_default_fieldset";
                    dtdatetime.className = "accesshide custom_field_default_fieldset";
                } else if (elemid == "datatype_menu") {
                    dtcheckbox.className = "accesshide custom_field_default_fieldset";
                    dtradio.className = "accesshide custom_field_default_fieldset";
                    dttext.className = "accesshide custom_field_default_fieldset";
                    dtdatetime.className = "accesshide custom_field_default_fieldset";
                } else if (elemid == "datatype_datetime") {
                    dtdatetime.className = "clearfix custom_field_default_fieldset";
                    dtcheckbox.className = "accesshide custom_field_default_fieldset";
                    dtradio.className = "accesshide custom_field_default_fieldset";
                    dttext.className = "accesshide custom_field_default_fieldset";
                } else { // default: datatype_text
                    dttext.className = "clearfix custom_field_default_fieldset";
                    dtdatetime.className = "accesshide custom_field_default_fieldset";
                    dtcheckbox.className = "accesshide custom_field_default_fieldset";
                    dtradio.className = "accesshide custom_field_default_fieldset";
                }
                updateMenuOptions();
            }
            function disableMenuOptions() {
                var srcs = document.getElementById("id_manual_field_options_source");
                var dtmenu = document.getElementById("datatype_menu");
                var dtradio = document.getElementById("datatype_radio");
                var dtcheckbox = document.getElementById("datatype_checkbox");
                var i, elemid;
                if (dtmenu) {
                    //alert("disableMenuOptions(): datatype_menu");
                    dtmenu.className = "accesshide custom_field_default_fieldset";
                }
                if (dtradio) {
                    dtradio.className = "accesshide custom_field_default_fieldset";
                }
                if (dtcheckbox) {
                    dtcheckbox.className = "accesshide custom_field_default_fieldset";
                }
                for (i = 1; i < srcs.options.length; ++i) {
                    if (elemid = document.getElementById("datatype_menu_" +
                                     srcs.options[i].value)) {
                        //alert("disableMenuOptions(): datatype_menu_" + srcs.options[i].value);
                        elemid.className = "accesshide custom_field_default_fieldset";
                    }
                    if (elemid = document.getElementById("datatype_radio_" +
                                     srcs.options[i].value)) {
                        //alert("disableMenuOptions(): datatype_radio_" + srcs.options[i].value);
                        elemid.className = "accesshide custom_field_default_fieldset";
                    }
                }
            }
            function updateMenuOptions() {
                var srcs = document.getElementById("id_manual_field_options_source");
                var fcontrol = document.getElementById("id_manual_field_control");
                disableMenuOptions();
                if (srcs && fcontrol) {
                    var mopts, itemend, cur, iecr;
                    var multivalued = document.getElementById("id_multivalued");
                    var menu_options = document.getElementById("id_manual_field_options");
                    if ((menu_options.value.length || srcs.selectedIndex > 0) &&
                        multivalued.checked &&
                        fcontrol.options[fcontrol.selectedIndex].value == "checkbox") {
                        // TBD: just change control type to menu???
                        for (var j = 0; j < fcontrol.options.length; ++j) {
                            if (fcontrol.options[j].value == "menu") {
                                fcontrol.selectedIndex = j;
                                break;
                            }
                        }
                    }
                    if (fcontrol.options[fcontrol.selectedIndex].value == "menu") {
                        var dtmenu;
                        if (srcs.selectedIndex == 0) {
                            dtmenu = document.getElementById("datatype_menu");
                            if (dtmenu) {
                                dtmenu.className = "clearfix custom_field_default_fieldset";
                                var defaultdata_menu = document.getElementById("id_defaultdata_menu");
                                if (defaultdata_menu && multivalued) {
                                    defaultdata_menu.multiple = multivalued.checked ? "multiple" : "";
                                }
                                if (defaultdata_menu && menu_options) {
                                    var i;
                                    for (i = defaultdata_menu.options.length - 1;
                                         i >= 0; --i) {
                                        defaultdata_menu.options.remove(i);
                                    }
                                    mopts = menu_options.value;
                                    do {
                                        itemend = mopts.indexOf("\n");
                                        if (itemend == -1) {
                                            cur = mopts;
                                        } else {
                                            cur = mopts.substr(0, itemend);
                                            iecr = cur.indexOf("\r"); // IE7
                                            if (iecr != -1) {
                                                cur = cur.substr(0, iecr);
                                            }
                                            mopts = mopts.substr(itemend + 1);
                                        }
                                        //alert("updateMenuOptions(): Adding option: " + cur);
                                        var elem = new Option(cur, cur);
                                        defaultdata_menu.options.add(elem);
                                    } while (itemend != -1);
                                }
                            }
                        } else if ((dtmenu = document.getElementById("datatype_menu_" + srcs.options[srcs.selectedIndex].value))) {
                            dtmenu.className = "clearfix custom_field_default_fieldset";
                        }
                    } else if (fcontrol.options[fcontrol.selectedIndex].value == "checkbox") {
                        var dtcheckbox = document.getElementById("datatype_checkbox");
                        if (multivalued.checked) {
                            dtcheckbox.className = "clearfix custom_field_default_fieldset";
                            return;
                        }
                        dtcheckbox.className = "accesshide custom_field_default_fieldset";
                        var dtradio = document.getElementById("datatype_radio");
                        if (dtradio) {
                            if (dtradio.children) {
                                //alert("updateMenuOptions(): RADIO: deleteing dtradio children " + dtradio.children.length);
                                //var dots = ".";
                                while (dtradio.children.length) {
                                    //alert("updateMenuOptions(): deleteing radio child node "+ dots);
                                    dtradio.children[0].parentNode.removeChild(dtradio.children[0]);
                                    //dots += ".";
                                }
                            }
                            if (srcs.selectedIndex == 0) {
                                mopts = menu_options.value;
                                if (!mopts.length) {
                                    //alert("updateMenuOptions(): RADIO: !mopts.length - returning!");
                                    dtcheckbox.className = "clearfix custom_field_default_fieldset";
                                    return;
                                }
                                dtradio.className = "clearfix custom_field_default_fieldset";
                                menu_options = document.getElementById("id_manual_field_options");
                                var radiolabel = "'. get_string('profiledefaultdata', 'admin') .'";
                                var checked = "checked";
                                var count = 0;
                                do {
                                    itemend = mopts.indexOf("\n");
                                    if (itemend == -1) {
                                        cur = mopts;
                                    } else {
                                        cur = mopts.substr(0, itemend);
                                        iecr = cur.indexOf("\r"); // IE7
                                        if (iecr != -1) {
                                            cur = cur.substr(0, iecr);
                                        }
                                        mopts = mopts.substr(itemend + 1);
                                    }
                                    //alert("updateMenuOptions(): Adding radio option: " + cur);
                                    // <div id="fitem_id_defaultdata_radio_" class="fitem fitem_fradio">
                                    //     <div class="fitemtitle">
                                    //         <label for="id_defaultdata_radio_"> </label>
                                    //     <div class="felement fradio">
                                    //         <span>
                                    //             <input id="id_defaultdata_radio_" type="radio" checked="checked" name="defaultdata_radio">
                                    //             <label for="id_defaultdata_radio_">Option2</label>
                                    var topdiv = document.createElement("div");
                                    topdiv.id = "fitem_id_defaultdata_radio";
                                    topdiv.className = "fitem fitem_fradio";
                                    var labeldiv = document.createElement("div");
                                    labeldiv.className = "fitemtitle";
                                    var labelel = document.createElement("label");
                                    //labelel.for = "id_defaultdata_radio";
                                    labelel.setAttribute("for", "id_defaultdata_radio");
                                    labelel.innerHTML = radiolabel;
                                    labeldiv.appendChild(labelel);

                                    var radiodiv = document.createElement("div");
                                    radiodiv.className = "felement fradio";
                                    var rspan = document.createElement("span");
                                    var rinput = document.createElement("input");
                                    rinput.type = "radio";
                                    rinput.checked = checked;
                                    checked = "";
                                    rinput.id = "id_defaultdata_radio"+count;
                                    rinput.name = "defaultdata_radio";
                                    rinput.value = cur;
                                    var labelrad = document.createElement("label");
                                    //labelrad.for = "id_defaultdata_radio";
                                    labelrad.setAttribute("for", "id_defaultdata_radio"+count);
                                    labelrad.innerHTML = cur;
                                    rspan.appendChild(rinput);
                                    rspan.appendChild(labelrad);
                                    radiodiv.appendChild(rspan);
                                    topdiv.appendChild(labeldiv);
                                    topdiv.appendChild(radiodiv);
                                    dtradio.appendChild(topdiv);
                                    radiolabel = "&nbsp;";
                                    count++;
                                } while (itemend != -1);
                            } else if ((dtradio = document.getElementById("datatype_radio_" + srcs.options[srcs.selectedIndex].value))) {
                                dtradio.className = "clearfix custom_field_default_fieldset";
			    }
                        }
                    }
                }
            }
            function updateDefaultYears() {
                var yrid = document.getElementById("id_defaultdata_datetime_year");
                var startyr = document.getElementById("id_manual_field_startyear");
                var stopyr = document.getElementById("id_manual_field_stopyear");
                if (startyr && stopyr && yrid) {
                    var i;
                    for (i = yrid.options.length - 1; i >= 0; --i) {
                        yrid.options.remove(i);
                    }
                    for (i = startyr.options[startyr.selectedIndex].value;
                         i <= stopyr.options[stopyr.selectedIndex].value; ++i) {
                        //alert("updateDefaultYears(); Adding yr = " + i);
                        var elem = new Option(i.toString(), i);
                        yrid.options.add(elem);
                    }
                }
            }
            function timeFieldsEnabled(ischecked) {
                var hrid = document.getElementById("id_defaultdata_datetime_hour");
                var minid = document.getElementById("id_defaultdata_datetime_minute");
                if (hrid) {
                    if (!ischecked) {
                        hrid.value = 0;
                        hrid.disabled = "disabled";
                    } else {
                        hrid.disabled = "";
                    }
                }
                if (minid) {
                    if (!ischecked) {
                        minid.value = 0;
                        minid.disabled = "disabled";
                    } else {
                        minid.disabled = "";
                    }
                }
            }
            function initCustomFieldDefault() {
                var inctime = document.getElementById("id_manual_field_inctime");
                if (inctime) {
                    timeFieldsEnabled(inctime.checked);
                }
                YUI().use("yui2-event", function(Y) {
                    var YAHOO = Y.YUI2;
                    YAHOO.util.Event.addListener(window, "load", switchDefaultData());
                });
            }
            function multivaluedChanged(checked) {
                var fcontrol = document.getElementById("id_manual_field_control");
                var srcs = document.getElementById("id_manual_field_options_source");
                var defaultdata_menu;
                defaultdata_menu = (srcs.selectedIndex != 0)
                                   ? document.getElementById("id_defaultdata_menu_"+ srcs.options[srcs.selectedIndex].value)
                                   : document.getElementById("id_defaultdata_menu")
                if (defaultdata_menu) {
                    defaultdata_menu.multiple = checked ? "multiple" : "";
                }
                if (fcontrol.options[fcontrol.selectedIndex].value == "checkbox") {
                    updateMenuOptions();
                }
            }
            YUI().use("yui2-event", function(Y) {
                var YAHOO = Y.YUI2;
                YAHOO.util.Event.onDOMReady(initCustomFieldDefault);
            });
        </script>');

        $attrs['manual_field_control'] = array('onchange' => 'switchDefaultData();');
        $attrs['manual_field_startyear'] = array('onchange' => 'updateDefaultYears();');
        $attrs['manual_field_stopyear'] = array('onchange' => 'updateDefaultYears();');
        $attrs['manual_field_inctime'] = array('onclick' => 'timeFieldsEnabled(this.checked);');
        $attrs['manual_field_options_source'] = array('onchange' => 'updateMenuOptions();');
        $attrs['manual_field_options'] = array('onchange' => 'updateMenuOptions();');

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

        $form->addElement('advcheckbox', 'multivalued', get_string('field_multivalued', 'elis_program'), '', array('onclick' => 'multivaluedChanged(this.checked);', 'group' => false));
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
