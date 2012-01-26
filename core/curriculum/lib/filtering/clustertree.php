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

require_once($CFG->dirroot.'/curriculum/config.php');
require_once(CURMAN_DIRLOCATION . '/lib/menuitem.class.php');
require_once($CFG->dirroot . '/blocks/curr_admin/lib.php');
require_once(CURMAN_DIRLOCATION . '/lib/contexts.php');
require_once(CURMAN_DIRLOCATION . '/lib/cluster.class.php');
//needed for execution mode constants
require_once($CFG->dirroot.'/blocks/php_report/php_report_base.php');

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
        foreach($list_entries as $key => $value) {
            if($value->parent == $name) {
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
        if(empty($this->children)) {
            $url = $this->get_url_tag();
            //$object->label = $url . $object->label . '</a>';
        }
        */

        $object->children = array();
        if(!empty($this->children)) {
            //recurse as needed
            foreach($this->children as $child) {
                $object->children[] = $child->get_js_object($expanded_sections);
            }

            //flag as expanded when appropriate
            if(in_array($this->name, $expanded_sections)) {
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

        foreach($listing->listing as $key => $value) {
            if(empty($value->parent)) {
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
     *
     * @return  string                   The appropriate markup
     */
    function convert_to_markup($uniqueid, $execution_mode) {
        global $CFG;

        //this prevents handling an empty tree
        if(empty($this->root->children)) {
            return '';
        }

        //use the appropriate css for the menu
        $style = '<style>@import url("' . $CFG->wwwroot . '/lib/yui/treeview/assets/skins/sam/treeview.css");</style>';

        //YUI needs an appropriate div to place the tree in
        $result = $style . "<div id=\"cluster_param_tree_" . $this->instanceid . "_" . $uniqueid . "\" class=\"ygtv-checkbox\"></div>";

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
        $result .= '<noscript>' . $this->root->convert_to_markup() . '</noscript>';

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
    switch($type) {
        case 'cluster':
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
            $display = 'clsname';
            break;
        default:
            break;
    }

    //unique id for this menu item
    $item_id = "{$type}_{$instance->id}";

    //create appropriate page type with correct parameters
    $page = new menuitempage("{$type}page", '', $params);

    //create the menu item
    $result = new menuitem($item_id, $page, $parent, $instance->$display, $css_class, '', true);

    $current_path = '';
    if (in_array($type, array('cluster', 'curriculum', 'course', 'track', 'cmclass'))) {
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
            parent::generalized_filter_type($uniqueid, $alias, $name, $label, $advanced, $field);
        }

    function get_list_condition($fieldname, $list) {
        if (!empty($list) && count($list) > 0) {
            $test_list = implode(',', $list);
            return "$fieldname IN ({$test_list})";
        } else {
            return 'FALSE';
        }
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        global $CFG;

        $full_fieldname = $this->get_full_fieldname();

        if (isset($data['specific_clusterid'])) {
            return "$full_fieldname = {$data['specific_clusterid']}";
        }

        $like = sql_ilike();

        //direct cluster id selection
        $clusterid_condition = $this->get_list_condition('c.id', $data['clusterids']);

        //full list of hierarchically selected entries
        $full_hierarchical_condition = $this->get_list_condition('grandparent_c.id', $data['clrunexpanded_ids']);

        //full unexpanded list
        if (count($data['unexpanded_ids']) > 0) {
            $list = implode(',', $data['unexpanded_ids']);
            $full_unexpanded_condition = "parent_c.id IN ({$list})";
        }  else {
            $full_unexpanded_condition = 'FALSE';
        }

        //full unexpanded and unselected list
        if (count($data['clrunexpanded_ids']) > 0) {
            $list = implode(',', $data['clrunexpanded_ids']);
            $full_clrunexpanded_condition = "eclipse_c.id IN ({$list})";
        } else {
            $full_clrunexpanded_condition = 'FALSE';
        }

        //needed in query to join context table
        $cluster_context_level = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');

        $parent_path = sql_concat('parent_context.path', "'/%'");
        $grandparent_path = sql_concat('grandparent_context.path', "'/%'");
        $eclipse_path = sql_concat('eclipse_context.path', "'/%'");

        //this query gives up exactly the clusters we want
        $sql = "SELECT c.id
                FROM
                {$CFG->prefix}crlm_cluster c
                JOIN {$CFG->prefix}context context
                  ON c.id = context.instanceid
                  AND context.contextlevel = {$cluster_context_level}
                LEFT JOIN {$CFG->prefix}context parent_context
                  ON context.path {$like} {$parent_path}
                  AND parent_context.contextlevel = {$cluster_context_level}
                LEFT JOIN {$CFG->prefix}crlm_cluster parent_c
                  ON parent_context.instanceid = parent_c.id
                  AND {$full_unexpanded_condition}
                LEFT JOIN {$CFG->prefix}context grandparent_context
                  ON parent_context.path {$like} {$grandparent_path}
                  AND grandparent_context.contextlevel = {$cluster_context_level}
                LEFT JOIN {$CFG->prefix}crlm_cluster grandparent_c
                  ON grandparent_context.instanceid = grandparent_c.id
                  AND {$full_hierarchical_condition}
                LEFT JOIN {$CFG->prefix}context eclipse_context
                  ON context.path {$like} {$eclipse_path}
                  AND eclipse_context.contextlevel = {$cluster_context_level}
                LEFT JOIN {$CFG->prefix}crlm_cluster eclipse_c
                  ON eclipse_context.instanceid = eclipse_c.id
                  AND {$full_clrunexpanded_condition}
                WHERE ({$clusterid_condition} AND eclipse_c.id IS NULL)
                  OR  (parent_c.id IS NOT NULL AND grandparent_c.id IS NULL)
                ";

        return "$full_fieldname IN ({$sql})";
    }

    function get_report_parameters($data) {

    }

    /**
     * Retrieves data from the form data
     * @param object $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    function check_data($formdata) {
        global $USER;

        //if we've selected a specific cluster, just use that
        $usingdropdown = $this->_uniqueid . '_usingdropdown';
        if (!empty($formdata->$usingdropdown)) {
            $dropdown = $this->_uniqueid . '_dropdown';

            if (!empty($formdata->$dropdown)) {
                return array('specific_clusterid' => $formdata->$dropdown);
            } else {
                return FALSE;
            }
        }

        //get directly selected clusters
        $listing = $this->_uniqueid . '_listing';

        $data = array();
        $cluster_ids = array();

        if (array_key_exists($listing, $formdata) && $formdata->$listing !== '') {
            $parts = explode(',', $formdata->$listing);

            foreach ($parts as $part) {
                if (strpos($part, 'cluster_') === 0) {
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
        $selected_unexpanded = $this->_uniqueid . '_unexpanded';

        if (array_key_exists($selected_unexpanded, $formdata) && $formdata->$selected_unexpanded !== '') {
            $parts = explode(',', $formdata->$selected_unexpanded);

            foreach ($parts as $part) {
                if (strpos($part, 'cluster_') === 0) {
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
        $selected_clrunexpanded = $this->_uniqueid . '_clrunexpanded';

        if (array_key_exists($selected_clrunexpanded, $formdata) && $formdata->$selected_clrunexpanded !== '') {
            $parts = explode(',', $formdata->$selected_clrunexpanded);

            foreach ($parts as $part) {
                if (strpos($part, 'cluster_') === 0) {
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
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     */
    function setupForm(&$mform) {
        global $USER, $CFG;

        /**
         * CSS includes
         */
        $mform->addElement('html', '<style>@import url("' . $CFG->wwwroot . '/lib/yui/treeview/assets/skins/sam/treeview-skin.css");</style>');

        /**(use "git add" and/or "git commit -a")
         * JavaScript includes
         */

        //include the necessary javascript libraries for the YUI TreeView
        require_js(array('yui_yahoo', 'yui_dom', 'yui_event', 'yui_treeview'));

        //for converting tree representation
        require_js('yui_json');

        //for asynch request dynamic loading
        require_js('yui_connection');

        //include our custom code that handles the YUI Treeview menu
        require_js($CFG->wwwroot . '/curriculum/js/clustertree.js');

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

        $context_result = cm_context_set::for_user_with_capability('cluster', $capability, $USER->id);
        $extrafilters = array('contexts' => $context_result,'parent' => 0);
        $num_records = cluster_count_records('', '', $extrafilters);

        /**
         * TreeView-related work
         */
        //CM entities for placement at the top of the menu
        $cm_entity_pages = array();
        $cm_entity_pages[] = new menuitem('root');

        if($clusters = cluster_get_listing('priority, name', 'ASC', 0, 0, '', '', array('parent' => 0))) {
            foreach($clusters as $cluster) {
                $params = array('id'             => $cluster->id,
                                'action'         => 'viewreport',
                                'execution_mode' => $this->execution_mode);

                $cluster_count = cluster_count_records('', '', array('parent' => $cluster->id));

                $isLeaf = empty($cluster_count);

                $cm_entity_pages[] = test_cluster_tree_get_menu_item('cluster',
                                                                     $cluster,
                                                                     'root',
                                                                     $manageclusters_css_class,
                                                                     $cluster->id,
                                                                     0,
                                                                     $params,
                                                                     $isLeaf);
            }
        }

        $menuitemlisting = new menuitemlisting($cm_entity_pages);
        $tree = new checkbox_treerepresentation($menuitemlisting, $this->options['report_id']);

        $tree_html = $tree->convert_to_markup($this->_uniqueid, $this->execution_mode);
        $params = array($this->options['report_id'],
                        $this->_uniqueid,
                        $this->options['dropdown_button_text'],
                        $this->options['tree_button_text']);
        $param_string = implode('", "', $params);

        /**
         * UI element setup
         */
        require_once($CFG->dirroot . '/curriculum/lib/filtering/equalityselect.php');

        $choices_array = array(0 => get_string('anyvalue', 'filters'));

        //set up cluster listing
        if ($records = cluster_get_listing('name', 'ASC', 0, 0, '', '', array('contexts' => $context_result))) {
            foreach ($records as $record) {
                if ($record->parent == 0) {
                    //merge in child clusters
                    $choices_array[$record->id] = $record->name;
                    $child_array = $this->find_child_clusters($records, $record->id);
                    $choices_array = $this->merge_array_keep_keys($choices_array,$child_array);
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
        $style = '<style>@import url("' . $CFG->wwwroot . '/curriculum/styles.css");</style>';

        //hack the nested fieldset into an html element
        $helptext = get_string('helpprefix2',$this->_filterhelp['1']).' ('.get_string('newwindow').')';
        $helpurl = '/help.php?module='.$this->_filterhelp['2'].'&amp;file='.$this->_filterhelp['0'].'.html&amp;forcelang=';
        $helplink = '<span class="helplink"><a title="'.'"'.
                    ' href="'.$CFG->wwwroot.$helpurl.'"'.
                    ' onclick="this.target=\'popup\'; '.
                    ' return openpopup(\''.$helpurl.'\', \'popup\', \'menubar=0,location=0,scrollbars,resizable,width=500,height=400\', 0);">'.
                    ' <img class="iconhelp" alt="'.$helptext.'" src="'.$CFG->pixpath.'/help.gif"></a></span>';
        $nested_fieldset = '<fieldset class="nested clearfix" id="'.$this->_uniqueid.'_label'.
                           '">'."\n".'<legend class="ftoggler">'.
                            $this->_label.$helplink.
                            '</legend>'."\n";
		$mform->addElement('html',$style.$nested_fieldset);

        //cluster select dropdown
        $mform->addElement('select', $this->_uniqueid . '_dropdown', '', $choices_array);
        //dropdown / cluster tree state storage
        $mform->addElement('hidden', $this->_uniqueid . '_usingdropdown');

        //default to showing dropdown if nothing has been persisted
        $report_shortname = $this->options['report_shortname'];
        $preferences = php_report_filtering_get_user_preferences($report_shortname);
        if (!isset($preferences["php_report_{$report_shortname}/{$this->_uniqueid}_usingdropdown"])) {
            $mform->setDefault($this->_uniqueid . '_usingdropdown', 1);
        }

        //cluster tree
        $mform->addElement('html', $tree_html);
        //list of explicitly selected elements
        $mform->addElement('hidden', $this->_uniqueid . '_listing');
        //list of selected and unexpanded elements
        $mform->addElement('hidden', $this->_uniqueid . '_unexpanded');
        //list of explicitly unselected elements
        $mform->addElement('hidden', $this->_uniqueid . '_clrunexpanded');

        /**
         * Work needed to initialize the state of necessary components
         */
        //parameters needed
        $params = array($this->options['report_id'],
                        $this->_uniqueid,
                        $this->options['dropdown_button_text'],
                        $this->options['tree_button_text']);
        $param_string = implode('", "', $params);

        $mform->addElement('button', $this->_uniqueid . '_toggle', '', array('onclick' => 'clustertree_toggle_tree("' . $param_string . '")'));

        //script to do the work
        $initialize_state_script = '<script type="text/javascript">
                                    clustertree_set_toggle_state("' . $param_string . '");
                                    </script>';
        $mform->addElement('html', $initialize_state_script);

        // close hacked nested fieldset
        $mform->addElement('html','</fieldset>');
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    function get_label($data) {
        global $CURMAN;

        //accumulate the result here
        $results = array();

        //needed for checking parent/child relationships
        $cluster_context_level = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');

        if (isset($data['specific_clusterid'])) {
            //selected a single cluster
            return $this->_label . ':<br/>' . get_field(CLSTTABLE, 'name', 'id', $data['specific_clusterid']) . '<br/>';
        }

        //condition for handling recursively selected entries
        $select_group_condition = '0=1';
        if (!empty($data['unexpanded_ids'])) {
            $select_group_condition = "parent_ctxt.instanceid IN (" . implode(',', $data['unexpanded_ids']) . ")";
        }

        //condition for handling recursively unselected entries
        $unselect_group_condition = '0=1';
        if (!empty($data['clrunexpanded_ids'])) {
            $unselect_group_condition = "parent_ctxt.instanceid IN (" . implode(',', $data['clrunexpanded_ids']) . ")";
        }

        $like = sql_ilike();
        $path = sql_concat('parent_ctxt.path', "'/%'");

        //handle individually selected clusters with child elements
        if (!empty($data['unexpanded_ids'])) {
            //the specific elements we are considering
            $list = implode(',', $data['unexpanded_ids']);

            $sql = "SELECT c.name
                    FROM
                    {$CURMAN->db->prefix_table(CLSTTABLE)} c
                    JOIN {$CURMAN->db->prefix_table('context')} ctxt
                      ON c.id = ctxt.instanceid
                      AND ctxt.contextlevel = {$cluster_context_level}
                    WHERE c.id IN ({$list})
                    AND NOT EXISTS (
                      SELECT *
                      FROM
                      {$CURMAN->db->prefix_table('context')} parent_ctxt
                      WHERE parent_ctxt.contextlevel = {$cluster_context_level}
                      AND
                        (parent_ctxt.path = ctxt.path OR
                         ctxt.path {$like} {$path})
                      AND {$unselect_group_condition}
                    ) AND NOT EXISTS (
                      SELECT *
                      FROM
                      {$CURMAN->db->prefix_table('context')} parent_ctxt
                      WHERE parent_ctxt.contextlevel = {$cluster_context_level}
                      AND ctxt.path {$like} {$path}
                      AND {$select_group_condition}
                    )";

            //append results
            if ($recordset = get_recordset_sql($sql)) {
                while ($record = rs_fetch_next_record($recordset)) {
                    $results[] = $record->name . ' ' . get_string('and_all_children', 'block_curr_admin');
                }
            }
        }

        //handle individually selected clusters without child elements
        if (!empty($data['clusterids'])) {
            $list = implode(',', $data['clusterids']);
            $sql = "SELECT c.name
                    FROM
                    {$CURMAN->db->prefix_table(CLSTTABLE)} c
                    JOIN {$CURMAN->db->prefix_table('context')} ctxt
                      ON c.id = ctxt.instanceid
                      AND ctxt.contextlevel = {$cluster_context_level}
                    WHERE c.id IN ({$list})
                    AND NOT EXISTS (
                      SELECT *
                      FROM
                      {$CURMAN->db->prefix_table('context')} parent_ctxt
                      WHERE parent_ctxt.contextlevel = {$cluster_context_level}
                      AND
                        (parent_ctxt.path = ctxt.path OR
                         ctxt.path {$like} {$path})
                      AND ({$select_group_condition} OR {$unselect_group_condition})
                    )";

            //append results
            if ($recordset = get_recordset_sql($sql)) {
                while ($record = rs_fetch_next_record($recordset)) {
                    $results[] = $record->name;
                }
            }
        }

        //nothing selected
        if (count($results) == 0) {
            return $this->_label . ': ' . get_string('cluster_tree_na', 'block_curr_admin') . '<br/>';
        }

        //return results as a comma-separated list
        return $this->_label . ':<br/>' . implode(', ', $results) . '<br/>';
    }

    /**
     * Returns the child clusters of the given parent
     * @param array $records complete cluster objects array
     * @param int $parentid the parent cluster id
     * @param int $indent the parent cluster indentation level
     * @return array child clusters array
     */
    function find_child_clusters($records, $parentid, $indent=0) {
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

        foreach ($array2 as $array2_key=>$array2_value) {
            $merged_array[$array2_key] = $array2_value;
        }

        return $merged_array;
    }
}

?>
