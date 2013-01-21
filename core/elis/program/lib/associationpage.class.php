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

require_once elis::lib('table.class.php');
require_once elispm::lib('lib.php');
require_once elispm::lib('deprecatedlib.php'); // cm_error();
require_once elispm::lib('page.class.php');
//require_once elispm::lib('recordlinkformatter.class.php');

/**
 * This is the base class for a page that manages association data object types, for example
 * curriculumcourse or usertrack objects.  This is in contrast to the "managementpage" class, which
 * is used to manage the basic data objects such as user, track or curriculum.
 *
 * When subclassing, you must have a constructor which sets a number of instance variables that
 * define how the class operates.  See an existing subclass for an example.
 *
 */
class associationpage extends pm_page {
    const LANG_FILE = 'elis_program';
    /**
     * The name of the class used for data objects
     */
    var $data_class;

    /**
     * The name of the class used for the add/edit form
     */
    var $form_class;

    var $tabs;

    var $_form;

    public function can_do_default() {
        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('elis/program:manage', $context);
    }

    /**
     * Returns an instance of the page class that should provide the tabs for this association page.
     * This allows the association interface to be located "under" the general management interface for
     * the data object whose associations are being viewed or modified.
     *
     * @param $params
     * @return object
     */
    function get_tab_page($params=array()) {
        return new $this->tab_page($params);
    }

    function print_header($_) {
        $id = $this->required_param('id', PARAM_INT);
        $default_tab = !empty($this->default_tab) ? $this->default_tab : get_class($this);
        $action = $this->optional_param('action', $default_tab, PARAM_CLEAN);
        if ($action == 'default') {
            $action = $default_tab;
        }
        $association_id = $this->optional_param('association_id', 0, PARAM_INT);

        parent::print_header($_);
        $params = array('id' => $id);
        if (!empty($association_id)) {
            $params['association_id'] = $association_id;
        }
        if ($parentid = $this->optional_param('parent', 0, PARAM_INT)) {
            $params['parent'] = $parentid;
        }
        $this->get_tab_page()->print_tabs($action, $params);
    }

    /**
     * Prints the tab bar describe by the $tabs instance variable.
     * - lifted from managmentpage.class.php
     * @param $selected name of tab to display as selected
     * @param $params extra parameters to insert into the tab links, such as an id
     */
    function print_tabs($selected, $params=array()) {
        $row = array();

        foreach($this->tabs as $tab) {
            $tab = $this->add_defaults_to_tab($tab);
            if($tab['showtab'] === true) {
                $target = new $tab['page'](array_merge($tab['params'], $params));
                if (!$target->can_do()) {
                    continue;
                }
                $row[] = new tabobject($tab['tab_id'], $target->url, $tab['name']);
            }
        }
        print_tabs(array($row), $selected);
    }

    function get_new_data_object($id = false) {
        return new $this->data_class($id);
    }

    function print_add_button($params=array(), $text=null) {
        global $OUTPUT;
        $pname = 'association_id'; // TBD: 'association_id' or 'id' ???
        $obj_id = isset($params[$pname]) ? $params[$pname] : false;
        $obj = $this->get_new_data_object($obj_id); // TBD

        echo '<div align="center">';
        $options = array_merge(array('s' => $this->pagename, 'action' => 'add'), $params);
        $dellabel = get_string('delete_label', self::LANG_FILE);
        $objlabel = get_string($obj->get_verbose_name(), self::LANG_FILE); // TBD
        //echo print_single_button('index.php', $options, $text ? $text : $dellabel .' ' . $objlabel, 'get', '_self', true, $text ? $text : $dellabel .' ' . $objlabel);
        $button = new single_button(new moodle_url('index.php', $options), $text ? $text : $dellabel.' '.$objlabel, 'get', array('disabled'=>false, 'title'=>$text ? $text : $dellabel.' '.$objlabel));
        echo $OUTPUT->render($button);
        echo '</div>';
    }

    /**To be overloaded in child class to return data_class::get_default() ...
     * Eg. see: pmclasspage, usersetpage, trackpage, coursepage ...
     *
     * @return obj  the default data class object for page or NULL
     */
    function get_default_object_for_add() {
        return NULL;
    }

    /**
     * Generic handler for the add action.  Prints the add form.
     */
    function display_add() { // action_add()
        $id = $this->required_param('id', PARAM_INT);
        $parent_obj = new $this->parent_data_class($id);
        $this->print_add_form($parent_obj);
    }

