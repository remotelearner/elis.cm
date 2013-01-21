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

require_once(dirname(__FILE__).'/../../../../config.php');
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once elis::lib('data/data_object_with_custom_fields.class.php');
require_once elispm::lib('data/course.class.php');
require_once elispm::lib('data/curriculum.class.php');
require_once elispm::lib('data/pmclass.class.php');
require_once elispm::lib('data/student.class.php');
require_once elispm::file('form/curriculumcourseform.class.php');

class curriculumcourse extends elis_data_object {
    const TABLE = 'crlm_curriculum_course';

    var $verbose_name = 'curriculumcourse';
    var $_dbloaded = TRUE;

    static $associations = array(
        'coursecorequisite' => array(
            'class' => 'coursecorequisite',
            'foreignidfield' => 'curriculumcourseid'
        ),
        'courseprerequisite' => array(
            'class' => 'courseprerequisite',
            'foreignidfield' => 'curriculumcourseid'
        ),
        'curriculum' => array(
            'class' => 'curriculum',
            'idfield' => 'curriculumid'
        ),
        'course' => array(
            'class' => 'course',
            'idfield' => 'courseid'
        ),
    );

    protected $_dbfield_curriculumid;
    protected $_dbfield_courseid;
    protected $_dbfield_required;
    protected $_dbfield_frequency;
    protected $_dbfield_timeperiod;
    protected $_dbfield_position;
    protected $_dbfield_timecreated;
    protected $_dbfield_timemodified;

    static $delete_is_complex = true;

    private $form_url = null;  //moodle_url object

    // Allowable values for the timeperiod property.
    var $timeperiod_values = array(
        'year'  => 'Years',  // Default
        'month' => 'Months',
        'week'  => 'Weeks',
        'day'   => 'Days'
    );

    // STRING - Styles to use for edit form.
    var $_editstyle = '
    .curriculumcourseeditform input,
    .curriculumcourseeditform textarea,
    .curriculumcourseeditform select {
        margin: 0;
        display: block;
    }
    ';

    function delete() {
        $this->delete_all_track_classes();

        parent::delete();
    }

    /////////////////////////////////////////////////////////////////////
    //                                                                 //
    //  FORM FUNCTIONS:                                                //
    //                                                                 //
    /////////////////////////////////////////////////////////////////////

    /**
     * Return the formslib form to edit a specific curriculum course.
     *
     * @param $formid string A suffix to put on all 'id' and index for all 'name' attributes.
     *                       This should be unique if being used more than once in a form.
     * @param $extraclass string Any extra class information to add to the output.
     * @param $rows int height of textareas
     * @param $cols int width of textareas
     *
     * @return string The form HTML, without the form.
     */
    public function create_edit_form($formid='', $extraclass='', $rows=2, $cols=40) {
        $config_data = array();
        $config_data['formid'] = $formid;
        $config_data['rows'] = $rows;
        $config_data['cols'] = $cols;

        $config_data['curricula_avail'] = $this->get_curricula_avail();
        $config_data['courses_avail'] = $this->get_courses_avail();

        $config_data_set = array('id', 'curriculumid', 'courseid', 'timeperiod_values');

        foreach($config_data_set as $d) {
            if(!empty($this->{$d})) {
                $config_data[$d] = $this->{$d};
            }
        }

        return new coursecurriculumform($this->form_url, $config_data);
    }

    /**
     * Return the HTML to edit a specific curriculum course.
     *
     * @param $formid string A suffix to put on all 'id' and index for all 'name' attributes.
     *                       This should be unique if being used more than once in a form.
     * @param $extraclass string Any extra class information to add to the output.
     * @param $rows int height of textareas
     * @param $cols int width of textareas
     *
     * @return string The form HTML, without the form.
     */
    public function edit_form_html($formid='', $extraclass='', $rows='2', $cols='40') {
        $output = '';

        $cmCurCourseForm = $this->create_edit_form($formid, $extraclass, $rows, $cols);

        $data_set = array('curriculumid', 'courseid', 'required', 'frequency', 'timeperiod', 'position');

        if (!empty($this->curriculumid)) {
            if (!isset($this->curriculum)) {
                $this->curriculum = new curriculum($this->curriculumid);
            }

            $data['curriculumname'] = $this->curriculum->name;
        }

        if (!empty($this->courseid)) {
            if (!isset($this->course)) {
                $this->course = new course($this->courseid);
            }

            $data['coursename'] = $this->course->name;
        }

        foreach($data_set as $d) {
            if(isset($this->{$d})){
                $data[$d] = $this->{$d};
            }
        }

        $cmCurCourseForm->set_data($data);

        ob_start();
        $cmCurCourseForm->display();
        $output .= ob_get_contents();
        ob_end_clean();

        return $output;
    }

