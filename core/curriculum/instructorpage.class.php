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


require_once (CURMAN_DIRLOCATION . '/lib/associationpage.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/cmclass.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/instructor.class.php');
require_once (CURMAN_DIRLOCATION . '/cmclasspage.class.php');


class instructorpage extends associationpage {
    var $data_class = 'instructor';
    var $pagename = 'ins';
    var $tab_page = 'cmclasspage';

    var $form_class = 'instructorform';

    var $section = 'curr';

    var $parent_data_class = 'cmclass';

    function __construct($params=false) {
        parent::__construct($params);

        $this->tabs = array(
        array('tab_id' => 'currcourse_edit', 'page' => get_class($this), 'params' => array('action' => 'edit'), 'name' => 'Edit', 'showtab' => true, 'showbutton' => true, 'image' => 'edit.gif'),
        array('tab_id' => 'delete', 'page' => get_class($this), 'params' => array('action' => 'delete'), 'name' => 'Delete', 'showbutton' => true, 'image' => 'delete.gif'),
        );
    }

    /*
        $action       = cm_get_param('action', '');
        $delete       = cm_get_param('delete', 0);
        $confirm      = cm_get_param('confirm', '');   //md5 confirmation hash
        $confirmuser  = cm_get_param('confirmuser', 0);
        $insid        = cm_get_param('association_id', 0);
        $clsid        = cm_get_param('id', 0);
        $userid       = cm_get_param('userid', 0);
        $sort         = cm_get_param('sort', 'assigntime');
        $dir          = cm_get_param('dir', 'ASC');
        $page         = cm_get_param('page', 0);
        $perpage      = cm_get_param('perpage', 30);        // how many per page
        $namesearch   = trim(cm_get_param('search', ''));
        $alpha        = cm_get_param('alpha', '');
     */

    function can_do_default() {
        $id = $this->required_param('id', PARAM_INT);
        $cmclasspage = new cmclasspage(array('id' => $id));
        return $cmclasspage->can_do('edit');
    }

    function action_confirm() {
        global $CURMAN;

        $insid = required_param('association_id', PARAM_INT);
        $confirm = required_param('confirm', PARAM_TEXT);

        $ins = new instructor($insid);

        $event_object = $CURMAN->db->get_record(INSTABLE, 'id', $insid);

        if (md5($insid) != $confirm) {
            echo cm_error('Invalid confirmation code!');
        } else if (!$ins->delete()){
            echo cm_error('Instructor "name: ' . cm_fullname($ins->user) . '" not deleted.');
        } else {
            //instructor_successfully_deleted
            echo cm_error('Instructor "name: ' . cm_fullname($ins->user) . '" deleted.');
        }

        $this->action_default();
    }

    function action_add() {
        $action       = cm_get_param('action', '');
        $delete       = cm_get_param('delete', 0);
        $confirm      = cm_get_param('confirm', '');   //md5 confirmation hash
        $confirmuser  = cm_get_param('confirmuser', 0);
        $insid        = cm_get_param('association_id', 0);
        $clsid        = cm_get_param('id', 0);
        $userid       = cm_get_param('userid', 0);
        $sort         = cm_get_param('sort', 'assigntime');
        $dir          = cm_get_param('dir', 'ASC');
        $page         = cm_get_param('page', 0);
        $perpage      = cm_get_param('perpage', 30);        // how many per page
        $namesearch   = trim(cm_get_param('search', ''));
        $alpha        = cm_get_param('alpha', '');

        echo $this->get_add_form($clsid, $sort, $dir, $page, $perpage, $namesearch, $alpha);
    }

    function action_delete() {
        global $CURMAN;

        $insid = required_param('association_id', PARAM_INT);

        echo $this->get_delete_form($insid);

    }

    function action_edit() {
        $insid = required_param('association_id', PARAM_INT);

        echo $this->get_edit_form($insid);
    }

    function action_savenew() {
        $users = cm_get_param('users', array());
        $clsid = required_param('id', PARAM_INT);

        if (!empty($users)) {
            foreach ($users as $uid => $user) {
                if (!empty($user['assign'])) {
                    $insrecord            = array();
                    $insrecord['classid'] = $clsid;
                    $insrecord['userid']  = $uid;

                    $startyear  = $user['startyear'];
                    $startmonth = $user['startmonth'];
                    $startday   = $user['startday'];
                    $insrecord['assigntime'] = mktime(0, 0, 0, $startmonth, $startday, $startyear);

                    $endyear  = $user['endyear'];
                    $endmonth = $user['endmonth'];
                    $endday   = $user['endday'];
                    $insrecord['completetime'] = mktime(0, 0, 0, $endmonth, $endday, $endyear);

                    $newins = new instructor($insrecord);
                    if (($status = $newins->add()) !== true) {
                        if (!empty($status->message)) {
                            echo cm_error('Record not created. Reason: '.$status->message);
                        } else {
                            echo cm_error('Record not created.');
                        }
                    }
                }
            }
        }

        $this->action_default();
    }

