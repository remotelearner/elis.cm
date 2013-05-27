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
 * DeepSight Instructor Assign/Edit Action.
 * An action used assigning or editing instructors.
 *
 * Usage:
 *     $('[button selector]').each(function() { $(this).deepsight_action_instructor_assignedit(); });
 *
 * Required Options:
 *     object rowdata           An object of information for the associated row.
 *     object parent            A jquery element after which the panel will be added.
 *     string sesskey           The Moodle sesskey (sent with requests to prevent CSRF attacks)
 *     mixed  parentid          When completing the action, this ID will be passed to the actionurl to identify for which element the
 *                              action was completed. Can also be set to "bulklist" to apply the action to the entire bulklist.
 *     object datatable         The datatable object this action is used for.
 *     string actionurl         The URL to call when completing the action.
 *     string name              The name for the action instance.
 *     string mode              The mode to use the action in. Can be "assign" or "edit"
 *
 * Optional Options:
 *     string wrapper           A html string for a wrapper that will be placed around the action panel.
 *     string actionclass       A CSS class to attach to the action div.
 *     string actiontrclass     A Css class to attach the action's parent.
 *     int    trans_speed       The number of miliseconds to perform animations in.
 *     string langworking       The language string to display while loading.
 *     string langbulkconfirm   The language string to display when performing bulk actions.
 *     string langchanges       The language string to show when confirming changes.
 *     string langnochanges     The language string to show when confirming changes (when there are no changes).
 *     string langgeneralerror  Language string to show when an unknown error occurs.
 *     string langtitle         The title of the panel
 *     string langassigntime    Language string for "assignment time"
 *     string langcompletetime  Language string for "completion time"
 *     object lang_months       An object of language strings for names of the months, indexed by month number, starting from 0/Jan.
 *
 * @param object options Options object (See Options section above for description)
 * @return object Main object
 */
