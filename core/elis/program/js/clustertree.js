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

/**
 * ----------------------------------
 * General (simple) utility functions
 * ----------------------------------
 */

/**
 * Function that always returns true (for use in getNodesBy)
 *
 * @param   TextNode     The node being passed in (not used)
 *
 * @return  boolean      true
 */
function clustertree_always_true(node) {
    //allow every node
    return true;
}

/**
 * -----------------------------------------
 * Handling of lists stored in form elements
 * -----------------------------------------
 */

/**
 * Appends a value to a list found within a UI element
 *
 * @param  string  instanceid  Unique report identifier
 * @param  string  list_name   Name of the UI element
 * @param  string  value       The value to append
 */
function clustertree_append_to_list(instanceid, list_name, value) {
    var listing_elements = clustertree_get_container_elements_by_name(instanceid, list_name);

    for (var i = 0; i < listing_elements.length; i++) {
        if (listing_elements[i].value == '') {
            //nothing stored yet
            listing_elements[i].value = value;
        } else {
            //check to see if it's already there
            var parts = listing_elements[i].value.split(',');
            var found = false;
            for (var j = 0; j < parts.length; j++) {
                if (parts[j] == value) {
                    found = true;
                    break;
                }
            }
            //append if not found
            if (!found) {
                listing_elements[i].value += ',' + value;
            }
        }
        //should only be getting a single element
        break;
    }
}

/**
 * Removed the first occurrence of a value from a list
 * found within a UI element
 *
 * @param  string  instanceid  Unique report identifier
 * @param  string  list_name   Name of the UI element
 * @param  string  value       The value to remove
 */
function clustertree_remove_from_list(instanceid, list_name, value) {
    var listing_elements = clustertree_get_container_elements_by_name(instanceid, list_name);

    for (i = 0; i < listing_elements.length; i++) {
        //get the current selection
        var selected_unexpanded = listing_elements[i].value.split(',');
        for (j = 0; j < selected_unexpanded.length; j++) {
            if (selected_unexpanded[j] == value) {
                //found the node's identifier, so remove it
                selected_unexpanded.splice(j, 1);
                found = true;
            }
        }
        //update the hidden field value
        listing_elements[i].value = selected_unexpanded.join(',');
    }
}

/**
 * Specifies the values stored in a particular hidden form element
 *
 * @param   string        instanceid  Unique report identifier
 * @param   string        list_name   Name of the UI element
 *
 * @return  string array              List of the contained values
 */
function clustertree_get_list_values(instanceid, list_name) {
    var result = [];

    //get the hidden element
    var elements = clustertree_get_container_elements_by_name(instanceid, list_name);

    //go through and append all values
    for (var i = 0; i < elements.length; i++) {
        var values = elements[i].value.split(',');
        for (var j = 0; j < values.length; j++) {
            result[result.length] = values[j];
        }
    }
    return result;
}

/**
 * -------------------------------------
 * Event handling and AJAX functionality
 * -------------------------------------
 */

/**
 * Function for handling dynamic node loading
 *
 * @param  Object    node            The node whose children we are loading
 * @param  function  fnLoadComplete  The function to call which will signify that loading is done
 * @param  string    uniqueid        The unique id used to identify the filter element
 * @param  TreeView  tree_view       The tree view where elements can be selected
 * @param  boolean   execution_mode  The mode in which the report is being executed (constant)
 */
