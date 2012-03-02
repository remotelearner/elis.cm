<?php
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

    require_once $CFG->dirroot . '/curriculum/config.php';

    if(!defined('CURR_ADMIN_DUPLICATE_EMAIL')) {
        define('CURR_ADMIN_DUPLICATE_EMAIL', 'development@remote-learner.net');
    }

/// Contains general functions to be used by curriculum manager. May use functions borrowed from the
/// platform application.

    /**
     * Load up the curriculum management global config values (copied from Moodle).
     *
     */
    function cm_load_config() {
        global $CFG, $CURMAN;

    /// Ensure that the table actually exists before we query records from it.
        require_once $CFG->libdir . '/ddllib.php';

        $table = new XMLDBTable(CMCONFIGTABLE);

        if (!table_exists($table)) {
            return $CURMAN->config;
        }

        if ($configs = get_records(CMCONFIGTABLE)) {
        /// Allow settings in the config.php file to override.
            $localcfg = (array)$CURMAN->config;
            foreach ($configs as $config) {
                if (!isset($localcfg[$config->name])) {
                    $localcfg[$config->name] = $config->value;
                }
            }
            $localcfg = (object)$localcfg;
            return $localcfg;
        } else {
            // preserve $CFG if DB returns nothing or error
            return $CURMAN->config;
        }
    }

    function cm_add_config_defaults() {
        global $CURMAN, $CFG;

        $defaults = array(
            'userdefinedtrack' => 0,
            'time_format_12h' => 0,
            'auto_assign_user_idnumber' => 1,
            'restrict_to_elis_enrolment_plugin' => 0,
            'cluster_groups' => 0,
            'site_course_cluster_groups' => 0,
            'cluster_groupings' => 0,
            'default_instructor_role' => 0,

            // Course catalog
            'catalog_collapse_count' => 4,
            'disablecoursecatalog' => 0,

            // Certificates
            'disablecertificates' => 1,

            // notifications
            'notify_classenrol_user' => 0,
            'notify_classenrol_role' => 0,
            'notify_classenrol_supervisor' => 0,
            'notify_classenrol_message' => get_string('notifyclassenrolmessagedef', 'block_curr_admin'),

            'notify_classcompleted_user' => 0,
            'notify_classcompleted_role' => 0,
            'notify_classcompleted_supervisor' => 0,
            'notify_classcompleted_message' => get_string('notifyclasscompletedmessagedef', 'block_curr_admin'),

            'notify_classnotstarted_user' => 0,
            'notify_classnotstarted_role' => 0,
            'notify_classnotstarted_supervisor' => 0,
            'notify_classnotstarted_message' => get_string('notifyclassnotstartedmessagedef', 'block_curr_admin'),
            'notify_classnotstarted_days' => 10,

            'notify_classnotcompleted_user' => 0,
            'notify_classnotcompleted_role' => 0,
            'notify_classnotcompleted_supervisor' => 0,
            'notify_classnotcompleted_message' => get_string('notifyclassnotcompletedmessagedef', 'block_curr_admin'),
            'notify_classnotcompleted_days' => 10,

            'notify_curriculumnotcompleted_user' => 0,
            'notify_curriculumnotcompleted_role' => 0,
            'notify_curriculumnotcompleted_supervisor' => 0,
            'notify_curriculumnotcompleted_message' => get_string('notifycurriculumnotcompletedmessagedef', 'block_curr_admin'),
            'notify_classnotstarted_days' => 10,

            'notify_trackenrol_user' => 0,
            'notify_trackenrol_role' => 0,
            'notify_trackenrol_supervisor' => 0,
            'notify_ttrackenrol_message' => get_string('notifytrackenrolmessagedef', 'block_curr_admin'),

            'notify_courserecurrence_user' => 0,
            'notify_courserecurrence_role' => 0,
            'notify_courserecurrence_supervisor' => 0,
            'notify_courserecurrence_message' => get_string('notifycourserecurrencemessagedef', 'block_curr_admin'),
            'notify_courserecurrence_days' => 10,

            'notify_curriculumrecurrence_user' => 0,
            'notify_curriculumrecurrence_role' => 0,
            'notify_curriculumrecurrence_supervisor' => 0,
            'notify_curriculumrecurrence_message' => get_string('notifycurriculumrecurrencemessagedef', 'block_curr_admin'),
            'notify_curriculumrecurrence_days' => 10,

            //number of icons of each type to display at each level in the curr admin block
            'num_block_icons' => 5,
            'display_clusters_at_top_level' => 1,
            'display_curricula_at_top_level' => 0,

            //default roles
            'default_cluster_role_id' => 0,
            'default_curriculum_role_id' => 0,
            'default_course_role_id' => 0,
            'default_class_role_id' => 0,
            'default_track_role_id' => 0,

            'autocreated_unknown_is_yes' => 1,

            //legacy settings
            'legacy_show_inactive_users' => 0,
        );

        // include defaults from plugins
        $plugins = get_list_of_plugins('curriculum/plugins');
        foreach ($plugins as $plugin) {
            if (is_readable(CURMAN_DIRLOCATION . '/plugins/' . $plugin . '/config.php')) {
                include_once(CURMAN_DIRLOCATION . '/plugins/' . $plugin . '/config.php');
                if (function_exists("{$plugin}_get_config_defaults")) {
                    $defaults += call_user_func("{$plugin}_get_config_defaults");
                }
            }
        }

        foreach ($defaults as $key => $value) {
            if (!isset($CURMAN->config->$key)) {
                $CURMAN->config->$key = $value;
            }
        }
    }

