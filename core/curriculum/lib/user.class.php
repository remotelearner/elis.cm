<?php
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

require_once CURMAN_DIRLOCATION . '/lib/datarecord.class.php';
require_once CURMAN_DIRLOCATION . '/lib/cluster.class.php';
require_once CURMAN_DIRLOCATION . '/lib/usercluster.class.php';
require_once CURMAN_DIRLOCATION . '/lib/clusterassignment.class.php';
require_once CURMAN_DIRLOCATION . '/lib/cmclass.class.php';
require_once CURMAN_DIRLOCATION . '/lib/curriculumcourse.class.php';
require_once CURMAN_DIRLOCATION . '/lib/student.class.php';
require_once CURMAN_DIRLOCATION . '/form/userform.class.php';
require_once CURMAN_DIRLOCATION . '/lib/customfield.class.php';
require_once $CFG->dirroot . '/user/filters/text.php';
require_once $CFG->dirroot . '/user/filters/date.php';
require_once $CFG->dirroot . '/user/filters/select.php';
require_once $CFG->dirroot . '/user/filters/simpleselect.php';
require_once $CFG->dirroot . '/user/filters/courserole.php';
require_once $CFG->dirroot . '/user/filters/globalrole.php';
require_once $CFG->dirroot . '/user/filters/profilefield.php';
require_once $CFG->dirroot . '/user/filters/yesno.php';
require_once $CFG->dirroot . '/user/filters/user_filter_forms.php';
require_once $CFG->dirroot . '/user/profile/lib.php';


define ('USRTABLE', 'crlm_user');
define ('CLSENROLTABLE', 'crlm_class_enrolment');
define ('WATLSTTABLE', 'crlm_wait_list');

class user extends datarecord {

    var $verbose_name = 'user';

    /*
      var $id;
      var $idnumber;
      var $username;
      var $password;
      var $firstname;
      var $lastname;
      var $mi;
      var $email;
      var $email2;
      var $address;
      var $address2;
      var $city;
      var $state;
      var $country;
      var $phone;
      var $phone2;
      var $fax;
      var $postalcode;
      var $birthdate;
      var $gender;
      var $language;
      var $transfercredits;
      var $comments;
      var $notes;
      var $contactemail;
      var $timcreated;
      var $timeapproved;
      var $timemodified;
      var $inactive;

      var $_dbloaded;    // BOOLEAN - True if loaded from database.
    */

    /**
     * Contructor.
     *
     * @param $userdata int/object/array The data id of a data record or data elements to load manually.
     *
     */
    function user($userdata=false) {
        parent::datarecord();

        $this->set_table(USRTABLE);
        $this->add_property('id', 'int');
        $this->add_property('idnumber', 'string', true);
        $this->add_property('username', 'string', true);
        $this->add_property('password', 'string', true);
        $this->add_property('firstname', 'string', true);
        $this->add_property('lastname', 'string', true);
        $this->add_property('mi', 'string');
        $this->add_property('email', 'string', true);
        $this->add_property('email2', 'string');
        $this->add_property('address', 'string');
        $this->add_property('address2', 'string');
        $this->add_property('city', 'string');
        $this->add_property('state', 'string');
        $this->add_property('country', 'string', true);
        $this->add_property('phone', 'string');
        $this->add_property('phone2', 'string');
        $this->add_property('fax', 'string');
        $this->add_property('postalcode', 'string');
        $this->add_property('birthdate', 'string');
        $this->add_property('gender', 'string');
        $this->add_property('language', 'string');
        $this->add_property('transfercredits', 'string');
        $this->add_property('comments', 'string');
        $this->add_property('notes', 'string');
        $this->add_property('timecreated', 'int');
        $this->add_property('timeapproved', 'int');
        $this->add_property('timemodified', 'int');
        $this->add_property('inactive', 'int');

        if (is_numeric($userdata) || is_string($userdata)) {
            $this->data_load_record($userdata);
        } else if (is_array($userdata)) {
            $this->data_load_array($userdata);
        } else if (is_object($userdata)) {
            $this->data_load_array(get_object_vars($userdata));
        }

        if (!empty($this->id)) {
            /// Load any other data we may want that is associated with the id number...
            if ($clusters = cluster_get_user_clusters($this->id)) {
                $this->load_cluster_info($clusters);
            }

            // custom fields
            $level = context_level_base::get_custom_context_level('user', 'block_curr_admin');
            if ($level) {
                $fielddata = field_data::get_for_context(get_context_instance($level,$this->id));
                $fielddata = $fielddata ? $fielddata : array();
                foreach ($fielddata as $name => $value) {
                    $this->{"field_{$name}"} = $value;
                }
            }
        }

        // TODO: move this to accessors (set or get) so that birthdate and birthday/month/year are always in sync
        if(isset($this->birthdate)) {
            $birthdateparts = explode('/', $this->birthdate);
            if (!empty($birthdateparts[1])) {
                $this->birthday = $birthdateparts[2];
                $this->birthmonth = $birthdateparts[1];
                $this->birthyear = $birthdateparts[0];
            } else {
                $this->birthday = 0;
                $this->birthmonth = 0;
                $this->birthyear = 0;
            }
        }
    }

    public function delete () {
        global $CFG;
        $result = false;
        $muser = cm_get_moodleuserid($this->id);

        if(empty($muser) || !is_primary_admin($muser)) {
            $level = context_level_base::get_custom_context_level('user', 'block_curr_admin');
            $result = attendance::delete_for_user($this->id);
            $result = $result && curriculumstudent::delete_for_user($this->id);
            $result = $result && instructor::delete_for_user($this->id);
            $result = $result && student::delete_for_user($this->id);
            $result = $result && student_grade::delete_for_user($this->id);
            $result = $result && usertrack::delete_for_user($this->id);
            $result = $result && usercluster::delete_for_user($this->id);
            $result = $result && clusterassignment::delete_for_user($this->id);
            $result = $result && waitlist::delete_for_user($this->id);
            $result = $result && delete_context($level,$this->id);

            // Delete Moodle user.
            if ($muser = get_record('user', 'idnumber', $this->idnumber, 'mnethostid', $CFG->mnet_localhost_id, 'deleted', 0)) {
                $result = $result && delete_user($muser);
            }

            $result = $result && parent::delete();
        }

        return $result;
    }

    public function set_from_data($data) {
        // Process non-direct elements:
        $this->set_date('birthdate', $data->birthyear, $data->birthmonth, $data->birthday);

        if (!empty($data->newpassword)) {
            $this->change_password($data->newpassword);
        }

        if(!empty($data->id_same_user)) {
            $data->username = $data->idnumber;
        }

        $fields = field::get_for_context_level(context_level_base::get_custom_context_level('user', 'block_curr_admin'));
        $fields = $fields ? $fields : array();
        foreach ($fields as $field) {
            $fieldname = "field_{$field->shortname}";
            if (isset($data->$fieldname)) {
                $this->$fieldname = $data->$fieldname;
            }
        }

        $this->data_load_array(get_object_vars($data));
    }

    public function to_string() {
        return cm_fullname($this);
    }

    public function get_country() {
        $countries = cm_get_list_of_countries();

        return isset($countries[$this->country]) ? $countries[$this->country] : '';
    }

    function get_add_form($form) {
        require_once CURMAN_DIRLOCATION . '/form/userform.class.php';

        return new addform($form);
    }

    function registration_form_html() {
        $regform = new registrationform();
        $regform->set_data($this);

        ob_start();
        $regform->display();
        $output = ob_get_contents();
        ob_end_clean();

        return $output;
    }

