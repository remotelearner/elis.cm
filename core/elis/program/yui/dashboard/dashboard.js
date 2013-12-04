/**
 * Generic JavaScript methods for a association/selection page.  Allows
 * multiple items to be selected using checkboxes, and use AJAX to do
 * paging/searching while maintaining the selection.  The selection will be
 * submitted as a form fieled called '_selection', which will be a JSON-encoded
 * array.
 *
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
 * @package    elis_program
 * @subpackage curriculummanagement
 * @author     Remote-Learner.net Inc
 * @copyright  2013 onwards Remote-Learner {@link http://www.remote-learner.ca/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
YUI.add('moodle-elis_program-dashboard', function(Y) {
    /**
     * Toggle visible with state class name
     * @property TOGGLECOMPLETEDINITCLASSNAME
     * @type {String}
     * @default 'program-dashboard-togglecompleted'
     */
    var TOGGLEVISIBLEINITWITHSTATECLASSNAME = 'program-dashboard-togglevisibleinitwithstate';
    /**
     * This method calls the base class constructor
     * @method TOGGLEVISIBLEINITWITHSTATE
     */
    var TOGGLEVISIBLEINITWITHSTATE = function() {
        TOGGLEVISIBLEINITWITHSTATE.superclass.constructor.apply(this, arguments);
    };
    /**
     * @class TOGGLEVISIBLEINITWITHSTATE
     * @constructor
     * @extends Base
     */
    Y.extend(TOGGLEVISIBLEINITWITHSTATE, Y.Base, {
        /**
         * Entry method into this module.  Acts on the domready event to retrieve an element by name
         * @event click
         * @method initializer
         * @param {Object} params parameters passed into the module class
         */
        initializer : function(params) {
            // Create the element and set attributes
            var showhidecompletebutton = Y.Node.create('<input type="button" />').getDOMNode();

            // Set attributes
            showhidecompletebutton.value = params.buttonlabel;
            showhidecompletebutton.name = params.nameattr;

            // Add event listening
            var handlerparams = {'hidelabel': params.hidetext, 'showlabel': params.showtext, 'element': params.element};

            var elementnode = Y.one('#'+params.addbefore);
            elementnode.ancestor().insertBefore(showhidecompletebutton, elementnode);

            showhidecompletebutton = Y.one('input[name="'+params.nameattr+'"]');
            showhidecompletebutton.on('click', this.toggle_visible, this, handlerparams);
        },

        /**
         * This method saves the visiblity state of the learning plan page elements
         * @method save_learning_plan_visibility_state
         * @param {Object} Event object
         * @param {Array} Array of parameters passed to the event handler
         */
        save_learning_plan_visibility_state : function(e, handlerparam) {
            var userprefparam = 'crlm_learningplan_collapsed_curricula';
            var hiddenfield = 'collapsed';
            var datapart = handlerparam['element'].split('-');
            var item = datapart[1];
            var itemset = Y.one('#'+hiddenfield).getAttribute('value').split(',');
            var newitemset = new Array();
            var newitems = '';
            var itemfound = 0;

            // Check for existence of item in current collapsed data
            for (i = 0; i < itemset.length; i++) {
                if (item == itemset[i]) {
                    itemfound = 1;
                } else {
                    newitemset.push(itemset[i]);
                }
            }

            // If item was not found then let's add it
            if (itemfound == 0) {
                newitemset.push(item);
            }

            // Convert the collapsed data array into a comma separated string
            for (i=0; i < newitemset.length; i++ ) {
                if (newitemset[i] != '') {
                    newitems = newitems+newitemset[i]+',';
                }
            }

            // Save a local reference copy of the currently collapsed items
            Y.one('#'+hiddenfield).setAttribute('value', newitems);

            // Save the currently collapsed items to user preferences table
            var cfg = {
                method: 'GET',
                data: 'param='+userprefparam+'&value='+newitems,
                on: {
                    success: false,
                    failure: false
                },
                context: this
            };

            var requesturl = this.get('wwwroot')+'/elis/program/userprefset.php';
            Y.io(requesturl, cfg);
        },

        /**
         * toggleVisible and toggleVisibleInit are shamelessly borrowed from lib/javascript-static.js from Moodle.
         * @method toggle_visible
         * @param {Object} Event object
         * @param {Array} Array of parameters passed to the event handler
         * @return {Boolean} always returns false
         */
        toggle_visible : function(e, handlerparam) {
            // Obtain the program id
            var pos = handlerparam['element'].indexOf('-');
            var programid = handlerparam['element'].substr(pos + 1);

            // If on the dashboard, handle the complete button toggle
            var completebuttonname;
            if (programid == 'na') {
                completebuttonname = 'noncurriculacompletedbutton';
            } else {
                completebuttonname = 'curriculum'+programid+'completedbutton';
            }

            // Try to fetch the complete button
            var completebuttonsnodelist = Y.one('[name='+completebuttonname+']');
            var completebuttonnode = null;
            if (null != completebuttonsnodelist && completebuttonsnodelist.size >= 0) {
                completebuttonnode = completebuttonsnodelist[0];
            }

            // Standard show/hide button
            var elementnode = Y.one('#'+handlerparam['element']);
            var buttonnode = e.target;
            var regexp = new RegExp(' ?hide');

            if (elementnode.getAttribute('class').match(regexp)) {
                elementnode.setAttribute('class', elementnode.getAttribute('class').replace(regexp, ''));

                if (completebuttonnode != null) {
                    // Show the complete toggle button
                    completebuttonnode.setAttribute('class', '');
                }

                buttonnode.setAttribute('value', handlerparam['hidelabel']);
            } else {
                elementnode.setAttribute('class', elementnode.getAttribute('class')+' hide');

                if (completebuttonnode != null) {
                    // Hide the complete toggle button
                    completebuttonnode.setAttribute('class', 'hide');
                }

                buttonnode.setAttribute('value', handlerparam['showlabel']);
            }

            this.save_learning_plan_visibility_state(e, handlerparam);

            return false;
        },

    }, {
        NAME : TOGGLEVISIBLEINITWITHSTATECLASSNAME,
        ATTRS : {
            addbefore: '',
            nameattr: '',
            buttonlabel: '',
            hidetext: '',
            showtext: '',
            element: '',
            wwwroot: ''
        }
    });

    /**
     * Toggle completed init class name
     * @property TOGGLECOMPLETEDINITCLASSNAME
     * @type {String}
     * @default 'program-dashboard-togglecompleted'
     */
    var TOGGLECOMPLETEDINITCLASSNAME = 'program-dashboard-togglecompleted';
    /**
     * This method calls the base class constructor
     * @method TOGGLECOMPLETEDINIT
     */
    var TOGGLECOMPLETEDINIT = function() {
        TOGGLECOMPLETEDINIT.superclass.constructor.apply(this, arguments);
    };

    /**
     * @class TOGGLECOMPLETEDINIT
     * @constructor
     * @extends Base
     */
    Y.extend(TOGGLECOMPLETEDINIT, Y.Base, {
        /**
         * Entry method into this module.  Acts on the domready event to retrieve an element by name
         * @method initializer
         * @event click
         * @param {Object} params parameters passed into the module class
         */
        initializer : function(params) {
            // Create the element and set attributes
            var showhidecompletebutton = Y.Node.create('<input type="button" />');
            // Get the DOM node, used to change the element attributes. Needed for IE8
            var showhidecompletebuttondom = showhidecompletebutton.getDOMNode();

            var elementnode = Y.one('#'+params.addbefore);

            // Insert element into the DOM
            elementnode.ancestor().insertBefore(showhidecompletebuttondom, elementnode);

            // Set attributes
            showhidecompletebuttondom.value = params.buttonlabel;
            showhidecompletebuttondom.name = params.nameattr;
            showhidecompletebuttondom.id = params.nameattr;

            if (params.displayed) {
                // Allow for the button to be displayed
                showhidecompletebutton.show();
            } else {
                // Hide the button via css
                showhidecompletebutton.hide();
            }

            if ('false' == params.enabled) {
                // Set the disabled attribute to grey it out
                showhidecompletebuttondom.disabled = true;
            }

            // Add event listening
            var handlerparams = {
                'hidelabel': params.hidetext,
                'showlabel': params.showtext,
                'element': params.element,
                'programid': params.currid
            };

            showhidecompletebutton.on('click', this.toggle_completed_courses, this, handlerparams);

            // Add event listener to anchor tags
            var currlinknode = Y.one('#'+params.element).one('a[name='+params.element+']');
            if (null != currlinknode) {
                currlinknode.on('click', this.onclick_anchor_event, this, handlerparams);
            }

            var nalinknode = Y.one('#'+params.element).one('a[name=curriculum-false]');
            if (null != nalinknode) {
                nalinknode.on('click', this.onclick_anchor_event, this, handlerparams);
            }
        },

        /**
         * This method adds onclick event listeners to the achor tags in the dashboard page
         * @method add_onclick_event_to_anchor
         * @param {Array} Array of parameters passed to the event handler
         * @return {Void} always returns nothing
         */
        add_onclick_event_to_anchor : function(transactionid, arguments) {
            if (1 == arguments['showcompleted']) {
                return;
            }

            var linkpostfix = 'na' == arguments['programid'] ? 'false' : arguments['programid'];
            var linknode = Y.one('#curriculum-'+arguments['programid']).one('a[name=curriculum-'+linkpostfix+']');

            if (null != linknode) {
                linknode.on('click', this.onclick_anchor_event, this, arguments);
            }

            return;
        },

        /**
         * Event handlerer for curriculum anchor tags
         * @method onclick_anchor_event
         * @param {Object} Event object
         * @param {Array} Array of parameters passed to the event handler
         */
        onclick_anchor_event : function(e, handlerparam) {
            // Name of the button that could be used to toggle
            var buttonname;
            // Data needed by toggleCompletedCourses
            var data;

            if ('curriculum-na' == handlerparam.element) {
                // Non-program section
                buttonname = 'noncurriculacompletedbutton';
                data = 'curriculum-na';
            } else {
                // Specific program
                buttonname = handlerparam.element+'completedbutton';
                data = 'curriculum-' + handlerparam.element;
            }

            // Set an object similar to a normal event to fake out toggleCompletedCoruses
            var e = new Object();
            e.target = Y.one('input[type=button][name='+buttonname+']');

            // Call the toggle-via-button method
            this.toggle_completed_courses(e, handlerparam);
        },

        /**
         * Method that toggles the state of whether completed courses are being displayed
         * (called when the appropriate button is clicked)
         * @method toggle_completed_course
         * @param {Object} Event object
         * @param {Array} Array of parameters passed to the event handler
         */
        toggle_completed_courses : function(e, handlerparam) {
            // Find the list of enabled programs via the hidden field
            var displaycompletednode = Y.one('#displayedcompleted');

            // Convert hidden value to an array of program ids
            var valueattr = displaycompletednode.getAttribute('value');
            if ('' == valueattr) {
                var enabled = Array();
            } else {
                var enabled = valueattr.split(',');
            }

            // Determine which program we're currently toggling
            var programid = handlerparam['element'].split('-')[1];

            // Determine whether that program has already been selected
            var position = -1;
            for (i = 0; i < enabled.length; i++) {
                if (enabled[i] == programid) {
                    //already selected
                    position = i;
                    break;
                }
            }

            // Obtain the button element so we can change its text
            var buttonnode = e.target;

            // Parameter to pass to PHP script
            var showcompleted;

            if (position > -1) {
                // Already selected, so current action is hide
                enabled.splice(position, 1);
                showcompleted = 0;
                // Next action for this program will be a "show"
                buttonnode.setAttribute('value', handlerparam['showlabel']);
            } else {
                // Not already selected, so current action is show
                enabled[enabled.length] = programid;
                showcompleted = 1;
                // Next action for this program will be a "hide"
                buttonnode.setAttribute('value', handlerparam['hidelabel']);
            }

            // Update hidden element with new state
            displaycompletednode.setAttribute('value', enabled.join(','));

            // Path to our PHP script
            var cfg = {
                method: 'GET',
                data: 'programid='+programid+'&showcompleted='+showcompleted,
                on: {
                    success: this.toggle_completed_courses_callback,
                    end: this.add_onclick_event_to_anchor,
                    failure: false
                },
                arguments: {
                    'programid': programid,
                    'showcompleted': showcompleted,
                    'element': handlerparam['element'],
                    'hidelabel': handlerparam['hidelabel'],
                    'showlabel': handlerparam['showlabel']
                },
                context: this
            };

            var requesturl = this.get('wwwroot')+'/elis/program/togglecompletedcourses.php';
            Y.io(requesturl, cfg);
        },

        /**
         * Ajax callback for toggling the display of completed courses
         * @method toggle_completed_courses_callback
         * @param {Integer} The transaction's ID
         * @param {Object} The response object
         * @param {Array} An array containing the program id
         */
        toggle_completed_courses_callback : function(transactionid, response, arguments) {
            // Obtain the container div
            var divdom = Y.one('#curriculum-'+arguments['programid']).getDOMNode();
            // Fill in with HTML content from the PHP script
            divdom.innerHTML = response.responseText;
        },

    }, {
        NAME : TOGGLECOMPLETEDINITCLASSNAME,
        ATTRS : {
            addbefore: '',
            nameattr: '',
            buttonlabel: '',
            hidetext: '',
            showtext: '',
            element: '',
            displayed: '',
            enabled: '',
            wwwroot: '',
            currid: ''
        }
    });

    M.elis_program = M.elis_program || {};

    /**
     * Creates an instance of the TOGGLECOMPLETEDINIT class.
     * @method init_togglecomplete
     * @param {Object} params parameters passed into the module class
     * @return {Object} Returns an instance of TOGGLECOMPLETEDINIT
     */
    M.elis_program.init_togglecomplete = function(params) {
        return new TOGGLECOMPLETEDINIT(params);
    }

    /**
     * Creates an instance of the TOGGLEVISIBLEINITWITHSTATE class.
     * @method init_togglevisibleinitstate
     * @param {Object} params parameters passed into the module class
     * @return {Object} Returns an instance of TOGGLEVISIBLEINITWITHSTATE
     */
    M.elis_program.init_togglevisibleinitstate = function(params) {
        return new TOGGLEVISIBLEINITWITHSTATE(params);
    }
}, '@VERSION@', {
    requires:['base', 'event', 'node', 'io']
});
