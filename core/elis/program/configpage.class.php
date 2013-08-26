<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
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
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once elispm::lib('page.class.php');
require_once elispm::lib('lib.php');


/// The main management page.
class configpage extends pm_page {
    var $pagename = 'cfg';
    var $section = 'admn';
    var $form_class = 'pmconfigform';

    function can_do_default() {
        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('elis/program:config', $context);
    }

    function build_navbar_default($who = null) { // was build_navigation_default
        global $CFG;

        $page = $this->get_new_page(array('action' => 'default'), true);

        $this->navbar->add(get_string('learningplan', 'elis_program'), "{$CFG->wwwroot}/elis/program/");
        $this->navbar->add(get_string('notifications', 'elis_program'), $page->url);
    }

    function get_title_default() {
        return get_string('configuration');
    }

    static function config_set_value($configdata, $key, $default = null) {
        if (isset($configdata->$key)) {
            $value = $configdata->$key;
        } else {
            $value = $default;
        }
        if ($value !== null) {
            pm_set_config($key, $value);
        }
    }

    function do_default() {
        global $CFG, $DB;

        $target = $this->get_new_page(array('action' => 'default'));

        $configform = new $this->form_class($target->url);
        if ($configform->is_cancelled()) {
            $target = $this->get_new_page(array('action' => 'default'), true);
            redirect($target->url);
            return;
        }
        $configform->set_data(elis::$config->elis_program);

        if ($configdata = $configform->get_data()) {
            $old_cluster_groups = elis::$config->elis_program->cluster_groups;
            $old_site_course_cluster_groups = elis::$config->elis_program->site_course_cluster_groups;
            $old_cluster_groupings = elis::$config->elis_program->cluster_groupings;

            // Track settings
            self::config_set_value($configdata, 'userdefinedtrack', 0);

            // Course catalog / Learning plan settings
            self::config_set_value($configdata, 'disablecoursecatalog', 0);
            self::config_set_value($configdata, 'catalog_collapse_count');

            // Curriculum expiration settings (ELIS-1172)
            $curassupdate = false;

            // We need to check for an required update before setting the variable as the API call requires the
            // variable to be set before the changes can take place.
            if (isset($configdata->curriculum_expiration_start) &&
                $configdata->curriculum_expiration_start != elis::$config->elis_program->curriculum_expiration_start) {

                $curassupdate = true;
            }

            self::config_set_value($configdata, 'enable_curriculum_expiration', 0);
            self::config_set_value($configdata, 'curriculum_expiration_start', '');

            // If this setting is changed, we need to update the existing curriclum expiration values
            if ($curassupdate) {
                require_once elispm::lib('data/curriculumstudent.class.php');
                if ($rs = $DB->get_recordset(curriculumstudent::TABLE, null, '', 'id, userid, curriculumid')) {
                    $timenow = time();

                    foreach ($rs as $curass) {
                        $update = new stdClass;
                        $update->id           = $curass->id;
                        $update->timeexpired  = calculate_curriculum_expiry(false, $curass->curriculumid, $curass->userid);
                        $update->timemodified = $timenow;
                        $DB->update_record(curriculumstudent::TABLE, $update);
                     }

                    $rs->close();
                }
            }

            // Certificate settings
            self::config_set_value($configdata, 'disablecertificates', 0);
            self::config_set_value($configdata, 'certificate_border_image', 'Fancy1-blue.jpg');
            self::config_set_value($configdata, 'certificate_seal_image', 'none');
            self::config_set_value($configdata, 'certificate_template_file', 'default.php');

            // Interface settings
            self::config_set_value($configdata, 'time_format_12h', 0);
            self::config_set_value($configdata, 'mymoodle_redirect', 0);

            // User settings
            self::config_set_value($configdata, 'auto_assign_user_idnumber', 0);
            $old_cfg = elis::$config->elis_program->auto_assign_user_idnumber;
            // if this setting is changed to true, synchronize the users
            if (isset($configdata->auto_assign_user_idnumber)
                && $configdata->auto_assign_user_idnumber != 0
                && $old_cfg == 0) {
                //TODO: Needs to be ported to ELIS 2
//                cm_migrate_moodle_users(true, 0);
            }
            self::config_set_value($configdata, 'default_instructor_role', 0);
            self::config_set_value($configdata, 'restrict_to_elis_enrolment_plugin', 0);

            // Cluster settings
            self::config_set_value($configdata, 'cluster_groups', 0);
            self::config_set_value($configdata, 'site_course_cluster_groups', 0);
            self::config_set_value($configdata, 'cluster_groupings', 0);

            //settings specifically for the curriculum administration block
            self::config_set_value($configdata, 'num_block_icons', 5);
            self::config_set_value($configdata, 'display_clusters_at_top_level', 1);
            self::config_set_value($configdata, 'display_curricula_at_top_level', 0);

            //default role assignments on cm entities
            self::config_set_value($configdata, 'default_cluster_role_id', 0);
            self::config_set_value($configdata, 'default_curriculum_role_id', 0);
            self::config_set_value($configdata, 'default_course_role_id', 0);
            self::config_set_value($configdata, 'default_class_role_id', 0);
            self::config_set_value($configdata, 'default_track_role_id', 0);

            // TODO: ELIS 2 port of roles
            //enrolment synchronization roles
            /*$old_role_sync = elis::$config->elis_program->enrolment_role_sync_student_role;
            self::config_set_value($configdata, 'enrolment_role_sync_student_role', 0);
            if (isset($configdata->enrolment_role_sync_student_role)
                && $configdata->enrolment_role_sync_student_role != 0
                && $configdata->enrolment_role_sync_student_role != $old_role_sync) {
                require_once CURMAN_DIRLOCATION . '/plugins/enrolment_role_sync/lib.php';
                enrolment_role_sync::student_sync_role_set();
            }
            $old_role_sync = elis::$config->elis_program->enrolment_role_sync_instructor_role;
            self::config_set_value($configdata, 'enrolment_role_sync_instructor_role', 0);
            if (isset($configdata->enrolment_role_sync_instructor_role)
                && $configdata->enrolment_role_sync_instructor_role != 0
                && $configdata->enrolment_role_sync_instructor_role != $old_role_sync) {
                require_once CURMAN_DIRLOCATION . '/plugins/enrolment_role_sync/lib.php';
                enrolment_role_sync::instructor_sync_role_set();
            }
            */

            // autocreate settings
            self::config_set_value($configdata, 'autocreated_unknown_is_yes', 0);

            //trigger events
            if(!empty($configdata->cluster_groups) && empty($old_cluster_groups)) {
                events_trigger('crlm_cluster_groups_enabled', 0);
            }

            if(!empty($configdata->site_course_cluster_groups) && empty($old_site_course_cluster_groups)) {
                events_trigger('crlm_site_course_cluster_groups_enabled', 0);
            }

            if(!empty($configdata->cluster_groupings) && empty($old_cluster_groupings)) {
                events_trigger('crlm_cluster_groupings_enabled', 0);
            }
        }

        //$configform->display();

        $this->display('default');
    }

    /**
     * handler for the edit action.  Prints the edit form.
     */
    function display_default() {

        $target = $this->get_new_page(array('action' => 'default'));


        $form = new $this->form_class($target->url);
//        $form->set_data(array('id' => $parent_obj->id,
//                              'association_id' => $obj->id));
        $form->display();
    }

}

?>
