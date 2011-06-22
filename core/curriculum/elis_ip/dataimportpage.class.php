<?php
/**
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

require_once($CFG->dirroot . '/curriculum/config.php');
require_once(CURMAN_DIRLOCATION . '/lib/newpage.class.php');

class dataimportpage extends newpage {
    var $pagename = 'dim';
    var $section = 'admn';

    function can_do_default() {
        return true;
    }

    function get_title_default() {
        return get_string('dataimport', 'block_curr_admin');
    }

    function action_default() {
        print '<span class="ip_warning">' . get_string('ip_disabled_warning', 'block_curr_admin') . '</span>';
        print '<br /><br /><p>' . get_string('ip_instructions', 'block_curr_admin', 'http://remote-learner.net/contactme') . '</p>';
    }

}

?>