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
 * @package    elis_program
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();
require_once(elispm::lib('page.class.php'));
require_once(elispm::file('form/certificateform.class.php'));
require_once(elispm::lib('data/certificatesettings.class.php'));
require_once(elispm::lib('certificate.php'));

/**
 * Page to display certificates.
 */
abstract class certificatepage extends pm_page {

    /**
     * Language file used within this class.
     */
    const LANG_FILE = 'elis_program';

    /**
     * @var string The data class used within this class.
     */
    public $data_class = 'certificatesettings';

    /**
     * @var string The form class used within this class.
     */
    public $form_class = 'certificateform.';

    /**
     * @var string This page's parent.
     */
    protected $parentpage;

    /**
     * @var string The section this page belongs to.
     */
    protected $section;

    /**
     * @var object The form object for this page.
     */
    protected $_form;

    /**
     * Constructor method for certificatepage class, this sets the section instance
     * variable to the parent class section instance variable
     * @param array $params An array of parameters
     */
    public function __construct($params = null) {
        parent::__construct($params);
        $this->section = $this->get_parent_page()->section;
    }

    /**
     * Check if the user can edit
     * @return bool True if the user has permission to use the edit action
     */
    public function can_do_edit() {
        return $this->can_do_default();
    }

    /**
     * Check if the user can delete (from db)
     * @return bool True if the user has permission to use the delete action
     */
    public function can_do_delete() {
        return $this->can_do_default();
    }

    /**
     * Check if the user can do the default action
     *
     * @return bool True if the user has permission to use the default action
     */
    public function can_do_default() {
        return has_capability('elis/program:'.$this->type.'_edit', $this->get_context());
    }

    /**
     * Get the page with tab definitions
     * @return object Parent page instance
     */
    public function get_tab_page() {
        return $this->get_parent_page();
    }

    /**
     * Get the default pate title.
     * @return string the human readable context name.
     */
    public function get_page_title_default() {
        return print_context_name($this->get_context(), false);
    }

    /**
     * Build the default navigation bar.
     * @param object $who Not sure what this is actually supposed to be null seems to be the parameter of choice.  But there is
     *                    not documentation in parent functions as to what $who is
     */
    public function build_navbar_default($who = null) {
        // Obtain the base of the navbar from the parent page class.
        $parenttemplate = $this->get_parent_page()->get_new_page();
        $parenttemplate->build_navbar_view();
        $this->_navbar = $parenttemplate->navbar;

        // Add a link to the first role screen where you select a role.
        $id = $this->required_param('id', PARAM_INT);
        $page = $this->get_new_page(array('id' => $id), true);
        $this->navbar->add(get_string('certificate_settings', self::LANG_FILE), $page->url);
    }

    /**
     * Print the tabs
     */
    public function print_tabs() {
        $id = $this->required_param('id', PARAM_INT);
        $this->get_parent_page()->print_tabs(get_class($this), array('id' => $id));
    }

    /**
     * Return the page parameters for the page.  Used by the constructor for calling $this->set_url().
     * @return array Array containing the id of the course description
     */
    protected function _get_page_params() {
        $params = parent::_get_page_params();

        $id = $this->required_param('id', PARAM_INT);
        $params['id'] = $id;

        return $params;
    }

    /**
     * Display the default page.  This function simply displays the edit page
     * @see display_edit()
     */
    public function display_default() {
        $this->display_edit();
    }

    /**
     * Display the edit page.
     * @throws ErrorException If the _form instance variable is not set
     */
    public function display_edit() {
        if (!isset($this->_form)) {
            throw new ErrorException('Display called before Do');
        }

        $this->print_tabs();
        $this->_form->display();
    }

    /**
     * Do the default, display the default page @see display_default()
     *
     * Set up the editing form before save.
     */
    public function do_default() {
        $this->display('default');
    }

    /**
     * This function processes the form submission and calls the appropriate
     * function for the buttion that was used to submit.
     */
    public function do_edit() {
        $id          = $this->required_param('id', PARAM_INT);
        $entityid    = $this->required_param('entity_id', PARAM_INT);
        $entitytype  = $this->required_param('entity_type', PARAM_TEXT);

        $target     = $this->get_new_page(array('action' => 'edit', 'id' => $id), true);
        $params     = array('id' => $id, 'entity_id' => $entityid, 'entity_type' => $entitytype);

        $form = new certificateform($target->url);

        $data = $form->get_data();

        // If save changes, else if deleted.
        if (!empty($data) && isset($data->submitbutton)) {

            // If there is no rec id then insert a new record, else update the record.
            $record   = $this->get_new_data_object(0);

            $time     = time();
            $data->id = $data->rec_id;
            unset($data->submitbutton);
            unset($data->rec_id);

            $record->set_from_data($data);

            if ($data->id > 0) {
                $record->id           = $data->id;
                $record->timemodified = $time;

            } else {
                unset($record->id);
                $record->timecreated  = $time;
                $record->timemodified = $time;
            }

            $record->save();

            $target = $this->get_new_page(array('action' => 'default', 'id' => $id), true);
            redirect($target->url);
        }

        // Initialize the rest of the parameters.
        $params['rec_id']         = $record->id;
        $params['cert_seal']      = $record->cert_seal;
        $params['cert_border']    = $record->cert_border;
        $params['cert_template']  = $record->cert_template;
        $params['disable']        = $record->disable;

        $form->set_data($params);
        $this->_form = $form;
        $this->display('default');

    }