    function do_add() { // action_savenew()
        $id = $this->required_param('id', PARAM_INT);
        $target = $this->get_new_page(array('action' => 'add', 'id' => $id), true); // TBD: 's' => ... && 2nd param true ???
        $obj = $this->get_default_object_for_add();
        $parent_obj = new $this->parent_data_class($id);
        $params = array();
        if ($obj != NULL) {
            $params['obj'] = $obj;
        }
        if ($parent_obj != NULL) {
            $params['parent_obj'] = $parent_obj;
        }
        $form = new $this->form_class($target->url, $params);
        if ($form->is_cancelled()) {
            $target = $this->get_new_page(array('id' => $id), true); // TBD: 's' => ... && 'action' => 'default' || 'view' && 2nd param true???
            redirect($target->url);
            return;
        }

        $data = $form->get_data();
        if($data) {
            require_sesskey();
            $obj = $this->get_new_data_object();
            $obj->set_from_data($data);
            $obj->save();
            $this->after_pm_entity_add($obj);
            $target = $this->get_new_page(array('id' => $id), true); // TBD: 's' => ... && 'action' => 'default' || 'view' && 2nd param true???
            redirect($target->url, ucwords(get_class($obj)) .' '. $obj->id .
                                   ' '.  get_string('saved','elis_program') .'.');
        } else {
            $this->_form = $form;
            $this->display('add');
        }
    }

    /**
     * Prints the add form.
     * @param $parent_obj is the basic data object we are forming an association with.
     */
    function print_add_form($parent_obj) {
        $id = required_param('id', PARAM_INT);
        $target = $this->get_new_page(array('action' => 'add', 'id' => $id));

        $form = new $this->form_class($target->url, array('parent_obj' => $parent_obj));
        $form->set_data(array('id' => $id));
        $form->display();
    }

    /**
     * Generic handler for the edit action.  Prints the edit form.
     */
    function display_edit() { // do_edit()
        $association_id = $this->required_param('association_id', PARAM_INT);
        $id             = $this->required_param('id', PARAM_INT);
        $obj            = new $this->data_class($association_id);
        $parent_obj     = new $this->parent_data_class($id);
        $sort           = optional_param('sort', 'idnumber', PARAM_ALPHA);
        $dir            = optional_param('dir', 'ASC', PARAM_ALPHA);

        /*if(!$obj->get_dbloaded()) { // TBD
            $sparam = new stdClass;
            $sparam->id = $id;
            print_error('invalid_objectid', 'elis_program', '', $sparam);
        }*/
        $obj->load();
        //error_log("associationpage::display_edit(): obj->id = {$obj->id}");
        $this->print_edit_form($obj, $parent_obj, $sort, $dir);
    }

    /**
     * Hook that gets called after a PM entity is added through this page
     * Override in subclasses as needed
     *
     * @param  object  $obj  The PM entity added
     */
    function after_pm_entity_add($obj) {
        //do nothing here, but allow subclass to override
    }

    /**
     * Generic handler for the edit action.  Prints the form for editing an
     * existing record, or updates the record.
     */
    function do_edit() {
        $association_id = $this->required_param('association_id', PARAM_INT);
        $params = array('action' => 'edit', 'association_id' => $association_id);
        if ($parentid = $this->optional_param('parent', 0, PARAM_INT)) {
            $params['parent'] = $parentid;
        }

        $target = $this->get_new_page($params, true);
        $obj = $this->get_new_data_object($association_id);
        $id = $this->required_param('id', PARAM_INT);

        $obj->load();

        $parent_obj = new $this->parent_data_class($id);
        $parent_obj->load();

        $params = array('action' => 'default', 'id' => $id);
        if ($parentid) {
            $params['parent'] = $parentid;
        }
        $form = new $this->form_class($target->url, array('obj' => $obj->to_object(),
                                                          'parent_obj' => $parent_obj->to_object()));

        if ($form->is_cancelled()) {
            $target = $this->get_new_page($params, true);
            redirect($target->url);
            return;
        }

        $data = $form->get_data();
        if ($data) {
            require_sesskey();
            $obj->set_from_data($data);
            $obj->save();
            $target = $this->get_new_page($params, true);
            redirect($target->url);
        } else {
            $this->_form = $form;
            $this->display('edit');
        }
    }

