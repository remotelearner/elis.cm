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

// this is needed if we get called in the event handler
require_once dirname(__FILE__) . '/../../config.php';

require_once CURMAN_DIRLOCATION . '/lib/cluster.class.php';
require_once CURMAN_DIRLOCATION . '/lib/user.class.php';
require_once CURMAN_DIRLOCATION . '/lib/lib.php';

function cluster_profile_delete_for_cluster($id) {
    global $CURMAN;

    return $CURMAN->db->delete_records(CLSTPROFTABLE, 'clusterid', $id);
}

/* adds to form:
 * profile_field1 -select box
 * profile_value1 -select box corresponding to profile_field1
 *
 * profile_field2 -select box
 * profile_value2 -select box corresponding to profile_field2
 */
function cluster_profile_edit_form($cluster) {
    global $CURMAN, $CFG;

    $mform =& $cluster->_form;

/// Only get--at most--two profile field associations for this cluster.
    if(empty($cluster->_customdata['obj']->id)) {
        $prof_fields = array();
    } else {
        $prof_fields = $CURMAN->db->get_records(CLSTPROFTABLE, 'clusterid', $cluster->_customdata['obj']->id, '', '*', 0, 2);
    }

    require_js('yui_yahoo');
    require_js('yui_event');
    require_js('yui_connection');
    require_js($CFG->wwwroot.'/curriculum/cluster/profile/profile_value.js');

/// First profile field
    if (!empty($prof_fields)) {
        $prof_field = array_shift($prof_fields);
    } else {
        $prof_field = null;
    }

    $select = "datatype = 'menu' OR
               datatype = 'checkbox' OR
               datatype = 'text'";

    $fields = get_records_select('user_info_field', $select, '', 'id, name');
    $profile_field_options = array(0 => get_string('dont_auto', 'block_curr_admin', ''));

    if(!empty($fields)) {
        foreach($fields as $field) {
            $profile_field_options[$field->id] = htmlspecialchars($field->name);
        }
    }

/// Retrieve the profile value, if set
    $value = null;
    if(isset($mform->_defaultValues['profile_value1'])) {
        $value = $mform->_defaultValues['profile_value1'];
    }

/// Create an appropriate value for passing to Javascript
    $value_js = $value === null ? 'null' : "'$value'";

/// Determine if the profile field is of type "text" as it affects the behaviour

    if(!empty($prof_field->fieldid)) {
        $datatype = get_field('user_info_field', 'datatype', 'id', $prof_field->fieldid);
        $originallyText = $datatype == 'text' ? 'true' : 'false';
    } else {
        $originallyText = 'false';
    }

/// Add field selector to form

    $js_function_call = "update_profile_value_list('1', 'profile_value1', $value_js, $originallyText)";
    $mform->addElement('select', 'profile_field1', get_string('auto_associate', 'block_curr_admin') . ':',
                       $profile_field_options, array('onchange'=>$js_function_call));
    $mform->setHelpButton('profile_field1', array('clusterform/profile_plugin', get_string('auto_associate', 'block_curr_admin'), 'block_curr_admin'));

/// Add field options to form

    $mform->addElement('html', '<div class="fitemtitle"></div><div id="cluster_profile_div1" class="felement">' .
                               get_profile_field_options($prof_field, 'profile_value1', $value) . '</div>');

/// Second profile field
    if (!empty($prof_fields)) {
        $prof_field = array_shift($prof_fields);
    } else {
        $prof_field = null;
    }

/// Retrieve the profile value, if set
    $value = null;
    if(isset($mform->_defaultValues['profile_value2'])) {
        $value = $mform->_defaultValues['profile_value2'];
    }

/// Create an appropriate value for passing to Javascript
    $value_js = $value === null ? 'null' : "'$value'";

/// Determine if the profile field is of type "text" as it affects the behaviour

    if(!empty($prof_field->fieldid)) {
        $datatype = get_field('user_info_field', 'datatype', 'id', $prof_field->fieldid);
        $originallyText = $datatype == 'text' ? 'true' : 'false';
    } else {
        $originallyText = 'false';
    }

/// Add field selector to form

    $js_function_call = "update_profile_value_list('2', 'profile_value2', $value_js, $originallyText)";
    $mform->addElement('select', 'profile_field2', get_string('auto_associate', 'block_curr_admin') . ':',
                       $profile_field_options, array('onchange'=>$js_function_call));

/// Add field options to form

    $mform->addElement('html', '<div class="fitemtitle"></div><div id="cluster_profile_div2" class="felement">' .
                               get_profile_field_options($prof_field, 'profile_value2', $value) . '</div>');

}

