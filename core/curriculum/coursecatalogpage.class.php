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


require_once CURMAN_DIRLOCATION . '/lib/newpage.class.php';
require_once CURMAN_DIRLOCATION . '/lib/user.class.php';
require_once CURMAN_DIRLOCATION . '/lib/curriculumcourse.class.php';
require_once CURMAN_DIRLOCATION . '/lib/curriculumstudent.class.php';
require_once CURMAN_DIRLOCATION . '/lib/course.class.php';
require_once CURMAN_DIRLOCATION . '/lib/cmclass.class.php';
require_once CURMAN_DIRLOCATION . '/lib/student.class.php';
require_once CURMAN_DIRLOCATION . '/lib/table.class.php';

require_once CURMAN_DIRLOCATION . '/form/enrolconfirmform.class.php';
require_once CURMAN_DIRLOCATION . '/lib/waitlist.class.php';
require_once CURMAN_DIRLOCATION . '/lib/recordlinkformatter.class.php';

/// The main management page.
class coursecatalogpage extends newpage {
    var $pagename = 'crscat';
    var $section = 'crscat';

    function can_do_default() {
        global $CURMAN;

        if (!empty($CURMAN->config->disablecoursecatalog)) {
            return false;
        }

        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('block/curr_admin:viewcoursecatalog', $context);
    }

    function get_title_default() {
        return get_string('coursecatalog', 'block_curr_admin');
    }

    function get_navigation_default() {
        return $this->get_navigation_current();
    }

    function get_navigation_confirmwaitlist() {
        return $this->get_navigation_waitlist();
    }

    function get_navigation_savewaitlist() {
        return $this->get_navigation_waitlist();
    }

    function get_navigation_delwaitlist() {
        return $this->get_navigation_waitlist();
    }

    function get_navigation_waitlist() {
        $page = new coursecatalogpage(array());

        return array(
            array('name' => get_string('waitlistcourses', 'block_curr_admin'),
                  'link' => $page->get_url()),
            );
    }

    function get_navigation_current() {
        $page = new coursecatalogpage(array());
        return array(
            array('name' => get_string('currentcourses', 'block_curr_admin'),
                  'link' => $page->get_url()),
            );
    }

    function get_navigation_available() {
        $page = new coursecatalogpage(array());
        return array(
            array('name' => get_string('availablecourses', 'block_curr_admin'),
                  'link' => $page->get_url()),
            );
    }

    public function get_navigation_add() {
        $crsid        = cm_get_param('crsid', 0);
        $crs = new course($crsid);

        $navigation = $this->get_navigation_default();
        $navigation[] = array('name' => get_string('choose_class_course', 'block_curr_admin', $crs->name),
                              'link' => $this->get_url());

        return $navigation;
    }

    function action_add() {
        $crsid        = cm_get_param('crsid', 0);

        if ($classes = cmclass_get_listing('startdate', 'ASC', 0, 0, '', '', $crsid, true)) {
            $table = new addclasstable($classes);
            $table->print_table();
        } else {
            print_heading(get_string('no_classes_available', 'block_curr_admin'));
        }
    }

