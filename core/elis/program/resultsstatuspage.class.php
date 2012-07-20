<?php
/**
 * Common page class for role assignments
 *
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2010 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2008-2010 Remote Learner.net Inc http://www.remote-learner.net
 */

defined('MOODLE_INTERNAL') || die();

require_once elispm::lib('lib.php');
require_once elispm::lib('page.class.php');
require_once elispm::file('resultspage.class.php');
require_once($CFG->libdir .'/tablelib.php');

/**
 * New table class to extend table_sql
 *
 * We're extending the table so we can format class name and student name columns
 *
 * @author Remote Learner http://www.remote-learner.net
 */
class table_engine_status extends table_sql {

    public $elisurl;

    /**
     * Standard Constructor
     *
     * @param string $uniqueid A unique id for the table
     * @param string $url      The base url for the page
     */
    function __construct($uniqueid, $url) {
        $this->elisurl = $url;
        parent::__construct($uniqueid);
    }

    /**
     * Format the idnumber with a link to the class results
     *
     * @param object $row A row of data
     * @return string The formatted idnumber
     */
    function col_idnumber($row) {
        $idnumber = $row->idnumber;

        if (!$this->download && !empty($row->daterun)) {
            $params = array('s' => 'clsenginestatus', 'id' => $row->id);
            $url = new moodle_url($this->elisurl, $params);
            $idnumber = html_writer::link($url, $row->idnumber);
        }
        return $idnumber;
    }

    /**
     * Format the actions with each action on a separate line
     *
     * @param object $row A row of data
     * @return string The formatted actions
     */
    function col_actions($row) {
        $actions = $row->actions;

        if (!$this->download) {
            $rows = explode(',', $row->actions);
            $actions = implode("<br />\n", $rows);
        }
        return $actions;
    }

    /**
     * Format the run date column to be human readable
     *
     * @param object $row A row of data
     * @return string The formatted date
     */
    function col_daterun($row) {
        $date = '';

        if (! empty($row->daterun)) {
            if (is_numeric($row->daterun)) {
                $format = get_string('strftimedate');
                $date = userdate($row->daterun, $format);
            } else {
                $date = $row->daterun;
            }
        }
        return $date;
    }

    /**
    * Fullname is treated as a special columname in tablelib and should always
    * be treated the same as the fullname of a user.
    * @uses $this->useridfield if the userid field is not expected to be id
    * then you need to override $this->useridfield to point at the correct
    * field for the user id.
    *
    * ELIS version.
    *
    */
    function col_fullname($row) {
        global $COURSE, $CFG;

        if (!$this->download) {
            $params = array('s' => 'usr', 'id' => $row->{$this->useridfield}, 'action' => 'view');
            $url = new moodle_url($this->elisurl, $params);
            if ($COURSE->id != SITEID) {
                $url->param('course', $COURSE->id);
            }
            return html_writer::link($url, fullname($row));

        } else {
            return fullname($row);
        }
    }
}

/**
 * Abstract class to define engine status pages.
 *
 * @author Remote Learner http://www.remote-learner.net
 */
abstract class enginestatuspage extends enginepage {
    protected $headers = array();

    /**
     * Build the default navigation bar
     *
     * @see enginepage::build_navbar_default()
     */
    function build_navbar_default() {
        parent::build_navbar_default();

        $id = $this->required_param('id', PARAM_INT);
        $page = $this->get_new_page(array('id' => $id), true);
        $this->navbar->add(get_string('results_status_report', self::LANG_FILE), $page->url);
    }

    /**
     * Display the default page
     *
     * @uses $CFG;
     */
    function display_default() {
        global $CFG;

        $id   = required_param('id', PARAM_INT);

        $title = get_string('results_status_report', self::LANG_FILE);

        $params = array($id);

        $page = $this->get_new_page(array('id' => $id), true);

        $table = new table_engine_status($this->tableid, $this->_get_page_url());
        $table->define_columns($this->columns);
        $table->define_headers($this->headers);
        $table->define_baseurl($page->url);
        $table->set_sql($this->fields, $this->from, $this->where, $params);
        $table->set_count_sql($this->count, $params);
        $table->sortable(true, $this->sort);

        print('<div class="results enginestatus">');
        print_string('results_status_'. $this->type .'_title', self::LANG_FILE, $this->get_title_data());
        $table->out(50, false);
        print('</div>');
    }

    /**
     * Do the default action
     *
     * @see enginestatuspage::display_default
     */
    function do_default() {
        $this->display('default');
    }
}

/**
 * Engine status page for courses
 *
 * @author Tyler Bannister <tyler.bannister@remote-learner.net>
 */
class course_enginestatuspage extends enginestatuspage {
    public $pagename = 'crsenginestatus';
    public $type     = 'course';

    protected $tableid = 'results_engine_course_status';
    protected $columns = array('idnumber', 'datescheduled', 'daterun');
    protected $fields  = 'cc.id, cc.idnumber, MAX(crcl.datescheduled) as datescheduled, MAX(crcl.daterun) as daterun';
    protected $from    = '{crlm_class} cc LEFT JOIN {crlm_results_class_log} crcl ON crcl.classid=cc.id';
    protected $where   = 'cc.courseid=? GROUP BY cc.id, cc.idnumber';
    protected $count   = 'SELECT COUNT(1) FROM {crlm_class} WHERE courseid=?';
    protected $sort    = 'idnumber';

