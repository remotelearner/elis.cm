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

require_once($CFG->dirroot .'/elis/core/lib/table.class.php');
require_once($CFG->dirroot .'/elis/program/accesslib.php');
require_once($CFG->dirroot .'/elis/program/lib/page.class.php');
require_once('request_form.php');
require_once('approvepage.class.php');

// main lib file for the course request block
require_once($CFG->dirroot.'/blocks/course_request/lib.php');

class RequestPage extends pm_page {
    var $pagename = 'crp';      // course request page
    var $section = 'admn';      // no admin category link but maybe later?
    var $folder;
    var $tabs;

    function __construct(array $params = null) {
        $this->tabs = array(
            array('tab_id' => 'default', 'page' => get_class($this), 'params' => array('action' => 'default'), 'name' => get_string('current', 'block_course_request')),
            array('tab_id' => 'requests', 'page' => get_class($this), 'params' => array('action' => 'requests'), 'name' => get_string('requests', 'block_course_request')),
        );
        parent::__construct($params);
    }

    /**
     * Checks permissions for the "Current" tab
     *
     * @return  boolean  True if page access is allowed, otherwise false
     */
    function can_do_default() {
        // check request permissions
        return block_course_request_can_do_request();
    }

    /**
     * Checks permissions for the "Requests" tab
     *
     * @return  boolean  True if page access is allowed, otherwise false
     */
    function can_do_requests() {
        // check request permissions
        return block_course_request_can_do_request();
    }

    function get_page_title_default() { // get_title_default()
        return get_string('request_title', 'block_course_request');
    }

    function build_navbar_default($who = null) {
        // parent::build_navbar_default();
        $this->navbar->add(get_string('current_classes', 'block_course_request'), $this->url);
    }

    function build_navbar_requests() {
        $this->navbar->add(get_string('requests', 'block_course_request'), $this->url);
    }

    function build_navbar_create() {
        $this->build_navbar_default();
        $tmppage = $this->get_new_page(array('action' => 'create'), true);
        $this->navbar->add(get_string('request', 'block_course_request'),
                           $tmppage->url);
    }

    function display_default() { // action_default()
        global $CFG;

        $target = $this->get_new_page(array('action' => 'create'), true);
        $configform = new current_form($target->url);

        $this->print_tabs('default');
        $configform->display();
    }

    function display_requests() { // action_requests()
        $sort = $this->optional_param('sort', 'timecreated', PARAM_ALPHANUM);
        $dir  = $this->optional_param('dir', 'ASC', PARAM_ALPHA);

        $columns = array(
            'title'         => array('header' => get_string('classes_requested', 'block_course_request')),
            'requeststatus' => array('header' => get_string('request_status', 'block_course_request')),
            'requestnotice' => array('header' => get_string('request_notice', 'block_course_request')),
        );

        // for self-approval, allow selection of the course template
        $system_context = context_system::instance();
        if (has_capability('block/course_request:approve', $system_context)) {
            $columns['usecoursetemplate'] = array('header' => get_string('use_course_template', 'block_course_request'));
        }

        $items = $this->get_pending_requests($sort, $dir);

        // This needs to be able to display the course name instead of an integer record ID value
        // $formatters = $this->create_link_formatters(array('courseid'), 'course', 'id');
        $formatters = null; // *TBD*

        $this->print_tabs('requests');
        $this->print_list_view($items, $columns, $formatters);
    }

    /**
     * Adds custom field data to a CM entity based on the data defined for
     * a particular class request (in-place)
     *
     * @param  int     $requestid          The database record id of the class request
     * @param  string  $contextlevel_name  Name of the context level we are adding fields for,
     *                                     such as 'course' or 'class'
     * @param  object  $entity             The CM entity to update with custom field data
     * @uses   $CFG
     * @uses   $DB
     */
    function add_custom_fields($requestid, $contextlevel_name, &$entity) {
        global $CFG, $DB;
        require_once($CFG->dirroot .'/elis/program/lib/contexts.php');

        $contextlevel = context_elis_helper::get_level_from_name($contextlevel_name);

        $select = "requestid = ? AND contextlevel = ?";
        // Add any custom field data from the request to this class now.
        if ($rs = $DB->get_recordset_select('block_course_request_data', $select, array($requestid, $contextlevel))) {
            foreach ($rs as $fielddata) {
                // ^WAS: while ($fielddata = rs_fetch_next_record($rs)) {
                $field = new field($fielddata->fieldid);
                // Check for multiple values and unserialize
                if ($fielddata->multiple == '1') {
                    $entity->{"field_{$field->shortname}"} = unserialize($fielddata->data);
                    $entity->{"field_{$field->shortname}"}->multivalued = true;
                } else {
                    $entity->{"field_{$field->shortname}"} = $fielddata->data;
                }
            }
            $rs->close();
        }
    }

