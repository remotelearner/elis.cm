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

require_once $CFG->libdir . '/weblib.php';
require_once CURMAN_DIRLOCATION . '/lib/newpage.class.php';
require_once CURMAN_DIRLOCATION . '/lib/cmsearchbox.class.php';
require_once CURMAN_DIRLOCATION . '/lib/cmalphabox.class.php';

/**
 * This is the base class for a page that manages the basic data object types, for example
 * user, track or curriculum objects.  This is in contrast to the "associationpage" class, which
 * is used to manage the data objects that associate two of the basic types together, for example
 * curriculumcourse or usertrack objects.
 *
 * When subclassing, you must have a constructor which sets a number of instance variables that
 * define how the class operates.  See an existing subclass for an example.
 *
 */
abstract class managementpage extends newpage {
    public function can_do_default() {
        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('block/curr_admin:managecurricula', $context);
    }

    /**
     * Returns a new instance of the data object class this page manages.
     * @param $id
     * @return object
     */
    function get_new_data_object($id=false) {
        return new $this->data_class($id);
    }

    /**
     * Generic handler for the confirm action.  Deletes the record identified by 'id'.
     */
    function action_confirm() {
        global $CFG;

        $id       = required_param('id', PARAM_INT);
        $confirm  = required_param('confirm', PARAM_ALPHANUM);   //md5 confirmation hash

        $obj = $this->get_new_data_object($id);

        $target_page = $this->get_new_page();

        if (md5($id) != $confirm) {
            redirect($target_page->get_url(), 'Invalid confirmation code!');
        } else if (!$obj->delete()){
            redirect($target_page->get_url(), ucwords($obj->get_verbose_name())  . ' ' . $obj->to_string() . ' not deleted.');
        } else {
            redirect($target_page->get_url(), ucwords($obj->get_verbose_name())  . ' ' . $obj->to_string() . ' deleted.');
        }
    }

    /**
     * Generic handler for the delete action.  Prints a deletion confirmation form that executes the confirm action
     * when submitted.
     */
    function action_delete() {
        $id       = required_param('id', PARAM_INT);

        if(empty($id)) {
            print_error('invalid_id');
        }

        $obj = new $this->data_class($id);

        $this->print_delete_form($obj);
    }

    /**
     * Prints a deletion confirmation form.
     * @param $obj record whose deletion is being confirmed
     */
    function print_delete_form($obj) {
        $url        = 'index.php';

        $a = new object();
        $a->object_name = $obj->to_string();
        $a->type_name = $obj->get_verbose_name();
        $a->id = $obj->id;

        $message    = get_string('confirm_delete', 'block_curr_admin', $a);
        $optionsyes = array('s' => $this->pagename, 'action' => 'confirm',
                            'id' => $obj->id, 'confirm' => md5($obj->id));
        $optionsno = array('s' => $this->pagename);

        echo cm_delete_form($url, $message, $optionsyes, $optionsno);
    }

