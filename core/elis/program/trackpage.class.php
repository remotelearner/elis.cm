<?php
/**
 * General class for displaying pages in the curriculum management system.
 *
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

// Waiting on conversion
//require_once (CURMAN_DIRLOCATION . '/clustertrackpage.class.php');
//require_once (CURMAN_DIRLOCATION . '/rolepage.class.php');

require_once elispm::lib('data/track.class.php');
require_once elispm::lib('data/user.class.php');
require_once elispm::file('usertrackpage.class.php');
require_once elispm::lib('managementpage.class.php');
require_once elispm::file('trackassignmentpage.class.php');
require_once elispm::file('form/trackform.class.php');

class trackpage extends managementpage {
    var $data_class = 'track';
    var $form_class = 'trackform';
    var $pagename = 'trk';
    var $section = 'curr';

    var $view_columns = array('name', 'description');

    static $contexts = array();

    public static function get_contexts($capability) {
        if (!isset(trackpage::$contexts[$capability])) {
            global $USER;
            trackpage::$contexts[$capability] = get_contexts_by_capability_for_user('track', $capability, $USER->id);
        }
        return trackpage::$contexts[$capability];
    }

    /**
     * Check the cached capabilities for the current track.
     */
    public static function check_cached($capability, $id) {
        if (isset(trackpage::$contexts[$capability])) {
            // we've already cached which contexts the user has delete
            // capabilities in
            $contexts = trackpage::$contexts[$capability];
            return $contexts->context_allowed($id, 'track');
        }
        return null;
    }

    /**
     * Determines whether the current user is allowed to enrol users into the provided track
     *
     * @param   int      $trackid  The id of the track we are checking permissions on
     *
     * @return  boolean            Whether the user is allowed to enrol users into the curriculum
     *
     */
    public static function can_enrol_into_track($trackid) {
        global $USER;

        //check the standard capability

        // TODO: Ugly, this needs to be overhauled
        $tpage = new trackpage();

        if ($tpage->_has_capability('elis/program:track_enrol', $trackid)) {
            return true;
        }

        //get the context for the "indirect" capability
        $context = pm_context_set::for_user_with_capability('cluster', 'elis/program:track_enrol_userset_user', $USER->id);

        //get the clusters and check the context against them
        $clusters = clustertrack::get_clusters($trackid);
        if(!empty($clusters)) {
            foreach($clusters as $cluster) {
                if($context->context_allowed($cluster->clusterid, 'cluster')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if the user has the given capability for the requested track
     */
    public function _has_capability($capability, $id = null) {
        $id = $id ? $id : $this->required_param('id', PARAM_INT);

        $cached = trackpage::check_cached($capability, $id);
        if ($cached !== null) {
            return $cached;
        }
        $context = context_elis_track::instance($id);
        return has_capability($capability, $context);
    }

    public function __construct(array $params=null) {

        parent::__construct($params);
        $curid = $this->optional_param('parent', 0, PARAM_INT); // TBD: get_cm_id(empty($action));
        $curid_param = $curid ? array('parent' => $curid) : array();
        $this->tabs = array(
        array('tab_id' => 'view', 'page' => 'trackpage', 'params' => array('action' => 'view') + $curid_param, 'name' => get_string('detail','elis_program'), 'showtab' => true),
        array('tab_id' => 'edit', 'page' => 'trackpage', 'params' => array('action' => 'edit') + $curid_param, 'name' => get_string('edit','elis_program'), 'showtab' => true, 'showbutton' => true, 'image' => 'edit'),
        array('tab_id' => 'trackclusterpage', 'page' => 'trackclusterpage', 'name' => get_string('clusters','elis_program'), 'params' => $curid_param, 'showtab' => true, 'showbutton' => true, 'image' => 'cluster'),
        array('tab_id' => 'trackuserpage', 'page' => 'trackuserpage', 'params' => $curid_param, 'name' => get_string('users','elis_program'), 'showtab' => true, 'showbutton' => true, 'image' => 'user'),
        array('tab_id' => 'trackassignmentpage', 'page' => 'trackassignmentpage', 'params' => $curid_param, 'name' => get_string('track_classes','elis_program'), 'showtab' => true, 'showbutton' => true, 'image' => 'class'),
        array('tab_id' => 'track_rolepage', 'page' => 'track_rolepage', 'params' => $curid_param, 'name' => get_string('roles', 'role'), 'showtab' => true, 'showbutton' => false, 'image' => 'tag'),
        array('tab_id' => 'delete', 'page' => 'trackpage', 'params' => array('action' => 'delete') + $curid_param, 'name' => get_string('delete','elis_program'), 'showbutton' => true, 'image' => 'delete'),
        );

    }

    function can_do_view() {
        return $this->_has_capability('elis/program:track_view');
    }

    function can_do_edit() {
        return $this->_has_capability('elis/program:track_edit');
    }

    function can_do_delete() {
        return $this->_has_capability('elis/program:track_delete');
    }

    function can_do_confirm() {
        return $this->can_do_delete();
    }

    function can_do_add() {
        global $USER;
        if (!empty($USER->id)) {
            $contexts = get_contexts_by_capability_for_user('curriculum',
                            'elis/program:track_create', $USER->id);
            if ($contexts->is_empty()) {
                return false;
            }
        /* ***
            // Now user MUST have 'elis/program:program_edit'
            // on at least one curricula
            $contexts = get_contexts_by_capability_for_user('curriculum',
                            'elis/program:program_edit', $USER->id);
            return !$contexts->is_empty();
        *** */
            return true;
        }
        return false;
    }

    function can_do_default() {
        $contexts = trackpage::get_contexts('elis/program:track_view');
        return !$contexts->is_empty();
    }

    /**
     * Overrides the default navigation to include curriculum breadcrumbs if appropriate
     */
    function build_navbar_default($who = null, $addparent = true, $params = array()) {
        $action = $this->optional_param('action', '', PARAM_CLEAN);
        $cancel = $this->optional_param('cancel', '', PARAM_CLEAN);
        $paramname = '';
        $parent = $this->get_cm_id(empty($action), $paramname);
        $params = array();
        $lp_bc = true;
        if (!empty($parent) /* && (empty($action) ||empty($cancel)) */ ) {
            //viewing from within curriculum
            $params['id'] = $parent;
            //$params[$paramname] = $parent;
            $curriculumpage = new curriculumpage($params);
            $curriculum_navigation = $curriculumpage->build_navbar_view($this, $paramname);
            $lp_bc = false;
        }
        parent::build_navbar_default(null, $lp_bc, $params);
    }

    function build_navbar_view($who = null, $id_param = 'id', $extra_params = array()) {
        $paramname = '';
        $curid = $this->get_cm_id(false, $paramname);
        parent::build_navbar_view(null, 'id', $curid ? array($paramname => $curid): array());
    }

    /**
     * Display the track listing, filtering on curriculum if the id parameter is present
     */
    function display_default() {
        // Get parameters
        $sort         = optional_param('sort', 'name', PARAM_ALPHA);
        $dir          = optional_param('dir', 'ASC', PARAM_ALPHA);

        $page         = optional_param('page', 0, PARAM_INT);
        $perpage      = optional_param('perpage', 30, PARAM_INT);        // how many per page

        $namesearch   = trim(optional_param('search', '', PARAM_TEXT));
        $alpha        = optional_param('alpha', '', PARAM_ALPHA);

        $parent_clusterid = $this->optional_param('parent_clusterid', 0, PARAM_INT);

        //curriculum id
        $id = $this->get_cm_id();

        // Define columns
        $columns = array(
            'name'          => array('header' => get_string('track_name', 'elis_program')),
            'description'   => array('header' => get_string('track_description', 'elis_program')),
            'parcur'        => array('header' => get_string('track_parcur', 'elis_program')),
            'class'         => array('header' => get_string('track_classes', 'elis_program'))
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
        $items   = track_get_listing($sort, $dir, $page*$perpage, $perpage, $namesearch, $alpha, $id, $parent_clusterid, trackpage::get_contexts('elis/program:track_view'));
        $numitems = track_count_records($namesearch, $alpha, $id, $parent_clusterid, trackpage::get_contexts('elis/program:track_view'));

        trackpage::get_contexts('elis/program:track_edit');
        trackpage::get_contexts('elis/program:track_delete');

        if (!empty($id)) {
            //print curriculum tabs if viewing from the curriculum view
            $curriculumpage = new curriculumpage(array('id' => $id));
            $curriculumpage->print_tabs('trackpage', array('id' => $id));
        }
        $this->print_list_view($items, $numitems, $columns, $filter=null, $alphaflag=true, $searchflag=true);
    }

    function add_table_items($items, $columns, $table=null) {
        //$countries = cm_get_list_of_countries(); ???

        $table->width = "95%";

        foreach ($items as $item) {
            // TODO: (short term) push this logic to the page class, by using a get_cell_value($item, $columnname) function that is called for
            // each cell in the table.
            // TODO: (long term) push this logic to the model, either by using accessors or by using field types
            $newarr = array();

            foreach ($columns as $column => $cdesc) {
                if ($column == 'class') {
                    $newarr[] = '<a href="index.php?s=trkcls&amp;section=curr&amp;trackid='.$item->id.
                        '">' . $item->$column . '</a>';
                } else {
                    $newarr[] = $item->$column;
                }

                // Add link to specified columns
                if (in_array($column, $this->view_columns)) {
                    $target = $this->get_new_page(array('action' => 'view', 'id' => $item->id));
                    $newarr[count($newarr)-1] = '<a href="' . $target->url . '">' . $newarr[count($newarr)-1] . '</a>';
                }
            }

            $newarr[] = $this->get_buttons(array('id' => $item->id));
            $table->data[] = $newarr;
        }

        return $table;
    }

    /**
     * Prints the single-button form used to request the add action for a record type.
     */
    function print_add_button() {
        global $OUTPUT;

        if (!$this->can_do('add')) {
            return;
        }

        $obj = $this->get_new_data_object();

        echo '<div align="center">';
        $options = array('s' => $this->pagename, 'action' => 'add');
        $parent = $this->optional_param('id', 0, PARAM_INT);
        if ($parent) {
            $options['parent'] = $parent;
        }
        // FIXME: change to language string
        //echo print_single_button('index.php', $options, get_string('add','elis_program').' ' . get_string($obj->get_verbose_name(),'elis_program'), 'get', '_self', true, get_string('add','elis_program').' ' . get_string($obj->get_verbose_name(),'elis_program'));
        $button = new single_button(new moodle_url('index.php', $options), get_string('add_track','elis_program'), 'get');
        echo $OUTPUT->render($button);

        echo '</div>';
    }

    /**
     * Converts add button data to data for our add form
     *
     * @return  stdClass  Form data, or null if none
     */
    function get_default_object_for_add() {
        $parent = $this->optional_param('parent', 0, PARAM_INT);
        if ($parent) {
            $obj = new stdClass;
            $obj->curid = $parent;
            $obj->parent = $parent;
            return $obj;
        } else {
            return NULL;
        }
    }

    /**
     * Hook that gets called after a CM entity is added through this page
     * (Note: this function should only use the id field from the supplied cm entity
     *  as the rest of the data is not guaranteed to be there)
     *
     * @param  object  $cm_entity  The CM entity added
     * @uses $DB
     * @uses $USER
     */
    function after_cm_entity_add($cm_entity) {
        global $DB, $USER;

        //make sure a valid role is set
        if(!empty(elis::$config->elis_program->default_track_role_id) && $DB->record_exists('role', array('id' => elis::$config->elis_program->default_track_role_id))) {

            //get the context instance for capability checking
            $context_instance = context_elis_track::instance($cm_entity->id);

            //assign the appropriate role if the user does not have the edit capability
            if(!has_capability('elis/program:track_edit', $context_instance)) {
                role_assign(elis::$config->elis_program->default_track_role_id, $USER->id, $context_instance->id);
            }
        }
    }

    /**
     * Returns the cm id corresponding to this page's entity, taking into account
     * weirdness from cancel actions
     *
     * @param  bool   $check_id     true to check 'id' param
     * @param  string &$paramname   to return param name
     * @return int    The appropriate id, or zero if none available
     */
    function get_cm_id($check_id = true, &$paramname = null) {
        $idparams = array('parent', 'curid', 'id');
        foreach ($idparams as $param) {
            if ($param == 'id' && !$check_id) {
                break;
            }
            if ($paramname !== null) {
                $paramname = $param;
            }
            $id = $this->optional_param($param, 0, PARAM_INT);
            if (!empty($id)) {
                break;
            }
        }
        return $id;
    }

    /**
     * Creates a new table object with specified $items and $columns.
     * @param array $items
     * @param array $columns
     */
    function create_table_object($items, $columns) {

        $parent_clusterid = $this->optional_param('parent_clusterid', 0, PARAM_INT);
        $extra_params = array();
        if (!empty($parent_clusterid)) {
            $extra_params['parent_clusterid'] = $parent_clusterid;
        }
        if ($curid = $this->get_cm_id()) {
            $extra_params['parent'] = $curid;
        }
        $page_object = $this->get_new_page($extra_params);

        //pass along the extra parameters because they are protected within
        //the page object
        return new management_page_table($items, $columns, $page_object, $extra_params);
    }

    /**
     * Determines the name of the context class that represents this page's cm entity
     *
     * @return  string  The name of the context class that represents this page's cm entity
     *
     * @todo            Do something less complex to determine the appropriate class
     *                  (requires page class redesign)
     */
    function get_page_context() {
        $action = $this->optional_param('action', '', PARAM_ACTION);
        $id = $this->optional_param('id', 0, PARAM_INT);

        if ($action == '' && $id > 0) {
            //need a special case because there isn't a proper page for listing
            //tracks that belong to a curriculum

            //@todo  Create a proper page that lists tracks that belong to a curriculum
            return 'curriculum';
        } else {
            return 'track';
        }
    }

    /**
     * Specifies a unique shortname for the entity represented by
     * a page of this type, transforming the supplied value if necessary
     *
     * @param   string       $parent_path  Path of all parent elements, or the empty string
     *                                     if none
     * @param   string       $name         Initial name provided for the element
     *
     * @return  string|NULL                A valid name to identify the item with, or NULL if
     *                                     not applicable
     */
    static function get_entity_name($parent_path, $name) {
        $parts = explode('_', $name);

        //try to find the entity type and id, and combine them
        if (count($parts) == 2) {
            if ($parts[0] == 'track') {
                return $parts[0] . '-' . $parts[1];
            }
        }

        return NULL;
    }
}
