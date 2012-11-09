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

require_once elispm::lib('data/pmclass.class.php');
require_once elispm::lib('data/track.class.php');
require_once elispm::lib('page.class.php');
require_once elispm::lib('associationpage.class.php');
require_once elispm::file('trackpage.class.php');
require_once elispm::file('pmclasspage.class.php');
require_once elispm::file('form/trackassignmentform.class.php');

class trackassignmentpage extends associationpage {
    var $data_class = 'trackassignment';
    var $form_class = 'trackassignmentform';
    var $parent_data_class = 'track';

    var $pagename = 'trkcls';
    var $tab_page = 'trackpage';
    //var $default_tab = 'trackassignmentpage';

    var $section = 'curr';

    function __construct(array $params=null) {
        parent::__construct($params);

        $this->tabs = array(
            array('tab_id' => 'view', 'page' => 'trackassignmentpage', 'params' => array('action' => 'edit'), 'name' => get_string('edit','elis_program'), 'showtab' => true, 'showbutton' => true, 'image' => 'edit'),
            array('tab_id' => 'edit', 'page' => 'trackassignmentpage', 'params' => array('action' => 'delete'), 'name' => get_string('delete','elis_program'), 'showtab' => true, 'showbutton' => true, 'image' => 'delete'),

        );
    }

    public static function get_contexts($capability) {
        if (!isset(trackassignmentpage::$contexts[$capability])) {
            global $USER;
            trackassignmentpage::$contexts[$capability] = get_contexts_by_capability_for_user('track', $capability, $USER->id);
        }
        return trackassignmentpage::$contexts[$capability];
    }

    function can_do_default() {
        $id = $this->required_param('id', PARAM_INT);

        if (trackpage::_has_capability('elis/program:track_view', $id)) {
            //allow viewing but not managing associations
        	return true;
        }

        return trackpage::_has_capability('elis/program:associate', $id);
    }

    function can_do_savenew() {
        // the user must have 'elis/program:associate' permissions on both ends
        $trackid = $this->required_param('trackid', PARAM_INT);
        $classid = $this->required_param('classid', PARAM_INT);

        return trackpage::_has_capability('elis/program:associate', $trackid)
            && pmclasspage::_has_capability('elis/program:associate', $classid);
    }

    function can_do_edit() {
        // the user must have 'elis/program:associate' permissions on both
        // ends
        $association_id = $this->required_param('association_id', PARAM_INT);
        $record = new trackassignment($association_id);
        $trackid = $record->trackid;
        $classid = $record->classid;

        return trackpage::_has_capability('elis/program:associate', $trackid)
            && pmclasspage::_has_capability('elis/program:associate', $classid);
    }

    function can_do_delete() {
        return $this->can_do_edit();
    }

    function display_add() {
        // TODO: update
        $trackid = required_param('trackid', PARAM_INT);
        $clsid = required_param('clsid', PARAM_INT);
        $id = required_param('id', PARAM_INT);

        $target = $this->get_new_page(array('action' => 'add', 'id' => $id, 'trackid' => $trackid, 'clsid' => $clsid));

        $form = new $this->form_class($target->url, array('trackid' => $trackid, 'classid' => $clsid));

        $form->set_data(array('trackid' => $trackid, 'classid' => $clsid, 'id' => $id));

        $form->display();
    }