    /**
     * Creates rows for each item in $items.
     * @param $items array of records
     * @param $columns associative array of column ids => column heading text.  column ids correspond to record properties.
     * @param $table the table object to add the items to
     */
    function add_table_items($items, $columns, $table=null) {
        $countries = cm_get_list_of_countries();

        $table->width = "95%";

        foreach ($items as $item) {
            // TODO: (short term) push this logic to the page class, by using a get_cell_value($item, $columnname) function that is called for
            // each cell in the table.
            // TODO: (long term) push this logic to the model, either by using accessors or by using field types
            $newarr = array();

            foreach ($columns as $column => $cdesc) {
                // From cmclasspage
                if ($column == 'idnumber') {
                    $newarr[] = '<a href="index.php?s=rep&amp;section=rept&amp;type=classroster&amp;' .
                                    'class=' . $item->id . '">' . $item->idnumber . '</a>';
                } else if ($column == 'envname') {
                    $newarr[] = '<div align="center"><span title="'.$item->envdescription.'">'.
                    $item->envname.'</span></div>';
                } else if (($column == 'startdate') || ($column == 'enddate')) {
                    if (empty($item->$column)) {
                        $newarr[] = '-';
                    } else {
                        $newarr[] = cm_timestamp_to_date($item->$column);
                    }
                } else if ($column == 'starttime')  {
                    if (($item->starttimehour == '0') && ($item->starttimeminute == '0')) {
                        $newarr[] = 'n/a';
                    } else {
                        $newarr[] = $item->starttimehour . ':' . sprintf("%02d", $item->starttimeminute);
                    }
                } else if ($column == 'endtime')  {
                    if (($item->endtimehour == '0') && ($item->endtimeminute == '0')) {
                        $newarr[] = 'n/a';
                    } else {
                        $newarr[] = $item->endtimehour . ':' . sprintf("%02d", $item->endtimeminute);
                    }
                // From usermanagementpage
                } else if ($column == 'location') {
                    $newarr[] = '<a href="index.php?s=rep&amp;section=rept&amp;type=' .
                                    'recloc&amp;loc=' . $item->location . '">' .
                    $item->location . '</a>';
                } else if ($column == 'currentclass') {
                    $newarr[] = '<a href="index.php?s=rep&amp;section=rept&amp;type=' .
                                    'classroster&amp;class=' . $item->currentclassid . '">' .
                    $item->currentclass . '</a>';

                } else if ($column == 'lastclass') {
                    $newarr[] = '<a href="index.php?s=rep&amp;section=rept&amp;type=' .
                                    'classroster&amp;class=' . $item->lastclassid . '">' .
                    $item->lastclass . '</a>';

                } else if ($column == 'country') {
                    $newarr[] = isset($countries[$item->country]) ? $countries[$item->country] : '';

                } else if ($column == 'timecreated') {
                    if (!empty($item->origenroldate)) {
                        $dateparts = explode('/', $item->origenroldate);
                        $bt = mktime(0, 0, 0, $dateparts[1], $dateparts[2], $dateparts[0]);
                        $newarr[] = userdate($bt);
                    } else if (!empty($item->timecreated)) {
                        $newarr[] = userdate($item->timecreated);
                    } else {
                        $newarr[] = '-';
                    }
                } else {
                    $newarr[] = $item->$column;
                }

                // Add link to specified columns
                if (in_array($column, $this->view_columns)) {
                    $target = $this->get_new_page(array('action' => 'view', 'id' => $item->id));
                    $newarr[count($newarr)-1] = '<a href="' . $target->get_url() . '">' . $newarr[count($newarr)-1] . '</a>';
                }
            }

            $newarr[] = $this->get_buttons(array('id' => $item->id));
            $table->data[] = $newarr;
        }

        return $table;
    }

    /**
     * Displays the page for when there are no items to list.
     */
    function print_no_items() {
        $namesearch   = trim(optional_param('search', '', PARAM_TEXT));
        $alpha        = optional_param('alpha', '', PARAM_ALPHA);

        $match = array();
        if ($namesearch !== '') {
            $match[] = s($namesearch);
        }
        if ($alpha) {
            $match[] = 'name'.": $alpha"."___";
        }
        $matchstring = implode(", ", $match);
        echo get_string('no_items_matching', 'block_curr_admin').$matchstring;
    }


    /**
     * Prints the '1 2 3 ...' paging bar for when a query set is split across multiple pages.
     * @param $numitems total number of items in the query set
     */
    function print_paging_bar($numitems) {
        // TODO: take a queryset as an argument rather than the number of items
        $sort         = optional_param('sort', '', PARAM_ALPHA);
        $dir          = optional_param('dir', '', PARAM_ALPHA);
        $locsearch    = trim(optional_param('locsearch', '', PARAM_TEXT));
        $namesearch   = trim(optional_param('search', '', PARAM_TEXT));
        $alpha        = optional_param('alpha', '', PARAM_ALPHA);

        $page         = optional_param('page', 0, PARAM_INT);
        $perpage      = optional_param('perpage', 30, PARAM_INT);
        $id           = optional_param('id', 0, PARAM_INT);

        $params = array(
			'search' => stripslashes($namesearch),
			'locsearch' => stripslashes($locsearch),
			'alpha' => $alpha,
			'perpage' => $perpage,
			/*'namesearch' => $namesearch*/
        );
        if (!empty($id)) {
            $params['id'] = $id;
        }
        if (!empty($sort)) {
            $params['sort'] = $sort;
        }
        if (!empty($sort)) {
            $params['dir'] = $dir;
        }

        $target = $this->get_new_page($params);

        print_paging_bar($numitems, $page, $perpage, $target->get_url() . '&amp;');
    }

