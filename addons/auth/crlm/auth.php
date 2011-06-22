<?php
/**
 * Curriculum Management Authentication plug-in
 *
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2009 Remote-Learner.net Inc (http://www.remote-learner.net)
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

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->libdir.'/authlib.php');
require_once($CFG->dirroot.'/curriculum/lib/lib.php');
require_once($CFG->dirroot.'/curriculum/lib/user.class.php');

/**
 * Email authentication plugin.
 */
class auth_plugin_crlm extends auth_plugin_base {

    /**
     * Constructor.
     */
    function auth_plugin_crlm() {
        $this->authtype = 'crlm';
        $this->config = get_config('auth/crlm');
    }

    /**
     * Returns true if the username and password work and false if they are
     * wrong or don't exist.
     *
     * @param string $username The username
     * @param string $password The password
     * @return bool Authentication success or failure.
     */
    function user_login ($username, $password) {
        global $CFG;
        if ($user = get_record('user', 'username', $username, 'mnethostid', $CFG->mnet_localhost_id)) {
            return validate_internal_user_password($user, $password);
        }
        return false;
    }

    /**
     * Updates the user's password.
     *
     * called when the user password is updated.
     *
     * @param  object  $user        User table object  (with system magic quotes)
     * @param  string  $newpassword Plaintext password (with system magic quotes)
     * @return boolean result
     *
     */
    function user_update_password($user, $newpassword) {
        global $CFG;

        require_once $CFG->dirroot.'/curriculum/config.php';
        require_once CURMAN_DIRLOCATION . '/lib/user.class.php';

        $user = get_complete_user_data('id', $user->id);

        $select = "idnumber = '{$user->idnumber}'";
        $cuser = new user($select);
        if (!empty($cuser->id)) {
            $cuser->change_password($newpassword, true);
        }

        return update_internal_user_password($user, $newpassword);
    }

    function can_signup() {
        return true;
    }

    /**
     * Sign up a new user ready for confirmation.
     * Password is passed in plaintext.
     *
     * @param object $user new user object (with system magic quotes)
     * @param boolean $notify print notice with link and terminate
     */
    function user_signup($user, $notify=true) {
        global $CFG;
        require_once($CFG->dirroot.'/user/profile/lib.php');

    /// Create a new curriculum user.
        $curuser = new user($user);
//        $curuser->generate_password();

    /// Calculate the birthdate.
//        $curuser->birthdate = (sprintf('%04d', $user->birthdate['year'])) . '/' .
//                              (sprintf('%02d', $user->birthdate['month'])) . '/' .
//                              (sprintf('%02d', $user->birthdate['day']));

    /// Create a new Moodle user from the user object passed in.
        $moodleuser = new Object();
        $moodleuser = clone($user);

    /// Modify the Moodle user with any curriculum user data.
        $moodleuser->username = $curuser->username;
        $moodleuser->password = $curuser->password;
        $moodleuser->password = hash_internal_user_password($moodleuser->password);
        $moodleuser->idnumber = $curuser->idnumber = $curuser->username;
        $moodleuser->timemodified = time();

//** TODO - Fix email validation **//
    /// Validate the email address.
        if (!validate_email($curuser->email)) {
            if (!validate_email($curuser->email2)) {
                $moodleuser->email = 'none@email.com';  /// This will only happen if both are set to "none" (see '/blocks/curr_admin/customscripts/login/signup_form.php').
            } else {
                $moodleuser->email = $user->email2;
            }
        } else {
            $moodleuser->email = $curuser->email;
        }

        if (! ($user->id = insert_record('user', $moodleuser)) ) {
            print_error('auth_emailnoinsert','auth');
        }
        /// Save any custom profile field information
        profile_save_data($user);

        $curuser->email2 = $curuser->contactemail = $user->email2;

        if (!$curuser->add()) {
            print_error('curr_usernotcreated', 'curr');
        }

        /// Assign them to the default curriculum (*** MAKE THIS CONFIGURABLE ***)
//        $curuser->assign_curriculum(array('idnumber' => 'CRS'));

        if (! $curuser->send_confirmation_email($this->config->crlm_fromemail, $this->config->crlm_emailadmins)) {
            print_error('auth_emailnoemail','auth');
        }

        if ($notify) {
            $emailconfirm = get_string('emailconfirm');
            $navlinks = array();
            $navlinks[] = array('name' => $emailconfirm, 'link' => null, 'type' => 'misc');
            $navigation = build_navigation($navlinks);

            print_header($emailconfirm, $emailconfirm, $navigation);
            $notice = new Object();
            $notice->email = $user->email;
            $this->notice(get_string('emailconfirmsent', 'auth_crlm', $notice), $CFG->wwwroot);
        } else {
            return true;
        }
    }

