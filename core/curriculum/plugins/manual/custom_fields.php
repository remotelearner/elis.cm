<?php

// Form functions

function manual_field_edit_form_definition($fform) {
    $form = $fform->_form;
    $form->addElement('header', '', get_string('field_manual_header', 'block_curr_admin'));

    $form->addElement('checkbox', 'manual_field_enabled', get_string('field_manual_allow_editing', 'block_curr_admin'));
    $form->setDefault('manual_field_enabled', 'checked');

    $form->addElement('checkbox', 'manual_field_required', get_string('profilerequired', 'admin'));
    $form->disabledIf('manual_field_required', 'manual_field_enabled', 'notchecked');

    $choices = array(
        '' => get_string('field_manual_anyone_edit', 'block_curr_admin'),
        'moodle/user:update' => get_string('field_manual_admin_edit', 'block_curr_admin'),
        'disabled' => get_string('field_manual_nobody', 'block_curr_admin'),
        );
    $form->addElement('select', 'manual_field_edit_capability', get_string('manual_field_edit_capability', 'block_curr_admin'), $choices);
    $form->disabledIf('manual_field_edit_capability', 'manual_field_enabled', 'notchecked');
    $form->setAdvanced('manual_field_edit_capability');

    $choices = array(
        '' => get_string('field_manual_anyone_view', 'block_curr_admin'),
        'moodle/user:viewhiddendetails' => get_string('field_manual_admin_view', 'block_curr_admin'),
        );
    $form->addElement('select', 'manual_field_view_capability', get_string('manual_field_view_capability', 'block_curr_admin'), $choices);
    $form->disabledIf('manual_field_view_capability', 'manual_field_enabled', 'notchecked');
    $form->setAdvanced('manual_field_view_capability');

    $choices = array(
        'checkbox' => get_string('profilefieldtypecheckbox', 'admin'),
        'menu' => get_string('profilefieldtypemenu', 'admin'),
        'text' => get_string('profilefieldtypetext', 'admin'),
        'textarea' => get_string('profilefieldtypetextarea', 'admin'),
        'password' => get_string('manual_field_control_password', 'block_curr_admin'),
        );
    $form->addElement('select', 'manual_field_control', get_string('manual_field_control', 'block_curr_admin'), $choices);
    $form->setType('manual_field_control', PARAM_ACTION);
    $form->disabledIf('manual_field_control', 'manual_field_enabled', 'notchecked');

    $choices = array();
    require_once (CURMAN_DIRLOCATION . '/plugins/manual/sources.php');
    $basedir = CURMAN_DIRLOCATION . '/plugins/manual/sources';
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
        $classname = "manual_options_$file";
        $plugin = new $classname();
        if ($plugin->is_applicable($fform->_customdata->required_param('level', PARAM_ACTION))) {
            $choices[$file] = get_string("options_source_$file", 'crlm_manual');;
        }
    }
    asort($choices);
    $choices = array('' => get_string('options_source_text', 'crlm_manual')) + $choices;
    $form->addElement('select', 'manual_field_options_source', get_string('options_source', 'crlm_manual'), $choices);
    $form->disabledIf('manual_field_options_source', 'manual_field_enabled', 'notchecked');
    $form->disabledIf('manual_field_options_source', 'manual_field_control', 'eq', 'text');
    $form->disabledIf('manual_field_options_source', 'manual_field_control', 'eq', 'textarea');
    $form->disabledIf('manual_field_options_source', 'manual_field_control', 'eq', 'password');
    $form->disabledIf('manual_field_options_source', 'datatype', 'eq', 'int');
    $form->disabledIf('manual_field_options_source', 'datatype', 'eq', 'num');
    $form->disabledIf('manual_field_options_source', 'datatype', 'eq', 'bool');
    $form->setAdvanced('manual_field_options_source');

    $form->addElement('textarea', 'manual_field_options', get_string('profilemenuoptions', 'admin'), array('rows' => 6, 'cols' => 40));
    $form->setType('manual_field_options', PARAM_MULTILANG);
    $form->disabledIf('manual_field_options', 'manual_field_enabled', 'notchecked');
    $form->disabledIf('manual_field_options', 'manual_field_control', 'eq', 'text');
    $form->disabledIf('manual_field_options', 'manual_field_control', 'eq', 'textarea');
    $form->disabledIf('manual_field_options', 'manual_field_control', 'eq', 'password');
    $form->disabledIf('manual_field_options', 'datatype', 'eq', 'int');
    $form->disabledIf('manual_field_options', 'datatype', 'eq', 'num');
    $form->disabledIf('manual_field_options', 'datatype', 'eq', 'bool');
    $form->disabledIf('manual_field_options', 'manual_field_options_source', 'neq', '');

    $form->addElement('text', 'manual_field_columns', get_string('profilefieldcolumns', 'admin'), 'size="6"');
    $form->setDefault('manual_field_columns', 30);
    $form->setType('manual_field_columns', PARAM_INT);
    $form->disabledIf('manual_field_columns', 'manual_field_enabled', 'notchecked');
    $form->disabledIf('manual_field_columns', 'manual_field_control', 'eq', 'checkbox');
    $form->disabledIf('manual_field_columns', 'manual_field_control', 'eq', 'menu');
    $form->disabledIf('manual_field_columns', 'datatype', 'eq', 'bool');

    $form->addElement('text', 'manual_field_rows', get_string('profilefieldrows', 'admin'), 'size="6"');
    $form->setDefault('manual_field_rows', 10);
    $form->setType('manual_field_rows', PARAM_INT);
    $form->disabledIf('manual_field_rows', 'manual_field_enabled', 'notchecked');
    $form->disabledIf('manual_field_rows', 'manual_field_control', 'eq', 'checkbox');
    $form->disabledIf('manual_field_rows', 'manual_field_control', 'eq', 'menu');
    $form->disabledIf('manual_field_rows', 'manual_field_control', 'eq', 'text');
    $form->disabledIf('manual_field_rows', 'manual_field_control', 'eq', 'password');
    $form->disabledIf('manual_field_rows', 'datatype', 'eq', 'bool');

    $form->addElement('text', 'manual_field_maxlength', get_string('profilefieldmaxlength', 'admin'), 'size="6"');
    $form->setDefault('manual_field_maxlength', 2048);
    $form->setType('manual_field_maxlength', PARAM_INT);
    $form->disabledIf('manual_field_maxlength', 'manual_field_enabled', 'notchecked');
    $form->disabledIf('manual_field_maxlength', 'manual_field_control', 'eq', 'checkbox');
    $form->disabledIf('manual_field_maxlength', 'manual_field_control', 'eq', 'menu');
    $form->disabledIf('manual_field_maxlength', 'manual_field_control', 'eq', 'textarea');
    $form->disabledIf('manual_field_maxlength', 'datatype', 'eq', 'bool');

    $form->addElement('text', 'manual_field_help_file', get_string('help_file', 'crlm_manual'));
    $form->setType('manual_field_help_file', PARAM_PATH);
    $form->setAdvanced('manual_field_help_file');
}

