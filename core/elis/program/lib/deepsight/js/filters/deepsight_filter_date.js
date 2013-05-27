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
 *     $('#element').deepsight_filter_date();
 *     Note: All elements this is used on must have an "id" attribute!
 *
 * Required Options:
 *     object datatable              A deepsight datatable object.
 *     string name                   A unique identifier that refers to this filter.
 *
 * Optional Options:
 *     string css_filter_class       A css class to add to the filter
 *     string css_dropdown_class     A css class to add to the dropdown
 *     string css_filterdelete_class A css class to add to the delete button.
 *     string lang_any               Language string for "any"
 *     object lang_days              An object of day language strings, indexed by day number, starting from 0/Sunday.
 *     object lang_months            An object of month language strings, indexed by month number, starting from 0/Jan
 *     string lang_clear             Language string used for the "clear" button.
 *
 * @param object options Options object (See Options section above for description)
 */
$.fn.deepsight_filter_date = function(options) {
    this.default_opts = {
        // required options
        datatable: null,
        name: null,
        css_filter_class: 'deepsight_filter-date',
        css_dropdown_class: 'deepsight_filter_dropdown',
        css_filterdelete_class: 'deepsight_filter-remove',
        lang_any: 'Any',
        lang_days: {
            0: 'S',
            1: 'M',
            2: 'T',
            3: 'W',
            4: 'T',
            5: 'F',
            6: 'S'
        },
        lang_months: {
            0: 'January',
            1: 'February',
            2: 'March',
            3: 'April',
            4: 'May',
            5: 'June',
            6: 'July',
            7: 'August',
            8: 'September',
            9: 'October',
            10: 'November',
            11: 'December'
        },
        lang_clear: 'Clear Selection'
    }

    var opts = $.extend({}, this.default_opts, options);
    var main = this;

    this.name = opts.name;
    this.type = 'date';
    this.calendar = null;

    /**
     * Update the currently selected date.
     *
     * @param object e    The jQuery calendar_date_changed from the calendar.
     * @param object data The incoming data from the event.
     */
    this.updateselection = function(e, data) {
        opts.datatable.filter_remove(main.name);
        opts.datatable.filter_add(main.name, main.calendar.selection);
        opts.datatable.updatetable();
        main.updatelabel();
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
     * Update the label on the filter button based on the currently selected date.
     */
    this.updatelabel = function() {
        if (typeof(main.calendar.selection.month) != 'undefined') {
            label = opts.lang_months[main.calendar.selection.month]+' '+main.calendar.selection.date+', '+main.calendar.selection.year;
        } else {
            label = opts.lang_any;
        }
        label = opts.label+': '+label+' &#x25BC;';
        main.html(label);
    }

    /**
     * Fired when removing the filter.
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
     * Performs the following actions.
     *     - Renders the main filter button
     *     - Attaches and renders the filter dropdown.
     *     - Adds removal button.
     */
    this.initialize = function() {
        // render
        main.addClass(opts.css_filter_class).html(opts.label+': '+opts.lang_any+' &#x25BC;');

        // add and initialize dropdown
        main.attach_dropdown();

        main.calendar = $('<div></div>');
        var date = new Date();
        main.calendar.deepsight_calendar(date.getFullYear(), date.getMonth(), opts.lang_days, opts.lang_months);
        main.calendar.bind('calendar_date_changed', main.updateselection);

        main.dropdown.addClass(opts.css_dropdown_class);
        main.dropdown.append(main.calendar);

        var clearbutton = $('<button>'+opts.lang_clear+'</button>');
        clearbutton.click(main.calendar.clearselection);
        main.dropdown.append(clearbutton);

        // add delete button
        main.removebutton = $('<button>X</button>');
        main.removebutton.addClass(opts.css_filterdelete_class).click(main.remove_action);
        main.after(main.removebutton);
    }

    this.initialize();
    return main;
}

/**
 * DeepSight Calendar
 * Render a clickable date selector.
 *
 * Usage:
 *     $([container]).deepsight_calendar(year, month, lang_days, lang_months);
 *
 * @param int year        The year to display initially.
 * @param int month       The month to display initially.
 * @param object  lang_days   An object of day language strings, indexed by day number, starting from 0/Sunday.
 * @param object  lang_months An object of month language strings, indexed by month number, starting from 0/Jan
 */
$.fn.deepsight_calendar = function(year, month, lang_days, lang_months) {
    var main = this;
    this.selection = {};

    /**
     * Gets the number of days in a given month, taking leap-years into account.
     *
     * @param int year  The year.
     * @param int month The month.
     */
    this.get_days_in_month = function(year, month) {
        var days_in_month = {
            0: 31,
            1: (year%4==0) ? 29 : 28,
            2: 31,
            3: 30,
            4: 31,
            5: 30,
            6: 31,
            7: 31,
            8: 30,
            9: 31,
            10: 30,
            11: 31
        }
        return (typeof(days_in_month[month]) != 'undefined') ? days_in_month[month] : 0;
    }

    /**
     * Clears the current selection
     */
    this.clearselection = function() {
        main.selection = {};
        main.render_calendar();
        main.trigger('calendar_date_changed', main.selection);
    }

    /**
     * Renders the calendar display for a given year and month.
     *
     * @param int year  The year.
     * @param int month The month.
     */
    this.render_calendar = function(year, month) {
        var date = new Date();

        if (typeof(year) == 'undefined') {
            var year = date.getFullYear();
        }
        if (typeof(month) == 'undefined') {
            var month = date.getMonth();
        }

        var days_in_month = main.get_days_in_month(year, month);
        var date = new Date();
        date.setMonth(month);
        date.setYear(year);
        date.setDate(1);
        var day_of_first_of_month = date.getDay();

        var html = '<table data-month="'+month+'" data-year="'+year+'"><tr>';
        html += '<th class="prevmonth"><button>&#9664;</button></th>';

        var monthselect = '<select class="monthinput">';
        var monthselected = '';
        for (var i in lang_months) {
            monthselected = (i == month) ? 'selected="selected"' : '';
            monthselect += '<option value="'+i+'" '+monthselected+'>'+lang_months[i]+'</option>';
        }
        monthselect += '</select>';

        html += '<th colspan="5" class="monthyear">'+monthselect+'&nbsp;<input type="text" class="yearinput" value="'+year+'"/></th>';
        html += '<th class="nextmonth"><button>&#9654;</button></th>';
        html += '</tr><tr>';
        for (var i in lang_days) {
            html += '<th>'+lang_days[i]+'</th>';
        }
        html += '</tr>';

        var date = 1;
        var row = 1;
        while(true) {
            html += '<tr>';
            for (var i = 0; i <= 6; i++) {
                if ((row == 1 && i < day_of_first_of_month) || (date > days_in_month)) {
                    html += '<td></td>';
                } else {
                    var selectedclass = (year == main.selection.year && month == main.selection.month && date == main.selection.date)
                        ? ' class="selected"' : '';
                    html += '<td data-date="'+date+'"'+selectedclass+'>'+date+'</td>';
                    date++;
                }
            }
            row++;
            html += '</tr>';

            if (date > days_in_month) {
                break;
            }
        }
        html += '</table>';

        main.addClass('deepsight_calendar').html(html);

        // date selection action
        main.find('td').click(function(e) {
            if ($(this).html() != '') {
                main.find('td.selected').removeClass('selected');
                $(this).addClass('selected');
                var table = main.find('table');
                main.selection = {
                    month: table.data('month'),
                    year: table.data('year'),
                    date: $(this).data('date')
                }
                main.trigger('calendar_date_changed', main.selection);
            }
        });

        // month selector
        main.find('.monthinput').change(function(e) {
            main.render_calendar(year, $(this).val());
        });

        // year input box
        main.find('.yearinput').keyup(function(e) {
            var yearinput = $(this).val();
            if (yearinput != year && yearinput.length == 4 && isNaN(Number(yearinput)) != true) {
                main.render_calendar(yearinput, month);
                main.find('.yearinput').focus();
            }
        });

        // previous month button
        main.find('th.prevmonth').click(function(e) {
            prevyear = year;
            prevmonth = month;
            if (prevmonth == 0) {
                prevmonth = 11;
                prevyear--;
            } else {
                prevmonth--;
            }
            main.render_calendar(prevyear, prevmonth);
        });

        // next month button
        main.find('th.nextmonth').click(function(e) {
            nextyear = year;
            nextmonth = month;
            if (nextmonth == 11) {
                nextmonth = 0;
                nextyear++;
            } else {
                nextmonth++;
            }
            main.render_calendar(nextyear, nextmonth);
        });
    }

    this.render_calendar(year, month);
    return this;
}

})(jQuery);