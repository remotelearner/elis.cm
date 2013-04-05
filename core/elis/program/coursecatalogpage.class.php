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
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once(elispm::lib('lib.php'));
require_once(elispm::lib('deprecatedlib.php')); // cm_get_param(), cm_error()
require_once(elispm::lib('page.class.php'));
require_once elis::lib('table.class.php');
require_once elispm::lib('data/curriculumcourse.class.php');
require_once elispm::lib('data/curriculumstudent.class.php');
require_once elispm::lib('data/student.class.php');
require_once elispm::file('form/enrolconfirmform.class.php');
require_once elispm::file('pmclasspage.class.php');

/* *** TBD
require_once CURMAN_DIRLOCATION . '/lib/recordlinkformatter.class.php';
*** */

/// The main management page.
class coursecatalogpage extends pm_page {
    var $pagename   = 'crscat';
    var $section    = 'crscat';
    var $form_class = 'enrolconfirmform';

    /**
     * Horizontal scrollbar needed for [waitlist] yui table in IE(8)
     * set to empty string '' to disable
     */
    var $div_attrs  = 'style="float: center; overflow: auto;"';

    function can_do_default() {
        if (!empty(elis::$config->elis_program->disablecoursecatalog)) {
            return false;
        }

        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('elis/program:viewcoursecatalog', $context);
    }

    function get_title_default() {
        return get_string('coursecatalog', 'elis_program');
    }

    function build_navbar_default($who = null) { // get_navigation_default()
        $this->build_navbar_current();
    }

    function build_navbar_confirmwaitlist() { // get_navigation_confirmwaitlist()
        $this->build_navbar_waitlist();
    }

    function build_navbar_savewaitlist() { // get_navigation_savewaitlist()
        $this->build_navbar_waitlist();
    }

    function build_navbar_delwaitlist() { // get_navigation_delwaitlist()
        $this->build_navbar_waitlist();
    }

    function build_navbar_waitlist() { // get_navigation_waitlist()
        //$action = optional_param('action', 'default', PARAM_CLEAN);
        //$page = $this->get_new_page(array('action' => $action), true); //new coursecatalogpage(array());
        $page = $this->get_new_page(); //new coursecatalogpage(array());
        $this->navbar->add(get_string('waitlistcourses', 'elis_program'),
                           $page->url);
    }

    function build_navbar_current() { // get_navigation_current()
        //$action = optional_param('action', 'default', PARAM_CLEAN);
        //$page = $this->get_new_page(array('action' => $action), true); //new coursecatalogpage(array());
        $page = $this->get_new_page(); //new coursecatalogpage(array());
        $this->navbar->add(get_string('currentcourses', 'elis_program'),
                           $page->url);
    }

    function build_navbar_available() { // get_navigation_available()
        //$action = optional_param('action', 'default', PARAM_CLEAN);
        //$page = $this->get_new_page(array('action' => $action), true); //new coursecatalogpage(array());
        $page = $this->get_new_page(); //new coursecatalogpage(array());
        $this->navbar->add(get_string('availablecourses', 'elis_program'),
                           $page->url);
    }

    public function build_navbar_add() { // get_navigation_add()
        $crsid = cm_get_param('crsid', 0);
        $crs   = new course($crsid);

        //$action = optional_param('action', 'default', PARAM_CLEAN);
        $page = $this->get_new_page(); //new coursecatalogpage(array());
        $fullurl = $page->url;
        $page->url->remove_params('crsid');
        $this->navbar->add(get_string('currentcourses', 'elis_program'),
                           $page->url);
        //$page = $this->get_new_page(array('action' => $action, 'crsid' => $crsid), true); //new coursecatalogpage(array());
        $this->navbar->add(get_string('choose_class_course', 'elis_program', $crs->name),
                           $fullurl);

    }

    function display_add() { // action_add
        global $OUTPUT;
        $crsid = cm_get_param('crsid', 0);

        $classes = pmclass_get_listing('startdate', 'ASC', 0, 0, '', '', $crsid, true);
        if ($classes->valid() === true) {
            $table = new addclasstable($classes);
            $table->print_table();
        } else {
            echo $OUTPUT->heading(get_string('no_classes_available', 'elis_program'));
        }
        unset($classes);
    }