    function display_create() { // action_create()
        global $CFG, $DB;

        $target = $this->get_new_page(array('action' => 'create'), true);
        $form = new create_form($target->url);
        $data = $form->get_data();

        if ($form->is_cancelled()) {
            redirect($this->url); // TBV
            return;
        } else if ($data) {
            global $USER;
            require_once($CFG->dirroot .'/elis/program/lib/data/course.class.php');
            $request = new stdClass;
            $request->userid = $USER->id;
            $request->firstname = $data->first;
            $request->lastname = $data->last;
            $request->email = $data->email;
            if (empty($data->title)) {
                $course_record = $DB->get_record(course::TABLE, array('id' => $data->courseid));
                $data->title = $course_record->name;
            }

            $request->title = $data->title;
            $request->courseid = $data->courseid;
            $request->usecoursetemplate = empty($data->usecoursetemplate) ? 0 : 1;
            $request->requeststatus = 'pending';
            $request->timemodified = $request->timecreated = time();
            $id = $DB->insert_record('block_course_request', $request);

            $fields = $DB->get_records('block_course_request_fields');
            $fields = $fields ? $fields : array();
            foreach ($fields as $reqfield) {
                $field = new field($reqfield->fieldid);
                if (!$field->id || !isset($field->owners['manual'])) {
                    // skip nonexistent fields, or fields without manual editing
                    continue;
                }
                $fielddata = new stdClass;
                $fielddata->requestid = $id;
                $fielddata->fieldid = $reqfield->fieldid;

                // key that represents the appropriate attribute on the object
                $field_key = "field_{$field->shortname}";

                // make sure the field was enabled, especially for preventing the
                // storage of course fields when using an existing course
                if (isset($data->$field_key)) {
                    // check for multiple fields
                    if (is_array($data->$field_key)) {
                        $fielddata->data = serialize($data->$field_key);
                        $fielddata->multiple = '1';
                    } else {
                        $fielddata->data = $data->$field_key;
                    }

                    // remember the context level that the field corresponds to
                    $fielddata->contextlevel = $reqfield->contextlevel;
                    $DB->insert_record('block_course_request_data', $fielddata);
                }
            }

            require_once($CFG->dirroot .'/elis/program/lib/notifications.php');
            $syscontext = context_system::instance();

            if (has_capability('block/course_request:approve', $syscontext)) {
            // Since we want to automatically approve requests for people approval permission, let's go ahead and create the course/class

                $requestid = $id;

                if (!$request = $DB->get_record('block_course_request', array('id' => $requestid))) {
                    $target = str_replace($CFG->wwwroot, '', $this->url);

                    print_error('errorinvalidrequestid', 'block_course_request', $target, $requestid);
                }

                $target = $this->get_new_page(array('action' => 'approveconfirm'));

                $approveform = new pending_request_approve_form($target->url, $request);
                $request->request = $request->id;
                $approveform->set_data($request);

                // We're not actually approving the request, redirect back to the approval table.
                if ($approveform->is_cancelled()) {
                    redirect($this->url, '', 0);
                }

                // Do we have to create a brand new course?
                if (empty($request->courseid)) {
                    $crsdata = array(
                        'name'     => $request->title, // TBV: addslashes() ?
                        'idnumber' => $data->crsidnumber,
                        'syllabus' => '' // *TBD*
                    );

                    $newcourse = new course($crsdata);

                    if (!empty($CFG->block_course_request_use_course_fields)) {
                        // course fields are enabled, so add the relevant data
                        $this->add_custom_fields($request->id, 'course', $newcourse);
                    }

                    $newcourse->save(); // ->add()

                    // do the course role assignment, if applicable
                    if (!empty($CFG->block_course_request_course_role)) {
                        if ($context = context_elis_course::instance($newcourse->id)) {
                            // TBD: role_assign() now throws exceptions!
                            $result = role_assign($CFG->block_course_request_course_role, $request->userid, $context->id, ECR_CD_ROLE_COMPONENT);
                        }
                    }

                    $courseid = $newcourse->id;
                } else {
                    $courseid = $request->courseid; // TBV: addslashes()
                }

                // Create the new class if we are using an existing course, or if
                // create_class_with_course is on.
                if (!empty($request->courseid) || !empty($CFG->block_course_request_create_class_with_course)) {
                    require_once($CFG->dirroot .'/elis/program/lib/data/pmclass.class.php');
                    $clsdata = array(
                        'name'            => $request->title,
                        'courseid'        => $courseid,
                        'idnumber'        => $data->clsidnumber,
                        'starttimehour'   => 25,
                        'starttimeminute' => 61,
                        'endtimehour'     => 25,
                        'endtimeminute'   => 61,
                    );

                    $newclass = new pmclass($clsdata);
                    $newclass->autocreate = false;

                    $set_class_fields = !isset($CFG->block_course_request_use_class_fields) ||
                                        !empty($CFG->block_course_request_use_class_fields);

                    if ($set_class_fields) {
                        // class fields are enabled, so add the relevant data
                        $this->add_custom_fields($request->id, 'class', $newclass);
                    }

                    $newclass->save(); // ->add()
                }

                // Update the request record to mark it as being approved.
                $request->requeststatus = 'approved';
                $request->statusnote = '';
                $DB->update_record('block_course_request', $request); // TBV: addslashes_object()

                // assign role to requester in the newly created class
                if (!empty($newclass->id)) {
                    if (isset($CFG->block_course_request_class_role) && $CFG->block_course_request_class_role) {
                        $context = context_elis_class::instance($newclass->id);
                        // TBD: role_assign() now throws exceptions!
                        role_assign($CFG->block_course_request_class_role, $request->userid, $context->id, ECR_CI_ROLE_COMPONENT);
                    }
                }

                // create a new Moodle course from the course template if applicable
                if (!empty($data->usecoursetemplate) && !empty($newclass->id)) {
                    moodle_attach_class($newclass->id, 0, '', true, true, true);

                    // copy role over into Moodle course
                    if (isset($CFG->block_course_request_class_role) && $CFG->block_course_request_class_role) {
                        require_once($CFG->dirroot .'/elis/program/lib/data/classmoodlecourse.class.php');
                        if ($class_moodle_record = $DB->get_record(classmoodlecourse::TABLE, array('classid' => $newclass->id))) {
                            $context = context_course::instance($class_moodle_record->moodlecourseid);
                            // TBD: role_assign() now throws exceptions!
                            role_assign($CFG->block_course_request_class_role, $request->userid, $context->id, ECR_MC_ROLE_COMPONENT);
                        }
                    }
                }

                // Send a notification to the requesting user that their course / class is ready.

                // set additional course / class information for use in the message
                if (isset($newclass->idnumber) && isset($newclass->id)) {
                    $request->classidnumber = $newclass->idnumber;
                    $request->classid = $newclass->id;
                }
                $request->newcourseid = $courseid;

                // calculate the actual message
                $notice = block_course_request_get_approval_message($request);

                // send it to the requester
                notification::notify($notice, $DB->get_record('user', array('id' => $request->userid)));

                print_string('request_submitted_and_auto_approved', 'block_course_request');
            } else {
                // find users with approve capabilities in the system context
                $admin = get_users_by_capability($syscontext, 'block/course_request:approve');

                foreach ($admin as $userto) {
                    notification::notify(get_string('new_request_notification', 'block_course_request', $data), $userto);
                }

                print_string('request_submitted', 'block_course_request');

            }
            redirect($this->url);
            return;
        }

        $form->display();
    }

