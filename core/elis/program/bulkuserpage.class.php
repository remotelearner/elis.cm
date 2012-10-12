<?php
/**
 * Page for bulk user actions.
 *
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

require_once elispm::lib('selectionpage.class.php');
require_once elispm::file('form/bulkuserform.class.php');
require_once elispm::lib('data/user.class.php');

/* *** TBD ***
require_once CURMAN_DIRLOCATION . '/lib/table.class.php';
require_once CURMAN_DIRLOCATION . '/lib/contexts.php';
*** */

class bulkuserpage extends selectionpage {
    var $pagename    = 'bulkuser';
    var $section     = 'admn';
    var $form_class  = 'bulkuserform';  
    static $contexts = array();

    static function get_contexts($capability) {
        if (!isset(bulkuserpage::$contexts[$capability])) {
            global $USER;
            bulkuserpage::$contexts[$capability] = get_contexts_by_capability_for_user('user', $capability, $USER->id);
        }
        return bulkuserpage::$contexts[$capability];
    }

    function can_do_default() {
        //this allows for cluster role assignments to be taken into account
        $contexts = bulkuserpage::get_contexts('elis/program:user_edit');
        return !$contexts->is_empty();
    }

    function get_title_default() {
        return get_string('userbulk', 'admin');
    }

    function build_navbar_default($who = null) {
        return $this->navbar->add(get_string('userbulk', 'admin'), $this->url);
    }

    function get_selection_form() {
        return new bulkuserform();
    }

    function get_selection_filter() {
        // filter
        $filter = new bulk_user_filtering('index.php', array('s' => 'bulkuser'));
        return $filter;
    }

    function get_records($filter) {
        global $USER;

        $sort         = optional_param('sort', 'name', PARAM_ALPHA);
        $dir          = optional_param('dir', 'ASC', PARAM_ALPHA);
        $pagenum      = optional_param('page', 0, PARAM_INT);
        $perpage      = optional_param('perpage', 30, PARAM_INT);

        if ($sort == 'name') {
            $sort = 'lastname';
        }

        $extrasql = $filter->get_sql_filter();

        //filter based on cluster role assignments
        $context_set = pm_context_set::for_user_with_capability('cluster', 'elis/program:user_edit', $USER->id);

        // Get list of users
        $items    = usermanagement_get_users_recordset($sort, $dir, $perpage * $pagenum,
                                                       $perpage, $extrasql, $context_set);
        $numitems = usermanagement_count_users($extrasql, $context_set);
        return array($items, $numitems);
    }

    function print_selection_filter($filter) {
        $filter->display_add();
        $filter->display_active();
    }

    function get_records_from_selection($record_ids) {
        $sort         = $this->optional_param('sort', 'name', PARAM_ALPHA);
        $dir          = $this->optional_param('dir', 'ASC', PARAM_ALPHA);

        $users = user::find(new in_list_filter('id', $record_ids), array($sort => $dir));

        return $users;
    }

    function create_selection_table($records, $baseurl) {
        $baseurl = new moodle_url($baseurl);
        return new bulkusertable($records, $baseurl);
    }

    function process_selection($data) {
        global $DB, $OUTPUT;

        if (empty($data->_selection)) {
            print_error('no_items_selected', 'elis_program');
        } else {
            $usersstring = implode(', ', array_map('fullname', $DB->get_records_select('crlm_user', 'id in ('.implode(',',$data->_selection).')')));
            $buttoncontinue = new single_button(
                                  new moodle_url('index.php',
                                                 array('s' => $this->pagename,
                                                       'action' => $data->do,
                                                       'selectedusers' => implode(',',$data->_selection))),
                                  get_string('yes'), 'POST');
            $buttoncancel   = new single_button(
                                  new moodle_url('index.php',
                                                 array('s' => $this->pagename)),
                                  get_string('no'), 'GET');

            echo $OUTPUT->confirm(get_string('confirm_bulk_'. $data->do, 'elis_program', $usersstring), $buttoncontinue, $buttoncancel);
        }
    }

    function print_tabs() {
    }

