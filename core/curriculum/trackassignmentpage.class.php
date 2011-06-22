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

require_once (CURMAN_DIRLOCATION . '/lib/page.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/associationpage.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/track.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/cmclass.class.php');
require_once (CURMAN_DIRLOCATION . '/form/trackassignmentform.class.php');
require_once (CURMAN_DIRLOCATION . '/trackpage.class.php');
require_once (CURMAN_DIRLOCATION . '/cmclasspage.class.php');

class trackassignmentpage extends associationpage {
    var $data_class = 'trackassignmentclass';
    var $form_class = 'trackassignmentform';
    var $parent_data_class = 'track';

    var $pagename = 'trkcls';
    var $tab_page = 'trackpage';

    var $section = 'curr';

    function __construct($params=false) {
        parent::__construct($params);

        $this->tabs = array(
            array('tab_id' => 'edit', 'page' => get_class($this), 'params' => array('action' => 'edit'), 'name' => get_string('edit','block_curr_admin'), 'showtab' => true, 'showbutton' => true, 'image' => 'edit.gif'),
            array('tab_id' => 'delete', 'page' => get_class($this), 'params' => array('action' => 'delete'), 'name' => get_string('delete','block_curr_admin'), 'showbutton' => true, 'image' => 'delete.gif'),
        );
    }

    function can_do_default() {
        $id = $this->required_param('id', PARAM_INT);
        return trackpage::_has_capability('block/curr_admin:associate', $id);
    }

    function can_do_savenew() {
        // the user must have 'block/curr_admin:associate' permissions on both ends
        $trackid = $this->required_param('trackid', PARAM_INT);
        $classid = $this->required_param('classid', PARAM_INT);

        return trackpage::_has_capability('block/curr_admin:associate', $trackid)
            && cmclasspage::_has_capability('block/curr_admin:associate', $classid);
    }

    function can_do_edit() {
        // the user must have 'block/curr_admin:associate' permissions on both
        // ends
        $association_id = $this->required_param('association_id', PARAM_INT);
        $record = new trackassignmentclass($association_id);
        $trackid = $record->trackid;
        $classid = $record->classid;

        return trackpage::_has_capability('block/curr_admin:associate', $trackid)
            && cmclasspage::_has_capability('block/curr_admin:associate', $classid);
    }

    function can_do_delete() {
        return $this->can_do_edit();
    }

    function action_add() {
        // TODO: update
        $trackid = required_param('trackid', PARAM_INT);
        $clsid = required_param('clsid', PARAM_INT);
        $id = required_param('id', PARAM_INT);

        $target = $this->get_new_page(array('action' => 'savenew', 'id' => $id, 'trackid' => $trackid, 'clsid' => $clsid));

        $form = new $this->form_class($target->get_moodle_url(), array('trackid' => $trackid, 'classid' => $clsid));

        $form->set_data(array('trackid' => $trackid, 'classid' => $clsid, 'id' => $id));

        $form->display();
    }