    function user_profile_html($returnurl = '', $editurl = '') {
        $returnurl = empty($returnurl) ? 'index.php?s=usr&amp;section=users' : $returnurl;
        $editurl   = empty($editurl) ? 'index.php?s=usr&amp;section=users&amp;action=edit&amp;userid=' . $this->id : $editurl;

        $systemcontext = get_context_instance(CONTEXT_SYSTEM);

        $output = '';

        $output .= '<div class="mform"><fieldset id="main" class="clearfix">
                    <legend class="ftoggler">' . fullname($this) . '</legend>
                    <div class="fcontainer">';

        $output .= '<div class="fitem"><div class="fitemtitle">' . get_string('idnumber') . ':</div>' .
            '<div class="felement">' . $this->idnumber . '</div></div>';
        $output .= '<div class="fitem"><div class="fitemtitle">' . get_string('username') . ':</div>' .
            '<div class="felement">' . $this->username . '</div></div>';
        $output .= '<div class="fitem"><div class="fitemtitle">' . get_string('timecreated', 'block_curr_admin') . ':</div>' .
            '<div class="felement">' . (!empty($this->timecreated) ? cm_timestamp_to_date($this->timecreated) : '-') . '</div></div>';
        $output .= '<div class="fitem"><div class="fitemtitle">' . get_string('userfirstname', 'block_curr_admin') . ':</div>' .
            '<div class="felement">' . $this->firstname . '</div></div>';
        $output .= '<div class="fitem"><div class="fitemtitle">' . get_string('userlastname', 'block_curr_admin') . ':</div>' .
            '<div class="felement">' . $this->lastname . '</div></div>';
        /*
          $output .= '<div class="fitem"><div class="fitemtitle">' . get_string('usermi', 'block_curr_admin') . ':</div>' .
          '<div class="felement">' . $this->mi . '</div></div>';
        */
        $output .= '<div class="fitem"><div class="fitemtitle">' . get_string('email') . ':</div>' .
            '<div class="felement">' . $this->email . '</div></div>';
        /*
          $output .= '<div class="fitem"><div class="fitemtitle">' . get_string('email2', 'block_curr_admin') . ':</div>' .
          '<div class="felement">' . $this->email2 . '</div></div>';
        */
        $output .= '<div class="fitem"><div class="fitemtitle">' . get_string('useraddress', 'block_curr_admin') . ':</div>' .
            '<div class="felement">' . $this->address . '</div></div>';
        /*
          $output .= '<div class="fitem"><div class="fitemtitle">' . get_string('useraddress2', 'block_curr_admin') . ':</div>' .
          '<div class="felement">' . $this->address2 . '</div></div>';
        */
        $output .= '<div class="fitem"><div class="fitemtitle">' . get_string('usercity', 'block_curr_admin') . ':</div>' .
            '<div class="felement">' . $this->city . '</div></div>';
        /*
          $output .= '<div class="fitem"><div class="fitemtitle">' . get_string('userstate', 'block_curr_admin') . ':</div>' .
          '<div class="felement">' . $this->state . '</div></div>';
        */
        $output .= '<div class="fitem"><div class="fitemtitle">' . get_string('country') . ':</div>' .
            '<div class="felement">' . $this->country . '</div></div>';
        /*
          $output .= '<div class="fitem"><div class="fitemtitle">' . get_string('userpostalcode', 'block_curr_admin') . ':</div>' .
          '<div class="felement">' . $this->postalcode . '</div></div>';
        */
        $output .= '<div class="fitem"><div class="fitemtitle">' . get_string('phone') . ':</div>' .
            '<div class="felement">' . $this->phone . '</div></div>';
        $output .= '<div class="fitem"><div class="fitemtitle">' . get_string('phone2', 'block_curr_admin') . ':</div>' .
            '<div class="felement">' . $this->phone2 . '</div></div>';
        /*
          $output .= '<div class="fitem"><div class="fitemtitle">' . get_string('fax', 'block_curr_admin') . ':</div>' .
          '<div class="felement">' . $this->fax . '</div></div>';
          $output .= '<div class="fitem"><div class="fitemtitle">' . get_string('userbirthdate', 'block_curr_admin') . ':</div>' .
          '<div class="felement">' . $this->birthdate . '</div></div>';
          $output .= '<div class="fitem"><div class="fitemtitle">' . get_string('usergender', 'block_curr_admin') . ':</div>' .
          '<div class="felement">' . $this->gender . '</div></div>';
          $output .= '<div class="fitem"><div class="fitemtitle">' . get_string('preferredlanguage', 'block_curr_admin') . ':</div>' .
          '<div class="felement">' . $this->language . '</div></div>';
        */

        $output .= '<div class="fitem"><div class="fitemtitle">' . get_string('usercluster', 'block_curr_admin') . ':</div>';
        $output .= '<div class="felement">';
        if (isset($this->cluster))
        {
            $clusternames = array();
            foreach ($this->cluster as $cluster) {
                $clusternames[] =  $cluster->name;
            }
            $output .= implode(', ', $clusternames);
        }
        $output .= '</div></div>';
        /*
          $output .= '<div class="fitem"><div class="fitemtitle">' . get_string('transfercredits', 'block_curr_admin') . ':</div>' .
          '<div class="felement">' . $this->transfercredits . '</div></div>';
          $output .= '<div class="fitem"><div class="fitemtitle">' . get_string('usercomments', 'block_curr_admin') . ':</div>' .
          '<div class="felement">' . $this->comments . '</div></div>';
          $output .= '<div class="fitem"><div class="fitemtitle">' . get_string('notes', 'block_curr_admin') . ':</div>' .
          '<div class="felement">' . $this->notes . '</div></div>';
        */
        $output .= '<div class="fitem"><div class="fitemtitle">' . get_string('createdtime', 'block_curr_admin') . ':</div>' .
            '<div class="felement">' . (empty($this->timecreated) ? '-' : userdate($this->timecreated)) . '</div></div>';

        $output .= '<div class="fitem"><div class="fitemtitle">' . get_string('inactive', 'block_curr_admin') . ':</div>' .
            '<div class="felement">' . ($this->inactive == 1 ? 'Yes' : 'No') . '</div></div>';

        $output .= '<br />';
        $output .= '<div class="fitem"><div class="fitemtitle"><a href="'.$returnurl.'">' . get_string('back') . '</div>' .
            '<div class="felement"><a href="'.$editurl.'">' . get_string('edit') . '</div></div>';

        $output .= '</div></fieldset></div>';
        return $output;
    }

    /**
     * Function to generate a new password.
     *
     */
    function generate_password() {
        $this->password = generate_password();
        return $this->password;
    }

    /**
     * Function to send out registration emails.
     *
     */
    function send_confirmation_email($fromadmin, $toadmins) {
        global $CFG;

        $a = new Object();
        $a->fullname = fullname($this);
        $a->username = $this->username;
        $a->password = $this->password;

        $messagehtml = get_string('welcomeemail', 'block_curr_admin', $a);
        $messagetext = html_to_text($messagehtml);

        $subject = get_string('emailwelcomesubject', 'block_curr_admin');
        $this->mailformat = 1;  // Always send HTML version as well

        email_to_user($this, $fromadmin, $subject, $messagetext, $messagehtml);

        $messagetext = "New registration:\n\n";

        $countries = cm_get_list_of_countries();

        $objectvars = array('username', 'password', 'firstname', 'lastname', 'email', 'email2', 'address', 'address2',
                            'city', 'state', 'country', 'postalcode', 'birthdate', 'gender', 'language', 'comments');
        foreach ($objectvars as $varname) {
            if ($varname == 'country') {
                if (isset($countries[$this->$varname])) {
                    $messagetext .= "$varname: {$countries[$this->$varname]}\n";
                } else {
                    $messagetext .= "$varname: {$this->$varname}\n";
                }
            } else {
                $messagetext .= "$varname: {$this->$varname}\n";
            }
        }

        $toaddresses = explode(',', $toadmins);
        $mail =& get_mailer();
        $mail->Sender = $fromadmin;
        $mail->From = $fromadmin;
        foreach ($toaddresses as $address) {
            $mail->AddAddress($address);
        }
        $mail->Subject = get_string('new_registration', 'block_curr_admin') .
            $mail->IsHTML(false);
        $mail->Body =  "\n$messagetext\n";

        return ($mail->Send());
    }