    public function action_waitlist() {
        global $CFG, $CURMAN, $USER;

        $cuserid = cm_get_crlmuserid($USER->id);

        $usercurs = curriculumstudent::get_curricula($cuserid);

        if(count($usercurs) > $CURMAN->config->catalog_collapse_count) {
            $buttonLabel = get_string('show', 'block_curr_admin');
            $extraclass = ' hide';
        }
        else {
            $buttonLabel = get_string('hide', 'block_curr_admin');
            $extraclass = '';
        }

        require_js($CFG->wwwroot . '/curriculum/js/util.js');
        $this->include_yui();

        if(!empty($usercurs)) {
            foreach($usercurs as $usercur) {
                print_heading('<div class="clearfix"></div><div class="headermenu"><script id="curriculum'.$usercur->curid.'script" type="text/javascript">toggleVisibleInit("curriculum'.$usercur->curid.'script", "curriculum'.$usercur->curid.'button", "' . $buttonLabel . '", "Hide", "Show", "curriculum'.$usercur->curid.'");</script></div>'. $usercur->name . ' (' . $usercur->idnumber . ')');

                echo '<div id="curriculum' . $usercur->curid . '" class="yui-skin-sam">';

                if($courses = student::get_waitlist_in_curriculum($cuserid, $usercur->curid)) {
                    echo "<div id=\"$usercur->curid\"></div>";

                    $table = new waitlisttable($courses);
                    $table->print_yui_table($usercur->curid);
                } else {
                    echo '<p>' . get_string('nocoursesinthiscurriculum', 'block_curr_admin') . '</p>';
                }


                echo '</div>';
            }
        } else {
            print_heading(get_string('nocoursesinthiscurriculum', 'block_curr_admin'));
        }

        echo '<br/>';
        print_box(get_string('lp_waitlist_instructions', 'block_curr_admin'), 'generalbox lp_instructions');

    }

    function action_savewaitlist() {
        global $USER, $CURMAN;

        $classid = cm_get_param('id', 0, PARAM_INT);

        $form = $this->create_waitlistform($classid);

        if($form->is_cancelled()) {
            $this->action_available();
        } else if($data = $form->get_data()) {
            $class = new cmclass($classid);

            $userid = cm_get_crlmuserid($USER->id);

            $position = $CURMAN->db->get_field(WATLSTTABLE, sql_max('position'), 'classid', $classid) + 1;

            $wait_record = new object();
            $wait_record->userid = $userid;
            $wait_record->classid = $classid;
            $wait_record->enrolmenttime = $class->startdate;
            $wait_record->timecraeted = time();
            $wait_record->position = $position;

            $wait_list = new waitlist($wait_record);
            $wait_list->add();

            $this->action_waitlist();
        }
    }

    function action_delwaitlist() {
        $waitlistid = required_param('id', PARAM_INT);

        $wait_list = new waitlist($waitlistid);
        $wait_list->delete();

        $this->action_waitlist();
    }

    function action_confirmwaitlist() {
        global $USER;

        $classid = cm_get_param('clsid', 0, PARAM_INT);

        $form = $this->create_waitlistform($classid);

        $form->display();
    }

    private function create_waitlistform($clsid) {
        $class = new cmclass($clsid);

//        $cuserid = cm_get_crlmuserid($USER->id);

        //form url to go submit and custom data
        $customdata = new object();
        $customdata->a = new object();
        $customdata->a->classid = $class->idnumber;
        $customdata->a->coursename = $class->course->name;
        $customdata->limit = $class->maxstudents;
        $customdata->enroled = student::count_enroled($class->id);
        $customdata->waitlisted = waitlist::count_records($class->id);

        $data = new object();
        $data->id = $class->id;

//s=crscat&amp;section=curr&amp;clsid=
        $url = new moodle_url(null, array('s'=>'crscat', 'action'=>'savewaitlist'));
        return new enrolconfirmform($url, array($customdata, 'obj'=>$data));
    }


    function action_savenew() {
        global $USER, $CFG, $CURMAN;

        $clsid = cm_get_param('clsid', 0);
        $class = new cmclass($clsid);

        if (!$class->is_enrollable()) {
            print_error('notenrollable');
        }

        // check if class is full
        if (!empty($class->maxstudents) && student::count_enroled($class->id) >= $class->maxstudents) {
            $form = $this->create_waitlistform($classid);

            $form->display();
            return;
        }

        // call the Moodle enrolment plugin if attached to a Moodle course, and
        // it's not the elis plugin
        $courseid = $class->get_moodle_course_id();
        if ($courseid) {
            $course = $CURMAN->db->get_record('course', 'id', $courseid);
            // the elis plugin is treated specially
            if ($course->enrol != 'elis') {
                // FIXME: add message
                redirect("$CFG->wwwroot/course/enrol.php?id=$courseid");
            }
        }

        $cuserid = cm_get_crlmuserid($USER->id);

        $sturecord                  = array();
        $sturecord['classid']       = $class->id;
        $sturecord['userid']        = $cuserid;
        $sturecord['enrolmenttime'] = max(time(), $class->startdate);
        $sturecord['completetime']  = 0;
        $newstu                     = new student($sturecord);

        if (($status = $newstu->add()) !== true) {
            if (!empty($status->message)) {
                echo cm_error('Record not created. Reason: '.$status->message);
            } else {
                echo cm_error('Record not created.');
            }
        }

        $this->action_default();
    }

