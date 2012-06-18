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

    require_once($CFG->dirroot . '/lib/formslib.php');

    class pmnotificationform extends moodleform {

        function definition() {
            global $USER, $CFG, $COURSE;

            $mform =& $this->_form;

            $strgeneral  = get_string('general');
            $strrequired = get_string('required');

        /// Add some extra hidden fields
            $mform->addElement('hidden', 's', 'ntf');
            $mform->addElement('hidden', 'section', 'admn');

            $mform->addElement('header', 'notifications', get_string('notificationssettings', 'elis_program'));

            $classenrol = array();
            $classenrol[] =& $mform->createElement('checkbox', 'notify_classenrol_user', '', get_string('notifications_notifyuser', 'elis_program'));
            $a = '"'.get_string('notify_classenrol', 'elis_program').'"';
            $classenrol[] =& $mform->createElement('checkbox', 'notify_classenrol_role', '', get_string('notifications_notifyrole', 'elis_program', $a));
            $classenrol[] =& $mform->createElement('checkbox', 'notify_classenrol_supervisor', '', get_string('notifications_notifysupervisor', 'elis_program', $a));
            $mform->addGroup($classenrol, 'classenrol', get_string('notify_classenrol', 'elis_program'), '<br />', false);

            $mform->addElement('textarea', 'notify_classenrol_message', get_string('notifyclassenrolmessage', 'elis_program'),
                               'wrap="virtual" rows="5" cols="40"');
            $mform->setType('notify_classenrol_message', PARAM_CLEAN);
            $mform->setDefault('notify_classenrol_message', get_string('notifyclassenrolmessagedef', 'elis_program'));

            $mform->addElement('static', 'spacer', '', '');

            $classcompl = array();
            $classcompl[] =& $mform->createElement('checkbox', 'notify_classcompleted_user', '', get_string('notifications_notifyuser', 'elis_program'));
            $a = '"'.get_string('notify_classcomplete', 'elis_program').'"';
            $classcompl[] =& $mform->createElement('checkbox', 'notify_classcompleted_role', '', get_string('notifications_notifyrole', 'elis_program', $a));
            $classcompl[] =& $mform->createElement('checkbox', 'notify_classcompleted_supervisor', '', get_string('notifications_notifysupervisor', 'elis_program', $a));
            $mform->addGroup($classcompl, 'classcompl', get_string('notify_classcomplete', 'elis_program'), '<br />', false);

            $mform->addElement('textarea', 'notify_classcompleted_message', get_string('notifyclasscompletedmessage', 'elis_program'),
                               'wrap="virtual" rows="5" cols="40"');
            $mform->setType('notify_classcompleted_message', PARAM_CLEAN);
            $mform->setDefault('notify_classcompleted_message', get_string('notifyclasscompletedmessagedef', 'elis_program'));

            $mform->addElement('static', 'spacer', '', '');

            $classnotst = array();
            $classnotst[] =& $mform->createElement('checkbox', 'notify_classnotstarted_user', '', get_string('notifications_notifyuser', 'elis_program'));
            $a = '"'.get_string('notify_classnotstart', 'elis_program').'"';
            $classnotst[] =& $mform->createElement('checkbox', 'notify_classnotstarted_role', '', get_string('notifications_notifyrole', 'elis_program', $a));
            $classnotst[] =& $mform->createElement('checkbox', 'notify_classnotstarted_supervisor', '', get_string('notifications_notifysupervisor', 'elis_program', $a));
            $mform->addGroup($classnotst, 'classnotst', get_string('notify_classnotstart', 'elis_program'), '<br />', false);

            $mform->addElement('textarea', 'notify_classnotstarted_message', get_string('notifyclassnotstartedmessage', 'elis_program'),
                               'wrap="virtual" rows="5" cols="40"');
            $mform->setType('notify_classnotstarted_message', PARAM_CLEAN);
            $mform->setDefault('notify_classnotstarted_message', get_string('notifyclassnotstartedmessagedef', 'elis_program'));

            $mform->addElement('text', 'notify_classnotstarted_days', get_string('notifyclassnotstarteddays', 'elis_program'), 'size="4"');
            $mform->setType('notify_classnotstarted_days', PARAM_INT);
            $mform->setDefault('notify_classnotstarted_days', 10);

            $mform->addElement('static', 'spacer', '', '');

            $classnotcm = array();
            $classnotcm[] =& $mform->createElement('checkbox', 'notify_classnotcompleted_user', '', get_string('notifications_notifyuser', 'elis_program'));
            $a = '"'.get_string('notify_classnotcomplete', 'elis_program').'"';
            $classnotcm[] =& $mform->createElement('checkbox', 'notify_classnotcompleted_role', '', get_string('notifications_notifyrole', 'elis_program', $a));
            $classnotcm[] =& $mform->createElement('checkbox', 'notify_classnotcompleted_supervisor', '', get_string('notifications_notifysupervisor', 'elis_program', $a));
            $mform->addGroup($classnotcm, 'classnotcm', get_string('notify_classnotcomplete', 'elis_program'), '<br />', false);

            $mform->addElement('textarea', 'notify_classnotcompleted_message', get_string('notifyclassnotcompletedmessage', 'elis_program'),
                               'wrap="virtual" rows="5" cols="40"');
            $mform->setType('notify_classnotcompleted_message', PARAM_CLEAN);
            $mform->setDefault('notify_classnotcompleted_message', get_string('notifyclassnotcompletedmessagedef', 'elis_program'));

            $mform->addElement('text', 'notify_classnotcompleted_days', get_string('notifyclassnotcompleteddays', 'elis_program'), 'size="4"');
            $mform->setType('notify_classnotcompleted_days', PARAM_INT);
            $mform->setDefault('notify_classnotcompleted_days', 10);

            $mform->addElement('static', 'spacer', '', '');

            $currcompl = array();
            $currcompl[] =& $mform->createElement('checkbox', 'notify_curriculumcompleted_user', '', get_string('notifications_notifyuser', 'elis_program'));
            $a = '"'.get_string('notify_curriculumcomplete', 'elis_program').'"';
            $currcompl[] =& $mform->createElement('checkbox', 'notify_curriculumcompleted_role', '', get_string('notifications_notifyrole', 'elis_program', $a));
            $currcompl[] =& $mform->createElement('checkbox', 'notify_curriculumcompleted_supervisor', '', get_string('notifications_notifysupervisor', 'elis_program', $a));
            $mform->addGroup($currcompl, 'currcompl', get_string('notify_curriculumcomplete', 'elis_program'), '<br />', false);

            $mform->addElement('textarea', 'notify_curriculumcompleted_message', get_string('notifycurriculumcompletedmessage', 'elis_program'),
                               'wrap="virtual" rows="5" cols="40"');
            $mform->setType('notify_curriculumcompleted_message', PARAM_CLEAN);
            $mform->setDefault('notify_curriculumcompleted_message', get_string('notifycurriculumcompletedmessagedef', 'elis_program'));

            $mform->addElement('static', 'spacer', '', '');


            $currncompl = array();
            $currncompl[] =& $mform->createElement('checkbox', 'notify_curriculumnotcompleted_user', '', get_string('notifications_notifyuser', 'elis_program'));
            $a = '"'.get_string('notify_curriculumnotcomplete', 'elis_program').'"';
            $currncompl[] =& $mform->createElement('checkbox', 'notify_curriculumnotcompleted_role', '', get_string('notifications_notifyrole', 'elis_program', $a));
            $currncompl[] =& $mform->createElement('checkbox', 'notify_curriculumnotcompleted_supervisor', '', get_string('notifications_notifysupervisor', 'elis_program', $a));
            $mform->addGroup($currncompl, 'currncompl', get_string('notify_curriculumnotcomplete', 'elis_program'), '<br />', false);

            $mform->addElement('textarea', 'notify_curriculumnotcompleted_message', get_string('notifycurriculumnotcompletedmessage', 'elis_program'),
                               'wrap="virtual" rows="5" cols="40"');
            $mform->setType('notify_curriculumnotcompleted_message', PARAM_CLEAN);
            $mform->setDefault('notify_curriculumnotcompleted_message', get_string('notifycurriculumnotcompletedmessagedef', 'elis_program'));

            $mform->addElement('text', 'notify_curriculumnotcompleted_days', get_string('notifycurriculumnotcompleteddays', 'elis_program'), 'size="4"');
            $mform->setType('notify_curriculumnotcompleted_days', PARAM_INT);
            $mform->setDefault('notify_curriculumnotcompleted_days', 10);

            $mform->addElement('static', 'spacer', '', '');


            $trackassign = array();
            $trackassign[] =& $mform->createElement('checkbox', 'notify_trackenrol_user', '', get_string('notifications_notifyuser', 'elis_program'));
            $a = '"'.get_string('notify_trackenrol', 'elis_program').'"';
            $trackassign[] =& $mform->createElement('checkbox', 'notify_trackenrol_role', '', get_string('notifications_notifyrole', 'elis_program', $a));
            $trackassign[] =& $mform->createElement('checkbox', 'notify_trackenrol_supervisor', '', get_string('notifications_notifysupervisor', 'elis_program', $a));
            $mform->addGroup($trackassign, 'trackassign', get_string('notify_trackenrol', 'elis_program'), '<br />', false);

            $mform->addElement('textarea', 'notify_trackenrol_message', get_string('notifytrackenrolmessage', 'elis_program'),
                               'wrap="virtual" rows="5" cols="40"');
            $mform->setType('notify_trackenrol_message', PARAM_CLEAN);
            $mform->setDefault('notify_trackenrol_message', get_string('notifytrackenrolmessagedef', 'elis_program'));

            $mform->addElement('static', 'spacer', '', '');

            $courserecur = array();
            $courserecur[] =& $mform->createElement('checkbox', 'notify_courserecurrence_user', '', get_string('notifications_notifyuser', 'elis_program'));
            $a = '"'.get_string('notify_courserecurrence', 'elis_program').'"';
            $courserecur[] =& $mform->createElement('checkbox', 'notify_courserecurrence_role', '', get_string('notifications_notifyrole', 'elis_program', $a));
            $courserecur[] =& $mform->createElement('checkbox', 'notify_courserecurrence_supervisor', '', get_string('notifications_notifysupervisor', 'elis_program', $a));
            $mform->addGroup($courserecur, 'courserecur', get_string('notify_courserecurrence', 'elis_program'), '<br />', false);

            $mform->addElement('textarea', 'notify_courserecurrence_message', get_string('notifycourserecurrencemessage', 'elis_program'),
                               'wrap="virtual" rows="5" cols="40"');
            $mform->setType('notify_courserecurrence_message', PARAM_CLEAN);
            $mform->setDefault('notify_courserecurrence_message', get_string('notifycourserecurrencemessagedef', 'elis_program'));

            $mform->addElement('text', 'notify_courserecurrence_days', get_string('notifycourserecurrencedays', 'elis_program'), 'size="4"');
            $mform->setType('notify_courserecurrence_days', PARAM_INT);
            $mform->setDefault('notify_courserecurrence_days', 10);

            $mform->addElement('static', 'spacer', '', '');

            $currrecur = array();
            $currrecur[] =& $mform->createElement('checkbox', 'notify_curriculumrecurrence_user', '', get_string('notifications_notifyuser', 'elis_program'));
            $a = '"'.get_string('notify_curriculumrecurrence', 'elis_program').'"';
            $currrecur[] =& $mform->createElement('checkbox', 'notify_curriculumrecurrence_role', '', get_string('notifications_notifyrole', 'elis_program', $a));
            $currrecur[] =& $mform->createElement('checkbox', 'notify_curriculumrecurrence_supervisor', '', get_string('notifications_notifysupervisor', 'elis_program', $a));
            $mform->addGroup($currrecur, 'curriculumrecur', get_string('notify_curriculumrecurrence', 'elis_program'), '<br />', false);

            $mform->addElement('textarea', 'notify_curriculumrecurrence_message', get_string('notifycurriculumrecurrencemessage', 'elis_program'),
                               'wrap="virtual" rows="5" cols="40"');
            $mform->setType('notify_curriculumrecurrence_message', PARAM_CLEAN);
            $mform->setDefault('notify_curriculumrecurrence_message', get_string('notifycurriculumrecurrencemessagedef', 'elis_program'));

            $mform->addElement('text', 'notify_curriculumrecurrence_days', get_string('notifycurriculumrecurrencedays', 'elis_program'), 'size="4"');
            $mform->setType('notify_curriculumrecurrence_days', PARAM_INT);
            $mform->setDefault('notify_curriculumrecurrence_days', 10);

            $mform->addElement('static', 'spacer', '', '');

            $this->add_action_buttons();
        }
    }
?>