    /**
     * Prints the tab bar describe by the $tabs instance variable.
     * @param $selected name of tab to display as selected
     * @param $params extra parameters to insert into the tab links, such as an id
     */
    function print_tabs($selected, $params = array()) {
        $row = array();
        foreach ($this->tabs as $tab) {
            $target = new $tab['page'](array_merge($tab['params'], $params));
            $row[] = new tabobject($tab['tab_id'], $target->url, $tab['name']);
        }
        print_tabs(array($row), $selected);
    }

    /**
     * Prints out the page that displays a list of records returned from a query.
     *
     * @param $items array of records to print
     * @param $columns associative array of column id => column heading text
     * @param $formatters associative array of column id => formatting object, used to customize the display of columns
     */
    function print_list_view($items, $columns, $formatters = array()) {
        if (empty($items)) {
            echo '<div>'. get_string('no_requests', 'block_course_request') .'</div>';
            return;
        }

        $table = $this->create_table_object($items, $columns, $formatters);
        echo $table;
    }

    /**
     * Creates a new table object with specified $items and $columns.
     * @param array $items
     * @param array $columns
     * @param array $formatters
     */
    function create_table_object($items, $columns, $formatters) {
        return new users_requests_page_table($items, $columns, $this, $formatters);
    }

    /*
     * Convenience function for creating the standard link-to-foreign-record formatters array for the list view.
     */
    function create_link_formatters($columns, $foreign_class, $foreign_id) {
        $formatters = array();
        foreach ($columns as $column) {
            // ***TBD***
            // $formatters[$column] = new recordlinkformatter(new $foreign_class(), $foreign_id);
        }

        return $formatters;
    }