    /**
     * This function retrieves a record by it's id
     * @param int $id ID record for crlm_certificate_settings record
     * @return object|bool Record object or false if something went wrong
     */
    public function get_data_object_by_id($id) {
        if (!is_int($id)) {
            return false;
        }

        $record = new $this->data_class($id);
        $record->load();

        if (empty($record->id)) {
            return false;
        } else {
            return $record;
        }
    }

    /**
     * Returns a new instance of the data object class this page manages.
     * @param mixed $data Usually either the id or parameters for object, false for blank
     * @return object The data object pulled form the database if an id was passed
     */
    public function get_new_data_object($data=false) {
        return new $this->data_class($data);
    }

    /**
     * This function returns a crlm_certificate_setting record based on a matching entity id and entity type
     * @param int $entityid The entity type id
     * @param string $entitytype The entity type string
     * @return object|bool Record object or false if nothing was found
     */
    public function get_data_object_by_entity($entityid = 0, $entitytype = '') {
        $record = false;
        $result = null;

        // Check if entity id is an integer.
        if (!is_int($entityid)) {
            return $record;
        }

        // Check if entity type is a valid entity type value.
        switch ($entitytype) {
            case CERTIFICATE_ENTITY_TYPE_PROGRAM:
            case CERTIFICATE_ENTITY_TYPE_COURSE:
            case CERTIFICATE_ENTITY_TYPE_LEARNINGOBJ:
            case CERTIFICATE_ENTITY_TYPE_CLASS:
                // Retrieve the record by the most common search criteria.
                $result = new $this->data_class();
                $result->get_data_by_entity($entityid, $entitytype);

                if (!empty($result->id)) {
                    $record = $result;
                }
                break;

            default:
                break;
        }

        return $record;
    }

}

/**
 * Page to display certificates for courses.
 */
class course_certificatepage extends certificatepage {

    /**
     * @var string A unique name for this page.
     */
    public $pagename = 'crscertificate';

    /**
     * @var string The type of certificate.
     */
    public $type     = 'course';

    /**
     * This function returns (or sets if not already initialized) a context object for the course description
     * @return object Course description context ojbect
     */
    protected function get_context() {
        if (!isset($this->context)) {
            $id = $this->required_param('id', PARAM_INT);

            $contextinstance = context_elis_course::instance($id);
            $this->set_context($contextinstance);
        }
        return $this->context;
    }

    /**
     * This function returns the parent page object
     * @return object A coursepage object
     */
    protected function get_parent_page() {
        if (!isset($this->parentpage)) {
            global $CFG, $CURMAN;
            require_once(elispm::file('coursepage.class.php'));
            $id = $this->required_param('id', PARAM_INT);
            $this->parentpage = new coursepage(array('id' => $id, 'action' => 'view'));
        }
        return $this->parentpage;
    }

    /**
     * Get the course id.
     *
     * @return int The course id
     */
    protected function get_course_id() {
        return $this->required_param('id', PARAM_INT);
    }

    /**
     * Check if the user can do the default action
     *
     * @return bool True if the user has permission to use the default action
     */
    public function can_do_default() {
        return has_capability('elis/program:course_view', $this->get_context());
    }

    /**
     * Check if the user can do edits
     *
     * @return bool True if the user has permission to do edits
     */
    public function can_do_edit() {
        return has_capability('elis/program:course_edit', $this->get_context());
    }

    /**
     * This function initializes the certificate form and sets the form instance to be used by the display_default() method
     */
    public function do_default() {
        $nosettingfound = true;
        $id      = $this->optional_param('id', 0, PARAM_INT);
        $target  = $this->get_new_page(array('action' => 'edit', 'id' => $id), true);

        // Look for an existing record in the crlm_certificate_settings table.
        $type    = CERTIFICATE_ENTITY_TYPE_COURSE;
        $params  = array('id' => $id, 'entity_id' => $id, 'entity_type' => $type);

        // If a record already exists, then we set the form to display the previous values.
        $record = $this->get_data_object_by_entity($id, CERTIFICATE_ENTITY_TYPE_COURSE);

        if (!empty($record)) {
            $nosettingfound          = false;
            $params['rec_id']        = $record->id;
            $params['cert_border']   = $record->cert_border;
            $params['cert_seal']     = $record->cert_seal;
            $params['cert_template'] = $record->cert_template;
            $params['disable']       = $record->disable;
        }

        $form = new certificateform($target->url, array('nosettingfound' => $nosettingfound));

        $form->set_data($params);
        if (!$this->can_do_edit()) {
            $form->freeze();
        }
        $this->_form = $form;
        $this->display('default');
    }

}