    /**
     *
     */
    function action_default() {
        // Drop through to the current classes action by default
        $this->action_current();
    }

    /**
     * Includes the YUI files required for DataTable and the show/hide buttons.
     * @return unknown_type
     */
    function include_yui() {
        global $CFG;

        echo '<style>@import url("' . $CFG->wwwroot . '/lib/yui/datatable/assets/skins/sam/datatable.css");</style>';

        require_js(array('yui_dom-event', 'yui_dragdrop', 'yui_element', 'yui_datasource', 'yui_datatable'));

        // Monkey patch - not required with YUI 2.6.0 apparently
        // require_js('js/yui_2527707_patch.js');
    }

    /**
     * List the classes the user is enrolled in or instructs.
     * @todo Use language strings.
     * @return unknown_type
     */
    function action_current() {
        global $CFG, $USER, $CURMAN;

        $clsid        = cm_get_param('clsid', 0);

        // This is for a Moodle user, so get the Curriculum user id.
        $cuserid = cm_get_crlmuserid($USER->id);

        // Needed for the hide buttons
        //require_js('yui_yahoo');
        //require_js('yui_event');
        require_js($CFG->wwwroot . '/curriculum/js/util.js');

        $this->include_yui();

        $usercurs = curriculumstudent::get_curricula($cuserid);
        $instrclasses = user::get_instructed_classes($cuserid);
        $noncurclasses = user::get_non_curriculum_classes($cuserid);

        $numtables = 0;
        if($usercurs) $numtables += count($usercurs);
        if($instrclasses) $numtables += count($instrclasses);
        if($noncurclasses) $numtables += count($noncurclasses);

        if($numtables > $CURMAN->config->catalog_collapse_count) {
            $buttonLabel = get_string('show', 'block_curr_admin');
            $extraclass = ' hide';
        } else {
            $buttonLabel = get_string('hide', 'block_curr_admin');
            $extraclass = '';
        }
        // Process our curricula in turn, outputting the courses within each.
        if ($usercurs) {
            $showcurid = optional_param('showcurid',0,PARAM_INT);
            foreach ($usercurs as $usercur) {
                if ($classes = user::get_current_classes_in_curriculum($cuserid, $usercur->curid)) {
                    if ($showcurid > 0) {
                        // If we are passed the showcurid parameter then override the default show/hide settings
                        $buttonLabel = ($usercur->curid == $showcurid) ? get_string('hide', 'block_curr_admin') : get_string('show', 'block_curr_admin');
                        $extraclass = ($usercur->curid == $showcurid) ? '' : ' hide';
                    }
                    print_heading('<div class="clearfix"></div><div class="headermenu"><script id="curriculum'.$usercur->curid.'script" type="text/javascript">toggleVisibleInit("curriculum'.$usercur->curid.'script", "curriculum'.$usercur->curid.'button", "' . $buttonLabel . '", "Hide", "Show", "curriculum'.$usercur->curid.'");</script></div>'. $usercur->name . ' (' . $usercur->idnumber . ')');
                    echo '<div id="curriculum' . $usercur->curid.'" class="yui-skin-sam ' . $extraclass . '">';
                    $table = new currentclasstable($classes, $this->get_moodle_url());
                    echo "<div id=\"$usercur->id\"></div>";
                    $table->print_yui_table($usercur->id);
                } else {
                    $buttonLabel2 = ($usercur->curid == $showcurid) ? get_string('hide', 'block_curr_admin') : get_string('show', 'block_curr_admin');
                    $extraclass2 = ($usercur->curid == $showcurid) ? '' : ' hide';
                    print_heading('<div class="clearfix"></div><div class="headermenu"><script id="curriculum'.$usercur->curid.'script" type="text/javascript">toggleVisibleInit("curriculum'.$usercur->curid.'script", "curriculum'.$usercur->curid.'button", "' . $buttonLabel2 . '", "Hide", "Show", "curriculum'.$usercur->curid.'");</script></div>'. $usercur->name . ' (' . $usercur->idnumber . ')');
                    echo '<div id="curriculum' . $usercur->curid.'" class="yui-skin-sam ' . $extraclass2 . '">';
                    echo '<p>' . get_string('nocoursesinthiscurriculum', 'block_curr_admin') . '</p>';
                }
                echo '</div>';

            }
        } else {
            print_heading(get_string('notassignedtocurricula', 'block_curr_admin'));
        }

        // Print out a table for classes not belonging to any curriculum
        if ($noncurclasses) {
            $labelshow = get_string('show', 'block_curr_admin');
            $labelhide = get_string('hide', 'block_curr_admin');
            print_heading('<div class="clearfix"></div><div class="headermenu"><script id="noncurrscript" type="text/javascript">toggleVisibleInit("noncurrscript", "noncurrbutton", "' . $buttonLabel . '", "'.$labelhide.'", "'.$labelshow.'", "noncurr");</script></div>'. get_string('othercourses', 'block_curr_admin'));

            echo '<div id="noncurr" class="yui-skin-sam ' . $extraclass . '">';

            echo '<div id="noncurrtable"></div>';

            $table = new currentclasstable($noncurclasses, $this->get_moodle_url());
            $table->print_yui_table("noncurrtable");

            echo '</div>';
        } else {
            // Display nothing if we don't have any non-curriculum classes
        }

        // Print out a table for classes we instruct
        if ($instrclasses) {
            print_heading('<div class="clearfix"></div><div class="headermenu"><script id="instrscript" type="text/javascript">toggleVisibleInit("instrscript", "instrbutton", "' . $buttonLabel . '", "Hide", "Show", "instr");</script></div>'. get_string('instructedcourses', 'block_curr_admin'));

            echo '<div id="instr" class="yui-skin-sam ' . $extraclass . '">';

            echo '<div id="instrtable"></div>';

            $table = new instructortable($instrclasses, $this->get_moodle_url());
            $classpage = new cmclasspage();
            $table->decorators['classname'] = new recordlinkformatter($classpage,'id');
            $table->print_yui_table("instrtable");

            echo '</div>';
        } else {
            // Display nothing if we don't instruct any classes
        }

        echo '<br/>';
        print_box(get_string('lp_class_instructions', 'block_curr_admin'), 'generalbox lp_instructions');

    }

