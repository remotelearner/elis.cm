<?php
/**
 * Form used for editing / displaying default values for class records.
 *
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
 * @copyright  (C) 2008-2009 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

if(!defined('NO_ROLE_ID')) {
    define('NO_ROLE_ID', 0);
}

require_once(CURMAN_DIRLOCATION . '/form/cmform.class.php');

    class configclsdefaultform extends cmform {

        function definition() {
            global $USER, $CFG, $COURSE, $CURMAN;

            $mform =& $this->_form;

            $mform->addElement('header', 'clsdefault', get_string('defaultcls', 'block_curr_admin'));

            $mform->addElement('text', 'clsdftidnumber', get_string('class_idnumber', 'block_curr_admin') . ':');
            $mform->setType('idnumber', PARAM_TEXT);

            $mform->addElement('date_selector', 'clsdftstartdate', get_string('class_startdate', 'block_curr_admin') . ':', array('optional' => true));
            $mform->addElement('date_selector', 'clsdftenddate', get_string('class_enddate', 'block_curr_admin') . ':', array('optional' => true));

            $mform->addElement('time_selector', 'clsdftstarttime', get_string('class_starttime', 'block_curr_admin') . ':',
                               array('display_12h'=>$CURMAN->config->time_format_12h));

            $mform->addElement('time_selector', 'clsdftendtime', get_string('class_endtime', 'block_curr_admin') . ':',
                               array('display_12h'=>$CURMAN->config->time_format_12h));

            $mform->addElement('text', 'clsdftmaxstudents', get_string('class_maxstudents', 'block_curr_admin') . ':');
            $mform->setType('maxstudents', PARAM_INT);

            // Environment selector
            $envs = environment_get_listing();
            $envs = $envs ? $envs : array();

            $o_envs = array(get_string('none', 'block_curr_admin'));

            foreach ($envs as $env) {
                $o_envs[$env->id] = $env->name;
            }

            $mform->addElement('select', 'clsdftenvironmentid', get_string('environment', 'block_curr_admin') . ':',
                               $o_envs);

            $this->add_action_buttons();
        }

        function set_data($default_values, $slashed=false) {

            $default_values = clone $default_values;
            parent::set_data($default_values, $slashed);
        }

    }
?>