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
 * DeepSight Link Action
 * Adds a simple link to a different page, optionally with link URL affected by row data.
 *
 * Usage:
 *     $('[button selector]').each(function() { $(this).deepsight_action_link(); });
 *
 * Required Options:
 *     object rowdata           An object of information for the associated row.
 *     object parent            A jquery element after which the panel will be added.
 *     string sesskey           The Moodle sesskey (sent with requests to prevent CSRF attacks)
 *     mixed  parentid          When completing the action, this ID will be passed to the actionurl to identify for which element the
 *                              action was completed. Can also be set to "bulklist" to apply the action to the entire bulklist.
 *     object datatable         The datatable object this action is used for.
 *     string linkwwwroot       The wwwroot of the site, from $CFG->wwwroot
 *     string linkbaseurl       The base URL of the page we want, without query string or wwwroot.
 *     object linkparams        An object of parameters for the link. This is formatted like key: value.
 *                              To add data from the row the link will appear on, set value to a key from opts.rowdata, surrounded
 *                              by {curly braces}.

 * @param object options Options object (See Options section above for description)
 */
$.fn.deepsight_action_link = function(options) {
    this.default_opts = {
        rowdata: null,
        parent: null,
        sesskey: null,
        parentid: null,
        datatable: null,
        linkwwwroot: '',
        linkbaseurl: '',
        linkparams: {}
    }

    var opts = $.extend({}, this.default_opts, options);
    var main = this;
    this.name = opts.name;
    this.actiontr = null;
    this.parent = opts.parent;

    /**
     * Construct the full URL.
     */
    this.make_link_url = function() {
        var url = opts.linkwwwroot+opts.linkbaseurl;
        var querystring = '';

        for (var i in opts.linkparams) {
            querystring += (querystring.length > 0) ? '&amp;' : '';
            if (opts.rowdata != null && opts.linkparams[i].indexOf('{') == 0
                    && opts.linkparams[i].indexOf('}') == (opts.linkparams[i].length-1) ) {
                var rowdatakey = opts.linkparams[i].substring(1, (opts.linkparams[i].length-1));
                if (typeof(opts.rowdata[rowdatakey]) != 'undefined') {
                    querystring += i+'='+opts.rowdata[rowdatakey];
                } else {
                    querystring += i+'='+opts.linkparams[i];
                }
            } else {
                querystring += i+'='+opts.linkparams[i];
            }
        }

        return url+((url.indexOf('?') >= 0) ? '&amp;' : '?')+querystring;
    }

    /**
     * Set up action.
     */
    this.initialize = function() {
        var url = main.make_link_url();
        var actionicon = $('<a title="'+opts.label+'" class="deepsight_action_'+opts.type+' '+opts.icon+'" href="'+url+'"></a>');
        actionicon.fancy_tooltip();
        main.append(actionicon);
    }

    this.initialize();
    return this;
}

})(jQuery);