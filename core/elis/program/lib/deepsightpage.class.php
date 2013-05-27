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
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') or die();

require_once($CFG->libdir.'/weblib.php');
require_once(elispm::lib('lib.php'));
require_once(elispm::lib('page.class.php'));
require_once(dirname(__FILE__).'/deepsight/lib/lib.php');

/**
 * Base page class for a deepsight association page.
 */
abstract class deepsightpage extends pm_page {
    const LANG_FILE = 'elis_program';
    /**
     * The name of the class used for data objects
     */
    public $data_class;

    /**
     * The name of the class used for the add/edit form
     */
    public $form_class;

    public $tabs;
    public $_form;
    protected $assigned_table_class = '';
    protected $assign_table_class = '';

    /**
     * Construct the assigned datatable.
     *
     * @param string $uniqid A unique ID to assign to the datatable object.
     * @return deepsight_datatable The datatable object.
     */
    abstract protected function construct_assigned_table($uniqid = null);

    /**
     * Construct the unassigned datatable.
     *
     * @param string $uniqid A unique ID to assign to the datatable object.
     * @return deepsight_datatable The datatable object.
     */
    abstract protected function construct_unassigned_table($uniqid = null);

    /**
     * Determine whether the current user has certain permissions for a given ID and context level.
     * @param array $perms An array of permissions the user must have to return true.
     * @param int $ctxlevel The context level name.
     * @param int $id The instance ID to check for.
     * @return bool Whether the user has all required permissions.
     */
    protected function has_perms_for_element(array $perms, $ctxlevel, $id) {
        global $USER;
        foreach ($perms as $perm) {
            $ctx = pm_context_set::for_user_with_capability($ctxlevel, $perm, $USER->id);
            if ($ctx->context_allowed($id, $ctxlevel) !== true) {
                return false;
            }
        }
        return true;
    }

    /**
     * Whether the user can see and manage current associations.
     * @return bool Whether the user can see and manage current associations.
     */
    public function can_do_default() {
        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('elis/program:manage', $context);
    }

    /**
     * Whether the user can add new associations - Implement in subclass.
     * @return bool Whether the user can add new associations.
     */
    public function can_do_add() {
        return false;
    }

    /**
     * Returns an instance of the page class that should provide the tabs for this association page.
     * This allows the association interface to be located "under" the general management interface for
     * the data object whose associations are being viewed or modified.
     *
     * @param $params
     * @return object
     */
    public function get_tab_page($params=array()) {
        return new $this->tab_page($params);
    }

    public function is_bare() {
        $mode = $this->optional_param('mode', '', PARAM_ACTION);
        return $mode == 'bare';
    }

    public function print_header($_) {
        if (!$this->is_bare()) {
            parent::print_header($_);
            $this->print_tabs();
        }
    }

    /**
     * Prints the tab bar describe by the $tabs instance variable.
     * - lifted from managmentpage.class.php
     * @param $selected name of tab to display as selected
     * @param $params extra parameters to insert into the tab links, such as an id
     */
    public function print_tabs() {
        $id = $this->required_param('id', PARAM_INT);
        $page = $this->get_tab_page();
        $params = array('id' => $id);
        $rows = array();
        $row = array();

        // Main Tab List.
        foreach ($page->tabs as $tab) {
            $tab = $this->add_defaults_to_tab($tab);
            if ($tab['showtab'] === true) {
                $target = new $tab['page'](array_merge($tab['params'], $params));
                if (!$target->can_do()) {
                    continue;
                }
                $row[] = new tabobject($tab['tab_id'], $target->url, $tab['name']);
            }
        }
        if (!empty($row)) {
            $rows[] = $row;
        }

        // Sub-menu.
        $assignedpage = $this->get_new_page(array('id' => $id, 'action' => 'default'));
        $unassignedpage = $this->get_new_page(array('id' => $id, 'action' => 'add'));
        list($langassigned, $langunassigned) = $this->get_assigned_strings();

        $rows[] = array(
            new tabobject('assigned', $assignedpage->url, $langassigned),
            new tabobject('unassigned', $unassignedpage->url, $langunassigned)
        );

        $selectedtab = ($this->is_assigning() === true) ? 'unassigned' : 'assigned';
        print_tabs($rows, $selectedtab, array(), array(get_class($this)));
    }

    /**
     * Get the strings to use for the "assigned" and "unassigned" headers.
     *
     * @return array An array consisting of the assigned header, and the unassigned header - in that order.
     */
    protected function get_assigned_strings() {
        return array(get_string('ds_assigned', 'elis_program'), get_string('ds_unassigned', 'elis_program'));
    }

    protected function is_assigning() {
        return ($this->optional_param('action', 'default', PARAM_ACTION) == 'add') ? true : false;
    }

    public function get_new_data_object($id = false) {
        return new $this->data_class($id);
    }

