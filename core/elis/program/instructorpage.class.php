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

require_once elispm::lib('lib.php');
require_once elispm::lib('deprecatedlib.php'); // cm_get_param(), cm_error() ...
require_once elispm::lib('associationpage.class.php');
require_once elispm::lib('data/instructor.class.php');
require_once elispm::file('pmclasspage.class.php');

class instructorpage extends associationpage {
    const LANG_FILE = 'elis_program';

    var $data_class = 'instructor';
    var $pagename = 'ins';
    var $tab_page = 'pmclasspage'; // cmclasspage
    var $default_tab = 'instructorpage';

    //var $form_class = 'instructorform';

    var $section = 'curr';

    var $parent_data_class = 'pmclass'; // cmclass

    function __construct(array $params = null) {
        $this->tabs = array(
        array('tab_id' => 'currcourse_edit', 'page' => get_class($this), 'params' => array('action' => 'edit'), 'name' => 'Edit', 'showtab' => true, 'showbutton' => true, 'image' => 'edit'),
        array('tab_id' => 'delete', 'page' => get_class($this), 'params' => array('action' => 'delete'), 'name' => 'Delete', 'showbutton' => true, 'image' => 'delete'),
        );
        parent::__construct($params);
    }

    function can_do_default() {
        $id = $this->required_param('id', PARAM_INT);
        $pmclasspage = new pmclasspage(array('id' => $id)); // cmclasspage
        return $pmclasspage->can_do();
    }

    function can_do_add() {
        $id    = $this->required_param('id');
        $users = pm_process_user_enrolment_data();

        foreach($users as $uid => $user) {
            if(!instructor::can_manage_assoc($uid, $id)) {
                return false;
            }
        }

        return instructorpage::can_enrol_into_class($id);
    }

    function can_do_savenew() {
        return $this->can_do_add();
    }

    function can_do_delete() {
        global $DB;
        $association_id = $this->required_param('association_id', PARAM_INT);
        $instructor = new instructor($association_id);

        //todo: if we set up removing Moodle enrolments as is done for students,
        //perform extra checks here to make sure no other enrolment plugin was used

        return instructor::can_manage_assoc($instructor->userid, $instructor->classid);
    }

    function can_do_edit() {
        $association_id = $this->optional_param('association_id', '', PARAM_INT);
        if (empty($association_id)) { // TBD
            error_log('instructorpage.class.php::can_do_edit() - empty association_id! Returning: false');
            return false;
        }
        $instructor = new instructor($association_id);
        return instructor::can_manage_assoc($instructor->userid, $instructor->classid);
    }

    /**
     * Determines whether the current user is allowed to enrol users into the provided class
     *
     * @param   int      $classid  The id of the class we are checking permissions on
     *
     * @return  boolean            Whether the user is allowed to enrol users into the class
     *
     */
    static function can_enrol_into_class($classid) {
        global $USER;

        //check the standard capability
        if(pmclasspage::_has_capability('elis/program:assign_class_instructor', $classid)
           || pmclasspage::_has_capability('elis/program:assign_userset_user_class_instructor', $classid)) {
            return true;
        }

        //get the context for the "indirect" capability
        $context = pm_context_set::for_user_with_capability('cluster', 'elis/program:assign_userset_user_class_instructor', $USER->id);

        //we first need to go through tracks to get to clusters
        $track_listing = new trackassignment(array('classid' => $classid));
        $tracks = $track_listing->get_assigned_tracks();

        //iterate over the track ides, which are the keys of the array
        if(!empty($tracks)) {
            foreach(array_keys($tracks) as $track) {
                //get the clusters and check the context against them
                $clusters = clustertrack::get_clusters($track);

                if(!empty($clusters)) {
                    foreach($clusters as $cluster) {
                        if($context->context_allowed($cluster->clusterid, 'cluster')) {
                            return true;
                        }
                    }
                }

            }
        }

        return false;
    }

