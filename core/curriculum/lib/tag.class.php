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

require_once CURMAN_DIRLOCATION . '/lib/taginstance.class.php';
require_once CURMAN_DIRLOCATION . '/lib/datarecord.class.php';

define ('TAGTABLE', 'crlm_tag');


class tag extends datarecord {
/*
    var $id;            // INT - The data id if in the database.
    var $name;          // STRING - Textual name of the tag.
    var $description;   // STRING - A description of the tag.
    var $timecreated;   // INT - Timestamp.
    var $timemodified;  // INT - Timestamp.
*/

    /**
     * Contructor.
     *
     * @param $tagdata int/object/array The data id of a data record or data elements to load manually.
     *
     */
    function tag($tagdata=false) {
        parent::datarecord();

        $this->set_table(TAGTABLE);
        $this->add_property('id', 'int');
        $this->add_property('name', 'string');
        $this->add_property('description', 'string');
        $this->add_property('timecreated', 'int');
        $this->add_property('timemodified', 'int');

        if (is_numeric($tagdata)) {
            $this->data_load_record($tagdata);
        } else if (is_array($tagdata)) {
            $this->data_load_array($tagdata);
        } else if (is_object($tagdata)) {
            $this->data_load_array(get_object_vars($tagdata));
        }
    }

    public function delete() {
    	$result = taginstance::delete_for_tag($this->id);

    	return $result && parent::delete();
    }

    function to_string() {
        return $this->name;
    }
}


/// Non-class supporting functions. (These may be able to replaced by a generic container/listing class)


/**
 * Gets a tag listing with specific sort and other filters.
 *
 * @param string $sort Field to sort on.
 * @param string $dir Direction of sort.
 * @param int $startrec Record number to start at.
 * @param int $perpage Number of records per page.
 * @param string $namesearch Search string for tag name.
 * @param string $descsearch Search string for tag description.
 * @param string $alpha Start initial of tag name filter.
 * @return object array Returned records.
 */

function tag_get_listing($sort='name', $dir='ASC', $startrec=0, $perpage=0, $namesearch='',
                         $alpha='') {
    global $CURMAN;

    $LIKE = $CURMAN->db->sql_compare();
    $select = '';
    if (!empty($namesearch)) {
        $namesearch = trim($namesearch);
        $select .= (!empty($select) ? ' AND ' : '') . "(name $LIKE '%$namesearch%') ";
    }

    if ($alpha) {
        $select .= (!empty($select) ? ' AND ' : '') . "(name $LIKE '$alpha%') ";
    }

    if ($sort) {
        $sort = $sort .' '. $dir;
    }

    $fields = 'id, name, description, timecreated, timemodified';

    return $CURMAN->db->get_records_select(TAGTABLE, $select, $sort, $fields, $startrec, $perpage);
}

function tag_count_records($namesearch = '', $alpha = '') {
    global $CURMAN;

    $select = '';

    $LIKE = $CURMAN->db->sql_compare();

    if (!empty($namesearch)) {
        $namesearch = trim($namesearch);
        $select .= (!empty($select) ? ' AND ' : '') . "(name $LIKE '%$namesearch%') ";
    }

    if ($alpha) {
        $select .= (!empty($select) ? ' AND ' : '') . "(name $LIKE '$alpha%') ";
    }

    return $CURMAN->db->count_records_select(TAGTABLE, $select);
}

?>
