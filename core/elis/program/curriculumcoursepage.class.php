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

require_once elispm::lib('associationpage.class.php');
require_once elispm::lib('data/curriculumcourse.class.php');
require_once elispm::lib('data/curriculumstudent.class.php');
require_once elispm::lib('contexts.php');
require_once elispm::file('curriculumpage.class.php');
require_once elispm::file('coursepage.class.php');
require_once elispm::file('form/curriculumcourseform.class.php');

class curriculumcoursebasepage extends associationpage {
    var $data_class = 'curriculumcourse';

    function __construct(array $params=null) {
        parent::__construct($params);

        $this->tabs = array(
        array('tab_id' => 'currcourse_edit', 'page' => get_class($this), 'params' => array('action' => 'edit'), 'name' => get_string('edit','elis_program'), 'showtab' => true, 'showbutton' => true, 'image' => 'edit'),

        array('tab_id' => 'prerequisites', 'page' => get_class($this), 'params' => array('action' => 'prereqedit'), 'name' => get_string('prerequisites','elis_program'), 'showbutton' => true, 'image' => 'prereq'),
        array('tab_id' => 'corequisites', 'page' => get_class($this), 'params' => array('action' => 'coreqedit'), 'name' => get_string('corequisites','elis_program'), 'showbutton' => true, 'image' => 'coreq'),

        array('tab_id' => 'delete', 'page' => get_class($this), 'params' => array('action' => 'delete'), 'name' => get_string('delete','elis_program'), 'showbutton' => true, 'image' => 'delete'),
        );
    }

    function create_table_object($items, $columns) {
        return new curriculumcourse_page_table($items, $columns, $this);
    }

    function can_do_add() {
        $curriculumid = $this->optional_param('curriculumid', 0, PARAM_INT);
        $courseid = $this->optional_param('courseid', 0, PARAM_INT);

        if ($curriculumid == 0 && $courseid == 0) {
            //just showing the page, so let the child page handle permissions
            return $this->can_do('default');
        }

        // the user must have 'block/curr_admin:associate' permissions on both ends
        return curriculumpage::_has_capability('elis/program:associate', $curriculumid)
            && coursepage::_has_capability('elis/program:associate', $courseid);
    }

    function can_do_edit() {
        // the user must have 'elis/program:associate' permissions on both
        // ends
        $association_id = $this->required_param('association_id', PARAM_INT);
        $record = new curriculumcourse($association_id);
        $curriculumid = $record->curriculumid;
        $courseid = $record->courseid;

        return curriculumpage::_has_capability('elis/program:associate', $curriculumid)
            && coursepage::_has_capability('elis/program:associate', $courseid);
    }

    function can_do_delete() {
        return $this->can_do_edit();
    }

    function has_associate_and_manage_capability() {
        if (has_capability('elis/program:associate', $this->_get_page_context()) ||
            //manage is deprecated but kept for consistency
            has_capability('elis/program:manage', $this->_get_page_context())) {
            return true;
        }
        return false;
    }

}

class curriculumcoursepage extends curriculumcoursebasepage {
    var $pagename = 'currcrs';
    var $tab_page = 'curriculumpage';
    //var $default_tab = 'curriculumcoursepage';

    var $form_class = 'curriculumcourseform';

    var $section = 'curr';

    var $parent_data_class = 'curriculum';

    public function _get_page_params() {
        return array('id' => $this->optional_param('id', 0, PARAM_INT)) + parent::_get_page_params();
    }

    function has_program_view_capability() {
        return has_capability('elis/program:program_view', $this->_get_page_context());
    }

    function can_do_default() {
        $id = $this->required_param('id', PARAM_INT);

        if (curriculumpage::_has_capability('elis/program:program_view', $id)) {
            //allow viewing but not managing associations
        	return true;
        }

        return curriculumpage::_has_capability('elis/program:associate', $id);
    }

    /**
     * Specifies whether the current user can edit prerequisites
     *
     * @return  boolean  true if allowed, otherwise false
     */
    function can_do_prereqedit() {
    	$id = $this->required_param('id', PARAM_INT);

    	return curriculumpage::_has_capability('elis/program:associate', $id);
    }

    /**
     * Specifies whether the current user can edit corequisites
     *
     * @return  boolean  true if allowed, otherwise false
     */
    function can_do_coreqedit() {
    	$id = $this->required_param('id', PARAM_INT);

    	return curriculumpage::_has_capability('elis/program:associate', $id);
    }