    function get_pending_requests($sort = '', $dir = '') {
        global $DB, $USER;

        if ($sort) {
            $sort = $sort .' '. $dir;
        }

        $requests = $DB->get_records('block_course_request', array('userid' => $USER->id), $sort);

        if (!empty($requests)) {
            return $requests;
        } else {
            return array();
        }
    }

}

/**
 * Defines the table that displays pending class requests.
 */
class users_requests_page_table extends display_table {
    function __construct(&$items, $columns, $page, $decorators = array()) {
        $sort = optional_param('sort', 'title', PARAM_ALPHA); // TBV
        $dir = optional_param('dir', 'ASC', PARAM_ALPHA);
        if ($dir !== 'DESC') {
            $dir = 'ASC';
        }
        if (isset($columns[$sort])) {
            $columns[$sort]['sortable'] = $dir;
        } else {
            $sort = 'name';
            $columns[$sort]['sortable'] = $dir;
        }
        $this->page = $page;
        if (isset($columns['requestnotice']) && is_array($columns['requestnotice'])) {
            $columns['requestnotice']['sortable'] = false;
        }
        $url = new moodle_url($page->url, array('action' => 'requests', 's' => 'crp'));
        parent::__construct($items, $columns, $url);
        // *TBD* $decorators
    }

    function get_column_align_buttons() {
        return 'center';
    }

    function is_column_wrapped_buttons() {
        return false;
    }

    function get_item_display_requestnotice($column, $item) {
        global $OUTPUT;
        if (!empty($item->statusnote)) {
            $link = new moodle_url('/blocks/course_request/displaynote.php',
                                   array('id' => $item->id));
            $action = new popup_action('click', $link, 'view_'. $item->id,
                              array('title' => get_string('blockname', 'block_course_request'),
                                    'menubar' => false, 'location' => false,
                                    'toolbar' => false, 'directories' => false,
                                    'scrollbars' => true, 'status' => true,
                                    'height' => 300, 'width' => 500));
            $retval = $OUTPUT->action_link($link, get_string('view', 'block_course_request'), $action);
           /* above WAS:
             $retval = link_to_popup_window(
                          "{$CFG->wwwroot}/blocks/course_request/displaynote.php?id={$item->id}",
                           'view_'. $item->id, get_string('view', 'block_course_request'),
                           400, 500, null, null, true);
           */
        } else {
            $retval = get_string('none', 'block_course_request');
        }

        return $retval;
    }

    /**
     * Display logic for showing the use of course template as a Yes / No value
     *
     * @param   string    $column  The field we are formatting
     * @param   stdClass  $item    The row data object
     *
     * @return                     The formatted column text
     */
    function get_item_display_usecoursetemplate($column, $item) {
        return empty($item->$column) ? get_string('no') : get_string('yes');
    }

    function is_sortable_requestnotice() {
        return false;
    }
}