    public function setUrl($url = null, $action = array()) {
        if(!($url instanceof moodle_url)) {
            $url = new moodle_url($url, $action);
        }

        $this->form_url = $url;
    }

    public function set_from_data($data) {
        $this->_load_data_from_record($data, true);
    }

    /**
     * creates the prerequisite form
     *
     * @param INT $formid
     * @param STR $extraclass
     * @param INT $rows
     * @param INT $cols
     * @return <type>
     */
    public function create_prerequisite_form($formid='', $extraclass='', $rows=2, $cols=40) {
        require_once(elispm::file('coursepage.class.php'));

        $config_data = array();
        $config_data['formid'] = $formid;
        $config_data['rows'] = $rows;
        $config_data['cols'] = $cols;

        $courseListing = course_get_listing();
        unset($courseListing[$this->courseid]);

        if (!empty($courseListing)) {
            $existingPrerequisites = array();

            foreach ($courseListing as $crsid => $crs) {
                if ($this->is_prerequisite($crsid)) {
                    $existingPrerequisites[$crsid] = '(' . $crs->idnumber . ')' . $crs->name;
                }
            }

            $config_data['existingPrerequisites'] = $existingPrerequisites;
        }

        $contexts = coursepage::get_contexts('elis/program:course_view');
        $courseListing = course_get_listing('crs.name', 'ASC', 0, 0, '', '', $contexts);
        unset($courseListing[$this->courseid]);

        if (!empty($courseListing)) {
            $availablePrerequisites = array();

            foreach ($courseListing as $crsid => $crs) {
                if (!$this->is_prerequisite($crsid) && !$this->is_corequisite($crsid)) {
                    $availablePrerequisites[$crsid] = '(' . $crs->idnumber . ')' . $crs->name;
                }
            }

            $config_data['availablePrerequisites'] = $availablePrerequisites;
        }

        //curriculumid, courseid, id
        $config_data['association_id'] = $this->id;
        $config_data['courseid'] = $this->courseid;
        $config_data['curriculumid'] = $this->curriculumid;

        return new prerequisiteform($this->form_url, $config_data);
    }

    /**
     * Return the HTML to edit the prerequisites for a specific course.
     *
     * @param $formid string A suffix to put on all 'id' and index for all 'name' attributes.
     *                       This should be unique if being used more than once in a form.
     * @param $extraclass string Any extra class information to add to the output.
     *
     * @return string The form HTML, without the form.
     */
    function prerequisite_form_html($formid = '', $extraclass = '', $rows = 2, $cols = 40) {
        $cmPrereqForm = $this->create_prerequisite_form($formid, $extraclass, $rows, $cols);

        ob_start();
        $cmPrereqForm->display();
        $output = ob_get_contents();
        ob_end_clean();

        return $output;
    }

    public function create_corequisite_form($formid = '', $extraclass = '', $rows = '2', $cols = '40') {
        require_once(elispm::file('coursepage.class.php'));

        $config_data = array();
        $config_data['formid'] = $formid;
        $config_data['rows'] = $rows;
        $config_data['cols'] = $cols;

        $courseListing = course_get_listing();
        unset($courseListing[$this->courseid]);

        if (!empty($courseListing)) {
            $existingCorequisites = array();

            foreach ($courseListing as $crsid => $crs) {
                if ($this->is_corequisite($crsid)) {
                    $existingCorequisites[$crsid] = '(' . $crs->idnumber . ')' . $crs->name;
                }
            }

            $config_data['existingCorequisites'] = $existingCorequisites;
        }

        $contexts = coursepage::get_contexts('elis/program:course_view');
        $courseListing = course_get_listing('crs.name', 'ASC', 0, 0, '', '', $contexts);
        unset($courseListing[$this->courseid]);

        if (!empty($courseListing)) {
            $availableCorequisites = array();

            foreach ($courseListing as $crsid => $crs) {
                if (!$this->is_prerequisite($crsid) && !$this->is_corequisite($crsid)) {
                    $availableCorequisites[$crsid] = '(' . $crs->idnumber . ')' . $crs->name;
                }
            }

            $config_data['availableCorequisites'] = $availableCorequisites;
        }

        $config_data['association_id'] = $this->id;
        $config_data['courseid'] = $this->courseid;
        $config_data['curriculumid'] = $this->curriculumid;

        return new corequisiteform($this->form_url, $config_data);
    }

