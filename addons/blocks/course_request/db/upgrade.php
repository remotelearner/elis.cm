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
 *
 * @package    elis
 * @subpackage blocks-course_request
 * @copyright 2011 Remote Learner - http://www.remote-learner.net/
 * @author    Brent Boghosian <brent.boghosian@remote-learner.net>
 * @author    Justin Filip <jfilip@remote-learner.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function xmldb_block_course_request_upgrade($oldversion = 0) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();
    $result = true;

    if ($oldversion < 2010062300) {
        $table = new xmldb_table('block_course_request');

        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        $dbman->install_from_xmldb_file($CFG->dirroot .'/blocks/course_request/db/install.xml');

        upgrade_block_savepoint($result, 2010062300, 'course_request');
    }

    if ($oldversion < 2010062301) {
        $table = new xmldb_table('block_course_request');
        $field = new xmldb_field('firstname');
        $field->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null, 'userid');
        $dbman->add_field($table, $field);

        $field = new xmldb_field('lastname');
        $field->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null, 'firstname');
        $dbman->add_field($table, $field);

        $field = new xmldb_field('email');
        $field->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null, 'lastname');
        $dbman->add_field($table, $field);

        $field = new xmldb_field('statusnote');
        $field->set_attributes(XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'modifiedby');
        $dbman->add_field($table, $field);

        upgrade_block_savepoint($result, 2010062301, 'course_request');
    }

    if ($oldversion < 2010062302) {
        $table = new xmldb_table('block_course_request');
        $field = new xmldb_field('title');
        $field->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null, 'userid');
        $dbman->add_field($table, $field);

        upgrade_block_savepoint($result, 2010062302, 'course_request');
    }

    if ($result && $oldversion < 2010062303) {

    /// Define field courseid to be added to block_course_request
        $table = new xmldb_table('block_course_request');
        $field = new xmldb_field('courseid');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'email');

    /// Launch add field courseid
        $dbman->add_field($table, $field);

        upgrade_block_savepoint($result, 2010062303, 'course_request');
    }

    if ($result && $oldversion < 2010112300) {
        $table = new xmldb_table('block_course_request');
        $field = new xmldb_field('classid');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'requeststatus');

        $dbman->add_field($table, $field);

        upgrade_block_savepoint($result, 2010112300, 'course_request');
    }

    if ($result && $oldversion < 2010122000) {
        //field that tracks whether a course template is used to create an associated Moodle course
        $table = new xmldb_table('block_course_request');
        $field = new xmldb_field('usecoursetemplate');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'classid');

        $dbman->add_field($table, $field);

        upgrade_block_savepoint($result, 2010122000, 'course_request');
    }

    if ($result && $oldversion < 2011051800) {
        //make sure the custom context libraries are loaded
        require_once($CFG->dirroot .'/elis/program/lib/contexts.php');

        //set all existing records to use the class context level
        $context_level = context_level_base::get_custom_context_level('class', 'elis_program');

        //our listing of available fields needs to track context levels now
        $table = new xmldb_table('block_course_request_fields');
        $field = new xmldb_field('contextlevel');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'sortorder');

        $dbman->add_field($table, $field);

        //set all values to the class context level (update condition is always true but needed
        //to prevent warnings)
        $DB->set_field('block_course_request_fields', 'contextlevel', $context_level, array('contextlevel' => '0'));

        //our individual request data need to track context levels as well
        $table = new xmldb_table('block_course_request_data');
        $field = new xmldb_field('contextlevel');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'data');

        $dbman->add_field($table, $field);

        //set all values to the class context level (update condition is always true but needed
        //to prevent warnings)
        $DB->set_field('block_course_request_data', 'contextlevel', $context_level, array('contextlevel' => '0'));

        upgrade_block_savepoint($result, 2011051800, 'course_request');
    }

    if ($result && $oldversion < 2011062000) {

    /// Define field multiple to be added to block_course_request_data
        $table = new xmldb_table('block_course_request_data');
        $field = new xmldb_field('multiple');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'data');

    /// Launch add field multiple
        $dbman->add_field($table, $field);

        upgrade_block_savepoint($result, 2011062000, 'course_request');
    }

    return $result;
}