    function action_update() {
        $userid = required_param('userid', PARAM_INT);
        $insid = required_param('association_id', PARAM_INT);
        $clsid = required_param('id', PARAM_INT);

        $users = cm_get_param('users', array());
        $uid   = $userid;
        $user  = current($users);

        $insrecord            = array();
        $insrecord['id']      = $insid;
        $insrecord['classid'] = $clsid;
        $insrecord['userid']  = $uid;

        $startyear  = $user['startyear'];
        $startmonth = $user['startmonth'];
        $startday   = $user['startday'];
        $insrecord['assigntime'] = mktime(0, 0, 0, $startmonth, $startday, $startyear);

        $endyear  = $user['endyear'];
        $endmonth = $user['endmonth'];
        $endday   = $user['endday'];
        $insrecord['completetime'] = mktime(0, 0, 0, $endmonth, $endday, $endyear);

        $ins = new instructor($insrecord);
        if (($status = $ins->data_update_record()) !== true) {
            echo cm_error('Record not updated.  Reason: ' . $status->message);
        }

        $this->action_default();
    }

    function action_default() {
        global $CFG;

        $action       = cm_get_param('action', '');
        $delete       = cm_get_param('delete', 0);
        $confirm      = cm_get_param('confirm', '');   //md5 confirmation hash
        $confirmuser  = cm_get_param('confirmuser', 0);
        $insid        = cm_get_param('association_id', 0);
        $clsid        = cm_get_param('id', 0);
        $userid       = cm_get_param('userid', 0);
        $sort         = cm_get_param('sort', 'assigntime');
        $dir          = cm_get_param('dir', 'ASC');
        $page         = cm_get_param('page', 0);
        $perpage      = cm_get_param('perpage', 30);        // how many per page
        $namesearch   = trim(cm_get_param('search', ''));
        $alpha        = cm_get_param('alpha', '');

        $cls = new cmclass($clsid);

        $columns = array(
            'idnumber'=> get_string('instructor_idnumber', 'block_curr_admin'),
            'name'         => get_string('instructor_name', 'block_curr_admin'),
            'assigntime'   => get_string('instructor_assignment', 'block_curr_admin'),
            'completetime' => get_string('instructor_completion', 'block_curr_admin'),
        );

        foreach ($columns as $column => $cdesc) {
            if ($sort != $column) {
                $columnicon = "";
                $columndir = "ASC";
            } else {
                $columndir  = $dir == "ASC" ? "DESC":"ASC";
                $columnicon = $dir == "ASC" ? "down":"up";
                $columnicon = " <img src=\"$CFG->pixpath/t/$columnicon.gif\" alt=\"\" />";

            }
            $$column = "<a href=\"index.php?s=ins&amp;section=curr&amp;id=$clsid&amp;sort=$column&amp;dir=$columndir&amp;namesearch=".urlencode(stripslashes($namesearch))."&amp;alpha=$alpha\">".$cdesc."</a>$columnicon";
            $table->head[]  = $$column;
            $table->align[] = "left";
        }

        $table->head[]  = '';
        $table->align[] = 'center';

        $inss    = instructor_get_listing($clsid, $sort, $dir, $page*$perpage, $perpage, $namesearch, $alpha);
        $numinss = instructor_count_records($clsid);

        $alphabet = explode(',', get_string('alphabet'));
        $strall = get_string('all');


        /// Bar of first initials

        echo "<p style=\"text-align:center\">";
        echo get_string('instructor_name', 'block_curr_admin')." : ";
        if ($alpha) {
            echo " <a href=\"index.php?s=ins&amp;section=curr&amp;id=$clsid&amp;sort=name&amp;dir=ASC&amp;".
                 "perpage=$perpage\">$strall</a> ";
        } else {
            echo " <b>$strall</b> ";
        }
        foreach ($alphabet as $letter) {
            if ($letter == $alpha) {
                echo " <b>$letter</b> ";
            } else {
                echo " <a href=\"index.php?s=ins&amp;section=curr&amp;id=$clsid&amp;sort=name&amp;dir=ASC&amp;".
                     "perpage=$perpage&amp;alpha=$letter\">$letter</a> ";
            }
        }
        echo "</p>";

        print_paging_bar($numinss, $page, $perpage,
                "index.php?s=ins&amp;section=curr&amp;id=$clsid&amp;sort=$sort&amp;dir=$dir&amp;perpage=$perpage&amp;alpha=$alpha&amp;namesearch=".
                urlencode(stripslashes($namesearch))."&amp;");

        flush();


        if (!$inss) {
            $match = array();
            if ($namesearch !== '') {
               $match[] = s($namesearch);
            }
            if ($alpha) {
               $match[] = 'name'.": $alpha"."___";
            }
            $matchstring = implode(", ", $match);
            echo get_string('no_instructor_matching', 'block_curr_admin') . $matchstring;

            $table = NULL;

        } else {
            $table->align = array ("left", "left", "center", "center");
            $table->width = "95%";

            foreach ($inss as $ins) {
                $deletebutton = '<a href="index.php?s=ins&amp;section=curr&amp;id=' . $clsid .
                                '&amp;action=delete&amp;association_id=' . $ins->id . '">' .
                                '<img src="pix/delete.gif" alt="Delete" title="Delete" /></a>';
                $editbutton = '<a href="index.php?s=ins&amp;section=curr&amp;id=' . $clsid .
                              '&amp;action=edit&amp;association_id=' . $ins->id . '">' .
                              '<img src="pix/edit.gif" alt="Edit" title="Edit" /></a>';

                $newarr = array();
                foreach ($columns as $column => $cdesc) {
                    if (($column == 'assigntime') || ($column == 'completetime')) {
                        $newarr[] = !empty($ins->$column) ? date('M j, Y', $ins->$column) : '-';
                    } else {
                        $newarr[] = $ins->$column;
                    }
                }
                $newarr[] = $editbutton . ' ' . $deletebutton;
                $table->data[] = $newarr;
            }
        }

        echo "<table class=\"searchbox\" style=\"margin-left:auto;margin-right:auto\" cellpadding=\"10\"><tr><td>";
        echo "<form action=\"index.php\" method=\"get\"><fieldset class=\"invisiblefieldset\">";
        echo '<input type="hidden" name="section" value="curr" />';
        echo '<input type="hidden" name="s" value="ins" />';
        echo '<input type="hidden" name="id" value="' . $clsid . '" />';
        echo '<input type="hidden" name="sort" value="' . $sort . '" />';
        echo '<input type="hidden" name="dir" value="' . $dir . '" />';
        echo "<input type=\"text\" name=\"search\" value=\"".s($namesearch, true)."\" size=\"20\" />";
        echo "<input type=\"submit\" value=\"Search\" />";
        if ($namesearch) {
            echo "<input type=\"button\" onclick=\"document.location='index.php?s=ins&amp;" .
                 "section=curr&amp;id=$clsid&amp;sort=$sort&amp;dir=$dir&amp;perpage=$perpage'\"" .
                 "value=\"Show all users\" />";
        }
        echo "</fieldset></form>";
        echo "</td></tr></table>";

        if (!empty($table)) {
            print_heading('<a href="index.php?s=ins&amp;section=curr&amp;action=add&amp;id=' . $clsid .
                          '">' . get_string('instructor_add', 'block_curr_admin') . '</a>');
            print_table($table);
            print_paging_bar($numinss, $page, $perpage,
                             "index.php?s=ins&amp;section=curr&amp;sort=$sort&amp;dir=$dir&amp;perpage=$perpage".
                             "&amp;alpha=$alpha&amp;namesearch=".urlencode(stripslashes($namesearch))."&amp;");
        }
            print_heading('<a href="index.php?s=ins&amp;section=curr&amp;action=add&amp;id=' . $clsid .
                          '">' . get_string('instructor_add', 'block_curr_admin') . '</a>');
    }

    function get_add_form($clsid, $sort, $dir, $page, $perpage, $namesearch, $alpha) {
        $output = '';

        $newins = new instructor();
        $newins->classid = $clsid;

        $cls = new cmclass($clsid);

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
        $output = '';

        $ins = new instructor($insid);

        $output .= $ins->edit_form_html($insid);

        return $output;
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
        $ins = new instructor($insid);

        $url     = 'index.php';
        $message = get_string('confirm_delete_instructor', 'block_curr_admin', cm_fullname($ins->user));
        $optionsyes = array('s' => 'ins', 'section' => 'curr', 'id' => $ins->classid,
                            'action' => 'confirm', 'association_id' => $insid, 'confirm' => md5($insid));
        $optionsno = array('s' => 'ins', 'section' => 'curr', 'id' => $ins->classid,
                           'search' => $ins->cmclass->idnumber);

        echo cm_delete_form($url, $message, $optionsyes, $optionsno);

    }
}

?>
