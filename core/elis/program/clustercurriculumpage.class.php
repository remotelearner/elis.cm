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
require_once(elispm::lib('data/clustercurriculum.class.php'));
require_once(elispm::file('curriculumpage.class.php'));
require_once(elispm::file('usersetpage.class.php'));
require_once(elispm::file('form/clustercurriculumform.class.php'));
require_once(elispm::file('form/clustercurriculumeditform.class.php'));

/**
 * Deepsight assignment page for userset - program associations.
 */
class clustercurriculumpage extends deepsightpage {
    public $pagename = 'clstcur';
    public $section = 'users';
    public $tab_page = 'usersetpage';
    public $data_class = 'clustercurriculum';
    public $parent_page;
    public $context;

    const CPY_CURR_PREFIX = 'add_curr_';
    const CPY_CURR_TRK_PREFIX = 'add_trk_curr_';
    const CPY_CURR_CRS_PREFIX = 'add_crs_curr_';
    const CPY_CURR_CLS_PREFIX = 'add_cls_curr_';
    const CPY_CURR_MDLCRS_PREFIX = 'add_mdlcrs_curr_';

    /**
     * Constructor.
     * @param array $params An array of parameters for the page.
     */
    public function __construct(array $params = null) {
        $this->context = parent::_get_page_context();
        parent::__construct($params);
    }

