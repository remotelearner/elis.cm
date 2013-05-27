<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2011 Remote-Learner.net Inc (http://www.remote-learner.net)
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

require_once($CFG->libdir . '/weblib.php');
require_once(elispm::lib('lib.php'));
require_once(elispm::lib('page.class.php'));

/**
 * This is the base class for a page that manages the basic data object types,
 * for example user, track or curriculum objects.  This is in contrast to the
 * "associationpage" class, which is used to manage the data objects that
 * associate two of the basic types together, for example curriculumcourse or
 * usertrack objects.
 *
 * When subclassing, you must have a constructor which sets a number of
 * instance variables that define how the class operates.  See an existing
 * subclass for an example.
 *
 */
abstract class managementpage extends pm_page {
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
        $context = $this->context;
        return has_capability('elis/program:manage', $context);
    }

    /**
     * Returns a new instance of the data object class this page manages.
     * @param $id
     * @return object
     */
    public function get_new_data_object($id=false) {
        return new $this->data_class($id);
    }

    /**
     * Generic handler for the delete action.  Deletes the record identified
     * by the 'id' parameter, if the confirm parameter is set.
     */
    public function do_delete() {
        global $CFG;

        if (!$this->optional_param('confirm', 0, PARAM_INT)) {
            return $this->display('delete');
        }

        require_sesskey();

        $id = $this->required_param('id', PARAM_INT);

        $obj = $this->get_new_data_object($id);
        $obj->load(); // force load, so that the confirmation notice has something to display
        $obj->delete();

        $returnurl = optional_param('return_url', null, PARAM_URL);
        if ($returnurl === null) {
            $target_page = $this->get_new_page(array(), true);
            $returnurl = $target_page->url;
        } else {
            $returnurl = $CFG->wwwroot.$returnurl;
        }

        redirect($returnurl, get_string('notice_'.get_class($obj).'_deleted', 'elis_program', $obj->to_object()));
    }

    /**
     * Generic handler for the delete action.  Prints a deletion confirmation
     * form that executes the delete action when submitted.
     */
    public function display_delete() {
        $id = $this->required_param('id', PARAM_INT);

        if(empty($id)) {
            print_error('invalid_id');
        }

        $obj = $this->get_new_data_object($id);

        $this->print_delete_form($obj);
    }

    /**
     * Prints a deletion confirmation form.
     * @param $obj record whose deletion is being confirmed
     */
    public function print_delete_form($obj) {
        global $OUTPUT;

        $obj->load(); // force load, so that the confirmation notice has something to display
        $message = get_string('confirm_delete_'.get_class($obj), 'elis_program', $obj->to_object());

        $target_page = $this->get_new_page(array('action' => 'view', 'id' => $obj->id, 'sesskey' => sesskey()), true);
        $no_url = $target_page->url;
        $no = new single_button($no_url, get_string('no'), 'get');

        $optionsyes = array('action' => 'delete', 'id' => $obj->id, 'confirm' => 1);
        $yes_url = clone($no_url);
        $yes_url->params($optionsyes);
        $yes = new single_button($yes_url, get_string('yes'), 'get');

        echo $OUTPUT->confirm($message, $yes, $no);
    }

    /**
     * Displays the page for when there are no items to list.
     */
    public function print_no_items() {
        $namesearch = trim($this->optional_param('search', '', PARAM_TEXT));
        $alpha = $this->optional_param('alpha', '', PARAM_ALPHA);

        $match = array();
        if ($namesearch) {
            $match[] = s($namesearch);
        }
        if ($alpha) {
            $match[] = "{$alpha}___";
        }
        $matchstring = implode(", ", $match);
        echo get_string('no_items_matching', 'elis_program', $matchstring);
    }


    /**
     * Prints the '1 2 3 ...' paging bar for when a query set is split across multiple pages.
     * @param $numitems total number of items in the query set
     */
    public function print_paging_bar($numitems) {
        global $OUTPUT;

        $page = $this->optional_param('page', 0, PARAM_INT);
        $perpage = $this->optional_param('perpage', 30, PARAM_INT);

        echo $OUTPUT->paging_bar($numitems, $page, $perpage, $this->url);
    }

    /**
     * Prints out a table with specified $columns and $items.
     *
     * @param $items array of records to print
     * @param $columns associative array of column id => column heading text.  column ids correspond to item properties.
     * @param $table the table object to print
     */
    public function print_table($items, $columns) {
        if ((is_array($items) && !empty($items)) || ($items instanceof Iterator && $items->valid() === true)) {
            $this->print_add_button();
            echo html_writer::empty_tag('br', array('clear' => 'all'));

            $table = $this->create_table_object($items, $columns);
            echo html_writer::start_tag('div', array('style' => 'overflow-x:auto;overflow-y:hidden;-ms-overflow-y:hidden;'));
            echo $table->get_html();
            echo html_writer::end_tag('div');
        } else {
            $this->print_no_items();
        }
    }

    /**
     * Creates a new table object with specified $items and $columns.
     * @param array $items
     * @param array $columns
     */
    public function create_table_object($items, $columns) {
        return new management_page_table($items, $columns, $this);
    }

    /**
     * Prints the more powerful filter interface, used on the usermanagement
     * page.
     *
     * @param $filter filter object
     */
    public function print_filter($filter) {
        // Print filtering interface
        $filter->display_add();
        $filter->display_active();
    }

    /**
     * Prints the single-button form used to request the add action for a
     * record type.
     */
    public function print_add_button() {
        global $OUTPUT;

        if (!$this->can_do('add')) {
            return;
        }

        $target_page = $this->get_new_page(array('action' => 'add'), true);
        $url = $target_page->url;

        echo html_writer::tag('div', $OUTPUT->single_button($url, get_string("add_{$this->data_class}",'elis_program'), 'get'), array('style' => 'text-align: center'));
    }

    /**
     * Prints the single-button form used to request the delete action for a
     * record.
     *
     * @param $obj record to request deletion for
     */
    public function print_delete_button($obj) {
        global $OUTPUT;

        if (!$this->can_do('delete')) {
            return;
        }

        $id = $this->required_param('id', PARAM_INT);

        $target_page = $this->get_new_page(array('action' => 'delete', 'id' => $id), true);
        $url = $target_page->url;

        echo html_writer::tag('div', $OUTPUT->single_button($url, get_string('delete_'.get_class($obj),'elis_program'), 'get'), array('style' => 'text-align: center'));
    }

    /**
     * Prints the 'All A B C ...' alphabetical filter bar.
     */
    public function print_alpha() {
        pmalphabox($this->url);
    }

    /**
     * Prints the text substring search interface.
     */
    public function print_search() {
        pmsearchbox($this);
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
    public function print_list_view($items, $numitems, $columns, $filter=null, $alphaflag=false, $searchflag=false) {
        $sparam = new stdClass;
        $sparam->num = $numitems;
        echo html_writer::tag('div', get_string("num_{$this->data_class}_found", 'elis_program', $sparam) /*, array('style' => 'float: right;') */);

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

        echo html_writer::empty_tag('br', array('clear' => 'all'));
    }

    /**
     * Prints a detailed view of a specific record.
     */
    public function display_view() {
        $id = $this->required_param('id', PARAM_INT);

        $this->print_tabs('view', array('id' => $id));

        $obj = $this->get_new_data_object($id);
        $obj->load();

        $form = new $this->form_class(null, array('obj' => $obj->to_object()));
        $form->freeze();
        $form->display();

        $this->print_delete_button($obj);
    }

    /**
     * Generic handler for the add action.  Prints the form to add a new
     * record, or creates a new record.
     */
    public function do_add() {
        $params = array('action' => 'add');
        if ($parentid = $this->optional_param('parent', 0, PARAM_INT)) {
            $params['parent'] = $parentid;
        }
        $target = $this->get_new_page($params, true);

        $obj = $this->get_default_object_for_add();
        $form = new $this->form_class($target->url, $obj ? array('obj' => $obj) : NULL);

        if ($form->is_cancelled()) {
            $params = array();
            if ($parentid) {
                $params['id'] = $parentid;
            }
            $target = $this->get_new_page($params, true);
            redirect($target->url);
            return;
        }

        $data = $form->get_data();
        if ($data) {
            require_sesskey();

            $obj = $this->get_new_data_object();
            $obj->set_from_data($data);
            $obj->save();
            $this->after_cm_entity_add($obj);

            $params = array('action' => 'view', 'id' => $obj->id);
            if ($parentid) {
                $params['parent'] = $parentid;
            }
            $target = $this->get_new_page($params, true);
            redirect($target->url);
        } else {
            $this->_form = $form;
            $this->display('add');
        }
    }

    /**
     * Prints the form to add a new record.
     */
    public function display_add() {
        if (!isset($this->_form)) {
            throw new ErrorException('Display called before Do');
        }

        $this->_form->display();
    }

    /**
     * Specify default values for the add form.
     */
    function get_default_object_for_add() {
        return NULL;
    }

    /**
     * Hook that gets called after a CM entity is added through this page
     * Override in subclasses as needed
     *
     * @param  object  $obj  The CM entity added
     */
    function after_cm_entity_add($obj) {
        //do nothing here, but allow subclass to override
    }

    /**
     * Generic handler for the edit action.  Prints the form for editing an
     * existing record, or updates the record.
     */
    function do_edit() {
        global $PAGE;
        $id = $this->required_param('id', PARAM_INT);
        $params = array('action' => 'edit', 'id' => $id);
        if ($parentid = $this->optional_param('parent', 0, PARAM_INT)) {
            $params['parent'] = $parentid;
        }
        $target = $this->get_new_page($params, true);
        $obj = $this->get_new_data_object($id);
        $obj->load();

        $params = array('action' => 'view', 'id' => $id);
        if ($parentid) {
            $params['parent'] = $parentid;
        }
        $form = new $this->form_class($target->url, array('obj' => $obj->to_object()));

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
     * Prints the form to edit a new record.
     */
    public function display_edit() {
        if (!isset($this->_form)) {
            throw new ErrorException('Display called before Do');
        }

        $id = $this->required_param('id', PARAM_INT);

        $this->print_tabs('edit', array('id' => $id));

        $this->_form->display();
    }

    /**
     * Generates the HTML for the management buttons (such as edit and delete) for a record's row in the table.
     * @param array $params extra parameters to pass through the buttons, such as a record id
     * @return string Button HTML
     */
    public function get_buttons($params) {
        global $OUTPUT;

        $buttons = array();

        $iconmap = array(
            'curriculum' => 'elisicon-program',
            'cluster' => 'elisicon-userset',
            'track' => 'elisicon-track',
            'course' => 'elisicon-course',
            'class' => 'elisicon-class',
            'user' => 'elisicon-user',
            'waiting' => 'elisicon-waitlist',
            'instructor' => 'elisicon-instructor',
            'grades' => 'elisicon-learningobjective',
            'calculator' => 'elisicon-resultsengine',
            'report' => 'elisicon-report',
            'edit' => 'elisicon-edit',
            'delete' => 'elisicon-remove'
        );
        foreach ($this->tabs as $tab) {
            $tab = $this->add_defaults_to_tab($tab);
            if ($tab['showbutton'] === true) {
                $target = new $tab['page'](array_merge($tab['params'], $params));
                if (!$target->can_do()) {
                    continue;
                }

                if (isset($iconmap[$tab['image']])) {
                    $iconattrs = array(
                        'title' => $tab['name'],
                        'alt' => $tab['name'],
                        'class' => $iconmap[$tab['image']].' managementicon elisicon'
                    );
                    $buttons[] = html_writer::link($target->url, '', $iconattrs);
                } else {
                    $iconattrs = array(
                        'title' => $tab['name'],
                        'alt' => $tab['name'],
                        'src' => $OUTPUT->pix_url($tab['image'], 'elis_program')
                    );
                    $icon = html_writer::empty_tag('img', $iconattrs);
                    $buttons[] = html_writer::link($target->url, $icon, array('class' => 'managementicon'));
                }

            }
        }
        return implode('', $buttons);
    }

    /**
     * Inserts default values into the tabs array provided by the page class.
     * @param $tab tab to set the defaults for
     */
    function add_defaults_to_tab($tab) {
        $defaults = array('params' => array(), 'showbutton' => false, 'showtab' => 'false', 'image' => '');

        return array_merge($defaults, $tab);
    }

    public function build_navbar_add($who = null) {
        if (!$who) {
            $who = $this;
        }
        $this->build_navbar_default($who);

        $url = $this->get_new_page(array('action' => 'add'), true)->url;
        $who->navbar->add(get_string("add_{$this->data_class}", 'elis_program'), $url);
    }

    function build_navbar_edit($who = null) {
        $this->build_navbar_view($who);
    }

    function build_navbar_delete($who = null) {
        if (!$who) {
            $who = $this;
        }
        $this->build_navbar_view($who);

        $who->navbar->add(get_string('delete'));
    }

    function build_navbar_view($who = null, $id_param = 'id', $extra_params = array()) {
        if (!$who) {
            $who = $this;
        }
        $this->build_navbar_default($who);

        if ($id_param == 'id' || !($id = $who->optional_param($id_param, 0, PARAM_INT))) {
            $id = $who->required_param('id', PARAM_INT);
        }

        $obj = $this->get_new_data_object($id); // TBD: $who-> ???
        $obj->load();
        $params = array_merge(array('action' => 'view', 'id' => $id),
                              $extra_params);
        $url = $this->get_new_page($params, true)->url; // TBD: who->
        $who->navbar->add(htmlspecialchars($obj), $url, navbar::TYPE_CUSTOM, null, null, new pix_icon('user', '', 'elis_program'));
    }

    public function build_navbar_default($who = null, $addparent = true, $params = array()) {
        if (!$who) {
            $who = $this;
        }
        if ($addparent) {
            parent::build_navbar_default($who, $addparent = true, $params = array());
        }
        $url = $this->get_new_page($params, true)->url; // TBD: who->
        $who->navbar->add(get_string("manage_{$this->data_class}", 'elis_program'), $url);
    }

    public function get_page_title_default() {
        return get_string("manage_{$this->data_class}", 'elis_program');
    }

    public function get_page_title_view() {
        $id = $this->required_param('id', PARAM_INT);
        $obj = $this->get_new_data_object($id);
        $obj->load();
        return $obj;
    }

    public function get_page_title_edit() {
        return $this->get_page_title_view();
    }

    public function get_page_title_delete() {
        return $this->get_page_title_view();
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
                $row[] = new tabobject($tab['tab_id'], $target->url, $tab['name']);
            }
        }

        print_tabs(array($row), $selected);
    }
}

