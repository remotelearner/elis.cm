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

    require_once '../config.php';
    require_once '../lib/cluster.class.php';

    if (isset($_SERVER['REMOTE_ADDR'])) {
        die('no web access');
    }

    if ($argc > 1) {
        die(print_usage());
    }

    mtrace("Begin cluster fixes...");

    $clusters = cluster_get_listing('id', 'ASC', 0);

    $clusters_fixed_cnt = 0;
    foreach ($clusters as $clusid => $clusdata) {
        if ($clusdata->parent > 0) {
            $select = "id='{$clusdata->parent}'";
            $parent_cnt = $CURMAN->db->count_records_select(CLSTTABLE, $select);
            if ($parent_cnt < 1) {
                mtrace('Cluster ID:'.$clusid.' Name:'.$clusdata->name.' converted to top-level');
                $newclusdata = new stdClass;
                $newclusdata->id = $clusdata->id;
                $newclusdata->parent = 0;
                $newclusdata->depth = 1;

                // Update the records in crlm_cluster table
                $result = update_record(CLSTTABLE, $newclusdata);

                // Blank out the depth and path for associated records and child records in context table
                $cluster_context_level = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');
                $cluster_context_instance = get_context_instance($cluster_context_level, $clusid);
                $instance_id = $cluster_context_instance->id;
                $sql = "UPDATE {$CFG->prefix}context
                        SET depth=0, path=NULL
                        WHERE id={$instance_id} OR path LIKE '%/{$instance_id}/%'";
                $feedback = "";
                execute_sql($sql, $feedback);

                $clusters_fixed_cnt++;
            }
        }
    }

    if ($clusters_fixed_cnt > 0) {
        mtrace("Rebuilding context paths...");
        build_context_path();
    } else {
        mtrace("No orphaned clusters found!");
    }

    mtrace("Done.");

    exit;

    function print_usage() {
        mtrace('Usage: ' . basename(__FILE__) . "\n");
    }

?>
