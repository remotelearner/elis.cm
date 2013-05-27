<?php
/**
 * Common functions.
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

require_once($CFG->dirroot.'/elis/program/lib/setup.php');

/**
 * Helper method for setting up a menu item based on a CM entity
 *
 * @param   string    $type                  The type of CM entity we are using
 * @param   object    $instance              CM entity instance
 * @param   string    $parent                Name of the parent element
 * @param   string    $css_class             CSS class used for styling this item
 * @param   int       $parent_cluster_id     The last cluster passed going down the curr_admin tree, or 0 if none
 * @param   int       $parent_curriculum_id  The last curriculum passed going down the curr_admin tree, or 0 if none
 * @param   array     $params                Any page params that are needed
 * @param   boolean   $isLeaf                If true, this node is automatically a leaf
 * @param   string    $parent_path           Path of parent curriculum elements in the tree
 * @return  menuitem                         The appropriate menu item
 */
function block_curr_admin_get_menu_item($type, $instance, $parent, $css_class, $parent_cluster_id, $parent_curriculum_id, $params = array(), $isLeaf = false, $parent_path = '') {
    $display = '';

    //determine the display attribute from the entity type
    switch ($type) {
        case 'cluster':
            $type = 'userset';
        case 'userset':
            $display = 'name';
            break;
        case 'curriculum':
            $display = 'name';
            break;
        case 'course':
            $display = 'coursename';
            break;
        case 'track':
            $display = 'name';
            break;
        case 'cmclass':
            $type = 'pmclass';
        case 'pmclass':
            $display = 'clsname';
            break;
        default:
            error_log("blocks/curr_admin/lib.php::block_curr_admin_get_menu_item() invalid type: {$type}");
            break;
    }

    //unique id for this menu item
    $item_id = "{$type}_{$instance->id}";

    //create appropriate page type with correct parameters
    $page = new menuitempage("{$type}page", '', $params);

    //create the menu item
    $result = new menuitem($item_id, $page, $parent, $instance->$display, $css_class, '', true, $parent_path);

    $current_path = '';
    if (in_array($type, array('userset', 'curriculum', 'course', 'track', 'pmclass'))) {
        $current_path = $type . '-' . $instance->id;

        if (!empty($parent_path)) {
            $current_path = $parent_path . '/' . $current_path;
        }
    }

    //put key info into this id for later use
    $result->contentElId = "{$type}_{$instance->id}_{$parent_cluster_id}_{$parent_curriculum_id}_{$current_path}";

    //is this a leaf node?
    $result->isLeaf = $isLeaf;

    //convert to a leaf is appropriate
    block_curr_admin_truncate_leaf($type, $result, $parent_cluster_id, $parent_curriculum_id);

    return $result;
}

/**
 * Helper method for creating a summary item
 * @param   string    $type         The type of CM entity we are summarizing
 * @param   string    $css_class    CSS class used for styling this item
 * @param   int       $num_more     The number of items not being displayed
 * @param   array     $params       Any page params that are needed
 * @param   string    $classfile    The name of the PHP file containing the page class
 * @param   string    $parent_path  Path of parent curriculum elements in the tree
 * @return  menuitem                The appropriate menu item
 */
function block_curr_admin_get_menu_summary_item($type, $css_class, $num_more, $params = array(), $classfile='', $parent_path = '') {

    //the id of this element doesn't really matter, just make sure it's unique
    static $index = 1;
    //just to be extra sure of uniqueness
    $time = time();

    $page = new menuitempage($type . 'page', $classfile, $params);

    //display text
    if ($num_more == 1) {
        $display = get_string('menu_summary_item_' . $type . '_singular', 'block_curr_admin', $num_more);
    } else {
        $display = get_string('menu_summary_item_' . $type, 'block_curr_admin', $num_more);
    }

    //create a new menuitem that is flagged as sensitive to JS inclusion
    $id = $type . '_summary_' . $index . '_' . $time;
    $result = new menuitem($id, $page, 'root', $display, $css_class, '', true, $parent_path);

    //summary items should never have children
    $result->isLeaf = true;

    $index++;

    return $result;
}