    /**
     * Return the HTML to edit the corequisites for a specific course.
     *
     * @param $formid string A suffix to put on all 'id' and index for all 'name' attributes.
     *                       This should be unique if being used more than once in a form.
     * @param $extraclass string Any extra class information to add to the output.
     * @param $rows int number of rows for any text areas in form
     * @param $cols in number of columns for any text areas in form
     * @return string The form HTML, without the form.
     */
    function corequisite_form_html($formid = '', $extraclass = '', $rows = '2', $cols = '40') {
        $cmCoreqForm = $this->create_corequisite_form($formid, $extraclass, $rows, $cols);

        ob_start();
        $cmCoreqForm->display();
        $output = ob_get_contents();
        ob_end_clean();

        return $output;
    }

    /////////////////////////////////////////////////////////////////////
    //                                                                 //
    //  DATA FUNCTIONS:                                                //
    //                                                                 //
    /////////////////////////////////////////////////////////////////////

    function delete_all_prerequisites() {
        if (!$this->id || !$this->_dbloaded) {
            return false;
        }

        return $this->_db->delete_records(courseprerequisite::TABLE, array('curriculumcourseid'=>$this->id));
    }

    function delete_all_corequisites() {
        if (!$this->id || !$this->_dbloaded) {
            return false;
        }

        return $this->_db->delete_records(coursecorequisite::TABLE, array('curriculumcourseid'=>$this->id));
    }

    function delete_all_track_classes() {
        if (!$this->courseid || !$this->curriculumid || !$this->_dbloaded) {
            return false;
        }

        $tracks = $this->_db->get_recordset(track::TABLE, array('curid'=>$this->curriculumid));
        foreach ($tracks as $track_id => $track_obj) {
            $result = $this->_db->delete_records(trackassignment::TABLE, array('trackid'=>$track_obj->id, 'courseid'=>$this->courseid));
        }
        unset($tracks);

        return true;
    }

    /**
     * Add a new prerequisite for a course.
     *
     * @param int $cid The course ID to add as a prerequisite.
     * @param bool True on success, False otherwise.
     */
    function add_prerequisite($cid, $add_to_curriculum = false) {
        if (empty($this->id)) {
            return false;
        }

        if ($this->is_prerequisite($cid) || $this->is_corequisite($cid)) {
            return false;
        }

        $cp = new stdClass;
        $cp->curriculumcourseid = $this->id;
        $cp->courseid           = $cid;
        $cp->id = $this->_db->insert_record(courseprerequisite::TABLE, $cp);

        $result = !empty($cp->id);

        if ($result && $add_to_curriculum) {
            $data = new object();
            $data->curriculumid = $this->curriculumid;
            $data->courseid = $cid;
            $data->timeperiod = 'year';

            $currprereq = new curriculumcourse($data);
            if(!$currprereq->is_recorded()){
                $currprereq->save();
            }
        }

        return $result;
    }

    /**
     * Delete a course pre-requisite.
     *
     * @param int $cid The course ID to delete as a prerequisite.
     * @return bool True on success, False otherwise.
     */
    function del_prerequisite($cid) {
        if (!$this->id || !$this->_dbloaded) {
            return false;
        }

        return $this->_db->delete_records(courseprerequisite::TABLE, array('curriculumcourseid'=>$this->id, 'courseid'=>$cid));
    }

    /**
     * Determine if a given course ID is a prerequisite for the current course.
     *
     * @param int $cid The course ID to add as a prerequisite.
     * @param bool True on success, False otherwise.
     */
    function is_prerequisite($cid) {
        if (!$this->id || !$this->_dbloaded) {
            return false;
        }

        return $this->_db->record_exists(courseprerequisite::TABLE, array('curriculumcourseid'=>$this->id, 'courseid'=>$cid));
    }