    /**
     * Constructor
     *
     * Sets up the language strings for table headers
     *
     * @param array $params Parameters for parent classes
     */
    function __construct($params = null) {
        $daterun       = get_string('results_date_run', self::LANG_FILE);
        $classinstance = get_string('results_class_instance', self::LANG_FILE);
        $datescheduled = get_string('results_date_scheduled', self::LANG_FILE);
        $this->headers = array($classinstance, $datescheduled, $daterun);

        parent::__construct($params);
    }

    /**
     * Get context
     *
     * @return object The context
     */
    protected function get_context() {
        if (!isset($this->context)) {
            $id = $this->required_param('id', PARAM_INT);

            $this->set_context(context_elis_course::instance($id));
        }
        return $this->context;
    }

    /**
     * Get the course id.
     *
     * @return int The course id
     */
    protected function get_course_id() {
        return $this->required_param('id', PARAM_INT);
    }

    /**
     * Get parent page object
     *
     * @return object An object of the same type as the parent page
     * @uses $CFG
     * @uses $CURMAN
     */
    protected function get_parent_page() {
        if (!isset($this->parent_page)) {
            global $CFG, $CURMAN;
            require_once elispm::file('coursepage.class.php');
            $id = $this->required_param('id', PARAM_INT);
            $this->parent_page = new coursepage(array('id' => $id, 'action' => 'view'));
        }
        return $this->parent_page;
    }

    /**
     * Get data object for title
     *
     * @return object An object of the same type as the parent page
     * @uses $DB
     */
    protected function get_title_data() {
        global $DB;

        $params =  array('id' => $this->required_param('id', PARAM_INT));
        $course = $DB->get_record('crlm_course', $params, 'id, name');
        return $course;
    }

    /**
     * Check if the user can do the default action
     *
     * @return bool True if the user has permission to use the default action
     */
    function can_do_default() {
        return has_capability('elis/program:course_edit', $this->get_context());
    }
}

/**
 * Engine page for classes
 *
 * Classes have an extra form field that courses don't have.
 *
 * @author Tyler Bannister <tyler.bannister@remote-learner.net>
 */
class class_enginestatuspage extends enginestatuspage {
    public $pagename = 'clsenginestatus';
    public $type     = 'class';


    protected $tableid = 'results_engine_class_status';
    protected $columns = array('fullname', 'actions', 'daterun');
    protected $fields  = 'cu.id, cu.firstname, cu.lastname, GROUP_CONCAT(crsl.action) as actions, MAX(crsl.daterun) as daterun';
    protected $from    = '{crlm_class_enrolment} cce JOIN {crlm_user} cu ON cu.id=cce.userid LEFT JOIN {crlm_results_class_log} crcl ON crcl.classid=cce.classid LEFT JOIN {crlm_results_student_log} crsl ON crsl.userid=cce.userid AND crsl.classlogid=crcl.id';
    protected $where   = 'cce.classid=? GROUP BY cu.id, cu.firstname, cu.lastname';
    protected $count   = 'SELECT COUNT(1) FROM {crlm_class_enrolment} WHERE classid=?';
    protected $sort    = 'lastname';

    /**
     * Constructor
     *
     * Sets up the language strings for table headers
     *
     * @param array $params Parameters for parent classes
     */
    function __construct($params = null) {
        $daterun = get_string('results_date_run', self::LANG_FILE);
        $student = get_string('defaultcoursestudent');
        $actions = get_string('results_actions_performed', self::LANG_FILE);
        $this->headers = array($student, $actions, $daterun);

        parent::__construct($params);
    }

    /**
     * Get context
     *
     * @return object The context
     */
    protected function get_context() {
        if (!isset($this->context)) {
            $id = $this->required_param('id', PARAM_INT);

            $this->set_context(context_elis_class::instance($id));
        }
        return $this->context;
    }

    /**
     * Get the course id.
     *
     * @return int The course id
     * @uses $DB
     */
    protected function get_course_id() {
        global $DB;

        $classid  = $this->required_param('id', PARAM_INT);
        $courseid = $DB->get_field('crlm_class', 'courseid', array('id' => $classid));
        return $courseid;
    }

    /**
     * Get parent page object
     *
     * @return object An object of the same type as the parent page
     * @uses $CFG
     * @uses $CURMAN
     */
    protected function get_parent_page() {
        if (!isset($this->parent_page)) {
            global $CFG, $CURMAN;
            require_once elispm::file('pmclasspage.class.php');
            $id = $this->required_param('id');
            $this->parent_page = new pmclasspage(array('id' => $id, 'action' => 'view'));
        }
        return $this->parent_page;
    }

    /**
     * Get data object for title
     *
     * @return object An object of the same type as the parent page
     * @uses $DB
     */
    protected function get_title_data() {
        global $DB;

        $sql = 'SELECT cls.id, cls.idnumber, crs.name'
             .' FROM {crlm_class} cls'
             .' JOIN {crlm_course} crs ON crs.id = cls.courseid'
             .' WHERE cls.id=?';

        $params = array('id' => $this->required_param('id', PARAM_INT));
        $class = $DB->get_record_sql($sql, $params);
        return $class;
    }
}