    function display_default() {
        global $DB, $OUTPUT;

        $id = required_param('id', PARAM_INT);

        $sort         = optional_param('sort', 'clsname', PARAM_ALPHA);
        $dir          = optional_param('dir', 'ASC', PARAM_ALPHA);

        $page         = optional_param('page', 0, PARAM_INT);
        $perpage      = optional_param('perpage', 30, PARAM_INT);        // how many per page

        $namesearch   = trim($this->optional_param('search', '', PARAM_ALPHA));
        $alpha        = $this->optional_param('alpha', '', PARAM_ALPHA);

        $columns = array(
            'clsname'   => array('header'=> get_string('class_idnumber', 'elis_program'),
                                 'decorator' => array(new record_link_decorator('pmclasspage',
                                                                                array('action'=>'view'),
                                                                                'clsid'),
                                                      'decorate')),
            'autoenrol' => array('header'=> get_string('track_auto_enrol', 'elis_program')),
            'enrolments' => array('header'=> get_string('enrolments', 'elis_program')),
            'buttons' => array('header'=> ''),
        );

        // TBD
        if ($dir !== 'DESC') {
            $dir = 'ASC';
        }
        if (isset($columns[$sort])) {
            $columns[$sort]['sortable'] = $dir;
        } else {
            $sort = 'clsname';
            $columns[$sort]['sortable'] = $dir;
        }

        $totalitems = track_assignment_get_listing($id);
        $items = track_assignment_get_listing($id, $sort, $dir, $page*$perpage, $perpage, $namesearch, $alpha);
        $numitems = track_assignment_count_records($id, $namesearch, $alpha);

        $this->print_alpha();
        $this->print_search();

        if ($numitems > $perpage) {
            $pagingbar = new paging_bar($numitems, $page, $perpage,
                             "index.php?s=trkcls&amp;id={$id}&amp;sort={$sort}&amp;dir={$dir}&amp;perpage={$perpage}&amp;alpha={$alpha}&amp;search="
                             . urlencode($namesearch)); // .'&amp;'
            echo $OUTPUT->render($pagingbar), '<br/>'; // TBD
        }
        $this->print_num_items($numitems);
        $this->print_list_view($items, $columns, 'track_classes');

        if (empty($items) && empty($namesearch) && empty($alpha)) {
            echo '<div align="center">';
            $tmppage = new trackassignmentpage(array('action'=>'autocreate', 'id'=>$id));
            //print_single_button(null, $tmppage->get_moodle_url()->params, get_string('track_autocreate_button', 'elis_program'));
            $button = new single_button($tmppage->url, get_string('track_autocreate_button','elis_program'), 'get');
            echo $OUTPUT->render($button);
            echo '</div>';
        }

        $contexts = pmclasspage::get_contexts('elis/program:associate');
        $filter_object = $contexts->get_filter('cls.id', 'class');
        $filter_sql = $filter_object->get_sql(false, 'cls');
        // find the classes that are part of a course that is part of a
        // curriculum that the track belongs to
        $sql = "SELECT cls.*
                  FROM {".track::TABLE."} trk
                  JOIN {".curriculum::TABLE."} cur ON cur.id = trk.curid
                  JOIN {".curriculumcourse::TABLE."} curcrs ON curcrs.curriculumid = cur.id
                  JOIN {".pmclass::TABLE."} cls ON cls.courseid = curcrs.courseid
                 WHERE trk.id = ?";
        $params = array($id);
        if (isset($filter_sql['where'])) {
            $sql .= " AND ".$filter_sql['where'];
            $params += $filter_sql['where_parameters'];
        }

        $classes = $DB->get_records_sql($sql, $params);
        if (empty($classes)) {
            $sql = "SELECT COUNT(*)
                      FROM {".track::TABLE."} trk
                      JOIN {".curriculum::TABLE."} cur ON cur.id = trk.curid
                      JOIN {".curriculumcourse::TABLE."} curcrs ON curcrs.curriculumid = cur.id
                      JOIN {".pmclass::TABLE."} cls ON cls.courseid = curcrs.courseid
                     WHERE trk.id = ?";
            $params = array($id);
            $num_classes = $DB->count_records_sql($sql, $params);
            if (!empty($num_classes)) {
                // some classes exist, but don't have associate capability on
                // any of them
                echo '<div align="center"><br />';
                print_string('no_associate_caps_class', 'elis_program');
                echo '</div>';
            } else {
                // no curricula at all
                echo '<div align="center"><br />';
                print_string('all_items_assigned', 'elis_program');
                echo '</div>';
            }
        } else {
            $this->print_dropdown($classes, $totalitems, 'trackid', 'clsid', 'add', 'idnumber');
        }
    }

    function create_table_object($items, $columns) {
        return new trackassignment_page_table($items, $columns, $this);
    }

    function do_autocreate() { // TBD: display_autocreate() for error messages?
        $id = required_param('id', PARAM_INT);

        $track = new track($id);
        $track->track_auto_create();

        $tmppage = new trackassignmentpage(array('id' => $id));
        redirect($tmppage->url, get_string('track_success_autocreate','elis_program'));
    }

    function display_enrolall() {
        // ELIS-3761: changed from do_enrolall()
        // since enrol_all_track_users_in_class() outputs message(s)!
        $id = required_param('id', PARAM_INT);
        $aid = required_param('association_id', PARAM_INT);

        $trackassignment = new trackassignment($aid);
        $trackassignment->enrol_all_track_users_in_class();

        $tmppage = new trackassignmentpage(array('id' => $id));
        redirect($tmppage->url, '', 15);
    }
}

class trackassignment_page_table extends association_page_table {
    function __construct(&$items, $columns, $page) {
        $id = required_param('id', PARAM_INT);
        $users = usertrack::get_users($id);
        $this->numusers = empty($users) ? 0 : count($users);

        parent::__construct($items, $columns, $page);
    }

    function get_item_display_autoenrol($column, $item) {
        return $this->display_yesno_item($column, $item);
    }

    function get_item_display_enrolments($column, $item) {
        if (empty($item->enrolments)) {
            $item->enrolments = 0;
        }
        return "{$item->enrolments} / {$this->numusers}";
    }
}
