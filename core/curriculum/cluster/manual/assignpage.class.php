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

// FIXME: change the name of this file to match the defined classes

require_once (CURMAN_DIRLOCATION . '/lib/lib.php');
require_once (CURMAN_DIRLOCATION . '/lib/associationpage.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/user.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/cluster.class.php');
require_once (CURMAN_DIRLOCATION . '/cluster/manual/lib.php');
require_once (CURMAN_DIRLOCATION . '/usermanagementpage.class.php');
require_once (CURMAN_DIRLOCATION . '/clusterpage.class.php');
require_once (CURMAN_DIRLOCATION . '/cluster/manual/assignpage_form.php');
require_once (CURMAN_DIRLOCATION . '/cluster/manual/selectpage.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/clusterassignment.class.php');


class userclusterbasepage extends associationpage {

    var $data_class = 'clusterassignment';  // TODO: create a usercluster datarecord subclass so we can use this
    var $form_class = 'assignpage_form';

    var $section = 'users';

    function action_add() {
        $this->print_add_form();
    }

    function print_add_form() {
        $userid = required_param('userid', PARAM_INT);
        $clusterid = required_param('clusterid', PARAM_INT);
        $id = required_param('id', PARAM_INT);

        $target = $this->get_new_page(array('action' => 'savenew', 'id' => $id));

        $data['obj'] = array('userid' => $userid, 'clusterid' => $clusterid);
        $form = new $this->form_class($target->get_moodle_url(), $data);

        $form->display();
    }

    function action_savenew() {
        $target = $this->get_new_page(array('action' => 'savenew'));

        $form = new $this->form_class($target->get_moodle_url());

        if ($form->is_cancelled()) {
            $this->action_default();
            return;
        }

        $data = $form->get_data();

        if($data) {
            cluster_manual_assign_user($data->clusterid, $data->userid, !empty($data->autoenrol), !empty($data->leader));
            $this->action_default();
        } else {
            // Validation must have failed, redisplay form
            $form->display();
        }
    }

    /**
     * @todo it would be better to create a datarecord subclass with the proper delete() method
     * instead of overriding this method
     */
    function action_confirm() {
        global $CURMAN;

        $association_id = required_param('association_id', PARAM_INT);
        $confirm = required_param('confirm', PARAM_ALPHANUM);

        if (md5($association_id) != $confirm) {
            echo cm_error('Invalid confirmation code!');
        } else {
            $rec = $CURMAN->db->get_record(CLSTASSTABLE, 'id', $association_id);
            cluster_manual_deassign_user($rec->clusterid, $rec->userid);
        }

        $target = $this->action_default();
    }

    function create_table_object($items, $columns, $formatters) {
        return new usercluster_page_table($items, $columns, $this, $formatters);
    }
}

class userclusterpage extends userclusterbasepage {
    var $pagename = 'usrclst';
    var $tab_page = 'usermanagementpage';

    var $parent_data_class = 'user';

    function can_do_default() {
        $id = $this->required_param('id', PARAM_INT);
        return usermanagementpage::_has_capability('block/curr_admin:user:view', $id);
    }

    function can_do_add() {
        $userid = $this->required_param('userid');
        $clustid = $this->required_param('clusterid');
        return usercluster::can_manage_assoc($userid, $clustid);
    }

    function can_do_delete() {
        return $this->can_do_edit();
    }

    function can_do_edit() {
        $aid = $this->required_param('association_id');
        $clustass = new clusterassignment($aid);
        return usercluster::can_manage_assoc($clustass->userid, $clustass->clusterid);
    }

    function action_default() {
        $id = $this->required_param('id', PARAM_INT);
        
        $this->print_autoassigned_table();

        $this->print_manuallyassigned_table();

        $items = $this->get_manuallyassigned_items();

        //get the listing specifically for this user
        $this->print_dropdown(cluster_get_listing('name', 'ASC', 0, 0, '', '', array(), $id), $items, 'userid', 'clusterid', 'add');
    }

