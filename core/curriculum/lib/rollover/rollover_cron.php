<?php
/**
 * Process the rollover queue as a cron job.
 *
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

    // BEGIN stuff copied from /admin/cron.php.  See that file for comments.
    set_time_limit(0);
    $starttime = microtime();

    define('FULLME', 'cron');

    $nomoodlecookie = true;

    if (!isset($_SERVER['REMOTE_ADDR']) && isset($_SERVER['argv'][0])) {
        chdir(dirname($_SERVER['argv'][0]));
    }

    require_once(dirname(__FILE__) . '/../config.php');
    require_once($CFG->libdir.'/adminlib.php');

    if (!empty($CFG->showcronsql)) {
        $db->debug = true;
    }
    if (!empty($CFG->showcrondebugging)) {
        $CFG->debug = DEBUG_DEVELOPER;
        $CFG->displaydebug = true;
    }

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

    $SESSION = new object();
    $USER = get_admin();      /// Temporarily, to provide environment for this script

    $USER->timezone = $CFG->timezone;
    $USER->lang = '';
    $USER->theme = '';
    course_setup(SITEID);

    @session_write_close();

    if (check_browser_version('MSIE')) {
        //ugly IE hack to work around downloading instead of viewing
        @header('Content-Type: text/html; charset=utf-8');
        echo "<xmp>"; //<pre> is not good enough for us here
    } else {
        //send proper plaintext header
        @header('Content-Type: text/plain; charset=utf-8');
    }

    while(@ob_end_flush());

    @raise_memory_limit('128M');

    $timenow  = time();

    mtrace("Server Time: ".date('r',$timenow)."\n\n");
    // END stuff copied from /admin/cron.php.

    require_once $CFG->dirroot . '/course/sharelib.php';

    // default: start running last task after 5 minutes
    $endtime = time() + (5 * 60);

    $status = TRUE;
    while ($status && time() < $endtime) {
        if (!process_queue()) {
            break;
        }
    }

    mtrace('Remaining tasks in rollover queue: '.count_records('block_admin_rollover_queue'));

    // BEGIN stuff copied from /admin/cron.php.  See that file for comments.
    @session_unset();
    @session_destroy();

    $difftime = microtime_diff($starttime, microtime());
    mtrace("Execution took ".$difftime." seconds");

/// finish the IE hack
    if (check_browser_version('MSIE')) {
        echo "</xmp>";
    }
    // END stuff copied from /admin/cron.php.

?>
