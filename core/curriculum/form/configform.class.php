<?php
/**
 * Form used for editing / displaying a class record.
 *
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

if(!defined('NO_ROLE_ID')) {
    define('NO_ROLE_ID', 0);
}

require_once(CURMAN_DIRLOCATION . '/form/cmform.class.php');
require_once(CURMAN_DIRLOCATION . '/lib/certificate.php');

    class cmconfigform extends cmform {

        function definition() {
            global $USER, $CFG, $COURSE;

            $mform =& $this->_form;

            $strgeneral  = get_string('general');
            $strrequired = get_string('required');

        /// Add some extra hidden fields
            $mform->addElement('hidden', 's', 'cfg');

            // Track settings
            $mform->addElement('header', 'tracksettings', get_string('tracksettings', 'block_curr_admin'));
            $mform->addElement('checkbox', 'userdefinedtrack', get_string('userdefinedtrackyesno', 'block_curr_admin'));

            // Course catalog / Learning plan settings
            $mform->addElement('header', 'coursecatalog', get_string('coursecatalog', 'block_curr_admin'));
            $mform->addElement('checkbox', 'disablecoursecatalog', get_string('disablecoursecatalog', 'block_curr_admin'));
            $mform->addElement('text', 'catalog_collapse_count', get_string('catalog_collapse_count', 'block_curr_admin'));
            $mform->setType('catalog_collapse_count', PARAM_INT);

            // Curriculum expiration settings (ELIS-1172)
            $mform->addElement('checkbox', 'enable_curriculum_expiration', get_string('enable_curriculum_expiration', 'block_curr_admin'));

            $opts = array(
                CURR_EXPIRE_ENROL_START    => get_string('curriculum_expire_enrol_start', 'block_curr_admin'),
                CURR_EXPIRE_ENROL_COMPLETE => get_string('curriculum_expire_enrol_complete', 'block_curr_admin')
            );
            $mform->addElement('select', 'curriculum_expiration_start', get_string('curriculum_expiration_start', 'block_curr_admin'), $opts);

            // Generate certificate border & seal image options
            $border_images = cm_certificate_get_borders();
            $seal_images = cm_certificate_get_seals();

            // Certificate settings
            $mform->addElement('header', 'certificates', get_string('certificates', 'block_curr_admin'));
            $mform->addElement('checkbox', 'disablecertificates', get_string('disablecertificates', 'block_curr_admin'));
            $mform->addElement('select', 'certificate_border_image', get_string('certificate_border_image', 'block_curr_admin'), $border_images);
            $mform->setHelpButton('certificate_border_image', array('config/certificate_border_image', get_string('certificate_border_image', 'block_curr_admin'), 'block_curr_admin'));
            $mform->addElement('select', 'certificate_seal_image', get_string('certificate_seal_image', 'block_curr_admin'), $seal_images);
            $mform->setHelpButton('certificate_seal_image', array('config/certificate_seal_image', get_string('certificate_seal_image', 'block_curr_admin'), 'block_curr_admin'));

            // Interface settings
            $mform->addElement('header', 'interfacesettings', get_string('interfacesettings', 'block_curr_admin'));
            $mform->addElement('checkbox', 'time_format_12h', get_string('time_format_12h', 'block_curr_admin'));
            $mform->addElement('checkbox', 'mymoodle_redirect', get_string('mymoodle_redirect', 'block_curr_admin'));

            // User settings
            $mform->addElement('header', 'usersettings', get_string('usersettings', 'block_curr_admin'));
            $mform->addElement('checkbox', 'auto_assign_user_idnumber', get_string('auto_assign_user_idnumber', 'block_curr_admin'));
            $mform->setHelpButton('auto_assign_user_idnumber', array('config/auto_assign_user_idnumber', get_string('auto_assign_user_idnumber', 'block_curr_admin'), 'block_curr_admin'));

            //default instructor role

            $roles = array();
            $roles[NO_ROLE_ID] = get_string('noroleselected', 'block_curr_admin');

            if($role_records = get_records('role', '', '', 'id')) {
                foreach($role_records as $id => $role_record) {
                    $roles[$id] = $role_record->name;
                }
            }

            $mform->addElement('select', 'default_instructor_role', get_string('instructorrole', 'block_curr_admin'), $roles);
            $mform->setHelpButton('default_instructor_role', array('config/default_instructor_role', get_string('instructorrole', 'block_curr_admin'), 'block_curr_admin'));

            $mform->addElement('checkbox', 'restrict_to_elis_enrolment_plugin', get_string('restrict_to_elis_enrolment_plugin', 'block_curr_admin'));
            $mform->setHelpButton('restrict_to_elis_enrolment_plugin', array('config/restrict_to_elis_enrolment_plugin', get_string('restrict_to_elis_enrolment_plugin','block_curr_admin'), 'block_curr_admin'));

            // Cluster settings
            $mform->addElement('header', 'clustersettings', get_string('clustersettings', 'crlm_cluster_groups'));
            $mform->addElement('checkbox', 'cluster_groups', get_string('cluster_groups', 'crlm_cluster_groups'));
            $mform->setHelpButton('cluster_groups', array('global/cluster_groups', get_string('cluster_groups', 'crlm_cluster_groups'), 'crlm_cluster_groups'));
            $mform->addElement('checkbox', 'site_course_cluster_groups', get_string('site_course_cluster_groups', 'crlm_cluster_groups'));
            $mform->setHelpButton('site_course_cluster_groups', array('global/site_course_cluster_groups', get_string('site_course_cluster_groups', 'crlm_cluster_groups'), 'crlm_cluster_groups'));
            $mform->addElement('checkbox', 'cluster_groupings', get_string('cluster_groupings', 'crlm_cluster_groups'));
            $mform->setHelpButton('cluster_groupings', array('global/cluster_groupings', get_string('cluster_groupings', 'crlm_cluster_groups'), 'crlm_cluster_groups'));

            //settings specifically for the curriculum administration block
            $mform->addElement('header', 'block_curr_admin_settings', get_string('block_curr_admin_settings', 'block_curr_admin'));

            //number of icons of each type to display in each section of the tree
            $mform->addElement('text', 'num_block_icons', get_string('num_block_icons', 'block_curr_admin'));
            $mform->setType('num_block_icons', PARAM_INT);
            $mform->setHelpButton('num_block_icons', array('config/num_block_icons', get_string('num_block_icons', 'block_curr_admin'), 'block_curr_admin'));

            //whether to display clusters at the top level
            $mform->addElement('advcheckbox', 'display_clusters_at_top_level', get_string('display_clusters_at_top_level', 'block_curr_admin'), null, null, array(0, 1));
            $mform->setHelpButton('display_clusters_at_top_level', array('config/display_clusters_at_top_level', get_string('display_clusters_at_top_level', 'block_curr_admin'), 'block_curr_admin'));

            //whether to display curricula at the top level
            $mform->addElement('checkbox', 'display_curricula_at_top_level', get_string('display_curricula_at_top_level', 'block_curr_admin'), null, null, array(0, 1));
            $mform->setHelpButton('display_curricula_at_top_level', array('config/display_curricula_at_top_level', get_string('display_curricula_at_top_level', 'block_curr_admin'), 'block_curr_admin'));

            //default role assignments on cm entities
            $mform->addElement('header', 'default_role_assignment_settings', get_string('default_role_assignment_settings', 'block_curr_admin'));

            // we must not use assignable roles here:
            //   1/ unsetting roles as assignable for admin might bork the settings!
            //   2/ default user role should not be assignable anyway
            $allroles = array(0 => get_string('no_default_role', 'block_curr_admin'));
            $nonguestroles = array();
            if ($roles = get_all_roles()) {
                foreach ($roles as $role) {
                    $rolename = strip_tags(format_string($role->name, true));
                    $allroles[$role->id] = $rolename;
                    if (!isset($guestroles[$role->id])) {
                        $nonguestroles[$role->id] = $rolename;
                    }
                }
            }

            //default cluster role
            $mform->addElement('select', 'default_cluster_role_id', get_string('default_cluster_role_id', 'block_curr_admin'), $allroles);
            $mform->setHelpButton('default_cluster_role_id', array('config/default_cluster_role_id', get_string('default_cluster_role_id', 'block_curr_admin'), 'block_curr_admin'));

            //default curriculum role
            $mform->addElement('select', 'default_curriculum_role_id', get_string('default_curriculum_role_id', 'block_curr_admin'), $allroles);
            $mform->setHelpButton('default_curriculum_role_id', array('config/default_curriculum_role_id', get_string('default_curriculum_role_id', 'block_curr_admin'), 'block_curr_admin'));

            //default course role
            $mform->addElement('select', 'default_course_role_id', get_string('default_course_role_id', 'block_curr_admin'), $allroles);
            $mform->setHelpButton('default_course_role_id', array('config/default_course_role_id', get_string('default_course_role_id', 'block_curr_admin'), 'block_curr_admin'));

            //default class role
            $mform->addElement('select', 'default_class_role_id', get_string('default_class_role_id', 'block_curr_admin'), $allroles);
            $mform->setHelpButton('default_class_role_id', array('config/default_class_role_id', get_string('default_class_role_id', 'block_curr_admin'), 'block_curr_admin'));

            //default track role
            $mform->addElement('select', 'default_track_role_id', get_string('default_track_role_id', 'block_curr_admin'), $allroles);
            $mform->setHelpButton('default_track_role_id', array('config/default_track_role_id', get_string('default_track_role_id', 'block_curr_admin'), 'block_curr_admin'));

            // enrolment role synchronization settings
            $mform->addElement('header', 'enrolment_role_sync', get_string('enrolment_role_sync_settings', 'crlm_enrolment_role_sync'));

            $mform->addElement('select', 'enrolment_role_sync_student_role', get_string('student_role', 'crlm_enrolment_role_sync'), $allroles);
            $mform->setHelpButton('enrolment_role_sync_student_role', array('student_role', get_string('student_role', 'crlm_enrolment_role_sync'), 'crlm_enrolment_role_sync'));

            $mform->addElement('select', 'enrolment_role_sync_instructor_role', get_string('instructor_role', 'crlm_enrolment_role_sync'), $allroles);
            $mform->setHelpButton('enrolment_role_sync_instructor_role', array('instructor_role', get_string('instructor_role', 'crlm_enrolment_role_sync'), 'crlm_enrolment_role_sync'));

            // autocreate settings
            $mform->addElement('header', 'autocreate', get_string('autocreate_settings', 'block_curr_admin'));

            $mform->addElement('advcheckbox', 'autocreated_unknown_is_yes', get_string('autocreated_unknown_is_yes', 'block_curr_admin'), null, null, array(0, 1));
            $mform->setHelpButton('autocreated_unknown_is_yes', array('config/autocreated_unknown_is_yes', get_string('autocreated_unknown_is_yes', 'block_curr_admin'), 'block_curr_admin'));

            // legacy settings
            $mform->addElement('header', 'legacy', get_string('legacy_settings', 'block_curr_admin'));

            $mform->addElement('advcheckbox', 'legacy_show_inactive_users', get_string('legacy_show_inactive_users', 'block_curr_admin'), null, null, array(0, 1));
            $mform->setHelpButton('legacy_show_inactive_users', array('config/legacy_show_inactive_users', get_string('legacy_show_inactive_users', 'block_curr_admin'), 'block_curr_admin'));

            $this->add_action_buttons();
        }

        function validation($data, $files) {
            $errors = parent::validation($data, $files);

            if(intval($data['catalog_collapse_count']) <= 0) {
                $errors['catalog_collapse_count'] = get_string('error_catalog_collapse_count', 'block_curr_admin');
            }

            if(intval($data['num_block_icons']) <= 0) {
                $errors['num_block_icons'] = get_string('error_num_block_icons', 'block_curr_admin');
            }

            return $errors;
        }

    }


?>
