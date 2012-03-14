<?php
/*
*  ELIS(TM): Enterprise Learning Intelligence Suite
*
*  Copyright (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
*
*  This program is free software: you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation, either version 3 of the License, or
*  (at your option) any later version.
*
*  This program is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  You should have received a copy of the GNU General Public License
*  along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
*  @package    elis
*  @subpackage curriculummanagement
*  @author     Remote-Learner.net Inc
*  @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
*  @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
*/

defined('MOODLE_INTERNAL') || die();

//require_once(CURMAN_DIRLOCATION . '/form/cmform.class.php');
require_once elispm::file('form/cmform.class.php');

class clustertrackform extends cmform {
    /**
     * items in the form
     */
    public function definition() {
        parent::definition();

        $mform = &$this->_form;

        $mform->addElement('hidden', 'trackid');
        $mform->addElement('hidden', 'clusterid');

        $mform->addElement('advcheckbox', 'autoenrol', null, get_string('usersettrack_auto_enrol', 'elis_program'), null, array('0', '1'));
        $mform->setDefault('autoenrol', '1');
        $mform->addHelpButton('autoenrol', 'usersettrackform:autoenrol', 'elis_program');

        $this->add_action_buttons();
    }
}
?>
