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
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot .'/lib/formslib.php');

require_once($CFG->dirroot .'/elis/core/lib/data/customfield.class.php');
require_once($CFG->dirroot .'/elis/core/lib/table.class.php');
require_once($CFG->dirroot .'/elis/program/accesslib.php');
require_once($CFG->dirroot .'/elis/program/lib/page.class.php');
require_once($CFG->dirroot .'/elis/program/lib/data/course.class.php');
require_once($CFG->dirroot .'/elis/program/lib/data/pmclass.class.php');

// main lib file for the course request block
require_once($CFG->dirroot .'/blocks/course_request/lib.php');
// needed to create the approval form based on the create form
require_once($CFG->dirroot .'/blocks/course_request/request_form.php');

class courserequestapprovepage extends pm_page {
    var $pagename = 'arp';  // course request page
    var $section  = 'admn'; // no admin category link but maybe later?
    var $folder;

    function __construct(array $params = null) {
        parent::__construct($params);
    }

    function can_do_default() {
        return has_capability('block/course_request:approve', context_system::instance());
    }

    function get_page_title_default() { // get_title_default()
        return get_string('approvependingrequests', 'block_course_request');
    }

    function build_navbar_default($who = null) {
        $url = $this->url;
        $url->remove_params(array('action', 'request'));
        $this->navbar->add(get_string('approvependingrequests', 'block_course_request'), $url);
    }

    function build_navbar_viewrequest() {
        $this->build_navbar_default();
        $this->navbar->add(get_string('approve', 'block_course_request'), $this->url);
    }

    function build_navbar_approverequest() {
        $this->build_navbar_viewrequest();
    }

    /**
     * Adds custom field data to a CM entity based on the data defined for
     * a particular class request (in-place)
     *
     * @param  object  $formdata           the submitted form data
     * @param  string  $contextlevel_name  Name of the context level we are adding fields for,
     *                                     such as 'course' or 'class'
     * @param  object  $entity             The CM entity to update with custom field data
     */
    function add_custom_fields($formdata, $contextlevel_name, &$entity) {
        global $CFG;

        require_once($CFG->dirroot .'/elis/program/lib/contexts.php');

        $contextlevel = context_elis_helper::get_level_from_name($contextlevel_name);
        if ($fields = field::get_for_context_level($contextlevel)) {
            foreach ($fields as $field) {
                $key = "field_{$field->shortname}";
                if (isset($formdata->$key)) {
                    $entity->$key = $formdata->$key;
                }
            }
        }
    }

    /**
     * Display a table of the pending requests or a message indicating that there aren't any.
     */
    function display_default() { // action_default()
        $sort = $this->optional_param('sort', 'timecreated', PARAM_ALPHANUM);
        $dir  = $this->optional_param('dir', 'ASC', PARAM_ALPHA);

        $columns = array(
            'courseid'          => array('header' => get_string('course', 'block_course_request')),
            'title'             => array('header' => get_string('title', 'block_course_request')),
            'firstname'         => array('header' => get_string('firstname', 'block_course_request')),
            'lastname'          => array('header' => get_string('lastname', 'block_course_request')),
            'email'             => array('header' => get_string('email', 'block_course_request')),
            'timecreated'       => array('header' => get_string('created', 'block_course_request')),
            'usecoursetemplate' => array('header' => get_string('use_course_template', 'block_course_request')),
            'manage'            => array('header' => '')
        );

        $items = $this->get_pending_requests($sort, $dir);

        echo '<h2>' . get_string('pendingrequests', 'block_course_request') . "</h2>\n";

        // This needs to be able to display the course name instead of an integer record ID value
        $formatters = $this->create_link_formatters(array('courseid'), 'coursepage', 'courseid');

        $this->print_list_view($items, $columns, $formatters);
    }

    /**
     * Adds submitted custom field data to a request record
     *
     * @param  object $request  the request record to to add custom field values to
     * @uses   $DB
     */
    protected function add_custom_field_data(&$request) {
        global $DB;
        // *TBD* table constants ??? include files

        // obtain submitted field values and set up the apppriate form data
        $sql = "SELECT field.shortname AS fieldshortname,
                       data.data,
                       data.multiple
                FROM {elis_field} field
                JOIN {block_course_request_data} data
                  ON field.id = data.fieldid
                WHERE data.requestid = ?";

        if ($recordset = $DB->get_recordset_sql($sql, array($request->id))) {
            foreach ($recordset as $record) {
                // set data for field shortname
                $key = "field_{$record->fieldshortname}";
                // Check for multiple values and unserialize
                if ($record->multiple == '1') {
                    $request->$key = unserialize($record->data);
                } else {
                    $request->$key = $record->data;
                }

            }
            $recordset->close();
        }
    }

