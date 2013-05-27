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
 * DeepSight Switch Filter
 * This filter allows for selection of one of multiple choices. No Searching. No Multi-Value.
 *
 * Usage:
 *     $('#element').deepsight_filter_switch();
 *     Note: All elements this is used on must have an "id" attribute!
 *
 * Required Options:
 *     object datatable        A deepsight datatable object.
 *     string name             A unique identifier that refers to this filter.
 *     array  choices          An array of objects defining choices.
 *                                 Object params:
 *                                 mixed  value Whatever value to use as the filter value when this is selected.
 *                                 string label The label of the choice.
 * Optional Options:
 *     string label               The label of the filter - what to display on the filter button.
 *     string css_active_class A CSS class to add when the filter's dropdown is active.
 *     string css_filter_class A CSS class to add to the filter button.
 *
 * @param object options Options object (See Options section above for description)
 */
$.fn.deepsight_filter_switch = function(options) {
    this.default_opts = {
        datatable: null,
        name: null,
        choices: [],
        label: 'Selected: ',
        css_active_class: 'active',
        css_filter_class: 'deepsight_filter-switch',
    }

    var opts = $.extend({}, this.default_opts, options);
    var main = this;

    this.name = opts.name;
    this.type = 'switch';

    /**
     * Choose a particular value for the filter.
     *
     * @param string label The label of the choice - updates the main filter button to indicate this choice.
     * @param mixed  val   The filter value sent to the datatable when this choice is selected.
     */
    this.choose = function(label, val) {
        main.html(opts.label+' '+label);

        $.deactivate_all_filters();

        opts.datatable.filter_remove(main.name);

        if (val != '') {
            opts.datatable.filter_add(main.name, val);
        }

        opts.datatable.updatetable();
    }

    /**
     * Notify the datatable that this filter has been added. This is used when added columns to the datatable when we add
     * new filters dynamically.
     */
    this.register_with_datatable = function() {
        opts.datatable.filter_register(main.name);
    }

    /**
     * Renders the available choices in the dropdown.
     *
     * @param array choices An array of objects defining the choices to render
     *                          Object params:
     *                          mixed  value Whatever value to use as the filter value when this is selected
     *                          string label The label of the choice.
     * @return object The rendered DOM node.
     */
    this.render_choices = function(choices) {
        var choicelist = $('<ul></ul>');

        var choice_html = '';
        for (var i in choices) {
            choice_html += '<li data-value="'+choices[i].value+'">'+choices[i].label+'</li>';
        }
        choicelist.html(choice_html);
        choicelist.children('li').each(function() {
            $(this).click(function(e) {
                main.choose($(this).html(), $(this).data('value'));
            });
        });
        return choicelist;
    }

    /**
     * Initializer.
     *
     * Performs the following actions:
     *     - Adds CSS class
     *     - Attaches dropdown
     *     - Renders filter button
     *     - Resets filter information in the datatable (removes + re-adds)
     *     - Renders available choices
     */
    this.initialize = function() {
        main.addClass(opts.css_filter_class).addClass('emphasis');
        main.attach_dropdown();
        main.dropdown.addClass('emphasis');
        main.html(opts.label+' '+opts.choices[0].label);
        main.dropdown.append(main.render_choices(opts.choices));
    }

    this.initialize();
    return main;
}

})(jQuery);