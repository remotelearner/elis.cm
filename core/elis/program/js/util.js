/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
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

/**
 * Debug output function
 * @param string msg the debug message
 * @param object|array obj the object to output
 */
function cm_var_dump(msg, obj) {
    var out = msg;
    for (var i in obj) {
        out += i+': '+obj[i]+"\n";
    }
    console.log(out); // window.alert(out);
}

/* toggleVisible and toggleVisibleInit are shamelessly borrowed from
 * lib/javascript-static.js from Moodle.  */

function toggleVisible(e, element, showtext, hidetext) {
    if (!element) {
        return false;
    }

    //obtain the program id
	var pos = element.indexOf('-');
	var programid = element.substr(pos + 1);

    //if on the dashboard, handle the complete button toggle
	var completebuttonname;
	if (programid == 'na') {
		completebuttonname = 'noncurriculacompletedbutton';
	} else {
		completebuttonname = 'curriculum' + programid + 'completedbutton';
	}

    //try to fetch the complete button
    var completebuttons = document.getElementsByName(completebuttonname);
    var completebutton = null;
    if (completebuttons.length >= 0) {
        completebutton = completebuttons[0];
    }

    //standard show/hide button
    element = document.getElementById(element);
    var button = e.target ? e.target : e.srcElement;
    // cm_var_dump("toggleVisisble:: button = ", button);
    var regexp = new RegExp(' ?hide');
    if (element.className.match(regexp)) {
	    element.className = element.className.replace(regexp, '');

	    if (completebutton != null) {
            //show the complete toggle button
	        completebutton.className = '';
	    }

        button.setAttribute('value', hidetext);

    } else {
        element.className += ' hide';

        if (completebutton != null) {
            //hide the complete toggle button
            completebutton.className = 'hide';
        }

        button.setAttribute('value', showtext);
    }

    return false;
}

function toggleVisibleInit(addBefore, nameAttr, buttonLabel, hideText, showText, elem) {
    YUI().use("dom", "event", function(Y) {
        var showHideButton = document.createElement("input");
        showHideButton.type = 'button';
        showHideButton.value = buttonLabel;
        showHideButton.name = nameAttr;
        showHideButton.id = 'showhide'+nameAttr;
        if (el = document.getElementById(addBefore)) {
            el.parentNode.insertBefore(showHideButton, el);

            var button = Y.one('#showhide'+nameAttr);
            button.on('click', function(e) {toggleVisible(e, elem, showText, hideText) });
        }
    });
}

// Takes an integer and returns a string representing the integer, padded to a minimum of two characters
var cmPadDigit = function(i) {
	return (i < 10) ? "0" + i : String(i);
}

// Custom formatter for time range column - used specifically by the course catelog when showing
// current classes in a YUI table
//
// Desired format is HH:MM(am/pm) - HH:MM(am/pm) if PM setting is in 12-hour mode, or
// HH:MM - HH::MM if PM setting is in 12-hour mode (or default to provided contant string
// in error / no data case)
var cmFormatTimeRange = function(elCell, oRecord, oColumn, oData) {
    var data = elCell.value;
    if (typeof(data) != "string") {
        // in this case, oData is a six-element array, containing:
        // start hour, start minute, start am/pm string (or empty string if in 24-hour mode),
        // end hour, end minute, end am/pm string (or empty if in 24-hour mode)
        return cmPadDigit(data[0])+":"+cmPadDigit(data[1])+data[2]+" - "+cmPadDigit(data[3])+":"
                +cmPadDigit(data[4])+data[5];
    } else {
        // in this case, oData is a stand-alone constant string, like n/a
        return data;
    }
};

/**
 * Custom sorter for time range column
 * @param object a the first element to compare
 * @param object b the second element to compare
 * @param bool desc true if descending, false otherwise
 * @return int the sort order of 2 passed elements
 */
var cmSortTimeRange = function(a, b, desc) {
    var x = a.get('timeofday');
    var y = b.get('timeofday');
    var shour1 = -1;
    var shour2 = -1;
    if (x != "-" && x != "<center>-</center>") {
        for (var i in x) {
            if (shour1 == -1) {
                shour1 = parseInt(x[i], 10);
                continue;
            }
            if (x[i] == 'pm') {
                if (shour1 < 12.0) {
                    shour1 += 12.0;
                }
                break;
            }
            if (x[i] == 'am') {
                if (shour1 >= 12.0) {
                    shour1 -= 12.0;
                }
                break;
            }
            if (x[i] == '') {
                break;
            }
            shour1 += parseInt(x[i],10)/60.0;
        }
    }
    if (y != "-" && y != "<center>-</center>") {
        for (var j in y) {
            if (shour2 == -1) {
                shour2 = parseInt(y[j], 10);
                continue;
            }
            if (y[j] == 'pm') {
                if (shour2 < 12.0) {
                    shour2 += 12.0;
                }
                break;
            }
            if (y[j] == 'am') {
                if (shour2 >= 12.0) {
                    shour2 -= 12.0;
                }
                break;
            }
            if (y[j] == '') {
                break;
            }
            shour2 += parseInt(y[j],10)/60.0;
        }
    }
    // console.log("shour1 = "+shour1+", shour2 = "+shour2);
    return(desc ? shour1 - shour2 : shour2 - shour1);
};

// Rewrite the name search query string
function changeNamesearch(anchoritem) {
  // Get the search text box
  var namesearch = document.getElementsByName('namesearch');

  // Replace the current query string with the current value in the search box
  // assume that "namesearch" (if it is provided) is not the first parameter
  var url = anchoritem.href;
  url = url.replace(/&namesearch=[^&]*/,'');
  url = url.concat('&namesearch=', namesearch[0].value);

  anchoritem.href = url;
}

/**
 * Custom date formatter for YUI table that understands 0 dates
 * @see yui-datatable
 */
var cmFormatDate = function(elCell, oRecord, oColumn, oData) {
    var date;
    if (typeof(oData) != 'undefined' && (date = new Date(oData))) {
        elCell.innerHTML = oData;
        if (oData.replace(/<\/?[^>]+(>|$)/g, "") == '-') {
            elCell.align = 'center';
        }
    } else {
        elCell.innerHTML = '-';
        elCell.align = 'center';
    }
}