    /**
     * curriculum overview menu
     *
     * @global object $CFG
     * @global object $USER
     * @global object $CURMAN
     */
    function action_available() {
        global $CFG, $USER, $CURMAN;

    /// This is for a Moodle user, so get the Curriculum user id.
        $cuserid = cm_get_crlmuserid($USER->id);

        $usercurs = curriculumstudent::get_curricula($cuserid);

        if(count($usercurs) > $CURMAN->config->catalog_collapse_count) {
            $buttonLabel = get_string('show', 'block_curr_admin');
            $extraclass = ' hide';
        }
        else {
            $buttonLabel = get_string('hide', 'block_curr_admin');
            $extraclass = '';
        }

        require_js($CFG->wwwroot . '/curriculum/js/util.js');
        $this->include_yui();

        // Process this user's curricula in turn, outputting the courses within each.
        if ($usercurs) {
            foreach ($usercurs as $usercur) {
                print_heading('<div class="clearfix"></div><div class="headermenu"><script id="curriculum'.$usercur->curid.'script" type="text/javascript">toggleVisibleInit("curriculum'.$usercur->curid.'script", "curriculum'.$usercur->curid.'button", "' . $buttonLabel . '", "Hide", "Show", "curriculum'.$usercur->curid.'");</script></div>'. $usercur->name . ' (' . $usercur->idnumber . ')');

                echo '<div id="curriculum'.$usercur->curid.'" class="yui-skin-sam ' . $extraclass . '">';
                if ($courses = user::get_user_course_curriculum($cuserid, $usercur->curid)) {

                    echo "<div id=\"$usercur->id\"></div>";

                    $table = new availablecoursetable($courses);
                    $table->print_yui_table($usercur->id);
                } else {
                    echo '<p>' . get_string('nocoursesinthiscurriculum', 'block_curr_admin') . '</p>';
                }

                echo '</div>';
            }
        } else {
            print_heading(get_string('nocoursesinthiscurriculum', 'block_curr_admin'));
        }

        echo '<br/>';
        print_box(get_string('lp_curriculum_instructions', 'block_curr_admin'), 'generalbox lp_instructions');

    }