$.fn.deepsight_action_instructor_assignedit = function(options) {
    this.default_opts = {
        rowdata: {},
        parent: null,
        sesskey: null,
        parentid: null,
        datatable: null,
        actionurl: null,
        name: null,
        mode: 'enrol',
        wrapper: '<div></div>',
        actionclass: 'deepsight_actionpanel',
        actiontrclass: 'deepsight_actionpanel_tr',
        trans_speed: 100,
        langworking: 'Working...',
        langbulkconfirm: '',
        langchanges: 'The following changes will be applied:',
        langnochanges: 'No Changes',
        langgeneralerror: 'Unknown Error',
        langtitle: 'Association Data',
        langassigntime: 'Assigned Time',
        langcompletetime: 'Completion Time',
        lang_months: {
            0: 'Jan',
            1: 'Feb',
            2: 'Mar',
            3: 'Apr',
            4: 'May',
            5: 'Jun',
            6: 'Jul',
            7: 'Aug',
            8: 'Sep',
            9: 'Oct',
            10: 'Nov',
            11: 'Dec'
        },
    }

    var opts = $.extend({}, this.default_opts, options);
    var main = this;
    this.actiontr = null;
    this.name = opts.name;
    this.form = null;
    this.parent = opts.parent;

    this.fields = ['assigntime', 'completetime'];
    this.fieldcolumnmap = {
        assigntime: 'field_ins_assigntime',
        completetime: 'field_ins_completetime'
    }
    this.fieldlangmap = {
        assigntime: opts.langassigntime,
        completetime: opts.langcompletetime
    }

    /**
     * Renders the HTML for the action panel.
     * @param object data An object containing preselected data.
     * @return object A jQuery object representing the rendered action panel.
     */
    this.render_action = function(data) {
        if (typeof(data) == 'undefined') {
            data = {};
        }

        var bulkeditui = (opts.parentid == 'bulklist' && opts.mode == 'edit') ? true : false;

        var timeparams = ['assigntime', 'completetime'];
        var rendered = {};
        var html = '';
        for (var i = 0; i < timeparams.length; i++) {
            var preselectedtime = (typeof(data[timeparams[i]]) != 'undefined') ? JSON.parse(data[timeparams[i]]) : {};

            html = '';
            if (bulkeditui == true) {
                var checked = (typeof(data[timeparams[i]]) != 'undefined') ? 'checked="checked"' : '';
                html += '<input type="checkbox" name="'+timeparams[i]+'_enabled" class="'+timeparams[i]+'_enabled" '+checked+'/>';
            }
            html += deepsight_render_date_selectors(timeparams[i], timeparams[i], preselectedtime, opts.lang_months);
            rendered[timeparams[i]] = html;
        }

        // Set up action panel outline.
        var actionpanel = $('<div><div>').addClass(opts.actionclass).addClass('deepsight_action_confirm').css('display', 'none');
        var actionpanelbody = '<div class="body"></div>\n\
                               <div class="actions"><i class="elisicon-confirm"></i><i class="elisicon-cancel"></i></div>';
        actionpanel.html('<div class="deepsight_actionpanel_inner">'+actionpanelbody+'</div>');

        // Add form.
        var form = '<form><h3>'+opts.langtitle+'</h3>';
        form += '<div class="data_wrpr">';
        // Headers
        form += '<div>';
        form += '<span>'+opts.langassigntime+'</span>';
        form += '<span>'+opts.langcompletetime+'</span>';
        form += '</div>';
        // Inputs
        form += '<div>';
        form += '<span>'+rendered.assigntime+'</span>';
        form += '<span>'+rendered.completetime+'</span>';
        form += '</div>';
        form += '</div></form>';
        main.form = $(form);
        actionpanel.find('.body').append(main.form);

        // Add actions.
        if (bulkeditui == true) {
            actionpanel.find('select.assigntime').change(function(e) {
                actionpanel.find('input.assigntime_enabled').prop('checked', true);
            });
            actionpanel.find('select.completetime').change(function(e) {
                actionpanel.find('input.completetime_enabled').prop('checked', true);
            });
        }

        actionpanel.find('i.elisicon-confirm').bind('click', main.precomplete_action);
        actionpanel.find('i.elisicon-cancel').click(main.hide_action);
        return actionpanel;
    }

    /**
     * Render a field for display
     * @param string field The field to render.
     * @param string val The raw value we're rendering.
     * @return string The rendered field or an empty string.
     */
    this.render_field = function(field, val) {
        var output = '';
        switch (field) {
            case 'assigntime':
                var month = (val.month <= 9) ? '0'+(parseInt(val.month)+1) : (parseInt(val.month)+1);
                return val.year+'-'+month+'-'+val.day;

            case 'completetime':
                var month = (val.month <= 9) ? '0'+(parseInt(val.month)+1) : (parseInt(val.month)+1);
                return val.year+'-'+month+'-'+val.day;
        }
        return '';
    }

    /**
     * Gets entered information from the form. If we're performing a bulk action, will only get information that has been "enabled"
     * @return object data The gathered form data.
     */
    this.get_formdata = function() {
        var data = {};
        var bulk = (opts.parentid == 'bulklist') ? true : false;
        for (i = 0; i < main.fields.length; i++) {
            var enablerequired = (bulk == true && opts.mode == 'edit') ? true : false;
            var enabled = (main.form.find('.'+main.fields[i]+'_enabled').prop('checked') == true) ?  true : false;
            if ((enablerequired == true &&  enabled == true) || enablerequired == false) {
                data[main.fields[i]] = {
                    day: main.form.find('select.'+main.fields[i]+'day').val(),
                    date: main.form.find('select.'+main.fields[i]+'day').val(),
                    month: main.form.find('select.'+main.fields[i]+'month').val(),
                    year: main.form.find('select.'+main.fields[i]+'year').val()
                }
            }
        }
        return data;
    }

    /**
     * Change the parent ID of the action - i.e. change the element we're performing the action for.
     * @param mixed newparentid Normally this would be an int representing the ID of a single element, but could be
     *                          any information you want passed to the actionurl to represent data. for example, this
     *                          is an array when this action is used with the bulk action panel.
     */
    this.update_parentid = function(newparentid) {
        opts.parentid = newparentid;
    }

    /**
     * Selectively complete action or provide bulk action warning.
     * @param object e The jquery event object that initialized the completion.
     */
    this.precomplete_action = function(e) {
        assocdata = main.get_formdata();

        if (opts.parentid == 'bulklist') {
            if (opts.mode == 'edit') {
                main.actiontr.find('.body').html('<span style="display:block">'+opts.langchanges+'</span>');
                var changeshtml = '<div style="display:inline-block;width: 100%;">'+main.render_changes(assocdata)+'</div>';
                main.actiontr.find('.body').append(changeshtml);
                main.actiontr.find('i.elisicon-confirm')
                    .unbind('click', main.precomplete_action)
                    .bind('click', function(e) {
                        main.complete_action(e, assocdata);
                    });
                main.actiontr.find('i.elisicon-cancel')
                    .unbind('click', main.hide_action)
                    .bind('click', function(e) {
                        var newassocdata = {};
                        for (var i in main.fields) {
                            newassocdata[main.fields[i]] = JSON.stringify(assocdata[main.fields[i]]);
                        }
                        main.actiontr.remove();
                        main.form = null;
                        main.actiontr = null;
                        main.show_action(newassocdata, false);
                    });
            } else {
                main.actiontr.find('.body').html(opts.langbulkconfirm);
                main.actiontr.find('i.elisicon-confirm')
                    .unbind('click', main.precomplete_action)
                    .bind('click', function(e) {
                        main.complete_action(e, assocdata);
                    });
            }
        } else {
            main.complete_action(e, assocdata);
        }
    }

    /**
     * Render changed data for confirmation.
     * @param object data The changed data to render.
     * @return string The rendered HTML for the confirm changes screen.
     */
    this.render_changes = function(data) {
        var changeshtml = '';
        for (var i in main.fieldlangmap) {
            if (typeof(data[i]) != 'undefined') {
                changeshtml += '<li>'+main.fieldlangmap[i]+': '+main.render_field(i, data[i])+'</li>';
            }
        }
        if (changeshtml == '') {
            changeshtml += '<li>'+opts.langnochanges+'</li>';
        }
        return '<ul class="changes">'+changeshtml+'</ul>';
    }

    /**
     * Updates a row with new information.
     * @param object row The jQuery object for the row (i.e. the <tr>)
     * @param object displaydata The updated display data.
     */
    this.update_row = function(row, displaydata) {
        for (var k in main.fieldcolumnmap) {
            if (typeof(displaydata[k]) != 'undefined') {
                row.find('.'+main.fieldcolumnmap[k]).html(displaydata[k]);
            }
        }
        row.addClass('confirmed', 500).delay(1000).removeClass('confirmed', 500);
    }

    /**
     * Completes the action.
     * @param object e The jquery event object that initialized the completion.
     * @param object assocdata The data to complete the action with.
     */
    this.complete_action = function(e, assocdata) {
        var ajaxdata = {
            uniqid: opts.datatable.uniqid,
            sesskey: opts.sesskey,
            elements: opts.parentid,
            actionname: main.name,
            assocdata: JSON.stringify(assocdata)
        }

        main.actiontr.find('.deepsight_actionpanel').html('<h1>'+opts.langworking+'</h1>').addClass('loading');
        main.trigger('action_started');

        $.ajax({
            type: 'POST',
            url: opts.actionurl,
            data: ajaxdata,
            dataType: 'text',
            success: function(data) {
                main.hide_action();

                try {
                    data = ds_parse_safe_json(data);
                } catch(err) {
                    opts.datatable.render_error(err);
                    return false;
                }

                if (typeof(data) == 'object' && typeof(data.result) != 'undefined' && data.result == 'success') {
                    if (opts.parentid != 'bulklist') {
                        if (opts.mode == 'assign') {
                            opts.parent.addClass('confirmed').delay(1000).fadeOut(250, function() {
                                opts.datatable.removefromtable('assigned', opts.parent.data('id'));
                            });
                        } else {
                            if (typeof(data.displaydata) != 'undefined') {
                                main.update_row(opts.parent, data.displaydata);
                            }
                            if (typeof(data.saveddata) != 'undefined') {
                                for (var i = 0; i < main.fields.length; i++) {
                                    if (typeof(data.saveddata[main.fields[i]]) != 'undefined') {
                                        var rowdataparam = 'assocdata_'+main.fields[i];
                                        opts.rowdata[rowdataparam] = data.saveddata[main.fields[i]];
                                    }
                                }
                            }
                        }
                    }
                    ds_debug('[deepsight_action_instructor_assignedit.complete_action] Completed action, recevied data:', data);
                    main.trigger('action_complete', {opts:opts});
                    return true;
                }

                var error_message = (typeof(data) == 'object' && data != null && typeof(data.msg) != 'undefined')
                        ? data.msg : opts.langgeneralerror;
                opts.datatable.render_error(error_message);
                return true;
            },
            error: function(jqXHR, textStatus, errorThrown) {
                main.hide_action();
                opts.datatable.render_error(textStatus+' :: '+errorThrown);
                return false;
            }
        });
    }

    /**
     * Shows the action panel.
     * @param object assocdata Preselected Data.
     * @param bool doanimation Whether to animate showing the panel.
     */
    this.show_action = function(assocdata, doanimation) {
        if (opts.wrapper != null) {
            var rendered = main.render_action(assocdata);
            rendered.wrap(opts.wrapper);
            main.actiontr = rendered.parents().last();
        } else {
            main.actiontr = main.render_action(assocdata);
        }

        main.actiontr.addClass(opts.actiontrclass);
        opts.parent.after(main.actiontr);
        if (doanimation == true) {
            main.actiontr.find('.'+opts.actionclass).slideDown(opts.trans_speed);
        } else {
            main.actiontr.find('.'+opts.actionclass).show();
        }
        opts.parent.addClass('active');
    }

    /**
     * Hides the action panel.
     */
    this.hide_action = function() {
        if (main.actiontr != null) {
            main.actiontr.find('.'+opts.actionclass).slideUp(
                opts.trans_speed,
                function() {
                    main.actiontr.remove();
                    main.form = null;
                    main.actiontr = null;
                }
            );
            opts.parent.removeClass('active');
        }
    }

    /**
     * Toggles display of the action panel.
     * @param object e The click event that fired this function.
     */
    this.toggle_action = function(e) {
        var preselecteddata = {};
        for (i = 0; i < main.fields.length; i++) {
            var rowdataparam = 'assocdata_'+main.fields[i];
            if (typeof(opts.rowdata[rowdataparam]) != 'undefined') {
                preselecteddata[main.fields[i]] = opts.rowdata[rowdataparam];
            }
        }
        if (main.actiontr == null) {
            main.show_action(preselecteddata, true);
        } else {
            main.hide_action();
        }
    }

    /**
     * Set up action.
     */
    this.initialize = function() {
        if (opts.parentid == 'bulklist') {
            var actionicon = $('<i title="'+opts.label+'" class="deepsight_action_'+opts.type+' '+opts.icon+'">'+opts.label+'</i>');
        } else {
            var actionicon = $('<i title="'+opts.label+'" class="deepsight_action_'+opts.type+' '+opts.icon+'"></i>');
            actionicon.fancy_tooltip();
        }

        actionicon.click(main.toggle_action);
        main.append(actionicon);
    }

    this.initialize();
    return this;
}

})(jQuery);