    /**
     * Get the context of the current userset.
     * @return context_elis_userset The current userset context object.
     */
    protected function get_context() {
        if (!isset($this->context)) {
            $id = required_param('id', PARAM_INT);
            $this->context = context_elis_userset::instance($id);
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
        $usersetid = $this->required_param('id', PARAM_INT);
        $endpoint = qualified_me().'&action=deepsight_response&tabletype=assigned&id='.$usersetid;
        $table = new deepsight_datatable_usersetprogram_assigned($DB, 'assigned', $endpoint, $uniqid);
        $table->set_usersetid($usersetid);
        return $table;
    }

    /**
     * Construct the unassigned datatable.
     * @param string $uniqid A unique ID to assign to the datatable object.
     * @return deepsight_datatable The datatable object.
     */
    protected function construct_unassigned_table($uniqid = null) {
        global $DB;
        $usersetid = $this->required_param('id', PARAM_INT);
        $endpoint = qualified_me().'&action=deepsight_response&tabletype=unassigned&id='.$usersetid;
        $table = new deepsight_datatable_usersetprogram_available($DB, 'unassigned', $endpoint, $uniqid);
        $table->set_usersetid($usersetid);
        return $table;
    }

    /**
     * Assignment permission is handled at the action-object level.
     * @return bool true
     */
    public function can_do_action_usersetprogramassign() {
        return true;
    }

    /**
     * Edit permission is handled at the action-object level.
     * @return bool true
     */
    public function can_do_action_usersetprogramedit() {
        return true;
    }

    /**
     * Unassignment permission is handled at the action-object level.
     * @return bool true
     */
    public function can_do_action_usersetprogramunassign() {
        return true;
    }

    /**
     * Whether the user has access to see the main page (assigned list)
     * @return bool Whether the user has access.
     */
    public function can_do_default() {
        global $USER;
        $id = $this->required_param('id', PARAM_INT);
        $requiredperms = array('elis/program:userset_view', 'elis/program:associate');
        foreach ($requiredperms as $perm) {
            $ctx = pm_context_set::for_user_with_capability('cluster', $perm, $USER->id);
            if ($ctx->context_allowed($id, 'cluster') !== true) {
                return false;
            }
        }
        return true;
    }

    /**
     * Determine whether the current user can assign programs to the viewed userset.
     * @return bool Whether the user can assign programs to this userset.
     */
    public function can_do_add() {
        return $this->can_do_default();
    }

    /**
     * Displays the count of users passed, failed, and not complete above the datatable.
     */
    public function display_default() {
        global $OUTPUT;
        $id = $this->required_param('id', PARAM_INT);
        $options = array('id' => $id, 's' => 'clstcur', 'action' => 'copycurredit');
        $button = new single_button(new moodle_url('index.php', $options), get_string('userset_cpycurr', 'elis_program'), 'get');

        // Add a more specific CSS class.
        $button->class = str_replace('singlebutton', 'singlebutton clscpycurrbtn ', $button->class);

        echo '<div style="display:inline-block;text-align:center;width:100%;margin-bottom:25px">';
        echo get_string('userset_cpycurr_instruction', 'elis_program');
        echo $OUTPUT->help_icon('program_copy', 'elis_program');
        echo $OUTPUT->render($button);
        echo '</div>';

        parent::display_default();
    }

    /**
     * Display copy curriculum action.
     */
    public function display_copycurredit() {
        global $CFG, $USER, $PAGE, $DB, $OUTPUT;

        $PAGE->requires->js('/elis/program/js/clustercurriculumpage.js');

        $id = $this->required_param('id', PARAM_INT);

        // Create a list of curricula to be excluded.
        $curriculumshown = array();

        $table = new html_table();
        $table->head = array(
                get_string('userset_cpyclustname', 'elis_program'),
                get_string('userset_cpycurname', 'elis_program'),
                get_string('userset_cpyadd', 'elis_program'),
                get_string('userset_cpytrkcpy', 'elis_program'),
                get_string('userset_cpycrscpy', 'elis_program'),
                get_string('userset_cpyclscpy', 'elis_program'),
                get_string('userset_cpymdlclscpy', 'elis_program'),
        );

        $table->class = 'cluster_copy_curriculum';

        // Get all clusters.
        $sort = 'name';
        $dir = 'ASC';
        $clusters = cluster_get_listing($sort, $dir, 0);
        $clusterlist = array();

        $sql = 'SELECT * from {'.userset::TABLE.'}';

        // Exclude clusters the user does not have the capability to manage/see.
        $context = get_contexts_by_capability_for_user('cluster', 'elis/program:userset_view', $USER->id);

        echo '<form action="index.php" method="post">';

        $mdlcrsoptions = array('copyalways' => get_string('program_copy_mdlcrs_copyalways', 'elis_program'),
                               'copyautocreated' => get_string('program_copy_mdlcrs_copyautocreated', 'elis_program'),
                               'autocreatenew' => get_string('program_copy_mdlcrs_autocreatenew', 'elis_program'),
                               'link' => get_string('program_copy_mdlcrs_link', 'elis_program')
            );

        $contexts = curriculumpage::get_contexts('elis/program:associate');
        foreach ($clusters as $clusid => $clusdata) {
            if (!$context->context_allowed($clusid, 'cluster')) {
                continue;
            }

            $assocurr = clustercurriculum::get_curricula($clusid);
            if (!empty($assocurr)) {

                $first = true;
                foreach ($assocurr as $assocurrrec) {

                    // Add to list of curricula to exclude.
                    $curriculumshown[] = $assocurrrec->curriculumid;

                    // Skip over this clusters associated curricula.
                    if ($clusid == $id) {
                        continue;
                    }

                    // Skip over curricula that user cannot associate.
                    if (!$contexts->context_allowed($assocurrrec->curriculumid, 'curriculum')) {
                        continue;
                    }

                    if ($first) {
                        $curname = format_string($clusdata->name);
                        $first = false;
                    } else {
                        $curname = '';
                    }
                    // Disable the last 3 options unless the first is checked.
                    $checkboxids = "'".self::CPY_CURR_PREFIX.$assocurrrec->curriculumid."',
                                     '".self::CPY_CURR_TRK_PREFIX.$assocurrrec->curriculumid."',
                                     '".self::CPY_CURR_CRS_PREFIX.$assocurrrec->curriculumid."',
                                     '".self::CPY_CURR_CLS_PREFIX.$assocurrrec->curriculumid."'";
                    $togglecheckboxes = "cluster_copy_checkbox_changed(".$checkboxids.");";
                    $table->data[] = array(
                            $curname,
                            format_string($assocurrrec->name),
                            html_writer::checkbox(self::CPY_CURR_PREFIX.$assocurrrec->curriculumid, 1, false, '',
                                    array('id' => self::CPY_CURR_PREFIX.$assocurrrec->curriculumid, 'onclick' => $togglecheckboxes)),
                            html_writer::checkbox(self::CPY_CURR_TRK_PREFIX.$assocurrrec->curriculumid, 1, false, '',
                                    array('disabled' => true, 'id' => self::CPY_CURR_TRK_PREFIX.$assocurrrec->curriculumid)),
                            html_writer::checkbox(self::CPY_CURR_CRS_PREFIX.$assocurrrec->curriculumid, 1, false, '',
                                    array('disabled' => true, 'id' => self::CPY_CURR_CRS_PREFIX.$assocurrrec->curriculumid)),
                            html_writer::checkbox(self::CPY_CURR_CLS_PREFIX.$assocurrrec->curriculumid, 1, false, '',
                                    array('disabled' => true, 'id' => self::CPY_CURR_CLS_PREFIX.$assocurrrec->curriculumid)),
                            html_writer::select($mdlcrsoptions, self::CPY_CURR_MDLCRS_PREFIX.$assocurrrec->curriculumid),
                    );
                    $table->rowclass[] = 'clus_cpy_row';
                }
            }
        }

        // Add unassociated row to table.
        $table->data[] = array(get_string('usersetprogram_unassociated', 'elis_program'),
                               '', '', '', '', '', '');
        $table->rowclass[] = 'clus_cpy_row unassigned';

        // Get all curriculums, removing curricula that have already been listed.
        $curriculums = curriculum_get_listing($sort, $dir, 0, 0, '', '', $contexts);
        foreach ($curriculums as $curriculumid => $curriculumdata) {
            if (false === array_search($curriculumid, $curriculumshown)) {
                $checkboxids = "'".self::CPY_CURR_PREFIX.$curriculumid."',
                                 '".self::CPY_CURR_TRK_PREFIX.$curriculumid."',
                                 '".self::CPY_CURR_CRS_PREFIX.$curriculumid."',
                                 '".self::CPY_CURR_CLS_PREFIX.$curriculumid."'";
                $togglecheckboxes = "cluster_copy_checkbox_changed(".$checkboxids.");";
                $table->data[] = array(
                        '',
                        format_string($curriculumdata->name),
                        html_writer::checkbox(self::CPY_CURR_PREFIX.$curriculumid, 1, false, '',
                                array('id' => self::CPY_CURR_PREFIX.$curriculumid, 'onclick' => $togglecheckboxes)),
                        html_writer::checkbox(self::CPY_CURR_TRK_PREFIX.$curriculumid, 1, false, '',
                                array('disabled' => true, 'id' => self::CPY_CURR_TRK_PREFIX.$curriculumid)),
                        html_writer::checkbox(self::CPY_CURR_CRS_PREFIX.$curriculumid, 1, false, '',
                                array('disabled' => true, 'id' => self::CPY_CURR_CRS_PREFIX.$curriculumid)),
                        html_writer::checkbox(self::CPY_CURR_CLS_PREFIX.$curriculumid, 1, false, '',
                                array('disabled' => true, 'id' => self::CPY_CURR_CLS_PREFIX.$curriculumid)),
                        html_writer::select($mdlcrsoptions, self::CPY_CURR_MDLCRS_PREFIX.$curriculumid),
                );

                $table->rowclass[] = 'clus_cpy_row';

            }
        }

        $currselectall = '<div class="currselectall">';
        $currselectall .= '<a id="clus_currcpy_select_all" onclick="cluster_copycurriculum_set_all_selected()">';
        $currselectall .= get_string('selectall').'</a></div>';

        $trkselectall = '<div class="trkselectall"><a id="clus_trkcpy_select_all" onclick="cluster_copytrack_set_all_selected()">';
        $trkselectall .= get_string('selectall').'</a></div>';

        $crsselectall = '<div class="crsselectall"><a id="clus_crscpy_select_all" onclick="cluster_copycourse_set_all_selected()">';
        $crsselectall .= get_string('selectall').'</a></div>';

        $clsselectall = '<div class="clsselectall"><a id="clus_crscpy_select_all" onclick="cluster_copyclass_set_all_selected()">';
        $clsselectall .= get_string('selectall').'</a></div>';

        $table->data[] = array('', '', $currselectall, $trkselectall, $crsselectall, $clsselectall);
        $table->rowclass[] = 'clus_cpy_row select_all_row';

        echo html_writer::table($table);

        echo '<div class="clus_curr_cpy_save_exit">';
        echo '<input type="submit" name="save" value="'.get_string('userset_saveexit', 'elis_program').'">';
        echo '<div class="hidden">';
        echo '<input type="hidden" name="id" value="'.$id.'">';
        echo '<input type="hidden" name="s" value="clstcur">';
        echo '<input type="hidden" name="action" value="copycurr">';
        echo '<input type="hidden" name="sesskey" value="'.$USER->sesskey.'">';
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }

    /**
     * Do copy curriculum.
     */
    public function do_copycurr() {
        global $CFG;

        // TODO: replace print_object messages with notice messages.
        $sesskey = required_param('sesskey', PARAM_TEXT);
        if (!confirm_sesskey($sesskey)) {
            print_error('invalidsesskey', 'error', 'index.php');
        }

        $data = (array) data_submitted();
        $clusterid = $this->required_param('id', PARAM_INT);

        if (empty($data)) {
            notify(get_string('nodatasubmit', 'elis_program'), 'red');
        }

        $targetcluster = new userset($clusterid);

        // Retrieve all of the curriculums that need to be copied and assigned.
        $prefixlen = strlen(self::CPY_CURR_PREFIX);
        foreach ($data as $datakey => $datavalue) {

            if (0 === strncmp($datakey, self::CPY_CURR_PREFIX, $prefixlen)) {

                $currid = (int)substr($datakey, $prefixlen);
                if (!$currid) {
                    continue;
                }
                $curr = new curriculum($currid);
                $options = array('targetcluster' => $targetcluster);
                if ($this->optional_param(self::CPY_CURR_TRK_PREFIX.$currid, 0, PARAM_INT)) {
                    $options['tracks'] = true;
                }
                if ($this->optional_param(self::CPY_CURR_CRS_PREFIX.$currid, 0, PARAM_INT)) {
                    $options['courses'] = true;
                }
                if ($this->optional_param(self::CPY_CURR_CLS_PREFIX.$currid, 0, PARAM_INT)) {
                    $options['classes'] = true;
                }
                $options['moodlecourses'] = $this->optional_param(self::CPY_CURR_MDLCRS_PREFIX.$currid, 'copyalways', PARAM_ALPHA);
                $rv = $curr->duplicate($options);
                if (!empty($rv['errors'])) {
                    foreach ($rv['errors'] as $error) {
                        notify($error);
                    }
                }

                /*
                 * The following block of code performs any necessary post-processing,
                 * primarily used for copying role assignments
                 */

                // We need to handle curricula first in case role assignments at lower levels become redundant.
                if (!empty($rv['curricula'])) {
                    $curriculum = new stdClass;
                    $curriculum->id = $rv['curricula'][$curr->id];
                    curriculumpage::after_cm_entity_add($curriculum);
                }

                if (!empty($rv['tracks'])) {
                    foreach ($rv['tracks'] as $trackid) {
                        $track = new stdClass;
                        $track->id = $trackid;
                        trackpage::after_cm_entity_add($track);
                    }
                }

                if (!empty($rv['courses'])) {
                    foreach ($rv['courses'] as $courseid) {
                        $course = new stdClass;
                        $course->id = $courseid;
                        coursepage::after_cm_entity_add($course);
                    }
                }

                if (!empty($rv['classes'])) {
                    foreach ($rv['classes'] as $classid) {
                        $class = new stdClass;
                        $class->id = $classid;
                        pmclasspage::after_cm_entity_add($class);
                    }
                }

                if (!empty($rv['curricula'])) {
                    $newcurr = new curriculum($rv['curricula'][$curr->id]);
                    $tempcurr = new stdClass;
                    $tempcurr->name = $curr->name;
                    $tempcurr->newname = $newcurr->name;
                    notify(get_string('clustcpycurr', 'elis_program', $tempcurr), 'notifysuccess');
                }
            }
        }

        $tmppage = new clustercurriculumpage(array('id' => $data['id']));
        redirect($tmppage->url, '', 2);
    }
}


/**
 * Deepsight assignment page for userset - program associations.
 */
class curriculumclusterpage extends deepsightpage {
    public $pagename = 'curclst';
    public $section = 'curr';
    public $tab_page = 'curriculumpage';
    public $data_class = 'clustercurriculum';
    public $parent_page;
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
        $table = new deepsight_datatable_programuserset_assigned($DB, 'assigned', $endpoint, $uniqid);
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
        $table = new deepsight_datatable_programuserset_available($DB, 'unassigned', $endpoint, $uniqid);
        $table->set_programid($programid);
        return $table;
    }

    /**
     * Assignment permission is handled at the action-object level.
     * @return bool true
     */
    public function can_do_action_programusersetassign() {
        return true;
    }

    /**
     * Edit permission is handled at the action-object level.
     * @return bool true
     */
    public function can_do_action_programusersetedit() {
        return true;
    }


    /**
     * Unassignment permission is handled at the action-object level.
     * @return bool true
     */
    public function can_do_action_programusersetunassign() {
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
     * Determine whether the current user can assign usersets to the viewed program.
     * @return bool Whether the user can assign usersets to this program.
     */
    public function can_do_add() {
        return $this->can_do_default();
    }
}
