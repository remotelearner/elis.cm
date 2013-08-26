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

YUI.add('moodle-elis_program-menuitem', function(Y) {

    /**
     * The menuitem module
     * @property MODULEBASENAME
     * @type {String}
     * @default 'program-menuitem'
     */
    var MODULEBASENAME = 'program-menuitem';

    /**
     * This method calls the base class constructor
     * @method MODULEBASE
     */
    var MODULEBASE = function() {
        MODULEBASE.superclass.constructor.apply(this, arguments);
    }

    /**
     * @class M.elis_program.menuitem
     */
    Y.extend(MODULEBASE, Y.Base, {

        /**
         * Initialize the menuitem module
         * @param onject args function arguments: {tree, wwwroot}
         */
        initializer : function(args) {
            document.wwwroot = args.wwwroot;
            var treeobj = Y.JSON.parse(args.tree);
            this.render_curr_admin_tree(treeobj);
        },

        /**
         * Function for handling dynamic node loading
         * @param Object   node            The node whose children we are loading
         * @param function fcnloadcomplete The function to call which will signify that loading is done
         */
        load_node_data : function(node, fcnloadcomplete) {
            var cfg = {
                method: 'GET',
                data: 'data='+node.contentElId,
                on: {
                    // success function
                    success: function(transid, o) {
                        // YUI can fire multiple expand events for the same node
                        // so make sure it hasn't already been loaded
                        if (o.responseText != '' && node.children == '') {
                            var responseobj = Y.JSON.parse(o.responseText);
                            // loop through and append new child nodes
                            for (var i = 0; i < responseobj.children.length; i++) {
                                var childobj = responseobj.children[i];
                                // this actually creates the node in the menu
                                var newNode = new window.yui2obj.widget.TextNode(childobj.label, node);
                                // information about parent elements is held in this value
                                newNode.contentElId = childobj.contentElId;

                                // CSS styling
                                newNode.labelStyle = childobj.labelStyle;

                                // specifies if we should not show the + icon
                                newNode.isLeaf = childobj.isLeaf;
                            }
                        }

                        // indicate that loading is complete
                        fcnloadcomplete();
                    },

                    // failure function
                    failure: function(transid, o) {
                        // DO NOT warn the user in any way because this failure can happen
                        // in an innocuous way if you navigate to another page while the menu is loading

                        // indicate that loading is complete
                        fcnloadcomplete();
                    }
                },
                context: this
            };

            if (node.contentElId != "") {
                // URL of our script (wwwroot is pre-set by the calling PHP script)
                var url = document.wwwroot+'/blocks/curr_admin/load_menu.php';
                // make the actual call
                Y.io(url, cfg);
            } else {
                // nothing to load
                fcnloadcomplete();
            }
        },

        /**
         * Render the tree that represents the curriculum admin menu
         * @param object treeobj The object representing tree contents
         */
        render_curr_admin_tree : function(treeobj) {
            window.yui2obj = window.yui2obj || Y.YUI2; // clustertree

            /**
             * Override YUI functionality to not escape HTML tags
             * todo: convert menuitem code to user href attribute rather than HTML content
             */
            window.yui2obj.widget.TextNode.prototype.getContentHtml = function() {
                var sb = [];

                sb[sb.length] = this.href ? '<a' : '<span';
                sb[sb.length] = ' id="'+Y.Escape.html(this.labelElId)+'"';
                sb[sb.length] = ' class="'+Y.Escape.html(this.labelStyle)+'"';
                if (this.href) {
                    sb[sb.length] = ' href="'+Y.Escape.html(this.href)+'"';
                    sb[sb.length] = ' target="'+Y.Escape.html(this.target)+'"';
                }
                if (this.title) {
                    sb[sb.length] = ' title="'+Y.Escape.html(this.title)+'"';
                }
                sb[sb.length] = ' >';
                sb[sb.length] = this.label;
                sb[sb.length] = this.href ? '</a>' : '</span>';
                return sb.join("");
            };

            var curradmintree = new window.yui2obj.widget.TreeView("block_curr_admin_tree", treeobj.children);

            // set up dynamic loading
            curradmintree.setDynamicLoad(this.load_node_data);
            curradmintree.render();
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
     * Entry point for menuitem module
     * @param object treeobj the tree object
     * @param string wwwroot the site's wwwroot setting
     * @return object the menuitem object
     */
    M.elis_program.init_menuitem = function(treeobj, wwwroot) {
        var args = { tree: treeobj, wwwroot: wwwroot};
        return new MODULEBASE(args);
    }

}, '@VERSION@', { requires : ['base', 'dom', 'escape', 'event', 'io', 'json', 'node', 'yui2-treeview'] }
);