/**
 * Makes the specified node a leaf is the appropriate criteria is met
 *
 * @param  string    $type                  The type of entity the supplied node represents
 * @param  menuitem  $menuitem              The current menu item
 * @param  int       $parent_cluster_id     The last cluster passed going down the curr_admin tree, or 0 if none
 * @param  int       $parent_curriculum_id  The last curriculum passed going down the curr_admin tree, or 0 if none
 */
function block_curr_admin_truncate_leaf($type, &$menuitem, $parent_cluster_id, $parent_curriculum_id) {
    //any cluster under a curriculum should be a leaf node
    if (($type == 'cluster' || $type == 'userset') && !empty($parent_curriculum_id)) {
        $menuitem->isLeaf = true;
    }

    //any class should also be a leaf node
    if ($type == 'pmclass' || $type == 'cmclass') {
        $menuitem->isLeaf = true;
    }
}

/**
 * Dynamically loads child menu items for a CM entity
 *
 * @param   string          $type                  The type of entity
 * @param   int             $id                    The entity id
 * @param   int             $parent_cluster_id     The last cluster passed going down the curr_admin tree, or 0 if none
 * @param   int             $parent_curriculum_id  The last curriculum passed going down the curr_admin tree, or 0 if none
 * @param   string          $parent_path           Path of parent curriculum elements in the tree
 * @return  menuitem array                         The appropriate child items
 */
function block_curr_admin_load_menu_children($type, $id, $parent_cluster_id, $parent_curriculum_id, $parent_path = '') {
    global $CURMAN;

    $function_name = "block_curr_admin_load_menu_children_{$type}";

    $result_items = array(new menuitem('root'));
    $extra_results = array();

    if (function_exists($function_name)) {
        $num_block_icons = isset(elis::$config->elis_program->num_block_icons) ? elis::$config->elis_program->num_block_icons : 5;

        $extra_results = call_user_func($function_name, $id, $parent_cluster_id, $parent_curriculum_id, $num_block_icons, $parent_path);
    }

    return array_merge($result_items, $extra_results);
}

/**
 * Dynamically loads child menu items for a cluster entity
 *
 * @param   int             $id                    The entity id
 * @param   int             $parent_cluster_id     The last cluster passed going down the curr_admin tree, or 0 if none
 * @param   int             $parent_curriculum_id  The last curriculum passed going down the curr_admin tree, or 0 if none
 * @param   int             $num_block_icons       Max number of entries to display
 * @param   string          $parent_path           Path of parent curriculum elements in the tree
 * @return  menuitem array                         The appropriate child items
 */
