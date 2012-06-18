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
 * @subpackage programmanagement-scripts
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

if (!isset($_SERVER['REMOTE_ADDR'])) {
    define('CLI_SCRIPT', true);
}

require_once(dirname(__FILE__) .'/../lib/setup.php');
require_once(elispm::lib('data/userset.class.php'));

global $DB;

if (isset($_SERVER['REMOTE_ADDR'])) {
    die('no web access');
}

if ($argc > 1) {
    die(print_usage());
}

mtrace("Begin User Set fixes...");
$clusters = cluster_get_listing('id');
$clusters_fixed_cnt = 0;
foreach ($clusters as $clusid => $clusdata) {
    if ($clusdata->parent > 0) {
        $select = "id = '{$clusdata->parent}'";
        $parent_cnt = $DB->count_records_select(userset::TABLE, $select);
        if ($parent_cnt < 1) {
            mtrace('User Set ID:'. $clusid .' Name:'. $clusdata->name .' converted to top-level');
            $newclusdata = new stdClass;
            $newclusdata->id = $clusdata->id;
            $newclusdata->parent = 0;
            $newclusdata->depth = 1;

            // Update the records in crlm_cluster table
            $result = $DB->update_record(userset::TABLE, $newclusdata);

            // Blank out the depth and path for associated records and child records in context table
            $cluster_context_instance = context_elis_userset::instance($clusid);
            $instance_id = $cluster_context_instance->id;
            $sql = "UPDATE {context}
                       SET depth = 0, path = NULL
                     WHERE id = {$instance_id} OR path LIKE '%/{$instance_id}/%'";
            $DB->execute($sql);
            $clusters_fixed_cnt++;
        }
    }
}

if ($clusters_fixed_cnt > 0) {
    mtrace("Rebuilding context paths...");
    build_context_path();
} else {
    mtrace("No orphaned User Sets found!");
}

mtrace("Done.");

//exit;

function print_usage() {
    mtrace('Usage: '. basename(__FILE__) ."\n");
}

