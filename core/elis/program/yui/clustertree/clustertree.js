/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
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
 * @package    elis_program
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

YUI.add('moodle-elis_program-clustertree', function(Y) {

    /**
     * The menuitem module
     * @property MODULEBASENAME
     * @type {String}
     * @default 'program-clustertree'
     */
    var MODULEBASENAME = 'program-clustertree';

    /**
     * This method calls the base class constructor
     * @method MODULEBASE
     */
    var MODULEBASE = function() {
        MODULEBASE.superclass.constructor.apply(this, arguments);
    }

    /**
     * @class M.elis_program.clustertree
     */
    Y.extend(MODULEBASE, Y.Base, {

        /**
         * Initialize the menuitem module
         * @param onject args function arguments:
         * { wwwroot, instanceid, uniqueid, tree, executionmode, reportid,
         *  dropdownbuttontext, treebuttontext [, script]};
         */
        initializer : function(args) {
            document.wwwroot = args.wwwroot;
            if (!args.script || args.script == '') {
                args.script = '/elis/program/lib/filtering/helpers/clustertree_load_menu.php';
            }
            document.clustertreescript = args.script;
            document.clustertreescope = this;

            var treeobj = Y.JSON.parse(args.tree);
            document.clustertreescope.clustertree_render_tree(args.instanceid, args.uniqueid, treeobj, args.executionmode);
            document.clustertreescope.clustertree_set_toggle_state(args.reportid, args.uniqueid, args.dropdownbuttontext, args.treebuttontext);

            // Assign click event for 'Enable Tree/Dropdown' button
            var clustertreetogglebutton = Y.one('#id_'+args.uniqueid+'_toggle');
            clustertreetogglebutton.on('click', document.clustertreescope.clustertree_toggle_tree, this, args.reportid, args.uniqueid,
                    args.dropdownbuttontext, args.treebuttontext);
        },

        /**
         * ----------------------------------
         * General (simple) utility functions
         * ----------------------------------
         */

        /**
         * Function that always returns true (for use in getNodesBy)
         * @param  object The node being passed in (not used)
         * @return boolean true
         */
        clustertree_always_true : function(node) {
            // allow every node
            return true;
        },

        /**
         * -----------------------------------------
         * Handling of lists stored in form elements
         * -----------------------------------------
         */

        /**
         * Appends a value to a list found within a UI element
         * @param string instanceid Unique report identifier
         * @param string listname Name of the UI element
         * @param string value The value to append
         */
        clustertree_append_to_list : function(instanceid, listname, value) {
            var listingelements = document.clustertreescope.clustertree_get_container_elements_by_name(instanceid, listname);
            for (var i = 0; i < listingelements.length; i++) {
                if (listingelements[i].value == '') {
                    // nothing stored yet
                    listingelements[i].value = value;
                } else {
                    // check to see if it's already there
                    var parts = listingelements[i].value.split(',');
                    var found = false;
                    for (var j = 0; j < parts.length; j++) {
                        if (parts[j] == value) {
                            found = true;
                            break;
                        }
                    }
                    // append if not found
                    if (!found) {
                        listingelements[i].value += ',' + value;
                    }
                }
                // should only be getting a single element
                break;
            }
        },

        /**
         * Removed the first occurrence of a value from a list
         * found within a UI element
         * @param string instanceid Unique report identifier
         * @param string listname Name of the UI element
         * @param string value The value to remove
         */
        clustertree_remove_from_list : function(instanceid, listname, value) {
            var listingelements = document.clustertreescope.clustertree_get_container_elements_by_name(instanceid, listname);
            for (var i = 0; i < listingelements.length; i++) {
                // get the current selection
                var selectedunexpanded = listingelements[i].value.split(',');
                for (var j = 0; j < selectedunexpanded.length; j++) {
                    if (selectedunexpanded[j] == value) {
                        // found the node's identifier, so remove it
                        selectedunexpanded.splice(j, 1);
                        found = true;
                    }
                }
                // update the hidden field value
                listingelements[i].value = selectedunexpanded.join(',');
            }
        },

        /**
         * Specifies the values stored in a particular hidden form element
         * @param  string instanceid Unique report identifier
         * @param  string listname Name of the UI element
         * @return array List of the contained values
         */
        clustertree_get_list_values : function(instanceid, listname) {
            var result = [];
            // get the hidden element
            var elements = document.clustertreescope.clustertree_get_container_elements_by_name(instanceid, listname);
            // go through and append all values
            for (var i = 0; i < elements.length; i++) {
                var values = elements[i].value.split(',');
                for (var j = 0; j < values.length; j++) {
                    result[result.length] = values[j];
                }
            }
            return result;
        },

        /**
         * -------------------------------------
         * Event handling and AJAX functionality
         * -------------------------------------
         */

        /**
         * Function for handling dynamic node loading
         * @param object   node The node whose children we are loading
         * @param function fnloadcomplete The function to call which will signify that loading is done
         * @param string   uniqueid The unique id used to identify the filter element
         * @param object treeview The tree view where elements can be selected
         * @param boolean  executionmode The mode in which the report is being executed (constant)
         */
        clustertree_loadnodedata : function(node, fnloadcomplete, uniqueid, treeview, instanceid, executionmode) {
            // URL of our script (document.wwwroot is set my clustertree_module.js)
            var url = document.wwwroot+document.clustertreescript;
            var cfg = {
                method: 'GET',
                data: 'data='+node.contentElId+'&instanceid='+instanceid+'&execution_mode='+executionmode,
                on: {
                    // success function
                    success: function(transid, o) {
                        // track the list of the content element ids that were added
                        var addedcontentelids = [];
                        // YUI can fire multiple expand events for the same node
                        // so make sure it hasn't already been loaded
                        if (o.responseText != '' && node.children == '') {
                            var responseobject = Y.JSON.parse(o.responseText);
                            // loop through and append new child nodes
                            for (var i = 0; i < responseobject.children.length; i++) {
                                var childobject = responseobject.children[i];
                                // this actually creates the node in the menu
                                var newnode = new window.yui2obj.widget.TextNode(childobject.label, node);
                                // information about parent elements is held in this value
                                newnode.contentElId = childobject.contentElId;
                                // CSS styling
                                newnode.labelStyle = childobject.labelStyle;
                                // specifies if we should not show the + icon
                                newnode.isLeaf = childobject.isLeaf;
                                // append this node to the list of added nodes
                                addedcontentelids[addedcontentelids.length] = newnode.contentElId;
                                // propagate child selection for selected by unexpanded parent nodes
                                document.clustertreescope.clustertree_propagate_selected_unexpanded(instanceid, uniqueid, treeview, node, newnode);
                                // tick the newly-expanded node if appropriate
                                document.clustertreescope.clustertree_tick_selected_child(instanceid, uniqueid, treeview, node, newnode);

                            }
                        }

                        // children are expanded, so remove the parent node from listing of unexpanded nodes
                        document.clustertreescope.clustertree_remove_selected_unexpanded(instanceid, node, uniqueid);
                        // remove from node from the listing of selected by unexpanded nodes, if appropriate
                        document.clustertreescope.clustertree_remove_from_list(instanceid, uniqueid+'_clrunexpanded', node.contentElId);

                        // add click handler to newly loaded elements
                        for (var j = 0; j < addedcontentelids.length; j++) {
                            document.clustertreescope.clustertree_set_up_onclick(instanceid, addedcontentelids[j], uniqueid, treeview);
                        }

                        // indicate that loading is complete
                        fnloadcomplete();
                    },
                    // failure function
                    failure: function(transid, o) {
                        // DO NOT warn the user in any way because this failure can happen
                        // in an innocuous way if you navigate to another page while the menu is loading
                        // indicate that loading is complete
                        fnloadcomplete();
                    }
                }
            }

            // make AJAX call
            Y.io(url, cfg);
        },

        /**
         * Function used to handle clicking on a checkbox
         * @param object event The event taking place with regard to a checkbox
         * @param string contentelid The id of the node to add the click handler to
         * @param string uniqueid The unique id used to identify the filter element
         * @param object treeview The tree view where elements can be selected
         * @param string instanceid Unique report identifier
         */
        clustertree_handler_func : function(event, contentelid, uniqueid, treeview, instanceid) {
            var e = event || window.event;
            var t = e.target || e.srcElement;
            if (typeof contentelid == 'object') {
                uniqueid = contentelid[1];
                treeview = contentelid[2];
                instanceid = contentelid[3];
                contentelid = contentelid[0];
            }
            if (t.id != '') {
                // toggle state on the element
                var currentelement = treeview.getNodesByProperty('contentElId', contentelid)[0];
                document.clustertreescope.clustertree_toggle_highlight(instanceid, currentelement, 1 - currentelement.highlightState, uniqueid, treeview, true);

                // prevent event from propagation to the element that controls expansion
                if (e.stopPropagation) {
                    e.stopPropagation();
                } else {
                    e.cancelBubble = true;
                }
            }
        },

        /**
         * Registers a click event handler on the supplied node
         * @param string instanceid Unique report identifier
         * @param string contentelid The id of the node to add the click handler to
         * @param string uniqueid The unique id used to identify the filter element
         * @param object treeview The tree view where elements can be selected
         */
        clustertree_set_up_onclick : function(instanceid, contentelid, uniqueid, treeview) {
            // add an event handler for the click event
            var spel = Y.one('#cluster_param_tree_'+instanceid+'_'+uniqueid);
            var el = spel.one('#'+contentelid);
            if (el) {
                el.on('click', document.clustertreescope.clustertree_handler_func, this, contentelid, uniqueid, treeview, instanceid);
            } else {
                var params = [contentelid, uniqueid, treeview, instanceid];
                window.yui2obj.util.Event.addListener(contentelid, 'click',  document.clustertreescope.clustertree_handler_func, params, false);
            }
        },

        /**
         * -----------------------------------------------
         * Helper methods fundamental to the UI / TreeView
         * -----------------------------------------------
         */

        /**
         * Obtains elements from a particular container that match the specified name
         * @param string instanceid Unique report identifier
         * @param string elementname Name of the elements to find
         * @return array Array of elements found
         */
        clustertree_get_container_elements_by_name : function(instanceid, elementname) {
            var result = window.yui2obj.util.Dom.getElementsBy(function(el) { return el.name == elementname; }, '', 'php_report_body_'+instanceid);
            return result;
        },

        /**
         * Adds a node to the stored listing of nodes that are selected
         * but have unloaded children
         * @param string instanceid Unique report identifier
         * @param object node The node we are adding to the list
         * @param string uniqueid The unique id used to identify the filter element
         */
        clustertree_add_selected_unexpanded : function(instanceid, node, uniqueid) {
            document.clustertreescope.clustertree_append_to_list(instanceid, uniqueid+'_unexpanded', node.contentElId);
            document.clustertreescope.clustertree_remove_from_list(instanceid, uniqueid+'_clrunexpanded', node.contentElId);
        },

        /**
         * Removes a node to the stored listing of nodes that are selected
         * but have unloaded children
         * @param string instanceid Unique report identifier
         * @param object node The node we are removing from the list
         * @param string uniqueid The unique id used to identify the filter element
         * @param boolean store Whether to store the unexpanded state
         */
        clustertree_remove_selected_unexpanded : function(instanceid, node, uniqueid, store) {
            document.clustertreescope.clustertree_remove_from_list(instanceid, uniqueid+'_unexpanded', node.contentElId);

            if (store) {
                document.clustertreescope.clustertree_append_to_list(instanceid, uniqueid+'_clrunexpanded', node.contentElId);
            }
        },

        /**
         * Toggles the highlighted state of a node if necessary (and child nodes accordingly)
         * @param string instanceid Unique report identifier
         * @param object node The node whose state we are setting
         * @param int idealstate The state we want the node to be in
         * @param string uniqueid The unique id used to identify the filter element
         * @param object treeview treeview object
         * @param boolean store Whether to store any unexpanded state info
         */
        clustertree_toggle_highlight : function(instanceid, node, idealstate, uniqueid, treeview, store) {
            if (node.highlightState != idealstate) {
                // state is not the ideal state, so toggle it
                treeview.onEventToggleHighlight(node);
                if (idealstate == 1) {
                    document.clustertreescope.clustertree_append_to_list(instanceid, uniqueid+'_listing', node.contentElId);
                } else {
                    document.clustertreescope.clustertree_remove_from_list(instanceid, uniqueid+'_listing', node.contentElId);
                }
            }

            if (!node.hasChildren() && node.hasChildren(true)) {
                // node has unexpanded children
                if (node.highlightState == 1) {
                    // flag the node as being unexpanded and having unloaded children
                    document.clustertreescope.clustertree_add_selected_unexpanded(instanceid, node, uniqueid);
                } else {
                    // remove the node from the list of unexpanded nodes
                    document.clustertreescope.clustertree_remove_selected_unexpanded(instanceid, node, uniqueid, store);
               }
            }

            if (node.hasChildren()) {
                // node has expanded children, so put them into the same state
                for (var i = 0; i < node.children.length; i++) {
                    document.clustertreescope.clustertree_toggle_highlight(instanceid, node.children[i], idealstate, uniqueid, treeview, store);
                }
            }
        },

        /**
         * Ticks off checkboxes that should be selected and are initially revealed
         * @param string instanceid Unique report identifier
         * @param string uniqueid The unique id used to identify the filter element
         * @param object treeview The tree view where elements can be selected
         */
        clustertree_tick_top_level_boxes : function(instanceid, uniqueid, treeview) {
            var values = document.clustertreescope.clustertree_get_list_values(instanceid, uniqueid+'_listing');
            var innernodes = treeview.getNodesBy(document.clustertreescope.clustertree_always_true);
            // match enabled elements to visible ones
            for (var i = 0; i < values.length; i++) {
                for (var j in innernodes) {
                    if (innernodes[j].contentElId == values[i] && innernodes[j].highlightState != 1) {
                        treeview.onEventToggleHighlight(innernodes[j]);
                    }
                }
            }
        },

        /**
         * Propagates the listing of nodes that are selected but unexpanded down
         * the tree when a node is expanded
         * @param string instanceid  Unique report identifier
         * @param string uniqueid The unique id used to identify the filter element
         * @param object treeview The tree view where elements can be selected
         * @param object node The parent of the newly revealed node
         * @param object newnode The newly revealed node
         */
        clustertree_propagate_selected_unexpanded : function(instanceid, uniqueid, treeview, node, newnode) {
            // enable selected by unexpanded nodes
            var elements = document.clustertreescope.clustertree_get_list_values(instanceid, uniqueid+'_unexpanded');
            for (var i = 0; i < elements.length; i++) {
                if (node.contentElId == elements[i]) {
                    document.clustertreescope.clustertree_toggle_highlight(instanceid, newnode, 1, uniqueid, treeview, false);
                }
            }

            // enable nodes whose selected status should be cleared
            elements = document.clustertreescope.clustertree_get_list_values(instanceid, uniqueid+'_clrunexpanded');
            for (i = 0; i < elements.length; i++) {
                if (node.contentElId == elements[i]) {
                    document.clustertreescope.clustertree_toggle_highlight(instanceid, newnode, 0, uniqueid, treeview, false);
                }
            }
        },

        /**
         * Tick a node that was just revealed by tree expansion
         * @param string instanceid Unique report identifier
         * @param string uniqueid The unique id used to identify the filter element
         * @param object treeview The tree view where elements can be selected
         * @param object node The parent of the newly revealed node
         * @param object newnode The newly revealed node
         */
        clustertree_tick_selected_child : function(instanceid, uniqueid, treeview, node, newnode) {
            // if the parent element is cleared, don't select the new node
            var clearunexpandedvalues = document.clustertreescope.clustertree_get_list_values(instanceid, uniqueid+'_clrunexpanded');
            for (var i = 0; i < clearunexpandedvalues.length; i++) {
                if (node.contentElId == clearunexpandedvalues[i]) {
                    // set the child element as cleared
                    document.clustertreescope.clustertree_append_to_list(instanceid, uniqueid+'_clrunexpanded', newnode.contentElId);
                    return;
                }
            }

            // main work to select the new node
            var values = document.clustertreescope.clustertree_get_list_values(instanceid, uniqueid+'_listing');
            for (i = 0; i < values.length; i++) {
                if (newnode.contentElId == values[i] && newnode.highlightState != 1) {
                    treeview.onEventToggleHighlight(newnode);
                    document.clustertreescope.clustertree_append_to_list(instanceid, uniqueid+'_listing', newnode.contentElId);
                }
            }
        },

        /**
         * Toggles the state / text of the button that switches between the cluster dropdown
         * and the cluster tree
         * @param string instanceid Unique report identifier
         * @param string uniqueid The unique id used to identify the filter element
         * @param string dropdownbuttontext Text to be shown on button when dropdown is enabled
         * @param string treebuttontext Text to be shown on button when tree is enabled
         * @param boolean usingdropdown If true, in "dropdown" mode, otherwise in "tree" mode
         */
        clustertree_toggle_button_state : function(instanceid, uniqueid, dropdownbuttontext, treebuttontext, usingdropdown) {
            var togglename = uniqueid+'_toggle';
            var togglebuttons = document.clustertreescope.clustertree_get_container_elements_by_name(instanceid, togglename);
            for (var i = 0; i < togglebuttons.length; i++) {
                if (usingdropdown) {
                    togglebuttons[i].value = dropdownbuttontext;
                } else {
                    togglebuttons[i].value = treebuttontext;
                }
                break;
            }
        },

        /**
         * Updates the UI state of the tree element
         * @param string instanceid Unique report identifier
         * @param string uniqueid The unique id used to identify the filter element
         * @param boolean usingdropdown If true, in "dropdown" mode, otherwise in "tree" mode
         */
        clustertree_set_tree_state : function(instanceid, uniqueid, usingdropdown) {
            var clusterparamtree = document.getElementById('cluster_param_tree_'+instanceid+'_'+uniqueid);
            if (!usingdropdown) {
                clusterparamtree.style.display = '';
            } else {
                clusterparamtree.style.display = 'none';
            }
        },

        /**
         * Updates the UI state of the dropdown element
         * @param string instanceid Unique report identifier
         * @param string uniqueid The unique id used to identify the filter element
         * @param boolean usingdropdown If true, in "dropdown" mode, otherwise in "tree" mode
         */
        clustertree_set_dropdown_state : function(instanceid, uniqueid, usingdropdown) {
            var dropdownname = uniqueid+"_dropdown";
            var dropdownelements = document.clustertreescope.clustertree_get_container_elements_by_name(instanceid, dropdownname);
            for (var i = 0; i < dropdownelements.length; i++) {
                if (!usingdropdown) {
                    dropdownelements[i].style.display = 'none';
                } else {
                    dropdownelements[i].style.display = '';
                }
                break;
            }
        },

        /**
         * --------------------
         * Entry points
         * --------------------
         */

        /**
         * Toggles the state of the entire widget to the opposite of whatever it currently is
         * @param object e the event
         * @param string instanceid Unique report identifier
         * @param string uniqueid The unique id used to identify the filter element
         * @param string dropdownbuttontext Text to be shown on button when dropdown is enabled
         * @param string treebuttontext Text to be shown on button when tree is enabled
         */
        clustertree_toggle_tree : function(e, instanceid, uniqueid, dropdownbuttontext, treebuttontext) {
            var usingdropdownname = uniqueid+'_usingdropdown';
            var usingdropdownelements = document.clustertreescope.clustertree_get_container_elements_by_name(instanceid, usingdropdownname);
            var usingdropdown = false;
            // store state
            for (var i = 0; i < usingdropdownelements.length; i++) {
                if (usingdropdownelements[i].value == '') {
                    usingdropdownelements[i].value = '1';
                    usingdropdown = true;
                } else {
                    usingdropdownelements[i].value = '';
                }
                break;
            }

            // change the button display text
            document.clustertreescope.clustertree_toggle_button_state(instanceid, uniqueid, dropdownbuttontext, treebuttontext, usingdropdown);

            // toggle dropdown state
            document.clustertreescope.clustertree_set_dropdown_state(instanceid, uniqueid, usingdropdown);

            // toggle tree state
            document.clustertreescope.clustertree_set_tree_state(instanceid, uniqueid, usingdropdown);
        },

        /**
         * Sets the state of all elements in the UI based on the current state
         * specified when setting data on the form
         * @param string instanceid Unique report identifier
         * @param string uniqueid The unique id used to identify the filter element
         * @param string dropdownbuttontext Text to be shown on button when dropdown is enabled
         * @param string treebuttontext Text to be shown on button when tree is enabled
         */
        clustertree_set_toggle_state : function(instanceid, uniqueid, dropdownbuttontext, treebuttontext) {
            var usingdropdownname = uniqueid+'_usingdropdown';
            var usingdropdownelements = document.clustertreescope.clustertree_get_container_elements_by_name(instanceid, usingdropdownname);
            var usingdropdownvalue = '';

            // determine if we are in dropdown mode
            for (var i = 0; i < usingdropdownelements.length; i++) {
                usingdropdownvalue = usingdropdownelements[i].value;
                break;
            }

            // disable the "inappropriate" element
            if (usingdropdownvalue == '1') {
                document.clustertreescope.clustertree_set_tree_state(instanceid, uniqueid, true);
            } else {
                document.clustertreescope.clustertree_set_dropdown_state(instanceid, uniqueid, false);
            }

            // set button text appropriately
            document.clustertreescope.clustertree_toggle_button_state(instanceid, uniqueid, dropdownbuttontext, treebuttontext, usingdropdownvalue == '1');
        },

        /**
         * Render the tree that represents the cluster tree
         * @param string instanceid Unique report identifier
         * @param string uniqueid The unique id used to identify the filter element
         * @param string treeobject The JSON string representing the tree object to be rendered
         * @param boolean executionmode The mode in which the report is being executed (constant)
         */
        clustertree_render_tree : function(instanceid, uniqueid, treeobject, executionmode) {
            window.yui2obj = window.yui2obj || Y.YUI2; // PM block

            var clusterparamtree = new window.yui2obj.widget.TreeView("cluster_param_tree_"+instanceid+"_"+uniqueid, treeobject.children);

            // set up dynamic loading, passing in the calculated unique id
            var dynamicloadfunc = function(node, fnloadcomplete) {
                document.clustertreescope.clustertree_loadnodedata(node, fnloadcomplete, uniqueid, clusterparamtree, instanceid, executionmode);
            };

            clusterparamtree.setDynamicLoad(dynamicloadfunc);

            // render the tree
            clusterparamtree.render();

            // set the selection in the UI
            document.clustertreescope.clustertree_tick_top_level_boxes(instanceid, uniqueid, clusterparamtree);

            // set up the onclick event for all top-level nodes
            var nodes = clusterparamtree.getNodesBy(this.clustertree_always_true);
            for (var i in nodes) {
                document.clustertreescope.clustertree_set_up_onclick(instanceid, nodes[i].contentElId, uniqueid, clusterparamtree);
            }
        }

    },
    {
        NAME : MODULEBASENAME,
        ATTRS : { }
    }
    );

    // Ensure that M.elis_program exists and that modulebase is initialised correctly
    M.elis_program = M.elis_program || {};

    /**
     * Entry point for clustertree module
     * @param string wwwroot the site's wwwroot setting
     * @param string instanceid the instance id
     * @param string uniqueid the unique filter id
     * @param object treeobj the tree object
     * @param string reportid the report id
     * @param string dropdownbuttontext the drop-down button text
     * @param string treebuttontext the tree button text
     * @param string script the backend script to make AJAX calls too
     * @return object the clustertree object
     */
    M.elis_program.init_clustertree = function(wwwroot, instanceid, uniqueid, treeobj, exmode, reportid, dropdownbuttontext, treebuttontext, script) {
        var args = { wwwroot: wwwroot, instanceid: instanceid, uniqueid: uniqueid, tree: treeobj, executionmode: exmode, reportid: reportid,
               dropdownbuttontext: dropdownbuttontext, treebuttontext: treebuttontext, script: script};
        return new MODULEBASE(args);
    }

}, '@VERSION@', { requires : ['base', 'dom', 'event', 'io', 'json', 'node', 'yui2-container', 'yui2-event', 'yui2-treeview'] }
);
