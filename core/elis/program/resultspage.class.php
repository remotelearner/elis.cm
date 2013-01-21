<?php
/**
 * Common page class for role assignments
 *
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2010 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2008-2010 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

define('ACTION_TYPE_TRACK', 0);
define('ACTION_TYPE_CLASS', 1);
define('ACTION_TYPE_PROFILE', 2);

require_once elispm::lib('data/resultsengine.class.php');
require_once elispm::lib('lib.php');
require_once elispm::lib('page.class.php');
require_once elispm::file('form/resultsform.class.php');

abstract class enginepage extends pm_page {
    const LANG_FILE = 'elis_program';

    public $data_class = 'resultsengine';
    public $child_data_class = 'resultsengineaction';
    public $form_class = 'cmEngineForm';

    protected $parent_page;
    public $section;
    protected $_form;

    public function __construct($params = null) {
        parent::__construct($params);
        $this->section = $this->get_parent_page()->section;
    }

    abstract protected function get_context();

    abstract protected function get_parent_page();

    abstract protected function get_course_id();

    /**
     * Check if the user can edit
     *
     * @return bool True if the user has permission to use the edit action
     */
    function can_do_edit() {
        return $this->can_do_default();
    }

    /**
     * Check if the user can delete from cache
     *
     * @return bool True if the user has permission to use the cachedelete action
     */
    function can_do_cachedelete() {
        return $this->can_do_default();
    }

    /**
     * Check if the user can delete (from db)
     *
     * @return bool True if the user has permission to use the delete action
     */
    function can_do_delete() {
        return $this->can_do_default();
    }

    /**
     * Check if the user can do the default action
     *
     * @return bool True if the user has permission to use the default action
     */
    function can_do_default() {
        return has_capability('elis/program:'. $this->type .'_edit', $this->get_context());
    }

    /**
     * Get the id of this results engine
     *
     * @return int The id of the results engine (0 if doesn't exist).
     */
    function get_engine_id() {
        $contextid  = $this->get_context()->id;
        $rid        = $this->optional_param('rid', 0, PARAM_INT);
        $obj        = $this->get_new_data_object($rid);

        if ($rid < 1) {
            $filter    = new field_filter('contextid', $contextid);
            $results   = $obj->find($filter);
            if (! empty($results->current()->id)) {
                $rid       = $results->current()->id;
            }
        }
        return $rid;
    }

    /**
     * Get the action type.
     *
     * @param array $data An array of data values
     * @return int The action type value.
     * @uses $DB
     */
    function get_action_type() {
        global $DB;

        $type = optional_param('result_type_id', -1, PARAM_INT);

        // If a button hasn't been pressed we have to look in the db.
        if ($type == -1) {
            $params = array('resultsid' => $this->get_engine_id());
            if (! $type = $DB->get_field('crlm_results_action', 'actiontype', $params, IGNORE_MULTIPLE)) {
                $type = ACTION_TYPE_TRACK;
            }
        }

        return $type;
    }

    /**
     * Return the engine form
     *
     * @return object The engine form
     */
    protected function get_engine_form($cache = '') {

        $known      = false;
        $contextid  = $this->get_context()->id;
        $id         = $this->optional_param('id', 0, PARAM_INT);
        $active     = $this->optional_param('active', -1, PARAM_INT);
        $rid        = $this->get_engine_id();
        $obj        = $this->get_new_data_object($rid);

        $filter    = new field_filter('id', $rid);

        if ($obj->exists($filter)) {
            $obj->id = $rid;
            $obj->load();
            $known = true;
        }

        $target    = $this->get_new_page(array('action' => 'edit', 'id' => $id), true);

        $obj->contextid = $contextid;

        $params = $obj->to_array();
        $params['id'] = $id;
        $params['rid'] = $rid;
        $params['courseid'] = $this->get_course_id();
        $params['contextid'] = $contextid;
        $params['enginetype'] = $this->type;
        $params['actiontype'] = $this->get_action_type();

        // If set on the form use the form value over db value.
        if ($active > -1) {
            $params['active'] = $active;
        }

        $params['cache'] = $cache;

        $form = new cmEngineForm($target->url, $params);
        $form->set_data($params);

        return $form;
    }

    /**
     * Get the page with tab definitions
     */
    function get_tab_page() {
        return $this->get_parent_page();
    }

    /**
     * Get the default pate title.
     */
    function get_page_title_default() {
        return print_context_name($this->get_context(), false);
    }

    /**
     * Build the default navigation bar.
     */
    function build_navbar_default($who = null) {

        //obtain the base of the navbar from the parent page class
        $parent_template = $this->get_parent_page()->get_new_page();
        $parent_template->build_navbar_view();
        $this->_navbar = $parent_template->navbar;

        //add a link to the first role screen where you select a role
        $id = $this->required_param('id', PARAM_INT);
        $page = $this->get_new_page(array('id' => $id), true);
        $this->navbar->add(get_string('results_engine', self::LANG_FILE), $page->url);
    }

    /**
     * Print the tabs
     */
    function print_tabs() {
        $id = $this->required_param('id', PARAM_INT);
        $this->get_parent_page()->print_tabs(get_class($this), array('id' => $id));
    }

    /**
     * Return the page parameters for the page.  Used by the constructor for
     * calling $this->set_url().
     *
     * @return array
     */
    protected function _get_page_params() {
        $params = parent::_get_page_params();

        $id = $this->required_param('id', PARAM_INT);
        $params['id'] = $id;

        return $params;
    }

    /**
     * Display the default page
     */
    function display_default() {
        $this->display_edit();
    }

    /**
     * Display the edit page
     */
    function display_edit() {
        if (!isset($this->_form)) {
            throw new ErrorException('Display called before Do');
        }

        $type = $this->get_action_type();

        echo '
        <script type="text/javascript">
            $(function(){

                // Accordion
                $("#accordion").accordion({ header: "h3", active: '. $type .' });
                $("#accordion").accordion({ change:
                    function(event, ui) {
                        document.getElementById("result_type_id").value = (ui.options.active);
                    }
                });

            });
        </script>';

        $this->print_tabs();
        $this->_form->display();
    }

    /**
     * Do the default
     *
     * Set up the editing form before save.
     */
    function do_default() {
        $form = $this->get_engine_form();
        $this->_form = $form;
        $this->display('default');
    }

    /**
     * Process the edit
     */
    function do_edit() {

        $known = false;
        $id         = $this->required_param('id', PARAM_INT);
        $actiontype = $this->get_action_type();
        $cache = '';

        if ($actiontype >= 0) {
            // TODO: Figure out a good place to store this lookup table.
            $types   = array(ACTION_TYPE_CLASS => 'class', ACTION_TYPE_TRACK => 'track', ACTION_TYPE_PROFILE => 'profile');
            $type    = $types[$actiontype];
            $cacheid = $type .'_cache';
            $cache   = $this->optional_param($cacheid, '', PARAM_SEQUENCE);
        }
        $form = $this->get_engine_form($cache);

        if ($form->is_cancelled()) {
            $target = $this->get_new_page(array('action' => 'default', 'id' => $id), true);
            redirect($target->url);
            return;
        }

        $data           = $form->get_data();

        if ($form->no_submit_button_pressed()) {

            $this->_form = $form;
            $this->display('edit');

        } else if ($data) {

            require_sesskey();

            if (array_key_exists('track_assignment', $data) or
                array_key_exists('class_assignment', $data) or
                array_key_exists('profile_assignment', $data)) {

                $this->_form = $form;
                $this->display('edit');

            } else {

                $obj = $this->get_new_data_object(0);
                $obj->set_from_data($data);
                if ($data->rid > 0) {
                    $obj->id = $data->rid;
                } else {
                    unset($obj->id);
                }

                $obj->save();

                // Updating existing score ranges
                $typename = $form->types[$actiontype];
                $data = (array) $data;


                // We don't keep the type of action in the parent table so child must be cleared
                $this->delete_data();

                // Save new score ranges submitted
                $this->save_data($data, $typename, $actiontype, $obj->id);

                $target = $this->get_new_page(array('action' => 'default',
                                                    'id' => $id), false);
                redirect($target->url);
            }


        } else {
            $this->_form = $form;
            $this->display('edit');
        }
    }

    /**
     * Process the delete
     */
    function do_delete() {
        $id  = $this->required_param('id', PARAM_INT);
        $aid = $this->optional_param('aid', 0, PARAM_INT);
        $rid = $this->get_engine_id();

        if ($aid > 0) {
            $rec = $this->get_new_child_data_object($aid);

            // To prevent "reload" or "double-click" problems
            if ($rec->exists()) {
                $rec->load();

                // Confirm it's an action for this page, to prevent capability circumvention
                if ($rec->resultsid == $rid) {
                    $rec->delete();
                }
            }
        }

        $this->do_default();
    }

    /**
     * Process the delete for cached items
     */
    function do_cachedelete() {
       $cache = $this->optional_param('actioncache', '', PARAM_SEQUENCE);
       $aid   = $this->optional_param('aid', 0, PARAM_INT);

       $cachedata = explode(',', $cache);
       unset($cachedata[4 * $aid+3]);
       unset($cachedata[4 * $aid+2]);
       unset($cachedata[4 * $aid+1]);
       unset($cachedata[4 * $aid]);

       $cache = implode(',', $cachedata);

       $form = $this->get_engine_form($cache);
       $this->_form = $form;
       $this->display('edit');
    }

    /**
     * Save new submitted data
     *
     * @param array  $data              The form data
     * @param string $type              The name of the action type
     * @param int    $actiontype        The id of the action type
     * @param int    $results_engine_id The id of the result engine entry
     */
    protected function save_data($data, $type, $actiontype, $results_engine_id) {

        $savetype = '';
        $instance = array();
        $dataobj  = (object) $data;

        // Since the selected value may be a hidden value, it might be set to a legacy value
        // So only check valid min and max rows.
        $pattern  = "/${type}_([0-9]+)_m/";

        // Check for existing data regarding track/class/profile actions
        foreach($data as $key => $value) {

            //error_log("resultspage::save_data(); checking K/V {$key} => {$value}");
            if (preg_match($pattern, $key, $matches)) {
                if (!isset($data[$key]) || !is_numeric($data[$key])) {
                    // If value is empty then it must be an empty score range row
                    // because form validation will catch any incomplete rows
                    continue;
                }

                if (! array_key_exists($matches[1], $instance)) {
                    $instance[$matches[1]] = '';
                }
            }
        }

        $updaterec = new stdClass();
        $field = '';
        $fieldvalue = false;

        switch ($actiontype) {
            case ACTION_TYPE_TRACK:
                $field = 'trackid';
                break;
            case ACTION_TYPE_CLASS:
                $field = 'classid';
                break;
            case ACTION_TYPE_PROFILE:
                $field = 'fieldid';
                $fieldvalue = true;
                break;
        }

        $fieldmap = array();
        $fieldmap['actiontype'] = 'result_type_id';

        foreach ($instance as $recid => $dummy_val) {

            $updaterec = $this->get_new_child_data_object();
            $updaterec->resultsid = $results_engine_id;

            $key = "{$type}_{$recid}_min";
            $fieldmap['minimum'] = $key;

            $key = "{$type}_{$recid}_max";
            $fieldmap['maximum'] = $key;

            $key = "{$type}_{$recid}_selected";
            $fieldmap[$field] = $key;

            if ($fieldvalue) {
                $key = "{$type}_{$recid}_value";
                $fieldmap['fielddata'] = $key;
            }

            $updaterec->set_from_data($dataobj, true, $fieldmap);
            $updaterec->save();
        }
    }

    /**
     * Delete existing data
     *
     * @param int    $actiontype The action type id
     */
    protected function delete_data() {
        $record = $this->get_new_child_data_object();

        $filters = array(
            new field_filter('resultsid', $this->get_engine_id()),
        );

        $record->delete_records($filters);
    }

    /**
     * Returns a new instance of the data object class this page manages.
     *
     * @param mixed $data Usually either the id or parameters for object, false for blank
     * @return object The data object pulled form the database if an id was passed
     */
    public function get_new_data_object($data=false) {
        return new $this->data_class($data);
    }

    /**
     * Returns a new instance of the child data object class this page manages.
     *
     * @param mixed $data Usually either the id or parameters for object, false for blank
     * @return object The data object pulled form the database if an id was passed
     */
    public function get_new_child_data_object($data=false) {
        return new $this->child_data_class($data);
    }

}

