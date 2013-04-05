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

function saveLearningPlanVisibilityState(e, data) {
    var userprefparam = 'crlm_learningplan_collapsed_curricula';
    var hiddenfield = 'collapsed';
    var dataPart = data.split('-');
    var item = dataPart[1];
    var itemset = document.getElementById(hiddenfield).value.split(',');
    var newitemset = new Array();
    var newitems = '';
    var itemfound = 0;

    // check for existence of item in current collapsed data
    for ( i=0; i < itemset.length; i++ )
    {
        if (item == itemset[i])
        {
            itemfound = 1;
        }
        else
        {
            newitemset.push(itemset[i]);
        }
    }

    // if item was not found then let's add it
    if (itemfound == 0)
    {
        newitemset.push(item);
    }

    // convert the collapsed data array into a comma separated string
    for ( i=0; i < newitemset.length; i++ )
    {
        if (newitemset[i] != '')
        {
            newitems = newitems + newitemset[i] + ',';
        }
    }

    // remove trailing comma
    newitems = newitems.slice(0,-1);

    // save a local reference copy of the currently collapsed items
    document.getElementById('collapsed').value = newitems;

    // save the currently collapsed items to user preferences table
    var callback = {
        success: false,
        failure: false,
        cache: false
    };
    var requestURL = M.cfg.wwwroot + '/elis/program/userprefset.php?param=' + userprefparam + '&value=' + newitems;
    YUI().use('yui2-connection', function(Y) {
        var YAHOO = Y.YUI2;
        YAHOO.util.Connect.asyncRequest('GET', requestURL, callback, null);
    });

    return false;
}

/**
 * Ajax callback for toggling the display of completed courses
 * 
 * @param object o the data provided back from the Ajax request
 */
function toggleCompletedCoursesCallback(o) {
    //obtain the container div
    div = document.getElementById('curriculum-'+o.argument.programid);
    //fill in with HTML content from the PHP script
    div.innerHTML = o.responseText;
}

/**
 * Method that toggles the state of whether completed courses are being displayed
 * (called when the appropriate button is clicked)
 *
 * @param object e the event that triggered this method call
 * @param string data curriculum-#, where # is either the program id or na
 */
function toggleCompletedCourses(e, data) {
    //find the list of enabled programs via the hidden field
    var hiddenfield = 'displayedcompleted';
    var hiddenelement = document.getElementById(hiddenfield);

    //convert hidden value to an array of program ids
    if (hiddenelement.value == '') {
	    var enabled = Array();
    } else { 
        var enabled = hiddenelement.value.split(',');
    }

    //determine which program we're currently toggling
    var programid = data.split('-')[1];

    //determine whether that program has already been selected
    var position = -1;
    for (i = 0; i < enabled.length; i++) {
        if (enabled[i] == programid) {
            //already selected
            position = i;
            break;
        }
    }

    //obtain the button element so we can change its text
    var button = e.target ? e.target : e.srcElement;

    //parameter to pass to PHP script
    var showcompleted;

    if (position > -1) {
    	//already selected, so current action is hide
    	enabled.splice(position, 1);
        showcompleted = 0;
    	//next action for this program will be a "show"
        button.value = button.moodle.showLabel;
    } else {
        //not already selected, so current action is show
        enabled[enabled.length] = programid;
        showcompleted = 1;
        //next action for this program will be a "hide"
        button.value = button.moodle.hideLabel;
    }

    //update hidden element with new state
    hiddenelement.value = enabled.join(',');

    //path to our PHP script
    var requestURL = M.cfg.wwwroot + '/elis/program/togglecompletedcourses.php?programid='+
                     programid + '&showcompleted=' + showcompleted;

    //Ajax callback object
    callback = {
        success: toggleCompletedCoursesCallback,
        failure: false,
        //pass the program id along
        argument: {programid: programid}
    }

    // make the Ajax request
    YUI().use('yui2-connection', function(Y) {
        var YAHOO = Y.YUI2;
        YAHOO.util.Connect.asyncRequest('GET', requestURL, callback, programid);
    });
}

/**
 * Toggle the display of completed courses via the appropriate link
 *
 * @param mixed programid the id of the program being toggled, or false for the
 *              non-program section
 */
function toggleCompletedCoursesViaLink(programid) {
    //name of the button that could be used to toggle
    var buttonname;
    //data needed by toggleCompletedCourses
    var data;

    if (programid == false) {
        //non-program section
        buttonname = 'noncurriculacompletedbutton';
        data = 'curriculum-na';
    } else {
        //specific program
        buttonname = 'curriculum' + programid + 'completedbutton';
        data = 'curriculum-' + programid;
    }

    //set an object similar to a normal event to fake out toggleCompletedCoruses
    var e = new Object();
    e.target = document.getElementsByName(buttonname)[0];

    //call the toggle-via-button method
    toggleCompletedCourses(e, data);
}

/**
 * Set up a button for a particular program, or non-program courses, that toggles
 * whether completed courses are displayed
 *
 * @param string addBefore the id value of the element we are adding the button
 *                         before
 * @param string nameAttr a name value to give the toggle button
 * @param string buttonLabel the label to display on the toggle button, in its
 *               initial state
 * @param string hideText the label to display on the toggle button when clicking
 *                        on it would hide completed courses
 * @param string showText the label to display on the toggle button when clicking
 *                        on it would show completed courses
 * @param string element the id of the container we are displaying information in
 * @param boolean displayed true if we are showing the button at all, otherwise false
 * @param boolean enabled if true, button is clickable, otherwise it's greyed out
 */
function toggleCompletedInit(addBefore, nameAttr, buttonLabel, hideText, showText, element, displayed, enabled) {
	//create the element
    var showHideCompletedButton = document.createElement("input");

    //set its main attributes 
    showHideCompletedButton.type = 'button';
    showHideCompletedButton.value = buttonLabel;
    showHideCompletedButton.name = nameAttr;
    //make IE7 happy
    showHideCompletedButton.id = nameAttr;

    if (displayed) {
        //allow for the button to be displayed
        showHideCompletedButton.className = '';
    } else {
        //hide the button via css
        showHideCompletedButton.className = 'hide';
    }
    if (!enabled) {
        //set the disabled attribute to grey it out
        showHideCompletedButton.disabled = 'disabled';
    }

    // pass along extra data for use in event handler
    showHideCompletedButton.moodle = {
        hideLabel: hideText,
        showLabel: showText
    };

    // set up the click handler
    YUI().use('yui2-event', function(Y) {
        var YAHOO = Y.YUI2;
        YAHOO.util.Event.addListener(showHideCompletedButton, 'click', toggleCompletedCourses, element);
    });

    // add the button to the DOM
    el = document.getElementById(addBefore);
    el.parentNode.insertBefore(showHideCompletedButton, el);
}

function toggleVisibleInitWithState(addBefore, nameAttr, buttonLabel, hideText, showText, element) {
    var showHideButton = document.createElement("input");
    showHideButton.type = 'button';
    showHideButton.value = buttonLabel;
    showHideButton.name = nameAttr;
    showHideButton.moodle = {
        hideLabel: hideText,
        showLabel: showText
    };
    YUI().use('yui2-event', function(Y) {
        var YAHOO = Y.YUI2;
        YAHOO.util.Event.addListener(showHideButton, 'click', toggleVisible, element);
        YAHOO.util.Event.addListener(showHideButton, 'click', saveLearningPlanVisibilityState, element);
    });
    el = document.getElementById(addBefore);
    el.parentNode.insertBefore(showHideButton, el);
}