    /**
     * Adds baseline information to the approval form based on the provided request
     *
     * @param  object  $request  The request we are approving / denying
     * @uses   $CFG
     * @uses   $DB
     */
    function add_approval_form_constants(&$request) {
        global $CFG, $DB;
        require_once($CFG->dirroot .'/elis/program/lib/data/course.class.php');

        // obtain the course idnumber if applicable
        if ($request->courseid != 0) {
            if ($course = $DB->get_record(course::TABLE, array('id' => $request->courseid))) {
                $request->crsidnumber = $course->idnumber;
            }
        }

        // todo: fix form to be consistent with request field names
        $request->first = $request->firstname;
        $request->last = $request->lastname;
    }

    /**
     * Display the denial / approval confirmation screen with informative, editable information.
     * @uses   $CFG
     * @uses   $DB
     */
    function display_viewrequest() { // action_viewrequest()
        global $CFG, $DB;

        $requestid = required_param('request', PARAM_INT);
        if (!$request = $DB->get_record('block_course_request', array('id' => $requestid))) {
            $target = str_replace($CFG->wwwroot, '', $this->url);

            print_error('errorinvalidrequestid', 'block_course_request', $target, $requestid);
        }

        $request->request = $request->id;

        // add additional information to the request that is needed by the form by default
        // and may not be part of the submitted info
        $this->add_approval_form_constants($request);

        // add the submitted custom field values to the form data
        $this->add_custom_field_data($request);

        $target = $this->get_new_page(array('action' => 'approverequest'), true);
        $approveform = new pending_request_approve_form($target->url, $request);
        $approveform->set_data($request);
        $approveform->display();
    }

    /**
     * Mainline for performing the back-end request denail/approval process
     * @uses   $CFG
     * @uses   $DB
     */
    function display_approverequest() { // action_approverequest()
        global $CFG, $DB;
        // determine the action we are taking
        $approval_action = $this->required_param('approvalaction', PARAM_CLEAN);

        // make sure we have the necessary request
        $requestid = $this->required_param('request', PARAM_INT);
        if (!$request = $DB->get_record('block_course_request', array('id' => $requestid))) {
            $target = str_replace($CFG->wwwroot, '', $this->url);

            print_error('errorinvalidrequestid', 'block_course_request', $target, $requestid);
        }

        // add additional information to the request that is needed by the form by default
        // and may not be part of the submitted info
        $this->add_approval_form_constants($request);

        // obtain the submitted form
        $target = $this->get_new_page(array('action' => 'approverequest'), true);
        $approveform = new pending_request_approve_form($target->url);

        $approveform->set_data($request);

        // cancel back to the base page if applicable
        if ($approveform->is_cancelled()) {
            redirect($this->url, '', 0);
        }

        // obtain the submitted data
        if ($formdata = $approveform->get_data(false)) {
            if ($approval_action == 'deny') {
                // deny the request
                $this->deny_request($request, $formdata);
            } else {
                // approve the request
                $this->approve_request($request, $formdata);
            }

            // redirect so reload won't resubmit form data
            $target = $this->get_new_page(array('action'  => 'viewrequest',
                                                'request' => $requestid), true);
            redirect($target->url);
        }
        $approveform->display();
    }

    /**
     * Helper method for performing actions specific to the back-end denial process
     *
     * @param  object  $request   the request record
     * @param  object  $formdata  the submitted form data
     * @uses   $DB
     * @uses   $USER
     */
    function deny_request($request, $formdata) {
        global $DB, $USER;
        // update the request with additional information
        $request->requeststatus = 'denied';
        $request->statusnote = $formdata->comments;
        $DB->update_record('block_course_request', $request); // TBV: addslashes_recursive() not required in Moodle-2

        // notify the user of the request status
        $statusnote = null;
        if (!empty($request->statusnote)) {
            $statusnote = $request->statusnote;
        }
        $notice = block_course_request_get_denial_message($request, $statusnote);
        $requser = $DB->get_record('user', array('id' => $request->userid));
        $requser->firstname = $formdata->first;
        $requser->lastname = $formdata->last;
        $requser->email = $formdata->email;
        notification::notify($notice, $requser, $USER);
        // error_log("course_request:deny_request(); notice = {$notice} ... sent to: {$requser->email} ". fullname($requser));

        // redirect back to the listing page
        redirect($this->url, get_string('deniedmessage', 'block_course_request'), 3);
    }

