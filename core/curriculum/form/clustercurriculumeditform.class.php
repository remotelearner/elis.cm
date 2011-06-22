<?php
/*
*  ELIS(TM): Enterprise Learning Intelligence Suite
*
*  Copyright (C) 2008-2010 Remote-Learner.net Inc (http://www.remote-learner.net)
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
*  @copyright  (C) 2008-2010 Remote Learner.net Inc http://www.remote-learner.net
*/

class clustercurriculumeditform extends moodleform {

    function clustercurriculumeditform($action=null, $customdata=null, $method='post', $target='', $attributes=null, $editable=true) {
        $this->cluster_fields = array('name'    => get_string('cluster_name', 'block_curr_admin'),
                                      'display' => get_string('cluster_description', 'block_curr_admin'));

        $this->curriculum_fields = array('idnumber'    => get_string('curriculum_idnumber', 'block_curr_admin'),
                                         'name'        => get_string('curriculum_name', 'block_curr_admin'),
                                         'description' => get_string('curriculum_shortdescription' ,'block_curr_admin'),
                                         'reqcredits'  => get_string('curriculum_reqcredits', 'block_curr_admin'),
                                         'numcourses'  => get_string('num_courses', 'block_curr_admin'));

        parent::__construct($action, $customdata, $method, $target, $attributes, $editable);
    }

    function definition() {
        $mform =& $this->_form;

        //cluster stuff
        $mform->addElement('header', 'clusterinfo', get_string('info_group_cluster', 'block_curr_admin'));

        foreach($this->cluster_fields as $id => $display) {
            $element =& $mform->createElement('text', 'cluster' . $id, $display);
            $element->freeze();
            $mform->addElement($element);
        }

        //curriculum stuff
        $mform->addElement('header', 'curriculuminfo', get_string('info_group_curriculum', 'block_curr_admin'));

        foreach($this->curriculum_fields as $id => $display) {
            $element =& $mform->createElement('text', 'curriculum' . $id, $display);
            $element->freeze();
            $mform->addElement($element);
        }

        //association stuff
        $mform->addElement('header', 'associationinfo', get_string('info_group_association', 'block_curr_admin'));
        $mform->addElement('advcheckbox', 'autoenrol', get_string('auto_enrol', 'block_curr_admin'), null, null, array('0', '1'));
        $mform->setHelpButton('autoenrol', array('clustercurriculumform/autoenrol', get_string('auto_enrol', 'block_curr_admin'), 'block_curr_admin'));

        $mform->addElement('hidden', 'association_id', '');

        $this->add_action_buttons();
    }

    function definition_after_data() {
        global $CFG;

        parent::definition_after_data();
        $mform =& $this->_form;

        if($association_id = $mform->getElementValue('association_id')) {
            if($record = get_record(CLSTCURTABLE, 'id', $association_id)) {

                //cluster stuff
                if($cluster_record = get_record(CLSTTABLE, 'id', $record->clusterid)) {
                    foreach($this->cluster_fields as $id => $display) {
                        $element =& $mform->getElement('cluster' . $id);
                        $element->setValue($cluster_record->{$id});
                    }
                }

                //curriculum stuff
                $curriculum_sql = "SELECT cur.idnumber,
                                          cur.name,
                                          cur.description,
                                          cur.reqcredits,
                                   COUNT(curcrs.id) as numcourses
                                   FROM {$CFG->prefix}crlm_curriculum cur
                                   LEFT JOIN {$CFG->prefix}crlm_curriculum_course curcrs
                                   ON curcrs.curriculumid = cur.id
                                   WHERE cur.id = {$record->curriculumid}";

                if($curriculum_record = get_record_sql($curriculum_sql)) {
                    foreach($this->curriculum_fields as $id => $display) {
                        $element =& $mform->getElement('curriculum' . $id);
                        $element->setValue($curriculum_record->{$id});
                    }
                }

                //association stuff
                $autoenrol_element =& $mform->getElement('autoenrol');
                $autoenrol_element->setValue($record->autoenrol);
            }
        }
    }

}

?>