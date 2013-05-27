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
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_elis_program_upgrade($oldversion=0) {
    global $DB, $CFG;

    $dbman = $DB->get_manager();
    $result = true;

    if ($result && $oldversion < 2011070800) {
        // Must switch enum in table: crlm_curriculum_course  field: timeperiod
        // to type text, small - saving & restoring table data
        $tabname = 'crlm_curriculum_course';
        $fldname = 'timeperiod';
        $table = new xmldb_table($tabname);
        $field = new xmldb_field($fldname);

        // save existing field data
        $rs = $DB->get_recordset($tabname, null, '', 'id, '. $fldname);

        // drop ENUM field
        $dbman->drop_field($table, $field);

        // re-add w/o ENUM - convert to text, small
        $field->set_attributes(XMLDB_TYPE_TEXT, 'small', null, null, null,
                               null, 'frequency');
        $dbman->add_field($table, $field);

        // Restore old field data to new field
        if (!empty($rs)) {
            foreach ($rs as $rec) {
                if (empty($rec->timeperiod)) {
                    $rec->timeperiod = 'year';
                }
                if (!($result = $result && $DB->update_record($tabname, $rec))) {
                    error_log("xmldb_elis_program_upgrade(): update error!");
                    break;
                }
            }
            $rs->close();
        }

        //error_log("xmldb_elis_program_upgrade(): result = {$result}");
        upgrade_plugin_savepoint($result, 2011070800, 'elis', 'program');
    }

    if ($oldversion < 2011080200) {

        // Changing the default of field autounenrol on table crlm_cluster_track to 0
        $table = new xmldb_table('crlm_cluster_track');
        $field = new xmldb_field('autounenrol', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'autoenrol');

        // Launch change of default for field autounenrol
        $dbman->change_field_default($table, $field);

        // elis savepoint reached
        upgrade_plugin_savepoint(true, 2011080200, 'elis', 'program');
    }

    if ($result && $oldversion < 2011091600) {
        require_once($CFG->dirroot.'/blocks/curr_admin/lib.php');
        //make sure the site has exactly one curr admin block instance
        //that is viewable everywhere
        block_curr_admin_create_instance();

        // elis savepoint reached
        upgrade_plugin_savepoint(true, 2011091600, 'elis', 'program');
    }

    if ($result && $oldversion < 2011091900) {
        require_once($CFG->dirroot.'/blocks/curr_admin/lib.php');

        //migrate tag data to custom fields
        pm_migrate_tags();
        //migrade environment data to custom fields
        pm_migrate_environments();

        // elis savepoint reached
        upgrade_plugin_savepoint(true, 2011091900, 'elis', 'program');
    }

    if ($result && $oldversion < 2011092000) {
        /**
         * Support class start/end times = 00:00 (midnight)
         * invalid/disabled is now hour/minute out-of-range (> 24/60)
         */
        $pmclasses = $DB->get_recordset('crlm_class');
        if ($pmclasses) {
            foreach ($pmclasses as $pmclass) {
                $change = false;
                if ($pmclass->starttimeminute == '0' && $pmclass->starttimehour == '0') {
                    $pmclass->starttimeminute = $pmclass->starttimehour = 61;
                    $change = true;
                }
                if ($pmclass->endtimeminute == '0' && $pmclass->endtimehour == '0') {
                    $pmclass->endtimeminute = $pmclass->endtimehour = 61;
                    $change = true;
                }
                if ($change) {
                    $DB->update_record('crlm_class', $pmclass);
                }
            }
            $pmclasses->close();
        }

        // elis savepoint reached
        upgrade_plugin_savepoint(true, 2011092000, 'elis', 'program');
    }

    if ($result && $oldversion < 2011092100) {
        //migrate data for the completion elements options source in custom field owners
        //to the new learning objectives source

        //necessary libraries
        require_once($CFG->dirroot.'/elis/core/lib/setup.php');
        require_once(elis::lib('data/customfield.class.php'));
        require_once(elis::lib('data/data_filter.class.php'));

        //create a filter to find all owners whose params contain the old value
        $filter = new field_filter('params', "%completion_elements%", field_filter::LIKE);
        $potential_owners = field_owner::find($filter);

        //iterate through possible matching owners (it's theoretically possible that the "completion_elements"
        //substring belongs to another field
        foreach ($potential_owners as $potential_owner) {

            //need to check and update the options source parameter
            if (!empty($potential_owner->params)) {
                $params = unserialize($potential_owner->params);

                //validate that the options source parameter is the old completion elements value
                if (!empty($params['options_source']) && $params['options_source'] == 'completion_elements') {
                    //update with the new learning objectives value
                    $params['options_source'] = 'learning_objectives';
                    $potential_owner->params = serialize($params);
                    $potential_owner->save();
                }
            }
        }

        // elis savepoint reached
        upgrade_plugin_savepoint(true, 2011092100, 'elis', 'program');
    }

    if ($result && $oldversion < 2011092101) {
      /*
        // make sure that the manager role can be assigned to all PM context levels
        update_capabilities('elis_program'); // load context levels
        pm_ensure_role_assignable('manager');
        pm_ensure_role_assignable('curriculumadmin');
      */
        upgrade_plugin_savepoint(true, 2011092101, 'elis', 'program');
    }

    if ($result && $oldversion < 2011102600) {
        require_once($CFG->dirroot.'/blocks/curr_admin/lib.php');
        //make sure the site has exactly one curr admin block instance
        //that is viewable everywhere
        // w/ defaultweight = -1, defaultregion = 'side-pre'
        block_curr_admin_create_instance();

        upgrade_plugin_savepoint(true, 2011102600, 'elis', 'program');
    }

    if ($result && $oldversion < 2011102700) {
        require_once($CFG->dirroot.'/elis/program/lib/setup.php');
        require_once(elispm::lib('lib.php'));
        require_once(elispm::lib('data/user.class.php'));

        //create table for storing the association between Moodle and PM users
        $table = new xmldb_table('crlm_user_moodle');
        $table->comment = 'Association between Moodle and CM users';

        //fields
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('cuserid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->add_field('muserid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->add_field('idnumber', XMLDB_TYPE_CHAR, '100', XMLDB_UNSIGNED, XMLDB_NOTNULL);

        // PK and indexes
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('cuserid_fk', XMLDB_KEY_FOREIGN, array('cuserid'), 'crlm_user', array('id'));
        $table->add_key('muserid_fk', XMLDB_KEY_FOREIGN, array('muserid'), 'user', array('id'));
        $table->add_index('idnumber_idx', XMLDB_INDEX_UNIQUE, array('idnumber'));

        $dbman->create_table($table);

        // Create a temporary table used to determine whether a PM user originally referenced
        // a Moodle user
        $result = $result && $DB->execute("CREATE TABLE {$CFG->prefix}crlm_user_moodle_temp LIKE {$CFG->prefix}crlm_user_moodle");

        $table = new xmldb_table('crlm_user_moodle_temp');
        $index = new xmldb_index('idnumber_idx', XMLDB_INDEX_UNIQUE, array('idnumber'));
        $dbman->drop_index($table, $index);

        // populate data for temporary table
        $sql = "INSERT
                INTO {crlm_user_moodle_temp} (cuserid, muserid, idnumber)
                SELECT cu.id, mu.id, cu.idnumber
                FROM {crlm_user} cu
                JOIN {user} mu ON cu.idnumber = mu.idnumber AND cu.idnumber != ''
                WHERE mu.deleted = 0
                AND mu.mnethostid = :mnethostid";

        $DB->execute($sql, array('mnethostid' => $CFG->mnet_localhost_id));

        //fix duplicate enrolment data
        $result = $result && pm_fix_duplicate_class_enrolments();
        //fix duplicate Moodle idnumber values
        $result = $result && pm_fix_duplicate_moodle_users();
        //fix duplicate Program Management idnumber values
        $result = $result && pm_fix_duplicate_pm_users();

        // populate data from existing Moodle and PM user tables
        $sql = "INSERT
                INTO {crlm_user_moodle} (cuserid, muserid, idnumber)
                SELECT cu.id, mu.id, cu.idnumber
                FROM {crlm_user} cu
                JOIN {user} mu ON cu.idnumber = mu.idnumber AND cu.idnumber != ''
                WHERE mu.deleted = 0
                AND mu.mnethostid = :mnethostid";

        $DB->execute($sql, array('mnethostid' => $CFG->mnet_localhost_id));

        //safe to migrate all Moodle users to ELIS because deleting in ELIS
        //would have deleted the Moodle user record
        pm_migrate_moodle_users();

        //migrate ELIS user records to Moodle if a new idnumber was generated for
        //a user
        $rs = $DB->get_recordset_select(user::TABLE,
               "NOT EXISTS (SELECT 'x'
                            FROM {user} mu
                            WHERE mu.idnumber = {".user::TABLE."}.idnumber)
                AND EXISTS (SELECT 'x'
                            FROM {crlm_user_moodle_temp} umt
                            WHERE umt.cuserid = {".user::TABLE."}.id)");
        if ($rs) {
            foreach ($rs as $user) {
                $user = new user($user->id);
                $user->load();
                $user->synchronize_moodle_user(true, true);
            }
        }

        //drop the temporary table
        $result = $result && $DB->execute("DROP TABLE {$CFG->prefix}crlm_user_moodle_temp");

        upgrade_plugin_savepoint(true, 2011102700, 'elis', 'program');
    }

    if ($result && $oldversion < 2011110700) {
        //create a new column in the notification log table
        //to store the user who triggered the notification
        $table = new xmldb_table('crlm_notification_log');
        $field = new xmldb_field('fromuserid');

        if (!$dbman->field_exists($table, $field)) {
            $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0, 'userid');
            $field->comment = 'PM user id that triggered the notification.';
            $dbman->add_field($table, $field);
        }

        //field may already exist from 1.9 install / upgrade
        if (!$dbman->field_exists($table, $field)) {
            $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0, 'userid');
            $field->comment = 'PM user id that triggered the notification.';
            $dbman->add_field($table, $field);

            //populate data, assuming that the user who received the notification is the one whose
            //criteria spawned it
            //NOTE: this fudges data and the side-effect implies that if someone had received a notification
            //for another user and satisfy the same criteria for the same instance for themself, they will not
            //receive a similar notification
            $sql = "UPDATE {crlm_notification_log}
                    SET fromuserid = userid";
            $DB->execute($sql);
        }
        upgrade_plugin_savepoint($result, 2011110700, 'elis', 'program');
    }

    if ($result && $oldversion < 2011120100) {
        $result = pm_migrate_certificate_files();
        upgrade_plugin_savepoint($result, 2011120100, 'elis', 'program');
    }

    if ($result && $oldversion < 2011121500) {
        // make sure that the manager role can be assigned to all PM context levels
        update_capabilities('elis_program'); // load context levels
        pm_ensure_role_assignable('manager');
        pm_ensure_role_assignable('curriculumadmin');
        upgrade_plugin_savepoint($result, 2011121500, 'elis', 'program');
    }

    if ($result && $oldversion < 2011121501) {
        $table = new xmldb_table('crlm_notification_log');
        $index = new xmldb_index('event_inst_fuser_ix');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('fromuserid', 'instance', 'event'));

        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint($result, 2011121501, 'elis', 'program');
    }

    if ($result && $oldversion < 2012022400) {
        /// table
        $table = new xmldb_table('crlm_results');

        /// Adding fields
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, true);
        $table->add_field('active', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
        $table->add_field('eventtriggertype', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
        $table->add_field('lockedgrade', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
        $table->add_field('triggerstartdate', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
        $table->add_field('days', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
        $table->add_field('criteriatype', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);

        /// Adding keys and index
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('contextid_idx', XMLDB_INDEX_UNIQUE, array('contextid'));

        /// Create table
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        /// table
        $table = new xmldb_table('crlm_results_action');
        if (!$dbman->table_exists($table)) {
            /// Adding fields
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('resultsid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL);
            $table->add_field('actiontype', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
            $table->add_field('minimum', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('maximum', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('trackid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
            $table->add_field('classid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
            $table->add_field('fieldid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
            $table->add_field('fielddata', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);

            /// Adding keys and index
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_index('resultsid_idx', XMLDB_INDEX_NOTUNIQUE, array('resultsid'));

            /// Create table
            $dbman->create_table($table);
        } else {
            if (!$dbman->field_exists($table, 'classid')) {
                $field = new xmldb_field('classid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, 'trackid');
                $dbman->add_field($table, $field);
            }
        }

        /// table
        $table = new xmldb_table('crlm_results_class_log');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('classid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->add_field('datescheduled', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL);
        $table->add_field('daterun', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL);

        /// Adding keys and index
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('classid_idx', XMLDB_INDEX_NOTUNIQUE, array('classid'));

        /// Create table
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        /// table
        $table = new xmldb_table('crlm_results_student_log');

        /// Adding keys and index
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('classlogid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->add_field('action', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $table->add_field('daterun', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL);

        /// Adding keys and index
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('classlogid_idx', XMLDB_INDEX_NOTUNIQUE, array('classlogid'));

        /// Create table
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint($result, 2012022400, 'pmplugins_results_engine_', '');
        upgrade_plugin_savepoint($result, 2012022400, 'elis', 'program');
    }

    if ($result && $oldversion < 2012022401) {
        // Add the new 'certificatecode' field to the curriculum_assignment table
        $table = new xmldb_table('crlm_curriculum_assignment');
        $field = new xmldb_field('certificatecode', XMLDB_TYPE_CHAR, '40', null, null, null, null, 'locked');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add a new non-uniue index for the new field
        $index = new xmldb_index('certificatecode_ix', XMLDB_INDEX_NOTUNIQUE, array('certificatecode'));

        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint($result, 2012022401, 'elis', 'program');
    }

    if ($result && $oldversion < 2012032700) {
        $table = new xmldb_table('crlm_class_enrolment');
        $field = new xmldb_field('grade');
        $field->set_attributes(XMLDB_TYPE_NUMBER, '10,5', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0, 'completestatusid');
        $dbman->change_field_type($table, $field);

        $table = new xmldb_table('crlm_class_graded');
        $field = new xmldb_field('grade');
        $field->set_attributes(XMLDB_TYPE_NUMBER, '10,5', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0, 'completionid');
        $dbman->change_field_type($table, $field);

        // Attempt to handle different DBs in the most efficient way possible
        if ($CFG->dbfamily == 'postgres') {
            $sql = "DELETE FROM {crlm_class_graded}
                          WHERE id IN (
                                SELECT ccg.id
                                  FROM {crlm_user} cu
                            INNER JOIN {crlm_class_enrolment} cce ON cce.userid = cu.id
                            INNER JOIN {crlm_class_graded} ccg ON (ccg.userid = cce.userid AND ccg.classid = cce.classid)
                            INNER JOIN {crlm_course_completion} ccc ON ccc.id = ccg.completionid
                            INNER JOIN {crlm_class_moodle} ccm ON ccm.classid = ccg.classid
                            INNER JOIN {user} u ON u.idnumber = cu.idnumber
                            INNER JOIN {course} c ON c.id = ccm.moodlecourseid
                            INNER JOIN {grade_items} gi ON (gi.courseid = c.id AND gi.idnumber = ccc.idnumber)
                            INNER JOIN {grade_grades} gg ON (gg.itemid = gi.id AND gg.userid = u.id)
                                 WHERE ccg.locked = 0
                                   AND ccc.idnumber != ''
                                   AND gi.itemtype != 'course'
                                   AND ccg.grade != gg.finalgrade
                                   AND gg.finalgrade IS NOT NULL
                    )";
            $DB->execute($sql);
        } else if ($CFG->dbfamily == 'mysql') {
            $sql = "    DELETE ccg
                          FROM {crlm_user} cu
                    INNER JOIN {crlm_class_enrolment} cce ON cce.userid = cu.id
                    INNER JOIN {crlm_class_graded} ccg ON (ccg.userid = cce.userid AND ccg.classid = cce.classid)
                    INNER JOIN {crlm_course_completion} ccc ON ccc.id = ccg.completionid
                    INNER JOIN {crlm_class_moodle} ccm ON ccm.classid = ccg.classid
                    INNER JOIN {user} u ON u.idnumber = cu.idnumber
                    INNER JOIN {course} c ON c.id = ccm.moodlecourseid
                    INNER JOIN {grade_items} gi ON (gi.courseid = c.id AND gi.idnumber = ccc.idnumber)
                    INNER JOIN {grade_grades} gg ON (gg.itemid = gi.id AND gg.userid = u.id)
                         WHERE ccg.locked = 0
                           AND ccc.idnumber != ''
                           AND gi.itemtype != 'course'
                           AND ccg.grade != gg.finalgrade
                           AND gg.finalgrade IS NOT NULL";

            $DB->execute($sql);
        } else {
            $sql = "    SELECT ccg.id, ccg.grade
                          FROM {crlm_user} cu
                    INNER JOIN {crlm_class_enrolment} cce ON cce.userid = cu.id
                    INNER JOIN {crlm_class_graded} ccg ON (ccg.userid = cce.userid AND ccg.classid = cce.classid)
                    INNER JOIN {crlm_course_completion} ccc ON ccc.id = ccg.completionid
                    INNER JOIN {crlm_class_moodle} ccm ON ccm.classid = ccg.classid
                    INNER JOIN {user} u ON u.idnumber = cu.idnumber
                    INNER JOIN {course} c ON c.id = ccm.moodlecourseid
                    INNER JOIN {grade_items} gi ON (gi.courseid = c.id AND gi.idnumber = ccc.idnumber)
                    INNER JOIN {grade_grades} gg ON (gg.itemid = gi.id AND gg.userid = u.id)
                         WHERE ccg.locked = 0
                           AND ccc.idnumber != ''
                           AND gi.itemtype != 'course'
                           AND ccg.grade != gg.finalgrade
                           AND gg.finalgrade IS NOT NULL";

            $rs = $DB->get_recordset_sql($sql);
            if ($rs && $rs->valid()) {
                foreach($rs as $cg) {
                    $DB->delete_records('crlm_class_graded',
                                        array('id' => $cg->id));
                }
                $rs->close();
            }
        }

        // Force a re-synchronization of ELIS class grade data
        require_once($CFG->dirroot .'/elis/program/lib/lib.php');
        pm_synchronize_moodle_class_grades();

        upgrade_plugin_savepoint($result, 2012032700, 'elis', 'program');
    }

    if ($result && $oldversion < 2012040200) {
        // The "crlm_class_enrolment.endtime" field might not exist in the current DB, so we need to check if it's missing and add it
        $table = new xmldb_table('crlm_class_enrolment');
        $field = new xmldb_field('endtime', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0, 'completetime');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // The "crlm_cluster_track.enrolmenttime" field might not exist in the current DB, so we need to check if it's missing and add it
        $table = new xmldb_table('crlm_cluster_track');
        $field = new xmldb_field('enrolmenttime', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0, 'autounenrol');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint($result, 2012040200, 'elis', 'program');
    }

     if ($result && $oldversion < 2012050300) {
         $table = new xmldb_table('crlm_user');
         $field = new xmldb_field('city', XMLDB_TYPE_CHAR, '120',null, null, null, null, 'address2');

         $dbman->change_field_precision($table, $field);

         upgrade_plugin_savepoint($result, 2012050300, 'elis', 'program');
     }

    if ($result && $oldversion < 2012050315) {
        //search for the following fields in the config_plugins table and change %%curriculumname%% to %%programname%%
        //for the fields in $fields
        $plugin = 'elis_program';
        $name1 = 'notify_curriculumrecurrence_message';
        $name2 = 'notify_curriculumnotcompleted_message';
        $name3 = 'notify_curriculumcompleted_message';
        $sqlwhere = "name IN (:name1,:name2,:name3)";
        $params = array('name1' => $name1,'name2'=>$name2,'name3'=>$name3);
        $rs = $DB->get_recordset_select('config_plugins', $sqlwhere, $params);
        if (!empty($rs)) {
            foreach ($rs as $record) {
                if ($record->value !== null) {
                    //update the value from curriculumname to programname
                    $record->value = str_replace('%%curriculumname%%','%%programname%%',$record->value);
                    $DB->set_field('config_plugins', 'value', $record->value, array('id'=>$record->id));
                }
            }
        }

        upgrade_plugin_savepoint($result, 2012050315, 'elis', 'program');
    }

    if ($result && $oldversion < 2012062900) {
        require_once($CFG->dirroot.'/elis/program/lib/setup.php');
        require_once(elispm::file('accesslib.php'));

        //rebuild user set context paths to fix problems related to changing hierarchy
        context_elis_helper::build_all_paths(false, array(CONTEXT_ELIS_USERSET));
        upgrade_plugin_savepoint($result, 2012062900, 'elis', 'program');
    }

    if ($result && $oldversion < 2012062901) {
        // add username index "username_ix" to crlm_user table
        $table = new xmldb_table('crlm_user');

        $index = new xmldb_index('username_ix', XMLDB_INDEX_NOTUNIQUE, array('username'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $index = new xmldb_index('idnumber_ix', XMLDB_INDEX_NOTUNIQUE, array('idnumber'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        upgrade_plugin_savepoint($result, 2012062901, 'elis', 'program');
    }

    if ($result && $oldversion < 2013031400) {
        // ELIS-8066: remove blank/empty menu options from custom field menu/checkbox and defaults using them
        $customfields = $DB->get_recordset('elis_field', null, '', 'id');
        foreach ($customfields as $id => $unused) {
            $field = new field($id);
            $field->load();
            if (isset($field->owners['manual'])) {
                $manual = new field_owner($field->owners['manual']);
                $control = $manual->param_control;
                $options = $manual->param_options;
                if (!empty($options) && empty($manual->param_options_source) && ($control == 'menu' || $control == 'checkbox')) {
                    $options = str_replace("\r", '', $options); // strip CRs
                    $options = preg_replace("/\n+/", "\n", $options);
                    $manual->param_options = rtrim($options, "\n");
                    $manual->save();
                    // Remove any empty defaults
                    $DB->delete_records_select($field->data_table(), "contextid IS NULL AND fieldid = ? AND data = ''", array($id));
                }
            }
        }
        upgrade_plugin_savepoint($result, 2013031400, 'elis', 'program');
    }

    // ELIS-7780: remove deprecated capabilites
    if ($result && $oldversion < 2013041900) {
        $capstodelete = array('elis/program:viewgroupreports', 'elis/program:viewreports');
        list($inorequal, $params) = $DB->get_in_or_equal($capstodelete);
        $where = "capability $inorequal";
        $DB->delete_records_select('role_capabilities', $where, $params);
        $where = "name $inorequal";
        $DB->delete_records_select('capabilities', $where, $params);
        upgrade_plugin_savepoint($result, 2013041900, 'elis', 'program');
    }

    if ($result && $oldversion < 2013042900) {
        // Add indexes to {crlm_user_track} table
        $table = new xmldb_table('crlm_user_track');
        if ($dbman->table_exists($table)) {
            // array of indexes to drop
            $dropindexes = array(
                new xmldb_index('any_userid_ix', XMLDB_INDEX_UNIQUE, array('userid')),
                new xmldb_index('any_trackid_ix', XMLDB_INDEX_UNIQUE, array('trackid')),
                new xmldb_index('any_userid_trackid_ix', XMLDB_INDEX_NOTUNIQUE, array('userid', 'trackid'))
            );
            foreach ($dropindexes as $index) {
                // Drop unwanted indexes if they exist
                if ($dbman->index_exists($table, $index)) {
                    $dbman->drop_index($table, $index);
                }
            }

            // array of indexes to create
            $createindexes = array(
                new xmldb_index('userid_ix', XMLDB_INDEX_NOTUNIQUE, array('userid')),
                new xmldb_index('trackid_ix', XMLDB_INDEX_NOTUNIQUE, array('trackid')),
                new xmldb_index('userid_trackid_ix', XMLDB_INDEX_UNIQUE, array('userid', 'trackid'))
            );

            foreach ($createindexes as $index) {
                // Create desired indexes as required
                if (!$dbman->index_exists($table, $index)) {
                    $dbman->add_index($table, $index);
                }
            }
        }
        upgrade_plugin_savepoint($result, 2013042900, 'elis', 'program');
    }

    return $result;
}
