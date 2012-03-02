<?php
/**
 * Curriculum Management dashboard
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

require_once (CURMAN_DIRLOCATION . '/lib/newpage.class.php');
require_once (CURMAN_DIRLOCATION . '/healthpage.class.php');

/**
 * This page is just a dummy that allows generic linking to the dashboard
 * from the Curriculum Admin menu
 *
 */
class dashboardpage extends newpage {
    // Arrays for which components last cron runtimes to include
    private $blocks = array('curr_admin'); // empty array for none
    private $plugins = array(); // TBD: 'elis_core' ?

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

    function last_cron_runtimes() {
        $description = '';
        foreach ($this->blocks as $block) {
            $lastcron = get_field('block', 'lastcron', 'name', $block);
            $a = new stdClass;
            $a->name = $block;
            $a->lastcron = $lastcron ? userdate($lastcron) : get_string('cron_notrun', 'block_curr_admin');
            $description .= get_string('health_cron_block', 'block_curr_admin', $a);
        }
        foreach ($this->plugins as $plugin) {
            $lastcron = get_field('config_plugins', 'value', 'plugin', $plugin, 'name', 'lastcron');
            $a = new stdClass;
            $a->name = $plugin;
            $a->lastcron = $lastcron ? userdate($lastcron) : get_string('cron_notrun', 'block_curr_admin');
            $description .= get_string('health_cron_plugin', 'block_curr_admin', $a);
        }
        $lasteliscron = get_field('elis_scheduled_tasks', 'MAX(lastruntime)', '', '');
        $lastcron = $lasteliscron ? userdate($lasteliscron) : get_string('cron_notrun', 'block_curr_admin');
        $description .= get_string('health_cron_elis', 'block_curr_admin', $lastcron);
        return $description;
    }

    function action_default() {
        global $CFG, $CURMAN, $USER;

        $context = get_context_instance(CONTEXT_SYSTEM);
        if (has_capability('block/curr_admin:managecurricula', $context)) {
            echo print_heading_block('Administrator Dashboard', '', true);
            echo '<p>';
            echo get_string('elis_doc_class_link', 'block_curr_admin');
            echo '</p><p>';
            echo $this->last_cron_runtimes();
            echo '</p>';
            $healthpg = new healthpage();
            if ($healthpg->can_do_default()) {
                echo '<p>', get_string('health_check_link', 'block_curr_admin', $CFG->wwwroot), '</p>';
            }
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