    function do_inactive() { // action_inactive()
        global $DB;

        $users = explode(',',$this->required_param('selectedusers',PARAM_TEXT));

        $this->session_selection_deletion();

        // make sure everything is an int
        foreach ($users as $key => $val) {
            $users[$key] = (int)$val;
            if (empty($users[$key])) {
                unset($users[$key]);
            }
        }

        $result = $DB->execute('UPDATE {'. user::TABLE .'} SET inactive = 1
                                WHERE id in ('.  implode(',', $users) .')');

        $tmppage = new bulkuserpage();

        if ($result) {
            redirect($tmppage->url, get_string('success_bulk_inactive', 'elis_program'));
        } else {
            print_error('error_bulk_inactive', 'elis_program', $tmppage->url);
        }
    }

    function do_delete() { // action_delete()
        require_once elispm::lib('data/user.class.php');

        $this->session_selection_deletion();

        $users = explode(',', $this->required_param('selectedusers', PARAM_TEXT));
        // make sure everything is an int
        foreach ($users as $key => $val) {
            $users[$key] = (int)$val;
            if (empty($users[$key])) {
                unset($users[$key]);
            }
        }

        foreach ($users as $userid) {
            $userobj = new user($userid);
            $userobj->delete(); // TBD: try {} catch () {} ???
        }

        $tmppage = new bulkuserpage();
        redirect($tmppage->url, get_string('success_bulk_delete', 'elis_program'));
    }
}


/**
 * Table to display the list of potential users.
 */
class bulkusertable extends selection_table {
    private $display_date;

    function __construct(&$items, $url) {
        $columns = array(
            '_selection'  => array('header' => get_string('select'), 'sortable' => false),
            'idnumber'    => array('header' => get_string('id', 'elis_program')),
             'name'       => array('header' => get_string('name', 'elis_program')),
             'country'    => array('header' => get_string('country', 'elis_program')),
            'timecreated' => array('header' => get_string('registered_date', 'elis_program'))
            );

        $sort = optional_param('sort', 'name', PARAM_ALPHA);
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
 
        parent::__construct($items, $columns, $url);
        $this->display_date = new display_date_item(get_string('pm_date_format', 'elis_program'));
    }

    function get_item_display_timecreated($column, $item) {
        return $this->display_date->display($column, $item);
    }

    function get_item_display_name($column, $item) {
        if (isset($item->name)) {
            return $item->name;
        } else {
            return display_table::display_user_fullname_item($column, $item);
        }
    }
}


/**
 * User filter that adds a filter for users who do not have an associated
 * Moodle user.
 */
class bulk_user_filtering extends pm_user_filtering {
    function bulk_user_filtering($baseurl, $extraparams) {
        $fieldnames = array(
                'realname' => 0,
                'lastname' => 1,
                'firstname' => 1,
                'idnumber' => 1,
                'email' => 0,
                'city' => 1,
                'country' => 1,
                'username' => 0,
                'language' => 1,
                'clusterid' => 1,
                'curriculumid' => 1,
                'inactive' => 1,
                'nomoodleuser' => 1,
            );

        parent::pm_user_filtering($fieldnames, $baseurl, $extraparams);
    }

    function get_field($fieldname, $advanced) {
        switch ($fieldname) {
        case 'nomoodleuser':
            return new no_moodle_user_filter('nomoodleuser', get_string('nomoodleuser_filt', 'elis_program'), $advanced, 'idnumber');
        default:
            return parent::get_field($fieldname, $advanced);
        }
    }
}

/**
 * Filter for showing users who don't have an associated Moodle user
 */
class no_moodle_user_filter extends user_filter_type {
    var $_field;

    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     * @param array $options select options
     */
    function no_moodle_user_filter($name, $label, $advanced, $field) {
        parent::user_filter_type($name, $label, $advanced);
        $this->_field = $field;
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     */
    function setupForm(&$mform) {
        $mform->addElement('checkbox', $this->_name, $this->_label);

        // TODO: add help
        //$mform->setHelpButton($this->_name, array('simpleselect', $this->_label, 'filters'));

        if ($this->_advanced) {
            $mform->setAdvanced($this->_name);
        }
    }

    /**
     * Retrieves data from the form data
     * @param object $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    function check_data($formdata) {
        $field = $this->_name;

        if (array_key_exists($field, $formdata)) {
            if($formdata->$field != 0) {
                return array('value' => (string)$formdata->$field);
            }
        }
        return false;
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        $sql = "(NOT EXISTS (SELECT _u.id FROM {user} _u WHERE _u.idnumber = {$this->_field}))";
        return array($sql, array());
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    function get_label($data) {
        $retval = '';

        if(!empty($data['value'])) {
            $retval = get_string('nomoodleuser_filt', 'elis_program');
        }
        return $retval;
    }
}

