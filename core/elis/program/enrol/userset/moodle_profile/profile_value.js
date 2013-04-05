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

// because IE doesn't play nice with innerHTML and select elements...
function set_profile_value_control(control, content)
{
    var new_div = document.createElement("div");
    new_div.innerHTML = "<select>" + content + "</select>";
    // clear the control
    while (control.hasChildNodes())
    {
	control.removeChild(control.childNodes[0]);
    }
    // add content to select
    var new_select = new_div.childNodes[0];
    var options = new Array();
    for (i = 0; i < new_select.childNodes.length; i++)
    {
	options.push(new_select.childNodes[i]);
    }
    for (i = 0; i < options.length; i++)
    {
	control.appendChild(options[i]);
    }
}

function update_profile_value_list(fieldIndex, elementName, selectedValue, originallyText)
{
    field = document.getElementById("id_profile_field" + fieldIndex).value;

    //callback success function
    var set_profile_value_list = function(o) {

    	//only populate text fields if the db value is a text field
        if(o.responseText.indexOf('type="text"') != -1 && selectedValue != "" && !originallyText) {
            update_profile_value_list(fieldIndex, elementName, "");
            return;
        }

        var value_div = document.getElementById("cluster_profile_div" + fieldIndex);
        value_div.innerHTML = o.responseText;
    }

    //callback failure function
    var get_profile_value_failure = function(o) {
        alert("Unable to fetch possible profile values");
    }

    var callback =
        {
	    success:set_profile_value_list,
	    failure:get_profile_value_failure
        }

    var requestURL = 'enrol/userset/moodle_profile/profile_value.php?field=' + field + '&elementName=' + elementName;
    if(selectedValue != null) {
	//ignore this part if we are just using the default value
	requestURL += '&value=' + selectedValue;
    }

    YUI().use('yui2-connection', function(Y) {
        var YAHOO = Y.YUI2;
        YAHOO.util.Connect.asyncRequest('GET', requestURL, callback, null);
    });
}
