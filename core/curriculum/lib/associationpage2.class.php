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

require_once CURMAN_DIRLOCATION . '/lib/selectionpage.class.php';

abstract class associationpage2 extends selectionpage {
    /**
     * Determines whether the page is in assigning or unassigning mode
     */
    protected function is_assigning() {
        return $this->optional_param('_assign', 'unassign', PARAM_ACTION) == 'assign';
    }

    function print_header() {
        $id = required_param('id', PARAM_INT);

        parent::print_header();

        $mode = optional_param('mode', '', PARAM_ACTION);
        if ($mode != 'bare') {
            $this->print_tabs();
        }
    }

    function get_assigned_strings() {
        return array(get_string('assigned', 'block_curr_admin'),
                     get_string('unassigned', 'block_curr_admin'));
    }

    function print_tabs() {
        $id = $this->required_param('id', PARAM_INT);

        $page = $this->get_tab_page();
        $params = array('id' => $id);
        $rows = array();
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
        $assignedpage = clone($this->get_basepage());
        unset($assignedpage->params['_assign']);
        $unassignedpage = clone($assignedpage);
        $unassignedpage->params['_assign'] = 'assign';
        list($assigned_string, $unassigned_string) = $this->get_assigned_strings();
        $row = array(new tabobject('assigned', $assignedpage->get_url(), $assigned_string),
                     new tabobject('unassigned', $unassignedpage->get_url(), $unassigned_string));
        if ($unassignedpage->can_do()) {
            $rows[] = $row;
        }

        print_tabs($rows, isset($rows[1]) ? ($this->is_assigning() ? 'unassigned' : 'assigned') : get_class($this), array(), array(get_class($this)));
    }

    /**
     * Get the base parameters for the association page
     * @return array
     */
    protected function get_base_params() {
        $data = parent::get_base_params();
        $data['id'] = $this->required_param('id', PARAM_INT);
        if ($this->is_assigning()) {
            $data['_assign'] = 'assign';
        }
        return $data;
    }

    protected function process_selection($data) {
        if ($this->is_assigning()) {
            return $this->process_assignment($data);
        } else {
            return $this->process_unassignment($data);
        }
    }
    abstract protected function process_assignment($data);
    abstract protected function process_unassignment($data);

    protected function get_records($filter) {
        if ($this->is_assigning()) {
            return $this->get_available_records($filter);
        } else {
            return $this->get_assigned_records($filter);
        }
    }
    abstract protected function get_available_records($filter);
    abstract protected function get_assigned_records($filter);
}

?>
