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
 * @subpackage curriculummanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

function get_element_by_name(name) {
    return YAHOO.util.Dom.getElementsBy(function(el) { return el.getAttribute("name") == name; })[0]
}


function make_links_internal() {
    YAHOO.util.Dom.getElementsBy(function(el) { return true; },
				 'a', 'list_display',
				 function(el) {
				     YAHOO.util.Event.addListener(el, "click", load_link, el.getAttribute('href'));
				 });
    YAHOO.util.Dom.getElementsBy(function(el) { return true; },
				 'form', 'list_display',
				 function(el) {
				     YAHOO.util.Event.addListener(el, "submit", load_form, el.id);
				 });
}

YAHOO.util.Event.onDOMReady(make_links_internal);

function set_content(resp) {
    div = document.createElement('div');
    div.id = 'list_display';
    div.innerHTML = resp.responseText;
    olddiv = document.getElementById('list_display');
    olddiv.parentNode.replaceChild(div, olddiv);
    make_links_internal();
    mark_selected();
}

set_content_callback = {
    success: set_content
};

var lastrequest = 'index.php?s=bulkuser';

function load_link(ev, link) {
    lastrequest = link;
    YAHOO.util.Connect.asyncRequest("GET", link + "&action=getlist&mode=bare", set_content_callback, null);
    YAHOO.util.Event.preventDefault(ev);
}

function load_form(ev, formid) {
    data = YAHOO.util.Connect.setForm(formid);

    link = document.getElementById(formid).getAttribute('action');
    lastrequest = link + '?' + data;
    YAHOO.util.Connect.asyncRequest("POST", link + "?action=getlist&mode=bare", set_content_callback, null);
    YAHOO.util.Event.preventDefault(ev);
}

function change_selected_display() {
    selected_only = get_element_by_name("selectedonly");
    if (selected_only.checked) {
	YAHOO.util.Connect.asyncRequest("GET", "index.php?s=bulkuser&action=listselected&mode=bare&users="+selected_users_field.value, set_content_callback, null);
    } else {
	YAHOO.util.Connect.asyncRequest("GET", lastrequest + "&action=getlist&mode=bare", set_content_callback, null);
    }
}


var selected_users_field = null;

YAHOO.util.Event.onDOMReady(function() {
    selected_users_field = get_element_by_name("selectedusers");
    selected_users_field.value = '';
});

function select_user(id) {
    value = selected_users_field.value;
    if (get_element_by_name("select"+id).checked) {
	if (value.length) {
	    value += "," + id;
	} else {
	    value = "" + id;
	}
    } else {
	value = value.replace(new RegExp('^'+id+'($|,)|,'+id+'(?=$|,)','g'),'');
    }
    selected_users_field.value = value;
    document.getElementById("numselected").innerHTML = value.length ? value.split(',').length : '0';
}

function mark_selected() {
    table = YAHOO.util.Dom.getElementsBy(function(el) { return true; }, 'table', 'list_display')[0];
    if (table) {
	YAHOO.util.Dom.getElementsBy(function(el) { return true; },
				     'input', table,
				     function(el) {
					 id = el.name.substr(6);
					 el.checked = (selected_users_field.value.search(new RegExp('(^|,)'+id+'($|,)')) != -1);
				     });
    }
}

YAHOO.util.Event.onDOMReady(mark_selected);

function select_all() {
    table = YAHOO.util.Dom.getElementsBy(function(el) { return true; }, 'table', 'list_display')[0];
    if (table) {
	YAHOO.util.Dom.getElementsBy(function(el) { return true; },
				     'input', table,
				     function(el) {
					 el.checked = true;
					 id = el.name.substr(6);
					 select_user(id);
				     });
    }
    button = get_element_by_name('selectall');
    button.checked = false;
}
