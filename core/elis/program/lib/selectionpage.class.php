<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
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
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once $CFG->libdir . '/weblib.php';
require_once elis::lib('table.class.php');
require_once elispm::lib('lib.php');
require_once elispm::lib('page.class.php');
require_once elispm::file('form/selectionform.class.php');

//require_once "{$CFG->libdir}/pear/HTML/AJAX/JSON.php";

abstract class selectionpage extends pm_page { // TBD
    const LANG_FILE = 'elis_program';

    var $_basepage;

    /**
     * The name of the class used for data objects
     */
    var $data_class;

    /**
     * The name of the class used for the add/edit form
     */
    var $form_class;

    var $tabs = array(); // TBD

    var $_form;

    /*
     * for AJAX calls:
     */
    function is_bare() {
        $mode = $this->optional_param('mode', '', PARAM_ACTION);
        return $mode == 'bare';
    }

    function print_header($_) {
        if (!$this->is_bare()) {
            parent::print_header($_);
            $this->print_tabs();
        }
    }

    function print_footer() {
        if (!$this->is_bare()) {
            return parent::print_footer();
        }
    }

    function print_tabs() {
        $id = $this->required_param('id', PARAM_INT);

        $page = $this; // TBD: $this->get_tab_page();
        $params = array('id' => $id);
        $rows = array(); // TBD was: = $row; -> Undefined variable error!
        $row = array();

        if (!empty($this->tab_page)) {
            $tabpage = new $this->tab_page();
            $tabpage->print_tabs($this->default_tab, array('id' => $id));
        }

        // main row of tabs
        foreach($page->tabs as $tab) {
            $tab = $page->add_defaults_to_tab($tab);
            if($tab['showtab'] === true) {
                $target = new $tab['page'](array_merge($tab['params'], $params));
                $row[] = new tabobject($tab['tab_id'], $target->url, $tab['name']);
            }
        }
        if (!empty($row)) {
            $rows[] = $row;
        }

        if (!empty($this->assign)) {
            // assigned/unassigned tabs
            $assignedpage = $this->get_new_page(array('id' => $id, 'mode' => 'assign')); // WAS: $this->get_basepage();
            //$unassignedpage = clone($assignedpage);
            //$unassignedpage->params['mode'] = 'unassign';
            $unassignedpage = $this->get_new_page(array('id' => $id, 'mode' => 'unassign'));
            $row = array(new tabobject('assigned', $assignedpage->url,
                                 get_string('assigned', self::LANG_FILE)),
                         new tabobject('unassigned', $unassignedpage->url,
                                 get_string('unassigned', self::LANG_FILE)));
            $rows[] = $row;
        }

        print_tabs($rows, $this->optional_param('mode', 'unassign', PARAM_ACTION) == 'assign' ? 'assigned' : 'unassigned', array(), array(get_class($this)));
    }

    protected function get_selection_form() {
        return new selectionform();
    }

    /**
     * Method to send back to AJAX request the selected checkboxes in SESSION array
     * outputs a comma-separated list of checkbox ids
     */
    function do_get_checkbox_selection() {
        global $SESSION;
        $id = optional_param('id', 0, PARAM_INT);
        $pagename = $this->page_identity($id);
        //error_log("selectionpage.class.php::do_get_checkbox_selection(): pagename = {$pagename}");
        if (isset($SESSION->selectionpage[$pagename])) {
            $selectedcheckboxes = $SESSION->selectionpage[$pagename];
            if (is_array($selectedcheckboxes)) {
                echo implode(',', $selectedcheckboxes);
            }
        }
    }

    // Store the checkbox selection into a session
    function do_checkbox_selection_session() {
        global $SESSION;
        $selection = optional_param('selected_checkboxes', '', PARAM_CLEAN);
        $id = optional_param('id', 0, PARAM_INT);
        $selectedcheckboxes = json_decode($selection);

        if (is_array($selectedcheckboxes)) {
            // Identify the page that the session is for
            $pagename = $this->page_identity($id);

            if (!isset($SESSION->selectionpage[$pagename])) {
                $SESSION->selectionpage[$pagename] = array();
            }

            $SESSION->selectionpage[$pagename] = $selectedcheckboxes;
        }
    }

    // Retrieve a unique page name identified by the page name, id and action
    function page_identity($id = 1) {
        $pagename = $this->pagename;
        if (method_exists($this, 'is_assigning')) {
            if ($this->is_assigning()) {
                $pagename = $this->pagename . $id . 'is_assigning';
            } else {
                $pagename = $this->pagename . $id . 'is_not_assigning';
            }
        }
        return $pagename;
    }

