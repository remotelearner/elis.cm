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

$.fn.deepsight_action_programcourse_assignedit = function(options) {
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
        langyes: 'Yes',
        langno: 'No',
        langreq: 'Required',
        langnonreq: 'Not Required',
        langtimeperiodyear: 'Year',
        langtimeperiodmonth: 'Month',
        langtimeperiodweek: 'Week',
        langtimeperiodday: 'Day',
        langgeneralerror: 'Unknown Error',
        langtitle: 'Association Data',
        langfrequency: 'Frequency',
        langtimeperiod: 'Timeperiod',
        langposition: 'Position'
    }

    var opts = $.extend({}, this.default_opts, options);
    var main = this;
    this.actiontr = null;
    this.name = opts.name;
    this.form = null;
    this.parent = opts.parent;

    this.fields = ['required', 'frequency', 'timeperiod', 'position'];
    this.fieldcolumnmap = {
        required: 'field_curcrs_required',
        frequency: 'field_curcrs_frequency',
        timeperiod: 'field_curcrs_timeperiod',
        position: 'field_curcrs_position'
    }
    this.fieldlangmap = {
        required: opts.langreq,
        frequency: opts.langfrequency,
        timeperiod: opts.langtimeperiod,
        position: opts.langposition
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

        // Required.
        var date = new Date();
        var requniq = date.getTime();
        var reqenabledchecked = (typeof(data.required) != 'undefined' && bulkeditui == true) ? 'checked="checked"' : '';
        if (typeof(data.required) != 'undefined' && data.required == 1) {
            var reqchecked = 'checked="checked"';
            var reqcheckclass = 'buttonset checked field-required';
            var reqlabelvisible = '';
            var nonreqlabelvisible = 'style="display:none"';
        } else {
            var reqchecked = '';
            var reqcheckclass = 'buttonset field-required';
            var reqlabelvisible = 'style="display:none"';
            var nonreqlabelvisible = '';
        }
        var required = (bulkeditui == true)
                ? '<input type="checkbox" name="required_enabled" class="required_enabled" '+reqenabledchecked+'/>' : '';
        required += '<input type="checkbox" id="field_req_'+requniq+'" class="'+reqcheckclass+'" name="required" '+reqchecked+'/>';
        required += '<label class="on buttonset" for="field_req_'+requniq+'" '+reqlabelvisible+'>'+opts.langreq+'</label>';
        required += '<label class="off buttonset" for="field_req_'+requniq+'" '+nonreqlabelvisible+'>'+opts.langnonreq+'</label>';

        // Frequency.
        if (typeof(data.frequency) != 'undefined') {
            var freqval = data.frequency;
            var freqenabledchecked = (bulkeditui == true) ? 'checked="checked"' : '';
        } else {
            var freqval = '';
            var freqenabledchecked = '';
        }
        var frequency = (bulkeditui == true)
                ? '<input type="checkbox" name="frequency_enabled" class="frequency_enabled" '+freqenabledchecked+'/>' : '';
        frequency += '<input type="text" class="field-frequency" name="frequency" value="'+freqval+'" />';

        // Time Period.
        var selected = '';
        var timeperiodenabledchecked = (bulkeditui == true && typeof(data.timeperiod) != 'undefined') ? 'checked="checked"' : '';
        var timeperiod = (bulkeditui == true)
                ? '<input type="checkbox" name="timeperiod_enabled" class="timeperiod_enabled" '+timeperiodenabledchecked+'/>' : '';
        timeperiod += '<select name="timeperiod" class="field-timeperiod">'
        var timeperiods = {
            year: opts.langtimeperiodyear,
            month: opts.langtimeperiodmonth,
            week: opts.langtimeperiodweek,
            day: opts.langtimeperiodday
        }
        for (var i in timeperiods) {
            selected = (typeof(data.timeperiod) != 'undefined' && data.timeperiod == i) ? 'selected="selected"' : '';
            timeperiod += '<option value="'+i+'" '+selected+'>'+timeperiods[i]+'</option>';
        }
        timeperiod += '</select>';

        // Position.
        if (typeof(data.position) != 'undefined') {
            var posval = data.position;
            var positionenabledchecked = (bulkeditui == true) ? 'checked="checked"' : '';
        } else {
            var posval = '';
            var positionenabledchecked = '';
        }
        var position = (bulkeditui == true)
                ? '<input type="checkbox" name="position_enabled" class="position_enabled" '+positionenabledchecked+'/>' : '';
        position += '<input type="text" class="field-position" name="position" value="'+posval+'" />';

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
        form += '<span>'+opts.langreq+'</span>';
        form += '<span>'+opts.langfrequency+'</span>';
        form += '<span>'+opts.langtimeperiod+'</span>';
        form += '<span>'+opts.langposition+'</span>';
        form += '</div>';
        // Inputs
        form += '<div>';
        form += '<span>'+required+'</span>';
        form += '<span>'+frequency+'</span>';
        form += '<span>'+timeperiod+'</span>';
        form += '<span>'+position+'</span>';
        form += '</div>';
        form += '</div></form>';
        main.form = $(form);
        actionpanel.find('.body').append(main.form);

        // Add actions.
        if (bulkeditui == true) {
            actionpanel.find('input.field-required').change(function(e) {
                actionpanel.find('input.required_enabled').prop('checked', true);
            });
            actionpanel.find('input.field-frequency').change(function(e) {
                actionpanel.find('input.frequency_enabled').prop('checked', true);
            });
            actionpanel.find('select.field-timeperiod').change(function(e) {
                actionpanel.find('input.timeperiod_enabled').prop('checked', true);
            });
            actionpanel.find('input.field-position').change(function(e) {
                actionpanel.find('input.position_enabled').prop('checked', true);
            });
        }
        actionpanel.find('.field-required').click(function(e) {
            actionpanel.find('.field-required').toggleClass('checked');
            actionpanel.find('.field-required').siblings('label').toggle();
        });
        actionpanel.find('i.elisicon-confirm').bind('click', main.precomplete_action);
        actionpanel.find('i.elisicon-cancel').click(main.hide_action);
        return actionpanel;
    }

    /**
     * Render a field for display
     * @param string field The field to render.
     * @param string val The raw value we're rendering.
     * @return string The rendered field.
     */
    this.render_field = function(field, val) {
        var output = '';
        switch (field) {
            case 'required':
                return (val == 1) ? opts.langyes : opts.langno;

            case 'frequency':
                return val;

            case 'timeperiod':
                var langtimeperiod = {
                    year: opts.langtimeperiodyear,
                    month: opts.langtimeperiodmonth,
                    week: opts.langtimeperiodweek,
                    day: opts.langtimeperiodday
                }
                return (typeof(langtimeperiod[val]) != 'undefined') ? langtimeperiod[val] : val;

            case 'position':
                return val;
        }
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
                if (main.fields[i] == 'required') {
                    data[main.fields[i]] = (main.form.find('.field-'+main.fields[i]).prop('checked') == true) ? 1 : 0;
                } else {
                    data[main.fields[i]] = main.form.find('.field-'+main.fields[i]).val();
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
                        main.actiontr.remove();
                        main.form = null;
                        main.actiontr = null;
                        main.show_action(assocdata, false);
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
                    ds_debug('[deepsight_action_programcourse.complete_action] Completed action, recevied data:', data);
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