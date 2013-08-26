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
 * DeepSight Enrolment/Edit Enrolment Action Panel
 * Adds an enrolment panel when the assigned element is clicked. This panel provides an interface to enrolment data - either to
 * enrol a new student, or to edit an existing one (or set of existing students).
 *
 * Features:
 *     - Enrol users
 *     - Edit existing enrolments (including pre-populating inputs)
 *     - Editing learning objective data
 *     - Bulk editing of enrolment data, including enabling/disabling inputs.
 *
 * Usage:
 *     $('[button selector]').each(function() { $(this).deepsight_action_enroledit(); });
 *
 * Required Options:
 *     object rowdata                     An object of information for the associated row.
 *     object parent                      A jQuery element after which the panel will be added.
 *     string sesskey                     The Moodle sesskey (sent with requests to prevent CSRF attacks).
 *     int    parentid                    When completing the action, this ID will be passed to the actionurl to identify for which
 *                                        element the action was completed.
 *     object datatable                   The datatable object this action is used for.
 *     string actionurl                   The URL to call when completing the action.
 *     string name                        A unique identifier that refers to this filter when talking to the endpoint.
 *     string mode                        "enrol" or "edit", based on whether we are enrolling a new user, or editing an existing
 *                                        user.
 *
 * Optional Options:
 *     string wrapper                     A html string for a wrapper that will be placed around the action panel.
 *     string actionclass                 A CSS class to attach to the action div.
 *     string actiontrclass               A Css class to attach the action's parent.
 *     int    trans_speed                 The number of miliseconds to perform animations in.
 *     string lang_enrolment_date         Language string for enrolment date.
 *     string lang_completion_status:     Language string for completion status.
 *     string lang_completion_notcomplete Language string for completion status "not complete".
 *     string lang_completion_passed      Language string for completion status "passed".
 *     string lang_completion_failed      Language string for completion status "failed".
 *     string lang_completion_on          Language string for "On" - shown before the completion time.
 *     string lang_grade                  Language string for grade.
 *     string lang_credits                Language string for credits.
 *     string lang_lock                   Language string for lock.
 *     string lang_locked                 Language string for locked.
 *     string lang_time_graded            Language string for learning objectives' time graded.
 *     string lang_enroldata              Language string for enrolment data title.
 *     string lang_learning_objectives    Language string for learning objectives title.
 *     string lang_bulk_confirm           Message displayed when applying the action to the bulk list.
 *     string lang_enrolled               Language string for "enrolled".
 *     object lang_months                 An object of language strings for the names of the months, indexed by month number,
 *                                        starting from 0/Jan.
 *     string lang_waitlist_headers       Language string for the text shown at the top of the waitlist modal.
 *     string lang_waitlist_overenrol     Language string for the waitlist overenrol option.
 *     string lang_waitlist_add           Language string for the waitlist add to waitlist option.
 *     string lang_waitlist_skip          Language string for the waitlist skip enrolmennt option.
 *     string lang_general_error          Language string for a general error.
 *     string lang_all_users              Language string for "All Users"
 *
 * @param object options Options object (See Options section above for description)
 */