function block_curr_admin_load_menu_children_userset($id, $parent_cluster_id, $parent_curriculum_id, $num_block_icons, $parent_path = '') {
	global $CFG;

	//page dependencies
	require_once(elispm::file('coursepage.class.php'));

    $result_items = array();

    /*****************************************
     * Cluster - Child Cluster Associations
     *****************************************/
    //note - no need to worry about permissions since there is no prevents/prohibits in ELIS
    $cluster_css_class = block_curr_admin_get_item_css_class('cluster_instance');

    $listing = cluster_get_listing('name', 'ASC', 0, $num_block_icons, '', '', array('parent' => $id));
    //$listing = cluster_get_listing('priority, name', 'ASC', 0, $num_block_icons, '', '', array('parent' => $id));

    if (!empty($listing)) {
        foreach ($listing as $item) {
            $params = array('id' => $item->id,
                            'action' => 'view');

            $cluster_count = cluster_count_records('', '', array('parent' => $item->id));
            $curriculum_count = clustercurriculum::count_curricula($item->id);

            $isLeaf = empty($cluster_count) &&
                      empty($curriculum_count);

            $result_items[] = block_curr_admin_get_menu_item('userset', $item, 'root', $cluster_css_class, $item->id, $parent_curriculum_id, $params, $isLeaf, $parent_path);
        }
    }

    //summary item
    $num_records = cluster_count_records('', '', array('parent' => $id));
    if ($num_block_icons < $num_records) {
        $params = array('id' => $parent_cluster_id);
        $result_items[] = block_curr_admin_get_menu_summary_item('userset', $cluster_css_class, $num_records - $num_block_icons, $params, '', $parent_path);
    }

    /*****************************************
     * Cluster - Curriculum
     *****************************************/
    $curriculum_css_class = block_curr_admin_get_item_css_class('curriculum_instance');

    //permissions filter
    $curriculum_filter = array('contexts' => curriculumpage::get_contexts('elis/program:program_view'));

    $curricula = clustercurriculum::get_curricula($id, 0, $num_block_icons, 'cur.priority ASC, cur.name ASC', $curriculum_filter);

    if (!empty($curricula)) {
        foreach ($curricula as $curriculum) {
            $curriculum->id = $curriculum->curriculumid;
            $params = array('id' => $curriculum->id,
                            'action' => 'view');

            //count associated courses
            $course_filter = array('contexts' => coursepage::get_contexts('elis/program:course_view'));
            $course_count = curriculumcourse_count_records($curriculum->id, '', '', $course_filter);

            //count associated tracks
            $track_contexts = trackpage::get_contexts('elis/program:track_view');
            $track_count = track_count_records('', '', $curriculum->id, $parent_cluster_id, $track_contexts);

            //count associated clusters
            $cluster_filter = array('contexts' => usersetpage::get_contexts('elis/program:userset_view'));
            $cluster_count = clustercurriculum::count_clusters($curriculum->id, $parent_cluster_id, $cluster_filter);

            $isLeaf = empty($course_count) &&
                      empty($track_count) &&
                      empty($cluster_count);

            $result_items[] = block_curr_admin_get_menu_item('curriculum', $curriculum, 'root', $curriculum_css_class, $parent_cluster_id, $curriculum->id, $params, $isLeaf, $parent_path);
        }
    }

    //summary item
    $num_records = clustercurriculum::count_curricula($id, $curriculum_filter);
    if ($num_block_icons < $num_records) {
        $params = array('id' => $id);
        $result_items[] = block_curr_admin_get_menu_summary_item('clustercurriculum', $curriculum_css_class, $num_records - $num_block_icons, $params, '', $parent_path);
    }

    return $result_items;
}

/**
 * Dynamically loads child menu items for a curriculum entity
 *
 * @param   int             $id                    The entity id
 * @param   int             $parent_cluster_id     The last cluster passed going down the curr_admin tree, or 0 if none
 * @param   int             $parent_curriculum_id  The last curriculum passed going down the curr_admin tree, or 0 if none
 * @param   int             $num_block_icons       Max number of entries to display
 * @param   string          $parent_path           Path of parent curriculum elements in the tree
 * @return  menuitem array                         The appropriate child items
 */
