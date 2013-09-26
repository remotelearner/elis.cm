/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2013 Remote Learner.net Inc (http://www.remote-learner.net)
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

YUI.add('moodle-elis_program-checkboxselection', function(Y) {

    /**
     * The checkboxselection module
     * @property MODULEBASENAME
     * @type {String}
     * @default 'program-checkboxselection'
     */
    var MODULEBASENAME = 'program-checkboxselection';

    /**
     * This method calls the base class constructor
     * @method MODULEBASE
     */
    var MODULEBASE = function() {
        MODULEBASE.superclass.constructor.apply(this, arguments);
    }

    /**
     * @class M.elis_program.checkboxselection
     */
    Y.extend(MODULEBASE, Y.Base, {

        /**
         * basepage url
         * @property basepage
         * @type {String}
         * @default ''
         */
        basepage: '',

        /**
         * divid
         * @property divid
         * @type {String}
         * @default ''
         */
        divid: '',

        /**
         * associateclass YUI object
         * @property associateclass
         * @type {Object}
         * @default null
         */
        associateclass: null,

        /**
         * current checkbox selections
         * @property selections
         * @type {Array}
         * @default []
         */
        selections: [],

        /**
         * lastrequest url
         * @property lastrequest
         * @type {String}
         * @default ''
         */
        lastrequest: '',

        /**
         * debug function
         * @property debugfcn
         * @type {Function}
         * @default void
         */
        debugfcn: function(x){ }, // console.log, alert

        /**
         * Initialize the checkboxselection module
         * @param onject args function arguments: {}
         */
        initializer : function(args) {
            this.basepage = args.basepage;
            this.divid = args.divid;
            var acparams = {basepage: this.basepage, divid: this.divid, context: this, loadlink: this.load_link, loadform: this.load_form};
            this.associateclass = M.elis_core.init_associateclass(acparams);
            var selectedonly = this.associateclass.get_element_by_name('selectedonly');
            selectedonly.delegate('click', this.change_selected_display, 'input[type=checkbox]', this);
            window.issubmitting = false;
            var beforeunload = function(ev, scope) {
                    scope.debugfcn('beforeunload: ev = '+ev+', scope = '+scope);
                    if (!window.issubmitting) {
                        scope.update_checkbox_selection();
                    }
            };
            Y.on('beforeunload', beforeunload, null, null, this); // breaks if context passed?
            this.setup_selectall_buttons();
            var submitbutton = document.getElementById('id_submitbutton');
            if (submitbutton) {
                submitbutton.onclick = function(e) {
                        window.issubmitting = true;
                };
            }
            var setcheckboxes = function(transid, resp) {
                    var selectedelem = document.getElementById('selected_checkboxes');
                    if (selectedelem) {
                        selectedelem.value = resp.responseText;
                    }
                    this.set_selected_checkboxes(resp.responseText);
            };
            var cfg = {
                method: 'GET',
                data: 'mode=bare&action=get_checkbox_selection',
                on: {
                    success: setcheckboxes
                },
                context: this
            };
            Y.io(this.basepage, cfg);
        },

        /**
         * setup select/deselect all button callbacks
         */
        setup_selectall_buttons : function() {
            var selectall = Y.one('#id_checkbox_selectall');
            if (selectall) {
                selectall.delegate('click', function(e) { this.checkbox_select(true); }, 'input[type=button]', this);
            }
            var deselectall = Y.one('#id_checkbox_deselectall');
            if (deselectall) {
                deselectall.delegate('click', function(e) { this.checkbox_select(false); }, 'input[type=button]', this);
            }
        },

        /**
         * event handler for links within the list_display div
         * @param object ev the event object
         */
        load_link : function(ev) {
            this.debugfcn('load_link');
            var target = ev.target; // TBD: Y.EventTarget.getTargets(ev).item(0);
            if (!target.getAttribute("href") || target.hasClass('moreless-toggler') || e.target.hasClass('fheader')) {
                return;
            }
            this.lastrequest = target.getAttribute("href");
            var selected = Y.JSON.stringify(this.selections);
            var data = 'mode=bare&selected_checkboxes='+selected;
            var selectedonly = this.associateclass.get_element_by_name('selectedonly');
            if (selectedonly.get('checked')) {
                data += '&_showselection='+selected;
            }
            var cfg = {
                method: 'POST',
                data: data,
                on: {
                    success: this.set_content,
                },
                context: this
            };
            Y.io(this.lastrequest, cfg);
            ev.preventDefault(); // TBD
        },

        /**
         * event handler for forms within the list_display div
         * @param object ev the event object
         */
        load_form : function(ev) {
            this.debugfcn('load_form');
            var target = ev.target; // TBD: Y.eventTarget.getTargets(ev).item(0);
            var targetname = target.getAttribute('name');
            // ELIS-8546: Moodle 2.5 filtering form buttons must be ignored
            if (targetname == 'addfilter' || targetname == 'removeall' || targetname == 'removeselected') {
                // console.log('checkboxselection::load_form(): targetname = '+targetname);
                return;
            }

            // var data = YAHOO.util.Connect.setForm(target);
            var link = target.getAttribute('action');
            var selected = Y.JSON.stringify(this.selections);
            this.lastrequest = link+'?mode=bare&selected_checkboxes='+selected; // TBD: link + '?' + data 
            var cfg = {
                method: 'POST',
                data: 'mode=bare&selected_checkboxes='+selected,
                on: {
                    success: this.set_content,
                },
                form: {
                    id: target
                },
                context: this
            };
            Y.io(link, cfg);
            ev.preventDefault(); // TBD
        },

        update_checkbox_selection : function() {
            var selected = Y.JSON.stringify(this.selections);
            var cfg = {
                method: 'POST',
                sync: true,
                data: 'action=checkbox_selection_session&selected_checkboxes='+selected,
                context: this
            };
            var request = Y.io(this.basepage, cfg);
        },

        change_selected_display : function () {
            var cfg = { method: 'POST', data: '', on: { success: this.set_content}, context: this};
            var data;
            var selectedonly = this.associateclass.get_element_by_name('selectedonly');
            if (selectedonly.get('checked')) {
                if (this.selections != null) {
                    data = this.selections.join(',');
                    if (!data) {
                        data = '';
                    }
                    cfg.data = 'mode=bare&_showselection=['+data+']&selected_checkboxes=['+data+']';
                    Y.io(this.basepage, cfg);
                }
            } else {
                cfg.data = 'mode=bare&selected_checkboxes='+Y.JSON.stringify(this.selections);
                Y.io(this.basepage, cfg);
            }
        },

        /**
         * set_content callback
         * @param {String} transid The ID of the transaction
         * @param {Object} resobj Object containing the response data.
         */
        set_content : function(transid, resp) {
            var div = document.createElement('div');
            div.id = this.divid;
            div.innerHTML = resp.responseText;
            var olddiv = document.getElementById(this.divid);
            olddiv.parentNode.replaceChild(div, olddiv);
            this.associateclass.make_links_internal(this.divid);
            this.setup_selectall_buttons();
            this.mark_selected();
            this.associateclass.run_inner_html_scripts(this.divid);
        },

        /**
         * set_selected_checkboxees
         * @param {String} sessionselection comma-separated list of checkbox ids
         */
        set_selected_checkboxes : function(sessionselection) {
            var checkedids = sessionselection.split(',');
            this.debugfcn('set_selected_checkboxes(): sessionselection = '+sessionselection);
            for (var i = 0; i < checkedids.length; i++) {
                if (checkedids[i]) {
                    var cb = this.associateclass.get_element_by_name('select'+ checkedids[i]);
                    if (cb) {
                        cb.set('checked', true);
                    }
                    this.selections.push(checkedids[i]);
                }
            }
            var selectionfield = document.getElementById('id__selection');
            selectionfield.value = '['+this.selections.join(',')+']';
            this.mark_selected();
        },

        /**
         * Function to add/remove selection to/from list of selected
         * and update the pages' 'numselected' element with total checkboxes selected.
         * @param int id  The element id of the checkbox entity
         */
        select_item : function(id) {
            if (this.associateclass.get_element_by_name('select'+id).get('checked')) {
                // Add checkbox selection
                if (this.checkbox_selection_index(id) == -1) {
                    this.selections.push(id);
                }
            } else {
                // Remove checkbox selection
                var pos = this.checkbox_selection_index(id);
                if (pos != -1) {
                    this.selections.splice(pos, 1);
                }
            }
            var selectionfield = document.getElementById('id__selection');
            selectionfield.value = '['+this.selections.join(',')+']';
            document.getElementById("numselected").innerHTML = this.selections.length;
        },

        /**
         * Function to get the index in the Javascript array of the specified id
         * @param int element  The element id of the checkbox entity
         * @return int  the javascript array index if found, -1 otherwise (not found)
         */
        checkbox_selection_index : function(element) {
            for (var i = 0; i < this.selections.length; i++) {
                if (this.selections[i] == element) {
                    return i;
                }
            }
            return -1;
        },

        /**
         * when the table is loaded, mark which elements have already been selected
         */
        mark_selected : function() {
            var table = Y.one('#selectiontable');
            var numselected = 0;
            if (table) {
                var scope = this;
                table.all('input[type=checkbox]').each(function(el) {
                    var id = el.getAttribute('name').substr(6);
                    if (scope.checkbox_selection_index(id) == -1) {
                        el.set('checked', false);
                    } else {
                        el.set('checked', true);
                    }
                    if (el.get('checked')) numselected++;
                    el.delegate('click', function(e) { this.select_item(id); }, 'input[type=checkbox]', scope);
                });
            }
            var sessionselection = document.getElementById('selected_checkboxes');
            var length = 0;
            if (sessionselection && sessionselection.value) {
                length = sessionselection.value.split(',').length;
                this.debugfcn('checkbox_selection.js::mark_selected(): length = '+ length);
            }

            document.getElementById("numonotherpages").innerHTML = (length - numselected);
            document.getElementById("numselected").innerHTML = length;

            if (length != numselected) {
                document.getElementById("selectedonotherpages").style.display = 'inline';
            } else {
                document.getElementById("selectedonotherpages").style.display = 'none';
            }
        },

        /**
         * Function to check/uncheck all input elements with 'selectiontable'
         * @param mixed checked what to set input elements 'checked' attribute to
         */
        checkbox_select: function(checked) {
            var table = Y.one('#selectiontable');
            if (table) {
                var scope = this;
                table.all('input[type=checkbox]').each(function(el) {
                    el.set('checked', checked);
                    id = el.getAttribute('name').substr(6);
                    scope.select_item(id);
                });
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
     * Entry point for checkboxselection module
     * @param string basepage the basepage url
     * @param string divid the div id for internal links
     * @return object the checkboxselection object
     */
    M.elis_program.init_checkboxselection = function(basepage, divid) {
        var args = { basepage: basepage, divid: divid };
        return new MODULEBASE(args);
    }

}, '@VERSION@', { requires : ['moodle-elis_core-associateclass', 'json'] }
);