    function print_autoassigned_table() {
        global $CURMAN;

        $id = $this->required_param('id', PARAM_INT);

        $sort         = optional_param('sort', 'clusterid', PARAM_ALPHA);
        $dir          = optional_param('dir', 'ASC', PARAM_ALPHA);

        $columns = array(
                'name'        => 'Name',
                'display'     => 'Display',
                'autoenrol'   => 'Autoenrol',
                'leader'      => 'Cluster leader',
        );

        $sql = "SELECT DISTINCT ca.clusterid, c.name, c.display, ca.autoenrol, ca.leader
                  FROM {$CURMAN->db->prefix_table(CLSTASSTABLE)} ca
            INNER JOIN {$CURMAN->db->prefix_table(CLSTTABLE)} c ON c.id = ca.clusterid
                 WHERE ca.userid = $id AND plugin <> 'manual'
                 ORDER BY $sort $dir";
        $items = $CURMAN->db->get_records_sql($sql);

        echo '<h2>' . get_string('autoassign_cluster_header', 'block_curr_admin') . "</h2>\n" ;

        $formatters = $this->create_link_formatters(array('name'), 'clusterpage', 'clusterid');

        $this->print_list_view($items, $columns, $formatters);
    }

    function print_manuallyassigned_table() {
        $columns = array(
                'name'        => 'Name',
                'display'     => 'Display',
                'autoenrol'   => 'Autoenrol',
                'leader'      => 'Cluster leader',
                'manage'      => '',
        );

        $items = $this->get_manuallyassigned_items();

        echo '<h2>' . get_string('manualassign_cluster_header', 'block_curr_admin') . "</h2>\n";

        $formatters = $this->create_link_formatters(array('name'), 'clusterpage', 'clusterid');

        $this->print_list_view($items, $columns, $formatters);
    }

    function get_manuallyassigned_items() {
        global $CURMAN;

        $id   = $this->required_param('id', PARAM_INT);
        $sort = $this->optional_param('sort', 'clusterid', PARAM_ALPHA);
        $dir  = $this->optional_param('dir', 'ASC', PARAM_ALPHA);

        $sql = "SELECT ca.id, ca.clusterid, c.name, c.display, ca.autoenrol, ca.leader
                  FROM {$CURMAN->db->prefix_table(CLSTASSTABLE)} ca
            INNER JOIN {$CURMAN->db->prefix_table(CLSTTABLE)} c ON c.id = ca.clusterid
                 WHERE ca.userid = $id AND plugin = 'manual'
              ORDER BY $sort $dir";
        return $CURMAN->db->get_records_sql($sql);
    }
}

class clusteruserpage extends userclusterbasepage {
    var $pagename = 'clstusr';
    var $tab_page = 'clusterpage';

    var $parent_data_class = 'cluster';

    function can_do_default() {
        $id = $this->required_param('id', PARAM_INT);
        return clusterpage::_has_capability('block/curr_admin:cluster:view', $id);
    }

    function can_do_add() {
        $userid = $this->required_param('userid');
        $clustid = $this->required_param('clusterid');
        return usercluster::can_manage_assoc($userid, $clustid);
    }

    function can_do_delete() {        
        $aid = 0;
        if(!empty($this->params['association_id'])) {
            $aid = $this->params['association_id'];
        } else {
            $aid = $this->optional_param('association_id', '', PARAM_INT);
        }
        
        $clustass = new clusterassignment($aid);
        return usercluster::can_manage_assoc($clustass->userid, $clustass->clusterid);
    }

    function can_do_edit() {
        return $this->can_do_delete();
    }

    function action_default() {
        $id = $this->required_param('id', PARAM_INT);

        $this->print_autoassigned_table();

        $this->print_manuallyassigned_table();

        $items = $this->get_manuallyassigned_items();

        $this->print_assign_link();
    }