function clustertree_loadNodeData(node, fnLoadComplete, uniqueid, tree_view, instanceid, execution_mode) {

    // URL of our script (document.wwwroot is set my clustertree_module.js)
    var url = document.wwwroot + '/elis/program/lib/filtering/helpers/clustertree_load_menu.php?data=' + node.contentElId +
                '&instanceid=' + instanceid + '&execution_mode=' + execution_mode;

    var callback = {
        //success function
        success: function(o) {
            //track the list of the content element ids that were added
            var added_content_el_ids = [];
            //YUI can fire multiple expand events for the same node
            //so make sure it hasn't already been loaded
            if (o.responseText != '' && node.children == '') {
                var responseObject = YAHOO.lang.JSON.parse(o.responseText);
                //loop through and append new child nodes
                for (var i = 0; i < responseObject.children.length; i++) {
                    var childObject = responseObject.children[i];
                    //this actually creates the node in the menu
                    var newNode = new YAHOO.widget.TextNode(childObject.label, node);
                    //information about parent elements is held in this value
                    newNode.contentElId = childObject.contentElId;

                    //CSS styling
                    newNode.labelStyle = childObject.labelStyle;

                    //specifies if we should not show the + icon
                    newNode.isLeaf = childObject.isLeaf;

                    //append this node to the list of added nodes
                    added_content_el_ids[added_content_el_ids.length] = newNode.contentElId;

                    //propagate child selection for selected by unexpanded parent nodes
                    clustertree_propagate_selected_unexpanded(instanceid, uniqueid, tree_view, node, newNode);

                    //tick the newly-expanded node if appropriate
                    clustertree_tick_selected_child(instanceid, uniqueid, tree_view, node, newNode);

                }
            }

            //children are expanded, so remove the parent node from listing of unexpanded nodes
            clustertree_remove_selected_unexpanded(instanceid, node, uniqueid);

            //remove from node from the listing of selected by unexpanded nodes, if appropriate
            clustertree_remove_from_list(instanceid, uniqueid + '_clrunexpanded', node.contentElId);

            //indicate that loading is complete
            fnLoadComplete();

            //add click handler to newly loaded elements
            for (var i = 0; i < added_content_el_ids.length; i++) {
                clustertree_set_up_onclick(instanceid, added_content_el_ids[i], uniqueid, tree_view);
            }
        },

        //failure function
        failure: function(o) {
            //DO NOT warn the user in any way because this failure can happen
            //in an innocuous way if you navigate to another page while the menu is loading
            //indicate that loading is complete
            fnLoadComplete();
        }
    }

    //make the actual call
    YAHOO.util.Connect.asyncRequest('GET', url, callback);
}

/**
 * Function used to handle clicking on a checkbox
 *
 * @param  Event   event     The event taking place with regard to a checkbox
 * @param  array   param     Object containing necessary parameters
 */
function clustertree_handler_func(event, param) {
    var e = event || window.event;
    var t = e.target || e.srcElement;

    //node's content element id
    var contentElId = param[0];
    //parameter uniqueid
    var uniqueid = param[1];
    //the tree view object
    var tree_view = param[2];
    //report instance id
    var instanceid = param[3];

    if (t.id != '') {
        //toggle state on the element
        current_element = tree_view.getNodesByProperty('contentElId', contentElId)[0];
        clustertree_toggle_highlight(instanceid, current_element, 1 - current_element.highlightState, uniqueid, tree_view, true);

        //prevent event from propagation to the element that controls expansion
        if (e.stopPropagation) {
            e.stopPropagation();
        } else {
            e.cancelBubble = true;
        }
    }
}

/**
 * Registers a click event handler on the supplied node
 *
 * @param  string    instanceid     Unique report identifier
 * @param  string    content_el_id  The id of the node to add the click handler to
 * @param  string    uniqueid       The unique id used to identify the filter element
 * @param  TreeView  tree_view      The tree view where elements can be selected
 */
function clustertree_set_up_onclick(instanceid, content_el_id, uniqueid, tree_view) {
    //element to add the listener to
    var element = clustertree_get_container_element_by_id('cluster_param_tree_' + instanceid + '_' + uniqueid, content_el_id);

    //data to be passed to the listener
    var array_param = [];
    //node's content element id
    array_param[0] = content_el_id;
    //parameter uniqueid
    array_param[1] = uniqueid;
    //the tree view object
    array_param[2] = tree_view;
    //report instance id
    array_param[3] = instanceid;

    //add an event listener for the click event
    YAHOO.util.Event.addListener(element, 'click', clustertree_handler_func, array_param);
}

/**
 * -----------------------------------------------
 * Helper methods fundamental to the UI / TreeView
 * -----------------------------------------------
 */

/**
 * Obtains elements from a particular container that match the specified name
 *
 * @param   string  instanceid   Unique report identifier
 * @param   string  elementname  Name of the elements to find
 *
 * @return  array                Array of elements found
 */
function clustertree_get_container_elements_by_name(instanceid, elementname) {
    var result = YAHOO.util.Dom.getElementsBy(function(el) { return el.name == elementname; },
                     '', 'php_report_body_' + instanceid);
    return result;
}

/**
 * Obtains the element from a particular container that mathces the specified id
 *
 * @param  string       instanceid  Unique report identifier
 * @param  string       elementid   Unique element identifier
 *
 * @return  DomElement              The element found
 */
