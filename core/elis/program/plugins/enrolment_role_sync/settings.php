<?php

defined('MOODLE_INTERNAL') || die;

require_once(dirname(__FILE__).'/../../../../config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(elispm::lib('lib.php'));
require_once(elispm::file('plugins/enrolment_role_sync/lib.php'));

if ($ADMIN->fulltree) {

    $options = array(0 => get_string('no_default_role', 'elis_program'));
    pm_get_select_roles_for_contexts($options, array(CONTEXT_ELIS_CLASS));

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
