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
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

/**
 * This class represents a list of menu items
 */
class menuitemlisting {

    //the actual array of menuitems
    var $listing;

    function menuitemlisting($listing = array()) {
        $this->listing = $listing;

        //expand the tree based on where you currently are
        $this->append_path_items();

        //remove any elements that should not be visible
        $this->filter_permissions();

        //convert all numeric keys to associative ones for later use
        $this->convert_to_assoc();
    }

    /**
     * Filters the elements based on capabilities in the supplied context
     *
     */
    private function filter_permissions() {
        global $CFG;

        //require_once(elispm::file('jasperreportpage.class.php'));

        //determine the shortnames of all jasper reports
        //$jasper_reports = array_keys(jasperreportpage::$reports);

        //iterate through the list of pages
        foreach ($this->listing as $id => $value) {

            //make sure we're a Jasper report page or a standard page
            if ($value->is_page_link()) {
                $page_instance = $value->page->page_instance;

                //remove the element if not accessible
                if (!$page_instance->can_do()) {
                    unset($this->listing[$id]);
                }

            }

        }

    }

    /**
     * Converts the list of all menu items to an associative array based on element names
     *
     */
    private function convert_to_assoc() {
        $result = array();
        foreach ($this->listing as $key => $value) {
            $result[$value->name] = $value;
        }
        $this->listing = $result;

    }

    /**
     * Turn all pages that have no children into leaf nodes
     * (make sure to never call this on elements used in dynamic loading)
     *
     * @param  menuitem array  $pages  The complete listing of pages
     */
    public static function flag_leaf_nodes(&$pages) {

        foreach ($pages as $id => $outer_page) {
            $found = false;

            foreach (array_values($pages) as $inner_page) {
                if ($inner_page->parent == $outer_page->name) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $pages[$id]->isLeaf = true;
            }
        }
    }

    /**
     * Expands the tree of curriculum entities based on how you got to the
     * current CM page via the curr admin menu
     */
    private function append_path_items() {
        global $USER;

        //make sure the current page was reached via the curr admin menu
        if (isset($USER->currentitypath)) {
            $parts = explode('/', $USER->currentitypath);

            //track the last element along the path
            $parent_element_id = 'root';

            //track the last encountered cluster and curriculum ids
            $parent_cluster_id = 0;
            $parent_curriculum_id = 0;

            //track where we are within the curr entity path
            $position_in_path = 0;

            //store the path as we build it up so that newly created links
            //expand the tree as well
            $cumulative_path = '';

            foreach ($parts as $part) {
                //convert to notation used by the tree
                $current_element_id = str_replace('-', '_', $part);

                //create menu item here
                $current_parts = explode('_', $current_element_id);

                //track the last cluster and curriculum id encountered so that we can
                //correctly load the child elements
                if ($current_parts[0] == 'cluster' || $current_parts[0] == 'userset') {
                    $parent_cluster_id = $current_parts[1];
                } else if ($current_parts[0] == 'curriculum') {
                    $parent_curriculum_id = $current_parts[1];
                }

                //append the current element to the working path
                if (empty($cumulative_path)) {
                    $cumulative_path = $part;
                }  else {
                    $cumulative_path .= '/' . $part;
                }

                //load children for all nodes except for the lowest-level ones
                if ($position_in_path < count($parts) - 1) {

                    //automatically load all correct children
                    if ($children = block_curr_admin_load_menu_children($current_parts[0], !isset($current_parts[1]) ? '' : $current_parts[1], $parent_cluster_id, $parent_curriculum_id, $cumulative_path)) {
                        foreach ($children as $child) {

                            $node = clone($child);
                            //make the loaded node a child of the current one
                            $node->parent = $current_element_id;
                            //ignore the bogus root element
                            if ($node->name !== 'root') {
                                $this->listing[] = $node;
                            }
                        }
                    }

                }

                //force the parent element to expand
                foreach ($this->listing as $listing_key => $listing_entry) {
                    if ($listing_entry->name == $parent_element_id) {
                        $this->listing[$listing_key]->forceexpand = true;
                    }
                }

                $parent_element_id = $current_element_id;

                $position_in_path++;

            }

        }

    }

}

/**
 * This class represents a menu item for display on the Curriculum Administration menu
 *
 */
class menuitem {

    var $name;
    var $page = null;
    var $parent = null;
    var $title = '';
    var $style = '';
    var $link_target = '';
    var $js_sensitive = false;