    /**
     * Get a list of the course IDs that are a prerequisite for the current corse.
     *
     * @param none
     * @return array The list of course IDs.
     */
    function get_prerequisites() {
        $cids = array();

        if (!$this->id || !$this->_dbloaded) {
            return false;
        }

        $courses = $this->_db->get_recordset(courseprerequisite::TABLE, array('curriculumcourseid'=>$this->id));
        foreach ($courses as $course) {
            $cids[] = $course->courseid;
        }
        unset($courses);

        return $cids;
    }

    /**
     * Determine whether the user has satisfied all the prerequisites for this
     * course.
     */
    function prerequisites_satisfied($userid) {
        // see if there are any prerequisites that are NOT completed
        $sql = 'SELECT p.courseid
                  FROM {'.curriculumcourse::TABLE.'} cc
                       -- find the prerequisite courses
            INNER JOIN {'.courseprerequisite::TABLE.'} p ON cc.id = p.curriculumcourseid
       LEFT OUTER JOIN ( -- find the courses the user has completed
                         -- when we require that courseid is NULL, we get the
                         -- courses the user has NOT completed
                         SELECT cls.courseid
                                -- find possible classes for the prereq courses
                           FROM {'.pmclass::TABLE.'} cls
                                -- find classes that the user has completed
                     INNER JOIN {'.student::TABLE.'} e ON cls.id = e.classid
                          WHERE e.userid = ? AND e.completestatusid = 2
                       ) pcls ON  pcls.courseid = p.courseid
                 WHERE cc.curriculumid = ? AND cc.courseid = ? AND pcls.courseid IS NULL';

        // prereqs satisfied if there are no unsatisfied prereqs
        return !$this->_db->record_exists_sql($sql, array($userid, $this->curriculumid, $this->courseid));
    }

    /**
     * Add a new corequisite for a course.
     *
     * @param int $cid The course ID to add as a corequisite.
     * @param bool True on success, False otherwise.
     */
    function add_corequisite($cid, $add_to_curriculum = false) {
        if (empty($this->id)) {
            return false;
        }

        if ($this->is_prerequisite($cid) || $this->is_corequisite($cid)) {
            return false;
        }

        $cp = new stdClass;
        $cp->curriculumcourseid = $this->id;
        $cp->courseid           = $cid;
        $cp->id = $this->_db->insert_record(coursecorequisite::TABLE, $cp);

        $result = !empty($cp->id);

        if ($result && $add_to_curriculum) {
            $data = new object();
            $data->curriculumid = $this->curriculumid;
            $data->courseid = $cid;
            $data->timeperiod = 'year';

            $currprereq = new curriculumcourse($data);
            if(!$currprereq->is_recorded()){
                $currprereq->save();
            }
        }

        return $result;
    }

    /**
     * Delete a course pre-requisite.
     *
     * @param int $cid The course ID to delete as a corequisite.
     * @return bool True on success, False otherwise.
     */
    function del_corequisite($cid) {
        if (!$this->id || !$this->_dbloaded) {
            return false;
        }

        return $this->_db->delete_records(coursecorequisite::TABLE, array('curriculumcourseid'=>$this->id, 'courseid'=>$cid));
    }

    /**
     * Determine if a given course ID is a corequisite for the current course.
     *
     * @param int $cid The course ID to add as a corequisite.
     * @param bool True on success, False otherwise.
     */
    function is_corequisite($cid) {
        if (!$this->id || !$this->_dbloaded) {
            return false;
        }

        return $this->_db->record_exists(coursecorequisite::TABLE, array('curriculumcourseid'=>$this->id, 'courseid'=>$cid));
    }

    /**
     * Get a list of the course IDs that are a corequisite for the current corse.
     *
     * @param none
     * @return array The list of course IDs.
     */
    function get_corequisites() {
        $cids = array();

        if (!$this->id || !$this->_dbloaded) {
            return false;
        }

        $courses = $this->_db->get_recordset(coursecorequisite::TABLE, array('curriculumcourseid'=>$this->id));
        foreach ($courses as $course) {
            $cids[] = $course->courseid;
        }
        unset($courses);

        return $cids;
    }

