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
 * DeepSight TextSearch filter
 * This filter will allows filtering on a string.
 *
 * Usage:
 *     $('#element').deepsight_filter_searchselect();
 *     Note: All elements this is used on must have an "id" attribute!
 *
 * Required Options:
 *     datatable               object  A deepsight datatable object.
 *     name                    string  A unique identifier that refers to this filter.
 *
 * Optional Options:
 *     label                   string  The label of the filter - appears in the filter button.
 *     css_active_class        string  A CSS class to add to the filter button when the filter is clicked.
 *     css_filter_class        string  Custom CSS Class to add to the filter.
 *     css_filterdelete_class  string  A CSS class to add to the filter's remove button.
 *     lang_any                string  The "Any" string displayed when the filter is inactive and has no value.
 *
 * @param object options Options object (See Options section above for description)
 */
$.fn.deepsight_filter_textsearch = function(options) {
    this.default_opts = {
        // required options
        datatable: null,
        name: null,
        // optional options
        label: 'Filter',
        css_active_class: 'active',
        css_filter_class: 'deepsight_filter-textsearch',
        css_filterdelete_class: 'deepsight_filter-remove',
        lang_any: 'Any'
    }

    var opts = $.extend({}, this.default_opts, options);
    var main = this;

    this.name = opts.name;
    this.type = 'textsearch';
    this.curval = '';
    this.removebutton = null;

    /**
     * Changes the value currently being filters on.
     *
     * @param string val The value to filter on.
     */
    this.addval = function(val) {
        if (val != main.curval) {
            opts.datatable.filter_remove(main.name);
            opts.datatable.filter_add(main.name, val);
            opts.datatable.updatetable();
            main.curval = val;
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
        main.remove();
        $(this).remove();
        opts.datatable.filter_remove(main.name);
        opts.datatable.updatetable();
    }

    /**
     * Updates the display of the filter button with the currently entered text.
     */
    this.update_display = function() {
        $('.'+opts.css_filter_class+'.'+opts.css_active_class).removeClass(opts.css_active_class);
        $(document).unbind('click', main.update_display);
    }

    /**
     * Initialize filter.
     *
     * Performs the following actions:
     *     - Adds CSS class
     *     - Renders filter button
     *     - Sets internal element vars
     *     - Adds action to maintain filter state when clicked.
     *     - Adds action to run addval() on keyup
     *     - Renders remove button
     */
    this.initialize = function() {
        // render
        main.addClass(opts.css_filter_class);
        main.html('<span class="label">'+opts.label+': </span><span class="selection">'+opts.lang_any+'</span><input type="text"/>');

        var ele_input = main.children('input');

        // prevent deactivating when clicking the input box
        ele_input.click(function(e) {
            e.stopPropagation();
        });

        ele_input.keyup(function(e) {
            main.addval(ele_input.val());
            main.children('span.selection').html((main.curval != '') ? '"'+main.curval+'"' : opts.lang_any);
        });

        // add remove button
        main.removebutton = $('<button>X</button>').addClass(opts.css_filterdelete_class).click(main.remove_action);
        main.after(main.removebutton);

        // toggle active/inactive on click
        main.click(function(e) {
            e.stopPropagation();
            $.deactivate_all_filters();
            main.toggleClass(opts.css_active_class);
            main.find('input').focus();
            $(document).bind('click', main.update_display);
        });
    }

    this.initialize();
    return main;
}

})(jQuery);