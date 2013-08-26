<?php
/**
 * Common page class for role assignments
 *
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

defined('MOODLE_INTERNAL') || die();

require_once elispm::lib('associationpage2.class.php');
require_once elispm::lib('data/user.class.php');
require_once elispm::lib('data/usermoodle.class.php');
require_once elispm::file('form/addroleform.class.php');

abstract class rolepage extends associationpage2 {
    var $parent_page;
    var $section;

    public function __construct($params = null) {
        parent::__construct($params);
        $this->section = $this->get_parent_page()->section;
    }

    abstract protected function get_context();

    abstract protected function get_parent_page();

    function get_tab_page() {
        return $this->get_parent_page();
    }

    function get_page_title_default() {
        return print_context_name($this->get_context(), false);
    }

    function build_navbar_default($who = null, $addparent = true, $params = array()) {
        global $DB;

        //obtain the base of the navbar from the parent page class
        $parent_template = $this->get_parent_page()->get_new_page();
        $parent_template->build_navbar_view();
        $this->_navbar = $parent_template->navbar;

        //add a link to the first role screen where you select a role
        $id = $this->required_param('id', PARAM_INT);
        $params = array('id' => $id);
        if ($parentid = $this->optional_param('parent', 0, PARAM_INT)) {
            $params['parent'] = $parentid;
        }
        $page = $this->get_new_page($params, true);
        $this->navbar->add(get_string('roles', 'role'), $page->url);

        //if we are looking at a particular role, add it to the navigation
        $roleid = $this->optional_param('role', 0, PARAM_INT);
        if ($roleid != 0) {
            $rolename = $DB->get_field('role', 'name', array('id' => $roleid));
            $page = $this->get_new_page(array('id' => $id,
                                              'role' => $roleid), true);
            $this->navbar->add($rolename, $page->url);
        }
    }

    function print_tabs() {
        $roleid = $this->optional_param('role', 0, PARAM_INT);
        if ($roleid) {
            parent::print_tabs();
        } else {
            $id = $this->required_param('id', PARAM_INT);
            $this->get_parent_page()->print_tabs(get_class($this), array('id' => $id));
        }
    }

    /**
     * Return the page parameters for the page.  Used by the constructor for
     * calling $this->set_url().
     *
     * @return array
     */
    protected function _get_page_params() {
        $params = parent::_get_page_params();

        $id = $this->required_param('id', PARAM_INT);
        $params['id'] = $id;

        $page = $this->optional_param('page', 0, PARAM_INT);
        if ($page != 0) {
            $params['page'] = $page;
        }

        $role = $this->optional_param('role', 0, PARAM_INT);
        if ($role != 0) {
            $params['role'] = $role;
        }

        $assign = $this->optional_param('_assign', 0, PARAM_CLEAN);
        if ($assign != 0) {
            $params['_assign'] = $assign;
        }

        return $params;
    }

    function can_do_default() {
        return has_capability('moodle/role:assign', $this->get_context());
    }

    /**
     * Counts all the users assigned this role in this context or higher
     * (can be used by child classes to override the counting with other criteria)
     *
     * @param mixed $roleid either int or an array of ints
     * @param object $context
     * @param bool $parent if true, get list of users assigned in higher context too
     * @return int Returns the result count
     */
    function count_role_users($roleid, $context, $parent = false) {
        //default behaviour of counting role assignments
        return count_role_users($roleid, $context, $parent);
    }

    function display_default() {
        global $CURMAN, $DB, $OUTPUT;

        //the specific role we are asigning users to
        $roleid = $this->optional_param('role', '', PARAM_INT);
        //the specific context id we are assigning roles on
        $context = $this->get_context();

        if ($roleid) {
            //make sure the current user can assign roles on the current context
            $assignableroles = get_assignable_roles($context, ROLENAME_BOTH);
            $roleids = array_keys($assignableroles);
            if (!in_array($roleid, $roleids)) {
                print_error('nopermissions', 'error');
            }

            return parent::display_default();
        } else {
            //use the standard link decorator to link role names to their specific sub-pages
            $decorators = array(new role_name_decorator($this), 'decorate');

            //determine all apprlicable roles we can assign users as the current context
            $assignableroles = get_assignable_roles($context, ROLENAME_BOTH);

            if (count($assignableroles) > 0) {
                $roles = array();

                foreach ($assignableroles as $roleid => $rolename) {
                    $rec = new stdClass;
                    $rec->id = $roleid;
                    $rec->name = $rolename;
                    $rec->description = format_string($DB->get_field('role', 'description', array('id' => $roleid)));
                    $rec->count = $this->count_role_users($roleid, $context);
                    $roles[$roleid] = $rec;
                }

                $columns = array('name'        => array('header' => get_string('name'),
                                                        'decorator' => $decorators),
                                 'description' => array('header' => get_string('description')),
                                 'count'       => array('header' =>  get_string('users')));
                $table = new nosort_table($roles, $columns, $this->url);
                echo $table->get_html();
            } else {
                //determine if there are any roles whose assignments are not permitted for the current user
                //by the "Allow role assignments" tab
                $admin_assignable_roles = get_assignable_roles($context, ROLENAME_BOTH, false, get_admin());

                if (count($admin_assignable_roles) > 0) {
                    //denied based on configuration
                    echo $OUTPUT->box(get_string('norolespermitted', 'elis_program'));
                } else {
                    //no roles are assignable at this context
                    echo $OUTPUT->box(get_string('norolesexist', 'elis_program'));
                }
            }
        }
    }

    protected function get_base_params() {
        $params = parent::get_base_params();
        $params['role'] = $this->required_param('role', PARAM_INT);
        return $params;
    }

    // ELISAT-349: Part 1
    function get_extra_page_params() {
        $extra_params = array();
        $sort = optional_param('sort', 'name', PARAM_ACTION);
        $order = optional_param('dir', 'ASC', PARAM_ACTION);
        if ($order != 'DESC') {
            $order = 'ASC';
        }
        $extra_params['sort'] = $sort;
        $extra_params['dir'] = $order;
        return $extra_params;
    }

    protected function get_selection_form() {
        if ($this->is_assigning()) {
            return new addroleform();
        } else {
            return new removeroleform();
        }
    }

    protected function process_assignment($data) {
        $this->session_selection_deletion();
        $context = $this->get_context();

        //make sure the current user can assign roles on the current context
        $assignableroles = get_assignable_roles($context, ROLENAME_BOTH);
        $roleids = array_keys($assignableroles);
        if (!in_array($data->role, $roleids)) {
            print_error('nopermissions', 'error');
        }

        //perform the role assignments
        foreach ($data->_selection as $user) {
            role_assign($data->role, cm_get_moodleuserid($user), $context->id);
        }

        //set up the redirect to the appropriate page
        $id = $this->required_param('id', PARAM_INT);
        $role = $this->required_param('role', PARAM_INT);
        $tmppage = $this->get_new_page(array('_assign' => 'assign',
                                             'id'      => $id,
                                             'role'    => $role));
        redirect($tmppage->url, get_string('users_assigned_to_role','elis_program',count($data->_selection)));
    }

    protected function process_unassignment($data) {
        $this->session_selection_deletion();
        $context = $this->get_context();

        //make sure the current user can assign roles on the current context
        $assignableroles = get_assignable_roles($context, ROLENAME_BOTH);
        $roleids = array_keys($assignableroles);
        if (!in_array($data->role, $roleids)) {
            print_error('nopermissions', 'error');
        }

        //perform the role unassignments
        foreach ($data->_selection as $user) {
            role_unassign($data->role, cm_get_moodleuserid($user), $context->id);
        }

        //set up the redirect to the appropriate page
        $id = $this->required_param('id', PARAM_INT);
        $role = $this->required_param('role', PARAM_INT);
        $tmppage = $this->get_new_page(array('_assign' => 'unassign',
                                             'id'      => $id,
                                             'role'    => $role));
        redirect($tmppage->url, get_string('users_removed_from_role','elis_program',count($data->_selection)));
    }

    protected function get_selection_filter() {
        $post = $_POST;
        $filter = new pm_user_filtering(null, 'index.php', array('s' => $this->pagename) + $this->get_base_params());
        $_POST = $post;
        return $filter;
    }

    protected function print_selection_filter($filter) {
        $filter->display_add();
        $filter->display_active();
    }

    protected function get_assigned_records($filter) {
        global $CFG, $DB;

        $context = $this->get_context();
        $roleid = $this->required_param('role', PARAM_INT);

        $pagenum = optional_param('page', 0, PARAM_INT);
        $perpage = 30;

        $sort = optional_param('sort', 'lastname', PARAM_ACTION);
        $order = optional_param('dir', 'ASC', PARAM_ACTION);
        if ($order != 'DESC') {
            $order = 'ASC';
        }

        static $sortfields = array(
            'lastname' => 'lastname',
            'firstname' => 'firstname',
            'idnumber' => 'idnumber',
            );
        if (!array_key_exists($sort, $sortfields)) {
            $sort = key($sortfields);
        }
        if (is_array($sortfields[$sort])) {
            $sortclause = implode(', ', array_map(create_function('$x', "return \"\$x $order\";"), $sortfields[$sort]));
        } else {
            $sortclause = "{$sortfields[$sort]} $order";
        }

        $where = "idnumber IN (SELECT mu.idnumber
                                 FROM {user} mu
                                 JOIN {role_assignments} ra
                                      ON ra.userid = mu.id
                                WHERE ra.contextid = :contextid
                                  AND ra.roleid = :roleid
                                  AND mu.mnethostid = :mnethostid)";

        $params = array('contextid' => $context->id,
                        'roleid' => $roleid,
                        'mnethostid' => $CFG->mnet_localhost_id);

        list($extrasql, $extraparams) = $filter->get_sql_filter();
        if ($extrasql) {
            $where .= " AND $extrasql";
            $params = array_merge($params, $extraparams);
        }

        $count = $DB->count_records_select(user::TABLE, $where, $params);
        $users = $DB->get_recordset_select(user::TABLE, $where, $params, $sortclause, '*', $pagenum*$perpage, $perpage);

        return array($users, $count);
    }

    protected function get_available_records($filter) {
        global $CFG, $DB;

        $context = $this->get_context();
        $roleid = required_param('role', PARAM_INT);

        $pagenum = optional_param('page', 0, PARAM_INT);
        $perpage = 30;

        $sort = optional_param('sort', 'name', PARAM_ACTION);
        $order = optional_param('dir', 'ASC', PARAM_ACTION);
        if ($order != 'DESC') {
            $order = 'ASC';
        }

        static $sortfields = array(
            'name' => array('lastname', 'firstname'),
            'lastname' => array('lastname', 'firstname'),
            'firstname' => array('firstname', 'lastname'),
            'idnumber' => 'idnumber',
            );
        if (!array_key_exists($sort, $sortfields)) {
            $sort = key($sortfields);
        }
        if (is_array($sortfields[$sort])) {
            $sortclause = implode(', ', array_map(create_function('$x', "return \"\$x $order\";"), $sortfields[$sort]));
        } else {
            $sortclause = "{$sortfields[$sort]} $order";
        }

        $where = "NOT EXISTS (SELECT 'x'
                              FROM {".usermoodle::TABLE."} um
                              JOIN {user} mu
                                ON um.muserid = mu.id
                              JOIN {role_assignments} ra
                                ON mu.id = ra.userid
                              WHERE ra.contextid = :contextid
                                AND ra.roleid = :roleid
                                AND mu.mnethostid = :mnethostid
                                AND {".user::TABLE."}.id = um.cuserid)
                  AND EXISTS (SELECT 'x'
                              FROM {".usermoodle::TABLE."} um
                              WHERE {".user::TABLE."}.id = um.cuserid)";
        $params = array('contextid' => $context->id,
                        'roleid' => $roleid,
                        'mnethostid' => $CFG->mnet_localhost_id);

        list($extrasql, $extraparams) = $filter->get_sql_filter();

        if ($extrasql) {
            $where .= " AND $extrasql";
            $params = array_merge($params, $extraparams);
        }

        $count = $DB->count_records_select(user::TABLE, $where, $params);
        $users = $DB->get_recordset_select(user::TABLE, $where, $params, $sortclause, '*', $pagenum*$perpage, $perpage);

        return array($users, $count);
    }

    function get_records_from_selection($record_ids) {
        $sort = optional_param('sort', 'name', PARAM_ACTION);
        $order = optional_param('dir', 'ASC', PARAM_ACTION);

        $records = user::find(new in_list_filter('id', $record_ids), array($sort => $order));
        return $records;
    }

    protected function print_record_count($count, $label = null) {
        print_string('usersfound','elis_program',$count);
    }

    protected function create_selection_table($records, $baseurl) {
        $pagenum = optional_param('page', 0, PARAM_INT);
        $baseurl .= "&page={$pagenum}"; // ELISAT-349: part 2

        //persist our specific parameters
        $id = $this->required_param('id', PARAM_INT);
        $baseurl .= "&id={$id}";
        $assign = $this->optional_param('_assign', 'unassign', PARAM_ACTION);
        $baseurl .= "&_assign={$assign}";
        $role = $this->required_param('role', PARAM_INT);
        $baseurl .= "&role={$role}";

        $records = (is_array($records) || ($records instanceof Iterator && $records->valid())) ? $records : array();
        $columns = array('_selection' => array('header' => get_string('select')),
                         'idnumber'   => array('header' => get_string('idnumber')),
                         'name'       => array('header' => array('firstname' => array('header' => get_string('firstname')),
                                                                 'lastname' => array('header' => get_string('lastname'))
                                                                 ),
                                               'display_function' => array('display_table', 'display_user_fullname_item')),
        );

        //determine sort order
        $sort = optional_param('sort', 'lastname', PARAM_ALPHA);
        $dir  = optional_param('dir', 'ASC', PARAM_ALPHA);
        if ($dir !== 'DESC') {
            $dir = 'ASC';
        }

        if (isset($columns[$sort])) {
            $columns[$sort]['sortable'] = $dir;
        } elseif (isset($columns['name']['header'][$sort])) {
            $columns['name']['header'][$sort]['sortable'] = $dir;
        } else {
            $sort = 'lastname';
            $columns['name']['header']['lastname']['sortable'] = $dir;
        }

        return new user_selection_table($records, $columns, new moodle_url($baseurl));
    }

    protected function get_table_footer() {
        if ($this->is_assigning()) {
            return get_string('onlyvalidmoodleusers', 'elis_program');
        }
        return parent::get_table_footer();
    }
}


