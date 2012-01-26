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

require_once CURMAN_DIRLOCATION . '/lib/associationpage2.class.php';
require_once CURMAN_DIRLOCATION . '/form/addroleform.class.php';

abstract class rolepage extends associationpage2 {
    public function __construct($params = false) {
        $this->section = $this->get_parent_page()->section;
        parent::__construct($params);
    }

    abstract protected function get_context();

    abstract protected function get_parent_page();

    function get_tab_page() {
        return $this->get_parent_page();
    }

    function get_title() {
        return print_context_name($this->get_context(), false);
    }

    function get_navigation_default() {
        global $CURMAN;

        $navigation = $this->get_parent_page()->get_navigation_view();

        $tmppage = clone($this);
        $tmppage->params = array('id' => $this->required_param('id', PARAM_INT));
        array_push($navigation, array('name' => get_string('roles', 'role'),
                                      'link' => $tmppage->get_url()));

        $roleid = $this->optional_param('role', '0', PARAM_INT);
        if ($roleid) {
            $tmppage->params = array('id' => $this->required_param('id', PARAM_INT),
                                     'role' => $roleid);
            array_push($navigation, array('name' => $CURMAN->db->get_field('role', 'name', 'id', $roleid),
                                          'link' => $tmppage->get_url()));
        }

        return $navigation;
    }

    function print_tabs() {
        $roleid = $this->optional_param('role', '0', PARAM_INT);
        if ($roleid) {
            parent::print_tabs();
        } else {
            $id = $this->required_param('id', PARAM_INT);
            $this->get_parent_page()->print_tabs(get_class($this), array('id' => $id));
        }
    }

    function can_do_default() {
        return has_capability('moodle/role:assign', $this->get_context());
    }

    function action_default() {
        global $CURMAN;

        $roleid = $this->optional_param('role', '', PARAM_INT);
        $context = $this->get_context();

        if ($roleid) {
            if (!user_can_assign($context, $roleid)) {
                print_error('nopermissions', 'error');
            }
            return parent::action_default();
        } else {
            $decorators = array('name' => new role_name_decorator(clone($this)));

            $assignableroles = get_assignable_roles($context, 'name', ROLENAME_BOTH);
            $roles = array();

            foreach ($assignableroles as $roleid => $rolename) {
                $rec = new stdClass;
                $rec->id = $roleid;
                $rec->name = $rolename;
                $rec->description = format_string(get_field('role', 'description', 'id', $roleid));
                $rec->count = count_role_users($roleid, $context);
                $roles[$roleid] = $rec;
            }

            $table = new nosort_table($roles,
                                      array('name' => get_string('name'),
                                            'description' => get_string('description'),
                                            'count' => get_string('users')),
                                      $this->get_moodle_url(), $decorators);
            $table->print_table();
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
        $timenow = time();
        $context = $this->get_context();
        if (!user_can_assign($context, $data->role)) {
            print_error('nopermissions', 'error');
        }
        foreach ($data->_selection as $user) {
            role_assign($data->role, cm_get_moodleuserid($user), 0, $context->id, $timenow, $data->duration ? $timenow + $data->duration : 0);
        }
        $tmppage = $this->get_basepage();
        $tmppage->params['_assign'] = 'assign';
        redirect($tmppage->get_url(), get_string('users_assigned_to_role','block_curr_admin',count($data->_selection)));
    }

    protected function process_unassignment($data) {
        $context = $this->get_context();
        if (!user_can_assign($context, $data->role)) {
            print_error('nopermissions', 'error');
        }
        foreach ($data->_selection as $user) {
            role_unassign($data->role, cm_get_moodleuserid($user), 0, $context->id);
        }
        $tmppage = $this->get_basepage();
        redirect($tmppage->get_url(), get_string('users_removed_from_role','block_curr_admin',count($data->_selection)));
    }

    protected function get_selection_filter() {
        $post = $_POST;
        $filter = new cm_user_filtering(null, 'index.php', array('s' => $this->pagename) + $this->get_base_params());
        $_POST = $post;
        return $filter;
    }

    protected function print_selection_filter($filter) {
        $filter->display_add();
        $filter->display_active();
    }

    protected function get_assigned_records($filter) {
        global $CURMAN, $CFG;

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
                                 FROM {$CURMAN->db->prefix_table('user')} mu
                                 JOIN {$CURMAN->db->prefix_table('role_assignments')} ra
                                      ON ra.userid = mu.id
                                WHERE ra.contextid = $context->id
                                  AND ra.roleid = $roleid
                                  AND mu.mnethostid = {$CFG->mnet_localhost_id})";

        $extrasql = $filter->get_sql_filter();
        if ($extrasql) {
            $where .= " AND $extrasql";
        }

        $count = $CURMAN->db->count_records_select('crlm_user usr', $where);
        $users = $CURMAN->db->get_records_select('crlm_user usr', $where, $sortclause, '*', $pagenum*$perpage, $perpage);

        return array($users, $count);
    }