    /**
     * Prints out a table with specified $columns and $items.
     *
     * @param $items array of records to print
     * @param $columns associative array of column id => column heading text.  column ids correspond to item properties.
     * @param $table the table object to print
     */
    function print_table($items, $columns) {
        if (!$items) {
            $this->print_no_items();
        }
        else
        {
            $this->print_add_button();
            echo "<br clear=\"all\" />\n";

            $table = $this->create_table_object($items, $columns);
            $table->print_table();
        }
    }

    /**
     * Creates a new table object with specified $items and $columns.
     * @param array $items
     * @param array $columns
     */
    function create_table_object($items, $columns) {
        return new management_page_table($items, $columns, $this);
    }

    /**
     * Prints the more powerful filter interface, used on the usermanagement page.
     * @param $filter filter object
     */
    function print_filter($filter) {
        // Print filtering interface
        $filter->display_add();
        $filter->display_active();
    }

    /**
     * Prints the single-button form used to request the add action for a record type.
     */
    function print_add_button() {
        if (!$this->can_do('add')) {
            return;
        }

        $obj = $this->get_new_data_object();

        echo '<div align="center">';
        $options = array('s' => $this->pagename, 'action' => 'add');
        // FIXME: change to language string
        echo print_single_button('index.php', $options, get_string('add','block_curr_admin').' ' . get_string($obj->get_verbose_name(),'block_curr_admin'), 'get', '_self', true, get_string('add','block_curr_admin').' ' . get_string($obj->get_verbose_name(),'block_curr_admin'));
        echo '</div>';
    }