    function do_delete() { // action_confirm
        global $DB;
        $insid = required_param('association_id', PARAM_INT);
        $confirm = optional_param('confirm', null, PARAM_CLEAN);
        if ($confirm == null) {
            $this->display('delete');
            return;
        }

        $ins = new instructor($insid);
        $ins->load(); // TBD
        $classid = $ins->classid;
        //$event_object = $DB->get_record(instructor::TABLE, array('id' => $insid));
        if (md5($insid) != $confirm) {
            echo cm_error(get_string('invalidconfirm', self::LANG_FILE));
        } else {
            $user = $DB->get_record(user::TABLE, array('id' => $ins->userid));
            $user->name = fullname($user);
            $status = $ins->delete();
        }

        $instructorpage = new instructorpage();
        $target = $instructorpage->get_new_page(array('action' => 'default', 'id' => $classid));
        redirect($target->url, get_string('instructor_deleted', self::LANG_FILE, $user));
    }

    function do_add() {
        $this->do_savenew();
    }

    function display_add() {
        $action       = cm_get_param('action', 'add'); // TBD was: ''
        $delete       = cm_get_param('delete', 0);
        $confirm      = cm_get_param('confirm', '');   //md5 confirmation hash
        $confirmuser  = cm_get_param('confirmuser', 0);
        $insid        = cm_get_param('association_id', 0);
        $clsid        = cm_get_param('id', 0);
        $userid       = cm_get_param('userid', 0);
        $sort         = cm_get_param('sort', 'name'); // TBD was: 'assigntime'
        $dir          = cm_get_param('dir', 'ASC');
        $page         = cm_get_param('page', 0);
        $perpage      = cm_get_param('perpage', 30);        // how many per page
        $namesearch   = trim(cm_get_param('search', ''));
        $alpha        = cm_get_param('alpha', '');

        echo $this->get_add_form($clsid, $sort, $dir, $page, $perpage, $namesearch, $alpha);
    }

    function display_delete() { // action_delete
        $insid = required_param('association_id', PARAM_INT);
        echo $this->get_delete_form($insid);
    }

    function display_edit() { // action_edit
        $insid = required_param('association_id', PARAM_INT);
        echo $this->get_edit_form($insid);
    }

    function do_edit() {
        $this->display('edit');
    }

    function do_savenew() { // action_savenew
        $clsid = required_param('id', PARAM_INT);
        $users = pm_process_user_enrolment_data(); // ELIS-4089 -- JJF

        if (!empty($users)) {
            // Delete/reset checkbox selection for add action
            session_selection_deletion('add');

            foreach ($users as $uid => $user) {
                if (!empty($user['assign'])) {
                    $insrecord            = array();
                    $insrecord['classid'] = $clsid;
                    $insrecord['userid']  = $uid;

                    $startyear  = $user['startyear'];
                    $startmonth = $user['startmonth'];
                    $startday   = $user['startday'];
                    $insrecord['assigntime'] = pm_timestamp(0, 0, 0, $startmonth, $startday, $startyear);

                    $endyear  = $user['endyear'];
                    $endmonth = $user['endmonth'];
                    $endday   = $user['endday'];
                    $insrecord['completetime'] = pm_timestamp(0, 0, 0, $endmonth, $endday, $endyear);

                    $newins = new instructor($insrecord);
                    $status = $newins->save();
                }
            }
        }

        $this->display('add'); // $this->action_default();
    }

    function do_update() { // action_update
        $userid = required_param('userid', PARAM_INT);
        $insid = required_param('association_id', PARAM_INT);
        $clsid = required_param('id', PARAM_INT);

        // ELIS-8286: Using elis/core/lib/page.class.php::optional_param_array() since moodle's doesn't support nested arrays!
        $users = $this->optional_param_array('users', array(), PARAM_CLEAN);
        $uid   = $userid;
        $user  = current($users);

        $insrecord            = array();
        $insrecord['id']      = $insid;
        $insrecord['classid'] = $clsid;
        $insrecord['userid']  = $uid;

        $startyear  = $user['startyear'];
        $startmonth = $user['startmonth'];
        $startday   = $user['startday'];
        $insrecord['assigntime'] = pm_timestamp(0, 0, 0, $startmonth, $startday, $startyear);

        $endyear  = $user['endyear'];
        $endmonth = $user['endmonth'];
        $endday   = $user['endday'];
        $insrecord['completetime'] = pm_timestamp(0, 0, 0, $endmonth, $endday, $endyear);

        $ins = new instructor($insrecord);
        $status = $ins->save(); // WAS: $ins->data_update_record()

        $this->display('default'); // $this->action_default();
    }