    function assign_curriculum($curdata) {
        global $CFG, $CURMAN;
        require_once($CFG->dirroot.'/curriculum/lib/curriculum.class.php');
        require_once($CFG->dirroot.'/curriculum/lib/curriculumstudent.class.php');

        if (is_object($curdata)) {
            if (get_class($curdata) == 'curriculum') {
                $curid = $curdata->id;
            } else {
                /// Don't know what to do.
                return false;
            }
        } else if (is_array($curdata)) {
            $select = '';
            foreach ($curdata as $field => $value) {
                if (!empty($select)) {
                    $select .= ' AND ';
                }
                $select .= '('.$field.' = \''.$value.'\')';
            }
            if (!($curid = $CURMAN->db->get_field_select(CURTABLE, 'id', $select))) {
                /// Don't know what to do.
                return false;
            }
        }

        if (!$CURMAN->db->record_exists(CURASSTABLE, 'userid', $this->id, 'curriculumid', $curid)) {
            $student = new curriculumstudent();
            $student->userid        = $this->id;
            $student->curriculumid  = $curid;
            return $student->data_insert_record();
        }
        return true;
    }

    function set_date($field, $year, $month, $day) {
        if ($field == '') {
            return '';
        }
        if (empty($year) || empty($month) || empty($day)) {
            return '';
        }
        $this->$field = sprintf('%04d/%02d/%02d', $year, $month, $day);
    }

    /**
     * Function to load assigned cluster information into the user object.
     * @param int | array The cluster information as data id or cluster information array.
     *
     */
    function load_cluster_info($clusterinfo) {
        if (is_int($clusterinfo) || is_numeric($clusterinfo)) {
            if (!isset($this->cluster)) {
                $this->cluster = array();
            }
            if (!($ucid = get_field(CLSTUSERTABLE, 'id', 'userid', $this->id, 'clusterid', $clusterinfo))) {
                $ucid = 0;
            }
            $this->cluster[$ucid] = new cluster($clusterinfo);
        } else if (is_array($clusterinfo)) {
            foreach ($clusterinfo as $ucid => $usercluster) {
                if (!isset($this->cluster)) {
                    $this->cluster = array();
                }
                $this->cluster[$ucid] = new cluster($usercluster->clusterid);
            }
        }
    }

    /**
     * Function to replace assigned cluster information into the user object.
     * @param int | array The cluster information as data id or cluster information array.
     *
     */
    function replace_cluster_info($clusterinfo) {
        unset($this->cluster);
        $this->load_cluster_info($clusterinfo);
    }

    function add() {
        global $CURMAN;

        /// **** Should really call all appropriate classes with their specific "add" functions

        $status = !$this->duplicate_check();

        $status = $status && $this->data_insert_record();

        /// Synchronize Moodle data with this data.
        $status = $status && $this->synchronize_moodle_user(true, true);

        $status = $status && $this->save_field_data();

        return $status;
    }

    function update() {
        global $CURMAN;

        /// **** Should really call all appropriate classes with their specific "add" functions
        $status = $this->duplicate_check();;

        $status = $status && $this->data_update_record();
        $status = $status && $this->save_field_data();

        /// Synchronize Moodle data with this data.
        $status = $status && $this->synchronize_moodle_user();

        $status = $status && $this->save_field_data();

        return $status;
    }

    function save_field_data() {
        static $loopdetect;

        if(!empty($loopdetect)) {
            return true;
        }

        field_data::set_for_context_from_datarecord('user', $this);

        $loopdetect = true;
        events_trigger('user_updated', cm_get_moodleuser($this->id));
        $loopdetect = false;

        return true;
    }

    /**
     * This function should change the password for the CM user.
     * It should treat it properly according to the text/HASH settings.
     *
     */
    function change_password($password, $setnow=false) {
        global $CURMAN;
        if (!empty($CURMAN->passwordnothashed)) {
            $this->password = $password;
        } else {
            $this->password = hash_internal_user_password($password);
        }

        if ($setnow) {
            $CURMAN->db->set_field($this->table, 'password', $this->password, 'id', $this->id);
        }
    }

    /////////////////////////////////////////////////////////////////////
    //                                                                 //
    //  DATA FUNCTIONS:                                                //
    //                                                                 //
    /////////////////////////////////////////////////////////////////////

    /**
     * Check for a duplicate record when doing an insert.
     *
     * @param boolean $record true if a duplicate is found false otherwise
     * note: output is expected and treated as boolean please ensure return values are boolean
     */
    function duplicate_check($record=null) {
        global $CURMAN;

        if(empty($record)) {
            $record = $this;
        }

        /// Check for valid idnumber - it can't already exist in the user table.
        if ($CURMAN->db->record_exists($this->table, 'idnumber', $this->idnumber)) {
            return true;
        }

        return false;
    }

    /**
     * Function to load the object with data from passed parameter.
     *
     * @param $data array Array of properties and values.
     *
     */
    function data_load_array($data) {

        if (!parent::data_load_array($data)) {
            return false;
        }

        if (!empty($data['cluster'])) {
            $this->load_cluster_info($data['cluster']);
        }

        return true;
    }

    /**
     * intended to add processing to fields before adding them to the object if needed
     * so there will not be any more database errors
     * this should be in the main data object
     * @param string $name the name of the property being set
     * @param mixed $value the value the property is being set to
     */
    function __call($name, $value) {
        if(!empty($this->properties[$name])) {
            $this->$name = current($value);
        }
    }

    function birthdate($value) {
        if(is_numeric($value)) {
            $timestamp = $value;
        } else {
            $timestamp = strtotime($value);
        }

        $this->birthdate = $timestamp;
    }

    function gender($value) {
        $sex = substr($value, 0,1);

        if(strcasecmp($sex, 'm') && strcasecmp($sex, 'f')) {
            $sex = '';
        }

        $this->gender = $sex;
    }