    function display_default() {
        $id = $this->required_param('id', PARAM_INT);

        $sort         = optional_param('sort', 'position', PARAM_ALPHA);
        $dir          = optional_param('dir', 'ASC', PARAM_ALPHA);

        $page         = optional_param('page', 0, PARAM_INT);
        $perpage      = optional_param('perpage', 30, PARAM_INT);        // how many per page

        $namesearch   = trim(optional_param('search', '', PARAM_TEXT));
        $alpha        = optional_param('alpha', '', PARAM_ALPHA);

        $columns = array(
            'coursename' => array('header' => get_string('course_name','elis_program'),
                                  'decorator' => array(new record_link_decorator('coursepage',
                                                                                 array('action'=>'view'),
                                                                                 'courseid'),
                                                       'decorate')),
            'required'   => array('header' => get_string('required','elis_program')),
            'frequency'  => array('header' => get_string('frequency','elis_program')),
            'timeperiod' => array('header' => get_string('time_period','elis_program')),
            'position'   => array('header' => get_string('position','elis_program')),
            'buttons'    => array('header' => ''),
        );

        if ($dir !== 'DESC') {
            $dir = 'ASC';
        }
        if (isset($columns[$sort])) {
            $columns[$sort]['sortable'] = $dir;
        }

        $items = curriculumcourse_get_listing($id, $sort, $dir, 0, 0, $namesearch, $alpha);
        $numitems = curriculumcourse_count_records($id, $namesearch, $alpha);

        $this->print_num_items($numitems);
        $this->print_alpha();
        $this->print_search();

        $this->print_list_view($items, $columns, 'courses');

        if (parent::has_associate_and_manage_capability()) {
            $this->print_add_button(array('id' => $id), get_string('curriculumcourse_assigncourse','elis_program'));
        }
    }

    function display_prereqedit() {
        $curid = $this->required_param('id', PARAM_INT);
        $curcrsid = $this->required_param('association_id', PARAM_INT);

        $curcrs = new curriculumcourse($curcrsid);
        $curcrs->seturl(null, array('s'=>$this->pagename, 'action'=>'prereqedit', 'id'=>$curid));
        $prereqform = $curcrs->create_prerequisite_form();

        if ($prereqform->is_cancelled()) {
            $this->display_default();
            return;
        } else if ($prereqform->is_submitted() && $prereqform->is_validated()) {
            $form_data = $prereqform->get_data();
            $output = '';

            $added  = 0;
            $deleted = 0;

            /// Process requested prerequisite deletions.
            if(!empty($form_data->remove) && isset($form_data->sprereqs)) {
                $sprereqs = $form_data->sprereqs;
            } else {
                $sprereqs = array();
            }

            foreach ($sprereqs as $sprereq) {
                if ($curcrs->del_prerequisite($sprereq)) {
                    $deleted++;
                }
            }

            /// Process requested prerequisite additions.
            if(!empty($form_data->add) && isset($form_data->prereqs)) {
                $prereqs = $form_data->prereqs;
            } else {
                $prereqs = array();
            }

            foreach ($prereqs as $prereq) {
                if (coursepage::_has_capability('elis/program:course_view', $prereq)
                    && $curcrs->add_prerequisite($prereq, !empty($form_data->add_to_curriculum))) {
                    $added++;
                }
            }

            if ($deleted > 0) {
                $delString = ($deleted > 1)? 'deleted_prerequisites': 'deleted_prerequisite';
                $output .= get_string($delString, 'elis_program', $deleted);
            }
            if ($added > 0) {
                $addString = ($added > 1)? 'added_prerequisites': 'added_prerequisite';
                $output .= (($deleted > 0) ? ' / ' : '') . get_string($addString, 'elis_program', $added);
            }
            if ($deleted > 0 || $added > 0) {
                $output .= "\n";
            }

            $curriculum = $curcrs->curriculum;
            if ($curriculum->iscustom) {
                $curassid = $this->_db->get_field(curriculumstudent::TABLE, 'id', array('curriculumid'=>$curriculum->id));
                $stucur   = new curriculumstudent($curassid);
                redirect('index.php?s=stucur&amp;section=curr&amp;id='.$stucur->id.'&amp;action=edit', $output, 3);
            }

            echo $output;
            // recreate the form, to reflect changes in the lists
            $prereqform = $curcrs->create_prerequisite_form();
        }

        $prereqform->display();
    }