    /**
     * Performs course-specific actions as a part of the back-end approval process
     *
     * @param   object  $request   the request record
     * @param   object  $formdata  the submitted form data
     * @uses   $CFG
     * @return  int                the id of the course we are currently working with
     */
    function approve_request_course_actions(&$request, $formdata) {
        global $CFG;

        if (empty($request->courseid)) {
            require_once($CFG->dirroot .'/elis/program/lib/data/course.class.php');
            // create a new course
            $crsdata = array(
                'name'     => $request->title,
                'idnumber' => $formdata->crsidnumber,
                'syllabus' => ''
            );

            $newcourse = new course($crsdata);

            // make sure custom field data is included
            if (!empty($CFG->block_course_request_use_course_fields)) {
                // course fields are enabled, so add the relevant data
                $this->add_custom_fields($formdata, 'course', $newcourse);
            }

            $newcourse->save(); // ->add()

            // do the course role assignment, if applicable
            if (!empty($CFG->block_course_request_course_role)) {
                if ($context = context_elis_course::instance($newcourse->id)) {
                    role_assign($CFG->block_course_request_course_role, $request->userid, $context->id, ECR_CD_ROLE_COMPONENT);
                }
            }

            return $newcourse->id;
        } else {
            return $request->courseid;
        }
    }

    /**
     * Performs class-specific actions as a part of the back-end approval process
     *
     * @param  object  $request   the request record
     * @param  object  $formdata  the submitted form data
     * @param  int     $courseid  the id of the course that the applicable class
     *                            will belong to, if created
     * @uses   $CFG
     * @uses   $DB
     */
    function approve_request_class_actions(&$request, $formdata, $courseid) {
        global $CFG, $DB;

        // Create the new class if we are using an existing course, or if
        // create_class_with_course is on.
        if (!empty($request->courseid) || !empty($CFG->block_course_request_create_class_with_course)) {
            require_once($CFG->dirroot .'/elis/program/lib/data/pmclass.class.php');
            $clsdata = array(
                'courseid'        => $courseid,
                'idnumber'        => $formdata->clsidnumber,
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
                $this->add_custom_fields($formdata, 'class', $newclass);
            }

            $newclass->save(); // ->add()

            // needed to update the request record with the created class
            $request->classidnumber = $newclass->idnumber;
            $request->classid = $newclass->id;

            // assign role to requester in the newly created class
            if (!empty($CFG->block_course_request_class_role)) {
                $context = context_elis_class::instance($newclass->id);
                role_assign($CFG->block_course_request_class_role, $request->userid, $context->id, ECR_CI_ROLE_COMPONENT);
            }

            // create a new Moodle course from the CM course template if set on the approve form
            if (!empty($formdata->usecoursetemplate)) {
                moodle_attach_class($newclass->id, 0, '', true, true, true);

                // copy role over into Moodle course
                if (isset($CFG->block_course_request_class_role) && $CFG->block_course_request_class_role) {
                    require_once($CFG->dirroot .'/elis/program/lib/data/classmoodlecourse.class.php');
                    if ($class_moodle_record = $DB->get_record(classmoodlecourse::TABLE, array('classid' => $newclass->id))) {
                        $context = context_course::instance($class_moodle_record->moodlecourseid);
                        role_assign($CFG->block_course_request_class_role, $request->userid, $context->id, ECR_MC_ROLE_COMPONENT);
                    }
                }
            }
        }
    }

    /**
     * Helper method for performing actions specific to the back-end approval process
     *
     * @param  object  $request   the request record
     * @param  object  $formdata  the submitted form data
     * @uses   $DB
     * @uses   $USER
     */
    function approve_request($request, $formdata) {
        global $DB, $USER;
        // process course-specific actions
        $courseid = $this->approve_request_course_actions($request, $formdata);
        // process class-specific actions
        $this->approve_request_class_actions($request, $formdata, $courseid);

        // update the request with additional information
        $request->requeststatus = 'approved';
        $request->statusnote = $formdata->comments;
        $DB->update_record('block_course_request', $request); // TBV: addslashes_recursive() not required in Moodle 2 ?

        // notify the user of the request status
        $statusnote = null;
        if (!empty($request->statusnote)) {
            $statusnote = $request->statusnote;
        }
        $request->newcourseid = $courseid;
        $notice = block_course_request_get_approval_message($request, $statusnote);
        $requser = $DB->get_record('user', array('id' => $request->userid));
        $requser->firstname = $formdata->first;
        $requser->lastname = $formdata->last;
        $requser->email = $formdata->email;
        notification::notify($notice, $requser, $USER);

        // redirect back to the listing page
        redirect($this->url, get_string('requestapproved', 'block_course_request'), 3);
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
            echo '<div>' . get_string('nopendingrequests', 'block_course_request') . '</div>';
            return;
        }