    /**
     * Menuitem constructor
     *
     * @param  string        $name          The shortname for this particular menu item
     * @param  menuitempage  $page          The page corresponding to this item
     * @param  string        $parent        The shortname of the parent element (overrides the one defined in the page class)
     * @param  string        $title         The title to display
     * @param  string        $style         A css class to use for the icon
     * @param  string        $link_target   Target window / tab for link
     * @param  boolean       $js_sensitive  If true, hide when no js
     * @param  string        $parent_path   Path of parent curriculum elements in the tree
     */
    function menuitem($name, $page = null, $parent = null, $title = '', $style = 'tree_icon', $link_target = '', $js_sensitive = false, $parent_path = '') {

        $this->name = $name;
        $this->page = $page;

        if (!empty($title)) {
            $this->title = $title;
        } else if ($name != 'root') {
            //the language string will usually correspond with the name
            $this->title = get_string($name, 'elis_program');
        } else {
            //not a visible node
            $this->title = '';
        }

        if (!empty($parent)) {
            $this->parent = $parent;
        } else if (!empty($this->page)) {
            //get the parent from the actual page
            $this->parent = $this->page->get_parent_page();
        } else {
            $this->parent = null;
        }

        $this->style = $style;
        $this->link_target = $link_target;
        $this->js_sensitive = $js_sensitive;
        $this->parent_path = $parent_path;
    }

    function is_page_link() {
        return !empty($this->page->page_instance);
    }

    /**
     * Retrieves the link to be used when rendering this item
     * in the menu
     *
     * @return  string  Valid anchor HTML tag, or the empty string if
     *                  this item doesn't link to a page
     */
    function get_link_url_tag() {
        //make sure we are actually linking to a page
        if ($this->is_page_link()) {

            //parameters to pass to the page
            $param_array = array();

            //retrieve the item identifier as calculated by the page we are linking to
            $identifier = $this->page->page_instance->get_entity_name($this->parent_path, $this->name);

            if ($identifier !== NULL) {
                if (empty($this->parent_path)) {
                    //no parent, so just use the identifier
                    $effective_parent_path = $identifier;
                } else {
                    //combine the parent path with the identifier
                    $effective_parent_path = $this->parent_path . '/' . $identifier;
                }
                $param_array['currentitypath'] = $effective_parent_path;
            }

            //create a valid URL from the page
            $target = empty($this->link_target) ? '' : ' target="' . $this->link_target . '"';
            $new_page = $this->page->page_instance->get_new_page($param_array);
            $url = $new_page->url->out();

            //this prevents the tree from being expanded when clicking on links
            $stop_propagation_script = 'event.cancelBubble = true;
                                        if (event.stopPropagation) {
                                            event.stopPropagation();
                                        }';

            //return an anchor tag linking to the appropriate page
            return "<a href=\"{$url}\"{$target} onclick=\"{$stop_propagation_script}\">";
        } else {
            return '';
        }
    }

}

/**
 * This class represents the page pointed to by an item on the Curriculum Administration menu
 *
 */
class menuitempage {

    var $page;
    var $classfile;
    var $params = array();
    //the actual ELIS page class instance
    var $page_instance = null;

    /**
     * Menuitempage constructor
     *
     * @param  string  $page       A short string representing the page name
     * @param  string  $classfile  The name of the classfile within the curriculum directory
     *                             (determined from page name when blank)
     * @param  array   $params     Additional page parameters
     */
    function menuitempage($page, $classfile = '', $params = array()) {
        global $CFG;

        $this->page = $page;

        if (!empty($classfile)) {
            $this->classfile = $classfile;
        } else {
            //the classfile will usually match the page name
            $this->classfile = $page . '.class.php';
        }

        $this->params = $params;

        //cache the actual instance
        require_once(elispm::file($this->classfile));
        $this->page_instance = new $this->page($params);
    }

    /**
     * Determines the parent page based on the actual page class
     *
     * @return  string  The shortname of the parent page, or null if none
     */
    function get_parent_page() {
        if (!empty($this->page_instance->section)) {
            return $this->page_instance->section;
        }

        return null;
    }

}

/**
 * Class that represents a menu item linking to a page that
 * is not necessary part of the curriculum managment system
 */
