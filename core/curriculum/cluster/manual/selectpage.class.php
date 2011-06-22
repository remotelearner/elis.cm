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

require_once (CURMAN_DIRLOCATION . '/lib/selectionpage.class.php');
require_once (CURMAN_DIRLOCATION . '/clusterpage.class.php');
require_once (CURMAN_DIRLOCATION . '/form/clusterassignmentform.class.php');

class clusteruserselectpage extends selectionpage {
    var $pagename = 'clstusrsel';
    var $tab_page = 'clusterpage';

    var $parent_data_class = 'cluster';

    function can_do_default() {
        $id = $this->required_param('id', PARAM_INT);
        return clusterpage::can_enrol_into_cluster($id);
    }

    function get_selection_filter() {
        // filter
        // cache POST data, because user_filtering messes with it
        $post = $_POST;
        $filter = new cm_user_filtering(null, 'index.php', array('s' => $this->pagename) + $this->get_base_params());
        $_POST = $post;
        return $filter;
    }

    function get_records($filter) {
        global $CURMAN, $USER;

        $id           = $this->required_param('id', PARAM_INT);
        $sort         = $this->optional_param('sort', 'name', PARAM_ALPHA);
        $dir          = $this->optional_param('dir', 'ASC', PARAM_ALPHA);
        $pagenum      = $this->optional_param('page', 0, PARAM_INT);

        $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');

        $LIKE     = $CURMAN->db->sql_compare();
        $sql = "  FROM {$CURMAN->db->prefix_table(USRTABLE)} usr
       LEFT OUTER JOIN {$CURMAN->db->prefix_table(CLSTASSTABLE)} ca ON ca.userid = usr.id AND ca.clusterid = $id AND ca.plugin = 'manual'
                 WHERE ca.userid IS NULL";

        $extrasql = $filter->get_sql_filter();
        if ($extrasql) {
            $sql .= " AND $extrasql";
        }

        if(!clusterpage::_has_capability('block/curr_admin:track:enrol')) {
            //perform SQL filtering for the more "conditional" capability

            //get the context for the "indirect" capability
            $context = cm_context_set::for_user_with_capability('cluster', 'block/curr_admin:cluster:enrol_cluster_user', $USER->id);
            
            $allowed_clusters = cluster::get_allowed_clusters($id);

            if(empty($allowed_clusters)) {
                $sql .= ' AND 0=1';
            } else {
                $cluster_filter = implode(',', $allowed_clusters);
                $sql .= " AND usr.id IN (
                            SELECT userid FROM " . $CURMAN->db->prefix_table(CLSTUSERTABLE) . "
                            WHERE clusterid IN ({$cluster_filter}))";
            }
        }

        $count = $CURMAN->db->count_records_sql('SELECT COUNT(usr.id) '.$sql);

        if ($sort) {
            if ($sort == 'name') {
                $sort = 'lastname';
            }
            $sql .= " ORDER BY $sort $dir";
        }

        $users = $CURMAN->db->get_records_sql("SELECT usr.*, $FULLNAME AS name".$sql, $pagenum*30, 30);

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
        global $CURMAN;

        $users = $CURMAN->db->get_records_select('crlm_user', 'id in ('.implode(',',$record_ids).')');

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
        redirect($tmppage->get_url(), get_string('cluster_user_assigned', 'block_curr_admin', count($data->_selection)));
    }
}

class clusteruserselecttable extends selection_table {
    function __construct(&$items, $url) {
        $columns = array(
            '_selection'       => '',
            'idnumber'     => get_string('id', 'block_curr_admin'),
            'name'         => get_string('name', 'block_curr_admin'),
            'country'      => get_string('country', 'block_curr_admin'),
            );
        parent::__construct($items, $columns, $url);
    }

    function get_item_display_name($column, $item) {
        if (isset($item->name)) {
            return $item->name;
        } else {
            return cm_fullname($item);
        }
    }
}


?>