    /**
     * Function to synchronize the curriculum data with the Moodle data.
     *
     * @param boolean $tomoodle Optional direction to synchronize the data.
     *
     */
    function synchronize_moodle_user($tomoodle = true, $createnew = false) {
        global $CFG, $CURMAN;

        static $mu_loop_detect = array();

        // Create a new Moodle user record to update with.

        if (!($muserid = get_field('user', 'id', 'idnumber', $this->idnumber, 'mnethostid', $CFG->mnet_localhost_id, 'deleted', 0)) && !$createnew) {
            return false;
        }

        if ($tomoodle) {
            if ($createnew && !$muserid) {
                /// Add a new user
                $record                 = new stdClass();
                $record->idnumber       = $this->idnumber;
                $record->username       = $this->username;
                /// Check if already hashed... (not active now)
                if (!empty($CURMAN->passwordnothashed)) {
                    $record->password   = hash_internal_user_password($this->password);
                } else {
                    $record->password   = $this->password;
                }
                $record->firstname      = $this->firstname;
                $record->lastname       = $this->lastname;
                $record->email          = $this->email;
                $record->confirmed      = 1;
                $record->mnethostid     = $CFG->mnet_localhost_id;
                $record->address        = $this->address;
                $record->city           = $this->city;
                $record->country        = $this->country;
                $record->timemodified   = time();
                $record->id = insert_record('user', $record);
            } else if ($muserid) {
                /// Update an existing user
                $record                 = new stdClass();
                $record->id             = $muserid;
                $record->idnumber       = $this->idnumber;
                $record->username       = $this->username;
                /// Check if already hashed... (not active now)
                if (!empty($CURMAN->passwordnothashed)) {
                    $record->password   = hash_internal_user_password($this->password);
                } else {
                    $record->password   = $this->password;
                }
                $record->firstname      = $this->firstname;
                $record->lastname       = $this->lastname;
                $record->email          = $this->email;
                $record->address        = $this->address;
                $record->city           = $this->city;
                $record->country        = $this->country;
                $record->timemodified   = time();
                update_record('user', $record);
            } else {
                return true;
            }

            // avoid update loops
            if (isset($mu_loop_detect[$this->id])) {
                return $record->id;
            }
            $mu_loop_detect[$this->id] = true;

            // synchronize profile fields
            $origrec = clone($record);
            profile_load_data($origrec);
            $fields = field::get_for_context_level(context_level_base::get_custom_context_level('user', 'block_curr_admin'));
            $mfields = $CURMAN->db->get_records('user_info_field', '', '', '', 'shortname');
            $fields = $fields ? $fields : array();
            $changed = false;
            require_once (CURMAN_DIRLOCATION . '/plugins/moodle_profile/custom_fields.php');
            foreach ($fields as $field) {
                $field = new field($field);
                if (isset($field->owners['moodle_profile']) && $field->owners['moodle_profile']->exclude == cm_moodle_profile::sync_to_moodle && isset($mfields[$field->shortname])) {
                    $shortname = $field->shortname;
                    $fieldname = "field_$shortname";
                    $mfieldname = "profile_$fieldname";
                    $mfieldvalue = isset($origrec->$mfieldname) ? $origrec->$mfieldname : null;
                    if ($mfieldvalue != $this->$fieldname) {
                        $record->$mfieldname = $this->$fieldname;
                        $changed = true;
                    }
                }
            }
            profile_save_data(addslashes_recursive($record));

            if ($muserid) {
                if ($changed) {
                    events_trigger('user_updated', $record);
                }
            } else {
                events_trigger('user_created', $record);
            }

            unset($mu_loop_detect[$this->id]);
            return $record->id;
        }
    }

    /**
     * Get a list of the existing instructors for the supplied (or current)
     * class.
     *
     * @uses $CURMAN
     * @param int $cid A class ID (optional).
     * @return array An array of user records.
     */
    function get_users($cid = 0) {
        global $CURMAN;

        if (!$cid) {
            if (!$this->id || !$this->_dbloaded) {
                return array();
            }

            $cid = $this->classid;
        }

        $uids = array();

        if ($users = $CURMAN->db->get_records(USRTABLE, 'classid', $cid)) {
            foreach ($users as $user) {
                $uids[] = $user->userid;
            }
        }

        if (!empty($uids)) {
            $sql = "SELECT idnumber, uname, firstname, lastname
                    FROM " . USRTABLE . "
                    WHERE idnumber IN ( " . implode(', ', $uids) . " )
                    ORDER BY lastname ASC, firstname ASC";

            if ($users = $CURMAN->db->get_records_sql($sql)) {
                foreach ($users as $i => $user) {
                    if (isset($users[$user->userid])) {
                        $user = $users[$user->userid];

                        $user->username  = $user->uname;
                        $user->firstname = $user->firstname;
                        $user->lastname  = $user->lastname;

                        $users[$i] = $user;
                    }
                }
            }
        }

        return $users;
    }

    /**
     * Retrieves a user object given the users idnumber
     * @global <type> $CURMAN
     * @param <type> $idnumber
     * @return <type>
     */
    public static function get_by_idnumber($idnumber, $inactive=null) {
        global $CURMAN;
        $retval = null;

        if(isset($inactive)) {
            $user = $CURMAN->db->get_record(USRTABLE, 'idnumber', $idnumber, 'inactive', $inactive);
        } else {
            $user = $CURMAN->db->get_record(USRTABLE, 'idnumber', $idnumber);
        }

        if(!empty($user)) {
            $retval = new user($user->id);
        }

        return $retval;
    }

    public static function get_by_username($username) {
        global $CURMAN;
        $retval = null;

        $user = $CURMAN->db->get_record(USRTABLE, 'username', $username);

        if(!empty($user)) {
            $retval = new user($user->id);
        }

        return $retval;
    }

    /**
     * Retrieves a list of classes the specified user is currently enrolled in under the specified curriculum.
     * @param $userid ID of the user
     * @param $curid ID of the curriculum
     * @return unknown_type
     */
    static function get_current_classes_in_curriculum($userid, $curid) {
        global $CURMAN;

        $select = 'SELECT curcrs.*, crs.name AS coursename ';
        $tables = 'FROM ' . $CURMAN->db->prefix_table(CURCRSTABLE) . ' curcrs ';
        $join   = 'INNER JOIN ' . $CURMAN->db->prefix_table(CRSTABLE) . ' crs ON curcrs.courseid = crs.id ';
        // Next two are to limit to currently enrolled courses
        $join  .= 'INNER JOIN ' . $CURMAN->db->prefix_table('crlm_class') . ' cls ON cls.courseid = crs.id ';
        $join  .= 'INNER JOIN ' . $CURMAN->db->prefix_table(CLSENROLTABLE) . ' clsenrol ON cls.id = clsenrol.classid ';
        $where  = 'WHERE curcrs.curriculumid = \'' . $curid . '\' ';
        $where .= 'AND clsenrol.userid = \'' . $userid . '\' ';
        $where .= 'AND clsenrol.completestatusid=' . STUSTATUS_NOTCOMPLETE . ' ';
        $sort = 'ORDER BY curcrs.position';

        $sql = $select.$tables.$join.$where.$sort;

        return $CURMAN->db->get_records_sql($sql);
    }

    /**
     * Retrieves a list of classes the specified user is currently enrolled in that don't fall under a
     * curriculum the user is assigned to.
     * @param $userid ID of the user
     * @param $curid ID of the curriculum
     * @return unknown_type
     */
    static function get_non_curriculum_classes($userid) {
        global $CURMAN;

        $select = 'SELECT curcrs.*, crs.name AS coursename, crs.id AS courseid ';
        $tables = 'FROM ' . $CURMAN->db->prefix_table(CLSENROLTABLE) . ' clsenrol ';
        $join   = 'INNER JOIN ' . $CURMAN->db->prefix_table('crlm_class') . ' cls ON cls.id = clsenrol.classid ';
        $join  .= 'INNER JOIN ' . $CURMAN->db->prefix_table('crlm_course') . ' crs ON crs.id = cls.courseid ';
        $join  .= 'LEFT JOIN (SELECT curcrs.courseid FROM ' . $CURMAN->db->prefix_table('crlm_curriculum_course') . ' curcrs ';
        $join  .= 'INNER JOIN ' . $CURMAN->db->prefix_table('crlm_curriculum_assignment') . ' curass ON curass.curriculumid = curcrs.curriculumid AND curass.userid = ' . $userid . ' ) curcrs ON curcrs.courseid = crs.id ';
        $where  = 'WHERE clsenrol.userid = \'' . $userid . '\' AND curcrs.courseid IS null';

        $sql = $select.$tables.$join.$where;

        return $CURMAN->db->get_records_sql($sql);
    }

