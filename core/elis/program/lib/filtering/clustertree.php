<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @subpackage pm-filtering
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot .'/elis/program/lib/setup.php');
require_once($CFG->dirroot .'/elis/program/lib/contexts.php');
require_once($CFG->dirroot .'/elis/program/lib/menuitem.class.php');

require_once($CFG->dirroot .'/elis/program/lib/data/userset.class.php');

//needed for execution mode constants
require_once($CFG->dirroot .'/blocks/php_report/php_report_base.php');

//needed for block_curr_admin_*() functions
require_once($CFG->dirroot .'/blocks/curr_admin/lib.php');

/**
 * checkbox node
 */
class checkbox_treerepresentationnode extends treerepresentationnode {
    /**
     * Treerepresentationnode constructor
     *
     * @param  string              $name    The name that represents this node
     * @param  treerepresentation  $parent  The containing treerepresentation
     *
     */
    function checkbox_treerepresentationnode($name, &$parent) {
        $this->name = $name;
        $this->parent =& $parent;

        $list_entries = $this->parent->get_listing_entries();

        //recursively build the tree of children
        foreach ($list_entries as $key => $value) {
            if ($value->parent == $name) {
                $this->children[] = new checkbox_treerepresentationnode($value->name, $parent);
            }
        }

    }

    /**
     * Recursively constructs the object representing this tree node
     *
     * @param   array   $expanded_sections  The name of the sections that are currently expanded
     *
     * @return  object                      The appropriate object
     */
    function get_js_object($expanded_sections) {
        $object = new stdClass;

        //display text
        $object->label = $this->parent->get_listing_entry($this->name)->title;

        //never want links
        /*
        if (empty($this->children)) {
            $url = $this->get_url_tag();
            //$object->label = $url . $object->label . '</a>';
        }
        */

        $object->children = array();
        if (!empty($this->children)) {
            //recurse as needed
            foreach ($this->children as $child) {
                $object->children[] = $child->get_js_object($expanded_sections);
            }

            //flag as expanded when appropriate
            if (in_array($this->name, $expanded_sections)) {
                $object->expanded = true;
            }
        }

        //style
        $object->labelStyle = $this->parent->get_listing_entry($this->name)->style;

        //parent entity info
        $object->contentElId = $this->calculate_identifier();

        //make this a leaf node if appropriate
        $object->isLeaf = empty($this->parent->get_listing_entry($this->name)->isLeaf) ? false : true;

        return $object;
    }
}

/**
 * Representation of a tree containing checkbox nodes
 */
class checkbox_treerepresentation extends treerepresentation {
    /**
     * Treerepresentation constructor
     *
     * @param  menuitemlisting  $listing  The list of all menu items
     *
     */
    function checkbox_treerepresentation($listing, $instanceid) {

        $this->listing = $listing;
        $this->instanceid = $instanceid;

        foreach ($listing->listing as $key => $value) {
            if (empty($value->parent)) {
                $this->root = new checkbox_treerepresentationnode($value->name, $this);
                break;
            }
        }

        $this->root->prune();
    }

    /**
     * Converts the tree representation to html markup
     * @param   string  $uniqueid        The unique id of the filter element
     * @param   int     $execution_mode  The mode in which the report is being run
     * @uses    $CFG
     * @return  string                   The appropriate markup
     */
    function convert_to_markup($uniqueid = '', $execution_mode = null) {
        global $CFG;

        //this prevents handling an empty tree
        if (empty($this->root->children)) {
            return '';
        }

        //use the appropriate css for the menu
        $style = '<style>@import url("'.$CFG->wwwroot.'/lib/yui/2.9.0/build/treeview/assets/skins/sam/treeview.css");</style>'; // TBV

        //YUI needs an appropriate div to place the tree in
        $result = $style ."<div id=\"cluster_param_tree_". $this->instanceid ."_". $uniqueid ."\" class=\"ygtv-checkbox felement\"></div>";

        //obtain the actual tree object
        $js_object = $this->get_js_object();

        //render the tree when the page is loaded
        $result .= "<script type=\"text/javascript\">
//<![CDATA[
                    //unique variable name to prevent conflict with curr admin block
                    var clustertree_object = $js_object;
                    var wwwroot = \"{$CFG->wwwroot}\";
                    //YAHOO.util.Event.onDOMReady(function() {
                                                        clustertree_render_tree('" . $this->instanceid . "', '" . $uniqueid .
                                                        "', clustertree_object, " . $execution_mode . ");
                    //                                       });
//]]>
                    </script>";

        //handle non-js case
        $result .= '<noscript>'. $this->root->convert_to_markup() .'</noscript>';

        return $result;
    }
}

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
 * @return  menuitem                         The appropriate menu item
 */
function test_cluster_tree_get_menu_item($type, $instance, $parent, $css_class, $parent_cluster_id, $parent_curriculum_id, $params = array(), $isLeaf = false) {
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
            error_log("clustertree.php::test_cluster_tree_get_menu_item() invalid type: {$type}");
            break;
    }