    function action_default() {
        global $CURMAN;

        $id = required_param('id', PARAM_INT);

        $sort         = optional_param('sort', 'clsname', PARAM_ALPHA);
        $dir          = optional_param('dir', 'ASC', PARAM_ALPHA);

        $page         = optional_param('page', 0, PARAM_INT);
        $perpage      = optional_param('perpage', 30, PARAM_INT);        // how many per page

        $namesearch   = trim(cm_get_param('search', ''));
        $alpha        = cm_get_param('alpha', '');

        $columns = array(
            'clsname'   => get_string('class_id_number', 'block_curr_admin'),
            'autoenrol' => get_string('auto_enrol', 'block_curr_admin'),
            'enrolments' => get_string('enrolments', 'block_curr_admin'),
            'buttons' => '',
        );

        $items = track_assignment_get_listing($id, $sort, $dir, $page*$perpage, $perpage, $namesearch, $alpha);
        $numitems = track_assignment_count_records($id, $namesearch, $alpha);

        if (empty($items)) {
            print_string('no_items_matching', 'block_curr_admin');
        } else {
            $this->print_num_items($numitems);
            $this->print_alpha();
            $this->print_search();

            $formatters = $this->create_link_formatters(array('clsname'), 'cmclasspage', 'clsid');

            $this->print_list_view($items, $columns, $formatters);
        }

        if (empty($items)) {
            echo '<div align="center">';
            $tmppage = new trackassignmentpage(array('action'=>'autocreate', 'id'=>$id));
            print_single_button(null, $tmppage->get_moodle_url()->params, get_string('track_autocreate_button', 'block_curr_admin'));
            echo '</div>';
        }

        $contexts = cmclasspage::get_contexts('block/curr_admin:associate');
        // find the classes that are part of a course that is part of a
        // curriculum that the track belongs to
        $sql = "SELECT cls.*
                  FROM {$CURMAN->db->prefix_table('crlm_track')} trk
                  JOIN {$CURMAN->db->prefix_table('crlm_curriculum')} cur ON cur.id = trk.curid
                  JOIN {$CURMAN->db->prefix_table('crlm_curriculum_course')} curcrs ON curcrs.curriculumid = cur.id
                  JOIN {$CURMAN->db->prefix_table('crlm_class')} cls ON cls.courseid = curcrs.courseid
                 WHERE trk.id = $id AND " . $contexts->sql_filter_for_context_level('cls.id', 'class');
        $classes = $CURMAN->db->get_records_sql($sql);
        if (empty($classes)) {
            $sql = "SELECT COUNT(*)
                      FROM {$CURMAN->db->prefix_table('crlm_track')} trk
                      JOIN {$CURMAN->db->prefix_table('crlm_curriculum')} cur ON cur.id = trk.curid
                      JOIN {$CURMAN->db->prefix_table('crlm_curriculum_course')} curcrs ON curcrs.curriculumid = cur.id
                      JOIN {$CURMAN->db->prefix_table('crlm_class')} cls ON cls.courseid = curcrs.courseid
                     WHERE trk.id = $id";
            $num_classes = $CURMAN->db->count_records_sql($sql);
            if (!empty($num_classes)) {
                // some classes exist, but don't have associate capability on
                // any of them
                echo '<div align="center"><br />';
                print_string('no_associate_caps_class', 'block_curr_admin');
                echo '</div>';
            } else {
                // no curricula at all
                echo '<div align="center"><br />';
                print_string('all_items_assigned', 'block_curr_admin');
                echo '</div>';
            }
        } else {
            $this->print_dropdown($classes, $items, 'trackid', 'clsid', 'add', 'idnumber');
        }
    }

    function create_table_object($items, $columns, $formatters) {
        return new trackassignment_page_table($items, $columns, $this, $formatters);
    }

    function action_autocreate() {
        $id = required_param('id', PARAM_INT);

        $track = new track($id);
        $track->track_auto_create();

        $tmppage = new trackassignmentpage(array('id' => $id));
        redirect($tmppage->get_url(), get_string('success_autocreate','block_curr_admin'));
    }

    function action_enrolall() {
        $id = required_param('id', PARAM_INT);
        $aid = required_param('association_id', PARAM_INT);

        $trackassignment = new trackassignmentclass($aid);
        $trackassignment->enrol_all_track_users_in_class();

        $tmppage = new trackassignmentpage(array('id' => $id));
        redirect($tmppage->get_url());
    }
}

class trackassignment_page_table extends association_page_table {
    function __construct(&$items, $columns, $page, $decorators=array()) {
        $id = required_param('id', PARAM_INT);
        $users = usertrack::get_users($id);
        $this->numusers = empty($users) ? 0 : count($users);

        parent::__construct($items, $columns, $page, $decorators);
    }

    function get_item_display_autoenrol($column, $item) {
        return $this->get_yesno_item_display($column, $item);
    }

    function get_item_display_enrolments($column, $item) {
        if (empty($item->enrolments)) {
            $item->enrolments = 0;
        }
        return "{$item->enrolments} / {$this->numusers}";
    }
}

class check_class_required extends table_decorator {
    var $flagged = false;

    function decorate($text, $column, $item) {
        if ($item->autoenrol && !$item->required) {
            // the class is set to autoenrolled, but can't be autoenrolled
            // because it is not required
            $this->flagged = true;
            return "<strike>$text</strike>*";
        } else {
            return $text;
        }
    }
}


class oldtrackassignmentpage extends page {
    var $data_class = 'track';
    var $form_class = 'ieform';
    var $pagename = 'tag';
    var $section = 'info';

    function trackassignmentpage ($action = 'main') {

    }

