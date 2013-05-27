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
 * DeepSight Filter Generator
 * Generates additional filters on-demand
 *
 * Usage:
 *     $('#element').deepsight_filter_generator();
 *
 * Required Options:
 *     object datatable          The deepsight datatable to link filters to. This will be added to the options of newly
 *                               created filters if they do not override it themselves - but it's not recommended that
 *                               they to do that
 *     array available_filters   An array of objects defining the filters that can be added.
 *                                   Object params:
 *                                       string label The text listed in the dropdown, and what will appear on the filter button
 *                                       string type  The type of filter (i.e. "searchselect")
 *                                       object opts  The option object passed to the filter.
 * Optional Options:
 *     string css_class          A CSS class to add to the button. Not recommended to change this.
 *     string css_dropdown_class A CSS class to add to the dropdown. Not recommended to change this.
 *     string css_choice_class   A CSS class to add to each option in the dropdown.
 *
 * Note: All elements this is run on must have an "id" attribute.
 *
 * @param object options Options for the class.
 */
$.fn.deepsight_filter_generator = function(options) {
    this.default_opts = {
        datatable: null,
        available_filters: {},
        css_class: 'deepsight_filter_generator',
        css_dropdown_class: 'deepsight_filter_generator_dropdown',
        css_choice_class: 'deepsight_filter_generator_choice'
    }

    var opts = $.extend({}, this.default_opts, options);
    var main = this;
    this.available_filters = opts.available_filters;

    /**
     * Add a filter right before this element.
     *
     * @param string label The label of the filter appears on the filter button.
     * @param string type  The type of filter to add - "searchselect", "textsearch", etc.
     * @param object opts  The options object to pass directly to the filter.
     */
    this.add_filter = function(label, type, opts) {
        var filter = $('<button></button').prop('id', 'filter_'+opts.name);
        main.before(filter);
        var filterfunc = 'deepsight_filter_'+type;
        filter = filter[filterfunc](opts);

        // add removebutton action to add filter back to list
        if (typeof(filter.removebutton) != 'undefined') {
            filter.removebutton.click({type: type, opts: opts},function(e) {
                main.available_filters[e.data.opts.name] = e.data;
                main.render_available_filters();
            });
        }

        main.reposition_dropdown();
        delete main.available_filters[opts.name];
        $('#'+main.prop('id')+'_choice_'+opts.name).remove();
        filter.register_with_datatable();
        opts.datatable.updatetable();
    }

    /**
     * Add an option to the list of available filters.
     *
     * @param string filtername  The name of the filter.
     * @param string type        The type of filter - i.e. "searchselect", "textsearch", etc
     * @param object filter_opts The options object to pass to the filter.
     */
    this.add_option = function(filtername, type, filter_opts) {
        var choice_id = main.prop('id')+'_choice_'+filtername;
        var choice = $('<div></div>')
            .prop('id',choice_id)
            .addClass(opts.css_choice_class)
            .html(filter_opts.label);

        if (typeof(filter_opts.datatable) == 'undefined') {
            filter_opts.datatable = opts.datatable;
        }

        choice.click(function(e) {
            main.add_filter(filter_opts.label, type, filter_opts);
            $.deactivate_all_filters();
        });

        main.dropdown.append(choice);
    }

    /**
     * Render the dropdown listing the available filters.
     */
    this.render_available_filters = function() {
        main.dropdown.empty();

        //translate opts.available filters into an associative object and record the names in an array so we
        //can sort the array and display the available filters in alphabetical order
        var filternames = [];
        for (var i in main.available_filters) {
            filternames.push(main.available_filters[i].opts.name);
        }

        filternames.sort();
        for (var i in filternames) {
            main.add_option(
                main.available_filters[filternames[i]].opts.name,
                main.available_filters[filternames[i]].type,
                main.available_filters[filternames[i]].opts
            );
        }
    }

    /**
     * Reposition the dropdown when a filter is added.
     */
    this.reposition_dropdown = function() {
        var offset = main.offset();
        main.dropdown.offset({
            left:offset.left,
            top:offset.top+main.outerHeight()-1
        });
    }

    /**
     * Initializer.
     *
     * Performs the following actions:
     *     - added the opts.css_class class to main.
     *     - attaches a dropdown + adds css class to dropdown
     *     - renders the list of available filters
     */
    this.initialize = function() {
        main.addClass(opts.css_class);
        main.attach_dropdown();
        main.dropdown.addClass(opts.css_dropdown_class);
        main.render_available_filters();
    }

    this.initialize();

    return main;
}

})(jQuery);