    protected function get_available_records($filter) {
        global $CURMAN, $CFG;

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

        $where = "idnumber NOT IN (SELECT mu.idnumber
                                     FROM {$CURMAN->db->prefix_table('user')} mu
                                LEFT JOIN {$CURMAN->db->prefix_table('role_assignments')} ra
                                          ON ra.userid = mu.id
                                    WHERE ra.contextid = $context->id
                                      AND ra.roleid = $roleid
                                      AND mu.mnethostid = {$CFG->mnet_localhost_id})";

        $extrasql = $filter->get_sql_filter();
        if ($extrasql) {
            $where .= " AND $extrasql";
        }

        $count = $CURMAN->db->count_records_select('crlm_user usr', $where);
        $users = $CURMAN->db->get_records_select('crlm_user usr', $where, $sortclause, '*', $pagenum*$perpage, $perpage);

        return array($users, $count);
    }

    function get_records_from_selection($record_ids) {
        global $CURMAN;
        $usersstring = implode(',', $record_ids);
        $records = $CURMAN->db->get_records_select('crlm_user', "id in ($usersstring)");
        return $records;
    }

    protected function print_record_count($count) {
        print_string('usersfound','block_curr_admin',$count);
    }

    protected function create_selection_table($records, $baseurl) {
        $pagenum = optional_param('page', 0, PARAM_INT);
        $baseurl .= "&page={$pagenum}"; // ELISAT-349: part 2
        $records = $records ? $records : array();
        return new user_selection_table($records,
                                        array('_selection' => '',
                                              'idnumber' => get_string('idnumber'),
                                              'name' => get_string('name')),
                                        new moodle_url($baseurl));
    }
}


class curriculum_rolepage extends rolepage {
    var $pagename = 'currole';

    protected function get_context() {
        if (!isset($this->context)) {
            $id = isset($this->params['id']) ? $this->params['id'] : required_param('id', PARAM_INT);

            $this->context = get_context_instance(context_level_base::get_custom_context_level('curriculum', 'block_curr_admin'), $id);
        }
        return $this->context;
    }

    protected function get_parent_page() {
        if (!isset($this->parent_page)) {
            global $CFG, $CURMAN;
            require_once CURMAN_DIRLOCATION . '/curriculumpage.class.php';
            $id = isset($this->params['id']) ? $this->params['id'] : required_param('id', PARAM_INT);
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
            $id = isset($this->params['id']) ? $this->params['id'] : required_param('id', PARAM_INT);

            $this->context = get_context_instance(context_level_base::get_custom_context_level('track', 'block_curr_admin'), $id);
        }
        return $this->context;
    }

    protected function get_parent_page() {
        if (!isset($this->parent_page)) {
            global $CFG, $CURMAN;
            require_once CURMAN_DIRLOCATION . '/trackpage.class.php';
            $id = isset($this->params['id']) ? $this->params['id'] : required_param('id', PARAM_INT);
            $this->parent_page = new trackpage(array('id' => $id,
                                                     'action' => 'view'));
        }
        return $this->parent_page;
    }
}

class course_rolepage extends rolepage {
    var $pagename = 'crsrole';

    protected function get_context() {
        if (!isset($this->context)) {
            $id = isset($this->params['id']) ? $this->params['id'] : required_param('id', PARAM_INT);

            $this->context = get_context_instance(context_level_base::get_custom_context_level('course', 'block_curr_admin'), $id);
        }
        return $this->context;
    }

    protected function get_parent_page() {
        if (!isset($this->parent_page)) {
            global $CFG, $CURMAN;
            require_once CURMAN_DIRLOCATION . '/coursepage.class.php';
            $id = isset($this->params['id']) ? $this->params['id'] : required_param('id', PARAM_INT);
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
            $id = isset($this->params['id']) ? $this->params['id'] : required_param('id', PARAM_INT);

            $this->context = get_context_instance(context_level_base::get_custom_context_level('class', 'block_curr_admin'), $id);
        }
        return $this->context;
    }

    protected function get_parent_page() {
        if (!isset($this->parent_page)) {
            global $CFG, $CURMAN;
            require_once CURMAN_DIRLOCATION . '/cmclasspage.class.php';
            $id = isset($this->params['id']) ? $this->params['id'] : required_param('id', PARAM_INT);
            $this->parent_page = new cmclasspage(array('id' => $id,
                                                       'action' => 'view'));
        }
        return $this->parent_page;
    }
}

class user_rolepage extends rolepage {
    var $pagename = 'usrrole';

