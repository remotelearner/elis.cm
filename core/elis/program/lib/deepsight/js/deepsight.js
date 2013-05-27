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

/**
 * General logging/debugging function - all log/debug messages pass through here.
 *
 * @param mixed a Something to log
 * @param mixed b Something else to log
 */
function ds_debug(a, b) {
    return;
    console.log(a, (typeof(b) != 'undefined') ? b : '');
}

/**
 * Parse an XSSI-safe JSON string. Many JSON responses have "throw 1;" prefixed to prevent XSSI attacks.
 *
 * @param string str An XSSI-safe JSON string.
 * @return mixed The parse JSON (object/array)
 */
function ds_parse_safe_json(str) {
    return JSON.parse(str.slice("throw 1;".length));
}

/**
 * Adds a query string to a URL - will use ? or & to add the query as necessary
 *
 * @param string input The input URL
 * @param string query The query string to add (without ? or & prefix)
 * @return The entire url with the query intelligently appended.
 */
function ds_add_query_to_uristr(input, query) {
    return (input.search("[?]") == '-1') ? input+'?'+query : input+'&'+query;
}

/**
 * Render a date selector
 *
 * This renders three select boxes, one for date, month, and year.
 *
 * @param string nameprefix      A prefix for the name attribute for each of the select boxes.
 * @param string cssclass        A CSS class to add to each of the select boxes.
 * @param object preselectedinfo An object containing preselected information.
 *                                   Should contain:
 *                                   int date  A preselected date.
 *                                   int month A preselected month.
 *                                   int year  A preselected year.
 * @param object lang_months     An object containing language strings for the names of the month.
 *                               Indexed by month number, starting from 0/Jan
 * @return string The HTML for the date selector.
 */
function deepsight_render_date_selectors(nameprefix, cssclass, preselectedinfo, lang_months) {
    var date = new Date();
    var curyear = date.getFullYear();
    var curmonth = date.getMonth();
    var curdate = date.getDate();

    if (typeof(preselectedinfo) == 'undefined') {
        preselectedinfo = {};
    }
    var selecteddate = (typeof(preselectedinfo.date) != 'undefined') ? preselectedinfo.date : curdate;
    var selectedmonth = (typeof(preselectedinfo.month) != 'undefined') ? (preselectedinfo.month) : curmonth;
    var selectedyear = (typeof(preselectedinfo.year) != 'undefined') ? preselectedinfo.year : curyear;

    var html = '';
    var selected = '';

    // date select
    html += '<select name="'+nameprefix+'day" class="'+cssclass+' '+nameprefix+'day">';
    for (var i = 1; i <= 31; i++) {
        selected = (i == selecteddate) ? 'selected="selected"' : '';
        html += '<option value="'+i+'" '+selected+'>'+i+'</option>';
    }
    html += '</select>';

    // month select
    html += '<select name="'+nameprefix+'month" class="'+cssclass+' '+nameprefix+'month">';
    for (var i in lang_months) {
        selected = (i == selectedmonth) ? 'selected="selected"' : '';
        html += '<option value="'+i+'" '+selected+'>'+lang_months[i]+'</option>';
    }
    html += '</select>';

    // year select
    html += '<select name="'+nameprefix+'year" class="'+cssclass+' '+nameprefix+'year">';
    for (var i = 1970; i < (curyear + 10); i++) {
        selected = (i == selectedyear) ? 'selected="selected"' : '';
        html += '<option value="'+i+'" '+selected+'>'+i+'</option>';
    }
    html += '</select>';
    return html;
}

