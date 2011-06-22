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
require_once CURMAN_DIRLOCATION . '/lib/cmclass.class.php';
require_once CURMAN_DIRLOCATION . '/lib/user.class.php';
require_once CURMAN_DIRLOCATION . '/lib/student.class.php';


define ('INSTABLE', 'crlm_class_instructor');


class instructor extends datarecord {
/*
    var $id;           // INT - The data id if in the database.
    var $classid;      // INT - The class ID.
    var $cmclass;      // OBJECT - The class object.
    var $userid;       // INT - The user ID.
    var $user;         // OBJECT - The user object.
    var $assigntime;   // INT - The time assigned.
    var $completetime; // INT - The time completed.

    var $_dbloaded;    // BOOLEAN - True if loaded from database.
*/
    // STRING - Styles to use for edit form.
    var $_editstyle = '
.instructoreditform input,
.instructoreditform textarea {
    margin: 0;
    display: block;
}
';


    /**
     * Contructor.
     *
     * @param $instructordata int/object/array The data id of a data record or data elements to load manually.
     *
     */
    function instructor($instructordata=false) {
        parent::datarecord();

        $this->set_table(INSTABLE);
        $this->add_property('id', 'int');
        $this->add_property('classid', 'int');
        $this->add_property('userid', 'int');
        $this->add_property('syllabus', 'string');
        $this->add_property('assigntime', 'int');
        $this->add_property('completetime', 'int');

        if (is_numeric($instructordata)) {
            $this->data_load_record($instructordata);
        } else if (is_array($instructordata)) {
            $this->data_load_array($instructordata);
        } else if (is_object($instructordata)) {
            $this->data_load_array(get_object_vars($instructordata));
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
    	
    	return $CURMAN->db->delete_records(INSTABLE, 'classid', $id);
    }
    
	public static function delete_for_user($id) {
		global $CURMAN;
			
		return $CURMAN->db->delete_records(INSTABLE, 'userid', $id);
	}
	
/////////////////////////////////////////////////////////////////////
//                                                                 //
//  FORM FUNCTIONS:                                                //
//                                                                 //
/////////////////////////////////////////////////////////////////////


    /**
     * Return the HTML to edit a specific instructor.
     * This could be extended to allow for application specific editing, for example
     * a Moodle interface to its formslib.
     *
     * @param $formid string A suffix to put on all 'id' and index for all 'name' attributes.
     *                       This should be unique if being used more than once in a form.
     * @param $extraclass string Any extra class information to add to the output.
     *
     * @return string The form HTML, without the form.
     */
    function edit_form_html($classid, $sort = 'name', $dir = 'ASC', $page = 0,
                            $perpage = 0, $namesearch = '', $alpha = '') {
        global $CFG;

        $output = '';
        ob_start();

        $table = new stdClass;

        if (empty($this->id)) {
            $columns = array(
                'assign'       => 'Assign',
                'idnumber'     => 'ID Number',
                'name'         => 'Name',
                'assigntime'   => 'Assigned Time',
                'completetime' => 'Completion Time'
            );

        } else {
            $columns = array(
                'idnumber'    => 'ID Number',
                'name'         => 'Name',
                'assigntime'   => 'Assigned Time',
                'completetime' => 'Completion Time'
            );
        }

        foreach ($columns as $column => $cdesc) {
            if ($sort != $column) {
                $columnicon = "";
                $columndir = "ASC";
            } else {
                $columndir = $dir == "ASC" ? "DESC":"ASC";
                $columnicon = $dir == "ASC" ? "down":"up";
                $columnicon = " <img src=\"$CFG->pixpath/t/$columnicon.gif\" alt=\"\" />";

            }

            if (($column == 'name') || ($column == 'description')) {
                $$column = "<a href=\"index.php?s=ins&amp;section=curr&amp;id=$classid&amp;" .
                           "action=add&amp;sort=$column&amp;dir=$columndir&amp;search=" .
                           urlencode(stripslashes($namesearch)) . "&amp;alpha=$alpha\">" .
                           $cdesc . "</a>$columnicon";
            } else {
                $$column = $cdesc;
            }

            $table->head[]  = $$column;
            $table->align[] = "left";
            $table->wrap[]  = true;
        }

        if (empty($this->id)) {
            $users     = $this->get_users_avail($sort, $dir, $page * $perpage, $perpage,
                                                $namesearch, $alpha);
            $usercount = $this->count_users_avail($namesearch, $alpha);

            $alphabet = explode(',', get_string('alphabet'));
            $strall   = get_string('all');


        /// Bar of first initials
            echo "<p style=\"text-align:center\">";
            echo get_string('tag_name', 'block_curr_admin')." : ";
            if ($alpha) {
                echo " <a href=\"index.php?s=ins&amp;section=curr&amp;action=add&amp;id=$classid&amp;" .
                     "sort=name&amp;dir=ASC&amp;perpage=$perpage\">$strall</a> ";
            } else {
                echo " <b>$strall</b> ";
            }
            foreach ($alphabet as $letter) {
                if ($letter == $alpha) {
                    echo " <b>$letter</b> ";
                } else {
                    echo " <a href=\"index.php?s=ins&amp;section=curr&amp;action=add&amp;id=$classid&amp;" .
                         "sort=name&amp;dir=ASC&amp;perpage=$perpage&amp;alpha=$letter\">$letter</a> ";
                }
            }
            echo "</p>";

            print_paging_bar($usercount, $page, $perpage,
                             "index.php?s=ins&amp;section=curr&amp;id=$classid&amp;action=add&amp;" .
                             "sort=$sort&amp;dir=$dir&amp;perpage=$perpage&amp;alpha=$alpha&amp;" .
                             "search=".urlencode(stripslashes($namesearch))."&amp;");

            flush();

        } else {
            $user = $this->user;
            $user->name        = cm_fullname($this->user);
            $users[]           = $user;
            $usercount         = 0;
        }

        if (empty($this->id) && !$users) {
            $match = array();
            if ($namesearch !== '') {
               $match[] = s($namesearch);
            }
            if ($alpha) {
               $match[] = 'name'.": $alpha"."___";
            }
            $matchstring = implode(", ", $match);
            echo get_string('no_users_matching', 'block_curr_admin').$matchstring;

            $table = NULL;

        } else {
            $insobj = new instructor();

            $table->width = "100%";
            foreach ($users as $user) {
                $newarr = array();

                foreach ($columns as $column => $cdesc) {
                    switch ($column) {
                        case 'assign':
                            $newarr[] = '<input type="checkbox" name="users[' . $user->id . '][assign]" value="1" />'.
                                        '<input type="hidden" name="users[' . $user->id . '][idnumber]" '.
                                        'value="' . $user->idnumber . '" />';
                            break;

                        case 'name':
                        case 'idnumber':
                        case 'description';
                            $newarr[] = $user->$column;
                            break;

                        case 'assigntime':
                            $newarr[] = cm_print_date_selector('users[' . $user->id . '][startday]',
                                                               'users[' . $user->id . '][startmonth]',
                                                               'users[' . $user->id . '][startyear]',
                                                               $this->assigntime, true);
                            break;

                        case 'completetime':
                            $newarr[] = cm_print_date_selector('users[' . $user->id . '][endday]',
                                                               'users[' . $user->id . '][endmonth]',
                                                               'users[' . $user->id . '][endyear]',
                                                               $this->completetime, true);
                            break;

                        default:
                            $newarr[] = '';
                            break;
                    }
                }

                $table->data[] = $newarr;
            }
        }

        if (empty($this->id)) {
            echo "<table class=\"searchbox\" style=\"margin-left:auto;margin-right:auto\" cellpadding=\"10\"><tr><td>";
            echo "<form action=\"index.php\" method=\"get\"><fieldset>";
            echo '<input type="hidden" name="s" value="ins" />';
            echo '<input type="hidden" name="section" value="curr" />';
            echo '<input type="hidden" name="action" value="add" />';
            echo '<input type="hidden" name="id" value="' . $classid . '" />';
            echo '<input type="hidden" name="sort" value="' . $sort . '" />';
            echo '<input type="hidden" name="dir" value="' . $dir . '" />';
            echo "<input type=\"text\" name=\"search\" value=\"".s($namesearch, true)."\" size=\"20\" />";
            echo "<input type=\"submit\" value=\"" . get_string('search', 'block_curr_admin') . "\" />";
            if ($namesearch) {
                echo "<input type=\"button\" onclick=\"document.location='index.php?s=ins&amp;section=curr&amp;" .
                     "action=add&amp;id=$classid';\" value=\"" . get_string('show_all_users', 'block_curr_admin') . "\" />";
            }
            echo "</fieldset></form>";
            echo "</td></tr></table>";

            echo '<form method="post" action="index.php?s=ins&amp;section=curr&amp;id=' . $classid . '" >'."\n";
            echo '<input type="hidden" name="action" value="savenew" />'."\n";

        } else {
            echo '<form method="post" action="index.php?s=ins&amp;section=curr&amp;id=' . $classid . '" >'."\n";
            echo '<input type="hidden" name="action" value="update" />'."\n";
            echo '<input type="hidden" name="association_id" value="' . $this->id . '" />' . "\n";
            echo '<input type="hidden" name="id" value="' . $this->classid . '" />' . "\n";
            echo '<input type="hidden" name="userid" value="' . $this->userid . '" />' . "\n";
        }

        if (!empty($table)) {
            print_table($table);
            print_paging_bar($usercount, $page, $perpage,
                             "index.php?s=ins&amp;section=curr&amp;id=$classid&amp;action=add&amp;" .
                             "sort=$sort&amp;dir=$dir&amp;perpage=$perpage&amp;alpha=$alpha&amp;" .
                             "search=".urlencode(stripslashes($namesearch))."&amp;");
        }

        if (empty($this->id)) {
            echo '<br /><input type="submit" value="' . get_string('assign_selected', 'block_curr_admin') . '">'."\n";
        } else {
            echo '<br /><input type="submit" value="' . get_string('update_assignment', 'block_curr_admin') . '">'."\n";
        }
        echo '</form>'."\n";

        $output = ob_get_contents();
        ob_end_clean();

        return $output;
    }

    function to_string() {
        return ucwords($this->get_verbose_name()) . ' ' . $this->user->idnumber . ' in ' . $this->cmclass->idnumber;
    }

/////////////////////////////////////////////////////////////////////
//                                                                 //
//  DATA FUNCTIONS:                                                //
//                                                                 //
/////////////////////////////////////////////////////////////////////

    /**
     * Check for a duplicate record when doing an insert.
     *
     * @param object $record The record we want to insert.
     */
    function duplicate_check($record=null) {
        global $CURMAN;

        if(empty($record)) {
            $record = $this;
        }

        /// Check for an existing instructor assignment - it can't already exist.
        if ($CURMAN->db->record_exists(INSTABLE, 'classid', $record->classid, 'userid', $record->userid)) {
            return true;
        }

        return false;
    }

    /**
     * Get a list of the existing instructors for the supplied (or current)
     * class.
     *
     * @uses $CURMAN
     * @paam int $cid A class ID (optional).
     * @return array An array of user records.
     */
    function get_instructors($cid = 0) {
        global $CURMAN;

        if (!$cid) {
            if (empty($this->classid)) {
                return array();
            }

            $cid = $this->classid;
        }

        $uids  = array();

        if ($instructors = $CURMAN->db->get_records(INSTABLE, 'classid', $cid)) {
            foreach ($instructors as $instructor) {
                $uids[] = $instructor->userid;
            }
        }

        if (!empty($uids)) {
            $sql = "SELECT id, idnumber, username, firstname, lastname
                    FROM " . $CURMAN->db->prefix_table(USRTABLE) . "
                    WHERE id IN ( " . implode(', ', $uids) . " )
                    ORDER BY lastname ASC, firstname ASC";

            return $CURMAN->db->get_records_sql($sql);
        }

        return array();
    }


    /**
     * Get a list of the available instructors not already attached to this course.
     *
     * @uses $CURMAN
     * @param string $search A search filter.
     * @return array An array of user records.
     */
    function get_users_avail($sort = 'name', $dir = 'ASC', $startrec = 0,
                             $perpage = 0, $namesearch = '', $alpha = '') {
        global $CFG, $CURMAN;

        if (empty($CURMAN->db)) {
            return NULL;
        }

        $LIKE     = $CURMAN->db->sql_compare();
        $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');

        $select  = 'SELECT usr.id, ' . $FULLNAME . ' as name, usr.idnumber, ' .
                   'ins.classid, ins.userid, ins.assigntime, ins.completetime ';
        $tables  = 'FROM ' . $CURMAN->db->prefix_table(USRTABLE) . ' usr ';
        $join    = 'LEFT JOIN ' . $CURMAN->db->prefix_table(INSTABLE) . ' ins ';
        $on      = 'ON ins.userid = usr.id ';

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

        if ($alpha) {
            $where .= (!empty($where) ? ' AND ' : '') . "($FULLNAME $LIKE '$alpha%') ";
        }
/*
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
*/
        $uids = array();
        $stu = new student;
        if ($users = $stu->get_students()) {
            foreach ($users as $user) {
                $uids[] = $user->id;
            }
        }

        if ($users = $this->get_instructors()) {
            foreach ($users as $user) {
                $uids[] = $user->id;
            }
        }

        if (!empty($uids)) {
            $where .= (!empty($where) ? ' AND ' : '') . 'usr.id NOT IN ( ' .
                      implode(', ', $uids) . ' ) ';
        }

        if (!empty($where)) {
            $where = 'WHERE '.$where.' ';
        }

        if ($sort) {
            $sort = 'ORDER BY '.$sort .' '. $dir.' ';
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

        $sql = $select.$tables.$join.$on.$where.$sort.$limit;

        return $CURMAN->db->get_records_sql($sql);
    }


    function count_users_avail($namesearch = '', $alpha = '') {
        global $CFG, $CURMAN;

        $LIKE     = $CURMAN->db->sql_compare();
        $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');

        $select  = 'SELECT COUNT(usr.id) ';
        $tables  = 'FROM ' . $CURMAN->db->prefix_table(USRTABLE) . ' usr ';
        $join    = 'LEFT JOIN ' . $CURMAN->db->prefix_table(INSTABLE) . ' ins ';
        $on      = 'ON ins.userid = usr.id ';

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

        if ($alpha) {
            $where .= (!empty($where) ? ' AND ' : '') . "($FULLNAME $LIKE '$alpha%') ";
        }
/*
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
*/
        $uids = array();
        $stu  = new student();
        if ($users = $stu->get_students()) {
            foreach ($users as $user) {
                $uids[] = $user->id;
            }
        }

        if ($users = $this->get_instructors()) {
            foreach ($users as $user) {
                $uids[] = $user->id;
            }
        }

        if (!empty($uids)) {
            $where .= (!empty($where) ? ' AND ' : '') . 'usr.id NOT IN ( ' .
                      implode(', ', $uids) . ' ) ';
        }

        if (!empty($where)) {
            $where = 'WHERE '.$where.' ';
        }

        $sql = $select.$tables.$join.$on.$where;

        return $CURMAN->db->count_records_sql($sql);
    }
/*
        global $CURMAN;

        if (empty($CURMAN->db)) {
            return NULL;
        }

        $insids = array();

        if ($instructors = $this->get_instructors($this->classid)) {
            foreach ($instructors as $instructor) {
                $insids[] = $instructor->userid;
            }
        }

        $sql = "SELECT id, idnumber, uname, firstname, lastname
                FROM authuser
                WHERE type = 'Instructor'";

        if (!empty($insids)) {
            $sql .= ' AND id NOT IN ( ' . implode(', ', $insids) . ' )';
        }

        $sql .= ' ORDER BY lastname ASC, firstname ASC';

        return $CURMAN->db->get_records_sql($sql);
    }
*/

    static function user_is_instructor_of_class($userid, $classid) {
        global $CURMAN;
        return $CURMAN->db->record_exists(INSTABLE, 'userid', $userid, 'classid', $classid);
    }

    /**
     * Delete an object.  Default behaviour removes the table row corresponding to the object.  
     * Override this in a subclass to provide other delete behaviour, such as cascades.
     * 
     * @return boolean Status of the operation.
     */
    public function delete() {
        $result = $this->data_delete_record();

        if ($result) {
            //trigger appropriate notification only on success
            events_trigger('crlm_instructor_unassigned', $this);            
        }

        return $result;
    }

    /**
     * Perform all necessary tasks to add an instructor assignment to the system.
     *
     * @return  boolean  true on success, otherwise false
     */
    public function add() {
        if($this->duplicate_check() === false && $this->has_required_fields()) {
            $result = $this->data_insert_record();

            if ($result) {
                //trigger appropriate notification only on success
                events_trigger('crlm_instructor_assigned', $this);
            }

            return $result;
        } else {
            //invalid data
            return false;
        }
    }
}


/// Non-class supporting functions. (These may be able to replaced by a generic container/listing class)


/**
 * Gets a instructor listing with specific sort and other filters.
 *
 * @param int $classid The class ID.
 * @param string $sort Field to sort on.
 * @param string $dir Direction of sort.
 * @param int $startrec Record number to start at.
 * @param int $perpage Number of records per page.
 * @param string $namesearch Search string for instructor name.
 * @param string $alpha Start initial of instructor name filter.
 * @return object array Returned records.
 */

function instructor_get_listing($classid, $sort = 'name', $dir = 'ASC', $startrec = 0,
                                $perpage = 0, $namesearch = '', $alpha='') {
    global $CURMAN;

    $LIKE     = $CURMAN->db->sql_compare();
    $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');

    $select  = 'SELECT ins.* ';
    $select .= ', ' . $FULLNAME . ' as name, usr.idnumber ';
    $tables  = 'FROM ' . $CURMAN->db->prefix_table(INSTABLE) . ' ins ';
    $join    = 'LEFT JOIN ' . $CURMAN->db->prefix_table(USRTABLE) . ' usr ';
    $on      = 'ON ins.userid = usr.id ';
    $where   = 'ins.classid = \'' . $classid . '\'';

    if (!empty($namesearch)) {
        $namesearch = trim($namesearch);
        $where .= (!empty($where) ? ' AND ' : '') . "($FULLNAME $LIKE '%$namesearch%') OR " .
                          "(usr.idnumber $LIKE '%$namesearch%') ";
    }

    if ($alpha) {
        $where .= (!empty($where) ? ' AND ' : '') . "($FULLNAME $LIKE '$alpha%') ";
    }

    if (!empty($where)) {
        $where = 'WHERE '.$where.' ';
    }

    if ($sort) {
        $sort = 'ORDER BY '.$sort .' '. $dir.' ';
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

    $sql = $select.$tables.$join.$on.$where.$sort.$limit;
//print_object($sql);
    return $CURMAN->db->get_records_sql($sql);
}


/**
 * Count the number of instructors for this class.
 *
 * @uses $CURMAN
 * @param int $classid The class ID.
 */
function instructor_count_records($classid, $namesearch = '', $alpha='') {
    global $CURMAN;

    $LIKE     = $CURMAN->db->sql_compare();
    $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');

    $select  = 'SELECT COUNT(ins.id) ';
    $tables  = 'FROM ' . $CURMAN->db->prefix_table(INSTABLE) . ' ins ';
    $join    = 'LEFT JOIN ' . $CURMAN->db->prefix_table(USRTABLE) . ' usr ';
    $on      = 'ON ins.userid = usr.id ';
    $where   = 'ins.classid = \'' . $classid . '\'';

    if (!empty($namesearch)) {
        $namesearch = trim($namesearch);
        $where .= (!empty($where) ? ' AND ' : '') . "($FULLNAME $LIKE '%$namesearch%') OR " .
                          "(usr.idnumber $LIKE '%$namesearch%')) ";
    }

    if ($alpha) {
        $where .= (!empty($where) ? ' AND ' : '') . "($FULLNAME $LIKE '$alpha%') ";
    }

    if (!empty($where)) {
        $where = 'WHERE '.$where.' ';
    }

    $sql = $select . $tables . $join . $on . $where;
    return $CURMAN->db->count_records_sql($sql);
}

?>