/**
     * Set a key in global configuration
     *
     * Set a key/value pair in both this session's {@link $CURMAN->config} global variable
     * and in the 'config' database table for future sessions.
     *
     * A NULL value will delete the entry.
     *
     * @param string $name the key to set
     * @param string $value the value to set (without magic quotes)
     * @uses $CURMAN
     * @return bool
     */
    function cm_set_config($name, $value) {
        global $CURMAN;

        $CURMAN->config->$name = $value;

        if (get_field(CMCONFIGTABLE, 'name', 'name', $name)) {
            if ($value===null) {
                return delete_records(CMCONFIGTABLE, 'name', $name);
            } else {
                return set_field(CMCONFIGTABLE, 'value', addslashes($value), 'name', $name);
            }
        } else {
            if ($value===null) {
                return true;
            }
            $config = new object();
            $config->name = $name;
            $config->value = addslashes($value);
            return insert_record(CMCONFIGTABLE, $config);
        }
    }

    /**
     * Function to get a parameter from _POST or _GET. If not present, will return the value defined
     * in the $default parameter or false if not defined.
     *
     * @param string $param The parameter to look for.
     * @param string $default Default value to return if not found.
     * @param int $clean (Future - not used).
     * @return string | boolean The value of the parameter, or $default.
     */
    function cm_get_param($param, $default = false, $clean = 0) {
    /// Using Moodle...
        return optional_param($param, $default);
    }


    /**
     * Return an error message formatted the way the application wants it.
     *
     * @param string $message The text to display.
     * @return string The formatted message.
     */
    function cm_error($message) {
    /// Using Moodle...
        return notify($message, 'notifyproblem', 'center', true);
    }


    /**
     * Returns a delete form formatted for the application.
     *
     * @param string $url The page to call.
     * @param string $message The message to ask.
     * @param array $optionsyes The form attributes for the "yes" portion.
     * @param array $optionsno The form attributes for the "no" portion.
     *
     * @return string The HTML for thr form.
     *
     */
    function cm_delete_form($url='', $message='', $optionsyes=NULL, $optionsno=NULL) {
    /// Using Moodle
        ob_start();
        notice_yesno($message, $url, $url, $optionsyes, $optionsno, 'post', 'get');
        $output = ob_get_contents();
        ob_end_clean();

        return $output;
    }


    function cm_course_complete($enrolment) {
        track::check_autoenrol_after_course_completion($enrolment);
        waitlist::check_autoenrol_after_course_completion($enrolment);

        return true;
    }

    /**
     * Prints form items with the names $day, $month and $year
     *
     * @param string $day   fieldname
     * @param string $month  fieldname
     * @param string $year  fieldname
     * @param int $currenttime A default timestamp in GMT
     * @param boolean $return
     */
    function cm_print_date_selector($day, $month, $year, $currenttime = 0, $return = false) {
        if (!$currenttime) {
            $currenttime = time();
        }

        $currentdate = cm_usergetdate($currenttime);

        for ($i=1; $i<=31; $i++) {
            $days[$i] = $i;
        }
        for ($i=1; $i<=12; $i++) {
            //$months[$i] = cm_userdate(gmmktime(12,0,0,$i,15,2000), "%B");
            $months[$i] = strftime("%B", gmmktime(12,0,0,$i,15,2000));
        }
        for ($i=1970; $i<=2020; $i++) {
            $years[$i] = $i;
        }
        return cm_choose_from_menu($days,   $day,   $currentdate['mday'], '', '', '0', $return)
              .cm_choose_from_menu($months, $month, $currentdate['mon'],  '', '', '0', $return)
              .cm_choose_from_menu($years,  $year,  $currentdate['year'], '', '', '0', $return);
    }


    /**
     *Prints form items with the names $hour and $minute
     *
     * @param string $hour  fieldname
     * @param string ? $minute  fieldname
     * @param $currenttime A default timestamp in GMT
     * @param int $step minute spacing
     * @param boolean $return
     */
    function cm_print_time_selector($hour, $minute, $currenttime = 0, $step = 5, $return = false) {
        if (!$currenttime) {
            $currenttime = time();
        }
        $currentdate = cm_usergetdate($currenttime);
        if ($step != 1) {
            $currentdate['minutes'] = ceil($currentdate['minutes']/$step)*$step;
        }
        for ($i=0; $i<=23; $i++) {
            $hours[$i] = sprintf("%02d",$i);
        }
        for ($i=0; $i<=59; $i+=$step) {
            $minutes[$i] = sprintf("%02d",$i);
        }

        return cm_choose_from_menu($hours,   $hour,   $currentdate['hours'],   '','','0',$return)
              .cm_choose_from_menu($minutes, $minute, $currentdate['minutes'], '','','0',$return);
    }


    /**
     * Given an array of value, creates a popup menu to be part of a form
     * $options["value"]["label"]
     *
     * @param    type description
     * @todo Finish documenting this function
     */
    function cm_choose_from_menu ($options, $name, $selected = '', $nothing = 'choose', $script = '',
                                  $nothingvalue = '0', $return = false, $disabled = false,
                                  $tabindex = 0, $id = '') {

        if ($nothing == 'choose') {
            //$nothing = get_string('choose') .'...';
            $nothing = get_string('choose', 'block_curr_admin');
        }

        $attributes = ($script) ? 'onchange="'. $script .'"' : '';
        if ($disabled) {
            $attributes .= ' disabled="disabled"';
        }

        if ($tabindex) {
            $attributes .= ' tabindex="'.$tabindex.'"';
        }

        if ($id ==='') {
            $id = 'menu'.$name;
             // name may contaion [], which would make an invalid id. e.g. numeric question type editing form, assignment quickgrading
            $id = str_replace('[', '', $id);
            $id = str_replace(']', '', $id);
        }

        $output = '<select id="'.$id.'" name="'. $name .'" '. $attributes .'>' . "\n";
        if ($nothing) {
            $output .= '   <option value="'. s($nothingvalue) .'"'. "\n";
            if ($nothingvalue === $selected) {
                $output .= ' selected="selected"';
            }
            $output .= '>'. $nothing .'</option>' . "\n";
        }

        if (!empty($options)) {
            foreach ($options as $value => $label) {
                $output .= '   <option value="'. s($value) .'"';
                if ((string)$value == (string)$selected) {
                    $output .= ' selected="selected"';
                }
                if ($label === '') {
                    $output .= '>'. $value .'</option>' . "\n";
                } else {
                    $output .= '>'. $label .'</option>' . "\n";
                }
            }
        }
        $output .= '</select>' . "\n";

        if ($return) {
            return $output;
        } else {
            echo $output;
        }
    }

    /**
     * Return a timestamp from a date style field (YYY/MM/DD).
     *
     */
    function cm_datestring_to_timestamp($datestring) {

        $dateparts = explode('/', $datestring);
        if (is_array($dateparts) && (count($dateparts) == 3)) {
            $ts = mktime(0, 0, 0, $dateparts[1], $dateparts[2], $dateparts[0]);
        } else {
            $ts = 0;
        }

        return $ts;
    }

    /**
     * Return a formatted date string from a date style field (YYY/MM/DD).
     *
     */
    function cm_timestring_to_date($timestring) {

        $months = array(1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May',
                        6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct',
                        11 => 'Nov', 12 => 'Dec');

        $dateparts = explode('/', $timestring);
        if (is_array($dateparts) && (count($dateparts) == 3)) {
            $date = $months[(int)$dateparts[1]].' '.$dateparts[2].', '.$dateparts[0];
        } else {
            $date = '';
        }

        return $date;
    }

    /**
     * Return a formatted date string from a timestamp. Use this to keep all strings formatted
     * the same way in the system.
     *
     */
    function cm_timestamp_to_date($timestamp, $format=CURMAN_DATEFORMAT) {
        if (is_numeric($timestamp)) {
            return date($format, $timestamp);
        } else {
            return '';
        }
    }

    /**
     * Given a $time timestamp in GMT (seconds since epoch),
     * returns an array that represents the date in user time
     *
     * @uses HOURSECS
     * @param int $time Timestamp in GMT
     * @param float $timezone ?
     * @return array An array that represents the date in user time
     * @todo Finish documenting this function
     */
    function cm_usergetdate($time, $timezone = 99) {
/*
        $timezone = get_user_timezone_offset($timezone);

        if (abs($timezone) > 13) {    // Server time
            return getdate($time);
        }

        // There is no gmgetdate so we use gmdate instead
        $time += dst_offset_on($time);
        $time += intval((float)$timezone * HOURSECS);
*/

        $datestring = gmstrftime('%S_%M_%H_%d_%m_%Y_%w_%j_%A_%B', $time);

        list(
            $getdate['seconds'],
            $getdate['minutes'],
            $getdate['hours'],
            $getdate['mday'],
            $getdate['mon'],
            $getdate['year'],
            $getdate['wday'],
            $getdate['yday'],
            $getdate['weekday'],
            $getdate['month']
        ) = explode('_', $datestring);

        return $getdate;
    }


    /**
     * Duplicate the functionality of the Moodle fullname() function call
     * to return a concatenated fullname from a user object with a 'firstname'
     * and 'lastname' property set.
     */
    function cm_fullname($user) {
        $name = '';

        if (isset($user->fullname)) {
            return $user->fullname;
        }

        if (isset($user->profile_first_name)) {
            $name .= $user->profile_first_name;
        }

        if (isset($user->profile_last_name)) {
            return $name . (!empty($name) ? ' ' : '') . $user->profile_last_name;
        }

        if (isset($user->firstname)) {
            $name .= $user->firstname;
        }

        if (isset($user->mi)) {
            $name .= (!empty($name) ? ' ' : '') . $user->mi;
        }

        if (isset($user->lastname)) {
            $name .= (!empty($name) ? ' ' : '') . $user->lastname;
        }

        return $name;
    }


    function cm_print_heading_block($heading, $class='', $return=false) {
        //Accessibility: 'headingblock' is now H1, see theme/standard/styles_*.css: ??
        $output = '<h2 class="headingblock header '.$class.'">'.stripslashes($heading).'</h2>';

        if ($return) {
            return $output;
        } else {
            echo $output;
        }
    }


    /**
     * Create a property_exists() function for PHP environments < 5.1.
     */
    if (!function_exists('property_exists')) {
        function property_exists( $class, $property ) {
            if ( is_object( $class ) ) {
                $vars = get_object_vars( $class );
            } else {
                $vars = get_class_vars( $class );
            }
            return array_key_exists( $property, $vars );
        }
    }


    /**
     * Function to raise the memory limit to a new value.
     * Will respect the memory limit if it is higher, thus allowing
     * settings in php.ini, apache conf or command line switches
     * to override it
     *
     * The memory limit should be expressed with a string (eg:'64M')
     *
     * @see Moodle:/lib/setuplib.php
     * @param string $newlimit the new memory limit
     * @return bool
     */
    function cm_raise_memory_limit ($newlimit) {
        if (empty($newlimit)) {
            return false;
        }

        $cur = @ini_get('memory_limit');
        if (empty($cur)) {
            // if php is compiled without --enable-memory-limits
            // apparently memory_limit is set to ''
            $cur=0;
        } else {
            if ($cur == -1){
                return true; // unlimited mem!
            }
          $cur = cm_get_real_size($cur);
        }

        $new = cm_get_real_size($newlimit);
        if ($new > $cur) {
            ini_set('memory_limit', $newlimit);
            return true;
        }
        return false;
    }


    /**
     * Converts numbers like 10M into bytes.
     *
     * @see Moodle:/lib/setuplib.php
     * @param mixed $size The size to be converted
     * @return mixed
     */
    function cm_get_real_size($size=0) {
        if (!$size) {
            return 0;
        }
        $scan['MB'] = 1048576;
        $scan['Mb'] = 1048576;
        $scan['M']  = 1048576;
        $scan['m']  = 1048576;
        $scan['KB'] = 1024;
        $scan['Kb'] = 1024;
        $scan['K']  = 1024;
        $scan['k']  = 1024;

        while (list($key) = each($scan)) {
            if ((strlen($size)>strlen($key))&&(substr($size, strlen($size) - strlen($key))==$key)) {
                $size = substr($size, 0, strlen($size) - strlen($key)) * $scan[$key];
                break;
            }
        }
        return $size;
    }


    /**
     * Adjust a given timestamp by the specified values.
     *
     * The given adjustment values can be either a positive or negative value
     * to create a new timestamp either in the past or future.
     *
     * @param int $time   The UNIX timestamp.
     * @param int $years  The number of years to adjust the timestamp by.
     * @param int $months The number of months to adjust the timestamp by.
     * @param int $weeks  The number of weeks to adjust the timestamp by.
     * @param int $days   The number of days to adjust the timestamp by.
     * @param int $hours  The number of hours to adjust the timestamp by.
     */
    function cm_timedelta($time, $years = 0, $months = 0, $weeks = 0, $days = 0, $hours = 0) {
        if ($years != 0) {
            $yd   = ($years > 0) ? "+$years" : "$years";
            $time = strtotime("$yd year", $time);
        }
        if ($months != 0) {
            $md   = ($months > 0) ? "+$months" : "$months";
            $time = strtotime("$md month", $time);
        }
        if ($weeks != 0) {
            $wd   = ($weeks > 0) ? "+$weeks" : "$weeks";
            $time = strtotime("$wd week", $time);
        }
        if ($days != 0) {
            $dd   = ($days > 0) ? "+$days" : "$days";
            $time = strtotime("$dd day", $time);
        }
        if ($hours != 0) {
            $hd   = ($hours > 0) ? "+$hours" : "$hours";
            $time = strtotime("hd hour", $time);
        }

        return $time;
    }


    /**
     * Determine the access level for the given user.
     *
     * @uses $USER
     * @param int $uid The user ID (optional)
     * @return string|bool The access level, or False on error.
     */
    function cm_determine_access($uid = false) {
        global $USER, $CFG;

        if (!$uid) {
            if (!isloggedin()) {
                return 'newuser';
            }
            $uid = $USER->id;
        }

        if (!record_exists('user', 'id', $uid)) {
            return false;
        }

        $context = get_context_instance(CONTEXT_SYSTEM, SITEID);

        require_once ($CFG->dirroot . '/curriculum/lib/cluster.class.php');

        if (has_capability('block/curr_admin:managecurricula', $context)) {
            return 'admin';
        //} else if (has_capability('block/curr_admin:viewreports', $context)) {
        //    return 'reviewer';
        //} else if (has_capability('block/curr_admin:viewgroupreports', $context)) {
        //    return 'groupreviewer';
        } else if (has_capability('block/curr_admin:viewownreports', $context)){
            return 'student';
        }
    }


    /**
     * Determine if the My Moodle direct to the CM dashboard is enabled.
     *
     * @uses $CURMAN
     * @param none
     * @return bool True if My Moodle redirect is enabled, False otherwise.
     */
    function cm_mymoodle_redirect() {
        global $CURMAN;

        return (!empty($CURMAN->config->mymoodle_redirect) && $CURMAN->config->mymoodle_redirect == 1);
    }


    /**
     * Get Moodle user id for a given curriculum user id.
     *
     */
    function cm_get_moodleuserid($userid) {
        global $CFG;
        require_once $CFG->dirroot . '/curriculum/lib/user.class.php';

        $select = 'SELECT mu.id ';
        $from   = 'FROM '.$CFG->prefix . USRTABLE . ' cu ';
        $join   = "INNER JOIN {$CFG->prefix}user mu ON mu.idnumber = cu.idnumber AND mu.mnethostid={$CFG->mnet_localhost_id} AND mu.deleted = 0 ";
        $where  = 'WHERE cu.id = '.$userid;
        return get_field_sql($select.$from.$join.$where);
    }

    /**
     * Get Moodle user record for a given curriculum user id.
     *
     */
    function cm_get_moodleuser($userid) {
        global $CFG;
        require_once $CFG->dirroot . '/curriculum/lib/user.class.php';

        $select = 'SELECT mu.* ';
        $from   = 'FROM '.$CFG->prefix . USRTABLE . ' cu ';
        $join   = "INNER JOIN {$CFG->prefix}user mu ON mu.idnumber = cu.idnumber AND mu.mnethostid={$CFG->mnet_localhost_id} AND mu.deleted = 0 ";
        $where  = 'WHERE cu.id = '.$userid;
        return get_record_sql($select.$from.$join.$where);

    }

    /**
     * Get Curriculum user id for a given Moodle user id.
     *
     */
    function cm_get_crlmuserid($userid) {
        global $CFG;
        require_once $CFG->dirroot . '/curriculum/lib/user.class.php';

        $select = 'SELECT cu.id ';
        $from   = 'FROM '.$CFG->prefix.'user mu ';
        $join   = 'INNER JOIN '.$CFG->prefix.USRTABLE.' cu ON cu.idnumber = mu.idnumber ';
        $where  = 'WHERE mu.id = '.$userid;
        return get_field_sql($select.$from.$join.$where);
    }

    /**
     * Check for access to the specified user.
     * (currently, this only verifies cluster groups)
     *
     */
    function cm_can_access_userreport($touserid, $fromuserid = 0) {
        global $USER, $CURMAN;

        if ($fromuserid == 0) {
            $fromuserid = cm_get_crlmuserid($USER->id);
        }

        // check if there is a cluster that contains both users
        return $CURMAN->db->record_exists_sql('SELECT c1.id FROM ' . $CURMAN->db->prefix_table(CLSTUSERTABLE) . ' c1 INNER JOIN ' . $CURMAN->db->prefix_table(CLSTUSERTABLE) . ' c2 ON c1.clusterid = c2.clusterid WHERE c1.userid = ' . $touserid . ' AND c2.userid = ' . $fromuserid);
    }

    /**
     * Get the location for a given user.
     *
     * NOTE: The $CURMAN global variable must be present for this function to
     * work correctly as it needs information from the non-Moodle DB.
     *
     * @uses $USER
     * @param int $uid The user ID (optional)
     * @return string|bool The location string, or False on error.
     */
    function cm_get_user_location($uid = false) {
        global $USER, $CURMAN;

        $return = '';

        if (!$uid) {
            $uid = $USER->id;
        }

        if ($idnumber = get_field('user', 'idnumber', 'id', $uid)) {
            $return = $CURMAN->db->get_field('authuser', 'local', 'idnumber', $idnumber);
        } else if ($loc = get_field('user', 'institution', 'id', $uid)) {
            if (is_string($loc) && strlen($loc) == 3) {
                $return = $loc;
            }
        }

        return $return;
    }


    /**
     * Calculate the end datestamp value based upon the current startdate timestamp.
     *
     * @param int $startdate A timestamp to calculate the end date from (optional).
     * @return none
     */
    function cm_get_enddate($length, $lengthdescription, $startdate = 0) {
        if (!empty($startdate)) {
            return strtotime("+{$length} {$lengthdescription}", $startdate);
        } else {
            return strtotime("+{$length} {$lengthdescription}");
        }
    }