function block_curr_admin_load_menu_children_curriculum($id, $parent_cluster_id, $parent_curriculum_id, $num_block_icons, $parent_path = '') {
	global $CFG;

	//page dependencies
	require_once(elispm::file('pmclasspage.class.php'));

    $result_items = array();

    /*****************************************
     * Curriculum - Course Associations
     *****************************************/
    $course_css_class = block_curr_admin_get_item_css_class('course_instance');

    //permissions filter
    $course_filter = array('contexts' => coursepage::get_contexts('elis/program:course_view'));

    $listing = curriculumcourse_get_listing($id, 'position', 'ASC', 0, $num_block_icons, '', '', $course_filter);
    foreach ($listing as $item) {
        $item->id = $item->courseid;
        $params = array('id'     => $item->id,
                        'action' => 'view');

        //count associated classes
        $class_contexts = pmclasspage::get_contexts('elis/program:class_view');
        $class_count = pmclass_count_records('', '', $item->id, false, $class_contexts, $parent_cluster_id);

        $isLeaf = empty($class_count);

        $result_items[] = block_curr_admin_get_menu_item('course', $item, 'root', $course_css_class, $parent_cluster_id, $parent_curriculum_id, $params, $isLeaf, $parent_path);
    }
    unset($listing);

    //summary item
    $num_records = curriculumcourse_count_records($id, '', '', $course_filter);
    if ($num_block_icons < $num_records) {
        $params = array('id' => $id);
        $result_items[] = block_curr_admin_get_menu_summary_item('curriculumcourse', $course_css_class, $num_records - $num_block_icons, $params, '', $parent_path);
    }

    /*****************************************
     * Curriculum - Track Associations
     *****************************************/
    $track_css_class = block_curr_admin_get_item_css_class('track_instance');

    //permissions filter
    $track_contexts = trackpage::get_contexts('elis/program:track_view');

    if ($track_records = track_get_listing('name', 'ASC', 0, $num_block_icons, '', '', $id, $parent_cluster_id, $track_contexts)) {
        foreach ($track_records as $track_record) {
            $params = array('id'     => $track_record->id,
                            'action' => 'view');

            //count associated classes
            $class_contexts = array('contexts' => pmclasspage::get_contexts('elis/program:class_view'));
            $class_count = track_assignment_count_records($track_record->id, '', '', $class_contexts);

            //count associated clusters
            $cluster_filter = array('contexts' => usersetpage::get_contexts('elis/program:userset_view'));
            $cluster_count = clustertrack::count_clusters($track_record->id, $parent_cluster_id, $cluster_filter);

            $isLeaf = empty($class_count) &&
                      empty($cluster_count);

            $result_items[] = block_curr_admin_get_menu_item('track', $track_record, 'root', $track_css_class, $parent_cluster_id, $parent_curriculum_id, $params, $isLeaf, $parent_path);
        }
    }

    //summary item
    $num_records = track_count_records('', '', $id, $parent_cluster_id, $track_contexts);
    if ($num_block_icons < $num_records) {
        $params = array('id' => $id);

        //add extra param if appropriate
        if (!empty($parent_cluster_id)) {
            $params['parent_clusterid'] = $parent_cluster_id;
        }
        $result_items[] = block_curr_admin_get_menu_summary_item('track', $track_css_class, $num_records - $num_block_icons, $params, '', $parent_path);
    }

    /*****************************************
     * Curriculum - Cluster Associations
     *****************************************/
    $cluster_css_class = block_curr_admin_get_item_css_class('cluster_instance');

    //permissions filter
    $cluster_filter = array('contexts' => usersetpage::get_contexts('elis/program:userset_view'));

    $clusters = clustercurriculum::get_clusters($id, $parent_cluster_id, 'name', 'ASC', 0, $num_block_icons, $cluster_filter);
    //$clusters = clustercurriculum::get_clusters($id, $parent_cluster_id, 'priority, name', 'ASC', 0, $num_block_icons);

    if (!empty($clusters)) {
        foreach ($clusters as $cluster) {
            $cluster->id = $cluster->clusterid;
            $params = array('id'     => $cluster->id,
                            'action' => 'view');
            $result_items[] = block_curr_admin_get_menu_item('userset', $cluster, 'root', $cluster_css_class, $cluster->id, $parent_curriculum_id, $params, false, $parent_path);
        }
    }

    //summary item
    $num_records = clustercurriculum::count_clusters($id, $parent_cluster_id, $cluster_filter);
    if ($num_block_icons < $num_records) {
        $params = array('id' => $id);

        //add extra param if appropriate
        if (!empty($parent_cluster_id)) {
            $params['parent_clusterid'] = $parent_cluster_id;
        }

        $result_items[] = block_curr_admin_get_menu_summary_item('curriculumcluster', $cluster_css_class, $num_records - $num_block_icons, $params, 'clustercurriculumpage.class.php', $parent_path);
    }

    return $result_items;
}

