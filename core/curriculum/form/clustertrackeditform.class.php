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

class clustertrackeditform extends moodleform {

    var $cluster_fields = array();
    var $track_fields = array();

    function clustertrackeditform($action=null, $customdata=null, $method='post', $target='', $attributes=null, $editable=true) {
        $this->cluster_fields = array('name'    => get_string('cluster_name','block_curr_admin'),
                                      'display' => get_string('description','block_curr_admin'),);

        $this->track_fields = array('name'        => get_string('track_name', 'block_curr_admin'),
                                    'description' => get_string('track_description', 'block_curr_admin'),
                                    'parcur'      => get_string('track_parcur', 'block_curr_admin'),
                                    'class'       => get_string('track_classes', 'block_curr_admin'));

        parent::__construct($action, $customdata, $method, $target, $attributes, $editable);
    }

    function definition() {
        $mform = $this->_form;

        //cluster stuff
        $mform->addElement('header', 'clusterinfo', get_string('info_group_cluster', 'block_curr_admin'));

        foreach($this->cluster_fields as $id => $display) {
            $element =& $mform->createElement('text', 'cluster' . $id, $display);
            $element->freeze();
            $mform->addElement($element);
        }

        //track stuff
        $mform->addElement('header', 'trackinfo', get_string('info_group_track', 'block_curr_admin'));

        foreach($this->track_fields as $id => $display) {
            $element =& $mform->createElement('text', 'track' . $id, $display);
            $element->freeze();
            $mform->addElement($element);
        }

        //association stuff
        $mform->addElement('header', 'associationinfo', get_string('info_group_association', 'block_curr_admin'));
        $mform->addElement('advcheckbox', 'autoenrol', get_string('auto_enrol', 'block_curr_admin'), null, null, array('0', '1'));
        $mform->setHelpButton('autoenrol', array('clustertrackform/autoenrol', get_string('auto_enrol', 'block_curr_admin'), 'block_curr_admin'));

        $mform->addElement('hidden', 'association_id', '');

        $this->add_action_buttons();
    }

    function definition_after_data() {
        global $CFG;

        parent::definition_after_data();
        $mform =& $this->_form;

        if($association_id = $mform->getElementValue('association_id')) {
            if($record = get_record(CLSTTRKTABLE, 'id', $association_id)) {
                if($cluster_record = get_record(CLSTTABLE, 'id', $record->clusterid)) {
                    foreach($this->cluster_fields as $id => $display) {
                        $element =& $mform->getElement('cluster' . $id);
                        $element->setValue($cluster_record->{$id});
                    }
                }

                $track_sql = "SELECT trk.*,
                                     cur.name AS parcur,
                                     (SELECT COUNT(*)
                                      FROM {$CFG->prefix}crlm_track_class
                                      WHERE trackid = trk.id ) as class
                              FROM {$CFG->prefix}crlm_track trk
                              JOIN {$CFG->prefix}crlm_curriculum cur
                              ON trk.curid = cur.id
                              WHERE trk.defaulttrack = 0
                              AND trk.id = {$record->trackid}";

                if($track_record = get_record_sql($track_sql)) {
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
