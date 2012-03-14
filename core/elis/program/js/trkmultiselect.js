/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2011 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

var cache = [];

function update_trk_multiselect() {
    crsid = document.getElementById("id_courseid").value;

    var print_message = function(o) {
        alert("Unable to update track selection.");
    }

    var set_trk_list = function(o) {
        var id_track = document.getElementById("id_track");

        if(id_track != null) {
            cache[crsid] = o.responseText;
            id_track.innerHTML = o.responseText;
        }
    }


    var callback = {
        success:set_trk_list,
        failure:print_message
    }

    if(cache[crsid] != null) {
        var id_track = document.getElementById("id_track");

        if(id_track != null) {
            id_track.innerHTML = cache[crsid];
        }
    } else {
        YAHOO.util.Connect.asyncRequest('GET', 'lib/data/tracklib.php?courseid=' + crsid, callback, null);
    }
}