require_once elis::lib('table.class.php');

// TODO: get rid of the not-so-common get_item_display_... methods
class management_page_table extends display_table {
    var $page;
    var $viewurl;

    /**
     * Constructor for a table that displays management page entries
     *
     * @param array $items The elements representing the rows of the table
     * @param array $columns The specifications for the columns of the table
     * @param elis_page $page An instance of the displayed entity type's page
     * @param array $extra_params Extra parameters to add to the page URL
     */
    function __construct(&$items, $columns, $page, $extra_params = null) {
        $namesearch   = trim(optional_param('search', '', PARAM_TEXT));
        $alpha        = optional_param('alpha', '', PARAM_ALPHA);
        $params = array(
            'search' => $namesearch,
            'alpha' => $alpha
            );

        //add page params
        if(!empty($page->params)) {
            $params += $page->params;
        }

        if (!empty($extra_params)) {
            //ok to use union here since we typically only pass
            //parameters that can't be accessed within the page class
            $params += $extra_params;
        }

        $this->page = $page;
        $this->display_date_item = new display_date_item(get_string('pm_date_format', 'elis_program'));

        $target = $page->get_new_page($params, true);
        parent::__construct($items, $columns + array('_buttons' => array('sortable' => false, 'wrapped' => false, 'align' => 'center')),
                            $target->url);
    }

