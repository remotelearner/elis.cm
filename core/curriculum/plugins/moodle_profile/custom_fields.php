<?php
/**
 * Synchronizes user profile data to/from Moodle.  If this module is set to
 * have exclusive ownership of the data, then that indicates that data will by
 * copied from Moodle to ELIS.  Otherwise, the data will be copied from ELIS to
 * Moodle (and this module will not actually write data).
 *
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

class cm_moodle_profile {
    const sync_from_moodle = 1;
    const sync_to_moodle = 0;
}


// Synchronization functions

function sync_profile_field_with_moodle($field) {
    sync_profile_field_to_moodle($field);
    sync_profile_field_from_moodle($field);
}

function sync_profile_field_to_moodle($field) {
    global $CURMAN;
    if (!isset($field->owners['moodle_profile'])
        || $field->owners['moodle_profile']->exclude == cm_moodle_profile::sync_from_moodle) {
        // not owned by the Moodle plugin, or set to sync from Moodle
        return true;
    }
    if (!$CURMAN->db->record_exists('user_info_field', 'shortname', $field->shortname)) {
        // no Moodle field to sync with
        return true;
    }
    $level = context_level_base::get_custom_context_level('user', 'block_curr_admin');

    $dest = 'user_info_data';
    $src = $field->data_table();
    $mfieldid = $CURMAN->db->get_field('user_info_field', 'id', 'shortname', $field->shortname);

    $joins = "JOIN {$CURMAN->db->prefix_table('crlm_user')} cu ON usr.idnumber = cu.idnumber
              JOIN {$CURMAN->db->prefix_table('context')} ctx ON ctx.instanceid = cu.id AND ctx.contextlevel = $level
              JOIN {$CURMAN->db->prefix_table($src)} src ON src.contextid = ctx.id AND src.fieldid = {$field->id}";

    // insert field values that don't already exist
    $sql = "INSERT INTO {$CURMAN->db->prefix_table($dest)}
                   (userid, fieldid, data)
            SELECT usr.id AS userid, {$mfieldid} AS fieldid, src.data
              FROM {$CURMAN->db->prefix_table('user')} usr
                   $joins
         LEFT JOIN {$CURMAN->db->prefix_table($dest)} dest ON dest.userid = usr.id AND dest.fieldid = {$field->id}
             WHERE dest.id IS NULL";
    $CURMAN->db->execute_sql($sql, false);

    // update already-existing values
    $sql = "UPDATE {$CURMAN->db->prefix_table($dest)} dest
              JOIN {$CURMAN->db->prefix_table('user')} usr ON dest.userid = usr.id
                   $joins
               SET dest.data = src.data
             WHERE dest.fieldid = $mfieldid";
    $CURMAN->db->execute_sql($sql, false);
}

function sync_profile_field_from_moodle($field) {
    global $CURMAN;
    $level = context_level_base::get_custom_context_level('user', 'block_curr_admin');
    if (!isset($field->owners['moodle_profile'])
        || $field->owners['moodle_profile']->exclude == cm_moodle_profile::sync_to_moodle) {
        // not owned by the Moodle plugin, or set to sync to Moodle
        return true;
    }
    if (!$CURMAN->db->record_exists('user_info_field', 'shortname', $field->shortname)) {
        // no Moodle field to sync with
        return true;
    }

    $dest = $field->data_table();
    $src = 'user_info_data';
    $mfieldid = $CURMAN->db->get_field('user_info_field', 'id', 'shortname', $field->shortname);

    $joins = "JOIN {$CURMAN->db->prefix_table('crlm_user')} cu ON usr.idnumber = cu.idnumber
              JOIN {$CURMAN->db->prefix_table('context')} ctx ON ctx.instanceid = cu.id AND ctx.contextlevel = $level
              JOIN {$CURMAN->db->prefix_table($src)} src ON src.userid = usr.id AND src.fieldid = {$mfieldid}";

    // insert field values that don't already exist
    $sql = "INSERT INTO {$CURMAN->db->prefix_table($dest)}
            (contextid, fieldid, data)
            SELECT ctx.id AS contextid, {$field->id} AS fieldid, src.data
              FROM {$CURMAN->db->prefix_table('user')} usr
                   $joins
         LEFT JOIN {$CURMAN->db->prefix_table($dest)} dest ON dest.contextid = ctx.id AND dest.fieldid = {$field->id}
             WHERE dest.id IS NULL";
    $CURMAN->db->execute_sql($sql, false);

    // update already-existing values
    $sql = "UPDATE {$CURMAN->db->prefix_table($dest)} dest
              JOIN {$CURMAN->db->prefix_table('user')} usr
                   $joins
               SET dest.data = src.data
             WHERE dest.fieldid = {$field->id}
               AND dest.contextid = ctx.id";
    $CURMAN->db->execute_sql($sql, false);
}


// Form functions

function moodle_profile_field_edit_form_definition($form) {
    $level = $form->_customdata->required_param('level', PARAM_ACTION);
    if ($level != 'user') {
        return;
    }

    $form =& $form->_form;
    $form->addElement('header', '', get_string('field_moodlesync', 'block_curr_admin'));

    $choices = array(
        -1 => get_string('field_no_sync', 'block_curr_admin'),
        cm_moodle_profile::sync_to_moodle => get_string('field_sync_to_moodle', 'block_curr_admin'),
        cm_moodle_profile::sync_from_moodle => get_string('field_sync_from_moodle', 'block_curr_admin'),
        );
    $form->addElement('select', 'moodle_profile_exclusive', get_string('field_syncwithmoodle', 'block_curr_admin'), $choices);
    $form->setType('moodle_profile_exclusive', PARAM_INT);
}

function moodle_profile_field_get_form_data($form, $field) {
    $level = $form->_customdata->required_param('level', PARAM_ACTION);
    if ($level != 'user') {
        return array();
    }

    if (!isset($field->owners['moodle_profile'])) {
        return array('moodle_profile_exclusive' => -1);
    } else {
        return array('moodle_profile_exclusive' => ($field->owners['moodle_profile']->exclude ? 1 : 0));
    }
}

function moodle_profile_field_save_form_data($form, $field, $data) {
    $level = $form->_customdata->required_param('level', PARAM_ACTION);
    if ($level != 'user') {
        return;
    }

    global $CURMAN;
    if ($data->moodle_profile_exclusive == cm_moodle_profile::sync_to_moodle
        || $data->moodle_profile_exclusive == cm_moodle_profile::sync_from_moodle) {
        if (isset($field->owners['moodle_profile'])) {
            $owner = new field_owner($field->owners['moodle_profile']);
            $owner->exclude = $data->moodle_profile_exclusive;
            $owner->update();
        } else {
            $owner = new field_owner();
            $owner->fieldid = $field->id;
            $owner->plugin = 'moodle_profile';
            $owner->exclude = $data->moodle_profile_exclusive;
            $owner->add();
        }

        unset($field->owners); // force reload of owners field
        sync_profile_field_with_moodle($field);
    } else {
        $CURMAN->db->delete_records(FIELDOWNERTABLE, 'fieldid', $field->id, 'plugin', 'moodle_profile');
    }
}

?>