    /**
     * Group a collection of rows by a particular column.  Output is an array where the keys are the
     * names of the groups.
     * @param $collection collection of rows
     * @param $column name of the column to group by
     * @return unknown_type
     */
    function group_by_column($collection, $column) {
        $r = array();
        foreach($collection as $row) {
            $val = $row->$column;
            if(!isset($r[$val])) {
                $r[$val] = array();
            }
            $r[$val][] = $row;
        }

        return $r;
    }
}

class waitlisttable extends display_table {
    public function __construct(&$items) {
        global $USER, $CFG;
        $this->cuserid = cm_get_crlmuserid($USER->id);

        $columns = array(
            'idnumber'    => 'Course ID',
            'name'  => get_string('course'),
            'clsid'   => get_string('class', 'block_curr_admin'),
            'startdate'   => get_string('startdate', 'block_curr_admin'),
            'enddate'     => get_string('enddate', 'block_curr_admin'),
//            'timeofday'   => get_string('timeofday', 'block_curr_admin'),
            'instructor'  => get_string('instructor', 'block_curr_admin'),
            'environment' => get_string('environment', 'block_curr_admin'),
            'position'  => get_string('position', 'block_curr_admin'),
            'maxstudents' => get_string('class_limit', 'block_curr_admin'),
            'management' => ''
            );

        $this->yui_formatters = array(
//            'timeofday' => 'cmFormatTimeRange',
            'startdate' => 'cmFormatDate',
            'enddate' => 'cmFormatDate',
        );

        $this->yui_parsers = array(
            'startdate' => 'date',
            'enddate' => 'date',
        );

//        $this->yui_sorters = array(
//            'timeofday' => 'cmSortTimeRange',
//        );

        $pageurl = new moodle_url($CFG->wwwroot . '/curriculum/index.php', array('s' => 'crscat'));
        parent::__construct($items, $columns, $pageurl);

        $this->table->width = '80%';
    }

    function is_sortable_default() {
        return false;
    }

    public function get_item_display_management($column, $item) {
        global $CFG;

        $retval = '';
        $retval .= '<div align="center">';
        $retval .= '<form action=' . $this->pageurl->out(false, array('action'=>'waitlist')) . ' method="post">';
        $hidden = new moodle_url(null, array('action'=>'delwaitlist', 'id'=>$item->wlid));
        $retval .= $hidden->hidden_params_out();
        $retval .= '<input type="image" alt="delete" src="pix/delete.gif" /> ';
        $retval .= '</form>';
        $retval .= '</div>';

        return $retval;
    }