    /**
     * Retrieves a list of courses that:
     * - Belong to the specified curriculum.
     * - The user is not currently enrolled in.
     * @param $userid ID of the user to retrieve the courses for.
     * @param $curid ID of the curriculum to retrieve the courses for.
     * @return unknown_type
     */
    static function get_user_course_curriculum($userid, $curid) {
        global $CURMAN;


        $select = 'SELECT curcrs.*, crs.name AS coursename, cls.count as classcount, prereq.count as prereqcount, enrol.completestatusid as completionid, waitlist.courseid as waiting ';
        $tables = 'FROM ' . $CURMAN->db->prefix_table(CURCRSTABLE) . ' curcrs ';
        $join   = 'INNER JOIN ' . $CURMAN->db->prefix_table(CRSTABLE) . ' crs ON curcrs.courseid = crs.id ';
        // limit to non-enrolled courses
        $join  .= "LEFT JOIN (SELECT cls.courseid, clsenrol.completestatusid FROM {$CURMAN->db->prefix_table(CLSTABLE)} cls
                  INNER JOIN {$CURMAN->db->prefix_table(CLSENROLTABLE)} clsenrol ON cls.id = clsenrol.classid AND clsenrol.userid = $userid) enrol ON enrol.courseid = crs.id
";
        // limit to courses where user is not on waitlist
        $join  .= "LEFT JOIN (SELECT cls.courseid FROM {$CURMAN->db->prefix_table(CLSTABLE)} cls
                  INNER JOIN  {$CURMAN->db->prefix_table(WATLSTTABLE)} watlst ON cls.id = watlst.classid AND watlst.userid = $userid) waitlist ON waitlist.courseid = crs.id
";
        // count the number of classes for each course
        $curtime = time() - 24*60*60; // enddate is beginning of day
        $join .= "LEFT JOIN (SELECT cls.courseid, COUNT(*) as count
                               FROM {$CURMAN->db->prefix_table(CLSTABLE)} cls
                               WHERE (cls.enddate > $curtime) OR NOT cls.enddate
                           GROUP BY cls.courseid) cls ON cls.courseid = crs.id
";
        $currenttime = time();
        // count the number of unsatisfied prerequisities
        $join .= "LEFT JOIN (SELECT prereq.curriculumcourseid, COUNT(*) as count
                               FROM {$CURMAN->db->prefix_table(CRSPREREQTABLE)} prereq
                         INNER JOIN {$CURMAN->db->prefix_table(CRSTABLE)} crs ON prereq.courseid=crs.id
                          LEFT JOIN (SELECT cls.courseid
                                       FROM {$CURMAN->db->prefix_table(CLSTABLE)} cls
                                       JOIN {$CURMAN->db->prefix_table(STUTABLE)} enrol ON enrol.classid=cls.id
                                      WHERE enrol.completestatusid=".STUSTATUS_PASSED." AND enrol.userid=$userid
                                      AND (cls.enddate > ' . $currenttime . ' OR NOT cls.enddate)) cls ON cls.courseid = crs.id
                              WHERE cls.courseid IS NULL
                           GROUP BY prereq.curriculumcourseid
                            ) prereq ON prereq.curriculumcourseid = curcrs.id
";

        $where  = 'WHERE curcrs.curriculumid = \'' . $curid . '\' ';
//        $where .= 'AND enrol.courseid IS null AND waitlist.courseid IS null';
        $sort = 'ORDER BY curcrs.position';

        $sql = $select.$tables.$join.$where.$sort;

        return $CURMAN->db->get_records_sql($sql);
    }

    /**
     * Retrieves a list of classes the user instructs.
     * @param $userid ID of the user
     * @return unknown_type
     */
    static function get_instructed_classes($userid) {
        global $CURMAN;

        $select = 'SELECT cls.*, crs.name AS coursename ';
        $tables = "FROM {$CURMAN->db->prefix_table('crlm_class')} cls ";
        $join   = 'INNER JOIN ' . $CURMAN->db->prefix_table(CRSTABLE) . ' crs ON cls.courseid = crs.id ';
        $join  .= 'INNER JOIN ' . $CURMAN->db->prefix_table('crlm_class_instructor') . ' clsinstr ON cls.id = clsinstr.classid ';
        $where = 'WHERE clsinstr.userid = \'' . $userid . '\' ';
        $group = 'GROUP BY cls.id ';

        $sql = $select.$tables.$join.$where.$group;

        return $CURMAN->db->get_records_sql($sql);
    }

    function load_profile_fields() {
        global $CURMAN;

        if (!empty($this->uid) && !empty($this->profile_fields)) {
            foreach ($this->profile_fields as $field) {
                $sql = 'SELECT pv.value
                        FROM '  . $CURMAN->db->prefix_table('profile_values') . ' pv
                        INNER JOIN ' . $CURMAN->db->prefix_table('profile_fields') . ' pf ON pf.fid = pv.fid
                        WHERE pf.name = \'' . $field . '\'
                        AND pv.uid = ' . $this->uid;

                $this->$field = $CURMAN->db->get_field_sql($sql);
            }
        }
    }