        $table = $this->create_table_object($items, $columns, $formatters);
        echo $table; // ->print_table();
    }

    /**
     * Get all pending requests with the oldest first.
     *
     * @param none
     * @uses   $DB
     * @return array An array of request records.
     */
    function get_pending_requests($sort = '', $dir = '') {
        global $DB;
        if ($sort) {
            $sort = $sort .' '. $dir;
        }

        if ($requests = $DB->get_records('block_course_request', array('requeststatus' => 'pending'), $sort,
                                         'id, courseid, title, firstname, lastname, email, timecreated, usecoursetemplate')) {

            return $requests;
        }

        return array();
    }

    /**
     * Creates a new table object with specified $items and $columns.
     * @param array $items
     * @param array $columns
     * @param array $formatters
     */
    function create_table_object($items, $columns, $formatters) {
        return new pending_requests_page_table($items, $columns, $this, $formatters);
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
}


/**
 * Defines the table that displays pending class requests.
 */
class pending_requests_page_table extends display_table {
    private  $display_date;
    var $coursename_cache = array();

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
        parent::__construct($items, $columns, $page->url);
        // ***TBD*** $decorators
        $this->display_date = new display_date_item(get_string('pm_date_format', 'elis_program'));
    }

    function get_column_align_buttons() {
        return 'center';
    }

    function is_column_wrapped_buttons() {
        return false;
    }

    function get_item_display_courseid($column, $item) {
        global $DB;
        $courseid = $item->$column;
        if (!$courseid) {
            return get_string('newcourse', 'block_course_request');
        }
        if (!isset($this->coursename_cache[$courseid])) {
            $this->coursename_cache[$courseid] = $DB->get_field('crlm_course', 'name', array('id' => $courseid));
        }
        return $this->coursename_cache[$courseid];
    }

    function get_item_display_timecreated($column, $item) {
        return $this->display_date->display($column, $item);
    }

    function get_item_display_buttons($column, $item) {
        $id = required_param('id', PARAM_INT);
        return $this->page->get_buttons(array('id' => $id, 'association_id' => $item->id));
    }

    /**
     * Formats the link for viewing a particular request
     *
     * @param   string    $column  The particular field we're looking at
     * @param   stdClass  $item    The row data as an object
     *
     * @return  string             The formatted link
     */
    function get_item_display_manage($column, $item) {
        // request page url
        $target = $this->page->get_new_page(array('action' => 'viewrequest',
                                                  'request' => $item->id), true);
        // construct the link
        $link = '<a href="'. $target->url .'">'. get_string('view_request', 'block_course_request').'</a>';
        return $link;
    }

    /**
     * Formats Yes / No values for whether a course template is used
     *
     * @param   string    $column  The particular field we're looking at
     * @param   stdClass  $item    The row data as an object
     *
     * @return  string             The formatted column value (Yes or No)
     */
    function get_item_display_usecoursetemplate($column, $item) {
        return empty($item->$column) ? get_string('no') : get_string('yes');
    }
}


/**
 * Defines the form for denying a pending request.
 */
class pending_request_deny_form extends moodleform {
    function definition() {
        $mform = &$this->_form;

        $request = $this->_customdata;

        $mform->addElement('hidden', 'request');

        $title =& $mform->createElement('text', 'title', get_string('title', 'block_course_request'));
        $title->freeze();
        $mform->addElement($title);

        $mform->addElement('static', 'spacer', '');

        $mform->addElement('static', 'confirmmessage', '', get_string('denialconfirm', 'block_course_request', $request->title));

        $mform->addElement('textarea', 'statusnote', get_string('note', 'block_course_request'), array('cols' => '40', 'rows' => '5'));
        $mform->setType('statusnote', PARAM_NOTAGS);

        $this->add_action_buttons(true, get_string('denythisrequest', 'block_course_request'));
    }

}