    function get_item_display_timeofday($column, $item) {
        if ((!empty($item->starttimehour) || !empty($item->starttimeminute)) &&
            (!empty($item->endtimehour) || !empty($item->endtimeminute))) {
                return array($item->starttimehour, $item->starttimeminute,
                            $item->endtimehour, $item->endtimeminute);
        } else {
            return array(0,0,0,0);
        }
    }

    function get_item_display_instructor($column, $item) {
        if ($instructors = instructor::get_instructors($item->id)) {
            $ins = array();

            foreach ($instructors as $instructor) {
                $ins[] = cm_fullname($instructor);
            }

            if (!empty($ins)) {
                return implode('<br />', $ins);
            }
        } else {
            return 'n/a';
        }
    }

    function get_item_display_environment($column, $item) {
        global $CURMAN;

        if (!empty($item->environmentid)) {
            return $CURMAN->db->get_field(ENVTABLE, 'name', 'id', $item->environmentid);
        } else {
            return 'n/a';
        }
    }
}

class currentclasstable extends display_table {
    function __construct(&$items, $pageurl) {
        global $USER;
        $this->cuserid = cm_get_crlmuserid($USER->id);

        $columns = array(
            'courseid'    => get_string('course_id', 'block_curr_admin'),
            'coursename'  => get_string('course'),
            'classname'   => get_string('class', 'block_curr_admin'),
            'startdate'   => get_string('startdate', 'block_curr_admin'),
            'enddate'     => get_string('enddate', 'block_curr_admin'),
            'timeofday'   => get_string('timeofday', 'block_curr_admin'),
            'instructor'  => get_string('instructor', 'block_curr_admin'),
            'environment' => get_string('environment', 'block_curr_admin'),
            );

        $this->yui_formatters = array(
//            'timeofday' => 'cmFormatTimeRange',
            'startdate' => 'cmFormatDate',
            'enddate' => 'cmFormatDate',
        );

        $this->yui_parsers = array(
            'startdate' => 'date',
            'enddate' => 'date',
        );

        $this->yui_sorters = array(
//            'timeofday' => 'cmSortTimeRange',
        );

        parent::__construct($items, $columns, $pageurl);

        $this->table->width = '80%';
    }

    function is_sortable_default() {
        return true;
    }

    function get_class($item) {
        if (!isset($this->current_class) || !isset($this->current_class->courseid) || $this->current_class->courseid != $item->courseid) {
            if (!empty($item->classid)) {
                $this->current_class = get_record('crlm_class', 'id', $item->classid);
            } else {
                $this->current_class = false;
            }
        }
        return $this->current_class;
    }

    function get_item_display_courseid($column, $item) {
        return get_field(CRSTABLE, 'idnumber', 'id', $item->courseid);
    }

    function get_item_display_coursename($column, $item) {
        return $item->coursename;
    }

    function get_item_display_classname($column, $item) {
        global $CFG;

        $this->get_class($item);
        $classid = $this->current_class->idnumber;
        if ($mdlcrs = moodle_get_course($this->current_class->id)) {
            $classid .= ' - <a href="' . $CFG->wwwroot . '/course/view.php?id=' .
                $mdlcrs . '">' . get_string('moodlecourse', 'block_curr_admin') . '</a>';
        }
        return $classid;
    }

    function get_item_display_startdate($column, $item) {
        return $this->get_date_item_display('startdate',$this->current_class);
    }

    function get_item_display_enddate($column, $item) {
        return $this->get_date_item_display('enddate',$this->current_class);
    }

