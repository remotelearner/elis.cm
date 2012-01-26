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
 * @subpackage curriculummanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once CURMAN_DIRLOCATION . '/lib/tag.class.php';


define('TAGINSTABLE', 'crlm_tag_instance');


// TODO: make this a subclass of datarecord
class taginstance {
    var $id;           // INT - The data id if in the database.
    var $instancetype; // STRING - The instance type.
    var $instanceid;   // INT - The instance type ID.
    var $tagid;        // INT - The tag ID.
    var $tag;          // OBJECT - The tag record object.
    var $data;         // STRING - Extra info for this instance.
    var $timecreated;  // INT - Timestamp.
    var $timemodified; // INT - Timestamp.

    var $_dbloaded;         // BOOLEAN - True if loaded from database.

    // Allowable values for the timeperiod property.
    var $instancetype_values = array(
        ''    => '',
        'cur' => 'Curriculum',
        'crs' => 'Course',
        'cls' => 'Class',
    );

    const tablename = TAGINSTABLE;

    // STRING - Styles to use for edit form.
    var $_editstyle = '
.taginstanceeditform input,
.taginstanceeditform textarea,
.taginstanceeditform select {
    margin: 0;
    display: block;
}
';

    public function get_table() {
    	return self::tablename;
    }

    /**
     * Contructor.
     *
     * @uses $CURMAN
     * @param $curcrsdata int/object/array The data id of a data record or data elements to load manually.
     */
    function taginstance ($curcrsdata = false) {
        global $CURMAN;

        $this->_dbloaded = false;

        $this->id           = 0;
        $this->instancetype = '';
        $this->instanceid   = 0;
        $this->tagid        = 0;
        $this->tag          = new stdClass;
        $this->data         = '';
        $this->timecreated  = 0;
        $this->timemodified = 0;

        if (is_numeric($curcrsdata)) {
            $this->data_load_record($curcrsdata);
        } else if (is_array($curcrsdata)) {
            $this->data_load_array($curcrsdata);
        } else if (is_object($curcrsdata)) {
            $this->data_load_array(get_object_vars($curcrsdata));
        }

        if (!empty($this->tagid)) {
            $this->tag = new tag($this->tagid);
        }
    }

    public function delete() {
    	return $this->data_delete_record();
    }

	public static function delete_for_class($id) {
    	global $CURMAN;

    	return $CURMAN->db->delete_records(TAGINSTABLE, 'instanceid', $id, 'instancetype', 'cls');
    }

	public static function delete_for_course($id) {
    	global $CURMAN;

    	return $CURMAN->db->delete_records(TAGINSTABLE, 'instanceid', $id, 'instancetype', 'crs');
    }

	public static function delete_for_curriculum($id) {
    	global $CURMAN;

    	return $CURMAN->db->delete_records(TAGINSTABLE, 'instanceid', $id, 'instancetype', 'cur');
    }

	public static function delete_for_tag($id) {
    	global $CURMAN;

    	return $CURMAN->db->delete_records(TAGINSTABLE, 'tagid', $id);
    }

/////////////////////////////////////////////////////////////////////
//                                                                 //
//  FORM FUNCTIONS:                                                //
//                                                                 //
/////////////////////////////////////////////////////////////////////


