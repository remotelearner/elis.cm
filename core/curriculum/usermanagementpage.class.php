<?php
/**
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
require_once (CURMAN_DIRLOCATION . '/linkpage.class.php');

class usermanagementpage extends managementpage {
    var $data_class = 'user';
    var $form_class = 'userform';

    var $view_columns = array('idnumber', 'name');

    var $pagename = 'usr';
    var $section = 'users';

    static $contexts = array();

    static function get_contexts($capability) {
        if (!isset(usermanagementpage::$contexts[$capability])) {
            global $USER;
            usermanagementpage::$contexts[$capability] = get_contexts_by_capability_for_user('user', $capability, $USER->id);
        }
        return usermanagementpage::$contexts[$capability];
    }

    /**
     * Check the cached capabilities for the current user.
     */
    static function check_cached($capability, $id) {
        global $CURMAN;
        if (isset(usermanagementpage::$contexts[$capability])) {
            // we've already cached which contexts the user has delete
            // capabilities in
            $contexts = usermanagementpage::$contexts[$capability];
            return $contexts->context_allowed($id, 'user');
        }
        return null;
    }

    /**
     * Check if the user has the given capability for the requested user
     */
    function _has_capability($capability, $id = null) {
        $id = $id ? $id : $this->required_param('id', PARAM_INT);
        // user contexts are different -- we rely on the cache because clusters
        // require special logic
        usermanagementpage::get_contexts($capability);
        $cached = usermanagementpage::check_cached($capability, $id);
        if ($cached !== null) {
            return $cached;
        }
        $context = get_context_instance(context_level_base::get_custom_context_level('user', 'block_curr_admin'), $id);
        return has_capability($capability, $context);
    }

    function __construct($params=false) {
        $this->tabs = array(
            array('tab_id' => 'view', 'page' => 'usermanagementpage', 'params' => array('action' => 'view'), 'name' => get_string('detail', 'block_curr_admin'), 'showtab' => true),
            array('tab_id' => 'edit', 'page' => 'usermanagementpage', 'params' => array('action' => 'edit'), 'name' => get_string('edit', 'block_curr_admin'), 'showtab' => true, 'showbutton' => true, 'image' => 'edit.gif'),

            array('tab_id' => 'studentcurriculumpage', 'page' => 'studentcurriculumpage', 'name' => get_string('curricula', 'block_curr_admin'), 'showtab' => true, 'showbutton' => true, 'image' => 'curriculum.gif'),
            array('tab_id' => 'userclusterpage', 'page' => 'userclusterpage', 'name' => get_string('clusters', 'block_curr_admin'), 'showtab' => true, 'showbutton' => true, 'image' => 'cluster.gif'),
            array('tab_id' => 'usertrackpage', 'page' => 'usertrackpage', 'name' => get_string('tracks', 'block_curr_admin'), 'showtab' => true, 'showbutton' => true, 'image' => 'track.gif'),
            array('tab_id' => 'user_rolepage', 'page' => 'user_rolepage', 'name' => get_string('roles', 'role'), 'showtab' => true, 'showbutton' => false, 'image' => 'tag.gif'),

            array('tab_id' => 'delete', 'page' => 'usermanagementpage', 'params' => array('action' => 'delete'), 'name' => get_string('delete', 'block_curr_admin'), 'showbutton' => true, 'image' => 'delete.gif'),
            array('tab_id' => 'report', 'page' => 'linkpage', 'params' => array('linkurl' => 'blocks/php_report/render_report_page.php', 'linkparams'=>'report,userid', 'report'=>'individual_user', 'userid'=>'=id'), 'name' => get_string('report', 'block_curr_admin'), 'showbutton' => true, 'image' => 'report.gif'),
        );

        parent::__construct($params);
    }

    function can_do_view() {
        return $this->_has_capability('block/curr_admin:user:view');
    }

    function can_do_edit() {
        return $this->_has_capability('block/curr_admin:user:edit');
    }

    function can_do_delete() {
        global $USER;
        // make sure we don't delete the admin user, or ourselves
        $userid =  cm_get_moodleuserid($this->required_param('id', PARAM_INT));
        return !is_primary_admin($userid) && $userid != $USER->id && $this->_has_capability('block/curr_admin:user:delete');
    }

    function can_do_confirm() {
        return $this->can_do_delete();
    }

    function can_do_add() {
        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('block/curr_admin:user:create', $context);
    }

    function can_do_default() {
        $contexts = usermanagementpage::get_contexts('block/curr_admin:user:view');
        return !$contexts->is_empty();
    }

    function action_default() {
        // Get parameters
        $sort         = optional_param('sort', 'name', PARAM_ALPHA);
        $dir          = optional_param('dir', 'ASC', PARAM_ALPHA);

        $page         = optional_param('page', 0, PARAM_INT);
        $perpage      = optional_param('perpage', 30, PARAM_INT);        // how many per page

        // Define columns
        $columns = array(
            'idnumber'     => get_string('id', 'block_curr_admin'),
            'name'         => get_string('name', 'block_curr_admin'),
            'country'      => get_string('country', 'block_curr_admin'),
            'language'     => get_string('user_language', 'block_curr_admin'),
            'timecreated'  => get_string('registered_date', 'block_curr_admin')
        );

        // Generate SQL filter
        $filter = new cm_user_filtering(null, 'index.php', array('s' => 'usr', 'section' => 'users'));
        $extrasql = $filter->get_sql_filter();

        // Get list of users
        $items    = usermanagement_get_users($sort, $dir, $page*$perpage, $perpage, $extrasql, usermanagementpage::get_contexts('block/curr_admin:user:view'));
        $numitems = usermanagement_count_users($extrasql, usermanagementpage::get_contexts('block/curr_admin:user:view'));

        usermanagementpage::get_contexts('block/curr_admin:user:edit');
        usermanagementpage::get_contexts('block/curr_admin:user:delete');

        $this->print_list_view($items, $numitems, $columns, $filter);
    }

    /**
     * Prints a detailed view of a specific record.
     */
    function action_view() {
        global $CFG;

        $id       = required_param('id', PARAM_INT);

        $obj = new $this->data_class($id);

        $moodle_id = cm_get_moodleuserid($id);

        if(!empty($moodle_id)) {
            $obj->username = '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $moodle_id . '">' . $obj->username . '</a>';
        }

        $form = new $this->form_class(null, array('obj' => $obj));
        $form->freeze();

        $this->print_tabs('view', array('id' => $id));
        $form->display();

        $this->print_delete_button($obj);
    }
}
?>
