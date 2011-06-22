<?php
/**
 * Page for bulk user actions.
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

require_once CURMAN_DIRLOCATION . '/lib/selectionpage.class.php';
require_once CURMAN_DIRLOCATION . '/lib/table.class.php';
require_once CURMAN_DIRLOCATION . '/lib/usermanagement.class.php';
require_once CURMAN_DIRLOCATION . '/form/bulkuserform.class.php';
require_once CURMAN_DIRLOCATION . '/lib/contexts.php';

class bulkuserpage extends selectionpage {
    var $pagename = 'bulkuser';
    var $section = 'admn';
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
        $contexts = bulkuserpage::get_contexts('block/curr_admin:user:edit');
        return !$contexts->is_empty();
    }

    function get_title_default() {
        return get_string('userbulk', 'admin');
    }

    function get_navigation_default() {
        return array(array('name' => get_string('userbulk', 'admin'),
                           'link' => $this->get_url()));
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

        if ($sort == 'name') {
            $sort = 'lastname';
        }

        $extrasql = $filter->get_sql_filter();

        //filter based on cluster role assignments
        $context_set = cm_context_set::for_user_with_capability('cluster', 'block/curr_admin:user:edit', $USER->id);

        // Get list of users
        $items    = usermanagement_get_users($sort, $dir, 30*$pagenum, 30, $extrasql, $context_set);
        $numitems = usermanagement_count_users($extrasql, $context_set);

        return array($items, $numitems);
    }

    function print_selection_filter($filter) {
        $filter->display_add();
        $filter->display_active();
    }

    function get_records_from_selection($record_ids) {
        global $CURMAN;

        $users = $CURMAN->db->get_records_select('crlm_user', 'id in ('.implode(',',$record_ids).')');

        return $users;
    }

    function create_selection_table($records, $baseurl) {
        return new bulkusertable($records, new moodle_url($baseurl));
    }

    function process_selection($data) {
        global $CURMAN;

        if (empty($data->_selection)) {
            echo print_error('no_items_selected', 'block_curr_admin');
        } else {
            $usersstring = implode(', ', array_map('cm_fullname', $CURMAN->db->get_records_select('crlm_user', 'id in ('.implode(',',$data->_selection).')')));
            notice_yesno(get_string('confirm_bulk_'.$data->do, 'block_curr_admin', $usersstring),
                         'index.php', 'index.php',
                         array('s' => $this->pagename,
                               'action' => $data->do,
                               'selectedusers' => implode(',',$data->_selection)
                             ),
                         array('s' => $this->pagename),
                         'POST', 'GET');
        }
    }

    function action_inactive() {
        global $CURMAN;

        $users = explode(',',$this->required_param('selectedusers',PARAM_TEXT));

        // make sure everything is an int
        foreach ($users as $key => $val) {
            $users[$key] = (int)$val;
            if (empty($users[$key])) {
                unset($users[$key]);
            }
        }

        $result = $CURMAN->db->execute_sql('UPDATE '.$CURMAN->db->prefix_table('crlm_user').' SET inactive=1 WHERE id in ('.implode(',',$users).')');

        $tmppage = new bulkuserpage();
        if ($result) {
            redirect($tmppage->get_url(), get_string('success_bulk_inactive', 'block_curr_admin'));
        } else {
            print_error('error_bulk_inactive', 'block_curr_admin', $tmppage->get_url());
        }
    }

    function action_delete() {
        global $CURMAN;
        require_once CURMAN_DIRLOCATION . '/lib/user.class.php';

        $users = explode(',',$this->required_param('selectedusers',PARAM_TEXT));

        // make sure everything is an int
        foreach ($users as $key => $val) {
            $users[$key] = (int)$val;
            if (empty($users[$key])) {
                unset($users[$key]);
            }
        }

        $result = true;
        foreach ($users as $userid) {
            $userobj = new user($userid);
            if (!($result = $userobj->delete())) {
                break;
            }
        }

        $tmppage = new bulkuserpage();
        if ($result) {
            redirect($tmppage->get_url(), get_string('success_bulk_delete', 'block_curr_admin'));
        } else {
            print_error('error_bulk_delete', 'block_curr_admin', $tmppage->get_url());
        }
    }
}


/**
 * Table to display the list of potential users.
 */
class bulkusertable extends selection_table {
    function __construct(&$items, $url) {
        $columns = array(
            '_selection'       => '',
            'idnumber'     => get_string('id', 'block_curr_admin'),
            'name'         => get_string('name', 'block_curr_admin'),
            'country'      => get_string('country', 'block_curr_admin'),
            'timecreated'  => get_string('registered_date', 'block_curr_admin')
            );
        parent::__construct($items, $columns, $url);
    }

    function get_item_display_timecreated($column, $item) {
        return $this->get_date_item_display($column, $item);
    }

    function get_item_display_name($column, $item) {
        if (isset($item->name)) {
            return $item->name;
        } else {
            return cm_fullname($item);
        }
    }
}


/**
 * Table to display the list of selected users.  Same as usertable, but
 * disallows sorting (not implemented), and uses PHP to show the name (since
 * we're just using the raw data from the user table).
 */
class selectedbulkusertable extends bulkusertable {
    function __construct(&$items) {
        parent::__construct($items, '');
    }

    function is_sortable_default() {
        return false;
    }

    function get_item_display_name($column, $item) {
        return cm_fullname($item);
    }
}


/**
 * User filter that adds a filter for users who do not have an associated
 * Moodle user.
 */
class bulk_user_filtering extends cm_user_filtering {
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

        parent::cm_user_filtering($fieldnames, $baseurl, $extraparams);
    }

    function get_field($fieldname, $advanced) {
        switch ($fieldname) {
        case 'nomoodleuser':
            return new no_moodle_user_filter('nomoodleuser', get_string('nomoodleuser_filt', 'block_curr_admin'), $advanced, 'usr.idnumber');
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
        $this->_field   = $field;
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
                return array('value'=>(string)$formdata->$field);
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
        global $CFG;
        return "not exists (select * from {$CFG->prefix}user _u where _u.idnumber = {$this->_field})";
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    function get_label($data) {
        $retval = '';

        if(!empty($data['value'])) {
            $retval = get_string('nomoodleuser_filt', 'block_curr_admin');
        }

        return $retval;
    }
}

?>