    //unique id for this menu item
    $item_id = "{$type}_{$instance->id}";

    //create appropriate page type with correct parameters
    $page = new menuitempage("{$type}page", '', $params);

    //create the menu item
    $result = new menuitem($item_id, $page, $parent, $instance->$display, $css_class, '', true);

    $current_path = '';
    if (in_array($type, array('cluster', 'curriculum', 'course', 'track', 'cmclass', 'userset', 'pmclass'))) {
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

class generalized_filter_clustertree extends generalized_filter_type {
    var $options;
    var $data = null; // ELIS-5348: chache data in get_sql_filter()
    var $ids  = array();

    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     * @param array $options select options
     */
    function generalized_filter_clustertree($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {
        $this->options = $options;
        if (!array_key_exists('fieldset', $options)) {
            $this->options['fieldset'] = true;
        }
        parent::generalized_filter_type($uniqueid, $alias, $name, $label, $advanced, $field);
    }

    function get_list_condition($fieldname, $list) {
        if (!empty($list) && count($list) > 0) {
            $test_list = implode(',', $list);
            return "{$fieldname} IN ({$test_list})";
        } else {
            return 'FALSE';
        }
    }

    /**
     * Returns the condition to be used with SQL where
     * @param  array $data filter settings
     * @uses   $CFG
     * @uses   $DB
     * @return array       the filtering condition with optional params
     *                     or null if the filter is disabled
     */
    function get_sql_filter($data) {
        static $counter = 0; // required for multiple calls to get_filter_condition in reports!

        $full_fieldname = $this->get_full_fieldname();
        if (empty($full_fieldname)) {
            return null;
        }

        if ($data != $this->data) {
            global $CFG, $DB;
            //dependencies for queries
            require_once($CFG->dirroot .'/elis/program/lib/setup.php');
            require_once($CFG->dirroot .'/elis/program/lib/data/userset.class.php');
            $this->data = $data;
            $this->ids = array();

            //determine if we are filtering on a user id rather than a cluster id
            $filter_on_user_records = !empty($this->options['filter_on_user_records']);

            if (isset($data['specific_clusterid'])) {
                $param_name = 'clustree_clusterid'. $counter++;
                $params = array($param_name => $data['specific_clusterid']);
                if ($filter_on_user_records) {
                    //validate user id against cluster assignments
                    $this->ids = $DB->get_records(clusterassignment::TABLE,
                                          array('clusterid' => $data['specific_clusterid']),
                                          '', 'DISTINCT userid');
                } else {
                    $this->ids[$data['specific_clusterid']] = $data['specific_clusterid'];
                    //validate cluster id against specific value
                    return array("{$full_fieldname} = :{$param_name}", $params);
                }
            } else {

                //direct cluster id selection
                $clusterid_condition = $this->get_list_condition('c.id', $data['clusterids']);

                //full list of hierarchically selected entries
                $full_hierarchical_condition = $this->get_list_condition('grandparent_context.instanceid', $data['clrunexpanded_ids']);

                //full unexpanded list
                $full_unexpanded_condition = $this->get_list_condition('parent_context.instanceid', $data['unexpanded_ids']);

                //full unexpanded and unselected list
                $full_clrunexpanded_condition = $this->get_list_condition('eclipse_context.instanceid', $data['clrunexpanded_ids']);

                //needed in query to join context table
                $cluster_context_level = CONTEXT_ELIS_USERSET;

                //$params = array();
                $param_cpath = 'clustree_cpath_a'. $counter;
                $param_pcpath = 'clustree_pcpath'. $counter;
                $param_cpath2 = 'clustree_cpath_b'. $counter;

                // ELIS-5861 -- Got named parameters working with sql_concat() -- tested in /elis/program/phpunit/testFilters
                $cpath_like = $DB->sql_like('context.path', $DB->sql_concat('parent_context.path', ':c_path'), false); // TBV: case insensitive?
                $params['c_path'] = '/%';

                $pcpath_like = $DB->sql_like('parent_context.path', $DB->sql_concat('grandparent_context.path', ':pc_path'), false); // TBV: case insensitive?
                $params['pc_path'] = '/%';

                $cpath2_like = $DB->sql_like('context.path', $DB->sql_concat('eclipse_context.path', ':c2_path'), false); // TBV: case insensitive?
                $params['c2_path'] = '/%';

                $param_ccl1 = 'clustree_context_a'. $counter;
                $param_ccl2 = 'clustree_context_b'. $counter;
                $param_ccl3 = 'clustree_context_c'. $counter;
                $param_ccl4 = 'clustree_context_d'. $counter;
                $params[$param_ccl1] = $cluster_context_level;
                $params[$param_ccl2] = $cluster_context_level;
                $params[$param_ccl3] = $cluster_context_level;
                $params[$param_ccl4] = $cluster_context_level;
                $counter++;

                //this query gives us exactly the user sets we want

                if ($filter_on_user_records) {
                    //connect cluster assignment user id to main query userid
                    $column = 'clstasgn.userid';
                    //only display records if there is a related cluster assignment
                    $user_join = 'JOIN {'. clusterassignment::TABLE .'} clstasgn
                                    ON c.id = clstasgn.clusterid';
                } else {
                    //connect cluster id to the main query cluster id
                    $column = 'c.id';
                    $user_join = '';
                }

                //it essentially consists of two parts
                //part 1: include all the user sets directly selected that have not been
                //cleared out at a parent context
                //part 2: include all user sets selected recursively through an unexpanded
                //parent that have not been cleared out at a parent context
                $sql = "SELECT DISTINCT {$column}
                          FROM {". userset::TABLE ."} c
                          JOIN {context} context
                            ON c.id = context.instanceid
                           AND context.contextlevel = :{$param_ccl1}
                        {$user_join}
                     LEFT JOIN {context} parent_context
                            ON {$cpath_like}
                           AND parent_context.contextlevel = :{$param_ccl2}
                           AND {$full_unexpanded_condition}
                     LEFT JOIN {context} grandparent_context
                            ON {$pcpath_like}
                           AND grandparent_context.contextlevel = :{$param_ccl3}
                           AND {$full_hierarchical_condition}
                     LEFT JOIN {context} eclipse_context
                            ON {$cpath2_like}
                           AND eclipse_context.contextlevel = :{$param_ccl4}
                           AND {$full_clrunexpanded_condition}
                         WHERE ({$clusterid_condition} AND eclipse_context.id IS NULL)
                            OR (parent_context.instanceid IS NOT NULL AND grandparent_context.id IS NULL)
                        ";

                $this->ids = $DB->get_records_sql($sql, $params);
            }
        }

        $ids = array_keys($this->ids);
        return array($this->get_list_condition($full_fieldname, $ids), array());
    }

    function get_report_parameters($data) {
        // TBD?
    }

    /**
     * Retrieves data from the form data
     * @param object $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    function check_data($formdata) {
        global $USER;

        //if we've selected a specific cluster, just use that
        $usingdropdown = $this->_uniqueid .'_usingdropdown';
        if (!empty($formdata->$usingdropdown)) {
            $dropdown = $this->_uniqueid .'_dropdown';

            if (!empty($formdata->$dropdown)) {
                return array('specific_clusterid' => $formdata->$dropdown);
            } else {
                return FALSE;
            }
        }

        //get directly selected clusters
        $listing = $this->_uniqueid .'_listing';

        $data = array();
        $cluster_ids = array();

        if (array_key_exists($listing, $formdata) && $formdata->$listing !== '') {
            $parts = explode(',', $formdata->$listing);
            foreach ($parts as $part) {
                if (strpos($part, 'userset_') === 0) {
                    $inner_parts = explode('_', $part);
                    if (isset($inner_parts[1])) {
                        $cluster_ids[] = $inner_parts[1];
                    }
                }
            }
        }

        //get clusters that are selected but aren't expanded and should have their selection
        //down to child elements
        $unexpanded_ids = array();
        $selected_unexpanded = $this->_uniqueid .'_unexpanded';

        if (array_key_exists($selected_unexpanded, $formdata) && $formdata->$selected_unexpanded !== '') {
            $parts = explode(',', $formdata->$selected_unexpanded);
            foreach ($parts as $part) {
                if (strpos($part, 'userset_') === 0) {
                    $inner_parts = explode('_', $part);
                    if (isset($inner_parts[1])) {
                        $unexpanded_ids[] = $inner_parts[1];
                    }
                }
            }
        }

        //get clusters that are unselected and should have this unselection propagated back
        //down to child elements
        $clrunexpanded_ids = array();
        $selected_clrunexpanded = $this->_uniqueid .'_clrunexpanded';

        if (array_key_exists($selected_clrunexpanded, $formdata) && $formdata->$selected_clrunexpanded !== '') {
            $parts = explode(',', $formdata->$selected_clrunexpanded);
            foreach ($parts as $part) {
                if (strpos($part, 'userset_') === 0) {
                    $inner_parts = explode('_', $part);
                    if (isset($inner_parts[1])) {
                        $clrunexpanded_ids[] = $inner_parts[1];
                    }
                }
            }
        }

        //put the lists in a result object and return it
        $data['clusterids'] = $cluster_ids;
        $data['unexpanded_ids'] = $unexpanded_ids;
        $data['clrunexpanded_ids'] = $clrunexpanded_ids;
        return $data;
    }

    /**
     * Gets the cluster listing for the drop-down menu
     * @param mixed  $contexts
     * @return array cluster listing
     */
    function cluster_dropdown_get_listing($contexts = null) {
        global $DB;

        //ob_start();
        //var_dump($contexts);
        //$tmp = ob_get_contents();
        //ob_end_clean();
        $sql = 'SELECT * FROM {'. userset::TABLE .'}';
        $params = array();

        if ($contexts) {
            $filter_obj = $contexts->get_filter('id', 'cluster');
            $filter_sql = $filter_obj->get_sql();
            if (isset($filter_sql['where'])) {
                $sql .= " WHERE {$filter_sql['where']}";
                $params = $filter_sql['where_parameters'];
            }
        }

        $sql .= ' ORDER BY depth ASC, name ASC';
        //error_log("cluster_dropdown_get_listing(); SQL => {$sql}; contextset = {$tmp}");
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     * @uses  $CFG
     * @uses  $OUTPUT
     * @uses  $PAGE
     * @uses  $USER
     */
    function setupForm(&$mform) {
        global $CFG, $OUTPUT, $PAGE, $USER;

        // Javascript for cluster dropdown onchange event
        $cluster_group_separator = '------------------------------';
        $js = '
<script type="text/javascript">
//<![CDATA[
    function dropdown_separator(selectelem) {
        /* alert("dropdown_separator(" + selectelem.selectedIndex + ")"); */
        if (selectelem.options[selectelem.selectedIndex].value < 0) {
            return 0;
        }
        return selectelem.selectedIndex;
    }
//]]>
</script>
';

        /**
         * CSS includes
         */
        $mform->addElement('html', '<style>@import url("'.$CFG->wwwroot.'/lib/yui/2.9.0/build/treeview/assets/skins/sam/treeview-skin.css");</style>'.$js);

        //include our custom code that handles the YUI Treeview menu
        //$PAGE->requires->js('/elis/program/js/clustertree.js');
        echo "<script type=\"text/javascript\" src=\"{$CFG->wwwroot}/elis/program/js/clustertree.js\"></script>";

        /**
         * Get set up necessary CSS classes
         */
        $manageclusters_css_class = block_curr_admin_get_item_css_class('manageclusters');
        $cluster_css_class = block_curr_admin_get_item_css_class('cluster_instance');

        //figure out which capability to check
        if ($this->execution_mode == php_report::EXECUTION_MODE_SCHEDULED) {
            $capability = 'block/php_report:schedule';
        } else {
            $capability = 'block/php_report:view';
        }

        $context_result = pm_context_set::for_user_with_capability('cluster', $capability, $USER->id);

        /**
         * TreeView-related work
         */
        //CM entities for placement at the top of the menu
        $cm_entity_pages = array();
        $cm_entity_pages[] = new menuitem('root');

        if ($clusters = cluster_get_listing('priority, name', 'ASC', 0, 0, '', '', array('parent' => 0))) {
            foreach ($clusters as $cluster) {
                $params = array('id'             => $cluster->id,
                                'action'         => 'viewreport',
                                'execution_mode' => $this->execution_mode);

                $cluster_count = cluster_count_records('', '', array('parent' => $cluster->id));

                $isLeaf = empty($cluster_count);

                $cm_entity_pages[] = test_cluster_tree_get_menu_item('cluster',
                                         $cluster, 'root', $manageclusters_css_class,
                                         $cluster->id, 0, $params, $isLeaf);
            }
        }

        $menuitemlisting = new menuitemlisting($cm_entity_pages);
        $tree = new checkbox_treerepresentation($menuitemlisting, $this->options['report_id']);

        /**
         * UI element setup
         */
        require_once($CFG->dirroot .'/elis/core/lib/filtering/equalityselect.php');

        $choices_array = array(0 => get_string('anyvalue', 'filters'));

        //set up cluster listing
        if ($records = $this->cluster_dropdown_get_listing($context_result)) {
            foreach ($records as $record) {
                if (empty($choices_array[$record->id])) {
                    if (count($choices_array) > 1) {
                        $choices_array[-$record->id] = $cluster_group_separator;
                    }
                    $ancestors = $record->depth - 1;
                    // shorten really long cluster names
                    $name = (strlen($record->name) > 100)
                            ? substr($record->name, 0, 100) .'...'
                            : $record->name;
                    $choices_array[$record->id] = $ancestors
                            ? str_repeat('- ', $ancestors) . $name
                            : $name;
                    //merge in child clusters
                    $child_array = $this->find_child_clusters($records, $record->id, $ancestors);
                    $choices_array = $this->merge_array_keep_keys($choices_array, $child_array);
                }
            }
        }

        //get help text
        if (isset($this->options['help'])) {
            $this->_filterhelp = $this->options['help'];
        } else {
            $this->_filterhelp = null;
        }

        //add filterhelp and label to this filter
        //import required css for the fieldset
        $style = '<style>@import url("'. $CFG->wwwroot .'/elis/program/styles.css");</style>';

        $helplink = '';
        $nested_fieldset = '';
        $title = '';
        if ($this->options['fieldset']) {
            $nested_fieldset = '<fieldset class="nested clearfix" id="'
                               . $this->_uniqueid ."_label\">\n";
        } else {
            $title = $this->_label . $helplink .'&nbsp;';
        }
        $legend = '<legend class="ftoggler">'. $this->_label //. $helplink
                  ."</legend>\n";

        $mform->addElement('html', $style . $nested_fieldset . $legend);
        $mform->addElement('static', $this->_uniqueid .'_help', '');

        // cluster select dropdown
        $selectparams = array('onchange' => 'this.selectedIndex = dropdown_separator(this);');
        $mform->addElement('select', $this->_uniqueid .'_dropdown', $title, $choices_array, $selectparams);

        //dropdown / cluster tree state storage
        $mform->addElement('hidden', $this->_uniqueid .'_usingdropdown');
        // Must use addHelpButton() to NOT open help link on page, but in popup!
        $mform->addHelpButton($this->_uniqueid .'_dropdown', $this->_filterhelp[0], $this->_filterhelp[2] /* , $this->_filterhelp[1] */); // TBV

        //default to showing dropdown if nothing has been persisted
        $report_shortname = $this->options['report_shortname'];
        $preferences = php_report_filtering_get_user_preferences($report_shortname);
        if (!isset($preferences["php_report_{$report_shortname}/{$this->_uniqueid}_usingdropdown"])) {
            $mform->setDefault($this->_uniqueid .'_usingdropdown', 1);
        }

        $module = array(
            'name'     => 'clustertree',
            'fullpath' => '/elis/program/js/clustertree_module.js',
            'requires' => array(
                'yui2-connection',
                'yui2-dom',
                'yui2-event',
                'yui2-json',
                'yui2-treeview'
            )
        );
        $PAGE->requires->js_module($module);
        $initcallopts = array(
            $CFG->httpswwwroot,
            $tree->instanceid,
            $this->_uniqueid,
            $tree->get_js_object(),
            $this->execution_mode,
            $this->options['report_id'],
            $this->options['dropdown_button_text'],
            $this->options['tree_button_text']
        );
        $PAGE->requires->js_init_call('M.clustertree.init_tree', $initcallopts, true, $module);

        // cluster tree
        $clustertreehtml = '<div class="fitem"><div class="fitemtitle"></div>'.
                           '<style>@import url("'.$CFG->wwwroot.'/lib/yui/2.9.0/build/treeview/assets/skins/sam/treeview.css");</style>'.
                           '<div id="cluster_param_tree_'.$tree->instanceid.'_'.$this->_uniqueid.'" class="ygtv-checkbox felement"></div>'.
                           '</div>';
        $mform->addElement('html', $clustertreehtml);

        //list of explicitly selected elements
        $mform->addElement('hidden', $this->_uniqueid .'_listing');
        //list of selected and unexpanded elements
        $mform->addElement('hidden', $this->_uniqueid .'_unexpanded');
        //list of explicitly unselected elements
        $mform->addElement('hidden', $this->_uniqueid .'_clrunexpanded');

        /**
         * Work needed to initialize the state of necessary components
         */
        //parameters needed
        $params = array($this->options['report_id'],
                        $this->_uniqueid,
                        $this->options['dropdown_button_text'],
                        $this->options['tree_button_text']);
        $param_string = implode('", "', $params);

        $mform->addElement('button', $this->_uniqueid .'_toggle', '',
                           array('onclick' =>
                                 'clustertree_toggle_tree("'. $param_string .'")'));

        // close hacked nested fieldset
        if ($this->options['fieldset']) {
            $mform->addElement('html','</fieldset>');
        }
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param  array $data filter settings
     * @uses   $DB
     * @return string      active filter label
     */
    function get_label($data) {
        global $DB;
        //accumulate the result here
        $results = array();

        if (isset($data['specific_clusterid'])) {
            //selected a single cluster
            return $this->_label .':<br/>'. $DB->get_field(userset::TABLE, 'name', array('id' => $data['specific_clusterid'])) .'<br/>';
        }

        //condition for handling recursively selected entries
        $select_group_condition = '0 = 1';
        if (!empty($data['unexpanded_ids'])) {
            $select_group_condition = "parent_ctxt.instanceid IN (" . implode(',', $data['unexpanded_ids']) . ")";
        }

        //condition for handling recursively unselected entries
        $unselect_group_condition = '0 = 1';
        if (!empty($data['clrunexpanded_ids'])) {
            $unselect_group_condition = "parent_ctxt.instanceid IN (" . implode(',', $data['clrunexpanded_ids']) . ")";
        }

        $ctxtpath_like1 = $DB->sql_like('ctxt.path', $DB->sql_concat('parent_ctxt.path', "'/%'"), false); // TBV: case insensitive
        $ctxtpath_like2 = $DB->sql_like('ctxt.path', $DB->sql_concat('parent_ctxt.path', "'/%'"), false); // TBV: case insensitive
        $params = array();
        //$params['ctxtpath1'] = $DB->sql_concat('parent_ctxt.path', "'/%'");
        //$params['ctxtpath2'] = $DB->sql_concat('parent_ctxt.path', "'/%'");

        //handle individually selected clusters with child elements
        if (!empty($data['unexpanded_ids'])) {
            //the specific elements we are considering
            $list = implode(',', $data['unexpanded_ids']);
            $params['cluster_context_level1'] = CONTEXT_ELIS_USERSET;
            $params['cluster_context_level2'] = CONTEXT_ELIS_USERSET;
            $params['cluster_context_level3'] = CONTEXT_ELIS_USERSET;
            $sql = 'SELECT c.name FROM {'. userset::TABLE ."} c
                    JOIN {context} ctxt
                      ON c.id = ctxt.instanceid
                      AND ctxt.contextlevel = :cluster_context_level1
                    WHERE c.id IN ({$list})
                    AND NOT EXISTS (
                      SELECT * FROM {context} parent_ctxt
                      WHERE parent_ctxt.contextlevel = :cluster_context_level2
                      AND (parent_ctxt.path = ctxt.path OR {$ctxtpath_like1})
                      AND {$unselect_group_condition}
                    )
                    AND NOT EXISTS (
                      SELECT * FROM {context} parent_ctxt
                      WHERE parent_ctxt.contextlevel = :cluster_context_level3
                      AND {$ctxtpath_like2} AND {$select_group_condition}
                    )";

            //append results
            if ($recordset = $DB->get_recordset_sql($sql, $params)) {
                foreach ($recordset as $record) {
                    $results[] = $record->name .' '. get_string('and_all_children', 'elis_program');
                }
                $recordset->close();
            }
        }

        //handle individually selected clusters without child elements
        if (!empty($data['clusterids'])) {
            $list = implode(',', $data['clusterids']);
            $params['cluster_context_level1'] = CONTEXT_ELIS_USERSET;
            $params['cluster_context_level2'] = CONTEXT_ELIS_USERSET;
            $sql = 'SELECT c.name FROM {'. userset::TABLE ."} c
                    JOIN {context} ctxt
                      ON c.id = ctxt.instanceid
                      AND ctxt.contextlevel = :cluster_context_level1
                    WHERE c.id IN ({$list})
                    AND NOT EXISTS (
                      SELECT * FROM {context} parent_ctxt
                      WHERE parent_ctxt.contextlevel = :cluster_context_level2
                      AND (parent_ctxt.path = ctxt.path OR {$ctxtpath_like1})
                      AND ({$select_group_condition} OR {$unselect_group_condition})
                    )";

            //append results
            if ($recordset = $DB->get_recordset_sql($sql, $params)) {
                foreach ($recordset as $record) {
                    $results[] = $record->name;
                }
                $recordset->close();
            }
        }

        //nothing selected
        if (count($results) == 0) {
            return $this->_label .': '. get_string('cluster_tree_na', 'elis_program') .'<br/>';
        }

        //return results as a comma-separated list
        return $this->_label .':<br/>'. implode(', ', $results) .'<br/>';
    }

    /**
     * Returns the child clusters of the given parent
     * @param array $records complete cluster objects array
     * @param int $parentid the parent cluster id
     * @param int $indent the parent cluster indentation level
     * @return array child clusters array
     */
    function find_child_clusters($records, $parentid, $indent = 0) {
        $choices_array = array();
        $indent++;

        if ($indent < 1000) { // avoid infinite recursion
            foreach ($records as $record) {
                if ($record->parent == $parentid) {
                    // shorten really long cluster names
                    $name = (strlen($record->name) > 100)
                          ? substr($record->name,0,100) . '...'
                          : $record->name;
                    $choices_array[$record->id] = str_repeat('- ', $indent) . $name;

                    // recursively find child clusters
                    $child_array = $this->find_child_clusters($records, $record->id, $indent);
                    $choices_array = $this->merge_array_keep_keys($choices_array, $child_array);
                }
            }
        }

        return $choices_array;
    }

    /**
     * Returns the merged array of two given arrays without renumbering the key values
     * @param array $array1 first array
     * @param array $array2 second array
     * @return array the merged array
     */
    function merge_array_keep_keys($array1, $array2) {
        $merged_array = $array1;

        foreach ($array2 as $array2_key => $array2_value) {
            $merged_array[$array2_key] = $array2_value;
        }

        return $merged_array;
    }


    /**
     * Takes a set of submitted values and retuns this filter's default values
     * for them in the same structure (used to reset the filtering form)
     */
    function get_default_values($filter_data) {
        //our data map of field shortnames to values
        $default_values = array();

        //dropdown element shortname
        $dropdown_shortname = $this->_uniqueid.'_usingdropdown';

        //set all fields to the default checkbox value of zero
        foreach ($filter_data as $key => $value) {
            if ($key == $dropdown_shortname) {
                $default_values[$key] = 1;
            } else {
                $default_values[$key] = '';
            }
        }

        //return our data mapping
        return $default_values;
    }

}

