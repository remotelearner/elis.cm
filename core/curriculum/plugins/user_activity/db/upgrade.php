<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2011 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @subpackage component
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2011 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

function xmldb_crlm_user_activity_upgrade($oldversion = 0) {
    global $CFG, $THEME, $db;

    $result = true;

    if ($result && $oldversion < 2010071502) {
    /// Define table etl_user_module_activity to be created
        $table = new XMLDBTable('etl_user_module_activity');

    /// Adding fields to table etl_user_module_activity
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('userid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('courseid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('cmid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('hour', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('duration', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);

    /// Adding keys to table etl_user_module_activity
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));

    /// Adding indexes to table etl_user_module_activity
        $table->addIndexInfo('user_cmid_hour_idx', XMLDB_INDEX_UNIQUE, array('userid', 'cmid', 'hour'));
        $table->addIndexInfo('cm_idx', XMLDB_INDEX_NOTUNIQUE, array('cmid'));
        $table->addIndexInfo('hour_idx', XMLDB_INDEX_NOTUNIQUE, array('hour'));

    /// Launch create table for etl_user_module_activity
        $result = $result && create_table($table);
    }

    if ($result && $oldversion < 2010071503) {

        // clear all records -- they may be incorrect, and we'll recalculate
        // them all
        delete_records_select('etl_user_activity', 'TRUE');
        delete_records('crlm_config', 'name', 'user_activity_last_run');

    /// Define index user_idx (not unique) to be dropped form etl_user_activity
        $table = new XMLDBTable('etl_user_activity');
        $index = new XMLDBIndex('user_idx');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('userid'));

    /// Launch drop index user_idx
        $result = $result && drop_index($table, $index);

    /// Define index user_idx (not unique) to be added to etl_user_activity
        $table = new XMLDBTable('etl_user_activity');
        $index = new XMLDBIndex('user_idx');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('userid', 'courseid', 'hour'));

    /// Launch add index user_idx
        $result = $result && add_index($table, $index);
    }

    return $result;
}