    // Remove all session data for a given page
    function session_selection_deletion() {
        global $SESSION;
        $id = optional_param('id', 0, PARAM_INT);

        $pagename = $this->page_identity($id);

        if (isset($SESSION->selectionpage[$pagename])) {
            unset($SESSION->selectionpage[$pagename]);
        }
    }

    function display_default() { // action_default
        $form = $this->get_selection_form();
        $this->url->remove_params(array('mode')); // TBD
        $baseurl = htmlspecialchars_decode($this->url);

        $this->do_checkbox_selection_session();

        if ($data = $form->get_data()) {
            $this->session_selection_deletion();
            $selection = json_decode($data->_selection);
            $selection = $selection ? $selection : array();
            if (!is_array($selection)) {
                print_error('form_error', self::LANG_FILE, $baseurl);
            }
            $data->_selection = $selection;
            $this->process_selection($data);
            return;
        } else if (($showselection = $this->optional_param('_showselection','',PARAM_RAW))) {
            $baseurl .= "&_showselection=$showselection";
            $selection = json_decode($showselection);
            $selection = $selection ? $selection : array();
            if (!is_array($selection)) {
                print_error('form_error', self::LANG_FILE, $baseurl);
            }

            if (!empty($selection)) {
                $records = $this->get_records_from_selection($selection);
            } else {
                $records = array();
            }
            $count = count($selection);
            $form = null;
            $filter = null;
        } else {
            $this->init_selection_form($form);

            $filter = $this->get_selection_filter();
            list($records, $count) = $this->get_records($filter);
        }

        $records = (is_array($records) || ($records instanceof Iterator && $records->valid())) ? $records : array();
        $table = $this->create_selection_table($records, $baseurl);
        $this->print_js_selection_table($table, $filter, $count, $form, $baseurl);
    }

    /**
     * Method to be implemented by child classes to display filter settings
     *
     * @param  int   $count  number of items matching filter
     * @param  array $filter the filter array
     */
    protected function showfilter($count, $filter) {
    }

    protected function print_js_selection_table($table, $filter, $count, $form, $baseurl) {
        global $CFG, $OUTPUT, $PAGE, $SESSION, $DB;

        if (!$this->is_bare()) {
            $title_sid = 'breadcrumb_'. get_class($this);
            if (method_exists($this, 'is_assigning') && !$this->is_assigning() &&
                get_string_manager()->string_exists($title_sid .'_unassign', self::LANG_FILE)) {
                $title_sid .= '_unassign';
            }
            if (get_string_manager()->string_exists($title_sid, self::LANG_FILE)) {
                $title = get_string($title_sid, self::LANG_FILE);
            } else {
                $title = get_string('select');
            }

            echo "<script>var basepage='$baseurl';</script>";
            // ***TBD***
            //$PAGE->requires->yui2_lib(array('yahoo', 'dom', 'event', 'connection'));
            $PAGE->requires->js('/elis/core/js/associate.class.js');
            $PAGE->requires->js('/elis/program/js/checkbox_selection.js');
            echo '<div class="mform" style="width: 100%"><fieldset><legend>'.
                 $title .'</legend><div id="list_display">';
        } else {
            // ELIS-3643: see reporting changes for ELIS-3679 ...
            $PAGE->set_pagelayout('embedded');
        }

        $id      = $this->optional_param('id', 0, PARAM_INT);
        $pagenum = $this->optional_param('page', 0, PARAM_INT);
        $perpage = $this->optional_param('perpage', 30, PARAM_INT);

        if ($filter != null) {
            $this->print_selection_filter($filter);
        }

        // pager
        $action = $this->optional_param('action', '', PARAM_ACTION);
        $assign = $this->optional_param('_assign', '', PARAM_CLEAN);
        $pgurl = $this->get_basepage()->url;
        $pgurl->remove_params(array('mode'));
        $pgurl .= ($id >= 0) ? "&amp;id=$id" : '';
        $pgurl .= $action ? "&amp;action=$action" : '';
        $pgurl .= $assign ? "&amp;_assign=$assign" : ''; // curstu & associationpage2
        $pagingbar = new paging_bar($count, $pagenum, $perpage, $pgurl); // TBD: '&amp;'
        echo $OUTPUT->render($pagingbar);

        $this->showfilter($count, $filter);

        echo '<div style="float: right">';
        $label = null;
        if (!empty($this->data_class)) {
            $label = 'num_'. $this->data_class .'_found';
            if (!get_string_manager()->string_exists($label, self::LANG_FILE)) {
                error_log("/elis/program/lib/selectionpage.class.php:: string '{$label}' not found.");
                $label = null;
            }
        }
        if (empty($label)) {
            $label = 'num_user_found'; // default if no 'num_{data_class}_found'
        }
        $this->print_record_count($count, $label);
        echo '</div>';

        $pagename = $this->page_identity($id);

        if(isset($SESSION->selectionpage[$pagename])) {
            $selectedcheckboxes = $SESSION->selectionpage[$pagename];
            if (is_array($selectedcheckboxes)) {
                $filtered = array();
                foreach ($selectedcheckboxes as $id) {
                    // ELIS-6431: removed check that id is valid userid (to fix waitlistpage)
                    $filtered[] = $id;
                }
                $selection  = implode(',', $filtered);
                echo '<input type="hidden" id="selected_checkboxes" value="' . $selection .'" /> ';
            }
        }

        if ($count) {
            // select all button
            echo '<input type="button" onclick="checkbox_select(true)" value="'.get_string('selectall').'" /> ';
            echo '<input type="button" onclick="checkbox_select(false)" value="'.get_string('deselectall').'" /> ';
            // table
            echo $table->get_html();
        }

        if (!$this->is_bare()) {
            echo '</div>';
            //if ($count)
            {
                $sparam1 = new stdClass;
                $sparam1->num = '<span id="numselected">0</span>';
                $sparam2 = new stdClass;
                $sparam2->num = '<span id="numonotherpages">0</span>';

                echo '<div align="center">'.get_string('numselected', self::LANG_FILE, $sparam1).'<span id="selectedonotherpages" style="display: none"> ('.get_string('num_not_shown', self::LANG_FILE, $sparam2).')</span></div>';
                echo '<div align="center">';
                _print_checkbox('selectedonly', '', false, get_string('selectedonly', self::LANG_FILE), '', 'change_selected_display()');
                echo '</div>';
            }
            echo $this->get_table_footer();
            echo '</fieldset></div>'; // from above
            //if ($count)
            {
                $form->display();
            }
        } else {
            // ELIS-3643: see reporting changes for ELIS-3679 ...
            $jscall = '
<script type="text/javascript">
//<![CDATA[
M.form.dependencyManager = null;
//]]>
</script>
'
                      . $this->requires->get_end_code();
            echo $jscall;
        }
    }

