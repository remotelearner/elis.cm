<?php
/**
 * Form used for editing / displaying a bundle record.
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

require_once(CURMAN_DIRLOCATION . '/form/cmform.class.php');
require_once(CURMAN_DIRLOCATION . '/lib/customfield.class.php');

class userform extends cmform {
    public function definition() {
        global $USER, $CFG, $COURSE;

        if($this->_customdata['obj']) {
            $this->set_data($this->_customdata['obj']);
            $disabled = true;
        } else {
            $disabled = false;
        }

        $mform =& $this->_form;

        $strgeneral                 = get_string('general');
        $strrequired                = get_string('required');

        $bundle = NULL;

        /// Add some extra hidden fields
        $mform->addElement('hidden', 'id');

        $mform->addElement('hidden', 'search');

        $mform->addElement('text', 'idnumber', get_string('idnumber'));
        $mform->setType('idnumber', PARAM_TEXT);
        $mform->addRule('idnumber', null, 'maxlength', 255);
        $mform->setHelpButton('idnumber', array('userform/idnumber', get_string('idnumber'), 'block_curr_admin'));

        $username_group = array();

        if(empty($disabled)){
            $mform->addRule('idnumber', null, 'required', null, 'client');

            $username_group[] =& $mform->createElement('text', 'username', get_string('username'));
            $username_group[] =& $mform->createElement('checkbox', 'id_same_user', null, get_string('id_same_as_user', 'block_curr_admin'));

            $mform->disabledIf('username_group', 'id_same_user', 'checked');

            $mform->addGroup($username_group, 'username_group', get_string('username'), ' ', false);

            $mform->addRule('username_group', $strrequired, 'required', null, 'client');
        } else {
            $mform->freeze('idnumber');
            $username_group[] =& $mform->createElement('static', 'username');

            $mform->addGroup($username_group, 'username_group', get_string('username'), ' ', false);

            $mform->freeze('username_group');
        }

        $mform->addGroupRule('username_group', array('username'=>array(array(null, 'maxlength', 100))));

        $mform->addElement('passwordunmask', 'newpassword', get_string('newpassword'));
        $mform->setType('newpassword', PARAM_TEXT);
        $mform->addRule('newpassword', null, 'maxlength', 25);

        $mform->addElement('text', 'firstname', get_string('userfirstname', 'block_curr_admin'));
        $mform->setType('firstname', PARAM_TEXT);
        $mform->addRule('firstname', $strrequired, 'required', null, 'client');
        $mform->addRule('firstname', null, 'maxlength', 100);

        $mform->addElement('text', 'lastname', get_string('userlastname', 'block_curr_admin'));
        $mform->setType('lastname', PARAM_TEXT);
        $mform->addRule('lastname', $strrequired, 'required', null, 'client');
        $mform->addRule('lastname', null, 'maxlength', 100);

        $mform->addElement('text', 'mi', get_string('usermi', 'block_curr_admin'));
        $mform->setType('mi', PARAM_TEXT);
        $mform->addRule('mi', null, 'maxlength', 100);

        $mform->addElement('text', 'email', get_string('email', 'block_curr_admin'));
        $mform->setType('email', PARAM_TEXT);
        $mform->addRule('email', null, 'email', null, 'client');
        $mform->addRule('email', null, 'required', null, 'client');
        $mform->addRule('email', null, 'maxlength', 100);

        $mform->addElement('text', 'email2', get_string('email2', 'block_curr_admin'));
        $mform->setType('email2', PARAM_TEXT);
        $mform->addRule('email2', null, 'email', null, 'client');
        $mform->addRule('email2', null, 'maxlength', 100);

        $mform->addElement('text', 'address', get_string('useraddress', 'block_curr_admin'));
        $mform->setType('address', PARAM_TEXT);
        $mform->addRule('address', null, 'maxlength', 100);

        $mform->addElement('text', 'address2', get_string('useraddress2', 'block_curr_admin'));
        $mform->setType('address2', PARAM_TEXT);
        $mform->addRule('address2', null, 'maxlength', 100);

        $mform->addElement('text', 'city', get_string('usercity', 'block_curr_admin'));
        $mform->setType('city', PARAM_TEXT);
        $mform->addRule('city', null, 'maxlength', 100);

        $mform->addElement('text', 'state', get_string('userstate', 'block_curr_admin'));
        $mform->setType('state', PARAM_TEXT);
        $mform->addRule('state', null, 'maxlength', 100);

        $mform->addElement('text', 'postalcode', get_string('userpostalcode', 'block_curr_admin'));
        $mform->setType('postalcode', PARAM_TEXT);
        $mform->addRule('postalcode', null, 'maxlength', 32);

        $country = cm_get_list_of_countries();
        $default_country[''] = get_string('selectacountry');
        $country = array_merge($default_country, $country);
        $mform->addElement('select', 'country', get_string('country'), $country);
        $mform->addRule('country', $strrequired, 'required', null, 'client');

        $mform->addElement('text', 'phone', get_string('phone'));
        $mform->setType('phone', PARAM_TEXT);
        $mform->addRule('phone', null, 'maxlength', 100);

        $mform->addElement('text', 'phone2', get_string('phone2', 'block_curr_admin'));
        $mform->setType('phone2', PARAM_TEXT);
        $mform->addRule('phone2', null, 'maxlength', 100);

        $mform->addElement('text', 'fax', get_string('fax', 'block_curr_admin'));
        $mform->setType('fax', PARAM_TEXT);
        $mform->addRule('fax', null, 'maxlength', 100);

        $bdaygroup = array();
        $days[''] = get_string('selectdays', 'block_curr_admin');
        for ($i=1; $i<=31; $i++) {
            $days[$i] = $i;
        }
        $months[''] = get_string('selectmonths', 'block_curr_admin');
        for ($i=1; $i<=12; $i++) {
            $months[$i] = userdate(gmmktime(12,0,0,$i,15,2000), "%B");
        }
        $years[''] = get_string('selectyears', 'block_curr_admin');
        for ($i=1900; $i<=2020; $i++) {
            $years[$i] = $i;
        }
        $bdaygroup[] =& $mform->createElement('select', 'birthday', get_string('day', 'block_curr_admin'), $days, '', true);
        $bdaygroup[] =& $mform->createElement('select', 'birthmonth', get_string('month', 'block_curr_admin'), $months, '', true);
        $bdaygroup[] =& $mform->createElement('select', 'birthyear', get_string('year', 'block_curr_admin'), $years, '', true);
        $mform->addGroup($bdaygroup, 'birthdate', get_string('userbirthdate', 'block_curr_admin'), ' ', false);

//        $mform->addElement('date_selector', 'birthdate', get_string('userbirthdate', 'block_curr_admin')); //TODO: the bdaygroup stuff with this but need to update the pages as well

        $radioarray = array();
        $radioarray[] = &$mform->createElement('radio', 'gender', '', get_string('male', 'block_curr_admin'), 'M');
        $radioarray[] = &$mform->createElement('radio', 'gender', '', get_string('female', 'block_curr_admin'), 'F');
        $mform->addGroup($radioarray, 'gender', get_string('usergender', 'block_curr_admin'), ' ', false);

        $language = cm_get_list_of_languages();
        $mform->addElement('select', 'language', get_string('userlanguage', 'block_curr_admin'), $language);
        $mform->setDefault('language', 'English');
        $mform->setHelpButton('language', array('userform/language', get_string('userlanguage', 'block_curr_admin'), 'block_curr_admin'));

        $mform->addElement('text', 'transfercredits', get_string('transfercredits', 'block_curr_admin'));
        $mform->setType('transfercredits', PARAM_INT);
        $mform->setHelpButton('transfercredits', array('userform/transfercredits', get_string('transfercredits', 'block_curr_admin'), 'block_curr_admin'));

        $mform->addElement('textarea', 'comments', get_string('usercomments', 'block_curr_admin'));
        $mform->setType('comments', PARAM_CLEAN);
        $mform->setHelpButton('comments', array('userform/comments', get_string('usercomments', 'block_curr_admin'), 'block_curr_admin'));

        $mform->addElement('textarea', 'notes', get_string('notes', 'block_curr_admin'));
        $mform->setType('notes', PARAM_CLEAN);
        $mform->setHelpButton('notes', array('userform/notes', get_string('notes', 'block_curr_admin'), 'block_curr_admin'));

        $mform->addElement('advcheckbox', 'inactive', get_string('inactive', 'block_curr_admin'));
        $mform->setType('inactive', PARAM_TEXT);
        $mform->setHelpButton('inactive', array('userform/inactive', get_string('inactive', 'block_curr_admin'), 'block_curr_admin'));

        $fields = field::get_for_context_level('user');
        $fields = $fields ? $fields : array();

        $lastcat = null;
        $context = isset($this->_customdata['obj']) && isset($this->_customdata['obj']->id)
            ? get_context_instance(context_level_base::get_custom_context_level('user', 'block_curr_admin'), $this->_customdata['obj']->id)
            : get_context_instance(CONTEXT_SYSTEM);
        require_once CURMAN_DIRLOCATION.'/plugins/manual/custom_fields.php';
        foreach ($fields as $rec) {
            $field = new field($rec);
            if (!isset($field->owners['manual'])) {
                continue;
            }
            if ($lastcat != $rec->categoryid) {
                $lastcat = $rec->categoryid;
                $mform->addElement('header', "category_{$lastcat}", htmlspecialchars($rec->categoryname));
            }
            manual_field_add_form_element($this, $context, $field);
        }

        if($this->_customdata['obj']) {
            $this->set_data($this->_customdata['obj']);
        }

        $this->add_action_buttons();
    }

    function check_unique($table, $field, $value, $id) {
        return !record_exists_select($table, "$field = '$value' AND id <> $id");
    }

    function validation($data, $files) {
        global $CFG, $CURMAN;
        $errors = parent::validation($data, $files);

        // Use a default for 'id' if we're doing an add
        if(!$data['id']) {
            $data['id'] = 0;
        }

        if (!empty($data['username'])) {
            if (!$this->check_unique(USRTABLE, 'username', $data['username'], $data['id'])) {
                $errors['username_group'] = get_string('badusername', 'block_curr_admin');
            }
        } else if(!$data['id'] && empty($data['id_same_user'])) {
            $errors['username_group'] = get_string('required');
        }

        if (!empty($data['idnumber'])) {
            if (!$this->check_unique(USRTABLE, 'idnumber', $data['idnumber'], $data['id'])) {
                $errors['idnumber'] = get_string('badidnumber', 'block_curr_admin');
            }
        }

        // Validate the supplied email addresses as best we can...
        if (!empty($data['email'])) {
            if (!$this->check_unique(USRTABLE, 'email', $data['email'], $data['id'])) {
                $errors['email'] = get_string('emailexists');
            }
        }

        if (!empty($data['email2'])) {
            if (!$this->check_unique(USRTABLE, 'email', $data['email2'], $data['id'])) {
                $errors['email2'] = get_string('emailexists');
            }
        }

        if (!empty($data['contactemail'])) {
            if (!$this->check_unique(USRTABLE, 'email', $data['contactemail'], $data['id'])) {
                $errors['contactemail'] = get_string('emailexists');
            }
        }

        // validate custom profile fields
        $fields = field::get_for_context_level(context_level_base::get_custom_context_level('user', 'block_curr_admin'));
        $fields = $fields ? $fields : array();
        if ($data['id']) {
            $context = get_context_instance(context_level_base::get_custom_context_level('user', 'block_curr_admin'), $data['id']);
            $contextid = $context->id;
        } else {
            $contextid = 0;
        }

        foreach ($fields as $field) {
            $field = new field($field);
            if ($field->forceunique) {
                $fielddata = $CURMAN->db->get_record($field->data_table(), 'fieldid', $field->id, 'data', $data["field_{$field->shortname}"]);
                print_object($fielddata);
                if ($fielddata && $fielddata->contextid != $contextid) {
                    $errors["field_{$field->shortname}"] = get_string('valuealreadyused');
                }
            }
        }

        return $errors;
    }
}
?>
