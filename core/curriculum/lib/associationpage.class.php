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

require_once (CURMAN_DIRLOCATION . '/lib/newpage.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/recordlinkformatter.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/cmsearchbox.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/cmalphabox.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/table.class.php');

/**
 * This is the base class for a page that manages association data object types, for example
 * curriculumcourse or usertrack objects.  This is in contrast to the "managementpage" class, which
 * is used to manage the basic data objects such as user, track or curriculum.
 *
 * When subclassing, you must have a constructor which sets a number of instance variables that
 * define how the class operates.  See an existing subclass for an example.
 *
 */
class associationpage extends newpage {

    public function can_do_default() {
        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('block/curr_admin:managecurricula', $context);
    }

    public function can_do_update() {
        return $this->can_do('edit');
    }

    public function can_do_savenew() {
        return $this->can_do('add');
    }

    public function can_do_confirm() {
        return $this->can_do('delete');
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

    function print_header() {
        $id = required_param('id', PARAM_INT);

        parent::print_header();

        $this->get_tab_page()->print_tabs(get_class($this), array('id' => $id));
    }

    function get_new_data_object($id=false) {
        return new $this->data_class($id);
    }

    function print_add_button($params=array(), $text=null) {
        $obj = $this->get_new_data_object();

        echo '<div align="center">';
        $options = array_merge(array('s' => $this->pagename, 'action' => 'add'), $params);
        // FIXME: change to language string
        echo print_single_button('index.php', $options, $text ? $text : get_string('delete_label','block_curr_admin').' ' . get_string($obj->get_verbose_name(),'block_curr_admin'), 'get', '_self', true, $text ? $text : get_string('delete_label','block_curr_admin').' ' . get_string($obj->get_verbose_name(),'block_curr_admin'));
        echo '</div>';
    }

    /**
     * Generic handler for the add action.  Prints the add form.
     */
    function action_add() {
        $id = required_param('id', PARAM_INT);

        $parent_obj = new $this->parent_data_class($id);

        $this->print_add_form($parent_obj);
    }

    /**
     * Prints the add form.
     * @param $parent_obj is the basic data object we are forming an association with.
     */
    function print_add_form($parent_obj) {
        $id = required_param('id', PARAM_INT);

        $target = $this->get_new_page(array('action' => 'savenew', 'id' => $id));

        $form = new $this->form_class($target->get_moodle_url(), array('parent_obj' => $parent_obj));

        $form->set_data(array('id' => $id));

        $form->display();
    }

    /**
     * Generic handler for the edit action.  Prints the edit form.
     */
    function action_edit() {
        $association_id       = required_param('association_id', PARAM_INT);

        $obj = new $this->data_class($association_id);

        $id = required_param('id', PARAM_INT);

        $parent_obj = new $this->parent_data_class($id);

        if(!$obj->get_dbloaded()) {
            error('Invalid object id: ' . $id . '.');
        }

        $this->print_edit_form($obj, $parent_obj);
    }

    /**
     * Prints the edit form.
     * @param $obj The association object being edited.
     * @param $parent_obj The basic data object being associated with.
     */
    function print_edit_form($obj, $parent_obj) {
    	$parent_id = required_param('id', PARAM_INT);

        $target = $this->get_new_page(array('action' => 'update', 'id' => $parent_id, 'association_id' => $obj->id));

        $form = new $this->form_class($target->get_moodle_url(), array('obj' => $obj, 'parent_obj' => $parent_obj));

        $form->display();
    }

    /**
     * Generic handler for the savenew action.  Tries to save the object and then prints the appropriate page.
     */
    function action_savenew() {
        $parent_id = required_param('id', PARAM_INT);

        $parent_obj = new $this->parent_data_class($parent_id);

        $target = $this->get_new_page(array('action' => 'savenew', 'id' => $parent_id));

        $form = new $this->form_class($target->get_moodle_url(), array('parent_obj' => $parent_obj));

        if ($form->is_cancelled()) {
            $this->action_default();
            return;
        }

        $data = $form->get_data();

        if($data) {
            $obj = new $this->data_class();
            $obj->set_from_data($data);
            $obj->add();
            $target = $this->get_new_page(array('action' => 'default', 'id' => $parent_id));
            redirect($target->get_url(), ucwords($obj->get_verbose_name())  . ' ' . $obj->to_string() . ' saved.');
        } else {
            // Validation must have failed, redisplay form
            $form->display();
        }
    }

    /**
     * Generic handler for the update action.  Tries to update the object and then prints the appropriate page.
     */
    function action_update() {
        $parent_id = required_param('id', PARAM_INT);
        $parent_obj = new $this->parent_data_class($parent_id);

        $association_id = required_param('association_id', PARAM_INT);
        $obj = new $this->data_class($association_id);

        $target = $this->get_new_page(array('action' => 'update'));


        $form = new $this->form_class($target->get_moodle_url(), array('obj' => $obj, 'parent_obj' => $parent_obj));

        if ($form->is_cancelled()) {
            $this->action_default();
            return;
        }

        $data = $form->get_data();

        if($data) {
            $obj->set_from_data($data);
            $obj->update();  // TODO: create a generalized "save" method that decides whether to do update or add
            $target = $this->get_new_page(array('action' => 'default', 'id' => $parent_id));
            redirect($target->get_url(), ucwords($obj->get_verbose_name())  . ' ' . $obj->to_string() . ' updated.');
        } else {
            // Validation must have failed, redisplay form
            $this->print_tabs('edit', array('id' => $id));
            $form->display();
        }
    }

    public function get_title() {
        return get_string('breadcrumb_' . get_class($this), 'block_curr_admin');
    }

    public function get_navigation_default() {
        return $this->get_tab_page()->get_navigation_view();
    }

    /**
     * Generic handler for the delete action.  Prints the delete confirmation form.
     */
    function action_delete() {
        $association_id = required_param('association_id', PARAM_INT);

        if(empty($association_id)) {
            print_error('invalid_id');
        }

        $obj = new $this->data_class($association_id);

        $this->print_delete_form($obj);
    }

    /**
     * Prints the delete confirmation form.
     * @param $obj Basic data object being associated with.
     */
    function print_delete_form($obj) {
        $id = required_param('id', PARAM_INT);
        $a_id = required_param('association_id', PARAM_INT);

        $url        = 'index.php';

        $a = new object();
        //$a->object_name = $obj->to_string();
        //$a->type_name = $obj->get_verbose_name();
        $a->id = $a_id;

        $message    = get_string('confirm_delete_association', 'block_curr_admin', $a);
        $optionsyes = array('s' => $this->pagename, 'action' => 'confirm',
                            'association_id' => $a_id, 'id' => $id, 'confirm' => md5($a_id));
        $optionsno = array('s' => $this->pagename, 'id' => $id);

        echo '<br />' . "\n";
        echo cm_delete_form($url, $message, $optionsyes, $optionsno);
    }

    /**
     * Generic handler for the confirm (confirm delete) action.  Tries to delete the object and then renders the appropriate page.
     */
    function action_confirm() {
        $association_id = required_param('association_id', PARAM_INT);
        $confirm = required_param('confirm', PARAM_ALPHANUM);

        if (md5($association_id) != $confirm) {
            echo cm_error('Invalid confirmation code!');
        } else {
            $obj = new $this->data_class($association_id);
            $obj->delete();
        }

        $target = $this->action_default();
    }

    /**
     * Prints out the page that displays a list of records returned from a query.
     * @param $items array of records to print
     * @param $columns associative array of column id => column heading text
     * @param $formatters associative array of column id => formatting object, used to customize the display of columns
     */
    function print_list_view($items, $columns, $formatters=array()) {
        global $CFG;

        $id = required_param('id', PARAM_INT);

        if (empty($items)) {
            echo '<div>' . get_string('none', 'block_curr_admin') . '</div>';
            return;
        }

        $table = $this->create_table_object($items, $columns, $formatters);
        $table->print_table();
    }

    /**
     * Creates a new table object with specified $items and $columns.
     * @param array $items
     * @param array $columns
     * @param array $formatters
     */
    function create_table_object($items, $columns, $formatters) {
        return new association_page_table($items, $columns, $this, $formatters);
    }

    /**
     * Prints out the item count for the list interface with the appropriate formatting.
     * @param $numitems number of items
     */
    function print_num_items($numitems) {
        echo '<div style="float:right;">' . get_string('items_found', 'block_curr_admin', $numitems) . '</div>';
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
    function print_dropdown($items, $taken_items, $local_key, $nonlocal_key, $action='savenew', $namefield='name') {
        $id = required_param('id', PARAM_INT);

        // As most of the listing functions return 'false' if there are no records.
        $taken_items = $taken_items ? $taken_items : array();

        $taken_ids = array();
        foreach($taken_items as $taken_item) {
            $taken_ids[] = $taken_item->$nonlocal_key;
            //$taken_ids[] = $taken_item->id;
        }

        if (!($avail = $items)) {
            $avail = array();
        }

        $menu = array();
        
        foreach ($avail as $info) {
            if (!in_array($info->id, $taken_ids)) {
                $menu[$info->id] = $info->$namefield;
            }
        }

        echo '<div align="center"><br />';
        
        if (!empty($menu)) {
            // TODO: use something that doesn't require this append-to-url-string approach
            // Double use of $id is to keep idea of foreign key for association and parent object separate
            $url = $this->get_new_page(array('action' => $action, $local_key => $id, 'id' => $id))->get_url() . '&amp;' . $nonlocal_key . '=';
            popup_form($url, $menu, 'assigntrk', '', 'Add...');
        } else {
            echo get_string('all_items_assigned', 'block_curr_admin');
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
     */
    function get_buttons($params) {
        // TODO: function copied from managementpage.class.php.  Refactor at some point.
        $buttons = '';

        foreach($this->tabs as $tab) {
            $tab = $this->add_defaults_to_tab($tab);
            if($tab['showbutton'] === true) {
                $target = new $tab['page'](array_merge($tab['params'], $params));

                if ($target->can_do()) {
                    $buttons .= '<a href="' . $target->get_url() . '"><img title="' . $tab['name'] . '" alt="' . $tab['name'] . '" src="pix/' . $tab['image'] . '" /></a> ';
                }
            }
        }

        return $buttons;
    }

    /*
     * Convenience function for creating the standard link-to-foreign-record formatters array for the list view.
     *
     */
    function create_link_formatters($columns, $foreign_class, $foreign_id) {
        $formatters = array();
        foreach($columns as $column) {
            $formatters[$column] = new recordlinkformatter(new $foreign_class(), $foreign_id);
        }

        return $formatters;
    }

    /**
     * Prints the 'All A B C ...' alphabetical filter bar.
     */
    function print_alpha() {
        $alphabox = new cmalphabox($this);

        $alphabox->display();
    }

    /**
     * Prints the text substring search interface.
     */
    function print_search() {
        $searchbox = new cmsearchbox($this);

        $searchbox->display();
    }
}

class association_page_table extends display_table {
    function __construct(&$items, $columns, $page, $decorators=array()) {
        $this->page = $page;

        parent::__construct($items, $columns,
                            $page->get_moodle_url(), $decorators);
    }

    function get_column_align_buttons() {
        return 'center';
    }

    function is_column_wrapped_buttons() {
        return false;
    }

    function get_item_display_buttons($column, $item) {
        $id = required_param('id', PARAM_INT);
        return $this->page->get_buttons(array('id' => $id, 'association_id' => $item->id));
    }

    function get_item_display_manage($column, $item) {
        $id = required_param('id', PARAM_INT);
        $target = $this->page->get_new_page(array('action' => 'delete', 'association_id' => $item->id, 'id' => $id));
        if ($target->can_do('delete')) {
            $deletebutton = '<a href="' . $target->get_url() . '">' .
                '<img src="pix/delete.gif" alt="Unenrol" title="Unenrol" /></a>';
        } else {
            $deletebutton = '';
        }
        return $deletebutton;
    }
}
?>