/**
 * Dynamically loads child menu items for a track entity
 *
 * @param   int             $id                    The entity id
 * @param   int             $parent_cluster_id     The last cluster passed going down the curr_admin tree, or 0 if none
 * @param   int             $parent_curriculum_id  The last curriculum passed going down the curr_admin tree, or 0 if none
 * @param   int             $num_block_icons       Max number of entries to display
 * @param   string          $parent_path           Path of parent curriculum elements in the tree
 * @return  menuitem array                         The appropriate child items
 */
function block_curr_admin_load_menu_children_track($id, $parent_cluster_id, $parent_curriculum_id, $num_block_icons, $parent_path = '') {
	global $CFG;

    //page dependencies
	require_once(elispm::file('pmclasspage.class.php'));

    $result_items = array();

    /*****************************************
     * Track - Class Associations
     *****************************************/
    $class_css_class = block_curr_admin_get_item_css_class('class_instance');

    //permissions filter
    $class_filter = array('contexts' => pmclasspage::get_contexts('elis/program:class_view'));

    $listing = track_assignment_get_listing($id, 'cls.idnumber', 'ASC', 0, $num_block_icons, '', '', $class_filter);
    foreach ($listing as $item) {
        $item->id = $item->classid;
        $params = array('id'     => $item->id,
                        'action' => 'view');
        $result_items[] = block_curr_admin_get_menu_item('pmclass', $item, 'root', $class_css_class, $parent_cluster_id, $parent_curriculum_id, $params, false, $parent_path);
    }
    unset($listing);

    //summary item
    $num_records = track_assignment_count_records($id, '', '', $class_filter);
    if ($num_block_icons < $num_records) {
        $params = array('id' => $id);
        $result_items[] = block_curr_admin_get_menu_summary_item('trackassignment', $class_css_class, $num_records - $num_block_icons, $params, '', $parent_path);
    }

    /*****************************************
     * Track - Cluster Associations
     *****************************************/
    $cluster_css_class = block_curr_admin_get_item_css_class('cluster_instance');

    //permissions filter
    $cluster_filter = array('contexts' => usersetpage::get_contexts('elis/program:userset_view'));

    $clusters = clustertrack::get_clusters($id, $parent_cluster_id, 'name', 'ASC', 0, $num_block_icons, $cluster_filter);
    //$clusters = clustertrack::get_clusters($id, 0, 'priority, name', 'ASC', $num_block_icons, $parent_cluster_id);

    if (!empty($clusters)) {
        foreach ($clusters as $cluster) {
            $cluster->id = $cluster->clusterid;
            $params = array('id'     => $cluster->id,
                            'action' => 'view');

            $result_items[] = block_curr_admin_get_menu_item('cluster', $cluster, 'root', $cluster_css_class, $cluster->id, $parent_curriculum_id, $params, false, $parent_path);
        }
    }

    //summary item
    $num_records = clustertrack::count_clusters($id, $parent_cluster_id, $cluster_filter);
    if ($num_block_icons < $num_records) {
        $params = array('id' => $id);

        //add extra param if appropriate
        if (!empty($parent_cluster_id)) {
           $params['parent_clusterid'] = $parent_cluster_id;
        }

        $result_items[] = block_curr_admin_get_menu_summary_item('trackcluster', $cluster_css_class, $num_records - $num_block_icons, $params, 'clustertrackpage.class.php', $parent_path);
    }

    return $result_items;
}

