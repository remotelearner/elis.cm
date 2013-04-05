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

String.prototype.starts_with = function (str) {
	return this.indexOf(str) === 0;
}

String.prototype.ends_with = function (str) {
	return this.indexOf(str) === this.length - str.length;
}

YUI().use('yui2-dom', 'yui2-event', function(Y) {
    var YAHOO = Y.YUI2;
    YAHOO.util.Event.onDOMReady(function() {
        var sessionselection = document.getElementById('selected_checkboxes');
        // Load current session data of selected checkboxes
        if (sessionselection != null) {
            var checkedselection = sessionselection.value.split(',');
            for (var i = 0; i < checkedselection.length; i++) {
                if (checkedselection[i]) {
                    selection.push(checkedselection[i]);
                }
            }
        }
    });
});

window.onbeforeunload = function(e) {
    update_checkbox_selection();
}

function update_checkbox_selection() {
    var baseurl = document.getElementById('baseurl');
    // Send the selected checkboxes synchronously
    YUI().use("io-base", "yui2-json", function(Y) {
        var YAHOO = Y.YUI2;
        var uri = baseurl.value + "&action=checkbox_selection_session";
        var cfg = {
            method: 'POST',
            sync: true,
            data: 'selected_checkboxes=' + YAHOO.lang.JSON.stringify(build_selection())
        };
        request = Y.io(uri, cfg);
    });
}

// Generate an array of the user selected checkboxes and the corresponding data
function build_selection() {
    var selection_record = new Array();
    var json;
    for(var i = 0; i < selection.length; i++) {
        // If the checkbox is not on the current page, store only its id
        if(document.getElementById('checkbox' + selection[i]) == null) {
            json = { id :  selection[i], bare : true }
        } else {
            // Get the selected data for a given checkbox on the current page
            var enrolment_day = document.getElementById("menuusers" + selection[i] + "startday");
            var enrolment_month = document.getElementById("menuusers" + selection[i] + "startmonth");
            var enrolment_year = document.getElementById("menuusers" + selection[i] + "startyear");
            var completion_day = document.getElementById("menuusers" + selection[i] + "endday");
            var completion_month = document.getElementById("menuusers" + selection[i] + "endmonth");
            var completion_year = document.getElementById("menuusers" + selection[i] + "endyear");

            var status = '';
            if (document.getElementById("menuusers" + selection[i] + "completestatusid") != null) {
                status = document.getElementById("menuusers" + selection[i] + "completestatusid");
                status = status.selectedIndex;
            }
            var grade = '';
            if (document.getElementById("grade" + selection[i]) != null) {
                grade = document.getElementById("grade" + selection[i]);
                grade = grade.value
            }
            var credits = '';
            if(document.getElementById("credits" + selection[i]) != null) {
                credits = document.getElementById("credits" + selection[i]);
                credits = credits.value;
            }
            var locked = '';
            if (document.getElementById("locked" + selection[i]) != null) {
                locked = document.getElementById("locked" + selection[i]);
                locked = locked.checked;
            }

            // Build JSON representing a record
            json = { id : selection[i],
                     enrolment_date  :  {   day     : enrolment_day.options[enrolment_day.selectedIndex].value,
                                            month   : enrolment_month.options[enrolment_month.selectedIndex].value,
                                            year    : enrolment_year.options[enrolment_year.selectedIndex].value
                                        },
                     completion_date :  {   day     : completion_day.options[completion_day.selectedIndex].value,
                                            month   : completion_month.options[completion_month.selectedIndex].value,
                                            year    : completion_year.options[completion_year.selectedIndex].value
                                        },
                     status: status,
                     grade: grade,
                     credits: credits,
                     locked: locked
                  }

        }
        YUI().use('yui2-json', function(Y) {
            var YAHOO = Y.YUI2;
            selection_record.push(YAHOO.lang.JSON.stringify(json));
        });
    }

    return selection_record;
}

var selection =  new Array();

function select_item(id) {
    if (document.getElementById("checkbox"+id).checked) {
        // Add the selection
        if (checkbox_selection_index(id) == -1) {
            selection.push(id);
        }
    } else {
        // Remove the selection
        var pos = checkbox_selection_index(id);
        if (pos != -1) {
            selection.splice(pos, 1);
        }
    }
}

function checkbox_selection_index(element) {
    for (var i = 0; i < selection.length; i++) {
        if (selection[i] == element) {
            return i;
        }
    }
    return -1;
}

function checkbox_select(checked, type) {
    var table = document.getElementById('selectiontbl');
    if (table) {
        YUI().use('yui2-dom', function(Y) {
            var YAHOO = Y.YUI2;
            YAHOO.util.Dom.getElementsBy(function(el) { return true; }, 'input', table, function(el) {
                if (el.name.starts_with('users') && el.name.ends_with(type)) {
                    el.checked = checked;
                    id = el.id.substr(8);
                    select_item(id);
                }
            });
        });
    }
}

function class_enrol_set_all_selected() {
	var checkbox = document.getElementById('class_enrol_select_all');

	var input_elements = document.getElementsByTagName('input');
	for(var i = 0; i < input_elements.length; i++) {
		var element = input_elements[i];
		if(element.name.starts_with('users') && element.name.ends_with('[enrol]')) {
			element.checked = checkbox.checked;
		}
	}
}

function class_bulkedit_set_all_selected() {
  var checkbox = document.getElementById('class_bulkedit_select_all');

  var input_elements = document.getElementsByTagName('input');
  for(var i = 0; i < input_elements.length; i++) {
    var element = input_elements[i];
    if(element.name.starts_with('users') && element.name.ends_with('[unenrol]')) {
      element.checked = checkbox.checked;
    }
  }
}

function class_confirm_unlink(selectedelement,message) {
  if(selectedelement.checked == true){
	  return confirm(message);
  } else {
	  return true;
  }
}