    /**
     * Prints the edit form.
     * @param $obj The association object being edited.
     * @param $parent_obj The basic data object being associated with.
     */
    function print_edit_form($obj, $parent_obj, $sort, $dir) {
        $parent_id = $this->required_param('id', PARAM_INT);
        $params = array('action' => 'edit', 'id' => $parent_id, 'association_id' => $obj->id);
        if ($parentid = $this->optional_param('parent', 0, PARAM_INT)) {
            $params['parent'] = $parentid;
        }
        $target = $this->get_new_page($params);

        $form = new $this->form_class($target->url, array('obj' => $obj, 'parent_obj' => $parent_obj));

        $form->display();
    }

    /**
     * Generic handler for the savenew action.  Tries to save the object and then prints the appropriate page.
     */
    function display_savenew() { // TBD: do_savenew() ?
        $parent_id = $this->required_param('id', PARAM_INT);
        $parent_obj = new $this->parent_data_class($parent_id);
        $params = array('action' => 'savenew', 'id' => $parent_id);
        if ($parentid = $this->optional_param('parent', 0, PARAM_INT)) {
            $params['parent'] = $parentid;
        }
        $target = $this->get_new_page($params);

        $form = new $this->form_class($target->url, array('parent_obj' => $parent_obj));

        if ($form->is_cancelled()) {
            $this->display('default'); // do_default()
            return;
        }

        $data = $form->get_data();
        if ($data) {
            $obj = new $this->data_class($data);
            //$obj->set_from_data($data);
            $obj->save();
            $params = array('action' => 'default', 'id' => $parent_id);
            if ($parentid) {
                $params['parent'] = $parentid;
            }
            $target = $this->get_new_page($params, true);
            redirect($target->url, ucwords($obj->get_verbose_name())  . ' ' . $obj->__toString() . ' saved.');
        } else {
            // Validation must have failed, redisplay form
            $form->display();
        }
    }

    /**
     * Generic handler for the update action.  Tries to update the object and then prints the appropriate page.
     */
    function display_update() { // do_update()
        $parent_id = $this->required_param('id', PARAM_INT);
        $parent_obj = new $this->parent_data_class($parent_id);

        $association_id = $this->required_param('association_id', PARAM_INT);
        $obj = new $this->data_class($association_id);
        $params = array('action' => 'update');
        if ($parentid = $this->optional_param('parent', 0, PARAM_INT)) {
            $params['parent'] = $parentid;
        }
        $target = $this->get_new_page($params);

        $form = new $this->form_class($target->url, array('obj' => $obj, 'parent_obj' => $parent_obj));

        if ($form->is_cancelled()) {
            $this->display('default'); // do_default()
            return;
        }

        $data = $form->get_data();
        if ($data) {
            $obj->set_from_data($data);
            $obj->save();
            $params = array('action' => 'default', 'id' => $parent_id);
            if ($parentid) {
                $params['parent'] = $parentid;
            }
            $target = $this->get_new_page($params);
            redirect($target->url, ucwords($obj->get_verbose_name())  . ' ' . $obj->__toString() . ' updated.');
        } else {
            // Validation must have failed, redisplay form
            $form->display();
        }
    }

    public function get_page_title_default() {
        $id = $this->required_param('id', PARAM_INT);
        $tabpage = $this->get_tab_page(array('action' => 'view', 'id' => $id));
        return $tabpage->get_page_title() . ': ' . get_string('breadcrumb_' . get_class($this), self::LANG_FILE);
    }

    public function get_title_default() {
        return $this->get_page_title_default();
    }

    public function build_navbar_default($who = null, $addparent = true, $params = array()) { // build_navigation_default
        //parent::build_navbar_default();
        $id = $this->required_param('id', PARAM_INT);
        $params = array('action' => 'view', 'id' => $id);
        if ($parentid = $this->optional_param('parent', 0, PARAM_INT)) { // this?
            $params['parent'] = $parentid;
        }
        $tabpage = $this->get_tab_page($params);
        $tabpage->build_navbar_view();
        $this->_navbar = $tabpage->navbar;
      /*
        $this->navbar->add(get_string("association_{$this->data_class}",
                                      self::LANG_FILE), $this->url);
      */
    }

    /**
     * Generic handler for the delete action.  Prints the delete confirmation form.
     */
    public function display_delete() {
        $association_id = $this->required_param('association_id', PARAM_INT);
        $id = $this->required_param('id', PARAM_INT);
        if(empty($association_id)) {
            print_error('invalid_id');
        }

        $obj = $this->get_new_data_object($association_id);
        $this->print_delete_form($obj);
    }