    /**
     * Prints the single-button form used to request the delete action for a record.
     * @param $obj record to request deletion for
     */
    function print_delete_button($obj) {
        if (!$this->can_do('delete')) {
            return;
        }
        
        echo '<div align="center">';
        $options = array('s' => $this->pagename, 'action' => 'delete', 'id' => $obj->id);
        // FIXME: change to language string
        echo print_single_button('index.php', $options, get_string('delete_label','block_curr_admin').' ' . get_string($obj->get_verbose_name(),'block_curr_admin'), 'get', '_self', true, get_string('delete_label','block_curr_admin').' ' . get_string($obj->get_verbose_name(),'block_curr_admin'));
        echo '</div>';
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

    /**
     * Prints out the page that displays a list of items returned from a query.
     * @param $items array of records to print
     * @param $numitems number of records in the $items array
     * @param $columns associative array of column id => column heading text
     * @param $filter filter object to use, null if there is none
     * @param $alphaflag boolean for whether to print the alpha query filter interface
     * @param $searchflag boolean for whether to print the substring search interface
     */
    function print_list_view($items, $numitems, $columns, $filter=null, $alphaflag=false, $searchflag=false) {
        echo '<div style="float:right;">' . get_string('items_found', 'block_curr_admin', $numitems) . '</div>';

        if($alphaflag) {
            $this->print_alpha();
        }

        if($searchflag) {
            $this->print_search();
        }

        $this->print_paging_bar($numitems);

        if($filter) {
            $this->print_filter($filter);
        }

        $this->print_table($items, $columns);

        $this->print_paging_bar($numitems);

        $this->print_add_button();

        echo '<br clear="all" />' . "\n";
    }

    /**
     * Creates headers in the $table object for the columns in $column, and adds an extra column for control buttons.
     */
    function add_table_header($columns, $table=null) {
        global $CFG;

        $sort         = optional_param('sort', 'name', PARAM_ALPHA);
        $dir          = optional_param('dir', 'ASC', PARAM_ALPHA);
        $locsearch    = trim(optional_param('locsearch', '', PARAM_TEXT));
        $namesearch   = trim(optional_param('search', '', PARAM_TEXT));
        $alpha        = optional_param('alpha', '', PARAM_ALPHA);

        // Set HTML for table columns (URL, column name, sort icon)
        foreach ($columns as $column => $cdesc) {
            if ($sort != $column) {
                $columnicon = "";
                $columndir  = "ASC";
            } else {
                $columndir  = $dir == "ASC" ? "DESC":"ASC";
                $columnicon = $dir == "ASC" ? "down":"up";
                $columnicon = " <img src=\"$CFG->pixpath/t/$columnicon.gif\" alt=\"\" />";
            }

            $params = array(
				'sort' => $column,
				'dir' => $columndir,
				'search' => stripslashes($namesearch),
				'locsearch' => stripslashes($locsearch),
				'alpha' => $alpha
            );

            $target = $this->get_new_page($params);

            $table->head[]  = '<a href="' . $target->get_url() . '">' . $cdesc . '</a>' . $columnicon;
            $table->align[] = 'left';
            $table->wrap[]  = false;
        }

        // Add a column for the icons
        $table->head[]  = '';
        $table->align[] = 'center';
        $table->wrap[]  = true;

        return $table;
    }

    /**
     * Prints a detailed view of a specific record.
     */
    function action_view() {
        $id       = required_param('id', PARAM_INT);

        $obj = new $this->data_class($id);

        $form = new $this->form_class(null, array('obj' => $obj));
        $form->freeze();

        $this->print_tabs('view', array('id' => $id));
        $form->display();

        $this->print_delete_button($obj);
    }

    /**
     * Generic handler for the add action.  Prints the form to add a new
     * record, or creates a new record.
     */
    function action_add() {
        $target = $this->get_new_page(array('action' => 'add'));

        $obj = $this->get_default_object_for_add();
        $form = new $this->form_class($target->get_moodle_url(), $obj ? array('obj' => $obj) : NULL);

        if ($form->is_cancelled()) {
            $this->action_default();
            return;
        }

        $data = $form->get_data();

        if($data) {
            $obj = new $this->data_class();
            $obj->set_from_data($data);
            $obj->add();
            $this->after_cm_entity_add($obj);
            $target = $this->get_new_page(array('action' => 'view', 'id' => $obj->id));
            redirect($target->get_url(), ucwords($obj->get_verbose_name())  . ' ' . $obj->to_string() . ' saved.');
        } else {
            $form->display();
        }
    }

    /**
     * Specify default values for the action_add form.
     */
    function get_default_object_for_add() {
        return NULL;
    }

    /**
     * Hook that gets called after a CM entity is added through this page
     * Override in subclasses as needed
     * (Note: this function should only use the id field from the supplied cm entity
     *  as the rest of the data is not guaranteed to be there) 
     *
     * @param  object  $cm_entity  The CM entity added
     */
    function after_cm_entity_add($cm_entity) {
        //do nothing here, but allow subclass to override
    }

    /**
     * Generic handler for the edit action.  Prints the form for editing an
     * existing record, or updates the record.
     */
    function action_edit() {
        $id       = required_param('id', PARAM_INT);

        $target = $this->get_new_page(array('action' => 'edit'));
        $obj = new $this->data_class($id);

        if(!$obj->get_dbloaded()) {
            error('Invalid object id: ' . $id . '.');
        }

        $form = new $this->form_class($target->get_moodle_url(), array('obj' => $obj));

        if ($form->is_cancelled()) {
            $this->action_view();
            return;
        }

        $data = $form->get_data();

        if($data) {
            $obj->set_from_data($data);
            $obj->update();  // TODO: create a generalized "save" method that decides whether to do update or add
            $target = $this->get_new_page(array('action' => 'view', 'id' => $id));
            redirect($target->get_url(), ucwords($obj->get_verbose_name())  . ' ' . $obj->to_string() . ' updated.');
        } else {
            $this->print_tabs('edit', array('id' => $id));
            $form->display();
        }
    }

    /**
     * Generic handler for the savenew action.  Actually creates a new record.
     */
    function action_savenew() {
        // TODO: this is very similar to action_update, should be refactored
        $target = $this->get_new_page(array('action' => 'savenew'));

        $form = new $this->form_class($target->get_moodle_url());

        if ($form->is_cancelled()) {
            $this->action_default();
            return;
        }

        $data = $form->get_data();

        if($data) {
            $obj = new $this->data_class();
            $obj->set_from_data($data);
            $obj->add();
            $target = $this->get_new_page(array('action' => 'view', 'id' => $obj->id));
            redirect($target->get_url(), ucwords($obj->get_verbose_name())  . ' ' . $obj->to_string() . ' saved.');
        } else {
            // Validation must have failed, redisplay form
            $form->display();
        }
    }

    /**
     * Generates the HTML for the management buttons (such as edit and delete) for a record's row in the table.
     * @param $params extra parameters to pass through the buttons, such as a record id
     */
    function get_buttons($params) {
        $buttons = '';

        foreach($this->tabs as $tab) {
            $tab = $this->add_defaults_to_tab($tab);
            if($tab['showbutton'] === true) {
                $target = new $tab['page'](array_merge($tab['params'], $params));
                if (!$target->can_do()) {
                    continue;
                }

                $buttons .= '<a href="' . $target->get_url() . '"><img title="' . $tab['name'] . '" alt="' . $tab['name'] . '" src="pix/' . $tab['image'] . '" /></a> ';
            }
        }

        return $buttons;
    }

    /**
     * Inserts default values into the tabs array provided by the page class.
     * @param $tab tab to set the defaults for
     */
    function add_defaults_to_tab($tab) {
        $defaults = array('params' => array(), 'showbutton' => false, 'showtab' => 'false', 'image' => '');

        return array_merge($defaults, $tab);
    }

    public function get_navigation_add() {
        global $CFG;

        $page = $this->get_new_page(array('action' => 'add'));
        $navigation = $this->get_navigation_default();
        $navigation[] = array('name' => get_string('adding_item', 'block_curr_admin'),
                              'link' => $page->get_url());

        return $navigation;
    }

    function get_navigation_edit() {
        return $this->get_navigation_default();
    }

    function get_navigation_update() {
        return $this->get_navigation_default();
    }

    function get_navigation_delete() {
        return $this->get_navigation_view();
    }

    function get_navigation_view() {
        $id = isset($this->params['id']) ? $this->params['id'] : required_param('id', PARAM_INT);
        $navigation = $this->get_navigation_default();

        $obj = new $this->data_class($id);

        $page = $this->get_new_page(array('action' => 'view', 'id' => $id));

        $navigation[] =
            array('name' => htmlspecialchars($obj->to_string()),
                  'link' => $page->get_url()
                );

        return $navigation;
    }

    public function get_title() {
        return get_string('manage' . $this->data_class . 's', 'block_curr_admin');
    }

    public function get_navigation_default() {
        global $CFG;

        $page = $this->get_new_page();

        return array(array('name' => get_string('manage' . $this->data_class . 's', 'block_curr_admin'),
                    'link'  => $page->get_url(),));
    }

    /**
     * Prints the tab bar describe by the $tabs instance variable.
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
                $row[] = new tabobject($tab['tab_id'], $target->get_url(), $tab['name']);
            }
        }

        print_tabs(array($row), $selected);
    }
}

require_once CURMAN_DIRLOCATION . '/lib/table.class.php';

// TODO: get rid of the not-so-common get_item_display_... methods
class management_page_table extends display_table {
    var $page;
    var $viewurl;

    function __construct(&$items, $columns, $page) {
        $locsearch    = trim(optional_param('locsearch', '', PARAM_TEXT));
        $namesearch   = trim(optional_param('search', '', PARAM_TEXT));
        $alpha        = optional_param('alpha', '', PARAM_ALPHA);
        $params = array(
            'search' => stripslashes($namesearch),
            'locsearch' => stripslashes($locsearch),
            'alpha' => $alpha
            );

        //add page params
        if(!empty($page->params)) {
            $params += $page->params;
        }

        $this->page = $page;
        $this->viewurl = $page->get_new_page(array('action' => 'view'))->get_moodle_url();

        $target = $page->get_new_page($params);
        parent::__construct($items, $columns + array('_buttons' => ''),
                            $target->get_moodle_url());
    }

    function get_column_align__buttons() {
        return 'center';
    }

    function is_column_wrapped__buttons() {
        return false;
    }

    function is_sortable__buttons() {
        return false;
    }

    function get_item_display__buttons($column, $item) {
        return $this->page->get_buttons(array('id' => $item->id));
    }


    // this breaks some pages:
    //function get_item_display_idnumber($column, $item) {
    //    return '<a href="index.php?s=rep&amp;section=rept&amp;type=classroster&amp;' .
    //        'class=' . $item->id . '">' . $item->idnumber . '</a>';
    //}

    function get_item_display_envname($column, $item) {
        return '<div align="center"><span title="'.$item->envdescription.'">'.
            $item->envname.'</span></div>';
    }

    function get_item_display_moodlecourse($column, $item) {
        global $CFG, $CURMAN;
        $coursename = $CURMAN->db->get_field('course', 'fullname', 'id', $item->moodlecourseid);
        return ($item->moodlecourseid == '') ? 'n/a' : '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$item->moodlecourseid.'">'.$coursename.'</a>';
    }

    function get_item_display_startdate($column, $item) {
        return $this->get_date_item_display($column, $item);
    }

    function get_item_display_enddate($column, $item) {
        return $this->get_date_item_display($column, $item);
    }

    function get_item_display_starttime($column, $item) {
        global $CURMAN;

        if (($item->starttimehour == '0') && ($item->starttimeminute == '0')) {
            return 'n/a';
        } else {
            if(empty($CURMAN->config->time_format_12h)) {
                return $item->starttimehour . ':' . sprintf("%02d", $item->starttimeminute);
            } else {
                if($item->starttimehour / 12 > 1) {
                    return ($item->starttimehour % 12) . ':' . sprintf("%02d pm", $item->starttimeminute);
                } else if($item->starttimehour == 12) {
                    return 12 . ':' . sprintf("%02d pm", $item->starttimeminute);
                } else if($item->starttimehour == 0) {
                    return 12 . ':' . sprintf("%02d am", $item->starttimeminute);
                } else {
                    return $item->starttimehour . ':' . sprintf("%02d am", $item->starttimeminute);
                }
            }
        }
    }

    function get_item_display_endtime($column, $item) {
        global $CURMAN;

        if (($item->endtimehour == '0') && ($item->endtimeminute == '0')) {
            return 'n/a';
        } else {
            if(empty($CURMAN->config->time_format_12h)) {
                return $item->endtimehour . ':' . sprintf("%02d", $item->endtimeminute);
            } else {
                if($item->endtimehour / 12 > 1) {
                    return ($item->endtimehour % 12) . ':' . sprintf("%02d pm", $item->endtimeminute);
                } else if($item->endtimehour == 12) {
                    return 12 . ':' . sprintf("%02d pm", $item->endtimeminute);
                } else if($item->endtimehour == 0) {
                    return 12 . ':' . sprintf("%02d am", $item->endtimeminute);
                } else {
                    return $item->endtimehour . ':' . sprintf("%02d am", $item->endtimeminute);
                }
            }
        }
    }

    function get_item_display_location($column, $item) {
        return '<a href="index.php?s=rep&amp;section=rept&amp;type=' .
            'recloc&amp;loc=' . $item->location . '">' .
            $item->location . '</a>';
    }

    function get_item_display_lastclass($column, $item) {
        return'<a href="index.php?s=rep&amp;section=rept&amp;type=' .
            'classroster&amp;class=' . $item->lastclassid . '">' .
            $item->lastclass . '</a>';
    }

    function get_item_display_country($column, $item) {
        return $this->get_country_item_display($column, $item);
    }

    function get_item_display_timecreated($column, $item) {
        if (!empty($item->origenroldate)) {
            $dateparts = explode('/', $item->origenroldate);
            $bt = mktime(0, 0, 0, $dateparts[1], $dateparts[2], $dateparts[0]);
            return userdate($bt);
        } else if (!empty($item->timecreated)) {
            return userdate($item->timecreated);
        } else {
            return '-';
        }
    }

    function get_item_display($column, $item) {
        $display = parent::get_item_display($column, $item);
        $tmppage = clone($this->page);
        if (in_array($column, $this->page->view_columns)) {
            $tmppage->params = array('id' => $item->id,
                                     'action' => 'view');
            if ($tmppage->can_do()) {
                return '<a href="' . $this->viewurl->out(false, array('id' => $item->id)) . '">' . $display . '</a>';
            }
        }
        return $display;
    }
}