/**
 * Dynamically loads child menu items for a course entity
 *
 * @param   int             $id                    The entity id
 * @param   int             $parent_cluster_id     The last cluster passed going down the curr_admin tree, or 0 if none
 * @param   int             $parent_curriculum_id  The last curriculum passed going down the curr_admin tree, or 0 if none
 * @param   int             $num_block_icons       Max number of entries to display
 * @param   string          $parent_path           Path of parent curriculum elements in the tree
 * @return  menuitem array                         The appropriate child items
 */
function block_curr_admin_load_menu_children_course($id, $parent_cluster_id, $parent_curriculum_id, $num_block_icons, $parent_path = '') {
	global $CFG;

	//page dependencies
	require_once(elispm::file('pmclasspage.class.php'));

    $result_items = array();

    /*****************************************
     * Course - Class Associations
     *****************************************/
    $class_css_class = block_curr_admin_get_item_css_class('class_instance');

    //permissions filter
    $class_contexts = pmclasspage::get_contexts('elis/program:class_view');

    $listing = pmclass_get_listing('crsname', 'asc', 0, $num_block_icons, '', '',
                                   $id, false, $class_contexts, $parent_cluster_id);

    foreach ($listing as $item) {
        $item->clsname = $item->idnumber;
        $params = array('id' => $item->id,
                        'action' => 'view');
        $result_items[] = block_curr_admin_get_menu_item('pmclass', $item, 'root', $class_css_class, $parent_cluster_id, $parent_curriculum_id, $params, false, $parent_path);
    }
    unset($listing);

    //summary item
    $num_records = pmclass_count_records('', '', $id, false, $class_contexts, $parent_cluster_id);
    if ($num_block_icons < $num_records) {
        $params = array('action' => 'default',
                        'id'     => $id);

        //add extra param if appropriate
        if (!empty($parent_cluster_id)) {
            $params['parent_clusterid'] = $parent_cluster_id;
        }

        $result_items[] = block_curr_admin_get_menu_summary_item('pmclass', $class_css_class, $num_records - $num_block_icons, $params, '', $parent_path);
    }

    return $result_items;
}

/**
 * Calculates the full list of css classes for a particular menu item
 *
 * @param   string   $class     Any classes we automatically want included
 * @param   boolean  $category  Whether the current node is a category / folder
 *
 * @return  string              The full CSS class attribute string
 */
function block_curr_admin_get_item_css_class($class, $category = false) {
    $categorycss = empty($category) ? '' : ' category';

    // Handle empty class.
    $class = trim($class);
    if (empty($class)) {
        return "$categorycss tree_icon";
    }

    // Split up the class string.
    $classstrings = explode(' ', $class);
    $validclasses = array();

    $iconmap = array(
        'dashboard' => 'elisicon-dashboard',
        'bulkuser' => 'elisicon-bulkuseractions',
        'resultsconfig' => 'elisicon-resultsengine',
        'reportslist' => 'elisicon-report',
        'customfields' => 'elisicon-customfields',
        'clusterclassification' => 'elisicon-userset',
        'configuration' => 'elisicon-configuration',
        'notifications' => 'elisicon-notifications',
        'defaultcls' => 'elisicon-defaultsettings',
        'defaultcrs' => 'elisicon-defaultsettings',
        'manageusers' => 'elisicon-user',
        'manageclusters' => 'elisicon-userset',
        'certificatelist' => 'elisicon-certificate',
        'managecurricula' => 'elisicon-program',
        'managecourses' => 'elisicon-course',
        'manageclasses' => 'elisicon-class',
        'managetracks' => 'elisicon-track',
        'currentcourses' => 'elisicon-course',
        'availablecourses' => 'elisicon-program',
        'waitlist' => 'elisicon-waitlist',
        'schedulereports' => 'elisicon-schedule',
        'reportinstance' => 'elisicon-report',
        'cluster_instance' => 'elisicon-userset',
        'curriculum_instance' => 'elisicon-program',
        'course_instance' => 'elisicon-course',
        'track_instance' => 'elisicon-track',
        'class_instance' => 'elisicon-class',
    );

    // Prefix each token.
    foreach ($classstrings as $classstring) {
        $trimmed = trim($classstring);

        if (!empty($trimmed)) {
            $validclasses[] = 'curr_'.$trimmed;

            if (isset($iconmap[$trimmed])) {
                $validclasses[] = $iconmap[$trimmed];
            }
        }
    }

    // Add necessary classes.
    return implode(' ', $validclasses)." $categorycss tree_icon";
}

