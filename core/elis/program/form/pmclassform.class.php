<?php
/**
 * Form used for editing / displaying a class record.
 *
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote-Learner.net Inc (http://www.remote-learner.net)
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

require_once elispm::file('form/cmform.class.php');

MoodleQuickForm::registerElementType('time_selector', "{$CFG->dirroot}/elis/program/form/timeselector.php", 'pm_time_selector');

class pmclassform extends cmform {
    function definition() {
        global $USER, $CFG, $DB, $PAGE;

        parent::definition();

        if(!empty($this->_customdata['obj'])) {
            $obj = $this->_customdata['obj'];
            if(empty($obj->startdate) || $obj->startdate == 0) {
                $this->set_data(array('disablestart' => '1'));
            }
            if(empty($obj->enddate) || $obj->enddate == 0) {
                $this->set_data(array('disableend' => '1'));
            }

            if (isset($obj->starttimeminute) && isset($obj->starttimehour)
                && $obj->starttimeminute < 61 && $obj->starttimehour < 25) {
                $this->set_data(array('starttime' => array('minute' => $obj->starttimeminute,
                                                           'hour' => $obj->starttimehour)));
            }
            if (isset($obj->endtimeminute) && isset($obj->endtimehour)
                && $obj->endtimeminute < 61 && $obj->endtimehour < 25) {
                $this->set_data(array('endtime' => array('minute' => $obj->endtimeminute,
                                                         'hour' => $obj->endtimehour)));
            }
        }

        $mform =& $this->_form;

        $mform->addElement('hidden', 'id');

        // If there is no custom data for the course, create some
        if (empty($this->_customdata['obj']->course->name) ||
            empty($this->_customdata['obj']->id)) {
            $courses = array();
            if (!empty($USER->id)) {
                // This is necessary for creating a new class instance but will prevent a parent course from appearing
                // when the user has class edit permissions but not class creation permission -- ELIS-5954
                $contexts = get_contexts_by_capability_for_user('course', 'elis/program:class_create', $USER->id);
                // get listing of available ELIS courses
                $courses = course_get_listing('name', 'ASC', 0, 0, '', '', $contexts);

                // Detect if we are editing an existing class instance by checking for an value in the 'id' element
                $elm = $mform->_elements[$mform->_elementIndex['id']];
                $id  = $elm->getValue();

                if (!empty($id)) {
                    // Make sure that the parent course for this class is always included otherwise the display is messed up
                    // and hitting the form Cancel button causes a DB error -- ELIS-5954
                    $pmclass = new pmclass($id);

                    $courses = array_merge($courses, course_get_listing('name', 'ASC', 0, 0, $pmclass->course->idnumber));
                }
            }

            // Add course select
            $attributes = array('onchange' => 'update_trk_multiselect(); update_crs_template();');

            $selections = array();
            if (!empty($courses)) {
                foreach($courses as $course) {
                    $selections[$course->id] = '(' . $course->idnumber . ')'
                                               . $course->name;
                }
            }

            $mform->addElement('select', 'courseid', get_string('course', 'elis_program') . ':', $selections, $attributes);
            $mform->addHelpButton('courseid', 'pmclassform:course', 'elis_program');

            $firstcourse = reset($courses);
            $this->firstcourse = $firstcourse;

            if(false !== $firstcourse && empty($this->_customdata['obj']->id)) {
                $this->add_track_multi_select($firstcourse->id);
            } elseif(!empty($courses)) {
                $this->add_track_multi_select($this->_customdata['obj']->courseid);
            }
        } else {
            $extra_params = array();
            $mform->addElement('static', 'courseid', get_string('course', 'elis_program') . ':');

            // Get current action and set param accordingly
            $current_action    = optional_param('action','view',PARAM_ALPHA);
            $extra_params['action']    = $current_action;
            $extra_params['s']         = 'crs'; // Want to set the url for the course
            $extra_params['id']        = $this->_customdata['obj']->courseid; // Course id
            $course_url = $this->get_moodle_url($extra_params);

            $course_name = '(' . $this->_customdata['obj']->course->idnumber . ')'.'<a href="'.$course_url.'" >'.$this->_customdata['obj']->course->name.'</a>';
            $this->set_data(array('courseid' => $course_name));
            $mform->addHelpButton('courseid', 'pmclassform:course', 'elis_program');

            $this->add_track_multi_select($this->_customdata['obj']->courseid);
        }

        if(!empty($this->_customdata['obj']->courseid)) {
            $mform->freeze('courseid');
        } else {
            $mform->addRule('courseid', get_string('required'), 'required', NULL, 'client');
        }
        // Done adding course select

        // Set any associated Moodle course for this class instance
        if (empty($this->_customdata['obj']->moodlecourseid) &&
            !empty($this->_customdata['obj']->id)) {
            $this->_customdata['obj']->moodlecourseid = moodle_get_course($this->_customdata['obj']->id);
        }

        $mform->addElement('text', 'idnumber', get_string('class_idnumber', 'elis_program') . ':');
        $mform->setType('idnumber', PARAM_TEXT);
        $mform->addRule('idnumber', get_string('required'), 'required', NULL, 'client');
        $mform->addRule('idnumber', null, 'maxlength', 100, 'client');
        $mform->addHelpButton('idnumber', 'pmclassform:class_idnumber', 'elis_program');

        $mform->addElement('date_selector', 'startdate', get_string('class_startdate', 'elis_program') . ':', array('optional'=>true, 'disabled'=>'disabled'));
        $mform->addHelpButton('startdate', 'pmclassform:class_startdate', 'elis_program');

        $mform->addElement('date_selector', 'enddate', get_string('class_enddate', 'elis_program') . ':', array('optional'=>true));

        if (!isset($obj->starttimehour) || $obj->starttimehour >= 25 ||
            !isset($obj->starttimeminute) || $obj->starttimeminute >= 61) {
            $mform->addElement('time_selector', 'starttime', get_string('class_starttime', 'elis_program') . ':',
                               array('optional'=>true, 'checked'=>'checked', 'display_12h'=>elis::$config->elis_program->time_format_12h));
        } else {
            $mform->addElement('time_selector', 'starttime', get_string('class_starttime', 'elis_program') . ':',
                               array('optional'=>true, 'checked'=>'unchecked', 'display_12h'=>elis::$config->elis_program->time_format_12h));
        }
        $mform->addHelpButton('starttime', 'pmclassform:class_starttime', 'elis_program');

        // Do the same thing for the endtime
        if (!isset($obj->endtimehour) || $obj->endtimehour >= 25 ||
            !isset($obj->endtimeminute) || $obj->endtimeminute >= 61) {
            $mform->addElement('time_selector', 'endtime', get_string('class_endtime', 'elis_program') . ':',
                               array('optional'=>true, 'checked'=>'checked', 'display_12h'=>elis::$config->elis_program->time_format_12h));
        } else {
            $mform->addElement('time_selector', 'endtime', get_string('class_endtime', 'elis_program') . ':',
                               array('optional'=>true, 'checked'=>'unchecked', 'display_12h'=>elis::$config->elis_program->time_format_12h));
        }

        $mform->addElement('text', 'maxstudents', get_string('class_maxstudents', 'elis_program') . ':');
        $mform->setType('maxstudents', PARAM_INT);
        $mform->addHelpButton('maxstudents', 'pmclassform:class_maxstudents', 'elis_program');

        // Course selector
        if (empty($this->_customdata['obj']->moodlecourseid)) {
            $this->add_moodle_course_select();
        } else {
            $PAGE->requires->js('/elis/program/js/classform.js');
            $courseSelected = array();
            $coursename = $DB->get_field('course', 'fullname', array('id'=>$this->_customdata['obj']->moodlecourseid));
            $courseSelected[] = $mform->createElement('static', 'class_attached_course', get_string('class_attached_course', 'elis_program') . ':',  "<a href=\"$CFG->wwwroot/course/view.php?id={$this->_customdata['obj']->moodlecourseid}\">$coursename</a>");
            //only show checkbox if current action is edit
            $current_action    = optional_param('action','view',PARAM_ALPHA);
            if ($current_action == 'edit') {
                $options = array();
                //set group to null
                $options['group']=null;
                $options['onclick'] = "return class_confirm_unlink(this,'".get_string('class_unlink_confirm', 'elis_program')."')";
                $courseSelected[] = $mform->createElement('advcheckbox', 'unlink_attached_course', get_string('class_unlink_attached_course', 'elis_program') . ':', get_string('class_unlink_attached_course', 'elis_program'), $options);
            }
            $mform->addGroup($courseSelected, 'courseSelected', get_string('class_attached_course', 'elis_program') . ':');
            $mform->addHelpButton('courseSelected', 'pmclassform:moodlecourse', 'elis_program');
            $mform->addElement('hidden', 'moodlecourseid');
        }

        $mform->addElement('advcheckbox', 'enrol_from_waitlist', get_string('waitlistenrol', 'elis_program') . ':');
        $mform->addHelpButton('enrol_from_waitlist', 'pmclassform:waitlistenrol', 'elis_program');

        // custom fields
        $this->add_custom_fields('class','elis/program:class_edit', 'elis/program:class_view', 'course');

        $this->add_action_buttons();
    }

    /**
     * Load the options into the track selection boxes based on the
     * selected course.
     */
    function definition_after_data() {
        $mform =& $this->_form;
        $courseid = $mform->getElementValue('courseid');
        if ($courseid) {
            $courseid = (int)array_shift($courseid);
        } else {
            if(!empty($this->_customdata['obj']->courseid)) {
                $courseid = $this->_customdata['obj']->courseid;
            } else if (!empty($this->firstcourse)) {
                $courseid = $this->firstcourse->id;
            }
        }
        if ($courseid) {
            $unassigned = array();
            $assigned = array();

            // Multi select box for choosing tracks
            $tracks = track_get_listing();
            $tracks = empty($tracks) ? array() : $tracks;

            $curcrsassign = curriculumcourse_get_list_by_course($courseid);
            foreach ($curcrsassign as $recid => $curcrsrec) {
                foreach ($tracks as $trackid => $trackrec) {
                    if ($trackrec->curid == $curcrsrec->curriculumid) {
                        if (!empty($this->_customdata['obj']->id)) {
                            $trkobj = new trackassignment(array('classid' => $this->_customdata['obj']->id,
                                                                'trackid' => $trackid));
                            if (!$trkobj->is_class_assigned_to_track()) {
                                $unassigned[$trackid] = $trackrec->name;
                            } else {
                                // Create list for currently assigned tracks
                                $assigned[$trackid] = $trackrec->name;
                            }
                        } else {
                            $unassigned[$trackid] = $trackrec->name;
                        }
                    }

                }
            }
            unset($curcrsassign);

            $temp = array('assignedtrack' => array_keys($assigned));
            $this->set_data($temp);

            if ($mform->elementExists('assignedtrack')) {
                $track_el =& $mform->getElement('assignedtrack');
                $track_el->load($assigned);
            }
            if ($mform->elementExists('track')) {
                $track_el =& $mform->getElement('track');
                $track_el->load($unassigned);
            }
        }
    }

    /**
     * Adds the multi-select box for tracks.  This form element
     * contents will change depending on which course was selected
     *
     * @param int $courseid the course id of the first item in the
     * course selection element
     *
     * @return string HTML for a multi-select element
     */
    function add_track_multi_select($courseid) {
        global $CFG, $PAGE;

        $PAGE->requires->js('/elis/program/js/trkmultiselect.js');

        $mform =& $this->_form;

        $assignedSelection = &$mform->addElement('select', 'assignedtrack', get_string('class_assigntrackhead', 'elis_program') . ':', array());
        $mform->freeze('assignedtrack');
        $assignedSelection->setMultiple(true);

        $unassignedSelections = &$mform->addElement('select', 'track', get_string('class_unassigntrackhead', 'elis_program') . ':', array());
        $unassignedSelections->setMultiple(true);
        $mform->addHelpButton('track', 'pmclassform:class_unassigntrackhead', 'elis_program');
    }

    /**
     * adds a moodle course selection box to the form
     *
     * @uses $CFG
     * @param $formid string A suffix to put on all 'id' and index for all 'name' attributes.
     *                       This should be unique if being used more than once in a form.
     * @param $extraclass string Any extra class information to add to the output.
     *
     * @return string The form HTML, without the form.
     */
    function add_moodle_course_select() {
        global $CFG, $DB;

        $mform =& $this->_form;

        $mform->addElement('html', '
<script type="text/javascript">
//<![CDATA[
    function update_crs_template() {
        var crselem = document.getElementById("id_courseid");
        var mdlcrselem = document.getElementById("id_moodleCourses_moodlecourseid");
        if (mdlcrselem && crselem && crselem.value) {

            var crstmpl_failure = function(o) {
                mdlcrselem.selectedIndex = 0;
            }

            var set_crs_tmpl = function(o) {
                var i;
                var mdlcrs = parseInt(o.responseText);
                mdlcrselem.selectedIndex = 0;
                for (i = 0; i < mdlcrselem.options.length; ++i) {
                    if (mdlcrs == mdlcrselem.options[i].value) {
                        mdlcrselem.selectedIndex = i;
                        break;
                    }
                }
            }

            var callback = {
                success:set_crs_tmpl,
                failure:crstmpl_failure
            }

            YAHOO.util.Connect.asyncRequest("GET", "coursetemplateid.php?courseid=" + crselem.value, callback, null);
        }
    }
//]]>
</script>
');

        $select = 'id != \'' . SITEID . '\' AND fullname NOT LIKE \'.%\'';

        $cselect = array(get_string('none', 'elis_program'));
        $crss = $DB->get_recordset_select('course', $select, null, 'fullname',
                                          'id, fullname');
        if (!empty($crss) && $crss->valid()) {
            foreach ($crss as $crs) {
                $cselect[$crs->id] = $crs->fullname;
            }
            $crss->close();
        }

        $moodleCourses = array();
        if (count($cselect) > 1) {
            $moodleCourses[] = $mform->createElement('select', 'moodlecourseid', get_string('moodlecourse', 'elis_program'), $cselect);
        } else {
            $mform->addElement('static', 'no_moodle_courses', get_string('moodlecourse', 'elis_program') . ':', get_string('no_moodlecourse', 'elis_program'));
            $mform->addHelpButton('no_moodle_courses', 'pmclassform:moodlecourse', 'elis_program');
        }

        // Add auto create checkbox if CM course uses a template
        if (empty($this->_customdata['obj']->courseid)) {
            $courseid = 0;
        } else {
            $courseid = $this->_customdata['obj']->courseid;
        }

        //attempt to retrieve the course template
        $template = coursetemplate::find(new field_filter('courseid', $courseid));
        if ($template->valid()) {
            $template = $template->current();
        }

        if (empty($courseid) || !empty($template->location)) {
            $moodleCourses[] = $mform->createElement('checkbox', 'autocreate', '', get_string('autocreate', 'elis_program'));
        }

        if (count($cselect) > 1) {
            $mform->addGroup($moodleCourses, 'moodleCourses', get_string('moodlecourse', 'elis_program') . ':');
            $mform->disabledIf('moodleCourses', 'moodleCourses[autocreate]', 'checked');
            $mform->addHelpButton('moodleCourses', 'pmclassform:moodlecourse', 'elis_program');
            if (!empty($template->location)) {
                //error_log("pmclassform::add_moodle_course_select() course template = {$template->location}");
                $mform->setDefault('moodleCourses[moodlecourseid]', $template->location);
            }
        }
    }

    /**
     *  make sure the start time is before the end time and the start date is before the end date for the class
     * @param array $data
     * @param mixed $files
     * @return array
     */
    function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);
        $sql = 'idnumber = ?';
        $params = array($data['idnumber']);
        if ($data['id']) {
            $sql .= ' AND id != ?';
            $params[] = $data['id'];
        }
        if ($DB->record_exists_select(pmclass::TABLE, $sql, $params)) {
            $errors['idnumber'] = get_string('idnumber_already_used', 'elis_program');
        }

        if (isset($data['starttime']) && isset($data['endtime'])) {
            if($data['starttime'] > $data['endtime']) {
                $errors['starttime'] = get_string('error_duration', 'elis_program');
            }

            if(!empty($data['startdate']) && !empty($data['enddate']) && !empty($data['disablestart']) && !empty($data['disableend'])) {
                if($data['startdate'] > $data['enddate']) {
                    $errors['start'] = get_string('error_date_range', 'elis_program');
                }
            }
        }

        if(!empty($this->_customdata['obj']) && !empty($this->_customdata['obj']->maxstudents)){
            if($data['maxstudents'] < $this->_customdata['obj']->maxstudents && $data['maxstudents'] < student::count_enroled($this->_customdata['obj']->id)) {
                $context = get_context_instance(CONTEXT_SYSTEM);
                if(!has_capability('elis/program:overrideclasslimit', $context)) {
                    $errors['maxstudents'] = get_string('error_n_overenrol', 'elis_program');
                }
            }
        }

        $errors += parent::validate_custom_fields($data, 'class');

        return $errors;
    }

    /**
      * Create a url to the current page.
     *
     * @param	array	extra	array of extra parameters
     * @return moodle_url
     */
    function get_moodle_url($extra = array()) {
        global $CFG;
        $params = array();

        $url = new moodle_url($CFG->wwwroot . '/elis/program/index.php', $params);

        foreach($extra as $name=>$value) {
            $url->param($name, $value);
        }

        return $url->out();
    }

    function get_data(){
        $data = parent::get_data();

        if (!empty($data)) {
            $mform =& $this->_form;

            if(!empty($mform->_submitValues['disablestart'])) {
                $data->startdate = 0;
            }

            if(!empty($mform->_submitValues['disableend'])) {
                $data->enddate = 0;
            }

            if(!empty($data->starttime) and
               !isset($mform->_submitValues['starttime']['timeenable'])) {
                $timearray = usergetdate($data->starttime);
                $data->starttimehour = (int)$timearray['hours'];
                $data->starttimeminute = (int)$timearray['minutes'];
            } else {
                $data->starttimehour = $data->starttimeminute = 61;
            }

            if(!empty($data->endtime) and
               !isset($mform->_submitValues['endtime']['timeenable'])) {
                $timearray = usergetdate($data->endtime);
                $data->endtimehour = (int)$timearray['hours'];
                $data->endtimeminute = (int)$timearray['minutes'];
            } else {
                $data->endtimehour = $data->endtimeminute = 61;
            }
        }
        return $data;
    }

    function freeze() {
        // Add completion status information
        $obj = new pmclass($this->_customdata['obj']);
        $counts = $obj->get_completion_counts();

        $counttext = "Passed: {$counts[2]}, Failed: {$counts[1]}, In Progress: {$counts[0]}";

        $this->_form->addElement('static', 'test', get_string('completion_status', 'elis_program'), $counttext);

        parent::freeze();
    }
}