    function display_default() { // action_default()
        global $OUTPUT;

        $action       = cm_get_param('action', 'default'); // TBD: was ''
        $delete       = cm_get_param('delete', 0);
        $confirm      = cm_get_param('confirm', ''); //md5 confirmation hash
        $confirmuser  = cm_get_param('confirmuser', 0);
        $insid        = cm_get_param('association_id', 0);
        $clsid        = cm_get_param('id', 0);
        $userid       = cm_get_param('userid', 0);
        $sort         = cm_get_param('sort', 'name'); // TBD 'assigntime'
        $dir          = cm_get_param('dir', 'ASC');
        $page         = cm_get_param('page', 0);
        $perpage      = cm_get_param('perpage', 30); // how many per page
        $namesearch   = trim(cm_get_param('search', ''));
        $alpha        = cm_get_param('alpha', '');

        $cls = new pmclass($clsid); // cmclass($clsid)

        //using standard buttons column here to get action permission checking for free
        //(see association_page_table for details)
        $columns = array(
            'idnumber'     => array('header' => get_string('instructor_idnumber', self::LANG_FILE),
                                    'display_function' => 'htmltab_display_function'),
            'name'         => array('header' => get_string('instructor_name', self::LANG_FILE),
                                    'display_function' => 'htmltab_display_function'),
            'assigntime'   => array('header' => get_string('instructor_assignment', self::LANG_FILE),
                                    'display_function' => 'get_item_display_assigntime'),
            'completetime' => array('header' => get_string('instructor_completion', self::LANG_FILE),
                                    'display_function' => 'get_item_display_completetime'),
            'buttons'  => array('header' => '', 'sortable' => false,
                                'display_function' => 'get_item_display_buttons')
        );

        if ($dir !== 'DESC') {
            $dir = 'ASC';
        }
        if (isset($columns[$sort])) {
            $columns[$sort]['sortable'] = $dir;
        } else {
            $sort = 'name';
            $columns[$sort]['sortable'] = $dir;
        }

        $inss    = instructor_get_listing($clsid, $sort, $dir, $page*$perpage, $perpage, $namesearch, $alpha);
        $numinss = instructor_count_records($clsid);

        $page_params = array('s' => 'ins', 'section' => 'curr', 'id' => $clsid,
                        'action' => $action, 'sort' => $sort, 'dir' => $dir,
                        'perpage' => $perpage, 'search' => $namesearch);

        pmalphabox(new moodle_url($this->_get_page_url(), $page_params),
            'alpha', get_string('instructor_name', self::LANG_FILE) .':');

        $full_url = "/elis/program/index.php?s=ins&amp;section=curr&amp;id=$clsid&amp;action=$action&amp;sort=$sort&amp;dir=$dir&amp;perpage=$perpage&amp;alpha=$alpha&amp;search="
                    . urlencode($namesearch) .'&amp;'; // TBD
        $pagingbar = new paging_bar($numinss, $page, $perpage, $full_url);
        echo $OUTPUT->render($pagingbar);
        flush();

        pmsearchbox($this, 'search', 'get', get_string('show_all_users', self::LANG_FILE));

        $table = NULL;
        if (!$inss) {
            pmshowmatches($alpha, $namesearch, null, 'no_instructor_matching');
        } else {
            // TBD
            //$table->align = array ("left", "left", "center", "center");
            //$table->width = "95%";

            //todo: convert this to use the standard listing function
            $newarr = array();
            foreach ($inss as $ins) {
                $deletestr = get_string('delete');
                $deletebutton = '<a href="index.php?s=ins&amp;section=curr&amp;id='.$clsid.'&amp;action=delete&amp;association_id='.$ins->id.'">'.
                                '<img src="'.$OUTPUT->pix_url('delete', 'elis_program').'" alt="'.$deletestr.'" title="'.$deletestr.'" /></a>';
                $editstr = get_string('edit');
                $editbutton = '<a href="index.php?s=ins&amp;section=curr&amp;id='.$clsid.'&amp;action=edit&amp;association_id='.$ins->id.'">'.
                              '<img src="'.$OUTPUT->pix_url('edit', 'elis_program').'" alt="'.$editstr.'" title="'.$editstr.'" /></a>';

                $tabobj = new stdClass;
                $tabobj->id = $ins->id;
                foreach ($columns as $column => $cdesc) {
                    if (isset($ins->{$column})) {
                        $tabobj->{$column} = $ins->{$column};
                    }
                }
                $newarr[] = $tabobj;
                //$table->data[] = $newarr;
            }
            if (!empty($newarr)) {
                $table = new instructor_page_table($newarr, $columns, $this);
            }
        }

        $options = array('s' => 'ins', 'section' => 'curr', 'action' => 'add',
                         'id' => $clsid);
        $add_instructor = new single_button(new moodle_url('index.php', $options), get_string('instructor_add','elis_program'), 'get');
            //'<a href="index.php?s=ins&amp;section=curr&amp;action=add&amp;id='. $clsid .'">'. get_string('instructor_add', self::LANG_FILE) .'</a>';

        //determine if current user has permissions to add instructors to this class instance
        $can_add = $this->can_do('add');

        if (!empty($table)) {
            if ($can_add) {
                echo '<div align="center">';
                echo $OUTPUT->render($add_instructor); // ->heading
                echo '</div><br/>';
            }
            echo $table->get_html();
            $pagingbar = new paging_bar($numinss, $page, $perpage, $full_url);
            echo $OUTPUT->render($pagingbar);
        }

        if ($can_add) {
            echo '<div align="center">';
            echo $OUTPUT->render($add_instructor); // ->heading
            echo '</div>';
        }
    }