class curriculum_rolepage extends rolepage {
    var $pagename = 'currole';

    protected function get_context() {
        if (!isset($this->context)) {
            $id = $this->required_param('id', PARAM_INT);
            $context_instance = context_elis_program::instance($id);
            $this->set_context($context_instance);
        }
        return $this->context;
    }

    protected function get_parent_page() {
        if (!isset($this->parent_page)) {
            global $CFG, $CURMAN;
            require_once elispm::file('curriculumpage.class.php');
            $id = $this->required_param('id');
            $this->parent_page = new curriculumpage(array('id' => $id,
                                                          'action' => 'view'));
        }
        return $this->parent_page;
    }
}

class track_rolepage extends rolepage {
    var $pagename = 'trkrole';

    protected function get_context() {
        if (!isset($this->context)) {
            $id = $this->required_param('id', PARAM_INT);

            $context_instance = context_elis_track::instance($id);
            $this->set_context($context_instance);
        }
        return $this->context;
    }

    protected function get_parent_page() {
        if (!isset($this->parent_page)) {
            global $CFG, $CURMAN;
            require_once elispm::file('trackpage.class.php');
            $id = $this->required_param('id');
            $params = array('id' => $id, 'action' => 'view');
            if ($parentid = $this->optional_param('parent', 0, PARAM_INT)) { // this?
                $params['parent'] = $parentid;
            }
            $this->parent_page = new trackpage($params);
        }
        return $this->parent_page;
    }
}

