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

var selectionstatus = new Array();

YUI().use('yui2-event', function(Y) {
    var YAHOO = Y.YUI2;
    /**
     * When the page is loaded, select the saved selections from #persist_ids_this_page
     */
    YAHOO.util.Event.onDOMReady(function() {
        var sessionselection = document.getElementById('persist_ids_this_page');
        // Load current session data of selected checkboxes
        // console.log('persist_ids_this_page = ' + sessionselection.value);
        if (sessionselection != null) {
            var checkedselection = sessionselection.value.split(',');
            for (var i = 0; i < checkedselection.length; i++) {
                if (checkedselection[i]) {
                    selectionstatus.push(checkedselection[i]);
                }
            }
        }
    });
});

/**
 * Selects all checkboxes for current page.
 */
function do_select_all() {
    var baseurl = document.getElementById('selfurl');
    // Make asynchronous call to self page with flag to select all for current page
    YUI().use("io-base", function(Y) {
        var uri = baseurl.value + "&do_select_all=1";
        var cfg = {
            method: 'POST',
            sync: false
        };
        request = Y.io(uri, cfg);
    });
    checkbox_select(true, '[selected]', 'selected');
    checkbox_select(true, '[changed]', 'changed');
}

/**
 * Deselects all checkboxes for current page.
 */
function do_deselect_all() {
    var baseurl = document.getElementById('baseurl');
    // Make asynchronous call to end point to deselect all for current page
    YUI().use("io-base", function(Y) {
        var uri = baseurl.value + "&action=bulk_checkbox_selection_deselectall";
        var cfg = {
            method: 'POST',
            sync: false
        };
        request = Y.io(uri, cfg);
    });
    checkbox_select(false, '[selected]', 'selected');
}

/**
 * Resets checkbox selections - this is the action for the "reset" button.
 */
function datapersist_do_reset() {
    var baseurl = document.getElementById('baseurl');
    // Make asynchronous call to end point to deselect all for current page
    YUI().use("io-base", function(Y) {
        var uri = baseurl.value + "&action=bulk_checkbox_selection_reset";
        var cfg = {
            method: 'POST',
            sync: true
        };
        request = Y.io(uri, cfg);
    });
    do_unload_update = false;
    window.location.reload();
}

/**
 * To avoid making an ajax call on every checkbox selection, we only make a call when the user leaves the page.
 * This collects selected checkboxes and makes an ajax request to save them when the user leaves the page.
 */
var do_unload_update = true;
window.onbeforeunload = function(e) {
    if (do_unload_update != false) {
        update_checkbox_selection();
    }
}

/**
 * Saves the currently selected checkboxes.
 * Makes an ajax call with the result of build_selection() to save the currently selected checkboxes in the session.
 */
function update_checkbox_selection() {
    var baseurl = document.getElementById('baseurl');
    // Send the selected checkboxes synchronously
    YUI().use('io-base', 'yui2-json', function(Y) {
        var YAHOO = Y.YUI2;
        var uri = baseurl.value + "&action=bulk_checkbox_selection_session";
        var cfg = {
            method: 'POST',
            sync: true,
            data: 'selected_checkboxes=' + YAHOO.lang.JSON.stringify(build_selection())
        };
        request = Y.io(uri, cfg);
    });
}

/**
 * Applies the currently selected values in the bulk-value tool to the currently selected elements.
 */
