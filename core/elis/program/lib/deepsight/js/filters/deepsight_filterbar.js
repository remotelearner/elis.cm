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
 * DeepSight Filter Bar
 * Wrapper function to allow interaction between active filters and a filter generator - when filters are deleted they
 * are added to the generator.
 *
 * Required Options:
 *     object datatable       The datatable object this filterbar is for.
 *     object filters         An object of objects defining all available filters.
 *                                Member object params:
 *                                string type The type of filter - i.e. "searchselect", "textsearch", etc.
 *                                object opts The options object to pass to the filter.
 *                                                Note: datatable will be populated automatically, unless specifically overridden.
 * Optional Options:
 *     array starting_filters An array of keys from the opts.filters object defining which filters should be present
 *                            initially.
 *
 * @param object options  Options object (See Options section above for description)
 */
$.fn.deepsight_filterbar = function(options) {
    this.default_opts = {
        datatable: null,
        filters: {},
        starting_filters: [],
        lang_add: 'Add'
    }
    var opts = $.extend({}, this.default_opts, options);

    var main = this;
    this.generator = null;

    /**
     * Initializer.
     *
     * Performs the following actions:
     *     - Either renders filters or adds them to the generator, depending on whether the keys in starting_filters.
     *     - Adds a filter generator containing all filters not defined to be starting filters.
     */
    this.initialize = function() {
        var available_filters = {};
        for (var i in opts.filters) {
            if ($.inArray(i, opts.starting_filters) >= 0) {

                // this is a starting filter
                main.append('<button id="filter_'+i+'"></button>');

                curfilter = $('#filter_'+i);
                var default_filter_opts = {
                    datatable: opts.datatable
                }
                var filter_opts = $.extend({}, default_filter_opts, opts.filters[i].opts);
                var filterfunc = 'deepsight_filter_'+opts.filters[i].type;
                curfilter = curfilter[filterfunc](filter_opts);

                if (typeof(curfilter.removebutton) != 'undefined') {
                    curfilter.removebutton.click(opts.filters[i], function(e) {
                        main.generator.available_filters[e.data.opts.name] = e.data;
                        main.generator.render_available_filters();
                    });
                }

            } else {
                // this is a filter to add to the "add more" dropdown
                available_filters[opts.filters[i].opts.name] = opts.filters[i];
            }
        }

        main.generator = $('<button id="filter_generator" class="elisicon-more">'+opts.lang_add+'</button>');
        main.generator.deepsight_filter_generator({
            datatable: opts.datatable,
            available_filters: available_filters
        });
        main.append(main.generator);
    }

    main.initialize();
    return main;
}

})(jQuery);