/**
 * Defines the form for approving a pending request.
 */
class pending_request_approve_form extends create_form {

    /**
     * Adds fields to this form which are relevant to the course, either
     * new or old, that a class is being requested for, including any associated
     * validation rules
     */
    protected function add_course_info() {
        // add the standard fields
        parent::add_course_info();

        $mform =& $this->_form;

        // remove the course dropdown
        $mform->removeElement('courseid');

        // disable editing of the course name
        $mform->hardFreeze('title');
        // don't display as required, since not editable
        $course_name_element =& $mform->getElement('title');
        $course_name_element->setLabel(get_string('title', 'block_course_request'));
    }

    /**
     * Adds fields to this form that are relevant to the user making the request
     */
    protected function add_user_info() {
        // add the standard fields
        parent::add_user_info();

        $mform =& $this->_form;

        // disable editing of firstname, lastname and email
        $mform->hardFreeze('first');
        $mform->hardFreeze('last');
        $mform->hardFreeze('email');
    }

    /**
     * Adds fields to this form that are relevant to denial/approval and related feedback
     */
    protected function add_feedback() {
        $mform =& $this->_form;

        // section header
        $mform->addElement('header', 'feedbackheader', get_string('feedbackheader', 'block_course_request'));

        // approve/deny action
        $choices = array('deny'    => get_string('deny', 'block_course_request'),
                         'approve' => get_string('approve', 'block_course_request'));
        $mform->addElement('select', 'approvalaction', get_string('action', 'block_course_request'), $choices);

        // comments
        $mform->addElement('static', 'spacer', '', '');
        $mform->addElement('static', 'comments_description', '', get_string('comments_description', 'block_course_request'));
        $mform->addElement('textarea', 'comments', get_string('comments', 'block_course_request'), array('cols' => '40', 'rows' => '5'));
        $mform->setType('comments', PARAM_NOTAGS);
    }

    /**
     * Main form definition method, which augments the
     * elements on the form represented by this object
     */
    public function definition() {
        $mform =& $this->_form;

        // for storing the request id
        $mform->addElement('hidden', 'request');

        $this->add_course_info();

        $this->add_class_info();

        $this->add_user_info();

        // section for denial/approval-related information
        $this->add_feedback();

        $this->add_action_buttons();
    }

    /**
     * Removes custom fields from the current form for a particular context level
     *
     * @param  string  $contextlevel_name  Name of the context level for which we are removing fields
     * @uses   $DB
     */
    function remove_form_custom_fields($contextlevel_name) {
        global $DB;
        $mform =& $this->_form;

        $contextlevel = context_elis_helper::get_level_from_name($contextlevel_name);

        $fields = $DB->get_records('block_course_request_fields', array('contextlevel' => $contextlevel));
        $fields = $fields ? $fields : array();
        foreach ($fields as $reqfield) {
            $field = new field($reqfield->fieldid);

            if (!$field->id || !isset($field->owners['manual'])) {
                // skip nonexistent fields, or fields without manual editing
                continue;
            }

            $manual = new field_owner($field->owners['manual']);

            if (empty($manual->param_edit_capability)) {
                // remove the field from the form
                $mform->removeElement("field_{$field->shortname}");
                $mform->removeElement("fieldisrequired_field_{$field->shortname}_{$contextlevel_name}");
            }
        }
    }

    /**
     * For new course requests, removes class fields if we are not forcing the creation
     * of a class with the new course
     */
    function remove_class_fields() {
        global $CFG;

        $mform =& $this->_form;

        // go through all configured class fields and remove them if applicable
        $create_class_with_course = !isset($CFG->block_course_request_create_class_with_course) ||
                                    !empty($CFG->block_course_request_create_class_with_course);

        if (!$create_class_with_course) {
            // not forcing the creation of a class with every course created

            // remove the fields that will always be there
            $mform->removeElement('classheader');
            $mform->removeElement('clsidnumber');
            $mform->removeElement('usecoursetemplate');

            // determine if class fields are enabled
            $show_class_fields = !isset($CFG->block_course_request_use_class_fields) ||
                                 !empty($CFG->block_course_request_use_class_fields);

            if ($show_class_fields) {
                // class-level custom fields would have been displayed, so remove them
                $this->remove_form_custom_fields('class');
            }
        }
    }

