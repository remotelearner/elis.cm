<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2011 Remote-Learner.net Inc (http://www.remote-learner.net)
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

require_once($CFG->dirroot .'/elis/program/lib/setup.php');
require_once(elispm::lib('page.class.php'));    // pm_page
require_once(elispm::lib('deprecatedlib.php')); // cm_get_crlmuserid()
require_once(elispm::lib('data/curriculumstudent.class.php'));

/// The main management page.
class certificatelistpage extends pm_page {
    var $pagename = 'certlist';
    var $section = 'curr';

    function can_do_default() {
        if (!empty(elis::$config->elis_program->disablecertificates)) {
            return false;
        }

        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('elis/program:viewownreports', $context);
    }

    function get_title_default() {
        return get_string('certificatelist', 'elis_program');
    }

    function build_navbar_default($who = null) {
        $page = new certificatelistpage(array());
        $this->navbar->add($this->get_title_default(), $page->url);
    }

    /**
     * List the certificates available to be printed.
     * @return unknown_type
     */
    function display_default() { // action_default()
        global $CFG, $USER;

        // This is for a Moodle user, so get the Curriculum user id.
        $cuserid = cm_get_crlmuserid($USER->id);
        if (empty($cuserid)) {
            print_error('notelisuser', 'elis_program');
        }

        $curasses = curriculumstudent::get_completed_for_user($cuserid);
        if (count($curasses) == 0) {
            print_string('certificates_none_earned','elis_program');
            return;
        }

        print_string('certificates_earned','elis_program');

        echo "<UL>\n";
        foreach ($curasses as $curass) {
            echo "<LI><a href=\"certificate.php?id={$curass->id}\" target=\"_blank\">{$curass->curriculum->name}</a></LI>\n";
        }
        echo "</UL>\n";
    }
}