    protected function get_context() {
        if (!isset($this->context)) {
            $id = isset($this->params['id']) ? $this->params['id'] : required_param('id', PARAM_INT);

            $this->context = get_context_instance(context_level_base::get_custom_context_level('user', 'block_curr_admin'), $id);
        }
        return $this->context;
    }

    protected function get_parent_page() {
        if (!isset($this->parent_page)) {
            global $CFG, $CURMAN;
            require_once CURMAN_DIRLOCATION . '/usermanagementpage.class.php';
            $id = isset($this->params['id']) ? $this->params['id'] : required_param('id', PARAM_INT);
            $this->parent_page = new usermanagementpage(array('id' => $id,
                                                              'action' => 'view'));
        }
        return $this->parent_page;
    }
}

class cluster_rolepage extends rolepage {
    var $pagename = 'clstrole';

    protected function get_context() {
        if (!isset($this->context)) {
            $id = isset($this->params['id']) ? $this->params['id'] : required_param('id', PARAM_INT);

            $this->context = get_context_instance(context_level_base::get_custom_context_level('cluster', 'block_curr_admin'), $id);
        }
        return $this->context;
    }

    protected function get_parent_page() {
        if (!isset($this->parent_page)) {
            global $CFG, $CURMAN;
            require_once CURMAN_DIRLOCATION . '/clusterpage.class.php';
            $id = isset($this->params['id']) ? $this->params['id'] : required_param('id', PARAM_INT);
            $this->parent_page = new clusterpage(array('id' => $id,
                                                       'action' => 'view'));
        }
        return $this->parent_page;
    }

    /*
     * Override the base get record method -- if the user has a certain
     * capability, then only show them the cluster members (ELIS-1570).
     */
    protected function get_assigned_records($filter) {
        if (has_capability('block/curr_admin:cluster:role_assign_cluster_users', $this->get_context(), NULL, false)) {
            global $CURMAN, $CFG;

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
                                     FROM {$CURMAN->db->prefix_table('user')} mu
                                     JOIN {$CURMAN->db->prefix_table('role_assignments')} ra
                                          ON ra.userid = mu.id
                                    WHERE ra.contextid = $context->id
                                      AND ra.roleid = $roleid
                                      AND mu.mnethostid = {$CFG->mnet_localhost_id})
                        AND id IN (SELECT userid
                                     FROM {$CURMAN->db->prefix_table('crlm_usercluster')} uc
                                    WHERE uc.clusterid = {$context->instanceid})";

            $extrasql = $filter->get_sql_filter();
            if ($extrasql) {
                $where .= " AND $extrasql";
            }

            $count = $CURMAN->db->count_records_select('crlm_user usr', $where);
            $users = $CURMAN->db->get_records_select('crlm_user usr', $where, $sortclause, '*', $pagenum*$perpage, $perpage);

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
        if (has_capability('block/curr_admin:cluster:role_assign_cluster_users', $this->get_context(), NULL, false)) {
            global $CURMAN, $CFG;

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

            $where = "idnumber NOT IN (SELECT mu.idnumber
                                         FROM {$CURMAN->db->prefix_table('user')} mu
                                    LEFT JOIN {$CURMAN->db->prefix_table('role_assignments')} ra
                                              ON ra.userid = mu.id
                                        WHERE ra.contextid = $context->id
                                          AND ra.roleid = $roleid
                                          AND mu.mnethostid = {$CFG->mnet_localhost_id})
                            AND id IN (SELECT userid
                                         FROM {$CURMAN->db->prefix_table('crlm_usercluster')} uc
                                        WHERE uc.clusterid = {$context->instanceid})";

            $extrasql = $filter->get_sql_filter();
            if ($extrasql) {
                $where .= " AND $extrasql";
            }

            $count = $CURMAN->db->count_records_select('crlm_user usr', $where);
            $users = $CURMAN->db->get_records_select('crlm_user usr', $where, $sortclause, '*', $pagenum*$perpage, $perpage);

            return array($users, $count);
        } else {
            return parent::get_available_records($filter);
        }
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
        return fullname($item);
    }
}

/**
 * Make the role name into a link to view the assignments for that role
 */
class role_name_decorator {
    function __construct($page) {
        $this->page = $page;
        if (!isset($page->params['id'])) {
            $page->params['id'] = required_param('id', PARAM_INT);
        }
    }

    function decorate($text, $column, $record) {
        $this->page->params['role'] = $record->id;

        // if there are no users in the role, then default to adding users,
        // otherwise default to listing current users
        if ($record->count) {
            unset($this->page->params['_assign']);
        } else {
            $this->page->params['_assign'] = 'assign';
        }

        return '<a href="' . $this->page->get_url() . '">' . $text . '</a>';
    }
}
?>