    /**
     * Prints the delete confirmation form.
     * @param $obj Basic data object being associated with.
     */
    public function print_delete_form($obj) {
        global $OUTPUT;

        $id = $this->required_param('id', PARAM_INT);

        $obj->load(); // force load, so that the confirmation notice has something to display
        $message = get_string('confirm_delete_association', 'elis_program', $obj->to_object());

        $target_page = $this->get_new_page(array('action' => 'default', 'id' => $id, 'sesskey' => sesskey()), true);
        $no_url = $target_page->url;
        $no = new single_button($no_url, get_string('no'), 'get');

        $optionsyes = array('action' => 'delete', 'association_id' => $obj->id, 'id' => $id, 'confirm' => 1);
        $yes_url = clone($no_url);
        $yes_url->params($optionsyes);
        $yes = new single_button($yes_url, get_string('yes'), 'get');

        echo $OUTPUT->confirm($message, $yes, $no);
    }

/**
     * Generic handler for the confirm (confirm delete) action.  Tries to delete the object and then renders the appropriate page.
     */
    public function do_delete() {
        global $CFG;

        if (!$this->optional_param('confirm', 0, PARAM_INT)) {
            return $this->display('delete');
        }

        require_sesskey();

        $id = $this->required_param('id', PARAM_INT);
        $association_id = $this->required_param('association_id', PARAM_INT);

        $obj = $this->get_new_data_object($association_id);
        $obj->load(); // force load, so that the confirmation notice has something to display
        $obj->delete();

        $returnurl = $this->optional_param('return_url', null, PARAM_URL);
        if ($returnurl === null) {
            $params = array('id' => $id);
            if ($parentid = $this->optional_param('parent', 0, PARAM_INT)) {
                $params['parent'] = $parentid;
            }
            $target_page = $this->get_new_page($params, true);
            $returnurl = $target_page->url;
        } else {
            $returnurl = $CFG->wwwroot.$returnurl;
        }

        //TODO: not returning something valid here... needs id=1
        redirect($returnurl, get_string('notice_'.get_class($obj).'_deleted', 'elis_program', $obj->to_object()));
    }

    /**
     * Prints out the page that displays a list of records returned from a query.
     * @param $items array of records to print
     * @param $columns associative array of column id => column heading text
     * @param $itemstr language string idenitifer for object - default = 'items'
     */
    function print_list_view($items, $columns, $itemstr = 'items') { // TBD
        global $CFG;

        $id = $this->required_param('id', PARAM_INT);
        $search = optional_param('search', null, PARAM_CLEAN);
        $alpha = optional_param('alpha', null, PARAM_ALPHA);

        //todo: make usersetassignmentpage consistent with other pages and
        //change all pages to use recordsets

        if (empty($items) || ($items instanceof Iterator && $items->valid() === false)) {
            $a = new stdClass;

            //determining if we are searching
            $is_searching = $alpha || $search;

            //display the appropriate "no items found" message
            $a->search = $is_searching ? get_string('matchingsearch', 'elis_program') : '';
            $a->obj = get_string($itemstr, 'elis_program');

            //display the appropriate message in a div
            echo html_writer::tag('div', get_string('noitems', 'elis_program', $a));
            return;
        }

        $table = $this->create_table_object($items, $columns);
        echo $table->get_html();
    }

    /**
     * Creates a new table object with specified $items and $columns.
     * @param array $items
     * @param array $columns
     */
    function create_table_object($items, $columns) {
        return new association_page_table($items, $columns, $this);
    }

    /**
     * Prints out the item count for the list interface with the appropriate formatting.
     * @param $numitems number of items
     */
    function print_num_items($numitems, $max) {
        $a = new stdClass;
        $a->num = $numitems;
        echo '<div style="float:right;">' . get_string('items_found', self::LANG_FILE, $a) . '</div>';
    }