class course_rolepage extends rolepage {
    var $pagename = 'crsrole';

    protected function get_context() {
        if (!isset($this->context)) {
            $id = $this->required_param('id', PARAM_INT);

            $context_instance = context_elis_course::instance($id);
            $this->set_context($context_instance);
        }
        return $this->context;
    }

    protected function get_parent_page() {
        if (!isset($this->parent_page)) {
            global $CFG, $CURMAN;
            require_once elispm::file('coursepage.class.php');
            $id = $this->required_param('id', PARAM_INT);
            $this->parent_page = new coursepage(array('id' => $id,
                                                      'action' => 'view'));
        }
        return $this->parent_page;
    }
}

class class_rolepage extends rolepage {
    var $pagename = 'clsrole';

    protected function get_context() {
        if (!isset($this->context)) {
            $id = $this->required_param('id', PARAM_INT);

            $context_instance = context_elis_class::instance($id);
            $this->set_context($context_instance);
        }
        return $this->context;
    }

    protected function get_parent_page() {
        if (!isset($this->parent_page)) {
            global $CFG, $CURMAN;
            require_once elispm::file('pmclasspage.class.php');
            $id = $this->required_param('id');
            $this->parent_page = new pmclasspage(array('id' => $id,
                                                       'action' => 'view'));
        }
        return $this->parent_page;
    }
}

