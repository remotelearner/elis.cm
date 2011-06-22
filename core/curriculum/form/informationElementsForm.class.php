<?php
/**
 *  ELIS(TM): Enterprise Learning Intelligence Suite
 *
 *  Copyright (C) 2008-2010 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *  @package    elis
 *  @subpackage curriculummanagement
 *  @author     Remote-Learner.net Inc
 *  @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 *  @copyright  (C) 2008-2010 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(CURMAN_DIRLOCATION . '/form/cmform.class.php');

/**
 * forms for submitting a name/description for the information elements
 * requires 's', 'action', 'id', 'title' values to be passed in customdata
 */
class ieform extends cmform {
    /**
     * inserserts the elements for the form name text box and description text box
     *
     * @return nothing
     */
    public function definition() {
        if($this->_customdata['obj']) {
            // FIXME: This is probably not be the right place for set_data.  Move it.
            $this->set_data($this->_customdata['obj']);
        }

        $mform =& $this->_form;

        $mform->addElement('hidden', 'id');

        $mform->addElement('text', 'name', get_string('tag_name', 'block_curr_admin') . ':');
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required_field', 'block_curr_admin', get_string('tag_name', 'block_curr_admin')), 'required', null, 'client');
        $mform->addRule('name', null, 'maxlength', 64);

        $attributes = array('rows'=>2, 'cols'=>40);
        $mform->addElement('textarea', 'description', get_string('tag_description', 'block_curr_admin') . ': ', $attributes);
        $mform->setType('description', PARAM_CLEAN);

        $this->add_action_buttons();
    }
}

?>