function do_bulk_value_apply() {
    var enrolmenttime_checked = document.getElementById('blktpl_enrolmenttime_checked').checked;
    var startday = document.getElementById('menublktpl_enrolmenttime_d');
    var startmonth = document.getElementById('menublktpl_enrolmenttime_m');
    var startyear = document.getElementById('menublktpl_enrolmenttime_y');

    var completetime_checked = document.getElementById('blktpl_completetime_checked').checked;
    var endday = document.getElementById('menublktpl_completetime_d');
    var endmonth = document.getElementById('menublktpl_completetime_m');
    var endyear = document.getElementById('menublktpl_completetime_y');

    var completestatusid_checked = document.getElementById('blktpl_status_checked').checked;
    var completestatusid = document.getElementById('menublktpl_status');

    var grade_checked = document.getElementById('blktpl_grade_checked').checked;
    var grade = document.getElementById('blktpl_grade').value;

    var credits_checked = document.getElementById('blktpl_credits_checked').checked;
    var credits = document.getElementById('blktpl_credits').value;

    var locked_checked = document.getElementById('blktpl_locked_checked').checked;
    var locked = document.getElementById('blktpl_locked').checked;

    //make ajax request to apply values to users on all pages
    var json = { id : selectionstatus[i],
        enrolment_date_checked : enrolmenttime_checked,
        enrolment_date :  {     day     : startday.options[startday.selectedIndex].value,
                                month   : startmonth.options[startmonth.selectedIndex].value,
                                year    : startyear.options[startyear.selectedIndex].value
                            },
        completion_date_checked : completetime_checked,
        completion_date :  {    day     : endday.options[endday.selectedIndex].value,
                                month   : endmonth.options[endmonth.selectedIndex].value,
                                year    : endyear.options[endyear.selectedIndex].value
                            },
        status_checked : completestatusid_checked,
        status: completestatusid.selectedIndex,

        grade_checked: grade_checked,
        grade: grade,

        credits_checked: credits_checked,
        credits: credits,

        locked_checked: locked_checked,
        locked: locked
    }

    YUI().use('io-base', 'yui2-json', function(Y) {
        var YAHOO = Y.YUI2;
        var baseurl = document.getElementById('baseurl');
        var uri = baseurl.value + "&action=bulk_apply_all";
        var blktpl = YAHOO.lang.JSON.stringify(json);
        var cfg = {
            method: 'POST',
            sync: true,
            data: 'bulktpl=' + blktpl
        };
        request = Y.io(uri, cfg);
    });

    var changed = false;

    // provide visual feedback for users on current page
    // console.log('updating checked users (' + selectionstatus.length + '): ' + selectionstatus);
    for (var i = 0; i < selectionstatus.length; i++) {
        chbx = document.getElementById('selected' + selectionstatus[i]);
        // console.log('updating checked userid = '+selectionstatus[i]+', checkbox = ' + chbx);
        if (chbx != null) {
            if (chbx.checked == true) {
                changed = false;
                if (enrolmenttime_checked == true) {
                    document.getElementById("menuusers"+selectionstatus[i]+"startday").selectedIndex = startday.selectedIndex;
                    document.getElementById("menuusers"+selectionstatus[i]+"startmonth").selectedIndex = startmonth.selectedIndex;
                    document.getElementById("menuusers"+selectionstatus[i]+"startyear").selectedIndex = startyear.selectedIndex;
                    changed = true;
                }
                if (completetime_checked == true) {
                    document.getElementById("menuusers"+selectionstatus[i]+"endday").selectedIndex = endday.selectedIndex;
                    document.getElementById("menuusers"+selectionstatus[i]+"endmonth").selectedIndex = endmonth.selectedIndex;
                    document.getElementById("menuusers"+selectionstatus[i]+"endyear").selectedIndex = endyear.selectedIndex;
                    changed = true;
                }
                if (completestatusid_checked == true) {
                    document.getElementById("menuusers"+selectionstatus[i]+"completestatusid").selectedIndex = completestatusid.selectedIndex;
                    changed = true;
                }
                if (grade_checked == true) {
                    document.getElementById("grade" + selectionstatus[i]).value = grade;
                    changed = true;
                }
                if (credits_checked == true) {
                    document.getElementById("credits" + selectionstatus[i]).value = credits;
                    changed = true;
                }
                if (locked_checked == true) {
                    document.getElementById("locked" + selectionstatus[i]).checked = locked;
                    changed = true;
                }
                if (changed == true) {
                    var chng = document.getElementById('changed' + selectionstatus[i]);
                    if (chng) {
                       chng.checked = true;
                    }
                }
            }
        }
    }
}

/**
 * Generates an array of the user selected checkboxes and the corresponding data
 */