    function is_recorded() {
        $retval = $this->_db->record_exists(curriculumcourse::TABLE, array('curriculumid'=>$this->curriculumid, 'courseid'=>$this->courseid));
        return $retval;
    }

    /**
     * Get a list of courses available to add to this curriculum in a format
     * that can be easily added to an HTML form.
     *
     * @param none
     * @return A list of course names, indexed by their ID values.
     */
    function get_courses_avail($filters=array()) {
        $params = array($this->curriculumid);

        $sql = 'SELECT crs.id, crs.name, crs.idnumber
                  FROM {'.course::TABLE.'} crs
             LEFT JOIN {'.curriculumcourse::TABLE.'} curcrs on curcrs.courseid = crs.id AND curcrs.curriculumid = ?
                 WHERE curcrs.id IS NULL ';
        if (isset($filters['contexts'])) {
            $filter_object = $filters['contexts']->get_filter('id', 'course');
            $filter_sql = $filter_object->get_sql(false, 'crs');
            if (isset($filter_sql['where'])) {
                $sql = $sql.' AND '.$filter_sql['where'].' ';
                $params = array_merge($params, $filter_sql['where_parameters']);
            }
        }
        $sql .= 'ORDER BY crs.name ASC';

        return $this->_db->get_records_sql($sql, $params);
    }

    function get_curricula_avail($filters=array()) {
        $params = array($this->courseid);

        $sql = 'SELECT cur.id, cur.name, cur.idnumber
                  FROM {'.curriculum::TABLE.'} cur
             LEFT JOIN {'.curriculumcourse::TABLE.'} curcrs ON curcrs.curriculumid = cur.id AND curcrs.courseid = ?
                 WHERE curcrs.id IS NULL ';
        if (isset($filters['contexts'])) {
            $filter_object = $filters['contexts']->get_filter('id', 'curriculum');
            $filter_sql = $filter_object->get_sql(false, 'cur');
            if (isset($filter_sql['where'])) {
                $sql = $sql.' AND '.$filter_sql['where'].' ';
                $params = array_merge($params, $filter_sql['where_parameters']);
            }
        }
        $sql = $sql . 'ORDER BY cur.name ASC';

        return $this->_db->get_records_sql($sql, $params);
    }

    /**
     * Returns true if course is required for the curriculum
     *
     * @return boolean true if course is required
     */
    function is_course_required() {
        $required = false;
        $result = $this->_db->get_field(curriculumcourse::TABLE, 'required',
                                        array('curriculumid'=>$this->curriculumid, 'courseid'=>$this->courseid));
        if ($result) {
            if (1 == $result) {
                $required = true;
            }
        }
        return $required;
    }

    static $validation_rules = array(
        array('validation_helper', 'is_unique_curriculumid_courseid')
    );

    public function save() {
        parent::save();

        events_trigger('crlm_curriculum_course_associated', $this);
    }

    function get_verbose_name() {
        return $this->verbose_name;
    }
}

/// Non-class supporting functions. (These may be able to replaced by a generic container/listing class)

/**
 * Gets a curriculum course listing with specific sort and other filters.
 *
 * @param int $curid The curriculum ID.
 * @param string $sort Field to sort on.
 * @param string $dir Direction of sort.
 * @param int $startrec Record number to start at.
 * @param int $perpage Number of records per page.
 * @param string $namesearch Search string for curriculum name.
 * @param string $descsearch Search string for curriculum description.
 * @param string $alpha Start initial of curriculum name filter.
 * @param array $extrafilters Additional filters to apply to the count
 * @return recordset Returned records.
 */
