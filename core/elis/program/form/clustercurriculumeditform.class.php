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

class clustercurriculumeditform extends moodleform {

    function clustercurriculumeditform($action=null, $customdata=null, $method='post', $target='', $attributes=null, $editable=true) {
        $this->cluster_fields = array('name'    => get_string('userset_name', 'elis_program'),
                                      'display' => get_string('userset_description', 'elis_program'));

        $this->curriculum_fields = array('idnumber'    => get_string('curriculum_idnumber', 'elis_program'),
                                         'name'        => get_string('curriculum_name', 'elis_program'),
                                         'description' => get_string('description' ,'elis_program'),
                                         'reqcredits'  => get_string('curriculum_reqcredits', 'elis_program'),
                                         'numcourses'  => get_string('num_courses', 'elis_program'));

        parent::__construct($action, $customdata, $method, $target, $attributes, $editable);
    }

    function definition() {
        $mform =& $this->_form;

        //cluster stuff
        $mform->addElement('header', 'clusterinfo', get_string('userset_info_group', 'elis_program'));

        foreach($this->cluster_fields as $id => $display) {
            $element =& $mform->createElement('text', 'cluster' . $id, $display);
            $element->freeze();
            $mform->addElement($element);
        }

        //curriculum stuff
        $mform->addElement('header', 'curriculuminfo', get_string('program_info_group', 'elis_program'));

        foreach($this->curriculum_fields as $id => $display) {
            $element =& $mform->createElement('text', 'curriculum' . $id, $display);
            $element->freeze();
            $mform->addElement($element);
        }

        //association stuff
        $mform->addElement('header', 'associationinfo', get_string('association_info_group', 'elis_program'));
        $mform->addElement('advcheckbox', 'autoenrol', get_string('usersetprogram_auto_enrol', 'elis_program'), null, null, array('0', '1'));
        $mform->addHelpButton('autoenrol', 'usersetprogramform:autoenrol', 'elis_program');

        $mform->addElement('hidden', 'association_id', '');

        $this->add_action_buttons();
    }

    function definition_after_data() {
        global $CFG, $DB;

        parent::definition_after_data();
        $mform =& $this->_form;

        if($association_id = $mform->getElementValue('association_id')) {
            if($record = $DB->get_record(clustercurriculum::TABLE, array('id'=> $association_id))) {

                //cluster stuff
                if($cluster_record = $DB->get_record(userset::TABLE, array('id'=> $record->clusterid))) {
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
                                   FROM {".curriculum::TABLE ."} cur
                                   LEFT JOIN {".curriculumcourse::TABLE."} curcrs
                                   ON curcrs.curriculumid = cur.id
                                   WHERE cur.id = ?";

                $params = array($record->curriculumid);
                if($curriculum_record = $DB->get_record_sql($curriculum_sql, $params)) {
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