class generic_menuitempage extends menuitempage {
    /**
     * Generic Menuitempage constructor
     *
     * @param  string  $page       A short string representing the page name
     * @param  string  $classfile  The name of the classfile, including the full path
     * @param  array   $params     Additional page parameters
     */
    function generic_menuitempage($page, $classfile = '', $params = array()) {
        global $CFG;

        $this->page = $page;

        $this->classfile = $classfile;

        $this->params = $params;

        //cache the actual instance
        require_once($this->classfile);
        $this->page_instance = new $this->page($params);
    }
}

/**
 * Fake page for linking to an arbitrary URL
 */
class url_page {
    public function __construct($url) {
        $this->url = new moodle_url($url);
    }

    public function can_do() {
        return true;
    }

    public function get_moodle_url() {
        if (is_a($this->url, 'moodle_url')) {
            return $this->url;
        } else {
            return new moodle_url($this->url);
        }
    }

    public function get_url() {
        if (is_a($this->url, 'moodle_url')) {
            return $this->url->out();
        } else {
            return $this->url;
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
        //implement in child class, if necessary
        return NULL;
    }

    /**
     * Create a new page object of the same class with the given parameters.
     *
     * @param string $url An overriding URL, if applicable.
     */
    public function get_new_page(array $url=null) {
        $pageclass = get_class($this);
        if ($url == null) {
            $url = $this->url;
        }
        return new $pageclass($url);
    }
}

/**
 * This class represents the markup tree that is used to create the YUI TreeView
 *
 */
class treerepresentation {

    var $root = null;
    var $listing = null;

    /**
     * Treerepresentation constructor
     *
     * @param  menuitemlisting  $listing  The list of all menu items
     *
     */
    function treerepresentation($listing) {

        $this->listing = $listing;

        foreach ($listing->listing as $key => $value) {
            if (empty($value->parent)) {
                $this->root = new treerepresentationnode($value->name, $this);
                break;
            }
        }

        $this->root->prune();
    }

    /**
     * Converts the tree representation to html markup
     *
     * @param   string  $uniqueid        The unique id of the filter element
     * @param   int     $execution_mode  The mode in which the report is being run
     * @return  string  The appropriate markup
     */
    function convert_to_markup($uniqueid = '', $execution_mode = null) {
        global $CFG;

        //this prevents handling an empty tree
        if (empty($this->root->children)) {
            return '';
        }

        //use the appropriate css for the menu
        $style = ''; // '<style>@import url("' . $CFG->wwwroot . '/lib/yui/treeview/assets/skins/sam/treeview.css");</style>';

        //YUI needs an appropriate div to place the tree in
        $result = $style . "<div id=\"block_curr_admin_tree\" class=\"admintree\"></div>";

        /*
        //obtain the actual tree object
        $js_object = $this->get_js_object();

        //render the tree when the page is loaded
        $result .= "<script type=\"text/javascript\">
        //<![CDATA[
        var object = $js_object;
        var wwwroot = \"{$CFG->wwwroot}\";
        YAHOO.util.Event.onDOMReady(function() {
                render_curr_admin_tree(object);
        });
        //]]>
        </script>";*/

        //handle non-js case
        $result .= '<noscript>' . $this->root->convert_to_markup() . '</noscript>';

        return $result;
    }

    /**
     * Returns the stored list of pages used by this tree
     *
     * @return  menuitem array  The list of pages
     */
    function get_listing_entries() {
        return $this->listing->listing;
    }

    /**
     * Returns the particular page being requested
     *
     * @param   string    $key  The name of the page being requested
     *
     * @return  menuitem        The appropriate item
     */
    function get_listing_entry($key) {
        return $this->listing->listing[$key];
    }

    /**
     * Determines the section the user is currently in
     */
    static function get_current_section() {
        global $CURMAN, $PAGE;

        //return the report shortname if we are specifically on a PHP report page
        $report = optional_param('report', '', PARAM_CLEAN);
        if ($report !== '') {
            return $report;
        }

        //this code is based on code found in the curr_admin block
        return (isset($CURMAN->page->section) ? $CURMAN->page->section : (isset($PAGE->section) ? $PAGE->section : ''));
    }

    /**
     * Returns a JSON representation of the tree in the format expected
     * by the YUI TreeView constructor
     */
    function get_js_object() {
        $expanded_sections = $this->root->get_expanded_sections();

        $object = $this->root->get_js_object($expanded_sections);
        return json_encode($object);
    }

}

/**
 * This class represents an item in the markup tree
 *
 */
class treerepresentationnode {

    var $name;
    //a reference to the containing treerepresentation
    var $parent;
    var $children = array();

