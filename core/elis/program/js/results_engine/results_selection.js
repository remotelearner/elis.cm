/**
 * Generic JavaScript methods for a results selection relaged page(s).
 *
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2010 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2008-2010 Remote Learner.net Inc http://www.remote-learner.net
 *
 */


/**
 * This functions opens a new popup window
 */
function show_panel( url ) {
    var x = window.open(url, 'newWindow', 'height=500,width=500,resizable,scrollbars');
}

/**
 * This function updates fields on the results engine results form/table
 * The first parameter is only part of the element id (the suffix).
 * 
 * @param elmid - unique id of element that is to be updated
 * @param label - name of track
 * @param id - track id
 */
function add_selection(elmid, label, id) {

    var elementid = elmid + 'label';
    var element = window.opener.document.getElementById(elementid);
    element.value = label;

    elementid = elmid + 'selected';
    element = window.opener.document.getElementById(elementid);
    element.value = id;

    window.close();
}

/**
 * Update the preview field
 * 
 * This method rebuilds the cache and posts it to the preview field.  It should be called
 * directly from the form elements.
 */
function replace_content(page, frameid, fieldid, fieldname) {
	var frame = document.getElementById(frameid);
	var url   = page +'?id='+ fieldid +'&name='+ fieldname;
	
	if (frame == null) {
		return false;
	}
	if (fieldid == '') {
		frame.innerHTML = '&nbsp;';
		return true;
	}
	
	var xmlhttp = new XMLHttpRequest();
	xmlhttp.onreadystatechange = function() {
		if ((xmlhttp.readyState == 4) && (xmlhttp.status == 200)) {
			frame.innerHTML = xmlhttp.responseText;
		}
	}
	
	xmlhttp.open('GET', url, true);
	xmlhttp.send();
}


function delete_row(id, cache, type) {
	var action = document.getElementsByName("action")[0];
	var aid    = document.getElementsByName("aid")[0];
	var max    = 99;
	var min1   = null;
	var max1   = null;
	var sel1   = null;
	var min2   = null;
	var max2   = null;
	var sel2   = null;
	var i      = 0;

	if (cache) {
		action.value = 'cachedelete';
		
        min1 = document.getElementsByName(type + '_add_' + id + '_min')[0];
        max1 = document.getElementsByName(type + '_add_' + id + '_max')[0];
        sel1 = document.getElementsByName(type + '_add_' + id + '_selected')[0];		
		
	    for (i=id; i < max; i++) {

	        min2 = document.getElementsByName(type + '_add_' + (i+1) + '_min')[0];
	        max2 = document.getElementsByName(type + '_add_' + (i+1) + '_max')[0];
	        sel2 = document.getElementsByName(type + '_add_' + (i+1) + '_selected')[0];
	        
	        if ( (typeof min2 == 'undefined') ||
                 (typeof max2 == 'undefined') ||
                 (typeof sel2 == 'undefined') ) {
	        	min1.value = '';
	        	max1.value = '';
	        	sel1.value = '';
	        	break;
	        }
	        min1.value = min2.value;
	        max1.value = max2.value;
	        sel1.value = sel2.value;
	        
	        min1 = min2;
	        max1 = max2;
	        sel1 = sel2;
	    }
		
	} else {
		action.value = "delete";
	}
	aid.value    = id;
	pre_submit_processing(type);
	aid.form.submit();
	return false;
}

function pre_submit_processing(type) {
    var max   = 99;
    var i     = 0;
    var value = 0;
    var ele_min = '';
    var ele_max = '';
    var ele_sel = '';
    var ele_val = '';
    var temp = '';
    var cache = document.getElementsByName("actioncache")[0]; 

    if ( (typeof cache == 'undefined') ) {
        return 0;
    }
       
    for (i=0; i < max; i++) {

        ele_min = document.getElementsByName(type + '_add_' + i + '_min')[0];
        ele_max = document.getElementsByName(type + '_add_' + i + '_max')[0];
        ele_sel = document.getElementsByName(type + '_add_' + i + '_selected')[0];
        ele_val = document.getElementsByName(type + '_add_' + i + '_value')[0];
        
        if ( (typeof ele_min == 'undefined') ||
             (typeof ele_max == 'undefined') ||
             (typeof ele_sel == 'undefined') ) {
            // We've gone too far time to exit
            i = max;
            continue;
        }

        if ( ('' != ele_min.value) ||
             ('' != ele_max.value) ||
             (('' != ele_sel.value) && (0 != ele_sel.value)) ) {
            // We need to cache incomplete rows too because of user profiles and configuration.
            value = 0;
            
            if (typeof ele_val != 'undefined') {
                value = ele_val.value;
            }
            temp = temp + ele_min.value + ',' + ele_max.value + ',' + ele_sel.value + ',' + value + ',';
        }
    }

    // Remove the last comma
    
    var last_occurance = temp.lastIndexOf(',');
    last_occurance = parseInt(last_occurance);
    
    cache.value = temp.slice(0, last_occurance);
}

//Toggle form disabled status.  Disabled if checkbox is not checked
function toggleform(checkbox) {
    var state = true;
    var form  = checkbox.form;

    if (checkbox.checked) {
        state = '';
    }

    for(var i=0; i<form.length;i+=1) {
        element = form.elements[i];
        if ((element.type != "hidden") && (element.type != "fieldset")
         && (element.name != "active")
         && (element.id != "id_submitbutton") && (element.id != "id_cancel")) {
            element.disabled = state;
        }
    }
}