class user_rolepage extends rolepage {
    var $pagename = 'usrrole';

    protected function get_context() {
        if (!isset($this->context)) {
            $id = $this->required_param('id', PARAM_INT);

            $context_instance = context_elis_user::instance($id);
            $this->set_context($context_instance);
        }
        return $this->context;
    }

    protected function get_parent_page() {
        if (!isset($this->parent_page)) {
            global $CFG, $CURMAN;
            require_once elispm::file('userpage.class.php');
            $id = $this->required_param('id', PARAM_INT);
            $this->parent_page = new userpage(array('id' => $id,
                                                    'action' => 'view'));
        }
        return $this->parent_page;
    }
}

class cluster_rolepage extends rolepage {
    var $pagename = 'clstrole';

    protected function get_context() {
        if (!isset($this->context)) {
            $id = $this->required_param('id', PARAM_INT);

            $context_instance = context_elis_userset::instance($id);
            $this->set_context($context_instance);
        }
        return $this->context;
    }

    protected function get_parent_page() {
        if (!isset($this->parent_page)) {
            global $CFG, $CURMAN;
            require_once elispm::file('usersetpage.class.php');
            $id = $this->required_param('id', PARAM_INT);
            $this->parent_page = new usersetpage(array('id' => $id,
                                                       'action' => 'view'));
        }
        return $this->parent_page;
    }