    /**
     * Treerepresentationnode constructor
     *
     * @param  string              $name    The name that represents this node
     * @param  treerepresentation  $parent  The containing treerepresentation
     *
     */
    function treerepresentationnode($name, &$parent) {

        $this->name = $name;
        $this->parent =& $parent;

        $list_entries = $this->parent->get_listing_entries();

        //recursively build the tree of children
        foreach ($list_entries as $key => $value) {
            if ($value->parent == $name) {
                $this->children[] = new treerepresentationnode($value->name, $parent);
            }
        }

    }

    /**
     * Determines an appropriate anchor tag for the node
     *
     * @return  string  The node URL
     */
    function get_url_tag() {

        //look for the name that identified the page we are linking to
        $listing_entry = $this->parent->get_listing_entry($this->name);

        return $listing_entry->get_link_url_tag();
    }

    /**
     * Recursively prune empty directories from the tree structure
     *
     * @return  boolean  True if fully pruned, false otherwise
     */
    function prune() {

        //recurse as necessary
        if (!empty($this->children)) {
            foreach ($this->children as $id => $child) {
                $result = $child->prune();
                if ($result == true) {
                    //prune the tree along the way
                    unset($this->children[$id]);
                }
            }
        }

        $listing_entry = $this->parent->get_listing_entry($this->name);

        //if an element has no children or link to a page, then it's an empty folder
        if (empty($this->children) && !$listing_entry->is_page_link()) {
            return true;
        } else {
            return false;
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

        //add link when appropriate (node is a leaf or was forced to be expanded based on entity path)
        if (empty($this->children) || !empty($this->parent->get_listing_entry($this->name)->forceexpand)) {
            $url = $this->get_url_tag();
            $object->label = $url . $object->label . '</a>';
        }

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

        //if the node itself is forced to expand (based on current page), expand it
        if (!empty($this->parent->get_listing_entry($this->name)->forceexpand)) {
            $object->expanded = true;
        }

        //style
        $object->labelStyle = $this->parent->get_listing_entry($this->name)->style;

        //parent entity info
        $object->contentElId = $this->calculate_identifier();

        //make this a leaf node if appropriate
        $object->isLeaf = empty($this->parent->get_listing_entry($this->name)->isLeaf) ? false : true;

        return $object;
    }

    /**
     * Determines which sections are currently expanded
     *
     * @return  array  The list of expanded pages
     */
    function get_expanded_sections() {
        $current_page = treerepresentation::get_current_section();

        //base case
        if ($this->name == $current_page) {
            return array($this->name);
        }

        foreach ($this->children as $child) {
            $child_result = $child->get_expanded_sections();
            if (!empty($child_result)) {
                //should have only one tree "branch", so merge upward
                return array_merge(array($this->name), $child_result);
            }
        }

        //not found
        return array();

    }

    /**
     * Converts this node to a markup element
     *
     * @param   boolean  $js_sensitive  If true, hide all elements that are js-sensitive
     * @return  string                  The markup for this entire tree
     */
    function convert_to_markup($js_sensitive = false) {

        if (!empty($this->parent->get_listing_entry($this->name)->js_sensitive)) {
            return '';
        }

        //create the link if necessary
        $url = $this->get_url_tag();

        if ($this->name != 'root') {

            $style = $this->parent->get_listing_entry($this->name)->style;
            $style_class = '';

            if (!empty($style)) {
                $style_class = ' class="' . $style . '"';
            }

            if (!empty($url)) {
                $result = "<li{$style_class}>" . $url . $this->parent->get_listing_entry($this->name)->title . '</a>';
            } else {
                $result = "<li{$style_class}><span class=\"categorytext\">" . $this->parent->get_listing_entry($this->name)->title . "</span>";
            }
        } else {
            $result = '';
        }

        //recurse into children if necessary
        if (!empty($this->children)) {
            $result .= "<ul class=\"block_curr_admin_nonjs\">";
            foreach ($this->children as $child) {
                $result .= $child->convert_to_markup();
            }
            $result .= "</ul>";
        }

        if ($this->name != 'root') {
            $result .= "</li>";
        }

        return $result;
    }

    /**
     * Determine the unique identifier used by this node of the tree
     *
     * @return  string  The identifier, or the empty string if not set
     */
    protected function calculate_identifier() {
        $listing_entry = $this->parent->get_listing_entry($this->name);

        if (empty($listing_entry->contentElId)) {
            return '';
        } else {
            return $listing_entry->contentElId;
        }

    }

}

