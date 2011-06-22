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

function xmldb_crlm_cluster_classification_upgrade($oldversion = 0) {
    global $CFG, $THEME, $db;

    $result = true;

    if ($result && $oldversion < 2010080502) {

    /// Define table crlm_cluster_classification to be created
        $table = new XMLDBTable('crlm_cluster_classification');

    /// Adding fields to table crlm_cluster_classification
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('shortname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('params', XMLDB_TYPE_TEXT, 'medium', null, null, null, null, null, null);

    /// Adding keys to table crlm_cluster_classification
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->addKeyInfo('shortname_idx', XMLDB_KEY_UNIQUE, array('shortname'));

    /// Launch create table for crlm_cluster_classification
        $result = $result && create_table($table);
    }

    if ($result && $oldversion < 2010080503) {
        require_once $CFG->dirroot . '/curriculum/lib/customfield.class.php';
        require_once $CFG->dirroot . '/curriculum/plugins/cluster_classification/lib.php';
        require_once $CFG->dirroot . '/curriculum/plugins/cluster_classification/clusterclassification.class.php';
        $field = new field(field::get_for_context_level_with_name('cluster', CLUSTER_CLASSIFICATION_FIELD));

        // make sure we're set as owner
        if (!isset($field->owners['cluster_classification'])) {
            $owner = new field_owner();
            $owner->fieldid = $field->id;
            $owner->plugin = 'cluster_classification';
            $owner->add();
        }

        // make sure we have a default value set
        if (!field_data::get_for_context_and_field(NULL, $field)) {
            field_data::set_for_context_and_field(NULL, $field, 'regular');
        }

        $default = new clusterclassification();
        $default->shortname = 'regular';
        $default->name = get_string('cluster', 'block_curr_admin');
        $default->param_autoenrol_curricula = 1;
        $default->param_autoenrol_tracks = 1;
        $default->add();
    }

    // make sure 'manual' is an owner
    if ($result && $oldversion < 2010080504) {
        require_once $CFG->dirroot . '/curriculum/lib/customfield.class.php';
        require_once $CFG->dirroot . '/curriculum/plugins/cluster_classification/lib.php';
        require_once $CFG->dirroot . '/curriculum/plugins/cluster_classification/clusterclassification.class.php';
        $field = new field(field::get_for_context_level_with_name('cluster', CLUSTER_CLASSIFICATION_FIELD));

        $owner = new field_owner();
        $owner->fieldid = $field->id;
        $owner->plugin = 'manual';
        $owner->param_view_capability = '';
        $owner->param_edit_capability = 'moodle/user:update';
        $owner->param_control = 'menu';
        $owner->param_options_source = 'cluster_classifications';
        $owner->add();
    }

    return $result;
}

?>
