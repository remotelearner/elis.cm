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
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/../../../../../config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(elispm::lib('data/userset.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elis::plugin_file('usersetenrol_moodle_profile', 'userset_profile.class.php'));

function cluster_moodle_profile_delete_for_cluster($id) {
    userset_profile::delete_records(new field_filter('clusterid', $id));
}

/* adds to form:
 * profile_field1 -select box
 * profile_value1 -select box corresponding to profile_field1
 *
 * profile_field2 -select box
 * profile_value2 -select box corresponding to profile_field2
 */
function cluster_moodle_profile_edit_form($form, $mform, $clusterid) {
    global $CFG, $DB, $PAGE;

/// Only get--at most--two profile field associations for this cluster.
    if (empty($clusterid)) {
        $prof_fields = null;
    } else {
        $prof_fields = userset_profile::find(new field_filter('clusterid', $clusterid), array(), 0, 2);
    }

    $PAGE->requires->js('/elis/program/enrol/userset/moodle_profile/profile_value.js');

    for ($i = 1; $i <= 2; $i++) {
        if ($prof_fields && $prof_fields->valid()) {
            $prof_field = $prof_fields->current();
            $prof_fields->next();
        } else {
            $prof_field = null;
        }

        $select = "datatype = 'menu'
                OR datatype = 'checkbox'
                OR datatype = 'text'";

        $profile_field_options = $DB->get_records_select_menu('user_info_field', $select, null, 'id, name');
        $profile_field_options = array(0 => get_string('dont_auto', 'usersetenrol_moodle_profile', '')) + $profile_field_options;

        /// Retrieve the profile value, if set
        $value = null;
        $value_js = 'null';
        if (isset($mform->_defaultValues['profile_value'.$i])) {
            $value = $mform->_defaultValues['profile_value'.$i];
            /// Create an appropriate value for passing to Javascript
            $value_js = "'" . str_replace("'", "\\'", $value) . "'";
        }

        /// Determine if the profile field is of type "text" as it affects the behaviour

        if (!empty($prof_field->fieldid)) {
            $datatype = $DB->get_field('user_info_field', 'datatype', array('id' => $prof_field->fieldid));
            $originallyText = ($datatype == 'text') ? 'true' : 'false';
            $data = array("profile_field{$i}" => $prof_field->fieldid);
            $form->set_data($data);
        } else {
            $originallyText = 'false';
        }

        /// Add field selector to form

        $js_function_call = "update_profile_value_list('{$i}', 'profile_value{$i}', $value_js, $originallyText)";
        $mform->addElement('select', "profile_field{$i}", get_string('auto_associate', 'usersetenrol_moodle_profile'),
                           $profile_field_options, array('onchange'=>$js_function_call));
        if ($i == 1) {
            $mform->addHelpButton('profile_field1', 'auto_associate', 'usersetenrol_moodle_profile');
        }

        /// Add field options to form
        // TBD: hack below is because $mform->isFrozen() ... didn't work!
        $isfrozen = optional_param('action', '', PARAM_CLEAN) == 'view';

        $mform->addElement('static', '', get_string('set_to', 'usersetenrol_moodle_profile'), html_writer::start_tag('div', array('id' => "cluster_profile_div{$i}")) . get_moodle_profile_field_options($prof_field, "profile_value{$i}", $value, $isfrozen) . html_writer::end_tag('div'));
    }
}

/**
 * Get the possible options for the given profile field.
 *
 * @param  stdClass  $prof_field      an object containing the appropriate field id
 * @param  int       $elementid       the index of the association we are referring to (1 or 2)
 * @param  string    $existing_value  a pre-set value for the element
 * @param  bool      $frozen          true if form is frozen, false (default) otherwise
 * @return string                     HTML representing the necessary input element
 */
function get_moodle_profile_field_options($prof_field, $elementid, $existing_value=null, $frozen = false) {
    global $DB;

    //error_log("get_moodle_profile_field_options(prof_field(obj), elementid = {$elementid}, existing_value = {$existing_value}, frozen = {$frozen});");
    if (empty($prof_field->fieldid)) {
        return get_string('option_profile_field', 'usersetenrol_moodle_profile');
    }

    $fieldid = $prof_field->fieldid;
    $fields = $DB->get_record('user_info_field', array('id' => $fieldid));

    if (empty($fields)) {
        return get_string('option_profile_field', 'usersetenrol_moodle_profile');
    }

    switch ($fields->datatype) {
    case 'menu':
        $return_html = '';

        //create the list of possible options
        $values = explode("\n", $fields->param1);
        $options = array();
        foreach ($values as $value) {
            $options[htmlspecialchars($value)] = htmlspecialchars($value);
        }

        //set up the default value
        $default = htmlspecialchars($fields->defaultdata);
        if ($existing_value !== null) {
            $default = htmlspecialchars($existing_value);
        }

        if ($frozen) {
            break;
        }

        //create and return the appropriate HTML
        foreach ($options as $value => $display) {
            $selected = ($value == $default ? ' selected' : '');
            $return_html .= "<option value=\"$value\"$selected>$display</option>\n";
        }
        $return_html = '<select id="'. $elementid .'" name="'. $elementid .'">'. $return_html .'</select>';
        return $return_html;

    case 'checkbox':
        //set up the default value
        $default = htmlspecialchars($fields->defaultdata);
        if ($existing_value !== null) {
            $default = htmlspecialchars($existing_value);
        }

        //set up and return the appropriate HTML
        $checked = !empty($default) ? 'checked="checked"' : '';
        if ($frozen) {
            $checked .= ' disabled="disabled"';
        }

        return '<input type="checkbox" id="'. $elementid .'" name="'. $elementid .'"'. $checked .'/>';

    case 'text';
        //set up the default value, based only on an actual set value
        $default = '';
        if ($existing_value !== null) {
            $default = htmlspecialchars($existing_value);
        }

        if ($frozen) {
            break;
        }

        //set up and return the appropriate HTML
        return '<input type="text" id ="'. $elementid .'" name="'. $elementid .'" value="'. $default .'"/>';

    default:
        //this should never happen, as only the types listed above are supported
        return '';
    }

    // Frozen form return
    return $default;
}

function userset_moodle_profile_update($cluster) {
    global $CFG, $DB;

    // get the "old" (existing) profile field assignment values
    $old = userset_profile::find(new field_filter('clusterid', $cluster->id), array(), 0, 2)->to_array();

    // get the "new" (submitted) profile field assignment values
    $new = array();
    for ($i = 1; $i <= 2; $i++) {
        $newfield = optional_param("profile_field{$i}", 0, PARAM_INT);
        if ($newfield) {
            /// convert checkbox values from 'on' and '' to 1 and 0
            $new[$newfield] = optional_param("profile_value{$i}", '', PARAM_CLEAN);
            if('checkbox' == $DB->get_field('user_info_field', 'datatype', array('id' => $newfield))) {
                $new[$newfield] = empty($new[$newfield]) ? 0 : 1;
            }
        }
    }

    $updated = false;  // Have we updated the profile field associated with this cluster?

    // Compare old values against new values
    foreach ($old as $field) {
        if (!isset($new[$field->id])) {
            // old field is no longer a field
            $field->delete();
            unset($old[$field->id]);
            $updated = true;
        } else if ($new[$field->id] != $field->value) {
            // value has changed
            $field->value = $new[$field->id];
            $field->save();
            $updated = true;
        }
    }

    // check for added fields
    $added = array_diff_key($new, $old);
    foreach ($added as $fieldid => $value) {
        $record = new userset_profile();
        $record->clusterid = $cluster->id;
        $record->fieldid = $fieldid;
        $record->value = $value;
        $record->save();
        $updated = true;
    }

    if ($updated) {
        // re-assign users:
        // remove previous cluster assignments
        clusterassignment::delete_records(array(new field_filter('clusterid', $cluster->id),
                                                new field_filter('plugin', 'moodle_profile')));

        // create new cluster assignments
        $join  = '';
        $join_params = array();
        $where_clauses = array();
        $where_params = array();
        $i = 1;

        foreach ($new as $fieldid => $value) {
            // check if the desired field value is equal to the field's default
            // value -- if so, we need to include users that don't have an
            // associated entry in user_info_data
            $defaultvalue = $DB->get_field('user_info_field', 'defaultdata', array('id' => $fieldid));
            $isdefault    = ($value == $defaultvalue);

            $join  .= ($isdefault ? ' LEFT' : ' INNER') . " JOIN {user_info_data} inf{$i} ON mu.id = inf{$i}.userid AND inf{$i}.fieldid = ?";
            $join_params[] = $fieldid;
            $where = "(inf{$i}.data = ?";

            // if desired field is the default
            if ($isdefault) {
                $where .= " OR inf{$i}.userid IS NULL";
            }
            $where .= ')';
            $where_clauses[] = $where;
            $where_params[] = $value;
            $i++;
        }

        //use the clauses to construct a where condition
        $where_clause = implode(' AND ', $where_clauses);

        if (!empty($join) && !empty($where)) {
            $sql = "INSERT INTO {" . clusterassignment::TABLE . "}
                    (clusterid, userid, plugin)
                    SELECT ?, cu.id, 'moodle_profile'
                    FROM {" . user::TABLE . "} cu
                    INNER JOIN {user} mu ON mu.idnumber = cu.idnumber
                    $join
                    WHERE $where_clause";
            $params = array_merge(array($cluster->id), $join_params, $where_params);

            $DB->execute($sql, $params);
        }
    }

    clusterassignment::update_enrolments(0, $cluster->id);
}

function cluster_profile_update_handler($userdata) {
    global $DB, $CFG;

    // make sure a CM user exists
    pm_moodle_user_to_pm($userdata);

    if (!isset($userdata->id)) {
        return true;
    }
    $cuid = pm_get_crlmuserid($userdata->id);

    if (empty($cuid)) {
        // not a curriculum user -- (guest?)
        return true;
    }

    // the cluster assignments that the plugin wants to exist
    // we figure this out by counting the number of profile fields that the
    // user has that matches the values for the cluster, and comparing that
    // with the number of profile values set for the cluster
    $new_assignments = "(SELECT DISTINCT ? as userid, cp.clusterid
                         FROM {" . userset_profile::TABLE . "} cp
                         WHERE (SELECT COUNT(*)
                                FROM {" . userset_profile::TABLE . "} cp1
                                JOIN (SELECT i.fieldid, i.data FROM {user_info_data} i
                                      WHERE i.userid = ?
                                      UNION
                                      SELECT uif.id as fieldid, uif.defaultdata as data
                                      FROM {user_info_field} uif
                                      LEFT JOIN {user_info_data} i ON i.userid = ? AND uif.id = i.fieldid
                                      WHERE i.id IS NULL
                                     ) inf ON inf.fieldid = cp1.fieldid AND inf.data = cp1.value
                                WHERE cp.clusterid=cp1.clusterid)
                               = (SELECT COUNT(*) FROM {" . userset_profile::TABLE . "} cp1 WHERE cp.clusterid = cp1.clusterid))";
    $new_assignments_params = array($cuid, $userdata->id, $userdata->id);

    // delete existing assignments that should not be there any more
    if ($CFG->dbfamily == 'postgres') {
        $delete = "DELETE FROM {" . clusterassignment::TABLE . "}
                   WHERE id IN (
                       SELECT id FROM {" . clusterassignment::TABLE . "} a
                       LEFT OUTER JOIN $new_assignments b ON a.clusterid = b.clusterid AND a.userid = b.userid
                       WHERE a.userid = ? AND b.clusterid IS NULL
                   ) AND plugin='moodle_profile'";
    } else {
        $delete = "DELETE a FROM {" . clusterassignment::TABLE . "} a
                   LEFT OUTER JOIN $new_assignments b ON a.clusterid = b.clusterid AND a.userid = b.userid
                   WHERE a.userid = ? AND b.clusterid IS NULL AND a.plugin='moodle_profile'";
    }
    $DB->execute($delete, array_merge($new_assignments_params, array($cuid)));

    // add new assignments
    $insert = "INSERT INTO {" . clusterassignment::TABLE . "}
               (clusterid, userid, plugin)
               SELECT a.clusterid, a.userid, 'moodle_profile'
               FROM $new_assignments a
               LEFT OUTER JOIN {" . clusterassignment::TABLE . "} b ON a.clusterid = b.clusterid AND a.userid = b.userid AND b.plugin='moodle_profile'
               WHERE a.userid = ? AND b.clusterid IS NULL";

    $DB->execute($insert, array_merge($new_assignments_params, array($cuid)));

    clusterassignment::update_enrolments($cuid);

    return true;
}
