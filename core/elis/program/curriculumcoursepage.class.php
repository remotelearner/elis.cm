<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @package    elis_program
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') or die();

require_once(elispm::lib('associationpage.class.php'));
require_once(elispm::lib('data/curriculumcourse.class.php'));
require_once(elispm::lib('data/curriculumstudent.class.php'));
require_once(elispm::lib('contexts.php'));
require_once(elispm::file('curriculumpage.class.php'));
require_once(elispm::file('coursepage.class.php'));
require_once(elispm::file('form/curriculumcourseform.class.php'));

/**
 * Deepsight assignment page for program - course associations.
 */
class curriculumcoursepage extends deepsightpage {
    /**
     * @var string A unique name for the page.
     */
    public $pagename = 'currcrs';

    /**
     * @var string The section of the page.
     */
    public $section = 'curr';

    /**
     * @var string The page to get tabs from.
     */
    public $tab_page = 'curriculumpage';

    /**
     * @var string The main data class.
     */
    public $data_class = 'curriculumcourse';

    /**
     * @var string The page's parent.
     */
    public $parent_page;

    /**
     * @var string The page's context.
     */
    public $context;

    /**
     * Constructor.
     * @param array $params An array of parameters for the page.
     */
    public function __construct(array $params = null) {
        $this->context = parent::_get_page_context();
        parent::__construct($params);
    }

    /**
     * Get the context of the current program.
     * @return context_elis_program The current program context object.
     */
    protected function get_context() {
        if (!isset($this->context)) {
            $id = required_param('id', PARAM_INT);
            $this->context = context_elis_program::instance($id);
        }
        return $this->context;
    }

    /**
     * Construct the assigned datatable.
     * @param string $uniqid A unique ID to assign to the datatable object.
     * @return deepsight_datatable The datatable object.
     */
    protected function construct_assigned_table($uniqid = null) {
        global $DB;
        $programid = $this->required_param('id', PARAM_INT);
        $endpoint = qualified_me().'&action=deepsight_response&tabletype=assigned&id='.$programid;
        $table = new deepsight_datatable_programcourse_assigned($DB, 'assigned', $endpoint, $uniqid);
        $table->set_programid($programid);
        return $table;
    }

    /**
     * Construct the unassigned datatable.
     * @param string $uniqid A unique ID to assign to the datatable object.
     * @return deepsight_datatable The datatable object.
     */
    protected function construct_unassigned_table($uniqid = null) {
        global $DB;
        $programid = $this->required_param('id', PARAM_INT);
        $endpoint = qualified_me().'&action=deepsight_response&tabletype=unassigned&id='.$programid;
        $table = new deepsight_datatable_programcourse_available($DB, 'unassigned', $endpoint, $uniqid);
        $table->set_programid($programid);
        return $table;
    }

    /**
     * Assignment permission is handled at the action-object level.
     * @return bool true
     */
    public function can_do_action_programcourse_assign() {
        return true;
    }

    /**
     * Edit permission is handled at the action-object level.
     * @return bool true
     */
    public function can_do_action_programcourse_edit() {
        return true;
    }

    /**
     * Unassignment permission is handled at the action-object level.
     * @return bool true
     */
    public function can_do_action_programcourse_unassign() {
        return true;
    }

    /**
     * Whether the user has access to see the main page (assigned list)
     * @return bool Whether the user has access.
     */
    public function can_do_default() {
        global $USER;
        $id = $this->required_param('id', PARAM_INT);
        $requiredperms = array('elis/program:program_view', 'elis/program:associate');
        foreach ($requiredperms as $perm) {
            $ctx = pm_context_set::for_user_with_capability('curriculum', $perm, $USER->id);
            if ($ctx->context_allowed($id, 'curriculum') !== true) {
                return false;
            }
        }
        return true;
    }

    /**
     * Determine whether the current user can assign tracks to the viewed userset.
     * @return bool Whether the user can assign tracks to this userset.
     */
    public function can_do_add() {
        return $this->can_do_default();
    }

    /**
     * Specifies whether the current user can edit prerequisites
     * @return bool true if allowed, otherwise false
     */
    public function can_do_prereqedit() {
        return $this->can_do_default();
    }

