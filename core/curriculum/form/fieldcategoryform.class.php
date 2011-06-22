<?php
/**
 * Based heavily on /user/profile/index_category_form.php.
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


global $CFG;
require_once($CFG->dirroot.'/lib/formslib.php');
require_once(CURMAN_DIRLOCATION.'/lib/customfield.class.php');

class fieldcategoryform extends moodleform {

    // Define the form
    function definition () {
        global $USER, $CFG;

        $mform =& $this->_form;

        $strrequired = get_string('required');

        $mform->addElement('text', 'name', get_string('profilecategoryname', 'admin'), 'maxlength="255" size="30"');
        $mform->setType('name', PARAM_MULTILANG);
        $mform->addRule('name', $strrequired, 'required', null, 'client');

        $this->add_action_buttons(true);

    } /// End of function

/// perform some moodle validation
    /*
    function validation($data, $files) {
        global $CFG;
        $errors = parent::validation($data, $files);

        $data  = (object)$data;

        $category = get_record(FIELDCATEGORYTABLE, 'id', $data->id);

        /// Check the name is unique
        if ($category and ($category->name !== $data->name) and (record_exists(FIELDCATEGORYTABLE, 'name', $data->name))) {
            $errors['name'] = get_string('profilecategorynamenotunique', 'admin');
        }

        return $errors;
    }
    */
}

?>
