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

require_once CURMAN_DIRLOCATION . '/lib/newpage.class.php';
require_once CURMAN_DIRLOCATION . '/lib/table.class.php';
require_once CURMAN_DIRLOCATION . '/form/selectionform.class.php';
require_once "{$CFG->libdir}/pear/HTML/AJAX/JSON.php";

abstract class selectionpage extends newpage {
    /*
     * for AJAX calls:
     */
    function is_bare() {
        $mode = $this->optional_param('mode', '', PARAM_ACTION);
        return $mode == 'bare';
    }

    function print_header() {
        if (!$this->is_bare()) {
            return parent::print_header();
        }
    }

    function print_footer() {
        if (!$this->is_bare()) {
            return parent::print_footer();
        }
    }

    function print_tabs() {
        $id = $this->required_param('id', PARAM_INT);

        $page = $this->get_tab_page();
        $params = array('id' => $id);
        $rows = $row;
        $row = array();

        // main row of tabs
        foreach($page->tabs as $tab) {
            $tab = $page->add_defaults_to_tab($tab);
            if($tab['showtab'] === true) {
                $target = new $tab['page'](array_merge($tab['params'], $params));
                $row[] = new tabobject($tab['tab_id'], $target->get_url(), $tab['name']);
            }
        }
        $rows[] = $row;

        // assigned/unassigned tabs
        $assignedpage = $this->get_basepage();
        $unassignedpage = clone($assignedpage);
        $unassignedpage->params['mode'] = 'unassign';
        $row = array(new tabobject('assigned', $assignedpage->get_url(), get_string('assigned', 'block_curr_admin')),
                     new tabobject('unassigned', $unassignedpage->get_url(), get_string('unassigned', 'block_curr_admin')));
        $rows[] = $row;

        print_tabs($rows, $this->optional_param('mode', 'unassign', PARAM_ACTION) == 'assign' ? 'assigned' : 'unassigned', array(), array(get_class($this)));
    }

    protected function get_selection_form() {
        return new selectionform();
    }

    function action_default() {
        $form = $this->get_selection_form();

        $baseurl = htmlspecialchars_decode($this->get_basepage()->get_url());

        if ($data = $form->get_data()) {
            $selection = json_decode($data->_selection);
            $selection = $selection ? $selection : array();
            if (!is_array($selection)) {
                print_error('form_error', 'block_curr_admin');
            }
            $data->_selection = $selection;
            $this->process_selection($data);
            return;
        } else if (($showselection = optional_param('_showselection','',PARAM_RAW))) {
            $baseurl .= "&_showselection=$showselection";
            $selection = json_decode($showselection);
            $selection = $selection ? $selection : array();
            if (!is_array($selection)) {
                print_error('form_error', 'block_curr_admin');
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

        $records = $records ? $records : array();
        $table = $this->create_selection_table($records, $baseurl);
        $this->print_js_selection_table($table, $filter, $count, $form, $baseurl);
    }

    /**
     * Hook for child classes of selectionpage to add extra URL parameters
     * to page links.
     *
     * @return associative array with keys as parameters and resp. values
     *         default null returns no extra parameters.
     *
     * for example see: /curriculum/rolepage.class.php
     */
    function get_extra_page_params() {
        return null;
    }

    protected function print_js_selection_table($table, $filter, $count, $form, $baseurl) {
        global $CFG;
        $mode = optional_param('mode', '', PARAM_ACTION);
        if (!$this->is_bare()) {
            echo "<script>var basepage='$baseurl';</script>";
            require_js(array('yui_yahoo',
                             'yui_dom',
                             'yui_event',
                             'yui_connection',
                             "{$CFG->wwwroot}/curriculum/js/associate.js"));

            echo '<div class="mform" style="width: 100%"><fieldset><legend>'.get_string('select').'</legend><div id="list_display">';
        }

        $pagenum = optional_param('page', 0, PARAM_INT);
        $perpage = 30;

        if ($filter != null) {
            $this->print_selection_filter($filter);
        }

        $extra_url_params = '&amp;'; // trailing '&' required!
        $extra_page_params = $this->get_extra_page_params();
        if (!empty($extra_page_params)) {
            foreach ($extra_page_params AS $key => $val) {
                $extra_url_params .= "{$key}={$val}&amp;"; // trailing '&' required!
            }
        }

        // pager
        $action = optional_param('action', '', PARAM_ACTION);
        print_paging_bar($count, $pagenum, $perpage, $this->get_basepage()->get_url() . ($action ? "&amp;action=$action" : '' ) . $extra_url_params);

        echo '<div style="float: right">';
        $this->print_record_count($count);
        echo '</div>';

        // select all button
        print_checkbox('selectall', '', false, get_string('selectall'), '', 'select_all()');

        // table
        $table->print_table();

        if (!$this->is_bare()) {
            echo '</div>';

            echo '<div align="center">'.get_string('numselected','block_curr_admin','<span id="numselected">0</span>').'<span id="selectedonotherpages" style="display: none"> ('.get_string('num_not_shown', 'block_curr_admin', '<span id="numonotherpages">0</span>').')</span></div>';
            echo '<div align="center">';
            print_checkbox('selectedonly', '', false, get_string('selectedonly', 'block_curr_admin'), '', 'change_selected_display()');
            echo '</div></fieldset></div>';

            $form->display();

            echo '</div></div>';
        }
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
        if (!isset($this->_basepage)) {
            $this->_basepage = clone($this);
            $this->_basepage->params = $this->get_base_params();
        }
        return $this->_basepage;
    }

    /**
     * Initializes the selection form (sets parameters)
     */
    protected function init_selection_form(&$form) {
        $params = (object) $this->get_basepage()->get_moodle_url()->params;
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

    protected function print_record_count($count) {
        print_string('items_found', 'block_curr_admin', $count);
    }

    abstract protected function create_selection_table($records, $baseurl);
}

/**
 * Table to display the selection list.  When creating a new table, it should
 * have a column called '_selection'.
 */
class selection_table extends display_table {
    function __construct(&$items, $columns, $pageurl, $decorators=array()) {
        parent::__construct($items, $columns, $pageurl, $decorators);
        $this->table->id = 'selectiontable';
    }

    function is_sortable__selection() {
        return false;
    }

    function get_item_display__selection($column, $item) {
        return print_checkbox('select'.$item->id, '', isset($this->checked[$item->id]), '', '', 'select_item(&quot;'.$item->id.'&quot;)', true);
    }
}
?>