    function print_autoassigned_table() {
        global $CURMAN;

        $id    = $this->required_param('id', PARAM_INT);
        $sort  = $this->optional_param('sort', 'name', PARAM_ALPHA);
        $dir   = $this->optional_param('dir', 'ASC', PARAM_ALPHA);

        $columns = array(
                'idnumber'    => 'ID Number',
                'name'        => 'Name',
                'email'       => 'E-mail Address',
                'autoenrol'   => 'Autoenrol',
                'leader'      => 'Cluster leader',
        );

        $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');

        $sql = "SELECT DISTINCT ca.userid, usr.idnumber, $FULLNAME AS name, usr.email, ca.autoenrol, ca.leader
                  FROM {$CURMAN->db->prefix_table(CLSTASSTABLE)} ca
            INNER JOIN {$CURMAN->db->prefix_table(USRTABLE)} usr ON usr.id = ca.userid
                 WHERE ca.clusterid = $id AND plugin <> 'manual'
                 ORDER BY $sort $dir";
        $items = new recordset_iterator(get_recordset_sql($sql));

        $sql = "SELECT COUNT(DISTINCT ca.userid)
                  FROM {$CURMAN->db->prefix_table(CLSTASSTABLE)} ca
                 WHERE ca.clusterid = $id AND plugin <> 'manual'";
        $count = count_records_sql($sql);

        echo '<h2>' . get_string('autoassign_user_header', 'block_curr_admin') . "</h2>\n" ;

        echo '<div style="float:right;">' . get_string('items_found', 'block_curr_admin', $count) . '</div>';

        $formatters = $this->create_link_formatters(array('idnumber', 'name'), 'usermanagementpage', 'userid');

        $this->print_list_view($items, $columns, $formatters);
    }

    function print_manuallyassigned_table() {
        global $CURMAN;
        $columns = array(
                'idnumber'    => 'ID Number',
                'name'        => 'Name',
                'email'       => 'E-mail Address',
                'autoenrol'   => 'Autoenrol',
                'leader'      => 'Cluster leader',
                'manage'      => '',
        );

        $id   = $this->required_param('id', PARAM_INT);

        $items = new recordset_iterator($this->get_manuallyassigned_items());

        echo '<h2>' . get_string('manualassign_user_header', 'block_curr_admin') . "</h2>\n";

        $sql = "SELECT COUNT(DISTINCT ca.userid)
                  FROM {$CURMAN->db->prefix_table(CLSTASSTABLE)} ca
                 WHERE ca.clusterid = $id AND plugin = 'manual'";
        $count = count_records_sql($sql);

        echo '<div style="float:right;">' . get_string('items_found', 'block_curr_admin', $count) . '</div>';

        $formatters = $this->create_link_formatters(array('idnumber', 'name'), 'usermanagementpage', 'userid');

        $this->print_list_view($items, $columns, $formatters);
    }

    function get_manuallyassigned_items() {
        global $CURMAN;

        $id   = $this->required_param('id', PARAM_INT);
        $sort = $this->optional_param('sort', 'name', PARAM_ALPHA);
        $dir  = $this->optional_param('dir', 'ASC', PARAM_ALPHA);

        $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');

        $sql = "SELECT ca.id, ca.userid, usr.idnumber, $FULLNAME AS name, usr.email, ca.autoenrol, ca.leader
                  FROM {$CURMAN->db->prefix_table(CLSTASSTABLE)} ca
            INNER JOIN {$CURMAN->db->prefix_table(USRTABLE)} usr ON usr.id = ca.userid
                 WHERE ca.clusterid = $id AND plugin = 'manual'
             ORDER BY $sort $dir";
        return get_recordset_sql($sql);
    }

    function print_assign_link() {
        $id = $this->required_param('id', PARAM_INT);

        $target = new clusteruserselectpage(array('id' => $id));
        if ($target->can_do()) {
            echo '<div align="center"><br /><a href="'.$target->get_url().'">Assign users</a></div>';
        }
    }
}


class usercluster_page_table extends association_page_table {
    function get_item_display_manage($column, $item) {
        $id = $this->page->required_param('id', PARAM_INT);

        $target = $this->page->get_new_page(array('action' => 'edit', 'association_id' => $item->id, 'id' => $id));
        if($target->can_do()) {
            $editbutton = "<a href=\"{$target->get_url()}\"><img src=\"pix/edit.gif\" alt=\"Edit\" title=\"Edit\" /></a>";
        } else {
            $editbutton = '';
        }

        return $editbutton.' '.parent::get_item_display_manage($column, $item);
    }

    function get_item_display_autoenrol($column, $item) {
        return $this->get_yesno_item_display($column, $item);
    }

    function get_item_display_leader($column, $item) {
        return $this->get_yesno_item_display($column, $item);
    }
}
?>
