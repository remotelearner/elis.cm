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

require_once (CURMAN_DIRLOCATION . '/lib/lib.php');
require_once (CURMAN_DIRLOCATION . '/lib/newpage.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/user.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/track.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/usertrack.class.php');
require_once (CURMAN_DIRLOCATION . '/usermanagementpage.class.php');
require_once (CURMAN_DIRLOCATION . '/trackpage.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/associationpage.class.php');

class usertrackbasepage extends associationpage {

    var $data_class = 'usertrack';

    /**
     * @todo Refactor this once we have a common save() method for datarecord subclasses.
     */
    function action_savenew() {
        $trackid = $this->required_param('trackid', PARAM_INT);
        $userid = $this->required_param('userid', PARAM_INT);

        usertrack::enrol($userid, $trackid);

        return $this->action_default();
    }
}

class usertrackpage extends usertrackbasepage {
    var $pagename = 'usrtrk';
    var $tab_page = 'usermanagementpage';

    var $section = 'users';

    function can_do_default() {
        $id = $this->required_param('id', PARAM_INT);
        return usermanagementpage::_has_capability('block/curr_admin:user:view', $id);
    }

    function can_do_add() {
        $userid = $this->required_param('userid', PARAM_INT);
        $trackid = $this->required_param('trackid', PARAM_INT);
        return usertrack::can_manage_assoc($userid, $trackid);
    }

    function can_do_savenew() {
        return $this->can_do_add();
    }

    function can_do_delete() {
        $aid = $this->required_param('association_id');
        $usrtrk = new usertrack($aid);
        return usertrack::can_manage_assoc($usrtrk->userid, $usrtrk->trackid);
    }

    function can_do_confirm() {
        return $this->can_do_delete();
    }

    function action_default() {
        global $USER, $CURMAN;

        $id = required_param('id', PARAM_INT);
        $contexts = clone(trackpage::get_contexts('block/curr_admin:track:enrol'));

        //look up student's cluster assignments with necessary capability
        $cluster_contexts = cm_context_set::for_user_with_capability('cluster', 'block/curr_admin:track:enrol_cluster_user', $USER->id);

        //calculate our filter condition based on cluster accessibility
        $cluster_filter = $cluster_contexts->sql_filter_for_context_level('clst.id', 'cluster');

        //query for getting tracks based on clusters
        $sql = "SELECT trk.id
                FROM {$CURMAN->db->prefix_table(CLSTTABLE)} clst
                JOIN {$CURMAN->db->prefix_table(CLSTTRKTABLE)} clsttrk
                ON clst.id = clsttrk.clusterid
                JOIN {$CURMAN->db->prefix_table(TRACKTABLE)} trk
                ON clsttrk.trackid = trk.id
                WHERE {$cluster_filter}";

        //assign the appropriate track ids
        $recordset = get_recordset_sql($sql);
        if($recordset && $recordset->RecordCount() > 0) {
            if(!isset($contexts->contexts['track'])) {
                $contexts->contexts['track'] = array();
            }

            $new_tracks = array();
            while($record = rs_fetch_next_record($recordset)) {
                $new_tracks[] = $record->id;
            }

            $contexts->contexts['track'] = array_merge($contexts->contexts['track'], $new_tracks);
        }

        $columns = array(
            'idnumber'    => get_string('track_idnumber', 'block_curr_admin'),
            'name'        => get_string('track_name', 'block_curr_admin'),
            'description' => get_string('track_description', 'block_curr_admin'),
            'numclasses'   => get_string('num_classes', 'block_curr_admin'),
            'manage'      => '',
        );

        $items = usertrack::get_tracks($id);

        $formatters = $this->create_link_formatters(array('idnumber', 'name'), 'trackpage', 'trackid');

        $this->print_list_view($items, $columns, $formatters);

        //get the listing specifically for this user
        $this->print_dropdown(track_get_listing('name', 'ASC', 0, 0, '', '', 0, 0, $contexts, $id), $items, 'userid', 'trackid', 'savenew', 'idnumber');
    }
}

class trackuserpage extends usertrackbasepage {
    var $pagename = 'trkusr';
    var $tab_page = 'trackpage';

    var $section = 'curr';

    function can_do_default() {
        $id = $this->required_param('id', PARAM_INT);
        return trackpage::_has_capability('block/curr_admin:track:view', $id);
    }

    function can_do_add() {
        //note: actual permission checking happens in usertrackpopup.php
        $id = $this->required_param('id', PARAM_INT);
        return trackpage::can_enrol_into_track($id);
    }

    function can_do_savenew() {
        return $this->can_do_add();
    }

    function can_do_delete() {
        global $USER;
        
        $association_id = 0;
        if(!empty($this->params['association_id'])) {
            $association_id = $this->params['association_id'];
        } else {
            $association_id = $this->optional_param('association_id', '', PARAM_INT);
        }        
        $usrtrk = new usertrack($association_id);
        
        return usertrack::can_manage_assoc($usrtrk->userid, $usrtrk->trackid);
    }

    function can_do_confirm() {
        return $this->can_do_add();
    }

    function action_default() {
        $id = $this->required_param('id', PARAM_INT);

        $columns = array(
                'idnumber'    => get_string('student_idnumber', 'block_curr_admin'),
                'name'        => get_string('tag_name', 'block_curr_admin'),
                'email'       => get_string('email', 'block_curr_admin'),
                'manage'      => '',
        );

        $items = usertrack::get_users($id);

        $formatters = $this->create_link_formatters(array('idnumber', 'name'), 'usermanagementpage', 'userid');

        $this->print_list_view($items, $columns, $formatters);

        if ($this->can_do_add()) {
            $this->print_assign_link();
        }
    }

    function print_assign_link() {
        $id = $this->required_param('id', PARAM_INT);

        echo <<<EOD
<div align="center"><br />
<a href="javascript:null(window.open('usertrackpopup.php?track=$id','','width=500, height=500, resizable, scrollbars'));">
Assign users
</a>
</div>
EOD;
    }
}
?>
