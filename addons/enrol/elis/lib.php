<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2011 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

class enrol_elis_plugin extends enrol_plugin {
    const ENROL_FROM_COURSE_CATALOG_CONFIG = 'enrol_from_course_catalog';
    const ENROL_FROM_COURSE_CATALOG_DB = 'customint1';

    public function allow_unenrol(stdClass $instance) {
        return true;
    }

    public function allow_manage(stdClass $instance) {
        return true;
    }

    // allow users to self-enrol
    // FIXME: make this per-instance configurable
    /*
    public function show_enrolme_link(stdClass $instance) {
        return true;
    }

    public function get_manual_enrol_link($instance) {
        // FIXME:
    }
    */

    /**
     * Returns enrolment instance manage link.
     *
     * By defaults looks for manage.php file and tests for manage capability.
     *
     * @param object $instance
     * @return moodle_url;
     */
    public function add_course_navigation($instancesnode, stdClass $instance) {
        if ($instance->enrol !== 'elis') {
             throw new coding_exception('Invalid enrol instance type!');
        }

        $context = get_context_instance(CONTEXT_COURSE, $instance->courseid);
        if (has_capability('enrol/elis:config', $context)) {
            $managelink = new moodle_url('/enrol/elis/edit.php', array('courseid'=>$instance->courseid));
            $instancesnode->add($this->get_instance_name($instance), $managelink, navigation_node::TYPE_SETTING);
        }
    }

    /**
     * Returns edit icons for the page with list of instances
     * @param stdClass $instance
     * @return array
     */
    public function get_action_icons(stdClass $instance) {
        global $OUTPUT;

        if ($instance->enrol !== 'elis') {
            throw new coding_exception('invalid enrol instance!');
        }
        $context = get_context_instance(CONTEXT_COURSE, $instance->courseid);

        $icons = array();

        if (has_capability('enrol/elis:config', $context)) {
            $editlink = new moodle_url("/enrol/elis/edit.php", array('courseid'=>$instance->courseid));
            $icons[] = $OUTPUT->action_icon($editlink, new pix_icon('i/edit', get_string('edit'), 'core', array('class'=>'icon')));
        }

        return $icons;
    }

    public function get_newinstance_link($courseid) {
        global $DB;

        $context = get_context_instance(CONTEXT_COURSE, $courseid, MUST_EXIST);

        if (!has_capability('moodle/course:enrolconfig', $context) or !has_capability('enrol/elis:config', $context)) {
            return null;
        }

        if ($DB->record_exists('enrol', array('courseid'=>$courseid, 'enrol'=>'elis'))) {
            return null;
        }

        return new moodle_url('/enrol/elis/edit.php', array('courseid'=>$courseid));
    }

    public function add_default_instance($course) {
        global $DB;
        if (!$DB->record_exists('enrol', array('courseid'=>$course->id, 'enrol'=>'elis'))) {
            // only add if no instance already exists
            $this->add_instance($course,
                                array('roleid' => $this->get_config('roleid', 0),
                                      self::ENROL_FROM_COURSE_CATALOG_DB => $this->get_config(self::ENROL_FROM_COURSE_CATALOG_CONFIG, 1)
                                    ));
        }
    }

    public function get_user_enrolment_actions(course_enrolment_manager $manager, $ue) {
        $actions = array();
        $context = $manager->get_context();
        $instance = $ue->enrolmentinstance;
        $params = $manager->get_moodlepage()->url->params();
        $params['ue'] = $ue->id;
        if ($this->allow_unenrol($instance) && has_capability("enrol/elis:unenrol", $context)) {
            $url = new moodle_url('/enrol/elis/unenroluser.php', $params);
            $actions[] = new user_enrolment_action(new pix_icon('t/delete', ''), get_string('unenrol', 'enrol'), $url, array('class'=>'unenrollink', 'rel'=>$ue->id));
        }
        return $actions;
    }

    /**
     * Get the instance for a course.  Creates a new instance if one does not exist.
     */
    public function get_or_create_instance($course) {
        global $DB;
        $enrol = $DB->get_record('enrol', array('courseid'=>$course->id, 'enrol'=>'elis'));
        if ($enrol) {
            return $enrol;
        }
        $this->add_default_instance($course);
        $enrol = $DB->get_record('enrol', array('courseid'=>$course->id, 'enrol'=>'elis'));
        return $enrol;
    }
}

/**
 * Indicates API features that the enrol plugin supports.
 *
 * @param string $feature
 * @return mixed True if yes (some features may use other values)
 */
function enrol_elis_supports($feature) {
    switch($feature) {
        case ENROL_RESTORE_TYPE: return ENROL_RESTORE_EXACT;

        default: return null;
    }
}
