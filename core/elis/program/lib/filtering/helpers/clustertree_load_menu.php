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
 * @subpackage pm-filtering-clustertree
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

//require necessary dependencies 
require_once(dirname(__FILE__) .'/../../../../../config.php');

require_once($CFG->dirroot .'/elis/program/lib/setup.php');
require_once($CFG->dirroot .'/elis/program/lib/data/userset.class.php');
require_once($CFG->dirroot .'/elis/program/lib/menuitem.class.php');

require_once($CFG->dirroot .'/elis/core/lib/filtering/lib.php');
require_once($CFG->dirroot .'/elis/program/lib/filtering/clustertree.php');

//needed for execution mode constants
require_once($CFG->dirroot .'/blocks/php_report/php_report_base.php');
require_once($CFG->dirroot .'/blocks/curr_admin/lib.php');

/**
 * Dynamically loads child menu items for a cluster entity (similar to block_curr_admin_load_menu_children
 * but only includes clusters and always includes all children)
 *
 * @param   int             $id                    The entity id
 * @param   int             $parent_cluster_id     The last cluster passed going down the curr_admin tree, or 0 if none
 * @param   int             $parent_curriculum_id  The last curriculum passed going down the curr_admin tree, or 0 if none
 * @param   string          $parent_path           Path of parent curriculum elements in the tree
 * @param   int             $execution_mode        The constant representing the execution mode
 * @return  menuitem array                         The appropriate child items
 */
function clustertree_load_menu_children_cluster($id, $parent_cluster_id, $parent_curriculum_id,
                                                $parent_path, $execution_mode) {

    $result_items = array(new menuitem('root'));

    /*****************************************
     * Cluster - Child Cluster Associations
     *****************************************/
    $cluster_css_class = block_curr_admin_get_item_css_class('cluster_instance');

    //get all child clusters
    $listing = cluster_get_listing('priority, name', 'ASC', 0, 0, '', '', array('parent' => $id));

    if(!empty($listing)) {
        foreach($listing as $item) {
            $params = array('id' => $item->id,
                            'action' => 'viewreport',
                            'execution_mode' => $execution_mode);

            //don't need permissions checking because custom contexts don't support
            //prevents / prohibits
            $cluster_count = cluster_count_records('', '', array('parent' => $item->id));

            $isLeaf = empty($cluster_count);

            $result_items[] = block_curr_admin_get_menu_item('cluster', $item, 'root', $cluster_css_class,
                                                             $item->id, $parent_curriculum_id, $params, $isLeaf, $parent_path);
        }
    }

    return $result_items;
}

//actual data
$data = required_param('data', PARAM_CLEAN);
//report instance id
$instanceid = required_param('instanceid', PARAM_CLEAN);
//constant for report execution mode (scheduled or interactive)
$execution_mode = required_param('execution_mode', PARAM_INT);

$parts = explode('_', $data);

//we are expecting a particular parameter format
if (count($parts) != 5) {
    exit;
}

//retrieve necessary data from parameter
$parent_type       = $parts[0];
$id                = $parts[1];
$parent_cluster    = $parts[2];
$parent_curriculum = $parts[3];
$parent_path       = $parts[4];

//load all necessary children
$result_items = clustertree_load_menu_children_cluster($id, $parent_cluster, $parent_curriculum,
                                                       $parent_path, $execution_mode);

//guaranteed one element because of 'root'
if (count($result_items) > 1) {
    //spit out the JSON object
    $tree = new checkbox_treerepresentation(new menuitemlisting($result_items), $instanceid);
    //$tree = new treerepresentation(new menuitemlisting($result_items));
    echo $tree->get_js_object();
}

