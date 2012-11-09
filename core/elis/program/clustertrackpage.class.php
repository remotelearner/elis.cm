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

defined('MOODLE_INTERNAL') || die();

require_once elispm::lib('associationpage.class.php');
require_once elispm::lib('data/clustercurriculum.class.php');
require_once elispm::lib('data/clustertrack.class.php');
require_once elispm::file('usersetpage.class.php');
require_once elispm::file('trackpage.class.php');
require_once elispm::file('form/clustertrackform.class.php');
require_once elispm::file('form/clustertrackeditform.class.php');

class clustertrackbasepage extends associationpage {

    var $data_class = 'clustertrack';
    var $form_class = 'clustertrackform';
    var $edit_form_class = 'clustertrackeditform';
//    var $tabs;

    function __construct(array $params=null) {
        $this->tabs = array(
            array('tab_id' => 'edit',
                  'page' => get_class($this),
                  'params' => array('action' => 'edit'),
                  'name' => get_string('edit', 'elis_program'),
                  'showtab' => true,
                  'showbutton' => true,
                  'image' => 'edit'),
            array('tab_id' => 'delete',
                  'page' => get_class($this),
                  'params' => array('action' => 'delete'),
                  'name' => get_string('delete_label', 'elis_program'),
                  'showbutton' => true,
                  'image' => 'delete'),
        );

        parent::__construct($params);
    }

    function can_do_add() {
        // the user must have 'elis/program:associate' permissions on both ends
        $clusterid = $this->required_param('clusterid', PARAM_INT);
        $trackid = $this->required_param('trackid', PARAM_INT);

        return usersetpage::_has_capability('elis/program:associate', $clusterid)
            && trackpage::_has_capability('elis/program:associate', $trackid);
    }

    function do_add() {
        $id = $this->required_param('id', PARAM_INT);
        $autounenrol = $this->optional_param('autounenrol', 1, PARAM_INT);
        $clusterid = $this->required_param('clusterid', PARAM_INT);
        $trackid = $this->required_param('trackid', PARAM_INT);


        $target = $this->get_new_page(array('action'       => 'add',
                                            'id'           => $id,
                                            'clusterid'    => $clusterid,
                                            'trackid'      => $trackid));

        $form = new $this->form_class($target->url, array('id'        => $id,
                                                           'clusterid' => $clusterid,
                                                           'trackid'   => $trackid));

        $form->set_data(array('clusterid' => $clusterid,
                              'trackid' => $trackid));

        if ($form->is_cancelled()) {
            $target = $this->get_new_page(array('action' => 'default', 'id' => $id), true);
            redirect($target->url);
        } else if($data = $form->get_data()) {
            clustertrack::associate($clusterid, $trackid, $autounenrol, $data->autoenrol);
            $target = $this->get_new_page(array('action' => 'default', 'id' => $id), true);
            redirect($target->url);
        } else {
            $this->display('add');
        }

    }

    /**
     * Prints the add form.
     * @param $parent_obj is the basic data object we are forming an association with.
     */
    function print_add_form($parent_obj) {
        $id = required_param('id', PARAM_INT);
        $clusterid = $this->required_param('clusterid', PARAM_INT);
        $trackid = $this->required_param('trackid', PARAM_INT);

        require_once elispm::file('plugins/userset_classification/usersetclassification.class.php');

        $target = $this->get_new_page(array('action'       => 'add',
                                            'id'           => $id));
        $form = new $this->form_class($target->url, array('id'        => $id));
        $form->set_data(array('id' => $id,
                              'clusterid' => $clusterid,
                              'trackid' => $trackid));
        $userset_classification = usersetclassification::get_for_cluster($clusterid);
        if (!empty($userset_classification->param_autoenrol_tracks)) {
            $form->set_data(array('autoenrol' => 1));
        } else {
            $form->set_data(array('autoenrol' => 0));
        }

        $form->display();
    }

