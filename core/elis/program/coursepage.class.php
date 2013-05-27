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

require_once elis::lib('data/data_filter.class.php');
require_once elispm::lib('data/course.class.php');
require_once elispm::lib('data/coursetemplate.class.php');
require_once elispm::lib('managementpage.class.php');
require_once elispm::lib('contexts.php');
require_once elispm::file('curriculumcoursepage.class.php');
require_once elispm::file('form/courseform.class.php');
require_once elispm::file('pmclasspage.class.php');
require_once elispm::file('rolepage.class.php');
require_once elispm::file('resultspage.class.php');

class coursepage extends managementpage {
    var $data_class = 'course';
    var $form_class = 'cmCourseForm';

    var $view_columns = array('name', 'code');

    var $pagename = 'crs';
    var $section = 'curr';

    static $contexts = array();

    public static function get_contexts($capability) {
        if (!isset(coursepage::$contexts[$capability])) {
            global $USER;
            coursepage::$contexts[$capability] = get_contexts_by_capability_for_user('course', $capability, $USER->id);
        }
        return coursepage::$contexts[$capability];
    }

    /**
     * Check the cached capabilities for the current user.
     */
    public static function check_cached($capability, $id) {
        if (isset(coursepage::$contexts[$capability])) {
            // we've already cached which contexts the user has delete
            // capabilities in
            $contexts = coursepage::$contexts[$capability];
            return $contexts->context_allowed($id, 'course');
        }
        return null;
    }

    /**
     * Check if the user has the given capability for the requested curriculum
     */
    public function _has_capability($capability, $id = null) {
        if (empty($id)) {
            $id = (isset($this) && method_exists($this, 'required_param'))
                  ? $this->required_param('id', PARAM_INT)
                  : required_param('id', PARAM_INT);
        }
        // course contexts are different -- we rely on the cache because curricula
        // require special logic
        coursepage::get_contexts($capability);
        $cached = coursepage::check_cached($capability, $id);
        if ($cached !== null) {
            return $cached;
        }

        return has_capability($capability, $this->context);
    }

    public function _get_page_params() {
        return parent::_get_page_params();
    }

    public function __construct(array $params=null) {
        $this->tabs = array(
        array('tab_id' => 'view', 'page' => get_class($this), 'params' => array('action' => 'view'), 'name' => get_string('detail', 'elis_program'), 'showtab' => true),
        array('tab_id' => 'edit', 'page' => get_class($this), 'params' => array('action' => 'edit'), 'name' => get_string('edit', 'elis_program'), 'showtab' => true, 'showbutton' => true, 'image' => 'edit'),

        //allow users to view the classes associated with this course
        array('tab_id' => 'pmclasspage', 'page' => 'pmclasspage', 'params' => array('action' => 'default'), 'name' => get_string('course_classes', 'elis_program'), 'showtab' => true, 'showbutton' => true, 'image' => 'class'),
        array('tab_id' => 'elem', 'page' => get_class($this), 'params' => array('action' => 'lelem'), 'name' => get_string('completion_elements', 'elis_program'), 'showtab' => true, 'showbutton' => true, 'image' => 'grades'),
        array('tab_id' => 'coursecurriculumpage', 'page' => 'coursecurriculumpage', 'name' => get_string('course_curricula', 'elis_program'), 'showtab' => true, 'showbutton' => true, 'image' => 'curriculum'),
        array('tab_id' => 'course_rolepage', 'page' => 'course_rolepage', 'name' => get_string('roles', 'role'), 'showtab' => true, 'showbutton' => false, 'image' => 'tag'),
        array('tab_id' => 'course_enginepage', 'page' => 'course_enginepage', 'name' => get_string('results_engine', 'elis_program'), 'showtab' => true, 'showbutton' => true, 'image' => 'calculator'),
        array('tab_id' => 'course_enginestatuspage', 'page' => 'course_enginestatuspage', 'name' => get_string('status_report', 'elis_program'), 'showtab' => false, 'showbutton' => false),

        array('tab_id' => 'delete', 'page' => get_class($this), 'params' => array('action' => 'delete'), 'name' => get_string('delete_label', 'elis_program'), 'showbutton' => true, 'image' => 'delete'),
        );

        parent::__construct($params);
    }

