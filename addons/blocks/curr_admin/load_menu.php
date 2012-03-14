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

require_once('../../config.php');
require_once($CFG->dirroot.'/blocks/curr_admin/lib.php');
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(elispm::lib('data/userset.class.php'));
require_once(elispm::lib('menuitem.class.php'));

$data = required_param('data', PARAM_CLEAN);

$parts = explode('_', $data);

//we are expecting a particular parameter format
if(count($parts) != 5) {
    exit;
}

//retrieve necessary data from parameter
$parent_type = $parts[0];
$id = $parts[1];
$parent_cluster = $parts[2];
$parent_curriculum = $parts[3];
$parent_path = $parts[4];

//load all necessary children
$result_items = block_curr_admin_load_menu_children($parent_type, $id, $parent_cluster, $parent_curriculum, $parent_path);

//guaranteed one element because of 'root'
if(count($result_items) > 1) {
    //spit out the JSON object
    $tree = new treerepresentation(new menuitemlisting($result_items));
    echo $tree->get_js_object();
}


?>