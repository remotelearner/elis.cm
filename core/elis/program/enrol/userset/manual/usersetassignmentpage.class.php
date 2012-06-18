<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote-Learner.net Inc (http://www.remote-learner.net)
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

require_once(elispm::lib('lib.php'));
require_once(elispm::lib('associationpage.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/userset.class.php'));
require_once(elispm::lib('data/clusterassignment.class.php'));
require_once(elispm::file('userpage.class.php'));
require_once(elispm::file('usersetpage.class.php'));
require_once(elis::plugin_file('usersetenrol_manual', 'lib.php'));
require_once(elis::plugin_file('usersetenrol_manual', 'usersetassignment_form.php'));
require_once(elis::plugin_file('usersetenrol_manual', 'selectpage.class.php'));

class userclusterbasepage extends associationpage {
    var $data_class = 'clusterassignment';
    var $form_class = 'assignpage_form';

    var $section = 'users';

    function can_do_add() {
        return false;
    }

    public function do_add() {
        error('This shouldn\'t happen');
    }

    public static function can_manage_assoc($userid, $clustid) {
        global $USER;

        $allowed_clusters = array();

        if (!usersetpage::can_enrol_into_cluster($clustid)) {
            //the users who satisfty this condition are a superset of those who can manage associations
            return false;
        } else if (usersetpage::_has_capability('elis/program:userset_enrol', $clustid)) {
            //current user has the direct capability
            return true;
        }

        $allowed_clusters = userset::get_allowed_clusters($clustid);

        $filter = array(new field_filter('userid', $userid));

        //query to get users associated to at least one enabling cluster
        if (empty($allowed_clusters)) {
            $filter[] = new select_filter('FALSE');
        } else {
            $filter[] = new in_list_filter('clusterid', $allowed_clusters);
        }

        //user just needs to be in one of the possible clusters
        if (clusterassignment::exists($filter)) {
            return true;
        }

        return false;
    }
}

class userclusterpage extends userclusterbasepage {
    var $pagename = 'usrclst';
    var $tab_page = 'userpage';
    //var $default_tab = 'userclusterpage';

    var $parent_data_class = 'user';

    function can_do_default() {
        $id = $this->required_param('id', PARAM_INT);
        return userpage::_has_capability('elis/program:user_view', $id);
    }

    function can_do_add() {
        $userid = $this->required_param('userid');
        $clustid = $this->required_param('clusterid');
        return self::can_manage_assoc($userid, $clustid);
    }

    function can_do_delete() {
        return $this->can_do_edit();
    }

    function can_do_edit() {
        $aid = $this->required_param('association_id');
        $clustass = new clusterassignment($aid);
        return self::can_manage_assoc($clustass->userid, $clustass->clusterid);
    }

    function display_default() {
        $id = $this->required_param('id', PARAM_INT);

        $this->print_autoassigned_table();
        echo '<br/>';
        $this->print_manuallyassigned_table();
    }

    // Overridden to use save immediately, instead of prompting
    public function do_add() {
        require_sesskey();

        $id = $this->required_param('id', PARAM_INT);
        $userid = $this->required_param('userid', PARAM_INT);
        $clusterid = $this->required_param('clusterid', PARAM_INT);

        cluster_manual_assign_user($clusterid, $userid);
        $target = $this->get_new_page(array('id' => $id), true);
        redirect($target->url, ucwords($this->data_class) .
                 ' '.  get_string('saved','elis_program') .'.');
    }

    function print_autoassigned_table() {
        global $OUTPUT;

        $id = $this->required_param('id', PARAM_INT);
        $sort = optional_param('sort', 'name', PARAM_ALPHA);
        $dir  = optional_param('dir', 'ASC', PARAM_ALPHA);
        $page  = $this->optional_param('aapage', 0, PARAM_INT);
        $perpage  = $this->optional_param('aaperpage', 20, PARAM_INT);
        $mapage  = $this->optional_param('mapage', 0, PARAM_INT);
        $maperpage  = $this->optional_param('maperpage', 20, PARAM_INT);

        $columns = array(
            'name'        => array('header' => get_string('name', 'elis_program'),
                                   'decorator' => array(new record_link_decorator('usersetpage',
                                                                                  array('action'=>'view'),
                                                                                  'id'),
                                                        'decorate')),
            'display'     => array('header' => get_string('description', 'elis_program')),
        );

        if ($dir !== 'DESC') {
            $dir = 'ASC';
        }
        if (isset($columns[$sort])) {
            $columns[$sort]['sortable'] = $dir;
        }

        $filter = new join_filter('id', clusterassignment::TABLE, 'clusterid',
                                  new AND_filter(array(new field_filter('plugin', 'manual', field_filter::NEQ),
                                                       new field_filter('userid', $id))),
                                  false, false);
        $items = userset::find($filter, array($sort => $dir), $page * $perpage,
                               $perpage);
        $count = userset::count($filter);

        echo html_writer::tag('h2', get_string('auto_assigned_usersets', 'usersetenrol_manual'));

        $full_url = "/elis/program/index.php?s={$this->pagename}&amp;section={$this->section}&amp;id={$id}&amp;sort={$sort}&amp;dir={$dir}&amp;mapage={$mapage}&amp;maperpage={$maperpage}&amp;aaperpage={$perpage}";
        $pagingbar = new paging_bar($count, $page, $perpage, $full_url, 'aapage');
        echo $OUTPUT->render($pagingbar);

        $this->print_list_view($items, $columns, 'clusters');
    }

    function print_manuallyassigned_table() {
        global $DB, $OUTPUT;

        $id = $this->required_param('id', PARAM_INT);
        $sort = $this->optional_param('sort', 'name', PARAM_ALPHA);
        $dir = $this->optional_param('dir', 'ASC', PARAM_ALPHA);
        $page  = $this->optional_param('mapage', 0, PARAM_INT);
        $perpage  = $this->optional_param('maperpage', 20, PARAM_INT);
        $aapage  = $this->optional_param('aapage', 0, PARAM_INT);
        $aaperpage  = $this->optional_param('aaperpage', 20, PARAM_INT);

        $columns = array(
            'name'        => array('header' => get_string('name', 'elis_program'),
                                   'decorator' => array(new record_link_decorator('usersetpage',
                                                                                  array('action'=>'view'),
                                                                                  'clusterid'),
                                                        'decorate')),
            'display'     => array('header' => get_string('description', 'elis_program')),
            'manage'      => array('header' => ''),
        );

        if ($dir !== 'DESC') {
            $dir = 'ASC';
        }
        if (isset($columns[$sort])) {
            $columns[$sort]['sortable'] = $dir;
        }

        $filter = new join_filter('id', clusterassignment::TABLE, 'clusterid',
                                  new AND_filter(array(new field_filter('plugin', 'manual'),
                                                       new field_filter('userid', $id))));

        $filter = new AND_filter(array(new field_filter('plugin', 'manual'),
                                       new field_filter('userid', $id)));

        $filtersql = $filter->get_sql(false, 'ca');
        $sql = 'SELECT ca.id, u.id AS clusterid, u.name, u.display
                FROM {' . clusterassignment::TABLE . '} ca
                JOIN {' . userset::TABLE . "} u ON ca.clusterid = u.id
                WHERE {$filtersql['where']}
                ORDER BY {$sort} {$dir}";
        $totalitems = $DB->get_records_sql($sql, $filtersql['where_parameters']);
        $items = $DB->get_records_sql($sql, $filtersql['where_parameters'],
                       $page * $perpage, $perpage);

        echo html_writer::tag('h2', get_string('manually_assigned_usersets', 'usersetenrol_manual'));

        $full_url = "/elis/program/index.php?s={$this->pagename}&amp;section={$this->section}&amp;id={$id}&amp;sort={$sort}&amp;dir={$dir}&amp;aapage={$aapage}&amp;aaperpage={$aaperpage}&amp;maperpage={$perpage}";
        $pagingbar = new paging_bar(count($totalitems), $page, $perpage, $full_url, 'mapage');
        echo $OUTPUT->render($pagingbar);

        $this->print_list_view($items, $columns, 'clusters');

        $this->print_dropdown(cluster_get_listing('name', 'ASC', 0, 0, '', '', array(), $id), $totalitems, 'userid', 'clusterid', 'add');
    }
}

class clusteruserpage extends userclusterbasepage {
    var $pagename = 'clstusr';
    var $tab_page = 'usersetpage';
    //var $default_tab = 'clusteruserpage';

    var $parent_data_class = 'userset';

    function can_do_default() {
        $id = $this->required_param('id', PARAM_INT);
        return usersetpage::_has_capability('elis/program:userset_view', $id);
    }

    function can_do_add() {
        $userid = $this->required_param('userid');
        $clustid = $this->required_param('clusterid');
        return self::can_manage_assoc($userid, $clustid);
    }

    function can_do_delete() {
        $aid = $this->optional_param('association_id', '', PARAM_INT);

        $clustass = new clusterassignment($aid);
        return self::can_manage_assoc($clustass->userid, $clustass->clusterid);
    }

    function can_do_edit() {
        return $this->can_do_delete();
    }

    function display_default() {
        $id = $this->required_param('id', PARAM_INT);

        $this->print_autoassigned_table();
        echo '<br/>';
        $this->print_manuallyassigned_table();
    }

    function print_autoassigned_table() {
        global $OUTPUT;

        $id    = $this->required_param('id', PARAM_INT);
        $sort  = $this->optional_param('sort', 'lastname', PARAM_ALPHA);
        $dir   = $this->optional_param('dir', 'ASC', PARAM_ALPHA);
        $page  = $this->optional_param('aapage', 0, PARAM_INT);
        $perpage  = $this->optional_param('aaperpage', 20, PARAM_INT);
        $mapage  = $this->optional_param('mapage', 0, PARAM_INT);
        $maperpage  = $this->optional_param('maperpage', 20, PARAM_INT);

        $decorator = new record_link_decorator('userpage', array('action'=>'view'), 'id');
        $columns = array(
            'idnumber'    => array('header' => get_string('idnumber', 'elis_program'),
                                   'decorator'   => array($decorator, 'decorate')),
            'name'        => array('header' => array('firstname' => array('header' => get_string('firstname')),
                                                      'lastname' => array('header' => get_string('lastname'))),
                                   'decorator'   => array($decorator, 'decorate'),
                                   'display_function' => array('display_table', 'display_user_fullname_item')),
            'email'       => array('header' => get_string('email'))
        );

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

        $AND_filter_array = array(new field_filter('plugin', 'manual', field_filter::NEQ),
                                  new field_filter('clusterid', $id));


        $filter = new join_filter('id', clusterassignment::TABLE, 'userid',
                                  new AND_filter($AND_filter_array),
                                  false, false);

        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $filter = new AND_filter(array($filter, new field_filter('inactive', 0)));
        }

        $items = user::find($filter, array($sort => $dir), $page * $perpage,
                            $perpage);
        $count = user::count($filter);

        echo html_writer::tag('h2', get_string('auto_assigned_users', 'usersetenrol_manual'));

        $full_url = "/elis/program/index.php?s={$this->pagename}&amp;section={$this->section}&amp;id={$id}&amp;sort={$sort}&amp;dir={$dir}&amp;mapage={$mapage}&amp;maperpage={$maperpage}&amp;aaperpage={$perpage}";
        $pagingbar = new paging_bar($count, $page, $perpage, $full_url, 'aapage');
        echo $OUTPUT->render($pagingbar);

        $a = new stdClass;
        $a->num = $count;
        echo html_writer::tag('div', get_string('items_found', 'elis_program', $a), array('style' => 'text-align:right'));

        $this->print_list_view($items, $columns, 'users');
    }

    function print_manuallyassigned_table() {
        global $DB, $OUTPUT;

        $id    = $this->required_param('id', PARAM_INT);
        $sort  = $this->optional_param('sort', 'lastname', PARAM_ALPHA);
        $dir   = $this->optional_param('dir', 'ASC', PARAM_ALPHA);
        $page  = $this->optional_param('mapage', 0, PARAM_INT);
        $perpage  = $this->optional_param('maperpage', 20, PARAM_INT);
        $aapage  = $this->optional_param('aapage', 0, PARAM_INT);
        $aaperpage  = $this->optional_param('aaperpage', 20, PARAM_INT);

        $decorator = new record_link_decorator('userpage', array('action'=>'view'), 'userid');
        $columns = array(
            'idnumber'    => array('header' => get_string('idnumber', 'elis_program'),
                                   'decorator'   => array($decorator, 'decorate')),
            'name'        => array('header' => array('firstname' => array('header' => get_string('firstname')),
                                                      'lastname' => array('header' => get_string('lastname'))),
                                   'decorator'   => array($decorator, 'decorate'),
                                   'display_function' => array('display_table', 'display_user_fullname_item')),
            'email'       => array('header' => get_string('email')),
            'manage'      => array('header' => ''),
        );

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

        $AND_filter_array = array(new field_filter('plugin', 'manual'),
                                  new field_filter('clusterid', $id));

        $filter = new AND_filter($AND_filter_array);
        $filtersql = $filter->get_sql(false, 'ca');
        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            if (!empty($filtersql['where'])) { // TBD: should be an 'else'!!!
                $filtersql['where'] .= ' AND u.inactive = 0 ';
            }
        }

        //todo: find a more elegant way to implement this - currently have to use
        //queries instead of find and count methods because we don't have a good way to
        //select specific fields across two tables

        //NOTE: can't just user user::find and user::count here because we need the assignment
        //id for permissions checking
        $sql = 'SELECT ca.id, u.id AS userid, u.idnumber, u.firstname, u.lastname, u.email
                  FROM {' . clusterassignment::TABLE . '} ca
                  JOIN {' . user::TABLE . "} u ON ca.userid = u.id
                 WHERE {$filtersql['where']}
              ORDER BY u.{$sort} {$dir}";
        $items = $DB->get_recordset_sql($sql, $filtersql['where_parameters'],
                         $page * $perpage, $perpage);

        $sql = 'SELECT COUNT(*)
                  FROM {' . clusterassignment::TABLE . '} ca
                  JOIN {' . user::TABLE . "} u ON ca.userid = u.id
                 WHERE {$filtersql['where']}";
        $count = $DB->count_records_sql($sql, $filtersql['where_parameters']);

        echo html_writer::tag('h2', get_string('manually_assigned_users', 'usersetenrol_manual'));

        $full_url = "/elis/program/index.php?s={$this->pagename}&amp;section={$this->section}&amp;id={$id}&amp;sort={$sort}&amp;dir={$dir}&amp;aapage={$aapage}&amp;aaperpage={$aaperpage}&amp;maperpage={$perpage}";
        $pagingbar = new paging_bar($count, $page, $perpage, $full_url, 'mapage');
        echo $OUTPUT->render($pagingbar);

        $a = new stdClass;
        $a->num = $count;
        echo html_writer::tag('div', get_string('items_found', 'elis_program', $a), array('style' => 'text-align:right'));

        $this->print_list_view($items, $columns, 'users');

        $this->print_assign_link();
    }

    function print_assign_link() {
        global $OUTPUT;
        $id = $this->required_param('id', PARAM_INT);

        $target = new clusteruserselectpage(array('id' => $id));
        if ($target->can_do()) {
            echo html_writer::empty_tag('br');
            $button = new single_button($target->url, get_string('assign_users', 'usersetenrol_manual'), 'get');
            //echo html_writer::tag('div', html_writer::link($target->url, get_string('assign_users', 'usersetenrol_manual')), array('align' => 'center'));
            echo html_writer::tag('div', $OUTPUT->render($button), array('align' => 'center'));
        }
    }
}
