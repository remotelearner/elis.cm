<?php
/**
 * Cron task for ETL processing.  Called from an external cron daemon for now,
 * until we implement a cron system.
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

set_time_limit(0);
$starttime = microtime();

/// The following is a hack necessary to allow this script to work well
/// from the command line. (from admin/cron.php)
define('FULLME', 'cron');

/// Do not set moodle cookie because we do not need it here, it is better to emulate session
$nomoodlecookie = true;

/// The current directory in PHP version 4.3.0 and above isn't necessarily the
/// directory of the script when run from the command line. The require_once()
/// would fail, so we'll have to chdir()

if (!isset($_SERVER['REMOTE_ADDR']) && isset($_SERVER['argv'][0])) {
    chdir(dirname($_SERVER['argv'][0]));
}

require_once(dirname(__FILE__) . '/../../config.php');

/// Extra debugging (set in config.php)
if (!empty($CFG->showcronsql)) {
    $db->debug = true;
}
if (!empty($CFG->showcrondebugging)) {
    $CFG->debug = DEBUG_DEVELOPER;
    $CFG->debugdisplay = true;
}

/// extra safety
@session_write_close();

/// check if execution allowed
if (isset($_SERVER['REMOTE_ADDR'])) { // if the script is accessed via the web.
    if (!empty($CFG->cronclionly)) {
        // This script can only be run via the cli.
        print_error('cronerrorclionly', 'admin');
        exit;
    }
    // This script is being called via the web, so check the password if there is one.
    if (!empty($CFG->cronremotepassword)) {
        $pass = optional_param('password', '', PARAM_RAW);
        if($pass != $CFG->cronremotepassword) {
            // wrong password.
            print_error('cronerrorpassword', 'admin');
            exit;
        }
    }
}


/// emulate normal session
$SESSION = new object();
$USER = get_admin();      /// Temporarily, to provide environment for this script

/// ignore admins timezone, language and locale - use site deafult instead!
$USER->timezone = $CFG->timezone;
$USER->lang = '';
$USER->theme = '';
course_setup(SITEID);

/// send mime type and encoding
if (check_browser_version('MSIE')) {
    //ugly IE hack to work around downloading instead of viewing
    @header('Content-Type: text/html; charset=utf-8');
    echo "<xmp>"; //<pre> is not good enough for us here
} else {
    //send proper plaintext header
    @header('Content-Type: text/plain; charset=utf-8');
}

/// no more headers and buffers
while(@ob_end_flush());

mtrace('ELIS ETL cron is no longer needed');

/// finish the IE hack
if (check_browser_version('MSIE')) {
    echo "</xmp>";
}

?>
