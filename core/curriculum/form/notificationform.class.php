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
    require_once($CFG->dirroot . '/lib/formslib.php');

    class cmnotificationform extends moodleform {

        function definition() {
            global $USER, $CFG, $COURSE;

            $mform =& $this->_form;

            $strgeneral  = get_string('general');
            $strrequired = get_string('required');

        /// Add some extra hidden fields
            $mform->addElement('hidden', 's', 'ntf');
            $mform->addElement('hidden', 'section', 'admn');

            $mform->addElement('header', 'notifications', get_string('notificationssettings', 'block_curr_admin'));

            $classenrol = array();
            $classenrol[] =& $mform->createElement('checkbox', 'notify_classenrol_user', '', get_string('notifyuser', 'block_curr_admin'));
            $a = '"curr_admin:notify_classenrol"';
            $classenrol[] =& $mform->createElement('checkbox', 'notify_classenrol_role', '', get_string('notifyrole', 'block_curr_admin', $a));
            $classenrol[] =& $mform->createElement('checkbox', 'notify_classenrol_supervisor', '', get_string('notifysupervisor', 'block_curr_admin', $a));
            $mform->addGroup($classenrol, 'classenrol', get_string('curr_admin:notify_classenrol', 'block_curr_admin'), '<br />', false);

            $mform->addElement('textarea', 'notify_classenrol_message', get_string('notifyclassenrolmessage', 'block_curr_admin'),
                               'wrap="virtual" rows="5" cols="40"');
            $mform->setType('notify_classenrol_message', PARAM_CLEAN);
            $mform->setDefault('notify_classenrol_message', get_string('notifyclassenrolmessagedef', 'block_curr_admin'));

            $mform->addElement('static', 'spacer', '', '');

            $classcompl = array();
            $classcompl[] =& $mform->createElement('checkbox', 'notify_classcompleted_user', '', get_string('notifyuser', 'block_curr_admin'));
            $a = '"curr_admin:notify_classcomplete"';
            $classcompl[] =& $mform->createElement('checkbox', 'notify_classcompleted_role', '', get_string('notifyrole', 'block_curr_admin', $a));
            $classcompl[] =& $mform->createElement('checkbox', 'notify_classcompleted_supervisor', '', get_string('notifysupervisor', 'block_curr_admin', $a));
            $mform->addGroup($classcompl, 'classcompl', get_string('curr_admin:notify_classcomplete', 'block_curr_admin'), '<br />', false);

            $mform->addElement('textarea', 'notify_classcompleted_message', get_string('notifyclasscompletedmessage', 'block_curr_admin'),
                               'wrap="virtual" rows="5" cols="40"');
            $mform->setType('notify_classcompleted_message', PARAM_CLEAN);
            $mform->setDefault('notify_classcompleted_message', get_string('notifyclasscompletedmessagedef', 'block_curr_admin'));

            $mform->addElement('static', 'spacer', '', '');

            $classnotst = array();
            $classnotst[] =& $mform->createElement('checkbox', 'notify_classnotstarted_user', '', get_string('notifyuser', 'block_curr_admin'));
            $a = '"curr_admin:notify_classnotstart"';
            $classnotst[] =& $mform->createElement('checkbox', 'notify_classnotstarted_role', '', get_string('notifyrole', 'block_curr_admin', $a));
            $classnotst[] =& $mform->createElement('checkbox', 'notify_classnotstarted_supervisor', '', get_string('notifysupervisor', 'block_curr_admin', $a));
            $mform->addGroup($classnotst, 'classnotst', get_string('curr_admin:notify_classnotstart', 'block_curr_admin'), '<br />', false);

            $mform->addElement('textarea', 'notify_classnotstarted_message', get_string('notifyclassnotstartedmessage', 'block_curr_admin'),
                               'wrap="virtual" rows="5" cols="40"');
            $mform->setType('notify_classnotstarted_message', PARAM_CLEAN);
            $mform->setDefault('notify_classnotstarted_message', get_string('notifyclassnotstartedmessagedef', 'block_curr_admin'));

            $mform->addElement('text', 'notify_classnotstarted_days', get_string('notifyclassnotstarteddays', 'block_curr_admin'), 'size="4"');
            $mform->setType('notify_classnotstarted_days', PARAM_INT);
            $mform->setDefault('notify_classnotstarted_days', 10);

            $mform->addElement('static', 'spacer', '', '');

            $classnotcm = array();
            $classnotcm[] =& $mform->createElement('checkbox', 'notify_classnotcompleted_user', '', get_string('notifyuser', 'block_curr_admin'));
            $a = '"curr_admin:notify_classnotcomplete"';
            $classnotcm[] =& $mform->createElement('checkbox', 'notify_classnotcompleted_role', '', get_string('notifyrole', 'block_curr_admin', $a));
            $classnotcm[] =& $mform->createElement('checkbox', 'notify_classnotcompleted_supervisor', '', get_string('notifysupervisor', 'block_curr_admin', $a));
            $mform->addGroup($classnotcm, 'classnotcm', get_string('curr_admin:notify_classnotcomplete', 'block_curr_admin'), '<br />', false);

            $mform->addElement('textarea', 'notify_classnotcompleted_message', get_string('notifyclassnotcompletedmessage', 'block_curr_admin'),
                               'wrap="virtual" rows="5" cols="40"');
            $mform->setType('notify_classnotcompleted_message', PARAM_CLEAN);
            $mform->setDefault('notify_classnotcompleted_message', get_string('notifyclassnotcompletedmessagedef', 'block_curr_admin'));

            $mform->addElement('text', 'notify_classnotcompleted_days', get_string('notifyclassnotcompleteddays', 'block_curr_admin'), 'size="4"');
            $mform->setType('notify_classnotcompleted_days', PARAM_INT);
            $mform->setDefault('notify_classnotcompleted_days', 10);

            $mform->addElement('static', 'spacer', '', '');

            $currcompl = array();
            $currcompl[] =& $mform->createElement('checkbox', 'notify_curriculumcompleted_user', '', get_string('notifyuser', 'block_curr_admin'));
            $a = '"curr_admin:notify_curriculumcomplete"';
            $currcompl[] =& $mform->createElement('checkbox', 'notify_curriculumcompleted_role', '', get_string('notifyrole', 'block_curr_admin', $a));
            $currcompl[] =& $mform->createElement('checkbox', 'notify_curriculumcompleted_supervisor', '', get_string('notifysupervisor', 'block_curr_admin', $a));
            $mform->addGroup($currcompl, 'currcompl', get_string('curr_admin:notify_curriculumcomplete', 'block_curr_admin'), '<br />', false);

            $mform->addElement('textarea', 'notify_curriculumcompleted_message', get_string('notifycurriculumcompletedmessage', 'block_curr_admin'),
                               'wrap="virtual" rows="5" cols="40"');
            $mform->setType('notify_curriculumcompleted_message', PARAM_CLEAN);
            $mform->setDefault('notify_curriculumcompleted_message', get_string('notifycurriculumcompletedmessagedef', 'block_curr_admin'));

            $mform->addElement('static', 'spacer', '', '');


            $currncompl = array();
            $currncompl[] =& $mform->createElement('checkbox', 'notify_curriculumnotcompleted_user', '', get_string('notifyuser', 'block_curr_admin'));
            $a = '"curr_admin:notify_curriculumnotcomplete"';
            $currncompl[] =& $mform->createElement('checkbox', 'notify_curriculumnotcompleted_role', '', get_string('notifyrole', 'block_curr_admin', $a));
            $currncompl[] =& $mform->createElement('checkbox', 'notify_curriculumnotcompleted_supervisor', '', get_string('notifysupervisor', 'block_curr_admin', $a));
            $mform->addGroup($currncompl, 'currncompl', get_string('curr_admin:notify_curriculumnotcomplete', 'block_curr_admin'), '<br />', false);

            $mform->addElement('textarea', 'notify_curriculumnotcompleted_message', get_string('notifycurriculumnotcompletedmessage', 'block_curr_admin'),
                               'wrap="virtual" rows="5" cols="40"');
            $mform->setType('notify_curriculumnotcompleted_message', PARAM_CLEAN);
            $mform->setDefault('notify_curriculumnotcompleted_message', get_string('notifycurriculumnotcompletedmessagedef', 'block_curr_admin'));

            $mform->addElement('text', 'notify_curriculumnotcompleted_days', get_string('notifycurriculumnotcompleteddays', 'block_curr_admin'), 'size="4"');
            $mform->setType('notify_curriculumnotcompleted_days', PARAM_INT);
            $mform->setDefault('notify_curriculumnotcompleted_days', 10);

            $mform->addElement('static', 'spacer', '', '');


            $trackassign = array();
            $trackassign[] =& $mform->createElement('checkbox', 'notify_trackenrol_user', '', get_string('notifyuser', 'block_curr_admin'));
            $a = '"curr_admin:notify_trackenrol"';
            $trackassign[] =& $mform->createElement('checkbox', 'notify_trackenrol_role', '', get_string('notifyrole', 'block_curr_admin', $a));
            $trackassign[] =& $mform->createElement('checkbox', 'notify_trackenrol_supervisor', '', get_string('notifysupervisor', 'block_curr_admin', $a));
            $mform->addGroup($trackassign, 'trackassign', get_string('curr_admin:notify_trackenrol', 'block_curr_admin'), '<br />', false);

            $mform->addElement('textarea', 'notify_trackenrol_message', get_string('notifytrackenrolmessage', 'block_curr_admin'),
                               'wrap="virtual" rows="5" cols="40"');
            $mform->setType('notify_trackenrol_message', PARAM_CLEAN);
            $mform->setDefault('notify_trackenrol_message', get_string('notifytrackenrolmessagedef', 'block_curr_admin'));

            $mform->addElement('static', 'spacer', '', '');

            $courserecur = array();
            $courserecur[] =& $mform->createElement('checkbox', 'notify_courserecurrence_user', '', get_string('notifyuser', 'block_curr_admin'));
            $a = '"curr_admin:notify_courserecurrence"';
            $courserecur[] =& $mform->createElement('checkbox', 'notify_courserecurrence_role', '', get_string('notifyrole', 'block_curr_admin', $a));
            $courserecur[] =& $mform->createElement('checkbox', 'notify_courserecurrence_supervisor', '', get_string('notifysupervisor', 'block_curr_admin', $a));
            $mform->addGroup($courserecur, 'courserecur', get_string('curr_admin:notify_courserecurrence', 'block_curr_admin'), '<br />', false);

            $mform->addElement('textarea', 'notify_courserecurrence_message', get_string('notifycourserecurrencemessage', 'block_curr_admin'),
                               'wrap="virtual" rows="5" cols="40"');
            $mform->setType('notify_courserecurrence_message', PARAM_CLEAN);
            $mform->setDefault('notify_courserecurrence_message', get_string('notifycourserecurrencemessagedef', 'block_curr_admin'));

            $mform->addElement('text', 'notify_courserecurrence_days', get_string('notifycourserecurrencedays', 'block_curr_admin'), 'size="4"');
            $mform->setType('notify_courserecurrence_days', PARAM_INT);
            $mform->setDefault('notify_courserecurrence_days', 10);

            $mform->addElement('static', 'spacer', '', '');

            $currrecur = array();
            $currrecur[] =& $mform->createElement('checkbox', 'notify_curriculumrecurrence_user', '', get_string('notifyuser', 'block_curr_admin'));
            $a = '"curr_admin:notify_curriculumrecurrence"';
            $currrecur[] =& $mform->createElement('checkbox', 'notify_curriculumrecurrence_role', '', get_string('notifyrole', 'block_curr_admin', $a));
            $currrecur[] =& $mform->createElement('checkbox', 'notify_curriculumrecurrence_supervisor', '', get_string('notifysupervisor', 'block_curr_admin', $a));
            $mform->addGroup($currrecur, 'curriculumrecur', get_string('curr_admin:notify_curriculumrecurrence', 'block_curr_admin'), '<br />', false);

            $mform->addElement('textarea', 'notify_curriculumrecurrence_message', get_string('notifycurriculumrecurrencemessage', 'block_curr_admin'),
                               'wrap="virtual" rows="5" cols="40"');
            $mform->setType('notify_curriculumrecurrence_message', PARAM_CLEAN);
            $mform->setDefault('notify_curriculumrecurrence_message', get_string('notifycurriculumrecurrencemessagedef', 'block_curr_admin'));

            $mform->addElement('text', 'notify_curriculumrecurrence_days', get_string('notifycurriculumrecurrencedays', 'block_curr_admin'), 'size="4"');
            $mform->setType('notify_curriculumrecurrence_days', PARAM_INT);
            $mform->setDefault('notify_curriculumrecurrence_days', 10);

            $mform->addElement('static', 'spacer', '', '');

            $this->add_action_buttons();
        }
    }
?>