function clustertree_get_container_element_by_id(instanceid, elementid) {
    var result = YAHOO.util.Dom.getElementsBy(function(el) { return el.id == elementid; },
    		     '', instanceid);
    return result[0];
}

/**
 * Adds a node to the stored listing of nodes that are selected
 * but have unloaded children
 *
 * @param  string    instanceid  Unique report identifier
 * @param  TextNode  node        The node we are adding to the list
 * @param  string    uniqueid    The unique id used to identify the filter element
 */
function clustertree_add_selected_unexpanded(instanceid, node, uniqueid) {
    clustertree_append_to_list(instanceid, uniqueid + '_unexpanded', node.contentElId);
    clustertree_remove_from_list(instanceid, uniqueid + '_clrunexpanded', node.contentElId);
}

/**
 * Removes a node to the stored listing of nodes that are selected
 * but have unloaded children
 *
 * @param  string    instanceid  Unique report identifier
 * @param  TextNode  node        The node we are removing from the list
 * @param  string    uniqueid    The unique id used to identify the filter element
 * @param  boolean   store       Whether to store the unexpanded state
 */
function clustertree_remove_selected_unexpanded(instanceid, node, uniqueid, store) {
    clustertree_remove_from_list(instanceid, uniqueid + '_unexpanded', node.contentElId);

    if (store) {
        clustertree_append_to_list(instanceid, uniqueid + '_clrunexpanded', node.contentElId);
    }
}

/**
 * Toggles the highlighted state of a node if necessary (and child nodes accordingly)
 *
 * @param  string    instanceid   Unique report identifier
 * @param  TextNode  node         The node whose state we are setting
 * @param  int       ideal_state  The state we want the node to be in
 * @param  string    uniqueid     The unique id used to identify the filter element
 * @param  boolean   store        Whether to store any unexpanded state info
 */
function clustertree_toggle_highlight(instanceid, node, ideal_state, uniqueid, tree_view, store) {
    if (node.highlightState != ideal_state) {
        //state is not the ideal state, so toggle it
        //*
        tree_view.onEventToggleHighlight(node);
        if (ideal_state == 1) {
            clustertree_append_to_list(instanceid, uniqueid + '_listing', node.contentElId);
        } else {
           clustertree_remove_from_list(instanceid, uniqueid + '_listing', node.contentElId);
        }
    }

    if (!node.hasChildren() && node.hasChildren(true)) {
        //node has unexpanded children
        if (node.highlightState == 1) {
            //flag the node as being unexpanded and having unloaded children
            clustertree_add_selected_unexpanded(instanceid, node, uniqueid);
        } else {
            //remove the node from the list of unexpanded nodes
            clustertree_remove_selected_unexpanded(instanceid, node, uniqueid, store);
       }
    }

    if (node.hasChildren()) {
        //node has expanded children, so put them into the same state
        for (var i = 0; i < node.children.length; i++) {
            clustertree_toggle_highlight(instanceid, node.children[i], ideal_state, uniqueid, tree_view, store);
        }
    }

}

/**
 * Ticks off checkboxes that should be selected and are initially revealed
 *
 * @param  string     instanceid  Unique report identifier
 * @param  string     uniqueid    The unique id used to identify the filter element
 * @param  TreeView   tree_view   The tree view where elements can be selected
 */
function clustertree_tick_top_level_boxes(instanceid, uniqueid, tree_view) {
    var values = clustertree_get_list_values(instanceid, uniqueid + '_listing');
    var inner_nodes = tree_view.getNodesBy(clustertree_always_true);

    //match enabled elements to visible ones
    for (var i = 0; i < values.length; i++) {
        for (var j in inner_nodes) {
            if (inner_nodes[j].contentElId == values[i] && inner_nodes[j].highlightState != 1) {
                tree_view.onEventToggleHighlight(inner_nodes[j]);
            }
        }
    }
}

/**
 * Propagates the listing of nodes that are selected but unexpanded down
 * the tree when a node is expanded
 *
 * @param  string     instanceid  Unique report identifier
 * @param  uniqueid   The unique id used to identify the filter element
 * @param  tree_view  The tree view where elements can be selected
 * @param  node       The parent of the newly revealed node
 * @param  newNode    The newly revealed node
 */