    function get_body() {
        global $CFG, $CURMAN;

        $action       = cm_get_param('action', '');
        $confirm      = cm_get_param('confirm', '');   //md5 confirmation hash
        $confirmuser  = cm_get_param('confirmuser', 0);
        $trackid      = cm_get_param('trackid', 0);
        $id           = cm_get_param('id', 0);
        $sort         = cm_get_param('sort', 'idnumber');
        $dir          = cm_get_param('dir', 'ASC');
        $page         = cm_get_param('page', 0);
        $perpage      = cm_get_param('perpage', 30);        // how many per page
        $namesearch   = trim(cm_get_param('search', ''));
        $alpha        = cm_get_param('alpha', '');

        if (!$track = new track($trackid)) {
            return ' (' . $trackid . ')';
        }

        switch ($action) {
            case 'add':
                return $this->get_add_form($trackid);
                break;

            case 'confirm':
                $tk = new trackassignmentclass($id);
                if (md5($tk->id) != $confirm) {
                    echo cm_error('Invalid confirmation code!');
                } else if (!$tk->delete()){
                    echo cm_error('Course "name: '.$tk->track->name.'" not deleted.');
                }
                break;

            case 'delete':
                return $this->get_delete_form($id);
                break;
            case 'edit':
                return $this->get_edit_form($id);
                break;
            case 'update':
                $id         = cm_get_param('id', 0);
                $autoenrol  = cm_get_param('autoenrol', 0);

                $trkassign = new trackassignmentclass($id);
                $trkassign->autoenrol = $autoenrol;
                $trkassign->data_update_record();
                break;
            case 'savenew':
                $classes    = cm_get_param('classes', '');
                $classes    = is_array($classes) ? $classes : array();
                $trackid    = cm_get_param('trackid', 0, PARAM_INT);

                if (!empty($classes) and !empty($trackid)) {
                    $param = array(
                        'trackid'       => $trackid,
                    );

                    $trackobj = new track($trackid);

                    foreach ($classes as $classid) {
                        $classobj = new cmclass($classid);
                        $param['classid'] = $classid;
                        $param['courseid'] = $classobj->courseid;
                        $param['autoenrol'] = 0;
                        $param['required'] = 0;

                        // Pull up the curricula assignment record(s)
                        $curcourse = curriculumcourse_get_list_by_curr($trackobj->curid);

                        // Traverse though curricula's courses until the the course the -
                        // selected classs is assigned to comes up
                        foreach ($curcourse as $recid => $curcourec) {

                            // Only interested in the course that the class is assigned to
                            if ($curcourec->courseid == $classobj->courseid) {
                                if ($curcourec->required) {
                                    $param['required'] = 1;
                                    // Only one class assigned to course to enable auto enrol
                                    if (1 == cmclass::count_course_assignments($curcourec->courseid)) {
                                        $param['autoenrol'] = 1;
                                    }
                                }
                            }

                        }
                        // Assign class to track now
                        $trkassignobj = new trackassignmentclass($param);
                        $trkassignobj->assign_class_to_track();
                    }
                }
                break;
        }

        $columns = array(
            'clsname'   => get_string('class_id_number', 'block_curr_admin'),
            'autoenrol' => get_string('auto_enrol', 'block_curr_admin')
        );

        foreach ($columns as $column => $cdesc) {
            if ($sort != $column) {
                $columnicon = "";
                $columndir = "ASC";
            } else {
                $columndir = $dir == "ASC" ? "DESC":"ASC";
                $columnicon = $dir == "ASC" ? "down":"up";
                $columnicon = " <img src=\"$CFG->pixpath/t/$columnicon.gif\" alt=\"\" />";

            }
            $$column        = "<a href=\"index.php?s=trkcls&amp;section=curr&amp;sort=$column&amp;".
                              "dir=$columndir&amp;search=".urlencode(stripslashes($namesearch)).
                              "&amp;alpha=$alpha&amp;trackid=$trackid\">".$cdesc."</a>$columnicon";
            $table->head[]  = $$column;
            $table->align[] = 'left';
            $table->wrap[]  = false;
        }

        $table->head[]  = '';
        $table->align[] = 'center';
        $table->wrap[]  = true;

        $trks   = track_assignment_get_listing($trackid, $sort, $dir, $page*$perpage, $perpage, $namesearch, $alpha);
        $numtrk = track_assignment_count_records($trackid, $namesearch, $alpha);

        $alphabet = explode(',', get_string('alphabet'));
        $strall = get_string('all');

    /// Nav bar information:
        $bc = '<div style="float:right;">'.$numtrk.' track(s) found.</div>'.'<span class="breadcrumb">'.
              get_string('trackasso_manage_crumb', 'block_curr_admin', $track->name).'</span>';
        echo cm_print_heading_block($bc, '', true);
        echo '<br />' . "\n";

        /// Bar of first initials

        echo "<p style=\"text-align:center\">";
        echo 'Name'." : ";
        if ($alpha) {
            echo " <a href=\"index.php?s=trkcls&amp;section=curr&amp;sort=name&amp;dir=ASC&amp;".
                 "perpage=$perpage&amp;trackid=$trackid\">$strall</a> ";
        } else {
            echo " <b>$strall</b> ";
        }

        foreach ($alphabet as $letter) {
            if ($letter == $alpha) {
                echo " <b>$letter</b> ";
            } else {
                echo " <a href=\"index.php?s=trkcls&amp;section=curr&amp;sort=idnumber&amp;dir=ASC&amp;".
                     "perpage=$perpage&amp;trackid=$trackid&amp;alpha=$letter\">$letter</a> ";
            }
        }
        echo "</p>";

        print_paging_bar($numtrk, $page, $perpage,
                        "index.php?s=trkm&amp;section=curr&amp;sort=$sort&amp;dir=$dir&amp;perpage=$perpage&amp;" .
                        "alpha=$alpha&amp;trackid=$trackid&amp;search=" . urlencode(stripslashes($namesearch)) .
                        "&amp;");
        if (!$trks) {
            $match = array();
            if ($namesearch !== '') {
               $match[] = s($namesearch);
            }
            if ($alpha) {
               $match[] = 'idnumber'.": $alpha"."___";
            }
            $matchstring = implode(", ", $match);
            echo get_string('no_matching_track_assign', 'block_curr_admin').$matchstring;

            $table = NULL;

        } else {
            $table->width = "95%";
            foreach ($trks as $trk) {
                $deletebutton = '<a href="index.php?s=trkcls&amp;section=curr&amp;action=delete&amp;'.
                                'id='.$trk->id.'">'.
                                '<img src="pix/delete.gif" alt="Delete" title="Delete" /></a>';
                $editbutton = '<a href="index.php?s=trkcls&amp;section=curr&amp;action=edit&amp;id='.$trk->id.'">'.
                              '<img src="pix/edit.gif" alt="Edit" title="Edit" /></a>';
                /*$tagbutton    = '<a href="index.php?s=tagins&amp;section=curr&amp;t=cur&amp;i='.$trk->id.'">'.
                                '<img src="pix/tag.gif" alt="Tags" title="Tags" /></a>';
                $clusterbutton = '<a href="index.php?s=clutrk&amp;section=curr&amp;mode=trk&amp;' .
                              'track=' . $trk->id . '"><img src="pix/cluster.gif" alt="Clusters" '.
                              'title="Clusters" /></a>';*/

                $newarr = array();
               foreach ($columns as $column => $cdesc) {
                   if ($column == 'clsname') {
                        $newarr[] = '<a href="index.php?s=cls&section=curr&action=edit&id='.
                                    $trk->classid.'">' . $trk->$column . '</a>';
                    } else {
                        $newarr[] = $trk->$column;
                    }
                }
                $newarr[] = $editbutton . ' ' . $deletebutton;
                $table->data[] = $newarr;
            }
        }

        echo "<table class=\"searchbox\" style=\"margin-left:auto;margin-right:auto\" cellpadding=\"10\"><tr><td>";
        echo "<form action=\"index.php\" method=\"get\"><fieldset class=\"invisiblefieldset\">";
        echo '<input type="hidden" name="s" value="trkcls" />';
        echo '<input type="hidden" name="section" value="curr" />';
        echo '<input type="hidden" name="sort" value="' . $sort . '" />';
        echo '<input type="hidden" name="dir" value="' . $dir . '" />';
        echo '<input type="hidden" name="perpage" value="' . $perpage . '" />';
        echo '<input type="hidden" name="trackid" value="' . $trackid . '" />';
        echo "<input type=\"text\" name=\"search\" value=\"".s($namesearch, true)."\" size=\"40\" />";
        echo "<input type=\"submit\" value=\"" . get_string('search', 'block_curr_admin') . "\" />";
        if ($namesearch) {
            echo "<input type=\"button\" onclick=\"document.location='index.php?s=trkcls&amp;" .
                 "section=curr&amp;sort=$sort&amp;dir=$dir&amp;perpage=$perpage&amp;alpha=$alpha&amp;trackid=$trackid';\" " .
                 "value=\"" . get_string('show_all_curricula', 'block_curr_admin') . "\" />";
        }
        echo "</fieldset></form>";
        echo "</td></tr></table>";

        if (!empty($table)) {
        print_heading('<a href="index.php?s=trkcls&amp;section=curr&amp;action=add&amp;trackid='.$trackid.'">'.
                      get_string('trackasso_add_asso', 'block_curr_admin', $track->name).'</a>');
            print_table($table);
            print_paging_bar($numtrk, $page, $perpage,
                             "index.php?s=trkcls&amp;section=curr&amp;sort=$sort&amp;dir=$dir&amp;perpage=$perpage".
                             "&amp;alpha=$alpha&amp;trackid=$trackid&amp;search=".urlencode(stripslashes($namesearch))."&amp;");
        }

        print_heading('<a href="index.php?s=trkcls&amp;section=curr&amp;action=add&amp;trackid='.$trackid.'">'.
                      get_string('trackasso_add_asso', 'block_curr_admin', $track->name).'</a>');
    }