    function get_add_form($clsid, $sort, $dir, $page, $perpage, $namesearch, $alpha) {
        $output = '';

        $newins = new instructor(); // TBD: was new instructor($clsid)
        //$newins->classid = $clsid;
        //$cls = new pmclass($clsid); // cmclass($clsid)

        $output .= $newins->edit_form_html($clsid, $sort, $dir, $page, $perpage, $namesearch, $alpha);

        return $output;
    }

    /**
     * Returns the edit ins form.
     *
     * @return string HTML for the form.
     */
    function get_edit_form($insid, $sort = '', $dir = '', $startrec = 0,
                           $perpage = 0, $namesearch = '', $alpha = '') {
        $ins = new instructor(); // TBD: was ($insid) ???
        $ins->id = $insid;
        $ins->classid = required_param('id', PARAM_INT);
        $ins->load(); // TBD
        //print_object($ins);
        return $ins->edit_form_html($ins->classid); // TBD: $insid == $classid ???
                                             // or use: $ins->classid
    }

    /**
     * Returns the delete instructor form.
     *
     * @param string $action Delete or confirm.
     * @param int    $id     The id of the instructor.
     * @return string HTML for the form.
     *
     */
    function get_delete_form($insid) {
        global $DB;
        $ins = new instructor($insid);
        $ins->load(); // TBD: no user name w/o ???
        $url = 'index.php'; // TBD: '/elis/program/index.php'
        $user = $DB->get_record(user::TABLE, array('id' => $ins->userid));
        $user->name = fullname($user);
        $message = get_string('confirm_delete_instructor', self::LANG_FILE, $user);
        $optionsyes = array('s' => 'ins', 'section' => 'curr', 'id' => $ins->classid,
                            'action' => 'delete', 'association_id' => $insid, 'confirm' => md5($insid));
        $optionsno = array('s' => 'ins', 'section' => 'curr', 'id' => $ins->classid,
                           /* 'search' => $ins->pmclass->idnumber */); // TBD???

        echo cm_delete_form($url, $message, $optionsyes, $optionsno);
    }

    function do_checkbox_selection_session() {
        parent::checkbox_selection_session();
    }

}

/**
 * Class responsible for implementing the display logic for the
 * instructor listing
 */
class instructor_page_table extends association_page_table {

    /**
     * Convert the assignment time to use the default date format
     * @param string $column The name of the column being formatted
     * @param object $item The object containing the row data
     */
    function get_item_display_assigntime($column, $item) {
        return get_date_item_display($column, $item);
    }

    /**
     * Convert the completion time to use the default date format
     * @param string $column The name of the column being formatted
     * @param object $item The object containing the row data
     */
    function get_item_display_completetime($column, $item) {
        return get_date_item_display($column, $item);
    }
}

