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

require_once elispm::lib('selectionpage.class.php');

abstract class associationpage2 extends selectionpage {
    /**
     * Determines whether the page is in assigning or unassigning mode
     */
    protected function is_assigning() {
        return $this->optional_param('_assign', 'unassign', PARAM_ACTION) == 'assign';
    }

    function print_header($_) {
        $id = $this->required_param('id', PARAM_INT); // TBD

        parent::print_header($_);

        $mode = $this->optional_param('mode', '', PARAM_ACTION);
        if ($mode != 'bare') {
            //$this->print_tabs();
        }
    }

    function get_assigned_strings() {
        return array(get_string('assigned', 'elis_program'),
                     get_string('unassigned', 'elis_program'));
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
                if (!$target->can_do()) {
                    //insufficient permissions according to tab's page, so don't add it
                    continue;
                }
                $row[] = new tabobject($tab['tab_id'], $target->url, $tab['name']);
            }
        }
        if (!empty($row)) {
            $rows[] = $row;
        }

        // assigned/unassigned tabs
        //$assignedpage = clone($this->get_basepage());
        //unset($assignedpage->params['_assign']);
        $assignedpage = $this->get_new_page(array('id' => $id, '_assign' => 'unassign')); // TBD: arbitrary != 'assign' ???
        //$unassignedpage = clone($assignedpage);
        //$unassignedpage->params['_assign'] = 'assign';
        $unassignedpage = $this->get_new_page(array('id' => $id, '_assign' => 'assign'));
        list($assigned_string, $unassigned_string) = $this->get_assigned_strings();
        $iscurstupage = get_class($this) == 'curriculumstudentpage';
        if ((!$iscurstupage && $this->can_do_default()) ||
            ($iscurstupage && curriculumpage::can_enrol_into_curriculum($id))) {
            $row = array(new tabobject('assigned', $assignedpage->url, $assigned_string),
                         new tabobject('unassigned', $unassignedpage->url, $unassigned_string));
        } else {
            $row = array(new tabobject('assigned', $assignedpage->url, $assigned_string));
        }
        $rows[] = $row;

        print_tabs($rows, $this->is_assigning() ? 'unassigned' : 'assigned', array(), array(get_class($this)));
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
        } else {
            $data['_assign'] = 'unassign';
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

