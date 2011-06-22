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
require_once (CURMAN_DIRLOCATION . '/lib/clustercurriculum.class.php'); // contains clustertrack as well
require_once (CURMAN_DIRLOCATION . '/clusterpage.class.php');
require_once (CURMAN_DIRLOCATION . '/trackpage.class.php');

class clustertrackbasepage extends associationpage {

    var $data_class = 'clustertrack';
    var $form_class = 'clustertrackform';
    var $edit_form_class = 'clustertrackeditform';

    function __construct($params=false) {
        $this->tabs = array(
            array('tab_id' => 'edit',
                  'page' => get_class($this),
                  'params' => array('action' => 'edit'),
                  'name' => get_string('edit', 'block_curr_admin'),
                  'showtab' => true,
                  'showbutton' => true,
                  'image' => 'edit.gif'),
            array('tab_id' => 'delete',
                  'page' => get_class($this),
                  'params' => array('action' => 'delete'),
                  'name' => get_string('delete_label', 'block_curr_admin'),
                  'showbutton' => true,
                  'image' => 'delete.gif'),
        );

        parent::__construct($params);
    }

    function can_do_savenew() {
        // the user must have 'block/curr_admin:associate' permissions on both ends
        $clusterid = $this->required_param('clusterid', PARAM_INT);
        $trackid = $this->required_param('trackid', PARAM_INT);

        return clusterpage::_has_capability('block/curr_admin:associate', $clusterid)
            && trackpage::_has_capability('block/curr_admin:associate', $trackid);
    }

    function action_savenew() {
        $id = $this->required_param('id', PARAM_INT);
        $autounenrol = $this->optional_param('autounenrol', 1, PARAM_INT);
        $clusterid = $this->required_param('clusterid', PARAM_INT);
        $trackid = $this->required_param('trackid', PARAM_INT);

        require_once(CURMAN_DIRLOCATION . '/form/' . $this->form_class . '.class.php');
        require_once(CURMAN_DIRLOCATION . '/plugins/cluster_classification/clusterclassification.class.php');

        $target = $this->get_new_page(array('action'       => 'savenew',
                                            'id'           => $id,
                                            'clusterid'    => $clusterid,
                                            'trackid'      => $trackid));

        $form = new $this->form_class($target->get_moodle_url(), array('id'        => $id,
                                                                       'clusterid' => $clusterid,
                                                                       'trackid'   => $trackid));

        $form->set_data(array('clusterid' => $clusterid,
                              'trackid' => $trackid));

        if($data = $form->get_data()) {
            if(!isset($data->cancel)) {
                clustertrack::associate($clusterid, $trackid, $autounenrol, $data->autoenrol);
            }
            $this->action_default();
        } else {
            $cluster_classification = clusterclassification::get_for_cluster($clusterid);
            if (!empty($cluster_classification->param_autoenrol_tracks)) {
                $form->set_data(array('autoenrol' => 1));
            } else {
                $form->set_data(array('autoenrol' => 0));
            }

            $form->display();
        }

    }

    function can_do_edit() {
        // the user must have 'block/curr_admin:associate' permissions on both
        // ends
        $association_id = $this->required_param('association_id', PARAM_INT);
        $record = new clustertrack($association_id);
        $clusterid = $record->clusterid;
        $trackid = $record->trackid;

        return clusterpage::_has_capability('block/curr_admin:associate', $clusterid)
            && trackpage::_has_capability('block/curr_admin:associate', $trackid);
    }

    function can_do_delete() {
        return $this->can_do_edit();
    }

    function action_edit() {
        $id = $this->required_param('id', PARAM_INT);
        $association_id = $this->required_param('association_id', PARAM_INT);

        require_once(CURMAN_DIRLOCATION . '/form/' . $this->edit_form_class . '.class.php');

        $target = $this->get_new_page(array('action'         => 'edit',
                                            'id'             => $id,
                                            'association_id' => $association_id));

        $form = new $this->edit_form_class($target->get_moodle_url(), array('id' => $id,
                                                                            'association_id' => $association_id));

        $form->set_data(array('id' => $id,
                              'association_id' => $association_id));

        if($data = $form->get_data()) {
            if(!isset($data->cancel)) {
                clustertrack::update_autoenrol($association_id, $data->autoenrol);
            }
            $this->action_default();
        } else {
            $form->display();
        }
    }

    function create_table_object($items, $columns, $formatters) {
        return new clustertrack_page_table($items, $columns, $this, $formatters);
    }
}

class clustertrackpage extends clustertrackbasepage {
    var $pagename = 'clsttrk';
    var $tab_page = 'clusterpage';

    var $section = 'users';

    function can_do_default() {
        $id = $this->required_param('id', PARAM_INT);
        return clusterpage::_has_capability('block/curr_admin:associate', $id);
    }