    /*
     * Override the base get record method -- if the user has a certain
     * capability, then only show them the cluster members (ELIS-1570).
     */
    protected function get_assigned_records($filter) {
        if (has_capability('elis/program:userset_role_assign_userset_users', $this->get_context(), NULL, false)) {
            global $CFG, $DB;

            $context = $this->get_context();
            $roleid = required_param('role', PARAM_INT);

            $pagenum = optional_param('page', 0, PARAM_INT);
            $perpage = 30;

            $sort = optional_param('sort', 'name', PARAM_ACTION);
            $order = optional_param('dir', 'ASC', PARAM_ACTION);
            if ($order != 'DESC') {
                $order = 'ASC';
            }

            static $sortfields = array(
                'name' => array('lastname', 'firstname'),
                'idnumber' => 'idnumber',
                );
            if (!array_key_exists($sort, $sortfields)) {
                $sort = key($sortfields);
            }
            if (is_array($sortfields[$sort])) {
                $sortclause = implode(', ', array_map(create_function('$x', "return \"\$x $order\";"), $sortfields[$sort]));
            } else {
                $sortclause = "{$sortfields[$sort]} $order";
            }

            $where = "idnumber IN (SELECT mu.idnumber
                                     FROM {user} mu
                                     JOIN {role_assignments} ra
                                          ON ra.userid = mu.id
                                    WHERE ra.contextid = :contextid
                                      AND ra.roleid = :roleid
                                      AND mu.mnethostid = :mnethostid)
                        AND id IN (SELECT userid
                                     FROM {".clusterassignment::TABLE."} uc
                                    WHERE uc.clusterid = :clusterid)";

            $params = array('contextid' => $context->id,
                            'roleid' => $roleid,
                            'mnethostid' => $CFG->mnet_localhost_id,
                            'clusterid' => $context->instanceid);

            list($extrasql, $extraparams) = $filter->get_sql_filter();

            if ($extrasql) {
                $where .= " AND $extrasql";
                $params = array_merge($params, $extraparams);
            }

            $count = $DB->count_records_select(user::TABLE, $where, $params);
            $users = $DB->get_recordset_select(user::TABLE, $where, $params, $sortclause, '*', $pagenum*$perpage, $perpage);

            return array($users, $count);
        } else {
            return parent::get_assigned_records($filter);
        }
    }

    /*
     * Override the base get record method -- if the user has a certain
     * capability, then only show them the cluster members (ELIS-1570).
     */
    protected function get_available_records($filter) {
        if (has_capability('elis/program:userset_role_assign_userset_users', $this->get_context(), NULL, false)) {
            global $CFG, $DB;

            $context = $this->get_context();
            $roleid = required_param('role', PARAM_INT);

            $pagenum = optional_param('page', 0, PARAM_INT);
            $perpage = 30;

            $sort = optional_param('sort', 'name', PARAM_ACTION);
            $order = optional_param('dir', 'ASC', PARAM_ACTION);
            if ($order != 'DESC') {
                $order = 'ASC';
            }

            static $sortfields = array(
                'name' => array('lastname', 'firstname'),
                'idnumber' => 'idnumber',
                );
            if (!array_key_exists($sort, $sortfields)) {
                $sort = key($sortfields);
            }
            if (is_array($sortfields[$sort])) {
                $sortclause = implode(', ', array_map(create_function('$x', "return \"\$x $order\";"), $sortfields[$sort]));
            } else {
                $sortclause = "{$sortfields[$sort]} $order";
            }

            $where = "NOT EXISTS (SELECT 'x'
                                  FROM {".usermoodle::TABLE."} um
                                  JOIN {user} mu
                                    ON um.muserid = mu.id
                                  JOIN {role_assignments} ra
                                    ON mu.id = ra.userid
                                  WHERE ra.contextid = :contextid
                                    AND ra.roleid = :roleid
                                    AND mu.mnethostid = :mnethostid
                                    AND {".user::TABLE."}.id = um.cuserid)
                      AND EXISTS (SELECT 'x'
                                  FROM {".usermoodle::TABLE."} um
                                  JOIN {".clusterassignment::TABLE."} ca
                                    ON um.cuserid = ca.userid
                                  WHERE {".user::TABLE."}.id = um.cuserid
                                    AND ca.clusterid = :clusterid)";

            $params = array('contextid' => $context->id,
                            'roleid' => $roleid,
                            'mnethostid' => $CFG->mnet_localhost_id,
                            'clusterid' => $context->instanceid);

            list($extrasql, $extraparams) = $filter->get_sql_filter();

            if ($extrasql) {
                $where .= " AND $extrasql";
                $params = array_merge($params, $extraparams);
            }

            $count = $DB->count_records_select(user::TABLE, $where, $params);
            $users = $DB->get_recordset_select(user::TABLE, $where, $params, $sortclause, '*', $pagenum*$perpage, $perpage);

            return array($users, $count);
        } else {
            return parent::get_available_records($filter);
        }
    }

    /**
     * Counts all the users assigned this role in this context or higher
     * (includes enforcing that counted users are assigned to cluster if the current
     *  user is limited in that regard)
     *
     * @param mixed $roleid either int or an array of ints
     * @param object $context
     * @param bool $parent if true, get list of users assigned in higher context too
     * @return int Returns the result count
     */
    function count_role_users($roleid, $context, $parent = false) {
        global $DB;

        if ($parent) {
            if ($contexts = get_parent_contexts($context)) {
                $parentcontexts = ' OR r.contextid IN ('.implode(',', $contexts).')';
            } else {
                $parentcontexts = '';
            }
        } else {
            $parentcontexts = '';
        }

        if ($roleid) {
            list($rids, $params) = $DB->get_in_or_equal($roleid, SQL_PARAMS_QM);
            $roleselect = "AND r.roleid $rids";
        } else {
            $params = array();
            $roleselect = '';
        }

        array_unshift($params, $context->id);

        $sql = "SELECT count(u.id)
                  FROM {role_assignments} r
                  JOIN {user} u ON u.id = r.userid
                 WHERE (r.contextid = ? $parentcontexts)
                       $roleselect
                       AND u.deleted = 0";

        //start of RL addition
        if (has_capability('elis/program:userset_role_assign_userset_users', $this->get_context(), NULL, false)) {
            //users explicitly assigned this capability are limited to seeing users who are also
            //directly assigned to this cluster
            require_once(elispm::lib('data/clusterassignment.class.php'));

            $sql .= " AND EXISTS (SELECT *
                                  FROM {".usermoodle::TABLE."} um
                                  JOIN {".clusterassignment::TABLE."} clstass
                                    ON um.cuserid = clstass.userid
                                  WHERE u.id = um.muserid
                                  AND clstass.clusterid = ?
                      )";
            $params[] = $context->instanceid;
        }
        //end of RL addition

        return $DB->count_records_sql($sql, $params);;
    }
}