function build_selection() {
    var selection_record = new Array();
    var json;
    for (var i = 0; i < selectionstatus.length; i++) {
        chbx = document.getElementById('changed' + selectionstatus[i]);
        if (chbx != null) {
            if (chbx.checked == true) {

                // Get the selected data for a given checkbox on the current page
                var selected = false;
                if (document.getElementById("selected" + selectionstatus[i]) != null) {
                    selected = document.getElementById("selected" + selectionstatus[i]);
                    selected = selected.checked;
                }

                var unenrol = '';
                if (document.getElementById("unenrol" + selectionstatus[i]) != null) {
                    unenrol = document.getElementById("unenrol" + selectionstatus[i]);
                    unenrol = unenrol.checked;
                }

                var enrolment_day = document.getElementById("menuusers" + selectionstatus[i] + "startday");
                var enrolment_month = document.getElementById("menuusers" + selectionstatus[i] + "startmonth");
                var enrolment_year = document.getElementById("menuusers" + selectionstatus[i] + "startyear");
                var completion_day = document.getElementById("menuusers" + selectionstatus[i] + "endday");
                var completion_month = document.getElementById("menuusers" + selectionstatus[i] + "endmonth");
                var completion_year = document.getElementById("menuusers" + selectionstatus[i] + "endyear");

                var status = '';
                if (document.getElementById("menuusers" + selectionstatus[i] + "completestatusid") != null) {
                    status = document.getElementById("menuusers" + selectionstatus[i] + "completestatusid");
                    status = status.selectedIndex;
                }
                var grade = '';
                if (document.getElementById("grade" + selectionstatus[i]) != null) {
                    grade = document.getElementById("grade" + selectionstatus[i]);
                    grade = grade.value
                }
                var credits = '';
                if(document.getElementById("credits" + selectionstatus[i]) != null) {
                    credits = document.getElementById("credits" + selectionstatus[i]);
                    credits = credits.value;
                }
                var locked = '';
                if (document.getElementById("locked" + selectionstatus[i]) != null) {
                    locked = document.getElementById("locked" + selectionstatus[i]);
                    locked = locked.checked;
                }

                var associd = '';
                if (document.getElementById("associationid" + selectionstatus[i]) != null) {
                    associd = document.getElementById("associationid" + selectionstatus[i]);
                    associd = associd.value;
                }


                // Build JSON representing a record
                json = { id : selectionstatus[i],
                        changed : true,
                        selected : selected,
                        enrolment_date  :  {   day     : enrolment_day.options[enrolment_day.selectedIndex].value,
                                                month   : enrolment_month.options[enrolment_month.selectedIndex].value,
                                                year    : enrolment_year.options[enrolment_year.selectedIndex].value
                                            },
                        completion_date :  {   day     : completion_day.options[completion_day.selectedIndex].value,
                                                month   : completion_month.options[completion_month.selectedIndex].value,
                                                year    : completion_year.options[completion_year.selectedIndex].value
                                            },
                        unenrol: unenrol,
                        status: status,
                        grade: grade,
                        credits: credits,
                        locked: locked,
                        associd: associd
                    }
            } else {
                json = { id : selectionstatus[i],
                        selected : false}
            }
            YUI().use('yui2-json', function(Y) {
                var YAHOO = Y.YUI2;
                selection_record.push(YAHOO.lang.JSON.stringify(json));
            });
        }
    }

    return selection_record;
}

/**
 * Selects item identified by <id> and does all other required UI changes
 * (ex. updating the "currently selected" indicator)
 * @param  int  id  The ID of the item we want to select.
 */
function select_item(id) {
    var chbx = document.getElementById('selected'+id);
    var unenrol = document.getElementById('unenrol'+id);
    var numselected_e = document.getElementById('numselected_allpages');
    var numselected = parseInt(numselected_e.innerHTML);
    var new_numselected = numselected;
    if (chbx.checked == false && unenrol.checked == false) {
        var ssindex = selectionstatus.indexOf(id);
        if (ssindex != -1) {
            if (numselected > 0) {
                new_numselected = numselected - 1;
            }
        }
    } else {
        proxy_select(id);
        if (selectionstatus.indexOf(id) == -1) {
            new_numselected = numselected + 1;
        }
    }
    // console.log('select_item('+id+'): selectionstatus = '+ selectionstatus);
    numselected_e.innerHTML = new_numselected;
}

/**
 * Selects the item identified by ID
 * @param  int id The ID of the item we want to select.
 */
function proxy_select(id) {
    document.getElementById('changed'+id).checked = true;
    if (selectionstatus.indexOf(id) == -1) {
        selectionstatus.push(id);
    }
}

/**
 * Changes the selected status on sets of elements.
 * @param  bool    checked  The checked status to change to - true=selected, false=not selected.
 * @param  string  type     The suffix of each element's "name" property, used to filter which elements get selected.
 * @param  string  idprefix A prefix to strip off each element when getting the numeric ID of the element.
 */
function checkbox_select(checked, type, idprefix) {
    var table = document.getElementById('selectiontbl');
    if (table) {
        YUI().use('yui2-dom', function(Y) {
            var YAHOO = Y.YUI2;
            YAHOO.util.Dom.getElementsBy(function(el) { return true; }, 'input', table, function(el) {
                if (el.name.starts_with('users') && el.name.ends_with(type)) {
                    if (el.checked != checked) {
                        el.checked = checked;
                        var prefix_len = idprefix.length;
                        id = el.id.substr(prefix_len);
                        select_item(id)
                    }
                }
            });
        });
    }
}

/**
 * Sets all elements' enrolment checkbox state based on the state of #class_enrol_select_all
 */
function class_enrol_set_all_selected() {
    var checkbox = document.getElementById('class_enrol_select_all');

    var input_elements = document.getElementsByTagName('input');
    for (var i = 0; i < input_elements.length; i++) {
        var element = input_elements[i];
        if (element.name.starts_with('users') && element.name.ends_with('[enrol]')) {
            element.checked = checkbox.checked;
        }
    }
}

/**
 * Sets all elements' unenrolment checkbox state based on the state of #class_bulkedit_select_all
 */
function class_bulkedit_set_all_selected() {
    var checkbox = document.getElementById('class_bulkedit_select_all');

    var input_elements = document.getElementsByTagName('input');
    for (var i = 0; i < input_elements.length; i++) {
        var element = input_elements[i];
        if (element.name.starts_with('users') && element.name.ends_with('[unenrol]')) {
            element.checked = checkbox.checked;
        }
    }
}
