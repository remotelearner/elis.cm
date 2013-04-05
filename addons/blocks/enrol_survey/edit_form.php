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
 * @subpackage enrol_survey
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

class block_enrol_survey_edit_form extends block_edit_form {
    protected function specific_definition($mform) {

        $mform->addElement('text', 'config_title', get_string('config_title', 'block_enrol_survey'));

        $availableintervals = array(
            0        => get_string('never'),
            HOURSECS => get_string('hour'),
            DAYSECS  => get_string('day'),
            YEARSECS => get_string('year')
        );

        $mform->addElement('select', 'config_cron_time', get_string('config_cron_time', 'block_enrol_survey'), $availableintervals);

    }
}