/**
 * Get the possible options for the given profile field.
 *
 * @param  stdClass  $prof_field      an object containing the appropriate field id
 * @param  int       $elementid       the index of the association we are referring to (1 or 2)
 * @param  string    $existing_value  a pre-set value for the element
 * @return string                     HTML representing the necessary input element
 */
function get_profile_field_options($prof_field, $elementid, $existing_value=null) {

    if(empty($prof_field->fieldid)) {
        return get_string('option_profile_field', 'block_curr_admin');
    }

    $fieldid = $prof_field->fieldid;

    $fields = get_record('user_info_field', 'id', $fieldid, '', '', '', '', 'datatype, defaultdata, param1');

    if (empty($fields)) {
        return get_string('option_profile_field', 'block_curr_admin');
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
        if($existing_value !== null) {
            $default = htmlspecialchars($existing_value);
        }

        //create and return the appropriate HTML
        foreach ($options as $value => $display) {
            $selected = ($value == $default ? ' selected' : '');
            $return_html .= "<option value=\"$value\"$selected>$display</option>\n";
        }
        $return_html = '<select id="' . $elementid . '" name="' . $elementid . '">' . $return_html . '</select>';
        return $return_html;

    case 'checkbox':

        //set up the default value
        $default = htmlspecialchars($fields->defaultdata);
        if($existing_value !== null) {
            $default = htmlspecialchars($existing_value);
        }

        //set up and return the appropriate HTML
        $checked = !empty($default) ? 'checked="checked"' : '';

        return '<input type="checkbox" id="' . $elementid . '" name="' . $elementid . '"' . $checked . '/>';

    case 'text';

        //set up the default value, based only on an actual set value
        $default = '';
        if($existing_value !== null) {
            $default = htmlspecialchars($existing_value);
        }

        //set up and return the appropriate HTML
        return '<input type="text" id ="' . $elementid . '" name="' . $elementid . '" value="' . $default . '"/>';

    default:

        //this should never happen, as only the types listed above are supported
        return '';
    }
}