    // TBD - Language strings: n/a, am, pm, ...
    function get_item_display_timeofday($column, $item) {
        global $CURMAN;
        if (($classdata = $this->get_class($item))) {
            if ((!empty($classdata->starttimehour) || !empty($classdata->starttimeminute)) &&
                (!empty($classdata->endtimehour) || !empty($classdata->endtimeminute))) {
                $start_ampm = '';
                $end_ampm = '';
                $starthr = $classdata->starttimehour;
                $endhr = $classdata->endtimehour;
                if ($CURMAN->config->time_format_12h) {
                    if ($starthr > 12) {
                        $starthr -= 12;
                        $start_ampm = ' pm';
                    } else {
                        $start_ampm = ' am';
                        if (!$starthr) {
                            $starthr = 12;
                        }
                    }
                    if ($endhr > 12) {
                        $endhr -= 12;
                        $end_ampm = ' pm';
                    } else {
                        $end_ampm = ' am';
                        if (!$endhr) {
                            $endhr = 12;
                        }
                    }
                }
                return sprintf("%d:%02d%s - %d:%02d%s", $starthr,
                               $classdata->starttimeminute, $start_ampm,
                               $endhr, $classdata->endtimeminute, $end_ampm);
               /*
                array($starthr, $classdata->starttimeminute,
                      $endhr, $classdata->endtimeminute);
               */
            }
        }
        return 'n/a'; /* array(0,0,0,0); */
    }

    function get_item_display_instructor($column, $item) {
        if ($this->get_class($item)) {
            if ($instructors = instructor::get_instructors($this->current_class->id)) {
                $ins = array();

                foreach ($instructors as $instructor) {
                    $ins[] = cm_fullname($instructor);
                }

                if (!empty($ins)) {
                    return implode('<br />', $ins);
                }
            } else {
                return 'n/a';
            }
        } else {
            return 'n/a';
        }
    }

    function get_item_display_environment($column, $item) {
        global $CURMAN;
        if ($this->get_class($item)) {
            if (!empty($this->current_class->environmentid)) {
                return $CURMAN->db->get_field(ENVTABLE, 'name', 'id', $this->current_class->environmentid);
            } else {
                return 'n/a';
            }
        } else {
            return 'n/a';
        }
    }
}

class instructortable extends currentclasstable {
    function get_class($item) {
        if (!isset($this->current_class) || !isset($this->current_class->id) || $this->current_class->id != $item->id) {
            $this->current_class = new cmclass($item->id);
        }
        return $this->current_class;
    }
}

class availablecoursetable extends display_table {
    function __construct(&$items) {
        global $USER;
        $this->cuserid = cm_get_crlmuserid($USER->id);

        $columns = array(
            'coursename'  => get_string('coursename', 'block_curr_admin'),
            'courseid'    => get_string('course_id', 'block_curr_admin'),
            'classname'   => '',        //action and status information
            );
        parent::__construct($items, $columns,'');

        $this->table->width = '80%';
    }

    function is_sortable_default() {
        return false;
    }

    function get_item_display_courseid($column, $item) {
        return get_field(CRSTABLE, 'idnumber', 'id', $item->courseid);
    }

    function get_item_display_coursename($column, $item) {
        return $item->coursename;
    }

    function get_item_display_classname($column, $item) {
        if(isset($item->completionid)) {
            if($item->completionid == STUSTATUS_NOTCOMPLETE) {
                return get_string('onenroledlist', 'block_curr_admin');
            } elseif($item->completionid == STUSTATUS_PASSED) {
                return get_string('onpassed', 'block_curr_admin');
            } elseif($item->completionid == STUSTATUS_FAILED) {
                return get_string('onfailed', 'block_curr_admin');
            }
        } elseif (!empty($item->prereqcount)) {
            return get_string('unsatisfiedprereqs', 'block_curr_admin');
        } elseif (empty($item->classcount)) {
            return get_string('noclassavail', 'block_curr_admin');
        } elseif(!empty($item->waiting)) {
            return get_string('onwaitlist', 'block_curr_admin');
        } else {
            return get_string('noclassyet', 'block_curr_admin') . ' - <a href="' .
                'index.php?s=crscat&amp;section=curr&amp;crsid=' . $item->courseid .
                '&amp;action=add">' . get_string('chooseclass', 'block_curr_admin') .
                '</a>';
        }
    }
}

