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
    var requestURL = '../curriculum/userprefset.php?param=' + userprefparam + '&value=' + newitems;
    YAHOO.util.Connect.asyncRequest('GET', requestURL, callback, null);

    return false;
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
    YAHOO.util.Event.addListener(showHideButton, 'click', toggleVisible, element);
    YAHOO.util.Event.addListener(showHideButton, 'click', saveLearningPlanVisibilityState, element);
    el = document.getElementById(addBefore);
    el.parentNode.insertBefore(showHideButton, el);
}


