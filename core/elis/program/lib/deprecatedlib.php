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
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

define('USE_OLD_CHECKBOX', 1);

/** Display an standard html checkbox with an optional label
 * NOTE: this version is from Moodle 1.9x it *SUPPORTS* script tags for onclick
 *       (unlike the version in /lib/deprecatedlib.php)
 *
 * @param string  $name    The name of the checkbox
 * @param string  $value   The valus that the checkbox will pass when checked
 * @param boolean $checked The flag to tell the checkbox initial state
 * @param string  $label   The label to be showed near the checkbox
 * @param string  $alt     The info to be inserted in the alt tag
 */
function _print_checkbox($name, $value, $checked = true, $label = '', $alt = '', $script = '', $return = false) {
  if (defined('USE_OLD_CHECKBOX')) {
    static $idcounter = 0;

    if (!$name) {
        $name = 'unnamed';
    }

    if ($alt) {
        $alt = strip_tags($alt);
    } else {
        $alt = 'checkbox';
    }

    if ($checked) {
        $strchecked = ' checked="checked"';
    } else {
        $strchecked = '';
    }

    $htmlid = 'auto-cb'.sprintf('%04d', ++$idcounter);
    $output  = '<span class="checkbox '.$name."\">";
    $output .= '<input name="'.$name.'" id="'.$htmlid.'" type="checkbox" value="'.$value.'" alt="'.$alt.'"'.$strchecked.' '.((!empty($script)) ? ' onclick="'.$script.'" ' : '').' />';
    if(!empty($label)) {
        $output .= ' <label for="'.$htmlid.'">'.$label.'</label>';
    }
    $output .= '</span>'."\n";

    if (empty($return)) {
        echo $output;
    } else {
        return $output;
    }
  } else {
    $output = html_writer::checkbox($name, $value, $checked, $label, empty($script) ? null : array('onclick' => $script));
    if (empty($return)) {
        echo $output;
    } else {
        return $output;
    }
  }
}

/**
 * Function to get a parameter from _POST or _GET. If not present, will return
 * the value defined in the $default parameter, or false if not defined.
 *
 * @param string $param     The parameter to look for.
 * @param string $default   Default value to return if not found.
 * @return string | boolean The value of the parameter, or $default.
 */
function cm_get_param($param, $default = false) {
    if (is_array($default)) {
        return optional_param_array($param, $default, PARAM_CLEAN);
    }
    return optional_param($param, $default, PARAM_CLEAN);
}

/**
 * Return an error message formatted the way the application wants it.
 *
 * @param string $message The text to display.
 * @return string The formatted message.
 */
function cm_error($message) {
    //global $OUTPUT;
    /// Using Moodle...
    return notify($message, 'notifyproblem', 'center', true);
    //return $OUTPUT->box($message, 'errorbox');
}

/**
 * Returns a delete form formatted for the application.
 *
 * @param string $url The page to call.
 * @param string $message The message to ask.
 * @param array $optionsyes The form attributes for the "yes" portion.
 * @param array $optionsno The form attributes for the "no" portion.
 * @uses $OUTPUT
 * @return string The HTML for the form.
 *
 */
function cm_delete_form($url='', $message='', $optionsyes=NULL, $optionsno=NULL) {
    global $OUTPUT;
    $methodyes = 'post';
    $methodno  = 'get';
    $linkyes   = $url;
    $linkno    = $url;

    $buttoncontinue = new single_button(new moodle_url($linkyes, $optionsyes), get_string('yes'), $methodyes);
    $buttoncancel   = new single_button(new moodle_url($linkno, $optionsno), get_string('no'), $methodno);

    return $OUTPUT->confirm($message, $buttoncontinue, $buttoncancel);
}

/**
 * Get Moodle user id for a given curriculum user id.
 *
 * @param int $userid  the cm userid
 * @uses $CFG
 * @uses $DB
 * @return int  the corresponding Moodle userid
 */
function cm_get_moodleuserid($userid) {
    global $CFG, $DB;
    require_once elispm::lib('data/user.class.php');

    $select = 'SELECT mu.id ';
    $from   = 'FROM {'. user::TABLE .'} cu ';
    $join   = 'INNER JOIN {user} mu ON mu.idnumber = cu.idnumber AND mu.mnethostid = ? AND mu.deleted = 0 ';
    $where  = 'WHERE cu.id = ? ';
    return $DB->get_field_sql($select.$from.$join.$where, array($CFG->mnet_localhost_id, $userid));
}

