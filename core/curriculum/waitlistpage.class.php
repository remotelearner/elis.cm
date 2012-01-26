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

require_once (CURMAN_DIRLOCATION . '/lib/selectionpage.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/page.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/cmclass.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/student.class.php');
require_once (CURMAN_DIRLOCATION . '/cmclasspage.class.php');

require_once (CURMAN_DIRLOCATION . '/lib/waitlist.class.php');
require_once (CURMAN_DIRLOCATION . '/form/waitlistform.class.php');

class waitlistpage extends selectionpage {
    var $data_class = 'waitlist';
    var $pagename = 'wtg';
    var $tab_page = 'cmclasspage';

    var $section = 'curr';

    function can_do_default() {
        $id = $this->required_param('id', PARAM_INT);
        $cmclasspage = new cmclasspage(array('id' => $id));
        return $cmclasspage->can_do('edit');
    }

    protected function get_selection_form() {
        return new waitlisteditform();
    }

    function get_selection_filter() {
        $alpha          = $this->optional_param('alpha', '', PARAM_ALPHA);
        $namesearch     = trim($this->optional_param('search', ''));
        // FIXME:
        return array('alpha' => $alpha,
                     'namesearch' => $namesearch);
    }

    function print_selection_filter($filter) {
        $alphabox = new cmalphabox($this);
        $alphabox->display();

        $searchbox = new cmsearchbox($this);
        $searchbox->display();
    }

    function get_records($filter) {
        $sort           = $this->optional_param('sort', 'timecreated');
        $dir            = $this->optional_param('dir', 'ASC');
        $page           = $this->optional_param('page', 0);
        $perpage        = $this->optional_param('perpage', 30);        // how many per page
        $id             = $this->required_param('id', PARAM_INT);

        $items = waitlist::get_students($id, $sort, $dir, $page, $perpage, $filter['namesearch'], $filter['alpha']);
        $numitems = waitlist::count_records($id, $filter['namesearch'], $filter['alpha']);

        return array($items, $numitems);
    }

    function get_records_from_selection($selection) {
        global $CURMAN;
        $id             = $this->required_param('id', PARAM_INT);
        $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');
        $sql = "SELECT watlst.id, usr.id as uid, $FULLNAME as name, usr.idnumber, usr.country, usr.language, watlst.timecreated
                  FROM {$CURMAN->db->prefix_table(WAITLISTTABLE)} watlst
                  JOIN {$CURMAN->db->prefix_table(USRTABLE)} usr ON watlst.userid = usr.id
                 WHERE watlst.classid = $id
                   AND watlst.id IN (".implode(',',$selection).')';
        return $CURMAN->db->get_records_sql($sql);
    }

    function create_selection_table($records, $baseurl) {
        return new waitlist_table($records, new moodle_url($baseurl));
    }

    protected function get_base_params() {
        $params = parent::get_base_params();
        $params['id'] = $this->required_param('id', PARAM_INT);
        return $params;
    }


    function get_tab_page($params=array()) {
        return new $this->tab_page($params);
    }

    function print_header() {
        parent::print_header();

        if (!$this->is_bare()) {
            $id = $this->required_param('id', PARAM_INT);
            $this->get_tab_page()->print_tabs(get_class($this), array('id' => $id));
        }
    }

    function process_selection($data) {
        global $CURMAN;
        $id = $this->required_param('id', PARAM_INT);

        if (empty($data->_selection)) {
            echo print_error('no_items_selected', 'block_curr_admin');
        } else {
            notice_yesno(get_string('confirm_waitlist_'.$data->do, 'block_curr_admin', count($data->_selection)),
                         'index.php', 'index.php',
                         array('s' => $this->pagename,
                               'id' => $id,
                               'action' => $data->do,
                               'selected' => implode(',',$data->_selection)
                             ),
                         array('s' => $this->pagename, 'id' => $id),
                         'POST', 'GET');
        }
    }

    function action_remove() {
        global $CURMAN;
        $id = $this->required_param('id', PARAM_INT);

        $recs = explode(',',$this->required_param('selected',PARAM_TEXT));

        // make sure everything is an int
        foreach ($recs as $key => $val) {
            $recs[$key] = (int)$val;
            if (empty($recs[$key])) {
                unset($recs[$key]);
            }
        }

        $result = true;
        foreach ($recs as $recid) {
            $waitlistobj = new waitlist($recid);
            if (!($result = $waitlistobj->delete())) {
                break;
            }
        }

        $tmppage = new waitlistpage(array('id' => $id));
        if ($result) {
            redirect($tmppage->get_url(), get_string('success_waitlist_remove', 'block_curr_admin'));
        } else {
            print_error('error_waitlist_remove', 'block_curr_admin', $tmppage->get_url());
        }
    }

    function action_overenrol() {
        global $CURMAN;
        $id = $this->required_param('id', PARAM_INT);

        $recs = explode(',',$this->required_param('selected',PARAM_TEXT));

        // make sure everything is an int
        foreach ($recs as $key => $val) {
            $recs[$key] = (int)$val;
            if (empty($recs[$key])) {
                unset($recs[$key]);
            }
        }

        $result = true;
        foreach ($recs as $recid) {
            $waitlistobj = new waitlist($recid);
            $waitlistobj->enrol();
        }

        $tmppage = new waitlistpage(array('id' => $id));
        if ($result) {
            redirect($tmppage->get_url(), get_string('success_waitlist_overenrol', 'block_curr_admin'));
        } else {
            print_error('error_waitlist_overenrol', 'block_curr_admin', $tmppage->get_url());
        }
    }
}

class waitlist_table extends selection_table {
    function __construct(&$items, $url) {
        $columns = array(
            '_selection'       => '',
            'idnumber'      => get_string('idnumber', 'block_curr_admin'),
            'name'          => get_string('name', 'block_curr_admin'),
            'country'       => get_string('country', 'block_curr_admin'),
            'language'      => get_string('user_language', 'block_curr_admin'),
            'timecreated'   => get_string('registered_date', 'block_curr_admin'),
        );
        $formatters = array();
        $formatters['name'] = $formatters['idnumber'] = new recordlinkformatter(new usermanagementpage(), 'uid');
        parent::__construct($items, $columns, $url, $formatters);
    }

    function get_item_display_timecreated($column, $item) {
        return $this->get_date_item_display($column, $item);
    }
}
