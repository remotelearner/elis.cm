<?php

defined('MOODLE_INTERNAL') || die;

require_once(dirname(__FILE__).'/../../../../config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(elispm::file('plugins/userset_groups/lib.php'));

if ($ADMIN->fulltree) {

    $settings->add(new admin_setting_heading('userset_grp_settings', get_string('userset_grp_settings', 'pmplugins_userset_groups'), ''));

    // Allow course-level group population from usersets
    $userset_groups = new admin_setting_configcheckbox('pmplugins_userset_groups/userset_groups',
                           get_string('grp_pop_userset_setting', 'pmplugins_userset_groups'),
                           get_string('grp_pop_userset_help', 'pmplugins_userset_groups'), 0);
    $userset_groups->set_updatedcallback('userset_groups_pm_userset_groups_enabled_handler');
    $settings->add($userset_groups);

    $sc_userset_groups = new admin_setting_configcheckbox('pmplugins_userset_groups/site_course_userset_groups',
                           get_string('fp_pop_userset_setting', 'pmplugins_userset_groups'),
                           get_string('fp_pop_userset_help', 'pmplugins_userset_groups'), 0);
    $sc_userset_groups->set_updatedcallback('userset_groups_pm_site_course_userset_groups_enabled_handler');
    $settings->add($sc_userset_groups);

    // Allow front page grouping creation from userset-based groups
    $userset_groupings = new admin_setting_configcheckbox('pmplugins_userset_groups/userset_groupings',
                           get_string('fp_grp_userset_setting', 'pmplugins_userset_groups'),
                           get_string('fp_grp_userset_help', 'pmplugins_userset_groups'), 0);
    $userset_groupings->set_updatedcallback('userset_groups_pm_userset_groupings_enabled');
    $settings->add($userset_groupings);

}