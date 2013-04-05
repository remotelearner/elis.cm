<?php
/**
 * Form used for editing / displaying a user
 *
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

defined('MOODLE_INTERNAL') || die();

require_once elispm::file('form/cmform.class.php');
//require_once elis::lib('customfield.class.php');

class userform extends cmform {
    public function definition() {
        global $USER, $CFG, $COURSE;

        //determine if this is a create or a view / edit
        if ($this->_customdata['obj'] && isset($this->_customdata['obj']->id)) {
            //view / edit
            $disabled = true;
            if (!empty($this->_customdata['obj']->birthdate)) {
                list($this->_customdata['obj']->birthyear, $this->_customdata['obj']->birthmonth, $this->_customdata['obj']->birthday) = sscanf($this->_customdata['obj']->birthdate, '%d/%d/%d');
            }
        } else {
            //create
            $disabled = false;
        }
        parent::definition();

        $mform =& $this->_form;

        $strgeneral                 = get_string('general');
        $strrequired                = get_string('required');

        $bundle = NULL;

        /// Add some extra hidden fields
        $mform->addElement('hidden', 'id');

        $mform->addElement('hidden', 'search');

        $mform->addElement('text', 'idnumber', get_string('idnumber'));
        $mform->setType('idnumber', PARAM_TEXT);
        $mform->addRule('idnumber', null, 'required', null, 'client');
        $mform->addRule('idnumber', null, 'maxlength', 255);
        $mform->addHelpButton('idnumber', 'useridnumber', 'elis_program');

        $username_group = array();

        if(empty($disabled)) {
            //create
            $username_group[] =& $mform->createElement('text', 'username', get_string('username'));
            $username_group[] =& $mform->createElement('checkbox', 'id_same_user', null, get_string('id_same_as_user', 'elis_program'));

            $mform->disabledIf('username_group', 'id_same_user', 'checked');

            $mform->addGroup($username_group, 'username_group', get_string('username'), ' ', false);

            $mform->addRule('username_group', $strrequired, 'required', null, 'client');
        } else {
            //view / edit
            $username_group[] =& $mform->createElement('static', 'username');

            $mform->addGroup($username_group, 'username_group', get_string('username'), ' ', false);

            $mform->freeze('username_group');
        }

        $mform->addGroupRule('username_group', array('username'=>array(array(null, 'maxlength', 100))));

        $mform->addElement('passwordunmask', 'newpassword', get_string('newpassword'));
        $mform->setType('newpassword', PARAM_TEXT);
        $mform->addRule('newpassword', null, 'maxlength', 25);

        $mform->addElement('text', 'firstname', get_string('firstname'));
        $mform->setType('firstname', PARAM_TEXT);
        $mform->addRule('firstname', $strrequired, 'required', null, 'client');
        $mform->addRule('firstname', null, 'maxlength', 100);

        $mform->addElement('text', 'lastname', get_string('lastname'));
        $mform->setType('lastname', PARAM_TEXT);
        $mform->addRule('lastname', $strrequired, 'required', null, 'client');
        $mform->addRule('lastname', null, 'maxlength', 100);

        $mform->addElement('text', 'mi', get_string('usermi', 'elis_program'));
        $mform->setType('mi', PARAM_TEXT);
        $mform->addRule('mi', null, 'maxlength', 100);

        $mform->addElement('text', 'email', get_string('email'));
        $mform->setType('email', PARAM_TEXT);
        $mform->addRule('email', null, 'email', null, 'client');
        $mform->addRule('email', null, 'required', null, 'client');
        $mform->addRule('email', null, 'maxlength', 100);

        $mform->addElement('text', 'email2', get_string('email2', 'elis_program'));
        $mform->setType('email2', PARAM_TEXT);
        $mform->addRule('email2', null, 'email', null, 'client');
        $mform->addRule('email2', null, 'maxlength', 100);

        $mform->addElement('text', 'address', get_string('address', 'elis_program'));
        $mform->setType('address', PARAM_TEXT);
        $mform->addRule('address', null, 'maxlength', 100);

        $mform->addElement('text', 'address2', get_string('address2', 'elis_program'));
        $mform->setType('address2', PARAM_TEXT);
        $mform->addRule('address2', null, 'maxlength', 100);

        $mform->addElement('text', 'city', get_string('city'));
        $mform->setType('city', PARAM_TEXT);
        $mform->addRule('city', null, 'maxlength', 100);

        $mform->addElement('text', 'state', get_string('state'));
        $mform->setType('state', PARAM_TEXT);
        $mform->addRule('state', null, 'maxlength', 100);

        $mform->addElement('text', 'postalcode', get_string('postalcode', 'elis_program'));
        $mform->setType('postalcode', PARAM_TEXT);
        $mform->addRule('postalcode', null, 'maxlength', 32);

        $country = get_string_manager()->get_list_of_countries();
        $default_country[''] = get_string('selectacountry');
        $country = array_merge($default_country, $country);
        $mform->addElement('select', 'country', get_string('country'), $country);
        $mform->addRule('country', $strrequired, 'required', null, 'client');

        $mform->addElement('text', 'phone', get_string('phone'));
        $mform->setType('phone', PARAM_TEXT);
        $mform->addRule('phone', null, 'maxlength', 100);

        $mform->addElement('text', 'phone2', get_string('phone2', 'elis_program'));
        $mform->setType('phone2', PARAM_TEXT);
        $mform->addRule('phone2', null, 'maxlength', 100);

        $mform->addElement('text', 'fax', get_string('fax', 'elis_program'));
        $mform->setType('fax', PARAM_TEXT);
        $mform->addRule('fax', null, 'maxlength', 100);

        $bdaygroup = array();
        $days[''] = get_string('day', 'form');
        for ($i=1; $i<=31; $i++) {
            $days[$i] = $i;
        }
        $months[''] = get_string('month', 'form');
        for ($i=1; $i<=12; $i++) {
            $months[$i] = userdate(gmmktime(12,0,0,$i,15,2000), "%B");
        }
        $years[''] = get_string('year', 'form');
        for ($i=1900; $i<=2020; $i++) {
            $years[$i] = $i;
        }

        $bdaygroup[] =& $mform->createElement('select', 'birthday', get_string('day', 'form'), $days, '', true);
        $bdaygroup[] =& $mform->createElement('select', 'birthmonth', get_string('month', 'form'), $months, '', true);
        $bdaygroup[] =& $mform->createElement('select', 'birthyear', get_string('year', 'form'), $years, '', true);
        $mform->addGroup($bdaygroup, 'birthdate', get_string('userbirthdate', 'elis_program'), ' ', false);

        //$mform->addElement('date_selector', 'birthdate', get_string('userbirthdate', 'elis_program')); // Note: date_selector limited to timestamp > 1970

        $radioarray = array();
        $radioarray[] = &$mform->createElement('radio', 'gender', '', get_string('male', 'elis_program'), 'M');
        $radioarray[] = &$mform->createElement('radio', 'gender', '', get_string('female', 'elis_program'), 'F');
        $mform->addGroup($radioarray, 'gender', get_string('usergender', 'elis_program'), ' ', false);

        $language = get_string_manager()->get_list_of_translations(true);
        $mform->addElement('select', 'language', get_string('language'), $language);
        //$mform->setDefault('language', 'en');
        // ELIS-4041: ^^^ had to remove setDefault('language', 'en')
        //            'cause it never showed real language value always default?
        //            added method to userpage::get_default_object_for_add()
        $mform->addHelpButton('language', 'user_language', 'elis_program');

        $mform->addElement('text', 'transfercredits', get_string('user_transfercredits', 'elis_program'));
        $mform->setType('transfercredits', PARAM_INT);
        $mform->addHelpButton('transfercredits', 'user_transfercredits', 'elis_program');

        $mform->addElement('textarea', 'comments', get_string('comments'));
        $mform->setType('comments', PARAM_CLEAN);
        $mform->addHelpButton('comments', 'user_comments', 'elis_program');

        $mform->addElement('textarea', 'notes', get_string('user_notes', 'elis_program'));
        $mform->setType('notes', PARAM_CLEAN);
        $mform->addHelpButton('notes', 'user_notes', 'elis_program');

        $mform->addElement('advcheckbox', 'inactive', get_string('user_inactive', 'elis_program'));
        $mform->setType('inactive', PARAM_TEXT);
        $mform->addHelpButton('inactive', 'user_inactive', 'elis_program');

        $this->add_custom_fields('user', 'elis/program:user_edit',
                                 'elis/program:user_view');

        $this->add_action_buttons();
    }

    function check_unique($table, $field, $value, $id) {
        global $DB;
        return !$DB->record_exists_select($table, "$field = ? AND id <> ?", array($value, $id));
    }

    function validation($data, $files) {
        global $CFG, $DB;
        $errors = parent::validation($data, $files);

        // Use a default for 'id' if we're doing an add
        if (!$data['id']) {
            $data['id'] = 0;
        }

        if (!empty($data['username'])) {
            if (!$this->check_unique(user::TABLE, 'username', $data['username'], $data['id'])) {
                $errors['username_group'] = get_string('badusername', 'elis_program');
            }
        } else if(!$data['id'] && empty($data['id_same_user'])) {
            $errors['username_group'] = get_string('required');
        }

        if (!empty($data['idnumber'])) {
            if (!$this->check_unique(user::TABLE, 'idnumber', $data['idnumber'], $data['id'])) {
                $errors['idnumber'] = get_string('badidnumber', 'elis_program');
            } else {
                //make sure we don't set up an idnumber that is related to a non-linked Moodle user
                require_once(elispm::lib('data/usermoodle.class.php'));
                if (!$muserid = $DB->get_field(usermoodle::TABLE, 'muserid', array('cuserid' => $data['id']))) {
                    $muserid = 0;
                }
                if (!$this->check_unique('user', 'idnumber', $data['idnumber'], $muserid)) {
                    $errors['idnumber'] = get_string('badidnumbermoodle', 'elis_program');
                }
            }
        }

        // Validate the supplied email addresses as best we can...
        if (!empty($data['email'])) {
            if (!$this->check_unique(user::TABLE, 'email', $data['email'], $data['id'])) {
                $errors['email'] = get_string('emailexists');
            }
        }

        if (!empty($data['email2'])) {
            if (!$this->check_unique(user::TABLE, 'email', $data['email2'], $data['id'])) {
                $errors['email2'] = get_string('emailexists');
            }
        }

        if (!empty($data['contactemail'])) {
            if (!$this->check_unique(user::TABLE, 'email', $data['contactemail'], $data['id'])) {
                $errors['contactemail'] = get_string('emailexists');
            }
        }

        $errors += parent::validate_custom_fields($data, 'user');

        return $errors;
    }
}
