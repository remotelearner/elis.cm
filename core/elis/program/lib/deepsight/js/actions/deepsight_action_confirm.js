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
 * DeepSight Confirm Action Panel
 * Adds an general confirmation action panel that can be used to ensure the user actually wants to perform the action.
 *
 * Usage:
 *     $('[button selector]').each(function() { $(this).deepsight_action_confirm(); });
 *
 * Required Options:
 *     object rowdata           An object of information for the associated row.
 *     object parent            A jquery element after which the panel will be added.
 *     string sesskey           The Moodle sesskey (sent with requests to prevent CSRF attacks)
 *     mixed  parentid          When completing the action, this ID will be passed to the actionurl to identify for which element the
 *                              action was completed. Can also be set to "bulklist" to apply the action to the entire bulklist.
 *     object datatable         The datatable object this action is used for.
 *     string actionurl         The URL to call when completing the action.
 *
 * Optional Options:
 *     string wrapper           A html string for a wrapper that will be placed around the action panel.
 *     string actionclass       A CSS class to attach to the action div.
 *     string actiontrclass     A Css class to attach the action's parent.
       string lang_bulk_confirm The confirmation message to display when applying the action to the bulklist.
 *     int    trans_speed       The number of miliseconds to perform animations in.
 *
 * @param object options Options object (See Options section above for description)
 */
$.fn.deepsight_action_confirm = function(options) {
    this.default_opts = {
        rowdata: null,
        parent: null,
        sesskey: null,
        parentid: null,
        datatable: null,
        actionurl: null,
        desc_single: 'Are you sure?',
        desc_multiple: 'Are you sure?',
        wrapper: '<div></div>',
        actionclass: 'deepsight_actionpanel',
        actiontrclass: 'deepsight_actionpanel_tr',
        lang_bulk_confirm: 'Performing actions on many users can take some time - Are you sure?',
        lang_working: 'Working...',
        trans_speed: 100
    }

    var opts = $.extend({}, this.default_opts, options);
    var main = this;
    this.name = opts.name;
    this.actiontr = null;
    this.parent = opts.parent;

    /**
     * Renders the HTML for the action panel.
     */
    this.render_action = function() {
        var desc = (opts.parentid == 'bulklist') ? opts.desc_multiple : opts.desc_single;
        var actionpanel = $('<div><div>').addClass(opts.actionclass).addClass('deepsight_action_confirm').css('display', 'none');
        var actionpanelbody = '<div class="body">'+desc+'</div>\n\
                                <div class="actions"><i class="elisicon-confirm"></i><i class="elisicon-cancel"></i></div>';

        actionpanel.html('<div class="deepsight_actionpanel_inner">'+actionpanelbody+'</div>');
        actionpanel.find('i.elisicon-confirm').bind('click', main.precomplete_action);
        actionpanel.find('i.elisicon-cancel').click(main.hide_action);
        return actionpanel;
    }

    /**
     * Change the parent ID of the action.
     *
     * Change the element we're performing the action for.
     *
     * @param mixed new_parentid Normally this would be an int representing the ID of a single element, but could be any information
     *                           you want passed to the actionurl to represent data. for example, this is an array when this action
     *                           is used with the bulk action panel.
     */
    this.update_parentid = function(new_parentid) {
        opts.parentid = new_parentid;
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
        if (opts.parentid == 'bulklist' && typeof(bulkconfirmed) == 'undefined') {
            main.actiontr.find('.body').html(opts.lang_bulk_confirm);
            main.actiontr.find('i.elisicon-confirm')
                .unbind('click', main.precomplete_action)
                .bind('click', main.complete_action);
        } else {
            main.complete_action(e);
        }
    }

    /**
     * Completes the action.
     *
     * The user has entered whatever information is required and has click the checkmark.
     *
     * @param object e The jquery event object that initialized the completion.
     */
    this.complete_action = function(e) {
        main.actiontr.find('.deepsight_actionpanel').html('<h1>'+opts.lang_working+'</h1>').addClass('loading');
        main.trigger('action_started');

        $.ajax({
            type: 'POST',
            url: opts.actionurl,
            data: {
                uniqid: opts.datatable.uniqid,
                sesskey: opts.sesskey,
                elements: opts.parentid,
                actionname: main.name,
            },
            dataType: 'text',
            success: function(data) {
                try {
                    data = ds_parse_safe_json(data);
                } catch(err) {
                    opts.datatable.render_error(err);
                    return false;
                }

                main.hide_action();

                if (typeof(data.result) != 'undefined' && data.result == 'success') {
                    ds_debug('[deepsight_action_confirm.complete_action] Completed action, recevied data:', data);
                    if (opts.parentid != 'bulklist') {
                        opts.parent.addClass('confirmed').delay(1000).fadeOut(250, function() {
                            opts.datatable.removefromtable(main.name, opts.parent.data('id'));
                        });
                    }
                    main.trigger('action_complete', {opts:opts});
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

    /**
     * Shows the action panel
     */
    this.show_action = function() {
        if (opts.wrapper != null) {
            var rendered = main.render_action();
            rendered.wrap(opts.wrapper);
            main.actiontr = rendered.parents().last();
        } else {
            main.actiontr = main.render_action();
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
            main.show_action();
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