    function can_do_view() {
        return $this->_has_capability('elis/program:course_view');
    }

    function can_do_edit() {
        return $this->_has_capability('elis/program:course_edit');
    }

    function can_do_delete() {
        return $this->_has_capability('elis/program:course_delete');
    }

    function can_do_add() {
        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('elis/program:course_create', $context);
    }

    function can_do_default() {
        $contexts = coursepage::get_contexts('elis/program:course_view');
        return !$contexts->is_empty();
    }

    function display_default() {
        global $DB, $USER;

        // Get parameters
        $sort         = optional_param('sort', 'name', PARAM_ALPHA);
        $dir          = optional_param('dir', 'ASC', PARAM_ALPHA);

        $page         = optional_param('page', 0, PARAM_INT);
        $perpage      = optional_param('perpage', 30, PARAM_INT);        // how many per page

        $namesearch   = trim(optional_param('search', '', PARAM_TEXT));
        $alpha        = optional_param('alpha', '', PARAM_ALPHA);

        // Define columns
        $columns = array(
            'name'      => array('header' => get_string('course_name','elis_program')),
            'code'      => array('header' => get_string('course_code','elis_program')),
            'version'   => array('header' => get_string('course_version','elis_program')),
            'curricula' => array('header' => get_string('course_curricula','elis_program'),
                                 'display_function' => 'count_curricula'),
        );

        // Set sorting
        if ($dir !== 'DESC') {
            $dir = 'ASC';
        }
        if (isset($columns[$sort])) {
            $columns[$sort]['sortable'] = $dir;
        }

        // TBD: get context set ...
        $contextset = coursepage::get_contexts('elis/program:course_view');
        //$contextset = pm_context_set::for_user_with_capability('course','elis/program:course_view', $USER->id);

        // Get list of courses
        $items    = course_get_listing($sort, $dir, $page*$perpage, $perpage, $namesearch, $alpha, $contextset);
        $numitems = course_count_records($namesearch, $alpha, $contextset);

        // Cache the context capabilities
        coursepage::get_contexts('elis/program:course_edit');
        coursepage::get_contexts('elis/program:course_delete');

        $this->print_list_view($items, $numitems, $columns, $filter=null, $alphaflag=true, $searchflag=true);
    }

    function display_lelem() {
        $id    = required_param('id', PARAM_INT);

        $crsid = required_param('id', PARAM_INT);

        $this->print_tabs('elem', array('id' => $id));
        echo $this->get_completion_page($crsid);
    }

    function display_eelem() {
        $this->display_celem();
    }

    function display_celem() {
        $id = required_param('id', PARAM_INT);
        $elemid = optional_param('elemid', 0, PARAM_INT);

        $this->print_tabs('elem', array('id' => $id));
        echo $this->get_completion_edit_form($id, $elemid);
    }

    function display_selem() {
        $id = required_param('id', PARAM_INT);

        $crs = new course($id);
        $crs->seturl(null, array('s'=>'crs', 'section'=>'curr', 'action'=>'selem'));
        $form = $crs->create_completion_form($this->optional_param('elemid', 0, PARAM_INT));
        if (!$form->is_cancelled() && !$form->is_validated()) {
            $this->print_tabs('elem', array('id' => $id));
            $form->display();
            return;
        }
        if (!$form->is_cancelled()) {
            $elemrecord = new Object();
            $elemrecord->id                = optional_param('elemid', 0, PARAM_INT);
            $elemrecord->idnumber          = optional_param('idnumber', '', PARAM_CLEAN);
            $elemrecord->name              = optional_param('name', '', PARAM_CLEAN);
            $elemrecord->description       = optional_param('description', '', PARAM_TEXT);
            $elemrecord->completion_grade  = optional_param('completion_grade', 0, PARAM_INT);
            $elemrecord->required          = optional_param('required', 0, PARAM_INT);
            $crs->save_completion_element($elemrecord);
        }

        $this->print_tabs('elem', array('id' => $id));
        echo $this->get_completion_page($id);
    }

    function display_delem() {
        $elemid = optional_param('elemid', 0, PARAM_INT);
        return $this->get_delete_element_form($elemid);
    }

