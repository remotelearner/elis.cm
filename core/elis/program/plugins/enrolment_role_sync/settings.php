<?php

defined('MOODLE_INTERNAL') || die;

require_once(elispm::file('plugins/enrolment_role_sync/lib.php'));

if ($ADMIN->fulltree) {

    //try to get all the non-guest roles
    $guestrole = get_guest_role();

    if ($roles = get_all_roles()) { 
        if (isset($guestrole->id)) {
            unset($roles[$guestrole->id]);
        }
    } else {
        //this should never happen
        $roles = array();
    }

    //combine the default option with the list of roles
    $options = array(0 => get_string('no_default_role', 'elis_program'));
    foreach ($roles as $id => $role) {
        $options[$id] = $role->name;
    }

    //setting header
    $settings->add(new admin_setting_heading('pmplugins_enrolment_role_sync/settings',
                                             get_string('enrolment_role_sync_settings', 'pmplugins_enrolment_role_sync'),
                                             ''));

    //student role setting
    $setting = new admin_setting_configselect('pmplugins_enrolment_role_sync/student_role',
                                              get_string('sync_student_role_setting', 'pmplugins_enrolment_role_sync'),
                                              get_string('sync_student_role_help', 'pmplugins_enrolment_role_sync'),
                                              0, $options);
    $setting->set_updatedcallback('enrolment_role_sync_updatedcallback');
    $settings->add($setting);

    //instructor role setting
    $setting = new admin_setting_configselect('pmplugins_enrolment_role_sync/instructor_role',
                                              get_string('sync_instructor_role_setting', 'pmplugins_enrolment_role_sync'),
                                              get_string('sync_instructor_role_help', 'pmplugins_enrolment_role_sync'),
                                              0, $options);
    $setting->set_updatedcallback('enrolment_role_sync_updatedcallback');
    $settings->add($setting);
}