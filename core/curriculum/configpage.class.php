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

require_once CURMAN_DIRLOCATION . '/lib/newpage.class.php';


/// The main management page.
class configpage extends newpage {
    var $pagename = 'cfg';
    var $section = 'admn';

    function can_do_default() {
        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('block/curr_admin:managecurricula', $context);
    }

    function get_navigation_default() {
        return array(
            array('name' => get_string('configuration'),
                  'link' => $this->get_url()),
            );
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
            cm_set_config($key, $value);
        }
    }

    function action_default() {
        global $CFG, $CURMAN;

        require_once($CFG->dirroot.'/curriculum/form/configform.class.php');

        $configform = new cmconfigform('index.php', $this);
        $configform->set_data($CURMAN->config);

        if ($configdata = $configform->get_data()) {
            $old_cluster_groups = $CURMAN->config->cluster_groups;
            $old_site_course_cluster_groups = $CURMAN->config->site_course_cluster_groups;
            $old_cluster_groupings = $CURMAN->config->cluster_groupings;

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
                $configdata->curriculum_expiration_start != $CURMAN->config->curriculum_expiration_start) {

                $curassupdate = true;
            }

            self::config_set_value($configdata, 'enable_curriculum_expiration', 0);
            self::config_set_value($configdata, 'curriculum_expiration_start', '');

            // If this setting is changed, we need to update the existing curriclum expiration values
            if ($curassupdate) {
                if ($rs = get_recordset(CURASSTABLE, '', '', '', 'id, userid, curriculumid')) {
                    $timenow = time();

                    while ($curass = rs_fetch_next_record($rs)) {
                        $update = new stdClass;
                        $update->id           = $curass->id;
                        $update->timeexpired  = calculate_curriculum_expiry(false, $curass->curriculumid, $curass->userid);
                        $update->timemodified = $timenow;

                        update_record(CURASSTABLE, $update);
                     }

                    rs_close($rs);
                }
            }

            // Certificate settings
            self::config_set_value($configdata, 'disablecertificates', 0);
            self::config_set_value($configdata, 'certificate_border_image', 'Fancy1-blue.jpg');
            self::config_set_value($configdata, 'certificate_seal_image', 'none');

            // Interface settings
            self::config_set_value($configdata, 'time_format_12h', 0);
            self::config_set_value($configdata, 'mymoodle_redirect', 0);

            // User settings
            self::config_set_value($configdata, 'auto_assign_user_idnumber', 0);
            $old_cfg = $CURMAN->config->auto_assign_user_idnumber;
            // if this setting is changed to true, synchronize the users
            if (isset($configdata->auto_assign_user_idnumber)
                && $configdata->auto_assign_user_idnumber != 0
                && $old_cfg == 0) {
                cm_migrate_moodle_users(true, 0);
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

            //enrolment synchronization roles
            $old_role_sync = $CURMAN->config->enrolment_role_sync_student_role;
            self::config_set_value($configdata, 'enrolment_role_sync_student_role', 0);
            if (isset($configdata->enrolment_role_sync_student_role)
                && $configdata->enrolment_role_sync_student_role != 0
                && $configdata->enrolment_role_sync_student_role != $old_role_sync) {
                require_once CURMAN_DIRLOCATION . '/plugins/enrolment_role_sync/lib.php';
                enrolment_role_sync::student_sync_role_set();
            }
            $old_role_sync = $CURMAN->config->enrolment_role_sync_instructor_role;
            self::config_set_value($configdata, 'enrolment_role_sync_instructor_role', 0);
            if (isset($configdata->enrolment_role_sync_instructor_role)
                && $configdata->enrolment_role_sync_instructor_role != 0
                && $configdata->enrolment_role_sync_instructor_role != $old_role_sync) {
                require_once CURMAN_DIRLOCATION . '/plugins/enrolment_role_sync/lib.php';
                enrolment_role_sync::instructor_sync_role_set();
            }

            // autocreate settings
            self::config_set_value($configdata, 'autocreated_unknown_is_yes', 0);

            // legacy settings
            self::config_set_value($configdata, 'legacy_show_inactive_users', 0);

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

        $configform->display();
    }
}

?>