    function display_confirmdelem() {
        $id = required_param('id', PARAM_INT);
        $confirm = required_param('confirm', PARAM_TEXT);

        $elemid = optional_param('elemid', 0, PARAM_INT);
        $crs = new course($id);
        if (md5($elemid) != $confirm) {
            echo cm_error('Invalid confirmation code!');
        } else if (!$crs->delete_completion_element($elemid)){
            echo cm_error('Completion element not deleted.');
        } else {
            echo cm_error('Completion element deleted.');
        }

        $this->print_tabs('elem', array('id' => $id));
        echo $this->get_completion_page($id);
    }

    function get_completion_page($crsid) {
        global $CFG, $OUTPUT;

        $output = '';

        $crs = new course($crsid);
        $table = new stdClass();

        $elements = $crs->get_completion_elements();

        if (!empty($elements) && $elements->valid() === true) {
            $columns = array(
                'idnumber'          => array('header'=>get_string('completion_idnumber','elis_program')),
                'name'              => array('header'=>get_string('completion_name','elis_program')),
                'description'       => array('header'=>get_string('completion_description','elis_program')),
                'completion_grade'  => array('header'=>get_string('completion_grade','elis_program')),
                'required'          => array('header'=>get_string('required','elis_program')),
                'actions'           => array('header' =>'',
                                             'display_function' => 'htmltab_display_function',
                                             'sortable' => false),
                );

            foreach ($columns as $column => $cdesc) {
                $columndir = "ASC";
                $columnicon = $columndir == "ASC" ? "down":"up";
                $columnicon = " <img src=\"".$OUTPUT->pix_url('t/'.$columnicon)."\" alt=\"\" />";

                $$column = $cdesc;
                $table->head[]  = $$column;
                $table->align[] = "left";
                $table->wrap[]  = false;
            }
            $table->head[]  = "";
            $table->align[] = "center";
            $table->wrap[]  = true;

            $newarr = array();

            $editstr = get_string('edit');
            $editiconattrs = array('class' => 'elisicon-edit elisicon', 'alt' => $editstr, 'title' => $editstr);
            $delstr = get_string('delete');
            $deliconattrs = array('class' => 'elisicon-remove elisicon', 'alt' => $delstr, 'title' => $delstr);

            foreach ($elements as $element) {
                $editurl = 'index.php?s=crs&amp;section=curr&amp;action=eelem&amp;id='.$crs->id.'&amp;elemid='.$element->id;
                $editbutton = html_writer::link($editurl, '', $editiconattrs);
                $delurl = 'index.php?s=crs&amp;section=curr&amp;action=delem&amp;id='.$crs->id.'&amp;elemid='.$element->id;
                $deletebutton = html_writer::link($delurl, '', $deliconattrs);

                $newobj = new stdClass;
                foreach ($columns as $column => $cdesc) {
                    if ($column == 'required') {
                        $newobj->required = empty($element->required) ? get_string('no') : get_string('yes');
                    } else if ($column == 'actions') {
                        $newobj->actions = $editbutton.' '.$deletebutton;
                    } else {
                        $newobj->$column = $element->$column;
                    }
                }
                $newarr[] = $newobj;
            }

            $table = new display_table($newarr, $columns);
            $output .= $table->get_html();
        } else {
            $output .= '<div align="center">' . get_string('no_completion_elements', 'elis_program') . '</div>';
        }
        unset($elements);

        $output .= '<br clear="all" />' . "\n";
        $output .= '<div align="center">';
        $options = array('s' => 'crs', 'section' => 'curr', 'action' => 'celem', 'id' => $crs->id);
        $addelement = get_string('add_element', 'elis_program');
        $button = new single_button(new moodle_url('index.php', $options), $addelement, 'get', array(
            'disabled' => false,
            'title'    => $addelement,
            'id'       => ''
        ));
        echo $OUTPUT->render($button);
        $output .= '</div>';

        return $output;
    }

    function build_navbar_view($who = null, $id_param = 'id', $extra_params = array()) {
        if (!$who) {
            $who = $this;
        }
        $this->build_navbar_default($who);

        if ($id_param == 'id' || !($id = $who->optional_param($id_param, 0, PARAM_INT))) {
            $id = $who->required_param('id', PARAM_INT);
        }

        $obj = $this->get_new_data_object($id);
        $obj->load();
        $params = array_merge(array('action' => 'view', 'id' => $id), $extra_params);
        $url = $this->get_new_page($params, true)->url;
        $who->navbar->add(htmlspecialchars($obj), $url);
    }