(function($) {

/**
 * General pagination interface featuring unlimited pages, intelligent page display, forward/back buttons, and text labels.
 * This will update it's own display when the page changes, but you will want to bind to the "pagechange" event fired by this object
 * to, for example, update your element when the page changes.
 *
 * Usage:
 *     $([container]).deepsight_pagination(1, 100, 10, lang);
 *     [container] would be an empty element - the contents will be overwritten.
 *
 * @param int    page             The current page.
 * @param int    total_results    The total amount of elements in your dataset.
 * @param int    results_per_page The number of elements per page.
 * @param object lang             An object of language strings to use. Contents/Examples in English follow:
 *                                    lang_result: 'Result',
 *                                    lang_results: 'Results'
 *                                    lang_showing: 'Showing'
 *                                    lang_page: 'Page'
 */
$.fn.deepsight_pagination = function(page, totalresults, results_per_page, lang) {
    var defaultlang = {
        lang_result: 'Result',
        lang_results: 'Results',
        lang_showing: 'Showing',
        lang_page: 'Page'
    };

    var lang = $.extend({}, defaultlang, lang);
    var main = this;

    /**
     * @var int The total number of elements in the dataset.
     */
    this.numresults = totalresults;

    /**
     * @var int The current page
     */
    this.page = page;

    /**
     * @var int The number of elements per page (used with this.numresults to calculate total pages)
     */
    this.resultsperpage = results_per_page;

    main.addClass('ds_pagelinks');

    /**
     * Render the page links according to the current internal state (this.page, this.numresults, this.resultsperpage)
     */
    this.render = function() {
        if (main.numresults > 0) {
            var startingresultnum = (((main.page - 1) * main.resultsperpage) + 1);
            var endingresultnum = (main.page * main.resultsperpage);
            if (endingresultnum > main.numresults) {
                endingresultnum = main.numresults;
            }

            var totalresults = main.numresults+' '+((main.numresults == 1) ? lang.lang_result : lang.lang_results);
            var pagelinkshtml = '<span>'+lang.lang_showing+' '+startingresultnum+'-'+endingresultnum+' of '+totalresults+'</span>';
            pagelinkshtml += '<span>'+lang.lang_page+':&nbsp;</span>';

            // add previous page link, as long as we're not at the first page.
            var numpages = Math.ceil(main.numresults/main.resultsperpage);
            if (main.page != 1) {
                pagelinkshtml += '<a class="pagearrow" data-page="'+(main.page - 1)+'" href="javascript:;">&#9664;</a>';
            } else {
                pagelinkshtml += '<span class="pagearrow">&nbsp;</span>';
            }

            // draw links
            if (numpages <= 10) {
                // we have less than 10 pages - show them all.
                for (var i = 1; i <= numpages; i++) {
                    pagelinkshtml += (i == main.page)
                        ? '<strong>'+i+'</strong>'
                        : '<a href="javascript:;" data-page="'+i+'">'+i+'</a>';
                }
            } else {
                if (main.page > 4 && main.page < (numpages - 3)) {
                    // we're somewhere in the middle of the pages, show the first three, the last three, and the middle 5,
                    // separated with ellipses when appropriate.

                    // beginning part
                    for (var i = 1; i <= 3; i++) {
                        pagelinkshtml += (i == main.page)
                            ? '<strong>'+i+'</strong>'
                            : '<a href="javascript:;" data-page="'+i+'">'+i+'</a>';
                    }

                    //middle part
                    var start = (main.page - 2);
                    var end = (main.page + 2);

                    // modify the start and end pages if we are close to the edges to prevent duplication
                    if (start <= 3) {
                        start = 4;
                    }
                    if (end >= numpages - 2) {
                        end = (numpages - 3);
                    }

                    if (start >= 5) {
                        pagelinkshtml+='...';
                    }
                    for (var i = start; i <= end; i++) {
                        pagelinkshtml += (i == main.page)
                            ? '<strong>'+i+'</strong>'
                            : '<a href="javascript:;" data-page="'+i+'">'+i+'</a>';
                    }
                    if (end <= (numpages-4)) {
                        pagelinkshtml += '...';
                    }
                    // end part
                    for (var i = (numpages - 2); i <= numpages; i++) {
                        pagelinkshtml += (i == main.page)
                            ? '<strong>'+i+'</strong>'
                            : '<a href="javascript:;" data-page="'+i+'">'+i+'</a>';
                    }
                } else {
                    // we are at the start or end of the pages - show the first or last 7, and the last or first 3, respectively
                    for (var i = 1; i <= 5; i++) {
                        pagelinkshtml += (i == main.page)
                            ? '<strong>'+i+'</strong>'
                            : '<a href="javascript:;" data-page="'+i+'">'+i+'</a>';
                    }
                    pagelinkshtml += ' ... ';
                    for (var i = (numpages - 4); i <= numpages; i++) {
                        pagelinkshtml += (i == main.page)
                            ? '<strong>'+i+'</strong>'
                            : '<a href="javascript:;" data-page="'+i+'">'+i+'</a>';
                    }
                }
            }

            // show the "next page" link, as long as we're not at the end.
            if (main.page != numpages) {
                pagelinkshtml += '<a class="pagearrow" data-page="'+(main.page + 1)+'" href="javascript:;">&#9654;</a>';
            } else {
                pagelinkshtml += '<span class="pagearrow">&nbsp;</span>';
            }

            // render
            main.html(pagelinkshtml);

            // add click action to change page and fire "pagechange" event.
            main.find('a').click(function(e) {
                e.preventDefault();
                main.page = $(this).data('page');
                main.trigger('pagechange', {page:main.page});
                main.render();
            });
        }
    }
    this.render();
    return this;
}

/**
 * A special panel allowing for dropping items from a datatable, and a bulk enrol action that applies to all dropped users.
 *
 * @param object options An object of options for the panel. Options are as follows:
 *     deepsight_datatable datatable              A deepsight_datatable object to be used with this panel.
 *     array               actions                An array of objects representing actions that can be performed with the elements
 *                                                added to the panel.
 *                                                    Object params:
 *                                                        string icon  A CSS class to use as the icon for this row.
 *                                                        string label A text label for this action. This is displayed in the
 *                                                                     tooltip for the action.
 *                                                        string type  The type of action. This corresponds to the end of the
 *                                                                     js file/jquery plugin for the action. For example, to
 *                                                                     use "deepsight_action_enrol", this would be "enrol".
 *                                                        object opts  Options object to be passed to the action.
 *                                                                     parent and datatable will be populated automatically,
 *                                                                     but will be overridden if included here.
 *     string              lang_title             The title of the panel to display.
 *     string              lang_selected_element  The language string to use when displaying a single element.
 *     string              lang_selected_elements The language string to use when displaying multiple selected elements.
 *     string              lang_default_status    The language string displayed when no elements are selected.
 *     int                 animspeed              Miliseconds to perform animations. Lower means faster animations, higher means
 *                                                slower.
 */
$.fn.deepsight_bulkactionpanel = function(options) {
    // options
    this.default_opts = {
        datatable: null,
        actions: [],
        lang_title: 'Bulk Actions',
        lang_selected_element: 'Selected Element',
        lang_selected_elements: 'Selected Elements',
        lang_default_status: 'To perform bulk actions, drag elements here.',
        lang_add_all: 'Add All',
        lang_add: 'Add',
        lang_search_results: 'Search Results',
        lang_clear: 'Clear',
        lang_unloadconfirm: 'Are you sure?',
        lang_result: 'Result',
        lang_results: 'Results',
        lang_showing: 'Showing',
        lang_page: 'Page',
        itemsperpage: 20,
        animspeed: 175
    }

    var opts = $.extend({}, this.default_opts, options);

    // used to reference main object from inside closures
    var main = this;

    /**
     * @var object The deepsight_panel object that gets added to main to provide attach/detach functionality.
     */
    this.panel = null;

    // elements
    var elestatustext = null;
    var eleactions = null;
    var elecontents = null;
    var eleaddallsearch = null;
    var eleaddallselected = null;
    var eleclearall = null;
    var eleactionbuttons = [];
    this.pagelinks = null;

    /**
     * @var object The current page of selected elements.
     */
    this.selected_elements_page = {};

    /**
     * @var int The total number of currently selected elements.
     */
    this.selected_elements_total = 0;

    /**
     * Executed when clicking the "add all" button.
     *
     * Tells the datatable to get the ids of all search results across all pages
     * and binds a handler to the "bulklist_add_by_filters_complete" datatable event, which is fired by the datatable when results
     * are returned. We have to use the event subscription model since the get all results call is asynchronous.
     */
    this.addall_search = function() {
        // ui effects
        eleaddallsearch.prop('disabled', true);
        main.addClass('loading');

        // make datatable request
        opts.datatable.bind('bulklist_add_by_filters_complete', main.addall_search_callback);
        opts.datatable.bulklist_add_by_filters();
    }

    /**
     * Bound to the "bulklist_add_by_filters_complete" datatable event when the "add all" button is clicked.
     *
     * Receives all results, adds them to the bulk panel.
     * in the table.
     *
     * @param object e    jQuery event object
     * @param object data Custom data - data.data holds the search results.
     */
    this.addall_search_callback = function(e, data) {
        e.stopPropagation();
        opts.datatable.unbind('bulklist_add_by_filters_complete', main.addall_search_callback);
        main.update_display(e, data);
        opts.datatable.doupdatetable();
    }

    /**
     * Adds all selected rows to the panel
     *
     * Fired whenever the user clicks the "add all selected" button, finds and adds multiselect "selected" rows.
     *
     * @param object e The click event object that initiated the function.
     */
    this.addall_selected = function(e) {
        //e.stopPropagation();
        var updatedisplaydata = {
            total_results: 0,
            page_results_ids: [],
            page_results_values: []
        };

        opts.datatable.find('tr.ds_multiselect.selected').each(function() {
            var row = $(this);
            opts.datatable.removefromtable('bulklist_add', row.data('id'));
            updatedisplaydata.page_results_ids.push(row.data('id'));
            updatedisplaydata.page_results_values.push(row.data('label'));
            updatedisplaydata.total_results++;
        });
        main.update_display(e, updatedisplaydata);
        //main.update_addselected_display({}, {total:0});
    }

    /**
     * Starts a page change.
     *
     * Bound to the "pagechange" deepsight_pagination event, this sends a request to the datatable to fetch a page of the bulk list
     * and send the result to the "pagechange_callback" function.
     *
     * @param object e    jQuery event object from the pagechange event.
     * @param object data The data from the pagechange event. Will contain the requested page number.
     */
    this.pagechange = function(e, data) {
        var page = data.page;
        opts.datatable.bind('bulklist_got', main.pagechange_callback);
        opts.datatable.bulklist_get(data.page);
    }

    /**
     * Changes the page.
     *
     * Bound to the bulklist_got event, this fires when the datatable has finished fetching the requested elements for the currently
     * displayed page.
     *
     * @param object e    jQuery event object from the bulklist_got event
     * @param object data The data from the bulklist_got event.
     */
    this.pagechange_callback = function(e, data) {
        opts.datatable.unbind('bulklist_got', main.pagechange_callback);
        main.selected_elements_page_ids = data.page_results_ids;
        main.selected_elements_page_values = data.page_results_values;
        main.render_contents();
    }

    /**
     * Renders contents.
     *
     * Renders the current page based on elements in main.selected_elements_page_ids and main.selected_elements_page_values.
     * Runs each of those through main.render_item.
     */
    this.render_contents = function() {
        var elecontentslist = elecontents.find('ul');
        elecontentslist.empty();
        for (var i in main.selected_elements_page_ids) {
            elecontentslist.append(
                main.render_item(main.selected_elements_page_ids[i], main.selected_elements_page_values[i])
            );
        }
    }

    /**
     * Renders a single id/label pair complete with removal action.
     *
     * @param int    id    The ID of the item to render
     * @param string label The label of the item to render
     * @return A jQuery object containing the item's label, the ID in the item's data-id property, and a functional remove button.
     */
    this.render_item = function(id, label) {
        var item = $('<li data-id="'+id+'"><i class="remove elisicon-cancel"></i>'+label+'</li>');
        item.find('i.remove').click(main.remove_item);
        return item;
    }

    /**
     * Updates the display of the panel.
     *
     * Updates the display of the panel to match the current elements stored in main.selected_elements. Writes the list of
     * elements, as well as updates the status display (number of elements selected, show/hide action buttons)
     *
     * @param object e The jQuery event object
     * @param object data Information to display. Contain total_results, page_results_ids, and page_results_values.
     */
    this.update_display = function(e, data) {
        eleclearall.prop('disabled', false);
        main.removeClass('loading');

        // assign received data
        main.selected_elements_total = data.total_results;
        main.selected_elements_page_ids = data.page_results_ids;
        main.selected_elements_page_values = data.page_results_values;

        // render the list of users
        main.render_contents();

        // render pagination
        if (main.pagelinks != null) {
            main.pagelinks.remove();
            main.pagelinks = null;
        }
        if (main.selected_elements_total > 0) {
            main.pagelinks = $('<div></div>');
            var paginationlang = {
                lang_result: opts.lang_result,
                lang_results: opts.lang_results,
                lang_showing: opts.lang_showing,
                lang_page: opts.lang_page
            }
            main.pagelinks.deepsight_pagination(1, main.selected_elements_total, opts.itemsperpage, paginationlang);
            main.pagelinks.bind('pagechange', main.pagechange);
            elecontents.prepend(main.pagelinks);
        }

        // send our selected elements to action objects
        for (var i in opts.actions) {
            eleactionbuttons[opts.actions[i].type].update_parentid('bulklist');
        }

        // update status text - show/hide action buttons
        elestatustext.unbind('click', main.toggle_contents);
        if (main.selected_elements_total > 0) {
            var selectedtext = (main.selected_elements_total == 1) ? opts.lang_selected_element : opts.lang_selected_elements;
            elestatustext.html('for '+main.selected_elements_total+' '+selectedtext).bind('click', main.toggle_contents);
            eleactions.show();
        } else {
            eleactions.hide();
            elestatustext.html(opts.lang_default_status);
        }
    }

    /**
     * Toggles display of the added element list.
     *
     * Fired when the user clicks the "for # selected elements" link.
     *
     * @param object e The jQuery event object for the click event.
     */
    this.toggle_contents = function(e) {
        elecontents.slideToggle(opts.animspeed);
    }

    /**
     * The action fired when a user clicks the "X" to remove an element from the panel.
     *
     * @param object e The jQuery event object for the click event.
     */
    this.remove_item = function(e) {
        var item = $(this).parent('li');
        item.remove();
        opts.datatable.bulklist_remove(item.data('id'));
        item = null;
    }

    /**
     * Resets the panel.
     *
     * Removes all selected elements from both this and the datatable. This is fired when a bulk action completes.
     *
     * @param object e The jQuery "action_complete" custom event.
     */
    this.reset = function(e) {
        opts.datatable.bulklist_remove('*', 0);
        for (var i in opts.actions) {
            eleactionbuttons[opts.actions[i].type].hide_action();
        }
    }

    /**
     * Handles the completion of an action
     *
     * @param object e The inititiating jquery event object
     */
    this.complete_action = function(e) {
        opts.datatable.doupdatetable();
        main.reset(e);
    }

    /**
     * Disables interaction with the panel while an action is being performed
     *
     * @param object e The initiating jquery event object
     */
    this.disable = function(e) {
        eleaddallsearch.prop('disabled', true);
        eleclearall.prop('disabled', true);
    }

    /**
     * Added dragged items to the panel.
     *
     * Adds information to main.selected elements, increments num_selected_elements, and adds items to the datatable's bulklist.
     * Also tells the datatable to update itself.
     *
     * @param object e  The drop event.
     * @param object ui The jQuery object for the drag helper.
     */
    this.dropaction = function(e, ui) {
        $('body').css('cursor', 'auto');
        main.addClass('dropped', 100).delay(100).removeClass('dropped', 100);

        var elementstoadd = opts.datatable.find('tr.selected');
        elementstoadd.addClass('dropped');
        var elecontentslist = elecontents.find('ul');
        $(elementstoadd.get().reverse()).each(function() {
            elecontentslist.prepend(main.render_item($(this).data('id'), $(this).data('label')));
            opts.datatable.removefromtable('bulklist_add', $(this).data('id'));
        });
    }

    /**
     * Updates the "add all" button to reflect a new number of search results.
     *
     * @param int numresults The new number of results to render on the button.
     */
    this.update_addall_display = function(numresults) {
        if (numresults <= 0) {
            eleaddallsearch.prop('disabled', true);
        } else {
            eleaddallsearch.prop('disabled', false);
        }
        eleaddallsearch.html(opts.lang_add_all+' '+numresults+' '+opts.lang_search_results);
    }

    /**
     * Updates the display of the add all selected button
     *
     * Sets the display text to match the number of elements currently selected, or hides the button if there are none.
     * Initiated by the "selection_changed" event.
     *
     * @param object e    The event object
     * @param object data The event's data. Will contan "total" - the amount of elements currently selected.
     */
    this.update_addselected_display = function(e, data) {
        var total = parseInt(data.total);

        if (total <= 0) {
            eleaddallselected.hide();
        } else if (total == 1) {
            eleaddallselected.show().html(opts.lang_add+' '+total+' '+opts.lang_selected_element);
        } else {
            eleaddallselected.show().html(opts.lang_add_all+' '+total+' '+opts.lang_selected_elements);
        };
    }

    /**
     * Initializes the panel.
     *
     * Performs the following actions:
     *     - render panel
     *     - assign internal elements
     *     - add actions
     *     - add datatable event listeners
     *     - add droppability + visual feedback event listeners
     */
    this.initialize = function() {
        $(window).on('beforeunload', function() {
            if (main.selected_elements_total > 0) {
                return opts.lang_unloadconfirm;
            }
        });

        $(window).unload(function() {
            if (main.selected_elements_total > 0) {
                ds_debug('[deepsight_bulkactionpanel] Cleaning bulk list...');
                opts.datatable.bulklist_clean();
            }
        });

        // render panel
        var panelhtml = '<h3 class="title">'+opts.lang_title+'</h3>\
            <span class="statustext">'+opts.lang_default_status+'</span>\
            <div class="actions"></div>\
            <button class="clearall" style=\'margin:0;margin-left:10px;\'>'+opts.lang_clear+'</button>\
            <button class="addallselected" style=\'margin:0;margin-left:10px;\'></button>\
            <button class="addallsearch" style=\'margin:0;margin-left:10px;\'></button>\
            <div class="contents"><ul></ul></div>';
        main.html(panelhtml);

        // assign elements
        main.panel = main.deepsight_panel({detach_when_invisible: 'top', detach_padding: 42});
        elestatustext = main.find('.statustext');
        elecontents = main.find('.contents');
        eleaddallsearch = main.find('.addallsearch');
        eleaddallselected = main.find('.addallselected');
        eleclearall = main.find('.clearall');
        eleactions = main.find('.actions');

        // element actions
        eleaddallsearch.click(main.addall_search);
        eleaddallselected.click(main.addall_selected).hide();
        eleclearall.click(function(e) { $(this).prop('disabled', true); main.reset(e); });

        // add actions
        for (var i in opts.actions) {
            var actioniconhtml = '<i class="deepsight_action_'+opts.actions[i].type+'"></i>';
            eleactionbuttons[opts.actions[i].type] = $(actioniconhtml);

            // assemble options to pass to action object - combination of these defaults and opts.actions[i].opts
            var defaultopts = {
                parent: eleaddallsearch,
                parentid: 'bulklist',
                datatable: opts.datatable,
                label: opts.actions[i].label,
                icon: opts.actions[i].icon,
                type: opts.actions[i].type
            };
            if (typeof(opts.actions[i].opts) == 'undefined') {
                opts.actions[i].opts = {};
            }
            var actionopts = $.extend({}, defaultopts, opts.actions[i].opts);

            // call the action function for the action button
            var actionbutton = eleactionbuttons[opts.actions[i].type];
            var func = 'deepsight_action_'+opts.actions[i].type;
            actionbutton[func](actionopts);

            // when the action is completed, "action_complete" event will be fired by action button. bind our reset func.
            actionbutton.bind('action_complete', main.reset);
            actionbutton.bind('action_started', main.disable);

            // add the action button!
            eleactions.append(actionbutton);
        }

        // datatable event listeners
        opts.datatable
            // when the datatable is updated, we need to update the "add all" button with the new number of results,
            // as well as the panel location for accurate attach/detach
            .bind('datatable_updated', function(e) {
                e.stopPropagation();
                ds_debug('[bulkactionpanel] Received "datatable_updated" event. New results:'+opts.datatable.numresults);
                main.update_addall_display(opts.datatable.numresults);
                main.panel.refresh_panel_dims();
            })

            // highlight or expand the panel when dragging
            .bind('dragstart', function(e) {
                e.stopPropagation();
                if (main.panel.state == 'detached') {
                    main.addClass('dragactive', opts.animspeed);
                } else {
                    main.addClass('dragactive');
                }
            })

            .bind('bulklist_modified',  main.update_display)

            // stop highlighting or reduce panel when drag stops
            .bind('dragstop', function(e) {
                try {
                    e.stopPropagation();
                } catch(e) {

                }
                if (main.panel.state == 'detached') {
                    main.removeClass('dragactive', opts.animspeed, main.panel.refresh_panel_dims);
                } else {
                    main.removeClass('dragactive');
                    main.panel.refresh_panel_dims();
                }
            })
            .bind('selection_changed', main.update_addselected_display);

        // make main droppable + add mouse enter/leave events for visual feedback
        main
            .droppable({
                tolerance: 'pointer',
                drop: main.dropaction
            })
            .mouseenter(function(e) {
                main.addClass('drophover');
            })
            .mouseleave(function(e) {
                main.removeClass('drophover');
            });
    }

    this.initialize();

    return main;
}

/**
 * A panel that detaches and sticks to the screen based on a number of options
 *
 * Usage:
 *     $('element').deepsight_panel();
 *
 * @param object options An object of options for the panel. Available options are as follows:
 *     string detach_when_invisible Simple rules for detaching the panel.
 *                                      "bottom": If the element's "bottom" dimension is below the viewport, detach the element.
 *                                      "top": If the element's "top" dimension is below the viewport, detach the element.
 *     int    detach_padding        Add or remove (using positive/negative numbers) pixels to the location for detachment.
 *                                      i.e. If I want the element to detach 35 pixels from the top, I'd set this to 35 and
 *                                      "detach_when_invisible" to "top"
 *
 */
$.fn.deepsight_panel = function(options) {
    this.default_opts = {
        detach_when_invisible: 'bottom',
        detach_padding: 0,
        css_class: 'deepsight_panel'
    }

    var opts = $.extend({}, this.default_opts, options);

    this.state = 'attached';

    var main = this;
    var ele_top = null;
    var ele_left = null;
    var ele_bottom = null;

    /**
     * Refeshes panel dimensions/location information
     */
    this.refresh_panel_dims = function() {
        main.attach();
        var offset = main.offset();
        ele_top = offset.top;
        ele_left = offset.left;
        ele_bottom = ele_top + main.outerHeight(true) - 7;
        ele_right = ($(window).width() - (offset.left + main.outerWidth()));
        main.refresh_viewport_dims();
    }

    /**
     * Detaches the panel
     */
    this.detach = function () {
        this.state = 'detached';
        main.removeClass('attached').addClass('detached');
        main.css({'left':ele_left, 'right':ele_right});
        main.find('.contents').hide();
    }

    /**
     * Attaches the panel
     */
    this.attach = function () {
        this.state = 'attached';
        main.removeClass('detached').addClass('attached');
        main.css({'left':'auto', 'right':'auto'});
        main.find('.contents').show();
    }

    /**
     * Gets the current dimensions of the viewport, and detaches/attaches the panel as necessary.
     */
    this.refresh_viewport_dims = function() {
        var jqwindow = $(window);
        var viewporttop = jqwindow.scrollTop();
        var viewportbottom = viewporttop+jqwindow.height();

        var targetrow = 0;
        if (opts.detach_when_invisible == 'bottom') {
            targetrow = ele_bottom;
        } else if (opts.detach_when_invisible == 'top') {
            targetrow = ele_top;
        }
        targetrow += opts.detach_padding;

        if (targetrow > viewportbottom) {
            main.detach();
        } else {
            main.attach();
        }
    }

    /**
     * Initializer: adds classes, activates information refresh on window change, and initializes dimension/location information.
     */
    this.initialize = function() {
        main.addClass(opts.css_class);

        $(window)
            .scroll(main.refresh_viewport_dims)
            .resize(main.refresh_viewport_dims);

        main.refresh_panel_dims();
        main.refresh_viewport_dims();
    }

    this.initialize();
    return main;
}

/**
 * DeepSight Datatable
 * This object links multiple filters to a table displaying the filterable data
 *
 * Usage:
 *     var datatable = $('#[elementid]').datatable();
 *     The variable datatable can then be used in the options for filters you'd like to connect to the table.
 *     Note: This can only be run on a single element at a time, and the element should have an ID set.
 *
 * @param object options Options object (See Options section above for description)
 *     string   dataurl           The script URL to send requests for data.
 *     string   sesskey           The Moodle sesskey (sent with requests to prevent CSRF attacks)
 *     string   uniqid            A unique ID used to identify different, concurrent datatable sessions. I.e. using the same
 *                                datatable in different browser windows. This keeps the requests separate.
 *     bool     dragdrop          Whether to allow drag+drop of rows (droppable can be configured elsewhere)
 *     bool     multiselect       Whether to enable selection/multiselect of rows.
 *     int      resultsperpage    The number of results per page. This is ONLY used to calculate page links, your dataurl should
 *                                use the same number.
 *     function rowfilter         (Optional) If defined, is run on every row when rendering, and can be used to transform the row
 *                                as desired.
 *                                    Params:
 *                                        object row     The jquery object for the current row.
 *                                        object rowdata The data returned from dataurl for the current row.
 *     array    initialfilters    An array of filter names which are present at start up.
 *     string   lang_no_results   Language string displayed when no results are returned.
 *     string   lang_errormessage Language string displayed when an error is encountered.
 *     string   lang_error        Language string for the title of the error's details section.
 *     string   lang_actions      Language string for the title of the actions column
 *     string   lang_page         Language string for "Page" - used for pagination.
 *     string   lang_result       Language string for "result" - used for pagination.
 *     string   lang_results      Language string for "results" - used for pagination.
 *     string   lang_showing      Language string for "showing" - used for pagination.
 *     string   lang_loading      Language string shown during initial loading.
 *     array    actions           (Optional) An array of objects defining available actions for each row.
 *                                    Object params:
 *                                        string   icon      A CSS class to use as the icon for this row.
 *                                        string   label     A text label for this action. This is displayed in the tooltip for the
 *                                                           action.
 *                                        string   type      The type of action. This corresponds to the end of the js file/jquery
 *                                                           plugin for the action. For example, to use "deepsight_action_enrol",
 *                                                           this would be "enrol".
 *                                        object   opts      Options object to be passed to the action.
 *                                                           parent, parentid, datatable, and wrapper will be populated
 *                                                           automatically, but will be overridden if included here.
 *                                        function condition (Optional) A function to determine if this action applies to a given
 *                                                           row.
 *                                                               Params:
 *                                                                   object data The data for the current row as returned from
 *                                                                               dataurl.
 */
$.fn.deepsight_datatable = function(options) {
    this.default_opts = {
        dataurl: null,
        sesskey: null,
        uniqid: null,
        dragdrop: false,
        multiselect: false,
        resultsperpage: 20,
        rowfilter: null,
        initial_filters: [],
        lang_no_results: '<strong>We couldn\'t find any more results for the selected filters.</strong><span>Note that users '
                         +'dragged to bulk enrolments will not be included here until enrolled or removed from bulk enrolments.'
                         +'</span>',
        lang_errormessage: '<strong>Sorry, we encountered a problem.</strong> Please refresh your browser and try again. If you '
                           +'continue to experience problems, please contact Remote Learner.',
        lang_error: 'Error Details: ',
        lang_actions: 'Actions',
        lang_page: 'Page',
        lang_result: 'Result',
        lang_results: 'Results',
        lang_showing: 'Showing',
        lang_loading: '<strong>Loading List...</strong>',
        actions: []
    }

    var opts = $.extend({}, this.default_opts, options);
    var main = this;

    /**
     * @var object Queues and timeouts for various ajax calls
     */
    this.bulklist_add_queue = {elements: [], timeout: null, ajax:null};
    this.bulklist_remove_queue = {elements: [], timeout: null, ajax:null};
    this.bulklist_get_queue = {page: 1, timeout: null};
    this.updatetable_queue = {timeout: null, ajax: null};

    /**
     * @var string The ajax endpoint to send results requests to
     */
    this.results_endpoint = opts.dataurl;

    /**
     * @var object The current filterdata. This is an object with the filter name as each key, and an array of values to filter on
     *             for each value.
     */
    this.filters = {};

    /**
     * @var object Currently displayed fields (table columns)
     */
    this.column_labels = {};

    /**
     * @var int Number of columns being displayed.
     */
    this.num_columns = 0;

    /**
     * @var object Sorting data. This is an object with the column name as key, direction (asc/desc) as value.
     */
    this.fieldsort = {};

    /**
     * @var int The current page of results.
     */
    this.page = 1;

    /**
     * @var string Unique key to link sessions/browser windows.
     */
    this.uniqid = opts.uniqid;

    /**
     * @var int The number of results currently in the page across all pages.
     */
    this.numresults = 0;

    /**
     * Does a delayed table update.
     *
     * Will update the table in 500ms unless somethings calls this again, in which case the timer will start over. This is to
     * prevent firing off many updates in rapid succession in, for example, the textsearch filter, where this is called after
     * every keystroke.
     */
    this.updatetable = function() {
        if (main.updatetable_queue.timeout != null) {
            clearTimeout(main.updatetable_queue.timeout);
        }
        main.updatetable_queue.timeout = setTimeout(main.doupdatetable, 500);
    }

    /**
     * Abort a previous ajax call to update the table.
     *
     * This is fired every time doupdatetable is fired, but will only abort a request if there is another request current in
     * process.
     */
    this.abortupdatetable = function() {
        if (main.updatetable_queue.ajax && main.updatetable_queue.ajax.readyState != 4) {
            main.updatetable_queue.ajax.abort();
        }
    }

    /**
     * Updates the table.
     *
     * Makes an asynchronous request to opts.dataurl with the current page, sortdata, fields, and filters.
     * Receives data and sends to renderers.
     */
    this.doupdatetable = function() {
        ds_debug('[datatable.doupdatetable] About to update with filter data: ', main.filters);

        main.abortupdatetable();

        var ajaxdata = {
            m: 'datatable_results',
            limit_from: ((main.page-1)*opts.resultsperpage),
            limit_num: opts.resultsperpage,
            sesskey: opts.sesskey,
            uniqid: opts.uniqid,
            sort: main.fieldsort,
            filters: JSON.stringify(main.filters)
        };

        main.addClass('loading');

        if (main.bulklist_remove_queue.elements.length > 0) {
            ajaxdata.bulklist_remove = main.bulklist_remove_queue.elements;
            main.bulklist_remove_queue.timeout = null;
        }

        if (main.bulklist_add_queue.elements.length > 0) {
            ajaxdata.bulklist_add = main.bulklist_add_queue.elements;
            main.bulklist_add_queue.timeout = null;
        }

        main.updatetable_queue.ajax = $.ajax({
            type: 'POST',
            url: main.results_endpoint,
            data: ajaxdata,
            dataType: 'text',
            success: function(data) {
                main.removeClass('loading');

                try {
                    data = ds_parse_safe_json(data);
                } catch(err) {
                    main.render_error(err);
                    return false;
                }

                ds_debug('[datatable.doupdatetable] Updated. Data received: ', data);

                if (typeof(data.bulklist_modify) != 'undefined' && typeof(data.bulklist_modify.result) != 'undefined'
                        && data.bulklist_modify.result == 'success') {
                    if (main.bulklist_remove_queue.timeout == null && main.bulklist_add_queue.timeout == null) {
                        main.trigger('bulklist_modified', data.bulklist_modify);
                        main.bulklist_remove_queue.elements = [];
                        main.bulklist_add_queue.elements = [];
                    }
                }

                if (typeof(data.datatable_results) != 'undefined' && typeof(data.datatable_results.result) != 'undefined'
                        && data.datatable_results.result == 'success') {
                    main.column_labels = data.datatable_results.column_labels;
                    main.numresults = data.datatable_results.total_results;
                    main.render_headings(main.column_labels);
                    main.render_data(data.datatable_results.results);
                    main.render_sort_display();

                    main.siblings('.ds_pagelinks').remove();
                    main.after(main.render_pagelinks());

                    main.trigger('datatable_updated');
                } else {
                    main.render_error(data.msg);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                main.removeClass('loading');
                if (errorThrown != 'abort') {
                    main.render_error(textStatus+' :: '+errorThrown);
                }
            }
        });
    }

    /**
     * Fetches all search results with the current filterdata and fires an event with them.
     *
     * This is used by other elements that want to do something will the search results as a whole - for example, the a bulk action
     * panel.
     */
    this.bulklist_add_by_filters = function() {
        ds_debug('[datatable.bulklist_add_by_filters] Adding all elements to bulk list matching filterdata: ', main.filters);

        $.post(
            main.results_endpoint,
            {
                m: 'add_all',
                page: 1,
                uniqid: opts.uniqid,
                filters: JSON.stringify(main.filters)
            },
            function(data) {
                try {
                    data = ds_parse_safe_json(data);
                } catch(err) {
                    main.render_error(err);
                }

                ds_debug('[datatable.bulklist_add_by_filters] Returning: ', data);
                main.trigger('bulklist_add_by_filters_complete', data);
            },
            'text'
        );
    }

    /**
     * Remove an element from the table, and perform all necessary UI effect.
     * @param string actiontype The action that removed the element.
     * @param int id The ID of the element to remove.
     */
    this.removefromtable = function(actiontype, id) {
        console.log('removing '+id+' from table');
        var queuename = actiontype+'_queue';
        if (typeof(main[queuename]) == 'undefined') {
            main[queuename] = {
                timeout: null,
                elements: [],
                ajax: null
            }
        }

        var row = $('tr[data-id='+id+']');
        row.remove();
        main.add_loading_row();

        if (main[queuename].timeout != null) {
            clearTimeout(main[queuename].timeout);
            main[queuename].timeout = null;
        }
        main[queuename].elements.push(id);
        main[queuename].timeout = setTimeout(function() { main.addtotable(actiontype); }, 500);
    }

    /**
     * Adds individual elements to the end of the current page. This is used when compensating for moving elements to the bulk
     * list, or for actions that remove items from the list, i.e. assign actions.
     * @param string actiontype The action that removed the element.
     * @return bool Success/Failure
     */
    this.addtotable = function(actiontype) {
        var queuename = actiontype+'_queue';
        main[queuename].timeout = null;

        if (main[queuename].elements.length <= 0) {
            return false;
        }
        var amountrequested = main[queuename].elements.length;
        var ajaxdata = {
            m: 'datatable_results',
            limit_from: ((main.page*opts.resultsperpage)-main[queuename].elements.length),
            limit_num: amountrequested,
            sesskey: opts.sesskey,
            uniqid: opts.uniqid,
            sort: main.fieldsort,
            filters: JSON.stringify(main.filters),
            bulklist_add: []
        }
        ajaxdata[actiontype] = [];

        for (var i in main[queuename].elements) {
            ajaxdata[actiontype].push(main[queuename].elements[i]);
        }

        // Initalize queue if necessary
        if (typeof(main.addtotablequeue) == 'undefined') {
            this.addtotablequeue = [];
        }
        if (typeof(main.addtotablequeue[queuename]) == 'undefined') {
            main.addtotablequeue[queuename] = {
                ajax: null
            }
        }

        // If an existing request has yet to finish, abort.
        if (main.addtotablequeue[queuename].ajax && main.addtotablequeue[queuename].ajax.readyState != 4) {
            main.addtotablequeue[queuename].ajax.abort();
        }

        main.addtotablequeue[queuename].ajax = $.ajax({
            type: 'POST',
            url: main.results_endpoint,
            data: ajaxdata,
            dataType: 'text',
            success: function(data) {

                    // We specifically remove the elements we sent rather than clearing the whole queue in case new elements
                    // have been added since.
                    if (ajaxdata[actiontype].length > 0) {
                        for (var i in ajaxdata[actiontype]) {
                            var valpos = $.inArray(ajaxdata[actiontype][i], main[queuename].elements);
                            if (valpos >= -1) {
                                main[queuename].elements.splice(valpos, 1);
                            }
                        }
                    }

                    // Try to parse the returned JSON, or show error.
                    try {
                        data = ds_parse_safe_json(data);
                    } catch(err) {
                        main.render_error(err);
                        return false;
                    }

                    ds_debug('[datatable.doupdatetable] Updated. Data received: ', data);

                    // If the bulklit was modified, fire event.
                    if (typeof(data.bulklist_modify) != 'undefined' && typeof(data.bulklist_modify.result) != 'undefined'
                            && data.bulklist_modify.result == 'success') {
                        if (main.addtotablequeue[queuename].ajax && main.addtotablequeue[queuename].ajax.readyState == 4) {
                            main.trigger('bulklist_modified', data.bulklist_modify);
                        }
                    }

                    // If we have a successful response, add the results to the table.
                    if (typeof(data.datatable_results) != 'undefined' && typeof(data.datatable_results.result) != 'undefined'
                            && data.datatable_results.result == 'success') {
                        main.numresults = data.datatable_results.total_results;
                        if (data.datatable_results.results.length > 0) {
                            var loadingremoved = 0;
                            for (var i in data.datatable_results.results) {
                                main.find('tr.loading').slice(0, 1).before(main.render_row(data.datatable_results.results[i]));
                                main.remove_loading_row();
                                loadingremoved++;
                            }
                            if (amountrequested > loadingremoved) {
                                var loadingremains = amountrequested - loadingremoved;
                                for (var i = 0; i < loadingremains; i++) {
                                    main.remove_loading_row();
                                }
                            }

                            main.render_sort_display();
                        } else {
                            main.find('tr.loading').slice(0, ajaxdata[actiontype].length).remove();
                            if (main.find('tr').length == 1) {
                                // no results
                                main.find('tr.loading').remove();
                                var rowhtml = '<tr><td class="no_results" colspan="'+(main.num_columns)+'">';
                                rowhtml += opts.lang_no_results+'</td></tr>';
                                var row = $(rowhtml);
                                main.append(row);
                            }
                        }
                        main.siblings('.ds_pagelinks').remove();
                        main.after(main.render_pagelinks());
                        return true;
                    } else {
                        main.render_error(data.msg);
                        return false;
                    }

            },
            error: function(jqXHR, textStatus, errorThrown) {
            }
        });
        return true;
    }

    /**
     * Removes an ID from the bulk list.
     *
     * @param int id An ID to remove from the bulk list.
     */
    this.bulklist_remove = function(id, timeout) {
        if (typeof(timeout) != 'number') {
            timeout = 1000;
        }

        main.abortupdatetable();
        if (main.bulklist_remove_queue.timeout != null) {
            clearTimeout(main.bulklist_remove_queue.timeout);
            main.bulklist_remove_queue.timeout = null;
        }
        if (typeof(id) != 'undefined') {
            main.bulklist_remove_queue.elements.push(id);
        } else {
            main.bulklist_remove_queue.elements.push('*');
        }

        main.bulklist_remove_queue.timeout = setTimeout(main.doupdatetable, timeout);
    }

    /**
     * Cleans the bulklist.
     *
     * Removes all elements. This is fired when the user leaves the page, if they have elements in the bulk list.
     */
    this.bulklist_clean = function() {
        $.ajax({
            type: 'POST',
            url: main.results_endpoint,
            data: {
                m: 'bulklist_modify',
                modify: 'remove',
                uniqid: opts.uniqid,
                sesskey: opts.sesskey,
                ids: ['*']
            },
            dataType: 'text',
            async: false,
            success: function(data) {
                ds_debug('[deepsight_datatable] Bulk Selections Cleaned.');
            }
        });
    }

    /**
     * Start a timeout to get a page of the bulklist
     * @param int page The page number to fetch.
     */
    this.bulklist_get = function(page) {
        if (main.bulklist_get_queue.timeout != null) {
            clearTimeout(main.bulklist_get_queue.timeout);
            main.bulklist_get_queue.timeout = null;
        }
        main.bulklist_get_queue.page = page;
        main.bulklist_get_queue.timeout = setTimeout(main.do_bulklist_get, 250);
    }

    /**
     * Perform the ajax to get bulklist information.
     */
    this.do_bulklist_get = function() {
        $.ajax({
            type: 'POST',
            url: main.results_endpoint,
            data: {
                m: 'bulklist_get',
                uniqid: opts.uniqid,
                page: main.bulklist_get_queue.page,
            },
            dataType: 'text',
            success: function(data) {
                try {
                    data = ds_parse_safe_json(data);
                } catch(err) {
                    main.render_error(err);
                    return false;
                }
                main.trigger('bulklist_got', data);
            },
            error: function(jqXHR, textStatus, errorThrown) {

            }
        });
    }

    /**
     * Adds filter data to the table.
     *
     * @param string filtername The name of the filter
     * @param mixed  val        The value to filter on.
     */
    this.filter_add = function(filtername, val) {
        if (typeof(main.filters[filtername]) == 'undefined') {
            main.filters[filtername] = [];
        }
        main.filters[filtername].push(val);
        main.page = 1;
    }

    /**
     * Removes filter data.
     *
     * @param string filtername The name of the filter
     * @param mixed  val        The value to remove. If not defined, ALL values for the filter will be removed.
     */
    this.filter_remove = function(filtername, val) {
        if (typeof(val) != 'undefined') {
            var index = $.inArray(val, main.filters[filtername]);
            if (index >= 0) {
                main.filters[filtername].splice(index,1);
            }
            if (main.filters[filtername].length == 0) {
                delete main.filters[filtername];
            }
        } else {
            delete main.filters[filtername];
        }
    }

    /**
     * Registers that a filter with the datatable.
     *
     * Registers that a filter has been added to the datatable, without adding an actual filtering value. This is used to add more
     * columns to the table when new filters are added.
     *
     * @param string filtername The name of the filter we're adding.
     */
    this.filter_register = function(filtername) {
        if (typeof(main.filters[filtername]) == 'undefined') {
            main.filters[filtername] = [];
        }
        ds_debug('[datatable.filter_register] Registered '+filtername+'. Cur filterdata:', main.filters);
    }

    /**
     * Updates visual elements to indicate sorting.
     *
     * If colname is specified, it will indicate [dir] sorting on that column, if it is undefined, it will use
     * information stored in main.fieldsort.
     *
     * @param string colname The column's name to sort.
     * @param string dir     Either 'asc' for ascending, or 'desc' for descending
     */
    this.render_sort_display = function(colname, dir) {
        if (typeof(colname) == 'undefined') {
            if (jQuery.isEmptyObject(main.fieldsort) != true) {
                for (var field in main.fieldsort) {
                    main.render_sort_display(field, main.fieldsort[field]);
                }
            }
        } else {
            var sorticon = (dir == 'asc') ? 'elisicon-sortasc' : 'elisicon-sortdesc';
            main.find('tr:first').find('th.sorting').removeClass('sorting').find('i').removeClass().addClass('elisicon-sortable');

            var header = main.find('tr:first').find('th.field_'+colname);
            header.addClass('sorting');
            header.find('i').removeClass().addClass(sorticon);

            main.find('td.field_'+colname).addClass('sorting');
            ds_debug('[datatable.render_sort_display] updated sort display: '+colname+'/'+dir);
        }
    }

    /**
     * Removes sorting indication from all columns
     */
    this.remove_sort = function() {
        main.find('tr:first').find('th.sorting').removeClass('sorting').find('i').removeClass().addClass('elisicon-sortable');
    }

    /**
     * Changes sorting the table for a particular column.
     *
     * If not currently sorting on that column, sort ascending. If sorting ascending, sort descending. If sorting descending, sort
     * ascending.
     *
     * @param string field The name of the column.
     */
    this.sort = function(colname) {
        var dir = 'asc';

        if (typeof(main.fieldsort[colname]) == 'undefined') {
            main.fieldsort = {};
            main.fieldsort[colname] = 'asc';
            main.render_sort_display(colname, 'asc');
        } else if (main.fieldsort[colname] == 'asc') {
            main.fieldsort = {};
            main.fieldsort[colname] = 'desc';
            main.render_sort_display(colname, 'desc');
        } else {
            main.fieldsort = {};
            main.remove_sort();
        }

        main.updatetable();
    }

    /**
     * Renders an error message for the table.
     *
     * @param string error_message The error message to render.
     */
    this.render_error = function(error_message) {
        main.find('tr').remove();
        main.siblings('.ds_pagelinks').remove();
        main.append('<tr><td class="no_results">'+opts.lang_errormessage+'<br /><br />'+opts.lang_error+error_message+'</td></tr>');
        main.trigger('datatable_updated');
    }

    /**
     * Renders an generic message for the table.
     *
     * @param string message The message to display.
     */
    this.render_message = function(message) {
        main.find('tr').remove();
        main.siblings('.ds_pagelinks').remove();
        main.append('<tr><td class="no_results">'+message+'</td></tr>');
        main.trigger('datatable_updated');
    }

    /**
     * Renders a modal dialog over top of the table, and disables interaction with the table until the modal is dismissed.
     *
     * @param string html     The innerHTML for the modal.
     * @param string cssclass A CSS class to add to the modal.
     */
    this.render_modal = function(html, cssclass) {
        var mainposition = main.position();

        var modalblocker = $('<div style="position:absolute;background-color:rgba(255,255,255,0.6);"></div>');
        modalblocker.css({
            width: '100%',
            height: main.outerHeight(),
            left: mainposition.left,
            top: mainposition.top,
        });
        main.after(modalblocker);

        var modalcssclass = (typeof(cssclass) != 'undefined') ? cssclass : '';
        var modal = $('<div class="deepsight_modal '+modalcssclass+'" style=""></div>').click(function(e) { e.stopPropagation(); });

        modal.remove_modal = function(e) {
            if (typeof(e) != 'undefined') {
                e.stopPropagation();
            }
            modalblocker.remove();
            modal.remove();
            $(document).unbind('click', main.remove_modal);
        }

        modal.render_error = function(msg) {
            var errorhtml = '<div class="errormessage">'+opts.lang_errormessage+'<br /><br />'+opts.lang_error+msg+'</div>';
            this.html(errorhtml);
        }

        var modalclose = $('<div class="closebutton">X</div>').click(modal.remove_modal);
        $(document).bind('click', modal.remove_modal);

        modal.append(modalclose);
        modal.append(html);

        modal.css({
            left: '50%',
            top: mainposition.top+'px'
        });

        main.after(modal);
        return modal;
    }

    /**
     * Renders headings for the passed fielddata, and initializes any applicable actions.
     *
     * @param object fielddata An object of columns consisting of colname => label
     */
    this.render_headings = function(fielddata) {
        main.num_columns = 0;

        // remove existing table headings
        main.find('tr:first-child').remove();

        // render and add table headings
        var header = $('<tr></tr>');

        if (opts.dragdrop == true) {
            header.append('<th class="header" style="overflow:hidden;"></th>');
            main.num_columns++;
        }

        for (var field in fielddata) {
            var headerhtml = '<th class="header sortable field_'+field+'" data-colname="'+field+'"><i class="elisicon-sortable"></i>';
            headerhtml += fielddata[field]+'</th>';
            header.append(headerhtml);
            main.num_columns++;
        }

        header.append('<th class="header actions">'+opts.lang_actions+'</th>');
        main.num_columns++;

        main.prepend(header);

        // initialize sorting.
        main.find('tr:first-child').find('th.sortable').click(function(e) {
            main.sort($(this).data('colname'));
        });
    }

    /**
     * Renders table data and initializes any application actions + interactions.
     *
     * @param array data The data returned from the dataurl when receiving new data. This is an array of objects, with each object
     *                   representing a row of data.
     */
    this.render_data = function(data) {
        ds_debug('[datatable.render_data] Rendering new data', data);

        // remove all rows except headers
        main.find('tr:not(:first-child)').remove();
        $(document).find('.fancy_tooltip').remove(); //sometimes these get stuck

        // render and add new rows
        if (data.length > 0) {
            if (opts.multiselect == true) {
                main.multiselect = $('<div></div>');
                main.multiselect.deepsight_multiselect({parent:'table'});
            }

            for (var i in data) {
                main.append(main.render_row(data[i]));
            }

        } else {
            // no results
            var row = $('<tr><td class="no_results" colspan="'+(main.num_columns)+'">'+opts.lang_no_results+'</td></tr>');
            main.append(row);
        }

        if (typeof(main.multiselect) != 'undefined') {
            main.multiselect.bind('selection_changed', function(e) {
                main.trigger('selection_changed', {total: main.find('tr.ds_multiselect.selected').length});
            });
        }
    }

    /**
     * Renders a single row of the table, including associated js actions.
     *
     * @param object data An object containing information for this row. This data is returned in the "datatable_results" parameter
     *                    when the datatable is updated, and it's contents are dependant on the currently enable columns.
     */
    this.render_row = function(data) {
        var row = (opts.dragdrop == true)
            ? $('<tr data-id="'+data.id+'" data-label="'+data.meta.label+'"><td class="movehandle">::</td></tr>')
            : $('<tr id="ds_result_'+data.id+'" data-id="'+data.id+'"></tr>');

        for (var field in main.column_labels) {
            cell = $('<td class="field_'+field+'"></td>').html(data[field]);
            row.append(cell);
        }

        // render + add actions
        var actionhtml = '';
        for (var j in opts.actions) {
            if (typeof(opts.actions[j].condition) == 'undefined' || opts.actions[j].condition == null
                    || (typeof(opts.actions[j].condition) == 'function' && opts.actions[j].condition(data) === true)) {
                actionhtml += '<span class="deepsight_action_'+opts.actions[j].type+' deepsight_action_'+opts.actions[j].name+'"></span>';
            }
        }

        row.append('<td class="actions">'+actionhtml+'</td>');

        if (typeof(opts.rowfilter) == 'function') {
            row = opts.rowfilter(row, data);
        }

        // initialize actions
        var actionobjs = {};
        for (var i in opts.actions) {
            var actioninitiator = row.find('.deepsight_action_'+opts.actions[i].name);
            var numtds = row.children('td').length;
            var func = 'deepsight_action_'+opts.actions[i].type;

            // construct parentid array (this will only have one element since these actions are for a single element)
            var parentidmember = {};
            parentidmember[row.data('id')] = row.data('label');
            parentid = JSON.stringify(parentidmember);

            var defaultopts = {
                rowdata: data,
                parent: row,
                parentid: parentid,
                datatable: main,
                label: opts.actions[i].label,
                icon: opts.actions[i].icon,
                type: opts.actions[i].type,
                wrapper: '<tr><td style="padding:0;margin:0;" colspan="'+numtds+'"></td></tr>'
            };
            if (typeof(opts.actions[i].opts) == 'undefined') {
                opts.actions[i].opts = {};
            }
            var actionopts = $.extend({}, defaultopts, opts.actions[i].opts);
            actionobjs[opts.actions[i].name] = actioninitiator[func](actionopts);

            if (typeof(opts.actions[i].completefunc) != 'undefined') {
                actioninitiator.bind('action_complete', opts.actions[i].completefunc);
            }
        }

        main.bind('action_complete', function(e, data) {
            for (var i in actionobjs) {
                var incomingid = data.opts.parent.data('id');
                var activeid = actionobjs[i].parent.data('id');
                if (incomingid == activeid) {
                    // Try to hide action, if available.
                    try {
                        actionobjs[i].hide_action();
                    } catch (err) {

                    }
                }
            }
        });

        // initialize drap+drop
        if (opts.dragdrop == true) {
            row.draggable({
                distance: 10,
                revert: 'invalid',
                revertDuration: 250,
                cursor: 'move',
                scroll: false,
                opacity: 0.9,
                cursorAt: { top: 13, left: 15 },
                appendTo: 'body',
                start: function() {
                    main.find('tr.selected').addClass('dragactive');
                    $(this).addClass('dragactive');
                    main.trigger('dragstart');
                },
                stop: function() {
                    if ($(this).hasClass('dropped') != true) {
                        main.find('tr.selected').removeClass('dragactive');
                        $(this).removeClass('dragactive');
                    }
                    $('body').css('cursor', 'auto');
                    main.trigger('dragstop');

                    $('.deepsight_datatable_draghelper').remove();
                },
                helper: function( event ) {
                    if ($(this).hasClass('selected') != true) {
                        $(this).click();
                    }
                    var helperhtml = '';
                    main.find('tr.selected').each(function() {
                        helperhtml += '<div>'+$(this).data('label')+'</div>';
                    });

                    var rethtml = '<div class="deepsight_datatable_draghelper" style="position:absolute;z-index:100;cursor:move;">';
                    rethtml += helperhtml+'</div>';

                    return $(rethtml);
                }
            });
        }

        if (opts.multiselect == true) {
            main.multiselect.add_element(row);
        }
        return row;
    }

    /**
     * Add a placeholder row when we are loading single rows of the table
     */
    this.add_loading_row = function() {
        var row = $('<tr class="loading"><td colspan="'+(main.num_columns)+'">&nbsp;</td></tr>');
        main.append(row);
    }

    /**
     * Removes the top-most loading row
     */
    this.remove_loading_row = function() {
        main.find('tr.loading').slice(0, 1).remove();
    }

    /**
     * Renders pagination links for the table
     */
    this.render_pagelinks = function() {
        var pagelinks = $('<div></div>');
        var paginationlang = {
            lang_result: opts.lang_result,
            lang_results: opts.lang_results,
            lang_showing: opts.lang_showing,
            lang_page: opts.lang_page
        };
        pagelinks.deepsight_pagination(main.page, main.numresults, opts.resultsperpage, paginationlang);
        pagelinks.bind('pagechange', function(e, data) {
            main.change_page(data.page);
        });
        return pagelinks;
    }

    /**
     * Navigates to a new page of results.
     *
     * @param int page The requested page number.
     */
    this.change_page = function(page) {
        main.page = page;
        main.updatetable();
    }

    /**
     * Object initializer
     *
     * Updates the datatable to get initial data.
     */
    this.initialize = function() {
        // initialize initial filters
        if (opts.initial_filters.length > 0) {
            for (var i in opts.initial_filters) {
                main.filters[opts.initial_filters[i]] = [];
            }
        }
        main.addClass('loading');
        main.render_message(opts.lang_loading);
        main.doupdatetable();

        // IE8 highlights everything under a drag :/ .
        if ($('body').hasClass('ie8')) {
            main.disableSelection();
        }
    }

    main.initialize();
    return main;
}

/**
 * DeepSight Dropdown
 * This will attach a generic dropdown element, with the associated actions to show/hide. Dropdowns will appear aligned
 * to the left side of the activator, unless this would draw them off-screen. If they would be drawn off-screen, they are
 * aligned to the right of the activator.
 *
 * Usage:
 *     $('#activator').attach_dropdown();
 *     Adds a dropdown property to the object it was run on, so you can down perform jquery operations on the dropdown
 *     object, i.e. setting html, attaching other actions
 *
 * Options
 *     string css_dropdown_class         The CSS class to add to the dropdown (Don't change unless necessary)
 *     string css_activator_class        The CSS class to dropdown activator (Don't change unless necessary)
 *     string css_activator_active_class The CSS class to add to the dropdown activator when the dropdown is active.
 *
 * @param object options Options object (See Options section above for description)
 */
$.fn.attach_dropdown = function(options) {
    this.default_opts = {
        css_dropdown_class: 'deepsight_dropdown',
        css_activator_class: 'deepsight_dropdown_activator',
        css_activator_active_class: 'active'
    }

    // assemble combined options
    var opts = $.extend({}, this.default_opts, options);

    // Reference to this for use inside closures.
    var main = this;

    /**
     * @var object The jQuery object of the dropdown.
     */
    this.dropdown = null

    /**
     * Initializs the dropdown.
     *
     * Performs the following actions:
     *     - renders and adds the dropdown to the document
     *     - adds actions to the dropdown to maintain dropdown stability (i.e. stopPropagation when clicked
     *     - adds actions to the activator to show/hide the dropdown, and reposition as necessary.
     */
    this.initialize = function() {
        main.addClass(opts.css_activator_class);

        // render dropdown
        var dropdownhtml = '<div id="'+main.prop('id')+'_dropdown" class="'+opts.css_dropdown_class+'" ';
        dropdownhtml += 'style="display:none;z-index:100;position:absolute;"></div>';
        main.dropdown = $(dropdownhtml);
        main.dropdown
            // prevent clicks on the dropdown from bubbling up to the document and hiding our dropdown
            .click(function(e) {
                e.stopPropagation();
            })
            // this is a bit of bandaid for the case where someone clicks the button, then drags to the dropdown before releasing
            // the button
            .mouseup(function(e) {
                if (main.hasClass(opts.css_activator_active_class) == false) {
                    main.addClass(opts.css_activator_active_class);
                }
            });

        $('body').append(main.dropdown);

        main
            .click(function(e) {
                if (main.hasClass(opts.css_activator_active_class) == false) {
                    e.stopPropagation();
                    main.toggleClass(opts.css_activator_active_class);
                }
            })
            .mousedown(function(e) {
                if (main.hasClass(opts.css_activator_active_class) == false) {

                    // hide existing dropdowns
                    $.deactivate_all_filters();

                    // show and position the dropdown
                    var offset = main.offset();

                    // determine left - if the right side of the dropdown would go off the page, we'll align the right sides
                    // instead.
                    var ddright = offset.left + main.dropdown.width();
                    var windowright = $(window).width();

                    ddleft = (ddright > windowright) ? (offset.left - main.dropdown.width() + main.outerWidth()) : offset.left;

                    main.dropdown.toggle().offset({
                        left:ddleft,
                        top:offset.top + main.outerHeight() - 1
                    });

                    $(document).bind('click', $.deactivate_all_filters);
                }
            });
    }

    this.initialize();
    return main;
}

/**
 * A general function to deactivate all filters.
 */
$.deactivate_all_filters = function() {
    $('.deepsight_dropdown').hide();
    $('.deepsight_dropdown_activator.active').removeClass('active');
    $('.deepsight_filter-textsearch').removeClass('active');
}

/**
 * DeepSight MultiSelect
 * Allows selection of elements, including multiselection with control and shift, as well as up/down arrow keyboard control
 *
 * Usage:
 *     $('.selectable').deepsight_multiselect();
 *     The selector should match a set elements you want to select.
 *     (i.e. '.selectable' is the class of the actual selectable elements)
 *
 * Options
 *     string       css_selected_class    The class that's applied to selected elements, and to determine which elements are
 *                                        selected.
 *     false/string parent                If set to a selector, this element will receive a class when selectable elements are
 *                                        focused.
 *                                        Selectable elements must be focused to use keyboard control. This must be a parent of all
 *                                        selectable elements.
 *     string       css_parentfocus_class The css class that will be applied to the parent (if not false) when selectable elements
 *                                        are focused.
 *
 * @param object options Options object (See Options section above for description)
 */
$.fn.deepsight_multiselect = function(options) {
    this.default_opts = {
        css_class: 'ds_multiselect',
        css_selected_class: 'selected',
        parent: false,
        css_parentfocus_class: 'focused'
    };
    var opts = $.extend({}, this.default_opts, options);
    var main = this;

    this.last_selected = null;
    this.focused = false;

    /**
     * Selects an element.
     *
     * @param object clicked_ele The jQuery object of the clicked element.
     * @param string multimode   Whether we're in a multi-select mode. This can be empty, or one of the following:
     *                               'ctrl'  The "control" button is pressed, add this element to the selection
     *                               'shift' The "shift" button is pressed, add this and all elements between this
     *                                       and the last selected element to the selection
     *                               [empty] Anything other than those two strings will select only the clicked element,
     *                                       deselecting all others.
     */
    this.select = function(clicked_ele, multimode) {
        if (multimode == 'ctrl') {
            if (clicked_ele.hasClass('disabled') != true) {
                clicked_ele.toggleClass(opts.css_selected_class);
            }
        } else if (multimode == 'shift') {
            var thisselected = clicked_ele;
            var selecting = false;
            $('.'+opts.css_class).each(function(e) {
                if ($(this).hasClass('disabled') != true) {
                    if ($(this)[0] == main.last_selected[0] || $(this)[0] == thisselected[0]) {
                        selecting = (selecting == false) ? true : false;
                    }
                    if (selecting == true) {
                        $(this).addClass(opts.css_selected_class);
                    }
                }
            });
            if (clicked_ele.hasClass('disabled') != true) {
                clicked_ele.addClass(opts.css_selected_class);
            }
        } else {
            $('.'+opts.css_class).removeClass(opts.css_selected_class);
            if (clicked_ele.hasClass('disabled') != true) {
                clicked_ele.toggleClass(opts.css_selected_class);
            }
        }
        main.last_selected = clicked_ele;
        main.setfocus(true);
        main.trigger('selection_changed');
    }

    /**
     * Toggles the focus of the multiselect array.
     *
     * Toggles the opts.css_parentfocus_class on the parent element to record whether we're focused on the parent object
     * or not. This is a workaround for recording "focus" as non-input elements usually don't fire onFocus events.
     *
     * @param bool setto True to set parent as focused, false to set parent as blurred.
     */
    this.setfocus = function(setto) {
        if (setto == true) {
            if (opts.parent != false) {
                $('.'+opts.css_class).parents(opts.parent).addClass(opts.css_parentfocus_class);
            }
            main.focused = true;
        } else {
            if (opts.parent != false) {
                $('.'+opts.css_class).parents(opts.parent).removeClass(opts.css_parentfocus_class);
            }
            main.focused = false;
        }
    }

    /**
     * Fired whenever a key is pressed, provides keyboard control for selections.
     *
     * Provides "control" and "shift" multiselection (see main.select for descriptions on multi-selection modes.)
     * Provides up/down arrow key control of selections.
     *
     * @param object e The "keydown" jQuery event object.
     */
    this.keyboard_control = function(e) {
        if (main.focused == true) {
            var multimode = 'single';
            if (e.ctrlKey == true) {
                multimode = 'ctrl';
            } else if (e.shiftKey == true) {
                multimode = 'shift';
            }

            // up arrow key
            if (e.keyCode == 38) {
                if (main.last_selected != null) {
                    e.preventDefault();
                    var prev = main.last_selected.prev();
                    if (prev.length > 0 && !prev.is(':first-child')) {
                        main.select(prev, multimode);
                    }
                }
            }

            // down arrow key
            if (e.keyCode == 40) {
                if (main.last_selected != null) {
                    e.preventDefault();
                    var next = main.last_selected.next();
                    if (next.length > 0) {
                        main.select(next, multimode);
                    }
                }
            }
        }
    }

    /**
     * Adds an element to the multiselect array.
     *
     * @param object ele A jQuery object to add to the multiselect.
     */
    this.add_element = function(ele) {
        ele.addClass(opts.css_class);
        ele
            .click(function(e) {
                var multimode = 'single';
                if (e.ctrlKey == true) {
                    multimode = 'ctrl';
                } else if (e.shiftKey == true) {
                    multimode = 'shift';
                }
                main.select(ele, multimode);
            })
            .mousedown(function(e) {
                e.preventDefault();
            });

    }

    /**
     * Initializer
     *
     * Initializes multiselection on matching elements.
     *     - Provides click and key-based selection.
     */
    this.initialize = function() {
        $(document)
            .keydown(main.keyboard_control)
            .click(function(e) {
                // we need a click event to propagate as things like filter deactivation rely on it, so this is a
                // workaround to catch the event at the root, but still determine whether our click was on the target
                // element
                if ($(e.target).closest($('.'+opts.css_class)).length == 0) {
                    $('.'+opts.css_class).filter('.'+opts.css_selected_class).removeClass(opts.css_selected_class);
                    main.setfocus(false);
                    main.trigger('selection_changed');
                }
            });
    }

    this.initialize();

    main.each(function() {
        main.add_element($(this));
    });
    return main;
}

/**
 * Fancy Tooltip
 * Render's an element's title attribute in a more noticable and "pretty" way.
 *
 * Required Options:
 *     string position The position of the tooltip. Can be "bottom" or "top"
 *
 * @param object options An object of options. See the options section above.
 */
$.fn.fancy_tooltip = function(options) {
    this.default_opts = {
        position: 'bottom'
    }
    var opts = $.extend({}, this.default_opts, options);
    var ele = $(this);
    var main = this;
    var eletitle = ele.prop('title');

    ele.prop('title', '');

    /**
     * Show the tooltip
     * @param object event The mouseover event that initialized the function.
     */
    this.show_tooltip = function(event) {
        var eleoffset = ele.offset();
        var eleheight = ele.outerHeight();
        var elewidth = ele.outerWidth();
        var eleleft = eleoffset.left;
        var eletop = eleoffset.top;

        $('div.fancy_tooltip').remove();
        main.tooltip = $('<div class="fancy_tooltip '+opts.position+'" style="position:absolute;">'+eletitle+'</div>');
        $('body').append(main.tooltip);

        if (opts.position == 'bottom') {
            main.tooltip.offset({
                left: (eleleft - (main.tooltip.outerWidth() / 2) + 7),
                top: (eletop + eleheight + 10)
            });
        }
        if (opts.position == 'top') {
            main.tooltip.offset({
                left: (eleleft - (main.tooltip.outerWidth() / 2)),
            });
            var bottom = $(window).height() - eleheight - eletop + main.tooltip.outerHeight();
            main.tooltip.css('bottom', bottom+'px');
        }
    }

    /**
     * Hide the tooltip
     * @param object event The mouseout/click event object that initialized the function.
     */
    this.hide_tooltip = function(event) {
        main.tooltip.remove();
    }

    ele.mouseover(main.show_tooltip);
    ele.mouseout(main.hide_tooltip);
    ele.click(main.hide_tooltip);
}

})(jQuery);