    /**
     * Returns true if plugin allows confirming of new users.
     *
     * @return bool
     */
    function can_confirm() {
        return true;
    }

    /**
     * Confirm the new user as registered.
     *
     * @param string $username (with system magic quotes)
     * @param string $confirmsecret (with system magic quotes)
     */
    function user_confirm($username, $confirmsecret) {
        $user = get_complete_user_data('username', $username);

        if (!empty($user)) {
            if ($user->confirmed) {
                return AUTH_CONFIRM_ALREADY;

            } else if ($user->auth != 'crlm') {
                return AUTH_CONFIRM_ERROR;

            } else if ($user->secret == stripslashes($confirmsecret)) {   // They have provided the secret key to get in
                if (!set_field("user", "confirmed", 1, "id", $user->id)) {
                    return AUTH_CONFIRM_FAIL;
                }
                if (!set_field("user", "firstaccess", time(), "id", $user->id)) {
                    return AUTH_CONFIRM_FAIL;
                }
                return AUTH_CONFIRM_OK;
            }
        } else {
            return AUTH_CONFIRM_ERROR;
        }
    }


    /**
     * Returns true if this authentication plugin is 'internal'.
     *
     * @return bool
     */
    function is_internal() {
        return true;
    }

    /**
     * Returns true if this authentication plugin can change the user's
     * password.
     *
     * @return bool
     */
    function can_change_password() {
        return true;
    }

    /**
     * Returns the URL for changing the user's pw, or empty if the default can
     * be used.
     *
     * @return mixed
     */
    function change_password_url() {
        return ''; // use dafult internal method
    }

    /**
     * Returns true if plugin allows resetting of internal password.
     *
     * @return bool
     */
    function can_reset_password() {
        return true;
    }

    /**
     * Prints a form for configuring this authentication plugin.
     *
     * This function is called from admin/auth.php, and outputs a full page with
     * a form for configuring this plugin.
     *
     * @param array $page An object containing all the data for this page.
     */
    function config_form($config, $err, $user_fields) {
        include "config.html";
    }

    /**
     * Processes and stores configuration data for this authentication plugin.
     */
    function process_config($config) {
        // set to defaults if undefined
        if (!isset($config->crlm_fromemail)) {
            $config->crlm_fromemail = $CFG->supportemail;
        }
        if (!isset($config->crlm_emailadmins)) {
            $config->crlm_emailadmins = $CFG->supportemail;
        }

        // save settings
        set_config('crlm_fromemail', $config->crlm_fromemail, 'auth/crlm');
        set_config('crlm_emailadmins', $config->crlm_emailadmins, 'auth/crlm');
        return true;
    }

    /**
     * Returns whether or not the captcha element is enabled, and the admin settings fulfil its requirements.
     * @abstract Implement in child classes
     * @return bool
     */
    function is_captcha_enabled() {
        return false;
    }

    /**
     * Replace the "notice" function so that we can keep our form.
     *
     */
    function notice ($message, $link='', $course=NULL) {
        global $CFG, $SITE, $THEME, $COURSE;

        if (defined('FULLME') && FULLME == 'cron') {
            // notices in cron should be mtrace'd.
            mtrace($message);
            die;
        }

        if (! defined('HEADER_PRINTED')) {
            //header not yet printed
            print_header(get_string('notice'));
        } else {
            print_container_end_all(false, $THEME->open_header_containers);
        }

        print_box($message, 'generalbox', 'notice');
        print_continue($link);

        if (empty($course)) {
            print_footer($COURSE);
        } else {
            print_footer($course);
        }
        exit;
    }
}

?>