function cluster_profile_update($cluster) {
    global $CURMAN, $CFG;

    $prof_fields = $CURMAN->db->get_records(CLSTPROFTABLE, 'clusterid', $cluster->id, '', '*', 0, 2);

    if (!empty($prof_fields)) {
        $prof_field1 = array_shift($prof_fields);
    }

    if (!empty($prof_fields)) {
        $prof_field2 = array_shift($prof_fields);
    }

    $oldfield1 = empty($prof_field1) ? 0 : $prof_field1->fieldid;
    $oldvalue1 = empty($prof_field1) ? '' : $prof_field1->value;

    $newfield1 = cm_get_param('profile_field1', 0);
    $newvalue1 = cm_get_param('profile_value1', '');

    $oldfield2 = empty($prof_field2) ? 0 : $prof_field2->fieldid;
    $oldvalue2 = empty($prof_field2) ? '' : $prof_field2->value;

    $newfield2 = cm_get_param('profile_field2', 0);
    $newvalue2 = cm_get_param('profile_value2', '');

    if ($newfield1 == -1) {
        $newvalue1 = '';
    }

/// convert checkbox values from 'on' and '' to 1 and 0
    if('checkbox' == get_field('user_info_field', 'datatype', 'id', $newfield1)) {
        $newvalue1 = empty($newvalue1) ? 0 : 1;
    }

    if('checkbox' == get_field('user_info_field', 'datatype', 'id', $newfield2)) {
        $newvalue2 = empty($newvalue2) ? 0 : 1;
    }

    $updated = false;  // Have we updated the profile field associated with this cluster?

/// Update the first field.
    if ($oldfield1 != $newfield1 || $oldvalue1 != $newvalue1) {
        $updated = true;

        // something has changed, so update tables
        if (empty($newfield1)) {
            $CURMAN->db->delete_records(CLSTPROFTABLE, 'clusterid', $cluster->id, 'fieldid', $oldfield1);
        } else if (empty($prof_field1)) {
            $record = new stdClass;
            $record->clusterid = $cluster->id;
            $record->fieldid   = $newfield1;
            $record->value     = $newvalue1;
            $CURMAN->db->insert_record(CLSTPROFTABLE, $record);
        } else {
            $prof_field1->fieldid = $newfield1;
            $prof_field1->value   = $newvalue1;
            $CURMAN->db->update_record(CLSTPROFTABLE, $prof_field1);
        }
    }

    if (empty($newfield2)) {
        $newvalue2 = '';
    }

/// Update the second field.
    if ($oldfield2 != $newfield2 || $oldvalue2 != $newvalue2) {
        $updated = true;

        // something has changed, so update tables
        if (empty($newfield2)) {
            $CURMAN->db->delete_records(CLSTPROFTABLE, 'clusterid', $cluster->id, 'fieldid', $oldfield2);
        } else if (empty($prof_field2)) {
            $record = new stdClass;
            $record->clusterid = $cluster->id;
            $record->fieldid   = $newfield2;
            $record->value     = $newvalue2;
            $CURMAN->db->insert_record(CLSTPROFTABLE, $record);
        } else {
            $prof_field2->fieldid = $newfield2;
            $prof_field2->value   = $newvalue2;
            $CURMAN->db->update_record(CLSTPROFTABLE, $prof_field2);
        }
    }

    if ($updated) {
        // re-assign users:
        // remove previous cluster assignments
        $CURMAN->db->delete_records(CLSTASSTABLE, 'clusterid', $cluster->id, 'plugin', 'profile');

        // create new cluster assignments
        $join  = '';
        $where = '';

        if (!empty($newfield1)) {
            // check if the desired field value is equal to the field's default
            // value -- if so, we need to include users that don't have an
            // associated entry in user_info_data
            $defaultvalue = get_field('user_info_field', 'defaultdata', 'id', $newfield1);
            $isdefault    = ($newvalue1 == $defaultvalue);

            $join  .= ($isdefault ? 'LEFT' : 'INNER') . " JOIN {$CFG->prefix}user_info_data inf1 ON mu.id = inf1.userid AND inf1.fieldid = $newfield1";
            $where .= "(inf1.data = '$newvalue1'";

            // if desired field is the default
            if ($isdefault) {
                $where .= ' OR inf1.userid IS NULL';
            }
            $where .= ')';
        }

        if (!empty($newfield2)) {
            // check if the desired field value is equal to the field's default
            // value -- if so, we need to include users that don't have an
            // associated entry in user_info_data
            $defaultvalue = get_field('user_info_field', 'defaultdata', 'id', $newfield2);
            $isdefault    = ($newvalue2 == $defaultvalue);

            $join  .= (!empty($join) ? "\n" : '') . ($isdefault ? 'LEFT' : 'INNER') .
                      " JOIN {$CFG->prefix}user_info_data inf2 ON mu.id = inf2.userid AND inf2.fieldid = $newfield2";
            $where .= (!empty($where) ? ' AND ' : '') .
                      "(inf2.data = '$newvalue2'";

            // if desired field is the default
            if ($isdefault) {
                $where .= ' OR inf2.userid IS NULL';
            }
            $where .= ')';
        }

        if (!empty($join) && !empty($where)) {
            $sql = "INSERT INTO " . $CURMAN->db->prefix_table(CLSTASSTABLE) . "
                    (clusterid, userid, plugin)
                    SELECT $cluster->id, cu.id, 'profile'
                    FROM " . $CURMAN->db->prefix_table(USRTABLE) . " cu
                    INNER JOIN {$CFG->prefix}user mu ON mu.idnumber = cu.idnumber AND mu.mnethostid = {$CFG->mnet_localhost_id}
                    $join
                    WHERE $where";

            $CURMAN->db->execute_sql($sql, false);
        }

        cluster::cluster_update_assignments($cluster->id);
    }
}

