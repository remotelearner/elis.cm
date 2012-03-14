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
 * @subpackage enrol_survey
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

function is_survey_taken($userid, $instanceid) {
    global $DB;
    $temp = $DB->get_record('block_enrol_survey_taken', array('userid' => $userid, 'blockinstanceid' => $instanceid));
    return !empty($temp);
}

function get_profilefields() {
    global $CFG, $DB;

    //get elis user fields as it appears in the db
    $sql = 'SELECT u.* FROM {crlm_user} u HAVING min(id)'; // TBD
    $profile_fields = get_object_vars(current($DB->get_records_sql($sql)));

    //unset any fields that should never be allowed to be changed eg. the id field
    unset($profile_fields['id']);
    unset($profile_fields['idnumber']);
    $profile_fields = array_keys($profile_fields);
    return array_combine($profile_fields, $profile_fields);
}

function get_customfields() {
    global $DB;
    $retval = array();
    //pull custom field values from moodle
    $custom_fields = $DB->get_records('user_info_field');
    if (!empty($custom_fields)) {
        foreach ($custom_fields as $cf) {
            $profile_fields[] = $cf->shortname;
        }

        $retval = array_combine($profile_fields, $profile_fields);
    }

    return $retval;
}

function get_fields() {
    return array_merge(get_profilefields(), get_customfields());
}

function get_questions() {
    global $block;

    $other = array('force_user', 'title');
    $retval = array();

    if (!empty($block->config)) {
        foreach ($block->config as $key=>$value) {
            if (!in_array($key, $other)) {
                $retval[$key] = $value;
            }
        }
    }

    return $retval;
}

function get_forceuser() {
    global $block;
    return empty($block->config->force_user) ? false : true;
}

