<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @subpackage blocks-course_request
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot .'/elis/program/accesslib.php');
// require_once($CFG->dirroot .'/elis/program/lib/setup.php');
require_once($CFG->dirroot .'/elis/program/lib/page.class.php');
require_once('request_form.php');

class EditRequestPage extends pm_page {
    var $pagename = 'erp';      // edit request page
    var $section = 'admn';      // no admin category link but maybe later?
    var $folder;

    function can_do_default() {
        $context = context_system::instance();
        return has_capability('block/course_request:config', $context);
    }

    function can_do_requests() {
        $context = context_system::instance();
        return has_capability('block/course_request:config', $context);
    }

    function get_page_title_default() { // get_title_default()
        return get_string('request_title', 'block_course_request');
    }

    function build_navbar_default($who = null) {
        $this->navbar->add(get_string('editrequestpages', 'block_course_request'), $this->url);
    }

    function display_request() { // New for redirect from display_default()
        $target = $this->get_new_page(array('action' => 'default'), true);
        $configform = new define_request_form($target->url);
        $configform->display();
    }

    function display_default() { // action_default()
        global $CFG, $DB;

        $target = $this->get_new_page(array('action' => 'default'), true);
        $delete = $this->optional_param('delete', null, PARAM_SEQUENCE);
        $redir = false; // redirection flag

        if ($delete) {
            $keys = array_keys($delete);
            foreach ($keys as $todel) {
                $DB->delete_records('block_course_request_fields', array('id' => $todel));
            }
            $redir = true;
        } else if ($this->optional_param('update', null, PARAM_TEXT)) {
            $fields = $this->optional_param_array('field', array(), PARAM_SEQUENCE);
            foreach ($fields as $id => $field) {
                $rec = new stdClass;
                $rec->id = $id;
                $rec->fieldid = $field;
                $DB->update_record('block_course_request_fields', $rec);
            }
            $redir = true;
        } else if ($submitted_data = data_submitted()) {
            // adding a field for a context level
            require_once($CFG->dirroot .'/elis/program/lib/contexts.php');

            // the context levels we are checking for new fields at
            $contextlevels = array(CONTEXT_ELIS_COURSE, CONTEXT_ELIS_CLASS);

            // go through all context levels to see if a new field was added at that level
            foreach ($contextlevels as $contextlevel) {
                // key representing the submit value
                $key = "add_field_{$contextlevel}";

                if (isset($submitted_data->$key)) {
                    // make the field available for editing
                    $rec = new stdClass;
                    $rec->fieldid = 0;
                    $rec->contextlevel = $contextlevel;
                    $DB->insert_record('block_course_request_fields', $rec);
                }
            }
            $redir = true;
        }

        if ($redir) { // redirect to avoid re-submitting form data on reload...
            $target = $this->get_new_page(array('action' => 'request'), true);
            redirect($target->url);
        } else {
            $this->display_request();
        }
    }
}