    /**
     * Returns the delete track class association form.
     *
     * @param int $id The id of the track class association record.
     * @return string HTML for the form.
     *
     */
    function get_delete_form($id) {

        $trk = new trackassignmentclass($id);

        $url = 'index.php';

        $a = new stdClass();
        $a->name = $trk->track->name;
        $a->idnumber = $trk->course->idnumber;
        $message = get_string('confirm_delete_track_assignment', 'block_curr_admin', $a);

        $optionsyes = array('s' => 'trkcls', 'section' => 'curr', 'action' => 'confirm',
                            'id' => $trk->id, 'trackid' => $trk->trackid, 'confirm' => md5($trk->id));
        $optionsno = array('s' => 'trkcls', 'section' => 'curr');

        $bc = '<span class="breadcrumb"><a href="index.php?s=trkcls&amp;search=' . urlencode($trk->course->name) .
              '&amp;trackid='.$trk->trackid.'">'.
              get_string('trackasso_genericmanage_crumb', 'block_curr_admin').'</a> ' .
              '&raquo; '.get_string('trackasso_delete', 'block_curr_admin', $trk->track->name).'"</span>';

        echo cm_print_heading_block($bc, '', true);
        echo '<br />' . "\n";
        echo cm_delete_form($url, $message, $optionsyes, $optionsno);
    }