function curriculumcourse_get_listing($curid, $sort='position', $dir='ASC', $startrec=0, $perpage=0, $namesearch='', $alpha='',
                                      $extrafilters = array()) {
    global $DB;

    $select = 'SELECT curcrs.*, crs.name AS coursename ';
    $tables = 'FROM {'.curriculumcourse::TABLE.'} curcrs ';
    $join   = 'INNER JOIN {'.course::TABLE.'} crs ';
    $on     = 'ON curcrs.courseid = crs.id ';
    $where  = 'curcrs.curriculumid = ?';

    $params = array($curid);

    if (!empty($namesearch)) {
        $namesearch = trim($namesearch);
        $name_like = $DB->sql_like('crs.name', '?', FALSE);

        $where .= (!empty($where) ? ' AND ' : '') . "($name_like) ";
        $params[] = "%$namesearch%";
    }

    if ($alpha) {
        $name_like = $DB->sql_like('crs.name', '?', FALSE);
        $where .= (!empty($where) ? ' AND ' : '') . "($name_like) ";
        $params[] = "$alpha%";
    }

    if (!empty($extrafilters['contexts'])) {
        //apply a filter related to filtering on particular course contexts
        $filter_object = $extrafilters['contexts']->get_filter('id', 'course');
        $filter_sql = $filter_object->get_sql(false, 'crs');

        if (!empty($filter_sql)) {
            //user does not have access at the system context
            $where .= 'AND '.$filter_sql['where'];
            $params = array_merge($params, $filter_sql['where_parameters']);
        }
    }

    if (!empty($where)) {
        $where = 'WHERE '.$where.' ';
    }

    if ($sort) {
        $sort = 'ORDER BY '.$sort .' '. $dir.' ';
    }

    $sql = $select.$tables.$join.$on.$where.$sort;

    return $DB->get_recordset_sql($sql, $params, $startrec, $perpage);
}

/**
 * Gets the number of courses assigned to a particular curriculum
 *
 * @param   int     $curid         The id of the curriculum to obtain the listing for
 * @param   string  $namesearch    Search string for course name
 * @param   string  $alpha         Start initial of course name filter
 * @param   array   $extrafilters  Additional filters to apply to the count
 * @return  int                    The number of appropriate records
 */
function curriculumcourse_count_records($curid, $namesearch = '', $alpha = '', $extrafilters = array()) {
    global $DB;

    $select = '';

    $select = 'SELECT COUNT(curcrs.id) ';
    $tables = 'FROM {'.curriculumcourse::TABLE.'} curcrs ';
    $join   = 'INNER JOIN {'.course::TABLE.'} crs ';
    $on     = 'ON curcrs.courseid = crs.id ';
    $where  = 'curcrs.curriculumid = ?';

    $params = array($curid);

    if (!empty($namesearch)) {
        $namesearch = trim($namesearch);
        $name_like = $DB->sql_like('crs.name', '?', FALSE);

        $where .= (!empty($where) ? ' AND ' : '') . "($name_like) ";
        $params[] = "%$namesearch%";
    }

    if ($alpha) {
        $name_like = $DB->sql_like('crs.name', '?', FALSE);
        $where .= (!empty($where) ? ' AND ' : '') . "($name_like) ";
        $params[] = "$alpha%";
    }

    if (!empty($extrafilters['contexts'])) {
        //apply a filter related to filtering on particular course contexts
        $filter_object = $extrafilters['contexts']->get_filter('id', 'course');
        $filter_sql = $filter_object->get_sql(false, 'crs');

        if (!empty($filter_sql)) {
            //user does not have access at the system context
            $where .= 'AND '.$filter_sql['where'];
            $params = array_merge($params, $filter_sql['where_parameters']);
        }
    }

    if (!empty($where)) {
        $where = 'WHERE '.$where.' ';
    }

    $sql = $select . $tables . $join . $on . $where;

    return $DB->count_records_sql($sql, $params);
}

/**
 * Gets a curriculum course listing with specific sort and other filters.
 *
 * @param int $crsid The course ID.
 * @param string $sort Field to sort on.
 * @param string $dir Direction of sort.
 * @param int $startrec Record number to start at.
 * @param int $perpage Number of records per page.
 * @param string $namesearch Search string for curriculum name.
 * @param string $descsearch Search string for curriculum description.
 * @param string $alpha Start initial of curriculum name filter.
 * @return recordset Returned records.
 */
