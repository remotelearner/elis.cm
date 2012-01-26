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

require_once CURMAN_DIRLOCATION . '/lib/course.class.php';
require_once CURMAN_DIRLOCATION . '/lib/curriculum.class.php';
require_once CURMAN_DIRLOCATION . '/lib/curriculumcourse.class.php';
require_once CURMAN_DIRLOCATION . '/lib/curriculumstudent.class.php';
require_once CURMAN_DIRLOCATION . '/lib/user.class.php';
require_once CURMAN_DIRLOCATION . '/lib/student.class.php';
require_once CURMAN_DIRLOCATION . '/lib/attendance.class.php';


class usermanagement extends curriculum {
    var $id;           // INT - The data ID if in the database.
    var $userid;       // INT - The user ID.
    var $user;         // OBJECT - The user database object.
    var $curriculumid; // INT - The curriculum ID.
    var $curriculum;   // OBJECT - The curriculum database object.
    var $timecreated;  // INT - The time created (timestamp).
    var $timemodified; // INT - The time modified (timestamp).

    var $_dbloaded;         // BOOLEAN - True if loaded from database.

    /**
     * Contructor.
     *
     * @param $curriculumstudentdata int/object/array The data id of a data record or data elements to load manually.
     *
     */
    function usermanagement($curriculumstudentdata = false) {
        $this->_dbloaded = false;

        $this->set_table(CURASSTABLE);
        $this->add_property('id', 'int');
        $this->add_property('userid', 'int');
        $this->add_property('curriculumid', 'int');
        $this->add_property('timecreated', 'int');
        $this->add_property('timemodified', 'int');

        if (is_numeric($curriculumstudentdata)) {
            $this->data_load_record($curriculumstudentdata);
        } else if (is_array($curriculumstudentdata)) {
            $this->data_load_array($curriculumstudentdata);
        } else if (is_object($curriculumstudentdata)) {
            $this->data_load_array(get_object_vars($curriculumstudentdata));
        }

        if (!empty($this->userid)) {
            $this->user = new user($this->userid);
        }

        if (!empty($this->curriculumid)) {
            $this->curriculum = new curriculum($this->curriculumid);
        }
    }
}


/// Non-class supporting functions. (These may be able to replaced by a generic container/listing class)


/**
 * Get a list of the available students not already attached to this course.
 *
 * @uses $CURMAN
 * @param string $search A search filter.
 * @return array An array of user records.
 */