    public function build_navbar_lelem($who = null) {
        if (!$who) {
            $who = $this;
        }
        $id = required_param('id', PARAM_INT);

        $page = $this->get_new_page(array('action' => 'lelem', 'id' => $id));
        $this->build_navbar_view($who);

        $who->navbar->add(get_string('completion_elements', 'elis_program'),
                          $page->url);
    }

    public function build_navbar_delem() {
        $page = $this->get_new_page(array('action' => 'delem'));
        $this->build_navbar_lelem($this);
        $this->navbar->add(get_string('deleting_completion_element', 'elis_program'),
                           $page->url);
    }

    public function build_navbar_celem() {
        $page = $this->get_new_page(array('action' => 'celem'));
        $this->build_navbar_lelem($this);
        $this->navbar->add(get_string('adding_completion_element', 'elis_program'),
                           $page->url);
    }

    public function build_navbar_eelem() {
        $page = $this->get_new_page(array('action' => 'eelem'));
        $this->build_navbar_lelem($this);
        $this->navbar->add(get_string('editing_completion_element', 'elis_program'),
                           $page->url);
    }

    function get_default_object_for_add() {
        $obj = (object) course::get_default();

        return $obj;
    }

    /**
     * Hook that gets called after a CM entity is added through this page
     *
     * @param  object  $cm_entity  The CM entity added
     * @uses $DB
     * @uses $USER
     */
    function after_cm_entity_add($cm_entity) {
        global $DB, $USER;

        //make sure a valid role is set
        if(!empty(elis::$config->elis_program->default_course_role_id) && $DB->record_exists('role', array('id' => elis::$config->elis_program->default_course_role_id))) {

            //get the context instance for capability checking
            $context_instance = context_elis_course::instance($cm_entity->id);

            //assign the appropriate role if the user does not have the edit capability
            if (!has_capability('elis/program:course_edit', $context_instance)) {
                role_assign(elis::$config->elis_program->default_course_role_id, $USER->id, $context_instance->id);
            }
        }
    }

    /**
     * Returns the edit course completion form.
     *
     * @return string HTML for the form.
     */
    function get_completion_edit_form($crsid, $elemid=0) {
        $output = '';

        $crs = new course($crsid);

        $crs->seturl(null, array('s'=>'crs', 'section'=>'curr', 'action'=>'selem'));
        $output .= $crs->edit_completion_form_html($elemid);

        return $output;
    }

    function get_delete_element_form($elemid) {
        global $DB;

        $elemrecord = $DB->get_record(coursecompletion::TABLE, array('id'=>$elemid));

        if (!($elemrecord)) {
            print_error('Undefined completion element.');
        }

        $crs = new course($elemrecord->courseid);

        $url = 'index.php';
        $message = get_string('confirm_delete_completion', 'elis_program', $elemrecord->idnumber);
        $optionsyes = array('s' => 'crs', 'section' => 'curr', 'action' => 'confirmdelem',
                            'id' => $crs->id, 'elemid' => $elemid, 'confirm' => md5($elemid));
        $optionsno = array('s' => 'crs', 'section' => 'curr', 'id' => $crs->id, 'action' => 'lelem');

        echo cm_delete_form($url, $message, $optionsyes, $optionsno);
    }

    /**
     * Specifies a unique shortname for the entity represented by
     * a page of this type, transforming the supplied value if necessary
     *
     * @param   string       $parent_path  Path of all parent elements, or the empty string
     *                                     if none
     * @param   string       $name         Initial name provided for the element
     *
     * @return  string|NULL                A valid name to identify the item with, or NULL if
     *                                     not applicable
     */
    static function get_entity_name($parent_path, $name) {
        $parts = explode('_', $name);

        //try to find the entity type and id, and combine them
        if (count($parts) == 2) {
            if ($parts[0] == 'course') {
                return $parts[0] . '-' . $parts[1];
            }
        }
        return NULL;
    }

}

function count_curricula($column, $item) {
    return curriculumcourse_count_curriculum_records($item->id);
}
