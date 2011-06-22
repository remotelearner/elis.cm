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

require_once (dirname(__FILE__) . '/../../config.php');
require_once CURMAN_DIRLOCATION . '/lib/user.class.php';
require_once CURMAN_DIRLOCATION . '/lib/cluster.class.php';
require_once CURMAN_DIRLOCATION . '/cluster/manual/lib.php';
require_once CURMAN_DIRLOCATION . '/cluster/manual/assignpage.class.php';

require_js($CFG->wwwroot . '/curriculum/js/util.js');

$site = get_site();
$access = cm_determine_access($USER->id);
if ($access != 'admin') {
    print_header($site->shortname);
    print_error('nopermissions', 'error', '', 'assign users');
}

$clusterid = required_param('clusterid', PARAM_INT);
$cluster = new cluster($clusterid);
$userid = optional_param('userid', 0,  PARAM_INT);

// get searching/sorting parameters
$sort = optional_param('sort','name',PARAM_ALPHA);
$alpha = optional_param('alpha','',PARAM_ALPHA);
$namesearch = optional_param('namesearch','',PARAM_MULTILANG);
$dir = optional_param('dir','ASC',PARAM_ALPHA);
if ($dir != 'ASC' && $dir != 'DESC') {
    $dir = 'ASC';
}
$page = optional_param('page',0,PARAM_INT);
$perpage = optional_param('perpage',30,PARAM_INT);

// add user to cluster
if ($userid) {
    require_once ($CFG->dirroot . '/curriculum/cluster/manual/assignpopup_form.php');
    $assignform = new assignpopup_form();
    
    if ($assignform->is_cancelled()) {
        // do something
    } elseif (($data = $assignform->get_data())) {
        cluster_manual_assign_user($data->clusterid, $data->userid, !empty($data->autoenrol), !empty($data->leader));
        // reload the main page with the new assignments
        $target = new clusteruserpage(array('id' => $clusterid));
?>
<script type="text/javascript">
//<![CDATA[
window.opener.location = "<?php echo htmlspecialchars_decode($target->get_url()); ?>";
//]]>
</script>
<?php
    } else {
        $a = new object();
        $a->site = $site->shortname;
        $a->name = $cluster->name;
        print_header(get_string('assign_user_cluster', 'block_curr_admin', $a));

        $user = new user($userid);

        $a = new object();
        $a->fullname = cm_fullname($user);
        $a->name = $cluster->name;
        $bc = '<span class="breadcrumb">'.get_string('cluster_manual_options', 'block_curr_admin', $a).'</span>';
        echo cm_print_heading_block($bc, '', true);

        $murl = new moodle_url(make_link());
        $data = $murl->params;
        $data['userid'] = $userid;
        $data['autoenrol'] = true;
        $assignform->set_data($data);

        $assignform->display();

        print_footer(get_string('empty', 'block_curr_admin'));
        exit;
    }
}

// find all users not assigned to the cluster
$FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');

$LIKE     = $CURMAN->db->sql_compare();
$select = 'SELECT usr.*, ' . $FULLNAME . ' AS name ';
$sql = 'FROM ' . $CURMAN->db->prefix_table(USRTABLE) . ' usr '
    . 'LEFT OUTER JOIN ' . $CURMAN->db->prefix_table(CLSTASSTABLE) . ' ca ON ca.userid = usr.id AND ca.clusterid = ' . $clusterid . ' AND ca.plugin = \'manual\' '
    . 'WHERE ca.userid IS NULL ';
if ($alpha) {
    $sql .= 'AND '.$FULLNAME.' '.$LIKE.' \''.$alpha.'%\' ';
}
if ($namesearch) {
    $sql .= 'AND '.$FULLNAME.' '.$LIKE.' \'%'.$namesearch.'%\' ';
}
// get the total number of matching users
$count = $CURMAN->db->count_records_sql('SELECT COUNT(usr.id) '.$sql);
if ($sort) {
    $sql .= 'ORDER BY '.$sort.' '.$dir.' ';
}
if ($perpage) {
    if ($CURMAN->db->_dbconnection->databaseType == 'postgres7') {
        $limit = 'LIMIT ' . $perpage . ' OFFSET ' . $page * $perpage. ' ';
    } else {
        $limit = 'LIMIT ' . $page * $perpage . ', ' . $perpage . ' ';
    }
}

$users = $CURMAN->db->get_records_sql($select.$sql.$limit);

$a = new object();
$a->site = $site->shortname;
$a->name = $cluster->name;
print_header(get_string('assign_user_cluster', 'block_curr_admin', $a));

