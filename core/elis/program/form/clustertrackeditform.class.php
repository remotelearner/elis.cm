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

class clustertrackeditform extends moodleform {

    var $cluster_fields = array();
    var $track_fields = array();

    function clustertrackeditform($action=null, $customdata=null, $method='post', $target='', $attributes=null, $editable=true) {
        $this->cluster_fields = array('name'    => get_string('userset_name','elis_program'),
                                      'display' => get_string('userset_description','elis_program'),);

        $this->track_fields = array('name'        => get_string('track_name', 'elis_program'),
                                    'description' => get_string('track_description', 'elis_program'),
                                    'parcur'      => get_string('track_parcur', 'elis_program'),
                                    'class'       => get_string('track_classes', 'elis_program'));

        parent::__construct($action, $customdata, $method, $target, $attributes, $editable);
    }

    function definition() {
        $mform = $this->_form;

        //cluster stuff
        $mform->addElement('header', 'clusterinfo', get_string('userset_info_group', 'elis_program'));

        foreach($this->cluster_fields as $id => $display) {
            $element =& $mform->createElement('text', 'cluster' . $id, $display);
            $element->freeze();
            $mform->addElement($element);
        }

        //track stuff
        $mform->addElement('header', 'trackinfo', get_string('track_info_group', 'elis_program'));

        foreach($this->track_fields as $id => $display) {
            $element =& $mform->createElement('text', 'track' . $id, $display);
            $element->freeze();
            $mform->addElement($element);
        }

        //association stuff
        $mform->addElement('header', 'associationinfo', get_string('association_info_group', 'elis_program'));
        $mform->addElement('advcheckbox', 'autoenrol', get_string('usersettrack_autoenrol', 'elis_program'), null, null, array('0', '1'));
        $mform->addHelpButton('autoenrol', 'usersettrackform:autoenrol', 'elis_program');

        $mform->addElement('hidden', 'association_id', '');

        $this->add_action_buttons();
    }

    function definition_after_data() {
        global $CFG, $DB;

        parent::definition_after_data();
        $mform =& $this->_form;

        if($association_id = $mform->getElementValue('association_id')) {
            if($record = $DB->get_record(clustertrack::TABLE, array('id'=> $association_id))) {
                if($cluster_record = $DB->get_record(userset::TABLE, array('id'=> $record->clusterid))) {
                    foreach($this->cluster_fields as $id => $display) {
                        $element =& $mform->getElement('cluster' . $id);
                        $element->setValue($cluster_record->{$id});
                    }
                }

                $params = array();
                $track_sql = "SELECT trk.*,
                                     cur.name AS parcur,
                                     (SELECT COUNT(*)
                                      FROM {".trackassignment::TABLE."}
                                      WHERE trackid = trk.id ) as class
                              FROM {".track::TABLE."} trk
                              JOIN {".curriculum::TABLE."} cur
                              ON trk.curid = cur.id
                              WHERE trk.defaulttrack = 0
                              AND trk.id = :trackid";

                $params['trackid'] = $record->trackid;
                if($track_record = $DB->get_record_sql($track_sql, $params)) {
                    foreach($this->track_fields as $id => $display) {
                        $element =& $mform->getElement('track' . $id);
                        $element->setValue($track_record->{$id});
                    }
                }

                $autoenrol_element =& $mform->getElement('autoenrol');
                $autoenrol_element->setValue($record->autoenrol);
            }
        }
    }
}

?>