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

global $CFG;
require_once($CFG->dirroot . '/lib/formslib.php');

class cmform extends moodleform {
    function definition() {
        if(!empty($this->_customdata['obj'])) {
            if (is_object($this->_customdata['obj']) && method_exists($this->_customdata['obj'], 'to_object')) {
                $this->_customdata['obj'] = $this->_customdata['obj']->to_object();
            }
            $this->set_data($this->_customdata['obj']);
        }
    }

    function freeze() {
        $this->_form->freeze();
    }

    /**
     * Method to add ELIS entity's custom fields to entity forms
     *
     * @param string $entity        ELIS entity name: Eg. 'curriculum', 'track', 'course', 'class', 'cluster', 'user'
     * @param string $edit_cap      The required edit capability
     * @param string $view_cap      The required view capability
     * @param string $parent_entity The entity name of the parent object (optional)
     */
    function add_custom_fields($entity, $edit_cap, $view_cap, $parent_entity = '') {
        $mform  = &$this->_form;
        $fields = field::get_for_context_level($entity);
        $fields = $fields ? $fields : array();

        if (isset($this->_customdata['obj'])) {
            if(isset($this->_customdata['obj']->id)) {
                // Use the current (existing) entity's context instance
                $contextlevel = context_elis_helper::get_level_from_name($entity);
                $contextclass = context_elis_helper::get_class_for_level($contextlevel);
                $context      = $contextclass::instance($this->_customdata['obj']->id);
            } else if (isset($this->_customdata['obj']->parent) && $parent_entity != '') {
                // ELIS-6498 -- Specify the parent entity type to get the correct parent context instance as we are
                // adding a new "child" entity
                $contextlevel = context_elis_helper::get_level_from_name($parent_entity);
                $contextclass = context_elis_helper::get_class_for_level($contextlevel);
                $context      = $contextclass::instance($this->_customdata['obj']->parent);
            } else if (isset($this->_customdata['obj']->courseid) && $parent_entity == 'course') {
                // ELIS-6498 -- Special handling of the course -> class hierarchy is required here
                $context = context_elis_course::instance($this->_customdata['obj']->courseid);
            } else {
                $context = context_system::instance();
            }
        } else {
            $context = context_system::instance();
        }

        require_once(elis::plugin_file('elisfields_manual', 'custom_fields.php'));

        $lastcat = null;
        foreach ($fields as $rec) {
            $field = new field($rec);
            if (!isset($field->owners['manual'])) {
                continue;
            }

            //capabilities for editing / viewing this context

            if (manual_field_is_view_or_editable($field, $context, $edit_cap, $view_cap) != MANUAL_FIELD_NO_VIEW_OR_EDIT) {
                if ($lastcat != $rec->categoryid) {
                    $lastcat = $rec->categoryid;
                    $mform->addElement('header', "category_{$lastcat}", htmlspecialchars($rec->categoryname));
                }

                manual_field_add_form_element($this, $mform, $context, $this->_customdata, $field, true, $edit_cap, $view_cap, $entity);
            }
        }
    }

    function validate_custom_fields($data, $eliscontext) {
        $errors = array();

        $contextlevel = context_elis_helper::get_level_from_name($eliscontext);
        $fields = field::get_for_context_level($contextlevel);
        $fields = $fields ? $fields : array();
        if (!empty($data['id'])) {
            $contextclass = context_elis_helper::get_class_for_level($contextlevel);
            $context     = $contextclass::instance($data['id']);
            $contextid = $context->id;
        } else {
            $contextid = 0;
        }

        foreach ($fields as $field) {
            $field = new field($field);
            $key = "field_{$field->shortname}";
            if ($errstr = manual_field_validation(isset($data[$key]) ? $data[$key] : null, $field, $contextid)) {
                $errors[$key] = $errstr;
            }
            //error_log("cmform.class.php::validation(): contextid = {$contextid}, data[{$key}] = {$data[$key]}, errors[$key] = {$errstr}");
        }

        return $errors;
    }
}

