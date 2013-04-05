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

M.block_curr_admin = {};
var pmYAHOO;
M.block_curr_admin.init_tree = function(Y,object,wwwroot) {
    document.wwwroot = wwwroot;
    Y.use('yui2-treeview', 'yui2-event', 'yui2-json', function(Y) {
        pmYAHOO = Y.YUI2;
        var YAHOO = pmYAHOO;
        YAHOO.util.Event.onDOMReady(function() {
            var tree_object = YAHOO.lang.JSON.parse(object);
            render_curr_admin_tree(tree_object);
        });
    });
};