function usermanagement_get_students($type = 'student', $sort = 'name', $dir = 'ASC',
                                     $startrec = 0, $perpage = 0, $namesearch = '',
                                     $locsearch = '', $alpha = '') {
    global $CURMAN, $CFG;

    if (empty($CURMAN->db)) {
        return NULL;
    }

    $LIKE     = $CURMAN->db->sql_compare();
    $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');

    $select  = 'SELECT usr.id, usr.idnumber as idnumber, ' .
               'curass.id as curassid, curass.curriculumid as curid, ' .
               $FULLNAME . ' as name ';
    $tables  = 'FROM ' . $CURMAN->db->prefix_table(USRTABLE) . ' usr
                LEFT JOIN ' . $CURMAN->db->prefix_table(CURASSTABLE) . ' curass ON curass.userid = usr.id ';

    /// If limiting returns to specific teams, set that up now.
    if (!empty($CFG->curr_configteams)) {
        $where = 'usr.team IN ('.$CFG->curr_configteams.') ';
    } else {
        $where = '';
    }

    if (!empty($namesearch)) {
        $namesearch = trim($namesearch);
        $where     .= (!empty($where) ? ' AND ' : '') . "($FULLNAME $LIKE '%$namesearch%') ";
    }

    if (!empty($locsearch)) {
        $locsearch = trim($locsearch);
        $where    .=  (!empty($where) ? ' AND ' : '') . "(usr.local $LIKE '%$locsearch%') ";
    }

    if ($alpha) {
        $where .= (!empty($where) ? ' AND ' : '') . "($FULLNAME $LIKE '$alpha%') ";
    }

    switch ($type) {
        case 'student':
            $where .= (!empty($where) ? ' AND ' : '') . 'usr.type = \'Student\' ';
            break;

        case 'instructor':
            $where .= (!empty($where) ? ' AND ' : '') . 'usr.type = \'Instructor\' ';
            break;

        case '':
            $where .= (!empty($where) ? ' AND ' : '') . '(usr.type = \'Student\' OR usr.type = \'Instructor\') ';
            break;
    }

    if (!empty($where)) {
        $where = 'WHERE '.$where.' ';
    }

    if ($sort) {
        if ($sort == 'name') {
            $sort = "ORDER BY lastname {$dir}, firstname {$dir} ";
        } else {
            $sort = "ORDER BY {$sort} {$dir} ";
        }
    }

    if (!empty($perpage)) {
        if ($CURMAN->db->_dbconnection->databaseType == 'postgres7') {
            $limit = 'LIMIT ' . $perpage . ' OFFSET ' . $startrec;
        } else {
            $limit = 'LIMIT '.$startrec.', '.$perpage;
        }
    } else {
        $limit = '';
    }

    $sql = $select.$tables.$where.$sort.$limit;

/// Perform some post-processing on the data received.
    if ($records = $CURMAN->db->get_records_sql($sql)) {
        foreach ($records as $i => $record) {
            $record->currentclassid = 0;
            $record->currentclass   = '';
            $record->lastclassid    = 0;
            $record->lastclass      = '';

            $timenow = time();

            $sql = "SELECT cls.id, crs.name
                    FROM " . $CURMAN->db->prefix_table(CRSTABLE) . " crs
                    LEFT JOIN " . $CURMAN->db->prefix_table(CLSTABLE) . " cls ON cls.courseid = crs.id
                    LEFT JOIN " . $CURMAN->db->prefix_table(STUTABLE) . " stu ON stu.classid = cls.id
                    WHERE stu.userid = '{$record->id}'
                    AND stu.completestatusid = '" . STUSTATUS_NOTCOMPLETE . "'
                    AND stu.enrolmenttime < '$timenow'
                    AND cls.enddate > '$timenow' ";

            if ($crs = $CURMAN->db->get_record_sql($sql)) {
                $record->currentclassid = $crs->id;
                $record->currentclass   = $crs->name;
            }

            $sql = "SELECT cls.id, crs.name, stu.enrolmenttime
                    FROM " . $CURMAN->db->prefix_table(CRSTABLE) . " crs
                    LEFT JOIN " . $CURMAN->db->prefix_table(CLSTABLE) . " cls ON cls.courseid = crs.id
                    LEFT JOIN " . $CURMAN->db->prefix_table(STUTABLE) . " stu ON stu.classid = cls.id
                    WHERE stu.userid = '{$record->id}'
                    AND stu.completestatusid != '" . STUSTATUS_NOTCOMPLETE . "'
                    AND stu.completetime = (
                        SELECT MAX(completetime)
                        FROM " . $CURMAN->db->prefix_table(STUTABLE) . "
                        WHERE userid = '{$record->id}'
                        AND completestatusid != '" . STUSTATUS_NOTCOMPLETE . "'
                    ) ";

            if ($crss = $CURMAN->db->get_records_sql($sql)) {
                if (count($crss) > 1) {
                    $starttime = 0;

                    foreach ($crss as $ci => $crst) {
                        if ($crst->enrolmenttime >= $starttime) {
                            $startime = $crst->enrolmenttime;
                            $crs      = $crss[$ci];
                        }
                    }
                } else {
                    $crs = current($crss);
                }

                $record->lastclassid = $crs->id;
                $record->lastclass   = $crs->name;
            }

            $records[$i] = $record;
        }

    } else {
        $records = array();
    }

    return $records;

//    return $CURMAN->db->get_records_sql($sql);
}


/**
 * Count the number of users
 */
function usermanagement_count_students($type = 'student', $namesearch = '',
                                       $locsearch = '', $alpha = '') {
    global $CFG, $CURMAN;

    $LIKE = $CURMAN->db->sql_compare();

    if ($CURMAN->db->_dbconnection->databaseType == 'postgres7') {
        $FULLNAME = 'usr.firstname || \' \' || COALESCE(usr.mi, \'\') || \' \' || usr.lastname';
    } else {
        $FULLNAME = 'CONCAT(usr.firstname,\' \',IFNULL(usr.mi, \'\'),\' \',usr.lastname)';
    }

    $select  = 'SELECT COUNT(usr.id) ';
    $tables  = 'FROM ' . $CURMAN->db->prefix_table(USRTABLE) . ' usr ';
    $join    = '';
    $on      = '';

    /// If limiting returns to specific teams, set that up now.
    if (!empty($CFG->curr_configteams)) {
        $where = 'usr.team IN ('.$CFG->curr_configteams.') ';
    } else {
        $where = '';
    }

    if (!empty($namesearch)) {
        $namesearch = trim($namesearch);
        $where     .= (!empty($where) ? ' AND ' : '') . "(($FULLNAME $LIKE '%$namesearch%') OR " .
                      "(usr.idnumber $LIKE '%$namesearch%')) ";
    }

    if (!empty($locsearch)) {
        $locsearch = trim($locsearch);
        $where    .=  (!empty($where) ? ' AND ' : '') . "(usr.local $LIKE '%$locsearch%') ";
    }

    if ($alpha) {
        $where .= (!empty($where) ? ' AND ' : '') . "($FULLNAME $LIKE '$alpha%') ";
    }

    switch ($type) {
        case 'student':
            $where .= (!empty($where) ? ' AND ' : '') . 'usr.type = \'Student\' ';
            break;

        case 'instructor':
            $where .= (!empty($where) ? ' AND ' : '') . 'usr.type = \'Instructor\' ';
            break;

        case '':
            $where .= (!empty($where) ? ' AND ' : '') . '(usr.type = \'Student\' OR usr.type = \'Instructor\') ';
            break;
    }

    if (!empty($where)) {
        $where = 'WHERE '.$where.' ';
    }

    $sql = $select.$tables.$join.$on.$where;

    return $CURMAN->db->count_records_sql($sql);
}