    function get_item_display__buttons($column, $item) {
        return $this->page->get_buttons(array('id' => $item->id));
    }

    function get_item_display_envname($column, $item) {
        return html_writer::tag('span', htmlspecialchars($item->envname), array('title' => $item->envdescription));
    }

    function get_item_display_moodlecourse($column, $item) {
        global $CFG, $DB;
        $coursename = $DB->get_field('course', 'fullname', array('id' => $item->moodlecourseid));
        return ($item->moodlecourseid == '') ? 'n/a' : html_writer::link(new moodle_url('/course/view.php', array('id' => $item->moodlecourseid)), htmlspecialchars($coursename));
    }

    function get_item_display_startdate($column, $item) {
        return $this->display_date_item->display($column, $item);
    }

    function get_item_display_enddate($column, $item) {
        return $this->display_date_item->display($column, $item);
    }

    function get_item_display_starttime($column, $item) {
        if ($item->starttimehour >= 25 || $item->starttimeminute >= 61) {
            return '-';
        } else {
            if(empty(elis::$config->elis_program->time_format_12h)) {
                return sprintf('%02d:%02d', $item->starttimehour, $item->starttimeminute);
            } else {
                if($item->starttimehour / 12 > 1) {
                    return sprintf('%d:%02d pm', ($item->starttimehour % 12), $item->starttimeminute);
                } else if($item->starttimehour == 12) {
                    return sprintf("12:%02d pm", $item->starttimeminute);
                } else if($item->starttimehour == 0) {
                    return sprintf("12:%02d am", $item->starttimeminute);
                } else {
                    return sprintf('%d:%02d am', $item->starttimehour, $item->starttimeminute);
                }
            }
        }
    }

    function get_item_display_endtime($column, $item) {
        if ($item->endtimehour >= 25 || $item->endtimeminute >= 61) {
            return '-';
        } else {
            if(empty(elis::$config->elis_program->time_format_12h)) {
                return sprintf('%02d:%02d', $item->endtimehour, $item->endtimeminute);
            } else {
                if($item->endtimehour / 12 > 1) {
                    return sprintf('%d:%02d pm', ($item->endtimehour % 12), $item->endtimeminute);
                } else if($item->endtimehour == 12) {
                    return sprintf('12:%02d pm', $item->endtimeminute);
                } else if($item->endtimehour == 0) {
                    return sprintf('12:%02d am', $item->endtimeminute);
                } else {
                    return sprintf("%d:%02d am", $item->endtimehour, $item->endtimeminute);
                }
            }
        }
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

    /**
     * @todo make this a decorator
     */
    function get_item_display($column, $item) {
        $display = parent::get_item_display($column, $item);
        if (in_array($column, $this->page->view_columns)) {
            $target = $this->page->get_new_page(array('id' => $item->id,
                                                      'action' => 'view'), true);
            if ($target->can_do()) {
                return html_writer::link($target->url, $display);
            }
        }
        return $display;
    }
}