    function can_do_edit() {
        // the user must have 'elis/program:associate' permissions on both
        // ends
        $association_id = $this->required_param('association_id', PARAM_INT);
        $record = new clustertrack($association_id);
        $clusterid = $record->clusterid;
        $trackid = $record->trackid;
//echo '<br>association id: '.$association_id.' clusterid: '.$clusterid.' trackid: '.$trackid.'*<br>';
        return usersetpage::_has_capability('elis/program:associate', $clusterid)
            && trackpage::_has_capability('elis/program:associate', $trackid);
    }

    function can_do_delete() {
        return $this->can_do_edit();
    }

    /**
     * handler for the edit action.  Prints the edit form.
     */
    function display_edit() { // do_edit()
        $association_id = required_param('association_id', PARAM_INT);
        $id             = required_param('id', PARAM_INT);
        $obj            = new $this->data_class($association_id);
        $parent_obj     = new $this->parent_data_class($id);

        /*if(!$obj->get_dbloaded()) { // TBD
            $sparam = new stdClass;
            $sparam->id = $id;
            print_error('invalid_objectid', 'elis_program', '', $sparam);
        }*/
       // $this->get_tab_page()->print_tabs('edit', array('id' => $id)); // TBD
        $this->print_edit_form($obj, $parent_obj);
    }

    function do_edit() {
        $id = $this->required_param('id', PARAM_INT);
        $association_id = $this->required_param('association_id', PARAM_INT);

        $target = $this->get_new_page(array('action'         => 'edit',
                                            'id'             => $id,
                                            'association_id' => $association_id));

        $form = new $this->edit_form_class($target->url, array('id' => $id,
                                                               'association_id' => $association_id));

        $form->set_data(array('id' => $id,
                              'association_id' => $association_id));

        if ($form->is_cancelled()) {
            $target = $this->get_new_page(array('action' => 'default', 'id' => $id), true);
            redirect($target->url);
        } else if($data = $form->get_data()) {
            clustertrack::update_autoenrol($association_id, $data->autoenrol);
            $target = $this->get_new_page(array('action' => 'default', 'id' => $id), true);
            redirect($target->url);
        } else {
            $this->display('edit');
        }
    }

    /**
     * Prints the edit form.
     * @param $obj The association object being edited.
     * @param $parent_obj The basic data object being associated with.
     */
    function print_edit_form($obj, $parent_obj) {
        $parent_id = required_param('id', PARAM_INT);
//        $association_id = required_param('association_id', PARAM_INT);

        $target = $this->get_new_page(array('action' => 'edit', 'id' => $parent_id));

        $form = new $this->edit_form_class($target->url);
        $form->set_data(array('id' => $parent_obj->id,
                              'association_id' => $obj->id));
        $form->display();
    }

    function create_table_object($items, $columns) {
        return new clustertrack_page_table($items, $columns, $this);
    }

}

class clustertrackpage extends clustertrackbasepage {
    var $pagename = 'clsttrk';
    var $tab_page = 'usersetpage';
    var $default_tab = 'clustertrackpage';

    var $section = 'users';

    var $parent_data_class = 'userset';

    function can_do_default() {
        $id = $this->required_param('id', PARAM_INT);

        if (usersetpage::_has_capability('elis/program:userset_view', $id)) {
            //allow viewing but not managing associations
        	return true;
        }

        return usersetpage::_has_capability('elis/program:associate', $id);
    }