function usermanagement_get_users($sort = 'name', $dir = 'ASC',
                                  $startrec = 0, $perpage = 0, $extrasql = '', $contexts = null) {
    global $CURMAN, $CFG;

    if (empty($CURMAN->db)) {
        return NULL;
    }

    $LIKE     = $CURMAN->db->sql_compare();
    $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');
    $select  = 'SELECT usr.id, usr.idnumber as idnumber, usr.country, usr.language, usr.timecreated, '.
               $FULLNAME . ' as name ';
    $tables  = 'FROM ' . $CURMAN->db->prefix_table(USRTABLE) . ' usr ';
    $where   = array();

    if (!empty($extrasql)) {
        $where[] = $extrasql;
    }

    if ($contexts !== null) {
        $where[] = $contexts->sql_filter_for_context_level('usr.id', 'user');
    }

    if (!empty($where)) {
        $where = 'WHERE '.implode(' AND ',$where).' ';
    } else {
        $where = '';
    }

    if ($sort) {
        if ($sort == 'name') {
            $sort = "ORDER BY lastname {$dir}, firstname {$dir} ";
        } else {
            $sort = "ORDER BY {$sort} {$dir} ";
        }
    }

    if (!empty($perpage)) {
        if ($CURMAN->db->_dbconnection->databaseType == 'postgres7') {
            $limit = 'LIMIT ' . $perpage . ' OFFSET ' . $startrec;
        } else {
            $limit = 'LIMIT '.$startrec.', '.$perpage;
        }
    } else {
        $limit = '';
    }

    $sql = $select.$tables.$where.$sort.$limit;

    return $CURMAN->db->get_records_sql($sql);
}

function usermanagement_get_users_recordset($sort = 'name', $dir = 'ASC',
                                            $startrec = 0, $perpage = 0, $extrasql = '', $contexts = null) {
    global $CURMAN, $CFG;

    if (empty($CURMAN->db)) {
        return NULL;
    }

    $LIKE     = $CURMAN->db->sql_compare();
    $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');
    $select  = 'SELECT usr.id, usr.idnumber as idnumber, usr.country, usr.language, usr.timecreated, '.
               $FULLNAME . ' as name ';
    $tables  = 'FROM ' . $CURMAN->db->prefix_table(USRTABLE) . ' usr ';
    $where   = array();

    if (!empty($extrasql)) {
        $where[] = $extrasql;
    }

    if ($contexts !== null) {
        $where[] = $contexts->sql_filter_for_context_level('usr.id', 'user');
    }

    if (!empty($where)) {
        $where = 'WHERE '.implode(' AND ',$where).' ';
    } else {
        $where = '';
    }

    if ($sort) {
        if ($sort == 'name') {
            $sort = "ORDER BY lastname {$dir}, firstname {$dir} ";
        } else {
            $sort = "ORDER BY {$sort} {$dir} ";
        }
    }

    if (!empty($perpage)) {
        if ($CURMAN->db->_dbconnection->databaseType == 'postgres7') {
            $limit = 'LIMIT ' . $perpage . ' OFFSET ' . $startrec;
        } else {
            $limit = 'LIMIT '.$startrec.', '.$perpage;
        }
    } else {
        $limit = '';
    }

    $sql = $select.$tables.$where.$sort.$limit;

    return get_recordset_sql($sql);
}

/**
 * Count the number of users
 */
function usermanagement_count_users($extrasql = '', $contexts = null) {
    global $CFG, $CURMAN;

    $LIKE     = $CURMAN->db->sql_compare();
    $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');

    $select  = 'SELECT COUNT(usr.id) ';
    $tables  = 'FROM ' . $CURMAN->db->prefix_table(USRTABLE) . ' usr ';
    $join    = '';
    $on      = '';
    $where   = array();

    if (!empty($extrasql)) {
        $where[] = $extrasql;
    }

    if ($contexts !== null) {
        $where[] = $contexts->sql_filter_for_context_level('id', 'user');
    }

    if (!empty($where)) {
        $where = 'WHERE '.implode(' AND ',$where).' ';
    } else {
        $where = '';
    }

    $sql = $select.$tables.$join.$on.$where;

    return $CURMAN->db->count_records_sql($sql);
}
?>
