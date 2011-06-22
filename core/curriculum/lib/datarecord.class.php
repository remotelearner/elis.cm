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

class datarecord {

    protected $_dbloaded;
    protected $table;
    public  $properties;
    public $_required = array();

    function datarecord() {
        $this->_dbloaded  = false;
        $this->table      = '';
        $this->properties = array();
    }


    /**
     * Define the database table name for this object.
     *
     * @param string $table The name of the table.
     * @return none
     */
    function set_table($table) {
        $this->table = $table;
    }
    
    /**
     * Retrieve the previously defined database table name for this object.
     * 
     * @return string The name of the table.
     */
    function get_table() {
    	return $this->table;
    }

    /**
     * Add a new property to this object.
     *
     * @param string $prop The property name.
     * @param string $type The property type (int, string).
     * @return none
     */
    function add_property($prop, $type, $required=false) {
        if (!property_exists($this, $prop)) {
            switch ($type) {
                case 'int':
                    $this->$prop = 0;
                    break;

                case 'string':
                case 'html':
                    $this->$prop = '';
                    break;

                case 'array':
                    $this->$prop = array();
                    break;

                case 'float':
                    $this->prop = 0.0;
                    break;

                default:
                    return;
            }

            $this->properties[$prop] = $type;

            if($required) {
                $this->_required[] = $prop;
            }
        }
    }


    public function has_required_fields() {
        foreach($this->_required as $rf) {
            if(empty($this->$rf)) {
                return false;
            }
        }
        
        return true;
    }

    public function get_missing_required_fields() {
        $retval = array();

        foreach($this->_required as $rf) {
            if(empty($this->$rf)) {
                $retval[] = $rf;
            }
        }

        return $retval;
    }

/////////////////////////////////////////////////////////////////////
//                                                                 //
//  DATA FUNCTIONS:                                                //
//                                                                 //
/////////////////////////////////////////////////////////////////////


    /**
     * Function to load the object with data from the database.
     *
     * @uses $CURMAN
     * @param $id int The data id.
     * @return bool True on success, False otherwise.
     */
    function data_load_record($id) {
        global $CURMAN;

        if (is_string($id) && !is_numeric($id)) {
        /// $id can be a select string...
            $select = $id;
        } else {
            $select = 'id = ' . $id;
        }

        $record = $CURMAN->db->get_record_select($this->table, $select);
        if (!($record)) {
            return false;
        }

        $fields = get_object_vars($record);

        foreach ($fields as $field => $value) {
            if (isset($this->$field)) {
                $this->$field = $value;
            }
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

        foreach($this->properties as $property => $value) {
            if(isset($data[$property])) {
                $this->$property = $data[$property];
            }
        }

        return true;
    }

    /**
     * safe way to update a property value
     *
     * @param string $field the data record field to be updated
     * @param string $value the value to update the data record field to
     */
     function set_property($field, $value) {
         if(isset($this->properties[$field])) {
             $this->$field = $value;
         }
     }

    /**
     * Data function to update the database record with the object contents.
     *
     * @uses $CURMAN
     * @param $createnew boolean If true, and the record doesn't exist, creates a new one.
     * @return boolean Status of the operation.
     */
    function data_update_record($createnew = false) {
        global $CURMAN;

        if ($this->_dbloaded || !empty($this->id)) {
            $record = new stdClass();

            if (!empty($this->properties)) {
                foreach ($this->properties as $prop => $type) {
                    if (!isset($this->$prop)) {
                        continue;
                    }

                    if ($prop == 'timemodified') {
                        $record->$prop = time();
                    } else {
                        switch ($type) {
                            case 'int':
                                $record->$prop = $CURMAN->db->clean_int($this->$prop);
                                break;

                            case 'float':
                                $record->$prop = $CURMAN->db->clean_num($this->$prop);
                                break;

                            case 'string':
                                $record->$prop = $CURMAN->db->clean_text($this->$prop);
                                break;

                            case 'html':
                                $record->$prop = $CURMAN->db->clean_html($this->$prop);
                                break;
                        }
                    }
                }
            }

            if ($CURMAN->db->update_record($this->table, $record)) {
                return true;
            } else if (!$createnew) {
                return false;
            }
        }

        if ($createnew) {
            return ($this->data_insert_record());
        } else {
            return false;
        }
    }

    /**
     * Delete an object.  Default behaviour removes the table row corresponding to the object.  
     * Override this in a subclass to provide other delete behaviour, such as cascades.
     * 
     * @return boolean Status of the operation.
     */
    public function delete() {
    	return $this->data_delete_record();
    }
    
    public function update() {
        return $this->data_update_record();
    }
    
    public function add() {
        if($this->duplicate_check() === false && $this->has_required_fields()) {
            return $this->data_insert_record();
        } else {
            return false;
        }
    }

    public function set_from_data($data) {
        if(is_object($data)) {
            $data = get_object_vars($data);
        }

        $this->data_load_array($data);
    }

    /**
     * Data function to insert a database record with the object contents.
     *
     * @param $record object If present, uses the contents of it rather than the object.
     * @return boolean Status of the operation.
     * @uses  $CURMAN global.
     */
    public function data_insert_record($record = false) {
        global $CURMAN;

        $timenow = time();

        $scope = $this;

        if (!empty($record)) {
            $scope = $record;
        }

        if (!empty($this->properties)) {
            foreach ($this->properties as $prop => $type) {
                if (!isset($scope->$prop)) {
                    continue;
                }

                if ($prop == 'timemodified' || $prop == 'timecreated') {
                    $record->$prop = $timenow;
                } else {
                    switch ($type) {
                        case 'int':
                            $record->$prop = $CURMAN->db->clean_int($scope->$prop);
                            break;

                        case 'float':
                            $record->$prop = $CURMAN->db->clean_num($scope->$prop);
                            break;

                        case 'string':
                            $record->$prop = $CURMAN->db->clean_text($scope->$prop);
                            break;

                        case 'html':
                            $record->$prop = $CURMAN->db->clean_html($scope->$prop);
                            break;
                    }
                }
            }
        }

        unset($record->id); /// Just in case...

        if ($id = $CURMAN->db->insert_record($this->table, $record)) {
            $this->id = $id;
            return true;
        } else {
            return false;
        }
    }


    /**
     * Data function to insert a database record with the object contents.
     *
     * @uses $CURMAN
     * @param $record object If present, uses the contents of it rather than the object.
     * @return boolean Status of the operation.
     */
    protected function data_delete_record($recordid = 0) {
        global $CURMAN;

        if (!$recordid) {
            $recordid = $this->id;
        }

        return ($CURMAN->db->delete_records($this->table, 'id', $recordid));
    }


    /**
     * Check for a duplicate record when doing an insert.
     *
     * @param object $record The record we want to insert.
     */
    function duplicate_check($record=null) {
    /// Override in sub-class.
        return false;
    }
    
    /**
     * Get the verbose name of an object.
     */
    function get_verbose_name() {
        if(isset($this->verbose_name)) {
    	    return $this->verbose_name;
        }
        else {
            return get_class($this);
        }
    }
    
    function to_string() {
    	return ucwords($this->get_verbose_name()) . " " . $this->id;  // TODO: enforce that every record has to have an id property
    }
    
    function get_dbloaded() {
        return $this->_dbloaded;
    }
}
?>
