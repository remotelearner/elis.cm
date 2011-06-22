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


require_once CURMAN_DIRLOCATION . '/lib/newpage.class.php';
require_once CURMAN_DIRLOCATION . '/lib/curriculumstudent.class.php';
require_once CURMAN_DIRLOCATION . '/lib/table.class.php';

/// The main management page.
class certificatelistpage extends newpage {
    var $pagename = 'certlist';
    var $section = 'curr';

    function can_do_default() {
        global $CURMAN;
        if (!empty($CURMAN->config->disablecertificates)) {
            return false;
        }

        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('block/curr_admin:viewownreports', $context);
    }

    function get_title_default() {
        return get_string('certificatelist', 'block_curr_admin');
    }

    function get_navigation_default() {
        $page = new certificatelistpage(array());
        return array(
            array('name' => get_string('certificatelist', 'block_curr_admin'),
                  'link' => $page->get_url()),
            );
    }

    /**
     * List the certificates available to be printed.
     * @return unknown_type
     */
    function action_default() {
        global $CFG, $USER, $CURMAN;

        // This is for a Moodle user, so get the Curriculum user id.
        $cuserid = cm_get_crlmuserid($USER->id);

        $curasses = curriculumstudent::get_completed_for_user($cuserid);

        if(count($curasses) == 0) {
            print_string('certificates_none_earned','block_curr_admin');
            return;
        }

        print_string('certificates_earned','block_curr_admin');

        echo "<UL>\n";

        foreach($curasses as $curass) {
            echo "<LI><a href=\"certificate.php?id={$curass->id}\">{$curass->curriculum->name}</a>\n";
        }

        echo "</UL>\n";
    }
}

?>