/**
 * Get the user dashboard report view.
 *
 * @uses $CFG, $CURMAN
 * @param none
 * @return string The HTML for the dashboard report.
 */
    function get_dashboard() {
        global $CFG, $CURMAN;

        require_once CURMAN_DIRLOCATION . '/lib/curriculumstudent.class.php';

        //needed for AJAX calls
        require_js(array('yui_yahoo',
                         'yui_dom',
                         'yui_event',
                         'yui_connection',
                         "{$CFG->wwwroot}/curriculum/js/util.js",
                         "{$CFG->wwwroot}/curriculum/js/dashboard.js"),true);

        if (optional_param('tab','',PARAM_CLEAN) == 'archivedlp') {
            $tab = 'archivedlp';
            $show_archived = 1;
        } else {
            $tab = 'currentlp';
            $show_archived = 0;
        }

        $content = '';
        $archive_var = '_elis_curriculum_archive';

        $totalcourses    = 0;
        $completecourses = 0;

        $curriculas = array();
        $classids = array();

        if ($usercurs = curriculumstudent::get_curricula($this->id)) {
            foreach ($usercurs as $usercur) {
                // Check if this curricula is set as archived and whether we want to display it
                $crlm_context = get_context_instance(context_level_base::get_custom_context_level('curriculum', 'block_curr_admin'), $usercur->curid);
                $data_array = field_data::get_for_context_and_field($crlm_context, $archive_var);
                $crlm_archived = 0;
                if (is_array($data_array) && !empty($data_array)) {
                    foreach ($data_array as $data_key=>$data_obj) {
                        $crlm_archived = (!empty($data_obj->data))
                                       ? 1
                                       : 0;
                    }
                }

                if ($show_archived == $crlm_archived) {

                    $curriculas[$usercur->curid]['id'] = $usercur->curid;
                    $curriculas[$usercur->curid]['name'] = $usercur->name;
                    $data = array();

                    if ($courses = curriculumcourse_get_listing($usercur->curid, 'curcrs.position, crs.name', 'ASC')) {
                        foreach ($courses as $course) {
                            $totalcourses++;

                            $course_obj = new course($course->courseid);
                            $coursedesc = $course_obj->syllabus;

                            if ($classdata = student_get_class_from_course($course->courseid, $this->id)) {
                                if (!in_array($classdata->id, $classids)) {
                                    $classids[] = $classdata->id;
                                }

                                if ($classdata->completestatusid == STUSTATUS_PASSED) {
                                    $completecourses++;
                                }

                                if ($mdlcrs = moodle_get_course($classdata->id)) {
                                    $coursename = '<a href="' . $CFG->wwwroot . '/course/view.php?id=' .
                                                  $mdlcrs . '">' . $course->coursename . '</a>';
                                } else {
                                    $coursename = $course->coursename;
                                }

                                $data[] = array(
                                    $coursename,
                                    $coursedesc,
                                    $classdata->grade,
                                    $classdata->completestatusid == STUSTATUS_PASSED ? get_string('yes') : get_string('no'),
                                    $classdata->completestatusid == STUSTATUS_PASSED && !empty($classdata->completetime) ?
                                        date('M j, Y', $classdata->completetime) : get_string('na','block_curr_admin')
                                );
                            } else {
                                $data[] = array(
                                    $course->coursename,
                                    $coursedesc,
                                    0,
                                    get_string('no'),
                                    get_string('na','block_curr_admin')
                                );
                            }
                        }
                    }

                    $curriculas[$usercur->curid]['data'] = $data;

                } else {

                    // Keep note of the classid's regardless if set archived or not for later use in determining non-curricula courses
                    if ($courses = curriculumcourse_get_listing($usercur->curid, 'curcrs.position, crs.name', 'ASC')) {
                        foreach ($courses as $course) {
                            if ($classdata = student_get_class_from_course($course->courseid, $this->id)) {
                                if (!in_array($classdata->id, $classids)) {
                                    $classids[] = $classdata->id;
                                }
                            }
                        }
                    }

                }
            }
        }

        // Show different css for IE below version 8
        if (check_browser_version('MSIE',7.0) && !check_browser_version('MSIE',8.0)) {
            // IEs that are lower than version 8 do not get the float because it messes up the tabs at the top of the page for some reason
            $float_style = 'text-align:right;';
        } else {
            // Sane browsers get the float tag
            $float_style = 'text-align:right; float:right;';
        }

        // Tab header
        $field_exists = field::get_for_context_level_with_name('curriculum', $archive_var);
        if (!empty($field_exists)) {
            $tabrow = array();
            $tabrow[] = new tabobject('currentlp', $CFG->wwwroot.'/curriculum/index.php?tab=currentlp',
                                      get_string('tab_current_learning_plans','block_curr_admin'));
            $tabrow[] = new tabobject('archivedlp', $CFG->wwwroot.'/curriculum/index.php?tab=archivedlp',
                                      get_string('tab_archived_learning_plans','block_curr_admin'));
            $tabrows = array($tabrow);
            print_tabs($tabrows, $tab);
        }

        $content .= print_heading_block(get_string('learningplanwelcome', 'block_curr_admin', fullname($this)), '', true);

        if ($totalcourses === 0) {
            $blank_lang = ($tab == 'archivedlp') ? 'noarchivedplan' : 'nolearningplan';
            $content .= '<br /><center>' . get_string($blank_lang, 'block_curr_admin') . '</center>';
        }

        // Load the user preferences for hide/show button states
        if ($collapsed = get_user_preferences('crlm_learningplan_collapsed_curricula')) {
            $collapsed_array = explode(',',$collapsed);
        } else {
            $collapsed = '';
            $collapsed_array = array();
        }

        $content .= '<input type="hidden" name="collapsed" id="collapsed" value="' . $collapsed . '">';

        if (!empty($curriculas)) {
            foreach ($curriculas as $curricula) {

                $table = new stdClass;
                $table->head = array(
                    get_string('class', 'block_curr_admin'),
                    get_string('description', 'block_curr_admin'),
                    get_string('score', 'block_curr_admin'),
                    get_string('completed_label', 'block_curr_admin'),
                    get_string('date', 'block_curr_admin')
                );
                $table->data = $curricula['data'];

                $curricula_name = empty($CURMAN->config->disablecoursecatalog)
                                ? ('<a href="index.php?s=crscat&section=curr&showcurid=' . $curricula['id'] . '">' . $curricula['name'] . '</a>')
                                : $curricula['name'];

                $header_curr_name = get_string('learningplanname', 'block_curr_admin', $curricula_name);
                if (in_array($curricula['id'],$collapsed_array)) {
                    $button_label = get_string('showcourses','block_curr_admin');
                    $extra_class = ' hide';
                } else {
                    $button_label = get_string('hidecourses','block_curr_admin');
                    $extra_class = '';
                }
                $heading = '<div class="clearfix"></div>'
                         . '<div style="' . $float_style . '">'
                         . '<script id="curriculum' . $curricula['id'] . 'script" type="text/javascript">toggleVisibleInitWithState("curriculum'
                         . $curricula['id'] . 'script", "curriculum' . $curricula['id'] . 'button", "'
                         . $button_label . '", "' . get_string('hidecourses','block_curr_admin') . '", "'
                         . get_string('showcourses','block_curr_admin') . '", "curriculum-'
                         . $curricula['id'] . '");</script></div>' . $header_curr_name;

                $content .= '<div class="dashboard_curricula_block">';
                $content .= print_heading($heading, 'left', 2, 'main', true);
                $content .= '<div id="curriculum-' . $curricula['id'] . '" class="yui-skin-sam ' . $extra_class . '">';
                $content .= print_table($table, true);
                $content .= '</div>';
                $content .= '</div>';
            }
        }

        /// Completed non-curricula course data
        if ($tab != 'archivedlp') {
            if (!empty($classids)) {
                $sql = "SELECT stu.id, crs.name as coursename, stu.completetime, stu.grade, stu.completestatusid
                        FROM " . $CURMAN->db->prefix_table(STUTABLE) . " stu
                        INNER JOIN " . $CURMAN->db->prefix_table(CLSTABLE) . " cls ON cls.id = stu.classid
                        INNER JOIN " . $CURMAN->db->prefix_table(CRSTABLE) . " crs ON crs.id = cls.courseid
                        WHERE userid = {$this->id}
                        AND classid " . (count($classids) == 1 ? "!= " . current($classids) :
                        "NOT IN (" . implode(", ", $classids) . ")") . "
                        ORDER BY crs.name ASC, stu.completetime ASC";
            } else {
                $sql = "SELECT stu.id, crs.name as coursename, stu.completetime, stu.grade, stu.completestatusid
                        FROM " . $CURMAN->db->prefix_table(STUTABLE) . " stu
                        INNER JOIN " . $CURMAN->db->prefix_table(CLSTABLE) . " cls ON cls.id = stu.classid
                        INNER JOIN " . $CURMAN->db->prefix_table(CRSTABLE) . " crs ON crs.id = cls.courseid
                        WHERE userid = {$this->id}
                        ORDER BY crs.name ASC, stu.completetime ASC";
            }

            if ($classes = get_records_sql($sql)) {
                $table = new stdClass;
                $table->head = array(
                    get_string('class', 'block_curr_admin'),
                    get_string('score', 'block_curr_admin'),
                    get_string('completed_label', 'block_curr_admin'),
                    get_string('date', 'block_curr_admin')
                );

                $table->data = array();

                foreach ($classes as $class) {
                    if ($mdlcrs = moodle_get_course($class->id)) {
                        $coursename = '<a href="' . $CFG->wwwroot . '/course/view.php?id=' .
                                      $mdlcrs . '">' . $class->coursename . '</a>';
                    } else {
                        $coursename = $class->coursename;
                    }

                    $table->data[] = array(
                        $coursename,
                        $class->grade,
                        $class->completestatusid == STUSTATUS_PASSED ? get_string('yes') : get_string('no'),
                        $class->completestatusid == STUSTATUS_PASSED && !empty($class->completetime) ?
                            date('M j, Y', $class->completetime) : get_string('na','block_curr_admin')
                    );
                }

                $header_curr_name = get_string('noncurriculacourses', 'block_curr_admin');
                if (in_array('na',$collapsed_array)) {
                    $button_label = get_string('showcourses','block_curr_admin');
                    $extra_class = ' hide';
                } else {
                    $button_label = get_string('hidecourses','block_curr_admin');
                    $extra_class = '';
                }
                $heading = '<div class="clearfix"></div>'
                         . '<div style="' . $float_style . '">'
                         . '<script id="noncurriculascript" type="text/javascript">toggleVisibleInitWithState("noncurriculascript", "noncurriculabutton", "'
                         . $button_label . '", "' . get_string('hidecourses','block_curr_admin') . '", "'
                         . get_string('showcourses','block_curr_admin') . '", "curriculum-na");</script></div>'
                         . $header_curr_name;

                $content .= '<div class="dashboard_curricula_block">';
                $content .= print_heading($heading, 'left', 2, 'main', true);
                $content .= '<div id="curriculum-na" class="yui-skin-sam ' . $extra_class . '">';
                $content .= print_table($table, true);
                $content .= '</div>';
                $content .= '</div>';
            }
        }

        return $content;
    }
}