    /**
     * Specifies whether the current user can edit corequisites
     * @return bool true if allowed, otherwise false
     */
    public function can_do_coreqedit() {
        return $this->can_do_default();
    }

    /**
     * Display form to manage prerequisites.
     */
    public function display_prereqedit() {
        $curid = $this->required_param('id', PARAM_INT);
        $curcrsid = $this->required_param('association_id', PARAM_INT);

        $curcrs = new curriculumcourse($curcrsid);
        $curcrs->seturl(null, array('s'=>$this->pagename, 'action'=>'prereqedit', 'id'=>$curid));
        $prereqform = $curcrs->create_prerequisite_form();

        if ($prereqform->is_cancelled()) {
            $this->display_default();
            return;
        } else if ($prereqform->is_submitted() && $prereqform->is_validated()) {
            $formdata = $prereqform->get_data();
            $output = '';

            $added  = 0;
            $deleted = 0;

            // Process requested prerequisite deletions.
            if (!empty($formdata->remove) && isset($formdata->sprereqs)) {
                $sprereqs = $formdata->sprereqs;
            } else {
                $sprereqs = array();
            }

            foreach ($sprereqs as $sprereq) {
                if ($curcrs->del_prerequisite($sprereq)) {
                    $deleted++;
                }
            }

            // Process requested prerequisite additions.
            if (!empty($formdata->add) && isset($formdata->prereqs)) {
                $prereqs = $formdata->prereqs;
            } else {
                $prereqs = array();
            }

            // TODO: Ugly, this needs to be overhauled.
            $cpage = new coursepage();
            foreach ($prereqs as $prereq) {
                if ($cpage->_has_capability('elis/program:course_view', $prereq)
                        && $curcrs->add_prerequisite($prereq, !empty($formdata->add_to_curriculum))) {
                    $added++;
                }
            }

            if ($deleted > 0) {
                $delstring = ($deleted > 1) ? 'deleted_prerequisites' : 'deleted_prerequisite';
                $output .= get_string($delstring, 'elis_program', $deleted);
            }
            if ($added > 0) {
                $addstring = ($added > 1) ? 'added_prerequisites' : 'added_prerequisite';
                $output .= (($deleted > 0) ? ' / ' : '').get_string($addstring, 'elis_program', $added);
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
            // Recreate the form, to reflect changes in the lists.
            $prereqform = $curcrs->create_prerequisite_form();
        }

        $prereqform->display();
    }

    /**
     * Display form to manage corequisites.
     */
    public function display_coreqedit() {
        $id = $this->required_param('id', PARAM_INT);
        $curcrsid = $this->required_param('association_id', PARAM_INT);

        $curcrs = new curriculumcourse($curcrsid);
        $curcrs->seturl(null, array('s'=>$this->pagename, 'action'=>'coreqedit', 'id'=>$id));
        $coreqform = $curcrs->create_corequisite_form();

        if ($coreqform->is_cancelled()) {
            $this->display_default();
            return;
        } else if ($coreqform->is_submitted() && $coreqform->is_validated()) {
            $formdata = $coreqform->get_data();
            $output = '';

            $added  = 0;
            $deleted = 0;

            // Process requested corequisite deletions.
            $scoreqs = (isset($formdata->scoreqs)) ? $formdata->scoreqs : array();
            foreach ($scoreqs as $scoreq) {
                if ($curcrs->del_corequisite($scoreq)) {
                    $deleted++;
                }
            }

            // Process requested corequisite additions.
            $coreqs = (isset($formdata->coreqs)) ? $formdata->coreqs : array();

            // TODO: Ugly, this needs to be overhauled.
            $cpage = new coursepage();
            foreach ($coreqs as $coreq) {
                if ($cpage->_has_capability('elis/program:course_view', $coreq)
                        && $curcrs->add_corequisite($coreq, !empty($formdata->add_to_curriculum))) {
                    $added++;
                }
            }

            if ($deleted > 0) {
                $delstring = ($deleted > 1) ? 'deleted_corequisites' : 'deleted_corequisite';
                $output .= get_string($delstring, 'elis_program', $deleted);
            }
            if ($added > 0) {
                $addstring = ($added > 1) ? 'added_corequisites' : 'added_corequisite';
                $output .= (($deleted > 0) ? ' / ' : '').get_string($addstring, 'elis_program', $added);
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
            // Recreate the form, to reflect changes in the lists.
            $coreqform = $curcrs->create_corequisite_form();
        }

        $coreqform->display();
    }
}

/**
 * Deepsight assignment page for course - program associations.
 */
class coursecurriculumpage extends deepsightpage {
    /**
     * @var string A unique name for the page.
     */
    public $pagename = 'crscurr';

    /**
     * @var string The section of the page.
     */
    public $section = 'curr';

    /**
     * @var string The page to get tabs from.
     */
    public $tab_page = 'coursepage';

    /**
     * @var string The main data class.
     */
    public $data_class = 'curriculumcourse';

    /**
     * @var string The page's parent.
     */
    public $parent_page;

    /**
     * @var string The page's context.
     */
    public $context;

    /**
     * Constructor.
     * @param array $params An array of parameters for the page.
     */
    public function __construct(array $params = null) {
        $this->context = parent::_get_page_context();
        parent::__construct($params);
    }

    /**
     * Get the context of the current course.
     * @return context_elis_course The current course context object.
     */
    protected function get_context() {
        if (!isset($this->context)) {
            $id = required_param('id', PARAM_INT);
            $this->context = context_elis_course::instance($id);
        }
        return $this->context;
    }

    /**
     * Construct the assigned datatable.
     * @param string $uniqid A unique ID to assign to the datatable object.
     * @return deepsight_datatable The datatable object.
     */
    protected function construct_assigned_table($uniqid = null) {
        global $DB;
        $courseid = $this->required_param('id', PARAM_INT);
        $endpoint = qualified_me().'&action=deepsight_response&tabletype=assigned&id='.$courseid;
        $table = new deepsight_datatable_courseprogram_assigned($DB, 'assigned', $endpoint, $uniqid);
        $table->set_courseid($courseid);
        return $table;
    }

    /**
     * Construct the unassigned datatable.
     * @param string $uniqid A unique ID to assign to the datatable object.
     * @return deepsight_datatable The datatable object.
     */
    protected function construct_unassigned_table($uniqid = null) {
        global $DB;
        $courseid = $this->required_param('id', PARAM_INT);
        $endpoint = qualified_me().'&action=deepsight_response&tabletype=unassigned&id='.$courseid;
        $table = new deepsight_datatable_courseprogram_available($DB, 'unassigned', $endpoint, $uniqid);
        $table->set_courseid($courseid);
        return $table;
    }

    /**
     * Assignment permission is handled at the action-object level.
     * @return bool true
     */
    public function can_do_action_courseprogram_assign() {
        return true;
    }

    /**
     * Edit permission is handled at the action-object level.
     * @return bool true
     */
    public function can_do_action_courseprogram_edit() {
        return true;
    }

    /**
     * Unassignment permission is handled at the action-object level.
     * @return bool true
     */
    public function can_do_action_courseprogram_unassign() {
        return true;
    }

    /**
     * Whether the user has access to see the main page (assigned list)
     * @return bool Whether the user has access.
     */
    public function can_do_default() {
        global $USER;
        $id = $this->required_param('id', PARAM_INT);
        $requiredperms = array('elis/program:course_view', 'elis/program:associate');
        foreach ($requiredperms as $perm) {
            $ctx = pm_context_set::for_user_with_capability('course', $perm, $USER->id);
            if ($ctx->context_allowed($id, 'course') !== true) {
                return false;
            }
        }
        return true;
    }

    /**
     * Determine whether the current user can assign tracks to the viewed userset.
     * @return bool Whether the user can assign tracks to this userset.
     */
    public function can_do_add() {
        return $this->can_do_default();
    }

    /**
     * Display assigned table.
     */
    public function display_default() {
        global $OUTPUT;

        if (false && has_capability('elis/program:program_create', $this->_get_page_context())) {
            echo '<div align="center">';
            $options = array_merge(array('s' => 'cfc', 'id' => $id, 'cfccourseid' => $id));
            $button = new single_button(new moodle_url('index.php', $options), get_string('makecurcourse', 'elis_program'), 'get',
                    array('disabled'=>false, 'title'=>get_string('makecurcourse', 'elis_program'), 'id'=>''));
            echo $OUTPUT->render($button);
            echo '</div>';
        }

        parent::display_default();
    }
}