$.fn.deepsight_action_enroledit = function(options) {
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
        lang_enrolment_date: 'Enrolment Date',
        lang_completion_status: 'Status',
        lang_completion_notcomplete: 'Not Complete',
        lang_completion_passed: 'Passed',
        lang_completion_failed: 'Failed',
        lang_completion_on: 'on',
        lang_completion_time: 'Completion Date',
        lang_grade: 'Grade',
        lang_credits: 'Credits',
        lang_lock: 'Lock',
        lang_locked: 'Locked',
        lang_time_graded: 'Time Graded',
        lang_enroldata: 'Enrolment Data',
        lang_learning_objectives: 'Learning Objectives',
        lang_changes: 'The following changes will be applied to the selected users:',
        lang_no_changes: 'No Changes',
        lang_working: 'Working...',
        lang_yes: 'Yes',
        lang_no: 'No',
        lang_bulk_confirm: 'Please note, performing actions on many users can take some time - Would you like to continue?',
        lang_enrolled: 'Enrolled',
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
        lang_waitlist_headers: '<h1>This course has reached it\'s enrolment limit.</h1>'
            +'<h3>What would you like to do with the following students?</h3>',
        lang_waitlist_overenrol: 'Over-Enrol',
        lang_waitlist_add: 'Add To Waitlist',
        lang_waitlist_skip: 'Skip Enrolment',
        lang_general_error: 'Unknown Error',
        lang_all_users: 'All Users'
    }

    var opts = $.extend({}, this.default_opts, options);
    var main = this;
    this.actiontr = null;
    this.name = opts.name;
    this.form = null;
    this.parent = opts.parent;

    /**
     * Renders the HTML for the action panel.
     * @param object preselecteddata An object containing preselected data. Possible keys are enroltime, completetime, grade,
     *                               credits, completestatus, locked, and association_id
     * @return object A jQuery object representing the rendered action panel.
     */
    this.render_action = function(preselecteddata) {

        var date = new Date();
        var curyear = date.getFullYear();
        var curmonth = date.getMonth();
        var curdate = date.getDate();

        // these selections will be extended by any received preselected data to generate the default values of the input
        // boxes
        var default_selections = {
            association_id: null,
            enroltime: {
                date: curdate,
                month: curmonth,
                year: curyear
            },
            completestatus: 'notcomplete',
            completetime: {
                date: curdate,
                month: curmonth,
                year: curyear
            },
            grade: 0,
            credits: 0,
            locked: 0,
            learningobjectives: []
        };

        // javascript uses months that start at 0, preselected data will use months that start at 1, so convert.
        if (typeof(preselecteddata.enroltime) != 'undefined' && typeof(preselecteddata.enroltime.month) != 'undefined') {
            preselecteddata.enroltime.month--;
        }
        if (typeof(preselecteddata.completetime) != 'undefined' && typeof(preselecteddata.completetime.month) != 'undefined') {
            preselecteddata.completetime.month--;
        }

        var data = $.extend(true, {}, default_selections, preselecteddata);
        var bulkeditui = (opts.mode == 'edit' && opts.parentid == 'bulklist') ? true : false;
        var enroldata = main.render_action_enroldata(data, bulkeditui);
        var learningobjectives = main.render_action_learningobjectives(data);
        var actionpanelactionshtml = '<div class="actions"><i class="elisicon-confirm"></i><i class="elisicon-cancel"></i></div>';
        var actionpanelbody = $('<div class="body"></div>').append(enroldata).append(learningobjectives);

        // render the action panel
        var actionpanel = $('<div><div class="deepsight_actionpanel_inner"></div><div>')
            .addClass(opts.actionclass)
            .addClass('deepsight_action_enrol')
            .css('display', 'none');
        actionpanel.children('.deepsight_actionpanel_inner').append(actionpanelbody).append(actionpanelactionshtml);

        main.enroldataform = actionpanel.find('form.enroldata');
        main.learnobjform = actionpanel.find('form.learnobjs');

        actionpanel.find('i.elisicon-confirm').bind('click', main.precomplete_action);
        actionpanel.find('i.elisicon-cancel').click(main.hide_action);

        return actionpanel;
    }

    /**
     * Renders the enrolment data portion of the action panel.
     *
     * @param object data        An object of preselected data (i.e. the initial state of inputs)
     *                           See the "default_selections" variable in main.render_action for details on structure.
     * @param bool   bulkeditui Whether to show the bulk edit ui. The bulk edit UI places a checkbox to the left of each input
     *                           to enable that input - non-enabled inputs will be ignored when applying values.
     * @return object A jquery object for the enrolment data portion of the action panel.
     */
    this.render_action_enroldata = function(data, bulkeditui) {
        // enrol time
        var htmlenroltime = (bulkeditui == true)
                ? '<input type="checkbox" name="enrolmenttime_enabled" class="enroltime_enabled" />'
                : '';
        htmlenroltime += deepsight_render_date_selectors('start', 'enroltime', data.enroltime, opts.lang_months);

        // completion status
        var htmlcompletestatus = (bulkeditui == true)
                ? '<input type="checkbox" name="completestatusid_enabled" class="completion_status_enabled"/>'
                : '';
        htmlcompletestatus += '<select class="completion_status" name="completestatusid">';
        var completestatuses = ['notcomplete', 'passed', 'failed'];
        for (var i in completestatuses) {
            var selected = (completestatuses[i] == data.completestatus) ? 'selected="selected"' : '';
            htmlcompletestatus += '<option value="'+completestatuses[i]+'" '+selected+'>';
            htmlcompletestatus += opts['lang_completion_'+completestatuses[i]]+'</option>';
        }
        htmlcompletestatus += '</select>';

        // completion time
        var completetimevisible = (data.completestatus == 'notcomplete') ? 'display:none' : '';
        var htmlcompletetime = '<div class="completetime" style="'+completetimevisible+'">';
        if (bulkeditui == true) {
            htmlcompletetime += '<input type="checkbox" name="completetime_enabled" class="completetime_enabled"/>';
        }
        htmlcompletetime += opts.lang_completion_on+' ';
        htmlcompletetime += deepsight_render_date_selectors('end', 'completetime', data.completetime, opts.lang_months);
        htmlcompletetime += '</div>';

        // grade
        var htmlgrade = (bulkeditui == true) ? '<input type="checkbox" name="grade_enabled" class="grade_enabled"/>' : '';
        htmlgrade += '<input class="grade_input" type="text" name="grade" size="8" value="'+data.grade+'"/>';

        // credits
        var htmlcredits = (bulkeditui == true) ? '<input type="checkbox" name="credits_enabled" class="credits_enabled"/>' : '';
        htmlcredits += '<input class="credits_input" type="text" name="credits" size="8" value="'+data.credits+'"/>';

        // locked button
        var htmllockedchecked = (data.locked == 1)
                ? 'class="lockedbutton buttonset checked" checked="checked"'
                : 'class="lockedbutton buttonset"';
        var lockedlabelvisible = (data.locked == 0) ? 'style="display:none"' : '';
        var unlockedlabelvisible = (data.locked == 1) ? 'style="display:none"' : '';
        var date = new Date();
        var lockuniqid = date.getTime();
        var htmllocked = (bulkeditui == true) ? '<input type="checkbox" name="locked_enabled" class="locked_enabled"/>' : '';
        htmllocked += '<input type="checkbox" name="locked" id="enrol_attr_locked_check_'+lockuniqid+'" '+htmllockedchecked+'/>';
        htmllocked += '<label class="off buttonset" for="enrol_attr_locked_check_'+lockuniqid+'" '+unlockedlabelvisible+'>';
        htmllocked += opts.lang_lock+'</label>';
        htmllocked += '<label class="on buttonset" for="enrol_attr_locked_check_'+lockuniqid+'" '+lockedlabelvisible+'>';
        htmllocked += opts.lang_locked+'</label>';

        // assemble enrolment data html
        var html = '<form class="enroldata"><h3>'+opts.lang_enroldata+'</h3>';
        if (data.association_id != null) {
            html += '<input type="hidden" name="association_id" value="'+data.association_id+'" />';
        }
        html += '<input type="hidden" name="bulkedit" value="'+((bulkeditui == true) ? 1 : 0)+'" />';
        html += '<div class="data_wrpr"><div><span>'+opts.lang_enrolment_date+'</span><span>'+opts.lang_completion_status+'</span>';
        html += '<span>'+opts.lang_grade+'</span><span>'+opts.lang_credits+'</span><span>'+opts.lang_locked+'</span></div><div>';
        html += '<span>'+htmlenroltime+'</span>';
        html += '<span>'+htmlcompletestatus+htmlcompletetime+'</span>';
        html += '<span>'+htmlgrade+'</span>';
        html += '<span>'+htmlcredits+'</span>';
        html += '<span>'+htmllocked+'</span>';
        html += '</div></div>'
        html += '</form>';

        var enroldata = $(html);

        // add js actions to elements
        if (bulkeditui == true) {
            enroldata.find('select.enroltime').change(function(e) {
                enroldata.find('input.enroltime_enabled').prop('checked', true);
            });
            enroldata.find('select.completion_status').change(function(e) {
                enroldata.find('input.completion_status_enabled').prop('checked', true);
            });
            enroldata.find('select.completetime').change(function(e) {
                enroldata.find('input.completetime_enabled').prop('checked', true);
            });
            enroldata.find('input.grade_input').change(function(e) {
                enroldata.find('input.grade_enabled').prop('checked', true);
            });
            enroldata.find('input.credits_input').change(function(e) {
                enroldata.find('input.credits_enabled').prop('checked', true);
            });
            enroldata.find('input.lockedbutton').change(function(e) {
                enroldata.find('input.locked_enabled').prop('checked', true);
            });
        }

        enroldata.find('.lockedbutton').click(function(e) {
            enroldata.find('.lockedbutton').toggleClass('checked');
            enroldata.find('.lockedbutton').siblings('label').toggle();
        });

        enroldata.find('select.completion_status').change(function(){
            if ($(this).val() == 'notcomplete') {
                $(this).parents('form').find('.completetime').hide();
            } else {
                $(this).parents('form').find('.completetime').show();
            }
        });

        return enroldata;
    }

    /**
     * Renders the learning objectives portion of the action panel.
     *
     * @param object data An object of preselected data (i.e. the initial state of inputs)
     *                    See the "default_selections" variable in main.render_action for details on structure.
     * @return string An HTML string containing the HTML for the learning objectives portion of the panel.
     */
    this.render_action_learningobjectives = function(data) {
        var html = '';
        if (data.learningobjectives.length > 0) {
            html += '<form class="learnobjs" style="margin-top:30px;display:inline-block;width:100%;">';
            html += '<h3>'+opts.lang_learning_objectives+'</h3>';
            html += '<div class="data_wrpr">'
            html += '<div><span></span><span>'+opts.lang_grade+'</span><span>'+opts.lang_locked+'</span>';
            html += '<span>'+opts.lang_time_graded+'</span></div>';
            for (var i in data.learningobjectives) {
                var curlo = data.learningobjectives[i];

                html += '<div>';
                // label (lo idnumber)
                html += '<span>'+curlo.idnumber+'</span>';

                // grade
                html += '<span>';
                html += '<input type="text" id="lo_'+curlo.objectiveid+'_grade" name="'+curlo.objectiveid+'_grade" size="8" ';
                html += 'value="'+curlo.grade+'"/>';
                html += '</span>';

                // locked
                var htmllockedchecked = (typeof(curlo.locked) != 'undefined' && curlo.locked == '1')
                    ? 'class="lockedbutton checked" checked="checked"' : 'class="lockedbutton"';
                var lockedlabelvisible = (curlo.locked == 0) ? 'style="display:none"' : '';
                var unlockedlabelvisible = (curlo.locked == 1) ? 'style="display:none"' : '';

                var lockcheck = '<input type="checkbox" name="'+curlo.objectiveid+'_locked" id="lo_'+curlo.objectiveid+'_locked" ';
                lockcheck += htmllockedchecked+'/>';

                var lockonlabel = '<label class="off buttonset" for="lo_'+curlo.objectiveid+'_locked" '+unlockedlabelvisible+'>';
                lockonlabel += opts.lang_lock+'</label>';

                var lockofflabel = '<label class="on buttonset" for="lo_'+curlo.objectiveid+'_locked" '+lockedlabelvisible+'>';
                lockofflabel += opts.lang_locked+'</label>';

                html += '<span>'+lockcheck+lockonlabel+lockofflabel+'</span>';

                // time graded
                if (typeof(curlo.date_graded.month) != 'undefined') {
                    curlo.date_graded.month--;
                }
                html += '<span>';
                html += deepsight_render_date_selectors(curlo.objectiveid+'_', '', curlo.date_graded, opts.lang_months);
                html += '</span>';

                html += '</div>';
            }
            html += '</div></form>';
        }

        var learningobjectives = $(html);
        learningobjectives.find('.lockedbutton').each(function() {
            $(this).click(function(e) {
                $(this).toggleClass('checked');
                $(this).siblings('label').toggle();
            });
        });
        return learningobjectives;
    }

    /**
     * Change the parent ID of the action - i.e. change the element we're performing the action for.
     *
     * @param mixed newparentid Normally this would be an int representing the ID of a single element, but could be
     *                           any information you want passed to the actionurl to represent data. for example, this
     *                           is an array when this action is used with the bulk action panel.
     */
    this.update_parentid = function(newparentid) {
        opts.parentid = newparentid;
    }

    /**
     * If the class is at it's enrolment limit, show a modal indicating show, and providing options for overenrolment or
     * waitlisting.
     */
    this.waitlistconfirm = function(data, enroldata) {
        var render_user = function(userid, name) {
            var userhtml = '';
            userhtml += '<li>';
            userhtml += '<input type="hidden" class="elementids" name="elements[]" value="'+userid+'">';
            userhtml += '<span class="studentname">'+name+'</span><span class="buttonset">';
            userhtml += '<input type="radio" value="overenrol" class="action action_enrol" id="student'+userid+'action_enrol" ';
            userhtml += 'name="'+userid+'">';
            userhtml += '<label for="student'+userid+'action_enrol">'+opts.lang_waitlist_overenrol+'</label>';
            userhtml += '<input type="radio" value="waitlist" class="action action_waitlist checked" checked="checked" id="student';
            userhtml += userid+'action_waitlist" name="'+userid+'">'
            userhtml += '<label for="student'+userid+'action_waitlist">'+opts.lang_waitlist_add+'</label>';
            userhtml += '<input type="radio" value="skip" class="action action_skip" id="student'+userid+'action_skip" ';
            userhtml += 'name="'+userid+'">';
            userhtml += '<label for="student'+userid+'action_skip">'+opts.lang_waitlist_skip+'</label>';
            userhtml += '</span></li>';
            return userhtml;
        }

        var html = '<form>'+opts.lang_waitlist_headers+'<ul>';
        if (data.users == 'bulklist') {
            html += '<li class="bulkeditor">';
            html += '<span class="studentname">'+data.total+' Users</span><span class="buttonset">';
            html += '<input type="radio" value="overenrol" class="action action_enrol" id="all_action_enrol" name="bulk_action">';
            html += '<label for="all_action_enrol">'+opts.lang_waitlist_overenrol+'</label>';
            html += '<input type="radio" value="waitlist" class="action action_waitlist checked" checked="checked" ';
            html += 'id="all_action_waitlist" name="bulk_action">';
            html += '<label for="all_action_waitlist">'+opts.lang_waitlist_add+'</label>';
            html += '<input type="radio" value="skip" class="action action_skip" id="all_action_skip" name="bulk_action">';
            html += '<label for="all_action_skip">'+opts.lang_waitlist_skip+'</label>';
            html += '</span></li>';
        } else {
            for (var i in data.users) {
                html += render_user(data.users[i].userid, data.users[i].name);
            }
        }
        html += '</ul><div class="submit"><input type="submit" /></div></form>';

        var modal = opts.datatable.render_modal(html, 'waitlist');
        modal.find('input[type="radio"]').click(function(e) {
            $(this).siblings().removeClass('checked');
            $(this).addClass('checked');
        });
        modal.find('li.bulkeditor').find('input[type="radio"]').click(function() {
            modal.find('li:not(".bulkeditor") input[type="radio"].'+$(this).prop('class')).click();
        });

        modal.find('form').submit(function(e) {
            e.preventDefault();

            // assemble elements param based on bulk or normal mode
            if (data.users == 'bulklist') {
                var elements = 'bulklist';
            } else {
                var elements = [];
                $(this).find('input.elementids').each(function() {
                    elements.push($(this).val());
                });
                elements = JSON.stringify(elements);
            }

            var actions = JSON.stringify($(this).find('input.action').serializeArray());

            modal.html('<h1>'+opts.lang_working+'</h1>').addClass('loading');

            // do actions
            $.ajax({
                type: 'POST',
                url: opts.actionurl,
                data: {
                    sesskey: opts.sesskey,
                    uniqid: opts.datatable.uniqid,
                    waitlistconfirm: 1,
                    actionname: main.name,
                    elements: elements,
                    actions: actions,
                    enroldata: enroldata
                },
                dataType: 'text',
                success: function(data) {
                    modal.removeClass('loading');

                    try {
                        data = ds_parse_safe_json(data);
                    } catch(err) {
                        modal.render_error(errormsg);
                        return false;
                    }

                    if (typeof(data) == 'object' && data != null && typeof(data.result) != 'undefined') {
                        if (data.result == 'success') {
                            modal.remove_modal();
                            if (data.num_affected > 0) {
                                main.trigger('action_complete', {opts:opts});
                            } else {
                                main.trigger('action_cancelled', {opts:opts});
                            }
                            return true;
                        } else {
                            errormsg = (typeof(data.msg) != 'undefined') ? data.msg : opts.lang_general_error;
                            modal.render_error(errormsg);
                        }
                    } else {
                        modal.render_error(opts.lang_general_error);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    modal.removeClass('loading');
                    modal.render_error(textStatus+' :: '+errorThrown);
                }
            });
        });
        return true;
    }

    /**
     * Selectively complete action or provide bulk action warning.
     *
     * This is fired first when the user indicates they want to complete the action. If we are completeing the action for one
     * element, this will proceed directly to main.complete_action. If we are applying the action to the bulk list however, it will
     * first display a confirmation message.
     *
     * @param object e The jquery event object that initialized the completion.
     */
    this.precomplete_action = function(e) {
        var enroldata = main.enroldataform.serializeArray();
        var enroldatacleaned = main.clean_bulk_data(enroldata);
        var enroldatajson = JSON.stringify(enroldata);

        if (opts.parentid == 'bulklist') {
            var origform = main.actiontr.find('.deepsight_actionpanel_inner').clone(true, true);
            main.actiontr.find('.body').html('<span style="display:block">'+opts.lang_changes+'</span>');
            var changeshtml = '<div style="display:inline-block;width: 100%;">'+main.render_changes(enroldatacleaned)+'</div>';
            main.actiontr.find('.body').append(changeshtml);
            main.actiontr.find('.body').append('<span style="display:block">'+opts.lang_bulk_confirm+'</span>');
            main.actiontr.find('i.elisicon-confirm')
                .unbind('click', main.precomplete_action)
                .bind('click', function(e) {
                    main.complete_action(e, enroldatajson);
                });
            main.actiontr.find('i.elisicon-cancel')
                .unbind('click', main.hide_action)
                .bind('click', function(e) {

                    var times = {
                        'enroltime':'start',
                        'completetime':'end'
                    };
                    var time_params = ['day','month','year'];
                    for (var t in times) {
                        for (var p in time_params) {
                            var fullparam = times[t]+time_params[p];
                            if (typeof(enroldatacleaned[fullparam]) != 'undefined') {
                                origform.find('select.'+t+'.'+fullparam).val(enroldatacleaned[fullparam]);
                            }
                        }
                    }

                    if (typeof(enroldatacleaned.completestatusid) != 'undefined') {
                        origform.find('select.completion_status').val(enroldatacleaned.completestatusid);
                    }

                    // re-apply actions
                    origform.find('select.enroltime').change(function(e) {
                        origform.find('input.enroltime_enabled').prop('checked', true);
                    });
                    origform.find('select.completion_status').change(function(e) {
                        origform.find('input.completion_status_enabled').prop('checked', true);
                    });
                    origform.find('select.completetime').change(function(e) {
                        origform.find('input.completetime_enabled').prop('checked', true);
                    });
                    origform.find('input.grade_input').change(function(e) {
                        origform.find('input.grade_enabled').prop('checked', true);
                    });
                    origform.find('input.credits_input').change(function(e) {
                        origform.find('input.credits_enabled').prop('checked', true);
                    });
                    origform.find('input.lockedbutton').change(function(e) {
                        origform.find('input.locked_enabled').prop('checked', true);
                    });

                    origform.find('.lockedbutton').unbind('click').click(function(e) {
                        origform.find('.lockedbutton').toggleClass('checked');
                        origform.find('.lockedbutton').siblings('label').toggle();
                    });

                    main.actiontr.find('.deepsight_actionpanel_inner').replaceWith(origform);
                    main.enroldataform = main.actiontr.find('form.enroldata');
                });
        } else {
            main.complete_action(e, enroldatajson);
        }
    }

    /**
     * Extracts only enabled, changed values from the raw bulk-edit form data
     *
     * @param object enroldata The raw form data, the result of a $.serializeArray() call.
     * @return object Only form values that have an associated _enabled parameter.
     */
    this.clean_bulk_data = function(enroldata) {
        var organized = {};
        for (var i in enroldata) {
            organized[enroldata[i].name] = enroldata[i].value;
        }

        if (opts.mode == 'edit') {
            if (typeof(organized.locked_enabled) != 'undefined' && typeof(organized.locked) == 'undefined') {
                organized.locked = 'off';
            }
        } else {
            if (typeof(organized.locked) == 'undefined') {
                organized.locked = 'off';
            }
        }

        var cleaned = {};
        for (var key in organized) {
            if (opts.mode == 'edit') {
                var enabled_key = key+'_enabled';
                if (typeof(organized[enabled_key]) != 'undefined') {
                    cleaned[key] = organized[key];
                }
            } else {
                cleaned[key] = organized[key];
            }
        }

        // times have to be handled specifically as they have multiple parameters
        if (opts.mode == 'edit' && typeof(organized.enrolmenttime_enabled) != 'undefined') {
            cleaned.startday = organized.startday
            cleaned.startmonth = organized.startmonth
            cleaned.startyear = organized.startyear
        }
        if (opts.mode == 'edit' && typeof(organized.completetime_enabled) != 'undefined') {
            cleaned.endday = organized.endday
            cleaned.endmonth = organized.endmonth
            cleaned.endyear = organized.endyear
        }

        return cleaned;
    }

    /**
     * Render the changes.
     */
    this.render_changes = function(data) {
        var fieldlabels = {
            enrolmenttime: opts.lang_enrolment_date,
            completestatusid: opts.lang_completion_status,
            completetime: opts.lang_completion_time,
            grade: opts.lang_grade,
            credits: opts.lang_credits,
            locked: opts.lang_locked
        };
        var displayorder = ['enrolmenttime','completestatusid','completetime','grade','credits','locked'];

        var changes = {};
        for (var k in data) {
            changes[k] = data[k];
        }

        // format data for display
        if (typeof(changes.startday) != 'undefined' && typeof(changes.startmonth) != 'undefined'
                && typeof(changes.startyear) != 'undefined') {
            var startmonth = (changes.startmonth <= 9)
                ? '0'+(parseInt(changes.startmonth)+1)
                : (parseInt(changes.startmonth)+1);
            changes.enrolmenttime = changes.startyear+'-'+startmonth+'-'+changes.startday;
        }

        if (typeof(changes.endday) != 'undefined' && typeof(changes.endmonth) != 'undefined'
                && typeof(changes.endyear) != 'undefined') {
            var endmonth = (changes.endmonth <= 9)
                ? '0'+(parseInt(changes.endmonth)+1)
                : (parseInt(changes.endmonth)+1);
            changes.completetime = changes.endyear+'-'+endmonth+'-'+changes.endday;
        }

        if (typeof(changes.completestatusid) != 'undefined') {
            changes.completestatusid = (typeof(opts['lang_completion_'+changes.completestatusid]) != 'undefined')
                ? opts['lang_completion_'+changes.completestatusid]
                : changes.completestatusid;
        }

        if (typeof(changes.locked) != 'undefined') {
            changes.locked = (changes.locked == 'on') ? opts.lang_yes : opts.lang_no;
        }

        var changeshtml = '';
        for (var i in displayorder) {
            var key = displayorder[i];
            if (typeof(changes[key]) != 'undefined' && typeof(fieldlabels[key]) != 'undefined') {
                changeshtml += '<li>'+fieldlabels[key]+': '+changes[key]+'</li>';
            }
        }

        if (changeshtml == '') {
            changeshtml = '<li>'+opts.lang_no_changes+'</li>';
        }

        return '<ul class="changes">'+changeshtml+'</ul>';
    }

    /**
     * Completes the action.
     *
     * The user has entered whatever information is required and has click the checkmark.
     *
     * @param object e The jquery event object that initialized the completion.
     * @return bool Success status.
     */
    this.complete_action = function(e, enroldata) {

        var ajaxdata = {
            uniqid: opts.datatable.uniqid,
            sesskey: opts.sesskey,
            elements: opts.parentid,
            actionname: main.name,
            enroldata: enroldata
        }

        if (typeof(main.learnobjform) != 'undefined' && main.learnobjform.length > 0) {
            ajaxdata.learnobjdata = JSON.stringify(main.learnobjform.serializeArray());
        }

        main.actiontr.find('.deepsight_actionpanel').html('<h1>'+opts.lang_working+'</h1>').addClass('loading');
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

                if (opts.mode == 'enrol') {
                    if (typeof(data) == 'object' && data != null && typeof(data.result) != 'undefined') {
                        if (data.result == 'success') {
                            ds_debug('[deepsight_action_enrol.complete_action] Completed action, recevied data:', data);

                            main.trigger('action_complete', {opts:opts});
                            return true;
                        }

                        if (data.result == 'waitlist') {
                            return main.waitlistconfirm(data, enroldata);
                        }
                    }
                } else if (opts.mode == 'edit') {
                    if (opts.parentid != 'bulklist') {
                        if (typeof(data.displaydata) != 'undefined') {
                            main.update_row(opts.parent, data.displaydata);
                        }
                    }
                    ds_debug('[deepsight_action_unenrol.complete_action] Completed action, recevied data:', data);
                    main.trigger('action_complete', {opts:opts});
                    return true;
                }

                var error_message = (typeof(data) == 'object' && data != null && typeof(data.msg) != 'undefined')
                        ? data.msg : opts.lang_general_error;
                opts.datatable.render_error(error_message);
                return true;
            },
            error: function(jqXHR, textStatus, errorThrown) {
                main.hide_action();
                opts.datatable.render_error(textStatus+' :: '+errorThrown);
            }
        });
    }

    /**
     * Updates a row with new information.
     *
     * @param object row The jQuery object for the row (i.e. the <tr>)
     * @param object displaydata The updated display data.
     */
    this.update_row = function(row, displaydata) {
        var fields = {
            enrolmenttime: 'field_enrol_enrolmenttime',
            completetime: 'field_enrol_completetime',
            completestatusid: 'field_enrol_completestatusid',
            grade: 'field_enrol_grade',
            credits: 'field_enrol_credits',
            locked: 'field_enrol_locked'
        }

        for (var k in fields) {
            if (typeof(displaydata[k]) != 'undefined') {
                row.find('.'+fields[k]).html(displaydata[k]);
            }
        }

        row.addClass('confirmed', 500).delay(1000).removeClass('confirmed', 500);
    }

    /**
     * Starts the process to show the action panel.
     *
     * If we're showing the panel for a single element, we make a request to get data about the element, and populate the panel as
     * necessary. For example, we pre-select the existing data, and display learning objectives.
     * This is now performed for bulk edits.
     *
     * @param object e           The jquery event object that initialized the action
     * @param object clickedele The jquery object that was clicked to initialize the action.
     */
    this.show_action = function(e, clickedele) {
        if (opts.parentid == 'bulklist' || opts.mode == 'enrol') {
            main.do_show_action({});
        } else {
            clickedele.addClass('loading');
            $.ajax({
                type: 'POST',
                url: opts.actionurl,
                data: {
                    uniqid: opts.datatable.uniqid,
                    sesskey: opts.sesskey,
                    elements: opts.parentid,
                    actionname: main.name,
                    mode: 'getinfo'
                },
                dataType: 'text',
                success: function(data) {
                    try {
                        data = ds_parse_safe_json(data);
                    } catch(err) {
                        opts.datatable.render_error(err);
                        return false;
                    }

                    if (typeof(data.result) != 'undefined' && data.result == 'success') {
                        clickedele.removeClass('loading');
                        main.do_show_action(data.enroldata);
                    } else {
                        opts.datatable.render_error(data.msg);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    main.hide_action();
                    opts.datatable.render_error(textStatus+' :: '+errorThrown);
                }
            });
        }
    }

    /**
     * Actually Shows the action panel.
     *
     * @param object data If we're performing the action for a single element, this will contain information about the element -
     *                    information to pre-select, learning objective data, etc.
     */
    this.do_show_action = function(data) {
        if (opts.wrapper != null) {
            var rendered = main.render_action(data);
            rendered.wrap(opts.wrapper);
            main.actiontr = rendered.parents().last();
        } else {
            main.actiontr = main.render_action(data);
        }

        main.actiontr.addClass(opts.actiontrclass);
        opts.parent.after(main.actiontr);
        main.actiontr.find('.'+opts.actionclass).slideDown(opts.trans_speed);
        opts.parent.addClass('active');
    }

    /**
     * Hides the action.
     *
     * Fired when the "X" button is clicked in the panel, and when completing the action.
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
     *
     * This is fired when the icon is clicked.
     *
     * @param object e The click event that fired this function.
     */
    this.toggle_action = function(e) {
        if (main.actiontr == null) {
            main.show_action(e, $(this));
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