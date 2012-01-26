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
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */


class enrolment_plugin_elis {
    function print_entry($course) {
        // FIXME: try to enrol from course catalog?
        print_error('nomoodleenrol', 'enrol_elis');
    }

    function check_entry($form, $course) {
        // FIXME: try to enrol from course catalog?
        print_error('nomoodleenrol', 'enrol_elis');
    }

    function check_group_entry($courseid, $password) {
        return false;
    }

    function get_access_icons($course) {
        return '';
    }

    function config_form($form) {
    }

    function process_config($config) {
    }
}

?>