/**
 * "Show inactive users" filter type.
 */
class cm_show_inactive_filter extends user_filter_type {
    /**
     * options for the list values
     */
    var $_options;

    var $_field;

    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     * @param array $options select options
     */
    function cm_show_inactive_filter($name, $label, $advanced, $field, $options) {
        parent::user_filter_type($name, $label, $advanced);
        $this->_field   = $field;
        $this->_options = $options;
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     */
    function setupForm(&$mform) {
        $mform->addElement('select', $this->_name, $this->_label, $this->_options);

        // TODO: add help
        //$mform->setHelpButton($this->_name, array('simpleselect', $this->_label, 'filters'));

        if ($this->_advanced) {
            $mform->setAdvanced($this->_name);
        }
    }

    /**
     * Retrieves data from the form data
     * @param object $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    function check_data($formdata) {
        $field = $this->_name;

        if (array_key_exists($field, $formdata)) {
            if($formdata->$field != 0) {
                return array('value'=>(string)$formdata->$field);
            }
        }

        return false;
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        $retval = $this->_field . ' = 0';
        $value = $data['value'];

        switch($value) {
        case '1':
            $retval = '1=1';

            break;
        case '2':
            $retval = $this->_field . ' = 1';

            break;
        }

        return $retval;
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    function get_label($data) {
        $retval = '';

        if(!empty($data['value'])) {
            if($data['value'] == 1) {
                $retval = get_string('all');
            } else if($data['value'] == 2) {
                $retval = get_string('inactive', 'block_curr_admin');
            }
        }

        return $retval;
    }
}

class cm_custom_field_filter extends user_filter_type {
    /**
     * options for the list values
     */
    var $_field;

    function cm_custom_field_filter($name, $label, $advanced, $field) {
        parent::user_filter_type($name, $label, $advanced);
        $this->_field   = $field;
    }

    function setupForm(&$mform) {
        $fieldname = "field_{$this->_field->shortname}";

        if (isset($this->_field->owners['manual'])) {
            $manual = new field_owner($this->_field->owners['manual']);
            if (isset($manual->param_control)) {
                $control = $manual->param_control;
            }
        }
        if (!isset($control)) {
            $control = 'text';
        }
        require_once(CURMAN_DIRLOCATION . "/plugins/manual/field_controls/{$control}.php");
        call_user_func("{$control}_control_display", $mform, $this->_field, true);

        $mform->setAdvanced($fieldname);
    }

    function check_data($formdata) {
        $field = "field_{$this->_field->shortname}";

        if (!empty($formdata->$field)) {
            return array('value'=>(string)$formdata->$field);
        }

        return false;
    }

    function get_sql_filter($data) {
        global $CURMAN;
        $ilike = sql_ilike();
        $level = context_level_base::get_custom_context_level('user', 'block_curr_admin');
        $sql = "EXISTS (SELECT * FROM {$CURMAN->db->prefix_table($this->_field->data_table())} data
                        JOIN {$CURMAN->db->prefix_table('context')} ctx ON ctx.id = data.contextid
                        WHERE ctx.instanceid = usr.id
                          AND ctx.contextlevel = $level
                          AND data.fieldid = {$this->_field->id}
                          AND data.data $ilike '%{$data['value']}%')";

        return $sql;
    }

    function get_label($data) {
        $retval = '';

        if(!empty($data['value'])) {
            $a = new stdClass;
            $a->label = $this->_field->name;
            $a->value = "\"{$data['value']}\"";
            $a->operator = get_string('contains', 'filters');

            return get_string('textlabel', 'filters', $a);
        }

        return $retval;
    }
}

/**
 * Class that filters users based on an operation and a cluster id
 */
class cm_user_cluster_filter extends user_filter_select {
    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        global $CURMAN;

        $operator = $data['operator'];
        $value    = addslashes($data['value']);
        //reference to the CM user id field
        $field    = $this->_field;

        //determine the necessary operation
        $sql_operator = '';
        switch($operator) {
            case 1: // equal to
                $sql_operator = "IN";
                break;
            case 2: // not equal to
                $sql_operator = "NOT IN";
                break;
            default:
                return '';
        }

        //make sure the main query's user id field belongs to /
        //does not belong to the set of users in the appropriate cluster
        return "$field $sql_operator (
                  SELECT userid
                  FROM
                  {$CURMAN->db->prefix_table(CLSTUSERTABLE)}
                  WHERE clusterid = {$value}
                )";
    }
}

/**
 * Class that filters users based on an operation and a curriculum id
 */
class cm_user_curriculum_filter extends user_filter_select {
    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        global $CURMAN;

        $operator = $data['operator'];
        $value    = addslashes($data['value']);
        //reference to the CM user id field
        $field    = $this->_field;

        //determine the necessary operation
        $sql_operator = '';
        switch($operator) {
            case 1: // equal to
                $sql_operator = "IN";
                break;
            case 2: // not equal to
                $sql_operator = "NOT IN";
                break;
            default:
                return '';
        }

        //make sure the main query's user id field belongs to /
        //does not belong to the set of users in the appropriate curriculum
        return "$field $sql_operator (
                  SELECT userid
                  FROM
                  {$CURMAN->db->prefix_table(CURASSTABLE)}
                  WHERE curriculumid = {$value}
                )";
    }
}

/**
 * Checks a text filter against several fields
 */
class cm_user_filter_text_OR extends user_filter_text {
    var $_fields;

    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $alias an alias to use for the form elements
     * @param array $fields an array of user table field names
     */
    function cm_user_filter_text_OR($name, $label, $advanced, $alias, $fields) {
        parent::user_filter_text($name, $label, $advanced, $alias);
        $this->_fields = $fields;
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        $operator = $data['operator'];
        $value    = addslashes($data['value']);
        $field    = $this->_field;

        if ($operator != 5 and $value === '') {
            return '';
        }

        $ilike = sql_ilike();

        switch($operator) {
            case 0: // contains
                $res = "$ilike '%$value%'"; break;
            case 1: // does not contain
                $res = "NOT $ilike '%$value%'"; break;
            case 2: // equal to
                $res = "$ilike '$value'"; break;
            case 3: // starts with
                $res = "$ilike '$value%'"; break;
            case 4: // ends with
                $res = "$ilike '%$value'"; break;
            case 5: // empty
                $res = "=''"; break;
            default:
                return '';
        }
        $conditions = array();
        foreach ($this->_fields as $field) {
            $conditions[] = $field.' '.$res;
        }
        return '(' . implode(' OR ', $conditions) . ')';
    }
}

/**
 * User filtering wrapper class.
 */