    public function display_waitlist() { // action_waitlist
        global $OUTPUT, $PAGE, $USER;

        $cuserid = cm_get_crlmuserid($USER->id);

        $usercurs = curriculumstudent::get_curricula($cuserid);

        if(count($usercurs) > elis::$config->elis_program->catalog_collapse_count) {
            $buttonLabel = get_string('show');
            $extraclass = ' hide';
        }
        else {
            $buttonLabel = get_string('hide');
            $extraclass = '';
        }

        // Needed for the hide buttons
        $this->include_js();

        if(!empty($usercurs)) {
            foreach($usercurs as $usercur) {
                echo $OUTPUT->heading('<div class="clearfix"></div><div class="headermenu"><script id="curriculum'.$usercur->curid.'script" type="text/javascript">toggleVisibleInit("curriculum'.$usercur->curid.'script", "curriculum'.$usercur->curid.'button", "' . $buttonLabel . '", "Hide", "Show", "curriculum'.$usercur->curid.'");</script></div>'. $usercur->name . ' (' . $usercur->idnumber . ')');

                echo '<div id="curriculum'. $usercur->curid ."\" {$this->div_attrs} class=\"yui-skin-sam\">";

                $courses = student::get_waitlist_in_curriculum($cuserid, $usercur->curid);
                if($courses->valid() === true) {
                    echo "<div id=\"$usercur->curid\"></div>";

                    $table = new waitlisttable($courses);
                    $table->print_yui_table($usercur->curid);
                } else {
                    echo '<p>' . get_string('nocoursesinthiscurriculum', 'elis_program') . '</p>';
                }
                unset($courses);

                echo '</div>';
            }
        } else {
            echo $OUTPUT->heading(get_string('nocoursesinthiscurriculum', 'elis_program'));
        }

        echo '<br/>';
        echo $OUTPUT->box(get_string('lp_waitlist_instructions', 'elis_program'),
                          'generalbox lp_instructions');

    }

    function do_savewaitlist() { // action_savewaitlist
        global $USER, $DB;

        $classid = cm_get_param('id', 0, PARAM_INT);

        $form = $this->create_waitlistform($classid);

        $now = time();

        if($form->is_cancelled()) {
            $this->display('available');
        } else if($data = $form->get_data()) {
            $class = new pmclass($classid);

            $userid = cm_get_crlmuserid($USER->id);

            $position = $DB->get_field(waitlist::TABLE, 'MAX(position)', array('classid' => $classid)) + 1;

            $wait_record = new object();
            $wait_record->userid = $userid;
            $wait_record->classid = $classid;
            $wait_record->enrolmenttime = $class->startdate;
            $wait_record->timecreated = $now;
            $wait_record->timemodified = $now;
            $wait_record->position = $position;

            $wait_list = new waitlist($wait_record);
            $wait_list->save(); // TBD: was ->add()

            $this->display('waitlist');
        }
    }

    function do_delwaitlist() {
        $waitlistid = required_param('id', PARAM_INT);

        $wait_list = new waitlist($waitlistid);
        $wait_list->delete();

        $this->display('waitlist'); // $this->action_waitlist();
    }

    function display_confirmwaitlist() {
        $classid = cm_get_param('clsid', 0, PARAM_INT);

        $form = $this->create_waitlistform($classid);

        $form->display();
    }