    function display_default() {
        $id           = $this->required_param('id', PARAM_INT);
        $sort         = $this->optional_param('sort', 'idnumber', PARAM_ALPHANUM);
        $dir          = $this->optional_param('dir', 'ASC', PARAM_ALPHA);
        $page         = $this->optional_param('page', 0, PARAM_INT);
        $perpage      = $this->optional_param('perpage', 30, PARAM_INT); // how many per page

        $columns = array(
            'idnumber'    => array('header' => get_string('track_idnumber','elis_program'),
                                   'decorator' => array(new record_link_decorator('trackpage',
                                                                                  array('action'=>'view'),
                                                                                  'trackid'),
                                                        'decorate')),
            'name'        => array('header' => get_string('track_name','elis_program'),
                                   'decorator' => array(new record_link_decorator('trackpage',
                                                                                  array('action'=>'view'),
                                                                                  'trackid'),
                                                        'decorate')),
            'description' => array('header' => get_string('track_description','elis_program')),
            'autoenrol'   => array('header' => get_string('usersettrack_autoenrol', 'elis_program')),
             //buttons triggers the use of "tabs" as buttons for editing and deleting
            'buttons'     => array('header' => ''),
        );

        // TBD
        if ($dir !== 'DESC') {
            $dir = 'ASC';
        }
        if (isset($columns[$sort])) {
            $columns[$sort]['sortable'] = $dir;
        } else {
            $sort = 'idnumber';
            $columns[$sort]['sortable'] = $dir;
        }

        $items = clustertrack::get_tracks($id, $sort, $dir);

        $this->print_list_view($items, $columns, 'tracks');

        // find the tracks that the user can associate with this cluster
        $contexts = trackpage::get_contexts('elis/program:associate');
        $tracks = track_get_listing('name', 'ASC', 0, 0, '', '', 0, 0, $contexts);
        if (empty($tracks)) {
            $num_tracks = track_count_records();
            if (!empty($num_tracks)) {
                // some tracks exist, but don't have associate capability on
                // any of them
                echo '<div align="center"><br />';
                print_string('no_associate_caps_track', 'elis_program');
                echo '</div>';
            } else {
                // no tracks at all
                echo '<div align="center"><br />';
                print_string('all_items_assigned', 'elis_program');
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
    var $default_tab = 'trackclusterpage';

    var $section = 'curr';
    var $parent_data_class = 'track';

    function can_do_default() {
        $id = $this->required_param('id', PARAM_INT);

        if (trackpage::_has_capability('elis/program:track_view', $id)) {
            //allow viewing but not managing associations
        	return true;
        }

        return trackpage::_has_capability('elis/program:associate', $id);
    }

    function display_default() {
        $id = $this->required_param('id', PARAM_INT);

        $parent_clusterid = $this->optional_param('parent_clusterid', 0, PARAM_INT);
        $sort = $this->optional_param('sort', 'name', PARAM_CLEAN);
        $dir = $this->optional_param('dir', 'ASC', PARAM_CLEAN);

        $columns = array(
            'name'        => array('header' => get_string('userset_name','elis_program'),
                                   'decorator' => array(new record_link_decorator('usersetpage',
                                                                                  array('action'=>'view'),
                                                                                  'clusterid'),
                                                        'decorate')),
            'display'     => array('header' => get_string('userset_description','elis_program')),
            'autoenrol'   => array('header' => get_string('trackuserset_auto_enrol', 'elis_program')),
             //buttons triggers the use of "tabs" as buttons for editing and deleting
            'buttons'     => array('header' => ''),
        );

        // TBD
        if ($dir !== 'DESC') {
            $dir = 'ASC';
        }
        if (isset($columns[$sort])) {
            $columns[$sort]['sortable'] = $dir;
        } else {
            $sort = 'name';
            $columns[$sort]['sortable'] = $dir;
        }

        $items = clustertrack::get_clusters($id, $parent_clusterid, $sort, $dir);

        $this->print_list_view($items, $columns, 'clusters');

        // find the tracks that the user can associate with this cluster
        $contexts = usersetpage::get_contexts('elis/program:associate');
        $clusters = cluster_get_listing('name', 'ASC', 0, 0, '', '', array('contexts' =>$contexts));
        if (empty($clusters)) {
            $num_clusters = cluster_count_records();
            if (!empty($num_clusters)) {
                // some clusters exist, but don't have associate capability on
                // any of them
                echo '<div align="center"><br />';
                print_string('no_associate_caps_clusters', 'elis_program');
                echo '</div>';
            } else {
                // no clusters at all
                echo '<div align="center"><br />';
                print_string('all_items_assigned', 'elis_program');
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
     */
    function create_table_object($items, $columns) {

        $parent_clusterid = $this->optional_param('parent_clusterid', 0, PARAM_INT);

        $extra_params = array();
        if(!empty($parent_clusterid)) {
            $extra_params['parent_clusterid'] = $parent_clusterid;
        }

        $page_object = $this->get_new_page($extra_params);

        return new clustertrack_page_table($items, $columns, $page_object);
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
        return $this->display_yesno_item($column, $item);
    }
}