    /**
     * Returns the add class to track form.
     *
     * @return string HTML for the form.
     */
    function get_add_form($trackid) {
        $output = '';

        $trkassign = new trackassignmentclass(array('trackid' => $trackid));

        $bc = '<span class="breadcrumb"><a href="index.php?s=trkcls&amp;section=curr">Manage track association</a> ' .
              '&raquo; Adding a new track</span>';

        $output .= cm_print_heading_block($bc, '', true);
        $output .= '<br clear="all" />' . "\n";
        $output .= '<form method="post" action="index.php?s=trkcls&amp;section=curr&amp;trackid='.$trackid.'" >'."\n";
        $output .= '<input type="hidden" name="action" value="savenew" />'."\n";
        $output .= '<input type="hidden" name="trackid" value="'.$trackid.'" />'."\n";
        $output .= $trkassign->edit_form_html();
        $output .= '<input type="submit" value="' . get_string('save', 'block_curr_admin') . '">'."\n";
        $output .= '</form>'."\n";

        return $output;
    }

    function get_edit_form($trkassignid) {
        $output = '';

        $trkassign = new trackassignmentclass($trkassignid);

        $bc = '<span class="breadcrumb"><a href="index.php?s=trkcls&amp;section=curr'.
              '&amp;trackid='.$trkassign->trackid.'">'.
              get_string('trackasso_genericmanage_crumb', 'block_curr_admin').'</a> ' .
              '&raquo; '.get_string('trackasso_genericedit_crumb', 'block_curr_admin').'</span>';

        $output .= cm_print_heading_block($bc, '', true);
        $output .= '<br clear="all" />' . "\n";
        $output .= '<form method="post" action="index.php?s=trkcls&amp;section=curr&amp;trackid='.$trkassign->trackid.'" >'."\n";
        $output .= '<input type="hidden" name="action" value="update" />'."\n";
        $output .= '<input type="hidden" name="id" value="'.$trkassign->id.'" />'."\n";
        $output .= $trkassign->edit_assocation_form_html();
        $output .= '<input type="submit" value="' . get_string('save', 'block_curr_admin') . '">'."\n";
        $output .= '</form>'."\n";

        return $output;
    }
}
?>
