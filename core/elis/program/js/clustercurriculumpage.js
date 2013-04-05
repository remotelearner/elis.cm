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
String.prototype.starts_with = function (str) {
  return this.indexOf(str) === 0;
}

function cluster_copycurriculum_set_all_selected() {
  var input_elements = document.getElementsByTagName('input');

  for(var i = 0; i < input_elements.length; i++) {

    var element = input_elements[i];

    if(element.name.starts_with('add_curr_')) {
      element.checked = 1;
    }
  }
}

function cluster_copytrack_set_all_selected() {
  var input_elements = document.getElementsByTagName('input');

  for(var i = 0; i < input_elements.length; i++) {

    var element = input_elements[i];

    if(element.name.starts_with('add_trk_curr_')) {
      element.checked = 1;
    }
  }
}

function cluster_copycourse_set_all_selected() {
  var input_elements = document.getElementsByTagName('input');

  for(var i = 0; i < input_elements.length; i++) {

    var element = input_elements[i];

    if(element.name.starts_with('add_crs_curr_')) {
      element.checked = 1;
    }
  }
}

function cluster_copyclass_set_all_selected() {
  var input_elements = document.getElementsByTagName('input');

  for(var i = 0; i < input_elements.length; i++) {

    var element = input_elements[i];

    if(element.name.starts_with('add_cls_curr_')) {
      element.checked = 1;
    }
  }
}
function cluster_copy_checkbox_changed(selected_id, box1, box2, box3)
{
	var element = document.getElementById(selected_id);
	
	if(!element.checked) {
        cluster_copy_disable_checkboxes(box1, box2, box3);
    } else if(element.checked) {
        cluster_copy_enable_checkboxes(box1, box2, box3);  
    }
}
function cluster_copy_disable_checkboxes(box1, box2, box3)
{
    var blacklist = [box1, box2, box3];
   
    for(var i=0;i<blacklist.length;i++)
    {
        var element = document.getElementById(blacklist[i]);
        if(element && element.nodeType === 1)
        {
            //check for element
        	if(element.type === "checkbox")
            {
                element.disabled = "true";
            }
        }else if(!element || element.nodeType !== 1)
        {
            throw new Error("input blacklist item does not exist or is not an element");
        }
    }   
}

function cluster_copy_enable_checkboxes(box1, box2, box3)
{
	var blacklist = [box1, box2, box3];
	
	for(var i=0;i<blacklist.length;i++)
    {
        var element = document.getElementById(blacklist[i]);
        
        if(element.type === "checkbox")
        {
            element.disabled = "";
        }
    }   
}

	
