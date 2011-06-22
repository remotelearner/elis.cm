<?php
/**
 * Curriculum Management dashboard
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

require_once (CURMAN_DIRLOCATION . '/lib/newpage.class.php');

/**
 * This page is just a dummy that allows generic linking to the dashboard
 * from the Curriculum Admin menu
 *
 */
class dashboardpage extends newpage {

    /**
     * Determines whether or not the current user can navigate to the
     * Curriculum Admin dashboard
     *
     * @return  boolean  Whether or not access is allowed
     *
     */
    function can_do_default() {
        //allow any logged-in user since the dashboard varies based on the user
        return isloggedin();
    }

    /**
     * Create a url to the current page (just points to the main curriculum index)
     *
     * @return moodle_url
     */
    function get_moodle_url($extra = array()) {
        $url = parent::get_moodle_url($extra);
        unset($url->params['s']);

        return $url;
    }

    function action_default() {
        global $CFG, $CURMAN, $USER;

        $context = get_context_instance(CONTEXT_SYSTEM);
        if (has_capability('block/curr_admin:managecurricula', $context)) {
            echo print_heading_block('Administrator Dashboard', '', true);
            echo '<p>';
            echo get_string('elis_doc_class_link', 'block_curr_admin');
            echo '</p><p>';
            echo get_string('health_check_link', 'block_curr_admin', $CFG->wwwroot);
            echo print_heading(get_string('elisversion', 'block_curr_admin') . ': ' . $CURMAN->release,
                               'right', '4', 'main', true);
        }

        if ($cmuid = cm_get_crlmuserid($USER->id)) {
            $user = new user($cmuid);
            echo $user->get_dashboard();
        }
    }
}

?>