//// Functions used for tracking and reporting.

    /**
     * Call all cron jobs needed for the CM system.
     *
     */
    function cm_cron() {
        $status = true;

        $status = cm_migrate_moodle_users(false, time() - (7*24*60*60)) && $status;
        $status = cm_update_student_progress() && $status;
        $status = cm_check_for_nags() && $status;
        $status = cm_update_student_enrolment() && $status;

        return $status;
    }

    /**
     *
     */
    function cm_update_student_enrolment() {
        global $CURMAN;
        //for every student
        $select = 'completestatusid = ' . STUSTATUS_NOTCOMPLETE . ' AND endtime > 0 AND endtime < ' . time();
        $students = $CURMAN->db->get_records_select(STUTABLE, $select);

        if(!empty($students)) {
            foreach($students as $s) {
                $a = $CURMAN->db->get_field('crlm_class', 'idnumber', 'id', $s->classid);

                $subject = get_string('incomplete_course_subject', 'block_curr_admin');
                $message = get_string('incomplete_course_message', 'block_curr_admin', $a);

                $user = cm_get_moodleuser($s->userid);
                $from = get_admin();

                notification::notify($message, $user, $from);
                email_to_user($user, $from, $subject, $message);

                $s->completetime = 0;
                $s->completestatusid = STUSTATUS_FAILED;
                $CURMAN->db->update_record(STUTABLE, $s);
            }
        }

        return true;
    }

    /**
     * call the import files script to set up cm items
     */
    function cm_dataimport() {
        include(CURMAN_DIRLOCATION . '/elis_ip/elis_ip_cron.php');
        return true;
    }

    /**
     * Get all of the data from Moodle and update the curriculum system.
     * This should do the following:
     *      - Get all Moodle courses connected with classes.
     *      - Get all users in each Moodle course.
     *      - Get grade records from the class's course and completion elements.
     *      - For each user:
     *          - Check if they have an enrolment record in CM, and add if not.
     *          - Update grade information in the enrollment and grade tables in CM.
     *
     */
    function cm_update_student_progress() {
        global $CFG;

        require_once ($CFG->dirroot.'/grade/lib.php');
        require_once ($CFG->dirroot.'/grade/querylib.php');

        /// Get all grades in all relevant courses for all relevant users.
        require_once ($CFG->dirroot.'/curriculum/lib/classmoodlecourse.class.php');
        require_once ($CFG->dirroot.'/curriculum/lib/student.class.php');
        require_once ($CFG->dirroot.'/curriculum/lib/cmclass.class.php');
        require_once ($CFG->dirroot.'/curriculum/lib/course.class.php');

    /// Start with the Moodle classes...
        mtrace("Synchronizing Moodle class grades<br />\n");
        cm_synchronize_moodle_class_grades();

        flush(); sleep(1);

    /// Now we need to check all of the student and grade records again, since data may have come from sources
    /// other than Moodle.
        mtrace("Updating all class grade completions.<br />\n");
        cm_update_class_grades();

        return true;
    }

    function cm_synchronize_moodle_class_grades() {
        global $CFG, $CURMAN;
        require_once($CFG->dirroot.'/grade/lib.php');

        if ($moodleclasses = moodle_get_classes()) {
            $timenow = time();
            foreach ($moodleclasses as $class) {
                $cmclass = new cmclass($class->classid);
                $context = get_context_instance(CONTEXT_COURSE, $class->moodlecourseid);
                $moodlecourse = $CURMAN->db->get_record('course', 'id', $class->moodlecourseid);

                // Get CM enrolment information (based on Moodle enrolments)
                // IMPORTANT: this record set must be sorted using the Moodle
                // user ID
                $relatedcontextsstring = get_related_contexts_string($context);
                $sql = "SELECT DISTINCT u.id AS muid, u.username, cu.id AS cmid, stu.*
                          FROM {$CURMAN->db->prefix_table('user')} u
                          JOIN {$CURMAN->db->prefix_table('role_assignments')} ra ON u.id = ra.userid
                     LEFT JOIN {$CURMAN->db->prefix_table(USRTABLE)} cu ON cu.idnumber = u.idnumber
                     LEFT JOIN {$CURMAN->db->prefix_table(STUTABLE)} stu on stu.userid = cu.id AND stu.classid = {$cmclass->id}
                         WHERE ra.roleid in ($CFG->gradebookroles)
                           AND ra.contextid {$relatedcontextsstring}
                      ORDER BY muid ASC";
                $causers = get_recordset_sql($sql);

                if(empty($causers)) {
                    // nothing to see here, move on
                    continue;
                }

                /// Get CM completion elements and related Moodle grade items
                $comp_elements = array();
                $gis = array();
                if (isset($cmclass->course) && (get_class($cmclass->course) == 'course')
                    && ($elements = $cmclass->course->get_completion_elements())) {

                    foreach ($elements as $element) {
                        // It looks like Moodle actually stores the "slashes" on the idnumber field in the grade_items
                        // table so we need to addslashes twice to this value if it needs them. =(  - ELIS-1830
                        $idnumber = addslashes($element->idnumber);
                        if ($idnumber != $element->idnumber) {
                            $idnumber = addslashes($idnumber);
                        }
                        if ($gi = get_record('grade_items', 'courseid', $class->moodlecourseid, 'idnumber', $idnumber)) {
                            $gis[$gi->id] = $gi;
                            $comp_elements[$gi->id] = $element;
                        }
                    }
                }
                // add grade item for the overall course grade
                $coursegradeitem = grade_item::fetch_course_item($moodlecourse->id);
                $gis[$coursegradeitem->id] = $coursegradeitem;

                if ($coursegradeitem->grademax == 0) {
                    // no maximum course grade, so we can't calculate the
                    // student's grade
                    continue;
                }

                if (!empty($elements)) {
                    // get current completion element grades if we have any
                    // IMPORTANT: this record set must be sorted using the Moodle
                    // user ID
                    $sql = "SELECT grades.*, mu.id AS muid
                              FROM {$CURMAN->db->prefix_table(GRDTABLE)} grades
                              JOIN {$CURMAN->db->prefix_table(USRTABLE)} cu ON grades.userid = cu.id
                              JOIN {$CURMAN->db->prefix_table('user')} mu ON cu.idnumber = mu.idnumber
                             WHERE grades.classid = {$cmclass->id}
                          ORDER BY mu.id";
                    $allcompelemgrades = get_recordset_sql($sql);
                    $last_rec = null; // will be used to store the last completion
                                      // element that we fetched from the
                                      // previous iteration (which may belong
                                      // to the current user)
                }

                // get the Moodle course grades
                // IMPORTANT: this iterator must be sorted using the Moodle
                // user ID
                $gradedusers = new graded_users_iterator($moodlecourse, $gis, 0, 'id', 'ASC', null);
                $gradedusers->init();

                // only create a new enrolment record if there is only one CM
                // class attached to this Moodle course
                $doenrol = ($CURMAN->db->count_records(CLSMDLTABLE, 'moodlecourseid', $class->moodlecourseid) == 1);

                // main loop -- go through the student grades
                while (($sturec = rs_fetch_next_record($causers)) && ($stugrades = $gradedusers->next_user())) {
                    // skip user records that don't match up
                    // (this works since both sets are sorted by Moodle user ID)
                    // (in theory, we shouldn't need this, but just in case...)
                    while ($sturec && $sturec->muid < $stugrades->user->id) {
                        $sturec = rs_fetch_next_record($causers);
                    }
                    if (!$sturec) {
                        break;
                    }
                    while($stugrades && $stugrades->user->id < $sturec->muid) {
                        $stugrades = $gradedusers->next_user();
                    }
                    if (!$stugrades) {
                        break;
                    }

                    /// If the user doesn't exist in CM, skip it -- should we flag it?
                    if (empty($sturec->cmid)) {
                        mtrace("No user record for Moodle user id: {$sturec->muid}: {$sturec->username}<br />\n");
                        continue;
                    }
                    $cmuserid = $sturec->cmid;

                    /// If no enrolment record in ELIS, then let's set one.
                    if (empty($sturec->id)) {
                        if(!$doenrol) {
                            continue;
                        }
                        $sturec->classid = $class->classid;
                        $sturec->userid = $cmuserid;
                        /// Enrolment time will be the earliest found role assignment for this user.
                        $enroltime = get_field('role_assignments', 'MIN(timestart) as enroltime', 'contextid',
                                               $context->id, 'userid', $sturec->muid);
                        $sturec->enrolmenttime = (!empty($enroltime) ? $enroltime : $timenow);
                        $sturec->completetime = 0;
                        $sturec->endtime = 0;
                        $sturec->completestatusid = STUSTATUS_NOTCOMPLETE;
                        $sturec->grade = 0;
                        $sturec->credits = 0;
                        $sturec->locked = 0;
                        $sturec->id = $CURMAN->db->insert_record(STUTABLE, $sturec);
                    }

                    /// Handle the course grade
                    if (isset($stugrades->grades[$coursegradeitem->id]->finalgrade)) {

                        /// Set the course grade if there is one and it's not locked.
                        $usergradeinfo = $stugrades->grades[$coursegradeitem->id];
                        if (!$sturec->locked && !is_null($usergradeinfo->finalgrade)) {
                            // clone of student record, to see if we actually change anything
                            $old_sturec = clone($sturec);

                            $grade = $usergradeinfo->finalgrade / $coursegradeitem->grademax * 100.0;
                            $sturec->grade = $grade;

                            /// Update completion status if all that is required is a course grade.
                            if (empty($elements)) {
                                if ($cmclass->course->completion_grade <= $sturec->grade) {
                                    $sturec->completetime = $usergradeinfo->get_dategraded();
                                    $sturec->completestatusid = STUSTATUS_PASSED;
                                    $sturec->credits = floatval($cmclass->course->credits);
                                } else {
                                    $sturec->completetime = 0;
                                    $sturec->completestatusid = STUSTATUS_NOTCOMPLETE;
                                    $sturec->credits = 0;
                                }
                            } else {
                                $sturec->completetime = 0;
                                $sturec->completestatusid = STUSTATUS_NOTCOMPLETE;
                                $sturec->credits = 0;
                            }

                            // only update if we actually changed anything
                            // (exception: if the completetime gets smaller,
                            // it's probably because $usergradeinfo->get_dategraded()
                            // returned an empty value, so ignore that change)
                            if ($old_sturec->grade != $sturec->grade
                                || $old_sturec->completetime < $sturec->completetime
                                || $old_sturec->completestatusid != $sturec->completestatusid
                                || $old_sturec->credits != $sturec->credits) {

                                if ($sturec->completestatusid == STUSTATUS_PASSED && empty($sturec->completetime)) {
                                    // make sure we have a valid complete time, if we passed
                                    $sturec->completetime = $timenow;
                                }

                                $CURMAN->db->update_record(STUTABLE, $sturec);
                            }
                        }
                    }

                    /// Handle completion elements
                    if (!empty($allcompelemgrades)) {
                        // get student's completion elements
                        $cmgrades = array();
                        // NOTE: we use a do-while loop, since $last_rec might
                        // be set from the last run, so we need to check it
                        // before we load from the database
                        do {
                            if ($last_rec) {
                                if ($last_rec->muid > $sturec->muid) {
                                    // we've reached the end of this student's
                                    // grades ($last_rec will save this record
                                    // for the next student's run)
                                    break;
                                }
                                if ($last_rec->muid == $sturec->muid) {
                                    $cmgrades[$last_rec->completionid] = $last_rec;
                                }
                            }
                        } while (($last_rec = rs_fetch_next_record($allcompelemgrades)));

                        foreach ($comp_elements as $gi_id => $element) {
                            if (!isset($stugrades->grades[$gi_id]->finalgrade)) {
                                continue;
                            }
                            // calculate Moodle grade as a percentage
                            $gradeitem = $stugrades->grades[$gi_id];
                            $maxgrade = $gis[$gi_id]->grademax;
                            /// Ignore mingrade for now... Don't really know what to do with it.
                            $gradepercent =  ($gradeitem->finalgrade >= $maxgrade) ? 100.0
                                          : (($gradeitem->finalgrade <= 0) ? 0.0
                                          :  ($gradeitem->finalgrade / $maxgrade * 100.0));

                            if (isset($cmgrades[$element->id])) {
                                // update existing completion element grade
                                $grade_element = $cmgrades[$element->id];
                                if (!$grade_element->locked
                                    && ($gradeitem->get_dategraded() > $grade_element->timegraded)) {

                                    // clone of record, to see if we actually change anything
                                    $old_grade = clone($grade_element);

                                    $grade_element->grade = $gradepercent;
                                    $grade_element->timegraded = $gradeitem->get_dategraded();
                                    /// If completed, lock it.
                                    $grade_element->locked = ($grade_element->grade >= $element->completion_grade) ? 1 : 0;

                                    // only update if we actually changed anything
                                    if ($old_grade->grade != $grade_element->grade
                                        || $old_grade->timegraded != $grade_element->timegraded
                                        || $old_grade->grade != $grade_element->grade
                                        || $old_grade->locked != $grade_element->locked) {

                                        $grade_element->timemodified = $timenow;
                                        $CURMAN->db->update_record(GRDTABLE, $grade_element);
                                    }
                                }
                            } else {
                                // no completion element grade exists: create a new one
                                $grade_element = new Object();
                                $grade_element->classid = $class->classid;
                                $grade_element->userid = $cmuserid;
                                $grade_element->completionid = $element->id;
                                $grade_element->grade = $gradepercent;
                                $grade_element->timegraded = $gradeitem->get_dategraded();
                                $grade_element->timemodified = $timenow;
                                /// If completed, lock it.
                                $grade_element->locked = ($grade_element->grade >= $element->completion_grade) ? 1 : 0;
                                $CURMAN->db->insert_record(GRDTABLE, $grade_element);
                            }
                        }
                    }
                }
                set_time_limit(600);
            }
        }
    }

    function cm_update_class_grades() {
        global $CFG, $CURMAN;

        $classid = 0;

/// Need to separate this out so that the enrolments by class are checked for completion.
/// ... for each class and then for each enrolment...
/// Goal is to minimize database reads, so we can't just instantiate a student object, as
/// each one will go and get the same things for one class. So, we probably need a class-level
/// function that then manages the student objects. Once this is in place, add completion notice
/// to the code.


        /// Get all classes with unlocked enrolments.
        $select = 'SELECT cce.classid as classid, COUNT(cce.userid) as numusers ';
        $from   = 'FROM '.$CFG->prefix.'crlm_class_enrolment cce ';
        $where  = 'WHERE cce.locked = 0 ';
        $group  = 'GROUP BY classid ';
        $order  = 'ORDER BY classid ASC ';
        $sql    = $select . $from . $where . $group . $order;

        $rs = get_recordset_sql($sql);
        if ($rs) {
            while ($rec = rs_fetch_next_record($rs)) {
                $cmclass = new cmclass($rec->classid);
                $cmclass->update_all_class_grades();
            }
            set_time_limit(600);
        }
    }

    /**
     * Check for nags...
     *
     */
    function cm_check_for_nags() {
        $status = true;

        mtrace("Checking notifications<br />\n");
        $status = cmclass::check_for_nags() && $status;
        $status = cmclass::check_for_moodle_courses() && $status;
        $status = course::check_for_nags() && $status;
        $status = curriculum::check_for_nags() && $status;

        return $status;
    }