/**
 * Engine page for courses
 *
 * @author Tyler Bannister <tyler.bannister@remote-learner.net>
 */
class course_enginepage extends enginepage {
    public $pagename = 'crsengine';
    public $type     = 'course';

    /**
     * Get context
     *
     * @return object The context
     */
    protected function get_context() {
        if (!isset($this->context)) {
            $id = $this->required_param('id', PARAM_INT);

            $this->set_context(context_elis_course::instance($id));
        }
        return $this->context;
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
     * Get parent page object
     *
     * @return object An object of the same type as the parent page
     * @uses $CFG
     * @uses $CURMAN
     */
    protected function get_parent_page() {
        if (!isset($this->parent_page)) {
            global $CFG, $CURMAN;
            require_once elispm::file('coursepage.class.php');
            $id = $this->required_param('id', PARAM_INT);
            $this->parent_page = new coursepage(array('id' => $id,
                                                      'action' => 'view'));
        }
        return $this->parent_page;
    }

    /**
     * Check if the user can do the default action
     *
     * @return bool True if the user has permission to use the default action
     */
    function can_do_default() {
        return has_capability('elis/program:course_edit', $this->get_context());
    }
}

/**
 * Engine page for classes
 *
 * Classes have an extra form field that courses don't have.
 *
 * @author Tyler Bannister <tyler.bannister@remote-learner.net>
 */
class class_enginepage extends enginepage {
    public $pagename = 'clsengine';
    public $type     = 'class';

    /**
     * Get context
     *
     * @return object The context
     */
    protected function get_context() {
        if (!isset($this->context)) {
            $id = $this->required_param('id', PARAM_INT);

            $this->set_context(context_elis_class::instance($id));
        }
        return $this->context;
    }

    /**
     * Get the course id.
     *
     * @return int The course id
     * @uses $DB
     */
    protected function get_course_id() {
        global $DB;

        $classid  = $this->required_param('id', PARAM_INT);
        $courseid = $DB->get_field('crlm_class', 'courseid', array('id' => $classid));
        return $courseid;
    }

    /**
     * Get parent page object
     *
     * @return object An object of the same type as the parent page
     * @uses $CFG
     * @uses $CURMAN
     */
    protected function get_parent_page() {
        if (!isset($this->parent_page)) {
            global $CFG, $CURMAN;
            require_once elispm::file('pmclasspage.class.php');
            $id = $this->required_param('id');
            $this->parent_page = new pmclasspage(array('id' => $id,
                                                       'action' => 'view'));
        }
        return $this->parent_page;
    }
}
