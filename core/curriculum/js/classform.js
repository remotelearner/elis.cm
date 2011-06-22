/**
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

String.prototype.starts_with = function (str) {
	return this.indexOf(str) === 0;
}

String.prototype.ends_with = function (str) {
	return this.indexOf(str) === this.length - str.length;
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