    /**
     * Prints the dropdown for adding a new association with the basic object currently being managed.
     * @param $items complete list of possible objects to associate with
     * @param $taken_items list of objects that are already associated with
     * @param $local_key property name in the association class that stores the ID of the basic object being managed
     * @param $nonlocal_key property name in the association class that stores the ID of the basic object being associated with
     * @param $action action to request when an item is selected from the dropdown
     * @param $namefield property name in the class to associate with that should be displayed in the dropdown rows
     */
    function print_dropdown($items, $taken_items, $local_key, $nonlocal_key, $action='add', $namefield='name') {
        global $OUTPUT;

        $id = $this->required_param('id', PARAM_INT);

        // As most of the listing functions return 'false' if there are no records.
        $taken_items = $taken_items ? $taken_items : array();

        $taken_ids = array();
        foreach($taken_items as $taken_item) {
            $taken_ids[] = $taken_item->$nonlocal_key;
            //$taken_ids[] = $taken_item->id;
        }

        $items = (is_array($items) || ($items instanceof Iterator && $items->valid()===true)) ? $items : array();

        $menu = array();

        foreach ($items as $info) {
            if (!in_array($info->id, $taken_ids)) {
                $menu[$info->id] = $info->$namefield;
            }
        }
        unset($items);

        echo '<div align="center"><br />';

        if (!empty($menu)) {
            // TODO: use something that doesn't require this append-to-url-string approach
            // Double use of $id is to keep idea of foreign key for association and parent object separate
            $url = $this->get_new_page(array('action' => $action, $local_key => $id, 'id' => $id))->url;
            $actionurl = new moodle_url($url, array('sesskey'=>sesskey()));
            $select = new single_select($actionurl, $nonlocal_key, $menu, null, array(''=>get_string('adddots')));
            echo $OUTPUT->render($select);
        } else {
            echo get_string('all_items_assigned', self::LANG_FILE);
        }
        echo '</div>';
    }

    /**
     * Inserts default values into the tabs array provided by the page class.
     * @param $tab tab to set the defaults for
     */
    function add_defaults_to_tab($tab) {
        $defaults = array('params' => array(), 'showbutton' => false, 'showtab' => 'false', 'image' => '');

        return array_merge($defaults, $tab);
    }

    /**
     * Generates the HTML for the management buttons (such as edit and delete) for a record's row in the table.
     * @param $params extra parameters to pass through the buttons, such as a record id
     * @uses $OUTPUT
     */
    function get_buttons($params) {
        global $OUTPUT;

        $buttons = array();

        foreach($this->tabs as $tab) {
            $tab = $this->add_defaults_to_tab($tab);
            if($tab['showbutton'] === true) {
                $target = new $tab['page'](array_merge($tab['params'], $params));
                if ($target->can_do()) {
                    $buttons[] = html_writer::link($target->url, html_writer::empty_tag('img', array('title' => $tab['name'], 'alt' => $tab['name'], 'src' => $OUTPUT->pix_url($tab['image'], 'elis_program'))));
                }
            }
        }

        return implode('', $buttons);
    }

    /**
     * Prints the 'All A B C ...' alphabetical filter bar.
     */
    function print_alpha() {
        pmalphabox($this->url);
    }

    /**
     * Prints the text substring search interface.
     */
    function print_search() {
        pmsearchbox($this);
    }

    /**
     * Receives a request to apply a value to all selected items. Saves the new information for each selected item.
     */
    public function bulk_apply_all() {
        global $SESSION;

        $target = optional_param('target', '', PARAM_ALPHA);
        $page = optional_param('s', '', PARAM_ALPHA);
        $id = optional_param('id', 1, PARAM_INT);
        $blktpl = optional_param('bulktpl', '', PARAM_CLEAN);
        $pagename = $page . $id . $target;
        $blktpl = json_decode($blktpl);

        if (!empty($SESSION->associationpage[$pagename]) && is_array($SESSION->associationpage[$pagename])) {
            foreach ($SESSION->associationpage[$pagename] as $uid => $rec) {
                if (!empty($rec->selected) && $rec->selected === true) {
                    if (!empty($blktpl->enrolment_date_checked) && $blktpl->enrolment_date_checked === true) {
                        $rec->enrolment_date->day = $blktpl->enrolment_date->day;
                        $rec->enrolment_date->month = $blktpl->enrolment_date->month;
                        $rec->enrolment_date->year = $blktpl->enrolment_date->year;
                    }
                    if (!empty($blktpl->completion_date_checked) && $blktpl->completion_date_checked === true) {
                        $rec->completion_date->day = $blktpl->completion_date->day;
                        $rec->completion_date->month = $blktpl->completion_date->month;
                        $rec->completion_date->year = $blktpl->completion_date->year;
                    }
                    if (!empty($blktpl->status_checked)) {
                        $rec->status = $blktpl->status;
                    }
                    if (!empty($blktpl->grade_checked)) {
                        $rec->grade = $blktpl->grade;
                    }
                    if (!empty($blktpl->credits_checked)) {
                        $rec->credits = $blktpl->credits;
                    }
                    if (!empty($blktpl->locked_checked)) {
                        $rec->locked = $blktpl->locked;
                    }
                }
            }
        }
    }

