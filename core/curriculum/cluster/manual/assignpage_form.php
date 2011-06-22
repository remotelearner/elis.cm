<?php
/**
 * Form for manual cluster assignment page window
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

require_once CURMAN_DIRLOCATION . '/form/cmform.class.php';


class assignpage_form extends cmform {
    function definition() {
        global $CFG;

        $mform =& $this->_form;

        $mform->addElement('hidden', 'userid');
        $mform->addElement('hidden', 'clusterid');
        
        $mform->addElement('advcheckbox', 'autoenrol', get_string('cluster_manual_autoenrol_label', 'block_curr_admin'));
        $mform->addElement('static', '', '', get_string('cluster_manual_autoenrol_help', 'block_curr_admin'));
        $mform->addElement('advcheckbox', 'leader', get_string('cluster_manual_leader_label', 'block_curr_admin'));
        $mform->addElement('static', '', '', get_string('cluster_manual_leader_help', 'block_curr_admin'));

        $this->add_action_buttons();

        parent::definition();
    }
}

?>