function curriculumcourse_get_curriculum_listing($crsid, $sort='position', $dir='ASC', $startrec=0, $perpage=0, $namesearch='',
                                                 $alpha='', $contexts = null) {
    global $DB;

    $select = 'SELECT curcrs.*, cur.name AS curriculumname ';
    $tables = 'FROM {'.curriculumcourse::TABLE.'} curcrs ';
    $join   = 'INNER JOIN {'.curriculum::TABLE.'} cur ';
    $on     = 'ON curcrs.curriculumid = cur.id ';
    $where  = 'curcrs.courseid = ?';

    $params = array($crsid);

    if (!empty($namesearch)) {
        $namesearch = trim($namesearch);
        $name_like = $DB->sql_like('cur.name', '?', FALSE);

        $where .= (!empty($where) ? ' AND ' : '') . "($name_like) ";
        $params[] = "%$namesearch%";
    }

    if ($alpha) {
        $name_like = $DB->sql_like('cur.name', '?', FALSE);
        $where .= (!empty($where) ? ' AND ' : '') . "($name_like) ";
        $params[] = "$alpha%";
    }

    if ($contexts !== null) {
        $filter_object = $contexts->get_filter('id', 'curriculum');
        $filter_sql = $filter_object->get_sql(false, 'cur');
        if (isset($filter_sql['where'])) {
            $where .= ' AND ' . $filter_sql['where'];
            $params = array_merge($params, $filter_sql['where_parameters']);
        }
    }

    if (!empty($where)) {
        $where = 'WHERE '.$where.' ';
    }

    if ($sort) {
        $sort = 'ORDER BY '.$sort .' '. $dir.' ';
    }

    $sql = $select.$tables.$join.$on.$where.$sort;

    return $DB->get_recordset_sql($sql, $params, $startrec, $perpage);
}

function curriculumcourse_count_curriculum_records($crsid, $namesearch = '', $alpha = '', $contexts = null) {
    global $DB;

    $select = 'SELECT COUNT(curcrs.id) ';
    $tables = 'FROM {'.curriculumcourse::TABLE.'} curcrs ';
    $join   = 'INNER JOIN {'.curriculum::TABLE.'} cur ';
    $on     = 'ON curcrs.curriculumid = cur.id ';
    $where  = 'curcrs.courseid = ?';

    $params = array($crsid);

    if (!empty($namesearch)) {
        $namesearch = trim($namesearch);
        $name_like = $DB->sql_like('cur.name', '?', FALSE);

        $where .= (!empty($where) ? ' AND ' : '') . "($name_like) ";
        $params[] = "%$namesearch%";
    }

    if ($alpha) {
        $name_like = $DB->sql_like('cur.name', '?', FALSE);
        $where .= (!empty($where) ? ' AND ' : '') . "($name_like) ";
        $params[] = "$alpha%";
    }

    if ($contexts !== null) {
        $filter_object = $contexts->get_filter('id', 'curriculum');
        $filter_sql = $filter_object->get_sql(false, 'cur');
        if (isset($filter_sql['where'])) {
            $where .= ' AND ' . $filter_sql['where'];
            $params = array_merge($params, $filter_sql['where_parameters']);
        }
    }

    if (!empty($where)) {
        $where = 'WHERE '.$where.' ';
    }

    $sql = $select.$tables.$join.$on.$where;

    return $DB->count_records_sql($sql, $params);
}

/**
 * Returns a list of records based on the course id
 *
 * @param int courseid Course id
 * @return recordset crlm_curriculum_course objects
 */
function curriculumcourse_get_list_by_course($courseid) {
    global $DB;
    return $DB->get_recordset(curriculumcourse::TABLE, array('courseid'=>$courseid));
}

/**
 * Returns a list of records base on the curriculum id
 *
 * @param int curriculumid Curriculum id
 * @return recordset crlm_curriculum_course objects
 */
function curriculumcourse_get_list_by_curr($curriculumid) {
    global $DB;
    return $DB->get_recordset(curriculumcourse::TABLE, array('curriculumid'=>$curriculumid));
}

class courseprerequisite extends elis_data_object {
    const TABLE = 'crlm_course_prerequisite';

    static $associations = array(
        'curriculumcourse' => array(
            'class' => 'curriculumcourse',
            'idfield' => 'curriculumcourseid'
        ),
        'course' => array(
            'class' => 'course',
            'idfield' => 'courseid'
        ),
    );

    protected $_dbfield_curriculumcourseid;
    protected $_dbfield_courseid;
}

class coursecorequisite extends elis_data_object {
    const TABLE = 'crlm_course_corequisite';

    static $associations = array(
        'curriculumcourse' => array(
            'class' => 'curriculumcourse',
            'idfield' => 'curriculumcourseid'
        ),
        'course' => array(
            'class' => 'course',
            'idfield' => 'courseid'
        ),
    );

    protected $_dbfield_curriculumcourseid;
    protected $_dbfield_courseid;
}
