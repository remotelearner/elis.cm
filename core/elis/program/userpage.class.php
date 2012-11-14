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

require_once elispm::lib('data/user.class.php');
require_once elispm::file('usertrackpage.class.php');
require_once elispm::lib('managementpage.class.php');
require_once elispm::lib('contexts.php');
require_once elispm::file('form/userform.class.php');
require_once elispm::file('linkpage.class.php');
/*
require_once (CURMAN_DIRLOCATION . '/lib/curriculumcourse.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/curriculumstudent.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/usermanagement.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/jasperlib.php');
require_once (CURMAN_DIRLOCATION . '/lib/managementpage.class.php');
require_once (CURMAN_DIRLOCATION . '/clusterpage.class.php');
require_once (CURMAN_DIRLOCATION . '/usertrackpage.class.php');
require_once (CURMAN_DIRLOCATION . '/curriculumstudentpage.class.php');
require_once (CURMAN_DIRLOCATION . '/jasperreportpage.class.php');
require_once (CURMAN_DIRLOCATION . '/form/userform.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/user.class.php');
require_once (CURMAN_DIRLOCATION . '/rolepage.class.php');
require_once (CURMAN_DIRLOCATION . '/cluster/manual/assignpage.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/contexts.php');
*/

class userpage extends managementpage {
    var $data_class = 'user';
    var $form_class = 'userform';

    var $view_columns = array('idnumber', 'name');

    var $pagename = 'usr';
    var $section = 'users';

    static $contexts = array();

    public static function get_contexts($capability) {
        if (!isset(userpage::$contexts[$capability])) {
            global $USER;
            userpage::$contexts[$capability] = get_contexts_by_capability_for_user('user', $capability, $USER->id);
        }
        return userpage::$contexts[$capability];
    }

    /**
     * Check the cached capabilities for the current user.
     */
    public static function check_cached($capability, $id) {
        if (isset(userpage::$contexts[$capability])) {
            // we've already cached which contexts the user has delete
            // capabilities in
            $contexts = userpage::$contexts[$capability];
            return $contexts->context_allowed($id, 'user');
        }
        return null;
    }

    /**
     * Check if the user has the given capability for the requested user
     */
    public function _has_capability($capability, $id = null) {
        $id = $id ? $id : $this->required_param('id', PARAM_INT);
        // user contexts are different -- we rely on the cache because clusters
        // require special logic
        userpage::get_contexts($capability);
        $cached = userpage::check_cached($capability, $id);
        if ($cached !== null) {
            return $cached;
        }
        return has_capability($capability, $this->context);
    }

    public function __construct(array $params=null) {
        global $CFG;

        //tab for the Individual User report
        //permissions checking happens in the link page
        if (file_exists($CFG->dirroot.'/blocks/php_report/render_report_page.php')) {
            $report_tab = array(
                'tab_id' => 'report',
                'page' => 'linkpage',
                'params' => array(
                    'linkurl' => 'blocks/php_report/render_report_page.php',
                    'linkparams' => 'report,userid',
                    'report' => 'individual_user',
                    'userid' => '=id'
                ),
                'name' => get_string('report', 'elis_program'),
                'showbutton' => true,
                'image' => 'report'
            );
        }

        $this->tabs = array(
            array('tab_id' => 'view', 'page' => 'userpage', 'params' => array('action' => 'view'), 'name' => get_string('detail', 'elis_program'), 'showtab' => true),
            array('tab_id' => 'edit', 'page' => 'userpage', 'params' => array('action' => 'edit'), 'name' => get_string('edit', 'elis_program'), 'showtab' => true, 'showbutton' => true, 'image' => 'edit'),

            array('tab_id' => 'studentcurriculumpage', 'page' => 'studentcurriculumpage', 'name' => get_string('curricula', 'elis_program'), 'showtab' => true, 'showbutton' => true, 'image' => 'curriculum'),
            array('tab_id' => 'userclusterpage', 'page' => 'userclusterpage', 'name' => get_string('clusters', 'elis_program'), 'showtab' => true, 'showbutton' => true, 'image' => 'cluster'),
            array('tab_id' => 'usertrackpage', 'page' => 'usertrackpage', 'name' => get_string('tracks', 'elis_program'), 'showtab' => true, 'showbutton' => true, 'image' => 'track'),
            array('tab_id' => 'user_rolepage', 'page' => 'user_rolepage', 'name' => get_string('roles', 'role'), 'showtab' => true, 'showbutton' => false, 'image' => 'tag'),

            array('tab_id' => 'delete', 'page' => 'userpage', 'params' => array('action' => 'delete'), 'name' => get_string('delete', 'elis_program'), 'showbutton' => true, 'image' => 'delete'),
        );

        //tab for the Individual User report
        //permissions checking happens in the link page
        if (file_exists($CFG->dirroot.'/blocks/php_report/render_report_page.php')) {
            //script for rendering report pages exists in report block, so reports
            //are at least installed
            $report_tab = array('tab_id' => 'report',
                                'page' => 'induserlinkpage',
                                'params' => array('linkurl' => 'blocks/php_report/render_report_page.php',
                                                  'linkparams'=>'report,userid',
                                                  'report'=>'individual_user', 'userid'=>'=id'),
                                'name' => get_string('report', 'elis_program'),
                                'showbutton' => true,
                                'image' => 'report');
            $this->tabs[] = $report_tab;
        }

        parent::__construct($params);
    }

