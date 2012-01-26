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

require_once (CURMAN_DIRLOCATION . '/lib/managementpage.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/course.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/coursetemplate.class.php');
require_once (CURMAN_DIRLOCATION . '/form/courseform.class.php');
require_once (CURMAN_DIRLOCATION . '/curriculumcoursepage.class.php');
require_once (CURMAN_DIRLOCATION . '/rolepage.class.php');

class coursepage extends managementpage {
    var $data_class = 'course';
    var $form_class = 'cmCourseForm';

    var $view_columns = array('name', 'code');

    var $pagename = 'crs';
    var $section = 'curr';

    static $contexts = array();

    static function get_contexts($capability) {
        if (!isset(coursepage::$contexts[$capability])) {
            global $USER;
            coursepage::$contexts[$capability] = get_contexts_by_capability_for_user('course', $capability, $USER->id);
        }
        return coursepage::$contexts[$capability];
    }

    /**
     * Check the cached capabilities for the current user.
     */
    static function check_cached($capability, $id) {
        global $CURMAN;
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
    function _has_capability($capability, $id = null) {
        $id = $id ? $id : $this->required_param('id', PARAM_INT);
        // course contexts are different -- we rely on the cache because curricula
        // require special logic
        coursepage::get_contexts($capability);
        $cached = coursepage::check_cached($capability, $id);
        if ($cached !== null) {
            return $cached;
        }
        $context = get_context_instance(context_level_base::get_custom_context_level('course', 'block_curr_admin'), $id);
        return has_capability($capability, $context);
    }

    function __construct($params=false) {
        $this->tabs = array(
        array('tab_id' => 'view', 'page' => get_class($this), 'params' => array('action' => 'view'), 'name' => get_string('detail', 'block_curr_admin'), 'showtab' => true),
        array('tab_id' => 'edit', 'page' => get_class($this), 'params' => array('action' => 'edit'), 'name' => get_string('edit', 'block_curr_admin'), 'showtab' => true, 'showbutton' => true, 'image' => 'edit.gif'),

        //allow users to view the classes associated with this course
        array('tab_id' => 'cmclasspage', 'page' => 'cmclasspage', 'params' => array('action' => 'default'), 'name' => get_string('course_classes', 'block_curr_admin'), 'showtab' => true, 'showbutton' => true, 'image' => 'class.gif'),
        array('tab_id' => 'elem', 'page' => get_class($this), 'params' => array('action' => 'lelem'), 'name' => get_string('completion_elements', 'block_curr_admin'), 'showtab' => true, 'showbutton' => true, 'image' => 'grades.gif'),
        array('tab_id' => 'coursecurriculumpage', 'page' => 'coursecurriculumpage', 'name' => get_string('course_curricula', 'block_curr_admin'), 'showtab' => true, 'showbutton' => true, 'image' => 'curriculum.gif'),
        array('tab_id' => 'crstaginstancepage', 'page' => 'crstaginstancepage', 'name' => get_string('tags', 'block_curr_admin'), 'showtab' => true, 'showbutton' => true, 'image' => 'tag.gif'),
        array('tab_id' => 'course_rolepage', 'page' => 'course_rolepage', 'name' => get_string('roles', 'role'), 'showtab' => true, 'showbutton' => false, 'image' => 'tag.gif'),

        array('tab_id' => 'delete', 'page' => get_class($this), 'params' => array('action' => 'delete'), 'name' => get_string('delete_label', 'block_curr_admin'), 'showbutton' => true, 'image' => 'delete.gif'),
        );

        parent::__construct($params);
    }

    function can_do_view() {
        return $this->_has_capability('block/curr_admin:course:view');
    }

    function can_do_edit() {
        return $this->_has_capability('block/curr_admin:course:edit');
    }

    function can_do_delete() {
        return $this->_has_capability('block/curr_admin:course:delete');
    }

    function can_do_confirm() {
        return $this->can_do_delete();
    }

    function can_do_add() {
        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('block/curr_admin:course:create', $context);
    }

    function can_do_default() {
        $contexts = coursepage::get_contexts('block/curr_admin:course:view');
        return !$contexts->is_empty();
    }

    function action_default() {
        // Get parameters
        $sort         = optional_param('sort', 'name', PARAM_ALPHA);
        $dir          = optional_param('dir', 'ASC', PARAM_ALPHA);

        $page         = optional_param('page', 0, PARAM_INT);
        $perpage      = optional_param('perpage', 30, PARAM_INT);        // how many per page

        $namesearch   = trim(optional_param('search', '', PARAM_TEXT));
        $alpha        = optional_param('alpha', '', PARAM_ALPHA);

        // Define columns
        $columns = array(
            'name'              => get_string('course_name','block_curr_admin'),
            'code'              => get_string('course_code','block_curr_admin'),
        //'idnumber'          => 'ID Number',
        //'documents'         => 'Documents',
        //'lengthdescription' => 'Duration (text)',
        //'length'            => 'Duration (seconds)',
        //'credits'           => 'Credits',
            'envname'           => get_string('environment','block_curr_admin'),
        //'cost'              => 'Cost',
            'version'           => get_string('course_version','block_curr_admin'),
            'curricula'         => get_string('course_curricula','block_curr_admin'),
        );

        $items    = course_get_listing($sort, $dir, $page*$perpage, $perpage, $namesearch, $alpha, coursepage::get_contexts('block/curr_admin:course:view'));
        $numitems = course_count_records($namesearch, $alpha, coursepage::get_contexts('block/curr_admin:course:view'));

        coursepage::get_contexts('block/curr_admin:course:edit');
        coursepage::get_contexts('block/curr_admin:course:delete');

        $this->print_list_view($items, $numitems, $columns, $filter=null, $alphaflag=true, $searchflag=true);
    }

    function action_lelem() {
        $id       = required_param('id', PARAM_INT);

        $crsid = required_param('id', PARAM_INT);

        $this->print_tabs('elem', array('id' => $id));
        echo $this->get_completion_page($crsid);
    }

    function action_eelem() {
        $this->action_celem();
    }

    function action_celem() {
        $id = required_param('id', PARAM_INT);
        $elemid = cm_get_param('elemid', 0);

        $this->print_tabs('elem', array('id' => $id));
        echo $this->get_completion_edit_form($id, $elemid);
    }

    function action_selem() {
        $id = required_param('id', PARAM_INT);

        $crs = new course($id);
        $crs->seturl(null, array('s'=>'crs', 'section'=>'curr', 'action'=>'selem'));
        $form = $crs->create_completion_form($this->optional_param('elemid', 0, PARAM_INT));
        if (!$form->is_cancelled()) {
            $elemrecord = new Object();
            $elemrecord->id                = cm_get_param('elemid', 0);
            $elemrecord->idnumber          = cm_get_param('idnumber', '');
            $elemrecord->name              = cm_get_param('name', '');
            $elemrecord->description       = cm_get_param('description', '');
            $elemrecord->completion_grade  = cm_get_param('completion_grade', 0);
            $elemrecord->required          = cm_get_param('required', 0);
            $crs->save_completion_element($elemrecord);
        }

        $this->print_tabs('elem', array('id' => $id));
        echo $this->get_completion_page($id);
    }

    function action_delem() {
        $elemid = cm_get_param('elemid', 0);
        return $this->get_delete_element_form($elemid);
    }

    function action_confirmdelem() {
        $id = required_param('id', PARAM_INT);
        $confirm = required_param('confirm', PARAM_TEXT);


        $elemid = cm_get_param('elemid', 0);
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
        global $CFG;

        $output = '';

        $crs = new course($crsid);
        $table = new stdClass();

        $elements = $crs->get_completion_elements();

        if ($elements) {
            $columns = array(
                'idnumber'          => get_string('completion_idnumber','block_curr_admin'),
                'name'              => get_string('completion_name','block_curr_admin'),
                'description'       => get_string('completion_description','block_curr_admin'),
                'completion_grade'  => get_string('completion_grade','block_curr_admin'),
                'required'          => get_string('required','block_curr_admin')
                );

                foreach ($columns as $column => $cdesc) {
                    $columndir = "ASC";
                    $columnicon = $columndir == "ASC" ? "down":"up";
                    $columnicon = " <img src=\"$CFG->pixpath/t/$columnicon.gif\" alt=\"\" />";

                    $$column = $cdesc;
                    $table->head[]  = $$column;
                    $table->align[] = "left";
                    $table->wrap[]  = false;
                }
                $table->head[]  = "";
                $table->align[] = "center";
                $table->wrap[]  = true;

                foreach ($elements as $element) {

                    $deletebutton = '<a href="index.php?s=crs&amp;section=curr&amp;action=delem&amp;id='.$crs->id.
                                '&amp;elemid='.$element->id.'">'.
                                '<img src="pix/delete.gif" alt="Delete" title="Delete" /></a>';
                    $editbutton   = '<a href="index.php?s=crs&amp;section=curr&amp;action=eelem&amp;id='.$crs->id.
                                '&amp;elemid='.$element->id.'">'.
                                '<img src="pix/edit.gif" alt="Edit" title="Edit" /></a>';

                    $newarr = array();
                    foreach ($columns as $column => $cdesc) {
                        if ($column == 'required') {
                            $newarr[] = empty($element->required) ? get_string('no') :  get_string('yes');
                        } else {
                            $newarr[] = $element->$column;
                        }
                    }
                    $newarr[] = $editbutton . ' ' . $deletebutton;
                    $table->data[] = $newarr;
                }
                $output .= print_table($table, true);

        } else {
            $output .= '<div align="center">' . get_string('no_completion_elements', 'block_curr_admin') . '</div>';
        }

        $output .= '<br clear="all" />' . "\n";
        $output .= '<div align="center">';
        $options = array('s' => 'crs', 'section' => 'curr', 'action' => 'celem', 'id' => $crs->id);
        $output .= print_single_button('index.php', $options,
                                       'Add Element', 'get', '_self', true, 'Add New Element');
        $output .= '</div>';

        return $output;
    }

    public function get_navigation_lelem() {
        $id = required_param('id', PARAM_INT);

        $page = $this->get_new_page(array('action' => 'lelem', 'id' => $id));
        $navigation = $this->get_navigation_default();
        $navigation[] = array('name' => get_string('completion_elements', 'block_curr_admin'),
                              'link' => $page->get_url());

        return $navigation;
    }

    public function get_navigation_delem() {
        $page = $this->get_new_page(array('action' => 'delem'));
        $navigation = $this->get_navigation_lelem();
        $navigation[] = array('name' => get_string('deleting_completion_element', 'block_curr_admin'),
                              'link' => $page->get_url());

        return $navigation;
    }

    public function get_navigation_celem() {
        $page = $this->get_new_page(array('action' => 'celem'));
        $navigation = $this->get_navigation_lelem();
        $navigation[] = array('name' => get_string('adding_completion_element', 'block_curr_admin'),
                              'link' => $page->get_url());

        return $navigation;
    }

    public function get_navigation_eelem() {
        $page = $this->get_new_page(array('action' => 'eelem'));
        $navigation = $this->get_navigation_lelem();
        $navigation[] = array('name' => get_string('editing_completion_element', 'block_curr_admin'),
                              'link' => $page->get_url());

        return $navigation;
    }

    function get_default_object_for_add() {
        // get site-wide default values
        global $CURMAN;

        $obj = (object) course::get_default();

        return $obj;
    }

    /**
     * Hook that gets called after a CM entity is added through this page
     * (Note: this function should only use the id field from the supplied cm entity
     *  as the rest of the data is not guaranteed to be there)
     *
     * @param  object  $cm_entity  The CM entity added
     */
    function after_cm_entity_add($cm_entity) {
        global $USER, $CURMAN;

        //make sure a valid role is set
        if(!empty($CURMAN->config->default_course_role_id) && record_exists('role', 'id', $CURMAN->config->default_course_role_id)) {

            //get the context instance for capability checking
            $context_level = context_level_base::get_custom_context_level('course', 'block_curr_admin');
            $context_instance = get_context_instance($context_level, $cm_entity->id);

            //assign the appropriate role if the user does not have the edit capability
            if(!has_capability('block/curr_admin:course:edit', $context_instance)) {
                role_assign($CURMAN->config->default_course_role_id, $USER->id, 0, $context_instance->id);
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
        global $CURMAN;

        $elemrecord = $CURMAN->db->get_record(CRSCOMPTABLE, 'id', $elemid);

        if (!($elemrecord)) {
            error ('Undefined completion element.');
        }

        $crs = new course($elemrecord->courseid);

        $url = 'index.php';
        $message = get_string('confirm_delete_completion', 'block_curr_admin', $elemrecord->idnumber);
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

?>