    public function get_page_title_default() {
        $id = $this->required_param('id', PARAM_INT);
        $tabpage = $this->get_tab_page(array('action' => 'view', 'id' => $id));
        return $tabpage->get_page_title().': '.get_string('breadcrumb_'.get_class($this), self::LANG_FILE);
    }

    public function get_title_default() {
        return $this->get_page_title_default();
    }

    public function build_navbar_default($who = null, $addparent = true, $params = array()) {
        $id = $this->required_param('id', PARAM_INT);
        $params = array('action' => 'view', 'id' => $id);
        if ($parentid = $this->optional_param('parent', 0, PARAM_INT)) {
            $params['parent'] = $parentid;
        }
        $tabpage = $this->get_tab_page($params);
        $tabpage->build_navbar_view();
        $this->_navbar = $tabpage->navbar;
    }

    /**
     * Inserts default values into the tabs array provided by the page class.
     *
     * @param $tab tab to set the defaults for
     */
    public function add_defaults_to_tab($tab) {
        $defaults = array('params' => array(), 'showbutton' => false, 'showtab' => 'false', 'image' => '');
        return array_merge($defaults, $tab);
    }

    protected function get_context() {
    }

    /**
     * Routes ajax requests to the applicable object and displays response.
     */
    public function do_deepsight_response() {
        global $DB;

        $context = $this->get_context();
        $mode = $this->required_param('m');

        $classid = $this->required_param('id', PARAM_INT);
        $tabletype = $this->required_param('tabletype', PARAM_ALPHA);
        if (!in_array($tabletype, array('assigned', 'unassigned'), true)) {
            throw new Exception('Invalid table type specified');
        }

        // Authorization.
        $assignedauthorized = ($tabletype === 'assigned' && $this->can_do_default() === true) ? true : false;
        $unassignedauthorized = ($tabletype === 'unassigned' && $this->can_do_add() === true) ? true : false;
        if ($assignedauthorized !== true && $unassignedauthorized !== true) {
            echo safe_json_encode(array('result' => 'fail', 'msg' => get_string('not_permitted', 'elis_program')));
        }

        // Build the table.
        $uniqid = optional_param('uniqid', null, PARAM_CLEAN);
        $table = ($tabletype === 'assigned')
            ? $this->construct_assigned_table($uniqid)
            : $this->construct_unassigned_table($uniqid);

        if ($mode === 'action') {
            // We'll use page-specific can_do actions to authorize access to each requested action.
            $actionname = required_param('actionname', PARAM_ALPHAEXT);
            $candoactionmethod = 'can_do_action_'.$actionname;
            if (method_exists($this, $candoactionmethod) && $this->$candoactionmethod() === true) {
                $table->respond($mode);
            } else {
                echo safe_json_encode(array('result' => 'fail', 'msg' => get_string('not_permitted', 'elis_program')));
            }
        } else {
            $table->respond($mode);
        }
    }

    /**
     * Display an assignment table.
     */
    public function display_add() {
        global $DB, $PAGE, $CFG;

        $table = $this->construct_unassigned_table();

        // Assemble bulk action panel.
        $bulktitle = get_string('ds_bulkassignment', 'elis_program');
        $bulkactionpanel = new deepsight_bulkactionpanel_standard('bulkenrol', $bulktitle, $table, $table->get_actions());

        // HTML.
        echo $table->get_html();
        echo $bulkactionpanel->get_html();

        // JS.
        echo '<script src="'.$CFG->wwwroot.'/elis/program/lib/deepsight/js/jquery-1.9.1.min.js"></script>';
        $jsdeps = $table->get_js_dependencies();
        foreach ($jsdeps as $jsfile) {
            $PAGE->requires->js($jsfile);
        }

        echo '<script>(function($) {';
        echo '$(function() {';
        echo $table->get_init_js();
        echo $bulkactionpanel->get_init_js();
        echo '});';
        echo '})(jQuery); jQuery.noConflict();</script>';
    }

    /**
     * Display an assigned table.
     */
    public function display_default() {
        global $PAGE, $CFG;

        $table = $this->construct_assigned_table();

        // Assemble bulk action panel.
        $bulktitle = get_string('ds_bulkedits', 'elis_program');
        $bulkactionpanel = new deepsight_bulkactionpanel_standard('bulkenrol', $bulktitle, $table, $table->get_actions());

        // HTML.
        echo $table->get_html();
        echo $bulkactionpanel->get_html();

        // JS.
        echo '<script src="'.$CFG->wwwroot.'/elis/program/lib/deepsight/js/jquery-1.9.1.min.js"></script>';
        $jsdeps = $table->get_js_dependencies();
        foreach ($jsdeps as $jsfile) {
            $PAGE->requires->js($jsfile);
        }

        echo '<script>(function($) {'."\n";
        echo '$(function() {';
        echo $table->get_init_js();
        if ($this->can_do('bulkedit')) {
            echo $bulkactionpanel->get_init_js();
        }
        echo '});';
        echo "\n".'})(jQuery); jQuery.noConflict();</script>';
    }
}