function clustertree_propagate_selected_unexpanded(instanceid, uniqueid, tree_view, node, newNode) {
    //enable selected by unexpanded nodes
    var elements = clustertree_get_list_values(instanceid, uniqueid + '_unexpanded');

    for (var i = 0; i < elements.length; i++) {
        if (node.contentElId == elements[i]) {
            clustertree_toggle_highlight(instanceid, newNode, 1, uniqueid, tree_view, false);
        }
    }

    //enable nodes whose selected status should be cleared
    elements = clustertree_get_list_values(instanceid, uniqueid + '_clrunexpanded');

    for (var i = 0; i < elements.length; i++) {
        if (node.contentElId == elements[i]) {
            clustertree_toggle_highlight(instanceid, newNode, 0, uniqueid, tree_view, false);
        }
    }
}

/**
 * Tick a node that was just revealed by tree expansion
 *
 * @param  string    instanceid  Unique report identifier
 * @param  string    uniqueid    The unique id used to identify the filter element
 * @param  TreeView  tree_view   The tree view where elements can be selected
 * @param  TextNode  node        The parent of the newly revealed node
 * @param  TextNode  newNode     The newly revealed node
 */
function clustertree_tick_selected_child(instanceid, uniqueid, tree_view, node, newNode) {
    //if the parent element is cleared, don't select the new node
    var clear_unexpanded_values = clustertree_get_list_values(instanceid, uniqueid + '_clrunexpanded');
    for (var i = 0; i < clear_unexpanded_values.length; i++) {
        if (node.contentElId == clear_unexpanded_values[i]) {
            //set the child element as cleared
            clustertree_append_to_list(instanceid, uniqueid + '_clrunexpanded', newNode.contentElId);
            return;
        }
    }

    //main work to select the new node
    var values = clustertree_get_list_values(instanceid, uniqueid + '_listing');
    for (var i = 0; i < values.length; i++) {
        if (newNode.contentElId == values[i] && newNode.highlightState != 1) {
            tree_view.onEventToggleHighlight(newNode);
            clustertree_append_to_list(instanceid, uniqueid + '_listing', newNode.contentElId);
        }
    }
}

/**
 * Toggles the state / text of the button that switches between the cluster dropdown
 * and the cluster tree
 *
 * @param  string   instanceid            Unique report identifier
 * @param  string   uniqueid              The unique id used to identify the filter element
 * @param  string   dropdown_button_text  Text to be shown on button when dropdown is enabled
 * @param  string   tree_button_text      Text to be shown on button when tree is enabled
 * @param  boolean  usingdropdown         If true, in "dropdown" mode, otherwise in "tree" mode
 */
function clustertree_toggle_button_state(instanceid, uniqueid, dropdown_button_text, tree_button_text, usingdropdown) {
    var toggle_name = uniqueid + '_toggle';
    var toggle_buttons = clustertree_get_container_elements_by_name(instanceid, toggle_name);
    for (var i = 0; i < toggle_buttons.length; i++) {
        if (usingdropdown) {
            toggle_buttons[i].value = dropdown_button_text;
        } else {
            toggle_buttons[i].value = tree_button_text;
        }
        break;
    }
}

/**
 * Updates the UI state of the tree element
 *
 * @param  string   instanceid     Unique report identifier
 * @param  string   uniqueid       The unique id used to identify the filter element
 * @param  boolean  usingdropdown  If true, in "dropdown" mode, otherwise in "tree" mode
 */
function clustertree_set_tree_state(instanceid, uniqueid, usingdropdown) {
    var cluster_param_tree = clustertree_get_container_element_by_id('php_report_body_' + instanceid, 'cluster_param_tree_' + instanceid + '_' + uniqueid);

    if (!usingdropdown) {
        cluster_param_tree.style.display = '';
    } else {
        cluster_param_tree.style.display = 'none';
    }
}

/**
 * Updates the UI state of the dropdown element
 *
 * @param  string   instanceid     Unique report identifier
 * @param  string   uniqueid       The unique id used to identify the filter element
 * @param  boolean  usingdropdown  If true, in "dropdown" mode, otherwise in "tree" mode
 */
