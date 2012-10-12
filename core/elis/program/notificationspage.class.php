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

defined('MOODLE_INTERNAL') || die();

require_once elispm::lib('page.class.php');
require_once elispm::lib('lib.php');
require_once elispm::file('/form/notificationform.class.php');
require_once elispm::file('configpage.class.php');

class notifications extends pm_page {
    var $pagename = 'ntf';
    var $section = 'admn';
    var $form_class = 'pmnotificationform';

    function can_do_default() {
        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('elis/program:config', $context);
    }

    function build_navbar_default($who = null) { // was build_navigation_default
        global $CFG;
        parent::build_navbar_default($who);
        $page = $this->get_new_page(array('action' => 'default'), true);
        //$this->navbar->add(get_string('learningplan', 'elis_program'), "{$CFG->wwwroot}/elis/program/");
        $this->navbar->add(get_string('notifications', 'elis_program'), $page->url);
    }

    function get_title_default() {
        return get_string('notifications', 'elis_program');
    }

    function do_default() {

        $target = $this->get_new_page(array('action' => 'default'));

        $form = new $this->form_class($target->url);

        if ($form->is_cancelled()) {
            $target = $this->get_new_page(array('action' => 'default'), true);
            redirect($target->url);
            return;
        }

        $this->display('default');
    }

    /**
     * handler for the default display action.  Prints the edit form.
     */
    function display_default() {

        $target = $this->get_new_page(array('action' => 'default'));

        $configform = new $this->form_class($target->url);

        $configform->set_data(elis::$config->elis_program);

        if ($configdata = $configform->get_data()) {
        /// Notifications section:

            configpage::config_set_value($configdata, 'notify_classenrol_user', 0);
            configpage::config_set_value($configdata, 'notify_classenrol_role', 0);
            configpage::config_set_value($configdata, 'notify_classenrol_supervisor', 0);
            if (empty($configdata->notify_classenrol_message)) {
                $configdata->notify_classenrol_message = get_string('notifyclassenrolmessagedef', 'elis_program');
            }
            pm_set_config('notify_classenrol_message', $configdata->notify_classenrol_message);

            configpage::config_set_value($configdata, 'notify_classcompleted_user', 0);
            configpage::config_set_value($configdata, 'notify_classcompleted_role', 0);
            configpage::config_set_value($configdata, 'notify_classcompleted_supervisor', 0);
            if (empty($configdata->notify_classcompleted_message)) {
                $configdata->notify_classcompleted_message = get_string('notifyclasscompletedmessagedef', 'elis_program');
            }
            pm_set_config('notify_classcompleted_message', $configdata->notify_classcompleted_message);

            configpage::config_set_value($configdata, 'notify_classnotstarted_user', 0);
            configpage::config_set_value($configdata, 'notify_classnotstarted_role', 0);
            configpage::config_set_value($configdata, 'notify_classnotstarted_supervisor', 0);
            if (empty($configdata->notify_classnotstarted_message)) {
                $configdata->notify_classnotstarted_message = get_string('notifyclassnotstartedmessagedef', 'elis_program');
            }
            pm_set_config('notify_classnotstarted_message', $configdata->notify_classnotstarted_message);
            configpage::config_set_value($configdata, 'notify_classnotstarted_days', 0);

            configpage::config_set_value($configdata, 'notify_classnotcompleted_user', 0);
            configpage::config_set_value($configdata, 'notify_classnotcompleted_role', 0);
            configpage::config_set_value($configdata, 'notify_classnotcompleted_supervisor', 0);
            if (empty($configdata->notify_classnotcompleted_message)) {
                $configdata->notify_classnotcompleted_message = get_string('notifyclassnotcompletedmessagedef', 'elis_program');
            }
            pm_set_config('notify_classnotcompleted_message', $configdata->notify_classnotcompleted_message);
            configpage::config_set_value($configdata, 'notify_classnotcompleted_days', 0);

            configpage::config_set_value($configdata, 'notify_curriculumcompleted_user', 0);
            configpage::config_set_value($configdata, 'notify_curriculumcompleted_role', 0);
            configpage::config_set_value($configdata, 'notify_curriculumcompleted_supervisor', 0);
            if (empty($configdata->notify_curriculumcompleted_message)) {
                $configdata->notify_curriculumcompleted_message = get_string('notifycurriculumcompletedmessagedef', 'elis_program');
            }
            pm_set_config('notify_curriculumcompleted_message', $configdata->notify_curriculumcompleted_message);


            configpage::config_set_value($configdata, 'notify_curriculumnotcompleted_user', 0);
            configpage::config_set_value($configdata, 'notify_curriculumnotcompleted_role', 0);
            configpage::config_set_value($configdata, 'notify_curriculumnotcompleted_supervisor', 0);
            if (empty($configdata->notify_curriculumnotcompleted_message)) {
                $configdata->notify_curriculumnotcompleted_message = get_string('notifycurriculumnotcompletedmessagedef', 'elis_program');
            }
            pm_set_config('notify_curriculumnotcompleted_message', $configdata->notify_curriculumnotcompleted_message);
            configpage::config_set_value($configdata, 'notify_curriculumnotcompleted_days', 0);

            configpage::config_set_value($configdata, 'notify_trackenrol_user', 0);
            configpage::config_set_value($configdata, 'notify_trackenrol_role', 0);
            configpage::config_set_value($configdata, 'notify_trackenrol_supervisor', 0);
            if (empty($configdata->notify_trackenrol_message)) {
                $configdata->notify_trackenrol_message = get_string('notifytrackenrolmessagedef', 'elis_program');
            }
            pm_set_config('notify_trackenrol_message', $configdata->notify_trackenrol_message);


            configpage::config_set_value($configdata, 'notify_courserecurrence_user', 0);
            configpage::config_set_value($configdata, 'notify_courserecurrence_role', 0);
            configpage::config_set_value($configdata, 'notify_courserecurrence_supervisor', 0);
            if (empty($configdata->notify_courserecurrence_message)) {
                $configdata->notify_courserecurrence_message = get_string('notifycourserecurrencemessagedef', 'elis_program');
            }
            pm_set_config('notify_courserecurrence_message', $configdata->notify_courserecurrence_message);
            configpage::config_set_value($configdata, 'notify_courserecurrence_days', 0);

            configpage::config_set_value($configdata, 'notify_curriculumrecurrence_user', 0);
            configpage::config_set_value($configdata, 'notify_curriculumrecurrence_role', 0);
            configpage::config_set_value($configdata, 'notify_curriculumrecurrence_supervisor', 0);
            if (empty($configdata->notify_curriculumrecurrence_message)) {
                $configdata->notify_curriculumrecurrence_message = get_string('notifycurriculumrecurrencemessagedef', 'elis_program');
            }
            pm_set_config('notify_curriculumrecurrence_message', $configdata->notify_curriculumrecurrence_message);
            configpage::config_set_value($configdata, 'notify_curriculumrecurrence_days', 0);
        }

        $configform->display();
    }
}
?>
