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

require_once(dirname(__FILE__).'/../../../../config.php');
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once elis::lib('data/data_object.class.php');
require_once elis::lib('table.class.php');
require_once elispm::lib('lib.php');
require_once elispm::lib('deprecatedlib.php');
require_once elispm::lib('data/pmclass.class.php');
require_once elispm::lib('data/user.class.php');
require_once elispm::lib('data/student.class.php');

define ('INSTABLE', 'crlm_class_instructor');

class instructor extends elis_data_object {
    const TABLE = INSTABLE;
    const LANG_FILE = 'elis_program';

    static $associations = array(
        'users'   => array('class' => 'user',
                           'idfield' => 'userid'),
        'pmclass' => array('class' => 'pmclass',
                           'idfield' => 'classid')
    );

    static $validation_rules = array(array('validation_helper', 'not_empty_userid'),
                                     array('validation_helper', 'not_empty_classid'),
                                     'validate_associated_user_exists',
                                     'validate_associated_class_exists',
                                     array('validation_helper', 'is_unique_userid_classid'));

    /**
     * Validates that the associated user record exists
     */
    public function validate_associated_user_exists() {
        validate_associated_record_exists($this, 'users');
    }

    /**
     * Validates that the associated pmclass record exists
     */
    public function validate_associated_class_exists() {
        validate_associated_record_exists($this, 'pmclass');
    }
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

    private $form_url = null;  //moodle_url object

    protected $_dbfield_classid;
    protected $_dbfield_userid;
    protected $_dbfield_assigntime;
    protected $_dbfield_completetime;

    //var $pmclass;           // OBJECT - The class object

    // STRING - Styles to use for edit form.
    var $_editstyle = '
.instructoreditform input,
.instructoreditform textarea {
    margin: 0;
    display: block;
}
';

    /**
     * Construct an ELIS instructor data object.
     * @param mixed $src record source.  It can be
     * - false: an empty object is created
     * - an integer: loads the record that has record id equal to $src
     * - an object: creates an object with field data taken from the members
     *   of $src
     * - an array: creates an object with the field data taken from the
     *   elements of $src
     * @param mixed $field_map mapping for field names from $src.  If it is a
     * string, then it will be treated as a prefix for field names.  If it is
     * an array, then it is a mapping of destination field names to source
     * field names.
     * @param array $associations pre-fetched associated objects (to avoid
     * needing to re-fetch)
     * @param boolean $from_db whether or not the record source object/array
     * comes from the database
     * @param array $extradatafields extra data from the $src object/array
     * associated with the record that should be kept in the data object (such
     * as counts of related records)
     * @param moodle_database $database database object to use (null for the
     * default database)
     */
    public function __construct($src=false, $field_map=null, array $associations=array(),
                                $from_db=false, array $extradatafields=array(),
                                moodle_database $database=null) {
        $extradatafields = array_merge($extradatafields, array('roleshortname'));
        parent::__construct($src, $field_map, $associations, $from_db, $extradatafields, $database);
    }

    /**
     * Perform parent add and trigger assigned event.
     */
    public function save() {
        parent::save();
        events_trigger('crlm_instructor_assigned', $this);
    }

    /**
     * Perform parent delete and trigger unassigned event.
    */
    public function delete() {
        parent::delete();
        events_trigger('crlm_instructor_unassigned', $this);
    }

    public static function delete_for_class($id) {
        global $DB;
        return $DB->delete_records(instructor::TABLE, array('classid' => $id));
    }