/**
 * Get Moodle user record for a given curriculum user id.
 *
 * @param int $userid  the cm userid
 * @uses $CFG
 * @uses $DB
 * @return object the corresponding Moodle user object
 */
function cm_get_moodleuser($userid) {
    global $CFG, $DB;
    require_once elispm::lib('data/user.class.php');

    $select = 'SELECT mu.* ';
    $from   = 'FROM {'. user::TABLE .'} cu ';
    $join   = 'INNER JOIN {user} mu ON mu.idnumber = cu.idnumber AND mu.mnethostid = ? AND mu.deleted = 0 ';
    $where  = 'WHERE cu.id = ? ';
    return $DB->get_record_sql($select.$from.$join.$where, array($CFG->mnet_localhost_id, $userid));
}

/**
 * Get Curriculum user id for a given Moodle user id.
 *
 * @param int $userid  the Moodle userid
 * @uses $DB
 */
function cm_get_crlmuserid($userid) {
    global $DB;
    require_once elispm::lib('data/user.class.php');

    $select = 'SELECT cu.id ';
    $from   = 'FROM {user} mu ';
    $join   = 'INNER JOIN {'. user::TABLE .'} cu ON cu.idnumber = mu.idnumber ';
    $where  = 'WHERE mu.id = ? ';
    return $DB->get_field_sql($select.$from.$join.$where, array($userid));
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
function cm_print_date_selector($day, $month, $year, $currenttime = 0, $return = false, $script = '') {
    if (!$currenttime) {
        $currenttime = time();
    }

    $currentdate = usergetdate($currenttime); // was cm_usergetdate

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
    return cm_choose_from_menu($days,   $day,   $currentdate['mday'], '', $script, '0', $return)
           . cm_choose_from_menu($months, $month, $currentdate['mon'],  '', $script, '0', $return)
           . cm_choose_from_menu($years,  $year,  $currentdate['year'], '', $script, '0', $return);
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
    $currentdate = usergetdate($currenttime); // was cm_usergetdate
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
           . cm_choose_from_menu($minutes, $minute, $currentdate['minutes'], '','','0',$return);
}


/**
 * Given an array of value, creates a popup menu to be part of a form
 * $options["value"]["label"]
 *
 * @param    type description
 * @todo Finish documenting this function
 */
function cm_choose_from_menu($options, $name, $selected = '', $nothing = 'choose', $script = '',
                             $nothingvalue = '0', $return = false, $disabled = false,
                             $tabindex = 0, $id = '') {
    if ($nothing == 'choose') {
        //$nothing = get_string('choose') .'...';
        $nothing = get_string('choose', 'elis_program');
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
 * Determine the access level for the given user.
 *
 * @uses $USER
 * @param int $uid The user ID (optional)
 * @return string|bool The access level, or False on error.
 */
function cm_determine_access($uid = false) {
    global $USER, $CFG, $DB;

    if (!$uid) {
        if (!isloggedin()) {
            return 'newuser';
        }
        $uid = $USER->id;
    }

    if (!$DB->record_exists('user', array('id' => $uid))) {
        return false;
    }

    $context = get_context_instance(CONTEXT_SYSTEM);

    //require_once($CFG->dirroot . '/curriculum/lib/cluster.class.php');

    if (has_capability('elis/program:manage', $context)) {
        return 'admin';
    //} else if (has_capability('elis/program:viewreports', $context)) {
    //    return 'reviewer';
    //} else if (has_capability('elis/program:viewgroupreports', $context)) {
    //    return 'groupreviewer';
    } else if (has_capability('elis/program:viewownreports', $context)){
        return 'student';
    }
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
 * Return a formatted date string from a timestamp.
 * Use this to keep all strings formatted the same way in the system.
 *
 */
function cm_timestamp_to_date($timestamp, $format=CURMAN_DATEFORMAT) {
    if (is_numeric($timestamp)) {
        return date($format, $timestamp);
    } else {
        return '';
    }
}