/******************************************************************************
 * Tables
 ******************************************************************************/

class nosort_table extends display_table {
    function is_sortable_default() {
        return false;
    }
}

class user_selection_table extends selection_table {
    function get_item_display_name($column, $item) {
        return display_table::display_user_fullname_item($column, $item);
    }
}

/**
 * Make the role name into a link to view the assignments for that role,
 * starting on the list of assigned users if there are any, or the list of
 * available users if not
 */
class role_name_decorator {
    /**
     * Class constructor
     *
     * @param rolepage $page The page being used to display the list of roles
     */
    function __construct(&$page) {
        //copy the page instance for safety reasons
        $this->page = $page->get_new_page();
    }

    /**
     * Main decoration method
     *
     * @param string $text The column text being displayed
     * @param string $column The field corresponding to the display text
     * @param object $item The record representing the current row
     * @return string The final display string
     */
    function decorate($text, $column, $item) {
        //obtain the page url, including parameters
        $url = $this->page->url;

        //set the role id based on the current item
        $url->params(array('role' => $item->id));

        if ($item->count == 0) {
            //no users, so start on "assign" page
            $url->params(array('_assign' => 'assign'));
        } else {
            //users, so start on "unassign" page
            $url->params(array('_assign' => 'unassign'));
        }

        return html_writer::link($url, $text);
    }
}