    /**
     * Return the HTML to edit a specific curriculum course.
     * This could be extended to allow for application specific editing, for example
     * a Moodle interface to its formslib.
     *
     * @uses $CURMAN
     * @param $formid string A suffix to put on all 'id' and index for all 'name' attributes.
     *                       This should be unique if being used more than once in a form.
     * @param $extraclass string Any extra class information to add to the output.
     *
     * @return string The form HTML, without the form.
     */
    function edit_form_html($formid='', $extraclass='', $rows='2', $cols='40') {
        global $CURMAN;

        $index = !empty($formid) ? '['.$formid.']' : '';
        $formid_suffix = !empty($formid) ? '_'.$formid : '';

        $output = '';

        $output .= '<style>'.$this->_editstyle.'</style>';
        $output .= '<fieldset id="taginstanceeditform'.$formid.'" class="taginstanceeditform '.$extraclass.'">'."\n";
        $output .= '<legend>' . get_string('curriculumcourse_edit', 'block_curr_admin') . '</legend>'."\n";

        $output .= '<input type="hidden" name="curriculumid" value="' . $this->curriculumid . '" />';

        $crss = $this->get_courses_avail();

        if (!empty($this->courseid)) {
            $output .= '<input type="hidden" name="courseid" value="' . $this->courseid . '" />';
            $crss[$this->courseid] = $CURMAN->db->get_record('crlm_course', 'id', $this->courseid,
                                                             '', '', '', '', 'id, name, idnumber');
        }

        asort($crss);

        $output .= '<label for="courseid'.$formid.'" id="lcourseid'.$formid.'">Course:';
        $output .= '<select name="courseid'.$index.'" id="courseid'.$formid.'" '.
                   'class="taginstanceeditform '.$extraclass.'"' .
                   (!empty($this->courseid) ? ' disabled' : '') . '>'."\n";

        foreach ($crss as $crsid => $crs) {
            $output .= '<option value="'.$crsid.'"' . ($this->courseid == $crsid ? ' selected' : '') .
                       '> ('.$crs->idnumber . ') ' . $crs->name.'</option>'."\n";
        }
        $output .= '</select>';
        $output .= '</label>';

        $output .= '<label for="frequency'.$formid.'" id="lfrequency'.$formid.'">Frequency:';
        $output .= '<input type="text" name="frequency'.$index.'"value="'.$this->frequency.'" id="frequency'.$formid.'" '.
                   'class="taginstanceeditform '.$extraclass.'" maxlength="64" />'."\n";
        $output .= '</label>';

        $output .= '<label for="timeperiod'.$formid.'" id="ltimeperiod'.$formid.'">Timeperiod:';
        $output .= '<select name="timeperiod'.$index.'" id="timeperiod'.$formid.'" '.
                   'class="taginstanceeditform '.$extraclass.'">'."\n";
        foreach ($this->timeperiod_values as $tpi => $tpv) {
            $output .= '<option value="'.$tpi.'">'.$tpv.'</option>'."\n";
        }
        $output .= '</select>';
        $output .= '</label>';

        $output .= '<label for="position'.$formid.'" id="lposition'.$formid.'">Position:';
        $output .= '<input type="text" name="position'.$index.'"value="'.$this->position.'" id="position'.$formid.'" '.
                   'class="taginstanceeditform '.$extraclass.'" />'."\n";
        $output .= '</label>';

        $output .= '<input type="hidden" name="id'.$index.'" value="'.$this->id.'" />'."\n";
        $output .= '</fieldset>';

        return $output;
    }


/////////////////////////////////////////////////////////////////////
//                                                                 //
//  DATA FUNCTIONS:                                                //
//                                                                 //
/////////////////////////////////////////////////////////////////////


    /**
     * Function to load the object with data from the database.
     *
     * @param $id int The data id.
     * @uses  $CURMAN global.
     *
     */
    function data_load_record($id) {
        global $CURMAN;

        if (!($record = $CURMAN->db->get_record(TAGINSTABLE, 'id', $id))) {
            return false;
        }
        $fields = get_object_vars($record);
        foreach ($fields as $field => $value) {
            $this->$field = $value;
        }

        $this->_dbloaded = true;

        return true;
    }


    /**
     * Function to load the object with data from passed parameter.
     *
     * @param $data array Array of properties and values.
     *
     */
    function data_load_array($data) {

        if (!is_array($data)) {
            return false;
        }

        foreach ($data as $property => $value) {
            if ((strpos($property, '_') !== 0) && property_exists(__CLASS__, $property)) {
                $this->$property = $value;
            }
        }
        return true;
    }


