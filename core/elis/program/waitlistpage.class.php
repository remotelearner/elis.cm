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

require_once elispm::lib('lib.php');
require_once elispm::lib('data/pmclass.class.php');
require_once elispm::lib('data/student.class.php');
require_once elispm::lib('data/waitlist.class.php');
require_once elispm::lib('selectionpage.class.php');
require_once elispm::file('pmclasspage.class.php');
require_once elispm::file('studentpage.class.php');
require_once elispm::file('form/waitlistform.class.php');

class waitlistpage extends selectionpage {
    const LANG_FILE = 'elis_program';

    var $data_class  = 'waitlist';
    var $pagename    = 'wtg';
    var $tab_page    = 'pmclasspage';  // see: selectionpage::print_tabs()
    var $default_tab = 'waitlistpage'; // "    "
    var $section     = 'curr';         // TBD: 'curr'
    var $assign      = null;

    public function __construct(array $params = null) {
        parent::__construct($params);
    }

    function can_do_default() {
        $id = $this->required_param('id', PARAM_INT);
        $pmclasspage = new pmclasspage(array('id' => $id));
        return $pmclasspage->can_do('edit');
    }

    /**
     * Constructs navigational breadcrumbs
     */
    function build_navbar_default($who = null) {
        $id = $this->required_param('id', PARAM_INT);
        $classpage = new pmclasspage(array('id' => $id));
        $classpage->build_navbar_view();
        $this->_navbar = $classpage->navbar;
    }

    function get_page_title_default() {
        //this is similar to what associationpage does
        $id = $this->required_param('id', PARAM_INT);
        $tabpage = $this->get_tab_page(array('action' => 'view', 'id' => $id));
        return $tabpage->get_page_title() . ': ' . get_string('breadcrumb_waitlistpage', self::LANG_FILE);
    }

    protected function get_selection_form() {
        return new waitlisteditform();
    }

    function get_selection_filter() {
        $alpha      = $this->optional_param('alpha', '', PARAM_ALPHA);
        $namesearch = trim($this->optional_param('search', '', PARAM_CLEAN));
        return array('alpha' => $alpha, 'namesearch' => $namesearch);
    }

    function print_selection_filter($filter) {
        $id = $this->required_param('id', PARAM_INT);
        pmalphabox(new moodle_url($this->_get_page_url(),
                           array('s' => $this->pagename, 'id' => $id)),
                   'alpha', get_string('tag_name', self::LANG_FILE) .':');
        pmsearchbox($this, 'search', 'get',
                    get_string('show_all_users', self::LANG_FILE));
    }

    protected function showfilter($count, $filter) {
        if (!$count &&
            (!empty($filter['alpha']) || !empty($filter['namesearch']))) {
            $nomatchlabel = null;
            if (!empty($this->data_class)) {
                $nomatchlabel = 'no_'. $this->data_class .'_matching';
                if (!get_string_manager()->string_exists($nomatchlabel, self::LANG_FILE)) {
                    error_log("/elis/program/lib/selectionpage.class.php:: string '{$nomatchlabel}' not found.");
                    $nomatchlabel = null;
                }
            }
            pmshowmatches($filter['alpha'], $filter['namesearch'], null, $nomatchlabel);
        }
    }

    function get_records($filter) {
        $sort    = $this->optional_param('sort', 'timecreated', PARAM_CLEAN);
        $dir     = $this->optional_param('dir', 'ASC', PARAM_CLEAN);
        $page    = $this->optional_param('page', 0, PARAM_INT);
        $perpage = $this->optional_param('perpage', 30, PARAM_INT); // how many per page
        $id      = $this->required_param('id', PARAM_INT);

        if ($sort == 'name') {
            $sort = 'lastname';
        }

        $items = waitlist::get_students($id, $sort, $dir, $page * $perpage, $perpage, $filter['namesearch'], $filter['alpha']);
        $numitems = waitlist::count_records($id, $filter['namesearch'], $filter['alpha']);
        //error_log("waitlistpage::get_records(): count(items) = ". count($items). ", numitems = {$numitems}");
        return array($items, $numitems);
    }