//// Functions that return registration values. Change these to data functions eventually.

    /**
     * Return the language options.
     */
    function cm_get_list_of_languages() {

        /// Get them from Moodle... Replace this if they should be something else.
        /// Should thie really rely on Moodle? Or should we provide our own?
        $languages = get_list_of_languages(false, true);

        return $languages;
    }

    /**
     * Return the cluster options.
     */
    function cm_get_list_of_clusters() {
        global $CFG;

        require_once($CFG->dirroot.'/curriculum/lib/cluster.class.php');

        return cluster_get_cluster_names();
    }

    /**
     * Return the referrals options.
     */
    function cm_get_list_of_referrals() {
        $referrals = array(
            'NU student'               => 'NU student',
            'Church or other ministry' => 'Church or other ministry',
            'Friend'                   => 'Friend',
            'School'                   => 'School',
            'Surfing the internet'     => 'Surfing the internet'
        );

        return $referrals;
    }

    /**
     * Return the education options.
     */
    function cm_get_list_of_education() {
        $education = array(
            'highschool' => 'Highschool',
            'bachelors'  => 'Bachelors',
            'masters'    => 'Masters'
        );

        return $education;
    }

    /**
     * Return the countries options.
     */
    function cm_get_list_of_countries() {
        static $countries;

        if (empty($countries)) {
        /// Get them from Moodle... Replace this if they should be something else.
            $countries = get_list_of_countries();
        }

        return $countries;
    }

    function cm_get_country($code) {
        $countries = cm_get_list_of_countries();

        if (!isset($countries[$code])) {
            return get_string('unknown', 'block_curr_admin');
        } else {
            return $countries[$code];
        }
    }

    /**
     * Migrate any existing Moodle users to the Curriculum Management
     * system.
     */
    function cm_migrate_moodle_users($setidnumber = false, $fromtime = 0) {
        global $CFG, $CURMAN;

        $timenow = time();
        $result  = true;

        // set time modified if not set, so we can keep track of "new" users
        $sql = "UPDATE {$CFG->prefix}user
                   SET timemodified = $timenow
                 WHERE timemodified = 0";
        $result = $result && execute_sql($sql);

        if ($setidnumber || $CURMAN->config->auto_assign_user_idnumber) {
            $sql = "UPDATE {$CFG->prefix}user
                       SET idnumber = username
                     WHERE idnumber=''
                       AND username != 'guest'
                       AND deleted = 0
                       AND confirmed = 1
                       AND mnethostid = {$CFG->mnet_localhost_id}";
            $result = $result && execute_sql($sql);
        }

        $rs = get_recordset_select('user',
                 "username != 'guest'
              AND deleted = 0
              AND confirmed = 1
              AND mnethostid = {$CFG->mnet_localhost_id}
              AND idnumber != ''
              AND timemodified >= $fromtime
              AND NOT EXISTS (SELECT 'x'
                              FROM {$CFG->prefix}crlm_user cu
                              WHERE cu.idnumber = {$CFG->prefix}user.idnumber)");

        if ($rs) {
            require_once $CFG->dirroot . '/curriculum/config.php';
            require_once CURMAN_DIRLOCATION . '/cluster/profile/lib.php';

            while ($user = rs_fetch_next_record($rs)) {
                // FIXME: shouldn't depend on cluster functionality -- should
                // be more modular
                cluster_profile_update_handler($user);
            }
        }
        return $result;
    }


    /**
     * Migrate a single Moodle user to the Curriculum Management system.  Will
     * only do this for users who have an idnumber set.
     */
    function cm_moodle_user_to_cm($mu) {
        global $CURMAN, $CFG;
        require_once(CURMAN_DIRLOCATION.'/lib/customfield.class.php');
        require_once(CURMAN_DIRLOCATION . '/lib/user.class.php');
        require_once($CFG->dirroot . '/user/profile/lib.php');
        // re-fetch, in case this is from a stale event
        $mu = addslashes_recursive($CURMAN->db->get_record('user', 'id', $mu->id));
        if (empty($mu->idnumber) && $CURMAN->config->auto_assign_user_idnumber) {
            $mu->idnumber = $mu->username;
            $CURMAN->db->update_record('user', $mu);
        }
        if (empty($mu->idnumber)) {
            return true;
        } else if (empty($mu->country)) {
            //this is necessary because PM requires this field
            return true;
        } else if ($cu = $CURMAN->db->get_record('crlm_user', 'idnumber', $mu->idnumber)) {
            $cu = new user(addslashes_recursive($cu));

            // synchronize any profile changes
            $cu->username = $mu->username;
            $cu->password = $mu->password;
            $cu->idnumber = $mu->idnumber;
            $cu->firstname = $mu->firstname;
            $cu->lastname = $mu->lastname;
            $cu->email = $mu->email;
            $cu->address = $mu->address;
            $cu->city = $mu->city;
            $cu->country = $mu->country;
            $cu->phone = empty($mu->phone1)?empty($cu->phone)? '': $cu->phone: $mu->phone1;
            $cu->phone2 = empty($mu->phone2)?empty($cu->phone2)? '': $cu->phone2: $mu->phone2;
            $cu->language = empty($mu->lang)?empty($cu->language)? '': $cu->language: $mu->lang;
            $cu->timemodified = time();

            // synchronize custom profile fields
            profile_load_data($mu);
            $fields = field::get_for_context_level(context_level_base::get_custom_context_level('user', 'block_curr_admin'));
            $fields = $fields ? $fields : array();
            require_once (CURMAN_DIRLOCATION . '/plugins/moodle_profile/custom_fields.php');
            foreach ($fields as $field) {
                $field = new field($field);
                if (isset($field->owners['moodle_profile']) && $field->owners['moodle_profile']->exclude == cm_moodle_profile::sync_from_moodle) {
                    $fieldname = "field_{$field->shortname}";
                    $cu->$fieldname = $mu->{"profile_field_{$field->shortname}"};
                }
            }
            $cu->update();
        } else {
            $cu = new user();
            $cu->username = $mu->username;
            $cu->password = $mu->password;
            $cu->idnumber = $mu->idnumber;
            $cu->firstname = $mu->firstname;
            $cu->lastname = $mu->lastname;
            $cu->email = $mu->email;
            $cu->address = $mu->address;
            $cu->city = $mu->city;
            $cu->country = $mu->country;
            $cu->phone = $mu->phone1;
            $cu->phone2 = $mu->phone2;
            $cu->language = $mu->lang;
            $cu->transfercredits = 0;
            $cu->timecreated = $cu->timemodified = time();

            // synchronize profile fields
            profile_load_data($mu);
            $fields = field::get_for_context_level(context_level_base::get_custom_context_level('user', 'block_curr_admin'));
            $fields = $fields ? $fields : array();
            require_once (CURMAN_DIRLOCATION . '/plugins/moodle_profile/custom_fields.php');
            foreach ($fields as $field) {
                $field = new field($field);
                if (isset($field->owners['moodle_profile']) && $field->owners['moodle_profile']->exclude == cm_moodle_profile::sync_from_moodle) {
                    $fieldname = "field_{$field->shortname}";
                    $cu->$fieldname = $mu->{"profile_field_{$field->shortname}"};
                }
            }

            $cu->add();
        }
        return true;
    }

    /**
     * Calculate statistic about curriculum user idnumbers that are
     * associated to multiple crlm_user records
     *
     * @return  Array of objects which contain statistics about idnumber,
     *          or false on error
     */
    function cm_calculate_duplicate_user_info() {
        global $CFG, $CURMAN;

        $user_table = $CURMAN->db->prefix_table('crlm_user');
        $enrolment_table = $CURMAN->db->prefix_table('crlm_class_enrolment');
        $class_graded_table = $CURMAN->db->prefix_table('crlm_class_graded');

        $results = array();

        $user_sql = "SELECT idnumber,
                     COUNT(*) AS num
                     FROM
                     {$user_table} user
                     GROUP BY idnumber
                     HAVING num > 1";

        if($user_recordset = get_recordset_sql($user_sql)) {
            while(!$user_recordset->EOF) {

                $results[$user_recordset->fields['idnumber']]->numuserrecords = $user_recordset->fields['num'];

                $enrolment_sql = "SELECT COUNT(DISTINCT enrolment.userid) AS num_users_in_classes
                                  FROM
                                  {$user_table} user
                                  JOIN
                                  {$enrolment_table} enrolment
                                  ON user.id = enrolment.userid
                                  WHERE user.idnumber = '" . addslashes($user_recordset->fields['idnumber']) . "'";

                $class_sql = "SELECT COUNT(DISTINCT enrolment.userid) AS num_users_graded
                              FROM
                              {$user_table} user
                              JOIN
                              {$enrolment_table} enrolment
                              ON user.id = enrolment.userid
                              JOIN
                              {$class_graded_table} class_grade
                              ON enrolment.userid = class_grade.userid
                              AND enrolment.classid = class_grade.classid
                              WHERE user.idnumber = '" . addslashes($user_recordset->fields['idnumber']) . "'";

                $no_class_sql = "SELECT COUNT(DISTINCT user.id) AS num_users_not_graded
                                 FROM
                                 {$user_table} user
                                 JOIN
                                 {$enrolment_table} enrolment
                                 ON user.id = enrolment.userid
                                 LEFT JOIN
                                 {$class_graded_table} class_grade
                                 ON enrolment.userid = class_grade.userid
                                 AND enrolment.classid = class_grade.classid
                                 WHERE user.idnumber = '" . addslashes($user_recordset->fields['idnumber']) . "'
                                 AND class_grade.id IS NULL";

                $class_completion_sql = "SELECT COUNT(DISTINCT user.id) AS num_completed
                                         FROM
                                         {$user_table} user
                                         JOIN
                                         {$enrolment_table} enrolment
                                         ON user.id = enrolment.userid
                                         WHERE user.idnumber = '" . addslashes($user_recordset->fields['idnumber']) . "'
                                         AND enrolment.completestatusid = " . STUSTATUS_PASSED;

                $class_graded_sql = "SELECT COUNT(DISTINCT user.id) AS num_grades_locked
                                     FROM
                                     {$user_table} user
                                     JOIN
                                     {$class_graded_table} graded
                                     ON user.id = graded.userid
                                     WHERE user.idnumber = '" . addslashes($user_recordset->fields['idnumber']) . "'
                                     AND graded.locked = 1";

                if($enrolment_record = get_record_sql($enrolment_sql) and
                   $no_class_record = get_record_sql($no_class_sql) and
                   $class_record = get_record_sql($class_sql) and
                   $class_completion_record = get_record_sql($class_completion_sql) and
                   $class_graded_record = get_record_sql($class_graded_sql)) {

                    $results[$user_recordset->fields['idnumber']]->numenrolled = $enrolment_record->num_users_in_classes;
                    $results[$user_recordset->fields['idnumber']]->numgraded = $class_record->num_users_graded;
                    $results[$user_recordset->fields['idnumber']]->numnotgraded = $no_class_record->num_users_not_graded;
                    $results[$user_recordset->fields['idnumber']]->numcompleted = $class_completion_record->num_completed;
                    $results[$user_recordset->fields['idnumber']]->numgradeslocked = $class_graded_record->num_grades_locked;

                } else {
                    return false;
                }

                $user_recordset->MoveNext();
            }
        }

        return $results;

    }

    /**
     * Notifies appropriate users about duplicate idnumbers
     * in curriculum user info
     *
     * @param   output_to_screen  Determines whether we should output this report
     *                            to the screen
     * @return  true on success, false otherwise
     */
    function cm_notify_duplicate_user_info($output_to_screen = false) {
        global $CFG;

        require_once($CFG->dirroot . '/message/lib.php');

        //calculate user info
        $info = cm_calculate_duplicate_user_info();

        if($info === false) {
            return $info;
        }

        if(empty($info)) {
            return true;
        }

        //construct the message
        $a = new stdClass;
        $a->sitename = get_field('course', 'fullname', 'id', SITEID);
        $a->url = $CFG->wwwroot;
        $message_text = get_string('duplicateuserheader', 'block_curr_admin', $a) . "\n\n";

        foreach($info as $key => $value) {
            $message_text .= get_string('duplicateuseridnumber', 'block_curr_admin') . $key . "\n";
            $message_text .= get_string('duplicateusernumusers', 'block_curr_admin') . $value->numuserrecords . "\n";
            $message_text .= get_string('duplicateusernumclassenrol', 'block_curr_admin') . $value->numenrolled . "\n";
            $message_text .= get_string('duplicateusernumgrades', 'block_curr_admin') . $value->numgraded . "\n";
            $message_text .= get_string('duplicateusernumnogrades', 'block_curr_admin') . $value->numnotgraded . "\n";
            $message_text .= get_string('duplicateusernumcompleted', 'block_curr_admin') . $value->numcompleted . "\n";
            $message_text .= get_string('duplicateusernumgradeslocked', 'block_curr_admin') . $value->numgradeslocked . "\n";
            $message_text .= "\n";
        }

        $message_html = nl2br($message_text);

        //send message to rladmin user if possible
        if($rladmin_user = get_record('user', 'username', 'rladmin')) {
            $result = message_post_message($rladmin_user, $rladmin_user, addslashes($message_html), FORMAT_HTML, 'direct');

            if($result === false) {
                return $result;
            }
        }

        //email to specified address
        $user_obj = new stdClass;
        $user_obj->email = CURR_ADMIN_DUPLICATE_EMAIL;
        $user_obj->mailformat = FORMAT_HTML;
        email_to_user($user_obj, get_admin(), get_string('duplicateuserheader', 'block_curr_admin', $a), $message_text, $message_html);

        //output to screen if possible
        if(!empty($output_to_screen)) {
            echo $message_html;
        }

        return true;

    }

    /**
     * Notifies appropriate users about possibly incorrect instructor assignments
     *
     * @param   output_to_screen  Determines whether we should output this report
     *                            to the screen
     * @return  true on success, false otherwise
     */
    function cm_notify_incorrect_instructor_assignment($output_to_screen = false) {
        global $CFG;

        require_once($CFG->dirroot . '/message/lib.php');

        $context_course = CONTEXT_COURSE;

        $sql = "SELECT cminst.id, cmuser.firstname, cmuser.lastname, cmcourse.idnumber, cmcourse.name, cmclass.idnumber AS classidnumber
                  FROM {$CFG->prefix}crlm_class_instructor cminst
                  JOIN {$CFG->prefix}crlm_class_moodle cmmoodle ON cmmoodle.id = cminst.classid
                  JOIN {$CFG->prefix}context ctx ON ctx.instanceid = cmmoodle.moodlecourseid
                                                AND ctx.contextlevel = {$context_course}
                  JOIN {$CFG->prefix}crlm_user cmuser ON cmuser.id = cminst.userid
                  JOIN {$CFG->prefix}user muser ON cmuser.idnumber = muser.idnumber
                  JOIN {$CFG->prefix}role_assignments ra ON ra.contextid = ctx.id
                                                        AND ra.roleid IN ({$CFG->coursemanager})
                                                        AND ra.userid = muser.id
             LEFT JOIN {$CFG->prefix}crlm_class cmclass ON cmclass.id = cminst.classid
             LEFT JOIN {$CFG->prefix}crlm_course cmcourse ON cmcourse.id = cmclass.courseid
             LEFT JOIN {$CFG->prefix}crlm_class_moodle cmmoodle2 ON cmmoodle2.classid = cmclass.id
             LEFT JOIN {$CFG->prefix}context ctx2 ON ctx2.instanceid = cmmoodle2.moodlecourseid
                                                 AND ctx2.contextlevel = {$context_course}
             LEFT JOIN {$CFG->prefix}role_assignments ra2 ON ra2.contextid = ctx2.id
                                                         AND ra2.roleid IN ({$CFG->coursemanager})
                                                         AND ra2.userid = muser.id
                 WHERE ctx2.id IS NOT NULL AND ra2.id IS NULL";

        $records = get_records_sql($sql);

        if(empty($info)) {
            return true;
        }

        //construct the message
        $a = new stdClass;
        $a->sitename = get_field('course', 'fullname', 'id', SITEID);
        $a->url = $CFG->wwwroot;
        $message_text = get_string('incorrectinstructorassignmentheader', 'block_curr_admin', $a) . "\n\n";

        foreach($info as $key => $value) {
            $message_text .= get_string('incorrectinstructorrecord', 'block_curr_admin', $value) . "\n";
        }

        $message_html = nl2br($message_text);

        //send message to rladmin user if possible
        if($rladmin_user = get_record('user', 'username', 'rladmin')) {
            $result = message_post_message($rladmin_user, $rladmin_user, addslashes($message_html), FORMAT_HTML, 'direct');

            if($result === false) {
                return $result;
            }
        }

        //email to specified address
        $user_obj = new stdClass;
        $user_obj->email = CURR_ADMIN_DUPLICATE_EMAIL;
        $user_obj->mailformat = FORMAT_HTML;
        email_to_user($user_obj, get_admin(), get_string('incorrectinstructorassignmentheader', 'block_curr_admin', $a), $message_text, $message_html);

        //output to screen if possible
        if(!empty($output_to_screen)) {
            echo $message_html;
        }

        return true;

    }

    /**
     * Retrieves the field data from a particular context and field name
     * This should never be used unless you can't access the cuurriculum admin classes
     *
     * @param    object  $context    The context instance, as retrieved from get_context_instance
     * @param    string  $shortname  The field's shortname
     * @return   string              The field's value
     *
     */
    function cm_get_field_data($context, $shortname) {
        if($field = get_record('crlm_field', 'shortname', $shortname)) {
            $table_name = 'crlm_field_data_' . $field->datatype;
            if($field_data = get_record($table_name, 'contextid', $context->id, 'fieldid', $field->id)) {
                return $field_data->data;
            }
        }
        return null;
    }

    /**
     * Specifies whether the CM system should link to a Jasper
     * reporting server
     *
     * @return  boolean  true if applicable, otherwise false
     */
    function cm_jasper_link_enabled() {
        $show_jasper_link = false;

        //check the necessary auth plugins
        $auths_enabled = get_enabled_auth_plugins();
        $mnet_auth_enabled = in_array('mnet', $auths_enabled);
        $elis_auth_enabled = in_array('elis', $auths_enabled);

        if ($mnet_auth_enabled && $elis_auth_enabled) {
            //check the necessary config data
            $jasper_shortname = get_config('auth/elis', 'jasper_shortname');
            $jasper_wwwroot = get_config('auth/elis', 'jasper_wwwroot');

            if ($jasper_shortname !== false && $jasper_wwwroot !== false) {
                //don't respond to bogus data
                $jasper_shortname = trim($jasper_shortname);
                $jasper_wwwroot = trim($jasper_wwwroot);

                if (strlen($jasper_shortname) > 0 && strlen($jasper_wwwroot) > 0) {
                    $show_jasper_link = true;
                }
            }
        }

        return $show_jasper_link;
    }

    /**
     * Given a float grade value, return a representation of the number meant for UI display
     *
     * An integer value will be returned without any decimals included and a true floating point value
     * will be reduced to only displaying two decimal digits without any rounding.
     *
     * @param float $grade The floating point grade value
     * @return string The grade value formatted for display
     */
    function cm_display_grade($grade) {
        if (preg_match('/([0-9]+)([\.[0-9]+|\.0+])/', $grade, $matches)) {
            if (count($matches) == 3) {
                return ($matches[2] == 0 ? $matches[1] : sprintf("%0.2f", $matches[0]));
            }
        }

        return $grade; // This probably isn't a float value
    }

?>
