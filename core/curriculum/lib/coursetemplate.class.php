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

require_once CURMAN_DIRLOCATION . '/lib/datarecord.class.php';

define ('CTTABLE', 'crlm_coursetemplate');

class coursetemplate extends datarecord {

    function coursetemplate($templatedata = false) {
        parent::datarecord();
        //TODO: change property and db column from 'locaton' to 'courseid'
        $this->set_table(CTTABLE);
        $this->add_property('id', 'int');
        $this->add_property('courseid', 'int');
        $this->add_property('location', 'string');
        $this->add_property('templateclass', 'string');

        if (is_numeric($templatedata)) {
            $this->data_load_record($templatedata);
        } else if (is_array($templatedata)) {
            $this->data_load_array($templatedata);
        } else if (is_object($templatedata)) {
            $this->data_load_array(get_object_vars($templatedata));
        }
    }

    // overriding method, parameter is now the course id
    function data_load_record($id) {
        global $CURMAN;

        if (is_string($id) && !is_numeric($id)) {
        /// $id can be a select string...
            $select = $id;
        } else {
            $select = 'courseid = ' . $id;
        }

        $record = $CURMAN->db->get_record_select($this->table, $select);
        if (empty($record)) {
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
     * Data function to update the database record with the object contents.
     * timecreated/modified not used for this table
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

	public static function delete_for_course($id) {
		global $CURMAN;

		return $CURMAN->db->delete_records(CTTABLE, 'courseid', $id);
	}
}
?>