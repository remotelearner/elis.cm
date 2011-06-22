<?php // $Id$

/**
 * Form used for signing up a user.
 *
 * @version $Id$
 * @author Justin Filip <jfilip@oktech.ca>
 * @author Open Knowlege Technologies - http://www.oktech.ca/
 * @author Remote Learner - http://www.remote-learner.net/
 */


    require_once($CFG->dirroot . '/lib/formslib.php');
    require_once($CFG->dirroot . '/curriculum/lib/lib.php');


    class login_signup_form extends moodleform {

        function definition() {
            global $USER, $CFG, $COURSE;

            $mform =& $this->_form;

            $strgeneral                 = get_string('general');
            $strrequired                = get_string('required');
            $strenglishcharactersonly   = get_string('englishcharactersonly', 'block_curr_admin');

            $bundle = NULL;

            $userid = optional_param('id', 0, PARAM_INT);

        /// Custom rules:
            $mform->registerRule('englishcharacters', 'callback', 'check_character_set');

        /// Add some extra hidden fields
            $mform->addElement('hidden', 'id');
            $mform->addElement('hidden', 's');
            $mform->addElement('hidden', 'section');
            $mform->addElement('hidden', 'action');
            $mform->addElement('hidden', 'search');

            $mform->addElement('header', 'main', get_string('userregister', 'block_curr_admin'));
            //$mform->setHelpButton('main', array('bundleadmin/bundle', get_string('help_bundle', 'block_curr_admin')));

            $mform->addElement('text', 'firstname', get_string('userfirstname', 'block_curr_admin'),
                               'size="50" maxlength="255"');
            $mform->addRule('firstname', $strrequired, 'required', NULL, 'client');
            $mform->addRule('firstname', $strenglishcharactersonly, 'englishcharacters', NULL, 'client');
            $mform->setType('firstname', PARAM_RAW);

            $mform->addElement('text', 'lastname', get_string('userlastname', 'block_curr_admin'),
                               'size="50" maxlength="255"');
            $mform->addRule('lastname', $strrequired, 'required', NULL, 'client');
            $mform->addRule('lastname', $strenglishcharactersonly, 'englishcharacters', NULL, 'client');
            $mform->setType('lastname', PARAM_RAW);

            $mform->addElement('text', 'email', get_string('useremail', 'block_curr_admin'),
                               'size="50" maxlength="100"');
            $mform->addRule('email', $strrequired, 'required', NULL, 'client');
            $mform->addRule('email', $strenglishcharactersonly, 'englishcharacters', NULL, 'client');
            $mform->setType('email', PARAM_RAW);

            $mform->addElement('text', 'address', get_string('useraddress', 'block_curr_admin'),
                               'size="50" maxlength="100"');
            $mform->addRule('address', $strenglishcharactersonly, 'englishcharacters', NULL, 'client');
            $mform->setType('address', PARAM_RAW);

            $mform->addElement('text', 'address2', get_string('useraddress2', 'block_curr_admin'),
                               'size="50" maxlength="100"');
            $mform->addRule('address2', $strenglishcharactersonly, 'englishcharacters', NULL, 'client');
            $mform->setType('address2', PARAM_RAW);

            $mform->addElement('text', 'city', get_string('usercity', 'block_curr_admin'),
                               'size="50" maxlength="100"');
            $mform->addRule('city', $strrequired, 'required', NULL, 'client');
            $mform->addRule('city', $strenglishcharactersonly, 'englishcharacters', NULL, 'client');
            $mform->setType('city', PARAM_RAW);

            /// Make the state group "look" required.
//            $mform->addElement('html', '<span class="required">');
            $stategroup = array();
            $stategroup[] =& $mform->createElement('text', 'state', null, 'size="50" maxlength="100"');
            $mform->setType('state', PARAM_RAW);
            $stategroup[] =& $mform->createElement('checkbox', 'statenone', null, get_string('none'));
            /// Make the state group "look" required.
            $label = get_string('userstate', 'block_curr_admin').
                     '<img class="req" src="'.$CFG->wwwroot.'/pix/req.gif" alt="Required field" title="Required field"/>';
            $mform->addGroup($stategroup, 'stategrp', $label, ' ', false);
//            $mform->addElement('html', '</span>');

            $mform->addElement('text', 'postalcode', get_string('userpostalcode', 'block_curr_admin'),
                               'size="32" maxlength="32"');
            $mform->addRule('postalcode', $strenglishcharactersonly, 'englishcharacters', NULL, 'client');
            $mform->setType('postalcode', PARAM_RAW);

            $country = cm_get_list_of_countries();
            $default_country[''] = get_string('selectacountry');
            $country = array_merge($default_country, $country);
            $mform->addElement('select', 'country', get_string('country'), $country);
            $mform->addRule('country', get_string('missingcountry'), 'required', null, 'server');

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
            $bdaygroup[] =& $mform->createElement('select', 'day', get_string('day', 'block_curr_admin'), $days, '', true);
            $bdaygroup[] =& $mform->createElement('select', 'month', get_string('month', 'block_curr_admin'), $months, '', true);
            $bdaygroup[] =& $mform->createElement('select', 'year', get_string('year', 'block_curr_admin'), $years, '', true);
            $mform->addGroup($bdaygroup, 'birthdate', get_string('userbirthdate', 'block_curr_admin'), ' ', true);
            $mform->addGroupRule('birthdate', $strrequired, 'required', NULL, 'client');

            $radioarray = array();
            $radioarray[] = &$mform->createElement('radio', 'gender', '', get_string('male', 'block_curr_admin'), 'M');
            $radioarray[] = &$mform->createElement('radio', 'gender', '', get_string('female', 'block_curr_admin'), 'F');
            $mform->addGroup($radioarray, 'gender', get_string('usergender', 'block_curr_admin'), ' ', false);
            $mform->addRule('gender', $strrequired, 'required', NULL, 'client');

            $language = cm_get_list_of_languages();
            $default = 'English';
            $mform->addElement('select', 'language', get_string('userlanguage', 'block_curr_admin'), $language);
            $mform->setDefault('language', $default);
            $mform->addRule('language', $strrequired, 'required', NULL, 'client');

            $mform->addElement('textarea', 'comments', get_string('usercomments', 'block_curr_admin'),
                               'cols="50" rows="5"');
            $mform->addRule('comments', $strenglishcharactersonly, 'englishcharacters', NULL, 'client');
            $mform->setType('comments', PARAM_CLEAN);

            $mform->addElement('text', 'email2', get_string('useremail2', 'block_curr_admin'),
                               'size="50" maxlength="100"');
            $mform->addRule('email2', $strrequired, 'required', NULL, 'client');
            $mform->addRule('email2', $strenglishcharactersonly, 'englishcharacters', NULL, 'client');
            $mform->setType('email2', PARAM_RAW);

            $mform->addElement('html', '<div style="margin-left:2em; margin-right:2em;"><br clear="all" /><p>'.
                               get_string('userpayment', 'block_curr_admin').'</p></div>');

            $mform->addElement('submit', 'submit', get_string('submit'));

            $mform->addElement('static', 'submittext', '', get_string('submittext', 'block_curr_admin'));

        }


        function validation($data, $files) {
            global $CFG;
            $errors = parent::validation($data, $files);

        /// Validate the supplied email addresses as best we can...
            if (validate_email($data['email'])) {
                if (record_exists('user', 'email', $data['email'])) {
                    $errors['email'] = get_string('emailexists').' <a href="forgot_password.php">'.get_string('newpassword').'?</a>';
                }
                if (empty($data['email2'])) {
                    $errors['email2'] = get_string('missingemail');

                } else if ($data['email2'] != $data['email']) {
                    $errors['email2'] = get_string('invalidemail');
                }
            } else if (($data['email2'] != 'none') || ($data['email'] != 'none')) {
                $errors['email2'] = get_string('invalidemail');
            }

            if (!isset($errors['email'])) {
                if ($err = email_is_not_allowed($data['email'])) {
                    $errors['email'] = $err;
                }

            }


            $birthdate = (sprintf('%04d', $data['birthdate']['year'])) . '/' .
                         (sprintf('%02d', $data['birthdate']['month'])) . '/' .
                         (sprintf('%02d', $data['birthdate']['day']));
            if (record_exists('crlm_user', 'firstname', $data['firstname'], 'lastname', $data['lastname'],
                              'birthdate', $birthdate)) {
                $errors['firstname'] = get_string('alreadyregistered', 'block_curr_admin');
                $errors['lastname'] = get_string('alreadyregistered', 'block_curr_admin');
                $errors['birthdate'] = get_string('alreadyregistered', 'block_curr_admin');
            }

            if (empty($data['statenone']) && empty($data['stategrp']['statenone']) &&
                empty($data['state']) && empty($data['stategrp']['state'])) {
                $errors['stategrp'] = get_string('required');
            }

            return $errors;
        }
    }

    /**
     * Validate a string is using only valid English characters.
     *
     * @param string    $string     The string to validate.
     *
     */
    function check_character_set($string) {

//        $basiclatin = '\x20-\x7F'; // Basic English characters
//        $latin_1    = '\x0C-\xFF'; // Spanish, French, etc.
        $basiclatin = ' -~'; // Basic English characters
        $latin_1    = 'À-ÿ'; // Spanish, French, etc.

        $regex = '/^['.$basiclatin.$latin_1.']+$/';
        if (!preg_match($regex, $string)) {
            return false;
        }
        return true;
    }
?>