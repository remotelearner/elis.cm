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

// This file keeps track of upgrades to
// the curr_admin block
//
// Sometimes, changes between versions involve
// alterations to database structures and other
// major things that may break installations.
//
// The upgrade function in this file will attempt
// to perform all the necessary actions to upgrade
// your older installtion to the current version.
//
// If there's something it cannot do itself, it
// will tell you what you need to do.
//
// The commands in here will all be database-neutral,
// using the functions defined in lib/ddllib.php

function xmldb_block_curr_admin_upgrade($oldversion = 0) {
    global $CFG, $THEME, $db;

    $result = true;

    if ($oldversion < 2009010102) {
        $context = get_context_instance(CONTEXT_SYSTEM, SITEID);

        if ($role = get_record('role', 'shortname', 'curriculumadmin')) {
            if ($role->name == 'Bundle Administrator') {
                $role->name = 'Curriculum Administrator';
                addslashes_object($role);
                update_record('role', $role);
            }
        }

        if (!empty($role->id)) {
            require_once dirname(__FILE__) . '/access.php';

            if (!empty($block_curr_admin_capabilities)) {
                foreach ($block_curr_admin_capabilities as $capname => $caprules) {
                    $result = $result && assign_capability($capname, CAP_ALLOW, $role->id, $context->id);
                }
            }
        }
    }

    if ($oldversion < 2009010103) {
        $table = new XMLDBTable('crlm_curriculum');
        $field = new XMLDBField('timetocomplete');
        $field->setAttributes(XMLDB_TYPE_CHAR, '64', NULL, XMLDB_NOTNULL, NULL, NULL, NULL, '0h, 0d, 0w, 0m, 0y', 'timemodified');
        $result = $result && add_field($table, $field);

        $field = new XMLDBField('frequency');
        $field->setAttributes(XMLDB_TYPE_CHAR, '64', NULL, XMLDB_NOTNULL, NULL, NULL, NULL, '0h, 0d, 0w, 0m, 0y', 'timetocomplete');
        $result = $result && add_field($table, $field);
    }

    if ($oldversion < 2009010104) {
        $table = new XMLDBTable('crlm_config');
        $table->comment = 'Curriculum management configuration values.';

        // fields
        $f = $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', false, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $f = $table->addFieldInfo('name', XMLDB_TYPE_CHAR, '255', null, false, null, null, null, null);
        $f = $table->addFieldInfo('value', XMLDB_TYPE_TEXT, 'medium', null, false, null, null, null, null);

        // PK and indexes
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->addIndexInfo('name_ix', XMLDB_INDEX_UNIQUE, array('name'));
        $result = $result && create_table($table);
    }

    if ($oldversion < 2009010105) {
        $table = new XMLDBTable('crlm_coursetemplate');
        $table->comment = 'Course templates';

        // fields
        $f = $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', false, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $f = $table->addFieldInfo('courseid', XMLDB_TYPE_INTEGER, '10', false, XMLDB_NOTNULL, null, null, null, null);
        $f = $table->addFieldInfo('location', XMLDB_TYPE_CHAR, '255', null, false, null, null, null, null);
        $f = $table->addFieldInfo('templateclass', XMLDB_TYPE_CHAR, '255', null, false, null, null, null, null);

        // PK and indexes
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->addIndexInfo('courseid_ix', XMLDB_INDEX_UNIQUE, array('courseid'));
        $result = $result && create_table($table);
    }

    if ($oldversion < 2009010106) {
        $table = new XMLDBTable('crlm_cluster_curriculum');
        $table->comment = 'Association between clusters and curricula';

        // fields
        $f = $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', false, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $f = $table->addFieldInfo('clusterid', XMLDB_TYPE_INTEGER, '10', null, true, null, null, null, null, 'id');
        $f->comment = 'Foreign key to cluster id';
        $f = $table->addFieldInfo('curriculumid', XMLDB_TYPE_INTEGER, '10', null, true, null, null, null, null, 'clusterid');
        $f->comment = 'Foreign key to curriculum id';

        // PK and indexes
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->addIndexInfo('cluster_idx', XMLDB_INDEX_NOTUNIQUE, array('clusterid'));
        $result = $result && create_table($table);
    }

    if ($oldversion < 2009010108) {
        $table = new XMLDBTable('crlm_cluster_track');
        $table->comment = 'Association between clusters and tracks';

        // fields
        $f = $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', false, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $f = $table->addFieldInfo('clusterid', XMLDB_TYPE_INTEGER, '10', null, true, null, null, null, null, 'id');
        $f->comment = 'Foreign key to cluster id';
        $f = $table->addFieldInfo('trackid', XMLDB_TYPE_INTEGER, '10', null, true, null, null, null, null, 'clusterid');
        $f->comment = 'Foreign key to track id';
        $f = $table->addFieldInfo('autounenrol', XMLDB_TYPE_INTEGER, '1', null, true, null, null, null, null, 'trackid');
        $f->comment = 'Whether or not to remove a user from classes when removed from cluster';

        // PK and indexes
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->addIndexInfo('cluster_idx', XMLDB_INDEX_NOTUNIQUE, array('clusterid'));
        $result = $result && create_table($table);


        $table = new XMLDBTable('crlm_usercluster');

        $f = new XMLDBField('autoenrol');
        $f->setAttributes(XMLDB_TYPE_INTEGER, '1', null, true, null, null, null, 1, 'clusterid');
        $f->comment = 'Whether users should be autoenrolled in tracks associated with this cluster.';

        $result = $result && add_field($table, $f);
    }

    if ($oldversion < 2009010109) {
    /// Define table crlm_class_moodle to be created
        $table = new XMLDBTable('crlm_class_moodle');

    /// Adding fields to table crlm_class_moodle
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('classid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $table->addFieldInfo('moodlecourseid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $table->addFieldInfo('enroltype', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, null, null, null, null, '0');
        $table->addFieldInfo('enrolplugin', XMLDB_TYPE_CHAR, '20', null, null, null, null, null, 'crlm');
        $table->addFieldInfo('timemodified', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');

    /// Adding keys to table crlm_class_moodle
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->addKeyInfo('mdl_currclasmood_clamoo_uix', XMLDB_KEY_UNIQUE, array('classid', 'moodlecourseid'));

    /// Launch create table for crlm_class_moodle
        $result = $result && create_table($table);
    }

    if ($oldversion < 2009010110) {
        $table = new XMLDBTable('crlm_user_track');
        $table->comment = 'User enrolment in tracks';

        // fields
        $f = $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', false, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $f = $table->addFieldInfo('userid', XMLDB_TYPE_INTEGER, '10', null, true, null, null, null, null, 'id');
        $f->comment = 'Foreign key to user id';
        $f = $table->addFieldInfo('trackid', XMLDB_TYPE_INTEGER, '10', null, true, null, null, null, null, 'userid');
        $f->comment = 'Foreign key to track id';

        // PK and indexes
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $result = $result && create_table($table);
    }

    if ($result && $oldversion < 2009010112) {

    /// Define table crlm_notification_log to be created
        $table = new XMLDBTable('crlm_notification_log');

    /// Adding fields to table crlm_notification_log
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('event', XMLDB_TYPE_CHAR, '166', null, null, null, null, null, null);
        $table->addFieldInfo('instance', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $table->addFieldInfo('userid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $table->addFieldInfo('data', XMLDB_TYPE_CHAR, '255', null, null, null, null, null, null);
        $table->addFieldInfo('timecreated', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');

    /// Adding keys to table crlm_notification_log
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));

    /// Adding indexes to table crlm_notification_log
        $table->addIndexInfo('event_inst_user_ix', XMLDB_INDEX_NOTUNIQUE, array('event', 'instance', 'userid'));

    /// Launch create table for crlm_notification_log
        $result = $result && create_table($table);
    }

    if ($result && $oldversion < 2009010113) {
    /// Define index event_inst_user_ix (not unique) to be dropped from crlm_notification_log
        $table = new XMLDBTable('crlm_notification_log');
        $index = new XMLDBIndex('event_inst_user_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('event', 'instance', 'userid'));

    /// Launch drop index event_inst_user_ix
        $result = $result && drop_index($table, $index);

    /// Define index event_inst_user_ix (not unique) to be added to crlm_notification_log
        $table = new XMLDBTable('crlm_notification_log');
        $index = new XMLDBIndex('event_inst_user_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('userid', 'instance', 'event'));

    /// Launch add index event_inst_user_ix
        $result = $result && add_index($table, $index);
    }

    if ($result && $oldversion < 2009010114) {
        // Creating track table
        $table = new XMLDBTable('crlm_track');
        $table->comment = 'Track table';

        // fields
        $f = $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', false, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $f = $table->addFieldInfo('curid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $f = $table->addFieldInfo('idnumber', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null, null);
        $f = $table->addFieldInfo('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null);
        $f = $table->addFieldInfo('description', XMLDB_TYPE_TEXT, 'small', null, XMLDB_NOTNULL, null, null, null, null);
        $f = $table->addFieldInfo('startdate', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $f = $table->addFieldInfo('enddate', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $f = $table->addFieldInfo('timecreated', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $f = $table->addFieldInfo('timemodified', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');

        // PK and indexes
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->addIndexInfo('track_curr_idx', XMLDB_INDEX_NOTUNIQUE, array('curid'));
        $result = $result && create_table($table);

        $table = new XMLDBTable('crlm_track_class');
        $table->comment = 'Track class table';
        $f = $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', false, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $f = $table->addFieldInfo('trackid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $f = $table->addFieldInfo('classid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $f = $table->addFieldInfo('courseid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $f = $table->addFieldInfo('requried', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $f = $table->addFieldInfo('autoenrol', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $f = $table->addFieldInfo('default', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $f = $table->addFieldInfo('timecreated', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $f = $table->addFieldInfo('timemodified', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');

        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->addIndexInfo('trackid_idx', XMLDB_INDEX_NOTUNIQUE, array('trackid'));
        $table->addIndexInfo('track_classid_idx', XMLDB_INDEX_NOTUNIQUE, array('classid'));
        $table->addIndexInfo('track_courseid_idx', XMLDB_INDEX_NOTUNIQUE, array('courseid'));

        $result = $result && create_table($table);
    }

    if ($result && $oldversion < 2009010115) {

    /// Define table crlm_cluster_profile to be created
        $table = new XMLDBTable('crlm_cluster_profile');

    /// Adding fields to table crlm_cluster_profile
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('clusterid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('fieldid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('value', XMLDB_TYPE_TEXT, 'big', null, XMLDB_NOTNULL, null, null, null, null);

    /// Adding keys to table crlm_cluster_profile
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));

    /// Launch create table for crlm_cluster_profile
        $result = $result && create_table($table);


    /// Define table crlm_cluster_assignments to be created
        $table = new XMLDBTable('crlm_cluster_assignments');

    /// Adding fields to table crlm_cluster_assignments
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('clusterid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('userid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('plugin', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null, null, null);

    /// Adding keys to table crlm_cluster_assignments
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));

    /// Launch create table for crlm_cluster_assignments
        $result = $result && create_table($table);
    }

    if ($result && $oldversion < 2009010116) {
        $table = new XMLDBTable('crlm_track_class');

        $field = new XMLDBField('default');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, '0', 'autoenrol');
        $result = $result && drop_field($table,$field);

        $field = new XMLDBField('defaulttrack');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, '0', 'autoenrol');
        $result = $result && add_field($table,$field);

    }

    if ($result && $oldversion < 2009010117) {
    /// Remove obsolete job code tables if they exist.
        $table = new XMLDBTable('crlm_jobcode_list');
        if (table_exists($table)) {
            drop_table($table);
        }

        $table = new XMLDBTable('crlm_curriculum_jobcode');
        if (table_exists($table)) {
            drop_table($table);
        }
    }

    if ($result && $oldversion < 2009010118) {
    /// Removing defaulttrack column from table
        $table = new XMLDBTable('crlm_track_class');

        $field = new XMLDBField('defaulttrack');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, '0', 'autoenrol');
        $result = $result && drop_field($table,$field);

    /// Adding defaulttrack column to table
        $table = new XMLDBTable('crlm_track');
        $field = new XMLDBField('defaulttrack');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, '0', 'enddate');
        $result = $result && add_field($table,$field);
    }

    if ($result && $oldversion < 2009010119) {

    /// Define field completed to be added to crlm_curriculum_assignment
        $table = new XMLDBTable('crlm_curriculum_assignment');
        $field = new XMLDBField('completed');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'curriculumid');

    /// Launch add field completed
        $result = $result && add_field($table, $field);

    /// Define field completiontime to be added to crlm_curriculum_assignment
        $field = new XMLDBField('timecompleted');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'completed');

    /// Launch add field completiontime
        $result = $result && add_field($table, $field);

    /// Define field credits to be added to crlm_curriculum_assignment
        $field = new XMLDBField('credits');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'timecompleted');

    /// Launch add field credits
        $result = $result && add_field($table, $field);

    /// Define field locked to be added to crlm_curriculum_assignment
        $table = new XMLDBTable('crlm_curriculum_assignment');
        $field = new XMLDBField('locked');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'credits');

    /// Launch add field locked
        $result = $result && add_field($table, $field);

    /// Define key mdl_currcurrassi_usecur_uix (unique) to be dropped from crlm_curriculum_assignment
        $key = new XMLDBKey('mdl_currcurrassi_usecur_uix');
        $key->setAttributes(XMLDB_KEY_UNIQUE, array('userid', 'curriculumid'));

    /// Launch drop key mdl_currcurrassi_usecur_uix
        $result = $result && drop_key($table, $key);

    /// Define index mdl_currcurrassi_usecurcom_ix (not unique) to be added to crlm_curriculum_assignment
        $index = new XMLDBIndex('mdl_currcurrassi_usecurcom_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('userid', 'curriculumid', 'completed'));

    /// Launch add index mdl_currcurrassi_usecurcom_ix
        $result = $result && add_index($table, $index);

    /// Define index completed_ix (not unique) to be added to crlm_curriculum_assignment
        $index = new XMLDBIndex('completed_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('completed'));

    /// Launch add index completed_ix
        $result = $result && add_index($table, $index);
    }

    if ($result && $oldversion < 2009010120) {

    /// Define field autoenrol to be added to crlm_cluster_assignments
        $table = new XMLDBTable('crlm_cluster_assignments');
        $field = new XMLDBField('autoenrol');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '1', 'plugin');

    /// Launch add field autoenrol
        $result = $result && add_field($table, $field);
    }

    if ($result && $oldversion < 2009010121) {
        if (!record_exists('mnet_application', 'name', 'java')) {
            $application = new stdClass();
            $application->name = 'java';
            $application->display_name = 'Java servlet';
            $application->xmlrpc_server_url = '/mnet/server';
            $application->sso_land_url = '/mnet/land.jsp';
            $result = $result && insert_record('mnet_application', $application, false);
        }
    }

    if ($result && $oldversion < 2009010122) {
        $table = new XMLDBTable('crlm_track_class');

        $field = new XMLDBField('requried');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'courseid');
        $result = $result && drop_field($table,$field);
    }

    if ($result && $oldversion < 2009010125) {
        $result = $result && execute_sql('CREATE OR REPLACE VIEW `courseNforums` AS select `f`.`id` AS `forumid`,concat(`c`.`shortname`,_utf8\' | \',`f`.`name`) AS `courseNforumname` from (`mdl_forum` `f` join `mdl_course` `c` on((`c`.`id` = `f`.`course`))) order by `c`.`shortname`,`f`.`name`');
    }

    if ($result && $oldversion < 2009010126) {
        $table = new XMLDBTable('crlm_cluster_curriculum');
        $table->comment = 'Association between clusters and curricula';

        // fields
        $f = $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', false, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $f = $table->addFieldInfo('clusterid', XMLDB_TYPE_INTEGER, '10', null, true, null, null, null, null, 'id');
        $f->comment = 'Foreign key to cluster id';
        $f = $table->addFieldInfo('curriculumid', XMLDB_TYPE_INTEGER, '10', null, true, null, null, null, null, 'clusterid');
        $f->comment = 'Foreign key to curriculum id';

        // PK and indexes
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->addIndexInfo('cluster_idx', XMLDB_INDEX_NOTUNIQUE, array('clusterid'));
        $result = $result && (table_exists($table) || create_table($table));


        $table = new XMLDBTable('crlm_cluster_track');
        $table->comment = 'Association between clusters and tracks';

        // fields
        $f = $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', false, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $f = $table->addFieldInfo('clusterid', XMLDB_TYPE_INTEGER, '10', null, true, null, null, null, null, 'id');
        $f->comment = 'Foreign key to cluster id';
        $f = $table->addFieldInfo('trackid', XMLDB_TYPE_INTEGER, '10', null, true, null, null, null, null, 'clusterid');
        $f->comment = 'Foreign key to track id';
        $f = $table->addFieldInfo('autounenrol', XMLDB_TYPE_INTEGER, '1', null, true, null, null, null, null, 'trackid');
        $f->comment = 'Whether or not to remove a user from classes when removed from cluster';

        // PK and indexes
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->addIndexInfo('cluster_idx', XMLDB_INDEX_NOTUNIQUE, array('clusterid'));
        $result = $result && (table_exists($table) || create_table($table));
    }

    if ($result && $oldversion < 2009010127) {
        // fix silly typos
        $newtable = new XMLDBTable('crlm_user_track');
        $oldtable = new XMLDBTable('clrm_user_track');
        $result = $result && (table_exists($newtable) || rename_table($oldtable, 'crlm_user_track'));
        $oldtable = new XMLDBTable('clrm_cluster_track');
        $result = $result && (!table_exists($oldtable) || drop_table($oldtable));
        $oldtable = new XMLDBTable('clrm_cluster_curriculum');
        $result = $result && (!table_exists($oldtable) || drop_table($oldtable));
    }

    if ($result && $oldversion < 2009010128) {
        require_once($CFG->dirroot . '/curriculum/lib/lib.php');
        cm_migrate_moodle_users();
    }

    if ($result && $oldversion < 2009010131) {
    /// Get rid of any outdated cluster data we might have lying around.
        if ($CFG->dbfamily == 'postgres') {
            $sql = "DELETE FROM {$CFG->prefix}crlm_cluster_assignments
                    WHERE id IN (
                        SELECT ca.clusterid
                        FROM {$CFG->prefix}crlm_cluster_assignments ca
                        LEFT JOIN {$CFG->prefix}crlm_cluster c ON c.id = ca.clusterid
                        WHERE c.id IS NULL
                    )";

            $result = $result && execute_sql($sql);

            $sql = "DELETE FROM {$CFG->prefix}crlm_cluster_curriculum
                    WHERE id IN (
                        SELECT cc.clusterid
                        FROM {$CFG->prefix}crlm_cluster_curriculum cc
                        LEFT JOIN {$CFG->prefix}crlm_cluster c ON c.id = cc.clusterid
                        WHERE c.id IS NULL
                    )";

            $result = $result && execute_sql($sql);

            $sql = "DELETE FROM {$CFG->prefix}crlm_cluster_profile
                    WHERE id IN (
                        SELECT cp.clusterid
                        FROM {$CFG->prefix}crlm_cluster_profile cp
                        LEFT JOIN {$CFG->prefix}crlm_cluster c ON c.id = cp.clusterid
                        WHERE c.id IS NULL
                    )";

            $result = $result && execute_sql($sql);

            $sql = "DELETE FROM {$CFG->prefix}crlm_cluster_track
                    WHERE id IN (
                        SELECT ct.clusterid
                        FROM {$CFG->prefix}crlm_cluster_track ct
                        LEFT JOIN {$CFG->prefix}crlm_cluster c ON c.id = ct.clusterid
                        WHERE c.id IS NULL
                    )";

            $result = $result && execute_sql($sql);

            $sql = "DELETE FROM {$CFG->prefix}crlm_usercluster
                    WHERE id IN (
                        SELECT uc.clusterid
                        FROM {$CFG->prefix}crlm_usercluster uc
                        LEFT JOIN {$CFG->prefix}crlm_cluster c ON c.id = uc.clusterid
                        WHERE c.id IS NULL
                    )";

            $result = $result && execute_sql($sql);
        } else {
            $sql = "DELETE ca FROM {$CFG->prefix}crlm_cluster_assignments ca
                    LEFT JOIN {$CFG->prefix}crlm_cluster c ON c.id = ca.clusterid
                    WHERE c.id IS NULL";

            $result = $result && execute_sql($sql);

            $sql = "DELETE cc FROM {$CFG->prefix}crlm_cluster_curriculum cc
                    LEFT JOIN {$CFG->prefix}crlm_cluster c ON c.id = cc.clusterid
                    WHERE c.id IS NULL";

            $result = $result && execute_sql($sql);

            $sql = "DELETE cp FROM {$CFG->prefix}crlm_cluster_profile cp
                    LEFT JOIN {$CFG->prefix}crlm_cluster c ON c.id = cp.clusterid
                    WHERE c.id IS NULL";

            $result = $result && execute_sql($sql);

            $sql = "DELETE ct FROM {$CFG->prefix}crlm_cluster_track ct
                    LEFT JOIN {$CFG->prefix}crlm_cluster c ON c.id = ct.clusterid
                    WHERE c.id IS NULL";

            $result = $result && execute_sql($sql);

            $sql = "DELETE uc FROM {$CFG->prefix}crlm_usercluster uc
                    LEFT JOIN {$CFG->prefix}crlm_cluster c ON c.id = uc.clusterid
                    WHERE c.id IS NULL";

            $result = $result && execute_sql($sql);
        }
    }

    if ($result && $oldversion < 2009010133) {
    /// Define field leader to be added to crlm_cluster_assignments
        $table = new XMLDBTable('crlm_cluster_assignments');
        $field = new XMLDBField('leader');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'autoenrol');

    /// Launch add field leader
        $result = $result && add_field($table, $field);

    /// Define field leader to be added to crlm_usercluster
        $table = new XMLDBTable('crlm_usercluster');
        $field = new XMLDBField('leader');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'autoenrol');

    /// Launch add field leader
        $result = $result && add_field($table, $field);
    }

    if ($result && $oldversion < 2009010134) {

    /// Define field inactive to be added to crlm_user
        $table = new XMLDBTable('crlm_user');
        $field = new XMLDBField('inactive');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, null, null, null, null, '0', 'timemodified');

    /// Launch add field inactive
        $result = $result && add_field($table, $field);
    }

    if ($result && $oldversion < 2009010137) {
        $roleid = get_field('role', 'id', 'shortname', 'curriculumadmin');

        if (!empty($roleid)) {
            $context = get_context_instance(CONTEXT_SYSTEM, SITEID);
            require_once dirname(dirname(__FILE__)) . '/db/access.php';

            if (!empty($block_curr_admin_capabilities)) {
                foreach ($block_curr_admin_capabilities as $capname => $caprules) {
                    $result = $result && assign_capability($capname, CAP_ALLOW, $roleid, $context->id);
                }
            }
        }
    }

    if($result && $oldversion < 2009010139) {
        global $CURMAN;

        require_once ($CFG->dirroot.'/curriculum/lib/classmoodlecourse.class.php');
        $moodleclasses = moodle_get_classes();

        if (!empty($moodleclasses)) {
            foreach ($moodleclasses as $class) {
                $context = get_context_instance(CONTEXT_COURSE, $class->moodlecourseid);

                $sql = "DELETE cmce
                    FROM {$CURMAN->db->prefix_table('user')} u
                    JOIN {$CURMAN->db->prefix_table('role_assignments')} ra ON u.id = ra.userid
                    JOIN {$CURMAN->db->prefix_table(STUTABLE)} cmce ON u.idnumber = cmce.user_idnumber
                    WHERE ra.roleid NOT IN ({$CFG->gradebookroles})
                    AND ra.contextid " . get_related_contexts_string($context) .
                    "AND cmce.classid = {$class->classid}";

                $result = $result && execute_sql($sql);
            }
        }
    }

    if ($result && $oldversion < 2009010140) {
        delete_records('crlm_cluster_profile', 'fieldid', 0);
    }

    if($result && $oldversion < 2009010141) {
        set_config('field_lock_idnumber', 'locked', 'auth/manual');
    }

    if ($result && $oldversion < 2009010143) {

    /// Define table crlm_wait_list to be created
        $table = new XMLDBTable('crlm_wait_list');

    /// Adding fields to table crlm_wait_list
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('classid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('userid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('timecreated', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $table->addFieldInfo('timemodified', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $table->addFieldInfo('position', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, null, null);

    /// Adding keys to table crlm_wait_list
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));

    /// Launch create table for crlm_wait_list
        $result = $result && create_table($table);
    }

    if($result && $oldversion < 2009010145) {
        $table = new XMLDBTable('crlm_wait_list');

        $field = new XMLDBField('enrolmenttime');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');

        $result = $result && add_field($table, $field);
    }

    if ($result && $oldversion < 2009010146) {
        // make sure trackclass's courseids are set

        // Let's just assume that all non-Postgres DB's use the same syntax as MySQL and call it a day.
        if ($CFG->dbfamily == 'postgres') {
            $sql = "UPDATE {$CFG->prefix}crlm_track_class
                       SET courseid = c.courseid
                      FROM {$CFG->prefix}crlm_track_class tc, {$CFG->prefix}crlm_class c
                     WHERE tc.classid = c.id AND tc.courseid = 0";
        } else {
            $sql = "UPDATE {$CFG->prefix}crlm_track_class tc, {$CFG->prefix}crlm_class c
                       SET tc.courseid = c.courseid
                     WHERE tc.classid = c.id AND tc.courseid = 0";
        }

        $result = $result && execute_sql($sql);
    }

    if ($result && $oldversion < 2009010147) {
        // make sure all users have an idnumber
        $users = get_records('crlm_user', 'idnumber', '');

        foreach ($users as $user) {
            $user = addslashes_recursive($user);
            $mu = addslashes_recursive(get_record('user', 'username', $user->username));
            if (empty($mu->idnumber)) {
                $user->idnumber = $mu->idnumber = $mu->username;
                update_record('user', $mu);
                update_record('crlm_user', $user);
            } else if (!get_record('crlm_user', 'idnumber', $mu->idnumber)) {
                $user->idnumber = $mu->idnumber;
                update_record('crlm_user', $user);
            } else if (!get_record('crlm_user', 'idnumber', $user->username)) {
                $user->idnumber = $mu->idnumber;
                update_record('crlm_user', $user);
            }
        }
    }

    if ($result && $oldversion < 2009010149) {
    /// Define index clusterid_idx (not unique) to be added to crlm_cluster_assignments
        $table = new XMLDBTable('crlm_cluster_assignments');
        $index = new XMLDBIndex('clusterid_idx');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('clusterid'));

    /// Launch add index clusterid_idx
        $result = $result && add_index($table, $index);

    /// Define index userid_idx (not unique) to be added to crlm_cluster_assignments
        $table = new XMLDBTable('crlm_cluster_assignments');
        $index = new XMLDBIndex('userid_idx');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('userid'));

    /// Launch add index userid_idx
        $result = $result && add_index($table, $index);

    /// Define index clusterid_idx (not unique) to be added to crlm_cluster_profile
        $table = new XMLDBTable('crlm_cluster_profile');
        $index = new XMLDBIndex('clusterid_idx');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('clusterid'));

    /// Launch add index clusterid_idx
        $result = $result && add_index($table, $index);
    }

    if ($result && $oldversion < 2009010151) {
        $table = new XMLDBTable('crlm_curriculum');

        $field = new XMLDBField('priority');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');

        $result = $result && add_field($table, $field);
    }

    if($result && $oldversion < 2009010150) {
        require_once (CURMAN_DIRLOCATION . '/lib/curriculumcourse.class.php');

        $sql = "SELECT cp.id, cp.courseid, cc.curriculumid
                FROM {$CFG->prefix}crlm_course_prerequisite cp
                JOIN {$CFG->prefix}crlm_curriculum_course cc ON cc.id = cp.curriculumcourseid
                WHERE cp.courseid NOT IN (
                    SELECT _cc.courseid
                    FROM {$CFG->prefix}crlm_curriculum_course _cc
                    WHERE _cc.curriculumid = cc.curriculumid
                )";

        $students = get_records_sql($sql);

        $retval = 0;

        foreach($students as $student) {
            $data = new object();
            $data->curriculumid = $student->curriculumid;
            $data->courseid = $student->courseid;
            $data->timeperiod = 'year';

            $currprereq = new curriculumcourse($data);

            $retval = $result && $currprereq->add();
        }

        $results = $retval;
    }

    if ($result && $oldversion < 2009103001) {
        $table = new XMLDBTable('crlm_curriculum');

        $field = new XMLDBField('priority');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');

        $result = $result && add_field($table, $field);

    /// Define table context_levels to be created
        $table = new XMLDBTable('context_levels');

    /// Adding fields to table context_levels
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('component', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null, null);

    /// Adding keys to table context_levels
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));

    /// Adding indexes to table context_levels
        $table->addIndexInfo('name', XMLDB_INDEX_NOTUNIQUE, array('name'));
        $table->addIndexInfo('component', XMLDB_INDEX_NOTUNIQUE, array('component'));

    /// Launch create table for context_levels
        $result = $result && create_table($table);
    }

    if ($result && $oldversion < 2009103003)  {

    /// Define table crlm_field to be created
        $table = new XMLDBTable('crlm_field');

    /// Adding fields to table crlm_field
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('shortname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('name', XMLDB_TYPE_TEXT, 'big', null, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('datatype', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('description', XMLDB_TYPE_TEXT, 'big', null, null, null, null, null, null);
        $table->addFieldInfo('categoryid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('sortorder', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('required', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('locked', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('visible', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('forceunique', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('defaultdata', XMLDB_TYPE_TEXT, 'big', null, null, null, null, null, null);
        $table->addFieldInfo('params', XMLDB_TYPE_TEXT, 'big', null, null, null, null, null, null);
        $table->addFieldInfo('syncwithmoodle', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);

    /// Adding keys to table crlm_field
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));

    /// Adding indexes to table crlm_field
        $table->addIndexInfo('shortname_idx', XMLDB_INDEX_NOTUNIQUE, array('shortname'));

    /// Launch create table for crlm_field
        $result = $result && create_table($table);

    /// Define table crlm_field_category to be created
        $table = new XMLDBTable('crlm_field_category');

    /// Adding fields to table crlm_field_category
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('sortorder', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);

    /// Adding keys to table crlm_field_category
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));

    /// Launch create table for crlm_field_category
        $result = $result && create_table($table);

    /// Define table crlm_field_contextlevel to be created
        $table = new XMLDBTable('crlm_field_contextlevel');

    /// Adding fields to table crlm_field_contextlevel
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('fieldid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('contextlevel', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);

    /// Adding keys to table crlm_field_contextlevel
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));

    /// Launch create table for crlm_field_contextlevel
        $result = $result && create_table($table);

    /// Define table crlm_field_data to be created
        $table = new XMLDBTable('crlm_field_data');

    /// Adding fields to table crlm_field_data
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('contextid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('fieldid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('data', XMLDB_TYPE_TEXT, 'big', null, XMLDB_NOTNULL, null, null, null, null);

    /// Adding keys to table crlm_field_data
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));

    /// Adding indexes to table crlm_field_data
        $table->addIndexInfo('context_idx', XMLDB_INDEX_NOTUNIQUE, array('contextid'));

    /// Launch create table for crlm_field_data
        $result = $result && create_table($table);
    }

    //if ($result && $oldversion < 2010040501) {
    //    require_once($CFG->dirroot . '/blocks/curr_admin/lib.php');
    //    $result = $result && create_views(); // create with default prefix
    //    $result = $result && create_views(''); // create with no prefix
    //}

    if ($result && $oldversion < 2010040501) {
        $table = new XMLDBTable('crlm_field_map');

    /// Adding fields to table crlm_field_data
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('context', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('type', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('elis_field', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('data_field', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null, null);

    /// Adding keys to table crlm_field_data
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));

    /// Launch create table for crlm_field_data
        $result = $result && create_table($table);
    }

    if ($result && $oldversion < 2010040501) {
        require_once "{$CFG->dirroot}/curriculum/lib/customfield.class.php";
        // make sure all ELIS users have a context
        update_capabilities('block/curr_admin');
        $ctxlvl = context_level_base::get_custom_context_level('user', 'block_curr_admin');
        $rs = get_recordset('crlm_user');
        while($rec = rs_fetch_next_record($rs)){
            get_context_instance($ctxlvl, $rec->id);
        }

        // sync profile fields
        $fields = field::get_for_context_level($ctxlvl);
        $fields = $fields ? $fields : array();
        require_once($CFG->dirroot . '/curriculum/plugins/moodle_profile/custom_fields.php');
        foreach ($fields as $field) {
            $fieldobj = new field($field);
            $sync_profile_field_with_moodle();
        }
    }

    if($result && $oldversion < 2010040501) {

        require_once($CFG->dirroot . '/curriculum/lib/notifications.php');

        if(!empty($CFG->coursemanager)) {

            $context_course = CONTEXT_COURSE;

            $sql = "SELECT role_assignments.* FROM {$CFG->prefix}role_assignments role_assignments
                    JOIN {$CFG->prefix}context context
                    ON role_assignments.contextid = context.id
                    WHERE role_assignments.roleid IN ({$CFG->coursemanager})
                    AND context.contextlevel = {$context_course}";

            if($records = get_records_sql($sql)) {
                foreach($records as $record) {
                    cm_assign_instructor_from_mdl($record);
                }
            }

        }
    }

    if($result && $oldversion < 2010063001) {
        $table = new XMLDBTable('crlm_curriculum_assignment');
        $field = new XMLDBField('user_idnumber');
        $result = $result && drop_field($table, $field);

        $table = new XMLDBTable('crlm_class_enrolment');
        $field = new XMLDBField('user_idnumber');
        $result = $result && drop_field($table, $field);

        $table = new XMLDBTable('crlm_class_instructor');
        $field = new XMLDBField('user_idnumber');
        $result = $result && drop_field($table, $field);

        $table = new XMLDBTable('crlm_class_attendance');
        $field = new XMLDBField('user_idnumber');
        $result = $result && drop_field($table, $field);
    }

    //if ($result && $oldversion < 2010063002) {
    //    require_once($CFG->dirroot . '/blocks/curr_admin/lib.php');
    //    $result = $result && create_views(); // create with default prefix
    //    $result = $result && create_views(''); // create with no prefix
    //}

    if ($result && $oldversion < 2010040505) {
        require_once($CFG->dirroot . '/curriculum/lib/lib.php');
        $result = $result && cm_notify_duplicate_user_info(true);
    }

    if ($result && $oldversion < 2010040506 && $oldversion >= 2010040501) {
        global $CFG, $CURMAN;

        // fix instructor assignments that were migrated incorrectly in the
        // 2010040501 upgrade code (ELIS-1171)

        // remove the obvious errors (instructors assigned to a non-existent class)
        $context_course = CONTEXT_COURSE;

        $sql = "DELETE
                  FROM {$CFG->prefix}crlm_class_instructor
                 WHERE NOT EXISTS (SELECT 'x' FROM {$CFG->prefix}crlm_class cmclass
                                    WHERE cmclass.id = {$CFG->prefix}crlm_class_instructor.classid)";

        $result = $result && execute_sql($sql);

        // warn about other potentially incorrect instructor assignments
        require_once($CFG->dirroot . '/curriculum/lib/lib.php');
        cm_notify_incorrect_instructor_assignment(true);

        // make sure the correct assignments are added
        if(!empty($CFG->coursemanager)) {
            require_once($CFG->dirroot . '/curriculum/lib/notifications.php');

            $context_course = CONTEXT_COURSE;

            $sql = "SELECT role_assignments.* FROM {$CFG->prefix}role_assignments role_assignments
                    JOIN {$CFG->prefix}context context
                    ON role_assignments.contextid = context.id
                    WHERE role_assignments.roleid IN ({$CFG->coursemanager})
                    AND context.contextlevel = {$context_course}";

            if($records = get_records_sql($sql)) {
                foreach($records as $record) {
                    cm_assign_instructor_from_mdl($record);
                }
            }
        }
    }

    if ($result && $oldversion < 2010063002) {
        //get the class table
        $table = new XMLDBTable('crlm_class');

        //add the auto enrol enabled flag
        $field = new XMLDBField('enrol_from_waitlist');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, null, '0');

        $result = $result && add_field($table, $field);
    }

    if ($result && $oldversion < 2010063005) {

    /// Define table crlm_field_data to be dropped
        $table = new XMLDBTable('crlm_field_map');

    /// Launch drop table for crlm_field_data
        $result = $result && drop_table($table);
    }

    if ($result && $oldversion < 2010063006) {

    /// Define table crlm_field_data to be renamed to crlm_field_data_text
        $table = new XMLDBTable('crlm_field_data');

    /// Define index context_idx (not unique) to be dropped form crlm_field_data_text
        $index = new XMLDBIndex('context_idx');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('contextid'));

    /// Launch drop index context_idx
        $result = $result && drop_index($table, $index);

    /// Changing nullability of field contextid on table crlm_field_data_text to null
        $field = new XMLDBField('contextid');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, null, null, 'id');

    /// Define index context_idx (not unique) to be added to crlm_field_data_text
        $index = new XMLDBIndex('context_idx');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('contextid'));

    /// Launch add index context_idx
        $result = $result && add_index($table, $index);

    /// Define index field_idx (not unique) to be added to crlm_field_data_text
        $index = new XMLDBIndex('field_idx');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('fieldid'));

    /// Launch add index field_idx
        $result = $result && add_index($table, $index);

    /// Launch change of nullability for field contextid
        $result = $result && change_field_notnull($table, $field);

    /// Launch rename table for crlm_field_data
        $result = $result && rename_table($table, 'crlm_field_data_text');


    /// Define table crlm_field_owner to be created
        $table = new XMLDBTable('crlm_field_owner');

    /// Adding fields to table crlm_field_owner
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('fieldid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, null, null);
        $table->addFieldInfo('plugin', XMLDB_TYPE_CHAR, '255', null, null, null, null, null, null);
        $table->addFieldInfo('exclude', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, null, null, null, null, '0');
        $table->addFieldInfo('params', XMLDB_TYPE_TEXT, 'big', null, null, null, null, null, null);

    /// Adding keys to table crlm_field_owner
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));

    /// Adding indexes to table crlm_field_owner
        $table->addIndexInfo('field_idx', XMLDB_INDEX_NOTUNIQUE, array('fieldid'));

    /// Launch create table for crlm_field_owner
        $result = $result && create_table($table);


    /// Define table crlm_field_category_context to be created
        $table = new XMLDBTable('crlm_field_category_context');

    /// Adding fields to table crlm_field_category_context
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('categoryid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, null, null);
        $table->addFieldInfo('contextlevel', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, null, null);

    /// Adding keys to table crlm_field_category_context
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));

    /// Adding indexes to table crlm_field_category_context
        $table->addIndexInfo('contextlevel_idx', XMLDB_INDEX_NOTUNIQUE, array('contextlevel'));
        $table->addIndexInfo('category_idx', XMLDB_INDEX_NOTUNIQUE, array('categoryid'));

    /// Launch create table for crlm_field_category_context
        $result = $result && create_table($table);


        $usercontextid = context_level_base::get_custom_context_level('user', 'block_curr_admin');
        if ($usercontextid) {
            $sql = "INSERT INTO {$CFG->prefix}crlm_field_category_context
                           (categoryid, contextlevel)
                    SELECT id, $usercontextid
                      FROM {$CFG->prefix}crlm_field_category";
            $result = $result && execute_sql($sql);
        }


    /// Define table crlm_field_data_int to be created
        $table = new XMLDBTable('crlm_field_data_int');

    /// Adding fields to table crlm_field_data_int
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('contextid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, null, null);
        $table->addFieldInfo('fieldid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('data', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, null);

    /// Adding keys to table crlm_field_data_int
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));

    /// Adding indexes to table crlm_field_data_int
        $table->addIndexInfo('context_idx', XMLDB_INDEX_NOTUNIQUE, array('contextid'));
        $table->addIndexInfo('field_idx', XMLDB_INDEX_NOTUNIQUE, array('fieldid'));

    /// Launch create table for crlm_field_data_int
        $result = $result && create_table($table);


    /// Define table crlm_field_data_num to be created
        $table = new XMLDBTable('crlm_field_data_num');

    /// Adding fields to table crlm_field_data_num
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('contextid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, null, null);
        $table->addFieldInfo('fieldid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('data', XMLDB_TYPE_NUMBER, '15, 5', null, XMLDB_NOTNULL, null, null, null, null);

    /// Adding keys to table crlm_field_data_num
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));

    /// Adding indexes to table crlm_field_data_num
        $table->addIndexInfo('context_idx', XMLDB_INDEX_NOTUNIQUE, array('contextid'));
        $table->addIndexInfo('field_idx', XMLDB_INDEX_NOTUNIQUE, array('fieldid'));

    /// Launch create table for crlm_field_data_num
        $result = $result && create_table($table);


    /// Define table crlm_field_data_char to be created
        $table = new XMLDBTable('crlm_field_data_char');

    /// Adding fields to table crlm_field_data_char
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('contextid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, null, null);
        $table->addFieldInfo('fieldid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('data', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null, null);

    /// Adding keys to table crlm_field_data_char
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));

    /// Adding indexes to table crlm_field_data_char
        $table->addIndexInfo('context_idx', XMLDB_INDEX_NOTUNIQUE, array('contextid'));
        $table->addIndexInfo('field_idx', XMLDB_INDEX_NOTUNIQUE, array('fieldid'));

    /// Launch create table for crlm_field_data_char
        $result = $result && create_table($table);


        $records = get_records('crlm_field');
        if ($records) {
            // FIXME: set data type based on old data type
            foreach ($records as $record) {
                unset($record->name);
                unset($record->shortname);
                unset($record->description);
                $record->defaultdata = addslashes($record->defaultdata);
                if (isset($record->syncwithmoodle)) {
                    // make sure the crlm_field table hasn't been upgraded yet
                    switch ($record->syncwithmoodle) {
                    case 2:
                        // sync from Moodle
                        // create "moodle_profile" owner
                        if (!record_exists('crlm_field_owner', 'fieldid', $record->id, 'plugin', 'moodle_profile')) {
                            $owner = new stdClass;
                            $owner->fieldid = $record->id;
                            $owner->plugin = 'moodle_profile';
                            $owner->exclude = true;
                            $result = $result && insert_record('crlm_field_owner', $owner);
                        }
                        // create "manual" owner
                        if (!record_exists('crlm_field_owner', 'fieldid', $record->id, 'plugin', 'manual')) {
                            $owner = new stdClass;
                            $owner->fieldid = $record->id;
                            $owner->plugin = 'manual';
                            $owner->exclude = false;
                            $owner->params = array('edit_capability' => 'disabled');
                            if (!$record->visible) {
                                $owner->params['view_capability'] = 'moodle/user:viewhiddendetails';
                            }
                            $owner->params = serialize($owner->params);
                            $result = $result && insert_record('crlm_field_owner', $owner);
                        }
                        $record->datatype = 'text';
                        break;
                    case 1:
                        // sync to Moodle
                        // create "moodle_profile" owner
                        if (!record_exists('crlm_field_owner', 'fieldid', $record->id, 'plugin', 'moodle_profile')) {
                            $owner = new stdClass;
                            $owner->fieldid = $record->id;
                            $owner->plugin = 'moodle_profile';
                            $owner->exclude = false;
                            $result = $result && insert_record('crlm_field_owner', $owner);
                        }
                        // NOTE: fall through
                    default:
                        // no sync or invalid user
                        // create "manual" owner
                        $controltype = $record->datatype;
                        $record->datatype = 'text';
                        if (!record_exists('crlm_field_owner', 'fieldid', $record->id, 'plugin', 'manual')) {
                            $owner = new stdClass;
                            $owner->fieldid = $record->id;
                            $owner->plugin = 'manual';
                            $owner->exclude = false;
                            $owner->params = array('control' => $controltype,
                                                   'required' => $record->required);
                            if ($record->locked) {
                                $owner->params['edit_capability'] = 'moodle/user:update';
                            }
                            if (!$record->visible) {
                                $owner->params['view_capability'] = 'moodle/user:viewhiddendetails';
                            }
                            if (!empty($record->params)) {
                                $owner->params += unserialize($record->params);
                            }
                            switch ($controltype) {
                            case 'checkbox':
                                // legacy checkboxes are all boolean
                                $record->datatype = 'bool';
                                $data_recs = get_records('crlm_field_data_text', 'fieldid', $record->id);
                                foreach ($data_recs as $data_rec) {
                                    delete_records('crlm_field_data_text', 'id', $data_rec->id);
                                    unset($data_rec->id);
                                    insert_record('crlm_field_data_int', $data_rec);
                                }
                                break;
                            case 'menu':
                                // menu items should be short text
                                $record->datatype = 'char';
                                $data_recs = get_records('crlm_field_data_text', 'fieldid', $record->id);
                                foreach ($data_recs as $data_rec) {
                                    delete_records('crlm_field_data_text', 'id', $data_rec->id);
                                    unset($data_rec->id);
                                    insert_record('crlm_field_data_char', $data_rec);
                                }
                            case 'text':
                                $owner->params['columns'] = $owner->params['size'];
                                unset($owner->params['size']);
                                break;
                            }
                            $owner->params = addslashes(serialize($owner->params));
                            $result = $result && insert_record('crlm_field_owner', $owner);
                        }
                        break;
                    }
                    $record->params = '';
                    $result = $result && update_record('crlm_field', $record);
                    if (!empty($record->defaultdata)) {
                        if (!record_exists_select('crlm_field_data_text', "fieldid = {$record->id} AND contextid IS NULL")) {
                            $defaultdata = new stdClass;
                            $defaultdata->fieldid = $record->id;
                            $defaultdata->data = $record->defaultdata;
                            $result = $result && insert_record('crlm_field_data_text', $defaultdata);
                        }
                    }
                }
            }
        }

        $table = new XMLDBTable('crlm_field');

    /// Define field required to be dropped from crlm_field
        $field = new XMLDBField('required');

    /// Launch drop field required
        $result = $result && drop_field($table, $field);


    /// Define field locked to be dropped from crlm_field
        $field = new XMLDBField('locked');

    /// Launch drop field locked
        $result = $result && drop_field($table, $field);


    /// Define field visible to be dropped from crlm_field
        $field = new XMLDBField('visible');

    /// Launch drop field visible
        $result = $result && drop_field($table, $field);


    /// Define field defaultdata to be dropped from crlm_field
        $field = new XMLDBField('defaultdata');

    /// Launch drop field defaultdata
        $result = $result && drop_field($table, $field);


    /// Define field syncwithmoodle to be dropped from crlm_field
        $field = new XMLDBField('syncwithmoodle');

    /// Launch drop field syncwithmoodle
        $result = $result && drop_field($table, $field);


    /// Define field multivalued to be added to crlm_field
        $field = new XMLDBField('multivalued');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, null, null, null, null, '0', 'sortorder');

    /// Launch add field multivalued
        $result = $result && add_field($table, $field);
    }

    if ($result && $oldversion < 2010063007) {
        // install.xml accidentally had the char table use an integer data field

    /// Changing type of field data on table crlm_field_data_char to char
        $table = new XMLDBTable('crlm_field_data_char');
        $field = new XMLDBField('data');
        $field->setAttributes(XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null, null, 'fieldid');

    /// Launch change of type for field data
        $result = $result && change_field_type($table, $field);
    }

    if ($result && $oldversion < 2010063008) {
        $table = new XMLDBTable('crlm_cluster_curriculum');
        $field = new XMLDBField('autoenrol');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '1', 'curriculumid');

        $result = $result && add_field($table, $field);

        $table = new XMLDBTable('crlm_cluster_track');
        $field = new XMLDBField('autoenrol');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '1', 'trackid');

        $result = $result && add_field($table, $field);

    /// Define field parent to be added to crlm_cluster
        $table = new XMLDBTable('crlm_cluster');
        $field = new XMLDBField('parent');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'display');

    /// Launch add field parent
        $result = $result && add_field($table, $field);

    /// Define field depth to be added to crlm_cluster
        $field = new XMLDBField('depth');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '1', 'parent');

    /// Launch add field depth
        $result = $result && add_field($table, $field);
    }

    if ($result && $oldversion < 2010063013) {
        /*
         * Curriculum
         */
        $table = new XMLDBTable('crlm_curriculum');

        //name field
        $index = new XMLDBIndex('name_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('name'));
        if(!index_exists($table, $index)) {
            $result = $result && add_index($table, $index);
        }

        /*
         * Course
         */
        $table = new XMLDBTable('crlm_course');

        //name field
        $index = new XMLDBIndex('name_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('name'));
        if(!index_exists($table, $index)) {
            $result = $result && add_index($table, $index);
        }

        //credits field
        $index = new XMLDBIndex('credits_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('credits'));
        if(!index_exists($table, $index)) {
            $result = $result && add_index($table, $index);
        }

        /*
         * Class
         */
        $table = new XMLDBTable('crlm_class');

        //idnumber field
        $index = new XMLDBIndex('idnumber_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('idnumber'));
        if(!index_exists($table, $index)) {
            $result = $result && add_index($table, $index);
        }

        //enddate field
        $index = new XMLDBIndex('enddate_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('enddate'));
        if(!index_exists($table, $index)) {
            $result = $result && add_index($table, $index);
        }

        /*
         * Class enrolment
         */
        $table = new XMLDBTable('crlm_class_enrolment');

        //completetime field
        $index = new XMLDBIndex('completetime_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('completetime'));
        if(!index_exists($table, $index)) {
            $result = $result && add_index($table, $index);
        }

        //completestatusid field
        $index = new XMLDBIndex('completestatusid_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('completestatusid'));
        if(!index_exists($table, $index)) {
            $result = $result && add_index($table, $index);
        }

        /*
         * CM user
         */
        $table = new XMLDBTable('crlm_user');

        //lastname field
        $index = new XMLDBIndex('lastname_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('lastname'));
        if(!index_exists($table, $index)) {
            $result = $result && add_index($table, $index);
        }

        //firstname field
        $index = new XMLDBIndex('firstname_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('firstname'));
        if(!index_exists($table, $index)) {
            $result = $result && add_index($table, $index);
        }
    }

    if ($result && $oldversion < 2010063015) {

    /// Define field autocreated to be added to crlm_class_moodle
        $table = new XMLDBTable('crlm_class_moodle');
        $field = new XMLDBField('autocreated');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null, null, '-1', 'timemodified');

    /// Launch add field autocreated
        $result = $result && add_field($table, $field);
    }

    if ($result && $oldversion < 2010111300) {
        $table = new XMLDBTable('crlm_curriculum_assignment');

        $field = new XMLDBField('timeexpired');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'timecompleted');

        // Launch add field multivalued
        $result = $result && add_field($table, $field);
    }

    if ($result && $oldversion < 2011011802) {
        // Delete duplicate class completion element grades
        $xmldbtable = new XMLDBTable('crlm_class_graded_temp');

        if (table_exists($xmldbtable)) {
            drop_table($xmldbtable);
        }

        // Create a temporary table
        $result = $result && execute_sql("CREATE TABLE {$CFG->prefix}crlm_class_graded_temp LIKE {$CFG->prefix}crlm_class_graded");

        // Store the unique values in the temporary table
        $sql = "INSERT INTO {$CFG->prefix}crlm_class_graded_temp
                SELECT MAX(id) ".sql_as()." id, classid, userid, completionid, grade, locked, timegraded, timemodified
                FROM {$CFG->prefix}crlm_class_graded
                GROUP BY classid, userid, completionid, locked";

        // Detect if there are still duplicates in the temporary table
        $sql = "SELECT COUNT(*) ".sql_as()." count, classid, userid, completionid, grade, locked, timegraded, timemodified
                FROM {$CFG->prefix}crlm_class_graded_temp
                GROUP BY classid, userid, completionid
                ORDER BY count DESC, classid ASC, userid ASC, completionid ASC";

        if ($dupcount = get_record_sql($sql, true)) {
            if ($dupcount->count > 1) {
                        if ($rs = get_recordset_sql($sql)) {
                    while ($dupe = rs_fetch_next_record($rs)) {
                        if ($dupe->count <= 1) {
                            continue;
                        }

                        $classid = $dupe->classid;
                        $userid  = $dupe->userid;
                        $goodid  = 0; // The ID of the record we will keep

                        // Look for the earliest locked grade record for this user and keep that (if any are locked)
                        $sql2 = "SELECT id, grade, locked, timegraded
                                 FROM mdl_crlm_class_graded
                                 WHERE classid = $classid
                                 AND userid = $userid
                                 ORDER BY timegraded ASC";

                        if ($rs2 = get_recordset_sql($sql2)) {
                            while ($rec = rs_fetch_next_record($rs2)) {
                                // Store the last record ID just in case we need it for cleanup
                                $lastid = $rec->id;

                                // Don't bother looking at remaining records if we have found a record to keep
                                if (!empty($goodid)) {
                                    continue;
                                }

                                if ($rec->locked = 1) {
                                    $goodid = $rec->id;
                                }
                            }

                            rs_close($rs2);

                            // We need to make sure we have a record ID to keep, if we found no "complete" and locked
                            // records, let's just keep the last record we saw
                            if (empty($goodid)) {
                                $goodid = $lastid;
                            }

                            $select = 'classid = '.$classid.' AND userid = '.$userid.' AND id != '.$goodid;
                        }

                        if (!empty($select)) {
                            $result = $result && delete_records_select('crlm_class_graded_temp', $select);
                        }
                    }
                }
            }
        }

        // Drop the real table
        $result = $result && execute_sql("DROP TABLE {$CFG->prefix}crlm_class_graded");

        // Replace the real table with the temporary table that now only contains unique values.
        $result = $result && execute_sql("ALTER TABLE {$CFG->prefix}crlm_class_graded_temp RENAME TO {$CFG->prefix}crlm_class_graded");
    }

    if ($result && $oldversion < 2011050200) {
        /// Define index startdate_ix (not unique) to be added to crlm_class
        $table = new XMLDBTable('crlm_class');
        $index = new XMLDBIndex('startdate_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('startdate'));
        $result = $result && add_index($table, $index);

        /// Define index enrolmenttime_ix (not unique) to be added to crlm_class_enrolment
        $table = new XMLDBTable('crlm_class_enrolment');
        $index = new XMLDBIndex('enrolmenttime_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('enrolmenttime'));
        $result = $result && add_index($table, $index);

        /// Define index locked_ix (not unique) to be added to crlm_class_graded
        $table = new XMLDBTable('crlm_class_graded');
        $index = new XMLDBIndex('locked_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('locked'));
        $result = $result && add_index($table, $index);

        /// Define index timegraded_ix (not unique) to be added to crlm_class_graded
        $table = new XMLDBTable('crlm_class_graded');
        $index = new XMLDBIndex('timegraded_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('timegraded'));
        $result = $result && add_index($table, $index);

        /// Define index classid_ix (not unique) to be added to crlm_class_moodle
        $table = new XMLDBTable('crlm_class_moodle');
        $index = new XMLDBIndex('classid_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('classid'));
        $result = $result && add_index($table, $index);

        /// Define index curriculumid_ix (not unique) to be added to crlm_cluster_curriculum
        $table = new XMLDBTable('crlm_cluster_curriculum');
        $index = new XMLDBIndex('curriculumid_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('curriculumid'));
        $result = $result && add_index($table, $index);

        /// Define index fieldid_ix (not unique) to be added to crlm_cluster_profile
        $table = new XMLDBTable('crlm_cluster_profile');
        $index = new XMLDBIndex('fieldid_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('fieldid'));
        $result = $result && add_index($table, $index);

        /// Define index trackid_ix (not unique) to be added to crlm_cluster_track
        $table = new XMLDBTable('crlm_cluster_track');
        $index = new XMLDBIndex('trackid_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('trackid'));
        $result = $result && add_index($table, $index);

        /// Define index idnumber_ix (not unique) to be added to crlm_course_completion
        $table = new XMLDBTable('crlm_course_completion');
        $index = new XMLDBIndex('idnumber_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('idnumber'));
        $result = $result && add_index($table, $index);

        /// Define index sortorder_ix (not unique) to be added to crlm_field
        $table = new XMLDBTable('crlm_field');
        $index = new XMLDBIndex('sortorder_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('sortorder'));
        $result = $result && add_index($table, $index);

        /// Define index username_ix (not unique) to be added to crlm_user
        $table = new XMLDBTable('crlm_user');
        $index = new XMLDBIndex('username_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('username'));
        $result = $result && add_index($table, $index);

        /// Define index inactive_ix (not unique) to be added to crlm_user
        $table = new XMLDBTable('crlm_user');
        $index = new XMLDBIndex('inactive_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('inactive'));
        $result = $result && add_index($table, $index);

        /// Define index userid_ix (not unique) to be added to crlm_user_track
        $table = new XMLDBTable('crlm_user_track');
        $index = new XMLDBIndex('userid_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('userid'));
        $result = $result && add_index($table, $index);

        /// Define index trackid_ix (not unique) to be added to crlm_user_track
        $table = new XMLDBTable('crlm_user_track');
        $index = new XMLDBIndex('trackid_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('trackid'));
        $result = $result && add_index($table, $index);

        /// Define index classid_ix (not unique) to be added to crlm_wait_list
        $table = new XMLDBTable('crlm_wait_list');
        $index = new XMLDBIndex('classid_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('classid'));
        $result = $result && add_index($table, $index);

        /// Define index userid_ix (not unique) to be added to crlm_wait_list
        $table = new XMLDBTable('crlm_wait_list');
        $index = new XMLDBIndex('userid_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('userid'));
        $result = $result && add_index($table, $index);
    }

    if ($result && $oldversion < 2011050201) {
        // make sure that hours are within 24 hours
        $sql = "UPDATE {$CFG->prefix}crlm_class
                   SET starttimehour = MOD(starttimehour, 24),
                       endtimehour = MOD(endtimehour, 24)";
        $result = $result && execute_sql($sql);
    }

    if ($result && $oldversion < 2011050202) {

    /// Changing type of field credits on table crlm_class_enrolment to number
        $table = new XMLDBTable('crlm_class_enrolment');
        $field = new XMLDBField('credits');
        $field->setAttributes(XMLDB_TYPE_NUMBER, '10, 2', XMLDB_UNSIGNED, null, null, null, null, '0', 'grade');

    /// Launch change of type for field credits
        $result = $result && change_field_type($table, $field);

    /// Changing type of field credits on table crlm_curriculum_assignment to number
        $table = new XMLDBTable('crlm_curriculum_assignment');
        $field = new XMLDBField('credits');
        $field->setAttributes(XMLDB_TYPE_NUMBER, '10, 2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'timeexpired');

    /// Launch change of type for field credits
        $result = $result && change_field_type($table, $field);


    /// Changing type of field reqcredits on table crlm_curriculum to number
        $table = new XMLDBTable('crlm_curriculum');
        $field = new XMLDBField('reqcredits');
        $field->setAttributes(XMLDB_TYPE_NUMBER, '10, 2', XMLDB_UNSIGNED, null, null, null, null, null, 'description');

    /// Launch change of type for field reqcredits
        $result = $result && change_field_type($table, $field);

        // update student class credits with decimal credits
        if ($CFG->dbfamily == 'postgres') {
            $sql = "UPDATE {$CFG->prefix}crlm_class_enrolment
                       SET credits = CAST(c.credits AS numeric)
                      FROM {$CFG->prefix}crlm_class_enrolment e, {$CFG->prefix}crlm_class cls, {$CFG->prefix}crlm_course c
                     WHERE e.classid = cls.id
                       AND cls.courseid = c.id
                       AND e.credits = CAST(c.credits AS integer)";
        } else {
            $sql = "UPDATE {$CFG->prefix}crlm_class_enrolment e, {$CFG->prefix}crlm_class cls, {$CFG->prefix}crlm_course c
                       SET e.credits = c.credits
                     WHERE e.classid = cls.id
                       AND cls.courseid = c.id
                       AND e.credits = CAST(c.credits AS unsigned)";
        }

        $result = $result && execute_sql($sql);
    }

    return $result;
}

?>
