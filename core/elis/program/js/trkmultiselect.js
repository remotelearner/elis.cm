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

/**
 * Update the listing of available tracks on the create / edis class form based
 * on the provided JSON encoded response text
 * 
 * @param string responseText The text containing a JSON encoded list of tracks
 */
function update_trk_multiselect_from_response(responseText) {
    // Obtain the element that represent the associated tracks
    var id_track = document.getElementById("id_track");

    if(id_track != null) {
        // Clear out previous selection
        id_track.innerHTML = '';

        var tracks = eval(responseText);

        for (var i = 0; i < tracks.length; i++) {
            // Append the appropriate option to the select element
            option = document.createElement('option');
            attr = document.createAttribute('value')
            attr.nodeValue = tracks[i].id;
            option.setAttributeNode(attr);
            option.appendChild(document.createTextNode(tracks[i].name));

            id_track.appendChild(option);
        }
    }
}

function update_trk_multiselect() {
    crsid = document.getElementById("id_courseid").value;

    var print_message = function(o) {
        alert("Unable to update track selection.");
    }

    var set_trk_list = function(o) {
    	update_trk_multiselect_from_response(o.responseText);
    }


    var callback = {
        success:set_trk_list,
        failure:print_message
    }

    if(cache[crsid] != null) {
        update_trk_multiselect_from_response(cache[crsid]);
    } else {
        YAHOO.util.Connect.asyncRequest('GET', 'lib/data/tracklib.php?courseid=' + crsid, callback, null);
    }
}