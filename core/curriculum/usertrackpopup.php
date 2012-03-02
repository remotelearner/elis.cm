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

require_once (dirname(__FILE__) . '/config.php');
require_once CURMAN_DIRLOCATION . '/lib/user.class.php';
require_once CURMAN_DIRLOCATION . '/lib/track.class.php';
require_once CURMAN_DIRLOCATION . '/lib/usertrack.class.php';
require_once CURMAN_DIRLOCATION . '/usertrackpage.class.php';

$site = get_site();

$trackid = required_param('track', PARAM_INT);
$track = new track($trackid);
$userid = optional_param('userid', 0,  PARAM_INT);
// get searching/sorting parameters
$sort = optional_param('sort','lastname',PARAM_ALPHA);
$alpha = optional_param('alpha','',PARAM_ALPHA);
$namesearch = optional_param('namesearch','',PARAM_MULTILANG);
$dir = optional_param('dir','ASC',PARAM_ALPHA);
if ($dir != 'ASC' && $dir != 'DESC') {
    $dir = 'ASC';
}
$page = optional_param('page',0,PARAM_INT);
$perpage = optional_param('perpage',30,PARAM_INT);

$context = get_context_instance(context_level_base::get_custom_context_level('track', 'block_curr_admin'), $trackid);

//todo: integrate this better with user-track page?
//this checks permissions at the track level
if(!trackpage::can_enrol_into_track($trackid)) {
    //standard failure message
    require_capability('block/curr_admin:track:enrol', $context);
}

// add user to track
if ($userid) {
    //todo: integrate this better with user-track page?
    //this checks permissions at the user-track association level
    if(!usertrack::can_manage_assoc($userid, $trackid)) {
        //standard failure message
        require_capability('block/curr_admin:track:enrol', $context);
    }
    
    usertrack::enrol($userid, $trackid);
    // reload the main page with the new assignments
    $target = new trackuserpage(array('id' => $trackid))
?>
<script type="text/javascript">
//<![CDATA[
window.opener.location = "<?php echo htmlspecialchars_decode($target->get_url()); ?>";
//]]>
</script>
<?php
}

// find all users not enrolled in the track
$FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');
$LIKE     = $CURMAN->db->sql_compare();
$select = 'SELECT usr.*, ' . $FULLNAME . ' AS name, usr.lastname AS lastname ';
$sql = 'FROM ' . $CURMAN->db->prefix_table(USRTABLE) . ' usr '
    . 'LEFT OUTER JOIN ' . $CURMAN->db->prefix_table(USRTRKTABLE) . ' ut ON ut.userid = usr.id AND ut.trackid = ' . $trackid . ' '
    . 'WHERE ut.userid IS NULL ';

if (empty($CURMAN->config->legacy_show_inactive_users)) {
    $sql .= 'AND usr.inactive = 0 ';
}

if ($alpha) {
    $sql .= 'AND '.$FULLNAME.' '.$LIKE.' \''.$alpha.'%\' ';
}
if ($namesearch) {
    $sql .= 'AND '.$FULLNAME.' '.$LIKE.' \'%'.$namesearch.'%\' ';
}

if(!trackpage::_has_capability('block/curr_admin:track:enrol', $trackid)) {
    //perform SQL filtering for the more "conditional" capability

    //get the context for the "indirect" capability
    $context = cm_context_set::for_user_with_capability('cluster', 'block/curr_admin:track:enrol_cluster_user', $USER->id);
    
    //get the clusters and check the context against them
    $clusters = clustertrack::get_clusters($trackid);
    $allowed_clusters = $context->get_allowed_instances($clusters, 'cluster', 'clusterid');
    
    if(empty($allowed_clusters)) {
        $sql .= 'AND 0=1';
    } else {
        $cluster_filter = implode(',', $allowed_clusters);
        $sql .= "AND usr.id IN (
                   SELECT userid FROM " . $CURMAN->db->prefix_table(CLSTUSERTABLE) . "
                   WHERE clusterid IN ({$cluster_filter}))";
    }
}

// get the total number of matching users
$count = $CURMAN->db->count_records_sql('SELECT COUNT(usr.id) '.$sql);
if ($sort) {
    $sql .= 'ORDER BY '.$sort.' '.$dir.' ';
}
if ($count < ($page * $perpage)) {
    $page = 0;
}
if ($perpage) {
    if ($CURMAN->db->_dbconnection->databaseType == 'postgres7') {
        $sql .= 'LIMIT ' . $perpage . ' OFFSET ' . $page * $perpage . ' ';
    } else {
        $sql .= 'LIMIT ' . $page * $perpage . ', ' . $perpage . ' ';
    }

}

$users = $CURMAN->db->get_records_sql($select.$sql);

print_header($site->shortname . ': Assign users to track "' . $track->name . '"');

// create a link based on the given parameters and the current page
// parameters
function make_link($psort=null, $pdir=null, $palpha=null, $ppage=null, $pnamesearch=null) {
    global $trackid, $sort, $alpha, $link, $namesearch, $dir, $page;
    $link = 'usertrackpopup.php?track='.$trackid;
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

/// Bar of first initials
$alphabet = explode(',', get_string('alphabet'));
$strall   = get_string('all');
echo '<p style="text-align:center">';
echo 'Name : ';
if ($alpha) {
    echo ' <a href="'.make_link(null,null,'').'">'.$strall.'</a> ';
} else {
    echo ' <b>'.$strall.'</b> ';
}
foreach ($alphabet as $letter) {
    if ($letter == $alpha) {
        echo ' <b>'.$letter.'</b> ';
    } else {
        echo ' <a href="'.make_link(null,null,$letter).'">'.$letter.'</a> ';
    }
}
echo "</p>";

// note: use moodle_url so that it will replace the current page parameter,
// if present
print_paging_bar($count, $page, $perpage, new moodle_url(make_link()));

?>
<center><form action="usertrackpopup.php" method="get">
<input type="hidden" name="track" value="<?php echo $trackid; ?>" />
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
    echo '<div>' . get_string('click_user_enrol_track', 'block_curr_admin') . '</div>';
    $table = null;
    $columns = array(
        'idnumber' => get_string('track_idnumber', 'block_curr_admin'),
        'name'     => get_string('name', 'block_curr_admin'),
        'email'    => get_string('email', 'block_curr_admin'),
        );

    foreach ($columns as $column => $cdesc) {
        if ($column == 'name') {
            $column = 'lastname';
        }
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
<div style="text-align: right"><a href="javascript:window.close()">Close window</a></div>
<?php

print_footer('empty');
?>