    /**
     * Default table footer method, defaults to none
     * overload in child pages requiring table footer, i.e. rolepages
     * @return string
     */
    protected function get_table_footer() {
        return '';
    }

    /**
     * Get the base parameters for the association page
     * @return array
     */
    protected function get_base_params() {
        $data = array();
        return $data;
    }

    /**
     * Get a base page object.
     * @return object
     */
    protected function get_basepage() {
        if (!isset($this->_basepage)) { // TBD: see class property $_basepage;
            $this->_basepage = clone($this);
            //$this->_basepage->params = $this->get_base_params();
        }
        return $this->_basepage;
    }

    /**
     * Initializes the selection form (sets parameters)
     */
    protected function init_selection_form(&$form) {
        //$params = (object) $this->get_basepage()->get_moodle_url()->params;
        $params = (object)$this->_get_page_params();
        $form->set_data($params);
    }

    /**
     * Process the form submission for selection.
     * @param array $data form data.  $data->selection is an array of IDs of
     * records that were selected
     */
    abstract protected function process_selection($data);

    /**
     * Gets the selection filter
     * @return object filter object to be used by print_selection_filter and get_assigned_records.
     */
    protected function get_selection_filter() {
        return null;
    }

    protected function print_selection_filter($filter) {
    }

    /**
     * @return array records, and a count
     */
    abstract protected function get_records($filter);

    abstract protected function get_records_from_selection($record_ids);

    protected function print_record_count($count, $label = null) {
        $sparam = new stdClass;
        $sparam->num = $count;
        print_string(empty($label) ? 'items_found' : $label, self::LANG_FILE,
                     $sparam);
    }

    abstract protected function create_selection_table($records, $baseurl);
}

/**
 * Table to display the selection list.  When creating a new table, it should
 * have a column called '_selection'.
 */
class selection_table extends display_table {
    function __construct(&$items, $columns, $pageurl, $decorators = array()) {
        if (isset($columns['_selection']) && is_array($columns['_selection'])) {
            $columns['_selection']['sortable'] = false;
            $columns['_selection']['display_function'] =
                        array(&$this, 'get_item_display__selection');
        }
        //$this->table->id = 'selectiontable';
        parent::__construct($items, $columns, $pageurl, 'sort', 'dir',
                            array('id' => 'selectiontable'));
    }

    function is_sortable__selection() {
        return false;
    }

    function get_item_display__selection($column, $item) {
        return html_writer::checkbox('select'.$item->id, '', isset($this->checked[$item->id]), '', array('onclick' => 'select_item("'.$item->id.'")'));
    }
}