    /**
     * Defaults for new user add
     *
     * @return  stdClass  Form data, or null if none
     */
    function get_default_object_for_add() {
        $obj = new stdClass;
        $obj->language = 'en';
        return $obj;
    }

    function can_do_view() {
        return $this->_has_capability('elis/program:user_view');
    }

    function can_do_edit() {
        return $this->_has_capability('elis/program:user_edit');
    }

    function can_do_delete() {
        global $USER;
        // make sure we don't delete the admin user, or ourselves
        $cuser = new user($this->required_param('id', PARAM_INT));
        $muser = $cuser->get_moodleuser();

        if (!isset($muser->id)) {
            //no corresponding Moodle user, so just check the capability
            return $this->_has_capability('elis/program:user_delete');
        }

        return !is_primary_admin($muser->id) && $muser->id != $USER->id && $this->_has_capability('elis/program:user_delete');
    }

    function can_do_add() {
        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('elis/program:user_create', $context);
    }

    function can_do_default() {
        $contexts = userpage::get_contexts('elis/program:user_view');
        return !$contexts->is_empty();
    }

    function display_default() {
        // Get parameters
        $sort         = optional_param('sort', 'lastname', PARAM_ALPHA);
        $dir          = optional_param('dir', 'ASC', PARAM_ALPHA);

        $page         = optional_param('page', 0, PARAM_INT);
        $perpage      = optional_param('perpage', 30, PARAM_INT);        // how many per page

        // Define columns
        // todo: support display values of country and language plus accurate sorting
        $columns = array(
            'idnumber'     => array('header' => get_string('id', 'elis_program')),
            'name'         => array('header' => array('firstname' => array('header' => get_string('firstname')),
                                                      'lastname' => array('header' => get_string('lastname'))),
                                    'display_function' => array('display_table', 'display_user_fullname_item')),
            'country'      => array('header' => get_string('country')),
            'language'     => array('header' => get_string('language')),
            'timecreated'  => array('header' => get_string('registered_date', 'elis_program'),
                                    'display_function' => array(new display_date_item(), 'display')),
        );

        // set sorting
        if ($dir !== 'DESC') {
            $dir = 'ASC';
        }
        if (isset($columns[$sort])) {
            $columns[$sort]['sortable'] = $dir;
        } elseif (isset($columns['name']['header'][$sort])) {
            $columns['name']['header'][$sort]['sortable'] = $dir;
        } else {
            $sort = 'lastname';
            $columns['name']['header']['lastname']['sortable'] = $dir;
        }

        // Generate SQL filter
        $filter = new pm_user_filtering(null, 'index.php', array('s' => 'usr'));
        $extrasql = $filter->get_sql_filter();

        // Get list of users
        $contextset = userpage::get_contexts('elis/program:user_view');
        $userfilter = new AND_filter(array(new select_filter($extrasql[0], $extrasql[1]), $contextset->get_filter('id')));
        $items    = user::find($userfilter, array($sort => $dir), $page*$perpage, $perpage);
        $numitems = user::count($userfilter);

        // cache the context capabilities
        userpage::get_contexts('elis/program:user_edit');
        userpage::get_contexts('elis/program:user_delete');

        $this->print_list_view($items, $numitems, $columns, $filter);
    }

    /**
     * Prints a detailed view of a specific record.
     */
    function display_view() {
        global $CFG;

        $id       = required_param('id', PARAM_INT);

        $obj = new $this->data_class($id);
        $obj->load();

        $muser = $obj->get_moodleuser();

        if(!empty($muser)) {
            $obj->username = html_writer::link(new moodle_url('/user/view.php', array('id' => $muser->id)), htmlspecialchars($obj->username));
        }

        $form = new $this->form_class(null, array('obj' => $obj->to_object()));
        $form->freeze();

        $this->print_tabs('view', array('id' => $id));
        $form->display();

        $this->print_delete_button($obj);
    }
}

/**
 * Page for linking to the individual user report, which handles permission
 * checking for that link as well
 */
class induserlinkpage extends linkpage {

    /**
     * Determine whether the current user has permissions to view the link to a
     * user's individual user report page
     */
    function can_do_default() {
        global $USER;

        //obtain the target user's PM user id
        $id = $this->required_param('userid', PARAM_INT);
        //obtain the target user's PM user id
        $cmuserid = cm_get_crlmuserid($USER->id);

        if ($cmuserid != 0 && ($cmuserid == $id)) {
            //looking at your own report, and you have a PM user id
            return true;
        }

        //regular permissions check

        // TODO: Ugly, this needs to be overhauled
        $upage = new userpage();
        return $upage->_has_capability('block/php_report:view', $id);
    }

}
