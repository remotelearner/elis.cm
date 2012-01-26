<?php
/**
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

require_once ($CFG->dirroot . '/lib/formslib.php');
require_once (CURMAN_DIRLOCATION . '/form/selectionform.class.php');

class bulkuserform extends selectionform {
    function definition() {
        global $CFG;

        $mform =& $this->_form;

        $actions = array('inactive' => get_string('mark_inactive','block_curr_admin'),
                         'delete' => get_string('delete'));
        $mform->addElement('select', 'do', get_string('withselectedusers'), $actions);

        parent::definition();
    }
}
?>