    /**
     * Removes custom fields when approving a request for only a class
     */
    function remove_course_fields() {
        $this->remove_form_custom_fields('course');
    }

    /**
     * For tweaking based on data, specifically for disabling the course template
     * functionality if not appropriate
     * @uses $DB
     */
    function definition_after_data() {
        global $DB;

        $mform =& $this->_form;

        // get the submitted id
        if ($requestid = $mform->getElementValue('request')) {
            if ($request = $DB->get_record('block_course_request', array('id' => $requestid))) {
                if (empty($request->courseid)) {
                    // new course, so disable
                    $mform->hardFreeze('usecoursetemplate');

                    // remove class-level custom fields if the request is only for a course
                    $this->remove_class_fields();
                } else {
                    // using existing course, so disable idnumber editing
                    $mform->hardFreeze('crsidnumber');
                    // don't display as required, since not editable
                    $course_idnumber_element =& $mform->getElement('crsidnumber');
                    $course_idnumber_element->setLabel(get_string('courseidnumber', 'block_course_request'));

                    $temp = coursetemplate::find(new field_filter('courseid', $request->courseid));
                    if ($temp->valid()) {
                        $temp = $temp->current();
                    }
                    if (empty($temp->id) || empty($temp->location)) {
                        // no template, so disable
                        $mform->hardFreeze('usecoursetemplate');
                    }

                    // remove course-level custom fields if the request is only for a class
                    $this->remove_course_fields();
                }
            }
        }
    }

    /**
     * Validates the inclusion and uniqueness of course and class information
     *
     * @param  object  $data   Submitted form data
     * @param  array   $files  List of submitted files
     * @uses   $CFG
     * @uses   $DB
     */
    function validation($data, $files) {
        global $CFG, $DB;

        $errors = array();

        if ($data['approvalaction'] == 'approve') {
            require_once($CFG->dirroot .'/elis/program/lib/data/pmclass.class.php');

            $recordid = $data['request']; // TBV: addslashes()

            // note: no need to call addslashes because data is already escaped
            if ($DB->record_exists_select('block_course_request', "id = ? AND courseid != 0", array($recordid))) {
                // new class for existing course
                if (empty($data['clsidnumber'])) {
                    $errors['clsidnumber'] = get_string('required');
                } else if ($DB->record_exists(pmclass::TABLE, array('idnumber' => $data['clsidnumber']))) {
                    $errors['clsidnumber'] = get_string('idnumber_already_used', 'elis_program');
                }
            } else {
                require_once($CFG->dirroot .'/elis/program/lib/data/course.class.php');
                if (empty($CFG->block_course_request_create_class_with_course)) {
                    // new course with no associated class
                    if (empty($data['crsidnumber'])) {
                        $errors['crsidnumber'] = get_string('required');
                    } else if ($DB->record_exists(course::TABLE, array('idnumber' => $data['crsidnumber']))) {
                        $errors['crsidnumber'] = get_string('idnumber_already_used', 'elis_program');
                    }
                } else {
                    // new course with an associated class
                    if (empty($data['crsidnumber'])) {
                        $errors['crsidnumber'] = get_string('required');
                    } else if ($DB->record_exists(course::TABLE, array('idnumber' => $data['crsidnumber']))) {
                        $errors['crsidnumber'] = get_string('idnumber_already_used', 'elis_program');
                    }

                    if (empty($data['clsidnumber'])) {
                        $errors['clsidnumber'] = get_string('required');
                    } else if ($DB->record_exists(pmclass::TABLE, array('idnumber' => $data['clsidnumber']))) {
                        $errors['clsidnumber'] = get_string('idnumber_already_used', 'elis_program');
                    }
                }
            }

            // Check for required custom fields
            foreach ($data as $data_key => $data_value) {
                $key_array = explode('_', $data_key);
                $req_check = !empty($key_array[0]) ? $key_array[0] : null;
                if ($req_check == 'fieldisrequired' && !empty($data_value)) {
                    $check_var = '';
                    for ($i = 1; $i < count($key_array) - 1; $i++) {
                        $check_var .= $key_array[$i] . '_';
                    }
                    $check_context = $key_array[$i];
                    $check_var = rtrim($check_var,'_');
                    if (($check_context == 'course' && empty($data['courseid'])) ||
                        ($check_context == 'class')) {
                        if (isset($data[$check_var]) && $data[$check_var] == '') {
                            $errors[$check_var] = 'Required';
                        }
                    }
                }
            }

        }

        return $errors;
    }
}

