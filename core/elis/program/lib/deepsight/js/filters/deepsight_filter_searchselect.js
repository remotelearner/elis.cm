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
 * @copyright  (C) 2013 Remote Learner.net Inc http://www.remote-learner.net
 * @author     James McQuillan <james.mcquillan@remote-learner.net>
 *
 */

(function($) {

/**
 * DeepSight SearchSelect filter
 * This filter allows filtering based on one or more choices. The filter also allows searching possible options
 *
 * Usage:
 *     $('#element').deepsight_filter_searchselect();
 *     Note: All elements this is used on must have an "id" attribute!
 *
 * Required Options:
 *     object datatable                A deepsight datatable object.
 *     string name                     A unique identifier that refers to this filter.
 *     string dataurl                  A URL to query when doing searching within this filter (i.e. on the dropdown)
 *
 * Optional Options:
 *     string label                    The label of the filter - appears in the filter button.
 *     array  initialchoices           Array of objects containing "id" and "label" properties which serve as default choices
 *     string filter_cssclass          Custom CSS Class to add to the filter.
 *     string filter_active_cssclass   A CSS class to add to the filter button when the filter is clicked.
 *     string filter_dropdown_cssclass Custom CSS class to add to the filter dropdown.
 *     string css_filterdelete_class   A CSS class to add to the filter's remove button.
 *     string lang_search              Language string used for the filter's "search" box.
 *     string lang_selected            Language string used for the "selected" portion of the dropdown.
 *     string lang_all                 Language string for "all"
 *
 * @param object options Options object (See Options section above for description)
 */
$.fn.deepsight_filter_searchselect = function(options) {
    this.default_opts = {
        // required options
        datatable: null,
        name: null,
        dataurl: null,
        // optional options
        label: 'Filter',
        initialchoices: [],
        filter_cssclass: 'deepsight_filter-searchselect',
        filter_active_cssclass: 'active',
        filter_dropdown_cssclass: 'deepsight_filter_dropdown',
        css_filterdelete_class: 'deepsight_filter-remove',
        lang_search: 'Search: ',
        lang_selected: 'Selected: ',
        lang_all: 'All'
    }

    var opts = $.extend({}, this.default_opts, options);
    var main = this;
    var eleid = main.prop('id');

    this.name = opts.name;
    this.type = 'searchselect';
    this.selections = {};
    this.removebutton = null;

    // The last ajax request made.
    this.last_req = null;
    this.searchval = '';

    /**
     * Stores/removes the value locally and in the linked datatable
     *
     * This is the click action for a checkbox in the dropdown.
     *
     * @param object checkbox The jQuery object of the checkbox that was clicked.
     */
    this.updateselections = function(checkbox) {
        if (checkbox.prop('checked') == true) {
            main.selections[checkbox.val()] = {id:checkbox.val(), label:checkbox.prop('title')}
            opts.datatable.filter_add(main.name, checkbox.val());
        } else {
            delete main.selections[checkbox.val()];
            opts.datatable.filter_remove(main.name, checkbox.val());
        }

        ds_debug('[filter_searchselect.updateselections] Updated selections for filter "'+main.name+'" with data: ', main.selections);

        opts.datatable.updatetable();
        main.updatelabel();
    }

    /**
     * Updates the label of the filter.
     *
     * Updates display of the filter - (i.e. changes "all" to "choice1, choice2..." etc). Run on choice selection/deselection
     */
    this.updatelabel = function() {
        var label = '';
        for (var i in main.selections) {
            if (label != '') {
                label += ', ';
            }
            label += main.selections[i].label;
        }
        if (label == '') {
            label = 'All';
        }
        label = opts.label+': '+label+' &#x25BC;';
        main.html(label);
    }

    /**
     * Sets the available choices. Called when setting the inital choices, and when doing a search or reset.
     *
     * @param array   choices       An array of objects representing choices.
     *                                  Object params:
     *                                  mixed id     The value of the choice - i.e. what should be filtered on when this
     *                                               is selected.
     *                                  string label The label of the choice - what appears next to the checkbox.
     * @param bool hideselected Whether to hide selected options.
     */
    this.set_choices = function(choices, hideselected) {
        var options = main.dropdown.children('.options');
        var optionhtml = '';

        for(var i in choices) {
            if (typeof(main.selections[choices[i].id]) == 'undefined') {
                // choice has not been selected
                optionhtml += main.render_choice(choices[i].id, choices[i].label);
            } else {
                // choice has been selected
                if (hideselected != true) {
                    optionhtml += main.render_choice(choices[i].id, choices[i].label, true);
                }
            }
        }

        // populate div
        options.html(optionhtml);

        // update checkbox click action
        options.find('input[type="checkbox"]').click(function(e) {
            main.updateselections($(this));
        });
    }

    /**
     * Renders a single choice in the dropdown.
     *
     * @param mixed  id      The value of the choice - i.e. what should be filtered on when this is selected.
     * @param string label   The label of the choice - what appears next to the checkbox.
     * @param bool   checked Whether the choice is currently selected/being filtered on.
     */
    this.render_choice = function(id, label, checked) {
        checkedstr = (checked == true) ? 'checked="checked"' : '';

        var renderedhtml = '<input type="checkbox" id="'+eleid+'_choices_'+id+'" title="'+label+'" name="'+eleid+'_choices[]" ';
        renderedhtml += 'value="'+id+'" '+checkedstr+'/>'+'<label for="'+eleid+'_choices_'+id+'">'+label+'</label>';

        return renderedhtml;
    }

    /**
     * Renders currently selected choices from main.selections.
     */
    this.set_selections = function() {
        var selectionsele = main.dropdown.children('.selections');
        var selectionhtml = '';
        for (var i in main.selections) {
            selectionhtml += main.render_choice(main.selections[i].id, main.selections[i].label, true);
        }
        if (selectionhtml != '') {
            selectionhtml = '<span class="selected_title">'+opts.lang_selected+'</span>'+selectionhtml;
            selectionsele.show().html(selectionhtml);
            selectionsele.find('input[type="checkbox"]').click(function(e) {
                main.updateselections($(this));
            });
        } else {
            selectionsele.empty().hide();
        }
    }

    /**
     * Resets the filter to the initial state.
     */
    this.reset_choices = function() {
        main.set_selections();
        main.set_choices(opts.initialchoices, true);
        main.dropdown.children('.filter_search').children('input[type="text"]').val('');
    }

    /**
     * Initializes a search for potential filterable values.
     *
     * This sets a timer to actually perform the search 250ms from now and cancels any existing timers
     * This is to prevent multiple ajax requests from firing when someone is typing.
     *
     * @param string val The value to search for.
     */
    this.search = function(val) {
        if (main.last_req != null) {
            clearTimeout(main.last_req);
        }
        main.searchval = val;
        main.last_req = setTimeout(main.dosearch, 250);
    }

    /**
     * Performs a search for potential filterable values.
     *
     * @param string val The value to search for.
     */
    this.dosearch = function() {
        if (main.searchval != '') {
            $.ajax({
                type: 'GET',
                url: opts.dataurl,
                data: {val: main.searchval, filtername: main.name},
                dataType: 'text',
                success: function(data) {
                    try {
                        data = ds_parse_safe_json(data);
                    } catch(err) {
                        return false;
                    }
                    main.dropdown.children('.selections').empty().hide();
                    main.set_choices(data);
                }
            });
        } else {
            main.reset_choices();
        }
    }

    /**
     * Notify the datatable that this filter has been added.
     *
     * This is used when added columns to the datatable when we add new filters dynamically.
     */
    this.register_with_datatable = function() {
        opts.datatable.filter_register(main.name);
    }

    /**
     * Fired when removing the filter
     *
     * @param object e The click event from the remove button.
     */
    this.remove_action = function(e) {
        e.stopPropagation();
        e.preventDefault();
        main.dropdown.remove();
        main.remove();
        $(this).remove();

        opts.datatable.filter_remove(main.name);
        opts.datatable.updatetable();
    }

    /**
     * Initializer.
     *
     * Performs the following actions:
     *     - Renders the main filter button
     *     - Attaches and renders the filter dropdown.
     *     - Adds removal button.
     *     - Renders intial choices.
     */
    this.initialize = function() {
        // render
        main.addClass(opts.filter_cssclass).html(opts.label+': '+opts.lang_all+' &#x25BC;');

        // add and initialize dropdown
        main.attach_dropdown();

        main.dropdown
            .addClass(opts.filter_dropdown_cssclass)
            .html(
                '<div class="filter_search">\n\
                    <span>'+opts.lang_search+'&nbsp;&nbsp;</span><input type="text"/>\n\
                </div>\n\
                <div class="selections choicelist" style="display:none"></div>\n\
                <div class="options choicelist"></div>'
            )
            .children('.filter_search').children('input[type="text"]').keyup(function(e) {
                main.search($(this).val());
            });

        // add delete button
        main.removebutton = $('<button>X</button>');
        main.removebutton.addClass(opts.css_filterdelete_class).click(main.remove_action);
        main.after(main.removebutton);

        // populate initial choices
        main.set_choices(opts.initialchoices,true);

        main.mousedown(function(e) {
            main.reset_choices();
        });
    }

    main.initialize();
    return main;
}

})(jQuery);