function clustertree_set_dropdown_state(instanceid, uniqueid, usingdropdown) {
    var dropdown_name = uniqueid + "_dropdown";
    var dropdown_elements = clustertree_get_container_elements_by_name(instanceid, dropdown_name);
    for (var i = 0; i < dropdown_elements.length; i++) {
        if (!usingdropdown) {
            dropdown_elements[i].style.display = 'none';
        } else {
            dropdown_elements[i].style.display = '';
        }
        break;
    }
}

/**
 * --------------------
 * Entry points
 * --------------------
 */

/**
 * Toggles the state of the entire widget to the opposite of whatever it currently is
 *
 * @param  string  instanceid            Unique report identifier
 * @param  string  uniqueid              The unique id used to identify the filter element
 * @param  string  dropdown_button_text  Text to be shown on button when dropdown is enabled
 * @param  string  tree_button_text      Text to be shown on button when tree is enabled
 */
function clustertree_toggle_tree(instanceid, uniqueid, dropdown_button_text, tree_button_text) {
    var usingdropdown_name = uniqueid + '_usingdropdown';
    var usingdropdown_elements = clustertree_get_container_elements_by_name(instanceid, usingdropdown_name);
    var usingdropdown = false;

    //store state
    for (var i = 0; i < usingdropdown_elements.length; i++) {
        if (usingdropdown_elements[i].value == '') {
            usingdropdown_elements[i].value = '1';
            usingdropdown = true;
        } else {
            usingdropdown_elements[i].value = '';
        }
        break;
    }

    //change the button display text
    clustertree_toggle_button_state(instanceid, uniqueid, dropdown_button_text, tree_button_text, usingdropdown);

    //toggle dropdown state
    clustertree_set_dropdown_state(instanceid, uniqueid, usingdropdown);

    //toggle tree state
    clustertree_set_tree_state(instanceid, uniqueid, usingdropdown);
}

/**
 * Sets the state of all elements in the UI based on the current state
 * specified when setting data on the form
 *
 * @param  string  instanceid            Unique report identifier
 * @param  string  uniqueid              The unique id used to identify the filter element
 * @param  string  dropdown_button_text  Text to be shown on button when dropdown is enabled
 * @param  string  tree_button_text      Text to be shown on button when tree is enabled
 */
function clustertree_set_toggle_state(instanceid, uniqueid, dropdown_button_text, tree_button_text) {
    var usingdropdown_name = uniqueid + '_usingdropdown';
    var usingdropdown_elements = clustertree_get_container_elements_by_name(instanceid, usingdropdown_name);
    var usingdropdown_value = '';

    //determine if we are in dropdown mode
    for (var i = 0; i < usingdropdown_elements.length; i++) {
        usingdropdown_value = usingdropdown_elements[i].value;
        break;
    }

    //disable the "inappropriate" element
    if (usingdropdown_value == '1') {
        clustertree_set_tree_state(instanceid, uniqueid, true);
    } else {
        clustertree_set_dropdown_state(instanceid, uniqueid, false);
    }

    //set button text appropriately
    clustertree_toggle_button_state(instanceid, uniqueid, dropdown_button_text, tree_button_text, usingdropdown_value == '1');
}

/**
 * Render the tree that represents the curriculum admin menu
 *
 * @param  string    instanceid      Unique report identifier
 * @param  string    uniqueid        The unique id used to identify the filter element
 * @param  string    tree_object     The JSON string representing the tree object to be rendered
 * @param  boolean   execution_mode  The mode in which the report is being executed (constant)
 */
function clustertree_render_tree(instanceid, uniqueid, tree_object, execution_mode) {
    var cluster_param_tree = new YAHOO.widget.TreeView("cluster_param_tree_" + instanceid + "_" + uniqueid,
			             tree_object.children);

    //set up dynamic loading, passing in the calculated unique id
    var dynamic_load_func = function(node, fnLoadComplete) {
        clustertree_loadNodeData(node, fnLoadComplete, uniqueid, cluster_param_tree,
                                 instanceid, execution_mode);
    };

    cluster_param_tree.setDynamicLoad(dynamic_load_func);

    //render the tree
    cluster_param_tree.render();

    //set the selection in the UI
    clustertree_tick_top_level_boxes(instanceid, uniqueid, cluster_param_tree);

    //set up the onclick event for all top-level nodes
    var nodes = cluster_param_tree.getNodesBy(clustertree_always_true);
    for (var i in nodes) {
        clustertree_set_up_onclick(instanceid, nodes[i].contentElId, uniqueid, cluster_param_tree);
    }
}
