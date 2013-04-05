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

/**
 * Initialize the custom field table
 * @param block_id	The id of the block where the table is located
 * @param action	The action to be performed on the field id
 * @param path		The path to find the dynamictable code
 * @return boolean	true
 */
function customfields_initializeTable(block_id,action,path,fieldidlist,fieldnamelist,scheduled) {

    var custom_field_success = function(o) {
        var container = document.getElementById('fieldtable' + block_id);
        var endOfTable = o.responseText.lastIndexOf('</table>') + 8;
        var fieldidElement = document.getElementById('id_field' + block_id);
        var tableElement = document.getElementById('fieldtable' + block_id);
        var fieldidsElement = document.getElementById('fieldidlist' + block_id);
        var fieldnamesElement = document.getElementById('fieldnamelist' + block_id);

        //Response text now includes updated field id and name lists all serialized and base64 encoded
        var foundlastTable = o.responseText.lastIndexOf('</table>');
        var hiddenData = o.responseText.substring(foundlastTable + 8,
                                            o.responseText.length);
        var hiddenArray = new Array();
        hiddenArray = hiddenData.split(':');

        var hiddenEncodedFieldIdList = hiddenArray[1];
        var hiddenEncodedFieldNameList = hiddenArray[2];
 
        tableElement.innerHTML = o.responseText.substring(0, foundlastTable + 8);

        fieldidsElement.value = hiddenEncodedFieldIdList;
        fieldidElement.value = hiddenEncodedFieldIdList;
        fieldnamesElement.value = hiddenEncodedFieldNameList;
    };

    var custom_field_failure = function(o) {
        alert("failure: " + o.responseText);
    };

    var callback = {
        success: custom_field_success,
        failure: custom_field_failure
    };

    var requestURL = path + "dynamictable.php?action=" + action + "&block_id=" + block_id + "&fieldidlist=" + fieldidlist + "&fieldnamelist=" + fieldnamelist + "&scheduled=" + scheduled;

    YUI().use('yui2-connection', function(Y) {
        var YAHOO = Y.YUI2;
        YAHOO.util.Connect.asyncRequest('GET', requestURL, callback, null);
    });
    return true;
}

/**
 * Calls dynamic table with these parms to update the table
 * and sets hidden form fields with list data
 * @param block_id	The id of the block where the table is located
 * @param action	The action to be performed on the field id
 * @param path		The path to find the dynamictable code
 * @param fieldId	The custom field id
 * @param fieldName	The custom field name (including category for now)
 * @param fieldIdList	List of custom field ids currently selected for filter
 * @param fieldNameList	List of custom field names currently selected for filter
 * @return boolean	true
 */
function customfields_updateTable(block_id, action, path, fieldId, fieldName, fieldIdList, fieldNameList){
    //on success, find the table and update its contents
    var container = null;

    var custom_field_success = function(o) {
        //Add action is coming from course popup window
        if (action === 'add') {
            container = window.opener.document;
        } else {
            container = document;
        }

        var fieldidElement = container.getElementById('id_field' + block_id);
        var tableElement = container.getElementById('fieldtable' + block_id);
        var fieldidsElement = container.getElementById('fieldidlist' + block_id);
        var fieldnamesElement = container.getElementById('fieldnamelist' + block_id);
        //Response text now includes updated field id and name lists all serialized and base64 encoded
        var foundlastTable = o.responseText.lastIndexOf('</table>');
        var hiddenData = o.responseText.substring(foundlastTable + 8,
                                            o.responseText.length);
        var hiddenArray = new Array();
        hiddenArray = hiddenData.split(':');

        var hiddenEncodedFieldIdList = hiddenArray[1];
        var hiddenEncodedFieldNameList = hiddenArray[2];

        tableElement.innerHTML = o.responseText.substring(0, foundlastTable + 8);

        fieldidsElement.value = hiddenEncodedFieldIdList;
        fieldidElement.value = hiddenEncodedFieldIdList;
        fieldnamesElement.value = hiddenEncodedFieldNameList;

        if (action === 'add') {
            window.close();
        }
    };

    var custom_field_failure = function(o) {
        alert("failure: " + o.responseText);
    };

    var callback = {
        success: custom_field_success,
        failure: custom_field_failure
    };

    var requestURL = path + "dynamictable.php?fieldid=" + fieldId + "&fieldname=" + fieldName + "&fieldidlist=" + fieldIdList + "&fieldnamelist=" + fieldNameList + "&action=" + action + "&block_id=" + block_id;

    YUI().use('yui2-connection', function(Y) {
        var YAHOO = Y.YUI2;
        YAHOO.util.Connect.asyncRequest('GET', requestURL, callback, null);
    });
    return true;
}

/**
 * Get hidden fieldid and fieldname lists from form
 * and open a popup window with these parameters
 * @param block_id	The id of the block where the table is located
 * @param action	The action to be performed on the field id
 * @param popupurl	The path to find the popup window
 * @return none
 */
function customfields_updateFieldLists(block_id, action, popupurl){

    var fieldidlist = document.getElementById('fieldidlist' + block_id);
    var fieldnamelist = document.getElementById('fieldnamelist' + block_id);

    popupurl += '?instance='+block_id;
    if (fieldidlist !== null) {
        popupurl += '&fieldidlist=' + fieldidlist.value;
    }

    if (fieldnamelist !== null) {
        popupurl += '&fieldnamelist=' + fieldnamelist.value;
    }

    window.open(popupurl, '', 'width=500,height=500,resizable,scrollbars');
}