/**
 * Functions specifically for adding PHP report links to the Curr Admin menu
 */

/**
 * Specifies the mapping of tree category shortnames to display names
 *
 * @return  array  Mapping of tree category shortnames to display names,
 *                 in the order they should appear
 */
function block_curr_admin_get_tree_category_mapping() {
    global $CFG;

    if (!file_exists($CFG->dirroot .'/blocks/php_report/php_report_base.php')) {
        return array();
    }

    require_once($CFG->dirroot .'/blocks/php_report/php_report_base.php');

    //categories, in a pre-determined order
    return array(php_report::CATEGORY_CURRICULUM    => get_string('curriculum_reports',    'block_php_report'),
                 php_report::CATEGORY_COURSE        => get_string('course_reports',        'block_php_report'),
                 php_report::CATEGORY_CLASS         => get_string('class_reports',         'block_php_report'),
                 php_report::CATEGORY_CLUSTER       => get_string('cluster_reports',       'block_php_report'),
                 php_report::CATEGORY_PARTICIPATION => get_string('participation_reports', 'block_php_report'),
                 php_report::CATEGORY_USER          => get_string('user_reports',          'block_php_report'),
                 php_report::CATEGORY_ADMIN         => get_string('admin_reports',         'block_php_report'),
                 php_report::CATEGORY_OUTCOMES      => get_string('outcomes_reports',      'block_php_report'));
}

/**
 * Flattens a group of bucketed menu items into a one-dimensional
 * array suitable for use when building the curr admin tree
 *
 * @param   menuitem array  $buckets  Two-dimensional array of menu items
 *                                    indexed by report category, then report display name
 *
 * @return  menuitem array            One-dimensional array of menu items, sorted such that
 *                                    reports in the same category are ordered relative to one another
 *                                    by display name
 */
function block_curr_admin_get_report_bucket_items($buckets) {
    $pages = array();

    if (count($buckets) > 0) {
        //sort the elements in each bucket by report display name
        foreach ($buckets as $key => $bucket) {
            ksort($bucket);
            $buckets[$key] = $bucket;
        }

        //append all pages to the listing in an order that
        //guantees elements within a folder are sorted alphabetically
        foreach ($buckets as $bucket) {
            foreach ($bucket as $entry) {
                $pages[] = $entry;
            }
        }
    }

    return $pages;
}

/**
 * Specifies the entries needed in the menu corresponding to
 * report categories
 *
 * @return  menuitem array  Array of menuitems representing report categories,
 *                          not including the report items themselves
 */
function block_curr_admin_get_report_category_items() {
    $pages = array();

    //mapping of categories to displaynames
    $report_category_mapping = block_curr_admin_get_tree_category_mapping();

    //add all category entries to the tree under the reports entry
    foreach ($report_category_mapping as $key => $value) {
        //css clas for this menu item
        $css_class = block_curr_admin_get_item_css_class('reportcategory', TRUE);
        //construct the item itself
        $pages[] = new menuitem($key, NULL, 'rept', $value, $css_class, '', FALSE, 'rept');
    }

    return $pages;
}

/**
 * Specifies the tree entries used to represent links to PHP reports
 *
 * @return  menuitem array  List of menu items to add (including report categories
 *                          but excluding the top-level report entry)
 */