class addclasstable extends display_table {
    function __construct(&$items) {
        $columns = array(
            'options'   => '',
            'idnumber'   => get_string('idnumber'),
            'startdate' => get_string('startdate', 'block_curr_admin'),
            'enddate'   => get_string('enddate', 'block_curr_admin'),
            'timeofday'   => get_string('timeofday', 'block_curr_admin'),
            'instructor'  => get_string('instructor', 'block_curr_admin'),
            'environment' => get_string('environment', 'block_curr_admin'),
            'waitlistsize' => get_string('waitlist_size', 'block_curr_admin'),
            'classsize' => get_string('class_size', 'block_curr_admin')
            );

        parent::__construct($items, $columns, '');

        unset($this->table->width);
    }

    function print_table() {
        parent::print_table();
        print('<div class="note">' . get_string('if_class_full', 'block_curr_admin') . '</div>');
    }

    function is_sortable_default() {
        return false;
    }

    function get_item_display_waitlistsize($column, $class) {
        require_once CURMAN_DIRLOCATION . '/lib/waitlist.class.php';
        return waitlist::count_records($class->id);
    }

    function get_item_display_classsize($column, $class) {
        $retval = 'n/a';

        if(!empty($class->maxstudents)) {
            $students = student::count_enroled($class->id);
            $retval = $students . '/' . $class->maxstudents;
        }

        return $retval;
    }

    function get_item_display_options($column, $class) {
        $classobj = new cmclass($class);
        if(!$classobj->is_enrollable()) {
            return get_string('notenrollable');
        }

        if(student::count_enroled($class->id) < $class->maxstudents || empty($class->maxstudents)) {
            $action = 'savenew';
        } else {
            $action = 'confirmwaitlist';
        }


        return '<a href="index.php?s=crscat&amp;section=curr&amp;clsid=' .
            $class->id . '&amp;action=' . $action . '">' . get_string('choose_label', 'block_curr_admin') . '</a>';
    }

    function get_item_display_startdate($column, $class) {
        return $this->get_date_item_display($column, $class);
    }

    function get_item_display_enddate($column, $class) {
        return $this->get_date_item_display($column, $class);
    }

    // TODO: fix time-of-day display
    function get_item_display_timeofday($column, $class) {
        global $CURMAN;
        if ((!empty($class->starttimehour) || !empty($class->starttimeminute)) &&
            (!empty($class->endtimehour) || !empty($class->endtimeminute))) {

            if ($CURMAN->config->time_format_12h) {
                $starthour = $class->starttimehour;
                $startampm = $starthour >= 12 ? 'pm' : 'am';
                if ($starthour > 12) {
                    $starthour -= 12;
                } else if ($starthour == 0) {
                    $starthour = 12;
                }
                $endhour = $class->endtimehour;
                $endampm = $endhour >= 12 ? 'pm' : 'am';
                if ($endhour > 12) {
                    $endhour -= 12;
                } else if ($endhour == 0) {
                    $endhour = 12;
                }
                return sprintf("%d:%02d %s - %d:%02d %s",
                               $starthour, $class->starttimeminute, $startampm,
                               $endhour, $class->endtimeminute, $endampm);
            } else {
                return sprintf("%d:%02d - %d:%02d",
                               $class->starttimehour, $class->starttimeminute,
                               $class->endtimehour, $class->endtimeminute);
            }
        } else {
            return 'n/a';
        }
    }

    function get_item_display_instructor($column, $class) {
        if ($instructors = instructor::get_instructors($class->id)) {
            $ins = array();

            foreach ($instructors as $instructor) {
                $ins[] = cm_fullname($instructor);
            }

            return implode('<br />', $ins);
        } else {
            return 'n/a';
        }
    }

    function get_item_display_environment($column, $class) {
        return !empty($class->envname) ? $class->envname : 'n/a';
    }
}
?>
