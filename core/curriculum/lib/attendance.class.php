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


define ('ATNTABLE', 'crlm_class_attendance');


class attendance extends datarecord {
	/*
	 var $id;        // INT - The data ID if in the database.
	 var $classid;   // INT - The class ID.
	 var $userid;    // INT - The user ID.
	 var $timestart; // INT - Timestamp for the starting time.
	 var $timeend;   // INT - Timestamp for the ending time.
	 var $adduserid; // INT - ?
	 var $note;      // STRING - Any additional info.

	 var $_dbloaded; // BOOLEAN - True if loaded from database.
	 */

	/**
	 * Contructor.
	 *
	 * @param $attendancedata int/object/array The data id of a data record or data elements to load manually.
	 *
	 */
	function attendance($attendancedata = false) {
		parent::datarecord();

		$this->set_table(ATNTABLE);
		$this->add_property('id', 'int');
		$this->add_property('classid', 'int');
		$this->add_property('userid', 'int');
		$this->add_property('timestart', 'int');
		$this->add_property('timeend', 'int');
		$this->add_property('adduserid', 'int');
		$this->add_property('note', 'string');

		if (is_numeric($attendancedata)) {
			$this->data_load_record($attendancedata);
		} else if (is_array($attendancedata)) {
			$this->data_load_array($attendancedata);
		} else if (is_object($attendancedata)) {
			$this->data_load_array(get_object_vars($attendancedata));
		}

		if (!empty($this->classid)) {
			$this->cmclass = new cmclass($this->classid);
		}

		if (!empty($this->userid)) {
			$this->user = new user($this->userid);
		}
	}

	public static function delete_for_class($id) {
		global $CURMAN;
			
		return $CURMAN->db->delete_records(ATNTABLE, 'classid', $id);
	}

	public static function delete_for_user($id) {
		global $CURMAN;
			
		return $CURMAN->db->delete_records(ATNTABLE, 'userid', $id);
	}
}


/// Non-class supporting functions. (These may be able to replaced by a generic container/listing class)


/**
 * Get an existing attendance record.
 *
 * @param int $classid The class ID.
 * @param int $userid  The user ID.
 * @return object|bool The database record or false.
 */
function cm_get_attendance($classid, $userid) {
	global $CURMAN;

	if (!$atn = $CURMAN->db->get_record(ATNTABLE, 'classid', $classid, 'userid', $userid)) {
		return false;
	}

	return $atn;
}

?>