    function action_default() {
        $id = $this->required_param('id', PARAM_INT);

        $columns = array(
            'idnumber'    => get_string('track_idnumber','block_curr_admin'),
            'name'        => get_string('track_name','block_curr_admin'),
            'description' => get_string('track_description','block_curr_admin'),
            'autoenrol'   => get_string('auto_enrol', 'block_curr_admin'),
             //buttons triggers the use of "tabs" as buttons for editing and deleting
            'buttons'     => '',
        );

        $items = clustertrack::get_tracks($id);

        $formatters = $this->create_link_formatters(array('idnumber', 'name'), 'trackpage', 'trackid');

        $this->print_list_view($items, $columns, $formatters);

        // find the tracks that the user can associate with this cluster
        $contexts = trackpage::get_contexts('block/curr_admin:associate');
        $tracks = track_get_listing('name', 'ASC', 0, 0, '', '', 0, 0, $contexts);
        if (empty($tracks)) {
            $num_tracks = track_count_records();
            if (!empty($num_tracks)) {
                // some tracks exist, but don't have associate capability on
                // any of them
                echo '<div align="center"><br />';
                print_string('no_associate_caps_track', 'block_curr_admin');
                echo '</div>';
            } else {
                // no tracks at all
                echo '<div align="center"><br />';
                print_string('all_items_assigned', 'block_curr_admin');
                echo '</div>';
            }
        } else {
            $this->print_dropdown($tracks, $items, 'clusterid', 'trackid');
        }
    }
}

class trackclusterpage extends clustertrackbasepage {
    var $pagename = 'trkclst';
    var $tab_page = 'trackpage';

    var $section = 'curr';

    function can_do_default() {
        $id = $this->required_param('id', PARAM_INT);
        return trackpage::_has_capability('block/curr_admin:associate', $id);
    }

    function action_default() {
        $id = $this->required_param('id', PARAM_INT);

        $parent_clusterid = $this->optional_param('parent_clusterid', 0, PARAM_INT);
        $sort = $this->optional_param('sort', 'name', PARAM_CLEAN);
        $dir = $this->optional_param('dir', 'ASC', PARAM_CLEAN);

        $columns = array(
            'name'        => get_string('cluster_name','block_curr_admin'),
            'display'     => get_string('description','block_curr_admin'),
            'autoenrol'   => get_string('auto_enrol', 'block_curr_admin'),
             //buttons triggers the use of "tabs" as buttons for editing and deleting
            'buttons'     => '',
        );

        $items = clustertrack::get_clusters($id, $parent_clusterid, $sort, $dir);

        $formatters = $this->create_link_formatters(array('name'), 'clusterpage', 'clusterid');

        $this->print_list_view($items, $columns, $formatters);

        // find the tracks that the user can associate with this cluster
        $contexts = clusterpage::get_contexts('block/curr_admin:associate');
        $clusters = cluster_get_listing('name', 'ASC', 0, 0, '', '', array('contexts' =>$contexts));
        if (empty($clusters)) {
            $num_clusters = cluster_count_records();
            if (!empty($num_clusters)) {
                // some clusters exist, but don't have associate capability on
                // any of them
                echo '<div align="center"><br />';
                print_string('no_associate_caps_clusters', 'block_curr_admin');
                echo '</div>';
            } else {
                // no clusters at all
                echo '<div align="center"><br />';
                print_string('all_items_assigned', 'block_curr_admin');
                echo '</div>';
            }
        } else {
            $this->print_dropdown($clusters, $items, 'trackid', 'clusterid');
        }
    }

    /**
     * Creates a new table object with specified $items and $columns.
     * @param array $items
     * @param array $columns
     * @param array $formatters
     */
    function create_table_object($items, $columns, $formatters) {

        $parent_clusterid = $this->optional_param('parent_clusterid', 0, PARAM_INT);

        $extra_params = array();
        if(!empty($parent_clusterid)) {
            $extra_params['parent_clusterid'] = $parent_clusterid;
        }

        $page_object = $this->get_new_page($extra_params);

        return new clustertrack_page_table($items, $columns, $page_object, $formatters);
    }
}

/**
 * This class is set up for displaying a cluster-track association, and performs
 * special formatting on the yes/no autoenrol flag
 */
class clustertrack_page_table extends association_page_table {

    /**
     * Converts a 0 or 1 to a Yes or No display string for the autoenrol field
     *
     * @param   string  $column  The field name we are checking
     * @param   object  $item    The object containing the field in question
     *
     * @return  string           The converted text - Yes or No
     *
     */
    function get_item_display_autoenrol($column, $item) {
        return $this->get_yesno_item_display($column, $item);
    }
}

?>