class cm_user_filtering extends user_filtering {
    /**
     * Contructor
     * @param array array of visible user fields
     * @param string base url used for submission/return, null if the same of current page
     * @param array extra page parameters
     */
    function cm_user_filtering($fieldnames=null, $baseurl=null, $extraparams=null) {
        if (empty($fieldnames)) {
            $fieldnames = array(
                'realname' => 0,
                'lastname' => 1,
                'firstname' => 1,
                'idnumber' => 1,
                'email' => 0,
                'city' => 1,
                'country' => 1,
                'username' => 0,
                'language' => 1,
                'clusterid' => 1,
                'curriculumid' => 1,
            	'inactive' => 1,
                );

            $fields = field::get_for_context_level(context_level_base::get_custom_context_level('user', 'block_curr_admin'));
            $fields = $fields ? $fields : array();
            foreach ($fields as $field) {
                $fieldnames["field_{$field->shortname}"] = 1;
            }
        }

        /// Remove filters if missing capability...
        $context = get_context_instance(CONTEXT_SYSTEM);
        if (!has_capability('block/curr_admin:viewreports', $context)) {
            if (has_capability('block/curr_admin:viewgroupreports', $context)) {
                unset($fieldnames['clusterid']);
            }
        }

        parent::user_filtering($fieldnames, $baseurl, $extraparams);
    }

    /**
     * Creates known user filter if present
     *
     * @uses $CURMAN
     * @uses $USER
     * @param string $fieldname
     * @param boolean $advanced
     * @return object filter
     */
    function get_field($fieldname, $advanced) {
        global $CURMAN, $USER;

        switch ($fieldname) {
        case 'username':    return new user_filter_text('username', get_string('username'), $advanced, 'usr.username');
        case 'realname':    return new cm_user_filter_text_OR('realname', get_string('fullname'),
                                                              $advanced, 'fullname',
                                                              array(sql_concat('usr.firstname',"' '","COALESCE(usr.mi, '')","' '",'usr.lastname'),
                                                                    sql_concat('usr.firstname',"' '",'usr.lastname')));
        case 'lastname':    return new user_filter_text('lastname', get_string('lastname'), $advanced, 'usr.lastname');
        case 'firstname':   return new user_filter_text('firstname', get_string('firstname'), $advanced, 'usr.firstname');
        case 'idnumber':    return new user_filter_text('idnumber', get_string('idnumber'), $advanced, 'usr.idnumber');
        case 'email':       return new user_filter_text('email', get_string('email'), $advanced, 'usr.email');

        case 'city':        return new user_filter_text('city', get_string('city'), $advanced, 'usr.city');
        case 'country':     return new user_filter_select('country', get_string('country'), $advanced, 'country', cm_get_list_of_countries(), $USER->country);
        case 'timecreated': return new user_filter_date('timecreated', get_string('createtime', 'block_curr_admin'), $advanced, 'usr.timecreated');

        case 'language':
            return new user_filter_select('language', get_string('preferredlanguage', 'block_curr_admin'), $advanced, 'usr.language', cm_get_list_of_languages());

        case 'clusterid':
            //obtain a mapping of cluster ids to names for all clusters
            $clusters = cm_get_list_of_clusters();
            //use a special filter class to filter users based on clusters
            return new cm_user_cluster_filter('clusterid', get_string('usercluster', 'block_curr_admin'), $advanced, 'usr.id', $clusters);

        case 'curriculumid':
            //obtain a mapping of curriculum ids to names for all curricula
            $choices = curriculum_get_menu();
            //use a special filter class to filter users based on curricula
            return new cm_user_curriculum_filter('curriculumid', get_string('usercurricula', 'block_curr_admin'), $advanced, 'usr.id', $choices);

        case 'inactive':
            $inactive_options = array(get_string('o_active', 'block_curr_admin'), get_string('all'), get_string('o_inactive', 'block_curr_admin'));
            return new cm_show_inactive_filter('inactive', get_string('showinactive', 'block_curr_admin'), $advanced, 'usr.inactive', $inactive_options);


        default:
            if (strncmp($fieldname, 'field_', 6) === 0) {
                $f = substr($fieldname, 6);
                $rec = new field($CURMAN->db->get_record(FIELDTABLE, 'shortname', $f));
                return new cm_custom_field_filter($fieldname, $rec->shortname, $advanced, $rec);
            }
            return null;
        }
    }

    /**
     * Print the add filter form.
     */
    function display_add($return = false) {
        if ($return) {
            return $this->_addform->_form->toHtml();
        } else {
            $this->_addform->display();
        }
    }

    /**
     * Print the active filter form.
     */
    function display_active($return = false) {
        if ($return) {
            return $this->_activeform->_form->toHtml();
        } else {
            $this->_activeform->display();
        }
    }

    /**
     * Returns sql where statement based on active user filters.  Overridden to provide proper
     * 'show inactive' default condition.
     *
     * @param string $extra sql
     * @return string
     */
    function get_sql_filter($extra='') {
        global $SESSION;

        $newextra = '';

        // Include default SQL if inactive filter has not been included in list
        if (empty($SESSION->user_filtering) || !isset($SESSION->user_filtering['inactive']) || !$SESSION->user_filtering['inactive']) {
            $newextra = ($extra ? $extra . ' AND ' : '') . 'inactive=0';
        }

        return parent::get_sql_filter($newextra);
    }
}

/// Non-class supporting functions. (These may be able to replaced by a generic container/listing class)


/**
 * Gets a instructor listing with specific sort and other filters.
 *
 * @param string $sort Field to sort on.
 * @param string $dir Direction of sort.
 * @param int $startrec Record number to start at.
 * @param int $perpage Number of records per page.
 * @param string $namesearch Search string for instructor name.
 * @param string $alpha Start initial of instructor name filter.
 * @return object array Returned records.
 */

function user_get_listing($sort='name', $dir='ASC', $startrec=0, $perpage=0, $namesearch='',
                          $alpha='') {
    global $CURMAN;

    $LIKE     = $CURMAN->db->sql_compare();
    //    $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');
    $FULLNAME = sql_concat('usr.firstname', "' '", 'usr.lastname');

    $select  = 'SELECT usr.*, ' . $FULLNAME . ' AS name ';
    //$select .= ', ' . $FULLNAME . ' as name, u.type as description ';
    $tables  = 'FROM ' . $CURMAN->db->prefix_table(USRTABLE) . ' usr ';
    //$join    = 'LEFT JOIN ' . $CURMAN->db->prefix_table(USRTABLE) . ' u ';
    $join    = '';
    //$on      = 'ON ins.userid = u.idnumber ';
    $on      = '';
    $where   = '';

    if (!empty($namesearch)) {
        $namesearch = trim($namesearch);
        $where .= (!empty($where) ? ' AND ' : '') . "($FULLNAME $LIKE '%$namesearch%') ";
    }

    if (!empty($descsearch)) {
        $descsearch = trim($descsearch);
        $where .= (!empty($where) ? ' AND ' : '') . "(description $LIKE '%$descsearch%') ";
    }

    if ($alpha) {
        $where .= (!empty($where) ? ' AND ' : '') . "($FULLNAME $LIKE '$alpha%') ";
    }

    if (!empty($where)) {
        $where = 'WHERE '.$where.' ';
    }

    if ($sort) {
        $sort = 'ORDER BY '.$sort .' '. $dir.' ';
    }

    if (!empty($perpage)) {
        if ($CURMAN->db->_dbconnection->databaseType == 'postgres7') {
            $limit = 'LIMIT ' . $perpage . ' OFFSET ' . $startrec;
        } else {
            $limit = 'LIMIT '.$startrec.', '.$perpage;
        }
    } else {
        $limit = '';
    }

    $sql = $select.$tables.$join.$on.$where.$sort.$limit;

    return $CURMAN->db->get_records_sql($sql);
}


function user_count_records() {
    global $CURMAN;

    return $CURMAN->db->count_records(USRTABLE);
}

?>