function block_curr_admin_get_report_tree_items() {
    global $CFG, $DB;

    //if the reports block is not installed, no entries will be displayed
    if (!$DB->record_exists('block', array('name' => 'php_report'))) {
        return array();
    }

    //get the category-level links
    $items = block_curr_admin_get_report_category_items();

    //path to library file for scheduling classes
    $schedulelib_path = $CFG->dirroot . '/blocks/php_report/lib/schedulelib.php';

    //check to make sure the required functionality is there
    //todo: remove this check when it's safe to do so
    if (file_exists($schedulelib_path)) {
        //reporting base class
        require_once($schedulelib_path);

        //schedule report entry
        //make sure we are using a "clean" page to check global permissions
        $test_permissions_page = new scheduling_page(array());

        //make sure we can access the report listing
        if ($test_permissions_page->can_do('list')) {
            //create a direct url to the list page
            $schedule_reports_page = new menuitempage('url_page', 'lib/menuitem.class.php', $CFG->wwwroot . '/blocks/php_report/schedule.php?action=list');
            //convert to a menu item
            $css_class = block_curr_admin_get_item_css_class('schedulereports');
            $schedule_reports_item = new menuitem('schedule_reports', $schedule_reports_page, 'rept', get_string('schedule_reports', 'block_php_report'), $css_class, '', FALSE, 'rept');
            //merge in with the current result
            $items = array_merge(array($schedule_reports_item), $items);
        }
    }

    //for storing the items bucketed by category
    $buckets = array();

    //look for all report instances
    if (file_exists($CFG->dirroot . '/blocks/php_report/instances') &&
        $handle = opendir($CFG->dirroot . '/blocks/php_report/instances')) {
        while (FALSE !== ($report_shortname = readdir($handle))) {

            //grab a test instance of the report in question
            $default_instance = php_report::get_default_instance($report_shortname);

            //make sure the report shortname is valid
            if ($default_instance !== FALSE) {

                //make sure the current user can access this report
                if ($default_instance->is_available() &&
                    $default_instance->can_view_report()) {

                    //user-friendly report name
                    $displayname = $default_instance->get_display_name();

                    //add the item to the necessary bucket
                    $item_category = $default_instance->get_category();
                    if (!isset($buckets[$item_category])) {
                        $buckets[$item_category] = array();
                    }

                    //obtain the page specific to this report
                    $report_page_classpath = $CFG->dirroot . '/blocks/php_report/lib/reportpage.class.php';
                    $report_page_params = array('report' => $report_shortname);
                    $page = new generic_menuitempage('report_page', $report_page_classpath, $report_page_params);

                    //retrieve the actual menuitem
                    $page_css_class = block_curr_admin_get_item_css_class('reportinstance');
                    $category_path = 'rept/' . $item_category;
                    $buckets[$item_category][$displayname] = new menuitem($report_shortname, $page, $item_category,
                                                                          $displayname, $page_css_class, '', FALSE, $category_path);
                }
            }
        }
    }

    //retrieve the items representing the reports themselves from the bucketed listings
    $report_instance_items = block_curr_admin_get_report_bucket_items($buckets);

    //merge the flat listings of category items and report instance items
    $items = array_merge($items, $report_instance_items);

    //return the flat listing
    return $items;
}

/**
 * Sets up a default instance of the curr admin blocks that
 * is viewable anywhere on the site, and cleans all other instances
 */
function block_curr_admin_create_instance() {
    global $DB;

    // First delete instances
    $params = array('blockname' => 'curr_admin');
    $instances = $DB->get_recordset('block_instances', $params);
    foreach($instances as $instance) {
        blocks_delete_instance($instance);
    }
    unset($instances);

    // Set up the new instance
    $block_instance_record = new stdclass;
    $block_instance_record->blockname = 'curr_admin';
    $block_instance_record->pagetypepattern = '*';
    $block_instance_record->parentcontextid = 1;
    $block_instance_record->showinsubcontexts = 1;
    // Force location
    $block_instance_record->defaultregion = 'side-pre';
    $block_instance_record->defaultweight = -999;
    $DB->insert_record('block_instances', $block_instance_record);
}