    function display_coreqedit() {
        $id = $this->required_param('id', PARAM_INT);
        $curcrsid = $this->required_param('association_id', PARAM_INT);

        $curcrs = new curriculumcourse($curcrsid);
        $curcrs->seturl(null, array('s'=>$this->pagename, 'action'=>'coreqedit', 'id'=>$id));
        $coreqform = $curcrs->create_corequisite_form();

        if ($coreqform->is_cancelled()) {
            $this->display_default();
            return;
        } else if ($coreqform->is_submitted() && $coreqform->is_validated()) {
            $form_data = $coreqform->get_data();
            $output = '';

            $added  = 0;
            $deleted = 0;

            /// Process requested corequisite deletions.
            $scoreqs = isset($form_data->scoreqs)? $form_data->scoreqs: array();
            foreach ($scoreqs as $scoreq) {
                if ($curcrs->del_corequisite($scoreq)) {
                    $deleted++;
                }
            }

            /// Process requested corequisite additions.
            $coreqs = isset($form_data->coreqs)? $form_data->coreqs: array();
            foreach ($coreqs as $coreq) {
                if (coursepage::_has_capability('elis/program:course_view', $coreq)
                    && $curcrs->add_corequisite($coreq, !empty($form_data->add_to_curriculum))) {
                    $added++;
                }
            }

            if ($deleted > 0) {
                $delString = ($deleted > 1)? 'deleted_corequisites': 'deleted_corequisite';
                $output .= get_string($delString, 'elis_program', $deleted);
            }
            if ($added > 0) {
                $addString = ($added > 1)? 'added_corequisites': 'added_corequisite';
                $output .= (($deleted > 0) ? ' / ' : '') . get_string($addString, 'elis_program', $added);
            }
            if ($deleted > 0 || $added > 0) {
                $output .= "\n";
            }

            $curriculum = $curcrs->curriculum;
            if ($curriculum->iscustom) {
                $curassid = $this->_db->get_field(curriculumstudent::TABLE, 'id', array('curriculumid'=>$curriculum->id));
                $stucur   = new curriculumstudent($curassid);
                redirect('index.php?s=stucur&amp;section=curr&amp;id='.$stucur->id.'&amp;action=edit', $output, 3);
            }

            echo $output;
            // recreate the form, to reflect changes in the lists
            $coreqform = $curcrs->create_corequisite_form();
        }

        $coreqform->display();
    }
}

class coursecurriculumpage extends curriculumcoursebasepage {
    var $pagename = 'crscurr';
    var $tab_page = 'coursepage';
    //var $default_tab = 'coursecurriculumpage';

    var $form_class = 'coursecurriculumform';

    var $section = 'curr';

    var $parent_data_class = 'course';

    public function _get_page_params() {
        return array('id' => $this->optional_param('id', 0, PARAM_INT)) + parent::_get_page_params();
    }

    function can_do_default() {
        $id = $this->required_param('id', PARAM_INT);

        if (coursepage::_has_capability('elis/program:program_view', $id)) {
            //allow viewing but not managing associations
        	return true;
        }

        return coursepage::_has_capability('elis/program:associate', $id);
    }

    function display_default() {
        global $OUTPUT;

        $id = $this->required_param('id', PARAM_INT);

        $sort         = optional_param('sort', 'name', PARAM_ALPHA);
        $dir          = optional_param('dir', 'ASC', PARAM_ALPHA);

        $page         = optional_param('page', 0, PARAM_INT);
        $perpage      = optional_param('perpage', 30, PARAM_INT);        // how many per page

        $namesearch   = trim(optional_param('search', '', PARAM_TEXT));
        $alpha        = optional_param('alpha', '', PARAM_ALPHA);

        $columns = array(
            'curriculumname'    => array('header' => get_string('curriculum_name','elis_program'),
                                         'decorator' => array(new record_link_decorator('curriculumpage',
                                                                                        array('action'=>'view'),
                                                                                        'curriculumid'),
                                                              'decorate')),
            'required'          => array('header' => get_string('required','elis_program')),
            'frequency'         => array('header' => get_string('frequency','elis_program')),
            'timeperiod'        => array('header' => get_string('time_period','elis_program')),
            'position'          => array('header' => get_string('position','elis_program')),
            'buttons'           => array('header' => ''),
        );

        $items = curriculumcourse_get_curriculum_listing($id, $sort, $dir, 0, 0, $namesearch, $alpha);
        $numitems = curriculumcourse_count_curriculum_records($id, $namesearch, $alpha);

        $this->print_num_items($numitems);
        $this->print_alpha();
        $this->print_search();

        $this->print_list_view($items, $columns, 'curricula');

        if (parent::has_associate_and_manage_capability()) {
            $this->print_add_button(array('id' => $id), get_string('course_assigncurriculum', 'elis_program'));
        }

        if ($this->has_program_create_capability()) {
            echo '<div align="center">';
            $options = array_merge(array('s' => 'cfc', 'id' => $id, 'cfccourseid' => $id));
            $button = new single_button(new moodle_url('index.php', $options), get_string('makecurcourse','elis_program'), 'get', array('disabled'=>false, 'title'=>get_string('makecurcourse','elis_program'), 'id'=>''));
            echo $OUTPUT->render($button);
            echo '</div>';
        }
    }


    function has_program_create_capability() {
        return has_capability('elis/program:program_create', $this->_get_page_context());
    }

    // disable prereq/coreq editing from the course page
    function can_do_prereqedit() {
        return false;
    }

    function can_do_coreqedit() {
        return false;
    }
}

class curriculumcourse_page_table extends association_page_table {
    function get_item_display_required($column, $item) {
        return $this->display_yesno_item($column, $item);
    }

    function get_default_sort_column() {
        return 'position';
    }
}
