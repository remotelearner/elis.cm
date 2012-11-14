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

defined('MOODLE_INTERNAL') || die();

require_once(elispm::lib('selectionpage.class.php'));
require_once(elispm::file('usersetpage.class.php'));
require_once(elis::plugin_file('usersetenrol_manual', 'select_form.class.php'));

class clusteruserselectpage extends selectionpage {
    var $default_tab = 'clusteruserpage';

    var $pagename = 'clstusrsel';
    var $tab_page = 'usersetpage';

    var $parent_data_class = 'userset';

    function can_do_default() {
        $id = $this->required_param('id', PARAM_INT);
        return usersetpage::can_enrol_into_cluster($id);
    }

    function get_selection_filter() {
        // filter
        // cache POST data, because user_filtering messes with it
        $post = $_POST;
        $filter = new pm_user_filtering(null, 'index.php', array('s' => $this->pagename) + $this->get_base_params());
        $_POST = $post;
        return $filter;
    }

    function get_records($filter) {
        global $DB, $USER;

        $id      = $this->required_param('id', PARAM_INT);
        $sort    = $this->optional_param('sort', 'name', PARAM_ALPHA);
        $dir     = $this->optional_param('dir', 'ASC', PARAM_ALPHA);
        $pagenum = $this->optional_param('page', 0, PARAM_INT);
        $perpage = $this->optional_param('perpage', 30, PARAM_INT);

        $filters = array();

        // find users who do not have a manual assignment already
        $filters[] = new join_filter('id', clusterassignment::TABLE, 'userid',
                                     new AND_filter(array(new field_filter('clusterid', $id),
                                                          new field_filter('plugin', 'manual'))),
                                     true);

        // user-defined filter
        list($extrasql, $params) = $filter->get_sql_filter();
        if ($extrasql) {
            $filters[] = new select_filter($extrasql, $params);
        }

        // TODO: Ugly, this needs to be overhauled
        $upage = new usersetpage();

        if (!$upage->_has_capability('elis/program:userset_enrol')) {
            //perform SQL filtering for the more "conditional" capability

            //get the context for the "indirect" capability
            $context = pm_context_set::for_user_with_capability('cluster', 'elis/program:userset_enrol_userset_user', $USER->id);

            $allowed_clusters = userset::get_allowed_clusters($id);

            if (empty($allowed_clusters)) {
                $filters[] = new select_filter('FALSE');
            } else {
                $filters[] = new join_filter('id', clusterassignment::TABLE, 'userid',
                                             new in_list_filter('clusterid', $allowed_clusters));
            }
        }

        $count = user::count($filters);

        $users = user::find($filters, array($sort => $dir), $pagenum * $perpage, $perpage);

        return array($users, $count);
    }

    protected function init_selection_form(&$form) {
        parent::init_selection_form($form);
        $params = array('autoenrol' => 1);
        $form->set_data($params);
    }

    function get_selection_form() {
        return new clusterassignmentform();
    }

    function get_base_params() {
        $params = parent::get_base_params();
        $params['id'] = $this->required_param('id', PARAM_INT);
        return $params;
    }

    function print_selection_filter($filter) {
        $filter->display_add();
        $filter->display_active();
    }

    function get_records_from_selection($record_ids) {
        $sort = $this->optional_param('sort', 'name', PARAM_ALPHA);
        $dir  = $this->optional_param('dir', 'ASC', PARAM_ALPHA);

        $users = user::find(new in_list_filter('id', $record_ids), array($sort => $dir));

        return $users;
    }

    function create_selection_table($records, $baseurl) {
        return new clusteruserselecttable($records, new moodle_url($baseurl));
    }

    function process_selection($data) {
        foreach ($data->_selection as $userid) {
            cluster_manual_assign_user($data->id, $userid, !empty($data->autoenrol), !empty($data->leader));
        }
        $tmppage = new clusteruserpage(array('id' => $data->id));
//        redirect($tmppage->url, get_string('userset_user_assigned', 'elis_program', count($data->_selection)));
        redirect($tmppage->url, '', 2);
    }
}

class clusteruserselecttable extends selection_table {
    function __construct(&$items, $url) {
        $url->remove_params(array('mode')); // TBD
        $url->params(array('id' => required_param('id', PARAM_INT)));
        $columns = array(
            '_selection'   => array('header' => ''),
            'idnumber'     => array('header' => get_string('idnumber', 'elis_program')),
            'name'        => array('header' => array('firstname' => array('header' => get_string('firstname')),
                                                      'lastname' => array('header' => get_string('lastname'))),
                                   'display_function' => array('display_table', 'display_user_fullname_item')),
            'country'      => array('header' => get_string('country')),
            );

        $sort = optional_param('sort', 'lastname', PARAM_ALPHA);
        $dir = optional_param('dir', 'ASC', PARAM_ALPHA);

        // set sorting
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

        parent::__construct($items, $columns, $url);
    }

    function get_item_display_name($column, $item) {
        if (isset($item->name)) {
            return $item->name;
        } else {
            return fullname($item->to_object());
        }
    }
}