    function get_records_from_selection($selection) {
        global $DB;
        $id = $this->required_param('id', PARAM_INT);
        $sort = $this->optional_param('sort', 'timecreated', PARAM_CLEAN);
        $dir = $this->optional_param('dir', 'ASC', PARAM_CLEAN);
        if ($sort == 'name') {
            $sort = 'lastname';
        }
        $FULLNAME = $DB->sql_concat('usr.firstname', "' '", 'usr.lastname');
        $sql = "SELECT watlst.id, usr.id as uid, $FULLNAME as name, usr.idnumber, usr.country, usr.language, watlst.timecreated
                  FROM {". waitlist::TABLE .'} watlst
                  JOIN {'. user::TABLE .'} usr ON watlst.userid = usr.id
                 WHERE watlst.classid = ?
                   AND watlst.id IN ('. implode(',',$selection) .")
              ORDER BY $sort $dir";
        return $DB->get_recordset_sql($sql, array($id));
    }

    function create_selection_table($records, $baseurl) {
        return new waitlist_table($records, get_pm_url($baseurl));
    }

    protected function get_base_params() {
        $params       = parent::get_base_params();
        $params['id'] = $this->required_param('id', PARAM_INT);
        $mode         = $this->optional_param('mode', '', PARAM_CLEAN);
        if (!empty($mode)) {
            $params['mode'] = $mode;
        }
        return $params;
    }

    function get_tab_page($params=array()) {
        return new $this->tab_page($params);
    }

    function process_selection($data) {
        global $OUTPUT;
        $id = $this->required_param('id', PARAM_INT);

        if (empty($data->_selection)) {
            $tmppage = new waitlistpage(array('id' => $id));
            print_error('no_items_selected', self::LANG_FILE, $tmppage->url);
        } else {
            $sparam = new stdClass;
            $sparam->num = count($data->_selection);
            $sparam->action = $data->do;
            $msg = get_string('confirm_waitlist', self::LANG_FILE, $sparam);
            echo cm_delete_form('index.php', $msg,
                         array('s' => $this->pagename,
                               'id' => $id,
                               'action' => $data->do,
                               'selected' => implode(',',$data->_selection)
                             ),
                         array('s' => $this->pagename, 'id' => $id)); // TBD
        }
    }

    function do_remove() {
        $id = $this->required_param('id', PARAM_INT);
        $recs = explode(',', $this->required_param('selected',PARAM_TEXT));

        $this->session_selection_deletion();

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
            /* $result = */ $waitlistobj->delete(); // No return code from delete()
            /* if (!$result) break; */
        }

        $tmppage = new waitlistpage(array('id' => $id));
        if ($result) {
            redirect($tmppage->url, get_string('success_waitlist_remove', self::LANG_FILE));
        } else {
            print_error('error_waitlist_remove', self::LANG_FILE, $tmppage->url);
        }
    }

    function do_overenrol() {
        $id = $this->required_param('id', PARAM_INT);
        $recs = explode(',', $this->required_param('selected', PARAM_TEXT));

        $this->session_selection_deletion();

        // make sure everything is an int
        foreach ($recs as $key => $val) {
            $recs[$key] = (int)$val;
            if (empty($recs[$key])) {
                unset($recs[$key]);
            }
        }

        $result = !empty($recs);
        foreach ($recs as $recid) {
            $waitlistobj = new waitlist($recid);
            $waitlistobj->enrol();
        }

        $tmppage = new waitlistpage(array('id' => $id));
        if ($result) {
            redirect($tmppage->url, get_string('success_waitlist_overenrol', self::LANG_FILE));
        } else {
            print_error('error_waitlist_overenrol', self::LANG_FILE, $tmppage->url);
        }
    }
}

class waitlist_table extends selection_table {
    const LANG_FILE = 'elis_program';

    function __construct(&$items, $url) {
        $sort         = optional_param('sort', 'timecreated', PARAM_CLEAN);
        $dir          = optional_param('dir', 'ASC', PARAM_CLEAN);

        $columns = array(
            '_selection'  => array('header' => get_string('select'), 'sortable' => false,
                                   'display_function' => array(&$this, 'get_item_display__selection')), // TBD
            'idnumber'    => array('header' => get_string('idnumber',        self::LANG_FILE)),
            'name'        => array('header' => get_string('name',            self::LANG_FILE)),
            'country'     => array('header' => get_string('country',         self::LANG_FILE)),
            'language'    => array('header' => get_string('user_language',   self::LANG_FILE)),
            'timecreated' => array('header' => get_string('registered_date', self::LANG_FILE),
                                   'display_function' => array(&$this, 'get_item_display_timecreated')), // TBD , ?
        );

        // set sorting
        if ($dir !== 'DESC') {
            $dir = 'ASC';
        }
        if (isset($columns[$sort])) {
            $columns[$sort]['sortable'] = $dir;
        } else {
            $sort = 'timecreated';
            $columns['name']['sortable'] = $dir;
        }

        // foreach($items as $item) $item->_selection = '';

        //$formatters = array();
        // TBD: class recordlinkformatter ???
        //$formatters['name'] = $formatters['idnumber'] = new recordlinkformatter(new usermanagementpage(), 'uid');
        parent::__construct($items, $columns, $url);
    }

    function get_item_display_timecreated($column, $item) {
        return get_date_item_display($column, $item);
    }

}