    private function create_waitlistform($clsid) {
        $class = new pmclass($clsid);

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


    function display_savenew() { // action_savenew()
        global $USER, $CFG, $DB;

        $clsid = cm_get_param('clsid', 0);
        $class = new pmclass($clsid);

        if (!$class->is_enrollable()) {
            print_error('notenrollable', 'enrol'); // TBD
        }

        // check if class is full
        if (!empty($class->maxstudents) && student::count_enroled($class->id) >= $class->maxstudents) {
            $form = $this->create_waitlistform($classid);

            $form->display();
            return;
        }

        // call the Moodle enrolment plugin if attached to a Moodle course, and
        // it's not the elis plugin
        //todo: check Moodle enrolment plugins here

        $cuserid = cm_get_crlmuserid($USER->id);

        $sturecord                  = array();
        $sturecord['classid']       = $class->id;
        $sturecord['userid']        = $cuserid;
        $sturecord['enrolmenttime'] = max(time(), $class->startdate);
        $sturecord['completetime']  = 0;
        $newstu                     = new student($sturecord);

        $courseid = $class->get_moodle_course_id();
        if ($courseid) {
            $course = $DB->get_record('course', array('id' => $courseid));

            // check that the elis plugin allows for enrolments from the course
            // catalog -- if not, see if there are other plugins that allow
            // self-enrolment.
            $plugin = enrol_get_plugin('elis');
            $enrol = $plugin->get_or_create_instance($course);
            if (!$enrol->{enrol_elis_plugin::ENROL_FROM_COURSE_CATALOG_DB}) {
                // get course enrolment plugins, and see if any of them allow self-enrolment
                $enrols = enrol_get_plugins(true);
                $enrolinstances = enrol_get_instances($course->id, true);
                foreach($enrolinstances as $instance) {
                    if (!isset($enrols[$instance->enrol])) {
                        continue;
                    }
                    $form = $enrols[$instance->enrol]->enrol_page_hook($instance);
                    if ($form) {
                        // at least one plugin allows self-enrolment -- send
                        // the user to the course enrolment page, and prevent
                        // automatic enrolment
                        $newstu->no_moodle_enrol = true;
                        $newstu->save();
                        redirect("$CFG->wwwroot/course/enrol.php?id=$courseid");
                        return;
                    }
                }
            }
        }

        $newstu->save();

        $tmppage = new coursecatalogpage(array('action' => 'default'));
        redirect($tmppage->url);
    }

    /**
     *
     */
    function display_default() {
        // Drop through to the current classes action by default
        $this->display_current();
    }

    /**
     * Includes the required JavaScript/YUI files for DataTable and the show/hide buttons.
     * @uses $CFG
     * @uses $PAGE
     * @return none
     */
    function include_js() {
        global $CFG, $PAGE;

        echo '<style>@import url("' . $CFG->wwwroot . '/lib/yui/datatable/assets/skins/sam/datatable.css");</style>';

        // $PAGE->requires->yui2_lib(array('dom', 'event', 'dragdrop', 'element', 'datasource', 'datatable')); // TBD

        // Monkey patch - not required with YUI 2.6.0 apparently
        // require_js('js/yui_2527707_patch.js');

      /* *** TBD: this is the prefered way, but not working!?!
        $PAGE->requires->js('/elis/program/js/util.js');
        $PAGE->requires->js_init_call('toggleVisibleInit',
                             array(), // will require args to be passed to fcn
                             true,
                             array('name'     => 'toggleHideShow',
                                   'fullpath' => '/elis/program/js/util.js',
                                   'requires' => array())
                         ); // args?
      *** */
      echo "<script type=\"text/javascript\" src=\"{$CFG->wwwroot}/elis/program/js/util.js\"></script>";
    }

    /**
     * List the classes the user is enrolled in or instructs.
     * @todo Use language strings.
     * @uses $OUTPUT
     * @uses $PAGE
     * @uses $USER
     * @return unknown_type
     */
    function display_current() { // action_current()
        global $OUTPUT, $PAGE, $USER;

        //$clsid = cm_get_param('clsid', 0);

        // This is for a Moodle user, so get the Curriculum user id.
        $cuserid = cm_get_crlmuserid($USER->id);
        if (empty($cuserid)) {
            return;
        }

        // Needed for the hide buttons
        $this->include_js();

        $usercnt = 0;
        $usercurs = curriculumstudent::get_curricula($cuserid, $usercnt);
        $instrcnt = 0;
        $instrclasses = user::get_instructed_classes($cuserid, $instrcnt);
        $noncurcnt = 0;
        $noncurclasses = user::get_non_curriculum_classes($cuserid, $noncurcnt);

        $numtables = $usercnt + $instrcnt + $noncurcnt;
        if ($numtables > elis::$config->elis_program->catalog_collapse_count) {
            $buttonLabel = get_string('show');
            $extraclass = ' hide';
        } else {
            $buttonLabel = get_string('hide');
            $extraclass = '';
        }

        // Process our curricula in turn, outputting the courses within each.
        if ($usercnt) {
            $showcurid = optional_param('showcurid',0,PARAM_INT);
            foreach ($usercurs as $usercur) {
                // make sure the curriculum still exists!
                $curr = curriculum::find(new field_filter('id', $usercur->curid));
                if (empty($curr) || empty($curr->rs) || !$curr->rs->valid()) {
                    continue;
                }
                if ($classes = user::get_current_classes_in_curriculum($cuserid, $usercur->curid)) {
                    if ($showcurid > 0) {
                        // If we are passed the showcurid parameter then override the default show/hide settings
                        $buttonLabel = ($usercur->curid == $showcurid) ? get_string('hide') : get_string('show');
                        $extraclass = ($usercur->curid == $showcurid) ? '' : ' hide';
                    }
                    echo $OUTPUT->heading('<div class="clearfix"></div><div class="headermenu"><script id="curriculum'.$usercur->curid.'script" type="text/javascript">toggleVisibleInit("curriculum'.$usercur->curid.'script", "curriculum'.$usercur->curid.'button", "' . $buttonLabel . '", "Hide", "Show", "curriculum'.$usercur->curid.'");</script></div>'. $usercur->name . ' (' . $usercur->idnumber . ')');
                    echo '<div id="curriculum' . $usercur->curid ."\" {$this->div_attrs} class=\"yui-skin-sam" . $extraclass . '">';
                    $table = new currentclasstable($classes, $this->url);
                    echo "<div id=\"$usercur->id\"></div>";
                    $table->print_yui_table($usercur->id);
                } else {
                    $buttonLabel2 = ($usercur->curid == $showcurid) ? get_string('hide') : get_string('show');
                    $extraclass2 = ($usercur->curid == $showcurid) ? '' : ' hide';
                    echo $OUTPUT->heading('<div class="clearfix"></div><div class="headermenu"><script id="curriculum'.$usercur->curid.'script" type="text/javascript">toggleVisibleInit("curriculum'.$usercur->curid.'script", "curriculum'.$usercur->curid.'button", "' . $buttonLabel2 . '", "Hide", "Show", "curriculum'.$usercur->curid.'");</script></div>'. $usercur->name . ' (' . $usercur->idnumber . ')');
                    echo '<div id="curriculum' . $usercur->curid ."\" {$this->div_attrs} class=\"yui-skin-sam" . $extraclass2 . '">';
                    echo '<p>' . get_string('nocoursesinthiscurriculum', 'elis_program') . '</p>';
                }
                echo '</div>';

            }
        } else {
            echo $OUTPUT->heading(get_string('notassignedtocurricula', 'elis_program'));
        }

        // Print out a table for classes not belonging to any curriculum
        if ($noncurcnt) {
            $labelshow = get_string('show');
            $labelhide = get_string('hide');
            echo $OUTPUT->heading('<div class="clearfix"></div><div class="headermenu"><script id="noncurrscript" type="text/javascript">toggleVisibleInit("noncurrscript", "noncurrbutton", "' . $buttonLabel . '", "'.$labelhide.'", "'.$labelshow.'", "noncurr");</script></div>'. get_string('othercourses', 'elis_program'));

            echo "<div id=\"noncurr\" {$this->div_attrs} class=\"yui-skin-sam" . $extraclass . '">';

            echo '<div id="noncurrtable"></div>';

            $table = new currentclasstable($noncurclasses, $this->url);
            $table->print_yui_table("noncurrtable");
            echo '</div>';
        } else {
            // Display nothing if we don't have any non-curriculum classes
        }

        // Print out a table for classes we instruct
        if ($instrcnt) {
            echo $OUTPUT->heading('<div class="clearfix"></div><div class="headermenu"><script id="instrscript" type="text/javascript">toggleVisibleInit("instrscript", "instrbutton", "' . $buttonLabel . '", "Hide", "Show", "instr");</script></div>'. get_string('instructedcourses', 'elis_program'));

            echo "<div id=\"instr\" {$this->div_attrs} class=\"yui-skin-sam" . $extraclass . '">';

            echo '<div id="instrtable"></div>';

            $table = new instructortable($instrclasses, $this->url);
            $classpage = new pmclasspage();
            //$table->decorators['classname'] = new recordlinkformatter($classpage,'id'); // ***TBD***
            $table->print_yui_table("instrtable");
            echo '</div>';
        } else {
            // Display nothing if we don't instruct any classes
        }

        echo '<br/>';
        echo $OUTPUT->box(get_string('lp_class_instructions', 'elis_program'),
                          'generalbox lp_instructions');

    }

    /**
     * curriculum overview menu
     *
     * @uses $OUTPUT
     * @uses $PAGE
     * @uses $USER
     */
    function display_available() { // action_available()
        global $OUTPUT, $PAGE, $USER;

    /// This is for a Moodle user, so get the Curriculum user id.
        $cuserid = cm_get_crlmuserid($USER->id);

        $usercurs = curriculumstudent::get_curricula($cuserid);

        if(count($usercurs) > elis::$config->elis_program->catalog_collapse_count) {
            $buttonLabel = get_string('show');
            $extraclass = ' hide';
        }
        else {
            $buttonLabel = get_string('hide');
            $extraclass = '';
        }

        // Needed for the hide buttons
        $this->include_js();

        // Process this user's curricula in turn, outputting the courses within each.
        if ($usercurs) {
            foreach ($usercurs as $usercur) {
                echo $OUTPUT->heading('<div class="clearfix"></div><div class="headermenu"><script id="curriculum'.$usercur->curid.'script" type="text/javascript">toggleVisibleInit("curriculum'.$usercur->curid.'script", "curriculum'.$usercur->curid.'button", "' . $buttonLabel . '", "Hide", "Show", "curriculum'.$usercur->curid.'");</script></div>'. $usercur->name . ' (' . $usercur->idnumber . ')');

                echo '<div id="curriculum'.$usercur->curid ."\" {$this->div_attrs} " .'" class="yui-skin-sam' . $extraclass . '">';
                if ($courses = user::get_user_course_curriculum($cuserid, $usercur->curid)) {

                    echo "<div id=\"$usercur->id\"></div>";

                    $table = new availablecoursetable($courses);
                    $table->print_yui_table($usercur->id);
                } else {
                    echo '<p>' . get_string('nocoursesinthiscurriculum', 'elis_program') . '</p>';
                }

                echo '</div>';
            }
        } else {
            echo $OUTPUT->heading(get_string('nocoursesinthiscurriculum', 'elis_program'));
        }

        echo '<br/>';
        echo $OUTPUT->box(get_string('lp_curriculum_instructions', 'elis_program'),
                          'generalbox lp_instructions');

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

class yui_table extends display_table {
    private $display_date;

    public function __construct($items, $columns, moodle_url $base_url=null) {
        parent::__construct($items, $columns, $base_url);
        $this->display_date = new display_date_item(get_string('pm_date_format', 'elis_program'));
    }

    public function get_date_item_display($column, $item) {
        return $this->display_date->display($column, $item);
    }

    /**
     * Returns a JSON representation of the table data.
     * @return string
     */
    function get_json() {
        $arr = array();
        foreach($this->table->data as $row) {
            $arr_row = array();
            $i = 0;
            foreach(array_keys($this->columns) as $key) {
                $arr_row[$key] = $row[$i++];
            }
            $arr[] = $arr_row;
        }
        return json_encode($arr);
        // TBD: or just return json_encode($this->table->data); ???
    }

    /**
     * Returns the columns that should be expected in the JSON data for the table.
     * Basically exists to support YUI.
     * @return string
     */
    function get_json_schema() {
        $arr = array();

        foreach(array_keys($this->columns) as $key) {
            $field = array('key' => $key);
            if(!empty($this->yui_parsers[$key])) {
                $field['parser'] = $this->yui_parsers[$key];
            }
            if(!empty($this->yui_sorters[$key])) {
                $field['sortOptions'] = array('sortFunction' => $this->yui_sorters[$key]);
            }
            $arr[] = $field;
        }
        return json_encode(array('fields' => $arr));
    }

    /**
     * Returns the javascript for defining the columns of a YUI DataTable representation of the table data.
     * @return string
     */
    function get_yui_columns() {
        $s = '[';
        foreach($this->columns as $column_id => $val) {
            $column_label = $val['header'];
            $s .= "{key:\"$column_id\", sortable:true, label:\"$column_label\", resizeable:true";
            if(!empty($this->yui_formatters[$column_id])) {
                $s .= ', formatter:' . $this->yui_formatters[$column_id];
            }
            $s .= "},\n";
        }

        //  remove the last comma, so that IE doesn't barf on us
        $s = rtrim($s, ",\n");
        $s .= ']';
        return $s;
    }

    /**
     * Prints the code for a YUI DataTable containing this table's data.
     * @return unknown_type
     */
    function print_yui_table($tablename) {
        $this->build_table();
?>

<script type="text/javascript">
YUI().use("yui2-datasource", "yui2-datatable", "yui2-dom", "yui2-element", "yui2-event", "yui2-utilities", function(Y) {
    var YAHOO = Y.YUI2;
    YAHOO.util.Event.addListener(window, "load", function() {
        YAHOO.example.Basic = function() {
            var myColumnDefs = <?php echo $this->get_yui_columns(); ?>;
            var myData = <?php echo $this->get_json(); ?>;
            var myDataSource = new YAHOO.util.DataSource(myData);
            myDataSource.responseType = YAHOO.util.DataSource.TYPE_JSARRAY;
            myDataSource.responseSchema = <?php echo $this->get_json_schema(); ?>;
            var myDataTable = new YAHOO.widget.DataTable("<?php echo $tablename; ?>", myColumnDefs, myDataSource);

            return {
                oDS: myDataSource,
                oDT: myDataTable
            };
        }();
    });
});
</script>

<?php
    }

}

if (!defined('ENVTABLE')) {
    define('ENVTABLE', 'crlm_environment');
}

class waitlisttable extends yui_table {
    public function __construct(&$items) {
        global $USER, $CFG;
        $this->cuserid = cm_get_crlmuserid($USER->id);

        $columns = array(
            'idnumber'    => array('header' => get_string('course_idnumber', 'elis_program')), // 'Course ID',
            'name'        => array('header' => get_string('course',          'elis_program')),
            'clsid'       => array('header' => get_string('class',           'elis_program')),
            'startdate'   => array('header' => get_string('class_startdate', 'elis_program')),
            'enddate'     => array('header' => get_string('class_enddate',   'elis_program')),
//            'timeofday'   => array('header' => get_string('timeofday',     'elis_program')),
            'instructor'  => array('header' => get_string('instructor',      'elis_program')),
            // 'environment' => array('header' => get_string('environment',     'elis_program')),
            'position'    => array('header' => get_string('position',        'elis_program')),
            'maxstudents' => array('header' => get_string('class_limit',     'elis_program')),
            'management'  => array('header' => '')
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

        $pageurl = new moodle_url($CFG->wwwroot .'/elis/program/index.php', array('s' => 'crscat'));
        parent::__construct($items, $columns, $pageurl);
        //$this->table->width = '80%'; // TBD
    }

    function is_sortable_default() {
        return false;
    }

    public function get_item_display_management($column, $item) {
        global $CFG, $OUTPUT;

        $retval = '<div align="center">';
        $formaction = $CFG->wwwroot .'/elis/program/index.php?s=crscat';
            // $formaction = new moodle_url(null, array('s' => 'crscat', 'action' => 'waitlist', 'id' => $item->wlid));
        $retval .= "<form action=\"{$formaction}\" method=\"post\">";
        $hidden = new moodle_url(null, array('s' => 'crscat', 'action' => 'delwaitlist', 'id' => $item->wlid));
        foreach ($hidden->params() as $key => $val) {
            $val = s($val);
            $retval .= "<input type=\"hidden\" name=\"{$key}\" value=\"{$val}\" />\n";
        }
        $retval .= '<input type="image" alt="'. get_string('delete', 'elis_program') .
                   '" title="'. get_string('delete', 'elis_program') .'" src="'.
                   $OUTPUT->pix_url('delete', 'elis_program') .'" /> ';
        $retval .= '</form>';
        $retval .= '</div>';

        return $retval;
    }

    // This method is NOT being used, and doesn't add dates - see formatters above
    function get_item_display_timeofday($column, $item) {
        $show_starttime = isset($item->starttimehour) && $item->starttimehour < 25 &&
                          isset($item->starttimeminute) && $item->starttimeminute < 61;
        $show_endtime = isset($item->endtimehour) && $item->endtimehour < 25 &&
                        isset($item->endtimeminute) && $item->endtimeminute < 61;
        if (!$show_starttime && !$show_endtime) {
            return get_string('course_catalog_time_na', 'elis_program');
        }

        $times = array();
        if ($show_starttime) {
            $times[] = $item->starttimehour;
            $times[] = $item->starttimeminute;
        } else {
            $times[] = 0;
            $times[] = 0;
        }

        if ($show_endtime) {
            $times[] = $item->endtimehour;
            $times[] = $item->endtimeminute;
        } else {
            $times[] = 0;
            $times[] = 0;
        }

        return $times;
    }

    function get_item_display_instructor($column, $item) {
        if ($instructors = instructor::get_instructors($item->id)) {
            $ins = array();

            foreach ($instructors as $instructor) {
                $ins[] = fullname($instructor);
            }

            if (!empty($ins)) {
                return implode('<br />', $ins);
            }
        }
        return get_string('course_catalog_time_na', 'elis_program');
    }

    function get_item_display_environment($column, $item) {
        global $DB;

        if (!empty($item->environmentid)) {
            return $DB->get_field(ENVTABLE, 'name', 'id', $item->environmentid);
        }
        return get_string('course_catalog_time_na', 'elis_program');
    }
}

class currentclasstable extends yui_table {
    function __construct(&$items, $pageurl) {
        global $USER;
        $this->cuserid = cm_get_crlmuserid($USER->id);

        $columns = array(
            'courseid'    => array('header' => get_string('course_idnumber', 'elis_program')), // 'Course ID',
            'coursename'  => array('header' => get_string('course',          'elis_program')),
            'classname'   => array('header' => get_string('class',           'elis_program')),
            'startdate'   => array('header' => get_string('class_startdate', 'elis_program')),
            'enddate'     => array('header' => get_string('class_enddate',   'elis_program')),
            'timeofday'   => array('header' => get_string('timeofday',       'elis_program')),
            'instructor'  => array('header' => get_string('instructor',      'elis_program'))
            // ,
            // 'environment' => array('header' => get_string('environment',     'elis_program'))
        );

        $this->yui_formatters = array(
            'timeofday' => 'cmFormatTimeRange',
            'startdate' => 'cmFormatDate',
            'enddate' => 'cmFormatDate',
        );

        $this->yui_parsers = array(
            'startdate' => 'date',
            'enddate' => 'date',
        );

        $this->yui_sorters = array(
            'timeofday' => 'cmSortTimeRange',
        );

        parent::__construct($items, $columns, $pageurl);
        //$this->table->width = '80%'; // TBD
    }

    function is_sortable_default() {
        return true;
    }

    function get_class($item) {
        // ELIS-3455: class id may change! cannot just use: $this->current_class
        if (!empty($item->classid)) {
            if (empty($this->current_class) || $this->current_class->id != $item->classid) {
                $this->current_class = new pmclass($item->classid);
            }
        } else {
            $this->current_class = false;
        }
        return $this->current_class;
    }

    function get_item_display_courseid($column, $item) {
        global $DB;
        return $DB->get_field(course::TABLE, 'idnumber', array('id' => $item->courseid));
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
                $mdlcrs . '">' . get_string('moodlecourse', 'elis_program') . '</a>';
        }
        return $classid;
    }

    function get_item_display_startdate($column, $item) {
        return $this->get_date_item_display('startdate',$this->current_class);
    }

    function get_item_display_enddate($column, $item) {
        return $this->get_date_item_display('enddate',$this->current_class);
    }

    /**
     * Converts a 24-hour-mode hour number to a combination of a
     * 12-hour-mode hour and either 'am' or 'pm'
     *
     * @param   int    $hour  An hour number from 0-23
     * @return  array         An array where the first element is the 12-hour-mode
     *                        hour and the second is either 'am' or 'pm'
     */
    private function format_hour($hour) {
        //determine if am or pm
        $ampm = $hour >= 12 ? 'pm' : 'am';

        //convert hour number
        if ($hour > 12) {
            $hour -= 12;
        } else if ($hour == 0) {
            $hour = 12;
        }

        //return both pieces of data
        return array($hour, $ampm);
    }

    function get_item_display_timeofday($column, $item) {
        if (($classdata = $this->get_class($item))) {
            //determine if at least one of the start time hour or minute is set to a valid value
            $show_starttime = isset($classdata->starttimehour) && $classdata->starttimehour < 25 &&
                              isset($classdata->starttimeminute) && $classdata->starttimeminute < 61;
            //determine if at least one of the end time hour or minute is set to a valid value
            $show_endtime =  isset($classdata->endtimehour) && $classdata->endtimehour < 25 &&
                             isset($classdata->endtimeminute) && $classdata->endtimeminute < 61;

            if ($show_starttime && $show_endtime) {
                //have valid times for both start and end time
                $starthour = $classdata->starttimehour;
                $startampm = '';
                $endhour = $classdata->endtimehour;
                $endampm = '';

                //perform the 24 to 12-hour conversion if necessary
                if (elis::$config->elis_program->time_format_12h) {
                    //calculate start hour and am/pm in 12-hour format
                    list($starthour, $startampm) = $this->format_hour($starthour);

                    //calculate end hour and am/pm in 12-hour format
                    list($endhour, $endampm) = $this->format_hour($endhour);
                }

                //return all the necessary data, which is:
                //start hour, start minute, start am/pm (or empty string if in 24-hour format),
                //end hour, end minute, end am/pm (or empty string if in 24-hour format)
                return array($starthour, $classdata->starttimeminute, $startampm,
                             $endhour, $classdata->endtimeminute, $endampm);
            }
        }
        //default n/a string
        return get_string('course_catalog_time_na', 'elis_program');
    }

    function get_item_display_instructor($column, $item) {
        if ($this->get_class($item)) {
            if ($instructors = instructor::get_instructors($this->current_class->id)) {
                $ins = array();

                foreach ($instructors as $instructor) {
                    $ins[] = fullname($instructor);
                }

                if (!empty($ins)) {
                    return implode('<br />', $ins);
                }
            }
        }
        return get_string('course_catalog_time_na', 'elis_program');
    }

    function get_item_display_environment($column, $item) {
        global $DB;
        if ($this->get_class($item)) {
            if (!empty($this->current_class->environmentid)) {
                return $DB->get_field(ENVTABLE, 'name', array('id' => $this->current_class->environmentid));
            }
        }
        return get_string('course_catalog_time_na', 'elis_program');
    }
}

class instructortable extends currentclasstable {
    function get_class($item) {
        if (!isset($this->current_class) || !isset($this->current_class->id) || $this->current_class->id != $item->id) {
            $this->current_class = new pmclass($item->id);
        }
        return $this->current_class;
    }
}

class availablecoursetable extends yui_table {
    function __construct(&$items) {
        global $USER;
        $this->cuserid = cm_get_crlmuserid($USER->id);

        $columns = array(
            'coursename'  => array('header' => get_string('course_name', 'elis_program')),
            'courseid'    => array('header' => get_string('course_idnumber', 'elis_program')),
            'classname'   => array('header' => '') // action/status info
        );
        parent::__construct($items, $columns);
        //$this->table->width = '80%';
    }

    function is_sortable_default() {
        return false;
    }

    function get_item_display_courseid($column, $item) {
        global $DB;
        return $DB->get_field(course::TABLE, 'idnumber', array('id' => $item->courseid));
    }

    function get_item_display_coursename($column, $item) {
        return $item->coursename;
    }

    function get_item_display_classname($column, $item) {
        if(isset($item->completionid)) {
            if($item->completionid == STUSTATUS_NOTCOMPLETE) {
                return get_string('onenroledlist', 'elis_program');
            } elseif($item->completionid == STUSTATUS_PASSED) {
                return get_string('onpassed', 'elis_program');
            } elseif($item->completionid == STUSTATUS_FAILED) {
                return get_string('onfailed', 'elis_program');
            }
        } elseif (!empty($item->prereqcount)) {
            return get_string('unsatisfiedprereqs', 'elis_program');
        } elseif (empty($item->classcount)) {
            return get_string('noclassavail', 'elis_program');
        } elseif(!empty($item->waiting)) {
            return get_string('onwaitlist', 'elis_program');
        } else {
            return get_string('noclassyet', 'elis_program') .' - <a href="'.
                "index.php?s=crscat&amp;section=curr&amp;crsid={$item->courseid}" .
                '&amp;action=add">'. get_string('chooseclass', 'elis_program') .
                '</a>';
        }
    }
}

class addclasstable extends yui_table {
    function __construct(&$items) {
        $columns = array(
            'options'      => array('header' => ''),
            'idnumber'     => array('header' => get_string('class_idnumber',  'elis_program')),
            'startdate'    => array('header' => get_string('class_startdate', 'elis_program')),
            'enddate'      => array('header' => get_string('class_enddate',   'elis_program')),
            'timeofday'    => array('header' => get_string('timeofday',       'elis_program')),
            'instructor'   => array('header' => get_string('instructor',      'elis_program')),
            // 'environment'  => array('header' => get_string('environment',     'elis_program')),
            'waitlistsize' => array('header' => get_string('waitlist_size',   'elis_program')),
            'classsize'    => array('header' => get_string('class_size',      'elis_program'))
        );

        parent::__construct($items, $columns);
        //unset($this->table->width);
    }

    function print_table() {
        echo $this;
        print('<div class="note">'. get_string('if_class_full', 'elis_program')
              .'</div>');
    }

    function is_sortable_default() {
        return false;
    }

    function get_item_display_waitlistsize($column, $class) {
        require_once(elispm::lib('data/waitlist.class.php'));
        return waitlist::count_records($class->id);
    }

    function get_item_display_classsize($column, $class) {
        $retval = get_string('course_catalog_time_na', 'elis_program');

        if(!empty($class->maxstudents)) {
            $students = student::count_enroled($class->id);
            $retval = $students . '/' . $class->maxstudents;
        }

        return $retval;
    }

    function get_item_display_options($column, $class) {
        $classobj = new pmclass($class);
        if(!$classobj->is_enrollable()) {
            return get_string('notenrollable', 'enrol');
        }

        if(student::count_enroled($class->id) < $class->maxstudents || empty($class->maxstudents)) {
            $action = 'savenew';
        } else {
            $action = 'confirmwaitlist';
        }


        return '<a href="index.php?s=crscat&amp;section=curr&amp;clsid='.
           "{$class->id}&amp;action={$action}\">". get_string('choose') .'</a>';
    }

    function get_item_display_startdate($column, $class) {
        return $this->get_date_item_display($column, $class);
    }

    function get_item_display_enddate($column, $class) {
        return $this->get_date_item_display($column, $class);
    }

    // TODO: fix time-of-day display
    function get_item_display_timeofday($column, $class) {
        //determine if at least one of the start time hour or minute is set to a valid value
        $show_starttime = isset($class->starttimehour) && $class->starttimehour < 25 &&
                          isset($class->starttimeminute) && $class->starttimeminute < 61;
        //determine if at least one of the end time hour or minute is set to a valid value
        $show_endtime =  isset($class->endtimehour) && $class->endtimehour < 25 &&
                         isset($class->endtimeminute) && $class->endtimeminute < 61;

        //for now, we are only showing the entry if both a start time and end time are
        //enabled
        if ($show_starttime && $show_endtime) {

            if (elis::$config->elis_program->time_format_12h) {
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
        }
        return get_string('course_catalog_time_na', 'elis_program');
    }

    function get_item_display_instructor($column, $class) {
        if ($instructors = instructor::get_instructors($class->id)) {
            $ins = array();

            foreach ($instructors as $instructor) {
                $ins[] = fullname($instructor);
            }

            return implode('<br />', $ins);
        }
        return get_string('course_catalog_time_na', 'elis_program');
    }

    function get_item_display_environment($column, $class) {
        return !empty($class->envname)
               ? $class->envname
               : get_string('course_catalog_time_na', 'elis_program');
    }
}

