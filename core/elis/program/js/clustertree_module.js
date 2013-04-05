/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
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
 * @package    elis
 * @subpackage curriculummanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

M.clustertree = {};
var YAHOO;
M.clustertree.init_tree = function(Y, wwwroot, instanceid, uniqueid, clustertree_object, executionmode, report_id, dropdown_button_text, tree_button_text) {
    document.wwwroot = wwwroot;
    Y.use('yui2-connection', 'yui2-dom', 'yui2-event', 'yui2-json', 'yui2-treeview', function(Y) {
        YAHOO = (typeof pmYAHOO == 'undefined') ? Y.YUI2 : pmYAHOO;
        YAHOO.util.Event.onDOMReady(function() {
            var tree_object = YAHOO.lang.JSON.parse(clustertree_object);
            clustertree_render_tree(instanceid, uniqueid, tree_object, executionmode);
            clustertree_set_toggle_state(report_id,uniqueid,dropdown_button_text, tree_button_text);
        });
    });
};
