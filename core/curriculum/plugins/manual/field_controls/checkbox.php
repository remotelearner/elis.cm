<?php

require_once(CURMAN_DIRLOCATION . '/plugins/manual/custom_fields.php');

/**
 * Adds an appropriate editing control to the provided form
 * 
 * @param  moodleform or HTML_QuickForm  $form       The form to add the appropriate element to
 * @param  field                         $field      The definition of the field defining the controls
 * @param  boolean                       $as_filter  Whether to display a "choose" message
 */
function checkbox_control_display($form, $field, $as_filter=false) {
    if ($form instanceof moodleform) {
        $mform = $form->_form;
    } else {
        $mform = $form;
        $form->_customdata = null;
    }
    
    if ($field->datatype == 'bool') {
        $checkbox = $mform->addElement('advcheckbox', "field_{$field->shortname}", $field->name);
        manual_field_add_help_button($mform, "field_{$field->shortname}", $field);
    } else {
        //if ($as_filter || $field->multivalued) {
            require_once(CURMAN_DIRLOCATION.'/plugins/manual/field_controls/menu.php');
            return menu_control_display($form, $field, $as_filter);
        //}
        //  FIXME: this doesn't work
        $manual = new field_owner($field->owners['manual']);
        $options = explode("\n", $manual->param_options);
        $controls = array();
        foreach ($options as $option) {
            if ($field->multivalued) {
                $cb = $controls[] = &MoodleQuickForm::createElement('checkbox', "field_{$field->shortname}", null, $option);
                $cb->updateAttributes(array('value'=>$option));
            } else {
                $controls[] = &MoodleQuickForm::createElement('radio', "field_{$field->shortname}", null, $option, $option);
            }
        }
        $mform->addGroup($controls, "field_{$field->shortname}", $field->name, '<br />', false);
    }
}

function checkbox_control_set_value($form, $data, $field) {
}

function checkbox_control_get_value($data, $field) {
    // FIXME: allow multivalued
    $name = "field_{$field->shortname}";
    return $data->$name;
}

?>