    public static function delete_for_user($id) {
        global $DB;
        return $DB->delete_records(instructor::TABLE, array('userid' => $id));
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
     * @uses $CFG
     * @uses $OUTPUT
     * @return string The form HTML, without the form.
     */
    function edit_form_html($classid, $sort = 'name', $dir = 'ASC', $page = 0,
                            $perpage = 30, $namesearch = '', $alpha = '') {
        global $CFG, $OUTPUT, $SESSION, $PAGE; // ^^^ set new non-zero default for $perpage
        $action = optional_param('action', '', PARAM_ALPHA);
        $this->classid = $classid;
        $output = '';
        ob_start();

        if (empty($this->id)) {
            $columns = array(
                'assign'       => array('header' => get_string('assign', self::LANG_FILE),
                                        'display_function' => 'htmltab_display_function',
                                        'sortable' => false),
                'idnumber'     => array('header' => get_string('class_idnumber', self::LANG_FILE),
                                        'display_function' => 'htmltab_display_function'),
                'name'         => array('header' => get_string('tag_name', self::LANG_FILE),
                                        'display_function' => 'htmltab_display_function'),
                'assigntime'   => array('header' => get_string('assigntime', self::LANG_FILE),
                                        'display_function' => 'htmltab_display_function',
                                        'sortable' => false),
                'completetime' => array('header' => get_string('completion_time', self::LANG_FILE),
                                        'display_function' => 'htmltab_display_function',
                                        'sortable' => false)
            );

        } else {
            $columns = array(
                'idnumber'     => array('header' => get_string('class_idnumber', self::LANG_FILE),
                                        'display_function' => 'htmltab_display_function'),
                'name'         => array('header' => get_string('tag_name', self::LANG_FILE),
                                        'display_function' => 'htmltab_display_function'),
                'assigntime'   => array('header' => get_string('assigntime', self::LANG_FILE),
                                        'display_function' => 'htmltab_display_function',
                                        'sortable' => false),
                'completetime' => array('header' => get_string('completion_time', self::LANG_FILE),
                                        'display_function' => 'htmltab_display_function',
                                        'sortable' => false)
              /* ****
                , 'buttons'      => array('header' => '',
                                        'display_function' => 'htmltab_display_function',
                                        'sortable' => false)
              **** */
            );
        }

        if ($dir !== 'DESC') {
            $dir = 'ASC';
        }
        if (isset($columns[$sort])) {
            $columns[$sort]['sortable'] = $dir;
        } else {
            $sort = 'name';
            $columns[$sort]['sortable'] = $dir;
        }

        $newarr = array();
        $users = array();
        if (empty($this->id)) {
            $users = $this->get_users_avail($sort, $dir, $page * $perpage, $perpage, $namesearch, $alpha);
            $usercount = $this->count_users_avail($namesearch, $alpha);

            pmalphabox(new moodle_url('/elis/program/index.php',
                               array('s' => 'ins', 'section' => 'curr',
                                     'action' => 'add', 'id' => $classid,
                                     'sort' => $sort, 'dir' => $dir,
                                     'perpage' => $perpage)),
                       'alpha', get_string('tag_name', self::LANG_FILE) .':');

            $pagingbar = new paging_bar($usercount, $page, $perpage,
                             "index.php?s=ins&amp;section=curr&amp;id=$classid&amp;action=add&amp;" .
                             "sort=$sort&amp;dir=$dir&amp;perpage=$perpage&amp;alpha=$alpha&amp;" .
                             "search=".urlencode($namesearch)); // TBD: .'&amp;'
            echo $OUTPUT->render($pagingbar);
            flush();
        } else {
            //error_log("instructor.class.php::edit_form_html(); userid = {$this->userid}");
            if (($tmpuser = $this->_db->get_record(user::TABLE, array('id' => $this->userid)))) {
                // TBD - above was: $tmpuser = new user($this->userid)
                //print_object($tmpuser);
                $user = new stdClass;
                $user->id = $this->userid;
                foreach ($tmpuser as $key => $val) {
                    $user->{$key} = $val;
                }
                $user->name = fullname($user);
                $users[]    = $user;
                $usercount  = 0; // TBD: 1 ???
            }
        }
        $has_users = ((is_array($users) && !empty($users)) || ($users instanceof Iterator && $users->valid() === true)) ? true : false;
        if (empty($this->id) && $has_users === false) {
            $table = NULL;
        } else {
            $insobj = new instructor();
            //$table->width = "100%";
            foreach ($users as $user) {
                $tabobj = new stdClass;

                $assigntime = $this->assigntime;
                $completetime = $this->completetime;

                $selection = json_decode(retrieve_session_selection($user->id, 'add'));
                if ($selection) {
                    $assigntime = pm_timestamp(0, 0, 0, $selection->enrolment_date->month, $selection->enrolment_date->day, $selection->enrolment_date->year);
                    $completetime = pm_timestamp(0, 0, 0, $selection->completion_date->month, $selection->completion_date->day, $selection->completion_date->year);
                }
              /* **** debug code
                ob_start();
                var_dump($user);
                $tmp = ob_get_contents();
                ob_end_clean();
                error_log("instructor.class.php::edit_form_html() user = $tmp");
              **** */
                foreach ($columns as $column => $cdesc) {
                    switch ($column) {
                        case 'assign':
                            $tabobj->{$column} = '<input type="checkbox" id="checkbox'. $user->id .'" onClick="select_item(' . $user->id .')" name="users[' . $user->id . '][assign]" value="1" '.($selection?'checked="checked"':''). '/>'.
                                        '<input type="hidden" name="users[' . $user->id . '][idnumber]" '.
                                        'value="' . $user->idnumber . '" />';
                            break;

                        case 'name':
                        case 'idnumber':
                        case 'description';
                            $tabobj->{$column} = $user->{$column};
                            break;

                        case 'assigntime':
                            $tabobj->{$column} = cm_print_date_selector('users[' . $user->id . '][startday]',
                                                     'users[' . $user->id . '][startmonth]',
                                                     'users[' . $user->id . '][startyear]',
                                                     $assigntime, true);
                            break;

                        case 'completetime':
                            $tabobj->{$column} = cm_print_date_selector('users[' . $user->id . '][endday]',
                                                     'users[' . $user->id . '][endmonth]',
                                                     'users[' . $user->id . '][endyear]',
                                                     $completetime, true);
                            break;

                        default:
                            $tabobj->{$column} = '';
                            break;
                    }
                }
                $newarr[] = $tabobj;
                //$table->data[] = $newarr;
            }
            $table = new display_table($newarr, $columns, get_pm_url(), 'sort', 'dir', array('id' => 'selectiontbl'));
        }
        unset($users);
        print_checkbox_selection($classid, 'ins', 'add');

        if (empty($this->id)) {
            pmsearchbox(null, 'search', 'get', get_string('show_all_users', self::LANG_FILE));

            echo '<form method="post" action="index.php?s=ins&amp;section=curr&amp;id=' . $classid . '" >'."\n";
            echo '<input type="hidden" name="action" value="savenew" />'."\n";

        } else {
            echo '<form method="post" action="index.php?s=ins&amp;section=curr&amp;id=' . $classid . '" >'."\n";
            echo '<input type="hidden" name="action" value="update" />'."\n";
            echo '<input type="hidden" name="association_id" value="' . $this->id . '" />' . "\n";
            echo '<input type="hidden" name="id" value="' . $this->classid . '" />' . "\n";
            echo '<input type="hidden" name="userid" value="' . $this->userid . '" />' . "\n";
        }

        if (!empty($table) && !empty($newarr)) {
            if ($action == 'add') {
                $PAGE->requires->js('/elis/program/js/classform.js');
                echo '<input type="button" onclick="checkbox_select(true,\'[assign]\')" value="'.get_string('selectall').'" /> ';
                echo '<input type="button" onclick="checkbox_select(false,\'[assign]\')" value="'.get_string('deselectall').'" /> ';
            }

            echo $table->get_html();
            $pagingbar = new paging_bar($usercount, $page, $perpage,
                             "index.php?s=ins&amp;section=curr&amp;id=$classid&amp;action=add&amp;" .
                             "sort=$sort&amp;dir=$dir&amp;perpage=$perpage&amp;alpha=$alpha&amp;" .
                             "search=".urlencode($namesearch)); // TBD: .'&amp;'
            echo $OUTPUT->render($pagingbar);
        }

        if (empty($this->id)) {
            if ($has_users === false) {
                pmshowmatches($alpha, $namesearch);
            }
            echo '<br /><input type="submit" value="' . get_string('assign_selected', self::LANG_FILE) . '">'."\n";
        } else {
            echo '<br /><input type="submit" value="' . get_string('update_assignment', self::LANG_FILE) . '">'."\n";
        }
        echo '</form>'."\n";

        $output = ob_get_contents();
        ob_end_clean();

        return $output;
    }

/////////////////////////////////////////////////////////////////////
//                                                                 //
//  DATA FUNCTIONS:                                                //
//                                                                 //
/////////////////////////////////////////////////////////////////////

    /**
     * Get a list of the existing instructors for the supplied (or current)
     * class.
     * NOTE: called statically in /elis/program/coursecatalogpage.class.php
     *       with argument $cid set!
     *
     * @param int $cid A class ID (optional).
     * @uses $DB
     * @return recordset An array of user records.
     */
    function get_instructors($cid = 0) {
        global $DB;
        if (!$cid) {
            if (empty($this->classid)) {
                return array();
            }

            $cid = $this->classid;
        }

        $uids  = array();

        $instructors = $DB->get_recordset(instructor::TABLE, array('classid' => $cid));
        foreach ($instructors as $instructor) {
            $uids[] = $instructor->userid;
        }
        unset($instructors);

        if (!empty($uids)) {
            $sql = 'SELECT id, idnumber, username, firstname, lastname
                    FROM {'. user::TABLE . '}
                    WHERE id IN ( ' . implode(', ', $uids) . ' )
                    ORDER BY lastname ASC, firstname ASC';

            return $DB->get_recordset_sql($sql);
        }
        return array();
    }

    /**
     * Get a list of the available instructors not already attached to this course.
     *
     * @param string $search A search filter.
     * @return recordset An array of user records.
     */
    function get_users_avail($sort = 'name', $dir = 'ASC', $startrec = 0,
                             $perpage = 0, $namesearch = '', $alpha = '') {
        global $CFG;
        if (empty($this->_db)) {
            return NULL;
        }

        $params = array();
        $FULLNAME = $this->_db->sql_concat('usr.firstname', "' '", 'usr.lastname');
        $FULLNAME_LIKE = $this->_db->sql_like($FULLNAME, ':name_like', FALSE);
        $LASTNAME_STARTSWITH = $this->_db->sql_like('usr.lastname', ':lastname_startswith', FALSE);

        $select  = 'SELECT usr.id, ' . $FULLNAME . ' as name, usr.idnumber, ' .
                   'ins.classid, ins.userid, ins.assigntime, ins.completetime ';
        $tables  = 'FROM {'. user::TABLE .'} usr ';
        $join    = 'LEFT JOIN {' . instructor::TABLE .'} ins ';
        $on      = 'ON ins.userid = usr.id ';

        /// If limiting returns to specific teams, set that up now.
        if (!empty($CFG->curr_configteams)) {
            $where = 'usr.team IN ('.$CFG->curr_configteams.') ';
        } else {
            $where = '';
        }

        if (!empty($namesearch)) {
            $namesearch = trim($namesearch);
            $where     .= (!empty($where) ? ' AND ' : ' ')."{$FULLNAME_LIKE} ";
            $params['name_like'] = "%{$namesearch}%";
        }

        if ($alpha) {
            $where .= (!empty($where) ? ' AND ' : ' ')."{$LASTNAME_STARTSWITH}  ";
            $params['lastname_startswith'] = "{$alpha}%";
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
        $stu = new student();
        if ($users = $stu->get_students($this->classid)) {
            foreach ($users as $user) {
                $uids[] = $user->id;
            }
        }
        unset($users);

        if ($users = $this->get_instructors()) {
            foreach ($users as $user) {
                $uids[] = $user->id;
            }
        }
        unset($users);

        if (!empty($uids)) {
            $where .= (!empty($where) ? ' AND ' : '') . 'usr.id NOT IN ( ' .
                      implode(', ', $uids) . ' ) ';
        }

        //if appropriate, limit selection to users belonging to clusters that
        //the current user can manage instructor assignments for

        // TODO: Ugly, this needs to be overhauled
        $cpage = new pmclasspage();

        if (!$cpage->_has_capability('elis/program:assign_class_instructor', $this->classid)) {
            //perform SQL filtering for the more "conditional" capability

            $allowed_clusters = instructor::get_allowed_clusters($this->classid);

            if (empty($allowed_clusters)) {
                $where .= (!empty($where) ? ' AND ' : '') . '0=1 ';
            } else {
                $cluster_filter = implode(',', $allowed_clusters);

                $where .= (!empty($where) ? ' AND ' : '') . 'usr.id IN (
                             SELECT userid FROM {'. clusterassignment::TABLE ."}
                             WHERE clusterid IN ({$cluster_filter}))";
            }
        }

        if (!empty($where)) {
            $where = 'WHERE '.$where.' ';
        }

        if ($sort) {
            if ($sort === 'name') {
                $sort = $FULLNAME;
            }
            $sort = 'ORDER BY '.$sort .' '. $dir.' ';
        }

        $sql = $select.$tables.$join.$on.$where.$sort;
        //error_log("instructor.class::get_users_avail(); sql = {$sql}");
        return $this->_db->get_recordset_sql($sql, $params, $startrec, $perpage);
    }


    function count_users_avail($namesearch = '', $alpha = '') {
        global $CFG;
        $params = array();
        $FULLNAME = $this->_db->sql_concat('usr.firstname', "' '", 'usr.lastname');
        $FULLNAME_LIKE = $this->_db->sql_like($FULLNAME, ':name_like', FALSE);
        $LASTNAME_STARTSWITH = $this->_db->sql_like('usr.lastname', ':lastname_startswith', FALSE);

        $select  = 'SELECT COUNT(usr.id) ';
        $tables  = 'FROM {'. user::TABLE .'} usr ';
        $join    = 'LEFT JOIN {'. instructor::TABLE .'} ins ';
        $on      = 'ON ins.userid = usr.id ';

        /// If limiting returns to specific teams, set that up now.
        if (!empty($CFG->curr_configteams)) {
            $where = 'usr.team IN ('.$CFG->curr_configteams.') ';
        } else {
            $where = '';
        }

        if (!empty($namesearch)) {
            $namesearch = trim($namesearch);
            $where     .= (!empty($where) ? ' AND ' : ' ')."{$FULLNAME_LIKE} ";
            $params['name_like'] = "%{$namesearch}%";
        }

        if ($alpha) {
            $where .= (!empty($where) ? ' AND ' : ' ')."{$LASTNAME_STARTSWITH}  ";
            $params['lastname_startswith'] = "{$alpha}%";
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
        if ($users = $stu->get_students($this->classid)) {
            foreach ($users as $user) {
                $uids[] = $user->id;
            }
        }
        unset($users);

        if ($users = $this->get_instructors()) {
            foreach ($users as $user) {
                $uids[] = $user->id;
            }
        }
        unset($users);

        if (!empty($uids)) {
            $where .= (!empty($where) ? ' AND ' : '') . 'usr.id NOT IN ( ' .
                      implode(', ', $uids) . ' ) ';
        }

        //if appropriate, limit selection to users belonging to clusters that
        //the current user can manage instructor assignments for

        // TODO: Ugly, this needs to be overhauled
        $cpage = new pmclasspage();

        if (!$cpage->_has_capability('elis/program:assign_class_instructor', $this->classid)) {
            //perform SQL filtering for the more "conditional" capability

            $allowed_clusters = instructor::get_allowed_clusters($this->classid);

            if (empty($allowed_clusters)) {
                $where .= (!empty($where) ? ' AND ' : '') . '0=1 ';
            } else {
                $cluster_filter = implode(',', $allowed_clusters);

                $where .= (!empty($where) ? ' AND ' : '') . 'usr.id IN (
                             SELECT userid FROM {'. clusterassignment::TABLE ."}
                             WHERE clusterid IN ({$cluster_filter}))";
            }
        }

        if (!empty($where)) {
            $where = 'WHERE '.$where.' ';
        }

        $sql = $select.$tables.$join.$on.$where;
        return $this->_db->count_records_sql($sql, $params);
    }

    static function user_is_instructor_of_class($userid, $classid) {
        global $DB;
        return $DB->record_exists(instructor::TABLE,
                   array('userid' => $userid, 'classid' => $classid));
    }

    public function set_from_data($data) {
        $this->_load_data_from_record($data, true);
    }

    /**
     * Determines whether the current user is allowed to create, edit, and delete associations
     * between a user (instructor) and a class
     *
     * @param    int      $userid    The id of the user being associated to the class
     * @param    int      $classid   The id of the class we are associating the user to
     * @uses     $DB
     * @uses     $USER;
     * @return   boolean             True if the current user has the required permissions, otherwise false
     */
    public static function can_manage_assoc($userid, $classid) {
        global $DB, $USER;

        // TODO: Ugly, this needs to be overhauled
        $cpage = new pmclasspage();

        if (!instructorpage::can_enrol_into_class($classid)) {
            //the users who satisfty this condition are a superset of those who can manage associations
            return false;
        } else if ($cpage->_has_capability('elis/program:assign_class_instructor', $classid)) {
            //current user has the direct capability
            return true;
        }

        //get the context for the "indirect" capability
        $context = pm_context_set::for_user_with_capability('cluster', 'elis/program:assign_userset_user_class_instructor', $USER->id);

        $allowed_clusters = array();
        $allowed_clusters = instructor::get_allowed_clusters($classid);

        //query to get users associated to at least one enabling cluster
        $cluster_select = '';
        if(empty($allowed_clusters)) {
            $cluster_select = '0=1';
        } else {
            $cluster_select = 'clusterid IN (' . implode(',', $allowed_clusters) . ')';
        }
        $select = "userid = ? AND {$cluster_select}";

        //user just needs to be in one of the possible clusters
        if($DB->record_exists_select(clusterassignment::TABLE, $select, array($userid))) {
            return true;
        }

        return false;
    }

    /**
     * Returns an array of cluster ids that are associated to the supplied class through tracks and
     * the current user has access to enrol users into
     *
     * @param   int        $clsid  The class whose association ids we care about
     * @return  int array          The array of accessible cluster ids
     */
    public static function get_allowed_clusters($clsid) {
        global $USER;

        $context = pm_context_set::for_user_with_capability('cluster', 'elis/program:assign_userset_user_class_instructor', $USER->id);

        $allowed_clusters = array();

        // TODO: Ugly, this needs to be overhauled
        $cpage = new pmclasspage();

        if ($cpage->_has_capability('elis/program:assign_userset_user_class_instructor', $clsid)) {
            require_once elispm::lib('data/clusterassignment.class.php');
            $cmuserid = pm_get_crlmuserid($USER->id);
            $userclusters = clusterassignment::find(new field_filter('userid', $cmuserid));
            foreach ($userclusters as $usercluster) {
                $allowed_clusters[] = $usercluster->clusterid;
            }
        }

        //we first need to go through tracks to get to clusters
        $track_listing = new trackassignment(array('classid' => $clsid));
        $tracks = $track_listing->get_assigned_tracks();

        //iterate over the track ides, which are the keys of the array
        if(!empty($tracks)) {
            foreach(array_keys($tracks) as $track) {
                //get the clusters and check the context against them
                $clusters = clustertrack::get_clusters($track);
                $allowed_track_clusters = $context->get_allowed_instances($clusters, 'cluster', 'clusterid');

                //append all clusters that are allowed by the available clusters contexts
                foreach($allowed_track_clusters as $allowed_track_cluster) {
                    $allowed_clusters[] = $allowed_track_cluster;
                }
            }
        }

        return $allowed_clusters;
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
 * @uses $DB
 * @return recordset Returned records.
 */

function instructor_get_listing($classid, $sort = 'name', $dir = 'ASC', $startrec = 0,
                                $perpage = 0, $namesearch = '', $alpha='') {
    global $DB;
    $params = array();
    $FULLNAME = $DB->sql_concat('usr.firstname', "' '", 'usr.lastname');
    $FULLNAME_LIKE = $DB->sql_like($FULLNAME, ':name_like', FALSE);
    $IDNUMBER_LIKE = $DB->sql_like('usr.idnumber', ':id_like', FALSE);
    $LASTNAME_STARTSWITH = $DB->sql_like('usr.lastname', ':lastname_startswith', FALSE);

    $select  = 'SELECT ins.* ';
    $select .= ', ' . $FULLNAME . ' as name, usr.idnumber ';
    $tables  = 'FROM {'. instructor::TABLE .'} ins ';
    $join    = 'LEFT JOIN {'. user::TABLE .'} usr ';
    $on      = 'ON ins.userid = usr.id ';
    $where   = 'ins.classid = :clsid ';
    $params['clsid'] = $classid;

    if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
        $where .= ' AND usr.inactive = 0 ';
    }

    if (!empty($namesearch)) {
        $namesearch = trim($namesearch);
        $where .= (!empty($where) ? ' AND ' : ' ') .'(('. $FULLNAME_LIKE .') OR ('.
                          $IDNUMBER_LIKE .')) ';
        $params['name_like'] = "%{$namesearch}%";
        $params['id_like']   = "%{$namesearch}%";
    }

    if ($alpha) {
        $where .= (!empty($where) ? ' AND ' : ' ') .'('. $LASTNAME_STARTSWITH .') ';
        $params['lastname_startswith'] = "{$alpha}%";
    }

    if (!empty($where)) {
        $where = 'WHERE '.$where.' ';
    }

    if ($sort) {
        if ($sort === 'name') {
            $sort = $FULLNAME;
        }
        $sort = 'ORDER BY '.$sort .' '. $dir.' ';
    }

    $sql = $select.$tables.$join.$on.$where.$sort;

    return $DB->get_recordset_sql($sql, $params, $startrec, $perpage);
}


/**
 * Count the number of instructors for this class.
 *
 * @uses $DB
 * @param int $classid The class ID.
 */
function instructor_count_records($classid, $namesearch = '', $alpha='') {
    global $DB;
    $params = array();
    $FULLNAME = $DB->sql_concat('usr.firstname', "' '", 'usr.lastname');
    $FULLNAME_LIKE = $DB->sql_like($FULLNAME, ':name_like', FALSE);
    $IDNUMBER_LIKE = $DB->sql_like('usr.idnumber', ':id_like', FALSE);
    $LASTNAME_STARTSWITH = $DB->sql_like('usr.lastname', ':lastname_startswith', FALSE);

    $select  = 'SELECT COUNT(ins.id) ';
    $tables  = 'FROM {'. instructor::TABLE .'} ins ';
    $join    = 'LEFT JOIN {'. user::TABLE .'} usr ';
    $on      = 'ON ins.userid = usr.id ';
    $where   = 'ins.classid = :clsid ';
    $params['clsid'] = $classid;

    if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
        $where .= ' AND usr.inactive = 0 ';
    }

    if (!empty($namesearch)) {
        $namesearch = trim($namesearch);
        $where .= (!empty($where) ? ' AND ' : ' ') .'(('. $FULLNAME_LIKE .') OR ('.
                          $IDNUMBER_LIKE .')) ';
        $params['name_like'] = "%{$namesearch}%";
        $params['id_like']   = "%{$namesearch}%";
    }

    if ($alpha) {
        $where .= (!empty($where) ? ' AND ' : ' ') .'('. $LASTNAME_STARTSWITH .') ';
        $params['lastname_startswith'] = "{$alpha}%";
    }

    if (!empty($where)) {
        $where = 'WHERE '.$where.' ';
    }

    $sql = $select . $tables . $join . $on . $where;
    return $DB->count_records_sql($sql, $params);
}