// create a link based on the given parameters and the current page
// parameters
function make_link($psort=null, $pdir=null, $palpha=null, $ppage=null, $pnamesearch=null) {
    global $clusterid, $sort, $alpha, $link, $namesearch, $dir, $page;
    $link = 'assignpopup.php?clusterid='.$clusterid;
    
    if ($psort!==null) {
        $link .= '&amp;sort='.$psort;
    } else if ($sort != 'name') {
        $link .= '&amp;sort='.$sort;
    }
    if ($palpha!==null) {
        $link .= '&amp;alpha='.$palpha;
    } else if ($alpha) {
        $link .= '&amp;alpha='.$alpha;
    }
    if ($pnamesearch===null && $namesearch) {
        $link .= '&amp;namesearch='.rawurlencode(stripslashes($namesearch));
    }
    if ($pdir!==null) {
        $link .= '&amp;dir='.$pdir;
    } else if ($dir != 'ASC') {
        $link .= '&amp;dir='.$dir;
    }
    if ($ppage!==null) {
        $link .= '&amp;page='.$ppage;
    } else if ($page) {
        $link .= '&amp;page='.$page;
    }
    return $link;
}

/**
 * Returns a string that calls events
 * 
 * @param array eventtype A array that contains the event type and
 * the function to be called by the event Usage example: 
 * array('type' => 'onMouseOver',
 *       'function' => 'mouse_over_handler(Document)');
 */
function make_event($eventarray = array()) {
  $funccall = '';
  
  foreach ($eventarray as $key => $data) {
    switch($key) {
      case 'type':
        $funccall = $data . '=' . $funccall;
        break;
      case 'function':
        $funccall = $funccall . '"'. $data . '"';
        break;
    }
  }
  
  return $funccall;
}

/// Bar of first initials
$alphabet = explode(',', get_string('alphabet'));
$strall   = get_string('all');
echo '<p style="text-align:center">';
echo get_string('student_name', 'block_curr_admin');

// Setup event call
$event = array('type' => 'onClick', 'function' => 'changeNamesearch(this)');

if ($alpha) {
    echo ' <a href="'.make_link(null,null,'').'" '. make_event($event) .'>'.$strall.'</a> ';
} else {
    echo ' <b>'.$strall.'</b> ';
}
foreach ($alphabet as $letter) {
    if ($letter == $alpha) {
        echo ' <b>'.$letter.'</b> ';
    } else {
        echo ' <a href="'.make_link(null,null,$letter).'" '. make_event($event) . '>'.$letter.'</a> ';
    }
}
echo "</p>";

// note: use moodle_url so that it will replace the current page parameter,
// if present
print_paging_bar($count, $page, $perpage, new moodle_url(make_link()));

?>
<center><form action="assignpopup.php" method="get">
<input type="hidden" name="clusterid" value="<?php echo $clusterid; ?>" />
<?php
if ($sort != 'name') {
    echo '<input type="hidden" name="sort" value="'.$sort.'" />';
}
if ($alpha) {
    echo '<input type="hidden" name="alpha" value="'.$alpha.'" />';
}
if ($dir != 'ASC') {
    echo '<input type="hidden" name="dir" value="'.$dir.'" />';
}
?>
<input type="text" name="namesearch" value="<?php echo s($namesearch,true); ?>" size="20" />
<input type="submit" value="Search" /></form></center>
<?php

// show list of available users
if (empty($users)) {
    echo '<div>' . get_string('no_matching_users', 'block_curr_admin') . '</div>';
} else {
    echo '<div>' . get_string('click_user', 'block_curr_admin') . '</div>';
    $table = null;
    $columns = array(
        'idnumber'    => 'ID Number',
        'name'        => 'Name',
        'email'       => 'E-mail Address',
        );

    foreach ($columns as $column => $cdesc) {
        if ($sort != $column) {
            $columnicon = "";
            $columndir = "ASC";
        } else {
            $columndir  = $dir == "ASC" ? 'DESC':'ASC';
            $columnicon = $dir == "ASC" ? 'down':'up';
            $columnicon = ' <img src="'.$CFG->pixpath.'/t/'.$columnicon.'.gif" alt="" />';

        }
        $$column        = '<a href="'.make_link($column,$columndir).'">'.$cdesc."</a>$columnicon";
        $table->head[]  = $$column;
        $table->align[] = 'left';
    }

    $table->width = "95%";
    foreach ($users as $user) {
        $newarr = array();
        foreach ($columns as $column => $cdesc) {
            $newarr[] = '<a href="'.make_link().'&amp;userid='.$user->id.'">'.$user->$column.'</a>';
        }
        $table->data[] = $newarr;
    }

    print_table($table);
}

?>
<div style="text-align: right"><a href="javascript:window.close()"><?php echo get_string('close_window', 'block_curr_admin'); ?></a></div>
<?php

print_footer(get_string('empty', 'block_curr_admin'));
?>