function cluster_profile_update_handler($userdata) {
    global $CURMAN, $CFG;

    // make sure a CM user exists
    cm_moodle_user_to_cm($userdata);

    $cuid = cm_get_crlmuserid($userdata->id);

    if (empty($cuid)) {
        // not a curriculum user -- (guest?)
        return true;
    }

    $usrtable      = $CURMAN->db->prefix_table(USRTABLE);
    $clstproftable = $CURMAN->db->prefix_table(CLSTPROFTABLE);
    $clstasstable  = $CURMAN->db->prefix_table(CLSTASSTABLE);

    // the cluster assignments that the plugin wants to exist
    $new_assignments = "(SELECT DISTINCT cu.id as userid, cp.clusterid
                         FROM {$CFG->prefix}crlm_cluster_profile cp
                         INNER JOIN {$CFG->prefix}crlm_user cu ON cu.id = $cuid
                         INNER JOIN {$CFG->prefix}user mu on cu.idnumber=mu.idnumber AND mu.mnethostid = {$CFG->mnet_localhost_id}
                         WHERE (SELECT COUNT(*)
                                FROM {$CFG->prefix}crlm_cluster_profile cp1
                                JOIN (SELECT i.userid, i.fieldid, i.data FROM {$CFG->prefix}user_info_data i
                                      WHERE i.userid = {$userdata->id}
                                      UNION
                                      SELECT  {$userdata->id} as userid, uif.id as fieldid, uif.defaultdata as data
                                      FROM {$CFG->prefix}user_info_field uif
                                      LEFT JOIN {$CFG->prefix}user_info_data i ON i.userid={$userdata->id} AND uif.id = i.fieldid
                                      WHERE i.id IS NULL
                                     ) inf ON inf.fieldid = cp1.fieldid AND inf.data = cp1.value
                                WHERE cp.clusterid=cp1.clusterid AND inf.userid = mu.id)
                               = (SELECT COUNT(*) FROM {$CFG->prefix}crlm_cluster_profile cp1 WHERE cp.clusterid = cp1.clusterid))";

    // delete existing assignments that should not be there any more
    if ($CFG->dbfamily == 'postgres') {
        $delete = "DELETE FROM $clstasstable
                   WHERE id IN (
                       SELECT id FROM $clstasstable a
                       LEFT OUTER JOIN $new_assignments b ON a.clusterid = b.clusterid AND a.userid = b.userid
                       WHERE a.userid = $cuid AND b.clusterid IS NULL
                   ) AND plugin='profile'";
    } else {
        $delete = "DELETE a FROM $clstasstable a
                   LEFT OUTER JOIN $new_assignments b ON a.clusterid = b.clusterid AND a.userid = b.userid
                   WHERE a.userid = $cuid AND b.clusterid IS NULL AND a.plugin='profile'";
    }
    $CURMAN->db->execute_sql($delete, false);

    // add new assignments
    $insert = "INSERT INTO $clstasstable
               (clusterid, userid, plugin)
               SELECT a.clusterid, a.userid, 'profile'
               FROM $new_assignments a
               LEFT OUTER JOIN $clstasstable b ON a.clusterid = b.clusterid AND a.userid = b.userid AND b.plugin='profile'
               WHERE a.userid = $cuid AND b.clusterid IS NULL";

    $CURMAN->db->execute_sql($insert, false);

    cluster::cluster_update_assignments(null, $cuid);

    return true;
}

?>