    /**
     * Data function to update the database record with the object contents.
     *
     * @param $createnew boolean If true, and the record doesn't exist, creates a new one.
     * @return boolean Status of the operation.
     * @uses  $CURMAN global.
     */
    function data_update_record($createnew = false) {
        global $CURMAN;

        if ($this->_dbloaded || !empty($this->id)) {
            $record = new stdClass();
            $record->id           = $CURMAN->db->clean_int($this->id);
            $record->instancetype = $CURMAN->db->clean_text($this->instancetype);
            $record->instanceid   = $CURMAN->db->clean_int($this->instanceid);
            $record->tagid        = $CURMAN->db->clean_int($this->tagid);
            $record->data         = $CURMAN->db->clean_text($this->data);
//            $record->timecreated  = $CURMAN->db->clean_int($this->timecreated);
            $record->timemodified = $CURMAN->db->clean_int($this->timemodified);

            if ($CURMAN->db->update_record(TAGINSTABLE, $record)) {
                return true;
            } else if (!$createnew) {
                return false;
            }
        }

        if ($createnew) {
            $record->timecreated = $CURMAN->db->clean_int($this->timecreated);
            return ($this->data_insert_record());
        } else {
            return false;
        }
    }


    /**
     * Data function to insert a database record with the object contents.
     *
     * @param $record object If present, uses the contents of it rather than the object.
     * @return boolean Status of the operation.
     * @uses  $CURMAN global.
     */
    function data_insert_record($record = false) {
        global $CURMAN;

        $scope = $this;

        if (!empty($record)) {
            $scope = $record;
        }

        $timenow              = time();
        $record->instancetype = $CURMAN->db->clean_text($scope->instancetype);
        $record->instanceid   = $CURMAN->db->clean_int($scope->instanceid);
        $record->tagid        = $CURMAN->db->clean_int($scope->tagid);
        $record->data         = $CURMAN->db->clean_text($scope->data);
        $record->timecreated  = $timenow;
        $record->timemodified = $timenow;

        /// Check for valid curriculum id - it can't already exist.
        if ($CURMAN->db->record_exists(TAGINSTABLE, 'instancetype', $record->instancetype,
                                       'instanceid', $record->instanceid, 'tagid', $record->tagid)) {
            $return = new stdClass();
            $return->status = false;
            $return->message = get_string('duplicate_tag', 'block_curr_admin');
            return $return;
        }

        if ($id = $CURMAN->db->insert_record(TAGINSTABLE, $record)) {
            $this->id = $id;
            return true;
        } else {
            return false;
        }
    }


    /**
     * Data function to insert a database record with the object contents.
     *
     * @param $record object If present, uses the contents of it rather than the object.
     * @return boolean Status of the operation.
     * @uses  $CURMAN global.
     */
    function data_delete_record($recordid = 0) {
        global $CURMAN;

        if (!$recordid) {
            $recordid = $this->id;
        }

        return ($CURMAN->db->delete_records(TAGINSTABLE, 'id', $recordid));
    }


    /**
     * Get all the tags assosciated with this instance.
     *
     * @uses $CURMAN
     * @parm none
     * @return array An array of instance tags.
     */
    static function get_instance_tags($instancetype, $instanceid) {
        global $CURMAN;

        //$select = "instancetype = '{$instancetype}' AND instanceid = '{$instanceid}'";

        //return $CURMAN->db->get_records_select(TAGINSTABLE, $select);

        $select  = 'SELECT tagins.id, tagins.tagid, tagins.instancetype, tagins.instanceid, tag.name, tag.description ';
        $tables  = 'FROM ' . $CURMAN->db->prefix_table(TAGINSTABLE) . ' tagins ';
        $join    = 'LEFT JOIN ' . $CURMAN->db->prefix_table(TAGTABLE) . ' tag '.
            'ON tagins.tagid = tag.id ';
        $where   = 'WHERE tagins.instancetype = \'' . $instancetype . '\' ';
        $where   .= 'AND tagins.instanceid = ' .  $instanceid . ' ';
        $sort    = 'ORDER BY tag.name ASC ';

        $sql = $select.$tables.$join.$where;

        return $CURMAN->db->get_records_sql($sql);
    }
}

?>
