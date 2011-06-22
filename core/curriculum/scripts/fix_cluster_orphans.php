<?php // $Id: fix_cluster_orphans.php,v 1.0 2010/12/01 10:30:23 mvidberg Exp $

/**
 * Fix ELIS sub-clusters that have had their parent clusters deleted by changing them to top-level clusters.
 *
 * @version   $Id: fix_cluster_orphans.php,v 1.0 2010/12/01 10:30:23 mvidberg Exp $
 * @package   codelibrary
 * @copyright 2010 Remote Learner - http://www.remote-learner.net/
 * @author    Marko Vidberg <marko.vidberg@remote-learner.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
                $clusdata->parent = 0;
                $clusdata->depth = 1;
                
                // Update the records in crlm_cluster table
                $result = update_record(CLSTTABLE, $clusdata);
                
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