function manual_field_get_form_data($form, $field) {
    if (!isset($field->owners['manual'])) {
        return array('manual_field_enabled' => false);
    }
    $manual = new field_owner($field->owners['manual']);
    $result = array('manual_field_enabled' => true);
    $parameters = array('required', 'edit_capability', 'view_capability',
                        'control', 'options_source', 'options', 'columns',
                        'rows', 'maxlength', 'help_file');
    foreach ($parameters as $param) {
        $paramname = "param_$param";
        if (isset($manual->$paramname)) {
            $result["manual_field_$param"] = $manual->$paramname;
        }
    }
    return $result;
}

function manual_field_save_form_data($form, $field, $data) {
    if (isset($data->manual_field_enabled) && $data->manual_field_enabled) {
        if (isset($field->owners['manual'])) {
            $manual = new field_owner($field->owners['manual']);
        } else {
            $manual = new field_owner();
            $manual->fieldid = $field->id;
            $manual->plugin = 'manual';
        }
        if (isset($data->manual_field_required)) {
            $manual->param_required = $data->manual_field_required;
        } else {
            $manual->param_required = false;
        }
        $parameters = array('edit_capability', 'view_capability',
                            'control', 'options_source', 'options', 'columns',
                            'rows', 'maxlength', 'help_file');
        foreach ($parameters as $param) {
            $dataname = "manual_field_$param";
            if (isset($data->$dataname)) {
                $manual->{"param_$param"} = stripslashes($data->$dataname);
            }
        }
        $manual->params = addslashes($manual->params);
        if (empty($manual->id)) {
            $manual->add();
        } else {
            $manual->update();
        }
    } else {
        global $CURMAN;
        $CURMAN->db->delete_records(FIELDOWNERTABLE, 'fieldid', $field->id, 'plugin', 'manual');
    }
}

/**
 * Add an element to a form for a field.
 */
function manual_field_add_form_element($form, $context, $field, $check_required = true) {
    $mform = $form->_form;
    $manual = new field_owner($field->owners['manual']);
    if (!empty($manual->param_edit_capability)) {
        $capability = $manual->param_edit_capability;
        if ($capability == 'disabled' || !has_capability($capability, $context)) {
            if (!empty($manual->param_view_capability)) {
                $capability = $manual->param_view_capability;
                if (!has_capability($capability, $context)) {
                    return;
                }
            }
            $mform->addElement('static', "field_{$field->shortname}", $field->name);
            return;
        }
    }
    $control = $manual->param_control;
    require_once(CURMAN_DIRLOCATION . "/plugins/manual/field_controls/{$control}.php");
    call_user_func("{$control}_control_display", $form, $field);
    if ($check_required) {
        $manual_params = unserialize($manual->params);
        if (!empty($manual_params['required'])) {
            $mform->addRule("field_{$field->shortname}", null, 'required', null, 'client'); // TBD
        }
    }
}

/**
 * Adds a help button to the provided form for the provided field
 * 
 * @param  MoodleQuickForm  $mform        The form to add the help button to
 * @param  string           $elementname  The shortname of the element to add the help button to
 * @param  field            $field        The field corresponding to the input control
 */
function manual_field_add_help_button($mform, $elementname, $field) {
    $manual = new field_owner($field->owners['manual']);
    if (!empty($manual->param_help_file)) {
        list($plugin,$filename) = explode('/', $manual->param_help_file, 2);
        $mform->setHelpButton($elementname, array($filename, $field->name, $plugin));
    }
}

?>
