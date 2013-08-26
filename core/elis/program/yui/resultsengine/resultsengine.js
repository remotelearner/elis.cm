/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 */

/**
 * YUI3 module to handle the functionality of the results engine from from selecting tracks and classes
 * from a YUI Panel, to event handlers and the inserting of hidden elements into the DOM.
 */
YUI.add('moodle-elis_program-resultsengine', function(Y) {
    /**
     * Toggle completed init class name
     * @property PROCESSMANUALCLASSNAME
     * @type {String}
     * @default 'program-resultsengine-processmanual'
     */
    var PROCESSMANUALCLASSNAME = 'program-resultsengine-processmanual';

    /**
     * This method calls the base class constructor
     * @method DELETEROW
     */
    var PROCESSMANUAL = function() {
        PROCESSMANUAL.superclass.constructor.apply(this, arguments);
    };

    /**
     * @class DELETEROW
     * @constructor
     * @extends Base
     */
    Y.extend(PROCESSMANUAL, Y.Base, {
        /**
         * Entry method into this module.  Acts on the domready event to retrieve an element by name
         * @method initializer
         * @param {Object} params Parameters passed into the module class
         */
        initializer : function(params) {
            var uri = params.source +"?id="+params.id;
            var request = Y.io(uri);

            var cfg = {
                method: 'GET',
                data: 'id='+params.id,
                on: {
                    complete: this.complete_message,
                },
                arguments: {
                    'message': params.message
                },
                context: this
            };

            Y.io(params.source, cfg);
        },

        /**
         * Ajax callback for toggling the display of completed courses
         * @method complete_message
         * @param {Integer} transactionid The transaction's ID
         * @param {Object} response The response object
         * @param {Array} arguments An array containing the program id
         */
        complete_message : function(transactionid, response, arguments) {
            var divnode = Y.one('#results');
            var message = arguments['message'];

            if (response.status != 200) {
                message = message+"<br />\n"+response.statusText;
            } else {
                message = message+"<br />\n"+response.responseText;
            }

            divnode.setHTML(message);
        }
    }, {
        NAME : PROCESSMANUALCLASSNAME,
        ATTRS : {
            source: '',
            id: '',
            message: ''
        }
    });

    /**
     * Toggle completed init class name
     * @property RESULTSENGINEFORMCLASSNAME
     * @type {String}
     * @default 'program-resultsengine-resultsengineform'
     */
    var RESULTSENGINEFORMCLASSNAME = 'program-resultsengine-resultsengineform';

    /**
     * This method calls the base class constructor
     * @method RESULTSENGINEFORM
     */
    var RESULTSENGINEFORM = function() {
        RESULTSENGINEFORM.superclass.constructor.apply(this, arguments);
    };

    /**
     * Popup panel modal used for selecting a track
     * @property selectorpanelmodal
     * @type {Object}
     * @default null
     */
    var selectorpanelmodal = null;

    /**
     * The type of selector being used 'track' or 'class'
     * @property selectortype
     * @type {String}
     * @default empty string
     */
    var selectortype = '';

    /**
     * The URL to see all tracks or classes
     * @property seeallrequesturl
     * @type {String}
     * @default empty string
     */
    var seeallrequesturl = '';

    /**
     * The URL to process the user's user profile selection
     * @property resultsprofileurl
     * @type {String}
     * @default empty string
     */
    var resultsprofileurl = '';

    /**
     * The parent used to render panel markup
     * @property selectorpanelmodalnode
     * @type {Object}
     * @default null
     */
    var selectorpanelmodalnodeprop = null;

    /**
     * @class RESULTSENGINEFORM
     * @constructor
     * @extends Base
     */
    Y.extend(RESULTSENGINEFORM, Y.Base, {
        /**
         * Entry method into this module.  Acts on the domready event to retrieve an element by name
         * @method initializer
         * @event click
         * @event change
         * @param {Object} params Parameters passed into the module class
         */
        initializer : function(params) {
            var resultsengineformnodes = Y.one('#region-main');
            // Add event handlers on all anchor tags whose name property is 'typeselector'
            resultsengineformnodes.delegate('click', this.selector_click, 'a[name=typeselector]', this);

            // Add an event handler to the user profile fields drop down
            resultsengineformnodes.delegate('change', this.userprofile_selector_change, 'select[id^=id_profile_]', this);

            // Remove onchange handlers
            var userprofileselectornodes = resultsengineformnodes.all('select[id^=id_profile_]');
            userprofileselectornodes.each(this.change_userprofile_rows, this);

            // Add an event handler to the 'activate results engine' checkbox
            var checkbox = resultsengineformnodes.one('#id_active');
            if (null != checkbox) {
                checkbox.setAttribute('onchange', '');
                checkbox.on('click', this.disable_form, this, resultsengineformnodes);
            }
        },

        /**
         * This function finds the last index of '_' in a string and returns a substring up to that index
         * @method get_substring_upto_last_underscore
         * @param {String} data A string to process
         * @return {String} A sub string
         */
        get_substring_upto_last_underscore : function(data) {
            // Get the last indext of '_'
            var index = data.lastIndexOf('_');
            // Get a substring
            return data.substr(0, index + 1);
        },

        /**
         * This callback function sends an AJAX call to process the user's userprofile selection
         * @method userprofile_selector_change
         * @param {Object} e An event object
         */
        userprofile_selector_change : function(e) {
            // Get the name of the drop down
            var selectorname = this.get_substring_upto_last_underscore(e.target.getAttribute('name'));

            // Make an AJAX call to process the user's action
            var cfg = {
                method: 'GET',
                data: 'id='+e.target.getDOMNode().value+'_&name='+selectorname+'value',
                on: {
                    success: this.process_user_userprofile_selection,
                    failure: false
                },
                arguments: {
                    'elementid': selectorname+'frame'
                },
                context: this
            };

            Y.io(this.resultsprofileurl, cfg);
        },

        /**
         * This function makes an AJAX call process the user's user profile selection
         * @method process_user_userprofile_selection
         * @param {Integer} transactionid The transaction's ID
         * @param {Object} response The response object
         * @param {Array} arguments An array of arguments
         */
        process_user_userprofile_selection : function(transactionid, response, arguments) {
            Y.one('#'+arguments['elementid']).setHTML(response.responseText);
        },

        /**
         * This methods calls functions to read the node's onchange event, parset the method arguments from it and set a class property.
         * Lastly the original onchange event is removed.
         * @method change_userprofile_rows
         * @param {Object} node A YUI Node object
         * @return {Integer} Returns a zero if something went wrong
         */
        change_userprofile_rows : function(node) {
            // Parse the arguments from the onchange event
            var cleandata = this.parse_userprofile_selector_onchange_arguments(node);

            // Check if the class property has already been set
            if (0 != Y.Array(cleandata).length && '' != this.resultsprofileurl) {
                this.resultsprofileurl = cleandata[0];
            }

            // Remove the onchange event
            node.setAttribute('onchange', '');

            return 1;
        },

        /**
         * This function reads the nodes onchange method, extracts the arguments and calls a function
         * @method parse_userprofile_selector_onchange_arguments
         * @param {Object} node A YUI Node object
         * @return {Array} Returns an array containing the arguments, or an empty array if something went wrong
         */
        parse_userprofile_selector_onchange_arguments : function(node) {
            // Retrieve the element id
            var data = node.getAttribute('onchange').split(",");

            // Check if array contains three elements, that means there were three parameters
            if (4 != Y.Array(data).length) {
                return new Array();
            }

            var i = 0;
            var cleandata = new Array();

            for (i = 0; i < data.length; i++) {
                cleandata[i] = this.get_data_inbetween_single_quotes(data[i], false);
            }

            return cleandata;
        },

        /**
         * This callback function disables all of the input and select elements on the form
         * @method disable_form
         * @param {Object} e An event object
         * @param {Object} parentnode A YUI node encopassing the results engine page
         */
        disable_form : function(e, parentnode) {
            if (false == e.target.get('checked')) {
                parentnode.all('input').each(
                        function (node) {
                            if ('submit' != node.getAttribute('type') && 'hidden' != node.getAttribute('type')
                                    && 'id_active' != node.getAttribute('id') && 'active' != node.getAttribute('name')) {
                                node.set('disabled', 'disabled');
                            }
                        }
                );

                parentnode.all('select').each(
                        function (node) {
                            node.set('disabled', 'disabled');
                        }
                );
            } else {
                parentnode.all('input').each(
                        function (node) {
                            if ('submit' != node.getAttribute('type') && 'hidden' != node.getAttribute('type')
                                    && 'id_active' != node.getAttribute('id') && 'active' != node.getAttribute('name')) {
                                node.set('disabled', '');
                            }
                        }
                );

                parentnode.all('select').each(
                        function (node) {
                            node.set('disabled', '');
                        }
                );
            }
        },

        /**
         * Event handler for when a 'select track' or 'select class' link is clicked
         * @method selector_click
         * @param {Object} e An event object
         * @return {Void} Will return void if the desired target element does not exist, or does not contain the presumed characters
         */
        selector_click : function(e) {
            if (!this.selectorpanelmodal) {
                // Create panel modal
                var panel = new Y.Panel({
                    width  : 500,
                    height : 400,
                    zIndex : 5,
                    modal  : true,
                    render : '#selectorpanelmodal',
                    visible: false,
                    centered: true
                });

                // Save the selector panel instance
                this.selectorpanelmodal = panel;
            }

            // Retrieve the hidden input id specifying the track selector
            var selectorid = e.target.ancestor().one('input[type=hidden][id^=id_track][id$=selected]');
            var selectortype = 'track';

            // If track selector is not null then retrieve the id value, else check if it's a class selector type
            if (null != selectorid) {
                selectorid = selectorid.getAttribute('id');
            } else {
                selectorid = e.target.ancestor().one('input[type=hidden][id^=id_class][id$=selected]');
                selectortype = 'class';

                if (null != selectorid) {
                    selectorid = selectorid.getAttribute('id');
                } else {
                    return;
                }
            }

            this.selectortype = selectortype;

            // Find the last occurance of an underscore and discard it if exists
            var index = selectorid.lastIndexOf('_');

            if (-1 == index) {
                return ;
            }

            var cfg = {
                method: 'GET',
                data: 'id='+selectorid.substr(0, index)+'_&callback=add_selection',
                on: {
                    success: this.render_selector_modal,
                    failure: false
                },
                context: this
            };

            var requesturl = this.get('wwwroot')+'/elis/program/'+selectortype+'selector.php';
            Y.io(requesturl, cfg);
        },

        /**
         * Call back method to handle the rendering of the modal content
         * @method render_selector_modal
         * @event click
         * @event submit
         * @event mouseenter
         * @param {Integer} transactionid The transaction's ID
         * @param {Object} response The response object
         * @param {Array} arguments An array containing the program id
         */
        render_selector_modal : function(transactionid, response, arguments) {
            this.selectorpanelmodal.setStdModContent(Y.WidgetStdMod.BODY, response.responseText, Y.WidgetStdMod.REPLACE);
            this.selectorpanelmodal.show();

            // Add a scroll bar to be displayed if the listing it larger than the panel height
            Y.one('.yui3-widget-bd').setStyle('max-height', '347px');
            Y.one('.yui3-widget-bd').setStyle('overflow-y', 'auto');
            var selectorpanelmodalnode = Y.one('#selectorpanelmodal');
            this.selectorpanelmodalnodeprop = selectorpanelmodalnode;

            // Convert all entity links to contain hidden element nodes
            selectorpanelmodalnode.delegate('mouseenter', this.preprocess_entity_links, 'table', this);
            // Add event listeners to the various elements on the panel
            selectorpanelmodalnode.delegate('click', this.anchor_click_selector_modal, 'a', this);
            selectorpanelmodalnode.delegate('submit', this.search_form_submit_selector_modal, 'form', this);
            selectorpanelmodalnode.delegate('click', this.search_form_show_all, 'input[type=button]', this);
        },

        /**
         * This function retrieves a nodelist for anchor tags within the panel markup and calls convert_entry_links
         * This is done becase when the track and class selector scripts, they render the anchor tags with
         * unused onclick event calls
         * @method preprocess_entity_links
         * @param {Object} e An event object
         */
        preprocess_entity_links : function(e) {
            var nodelist = this.selectorpanelmodalnodeprop.all('a[href="#"]');
            nodelist.each(this.convert_entity_links, this);
        },

        /**
         * This function removes the onclick method and adds hidden input elements, to the DOM, containing information necessary to
         * add the entity to the results engine form
         * @method convert_entity_links
         * @param {Object} node An anchor node
         * @return {Integer} 0 if the number of parameters in the onclick method isn't 3
         */
        convert_entity_links : function(node) {
            // If on click attribute is empty, then it's already been converted
            if ('' == node.getAttribute('onclick')) {
                return 0;
            }

            // Retrieve the element id
            var data = node.getAttribute('onclick').split(",");

            // Check if array contains three elements, that means there were three parameters
            if (3 != Y.Array(data).length) {
                return 0;
            }

            var i = 0;
            var cleandata = new Array();

            for (i = 0; i < data.length; i++) {
                cleandata[i] = this.get_data_inbetween_single_quotes(data[i], true);
            }

            // Create hidden elements and add them to the DOM
            var hiddenelement = Y.Node.create('<input>');
            hiddenelement.setAttribute('type', 'hidden');
            hiddenelement.setAttribute('id', this.selectortype+'_'+cleandata[2]+'_elementid');
            hiddenelement.setAttribute('name', this.selectortype+'_elementid');
            hiddenelement.setAttribute('value', cleandata[0]);
            node.ancestor().appendChild(hiddenelement);

            hiddenelement = Y.Node.create('<input>');
            hiddenelement.setAttribute('type', 'hidden');
            hiddenelement.setAttribute('id', this.selectortype+'_'+cleandata[2]+'_elemententityid');
            hiddenelement.setAttribute('name', this.selectortype+'_elemententityid');
            hiddenelement.setAttribute('value', cleandata[2]);
            node.ancestor().appendChild(hiddenelement);

            // Remove onclick event
            node.setAttribute('onclick', '');
        },

        /**
         * This callback method sends an AJAX call to show all entity in the popup modal
         * @method search_form_show_all
         * @param {Object} e An event object
         */
        search_form_show_all : function(e) {
            var cfg = {
                method: 'GET',
                on: {
                    success: this.render_selector_modal_content,
                    failure: false
                },
                context: this
            };

            Y.io(this.seeallrequesturl, cfg);

            e.preventDefault();
        },

        /**
         * Call back method to process the user's search criteria submit
         * @method search_form_submit_selector_modal
         * @param {Object} e An event object
         */
        search_form_submit_selector_modal : function(e) {
            var actionurl = e.target.getAttribute('action');

            var cfg = {
                method: 'POST',
                form: {
                    id: e.target.getAttribute('id'),
                    useDisabled: false
                },
                on: {
                    success: this.render_selector_modal_content,
                    failure: false
                },
                context: this
            };

            Y.io(actionurl, cfg);
            e.preventDefault();
        },

        /**
         * Call back method to process the user clicks on anchor tags in the selector modal
         * @method anchor_click_selector_modal
         * @param {Object} e An event object
         * @return {Void} returns void if anchor tag href equals #
         */
        anchor_click_selector_modal : function(e) {
            // Check if href="#"
            if ('#' == e.target.getAttribute('href')) {
                // Retrieve hidden element data from panel and update entity form
                var entityformelementnode = e.target.ancestor().one('[name='+this.selectortype+'_elementid]');
                Y.one('#'+entityformelementnode.getAttribute('value')+'label').setAttribute('value', Y.Lang.trim(e.target.getHTML()));

                entityselectorelementid = e.target.ancestor().one('[name='+this.selectortype+'_elemententityid]').getAttribute('value');
                Y.one('#'+entityformelementnode.getAttribute('value')+'selected').setAttribute('value', entityselectorelementid);

                this.selectorpanelmodal.hide();
                return;
            }

            var cfg = {
                method: 'GET',
                on: {
                    success: this.render_selector_modal_content,
                    failure: false
                },
                context: this
            };

            var requesturl = e.target.getAttribute('href');
            Y.io(requesturl, cfg);
            e.preventDefault();
        },

        /**
         * Call back method to set the body of the modal content.  This method also removes the onclick method for the
         * See all button and saves the event URL to the class method.  The reason for this is that the original onclick event
         * uses document.location which will force the browser to reload, taking the user away from the results engine page.
         * @method render_selector_modal_content
         * @param {Integer} transactionid The transaction's ID
         * @param {Object} response The response object
         * @param {Array} arguments An array of arguments
         */
        render_selector_modal_content : function(transactionid, response, arguments) {
            this.selectorpanelmodal.setStdModContent(Y.WidgetStdMod.BODY, response.responseText, Y.WidgetStdMod.REPLACE);

            // Remove document.location onclick call
            var selectorpanelmodalnode = Y.one('#selectorpanelmodal');

            if (null !== selectorpanelmodalnode) {
                var seeallbuttonnode = selectorpanelmodalnode.one('input[type=button]');

                if (null !== seeallbuttonnode) {

                    this.seeallrequesturl = this.get_data_inbetween_single_quotes(seeallbuttonnode.getAttribute('onclick'), true);
                    seeallbuttonnode.setAttribute('onclick', '');
                }
            }
        },

        /**
         * This method parses a string returns only the content inbetween the single quotes
         * @method get_data_inbetween_single_quotes
         * @param {String} onclick string containing content within single quotes
         * @param {Boolean} single A string containg the the type of quote whether single or double
         * @return {String}|{Void} returns the string within the single quotes, otherwise an empty string
         */
        get_data_inbetween_single_quotes : function(onclick, single) {
            var quote = '';

            if (single) {
                quote = "'";
            } else {
                quote = '"';
            }

            var needle = onclick.indexOf(quote);

            if (-1 == needle) {
                return '';
            }

            var data = onclick.substr(needle + 1);
            var needle = data.lastIndexOf(quote);

            if (-1 == needle) {
                return '';
            }

            data = data.substr(0, needle);

            return data;
        }
    }, {
        NAME : RESULTSENGINEFORMCLASSNAME,
        ATTRS : {
            wwwroot: ''
        }
    });

    M.elis_program = M.elis_program || {};

    /**
     * Creates an instance of the init_selector class.
     * @method init_resultsengineform
     * @param {Object} params parameters passed into the module class
     * @return {Object} Returns an instance of init_selector
     */
    M.elis_program.init_resultsengineform = function(params) {
        return new RESULTSENGINEFORM(params);
    }

    /**
     * Creates an instance of the PROCESSMANUAL class.
     * @method init_processmanual
     * @param {Object} params parameters passed into the module class
     * @return {Object} Returns an instance of PROCESSMANUAL
     */
    M.elis_program.init_processmanual = function(params) {
        return new PROCESSMANUAL(params);
    }
}, '@VERSION@', {
    requires:['base', 'event', 'node', 'io', 'panel', 'widget']
});