    /**
     * Removes saved checkboxes for the page specified by target, s, and id input vars.
     */
    public function bulk_checkbox_selection_deselectall() {
        global $SESSION;

        $target = optional_param('target', '', PARAM_ALPHA);
        $page = optional_param('s', '', PARAM_ALPHA);
        $id = optional_param('id', 1, PARAM_INT);
        $pagename = $page . $id . $target;

        if (!empty($SESSION->associationpage[$pagename])) {
            foreach ($SESSION->associationpage[$pagename] as $userid => $rec) {
                unset($SESSION->associationpage[$pagename][$userid]->selected);
            }
        }

        return true;
    }

    /**
     * Resets all saved changes.
     * @param  string $target The the section for the current page to reset. (ex. "bulkedit")
     */
    public function bulk_checkbox_selection_reset($target) {
        session_selection_deletion($target);
    }

    /**
     * Store checkbox data into session
     */
    public function bulk_checkbox_selection_session() {
        global $SESSION;
        $selection = optional_param('selected_checkboxes', '', PARAM_CLEAN);
        $target = optional_param('target', '', PARAM_ALPHA);
        $page = optional_param('s', '', PARAM_ALPHA);
        $id = optional_param('id', 1, PARAM_INT);

        $selectedcheckboxes = json_decode($selection);

        if (is_array($selectedcheckboxes)) {
            $pagename = $page . $id . $target;

            if (!isset($SESSION->associationpage[$pagename])) {
                $SESSION->associationpage[$pagename] = array();
            }

            foreach ($selectedcheckboxes as $selectedcheckbox) {
                $record = json_decode($selectedcheckbox);

                if(isset($record->changed) && $record->changed === true) {
                    $SESSION->associationpage[$pagename][$record->id] = $record;
                } else {
                    if (isset($SESSION->associationpage[$pagename][$record->id])) {
                        unset($SESSION->associationpage[$pagename][$record->id]);
                    }
                }
            }
        }
    }

    // Store checkbox data into session
    public function checkbox_selection_session() {
        global $SESSION;
        $selection = optional_param('selected_checkboxes', '', PARAM_CLEAN);
        $target = optional_param('target', '', PARAM_ALPHA);
        $page = optional_param('s', '', PARAM_ALPHA);
        $id = optional_param('id', 1, PARAM_INT);

        $selectedcheckboxes = json_decode($selection);

        if (is_array($selectedcheckboxes)) {
            $pagename = $page . $id . $target;

            if (!isset($SESSION->associationpage[$pagename])) {
                $SESSION->associationpage[$pagename] = array();
            }

            $existing_selection = array();
            $new_selection = array();
            foreach ($selectedcheckboxes as $selectedcheckbox) {
                $record = json_decode($selectedcheckbox);
                /* If only the id is provided, the selection was stored previously
                 * Get the corresponding session data, if available
                 */
                if(isset($record->bare)) {
                    $result = retrieve_session_selection($record->id, $target);
                    if ($result) {
                        $existing_selection[] = $result;
                    }
                } else {
                    // New selection record
                    $new_selection[] = $selectedcheckbox;
                }
            }

            $result = array_merge($existing_selection, $new_selection);
            $SESSION->associationpage[$pagename] = $result;
        }
    }

}

class association_page_table extends display_table {
    function __construct(&$items, $columns, $page) {
        $this->page = $page;

        parent::__construct($items, $columns, $page->url);
    }

    function get_column_align_buttons() {
        return 'center';
    }

    function is_column_wrapped_buttons() {
        return false;
    }

    function get_item_display_buttons($column, $item) {
        $id = required_param('id', PARAM_INT);
        $params = array('id' => $id, 'association_id' => $item->id);
        if ($parentid = optional_param('parent', 0, PARAM_INT)) {
            $params['parent'] = $parentid;
        }
        return $this->page->get_buttons($params);
    }

    function get_item_display_manage($column, $item) {
        global $OUTPUT;
        $id = required_param('id', PARAM_INT);
        $target = $this->page->get_new_page(array('action' => 'delete', 'association_id' => $item->id, 'id' => $id));
        if ($target->can_do('delete')) {
            $deletebutton = html_writer::link($target->url, html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('t/delete'), 'alt' => 'Unenrol', 'title' => 'Unenrol')));
        } else {
            $deletebutton = '';
        }
        return $deletebutton;
    }
}

